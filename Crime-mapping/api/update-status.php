<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();

require __DIR__ . '/db.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
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

$required = ['incident_id', 'new_status', 'remarks'];
foreach ($required as $field) {
    if (!isset($payload[$field]) || $payload[$field] === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
        exit;
    }
}

$incidentId = (int) $payload['incident_id'];
$newStatus = trim($payload['new_status']);
$remarks = trim($payload['remarks']);

// Validate status value
$validStatuses = ['pending', 'under_investigation', 'action_taken', 'resolved', 'dismissed'];
if (!in_array($newStatus, $validStatuses, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid status value.']);
    exit;
}

// Get incident details
$incidentStmt = $pdo->prepare('
    SELECT i.incident_id, i.barangay_id, i.status
    FROM incidents i
    WHERE i.incident_id = :incident_id
');
$incidentStmt->execute([':incident_id' => $incidentId]);
$incident = $incidentStmt->fetch();

if (!$incident) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Incident not found.']);
    exit;
}

// Check authorization: admin or barangay user of that barangay
$userRole = $_SESSION['role'] ?? null;
$userBarangayId = $_SESSION['barangay_id'] ?? null;

if ($userRole !== 'admin' && ($userRole !== 'barangay' || $userBarangayId != $incident['barangay_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
    exit;
}

// Update incident status
$updateStmt = $pdo->prepare('
    UPDATE incidents
    SET status = :status
    WHERE incident_id = :incident_id
');
$updateStmt->execute([
    ':status' => $newStatus,
    ':incident_id' => $incidentId
]);

// Insert log entry
$logStmt = $pdo->prepare('
    INSERT INTO incident_logs (incident_id, action, remarks, created_by)
    VALUES (:incident_id, :action, :remarks, :created_by)
');
$logStmt->execute([
    ':incident_id' => $incidentId,
    ':action' => 'Status updated to ' . $newStatus,
    ':remarks' => $remarks,
    ':created_by' => $_SESSION['user_id']
]);

$recipientStmt = $pdo->prepare('
    SELECT user_id
    FROM users
    WHERE status = "active" AND (role = "admin" OR (role = "barangay" AND barangay_id = :barangay_id))
');
$recipientStmt->execute([':barangay_id' => (int) $incident['barangay_id']]);
$recipients = $recipientStmt->fetchAll();

$message = sprintf('Incident #%d status updated to %s', $incidentId, str_replace('_', ' ', $newStatus));
$notificationStmt = $pdo->prepare('
    INSERT INTO notifications (user_id, barangay_id, incident_id, notification_type, message)
    VALUES (:user_id, :barangay_id, :incident_id, :notification_type, :message)
');

foreach ($recipients as $recipient) {
    $notificationStmt->execute([
        ':user_id' => (int) $recipient['user_id'],
        ':barangay_id' => (int) $incident['barangay_id'],
        ':incident_id' => $incidentId,
        ':notification_type' => 'status_update',
        ':message' => $message
    ]);
}

echo json_encode([
    'ok' => true,
    'incident_id' => $incidentId,
    'new_status' => $newStatus
]);
