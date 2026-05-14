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
$address = trim($payload['address'] ?? '');
$next = $payload['next'] ?? null;

$check = $pdo->prepare('SELECT user_id FROM users WHERE username = :username OR email = :email');
$check->execute([':username' => $username, ':email' => $email]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Account already exists.']);
    exit;
}


$columns = ['username', 'email', 'contact', 'password_hash', 'role'];
$placeholders = [':username', ':email', ':contact', ':password_hash', ':role'];
$params = [
    ':username' => $username,
    ':email' => $email,
    ':contact' => $contact,
    ':password_hash' => $passwordHash,
    ':role' => 'registered'
];

if ($address !== '') {
    try {
        $colStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'address'");
        $colStmt->execute();
        $colRow = $colStmt->fetch();
        if ($colRow && (int)$colRow['cnt'] > 0) {
            $columns[] = 'address';
            $placeholders[] = ':address';
            $params[':address'] = $address;
        }
    } catch (Exception $e) {
        
    }
}

$sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
$insert = $pdo->prepare($sql);
$insert->execute($params);

$_SESSION['user_id'] = (int) $pdo->lastInsertId();
$_SESSION['username'] = $username;
$_SESSION['role'] = 'registered';
$_SESSION['barangay_id'] = null;


$redirect = 'index.php';
if (is_string($next) && $next) {
    if (strpos($next, '://') === false && strpos($next, '//') !== 0) {
        $redirect = $next;
    }
}

echo json_encode([
    'ok' => true,
    'data' => [
        'redirect' => $redirect
    ]
]);
