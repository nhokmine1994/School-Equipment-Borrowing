import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import LegacyPageShell, { PageNotice } from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';
import { setUser } from '../store/slices/appSlice';

const editableFields = [
  ['name', 'Họ và tên', 'fullName', 'fas fa-user'],
  ['email', 'Email', 'email', 'fas fa-envelope'],
  ['subject', 'Môn học / Bộ môn', 'subject', 'fas fa-book-open'],
  ['cccd', 'Số CCCD', 'cccd', 'fas fa-id-card'],
  ['teacher_code', 'Mã số giáo viên', 'teacherCode', 'fas fa-chalkboard-user'],
  ['education_code', 'Mã số Bộ GDĐT', 'educationCode', 'fas fa-building-columns'],
];

export default function ProfilePage() {
  const dispatch = useDispatch();
  const user = useSelector((state) => state.app.user);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({});
  const [subjects, setSubjects] = useState([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const displayName = user?.name || user?.display_name || user?.username || 'Khách';
  const initials = useMemo(() => {
    const parts = displayName.trim().split(/\s+/).filter(Boolean);
    return (parts.length > 1 ? `${parts[0][0]}${parts[parts.length - 1][0]}` : parts[0]?.slice(0, 2) || '?').toUpperCase();
  }, [displayName]);

  useEffect(() => {
    setForm({
      name: user?.name || user?.display_name || '',
      email: user?.email || '',
      subject: user?.subject || '',
      cccd: user?.cccd || '',
      teacher_code: user?.teacher_code || '',
      education_code: user?.education_code || '',
    });
  }, [user]);

  useEffect(() => {
    api.getSubjects().then((result) => {
      if (result.success) setSubjects(result.data);
    });
  }, []);

  const update = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const save = async () => {
    setMessage(''); setError('');
    const result = await api.updateProfile({
      fullName: form.name,
      email: form.email,
      subject: form.subject,
      cccd: form.cccd,
      teacherCode: form.teacher_code,
      educationCode: form.education_code,
    });
    if (!result.success) {
      setError(result.error);
      return;
    }
    if (result.data) dispatch(setUser(result.data));
    setEditing(false);
    setMessage(result.message || 'Đã cập nhật hồ sơ.');
  };

  const renderValue = (key) => form[key] || 'Chưa cập nhật';

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
        {message ? <PageNotice tone="success">{message}</PageNotice> : null}
        {error ? <PageNotice tone="error">{error}</PageNotice> : null}
        <div className="profile-details-grid">
          <div><span>Tài khoản / Số điện thoại <i className="fas fa-lock profile-locked" /></span><strong>{user?.username || 'Chưa có'}</strong></div>
          <div><span>Vai trò <i className="fas fa-lock profile-locked" /></span><strong>{user?.role || 'Guest'}</strong></div>
          {editableFields.map(([key, label, inputName, icon]) => (
            <div key={key}>
              <span>{label} {editing ? <i className={icon} /> : <button type="button" className="profile-edit-button" onClick={() => setEditing(true)} aria-label={`Sửa ${label}`}><i className="fas fa-pen" /></button>}</span>
              {editing ? (
                key === 'subject' ? (
                  <select value={form[key] || ''} onChange={(event) => update(key, event.target.value)}>
                    <option value="">-- Chọn môn học --</option>
                    {subjects.map((subject) => <option key={subject} value={subject}>{subject}</option>)}
                  </select>
                ) : <input name={inputName} value={form[key] || ''} type={key === 'email' ? 'email' : 'text'} onChange={(event) => update(key, event.target.value)} />
              ) : <strong>{renderValue(key)}</strong>}
            </div>
          ))}
        </div>
        {editing ? (
          <div className="profile-edit-actions">
            <button type="button" className="admin-btn-soft" onClick={() => { setEditing(false); setError(''); }}>Hủy</button>
            <button type="button" className="page-action" onClick={save}><i className="fas fa-save" /> Lưu thay đổi</button>
          </div>
        ) : null}
      </div>

      <div className="profile-note">
        <i className="fas fa-circle-info" />
        <div><strong>Thông tin phân quyền</strong><p>Tài khoản, số điện thoại đăng nhập, vai trò và quyền truy cập chỉ được quản trị viên nhà trường thay đổi.</p></div>
      </div>
    </LegacyPageShell>
  );
}
