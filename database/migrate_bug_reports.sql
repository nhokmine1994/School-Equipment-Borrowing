IF OBJECT_ID('dbo.BaoCaoLoi', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.BaoCaoLoi (
        MaBaoCao bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TaiKhoan nvarchar(50) NULL,
        UrlTrang nvarchar(1000) NULL,
        TieuDe nvarchar(200) NOT NULL,
        NoiDung nvarchar(MAX) NOT NULL,
        MucDo nvarchar(20) NOT NULL DEFAULT N'normal',
        TrangThai nvarchar(20) NOT NULL DEFAULT N'mới',
        NgayTao datetime2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
    CREATE INDEX IX_BaoCaoLoi_Status_Created ON dbo.BaoCaoLoi (TrangThai, NgayTao DESC);
END
GO
