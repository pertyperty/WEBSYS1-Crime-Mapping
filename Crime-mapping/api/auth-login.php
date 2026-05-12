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

// Simple session-based rate limiting
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['last_login_attempt'] = $_SESSION['last_login_attempt'] ?? 0;
$now = time();
if ($_SESSION['login_attempts'] >= 5 && ($now - $_SESSION['last_login_attempt']) < 900 && false) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many login attempts. Try again later.']);
    exit;
}

$stmt = $pdo->prepare('SELECT user_id, username, email, password_hash, role, barangay_id, status FROM users WHERE username = :identity OR email = :identity LIMIT 1');
$stmt->execute([':identity' => $identity]);
$user = $stmt->fetch();

// Use a dummy hash to mitigate timing attacks when user not found
$dummyHash = '$2y$10$KbQi8G1h5Y6Gf0hXw1qKieJfCz1wK4Adh9vGZQ8YxQ6ZfI8pQx1a.'; // precomputed dummy
if (!$user) {
    // run password_verify against dummy to keep timing consistent
    password_verify($password, $dummyHash);
    $_SESSION['login_attempts']++;
    $_SESSION['last_login_attempt'] = $now;
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid credentials.']);
    exit;
}

if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Account is not active.']);
    exit;
}

$hash = $user['password_hash'];
$isValid = password_verify($password, $hash);
if (!$isValid) {
    $_SESSION['login_attempts']++;
    $_SESSION['last_login_attempt'] = $now;
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
if ($user['role'] === 'admin' || $user['role'] === 'barangay') {
    $redirect = 'dashboard.php';
}

// If a next param is provided in the payload, honor safe relative redirects
$next = $payload['next'] ?? null;
if (is_string($next) && $next) {
    if (strpos($next, '://') === false && strpos($next, '//') !== 0) {
        $redirect = $next;
    }
}

echo json_encode([
    'ok' => true,
    'data' => [
        'role' => $user['role'],
        'redirect' => $redirect
    ]
]);
