<?php
require __DIR__ . '/../api/security.php';
init_secure_session();

// Clear session
$_SESSION = [];

// Destroy cookie if set
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Destroy session
session_destroy();

// Redirect to home
header('Location: index.php');
exit;
