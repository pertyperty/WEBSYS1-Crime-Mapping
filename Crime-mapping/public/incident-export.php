<?php
require __DIR__ . '/guard.php';
requireRole(['admin', 'barangay']);
require __DIR__ . '/../api/db.php';

$incidentId = (int) ($_GET['incident_id'] ?? 0);
if ($incidentId <= 0) {
    http_response_code(400);
    echo 'Missing incident_id.';
    exit;
}

$viewerRole = $_SESSION['role'] ?? null;
$viewerBarangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : null;

$incidentStmt = $pdo->prepare('
    SELECT
        i.incident_id AS id,
        i.barangay_id,
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

if ($viewerRole === 'barangay' && $viewerBarangayId !== (int) $incident['barangay_id']) {
    http_response_code(403);
    echo 'You can only export incidents from your assigned barangay.';
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

function esc($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function label_case(string $value): string {
    return ucwords(str_replace('_', ' ', $value));
}


$caseNumber = 'CR-' . date('Y') . '-' . str_pad($incident['id'], 6, '0', STR_PAD_LEFT);
$reportDate = new DateTime($incident['created_at']);
$occurDate = new DateTime($incident['occurred_at']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Crime Incident Report - Case #<?php echo esc($caseNumber); ?></title>
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet" />
    <style>
        @page {
            size: letter;
            margin: 0.75in;
            @bottom-center {
                content: "Page " counter(page) " of " counter(pages);
                font-family: 'Times New Roman', serif;
                font-size: 10pt;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Times New Roman', 'Noto Serif', serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background: #fff;
        }

        .document {
            max-width: 8.5in;
            margin: 0 auto;
            padding: 0.75in;
            background: #fff;
            min-height: 11in;
            box-shadow: 0 0 0 1px #ccc;
        }

        .header {
            text-align: center;
            border-bottom: 2pt solid #000;
            padding-bottom: 0.3in;
            margin-bottom: 0.3in;
        }

        .header-logo {
            height: 0.6in;
            width: auto;
            margin-bottom: 0.1in;
        }

        .header-title {
            font-weight: bold;
            font-size: 13pt;
            letter-spacing: 0.1em;
        }

        .header-subtitle {
            font-size: 11pt;
            margin-top: 0.05in;
        }

        .report-number {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 0.2in;
            border-bottom: 1pt solid #000;
            padding-bottom: 0.1in;
        }

        .section {
            margin-bottom: 0.25in;
        }

        .section-header {
            font-weight: bold;
            font-size: 11pt;
            background-color: #e8e8e8;
            padding: 0.08in 0.1in;
            border-left: 3pt solid #000;
            margin-bottom: 0.1in;
            text-transform: uppercase;
        }

        .section-content {
            margin-left: 0.15in;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.2in;
            margin-bottom: 0.1in;
            page-break-inside: avoid;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-field {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.04in;
        }

        .form-value {
            font-size: 11pt;
            border-bottom: 1pt solid #000;
            padding-bottom: 0.03in;
            min-height: 0.2in;
        }

        .form-value.multiline {
            min-height: 0.5in;
            border: 1pt solid #000;
            padding: 0.05in 0.05in;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .badge {
            display: inline-block;
            padding: 0.05in 0.1in;
            border: 1pt solid #000;
            font-size: 10pt;
            font-weight: bold;
        }

        .status-pending { background: #fffacd; }
        .status-active { background: #e6f3ff; }
        .status-resolved { background: #e6ffe6; }

        .severity-high { background: #ffe6e6; }
        .severity-medium { background: #fff9e6; }
        .severity-low { background: #e6f9f0; }

        .location-box {
            border: 1pt solid #000;
            padding: 0.1in;
            margin: 0.1in 0;
            background: #f9f9f9;
        }

        .location-line {
            display: grid;
            grid-template-columns: 1.2in 1fr;
            margin-bottom: 0.08in;
            font-size: 11pt;
        }

        .location-line label {
            font-weight: bold;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.15in;
            margin: 0.1in 0;
            page-break-inside: avoid;
        }

        .photo-item {
            border: 1pt solid #000;
            padding: 0.08in;
            page-break-inside: avoid;
        }

        .photo-caption {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 0.05in;
            text-align: center;
        }

        .photo-image {
            width: 100%;
            height: auto;
            max-height: 1.8in;
            display: block;
            border: 1pt solid #ddd;
        }

        .photo-placeholder {
            width: 100%;
            height: 1.8in;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border: 1pt dashed #999;
            font-size: 10pt;
            color: #666;
        }

        .activity-entry {
            border-left: 2pt solid #000;
            padding-left: 0.1in;
            margin-bottom: 0.1in;
            page-break-inside: avoid;
            font-size: 10pt;
        }

        .activity-timestamp {
            font-weight: bold;
            font-size: 9pt;
        }

        .activity-action {
            font-weight: bold;
        }

        .activity-remarks {
            margin-left: 0.15in;
            font-size: 10pt;
            color: #333;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .footer {
            margin-top: 0.3in;
            padding-top: 0.2in;
            border-top: 1pt solid #000;
            font-size: 9pt;
            text-align: center;
            color: #666;
        }

        .signature-block {
            margin-top: 0.4in;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.4in;
            page-break-inside: avoid;
        }

        .signature-line {
            text-align: center;
        }

        .signature-blank {
            border-bottom: 1pt solid #000;
            height: 0.5in;
            margin-bottom: 0.05in;
        }

        .signature-label {
            font-size: 10pt;
            font-weight: bold;
        }

        .page-break {
            page-break-after: always;
        }

        @media print {
            body {
                background: #fff;
                margin: 0;
                padding: 0;
            }

            .document {
                max-width: none;
                box-shadow: none;
                margin: 0;
                padding: 0.75in;
                min-height: auto;
            }

            .page-break {
                page-break-after: always;
            }

            .activity-entry,
            .photo-item,
            .section {
                page-break-inside: avoid;
            }

            a {
                color: #000;
                text-decoration: underline;
            }
        }

        @media screen {
            body {
                background: #e8e8e8;
                padding: 0.5in;
            }

            .document {
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
                margin-bottom: 0.5in;
            }
        }
    </style>
</head>
<body>
    <div class="document">
        <!-- Header -->
        <div class="header">
            <img src="../assets/images/logo/la-trinidad.png" alt="La Trinidad" class="header-logo" />
            <div class="header-title">LA TRINIDAD MUNICIPAL GOVERNMENT</div>
            <div class="header-subtitle">CRIME INCIDENT REPORT</div>
        </div>

        <!-- Case Number -->
        <div class="report-number">
            CASE NO: <?php echo esc($caseNumber); ?>
        </div>

        <!-- Report Information -->
        <div class="section">
            <div class="section-header">Report Information</div>
            <div class="section-content">
                <div class="form-row">
                    <div class="form-field">
                        <div class="form-label">Report Date & Time</div>
                        <div class="form-value"><?php echo esc($reportDate->format('M d, Y \a\t H:i')); ?></div>
                    </div>
                    <div class="form-field">
                        <div class="form-label">Incident Date & Time</div>
                        <div class="form-value"><?php echo esc($occurDate->format('M d, Y \a\t H:i')); ?></div>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-field">
                        <div class="form-label">Barangay / Location</div>
                        <div class="form-value"><?php echo esc($incident['barangay']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incident Classification -->
        <div class="section">
            <div class="section-header">Incident Classification</div>
            <div class="section-content">
                <div class="form-row">
                    <div class="form-field">
                        <div class="form-label">Crime Type</div>
                        <div class="form-value"><?php echo esc($incident['type_name'] ?? $incident['type']); ?></div>
                    </div>
                    <div class="form-field">
                        <div class="form-label">Category</div>
                        <div class="form-value"><?php echo esc($incident['type']); ?></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <div class="form-label">Severity Level</div>
                        <div class="form-value">
                            <span class="badge severity-<?php echo strtolower($incident['severity']); ?>">
                                <?php echo esc(strtoupper(label_case($incident['severity']))); ?>
                            </span>
                        </div>
                    </div>
                    <div class="form-field">
                        <div class="form-label">Current Status</div>
                        <div class="form-value">
                            <span class="badge status-<?php echo strtolower($incident['status']); ?>">
                                <?php echo esc(strtoupper(label_case($incident['status']))); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incident Title & Description -->
        <div class="section">
            <div class="section-header">Incident Summary</div>
            <div class="section-content">
                <div class="form-row full">
                    <div class="form-field">
                        <div class="form-label">Incident Title</div>
                        <div class="form-value"><?php echo esc($incident['title']); ?></div>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-field">
                        <div class="form-label">Detailed Description & Narrative</div>
                        <div class="form-value multiline"><?php echo esc($incident['description']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coordinates & Location Details -->
        <div class="section">
            <div class="section-header">Location Coordinates</div>
            <div class="section-content">
                <div class="location-box">
                    <div class="location-line">
                        <label>Latitude (N):</label>
                        <span><?php echo esc($incident['lat']); ?></span>
                    </div>
                    <div class="location-line">
                        <label>Longitude (E):</label>
                        <span><?php echo esc($incident['lng']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Details -->
        <div class="section">
            <div class="section-header">Report Details</div>
            <div class="section-content">
                <div class="form-row">
                    <div class="form-field">
                        <div class="form-label">Reporting Source</div>
                        <div class="form-value"><?php echo esc(ucfirst($incident['source'] ?? 'Unknown')); ?></div>
                    </div>
                    <div class="form-field">
                        <div class="form-label">Reported By</div>
                        <div class="form-value"><?php echo esc($incident['reported_by'] ?? 'System'); ?></div>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-field">
                        <div class="form-label">Visibility</div>
                        <div class="form-value">
                            <?php echo ($incident['is_public'] ?? false) ? 'Public Record' : 'Restricted / Private'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Evidence & Photographs -->
        <?php if (!empty($images)): ?>
        <div class="section">
            <div class="section-header">Evidence - Photographs</div>
            <div class="section-content">
                <div class="photo-grid">
                    <?php foreach ($images as $idx => $image): ?>
                    <div class="photo-item">
                        <div class="photo-caption">Photo <?php echo ($idx + 1); ?></div>
                        <?php if (file_exists(__DIR__ . '/../' . $image['file_path'])): ?>
                            <img src="<?php echo htmlspecialchars('../' . $image['file_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Evidence" class="photo-image" />
                        <?php else: ?>
                            <div class="photo-placeholder">Image Not Found</div>
                        <?php endif; ?>
                        <div style="font-size: 9pt; color: #666; margin-top: 0.04in; text-align: center;">
                            Uploaded: <?php echo esc(substr($image['uploaded_at'], 0, 10)); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activity Log / Investigation Timeline -->
        <?php if (!empty($logs)): ?>
        <div class="section">
            <div class="section-header">Investigation Timeline & Activity Log</div>
            <div class="section-content">
                <?php foreach ($logs as $log): ?>
                <div class="activity-entry">
                    <div class="activity-timestamp">[<?php echo esc($log['created_at']); ?>]</div>
                    <div class="activity-action"><?php echo esc(label_case($log['action'])); ?></div>
                    <div style="font-size: 10pt; color: #666;">By: <?php echo esc($log['created_by_username'] ?? 'System'); ?></div>
                    <?php if ($log['remarks']): ?>
                    <div class="activity-remarks"><?php echo esc($log['remarks']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Signature Block -->
        <div class="section">
            <div class="signature-block">
                <div class="signature-line">
                    <div class="signature-blank"></div>
                    <div class="signature-label">Reporting Officer / Admin</div>
                </div>
                <div class="signature-line">
                    <div class="signature-blank"></div>
                    <div class="signature-label">Authorized By</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is an official record of the La Trinidad Crime Mapping System.</p>
            <p>Generated: <?php echo date('F d, Y \a\t g:i A'); ?> | Case #<?php echo esc($caseNumber); ?></p>
            <p>Confidential - For Official Use Only</p>
        </div>
    </div>

    <script>
        
        window.addEventListener('load', () => {
            window.print();
        });
    </script>
</body>
</html>
