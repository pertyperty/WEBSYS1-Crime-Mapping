<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();
require __DIR__ . '/db.php';

$viewerRole = $_SESSION['role'] ?? null;
$viewerUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$viewerBarangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : null;

// Validate role
if (!in_array($viewerRole, ['admin', 'barangay'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

$visibilityClause = 'i.is_public = 1';
$visibilityParams = [];

if ($viewerRole === 'admin') {
    $visibilityClause = '1 = 1';
} elseif ($viewerRole === 'barangay' && $viewerBarangayId) {
    $visibilityClause = 'i.barangay_id = :viewer_barangay_id';
    $visibilityParams[':viewer_barangay_id'] = $viewerBarangayId;
}

// Crime types distribution
$crimeTypesStmt = $pdo->prepare("
    SELECT ct.category, ct.type_name, COUNT(*) AS count
    FROM incidents i
    JOIN crime_types ct ON i.crime_type_id = ct.crime_type_id
    WHERE {$visibilityClause}
    GROUP BY ct.category, ct.type_name
    ORDER BY count DESC
");
$crimeTypesStmt->execute($visibilityParams);
$crimeTypesData = $crimeTypesStmt->fetchAll();

// Status distribution
$statusStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS count
    FROM incidents i
    WHERE {$visibilityClause}
    GROUP BY status
    ORDER BY count DESC
");
$statusStmt->execute($visibilityParams);
$statusData = $statusStmt->fetchAll();

// Severity distribution
$severityStmt = $pdo->prepare("
    SELECT severity, COUNT(*) AS count
    FROM incidents i
    WHERE {$visibilityClause}
    GROUP BY severity
    ORDER BY FIELD(severity, 'high', 'medium', 'low')
");
$severityStmt->execute($visibilityParams);
$severityData = $severityStmt->fetchAll();

// Barangay distribution (for admin view)
$barangayData = [];
if ($viewerRole === 'admin') {
    $barangayStmt = $pdo->prepare("
        SELECT b.barangay_name, COUNT(*) AS count
        FROM incidents i
        JOIN barangays b ON i.barangay_id = b.barangay_id
        WHERE 1 = 1
        GROUP BY b.barangay_id
        ORDER BY count DESC
    ");
    $barangayStmt->execute();
    $barangayData = $barangayStmt->fetchAll();
}

echo json_encode([
    'ok' => true,
    'data' => [
        'crime_types' => $crimeTypesData,
        'status' => $statusData,
        'severity' => $severityData,
        'barangays' => $barangayData
    ]
]);
