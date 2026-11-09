import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import { api } from '../services/api';
import { setAuthStatus, setUser } from '../store/slices/appSlice';
import LegacyPageShell, { PageNotice } from '../components/legacy/LegacyPageShell';

export default function LoginPage() {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    setLoading(true);

    try {
      const result = await api.loginUser(username, password);
      if (!result.success) {
        setError(result.error || 'Đăng nhập thất bại.');
        return;
      }

      dispatch(
        setUser(
          result.data ?? {
            name: username,
            role: 'user',
            username,
          },
        ),
      );
      dispatch(setAuthStatus('authenticated'));
      navigate('/dashboard', { replace: true });
    } finally {
      setLoading(false);
    }
  };

  return (
    <LegacyPageShell title="Đăng nhập hệ thống" icon="fas fa-right-to-bracket">
      <div style={{ padding: '28px 20px' }}>
        <div className="page-card" style={{ maxWidth: 460, margin: '0 auto', padding: '28px 28px 32px' }}>
          <p className="page-intro" style={{ margin: '0 0 20px', textAlign: 'center' }}>
            Đăng nhập để quản lý thiết bị, lịch mượn và phòng học của bạn.
          </p>
          <form className="page-form" onSubmit={handleSubmit}>
            {error ? <PageNotice tone="error">{error}</PageNotice> : null}
            <label htmlFor="username">Tên đăng nhập</label>
            <input id="username" placeholder="Nhập tài khoản của bạn..." value={username} onChange={(event) => setUsername(event.target.value)} required />
            <label htmlFor="password">Mật khẩu</label>
            <input id="password" type="password" placeholder="Nhập mật khẩu..." value={password} onChange={(event) => setPassword(event.target.value)} required />
            <button className="page-action" type="submit" disabled={loading} style={{ marginTop: 6 }}>
              {loading ? 'Đang đăng nhập...' : 'Đăng nhập'}
            </button>
          </form>
        </div>
      </div>
    </LegacyPageShell>
  );
}
