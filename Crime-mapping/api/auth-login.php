<?php
require __DIR__ . '/security.php';
init_secure_session();
require __DIR__ . '/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_csrf_token();

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request payload.']);
    exit;
}

$identity = trim($payload['identity'] ?? '');
$password = $payload['password'] ?? '';

if ($identity === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing credentials.']);
    exit;
}

$stmt = $pdo->prepare('SELECT user_id, username, email, password_hash, role, barangay_id, status FROM users WHERE username = :identity OR email = :identity LIMIT 1');
$stmt->execute([':identity' => $identity]);
$user = $stmt->fetch();

if (!$user || $user['status'] !== 'active') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid credentials.']);
    exit;
}

$hash = $user['password_hash'];
$isValid = password_verify($password, $hash);
if (!$isValid) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid credentials.']);
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['barangay_id'] = $user['barangay_id'];

$redirect = 'index.php';
if ($user['role'] === 'admin') {
    $redirect = 'admin-dashboard.php';
} elseif ($user['role'] === 'barangay') {
    $redirect = 'barangay-dashboard.php';
}

echo json_encode([
    'ok' => true,
    'data' => [
        'role' => $user['role'],
        'redirect' => $redirect
    ]
]);
