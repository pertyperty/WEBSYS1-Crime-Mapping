<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();

require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;
$barangayId = $_SESSION['barangay_id'] ?? null;

if (!$userId || !in_array($role, ['admin', 'barangay'], true)) {
    echo json_encode([
        'ok' => true,
        'data' => []
    ]);
    exit;
}

$where = [];
$params = [];

if ($role === 'admin') {
    $where[] = '(n.user_id IS NULL OR n.user_id = :user_id)';
    $params[':user_id'] = $userId;
} elseif ($role === 'barangay' && $barangayId) {
    $where[] = '(n.user_id = :user_id OR n.barangay_id = :barangay_id OR n.user_id IS NULL)';
    $params[':user_id'] = $userId;
    $params[':barangay_id'] = $barangayId;
}

$sql = '
    SELECT
        n.notification_id,
        n.notification_type,
        n.message,
        n.is_read,
        DATE_FORMAT(n.created_at, "%b %d, %Y %h:%i %p") AS created_at,
        i.title AS incident_title,
        b.barangay_name AS barangay
    FROM notifications n
    LEFT JOIN incidents i ON n.incident_id = i.incident_id
    LEFT JOIN barangays b ON n.barangay_id = b.barangay_id
';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY n.created_at DESC LIMIT 8';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

echo json_encode([
    'ok' => true,
    'data' => $notifications
]);
