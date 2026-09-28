/* SEB - Password reset requests. Safe to run more than once. */
IF OBJECT_ID('dbo.MatKhauResetRequest', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MatKhauResetRequest (
        MaYeuCau       bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TaiKhoan       nvarchar(50) NOT NULL,
        EmailXacThuc   nvarchar(254) NOT NULL,
        SoDienThoai    nvarchar(20) NOT NULL,
        TrangThai      nvarchar(20) NOT NULL CONSTRAINT DF_MKReset_Status DEFAULT N'pending',
        AdminXuLy      nvarchar(50) NULL,
        GhiChuAdmin    nvarchar(500) NULL,
        NgayTao        datetime2 NOT NULL CONSTRAINT DF_MKReset_Created DEFAULT SYSUTCDATETIME(),
        NgayXuLy       datetime2 NULL,
        CONSTRAINT CK_MKReset_Status CHECK (TrangThai IN (N'pending', N'approved', N'rejected', N'email_failed'))
    );

    CREATE INDEX IX_MKReset_Status_Created
        ON dbo.MatKhauResetRequest (TrangThai, NgayTao DESC);
END
GO
