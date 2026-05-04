<?php
require __DIR__ . '/guard.php';
requireRole(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard | La Trinidad Crime Mapping</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="brand">
                <span class="brand-mark"></span>
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
                    <h1>Oversee reports, users, and system analytics.</h1>
                    <p class="lead">Review all incidents, validate community reports, and manage categories for the entire municipality.</p>
                </div>
            </section>

            <section class="dashboard-kpis">
                <div class="kpi-card">
                    <div class="kpi-label">Total reports</div>
                    <div class="kpi-value" id="kpi-total">--</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Active cases</div>
                    <div class="kpi-value" id="kpi-active">--</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Resolved cases</div>
                    <div class="kpi-value" id="kpi-resolved">--</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">High severity alerts</div>
                    <div class="kpi-value" id="kpi-high-severity">--</div>
                </div>
            </section>
            <br>

            <section class="panel">
                <div class="panel-header">
                    <h2>Verification Queue</h2>
                    <button class="btn-secondary">Manage users</button>
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
</body>
</html>

    <script>
        function escapeHtml(value) {
            return String(value ?? "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/\"/g, "&quot;")
                .replace(/'/g, "&#39;");
        }

        const statusLabels = {
            pending: 'Pending',
            under_investigation: 'Under investigation',
            action_taken: 'Action taken',
            resolved: 'Resolved',
            dismissed: 'Dismissed'
        };

        const severityLabels = {
            low: 'Low',
            medium: 'Medium',
            high: 'High'
        };

        async function loadDashboard() {
            try {
                const response = await fetch('../api/admin-incidents.php');
                const data = await response.json();

                if (!data.ok) {
                    console.error(data.error);
                    return;
                }

                // Update KPIs
                document.getElementById('kpi-total').textContent = data.kpis.total;
                document.getElementById('kpi-active').textContent = data.kpis.active;
                document.getElementById('kpi-resolved').textContent = data.kpis.resolved;
                document.getElementById('kpi-high-severity').textContent = data.kpis.high_severity;

                // Populate table
                const table = document.getElementById('incident-table');
                data.incidents.slice(0, 10).forEach(incident => {
                    const safeTitle = escapeHtml(incident.title);
                    const safeBarangay = escapeHtml(incident.barangay);
                    const safeStatus = escapeHtml(statusLabels[incident.status] || incident.status);
                    const safeSeverity = escapeHtml(severityLabels[incident.severity] || incident.severity);

                    const row = document.createElement('div');
                    row.className = 'table-row';
                    row.innerHTML = `
                        <div>${safeTitle}</div>
                        <div>${safeBarangay}</div>
                        <div>${safeStatus}</div>
                        <div>${safeSeverity}</div>
                    `;
                    table.appendChild(row);
                });

                if (data.incidents.length === 0) {
                    const row = document.createElement('div');
                    row.className = 'table-row';
                    row.innerHTML = '<div colspan="4">No reports yet.</div>';
                    table.appendChild(row);
                }
            } catch (error) {
                console.error('Failed to load dashboard:', error);
            }
        }

        loadDashboard();
    </script>
