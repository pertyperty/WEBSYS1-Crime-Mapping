<?php
/**
 * Shared incident querying helpers used by multiple API endpoints.
 */

function incident_viewer_context(): array
{
    return [
        'role' => $_SESSION['role'] ?? null,
        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'barangay_id' => isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : null,
    ];
}

function incident_visibility_clause(array $viewer, array &$params): string
{
    $role = $viewer['role'] ?? null;
    $userId = $viewer['user_id'] ?? null;
    $barangayId = $viewer['barangay_id'] ?? null;

    if ($role === 'admin') {
        return '1 = 1';
    }

    if ($role === 'barangay' && $barangayId) {
        $params[':viewer_barangay_id'] = (int) $barangayId;
        return 'i.barangay_id = :viewer_barangay_id';
    }

    if ($role === 'registered' && $userId) {
        $params[':viewer_user_id'] = (int) $userId;
        return '(i.is_public = 1 OR i.reported_by = :viewer_user_id)';
    }

    return 'i.is_public = 1';
}

function incident_fetch_many(PDO $pdo, array $viewer, array $filters = [], array $options = []): array
{
    $options = array_merge([
        'include_time' => false,
        'include_images' => false,
    ], $options);

    $where = [];
    $params = [];

    $where[] = incident_visibility_clause($viewer, $params);

    $types = $filters['types'] ?? [];
    if (is_string($types)) {
        $types = array_filter(array_map('trim', explode(',', $types)));
    }
    if (is_array($types) && $types) {
        $placeholders = [];
        foreach (array_values($types) as $index => $type) {
            $key = ':type' . $index;
            $placeholders[] = $key;
            $params[$key] = $type;
        }
        $where[] = 'ct.category IN (' . implode(',', $placeholders) . ')';
    }

    $barangay = isset($filters['barangay']) ? trim((string) $filters['barangay']) : '';
    if ($barangay !== '') {
        $where[] = 'b.barangay_name = :barangay';
        $params[':barangay'] = $barangay;
    }

    $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
    if ($status !== '') {
        $where[] = 'i.status = :status';
        $params[':status'] = $status;
    }

    $dateStart = isset($filters['date_start']) ? trim((string) $filters['date_start']) : '';
    if ($dateStart !== '') {
        $where[] = 'i.occurred_at >= :date_start';
        $params[':date_start'] = $dateStart . ' 00:00:00';
    }

    $dateEnd = isset($filters['date_end']) ? trim((string) $filters['date_end']) : '';
    if ($dateEnd !== '') {
        $where[] = 'i.occurred_at <= :date_end';
        $params[':date_end'] = $dateEnd . ' 23:59:59';
    }

    $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
    if ($search !== '') {
        $where[] = '(i.title LIKE :search OR i.description LIKE :search OR b.barangay_name LIKE :search OR ct.type_name LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $limit = isset($filters['limit']) ? (int) $filters['limit'] : 0;

    $selectTime = $options['include_time']
        ? ', TIME_FORMAT(i.occurred_at, "%H:%i") AS time'
        : '';

    $sql = "
        SELECT
            i.incident_id AS id,
            ct.category AS type,
            ct.type_name,
            i.title,
            i.description,
            b.barangay_name AS barangay,
            i.status,
            i.severity,
            DATE(i.occurred_at) AS date
            {$selectTime},
            i.latitude AS lat,
            i.longitude AS lng
        FROM incidents i
        JOIN barangays b ON i.barangay_id = b.barangay_id
        JOIN crime_types ct ON i.crime_type_id = ct.crime_type_id
    ";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY i.occurred_at DESC';

    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();

    if (!$options['include_images'] || !$incidents) {
        return $incidents;
    }

    $incidentIds = array_values(array_unique(array_map(static fn($row) => (int) ($row['id'] ?? 0), $incidents)));
    $incidentIds = array_values(array_filter($incidentIds, static fn($id) => $id > 0));
    if (!$incidentIds) {
        foreach ($incidents as &$incident) {
            $incident['images'] = [];
            $incident['image_count'] = 0;
        }
        unset($incident);
        return $incidents;
    }

    $placeholders = implode(',', array_fill(0, count($incidentIds), '?'));
    $imagesStmt = $pdo->prepare("
        SELECT incident_id, file_path, uploaded_at
        FROM incident_images
        WHERE incident_id IN ($placeholders)
        ORDER BY uploaded_at DESC
    ");
    $imagesStmt->execute($incidentIds);

    $imagesByIncident = [];
    foreach ($imagesStmt->fetchAll(PDO::FETCH_ASSOC) as $image) {
        $imagesByIncident[(int) $image['incident_id']][] = $image['file_path'];
    }

    foreach ($incidents as &$incident) {
        $incidentId = (int) ($incident['id'] ?? 0);
        $incident['images'] = $imagesByIncident[$incidentId] ?? [];
        $incident['image_count'] = count($incident['images']);
    }
    unset($incident);

    return $incidents;
}

function incident_kpis_admin(array $incidents): array
{
    $totalReports = count($incidents);
    $activeCount = count(array_filter($incidents, static fn($i) => in_array($i['status'] ?? null, ['pending', 'under_investigation', 'action_taken'], true)));
    $resolvedCount = count(array_filter($incidents, static fn($i) => ($i['status'] ?? null) === 'resolved'));
    $highSeverityCount = count(array_filter($incidents, static fn($i) => ($i['severity'] ?? null) === 'high'));

    return [
        'total' => $totalReports,
        'active' => $activeCount,
        'resolved' => $resolvedCount,
        'high_severity' => $highSeverityCount,
    ];
}

function incident_kpis_barangay(array $incidents): array
{
    $pending = count(array_filter($incidents, static fn($i) => ($i['status'] ?? null) === 'pending'));
    $active = count(array_filter($incidents, static fn($i) => in_array($i['status'] ?? null, ['pending', 'under_investigation', 'action_taken'], true)));
    $resolvedThisMonth = count(array_filter($incidents, static function ($i) {
        if (($i['status'] ?? null) !== 'resolved') {
            return false;
        }
        $date = $i['date'] ?? null;
        if (!$date) {
            return false;
        }
        return date('Y-m', strtotime((string) $date)) === date('Y-m');
    }));
    $highRisk = count(array_filter($incidents, static fn($i) => ($i['severity'] ?? null) === 'high'));

    return [
        'pending' => $pending,
        'active' => $active,
        'resolved_month' => $resolvedThisMonth,
        'high_risk' => $highRisk,
    ];
}

