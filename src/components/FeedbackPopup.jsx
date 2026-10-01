import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

export default function FeedbackPopup({ message, error, onClose }) {
  const [visible, setVisible] = useState(false);
  const close = () => {
    setVisible(false);
    onClose?.();
  };

  useEffect(() => {
    if (!message && !error) return undefined;
    setVisible(true);
    const timer = window.setTimeout(close, 6000);
    return () => window.clearTimeout(timer);
  }, [message, error]);

  if (!visible || (!message && !error)) return null;
  return createPortal(
    <div className={`borrow-feedback-overlay ${error ? 'error' : 'success'}`} role="alertdialog" aria-live="assertive">
      <div className="borrow-feedback-dialog">
        <button type="button" className="borrow-feedback-close" aria-label="Đóng" onClick={close}>&times;</button>
        <div className="borrow-feedback-icon"><i className={`fas ${error ? 'fa-circle-exclamation' : 'fa-circle-check'}`} /></div>
        <h2>{error ? 'Không thể thực hiện' : 'Đã cập nhật'}</h2>
        <p>{error || message}</p>
        <div className="borrow-feedback-progress" />
      </div>
    </div>,
    document.body,
  );
}
