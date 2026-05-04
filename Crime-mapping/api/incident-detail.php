<?php
header('Content-Type: application/json');
session_start();

require __DIR__ . '/db.php';

if (!isset($_GET['incident_id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing incident_id.']);
    exit;
}

$incidentId = (int) $_GET['incident_id'];
$viewerRole = $_SESSION['role'] ?? null;
$viewerUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$viewerBarangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : null;

$visibilityClause = 'i.is_public = 1';
$visibilityParams = [];

if ($viewerRole === 'admin') {
    $visibilityClause = '1 = 1';
} elseif ($viewerRole === 'barangay' && $viewerBarangayId) {
    $visibilityClause = 'i.barangay_id = :viewer_barangay_id';
    $visibilityParams[':viewer_barangay_id'] = $viewerBarangayId;
} elseif ($viewerRole === 'registered' && $viewerUserId) {
    $visibilityClause = '(i.is_public = 1 OR i.reported_by = :viewer_user_id)';
    $visibilityParams[':viewer_user_id'] = $viewerUserId;
}

$stmt = $pdo->prepare('
    SELECT
        i.incident_id AS id,
        ct.category AS type,
        ct.type_name,
        i.title,
        i.description,
        b.barangay_name AS barangay,
        i.status,
        i.severity,
        i.source,
        i.is_public,
        DATE_FORMAT(i.occurred_at, "%Y-%m-%d %H:%i") AS occurred_at,
        i.latitude AS lat,
        i.longitude AS lng,
        u.username AS reported_by,
        i.created_at
    FROM incidents i
    JOIN barangays b ON i.barangay_id = b.barangay_id
    JOIN crime_types ct ON i.crime_type_id = ct.crime_type_id
    LEFT JOIN users u ON i.reported_by = u.user_id
    WHERE i.incident_id = :id AND ' . $visibilityClause . '
');
$stmt->execute(array_merge([':id' => $incidentId], $visibilityParams));
$incident = $stmt->fetch();

if (!$incident) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Incident not found.']);
    exit;
}

$imagesStmt = $pdo->prepare('
    SELECT image_id, file_path, uploaded_at
    FROM incident_images
    WHERE incident_id = :incident_id
    ORDER BY uploaded_at DESC
');
$imagesStmt->execute([':incident_id' => $incidentId]);
$images = $imagesStmt->fetchAll();

echo json_encode([
    'ok' => true,
    'incident' => $incident,
    'images' => $images
]);
