<?php
header('Content-Type: application/json');
session_start();

require __DIR__ . '/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

// Get all incidents
 $incidentsStmt = $pdo->prepare('
    SELECT
        i.incident_id AS id,
        ct.category AS type,
        ct.type_name,
        i.title,
        i.description,
        b.barangay_name AS barangay,
        i.status,
        i.severity,
        DATE(i.occurred_at) AS date,
        TIME_FORMAT(i.occurred_at, "%H:%i") AS time,
        i.latitude AS lat,
        i.longitude AS lng
    FROM incidents i
    JOIN barangays b ON i.barangay_id = b.barangay_id
    JOIN crime_types ct ON i.crime_type_id = ct.crime_type_id
    ORDER BY i.occurred_at DESC
');
$incidentsStmt->execute();
$incidents = $incidentsStmt->fetchAll();

$incidentIds = array_map(fn($incident) => (int) $incident['id'], $incidents);
$imagesByIncident = [];
if ($incidentIds) {
    $placeholders = implode(',', array_fill(0, count($incidentIds), '?'));
    $imagesStmt = $pdo->prepare("\n        SELECT incident_id, file_path, uploaded_at\n        FROM incident_images\n        WHERE incident_id IN ($placeholders)\n        ORDER BY uploaded_at DESC\n    ");
    $imagesStmt->execute($incidentIds);
    foreach ($imagesStmt->fetchAll(PDO::FETCH_ASSOC) as $image) {
        $imagesByIncident[(int) $image['incident_id']][] = $image['file_path'];
    }
}

foreach ($incidents as &$incident) {
    $incidentId = (int) $incident['id'];
    $incident['images'] = $imagesByIncident[$incidentId] ?? [];
    $incident['image_count'] = count($incident['images']);
}
unset($incident);

// Calculate KPIs
$totalReports = count($incidents);
$activeCount = count(array_filter($incidents, fn($i) => in_array($i['status'], ['pending', 'under_investigation', 'action_taken'])));
$resolvedCount = count(array_filter($incidents, fn($i) => $i['status'] === 'resolved'));
$highSeverityCount = count(array_filter($incidents, fn($i) => $i['severity'] === 'high'));

echo json_encode([
    'ok' => true,
    'kpis' => [
        'total' => $totalReports,
        'active' => $activeCount,
        'resolved' => $resolvedCount,
        'high_severity' => $highSeverityCount
    ],
    'incidents' => $incidents
]);
