-- SQL schema for admin user management (sample)
CREATE TABLE Users (
    Username VARCHAR(100) PRIMARY KEY,
    PasswordHash VARCHAR(255), -- store password_hash()
    Password VARCHAR(255),     -- optional legacy plain password (not recommended)
    Role VARCHAR(50) NOT NULL DEFAULT 'user',
    FullName NVARCHAR(255) NULL,
    Email NVARCHAR(255) NULL
);

-- Example insert (use PHP password_hash to generate hash):
-- INSERT INTO Users (Username, PasswordHash, Role, FullName) VALUES ('admin', '<hash_here>', 'admin', N'Quản trị');

-- Convenience: temporary plain password (fallback auth supports plain Password column).
-- Run this once in your SQL Server to create initial admin account:
INSERT INTO Users (Username, Password, Role, FullName, Email)
VALUES ('admin', 'admin', 'admin', N'Administrator', 'admin@example.com');