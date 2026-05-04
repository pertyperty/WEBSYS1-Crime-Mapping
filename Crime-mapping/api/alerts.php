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

$sql = "
    SELECT
        i.incident_id AS id,
        i.title,
        b.barangay_name AS barangay,
        i.severity,
        DATE(i.occurred_at) AS date
    FROM incidents i
    JOIN barangays b ON i.barangay_id = b.barangay_id
    WHERE i.severity = 'high' AND {$visibilityClause}
    ORDER BY i.occurred_at DESC
    LIMIT 5
";

$stmt = $pdo->prepare($sql);
$stmt->execute($visibilityParams);
$alerts = $stmt->fetchAll();

echo json_encode([
    'ok' => true,
    'data' => $alerts
]);
