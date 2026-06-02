<?php

session_start();

require_once __DIR__ . '/../connect.php';

function resolveSafeReturnUrl($candidate)
{
    $fallback = '../index.php';
    if (!is_string($candidate) || trim($candidate) === '') {
        return $fallback;
    }

    $candidate = trim($candidate);
    $parts = parse_url($candidate);
    if ($parts === false) {
        return $fallback;
    }

    // Allow only relative redirects inside this site.
    if (!empty($parts['scheme']) || !empty($parts['host'])) {
        return $fallback;
    }

    return $candidate;
}

if(isset($_POST['login']))
{
    $tk = $_POST['TaiKhoan'];
    $mk = $_POST['MatKhau'];

    if (!isset($conn) || !$conn) {
        http_response_code(500);
        echo 'Lỗi kết nối cơ sở dữ liệu.';
        exit;
    }

    $sql = "SELECT * FROM TaiKhoan WHERE TaiKhoan = ?";
    $params = array($tk);
    $stmt = @sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = function_exists('sqlsrv_errors') ? sqlsrv_errors() : null;
        if (is_array($errors) && !empty($errors[0]['message'])) {
            error_log('SEB: login sql error: ' . $errors[0]['message']);
            $errMsg = $errors[0]['message'];
        } else {
            error_log('SEB: login sql error: unknown');
            $errMsg = 'unknown';
        }

        http_response_code(500);
        $debug = function_exists('seb_env_value') ? seb_env_value('SEB_DEBUG', 'false') : 'false';
        // Append a debug line to logs/login_debug.txt for easier access
        $logPath = __DIR__ . '/../logs/login_debug.txt';
        $logLine = date('c') . "\tLOGIN_SQL_ERROR\tuser=" . (string)$tk . "\tmsg=" . str_replace("\n", ' ', (string)$errMsg) . "\n";
        @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);

        if (strtolower($debug) === 'true') {
            echo 'Đăng nhập thất bại do lỗi hệ thống: ' . htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8');
        } else {
            echo 'Đăng nhập thất bại do lỗi hệ thống.';
        }
        exit;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $isAuthenticated = false;
    if ($row) {
        $storedPassword = trim((string) ($row['MatKhau'] ?? ''));
        if ($storedPassword !== '' && password_verify($mk, $storedPassword)) {
            $isAuthenticated = true;
        } elseif ($storedPassword !== '' && $storedPassword === $mk) {
            // Fallback for legacy plaintext passwords.
            $isAuthenticated = true;
        }
    }

    if ($isAuthenticated) {
        $roleValue = strtolower(trim((string) ($row['LoaiTaiKhoan'] ?? 'user')));
        if ($roleValue === 'pending' || $roleValue === 'rejected') {
            http_response_code(403);
            echo $roleValue === 'rejected'
                ? 'Tài khoản của bạn đã bị từ chối.'
                : 'Tài khoản của bạn đang chờ quản trị viên duyệt.';
            exit;
        }

        // Standardized session structure
        session_regenerate_id(true);
        // Resolve a sensible display name from available columns
        $displayCandidates = ['HoVaTen','HoTen','TenHienThi','display_name','full_name','fullName','FullName','name','TaiKhoan'];
        $displayName = '';
        foreach ($displayCandidates as $cand) {
            if (isset($row[$cand]) && trim((string)$row[$cand]) !== '') {
                $displayName = trim((string)$row[$cand]);
                break;
            }
        }

        // Log which columns were returned for debugging
        $cols = array_keys($row);
        $logPath = __DIR__ . '/../logs/login_debug.txt';
        $logLine = date('c') . "\tLOGIN_COLUMNS\tuser=" . (string)$tk . "\tcols=" . implode(',', $cols) . "\n";
        @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
        $_SESSION['user'] = [
            'username' => $row['TaiKhoan'],
            'role' => $roleValue,
            'type' => 'regular_user',
            'display_name' => $displayName,
        ];

        $returnFromPost = isset($_POST['return']) ? $_POST['return'] : '';
        $returnFromGet = isset($_GET['return']) ? $_GET['return'] : '';
        $redirectTo = resolveSafeReturnUrl($returnFromPost ?: $returnFromGet);

        header('Location: ' . $redirectTo);
        exit;
    }

    // Log failed auth attempt to file for debugging
    $logPath = __DIR__ . '/../logs/login_debug.txt';
    $storedLen = isset($row['MatKhau']) ? strlen((string)$row['MatKhau']) : 0;
    $logLine = date('c') . "\tFAILED_LOGIN\tuser=" . (string)$tk . "\tstored_len=" . $storedLen . "\n";
    @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);

    // Set an error message in session and redirect back to the return URL so the
    // login modal can show an inline error instead of rendering a plain page.
    $_SESSION['auth_error'] = 'Sai tài khoản hoặc mật khẩu';
    $returnFromPost = isset($_POST['return']) ? $_POST['return'] : '';
    $returnFromGet = isset($_GET['return']) ? $_GET['return'] : '';
    $redirectTo = resolveSafeReturnUrl($returnFromPost ?: $returnFromGet);
    header('Location: ' . $redirectTo);
    exit;
}
else
{
    header('Location: ../index.php');
    exit;
}

?>