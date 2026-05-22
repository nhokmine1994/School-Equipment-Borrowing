<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Quản lý thiết bị');
require_once __DIR__ . '/../components/category_helper.php';

function admin_pad_device_sequence($value)
{
  return str_pad((string) (int) $value, 3, '0', STR_PAD_LEFT);
}

function admin_generate_device_code($conn, $categoryCode)
{
  $categoryCode = trim((string) $categoryCode);
  if ($categoryCode === '') {
    return '';
  }

  $prefixCandidates = [];
  $sequenceByPrefix = [];
  $defaultMaxSequence = 0;

  $stmt = sqlsrv_query($conn, 'EXEC sp_XemKho');
  if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
      $categoryInfo = seb_resolve_category_display($row, seb_load_category_map($conn));
      $rowCategoryCode = trim((string) ($categoryInfo['code'] ?? ''));
      if ($rowCategoryCode !== $categoryCode) {
        continue;
      }

      $deviceCode = trim((string) ($row['MaThietBi'] ?? $row['IDThietBi'] ?? $row['ID'] ?? ''));
      if ($deviceCode === '') {
        continue;
      }

      if (preg_match('/^(.*?)(\d+)$/', $deviceCode, $matches)) {
        $prefix = $matches[1];
        $seq = (int) $matches[2];
        if ($prefix !== '') {
          if (!isset($prefixCandidates[$prefix])) {
            $prefixCandidates[$prefix] = 0;
            $sequenceByPrefix[$prefix] = 0;
          }
          $prefixCandidates[$prefix]++;
          if ($seq > $sequenceByPrefix[$prefix]) {
            $sequenceByPrefix[$prefix] = $seq;
          }
        } elseif ($seq > $defaultMaxSequence) {
          $defaultMaxSequence = $seq;
        }
      }
    }
  }

  arsort($prefixCandidates);
  $bestPrefix = key($prefixCandidates);
  if (!$bestPrefix) {
    $bestPrefix = $categoryCode . '-';
  } elseif (stripos($bestPrefix, 'TB-') === 0) {
    $bestPrefix = substr($bestPrefix, 3);
  }

  $nextSequence = ($sequenceByPrefix[$bestPrefix] ?? $defaultMaxSequence) + 1;
  return $bestPrefix . admin_pad_device_sequence($nextSequence);
}

function admin_resolve_device_pk(array $row)
{
  $id = trim((string) ($row['ID'] ?? $row['IDThietBi'] ?? ''));
  if ($id !== '') {
    return ['column' => !empty($row['ID']) ? 'ID' : 'IDThietBi', 'value' => $id];
  }

  $code = trim((string) ($row['MaThietBi'] ?? ''));
  return ['column' => 'MaThietBi', 'value' => $code];
}

function admin_upload_device_image($fieldName, $currentFilename = '')
{
  if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return ['success' => true, 'filename' => $currentFilename, 'message' => ''];
  }

  $result = validate_and_upload_file($_FILES[$fieldName]);
  if (!$result['success']) {
    return ['success' => false, 'filename' => $currentFilename, 'message' => $result['message']];
  }

  return ['success' => true, 'filename' => $result['filename'], 'message' => $result['message']];
}

$message = '';
$messageType = 'success';
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $message = 'CSRF token không hợp lệ. Vui lòng thử lại.';
    $messageType = 'danger';
  } else {
    $ten = trim($_POST['TenThietBi'] ?? '');
    $sl = (int) ($_POST['SoLuong'] ?? 0);
    $tinhtrang = trim($_POST['TinhTrang'] ?? 'Sẵn sàng');
    $danhmuc = trim($_POST['DanhMuc'] ?? '');
    $thongtin = trim($_POST['ThongTin'] ?? '');
    $phukien = trim($_POST['PhuKien'] ?? '');
    $ma = admin_generate_device_code($conn, $danhmuc);
    $deviceId = trim($_POST['ID'] ?? '');
    $imageUpload = admin_upload_device_image('HinhAnhFile');
    $hinh = $imageUpload['filename'];

    if ($ma === '' || $ten === '') {
      $message = 'Danh mục và tên thiết bị là bắt buộc.';
      $messageType = 'danger';
    } elseif (!$imageUpload['success']) {
      $message = $imageUpload['message'];
      $messageType = 'danger';
    } else {
      $sql = "INSERT INTO Kho (MaThietBi, TenThietBi, MaDanhMuc, SoLuong, TinhTrang, HinhAnh, ThongTin, PhuKien) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
      $params = array(&$ma, &$ten, &$danhmuc, &$sl, &$tinhtrang, &$hinh, &$thongtin, &$phukien);
      $stmt = sqlsrv_prepare($conn, $sql, $params);
      if ($stmt && sqlsrv_execute($stmt)) {
        $message = 'Thêm thiết bị thành công.';
        add_admin_notification($conn, 'device', 'Thiết bị mới', 'Đã thêm thiết bị ' . $ten . ' (' . $ma . ').', 'admin_thiet_bi.php');
      } else {
        $message = 'Thêm thiết bị thất bại.';
        $messageType = 'danger';
      }
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $message = 'CSRF token không hợp lệ. Vui lòng thử lại.';
    $messageType = 'danger';
  } else {
    $id = trim($_POST['ID'] ?? '');
    $ma = trim($_POST['MaThietBi'] ?? '');
    $ten = trim($_POST['TenThietBi'] ?? '');
    $sl = (int) ($_POST['SoLuong'] ?? 0);
    $tinhtrang = trim($_POST['TinhTrang'] ?? 'Sẵn sàng');
    $danhmuc = trim($_POST['DanhMuc'] ?? '');
    $currentHinh = trim($_POST['HinhAnhCurrent'] ?? '');
    $thongtin = trim($_POST['ThongTin'] ?? '');
    $phukien = trim($_POST['PhuKien'] ?? '');
    $imageUpload = admin_upload_device_image('HinhAnhFile', $currentHinh);
    $hinh = $imageUpload['filename'];

    if ($ma === '' || $ten === '') {
      $message = 'Mã và tên thiết bị là bắt buộc.';
      $messageType = 'danger';
    } elseif (!$imageUpload['success']) {
      $message = $imageUpload['message'];
      $messageType = 'danger';
    } else {
      $whereColumn = $id !== '' ? 'ID' : 'MaThietBi';
      $whereValue = $id !== '' ? $id : $ma;
      $sql = "UPDATE Kho SET TenThietBi = ?, MaDanhMuc = ?, SoLuong = ?, TinhTrang = ?, HinhAnh = ?, ThongTin = ?, PhuKien = ? WHERE [{$whereColumn}] = ?";
      $params = array(&$ten, &$danhmuc, &$sl, &$tinhtrang, &$hinh, &$thongtin, &$phukien, &$whereValue);
      $stmt = sqlsrv_prepare($conn, $sql, $params);

      $updated = false;
      if ($stmt && sqlsrv_execute($stmt)) {
        $rows = sqlsrv_rows_affected($stmt);
        if ($rows !== false && $rows > 0) {
          $updated = true;
        }
      }

      if ($updated) {
        $message = 'Cập nhật thiết bị thành công.';
        add_admin_notification($conn, 'device', 'Thiết bị được cập nhật', 'Đã cập nhật thiết bị ' . $ten . ' (' . $ma . ').', 'admin_thiet_bi.php');
      } else {
        $message = 'Cập nhật thiết bị thất bại.';
        $messageType = 'danger';
        $errs = sqlsrv_errors();
        if ($errs) {
          $message .= ' SQLERR: ' . htmlspecialchars(print_r($errs, true));
        }
      }
    }
  }
}

function admin_device_status_class($status)
{
    $status = mb_strtolower(trim((string) $status), 'UTF-8');
    if (strpos($status, 'hỏng') !== false || strpos($status, 'hong') !== false || strpos($status, 'broken') !== false) {
        return 'admin-chip-danger';
    }
    if (strpos($status, 'bảo trì') !== false || strpos($status, 'bao tri') !== false || strpos($status, 'unavailable') !== false) {
        return 'admin-chip-warning';
    }
    return 'admin-chip-success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];

        if ($action === 'upload_image' && !empty($_POST['ma'])) {
            $ma = trim($_POST['ma']);
            $id = trim($_POST['id'] ?? '');
            if (!empty($_FILES['image'])) {
                $result = validate_and_upload_file($_FILES['image']);
                if ($result['success']) {
                    $filename = $result['filename'];
                  $whereColumn = $id !== '' ? 'ID' : 'MaThietBi';
                  $whereValue = $id !== '' ? $id : $ma;
                  $sql = "UPDATE Kho SET HinhAnh = ? WHERE [{$whereColumn}] = ?";
                    $params = array(&$filename, &$whereValue);
                    $stmt = sqlsrv_prepare($conn, $sql, $params);
                    if ($stmt && sqlsrv_execute($stmt)) {
                        $message = 'Upload ảnh và cập nhật thành công.';
                    } else {
                        $message = 'Upload ảnh xong nhưng cập nhật DB lỗi.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = $result['message'];
                    $messageType = 'danger';
                }
            } else {
                $message = 'Vui lòng chọn file ảnh.';
                $messageType = 'danger';
            }
        }

        if ($action === 'delete' && !empty($_POST['ma'])) {
            $ma = trim($_POST['ma']);
            $id = trim($_POST['id'] ?? '');
            $whereColumn = $id !== '' ? 'ID' : 'MaThietBi';
            $whereValue = $id !== '' ? $id : $ma;
          $sql = "DELETE FROM Kho WHERE [{$whereColumn}] = ?";
            $params = array(&$whereValue);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt && sqlsrv_execute($stmt)) {
                $message = 'Xóa thiết bị thành công.';
            } else {
                $message = 'Xóa thất bại.';
                $messageType = 'danger';
            }
        }
    }
}

$devices = [];
$sql = "EXEC sp_XemKho";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $devices[] = $row;
    }
}

// Load category names from dbo.DanhMuc
$categoryMap = seb_load_category_map($conn);

// Load device statuses directly from Kho.TinhTrang in SQL Server
$deviceStatusOptions = [];
$statusSql = "SELECT DISTINCT LTRIM(RTRIM(TinhTrang)) AS TinhTrang
              FROM Kho
              WHERE TinhTrang IS NOT NULL
                AND LTRIM(RTRIM(TinhTrang)) <> ''
              ORDER BY LTRIM(RTRIM(TinhTrang))";
$statusStmt = sqlsrv_query($conn, $statusSql);
if ($statusStmt) {
  while ($statusRow = sqlsrv_fetch_array($statusStmt, SQLSRV_FETCH_ASSOC)) {
    $statusValue = trim((string) ($statusRow['TinhTrang'] ?? ''));
    if ($statusValue === '') {
      continue;
    }
    $deviceStatusOptions[$statusValue] = $statusValue;
  }
}
if (empty($deviceStatusOptions)) {
  $deviceStatusOptions = [];
}

// Resolve category display name from MaDanhMuc
foreach ($devices as &$device) {
  $categoryInfo = seb_resolve_category_display($device, $categoryMap);
  $device['MaDanhMuc'] = $categoryInfo['code'];
  $device['TenDanhMuc'] = $categoryInfo['name'];
  $device['DanhMuc'] = $categoryInfo['name'];
}
unset($device);

// Build next sequence suggestion and detect common prefix per category
$categoryNextMap = array();
$categoryPrefixMap = array();
$categorySequenceMap = array();
foreach ($categoryMap as $catCode => $label) {
  $categoryNextMap[$catCode] = 1; // default next index
  $categoryPrefixMap[$catCode] = ''; // discovered prefix like 'TB-PC-'
  $categorySequenceMap[$catCode] = [];
}
// Collect prefix candidates per category
$prefixCandidates = [];
foreach ($devices as $d) {
  $ma = trim((string) ($d['MaThietBi'] ?? ''));
  $cat = trim((string) ($d['MaDanhMuc'] ?? ''));
  if ($ma === '' || $cat === '') continue;
  if (!isset($prefixCandidates[$cat])) $prefixCandidates[$cat] = [];
  if (preg_match('/^(.*?)(\d+)$/', $ma, $m)) {
    $prefix = $m[1];
    $num = (int) $m[2];
    if ($prefix !== '') {
      if (!isset($prefixCandidates[$cat][$prefix])) $prefixCandidates[$cat][$prefix] = 0;
      $prefixCandidates[$cat][$prefix]++;
      if (!isset($categorySequenceMap[$cat][$prefix])) $categorySequenceMap[$cat][$prefix] = 0;
      if ($num > $categorySequenceMap[$cat][$prefix]) {
        $categorySequenceMap[$cat][$prefix] = $num;
      }
    }
  } else {
    // fallback: treat whole code as prefix with no numeric suffix
    if (!isset($prefixCandidates[$cat][$ma])) $prefixCandidates[$cat][$ma] = 0;
    $prefixCandidates[$cat][$ma]++;
  }
}
// Choose most frequent prefix for each category
foreach ($prefixCandidates as $cat => $cands) {
  arsort($cands);
  $best = key($cands);
  if ($best) {
    $categoryPrefixMap[$cat] = $best;
    $categoryNextMap[$cat] = (($categorySequenceMap[$cat][$best] ?? 0) + 1);
  }
}


$totalDevices = (int) array_sum(array_map(function ($item) {
    return (int) ($item['SoLuong'] ?? 0);
}, $devices));
$lowStockDevices = (int) array_sum(array_map(function ($item) {
    $qty = (int) ($item['SoLuong'] ?? 0);
    return ($qty > 0 && $qty <= 2) ? $qty : 0;
}, $devices));
$maintenanceDevices = (int) array_sum(array_map(function ($item) {
    return (admin_device_status_class($item['TinhTrang'] ?? '') === 'admin-chip-warning') ? (int) ($item['SoLuong'] ?? 0) : 0;
}, $devices));
$brokenDevices = (int) array_sum(array_map(function ($item) {
    return (admin_device_status_class($item['TinhTrang'] ?? '') === 'admin-chip-danger') ? (int) ($item['SoLuong'] ?? 0) : 0;
}, $devices));

require_once __DIR__ . '/../components/admin_layout.php';
$adminUsername = $_SESSION['user']['username'] ?? '';
admin_render_head('Quản lý thiết bị');
admin_render_shell_open($adminUsername);
admin_render_nav('devices');
admin_render_page_intro(
    'Quản lý thiết bị',
    'fa-boxes-stacked',
    'Dữ liệu lấy trực tiếp từ SQL Server, hiển thị theo dạng thẻ dễ xem hơn bảng cũ.'
);
?>
    <section class="admin-grid-4">
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Tổng thiết bị</p>
        <p class="admin-stat-value"><?php echo $totalDevices; ?></p>
        <p class="admin-stat-desc">Tất cả thiết bị trong kho.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Số lượng thấp</p>
        <p class="admin-stat-value"><?php echo $lowStockDevices; ?></p>
        <p class="admin-stat-desc">Thiết bị còn từ 1 đến 2 chiếc.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Đang bảo trì</p>
        <p class="admin-stat-value"><?php echo $maintenanceDevices; ?></p>
        <p class="admin-stat-desc">Trạng thái bảo trì / không khả dụng.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Thiết bị báo hỏng</p>
        <p class="admin-stat-value"><?php echo $brokenDevices; ?></p>
        <p class="admin-stat-desc">Thiết bị cần kiểm tra hoặc thay thế.</p>
      </article>
    </section>

    <?php if ($message): ?>
      <div class="admin-flash <?php echo $messageType === 'danger' ? 'admin-flash-danger' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-card" style="margin-top:16px;">
      <div class="admin-card-head">
        <div>
          <h2 class="admin-card-title">Danh sách thiết bị</h2>
          <p class="admin-card-note">Tìm kiếm nhanh, chỉnh sửa, upload ảnh hoặc xóa từng thiết bị.</p>
        </div>
        <div class="admin-toolbar">
          <input id="deviceSearch" class="admin-input" style="width:280px;" placeholder="Tìm theo mã, tên, danh mục...">
          <a class="admin-btn admin-btn-primary" href="#them-moi" style="text-decoration:none;display:inline-flex;align-items:center;">Thêm thiết bị mới</a>
        </div>
      </div>
      <div class="admin-card-body">
        <?php if (empty($devices)): ?>
          <div class="admin-empty">Chưa có dữ liệu thiết bị.</div>
        <?php else: ?>
          <div style="overflow-x: auto;">
            <table class="admin-compact-table" style="font-size: 13px; width: 100%;">
              <thead>
                <tr>
                  <th style="width: 8%; text-align: left;">ID</th>
                  <th style="width: 15%; text-align: left;">Mã</th>
                  <th style="width: 30%; text-align: left;">Tên thiết bị</th>
                  <th style="width: 12%; text-align: center;">Số lượng</th>
                  <th style="width: 18%; text-align: left;">Danh mục</th>
                  <th style="width: 15%; text-align: center;">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($devices as $d):
                  $rowId = htmlspecialchars((string) ($d['ID'] ?? $d['IDThietBi'] ?? ''));
                  $ma = htmlspecialchars($d['MaThietBi'] ?? $d['ID'] ?? '');
                  $ten = htmlspecialchars($d['TenThietBi'] ?? $d['Ten'] ?? '');
                  $sl = htmlspecialchars((string) ($d['SoLuong'] ?? '0'));
                  $statusText = htmlspecialchars((string) ($d['TinhTrang'] ?? 'Sẵn sàng'));
                  $chipClass = admin_device_status_class($d['TinhTrang'] ?? '');
                  $categoryName = htmlspecialchars($d['TenDanhMuc'] ?? $d['DanhMuc'] ?? '');
                  $selectedCategory = seb_resolve_category_display($d, $categoryMap)['code'];
                  $detailRowId = md5((string) ($d['ID'] ?? $d['IDThietBi'] ?? $ma) . ':' . (string) ($d['MaThietBi'] ?? $d['ID'] ?? ''));
                  $editPayload = [
                    'id' => (string) ($d['ID'] ?? $d['IDThietBi'] ?? ''),
                    'ma' => (string) ($d['MaThietBi'] ?? $d['ID'] ?? ''),
                    'ten' => (string) ($d['TenThietBi'] ?? $d['Ten'] ?? ''),
                    'soLuong' => (string) ($d['SoLuong'] ?? '0'),
                    'tinhTrang' => (string) ($d['TinhTrang'] ?? 'Sẵn sàng'),
                    'danhMuc' => (string) $selectedCategory,
                    'hinhAnh' => (string) ($d['HinhAnh'] ?? $d['Anh'] ?? ''),
                    'thongTin' => (string) ($d['ThongTin'] ?? ''),
                    'phuKien' => (string) ($d['PhuKien'] ?? ''),
                  ];
                  $editPayloadJson = htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="device-item" data-search="<?php echo htmlspecialchars(mb_strtolower(trim(($ma . ' ' . $ten . ' ' . ($d['TenDanhMuc'] ?? $d['DanhMuc'] ?? '') . ' ' . ($d['ThongTin'] ?? '') . ' ' . ($d['PhuKien'] ?? ''))), 'UTF-8')); ?>" onclick='openDeviceEditModal(<?php echo $editPayloadJson; ?>)' style="cursor: pointer;">
                  <td style="color: #64748b; font-size: 11px;"><?php echo $rowId ?: '---'; ?></td>
                  <td style="color: #0D8ABC; font-weight: 600;"><?php echo $ma; ?></td>
                  <td><?php echo $ten; ?></td>
                  <td style="text-align: center;"><?php echo $sl; ?></td>
                  <td><?php echo $categoryName; ?></td>
                  <td style="text-align: center;">
                    <span class="admin-chip <?php echo $chipClass; ?>" style="font-size: 12px;"><?php echo $statusText; ?></span>
                    <button class="admin-btn-sm admin-btn-edit" onclick="event.stopPropagation(); toggleDeviceDetails('device-<?php echo $detailRowId; ?>')" title="Chi tiết" style="margin-left:8px; vertical-align:middle;">
                      <i class="fas fa-chevron-down"></i>
                    </button>
                  </td>
                </tr>
                <tr id="device-<?php echo $detailRowId; ?>" class="device-details-row" style="display: none;">
                  <td colspan="6" style="padding: 0; border: none;">
                    <div class="device-details-content" style="padding: 20px; background: #f9f9f9; border-top: 1px solid #e0e0e0;">
                      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                          <p><strong>Thông tin:</strong><br><?php echo htmlspecialchars($d['ThongTin'] ?? 'N/A'); ?></p>
                          <p><strong>Phụ kiện:</strong><br><?php echo htmlspecialchars($d['PhuKien'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                          <?php 
                            $img = htmlspecialchars($d['HinhAnh'] ?? $d['Anh'] ?? '');
                            $imgPath = $img ? ('../Images/devices/' . $img) : '';
                          ?>
                          <?php if ($imgPath): ?>
                            <img src="<?php echo $imgPath; ?>" alt="<?php echo $ten; ?>" style="max-width: 150px; max-height: 150px; border-radius: 6px;">
                          <?php else: ?>
                            <div style="width: 150px; height: 150px; background: #e0e0e0; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                              <i class="fas fa-image" style="font-size: 32px; color: #999;"></i>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>

                      <div class="admin-divider" style="margin: 16px 0;"></div>

                      <!-- Edit Form -->
                      <details style="margin-bottom: 12px;">
                        <summary style="cursor: pointer; font-weight: 600; padding: 8px; background: #f0f0f0; border-radius: 4px;">
                          <i class="fas fa-edit"></i> Chỉnh sửa
                        </summary>
                        <form method="post" enctype="multipart/form-data" class="admin-form-grid admin-form-grid-vertical" style="margin-top: 12px;">
                          <input type="hidden" name="action" value="edit">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                          <input type="hidden" name="ID" value="<?php echo htmlspecialchars((string) ($d['ID'] ?? $d['IDThietBi'] ?? '')); ?>">
                          <input type="hidden" name="MaThietBi" value="<?php echo $ma; ?>">
                          <input type="hidden" name="HinhAnhCurrent" value="<?php echo htmlspecialchars((string) ($d['HinhAnh'] ?? $d['Anh'] ?? '')); ?>">

                          <?php $currentImage = trim((string) ($d['HinhAnh'] ?? $d['Anh'] ?? '')); ?>
                          <div class="admin-field admin-col-12">
                            <label>Ảnh thiết bị</label>
                            <div class="admin-actions" style="align-items:center; padding:10px 12px; border:1px dashed #93c5fd; border-radius:8px; background:#f8fbff;">
                              <input class="admin-input" type="file" name="HinhAnhFile" id="editHinhAnhFile" accept="image/*" style="display:none;">
                              <label for="editHinhAnhFile" class="admin-btn admin-btn-soft" style="display:inline-flex; align-items:center; cursor:pointer;">
                                Chọn ảnh
                              </label>
                              <span id="editHinhAnhFileName" style="font-size:12px; color:#6b7280;">Chưa chọn file</span>
                            </div>
                            <div style="font-size:12px; color:#6b7280; margin-top:6px;">Để trống nếu không đổi ảnh. File mới sẽ lưu trong Images/devices/.</div>
                          </div>

                          <?php if ($currentImage !== ''): ?>
                            <div class="admin-field admin-col-12">
                              <label>Ảnh hiện tại</label>
                              <div style="display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #cfe0f5; border-radius:8px; background:#f8fbff;">
                                <img src="../Images/devices/<?php echo htmlspecialchars($currentImage); ?>" alt="<?php echo $ten; ?>" style="width:56px; height:56px; object-fit:cover; border-radius:8px; border:1px solid #dbe7f3;">
                                <div style="font-size:13px; color:#334155;">
                                  <div style="font-weight:700;"><?php echo htmlspecialchars($currentImage); ?></div>
                                  <div style="color:#64748b; font-size:12px;">Chọn file mới nếu muốn thay ảnh này.</div>
                                </div>
                              </div>
                            </div>
                          <?php endif; ?>

                          <div class="admin-field admin-col-12">
                            <label>Danh mục</label>
                            <select class="admin-input" name="DanhMuc">
                              <option value="">-- Chọn danh mục --</option>
                              <?php foreach ($categoryMap as $categoryCode => $categoryLabel): ?>
                                <option value="<?php echo htmlspecialchars($categoryCode); ?>" <?php echo $selectedCategory === $categoryCode ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoryLabel); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="admin-field admin-col-12">
                            <label>Mã thiết bị</label>
                            <input class="admin-input" value="<?php echo $ma; ?>" disabled>
                            <div style="font-size:12px; color:#6b7280; margin-top:6px;">Mã thiết bị được giữ nguyên theo bản ghi hiện tại.</div>
                          </div>

                          <div class="admin-field admin-col-12">
                            <label>Tên thiết bị</label>
                            <input class="admin-input" name="TenThietBi" value="<?php echo $ten; ?>">
                          </div>

                          <div class="admin-field admin-col-12">
                            <label>Số lượng</label>
                            <input class="admin-input" name="SoLuong" type="number" min="0" value="<?php echo $sl; ?>">
                          </div>

                          <div class="admin-field admin-col-12">
                            <label>Trạng thái</label>
                            <select class="admin-input" name="TinhTrang">
                              <option value="Sẵn sàng" <?php echo (isset($d['TinhTrang']) && $d['TinhTrang'] === 'Sẵn sàng') ? 'selected' : ''; ?>>Sẵn sàng</option>
                              <option value="Đang bảo trì" <?php echo (isset($d['TinhTrang']) && $d['TinhTrang'] === 'Đang bảo trì') ? 'selected' : ''; ?>>Đang bảo trì</option>
                              <option value="Hỏng" <?php echo (isset($d['TinhTrang']) && $d['TinhTrang'] === 'Hỏng') ? 'selected' : ''; ?>>Hỏng</option>
                            </select>
                          </div>

                          <div class="admin-field admin-col-12">
                            <label>Phụ kiện</label>
                            <input class="admin-input" name="PhuKien" value="<?php echo htmlspecialchars($d['PhuKien'] ?? ''); ?>">
                          </div>

                          <div class="admin-field admin-col-12">
                            <label>Thông tin</label>
                            <textarea class="admin-textarea" name="ThongTin"><?php echo htmlspecialchars($d['ThongTin'] ?? ''); ?></textarea>
                          </div>
                          <div class="admin-col-12 admin-actions">
                            <button class="admin-btn admin-btn-soft" type="submit">Lưu chỉnh sửa</button>
                          </div>
                        </form>
                      </details>

                      <!-- Delete -->
                      <form method="post" onsubmit="return confirm('Xóa thiết bị <?php echo htmlspecialchars($ten); ?>?')" class="admin-actions">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($d['ID'] ?? $d['IDThietBi'] ?? '')); ?>">
                        <input type="hidden" name="ma" value="<?php echo $ma; ?>">
                        <button class="admin-btn admin-btn-danger" type="submit">Xóa thiết bị</button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="admin-card" style="margin:16px;" id="them-moi">
      <div class="admin-card-head">
        <div>
          <h2 class="admin-card-title">Thêm thiết bị mới</h2>
          <p class="admin-card-note">Form thêm mới nằm ngay trong trang thiết bị.</p>
        </div>
      </div>
      <div class="admin-card-body">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <div class="admin-form-grid">
            <div class="admin-field admin-col-4">
              <label>Danh mục</label>
              <select class="admin-input" name="DanhMuc" id="addDanhMuc" required>
                <option value="">-- Chọn danh mục --</option>
                <?php foreach ($categoryMap as $categoryCode => $categoryLabel): ?>
                  <option value="<?php echo htmlspecialchars($categoryCode); ?>"><?php echo htmlspecialchars($categoryLabel); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-field admin-col-8">
              <label>Mã thiết bị</label>
              <input class="admin-input" name="MaThietBi" id="addMaThietBi" required readonly>
              <div style="font-size:12px; color:#6b7280; margin-top:6px;">Mã được sinh tự động theo danh mục và không thể nhập tay.</div>
            </div>
            <div class="admin-field admin-col-12">
              <label>Tên thiết bị</label>
              <input class="admin-input" name="TenThietBi" required>
            </div>
            <div class="admin-field admin-col-3">
              <label>Số lượng</label>
              <input class="admin-input" name="SoLuong" type="number" min="0" value="1">
            </div>
            <div class="admin-field admin-col-3">
              <label>Trạng thái</label>
              <select class="admin-input" name="TinhTrang" id="addTinhTrang">
                <?php if (!empty($deviceStatusOptions)): ?>
                  <?php foreach ($deviceStatusOptions as $statusValue): ?>
                    <option value="<?php echo htmlspecialchars($statusValue); ?>"><?php echo htmlspecialchars($statusValue); ?></option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>
            <div class="admin-field admin-col-3">
              <label>Ảnh thiết bị</label>
              <div class="admin-actions" style="align-items:center; padding:8px 10px; border:1px dashed #e6eefc; border-radius:8px; background:#fff;">
                <input class="admin-input" type="file" name="HinhAnhFile" id="addHinhAnhFile" accept="image/*" style="display:none;">
                <label for="addHinhAnhFile" class="admin-btn admin-btn-soft" style="display:inline-flex; align-items:center; cursor:pointer; padding:8px 10px;">Chọn ảnh</label>
                <span id="addHinhAnhFileName" style="font-size:12px; color:#6b7280; margin-left:8px;">Chưa chọn file</span>
              </div>
            </div>
            <div class="admin-field admin-col-3">
              <label>Phụ kiện</label>
              <input class="admin-input" name="PhuKien" placeholder="Cáp, adapter...">
            </div>
            <div class="admin-field admin-col-12">
              <label>Thông tin</label>
              <textarea class="admin-textarea" name="ThongTin" placeholder="Mô tả ngắn thiết bị..."></textarea>
            </div>
          </div>

          <div class="admin-actions" style="margin-top:12px;">
            <button class="admin-btn admin-btn-primary" type="submit">Lưu thiết bị</button>
            <a class="admin-btn admin-btn-soft" href="admin_thiet_bi_them.php" style="text-decoration:none;display:inline-flex;align-items:center;">Lối tắt cũ</a>
          </div>
        </form>
      </div>
    </section>

    <div id="deviceEditModal" class="admin-device-modal" style="display: none;">
      <div class="admin-device-modal-backdrop" onclick="closeDeviceEditModal()"></div>
      <div class="admin-device-modal-panel" role="dialog" aria-modal="true" aria-labelledby="deviceEditModalTitle">
        <div class="admin-device-modal-head">
          <div>
            <h2 id="deviceEditModalTitle" class="admin-card-title">Sửa nhanh thiết bị</h2>
            <p class="admin-card-note">Nhấn vào một thiết bị để mở form sửa nhanh.</p>
          </div>
          <button type="button" class="admin-btn-sm admin-btn-edit" onclick="closeDeviceEditModal()" aria-label="Đóng">&times;</button>
        </div>
        <div class="admin-device-modal-body">
          <form method="post" id="deviceEditForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="ID" id="editID">
            <input type="hidden" name="MaThietBi" id="editMaThietBi">

            <div class="admin-form-grid">
              <div class="admin-field admin-col-4">
                <label>Mã thiết bị</label>
                <input class="admin-input" id="editMaThietBiDisplay" disabled>
              </div>
              <div class="admin-field admin-col-8">
                <label>Tên thiết bị</label>
                <input class="admin-input" name="TenThietBi" id="editTenThietBi" required>
              </div>
              <div class="admin-field admin-col-3">
                <label>Số lượng</label>
                <input class="admin-input" name="SoLuong" id="editSoLuong" type="number" min="0">
              </div>
              <div class="admin-field admin-col-3">
                <label>Trạng thái</label>
                <select class="admin-input" name="TinhTrang" id="editTinhTrang">
                  <?php if (!empty($deviceStatusOptions)): ?>
                    <?php foreach ($deviceStatusOptions as $statusValue): ?>
                      <option value="<?php echo htmlspecialchars($statusValue); ?>"><?php echo htmlspecialchars($statusValue); ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
              <div class="admin-field admin-col-3">
                <label>Danh mục</label>
                <select class="admin-input" name="DanhMuc" id="editDanhMuc">
                  <option value="">-- Chọn danh mục --</option>
                  <?php foreach ($categoryMap as $categoryCode => $categoryLabel): ?>
                    <option value="<?php echo htmlspecialchars($categoryCode); ?>"><?php echo htmlspecialchars($categoryLabel); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="admin-field admin-col-3">
                <label>Phụ kiện</label>
                <input class="admin-input" name="PhuKien" id="editPhuKien">
              </div>
              <div class="admin-field admin-col-12">
                <label>Thông tin</label>
                <textarea class="admin-textarea" name="ThongTin" id="editThongTin"></textarea>
              </div>
              <div class="admin-field admin-col-12">
                <label>Ảnh thiết bị</label>
                <div class="admin-actions" style="align-items:center; padding:10px 12px; border:1px dashed #93c5fd; border-radius:8px; background:#f8fbff;">
                  <input class="admin-input" type="file" name="HinhAnhFile" id="modalEditHinhAnhFile" accept="image/*" style="display:none;">
                  <label for="modalEditHinhAnhFile" class="admin-btn admin-btn-soft" style="display:inline-flex; align-items:center; cursor:pointer;">
                    Chọn ảnh
                  </label>
                  <span id="modalEditHinhAnhFileName" style="font-size:12px; color:#6b7280;">Chưa chọn file</span>
                </div>
                <div style="font-size:12px; color:#6b7280; margin-top:6px;">Để trống nếu không đổi ảnh. File mới sẽ lưu trong Images/devices/.</div>
              </div>

              <div class="admin-field admin-col-12" id="modalCurrentImageWrapper" style="display:none;">
                <label>Ảnh hiện tại</label>
                <div id="modalCurrentImage" style="display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #cfe0f5; border-radius:8px; background:#f8fbff;"></div>
              </div>

              <div class="admin-field admin-col-12" id="modalSelectedPreviewWrapper" style="display:none; margin-top:8px;">
                <label>Xem trước ảnh mới</label>
                <div style="display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #e6edf8; border-radius:8px; background:#fff;">
                  <img id="modalSelectedPreviewImg" src="" alt="Preview" style="width:72px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #dbe7f3; display:none;">
                  <div style="display:flex; flex-direction:column;">
                    <span id="modalSelectedPreviewName" style="font-size:13px; color:#334155;"></span>
                    <span id="modalFileError" style="font-size:12px; color:#dc2626;"></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="admin-actions" style="margin-top: 14px; justify-content: flex-end;">
              <button type="button" class="admin-btn admin-btn-soft" onclick="closeDeviceEditModal()">Hủy</button>
              <button type="submit" class="admin-btn admin-btn-primary">Lưu thay đổi</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  <script>
    const deviceEditModal = document.getElementById('deviceEditModal');
    const editID = document.getElementById('editID');
    const editMaThietBi = document.getElementById('editMaThietBi');
    const editMaThietBiDisplay = document.getElementById('editMaThietBiDisplay');
    const editTenThietBi = document.getElementById('editTenThietBi');
    const editSoLuong = document.getElementById('editSoLuong');
    const editTinhTrang = document.getElementById('editTinhTrang');
    const editDanhMuc = document.getElementById('editDanhMuc');
    const editHinhAnhFile = document.getElementById('editHinhAnhFile');
    const editHinhAnhFileName = document.getElementById('editHinhAnhFileName');
    const editThongTin = document.getElementById('editThongTin');
    const editPhuKien = document.getElementById('editPhuKien');
    const modalEditHinhAnhFile = document.getElementById('modalEditHinhAnhFile');
    const modalEditHinhAnhFileName = document.getElementById('modalEditHinhAnhFileName');
    const modalCurrentImageWrapper = document.getElementById('modalCurrentImageWrapper');
    const modalCurrentImage = document.getElementById('modalCurrentImage');
    const modalSelectedPreviewWrapper = document.getElementById('modalSelectedPreviewWrapper');
    const modalSelectedPreviewImg = document.getElementById('modalSelectedPreviewImg');
    const modalSelectedPreviewName = document.getElementById('modalSelectedPreviewName');
    const modalFileError = document.getElementById('modalFileError');

    function openDeviceEditModal(device) {
      if (!deviceEditModal || !device) {
        return;
      }

      if (editID) editID.value = device.id || '';
      if (editMaThietBi) editMaThietBi.value = device.ma || '';
      if (editMaThietBiDisplay) editMaThietBiDisplay.value = device.ma || '';
      if (editTenThietBi) editTenThietBi.value = device.ten || '';
      if (editSoLuong) editSoLuong.value = device.soLuong || 0;
      if (editTinhTrang) editTinhTrang.value = device.tinhTrang || 'Sẵn sàng';
      if (editDanhMuc) editDanhMuc.value = device.danhMuc || '';
      if (editThongTin) editThongTin.value = device.thongTin || '';
      if (editPhuKien) editPhuKien.value = device.phuKien || '';
      if (editHinhAnhFile) editHinhAnhFile.value = '';
      if (editHinhAnhFileName) editHinhAnhFileName.textContent = 'Chưa chọn file';
      if (modalEditHinhAnhFile) modalEditHinhAnhFile.value = '';
      if (modalEditHinhAnhFileName) modalEditHinhAnhFileName.textContent = 'Chưa chọn file';
      if (modalSelectedPreviewWrapper) modalSelectedPreviewWrapper.style.display = 'none';
      if (modalSelectedPreviewImg) { modalSelectedPreviewImg.src = ''; modalSelectedPreviewImg.style.display = 'none'; }
      if (modalSelectedPreviewName) modalSelectedPreviewName.textContent = '';
      if (modalFileError) modalFileError.textContent = '';

      // Populate current image preview in modal if available
      const current = device.hinhAnh || '';
      if (modalCurrentImage && modalCurrentImageWrapper) {
        if (current) {
          modalCurrentImageWrapper.style.display = '';
          modalCurrentImage.innerHTML = `<img src="../Images/devices/${encodeHTML(current)}" alt="" style="width:56px; height:56px; object-fit:cover; border-radius:8px; border:1px solid #dbe7f3; margin-right:12px;">` +
            `<div style="font-size:13px; color:#334155;"><div style="font-weight:700;">${encodeHTML(current)}</div><div style="color:#64748b; font-size:12px;">Chọn file mới nếu muốn thay ảnh này.</div></div>`;
        } else {
          modalCurrentImageWrapper.style.display = 'none';
          modalCurrentImage.innerHTML = '';
        }
      }

      deviceEditModal.style.display = 'flex';
      deviceEditModal.style.pointerEvents = 'auto';
    }

    function closeDeviceEditModal() {
      if (!deviceEditModal) {
        return;
      }

      deviceEditModal.style.display = 'none';
      deviceEditModal.style.pointerEvents = 'none';
    }

    window.openDeviceEditModal = openDeviceEditModal;
    window.closeDeviceEditModal = closeDeviceEditModal;

    if (editHinhAnhFile && editHinhAnhFileName) {
      editHinhAnhFile.addEventListener('change', () => {
        editHinhAnhFileName.textContent = editHinhAnhFile.files && editHinhAnhFile.files.length
          ? editHinhAnhFile.files[0].name
          : 'Chưa chọn file';
      });
    }

    // Modal file input listener with validation and preview
    if (modalEditHinhAnhFile && modalEditHinhAnhFileName) {
      const MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 MB
      modalEditHinhAnhFile.addEventListener('change', () => {
        const f = modalEditHinhAnhFile.files && modalEditHinhAnhFile.files[0];
        if (!f) {
          modalEditHinhAnhFileName.textContent = 'Chưa chọn file';
          if (modalSelectedPreviewWrapper) modalSelectedPreviewWrapper.style.display = 'none';
          if (modalFileError) modalFileError.textContent = '';
          return;
        }

        modalEditHinhAnhFileName.textContent = f.name;

        // Type check
        if (!f.type || !f.type.startsWith('image/')) {
          if (modalFileError) modalFileError.textContent = 'Vui lòng chọn file ảnh (jpg/png/gif).';
          if (modalSelectedPreviewWrapper) modalSelectedPreviewWrapper.style.display = 'none';
          return;
        }

        // Size check
        if (f.size > MAX_UPLOAD_BYTES) {
          if (modalFileError) modalFileError.textContent = 'Kích thước ảnh quá lớn (tối đa 2MB).';
          if (modalSelectedPreviewWrapper) modalSelectedPreviewWrapper.style.display = 'none';
          return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
          if (modalSelectedPreviewImg) {
            modalSelectedPreviewImg.src = e.target.result;
            modalSelectedPreviewImg.style.display = '';
          }
          if (modalSelectedPreviewName) modalSelectedPreviewName.textContent = f.name;
          if (modalSelectedPreviewWrapper) modalSelectedPreviewWrapper.style.display = '';
          if (modalFileError) modalFileError.textContent = '';
        };
        reader.readAsDataURL(f);
      });
    }

    // Prevent submit if file invalid
    const deviceEditForm = document.getElementById('deviceEditForm');
    if (deviceEditForm) {
      deviceEditForm.addEventListener('submit', (ev) => {
        if (modalFileError && modalFileError.textContent) {
          ev.preventDefault();
          alert('Vui lòng sửa lỗi file ảnh trước khi lưu.');
        }
      });
    }

    // Basic HTML escape for inserted strings
    function encodeHTML(s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Toggle device details row
    function toggleDeviceDetails(id) {
      const row = document.getElementById(id);
      if (row) {
        const isHidden = row.style.display === 'none';
        row.style.display = isHidden ? '' : 'none';
      }
    }

    // Search functionality
    const deviceSearch = document.getElementById('deviceSearch');
    const deviceItems = Array.from(document.querySelectorAll('.device-item'));

    // Add-form file input listener
    const addHinhAnhFile = document.getElementById('addHinhAnhFile');
    const addHinhAnhFileName = document.getElementById('addHinhAnhFileName');
    if (addHinhAnhFile && addHinhAnhFileName) {
      addHinhAnhFile.addEventListener('change', () => {
        addHinhAnhFileName.textContent = addHinhAnhFile.files && addHinhAnhFile.files.length
          ? addHinhAnhFile.files[0].name
          : 'Chưa chọn file';
      });
    }

    if (deviceSearch) {
      deviceSearch.addEventListener('input', () => {
        const term = deviceSearch.value.trim().toLowerCase();
        deviceItems.forEach((item) => {
          const haystack = String(item.getAttribute('data-search') || '');
          item.style.display = !term || haystack.includes(term) ? '' : 'none';
          
          // Hide details row when parent row is hidden
          const nextRow = item.nextElementSibling;
          if (nextRow && nextRow.classList.contains('device-details-row')) {
            nextRow.style.display = 'none';
          }
        });
      });
    }

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeDeviceEditModal();
      }
    });
  </script>
  <style>
    .admin-form-grid-vertical {
      grid-template-columns: 1fr;
    }

    .admin-form-grid-vertical .admin-field,
    .admin-form-grid-vertical .admin-actions {
      grid-column: 1 / -1;
    }

    .admin-device-modal {
      position: fixed;
      inset: 0;
      z-index: 14000;
      display: none;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }

    .admin-device-modal-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      backdrop-filter: blur(3px);
    }

    .admin-device-modal-panel {
      position: relative;
      width: 100%;
      max-width: 920px;
      margin: 20px;
      background: #ffffff;
      border-radius: 14px;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
      overflow: hidden;
      pointer-events: auto;
    }

    .admin-device-modal-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      padding: 18px 20px;
      background: #f8fbff;
      border-bottom: 1px solid #dbe7f3;
    }

    .admin-device-modal-body {
      padding: 20px;
      max-height: 75vh;
      overflow: auto;
    }
  </style>
  <script>
    // Category -> next sequence map and detected prefix map from server
    const CATEGORY_NEXT = <?php echo json_encode($categoryNextMap, JSON_UNESCAPED_UNICODE); ?> || {};
    const CATEGORY_PREFIX = <?php echo json_encode($categoryPrefixMap, JSON_UNESCAPED_UNICODE); ?> || {};
    function padNum(n) {
      return String(n).padStart(3, '0');
    }
    (function () {
      const addDanhMuc = document.getElementById('addDanhMuc');
      const addMa = document.getElementById('addMaThietBi');
      if (addDanhMuc && addMa) {
        addDanhMuc.addEventListener('change', function () {
          const cat = (this.value || '').trim();
          if (!cat) return;
          const next = (CATEGORY_NEXT[cat] !== undefined) ? CATEGORY_NEXT[cat] : 1;
          const prefix = (CATEGORY_PREFIX[cat] || '').trim().replace(/^TB-/i, '');
          const suggestion = prefix ? (prefix + padNum(next)) : (cat + '-' + padNum(next));
          addMa.value = suggestion;
        });
      }
    })();
  </script>
<?php admin_render_shell_close(); ?>
<script>
// Runtime fallback: ensure modal file-picker UI exists and attach listeners
(function(){
  function ensureModalFileUI() {
    const modal = document.getElementById('deviceEditModal');
    if (!modal) return;
    const form = modal.querySelector('form') || document.getElementById('deviceEditForm');
    if (!form) return;

    // If the modal file input already exists, nothing to do
    if (document.getElementById('modalEditHinhAnhFile')) return;

    const insertBefore = form.querySelector('.admin-actions') || null;

    // Build the file input block
    const fileBlock = document.createElement('div');
    fileBlock.className = 'admin-field admin-col-12';
    fileBlock.innerHTML = `
      <label>Ảnh thiết bị</label>
      <div class="admin-actions" style="align-items:center; padding:10px 12px; border:1px dashed #93c5fd; border-radius:8px; background:#f8fbff;">
        <input class="admin-input" type="file" name="HinhAnhFile" id="modalEditHinhAnhFile" accept="image/*" style="display:none;">
        <label for="modalEditHinhAnhFile" class="admin-btn admin-btn-soft" style="display:inline-flex; align-items:center; cursor:pointer;">Chọn ảnh</label>
        <span id="modalEditHinhAnhFileName" style="font-size:12px; color:#6b7280;">Chưa chọn file</span>
      </div>
      <div style="font-size:12px; color:#6b7280; margin-top:6px;">Để trống nếu không đổi ảnh. File mới sẽ lưu trong Images/devices/.</div>
    `;

    // Preview block
    const previewBlock = document.createElement('div');
    previewBlock.className = 'admin-field admin-col-12';
    previewBlock.id = 'modalSelectedPreviewWrapper';
    previewBlock.style.display = 'none';
    previewBlock.style.marginTop = '8px';
    previewBlock.innerHTML = `
      <label>Xem trước ảnh mới</label>
      <div style="display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #e6edf8; border-radius:8px; background:#fff;">
        <img id="modalSelectedPreviewImg" src="" alt="Preview" style="width:72px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #dbe7f3; display:none;">
        <div style="display:flex; flex-direction:column;">
          <span id="modalSelectedPreviewName" style="font-size:13px; color:#334155;"></span>
          <span id="modalFileError" style="font-size:12px; color:#dc2626;"></span>
        </div>
      </div>
    `;

    if (insertBefore) {
      form.insertBefore(fileBlock, insertBefore);
      form.insertBefore(previewBlock, insertBefore);
    } else {
      form.appendChild(fileBlock);
      form.appendChild(previewBlock);
    }

    // Attach listeners similar to main script
    try {
      const fileInput = document.getElementById('modalEditHinhAnhFile');
      const fileNameSpan = document.getElementById('modalEditHinhAnhFileName');
      const selectedWrapper = document.getElementById('modalSelectedPreviewWrapper');
      const selectedImg = document.getElementById('modalSelectedPreviewImg');
      const selectedName = document.getElementById('modalSelectedPreviewName');
      const fileError = document.getElementById('modalFileError');
      const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

      if (fileInput && fileNameSpan) {
        fileInput.addEventListener('change', function () {
          const f = fileInput.files && fileInput.files[0];
          if (!f) {
            fileNameSpan.textContent = 'Chưa chọn file';
            if (selectedWrapper) selectedWrapper.style.display = 'none';
            if (fileError) fileError.textContent = '';
            return;
          }
          fileNameSpan.textContent = f.name;
          if (!f.type || !f.type.startsWith('image/')) {
            if (fileError) fileError.textContent = 'Vui lòng chọn file ảnh (jpg/png/gif).';
            if (selectedWrapper) selectedWrapper.style.display = 'none';
            return;
          }
          if (f.size > MAX_UPLOAD_BYTES) {
            if (fileError) fileError.textContent = 'Kích thước ảnh quá lớn (tối đa 2MB).';
            if (selectedWrapper) selectedWrapper.style.display = 'none';
            return;
          }
          const reader = new FileReader();
          reader.onload = function(e) {
            if (selectedImg) { selectedImg.src = e.target.result; selectedImg.style.display = ''; }
            if (selectedName) selectedName.textContent = f.name;
            if (selectedWrapper) selectedWrapper.style.display = '';
            if (fileError) fileError.textContent = '';
          };
          reader.readAsDataURL(f);
        });
      }

      // Ensure form submit checks file error
      const deviceEditForm = document.getElementById('deviceEditForm');
      if (deviceEditForm && fileError) {
        deviceEditForm.addEventListener('submit', function(ev){
          if (fileError.textContent) { ev.preventDefault(); alert('Vui lòng sửa lỗi file ảnh trước khi lưu.'); }
        });
      }
    } catch (e) {
      console.warn('ensureModalFileUI error', e);
    }
  }

  const origOpen = window.openDeviceEditModal;
  window.openDeviceEditModal = function(device) {
    try { if (typeof origOpen === 'function') origOpen(device); } catch(e){ console.warn('origOpen failed', e); }
    try { ensureModalFileUI(); } catch(e){ console.warn('ensureModalFileUI failed', e); }
  };
})();
</script>