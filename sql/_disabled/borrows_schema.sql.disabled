-- SQL schema for borrowing requests (sample)
CREATE TABLE Borrows (
    BorrowID INT PRIMARY KEY IDENTITY(1,1),
    MaThietBi VARCHAR(100) NOT NULL,
    Username VARCHAR(100) NOT NULL,
    NgayMuon DATETIME DEFAULT GETDATE(),
    NgayTraDuKien DATETIME NULL,
    NgayTraThucTe DATETIME NULL,
    SoLuong INT DEFAULT 1,
    TrangThai NVARCHAR(50) DEFAULT 'pending', -- pending, approved, returned, rejected
    GhiChu NVARCHAR(MAX) NULL,
    FOREIGN KEY (MaThietBi) REFERENCES ThietBi(MaThietBi),
    FOREIGN KEY (Username) REFERENCES Users(Username)
);