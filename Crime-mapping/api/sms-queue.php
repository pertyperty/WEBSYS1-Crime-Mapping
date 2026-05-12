<?php
header('Content-Type: application/json');

require __DIR__ . '/db.php';
require __DIR__ . '/sms-helper.php';

if (PHP_SAPI !== 'cli') {
    require __DIR__ . '/security.php';
    init_secure_session();

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
        exit;
    }
}

$config = require __DIR__ . '/config.php';
$limit = 20;
$reclaimSeconds = 600;
$maxAttempts = 3;

try {
    $pdo->beginTransaction();

    $selectSql = 
        "SELECT queue_id FROM notification_sms_queue \
        WHERE (status = 'pending' OR (status = 'processing' AND locked_at < DATE_SUB(NOW(), INTERVAL {$reclaimSeconds} SECOND))) \
        ORDER BY created_at ASC LIMIT {$limit} FOR UPDATE";

    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->execute();
    $queueRows = $selectStmt->fetchAll();

    if (!$queueRows) {
        $pdo->commit();
        echo json_encode(['ok' => true, 'processed' => 0]);
        exit;
    }

    $queueIds = array_column($queueRows, 'queue_id');
    $updateSql = sprintf(
        "UPDATE notification_sms_queue SET status = 'processing', locked_at = NOW() WHERE queue_id IN (%s)",
        implode(',', array_map('intval', $queueIds))
    );
    $pdo->exec($updateSql);
    $pdo->commit();

    $rowsSql = sprintf(
        "SELECT queue_id, notification_id, user_id, phone, message, attempts FROM notification_sms_queue WHERE queue_id IN (%s)",
        implode(',', array_map('intval', $queueIds))
    );
    $rowsStmt = $pdo->query($rowsSql);
    $rows = $rowsStmt->fetchAll();

    $processed = 0;

    $updateStmt = $pdo->prepare(
        'UPDATE notification_sms_queue SET status = :status, attempts = :attempts, last_error = :last_error, updated_at = NOW() WHERE queue_id = :queue_id'
    );
    $notificationUpdateStmt = $pdo->prepare(
        'UPDATE notifications SET sms_status = :sms_status, sms_sent_at = :sms_sent_at WHERE notification_id = :notification_id'
    );

    foreach ($rows as $row) {
        $processed++;
        $attempts = (int) $row['attempts'] + 1;
        $result = send_sms_message($row['phone'], $row['message'], $config);

        $status = $result['success'] ? 'sent' : ($attempts >= $maxAttempts ? 'failed' : 'pending');
        $lastError = $result['error'] ?? null;
        $smsSentAt = $result['success'] ? date('Y-m-d H:i:s') : null;

        $updateStmt->execute([
            ':status' => $status,
            ':attempts' => $attempts,
            ':last_error' => $lastError,
            ':queue_id' => $row['queue_id']
        ]);

        if ($result['success']) {
            $notificationUpdateStmt->execute([
                ':sms_status' => 'sent',
                ':sms_sent_at' => $smsSentAt,
                ':notification_id' => $row['notification_id']
            ]);
        } elseif ($status === 'failed') {
            $notificationUpdateStmt->execute([
                ':sms_status' => 'failed',
                ':sms_sent_at' => null,
                ':notification_id' => $row['notification_id']
            ]);
        }
    }

    echo json_encode(['ok' => true, 'processed' => $processed]);
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to process SMS queue.',
        'details' => $exception->getMessage()
    ]);
}
