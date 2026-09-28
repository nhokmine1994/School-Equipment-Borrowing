# SEB SMTP Setup

Password-reset approval sends a temporary password through PHPMailer. SMTP credentials are never stored in source code.

Configure these environment variables for Apache/PHP:

```text
SEB_SMTP_HOST=smtp.example.edu
SEB_SMTP_PORT=587
SEB_SMTP_USERNAME=seb@example.edu
SEB_SMTP_PASSWORD=change-this-in-server-environment
SEB_SMTP_ENCRYPTION=tls
SEB_MAIL_FROM=seb@example.edu
SEB_MAIL_FROM_NAME=SEB School Equipment Borrowing
```

For XAMPP, the variables can be added with `SetEnv` in Apache configuration or configured in the Windows service environment. Restart Apache after changing them.

If SMTP is not configured, admin approval deliberately stops before changing the account password and displays a configuration error. This prevents a password from being changed without being delivered to the user.
