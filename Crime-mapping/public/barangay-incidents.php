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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Incidents | La Trinidad Crime Mapping</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
    <style>
        .incidents-container {
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .incidents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 16px;
        }

        .incident-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .incident-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }

        .incident-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
        }

        .incident-card-title {
            font-weight: 600;
            color: var(--text);
            margin: 0;
            flex: 1;
        }

        .incident-card-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            font-size: 12px;
            color: var(--muted);
        }

        .incident-card-meta div {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .incident-card-meta strong {
            color: var(--text);
            font-size: 13px;
        }

        .incident-card-description {
            font-size: 13px;
            color: var(--text);
            line-height: 1.4;
            flex: 1;
        }

        .incident-card-footer {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            padding-top: 8px;
            border-top: 1px solid var(--border);
        }

        .incidents-controls {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .incidents-controls input,
        .incidents-controls select {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
        }

        .incidents-controls input {
            flex: 1;
            min-width: 200px;
        }

        .incidents-controls select {
            min-width: 150px;
        }

        .incidents-empty {
            text-align: center;
            padding: 48px 24px;
            color: var(--muted);
        }

        .incidents-empty p {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="brand">
                <img class="brand-logo" src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                <div>
                    <div class="brand-title">Crime Mapping</div>
                    <div class="brand-subtitle">Barangay Officer</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('incidents', 'barangay'); ?>
        </header>

        <main class="incidents-container">
            <section>
                <div style="margin-bottom: 24px;">
                    <p class="eyebrow">Incident Management</p>
                    <h1>Incidents in Your Area</h1>
                    <p class="lead">Review, verify, and manage incident reports for <strong><?php echo htmlspecialchars($barangayName ?? ''); ?></strong>.</p>
                </div>

                <div class="incidents-controls">
                    <input type="text" id="search-incidents" placeholder="Search incidents by title or description..." />
                    <select id="filter-status">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="under_investigation">Under investigation</option>
                        <option value="action_taken">Action taken</option>
                        <option value="resolved">Resolved</option>
                        <option value="dismissed">Dismissed</option>
                    </select>
                </div>

                <div id="incidents-container" class="incidents-grid">
                    <div class="incidents-empty">
                        <p>Loading incidents...</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const apiBase = "../api";
        const barangayName = <?php echo json_encode($barangayName); ?>;
        let allIncidents = [];

        function escapeHtml(value) {
            return String(value ?? "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/\"/g, "&quot;")
                .replace(/'/g, "&#39;");
        }

        function sanitizeClassToken(value) {
            return String(value ?? "").toLowerCase().replace(/[^a-z0-9_-]/g, "");
        }

        async function loadIncidents() {
            try {
                // Load only incidents for this barangay
                const query = new URLSearchParams({ barangay: barangayName }).toString();
                const response = await fetch(`${apiBase}/incidents.php?${query}`);
                const data = await response.json();
                allIncidents = data.ok ? data.data : [];
                renderIncidents(allIncidents);
            } catch (error) {
                console.error("Failed to load incidents", error);
                document.getElementById("incidents-container").innerHTML = '<div class="incidents-empty" style="grid-column: 1 / -1;"><p style="color: #f43f5e;">Failed to load incidents. Please try again.</p></div>';
            }
        }

        function renderIncidents(incidents) {
            const container = document.getElementById("incidents-container");
            if (!incidents || incidents.length === 0) {
                container.innerHTML = '<div class="incidents-empty" style="grid-column: 1 / -1;"><p>No incidents in your area</p></div>';
                return;
            }

            container.innerHTML = incidents.map(incident => {
                const id = Number.parseInt(incident.id, 10) || 0;
                const statusClass = sanitizeClassToken(incident.status);
                const severityClass = sanitizeClassToken(incident.severity);
                const title = escapeHtml(incident.title);
                const statusLabel = escapeHtml(String(incident.status ?? "").replace(/_/g, ' '));
                const description = escapeHtml(incident.description);
                const shortDescription = description.length > 100 ? `${description.substring(0, 100)}...` : description;
                const typeName = escapeHtml(incident.type_name);
                const date = escapeHtml(incident.date);
                const severity = escapeHtml(incident.severity);

                return `
                <div class="incident-card" onclick="viewIncident(${id})">
                    <div class="incident-card-header">
                        <h3 class="incident-card-title">${title}</h3>
                        <span class="status-badge status-${statusClass}">${statusLabel}</span>
                    </div>
                    <p class="incident-card-description">${shortDescription}</p>
                    <div class="incident-card-meta">
                        <div>
                            <strong>Type</strong>
                            ${typeName}
                        </div>
                        <div>
                            <strong>Date</strong>
                            ${date}
                        </div>
                        <div>
                            <strong>Severity</strong>
                            <span class="severity-badge severity-${severityClass}">${severity}</span>
                        </div>
                        <div>
                            <strong>Status</strong>
                            ${statusLabel}
                        </div>
                    </div>
                    <div class="incident-card-footer">
                        <small style="color: var(--muted);">ID: ${id}</small>
                        <a href="barangay-map.php?incident=${id}" class="link-small" onclick="event.stopPropagation()">View on Map →</a>
                    </div>
                </div>
            `;
            }).join('');
        }

        function filterIncidents() {
            const search = document.getElementById("search-incidents").value.toLowerCase();
            const status = document.getElementById("filter-status").value;

            let filtered = allIncidents.filter(incident => {
                const matchesSearch = incident.title.toLowerCase().includes(search) || 
                                     incident.description.toLowerCase().includes(search);
                const matchesStatus = status === '' || incident.status === status;
                return matchesSearch && matchesStatus;
            });

            renderIncidents(filtered);
        }

        function viewIncident(incidentId) {
            window.location.href = `barangay-map.php?incident=${incidentId}`;
        }

        document.getElementById("search-incidents").addEventListener("input", filterIncidents);
        document.getElementById("filter-status").addEventListener("change", filterIncidents);

        loadIncidents();
    </script>
</body>
</html>
