<?php
require __DIR__ . '/guard.php';
requireRole(['admin', 'barangay']);

require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/incidents-repository.php';

init_secure_session();

$viewer = incident_viewer_context();

$filters = [
    'types' => isset($_GET['types']) ? trim((string) $_GET['types']) : '',
    'barangay' => isset($_GET['barangay']) ? trim((string) $_GET['barangay']) : '',
    'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
    'date_start' => isset($_GET['date_start']) ? trim((string) $_GET['date_start']) : '',
    'date_end' => isset($_GET['date_end']) ? trim((string) $_GET['date_end']) : '',
    'search' => isset($_GET['search']) ? trim((string) $_GET['search']) : '',
    'limit' => 0,
];

$incidents = incident_fetch_many($pdo, $viewer, $filters, [
    'include_time' => true,
    'include_images' => false,
]);

$filename = 'crime-incidents-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
if ($out === false) {
    http_response_code(500);
    echo 'Unable to export CSV.';
    exit;
}

fputcsv($out, ['Incident ID', 'Category', 'Crime Type', 'Title', 'Description', 'Barangay', 'Status', 'Severity', 'Date', 'Time', 'Latitude', 'Longitude']);

foreach ($incidents as $incident) {
    fputcsv($out, [
        $incident['id'] ?? '',
        $incident['type'] ?? '',
        $incident['type_name'] ?? '',
        $incident['title'] ?? '',
        $incident['description'] ?? '',
        $incident['barangay'] ?? '',
        $incident['status'] ?? '',
        $incident['severity'] ?? '',
        $incident['date'] ?? '',
        $incident['time'] ?? '',
        $incident['lat'] ?? '',
        $incident['lng'] ?? '',
    ]);
}

fclose($out);

