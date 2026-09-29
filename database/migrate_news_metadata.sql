/* SEB - News metadata for title, image and imported source URL. */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.ThongBao') AND name = 'TieuDe')
    ALTER TABLE dbo.ThongBao ADD TieuDe nvarchar(250) NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.ThongBao') AND name = 'HinhAnh')
    ALTER TABLE dbo.ThongBao ADD HinhAnh nvarchar(255) NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.ThongBao') AND name = 'NguonLink')
    ALTER TABLE dbo.ThongBao ADD NguonLink nvarchar(1000) NULL;
GO
