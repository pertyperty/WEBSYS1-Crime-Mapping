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

$name = trim($payload['name'] ?? '');
$email = trim($payload['email'] ?? '');
$contact = trim($payload['contact'] ?? '');
$password = $payload['password'] ?? '';

if ($name === '' || $email === '' || $contact === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
    exit;
}

$username = strtolower(preg_replace('/\s+/', '_', $name));
$username = substr($username, 0, 50);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$check = $pdo->prepare('SELECT user_id FROM users WHERE username = :username OR email = :email');
$check->execute([':username' => $username, ':email' => $email]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Account already exists.']);
    exit;
}

$insert = $pdo->prepare('INSERT INTO users (username, email, contact, password_hash, role) VALUES (:username, :email, :contact, :password_hash, :role)');
$insert->execute([
    ':username' => $username,
    ':email' => $email,
    ':contact' => $contact,
    ':password_hash' => $passwordHash,
    ':role' => 'registered'
]);

$_SESSION['user_id'] = (int) $pdo->lastInsertId();
$_SESSION['username'] = $username;
$_SESSION['role'] = 'registered';
$_SESSION['barangay_id'] = null;

echo json_encode([
    'ok' => true,
    'data' => [
        'redirect' => 'index.php'
    ]
]);
