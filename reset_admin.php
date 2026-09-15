<?php
require __DIR__ . '/connect.php';

if (!$conn) {
    fwrite(STDERR, "NO_DATABASE_CONNECTION\n");
    exit(1);
}

$username = 'admin';
$newPassword = 'Admin@123';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$existsStmt = sqlsrv_query($conn, 'SELECT 1 FROM TaiKhoan WHERE TaiKhoan = ?', [$username]);
if ($existsStmt) {
    $row = sqlsrv_fetch_array($existsStmt, SQLSRV_FETCH_ASSOC);
    if ($row) {
        $stmt = sqlsrv_query(
            $conn,
            'UPDATE TaiKhoan SET MatKhau = ?, LoaiTaiKhoan = ?, HoVaTen = N\'Quản trị viên\', Email = N\'admin@seb.edu.vn\' WHERE TaiKhoan = ?',
            [$hash, 'admin', $username]
        );
        if (!$stmt) {
            fwrite(STDERR, "UPDATE_FAILED\n");
            var_dump(sqlsrv_errors());
            exit(1);
        }
    } else {
        $stmt = sqlsrv_query(
            $conn,
            'INSERT INTO TaiKhoan (TaiKhoan, MatKhau, LoaiTaiKhoan, HoVaTen, Email) VALUES (?, ?, ?, N\'Quản trị viên\', N\'admin@seb.edu.vn\')',
            [$username, $hash, 'admin']
        );
        if (!$stmt) {
            fwrite(STDERR, "INSERT_FAILED\n");
            var_dump(sqlsrv_errors());
            exit(1);
        }
    }
} else {
    fwrite(STDERR, "EXISTS_CHECK_FAILED\n");
    var_dump(sqlsrv_errors());
    exit(1);
}

$checkStmt = sqlsrv_query($conn, 'SELECT TaiKhoan, MatKhau, LoaiTaiKhoan FROM TaiKhoan WHERE TaiKhoan = ?', [$username]);
if (!$checkStmt) {
    fwrite(STDERR, "CHECK_FAILED\n");
    var_dump(sqlsrv_errors());
    exit(1);
}
$row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
$verified = $row && password_verify($newPassword, trim((string) ($row['MatKhau'] ?? '')));

echo json_encode([
    'username' => $row['TaiKhoan'] ?? $username,
    'role' => $row['LoaiTaiKhoan'] ?? 'admin',
    'password' => $newPassword,
    'verified' => $verified,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
