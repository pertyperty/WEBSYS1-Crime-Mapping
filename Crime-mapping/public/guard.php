<?php
require __DIR__ . '/../api/security.php';
init_secure_session();

function requireRole(array $roles): void
{
    $role = $_SESSION['role'] ?? null;
    if (!$role || !in_array($role, $roles, true)) {
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $next = basename($requestPath);
        $location = 'login.php';
        if ($next !== '' && $next !== 'login.php' && $next !== 'auth-logout.php') {
            $location .= '?next=' . urlencode($next);
        }

        header('Location: ' . $location);
        exit;
    }
}
