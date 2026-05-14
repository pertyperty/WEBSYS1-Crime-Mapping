<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_exception_handler(function ($exception) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $message = is_object($exception) && method_exists($exception, 'getMessage')
        ? $exception->getMessage()
        : 'Internal server error.';
    error_log('update-status exception: ' . $message);
    echo json_encode([
        'ok' => false,
        'error' => 'Server error during status update.',
        'details' => $message
    ]);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/security.php';
init_secure_session();

require __DIR__ . '/db.php';
require __DIR__ . '/sms-helper.php';

ensure_notifications_sms_columns($pdo);


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

if (!isset($payload['incident_id']) || !isset($payload['new_status'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
    exit;
}

$incidentId = (int) $payload['incident_id'];
$newStatus = trim($payload['new_status']);
$remarks = isset($payload['remarks']) ? trim($payload['remarks']) : '';
$makePublic = !empty($payload['make_public']);


$validStatuses = ['pending', 'under_investigation', 'action_taken', 'resolved', 'dismissed'];
if (!in_array($newStatus, $validStatuses, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid status value.']);
    exit;
}


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


$userRole = $_SESSION['role'] ?? null;
$userBarangayId = $_SESSION['barangay_id'] ?? null;

if ($userRole !== 'admin' && ($userRole !== 'barangay' || $userBarangayId != $incident['barangay_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
    exit;
}


$updateStmt = $pdo->prepare('
    UPDATE incidents
    SET status = :status,
        is_public = CASE WHEN :make_public = 1 THEN 1 ELSE is_public END
    WHERE incident_id = :incident_id
');
$updateStmt->execute([
    ':status' => $newStatus,
    ':make_public' => $makePublic ? 1 : 0,
    ':incident_id' => $incidentId
]);


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
    SELECT user_id, contact
    FROM users
    WHERE status = "active" AND (role = "admin" OR (role = "barangay" AND barangay_id = :barangay_id))
');
$recipientStmt->execute([':barangay_id' => (int) $incident['barangay_id']]);
$recipients = $recipientStmt->fetchAll();

$message = sprintf('Incident #%d status updated to %s', $incidentId, str_replace('_', ' ', $newStatus));
$notificationStmt = $pdo->prepare('
    INSERT INTO notifications (user_id, barangay_id, incident_id, notification_type, message, sms_status)
    VALUES (:user_id, :barangay_id, :incident_id, :notification_type, :message, :sms_status)
');

foreach ($recipients as $recipient) {
    $hasSms = is_valid_phone_number((string) ($recipient['contact'] ?? '')) && should_send_sms_for_notification_type('status_update');
    $smsStatus = $hasSms ? 'pending' : null;

    $notificationStmt->execute([
        ':user_id' => (int) $recipient['user_id'],
        ':barangay_id' => (int) $incident['barangay_id'],
        ':incident_id' => $incidentId,
        ':notification_type' => 'status_update',
        ':message' => $message,
        ':sms_status' => $smsStatus
    ]);

    if ($hasSms) {
        enqueue_notification_sms(
            $pdo,
            (int) $pdo->lastInsertId(),
            (int) $recipient['user_id'],
            (string) $recipient['contact'],
            $message
        );
    }
}

echo json_encode([
    'ok' => true,
    'incident_id' => $incidentId,
    'new_status' => $newStatus
]);
