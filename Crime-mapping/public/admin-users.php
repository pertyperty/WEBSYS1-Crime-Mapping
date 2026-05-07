<?php
require __DIR__ . '/guard.php';
requireRole(['admin']);
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>User Management | Admin</title>
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
                    <div class="brand-title">Admin Dashboard</div>
                    <div class="brand-subtitle">User Management</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('users', 'admin'); ?>
        </header>

        <main>
            <section class="hero hero-tight">
                <div class="hero-copy">
                    <p class="eyebrow">Access Control</p>
                    <h1>Manage registered and barangay accounts.</h1>
                    <p class="lead">Review account activity, update status, and remove inactive accounts when necessary.</p>
                </div>
            </section>

            <section class="summary-strip" id="summary-strip">
                <div class="summary-chip"><span>Total users</span><strong id="count-total">0</strong></div>
                <div class="summary-chip"><span>Registered</span><strong id="count-registered">0</strong></div>
                <div class="summary-chip"><span>Barangay</span><strong id="count-barangay">0</strong></div>
                <div class="summary-chip"><span>Disabled</span><strong id="count-disabled">0</strong></div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>Users</h2>
                    <div class="dashboard-toolbar">
                        <input id="search-users" type="text" placeholder="Search users" />
                        <select id="filter-role">
                            <option value="">All roles</option>
                            <option value="registered">Registered</option>
                            <option value="barangay">Barangay</option>
                            <option value="admin">Admin</option>
                        </select>
                        <select id="filter-status">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                </div>

                <div class="user-management-grid" id="users-table">
                    <div class="user-row header">
                        <div>User</div>
                        <div>Contact</div>
                        <div>Role</div>
                        <div>Barangay</div>
                        <div>Status</div>
                        <div>Actions</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="user-modal">
        <div class="modal-content user-modal">
            <div class="modal-header">
                <h2 id="user-modal-title">User details</h2>
                <button class="modal-close" id="close-user-modal">×</button>
            </div>
            <div id="user-modal-body" class="report-output"></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="toggle-user-status">Disable</button>
                <button type="button" class="btn-secondary" id="delete-user">Remove</button>
            </div>
            <p class="muted" id="user-modal-status"></p>
        </div>
    </div>

    <script>
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const apiBase = '../api';
        let allUsers = [];
        let selectedUser = null;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderSummary(users) {
            document.getElementById('count-total').textContent = users.length;
            document.getElementById('count-registered').textContent = users.filter((user) => user.role === 'registered').length;
            document.getElementById('count-barangay').textContent = users.filter((user) => user.role === 'barangay').length;
            document.getElementById('count-disabled').textContent = users.filter((user) => user.status === 'disabled').length;
        }

        function openUserModal(user) {
            selectedUser = user;
            document.getElementById('user-modal-title').textContent = user.username;
            const statusBadge = user.status === 'active' 
                ? '<span class="status-badge status-active">Active</span>' 
                : '<span class="status-badge status-disabled">Disabled</span>';
            document.getElementById('user-modal-body').innerHTML = `
                <div class="user-details-card">
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value">${escapeHtml(user.email || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contact</span>
                        <span class="detail-value">${escapeHtml(user.contact || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Address</span>
                        <span class="detail-value">${escapeHtml(user.address || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Role</span>
                        <span class="detail-value"><span class="pill">${escapeHtml(user.role || '-')}</span></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Barangay</span>
                        <span class="detail-value">${escapeHtml(user.barangay_name || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">${statusBadge}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Reports</span>
                        <span class="detail-value">${escapeHtml(user.incident_count ?? 0)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Created</span>
                        <span class="detail-value">${escapeHtml(user.created_at || '-')}</span>
                    </div>
                </div>
            `;
            const toggleBtn = document.getElementById('toggle-user-status');
            const isActive = user.status === 'active';
            toggleBtn.textContent = isActive ? 'Disable' : 'Enable';
            toggleBtn.classList.toggle('btn-danger', isActive);
            toggleBtn.classList.toggle('btn-success', !isActive);
            document.getElementById('user-modal-status').textContent = '';
            document.getElementById('user-modal').classList.add('is-open');
        }

        function closeUserModal() {
            document.getElementById('user-modal').classList.remove('is-open');
            selectedUser = null;
        }

        async function performUserAction(action, user, extra = {}) {
            const payload = { action, user_id: user.user_id, ...extra };
            const response = await fetch(`${apiBase}/users-manage.php`, {
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

        function renderUsers() {
            const container = document.getElementById('users-table');
            const search = document.getElementById('search-users').value.trim().toLowerCase();
            const roleFilter = document.getElementById('filter-role').value;
            const statusFilter = document.getElementById('filter-status').value;

            const filtered = allUsers.filter((user) => {
                const haystack = [user.username, user.email, user.contact, user.address, user.barangay_name, user.role, user.status]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();
                return (!search || haystack.includes(search))
                    && (!roleFilter || user.role === roleFilter)
                    && (!statusFilter || user.status === statusFilter);
            });

            container.innerHTML = `
                <div class="user-row header">
                    <div>User</div>
                    <div>Contact</div>
                    <div>Role</div>
                    <div>Barangay</div>
                    <div>Status</div>
                    <div>Actions</div>
                </div>
            `;

            if (filtered.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'user-row';
                empty.style.gridTemplateColumns = '1fr';
                empty.innerHTML = '<div class="muted">No users match the current filters.</div>';
                container.appendChild(empty);
                return;
            }

            filtered.forEach((user) => {
                const row = document.createElement('div');
                row.className = 'user-row';
                row.innerHTML = `
                    <div>
                        <strong>${escapeHtml(user.username)}</strong><br />
                        <span class="muted">${escapeHtml(user.created_at || '')}</span>
                    </div>
                    <div>${escapeHtml(user.email || '-')}<br /><span class="muted">${escapeHtml(user.contact || '-')}</span></div>
                    <div><span class="pill">${escapeHtml(user.role || '-')}</span></div>
                    <div>${escapeHtml(user.barangay_name || '-')}</div>
                    <div><span class="status-badge status-${escapeHtml(String(user.status || '').toLowerCase())}">${escapeHtml(user.status || '-')}</span></div>
                    <div class="user-actions">
                        <button type="button" class="btn-secondary" data-action="view">View</button>
                        <button type="button" class="btn-secondary status-toggle" data-action="toggle">${escapeHtml(user.status === 'active' ? 'Disable' : 'Enable')}</button>
                        <button type="button" class="btn-secondary" data-action="delete">Remove</button>
                    </div>
                `;

                const toggleButton = row.querySelector('[data-action="toggle"]');
                row.querySelector('[data-action="view"]').addEventListener('click', () => openUserModal(user));
                toggleButton.addEventListener('click', async () => {
                    try {
                        await performUserAction('toggle-status', user, { status: user.status === 'active' ? 'disabled' : 'active' });
                        await loadUsers();
                    } catch (error) {
                        alert(error.message);
                    }
                });
                row.querySelector('[data-action="delete"]').addEventListener('click', async () => {
                    if (!confirm(`Disable ${user.username}?`)) return;
                    try {
                        await performUserAction('delete', user);
                        await loadUsers();
                    } catch (error) {
                        alert(error.message);
                    }
                });

                const isActive = user.status === 'active';
                toggleButton.textContent = isActive ? 'Disable' : 'Enable';
                toggleButton.classList.toggle('btn-success', !isActive);
                toggleButton.classList.toggle('btn-danger', isActive);
                container.appendChild(row);
            });
        }

        async function loadUsers() {
            try {
                const resp = await fetch(`${apiBase}/users-manage.php`);
                const data = await resp.json();
                if (!data.ok) {
                    throw new Error(data.error || 'Failed to load users');
                }

                allUsers = data.users || [];
                renderSummary(allUsers);
                renderUsers();
            } catch (e) {
                console.error('Failed to load users', e);
                document.getElementById('users-table').innerHTML = '<div class="user-row"><div class="muted">Failed to load users.</div></div>';
            }
        }

        document.getElementById('search-users').addEventListener('input', renderUsers);
        document.getElementById('filter-role').addEventListener('change', renderUsers);
        document.getElementById('filter-status').addEventListener('change', renderUsers);
        document.getElementById('close-user-modal').addEventListener('click', closeUserModal);
        document.getElementById('user-modal').addEventListener('click', (event) => {
            if (event.target.id === 'user-modal') closeUserModal();
        });
        document.getElementById('toggle-user-status').addEventListener('click', async () => {
            if (!selectedUser) return;
            try {
                await performUserAction('toggle-status', selectedUser, { status: selectedUser.status === 'active' ? 'disabled' : 'active' });
                closeUserModal();
                loadUsers();
            } catch (error) {
                document.getElementById('user-modal-status').textContent = error.message;
            }
        });

        document.getElementById('delete-user').addEventListener('click', async () => {
            if (!selectedUser || !confirm(`Disable ${selectedUser.username}?`)) return;
            try {
                await performUserAction('delete', selectedUser);
                closeUserModal();
                loadUsers();
            } catch (error) {
                document.getElementById('user-modal-status').textContent = error.message;
            }
        });

        loadUsers();
    </script>
</body>
</html>
