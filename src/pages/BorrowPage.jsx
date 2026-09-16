import { useEffect, useState } from 'react';
import LegacyPageShell from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';

const fallback = [
  { user: 'Nguyễn Văn A', item: 'Laptop Dell XPS', status: 'Đã duyệt' },
  { user: 'Trần Thị B', item: 'Máy chiếu', status: 'Chờ duyệt' },
  { user: 'Lê Văn C', item: 'Webcam', status: 'Đang mượn' },
];

export default function BorrowPage() {
  const [requests, setRequests] = useState(fallback);
  useEffect(() => {
    let active = true;
    api.getBorrowList().then((result) => {
      if (active && result?.success && result.data?.length) {
        setRequests(result.data.map((item) => ({
          user: item.username || item.userNameLabel || item.user || 'Người dùng',
          item: item.name || item.item || item.tenThietBi || 'Thiết bị',
          status: item.borrowStatus || item.status || 'Chưa cập nhật',
        })));
      }
    });
    return () => { active = false; };
  }, []);
  return (
    <LegacyPageShell title="Lịch sử mượn / trả" icon="fas fa-clock-rotate-left">
      <p className="page-intro">Theo dõi các yêu cầu mượn thiết bị và trạng thái xử lý.</p>
      <div className="page-card page-list">
        {requests.map((request, index) => (
          <div className="page-list-row" key={`${request.user}-${request.item}-${index}`}>
            <div><strong>{request.item}</strong><p>{request.user}</p></div>
            <span className="status-pill">{request.status}</span>
          </div>
        ))}
      </div>
    </LegacyPageShell>
  );
}
