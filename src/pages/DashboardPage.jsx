import { useEffect, useState } from 'react';
import LegacyPageShell from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';

const defaults = [
  ['Tổng thiết bị', 'total_devices', 'fas fa-boxes-stacked'],
  ['Đang cho mượn', 'borrowed_devices', 'fas fa-hand-holding'],
  ['Phòng học', 'total_rooms', 'fas fa-door-open'],
  ['Yêu cầu chờ', 'pending_requests', 'fas fa-hourglass-half'],
];

export default function DashboardPage() {
  const [summary, setSummary] = useState({});
  useEffect(() => { api.getDashboardSummary().then((result) => { if (result?.success) setSummary(result.data || {}); }); }, []);
  return (
    <LegacyPageShell title="Tổng quan hệ thống" icon="fas fa-chart-line">
      <p className="page-intro">Tình hình sử dụng thiết bị và phòng học được cập nhật tập trung.</p>
      <div className="page-grid">
        {defaults.map(([label, key, icon]) => (
          <article className="page-card" key={key}><h3><i className={icon} /> {label}</h3><strong style={{ fontSize: 32, color: '#0d8abc' }}>{summary[key] ?? '...'}</strong></article>
        ))}
      </div>
    </LegacyPageShell>
  );
}
