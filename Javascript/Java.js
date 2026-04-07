// ============================================
//  Hamburger Menu Toggle (dùng chung mọi trang)
// ============================================

const hamburgerBtn = document.getElementById('hamburgerBtn');
const navLinks     = document.getElementById('navLinks');
const navTabs      = document.querySelectorAll('.nav-tab');

if (hamburgerBtn && navLinks) {

  hamburgerBtn.addEventListener('click', function () {
    const isOpen = navLinks.classList.toggle('open');
    hamburgerBtn.classList.toggle('open', isOpen);
    hamburgerBtn.setAttribute('aria-expanded', isOpen);
  });

  navTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      navTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      navLinks.classList.remove('open');
      hamburgerBtn.classList.remove('open');
      hamburgerBtn.setAttribute('aria-expanded', false);
    });
  });

  document.addEventListener('click', function (e) {
    const nav = document.getElementById('mainNav');
    if (nav && !nav.contains(e.target)) {
      navLinks.classList.remove('open');
      hamburgerBtn.classList.remove('open');
      hamburgerBtn.setAttribute('aria-expanded', false);
    }
  });

}
