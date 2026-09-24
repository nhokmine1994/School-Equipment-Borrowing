# 🔄 Hướng dẫn Chuyển Dự án SEB giữa 2 máy (Server cũ PHP thuần → Máy mới React)

## 📋 Tổng quan

Bạn đã chuyển sang **máy khác** để làm dự án. Máy cũ vẫn đang chạy **bản PHP thuần (không React)**.
Để chuyển sang bản **v2 có React + đầy đủ chức năng mới**, cần:
1. Backup **Database** từ máy cũ
2. Restore DB vào máy mới
3. Chạy **SQL patch** để nâng cấp schema cho code mới
4. Copy **source code SEB v2** từ máy cũ (hoặc kéo từ repo git)
5. Build React và khởi chạy dự án

---

## 📋 Chuẩn bị trước khi chuyển

### Trên máy CŨ

```
1. Tắt XAMPP/SQL Server đang chạy (Stop, tránh file lock)
2. Backup SQL Server database (chọn 1 trong 2 cách):

   CÁCH A — SQL Server Management Studio (khuyến nghị):
     - Mở SSMS → Database "SEB" → Task → Back Up...
     - Destination: D:\SEB_BAK.bak
     - Components: BACKUP DATABASE SEB TO DISK='D:\SEB_BAK.bak'

   CÁCH B — T-SQL Command (sqlcmd):
     sqlcmd -S .\SQLEXPRESS -E -Q "BACKUP DATABASE SEB TO DISK='D:\SEB_BAK.bak'"

3. Copy/zip thư mục dự án (không kèm node_modules):
     a. Chạy script đóng gói: .\tools\package_project.ps1   (tự tạo zip)
     HOẶC tự zip thủ công (bỏ qua node_modules, dist_bak, .git):
       - hugged	ZIP: XAMPP\htdocs\SEB\  → SEB_sourcecode.zip
4. Chuyển 2 file zip/bak sang máy mới (USB / cloud / LAN)
```

---

## 🟨 TRÊN MÁY MỚI

### Bước 1 — Cài XAMPP mới
- Cài XAMPP với **PHP 8.2+**, cài SQL Server Express + **ODBC Driver 17 for SQL Server**
- Kích hoạt Driver sqlsrv trong php.ini (`extension=sqlsrv_82_ts_x64.dll` và `pdo_sqlsrv`)
- Cấu hình XAMPP trỏ vistas `php.ini` có `extension_dir` đúng

### Bước 2 — Restore Database
```
SSMS → Connect SQL Server (LocalDB/SQLEXPRESS) → Right-click "Databases" → Restore File(s)
→ Chọn .bak file → tick "Overwrite the existing database" nếu có DB trùng tên
→ Rename thành "SEB" nếu cần; — Ensure có quyền sẵn (admin login từ connect.php env vars)
```

### Bước 3 — Copy Source code SEB v2
```
1. Copy SEB_sourcecode.zip vào C:\xampp\htdocs\ (hoặc D:\...).
2. Giải nén.
3. Kết quả: D:\something\xampp\htdocs\SEB\
```

### Bước 4 — Chạy SQL PATCH (nâng cấp schema cho code v2)
```
SSMS → mở file htdocs\SEB\database\db_patch_v2.sql → F5 (Execute)
— patch hỗ trợ idempotent, chạy N lần không lỗi.
```

### Bước 5 — Build React bundle
```
cd <SEB-project>\  (thư mục gốc dự án)
npm install          (lần đầu tiên; copy node_modules máy cũ sẽ ok — nhưng nhẹ hơn lần build mới)
npm run build        → sinh dist/ (assets React)
```

### Bước 6 — Khởi chạy Project
```
Cách A — PHP Built-in (nhanh, dev):
  php -S localhost:8080 -t . index.php
  → mở http://localhost:8080

Cách B — XAMPP Apache (khuyến nghị khi làm lâu dài):
  Start XAMPP → mở http://localhost/SEB/
  Nghiêm .htaccess auto chuyển index.php → dist/index.html (React SPA)
```

### Bước 6.1 — Đảm bảo biến môi trường nếu cần
```
connect.php dùng các biến env (SEB_DB_SERVER, SEB_DB_NAME,...)
Mỗi lần chạy cần set:
  setx SEB_DB_SERVER "localhost\SQLEXPRESS" (hoặc tên server SQL server thực tế)
  setx SEB_DB_NAME "SEB"
  ...
hoặc shell .env (nếu vậy bỏ qua). Code sẽ tự loop thử nhiều server names sẵn.
```

### Bước 6.5 — Kiểm tra sau chuyển (Checklist)
```
□ Vào http://localhost:8080/ — trang chủ React hiện đúng Header + banner
✓ Kiểm tra các nhóm: React (SPA), PHP API (api/seb_api.php), PHP pages (/Page/*)
✓ Login admin   →   Password admin — thử từng trang admin
✓ Kho thiết bị load đủ ~82 items (kiểm tra IDActive đã được patch)
✓ Đăng ký phòng học hoạt động, duyệt phòng admin OK
✓ Mượn thiết bị → duyệt → số量的 kho giảm/quay lại đúng
```

### Bước 7 — Dọn rác sau chuyển
```
Nếu máy mới không cần XAMPP cũ + source code đã copy đủ:
  • Xóa http://localhost:8080/ nếu không dùng server cũ
  • Clean folder tmp / cache của XAMPP
  • Cập nhật 404 hardcoded 'Images/...' nếu đã đổi đường dẫn
```

## 🚨 Common Issues khi chuyển máy

| Vấn đề | Giải pháp |
|---|---|
| PHP Notice "call to undefined sqlsrv_connect" | Bật extension=sqlsrv_82_ts_x64.dll + pdo_sqlsrv trong php.ini rồi restart XAMPP |
| View React trắng trang | Chạy `npm run build` lại từ đầu trong thư mục SEB |
| Warning lỗi "Undefined array key Total" | Chạy đủ SQL patch db_patch_v2.sql |
| Mượn thiết bị báo "Không đủ số lượng" | Trừ kho cộng tổng chưa từng chạy — chạy SQL patch + reset `SoLuong` trong bảng Kho giá trị chuẩn |
| Restore .bak lỗi file trong use | Stop SQL Server trước, rồi restore over Daemon — hoặc dùng `sqlcmd -E -Q "RESTORE DATABASE SEB FROM DISK=... WITH REPLACE"` |
| Thiiếu các file JS bundle rỗng | npm như `npm install --force` nếu gặp lỗi node_modules corrupt |

---

## 📌 TÓM TẮT NGẮN GỌN (Big Picture)

```
Old machine                     New machine
──────────────                  ─────────────────────
XAMPP PHP-Thuần code            XAMPP + Node (React build) + SQL Server
    │
    ├─ SQL Server backup .bak   → Restore vào máy mới
    ├─ Source folder zip        → Copy vào htdocs của máy mới
    │
    └── Chạy db_patch_v2.sql    → Nâng schema cho code v2
        │
        └── npm install + npm run build

Khởi động PHP built-in fullstack → dùng http://localhost:8080/
Hoặc → http://localhost/SEB/ (XAMPP web server)
```

---

Biên soạn 16/09/2026 — giúp bạn chuyển server PHP thuần lên SEB v2 (React + Sqlsrv).