import { useState } from 'react';
import { NavLink } from 'react-router-dom';

const navItems = [
  { label: 'Trang chủ', to: '/', icon: 'fas fa-house', end: true },
  { label: 'Kho thiết bị', to: '/devices', icon: 'fas fa-boxes-stacked' },
  { label: 'Đăng ký phòng học', to: '/rooms', icon: 'fas fa-calendar-check' },
  { label: 'Kho cá nhân', to: '/personal', icon: 'fas fa-briefcase' },
  { label: 'Tin tức', to: '/news', icon: 'fas fa-newspaper' },
  { label: 'Liên hệ', to: '/about', icon: 'fas fa-address-book' },
];

export default function LegacyNav() {
  const [open, setOpen] = useState(false);

  return (
    <nav className="nav-bar" id="mainNav">
      <button
        className={`hamburger-btn${open ? ' open' : ''}`}
        id="hamburgerBtn"
        aria-label="Mở menu"
        aria-expanded={open}
        type="button"
        onClick={() => setOpen((value) => !value)}
      >
        <span />
        <span />
        <span />
      </button>
      <div className="nav-links" id="navLinks">
        {navItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            className={({ isActive }) => `nav-tab${isActive ? ' active' : ''}`}
            onClick={() => setOpen(false)}
          >
            <i className={item.icon} style={{ fontSize: '0.9em', opacity: 0.9 }} />
            <span>{item.label}</span>
          </NavLink>
        ))}
      </div>
    </nav>
  );
}