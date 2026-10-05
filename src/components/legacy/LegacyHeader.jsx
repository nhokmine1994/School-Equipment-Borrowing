import { useEffect, useMemo, useRef, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../../services/api';
import { setAuthStatus, setUser } from '../../store/slices/appSlice';

const appBase = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';

function getInitials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

export default function LegacyHeader() {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const user = useSelector((state) => state.app.user);
  const [menuOpen, setMenuOpen] = useState(false);
  const [notificationOpen, setNotificationOpen] = useState(false);
  const headerRightRef = useRef(null);
  const [notifications, setNotifications] = useState({ counts: {}, items: [] });
  const [seenNotifications, setSeenNotifications] = useState(() => new Set());

  const displayName = useMemo(
    () => user?.name || user?.display_name || user?.username || '',
    [user],
  );
  const isLoggedIn = Boolean(user?.username);
  const userRole = String(user?.role || '').toLowerCase();

  useEffect(() => {
    if (!isLoggedIn) return undefined;
    try {
      const stored = JSON.parse(localStorage.getItem(`seb_seen_notifications_${user?.username}`) || '[]');
      setSeenNotifications(new Set(stored));
    } catch { setSeenNotifications(new Set()); }
    let active = true;
    const refresh = () => api.getUserNotifications().then((result) => {
      if (active && result?.success) setNotifications(result.data);
    });
    refresh();
    const timer = window.setInterval(refresh, 15000);
    return () => { active = false; window.clearInterval(timer); };
  }, [isLoggedIn, user?.username]);

  useEffect(() => {
    const closeOnOutsideClick = (event) => {
      if (headerRightRef.current && !headerRightRef.current.contains(event.target)) {
        setNotificationOpen(false);
        setMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', closeOnOutsideClick);
    return () => document.removeEventListener('mousedown', closeOnOutsideClick);
  }, []);

  const notificationKey = (item) => `${item.borrowId || item.message}`;
  const visibleNotifications = (notifications.items || []).filter((item) => !seenNotifications.has(notificationKey(item)));
  const notificationCount = visibleNotifications.length;
  const markNotificationSeen = (item) => {
    const key = notificationKey(item);
    setSeenNotifications((current) => {
      const next = new Set(current);
      next.add(key);
      localStorage.setItem(`seb_seen_notifications_${user?.username}`, JSON.stringify([...next]));
      return next;
    });
  };

  const handleLogout = async () => {
    await api.logoutUser();
    dispatch(setUser({ name: 'Khách', role: 'Guest', username: '' }));
    dispatch(setAuthStatus('guest'));
    navigate('/login', { replace: true });
  };

  return (
    <header className="header-banner">
      <div className="header-logo-box">
        <Link to="/" aria-label="Trang chủ">
          <img src={`${appBase}/Images/logo.png`} alt="Logo Lộc An" />
        </Link>
      </div>
      <div className="header-title-group">
        <h2>TRƯỜNG TRUNG HỌC CƠ SỞ LỘC AN</h2>
        <h1>HỆ THỐNG MƯỢN/TRẢ THIẾT BỊ ( SEB )</h1>
      </div>
      <div className="header-right" ref={headerRightRef}>
        {!isLoggedIn ? (
          <>
            <button type="button" className="icon-btn" aria-label="Đăng nhập" onClick={() => navigate('/login')}>
              <i className="far fa-user" />
            </button>
          </>
        ) : (
          <>
          <div
            className="user-profile-menu"
            id="userProfileMenu"
           style={{ display: 'flex', position: 'relative' }}
           >
            <span className="user-profile-name" title={displayName}>{displayName}</span>
            <button
              type="button"
              className="icon-btn avatar-btn"
              id="avatarBtn"
              onClick={() => setMenuOpen((value) => !value)}
            >
              <span className="avatar-initials" aria-hidden="true">{getInitials(displayName)}</span>
            </button>
            {menuOpen ? (
              <div
                className="dropdown-content"
                id="avatarDropdown"
                style={{
                  display: 'flex',
                  position: 'absolute',
                  right: 0,
                  top: '120%',
                  minWidth: 220,
                  backgroundColor: '#fff',
                  boxShadow: '0 8px 24px rgba(0,0,0,0.15)',
                  borderRadius: 8,
                  zIndex: 100,
                  overflow: 'hidden',
                  border: '1px solid #e0e0e0',
                  flexDirection: 'column',
                  textAlign: 'left',
                }}
              >
                <div
                  style={{
                    padding: '10px 16px',
                    background: '#f5f5f5',
                    borderBottom: '1px solid #e0e0e0',
                    fontWeight: 600,
                    color: '#333',
                  }}
                >
                  {displayName}
                </div>
                <Link to="/profile" className="dropdown-item" onClick={() => setMenuOpen(false)}>
                  <i className="fas fa-id-card" /> Thông tin cá nhân
                </Link>
                <Link to="/borrow" className="dropdown-item" onClick={() => setMenuOpen(false)}>
                  <i className="fas fa-history" /> Lịch sử mượn/trả
                </Link>
                <Link to="/settings" className="dropdown-item" onClick={() => setMenuOpen(false)}>
                  <i className="fas fa-cog" /> Cài đặt
                </Link>
                {userRole === 'admin' ? (
                  <a href="Page/admin_panel.php" className="dropdown-item">
                    <i className="fas fa-user-shield" /> Chế độ quản trị viên
                  </a>
                ) : null}
                <div style={{ borderTop: '1px solid #eee', margin: '4px 0' }} />
                <button
                  type="button"
                  className="dropdown-item"
                  id="logoutBtn"
                  style={{ color: '#dc3545', border: 'none', background: 'none', textAlign: 'left', cursor: 'pointer' }}
                  onClick={handleLogout}
                >
                  <i className="fas fa-sign-out-alt" /> Đăng xuất
                </button>
              </div>
            ) : null}
          </div>
          <div className="notification-menu-wrap">
            <button type="button" className="icon-btn notification-btn" aria-label="Thông báo" onClick={() => setNotificationOpen((value) => !value)}>
              <i className="fas fa-bell" />
              {notificationCount > 0 ? <span className="notification-badge">{notificationCount > 99 ? '99+' : notificationCount}</span> : null}
            </button>
            {notificationOpen ? (
              <div className="notification-dropdown">
                <div className="notification-dropdown-title"><i className="fas fa-bell" /> Thông báo</div>
                {visibleNotifications.length ? visibleNotifications.slice(0, 8).map((item, index) => (
                  <Link key={`${item.type}-${item.borrowId}-${index}`} to="/borrow" className={`user-notification-item ${item.type}`} onClick={() => { markNotificationSeen(item); setNotificationOpen(false); }}>
                    <i className={`fas ${item.type === 'overdue' ? 'fa-triangle-exclamation' : item.type === 'rejected' ? 'fa-xmark' : 'fa-bell'}`} />
                    <span>{item.message}</span>
                  </Link>
                )) : <div className="notification-empty">Chưa có thông báo mới.</div>}
              </div>
            ) : null}
          </div>
          </>
        )}
      </div>
    </header>
  );
}
