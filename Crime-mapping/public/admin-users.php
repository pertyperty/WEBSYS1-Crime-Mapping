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
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
    <style>
        .user-management-toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .user-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .user-form-note {
            margin-top: -4px;
            color: var(--muted);
            font-size: 13px;
        }
    </style>
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
                    <h1>Manage registered, barangay, and admin accounts.</h1>
                    <p class="lead">Create accounts, review activity, update profile data, and disable access when needed.</p>
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
                    <div class="user-management-toolbar">
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
                        <button type="button" class="btn-primary" id="add-user-btn">Add user</button>
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

    <div class="modal-overlay" id="user-details-modal">
        <div class="modal-content user-modal">
            <div class="modal-header">
                <h2 id="user-details-title">User details</h2>
                <button class="modal-close" id="close-user-details-modal">×</button>
            </div>
            <div id="user-details-body" class="report-output"></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="toggle-user-status">Toggle status</button>
                <button type="button" class="btn-secondary" id="disable-user">Disable</button>
                <button type="button" class="btn-secondary" id="delete-user">Remove</button>
            </div>
            <p class="muted" id="user-details-status"></p>
        </div>
    </div>

    <div class="modal-overlay" id="user-form-modal">
        <div class="modal-content user-modal">
            <div class="modal-header">
                <h2 id="user-form-title">Add user</h2>
                <button class="modal-close" id="close-user-form-modal">×</button>
            </div>
            <form id="user-form" class="form-grid">
                <label>
                    <span>Username *</span>
                    <input type="text" id="user-username" required />
                </label>
                <label>
                    <span>Email *</span>
                    <input type="email" id="user-email" required />
                </label>
                <label>
                    <span>Contact *</span>
                    <input type="text" id="user-contact" required />
                </label>
                <label>
                    <span>Address</span>
                    <input type="text" id="user-address" />
                </label>
                <label>
                    <span>Role *</span>
                    <select id="user-role" required>
                        <option value="registered">Registered</option>
                        <option value="barangay">Barangay</option>
                        <option value="admin">Admin</option>
                    </select>
                </label>
                <label>
                    <span>Barangay</span>
                    <select id="user-barangay">
                        <option value="">Select barangay</option>
                    </select>
                </label>
                <label>
                    <span>Status *</span>
                    <select id="user-status" required>
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </label>
                <label>
                    <span>Password <span id="password-label-note">*</span></span>
                    <input type="password" id="user-password" />
                </label>
                <p class="user-form-note" id="user-form-note">Password is required when creating a new account.</p>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="cancel-user-form">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
                <p class="muted" id="user-form-status"></p>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const apiBase = '../api';
        let allUsers = [];
        let allBarangays = [];
        let selectedUser = null;
        let formMode = 'create';

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderSummary(users) {
            document.getElementById('count-total').textContent = users.length;
            document.getElementById('count-registered').textContent = users.filter((user) => user.role === 'registered').length;
            document.getElementById('count-barangay').textContent = users.filter((user) => user.role === 'barangay').length;
            document.getElementById('count-disabled').textContent = users.filter((user) => user.status === 'disabled').length;
        }

        function openUserDetails(user) {
            selectedUser = user;
            document.getElementById('user-modal-title').textContent = user.username;
            document.getElementById('user-modal-body').innerHTML = `
                <div class="report-panel-card">
                    <div><strong>Email:</strong> ${escapeHtml(user.email || '-')}</div>
                    <div><strong>Contact:</strong> ${escapeHtml(user.contact || '-')}</div>
                    <div><strong>Address:</strong> ${escapeHtml(user.address || '-')}</div>
                    <div><strong>Role:</strong> ${escapeHtml(user.role || '-')}</div>
                    <div><strong>Barangay:</strong> ${escapeHtml(user.barangay_name || '-')}</div>
                    <div><strong>Status:</strong> ${escapeHtml(user.status || '-')}</div>
                    <div><strong>Reports:</strong> ${escapeHtml(user.incident_count ?? 0)}</div>
                    <div><strong>Created:</strong> ${escapeHtml(user.created_at || '-')}</div>
                </div>
            `;
            document.getElementById('user-modal-status').textContent = '';
            document.getElementById('user-modal').classList.add('is-open');
        }

        function closeUserDetails() {
            document.getElementById('user-details-modal').classList.remove('is-open');
            selectedUser = null;
        }

        function populateBarangaySelect(selectedId = '') {
            const select = document.getElementById('user-barangay');
            select.innerHTML = '<option value="">Select barangay</option>';
            allBarangays.forEach((barangay) => {
                const option = document.createElement('option');
                option.value = String(barangay.barangay_id);
                option.textContent = barangay.barangay_name;
                if (String(selectedId) === String(barangay.barangay_id)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }

        function openUserForm(mode, user = null) {
            formMode = mode;
            const isEdit = mode === 'edit';
            const modal = document.getElementById('user-form-modal');
            const title = document.getElementById('user-form-title');
            const note = document.getElementById('user-form-note');
            const password = document.getElementById('user-password');
            const passwordLabel = document.getElementById('password-label-note');
            const roleSelect = document.getElementById('user-role');
            const barangaySelect = document.getElementById('user-barangay');

            document.getElementById('user-form').reset();
            populateBarangaySelect(user?.barangay_id || '');

            title.textContent = isEdit ? 'Edit user' : 'Add user';
            note.textContent = isEdit ? 'Leave password empty to keep the current one.' : 'Password is required when creating a new account.';
            password.required = !isEdit;
            password.value = '';
            passwordLabel.textContent = isEdit ? '' : '*';

            document.getElementById('user-username').value = user?.username || '';
            document.getElementById('user-email').value = user?.email || '';
            document.getElementById('user-contact').value = user?.contact || '';
            document.getElementById('user-address').value = user?.address || '';
            roleSelect.value = user?.role || 'registered';
            document.getElementById('user-status').value = user?.status || 'active';

            if (user && user.role !== 'barangay') {
                barangaySelect.value = '';
            }

            barangaySelect.disabled = roleSelect.value !== 'barangay';

            document.getElementById('user-form-status').textContent = '';
            modal.classList.add('is-open');
        }

        function closeUserForm() {
            document.getElementById('user-form-modal').classList.remove('is-open');
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
                        <button type="button" class="btn-secondary" data-action="toggle">Toggle</button>
                        <button type="button" class="btn-secondary" data-action="disable">Disable</button>
                        <button type="button" class="btn-secondary" data-action="delete">Remove</button>
                    </div>
                `;

                row.querySelector('[data-action="view"]').addEventListener('click', () => openUserModal(user));
                row.querySelector('[data-action="toggle"]').addEventListener('click', async () => {
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
                allBarangays = data.barangays || [];
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
        document.getElementById('add-user-btn').addEventListener('click', () => openUserForm('create'));

        document.getElementById('close-user-details-modal').addEventListener('click', closeUserDetails);
        document.getElementById('user-details-modal').addEventListener('click', (event) => {
            if (event.target.id === 'user-details-modal') closeUserDetails();
        });
        document.getElementById('toggle-user-status').addEventListener('click', async () => {
            if (!selectedUser) return;
            try {
                await performUserAction('toggle-status', selectedUser, { status: selectedUser.status === 'active' ? 'disabled' : 'active' });
                closeUserDetails();
                loadUsers();
            } catch (error) {
                document.getElementById('user-details-status').textContent = error.message;
            }
        });
        document.getElementById('disable-user').addEventListener('click', async () => {
            if (!selectedUser) return;
            try {
                await performUserAction('toggle-status', selectedUser, { status: 'disabled' });
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
                closeUserDetails();
                loadUsers();
            } catch (error) {
                document.getElementById('user-details-status').textContent = error.message;
            }
        });

        document.getElementById('close-user-form-modal').addEventListener('click', closeUserForm);
        document.getElementById('cancel-user-form').addEventListener('click', closeUserForm);
        document.getElementById('user-form-modal').addEventListener('click', (event) => {
            if (event.target.id === 'user-form-modal') closeUserForm();
        });

        document.getElementById('user-role').addEventListener('change', (event) => {
            const barangaySelect = document.getElementById('user-barangay');
            barangaySelect.disabled = event.target.value !== 'barangay';
            if (event.target.value !== 'barangay') {
                barangaySelect.value = '';
            }
        });

        document.getElementById('user-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const statusElement = document.getElementById('user-form-status');
            statusElement.textContent = 'Saving...';

            const roleValue = document.getElementById('user-role').value;
            const barangayValue = document.getElementById('user-barangay').value;
            const payload = {
                action: formMode === 'edit' ? 'update' : 'create',
                username: document.getElementById('user-username').value.trim(),
                email: document.getElementById('user-email').value.trim(),
                contact: document.getElementById('user-contact').value.trim(),
                address: document.getElementById('user-address').value.trim(),
                role: roleValue,
                status: document.getElementById('user-status').value,
                barangay_id: barangayValue ? parseInt(barangayValue, 10) : 0,
                password: document.getElementById('user-password').value
            };

            if (formMode === 'edit' && selectedUser) {
                payload.user_id = selectedUser.user_id;
            }

            if (formMode === 'edit' && !payload.password) {
                delete payload.password;
            }

            try {
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
                    throw new Error(result.error || 'Save failed.');
                }

                statusElement.textContent = 'Saved.';
                closeUserForm();
                selectedUser = null;
                await loadUsers();
            } catch (error) {
                statusElement.textContent = error.message;
            }
        });

        loadUsers();
    </script>
</body>
</html>