# Manual Integration Checklist

This file lists all the manual code changes needed to complete the admin/barangay feature implementation.

## 📝 Step-by-Step Integration

### STEP 1: Update Navbar (_navbar.php)
**File**: `Crime-mapping/public/_navbar.php`

**Find the admin items array (line ~14-17):**
```php
case 'admin':
    $items = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'admin-dashboard.php'],
        ['key' => 'map', 'label' => 'Map', 'href' => 'admin-map.php'],
        ['key' => 'incidents', 'label' => 'Incidents', 'href' => 'admin-incidents.php'],
        ['key' => 'users', 'label' => 'User Management', 'href' => 'admin-users.php'],
    ];
```

**Add this new line after 'users':**
```php
        ['key' => 'faq', 'label' => 'FAQ Management', 'href' => 'admin-faq.php'],
```

---

**Find the barangay items array (line ~20-24):**
```php
case 'barangay':
    $items = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'barangay-dashboard.php'],
        ['key' => 'map', 'label' => 'Map', 'href' => 'barangay-map.php'],
        ['key' => 'incidents', 'label' => 'Incidents', 'href' => 'barangay-incidents.php'],
    ];
```

**Add this new line after 'incidents':**
```php
        ['key' => 'add-incident', 'label' => 'Add Incident', 'href' => 'barangay-add-incident.php'],
```

---

### STEP 2: Enhance Admin Dashboard (admin-dashboard.php)
**File**: `Crime-mapping/public/admin-dashboard.php`

The file needs several enhancements. **Option A: Replace entire file** (simplest - see new version below)

**Option B: Manual patches:**

After the KPI cards section (after line ~46), add new section:
```php
            <section>
                <div style="margin-bottom: 16px;">
                    <p class="eyebrow">Report Generation</p>
                    <h2>Generate Reports</h2>
                </div>
                <div class="dashboard-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px;">
                    <button type="button" onclick="openReportModal('monthly')" class="action-btn" style="padding: 12px 16px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); cursor: pointer;">📊 Monthly Summary</button>
                    <button type="button" onclick="openReportModal('area')" class="action-btn" style="padding: 12px 16px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); cursor: pointer;">🗺️ Area Report</button>
                    <button type="button" onclick="openReportModal('crime')" class="action-btn" style="padding: 12px 16px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); cursor: pointer;">🔍 Crime Analysis</button>
                    <button type="button" onclick="openReportModal('forensics')" class="action-btn" style="padding: 12px 16px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); cursor: pointer;">🔎 Forensics</button>
                    <button type="button" onclick="window.location.href='admin-faq.php'" class="action-btn" style="padding: 12px 16px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); cursor: pointer;">📚 Manage FAQ</button>
                </div>
            </section>
```

Before the closing `</body>` tag, add the report modal:
```html
    <!-- Report Modal -->
    <div id="report-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--surface); border-radius: 12px; padding: 24px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border);">
                <h2 id="report-title" style="margin: 0; font-size: 18px;">Report</h2>
                <button onclick="closeReportModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text); padding: 4px 8px;">×</button>
            </div>
            <form id="report-form" style="display: grid; gap: 16px;">
                <label style="display: flex; flex-direction: column; gap: 6px;">
                    <span style="font-weight: 500; font-size: 13px;">Month</span>
                    <input type="month" id="report-month" style="padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); color: var(--text); font-size: 13px;" />
                </label>
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border);">
                    <button type="button" class="btn-secondary" onclick="closeReportModal()">Close</button>
                    <button type="submit" class="btn-primary">Generate</button>
                </div>
            </form>
            <div id="report-output" style="background: var(--background); border: 1px solid var(--border); border-radius: 6px; padding: 16px; margin-top: 20px; max-height: 400px; overflow-y: auto; font-family: 'Monaco', monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; display: none;"></div>
        </div>
    </div>

    <script>
        function openReportModal(reportType) {
            document.getElementById('report-title').textContent = reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' Report';
            document.getElementById('report-form').dataset.type = reportType;
            document.getElementById('report-month').valueAsDate = new Date();
            document.getElementById('report-output').innerHTML = '';
            document.getElementById('report-output').style.display = 'none';
            document.getElementById('report-modal').style.display = 'flex';
        }

        function closeReportModal() {
            document.getElementById('report-modal').style.display = 'none';
        }

        document.getElementById('report-form').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            
            const reportType = ev.target.dataset.type || 'summary';
            const month = document.getElementById('report-month').value;
            const outputDiv = document.getElementById('report-output');
            
            outputDiv.innerHTML = 'Generating report...';
            outputDiv.style.display = 'block';
            
            try {
                const resp = await fetch(`../api/reports-generate.php?type=${encodeURIComponent(reportType)}&month=${encodeURIComponent(month)}`);
                const result = await resp.json();
                
                if (!result.ok) {
                    outputDiv.textContent = 'Error: ' + (result.error || 'Unknown error');
                    return;
                }
                
                outputDiv.textContent = JSON.stringify(result.data, null, 2);
            } catch (e) {
                outputDiv.textContent = 'Error: ' + e.message;
            }
        });
    </script>
```

---

### STEP 3: Enhance Barangay Dashboard (barangay-dashboard.php)
**File**: `Crime-mapping/public/barangay-dashboard.php`

After the hero section (around line ~45), add:
```php
            <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 24px 0; padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px;">
                <a href="barangay-add-incident.php" class="btn-primary" style="padding: 12px 16px; text-decoration: none; text-align: center; border-radius: 6px;">📝 Add Incident</a>
                <button type="button" onclick="openReportModal('monthly')" style="padding: 12px 16px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); color: var(--text); cursor: pointer;">📊 Monthly Report</button>
                <button type="button" onclick="openReportModal('area')" style="padding: 12px 16px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); color: var(--text); cursor: pointer;">🗺️ Area Stats</button>
                <button type="button" onclick="openReportModal('crime')" style="padding: 12px 16px; border: 1px solid var(--border); border-radius: 6px; background: var(--background); color: var(--text); cursor: pointer;">🔍 Crime Types</button>
            </section>
```

Before closing `</body>`, add the report modal (same as admin dashboard above).

---

### STEP 4: Enhance Admin Incidents Page (admin-incidents.php)
**File**: `Crime-mapping/public/admin-incidents.php`

This requires the most work. Find the incidents grid rendering and enhance card to include image carousel.

In the JavaScript section where incident cards are rendered, update to include image carousel:
```javascript
// Inside the loop where incident cards are created
const carouselHtml = `
    <div style="width: 100%; height: 200px; background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); border-radius: 8px; overflow: hidden; position: relative; margin-bottom: 12px;" class="incident-image-carousel" data-incident-id="${incident.id}">
        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 12px; color: var(--muted);">📷 Loading images...</div>
    </div>
`;
```

Then load images and create carousel for each incident.

---

### STEP 5: Enhance Admin Users Page (admin-users.php)
**File**: `Crime-mapping/public/admin-users.php`

Replace the user loading script with:
```javascript
async function loadUsers() {
    try {
        const resp = await fetch('../api/users-manage.php');
        const data = await resp.json();
        const usersTable = document.getElementById('users-table');
        const barangayTable = document.getElementById('barangay-users-table');
        
        if (!data.ok) {
            usersTable.innerHTML = '<div class="table-row">Failed to load users</div>';
            return;
        }
        
        usersTable.innerHTML = '<div class="table-row header"><div>Username</div><div>Email</div><div>Address</div><div>Status</div><div>Reports</div><div>Actions</div></div>';
        barangayTable.innerHTML = '<div class="table-row header"><div>Username</div><div>Barangay</div><div>Email</div><div>Status</div><div>Reports</div><div>Actions</div></div>';
        
        data.users.forEach(u => {
            const actionHtml = `
                <div style="display: flex; gap: 4px;">
                    <button onclick="toggleUserStatus(${u.user_id}, '${u.status === 'active' ? 'disabled' : 'active'}')" style="padding: 4px 8px; font-size: 11px; border: 1px solid var(--border); border-radius: 4px; cursor: pointer; background: var(--background);">
                        ${u.status === 'active' ? 'Disable' : 'Enable'}
                    </button>
                </div>
            `;
            
            const row = document.createElement('div');
            row.className = 'table-row';
            
            if (u.role === 'registered') {
                row.innerHTML = `
                    <div>${u.username}</div>
                    <div>${u.email || '-'}</div>
                    <div>${u.address || '-'}</div>
                    <div>${u.status}</div>
                    <div>${u.incident_count || 0}</div>
                    <div>${actionHtml}</div>
                `;
                usersTable.appendChild(row);
            } else if (u.role === 'barangay') {
                row.innerHTML = `
                    <div>${u.username}</div>
                    <div>${u.barangay_name || '-'}</div>
                    <div>${u.email || '-'}</div>
                    <div>${u.status}</div>
                    <div>${u.incident_count || 0}</div>
                    <div>${actionHtml}</div>
                `;
                barangayTable.appendChild(row);
            }
        });
    } catch (e) {
        console.error('Failed to load users', e);
    }
}

async function toggleUserStatus(userId, newStatus) {
    if (!confirm(`Change user status to ${newStatus}?`)) return;
    
    try {
        const resp = await fetch('../api/users-manage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                action: 'toggle-status',
                user_id: userId,
                status: newStatus
            })
        });
        const result = await resp.json();
        if (result.ok) {
            loadUsers();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

loadUsers();
```

---

## ✅ Completion Checklist

- [ ] Step 1: Update navbar with FAQ and Add Incident links
- [ ] Step 2: Enhance admin dashboard with report buttons
- [ ] Step 3: Enhance barangay dashboard with report and add incident buttons
- [ ] Step 4: Add image carousel to admin incidents cards
- [ ] Step 5: Add user management controls to admin users page
- [ ] Run database migration: `mysql crime_mapping < sql/migration-faq-and-address.sql`
- [ ] Test FAQ management: `/admin-faq.php`
- [ ] Test report generation: Admin dashboard report buttons
- [ ] Test barangay add incident: `/barangay-add-incident.php`
- [ ] Test user management: Admin users page status controls
- [ ] Verify styling consistency across pages
- [ ] Test all features with different user roles

---

## 🔗 Quick Links to New Files

- Admin FAQ: `/admin-faq.php`
- Barangay Add Incident: `/barangay-add-incident.php`
- FAQ API: `/api/faq.php`
- Reports API: `/api/reports-generate.php`
- Users Management API: `/api/users-manage.php`
- Incident Images API: `/api/incident-images.php`

---

## 💾 Database Setup

```bash
# Run migration
mysql -u [user] -p [database] < sql/migration-faq-and-address.sql

# Or manually in MySQL:
# CREATE TABLE faqs (...)
# ALTER TABLE users ADD COLUMN address TEXT NULL;
```

**That's it! The system will automatically use the new tables and columns.**
