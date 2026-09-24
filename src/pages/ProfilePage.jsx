import { useSelector } from 'react-redux';
import LegacyPageShell from '../components/legacy/LegacyPageShell';

export default function ProfilePage() {
  const user = useSelector((state) => state.app.user);
  return (
    <LegacyPageShell title="Thông tin cá nhân" icon="fas fa-id-card">
      <p className="page-intro">Thông tin tài khoản đang đăng nhập vào hệ thống SEB.</p>
      <div className="page-card profile-grid">
        <strong>Họ và tên</strong><span>{user?.name || 'Khách'}</span>
        <strong>Tài khoản</strong><span>{user?.username || 'Chưa có'}</span>
        <strong>Vai trò</strong><span>{user?.role || 'Guest'}</span>
        <strong>Email</strong><span>{user?.email || 'Chưa cập nhật'}</span>
      </div>
    </LegacyPageShell>
  );
}
