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

function seb_fetch_subjects_list($conn)
{
    $subjects = [];

    $stmt = @sqlsrv_query($conn, "SELECT DISTINCT BoMon FROM TaiKhoan WHERE BoMon IS NOT NULL AND LTRIM(RTRIM(BoMon)) <> '' ORDER BY BoMon ASC");
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $value = trim((string) ($row['BoMon'] ?? ''));
            if ($value !== '') {
                $subjects[] = $value;
            }
        }
    }

    if (empty($subjects)) {
        $subjects = ['Tin học', 'Toán', 'Vật lý', 'Hóa học', 'Sinh học', 'Ngữ văn'];
    }

    return array_values(array_unique($subjects));
}

switch ($action) {
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
                (MaDatCho, Username, LoaiPhong, LoaiPhongLabel, SoPhong, SoPhongLabel, TenHienThi, MucDich, DuLieuCa)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $bookingId, $username, $roomType, $roomTypeLabel,
            $roomNumber, $roomNumberLabel, $userNameLabel, $purpose, $slotsJson,
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            seb_json_response(['ok' => false, 'error' => 'Không lưu được đăng ký phòng.'], 500);
        }

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
            $params[] = $subject !== '' ? $subject : null;
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

    default:
        seb_json_response(['ok' => false, 'error' => 'Action không hợp lệ.'], 400);
}
