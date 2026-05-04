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
    <title>Admin Map | La Trinidad Crime Mapping</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="../assets/css/site.css" />
</head>
<body class="page-map">
    <div class="map-shell overlay-mode">
        <aside class="map-filters">
            <div class="filters-header">
                <div>
                    <div class="eyebrow">Admin Control</div>
                    <h2>Crime Map - Global View</h2>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="button" id="close-filters" class="btn-tertiary close-filters">Close</button>
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-label">Search</label>
                <input id="search-input" type="text" placeholder="Search name, keyword, or description" />
            </div>

            <div class="filter-group">
                <label class="filter-label">Type of Crime</label>
                <div class="checkbox-list" id="type-filters"></div>
            </div>

            <div class="filter-group">
                <label class="filter-label">Date Range</label>
                <div class="date-range">
                    <input id="date-start" type="date" />
                    <input id="date-end" type="date" />
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-label">Barangay</label>
                <select id="barangay-filter">
                    <option value="">All barangays</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="status-filter">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="under_investigation">Under investigation</option>
                    <option value="action_taken">Action taken</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Marker Style</label>
                <div class="toggle-row">
                    <button type="button" class="toggle-btn is-active" data-style="icon">Category icon</button>
                    <button type="button" class="toggle-btn " data-style="dot">Colored dots</button>
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn-secondary" id="reset-filters">Reset</button>
            </div>
            <p class="muted filter-hint">All incidents visible. Filters apply automatically.</p>
        </aside>

            <main class="map-stage">
            <header class="map-topbar">
                <div class="brand">
                    <span class="brand-mark"></span>
                    <div>
                        <div class="brand-title">La Trinidad Crime Mapping</div>
                        <div class="brand-subtitle">Admin global view</div>
                    </div>
                </div>
                <nav class="map-nav">
                    <a href="admin-dashboard.php">Dashboard</a>
                    <a class="is-active" href="admin-map.php">Map</a>
                    <a href="admin-incidents.php">Incidents</a>
                    <a href="auth-logout.php">Logout</a>
                </nav>
            </header>

            <button id="hamburger-filters" class="hamburger-btn" aria-label="Open filters">
                <svg width="18" height="14" viewBox="0 0 18 14" fill="none" xmlns="http://www.w3.org/2000/svg"><rect y="1" width="18" height="2" rx="1" fill="currentColor"/><rect y="6" width="18" height="2" rx="1" fill="currentColor"/><rect y="11" width="18" height="2" rx="1" fill="currentColor"/></svg>
            </button>

            <div id="map" class="map-canvas map-full"></div>
        </main>

        <aside class="map-details" id="details-panel">
            <div class="details-header">
                <div>
                    <div class="eyebrow">Incident Details</div>
                    <h2 id="details-title">Select a pin</h2>
                </div>
                <button type="button" id="close-details" class="btn-tertiary">Close</button>
            </div>
            <div class="details-body" id="details-body">
                <p class="muted">Click a marker to view the full report.</p>
            </div>
            <div class="validation-panel">
                <div class="validation-label">Report Actions</div>
                <div class="validation-buttons">
                    <button type="button" class="validation-btn" id="approve-btn">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M22 11.08V12a10 10 0 1 1-5.93-9.14'/%3E%3Cpolyline points='22 4 12 14.01 9 11.01'/%3E%3C/svg%3E" alt="approve" class="validation-icon" />
                        <div class="validation-text">
                            <span class="validation-label-small">Approve</span>
                        </div>
                    </button>
                    <button type="button" class="validation-btn" id="reject-btn">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cline x1='15' y1='9' x2='9' y2='15'/%3E%3Cline x1='9' y1='9' x2='15' y2='15'/%3E%3C/svg%3E" alt="reject" class="validation-icon" />
                        <div class="validation-text">
                            <span class="validation-label-small">Reject</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="details-actions">
                <button type="button" class="btn-primary" id="report-crime" style="display:none;">Report a crime</button>
            </div>

            <div class="report-panel" id="report-panel" style="display:none;">
                <div class="details-header">
                    <div>
                        <div class="eyebrow">Submit Report</div>
                        <h2>Report a crime</h2>
                    </div>
                    <button type="button" id="close-report" class="btn-tertiary">Close</button>
                </div>
                <form id="report-form" class="form-grid">
                    <label>
                        <span>Crime type</span>
                        <select id="report-type" required></select>
                    </label>
                    <label>
                        <span>Title</span>
                        <input id="report-title" type="text" placeholder="Short incident title" required />
                    </label>
                    <label>
                        <span>Description</span>
                        <textarea id="report-description" rows="4" placeholder="Describe what happened" required></textarea>
                    </label>
                    <label>
                        <span>Barangay</span>
                        <select id="report-barangay" required></select>
                    </label>
                    <label>
                        <span>Date</span>
                        <input id="report-date" type="date" required />
                    </label>
                    <label>
                        <span>Time</span>
                        <input id="report-time" type="time" required />
                    </label>
                    <label>
                        <span>Severity</span>
                        <select id="report-severity" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </label>
                    <label>
                        <span>Coordinates</span>
                        <input id="report-coords" type="text" placeholder="Click on the map to set" readonly />
                    </label>
                    <div class="report-actions">
                        <button class="btn-primary" type="submit">Submit report</button>
                        <button class="btn-secondary" id="report-cancel" type="button">Cancel</button>
                    </div>
                    <p class="muted" id="report-status"></p>
                </form>
            </div>

            <div class="detail-modal" id="detail-modal">
                <div class="detail-modal-header">
                    <div>
                        <div class="eyebrow">Incident Details</div>
                        <h2 id="modal-title">Loading...</h2>
                    </div>
                    <button type="button" id="close-modal" class="btn-tertiary">Close</button>
                </div>

                <div class="detail-modal-body">
                    <div class="detail-info" id="detail-info">
                        <p class="muted">Loading incident details...</p>
                    </div>

                    <div class="detail-gallery">
                        <div class="gallery-label">Evidence Images</div>
                        <div class="image-carousel" id="image-carousel">
                            <p class="muted">No images uploaded yet.</p>
                        </div>
                    </div>

                    <div class="image-upload-section">
                        <label class="image-upload-label">
                            <span>Upload Image</span>
                            <input type="file" id="detail-image-input" accept="image/*" />
                        </label>
                        <p class="muted" id="upload-status"></p>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        // Admin mode: no barangay restriction
        const userBarangayId = null;
        const userBarangayName = null;
        window.userRole = 'admin';
        window.csrfToken = <?php echo json_encode($csrfToken); ?>;
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="../assets/js/map.js"></script>
</body>
</html>
