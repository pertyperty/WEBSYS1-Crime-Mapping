<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
init_secure_session();

require __DIR__ . '/db.php';

$viewerRole = $_SESSION['role'] ?? null;
$viewerUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$viewerBarangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : null;

function buildIncidentVisibilityClause(?string $viewerRole, ?int $viewerUserId, ?int $viewerBarangayId, array &$params): string
{
    if ($viewerRole === 'admin') {
        return '1 = 1';
    }

    if ($viewerRole === 'barangay' && $viewerBarangayId) {
        $params[':viewer_barangay_id'] = $viewerBarangayId;
        return 'i.barangay_id = :viewer_barangay_id';
    }

    if ($viewerRole === 'registered' && $viewerUserId) {
        $params[':viewer_user_id'] = $viewerUserId;
        return '(i.is_public = 1 OR i.reported_by = :viewer_user_id)';
    }

    return 'i.is_public = 1';
}

// Handle GET request - fetch counts and user reaction
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['incident_id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing incident_id.']);
        exit;
    }

    $incidentId = (int) $_GET['incident_id'];
    $visibilityParams = [];
    $visibilityClause = buildIncidentVisibilityClause($viewerRole, $viewerUserId, $viewerBarangayId, $visibilityParams);

    // Check if incident exists
    $incidentStmt = $pdo->prepare('SELECT incident_id FROM incidents i WHERE i.incident_id = :id AND ' . $visibilityClause);
    $incidentStmt->execute(array_merge([':id' => $incidentId], $visibilityParams));
    if (!$incidentStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Incident not found.']);
        exit;
    }

    // Get counts
    $credibleStmt = $pdo->prepare('
        SELECT COUNT(*) as count FROM incident_validations
        WHERE incident_id = :incident_id AND reaction = "credible"
    ');
    $credibleStmt->execute([':incident_id' => $incidentId]);
    $credibleCount = (int) $credibleStmt->fetch()['count'];

    $notCredibleStmt = $pdo->prepare('
        SELECT COUNT(*) as count FROM incident_validations
        WHERE incident_id = :incident_id AND reaction = "not_credible"
    ');
    $notCredibleStmt->execute([':incident_id' => $incidentId]);
    $notCredibleCount = (int) $notCredibleStmt->fetch()['count'];

    // Get user's reaction if logged in
    $userReaction = null;
    $userId = $_SESSION['user_id'] ?? null;

    if ($userId) {
        $userReactionStmt = $pdo->prepare('
            SELECT reaction FROM incident_validations
            WHERE incident_id = :incident_id AND user_id = :user_id
        ');
        $userReactionStmt->execute([':incident_id' => $incidentId, ':user_id' => $userId]);
        $result = $userReactionStmt->fetch();
        $userReaction = $result ? $result['reaction'] : null;
    } else {
        // Check guest token
        if (isset($_COOKIE['guest_token'])) {
            $guestToken = $_COOKIE['guest_token'];
            $userReactionStmt = $pdo->prepare('
                SELECT reaction FROM incident_validations
                WHERE incident_id = :incident_id AND guest_token = :guest_token
            ');
            $userReactionStmt->execute([':incident_id' => $incidentId, ':guest_token' => $guestToken]);
            $result = $userReactionStmt->fetch();
            $userReaction = $result ? $result['reaction'] : null;
        }
    }

    echo json_encode([
        'ok' => true,
        'incident_id' => $incidentId,
        'credible' => $credibleCount,
        'not_credible' => $notCredibleCount,
        'user_reaction' => $userReaction
    ]);
    exit;
}

// Handle POST request - submit/toggle validation
require_csrf_token();
$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request payload.']);
    exit;
}

$required = ['incident_id', 'reaction'];
foreach ($required as $field) {
    if (!isset($payload[$field]) || $payload[$field] === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
        exit;
    }
}

$incidentId = (int) $payload['incident_id'];
$reaction = trim($payload['reaction']);
$visibilityParams = [];
$visibilityClause = buildIncidentVisibilityClause($viewerRole, $viewerUserId, $viewerBarangayId, $visibilityParams);

// Validate reaction value
$validReactions = ['credible', 'not_credible'];
if (!in_array($reaction, $validReactions, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid reaction value.']);
    exit;
}

// Check if incident exists
$incidentStmt = $pdo->prepare('SELECT incident_id FROM incidents i WHERE i.incident_id = :id AND ' . $visibilityClause);
$incidentStmt->execute(array_merge([':id' => $incidentId], $visibilityParams));
if (!$incidentStmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Incident not found.']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$guestToken = null;

// If not logged in, use guest token (from cookie or generate new one)
if (!$userId) {
    if (isset($_COOKIE['guest_token'])) {
        $guestToken = $_COOKIE['guest_token'];
    } else {
        $guestToken = bin2hex(random_bytes(32));
        setcookie('guest_token', $guestToken, [
            'expires' => time() + (365 * 24 * 60 * 60),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

// Check if user/guest already voted on this incident
if ($userId) {
    $existingStmt = $pdo->prepare('
        SELECT validation_id, reaction FROM incident_validations
        WHERE incident_id = :incident_id AND user_id = :user_id
    ');
    $existingStmt->execute([':incident_id' => $incidentId, ':user_id' => $userId]);
    $existing = $existingStmt->fetch();
} else {
    $existingStmt = $pdo->prepare('
        SELECT validation_id, reaction FROM incident_validations
        WHERE incident_id = :incident_id AND guest_token = :guest_token
    ');
    $existingStmt->execute([':incident_id' => $incidentId, ':guest_token' => $guestToken]);
    $existing = $existingStmt->fetch();
}

// If same reaction, remove vote (toggle)
if ($existing && $existing['reaction'] === $reaction) {
    $deleteStmt = $pdo->prepare('DELETE FROM incident_validations WHERE validation_id = :id');
    $deleteStmt->execute([':id' => $existing['validation_id']]);
    $isRemoved = true;
} else {
    // Delete old vote if exists (switching reactions)
    if ($existing) {
        $deleteStmt = $pdo->prepare('DELETE FROM incident_validations WHERE validation_id = :id');
        $deleteStmt->execute([':id' => $existing['validation_id']]);
    }

    // Insert new vote
    $insertStmt = $pdo->prepare('
        INSERT INTO incident_validations (incident_id, user_id, guest_token, reaction)
        VALUES (:incident_id, :user_id, :guest_token, :reaction)
    ');
    $insertStmt->execute([
        ':incident_id' => $incidentId,
        ':user_id' => $userId,
        ':guest_token' => $guestToken,
        ':reaction' => $reaction
    ]);
    $isRemoved = false;
}

// Get updated counts
$credibleStmt = $pdo->prepare('
    SELECT COUNT(*) as count FROM incident_validations
    WHERE incident_id = :incident_id AND reaction = "credible"
');
$credibleStmt->execute([':incident_id' => $incidentId]);
$credibleCount = (int) $credibleStmt->fetch()['count'];

$notCredibleStmt = $pdo->prepare('
    SELECT COUNT(*) as count FROM incident_validations
    WHERE incident_id = :incident_id AND reaction = "not_credible"
');
$notCredibleStmt->execute([':incident_id' => $incidentId]);
$notCredibleCount = (int) $notCredibleStmt->fetch()['count'];

// Get current user's reaction
$userReaction = null;
if ($userId) {
    $userReactionStmt = $pdo->prepare('
        SELECT reaction FROM incident_validations
        WHERE incident_id = :incident_id AND user_id = :user_id
    ');
    $userReactionStmt->execute([':incident_id' => $incidentId, ':user_id' => $userId]);
    $result = $userReactionStmt->fetch();
    $userReaction = $result ? $result['reaction'] : null;
} else {
    $userReactionStmt = $pdo->prepare('
        SELECT reaction FROM incident_validations
        WHERE incident_id = :incident_id AND guest_token = :guest_token
    ');
    $userReactionStmt->execute([':incident_id' => $incidentId, ':guest_token' => $guestToken]);
    $result = $userReactionStmt->fetch();
    $userReaction = $result ? $result['reaction'] : null;
}

echo json_encode([
    'ok' => true,
    'incident_id' => $incidentId,
    'credible' => $credibleCount,
    'not_credible' => $notCredibleCount,
    'user_reaction' => $userReaction,
    'removed' => $isRemoved
]);
