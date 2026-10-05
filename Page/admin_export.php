<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Xuất dữ liệu & biểu mẫu');
require_once __DIR__ . '/../components/seb_db.php';
require_once __DIR__ . '/../components/admin_layout.php';

function admin_export_csv(string $filename, array $headers, array $rows): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($output, $row, ';');
    }
    fclose($output);
    exit;
}

function admin_export_fetch_all($stmt): array
{
    $rows = [];
    if (!$stmt) {
        return $rows;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function admin_export_value($value): string
{
    if ($value === null) {
        return '';
    }
    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('Y-m-d H:i:s');
    }
    return trim((string) $value);
}

function admin_export_rows(array $rows, array $columns): array
{
    return array_map(static function (array $row) use ($columns): array {
        return array_map(static function (string $column) use ($row): string {
            return admin_export_value($row[$column] ?? '');
        }, $columns);
    }, $rows);
}

$type = trim((string) ($_GET['type'] ?? ''));
$date = date('Ymd_His');

if ($type === 'users') {
    $rows = admin_export_fetch_all(sqlsrv_query($conn, 'SELECT TaiKhoan, LoaiTaiKhoan, HoVaTen, SoDienThoai, Email, BoMon FROM TaiKhoan ORDER BY TaiKhoan'));
    admin_export_csv('seb_nguoi_dung_' . $date . '.csv', ['Tài khoản', 'Vai trò', 'Họ và tên', 'Số điện thoại', 'Email', 'Bộ môn'], admin_export_rows($rows, ['TaiKhoan', 'LoaiTaiKhoan', 'HoVaTen', 'SoDienThoai', 'Email', 'BoMon']));
}

if ($type === 'devices') {
    $rows = admin_export_fetch_all(sqlsrv_query($conn, 'EXEC sp_XemKho'));
    $headers = ['Mã thiết bị', 'Tên thiết bị', 'Mã danh mục', 'Số lượng', 'Tình trạng', 'Hiển thị', 'Thông tin', 'Phụ kiện'];
    $exportRows = [];
    foreach ($rows as $row) {
        $exportRows[] = [
            admin_export_value($row['MaThietBi'] ?? $row['IDThietBi'] ?? $row['ID'] ?? ''),
            admin_export_value($row['TenThietBi'] ?? $row['Ten'] ?? ''),
            admin_export_value($row['MaDanhMuc'] ?? $row['DanhMuc'] ?? ''),
            admin_export_value($row['SoLuong'] ?? $row['SoLuongTon'] ?? 0),
            admin_export_value($row['TinhTrang'] ?? $row['TrangThai'] ?? ''),
            ((int) ($row['IDActive'] ?? 2) === 2) ? 'Đang dùng' : 'Ẩn',
            admin_export_value($row['ThongTin'] ?? $row['MoTa'] ?? ''),
            admin_export_value($row['PhuKien'] ?? ''),
        ];
    }
    admin_export_csv('seb_thiet_bi_' . $date . '.csv', $headers, $exportRows);
}

if ($type === 'borrows') {
    $sql = 'SELECT SoPhieuMuon, MaThietBi, TenThietBi, TaiKhoan, SoLuong, NgayMuon, HanTra, TinhTrangMuon, TinhTrangDuyetID FROM PhieuMuon ORDER BY NgayMuon DESC, SoPhieuMuon DESC';
    $rows = admin_export_fetch_all(sqlsrv_query($conn, $sql));
    admin_export_csv('seb_phieu_muon_' . $date . '.csv', ['Số phiếu', 'Mã thiết bị', 'Tên thiết bị', 'Tài khoản', 'Số lượng', 'Ngày mượn', 'Hạn trả', 'Trạng thái chữ', 'Trạng thái mã'], admin_export_rows($rows, ['SoPhieuMuon', 'MaThietBi', 'TenThietBi', 'TaiKhoan', 'SoLuong', 'NgayMuon', 'HanTra', 'TinhTrangMuon', 'TinhTrangDuyetID']));
}

if ($type === 'bug_reports') {
    $rows = admin_export_fetch_all(sqlsrv_query($conn, 'SELECT MaBaoCao, TaiKhoan, UrlTrang, TieuDe, NoiDung, MucDo, TrangThai, NgayTao FROM BaoCaoLoi ORDER BY NgayTao DESC'));
    admin_export_csv('seb_bao_loi_' . $date . '.csv', ['Mã', 'Tài khoản', 'Trang', 'Tiêu đề', 'Nội dung', 'Mức độ', 'Trạng thái', 'Ngày tạo'], admin_export_rows($rows, ['MaBaoCao', 'TaiKhoan', 'UrlTrang', 'TieuDe', 'NoiDung', 'MucDo', 'TrangThai', 'NgayTao']));
}

if ($type === 'stats') {
    $stats = [];
    $userRow = sqlsrv_fetch_array(sqlsrv_query($conn, 'SELECT COUNT(*) AS Total FROM TaiKhoan'), SQLSRV_FETCH_ASSOC);
    $stats[] = ['Tổng người dùng', (int) ($userRow['Total'] ?? 0)];

    $deviceRows = admin_export_fetch_all(sqlsrv_query($conn, 'EXEC sp_XemKho'));
    $totalDevices = 0;
    foreach ($deviceRows as $row) {
        $totalDevices += max(0, (int) ($row['SoLuong'] ?? $row['SoLuongTon'] ?? 0));
    }
    $stats[] = ['Tổng số lượng thiết bị', $totalDevices];

    $borrowRows = admin_export_fetch_all(sqlsrv_query($conn, 'SELECT TinhTrangDuyetID, TinhTrangMuon, COUNT(*) AS Total FROM PhieuMuon GROUP BY TinhTrangDuyetID, TinhTrangMuon'));
    foreach ($borrowRows as $row) {
        $label = admin_export_value($row['TinhTrangMuon'] ?? '');
        $id = admin_export_value($row['TinhTrangDuyetID'] ?? '');
        $stats[] = ['Phiếu trạng thái ' . ($label !== '' ? $label : $id), (int) ($row['Total'] ?? 0)];
    }
    admin_export_csv('seb_thong_ke_' . $date . '.csv', ['Chỉ số', 'Giá trị'], $stats);
}

$templates = [
    'user_form' => ['Biểu mẫu người dùng', 'seb_bieu_mau_nguoi_dung.csv', ['Tài khoản', 'Mật khẩu', 'Vai trò', 'Họ và tên', 'Số điện thoại', 'Email', 'Bộ môn']],
    'device_form' => ['Biểu mẫu thiết bị', 'seb_bieu_mau_thiet_bi.csv', ['Mã thiết bị', 'Tên thiết bị', 'Mã danh mục', 'Số lượng', 'Tình trạng', 'Thông tin', 'Phụ kiện']],
    'borrow_form' => ['Biểu mẫu phiếu mượn', 'seb_bieu_mau_phieu_muon.csv', ['Số phiếu', 'Mã thiết bị', 'Tên thiết bị', 'Tài khoản', 'Số lượng', 'Ngày mượn', 'Hạn trả', 'Ghi chú']],
];

if (isset($templates[$type])) {
    [$title, $filename, $headers] = $templates[$type];
    admin_export_csv($filename, $headers, [array_fill(0, count($headers), '')]);
}

$username = $_SESSION['user']['username'] ?? '';
admin_render_head('Xuất dữ liệu & biểu mẫu');
admin_render_shell_open($username);
admin_render_nav('exports');
admin_render_page_intro('Xuất dữ liệu & biểu mẫu', 'fa-file-export', 'Các file CSV được mã hóa UTF-8 và mở trực tiếp bằng Microsoft Excel.');
?>
<div class="admin-grid-4 admin-export-grid">
  <article class="admin-card">
    <div class="admin-card-head"><h2 class="admin-card-title"><i class="fas fa-chart-pie"></i> Thống kê</h2></div>
    <div class="admin-card-body"><p class="admin-card-note">Tổng người dùng, thiết bị và trạng thái phiếu mượn.</p><div class="admin-export-actions"><a class="admin-btn admin-btn-primary" href="admin_export.php?type=stats"><i class="fas fa-file-excel"></i> Xuất Excel</a></div></div>
  </article>
  <article class="admin-card">
    <div class="admin-card-head"><h2 class="admin-card-title"><i class="fas fa-boxes-stacked"></i> Thiết bị</h2></div>
    <div class="admin-card-body"><p class="admin-card-note">Danh sách thiết bị, số lượng, tình trạng và danh mục.</p><div class="admin-export-actions"><a class="admin-btn admin-btn-primary" href="admin_export.php?type=devices"><i class="fas fa-file-excel"></i> Xuất Excel</a></div></div>
  </article>
  <article class="admin-card">
    <div class="admin-card-head"><h2 class="admin-card-title"><i class="fas fa-users"></i> Người dùng</h2></div>
    <div class="admin-card-body"><p class="admin-card-note">Danh sách tài khoản và thông tin liên hệ.</p><div class="admin-export-actions"><a class="admin-btn admin-btn-primary" href="admin_export.php?type=users"><i class="fas fa-file-excel"></i> Xuất Excel</a></div></div>
  </article>
  <article class="admin-card">
    <div class="admin-card-head"><h2 class="admin-card-title"><i class="fas fa-clipboard-list"></i> Phiếu mượn</h2></div>
    <div class="admin-card-body"><p class="admin-card-note">Toàn bộ lịch sử phiếu mượn và trạng thái xử lý.</p><div class="admin-export-actions"><a class="admin-btn admin-btn-primary" href="admin_export.php?type=borrows"><i class="fas fa-file-excel"></i> Xuất Excel</a></div></div>
  </article>
  <article class="admin-card">
    <div class="admin-card-head"><h2 class="admin-card-title"><i class="fas fa-bug"></i> Báo lỗi</h2></div>
    <div class="admin-card-body"><p class="admin-card-note">Danh sách lỗi/góp ý người dùng gửi từ các trang.</p><div class="admin-export-actions"><a class="admin-btn admin-btn-primary" href="admin_export.php?type=bug_reports"><i class="fas fa-file-excel"></i> Xuất báo lỗi</a></div></div>
  </article>
</div>

<section class="admin-card" style="margin-top:16px;">
  <div class="admin-card-head"><h2 class="admin-card-title"><i class="fas fa-file-lines"></i> Biểu mẫu nhập liệu</h2></div>
  <div class="admin-card-body admin-toolbar">
    <a class="admin-btn admin-btn-soft" href="admin_export.php?type=user_form"><i class="fas fa-download"></i> Biểu mẫu người dùng</a>
    <a class="admin-btn admin-btn-soft" href="admin_export.php?type=device_form"><i class="fas fa-download"></i> Biểu mẫu thiết bị</a>
    <a class="admin-btn admin-btn-soft" href="admin_export.php?type=borrow_form"><i class="fas fa-download"></i> Biểu mẫu phiếu mượn</a>
  </div>
</section>
<?php admin_render_shell_close(); ?>
