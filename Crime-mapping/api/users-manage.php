<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
require __DIR__ . '/db.php';
init_secure_session();

function users_have_column(PDO $pdo, string $columnName): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = :column_name");
    $stmt->execute([':column_name' => $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

$role = $_SESSION['role'] ?? null;
if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$allowedRoles = ['registered', 'barangay', 'admin'];
$allowedStatuses = ['active', 'disabled'];
$hasAddressColumn = users_have_column($pdo, 'address');
$addressSelect = $hasAddressColumn ? 'u.address' : 'NULL AS address';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $barangaysStmt = $pdo->prepare('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name ASC');
    $barangaysStmt->execute();
    $barangays = $barangaysStmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("\n        SELECT u.user_id, u.username, u.email, u.contact, {$addressSelect}, u.role, u.barangay_id, b.barangay_name, u.status, u.created_at, COUNT(i.incident_id) AS incident_count\n        FROM users u\n        LEFT JOIN barangays b ON u.barangay_id = b.barangay_id\n        LEFT JOIN incidents i ON u.user_id = i.reported_by\n        GROUP BY u.user_id\n        ORDER BY u.created_at DESC\n    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'users' => $users,
        'barangays' => $barangays
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid request payload.']);
        exit;
    }

    $action = $payload['action'] ?? '';
    $normalizeNullable = static function ($value): ?string {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    };

    if ($action === 'create') {
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $contact = trim((string) ($payload['contact'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $roleName = trim((string) ($payload['role'] ?? 'registered'));
        $status = trim((string) ($payload['status'] ?? 'active'));
        $barangayId = (int) ($payload['barangay_id'] ?? 0);
        $address = $normalizeNullable($payload['address'] ?? '');

        if ($username === '' || $email === '' || $contact === '' || $password === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
            exit;
        }

        if (!in_array($roleName, $allowedRoles, true) || !in_array($status, $allowedStatuses, true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid role or status.']);
            exit;
        }

        if ($roleName !== 'barangay') {
            $barangayId = 0;
        } elseif ($barangayId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Barangay selection is required for barangay accounts.']);
            exit;
        }

        $check = $pdo->prepare('SELECT user_id FROM users WHERE username = :username OR email = :email');
        $check->execute([':username' => $username, ':email' => $email]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Account already exists.']);
            exit;
        }

        $columns = ['username', 'email', 'contact', 'password_hash', 'role', 'status'];
        $placeholders = [':username', ':email', ':contact', ':password_hash', ':role', ':status'];
        $params = [
            ':username' => $username,
            ':email' => $email,
            ':contact' => $contact,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $roleName,
            ':status' => $status,
        ];

        if ($barangayId > 0) {
            $columns[] = 'barangay_id';
            $placeholders[] = ':barangay_id';
            $params[':barangay_id'] = $barangayId;
        }

        if ($hasAddressColumn && $address !== null) {
            $columns[] = 'address';
            $placeholders[] = ':address';
            $params[':address'] = $address;
        }

        $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $insert = $pdo->prepare($sql);
        $insert->execute($params);

        echo json_encode(['ok' => true, 'user_id' => (int) $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'update') {
        $userId = (int) ($payload['user_id'] ?? 0);
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $contact = trim((string) ($payload['contact'] ?? ''));
        $roleName = trim((string) ($payload['role'] ?? 'registered'));
        $status = trim((string) ($payload['status'] ?? 'active'));
        $barangayId = (int) ($payload['barangay_id'] ?? 0);
        $address = $normalizeNullable($payload['address'] ?? '');
        $password = trim((string) ($payload['password'] ?? ''));

        if ($userId === 0 || $username === '' || $email === '' || $contact === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
            exit;
        }

        if (!in_array($roleName, $allowedRoles, true) || !in_array($status, $allowedStatuses, true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid role or status.']);
            exit;
        }

        if ($roleName !== 'barangay') {
            $barangayId = 0;
        } elseif ($barangayId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Barangay selection is required for barangay accounts.']);
            exit;
        }

        $existingStmt = $pdo->prepare('SELECT user_id, role, status FROM users WHERE user_id = :user_id');
        $existingStmt->execute([':user_id' => $userId]);
        $existingUser = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingUser) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'User not found.']);
            exit;
        }

        if ($userId === (int) $_SESSION['user_id'] && ($existingUser['role'] !== $roleName || $existingUser['status'] !== $status)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Cannot change your own role or status.']);
            exit;
        }

        $check = $pdo->prepare('SELECT user_id FROM users WHERE (username = :username OR email = :email) AND user_id <> :user_id');
        $check->execute([':username' => $username, ':email' => $email, ':user_id' => $userId]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Account already exists.']);
            exit;
        }

        $fields = [
            'username = :username',
            'email = :email',
            'contact = :contact',
            'role = :role',
            'status = :status',
            'barangay_id = :barangay_id'
        ];
        $params = [
            ':user_id' => $userId,
            ':username' => $username,
            ':email' => $email,
            ':contact' => $contact,
            ':role' => $roleName,
            ':status' => $status,
            ':barangay_id' => $barangayId > 0 ? $barangayId : null,
        ];

        if ($hasAddressColumn) {
            $fields[] = 'address = :address';
            $params[':address'] = $address;
        }

        if ($password !== '') {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE user_id = :user_id');
        $stmt->execute($params);

        echo json_encode(['ok' => true, 'user_id' => $userId]);
        exit;
    }

    if ($action === 'toggle-status') {
        $userId = (int) ($payload['user_id'] ?? 0);
        $newStatus = trim((string) ($payload['status'] ?? 'active'));

        if ($userId === 0 || !in_array($newStatus, $allowedStatuses, true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid parameters.']);
            exit;
        }

        if ($userId === (int) $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Cannot disable your own account.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE user_id = :user_id');
        $stmt->execute([':status' => $newStatus, ':user_id' => $userId]);

        echo json_encode(['ok' => true, 'status' => $newStatus]);
        exit;
    }

    if ($action === 'delete') {
        $userId = (int) ($payload['user_id'] ?? 0);

        if ($userId === 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid user_id.']);
            exit;
        }

        if ($userId === (int) $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Cannot delete your own account.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET status = "disabled" WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);

        echo json_encode(['ok' => true, 'message' => 'User disabled.']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);