import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useSelector } from 'react-redux';
import LegacyPageShell from '../components/legacy/LegacyPageShell';

export default function ProfilePage() {
  const user = useSelector((state) => state.app.user);
  const displayName = user?.name || user?.display_name || user?.username || 'Khách';
  const initials = useMemo(() => {
    const parts = displayName.trim().split(/\s+/).filter(Boolean);
    return (parts.length > 1 ? `${parts[0][0]}${parts[parts.length - 1][0]}` : parts[0]?.slice(0, 2) || '?').toUpperCase();
  }, [displayName]);

  return (
    <LegacyPageShell title="Thông tin cá nhân" icon="fas fa-id-card">
      <div className="profile-hero">
        <div className="profile-hero-avatar">{initials}</div>
        <div className="profile-hero-copy">
          <span className="profile-kicker">HỒ SƠ NGƯỜI DÙNG</span>
          <h2>{displayName}</h2>
          <p>{user?.role || 'Người dùng'} · Tài khoản được quản lý bởi nhà trường</p>
        </div>
        <Link className="profile-hero-action" to="/settings"><i className="fas fa-cog" /> Cài đặt</Link>
      </div>

      <div className="page-card profile-details-card">
        <div className="profile-section-title"><i className="fas fa-address-card" /> Thông tin hồ sơ</div>
        <div className="profile-details-grid">
          <div><span>Họ và tên</span><strong>{displayName}</strong></div>
          <div><span>Tài khoản / Số điện thoại</span><strong>{user?.username || 'Chưa có'}</strong></div>
          <div><span>Email</span><strong>{user?.email || 'Chưa cập nhật'}</strong></div>
          <div><span>Môn học / Bộ môn</span><strong>{user?.subject || user?.boMon || 'Chưa cập nhật'}</strong></div>
          <div><span>Vai trò</span><strong>{user?.role || 'Guest'}</strong></div>
          <div><span>Trạng thái bảo mật</span><strong className="profile-status"><i className="fas fa-shield-halved" /> Đang hoạt động</strong></div>
        </div>
      </div>

      <div className="profile-note">
        <i className="fas fa-circle-info" />
        <div><strong>Cần cập nhật thông tin?</strong><p>Vai trò, bộ môn và quyền truy cập được admin nhà trường xác minh. Bạn có thể đổi mật khẩu trong phần Cài đặt.</p></div>
      </div>
    </LegacyPageShell>
  );
}
