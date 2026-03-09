// script.js - JavaScript cho landing page

// Hàm để thêm class 'animate' khi phần tử vào viewport
function animateOnScroll() {
    const sections = document.querySelectorAll('.section');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate');
            }
        });
    }, {
        threshold: 0.1
    });
    
    sections.forEach(section => {
        observer.observe(section);
    });
}

// Hàm để scroll xuống phần features khi click nút
function scrollToFeatures() {
    const featuresSection = document.getElementById('features');
    featuresSection.scrollIntoView({ behavior: 'smooth' });
}

// Thêm event listener khi DOM loaded
document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo animation on scroll
    animateOnScroll();
    
    // Thêm event listener cho nút "Xem chức năng"
    const viewFeaturesBtn = document.getElementById('view-features-btn');
    if (viewFeaturesBtn) {
        viewFeaturesBtn.addEventListener('click', scrollToFeatures);
    }
});