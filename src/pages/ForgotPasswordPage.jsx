import { useState } from 'react';
import LegacyPageShell, { PageNotice } from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';

export default function ForgotPasswordPage() {
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    setMessage('');
    setError('');
    setLoading(true);
    try {
      const result = await api.requestPasswordReset(phone.trim(), email.trim());
      if (!result.success) {
        setError(result.error);
        return;
      }
      setMessage(result.message);
      setPhone('');
      setEmail('');
    } finally {
      setLoading(false);
    }
  };

  return (
    <LegacyPageShell title="Quên mật khẩu" icon="fas fa-key">
      <div className="page-card" style={{ maxWidth: 560, margin: '0 auto' }}>
        <p className="page-intro">Nhập số điện thoại và email đã đăng ký. Admin sẽ xác minh trước khi hệ thống gửi mật khẩu tạm thời.</p>
        <form className="page-form" onSubmit={submit}>
          {message ? <PageNotice tone="success">{message}</PageNotice> : null}
          {error ? <PageNotice tone="error">{error}</PageNotice> : null}
          <label htmlFor="resetPhone">Số điện thoại / tài khoản</label>
          <input id="resetPhone" type="tel" inputMode="numeric" pattern="0[0-9]{9,10}" value={phone} onChange={(event) => setPhone(event.target.value)} required />
          <label htmlFor="resetEmail">Email đã đăng ký</label>
          <input id="resetEmail" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
          <button className="page-action" type="submit" disabled={loading}>{loading ? 'Đang gửi...' : 'Gửi yêu cầu xác minh'}</button>
        </form>
      </div>
    </LegacyPageShell>
  );
}
