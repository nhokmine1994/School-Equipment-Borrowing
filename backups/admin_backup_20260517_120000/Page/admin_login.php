<?php
session_start();
include '../connect.php';

// Inline session helpers (merged)
function require_admin()
{
  if (empty($_SESSION['user']) || empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
  }
}

function login_user($user)
{
  session_regenerate_id(true);
  $_SESSION['user'] = $user;
}

function logout_user()
{
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
  }
  session_destroy();
}

// Logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
  logout_user();
  header('Location: admin_login.php');
  exit;
}

// If already logged in redirect to admin area
if (!empty($_SESSION['user']) && !empty($_SESSION['user']['role'])) {
  header('Location: admin_thiet_bi.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($username === '' || $password === '') {
    $error = 'Vui lòng nhập username và password.';
  } else {
    // Query Users table for role and password
    $sql = "SELECT Username, PasswordHash, Password, Role FROM Users WHERE Username = ?";
    $params = array(&$username);
    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if ($stmt && sqlsrv_execute($stmt)) {
      $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
      if ($row) {
        $hash = $row['PasswordHash'] ?? '';
        $plain = $row['Password'] ?? '';
        $ok = false;
        if ($hash && password_verify($password, $hash)) {
          $ok = true;
        } elseif ($plain !== '' && $plain === $password) {
          $ok = true; // fallback
        }

        if ($ok) {
          $user = ['username' => $row['Username'], 'role' => $row['Role'] ?? 'user'];
          login_user($user);
          header('Location: admin_thiet_bi.php');
          exit;
        }
      }
    }
    $error = 'Đăng nhập thất bại.';
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login - SEB</title>
  <link rel="stylesheet" href="../CSS/main.css">
  <style> .login-box{max-width:420px;margin:60px auto;padding:20px;border:1px solid #ddd;background:#fff;} .error{color:#b00} </style>
</head>
<body>
  <div class="login-box">
    <h2>Đăng nhập Admin</h2>
    <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
    <form method="post">
      <div>
        <label>Username</label><br>
        <input name="username" required autofocus>
      </div>
      <div style="margin-top:8px;">
        <label>Password</label><br>
        <input name="password" type="password" required>
      </div>
      <div style="margin-top:12px;">
        <button type="submit">Đăng nhập</button>
      </div>
    </form>
  </div>
</body>
</html>