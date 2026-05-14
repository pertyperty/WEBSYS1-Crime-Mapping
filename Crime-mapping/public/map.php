<?php
require __DIR__ . '/../api/security.php';
require __DIR__ . '/../api/db.php';
init_secure_session();


$userRole = $_SESSION['role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;
$barangayId = $_SESSION['barangay_id'] ?? null;
$barangayName = null;


if ($userRole === 'barangay' && isset($barangayId)) {
    $stmt = $pdo->prepare('SELECT barangay_name FROM barangays WHERE barangay_id = :id');
    $stmt->execute([':id' => $barangayId]);
    $result = $stmt->fetch();
    $barangayName = $result ? $result['barangay_name'] : null;
}

$csrfToken = csrf_token();


$filterHeaderEyebrow = 'Filters';
$filterHeaderTitle = 'Crime Map';
$mapSubtitle = 'Interactive map view';
$filterHint = 'Filters apply automatically as you change them.';
$isBarangayFiltered = false;
$showPendingStatus = false;
$showDetailsHeader = false;
$validationLabel = 'Is this report accurate?';
$validationButtons = 'public'; 
$showReportCrimeButton = true;
$showExportPdf = false;

if ($userRole === 'admin') {
    $filterHeaderEyebrow = 'Admin Control';
    $filterHeaderTitle = 'Crime Map - Global View';
    $mapSubtitle = 'Admin global view';
    $filterHint = 'All incidents visible. Filters apply automatically.';
    $showPendingStatus = true;
    $showDetailsHeader = true;
    $validationLabel = 'Report Actions';
    $validationButtons = 'admin';
    $showReportCrimeButton = true;
    $showExportPdf = true;
} elseif ($userRole === 'barangay') {
    $filterHeaderEyebrow = 'Barangay Control';
    $filterHeaderTitle = 'Crime Map - My Area';
    $mapSubtitle = 'Barangay area view';
    $filterHint = 'Showing incidents in your assigned area only. Filters apply automatically.';
    $isBarangayFiltered = true;
    $showPendingStatus = true;
    $showDetailsHeader = false;
    $validationLabel = 'Verification & Status';
    $validationButtons = 'barangay';
    $showReportCrimeButton = true;
    $showExportPdf = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Crime Map | La Trinidad</title>
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
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
                    <div class="eyebrow"><?php echo htmlspecialchars($filterHeaderEyebrow, ENT_QUOTES, 'UTF-8'); ?></div>
                    <h2><?php echo htmlspecialchars($filterHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                </div>
                <div class="u-hstack">
                    <button type="button" id="close-filters" class="btn-tertiary close-filters">Close</button>
                </div>
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
                    <option value="under_investigation">Under investigation</option>
                    <option value="action_taken">Action taken</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Marker Style</label>
                <div class="toggle-row">
                    <button type="button" class="toggle-btn is-active" data-style="icon">Category icon</button>
                    <button type="button" class="toggle-btn" data-style="dot">Colored dots</button>
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn-secondary" id="reset-filters">Reset</button>
            </div>
            <p class="muted filter-hint"><?php echo htmlspecialchars($filterHint, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="muted" id="barangay-filter-notice"></p>
        </aside>

            <main class="map-stage">
            <header class="map-topbar">
                <div class="brand">
                    <img class="brand-logo" src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                    <div>
                        <div class="brand-title">La Trinidad Crime Mapping</div>
                        <div class="brand-subtitle"><?php echo htmlspecialchars($mapSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
                <?php require_once __DIR__ . '/_navbar.php'; render_navbar('map', $userRole ?? 'public'); ?>
            </header>

            <!-- Hamburger button to toggle filters (top-left of map) -->
            <button id="hamburger-filters" class="hamburger-btn" aria-label="Open filters">
                <svg width="18" height="14" viewBox="0 0 18 14" fill="none" xmlns="http://www.w3.org/2000/svg"><rect y="1" width="18" height="2" rx="1" fill="currentColor"/><rect y="6" width="18" height="2" rx="1" fill="currentColor"/><rect y="11" width="18" height="2" rx="1" fill="currentColor"/></svg>
            </button>

            <label class="map-search-bar" aria-label="Search incidents">
                <span class="map-search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </span>
                <input id="search-input" type="text" placeholder="Search incidents, names, or places" autocomplete="off" />
            </label>

            <div id="map" class="map-canvas map-full"></div>
        </main>

        <aside class="map-details" id="details-panel">
            <div class="details-header">
                <h2 id="details-title">Incident Details</h2>
            </div>

            <div class="detail-gallery" id="sidebar-gallery" style="display: none;">
                <div class="gallery-label">Evidence Images</div>
                <div class="image-carousel" id="sidebar-image-carousel">
                    <p class="muted">No images uploaded yet.</p>
                </div>
            </div>

            <div class="details-body" id="details-body">
                <p class="muted">Click a marker to view the full report.</p>
            </div>

            <div class="validation-panel">
                <div class="validation-label">Is this report accurate?</div>
                <div class="validation-buttons">
                    <button type="button" class="validation-btn" id="credible-btn">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M22 11.08V12a10 10 0 1 1-5.93-9.14'/%3E%3Cpolyline points='22 4 12 14.01 9 11.01'/%3E%3C/svg%3E" alt="credible" class="validation-icon" />
                        <div class="validation-text">
                            <span class="validation-label-small">Credible</span>
                            <span class="validation-count" id="credible-count">0</span>
                        </div>
                    </button>
                    <button type="button" class="validation-btn" id="not-credible-btn">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cline x1='15' y1='9' x2='9' y2='15'/%3E%3Cline x1='9' y1='9' x2='15' y2='15'/%3E%3C/svg%3E" alt="not credible" class="validation-icon" />
                        <div class="validation-text">
                            <span class="validation-label-small">Not Credible</span>
                            <span class="validation-count" id="not-credible-count">0</span>
                        </div>
                    </button>
                </div>
            </div>
            <div class="details-actions">
                <?php if ($showReportCrimeButton): ?>
                    <button type="button" class="btn-primary" id="report-crime">Report a crime</button>
                <?php endif; ?>
                <?php if (in_array($userRole, ['admin', 'barangay'], true)): ?>
                    <button type="button" class="btn-secondary" id="verify-btn">Verify</button>
                    <button type="button" class="btn-secondary" id="escalate-btn">Escalate</button>
                <?php endif; ?>
            </div>

            <div class="report-panel" id="report-panel">
                <div class="details-header">
                    <div>
                        <div class="eyebrow">Submit Report</div>
                        <h2>Report a crime</h2>
                    </div>
                    <button type="button" id="close-report" class="btn-tertiary">Close</button>
                </div>
                <form id="report-form" class="form-grid">
                    <label>
                        <span>Location</span>
                        <input id="report-coords" type="text" placeholder="Click the map or type a location" autocomplete="off" />
                        <small class="muted">Type a place name, address, or coordinates. The marker updates automatically.</small>
                    </label>
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
                    <label<?php echo $userRole === 'admin' ? '' : ' class="u-hidden"'; ?>>
                        <span>Barangay</span>
                        <select id="report-barangay" required></select>
                    </label>
                    <input type="hidden" id="report-barangay-hidden" />
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
                        <span>Evidence Images</span>
                        <input id="report-images" type="file" multiple accept="image/*" />
                        <small class="muted">Upload one or more images as evidence. Supported formats: JPG, PNG, GIF, WebP</small>
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
        window.csrfToken = <?php echo json_encode(csrf_token()); ?>;
        window.userRole = <?php echo json_encode($_SESSION['role'] ?? null); ?>;
        window.userBarangayName = <?php echo json_encode($barangayName); ?>;
        window.currentUser = {
            id: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
            username: <?php echo json_encode($_SESSION['username'] ?? null); ?>,
            barangay_id: <?php echo json_encode($_SESSION['barangay_id'] ?? null); ?>
        };
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="../assets/js/map.js"></script>
</body>
</html>
