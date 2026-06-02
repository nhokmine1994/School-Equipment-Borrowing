<?php
require 'connect.php';

$sql = "SELECT SUM(SoLuong) AS totalQty FROM Kho";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    echo 'DB_total_qty=' . (int)($row['totalQty'] ?? 0) . "\n";
} else {
    echo 'ERROR: ' . print_r(sqlsrv_errors(), true);
}

// Also call sp_ThongKeTongQuan if exists
$overview = sqlsrv_query($conn, "EXEC sp_ThongKeTongQuan");
if ($overview) {
    $ov = sqlsrv_fetch_array($overview, SQLSRV_FETCH_ASSOC);
    echo "SP_ThongKeTongQuan: ";
    print_r($ov);
}
?>