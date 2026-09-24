<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Quản lý thông báo bảo trì');

$username = $_SESSION['user']['username'] ?? '';
$message = '';
$messageTone = 'success';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$csrf_token = generate_csrf_token();

// Get all maintenance notifications
$notifications = [];
$notificationSql = "SELECT MaBaoTri, TieuDe, NoiDung, NgayTao, NgayCapNhat, TrangThai, ThuTuHienThi 
                    FROM BaoTriThongBao 
                    ORDER BY ThuTuHienThi ASC, NgayCapNhat DESC";
$notificationStmt = sqlsrv_query($conn, $notificationSql);
if ($notificationStmt !== false) {
    while ($row = sqlsrv_fetch_array($notificationStmt, SQLSRV_FETCH_ASSOC)) {
        $notifications[] = $row;
    }
}

// Refresh helper
function bao_tri_refresh_notifications($conn, $notificationSql, &$notifications)
{
    $notifications = [];
    $stmt = sqlsrv_query($conn, $notificationSql);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $notifications[] = $row;
        }
    }
}

// Add new notification
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại!';
        $messageTone = 'danger';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '') {
            $message = 'Tiêu đề không được bỏ trống!';
            $messageTone = 'danger';
        } else {
            $addSql = "INSERT INTO BaoTriThongBao (TieuDe, NoiDung, TrangThai, ThuTuHienThi) 
                       VALUES (?, ?, 1, (SELECT ISNULL(MAX(ThuTuHienThi), 0) + 1 FROM BaoTriThongBao))";
            $addStmt = sqlsrv_query($conn, $addSql, [$title, $content]);

            if ($addStmt !== false) {
                $message = 'Thêm thông báo thành công!';
                bao_tri_refresh_notifications($conn, $notificationSql, $notifications);
            } else {
                $message = 'Lỗi khi thêm thông báo!';
                $messageTone = 'danger';
            }
        }
    }
}

// Update notification
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại!';
        $messageTone = 'danger';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $display_order = (int) ($_POST['display_order'] ?? 0);

        if ($title === '') {
            $message = 'Tiêu đề không được bỏ trống!';
            $messageTone = 'danger';
        } elseif ($id <= 0) {
            $message = 'ID thông báo không hợp lệ!';
            $messageTone = 'danger';
        } else {
            $updateSql = "UPDATE BaoTriThongBao SET TieuDe = ?, NoiDung = ?, ThuTuHienThi = ?, NgayCapNhat = GETDATE() WHERE MaBaoTri = ?";
            $updateStmt = sqlsrv_query($conn, $updateSql, [$title, $content, $display_order, $id]);

            if ($updateStmt !== false) {
                $message = 'Cập nhật thông báo thành công!';
                bao_tri_refresh_notifications($conn, $notificationSql, $notifications);
            } else {
                $message = 'Lỗi khi cập nhật thông báo!';
                $messageTone = 'danger';
            }
        }
    }
}

// Delete notification
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại!';
        $messageTone = 'danger';
    } else {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $message = 'ID thông báo không hợp lệ!';
            $messageTone = 'danger';
        } else {
            $deleteStmt = sqlsrv_query($conn, "DELETE FROM BaoTriThongBao WHERE MaBaoTri = ?", [$id]);

            if ($deleteStmt !== false) {
                $message = 'Xóa thông báo thành công!';
                bao_tri_refresh_notifications($conn, $notificationSql, $notifications);
            } else {
                $message = 'Lỗi khi xóa thông báo!';
                $messageTone = 'danger';
            }
        }
    }
}

// Toggle notification status
if ($action === 'toggle_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại!';
        $messageTone = 'danger';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $current_status = (int) ($_POST['current_status'] ?? 0);
        $new_status = $current_status === 1 ? 0 : 1;

        if ($id <= 0) {
            $message = 'ID thông báo không hợp lệ!';
            $messageTone = 'danger';
        } else {
            $toggleStmt = sqlsrv_query($conn, "UPDATE BaoTriThongBao SET TrangThai = ?, NgayCapNhat = GETDATE() WHERE MaBaoTri = ?", [$new_status, $id]);

            if ($toggleStmt !== false) {
                $status_text = $new_status === 1 ? 'hiện' : 'ẩn';
                $message = 'Thay đổi trạng thái thành công (' . $status_text . ')!';
                bao_tri_refresh_notifications($conn, $notificationSql, $notifications);
            } else {
                $message = 'Lỗi khi thay đổi trạng thái!';
                $messageTone = 'danger';
            }
        }
    }
}

function bao_tri_human_date($value)
{
    if (!$value) {
        return '—';
    }
    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('d/m/Y H:i');
    }
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : (string) $value;
}

require_once __DIR__ . '/../components/admin_layout.php';
admin_render_head('Quản lý bảo trì');
admin_render_shell_open($username);
admin_render_nav('maintenance');
admin_render_page_intro(
    'Quản lý thông báo bảo trì',
    'fa-hammer',
    'Thêm, chỉnh sửa và ẩn/hiện thông báo bảo trì hiển thị trên trang chủ.'
);
?>
<?php if (!empty($message)): ?>
  <div class="admin-flash admin-flash-<?php echo $messageTone === 'danger' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<section class="admin-card" style="margin-bottom: 16px;">
  <div class="admin-card-head">
    <div>
      <h2 class="admin-card-title"><i class="fas fa-circle-plus"></i> Thêm thông báo mới</h2>
      <p class="admin-card-note">Thông báo sẽ hiển thị ngay tại mục "Thông tin bảo trì/cập nhật" của trang chủ.</p>
    </div>
  </div>
  <div class="admin-card-body">
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="admin-form-grid">
        <div class="admin-field admin-col-8">
          <label for="title">Tiêu đề <span style="color: var(--admin-danger);">*</span></label>
          <input class="admin-input" id="title" name="title" placeholder="VD: Phòng máy PM102 bảo trì định kỳ 20/12/2025" required>
        </div>
        <div class="admin-field admin-col-4">
          <label for="content">Nội dung (tùy chọn)</label>
          <input class="admin-input" id="content" name="content" placeholder="VD: PM102 tạm ngừng hoạt động để bảo trì...">
        </div>
      </div>
      <div class="admin-actions" style="margin-top: 14px;">
        <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-check"></i> Thêm thông báo</button>
        <button type="reset" class="admin-btn admin-btn-soft"><i class="fas fa-rotate-left"></i> Xóa form</button>
      </div>
    </form>
  </div>
</section>

<section class="admin-card">
  <div class="admin-card-head">
    <div>
      <h2 class="admin-card-title"><i class="fas fa-list"></i> Danh sách thông báo (<?php echo count($notifications); ?>)</h2>
      <p class="admin-card-note">Bật/tắt hiển thị, sắp xếp thứ tự hoặc xóa thông báo cũ.</p>
    </div>
  </div>
  <div class="admin-card-body">
    <?php if (empty($notifications)): ?>
      <div class="admin-empty"><i class="fas fa-inbox" style="font-size: 30px; display: block; margin-bottom: 10px; color: #94a3b8;"></i>Chưa có thông báo bảo trì nào.</div>
    <?php else: ?>
      <div class="admin-notification-feed">
        <?php foreach ($notifications as $notification): $id = (int) $notification['MaBaoTri']; $active = (int) $notification['TrangThai'] === 1; ?>
          <div class="admin-notification-item" style="flex-wrap: wrap;">
            <div class="admin-notification-dot <?php echo $active ? 'warn' : 'user'; ?>" title="<?php echo $active ? 'Đang hiện' : 'Đang ẩn'; ?>"><i class="fas fa-<?php echo $active ? 'screwdriver-wrench' : 'eye-slash'; ?>"></i></div>
            <div style="min-width: 0; flex: 1;">
              <p class="admin-notification-title"><?php echo htmlspecialchars($notification['TieuDe']); ?></p>
              <?php $nd = trim((string) ($notification['NoiDung'] ?? '')); if ($nd !== ''): ?>
              <p class="admin-notification-text"><?php echo htmlspecialchars($nd); ?></p>
              <?php endif; ?>
              <div class="admin-notification-time">
                Thứ tự #<?php echo (int) $notification['ThuTuHienThi']; ?>
                · Cập nhật: <?php echo bao_tri_human_date($notification['NgayCapNhat'] ?? null); ?>
                <span class="admin-chip <?php echo $active ? 'admin-chip-success' : 'admin-chip-muted'; ?>" style="margin-left: 8px; padding: 2px 8px;"><?php echo $active ? '✓ Đang hiện' : '✕ Đang ẩn'; ?></span>
              </div>
            </div>
            <div style="display: flex; gap: 8px; flex-shrink: 0;">
              <button
                type="button"
                class="admin-btn admin-btn-soft"
                title="Sửa"
                onclick='editNotification(<?php echo $id; ?>, <?php echo htmlspecialchars(json_encode(["title" => $notification['TieuDe'], "content" => $notification['NoiDung'] ?? "", "order" => (int) $notification['ThuTuHienThi']], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>, <?php echo (int) $notification['ThuTuHienThi']; ?>)'
              ><i class="fas fa-pen"></i></button>
              <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="current_status" value="<?php echo (int) $notification['TrangThai']; ?>">
                <button type="submit" class="admin-btn admin-btn-soft" title="<?php echo $active ? 'Ẩn thông báo' : 'Hiện thông báo'; ?>"><i class="fas fa-<?php echo $active ? 'eye-slash' : 'eye'; ?>"></i></button>
              </form>
              <form method="POST" style="margin: 0;" onsubmit="return confirm('Xóa thông báo này?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <button type="submit" class="admin-btn admin-btn-danger" title="Xóa"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="admin-modal" id="editModal">
  <div class="admin-modal-content admin-card">
    <span class="admin-modal-close" onclick="closeEditModal()">&times;</span>
    <div class="admin-modal-title"><i class="fas fa-pen"></i> Chỉnh sửa thông báo</div>
    <form method="POST" id="editForm">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <input type="hidden" name="id" id="editId">
      <div class="admin-field">
        <label for="editTitle">Tiêu đề <span style="color: var(--admin-danger);">*</span></label>
        <input class="admin-input" type="text" id="editTitle" name="title" required>
      </div>
      <div class="admin-field" style="margin-top: 12px;">
        <label for="editContent">Nội dung (tùy chọn)</label>
        <textarea class="admin-input admin-textarea" id="editContent" name="content"></textarea>
      </div>
      <div class="admin-field" style="margin-top: 12px;">
        <label for="editOrder">Thứ tự hiển thị</label>
        <input class="admin-input" type="number" id="editOrder" name="display_order" min="0" max="999">
      </div>
      <div class="admin-actions" style="margin-top: 16px;">
        <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-floppy-disk"></i> Lưu thay đổi</button>
        <button type="button" class="admin-btn admin-btn-soft" onclick="closeEditModal()"><i class="fas fa-xmark"></i> Hủy</button>
      </div>
    </form>
  </div>
</div>

<style>
  .admin-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    z-index: 12000;
    align-items: center;
    justify-content: center;
  }

  .admin-modal.active {
    display: flex;
  }

  .admin-modal-content {
    width: 90%;
    max-width: 560px;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
  }

  .admin-modal-close {
    position: absolute;
    top: 10px;
    right: 16px;
    font-size: 22px;
    color: #94a3b8;
    cursor: pointer;
  }

  .admin-modal-close:hover {
    color: var(--admin-danger);
  }
</style>

<script>
  function editNotification(id, title, content, displayOrder) {
    document.getElementById('editId').value = id;
    document.getElementById('editTitle').value = typeof title === 'string' ? title : (title && title.title) || '';
    document.getElementById('editContent').value = typeof title === 'object' && title !== null ? (title.content || '') : (content || '');
    var order = typeof title === 'object' && title !== null ? (title.order ?? displayOrder) : displayOrder;
    document.getElementById('editOrder').value = order;
    document.getElementById('editModal').classList.add('active');
  }

  function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
  }

  window.addEventListener('click', function (event) {
    var modal = document.getElementById('editModal');
    if (event.target === modal) {
      modal.classList.remove('active');
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      document.getElementById('editModal').classList.remove('active');
    }
  });
</script>
<?php admin_render_shell_close(); ?>
