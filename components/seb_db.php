<?php
/**
 * Schema & helpers — các hàm helper không tạo/xóa bảng, chỉ làm việc với bảng hiện có.
 */

function seb_ensure_application_schema($conn)
{
    // Bảng đã tồn tại trong database, không can thiệp vào schema
    return;
}

function seb_require_conn()
{
    global $conn;

    if (!isset($conn) || !$conn) {
        require_once dirname(__DIR__) . '/connect.php';
    }

    if (empty($conn)) {
        return null;
    }

    seb_ensure_application_schema($conn);
    return $conn;
}

function seb_json_response($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function seb_current_username()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $user = $_SESSION['user'] ?? null;
    if (!is_array($user)) {
        return '';
    }

    return trim((string) ($user['username'] ?? ''));
}

function seb_require_login_json()
{
    $username = seb_current_username();
    if ($username === '') {
        seb_json_response(['ok' => false, 'error' => 'Vui lòng đăng nhập.'], 401);
    }

    return $username;
}

function seb_datetime_iso($value)
{
    if ($value === null) {
        return null;
    }
    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('c');
    }
    $ts = strtotime((string) $value);
    return $ts ? date('c', $ts) : (string) $value;
}

function seb_sql_error_message($fallback = 'Lỗi cơ sở dữ liệu.')
{
    $errors = function_exists('sqlsrv_errors') ? sqlsrv_errors() : null;
    if (!is_array($errors) || empty($errors[0]['message'])) {
        return $fallback;
    }

    return trim((string) $errors[0]['message']);
}

function seb_normalize_text($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function seb_get_table_columns_info($conn, $tableName)
{
    static $cache = [];
    $cacheKey = strtolower(trim((string) $tableName));
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $columns = [];
    $stmt = sqlsrv_query(
        $conn,
        "SELECT COLUMN_NAME, DATA_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION",
        [$tableName]
    );

    if (!$stmt) {
        return $cache[$cacheKey] = [];
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $columnName = strtolower(trim((string) ($row['COLUMN_NAME'] ?? '')));
        if ($columnName === '') {
            continue;
        }

        $columns[$columnName] = [
            'name' => (string) ($row['COLUMN_NAME'] ?? ''),
            'type' => strtolower(trim((string) ($row['DATA_TYPE'] ?? ''))),
        ];
    }

    return $cache[$cacheKey] = $columns;
}

function seb_pick_column_name(array $columns, array $candidates, array $types = [])
{
    $types = array_map('strtolower', $types);

    foreach ($candidates as $candidate) {
        $key = strtolower(trim((string) $candidate));
        if ($key === '' || !isset($columns[$key])) {
            continue;
        }

        if (empty($types) || in_array($columns[$key]['type'], $types, true)) {
            return $columns[$key]['name'];
        }
    }

    if (!empty($types)) {
        foreach ($columns as $column) {
            if (in_array($column['type'], $types, true)) {
                return $column['name'];
            }
        }
    }

    return '';
}

function seb_get_identity_columns($conn, $tableName)
{
    static $cache = [];

    $table = trim((string) $tableName);
    if ($table === '') {
        return [];
    }

    $cacheKey = strtolower($table);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $identityColumns = [];
    $sql = "SELECT c.name FROM sys.columns c JOIN sys.tables t ON t.object_id = c.object_id WHERE t.name = ? AND c.is_identity = 1";
    $stmt = @sqlsrv_query($conn, $sql, [$table]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            if ($name !== '') {
                $identityColumns[] = $name;
            }
        }
    }

    return $cache[$cacheKey] = $identityColumns;
}

function seb_resolve_column_by_candidates($conn, $tableName, array $candidates, array $types = [], $excludeIdentity = false)
{
    $columns = seb_get_table_columns_info($conn, $tableName);
    if (empty($columns)) {
        return [
            'ok' => false,
            'error' => 'Thiếu bảng ' . $tableName . ' trong SQL Server.',
        ];
    }

    if ($excludeIdentity) {
        $identityColumns = seb_get_identity_columns($conn, $tableName);
        if (!empty($identityColumns)) {
            $candidates = array_values(array_filter($candidates, function ($candidate) use ($identityColumns) {
                $key = strtolower(trim((string) $candidate));
                return $key !== '' && !in_array($key, $identityColumns, true);
            }));
        }
    }

    $column = seb_pick_column_name($columns, $candidates, $types);
    if ($excludeIdentity && $column !== '') {
        $identityColumns = seb_get_identity_columns($conn, $tableName);
        if (in_array(strtolower(trim((string) $column)), $identityColumns, true)) {
            $column = '';
        }
    }

    if ($column === '') {
        return [
            'ok' => false,
            'error' => 'Không tìm thấy cột phù hợp trong bảng ' . $tableName . '.',
        ];
    }

    return [
        'ok' => true,
        'column' => $column,
    ];
}

function seb_borrow_status_aliases()
{
    return [
        'pending' => ['pending', 'pending approval', 'chờ duyệt', 'dang cho', 'đang chờ', 'waiting', '3'],
        'approved' => ['approved', 'đã duyệt', 'da duyet', 'đang mượn', 'dang muon', 'borrowed', '1'],
        'returned' => ['returned', 'đã trả', 'da tra', '4'],
        'rejected' => ['rejected', 'bị từ chối', 'từ chối', 'tu choi', '2'],
    ];
}

function seb_get_tinh_trang_duyet_meta($conn)
{
    $statusTable = 'TinhTrangDuyet';
    $columns = seb_get_table_columns_info($conn, $statusTable);
    if (empty($columns)) {
        return [
            'ok' => false,
            'error' => 'Thiếu bảng TinhTrangDuyet trong SQL Server.',
        ];
    }

    $idCol = seb_pick_column_name($columns, ['TinhTrangDuyetID', 'MaTinhTrangDuyet', 'MaTinhTrang', 'TinhTrangID', 'ID'], ['int', 'bigint', 'smallint', 'tinyint', 'numeric', 'decimal']);
    $labelCol = seb_pick_column_name($columns, ['TenTinhTrang', 'TenTrangThai', 'TrangThai', 'Ten', 'Name', 'MoTa', 'TenHienThi'], ['nvarchar', 'varchar', 'nchar', 'char', 'text', 'ntext']);
    $codeCol = seb_pick_column_name($columns, ['MaTinhTrangDuyet', 'MaTinhTrang', 'Code', 'Slug', 'TinhTrangCode'], ['nvarchar', 'varchar', 'nchar', 'char']);

    if ($idCol === '' || $labelCol === '') {
        return [
            'ok' => false,
            'error' => 'Bảng TinhTrangDuyet thiếu cột ID hoặc cột tên trạng thái. Hãy bổ sung cột kiểu số (ví dụ MaTinhTrang) và cột tên (ví dụ TenTinhTrang).',
        ];
    }

    return [
        'ok' => true,
        'table' => $statusTable,
        'id_col' => $idCol,
        'label_col' => $labelCol,
        'code_col' => $codeCol,
    ];
}

function seb_fetch_tinh_trang_duyet_rows($conn)
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $meta = seb_get_tinh_trang_duyet_meta($conn);
    if (empty($meta['ok'])) {
        return $cache = $meta;
    }

    $selectCols = '[' . str_replace(']', ']]', $meta['id_col']) . '] AS status_id, [' . str_replace(']', ']]', $meta['label_col']) . '] AS status_label';
    if (!empty($meta['code_col'])) {
        $selectCols .= ', [' . str_replace(']', ']]', $meta['code_col']) . '] AS status_code';
    }

    $sql = 'SELECT ' . $selectCols . ' FROM [' . str_replace(']', ']]', $meta['table']) . ']';
    $stmt = sqlsrv_query($conn, $sql);
    if (!$stmt) {
        return $cache = [
            'ok' => false,
            'error' => seb_sql_error_message('Không đọc được bảng TinhTrangDuyet.'),
        ];
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = [
            'id' => (int) ($row['status_id'] ?? 0),
            'label' => trim((string) ($row['status_label'] ?? '')),
            'code' => trim((string) ($row['status_code'] ?? '')),
        ];
    }

    if (empty($rows)) {
        return $cache = [
            'ok' => false,
            'error' => 'Bảng TinhTrangDuyet đang rỗng. Hãy thêm ít nhất 3 trạng thái: Đã duyệt, Từ chối, Chờ duyệt.',
        ];
    }

    return $cache = [
        'ok' => true,
        'meta' => $meta,
        'rows' => $rows,
    ];
}

function seb_resolve_borrow_status_id($conn, $statusKey)
{
    $normalizedKey = seb_normalize_text($statusKey);
    if ($normalizedKey === '') {
        return [
            'ok' => false,
            'error' => 'Thiếu trạng thái mượn.',
        ];
    }

    if (ctype_digit($normalizedKey)) {
        return ['ok' => true, 'id' => (int) $normalizedKey];
    }

    $rowsData = seb_fetch_tinh_trang_duyet_rows($conn);
    if (empty($rowsData['ok'])) {
        return [
            'ok' => false,
            'error' => $rowsData['error'] ?? 'Thiếu bảng TinhTrangDuyet trong SQL Server.',
        ];
    }

    $aliases = seb_borrow_status_aliases();
    $possibleKeys = [$normalizedKey];
    foreach ($aliases as $canonical => $group) {
        if ($normalizedKey === $canonical || in_array($normalizedKey, $group, true)) {
            $possibleKeys = array_unique(array_merge($possibleKeys, $group, [$canonical]));
            break;
        }
    }

    foreach ($rowsData['rows'] as $row) {
        $haystacks = [
            seb_normalize_text((string) ($row['label'] ?? '')),
            seb_normalize_text((string) ($row['code'] ?? '')),
            seb_normalize_text((string) ($row['id'] ?? '')),
        ];

        foreach ($possibleKeys as $alias) {
            foreach ($haystacks as $needle) {
                if ($alias !== '' && $needle !== '' && (strpos($needle, $alias) !== false || strpos($alias, $needle) !== false)) {
                    return ['ok' => true, 'id' => (int) $row['id']];
                }
            }
        }
    }

    $labels = array_map(static function ($row) {
        return trim((string) ($row['label'] ?? ''));
    }, $rowsData['rows']);

    return [
        'ok' => false,
        'error' => 'Không tìm thấy trạng thái "' . $statusKey . '" trong bảng TinhTrangDuyet. Đang có: ' . implode(', ', $labels) . '.',
    ];
}

function seb_resolve_borrow_status_label($conn, $statusValue)
{
    $normalized = seb_normalize_text($statusValue);
    if ($normalized === '') {
        return '';
    }

    $rowsData = seb_fetch_tinh_trang_duyet_rows($conn);
    if (empty($rowsData['ok'])) {
        return trim((string) $statusValue);
    }

    $numericStatus = ctype_digit($normalized) ? (int) $normalized : null;
    foreach ($rowsData['rows'] as $row) {
        if ($numericStatus !== null && (int) $row['id'] === $numericStatus) {
            return trim((string) ($row['label'] ?? $statusValue));
        }

        $label = seb_normalize_text((string) ($row['label'] ?? ''));
        $code = seb_normalize_text((string) ($row['code'] ?? ''));
        if ($normalized !== '' && ($normalized === $label || $normalized === $code)) {
            return trim((string) ($row['label'] ?? $statusValue));
        }
    }

    return trim((string) $statusValue);
}

function seb_resolve_phieu_muon_status_column($conn)
{
    $columns = seb_get_table_columns_info($conn, 'PhieuMuon');
    if (empty($columns)) {
        return [
            'ok' => false,
            'error' => 'Thiếu bảng PhieuMuon trong SQL Server.',
        ];
    }

    // Try to find a well-known status column name. Do NOT fallback to any numeric column
    // because that may accidentally pick columns like SoLuong and cause duplicate-column SQL errors.
    $candidates = ['TinhTrangDuyetID', 'MaTinhTrangDuyet', 'MaTinhTrang', 'TinhTrangID', 'TinhTrangMuonID', 'TrangThaiID', 'StatusID', 'TinhTrangMuon', 'TinhTrang'];

    // Get identity columns on PhieuMuon and avoid selecting them (they cannot be explicitly inserted)
    $identityColumns = [];
    $idSql = "SELECT c.name FROM sys.columns c JOIN sys.tables t ON t.object_id = c.object_id WHERE t.name = 'PhieuMuon' AND c.is_identity = 1";
    $idStmt = @sqlsrv_query($conn, $idSql);
    if ($idStmt) {
        while ($r = sqlsrv_fetch_array($idStmt, SQLSRV_FETCH_ASSOC)) {
            $identityColumns[] = strtolower(trim((string) ($r['name'] ?? '')));
        }
    }

    $column = '';
    foreach ($candidates as $cand) {
        $key = strtolower(trim((string) $cand));
        if ($key === '' || !isset($columns[$key])) {
            continue;
        }
        if (!in_array($columns[$key]['type'], ['int', 'bigint', 'smallint', 'tinyint'], true)) {
            continue;
        }
        if (in_array($key, $identityColumns, true)) {
            continue;
        }
        $column = $columns[$key]['name'];
        break;
    }

    if ($column === '') {
        return [
            'ok' => false,
            'error' => 'Không tìm thấy cột trạng thái hợp lệ trong PhieuMuon. Vui lòng thêm cột trạng thái (ví dụ MaTinhTrangDuyet) hoặc cập nhật cấu trúc DB. Hiện tại bảng có các cột IDENTITY: ' . implode(', ', $identityColumns),
        ];
    }

    return [
        'ok' => true,
        'column' => $column,
    ];
}

function seb_resolve_phieu_muon_text_status_column($conn)
{
    $columns = seb_get_table_columns_info($conn, 'PhieuMuon');
    if (empty($columns)) {
        return [
            'ok' => false,
            'error' => 'Thiếu bảng PhieuMuon trong SQL Server.',
        ];
    }

    $candidates = ['TinhTrangMuon', 'TinhTrang', 'TrangThai'];
    foreach ($candidates as $cand) {
        $key = strtolower(trim((string) $cand));
        if ($key === '' || !isset($columns[$key])) {
            continue;
        }

        if (!in_array($columns[$key]['type'], ['nvarchar', 'varchar', 'nchar', 'char', 'text', 'ntext'], true)) {
            continue;
        }

        return [
            'ok' => true,
            'column' => $columns[$key]['name'],
        ];
    }

    return [
        'ok' => false,
        'error' => 'Không tìm thấy cột trạng thái text hợp lệ trong PhieuMuon.',
    ];
}

function seb_resolve_phieu_muon_device_column($conn)
{
    return seb_resolve_column_by_candidates(
        $conn,
        'PhieuMuon',
        ['IDThietBi', 'MaThietBi', 'ThietBiID', 'DeviceID', 'ID'],
        ['int', 'bigint', 'smallint', 'tinyint', 'numeric', 'decimal', 'nvarchar', 'varchar', 'nchar', 'char'],
        true
    );
}

function seb_resolve_kho_ca_nhan_device_column($conn)
{
    return seb_resolve_column_by_candidates(
        $conn,
        'KhoCaNhan',
        ['IDThietBi', 'MaThietBi', 'ThietBiID', 'DeviceID', 'ID'],
        ['int', 'bigint', 'smallint', 'tinyint', 'numeric', 'decimal', 'nvarchar', 'varchar', 'nchar', 'char']
    );
}

function seb_normalize_device_status($value)
{
    $text = strtolower(trim((string) $value));
    if ($text === '') {
        return ['code' => 'available', 'label' => 'Sẵn sàng'];
    }

    $unavailable = preg_match('/(bảo trì|bao tri|hết hàng|het hang|unavailable|ngưng|ngung|disabled|đang mượn|dang muon)/u', $text);
    return [
        'code' => $unavailable ? 'unavailable' : 'available',
        'label' => (string) $value,
    ];
}

if (!function_exists('seb_resolve_device_image')) {
    function seb_resolve_device_image($image)
    {
        $image = trim((string) $image);
        if ($image === '') {
            return '';
        }
        if (preg_match('/^(https?:\/\/|\/|\.\.\/|\.\/)/i', $image)) {
            return $image;
        }

        return '../Images/devices/' . ltrim($image, "/\\");
    }
}

/** @return array<string, array<string, mixed>> */
function seb_fetch_devices_map($conn)
{
    $map = [];
    $stmt = sqlsrv_query($conn, 'EXEC sp_XemKho');
    if (!$stmt) {
        return $map;
    }

    require_once __DIR__ . '/category_helper.php';
    $categoryMap = seb_load_category_map($conn);

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $device = seb_map_kho_row_to_device($row, $categoryMap);
        if ($device['id'] !== '') {
            $map[$device['id']] = $device;
        }
        if (!empty($device['code']) && $device['code'] !== $device['id']) {
            $map[$device['code']] = $device;
        }
    }

    return $map;
}

function seb_map_kho_row_to_device(array $row, array $categoryMap = [])
{
    require_once __DIR__ . '/category_helper.php';
    $categoryInfo = seb_resolve_category_display($row, $categoryMap);

    $status = seb_normalize_device_status($row['TinhTrang'] ?? $row['TrangThai'] ?? '');

    return [
        'id' => (string) ($row['IDThietBi'] ?? $row['ID'] ?? ''),
        'code' => (string) ($row['MaThietBi'] ?? $row['IDThietBi'] ?? $row['ID'] ?? ''),
        'name' => (string) ($row['TenThietBi'] ?? $row['Ten'] ?? 'Thiết bị'),
        'category' => (string) ($categoryInfo['name'] ?? ''),
        'subject' => 'Chung',
        'status' => $status['code'],
        'statusLabel' => $status['label'],
        'quantity' => (int) ($row['SoLuong'] ?? $row['SoLuongTon'] ?? 0),
        'description' => (string) ($row['MoTa'] ?? $row['ThongTin'] ?? $row['GhiChu'] ?? ''),
        'image' => seb_resolve_device_image($row['HinhAnh'] ?? $row['Anh'] ?? ''),
    ];
}

function seb_personal_device_exists($conn, $taiKhoan, $maThietBi)
{
    $deviceColumn = seb_resolve_kho_ca_nhan_device_column($conn);
    if (empty($deviceColumn['ok'])) {
        return false;
    }

    $stmt = sqlsrv_query(
        $conn,
        'SELECT 1 FROM KhoCaNhan WHERE TaiKhoan = ? AND [' . str_replace(']', ']]', $deviceColumn['column']) . '] = ?',
        [$taiKhoan, $maThietBi]
    );

    return $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

/** Danh sách thiết bị kho cá nhân qua sp_XemKhoCaNhan */
function seb_fetch_personal_devices($conn, $taiKhoan)
{
    $devicesMap = seb_fetch_devices_map($conn);
    $items = [];

    $stmt = sqlsrv_query($conn, 'EXEC sp_XemKhoCaNhan ?', [$taiKhoan]);
    if (!$stmt) {
        return $items;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $ma = (string) ($row['IDThietBi'] ?? $row['MaThietBi'] ?? $row['ID'] ?? '');
        if ($ma === '') {
            continue;
        }

        if (isset($devicesMap[$ma])) {
            $items[] = $devicesMap[$ma];
            continue;
        }

        $status = seb_normalize_device_status($row['TinhTrang'] ?? '');
        $items[] = [
            'id' => $ma,
            'name' => (string) ($row['TenThietBi'] ?? 'Thiết bị'),
            'category' => '',
            'subject' => 'Chung',
            'status' => $status['code'],
            'statusLabel' => $status['label'],
            'quantity' => 0,
            'description' => '',
            'image' => seb_resolve_device_image($row['HinhAnh'] ?? ''),
        ];
    }

    return $items;
}

/** Gọi sp_MuonThietBi — ghi PhieuMuon, trừ Kho, thông báo admin */
function seb_exec_muon_thiet_bi($conn, $taiKhoan, $maThietBi, $tenThietBi, $soLuong, $hanTra = null)
{
    $soPhieuMuon = 'PM-' . date('YmdHis') . '-' . substr((string) mt_rand(1000, 9999), -4);
    if ($hanTra === null) {
        $hanTra = date('Y-m-d', strtotime('+7 days'));
    }

    $params = [$soPhieuMuon, $tenThietBi, $maThietBi, $taiKhoan, (int) $soLuong, $hanTra];
    $stmt = sqlsrv_query($conn, '{CALL sp_MuonThietBi(?, ?, ?, ?, ?, ?)}', $params);

    if ($stmt === false) {
        return ['ok' => false, 'error' => seb_sql_error_message('Không mượn được thiết bị.')];
    }

    $deviceColumn = seb_resolve_kho_ca_nhan_device_column($conn);
    if (!empty($deviceColumn['ok'])) {
        sqlsrv_query($conn, 'DELETE FROM KhoCaNhan WHERE TaiKhoan = ? AND [' . str_replace(']', ']]', $deviceColumn['column']) . '] = ?', [$taiKhoan, $maThietBi]);
    }

    return ['ok' => true, 'soPhieuMuon' => $soPhieuMuon];
}

/** Tạo yêu cầu mượn (pending) — không trừ kho, chờ admin duyệt */
function seb_create_borrow_request($conn, $taiKhoan, $deviceId, $deviceCode, $tenThietBi, $soLuong, $hanTra = null)
{
    $soPhieuMuon = 'PM-' . date('YmdHis') . '-' . substr((string) mt_rand(1000, 9999), -4);
    if ($hanTra === null) {
        $hanTra = date('Y-m-d', strtotime('+7 days'));
    }

    $statusColumn = seb_resolve_phieu_muon_status_column($conn);
    $textStatusColumn = seb_resolve_phieu_muon_text_status_column($conn);
    $deviceIdColumn = seb_resolve_column_by_candidates(
        $conn,
        'PhieuMuon',
        ['IDThietBi', 'MaThietBi', 'ThietBiID', 'DeviceID', 'ID'],
        ['int', 'bigint', 'smallint', 'tinyint', 'numeric', 'decimal'],
        true
    );
    $deviceCodeColumn = seb_resolve_column_by_candidates(
        $conn,
        'PhieuMuon',
        ['MaThietBi'],
        ['nvarchar', 'varchar', 'nchar', 'char']
    );
    $statusColSql = '';
    $deviceColumnsSql = [];
    $params = [];

    if (empty($deviceIdColumn['ok']) && empty($deviceCodeColumn['ok'])) {
        return ['ok' => false, 'error' => 'Không tìm thấy cột thiết bị hợp lệ trong PhieuMuon.'];
    }

    if (!empty($deviceIdColumn['ok'])) {
        $deviceColumnsSql[] = '[' . str_replace(']', ']]', $deviceIdColumn['column']) . ']';
    }

    if (!empty($deviceCodeColumn['ok'])) {
        $deviceColumnsSql[] = '[' . str_replace(']', ']]', $deviceCodeColumn['column']) . ']';
    }

    if (!empty($statusColumn['ok'])) {
        $pendingStatus = seb_resolve_borrow_status_id($conn, 'pending');
        if (empty($pendingStatus['ok'])) {
            return ['ok' => false, 'error' => $pendingStatus['error'] ?? 'Không xác định được trạng thái chờ duyệt.'];
        }

        $pendingLabel = seb_resolve_borrow_status_label($conn, 'pending');

        $statusColSql = '[' . str_replace(']', ']]', $statusColumn['column']) . ']';
        $sqlColumns = ['SoPhieuMuon', 'TenThietBi'];
        $sqlValues = ['?', '?'];
        $params = [$soPhieuMuon, $tenThietBi];

        if (!empty($deviceIdColumn['ok'])) {
            $sqlColumns[] = '[' . str_replace(']', ']]', $deviceIdColumn['column']) . ']';
            $sqlValues[] = '?';
            $params[] = $deviceId;
        }

        if (!empty($deviceCodeColumn['ok'])) {
            $sqlColumns[] = '[' . str_replace(']', ']]', $deviceCodeColumn['column']) . ']';
            $sqlValues[] = '?';
            $params[] = $deviceCode;
        }

        $sqlColumns[] = 'TaiKhoan';
        $sqlColumns[] = 'SoLuong';
        $sqlColumns[] = 'NgayMuon';
        $sqlColumns[] = 'HanTra';
        $sqlColumns[] = $statusColSql;
        $sqlValues[] = '?';
        $sqlValues[] = '?';
        $sqlValues[] = 'GETDATE()';
        $sqlValues[] = '?';
        $sqlValues[] = '?';
        $params[] = $taiKhoan;
        $params[] = (int) $soLuong;
        $params[] = $hanTra;
        $params[] = (int) $pendingStatus['id'];

        if (!empty($textStatusColumn['ok'])) {
            $sqlColumns[] = '[' . str_replace(']', ']]', $textStatusColumn['column']) . ']';
            $sqlValues[] = '?';
            $params[] = $pendingLabel;
        }

        $sql = 'INSERT INTO PhieuMuon (' . implode(', ', $sqlColumns) . ') VALUES (' . implode(', ', $sqlValues) . ')';
    } else {
        // No numeric status-id column available. Try to fallback to textual column 'TinhTrangMuon' if present.
        $columns = seb_get_table_columns_info($conn, 'PhieuMuon');
        $textColKey = '';
        foreach ($columns as $k => $cinfo) {
            if (in_array(strtolower($cinfo['name']), ['tinhtrangmuon', 'tinhtrang', 'trangthai'], true) && in_array($cinfo['type'], ['nvarchar', 'varchar', 'nchar', 'char', 'text', 'ntext'], true)) {
                $textColKey = $cinfo['name'];
                break;
            }
        }

        if ($textColKey === '') {
            return ['ok' => false, 'error' => $statusColumn['error'] ?? 'Thiếu cột trạng thái hợp lệ trong PhieuMuon.'];
        }

        $pendingLabel = seb_resolve_borrow_status_label($conn, 'pending');
        $statusColSql = '[' . str_replace(']', ']]', $textColKey) . ']';
        $sqlColumns = ['SoPhieuMuon', 'TenThietBi'];
        $sqlValues = ['?', '?'];
        $params = [$soPhieuMuon, $tenThietBi];

        if (!empty($deviceIdColumn['ok'])) {
            $sqlColumns[] = '[' . str_replace(']', ']]', $deviceIdColumn['column']) . ']';
            $sqlValues[] = '?';
            $params[] = $deviceId;
        }

        if (!empty($deviceCodeColumn['ok'])) {
            $sqlColumns[] = '[' . str_replace(']', ']]', $deviceCodeColumn['column']) . ']';
            $sqlValues[] = '?';
            $params[] = $deviceCode;
        }

        $sqlColumns[] = 'TaiKhoan';
        $sqlColumns[] = 'SoLuong';
        $sqlColumns[] = 'NgayMuon';
        $sqlColumns[] = 'HanTra';
        $sqlColumns[] = $statusColSql;
        $sqlValues[] = '?';
        $sqlValues[] = '?';
        $sqlValues[] = 'GETDATE()';
        $sqlValues[] = '?';
        $sqlValues[] = '?';
        $params[] = $taiKhoan;
        $params[] = (int) $soLuong;
        $params[] = $hanTra;
        $params[] = $pendingLabel;

        if (!empty($textStatusColumn['ok'])) {
            $sqlColumns[] = '[' . str_replace(']', ']]', $textStatusColumn['column']) . ']';
            $sqlValues[] = '?';
            $params[] = $pendingLabel;
        }

        $sql = 'INSERT INTO PhieuMuon (' . implode(', ', $sqlColumns) . ') VALUES (' . implode(', ', $sqlValues) . ')';
    }

    // Log the INSERT SQL and parameters for debugging borrow failures
    $debugLog = __DIR__ . '/../logs/borrow_debug.txt';
    $logEntry = date('c') . "\tINSERT_PHIEUMUON\tSQL=" . str_replace("\n", ' ', $sql) . "\tPARAMS=" . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents($debugLog, $logEntry, FILE_APPEND | LOCK_EX);

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errMsg = seb_sql_error_message('Không tạo được yêu cầu mượn.');
        $errLog = date('c') . "\tINSERT_FAILED\tuser=" . (string)$taiKhoan . "\tmsg=" . str_replace("\n", ' ', $errMsg) . "\n";
        @file_put_contents($debugLog, $errLog, FILE_APPEND | LOCK_EX);

        return ['ok' => false, 'error' => $errMsg];
    }

    return ['ok' => true, 'soPhieuMuon' => $soPhieuMuon];
}

/** Gọi sp_ThemKhoCaNhan */
function seb_exec_them_kho_ca_nhan($conn, $taiKhoan, $maThietBi)
{
    if (seb_personal_device_exists($conn, $taiKhoan, $maThietBi)) {
        return ['ok' => true, 'duplicate' => true];
    }

    $stmt = sqlsrv_query($conn, 'EXEC sp_ThemKhoCaNhan ?, ?', [$taiKhoan, $maThietBi]);
    if ($stmt === false) {
        return ['ok' => false, 'error' => seb_sql_error_message('Không thêm được vào kho cá nhân.')];
    }

    return ['ok' => true, 'duplicate' => false];
}

/** Lịch sử mượn từ bảng PhieuMuon */
function seb_is_kho_maintenance_status($value)
{
    $text = mb_strtolower(trim((string) $value), 'UTF-8');
    if ($text === '') {
        return false;
    }

    return strpos($text, 'bảo trì') !== false
        || strpos($text, 'bao tri') !== false
        || strpos($text, 'hỏng') !== false
        || strpos($text, 'hong') !== false
        || strpos($text, 'hết hàng') !== false
        || strpos($text, 'het hang') !== false;
}

/**
 * Ảnh thiết bị cho trang chủ — ưu tiên cột Kho.HinhAnh, sau đó file theo MaThietBi.
 */
function seb_resolve_index_equipment_image($maThietBi, $hinhAnh = null)
{
    $hinhAnh = trim((string) $hinhAnh);
    if ($hinhAnh !== '') {
        if (preg_match('/^(https?:\/\/|\/|\.\.\/|\.\/)/i', $hinhAnh)) {
            return $hinhAnh;
        }

        return 'Images/devices/' . ltrim(str_replace('\\', '/', $hinhAnh), '/');
    }

    $code = trim((string) $maThietBi);
    if ($code === '') {
        return '';
    }

    $baseDir = dirname(__DIR__) . '/Images/devices/';
    $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    foreach ($extensions as $ext) {
        $path = $baseDir . $code . '.' . $ext;
        if (is_file($path)) {
            return 'Images/devices/' . $code . '.' . $ext;
        }
    }

    return '';
}

/** Thống kê panel trang chủ — đọc trực tiếp bảng Kho (sp_XemKho) + PhieuMuon. */
function seb_load_index_dashboard_stats($conn)
{
    $stats = [
        'total_devices' => 0,
        'maintenance_devices' => 0,
        'borrowed_devices' => 0,
    ];

    if (empty($conn) || !function_exists('sqlsrv_query')) {
        return $stats;
    }

    $khoSql = "SELECT
            ISNULL(SUM(CAST(SoLuong AS INT)), 0) AS TongTonKho,
            ISNULL(SUM(CASE WHEN TinhTrang = N'Sẵn sàng' THEN CAST(SoLuong AS INT) ELSE 0 END), 0) AS SanSang,
            ISNULL(SUM(CASE WHEN TinhTrang = N'Bảo trì' THEN CAST(SoLuong AS INT) ELSE 0 END), 0) AS BaoTri
        FROM Kho";

    $khoStmt = sqlsrv_query($conn, $khoSql);
    if ($khoStmt && ($khoRow = sqlsrv_fetch_array($khoStmt, SQLSRV_FETCH_ASSOC))) {
        $stats['total_devices'] = (int) ($khoRow['TongTonKho'] ?? 0);
        $stats['maintenance_devices'] = (int) ($khoRow['BaoTri'] ?? 0);
    } else {
        $fallbackStmt = sqlsrv_query($conn, 'EXEC sp_XemKho');
        if ($fallbackStmt) {
            while ($row = sqlsrv_fetch_array($fallbackStmt, SQLSRV_FETCH_ASSOC)) {
                $qty = max(0, (int) ($row['SoLuong'] ?? 0));
                $status = trim((string) ($row['TinhTrang'] ?? ''));
                $stats['total_devices'] += $qty;

                if ($status === 'Bảo trì') {
                    $stats['maintenance_devices'] += $qty;
                }
            }
        }
    }

    $statusInfo = seb_resolve_phieu_muon_status_column($conn);
    $approvedStatus = seb_resolve_borrow_status_id($conn, 'approved');
    if (!empty($statusInfo['ok']) && !empty($approvedStatus['ok'])) {
        $statusCol = '[' . str_replace(']', ']]', $statusInfo['column']) . ']';
        $borrowSql = "SELECT ISNULL(SUM(CAST(SoLuong AS INT)), 0) AS borrowed_qty
                      FROM PhieuMuon
                      WHERE {$statusCol} = ?";
        $borrowStmt = sqlsrv_query($conn, $borrowSql, [(int) $approvedStatus['id']]);
        if ($borrowStmt && ($borrowRow = sqlsrv_fetch_array($borrowStmt, SQLSRV_FETCH_ASSOC))) {
            $stats['borrowed_devices'] = (int) ($borrowRow['borrowed_qty'] ?? 0);
        }
    }

    if ($stats['borrowed_devices'] === 0) {
        $overviewStmt = sqlsrv_query($conn, 'EXEC sp_ThongKeTongQuan');
        if ($overviewStmt && ($overview = sqlsrv_fetch_array($overviewStmt, SQLSRV_FETCH_ASSOC))) {
            $stats['borrowed_devices'] = (int) ($overview['DangMuon'] ?? 0);
        }
    }

    return $stats;
}

/**
 * Phiếu mượn gần đây — PhieuMuon join Kho, sắp xếp theo NgayMuon (mọi trạng thái).
 */
function seb_fetch_recent_phieu_muon($conn, $limit = 9)
{
    $items = [];
    if (empty($conn) || !function_exists('sqlsrv_query')) {
        return $items;
    }

    $limit = max(1, (int) $limit);
    $statusInfo = seb_resolve_phieu_muon_status_column($conn);
    $statusSelect = !empty($statusInfo['ok'])
        ? ('pm.[' . str_replace(']', ']]', $statusInfo['column']) . '] AS TinhTrangMuon')
        : 'pm.TinhTrangMuon';
    $sql = "SELECT TOP ($limit)
                pm.SoPhieuMuon,
                pm.MaThietBi,
                pm.TenThietBi AS TenThietBiPhieu,
                pm.SoLuong,
                pm.NgayMuon,
                {$statusSelect},
                pm.TaiKhoan,
                k.TenThietBi AS TenThietBiKho,
                k.HinhAnh,
                k.TinhTrang AS TinhTrangKho,
                k.SoLuong AS SoLuongKho
            FROM PhieuMuon pm
            LEFT JOIN Kho k ON k.MaThietBi = pm.MaThietBi
            ORDER BY pm.NgayMuon DESC, pm.SoPhieuMuon DESC";

    $stmt = sqlsrv_query($conn, $sql);
    if (!$stmt) {
        return $items;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $ma = (string) ($row['MaThietBi'] ?? '');
        $tenKho = trim((string) ($row['TenThietBiKho'] ?? ''));
        $tenPhieu = trim((string) ($row['TenThietBiPhieu'] ?? ''));
        $ten = $tenKho !== '' ? $tenKho : $tenPhieu;

        $items[] = [
            'soPhieuMuon' => (string) ($row['SoPhieuMuon'] ?? ''),
            'id' => $ma,
            'name' => $ten !== '' ? $ten : 'Thiết bị',
            'quantity' => max(1, (int) ($row['SoLuong'] ?? 1)),
            'username' => (string) ($row['TaiKhoan'] ?? ''),
            'borrowStatus' => seb_resolve_borrow_status_label($conn, $row['TinhTrangMuon'] ?? ''),
            'khoStatus' => trim((string) ($row['TinhTrangKho'] ?? '')),
            'khoQuantity' => (int) ($row['SoLuongKho'] ?? 0),
            'ngayMuon' => $row['NgayMuon'] ?? null,
            'image' => seb_resolve_index_equipment_image($ma, $row['HinhAnh'] ?? ''),
        ];
    }

    return $items;
}

function seb_fetch_phieu_muon_by_user($conn, $taiKhoan)
{
    $statusInfo = seb_resolve_phieu_muon_status_column($conn);
    $statusSelect = !empty($statusInfo['ok'])
        ? ('[' . str_replace(']', ']]', $statusInfo['column']) . '] AS TinhTrangMuon')
        : 'TinhTrangMuon';
    $sql = 'SELECT SoPhieuMuon, TenThietBi, MaThietBi, SoLuong, NgayMuon, HanTra, ' . $statusSelect . '
            FROM PhieuMuon
            WHERE TaiKhoan = ?
            ORDER BY NgayMuon DESC';
    $stmt = sqlsrv_query($conn, $sql, [$taiKhoan]);
    $items = [];
    $devicesMap = seb_fetch_devices_map($conn);

    if (!$stmt) {
        return $items;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $ma = (string) ($row['MaThietBi'] ?? '');
        $base = $devicesMap[$ma] ?? [
            'id' => $ma,
            'name' => (string) ($row['TenThietBi'] ?? $ma),
            'category' => '',
            'subject' => 'Chung',
            'status' => 'available',
            'statusLabel' => '',
            'quantity' => (int) ($row['SoLuong'] ?? 1),
            'description' => '',
            'image' => '',
        ];

        $items[] = array_merge($base, [
            'borrowId' => (string) ($row['SoPhieuMuon'] ?? ''),
            'borrowDate' => seb_datetime_iso($row['NgayMuon'] ?? null),
            'borrowStatus' => seb_resolve_borrow_status_label($conn, $row['TinhTrangMuon'] ?? ''),
            'hanTra' => seb_datetime_iso($row['HanTra'] ?? null),
            'soLuong' => (int) ($row['SoLuong'] ?? 1),
        ]);
    }

    return $items;
}
