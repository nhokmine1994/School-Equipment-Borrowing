<?php
include '../connect.php';
require_once 'admin_auth.php';
require_admin();
seb_require_admin_connection($conn, 'Đăng tin tức');

$username = $_SESSION['user']['username'] ?? '';
$message = '';
$messageTone = 'success';
$csrf_token = generate_csrf_token();

// CHỨNG quy định các LoaiThongBao cho CHECK constraint CK_LoaiThongBao
$loaiHopsLe = ['Bổ sung', 'Sửa chữa', 'Cập nhật', 'Bảo trì'];

function admin_news_classify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    if (preg_match('/bảo trì|bao tri|sửa chữa|sua chua|hỏng|hong/', $text)) return 'Bảo trì';
    if (preg_match('/cập nhật|cap nhat|nâng cấp|nang cap|thay đổi|thay doi/', $text)) return 'Cập nhật';
    if (preg_match('/thiết bị|thiet bi|bổ sung|bo sung|mua sắm|mua sam/', $text)) return 'Bổ sung';
    return 'Cập nhật';
}

function admin_news_fetch_source(string $url): array
{
    $parts = parse_url(trim($url));
    if (!$parts || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
        return ['ok' => false, 'error' => 'Link phải bắt đầu bằng http:// hoặc https://.'];
    }
    $host = strtolower((string) $parts['host']);
    $ips = gethostbynamel($host) ?: [];
    foreach ($ips as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['ok' => false, 'error' => 'Link trỏ tới địa chỉ mạng nội bộ và không được phép.'];
        }
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_USERAGENT => 'SEB-NewsImporter/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);
    $html = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    curl_close($ch);
    if (!is_string($html) || $html === '' || $status < 200 || $status >= 300 || (strlen($html) > 3 * 1024 * 1024)) {
        return ['ok' => false, 'error' => 'Không đọc được nội dung link nguồn.'];
    }
    if ($contentType !== '' && strpos($contentType, 'html') === false) {
        return ['ok' => false, 'error' => 'Link nguồn không phải trang HTML.'];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    $meta = static function (string $property) use ($xpath): string {
        $nodes = $xpath->query('//meta[@property="' . $property . '"]/@content | //meta[@name="' . $property . '"]/@content');
        return $nodes && $nodes->length ? trim((string) $nodes->item(0)->nodeValue) : '';
    };
    $title = $meta('og:title');
    if ($title === '') {
        $titleNodes = $xpath->query('//title');
        $title = $titleNodes && $titleNodes->length ? trim((string) $titleNodes->item(0)->textContent) : '';
    }
    $description = $meta('og:description');
    if ($description === '') $description = $meta('description');
    $image = $meta('og:image');
    $contentNodes = $xpath->query('//article | //main | //body');
    $content = $description;
    if ($contentNodes && $contentNodes->length) {
        $paragraphs = $xpath->query('.//p', $contentNodes->item(0));
        $partsText = [];
        if ($paragraphs) foreach ($paragraphs as $paragraph) {
            $text = trim(preg_replace('/\s+/u', ' ', $paragraph->textContent));
            if ($text !== '' && mb_strlen($text, 'UTF-8') > 30) $partsText[] = $text;
        }
        if ($partsText) $content = implode("\n\n", array_slice($partsText, 0, 30));
    }
    if ($title === '') $title = 'Tin tức từ nguồn nhập';
    if ($content === '') $content = $title;
    return ['ok' => true, 'title' => mb_substr($title, 0, 250), 'content' => mb_substr($content, 0, 12000), 'imageUrl' => $image, 'category' => admin_news_classify($title . ' ' . $content)];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'preview_url') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => 'CSRF token không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(admin_news_fetch_source((string) ($_POST['source_url'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF token không hợp lệ. Vui lòng thử lại!';
        $messageTone = 'danger';
    } else {
        $maTin = (int) ($_POST['ma_tin'] ?? 0);
        if ($action === 'add') {
            $loaiTin = trim((string) ($_POST['loai_tin'] ?? 'Bổ sung'));
            $tieuDe = trim((string) ($_POST['tieu_de'] ?? ''));
            $noiDung = trim((string) ($_POST['noi_dung'] ?? ''));
            $trangThai = trim((string) ($_POST['trang_thai'] ?? 'Hiển thị'));
            $nguonLink = trim((string) ($_POST['nguon_link'] ?? ''));
            $imageName = trim((string) ($_POST['hinh_anh_url'] ?? ''));
            if (!empty($_FILES['hinh_anh']['name'])) {
                $upload = validate_and_upload_file($_FILES['hinh_anh'], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                if (!$upload['success']) {
                    $message = $upload['message'];
                    $messageTone = 'danger';
                } else {
                    $imageName = $upload['filename'];
                }
            }

            if (!in_array($loaiTin, $loaiHopsLe, true)) {
                $message = "Loại tin không hợp lệ. Chọn một trong: " . implode(', ', $loaiHopsLe);
                $messageTone = 'danger';
            } elseif ($tieuDe === '' || $noiDung === '') {
                $message = 'Nội dung không được bỏ trống!';
                $messageTone = 'danger';
            } else {
                $stmt = @sqlsrv_query(
                    $conn,
                    "INSERT INTO ThongBao (LoaiThongBao, TieuDe, NoiDung, HinhAnh, NguonLink, NgayDang, NguoiDang, TrangThai) VALUES (?, ?, ?, ?, ?, GETDATE(), ?, ?)",
                    [$loaiTin, $tieuDe, $noiDung, $imageName, $nguonLink !== '' ? $nguonLink : null, $username, $trangThai]
                );
                if ($stmt) {
                    $message = 'Đăng tin tức thành công!';
                    add_admin_notification($conn, 'news', 'Tin tức mới đã đăng', 'Bài tin [' . $loaiTin . '] đã được đăng lên trang chủ.', 'admin_tin_tuc.php');
                } else {
                    $message = 'Đăng tin thất bại (lỗi database).';
                    $messageTone = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            if ($maTin <= 0) {
                $message = 'ID tin không hợp lệ!';
                $messageTone = 'danger';
            } else {
                $stmt = @sqlsrv_query($conn, "DELETE FROM ThongBao WHERE MaThongBao = ?", [$maTin]);
                $message = $stmt ? 'Xóa tin thành công.' : 'Xóa thất bại.';
                $messageTone = $stmt ? 'success' : 'danger';
            }
        } elseif ($action === 'toggle') {
            if ($maTin <= 0) {
                $message = 'ID tin không hợp lệ!';
                $messageTone = 'danger';
            } else {
                // Đảo trạng thái: Hiển thị <-> Ẩn
                $stmt = @sqlsrv_query($conn, "UPDATE ThongBao SET TrangThai = CASE WHEN LOWER(TrangThai) = N'hiển thị' THEN N'Ẩn' ELSE N'Hiển thị' END WHERE MaThongBao = ?", [$maTin]);
                $message = $stmt ? 'Đổi trạng thái thành công.' : 'Cập nhật thất bại.';
                $messageTone = $stmt ? 'success' : 'danger';
            }
        }
    }
}

$news = [];
$newsStmt = @sqlsrv_query($conn, "SELECT MaThongBao, LoaiThongBao, TieuDe, NoiDung, HinhAnh, NguonLink, NgayDang, NguoiDang, TrangThai FROM ThongBao ORDER BY NgayDang DESC, MaThongBao DESC");
if ($newsStmt) {
    while ($row = sqlsrv_fetch_array($newsStmt, SQLSRV_FETCH_ASSOC)) {
        $news[] = $row;
    }
}

require_once __DIR__ . '/../components/admin_layout.php';
admin_render_head('Đăng tin tức');
admin_render_shell_open($username);
admin_render_nav('tin_tuc_page_news');
admin_render_page_intro(
    'Đăng tin tức',
    'fa-newspaper',
    'Thêm thông báo,bảo trì, sửa chữa hay cập nhật tin tức cho trang chủ.'
);
?>
<?php if (!empty($message)): ?>
  <div class="admin-flash admin-flash-<?php echo $messageTone; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<section class="admin-card" style="margin-bottom: 16px;">
  <div class="admin-card-head">
    <div>
      <h2 class="admin-card-title"><i class="fas fa-feather"></i> Soạn bài tin tức</h2>
      <p class="admin-card-note">Cấu hình tiêu trạng là <code>Hiển thị</code> để tin có trên trang chủ. Một bài 5-6 đoạn. Riêng biệt mỗi ý có xuống dòng.</p>
    </div>
  </div>
  <div class="admin-card-body">
    <form method="POST" enctype="multipart/form-data" id="newsComposeForm">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <input type="hidden" name="hinh_anh_url" id="hinh_anh_url">
      <div class="admin-form-grid">
        <div class="admin-field admin-col-4">
          <label for="loai_tin">Loại tin *</label>
          <select class="admin-input" name="loai_tin" required>
            <?php foreach ($loaiHopsLe as $loaiCoSan): ?>
              <option value="<?php echo htmlspecialchars($loaiCoSan); ?>"><?php echo htmlspecialchars($loaiCoSan); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field admin-col-3">
          <label for="trang_thai">Trạng thái *</label>
          <select class="admin-input" name="trang_thai" required>
            <option value="Hiển thị">Hiển thị</option>
            <option value="Ẩn">Ẩn</option>
          </select>
        </div>
        <div class="admin-field admin-col-5">
          <label for="nguoi_dang">Người đăng</label>
          <input class="admin-input" value="<?php echo htmlspecialchars($username); ?>" disabled>
        </div>
        <div class="admin-field admin-col-8">
          <label for="tieu_de">Tiêu đề bài viết *</label>
          <input class="admin-input" name="tieu_de" id="tieu_de" maxlength="250" required>
        </div>
        <div class="admin-field admin-col-4">
          <label for="nguon_link">Nhập link nguồn</label>
          <div style="display:flex;gap:8px;"><input class="admin-input" name="nguon_link" id="nguon_link" type="url" placeholder="https://..."><button type="button" class="admin-btn admin-btn-soft" id="importNewsUrl">Lấy tin</button></div>
          <small>Nội dung sẽ được đọc, rút gọn và phân loại tự động.</small>
        </div>
        <div class="admin-field admin-col-12">
          <label for="noi_dung">Nội dung bài tin *</label>
          <textarea class="admin-input admin-textarea" name="noi_dung" id="noi_dung" style="min-height: 120px;" placeholder="Nhập nội dung tin tức. Xuống dòng để tạo đoạn..." required></textarea>
        </div>
        <div class="admin-field admin-col-6">
          <label for="hinh_anh">Ảnh đại diện</label>
          <input class="admin-input" type="file" name="hinh_anh" id="hinh_anh" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <div class="admin-field admin-col-6"><div id="newsImagePreview" style="display:none;"><img alt="Xem trước ảnh" style="max-width:220px;max-height:130px;border-radius:8px;object-fit:cover;"></div></div>
      </div>
      <div class="admin-actions" style="margin-top: 14px;">
        <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-paper-plane"></i> Đăng tin tức</button>
        <button type="reset" class="admin-btn admin-btn-soft"><i class="fas fa-rotate-left"></i> Xóa form</button>
      </div>
    </form>
  </div>
</section>

<section class="admin-card">
  <div class="admin-card-head">
    <div>
      <h2 class="admin-card-title"><i class="fas fa-list"></i> Danh sách tin tức (<?php echo count($news); ?>)</h2>
      <p class="admin-card-note">Tin 'Hiển thị' sẽ xuất hiện trên trang Tin tức; tin 'Ẩn' chỉ dành cho quản trị.</p>
    </div>
  </div>
  <div class="admin-card-body">
    <?php if (empty($news)): ?>
      <div class="admin-empty"><i class="fas fa-inbox" style="font-size: 30px; display: block; margin-bottom: 10px; color: #94a3b8;"></i>Chưa có tin tức nào.</div>
    <?php else: ?>
      <div class="admin-notification-feed">
      <?php foreach ($news as $tin): $id = (int) $tin['MaThongBao']; $isShow = strtolower(trim((string) $tin['TrangThai'])) === 'hiển thị'; ?>
        <article class="admin-notification-item" style="flex-wrap: wrap;">
          <div class="admin-notification-dot warn"><i class="fas fa-newspaper"></i></div>
          <div style="min-width: 0; flex: 1;">
            <?php if (!empty($tin['HinhAnh'])): ?><img src="<?php echo htmlspecialchars((string) $tin['HinhAnh']); ?>" alt="" style="width:96px;height:64px;object-fit:cover;border-radius:7px;float:left;margin:0 12px 6px 0;"><?php endif; ?>
            <div style="margin-bottom: 5px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <span class="admin-chip admin-chip-primary" style="padding: 3px 10px; font-size: 12px;">
                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($tin['LoaiThongBao']); ?>
              </span>
              <span class="admin-chip <?php echo $isShow ? 'admin-chip-success' : 'admin-chip-muted'; ?>" style="padding: 3px 9px; font-size: 12px;">
                <?php echo $isShow ? 'Hiển thị' : 'Ẩn'; ?>
              </span>
              <small style="color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($tin['NguoiDang']); ?> · <?php echo is_object($tin['NgayDang']) ? $tin['NgayDang']->format('d/m/Y') : ''; ?></small>
            </div>
            <strong style="display:block;margin-bottom:5px;color:#123d63;"><?php echo htmlspecialchars((string) ($tin['TieuDe'] ?? '')); ?></strong>
            <div style="white-space: pre-wrap; word-break: break-word; font-size: 13.5px; color: #334155; max-height: 86px; overflow-y: auto;">
              <?php echo htmlspecialchars((string) $tin['NoiDung']); ?>
            </div>
          </div>
          <div style="display: flex; gap: 8px; flex-shrink: 0;">
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="ma_tin" value="<?php echo $id; ?>">
              <button type="submit" class="admin-btn admin-btn-soft" title="<?php echo $isShow ? 'Ẩn tin' : 'Hiện tin'; ?>"><i class="fas fa-eye<?php echo $isShow ? '-slash' : ''; ?>"></i></button>
            </form>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Xóa tin tức này?');">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ma_tin" value="<?php echo $id; ?>">
              <button type="submit" class="admin-btn admin-btn-danger" title="Xóa"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
(() => {
  const form = document.getElementById('newsComposeForm');
  const importButton = document.getElementById('importNewsUrl');
  const source = document.getElementById('nguon_link');
  const title = document.getElementById('tieu_de');
  const content = document.getElementById('noi_dung');
  const category = document.querySelector('select[name="loai_tin"]');
  const image = document.getElementById('hinh_anh');
  const preview = document.getElementById('newsImagePreview');
  const csrf = form?.querySelector('input[name="csrf_token"]')?.value || '';
  importButton?.addEventListener('click', async () => {
    if (!source.value.trim()) return;
    importButton.disabled = true;
    importButton.textContent = 'Đang lấy...';
    try {
      const body = new URLSearchParams({ action: 'preview_url', csrf_token: csrf, source_url: source.value.trim() });
      const response = await fetch('admin_tin_tuc.php', { method: 'POST', body });
      const data = await response.json();
      if (!data.ok) throw new Error(data.error || 'Không lấy được nội dung.');
      title.value = data.title || '';
      content.value = data.content || '';
      category.value = data.category || 'Cập nhật';
      document.getElementById('hinh_anh_url').value = data.imageUrl || '';
      if (data.imageUrl) {
        preview.style.display = 'block';
        preview.querySelector('img').src = data.imageUrl;
      }
    } catch (error) {
      window.alert(error.message || 'Không lấy được nội dung từ link.');
    } finally {
      importButton.disabled = false;
      importButton.textContent = 'Lấy tin';
    }
  });
  image?.addEventListener('change', () => {
    const file = image.files?.[0];
    if (!file) return;
    preview.style.display = 'block';
    preview.querySelector('img').src = URL.createObjectURL(file);
  });
})();
</script>
<?php admin_render_shell_close(); ?>
