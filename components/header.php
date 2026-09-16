<header class="site-header">
  <div class="header-container">
    <div class="brand">
      <img src="../Images/logo.png" alt="Logo" class="logo" />
    </div>

    <div class="brand-text center-title">
      <div class="school-name">TRƯỜNG TRUNG HỌC CƠ SỞ LỘC AN</div>
      <div class="system-name">HỆ THỐNG MƯỢN/TRẢ THIẾT BỊ (SEB)</div>
    </div>

    <div class="header-actions">
      <button type="button" class="icon-btn icon-signin" aria-label="Đăng nhập" onclick="window.sebOpenAuthModal && window.sebOpenAuthModal('login')"></button>
      <button type="button" class="icon-btn icon-signup" aria-label="Đăng ký" onclick="window.sebOpenAuthModal && window.sebOpenAuthModal('register')"></button>
    </div>
  </div>
</header>

<nav class="site-nav">
  <a href="../index.php">Trang chủ</a>
  <a href="../Page/kho_thiet_bi.php">Kho thiết bị</a>
  <a href="../Page/dang-ky-phong-hoc.php">Đăng ký phòng học</a>
  <a href="../Page/kho-ca-nhan.php">Kho cá nhân</a>
  <a href="../Page/news.php">Tin tức</a>
  <a href="../Page/about.php">Liên hệ</a>
</nav>
<script>
  const currentPath = window.location.pathname.toLowerCase();
  const currentPage = (currentPath === '/' || currentPath === '' || currentPath.endsWith('/'))
    ? 'index.php'
    : currentPath.split('/').pop();

  document.querySelectorAll('.site-nav a').forEach(link => {
    const href = link.getAttribute('href').toLowerCase();
    const hrefPage = href === '/' || href === '' || href.endsWith('/') ? 'index.php' : href.split('/').pop();
    if (hrefPage === currentPage || (currentPage === 'index.php' && hrefPage === 'index.php')) {
      link.classList.add('active');
    }
  });
</script>