import { useEffect, useMemo, useState } from 'react';
import LegacyPageShell, { PageNotice } from '../components/legacy/LegacyPageShell';
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
  const [query, setQuery] = useState(() => (new URLSearchParams(window.location.search)).get('q') || '');
  const [category, setCategory] = useState('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

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

  const categories = useMemo(() => [...new Set(devices.map((item) => item.category).filter(Boolean))], [devices]);
  const subjects = useMemo(() => [...new Set(devices.map((item) => item.subject).filter(Boolean))], [devices]);
  const visible = devices.filter((item) => (
    item.name.toLowerCase().includes(query.toLowerCase())
    && (!category || item.category === category)
    && (!subject || item.subject === subject)
  ));

  const borrow = async (device) => {
    setMessage(''); setError('');
    const result = await api.createBorrowRequest({ maThietBi: String(device.dbId || device.id), soLuong: 1 });
    if (result.success) setMessage(result.data?.message || `Đã gửi yêu cầu mượn ${device.name}.`);
    else setError(result.error);
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
          {message ? <PageNotice tone="success">{message}</PageNotice> : null}
          {error ? <PageNotice tone="error">{error}</PageNotice> : null}

          <div className="equipment-grid" id="equipment-grid">
            {visible.map((device) => {
              const unavailable = isUnavailable(device);
              return (
                <article
                  className="device-card"
                  key={`${device.id}-${device.name}`}
                  data-name={device.name}
                  data-category={device.category}
                  data-subject={device.subject}
                  data-status={device.status}
                >
                  <div
                    className="device-image"
                    style={{ cursor: 'pointer' }}
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
                    <h3 style={{ cursor: 'pointer', color: '#2563eb', transition: 'color 0.2s' }}>{device.name}</h3>
                    <p><strong>ID thiết bị:</strong> {device.id}</p>
                    <p><strong>Số lượng:</strong> {device.quantity || '---'}</p>
                    <p><strong>Môn học:</strong> {device.subject || 'Chung'}</p>
                    {device.description ? <p><strong>Thông tin:</strong> {device.description}</p> : null}
                    <p>
                      <strong>Trạng thái:</strong>{' '}
                      <span className={`status ${unavailable ? 'unavailable' : 'available'}`}>{device.status}</span>
                    </p>
                  </div>
                  <div className="device-actions">
                    <div className="action-controls">
                      <button type="button" className="btn-borrow" disabled={unavailable} onClick={() => borrow(device)}>
                        {borrowButtonText(device)}
                      </button>
                      <button type="button" className="btn-add" onClick={() => addPersonal(device)}>Thêm</button>
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
    </LegacyPageShell>
  );
}
