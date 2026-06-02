<?php
// Simple test script for API registration
// Usage: php tools/test_register.php
$base = getenv('SEB_BASE_URL') ?: 'http://localhost/SEB';
$url = rtrim($base, '/') . '/api/seb_api.php?action=register';

$data = [
    'username' => 'testuser_' . rand(1000,9999),
    'password' => 'P@ssw0rd123',
    'fullName' => 'Test User',
    'email' => 'test+' . rand(1000,9999) . '@example.com',
    'subject' => 'Tin học'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$response = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "POST $url\n";
if ($err) {
    echo "cURL error: $err\n";
    exit(1);
}

echo "HTTP $code\n";
echo "Response:\n" . $response . "\n";

// Manual DB check suggestion
echo "\nAfter running, verify the new user exists in TaiKhoan and that Email column is set.\n";
