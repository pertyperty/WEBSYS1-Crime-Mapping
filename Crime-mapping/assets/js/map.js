const apiBase = "../api";
let incidents = [];
let isBarangayMode = typeof userBarangayName !== 'undefined' && userBarangayName !== null;

let typeLabels = {};

const typeColors = {
    violent: "#f43f5e",
    property: "#facc15",
    drug: "#a855f7",
    traffic: "#22d3ee",
    cybercrime: "#38bdf8",
    white_collar: "#f97316",
    public_order: "#34d399",
    status_offense: "#fb7185"
};

const typeIconUrls = {
    violent: "../assets/images/icons/violent.svg",
    property: "../assets/images/icons/property.svg",
    drug: "../assets/images/icons/drug.svg",
    traffic: "../assets/images/icons/traffic.svg",
    cybercrime: "../assets/images/icons/cybercrime.svg",
    white_collar: "../assets/images/icons/white_collar.svg",
    public_order: "../assets/images/icons/public_order.svg",
    status_offense: "../assets/images/icons/status_offense.svg"
};

let barangays = [];

let map;
try {
    console.log("Initializing map container...");
    map = L.map("map").setView([16.455, 120.59], 12);
    console.log("Map initialized successfully");
    
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors"
    }).addTo(map);
    console.log("Tile layer added");
} catch (error) {
    console.error("Failed to initialize map:", error);
}

const markersLayer = L.layerGroup().addTo(map);
// Restrict map to La Trinidad bounds and animate back if user pans out
try {
    const laTrinidadBounds = L.latLngBounds([
        [16.4150, 120.5600], // SW
        [16.4800, 120.6050]  // NE
    ]);

    // Apply as max bounds with a small padding so users can see edge but not escape
    if (map && laTrinidadBounds.isValid()) {
        map.setMaxBounds(laTrinidadBounds.pad(0.02));

        function ensureInsideBounds() {
            const center = map.getCenter();
            if (!laTrinidadBounds.contains(center)) {
                const target = laTrinidadBounds.getCenter();
                try {
                    map.flyTo([target.lat, target.lng], Math.max(map.getZoom(), 13), { duration: 0.9, easeLinearity: 0.25 });
                } catch (e) {
                    try { map.setView([target.lat, target.lng]); } catch(ignore) {}
                }
            }
        }

        map.on('moveend', ensureInsideBounds);
        map.on('zoomend', ensureInsideBounds);
        map.on('dragend', ensureInsideBounds);
    }
} catch (e) {
    console.error('Failed to set map bounds for La Trinidad:', e);
}
const typeFilters = document.getElementById("type-filters");
const barangayFilter = document.getElementById("barangay-filter");
const searchInput = document.getElementById("search-input");
const dateStart = document.getElementById("date-start");
const dateEnd = document.getElementById("date-end");
const statusFilter = document.getElementById("status-filter");
const detailsPanel = document.getElementById("details-panel");
const detailsTitle = document.getElementById("details-title");
const detailsBody = document.getElementById("details-body");
const markerStyleButtons = document.querySelectorAll(".toggle-btn");
const reportPanel = document.getElementById("report-panel");
const resetButton = document.getElementById("reset-filters");

const filtersPanel = document.querySelector('.map-filters');
const toggleFiltersBtn = document.getElementById('toggle-filters');
const hamburgerFiltersBtn = document.getElementById('hamburger-filters');

// Make panels overlay on narrower screens or when user toggles
function ensureOverlayMode() {
    // Use overlay mode for desktop too so filters/details float as overlays
    if (filtersPanel) filtersPanel.classList.add('overlay');
    if (detailsPanel) detailsPanel.classList.add('overlay');
}
ensureOverlayMode();


ensureOverlayMode();
const mapShell = document.querySelector('.map-shell');
if (mapShell) mapShell.classList.add('overlay-mode');

if (toggleFiltersBtn && filtersPanel) {
    toggleFiltersBtn.addEventListener('click', () => {
        const isOpen = filtersPanel.classList.toggle('is-open');
        // focus first input when opened
        if (isOpen) {
            const input = filtersPanel.querySelector('input, select, button');
            if (input) input.focus();
        }
        showHamburger(!isOpen);
        // allow CSS transition, then update map size
        setTimeout(() => { try { map.invalidateSize(); } catch(e){} }, 300);
    });
}

if (hamburgerFiltersBtn && filtersPanel) {
    hamburgerFiltersBtn.addEventListener('click', () => {
        // open/close filters overlay
        const isOpen = filtersPanel.classList.toggle('is-open');
        if (isOpen) {
            // ensure details panel is closed so filters are visible
            if (detailsPanel) detailsPanel.classList.remove('is-open');
            const input = filtersPanel.querySelector('input, select, button');
            if (input) input.focus();
        }
        // hide hamburger when panel opens, show when closed
        showHamburger(!isOpen);
        setTimeout(() => { try { map.invalidateSize(); } catch(e){} }, 300);
    });
}

// Close button inside filters overlay
const closeFiltersBtn = document.getElementById('close-filters');

function showHamburger(visible) {
    if (!hamburgerFiltersBtn) return;
    if (visible) {
        hamburgerFiltersBtn.classList.remove('hidden');
    } else {
        hamburgerFiltersBtn.classList.add('hidden');
    }
}

// Keep hamburger visible initially
showHamburger(true);

// When filters open via toggle or hamburger, hide hamburger; when closed, show it
function watchFilterPanel() {
    if (!filtersPanel) return;
    const observer = new MutationObserver(() => {
        const open = filtersPanel.classList.contains('is-open');
        showHamburger(!open);
    });
    observer.observe(filtersPanel, { attributes: true, attributeFilter: ['class'] });
}
watchFilterPanel();

if (closeFiltersBtn && filtersPanel) {
    closeFiltersBtn.addEventListener('click', () => {
        filtersPanel.classList.remove('is-open');
        showHamburger(true);
        setTimeout(() => { try { map.invalidateSize(); } catch(e){} }, 300);
    });
}
const reportForm = document.getElementById("report-form");
const reportType = document.getElementById("report-type");
const reportTitle = document.getElementById("report-title");
const reportDescription = document.getElementById("report-description");
const reportBarangay = document.getElementById("report-barangay");
const reportBarangayHidden = document.getElementById("report-barangay-hidden");
const reportDate = document.getElementById("report-date");
const reportTime = document.getElementById("report-time");
const reportSeverity = document.getElementById("report-severity");
const reportCoords = document.getElementById("report-coords");
const reportStatus = document.getElementById("report-status");
const reportButton = document.getElementById("report-crime");
const reportClose = document.getElementById("close-report");
const reportCancel = document.getElementById("report-cancel");
const credibleBtn = document.getElementById("credible-btn");
const notCredibleBtn = document.getElementById("not-credible-btn");
const credibleCount = document.getElementById("credible-count");
const notCredibleCount = document.getElementById("not-credible-count");
const detailModal = document.getElementById("detail-modal");
const modalTitle = document.getElementById("modal-title");
const detailInfo = document.getElementById("detail-info");
const imageCarousel = document.getElementById("image-carousel");
const exportPdfButton = document.getElementById("export-pdf-btn");
const detailImageInput = document.getElementById("detail-image-input");
const uploadStatus = document.getElementById("upload-status");
const closeModal = document.getElementById("close-modal");
const csrfToken = window.csrfToken || "";

function csrfHeaders(extraHeaders = {}) {
    return csrfToken
        ? { ...extraHeaders, "X-CSRF-Token": csrfToken }
        : extraHeaders;
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function renderIncidentDetailsHtml(incident) {
    const safeBarangay = escapeHtml(incident.barangay);
    const safeOccurredAt = escapeHtml(incident.occurred_at || incident.date);
    const safeStatus = escapeHtml(incident.status ? formatStatus(incident.status) : "");
    const safeSeverity = escapeHtml(incident.severity);
    const safeTypeName = escapeHtml(incident.type_name || incident.type || "");
    const safeReportedBy = escapeHtml(incident.reported_by);
    const safeDescription = escapeHtml(incident.description);

    return `
        <div>
            <p><strong>Barangay:</strong> ${safeBarangay}</p>
            <p><strong>Date:</strong> ${safeOccurredAt}</p>
            ${incident.status ? `<p><strong>Status:</strong> ${safeStatus}</p>` : ""}
            ${incident.severity ? `<p><strong>Severity:</strong> ${safeSeverity}</p>` : ""}
            ${safeTypeName ? `<p><strong>Type:</strong> ${safeTypeName}</p>` : ""}
            ${incident.reported_by ? `<p><strong>Reported by:</strong> ${safeReportedBy}</p>` : ""}
            <p class="muted" style="margin-top: 12px;">${safeDescription}</p>
        </div>
    `;
}

function updateExportLink(incidentId) {
    if (!exportPdfButton) return;

    if (!incidentId) {
        exportPdfButton.setAttribute("href", "#");
        exportPdfButton.setAttribute("aria-disabled", "true");
        return;
    }

    exportPdfButton.setAttribute("href", `admin-incident-export.php?incident_id=${encodeURIComponent(incidentId)}`);
    exportPdfButton.removeAttribute("aria-disabled");
}

function setValidationSelection(userReaction) {
    if (!credibleBtn || !notCredibleBtn) return;
    credibleBtn.classList.remove("is-active");
    notCredibleBtn.classList.remove("is-active");

    if (userReaction === "credible") {
        credibleBtn.classList.add("is-active");
    } else if (userReaction === "not_credible") {
        notCredibleBtn.classList.add("is-active");
    }
}

function clearValidationPanelState() {
    if (credibleCount) credibleCount.textContent = "0";
    if (notCredibleCount) notCredibleCount.textContent = "0";
    setValidationSelection(null);
}

function openDetailSidebar(incident) {
    if (!incident || !incident.id) return;

    if (detailsPanel) {
        detailsPanel.classList.add("is-open");
        detailsPanel.classList.add("overlay");
    }
    if (filtersPanel) filtersPanel.classList.remove("is-open");

    currentIncidentId = incident.id;
    updateExportLink(currentIncidentId);

    if (detailsTitle) detailsTitle.textContent = incident.title || "Incident";
    if (detailsBody) detailsBody.innerHTML = '<p class="muted">Loading details...</p>';

    const validationPanelEl = document.querySelector(".validation-panel");
    if (validationPanelEl) validationPanelEl.style.display = "";

    loadValidationCounts();
    loadIncidentDetail(incident.id, {
        titleElement: detailsTitle,
        infoElement: detailsBody,
        includeImages: false
    });
}

let markerStyle = "icon";
let activeTypes = new Set();
let reportLatLng = null;
let reportTypes = [];
let currentIncidentId = null;
let filterTimer = null;
let tempMarker = null;

async function reverseGeocode(lat, lng) {
    try {
        const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`);
        if (!resp.ok) return null;
        const json = await resp.json();
        // prefer address.road + house_number + city
        if (json && json.display_name) return json.display_name;
        return null;
    } catch (e) {
        console.error('Reverse geocode failed', e);
        return null;
    }
}

function buildTypeFilters() {
    typeFilters.innerHTML = "";
    Object.entries(typeLabels).forEach(([key, label]) => {
        const wrapper = document.createElement("label");
        wrapper.className = "checkbox-item";

        const input = document.createElement("input");
        input.type = "checkbox";
        input.setAttribute("data-type", key);
        input.checked = true;

        const span = document.createElement("span");
        span.textContent = label;

        wrapper.appendChild(input);
        wrapper.appendChild(span);
        typeFilters.appendChild(wrapper);
    });

    typeFilters.querySelectorAll("input[type='checkbox']").forEach((input) => {
        input.addEventListener("change", () => {
            const type = input.getAttribute("data-type");
            if (input.checked) {
                activeTypes.add(type);
            } else {
                activeTypes.delete(type);
            }

            scheduleLoadIncidents();
        });
    });
}

function scheduleLoadIncidents() {
    if (filterTimer) {
        clearTimeout(filterTimer);
    }

    filterTimer = setTimeout(() => {
        loadIncidents();
    }, 150);
}

function buildBarangayOptions() {
    if (!barangayFilter) {
        console.warn("barangayFilter element not found");
        return;
    }
    if (isBarangayMode && userBarangayName) {
        barangayFilter.innerHTML = "";
        const option = document.createElement("option");
        option.value = userBarangayName;
        option.textContent = userBarangayName;
        barangayFilter.appendChild(option);
        barangayFilter.value = userBarangayName;
        return;
    }

    barangayFilter.innerHTML = '<option value="">All barangays</option>';
    barangays.forEach((barangay) => {
        const option = document.createElement("option");
        option.value = barangay;
        option.textContent = barangay;
        barangayFilter.appendChild(option);
    });
}

function buildReportTypeOptions() {
    if (!reportType) {
        console.warn("reportType element not found");
        return;
    }
    reportType.innerHTML = "";
    reportTypes.forEach((type) => {
        const option = document.createElement("option");
        option.value = type.crime_type_id;
        option.textContent = `${type.type_name} (${type.category.replace(/_/g, " ")})`;
        reportType.appendChild(option);
    });
}

function buildReportBarangayOptions() {
    if (!reportBarangay) {
        console.warn("reportBarangay element not found");
        return;
    }

    // If the reportBarangay is a select, populate options (kept hidden for guests).
    if (reportBarangay.tagName && reportBarangay.tagName.toLowerCase() === 'select') {
        reportBarangay.innerHTML = "";
        barangays.forEach((barangay) => {
            const option = document.createElement("option");
            option.value = barangay;
            option.textContent = barangay;
            reportBarangay.appendChild(option);
        });
    }

    // Ensure hidden input is present and set to empty by default
    if (reportBarangayHidden) reportBarangayHidden.value = "";
}

function formatStatus(status) {
    return status.replace(/_/g, " ");
}

function isWithinDateRange(dateString) {
    const start = dateStart.value ? new Date(dateStart.value) : null;
    const end = dateEnd.value ? new Date(dateEnd.value) : null;
    const date = new Date(dateString);

    if (start && date < start) {
        return false;
    }
    if (end && date > end) {
        return false;
    }
    return true;
}

function createMarker(incident) {
    if (markerStyle === "icon") {
        return L.marker([incident.lat, incident.lng], {
            icon: L.icon({
                iconUrl: typeIconUrls[incident.type] || "../assets/images/icons/violent.svg",
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -28]
            })
        });
    }
    return L.circleMarker([incident.lat, incident.lng], {
        radius: 7,
        color: typeColors[incident.type] || "#22d3ee",
        fillOpacity: 0.85
    });
}

function renderMarkers() {
    try {
        console.log("Rendering markers...");
        markersLayer.clearLayers();

        incidents
            .filter((incident) => (activeTypes.size ? activeTypes.has(incident.type) : true))
            .filter((incident) => (barangayFilter && barangayFilter.value ? incident.barangay === barangayFilter.value : true))
            .filter((incident) => (statusFilter && statusFilter.value ? incident.status === statusFilter.value : true))
            .filter((incident) => isWithinDateRange(incident.date))
            .forEach((incident) => {
                const marker = createMarker(incident).addTo(markersLayer);
                const safeTitle = escapeHtml(incident.title);
                const safeBarangay = escapeHtml(incident.barangay);
                const safeDate = escapeHtml(incident.date);
                const safeDescription = escapeHtml(incident.description);
                marker.bindTooltip(
                    `<strong>${safeTitle}</strong><br>${safeBarangay} • ${safeDate}<br>${safeDescription}`,
                    { direction: "top" }
                );
                marker.on("click", () => openDetailSidebar(incident));
            });
        console.log(`Rendered ${incidents.length} markers`);
    } catch (error) {
        console.error("Error rendering markers:", error);
    }
}

async function loadIncidentDetail(incidentId, options = {}) {
    const {
        titleElement = modalTitle,
        infoElement = detailInfo,
        includeImages = true
    } = options;

    try {
        const response = await fetch(`${apiBase}/incident-detail.php?incident_id=${incidentId}`);
        const data = await response.json();

        if (!data.ok) {
            if (infoElement) {
                infoElement.innerHTML = '<p class="muted">Failed to load incident details.</p>';
            }
            return;
        }

        const incident = data.incident;
        if (titleElement) {
            titleElement.textContent = incident.title;
        }

        if (infoElement) {
            infoElement.innerHTML = renderIncidentDetailsHtml(incident);
        }

        if (includeImages) {
            renderImages(data.images);
        }

        return data;
    } catch (error) {
        console.error("Failed to load incident detail", error);
        if (infoElement) {
            infoElement.innerHTML = '<p class="muted">Failed to load incident details.</p>';
        }
    }
}

function renderImages(images) {
    imageCarousel.innerHTML = "";
    if (!images || images.length === 0) {
        imageCarousel.innerHTML = '<p class="muted">No images uploaded yet.</p>';
        return;
    }

    images.forEach((img) => {
        const imgElement = document.createElement("img");
        imgElement.src = "../" + img.file_path;
        imgElement.alt = "Evidence";
        imgElement.className = "image-thumbnail";
        imgElement.addEventListener("click", () => viewImageFull(img.file_path));
        imageCarousel.appendChild(imgElement);
    });
}

function viewImageFull(filePath) {
    window.open("../" + filePath, "_blank");
}

function openDetailModal(incident) {
    // When opening the detail modal, avoid also populating the side details
    // to prevent duplicate rendering. Ensure the side panel is closed.
    try { detailsPanel.classList.remove('is-open'); } catch (e) {}
    currentIncidentId = incident.id;
    updateExportLink(currentIncidentId);
    detailModal.classList.add("is-open");
    uploadStatus.textContent = "";
    loadValidationCounts();
    loadIncidentDetail(incident.id, {
        titleElement: modalTitle,
        infoElement: detailInfo,
        includeImages: true
    });
}

function closeDetailModal() {
    detailModal.classList.remove("is-open");
    currentIncidentId = null;
    updateExportLink(null);
    uploadStatus.textContent = "";
}

function openReportPanel() {
    reportPanel.classList.add("is-open");
    reportPanel.style.display = ""; // Ensure visible for authenticated users
    detailsBody.classList.add("is-hidden");
    reportStatus.textContent = "";
    
    // Update form title based on user role
    const reportHeaderTitle = reportPanel.querySelector('h2');
    if (reportHeaderTitle) {
        if (window.userRole === 'admin' || window.userRole === 'barangay') {
            reportHeaderTitle.textContent = 'Add Crime';
        } else {
            reportHeaderTitle.textContent = 'Report a crime';
        }
    }
}

function closeReportPanel() {
    reportPanel.classList.remove("is-open");
    detailsBody.classList.remove("is-hidden");
}

function setReportCoords(latlng) {
    reportLatLng = latlng;
    reportCoords.value = `${latlng.lat.toFixed(5)}, ${latlng.lng.toFixed(5)}`;
}

function resetFilters() {
    activeTypes = new Set(Object.keys(typeLabels));
    buildTypeFilters();
    if (barangayFilter) {
        barangayFilter.value = isBarangayMode && userBarangayName ? userBarangayName : "";
    }
    if (statusFilter) statusFilter.value = "";
    if (dateStart) dateStart.value = "";
    if (dateEnd) dateEnd.value = "";
    if (searchInput) searchInput.value = "";
}

async function loadFilters() {
    try {
        const response = await fetch(`${apiBase}/filters.php`);
        const payload = await response.json();
        if (!payload.ok) {
            return;
        }

        reportTypes = payload.data.types;
        typeLabels = payload.data.types.reduce((acc, item) => {
            if (!acc[item.category]) {
                acc[item.category] = item.category.replace(/_/g, " ");
            }
            return acc;
        }, {});

        barangays = payload.data.barangays;
        buildTypeFilters();
        buildBarangayOptions();
        buildReportTypeOptions();
        buildReportBarangayOptions();

        if (payload.data.date_range.min) {
            dateStart.value = payload.data.date_range.min.slice(0, 10);
        }
        if (payload.data.date_range.max) {
            dateEnd.value = payload.data.date_range.max.slice(0, 10);
        }

        resetFilters();
    } catch (error) {
        console.error("Failed to load filters", error);
    }
}

function buildQueryParams() {
    const params = new URLSearchParams();
    if (activeTypes.size) {
        params.set("types", Array.from(activeTypes).join(","));
    }
    if (barangayFilter && barangayFilter.value) {
        params.set("barangay", barangayFilter.value);
    } else if (isBarangayMode && userBarangayName) {
        params.set("barangay", userBarangayName);
    }
    if (statusFilter && statusFilter.value) {
        params.set("status", statusFilter.value);
    }
    if (dateStart && dateStart.value) {
        params.set("date_start", dateStart.value);
    }
    if (dateEnd && dateEnd.value) {
        params.set("date_end", dateEnd.value);
    }
    if (searchInput && searchInput.value.trim()) {
        params.set("search", searchInput.value.trim());
    }
    return params.toString();
}

async function loadIncidents() {
    try {
        const query = buildQueryParams();
        const response = await fetch(`${apiBase}/incidents.php?${query}`);
        const payload = await response.json();
        incidents = payload.ok ? payload.data : [];
        renderMarkers();
    } catch (error) {
        console.error("Failed to load incidents", error);
        incidents = [];
        renderMarkers();
    }
}

markerStyleButtons.forEach((button) => {
    button.addEventListener("click", () => {
        markerStyleButtons.forEach((btn) => btn.classList.remove("is-active"));
        button.classList.add("is-active");
        markerStyle = button.getAttribute("data-style");
        renderMarkers();
    });
});

async function loadValidationCounts() {
    if (!currentIncidentId) {
        return;
    }

    try {
        const response = await fetch(`${apiBase}/validate-report.php?incident_id=${currentIncidentId}`, {
            method: "GET"
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.ok) {
                credibleCount.textContent = data.credible || 0;
                notCredibleCount.textContent = data.not_credible || 0;
                setValidationSelection(data.user_reaction || null);
            }
        }
    } catch (error) {
        console.error("Failed to load validation counts", error);
    }
}

async function submitValidation(reaction) {
    if (!currentIncidentId) {
        return;
    }

    try {
        const response = await fetch(`${apiBase}/validate-report.php`, {
            method: "POST",
            headers: {
                ...csrfHeaders({
                    "Content-Type": "application/json"
                })
            },
            body: JSON.stringify({
                incident_id: currentIncidentId,
                reaction: reaction
            })
        });

        const data = await response.json();
        if (data.ok) {
            credibleCount.textContent = data.credible || 0;
            notCredibleCount.textContent = data.not_credible || 0;
            setValidationSelection(data.user_reaction || null);
        }
    } catch (error) {
        console.error("Failed to submit validation", error);
    }
}

credibleBtn?.addEventListener("click", () => {
    if (!currentIncidentId) return;
    submitValidation("credible");
});

notCredibleBtn?.addEventListener("click", () => {
    if (!currentIncidentId) return;
    submitValidation("not_credible");
});

// Verify / Escalate buttons (barangay/admin controls)
const verifyBtnEl = document.getElementById("verify-btn");
const escalateBtnEl = document.getElementById("escalate-btn");
const userRole = window.userRole || null;

// Hide verify/escalate controls for unauthorized clients as an extra safety layer.
if (verifyBtnEl || escalateBtnEl) {
    // If no userRole from server, hide the buttons.
    if (!userRole || (userRole !== 'admin' && userRole !== 'barangay')) {
        verifyBtnEl?.classList.add('hidden');
        escalateBtnEl?.classList.add('hidden');
        if (verifyBtnEl) verifyBtnEl.style.display = 'none';
        if (escalateBtnEl) escalateBtnEl.style.display = 'none';
    } else {
        // Ensure visible when authorized (admin/barangay)
        if (verifyBtnEl) verifyBtnEl.style.display = '';
        if (escalateBtnEl) escalateBtnEl.style.display = '';
    }
}

async function updateIncidentStatus(newStatus, remarks = '') {
    if (!currentIncidentId) return;
    try {
        if (uploadStatus) uploadStatus.textContent = 'Updating status...';
        const response = await fetch(`${apiBase}/update-status.php`, {
            method: 'POST',
            headers: {
                ...csrfHeaders({ 'Content-Type': 'application/json' })
            },
            body: JSON.stringify({
                incident_id: currentIncidentId,
                new_status: newStatus,
                remarks: remarks
            })
        });

        const data = await response.json();
        if (data.ok) {
            // refresh markers and detail
            await loadIncidents();
            await loadIncidentDetail(currentIncidentId);
            if (uploadStatus) uploadStatus.textContent = 'Status updated.';
        } else {
            console.error('Status update failed', data.error);
            if (uploadStatus) uploadStatus.textContent = data.error || 'Status update failed.';
        }
    } catch (error) {
        console.error('Failed to update status', error);
        if (uploadStatus) uploadStatus.textContent = 'Status update failed.';
    } finally {
        setTimeout(() => { if (uploadStatus) uploadStatus.textContent = ''; }, 2500);
    }
}

verifyBtnEl?.addEventListener('click', () => {
    updateIncidentStatus('under_investigation', 'Verified via UI');
});

escalateBtnEl?.addEventListener('click', () => {
    updateIncidentStatus('action_taken', 'Escalated via UI');
});

if (searchInput) searchInput.addEventListener("input", scheduleLoadIncidents);
if (barangayFilter) barangayFilter.addEventListener("change", loadIncidents);
if (statusFilter) statusFilter.addEventListener("change", loadIncidents);
if (dateStart) dateStart.addEventListener("change", loadIncidents);
if (dateEnd) dateEnd.addEventListener("change", loadIncidents);

if (resetButton) {
    resetButton.addEventListener("click", () => {
        resetFilters();
        loadIncidents();
    });
}

const closeDetailsBtn = document.getElementById("close-details");
if (closeDetailsBtn) {
    closeDetailsBtn.addEventListener("click", () => {
        detailsPanel.classList.remove("is-open");
    });
}

closeModal.addEventListener("click", closeDetailModal);

if (detailImageInput) {
    detailImageInput.addEventListener("change", async (event) => {
        const file = event.target.files[0];
        if (!file || !currentIncidentId) {
            return;
        }

        const formData = new FormData();
        formData.append("incident_id", currentIncidentId);
        formData.append("image", file);

        if (uploadStatus) uploadStatus.textContent = "Uploading...";

        try {
            const response = await fetch(`${apiBase}/upload-image.php`, {
                method: "POST",
                headers: csrfHeaders(),
                body: formData
            });

            const result = await response.json();
            if (!result.ok) {
                if (uploadStatus) uploadStatus.textContent = result.error || "Upload failed.";
                return;
            }

            if (uploadStatus) uploadStatus.textContent = "Image uploaded successfully.";
            detailImageInput.value = "";
            loadIncidentDetail(currentIncidentId);
        } catch (error) {
            console.error("Image upload failed", error);
            if (uploadStatus) uploadStatus.textContent = "Upload failed. Please try again.";
        }
    });
}

if (reportButton) reportButton.addEventListener("click", openReportPanel);
if (reportClose) reportClose.addEventListener("click", closeReportPanel);
if (reportCancel) reportCancel.addEventListener("click", closeReportPanel);

// Restore pending report after login/register if present
(function tryRestorePendingReport() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('resume_report')) {
        const raw = sessionStorage.getItem('pending_report');
        if (!raw) return;
        try {
            const draft = JSON.parse(raw);
            if (draft.latitude && draft.longitude) {
                const latlng = L.latLng(draft.latitude, draft.longitude);
                // place marker
                try { if (tempMarker) markersLayer.removeLayer(tempMarker); } catch(e){}
                tempMarker = L.marker([latlng.lat, latlng.lng], { draggable: true }).addTo(markersLayer);
                setReportCoords(latlng);
                // populate fields
                if (draft.crime_type_id) reportType.value = draft.crime_type_id;
                if (draft.title) reportTitle.value = draft.title;
                if (draft.description) reportDescription.value = draft.description;
                if (draft.occurred_date) reportDate.value = draft.occurred_date;
                if (draft.occurred_time) reportTime.value = draft.occurred_time;
                if (draft.severity) reportSeverity.value = draft.severity;
                if (draft.barangay) {
                    if (reportBarangayHidden) reportBarangayHidden.value = draft.barangay;
                    if (reportBarangay && reportBarangay.tagName && reportBarangay.tagName.toLowerCase() === 'select') reportBarangay.value = draft.barangay;
                }
                openReportPanel();
            }
        } catch (e) {
            console.error('Failed to restore pending report', e);
        }
    }
})();

map.on("click", (event) => {
    if (!reportPanel || !reportPanel.classList.contains("is-open")) {
        return;
    }
    setReportCoords(event.latlng);
});

// Open details panel at clicked location and show nearby pins or message
map.on('click', (event) => {
    try {
        const latlng = event.latlng;
        // find incidents within 50 meters
        const nearby = incidents.filter(i => {
            if (!i.lat || !i.lng) return false;
            try {
                return L.latLng(i.lat, i.lng).distanceTo(latlng) <= 50;
            } catch (e) {
                return false;
            }
        });

        // ensure details panel is overlay and visible
        if (detailsPanel) {
            detailsPanel.classList.add('is-open');
            detailsPanel.classList.add('overlay');
            if (filtersPanel) filtersPanel.classList.remove('is-open');
        }

        if (!detailsBody) return;

        const validationPanelEl = document.querySelector('.validation-panel');

            if (nearby.length === 0) {
            // hide validation UI when there are no incidents at clicked location
            if (validationPanelEl) validationPanelEl.style.display = 'none';

            // Allow anyone (including guests) to place a temporary marker and open report form.
            try {
                if (typeof tempMarker !== 'undefined' && tempMarker) {
                    try { markersLayer.removeLayer(tempMarker); } catch(e){}
                }
                tempMarker = L.marker([latlng.lat, latlng.lng], { draggable: true }).addTo(markersLayer);
                setReportCoords(latlng);
                openReportPanel();

                // reverse geocode and try to infer barangay from address
                try {
                    reverseGeocode(latlng.lat, latlng.lng).then(addr => {
                        if (reportCoords) reportCoords.value = addr || `${latlng.lat.toFixed(5)}, ${latlng.lng.toFixed(5)}`;
                        if (addr && barangays && barangays.length) {
                            const lowerAddr = addr.toLowerCase();
                            const match = barangays.find(b => lowerAddr.includes(String(b).toLowerCase()));
                            if (match) {
                                if (reportBarangayHidden) reportBarangayHidden.value = match;
                                if (reportBarangay && reportBarangay.tagName && reportBarangay.tagName.toLowerCase() === 'select') reportBarangay.value = match;
                            }
                        }
                    }).catch(e=>{});
                } catch(e){}

                // allow dragging to update coords
                tempMarker.on('dragend', (ev) => {
                    const p = ev.target.getLatLng();
                    setReportCoords(p);
                    reverseGeocode(p.lat, p.lng).then(addr => {
                        if (addr && barangays && barangays.length) {
                            const lowerAddr = addr.toLowerCase();
                            const match = barangays.find(b => lowerAddr.includes(String(b).toLowerCase()));
                            if (match) {
                                if (reportBarangayHidden) reportBarangayHidden.value = match;
                                if (reportBarangay && reportBarangay.tagName && reportBarangay.tagName.toLowerCase() === 'select') reportBarangay.value = match;
                            }
                        }
                    }).catch(()=>{});
                });
            } catch (e) {
                console.error('Failed to place temporary marker', e);
            }
            return;
        }
        // hide validation until a specific incident is selected from the nearby list
        if (validationPanelEl) validationPanelEl.style.display = 'none';
        currentIncidentId = null;
        clearValidationPanelState();

        detailsTitle.textContent = `${nearby.length} incident${nearby.length>1?'s':''} nearby`;
        // build a simple clickable list
        const list = document.createElement('div');
        list.style.display = 'flex';
        list.style.flexDirection = 'column';
        list.style.gap = '8px';

        nearby.forEach((inc) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-secondary';
            btn.style.textAlign = 'left';
            btn.textContent = `${inc.title || 'Untitled'} — ${inc.barangay || ''}`;
            btn.addEventListener('click', () => {
                // pan map to marker and open detail sidebar
                try {
                    map.setView([inc.lat, inc.lng], Math.max(map.getZoom(), 15));
                } catch (e) {}
                openDetailSidebar(inc);
            });
            list.appendChild(btn);
        });

        detailsBody.innerHTML = '';
        detailsBody.appendChild(list);
    } catch (error) {
        console.error('Error handling map click for details:', error);
    }
});

if (reportForm) {
    reportForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!reportLatLng) {
            if (reportStatus) reportStatus.textContent = "Please click on the map to set the report location.";
            return;
        }

        const draft = {
            crime_type_id: reportType.value,
            title: reportTitle.value.trim(),
            description: reportDescription.value.trim(),
            barangay: reportBarangayHidden.value || reportBarangay.value,
            occurred_date: reportDate.value,
            occurred_time: reportTime.value,
            severity: reportSeverity.value,
            latitude: reportLatLng.lat,
            longitude: reportLatLng.lng
        };

        // Guest redirect + draft save
        if (!window.currentUser || !window.currentUser.id) {
            try {
                sessionStorage.setItem('pending_report', JSON.stringify(draft));
                const next = 'map.php?resume_report=1';
                window.location.href = `login.php?next=${encodeURIComponent(next)}`;
                return;
            } catch (e) {
                console.error('Failed to save draft', e);
                if (reportStatus) reportStatus.textContent = 'Unable to save draft. Please try again.';
                return;
            }
        }

        // Authenticated user submit
        const payload = {
            crime_type_id: draft.crime_type_id,
            title: draft.title,
            description: draft.description,
            barangay: draft.barangay,
            occurred_date: draft.occurred_date,
            occurred_time: draft.occurred_time,
            severity: draft.severity,
            latitude: draft.latitude,
            longitude: draft.longitude,
            source: window.userRole ? 'direct' : 'reported'
        };

        if (reportStatus) reportStatus.textContent = window.userRole ? "Adding crime..." : "Submitting report...";
        try {
            const response = await fetch(`${apiBase}/report.php`, {
                method: "POST",
                headers: {
                    ...csrfHeaders({
                        "Content-Type": "application/json"
                    })
                },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (!result.ok) {
                if (reportStatus) reportStatus.textContent = result.error || "Submission failed.";
                return;
            }

            if (reportStatus) {
                if (window.userRole) {
                    reportStatus.textContent = "Crime added successfully.";
                } else {
                    reportStatus.textContent = "Report submitted. Awaiting verification.";
                }
            }
            reportForm.reset();
            reportLatLng = null;
            if (reportCoords) reportCoords.value = "";
            
            // Clear pending draft if any
            try { sessionStorage.removeItem('pending_report'); } catch(e){}
            
            // Close the form and reload incidents
            closeReportPanel();
            loadIncidents();
            setTimeout(() => { if (reportStatus) reportStatus.textContent = ''; }, 2500);
        } catch (error) {
            console.error("Report submission failed", error);
            if (reportStatus) reportStatus.textContent = "Submission failed. Please try again.";
        }
    });
}

loadFilters().then(() => {
    console.log("Filters loaded successfully");
    // If in barangay mode, set up barangay-specific filtering
    if (isBarangayMode && userBarangayName) {
        // Set the barangay filter to the user's assigned barangay
        if (barangayFilter) {
            barangayFilter.value = userBarangayName;
            barangayFilter.disabled = true;
            barangayFilter.title = `Limited to your barangay: ${userBarangayName}`;
        }

        const barangayFilterNotice = document.getElementById("barangay-filter-notice");
        if (barangayFilterNotice) {
            barangayFilterNotice.textContent = `Limited to your barangay: ${userBarangayName}`;
        }
        
        // Pre-select barangay in report form
        if (reportBarangay) {
            reportBarangay.value = userBarangayName;
            
            // Hide the barangay selection in report form (only show in read-only mode)
            const reportBarangayGroup = reportBarangay.closest('label');
            if (reportBarangayGroup) {
                reportBarangay.disabled = true;
                reportBarangay.title = `Limited to your barangay: ${userBarangayName}`;
                reportBarangayGroup.style.opacity = '0.7';
            }
        }
    }
    
    console.log("Loading incidents...");
    loadIncidents().then(() => {
        // Check if an incident ID is in the query parameters
        const urlParams = new URLSearchParams(window.location.search);
        const incidentId = urlParams.get('incident');
        if (incidentId) {
            console.log(`Auto-loading incident ${incidentId}`);
            // Find the incident in the loaded incidents
            const incident = incidents.find(i => i.id == incidentId);
            if (incident) {
                // Wait a bit for the map to render, then open the detail
                setTimeout(() => {
                    openDetailModal(incident);
                }, 500);
            }
        }
    });
}).catch(error => {
    console.error("Error in initialization:", error);
    // Ensure incidents still load even if filters failed
    try { loadIncidents(); } catch (e) { console.error('Failed to load incidents after filters error', e); }
});
