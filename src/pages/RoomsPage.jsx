import { useEffect, useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import { Link } from 'react-router-dom';
import LegacyPageShell, { PageNotice } from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';

const periods = [
  ...['Sáng T1', 'Sáng T2', 'Sáng T3', 'Sáng T4', 'Sáng T5'],
  ...['Chiều T1', 'Chiều T2', 'Chiều T3', 'Chiều T4', 'Chiều T5'],
];

function getMonday() {
  const date = new Date();
  date.setHours(0, 0, 0, 0);
  const day = date.getDay();
  date.setDate(date.getDate() + (day === 0 ? 1 : day === 6 ? 2 : 1 - day));
  return date;
}

function formatDate(date) {
  return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export default function RoomsPage() {
  const user = useSelector((state) => state.app.user);
  const [roomType, setRoomType] = useState('');
  const [roomNumber, setRoomNumber] = useState('');
  const [purpose, setPurpose] = useState('');
  const [schedule, setSchedule] = useState([]);
  const [selectedSlots, setSelectedSlots] = useState([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const weekDays = useMemo(() => {
    const monday = getMonday();
    return Array.from({ length: 5 }, (_, index) => {
      const date = new Date(monday);
      date.setDate(monday.getDate() + index);
      return { label: `T${index + 2} ${formatDate(date)}`, index };
    });
  }, []);

  const loadSchedule = async () => {
    if (!roomType || !roomNumber) {
      setSchedule([]);
      return;
    }
    const result = await api.getRoomSchedule(roomType, roomNumber);
    if (result?.success) setSchedule(result.data || []);
  };

  useEffect(() => {
    setSelectedSlots([]);
    loadSchedule();
  }, [roomType, roomNumber]);

  const occupied = useMemo(() => {
    const result = new Map();
    schedule.forEach((booking) => {
      const mine = String(booking.createdBy || '').toLowerCase() === String(user?.username || '').toLowerCase();
      (booking.slots || []).forEach((slot) => {
        const key = slot.slotKey || `${slot.dayLabel}__${slot.timeLabel}`;
        result.set(key, mine ? 'mine' : 'busy');
      });
    });
    return result;
  }, [schedule, user]);

  const toggleSlot = (dayLabel, timeLabel) => {
    const slotKey = `${dayLabel}__${timeLabel}`;
    if (occupied.has(slotKey)) return;
    setSelectedSlots((current) => current.some((slot) => slot.slotKey === slotKey)
      ? current.filter((slot) => slot.slotKey !== slotKey)
      : [...current, { dayLabel, timeLabel, slotKey }]);
  };

  const submit = async (event) => {
    event.preventDefault(); setMessage(''); setError('');
    if (!selectedSlots.length) return setError('Vui lòng chọn ít nhất một tiết học trống.');
    const result = await api.createRoomBooking({
      bookingId: `RB-${Date.now()}`, roomType, roomTypeLabel: roomType === 'cntt' ? 'Phòng CNTT' : 'Phòng Vật lý',
      roomNumber, roomNumberLabel: roomNumber, userNameLabel: user?.name || user?.username || 'Người dùng',
      purpose: purpose.trim() || 'Không có ghi chú', slots: selectedSlots,
    });
    if (!result.success) return setError(result.error);
    setMessage('Đã gửi yêu cầu đăng ký phòng học.'); setSelectedSlots([]); setPurpose(''); loadSchedule();
  };

  return (
    <LegacyPageShell title="Đăng ký phòng học" icon="fas fa-calendar-check" extraCss={['/CSS/dang-ky-phong.css']}>
      {message ? <PageNotice tone="success">{message}</PageNotice> : null}
      {error ? <PageNotice tone="error">{error}</PageNotice> : null}
      <div className="booking-container">
        <div className="booking-header">
          <div className="booking-title">CHỌN PHÒNG HỌC</div>
          <div className="status-legend header-legend">
            <div className="legend-item">
              <span className="status-dot status-empty" />
              Trống
            </div>
            <div className="legend-item">
              <span className="status-dot status-busy" />
              Có lịch
            </div>
            <div className="legend-item">
              <span className="status-dot status-my-booked" />
              Lịch của tôi
            </div>
          </div>
        </div>

        <div className="booking-main-layout">
          <form className="booking-sidebar" onSubmit={submit}>
            <div className="sidebar-block">
              <h3 className="block-title">Thông tin phòng học</h3>
              <div className="input-field">
                <select name="room-type" value={roomType} onChange={(event) => setRoomType(event.target.value)}>
                  <option value="" disabled>Loại phòng</option>
                  <option value="cntt">Phòng CNTT</option>
                  <option value="ly">Phòng Vật lý</option>
                </select>
                <i className="fas fa-chevron-down field-arrow" />
              </div>
              <div className="input-field">
                <select name="room-number" value={roomNumber} onChange={(event) => setRoomNumber(event.target.value)}>
                  <option value="" disabled>Số phòng</option>
                  <option value="101">101</option>
                  <option value="102">102</option>
                  <option value="205">205</option>
                </select>
                <i className="fas fa-chevron-down field-arrow" />
              </div>
            </div>

            <div className="sidebar-block">
              <h3 className="block-title">Thông tin người dùng</h3>
              <div className="input-field">
                <input
                  type="text"
                  value={user?.name || user?.username || ''}
                  readOnly
                  disabled
                />
              </div>
            </div>

            <div className="sidebar-block">
              <h3 className="block-title">Mục đích sử dụng</h3>
              <div className="input-field textarea-field">
                <textarea
                  value={purpose}
                  onChange={(event) => setPurpose(event.target.value)}
                  placeholder="VD: Dạy Tin học..."
                />
              </div>
            </div>

            <button className="btn-submit-booking" type="submit">
              Gửi Yêu Cầu{selectedSlots.length ? ` (${selectedSlots.length})` : ''}
            </button>
            <Link className="btn-my-room-bookings" to="/personal">
              Xem phòng học của tôi
            </Link>
          </form>

          <main className="booking-content">
            <div className="status-legend mobile-legend">
              <div className="legend-item">
                <span className="status-dot status-empty" />
                Trống
              </div>
              <div className="legend-item">
                <span className="status-dot status-busy" />
                Có lịch
              </div>
              <div className="legend-item">
                <span className="status-dot status-my-booked" />
                Lịch của tôi
              </div>
            </div>
            <div className="timetable-wrapper">
              <table className="timetable">
                <thead>
                  <tr>
                    <th />
                    {weekDays.map((day) => <th key={day.label}>{day.label}</th>)}
                  </tr>
                </thead>
                <tbody>
                  {periods.map((period, periodIndex) => (
                    <tr className={periodIndex === 5 ? 'row-divider' : ''} key={period}>
                      <td className="time-label">{period}</td>
                      {weekDays.map((day) => {
                        const key = `${day.label}__${period}`;
                        const status = occupied.get(key);
                        const selected = selectedSlots.some((slot) => slot.slotKey === key);
                        const btnClass = status === 'busy'
                          ? 'busy'
                          : status === 'mine'
                            ? 'my-booked'
                            : `empty${selected ? ' selected' : ''}`;
                        const btnText = status === 'busy' ? 'Có lịch' : status === 'mine' ? 'Của tôi' : selected ? 'Đã chọn' : 'Trống';
                        return (
                          <td key={key}>
                            <button
                              type="button"
                              className={`slot-btn ${btnClass}`}
                              disabled={Boolean(status)}
                              onClick={() => toggleSlot(day.label, period)}
                            >
                              {btnText}
                            </button>
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </main>
        </div>
      </div>
    </LegacyPageShell>
  );
}
