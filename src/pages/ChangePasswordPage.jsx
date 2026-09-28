import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import LegacyPageShell, { PageNotice } from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';
import { setUser } from '../store/slices/appSlice';

export default function ChangePasswordPage() {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const user = useSelector((state) => state.app.user);
  const mustChange = Boolean(user?.must_change_password);
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setLoading(true);
    try {
      const result = await api.changePassword(password, confirm);
      if (!result.success) {
        setError(result.error);
        return;
      }
      dispatch(setUser({ ...user, must_change_password: false }));
      navigate('/dashboard', { replace: true });
    } finally {
      setLoading(false);
    }
  };

  return (
    <LegacyPageShell title="Đổi mật khẩu" icon="fas fa-lock">
      <div className="page-card" style={{ maxWidth: 560, margin: '0 auto' }}>
        <PageNotice tone="info">{mustChange ? 'Đây là lần đăng nhập đầu tiên sau khi cấp mật khẩu tạm. Bạn phải đổi mật khẩu trước khi tiếp tục sử dụng hệ thống.' : 'Bạn có thể đổi mật khẩu tài khoản tại đây.'}</PageNotice>
        <form className="page-form" onSubmit={submit}>
          {error ? <PageNotice tone="error">{error}</PageNotice> : null}
          <label htmlFor="newPassword">Mật khẩu mới</label>
          <input id="newPassword" type="password" minLength="8" pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}" value={password} onChange={(event) => setPassword(event.target.value)} required />
          <small>Ít nhất 8 ký tự, gồm chữ viết hoa, số và ký tự đặc biệt.</small>
          <label htmlFor="confirmPassword">Nhập lại mật khẩu mới</label>
          <input id="confirmPassword" type="password" minLength="8" value={confirm} onChange={(event) => setConfirm(event.target.value)} required />
          <button className="page-action" type="submit" disabled={loading}>{loading ? 'Đang cập nhật...' : 'Đổi mật khẩu và tiếp tục'}</button>
        </form>
      </div>
    </LegacyPageShell>
  );
}
