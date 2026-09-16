<?php
require_once 'connect.php';
header('Content-Type: application/json; charset=utf-8');
$out = ['ok' => false, 'deleted' => 0, 'error' => null];
if (!isset($conn) || !$conn) { $out['error'] = 'No DB connection'; echo json_encode($out, JSON_UNESCAPED_UNICODE); exit; }

$username = 'auto_user_test';
$sql = "DELETE FROM TaiKhoan WHERE TaiKhoan = ?";
$stmt = sqlsrv_query($conn, $sql, [$username]);
if ($stmt === false) {
    $out['error'] = sqlsrv_errors();
    echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}

$out['ok'] = true;
$out['deleted'] = sqlsrv_rows_affected($stmt);
$out['message'] = 'Delete executed';
echo json_encode($out, JSON_UNESCAPED_UNICODE);
