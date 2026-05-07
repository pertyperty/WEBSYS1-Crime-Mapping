<?php
header('Content-Type: application/json');
require __DIR__ . '/security.php';
require __DIR__ . '/db.php';
init_secure_session();

// GET: Fetch FAQs for public display or admin
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $includeInactive = filter_var($_GET['include_inactive'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $role = $_SESSION['role'] ?? 'public';
    
    // Public can only see active FAQs
    if ($role !== 'admin' && !$includeInactive) {
        $stmt = $pdo->prepare('
            SELECT faq_id,
                COALESCE(question, "") AS question,
                COALESCE(answer, "") AS answer,
                COALESCE(category, "") AS category,
                sort_order
            FROM faqs
            WHERE is_active = 1
            ORDER BY category ASC, sort_order ASC
        ');
    } else {
        // Admin can see all FAQs
        $stmt = $pdo->prepare('
            SELECT faq_id,
                COALESCE(question, "") AS question,
                COALESCE(answer, "") AS answer,
                COALESCE(category, "") AS category,
                sort_order,
                is_active
            FROM faqs
            ORDER BY category ASC, sort_order ASC
        ');
    }
    
    $stmt->execute();
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'data' => $faqs
    ]);
    exit;
}

// POST: Admin only - add/update/delete FAQ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    
    // Check admin role
    if (($_SESSION['role'] ?? null) !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }
    
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid request payload.']);
        exit;
    }
    
    $action = $payload['action'] ?? '';
    
    if ($action === 'create') {
        $question = trim($payload['question'] ?? '');
        $answer = trim($payload['answer'] ?? '');
        $category = trim($payload['category'] ?? '');
        
        if ($question === '' || $answer === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
            exit;
        }
        
        $stmt = $pdo->prepare('
            INSERT INTO faqs (question, answer, category, sort_order)
            VALUES (:question, :answer, :category, COALESCE((SELECT MAX(sort_order) FROM faqs) + 1, 0))
        ');
        $stmt->execute([
            ':question' => $question,
            ':answer' => $answer,
            ':category' => $category ?: null
        ]);
        
        echo json_encode([
            'ok' => true,
            'faq_id' => (int) $pdo->lastInsertId()
        ]);
    } elseif ($action === 'update') {
        $faqId = (int) ($payload['faq_id'] ?? 0);
        $question = trim($payload['question'] ?? '');
        $answer = trim($payload['answer'] ?? '');
        $category = trim($payload['category'] ?? '');
        $isActive = (int) ($payload['is_active'] ?? 1);
        $sortOrder = (int) ($payload['sort_order'] ?? 0);
        
        if ($faqId === 0 || $question === '' || $answer === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
            exit;
        }
        
        $stmt = $pdo->prepare('
            UPDATE faqs
            SET question = :question, answer = :answer, category = :category, is_active = :is_active, sort_order = :sort_order
            WHERE faq_id = :faq_id
        ');
        $stmt->execute([
            ':faq_id' => $faqId,
            ':question' => $question,
            ':answer' => $answer,
            ':category' => $category ?: null,
            ':is_active' => $isActive,
            ':sort_order' => $sortOrder
        ]);
        
        echo json_encode(['ok' => true, 'faq_id' => $faqId]);
    } elseif ($action === 'delete') {
        $faqId = (int) ($payload['faq_id'] ?? 0);
        
        if ($faqId === 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Missing faq_id.']);
            exit;
        }
        
        $stmt = $pdo->prepare('DELETE FROM faqs WHERE faq_id = :faq_id');
        $stmt->execute([':faq_id' => $faqId]);
        
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
