<?php
require __DIR__ . '/../api/security.php';
require __DIR__ . '/../api/db.php';
init_secure_session();


$userRole = $_SESSION['role'] ?? 'public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>La Trinidad Crime Mapping</title>
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
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
                <img class="brand-logo" src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                <div>
                    <div class="brand-title">La Trinidad Crime Mapping</div>
                    <div class="brand-subtitle">Benguet, Philippines</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('dashboard', $userRole); ?>
        </header>

        <main>
            <section class="hero">
                <div class="hero-copy">
                    <p class="eyebrow">Live community safety insights</p>
                    <h1>Track incidents across La Trinidad</h1>
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
                        <div class="map-legend" id="mini-map-legend"></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel report-panel report-panel-wide">
                <div class="panel-header">
                    <div>
                        <h2>Last 7 Day Report</h2>
                        <p class="panel-note">A rolling summary of the latest reports, hotspots, and category mix.</p>
                    </div>
                    <span class="pill" id="week-report-range">Last 7 days</span>
                </div>
                <div class="week-report-grid">
                    <div class="week-report-overview">
                        <div class="week-report-stat">
                            <span>Total reports</span>
                            <strong id="week-total">0</strong>
                        </div>
                        <div class="week-report-stat">
                            <span>Average per day</span>
                            <strong id="week-average">0</strong>
                        </div>
                        <div class="week-report-stat">
                            <span>Busiest day</span>
                            <strong id="week-busiest">-</strong>
                        </div>
                        <div class="week-report-stat">
                            <span>Top category</span>
                            <strong id="week-category">-</strong>
                        </div>
                    </div>
                    <div class="week-chart" id="week-chart" aria-label="Seven day report chart"></div>
                </div>
            </section>

            <section class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>Alerts & Notifications</h2>
                        <span class="pill">Auto-updating</span>
                    </div>
                    <div class="alerts" id="alerts"></div>
                </div>

                <div class="panel carousel-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Recent Crime Images</h2>
                            <p class="panel-note">Recent incident photos are shown below with captions and a graceful fallback when no image is attached.</p>
                        </div>
                    </div>
                    <div class="carousel-shell">
                        <div class="carousel-track" id="crime-carousel-track" aria-live="polite"></div>
                    </div>
                    <div class="carousel-status" id="crime-carousel-status">Loading recent images...</div>
                </div>

                <div class="panel span-full">
                    <div class="panel-header">
                        <h2>Recent Reports</h2>
                        <button class="btn-tertiary" id="refresh-feed">Refresh</button>
                    </div>
                    <div class="feed" id="recent-feed"></div>
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
