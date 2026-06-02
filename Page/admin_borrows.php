<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Duyệt mượn / trả');
require_once __DIR__ . '/../components/seb_db.php';

$message = '';
$messageType = 'success';
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $borrow_id = trim((string) ($_POST['borrow_id'] ?? ''));

        if ($action === 'approve') {
          $ngay_tra_du_kien = $_POST['ngay_tra_du_kien'] ?? '';
          $newStatus = isset($_POST['new_status']) ? trim((string) $_POST['new_status']) : 'approved';
          $statusColumnInfo = seb_resolve_phieu_muon_status_column($conn);
          $resolvedStatus = seb_resolve_borrow_status_id($conn, $newStatus);

          if (empty($statusColumnInfo['ok']) || empty($resolvedStatus['ok'])) {
            $message = ($statusColumnInfo['error'] ?? $resolvedStatus['error'] ?? 'Thiếu SQL cho trạng thái duyệt.');
            $messageType = 'danger';
            $ok = false;
          } else {
            $statusColumn = '[' . str_replace(']', ']]', $statusColumnInfo['column']) . ']';
            $sql = "UPDATE PhieuMuon SET {$statusColumn} = ?, HanTra = ? WHERE SoPhieuMuon = ?";
            $params = array(&$resolvedStatus['id'], &$ngay_tra_du_kien, &$borrow_id);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt) {
              $ok = sqlsrv_execute($stmt);
            } else {
              $ok = false;
            }
          }

          if (!empty($ok)) {
            $message = 'Duyệt mượn thành công.';
            add_admin_notification($conn, 'borrow', 'Yêu cầu mượn đã duyệt', 'Đơn mượn #' . $borrow_id . ' đã được xử lý.', 'admin_borrows.php');
          } else {
            $message = 'Duyệt thất bại.';
            $messageType = 'danger';
          }
        }

        if ($action === 'reject') {
          $ghi_chu = trim($_POST['ghi_chu'] ?? '');
          $newStatus = isset($_POST['new_status']) ? trim((string) $_POST['new_status']) : 'rejected';
          $statusColumnInfo = seb_resolve_phieu_muon_status_column($conn);
          $resolvedStatus = seb_resolve_borrow_status_id($conn, $newStatus);

          if (empty($statusColumnInfo['ok']) || empty($resolvedStatus['ok'])) {
            $message = ($statusColumnInfo['error'] ?? $resolvedStatus['error'] ?? 'Thiếu SQL cho trạng thái từ chối.');
            $messageType = 'danger';
            $ok = false;
          } else {
            $statusColumn = '[' . str_replace(']', ']]', $statusColumnInfo['column']) . ']';
            $sql = "UPDATE PhieuMuon SET {$statusColumn} = ? WHERE SoPhieuMuon = ?";
            $params = array(&$resolvedStatus['id'], &$borrow_id);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt) {
              $ok = sqlsrv_execute($stmt);
            } else {
              $ok = false;
            }
          }

          if (!empty($ok)) {
            $message = 'Từ chối mượn thành công.';
            add_admin_notification($conn, 'borrow', 'Yêu cầu mượn bị từ chối', 'Đơn mượn #' . $borrow_id . ' đã được xử lý.', 'admin_borrows.php');
          } else {
            $message = 'Từ chối thất bại.';
            $messageType = 'danger';
          }
        }

        if ($action === 'returned') {
          $statusColumnInfo = seb_resolve_phieu_muon_status_column($conn);
          $returnedStatus = seb_resolve_borrow_status_id($conn, 'returned');

          if (empty($statusColumnInfo['ok']) || empty($returnedStatus['ok'])) {
            $message = ($statusColumnInfo['error'] ?? $returnedStatus['error'] ?? 'Thiếu SQL cho trạng thái đã trả.');
            $messageType = 'danger';
            $ok = false;
          } else {
            $statusColumn = '[' . str_replace(']', ']]', $statusColumnInfo['column']) . ']';
            $sql = "UPDATE PhieuMuon SET {$statusColumn} = ? WHERE SoPhieuMuon = ?";
            $params = array(&$returnedStatus['id'], &$borrow_id);
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            if ($stmt) {
              $ok = sqlsrv_execute($stmt);
            } else {
              $ok = false;
            }
          }

          if (!empty($ok)) {
            $message = 'Đánh dấu trả thành công.';
            add_admin_notification($conn, 'borrow', 'Đã trả thiết bị', 'Đơn mượn #' . $borrow_id . ' đã được đánh dấu đã trả.', 'admin_borrows.php');
          } else {
            $message = 'Đánh dấu trả thất bại.';
            $messageType = 'danger';
          }
        }
    }
}

$borrows = [];

// Build a tolerant SELECT for PhieuMuon that works with varying column names
$columns = seb_get_table_columns_info($conn, 'PhieuMuon');
$statusColumnInfo = seb_resolve_phieu_muon_status_column($conn);

$idColSql = '';
$tenColSql = '';
$statusColSql = '';

// Device id column
$deviceCol = seb_resolve_phieu_muon_device_column($conn);
if (!empty($deviceCol['ok'])) {
  $idColSql = 'pm.[' . str_replace(']', ']]', $deviceCol['column']) . '] AS IDThietBi';
} else {
  if (isset($columns['mathietbi'])) {
    $idColSql = 'pm.MaThietBi AS IDThietBi';
  } elseif (isset($columns['idthietbi'])) {
    $idColSql = 'pm.IDThietBi AS IDThietBi';
  } else {
    $idColSql = "'' AS IDThietBi";
  }
}

// TenThietBi column
if (isset($columns['tenthietbi'])) {
  $tenColSql = 'pm.TenThietBi';
} elseif (isset($columns['ten'])) {
  $tenColSql = 'pm.Ten';
} else {
  $tenColSql = "pm.TenThietBi";
}

// Status column (prefer numeric status column, fallback to textual columns)
if (!empty($statusColumnInfo['ok'])) {
  $statusColSql = 'pm.[' . str_replace(']', ']]', $statusColumnInfo['column']) . '] AS TrangThai';
} elseif (isset($columns['tinhtrangmuon'])) {
  $statusColSql = 'pm.TinhTrangMuon AS TrangThai';
} elseif (isset($columns['tinhtrang'])) {
  $statusColSql = 'pm.TinhTrang AS TrangThai';
} elseif (isset($columns['trangthai'])) {
  $statusColSql = 'pm.TrangThai AS TrangThai';
} else {
  $statusColSql = "'' AS TrangThai";
}

// Date columns: HanTra (planned return) and actual return (various possible names)
if (isset($columns['hantra'])) {
  $hanTraSql = 'pm.HanTra AS NgayTraDuKien';
} else {
  $hanTraSql = "'' AS NgayTraDuKien";
}

$actualReturnCandidates = ['ngaytrathucte', 'ngaytrathuc', 'ngaytra'];
$actualReturnSql = "'' AS NgayTraThucTe";
foreach ($actualReturnCandidates as $cand) {
  if (isset($columns[$cand])) {
    $actualReturnSql = 'pm.[' . str_replace(']', ']]', $columns[$cand]['name']) . '] AS NgayTraThucTe';
    break;
  }
}

// Try reading from PhieuMuon first (most installations), but be tolerant to schema variations
$ghiChuSql = "'' AS GhiChu";
if (isset($columns['ghichu'])) {
  $ghiChuSql = 'pm.[' . str_replace(']', ']]', $columns['ghichu']['name']) . '] AS GhiChu';
}

$sql = "SELECT TOP 100
         pm.SoPhieuMuon AS BorrowID,
         {$idColSql},
         {$tenColSql} AS TenThietBi,
         pm.TaiKhoan AS Username,
         pm.NgayMuon,
         {$hanTraSql},
         {$actualReturnSql},
         pm.SoLuong,
         {$statusColSql},
         {$ghiChuSql}
       FROM PhieuMuon pm
       ORDER BY pm.NgayMuon DESC, pm.SoPhieuMuon DESC";

$stmt = sqlsrv_query($conn, $sql);
// Debug: log the query and any errors
$debugPath = __DIR__ . '/../logs/admin_borrows_debug.txt';
@file_put_contents($debugPath, date('c') . "\tPHIEUMUON_QUERY\tSQL=" . str_replace("\n", ' ', $sql) . "\n", FILE_APPEND | LOCK_EX);

if ($stmt) {
  $count = 0;
  while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $borrows[] = $row;
    $count++;
  }
  @file_put_contents($debugPath, date('c') . "\tPHIEUMUON_ROWS\tcount=" . (int)$count . "\n", FILE_APPEND | LOCK_EX);
} else {
  $err = seb_sql_error_message('Lỗi khi đọc PhieuMuon.');
  @file_put_contents($debugPath, date('c') . "\tPHIEUMUON_ERROR\tmsg=" . str_replace("\n", ' ', $err) . "\n", FILE_APPEND | LOCK_EX);
}

if (empty($borrows)) {
  // fallback to legacy Borrows table
  $sql = "SELECT b.BorrowID, b.MaThietBi, t.TenThietBi, b.Username, b.NgayMuon, b.NgayTraDuKien, b.NgayTraThucTe, b.SoLuong, b.TrangThai, b.GhiChu
       FROM Borrows b
      LEFT JOIN Kho t ON b.MaThietBi = t.MaThietBi
       ORDER BY b.NgayMuon DESC";
  $stmt = sqlsrv_query($conn, $sql);
  if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
      $borrows[] = $row;
    }
  }
}

// Load TinhTrangDuyet rows for UI selects
$tinhTrangData = seb_fetch_tinh_trang_duyet_rows($conn);
$tinhTrangRows = [];
if (!empty($tinhTrangData['ok'])) {
  $tinhTrangRows = $tinhTrangData['rows'];
}

// Defaults for approved/rejected ids
$defaultApproved = seb_resolve_borrow_status_id($conn, 'approved');
$defaultApprovedId = !empty($defaultApproved['ok']) ? $defaultApproved['id'] : 1;
$defaultRejected = seb_resolve_borrow_status_id($conn, 'rejected');
$defaultRejectedId = !empty($defaultRejected['ok']) ? $defaultRejected['id'] : 2;
$defaultReturned = seb_resolve_borrow_status_id($conn, 'returned');
$defaultReturnedId = !empty($defaultReturned['ok']) ? $defaultReturned['id'] : 4;

function admin_borrow_date($date)
{
    if ($date === null) {
        return '-';
    }
    if (is_object($date) && method_exists($date, 'format')) {
        return $date->format('d/m/Y H:i');
    }
    $timestamp = strtotime((string) $date);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : htmlspecialchars((string) $date);
}

function admin_borrow_chip($status)
{
  $status = admin_borrow_status_key($status);
  if (in_array($status, ['approved', 'đã duyệt', 'da duyet', '1'], true)) {
        return 'admin-chip-success';
    }
  if (in_array($status, ['returned', 'đã trả', 'da tra', '4'], true)) {
        return 'admin-chip-primary';
    }
  if (in_array($status, ['rejected', 'từ chối', 'tu choi', '2'], true)) {
        return 'admin-chip-danger';
    }
    return 'admin-chip-warning';
}

function admin_borrow_status_key($status)
{
  $status = trim((string) $status);
  if ($status === '') {
    return '';
  }

  global $conn;
  if (ctype_digit($status) && function_exists('seb_resolve_borrow_status_label')) {
    $resolved = seb_resolve_borrow_status_label($conn, $status);
    if ($resolved !== '') {
      $status = $resolved;
    }
  }

  if (function_exists('mb_strtolower')) {
    return mb_strtolower($status, 'UTF-8');
  }
  return strtolower($status);
}

function admin_borrow_is_pending($status)
{
  $s = admin_borrow_status_key($status);
  return in_array($s, ['pending', 'pending approval', 'dang cho', 'đang chờ', 'chờ duyệt', 'waiting', '3'], true) || $s === '';
}

function admin_borrow_is_approved($status)
{
  $s = admin_borrow_status_key($status);
  return in_array($s, ['approved', 'đã duyệt', 'da duyet', '1'], true);
}

function admin_borrow_is_rejected($status)
{
  $s = admin_borrow_status_key($status);
  return in_array($s, ['rejected', 'bị từ chối', 'từ chối', 'tu choi', '2'], true);
}

function admin_borrow_is_returned($status)
{
  $s = admin_borrow_status_key($status);
  return in_array($s, ['returned', 'đã trả', 'da tra', '4'], true);
}


// Sort borrows: pending (0) -> approved (1) -> completed (returned/rejected) (2)
if (!empty($borrows) && is_array($borrows)) {
  $get_time = function ($row) {
    $v = $row['NgayMuon'] ?? null;
    if ($v === null) return 0;
    if (is_object($v) && method_exists($v, 'format')) {
      try { return (int) $v->format('U'); } catch (
 Exception $e) { /* fallthrough */ }
    }
    $ts = @strtotime((string)$v);
    return $ts ? (int)$ts : 0;
  };

  $priority_of = function ($status) {
    if (admin_borrow_is_pending($status)) return 0;
    if (admin_borrow_is_approved($status)) return 1;
    if (admin_borrow_is_returned($status) || admin_borrow_is_rejected($status)) return 2;
    return 3;
  };

  usort($borrows, function ($a, $b) use ($get_time, $priority_of) {
    $sa = (string) ($a['TrangThai'] ?? $a['TinhTrang'] ?? '');
    $sb = (string) ($b['TrangThai'] ?? $b['TinhTrang'] ?? '');
    $pa = $priority_of($sa);
    $pb = $priority_of($sb);
    if ($pa !== $pb) return $pa - $pb;

    $ta = $get_time($a);
    $tb = $get_time($b);
    if ($ta !== $tb) return $tb <=> $ta; // newer first

    $ida = (string) ($a['BorrowID'] ?? '');
    $idb = (string) ($b['BorrowID'] ?? '');
    return strcmp($idb, $ida);
  });
}


$pendingCount = count(array_filter($borrows, function ($item) {
  $s = admin_borrow_status_key($item['TrangThai'] ?? '');
  return in_array($s, ['pending', 'pending approval', 'dang cho', 'đang chờ', 'chờ duyệt', 'waiting', '3'], true) || $s === '';
}));
$approvedCount = count(array_filter($borrows, function ($item) {
  $s = admin_borrow_status_key($item['TrangThai'] ?? '');
  return in_array($s, ['approved', 'đã duyệt', 'da duyet', '1'], true);
}));
$returnedCount = count(array_filter($borrows, function ($item) {
  $s = admin_borrow_status_key($item['TrangThai'] ?? '');
  return in_array($s, ['returned', 'đã trả', 'da tra', '4'], true);
}));
$rejectedCount = count(array_filter($borrows, function ($item) {
  $s = admin_borrow_status_key($item['TrangThai'] ?? '');
  return in_array($s, ['rejected', 'bị từ chối', 'từ chối', 'tu choi', '2'], true);
}));

require_once __DIR__ . '/../components/admin_layout.php';
$adminUsername = $_SESSION['user']['username'] ?? '';
admin_render_head('Duyệt mượn');
admin_render_shell_open($adminUsername);
admin_render_nav('borrows');
admin_render_page_intro(
    'Duyệt yêu cầu mượn',
    'fa-clipboard-check',
    'Xử lý yêu cầu, đánh dấu trả và tạo thông báo tự động cho dashboard.'
);
?>
    <section class="admin-grid-4">
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Chờ duyệt</p>
        <p class="admin-stat-value"><?php echo $pendingCount; ?></p>
        <p class="admin-stat-desc">Đơn mượn mới chưa xử lý.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Đã duyệt</p>
        <p class="admin-stat-value"><?php echo $approvedCount; ?></p>
        <p class="admin-stat-desc">Đang chờ trả hoặc xác nhận hoàn tất.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Đã trả</p>
        <p class="admin-stat-value"><?php echo $returnedCount; ?></p>
        <p class="admin-stat-desc">Các đơn đã hoàn tất.</p>
      </article>
      <article class="admin-card admin-stat">
        <p class="admin-stat-label">Từ chối</p>
        <p class="admin-stat-value"><?php echo $rejectedCount; ?></p>
        <p class="admin-stat-desc">Các đơn không được chấp thuận.</p>
      </article>
    </section>

    <?php if ($message): ?>
      <div class="admin-flash <?php echo $messageType === 'danger' ? 'admin-flash-danger' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-card" style="margin-top:16px;">
      <div class="admin-card-head">
        <div>
          <h2 class="admin-card-title">Danh sách yêu cầu mượn</h2>
          <p class="admin-card-note">Duyệt, từ chối hoặc đánh dấu trả trực tiếp trên từng dòng.</p>
        </div>
      </div>
      <div class="admin-card-body">
        <div class="admin-toolbar" style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
          <input id="borrowSearch" class="admin-input" placeholder="Tìm số phiếu, tài khoản, thiết bị, ngày..." style="flex:1" aria-label="Tìm kiếm">
          <select id="borrowStatusFilter" class="admin-input" style="width:220px;" aria-label="Bộ lọc trạng thái">
            <option value="">Tất cả trạng thái</option>
            <?php if (!empty($tinhTrangRows)): foreach ($tinhTrangRows as $st): ?>
              <option value="<?php echo htmlspecialchars($st['label'] ?? (string)($st['id'] ?? '')); ?>"><?php echo htmlspecialchars($st['label'] ?? (string)($st['id'] ?? '')); ?></option>
            <?php endforeach; else: ?>
              <option value="pending">Chờ duyệt</option>
              <option value="approved">Đã duyệt</option>
              <option value="returned">Đã trả</option>
              <option value="rejected">Từ chối</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>ID thiết bị</th>
                <th>Tên thiết bị</th>
                <th>Người mượn</th>
                <th>Ngày mượn</th>
                <th>Ngày trả dự kiến</th>
                <th>SL</th>
                <th>Trạng thái</th>
                <th>Hành động</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($borrows as $b):
                $idRaw = $b['BorrowID'] ?? '';
                $id = htmlspecialchars((string) $idRaw);
                $ma = htmlspecialchars($b['IDThietBi'] ?? $b['MaThietBi'] ?? '');
                $ten = htmlspecialchars($b['TenThietBi'] ?? '');
                $user = htmlspecialchars($b['Username'] ?? '');
                $ngay_muon = admin_borrow_date($b['NgayMuon'] ?? null);
                $ngay_tra = admin_borrow_date($b['NgayTraDuKien'] ?? null);
                $sl = (int) ($b['SoLuong'] ?? 1);
                $statusRaw = (string) ($b['TrangThai'] ?? 'pending');
                $statusKey = admin_borrow_status_key($statusRaw);
                $statusLabel = seb_resolve_borrow_status_label($conn, $statusRaw);
                $status = htmlspecialchars($statusLabel);
                $chipClass = admin_borrow_chip($statusRaw);
              ?>
              <tr>
                <td data-label="ID"><?php echo $id; ?></td>
                <td data-label="ID THIẾT BỊ"><?php echo $ma; ?></td>
                <td data-label="TÊN THIẾT BỊ"><?php echo $ten; ?></td>
                <td data-label="NGƯỜI MƯỢN"><?php echo $user; ?></td>
                <td data-label="NGÀY MƯỢN"><?php echo $ngay_muon; ?></td>
                <td data-label="NGÀY TRẢ DỰ KIẾN"><?php echo $ngay_tra; ?></td>
                <td data-label="SL"><?php echo $sl; ?></td>
                <td data-label="TRẠNG THÁI"><span class="admin-chip <?php echo $chipClass; ?>"><?php echo $status; ?></span></td>
                <td data-label="HÀNH ĐỘNG">
                  <?php if (in_array($statusKey, ['pending','dang cho','đang chờ','chờ duyệt','pending approval','3'], true)): ?>
                    <div class="admin-actions">
                      <button class="admin-btn admin-btn-success" type="button" onclick="showModal('approve', '<?php echo $id; ?>')">Duyệt</button>
                      <button class="admin-btn admin-btn-danger" type="button" onclick="showModal('reject', '<?php echo $id; ?>')">Từ chối</button>
                    </div>
                  <?php elseif (in_array($statusKey, ['approved','đã duyệt','da duyet','1'], true)): ?>
                    <button class="admin-btn admin-btn-primary" type="button" onclick="markReturned('<?php echo $id; ?>')">Đã trả</button>
                  <?php else: ?>
                    <span class="admin-chip admin-chip-primary">Hoàn tất</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <div id="approveModal" class="modal" style="display:none;position:fixed;inset:0;background:rgba(13,22,36,0.56);z-index:9999;align-items:center;justify-content:center;">
    <div class="admin-card" style="width:min(480px, calc(100vw - 24px));">
      <div class="admin-card-head">
        <div>
          <h2 class="admin-card-title">Duyệt mượn thiết bị</h2>
          <p class="admin-card-note">Chọn ngày trả dự kiến trước khi duyệt.</p>
        </div>
      </div>
      <div class="admin-card-body">
        <form method="post" onsubmit="submitApprove(event)">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" id="approveId" name="borrow_id">
              <div class="admin-field">
                <label>Trạng thái duyệt</label>
                <select class="admin-input" id="approveStatus" name="new_status">
                  <?php if (!empty($tinhTrangRows)): foreach ($tinhTrangRows as $st): ?>
                    <option value="<?php echo (int)($st['id'] ?? 0); ?>" <?php echo ((int)($st['id'] ?? 0) === (int)$defaultApprovedId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['label'] ?? ''); ?></option>
                  <?php endforeach; else: ?>
                    <option value="1">Đã duyệt</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="admin-field">
                <label>Ngày trả dự kiến</label>
                <input class="admin-input" type="date" id="ngayTraDuKien" name="ngay_tra_du_kien" required>
              </div>
          <div class="admin-actions" style="margin-top:12px;">
            <button type="submit" class="admin-btn admin-btn-success">Duyệt</button>
            <button type="button" class="admin-btn admin-btn-soft" onclick="closeModal()">Hủy</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="rejectModal" class="modal" style="display:none;position:fixed;inset:0;background:rgba(13,22,36,0.56);z-index:9999;align-items:center;justify-content:center;">
    <div class="admin-card" style="width:min(480px, calc(100vw - 24px));">
      <div class="admin-card-head">
        <div>
          <h2 class="admin-card-title">Từ chối yêu cầu mượn</h2>
          <p class="admin-card-note">Nhập lý do từ chối nếu cần.</p>
        </div>
      </div>
      <div class="admin-card-body">
        <form method="post" onsubmit="submitReject(event)">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" id="rejectId" name="borrow_id">
          <div class="admin-field">
            <label>Trạng thái</label>
            <select class="admin-input" id="rejectStatus" name="new_status">
              <?php if (!empty($tinhTrangRows)): foreach ($tinhTrangRows as $st): ?>
                <option value="<?php echo (int)($st['id'] ?? 0); ?>" <?php echo ((int)($st['id'] ?? 0) === (int)$defaultRejectedId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['label'] ?? ''); ?></option>
              <?php endforeach; else: ?>
                <option value="2">Từ chối</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="admin-field">
            <label>Ghi chú (lý do)</label>
            <textarea class="admin-textarea" id="ghiChu" name="ghi_chu" rows="3"></textarea>
          </div>
          <div class="admin-actions" style="margin-top:12px;">
            <button type="submit" class="admin-btn admin-btn-danger">Từ chối</button>
            <button type="button" class="admin-btn admin-btn-soft" onclick="closeModal()">Hủy</button>
          </div>
        </form>
      </div>
    </div>

    <script src="../Javascript/toast.js?v=20260522"></script>
    <script src="../Javascript/seb_api.js?v=20260520"></script>
    <script src="../Javascript/Java.js?v=20260520"></script>

    <script>
    const csrfToken = <?php echo json_encode($csrf_token); ?>;
    const DEFAULT_APPROVED_STATUS = <?php echo json_encode((int)$defaultApprovedId); ?>;
    const DEFAULT_REJECTED_STATUS = <?php echo json_encode((int)$defaultRejectedId); ?>;
    const DEFAULT_RETURNED_STATUS = <?php echo json_encode((int)$defaultReturnedId); ?>;

    // Live search + status filter (no submit button). Filters table rows as user types/selects.
    (function() {
      const input = document.getElementById('borrowSearch');
      const select = document.getElementById('borrowStatusFilter');
      let debounce = null;

      function normalize(s) {
        return String(s || '').toLowerCase().trim();
      }

      function rowMatches(row, q, statusFilter) {
        const tds = Array.from(row.querySelectorAll('td'));
        const text = tds.map(td => normalize(td.innerText)).join(' ');
        const matchesQuery = q === '' || text.indexOf(q) !== -1;
        if (!matchesQuery) return false;
        if (!statusFilter) return true;
        // try to match status cell specifically
        const statusCell = tds.find(td => (td.getAttribute('data-label') || '').toLowerCase().indexOf('trạng') !== -1 || (td.getAttribute('data-label') || '').toLowerCase().indexOf('status') !== -1);
        if (statusCell) {
          return normalize(statusCell.innerText).indexOf(statusFilter) !== -1;
        }
        return normalize(text).indexOf(statusFilter) !== -1;
      }

      function filterNow() {
        const q = normalize(input.value);
        const status = normalize(select.value);
        const rows = document.querySelectorAll('.admin-table tbody tr');
        rows.forEach(r => {
          try {
            if (rowMatches(r, q, status)) r.style.display = '';
            else r.style.display = 'none';
          } catch (e) { /* ignore */ }
        });
      }

      if (input) {
        input.addEventListener('input', () => {
          clearTimeout(debounce);
          debounce = setTimeout(filterNow, 180);
        });
        input.addEventListener('keydown', (ev) => {
          if (ev.key === 'Enter') {
            ev.preventDefault();
            clearTimeout(debounce);
            filterNow();
          }
        });
      }
      if (select) {
        select.addEventListener('change', filterNow);
      }
    })();

    function showModal(type, id) {
      const approveModal = document.getElementById('approveModal');
      const rejectModal = document.getElementById('rejectModal');

      if (type === 'approve') {
        document.getElementById('approveId').value = id;
        document.getElementById('ngayTraDuKien').value = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        const apr = document.getElementById('approveStatus'); if (apr) apr.value = DEFAULT_APPROVED_STATUS;
        approveModal.style.display = 'flex';
        rejectModal.style.display = 'none';
      } else if (type === 'reject') {
        document.getElementById('rejectId').value = id;
        document.getElementById('ghiChu').value = '';
        const rej = document.getElementById('rejectStatus'); if (rej) rej.value = DEFAULT_REJECTED_STATUS;
        rejectModal.style.display = 'flex';
        approveModal.style.display = 'none';
      }
    }

    function closeModal() {
      document.getElementById('approveModal').style.display = 'none';
      document.getElementById('rejectModal').style.display = 'none';
    }

    function submitApprove(event) {
      event.preventDefault();
      event.target.submit();
    }

    function submitReject(event) {
      event.preventDefault();
      event.target.submit();
    }

    async function markReturned(id) {
      const message = 'Đánh dấu thiết bị này đã được trả?';
      let accepted = false;
      if (typeof window.sebConfirm === 'function') {
        try {
          accepted = await window.sebConfirm(message, 'Xác nhận');
        } catch (e) {
          accepted = false;
        }
      } else {
        accepted = confirm(message);
      }

      if (accepted) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input name="action" value="returned">'
          + '<input name="csrf_token" value="' + csrfToken + '">'
          + '<input name="borrow_id" value="' + id + '">'
          + '<input name="new_status" value="' + DEFAULT_RETURNED_STATUS + '">';
        document.body.appendChild(form);
        form.submit();
      }
    }

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }

      const approveModal = document.getElementById('approveModal');
      const rejectModal = document.getElementById('rejectModal');
      const isApproveOpen = approveModal && approveModal.style.display === 'flex';
      const isRejectOpen = rejectModal && rejectModal.style.display === 'flex';

      if (isApproveOpen || isRejectOpen) {
        closeModal();
      }
    });
  </script>
<?php admin_render_shell_close(); ?>