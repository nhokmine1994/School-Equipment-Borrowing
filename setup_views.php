<?php
include 'c:/xampp/htdocs/SEB/connect.php';
$sql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='PageViews' AND xtype='U')
        CREATE TABLE PageViews (
            ViewDate DATE PRIMARY KEY,
            ViewsCount INT NOT NULL DEFAULT 0
        )";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    echo "Table PageViews created successfully.";
} else {
    print_r(sqlsrv_errors());
}
