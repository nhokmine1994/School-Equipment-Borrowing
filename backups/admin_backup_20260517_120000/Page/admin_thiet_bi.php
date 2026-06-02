<?php
require_once 'admin_auth.php';
require_admin();
include '../connect.php';

$message = '';
// handle image upload and update HinhAnh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'upload_image' && !empty($_POST['ma'])) {
        $ma = $_POST['ma'];
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $targetDir = __DIR__ . '/../Images/devices/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $tmp = $_FILES['image']['tmp_name'];
            $name = basename($_FILES['image']['name']);
            $target = $targetDir . $name;
            if (move_uploaded_file($tmp, $target)) {
                // update DB
                $sql = "UPDATE ThietBi SET HinhAnh = ? WHERE MaThietBi = ?";
                $params = array(&$name, &$ma);
                $stmt = sqlsrv_prepare($conn, $sql, $params);
                if ($stmt && sqlsrv_execute($stmt)) {
                    $message = 'Upload ảnh và cập nhật thành công.';
                } else {
                    $message = 'Upload ảnh xong nhưng cập nhật DB lỗi.';
                }
            } else {
                $message = 'Không thể di chuyển file upload.';
            }
        } else {
            $message = 'Vui lòng chọn file ảnh hợp lệ.';
        }
    }
    if ($action === 'delete' && !empty($_POST['ma'])) {
        $ma = $_POST['ma'];
        $sql = "DELETE FROM ThietBi WHERE MaThietBi = ?";
        $params = array(&$ma);
        $stmt = sqlsrv_prepare($conn, $sql, $params);
        if ($stmt && sqlsrv_execute($stmt)) $message = 'Xóa thiết bị thành công.'; else $message = 'Xóa thất bại.';
    }
}

// fetch devices
$devices = [];
$sql = "EXEC sp_XemKho";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $devices[] = $row;
    }
}

?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Quản lý thiết bị</title>
  <link rel="stylesheet" href="../CSS/main.css">
  <style> table{width:100%;border-collapse:collapse} th,td{border:1px solid #ddd;padding:8px} img{max-width:80px}</style>
</head>
<body>
  <div style="padding:20px">
    <h2>Quản lý Thiết bị (Admin)</h2>
    <p><a href="admin_login.php">Back to login</a> | <a href="admin_users.php">Quản lý người dùng</a></p>
    <?php if ($message): ?><p style="color:green"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

    <table>
      <tr><th>Mã</th><th>Tên</th><th>Số lượng</th><th>Ảnh</th><th>Hành động</th></tr>
      <?php foreach ($devices as $d):
        $ma = htmlspecialchars($d['MaThietBi'] ?? $d['ID'] ?? '');
        $ten = htmlspecialchars($d['TenThietBi'] ?? $d['Ten'] ?? '');
        $sl = htmlspecialchars($d['SoLuong'] ?? '');
        $img = htmlspecialchars($d['HinhAnh'] ?? $d['Anh'] ?? '');
        $imgPath = $img ? ('../Images/devices/' . $img) : '';
      ?>
      <tr>
        <td><?php echo $ma;?></td>
        <td><?php echo $ten;?></td>
        <td><?php echo $sl;?></td>
        <td><?php if ($imgPath): ?><img src="<?php echo $imgPath;?>" alt="<?php echo $ten;?>"><?php else:?><em>chưa có</em><?php endif;?></td>
        <td>
          <form method="post" enctype="multipart/form-data" style="display:inline-block">
            <input type="hidden" name="action" value="upload_image">
            <input type="hidden" name="ma" value="<?php echo $ma; ?>">
            <input type="file" name="image" accept="image/*" required>
            <button type="submit">Upload ảnh</button>
          </form>
          <form method="post" style="display:inline-block;margin-left:6px" onsubmit="return confirm('Xóa thiết bị?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="ma" value="<?php echo $ma; ?>">
            <button type="submit" style="color:#b00">Xóa</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</body>
</html>