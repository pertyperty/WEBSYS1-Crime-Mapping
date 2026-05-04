<?php
require __DIR__ . '/../api/security.php';
init_secure_session();

function requireRole(array $roles): void
{
    $role = $_SESSION['role'] ?? null;
    if (!$role || !in_array($role, $roles, true)) {
        header('Location: login.php');
        exit;
    }
}
