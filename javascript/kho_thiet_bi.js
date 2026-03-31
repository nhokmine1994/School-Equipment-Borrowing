// javascript/kho_thiet_bi.js

let allDevices = []; 
let filteredDevices = [];
let currentPage = 1;
const itemsPerPage = 8; 

function renderCard(device) {
    const isUnavailable = device.status === 'unavailable';
    const statusText = isUnavailable ? 'Bảo trì / Hết hàng' : 'Sẵn sàng';
    const statusClass = isUnavailable ? 'unavailable' : 'available';

    // Disable button attributes
    const btnState = isUnavailable ? 'disabled' : '';
    const btnText = isUnavailable ? 'Chờ nhập kho' : 'Mượn';

    return `
        <div class="device-card" 
             data-name="${device.name}" 
             data-category="${device.category}" 
             data-subject="${device.subject || ''}"
             data-status="${device.status}">
            <div class="device-image" style="cursor: pointer;" onclick="window.openDeviceModalById('${device.id}')" title="Nhấn để xem cấu hình chi tiết">${device.image}</div>
            <div class="device-info">
                <h3 style="cursor: pointer; color: #2563eb; transition: color 0.2s;" onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'" onclick="window.openDeviceModalById('${device.id}')" title="Nhấn để xem cấu hình chi tiết">${device.name}</h3>
                <p><strong>Mã TB:</strong> ${device.id}</p>
                <p><strong>Môn học:</strong> ${device.subject || 'Chung'}</p>
                <p><strong>Trạng thái:</strong> <span class="status ${statusClass}">${statusText}</span></p>
            </div>
            <div class="device-actions">
                <button class="btn-borrow" ${btnState} onclick="handleBorrow('${device.id}')">${btnText}</button>
                <button class="btn-add" onclick="handleAdd('${device.id}')">Thêm</button>
            </div>
        </div>
    `;
}

function handleBorrow(id) {
    alert('Thao tác mượn thiết bị: ' + id);
}

function handleAdd(id) {
    alert('Thao tác thêm thiết bị vào phiếu báo: ' + id);
}

function renderPage(page) {
    const grid = document.getElementById('equipment-grid');
    if (!grid) return;
    
    const startIndex = (page - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filteredDevices.slice(startIndex, endIndex);
    
    if (pageData.length === 0) {
        // Giao diện Khôn tìm thấy kết quả
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

window.goToPage = function(page) {
    const totalPages = Math.ceil(filteredDevices.length / itemsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        renderPage(currentPage);
        renderPagination();
        
        const searchBar = document.getElementById('search-input');
        if(searchBar) {
            window.scrollTo({
                top: searchBar.offsetTop - 50,
                behavior: 'smooth'
            });
        }
    }
};

function debounce(func, wait) {
    let timeout;
    return function(...args) {
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
}

function applyFilters() {
    const checkedBoxes = Array.from(document.querySelectorAll('.filter-checkbox:checked'));
    const selectedCategories = checkedBoxes.filter(cb => cb.dataset.type === 'category').map(cb => cb.value);
    const selectedSubjects = checkedBoxes.filter(cb => cb.dataset.type === 'subject').map(cb => cb.value);

    const searchInput = document.getElementById('search-input');
    const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';

    filteredDevices = allDevices.filter(device => {
        const matchCategory = selectedCategories.length === 0 || selectedCategories.includes(device.category);
        const matchSubject = selectedSubjects.length === 0 || selectedSubjects.includes(device.subject);
        const matchSearch = searchTerm === '' || device.name.toLowerCase().includes(searchTerm);
        
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

document.addEventListener('DOMContentLoaded', async () => {
    const grid = document.getElementById('equipment-grid');
    if (grid) {
        // Chủ động render Skeleton skeleton giả lập Loading... ngay khi page mở
        renderSkeleton();
        
        try {
            // Giả lập đường truyền mạng delay nhẹ (600ms) để Admin có thể xem được hiệu ứng Skeleton skeleton
            await new Promise(resolve => setTimeout(resolve, 600));

            const res = await fetch('../data/devices.json');
            if (!res.ok) throw new Error('Mã lỗi HTTP: ' + res.status);
            allDevices = await res.json();
            
            applyFilters();
            setupFilters();
        } catch (err) {
            console.error('Lỗi khi tải danh sách thiết bị:', err);
            grid.innerHTML = '<div class="empty-state" style="border-color: #ef4444; color: #ef4444;"><h3>Lỗi kết nối JSON</h3><p>Server trả lỗi. Hãy kiểm tra kết nối Live Server hoặc Fetch Policy.</p></div>';
        }
    }
});

// Hàm Bridge để tương tác chéo giúp mở Modal cho thiết bị
window.openDeviceModalById = function(id) {
    const device = allDevices.find(d => d.id === id);
    if (device && typeof window.openModal === 'function') {
        window.openModal(device);
    } else {
        console.warn("Hệ thống cảnh báo: Modal Component chưa tải kịp hoặc mã ID bị sai!");
    }
};
