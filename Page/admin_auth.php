<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// Simple admin middleware — include at top of admin pages
function require_admin()
{
    $role = strtolower(trim((string) ($_SESSION['user']['role'] ?? '')));
    if (empty($_SESSION['user']) || $role !== 'admin') {
        header('Location: admin_login.php');
        exit;
    }
}

function seb_admin_connection_message(): string
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

function seb_render_admin_db_error_page(string $title = 'Lỗi kết nối cơ sở dữ liệu'): void
{
    require_once __DIR__ . '/../components/admin_layout.php';
    $username = $_SESSION['user']['username'] ?? '';

    admin_render_head($title);
    admin_render_shell_open(is_string($username) ? $username : '');
    admin_render_nav('panel');
    admin_render_page_intro($title, 'fa-triangle-exclamation', seb_admin_connection_message());
    echo '<div class="admin-flash admin-flash-danger">' . htmlspecialchars(seb_admin_connection_message(), ENT_QUOTES, 'UTF-8') . '</div>';
    admin_render_shell_close();
    exit;
}

function seb_require_admin_connection($conn, string $title = 'Lỗi kết nối cơ sở dữ liệu'): void
{
    if (!empty($conn)) {
        return;
    }

    seb_render_admin_db_error_page($title);
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

// CSRF Token Functions
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// File Upload Validation
function validate_and_upload_file($file, $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'], $upload_dir = null) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Vui lòng chọn file hợp lệ.'];
    }
    
    if (empty($upload_dir)) {
        $upload_dir = __DIR__ . '/../Images/devices/';
    }
    
    // Create dir if not exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $tmp = $file['tmp_name'];
    $original_name = $file['name'];
    $file_size = $file['size'];
    
    // Get file extension
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    // Check extension whitelist
    if (!in_array($ext, $allowed_exts, true)) {
        return ['success' => false, 'message' => 'Định dạng file không được phép. Chỉ chấp nhận: ' . implode(', ', $allowed_exts)];
    }
    
    // Check file size (max 5MB)
    $max_size = 5 * 1024 * 1024;
    if ($file_size > $max_size) {
        return ['success' => false, 'message' => 'File quá lớn. Tối đa 5MB.'];
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mimes, true)) {
        return ['success' => false, 'message' => 'File không phải là ảnh hợp lệ.'];
    }
    
    // Generate secure filename
    $secure_name = uniqid('img_', true) . '.' . $ext;
    $target = $upload_dir . $secure_name;
    
    if (move_uploaded_file($tmp, $target)) {
        return ['success' => true, 'filename' => $secure_name, 'message' => 'Upload thành công.'];
    } else {
        return ['success' => false, 'message' => 'Không thể di chuyển file upload.'];
    }
}

function add_admin_notification($conn, $loaiThongBao, $tieuDe, $loiNhan, $link = null)
{
    // Bảng ThongBaoAdmin đã tồn tại trong SQL Server, chỉ insert dữ liệu
    $sql = "INSERT INTO ThongBaoAdmin (LoaiThongBao, TieuDe, LoiNhan, Link) VALUES (?, ?, ?, ?)";
    $params = array(&$loaiThongBao, &$tieuDe, &$loiNhan, &$link);
    $stmt = sqlsrv_prepare($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_execute($stmt);
    }
}

function fetch_admin_notifications($conn, $limit = 6)
{

    $limit = max(1, (int) $limit);
    $notifications = [];
    $sql = "SELECT TOP ($limit) MaThongBao, LoaiThongBao, TieuDe, LoiNhan, Link, ThoiGianTao, TrangThai
            FROM ThongBaoAdmin
            ORDER BY ThoiGianTao DESC, MaThongBao DESC";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $notifications[] = $row;
        }
    }

    return $notifications;
}

function render_notification_time($value)
{
    if (!$value) {
        return '';
    }

    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('d/m/Y H:i');
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : (string) $value;
}

?>