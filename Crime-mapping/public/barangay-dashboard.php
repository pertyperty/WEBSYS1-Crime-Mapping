<?php
require __DIR__ . '/guard.php';
requireRole(['barangay']);

require __DIR__ . '/../api/db.php';
$barangayName = null;
if (isset($_SESSION['barangay_id'])) {
    $stmt = $pdo->prepare('SELECT barangay_name FROM barangays WHERE barangay_id = :id');
    $stmt->execute([':id' => $_SESSION['barangay_id']]);
    $result = $stmt->fetch();
    $barangayName = $result ? $result['barangay_name'] : null;
}
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Barangay Dashboard | La Trinidad Crime Mapping</title>
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="brand">
                <img class="brand-logo" src="../assets/images/logo/la-trinidad.png" alt="La Trinidad" />
                <div>
                    <div class="brand-title">Barangay Dashboard</div>
                    <div class="brand-subtitle">Incident verification and updates</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('dashboard', 'barangay'); ?>
        </header>

        <main>
            <section class="hero hero-tight">
                <div class="hero-copy">
                    <p class="eyebrow">Barangay overview</p>
                    <h1>Monitor reports in <?php echo htmlspecialchars($barangayName ?? 'your area'); ?>.</h1>
                    <p class="lead">Review new submissions, add incidents directly, and generate monthly or area-based reports for local response work.</p>
                </div>
            </section>

            <section class="summary-strip">
                <div class="summary-chip"><span>Pending reports</span><strong id="kpi-pending">--</strong></div>
                <div class="summary-chip"><span>Active cases</span><strong id="kpi-active">--</strong></div>
                <div class="summary-chip"><span>Resolved this month</span><strong id="kpi-resolved">--</strong></div>
                <div class="summary-chip"><span>High risk areas</span><strong id="kpi-high-risk">--</strong></div>
            </section>
            <br>
            <section class="panel">
                <div class="panel-header">
                    <h2>Quick Actions</h2>
                    <div class="dashboard-actions">
                        <a class="btn-primary" href="barangay-add-incident.php">Add incident</a>
                        <a class="btn-secondary" href="barangay-incidents.php">Review incidents</a>
                        <a class="btn-secondary" href="report-export.php?type=forensics&autoprint=1">Export reports</a>
                    </div>
                </div>
            
                <div class="dashboard-toolbar">
                    <input type="month" id="report-month" />
                    <button type="button" class="btn-primary" data-report="monthly">Monthly report</button>
                    <button type="button" class="btn-secondary" data-report="area">Area report</button>
                    <button type="button" class="btn-secondary" data-report="crime">Crime report</button>
                </div>

                <div class="report-panel-card report-output" id="report-output">
                    <p class="muted">Choose a report type to generate a summary.</p>
                </div>
            </section>
            <br>
            <section class="panel">
                <div class="panel-header">
                    <h2>Report Queue</h2>
                    <a class="btn-secondary" href="barangay-add-incident.php">Enter new incident</a>
                </div>
                <div class="data-table" id="incident-table">
                    <div class="table-row header">
                        <div>Incident</div>
                        <div>Status</div>
                        <div>Date</div>
                        <div>Severity</div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div>Barangay operations dashboard</div>
            <div>Update reports promptly to keep the community informed.</div>
        </footer>
    </div>

    <script>
        const apiBase = '../api';
        const reportMonthInput = document.getElementById('report-month');
        reportMonthInput.value = new Date().toISOString().slice(0, 7);

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderReport(data) {
            const output = document.getElementById('report-output');
            if (!data || !data.ok) {
                output.innerHTML = '<p class="muted">Failed to generate the report.</p>';
                return;
            }

            if (data.data.type === 'monthly') {
                output.innerHTML = `
                    <div class="summary-chip"><span>Report period</span><strong>${escapeHtml(data.data.month)}</strong></div>
                    <table>
                        <thead><tr><th>Status</th><th>Severity</th><th>Count</th></tr></thead>
                        <tbody>${(data.data.incidents_by_status || []).map((row) => `<tr><td>${escapeHtml(row.status)}</td><td>${escapeHtml(row.severity)}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}</tbody>
                    </table>
                `;
                return;
            }

            if (data.data.type === 'area') {
                output.innerHTML = `
                    <div class="summary-chip"><span>Report period</span><strong>${escapeHtml(data.data.month)}</strong></div>
                    <table>
                        <thead><tr><th>Barangay</th><th>Count</th><th>High severity %</th></tr></thead>
                        <tbody>${(data.data.barangay_stats || []).map((row) => `<tr><td>${escapeHtml(row.barangay_name)}</td><td>${escapeHtml(row.count)}</td><td>${escapeHtml(Number(row.high_severity_pct || 0).toFixed(1))}%</td></tr>`).join('')}</tbody>
                    </table>
                `;
                return;
            }

            output.innerHTML = `
                <div class="summary-chip"><span>Report period</span><strong>${escapeHtml(data.data.month)}</strong></div>
                <table>
                    <thead><tr><th>Category</th><th>Crime Type</th><th>Count</th></tr></thead>
                    <tbody>${(data.data.crime_stats || []).map((row) => `<tr><td>${escapeHtml(row.category)}</td><td>${escapeHtml(row.type_name)}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}</tbody>
                </table>
            `;
        }

        async function loadDashboard() {
            try {
                const response = await fetch(`${apiBase}/barangay-incidents.php`);
                const data = await response.json();

                if (!data.ok) {
                    console.error(data.error);
                    return;
                }

                document.getElementById('kpi-pending').textContent = data.kpis.pending;
                document.getElementById('kpi-active').textContent = data.kpis.active;
                document.getElementById('kpi-resolved').textContent = data.kpis.resolved_month;
                document.getElementById('kpi-high-risk').textContent = data.kpis.high_risk;

                const table = document.getElementById('incident-table');
                table.innerHTML = '<div class="table-row header"><div>Incident</div><div>Status</div><div>Date</div><div>Severity</div></div>';
                data.incidents.slice(0, 8).forEach((incident) => {
                    const row = document.createElement('div');
                    row.className = 'table-row';
                    row.setAttribute('data-id', incident.id || incident.incident_id || '');
                    row.setAttribute('data-lat', incident.lat || incident.latitude || '');
                    row.setAttribute('data-lng', incident.lng || incident.longitude || '');
                    row.innerHTML = `
                        <div>${escapeHtml(incident.title)}</div>
                        <div>${escapeHtml(incident.status)}</div>
                        <div>${escapeHtml(incident.date)}</div>
                        <div>${escapeHtml(incident.severity)}</div>
                    `;
                    table.appendChild(row);
                });

                if (data.incidents.length === 0) {
                    const row = document.createElement('div');
                    row.className = 'table-row';
                    row.innerHTML = '<div>No reports yet.</div>';
                    table.appendChild(row);
                }
            } catch (error) {
                console.error('Failed to load dashboard:', error);
            }
        }

        async function generateReport(type) {
            const month = reportMonthInput.value || new Date().toISOString().slice(0, 7);
            const response = await fetch(`${apiBase}/reports-generate.php?type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}`);
            const data = await response.json();
            renderReport(data);
        }

        document.querySelectorAll('[data-report]').forEach((button) => {
            button.addEventListener('click', () => generateReport(button.getAttribute('data-report')));
        });

        loadDashboard();
    </script>
</body>
</html>