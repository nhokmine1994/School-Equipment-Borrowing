<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Báo lỗi người dùng');
require_once __DIR__ . '/../components/admin_layout.php';

$username = $_SESSION['user']['username'] ?? '';
$message = '';
$messageTone = 'success';
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ.';
        $messageTone = 'danger';
    } else {
        $action = $_POST['action'];
        $id = (int) ($_POST['ma_bao_cao'] ?? 0);
        if ($id <= 0) {
            $message = 'Mã báo cáo không hợp lệ.';
            $messageTone = 'danger';
        } elseif ($action === 'resolve') {
            $s = sqlsrv_query($conn, "UPDATE BaoCaoLoi SET TrangThai = N'đã xử lý' WHERE MaBaoCao = ?", [$id]);
            $message = $s ? 'Đã đánh dấu đã xử lý.' : 'Cập nhật thất bại.';
            $messageTone = $s ? 'success' : 'danger';
        } elseif ($action === 'delete') {
            $s = sqlsrv_query($conn, "DELETE FROM BaoCaoLoi WHERE MaBaoCao = ?", [$id]);
            $message = $s ? 'Đã xóa báo cáo.' : 'Xóa thất bại.';
            $messageTone = $s ? 'success' : 'danger';
        }
    }
}

$reports = [];
$pendingCount = 0;
$stmt = sqlsrv_query($conn, "SELECT MaBaoCao, TaiKhoan, UrlTrang, TieuDe, NoiDung, MucDo, TrangThai, NgayTao FROM BaoCaoLoi ORDER BY NgayTao DESC");
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $reports[] = $row;
        $st = mb_strtolower(trim((string) ($row['TrangThai'] ?? '')), 'UTF-8');
        if (in_array($st, ['mới', 'moi', 'new'], true)) {
            $pendingCount++;
        }
    }
}

admin_render_head('Báo lỗi người dùng');
admin_render_shell_open($username);
admin_render_nav('bug_reports');
admin_render_page_intro('Báo lỗi người dùng', 'fa-bug', 'Danh sách lỗi và góp ý mà người dùng đã gửi từ các trang.');
?>
<?php if ($message): ?>
  <div class="admin-flash <?php echo $messageTone === 'danger' ? 'admin-flash-danger' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<section class="admin-grid-4">
  <article class="admin-card admin-stat">
    <p class="admin-stat-label">Tổng báo cáo</p>
    <p class="admin-stat-value"><?php echo count($reports); ?></p>
    <p class="admin-stat-desc">Toàn bộ báo cáo đã nhận.</p>
  </article>
  <article class="admin-card admin-stat">
    <p class="admin-stat-label">Chờ xử lý</p>
    <p class="admin-stat-value"><?php echo $pendingCount; ?></p>
    <p class="admin-stat-desc">Báo cáo mới chưa xử lý.</p>
  </article>
  <article class="admin-card admin-stat">
    <p class="admin-stat-label">Đã xử lý</p>
    <p class="admin-stat-value"><?php echo count($reports) - $pendingCount; ?></p>
    <p class="admin-stat-desc">Báo cáo đã hoàn tất.</p>
  </article>
  <article class="admin-card admin-stat">
    <p class="admin-stat-label">Xuất Excel</p>
    <p class="admin-stat-value"><a href="admin_export.php?type=bug_reports" class="admin-btn admin-btn-primary" style="text-decoration:none;font-size:16px;padding:8px 14px;"><i class="fas fa-file-excel"></i> Tải báo cáo</a></p>
    <p class="admin-stat-desc">Xuất toàn bộ ra file CSV.</p>
  </article>
</section>

<section class="admin-card" style="margin-top:16px;">
  <div class="admin-card-head">
    <div>
      <h2 class="admin-card-title"><i class="fas fa-inbox"></i> Danh sách báo cáo (<?php echo count($reports); ?>)</h2>
      <p class="admin-card-note">Báo cáo mới hiển thị trước. Có thể đánh dấu đã xử lý hoặc xóa báo cáo.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <span class="admin-chip admin-chip-warning"><?php echo $pendingCount; ?> chờ xử lý</span>
    </div>
  </div>
  <div class="admin-card-body">
    <?php if (empty($reports)): ?>
      <div class="admin-empty"><i class="fas fa-inbox" style="font-size:30px;display:block;margin-bottom:10px;color:#94a3b8;"></i>Chưa có báo cáo nào.</div>
    <?php else: ?>
      <div class="admin-notification-feed">
      <?php foreach ($reports as $r):
        $rid = (int) $r['MaBaoCao'];
        $st = trim((string) ($r['TrangThai'] ?? 'mới'));
        $isNew = in_array(mb_strtolower($st, 'UTF-8'), ['mới', 'moi', 'new'], true);
        $date = is_object($r['NgayTao']) ? $r['NgayTao']->format('d/m/Y H:i') : (string) ($r['NgayTao'] ?? '');
        $user = trim((string) ($r['TaiKhoan'] ?? ''));
        $url = trim((string) ($r['UrlTrang'] ?? ''));
        $title = trim((string) ($r['TieuDe'] ?? ''));
        $content = trim((string) ($r['NoiDung'] ?? ''));
        $priority = trim((string) ($r['MucDo'] ?? 'normal'));
      ?>
        <article class="admin-notification-item" style="flex-wrap:wrap;">
          <div class="admin-notification-dot <?php echo $isNew ? 'danger' : 'warn'; ?>">
            <i class="fas fa-bug"></i>
          </div>
          <div style="min-width:0;flex:1;">
            <p class="admin-notification-title">
              <?php echo htmlspecialchars($title); ?>
              <span class="admin-chip <?php echo $isNew ? 'admin-chip-danger' : 'admin-chip-success'; ?>" style="margin-left:8px;padding:2px 8px;">
                <i class="fas <?php echo $isNew ? 'fa-exclamation-triangle' : 'fa-check'; ?>"></i>
                <?php echo htmlspecialchars($st); ?>
              </span>
              <?php if ($priority !== 'normal'): ?>
                <span class="admin-chip admin-chip-warning" style="margin-left:4px;padding:2px 8px;"><i class="fas fa-flag"></i> <?php echo htmlspecialchars($priority); ?></span>
              <?php endif; ?>
            </p>
            <p class="admin-notification-text">
              <?php if ($user !== ''): ?><strong>Người gửi:</strong> <?php echo htmlspecialchars($user); ?> · <?php endif; ?>
              <?php if ($url !== ''): ?><span style="font-family:monospace;font-size:12px;color:#64748b;"><?php echo htmlspecialchars($url); ?></span> · <?php endif; ?>
              <small style="color:#64748b;"><?php echo htmlspecialchars($date); ?></small>
            </p>
            <?php if ($content !== ''): ?>
              <div style="margin-top:8px;padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;white-space:pre-wrap;word-break:break-word;font-size:13px;color:#334155;line-height:1.6;max-height:200px;overflow-y:auto;">
                <?php echo htmlspecialchars($content); ?>
              </div>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap;">
            <?php if ($isNew): ?>
            <form method="POST" style="margin:0;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="resolve">
              <input type="hidden" name="ma_bao_cao" value="<?php echo $rid; ?>">
              <button type="submit" class="admin-btn admin-btn-success" title="Đánh dấu đã xử lý"><i class="fas fa-check"></i> Đã xử lý</button>
            </form>
            <?php endif; ?>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Xóa báo cáo này?');">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ma_bao_cao" value="<?php echo $rid; ?>">
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
