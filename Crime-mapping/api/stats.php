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

$dailyStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM incidents i WHERE i.occurred_at >= (NOW() - INTERVAL 1 DAY) AND {$visibilityClause}");
$dailyStmt->execute($visibilityParams);
$daily = (int) $dailyStmt->fetchColumn();

$activeStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM incidents i WHERE i.status IN ('pending','under_investigation','action_taken') AND {$visibilityClause}");
$activeStmt->execute($visibilityParams);
$active = (int) $activeStmt->fetchColumn();

$hotspotStmt = $pdo->prepare("
    SELECT b.barangay_name, COUNT(*) AS total
    FROM incidents i
    JOIN barangays b ON i.barangay_id = b.barangay_id
    WHERE i.occurred_at >= (NOW() - INTERVAL 30 DAY) AND {$visibilityClause}
    GROUP BY b.barangay_id
    ORDER BY total DESC
    LIMIT 1
$");
$hotspotStmt->execute($visibilityParams);
$hotspotRow = $hotspotStmt->fetch();
$hotspot = $hotspotRow ? $hotspotRow['barangay_name'] : '-';

echo json_encode([
    'ok' => true,
    'data' => [
        'daily' => $daily,
        'active' => $active,
        'hotspot' => $hotspot
    ]
]);
