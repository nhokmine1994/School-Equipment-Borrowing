<?php
require_once 'connect.php';
header('Content-Type: application/json; charset=utf-8');
$out = ['ok' => false, 'error' => null];
if (!isset($conn) || !$conn) { $out['error'] = 'No DB connection'; echo json_encode($out, JSON_UNESCAPED_UNICODE); exit; }

$username = 'auto_user_test';
$password = 'Pass1234';
$fullname = 'Auto Test';
$phone = '';
$boMon = 'Tin học';

// avoid duplicate
$check = sqlsrv_query($conn, "SELECT 1 FROM TaiKhoan WHERE TaiKhoan = ?", [$username]);
if ($check && sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC)) {
    $out['ok'] = false; $out['error'] = 'User exists'; echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$sql = "INSERT INTO TaiKhoan (TaiKhoan, MatKhau, LoaiTaiKhoan, HoVaTen, SoDienThoai, BoMon) VALUES (?, ?, 'user', ?, ?, ?)";
$params = [$username, $hash, $fullname, $phone, $boMon];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $out['ok'] = false;
    $out['error'] = sqlsrv_errors();
    echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}
$out['ok'] = true; $out['message'] = 'Created user ' . $username;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
