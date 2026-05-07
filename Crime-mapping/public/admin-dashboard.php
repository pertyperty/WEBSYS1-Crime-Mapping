<?php
require __DIR__ . '/guard.php';
requireRole(['admin']);
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard | La Trinidad Crime Mapping</title>
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
                    <div class="brand-title">Admin Dashboard</div>
                    <div class="brand-subtitle">System-wide monitoring</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('dashboard', 'admin'); ?>
        </header>

        <main>
            <section class="hero hero-tight">
                <div class="hero-copy">
                    <p class="eyebrow">Admin control panel</p>
                    <h1>Oversee reports, users, and forensic outputs.</h1>
                    <p class="lead">Track incident volume, generate monthly and area reports, and jump straight into the management pages.</p>
                </div>
            </section>

            <section class="dashboard-kpis">
                <div class="kpi-card"><div class="kpi-label">Total reports</div><div class="kpi-value" id="kpi-total">--</div></div>
                <div class="kpi-card"><div class="kpi-label">Active cases</div><div class="kpi-value" id="kpi-active">--</div></div>
                <div class="kpi-card"><div class="kpi-label">Resolved cases</div><div class="kpi-value" id="kpi-resolved">--</div></div>
                <div class="kpi-card"><div class="kpi-label">High severity alerts</div><div class="kpi-value" id="kpi-high-severity">--</div></div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>Quick Actions</h2>
                    <div class="dashboard-actions">
                        <a class="btn-secondary" href="admin-users.php">Manage users</a>
                        <a class="btn-secondary" href="admin-faq.php">Manage FAQs</a>
                        <a class="btn-secondary" href="admin-incidents.php">View incidents</a>
                        <a class="btn-secondary" href="report-export.php?type=forensics&autoprint=1">Export reports</a>
                    </div>
                </div>

                <div class="dashboard-toolbar">
                    <input type="month" id="report-month" />
                    <button type="button" class="btn-primary" data-report="monthly">Monthly report</button>
                    <button type="button" class="btn-secondary" data-report="area">Area report</button>
                    <button type="button" class="btn-secondary" data-report="crime">Crime report</button>
                    <button type="button" class="btn-secondary" data-report="forensics">Forensics report</button>
                </div>

                <div class="report-panel-card report-output" id="report-output">
                    <p class="muted">Choose a report type to generate a summary.</p>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>Verification Queue</h2>
                    <a class="btn-secondary" href="admin-incidents.php">Open compact cards</a>
                </div>
                <div class="data-table" id="incident-table">
                    <div class="table-row header">
                        <div>Incident</div>
                        <div>Barangay</div>
                        <div>Status</div>
                        <div>Severity</div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div>Admin oversight dashboard</div>
            <div>Keep the system accurate and transparent.</div>
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

            if (data.data.type === 'crime') {
                output.innerHTML = `
                    <div class="summary-chip"><span>Report period</span><strong>${escapeHtml(data.data.month)}</strong></div>
                    <table>
                        <thead><tr><th>Category</th><th>Crime Type</th><th>Count</th></tr></thead>
                        <tbody>${(data.data.crime_stats || []).map((row) => `<tr><td>${escapeHtml(row.category)}</td><td>${escapeHtml(row.type_name)}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}</tbody>
                    </table>
                `;
                return;
            }

            output.innerHTML = `
                <div class="summary-chip"><span>Report period</span><strong>${escapeHtml(data.data.month || '')}</strong></div>
                <table>
                    <thead><tr><th>Incident</th><th>Barangay</th><th>Status</th><th>Images</th></tr></thead>
                    <tbody>${(data.data.active_investigations || []).map((row) => `<tr><td>${escapeHtml(row.title)}</td><td>${escapeHtml(row.barangay_name)}</td><td>${escapeHtml(row.status)}</td><td>${escapeHtml(row.image_count)}</td></tr>`).join('')}</tbody>
                </table>
            `;
        }

        async function loadDashboard() {
            try {
                const response = await fetch(`${apiBase}/admin-incidents.php`);
                const data = await response.json();

                if (!data.ok) {
                    console.error(data.error);
                    return;
                }

                document.getElementById('kpi-total').textContent = data.kpis.total;
                document.getElementById('kpi-active').textContent = data.kpis.active;
                document.getElementById('kpi-resolved').textContent = data.kpis.resolved;
                document.getElementById('kpi-high-severity').textContent = data.kpis.high_severity;

                const table = document.getElementById('incident-table');
                table.innerHTML = '<div class="table-row header"><div>Incident</div><div>Barangay</div><div>Status</div><div>Severity</div></div>';
                data.incidents.slice(0, 8).forEach((incident) => {
                    const row = document.createElement('div');
                    row.className = 'table-row';
                    row.innerHTML = `
                        <div>${escapeHtml(incident.title)}</div>
                        <div>${escapeHtml(incident.barangay)}</div>
                        <div>${escapeHtml(incident.status)}</div>
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