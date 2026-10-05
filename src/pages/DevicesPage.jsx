import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import LegacyPageShell from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';
import { assetPath } from '../utils/assetPath';

const fallback = [
  { id: 'TB-001', name: 'Laptop Dell XPS 13', category: 'CNTT', subject: 'Tin học', quantity: 2, status: 'Sẵn sàng' },
  { id: 'TB-002', name: 'Máy chiếu Epson', category: 'Trình chiếu', subject: 'Chung', quantity: 1, status: 'Đang cho mượn' },
  { id: 'TB-003', name: 'Bảng điện tử', category: 'CNTT', subject: 'Chung', quantity: 0, status: 'Bảo trì' },
  { id: 'TB-004', name: 'Webcam Logitech', category: 'CNTT', subject: 'Tin học', quantity: 4, status: 'Sẵn sàng' },
];

function placeholder(label) {
  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="320" height="190"><rect width="320" height="190" fill="#f1f5f9"/><text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" font-family="Arial" fill="#789">${label}</text></svg>`)}`;
}

export default function DevicesPage() {
  const [devices, setDevices] = useState(fallback);
  const [selectedDevice, setSelectedDevice] = useState(null);
  const [borrowQuantity, setBorrowQuantity] = useState(1);
  const [borrowDate, setBorrowDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [returnDate, setReturnDate] = useState(() => new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10));
  const [query, setQuery] = useState(() => (new URLSearchParams(window.location.search)).get('q') || '');
  const [category, setCategory] = useState('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [feedbackVisible, setFeedbackVisible] = useState(false);

  useEffect(() => {
    let active = true;
    api.getDevices().then((result) => {
      if (active && result?.success && result.data?.length) {
        setDevices(result.data.map((item) => ({
          ...item,
          id: item.code || item.id || 'TB',
          name: item.name || 'Thiết bị',
          category: item.category || 'Khác',
          subject: item.subject || 'Chung',
          quantity: Number(item.quantity ?? 0),
          status: item.statusLabel || item.status || 'Chưa cập nhật',
        })));
      }
    });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (!message && !error) return undefined;
    setFeedbackVisible(true);
    const timer = window.setTimeout(() => setFeedbackVisible(false), 6000);
    return () => window.clearTimeout(timer);
  }, [message, error]);

  const closeFeedback = () => {
    setFeedbackVisible(false);
    setMessage('');
    setError('');
  };

  const categories = useMemo(() => [...new Set(devices.map((item) => item.category).filter(Boolean))], [devices]);
  const subjects = useMemo(() => [...new Set(devices.map((item) => item.subject).filter(Boolean))], [devices]);
  const visible = devices.filter((item) => (
    item.name.toLowerCase().includes(query.toLowerCase())
    && (!category || item.category === category)
    && (!subject || item.subject === subject)
  ));

  const openDevice = (device) => {
    if (isUnavailable(device)) return;
    setSelectedDevice(device);
    setBorrowQuantity(1);
    setBorrowDate(new Date().toISOString().slice(0, 10));
    setReturnDate(new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10));
  };

  const borrow = async (device, quantity, startDate, endDate) => {
    setMessage(''); setError('');
    const result = await api.createBorrowRequest({
      maThietBi: String(device.dbId || device.id),
      soLuong: quantity,
      ngayMuon: startDate,
      hanTra: endDate,
    });
    if (result.success) {
      setMessage(result.data?.message || `Đã gửi yêu cầu mượn ${device.name}.`);
      setSelectedDevice(null);
    } else setError(result.error);
  };

  const addPersonal = async (device) => {
    setMessage(''); setError('');
    const result = await api.addPersonalDevice(String(device.dbId || device.id));
    if (result.success) setMessage(result.data?.message || 'Đã thêm thiết bị vào kho cá nhân.');
    else setError(result.error);
  };

  const isUnavailable = (device) => /hết|het|hết hàng|unavailable|ngưng|ngung|hỏng|hong|bao tri|bảo trì/i.test(device.status) || device.quantity === 0;
  const borrowButtonText = (device) => {
    if (/hết|het|hết hàng|unavailable|ngưng|ngung|hỏng|hong|bao tri|bảo trì/i.test(device.status)) return 'Chờ nhập kho';
    if (device.quantity === 0) return 'HẾT';
    return 'Mượn';
  };

  return (
    <LegacyPageShell title="Kho thiết bị" icon="fas fa-boxes-stacked" extraCss={['/CSS/kho.css', '/CSS/kho-pages.css']}>
      <div className="kho-app device-kho-layout">
        <aside className="sidebar">
          <h3 className="collapsible-title">Danh mục <i className="fas fa-chevron-down toggle-icon" /></h3>
          <div className="collapsible-content" id="category-filters">
            <div className="filter-group kho-filter-group">
              <label className={`kho-filter-label${!category ? ' selected' : ''}`}>
                <input type="checkbox" className="filter-checkbox kho-filter-checkbox" checked={!category} onChange={() => { setCategory(''); setSubject(''); }} />
                Tất cả thiết bị
              </label>
            </div>
            {categories.map((item) => (
              <div className="filter-group kho-filter-group" key={item}>
                <label className="kho-filter-label">
                  <input
                    type="checkbox"
                    className="filter-checkbox kho-filter-checkbox"
                    checked={category === item}
                    onChange={() => setCategory(category === item ? '' : item)}
                  />
                  {item}
                </label>
              </div>
            ))}
          </div>

          <h3 className="kho-subject-title collapsible-title">Môn học <i className="fas fa-chevron-down toggle-icon" /></h3>
          <div className="collapsible-content" id="subject-filters">
            {subjects.map((item) => (
              <div className="filter-group kho-filter-group" key={item}>
                <label className="kho-filter-label">
                  <input
                    type="checkbox"
                    className="filter-checkbox kho-filter-checkbox"
                    checked={subject === item}
                    onChange={() => setSubject(subject === item ? '' : item)}
                  />
                  {item}
                </label>
              </div>
            ))}
          </div>
        </aside>

        <main className="content">
          <h1>Kho Thiết Bị</h1>
          <div className="search-bar kho-search-wrap">
            <input
              type="text"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Nhập tên thiết bị để tìm kiếm..."
              className="kho-search-input"
              aria-label="Tìm kiếm thiết bị"
            />
          </div>
          <div className="equipment-grid" id="equipment-grid">
            {visible.map((device) => {
              const unavailable = isUnavailable(device);
              return (
                <article
                  className="device-card"
                  key={`${device.id}-${device.name}`}
                  onClick={() => openDevice(device)}
                  onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') openDevice(device); }}
                  role="button"
                  tabIndex={unavailable ? -1 : 0}
                  data-name={device.name}
                  data-category={device.category}
                  data-subject={device.subject}
                  data-status={device.status}
                >
                  <div
                    className="device-image"
                    style={{ cursor: unavailable ? 'not-allowed' : 'pointer' }}
                    title="Nhấn để xem cấu hình chi tiết"
                  >
                    <img
                      src={assetPath(device.image) || placeholder(device.id)}
                      alt={device.name}
                      style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }}
                      onError={(event) => { event.currentTarget.src = placeholder('No Image'); }}
                    />
                  </div>
                  <div className="device-info">
                     <h3 style={{ cursor: unavailable ? 'not-allowed' : 'pointer', color: '#2563eb', transition: 'color 0.2s' }}>{device.name}</h3>
                    <p><strong>ID thiết bị:</strong> {device.id}</p>
                    <p><strong>Số lượng:</strong> {device.quantity || '---'}</p>
                    <p><strong>Môn học:</strong> {device.subject || 'Chung'}</p>
                     {device.description ? <p className="device-description"><strong>Thông tin:</strong> {device.description}</p> : null}
                    <p>
                      <strong>Trạng thái:</strong>{' '}
                      <span className={`status ${unavailable ? 'unavailable' : 'available'}`}>{device.status}</span>
                    </p>
                  </div>
                  <div className="device-actions">
                    <div className="action-controls">
                       <button type="button" className="btn-borrow" disabled={unavailable} onClick={(event) => { event.stopPropagation(); openDevice(device); }}>
                        {borrowButtonText(device)}
                      </button>
                       <button type="button" className="btn-add" onClick={(event) => { event.stopPropagation(); addPersonal(device); }}>Thêm</button>
                    </div>
                  </div>
                </article>
              );
            })}
          </div>
          {!visible.length ? (
            <div className="empty-state">
              <h3>Không tìm thấy thiết bị</h3>
              <p>Vui lòng thử từ khóa hoặc bộ lọc khác.</p>
            </div>
          ) : null}
        </main>
      </div>
      {selectedDevice ? (
        <div className="device-modal-overlay active" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelectedDevice(null); }}>
          <div className="device-modal" role="dialog" aria-modal="true" aria-labelledby="device-modal-title">
            <div className="modal-header">
              <h2 id="device-modal-title">{selectedDevice.name}</h2>
              <button type="button" className="close-modal" aria-label="Đóng" onClick={() => setSelectedDevice(null)}>&times;</button>
            </div>
            <div className="modal-body">
              <div>
                <div className="modal-image-container">
                  <img src={assetPath(selectedDevice.image) || placeholder(selectedDevice.id)} alt={selectedDevice.name} onError={(event) => { event.currentTarget.src = placeholder('No Image'); }} />
                </div>
                <div className="modal-info-section" style={{ marginTop: 18 }}>
                  <div className="modal-detail-row"><span className="modal-detail-label">Mã thiết bị</span><span className="modal-detail-value">{selectedDevice.id}</span></div>
                  <div className="modal-detail-row"><span className="modal-detail-label">Số lượng còn</span><span className="modal-detail-value">{selectedDevice.quantity}</span></div>
                  <div className="modal-detail-row"><span className="modal-detail-label">Môn học</span><span className="modal-detail-value">{selectedDevice.subject || 'Chung'}</span></div>
                  <div className="modal-detail-row"><span className="modal-detail-label">Trạng thái</span><span className="modal-detail-value">{selectedDevice.status}</span></div>
                </div>
                {selectedDevice.description ? <p className="modal-desc">{selectedDevice.description}</p> : null}
              </div>
              <div>
                <div className="modal-info-section">
                  <h3>Thông tin mượn</h3>
                  <div className="modal-form-group">
                    <label htmlFor="borrow-quantity">Số lượng</label>
                    <input id="borrow-quantity" type="number" min="1" max={selectedDevice.quantity} value={borrowQuantity} onChange={(event) => setBorrowQuantity(Math.max(1, Math.min(selectedDevice.quantity, Number(event.target.value) || 1)))} />
                  </div>
                  <div className="modal-form-group">
                    <label htmlFor="borrow-date">Ngày mượn</label>
                    <input id="borrow-date" type="date" min={new Date().toISOString().slice(0, 10)} value={borrowDate} onChange={(event) => setBorrowDate(event.target.value)} />
                  </div>
                  <div className="modal-form-group">
                    <label htmlFor="return-date">Ngày trả dự kiến</label>
                    <input id="return-date" type="date" min={borrowDate || new Date().toISOString().slice(0, 10)} value={returnDate} onChange={(event) => setReturnDate(event.target.value)} />
                  </div>
                </div>
                <p className="modal-desc">Yêu cầu sẽ chờ quản trị viên duyệt. Kho chỉ được trừ sau khi yêu cầu được duyệt.</p>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="modal-btn-cancel" onClick={() => setSelectedDevice(null)}>Hủy</button>
              <button type="button" className="modal-btn-confirm" disabled={!borrowDate || !returnDate || returnDate < borrowDate} onClick={() => borrow(selectedDevice, borrowQuantity, borrowDate, returnDate)}>Gửi yêu cầu mượn</button>
            </div>
          </div>
        </div>
      ) : null}
      {feedbackVisible && (message || error) ? createPortal((
        <div className={`borrow-feedback-overlay ${error ? 'error' : 'success'}`} role="alertdialog" aria-live="assertive">
          <div className="borrow-feedback-dialog">
            <button type="button" className="borrow-feedback-close" aria-label="Đóng" onClick={closeFeedback}>&times;</button>
            <div className="borrow-feedback-icon"><i className={`fas ${error ? 'fa-circle-exclamation' : 'fa-circle-check'}`} /></div>
            <h2>{error ? 'Không thể thực hiện' : 'Đã gửi yêu cầu'}</h2>
            <p>{error || message}</p>
            <div className="borrow-feedback-progress" />
          </div>
        </div>
      ), document.body) : null}
    </LegacyPageShell>
  );
}
