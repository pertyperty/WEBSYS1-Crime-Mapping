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
$autoPrint = (($_GET['autoprint'] ?? '') === '1');
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
            --paper: #ffffff;
            --bg: #eef2f7;
            --ink: #172033;
            --muted: #5b697f;
            --line: #cad5e0;
            --accent: #142d6b;
            --accent-soft: rgba(20, 45, 107, 0.08);
            --shadow: 0 18px 50px rgba(17, 24, 39, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'IBM Plex Sans', sans-serif;
            color: var(--ink);
            background: linear-gradient(180deg, #f5f8fc 0%, var(--bg) 100%);
        }

        .page {
            max-width: 1080px;
            margin: 0 auto;
            padding: 24px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .topbar .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
        }

        .topbar .brand img {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            object-fit: cover;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            appearance: none;
            border: 1px solid var(--line);
            background: var(--paper);
            color: var(--ink);
            border-radius: 999px;
            padding: 10px 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: var(--shadow);
            cursor: pointer;
        }

        .btn.primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .report-sheet {
            background: var(--paper);
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
            padding: 28px 32px 34px;
        }

        .report-header {
            display: grid;
            grid-template-columns: 1.4fr 0.9fr;
            gap: 24px;
            align-items: start;
        }

        .dept {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .dept img {
            width: 82px;
            height: 82px;
            border-radius: 18px;
            object-fit: cover;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        }

        .dept h1 {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 30px;
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .dept .subtitle {
            margin-top: 6px;
            color: var(--muted);
            font-size: 14px;
        }

        .report-title {
            text-align: right;
        }

        .report-title .main {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .report-title .sub {
            margin-top: 6px;
            font-style: italic;
            color: var(--muted);
            font-size: 14px;
        }

        .divider {
            height: 4px;
            background: var(--accent);
            margin: 18px 0 16px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            align-items: start;
            margin-bottom: 22px;
        }

        .contact-box {
            font-size: 14px;
            line-height: 1.45;
        }

        .contact-line {
            display: grid;
            grid-template-columns: 72px 1fr;
            gap: 10px;
            margin-bottom: 4px;
        }

        .contact-label {
            font-weight: 700;
        }

        .date-box {
            text-align: right;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            align-self: end;
        }

        .scope-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .incident-heading {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: start;
            margin-bottom: 12px;
        }

        .incident-heading h2 {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .incident-meta {
            color: var(--muted);
            font-size: 12px;
            text-align: right;
            white-space: nowrap;
        }

        .report-body {
            display: grid;
            gap: 22px;
        }

        .entry {
            break-inside: avoid;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }

        .entry:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .entry-grid {
            display: grid;
            grid-template-columns: 1.55fr 0.85fr;
            gap: 22px;
            align-items: start;
        }

        .entry p {
            margin: 0 0 12px;
            font-size: 15px;
            line-height: 1.7;
        }

        .entry .leadline {
            margin-bottom: 10px;
            font-weight: 700;
        }

        .entry-photo {
            justify-self: end;
            width: 100%;
            max-width: 260px;
            min-height: 210px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .entry-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .entry-photo .placeholder {
            color: var(--muted);
            font-size: 13px;
            padding: 16px;
            text-align: center;
        }

        .tag-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .tag {
            padding: 6px 10px;
            border-radius: 999px;
            background: #edf2ff;
            color: #223f8f;
            font-size: 12px;
            font-weight: 700;
        }

        .footer-note {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: end;
            margin-top: 24px;
            border-top: 1px solid var(--line);
            padding-top: 16px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .footer-right {
            text-align: right;
        }

        .report-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .report-controls select,
        .report-controls input {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 14px;
            background: #fff;
            font: inherit;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .summary-table th,
        .summary-table td {
            border-bottom: 1px solid var(--line);
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
        }

        .summary-table th {
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-size: 11px;
        }

        .summary-card-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 18px 0;
        }

        .summary-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px;
            background: #fbfcfe;
        }

        .summary-card .label {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .summary-card .value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
        }

        .loading {
            padding: 24px;
            color: var(--muted);
        }

        @media print {
            body { background: #fff; }
            .topbar, .report-controls, .btn { display: none !important; }
            .page { padding: 0; max-width: none; }
            .report-sheet { border: 0; box-shadow: none; padding: 0; }
            .entry, .summary-card { break-inside: avoid; }
        }

        @media (max-width: 900px) {
            .report-header,
            .contact-grid,
            .entry-grid,
            .footer-note,
            .summary-card-row {
                grid-template-columns: 1fr;
            }

            .report-title,
            .incident-meta,
            .footer-right,
            .date-box {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="topbar">
            <div class="brand">
                <img src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                <div>Report Export</div>
            </div>
            <div class="actions">
                <a class="btn" href="<?php echo $viewerRole === 'admin' ? 'admin-dashboard.php' : 'barangay-dashboard.php'; ?>">Back</a>
                <button class="btn primary" type="button" id="print-btn">Print / Save as PDF</button>
            </div>
        </div>

        <section class="report-sheet">
            <div class="report-header">
                <div class="dept">
                    <img src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                    <div>
                        <h1>La Trinidad Crime Mapping</h1>
                        <div class="subtitle">Community safety reporting and incident review</div>
                    </div>
                </div>
                <div class="report-title">
                    <div class="main" id="report-title-text">CRIME REPORT</div>
                    <div class="sub" id="report-subtitle-text">For Internal Distribution</div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="contact-grid">
                <div class="contact-box">
                    <div class="contact-line"><span class="contact-label">Prepared by</span><span><?php echo $viewerRole === 'barangay' ? esc($barangayName ?? 'Barangay Office') : 'Admin Office'; ?></span></div>
                    <div class="contact-line"><span class="contact-label">Scope</span><span id="scope-text"><?php echo $viewerRole === 'barangay' ? esc($barangayName ?? 'Assigned barangay') : 'All barangays'; ?></span></div>
                    <div class="contact-line"><span class="contact-label">Type</span><span id="type-text">Forensics</span></div>
                    <div class="contact-line"><span class="contact-label">Month</span><span id="month-text"><?php echo date('F Y'); ?></span></div>
                </div>
                <div class="date-box">
                    <div id="report-date-text"><?php echo date('F j, Y'); ?></div>
                    <div style="font-size: 13px; color: var(--muted); font-style: italic; margin-top: 6px;">For Immediate Media Distribution</div>
                </div>
            </div>

            <div class="report-controls">
                <label>
                    <span class="sr-only">Report type</span>
                    <select id="report-type">
                        <option value="forensics">Forensics</option>
                        <option value="monthly">Monthly</option>
                        <option value="area">Area</option>
                        <option value="crime">Crime</option>
                    </select>
                </label>
                <label>
                    <span class="sr-only">Month</span>
                    <input type="month" id="report-month" />
                </label>
                <button class="btn primary" type="button" id="refresh-btn">Load report</button>
            </div>

            <div id="report-content" class="report-body">
                <div class="loading">Loading report...</div>
            </div>

            <div class="footer-note">
                <div>
                    Crime report generated from the La Trinidad Crime Mapping system.
                    Data access is restricted to authorized users and scoped to the viewer’s role.
                </div>
                <div class="footer-right">
                    Report generated at <span id="generated-at"><?php echo esc(date('Y-m-d H:i:s')); ?></span>
                </div>
            </div>
        </section>
    </div>

    <script>
        const apiBase = '../api';
        const reportTypeSelect = document.getElementById('report-type');
        const reportMonthInput = document.getElementById('report-month');
        const reportContent = document.getElementById('report-content');
        const reportTitleText = document.getElementById('report-title-text');
        const reportSubtitleText = document.getElementById('report-subtitle-text');
        const scopeText = document.getElementById('scope-text');
        const typeText = document.getElementById('type-text');
        const monthText = document.getElementById('month-text');
        const generatedAt = document.getElementById('generated-at');
        const autoPrint = new URLSearchParams(window.location.search).get('autoprint') === '1';

        const initialType = new URLSearchParams(window.location.search).get('type') || 'forensics';
        const initialMonth = new URLSearchParams(window.location.search).get('month') || new Date().toISOString().slice(0, 7);

        reportTypeSelect.value = initialType;
        reportMonthInput.value = initialMonth;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatLabel(value) {
            return String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
        }

        function renderMonthlyReport(data) {
            const rows = data.incidents_by_status || [];
            const total = rows.reduce((sum, row) => sum + Number(row.count || 0), 0);
            const high = rows.filter((row) => row.severity === 'high').reduce((sum, row) => sum + Number(row.count || 0), 0);
            const resolved = rows.filter((row) => row.status === 'resolved').reduce((sum, row) => sum + Number(row.count || 0), 0);
            const pending = rows.filter((row) => row.status === 'pending').reduce((sum, row) => sum + Number(row.count || 0), 0);

            reportContent.innerHTML = `
                <div class="summary-card-row">
                    <div class="summary-card"><div class="label">Total incidents</div><div class="value">${escapeHtml(total)}</div></div>
                    <div class="summary-card"><div class="label">High severity</div><div class="value">${escapeHtml(high)}</div></div>
                    <div class="summary-card"><div class="label">Resolved</div><div class="value">${escapeHtml(resolved)}</div></div>
                    <div class="summary-card"><div class="label">Pending</div><div class="value">${escapeHtml(pending)}</div></div>
                </div>
                <table class="summary-table">
                    <thead><tr><th>Status</th><th>Severity</th><th>Count</th></tr></thead>
                    <tbody>
                        ${rows.map((row) => `<tr><td>${escapeHtml(formatLabel(row.status))}</td><td>${escapeHtml(formatLabel(row.severity))}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}
                    </tbody>
                </table>
            `;
        }

        function renderAreaReport(data) {
            const rows = data.barangay_stats || [];
            reportContent.innerHTML = `
                <div class="summary-card-row">
                    <div class="summary-card"><div class="label">Barangay scope</div><div class="value">${escapeHtml(rows.length)}</div></div>
                    <div class="summary-card"><div class="label">Report period</div><div class="value" style="font-size: 18px;">${escapeHtml(data.month)}</div></div>
                    <div class="summary-card"><div class="label">Highest volume</div><div class="value" style="font-size: 18px;">${escapeHtml(rows[0]?.barangay_name || 'N/A')}</div></div>
                    <div class="summary-card"><div class="label">Top count</div><div class="value">${escapeHtml(rows[0]?.count || 0)}</div></div>
                </div>
                <table class="summary-table">
                    <thead><tr><th>Barangay</th><th>Incidents</th><th>High severity %</th></tr></thead>
                    <tbody>
                        ${rows.map((row) => `<tr><td>${escapeHtml(row.barangay_name)}</td><td>${escapeHtml(row.count)}</td><td>${escapeHtml(Number(row.high_severity_pct || 0).toFixed(1))}%</td></tr>`).join('')}
                    </tbody>
                </table>
            `;
        }

        function renderCrimeReport(data) {
            const rows = data.crime_stats || [];
            reportContent.innerHTML = `
                <table class="summary-table">
                    <thead><tr><th>Category</th><th>Crime Type</th><th>Count</th></tr></thead>
                    <tbody>
                        ${rows.map((row) => `<tr><td>${escapeHtml(formatLabel(row.category))}</td><td>${escapeHtml(row.type_name)}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}
                    </tbody>
                </table>
            `;
        }

        async function fetchFirstImage(incidentId) {
            try {
                const response = await fetch(`${apiBase}/incident-images.php?incident_id=${encodeURIComponent(incidentId)}`);
                const payload = await response.json();
                if (!payload.ok || !payload.images || !payload.images.length) {
                    return null;
                }
                return payload.images[0].file_path || null;
            } catch (error) {
                return null;
            }
        }

        async function renderForensicsReport(data) {
            const incidents = data.active_investigations || [];
            const incidentsWithImages = await Promise.all(incidents.map(async (incident) => ({
                ...incident,
                first_image: await fetchFirstImage(incident.incident_id)
            })));

            if (incidentsWithImages.length === 0) {
                reportContent.innerHTML = '<div class="loading">No active investigations for the selected period.</div>';
                return;
            }

            reportContent.innerHTML = incidentsWithImages.map((incident) => `
                <article class="entry">
                    <div class="incident-heading">
                        <h2>${escapeHtml(incident.title)}</h2>
                        <div class="incident-meta">
                            ${escapeHtml(formatLabel(incident.severity))}<br />
                            ${escapeHtml(incident.status ? formatLabel(incident.status) : 'Open')}
                        </div>
                    </div>
                    <div class="entry-grid">
                        <div>
                            <p class="leadline">${escapeHtml(incident.occurred_at)} • ${escapeHtml(incident.barangay_name)}</p>
                            <p>${escapeHtml(incident.description)}</p>
                            <p><strong>Type:</strong> ${escapeHtml(incident.type_name)}<br />
                            <strong>Image count:</strong> ${escapeHtml(incident.image_count || 0)}<br />
                            <strong>Validation count:</strong> ${escapeHtml(incident.validation_count || 0)}</p>
                            <div class="tag-row">
                                <span class="tag">Incident #${escapeHtml(incident.incident_id)}</span>
                                <span class="tag">${escapeHtml(formatLabel(incident.status))}</span>
                                <span class="tag">${escapeHtml(incident.severity)} severity</span>
                            </div>
                        </div>
                        <div class="entry-photo">
                            ${incident.first_image ? `<img src="../${escapeHtml(incident.first_image)}" alt="Incident evidence" />` : '<div class="placeholder">No image available</div>'}
                        </div>
                    </div>
                </article>
            `).join('');
        }

        async function loadReport() {
            const type = reportTypeSelect.value || 'forensics';
            const month = reportMonthInput.value || new Date().toISOString().slice(0, 7);

            reportTitleText.textContent = 'CRIME REPORT';
            reportSubtitleText.textContent = type === 'forensics' ? 'For Immediate Media Distribution' : 'For Internal Distribution';
            typeText.textContent = formatLabel(type);
            monthText.textContent = new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            reportContent.innerHTML = '<div class="loading">Loading report...</div>';

            try {
                const response = await fetch(`${apiBase}/reports-generate.php?type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}`);
                const payload = await response.json();
                if (!payload.ok) {
                    reportContent.innerHTML = `<div class="loading">${escapeHtml(payload.error || 'Failed to generate the report.')}</div>`;
                    return;
                }

                const data = payload.data || {};
                if (type === 'monthly') {
                    renderMonthlyReport(data);
                } else if (type === 'area') {
                    renderAreaReport(data);
                } else if (type === 'crime') {
                    renderCrimeReport(data);
                } else {
                    await renderForensicsReport(data);
                }

                generatedAt.textContent = payload.generated_at || new Date().toISOString().replace('T', ' ').slice(0, 19);
                if (autoPrint) {
                    setTimeout(() => window.print(), 400);
                }
            } catch (error) {
                console.error('Failed to load report', error);
                reportContent.innerHTML = '<div class="loading">Failed to load the report.</div>';
            }
        }

        document.getElementById('refresh-btn').addEventListener('click', loadReport);
        document.getElementById('print-btn').addEventListener('click', () => window.print());
        reportTypeSelect.addEventListener('change', loadReport);
        reportMonthInput.addEventListener('change', loadReport);

        loadReport();
    </script>
        const autoPrint = <?php echo json_encode($autoPrint); ?>;
        if (autoPrint) {
            window.addEventListener('load', () => setTimeout(() => window.print(), 450));
        }

</body>
</html>
