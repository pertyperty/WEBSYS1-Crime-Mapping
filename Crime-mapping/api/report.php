<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();
require __DIR__ . '/db.php';
require __DIR__ . '/sms-helper.php';

ensure_notifications_sms_columns($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_csrf_token();

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$userRole = $_SESSION['role'] ?? null;
if (!$userId || !in_array($userRole, ['registered', 'barangay', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request payload.']);
    exit;
}

$required = ['crime_type_id', 'title', 'description', 'occurred_date', 'occurred_time', 'severity', 'latitude', 'longitude'];
foreach ($required as $field) {
    if (!isset($payload[$field]) || $payload[$field] === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
        exit;
    }
}

$crimeTypeId = (int) $payload['crime_type_id'];
$barangayName = trim((string) ($payload['barangay'] ?? ''));
if ($barangayName === '' && $userRole === 'barangay') {
    $barangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : null;
    if ($barangayId) {
        $barangayLookup = $pdo->prepare('SELECT barangay_name FROM barangays WHERE barangay_id = :id');
        $barangayLookup->execute([':id' => $barangayId]);
        $row = $barangayLookup->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['barangay_name'])) {
            $barangayName = (string) $row['barangay_name'];
        }
    }
}

if ($barangayName === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing barangay.']);
    exit;
}

$typeStmt = $pdo->prepare('SELECT crime_type_id FROM crime_types WHERE crime_type_id = :id AND is_active = 1');
$typeStmt->execute([':id' => $crimeTypeId]);
if (!$typeStmt->fetch()) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid crime type.']);
    exit;
}

$barangayStmt = $pdo->prepare('SELECT barangay_id FROM barangays WHERE barangay_name = :name');
$barangayStmt->execute([':name' => $barangayName]);
$barangayRow = $barangayStmt->fetch();
if (!$barangayRow) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid barangay.']);
    exit;
}

$occurredAt = $payload['occurred_date'] . ' ' . $payload['occurred_time'] . ':00';

// Determine source and visibility
// Officers/admins can file entries that are immediately visible (stored as source='verified').
// Registered users file reports that start as pending (source='reported').
$requestedSource = isset($payload['source']) ? (string) $payload['source'] : '';
$isOfficerEntry = in_array($userRole, ['admin', 'barangay'], true) && $requestedSource === 'direct';
$source = $isOfficerEntry ? 'verified' : 'reported';

$initialStatus = $isOfficerEntry ? 'under_investigation' : 'pending';
$isPublic = $isOfficerEntry ? 1 : 0;

$insert = $pdo->prepare(
    'INSERT INTO incidents
    (crime_type_id, title, description, barangay_id, latitude, longitude, occurred_at, severity, status, source, is_public, reported_by)
    VALUES
    (:crime_type_id, :title, :description, :barangay_id, :latitude, :longitude, :occurred_at, :severity, :status, :source, :is_public, :reported_by)'
);

$insert->execute([
    ':crime_type_id' => $crimeTypeId,
    ':title' => trim($payload['title']),
    ':description' => trim($payload['description']),
    ':barangay_id' => (int) $barangayRow['barangay_id'],
    ':latitude' => $payload['latitude'],
    ':longitude' => $payload['longitude'],
    ':occurred_at' => $occurredAt,
    ':severity' => $payload['severity'],
    ':status' => $initialStatus,
    ':source' => $source,
    ':is_public' => $isPublic,
    ':reported_by' => $userId,
]);

$incidentId = (int) $pdo->lastInsertId();

$recipientStmt = $pdo->prepare('
    SELECT user_id, role, contact
    FROM users
    WHERE status = "active" AND (role = "admin" OR (role = "barangay" AND barangay_id = :barangay_id))
');
$recipientStmt->execute([':barangay_id' => (int) $barangayRow['barangay_id']]);
$recipients = $recipientStmt->fetchAll();

$notificationStmt = $pdo->prepare('
    INSERT INTO notifications (user_id, barangay_id, incident_id, notification_type, message, sms_status)
    VALUES (:user_id, :barangay_id, :incident_id, :notification_type, :message, :sms_status)
');

$notificationType = $payload['severity'] === 'high' ? 'high_severity' : 'new_report';
$notificationMessage = sprintf(
    'New %s report in %s: %s',
    $notificationType === 'high_severity' ? 'high severity' : 'incident',
    $barangayName,
    trim($payload['title'])
);

foreach ($recipients as $recipient) {
    $hasSms = is_valid_phone_number((string) ($recipient['contact'] ?? '')) && should_send_sms_for_notification_type($notificationType);
    $smsStatus = $hasSms ? 'pending' : null;

    $notificationStmt->execute([
        ':user_id' => (int) $recipient['user_id'],
        ':barangay_id' => (int) $barangayRow['barangay_id'],
        ':incident_id' => $incidentId,
        ':notification_type' => $notificationType,
        ':message' => $notificationMessage,
        ':sms_status' => $smsStatus
    ]);

    if ($hasSms) {
        enqueue_notification_sms(
            $pdo,
            (int) $pdo->lastInsertId(),
            (int) $recipient['user_id'],
            (string) $recipient['contact'],
            $notificationMessage
        );
    }
}

echo json_encode([
    'ok' => true,
    'incident_id' => $incidentId
]);
