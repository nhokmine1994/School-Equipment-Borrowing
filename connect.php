<?php

if (!function_exists('seb_env_value')) {
    function seb_env_value($key, $default = '')
    {
        $value = getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        $value = trim((string) $value);
        return $value === '' ? $default : $value;
    }
}

$serverName = seb_env_value('SEB_DB_SERVER', 'localhost\\SQLEXPRESS');
$databaseName = seb_env_value('SEB_DB_NAME', 'SEB');
$databaseUser = seb_env_value('SEB_DB_USER', '');
$databasePassword = seb_env_value('SEB_DB_PASSWORD', '');
$characterSet = seb_env_value('SEB_DB_CHARACTER_SET', 'UTF-8');
$encrypt = filter_var(seb_env_value('SEB_DB_ENCRYPT', 'false'), FILTER_VALIDATE_BOOLEAN);
$trustServerCertificate = filter_var(seb_env_value('SEB_DB_TRUST_SERVER_CERTIFICATE', 'true'), FILTER_VALIDATE_BOOLEAN);
$integratedSecurity = filter_var(seb_env_value('SEB_DB_INTEGRATED_SECURITY', $databaseUser === '' && $databasePassword === '' ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);

$connectionOptions = [
    'Database' => $databaseName,
    'CharacterSet' => $characterSet,
    'Encrypt' => $encrypt,
    'TrustServerCertificate' => $trustServerCertificate,
];

if (!$integratedSecurity) {
    $connectionOptions['Uid'] = $databaseUser;
    $connectionOptions['PWD'] = $databasePassword;
}

$conn = null;

// Enable debug output for troubleshooting (temporary).
putenv('SEB_DEBUG=true');
ini_set('display_errors', '1');
error_reporting(E_ALL);
$serverCandidates = [];
$configuredServer = seb_env_value('SEB_DB_SERVER', '');
if ($configuredServer !== '') {
    $serverCandidates[] = $configuredServer;
}

$localHost = gethostname();
if (is_string($localHost) && trim($localHost) !== '') {
    $localHost = trim($localHost);
    $serverCandidates[] = $localHost . '\\SQLEXPRESS';
    $serverCandidates[] = $localHost;
}

$serverCandidates[] = 'localhost\\SQLEXPRESS';
$serverCandidates[] = 'localhost';
$serverCandidates[] = '127.0.0.1\\SQLEXPRESS';
$serverCandidates[] = '127.0.0.1';

$serverCandidates = array_values(array_unique(array_filter($serverCandidates, static function ($value) {
    return is_string($value) && trim($value) !== '';
})));

if (!function_exists('sqlsrv_connect')) {
    error_log('SEB: PHP sqlsrv extension is not installed or not enabled.');
} else {
    foreach ($serverCandidates as $candidate) {
        $conn = @sqlsrv_connect($candidate, $connectionOptions);
        if ($conn) {
            $serverName = $candidate;
            break;
        }
    }

    if ($conn) {
        require_once __DIR__ . '/components/seb_db.php';
        seb_ensure_application_schema($conn);
    } else {
        $errors = function_exists('sqlsrv_errors') ? sqlsrv_errors() : null;
        if (is_array($errors) && !empty($errors[0]['message'])) {
            error_log('SEB DB connection failed: ' . $errors[0]['message'] . ' | Tried: ' . implode(', ', $serverCandidates));
        } else {
            error_log('SEB DB connection failed: unable to connect to SQL Server. Tried: ' . implode(', ', $serverCandidates));
        }
    }
}

?>