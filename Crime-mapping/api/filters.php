<?php
header('Content-Type: application/json');
session_start();
require __DIR__ . '/db.php';

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

$typesStmt = $pdo->query("SELECT crime_type_id, category, type_name FROM crime_types WHERE is_active = 1 ORDER BY category, type_name");
$types = $typesStmt->fetchAll();

$barangaysStmt = $pdo->query("SELECT barangay_name FROM barangays ORDER BY barangay_name");
$barangays = $barangaysStmt->fetchAll();

$dateStmt = $pdo->prepare("SELECT MIN(i.occurred_at) AS min_date, MAX(i.occurred_at) AS max_date FROM incidents i WHERE {$visibilityClause}");
$dateStmt->execute($visibilityParams);
$dateRange = $dateStmt->fetch();

$statuses = [
    'pending',
    'under_investigation',
    'action_taken',
    'resolved',
    'dismissed'
];

echo json_encode([
    'ok' => true,
    'data' => [
        'types' => $types,
        'barangays' => array_map(function ($row) {
            return $row['barangay_name'];
        }, $barangays),
        'date_range' => [
            'min' => $dateRange ? $dateRange['min_date'] : null,
            'max' => $dateRange ? $dateRange['max_date'] : null
        ],
        'statuses' => $statuses
    ]
]);
