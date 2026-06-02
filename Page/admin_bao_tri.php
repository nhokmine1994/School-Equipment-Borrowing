<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Quản lý thông báo bảo trì');

$message = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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

// Add new notification
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($title)) {
        $message = '<div class="admin-alert admin-alert-danger">Tiêu đề không được bỏ trống!</div>';
    } else {
        $addSql = "INSERT INTO BaoTriThongBao (TieuDe, NoiDung, TrangThai, ThuTuHienThi) 
                   VALUES (?, ?, 1, (SELECT ISNULL(MAX(ThuTuHienThi), 0) + 1 FROM BaoTriThongBao))";
        $params = [$title, $content];
        $addStmt = sqlsrv_query($conn, $addSql, $params);
        
        if ($addStmt !== false) {
            $message = '<div class="admin-alert admin-alert-success">Thêm thông báo thành công!</div>';
            // Refresh notifications list
            $notifications = [];
            $notificationStmt = sqlsrv_query($conn, $notificationSql);
            if ($notificationStmt !== false) {
                while ($row = sqlsrv_fetch_array($notificationStmt, SQLSRV_FETCH_ASSOC)) {
                    $notifications[] = $row;
                }
            }
        } else {
            $message = '<div class="admin-alert admin-alert-danger">Lỗi khi thêm thông báo!</div>';
        }
    }
}

// Update notification
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $display_order = (int) ($_POST['display_order'] ?? 0);
    
    if (empty($title)) {
        $message = '<div class="admin-alert admin-alert-danger">Tiêu đề không được bỏ trống!</div>';
    } elseif ($id <= 0) {
        $message = '<div class="admin-alert admin-alert-danger">ID thông báo không hợp lệ!</div>';
    } else {
        $updateSql = "UPDATE BaoTriThongBao SET TieuDe = ?, NoiDung = ?, ThuTuHienThi = ?, NgayCapNhat = GETDATE() WHERE MaBaoTri = ?";
        $params = [$title, $content, $display_order, $id];
        $updateStmt = sqlsrv_query($conn, $updateSql, $params);
        
        if ($updateStmt !== false) {
            $message = '<div class="admin-alert admin-alert-success">Cập nhật thông báo thành công!</div>';
            // Refresh notifications list
            $notifications = [];
            $notificationStmt = sqlsrv_query($conn, $notificationSql);
            if ($notificationStmt !== false) {
                while ($row = sqlsrv_fetch_array($notificationStmt, SQLSRV_FETCH_ASSOC)) {
                    $notifications[] = $row;
                }
            }
        } else {
            $message = '<div class="admin-alert admin-alert-danger">Lỗi khi cập nhật thông báo!</div>';
        }
    }
}

// Delete notification
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        $message = '<div class="admin-alert admin-alert-danger">ID thông báo không hợp lệ!</div>';
    } else {
        $deleteSql = "DELETE FROM BaoTriThongBao WHERE MaBaoTri = ?";
        $deleteStmt = sqlsrv_query($conn, $deleteSql, [$id]);
        
        if ($deleteStmt !== false) {
            $message = '<div class="admin-alert admin-alert-success">Xóa thông báo thành công!</div>';
            // Refresh notifications list
            $notifications = [];
            $notificationStmt = sqlsrv_query($conn, $notificationSql);
            if ($notificationStmt !== false) {
                while ($row = sqlsrv_fetch_array($notificationStmt, SQLSRV_FETCH_ASSOC)) {
                    $notifications[] = $row;
                }
            }
        } else {
            $message = '<div class="admin-alert admin-alert-danger">Lỗi khi xóa thông báo!</div>';
        }
    }
}

// Toggle notification status
if ($action === 'toggle_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $current_status = (int) ($_POST['current_status'] ?? 0);
    $new_status = $current_status === 1 ? 0 : 1;
    
    if ($id <= 0) {
        $message = '<div class="admin-alert admin-alert-danger">ID thông báo không hợp lệ!</div>';
    } else {
        $toggleSql = "UPDATE BaoTriThongBao SET TrangThai = ?, NgayCapNhat = GETDATE() WHERE MaBaoTri = ?";
        $toggleStmt = sqlsrv_query($conn, $toggleSql, [$new_status, $id]);
        
        if ($toggleStmt !== false) {
            $status_text = $new_status === 1 ? 'kích hoạt' : 'ẩn';
            $message = '<div class="admin-alert admin-alert-success">Thay đổi trạng thái thành công (' . $status_text . ')!</div>';
            // Refresh notifications list
            $notifications = [];
            $notificationStmt = sqlsrv_query($conn, $notificationSql);
            if ($notificationStmt !== false) {
                while ($row = sqlsrv_fetch_array($notificationStmt, SQLSRV_FETCH_ASSOC)) {
                    $notifications[] = $row;
                }
            }
        } else {
            $message = '<div class="admin-alert admin-alert-danger">Lỗi khi thay đổi trạng thái!</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Thông báo Bảo trì - SEB Admin</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-notification-form {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .admin-header {
            position: relative;
        }

        .admin-form-group {
            margin-bottom: 16px;
        }

        .admin-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .admin-form-group input,
        .admin-form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
        }

        .admin-form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .admin-form-group input:focus,
        .admin-form-group textarea:focus {
            outline: none;
            border-color: #0D8ABC;
            box-shadow: 0 0 0 2px rgba(13, 136, 188, 0.1);
        }

        .admin-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 768px) {
            .admin-form-row {
                grid-template-columns: 1fr;
            }
        }

        .admin-alert {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .admin-alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .admin-alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .admin-btn-group {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }

        .admin-btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .admin-btn-primary {
            background: #0D8ABC;
            color: #fff;
        }

        .admin-btn-primary:hover {
            background: #0a6a8f;
        }

        .admin-btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .admin-btn-secondary:hover {
            background: #e0e0e0;
        }

        .admin-btn-danger {
            background: #dc3545;
            color: #fff;
        }

        .admin-btn-danger:hover {
            background: #c82333;
        }

        .admin-notification-list {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .admin-compact-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .admin-compact-table thead {
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .admin-compact-table th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #333;
        }

        .admin-compact-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .admin-compact-table tbody tr:hover {
            background: #f9f9f9;
        }

        .admin-compact-table .title-col {
            color: #0D8ABC;
            font-weight: 500;
        }

        .admin-compact-table .action-col {
            text-align: right;
            white-space: nowrap;
        }

        .admin-notification-item {
            padding: 16px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .admin-notification-item:last-child {
            border-bottom: none;
        }

        .admin-notification-content {
            flex: 1;
        }

        .admin-notification-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .admin-notification-desc {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
        }

        .admin-notification-meta {
            font-size: 12px;
            color: #999;
        }

        .admin-notification-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .admin-btn-sm {
            padding: 8px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .admin-btn-edit {
            background: #0D8ABC;
            color: #fff;
        }

        .admin-btn-edit:hover {
            background: #0a6a8f;
        }

        .admin-btn-delete {
            background: #dc3545;
            color: #fff;
        }

        .admin-btn-delete:hover {
            background: #c82333;
        }

        .admin-btn-status {
            background: #6c757d;
            color: #fff;
        }

        .admin-btn-status:hover {
            background: #5a6268;
        }

        .admin-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .admin-badge-active {
            background: #d4edda;
            color: #155724;
        }

        .admin-badge-inactive {
            background: #f8f9fa;
            color: #666;
        }

        .admin-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .admin-modal.active {
            display: flex;
        }

        .admin-modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .admin-modal-close {
            float: right;
            cursor: pointer;
            font-size: 24px;
            color: #999;
        }

        .admin-modal-close:hover {
            color: #333;
        }

        .admin-modal-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            clear: both;
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <!-- Header -->
        <header class="admin-header">
            <h1><i class="fas fa-wrench"></i> Quản lý Thông báo Bảo trì</h1>
            <p>Cập nhật thông tin bảo trì, cập nhật thiết bị cho trang chủ</p>
            <div style="position: absolute; top: 16px; right: 20px;">
                <a href="admin_panel.php" class="admin-btn admin-btn-secondary" style="display: inline-block; font-size: 13px; padding: 8px 12px;">
                    <i class="fas fa-times"></i> Đóng
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="admin-main">
            <?php if (!empty($message)) echo $message; ?>

            <!-- Add/Edit Form -->
            <div class="admin-notification-form">
                <h3><i class="fas fa-plus-circle"></i> Thêm Thông báo Mới</h3>

                <form method="POST" style="margin-top: 16px;">
                    <input type="hidden" name="action" value="add">

                    <div class="admin-form-group">
                        <label for="title">Tiêu đề <span style="color: #dc3545;">*</span></label>
                        <input type="text" id="title" name="title" placeholder="VD: Phòng máy PM102 bảo trì định kỳ 20/12/2025" required>
                    </div>

                    <div class="admin-form-group">
                        <label for="content">Nội dung (Tùy chọn)</label>
                        <textarea id="content" name="content" placeholder="VD: Phòng máy PM102 tạm ngừng hoạt động để bảo trì toàn bộ hệ thống..."></textarea>
                    </div>

                    <div class="admin-btn-group">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i class="fas fa-check"></i> Thêm Thông báo
                        </button>
                        <button type="reset" class="admin-btn admin-btn-secondary">
                            <i class="fas fa-redo"></i> Xóa Form
                        </button>
                    </div>
                </form>
            </div>

            <!-- List of Notifications -->
            <div class="admin-notification-list">
                <div style="padding: 16px 20px; background: #f8f9fa; border-bottom: 1px solid #e0e0e0;">
                    <h3 style="margin: 0;"><i class="fas fa-list"></i> Danh sách Thông báo (<?php echo count($notifications); ?>)</h3>
                </div>

                <?php if (empty($notifications)): ?>
                <div style="padding: 40px 20px; text-align: center; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                    <p>Chưa có thông báo nào</p>
                </div>
                <?php else: ?>
                <table class="admin-compact-table">
                    <thead>
                        <tr>
                            <th style="width: 45%;">Tiêu đề</th>
                            <th style="width: 20%;">Trạng thái</th>
                            <th style="width: 15%;">Thứ tự</th>
                            <th style="width: 20%; text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td class="title-col">
                                <?php echo htmlspecialchars(substr($notification['TieuDe'], 0, 60), ENT_QUOTES, 'UTF-8'); ?><?php echo strlen($notification['TieuDe']) > 60 ? '...' : ''; ?>
                            </td>
                            <td>
                                <span class="admin-badge <?php echo $notification['TrangThai'] == 1 ? 'admin-badge-active' : 'admin-badge-inactive'; ?>">
                                    <?php echo $notification['TrangThai'] == 1 ? '✓ Hiện' : '✕ Ẩn'; ?>
                                </span>
                            </td>
                            <td>#<?php echo (int) $notification['ThuTuHienThi']; ?></td>
                            <td class="action-col">
                                <button class="admin-btn-sm admin-btn-edit" onclick="editNotification(<?php echo (int) $notification['MaBaoTri']; ?>, '<?php echo htmlspecialchars($notification['TieuDe'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($notification['NoiDung'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int) $notification['ThuTuHienThi']; ?>)" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo (int) $notification['MaBaoTri']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo (int) $notification['TrangThai']; ?>">
                                    <button type="submit" class="admin-btn-sm admin-btn-status" title="<?php echo $notification['TrangThai'] == 1 ? 'Ẩn' : 'Hiện'; ?>">
                                        <i class="fas fa-<?php echo $notification['TrangThai'] == 1 ? 'eye-slash' : 'eye'; ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa thông báo này?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $notification['MaBaoTri']; ?>">
                                    <button type="submit" class="admin-btn-sm admin-btn-delete" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Footer with Back Button -->
            <div style="margin-top: 32px; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;">
                <a href="admin_panel.php" class="admin-btn admin-btn-secondary" style="display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Quay lại Quản lý Admin
                </a>
            </div>
        </main>

        <!-- Edit Modal -->
        <div class="admin-modal" id="editModal">
            <div class="admin-modal-content">
                <span class="admin-modal-close" onclick="closeEditModal()">&times;</span>
                <div class="admin-modal-title">Chỉnh Sửa Thông báo</div>

                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="editId">

                    <div class="admin-form-group">
                        <label for="editTitle">Tiêu đề <span style="color: #dc3545;">*</span></label>
                        <input type="text" id="editTitle" name="title" required>
                    </div>

                    <div class="admin-form-group">
                        <label for="editContent">Nội dung (Tùy chọn)</label>
                        <textarea id="editContent" name="content"></textarea>
                    </div>

                    <div class="admin-form-group">
                        <label for="editOrder">Thứ tự Hiển thị</label>
                        <input type="number" id="editOrder" name="display_order" min="0" max="999">
                    </div>

                    <div class="admin-btn-group">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i class="fas fa-save"></i> Lưu Thay đổi
                        </button>
                        <button type="button" class="admin-btn admin-btn-secondary" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Hủy
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editNotification(id, title, content, displayOrder) {
            document.getElementById('editId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editContent').value = content;
            document.getElementById('editOrder').value = displayOrder;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        window.onclick = function (event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        };
    </script>
</body>

</html>
