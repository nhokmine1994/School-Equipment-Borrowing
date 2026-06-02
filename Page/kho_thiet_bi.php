<?php
session_start();
include '../connect.php';
require_once __DIR__ . '/../components/category_helper.php';

$is_logged_in = !empty($_SESSION['user']) && !empty($_SESSION['user']['username']);
$userSession = $is_logged_in && is_array($_SESSION['user']) ? $_SESSION['user'] : [];
$username = $is_logged_in ? (string) ($userSession['username'] ?? '') : '';
$displayName = $is_logged_in ? trim((string) ($userSession['display_name'] ?? $userSession['full_name'] ?? $userSession['fullName'] ?? $userSession['FullName'] ?? $userSession['username'])) : '';
$userRole = $is_logged_in ? strtolower(trim((string) ($userSession['role'] ?? ''))) : '';

$sql = "EXEC sp_XemKho";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

function normalizeDeviceStatus($value)
{
    $text = strtolower(trim((string) $value));

    if ($text === '') {
        return 'available';
    }

    if (preg_match('/(bảo trì|bao tri|hết hàng|het hang|unavailable|ngưng|ngung|disabled)/u', $text)) {
        return 'unavailable';
    }

    return 'available';
}

function resolveDeviceImage($row)
{
    $image = trim((string) ($row['HinhAnh'] ?? $row['Anh'] ?? $row['Image'] ?? $row['ImagePath'] ?? ''));

    if ($image === '') {
        return '';
    }

    if (preg_match('/^(https?:\/\/|\/|\.\.\/|\.\/)/i', $image)) {
        return $image;
    }

    return '../Images/devices/' . ltrim($image, "/\\");
}

$categoryMap = seb_load_category_map($conn);

$devices = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $categoryInfo = seb_resolve_category_display($row, $categoryMap);
    $dbId = (string) ($row['IDThietBi'] ?? $row['ID'] ?? '');
    $deviceCode = (string) ($row['MaThietBi'] ?? $row['IDThietBi'] ?? $row['ID'] ?? '');

    $devices[] = [
        'dbId' => $dbId,
        'id' => $deviceCode,
        'name' => (string) ($row['TenThietBi'] ?? $row['Ten'] ?? 'Thiết bị'),
        'category' => $categoryInfo['name'],
        'subject' => 'Chung',
        'status' => normalizeDeviceStatus($row['TinhTrang'] ?? $row['TrangThai'] ?? ''),
        'statusLabel' => (string) ($row['TinhTrang'] ?? $row['TrangThai'] ?? 'Sẵn sàng'),
        'quantity' => (int) ($row['SoLuong'] ?? $row['SoLuongTon'] ?? 0),
        'description' => (string) ($row['MoTa'] ?? $row['ThongTin'] ?? $row['GhiChu'] ?? ''),
        'image' => resolveDeviceImage($row),
        'idActive' => (int) ($row['IDActive'] ?? 2),
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEB - Kho thiết bị</title>
    <meta name="description"
        content="Kho thiết bị SEB: tra cứu, lọc và mượn thiết bị dạy học theo danh mục và môn học.">
    <link rel="stylesheet" href="../CSS/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/kho.css?v=20260521">
    <link rel="stylesheet" href="../CSS/kho-pages.css?v=20260521">
</head>

<body>
    <div class="system-container">
        <header class="header-banner">
            <div class="header-logo-box">
                <img src="../Images/logo.png" alt="Logo Lộc An">
            </div>
            <div class="header-title-group">
                <h2>TRƯỜNG TRUNG HỌC CƠ SỞ LỘC AN</h2>
                <h1>HỆ THỐNG MƯỢN/TRẢ THIẾT BỊ ( SEB )</h1>
            </div>
            <div class="header-right">
                <button class="icon-btn"><i class="far fa-user"></i></button>
                <button class="icon-btn"><i class="fas fa-pencil-alt"></i></button>
            </div>
        </header>

        <nav class="nav-bar" id="mainNav">
            <div class="nav-links" id="navLinks">
                <a href="../index.php" class="nav-tab">Trang chủ</a>
                <a href="kho_thiet_bi.php" class="nav-tab active">Kho thiết bị</a>
                <a href="dang-ky-phong-hoc.php" class="nav-tab">Đăng ký phòng học</a>
                <a href="kho-ca-nhan.php" class="nav-tab">Kho cá nhân</a>
                <a href="news.php" class="nav-tab">Tin tức</a>
                <a href="about.php" class="nav-tab">Liên hệ</a>
            </div>
        </nav>

        <section class="section-wrapper">
            <div class="section-heading">
                <i class="fas fa-boxes-stacked"></i>
                Kho thiết bị
            </div>

            <div id="app" class="kho-app">
                <aside class="sidebar">
                    <h3 class="collapsible-title">Danh mục <i class="fas fa-chevron-down toggle-icon"></i></h3>
                    <div class="collapsible-content" id="category-filters">
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="CNTT"
                                data-type="category"> CNTT
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Âm thanh"
                                data-type="category"> Âm thanh
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Trình chiếu"
                                data-type="category"> Trình chiếu
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Phòng lab"
                                data-type="category"> Phòng lab
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Dụng cụ thực hành"
                                data-type="category"> Dụng cụ thực hành
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Hóa chất"
                                data-type="category"> Hóa chất
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Khác"
                                data-type="category"> Khác
                        </label>
                    </div>

                    </div>
                    
                    <h3 class="kho-subject-title collapsible-title">Môn học <i class="fas fa-chevron-down toggle-icon"></i></h3>
                    <div class="collapsible-content" id="subject-filters">
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Vật lý"
                                data-type="subject"> Vật lý
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Hóa học"
                                data-type="subject"> Hóa học
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Sinh học"
                                data-type="subject"> Sinh học
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Tin học"
                                data-type="subject"> Tin học
                        </label>
                    </div>
                    <div class="filter-group kho-filter-group">
                        <label class="kho-filter-label">
                            <input type="checkbox" class="filter-checkbox kho-filter-checkbox" value="Chung"
                                data-type="subject"> Khác / Dùng chung
                        </label>
                    </div>
                    </div>
                </aside>

                <main class="content">
                    <h1>Kho Thiết Bị</h1>
                    <div class="search-bar kho-search-wrap">
                        <input type="text" id="search-input" placeholder="Nhập tên thiết bị để tìm kiếm..."
                            class="kho-search-input">
                    </div>
                    <div class="equipment-grid" id="equipment-grid">
                        <div class="empty-state">
                            <h3>Đang tải dữ liệu</h3>
                            <p>Dữ liệu thiết bị sẽ được hiển thị từ SQL Server.</p>
                        </div>
                    </div>

                    <div id="pagination-container" class="pagination"></div>
                </main>
            </div>
        </section>

        <footer class="footer">
            <div class="footer-content">
                Copyright 2026<br>
                Website này là tài sản thuộc quyền sở hữu hợp pháp của SEB.<br>
                Nội dung, hình ảnh và mã nguồn được bảo hộ theo quy định pháp luật hiện hành.<br>
                Mọi thông tin chi tiết xin liên hệ<br>
                Hotline: 0344655621
            </div>
            <div class="footer-tip">
                <i class="far fa-lightbulb"></i>
                Bạn có thể quét mã QR để mượn, trả và báo hỏng thiết bị nhanh chóng !
            </div>
        </footer>
    </div>

    <script>
        window.DEVICES_DATA = <?php echo json_encode($devices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script>
        window.__is_logged_in = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        window.__display_name = <?php echo json_encode($displayName); ?>;
        window.__user_role = <?php echo json_encode($userRole); ?>;
        window.__username = <?php echo json_encode($username); ?>;
    </script>
    <script src="../Javascript/toast.js?v=20260522"></script>
    <script src="../Javascript/Java.js?v=20260520"></script>
    <script src="../Javascript/seb_api.js?v=20260521_statusid"></script>
    <script src="../Javascript/device_modal.js?v=20260521_request"></script>
    <script src="../Javascript/kho_thiet_bi.js?v=20260521_request"></script>

</body>

</html>