<?php

function seb_send_password_reset_email(string $to, string $displayName, string $temporaryPassword): array
{
    $required = [
        'SEB_SMTP_HOST' => getenv('SEB_SMTP_HOST'),
        'SEB_SMTP_USERNAME' => getenv('SEB_SMTP_USERNAME'),
        'SEB_SMTP_PASSWORD' => getenv('SEB_SMTP_PASSWORD'),
        'SEB_MAIL_FROM' => getenv('SEB_MAIL_FROM'),
    ];
    foreach ($required as $key => $value) {
        if (!is_string($value) || trim($value) === '') {
            return ['ok' => false, 'error' => 'Chưa cấu hình email máy chủ (' . $key . ').'];
        }
    }

    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = trim((string) $required['SEB_SMTP_HOST']);
        $mail->Port = (int) (getenv('SEB_SMTP_PORT') ?: 587);
        $mail->SMTPAuth = true;
        $mail->Username = trim((string) $required['SEB_SMTP_USERNAME']);
        $mail->Password = (string) $required['SEB_SMTP_PASSWORD'];
        $encryption = strtolower(trim((string) (getenv('SEB_SMTP_ENCRYPTION') ?: 'tls')));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(trim((string) $required['SEB_MAIL_FROM']), (string) (getenv('SEB_MAIL_FROM_NAME') ?: 'SEB'));
        $mail->addAddress($to, $displayName !== '' ? $displayName : $to);
        $mail->isHTML(true);
        $mail->Subject = 'Mật khẩu tạm thời cho tài khoản SEB';
        $safeName = htmlspecialchars($displayName !== '' ? $displayName : 'bạn', ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');
        $mail->Body = '<p>Xin chào ' . $safeName . ',</p>'
            . '<p>Yêu cầu quên mật khẩu của bạn đã được quản trị viên xác minh.</p>'
            . '<p>Mật khẩu tạm thời: <strong>' . $safePassword . '</strong></p>'
            . '<p>Vui lòng đăng nhập và liên hệ quản trị viên nếu cần đổi mật khẩu.</p>';
        $mail->AltBody = "Mật khẩu tạm thời SEB: {$temporaryPassword}";
        $mail->send();
        return ['ok' => true];
    } catch (Throwable $error) {
        error_log('SEB password reset email failed: ' . $error->getMessage());
        return ['ok' => false, 'error' => 'Không gửi được email. Hãy kiểm tra cấu hình SMTP.'];
    }
}
