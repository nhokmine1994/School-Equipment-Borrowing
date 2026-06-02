<?php

function admin_render_head(string $pageTitle): void
{
    $title = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$title} - SEB</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../CSS/main.css">
  <link rel="stylesheet" href="../CSS/admin.css">
</head>
HTML;
}

function admin_render_shell_open(string $username = '', string $containerClass = ''): void
{
    $extraClass = trim($containerClass) !== '' ? ' ' . htmlspecialchars(trim($containerClass), ENT_QUOTES, 'UTF-8') : '';
    $userBadge = '';
    if ($username !== '') {
        $userLabel = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $userBadge = <<<HTML
      <div class="header-right admin-header-meta">
        <span class="admin-user-badge" title="Tài khoản quản trị">
          <i class="fas fa-user-shield" aria-hidden="true"></i>
          <span>{$userLabel}</span>
        </span>
      </div>
HTML;
    }
    echo <<<HTML
<body class="admin-theme">
  <div class="system-container admin-container{$extraClass}">
    <header class="header-banner admin-header-banner">
      <div class="header-logo-box">
        <img src="../Images/logo.png" alt="Logo Lộc An">
      </div>
      <div class="header-title-group">
        <h2>TRƯỜNG TRUNG HỌC CƠ SỞ LỘC AN</h2>
        <h1>HỆ THỐNG MƯỢN/TRẢ THIẾT BỊ ( SEB )</h1>
      </div>
{$userBadge}
    </header>
    <div class="admin-mode-strip" role="status">
      <i class="fas fa-shield-halved" aria-hidden="true"></i>
      <span>Chế độ quản trị viên</span>
    </div>
HTML;
}

function admin_render_nav(string $active = ''): void
{
    $items = [
        'panel' => ['admin_panel.php', 'Tổng quan'],
        'devices' => ['admin_thiet_bi.php', 'Thiết bị'],
        'users' => ['admin_users.php', 'Người dùng'],
        'borrows' => ['admin_borrows.php', 'Duyệt mượn'],
    ];

    echo '<nav class="nav-bar admin-nav-bar" aria-label="Menu quản trị">';
    echo '<div class="nav-links">';

    foreach ($items as $key => $item) {
        $href = htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8');
        $class = 'nav-tab' . ($active === $key ? ' active' : '');
        echo "<a class=\"{$class}\" href=\"{$href}\">{$label}</a>";
    }

    echo '<a class="nav-tab" href="../index.php">Về trang chủ</a>';
    echo '<a class="nav-tab admin-nav-logout" href="admin_login.php?action=logout">Đăng xuất</a>';
    echo '</div></nav>';
}

function admin_render_page_intro(string $heading, string $icon = 'fa-user-shield', string $subtitle = ''): void
{
    $headingText = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $iconClass = preg_match('/^fa-[a-z0-9-]+$/i', $icon) ? $icon : 'fa-user-shield';
    $subtitleHtml = '';

    if ($subtitle !== '') {
        $subtitleText = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
        $subtitleHtml = "<p class=\"admin-page-subtitle\">{$subtitleText}</p>";
    }

    echo <<<HTML
    <section class="section-wrapper admin-section">
      <div class="section-heading">
        <i class="fas {$iconClass}" aria-hidden="true"></i>
        {$headingText}
      </div>
      <div class="admin-content">
        {$subtitleHtml}
HTML;
}

function admin_render_shell_close(): void
{
    echo <<<HTML
      </div>
    </section>
  </div>
</body>
</html>
HTML;
}

function admin_render_footer(): void
{
    echo <<<HTML
  </div>
</body>
</html>
HTML;
}
