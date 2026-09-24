<?php

function seb_load_category_map($conn)
{
    $categoryMap = array();

    if (empty($conn) || !function_exists('sqlsrv_query')) {
        return $categoryMap;
    }

    $stmt = sqlsrv_query($conn, 'SELECT MaDanhMuc, TenDanhMuc FROM DanhMuc ORDER BY TenDanhMuc');
    if ($stmt === false) {
        return $categoryMap;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $categoryKey = trim((string) ($row['MaDanhMuc'] ?? ''));
        if ($categoryKey !== '') {
            $categoryMap[$categoryKey] = trim((string) ($row['TenDanhMuc'] ?? ''));
        }
    }

    return $categoryMap;
}

function seb_resolve_category_display($row, $categoryMap)
{
    $categoryNameToCode = array_flip($categoryMap);

    $maDanhMuc = trim((string) ($row['MaDanhMuc'] ?? ''));
    $rawDanhMuc = trim((string) ($row['DanhMuc'] ?? $row['TenDanhMuc'] ?? $row['LoaiThietBi'] ?? $row['Nhom'] ?? ''));
    $tenDanhMuc = $rawDanhMuc;

    if ($maDanhMuc !== '' && isset($categoryMap[$maDanhMuc])) {
        $tenDanhMuc = $categoryMap[$maDanhMuc];
    } elseif ($rawDanhMuc !== '') {
        if (isset($categoryMap[$rawDanhMuc])) {
            $tenDanhMuc = $categoryMap[$rawDanhMuc];
            $maDanhMuc = $rawDanhMuc;
        } elseif (isset($categoryNameToCode[$rawDanhMuc])) {
            $maDanhMuc = $categoryNameToCode[$rawDanhMuc];
            $tenDanhMuc = $categoryMap[$maDanhMuc] ?? $rawDanhMuc;
        }
    }

    return array(
        'code' => $maDanhMuc,
        'name' => $tenDanhMuc,
    );
}
