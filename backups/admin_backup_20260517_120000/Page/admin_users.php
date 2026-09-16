<?php
require_once 'admin_auth.php';
require_admin();
include '../connect.php';

$message = '';

// Handle add/delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if ($username === '' || $password === '') {
            $message = 'Vui lòng nhập username và password.';
        } else {
            $sql = "INSERT INTO Users (Username, Password, Role, FullName, Email) VALUES (?, ?, ?, ?, ?)";
            $params = array(&$username, &$password, &$role, &$fullname, &$email);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt && sqlsrv_execute($stmt)) {
                $message = 'Tạo tài khoản thành công.';
            } else {
                $message = 'Tạo tài khoản thất bại (có thể username đã tồn tại).';
            }
        }
    }
    
    if ($action === 'delete') {
        $username = trim($_POST['username'] ?? '');
        if ($username === 'admin') {
            $message = 'Không thể xóa tài khoản admin.';
        } else {
            $sql = "DELETE FROM Users WHERE Username = ?";
            $params = array(&$username);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt && sqlsrv_execute($stmt)) $message = 'Xóa tài khoản thành công.'; else $message = 'Xóa thất bại.';
        }
    }
}

// Fetch all users
$users = [];
$sql = "SELECT Username, Role, FullName, Email FROM Users";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $users[] = $row;
    }
}

?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Quản lý người dùng</title>
  <link rel="stylesheet" href="../CSS/main.css">
  <style>
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #ddd;padding:8px;text-align:left}
    .form-box{background:#f9f9f9;padding:15px;margin-bottom:20px}
    .form-box div{margin:8px 0}
    .form-box input, .form-box select{padding:6px;width:200px}
  </style>
</head>
<body>
  <div style="padding:20px">
    <h2>Quản lý Người dùng (Admin)</h2>
    <p><a href="admin_thiet_bi.php">Quản lý thiết bị</a> | <a href="admin_login.php">Đăng xuất</a></p>
    <?php if ($message): ?><p style="color:green"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

    <h3>Thêm người dùng mới</h3>
    <form method="post" class="form-box">
      <input type="hidden" name="action" value="add">
      <div>
        <label>Username:</label><br>
        <input name="username" required>
      </div>
      <div>
        <label>Password:</label><br>
        <input name="password" type="password" required>
      </div>
      <div>
        <label>Full Name:</label><br>
        <input name="fullname">
      </div>
      <div>
        <label>Email:</label><br>
        <input name="email" type="email">
      </div>
      <div>
        <label>Role:</label><br>
        <select name="role">
          <option value="user">user (người dùng bình thường)</option>
          <option value="teacher">teacher (giáo viên)</option>
          <option value="admin">admin (quản trị viên)</option>
        </select>
      </div>
      <div>
        <button type="submit">Tạo tài khoản</button>
      </div>
    </form>

    <h3>Danh sách người dùng</h3>
    <table>
      <tr><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Hành động</th></tr>
      <?php foreach ($users as $u):
        $username = htmlspecialchars($u['Username'] ?? '');
        $fullname = htmlspecialchars($u['FullName'] ?? '');
        $email = htmlspecialchars($u['Email'] ?? '');
        $role = htmlspecialchars($u['Role'] ?? 'user');
      ?>
      <tr>
        <td><?php echo $username; ?></td>
        <td><?php echo $fullname; ?></td>
        <td><?php echo $email; ?></td>
        <td><?php echo $role; ?></td>
        <td>
          <?php if ($username !== 'admin'): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Xóa tài khoản?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="username" value="<?php echo $username; ?>">
            <button type="submit" style="color:#b00">Xóa</button>
          </form>
          <?php else: ?>
          <em>không thể xóa</em>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</body>
</html>