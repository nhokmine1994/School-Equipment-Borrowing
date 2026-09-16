<?php

include "connect.php";

$sql = "SELECT *
    FROM Kho k
    LEFT JOIN DanhMuc dm ON k.MaDanhMuc = dm.MaDanhMuc";

$query = sqlsrv_query($conn, $sql);

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}

while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)){
    $deviceId = $row['IDThietBi'] ?? $row['ID'] ?? $row['MaThietBi'] ?? '';
    echo $deviceId . " - ";
    echo $row['TenThietBi'] . " - ";
    echo $row['SoLuong'] . " - ";
    echo $row['TenDanhMuc'];
    echo "<br>";
}

?>