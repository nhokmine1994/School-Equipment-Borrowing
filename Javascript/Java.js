document.addEventListener("DOMContentLoaded", () => {
    const PROTECTED_PAGE_PATTERN = /(kho-ca-nhan|dang-ky-phong-hoc)\.php$/i;

    function isPageSubRoute() {
        return /\/Page\//i.test(window.location.pathname);
    }

    function buildRoute(fileName) {
        return isPageSubRoute() ? fileName : `Page/${fileName}`;
    }

    function getLoginUrl(returnUrl) {
        const base = buildRoute("tai-khoan.php");
        if (!returnUrl) {
            return base;
        }
        return `${base}?return=${encodeURIComponent(returnUrl)}`;
    }

    function getLogoutUrl() {
        return buildRoute("logout.php");
    }

    // Use server-provided login state (injected by PHP) instead of demo localStorage
    function isLoggedIn() {
        return Boolean(window.__is_logged_in);
    }

    function getAuthDisplayName() {
        return String(window.__display_name || window.__full_name || window.__username || '').trim();
    }

    function getAuthRole() {
        return String(window.__user_role || window.__role || '').trim().toLowerCase();
    }

    function getAuthUsername() {
        return String(window.__username || '').trim();
    }

    function isProtectedCurrentPage() {
        return PROTECTED_PAGE_PATTERN.test(window.location.pathname);
    }

    function getRequireAuthMessage() {
        return "Vui lòng đăng nhập hoặc đăng ký để truy cập trang này.";
    }

    function syncProtectedPageVisibility() {
        const protectedCurrentPage = isProtectedCurrentPage();
        const shouldHideProtectedContent = protectedCurrentPage && !isLoggedIn();
        const currentPath = window.location.pathname;

        const authBanner = document.getElementById("auth-banner");
        if (authBanner) {
            authBanner.style.display = shouldHideProtectedContent ? "flex" : "none";
        }

        const hiddenTargets = [];

        if (/\/kho-ca-nhan\.php(?:$|[?#])/i.test(currentPath)) {
            const appWrapper = document.getElementById("app");
            if (appWrapper) {
                hiddenTargets.push(appWrapper);
            }
        }

        if (/\/dang-ky-phong-hoc\.php(?:$|[?#])/i.test(currentPath)) {
            const pageTitleBar = document.querySelector(".page-title-bar");
            const bookingContainer = document.querySelector(".booking-container");
            if (pageTitleBar) {
                hiddenTargets.push(pageTitleBar);
            }
            if (bookingContainer) {
                hiddenTargets.push(bookingContainer);
            }
        }

        hiddenTargets.forEach((element) => {
            element.classList.toggle("page-content-hidden", shouldHideProtectedContent);
        });
    }

    function shouldEnableAuthModal() {
        // Allow auth modal on all pages (previously disabled on kho_thiet_bi)
        return true;
    }

    function ensureAuthModal() {
        if (document.getElementById("authModal")) {
            return;
        }

        const authModal = document.createElement("div");
        authModal.id = "authModal";
        authModal.className = "modal-overlay";
        authModal.innerHTML = `
            <div class="modal-content">
                <span class="close-btn" id="closeModalBtn">&times;</span>

                <div id="loginFormContainer" class="auth-form-container">
                    <h2 class="auth-title">Đăng nhập</h2>
                    <form method="POST" action="${getLoginUrl(window.location.href)}">
                        <div class="input-group">
                            <label for="loginName">Tên đăng nhập</label>
                            <input type="text" id="loginName" name="TaiKhoan" placeholder="Nhập tên đăng nhập" required>
                        </div>
                        <div class="input-group">
                            <label for="loginPass">Mật khẩu</label>
                            <input type="password" id="loginPass" name="MatKhau" placeholder="Nhập mật khẩu" required>
                        </div>
                        <button type="submit" name="login" value="1" class="submit-btn auth-submit">Đăng nhập</button>
                    </form>
                    <div class="auth-switch">
                        Chưa có tài khoản? <a href="#" id="switchToRegister">Đăng ký ngay</a>
                    </div>
                </div>

                <div id="registerFormContainer" class="auth-form-container" style="display: none;">
                    <h2 class="auth-title">Đăng ký</h2>
                    <form id="dynamicRegisterForm" novalidate>
                        <div class="input-group">
                            <label for="regName">Tên đăng nhập</label>
                            <input type="text" id="regName" name="username" placeholder="Nhập tên đăng nhập" required>
                        </div>
                        <div class="input-group">
                            <label for="regEmail">Email</label>
                            <input type="email" id="regEmail" name="email" placeholder="Nhập email">
                        </div>
                        <div class="input-group">
                            <label for="regPhone">SĐT</label>
                            <input type="tel" id="regPhone" name="phone" placeholder="Nhập số điện thoại">
                        </div>
                                    <div class="input-group">
                                        <label for="regMonHoc">Môn học</label>
                                        <select id="regMonHoc" name="boMon">
                                            <option value="">-- Chọn môn học --</option>
                                        </select>
                                    </div>
                        <div class="input-group">
                            <label for="regPass">Mật khẩu</label>
                            <input type="password" id="regPass" name="password" placeholder="Tạo mật khẩu" required>
                        </div>
                        <div class="input-group">
                            <label for="regPassConfirm">Xác nhận mật khẩu</label>
                            <input type="password" id="regPassConfirm" placeholder="Nhập lại mật khẩu" required>
                        </div>
                        <button type="submit" class="submit-btn auth-submit">Đăng ký</button>
                    </form>
                    <div class="auth-switch">
                        Đã có tài khoản? <a href="#" id="switchToLogin">Đăng nhập ngay</a>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(authModal);

        // Populate subject select inside dynamic modal from server-provided list
        try {
            const subjects = Array.isArray(window.__subjects) ? window.__subjects : [];
            const select = document.getElementById('regMonHoc');
            if (select && subjects.length) {
                // Remove existing extra options (keep first placeholder)
                select.innerHTML = '<option value="">-- Chọn môn học --</option>' + subjects.map(s => '<option value="' + (s || '') + '">' + s + '</option>').join('');
            }
        } catch (e) {
            // silent
        }

        // If no subjects available via global var, try fetching from API
        try {
            const select = document.getElementById('regMonHoc');
            const hasOptions = select && select.querySelectorAll('option').length > 1;
            if (select && !hasOptions) {
                const apiPath = isPageSubRoute() ? '../api/seb_api.php?action=subjects' : 'api/seb_api.php?action=subjects';
                fetch(apiPath, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.ok && Array.isArray(data.subjects) && data.subjects.length) {
                            select.innerHTML = '<option value="">-- Chọn môn học --</option>' + data.subjects.map(s => '<option value="' + (s || '') + '">' + s + '</option>').join('');
                        }
                    }).catch(() => { /* ignore */ });
            }
        } catch (e) { /* ignore */ }
    }

    function ensureConfirmPopup() {
        if (document.getElementById("sebConfirmOverlay")) {
            return;
        }

        const overlay = document.createElement("div");
        overlay.id = "sebConfirmOverlay";
        overlay.className = "seb-confirm-overlay";
        overlay.setAttribute("aria-hidden", "true");
        overlay.innerHTML = `
            <div class="seb-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="sebConfirmTitle">
                <h3 id="sebConfirmTitle" class="seb-confirm-title">Xác nhận</h3>
                <p id="sebConfirmMessage" class="seb-confirm-message"></p>
                <div class="seb-confirm-actions">
                    <button type="button" id="sebConfirmCancel" class="seb-confirm-btn seb-confirm-cancel">Hủy</button>
                    <button type="button" id="sebConfirmAccept" class="seb-confirm-btn seb-confirm-accept">Đồng ý</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
    }

    function ensurePendingOverlay() {
        if (document.getElementById('sebPendingOverlay')) return;
        const overlay = document.createElement('div');
        overlay.id = 'sebPendingOverlay';
        overlay.className = 'seb-pending-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <div class="seb-pending-dialog" role="dialog" aria-modal="true">
                <h3 class="seb-pending-title">Yêu cầu đã được gửi</h3>
                <p class="seb-pending-message">Chúng tôi đã ghi nhận yêu cầu đăng ký của bạn. Vui lòng chờ quản trị viên duyệt. Bạn sẽ nhận được email khi được duyệt.</p>
                <div class="seb-pending-actions">
                    <button type="button" id="sebPendingClose" class="seb-pending-btn">Đóng</button>
                </div>
            </div>
        `;
        const style = document.createElement('style');
        style.id = 'sebPendingStyle';
        style.textContent = `
            .seb-pending-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:1200}
            .seb-pending-dialog{background:#fff;padding:20px 22px;border-radius:10px;max-width:420px;width:92%;box-shadow:0 10px 30px rgba(0,0,0,0.2);text-align:center}
            .seb-pending-title{margin:0 0 8px;font-size:18px}
            .seb-pending-message{color:#334155;margin:0 0 16px}
            .seb-pending-actions{display:flex;justify-content:center}
            .seb-pending-btn{background:#0b74de;color:#fff;border:none;padding:9px 14px;border-radius:8px;cursor:pointer}
        `;
        document.head.appendChild(style);
        document.body.appendChild(overlay);
        const closeBtn = document.getElementById('sebPendingClose');
        if (closeBtn) closeBtn.addEventListener('click', () => {
            // clear any pending auto-close timer
            try { const t = parseInt(overlay.dataset.pendingTimeout || '0', 10); if (t) clearTimeout(t); } catch (e) {}
            overlay.parentElement && overlay.parentElement.removeChild(overlay);
            const s = document.getElementById('sebPendingStyle'); if (s && s.parentElement) s.parentElement.removeChild(s);
        });
    }

    window.sebShowPendingApproval = function(message) {
        ensurePendingOverlay();
        const overlay = document.getElementById('sebPendingOverlay');
        if (!overlay) return;
        const msgEl = overlay.querySelector('.seb-pending-message');
        if (msgEl && typeof message === 'string' && message.trim() !== '') msgEl.textContent = message;
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
        // auto-close after 5 seconds
        try {
            const prev = parseInt(overlay.dataset.pendingTimeout || '0', 10);
            if (prev) clearTimeout(prev);
        } catch (e) {}
        const tid = setTimeout(() => {
            try { const ov = document.getElementById('sebPendingOverlay'); if (ov && ov.parentElement) ov.parentElement.removeChild(ov); } catch (e) {}
            try { const s = document.getElementById('sebPendingStyle'); if (s && s.parentElement) s.parentElement.removeChild(s); } catch (e) {}
        }, 5000);
        try { overlay.dataset.pendingTimeout = String(tid); } catch (e) {}
    };

    window.sebConfirm = function (message, title = "Xác nhận") {
        ensureConfirmPopup();

        const overlay = document.getElementById("sebConfirmOverlay");
        const titleEl = document.getElementById("sebConfirmTitle");
        const messageEl = document.getElementById("sebConfirmMessage");
        const acceptBtn = document.getElementById("sebConfirmAccept");
        const cancelBtn = document.getElementById("sebConfirmCancel");

        if (!overlay || !titleEl || !messageEl || !acceptBtn || !cancelBtn) {
            return Promise.resolve(window.confirm(message));
        }

        titleEl.textContent = title;
        messageEl.textContent = message;
        overlay.classList.add("active");
        overlay.setAttribute("aria-hidden", "false");

        return new Promise((resolve) => {
            const cleanup = () => {
                overlay.classList.remove("active");
                overlay.setAttribute("aria-hidden", "true");
                acceptBtn.removeEventListener("click", onAccept);
                cancelBtn.removeEventListener("click", onCancel);
                document.removeEventListener("keydown", onKeyDown);
            };

            const onAccept = () => {
                cleanup();
                resolve(true);
            };

            const onCancel = () => {
                cleanup();
                resolve(false);
            };

            const onKeyDown = (event) => {
                if (event.key === "Escape") {
                    onCancel();
                }
            };

            acceptBtn.addEventListener("click", onAccept);
            cancelBtn.addEventListener("click", onCancel);
            document.addEventListener("keydown", onKeyDown);
        });
    };

    function resolveAuthButtons() {
        const loginById = document.getElementById("loginBtn");
        const registerById = document.getElementById("registerBtn");

        if (loginById || registerById) {
            return {
                loginBtn: loginById,
                registerBtn: registerById,
            };
        }

        const headerButtons = document.querySelectorAll(".header-right .icon-btn");
        return {
            loginBtn: headerButtons[0] || null,
            registerBtn: headerButtons[1] || null,
        };
    }

    function updateHeaderAuthButtons(loginBtn, registerBtn) {
        if (loginBtn && loginBtn.classList.contains("icon-btn")) {
            loginBtn.classList.remove("avatar-btn", "avatar-main-btn");
            loginBtn.innerHTML = '<i class="far fa-user"></i>';
            loginBtn.style.display = "flex";
            loginBtn.setAttribute("title", "Đăng nhập");
            loginBtn.setAttribute("aria-label", "Đăng nhập");
            loginBtn.removeAttribute("aria-haspopup");
            loginBtn.setAttribute("aria-expanded", "false");
        }

        if (registerBtn && registerBtn.classList.contains("icon-btn")) {
            registerBtn.classList.remove("avatar-btn", "avatar-main-btn");
            registerBtn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
            registerBtn.style.display = "flex";
            registerBtn.setAttribute("title", "Đăng ký");
            registerBtn.setAttribute("aria-label", "Đăng ký");
        }
    }

    function getAvatarInitials(name) {
        const normalized = String(name || 'User')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^\p{L}\p{N}\s]/gu, ' ')
            .trim();

        if (!normalized) {
            return 'U';
        }

        const parts = normalized.split(/\s+/).filter(Boolean);
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }

        const first = parts[0].charAt(0);
        const last = parts[parts.length - 1].charAt(0);
        return `${first}${last}`.toUpperCase();
    }

    function makeAvatarPlaceholder(name) {
        const initials = getAvatarInitials(name || 'User');
        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" role="img" aria-label="Avatar ${initials}">
                <defs>
                    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#0D8ABC" />
                        <stop offset="100%" stop-color="#2563eb" />
                    </linearGradient>
                </defs>
                <rect width="120" height="120" rx="60" fill="url(#g)" />
                <text x="50%" y="54%" text-anchor="middle" dominant-baseline="middle"
                      fill="#ffffff" font-family="Inter, Arial, sans-serif" font-size="42" font-weight="700">${initials}</text>
            </svg>
        `;
        return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
    }

    function initScrollReveal() {
        const revealTargets = document.querySelectorAll(".section-wrapper, .search-container, .stats-row, .footer");
        if (!revealTargets.length) {
            return;
        }

        if (!("IntersectionObserver" in window)) {
            revealTargets.forEach((element) => {
                element.classList.add("is-visible");
            });
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-visible");
                        observer.unobserve(entry.target);
                    }
                });
            },
            {
                threshold: 0.12,
                rootMargin: "0px 0px -30px 0px",
            }
        );

        revealTargets.forEach((element) => {
            element.classList.add("reveal-on-scroll");
            observer.observe(element);
        });
    }

    initScrollReveal();

    ensureAuthModal();

    const { loginBtn, registerBtn } = resolveAuthButtons();
    let userProfileMenu = document.getElementById("userProfileMenu");
    let avatarBtn = document.getElementById("avatarBtn");
    let avatarDropdown = document.getElementById("avatarDropdown");
    let logoutBtn = document.getElementById("logoutBtn");
    let avatarImage = avatarBtn ? avatarBtn.querySelector("img") : null;

    function ensureAvatarMenu() {
        if (userProfileMenu) {
            return;
        }

        const headerRight = document.querySelector(".header-right");
        if (!headerRight) {
            return;
        }

        const menu = document.createElement("div");
        menu.className = "user-profile-menu";
        menu.id = "userProfileMenu";
        menu.style.display = "none";
        menu.style.position = "relative";

        const initialAvatarName = getAuthDisplayName() || getAuthUsername() || "User";

        const isAdmin = getAuthRole() === 'admin';

        menu.innerHTML = `
            <button class="icon-btn avatar-btn" id="avatarBtn" aria-label="Avatar người dùng">
                <img src="${makeAvatarPlaceholder(initialAvatarName)}" alt="Avatar ${initialAvatarName}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" />
            </button>
            <div class="dropdown-content" id="avatarDropdown" style="display: none; position: absolute; right: 0; top: 120%; min-width: 220px; background-color: #fff; box-shadow: 0 8px 24px rgba(0,0,0,0.15); border-radius: 8px; z-index: 100; overflow: hidden; border: 1px solid #e0e0e0; flex-direction: column; text-align: left;">
                <a href="#" class="dropdown-item"><i class="fas fa-id-card"></i> Thông tin cá nhân</a>
                <a href="#" class="dropdown-item"><i class="fas fa-history"></i> Lịch sử mượn/trả</a>
                <a href="#" class="dropdown-item"><i class="fas fa-cog"></i> Cài đặt</a>
                ${isAdmin ? '<a href="#" class="dropdown-item"><i class="fas fa-user-shield"></i> Chế độ quản trị viên</a>' : ''}
                <div style="border-top: 1px solid #eee; margin: 4px 0;"></div>
                <a href="${getLogoutUrl()}" class="dropdown-item" id="logoutBtn" style="color: #dc3545;"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
            </div>
        `;

        headerRight.appendChild(menu);

        userProfileMenu = menu;
        avatarBtn = menu.querySelector("#avatarBtn");
        avatarDropdown = menu.querySelector("#avatarDropdown");
        logoutBtn = menu.querySelector("#logoutBtn");
        avatarImage = avatarBtn ? avatarBtn.querySelector("img") : null;

        const adminModeLink = avatarDropdown
            ? avatarDropdown.querySelector('a.dropdown-item i.fa-user-shield')
            : null;
        if (adminModeLink && adminModeLink.parentElement) {
            adminModeLink.parentElement.setAttribute('href', buildRoute('admin_panel.php'));
        }
    }

    ensureAvatarMenu();

    // Ensure existing static dropdowns also point to the real admin route.
    if (avatarDropdown) {
        const adminModeIcon = avatarDropdown.querySelector('a.dropdown-item i.fa-user-shield');
        if (adminModeIcon && adminModeIcon.parentElement) {
            adminModeIcon.parentElement.setAttribute('href', buildRoute('admin_panel.php'));
        }
    }

    function closeAvatarDropdown() {
        if (!avatarDropdown) {
            return;
        }
        avatarDropdown.style.display = "none";
    }

    function updateHeaderAuthUi() {
        const loggedIn = isLoggedIn();
        updateHeaderAuthButtons(loginBtn, registerBtn);

        if (!userProfileMenu) {
            ensureAvatarMenu();
        }

        if (!userProfileMenu) return;

        if (loggedIn) {
            if (loginBtn) loginBtn.style.display = "none";
            if (registerBtn) registerBtn.style.display = "none";
            userProfileMenu.style.display = "block";

            if (avatarImage) {
                const name = getAuthDisplayName() || getAuthUsername() || 'User';
                avatarImage.src = makeAvatarPlaceholder(name);
                avatarImage.alt = `Avatar ${name}`;
            }

            bindLogoutButton();
        } else {
            if (loginBtn) loginBtn.style.display = "flex";
            if (registerBtn) registerBtn.style.display = "flex";
            userProfileMenu.style.display = "none";
            closeAvatarDropdown();
        }
    }

    updateHeaderAuthUi();

    const authModalEnabled = shouldEnableAuthModal();
    
    const authModal = document.getElementById("authModal");
    const closeModalBtn = document.getElementById("closeModalBtn");
    
    const loginFormContainer = document.getElementById("loginFormContainer");
    const registerFormContainer = document.getElementById("registerFormContainer");
    
    const switchToRegister = document.getElementById("switchToRegister");
    const switchToLogin = document.getElementById("switchToLogin");
    const loginForm = loginFormContainer ? loginFormContainer.querySelector("form") : null;
    const registerForm = registerFormContainer ? registerFormContainer.querySelector("form") : null;
    const closeButtonInitialDisplay = closeModalBtn ? closeModalBtn.style.display : "";
    let authLock = false;

    function setAuthLockState(isLocked) {
        authLock = isLocked;
        if (closeModalBtn) {
            closeModalBtn.style.display = isLocked ? "none" : closeButtonInitialDisplay;
        }
    }

    // Demo client-side auth removed — server-side login is used exclusively.

    function openModal(type) {
        if (!authModalEnabled || !authModal || !loginFormContainer || !registerFormContainer) {
            return;
        }

        // Clear any previous inline login error when opening modal
        try {
            var prevErr = document.getElementById('loginError');
            if (prevErr) {
                prevErr.style.display = 'none';
                prevErr.textContent = '';
            }
        } catch (e) {
            // ignore
        }

        // Always keep return URL so user goes back to the current page after login.
        if (loginForm) {
            loginForm.setAttribute("action", getLoginUrl(window.location.href));
        }

        authModal.style.display = "flex";
        authModal.style.pointerEvents = "auto";
        if (type === "login") {
            loginFormContainer.style.display = "block";
            registerFormContainer.style.display = "none";
        } else {
            loginFormContainer.style.display = "none";
            registerFormContainer.style.display = "block";
        }
    }

    window.sebOpenAuthModal = function (type = "login") {
        openModal(type === "register" ? "register" : "login");
    };

    function closeModal() {
        if (!authModal) {
            return;
        }

        if (authLock) {
            openModal("login");
            return;
        }

        authModal.style.display = "none";
        authModal.style.pointerEvents = "none";
        try {
            var prevErr = document.getElementById('loginError');
            if (prevErr) {
                prevErr.style.display = 'none';
                prevErr.textContent = '';
            }
        } catch (e) {
            // ignore
        }
    }

    window.addEventListener("seb:require-auth", (event) => {
        const detail = event && event.detail ? event.detail : {};
        const keepLocked = Boolean(detail.keepLocked);
        const message = typeof detail.message === "string" && detail.message.trim() !== ""
            ? detail.message
            : getRequireAuthMessage();

        setAuthLockState(keepLocked);
        syncProtectedPageVisibility();
        openModal("login");
        if (typeof sebShowMessage === 'function') sebShowMessage(message, 'info'); else alert(message);
    });

    if (loginBtn) {
        if (authModalEnabled) {
            loginBtn.addEventListener("click", () => openModal("login"));
        }
    }

    if (registerBtn) {
        if (authModalEnabled) {
            registerBtn.addEventListener("click", () => openModal("register"));
        }
    }

    window.addEventListener("seb:auth-changed", () => {
        updateHeaderAuthUi();
        syncProtectedPageVisibility();
    });

    window.addEventListener("focus", () => {
        updateHeaderAuthUi();
        syncProtectedPageVisibility();
    });

    if (avatarBtn && avatarDropdown) {
        avatarBtn.addEventListener("click", (event) => {
            event.stopPropagation();
            const nextDisplay = avatarDropdown.style.display === "flex" ? "none" : "flex";
            avatarDropdown.style.display = nextDisplay;
        });

        document.addEventListener("click", (event) => {
            if (!avatarDropdown || !userProfileMenu) {
                return;
            }

            if (!userProfileMenu.contains(event.target)) {
                closeAvatarDropdown();
            }
        });
    }

    function navigateToLogout() {
        window.location.assign(getLogoutUrl());
    }

    async function handleLogoutClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const accepted = typeof window.sebConfirm === 'function'
            ? await window.sebConfirm("Bạn có chắc muốn đăng xuất không?", "Xác nhận đăng xuất")
            : window.confirm("Bạn có chắc muốn đăng xuất không?");

        if (!accepted) {
            return;
        }

        closeAvatarDropdown();
        navigateToLogout();
    }

    function bindLogoutButton() {
        const btn = document.getElementById("logoutBtn");
        if (!btn || btn.dataset.sebLogoutBound === "1") {
            return btn;
        }
        btn.dataset.sebLogoutBound = "1";
        btn.addEventListener("click", handleLogoutClick);
        return btn;
    }

    bindLogoutButton();

    if (closeModalBtn) {
        closeModalBtn.addEventListener("click", closeModal);
    }

    if (authModalEnabled) {
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && authModal && authModal.style.display === "flex") {
                closeModal();
            }
        });
    }

    if (switchToRegister) {
        switchToRegister.addEventListener("click", (e) => {
            e.preventDefault();
            openModal("register");
        });
    }

    if (switchToLogin) {
        switchToLogin.addEventListener("click", (e) => {
            e.preventDefault();
            openModal("login");
        });
    }

    if (loginForm) {
        loginForm.addEventListener("submit", (e) => {
            // If the form has action + POST, allow normal submit to server
            const formAction = (loginForm.getAttribute('action') || '').trim();
            const formMethod = (loginForm.getAttribute('method') || '').toLowerCase();
            if (formAction !== '' && formMethod === 'post') {
                return; // browser will submit to server
            }

            // Otherwise redirect to server login page with return URL
            e.preventDefault();
            const current = window.location.href;
            window.location.href = getLoginUrl(current);
        });
    }

    if (registerForm) {
        registerForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            const username = (registerForm.querySelector('#regName')?.value || '').trim();
            const email = (registerForm.querySelector('#regEmail')?.value || '').trim();
            const phone = (registerForm.querySelector('#regPhone')?.value || '').trim();
            const boMon = (registerForm.querySelector('#regMonHoc')?.value || '').trim();
            const password = registerForm.querySelector('#regPass')?.value || '';
            const confirm = registerForm.querySelector('#regPassConfirm')?.value || '';

            if (!username || !password) {
                if (typeof sebShowMessage === 'function') sebShowMessage('Vui lòng nhập tài khoản và mật khẩu.', 'warn'); else alert('Vui lòng nhập tài khoản và mật khẩu.');
                return;
            }
            if (password !== confirm) {
                if (typeof sebShowMessage === 'function') sebShowMessage('Mật khẩu xác nhận không khớp.', 'warn'); else alert('Mật khẩu xác nhận không khớp.');
                return;
            }
            if (typeof window.sebApi === 'undefined') {
                if (typeof sebShowMessage === 'function') sebShowMessage('Chưa tải module kết nối máy chủ (seb_api.js).', 'error'); else alert('Chưa tải module kết nối máy chủ (seb_api.js).');
                return;
            }

            try {
                await window.sebApi.register({ username, email, phone, password, fullName: username, boMon });
                // Show confirmation and ensure modal is fully closed even if auth was previously locked
                if (typeof window.sebShowPendingApproval === 'function') {
                    window.sebShowPendingApproval('Yêu cầu đăng ký của bạn đã được gửi và đang chờ quản trị viên duyệt. Bạn sẽ được thông báo khi tài khoản được chấp nhận.');
                } else if (typeof sebShowMessage === 'function') {
                    sebShowMessage('Đã gửi yêu cầu đăng ký. Vui lòng chờ admin duyệt.', 'success');
                } else {
                    alert('Đã gửi yêu cầu đăng ký. Vui lòng chờ admin duyệt.');
                }
                if (typeof registerForm.reset === 'function') {
                    registerForm.reset();
                }
                try {
                    // clear any auth lock that might reopen the modal and sync UI
                    setAuthLockState(false);
                    syncProtectedPageVisibility();
                } catch (e) { /* ignore */ }
                // Force close modal
                if (typeof closeModal === 'function') closeModal();
            } catch (error) {
                if (typeof sebShowMessage === 'function') sebShowMessage(error.message || 'Đăng ký thất bại.', 'error'); else alert(error.message || 'Đăng ký thất bại.');
            }
        });
    }

    // Do not force redirect from navbar links to Tai-khoan.php.
    // Protected pages handle unauthenticated users locally by showing auth modal.

    setAuthLockState(authLock);
    syncProtectedPageVisibility();

    // If this page requires auth, show prompt but allow user to close popup.
    if (isProtectedCurrentPage() && !isLoggedIn()) {
        window.dispatchEvent(new CustomEvent("seb:require-auth", {
            detail: {
                keepLocked: false,
                message: getRequireAuthMessage(),
            },
        }));
    }

    // Hamburger removed; nav uses horizontal swipe behavior. No JS needed here.

    // --- NAV ACCESSIBILITY ENHANCEMENTS ---
    function enhanceNavAccessibility() {
        const nav = document.getElementById('mainNav');
        const navLinks = document.getElementById('navLinks');
        if (!nav || !navLinks) return;

        nav.setAttribute('role', 'navigation');
        if (!nav.getAttribute('aria-label')) nav.setAttribute('aria-label', 'Primary navigation');

        const links = Array.from(navLinks.querySelectorAll('.nav-tab'));
        if (!links.length) return;

        // Track last input modality so we only auto-center when keyboard navigation happens.
        let lastInteractionWasKeyboard = false;
        document.addEventListener('keydown', (ev) => {
            // treat Tab, Arrow keys, Home/End and printable keys as keyboard navigation
            const k = ev.key || '';
            if (k === 'Tab' || k.startsWith('Arrow') || k === 'Home' || k === 'End' || k.length === 1) {
                lastInteractionWasKeyboard = true;
            }
        }, { passive: true });
        // Pointer (mouse/touch) interaction => disable keyboard centering for the next focus
        document.addEventListener('pointerdown', () => { lastInteractionWasKeyboard = false; }, { passive: true });

        links.forEach((a, idx) => {
            // keep native link behavior but add ARIA markers
            if (a.classList.contains('active')) {
                a.setAttribute('aria-current', 'page');
            } else {
                a.removeAttribute('aria-current');
            }

            // ensure focusable
            a.tabIndex = 0;

            a.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    const next = links[(idx + 1) % links.length];
                    next.focus();
                    next.scrollIntoView({ inline: 'center', behavior: 'smooth' });
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    const prev = links[(idx - 1 + links.length) % links.length];
                    prev.focus();
                    prev.scrollIntoView({ inline: 'center', behavior: 'smooth' });
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    links[0].focus();
                    links[0].scrollIntoView({ inline: 'center', behavior: 'smooth' });
                } else if (e.key === 'End') {
                    e.preventDefault();
                    links[links.length - 1].focus();
                    links[links.length - 1].scrollIntoView({ inline: 'center', behavior: 'smooth' });
                }
            });

            a.addEventListener('focus', () => {
                // Auto-center only when keyboard navigation was used to reach the element.
                if (lastInteractionWasKeyboard) {
                    try { a.scrollIntoView({ inline: 'center', behavior: 'smooth' }); } catch (e) { /* ignore */ }
                }
            });
        });

        // update aria-current when navigation changes (server-side pages will reload, but support SPA-like behavior)
        navLinks.addEventListener('click', (ev) => {
            const t = ev.target.closest('.nav-tab');
            if (!t) return;
            links.forEach(l => l.removeAttribute('aria-current'));
            t.setAttribute('aria-current', 'page');
        });
    }

    try { enhanceNavAccessibility(); } catch (e) { /* fail silently */ }

    // --- SIDEBAR COLLAPSIBLE LOGIC (mobile) ---
    const collapsibleTitles = document.querySelectorAll(".collapsible-title");
    if (collapsibleTitles.length) {
        const isMobile = () => window.matchMedia("(max-width: 768px)").matches;

        collapsibleTitles.forEach((title, index) => {
            const content = title.nextElementSibling;
            if (!content || !content.classList.contains("collapsible-content")) {
                return;
            }

            title.setAttribute("role", "button");
            title.setAttribute("tabindex", "0");

            const setExpanded = (expanded) => {
                title.classList.toggle("active", expanded);
                title.setAttribute("aria-expanded", expanded ? "true" : "false");
            };

            // Mặc định mở block đầu tiên trên mobile để người dùng dễ nhận biết.
            setExpanded(isMobile() && index === 0);

            const onToggle = () => {
                if (!isMobile()) {
                    return;
                }
                setExpanded(!title.classList.contains("active"));
            };

            title.addEventListener("click", onToggle);
            title.addEventListener("keydown", (event) => {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    onToggle();
                }
            });
        });

        window.addEventListener("resize", () => {
            if (!isMobile()) {
                collapsibleTitles.forEach((title) => {
                    title.classList.remove("active");
                    title.setAttribute("aria-expanded", "false");
                });
            }
        });
    }
});
