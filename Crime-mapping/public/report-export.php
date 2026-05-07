<?php
require __DIR__ . '/guard.php';
requireRole(['admin', 'barangay']);

require __DIR__ . '/../api/db.php';

$viewerRole = $_SESSION['role'] ?? null;
$barangayName = null;
if ($viewerRole === 'barangay' && isset($_SESSION['barangay_id'])) {
    $stmt = $pdo->prepare('SELECT barangay_name FROM barangays WHERE barangay_id = :id');
    $stmt->execute([':id' => $_SESSION['barangay_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $barangayName = $result ? $result['barangay_name'] : null;
}

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Crime Report Export | La Trinidad Crime Mapping</title>
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
    <style>
        :root {
            --paper: #ffffff;
            --bg: #eff3f8;
            --ink: #152033;
            --muted: #5e6c84;
            --line: #d2dbe6;
            --accent: #12357a;
            --soft: rgba(18, 53, 122, 0.08);
            --shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'IBM Plex Sans', sans-serif;
            color: var(--ink);
            background: linear-gradient(180deg, #f7f9fc 0%, var(--bg) 100%);
        }

        .export-page {
            max-width: 1120px;
            margin: 0 auto;
            padding: 24px;
        }

        .export-topbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .export-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
        }

        .export-brand img {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            object-fit: cover;
        }

        .export-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .export-btn {
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

        .export-btn.primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .sheet {
            background: var(--paper);
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
            padding: 28px 32px 34px;
        }

        .header {
            display: grid;
            grid-template-columns: 1.35fr 0.95fr;
            gap: 24px;
            align-items: start;
        }

        .header-left {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .header-left img {
            width: 82px;
            height: 82px;
            border-radius: 18px;
            object-fit: cover;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        }

        .header-left h1 {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 30px;
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .header-left p {
            margin: 6px 0 0;
            color: var(--muted);
        }

        .header-right {
            text-align: right;
        }

        .header-right .main {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .header-right .sub {
            margin-top: 6px;
            font-style: italic;
            color: var(--muted);
            font-size: 14px;
        }

        .rule {
            height: 4px;
            background: var(--accent);
            margin: 18px 0 16px;
        }

        .meta-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 22px;
            align-items: end;
        }

        .meta-box {
            display: grid;
            gap: 6px;
            font-size: 14px;
            line-height: 1.45;
        }

        .meta-line {
            display: grid;
            grid-template-columns: 72px 1fr;
            gap: 10px;
        }

        .meta-label {
            font-weight: 700;
        }

        .meta-right {
            text-align: right;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
        }

        .subtitle {
            font-size: 13px;
            color: var(--muted);
            font-style: italic;
            margin-top: 6px;
        }

        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .controls select,
        .controls input {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 14px;
            background: #fff;
            font: inherit;
        }

        .summary-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 16px 0 20px;
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

        .content {
            display: grid;
            gap: 22px;
        }

        .section-title {
            margin: 0 0 10px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
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

        .entry {
            border-bottom: 1px solid var(--line);
            padding-bottom: 20px;
            break-inside: avoid;
        }

        .entry:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .entry-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: start;
            margin-bottom: 12px;
        }

        .entry-head h2 {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .entry-meta {
            color: var(--muted);
            font-size: 12px;
            text-align: right;
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

        .entry-photo {
            justify-self: end;
            width: 100%;
            max-width: 260px;
            min-height: 210px;
            border-radius: 14px;
            border: 1px solid var(--line);
            overflow: hidden;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
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

        .business-report {
            display: grid;
            gap: 16px;
        }

        .business-case {
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            break-inside: avoid;
        }

        .case-kicker {
            margin: 0 0 6px;
            color: var(--accent);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .case-title {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            line-height: 1.15;
        }

        .case-summary {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 14px;
        }

        .case-layout {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 16px;
            margin-top: 16px;
            align-items: start;
        }

        .case-section {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            background: #fff;
        }

        .case-section h3 {
            margin: 0 0 12px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
        }

        .case-details {
            display: grid;
            gap: 10px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 12px;
            font-size: 14px;
            line-height: 1.5;
        }

        .detail-row strong {
            color: var(--muted);
        }

        .evidence-block {
            display: grid;
            gap: 12px;
        }

        .evidence-frame {
            min-height: 260px;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: #f8fafc;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .evidence-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .evidence-placeholder {
            display: grid;
            place-items: center;
            gap: 10px;
            padding: 20px;
            text-align: center;
            color: var(--muted);
        }

        .evidence-placeholder svg {
            width: 88px;
            height: 88px;
            color: var(--accent);
        }

        .chronology {
            display: grid;
            gap: 10px;
        }

        .chronology-item {
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fbfdff;
        }

        .chronology-item .top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .chronology-item .top strong {
            color: var(--text);
        }

        .chronology-item .top span {
            color: var(--muted);
        }

        .chronology-item p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: var(--text);
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

        .loading {
            padding: 24px;
            color: var(--muted);
        }

        .footer {
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

        @media print {
            body { background: #fff; }
            .export-topbar, .controls, .export-btn { display: none !important; }
            .export-page { padding: 0; max-width: none; }
            .sheet { border: 0; box-shadow: none; padding: 0; }
            .entry, .summary-card { break-inside: avoid; }
        }

        @media (max-width: 920px) {
            .header,
            .meta-row,
            .entry-grid,
            .footer,
            .summary-strip {
                grid-template-columns: 1fr;
            }

            .case-layout,
            .detail-row {
                grid-template-columns: 1fr;
            }

            .header-right,
            .meta-right,
            .footer-right {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="export-page">
        <div class="export-topbar">
            <div class="export-brand">
                <img src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                <div>Report Export</div>
            </div>
            <div class="export-actions">
                <a class="export-btn" href="<?php echo $viewerRole === 'admin' ? 'admin-dashboard.php' : 'barangay-dashboard.php'; ?>">Back</a>
                <a class="export-btn" href="incidents-export-csv.php">Download CSV</a>
                <button class="export-btn primary" id="print-btn" type="button">Print / Save as PDF</button>
            </div>
        </div>

        <section class="sheet">
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                    <div>
                        <h1>La Trinidad Crime Mapping</h1>
                        <p>Official incident and monitoring report</p>
                    </div>
                </div>
                <div class="header-right">
                        <div class="main" id="report-title">CASE INVESTIGATION REPORT</div>
                        <div class="sub" id="report-subtitle">Business-style incident brief for internal review</div>
                </div>
            </div>

            <div class="rule"></div>

            <div class="meta-row">
                <div class="meta-box">
                    <div class="meta-line"><span class="meta-label">Prepared by</span><span><?php echo $viewerRole === 'barangay' ? esc($barangayName ?? 'Barangay Office') : 'Admin Office'; ?></span></div>
                    <div class="meta-line"><span class="meta-label">Scope</span><span id="scope-label"><?php echo $viewerRole === 'barangay' ? esc($barangayName ?? 'Assigned barangay') : 'All barangays'; ?></span></div>
                    <div class="meta-line"><span class="meta-label">Type</span><span id="type-label">Forensics</span></div>
                    <div class="meta-line"><span class="meta-label">Month</span><span id="month-label"><?php echo date('F Y'); ?></span></div>
                </div>
                <div class="meta-right">
                    <div id="generated-date"><?php echo esc(date('F j, Y')); ?></div>
                    <div class="subtitle">For internal distribution</div>
                </div>
            </div>

            <div class="controls">
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
                <button class="btn primary" type="button" id="load-report-btn">Load report</button>
            </div>

            <div id="report-content" class="content">
                <div class="loading">Loading report...</div>
            </div>

            <div class="footer">
                <div>
                    Data is limited to the authenticated viewer’s scope. Barangay users can only generate and export incidents within their assigned area.
                </div>
                <div class="footer-right">
                    Generated at <span id="generated-at"><?php echo esc(date('Y-m-d H:i:s')); ?></span>
                </div>
            </div>
        </section>
    </div>

    <script>
        const apiBase = '../api';
        const reportType = document.getElementById('report-type');
        const reportMonth = document.getElementById('report-month');
        const reportContent = document.getElementById('report-content');
        const reportTitle = document.getElementById('report-title');
        const reportSubtitle = document.getElementById('report-subtitle');
        const scopeLabel = document.getElementById('scope-label');
        const typeLabel = document.getElementById('type-label');
        const monthLabel = document.getElementById('month-label');
        const generatedDate = document.getElementById('generated-date');
        const autoPrint = new URLSearchParams(window.location.search).get('autoprint') === '1';
        let autoPrintScheduled = false;

        const query = new URLSearchParams(window.location.search);
        reportType.value = query.get('type') || 'forensics';
        reportMonth.value = query.get('month') || new Date().toISOString().slice(0, 7);

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function humanize(value) {
            return String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
        }

        function formatLabel(value) {
            return humanize(value || 'N/A');
        }

        function placeholderImage() {
            const svg = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" role="img" aria-labelledby="title desc">
                    <title id="title">No incident image available</title>
                    <desc id="desc">Placeholder image for incidents without evidence photos.</desc>
                    <defs>
                        <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
                            <stop offset="0%" stop-color="#edf2ff"/>
                            <stop offset="100%" stop-color="#f8fafc"/>
                        </linearGradient>
                    </defs>
                    <rect width="800" height="600" rx="36" fill="url(#g)"/>
                    <rect x="145" y="115" width="510" height="310" rx="28" fill="#ffffff" stroke="#cfd8e5" stroke-width="10"/>
                    <circle cx="300" cy="240" r="44" fill="#dce7f7"/>
                    <path d="M240 370l88-96 76 66 58-53 78 83H240z" fill="#dbe6f5"/>
                    <rect x="230" y="456" width="340" height="24" rx="12" fill="#c7d4e8"/>
                    <rect x="290" y="494" width="220" height="18" rx="9" fill="#d7e0ee"/>
                </svg>`;
            return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
        }

        function escAttr(value) {
            return escapeHtml(value).replace(/\n/g, ' ');
        }

        function renderDetailRows(rows) {
            return rows.map((row) => `
                <div class="detail-row">
                    <strong>${escapeHtml(row.label)}</strong>
                    <span>${escapeHtml(row.value)}</span>
                </div>
            `).join('');
        }

        function renderMonthly(data) {
            const rows = data.incidents_by_status || [];
            const total = rows.reduce((sum, row) => sum + Number(row.count || 0), 0);
            const resolved = rows.filter((row) => row.status === 'resolved').reduce((sum, row) => sum + Number(row.count || 0), 0);
            const pending = rows.filter((row) => row.status === 'pending').reduce((sum, row) => sum + Number(row.count || 0), 0);
            const high = rows.filter((row) => row.severity === 'high').reduce((sum, row) => sum + Number(row.count || 0), 0);

            reportContent.innerHTML = `
                <div class="summary-strip">
                    <div class="summary-card"><div class="label">Total incidents</div><div class="value">${escapeHtml(total)}</div></div>
                    <div class="summary-card"><div class="label">Resolved</div><div class="value">${escapeHtml(resolved)}</div></div>
                    <div class="summary-card"><div class="label">Pending</div><div class="value">${escapeHtml(pending)}</div></div>
                    <div class="summary-card"><div class="label">High severity</div><div class="value">${escapeHtml(high)}</div></div>
                </div>
                <table class="summary-table">
                    <thead><tr><th>Status</th><th>Severity</th><th>Count</th></tr></thead>
                    <tbody>
                        ${rows.map((row) => `<tr><td>${escapeHtml(humanize(row.status))}</td><td>${escapeHtml(humanize(row.severity))}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}
                    </tbody>
                </table>
            `;
        }

        function renderArea(data) {
            const rows = data.barangay_stats || [];
            reportContent.innerHTML = `
                <div class="summary-strip">
                    <div class="summary-card"><div class="label">Barangays listed</div><div class="value">${escapeHtml(rows.length)}</div></div>
                    <div class="summary-card"><div class="label">Report period</div><div class="value" style="font-size: 18px;">${escapeHtml(data.month)}</div></div>
                    <div class="summary-card"><div class="label">Top barangay</div><div class="value" style="font-size: 18px;">${escapeHtml(rows[0]?.barangay_name || 'N/A')}</div></div>
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

        function renderCrime(data) {
            const rows = data.crime_stats || [];
            reportContent.innerHTML = `
                <div class="summary-strip">
                    <div class="summary-card"><div class="label">Crime categories</div><div class="value">${escapeHtml(rows.length)}</div></div>
                    <div class="summary-card"><div class="label">Report period</div><div class="value" style="font-size: 18px;">${escapeHtml(data.month)}</div></div>
                    <div class="summary-card"><div class="label">Lead category</div><div class="value" style="font-size: 18px;">${escapeHtml(rows[0]?.category ? humanize(rows[0].category) : 'N/A')}</div></div>
                    <div class="summary-card"><div class="label">Lead total</div><div class="value">${escapeHtml(rows[0]?.count || 0)}</div></div>
                </div>
                <table class="summary-table">
                    <thead><tr><th>Category</th><th>Crime Type</th><th>Count</th></tr></thead>
                    <tbody>
                        ${rows.map((row) => `<tr><td>${escapeHtml(humanize(row.category))}</td><td>${escapeHtml(row.type_name)}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}
                    </tbody>
                </table>
            `;
        }

        async function fetchIncidentDetail(incidentId) {
            try {
                const response = await fetch(`${apiBase}/incident-detail.php?incident_id=${encodeURIComponent(incidentId)}`);
                const payload = await response.json();
                if (!payload.ok) {
                    return null;
                }
                return payload;
            } catch (error) {
                return null;
            }
        }

        async function renderForensics(data) {
            const incidents = data.active_investigations || [];
            const incidentRows = await Promise.all(incidents.map(async (incident) => {
                const detail = await fetchIncidentDetail(incident.incident_id);
                const incidentDetail = detail?.incident || incident;
                const detailImages = detail?.images || [];
                const detailLogs = detail?.logs || [];

                return {
                    ...incident,
                    ...incidentDetail,
                    detailImages,
                    detailLogs,
                    first_image: detailImages[0]?.file_path || null
                };
            }));

            if (!incidentRows.length) {
                reportContent.innerHTML = '<div class="loading">No active investigations for the selected period.</div>';
                return;
            }

            reportContent.innerHTML = `
                <div class="business-report">
                    ${incidentRows.map((incident) => {
                        const evidenceCount = incident.detailImages?.length ?? incident.image_count ?? 0;
                        const logCount = incident.detailLogs?.length ?? incident.validation_count ?? 0;
                        const imageSrc = incident.first_image ? `../${incident.first_image}` : placeholderImage();

                        return `
                            <article class="business-case">
                                <p class="case-kicker">Incident case brief</p>
                                <h2 class="case-title">${escapeHtml(incident.title || 'Untitled incident')}</h2>
                                <p class="case-summary">${escapeHtml(incident.description || 'No incident description was provided.')}</p>

                                <div class="case-layout">
                                    <div class="case-section">
                                        <h3>Case details</h3>
                                        <div class="case-details">
                                            ${renderDetailRows([
                                                { label: 'Case number', value: `INC-${String(incident.incident_id).padStart(5, '0')}` },
                                                { label: 'Reported date', value: incident.occurred_at || 'N/A' },
                                                { label: 'Barangay', value: incident.barangay_name || incident.barangay || 'N/A' },
                                                { label: 'Crime type', value: incident.type_name || 'N/A' },
                                                { label: 'Severity', value: formatLabel(incident.severity) },
                                                { label: 'Status', value: formatLabel(incident.status || 'open') },
                                                { label: 'Source', value: formatLabel(incident.source || 'reported') },
                                                { label: 'Image count', value: String(evidenceCount) },
                                                { label: 'Log entries', value: String(logCount) }
                                            ])}
                                        </div>
                                        <div class="tag-row">
                                            <span class="tag">Incident #${escapeHtml(incident.incident_id)}</span>
                                            <span class="tag">${escapeHtml(formatLabel(incident.status || 'open'))}</span>
                                            <span class="tag">${escapeHtml(formatLabel(incident.severity))}</span>
                                        </div>
                                    </div>

                                    <div class="case-section evidence-block">
                                        <h3>Evidence image</h3>
                                        <div class="evidence-frame">
                                            <img src="${escapeHtml(imageSrc)}" alt="${escAttr(incident.title || 'Incident evidence')}" />
                                        </div>
                                    </div>
                                </div>

                                <div class="case-layout">
                                    <div class="case-section">
                                        <h3>Business narrative</h3>
                                        <p class="case-summary" style="margin-top: 0; color: var(--text);">This report documents the incident case for internal review, operational tracking, and coordination with the appropriate barangay office. The account below preserves the incident description, classification, and follow-up status in a business-report format.</p>
                                        <div class="case-details" style="margin-top: 14px;">
                                            ${renderDetailRows([
                                                { label: 'Narrative summary', value: incident.description || 'No narrative provided.' },
                                                { label: 'Operational note', value: `Case assigned to ${incident.barangay_name || incident.barangay || 'the responsible barangay'} with current status ${formatLabel(incident.status || 'open')}.` },
                                                { label: 'Investigation note', value: `The record contains ${String(logCount)} log entry/entries and ${String(evidenceCount)} evidence image(s).` }
                                            ])}
                                        </div>
                                    </div>

                                    <div class="case-section">
                                        <h3>Case chronology</h3>
                                        <div class="chronology">
                                            ${(incident.detailLogs || []).length
                                                ? incident.detailLogs.map((log) => `
                                                    <div class="chronology-item">
                                                        <div class="top">
                                                            <strong>${escapeHtml(formatLabel(log.action || 'update'))}</strong>
                                                            <span>${escapeHtml(log.created_at || '')}</span>
                                                        </div>
                                                        <p>${escapeHtml(log.remarks || 'No remarks were recorded.')}</p>
                                                        <div class="subtitle" style="margin-top: 8px; font-style: normal;">${escapeHtml(log.created_by_username || log.created_by_role || 'System')}</div>
                                                    </div>
                                                `).join('')
                                                : '<div class="loading">No chronology entries available for this case.</div>'}
                                        </div>
                                    </div>
                                </div>
                            </article>
                        `;
                    }).join('')}
                </div>
            `;
        }

        async function loadReport() {
            const type = reportType.value || 'forensics';
            const month = reportMonth.value || new Date().toISOString().slice(0, 7);

            typeLabel.textContent = humanize(type);
            monthLabel.textContent = new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            reportTitle.textContent = type === 'forensics' ? 'CASE INVESTIGATION REPORT' : 'CRIME REPORT';
            reportSubtitle.textContent = type === 'forensics' ? 'Business-style incident brief for internal review' : 'For internal distribution';
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
                    renderMonthly(data);
                } else if (type === 'area') {
                    renderArea(data);
                } else if (type === 'crime') {
                    renderCrime(data);
                } else {
                    await renderForensics(data);
                }

                generatedDate.textContent = payload.generated_at || new Date().toISOString().replace('T', ' ').slice(0, 19);
                if (autoPrint && !autoPrintScheduled) {
                    autoPrintScheduled = true;
                    setTimeout(() => window.print(), 450);
                }
            } catch (error) {
                console.error('Failed to load report', error);
                reportContent.innerHTML = '<div class="loading">Failed to load report.</div>';
            }
        }

        document.getElementById('load-report-btn').addEventListener('click', loadReport);
        document.getElementById('print-btn').addEventListener('click', () => window.print());
        reportType.addEventListener('change', loadReport);
        reportMonth.addEventListener('change', loadReport);

        loadReport();
    </script>
</body>
</html>
