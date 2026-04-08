document.addEventListener("DOMContentLoaded", () => {
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

    const loginBtn = document.getElementById("loginBtn");
    const registerBtn = document.getElementById("registerBtn");
    
    const authModal = document.getElementById("authModal");
    const closeModalBtn = document.getElementById("closeModalBtn");
    
    const loginFormContainer = document.getElementById("loginFormContainer");
    const registerFormContainer = document.getElementById("registerFormContainer");
    
    const switchToRegister = document.getElementById("switchToRegister");
    const switchToLogin = document.getElementById("switchToLogin");

    function openModal(type) {
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
        authModal.style.display = "none";
    }

    if (loginBtn) {
        loginBtn.addEventListener("click", () => openModal("login"));
    }

    if (registerBtn) {
        registerBtn.addEventListener("click", () => openModal("register"));
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
});
