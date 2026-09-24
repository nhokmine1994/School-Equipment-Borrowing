<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Đăng tin tức');

$username = $_SESSION['user']['username'] ?? '';
$message = '';
$messageTone = 'success';
$csrf_token = generate_csrf_token();

// CHỨNG quy định các LoaiThongBao cho CHECK constraint CK_LoaiThongBao
$loaiHopsLe = ['Bổ sung', 'Sửa chữa', 'Cập nhật', 'Bảo trì'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại!';
        $messageTone = 'danger';
    } else {
        $maTin = (int) ($_POST['ma_tin'] ?? 0);
        if ($action === 'add') {
            $loaiTin = trim((string) ($_POST['loai_tin'] ?? 'Bổ sung'));
            $noiDung = trim((string) ($_POST['noi_dung'] ?? ''));
            $trangThai = trim((string) ($_POST['trang_thai'] ?? 'Hiển thị'));

            if (!in_array($loaiTin, $loaiHopsLe, true)) {
                $message = "Loại tin không hợp lệ. Chọn một trong: " . implode(', ', $loaiHopsLe);
                $messageTone = 'danger';
            } elseif ($noiDung === '') {
                $message = 'Nội dung không được bỏ trống!';
                $messageTone = 'danger';
            } else {
                $stmt = @sqlsrv_query(
                    $conn,
                    "INSERT INTO ThongBao (LoaiThongBao, NoiDung, NgayDang, NguoiDang, TrangThai) VALUES (?, ?, GETDATE(), ?, ?)",
                    [$loaiTin, $noiDung, $username, $trangThai]
                );
                if ($stmt) {
                    $message = 'Đăng tin tức thành công!';
                    add_admin_notification($conn, 'news', 'Tin tức mới đã đăng', 'Bài tin [' . $loaiTin . '] đã được đăng lên trang chủ.', 'admin_tin_tuc.php');
                } else {
                    $message = 'Đăng tin thất bại (lỗi database).';
                    $messageTone = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            if ($maTin <= 0) {
                $message = 'ID tin không hợp lệ!';
                $messageTone = 'danger';
            } else {
                $stmt = @sqlsrv_query($conn, "DELETE FROM ThongBao WHERE MaThongBao = ?", [$maTin]);
                $message = $stmt ? 'Xóa tin thành công.' : 'Xóa thất bại.';
                $messageTone = $stmt ? 'success' : 'danger';
            }
        } elseif ($action === 'toggle') {
            if ($maTin <= 0) {
                $message = 'ID tin không hợp lệ!';
                $messageTone = 'danger';
            } else {
                // Đảo trạng thái: Hiển thị <-> Ẩn
                $stmt = @sqlsrv_query($conn, "UPDATE ThongBao SET TrangThai = CASE WHEN LOWER(TrangThai) = N'hiển thị' THEN N'Ẩn' ELSE N'Hiển thị' END WHERE MaThongBao = ?", [$maTin]);
                $message = $stmt ? 'Đổi trạng thái thành công.' : 'Cập nhật thất bại.';
                $messageTone = $stmt ? 'success' : 'danger';
            }
        }
    }
}

$news = [];
$newsStmt = @sqlsrv_query($conn, "SELECT MaThongBao, LoaiThongBao, NoiDung, NgayDang, NguoiDang, TrangThai FROM ThongBao ORDER BY NgayDang DESC, MaThongBao DESC");
if ($newsStmt) {
    while ($row = sqlsrv_fetch_array($newsStmt, SQLSRV_FETCH_ASSOC)) {
        $news[] = $row;
    }
}

require_once __DIR__ . '/../components/admin_layout.php';
admin_render_head('Đăng tin tức');
admin_render_shell_open($username);
admin_render_nav('tin_tuc_page_news');
admin_render_page_intro(
    'Đăng tin tức',
    'fa-newspaper',
    'Thêm thông báo,bảo trì, sửa chữa hay cập nhật tin tức cho trang chủ.'
);
?>
<?php if (!empty($message)): ?>
  <div class="admin-flash admin-flash-<?php echo $messageTone; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<section class="admin-card" style="margin-bottom: 16px;">
  <div class="admin-card-head">
    <div>
      <h2 class="admin-card-title"><i class="fas fa-feather"></i> Soạn bài tin tức</h2>
      <p class="admin-card-note">Cấu hình tiêu trạng là <code>Hiển thị</code> để tin có trên trang chủ. Một bài 5-6 đoạn. Riêng biệt mỗi ý có xuống dòng.</p>
    </div>
  </div>
  <div class="admin-card-body">
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="admin-form-grid">
        <div class="admin-field admin-col-4">
          <label for="loai_tin">Loại tin *</label>
          <select class="admin-input" name="loai_tin" required>
            <?php foreach ($loaiHopsLe as $loaiCoSan): ?>
              <option value="<?php echo htmlspecialchars($loaiCoSan); ?>"><?php echo htmlspecialchars($loaiCoSan); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field admin-col-3">
          <label for="trang_thai">Trạng thái *</label>
          <select class="admin-input" name="trang_thai" required>
            <option value="Hiển thị">Hiển thị</option>
            <option value="Ẩn">Ẩn</option>
          </select>
        </div>
        <div class="admin-field admin-col-5">
          <label for="nguoi_dang">Người đăng</label>
          <input class="admin-input" value="<?php echo htmlspecialchars($username); ?>" disabled>
        </div>
        <div class="admin-field admin-col-12">
          <label for="noi_dung">Nội dung bài tin *</label>
          <textarea class="admin-input admin-textarea" name="noi_dung" id="noi_dung" style="min-height: 120px;" placeholder="Nhập nội dung tin tức. Xuống dòng để tạo đoạn..." required></textarea>
        </div>
      </div>
      <div class="admin-actions" style="margin-top: 14px;">
        <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-paper-plane"></i> Đăng tin tức</button>
        <button type="reset" class="admin-btn admin-btn-soft"><i class="fas fa-rotate-left"></i> Xóa form</button>
      </div>
    </form>
  </div>
</section>

<section class="admin-card">
  <div class="admin-card-head">
    <div>
      <h2 class="admin-card-title"><i class="fas fa-list"></i> Danh sách tin tức (<?php echo count($news); ?>)</h2>
      <p class="admin-card-note">Tin 'Hiển thị' sẽ xuất hiện trên trang Tin tức; tin 'Ẩn' chỉ dành cho quản trị.</p>
    </div>
  </div>
  <div class="admin-card-body">
    <?php if (empty($news)): ?>
      <div class="admin-empty"><i class="fas fa-inbox" style="font-size: 30px; display: block; margin-bottom: 10px; color: #94a3b8;"></i>Chưa có tin tức nào.</div>
    <?php else: ?>
      <div class="admin-notification-feed">
      <?php foreach ($news as $tin): $id = (int) $tin['MaThongBao']; $isShow = strtolower(trim((string) $tin['TrangThai'])) === 'hiển thị'; ?>
        <article class="admin-notification-item" style="flex-wrap: wrap;">
          <div class="admin-notification-dot warn"><i class="fas fa-newspaper"></i></div>
          <div style="min-width: 0; flex: 1;">
            <div style="margin-bottom: 5px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <span class="admin-chip admin-chip-primary" style="padding: 3px 10px; font-size: 12px;">
                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($tin['LoaiThongBao']); ?>
              </span>
              <span class="admin-chip <?php echo $isShow ? 'admin-chip-success' : 'admin-chip-muted'; ?>" style="padding: 3px 9px; font-size: 12px;">
                <?php echo $isShow ? 'Hiển thị' : 'Ẩn'; ?>
              </span>
              <small style="color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($tin['NguoiDang']); ?> · <?php echo is_object($tin['NgayDang']) ? $tin['NgayDang']->format('d/m/Y') : ''; ?></small>
            </div>
            <div style="white-space: pre-wrap; word-break: break-word; font-size: 13.5px; color: #334155; max-height: 86px; overflow-y: auto;">
              <?php echo htmlspecialchars((string) $tin['NoiDung']); ?>
            </div>
          </div>
          <div style="display: flex; gap: 8px; flex-shrink: 0;">
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="ma_tin" value="<?php echo $id; ?>">
              <button type="submit" class="admin-btn admin-btn-soft" title="<?php echo $isShow ? 'Ẩn tin' : 'Hiện tin'; ?>"><i class="fas fa-eye<?php echo $isShow ? '-slash' : ''; ?>"></i></button>
            </form>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Xóa tin tức này?');">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ma_tin" value="<?php echo $id; ?>">
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
