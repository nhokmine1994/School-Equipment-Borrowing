/* SEB - Additional user profile identity fields. Safe to run more than once. */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.TaiKhoan') AND name = 'SoCCCD')
    ALTER TABLE dbo.TaiKhoan ADD SoCCCD nvarchar(20) NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.TaiKhoan') AND name = 'MaSoGiaoVien')
    ALTER TABLE dbo.TaiKhoan ADD MaSoGiaoVien nvarchar(50) NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.TaiKhoan') AND name = 'MaSoBoGDDT')
    ALTER TABLE dbo.TaiKhoan ADD MaSoBoGDDT nvarchar(50) NULL;
GO
