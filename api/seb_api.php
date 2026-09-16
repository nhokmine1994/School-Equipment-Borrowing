<?php
session_start();

require_once dirname(__DIR__) . '/connect.php';
require_once dirname(__DIR__) . '/components/seb_db.php';

$conn = seb_require_conn();
if (!$conn) {
    seb_json_response(['ok' => false, 'error' => 'Không kết nối được SQL Server.'], 503);
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function seb_read_json_body()
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function seb_api_format_time_ago($dateValue)
{
    if ($dateValue === null || $dateValue === '') {
        return '';
    }

    $timestamp = is_object($dateValue) && method_exists($dateValue, 'getTimestamp')
        ? $dateValue->getTimestamp()
        : strtotime((string) $dateValue);
    if (!$timestamp) {
        return '';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Vừa mới';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' phút trước';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' giờ trước';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' ngày trước';
    }
    return floor($diff / 604800) . ' tuần trước';
}

function seb_api_resolve_user_payload($user)
{
    $role = strtolower(trim((string) ($user['role'] ?? 'user')));
    $username = (string) ($user['username'] ?? '');
    $display = trim((string) ($user['display_name'] ?? $user['name'] ?? $user['full_name'] ?? $username));
    return [
        'username' => $username,
        'role' => $role !== '' ? $role : 'user',
        'type' => (string) ($user['type'] ?? ''),
        'name' => $display,
        'display_name' => $display,
    ];
}

function seb_fetch_subjects_list($conn)
{
    // Nguồn trung tâm: bảng tra cứu dbo.BoMon (fallback TaiKhoan.BoMon trong helper)
    if (function_exists('seb_fetch_bo_mon_list')) {
        $subjects = seb_fetch_bo_mon_list($conn);
    } else {
        $subjects = [];
    }

    if (empty($subjects)) {
        $subjects = ['Tin học', 'Toán', 'Vật lý', 'Hóa học', 'Sinh học', 'Ngữ văn'];
    }

    return array_values(array_unique($subjects));
}

switch ($action) {
    case 'current_user':
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || trim((string) ($user['username'] ?? '')) === '') {
            seb_json_response(['ok' => true, 'loggedIn' => false, 'user' => null]);
        }
        seb_json_response(['ok' => true, 'loggedIn' => true, 'user' => seb_api_resolve_user_payload($user)]);

    case 'logout_user':
        $_SESSION['user'] = null;
        unset($_SESSION['user']);
        seb_json_response(['ok' => true]);

    case 'login_user':
        if ($method !== 'POST') {
            seb_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $body = array_merge($_POST, seb_read_json_body());
        $tk = trim((string) ($body['username'] ?? $body['TaiKhoan'] ?? ''));
        $mk = (string) ($body['password'] ?? $body['MatKhau'] ?? '');

        if ($tk === '' || $mk === '') {
            seb_json_response(['ok' => false, 'error' => 'Vui lòng nhập tài khoản và mật khẩu.'], 400);
        }

        $stmt = @sqlsrv_query($conn, 'SELECT * FROM TaiKhoan WHERE TaiKhoan = ?', [$tk]);
        if ($stmt === false) {
            seb_json_response(['ok' => false, 'error' => 'Đăng nhập thất bại do lỗi hệ thống.'], 500);
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $isAuthenticated = false;
        if ($row) {
            $storedPassword = trim((string) ($row['MatKhau'] ?? ''));
            if ($storedPassword !== '' && password_verify($mk, $storedPassword)) {
                $isAuthenticated = true;
            } elseif ($storedPassword !== '' && $storedPassword === $mk) {
                $isAuthenticated = true;
            }
        }

        if (!$isAuthenticated) {
            seb_json_response(['ok' => false, 'error' => 'Sai tài khoản hoặc mật khẩu'], 401);
        }

        $roleValue = strtolower(trim((string) ($row['LoaiTaiKhoan'] ?? 'user')));
        if ($roleValue === 'pending') {
            seb_json_response(['ok' => false, 'error' => 'Tài khoản của bạn đang chờ quản trị viên duyệt.'], 403);
        }
        if ($roleValue === 'rejected') {
            seb_json_response(['ok' => false, 'error' => 'Tài khoản của bạn đã bị từ chối.'], 403);
        }

        $displayCandidates = ['HoVaTen', 'HoTen', 'TenHienThi', 'display_name', 'full_name', 'fullName', 'FullName', 'name', 'TaiKhoan'];
        $displayName = '';
        foreach ($displayCandidates as $cand) {
            if (isset($row[$cand]) && trim((string) $row[$cand]) !== '') {
                $displayName = trim((string) $row[$cand]);
                break;
            }
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'username' => (string) ($row['TaiKhoan'] ?? $tk),
            'role' => $roleValue,
            'type' => 'regular_user',
            'display_name' => $displayName,
        ];
        $user = $_SESSION['user'];

        seb_json_response(['ok' => true, 'user' => seb_api_resolve_user_payload($user)]);

    case 'dashboard_summary':
        $stats = function_exists('seb_load_index_dashboard_stats') ? seb_load_index_dashboard_stats($conn) : [];
        $summary = [
            'total_devices' => (int) ($stats['total_devices'] ?? 0),
            'maintenance_devices' => (int) ($stats['maintenance_devices'] ?? 0),
            'borrowed_devices' => (int) ($stats['borrowed_devices'] ?? 0),
            'total_rooms' => 0,
            'pending_requests' => 0,
        ];

        $roomStmt = @sqlsrv_query($conn, 'SELECT COUNT(DISTINCT SoPhong) AS c FROM DangKyPhong');
        if ($roomStmt && ($roomRow = sqlsrv_fetch_array($roomStmt, SQLSRV_FETCH_ASSOC))) {
            $summary['total_rooms'] = (int) ($roomRow['c'] ?? 0);
        }

        $pendingId = seb_resolve_borrow_status_id($conn, 'pending');
        $pendingStatus = seb_resolve_phieu_muon_status_column($conn);
        if (!empty($pendingId['ok']) && !empty($pendingStatus['ok'])) {
            $pendStmt = @sqlsrv_query(
                $conn,
                'SELECT COUNT(*) AS c FROM PhieuMuon WHERE [' . str_replace(']', ']]', $pendingStatus['column']) . '] = ?',
                [(int) $pendingId['id']]
            );
            if ($pendStmt && ($pendRow = sqlsrv_fetch_array($pendStmt, SQLSRV_FETCH_ASSOC))) {
                $summary['pending_requests'] = (int) ($pendRow['c'] ?? 0);
            }
        }

        seb_json_response(['ok' => true, 'summary' => $summary]);

    case 'device_list':
        $devicesMap = seb_fetch_devices_map($conn);
        $seen = [];
        $items = [];
        foreach ($devicesMap as $device) {
            $id = (string) ($device['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $items[] = $device;
        }
        seb_json_response(['ok' => true, 'items' => $items]);

    case 'borrow_create':
    case 'borrow_request':
        if ($method !== 'POST') {
            seb_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $taiKhoan = seb_require_login_json();
        $body = array_merge($_POST, seb_read_json_body());
        $maThietBi = trim((string) ($body['maThietBi'] ?? $body['id'] ?? ''));
        $soLuong = max(1, (int) ($body['soLuong'] ?? 1));
        $hanTra = trim((string) ($body['hanTra'] ?? ''));

        if ($maThietBi === '') {
            seb_json_response(['ok' => false, 'error' => 'Thiếu mã thiết bị.'], 400);
        }

        $devices = seb_fetch_devices_map($conn);
        $device = $devices[$maThietBi] ?? null;
        if (!$device) {
            seb_json_response(['ok' => false, 'error' => 'Không tìm thấy thiết bị trong kho.'], 404);
        }

        if (($device['status'] ?? '') === 'unavailable') {
            seb_json_response(['ok' => false, 'error' => 'Thiết bị đang bảo trì hoặc hết hàng.'], 400);
        }

        if ((int) ($device['quantity'] ?? 0) < $soLuong) {
            seb_json_response(['ok' => false, 'error' => 'Không đủ số lượng thiết bị trong kho.'], 400);
        }

        // Create borrow request with 'pending' status (waiting for admin approval)
        $result = seb_create_borrow_request(
            $conn,
            $taiKhoan,
            (string) ($device['id'] ?? $maThietBi),
            (string) ($device['code'] ?? $maThietBi),
            (string) $device['name'],
            $soLuong,
            $hanTra !== '' ? $hanTra : null
        );

        if (empty($result['ok'])) {
            // Log detailed error for debugging (DB error messages are captured inside seb_sql_error_message)
            error_log('SEB: borrow_request failed. user=' . $taiKhoan . ' device=' . $maThietBi . ' qty=' . (int)$soLuong . ' err=' . ($result['error'] ?? 'unknown'));
            seb_json_response(['ok' => false, 'error' => $result['error'] ?? 'Không gửi được yêu cầu mượn.'], 500);
        }

        seb_json_response([
            'ok' => true,
            'borrowId' => $result['soPhieuMuon'],
            'device' => $device,
            'borrowDate' => date('c'),
            'message' => 'Đã gửi yêu cầu mượn ' . $result['soPhieuMuon'] . ' chờ quản trị viên duyệt.',
            'status' => 'pending',
        ]);

    case 'borrow_list':
        $taiKhoan = seb_require_login_json();
        $items = seb_fetch_phieu_muon_by_user($conn, $taiKhoan);
        seb_json_response(['ok' => true, 'items' => $items]);

    case 'personal_list':
        $taiKhoan = seb_require_login_json();
        $items = seb_fetch_personal_devices($conn, $taiKhoan);
        seb_json_response(['ok' => true, 'items' => $items]);

    case 'personal_add':
        if ($method !== 'POST') {
            seb_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $taiKhoan = seb_require_login_json();
        $body = array_merge($_POST, seb_read_json_body());
        $maThietBi = trim((string) ($body['maThietBi'] ?? $body['id'] ?? ''));

        if ($maThietBi === '') {
            seb_json_response(['ok' => false, 'error' => 'Thiếu mã thiết bị.'], 400);
        }

        $devices = seb_fetch_devices_map($conn);
        if (!isset($devices[$maThietBi])) {
            seb_json_response(['ok' => false, 'error' => 'Mã thiết bị không tồn tại trong kho.'], 404);
        }

        $result = seb_exec_them_kho_ca_nhan($conn, $taiKhoan, $maThietBi);
        if (empty($result['ok'])) {
            seb_json_response(['ok' => false, 'error' => $result['error'] ?? 'Không thêm được vào kho cá nhân.'], 500);
        }

        if (!empty($result['duplicate'])) {
            seb_json_response(['ok' => true, 'duplicate' => true, 'message' => 'Thiết bị đã có trong kho cá nhân.']);
        }

        seb_json_response([
            'ok' => true,
            'device' => $devices[$maThietBi],
            'message' => 'Đã thêm thiết bị vào kho cá nhân.',
        ]);

    case 'personal_remove':
        if ($method !== 'POST') {
            seb_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $taiKhoan = seb_require_login_json();
        $body = array_merge($_POST, seb_read_json_body());
        $maThietBi = trim((string) ($body['maThietBi'] ?? $body['id'] ?? ''));

        sqlsrv_query($conn, 'DELETE FROM KhoCaNhan WHERE TaiKhoan = ? AND MaThietBi = ?', [$taiKhoan, $maThietBi]);
        seb_json_response(['ok' => true]);

    case 'room_schedule':
        seb_require_login_json();
        $roomType = trim((string) ($_GET['roomType'] ?? ''));
        $roomNumber = trim((string) ($_GET['roomNumber'] ?? ''));
        $sql = "SELECT MaDatCho, Username, LoaiPhong, LoaiPhongLabel, SoPhong, SoPhongLabel,
                       TenHienThi, MucDich, DuLieuCa, TrangThai, NgayTao
                FROM DangKyPhong
                WHERE LoaiPhong = ? AND SoPhong = ? AND TrangThai <> 'cancelled'
                ORDER BY NgayTao DESC";
        $stmt = sqlsrv_query($conn, $sql, [$roomType, $roomNumber]);
        $items = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $slots = [];
                if (!empty($row['DuLieuCa'])) {
                    $decoded = json_decode((string) $row['DuLieuCa'], true);
                    if (is_array($decoded)) {
                        $slots = $decoded;
                    }
                }
                $items[] = [
                    'bookingId' => (string) ($row['MaDatCho'] ?? ''),
                    'roomType' => (string) ($row['LoaiPhong'] ?? ''),
                    'roomNumber' => (string) ($row['SoPhong'] ?? ''),
                    'createdBy' => (string) ($row['Username'] ?? ''),
                    'userNameLabel' => (string) ($row['TenHienThi'] ?? ''),
                    'purpose' => (string) ($row['MucDich'] ?? ''),
                    'createdAt' => seb_datetime_iso($row['NgayTao'] ?? null),
                    'slots' => $slots,
                ];
            }
        }
        seb_json_response(['ok' => true, 'items' => $items]);

    case 'room_list':
        $username = seb_require_login_json();
        $sql = "SELECT MaDangKy, MaDatCho, LoaiPhong, LoaiPhongLabel, SoPhong, SoPhongLabel,
                       TenHienThi, MucDich, DuLieuCa, TrangThai, NgayTao
                FROM DangKyPhong
                WHERE Username = ?
                ORDER BY NgayTao DESC";
        $stmt = sqlsrv_query($conn, $sql, [$username]);
        $items = [];

        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $slots = [];
                if (!empty($row['DuLieuCa'])) {
                    $decoded = json_decode((string) $row['DuLieuCa'], true);
                    if (is_array($decoded)) {
                        $slots = $decoded;
                    }
                }

                $items[] = [
                    'bookingId' => (string) ($row['MaDatCho'] ?? ''),
                    'roomType' => (string) ($row['LoaiPhong'] ?? ''),
                    'roomTypeLabel' => (string) ($row['LoaiPhongLabel'] ?? ''),
                    'roomNumber' => (string) ($row['SoPhong'] ?? ''),
                    'roomNumberLabel' => (string) ($row['SoPhongLabel'] ?? ''),
                    'createdBy' => $username,
                    'userNameLabel' => (string) ($row['TenHienThi'] ?? $username),
                    'purpose' => (string) ($row['MucDich'] ?? ''),
                    'createdAt' => seb_datetime_iso($row['NgayTao'] ?? null),
                    'status' => (string) ($row['TrangThai'] ?? 'pending'),
                    'slots' => $slots,
                ];
            }
        }

        seb_json_response(['ok' => true, 'items' => $items]);

    case 'room_create':
        if ($method !== 'POST') {
            seb_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $username = seb_require_login_json();
        $body = seb_read_json_body();
        if (empty($body)) {
            $body = $_POST;
        }

        $bookingId = trim((string) ($body['bookingId'] ?? ('RB-' . time())));
        $roomType = trim((string) ($body['roomType'] ?? ''));
        $roomTypeLabel = trim((string) ($body['roomTypeLabel'] ?? $roomType));
        $roomNumber = trim((string) ($body['roomNumber'] ?? ''));
        $roomNumberLabel = trim((string) ($body['roomNumberLabel'] ?? $roomNumber));
        $userNameLabel = trim((string) ($body['userNameLabel'] ?? $username));
        $purpose = trim((string) ($body['purpose'] ?? 'Không có ghi chú'));
        $slots = $body['slots'] ?? [];
        $slotsJson = json_encode(is_array($slots) ? $slots : [], JSON_UNESCAPED_UNICODE);

        if ($roomNumber === '' || !is_array($slots) || count($slots) === 0) {
            seb_json_response(['ok' => false, 'error' => 'Thiếu thông tin phòng hoặc ca học.'], 400);
        }

        $sql = "INSERT INTO DangKyPhong
                (MaDatCho, Username, LoaiPhong, LoaiPhongLabel, SoPhong, SoPhongLabel, TenHienThi, MucDich, DuLieuCa, TrangThai)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $bookingId, $username, $roomType, $roomTypeLabel,
            $roomNumber, $roomNumberLabel, $userNameLabel, $purpose, $slotsJson, 'Chờ duyệt',
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            seb_json_response(['ok' => false, 'error' => 'Không lưu được đăng ký phòng.'], 500);
        }

        // Thông báo cho quản trị viên duyệt đăng ký phòng
        $slotTexts = [];
        if (is_array($slots)) {
            foreach (array_slice($slots, 0, 5) as $slot) {
                $slotText = trim((string) ($slot['dayLabel'] ?? '')) . ' - ' . trim((string) ($slot['timeLabel'] ?? ''));
                if (trim($slotTextRaw = str_replace(' - -', ' -', $slotText)) !== '') {
                    $slotTexts[] = $slotText;
                }
            }
        }
        // thông báo dạng text đơn giản — tránh lỗi biến undefined
        $slotTexts = isset($slotTexts) && is_array($slotTexts) ? $slotTexts : [];
        $slotsBrief = implode('; ', $slotTexts);
        $roomLabel = trim($roomTypeLabel . ' ' . $roomNumberLabel);
        $notifySql = "INSERT INTO ThongBaoAdmin (LoaiThongBao, TieuDe, LoiNhan, Link, ThoiGianTao, TrangThai)
                      VALUES (N'room', ?, ?, 'admin_dang_ky_phong.php', GETDATE(), 0)";
        $notifyMsg = trim((string) ($userNameLabel !== '' ? $userNameLabel : $username)) . ' gửi yêu cầu đăng ký phòng học. Vui lòng duyệt.';
        @sqlsrv_query($conn, $notifySql, ['Yêu cầu đăng ký phòng', $notifyMsg . ' Nhấn trang duyệt để xử lý.']);

        seb_json_response(['ok' => true, 'bookingId' => $bookingId]);

    case 'room_cancel':
        if ($method !== 'POST') {
            seb_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $username = seb_require_login_json();
        $body = array_merge($_POST, seb_read_json_body());
        $bookingId = trim((string) ($body['bookingId'] ?? ''));

        if ($bookingId === '') {
            seb_json_response(['ok' => false, 'error' => 'Thiếu mã đặt chỗ.'], 400);
        }

        sqlsrv_query(
            $conn,
            "DELETE FROM DangKyPhong WHERE MaDatCho = ? AND Username = ?",
            [$bookingId, $username]
        );
        seb_json_response(['ok' => true]);

    case 'subjects':
        $subjects = seb_fetch_subjects_list($conn);
        seb_json_response(['ok' => true, 'subjects' => $subjects]);

    case 'register':
        if ($method !== 'POST') {
            seb_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $body = array_merge($_POST, seb_read_json_body());
        $username = trim((string) ($body['username'] ?? $body['TaiKhoan'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? $body['MatKhau'] ?? '');
        $phone = trim((string) ($body['phone'] ?? $body['SoDienThoai'] ?? ''));
        $fullName = trim((string) ($body['fullName'] ?? $username));
        // Accept either monHoc (frontend) or boMon (admin form) and save into BoMon column
        $subject = trim((string) ($body['boMon'] ?? $body['monHoc'] ?? $body['subject'] ?? ''));

        if ($username === '' || $password === '') {
            seb_json_response(['ok' => false, 'error' => 'Vui lòng nhập tài khoản và mật khẩu.'], 400);
        }

        $check = sqlsrv_query($conn, 'SELECT 1 FROM TaiKhoan WHERE TaiKhoan = ?', [$username]);
        if ($check && sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC)) {
            seb_json_response(['ok' => false, 'error' => 'Tài khoản đã tồn tại.'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Build INSERT dynamically based on actual columns in TaiKhoan table
        $tblCols = seb_get_table_columns_info($conn, 'TaiKhoan');
        if (empty($tblCols)) {
            seb_json_response(['ok' => false, 'error' => 'Bảng TaiKhoan không tồn tại trong cơ sở dữ liệu.'], 500);
        }

        $colMap = [
            'username' => ['taikhoan', 'username'],
            'password' => ['matkhau', 'password', 'pass'],
            'role' => ['loaitaiKhoan', 'loaiTaiKhoan', 'role'],
            'fullname' => ['hovaten', 'hovaten', 'fullname', 'tenhienthi'],
            'phone' => ['sodienthoai', 'sodienthoai', 'phone'],
            'email' => ['email', 'emailaddress'],
            'bomon' => ['bomon', 'bomon', 'department'],
        ];

        $insertCols = [];
        $params = [];

        // Helper to find first matching column name (case-insensitive)
        $findCol = function ($candidates) use ($tblCols) {
            foreach ($candidates as $c) {
                $k = strtolower(trim((string) $c));
                if ($k === '') {
                    continue;
                }
                if (isset($tblCols[$k])) {
                    return $tblCols[$k]['name'];
                }
            }
            return '';
        };

        $usernameCol = $findCol($colMap['username']);
        $passwordCol = $findCol($colMap['password']);
        if ($usernameCol === '' || $passwordCol === '') {
            seb_json_response(['ok' => false, 'error' => 'Bảng TaiKhoan thiếu cột tài khoản hoặc mật khẩu.'], 500);
        }

        $insertCols[] = $usernameCol;
        $params[] = $username;

        $insertCols[] = $passwordCol;
        $params[] = $hash;

        $roleCol = $findCol($colMap['role']);
        if ($roleCol !== '') {
            $insertCols[] = $roleCol;
            // Front-end registrations must be approved by admin first.
            $params[] = 'pending';
        }

        $fullNameCol = $findCol($colMap['fullname']);
        if ($fullNameCol !== '') {
            $insertCols[] = $fullNameCol;
            $params[] = $fullName;
        }

        $phoneCol = $findCol($colMap['phone']);
        if ($phoneCol !== '') {
            $insertCols[] = $phoneCol;
            $params[] = $phone !== '' ? $phone : '';
        }

        $emailCol = $findCol($colMap['email']);
        if ($emailCol !== '') {
            $insertCols[] = $emailCol;
            $params[] = $email !== '' ? $email : null;
        }

        $bomonCol = $findCol($colMap['bomon']);
        if ($bomonCol !== '') {
            $insertCols[] = $bomonCol;
            // BoMon là NVARCHAR NOT NULL trong DB — không bao giờ insert NULL
            $params[] = $subject !== '' ? $subject : 'Chung';
        }

        $placeholders = rtrim(str_repeat('?,', count($insertCols)), ',');
        $escapedCols = array_map(function ($c) { return '[' . str_replace(']', ']]', $c) . ']'; }, $insertCols);
        $sql = 'INSERT INTO TaiKhoan (' . implode(', ', $escapedCols) . ') VALUES (' . $placeholders . ')';

        $stmt = @sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            // If insert fails, return SQL error message for debugging
            seb_json_response(['ok' => false, 'error' => seb_sql_error_message('Không tạo được tài khoản.')], 500);
        }

        seb_json_response(['ok' => true, 'message' => 'Đăng ký thành công. Vui lòng đăng nhập.']);

    case 'recent_borrows':
        $items = seb_fetch_recent_phieu_muon($conn, 9);
        $formatted = [];
        foreach ($items as $item) {
            $formatted[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'quantity' => (int) ($item['quantity'] ?? 1),
                'username' => (string) ($item['username'] ?? ''),
                'borrowStatus' => (string) ($item['borrowStatus'] ?? ''),
                'soPhieu' => (string) ($item['soPhieuMuon'] ?? ''),
                'timeAgo' => seb_api_format_time_ago($item['ngayMuon'] ?? null),
                'image' => (string) ($item['image'] ?? ''),
            ];
        }
        seb_json_response(['ok' => true, 'items' => $formatted]);

    case 'news_list':
        // Nguồn chính: bảng ThongBao (thông báo thật từ trang quản trị)
        $items = [];
        $newsFromDb = false;
        if (!empty($conn) && function_exists('sqlsrv_query')) {
            $newsSql = "SELECT MaThongBao, LoaiThongBao, NoiDung, NgayDang, NguoiDang
                        FROM ThongBao
                        WHERE TrangThai IN (N'Hiển thị', N'Dang hien thi', N'Hiển thị ', N'Hiện thị')
                           OR TrangThai = 1
                        ORDER BY NgayDang DESC, MaThongBao DESC";
            $newsStmt = @sqlsrv_query($conn, $newsSql);
            if ($newsStmt) {
                while ($row = sqlsrv_fetch_array($newsStmt, SQLSRV_FETCH_ASSOC)) {
                    $loai = trim((string) ($row['LoaiThongBao'] ?? 'Bảo trì'));
                    // map loại → key frontend
                    $lower = mb_strtolower($loai, 'UTF-8');
                    if (strpos($lower, 'bảo trì') !== false || strpos($lower, 'bao tri') !== false) {
                        $key = 'bao-tri';
                        $gradient = 'gradient-bao-tri-2';
                        $icon = 'fa-tools';
                    } elseif (strpos($lower, 'sự kiện') !== false) {
                        $key = 'su-kien'; $gradient = 'gradient-su-kien'; $icon = 'fa-calendar-alt';
                    } elseif (strpos($lower, 'thiết bị') !== false) {
                        $key = 'thiet-bi-moi'; $gradient = 'gradient-thiet-bi-moi'; $icon = 'fa-box-open';
                    } else {
                        $key = 'thong-bao'; $gradient = 'gradient-thong-bao'; $icon = 'fa-bullhorn';
                    }

                    $dateObj = $row['NgayDang'] ?? null;
                    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
                        $dateStr = $dateObj->format('d/m/Y');
                    } else {
                        $ts = strtotime((string) $dateObj);
                        $dateStr = $ts ? date('d/m/Y', $ts) : '';
                    }

                    $noiDung = trim((string) ($row['NoiDung'] ?? ''));
                    $items[] = [
                        'id' => (int) ($row['MaThongBao'] ?? 0),
                        'category' => $key,
                        'categoryLabel' => $loai,
                        'title' => mb_substr($noiDung !== '' ? $noiDung : $loai, 0, 80),
                        'content' => $noiDung,
                        'excerpt' => mb_substr($noiDung !== '' ? $noiDung : $loai, 0, 120),
                        'date' => $dateStr,
                        'readTime' => max(1, (int) ceil(mb_strlen($noiDung, 'UTF-8') / 300)) . ' phút',
                        'views' => '',
                        'author' => (string) ($row['NguoiDang'] ?? 'Quản trị'),
                        'gradient' => $gradient,
                        'icon' => $icon,
                    ];
                }
                $newsFromDb = count($items) > 0;
            }
        }

        if ($newsFromDb) {
            $first = array_shift($items);
            seb_json_response(['ok' => true, 'news' => [
                'banner' => ['articles' => (string) ($newsCount = count($items) + 1), 'topics' => '3', 'views' => '—'],
                'featured' => [
                    'category' => $first['category'],
                    'title' => $first['title'],
                    'excerpt' => $first['content'] !== '' ? $first['content'] : $first['title'],
                    'date' => $first['date'],
                    'readTime' => $first['readTime'],
                    'views' => '',
                    'label' => 'Nổi bật',
                ],
                'articles' => $items,
            ]]);
        }

        // Fallback: file news.json (nếu bảng chưa có tin)
        $newsPath = dirname(__DIR__) . '/data/news.json';
        if (!is_file($newsPath)) {
            seb_json_response(['ok' => false, 'error' => 'Chưa có tin tức nào được đăng.'], 404);
        }

        $news = json_decode((string) file_get_contents($newsPath), true);
        seb_json_response(['ok' => true, 'news' => is_array($news) ? $news : []]);

    case 'team_members':
        $teamPath = dirname(__DIR__) . '/data/team.json';
        if (!is_file($teamPath)) {
            seb_json_response(['ok' => false, 'error' => 'Không tìm thấy dữ liệu đội ngũ.'], 404);
        }

        $team = json_decode((string) file_get_contents($teamPath), true);
        seb_json_response(['ok' => true, 'team' => is_array($team) ? $team : ['members' => []]]);

    case 'maintenance_list':
        $items = [];
        if (!empty($conn) && function_exists('sqlsrv_query')) {
            $sql = "SELECT TOP 10 MaBaoTri, TieuDe, NoiDung, NgayCapNhat FROM BaoTriThongBao WHERE TrangThai = 1 ORDER BY ThuTuHienThi ASC, NgayCapNhat DESC";
            $stmt = @sqlsrv_query($conn, $sql);
            if ($stmt !== false) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $dateObj = $row['NgayCapNhat'] ?? null;
                    $formattedDate = '';
                    if ($dateObj) {
                        if (is_object($dateObj) && method_exists($dateObj, 'format')) {
                            $formattedDate = $dateObj->format('d/m/Y');
                        } else {
                            $ts = strtotime((string) $dateObj);
                            $formattedDate = $ts ? date('d/m/Y', $ts) : '';
                        }
                    }
                    $items[] = [
                        'id' => (int) ($row['MaBaoTri'] ?? 0),
                        'title' => (string) ($row['TieuDe'] ?? ''),
                        'content' => (string) ($row['NoiDung'] ?? ''),
                        'date' => $formattedDate,
                    ];
                }
            }
        }
        seb_json_response(['ok' => true, 'items' => $items]);

    default:
        seb_json_response(['ok' => false, 'error' => 'Action không hợp lệ.'], 400);
}
