<?php
require_once 'admin_auth.php';
require_admin();
include '../connect.php';

$message = '';

// Handle approve/reject/return borrow
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $borrow_id = (int) ($_POST['borrow_id'] ?? 0);
    
    if ($action === 'approve') {
        $ngay_tra_du_kien = $_POST['ngay_tra_du_kien'] ?? '';
        $sql = "UPDATE Borrows SET TrangThai = 'approved', NgayTraDuKien = ? WHERE BorrowID = ?";
        $params = array(&$ngay_tra_du_kien, &$borrow_id);
        $stmt = sqlsrv_prepare($conn, $sql, $params);
        if ($stmt && sqlsrv_execute($stmt)) $message = 'Duyệt mượn thành công.'; else $message = 'Duyệt thất bại.';
    }
    
    if ($action === 'reject') {
        $ghi_chu = trim($_POST['ghi_chu'] ?? '');
        $sql = "UPDATE Borrows SET TrangThai = 'rejected', GhiChu = ? WHERE BorrowID = ?";
        $params = array(&$ghi_chu, &$borrow_id);
        $stmt = sqlsrv_prepare($conn, $sql, $params);
        if ($stmt && sqlsrv_execute($stmt)) $message = 'Từ chối mượn thành công.'; else $message = 'Từ chối thất bại.';
    }
    
    if ($action === 'returned') {
        $sql = "UPDATE Borrows SET TrangThai = 'returned', NgayTraThucTe = GETDATE() WHERE BorrowID = ?";
        $params = array(&$borrow_id);
        $stmt = sqlsrv_prepare($conn, $sql, $params);
        if ($stmt && sqlsrv_execute($stmt)) $message = 'Đánh dấu trả thành công.'; else $message = 'Đánh dấu trả thất bại.';
    }
}

// Fetch all borrow requests
$borrows = [];
$sql = "SELECT b.BorrowID, b.MaThietBi, t.TenThietBi, b.Username, b.NgayMuon, b.NgayTraDuKien, b.SoLuong, b.TrangThai, b.GhiChu
         FROM Borrows b
         LEFT JOIN ThietBi t ON b.MaThietBi = t.MaThietBi
         ORDER BY b.NgayMuon DESC";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $borrows[] = $row;
    }
}

function format_date($date) {
    if ($date === null) return '-';
    if (is_object($date)) $date = $date->format('Y-m-d H:i');
    return htmlspecialchars(substr($date, 0, 16));
}

function status_class($status) {
    $s = strtolower($status ?? '');
    if ($s === 'pending') return 'style="color:#f70"';
    if ($s === 'approved') return 'style="color:#0a0"';
    if ($s === 'returned') return 'style="color:#00a"';
    if ($s === 'rejected') return 'style="color:#a00"';
    return '';
}

?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Duyệt mượn</title>
  <link rel="stylesheet" href="../CSS/main.css">
  <style>
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;font-size:13px}
    th{background:#f0f0f0}
    .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999}
    .modal.show{display:flex;align-items:center;justify-content:center}
    .modal-content{background:white;padding:20px;border-radius:5px;max-width:400px}
    .modal-content input, .modal-content textarea{width:100%;padding:6px;margin:5px 0}
    button{padding:6px 12px;margin:2px;cursor:pointer}
    .btn-approve{background:#0a0;color:white}
    .btn-reject{background:#a00;color:white}
    .btn-returned{background:#00a;color:white}
  </style>
</head>
<body>
  <div style="padding:20px">
    <h2>Duyệt Yêu Cầu Mượn (Admin)</h2>
    <p><a href="admin_thiet_bi.php">Quản lý thiết bị</a> | <a href="admin_users.php">Quản lý người dùng</a> | <a href="admin_login.php">Đăng xuất</a></p>
    <?php if ($message): ?><p style="color:green"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

    <table>
      <tr>
        <th>ID</th><th>Mã TB</th><th>Tên Thiết Bị</th><th>Người mượn</th><th>Ngày mượn</th>
        <th>Ngày trả dự kiến</th><th>Số lượng</th><th>Trạng thái</th><th>Hành động</th>
      </tr>
      <?php foreach ($borrows as $b):
        $id = (int) ($b['BorrowID'] ?? 0);
        $ma = htmlspecialchars($b['MaThietBi'] ?? '');
        $ten = htmlspecialchars($b['TenThietBi'] ?? '');
        $user = htmlspecialchars($b['Username'] ?? '');
        $ngay_muon = format_date($b['NgayMuon'] ?? null);
        $ngay_tra = format_date($b['NgayTraDuKien'] ?? null);
        $sl = (int) ($b['SoLuong'] ?? 1);
        $status = htmlspecialchars($b['TrangThai'] ?? 'pending');
      ?>
      <tr>
        <td><?php echo $id; ?></td>
        <td><?php echo $ma; ?></td>
        <td><?php echo $ten; ?></td>
        <td><?php echo $user; ?></td>
        <td><?php echo $ngay_muon; ?></td>
        <td><?php echo $ngay_tra; ?></td>
        <td><?php echo $sl; ?></td>
        <td <?php echo status_class($status); ?>><?php echo $status; ?></td>
        <td>
          <?php if ($status === 'pending'): ?>
            <button onclick="showModal('approve', <?php echo $id; ?>)" class="btn-approve">Duyệt</button>
            <button onclick="showModal('reject', <?php echo $id; ?>)" class="btn-reject">Từ chối</button>
          <?php elseif ($status === 'approved'): ?>
            <button onclick="markReturned(<?php echo $id; ?>)" class="btn-returned">Đã trả</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- Modal duyệt -->
  <div id="approveModal" class="modal">
    <div class="modal-content">
      <h3>Duyệt mượn thiết bị</h3>
      <form method="post" onsubmit="submitApprove(event)">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" id="approveId" name="borrow_id">
        <label>Ngày trả dự kiến:</label>
        <input type="date" id="ngayTraDuKien" name="ngay_tra_du_kien" required>
        <button type="submit" class="btn-approve">Duyệt</button>
        <button type="button" onclick="closeModal()">Hủy</button>
      </form>
    </div>
  </div>

  <!-- Modal từ chối -->
  <div id="rejectModal" class="modal">
    <div class="modal-content">
      <h3>Từ chối yêu cầu mượn</h3>
      <form method="post" onsubmit="submitReject(event)">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" id="rejectId" name="borrow_id">
        <label>Ghi chú (lý do):</label>
        <textarea id="ghiChu" name="ghi_chu" rows="3"></textarea>
        <button type="submit" class="btn-reject">Từ chối</button>
        <button type="button" onclick="closeModal()">Hủy</button>
      </form>
    </div>
  </div>

  <script>
    function showModal(type, id) {
      if (type === 'approve') {
        document.getElementById('approveId').value = id;
        document.getElementById('ngayTraDuKien').value = new Date(Date.now() + 7*24*60*60*1000).toISOString().split('T')[0];
        document.getElementById('approveModal').classList.add('show');
      } else if (type === 'reject') {
        document.getElementById('rejectId').value = id;
        document.getElementById('ghiChu').value = '';
        document.getElementById('rejectModal').classList.add('show');
      }
    }
    function closeModal() {
      document.getElementById('approveModal').classList.remove('show');
      document.getElementById('rejectModal').classList.remove('show');
    }
    function submitApprove(e) {
      e.preventDefault();
      document.querySelector('#approveModal form').submit();
    }
    function submitReject(e) {
      e.preventDefault();
      document.querySelector('#rejectModal form').submit();
    }
    function markReturned(id) {
      if (confirm('Đánh dấu thiết bị này đã được trả?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input name="action" value="returned"><input name="borrow_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>
</body>
</html>