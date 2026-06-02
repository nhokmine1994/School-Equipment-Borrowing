<?php
session_start();
// Simple admin middleware — include at top of admin pages
function require_admin()
{
    if (empty($_SESSION['user']) || empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        header('Location: admin_login.php');
        exit;
    }
}

function login_user($user)
{
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
}

function logout_user()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

?>