import { useEffect, useState, useRef } from 'react';
import { useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import LegacyPageShell from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';

const appBase = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';

const fallbackSummary = [
  { label: 'THIẾT BỊ HIỆN CÓ', value: 631 },
  { label: 'BẢO TRÌ', value: 0 },
  { label: 'ĐANG MƯỢN', value: 65 },
];

const fallbackRecentBorrows = [
  { id: 'AT-003', name: 'Micro không dây Shure', status: 'Đã duyệt', timeAgo: '12 tuần trước' },
  { id: 'NW-001', name: 'Router TP-Link Archer C6', status: 'Chờ duyệt', timeAgo: '12 tuần trước' },
  { id: 'AT-002', name: 'Loa Bluetooth JBL PartyBox', status: 'Đã duyệt', timeAgo: '14 tuần trước' },
  { id: 'TB999', name: 'Laptop Asus', status: 'Đã duyệt', timeAgo: '14 tuần trước' },
  { id: 'TN-001', name: 'Bộ thực hành Arduino Uno', status: 'Chờ duyệt', timeAgo: '13 tuần trước' },
];

const partnerLogos = [
  'nhataitro-1.png',
  'nhataitro-2.jpg',
  'nhataitro-3.png',
  'nhataitro-4.png',
  'nhataitro-5.png',
  'nhataitro-6.png',
  'logo.png',
];

const highlightBlocks = [
  {
    title: 'Tin văn',
    icon: 'fas fa-newspaper',
    items: [
      'SEB hoàn tất tới ưu giao diện mobile, cải thiện trải nghiệm mượn/trả trên điện thoại.',
      'Thêm bộ lọc theo trằng thái thiết bị để tra cứu nhanh trong giờ lên lớp.',
      'Hoàn thiện trang Về dự án và Về chúng tôi với lượng điều hướng trực tiếp.',
    ],
  },
  {
    title: 'Chứng nhận nổi bật',
    icon: 'fas fa-award',
    items: [
      'Top giải pháp ứng dụng CNTT trong quản lý thiết bị nội bộ năm 2026.',
      'Được đánh giá cao về tính thực tiễn và chi phí triển khai thấp.',
      'Đề xuất nhân rộng mô hình sang các phòng bộ môn và các trường lân cận.',
    ],
  },
];

function createPlaceholderImage(text) {
  const safeText = String(text ?? 'No Image').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[char]));

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><rect width="80" height="80" rx="12" fill="#f1f5f9"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#64748b">${safeText}</text></svg>`;
  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

export default function HomePage() {
  const user = useSelector((state) => state.app.user);
  const [summary, setSummary] = useState(fallbackSummary);
  const [recentBorrows, setRecentBorrows] = useState([]);
  const [recentVisibleCount, setRecentVisibleCount] = useState(5);
  const [activeRecent, setActiveRecent] = useState(null);
  const [maintenance, setMaintenance] = useState([]);
  const [activeNotice, setActiveNotice] = useState(null);
  const [visibleCount, setVisibleCount] = useState(5);
  const [searchText, setSearchText] = useState('');
  const [camOpen, setCamOpen] = useState(false);
  const [camPhoto, setCamPhoto] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    let active = true;

    api.getDashboardSummary().then((result) => {
      if (!active || !result?.success) return;
      const data = result.data ?? {};
      setSummary([
        { label: 'THIẾT BỊ HIỆN CÓ', value: data.total_devices ?? 0 },
        { label: 'BẢO TRÌ', value: data.maintenance_devices ?? 0 },
        { label: 'ĐANG MƯỢN', value: data.borrowed_devices ?? 0 },
      ]);
    });

    api.getRecentBorrows().then((result) => {
      if (!active || !result?.success) return;
      setRecentBorrows(result.data ?? []);
    });

    api.getMaintenanceList().then((result) => {
      if (!active || !result?.success) return;
      setMaintenance(result.data ?? []);
    });

    return () => { active = false; };
  }, []);

  return (
    <LegacyPageShell noHeading>
      <div className="search-container">
        <div className="search-pill" role="search" onSubmit={(e) => e.preventDefault()}>
          <i className="fas fa-search search-icon-left" />
          <input
            type="text"
            placeholder="Tìm kiếm thiết bị"
            value={searchText}
            onChange={(e) => setSearchText(e.target.value)}
            onKeyDown={(e) => { if (e.key === "Enter") { navigate(`/devices?q=${encodeURIComponent(searchText)}`); } }}
          />
          <button type="button" className="search-icon-right" style={{ background: "none", border: 0, cursor: "pointer" }} title="Chụp ảnh mô tả / quét QR (sắp ra mắt)" onClick={() => { setCamPhoto(null); setCamOpen(true); }}>
            <i className="fas fa-camera" />
          </button>
        </div>
      </div>

      <div className="stats-row">
        {summary.map((item) => (
          <div className="stat-card" key={item.label}>
            <div className="stat-title">{item.label}</div>
            <div className="stat-value">{Number(item.value).toLocaleString('vi-VN')}</div>
          </div>
        ))}
      </div>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="far fa-clock" />
          Thiết bị mượn gần đây
        </div>
        <div className="equipment-container">
          {recentBorrows.length === 0 ? (
            <div className="empty-state">
              <h3>Chưa có thiết bị mượn</h3>
              <p>Chưa có phiếu mượn nào gần đây. Khi có hoạt động mượn/trả, thiết bị sẽ hiển thị tại đây.</p>
            </div>
          ) : (
            <div className="equipment-grid">
              {recentBorrows.slice(0, recentVisibleCount).map((item, index) => (
                <button
                  type="button"
                  className="equipment-card"
                  key={`${item.id}-${item.soPhieuMuon || index}`}
                  style={{ width: '100%', textAlign: 'inherit', font: 'inherit', cursor: 'pointer' }}
                  title="Xem chi tiết phiếu mượn"
                  onClick={() => setActiveRecent(item)}
                >
                  <div className="equipment-img-box">
                    <img
                      src={item.image || createPlaceholderImage(item.id || 'TB')}
                      alt={item.name}
                      onError={(event) => { event.currentTarget.src = createPlaceholderImage('No Image'); }}
                    />
                  </div>
                  <div className="equipment-info">
                    <div className="equipment-name">{item.name}</div>
                    <div className="equipment-meta" style={{ fontSize: 12, color: '#64748b' }}>
                      Mã: {item.id}
                      {item.borrowStatus ? ` · ${item.borrowStatus}` : ''}
                    </div>
                    <div className="equipment-time">{item.timeAgo}</div>
                  </div>
                </button>
              ))}
            </div>
          )}
          {recentBorrows.length > recentVisibleCount ? (
            <div className="see-more-container">
              <button type="button" className="btn-see-more" onClick={() => setRecentVisibleCount((n) => n + 5)}>
                Xem thêm ({recentBorrows.length - recentVisibleCount}) <i className="fas fa-chevron-down" />
              </button>
            </div>
          ) : null}
        </div>
      </section>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="fas fa-tools" />
          Thông tin bảo trì/cập nhật
        </div>
        <div className="maintenance-content">
          {maintenance.length === 0 ? (
            <div className="maintenance-item">
              <i className="fas fa-paperclip" />
              <span>Chưa có thông báo bảo trì</span>
            </div>
          ) : (
            maintenance.slice(0, visibleCount).map((item) => (
              <button
                type="button"
                className="maintenance-item"
                key={item.id}
                title="Xem chi tiết thông báo"
                onClick={() => setActiveNotice(item)}
              >
                <i className="fas fa-paperclip" />
                <span className="maintenance-item-text">
                  <strong style={{ color: '#0D8ABC' }}>[Bảo trì]</strong>
                  {' '}{item.title}
                  {item.date ? <> – <span style={{ color: '#999', fontSize: 13 }}>{item.date}</span></> : null}
                  <i className="fas fa-chevron-right" style={{ marginLeft: 8, fontSize: 11, color: '#94a3b8' }} />
                </span>
              </button>
            ))
          )}
          <div className="see-more-container">
            {maintenance.length > visibleCount ? (
              <button type="button" className="btn-see-more" onClick={() => setVisibleCount((n) => n + 5)}>
                Xem thêm ({maintenance.length - visibleCount}) <i className="fas fa-chevron-down" />
              </button>
            ) : null}
          </div>
        </div>
      </section>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="fas fa-bullhorn" />
          Tin văn &amp; Thành tựu
        </div>
        <div className="maintenance-content">
          <div className="highlight-grid">
            {highlightBlocks.map((block) => (
              <article className="highlight-card" key={block.title}>
                <h4><i className={block.icon} /> {block.title}</h4>
                <ul className="highlight-list">
                  {block.items.map((text) => (
                    <li key={text}>{text}</li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
          <div className="partner-strip">
            <p className="partner-title">
              <i className="fas fa-handshake" /> Đơn vị đồng hành
            </p>
            <div className="partner-logo-row">
              {partnerLogos.map((logo) => (
                <div className="partner-logo-item" key={logo}>
                  <img src={`${appBase}/Images/${logo}`} alt={logo.replace(/\.(png|jpg|jpeg|webp)$/i, '')} />
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {activeRecent ? (
        <div
          className="maint-modal-overlay"
          role="dialog"
          aria-modal="true"
          onClick={(event) => { if (event.target === event.currentTarget) setActiveRecent(null); }}
        >
          <div className="maint-modal-dialog">
            <div className="maint-modal-head">
              <span className="maint-modal-badge"><i className="fas fa-hand-holding" /></span>
              <div className="maint-modal-titles">
                <p className="maint-modal-kicker">Thiết bị mượn gần đây</p>
                <h2 className="maint-modal-title">{activeRecent.name}</h2>
              </div>
              <button type="button" className="maint-modal-close" aria-label="Đóng" onClick={() => setActiveRecent(null)}>
                <i className="fas fa-xmark" />
              </button>
            </div>
            <div className="maint-modal-body">
              <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap' }}>
                <img
                  src={activeRecent.image || createPlaceholderImage(activeRecent.id || 'TB')}
                  alt={activeRecent.name}
                  style={{ width: 132, height: 132, objectFit: 'cover', borderRadius: 10, border: '1px solid #e2e8f0', backgroundColor: '#f1f5f9', flexShrink: 0 }}
                  onError={(event) => { event.currentTarget.src = createPlaceholderImage('No Image'); }}
                />
                <div style={{ flex: 1, minWidth: 190, display: 'grid', gap: 8, alignContent: 'center' }}>
                  <p style={{ margin: 0, fontSize: 14, color: '#334155' }}><strong>Mã thiết bị:</strong> {activeRecent.id || '—'}</p>
                  {activeRecent.soPhieuMuon ? <p style={{ margin: 0, fontSize: 14, color: '#334155' }}><strong>Số phiếu:</strong> {activeRecent.soPhieuMuon}</p> : null}
                  {activeRecent.borrowStatus ? <p style={{ margin: 0, fontSize: 14, color: '#334155' }}><strong>Trạng thái phiếu:</strong> {activeRecent.borrowStatus}</p> : null}
                  {activeRecent.timeAgo ? <p style={{ margin: 0, fontSize: 14, color: '#334155' }}><strong>Thời điểm:</strong> {activeRecent.timeAgo}</p> : null}
                  {activeRecent.username ? <p style={{ margin: 0, fontSize: 14, color: '#334155' }}><strong>Người mượn:</strong> {activeRecent.username}</p> : null}
                </div>
              </div>
            </div>
          </div>
        </div>
      ) : null}

      {activeNotice ? (
        <div
          className="maint-modal-overlay"
          role="dialog"
          aria-modal="true"
          onClick={(event) => { if (event.target === event.currentTarget) setActiveNotice(null); }}
        >
          <div className="maint-modal-dialog">
            <div className="maint-modal-head">
              <span className="maint-modal-badge"><i className="fas fa-screwdriver-wrench" /></span>
              <div className="maint-modal-titles">
                <p className="maint-modal-kicker">Thông tin bảo trì / cập nhật</p>
                <h2 className="maint-modal-title">{activeNotice.title || 'Thông báo'}</h2>
              </div>
              <button type="button" className="maint-modal-close" aria-label="Đóng" onClick={() => setActiveNotice(null)}>
                <i className="fas fa-xmark" />
              </button>
            </div>
            <div className="maint-modal-body">
              {String(activeNotice.content ?? '').trim() !== '' ? (
                <p className="maint-modal-desc">{activeNotice.content}</p>
              ) : (
                <p className="maint-modal-empty">Chưa có nội dung chi tiết cho thông báo này.</p>
              )}
              <div className="maint-meta-row">
                <span><i className="far fa-calendar" /> Cập nhật: {activeNotice.date || '—'}</span>
                {activeNotice.id ? <span><i className="fas fa-hashtag" /> #{activeNotice.id}</span> : null}
              </div>
            </div>
          </div>
        </div>
      ) : null}
      {camOpen ? <CameraModal onClose={() => setCamOpen(false)} onPhoto={(d) => setCamPhoto(d)} /> : null}
    </LegacyPageShell>
  );
}

function CameraModal({ onClose, onPhoto }) {
  const videoRef = useRef(null);
  const [error, setError] = useState('');
  const [photo, setPhoto] = useState(null);
  const streamRef = useRef(null);

  useEffect(() => {
    let cancelled = false;
    navigator.mediaDevices?.getUserMedia({ video: { facingMode: 'environment' } })
      .then((stream) => {
        if (cancelled) { stream.getTracks().forEach((t) => t.stop()); return; }
        streamRef.current = stream;
        if (videoRef.current) videoRef.current.srcObject = stream;
      })
      .catch(() => setError('Không truy cập được máy ảnh. Hãy cho phép quyền camera trên trình duyệt.'));
    return () => {
      cancelled = true;
      streamRef.current?.getTracks().forEach((t) => t.stop());
    };
  }, []);

  const capture = () => {
    const video = videoRef.current;
    if (!video) return;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    setPhoto(dataUrl);
  };

  return (
    <div className="maint-modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="maint-modal-dialog" style={{ maxWidth: 460 }}>
        <div className="maint-modal-head">
          <span className="maint-modal-badge"><i className="fas fa-camera" /></span>
          <div className="maint-modal-titles">
            <p className="maint-modal-kicker">Máy ảnh</p>
            <h2 className="maint-modal-title" style={{ fontSize: 16 }}>Chụp ảnh mô tả / quét QR</h2>
          </div>
          <button type="button" className="maint-modal-close" onClick={onClose}><i className="fas fa-xmark" /></button>
        </div>
        <div className="maint-modal-body" style={{ textAlign: 'center' }}>
          <div style={{ aspectRatio: '3/4', maxWidth: 320, margin: '0 auto', borderRadius: 12, overflow: 'hidden', backgroundColor: '#0f172a', border: '1px solid #334155' }}>
            <video ref={videoRef} autoPlay playsInline muted style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
          </div>
          {error ? <p style={{ color: '#ef4444', fontSize: 13.5, marginTop: 10 }}>{error}</p> : null}
          {photo ? (
            <div>
              <img src={photo} alt="Đã chụp" style={{ width: '100%', maxWidth: 320, borderRadius: 10, border: '3px solid #16a34a' }} />
              <p style={{ color: '#16a34a', fontWeight: 700, margin: '10px 0 0' }}>Đã chụp ảnh — sẽ dùng để quét QR tìm thiết bị ở phiên bản sắp tới.</p>
              <button type="button" className="btn-see-more" style={{ marginTop: 10 }} onClick={onClose}>Hoàn tất</button>
            </div>
          ) : (
            <button type="button" onClick={capture} style={{ marginTop: 14, border: 'none', background: 'linear-gradient(90deg,#1665b8,#2b8de4)', color: '#fff', borderRadius: 999, padding: '12px 30px', fontWeight: 700, fontSize: 15, cursor: 'pointer' }}>
              <i className="fas fa-camera" style={{ marginRight: 8 }} /> Chụp ảnh
            </button>
          )}
        </div>
      </div>
    </div>
  );
}