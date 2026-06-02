<?php

function seb_destroy_session()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?: '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function seb_app_root_url_path()
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($scriptName);
    $root = dirname($dir);

    if ($root === '/' || $root === '.' || $root === '') {
        return '';
    }

    return rtrim($root, '/');
}

function seb_redirect_after_logout()
{
    $root = seb_app_root_url_path();

    // Build an absolute URL to avoid cases where the browser treats the
    // Location header as a hostname or produces unexpected redirects.
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    if ($host !== '') {
        $location = $scheme . '://' . $host . ($root === '' ? '' : $root) . '/index.php';
    } else {
        // Fallback to a relative path if host is not available in the environment.
        $location = ($root === '' ? '' : $root) . '/index.php';
    }

    header('Location: ' . $location);
    exit;
}
