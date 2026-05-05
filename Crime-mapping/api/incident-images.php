<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
require __DIR__ . '/db.php';
init_secure_session();

$incidentId = $_GET['incident_id'] ?? null;

if (!$incidentId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing incident_id.']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT image_id, file_path, uploaded_at
        FROM incident_images
        WHERE incident_id = :incident_id
        ORDER BY uploaded_at DESC
    ');
    $stmt->execute([':incident_id' => (int) $incidentId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'incident_id' => (int) $incidentId,
        'images' => $images,
        'count' => count($images)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to fetch images.'
    ]);
}
