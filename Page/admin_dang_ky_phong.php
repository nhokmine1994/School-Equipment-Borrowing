<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Duyệt đăng ký phòng học');

$username = $_SESSION['user']['username'] ?? '';
$message = '';
$messageTone = 'success';
$csrf_token = generate_csrf_token();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// === POST handlers (CSRF-guarded) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại!';
        $messageTone = 'danger';
    } else {
        $maDangKy = (int) ($_POST['ma_dang_ky'] ?? 0);

        if ($maDangKy <= 0) {
            $message = 'ID đăng ký không hợp lệ!';
            $messageTone = 'danger';
        } else {
            switch ($action) {
                case 'approve':
                    $s = @sqlsrv_query($conn, "UPDATE DangKyPhong SET TrangThai = N'Đã duyệt' WHERE MaDangKy = ?", [$maDangKy]);
                    $message = $s ? 'Duyệt phòng học thành công.' : 'Duyệt thất bại (lỗi database).';
                    $messageTone = $s ? 'success' : 'danger';
                    break;

                case 'reject':
                    $ltreTuChoi = trim((string) ($_POST['ghi_chu'] ?? ''));
                    $s = @sqlsrv_query($conn, "UPDATE DangKyPhong SET TrangThai = N'Đã từ chối' WHERE MaDangKy = ?", [$maDangKy]);
                    $message = $s ? 'Đã từ chối đăng ký phòng.' : 'Từ chối thất bại (lỗi database).';
                    $messageTone = $s ? 'success' : 'danger';
                    break;

                case 'cancel':
                    // Trả lại trạng thái chờ duyệt (nếu admin bấm nhầm)
                    $s = @sqlsrv_query($conn, "UPDATE DangKyPhong SET TrangThai = N'Chờ duyệt' WHERE MaDangKy = ?", [$maDangKy]);
                    $message = $s ? 'Đã đưa về Chờ duyệt.' : 'Lỗi database.';
                    $messageTone = $s ? 'success' : 'danger';
                    break;

                case 'delete':
                    $s = @sqlsrv_query($conn, "DELETE FROM DangKyPhong WHERE MaDangKy = ?", [$maDangKy]);
                    $message = $s ? 'Đã xóa đăng ký phòng.' : 'Xóa thất bại (lỗi database).';
                    $messageTone = $s ? 'success' : 'danger';
                    break;
            }
        }
    }
}

// === Load bookings (mới nhất trước, cancelled ẩn,:) ===
$bookings = [];
$sql = "SELECT MaDangKy, MaDatCho, Username, LoaiPhong, LoaiPhongLabel, SoPhong, SoPhongLabel,
               TenHienThi, MucDich, DuLieuCa, TrangThai, NgayTao
        FROM DangKyPhong
        WHERE ISNULL(TrangThai, 'Chờ duyệt') <> N'cancelled'
        ORDER BY CASE WHEN ISNULL(TrangThai, 'Chờ duyệt') = N'Chờ duyệt' THEN 0 ELSE 1 END ASC, NgayTao DESC";
$stmt = @sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $slots = [];
        $duLieu = trim((string) ($row['DuLieuCa'] ?? ''));
        if ($duLieu !== '') {
            $decoded = json_decode($duLieu, true);
            if (is_array($decoded)) {
                $slots = $decoded;
            }
        }
        $row['_slots'] = $slots;
        $bookings[] = $row;
    }
}

function room_admin_human_date($value): string
{
    if (!$value) {
        return '—';
    }
    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('d/m/Y H:i');
    }
    $ts = strtotime((string) $value);
    return $ts ? date('d/m/Y H:i', $ts) : (string) $value;
}

// Trạng thái chip config
function room_admin_status_meta(string $status): array
{
    $s = trim($status) === '' ? 'Chờ duyệt' : trim($status);
    switch ($s) {
        case 'Đã duyệt':
            return ['class' => 'admin-chip-success', 'icon' => 'fa-check'];
        case 'Đã từ chối':
            return ['class' => 'admin-chip-danger', 'icon' => 'fa-xmark'];
        default:
            return ['class' => 'admin-chip-warning', 'icon' => 'fa-hourglass-half'];
    }
}

$pendingCount = 0;
foreach ($bookings as $b) {
    if (trim((string) ($b['TrangThai'] ?? '')) === '' || trim((string) $b['TrangThai']) === 'Chờ duyệt') {
        $pendingCount++;
    }
}

require_once __DIR__ . '/../components/admin_layout.php';
admin_render_head('Duyệt phòng học');
admin_render_shell_open($username);
admin_render_nav('rooms');
admin_render_page_intro(
    'Duyệt đăng ký phòng học',
    'fa-clipboard-check',
    'Duyệt, từ chối hoặc xóa các ca đăng ký phòng học của giáo viên/nhân viên.'
);
?>
<?php if (!empty($message)): ?>
  <div class="admin-flash admin-flash-<?php echo $messageTone; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<section class="admin-card">
  <div class="admin-card-head">
    <div>
      <h2 class="admin-card-title"><i class="fas fa-clipboard-list"></i> Danh sách đăng ký (<?php echo count($bookings); ?>)</h2>
      <p class="admin-card-note">Đăng ký chờ duyệt hiển thị trước. Xóa sẽ mất hoàn toàn lịch sử đăng ký.</p>
    </div>
    <span class="admin-chip admin-chip-warning"><?php echo $pendingCount; ?> chờ duyệt</span>
  </div>
  <div class="admin-card-body">
    <?php if (empty($bookings)): ?>
      <div class="admin-empty"><i class="fas fa-inbox" style="font-size: 30px; display: block; margin-bottom: 10px; color: #94a3b8;"></i>Chưa có đăng ký phòng học nào.</div>
    <?php else: ?>
      <div class="admin-notification-feed">
      <?php foreach ($bookings as $b): $id = (int) $b['MaDangKy']; $statusMeta = room_admin_status_meta((string) ($b['TrangThai'] ?? '')); ?>
        <article class="admin-notification-item" style="flex-wrap: wrap;">
          <div class="admin-notification-dot user"><i class="fas fa-door-open"></i></div>
          <div style="min-width: 0; flex: 1;">
            <p class="admin-notification-title">
              <?php echo htmlspecialchars(($b['LoaiPhongLabel'] ?: $b['LoaiPhong']) . ' - Phòng ' . ($b['SoPhongLabel'] ?: $b['SoPhong'])); ?>
              <span class="admin-chip <?php echo $statusMeta['class']; ?>" style="margin-left: 8px; padding: 2px 8px;"><i class="fas <?php echo $statusMeta['icon']; ?>"></i> <?php echo htmlspecialchars($b['TrangThai'] ?: 'Chờ duyệt'); ?></span>
            </p>
            <p class="admin-notification-text">
              Người đặt: <strong><?php echo htmlspecialchars($b['TenHienThi'] ?: $b['Username']); ?></strong>
              (<?php echo htmlspecialchars($b['Username']); ?>)
              · Ngày đặt: <?php echo room_admin_human_date($b['NgayTao'] ?? null); ?>
            </p>
            <?php $muc = trim((string) ($b['MucDich'] ?? ''));
            if ($muc !== ''): ?>
              <p class="admin-notification-text"><strong>Mục đích:</strong> <?php echo htmlspecialchars($muc); ?></p>
            <?php endif; ?>
            <?php if (!empty($b['_slots'])): ?>
              <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px;">
                <?php foreach ($b['_slots'] as $slot): ?>
                  <span class="admin-chip admin-chip-primary" style="padding: 3px 9px; font-size: 12px;">
                    <?php echo htmlspecialchars(trim((string) ($slot['dayLabel'] ?? '')) . ' · ' . trim((string) ($slot['timeLabel'] ?? ''))); ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div style="display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap;">
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="ma_dang_ky" value="<?php echo $id; ?>">
              <button type="submit" class="admin-btn admin-btn-primary" title="Duyệt"><i class="fas fa-check"></i></button>
            </form>
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="ma_dang_ky" value="<?php echo $id; ?>">
              <button type="submit" class="admin-btn admin-btn-danger" title="Từ chối"><i class="fas fa-xmark"></i></button>
            </form>
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="ma_dang_ky" value="<?php echo $id; ?>">
              <button type="submit" class="admin-btn admin-btn-soft" title="Đưa về Chờ duyệt"><i class="fas fa-rotate-left"></i></button>
            </form>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Xóa đăng ký phòng này?');">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ma_dang_ky" value="<?php echo $id; ?>">
              <button type="submit" class="admin-btn admin-btn-danger" title="Xóa"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php admin_render_shell_close(); ?>
