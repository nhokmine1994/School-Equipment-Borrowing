// javascript/device_modal.js

function createModalImagePlaceholder(text) {
    const placeholder = document.createElement('div');
    placeholder.style.padding = '100px';
    placeholder.style.textAlign = 'center';
    placeholder.style.color = '#64748b';
    placeholder.textContent = text;
    return placeholder;
}

function renderSafeModalImage(container, imageHtml) {
    if (!container) {
        return;
    }

    container.textContent = '';

    if (typeof imageHtml !== 'string' || imageHtml.trim() === '') {
        container.appendChild(createModalImagePlaceholder('Không có ảnh'));
        return;
    }

    const template = document.createElement('template');
    template.innerHTML = imageHtml.trim();
    const img = template.content.querySelector('img');

    if (!img) {
        container.appendChild(createModalImagePlaceholder('Không có ảnh'));
        return;
    }

    const src = (img.getAttribute('src') || '').trim();
    const isTrustedSrc = /^(https?:\/\/|\/|\.\.?\/)/i.test(src);
    if (!isTrustedSrc) {
        container.appendChild(createModalImagePlaceholder('Ảnh không hợp lệ'));
        return;
    }

    const safeImg = document.createElement('img');
    safeImg.src = src;
    safeImg.alt = img.getAttribute('alt') || 'Thiết bị';
    safeImg.style.width = '100%';
    safeImg.style.height = '100%';
    safeImg.style.objectFit = 'cover';
    safeImg.style.display = 'block';
    container.appendChild(safeImg);
}

function renderSafeAccessories(container, accessories) {
    if (!container) {
        return;
    }

    container.textContent = '';

    const safeAccessories = Array.isArray(accessories) && accessories.length
        ? accessories
        : ['Dây nguồn, hướng dẫn sử dụng', 'Hộp chống sốc / Cáp tín hiệu'];

    const list = document.createElement('ul');
    safeAccessories.forEach((item) => {
        const li = document.createElement('li');
        li.textContent = String(item ?? '');
        list.appendChild(li);
    });

    container.appendChild(list);
}

// Clear any unexpected highlights/marks that some browsers or extensions
// may have injected into the modal HTML (e.g. find-in-page <mark> nodes
// or inline background styles). This forcibly resets background to transparent.
function clearModalHighlights(container) {
    if (!container) return;
    // Remove <mark> background
    container.querySelectorAll('mark').forEach(m => {
        try { m.style.background = 'transparent'; m.style.color = 'inherit'; } catch (e) {}
    });
    // Clear inline background styles on spans/labels
    container.querySelectorAll('[style]').forEach(el => {
        const s = el.getAttribute('style') || '';
        if (/background|background-color|background-image/i.test(s)) {
            try { el.style.background = 'transparent'; el.style.backgroundColor = 'transparent'; } catch (e) {}
        }
    });
    // Remove common highlight classes
    container.querySelectorAll('.highlight, .selected').forEach(el => {
        el.classList.remove('highlight'); el.classList.remove('selected');
        try { el.style.background = 'transparent'; } catch (e) {}
    });
    // Specifically target and clear schedule element
    const scheduleEl = container.querySelector('#modal-schedule');
    if (scheduleEl) {
        try {
            scheduleEl.style.background = 'transparent !important';
            scheduleEl.style.backgroundColor = 'transparent';
        } catch (e) {}
    }
    const scheduleLabel = container.querySelector('[for="modal-schedule"], .modal-form-group:has(#modal-schedule)');
    if (scheduleLabel) {
        try {
            scheduleLabel.style.background = 'transparent !important';
            scheduleLabel.style.backgroundColor = 'transparent';
        } catch (e) {}
    }
}

// Tạo và nhúng HTML Modal toàn cục vào Document Body
function initDeviceModal() {
    if (document.getElementById('device-modal-overlay')) return;

    const overlay = document.createElement('div');
    overlay.id = 'device-modal-overlay';
    overlay.className = 'device-modal-overlay';

    overlay.innerHTML = `
        <div class="device-modal" id="device-modal">
                <div class="modal-header">
                <h2 id="modal-title">Chi tiết thiết bị</h2>
                <button class="close-modal" onclick="window.closeDeviceModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-left">
                    <div class="modal-image-container" id="modal-image">
                        <!-- Image render here -->
                    </div>
                </div>
                <div class="modal-right">
                    <div class="modal-info-section">
                        <h3>Thông tin chung</h3>
                        <div class="modal-detail-row">
                            <span class="modal-detail-label">Mã thiết bị:</span>
                            <span class="modal-detail-value" id="modal-code">...</span>
                        </div>
                        <div class="modal-detail-row">
                            <span class="modal-detail-label">Danh mục:</span>
                            <span class="modal-detail-value" id="modal-category">...</span>
                        </div>
                        <div class="modal-detail-row">
                            <span class="modal-detail-label">Tình trạng:</span>
                            <span class="modal-detail-value" id="modal-status">...</span>
                        </div>
                        <div class="modal-detail-row">
                            <span class="modal-detail-label">Nhà kho:</span>
                            <span class="modal-detail-value" id="modal-stock">...</span>
                        </div>
                    </div>
                    
                    <div class="modal-info-section">
                        <h3>Mô tả chi tiết</h3>
                        <p class="modal-desc" id="modal-desc">...</p>
                    </div>

                    <div class="modal-info-section">
                        <h3>Phụ kiện đồng bộ</h3>
                        <div class="modal-accessories" id="modal-accessories">
                            <ul><li>Không có số liệu</li></ul>
                        </div>
                    </div>

                    <div class="modal-info-section" style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 0;">
                        <h3 style="border: none; margin-bottom: 15px; padding: 0;">Khởi tạo phiếu mượn</h3>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                            <div class="modal-form-group" style="flex: 1 1 180px; min-width:160px;">
                                <label for="modal-date">Ngày nhận mong đợi</label>
                                <input type="date" id="modal-date" required>
                            </div>
                            <div class="modal-form-group" style="flex: 1 1 180px; min-width:160px;">
                                <label for="modal-return-date">Ngày trả</label>
                                <input type="date" id="modal-return-date" required>
                            </div>
                            <div class="modal-form-group" style="flex: 0 0 110px; min-width:90px;">
                                <label for="modal-qty">Số lượng</label>
                                <input style="width:100%;" type="number" id="modal-qty" min="1" value="1" required>
                            </div>
                        </div>
                        <div class="modal-form-group" style="margin-bottom: 0; margin-top: 10px; background: transparent !important;">
                            <label style="background: transparent !important;">Lịch mượn gần đây (hệ thống):</label>
                            <span style="font-size: 0.85rem; color: #10b981; font-weight: 500; background: transparent !important;" id="modal-schedule">Không có ai đặt trùng lịch hôm nay!</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn-cancel" onclick="window.closeDeviceModal()">Trở lại</button>
                <button class="modal-btn-confirm" id="modal-btn-submit" onclick="submitModalBorrow()">Đăng ký mượn</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);

    clearModalHighlights(overlay);

    // Chỉ cho phép đóng bằng ESC hoặc nút đóng trong modal.
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const activeOverlay = document.getElementById('device-modal-overlay');
        if (activeOverlay && activeOverlay.classList.contains('active')) {
            if (typeof window.closeDeviceModal === 'function') {
                window.closeDeviceModal();
            }
        }
    });

    // Điền ngày hiện tại mặc định cho ngày nhận
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('modal-date');
    if (dateInput) dateInput.value = today;
    
    // Điền ngày trả mặc định (7 ngày sau)
    const returnDate = new Date();
    returnDate.setDate(returnDate.getDate() + 7);
    const returnDateStr = returnDate.toISOString().split('T')[0];
    const returnDateInput = document.getElementById('modal-return-date');
    if (returnDateInput) returnDateInput.value = returnDateStr;
}

// Global expose function (Sẵn sàng phục vụ nhiều trang)
window.openDeviceModal = function(device) {
    initDeviceModal(); // Lazy load UI only when needed

    document.getElementById('modal-title').textContent = device.name;
    document.getElementById('modal-code').textContent = device.id;
    document.getElementById('modal-category').textContent = device.category + (device.subject && device.subject !== 'Chung' ? ` - Môn ${device.subject}` : '');
    
    // Status color
    const statusEl = document.getElementById('modal-status');
    const isAvail = device.status === 'available';
    statusEl.textContent = isAvail ? 'Sẵn sàng' : 'Không có sẵn';
    statusEl.style.color = isAvail ? '#10b981' : '#ef4444';
    
    // Số lượng phải phản ánh đúng dữ liệu từ DB, không tự sinh ngẫu nhiên
    const stock = Number.isFinite(Number(device.quantity)) ? Number(device.quantity) : 0;
    document.getElementById('modal-stock').textContent = isAvail ? `Còn ${stock} cái` : 'Đã xuất hết (0 cái)';
    
    renderSafeModalImage(document.getElementById('modal-image'), device.image);

    document.getElementById('modal-desc').textContent = device.description || `Đây là thiết bị [${device.name}] được cung cấp mặc định trong kho. Đảm bảo hỗ trợ giáo viên và học sinh đáp ứng nhu cầu sử dụng thực tiễn công nghệ, an toàn và dễ sử dụng tại trường học.`;

    renderSafeAccessories(document.getElementById('modal-accessories'), device.accessories);

    // Validate Input form
    const qtyInput = document.getElementById('modal-qty');
    qtyInput.max = isAvail ? Math.max(stock, 1) : 0;
    qtyInput.value = isAvail ? 1 : 0;
    qtyInput.disabled = !isAvail;
    
    const submitBtn = document.getElementById('modal-btn-submit');
    submitBtn.disabled = !isAvail;
    submitBtn.textContent = isAvail ? 'Đăng ký mượn' : 'Hàng chưa về kho';
    submitBtn.setAttribute('data-device-id', String(device.dbId || device.id || ''));

    // Kích hoạt bật hiển thị Modal
    const overlay = document.getElementById('device-modal-overlay');
    // Ensure any injected highlights are cleared right before showing
    try { clearModalHighlights(overlay); } catch (e) {}
    overlay.classList.add('active');

    // Khóa cuộn trang nền
    document.body.style.overflow = 'hidden';
};

window.closeDeviceModal = function() {
    const overlay = document.getElementById('device-modal-overlay');
    if (overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
};

window.submitModalBorrow = function() {
    const deviceId = document.getElementById('modal-btn-submit').getAttribute('data-device-id');
    const qty = document.getElementById('modal-qty').value;
    const date = document.getElementById('modal-date').value;
    const returnDate = document.getElementById('modal-return-date').value;
    
    if (qty < 1) {
        if (typeof sebShowMessage === 'function') sebShowMessage('Vui lòng chọn số lượng hợp lệ!', 'warn'); else alert('Vui lòng chọn số lượng hợp lệ!');
        return;
    }
    
    if (!returnDate) {
        if (typeof sebShowMessage === 'function') sebShowMessage('Vui lòng chọn ngày trả!', 'warn'); else alert('Vui lòng chọn ngày trả!');
        return;
    }
    
    if (new Date(returnDate) <= new Date(date)) {
        if (typeof sebShowMessage === 'function') sebShowMessage('Ngày trả phải sau ngày nhận!', 'warn'); else alert('Ngày trả phải sau ngày nhận!');
        return;
    }
    
    // Send borrow request to API
    const payload = {
        maThietBi: deviceId,
        soLuong: qty,
        hanTra: returnDate
    };
    
    fetch('../api/seb_api.php?action=borrow_request', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            const msg = `Đã gửi yêu cầu mượn. Số phiếu: ${data.borrowId}. Quản trị viên sẽ duyệt.`;
            if (typeof sebShowMessage === 'function') sebShowMessage(msg, 'success'); else alert(`✅ ${ data.message }\n\nSố phiếu: ${data.borrowId}\n\nQuản trị viên sẽ duyệt yêu cầu của bạn trong thời gian sớm nhất.`);
            if (typeof window.closeDeviceModal === 'function') {
                window.closeDeviceModal();
            }
        } else {
            if (typeof sebShowMessage === 'function') sebShowMessage(data.error || 'Không gửi được yêu cầu.', 'error'); else alert(`❌ Lỗi: ${data.error || 'Không gửi được yêu cầu.'}`);
        }
    })
    .catch(err => {
        if (typeof sebShowMessage === 'function') sebShowMessage('Lỗi kết nối: ' + (err.message || ''), 'error'); else alert(`❌ Lỗi kết nối: ${err.message}`);
    });
};
