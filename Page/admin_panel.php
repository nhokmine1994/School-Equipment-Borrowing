<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Bảng điều khiển quản trị');
require_once __DIR__ . '/../components/seb_db.php';

$username = $_SESSION['user']['username'] ?? '';
$devices = [];
$borrows = [];
$message = '';

function admin_human_date($value)
{
    if (!$value) {
        return '';
    }

    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('d/m/Y H:i');
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : (string) $value;
}

function admin_timestamp($value)
{
    if (!$value) {
        return 0;
    }

    if (is_object($value) && method_exists($value, 'format')) {
        return (int) $value->getTimestamp();
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ?: 0;
}

function admin_device_status_text($status)
{
    $status = mb_strtolower(trim((string) $status), 'UTF-8');
    if ($status === '') {
        return 'Sẵn sàng';
    }
    return $status;
}

function admin_device_is_broken($status)
{
    $status = mb_strtolower(trim((string) $status), 'UTF-8');
    return strpos($status, 'hỏng') !== false || strpos($status, 'hong') !== false || strpos($status, 'broken') !== false;
}

function admin_device_is_maintenance($status)
{
    $status = mb_strtolower(trim((string) $status), 'UTF-8');
    return strpos($status, 'bảo trì') !== false || strpos($status, 'bao tri') !== false || strpos($status, 'unavailable') !== false;
}

function admin_borrow_status_key_panel($status)
{
  global $conn;
  $label = function_exists('seb_resolve_borrow_status_label') ? seb_resolve_borrow_status_label($conn, $status) : (string) $status;
  $label = trim((string) $label);

  if ($label === '') {
    return '';
  }

  if (function_exists('mb_strtolower')) {
    return mb_strtolower($label, 'UTF-8');
  }

  return strtolower($label);
}

// devices
$deviceSql = "EXEC sp_XemKho";
$deviceStmt = sqlsrv_query($conn, $deviceSql);
if ($deviceStmt) {
    while ($row = sqlsrv_fetch_array($deviceStmt, SQLSRV_FETCH_ASSOC)) {
        $devices[] = $row;
    }
}

// borrows
$borrowStatusInfo = seb_resolve_phieu_muon_status_column($conn);
$borrowStatusColumn = !empty($borrowStatusInfo['ok']) ? ('pm.[' . str_replace(']', ']]', $borrowStatusInfo['column']) . ']') : '';
$borrowSql = !empty($borrowStatusInfo['ok'])
  ? "SELECT TOP 100 pm.SoPhieuMuon AS BorrowID, pm.IDThietBi, pm.TenThietBi, pm.TaiKhoan AS Username, pm.NgayMuon, pm.HanTra AS NgayTraDuKien, pm.NgayTraThucTe, pm.SoLuong, {$borrowStatusColumn} AS TrangThai, pm.GhiChu
        FROM PhieuMuon pm
    LEFT JOIN Kho t ON pm.IDThietBi = t.ID
        ORDER BY pm.NgayMuon DESC"
  : "SELECT TOP 100 b.BorrowID, b.MaThietBi, t.TenThietBi, b.Username, b.NgayMuon, b.NgayTraDuKien, b.NgayTraThucTe, b.SoLuong, b.TrangThai, b.GhiChu
        FROM Borrows b
        LEFT JOIN Kho t ON b.MaThietBi = t.MaThietBi
        ORDER BY b.NgayMuon DESC";
$borrowStmt = sqlsrv_query($conn, $borrowSql);
if ($borrowStmt) {
    while ($row = sqlsrv_fetch_array($borrowStmt, SQLSRV_FETCH_ASSOC)) {
        $borrows[] = $row;
    }
}

$now = time();
$todayStart = strtotime(date('Y-m-d 00:00:00', $now));
$todayEnd = strtotime(date('Y-m-d 23:59:59', $now));
// Compute totals by quantity (SoLuong) rather than counting device types
$totalDevices = 0;
$brokenDeviceCount = 0;
$brokenDeviceItems = [];
$maintenanceDevices = 0;
foreach ($devices as $item) {
  $qty = max(0, (int) ($item['SoLuong'] ?? $item['SoLuongTon'] ?? 0));
  $totalDevices += $qty;
  if (admin_device_is_broken($item['TinhTrang'] ?? '')) {
    $brokenDeviceCount += $qty;
    $brokenDeviceItems[] = $item;
  }
  if (admin_device_is_maintenance($item['TinhTrang'] ?? '')) {
    $maintenanceDevices += $qty;
  }
}
$overdueBorrows = array_values(array_filter($borrows, function ($item) use ($now) {
    if (($item['NgayTraThucTe'] ?? null) !== null) {
        return false;
    }
    $status = admin_borrow_status_key_panel($item['TrangThai'] ?? '');
    $due = admin_timestamp($item['NgayTraDuKien'] ?? null);
    return in_array($status, ['approved', 'đã duyệt', 'da duyet', '1'], true) && $due > 0 && $due < $now;
}));
  $dueTodayBorrows = array_values(array_filter($borrows, function ($item) use ($todayStart, $todayEnd) {
    if (($item['NgayTraThucTe'] ?? null) !== null) {
      return false;
    }
    $status = admin_borrow_status_key_panel($item['TrangThai'] ?? '');
    $due = admin_timestamp($item['NgayTraDuKien'] ?? null);
    return in_array($status, ['approved', 'đã duyệt', 'da duyet', '1'], true) && $due >= $todayStart && $due <= $todayEnd;
  }));
  $dueSoonBorrows = array_values(array_filter($borrows, function ($item) use ($todayEnd) {
    if (($item['NgayTraThucTe'] ?? null) !== null) {
        return false;
    }
    $status = admin_borrow_status_key_panel($item['TrangThai'] ?? '');
    $due = admin_timestamp($item['NgayTraDuKien'] ?? null);
    $soonStart = $todayEnd + 1;
    $soonEnd = $todayEnd + (3 * 24 * 60 * 60);
    return in_array($status, ['approved', 'đã duyệt', 'da duyet', '1'], true) && $due >= $soonStart && $due <= $soonEnd;
}));

$recentUsers = [];
$userStmt = sqlsrv_query($conn, "SELECT TOP 5 TaiKhoan, LoaiTaiKhoan, HoVaTen, SoDienThoai FROM TaiKhoan ORDER BY TaiKhoan DESC");
if ($userStmt) {
    while ($row = sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC)) {
        $recentUsers[] = $row;
    }
}

$dbNotifications = fetch_admin_notifications($conn, 6);
$feed = [];

foreach ($dbNotifications as $item) {
    $feed[] = [
    'type' => $item['LoaiThongBao'] ?? 'info',
    'title' => $item['TieuDe'] ?? 'Thông báo',
    'message' => $item['LoiNhan'] ?? '',
    'time' => admin_human_date($item['ThoiGianTao'] ?? null),
    'timestamp' => admin_timestamp($item['ThoiGianTao'] ?? null),
    'link' => $item['Link'] ?? '',
    'read' => (int) ($item['TrangThai'] ?? 0),
    ];
}

foreach (array_slice($borrows, 0, 5) as $item) {
  $status = admin_borrow_status_key_panel($item['TrangThai'] ?? '');
    $type = 'borrow';
    if (in_array($status, ['pending', 'chờ duyệt', 'waiting', '3'], true)) {
        $type = 'warn';
    } elseif (in_array($status, ['rejected', 'từ chối', '2'], true)) {
        $type = 'danger';
    }

    $feed[] = [
        'type' => $type,
        'title' => 'Mượn thiết bị',
        'message' => trim((string) ($item['Username'] ?? 'Người dùng')) . ' vừa ' . (in_array($status, ['pending', 'chờ duyệt', 'waiting', '3'], true) ? 'gửi yêu cầu mượn' : 'cập nhật mượn') . ' thiết bị ' . trim((string) ($item['TenThietBi'] ?? $item['MaThietBi'] ?? '')),
        'time' => admin_human_date($item['NgayMuon'] ?? null),
        'timestamp' => admin_timestamp($item['NgayMuon'] ?? null),
        'link' => 'admin_borrows.php',
    ];
}

foreach (array_slice($recentUsers, 0, 5) as $item) {
    $feed[] = [
      'type' => 'user',
      'title' => 'Tài khoản mới',
      'message' => trim((string) ($item['TaiKhoan'] ?? 'Người dùng')) . ' - ' . trim((string) ($item['HoVaTen'] ?? '')) . ' đã có trong hệ thống.',
      'time' => '',
      'timestamp' => 0,
      'link' => 'admin_users.php',
    ];
}

usort($feed, function ($a, $b) {
    return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
});

$feed = array_slice($feed, 0, 8);

require_once __DIR__ . '/../components/admin_layout.php';
admin_render_head('Bảng điều khiển quản trị');
admin_render_shell_open($username);
admin_render_nav('panel');
admin_render_page_intro(
    'Bảng điều khiển quản trị',
    'fa-gauge-high',
    'Tổng quan thiết bị, trạng thái mượn trả, người dùng và thông báo hệ thống.'
);
?>
    <section class="admin-grid-4">
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Tổng quan thiết bị</p>
        <p class="admin-stat-value"><?php echo $totalDevices; ?></p>
        <p class="admin-stat-desc">Toàn bộ thiết bị hiện có trong kho.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Thiết bị quá hạn trả</p>
        <p class="admin-stat-value"><?php echo count($overdueBorrows); ?></p>
        <p class="admin-stat-desc">Đang mượn nhưng chưa trả đúng hạn.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Thiết bị sắp đến hạn</p>
        <p class="admin-stat-value"><?php echo count($dueSoonBorrows); ?></p>
        <p class="admin-stat-desc">Sẽ đến hạn trả trong 3 ngày tới.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Thiết bị báo hỏng</p>
        <p class="admin-stat-value"><?php echo $brokenDeviceCount; ?></p>
        <p class="admin-stat-desc">Trạng thái hỏng/báo hỏng/broken.</p>
      </article>
    </section>

    <div class="admin-layout">
      <div>
        <section class="admin-card">
          <div class="admin-card-head">
            <div>
              <h2 class="admin-card-title">Thiết bị quá hạn trả</h2>
              <p class="admin-card-note">Danh sách ưu tiên xử lý ngay.</p>
            </div>
            <span class="admin-chip admin-chip-danger"><?php echo count($overdueBorrows); ?> mục</span>
          </div>
          <div class="admin-card-body">
            <?php if (empty($overdueBorrows)): ?>
              <div class="admin-empty">Không có thiết bị quá hạn trả.</div>
            <?php else: ?>
              <div class="admin-notification-feed">
                <?php foreach (array_slice($overdueBorrows, 0, 5) as $item): ?>
                  <div class="admin-notification-item">
                    <div class="admin-notification-dot danger"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                      <p class="admin-notification-title"><?php echo htmlspecialchars($item['TenThietBi'] ?? $item['MaThietBi'] ?? 'Thiết bị'); ?></p>
                      <p class="admin-notification-text">Người mượn: <?php echo htmlspecialchars($item['Username'] ?? ''); ?> | Số lượng: <?php echo (int) ($item['SoLuong'] ?? 1); ?> | Hạn trả: <?php echo htmlspecialchars(admin_human_date($item['NgayTraDuKien'] ?? null)); ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="admin-card" style="margin-top:16px;">
          <div class="admin-card-head">
            <div>
              <h2 class="admin-card-title">Thiết bị đến hạn hôm nay</h2>
              <p class="admin-card-note">Các thiết bị cần trả trong ngày hôm nay.</p>
            </div>
            <span class="admin-chip admin-chip-warning"><?php echo count($dueTodayBorrows); ?> mục</span>
          </div>
          <div class="admin-card-body">
            <?php if (empty($dueTodayBorrows)): ?>
              <div class="admin-empty">Không có thiết bị nào đến hạn hôm nay.</div>
            <?php else: ?>
              <div class="admin-notification-feed">
                <?php foreach (array_slice($dueTodayBorrows, 0, 5) as $item): ?>
                  <div class="admin-notification-item">
                    <div class="admin-notification-dot warn"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                      <p class="admin-notification-title"><?php echo htmlspecialchars($item['TenThietBi'] ?? $item['MaThietBi'] ?? 'Thiết bị'); ?></p>
                      <p class="admin-notification-text">Người mượn: <?php echo htmlspecialchars($item['Username'] ?? ''); ?> | Số lượng: <?php echo (int) ($item['SoLuong'] ?? 1); ?> | Hạn trả: <?php echo htmlspecialchars(admin_human_date($item['NgayTraDuKien'] ?? null)); ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="admin-card" style="margin-top:16px;">
          <div class="admin-card-head">
            <div>
              <h2 class="admin-card-title">Thiết bị sắp đến hạn</h2>
              <p class="admin-card-note">Các thiết bị cần nhắc trước khi quá hạn.</p>
            </div>
            <span class="admin-chip admin-chip-warning"><?php echo count($dueSoonBorrows); ?> mục</span>
          </div>
          <div class="admin-card-body">
            <?php if (empty($dueSoonBorrows)): ?>
              <div class="admin-empty">Chưa có thiết bị nào sắp đến hạn.</div>
            <?php else: ?>
              <div class="admin-notification-feed">
                <?php foreach (array_slice($dueSoonBorrows, 0, 5) as $item): ?>
                  <div class="admin-notification-item">
                    <div class="admin-notification-dot warn"><i class="fas fa-clock"></i></div>
                    <div>
                      <p class="admin-notification-title"><?php echo htmlspecialchars($item['TenThietBi'] ?? $item['MaThietBi'] ?? 'Thiết bị'); ?></p>
                      <p class="admin-notification-text">Người mượn: <?php echo htmlspecialchars($item['Username'] ?? ''); ?> | Số lượng: <?php echo (int) ($item['SoLuong'] ?? 1); ?> | Hạn trả: <?php echo htmlspecialchars(admin_human_date($item['NgayTraDuKien'] ?? null)); ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="admin-card" style="margin-top:16px;">
          <div class="admin-card-head">
            <div>
              <h2 class="admin-card-title">Thiết bị báo hỏng</h2>
              <p class="admin-card-note">Thiết bị cần bảo trì / thay thế.</p>
            </div>
            <span class="admin-chip admin-chip-primary"><?php echo $brokenDeviceCount; ?> mục</span>
          </div>
          <div class="admin-card-body">
            <?php if (empty($brokenDeviceItems)): ?>
              <div class="admin-empty">Chưa có thiết bị báo hỏng.</div>
            <?php else: ?>
              <div class="admin-media-list">
                <?php foreach (array_slice($brokenDeviceItems, 0, 4) as $item):
                  $image = trim((string) ($item['HinhAnh'] ?? ''));
                  $imagePath = $image ? '../Images/devices/' . $image : '';
                ?>
                  <article class="admin-device-card">
                    <div class="admin-device-cover">
                      <?php if ($imagePath): ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($item['TenThietBi'] ?? 'Thiết bị'); ?>">
                      <?php else: ?>
                        <i class="fas fa-tools" style="font-size:36px;color:#7fa6c8"></i>
                      <?php endif; ?>
                    </div>
                    <div class="admin-device-body">
                      <h3 class="admin-device-title"><?php echo htmlspecialchars($item['TenThietBi'] ?? 'Thiết bị'); ?></h3>
                      <p class="admin-device-meta">Mã: <?php echo htmlspecialchars($item['MaThietBi'] ?? ''); ?><br>Trạng thái: <?php echo htmlspecialchars($item['TinhTrang'] ?? ''); ?><br>Số lượng: <?php echo (int) ($item['SoLuong'] ?? 0); ?></p>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="admin-card" style="margin-top:16px;">
          <div class="admin-card-head">
            <div>
              <h2 class="admin-card-title">Lối tắt quản trị</h2>
              <p class="admin-card-note">Chuyển nhanh tới các khu vực thao tác.</p>
            </div>
          </div>
          <div class="admin-card-body admin-toolbar">
            <a class="admin-btn admin-btn-primary" href="admin_thiet_bi.php#them-moi">Thêm thiết bị mới</a>
            <a class="admin-btn admin-btn-soft" href="admin_users.php">Quản lý người dùng</a>
            <a class="admin-btn admin-btn-soft" href="admin_bao_tri.php">Quản lý Bảo trì</a>
          </div>
        </section>
      </div>

      <aside>
        <section class="admin-card">
          <div class="admin-card-head">
            <div>
              <h2 class="admin-card-title">Bảng thông báo</h2>
              <p class="admin-card-note">Dạng feed như mạng xã hội cho hoạt động gần đây.</p>
            </div>
          </div>
          <div class="admin-card-body">
            <?php if (empty($feed)): ?>
              <div class="admin-empty">Chưa có thông báo nào.</div>
            <?php else: ?>
              <div class="admin-notification-feed">
                <?php foreach ($feed as $item):
                  $type = $item['type'] ?? 'info';
                  $icon = 'fa-bell';
                  $dotClass = 'user';
                  if ($type === 'borrow') {
                      $icon = 'fa-hand-holding-circle';
                      $dotClass = 'borrow';
                  } elseif ($type === 'warn') {
                      $icon = 'fa-clock';
                      $dotClass = 'warn';
                  } elseif ($type === 'danger') {
                      $icon = 'fa-triangle-exclamation';
                      $dotClass = 'danger';
                  }
                ?>
                  <div class="admin-notification-item">
                    <div class="admin-notification-dot <?php echo $dotClass; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                    <div style="min-width:0;">
                      <p class="admin-notification-title"><?php echo htmlspecialchars($item['title'] ?? 'Thông báo'); ?></p>
                      <p class="admin-notification-text"><?php echo htmlspecialchars($item['message'] ?? ''); ?></p>
                      <?php if (!empty($item['time'])): ?><div class="admin-notification-time"><?php echo htmlspecialchars($item['time']); ?></div><?php endif; ?>
                      <?php if (!empty($item['link'])): ?>
                        <div style="margin-top:8px;"><a class="admin-btn admin-btn-soft" href="<?php echo htmlspecialchars($item['link']); ?>" style="padding:8px 11px;font-size:12px;text-decoration:none;display:inline-block;">Xem chi tiết</a></div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </aside>
    </div>
<?php admin_render_shell_close(); ?>
