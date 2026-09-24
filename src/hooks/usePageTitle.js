import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

const routeTitleMap = {
  '/': 'SEB - Hệ thống mượn/trả thiết bị Lộc An',
  '/login': 'SEB | Đăng nhập',
  '/dashboard': 'SEB | Dashboard',
  '/devices': 'SEB | Thiết bị',
  '/borrow': 'SEB | Mượn trả',
  '/rooms': 'SEB | Phòng học',
  '/personal': 'SEB | Kho cá nhân',
  '/profile': 'SEB | Hồ sơ',
  '/news': 'Tin tức & Thông báo — SEB Lộc An',
  '/about': 'SEB | Liên hệ',
  '/ve-chung-toi': 'SEB | Về chúng tôi',
  '/ve-du-an': 'SEB | Về dự án',
};

export default function usePageTitle() {
  const location = useLocation();

  useEffect(() => {
    const title = routeTitleMap[location.pathname] || 'SEB';
    document.title = title;
  }, [location.pathname]);
}
