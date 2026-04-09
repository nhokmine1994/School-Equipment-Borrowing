document.addEventListener("DOMContentLoaded", () => {
    const AUTH_STORAGE_KEY = "seb_demo_auth_user";
    const PROTECTED_PAGE_PATTERN = /kho-ca-nhan\.html$/i;
    const PROTECTED_LINK_PATTERN = /kho-ca-nhan\.html(?:$|[?#])/i;
    let pendingRedirect = "";

    function getAuthUser() {
        try {
            const raw = localStorage.getItem(AUTH_STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            console.warn("Không thể đọc trạng thái đăng nhập demo:", error);
            return null;
        }
    }

    function isLoggedIn() {
        const authUser = getAuthUser();
        return Boolean(authUser && authUser.username);
    }

    function setAuthUser(username) {
        const authUser = {
            username,
            loginAt: new Date().toISOString(),
        };
        localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(authUser));
    }

    function isProtectedCurrentPage() {
        return PROTECTED_PAGE_PATTERN.test(window.location.pathname);
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
                    <form>
                        <div class="input-group">
                            <label for="loginName">Tên đăng nhập</label>
                            <input type="text" id="loginName" placeholder="Nhập tên đăng nhập">
                        </div>
                        <div class="input-group">
                            <label for="loginPass">Mật khẩu</label>
                            <input type="password" id="loginPass" placeholder="Nhập mật khẩu">
                        </div>
                        <button type="submit" class="submit-btn auth-submit">Đăng nhập</button>
                    </form>
                    <div class="auth-switch">
                        Chưa có tài khoản? <a href="#" id="switchToRegister">Đăng ký ngay</a>
                    </div>
                </div>

                <div id="registerFormContainer" class="auth-form-container" style="display: none;">
                    <h2 class="auth-title">Đăng ký</h2>
                    <form>
                        <div class="input-group">
                            <label for="regName">Tên đăng nhập</label>
                            <input type="text" id="regName" placeholder="Nhập tên đăng nhập">
                        </div>
                        <div class="input-group">
                            <label for="regEmail">Email</label>
                            <input type="email" id="regEmail" placeholder="Nhập email">
                        </div>
                        <div class="input-group">
                            <label for="regPass">Mật khẩu</label>
                            <input type="password" id="regPass" placeholder="Tạo mật khẩu">
                        </div>
                        <div class="input-group">
                            <label for="regPassConfirm">Xác nhận mật khẩu</label>
                            <input type="password" id="regPassConfirm" placeholder="Nhập lại mật khẩu">
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
                overlay.removeEventListener("click", onOverlayClick);
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

            const onOverlayClick = (event) => {
                if (event.target === overlay) {
                    onCancel();
                }
            };

            const onKeyDown = (event) => {
                if (event.key === "Escape") {
                    onCancel();
                }
            };

            acceptBtn.addEventListener("click", onAccept);
            cancelBtn.addEventListener("click", onCancel);
            overlay.addEventListener("click", onOverlayClick);
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

    function makeAvatarPlaceholder(username) {
        const label = encodeURIComponent(String(username || "User").trim() || "User");
        return `https://ui-avatars.com/api/?name=${label}&background=0D8ABC&color=fff`;
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

        menu.innerHTML = `
            <button class="icon-btn avatar-btn" id="avatarBtn" aria-label="Avatar người dùng">
                <img src="${makeAvatarPlaceholder("User")}" alt="Avatar User" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" />
            </button>
            <div class="dropdown-content" id="avatarDropdown" style="display: none; position: absolute; right: 0; top: 120%; min-width: 220px; background-color: #fff; box-shadow: 0 8px 24px rgba(0,0,0,0.15); border-radius: 8px; z-index: 100; overflow: hidden; border: 1px solid #e0e0e0; flex-direction: column; text-align: left;">
                <a href="#" class="dropdown-item"><i class="fas fa-id-card"></i> Thông tin cá nhân</a>
                <a href="#" class="dropdown-item"><i class="fas fa-history"></i> Lịch sử mượn/trả</a>
                <a href="#" class="dropdown-item"><i class="fas fa-cog"></i> Cài đặt</a>
                <a href="#" class="dropdown-item"><i class="fas fa-user-shield"></i> Chế độ quản trị viên</a>
                <div style="border-top: 1px solid #eee; margin: 4px 0;"></div>
                <a href="#" class="dropdown-item" id="logoutBtn" style="color: #dc3545;"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
            </div>
        `;

        headerRight.appendChild(menu);

        userProfileMenu = menu;
        avatarBtn = menu.querySelector("#avatarBtn");
        avatarDropdown = menu.querySelector("#avatarDropdown");
        logoutBtn = menu.querySelector("#logoutBtn");
        avatarImage = avatarBtn ? avatarBtn.querySelector("img") : null;
    }

    ensureAvatarMenu();

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

            const authUser = getAuthUser();
            if (avatarImage) {
                avatarImage.src = makeAvatarPlaceholder(authUser && authUser.username ? authUser.username : "User");
                avatarImage.alt = `Avatar ${authUser && authUser.username ? authUser.username : "User"}`;
            }
        } else {
            if (loginBtn) loginBtn.style.display = "flex";
            if (registerBtn) registerBtn.style.display = "flex";
            userProfileMenu.style.display = "none";
            closeAvatarDropdown();
        }
    }

    updateHeaderAuthUi();
    
    const authModal = document.getElementById("authModal");
    const closeModalBtn = document.getElementById("closeModalBtn");
    
    const loginFormContainer = document.getElementById("loginFormContainer");
    const registerFormContainer = document.getElementById("registerFormContainer");
    
    const switchToRegister = document.getElementById("switchToRegister");
    const switchToLogin = document.getElementById("switchToLogin");
    const loginForm = loginFormContainer ? loginFormContainer.querySelector("form") : null;
    const registerForm = registerFormContainer ? registerFormContainer.querySelector("form") : null;
    const closeButtonInitialDisplay = closeModalBtn ? closeModalBtn.style.display : "";
    let authLock = isProtectedCurrentPage() && !isLoggedIn();

    function setAuthLockState(isLocked) {
        authLock = isLocked;
        if (closeModalBtn) {
            closeModalBtn.style.display = isLocked ? "none" : closeButtonInitialDisplay;
        }
    }

    function ensureDemoHints() {
        if (loginFormContainer && !loginFormContainer.querySelector(".demo-auth-hint")) {
            const hint = document.createElement("p");
            hint.className = "demo-auth-hint";
            hint.style.fontSize = "13px";
            hint.style.color = "#64748b";
            hint.style.marginTop = "8px";
            hint.textContent = "Demo: nhập tài khoản và mật khẩu bất kỳ để sử dụng.";
            loginFormContainer.appendChild(hint);
        }

        if (registerFormContainer && !registerFormContainer.querySelector(".demo-auth-hint")) {
            const hint = document.createElement("p");
            hint.className = "demo-auth-hint";
            hint.style.fontSize = "13px";
            hint.style.color = "#64748b";
            hint.style.marginTop = "8px";
            hint.textContent = "Demo: đăng ký nhanh bằng thông tin bất kỳ rồi dùng ngay.";
            registerFormContainer.appendChild(hint);
        }
    }

    function finishDemoAuth(username) {
        setAuthUser(username);
        updateHeaderAuthUi();
        window.dispatchEvent(new CustomEvent("seb:auth-changed"));
        setAuthLockState(false);
        closeModal();

        if (pendingRedirect) {
            window.location.href = pendingRedirect;
            return;
        }

        if (isProtectedCurrentPage()) {
            window.location.reload();
        }
    }

    function openModal(type) {
        if (!authModal || !loginFormContainer || !registerFormContainer) {
            return;
        }

        authModal.style.display = "flex";
        if (type === "login") {
            loginFormContainer.style.display = "block";
            registerFormContainer.style.display = "none";
        } else {
            loginFormContainer.style.display = "none";
            registerFormContainer.style.display = "block";
        }
    }

    function closeModal() {
        if (!authModal) {
            return;
        }

        if (authLock) {
            openModal("login");
            return;
        }

        authModal.style.display = "none";
    }

    window.addEventListener("seb:require-auth", () => {
        setAuthLockState(false);
        openModal("login");
        alert("Vui lòng đăng nhập hoặc đăng ký để gửi yêu cầu đăng ký phòng.");
    });

    if (loginBtn) {
        loginBtn.addEventListener("click", () => openModal("login"));
    }

    if (registerBtn) {
        registerBtn.addEventListener("click", () => openModal("register"));
    }

    window.addEventListener("seb:auth-changed", () => {
        updateHeaderAuthUi();
    });

    window.addEventListener("storage", (event) => {
        if (event.key === AUTH_STORAGE_KEY) {
            updateHeaderAuthUi();
        }
    });

    window.addEventListener("focus", () => {
        updateHeaderAuthUi();
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

    if (logoutBtn) {
        logoutBtn.addEventListener("click", async (event) => {
            event.preventDefault();
            const accepted = await window.sebConfirm("Bạn có chắc muốn đăng xuất không?", "Xác nhận đăng xuất");
            if (!accepted) {
                return;
            }
            localStorage.removeItem(AUTH_STORAGE_KEY);
            closeAvatarDropdown();
            updateHeaderAuthUi();
            window.dispatchEvent(new CustomEvent("seb:auth-changed"));
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener("click", closeModal);
    }

    // click outside to close
    window.addEventListener("click", (e) => {
        if (e.target === authModal) {
            closeModal();
        }
    });

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
            e.preventDefault();

            const usernameInput = document.getElementById("loginName");
            const passwordInput = document.getElementById("loginPass");
            const username = usernameInput ? usernameInput.value.trim() : "";
            const password = passwordInput ? passwordInput.value.trim() : "";

            if (!username || !password) {
                alert("Vui lòng nhập tài khoản và mật khẩu bất kỳ để vào demo.");
                return;
            }

            finishDemoAuth(username);
            alert("Đăng nhập demo thành công.");
        });
    }

    if (registerForm) {
        registerForm.addEventListener("submit", (e) => {
            e.preventDefault();

            const usernameInput = document.getElementById("regName");
            const passwordInput = document.getElementById("regPass");
            const username = usernameInput ? usernameInput.value.trim() : "";
            const password = passwordInput ? passwordInput.value.trim() : "";

            if (!username || !password) {
                alert("Vui lòng nhập tài khoản và mật khẩu bất kỳ để đăng ký demo.");
                return;
            }

            finishDemoAuth(username);
            alert("Đăng ký demo thành công.");
        });
    }

    const protectedLinks = document.querySelectorAll("a[href]");
    protectedLinks.forEach((link) => {
        const href = link.getAttribute("href") || "";
        if (!PROTECTED_LINK_PATTERN.test(href)) {
            return;
        }

        link.addEventListener("click", (e) => {
            if (isLoggedIn()) {
                return;
            }

            e.preventDefault();
            pendingRedirect = new URL(href, window.location.href).href;
            setAuthLockState(false);
            openModal("login");
            alert("Vui lòng đăng nhập để truy cập chức năng này.");
        });
    });

    ensureDemoHints();
    setAuthLockState(authLock);

    // Show auth notification banner on kho-ca-nhan if not logged in
    const authBanner = document.getElementById("auth-banner");
    const appWrapper = document.getElementById("app");
    
    if (isProtectedCurrentPage() && !isLoggedIn()) {
        if (authBanner) {
            authBanner.style.display = "flex";
        }
        if (appWrapper) {
            appWrapper.classList.add("page-overlay-locked");
        }
    } else {
        if (authBanner) {
            authBanner.style.display = "none";
        }
        if (appWrapper) {
            appWrapper.classList.remove("page-overlay-locked");
        }
    }

    // Update banner visibility after login
    const originalFinishDemoAuth = finishDemoAuth;
    finishDemoAuth = function(username) {
        originalFinishDemoAuth.call(this, username);
        if (authBanner) {
            authBanner.style.display = "none";
        }
        if (appWrapper) {
            appWrapper.classList.remove("page-overlay-locked");
        }
    };

    if (authLock) {
        openModal("login");
        alert("Trang này yêu cầu đăng nhập. Vui lòng nhập tài khoản bất kỳ để tiếp tục (demo).");
    }

    // --- HAMBURGER MENU LOGIC ---
    const hamburgerBtn = document.getElementById("hamburgerBtn");
    const navLinks = document.getElementById("navLinks");

    if (hamburgerBtn && navLinks) {
        hamburgerBtn.addEventListener("click", (e) => {
            e.stopPropagation(); // Prettify click bubbling issues
            hamburgerBtn.classList.toggle("open");
            navLinks.classList.toggle("open");
            
            // Cập nhật thuộc tính aria để hỗ trợ tiếp cận (accessibility)
            const isOpen = hamburgerBtn.classList.contains("open");
            hamburgerBtn.setAttribute("aria-expanded", isOpen);
        });

        // Đóng menu khi click vào một link (hữu ích cho Mobile)
        const links = navLinks.querySelectorAll(".nav-tab");
        links.forEach(link => {
            link.addEventListener("click", () => {
                hamburgerBtn.classList.remove("open");
                navLinks.classList.remove("open");
                hamburgerBtn.setAttribute("aria-expanded", "false");
            });
        });

        // Đóng menu khi click ra ngoài
        document.addEventListener("click", (e) => {
            if (!hamburgerBtn.contains(e.target) && !navLinks.contains(e.target)) {
                hamburgerBtn.classList.remove("open");
                navLinks.classList.remove("open");
                hamburgerBtn.setAttribute("aria-expanded", "false");
            }
        });
    }

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
