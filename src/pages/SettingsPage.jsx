import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useSelector } from 'react-redux';
import LegacyPageShell, { PageNotice } from '../components/legacy/LegacyPageShell';

const defaultPreferences = {
  inAppNotifications: true,
  returnReminders: true,
  compactView: false,
};

export default function SettingsPage() {
  const user = useSelector((state) => state.app.user);
  const [preferences, setPreferences] = useState(defaultPreferences);
  const [saved, setSaved] = useState(false);
  const appBase = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';

  useEffect(() => {
    try {
      const stored = JSON.parse(localStorage.getItem(`seb_preferences_${user?.username}`) || '{}');
      setPreferences({ ...defaultPreferences, ...stored });
    } catch {
      setPreferences(defaultPreferences);
    }
  }, [user?.username]);

  useEffect(() => {
    document.body.classList.toggle('seb-compact-view', Boolean(preferences.compactView));
    return () => document.body.classList.remove('seb-compact-view');
  }, [preferences.compactView]);

  const updatePreference = (key) => {
    setPreferences((current) => ({ ...current, [key]: !current[key] }));
    setSaved(false);
  };

  const savePreferences = () => {
    localStorage.setItem(`seb_preferences_${user?.username}`, JSON.stringify(preferences));
    setSaved(true);
  };

  return (
    <LegacyPageShell title="Cài đặt" icon="fas fa-cog">
      <p className="page-intro">Tùy chỉnh trải nghiệm cá nhân. Vai trò và quyền truy cập chỉ được quản trị viên thay đổi.</p>
      {saved ? <PageNotice tone="success">Đã lưu cài đặt trên thiết bị này.</PageNotice> : null}

      <div className="settings-grid">
        <section className="page-card settings-card">
          <div className="settings-card-heading"><i className="fas fa-user-lock" /><div><h2>Tài khoản &amp; phân quyền</h2><p>Thông tin do quản trị viên nhà trường quản lý.</p></div></div>
          <div className="settings-readonly-list">
            <div><span>Tài khoản</span><strong>{user?.username || 'Chưa có'}</strong></div>
            <div><span>Vai trò</span><strong>{user?.role || 'user'}</strong></div>
            <div><span>Bộ môn</span><strong>{user?.subject || user?.boMon || 'Theo hồ sơ nhà trường'}</strong></div>
          </div>
          <div className="settings-security-note"><i className="fas fa-shield-halved" /> Không thể tự đổi vai trò, bộ môn hoặc quyền truy cập. Vui lòng liên hệ admin khi cần điều chỉnh.</div>
          {String(user?.role || '').toLowerCase() === 'admin' ? (
            <a className="page-action settings-admin-link" href={`${appBase}/Page/admin_users.php`}><i className="fas fa-users-gear" /> Quản lý tài khoản và quyền</a>
          ) : null}
        </section>

        <section className="page-card settings-card">
          <div className="settings-card-heading"><i className="fas fa-bell" /><div><h2>Thông báo</h2><p>Chỉ áp dụng cho giao diện hiện tại.</p></div></div>
          <label className="settings-toggle"><span><strong>Thông báo trong website</strong><small>Hiển thị kết quả mượn, trả và đăng ký phòng.</small></span><input type="checkbox" checked={preferences.inAppNotifications} onChange={() => updatePreference('inAppNotifications')} /></label>
          <label className="settings-toggle"><span><strong>Nhắc hạn trả thiết bị</strong><small>Hiển thị nhắc nhở khi phiếu sắp đến hạn trả.</small></span><input type="checkbox" checked={preferences.returnReminders} onChange={() => updatePreference('returnReminders')} /></label>
        </section>

        <section className="page-card settings-card">
          <div className="settings-card-heading"><i className="fas fa-display" /><div><h2>Hiển thị</h2><p>Lưu theo từng tài khoản trên trình duyệt này.</p></div></div>
          <label className="settings-toggle"><span><strong>Chế độ gọn</strong><small>Thu gọn khoảng cách giữa các nội dung.</small></span><input type="checkbox" checked={preferences.compactView} onChange={() => updatePreference('compactView')} /></label>
        </section>

        <section className="page-card settings-card settings-help-card">
          <div className="settings-card-heading"><i className="fas fa-circle-question" /><div><h2>Hỗ trợ tài khoản</h2><p>Liên hệ admin nếu thông tin phân quyền chưa đúng.</p></div></div>
          <p>Admin có thể cập nhật vai trò, bộ môn, trạng thái tài khoản và thông tin hồ sơ từ khu vực quản trị.</p>
          <Link className="settings-inline-link" to="/profile">Xem thông tin cá nhân <i className="fas fa-arrow-right" /></Link>
        </section>
      </div>

      <div className="settings-actions">
        <button type="button" className="page-action" onClick={savePreferences}><i className="fas fa-floppy-disk" /> Lưu cài đặt</button>
      </div>
    </LegacyPageShell>
  );
}
