# Thư mục SQL — đã vô hiệu hóa file chạy tay

Các script schema cũ đã chuyển sang **`sql/_disabled/*.sql.disabled`** và **không còn dùng** trong quy trình triển khai.

Schema và dữ liệu nghiệp vụ được quản lý qua:

- **`connect.php`** — kết nối SQL Server (database `SEB`)
- **`components/seb_db.php`** — tự tạo bảng thiếu khi ứng dụng chạy (`Borrows`, `KhoCaNhan`, `DangKyPhong`, `BaoTriThongBao`, `Users`, …)
- **`api/seb_api.php`** — mượn thiết bị, kho cá nhân, đăng ký phòng, đăng ký tài khoản

Bạn chỉ cần đảm bảo SQL Server đang chạy. Các SP chính cho kho thiết bị:

- `sp_XemKho` — danh sách thiết bị
- `sp_DangNhap` — đăng nhập
- `sp_ThemKhoCaNhan` / `sp_XemKhoCaNhan` — kho cá nhân
- `sp_MuonThietBi` — mượn (ghi `PhieuMuon`, trừ `Kho`)
