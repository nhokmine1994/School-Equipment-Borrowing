<?php
require_once 'connect.php';
header('Content-Type: application/json; charset=utf-8');
$out = ['ok' => false, 'error' => null, 'columns' => [], 'rows' => []];
if (!isset($conn) || !$conn) {
    $out['error'] = 'Không kết nối DB. Kiểm tra connect.php.';
    echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

// Get columns for dbo.TaiKhoan
$colsSql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'TaiKhoan'
            ORDER BY ORDINAL_POSITION";
$colsStmt = sqlsrv_query($conn, $colsSql);
if ($colsStmt) {
    while ($c = sqlsrv_fetch_array($colsStmt, SQLSRV_FETCH_ASSOC)) {
        $out['columns'][] = $c;
    }
} else {
    $out['error'] = 'Không lấy được cấu trúc bảng TaiKhoan.';
}

// Get sample rows
$rowsStmt = sqlsrv_query($conn, "SELECT TOP 50 * FROM TaiKhoan ORDER BY TaiKhoan ASC");
if ($rowsStmt) {
    while ($r = sqlsrv_fetch_array($rowsStmt, SQLSRV_FETCH_ASSOC)) {
        // convert SQLSRV resources to strings if any
        foreach ($r as $k => $v) {
            if (is_resource($v)) {
                $r[$k] = '[resource]';
            }
        }
        $out['rows'][] = $r;
    }
}

$out['ok'] = true;
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
