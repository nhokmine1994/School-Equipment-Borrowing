<?php
session_start();
include '../connect.php';

if (!function_exists('seb_admin_connection_message')) {
  function seb_admin_connection_message()
  {
    $server = getenv('SEB_DB_SERVER');
    $server = is_string($server) ? trim($server) : '';

    $messages = [
      'Không kết nối được cơ sở dữ liệu.',
      'Hãy kiểm tra service SQL Server, driver sqlsrv và biến môi trường SEB_DB_SERVER.',
    ];

    if ($server !== '') {
      $messages[] = 'Server hiện tại: ' . $server . '.';
    }

    return implode(' ', $messages);
  }
}

function require_admin()
{
  $role = strtolower(trim((string) ($_SESSION['user']['role'] ?? '')));
  if (empty($_SESSION['user']) || $role !== 'admin') {
    header('Location: admin_login.php');
    exit;
  }
}

function login_user($user)
{
  session_regenerate_id(true);
  if (is_array($user) && isset($user['role'])) {
    $user['role'] = strtolower(trim((string) $user['role']));
  }
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

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
  logout_user();
  header('Location: admin_login.php');
  exit;
}

if (strtolower(trim((string) ($_SESSION['user']['role'] ?? ''))) === 'admin') {
  header('Location: admin_thiet_bi.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($conn)) {
    $error = seb_admin_connection_message();
  } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
      $error = 'Vui lòng nhập username và password.';
    } else {
      $sql = "SELECT * FROM TaiKhoan WHERE TaiKhoan = ?";
      $params = array(&$username);
      $stmt = sqlsrv_prepare($conn, $sql, $params);
      if ($stmt && sqlsrv_execute($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
          $storedPassword = trim((string) ($row['MatKhau'] ?? ''));
          $isAuthenticated = false;
          if ($storedPassword !== '' && password_verify($password, $storedPassword)) {
            $isAuthenticated = true;
          } elseif ($storedPassword !== '' && $storedPassword === $password) {
            $isAuthenticated = true;
          }

          if ($isAuthenticated) {
            // Resolve display name from available columns
            $displayCandidates = ['HoVaTen','HoTen','TenHienThi','display_name','full_name','fullName','FullName','name','TaiKhoan'];
            $displayName = '';
            foreach ($displayCandidates as $cand) {
                if (isset($row[$cand]) && trim((string)$row[$cand]) !== '') {
                    $displayName = trim((string)$row[$cand]);
                    break;
                }
            }

            $user = [
              'username' => $row['TaiKhoan'],
              'role' => strtolower(trim((string) ($row['LoaiTaiKhoan'] ?? 'user'))),
              'display_name' => $displayName,
            ];
            login_user($user);
            header('Location: admin_thiet_bi.php');
            exit;
          }
        }
      }
      $error = 'Đăng nhập thất bại.';
    }
  }
}

if (empty($conn) && $error === '') {
  $error = seb_admin_connection_message();
}

require_once __DIR__ . '/../components/admin_layout.php';
admin_render_head('Đăng nhập quản trị');
admin_render_shell_open('', 'admin-login-page');
?>
    <section class="section-wrapper admin-section">
      <div class="section-heading">
        <i class="fas fa-user-shield" aria-hidden="true"></i>
        Đăng nhập quản trị
      </div>
      <div class="admin-content">
        <article class="admin-card admin-login-card">
          <div class="admin-card-head">
            <div>
              <h2 class="admin-card-title">Tài khoản quản trị viên</h2>
              <p class="admin-card-note">Đăng nhập để truy cập khu vực quản trị SEB.</p>
            </div>
          </div>
          <div class="admin-card-body">
            <?php if ($error): ?>
              <div class="admin-flash admin-flash-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
              <div class="admin-field">
                <label for="username">Username</label>
                <input class="admin-input" id="username" name="username" required autofocus>
              </div>
              <div class="admin-field" style="margin-top:12px;">
                <label for="password">Password</label>
                <input class="admin-input" id="password" name="password" type="password" required>
              </div>
              <div class="admin-actions" style="margin-top:16px;">
                <button class="admin-btn admin-btn-primary" type="submit">Đăng nhập</button>
                <a class="admin-btn admin-btn-soft" href="../index.php" style="text-decoration:none;display:inline-flex;align-items:center;">Về trang chủ</a>
              </div>
            </form>
          </div>
        </article>
      </div>
    </section>
<?php admin_render_footer(); ?>
