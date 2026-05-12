<?php
require __DIR__ . '/guard.php';
require __DIR__ . '/../api/db.php';
init_secure_session();

// Determine user role
$userRole = $_SESSION['role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;
$barangayId = $_SESSION['barangay_id'] ?? null;
$barangayName = null;

// Validate role
if (!in_array($userRole, ['admin', 'barangay'], true)) {
    header('Location: login.php');
    exit;
}

// Fetch barangay name for barangay users
if ($userRole === 'barangay' && isset($barangayId)) {
    $stmt = $pdo->prepare('SELECT barangay_name FROM barangays WHERE barangay_id = :id');
    $stmt->execute([':id' => $barangayId]);
    $result = $stmt->fetch();
    $barangayName = $result ? $result['barangay_name'] : null;
}

$csrfToken = csrf_token();

// Configuration per role
$isAdmin = $userRole === 'admin';
$isBarangay = $userRole === 'barangay';

$brandTitle = $isAdmin ? 'Admin Dashboard' : 'Barangay Dashboard';
$brandSubtitle = $isAdmin ? 'System-wide monitoring' : 'Incident verification and updates';
$heroEyebrow = $isAdmin ? 'Admin control panel' : 'Barangay overview';
$heroTitle = $isAdmin ? 'Oversee reports, users, and forensic outputs.' : 'Monitor reports in ' . htmlspecialchars($barangayName ?? 'your area') . '.';
$heroLead = $isAdmin 
    ? 'Track incident volume, generate monthly and area reports, and jump straight into the management pages.'
    : 'Review new submissions, add incidents directly, and generate monthly or area-based reports for local response work.';

$incidentsApiEndpoint = $isAdmin ? 'admin-incidents.php' : 'barangay-incidents.php';
$quickActionLink1 = $isAdmin ? 'admin-users.php' : 'barangay-add-incident.php';
$quickActionLabel1 = $isAdmin ? 'Manage users' : 'Add incident';
$quickActionPrimary = !$isAdmin;

$kpiPending = $isAdmin ? 'Total reports' : 'Pending reports';
$kpiActive = $isAdmin ? 'Active cases' : 'Active cases';
$kpiResolved = $isAdmin ? 'Resolved cases' : 'Resolved this month';
$kpiFourth = $isAdmin ? 'High severity alerts' : 'High risk areas';

$footerTitle = $isAdmin ? 'Admin oversight dashboard' : 'Barangay operations dashboard';
$footerSubtitle = $isAdmin ? 'Keep the system accurate and transparent.' : 'Update reports promptly to keep the community informed.';

// Determine which KPI fields are shown
$showKpiFields = $isAdmin 
    ? ['total', 'active', 'resolved', 'high_severity']
    : ['pending', 'active', 'resolved_month', 'high_risk'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($brandTitle, ENT_QUOTES, 'UTF-8'); ?> | La Trinidad Crime Mapping</title>
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
                    <div class="brand-title"><?php echo htmlspecialchars($brandTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="brand-subtitle"><?php echo htmlspecialchars($brandSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('dashboard', $userRole); ?>
        </header>

        <main>
            <section class="hero hero-tight">
                <div class="hero-copy">
                    <p class="eyebrow"><?php echo htmlspecialchars($heroEyebrow, ENT_QUOTES, 'UTF-8'); ?></p>
                    <h1><?php echo htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="lead"><?php echo htmlspecialchars($heroLead, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </section>

            <?php if ($isAdmin): ?>
                <section class="dashboard-kpis">
                    <div class="kpi-card"><div class="kpi-label">Total reports</div><div class="kpi-value" id="kpi-total">--</div></div>
                    <div class="kpi-card"><div class="kpi-label">Active cases</div><div class="kpi-value" id="kpi-active">--</div></div>
                    <div class="kpi-card"><div class="kpi-label">Resolved cases</div><div class="kpi-value" id="kpi-resolved">--</div></div>
                    <div class="kpi-card"><div class="kpi-label">High severity alerts</div><div class="kpi-value" id="kpi-high-severity">--</div></div>
                </section>
            <?php else: ?>
                <section class="summary-strip">
                    <div class="summary-chip"><span>Pending reports</span><strong id="kpi-pending">--</strong></div>
                    <div class="summary-chip"><span>Active cases</span><strong id="kpi-active">--</strong></div>
                    <div class="summary-chip"><span>Resolved this month</span><strong id="kpi-resolved">--</strong></div>
                    <div class="summary-chip"><span>High risk areas</span><strong id="kpi-high-risk">--</strong></div>
                </section>
            <?php endif; ?>
            <br>

            <section class="panel">
                <div class="panel-header">
                    <h2>Quick Actions</h2>
                    <div class="dashboard-actions">
                        <?php if ($isAdmin): ?>
                            <a class="btn-secondary" href="admin-users.php">Manage users</a>
                            <a class="btn-secondary" href="admin-faq.php">Manage FAQs</a>
                            <a class="btn-secondary" href="incidents.php">View incidents</a>
                            <a class="btn-secondary" href="report-export.php?type=forensics&autoprint=1">Export reports</a>
                        <?php else: ?>
                            <a class="btn-primary" href="barangay-add-incident.php">Add incident</a>
                            <a class="btn-secondary" href="incidents.php">Review incidents</a>
                            <a class="btn-secondary" href="report-export.php?type=forensics&autoprint=1">Export reports</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-toolbar">
                    <button type="button" class="date-trigger" id="date-trigger-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span id="date-trigger-label">May 2026</span>
                    </button>
                    <input type="hidden" id="report-month" />
                    <button type="button" class="btn-primary" data-report="monthly">Monthly report</button>
                    <button type="button" class="btn-secondary" data-report="area">Area report</button>
                    <button type="button" class="btn-secondary" data-report="crime">Crime report</button>
                    <?php if ($isAdmin): ?>
                        <button type="button" class="btn-secondary" data-report="forensics">Forensics report</button>
                    <?php endif; ?>
                </div>

                <!-- Date picker modal -->
                <div class="modal-overlay" id="datepicker-overlay">
                    <div class="modal-content datepicker-modal">
                        <div class="modal-header">
                            <h2>Select period</h2>
                            <button class="modal-close" id="datepicker-close">&times;</button>
                        </div>
                        <div class="datepicker-body">
                            <div class="datepicker-year-row">
                                <button type="button" class="dp-nav-btn" id="dp-prev-year">&#8592;</button>
                                <span class="dp-year-label" id="dp-year-label"></span>
                                <button type="button" class="dp-nav-btn" id="dp-next-year">&#8594;</button>
                            </div>
                            <div class="datepicker-months" id="dp-months"></div>
                        </div>
                    </div>
                </div>

                <div class="report-panel-card report-output" id="report-output">
                    <p class="muted">Choose a report type to generate a summary.</p>
                </div>
            </section>
            <br>

            <section class="panel">
                <div class="panel-header">
                    <h2><?php echo $isAdmin ? 'Verification Queue' : 'Report Queue'; ?></h2>
                    <a class="btn-secondary" href="<?php echo $isAdmin ? 'incidents.php' : 'barangay-add-incident.php'; ?>"><?php echo $isAdmin ? 'Open compact cards' : 'Enter new incident'; ?></a>
                </div>
                <div class="data-table" id="incident-table">
                    <div class="table-row header">
                        <div>Incident</div>
                        <div><?php echo $isAdmin ? 'Barangay' : 'Status'; ?></div>
                        <div><?php echo $isAdmin ? 'Status' : 'Date'; ?></div>
                        <div>Severity</div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div><?php echo htmlspecialchars($footerTitle, ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars($footerSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
        </footer>
    </div>

    <script>
        const apiBase = '../api';
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

        // ── Custom date picker ───────────────────────────────────
        const MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const MONTH_SHORT  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        const now = new Date();
        let dpYear  = now.getFullYear();
        let dpMonth = now.getMonth();
        let selYear = now.getFullYear();
        let selMonth = now.getMonth();

        function getReportMonth() {
            return `${selYear}-${String(selMonth + 1).padStart(2, '0')}`;
        }

        function syncTrigger() {
            document.getElementById('report-month').value = getReportMonth();
            document.getElementById('date-trigger-label').textContent = `${MONTH_NAMES[selMonth]} ${selYear}`;
        }

        function renderDpMonths() {
            document.getElementById('dp-year-label').textContent = dpYear;
            const grid = document.getElementById('dp-months');
            grid.innerHTML = '';
            MONTH_SHORT.forEach((name, i) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = name;
                btn.className = 'dp-month-btn' + (i === dpMonth ? ' dp-month-active' : '');
                btn.addEventListener('click', () => {
                    selYear  = dpYear;
                    selMonth = i;
                    syncTrigger();
                    document.getElementById('datepicker-overlay').classList.remove('is-open');
                    if (activeReportType) generateReport(activeReportType);
                });
                grid.appendChild(btn);
            });
        }

        document.getElementById('date-trigger-btn').addEventListener('click', () => {
            dpYear  = selYear;
            dpMonth = selMonth;
            renderDpMonths();
            document.getElementById('datepicker-overlay').classList.add('is-open');
        });

        document.getElementById('dp-prev-year').addEventListener('click', () => { dpYear--; renderDpMonths(); });
        document.getElementById('dp-next-year').addEventListener('click', () => { dpYear++; renderDpMonths(); });

        document.getElementById('datepicker-close').addEventListener('click', () => {
            document.getElementById('datepicker-overlay').classList.remove('is-open');
        });
        document.getElementById('datepicker-overlay').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) e.currentTarget.classList.remove('is-open');
        });

        syncTrigger();
        // ────────────────────────────────────────────────────────

        function formatMonth(ym) {
            if (!ym) return ym;
            const [year, month] = ym.split('-');
            const names = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            return `${names[parseInt(month, 10) - 1]} ${year}`;
        }

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
                    <div class="summary-chip"><span>Report period</span><strong>${escapeHtml(formatMonth(data.data.month))}</strong></div>
                    <table>
                        <thead><tr><th>Status</th><th>Severity</th><th>Count</th></tr></thead>
                        <tbody>${(data.data.incidents_by_status || []).map((row) => `<tr><td>${escapeHtml(row.status)}</td><td>${escapeHtml(row.severity)}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}</tbody>
                    </table>
                `;
                return;
            }

            if (data.data.type === 'area') {
                output.innerHTML = `
                    <div class="summary-chip"><span>Report period</span><strong>${escapeHtml(formatMonth(data.data.month))}</strong></div>
                    <table>
                        <thead><tr><th>Barangay</th><th>Count</th><th>High severity %</th></tr></thead>
                        <tbody>${(data.data.barangay_stats || []).map((row) => `<tr><td>${escapeHtml(row.barangay_name)}</td><td>${escapeHtml(row.count)}</td><td>${escapeHtml(Number(row.high_severity_pct || 0).toFixed(1))}%</td></tr>`).join('')}</tbody>
                    </table>
                `;
                return;
            }

            output.innerHTML = `
                <div class="summary-chip"><span>Report period</span><strong>${escapeHtml(formatMonth(data.data.month))}</strong></div>
                <table>
                    <thead><tr><th>Category</th><th>Crime Type</th><th>Count</th></tr></thead>
                    <tbody>${(data.data.crime_stats || []).map((row) => `<tr><td>${escapeHtml(row.category)}</td><td>${escapeHtml(row.type_name)}</td><td>${escapeHtml(row.count)}</td></tr>`).join('')}</tbody>
                </table>
            `;
        }

        async function loadDashboard() {
            try {
                const endpoint = isAdmin ? 'admin-incidents.php' : 'barangay-incidents.php';
                const response = await fetch(`${apiBase}/${endpoint}`);
                const data = await response.json();

                if (!data.ok) { console.error(data.error); return; }

                if (isAdmin) {
                    document.getElementById('kpi-total').textContent = data.kpis.total;
                    document.getElementById('kpi-active').textContent = data.kpis.active;
                    document.getElementById('kpi-resolved').textContent = data.kpis.resolved;
                    document.getElementById('kpi-high-severity').textContent = data.kpis.high_severity;
                } else {
                    document.getElementById('kpi-pending').textContent = data.kpis.pending;
                    document.getElementById('kpi-active').textContent = data.kpis.active;
                    document.getElementById('kpi-resolved').textContent = data.kpis.resolved_month;
                    document.getElementById('kpi-high-risk').textContent = data.kpis.high_risk;
                }

                const table = document.getElementById('incident-table');
                if (isAdmin) {
                    table.innerHTML = '<div class="table-row header"><div>Incident</div><div>Barangay</div><div>Status</div><div>Severity</div></div>';
                } else {
                    table.innerHTML = '<div class="table-row header"><div>Incident</div><div>Status</div><div>Date</div><div>Severity</div></div>';
                }

                data.incidents.slice(0, 8).forEach((incident) => {
                    const row = document.createElement('div');
                    row.className = 'table-row';
                    if (isAdmin) {
                        row.innerHTML = `
                            <div>${escapeHtml(incident.title)}</div>
                            <div>${escapeHtml(incident.barangay)}</div>
                            <div>${escapeHtml(incident.status)}</div>
                            <div>${escapeHtml(incident.severity)}</div>
                        `;
                    } else {
                        row.setAttribute('data-id', incident.id || incident.incident_id || '');
                        row.setAttribute('data-lat', incident.lat || incident.latitude || '');
                        row.setAttribute('data-lng', incident.lng || incident.longitude || '');
                        row.innerHTML = `
                            <div>${escapeHtml(incident.title)}</div>
                            <div>${escapeHtml(incident.status)}</div>
                            <div>${escapeHtml(incident.date)}</div>
                            <div>${escapeHtml(incident.severity)}</div>
                        `;
                    }
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

        let activeReportType = null;

        async function generateReport(type) {
            activeReportType = type;
            const month = getReportMonth();
            const response = await fetch(`${apiBase}/reports-generate.php?type=${encodeURIComponent(type)}&month=${encodeURIComponent(month)}`);
            const data = await response.json();
            renderReport(data);
        }

        document.querySelectorAll('[data-report]').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('[data-report]').forEach(b => {
                    b.classList.remove('is-active');
                    b.className = 'btn-secondary';
                    b.setAttribute('data-report', b.dataset.report);
                });
                button.classList.add('is-active');
                generateReport(button.getAttribute('data-report'));
            });
        });

        loadDashboard();
    </script>
</body>
</html>