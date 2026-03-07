/* ==========================================
   School Equipment Management System - JavaScript
   ========================================== */

// ==========================================
// Sample Equipment Data
// ==========================================

const equipmentData = [
    {
        id: 1,
        name: "Máy chiếu",
        type: "Projector",
        status: "available", // 'available' hoặc 'borrowed'
        description: "Máy chiếu Epson E-500",
        image: "Images/Máy chiếu.jpg"
    },
    {
        id: 2,
        name: "Laptop",
        type: "Computer",
        status: "available",
        description: "Laptop Dell XPS 13",
        image: "Images/Laptop.jpg"
    },
    {
        id: 3,
        name: "Loa",
        type: "Speaker",
        status: "borrowed",
        description: "Loa JBL Professional",
        image: "Images/Loa.jpg"
    },
    {
        id: 4,
        name: "Micro",
        type: "Microphone",
        status: "available",
        description: "Micro Shure SM7B",
        image: "Images/Micro.jpg"
    },
    {
        id: 5,
        name: "Máy ảnh",
        type: "Camera",
        status: "available",
        description: "Máy ảnh Canon EOS R",
        image: "Images/Máy ảnh.jpg"
    },
    {
        id: 6,
        name: "Bảng trắng thông minh",
        type: "Smart Board",
        status: "borrowed",
        description: "Smart Board SMART Board 6000 Series",
        image: "Images/Bảng trắng thông minh.jpg"
    }
];

// Lịch sử mượn (demo)
let borrowHistory = [
    {
        equipment: "Máy chiếu",
        borrowDate: "2026-03-05",
        returnDate: "2026-03-06"
    },
    {
        equipment: "Loa",
        borrowDate: "2026-03-07",
        returnDate: "-"
    }
];

// ==========================================
// DOM Elements
// ==========================================

const navButtons = document.querySelectorAll('nav button');
const sections = document.querySelectorAll('section');
const equipmentList = document.getElementById('equipmentList');
const searchInput = document.getElementById('searchInput');
const historyList = document.getElementById('historyList');

// ==========================================
// Navigation Functions
// ==========================================

// Sự kiện khi click vào các nút menu
navButtons.forEach(button => {
    button.addEventListener('click', () => {
        const targetSection = button.getAttribute('data-section');
        switchSection(targetSection);
    });
});

/**
 * Chuyển đổi giữa các phần (Trang chủ, Danh sách thiết bị, Lịch sử mượn)
 * @param {string} sectionName - Tên phần cần hiển thị
 */
function switchSection(sectionName) {
    // Ẩn tất cả các phần
    sections.forEach(section => {
        section.classList.remove('active');
    });

    // Xóa active class từ tất cả nút
    navButtons.forEach(button => {
        button.classList.remove('active');
    });

    // Hiển thị phần được chọn
    const activeSection = document.getElementById(sectionName);
    if (activeSection) {
        activeSection.classList.add('active');
    }

    // Thêm active class vào nút được chọn
    const activeButton = document.querySelector(`button[data-section="${sectionName}"]`);
    if (activeButton) {
        activeButton.classList.add('active');
    }

    // Nếu chuyển sang trang danh sách thiết bị, render danh sách
    if (sectionName === 'equipment') {
        renderEquipment(equipmentData);
    }

    // Nếu chuyển sang trang lịch sử, render lịch sử
    if (sectionName === 'history') {
        renderHistory();
    }
}

// ==========================================
// Equipment Functions
// ==========================================

/**
 * Hiển thị danh sách thiết bị
 * @param {Array} equipment - Mảng thiết bị cần hiển thị
 */
function renderEquipment(equipment) {
    equipmentList.innerHTML = '';

    if (equipment.length === 0) {
        equipmentList.innerHTML = '<div class="empty-state"><p>Không tìm thấy thiết bị nào.</p></div>';
        return;
    }

    equipment.forEach(item => {
        const card = document.createElement('div');
        card.className = 'equipment-card';
        
        const isAvailable = item.status === 'available';
        const statusBadgeClass = isAvailable ? 'status-available' : 'status-borrowed';
        const statusText = isAvailable ? 'Available' : 'Borrowed';
        const buttonClass = isAvailable ? 'btn-borrow' : 'btn-return';
        const buttonText = isAvailable ? 'Mượn' : 'Trả';
        const buttonDisabled = !isAvailable;

        card.innerHTML = `
            <div class="equipment-image">
                <img src="${item.image}" alt="${item.name}" onerror="this.src='Images/Image.jpg'">
            </div>
            <h3>${item.name}</h3>
            <div class="equipment-info">
                <p><label>Loại:</label> ${item.type}</p>
                <p><label>Mô tả:</label> ${item.description}</p>
                <p><label>Trạng thái:</label></p>
                <span class="status-badge ${statusBadgeClass}">${statusText}</span>
            </div>
            <button class="btn ${buttonClass}" onclick="toggleEquipmentStatus(${item.id})" ${buttonDisabled && item.status !== 'available' ? '' : ''}>
                ${buttonText}
            </button>
        `;

        equipmentList.appendChild(card);
    });
}

/**
 * Chuyển đổi trạng thái mượn/trả của thiết bị
 * @param {number} equipmentId - ID của thiết bị
 */
function toggleEquipmentStatus(equipmentId) {
    const equipment = equipmentData.find(item => item.id === equipmentId);
    
    if (equipment) {
        if (equipment.status === 'available') {
            // Mượn thiết bị
            equipment.status = 'borrowed';
            addToBorrowHistory(equipment.name);
        } else {
            // Trả thiết bị
            equipment.status = 'available';
            updateReturnDate(equipment.name);
        }

        // Cập nhật hiển thị
        renderEquipment(equipmentData);
        
        // Hiển thị thông báo
        const message = equipment.status === 'borrowed' 
            ? `Bạn đã mượn ${equipment.name}`
            : `Bạn đã trả ${equipment.name}`;
        showNotification(message);
    }
}

// ==========================================
// Search Functions
// ==========================================

/**
 * Sự kiện khi người dùng nhập vào ô tìm kiếm
 */
searchInput.addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    
    // Lọc thiết bị theo từ khóa tìm kiếm
    const filteredEquipment = equipmentData.filter(item =>
        item.name.toLowerCase().includes(searchTerm) ||
        item.type.toLowerCase().includes(searchTerm) ||
        item.description.toLowerCase().includes(searchTerm)
    );

    renderEquipment(filteredEquipment);
});

// ==========================================
// History Functions
// ==========================================

/**
 * Thêm thiết bị vào lịch sử mượn
 * @param {string} equipmentName - Tên thiết bị
 */
function addToBorrowHistory(equipmentName) {
    const today = new Date().toISOString().split('T')[0];
    
    borrowHistory.push({
        equipment: equipmentName,
        borrowDate: today,
        returnDate: "-"
    });
}

/**
 * Cập nhật ngày trả của thiết bị
 * @param {string} equipmentName - Tên thiết bị
 */
function updateReturnDate(equipmentName) {
    const today = new Date().toISOString().split('T')[0];
    
    const historyItem = borrowHistory.find(item => item.equipment === equipmentName && item.returnDate === "-");
    if (historyItem) {
        historyItem.returnDate = today;
    }
}

/**
 * Hiển thị lịch sử mượn
 */
function renderHistory() {
    historyList.innerHTML = '';

    if (borrowHistory.length === 0) {
        historyList.innerHTML = '<div class="empty-history"><p>Chưa có lịch sử mượn thiết bị.</p></div>';
        return;
    }

    borrowHistory.forEach((item, index) => {
        const listItem = document.createElement('li');
        listItem.className = 'history-item';

        const returnDateText = item.returnDate === "-" ? "Đang mượn" : item.returnDate;
        const returnDateStyle = item.returnDate === "-" ? 'color: #ff6b6b;' : '';

        listItem.innerHTML = `
            <div class="history-item-info">
                <h4>${item.equipment}</h4>
                <p>Ngày mượn: ${item.borrowDate}</p>
                <p style="${returnDateStyle}">Ngày trả: ${returnDateText}</p>
            </div>
        `;

        historyList.appendChild(listItem);
    });
}

// ==========================================
// Notification Function
// ==========================================

/**
 * Hiển thị thông báo ngắn
 * @param {string} message - Tin nhắn cần hiển thị
 */
function showNotification(message) {
    // Tạo phần tử thông báo
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #0066cc;
        color: white;
        padding: 15px 25px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;

    // Thêm animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    
    if (!document.querySelector('style[data-notification]')) {
        style.setAttribute('data-notification', 'true');
        document.head.appendChild(style);
    }

    document.body.appendChild(notification);

    // Xóa thông báo sau 3 giây
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ==========================================
// Authentication Functions
// ==========================================

// Lưu trữ thông tin người dùng (demo)
let currentUser = null;
const registeredUsers = [
    { username: 'admin', password: 'admin123', fullName: 'Quản trị viên' }
];

/**
 * Mở modal đăng nhập/đăng ký
 */
function openAuthModal() {
    const modal = document.getElementById('authModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

/**
 * Đóng modal đăng nhập/đăng ký
 */
function closeAuthModal() {
    const modal = document.getElementById('authModal');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    clearAuthForms();
}

/**
 * Chuyển đổi giữa tab Đăng nhập và Đăng ký
 * @param {string} tabName - 'login' hoặc 'register'
 */
function switchAuthTab(tabName) {
    // Ẩn tất cả tabs
    document.getElementById('loginTab').classList.remove('active');
    document.getElementById('registerTab').classList.remove('active');

    // Xóa active class từ tất cả nút tabs
    document.querySelectorAll('.auth-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Hiển thị tab được chọn
    if (tabName === 'login') {
        document.getElementById('loginTab').classList.add('active');
        document.querySelectorAll('.auth-tab-btn')[0].classList.add('active');
    } else {
        document.getElementById('registerTab').classList.add('active');
        document.querySelectorAll('.auth-tab-btn')[1].classList.add('active');
    }

    // Xóa thông báo cũ
    const messageEl = document.getElementById('authMessage');
    messageEl.textContent = '';
    messageEl.className = 'auth-message';
}

/**
 * Xử lý form đăng nhập
 * @param {Event} event - Sự kiện submit form
 */
function handleLogin(event) {
    event.preventDefault();

    const username = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;

    // Kiểm tra thông tin (demo)
    const user = registeredUsers.find(u => u.username === username && u.password === password);

    if (user) {
        currentUser = {
            username: user.username,
            fullName: user.fullName
        };
        showAuthMessage('Đăng nhập thành công!', 'success');
        
        // Cập nhật nút login
        updateLoginButton();

        // Đóng modal sau 1.5 giây
        setTimeout(() => {
            closeAuthModal();
        }, 1500);
    } else {
        showAuthMessage('Tên đăng nhập hoặc mật khẩu không chính xác!', 'error');
    }
}

/**
 * Xử lý form đăng ký
 * @param {Event} event - Sự kiện submit form
 */
function handleRegister(event) {
    event.preventDefault();

    const fullName = document.getElementById('regFullName').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const username = document.getElementById('regUsername').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;

    // Kiểm tra điều kiện
    if (password !== confirmPassword) {
        showAuthMessage('Mật khẩu xác nhận không khớp!', 'error');
        return;
    }

    if (registeredUsers.some(u => u.username === username)) {
        showAuthMessage('Tên đăng nhập đã tồn tại!', 'error');
        return;
    }

    // Thêm người dùng mới (demo)
    registeredUsers.push({
        username: username,
        password: password,
        fullName: fullName
    });

    // Đăng nhập tự động
    currentUser = {
        username: username,
        fullName: fullName
    };

    showAuthMessage('Đăng ký thành công! Đang chuyển hướng...', 'success');
    updateLoginButton();

    // Đóng modal sau 1.5 giây
    setTimeout(() => {
        closeAuthModal();
    }, 1500);
}

/**
 * Hiển thị thông báo trong modal
 * @param {string} message - Thông báo
 * @param {string} type - 'success' hoặc 'error'
 */
function showAuthMessage(message, type) {
    const messageEl = document.getElementById('authMessage');
    messageEl.textContent = message;
    messageEl.className = `auth-message ${type}`;
}

/**
 * Xóa dữ liệu các form
 */
function clearAuthForms() {
    document.getElementById('loginEmail').value = '';
    document.getElementById('loginPassword').value = '';
    document.getElementById('rememberMe').checked = false;

    document.getElementById('regFullName').value = '';
    document.getElementById('regEmail').value = '';
    document.getElementById('regUsername').value = '';
    document.getElementById('regPassword').value = '';
    document.getElementById('regConfirmPassword').value = '';
    document.getElementById('agreeTerms').checked = false;

    const messageEl = document.getElementById('authMessage');
    messageEl.textContent = '';
    messageEl.className = 'auth-message';
}

/**
 * Cập nhật nút login sau khi đăng nhập
 */
function updateLoginButton() {
    const loginBtn = document.getElementById('loginBtn');

    if (currentUser) {
        loginBtn.textContent = `${currentUser.fullName} (Đăng xuất)`;
        loginBtn.onclick = logout;
    } else {
        loginBtn.textContent = 'Đăng nhập';
        loginBtn.onclick = openAuthModal;
    }
}

/**
 * Đăng xuất
 */
function logout() {
    currentUser = null;
    updateLoginButton();
    showNotification('Đã đăng xuất thành công');
}

/**
 * Đóng modal khi click ngoài modal
 */
window.addEventListener('click', (event) => {
    const modal = document.getElementById('authModal');
    if (event.target === modal) {
        closeAuthModal();
    }
});

// ==========================================
// Initialization
// ==========================================

/**
 * Khởi tạo ứng dụng khi trang load
 */
document.addEventListener('DOMContentLoaded', () => {
    // Hiển thị trang chủ mặc định
    switchSection('home');
    
    // Render danh sách thiết bị
    renderEquipment(equipmentData);

    // Setup nút login
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.addEventListener('click', openAuthModal);

    // Setup tab chuyển đổi
    document.querySelectorAll('.auth-tab-btn').forEach((btn, index) => {
        if (index === 0) {
            btn.addEventListener('click', () => switchAuthTab('login'));
        } else {
            btn.addEventListener('click', () => switchAuthTab('register'));
        }
    });
});
