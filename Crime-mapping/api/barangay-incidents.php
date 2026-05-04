<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();

require __DIR__ . '/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$barangayId = $_SESSION['barangay_id'] ?? null;
if (!$barangayId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Barangay not assigned.']);
    exit;
}

// Get incidents for this barangay
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
    WHERE i.barangay_id = :barangay_id
    ORDER BY i.occurred_at DESC
');
$incidentsStmt->execute([':barangay_id' => $barangayId]);
$incidents = $incidentsStmt->fetchAll();

// Calculate KPIs
$pending = count(array_filter($incidents, fn($i) => $i['status'] === 'pending'));
$active = count(array_filter($incidents, fn($i) => in_array($i['status'], ['pending', 'under_investigation', 'action_taken'])));
$resolved = count(array_filter($incidents, fn($i) => $i['status'] === 'resolved' && date('Y-m', strtotime($i['date'])) === date('Y-m')));
$highRisk = count(array_filter($incidents, fn($i) => $i['severity'] === 'high'));

echo json_encode([
    'ok' => true,
    'kpis' => [
        'pending' => $pending,
        'active' => $active,
        'resolved_month' => $resolved,
        'high_risk' => $highRisk
    ],
    'incidents' => $incidents
]);
