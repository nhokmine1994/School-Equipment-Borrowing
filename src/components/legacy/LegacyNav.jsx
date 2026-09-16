import { useState } from 'react';
import { NavLink } from 'react-router-dom';

const navItems = [
  { label: 'Trang chủ', to: '/', end: true },
  { label: 'Kho thiết bị', to: '/devices' },
  { label: 'Đăng ký phòng học', to: '/rooms' },
  { label: 'Kho cá nhân', to: '/personal' },
  { label: 'Tin tức', to: '/news' },
  { label: 'Liên hệ', to: '/about' },
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
            {item.label}
          </NavLink>
        ))}
      </div>
    </nav>
  );
}