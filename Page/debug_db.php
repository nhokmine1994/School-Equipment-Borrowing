<?php
require_once __DIR__ . '/../connect.php';
header('Content-Type: text/plain; charset=utf-8');

echo "SEB debug endpoint\n";
echo "php version: " . PHP_VERSION . "\n";
echo "sqlsrv extension: " . (function_exists('sqlsrv_connect') ? 'available' : 'missing') . "\n";

// Try to establish a direct connection using connect.php candidates
$server = getenv('SEB_DB_SERVER') ?: 'localhost\\SQLEXPRESS';
$db = getenv('SEB_DB_NAME') ?: 'SEB';
$user = getenv('SEB_DB_USER') ?: '';
$pwd = getenv('SEB_DB_PASSWORD') ?: '';
$options = [ 'Database' => $db, 'CharacterSet' => 'UTF-8' ];
if ($user !== '') { $options['Uid'] = $user; $options['PWD'] = $pwd; }

if (!function_exists('sqlsrv_connect')) {
    echo "sqlsrv_connect not available, cannot test connection.\n";
    exit;
}

$try = @sqlsrv_connect($server, $options);
if ($try) {
    echo "Connected to SQL Server: " . $server . "\n";
} else {
    $errs = sqlsrv_errors();
    if ($errs && isset($errs[0]['message'])) {
        echo "Connection failed: " . $errs[0]['message'] . "\n";
    } else {
        echo "Connection failed: unknown error\n";
    }
}

// Show any recent debug log content
$log = __DIR__ . '/../logs/login_debug.txt';
if (is_file($log)) {
    echo "\nRecent login debug log:\n";
    echo file_get_contents($log);
} else {
    echo "\nNo login debug log found.\n";
}
