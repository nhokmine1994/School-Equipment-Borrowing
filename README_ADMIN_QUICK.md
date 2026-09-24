# README Admin Quick - SEB

## 1) URL quan trọng
- Dang nhap admin: `http://localhost:8080/SEB/Page/admin_login.php`
- Panel tong: `http://localhost:8080/SEB/Page/admin_panel.php`
- Quan ly thiet bi: `http://localhost:8080/SEB/Page/admin_thiet_bi.php`
- Quan ly nguoi dung: `http://localhost:8080/SEB/Page/admin_users.php`
- Duyet muon/tra: `http://localhost:8080/SEB/Page/admin_borrows.php`

## 1.1) Cau hinh de chay tren server moi
- `connect.php` khong con phu thuoc may cu, se doc bien moi truong neu co.
- Bat extension PHP `sqlsrv` / `pdo_sqlsrv` tren may server.
- Dat cac bien moi truong:
	- `SEB_DB_SERVER` = ten may SQL Server hoac IP, vd `192.168.1.10\\SQLEXPRESS`
	- `SEB_DB_NAME` = `SEB`
	- `SEB_DB_USER` va `SEB_DB_PASSWORD` neu dung SQL Login
	- `SEB_DB_INTEGRATED_SECURITY=true` neu dung Windows Auth
	- `SEB_DB_ENCRYPT=false` neu server chua cau hinh TLS
	- `SEB_DB_TRUST_SERVER_CERTIFICATE=true` neu dung cert tu ky
- Neu chua thay doi gi, app se thu ket noi `localhost\\SQLEXPRESS` va database `SEB`.
- Neu URL khac `localhost:8080`, cap nhat lai link truy cap cho dung cong/host cua server.

## 2) Session dang dung
He thong hien co 2 kieu session:
- Kieu A (Tai-khoan.php): `$_SESSION['TaiKhoan']`, `$_SESSION['LoaiTaiKhoan']`
- Kieu B (admin_login.php): `$_SESSION['user']['username']`, `$_SESSION['user']['role']`

Khuyen nghi: chuan hoa 1 kieu duy nhat de tranh loi truy cap cheo trang admin.

## 3) Van hanh can ban
- Tao/sua/xoa thiet bi trong `admin_panel.php` hoac `admin_thiet_bi.php`
- Tao/xoa tai khoan trong `admin_users.php`
- Duyet/tu choi/danh dau da tra trong `admin_borrows.php`

## 4) Ranh gioi phan quyen SQL
Ban da phan quyen tren SQL. De an toan, tiep tuc giu nguyen nguyen tac:
- Moi thao tac ghi du lieu di qua Stored Procedure
- Tai khoan ket noi DB cua web chi co quyen EXEC cac SP can thiet
- Han che quyen INSERT/UPDATE/DELETE truc tiep neu co the

## 5) Chuan bi de them AI gui mail sau nay (khong sua code goc nhieu)
Khuyen nghi mo hinh 3 lop:
1. Lop admin web doi trang thai (approve/reject/returned)
2. SQL ghi su kien vao bang hang doi (vd: `NotificationQueue`)
3. Worker AI/Email doc queue va gui thong bao

Bang queue toi thieu de them sau:
- QueueID (identity)
- EventType (BorrowApproved, BorrowReminder, BorrowRejected, BorrowReturned)
- PayloadJson (du lieu email, ten nguoi dung, thoi han)
- Status (Pending, Processing, Sent, Failed)
- RetryCount
- CreatedAt

Voi mo hinh nay, ban co the thay doi nha cung cap AI/mail ma khong can sua phan giao dien admin.

## 6) Danh sach uu tien hardening (ngan gon)
1. Hop nhat session model (muc uu tien cao)
2. Them CSRF token cho tat ca form POST admin
3. Kiem tra MIME + extension + doi ten file upload anh
4. Chuyen action UPDATE/DELETE sang Stored Procedure
5. Bo luu password plain, dung PasswordHash
