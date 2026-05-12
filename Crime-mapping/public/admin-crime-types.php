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
    <title>Crime Types | Admin</title>
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
</head>
<body class="page-admin-crime-types">
    <div class="page-shell">
        <header class="site-header">
            <div class="brand">
                <img class="brand-logo" src="../assets/images/logo/la-trinidad.png" alt="La Trinidad" />
                <div>
                    <div class="brand-title">Admin Dashboard</div>
                    <div class="brand-subtitle">Crime type management</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('crime-types', 'admin'); ?>
        </header>

        <main>
            <section class="hero hero-tight">
                <div class="hero-copy">
                    <p class="eyebrow">System Management</p>
                    <h1>Manage crime categories and types.</h1>
                    <p class="lead">Add new crime types, rename existing ones, or deactivate types to hide them from report forms and filters.</p>
                </div>
            </section>

            <section class="summary-strip" id="summary-strip">
                <div class="summary-chip"><span>Total</span><strong id="count-total">0</strong></div>
                <div class="summary-chip"><span>Active</span><strong id="count-active">0</strong></div>
                <div class="summary-chip"><span>Inactive</span><strong id="count-inactive">0</strong></div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>Crime Types</h2>
                    <div class="dashboard-actions">
                        <button type="button" class="btn-primary" id="add-type-btn">Add crime type</button>
                    </div>
                </div>

                <div class="dashboard-toolbar">
                    <input id="search-types" type="text" placeholder="Search crime types" />
                    <select id="filter-category">
                        <option value="">All categories</option>
                    </select>
                    <select id="filter-active">
                        <option value="">All statuses</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div class="crime-type-grid" id="types-grid">
                    <div class="crime-type-row header">
                        <div>Type</div>
                        <div>Category</div>
                        <div>Status</div>
                        <div>Actions</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="type-modal">
        <div class="modal-content type-modal">
            <div class="modal-header">
                <h2 id="type-modal-title">Crime type</h2>
                <button class="modal-close" id="close-type-modal">×</button>
            </div>

            <form id="type-form" class="form-grid">
                <input type="hidden" id="crime-type-id" />
                <label>
                    <span>Category</span>
                    <select id="crime-type-category" required></select>
                </label>
                <label>
                    <span>Type name</span>
                    <input id="crime-type-name" type="text" required placeholder="e.g., Theft / Shoplifting" />
                </label>
                <label class="checkbox-row">
                    <input id="crime-type-active" type="checkbox" checked />
                    <span>Active</span>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="save-type-btn">Save</button>
                    <button type="button" class="btn-secondary" id="cancel-type-btn">Cancel</button>
                </div>
                <p class="muted" id="type-form-status"></p>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const apiBase = '../api';
        let allTypes = [];
        let categories = [];

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function openModal(mode, crimeType = null) {
            const modal = document.getElementById('type-modal');
            const title = document.getElementById('type-modal-title');
            const idInput = document.getElementById('crime-type-id');
            const categorySelect = document.getElementById('crime-type-category');
            const nameInput = document.getElementById('crime-type-name');
            const activeInput = document.getElementById('crime-type-active');
            const status = document.getElementById('type-form-status');

            title.textContent = mode === 'edit' ? 'Edit crime type' : 'Add crime type';
            status.textContent = '';

            idInput.value = crimeType ? crimeType.crime_type_id : '';
            categorySelect.value = crimeType ? crimeType.category : (categories[0] || '');
            nameInput.value = crimeType ? crimeType.type_name : '';
            activeInput.checked = crimeType ? Number(crimeType.is_active) === 1 : true;

            modal.classList.add('is-open');
            setTimeout(() => nameInput.focus(), 50);
        }

        function closeModal() {
            document.getElementById('type-modal').classList.remove('is-open');
        }

        function renderSummary(types) {
            const total = types.length;
            const active = types.filter(t => Number(t.is_active) === 1).length;
            const inactive = total - active;
            document.getElementById('count-total').textContent = total;
            document.getElementById('count-active').textContent = active;
            document.getElementById('count-inactive').textContent = inactive;
        }

        function renderCategories() {
            const filter = document.getElementById('filter-category');
            const select = document.getElementById('crime-type-category');
            filter.innerHTML = '<option value="">All categories</option>';
            select.innerHTML = '';

            categories.forEach((cat) => {
                const opt = document.createElement('option');
                opt.value = cat;
                opt.textContent = cat.replace(/_/g, ' ');
                filter.appendChild(opt.cloneNode(true));
                select.appendChild(opt);
            });
        }

        function rowHtml(type) {
            const statusLabel = Number(type.is_active) === 1 ? 'active' : 'disabled';
            const statusText = Number(type.is_active) === 1 ? 'Active' : 'Inactive';
            const safeCategory = escapeHtml(type.category || '');
            const safeTypeName = escapeHtml(type.type_name || '');
            const toggleText = Number(type.is_active) === 1 ? 'Deactivate' : 'Activate';
            const statusClass = Number(type.is_active) === 1 ? 'active' : 'disabled';

            return `
                <div class="crime-type-row">
                    <div><strong>${safeTypeName}</strong><div class="muted">ID: ${escapeHtml(type.crime_type_id)}</div></div>
                    <div><span class="pill">${safeCategory.replace(/_/g, ' ')}</span></div>
                    <div><span class="status-badge status-${statusClass}">${statusText}</span></div>
                    <div class="crime-type-actions">
                        <button type="button" class="btn-secondary" data-action="edit">Edit</button>
                        <button type="button" class="btn-secondary" data-action="toggle">${toggleText}</button>
                    </div>
                </div>
            `;
        }

        function renderTypes() {
            const grid = document.getElementById('types-grid');
            const search = document.getElementById('search-types').value.trim().toLowerCase();
            const category = document.getElementById('filter-category').value;
            const active = document.getElementById('filter-active').value;

            const filtered = allTypes.filter((t) => {
                const haystack = [t.type_name, t.category, t.crime_type_id].filter(Boolean).join(' ').toLowerCase();
                const matchesSearch = !search || haystack.includes(search);
                const matchesCategory = !category || t.category === category;
                const matchesActive = active === '' || String(Number(t.is_active)) === active;
                return matchesSearch && matchesCategory && matchesActive;
            });

            grid.innerHTML = `
                <div class="crime-type-row header">
                    <div>Type</div>
                    <div>Category</div>
                    <div>Status</div>
                    <div>Actions</div>
                </div>
            `;

            if (filtered.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'crime-type-row';
                empty.style.gridTemplateColumns = '1fr';
                empty.innerHTML = '<div class="muted">No crime types match the current filters.</div>';
                grid.appendChild(empty);
                return;
            }

            filtered.forEach((type) => {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = rowHtml(type);
                const row = wrapper.firstElementChild;
                row.querySelector('[data-action="edit"]').addEventListener('click', () => openModal('edit', type));
                row.querySelector('[data-action="toggle"]').addEventListener('click', async () => {
                    try {
                        await performAction('toggle-active', type, { is_active: Number(type.is_active) === 1 ? 0 : 1 });
                        await loadTypes();
                    } catch (error) {
                        alert(error.message);
                    }
                });
                grid.appendChild(row);
            });

            renderSummary(allTypes);
        }

        async function performAction(action, type, extra = {}) {
            const payload = { action, crime_type_id: type?.crime_type_id, ...extra };
            const response = await fetch(`${apiBase}/crime-types-manage.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (!result.ok) {
                throw new Error(result.error || 'Action failed.');
            }
            return result;
        }

        async function loadTypes() {
            const response = await fetch(`${apiBase}/crime-types-manage.php`);
            const result = await response.json();
            if (!result.ok) {
                throw new Error(result.error || 'Failed to load crime types.');
            }
            categories = result.data.categories || [];
            allTypes = result.data.types || [];
            renderCategories();
            renderTypes();
            renderSummary(allTypes);
        }

        document.getElementById('add-type-btn').addEventListener('click', () => openModal('create'));
        document.getElementById('close-type-modal').addEventListener('click', closeModal);
        document.getElementById('cancel-type-btn').addEventListener('click', closeModal);
        document.getElementById('type-modal').addEventListener('click', (e) => {
            if (e.target && e.target.id === 'type-modal') closeModal();
        });

        document.getElementById('search-types').addEventListener('input', renderTypes);
        document.getElementById('filter-category').addEventListener('change', renderTypes);
        document.getElementById('filter-active').addEventListener('change', renderTypes);

        document.getElementById('type-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const status = document.getElementById('type-form-status');
            status.textContent = 'Saving...';
            const id = Number.parseInt(document.getElementById('crime-type-id').value, 10) || 0;
            const category = document.getElementById('crime-type-category').value;
            const typeName = document.getElementById('crime-type-name').value.trim();
            const isActive = document.getElementById('crime-type-active').checked ? 1 : 0;

            try {
                if (!typeName) {
                    status.textContent = 'Type name is required.';
                    return;
                }

                if (id > 0) {
                    await performAction('update', { crime_type_id: id }, { category, type_name: typeName });
                    await performAction('toggle-active', { crime_type_id: id }, { is_active: isActive });
                } else {
                    await performAction('create', null, { category, type_name: typeName, is_active: isActive });
                }

                status.textContent = 'Saved.';
                await loadTypes();
                closeModal();
            } catch (error) {
                status.textContent = error.message;
            }
        });

        loadTypes().catch((e) => {
            console.error(e);
            alert('Failed to load crime types.');
        });
    </script>
</body>
</html>

