const APP_ROOT = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';
const API_BASE = `${APP_ROOT}/api/seb_api.php`;

async function requestJson(action, query = {}) {
  const params = new URLSearchParams({ action });
  Object.entries(query).forEach(([key, value]) => params.set(key, String(value)));
  const response = await fetch(`${API_BASE}?${params.toString()}`, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
    },
  });

  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    throw new Error(payload?.error || 'Không thể tải dữ liệu.');
  }

  return payload;
}

const fallbackDashboardSummary = {
  total_devices: 128,
  maintenance_devices: 0,
  borrowed_devices: 36,
  total_rooms: 12,
  pending_requests: 8,
};

const fallbackDevices = [
  { name: 'Laptop Dell XPS 13', statusLabel: 'Sẵn sàng' },
  { name: 'Máy chiếu Epson', statusLabel: 'Đang cho mượn' },
  { name: 'Bảng điện tử', statusLabel: 'Bảo trì' },
  { name: 'Webcam Logitech', statusLabel: 'Sẵn sàng' },
];

const fallbackBorrowHistory = [
  { user: 'Nguyễn Văn A', item: 'Laptop Dell XPS', status: 'Đã duyệt' },
  { user: 'Trần Thị B', item: 'Máy chiếu', status: 'Chờ duyệt' },
  { user: 'Lê Văn C', item: 'Webcam', status: 'Đang mượn' },
];

const fallbackRooms = [
  { name: 'Phòng A101', status: 'Trống' },
  { name: 'Phòng A102', status: 'Đang sử dụng' },
  { name: 'Phòng B205', status: 'Trống' },
  { name: 'Phòng C301', status: 'Đặt trước' },
];

export const api = {
  getDashboardSummary: async () => {
    try {
      const payload = await requestJson('dashboard_summary');
      return {
        success: true,
        data: {
          ...fallbackDashboardSummary,
          ...(payload?.summary ?? {}),
        },
      };
    } catch {
      return {
        success: true,
        data: fallbackDashboardSummary,
      };
    }
  },

  getCurrentUser: async () => {
    try {
      const payload = await requestJson('current_user');
      return {
        success: true,
        data: payload?.user ?? { name: 'Khách', role: 'Guest', username: '' },
        loggedIn: Boolean(payload?.loggedIn),
      };
    } catch {
      return {
        success: true,
        data: { name: 'Khách', role: 'Guest', username: '' },
        loggedIn: false,
      };
    }
  },

  loginUser: async (username, password) => {
    const response = await fetch(`${API_BASE}?action=login_user`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ username, password }),
    });

    const payload = await response.json().catch(() => null);
    if (!response.ok) {
      return {
        success: false,
        error: payload?.error || 'Đăng nhập thất bại.',
      };
    }

    return {
      success: true,
      data: payload?.user ?? null,
    };
  },

  logoutUser: async () => {
    try {
      const payload = await requestJson('logout_user');
      return {
        success: Boolean(payload?.ok),
      };
    } catch {
      return {
        success: false,
      };
    }
  },

  getDevices: async () => {
    try {
      const payload = await requestJson('device_list');
      return {
        success: true,
        data: payload?.items ?? fallbackDevices,
      };
    } catch {
      return {
        success: true,
        data: fallbackDevices,
      };
    }
  },

  getBorrowList: async () => {
    try {
      const payload = await requestJson('borrow_list');
      return {
        success: true,
        data: payload?.items ?? fallbackBorrowHistory,
      };
    } catch {
      return {
        success: true,
        data: fallbackBorrowHistory,
      };
    }
  },

  createBorrowRequest: async (body) => {
    try {
      const response = await fetch(`${API_BASE}?action=borrow_request`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
      });
      const payload = await response.json().catch(() => null);
      return response.ok
        ? { success: true, data: payload ?? null }
        : { success: false, error: payload?.error || 'Không gửi được yêu cầu mượn.' };
    } catch {
      return { success: false, error: 'Không kết nối được máy chủ.' };
    }
  },

  getRoomList: async () => {
    try {
      const payload = await requestJson('room_list');
      return {
        success: true,
        data: payload?.items ?? fallbackRooms,
      };
    } catch {
      return {
        success: true,
        data: fallbackRooms,
      };
    }
  },

  getRoomSchedule: async (roomType, roomNumber) => {
    try {
      const payload = await requestJson('room_schedule', { roomType, roomNumber });
      return { success: true, data: payload?.items ?? [] };
    } catch {
      return { success: true, data: [] };
    }
  },

  createRoomBooking: async (body) => {
    try {
      const response = await fetch(`${API_BASE}?action=room_create`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
      });
      const payload = await response.json().catch(() => null);
      return response.ok
        ? { success: true, data: payload ?? null }
        : { success: false, error: payload?.error || 'Không gửi được đăng ký phòng.' };
    } catch {
      return { success: false, error: 'Không kết nối được máy chủ.' };
    }
  },

  cancelRoomBooking: async (bookingId) => {
    try {
      const response = await fetch(`${API_BASE}?action=room_cancel`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ bookingId }),
      });
      const payload = await response.json().catch(() => null);
      return response.ok
        ? { success: true, data: payload ?? null }
        : { success: false, error: payload?.error || 'Không hủy được lịch phòng.' };
    } catch {
      return { success: false, error: 'Không kết nối được máy chủ.' };
    }
  },

  getRecentBorrows: async () => {
    try {
      const payload = await requestJson('recent_borrows');
      return {
        success: true,
        data: payload?.items ?? [],
      };
    } catch {
      return {
        success: true,
        data: [],
      };
    }
  },

  getNews: async () => {
    try {
      const payload = await requestJson('news_list');
      return {
        success: true,
        data: payload?.news ?? {},
      };
    } catch {
      return {
        success: true,
        data: {
          banner: { articles: '42', topics: '5', views: '1.2K' },
          featured: null,
          articles: [],
        },
      };
    }
  },

  getTeamMembers: async () => {
    try {
      const payload = await requestJson('team_members');
      return {
        success: true,
        data: payload?.team ?? { intro: '', members: [] },
      };
    } catch {
      return {
        success: true,
        data: { intro: '', members: [] },
      };
    }
  },

  getMaintenanceList: async () => {
    try {
      const payload = await requestJson('maintenance_list');
      return {
        success: true,
        data: payload?.items ?? [],
      };
    } catch {
      return {
        success: true,
        data: [],
      };
    }
  },

  getPersonalList: async () => {
    try {
      const payload = await requestJson('personal_list');
      return {
        success: true,
        data: payload?.items ?? [],
      };
    } catch {
      return {
        success: true,
        data: [],
      };
    }
  },

  addPersonalDevice: async (maThietBi) => {
    try {
      const response = await fetch(`${API_BASE}?action=personal_add`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ maThietBi }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok) {
        return { success: false, error: payload?.error || 'Không thêm được thiết bị.' };
      }
      return { success: true, data: payload ?? null };
    } catch {
      return { success: false, error: 'Không kết nối được máy chủ.' };
    }
  },

  removePersonalDevice: async (id) => {
    try {
      const response = await fetch(`${API_BASE}?action=personal_remove`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ id }),
      });
      const payload = await response.json().catch(() => null);
      return { success: Boolean(response.ok), data: payload ?? null };
    } catch {
      return { success: false };
    }
  },
};
