import { useState } from 'react';
import { api } from '../services/api';

export default function BugReportButton() {
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [details, setDetails] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setMessage('');
    const result = await api.reportBug({ title, details, url: window.location.href });
    setBusy(false);
    if (!result.success) { setMessage(result.error); return; }
    setMessage('Đã gửi báo lỗi. Cảm ơn bạn!'); setTitle(''); setDetails();
    window.setTimeout(() => setOpen(false), 1200);
  };

  return <>
    <button type="button" className="bug-report-fab" aria-label="Báo lỗi" onClick={() => setOpen(true)}><i className="fas fa-bug" /><span>Báo lỗi</span></button>
    {open ? <div className="bug-report-overlay" role="dialog" aria-modal="true">
      <form className="bug-report-dialog" onSubmit={submit}>
        <button type="button" className="bug-report-close" onClick={() => setOpen(false)}>&times;</button>
        <h2><i className="fas fa-bug" /> Báo lỗi / góp ý</h2>
        <p>Thông tin này sẽ được gửi cho quản trị viên để xử lý.</p>
        <label>Tiêu đề<input value={title} onChange={(event) => setTitle(event.target.value)} required maxLength="200" /></label>
        <label>Mô tả<textarea value={details} onChange={(event) => setDetails(event.target.value)} required rows="5" /></label>
        {message ? <div className="bug-report-message">{message}</div> : null}
        <button className="page-action" type="submit" disabled={busy}>{busy ? 'Đang gửi...' : 'Gửi báo lỗi'}</button>
      </form>
    </div> : null}
  </>;
}
