/*
  Migration: TinhTrangDuyet + PhieuMuon status ID
  -------------------------------------------------
  Mục tiêu:
    - Tạo bảng trạng thái duyệt theo ID
    - Dùng cột ID hiện có trong PhieuMuon để lưu trạng thái duyệt
    - Seed 3 trạng thái chuẩn theo dữ liệu thực tế của bạn
    - Map dữ liệu cũ từ TinhTrangMuon (text) sang ID
  - Gỡ CHECK constraint cũ trên TinhTrangMuon nếu còn

  Lưu ý:
  - Script này ưu tiên tên cột:
      MaTinhTrang (ID trạng thái)
      TenTinhTrang (tên hiển thị)
  - Nếu bạn đã đặt tên cột khác, hãy đổi lại cho khớp.
*/

SET NOCOUNT ON;
GO

/* 1) Tạo bảng trạng thái nếu chưa có */
IF OBJECT_ID(N'dbo.TinhTrangDuyet', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TinhTrangDuyet
    (
        MaTinhTrang INT NOT NULL,
        TenTinhTrang NVARCHAR(100) NOT NULL,
        ThuTu INT NOT NULL CONSTRAINT DF_TinhTrangDuyet_ThuTu DEFAULT (0),
        CONSTRAINT PK_TinhTrangDuyet PRIMARY KEY (MaTinhTrang)
    );
END
GO

/* 2) Seed trạng thái chuẩn theo ID cố định (đúng theo dữ liệu bạn gửi) */
IF NOT EXISTS (SELECT 1 FROM dbo.TinhTrangDuyet WHERE MaTinhTrang = 1)
    INSERT INTO dbo.TinhTrangDuyet (MaTinhTrang, TenTinhTrang, ThuTu) VALUES (1, N'Đã duyệt', 1);
IF NOT EXISTS (SELECT 1 FROM dbo.TinhTrangDuyet WHERE MaTinhTrang = 2)
    INSERT INTO dbo.TinhTrangDuyet (MaTinhTrang, TenTinhTrang, ThuTu) VALUES (2, N'Từ chối', 2);
IF NOT EXISTS (SELECT 1 FROM dbo.TinhTrangDuyet WHERE MaTinhTrang = 3)
    INSERT INTO dbo.TinhTrangDuyet (MaTinhTrang, TenTinhTrang, ThuTu) VALUES (3, N'Chờ duyệt', 3);
GO

/* 3) Bảo đảm cột ID trên PhieuMuon là kiểu INT để lưu trạng thái */
IF COL_LENGTH('dbo.PhieuMuon', 'ID') IS NULL
BEGIN
    ALTER TABLE dbo.PhieuMuon ADD ID INT NULL;
END
GO

/* 4) Default cho trạng thái chờ duyệt */
IF OBJECT_ID(N'dbo.DF_PhieuMuon_ID', N'D') IS NOT NULL
    ALTER TABLE dbo.PhieuMuon DROP CONSTRAINT DF_PhieuMuon_ID;
GO

ALTER TABLE dbo.PhieuMuon ADD CONSTRAINT DF_PhieuMuon_ID DEFAULT (3) FOR ID;
GO

/* 5) Foreign key nếu chưa có */
IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_PhieuMuon_TinhTrangDuyet'
      AND parent_object_id = OBJECT_ID('dbo.PhieuMuon')
)
BEGIN
    ALTER TABLE dbo.PhieuMuon
    ADD CONSTRAINT FK_PhieuMuon_TinhTrangDuyet
    FOREIGN KEY (ID) REFERENCES dbo.TinhTrangDuyet(MaTinhTrang);
END
GO

/* 6) Map dữ liệu cũ từ TinhTrangMuon sang ID */
UPDATE pm
SET ID = CASE
    WHEN pm.TinhTrangMuon IS NULL OR LTRIM(RTRIM(pm.TinhTrangMuon)) = '' THEN 3
    WHEN LOWER(LTRIM(RTRIM(pm.TinhTrangMuon))) IN ('pending', 'pending approval', 'dang cho', 'đang chờ', N'chờ duyệt', 'waiting') THEN 3
    WHEN LOWER(LTRIM(RTRIM(pm.TinhTrangMuon))) IN ('approved', 'borrowed', 'dang muon', N'đã duyệt', N'da duyet') THEN 1
    WHEN LOWER(LTRIM(RTRIM(pm.TinhTrangMuon))) IN ('rejected', 'tu choi', N'từ chối', N'bị từ chối') THEN 2
    ELSE 3
END
FROM dbo.PhieuMuon pm;
GO

/* 7) (Khuyến nghị) Gỡ CHECK constraint cũ trên TinhTrangMuon nếu còn */
DECLARE @checkName SYSNAME;
SELECT TOP 1 @checkName = cc.name
FROM sys.check_constraints cc
WHERE cc.parent_object_id = OBJECT_ID('dbo.PhieuMuon')
  AND cc.definition LIKE '%TinhTrangMuon%';

IF @checkName IS NOT NULL
BEGIN
    EXEC(N'ALTER TABLE dbo.PhieuMuon DROP CONSTRAINT [' + @checkName + ']');
END
GO

/* 8) Tùy chọn: nếu muốn giữ TinhTrangMuon làm cột hiển thị text, đồng bộ lại theo ID */
UPDATE pm
SET TinhTrangMuon = tt.TenTinhTrang
FROM dbo.PhieuMuon pm
INNER JOIN dbo.TinhTrangDuyet tt ON tt.MaTinhTrang = pm.ID;
GO

PRINT N'Migration TinhTrangDuyet completed successfully.';
GO
