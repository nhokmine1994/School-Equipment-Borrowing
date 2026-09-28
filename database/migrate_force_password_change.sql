/* SEB - Force first-login password change. Safe to run more than once. */
IF NOT EXISTS (
    SELECT 1 FROM sys.columns c
    JOIN sys.tables t ON t.object_id = c.object_id
    WHERE t.name = 'TaiKhoan' AND c.name = 'MustChangePassword'
)
BEGIN
    ALTER TABLE dbo.TaiKhoan
        ADD MustChangePassword bit NOT NULL CONSTRAINT DF_TaiKhoan_MustChangePassword DEFAULT (0);
END
GO
