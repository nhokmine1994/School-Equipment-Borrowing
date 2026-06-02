# Hướng Dẫn Sử Dụng Admin Panel - SEB

## 🔐 Đăng Nhập Admin

**URL:** `http://localhost:8080/SEB/Page/admin_login.php`

**Tài khoản mẫu:**
- Username: `admin`
- Password: `admin`

---

## 📋 Các Trang Admin Có Sẵn

### 1. **Quản Lý Thiết Bị** (`admin_thiet_bi.php`)
**Url:** `http://localhost:8080/SEB/Page/admin_thiet_bi.php`

**Tính năng:**
- ✅ Danh sách tất cả thiết bị từ SQL Server
- ✅ Upload ảnh cho từng thiết bị (lưu vào `Images/devices/`)
- ✅ Xóa thiết bị (xác nhận trước khi xóa)

**Cách sử dụng:**
1. Đăng nhập admin tại `admin_login.php`
2. Bạn sẽ nhìn thấy danh sách thiết bị dưới dạng bảng
3. Chọn file ảnh (`JPG, PNG, GIF` etc.) rồi nhấn **"Upload ảnh"** → ảnh sẽ được lưu vào folder `Images/devices/` và cập nhật vào cơ sở dữ liệu
4. Nhấn **"Xóa"** để xóa thiết bị (yêu cầu xác nhận)

---

### 2. **Quản Lý Người Dùng** (`admin_users.php`)
**URL:** `http://localhost:8080/SEB/Page/admin_users.php`

**Tính năng:**
- ✅ Danh sách tất cả người dùng
- ✅ Thêm người dùng mới (gán role: user, teacher, admin)
- ✅ Xóa tài khoản (không thể xóa `admin`)

**Cách sử dụng:**
1. Điền form **"Thêm người dùng mới":**
   - Username: Tên đăng nhập (duy nhất)
   - Password: Mật khẩu (để mặc định)
   - Full Name: Họ và tên (tùy chọn)
   - Email: Email (tùy chọn)
   - Role: Chọn role (user / teacher / admin)
2. Nhấn **"Tạo tài khoản"** → người dùng sẽ được thêm vào bảng `Users`
3. Danh sách người dùng hiển thị phía dưới
4. Nhấn **"Xóa"** để xóa (ngoại trừ `admin`)

---

### 3. **Duyệt Yêu Cầu Mượn** (`admin_borrows.php`)
**URL:** `http://localhost:8080/SEB/Page/admin_borrows.php`

**Tính năng:**
- ✅ Danh sách yêu cầu mượn (trạng thái: pending, approved, returned, rejected)
- ✅ Duyệt mượn (approve) → bắt buộc nhập ngày trả dự kiến
- ✅ Từ chối mươn (reject) → có thể ghi lý do
- ✅ Đánh dấu đã trả (returned)

**Trạng thái yêu cầu:**
- 🟠 **pending** (đang chờ) → có nút **Duyệt** và **Từ chối**
- 🟢 **approved** (đã duyệt) → có nút **Đã trả** để đánh dấu trả thiết bị
- 🔵 **returned** (đã trả) → không có hành động thêm
- 🔴 **rejected** (từ chối) → không có hành động thêm

**Cách sử dụng:**
1. Xem danh sách yêu cầu mượn
2. Nhấn **"Duyệt"** → chọn ngày trả dự kiến → nhấn **"Duyệt"**
3. Nhấn **"Từ chối"** → ghi lý do từ chối → nhấn **"Từ chối"**
4. Khi người dùng trả thiết bị: nhấn **"Đã trả"** → mark as `returned`

---

## 🗄️ Cấu Trúc Cơ Sở Dữ Liệu

### Bảng `Users` (tài khoản)
```sql
CREATE TABLE Users (
    Username VARCHAR(100) PRIMARY KEY,
    PasswordHash VARCHAR(255),
    Password VARCHAR(255),
    Role VARCHAR(50),
    FullName NVARCHAR(255),
    Email NVARCHAR(255)
);
```

### Bảng `Borrows` (yêu cầu mượn)
```sql
CREATE TABLE Borrows (
    BorrowID INT PRIMARY KEY IDENTITY(1,1),
    MaThietBi VARCHAR(100),
    Username VARCHAR(100),
    NgayMuon DATETIME,
    NgayTraDuKien DATETIME,
    NgayTraThucTe DATETIME,
    SoLuong INT,
    TrangThai NVARCHAR(50),  -- pending, approved, returned, rejected
    GhiChu NVARCHAR(MAX)
);
```

---

## 🔒 Bảo Mật & Quyền

- ✅ Tất cả trang admin yêu cầu đăng nhập
- ✅ Kiểm tra `$_SESSION['user']['role'] === 'admin'` trước khi cho phép truy cập
- ✅ Không thể xóa tài khoản `admin`
- ✅ Mật khẩu hiện được lưu plain (fallback) — khuyến nghị thay thế bằng `password_hash()`

---

## 🛠️ Các Tệp Tạo

| File | Mô tả |
|------|--------|
| `Page/admin_login.php` | Trang đăng nhập admin |
| `Page/admin_auth.php` | Middleware kiểm tra quyền session |
| `Page/admin_thiet_bi.php` | Quản lý thiết bị (upload ảnh, xóa) |
| `Page/admin_users.php` | Quản lý người dùng (thêm, xóa, role) |
| `Page/admin_borrows.php` | Duyệt yêu cầu mượn (approve, reject, return) |
| `sql/admin_schema.sql` | Schema bảng `Users` + INSERT mẫu |
| `sql/borrows_schema.sql` | Schema bảng `Borrows` |

---

## 🚀 Cách Chạy Lần Đầu

1. **Tạo bảng trong SQL Server:**
   ```sql
   -- Chạy nội dung từ sql/admin_schema.sql
   CREATE TABLE Users ( ... );
   INSERT INTO Users VALUES ('admin', 'admin', 'admin', N'Administrator', 'admin@example.com');

   -- Chạy nội dung từ sql/borrows_schema.sql
   CREATE TABLE Borrows ( ... );
   ```

2. **Truy cập trang admin:**
   - Login: `http://localhost:8080/SEB/Page/admin_login.php`
   - Username: `admin`, Password: `admin`
   - Quản lý thiết bị → upload ảnh
   - Quản lý người dùng → thêm tài khoản khác
   - Duyệt mượn → duyệt yêu cầu

---

## 📝 Ghi Chú

- Thư mục ảnh thiết bị: `C:\xampp\htdocs\SEB\Images\devices\`
- Tên file ảnh lưu trong cột `HinhAnh` của bảng `ThietBi`
- Session admin có thời hạn theo cấu hình PHP (mặc định ~24 phút)
- Có thể mở rộng: thêm form chỉnh sửa thiết bị, thay đổi mật khẩu, v.v.

---

**Liên hệ:** Admin tại SEB - THCS Lộc An