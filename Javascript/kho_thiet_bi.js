// javascript/kho_thiet_bi.js

let allDevices = [];
let filteredDevices = [];
let currentPage = 1;
const itemsPerPage = 8;
let myRoomUiBound = false;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeJsSingleQuoted(value) {
    return String(value ?? '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'");
}

function requireSebApi() {
    if (typeof window.sebApi === 'undefined') {
        throw new Error('Thiếu seb_api.js — không thể gọi SQL Server.');
    }
    return window.sebApi;
}

function requireAuthForAction() {
    if (!getAuthUsername()) {
        window.dispatchEvent(new CustomEvent('seb:require-auth', {
            detail: {
                keepLocked: false,
                message: 'Vui lòng đăng nhập để thực hiện thao tác này.',
            },
        }));
        return false;
    }
    return true;
}

function getSafeImageMarkup(imageHtml) {
    if (typeof imageHtml !== 'string' || imageHtml.trim() === '') {
           return '<div class="device-placeholder"></div>';
    }

    const rawValue = imageHtml.trim();
    let src = rawValue;
    let alt = 'Thiết bị';

    if (rawValue.toLowerCase().includes('<img')) {
        const template = document.createElement('template');
        template.innerHTML = rawValue;
        const img = template.content.querySelector('img');

        if (!img) {
            return '<div style="width: 100%; height: 100%; background: #f1f5f9;"></div>';
        }

        src = (img.getAttribute('src') || '').trim();
        alt = img.getAttribute('alt') || 'Thiết bị';
    }

    const isTrustedSrc = /^(https?:\/\/|\/|\.\.?\/)/i.test(src);
    if (!isTrustedSrc) {
           return '<div class="device-placeholder"></div>';
    }

    const safeSrc = escapeHtml(src);
    const safeAlt = escapeHtml(alt);
    return `<img src="${safeSrc}" alt="${safeAlt}" style="width: 100%; height: 100%; object-fit: cover; display: block;" />`;
}

function renderCard(device) {
    // Determine unavailable by checking both normalized `status` and original `statusLabel`
    const _statusRaw = String((device.status || device.statusLabel || '')).toLowerCase();
    const isUnavailable = /hết|het|hết hàng|unavailable|ngưng|ngung|hỏng|hong|bao tri|bảo trì/u.test(_statusRaw);
    const statusText = isUnavailable ? 'Bảo trì / Hết hàng' : 'Sẵn sàng';
    const statusClass = isUnavailable ? 'unavailable' : 'available';
    const deviceDbId = String(device.dbId || device.id || '');
    const deviceCode = String(device.id || device.dbId || '');
    const safeDeviceIdForJs = escapeJsSingleQuoted(deviceDbId);
    const safeDeviceCode = escapeHtml(deviceCode);
    const safeName = escapeHtml(device.name || 'Thiết bị');
    const safeCategory = escapeHtml(device.category || '');
    const safeSubject = escapeHtml(device.subject || '');
    const safeStatus = escapeHtml(device.status || '');
    const safeStatusLabel = escapeHtml(device.statusLabel || (isUnavailable ? 'Bảo trì / Hết hàng' : 'Sẵn sàng'));
    const safeQuantity = escapeHtml(device.quantity ?? '');
    const safeDescription = escapeHtml(device.description || '');
    const safeImage = getSafeImageMarkup(device.image);

    // Disable button attributes
    const qtyNum = Number(device.quantity ?? 0) || 0;
    const btnState = isUnavailable || qtyNum === 0 ? 'disabled' : '';
    const btnText = isUnavailable ? 'Chờ nhập kho' : (qtyNum === 0 ? 'HẾT' : 'Mượn');

    const isPersonalPage = window.location.pathname.includes('kho-ca-nhan.php');
    // Ensure buttons find the device reliably by id/code using helper in the script
    let actionsHtml = '';
    if (isPersonalPage) {
        actionsHtml = `<div class="action-controls"><button class="btn-borrow" style="width: 100%; margin: 0; display: block;" ${btnState} onclick="handleBorrow('${safeDeviceIdForJs}')">${btnText}</button></div>`;
    } else {
        actionsHtml = `
            <div class="action-controls">
                <button class="btn-borrow" ${btnState} onclick="handleBorrow('${safeDeviceIdForJs}')">${btnText}</button>
                <button class="btn-add" onclick="handleAdd('${safeDeviceIdForJs}')">Thêm</button>
            </div>
        `;
    }

    return `
        <div class="device-card" 
             data-name="${safeName}" 
             data-category="${safeCategory}" 
             data-subject="${safeSubject}"
             data-status="${safeStatus}">
            <div class="device-image" style="cursor: pointer;" onclick="window.openDeviceModalById('${safeDeviceIdForJs}')" title="Nhấn để xem cấu hình chi tiết">${safeImage}</div>
            <div class="device-info">
                <h3 style="cursor: pointer; color: #2563eb; transition: color 0.2s;" onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'" onclick="window.openDeviceModalById('${safeDeviceIdForJs}')" title="Nhấn để xem cấu hình chi tiết">${safeName}</h3>
                <p><strong>ID thiết bị:</strong> ${safeDeviceCode}</p>
                <p><strong>Số lượng:</strong> ${safeQuantity || '---'}</p>
                <p><strong>Môn học:</strong> ${safeSubject || 'Chung'}</p>
                ${safeDescription ? `<p><strong>Thông tin:</strong> ${safeDescription}</p>` : ''}
                <p><strong>Trạng thái:</strong> <span class="status ${statusClass}">${safeStatusLabel}</span></p>
            </div>
            <div class="device-actions">
                ${actionsHtml}
            </div>
        </div>
    `;
}

// Helper to find device by id robustly.
function findDeviceById(id) {
    if (!id) return null;
    const sid = String(id);
    return allDevices.find(d => String(d.dbId || d.id || '') === sid || String(d.id || d.dbId || '') === sid || String(d.code || d.id || '') === sid) || null;
}

async function handleBorrow(id) {
    const device = findDeviceById(id);
    if (!device) return;
    if (!requireAuthForAction()) return;

    // Extra guard: prevent borrow if device is unavailable according to label/status
    const _statusRaw = String((device.status || device.statusLabel || '')).toLowerCase();
    const isUnavailable = /hết|het|hết hàng|unavailable|ngưng|ngung|hỏng|hong|bao tri|bảo trì/u.test(_statusRaw);
    const qtyNum = Number(device.quantity ?? 0) || 0;
    if (isUnavailable || qtyNum === 0) {
        if (typeof sebShowMessage === 'function') {
            sebShowMessage('Thiết bị hiện không thể mượn (Hết hoặc đang bảo trì).', 'error');
        } else {
            alert('Thiết bị hiện không thể mượn (Hết hoặc đang bảo trì).');
        }
        return;
    }

    // Nếu trang hiện tại KHÔNG phải là trang kho cá nhân, ưu tiên mở Modal chi tiết
    // để người dùng chọn số lượng / ngày trả. Modal sẽ gọi API khi người dùng Submit.
    const isPersonalPage = window.location.pathname.includes('kho-ca-nhan.php');
    if (!isPersonalPage && typeof window.openDeviceModal === 'function') {
        try {
            window.openDeviceModal(device);
        } catch (err) {
            console.warn('Không thể mở modal thiết bị:', err);
            // fallback: nếu modal không mở được, gửi yêu cầu mượn nhanh như cũ
        }
        return;
    }

    // Ở trang kho cá nhân (hoặc khi không có modal), gửi yêu cầu mượn ngay lập tức
    try {
        const api = requireSebApi();
        const result = await api.borrowCreate({ maThietBi: String(device.dbId || device.id || id), soLuong: 1 });
        if (typeof sebShowMessage === 'function') {
            sebShowMessage(result.message || ('Đã gửi yêu cầu mượn: ' + device.name + '. Chờ quản trị viên duyệt.'), 'success');
        } else {
            alert(result.message || ('Đã gửi yêu cầu mượn: ' + device.name + '. Chờ quản trị viên duyệt.'));
        }

        if (isPersonalPage) {
            await loadPersonalDevices();
            applyFilters();
        }

        if (typeof window.renderBorrowHistorySidebar === 'function') {
            await window.renderBorrowHistorySidebar();
        }
    } catch (error) {
        if (typeof sebShowMessage === 'function') {
            sebShowMessage(error.message || 'Không gửi được yêu cầu mượn.', 'error');
        } else {
            alert(error.message || 'Không gửi được yêu cầu mượn.');
        }
    }
}

async function handleAdd(id) {
    const device = findDeviceById(id);
    if (!device) return;
    if (!requireAuthForAction()) return;

    try {
        const api = requireSebApi();
        const result = await api.personalAdd({ maThietBi: String(device.dbId || device.id || id) });
        if (result.duplicate) {
            if (typeof sebShowMessage === 'function') sebShowMessage(result.message || 'Thiết bị này đã có trong kho cá nhân!', 'info'); else alert(result.message || 'Thiết bị này đã có trong kho cá nhân!');
        } else {
            if (typeof sebShowMessage === 'function') sebShowMessage(result.message || ('Đã thêm vào kho cá nhân (SQL Server): ' + device.name), 'success'); else alert(result.message || ('Đã thêm vào kho cá nhân (SQL Server): ' + device.name));
        }
    } catch (error) {
        if (typeof sebShowMessage === 'function') sebShowMessage(error.message || 'Không thêm được vào kho cá nhân.', 'error'); else alert(error.message || 'Không thêm được vào kho cá nhân.');
    }
}

async function loadPersonalDevices() {
    const api = requireSebApi();
    const result = await api.personalList();
    allDevices = Array.isArray(result.items) ? result.items : [];
    return allDevices;
}

function renderPage(page) {
    const grid = document.getElementById('equipment-grid');
    if (!grid) return;

    const startIndex = (page - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filteredDevices.slice(startIndex, endIndex);

    if (pageData.length === 0) {
        // Giao diện không tìm thấy kết quả
        grid.innerHTML = `
            <div class="empty-state">
                <h3>Không có kết quả</h3>
                <p>Không tìm thấy thiết bị nào khớp với từ khóa tìm kiếm hoặc bộ lọc.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = pageData.map(d => renderCard(d)).join('');
}

function renderPagination() {
    const container = document.getElementById('pagination-container');
    if (!container) return;

    const totalPages = Math.ceil(filteredDevices.length / itemsPerPage);

    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    if (currentPage > 1) {
        html += `<button class="page-btn prev" onclick="goToPage(${currentPage - 1})">Prev</button>`;
    } else {
        html += `<button class="page-btn prev" disabled>Prev</button>`;
    }

    for (let i = 1; i <= totalPages; i++) {
        const activeClass = (i === currentPage) ? 'active' : '';
        html += `<button class="page-btn ${activeClass}" onclick="goToPage(${i})">${i}</button>`;
    }

    if (currentPage < totalPages) {
        html += `<button class="page-btn next" onclick="goToPage(${currentPage + 1})">Next</button>`;
    } else {
        html += `<button class="page-btn next" disabled>Next</button>`;
    }

    container.innerHTML = html;
}

window.goToPage = function (page) {
    const totalPages = Math.ceil(filteredDevices.length / itemsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        renderPage(currentPage);
        renderPagination();

        const searchBar = document.getElementById('search-input');
        if (searchBar) {
            window.scrollTo({
                top: searchBar.offsetTop - 50,
                behavior: 'smooth'
            });
        }
    }
};

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function setupFilters() {
    const checkboxes = document.querySelectorAll('.filter-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            applyFilters();
        });
    });

    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            applyFilters();
        }, 300));
    }

    const filterCategory = document.getElementById('filter-category');
    if (filterCategory) filterCategory.addEventListener('change', applyFilters);

    const filterSubject = document.getElementById('filter-subject');
    if (filterSubject) filterSubject.addEventListener('change', applyFilters);
}

function applyFilters() {
    const checkedBoxes = Array.from(document.querySelectorAll('.filter-checkbox:checked'));
    let selectedCategories = checkedBoxes.filter(cb => cb.dataset.type === 'category').map(cb => cb.value);
    let selectedSubjects = checkedBoxes.filter(cb => cb.dataset.type === 'subject').map(cb => cb.value);

    const searchInput = document.getElementById('search-input');
    const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';

    const filterCategory = document.getElementById('filter-category');
    if (filterCategory && filterCategory.value) {
        selectedCategories.push(filterCategory.value);
    }

    const filterSubject = document.getElementById('filter-subject');
    if (filterSubject && filterSubject.value) {
        selectedSubjects.push(filterSubject.value);
    }

    filteredDevices = allDevices.filter(device => {
        const matchCategory = selectedCategories.length === 0 || selectedCategories.includes(device.category);
        const matchSubject = selectedSubjects.length === 0 || selectedSubjects.includes(device.subject);
        const deviceName = String(device.name || '').toLowerCase();
        const matchSearch = searchTerm === '' || deviceName.includes(searchTerm);

        return matchCategory && matchSubject && matchSearch;
    });

    currentPage = 1;
    renderPage(currentPage);
    renderPagination();
}

function renderSkeleton() {
    const grid = document.getElementById('equipment-grid');
    if (!grid) return;

    // Sinh HTML placeholder của 8 skeleton form tương ứng với itemsPerPage
    const skeletonHTML = Array(8).fill(`
        <div class="skeleton-card">
            <div class="skeleton-shimmer skeleton-img"></div>
            <div class="skeleton-shimmer skeleton-text title"></div>
            <div class="skeleton-shimmer skeleton-text"></div>
            <div class="skeleton-shimmer skeleton-text short"></div>
            <div class="skeleton-actions">
                <div class="skeleton-shimmer skeleton-btn"></div>
                <div class="skeleton-shimmer skeleton-btn"></div>
            </div>
        </div>
    `).join('');

    grid.innerHTML = skeletonHTML;
}

function getAuthUsername() {
    if (window && typeof window.__username === 'string') {
        return window.__username.trim();
    }
    return '';
}

window.renderBorrowHistorySidebar = async function () {
    const listEl = document.getElementById('borrow-history-list');
    if (!listEl) return;

    if (!getAuthUsername()) {
        listEl.innerHTML = '<li style="color: #64748b; font-size: 0.9rem;">Đăng nhập để xem lịch sử mượn.</li>';
        return;
    }

    let borrowHistory = [];
    try {
        const api = requireSebApi();
        const result = await api.borrowList();
        borrowHistory = Array.isArray(result.items) ? result.items : [];
    } catch (error) {
        listEl.innerHTML = '<li style="color: #ef4444; font-size: 0.9rem;">Không tải được lịch sử mượn.</li>';
        return;
    }

    if (borrowHistory.length === 0) {
        listEl.innerHTML = '<li style="color: #64748b; font-size: 0.9rem;">Chưa có lịch sử mượn.</li>';
        return;
    }

    const recent = borrowHistory.slice(0, 5);
    listEl.innerHTML = recent.map(item => {
        const dateObj = new Date(item.borrowDate);
        const date = dateObj.toLocaleDateString('vi-VN');
        const time = dateObj.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        const safeName = escapeHtml(item.name || 'Thiết bị');
        const safeImage = getSafeImageMarkup(item.image);

        return `
            <li style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: flex-start;">
                <div style="width: 44px; height: 44px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center;">
                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; transform: scale(0.65);">
                        ${safeImage}
                    </div>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem; line-height: 1.3; margin-bottom: 4px;">${safeName}</div>
                    <div style="font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 4px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        ${time} - ${date}
                    </div>
                </div>
            </li>
        `;
    }).join('');
};

window.renderMyRoomBookings = async function () {
    const listEl = document.getElementById('my-room-bookings-list');
    if (!listEl) return;

    const roomFilterEl = document.getElementById('my-room-filter-room');
    const dayFilterEl = document.getElementById('my-room-filter-day');
    const keywordFilterEl = document.getElementById('my-room-filter-keyword');

    let userBookings = [];
    if (!getAuthUsername()) {
        listEl.innerHTML = '<div class="my-room-empty">Đăng nhập để xem lịch đăng ký phòng.</div>';
        return;
    }

    try {
        const api = requireSebApi();
        const result = await api.roomList();
        userBookings = Array.isArray(result.items) ? result.items : [];
    } catch (error) {
        listEl.innerHTML = '<div class="my-room-empty">Không tải được lịch phòng từ máy chủ.</div>';
        return;
    }

    if (roomFilterEl) {
        const roomOptions = Array.from(new Set(userBookings.map((booking) => {
            const roomNumber = String(booking.roomNumberLabel || booking.roomNumber || '').trim();
            return roomNumber;
        }).filter(Boolean)));
        const currentRoomFilter = roomFilterEl.value;
        roomFilterEl.innerHTML = '<option value="">Tất cả phòng</option>' + roomOptions.map((room) => `<option value="${escapeHtml(room)}">Phòng ${escapeHtml(room)}</option>`).join('');
        roomFilterEl.value = roomOptions.includes(currentRoomFilter) ? currentRoomFilter : '';
    }

    if (dayFilterEl) {
        const dayOptions = Array.from(new Set(userBookings.flatMap((booking) => {
            const slots = Array.isArray(booking.slots) ? booking.slots : [];
            return slots.map((slot) => String(slot.dayLabel || '').trim()).filter(Boolean);
        })));
        const currentDayFilter = dayFilterEl.value;
        dayFilterEl.innerHTML = '<option value="">Tất cả ngày</option>' + dayOptions.map((day) => `<option value="${escapeHtml(day)}">${escapeHtml(day)}</option>`).join('');
        dayFilterEl.value = dayOptions.includes(currentDayFilter) ? currentDayFilter : '';
    }

    const roomFilter = roomFilterEl ? roomFilterEl.value.trim() : '';
    const dayFilter = dayFilterEl ? dayFilterEl.value.trim() : '';
    const keywordFilter = keywordFilterEl ? keywordFilterEl.value.trim().toLowerCase() : '';

    const bookings = userBookings.filter((booking) => {
        const roomLabel = String(booking.roomNumberLabel || booking.roomNumber || '').trim();
        if (roomFilter && roomLabel !== roomFilter) {
            return false;
        }

        const slots = Array.isArray(booking.slots) ? booking.slots : [];
        if (dayFilter && !slots.some((slot) => String(slot.dayLabel || '').trim() === dayFilter)) {
            return false;
        }

        if (keywordFilter) {
            const purpose = String(booking.purpose || '').toLowerCase();
            const slotText = slots.map((slot) => `${String(slot.dayLabel || '')} ${String(slot.timeLabel || '')}`).join(' ').toLowerCase();
            if (!purpose.includes(keywordFilter) && !slotText.includes(keywordFilter)) {
                return false;
            }
        }

        return true;
    });

    if (bookings.length === 0) {
        listEl.innerHTML = '<div class="my-room-empty">Bạn chưa có ca đăng ký phòng học nào.</div>';
        return;
    }

    const sorted = [...bookings].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    listEl.innerHTML = sorted.map((booking) => {
        const roomTypeLabel = escapeHtml(booking.roomTypeLabel || booking.roomType || 'Phòng học');
        const roomNumberLabel = escapeHtml(booking.roomNumberLabel || booking.roomNumber || '---');
        const userNameLabel = escapeHtml(booking.userNameLabel || booking.createdBy || window.__username || 'Người dùng');
        const purpose = escapeHtml(booking.purpose || 'Không có ghi chú');
        const createdAt = booking.createdAt ? new Date(booking.createdAt) : null;
        const createdText = createdAt && !Number.isNaN(createdAt.getTime())
            ? createdAt.toLocaleString('vi-VN')
            : 'Không rõ thời gian';

        const slots = Array.isArray(booking.slots) ? booking.slots : [];
        const slotHtml = slots.length
            ? slots.map((slot) => {
                const dayLabel = escapeHtml(slot.dayLabel || '---');
                const timeLabel = escapeHtml(slot.timeLabel || '---');
                return `<li class="my-room-slot-item">${dayLabel} - ${timeLabel}</li>`;
            }).join('')
            : '<li class="my-room-slot-item">Không có dữ liệu ca</li>';

        return `
            <article class="my-room-card">
                <h3 class="my-room-card-title">${roomTypeLabel} - Phòng ${roomNumberLabel}</h3>
                <p class="my-room-card-meta"><strong>Người dùng:</strong> ${userNameLabel}</p>
                <p class="my-room-card-meta"><strong>Thời điểm đăng ký:</strong> ${escapeHtml(createdText)}</p>
                <p class="my-room-card-purpose"><strong>Mục đích:</strong> ${purpose}</p>
                <ul class="my-room-slot-list">${slotHtml}</ul>
                <div class="my-room-card-actions">
                    <button class="my-room-cancel-btn" data-booking-id="${escapeHtml(booking.bookingId || '')}">Hủy lịch</button>
                </div>
            </article>
        `;
    }).join('');
};

window.bindMyRoomBookingEvents = function () {
    if (myRoomUiBound) {
        return;
    }

    const section = document.getElementById('my-room-bookings-section');
    if (!section) {
        return;
    }

    myRoomUiBound = true;

    const roomFilterEl = document.getElementById('my-room-filter-room');
    const dayFilterEl = document.getElementById('my-room-filter-day');
    const keywordFilterEl = document.getElementById('my-room-filter-keyword');

    if (roomFilterEl) {
        roomFilterEl.addEventListener('change', () => window.renderMyRoomBookings());
    }

    if (dayFilterEl) {
        dayFilterEl.addEventListener('change', () => window.renderMyRoomBookings());
    }

    if (keywordFilterEl) {
        keywordFilterEl.addEventListener('input', debounce(() => window.renderMyRoomBookings(), 200));
    }

    section.addEventListener('click', async (event) => {
        const cancelBtn = event.target.closest('.my-room-cancel-btn');
        if (!cancelBtn) {
            return;
        }

        const bookingId = String(cancelBtn.getAttribute('data-booking-id') || '').trim();
        if (!bookingId) {
            return;
        }

        const accept = window.confirm('Bạn có chắc muốn hủy lịch phòng học này không?');
        if (!accept) {
            return;
        }

        try {
            const api = requireSebApi();
            await api.roomCancel({ bookingId });
            await window.renderMyRoomBookings();
        } catch (error) {
            alert(error.message || 'Không hủy được lịch phòng.');
        }
    });
};

document.addEventListener('DOMContentLoaded', async () => {
    const grid = document.getElementById('equipment-grid');
    if (grid) {
        renderSkeleton();

        try {
            await new Promise(resolve => setTimeout(resolve, 600));

            const isPersonalPage = window.location.pathname.includes('kho-ca-nhan.php');

            if (isPersonalPage) {
                if (!getAuthUsername()) {
                    allDevices = [];
                } else {
                    await loadPersonalDevices();
                }
                if (typeof window.renderBorrowHistorySidebar === 'function') {
                    await window.renderBorrowHistorySidebar();
                }
                if (typeof window.renderMyRoomBookings === 'function') {
                    if (typeof window.bindMyRoomBookingEvents === 'function') {
                        window.bindMyRoomBookingEvents();
                    }
                    await window.renderMyRoomBookings();
                }
            } else {
                if (!Array.isArray(window.DEVICES_DATA)) {
                    throw new Error('Thiếu dữ liệu DEVICES_DATA trong JS');
                }
                // Hide inactive devices for normal users (IDActive != 2). Admins should see all.
                try {
                    const role = (window.__user_role || '').toLowerCase();
                    if (role === 'admin' || role === 'superadmin' || role === 'administrator') {
                        allDevices = window.DEVICES_DATA;
                    } else {
                        allDevices = window.DEVICES_DATA.filter(d => {
                            const isActive = Number(d.idActive || 2) === 2;
                            const status = String(d.status || '').toLowerCase();
                            const isUnavailable = (status === 'unavailable' || status === 'het' || status === 'hết' || status === 'hết hàng');
                            return isActive && !isUnavailable;
                        });
                    }
                } catch (e) {
                    allDevices = window.DEVICES_DATA.filter(d => Number(d.idActive || 2) === 2);
                }
            }

            applyFilters();
            // Populate filter checkboxes dynamically from data so categories/subjects stay in sync
            try {
                populateFiltersFromData(allDevices);
            } catch (err) {
                console.warn('Không thể populate filters:', err);
            }
            setupFilters();
        } catch (err) {
            console.error('Lỗi khi tải danh sách thiết bị:', err);
            grid.innerHTML = '<div class="empty-state" style="border-color: #ef4444; color: #ef4444;"><h3>Lỗi tải dữ liệu</h3><p>Không tìm thấy dữ liệu thiết bị trong file JS.</p></div>';
        }
    }
});

// Hàm Bridge để tương tác chéo giúp mở Modal cho thiết bị
window.openDeviceModalById = function (id) {
    const device = allDevices.find(d => String(d.dbId || d.id) === String(id));
    if (device && typeof window.openDeviceModal === 'function') {
        window.openDeviceModal(device);
    } else {
        console.warn("Hệ thống cảnh báo: Modal Component chưa tải kịp hoặc mã ID bị sai!");
    }
};

// Populate category and subject filter containers from provided devices array
function populateFiltersFromData(devices) {
    if (!Array.isArray(devices)) return;

    const categoryContainer = document.getElementById('category-filters');
    const subjectContainer = document.getElementById('subject-filters');

    const categories = Array.from(new Set(devices.map(d => String(d.category || '').trim()).filter(Boolean)));
    const subjects = Array.from(new Set(devices.map(d => String(d.subject || '').trim()).filter(Boolean)));

    if (categoryContainer) {
        categoryContainer.innerHTML = categories.map(cat => `
            <div class="filter-group kho-filter-group">
                <label class="kho-filter-label">
                    <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="${escapeHtml(cat)}" data-type="category"> ${escapeHtml(cat)}
                </label>
            </div>
        `).join('');
    }

    if (subjectContainer) {
        subjectContainer.innerHTML = subjects.map(sub => `
            <div class="filter-group kho-filter-group">
                <label class="kho-filter-label">
                    <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="${escapeHtml(sub)}" data-type="subject"> ${escapeHtml(sub === 'Chung' ? 'Khác / Dùng chung' : sub)}
                </label>
            </div>
        `).join('');
    }
}
