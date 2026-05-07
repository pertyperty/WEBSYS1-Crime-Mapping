<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();
require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

function allowed_crime_categories(): array
{
    return [
        'violent',
        'property',
        'white_collar',
        'drug',
        'cybercrime',
        'public_order',
        'traffic',
        'status_offense',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('
        SELECT crime_type_id, category, type_name, is_active, created_at
        FROM crime_types
        ORDER BY is_active DESC, category ASC, type_name ASC
    ');
    echo json_encode([
        'ok' => true,
        'data' => [
            'categories' => allowed_crime_categories(),
            'types' => $stmt->fetchAll(),
        ],
    ]);
    exit;
}

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

$action = trim((string) ($payload['action'] ?? ''));
if ($action === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing action.']);
    exit;
}

if ($action === 'create') {
    $category = trim((string) ($payload['category'] ?? ''));
    $typeName = trim((string) ($payload['type_name'] ?? ''));
    $isActive = isset($payload['is_active']) ? (int) (bool) $payload['is_active'] : 1;

    if ($category === '' || $typeName === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
        exit;
    }

    if (!in_array($category, allowed_crime_categories(), true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid category.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO crime_types (category, type_name, is_active)
            VALUES (:category, :type_name, :is_active)
        ');
        $stmt->execute([
            ':category' => $category,
            ':type_name' => $typeName,
            ':is_active' => $isActive,
        ]);
    } catch (Throwable $error) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Crime type already exists or cannot be created.']);
        exit;
    }

    echo json_encode(['ok' => true, 'crime_type_id' => (int) $pdo->lastInsertId()]);
    exit;
}

if ($action === 'update') {
    $id = (int) ($payload['crime_type_id'] ?? 0);
    $category = trim((string) ($payload['category'] ?? ''));
    $typeName = trim((string) ($payload['type_name'] ?? ''));

    if ($id <= 0 || $category === '' || $typeName === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
        exit;
    }

    if (!in_array($category, allowed_crime_categories(), true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid category.']);
        exit;
    }

    $exists = $pdo->prepare('SELECT crime_type_id FROM crime_types WHERE crime_type_id = :id');
    $exists->execute([':id' => $id]);
    if (!$exists->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Crime type not found.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('
            UPDATE crime_types
            SET category = :category, type_name = :type_name
            WHERE crime_type_id = :id
        ');
        $stmt->execute([
            ':category' => $category,
            ':type_name' => $typeName,
            ':id' => $id,
        ]);
    } catch (Throwable $error) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Update failed (possible duplicate).']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'toggle-active') {
    $id = (int) ($payload['crime_type_id'] ?? 0);
    $isActive = isset($payload['is_active']) ? (int) (bool) $payload['is_active'] : null;

    if ($id <= 0 || $isActive === null) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE crime_types SET is_active = :is_active WHERE crime_type_id = :id');
    $stmt->execute([
        ':is_active' => $isActive,
        ':id' => $id,
    ]);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);

