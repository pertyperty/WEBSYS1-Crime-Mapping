const apiBase = "../api";
const placeholderImage = "../assets/images/home-placeholder.svg";

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

const miniMap = L.map("mini-map", { zoomControl: false, scrollWheelZoom: false }).setView([16.455, 120.59], 12);
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap contributors"
}).addTo(miniMap);

const miniMarkers = L.layerGroup().addTo(miniMap);

const feedContainer = document.getElementById("recent-feed");
const alertsContainer = document.getElementById("alerts");
const weekChart = document.getElementById("week-chart");
const weekTotal = document.getElementById("week-total");
const weekAverage = document.getElementById("week-average");
const weekBusiest = document.getElementById("week-busiest");
const weekCategory = document.getElementById("week-category");
const weekReportRange = document.getElementById("week-report-range");
const carouselTrack = document.getElementById("crime-carousel-track");
const carouselStatus = document.getElementById("crime-carousel-status");
const miniMapLegend = document.getElementById("mini-map-legend");

let carouselIsLoading = true; // Track if images are still loading

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function formatNotificationLabel(type) {
    const labels = {
        new_report: "New report",
        status_update: "Status update",
        high_severity: "High severity",
        mention: "Mention"
    };

    return labels[type] || "Notification";
}

function buildLegend() {
    if (!miniMapLegend) {
        return;
    }

    const legendItems = [
        ["violent", "Violent"],
        ["property", "Property"],
        ["drug", "Drug"],
        ["traffic", "Traffic"],
        ["cybercrime", "Cybercrime"],
        ["white_collar", "White Collar"],
        ["public_order", "Public Order"],
        ["status_offense", "Status Offense"]
    ];

    miniMapLegend.innerHTML = legendItems.map(([key, label]) => `
        <span class="map-legend-item" title="${escapeHtml(label)}">
            <img src="../assets/images/icons/${key}.svg" alt="${escapeHtml(label)} icon" onerror="this.style.display='none'" />
            <span>${escapeHtml(label)}</span>
        </span>
    `).join("");
}

function formatDayLabel(dateString) {
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) {
        return dateString;
    }

    return new Intl.DateTimeFormat("en-US", {
        weekday: "short",
        month: "short",
        day: "numeric"
    }).format(date);
}

function normalizeImageUrl(filePath) {
    if (!filePath) {
        return placeholderImage;
    }

    if (/^https?:\/\//i.test(filePath)) {
        return filePath;
    }

    return filePath.startsWith("../") ? filePath : `../${String(filePath).replace(/^\/+/, "")}`;
}

function getPlaceholderSlides() {
    return [
        {
            image: "../assets/images/crime-images/Arrest.jpg",
            title: "Arrest evidence",
            subtitle: "Recent on-site arrest • Barangay patrol",
            description: "A sample crime image showing evidence and documentation for a recent arrest."
        },
        {
            image: "../assets/images/crime-images/murder.jpg",
            title: "Crime scene overview",
            subtitle: "Active investigation • Night shift",
            description: "A real scene photo placeholder from the crime image assets with a short summary."
        }
    ];
}

function buildSevenDaySummary(incidents) {
    const today = new Date();
    const dayBuckets = [];
    const countsByCategory = new Map();

    for (let offset = 6; offset >= 0; offset -= 1) {
        const bucketDate = new Date(today);
        bucketDate.setDate(today.getDate() - offset);
        const key = bucketDate.toISOString().slice(0, 10);
        dayBuckets.push({
            key,
            label: new Intl.DateTimeFormat("en-US", { weekday: "short" }).format(bucketDate),
            fullLabel: new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric" }).format(bucketDate),
            count: 0
        });
    }

    incidents.forEach((incident) => {
        const dateKey = String(incident.date || "").slice(0, 10);
        const dayBucket = dayBuckets.find((entry) => entry.key === dateKey);
        if (dayBucket) {
            dayBucket.count += 1;
        }

        const categoryLabel = incident.type_name || incident.type || "Unknown";
        countsByCategory.set(categoryLabel, (countsByCategory.get(categoryLabel) || 0) + 1);
    });

    const total = incidents.length;
    const busiestDay = dayBuckets.reduce((best, entry) => (entry.count > best.count ? entry : best), dayBuckets[0]);
    const topCategoryEntry = [...countsByCategory.entries()].sort((left, right) => right[1] - left[1])[0];

    return {
        total,
        average: total ? (total / 7).toFixed(1) : "0.0",
        busiestDay,
        topCategory: topCategoryEntry ? topCategoryEntry[0] : "-",
        dayBuckets
    };
}

function renderSevenDaySummary(summary) {
    if (weekTotal) {
        weekTotal.textContent = String(summary.total);
    }
    if (weekAverage) {
        weekAverage.textContent = summary.average;
    }
    if (weekBusiest) {
        weekBusiest.textContent = summary.busiestDay ? `${summary.busiestDay.label} (${summary.busiestDay.count})` : "-";
    }
    if (weekCategory) {
        weekCategory.textContent = summary.topCategory;
    }
    if (weekReportRange) {
        weekReportRange.textContent = `Updated ${formatDayLabel(new Date().toISOString())}`;
    }

    if (!weekChart) {
        return;
    }

    const maxCount = Math.max(1, ...summary.dayBuckets.map((entry) => entry.count));
    weekChart.innerHTML = summary.dayBuckets.map((entry) => {
        const height = Math.max(12, Math.round((entry.count / maxCount) * 100));
        return `
            <div class="week-bar">
                <div class="week-bar-fill" style="height:${height}%"></div>
                <div class="week-bar-meta">
                    <strong>${entry.count}</strong>
                    <span>${entry.label}</span>
                    <small>${entry.fullLabel}</small>
                </div>
            </div>
        `;
    }).join("");
}

function renderImageCarousel(slides, isLoading = false) {
    if (!carouselTrack) {
        return;
    }

    carouselTrack.innerHTML = "";

    const safeSlides = slides.length ? slides : getPlaceholderSlides();
    carouselIsLoading = isLoading;

    safeSlides.forEach((slide) => {
        const slideElement = document.createElement("article");
        slideElement.className = "carousel-slide";
        slideElement.innerHTML = `
            <div class="carousel-media">
                <img src="${escapeHtml(slide.image)}" alt="${escapeHtml(slide.title)}" loading="lazy" onerror="this.onerror=null;this.src='${placeholderImage}'" />
            </div>
            <div class="carousel-caption">
                <h3>${escapeHtml(slide.title)}</h3>
                <p>${escapeHtml(slide.subtitle)}</p>
                ${slide.description ? `<p class="carousel-description">${escapeHtml(slide.description)}</p>` : ""}
            </div>
        `;
        carouselTrack.appendChild(slideElement);
    });

    if (carouselStatus) {
        if (slides.length > 0) {
            carouselStatus.textContent = `${slides.length} slide${slides.length === 1 ? "" : "s"} ready`;
        } else if (isLoading) {
            carouselStatus.textContent = "Loading recent images...";
        } else {
            carouselStatus.textContent = "No recent images available. Showing placeholder.";
        }
    }
}

function setupCarouselControls() {
    if (!carouselTrack) {
        return;
    }

    let autoAdvanceTimer = null;
    let isDragging = false;
    let startX = 0;
    let dragDelta = 0;
    let activeSlideIndex = 0;

    const getSlideWidth = () => {
        const slide = carouselTrack.querySelector(".carousel-slide");
        if (!slide) {
            return Math.max(280, carouselTrack.clientWidth);
        }

        return slide.getBoundingClientRect().width + 14;
    };

    const moveToSlide = (nextIndex) => {
        const slides = carouselTrack.querySelectorAll(".carousel-slide");
        if (!slides.length) {
            return;
        }

        const maxIndex = slides.length - 1;
        activeSlideIndex = Math.max(0, Math.min(maxIndex, nextIndex));
        carouselTrack.scrollTo({ left: activeSlideIndex * getSlideWidth(), behavior: "smooth" });
    };

    const stepCarousel = (direction) => {
        const slides = carouselTrack.querySelectorAll(".carousel-slide");
        if (!slides.length) {
            return;
        }

        moveToSlide((activeSlideIndex + direction + slides.length) % slides.length);
    };

    const restartAutoAdvance = () => {
        if (autoAdvanceTimer) {
            window.clearInterval(autoAdvanceTimer);
        }

        autoAdvanceTimer = window.setInterval(() => stepCarousel(1), 5000);
    };

    const commitDrag = () => {
        const threshold = Math.max(48, getSlideWidth() * 0.18);
        if (dragDelta <= -threshold) {
            stepCarousel(1);
        } else if (dragDelta >= threshold) {
            stepCarousel(-1);
        } else {
            moveToSlide(activeSlideIndex);
        }

        isDragging = false;
        dragDelta = 0;
        carouselTrack.classList.remove("is-dragging");
        restartAutoAdvance();
    };

    carouselTrack.addEventListener("pointerdown", (event) => {
        isDragging = true;
        startX = event.clientX;
        dragDelta = 0;
        carouselTrack.classList.add("is-dragging");
        carouselTrack.setPointerCapture(event.pointerId);
        if (autoAdvanceTimer) {
            window.clearInterval(autoAdvanceTimer);
        }
    });

    carouselTrack.addEventListener("pointermove", (event) => {
        if (!isDragging) {
            return;
        }

        dragDelta = event.clientX - startX;
        carouselTrack.scrollLeft = activeSlideIndex * getSlideWidth() - dragDelta;
    });

    carouselTrack.addEventListener("pointerup", () => {
        if (isDragging) {
            commitDrag();
        }
    });

    carouselTrack.addEventListener("pointercancel", () => {
        if (isDragging) {
            commitDrag();
        }
    });

    carouselTrack.addEventListener("scroll", () => {
        if (!isDragging) {
            activeSlideIndex = Math.round(carouselTrack.scrollLeft / getSlideWidth());
        }
    });

    carouselTrack.addEventListener("mouseenter", () => {
        if (autoAdvanceTimer) {
            window.clearInterval(autoAdvanceTimer);
        }
    });

    carouselTrack.addEventListener("mouseleave", restartAutoAdvance);

    restartAutoAdvance();
}

async function loadRecentCrimeImages(incidents) {
    renderImageCarousel([], true); // Show placeholder with loading message

    const slides = [];
    const candidates = incidents.slice(0, 8);

    for (const incident of candidates) {
        try {
            const response = await fetch(`${apiBase}/incident-detail.php?incident_id=${encodeURIComponent(incident.id)}`);
            const payload = await response.json();
            const images = payload.ok ? payload.images || [] : [];

            if (images.length) {
                const firstImage = images[0];
                slides.push({
                    image: normalizeImageUrl(firstImage.file_path),
                    title: incident.title || payload.incident?.title || "Recent incident",
                    subtitle: `${incident.barangay || payload.incident?.barangay || "Unknown barangay"} • ${incident.date || payload.incident?.occurred_at || "Recent"} • ${String(payload.incident?.status || incident.status || "pending").replace(/_/g, " ")}`,
                    description: payload.incident?.description || incident.description || "No description was provided."
                });
            }
        } catch (error) {
            console.warn("Failed to load incident image", error);
        }
    }

    renderImageCarousel(slides, false); // Mark loading as complete
}

function renderFeed(incidents) {
    feedContainer.innerHTML = "";
    if (!incidents.length) {
        feedContainer.innerHTML = "<div class=\"feed-item\">No reports yet.</div>";
        return;
    }

    incidents.slice(0, 3).forEach((incident) => {
        const safeTitle = escapeHtml(incident.title);
        const safeBarangay = escapeHtml(incident.barangay);
        const safeDate = escapeHtml(incident.date);
        const safeStatus = escapeHtml(String(incident.status ?? "").replace(/_/g, " "));
        const safeDescription = escapeHtml(incident.description);
        const item = document.createElement("div");
        item.className = "feed-item";
        item.innerHTML = `
            <h3>${safeTitle}</h3>
            <div class="feed-meta">${safeBarangay} • ${safeDate} • ${safeStatus}</div>
            <p class="muted">${safeDescription}</p>
        `;
        feedContainer.appendChild(item);
    });
}

function renderAlerts(alerts, notifications = []) {
    alertsContainer.innerHTML = "";
    const feedItems = [
        ...alerts.map((incident) => ({
            kind: "alert",
            title: incident.title,
            subtitle: `High severity • ${incident.barangay}`,
            meta: incident.date
        })),
        ...notifications.map((notification) => ({
            kind: "notification",
            title: notification.message,
            subtitle: `${formatNotificationLabel(notification.notification_type)} • ${notification.barangay || "Municipal"}`,
            meta: notification.created_at
        }))
    ];

    if (!feedItems.length) {
        alertsContainer.innerHTML = "<div class=\"alert-item\">No alerts or notifications yet.</div>";
        return;
    }

    feedItems.forEach((entry) => {
        const safeTitle = escapeHtml(entry.title);
        const safeSubtitle = escapeHtml(entry.subtitle);
        const safeMeta = escapeHtml(entry.meta);
        const itemElement = document.createElement("div");
        itemElement.className = "alert-item";
        itemElement.innerHTML = `
            <strong>${safeTitle}</strong>
            <div class="alert-meta">${safeSubtitle}</div>
            <div class="alert-time">${safeMeta}</div>
        `;
        alertsContainer.appendChild(itemElement);
    });
}

function updateStats(stats) {
    document.querySelector('[data-stat="daily"]').textContent = stats.daily ?? 0;
    document.querySelector('[data-stat="active"]').textContent = stats.active ?? 0;
    document.querySelector('[data-stat="hotspot"]').textContent = stats.hotspot ?? "-";
}

function renderMiniMap(incidents) {
    miniMarkers.clearLayers();
    incidents.forEach((incident) => {
        if (!incident.lat || !incident.lng) {
            return;
        }
        const marker = L.circleMarker([incident.lat, incident.lng], {
            radius: 6,
            color: typeColors[incident.type] || "#22d3ee",
            fillOpacity: 0.8
        }).addTo(miniMarkers);
        const safeTitle = escapeHtml(incident.title);
        const safeBarangay = escapeHtml(incident.barangay);
        marker.bindTooltip(`${safeTitle}<br>${safeBarangay}`, { direction: "top" });
    });
}

function startMiniMapDrift(incidents) {
    const points = incidents.filter((incident) => incident.lat && incident.lng);
    let driftIndex = 0;

    const drift = () => {
        if (!points.length) {
            miniMap.flyTo([16.455 + (Math.random() - 0.5) * 0.03, 120.59 + (Math.random() - 0.5) * 0.03], 12, {
                duration: 2.5,
                easeLinearity: 0.1
            });
            return;
        }

        const incident = points[driftIndex % points.length];
        driftIndex += 1;
        const jitterLat = (Math.random() - 0.5) * 0.003;
        const jitterLng = (Math.random() - 0.5) * 0.003;
        miniMap.flyTo([incident.lat + jitterLat, incident.lng + jitterLng], 13, {
            duration: 2.8,
            easeLinearity: 0.08
        });
    };

    drift();
    window.setInterval(drift, 9000);
}

async function loadDashboard() {
    try {
        const sevenDayStart = new Date();
        sevenDayStart.setDate(sevenDayStart.getDate() - 6);
        const dateStart = sevenDayStart.toISOString().slice(0, 10);

        const [statsRes, incidentsRes, alertsRes, notificationsRes] = await Promise.all([
            fetch(`${apiBase}/stats.php`),
            fetch(`${apiBase}/incidents.php?date_start=${dateStart}&limit=50`),
            fetch(`${apiBase}/alerts.php`),
            fetch(`${apiBase}/notifications.php`)
        ]);

        const statsJson = await statsRes.json();
        const incidentsJson = await incidentsRes.json();
        const alertsJson = await alertsRes.json();
        const notificationsJson = await notificationsRes.json();

        const incidents = incidentsJson.ok ? incidentsJson.data : [];
        const alerts = alertsJson.ok ? alertsJson.data : [];
        const notifications = notificationsJson.ok ? notificationsJson.data : [];
        const stats = statsJson.ok ? statsJson.data : { daily: 0, active: 0, hotspot: "-" };

        const sevenDaySummary = buildSevenDaySummary(incidents);

        updateStats(stats);
        buildLegend();
        renderSevenDaySummary(sevenDaySummary);
        renderFeed(incidents.slice(0, 6));
        renderAlerts(alerts, notifications);
        renderMiniMap(incidents.slice(0, 20));
        loadRecentCrimeImages(incidents);
        startMiniMapDrift(incidents.slice(0, 20));
    } catch (error) {
        console.error("Failed to load dashboard data", error);
        updateStats({ daily: 0, active: 0, hotspot: "-" });
        buildLegend();
        renderSevenDaySummary({ total: 0, average: "0.0", busiestDay: null, topCategory: "-", dayBuckets: [] });
        renderFeed([]);
        renderAlerts([]);
        renderMiniMap([]);
        renderImageCarousel([], false);
        startMiniMapDrift([]);
    }
}

document.getElementById("refresh-feed").addEventListener("click", loadDashboard);
setupCarouselControls();

loadDashboard();
