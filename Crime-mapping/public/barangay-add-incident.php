<?php
require __DIR__ . '/guard.php';
requireRole(['barangay']);
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Add Incident | Barangay Dashboard</title>
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
                    <div class="brand-title">Add Crime Report</div>
                    <div class="brand-subtitle">Quick incident entry</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('add-incident', 'barangay'); ?>
        </header>

        <main class="u-content-narrow">
            <section class="hero hero-tight u-mb-32">
                <div class="hero-copy">
                    <p class="eyebrow">Incident Reporting</p>
                    <h1>Add a New Crime Report</h1>
                    <p class="lead">Report a crime incident in your barangay. Verified reports will appear on the public map.</p>
                </div>
            </section>

            <div class="panel">
                <form id="add-incident-form" class="form-grid">
                    <label>
                        <span>Crime Type *</span>
                        <select id="crime-type" required></select>
                    </label>
                    <label>
                        <span>Title / Headline *</span>
                        <input type="text" id="incident-title" placeholder="Brief description of the incident" required />
                    </label>
                    <label>
                        <span>Description *</span>
                        <textarea id="incident-description" rows="4" placeholder="What happened? Provide details..." required></textarea>
                    </label>
                    <label>
                        <span>Location *</span>
                        <input type="text" id="incident-location" placeholder="Enter a place name, address, or coordinates" required />
                        <small class="muted">Type a location name or coordinate pair. The system will geocode it automatically.</small>
                    </label>
                    <label>
                        <span>Date *</span>
                        <input type="date" id="incident-date" required />
                    </label>
                    <label>
                        <span>Time *</span>
                        <input type="time" id="incident-time" required />
                    </label>
                    <label>
                        <span>Severity *</span>
                        <select id="incident-severity" required>
                            <option value="">Select severity level...</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Submit Report</button>
                        <button type="button" class="btn-secondary" onclick="window.location.href='barangay-dashboard.php'">Cancel</button>
                    </div>
                    <p id="form-status" class="muted"></p>
                </form>
            </div>
        </main>

        <footer class="site-footer">
            <div>Barangay Incident Reporting</div>
            <div>Quick and efficient crime documentation.</div>
        </footer>
    </div>

    <script>
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const apiBase = '../api';

        function parseLocationCoordinates(value) {
            const cleaned = String(value || '').trim();
            const match = cleaned.match(/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/);
            if (!match) {
                return null;
            }

            const lat = Number(match[1]);
            const lng = Number(match[2]);
            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                return null;
            }

            return { lat, lng };
        }

        async function forwardGeocode(query) {
            try {
                const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(query)}`);
                if (!resp.ok) return null;
                const results = await resp.json();
                if (!Array.isArray(results) || !results.length) return null;
                const first = results[0];
                const lat = Number(first.lat);
                const lng = Number(first.lon);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return null;
                return { lat, lng };
            } catch (error) {
                console.error('Location geocode failed', error);
                return null;
            }
        }

        // Set default date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('incident-date').value = today;

        // Load crime types
        async function loadCrimeTypes() {
            try {
                const resp = await fetch(`${apiBase}/filters.php`);
                const result = await resp.json();
                if (result.ok && result.data.types) {
                    const select = document.getElementById('crime-type');
                    select.innerHTML = '<option value="">Select crime type...</option>';
                    result.data.types.forEach(type => {
                        const opt = document.createElement('option');
                        opt.value = type.crime_type_id;
                        opt.textContent = `${type.type_name} (${type.category.replace(/_/g, ' ')})`;
                        select.appendChild(opt);
                    });
                }
            } catch (e) {
                console.error('Failed to load crime types', e);
            }
        }

        document.getElementById('add-incident-form').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            
            const formStatus = document.getElementById('form-status');
            formStatus.textContent = 'Submitting...';

            try {
                const locationStr = document.getElementById('incident-location').value.trim();
                let location = parseLocationCoordinates(locationStr);

                if (!location) {
                    formStatus.textContent = 'Resolving location...';
                    location = await forwardGeocode(locationStr);
                }

                if (!location) {
                    formStatus.textContent = 'Unable to resolve the location. Please enter a clearer place name or coordinates.';
                    return;
                }

                // Get barangay name from session (set server-side) or from coordinates
                // For now, we'll send as part of the payload and server validates
                const payload = {
                    crime_type_id: parseInt(document.getElementById('crime-type').value),
                    title: document.getElementById('incident-title').value.trim(),
                    description: document.getElementById('incident-description').value.trim(),
                    latitude: location.lat,
                    longitude: location.lng,
                    occurred_date: document.getElementById('incident-date').value,
                    occurred_time: document.getElementById('incident-time').value,
                    severity: document.getElementById('incident-severity').value,
                    barangay: null // Will be filled by server based on user's barangay_id
                };

                const resp = await fetch(`${apiBase}/report.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const result = await resp.json();
                if (!result.ok) {
                    formStatus.textContent = result.error || 'Submission failed.';
                    return;
                }

                formStatus.textContent = 'Report submitted successfully!';
                setTimeout(() => {
                    window.location.href = 'barangay-dashboard.php';
                }, 1500);
            } catch (e) {
                formStatus.textContent = 'Error: ' + e.message;
                console.error(e);
            }
        });

        loadCrimeTypes();
    </script>
</body>
</html>
