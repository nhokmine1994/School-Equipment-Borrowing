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
    updateHeaderAuthButtons(loginBtn, registerBtn);
    
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
        updateHeaderAuthButtons(loginBtn, registerBtn);
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
        updateHeaderAuthButtons(loginBtn, registerBtn);
    });

    window.addEventListener("storage", (event) => {
        if (event.key === AUTH_STORAGE_KEY) {
            updateHeaderAuthButtons(loginBtn, registerBtn);
        }
    });

    window.addEventListener("focus", () => {
        updateHeaderAuthButtons(loginBtn, registerBtn);
    });

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

    // --- COLLAPSIBLE SIDEBAR LOGIC (MOBILE) ---
    const collapsibleTitles = document.querySelectorAll('.collapsible-title');
    collapsibleTitles.forEach(title => {
        title.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                title.classList.toggle('active');
            }
        });
    });
});
