<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
require __DIR__ . '/db.php';
init_secure_session();


$role = $_SESSION['role'] ?? null;
if ($role !== 'admin' && $role !== 'barangay') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$reportType = $_GET['type'] ?? 'summary';
$month = $_GET['month'] ?? date('Y-m');
$barangayId = $_GET['barangay_id'] ?? null;
$crimeType = $_GET['crime_type'] ?? null;


if ($role === 'barangay') {
    $barangayId = $_SESSION['barangay_id'];
}

$data = [];

if ($reportType === 'monthly') {
    
    $query = 'SELECT status, severity, COUNT(*) as count FROM incidents WHERE 1=1';
    $params = [];
    
    if ($barangayId) {
        $query .= ' AND barangay_id = :barangay_id';
        $params[':barangay_id'] = $barangayId;
    }
    
    $query .= ' AND YEAR(occurred_at) = :year AND MONTH(occurred_at) = :month';
    list($year, $monthNum) = explode('-', $month);
    $params[':year'] = (int) $year;
    $params[':month'] = (int) $monthNum;
    
    $query .= ' GROUP BY status, severity';
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [
        'type' => 'monthly',
        'month' => $month,
        'incidents_by_status' => $results
    ];
} elseif ($reportType === 'area') {
    
    $query = 'SELECT b.barangay_name, COUNT(i.incident_id) as count, AVG(i.severity = "high") * 100 as high_severity_pct FROM barangays b LEFT JOIN incidents i ON b.barangay_id = i.barangay_id WHERE 1=1';
    $params = [];
    
    if ($barangayId) {
        $query .= ' AND b.barangay_id = :barangay_id';
        $params[':barangay_id'] = $barangayId;
    }
    
    $query .= ' AND YEAR(i.occurred_at) = :year AND MONTH(i.occurred_at) = :month GROUP BY b.barangay_id ORDER BY count DESC';
    list($year, $monthNum) = explode('-', $month);
    $params[':year'] = (int) $year;
    $params[':month'] = (int) $monthNum;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [
        'type' => 'area',
        'month' => $month,
        'barangay_stats' => $results
    ];
} elseif ($reportType === 'crime') {
    
    $query = 'SELECT ct.category, ct.type_name, COUNT(i.incident_id) as count FROM crime_types ct LEFT JOIN incidents i ON ct.crime_type_id = i.crime_type_id WHERE 1=1';
    $params = [];
    
    if ($barangayId) {
        $query .= ' AND i.barangay_id = :barangay_id';
        $params[':barangay_id'] = $barangayId;
    }
    
    if ($crimeType) {
        $query .= ' AND ct.category = :crime_type';
        $params[':crime_type'] = $crimeType;
    }
    
    $query .= ' AND YEAR(i.occurred_at) = :year AND MONTH(i.occurred_at) = :month GROUP BY ct.category, ct.type_name ORDER BY count DESC';
    list($year, $monthNum) = explode('-', $month);
    $params[':year'] = (int) $year;
    $params[':month'] = (int) $monthNum;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [
        'type' => 'crime',
        'month' => $month,
        'crime_stats' => $results
    ];
} elseif ($reportType === 'forensics') {
    
    $query = 'SELECT i.incident_id, i.title, i.description, i.severity, i.status, i.occurred_at, b.barangay_name, ct.type_name, COUNT(img.image_id) as image_count, COUNT(val.validation_id) as validation_count FROM incidents i LEFT JOIN barangays b ON i.barangay_id = b.barangay_id LEFT JOIN crime_types ct ON i.crime_type_id = ct.crime_type_id LEFT JOIN incident_images img ON i.incident_id = img.incident_id LEFT JOIN incident_validations val ON i.incident_id = val.incident_id WHERE i.status IN ("under_investigation", "action_taken")';
    $params = [];
    
    if ($barangayId) {
        $query .= ' AND i.barangay_id = :barangay_id';
        $params[':barangay_id'] = $barangayId;
    }
    
    if ($crimeType) {
        $query .= ' AND ct.category = :crime_type';
        $params[':crime_type'] = $crimeType;
    }
    
    $query .= ' AND YEAR(i.occurred_at) = :year AND MONTH(i.occurred_at) = :month GROUP BY i.incident_id ORDER BY i.severity DESC, i.occurred_at DESC';
    list($year, $monthNum) = explode('-', $month);
    $params[':year'] = (int) $year;
    $params[':month'] = (int) $monthNum;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [
        'type' => 'forensics',
        'month' => $month,
        'active_investigations' => $results
    ];
} else {
    
    $query = 'SELECT COUNT(*) as total, SUM(severity = "high") as high_severity, SUM(status = "resolved") as resolved, SUM(status = "pending") as pending FROM incidents WHERE 1=1';
    $params = [];
    
    if ($barangayId) {
        $query .= ' AND barangay_id = :barangay_id';
        $params[':barangay_id'] = $barangayId;
    }
    
    $query .= ' AND YEAR(occurred_at) = :year AND MONTH(occurred_at) = :month';
    list($year, $monthNum) = explode('-', $month);
    $params[':year'] = (int) $year;
    $params[':month'] = (int) $monthNum;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $data = [
        'type' => 'summary',
        'month' => $month,
        'summary' => $summary
    ];
}

echo json_encode([
    'ok' => true,
    'data' => $data,
    'generated_at' => date('Y-m-d H:i:s')
]);
