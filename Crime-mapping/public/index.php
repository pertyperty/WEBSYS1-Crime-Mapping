<?php
require __DIR__ . '/../api/security.php';
init_secure_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>La Trinidad Crime Mapping</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="../assets/css/site.css" />
</head>
<body class="page-home">
    <div class="page-shell">
        <header class="site-header">
            <div class="brand">
                <span class="brand-mark"></span>
                <div>
                    <div class="brand-title">La Trinidad Crime Mapping</div>
                    <div class="brand-subtitle">Benguet, Philippines</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('dashboard', 'public'); ?>
        </header>

        <main>
            <section class="hero">
                <div class="hero-copy">
                    <p class="eyebrow">Live community safety insights</p>
                    <h1>Track incidents across La Trinidad in real time.</h1>
                    <p class="lead">Monitor crime activity, view hotspots, and stay informed with verified community reports.</p>
                    <div class="hero-actions">
                        <a class="btn-primary" href="map.php">Open Full Map</a>
                        <a class="btn-secondary" href="about.php">How it works</a>
                    </div>
                    <div class="quick-stats" id="quick-stats">
                        <div class="stat-card">
                            <div class="stat-label">Last 24 hrs</div>
                            <div class="stat-value" data-stat="daily">0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Active cases</div>
                            <div class="stat-value" data-stat="active">0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Most affected</div>
                            <div class="stat-value" data-stat="hotspot">-</div>
                        </div>
                    </div>
                </div>
                <div class="hero-map">
                    <div class="map-shell">
                        <div class="map-header">
                            <div>
                                <div class="map-title">Mini Map Snapshot</div>
                                <div class="map-subtitle">Recent incidents only</div>
                            </div>
                            <a class="map-link" href="map.php">Expand</a>
                        </div>
                        <div id="mini-map" class="map-canvas"></div>
                        <div class="map-legend">
                            <span class="legend-dot violent"></span> Violent
                            <span class="legend-dot property"></span> Property
                            <span class="legend-dot drug"></span> Drug
                            <span class="legend-dot traffic"></span> Traffic
                        </div>
                    </div>
                </div>
            </section>

            <section class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>Recent Reports</h2>
                        <button class="btn-tertiary" id="refresh-feed">Refresh</button>
                    </div>
                    <div class="feed" id="recent-feed"></div>
                </div>
                <div class="panel">
                    <div class="panel-header">
                        <h2>Alerts & Notifications</h2>
                        <span class="pill">Auto-updating</span>
                    </div>
                    <div class="alerts" id="alerts"></div>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div>La Trinidad Crime Mapping &mdash; Community Safety Portal</div>
            <div>For emergency response, contact local authorities.</div>
        </footer>
    </div>

    <script>
        window.csrfToken = <?php echo json_encode(csrf_token()); ?>;
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
