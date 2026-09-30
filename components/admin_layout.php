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
  <link rel="stylesheet" href="../CSS/admin.css?v=20260929-sidebar">
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
    $groups = [
        ['label' => 'Điều hành', 'items' => [
            'panel' => ['admin_panel.php', 'Tổng quan', 'fa-gauge-high'],
            'stats' => ['admin_thong_ke.php', 'Thống kê', 'fa-chart-pie'],
        ]],
        ['label' => 'Quản lý', 'items' => [
            'devices' => ['admin_thiet_bi.php', 'Thiết bị', 'fa-boxes-stacked'],
            'users' => ['admin_users.php', 'Người dùng', 'fa-users'],
            'maintenance' => ['admin_bao_tri.php', 'Bảo trì', 'fa-screwdriver-wrench'],
            'news' => ['admin_tin_tuc.php', 'Tin tức', 'fa-newspaper'],
        ]],
        ['label' => 'Duyệt yêu cầu', 'items' => [
            'borrows' => ['admin_borrows.php', 'Duyệt mượn', 'fa-clipboard-check'],
            'rooms' => ['admin_dang_ky_phong.php', 'Duyệt phòng', 'fa-calendar-check'],
            'exports' => ['admin_export.php', 'Xuất file', 'fa-file-export'],
        ]],
    ];

    echo '<nav class="nav-bar admin-nav-bar" aria-label="Menu quản trị">';
    echo '<div class="nav-links">';

    foreach ($groups as $group) {
        $groupLabel = htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8');
        echo "<div class=\"admin-nav-group\"><span class=\"admin-nav-group-label\">{$groupLabel}</span>";
        foreach ($group['items'] as $key => $item) {
        $href = htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars($item[2], ENT_QUOTES, 'UTF-8');
        $class = 'nav-tab' . ($active === $key ? ' active' : '');
        echo "<a class=\"{$class}\" href=\"{$href}\" title=\"{$label}\" aria-label=\"{$label}\"><i class=\"fas {$icon}\" aria-hidden=\"true\"></i><span>{$label}</span></a>";
        }
        echo '</div>';
    }

    echo '<a class="nav-tab admin-nav-home" href="../index.php" title="Về trang chủ" aria-label="Về trang chủ"><i class="fas fa-house"></i><span>Về trang chủ</span></a>';
    echo '<a class="nav-tab admin-nav-logout" href="admin_login.php?action=logout" title="Đăng xuất" aria-label="Đăng xuất"><i class="fas fa-right-from-bracket"></i><span>Đăng xuất</span></a>';
    echo '</div></nav>';
    echo <<<'HTML'
    <script>
    (() => {
      const apiUrl = '../api/seb_api.php?action=admin_poll';
      const status = document.getElementById('adminLiveStatus');
      let previous = null;
      let timer = null;
      const showNotice = (message, link) => {
        const node = document.createElement('a');
        node.className = 'admin-live-toast';
        node.href = link;
        node.innerHTML = '<i class="fas fa-bell"></i><span>' + message + '</span>';
        document.body.appendChild(node);
        setTimeout(() => node.remove(), 7000);
      };
      const poll = async () => {
        try {
          const response = await fetch(apiUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
          const payload = await response.json();
          if (!payload.ok) return;
          const counts = payload.counts || {};
          if (status) status.classList.add('is-online');
          if (previous) {
            if (counts.pending_resets > previous.pending_resets) showNotice('Có yêu cầu quên mật khẩu mới', 'admin_users.php');
            else if (counts.pending_borrows > previous.pending_borrows) showNotice('Có yêu cầu mượn mới', 'admin_borrows.php');
            else if (counts.pending_rooms > previous.pending_rooms) showNotice('Có đăng ký phòng mới', 'admin_dang_ky_phong.php');
          }
          previous = counts;
        } catch (error) {
          if (status) status.classList.remove('is-online');
        }
      };
      poll();
      timer = window.setInterval(poll, 15000);
      window.addEventListener('beforeunload', () => window.clearInterval(timer), { once: true });
    })();
    </script>
HTML;
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
