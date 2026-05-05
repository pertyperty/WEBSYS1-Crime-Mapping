<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
require __DIR__ . '/db.php';
init_secure_session();

// Check admin role
$role = $_SESSION['role'] ?? null;
if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all users
    $stmt = $pdo->prepare('
        SELECT 
            u.user_id, 
            u.username, 
            u.email, 
            u.contact, 
            u.address,
            u.role, 
            b.barangay_name,
            u.status,
            u.created_at,
            COUNT(i.incident_id) as incident_count
        FROM users u
        LEFT JOIN barangays b ON u.barangay_id = b.barangay_id
        LEFT JOIN incidents i ON u.user_id = i.reported_by
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
    ');
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'users' => $users
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid request payload.']);
        exit;
    }
    
    $action = $payload['action'] ?? '';
    
    if ($action === 'toggle-status') {
        $userId = (int) ($payload['user_id'] ?? 0);
        $newStatus = $payload['status'] ?? 'active';
        
        if ($userId === 0 || !in_array($newStatus, ['active', 'disabled'])) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid parameters.']);
            exit;
        }
        
        // Don't allow disabling yourself
        if ($userId === (int) $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Cannot disable your own account.']);
            exit;
        }
        
        $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE user_id = :user_id');
        $stmt->execute([':status' => $newStatus, ':user_id' => $userId]);
        
        echo json_encode(['ok' => true, 'status' => $newStatus]);
    } elseif ($action === 'delete') {
        $userId = (int) ($payload['user_id'] ?? 0);
        
        if ($userId === 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid user_id.']);
            exit;
        }
        
        // Don't allow deleting yourself
        if ($userId === (int) $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Cannot delete your own account.']);
            exit;
        }
        
        // Soft delete by disabling
        $stmt = $pdo->prepare('UPDATE users SET status = "disabled" WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        
        echo json_encode(['ok' => true, 'message' => 'User disabled.']);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
