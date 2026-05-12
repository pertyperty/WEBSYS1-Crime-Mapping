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

// Generate filename with timestamp and applied filters
$filterSuffix = '';
if (!empty($filters['status'])) {
    $filterSuffix .= '-' . $filters['status'];
}
if (!empty($filters['date_start'])) {
    $filterSuffix .= '-from-' . str_replace('-', '', $filters['date_start']);
}

$filename = 'crime-incidents-' . date('Ymd-His') . $filterSuffix . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
if ($out === false) {
    http_response_code(500);
    echo 'Unable to export CSV.';
    exit;
}

// Add BOM for Excel UTF-8 compatibility
fwrite($out, "\xEF\xBB\xBF");

// CSV Headers
$headers = [
    'Incident ID',
    'Category',
    'Crime Type',
    'Title',
    'Description',
    'Barangay',
    'Status',
    'Severity',
    'Date',
    'Time',
    'Latitude',
    'Longitude',
    'Source',
    'Visibility',
    'Reported By'
];

fputcsv($out, $headers);

// CSV Data Rows
foreach ($incidents as $incident) {
    $visibility = ($incident['is_public'] ?? false) ? 'Public' : 'Private';
    $source = ucfirst($incident['source'] ?? 'unknown');
    
    fputcsv($out, [
        $incident['id'] ?? '',
        $incident['type'] ?? '',
        $incident['type_name'] ?? '',
        $incident['title'] ?? '',
        $incident['description'] ?? '',
        $incident['barangay'] ?? '',
        ucfirst($incident['status'] ?? ''),
        ucfirst($incident['severity'] ?? ''),
        $incident['date'] ?? '',
        $incident['time'] ?? '',
        $incident['lat'] ?? '',
        $incident['lng'] ?? '',
        $source,
        $visibility,
        $incident['reported_by'] ?? 'N/A'
    ]);
}

fclose($out);

