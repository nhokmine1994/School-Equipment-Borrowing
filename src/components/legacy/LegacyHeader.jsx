import { useMemo, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../../services/api';
import { setAuthStatus, setUser } from '../../store/slices/appSlice';

const avatarStyle = {
  width: '100%',
  height: '100%',
  borderRadius: '50%',
  objectFit: 'cover',
};

const appBase = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';

export default function LegacyHeader() {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const user = useSelector((state) => state.app.user);
  const [menuOpen, setMenuOpen] = useState(false);

  const displayName = useMemo(
    () => user?.name || user?.display_name || user?.username || '',
    [user],
  );
  const isLoggedIn = Boolean(user?.username);
  const userRole = String(user?.role || '').toLowerCase();

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
      <div className="header-right">
        {!isLoggedIn ? (
          <>
            <button type="button" className="icon-btn" aria-label="Đăng nhập" onClick={() => navigate('/login')}>
              <i className="far fa-user" />
            </button>
            <button type="button" className="icon-btn" aria-label="Đăng ký" onClick={() => navigate('/login')}>
              <i className="fas fa-pencil-alt" />
            </button>
          </>
        ) : (
          <div
            className="user-profile-menu"
            id="userProfileMenu"
            style={{ display: 'flex', position: 'relative' }}
          >
            <button
              type="button"
              className="icon-btn avatar-btn"
              id="avatarBtn"
              onClick={() => setMenuOpen((value) => !value)}
            >
              <img src={`${appBase}/Images/avatar-demo.png`} alt="Avatar" style={avatarStyle} />
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
                <a href="#settings" className="dropdown-item">
                  <i className="fas fa-cog" /> Cài đặt
                </a>
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
        )}
      </div>
    </header>
  );
}
