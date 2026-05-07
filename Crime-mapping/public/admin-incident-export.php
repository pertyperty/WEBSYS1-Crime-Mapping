<?php
require __DIR__ . '/guard.php';
requireRole(['admin']);
require __DIR__ . '/../api/db.php';

$incidentId = (int) ($_GET['incident_id'] ?? 0);
if ($incidentId <= 0) {
    http_response_code(400);
    echo 'Missing incident_id.';
    exit;
}

$incidentStmt = $pdo->prepare('
    SELECT
        i.incident_id AS id,
        ct.category AS type,
        ct.type_name,
        i.title,
        i.description,
        b.barangay_name AS barangay,
        i.status,
        i.severity,
        i.source,
        i.is_public,
        DATE_FORMAT(i.occurred_at, "%Y-%m-%d %H:%i") AS occurred_at,
        DATE_FORMAT(i.created_at, "%Y-%m-%d %H:%i") AS created_at,
        i.latitude AS lat,
        i.longitude AS lng,
        u.username AS reported_by
    FROM incidents i
    JOIN barangays b ON i.barangay_id = b.barangay_id
    JOIN crime_types ct ON i.crime_type_id = ct.crime_type_id
    LEFT JOIN users u ON i.reported_by = u.user_id
    WHERE i.incident_id = :id
');
$incidentStmt->execute([':id' => $incidentId]);
$incident = $incidentStmt->fetch(PDO::FETCH_ASSOC);

if (!$incident) {
    http_response_code(404);
    echo 'Incident not found.';
    exit;
}

$imagesStmt = $pdo->prepare('
    SELECT image_id, file_path, uploaded_at
    FROM incident_images
    WHERE incident_id = :incident_id
    ORDER BY uploaded_at ASC, image_id ASC
');
$imagesStmt->execute([':incident_id' => $incidentId]);
$images = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

$logsStmt = $pdo->prepare('
    SELECT
        l.log_id,
        l.action,
        l.remarks,
        l.created_at,
        u.username AS created_by_username,
        u.role AS created_by_role
    FROM incident_logs l
    LEFT JOIN users u ON l.created_by = u.user_id
    WHERE l.incident_id = :incident_id
    ORDER BY l.created_at ASC, l.log_id ASC
');
$logsStmt->execute([':incident_id' => $incidentId]);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function label_case(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

$statusLabel = label_case((string) ($incident['status'] ?? ''));
$severityLabel = label_case((string) ($incident['severity'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Export Incident #<?php echo esc($incident['id']); ?></title>
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <style>
        :root {
            --bg: #f4f7fb;
            --panel: #ffffff;
            --text: #132238;
            --muted: #5c7087;
            --border: #d7e0ea;
            --accent: #154ed8;
            --accent-2: #0f766e;
            --shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'IBM Plex Sans', sans-serif;
            color: var(--text);
            background: linear-gradient(180deg, #e8eef7 0%, var(--bg) 42%, #f8fbff 100%);
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 28px 20px 56px;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
        }

        .brand {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            appearance: none;
            border: 1px solid var(--border);
            background: var(--panel);
            color: var(--text);
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            box-shadow: var(--shadow);
        }

        .btn.primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .hero {
            background: radial-gradient(circle at top right, rgba(21, 78, 216, 0.12), transparent 36%), var(--panel);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow);
            padding: 26px;
            margin-bottom: 18px;
        }

        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-size: 12px;
            color: var(--accent-2);
            margin-bottom: 10px;
        }

        h1, h2, h3 { font-family: 'Space Grotesk', sans-serif; margin: 0; }
        h1 { font-size: 34px; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 18px;
        }

        .meta-label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 8px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 600;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .detail-list {
            display: grid;
            gap: 12px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }

        .detail-item:last-child { border-bottom: 0; padding-bottom: 0; }

        .detail-item span:first-child {
            color: var(--muted);
            flex: 0 0 auto;
        }

        .detail-item span:last-child {
            text-align: right;
            font-weight: 600;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-right: 8px;
            margin-top: 8px;
        }

        .badge.status { background: #e0ecff; color: #154ed8; }
        .badge.severity { background: #fff4d6; color: #8a5b00; }

        .description {
            line-height: 1.7;
            color: #243648;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }

        .gallery img {
            width: 100%;
            height: 170px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid var(--border);
        }

        .timeline {
            display: grid;
            gap: 12px;
        }

        .timeline-item {
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fbfdff;
        }

        .timeline-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .muted { color: var(--muted); }

        @media print {
            body { background: #fff; }
            .toolbar, .btn { display: none !important; }
            .page { padding: 0; max-width: none; }
            .hero, .card { box-shadow: none; }
            .hero, .card { break-inside: avoid; }
        }

        @media (max-width: 860px) {
            .summary-grid, .detail-grid { grid-template-columns: 1fr; }
            .toolbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="toolbar">
            <div class="brand">Incident Export</div>
            <div class="toolbar-actions">
                <a class="btn" href="admin-map.php?incident=<?php echo esc($incident['id']); ?>">Back to map</a>
                <button class="btn primary" type="button" onclick="window.print()">Print / Save as PDF</button>
            </div>
        </div>

        <section class="hero">
            <div class="eyebrow">Admin incident export</div>
            <h1><?php echo esc($incident['title']); ?></h1>
            <div>
                <span class="badge status"><?php echo esc($statusLabel ?: 'Unknown'); ?></span>
                <span class="badge severity"><?php echo esc($severityLabel ?: 'Unknown'); ?></span>
            </div>
        </section>

        <section class="summary-grid">
            <div class="card">
                <div class="meta-label">Incident</div>
                <div class="meta-value">#<?php echo esc($incident['id']); ?></div>
            </div>
            <div class="card">
                <div class="meta-label">Barangay</div>
                <div class="meta-value"> <?php echo esc($incident['barangay']); ?></div>
            </div>
            <div class="card">
                <div class="meta-label">Occurred</div>
                <div class="meta-value"><?php echo esc($incident['occurred_at']); ?></div>
            </div>
        </section>

        <section class="detail-grid">
            <div class="card">
                <h2>Incident Details</h2>
                <div class="detail-list" style="margin-top: 16px;">
                    <div class="detail-item"><span>Type</span><span><?php echo esc($incident['type_name']); ?></span></div>
                    <div class="detail-item"><span>Source</span><span><?php echo esc(label_case((string) $incident['source'])); ?></span></div>
                    <div class="detail-item"><span>Visibility</span><span><?php echo ((int) ($incident['is_public'] ?? 0)) ? 'Public' : 'Private'; ?></span></div>
                    <div class="detail-item"><span>Coordinates</span><span><?php echo esc($incident['lat']); ?>, <?php echo esc($incident['lng']); ?></span></div>
                    <div class="detail-item"><span>Reported by</span><span><?php echo esc($incident['reported_by'] ?: 'Unknown'); ?></span></div>
                    <div class="detail-item"><span>Created</span><span><?php echo esc($incident['created_at']); ?></span></div>
                </div>
                <div style="margin-top: 18px;">
                    <h3>Description</h3>
                    <p class="description"><?php echo nl2br(esc($incident['description'])); ?></p>
                </div>
            </div>

            <div class="card">
                <h2>Evidence Images</h2>
                <div style="margin-top: 16px;">
                    <?php if ($images): ?>
                        <div class="gallery">
                            <?php foreach ($images as $image): ?>
                                <img src="../<?php echo esc($image['file_path']); ?>" alt="Incident evidence" />
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">No images uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="card" style="margin-top: 18px;">
            <h2>Incident History</h2>
            <div style="margin-top: 16px;">
                <?php if ($logs): ?>
                    <div class="timeline">
                        <?php foreach ($logs as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-head">
                                    <span><?php echo esc($log['action']); ?></span>
                                    <span class="muted"><?php echo esc($log['created_at']); ?></span>
                                </div>
                                <div class="muted">By <?php echo esc($log['created_by_username'] ?: 'System'); ?><?php echo $log['created_by_role'] ? ' (' . esc($log['created_by_role']) . ')' : ''; ?></div>
                                <?php if (!empty($log['remarks'])): ?>
                                    <p style="margin: 10px 0 0; line-height: 1.6;"><?php echo nl2br(esc($log['remarks'])); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No incident history entries found.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 500));
    </script>
</body>
</html>