/* ============================================================
   SEB DB PATCH v2 — nâng cấp schema server CŨ -> schema MỚI
   Chạy trên: SQL Server kiểu localdb / SQLEXPRESS / full
   Chạy NHIỀU LẦN đều an toàn (IF EXISTS guards)
   Dành cho: bước chuyển server PHP thuần -> SEB v2 (React)
   ============================================================ */


/* ─── 1. Bảng Kho: thêm cột IDActive (1=thanh lý/ẩn, 2=đang dùng) ─── */
IF NOT EXISTS (SELECT 1 FROM sys.columns c JOIN sys.tables t ON c.object_id=t.object_id
               WHERE t.name='Kho' AND c.name='IDActive')
    ALTER TABLE Kho ADD IDActive int NOT NULL DEFAULT 2;
GO

/* ─── 2. Bảng Active (lookup trạng thái hoạt động) ─── */
IF OBJECT_ID('dbo.Active') IS NULL
BEGIN
    CREATE TABLE dbo.Active (
        IDActive          int NOT NULL PRIMARY KEY,
        TinhTrangHoatDong nvarchar(100) NOT NULL
    );
    INSERT INTO dbo.Active (IDActive, TinhTrangHoatDong)
    VALUES (1, N'Đã thanh lý / Ngưng sử dụng'), (2, N'Đang sử dụng');
END
GO

/* ─── 3. Bảng BoMon (tra cứu bộ môn — dùng chung TK GV / thiết bị) ─── */
IF OBJECT_ID('dbo.BoMon') IS NULL
BEGIN
    CREATE TABLE dbo.BoMon (
        MaBoMon int IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TenBoMon nvarchar(50) NOT NULL UNIQUE,
        GhiChu nvarchar(200) NULL
    );
    INSERT INTO dbo.BoMon (TenBoMon) VALUES
        (N'Toán'), (N'Ngữ văn'), (N'Tiếng Anh'), (N'Vật lý'),
        (N'Hóa học'), (N'Sinh học'), (N'Lịch sử'), (N'Địa lí'),
        (N'GDCD'), (N'Công nghệ'), (N'Tin học'), (N'Thể dục'),
        (N'Âm nhạc'), (N'Mỹ thuật'), (N'Hoạt động trải nghiệm'),
        (N'Khác / Dùng chung');
END
GO

/* ─── 4. Bảng ThongBaoAdmin (nếu server cũ chưa có) ─── */
IF OBJECT_ID('dbo.ThongBaoAdmin') IS NULL
BEGIN
    CREATE TABLE dbo.ThongBaoAdmin (
        MaThongBao   int IDENTITY(1,1) NOT NULL PRIMARY KEY,
        LoaiThongBao nvarchar(50) NOT NULL,
        TieuDe       nvarchar(200) NOT NULL,
        LoiNhan      nvarchar(500) NOT NULL,
        Link         nvarchar(200) NULL,
        ThoiGianTao  datetime NOT NULL CONSTRAINT DF_ThongBaoAdmin_Time DEFAULT (GETDATE()),
        TrangThai    bit NOT NULL CONSTRAINT DF_ThongBaoAdmin_St DEFAULT (0)
    );
END
GO

/* ─── 5. Bảng DangKyPhong (nếu server cũ chưa có) ─── */
IF OBJECT_ID('dbo.DangKyPhong') IS NULL
BEGIN
    CREATE TABLE dbo.DangKyPhong (
        MaDangKy       int IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MaDatCho       nvarchar(50) NOT NULL,
        Username       nvarchar(50) NOT NULL,
        LoaiPhong      nvarchar(50) NOT NULL,
        LoaiPhongLabel nvarchar(100) NULL,
        SoPhong        nvarchar(20) NULL,
        SoPhongLabel   nvarchar(50) NULL,
        TenHienThi     nvarchar(200) NULL,
        MucDich        nvarchar(500) NULL,
        DuLieuCa       nvarchar(MAX) NULL,
        TrangThai      nvarchar(50) NULL,
        NgayTao        datetime NOT NULL CONSTRAINT DF_DangKyPhong_Time DEFAULT (GETDATE())
    );
END
GO

/* ─── 6. Bảng BaoTriThongBao (nếu server cũ chưa có) ─── */
IF OBJECT_ID('dbo.BaoTriThongBao') IS NULL
BEGIN
    CREATE TABLE dbo.BaoTriThongBao (
        MaBaoTri     int IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TieuDe       nvarchar(200) NOT NULL,
        NoiDung      nvarchar(MAX) NULL,
        NgayTao      datetime NULL CONSTRAINT DF_BaoTriTime DEFAULT (GETDATE()),
        NgayCapNhat  datetime NULL,
        TrangThai    int NOT NULL DEFAULT 1,
        ThuTuHienThi int NOT NULL DEFAULT 1
    );
END
GO

/* ─── 7. Bảng ThongBao (tin tức trang chủ) + CHECK constraint ─── */
IF OBJECT_ID('dbo.ThongBao') IS NULL
BEGIN
    CREATE TABLE dbo.ThongBao (
        MaThongBao   int IDENTITY(1,1) NOT NULL PRIMARY KEY,
        LoaiThongBao nvarchar(50) NOT NULL,
        NoiDung      nvarchar(MAX) NULL,
        NgayDang     datetime NULL DEFAULT (GETDATE()),
        NguoiDang    nvarchar(50) NULL,
        TrangThai    nvarchar(20) NOT NULL DEFAULT N'Hiển thị',
        CONSTRAINT CK_LoaiThongBao CHECK (LoaiThongBao IN (N'Bổ sung', N'Sửa chữa', N'Cập nhật', N'Bảo trì'))
    );
END
GO

/* ─── 8. TaiKhoan: đảm bảo BoMon tồn tại (nếu schema cũ thiếu) ─── */
IF NOT EXISTS (SELECT 1 FROM sys.columns c JOIN sys.tables t ON c.object_id=t.object_id
               WHERE t.name='TaiKhoan' AND c.name='BoMon')
    ALTER TABLE TaiKhoan ADD BoMon nvarchar(50) NOT NULL CONSTRAINT DF_TaiKhoan_BoMon DEFAULT N'Chung';
GO

PRINT N'✓ Đã nâng cấp schema dbo. thành công (SEB v2).';
GO