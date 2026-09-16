<?php
require_once 'connect.php';
header('Content-Type: application/json; charset=utf-8');
$out = ['ok' => false, 'message' => '', 'executed' => false];
if (!isset($conn) || !$conn) {
    $out['message'] = 'No DB connection.';
    echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

// Check if column MonHoc exists
$check = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'TaiKhoan' AND COLUMN_NAME = 'MonHoc'");
$exists = false;
if ($check) {
    if (sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC)) {
        $exists = true;
    }
}

if (!$exists) {
    $out['ok'] = true;
    $out['message'] = 'Column MonHoc does not exist; nothing to do.';
    echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

// Attempt to drop column
$drop = sqlsrv_query($conn, "ALTER TABLE dbo.TaiKhoan DROP COLUMN MonHoc");
if ($drop === false) {
    $out['message'] = 'Failed to drop MonHoc column.';
    $errors = sqlsrv_errors();
    $out['errors'] = $errors;
    echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$out['ok'] = true;
$out['executed'] = true;
$out['message'] = 'Dropped MonHoc column successfully.';
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
