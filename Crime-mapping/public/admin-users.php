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
    <link rel="stylesheet" href="../assets/css/site.css" />
</head>
<body>
    <header class="site-header">
        <div class="brand">
            <img class="brand-logo" src="../assets/images/logo/la-trinidad.png" alt="La Trinidad" />
            <div>
                <div class="brand-title">Admin</div>
                <div class="brand-subtitle">User Management</div>
            </div>
        </div>
        <?php require_once __DIR__ . '/_navbar.php'; render_navbar('users', 'admin'); ?>
    </header>

    <main class="panel">
        <section>
            <h2>Registered Users</h2>
            <div id="users-table" class="data-table"></div>
        </section>

        <section style="margin-top:24px;">
            <h2>Barangay Accounts</h2>
            <div id="barangay-users-table" class="data-table"></div>
        </section>
    </main>

    <script>
        async function loadUsers() {
            try {
                const resp = await fetch('../api/users-list.php');
                const data = await resp.json();
                const usersTable = document.getElementById('users-table');
                const barangayTable = document.getElementById('barangay-users-table');
                if (!data.ok) {
                    usersTable.innerHTML = '<div class="table-row">Failed to load users</div>';
                    return;
                }
                usersTable.innerHTML = '';
                barangayTable.innerHTML = '';
                data.users.forEach(u => {
                    const row = document.createElement('div');
                    row.className = 'table-row';
                    row.innerHTML = `<div>${u.username}</div><div>${u.email || ''}</div><div>${u.role}</div>`;
                    if (u.role === 'registered') usersTable.appendChild(row);
                    if (u.role === 'barangay') barangayTable.appendChild(row);
                });
            } catch (e) {
                console.error('Failed to load users', e);
            }
        }
        loadUsers();
    </script>
</body>
</html>
