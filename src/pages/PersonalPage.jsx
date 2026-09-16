import { useEffect, useMemo, useState } from 'react';
import LegacyPageShell, { PageNotice } from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';
import { assetPath } from '../utils/assetPath';

const placeholder = (label) => `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="320" height="190"><rect width="320" height="190" fill="#f1f5f9"/><text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" font-family="Arial" fill="#789">${label}</text></svg>`)}`;

function normalizeDevice(item) {
  return {
    ...item,
    id: item.id ?? item.code ?? 'TB',
    name: item.name || 'Thiết bị',
    category: item.category || 'Khác',
    subject: item.subject || 'Chung',
    image: item.image,
    status: item.statusLabel || item.status || 'Sẵn sàng',
  };
}

export default function PersonalPage() {
  const [personal, setPersonal] = useState([]);
  const [devices, setDevices] = useState([]);
  const [rooms, setRooms] = useState([]);
  const [history, setHistory] = useState([]);
  const [selectedId, setSelectedId] = useState('');
  const [category, setCategory] = useState('');
  const [subject, setSubject] = useState('');
  const [roomFilter, setRoomFilter] = useState('');
  const [dayFilter, setDayFilter] = useState('');
  const [roomQuery, setRoomQuery] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const loadData = async () => {
    const [mine, all, roomList, borrowList] = await Promise.all([
      api.getPersonalList(), api.getDevices(), api.getRoomList(), api.getBorrowList(),
    ]);
    if (mine?.success) setPersonal((mine.data || []).map(normalizeDevice));
    if (all?.success) setDevices((all.data || []).map(normalizeDevice));
    if (roomList?.success) setRooms(roomList.data || []);
    if (borrowList?.success) setHistory(borrowList.data || []);
  };

  useEffect(() => { loadData(); }, []);

  const categories = useMemo(() => [...new Set(personal.map((item) => item.category).filter(Boolean))], [personal]);
  const subjects = useMemo(() => [...new Set(personal.map((item) => item.subject).filter(Boolean))], [personal]);
  const available = devices.filter((device) => !personal.some((item) => String(item.id) === String(device.id)));
  const visiblePersonal = personal.filter((device) => (!category || device.category === category) && (!subject || device.subject === subject));
  const roomOptions = [...new Set(rooms.map((room) => String(room.roomNumberLabel || room.roomNumber || '').trim()).filter(Boolean))];
  const dayOptions = [...new Set(rooms.flatMap((room) => (room.slots || []).map((slot) => slot.dayLabel).filter(Boolean)))];
  const visibleRooms = rooms.filter((room) => {
    const roomNumber = String(room.roomNumberLabel || room.roomNumber || '');
    const slots = room.slots || [];
    const text = `${room.purpose || ''} ${slots.map((slot) => `${slot.dayLabel} ${slot.timeLabel}`).join(' ')}`.toLowerCase();
    return (!roomFilter || roomNumber === roomFilter) && (!dayFilter || slots.some((slot) => slot.dayLabel === dayFilter)) && (!roomQuery || text.includes(roomQuery.toLowerCase()));
  });

  const runAction = async (action, successText) => {
    setBusy(true); setMessage(''); setError('');
    const result = await action();
    setBusy(false);
    if (!result?.success) { setError(result?.error || 'Thao tác không thành công.'); return false; }
    setMessage(successText(result));
    await loadData();
    return true;
  };

  const addDevice = () => {
    if (!selectedId) { setError('Vui lòng chọn thiết bị cần thêm.'); return; }
    runAction(() => api.addPersonalDevice(selectedId), (result) => result.data?.message || 'Đã thêm thiết bị vào kho cá nhân.').then(() => setSelectedId(''));
  };
  const removeDevice = (device) => runAction(() => api.removePersonalDevice(device.id), () => 'Đã xóa thiết bị khỏi kho cá nhân.');
  const borrowDevice = (device) => runAction(() => api.createBorrowRequest({ maThietBi: String(device.dbId || device.id), soLuong: 1 }), (result) => result.data?.message || `Đã gửi yêu cầu mượn ${device.name}.`);
  const cancelRoom = (bookingId) => runAction(() => api.cancelRoomBooking(bookingId), () => 'Đã hủy lịch phòng.');

  return (
    <LegacyPageShell title="Kho cá nhân" icon="fas fa-user-check" extraCss={['/CSS/kho.css', '/CSS/kho-pages.css']}>
      {message ? <PageNotice tone="success">{message}</PageNotice> : null}{error ? <PageNotice tone="error">{error}</PageNotice> : null}
      <div className="personal-kho-layout">
        <aside className="sidebar personal-history"><h3>Lịch sử mượn gần đây</h3>{history.length ? history.slice(0, 5).map((item, index) => <div className="history-item" key={`${item.id || item.borrowId || index}-${item.name}`}><img src={assetPath(item.image) || placeholder('TB')} alt="" /><div><strong>{item.name || item.item || 'Thiết bị'}</strong><small>{item.borrowDate ? new Date(item.borrowDate).toLocaleString('vi-VN') : 'Chưa có thời gian'}</small></div></div>) : <p className="borrow-empty">Chưa có lịch sử mượn.</p>}</aside>
        <main className="content">
          <div className="personal-filter-row"><select value={category} onChange={(event) => setCategory(event.target.value)}><option value="">Tất cả danh mục</option>{categories.map((item) => <option key={item} value={item}>{item}</option>)}</select><select value={subject} onChange={(event) => setSubject(event.target.value)}><option value="">Tất cả môn học</option>{subjects.map((item) => <option key={item} value={item}>{item}</option>)}</select><select value={selectedId} onChange={(event) => setSelectedId(event.target.value)}><option value="">Thêm thiết bị từ kho</option>{available.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}</select><button type="button" className="page-action" onClick={addDevice} disabled={busy}>+ Thêm</button></div>
          <div className="equipment-grid">{visiblePersonal.length ? visiblePersonal.map((device) => <article className="device-card" key={String(device.id)}><div className="device-image"><img src={assetPath(device.image) || placeholder(device.id)} alt={device.name} onError={(event) => { event.currentTarget.src = placeholder('No Image'); }} /></div><div className="device-info"><h3>{device.name}</h3><p><strong>ID:</strong> {device.code || device.id}</p><p><strong>Danh mục:</strong> {device.category}</p><p><strong>Môn học:</strong> {device.subject}</p><p><strong>Trạng thái:</strong> <span className="status available">{device.status}</span></p></div><div className="action-controls"><button type="button" className="btn-borrow" onClick={() => borrowDevice(device)} disabled={busy}>Mượn</button><button type="button" className="btn-add" onClick={() => removeDevice(device)} disabled={busy}>Xóa</button></div></article>) : <div className="page-card"><p>Không tìm thấy thiết bị nào khớp với bộ lọc.</p></div>}</div>
        </main>
      </div>
      <section className="my-room-bookings-section"><h2 className="my-room-bookings-title">Phòng học của tôi</h2><p className="my-room-bookings-desc">Danh sách các ca phòng học bạn đã đăng ký gần đây.</p><div className="my-room-filter-row"><select value={roomFilter} onChange={(event) => setRoomFilter(event.target.value)}><option value="">Tất cả phòng</option>{roomOptions.map((item) => <option key={item} value={item}>Phòng {item}</option>)}</select><select value={dayFilter} onChange={(event) => setDayFilter(event.target.value)}><option value="">Tất cả ngày</option>{dayOptions.map((item) => <option key={item} value={item}>{item}</option>)}</select><input value={roomQuery} onChange={(event) => setRoomQuery(event.target.value)} placeholder="Tìm theo mục đích / ca học..." /></div><div className="my-room-bookings-list">{visibleRooms.length ? visibleRooms.map((room, index) => <article className="my-room-card" key={String(room.bookingId || index)}><h3 className="my-room-card-title">{room.roomTypeLabel || room.roomType || 'Phòng học'} - Phòng {room.roomNumberLabel || room.roomNumber || room.name}</h3><p className="my-room-card-meta"><strong>Thời điểm đăng ký:</strong> {room.createdAt ? new Date(room.createdAt).toLocaleString('vi-VN') : 'Không rõ thời gian'}</p><p className="my-room-card-purpose"><strong>Mục đích:</strong> {room.purpose || 'Không có ghi chú'}</p><ul className="my-room-slot-list">{(room.slots || []).map((slot, slotIndex) => <li className="my-room-slot-item" key={`${slot.dayLabel}-${slot.timeLabel}-${slotIndex}`}>{slot.dayLabel} - {slot.timeLabel}</li>)}</ul>{room.bookingId ? <div className="my-room-card-actions"><button type="button" className="my-room-cancel-btn" onClick={() => cancelRoom(room.bookingId)} disabled={busy}>Hủy lịch</button></div> : null}</article>) : <div className="my-room-empty">Bạn chưa có ca đăng ký phòng học nào.</div>}</div></section>
    </LegacyPageShell>
  );
}
