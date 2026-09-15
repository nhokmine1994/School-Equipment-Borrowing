# Báo cáo thay đổi và fix bảo mật/deployment

## 1) Vấn đề gốc

Trang web đang có cấu trúc hỗn hợp giữa PHP và React như sau:

- Root entry `index.php` redirect thẳng sang `index.html`
- file `index.html` lại load `/src/main.jsx` trực tiếp
- trên môi trường production/host, không có môi trường Vite chạy để xử lý `/src` và module React
- kết quả: browser mở root website, nhưng do không có build/asset hợp lệ nên trang trắng hoặc không render

Đây là lỗi cấu hình deploy, không phải lỗi compile React.

## 2) Thay đổi đã thực hiện

### 2.1 Sửa entry chính
- `index.php` không còn redirect thẳng sang `index.html`
- `index.php` bây giờ kiểm tra xem build production có tồn tại ở `dist/index.html` hay không
- nếu có, PHP sẽ serve nội dung build production ngay
- nếu không, hệ thống hiển thị thông báo rõ ràng thay vì trắng màn hình

### 2.2 Sửa legacy entry HTML
- file `index.html` không còn chạy dev shell dạng Vite trực tiếp
- file này bây giờ chuyển hướng an toàn về `/index.php` để tránh mở trực tiếp vào entry sai

### 2.3 Thêm rewrite Apache cho hosting production
- tạo file `.htaccess` để:
  - cho phép truy cập file PHP/static có thật
  - rewrite route SPA về `index.php`
  - chuyển `/index.html` về `index.php`
  - ánh xạ `/assets/*` về `dist/assets/*` khi host dùng Apache

### 2.4 Build production được phục vụ thực tế
- phần build đã được generate trong `dist/`
- asset đã được copy sang `/assets` để browser có thể load bundle ngay cả khi rewrite không hoạt động hoặc host đơn giản

## 3) File chính đã chỉnh sửa

- `index.php`
- `index.html`
- `.htaccess`

## 4) Verifikasi

Các kiểm tra đã thực hiện:

1. Build production:
   - `npm run build`
   - kết quả: thành công, Vite build xong

2. Syntax PHP:
   - `C:\xampp\php\php.exe -l C:\xampp\htdocs\SEB\index.php`
   - kết quả: `No syntax errors detected`

3. Smoke test local server:
   - `C:\xampp\php\php.exe -S localhost:8000`
   - request tới `/` trả về HTML build của app
   - request tới `/index.html` trả về redirect an toàn
   - request tới `/assets/index-DtZWYHnO.js` trả về HTTP 200

## 5) Kết luận

Vấn đề đã được khắc phục ở hướng đúng: không còn để host/Open browser vô `index.html` raw dev shell, mà chuyển sang serve production build an toàn và có fallback rõ ràng.

Nếu deploy lên host thật (seb.io.vn), máy chủ phải phục vụ project theo cấu hình khớp với `index.php` + `dist/` + `assets/` hoặc có `.htaccess` enabled. Hiện trạng code đã không còn rơi vào trường hợp blank page vì entry sai.
