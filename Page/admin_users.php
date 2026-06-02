<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Quản lý người dùng');

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
            $username = trim($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $role = trim($_POST['role'] ?? 'user');
            $fullname = trim($_POST['fullname'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $boMon = trim($_POST['boMon'] ?? '');

            if ($username === '' || $password === '') {
                $message = 'Vui lòng nhập username và password.';
                $messageType = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO TaiKhoan (TaiKhoan, MatKhau, LoaiTaiKhoan, HoVaTen, SoDienThoai, Email, BoMon) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $params = array(&$username, &$hash, &$role, &$fullname, &$phone, &$email, &$boMon);
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
          $username = trim($_POST['username'] ?? '');
          if ($username === '') {
            $message = 'Thiếu username để reset mật khẩu.';
            $messageType = 'danger';
          } else {
            try {
              $temp = bin2hex(random_bytes(4));
            } catch (Exception $e) {
              $temp = substr(md5(uniqid('', true)), 0, 8);
            }
            $hash = password_hash($temp, PASSWORD_DEFAULT);
            $sql = "UPDATE TaiKhoan SET MatKhau = ? WHERE TaiKhoan = ?";
            $params = array(&$hash, &$username);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt && sqlsrv_execute($stmt)) {
              $message = 'Đã reset mật khẩu tạm cho ' . $username . '. Mật khẩu tạm thời: ' . $temp;
              add_admin_notification($conn, 'user', 'Reset mật khẩu', 'Đã reset mật khẩu cho ' . $username . '.', 'admin_users.php');
            } else {
              $message = 'Reset mật khẩu thất bại.';
              $messageType = 'danger';
              $errs = sqlsrv_errors();
              if ($errs) {
                $message .= ' SQLERR: ' . htmlspecialchars(print_r($errs, true));
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
                    $message = 'Xóa thất bại.';
                    $messageType = 'danger';
                }
            }
        }
    }
}

$users = [];
$sql = "SELECT TaiKhoan, LoaiTaiKhoan, HoVaTen, SoDienThoai, Email, BoMon
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

// Load subjects list from TaiKhoan.BoMon to populate dropdown (fallback to common subjects)
$subjects = [];
$sstmt = sqlsrv_query($conn, "SELECT DISTINCT BoMon FROM TaiKhoan WHERE BoMon IS NOT NULL AND BoMon <> '' ORDER BY BoMon ASC");
if ($sstmt) {
  while ($r = sqlsrv_fetch_array($sstmt, SQLSRV_FETCH_ASSOC)) {
    $val = trim((string) ($r['BoMon'] ?? ''));
    if ($val !== '') $subjects[] = $val;
  }
}
if (empty($subjects)) {
  $subjects = ['Tin học', 'Toán', 'Vật lý', 'Hóa học', 'Sinh học', 'Ngữ văn'];
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
                    <td><span class="admin-chip <?php echo $chipClass; ?>"><?php echo htmlspecialchars($role); ?></span></td>
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
                        <form method="post" onsubmit="return confirm('Reset mật khẩu cho tài khoản này?')" style="display:inline-block;margin-left:8px;">
                          <input type="hidden" name="action" value="reset_password">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                          <input type="hidden" name="username" value="<?php echo $username; ?>">
                          <button class="admin-btn admin-btn-warning" type="submit">Reset mật khẩu</button>
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
                  <label>Username</label>
                  <input class="admin-input" name="username" required>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Password</label>
                  <input class="admin-input" name="password" type="password" required>
                </div>
                <div class="admin-field admin-col-12">
                  <label>Họ tên</label>
                  <input class="admin-input" name="fullname">
                </div>
                <div class="admin-field admin-col-12">
                  <label>Email</label>
                  <input class="admin-input" name="email" type="email">
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
                    <option value="user">user - người dùng bình thường</option>
                    <option value="teacher">teacher - giáo viên</option>
                    <option value="admin">admin - quản trị viên</option>
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