/* SEB - Account enable/disable state. Safe to run more than once. */
IF NOT EXISTS (
    SELECT 1 FROM sys.columns c
    JOIN sys.tables t ON t.object_id = c.object_id
    WHERE t.name = 'TaiKhoan' AND c.name = 'TaiKhoanActive'
)
BEGIN
    ALTER TABLE dbo.TaiKhoan
        ADD TaiKhoanActive bit NOT NULL CONSTRAINT DF_TaiKhoanActive DEFAULT (1);
END
GO
