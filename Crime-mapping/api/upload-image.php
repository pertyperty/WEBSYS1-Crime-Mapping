<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();

require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_csrf_token();

if (!isset($_POST['incident_id']) || !isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing incident_id or image file.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$incidentId = (int) $_POST['incident_id'];
$file = $_FILES['image'];

$checkStmt = $pdo->prepare('SELECT incident_id, barangay_id, reported_by FROM incidents WHERE incident_id = :id');
$checkStmt->execute([':id' => $incidentId]);
$incident = $checkStmt->fetch();
if (!$incident) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Incident not found.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? null;
$userBarangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : null;

$isAuthorized = false;
if ($userRole === 'admin') {
    $isAuthorized = true;
} elseif ($userRole === 'barangay' && $userBarangayId && $userBarangayId === (int) $incident['barangay_id']) {
    $isAuthorized = true;
} elseif ($userRole === 'registered' && isset($incident['reported_by']) && (int) $incident['reported_by'] === $userId) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File upload error.']);
    exit;
}

$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'File size exceeds 5MB limit.']);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.']);
    exit;
}

$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$filename = bin2hex(random_bytes(16)) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
$filePath = $uploadsDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save file.']);
    exit;
}

$relativePath = 'uploads/' . $filename;

$insertStmt = $pdo->prepare('
    INSERT INTO incident_images (incident_id, file_path)
    VALUES (:incident_id, :file_path)
');
$insertStmt->execute([
    ':incident_id' => $incidentId,
    ':file_path' => $relativePath
]);

echo json_encode([
    'ok' => true,
    'image_id' => (int) $pdo->lastInsertId(),
    'file_path' => $relativePath
]);
