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
                <img class="brand-logo" src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                <div>
                    <div class="brand-title">Crime Mapping</div>
                    <div class="brand-subtitle">Barangay officer</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('incidents', 'barangay'); ?>
        </header>

        <main>
            <section class="hero hero-tight">
                <div class="hero-copy">
                    <p class="eyebrow">Incident Management</p>
                    <h1>Incidents in <?php echo htmlspecialchars($barangayName ?? 'your area'); ?>.</h1>
                    <p class="lead">Review current reports, then add an incident directly when the map needs a local record.</p>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>Filters</h2>
                    <div class="incident-filterbar">
                        <input type="text" id="search-incidents" placeholder="Search incidents by title or description..." />
                        <select id="filter-status">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="under_investigation">Under investigation</option>
                            <option value="action_taken">Action taken</option>
                            <option value="resolved">Resolved</option>
                            <option value="dismissed">Dismissed</option>
                        </select>
                        <a class="btn-primary" href="barangay-add-incident.php">Add incident</a>
                    </div>
                </div>

                <div class="compact-card-grid" id="incidents-container">
                    <div class="incidents-empty u-span-full">
                        <p>Loading incidents...</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const apiBase = '../api';
        let allIncidents = [];
        const carouselTimers = new Map();

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function sanitizeClassToken(value) {
            return String(value ?? '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        }

        function shortText(value, limit = 120) {
            const text = String(value ?? '');
            return text.length > limit ? `${text.slice(0, limit)}...` : text;
        }

        function buildMedia(images) {
            const list = Array.isArray(images) ? images : [];
            if (list.length === 0) {
                return `
                    <div class="compact-card-media">
                        <img src="../assets/images/home-placeholder.svg" alt="Incident placeholder" />
                        <div class="media-placeholder">No image attached yet</div>
                    </div>
                `;
            }

            const safeImages = list.map((path) => escapeHtml(path));
            return `
                <div class="compact-card-media incident-carousel" data-images='${JSON.stringify(safeImages)}'>
                    <img src="../${safeImages[0]}" alt="Incident image" data-carousel-image />
                </div>
            `;
        }

        function startCarousels() {
            carouselTimers.forEach((timer) => clearInterval(timer));
            carouselTimers.clear();

            document.querySelectorAll('.incident-carousel').forEach((carousel) => {
                const images = JSON.parse(carousel.getAttribute('data-images') || '[]');
                if (!images.length) return;

                const image = carousel.querySelector('[data-carousel-image]');
                let index = 0;
                const timer = setInterval(() => {
                    index = (index + 1) % images.length;
                    image.src = `../${images[index]}`;
                }, 5000);
                carouselTimers.set(carousel, timer);
            });
        }

        async function loadIncidents() {
            try {
                const query = new URLSearchParams({ barangay: <?php echo json_encode($barangayName); ?> }).toString();
                const response = await fetch(`${apiBase}/barangay-incidents.php?${query}`);
                const data = await response.json();
                allIncidents = data.ok ? data.incidents : [];
                renderIncidents(allIncidents);
            } catch (error) {
                console.error('Failed to load incidents', error);
                document.getElementById('incidents-container').innerHTML = '<div class="incidents-empty u-span-full"><p class="u-text-danger">Failed to load incidents. Please try again.</p></div>';
            }
        }

        function renderIncidents(incidents) {
            const container = document.getElementById('incidents-container');
            if (!incidents || incidents.length === 0) {
                container.innerHTML = '<div class="incidents-empty u-span-full"><p>No incidents in your area</p></div>';
                return;
            }

            container.innerHTML = incidents.map((incident) => {
                const id = Number.parseInt(incident.id, 10) || 0;
                const statusClass = sanitizeClassToken(incident.status);
                const severityClass = sanitizeClassToken(incident.severity);
                const title = escapeHtml(incident.title);
                const statusLabel = escapeHtml(String(incident.status ?? '').replace(/_/g, ' '));
                const description = shortText(escapeHtml(incident.description), 130);
                const typeName = escapeHtml(incident.type_name);
                const date = escapeHtml(incident.date);
                const severity = escapeHtml(incident.severity);

                return `
                    <article class="compact-card incident-report-card" onclick="viewIncident(${id})">
                        ${buildMedia(incident.images || [])}
                        <div class="compact-card-body">
                            <div class="compact-card-title">${title}</div>
                            <span class="status-badge status-${statusClass}">${statusLabel}</span>
                            <p class="compact-card-text">${description}</p>
                            <div class="compact-card-meta">
                                <div><strong>Type</strong>${typeName}</div>
                                <div><strong>Date</strong>${date}</div>
                                <div><strong>Severity</strong><span class="severity-badge severity-${severityClass}">${severity}</span></div>
                                <div><strong>Status</strong>${statusLabel}</div>
                            </div>
                            <div class="compact-card-footer">
                                <span class="incident-mini-note">ID: ${id}</span>
                                <a href="barangay-map.php?incident=${id}" class="link-small" onclick="event.stopPropagation()">View on Map →</a>
                                <a href="incident-export.php?incident_id=${id}&autoprint=1" class="link-small" target="_blank" rel="noopener" onclick="event.stopPropagation()">Export PDF →</a>
                            </div>
                        </div>
                    </article>
                `;
            }).join('');

            startCarousels();
        }

        function filterIncidents() {
            const search = document.getElementById('search-incidents').value.toLowerCase();
            const status = document.getElementById('filter-status').value;

            const filtered = allIncidents.filter((incident) => {
                const matchesSearch = [incident.title, incident.description, incident.type_name]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()
                    .includes(search);
                const matchesStatus = status === '' || incident.status === status;
                return matchesSearch && matchesStatus;
            });

            renderIncidents(filtered);
        }

        function viewIncident(incidentId) {
            window.location.href = `barangay-map.php?incident=${incidentId}`;
        }

        document.getElementById('search-incidents').addEventListener('input', filterIncidents);
        document.getElementById('filter-status').addEventListener('change', filterIncidents);

        loadIncidents();
    </script>
</body>
</html>
