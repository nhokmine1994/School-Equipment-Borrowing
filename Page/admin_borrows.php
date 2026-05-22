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
$statusColumnInfo = seb_resolve_phieu_muon_status_column($conn);
if (!empty($statusColumnInfo['ok'])) {
  $statusColumnSql = 'pm.[' . str_replace(']', ']]', $statusColumnInfo['column']) . ']';
  $sql = "SELECT TOP 100
         pm.SoPhieuMuon AS BorrowID,
      pm.IDThietBi,
         pm.TenThietBi,
         pm.TaiKhoan AS Username,
         pm.NgayMuon,
         pm.HanTra AS NgayTraDuKien,
         pm.NgayTraThucTe,
         pm.SoLuong,
         {$statusColumnSql} AS TrangThai,
         pm.IDThietBi,
         pm.GhiChu
       FROM PhieuMuon pm
       ORDER BY pm.NgayMuon DESC, pm.SoPhieuMuon DESC";
  $stmt = sqlsrv_query($conn, $sql);
  if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
      $borrows[] = $row;
    }
  }
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
                <td><?php echo $id; ?></td>
                <td><?php echo $ma; ?></td>
                <td><?php echo $ten; ?></td>
                <td><?php echo $user; ?></td>
                <td><?php echo $ngay_muon; ?></td>
                <td><?php echo $ngay_tra; ?></td>
                <td><?php echo $sl; ?></td>
                <td><span class="admin-chip <?php echo $chipClass; ?>"><?php echo $status; ?></span></td>
                <td>
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

  <script>
    const csrfToken = <?php echo json_encode($csrf_token); ?>;
    const DEFAULT_APPROVED_STATUS = <?php echo json_encode((int)$defaultApprovedId); ?>;
    const DEFAULT_REJECTED_STATUS = <?php echo json_encode((int)$defaultRejectedId); ?>;

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

    function markReturned(id) {
      if (confirm('Đánh dấu thiết bị này đã được trả?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input name="action" value="returned"><input name="csrf_token" value="' + csrfToken + '"><input name="borrow_id" value="' + id + '">';
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