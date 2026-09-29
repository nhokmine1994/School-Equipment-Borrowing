<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Quản lý người dùng');
require_once __DIR__ . '/../components/seb_db.php';
require_once __DIR__ . '/../components/mail_helper.php';

$message = '';
$messageType = 'success';
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $phone = trim($_POST['phone'] ?? '');
            $username = $phone;
            $password = (string) ($_POST['password'] ?? '');
            $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
            $role = trim($_POST['role'] ?? 'user');
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $boMon = trim($_POST['boMon'] ?? '');

            $passwordError = seb_validate_password_policy($password);
            if (!preg_match('/^0\d{9,10}$/', $phone)) {
                $message = 'Số điện thoại phải gồm 10 hoặc 11 chữ số và được dùng làm tài khoản.';
                $messageType = 'danger';
            } elseif ($passwordError !== '') {
                $message = $passwordError;
                $messageType = 'danger';
            } elseif ($password !== $passwordConfirm) {
                $message = 'Mật khẩu nhập lại không khớp.';
                $messageType = 'danger';
            } elseif ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Họ và tên và email hợp lệ là bắt buộc.';
                $messageType = 'danger';
            } elseif (!in_array($role, ['teacher', 'staff', 'user'], true)) {
                $message = 'Vai trò tài khoản không hợp lệ.';
                $messageType = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $mustChangePassword = 0;
                $accountActive = 1;
                $sql = "INSERT INTO TaiKhoan (TaiKhoan, MatKhau, LoaiTaiKhoan, HoVaTen, SoDienThoai, Email, BoMon, MustChangePassword, TaiKhoanActive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = array(&$username, &$hash, &$role, &$fullname, &$phone, &$email, &$boMon, &$mustChangePassword, &$accountActive);
                $stmt = sqlsrv_prepare($conn, $sql, $params);
                if ($stmt && sqlsrv_execute($stmt)) {
                    $message = 'Tạo tài khoản thành công.';
                    add_admin_notification($conn, 'new_user', 'Tài khoản mới', 'Đã tạo tài khoản ' . $username . ' (' . $role . ').', 'admin_users.php');
                } else {
                    $message = 'Tạo tài khoản thất bại (có thể username đã tồn tại).';
                    $messageType = 'danger';
                }
            }
        }

        if ($action === 'edit') {
            $username = trim($_POST['username'] ?? '');
            $role = trim($_POST['role'] ?? 'user');
            $fullname = trim($_POST['fullname'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $boMon = trim($_POST['boMon'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if ($username === '') {
                $message = 'Thiếu username để cập nhật.';
                $messageType = 'danger';
            } else {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE TaiKhoan SET LoaiTaiKhoan = ?, HoVaTen = ?, SoDienThoai = ?, Email = ?, BoMon = ?, MatKhau = ? WHERE TaiKhoan = ?";
                    $params = array(&$role, &$fullname, &$phone, &$email, &$boMon, &$hash, &$username);
                } else {
                    $sql = "UPDATE TaiKhoan SET LoaiTaiKhoan = ?, HoVaTen = ?, SoDienThoai = ?, Email = ?, BoMon = ? WHERE TaiKhoan = ?";
                    $params = array(&$role, &$fullname, &$phone, &$email, &$boMon, &$username);
                }

                $stmt = sqlsrv_prepare($conn, $sql, $params);
                if ($stmt && sqlsrv_execute($stmt)) {
                    $message = 'Cập nhật tài khoản thành công.';
                    add_admin_notification($conn, 'user', 'Tài khoản được cập nhật', 'Đã cập nhật tài khoản ' . $username . '.', 'admin_users.php');
                } else {
                    $message = 'Cập nhật tài khoản thất bại.';
                    $messageType = 'danger';
                    $errs = sqlsrv_errors();
                    if ($errs) {
                        $message .= ' SQLERR: ' . htmlspecialchars(print_r($errs, true));
                    }
                }
            }
        }

        if ($action === 'reset_password') {
          $message = 'Mật khẩu chỉ được cấp lại qua yêu cầu quên mật khẩu đã được xác minh.';
          $messageType = 'danger';
        }

        if ($action === 'toggle_active') {
            $username = trim($_POST['username'] ?? '');
            $active = ($_POST['active'] ?? '0') === '1' ? 1 : 0;
            if ($username === 'admin') {
                $message = 'Không thể vô hiệu hóa tài khoản admin gốc.';
                $messageType = 'danger';
            } else {
                $stmt = sqlsrv_query($conn, 'UPDATE TaiKhoan SET TaiKhoanActive = ? WHERE TaiKhoan = ?', [$active, $username]);
                $message = $stmt ? ($active ? 'Đã kích hoạt tài khoản.' : 'Đã vô hiệu hóa tài khoản.') : 'Cập nhật trạng thái tài khoản thất bại.';
                $messageType = $stmt ? 'success' : 'danger';
            }
        }

        if ($action === 'approve_reset' || $action === 'reject_reset') {
          $requestId = (int) ($_POST['reset_request_id'] ?? 0);
          $adminName = (string) ($_SESSION['user']['username'] ?? 'admin');
          if ($requestId <= 0) {
            $message = 'Thiếu mã yêu cầu quên mật khẩu.';
            $messageType = 'danger';
          } elseif ($action === 'reject_reset') {
            $stmt = sqlsrv_query($conn, "UPDATE MatKhauResetRequest SET TrangThai = N'rejected', AdminXuLy = ?, GhiChuAdmin = ?, NgayXuLy = SYSUTCDATETIME() WHERE MaYeuCau = ? AND TrangThai = N'pending'", [$adminName, trim((string) ($_POST['ghi_chu'] ?? '')), $requestId]);
            $message = $stmt ? 'Đã từ chối yêu cầu quên mật khẩu.' : 'Từ chối yêu cầu thất bại.';
            $messageType = $stmt ? 'success' : 'danger';
          } else {
            $requestStmt = sqlsrv_query($conn, "SELECT r.*, tk.HoVaTen FROM MatKhauResetRequest r INNER JOIN TaiKhoan tk ON tk.TaiKhoan = r.TaiKhoan WHERE r.MaYeuCau = ? AND r.TrangThai = N'pending'", [$requestId]);
            $request = $requestStmt ? sqlsrv_fetch_array($requestStmt, SQLSRV_FETCH_ASSOC) : null;
            if (!$request) {
              $message = 'Yêu cầu không tồn tại hoặc đã được xử lý.';
              $messageType = 'danger';
            } else {
              $temporaryPassword = seb_generate_temporary_password();
              $mailResult = seb_send_password_reset_email((string) $request['EmailXacThuc'], (string) ($request['HoVaTen'] ?? $request['TaiKhoan']), $temporaryPassword);
              if (empty($mailResult['ok'])) {
                $message = $mailResult['error'] ?? 'Không gửi được email, chưa cập nhật mật khẩu.';
                $messageType = 'danger';
              } else {
                $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
                sqlsrv_begin_transaction($conn);
                $updateUser = sqlsrv_query($conn, 'UPDATE TaiKhoan SET MatKhau = ?, MustChangePassword = 1 WHERE TaiKhoan = ?', [$hash, $request['TaiKhoan']]);
                $updateRequest = $updateUser ? sqlsrv_query($conn, "UPDATE MatKhauResetRequest SET TrangThai = N'approved', AdminXuLy = ?, NgayXuLy = SYSUTCDATETIME() WHERE MaYeuCau = ? AND TrangThai = N'pending'", [$adminName, $requestId]) : false;
                if ($updateUser && $updateRequest && sqlsrv_commit($conn)) {
                  $message = 'Đã xác minh và gửi mật khẩu tạm thời qua email.';
                  add_admin_notification($conn, 'password_reset', 'Đã duyệt quên mật khẩu', 'Đã xử lý yêu cầu của ' . $request['TaiKhoan'] . '.', 'admin_users.php');
                } else {
                  sqlsrv_rollback($conn);
                  $message = 'Không cập nhật được mật khẩu sau khi gửi email.';
                  $messageType = 'danger';
                }
              }
            }
          }
        }

        if ($action === 'approve_account') {
          $username = trim($_POST['username'] ?? '');
          if ($username === '') {
            $message = 'Thiếu username để duyệt.';
            $messageType = 'danger';
          } else {
            $sql = "UPDATE TaiKhoan SET LoaiTaiKhoan = 'user' WHERE TaiKhoan = ? AND LOWER(LTRIM(RTRIM(LoaiTaiKhoan))) = 'pending'";
            $params = array(&$username);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt && sqlsrv_execute($stmt)) {
              $message = 'Đã duyệt tài khoản thành công.';
              add_admin_notification($conn, 'new_user', 'Tài khoản đã duyệt', 'Đã duyệt tài khoản ' . $username . '.', 'admin_users.php');
            } else {
              $message = 'Duyệt tài khoản thất bại.';
              $messageType = 'danger';
            }
          }
        }

        if ($action === 'reject_account') {
          $username = trim($_POST['username'] ?? '');
          if ($username === '') {
            $message = 'Thiếu username để từ chối.';
            $messageType = 'danger';
          } else {
            $sql = "UPDATE TaiKhoan SET LoaiTaiKhoan = 'rejected' WHERE TaiKhoan = ? AND LOWER(LTRIM(RTRIM(LoaiTaiKhoan))) = 'pending'";
            $params = array(&$username);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt && sqlsrv_execute($stmt)) {
              $message = 'Đã từ chối tài khoản.';
              add_admin_notification($conn, 'new_user', 'Tài khoản bị từ chối', 'Đã từ chối tài khoản ' . $username . '.', 'admin_users.php');
            } else {
              $message = 'Từ chối tài khoản thất bại.';
              $messageType = 'danger';
            }
          }
        }

        if ($action === 'delete') {
            $username = trim($_POST['username'] ?? '');
            if ($username === 'admin') {
                $message = 'Không thể xóa tài khoản admin.';
                $messageType = 'danger';
            } else {
                $sql = "DELETE FROM TaiKhoan WHERE TaiKhoan = ?";
                $params = array(&$username);
                $stmt = sqlsrv_prepare($conn, $sql, $params);
                if ($stmt && sqlsrv_execute($stmt)) {
                    $message = 'Xóa tài khoản thành công.';
                } else {
                    $message = 'Không thể xóa tài khoản vì có dữ liệu liên quan (thường là lịch sử mượn). Hãy dùng Vô hiệu hóa.';
                    $messageType = 'danger';
                }
            }
        }
    }
}

$users = [];
$sql = "SELECT TaiKhoan, LoaiTaiKhoan, HoVaTen, SoDienThoai, Email, BoMon, TaiKhoanActive
  FROM TaiKhoan
  ORDER BY CASE WHEN LOWER(LTRIM(RTRIM(LoaiTaiKhoan))) = 'pending' THEN 0 ELSE 1 END,
     CASE WHEN LOWER(LTRIM(RTRIM(LoaiTaiKhoan))) = 'rejected' THEN 2 ELSE 1 END,
     TaiKhoan ASC";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $users[] = $row;
    }
}

// Load subjects list from bảng tra cứu dbo.BoMon (nguồn trung tâm, fallback TaiKhoan.BoMon trong helper)
require_once __DIR__ . '/../components/seb_db.php';
$subjects = function_exists('seb_fetch_bo_mon_list') ? seb_fetch_bo_mon_list($conn) : [];
if (empty($subjects)) {
  $subjects = ['Tin học', 'Toán', 'Vật lý', 'Hóa học', 'Sinh học', 'Ngữ văn'];
}

$resetRequests = [];
$resetStmt = sqlsrv_query($conn, "SELECT r.MaYeuCau, r.TaiKhoan, r.EmailXacThuc, r.SoDienThoai, r.NgayTao, tk.HoVaTen FROM MatKhauResetRequest r LEFT JOIN TaiKhoan tk ON tk.TaiKhoan = r.TaiKhoan WHERE r.TrangThai = N'pending' ORDER BY r.NgayTao ASC");
if ($resetStmt) {
  while ($row = sqlsrv_fetch_array($resetStmt, SQLSRV_FETCH_ASSOC)) {
    $resetRequests[] = $row;
  }
}

$totalUsers = count($users);
$pendingUsers = count(array_filter($users, function ($item) {
  return strtolower(trim((string) ($item['LoaiTaiKhoan'] ?? ''))) === 'pending';
}));
$rejectedUsers = count(array_filter($users, function ($item) {
  return strtolower(trim((string) ($item['LoaiTaiKhoan'] ?? ''))) === 'rejected';
}));
$adminCount = count(array_filter($users, function ($item) {
    return strtolower((string) ($item['LoaiTaiKhoan'] ?? '')) === 'admin';
}));
$teacherCount = count(array_filter($users, function ($item) {
    return strtolower((string) ($item['LoaiTaiKhoan'] ?? '')) === 'teacher';
}));
$studentCount = count(array_filter($users, function ($item) {
  return strtolower((string) ($item['LoaiTaiKhoan'] ?? '')) === 'user';
}));

require_once __DIR__ . '/../components/admin_layout.php';
$adminUsername = $_SESSION['user']['username'] ?? '';
admin_render_head('Quản lý người dùng');
admin_render_shell_open($adminUsername);
admin_render_nav('users');
admin_render_page_intro(
    'Quản lý người dùng',
    'fa-users',
    'Xem toàn bộ tài khoản hiện có, tạo mới ở mục riêng bên phải.'
);
?>
    <section class="admin-grid-4">
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Tổng tài khoản</p>
        <p class="admin-stat-value"><?php echo $totalUsers; ?></p>
        <p class="admin-stat-desc">Tất cả người dùng hiện có trong hệ thống.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Quản trị viên</p>
        <p class="admin-stat-value"><?php echo $adminCount; ?></p>
        <p class="admin-stat-desc">Tài khoản có quyền admin.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Giáo viên</p>
        <p class="admin-stat-value"><?php echo $teacherCount; ?></p>
        <p class="admin-stat-desc">Tài khoản vai trò teacher.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Người dùng thường</p>
        <p class="admin-stat-value"><?php echo $studentCount; ?></p>
        <p class="admin-stat-desc">Các tài khoản vai trò user.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Chờ duyệt</p>
        <p class="admin-stat-value"><?php echo $pendingUsers; ?></p>
        <p class="admin-stat-desc">Tài khoản mới đăng ký chưa được duyệt.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Bị từ chối</p>
        <p class="admin-stat-value"><?php echo $rejectedUsers; ?></p>
        <p class="admin-stat-desc">Tài khoản mới không được chấp thuận.</p>
      </article>
    </section>

    <?php if ($message): ?>
      <div class="admin-flash <?php echo $messageType === 'danger' ? 'admin-flash-danger' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-card" style="margin-top:16px;">
      <div class="admin-card-head">
        <div>
          <h2 class="admin-card-title"><i class="fas fa-key"></i> Duyệt quên mật khẩu</h2>
          <p class="admin-card-note">Xác minh số điện thoại và email trước khi hệ thống sinh mật khẩu tạm thời gửi qua email.</p>
        </div>
      </div>
      <div class="admin-card-body">
        <?php if (empty($resetRequests)): ?>
          <div class="admin-empty">Không có yêu cầu quên mật khẩu đang chờ.</div>
        <?php else: ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Tài khoản</th><th>Họ tên</th><th>Số điện thoại</th><th>Email</th><th>Ngày gửi</th><th>Hành động</th></tr></thead>
              <tbody>
              <?php foreach ($resetRequests as $request):
                $resetCreatedAt = $request['NgayTao'] ?? null;
                $resetCreatedAtText = is_object($resetCreatedAt) && method_exists($resetCreatedAt, 'format')
                  ? $resetCreatedAt->format('d/m/Y H:i')
                  : (string) $resetCreatedAt;
              ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) $request['TaiKhoan']); ?></td>
                  <td><?php echo htmlspecialchars((string) ($request['HoVaTen'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars((string) $request['SoDienThoai']); ?></td>
                  <td><?php echo htmlspecialchars((string) $request['EmailXacThuc']); ?></td>
                  <td><?php echo htmlspecialchars($resetCreatedAtText, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <form method="post" class="reset-request-actions" style="display:flex;gap:8px;align-items:center;white-space:nowrap;min-width:230px;">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                      <input type="hidden" name="reset_request_id" value="<?php echo (int) $request['MaYeuCau']; ?>">
                      <button class="admin-btn admin-btn-success" type="submit" name="action" value="approve_reset">Duyệt &amp; gửi email</button>
                      <button class="admin-btn admin-btn-danger" type="submit" name="action" value="reject_reset">Từ chối</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

        <style>
          .admin-device-modal {
            position: fixed;
            inset: 0;
            z-index: 14000;
            display: none;
            align-items: center;
            justify-content: center;
            pointer-events: none;
          }

          .admin-device-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(3px);
          }

          .admin-device-modal-panel {
            position: relative;
            width: 100%;
            max-width: 760px;
            margin: 20px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
            overflow: hidden;
            pointer-events: auto;
          }

          .admin-device-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            background: #f8fbff;
            border-bottom: 1px solid #dbe7f3;
          }

          .admin-device-modal-body {
            padding: 20px;
            max-height: 75vh;
            overflow: auto;
          }
        </style>

    <div class="admin-layout" style="margin-top:16px;">
      <section class="admin-card">
        <div class="admin-card-head">
          <div>
            <h2 class="admin-card-title">Danh sách người dùng</h2>
            <p class="admin-card-note">Xem toàn bộ tài khoản hiện có và xóa nếu cần.</p>
          </div>
        </div>
        <div class="admin-card-body">
          <?php if (empty($users)): ?>
            <div class="admin-empty">Chưa có người dùng nào.</div>
          <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Username</th>
                    <th>Họ tên</th>
                    <th>SĐT</th>
                    <th>Email</th>
                    <th>Môn học</th>
                    <th>Role</th>
                    <th>Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u):
                    $username = htmlspecialchars($u['TaiKhoan'] ?? '');
                    $fullname = htmlspecialchars($u['HoVaTen'] ?? '');
                    $phone = htmlspecialchars($u['SoDienThoai'] ?? '');
                    $email = htmlspecialchars($u['Email'] ?? '');
                    $subject = htmlspecialchars($u['BoMon'] ?? '');
                     $role = strtolower(trim((string) ($u['LoaiTaiKhoan'] ?? 'user')));
                     $accountActive = (int) ($u['TaiKhoanActive'] ?? 1) === 1;
                    $chipClass = 'admin-chip-primary';
                    if ($role === 'admin') {
                        $chipClass = 'admin-chip-danger';
                    } elseif ($role === 'teacher') {
                        $chipClass = 'admin-chip-warning';
                    } elseif ($role === 'pending') {
                      $chipClass = 'admin-chip-warning';
                    } elseif ($role === 'rejected') {
                      $chipClass = 'admin-chip-danger';
                    } else {
                        $chipClass = 'admin-chip-success';
                    }
                    $editPayload = [
                        'username' => (string) ($u['TaiKhoan'] ?? ''),
                        'fullname' => (string) ($u['HoVaTen'] ?? ''),
                        'phone' => (string) ($u['SoDienThoai'] ?? ''),
                        'email' => (string) ($u['Email'] ?? ''),
                        'boMon' => (string) ($u['BoMon'] ?? ''),
                        'role' => (string) ($u['LoaiTaiKhoan'] ?? 'user'),
                    ];
                    $editPayloadJson = htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                  ?>
                  <tr class="user-item" onclick='openUserEditModal(<?php echo $editPayloadJson; ?>)' style="cursor:pointer;">
                    <td><strong><?php echo $username; ?></strong></td>
                    <td><?php echo $fullname ?: '<span style="color:#7890a6">Chưa có</span>'; ?></td>
                    <td><?php echo $phone ?: '<span style="color:#7890a6">Chưa có</span>'; ?></td>
                    <td><?php echo $email ?: '<span style="color:#7890a6">Chưa có</span>'; ?></td>
                    <td><?php echo $subject ?: '<span style="color:#7890a6">Chưa có</span>'; ?></td>
                     <td><span class="admin-chip <?php echo $chipClass; ?>"><?php echo htmlspecialchars($role); ?></span><br><span class="admin-chip <?php echo $accountActive ? 'admin-chip-success' : 'admin-chip-danger'; ?>" style="margin-top:4px;"><?php echo $accountActive ? 'Hoạt động' : 'Vô hiệu hóa'; ?></span></td>
                    <td>
                      <button class="admin-btn admin-btn-soft" type="button" onclick="event.stopPropagation(); openUserEditModal(<?php echo $editPayloadJson; ?>)">Sửa</button>
                      <?php if ($role === 'pending'): ?>
                        <form method="post" onsubmit="return confirm('Duyệt tài khoản này?')" style="display:inline-block;margin-left:8px;">
                          <input type="hidden" name="action" value="approve_account">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                          <input type="hidden" name="username" value="<?php echo $username; ?>">
                          <button class="admin-btn admin-btn-success" type="submit">Duyệt</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Từ chối tài khoản này?')" style="display:inline-block;margin-left:8px;">
                          <input type="hidden" name="action" value="reject_account">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                          <input type="hidden" name="username" value="<?php echo $username; ?>">
                          <button class="admin-btn admin-btn-danger" type="submit">Từ chối</button>
                        </form>
                      <?php endif; ?>
                       <?php if ($username !== 'admin'): ?>
                         <form method="post" style="display:inline-block;margin-left:8px;">
                           <input type="hidden" name="action" value="toggle_active">
                           <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                           <input type="hidden" name="username" value="<?php echo $username; ?>">
                           <input type="hidden" name="active" value="<?php echo $accountActive ? '0' : '1'; ?>">
                           <button class="admin-btn <?php echo $accountActive ? 'admin-btn-warning' : 'admin-btn-success'; ?>" type="submit"><?php echo $accountActive ? 'Vô hiệu hóa' : 'Kích hoạt'; ?></button>
                         </form>
                        <form method="post" onsubmit="return confirm('Xóa tài khoản này?')" style="display:inline-block;margin-left:8px;">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                          <input type="hidden" name="username" value="<?php echo $username; ?>">
                          <button class="admin-btn admin-btn-danger" type="submit">Xóa</button>
                        </form>
                      <?php else: ?>
                        <span class="admin-chip admin-chip-primary">Tài khoản gốc</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <aside>
        <section class="admin-card">
          <div class="admin-card-head">
            <div>
              <h2 class="admin-card-title">Tạo tài khoản mới</h2>
              <p class="admin-card-note">Phần tạo tài khoản nằm riêng, không lẫn với danh sách.</p>
            </div>
          </div>
          <div class="admin-card-body">
            <form method="post">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

              <div class="admin-form-grid">
                <div class="admin-field admin-col-12">
                  <label>Số điện thoại / tài khoản</label>
                  <input class="admin-input" name="phone" type="tel" inputmode="numeric" pattern="0[0-9]{9,10}" placeholder="0xxxxxxxxx" required>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Mật khẩu</label>
                  <input class="admin-input" name="password" type="password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}" required>
                  <small>Ít nhất 8 ký tự, gồm chữ hoa, số và ký tự đặc biệt.</small>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Nhập lại mật khẩu</label>
                  <input class="admin-input" name="password_confirm" type="password" minlength="8" required>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Họ và tên</label>
                  <input class="admin-input" name="fullname" required>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Email</label>
                  <input class="admin-input" name="email" type="email" required>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Môn học</label>
                  <select class="admin-select" name="boMon">
                    <option value="">-- Chọn môn học --</option>
                    <?php foreach ($subjects as $s): ?>
                      <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Role</label>
                  <select class="admin-select" name="role">
                    <option value="teacher">Giáo viên</option>
                    <option value="staff">Nhân viên</option>
                    <option value="user">Người dùng khác</option>
                  </select>
                </div>
              </div>

              <div class="admin-actions" style="margin-top:12px;">
                <button class="admin-btn admin-btn-primary" type="submit">Tạo tài khoản</button>
              </div>
            </form>
          </div>
        </section>
      </aside>
    </div>

    <div id="userEditModal" class="admin-device-modal" style="display:none;">
      <div class="admin-device-modal-backdrop" onclick="closeUserEditModal()"></div>
      <div class="admin-device-modal-panel" role="dialog" aria-modal="true" aria-labelledby="userEditModalTitle" style="max-width:760px;">
        <div class="admin-device-modal-head">
          <div>
            <h2 id="userEditModalTitle" class="admin-card-title">Sửa nhanh người dùng</h2>
            <p class="admin-card-note">Nhấn vào một người dùng để chỉnh sửa nhanh.</p>
          </div>
          <button type="button" class="admin-btn-sm admin-btn-edit" onclick="closeUserEditModal()" aria-label="Đóng">&times;</button>
        </div>
        <div class="admin-device-modal-body">
          <form method="post" id="userEditForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="username" id="editUsername">

            <div class="admin-form-grid">
              <div class="admin-field admin-col-4">
                <label>Username</label>
                <input class="admin-input" id="editUsernameDisplay" disabled>
              </div>
              <div class="admin-field admin-col-8">
                <label>Họ tên</label>
                <input class="admin-input" name="fullname" id="editFullname">
              </div>
              <div class="admin-field admin-col-4">
                <label>SĐT</label>
                <input class="admin-input" name="phone" id="editPhone">
              </div>
                <div class="admin-field admin-col-4">
                  <label>Email</label>
                  <input class="admin-input" name="email" id="editEmail" type="email">
                </div>
                <div class="admin-field admin-col-4">
                  <label>Môn học</label>
                  <select class="admin-input" name="boMon" id="editBoMon">
                    <option value="">-- Chọn môn học --</option>
                    <?php foreach ($subjects as $s): ?>
                      <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="admin-field admin-col-4">
                  <label>Role</label>
                  <select class="admin-input" name="role" id="editRole">
                   <option value="pending">pending</option>
                   <option value="user">user</option>
                   <option value="teacher">teacher</option>
                   <option value="staff">staff</option>
                   <option value="admin">admin</option>
                  </select>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Mật khẩu mới</label>
                  <input class="admin-input" type="password" name="password" id="editPassword" placeholder="Để trống nếu không đổi mật khẩu">
                </div>
            </div>

            <div class="admin-actions" style="margin-top: 14px; justify-content: flex-end;">
              <button type="button" class="admin-btn admin-btn-soft" onclick="closeUserEditModal()">Hủy</button>
              <button type="submit" class="admin-btn admin-btn-primary">Lưu thay đổi</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
      const userEditModal = document.getElementById('userEditModal');
      const editUsername = document.getElementById('editUsername');
      const editUsernameDisplay = document.getElementById('editUsernameDisplay');
      const editFullname = document.getElementById('editFullname');
      const editPhone = document.getElementById('editPhone');
      const editEmail = document.getElementById('editEmail');
      const editBoMon = document.getElementById('editBoMon');
      const editRole = document.getElementById('editRole');
      const editPassword = document.getElementById('editPassword');

      function openUserEditModal(user) {
        if (!userEditModal || !user) {
          return;
        }

        if (editUsername) editUsername.value = user.username || '';
        if (editUsernameDisplay) editUsernameDisplay.value = user.username || '';
        if (editFullname) editFullname.value = user.fullname || '';
        if (editPhone) editPhone.value = user.phone || '';
        if (editEmail) editEmail.value = user.email || '';
        if (editBoMon) editBoMon.value = user.boMon || '';
        if (editRole) editRole.value = user.role || 'user';
        if (editPassword) editPassword.value = '';

        userEditModal.style.display = 'flex';
        userEditModal.style.pointerEvents = 'auto';
      }

      function closeUserEditModal() {
        if (!userEditModal) {
          return;
        }

        userEditModal.style.display = 'none';
        userEditModal.style.pointerEvents = 'none';
      }

      window.openUserEditModal = openUserEditModal;
      window.closeUserEditModal = closeUserEditModal;

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeUserEditModal();
        }
      });
    </script>
<?php admin_render_shell_close(); ?>
