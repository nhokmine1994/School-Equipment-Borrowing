<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Thống kê & Báo cáo');
require_once __DIR__ . '/../components/seb_db.php';
require_once __DIR__ . '/../components/admin_layout.php';

$username = $_SESSION['user']['username'] ?? '';

// 1. Fetch Basic Stats & Inventory Health
$totalDevices = 0;
$healthStats = [
    'ready' => 0,
    'broken' => 0,
    'maintenance' => 0
];

$deviceSql = "EXEC sp_XemKho";
$deviceStmt = sqlsrv_query($conn, $deviceSql);
if ($deviceStmt) {
    while ($item = sqlsrv_fetch_array($deviceStmt, SQLSRV_FETCH_ASSOC)) {
        $qty = max(0, (int) ($item['SoLuong'] ?? $item['SoLuongTon'] ?? 0));
        $totalDevices += $qty;
        
        $status = mb_strtolower(trim((string)($item['TinhTrang'] ?? '')), 'UTF-8');
        if (strpos($status, 'hỏng') !== false || strpos($status, 'hong') !== false || strpos($status, 'broken') !== false) {
            $healthStats['broken'] += $qty;
        } elseif (strpos($status, 'bảo trì') !== false || strpos($status, 'bao tri') !== false || strpos($status, 'unavailable') !== false) {
            $healthStats['maintenance'] += $qty;
        } else {
            $healthStats['ready'] += $qty;
        }
    }
}

$totalUsers = 0;
$userSql = "SELECT COUNT(*) AS Total FROM TaiKhoan";
$userStmt = sqlsrv_query($conn, $userSql);
if ($userStmt && $row = sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC)) {
    $totalUsers = $row['Total'] ?? 0;
}

// Prepare table info for borrows
$borrowStatusInfo = seb_resolve_phieu_muon_status_column($conn);
$borrowStatusColumn = !empty($borrowStatusInfo['ok']) ? ('[' . str_replace(']', ']]', $borrowStatusInfo['column']) . ']') : 'TrangThai';
$tableName = !empty($borrowStatusInfo['ok']) ? 'PhieuMuon' : 'Borrows';

// 2. Fetch Borrow Stats
$borrowStats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'returned' => 0
];
$borrowSql = "SELECT {$borrowStatusColumn} AS TrangThai, COUNT(*) AS Total FROM {$tableName} GROUP BY {$borrowStatusColumn}";
$borrowStmt = sqlsrv_query($conn, $borrowSql);
if ($borrowStmt) {
    while ($row = sqlsrv_fetch_array($borrowStmt, SQLSRV_FETCH_ASSOC)) {
        $status = mb_strtolower(trim((string)$row['TrangThai']), 'UTF-8');
        $count = (int)$row['Total'];
        if (in_array($status, ['pending', 'chờ duyệt', 'waiting', '3'])) $borrowStats['pending'] += $count;
        elseif (in_array($status, ['approved', 'đã duyệt', 'da duyet', '1'])) $borrowStats['approved'] += $count;
        elseif (in_array($status, ['rejected', 'từ chối', '2'])) $borrowStats['rejected'] += $count;
        elseif (in_array($status, ['returned', 'đã trả', 'da tra', '4'])) $borrowStats['returned'] += $count;
    }
}

// 3. Top Borrowed Devices (Thiết bị mượn nhiều nhất)
$topDevices = [];
$topDeviceSql = "SELECT TOP 5 TenThietBi, SUM(SoLuong) as TotalBorrowed 
                 FROM {$tableName} 
                 WHERE {$borrowStatusColumn} NOT IN ('rejected', 'từ chối', '2', 'pending', 'chờ duyệt', 'waiting', '3')
                 GROUP BY TenThietBi 
                 ORDER BY TotalBorrowed DESC";
$topDeviceStmt = sqlsrv_query($conn, $topDeviceSql);
if ($topDeviceStmt) {
    while ($row = sqlsrv_fetch_array($topDeviceStmt, SQLSRV_FETCH_ASSOC)) {
        $topDevices[] = $row;
    }
}

// 4. Top Active Users (Người mượn tích cực)
$topUsers = [];
$topUserSql = "SELECT TOP 5 pm.TaiKhoan, tk.HoVaTen, COUNT(pm.SoPhieuMuon) as TotalRequests
               FROM {$tableName} pm
               LEFT JOIN TaiKhoan tk ON pm.TaiKhoan = tk.TaiKhoan
               GROUP BY pm.TaiKhoan, tk.HoVaTen
               ORDER BY TotalRequests DESC";
$topUserStmt = sqlsrv_query($conn, $topUserSql);
if ($topUserStmt) {
    while ($row = sqlsrv_fetch_array($topUserStmt, SQLSRV_FETCH_ASSOC)) {
        $topUsers[] = $row;
    }
}

// 5. Inventory Health (Tình trạng kho)
// Logic đã được gộp vào phần 1 (Fetch Basic Stats & Inventory Health)

// 6. Borrow activity over last 7 days (real data from PhieuMuon)
$chartLabels = [];
$chartData = [];
$viewSql = "SELECT CONVERT(date, NgayMuon) AS Ngay, COUNT(*) AS SoPhieu 
            FROM PhieuMuon 
            GROUP BY CONVERT(date, NgayMuon)";
$viewStmt = sqlsrv_query($conn, $viewSql);
$tempViews = [];
if ($viewStmt) {
    while ($row = sqlsrv_fetch_array($viewStmt, SQLSRV_FETCH_ASSOC)) {
        $dateStr = is_object($row['Ngay']) ? $row['Ngay']->format('d/m') : date('d/m', strtotime((string)$row['Ngay']));
        $tempViews[$dateStr] = (int)$row['Total'];
    }
}
for ($i = 6; $i >= 0; $i--) {
    $d = date('d/m', strtotime("-$i days"));
    $chartLabels[] = $d;
    $chartData[] = $tempViews[$d] ?? 0;
}
$chartLabelsJson = json_encode($chartLabels);
$chartDataJson = json_encode($chartData);

admin_render_head('Thống kê & Báo cáo');
admin_render_shell_open($username);
admin_render_nav('stats');
admin_render_page_intro(
    'Thống kê & Báo cáo',
    'fa-chart-pie',
    'Xem thống kê tổng quan về hoạt động của website.'
);
?>

<section class="admin-grid-4">
    <article class="admin-card admin-stat">
        <p class="admin-stat-label">Tổng người dùng</p>
        <p class="admin-stat-value"><?php echo $totalUsers; ?></p>
        <p class="admin-stat-desc">Tài khoản trong hệ thống</p>
    </article>
    <article class="admin-card admin-stat">
        <p class="admin-stat-label">Tổng thiết bị</p>
        <p class="admin-stat-value"><?php echo $totalDevices; ?></p>
        <p class="admin-stat-desc">Số lượng thiết bị trong kho</p>
    </article>
    <article class="admin-card admin-stat">
        <p class="admin-stat-label">Yêu cầu chờ duyệt</p>
        <p class="admin-stat-value"><?php echo $borrowStats['pending']; ?></p>
        <p class="admin-stat-desc">Phiếu mượn cần xử lý</p>
    </article>
    <article class="admin-card admin-stat">
        <p class="admin-stat-label">Đang được mượn</p>
        <p class="admin-stat-value"><?php echo $borrowStats['approved']; ?></p>
        <p class="admin-stat-desc">Đã duyệt và chưa trả</p>
    </article>
</section>

<div class="admin-layout" style="margin-top: 24px;">
    <!-- Left Column -->
    <div style="flex: 2;">
        <!-- Top Devices -->
        <section class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="fas fa-trophy" style="color: #f1c40f; margin-right: 8px;"></i>Thiết bị được mượn nhiều nhất</h2>
            </div>
            <div class="admin-card-body">
                <?php if (empty($topDevices)): ?>
                    <div class="admin-empty">Chưa có dữ liệu mượn thiết bị.</div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Tên thiết bị</th>
                                <th style="text-align: right;">Số lượng đã mượn</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topDevices as $device): ?>
                            <tr>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($device['TenThietBi']); ?></td>
                                <td style="text-align: right;"><span class="admin-chip admin-chip-primary" style="font-size: 14px;"><?php echo $device['TotalBorrowed']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <!-- Top Users -->
        <section class="admin-card" style="margin-top: 24px;">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="fas fa-users" style="color: #3498db; margin-right: 8px;"></i>Người mượn tích cực nhất</h2>
            </div>
            <div class="admin-card-body">
                <?php if (empty($topUsers)): ?>
                    <div class="admin-empty">Chưa có dữ liệu người dùng.</div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Tài khoản</th>
                                <th>Họ và tên</th>
                                <th style="text-align: right;">Số lần mượn</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topUsers as $user): ?>
                            <tr>
                                <td><span style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($user['TaiKhoan']); ?></span></td>
                                <td><?php echo htmlspecialchars($user['HoVaTen'] ?? 'Không rõ'); ?></td>
                                <td style="text-align: right;"><span class="admin-chip admin-chip-success" style="font-size: 14px;"><?php echo $user['TotalRequests']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Right Column -->
    <div style="flex: 1;">
        <!-- Borrow Status Breakdown -->
        <section class="admin-card">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="fas fa-chart-bar" style="color: #9b59b6; margin-right: 8px;"></i>Trạng thái phiếu mượn</h2>
            </div>
            <div class="admin-card-body">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                        <span style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-hourglass-half" style="color: #f39c12;"></i> Chờ duyệt</span>
                        <strong style="font-size: 18px;"><?php echo $borrowStats['pending']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                        <span style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-check-circle" style="color: #27ae60;"></i> Đang mượn</span>
                        <strong style="font-size: 18px;"><?php echo $borrowStats['approved']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                        <span style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-undo" style="color: #2980b9;"></i> Đã trả</span>
                        <strong style="font-size: 18px;"><?php echo $borrowStats['returned']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                        <span style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-times-circle" style="color: #e74c3c;"></i> Từ chối</span>
                        <strong style="font-size: 18px;"><?php echo $borrowStats['rejected']; ?></strong>
                    </div>
                </div>
            </div>
        </section>

        <!-- Inventory Health -->
        <section class="admin-card" style="margin-top: 24px;">
            <div class="admin-card-head">
                <h2 class="admin-card-title"><i class="fas fa-medkit" style="color: #e67e22; margin-right: 8px;"></i>Tình trạng thiết bị</h2>
            </div>
            <div class="admin-card-body">
                 <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: rgba(39, 174, 96, 0.1); border-left: 4px solid #27ae60; border-radius: 4px;">
                        <span>Sẵn sàng sử dụng</span>
                        <strong style="color: #27ae60; font-size: 18px;"><?php echo $healthStats['ready']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: rgba(230, 126, 34, 0.1); border-left: 4px solid #e67e22; border-radius: 4px;">
                        <span>Đang bảo trì</span>
                        <strong style="color: #e67e22; font-size: 18px;"><?php echo $healthStats['maintenance']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: rgba(231, 76, 60, 0.1); border-left: 4px solid #e74c3c; border-radius: 4px;">
                        <span>Hỏng / Cần sửa</span>
                        <strong style="color: #e74c3c; font-size: 18px;"><?php echo $healthStats['broken']; ?></strong>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Page Views Chart -->
<section class="admin-card" style="margin-top: 24px;">
    <div class="admin-card-head">
        <h2 class="admin-card-title"><i class="fas fa-chart-line" style="color: #2c3e50; margin-right: 8px;"></i>Phiếu mượn 7 ngày qua</h2>
    </div>
    <div class="admin-card-body">
        <canvas id="pageViewsChart" style="max-height: 300px; width: 100%;"></canvas>
    </div>
</section>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('pageViewsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo $chartLabelsJson; ?>,
                datasets: [{
                    label: 'Số phiếu mượn',
                    data: <?php echo $chartDataJson; ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#2980b9',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    });
</script>

<?php 
admin_render_shell_close(); 
?>
