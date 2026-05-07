<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();
require __DIR__ . '/db.php';

require __DIR__ . '/incidents-repository.php';

$filters = [
    'types' => isset($_GET['types']) ? trim((string) $_GET['types']) : '',
    'barangay' => isset($_GET['barangay']) ? trim((string) $_GET['barangay']) : '',
    'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
    'date_start' => isset($_GET['date_start']) ? trim((string) $_GET['date_start']) : '',
    'date_end' => isset($_GET['date_end']) ? trim((string) $_GET['date_end']) : '',
    'search' => isset($_GET['search']) ? trim((string) $_GET['search']) : '',
    'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 0,
];

$viewer = incident_viewer_context();
$incidents = incident_fetch_many($pdo, $viewer, $filters, [
    'include_time' => false,
    'include_images' => false,
]);

echo json_encode([
    'ok' => true,
    'data' => $incidents
]);
