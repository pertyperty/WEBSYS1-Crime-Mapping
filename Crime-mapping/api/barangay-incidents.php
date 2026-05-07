<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();

require __DIR__ . '/db.php';
require __DIR__ . '/incidents-repository.php';

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

$viewer = incident_viewer_context();
$incidents = incident_fetch_many($pdo, $viewer, [], [
    'include_time' => true,
    'include_images' => true,
]);

echo json_encode([
    'ok' => true,
    'kpis' => incident_kpis_barangay($incidents),
    'incidents' => $incidents
]);
