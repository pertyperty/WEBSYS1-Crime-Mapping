# Admin & Barangay Dashboard Enhancement - Implementation Guide

## ✅ Completed Components

### 1. **FAQ Management System**
- **API**: `api/faq.php` - Full CRUD operations for FAQ entries
- **Page**: `public/admin-faq.php` - Admin interface for managing FAQs
- **Database**: Create table via `sql/migration-faq-and-address.sql`

### 2. **Report Generation System**
- **API**: `api/reports-generate.php` - Generates multiple report types:
  - Monthly Summary: Total, active, resolved, pending incidents
  - Area Report: Statistics by barangay
  - Crime Analysis: Incidents by crime type
  - Forensics: Active investigations with images and validation counts

### 3. **User Management Enhancement**
- **API**: `api/users-manage.php` - Enhanced user management with:
  - Toggle user status (active/disabled)
  - Delete users (soft delete)
  - View detailed user stats including incident counts
  - Address field support

### 4. **Barangay Incident Reporting**
- **Page**: `public/barangay-add-incident.php` - Dedicated form for barangay officers to:
  - Quick entry of crime incidents
  - Pre-fill with crime types from system
  - Set severity levels
  - Auto-populate date/time fields
  - Barangay auto-determined from session

### 5. **Image Management**
- **API**: `api/incident-images.php` - Fetch incident images for carousel display

### 6. **Database Schema**
- **Migration**: `sql/migration-faq-and-address.sql`
  - Creates `faqs` table with categories and sort order
  - Adds `address` column to `users` table
  - Seeds default FAQ entries

---

## 🔧 Manual Updates Required

### To the Navbar (`public/_navbar.php`)
Add these items to the `admin` section:
```php
['key' => 'faq', 'label' => 'FAQ Management', 'href' => 'admin-faq.php'],
```

And to the `barangay` section:
```php
['key' => 'add-incident', 'label' => 'Add Incident', 'href' => 'barangay-add-incident.php'],
```

### To Admin Dashboard (`public/admin-dashboard.php`)
Replace the entire file OR add these sections:
1. Import report modal HTML (see file comments)
2. Add dashboard action buttons for reports (Monthly, Area, Crime, Forensics)
3. Add report generation form/modal
4. Add JavaScript handlers for report generation and display

Key additions:
- New `.dashboard-actions` section with report buttons
- Report modal with form and output area
- `openReportModal(type)` and `closeReportModal()` functions
- Event handlers for form submission

### To Barangay Dashboard (`public/barangay-dashboard.php`)
Add:
1. Button to navigate to `barangay-add-incident.php`
2. Report generation buttons (Monthly, Area, Crime) - restricted to barangay's area
3. Similar modal structure as admin dashboard

### To Admin Incidents Page (`public/admin-incidents.php`)
Enhance the incident cards with:
1. Image carousel for each incident (auto-cycle every 5s)
2. Placeholder image if no images exist
3. Image count indicator
4. Integration with `api/incident-images.php`

Card enhancements:
```javascript
// For each incident card, add image carousel div
<div class="incident-carousel" data-incident-id="${incident.id}">
  <!-- images loaded via JavaScript -->
</div>
```

### To Admin Users Page (`public/admin-users.php`)
Enhance with:
1. Status toggle buttons (Active/Disabled)
2. Delete buttons (soft delete via `api/users-manage.php`)
3. Additional columns: Address, Status, Incident Count
4. Use new `api/users-manage.php` endpoint

Add features:
- Status badge showing account status
- Action buttons for status toggle and delete
- Address field display if present

---

## 🚀 Setup Instructions

### 1. **Database Setup**
```bash
# Connect to your MySQL instance and run:
mysql crime_mapping < sql/migration-faq-and-address.sql
```

### 2. **Test FAQs**
- Navigate to: `/admin-faq.php`
- Add/Edit/Delete FAQ entries
- Visit public `/about.php` to see FAQs (you may want to integrate database FAQs there)

### 3. **Test Report Generation**
- Visit Admin Dashboard
- Click report generation buttons
- View JSON output for each report type

### 4. **Test Barangay Features**
- Login as barangay user
- Navigate to "Add Incident"
- Submit new incident reports
- View in barangay dashboard

### 5. **Complete Incident Cards**
- Manually update `admin-incidents.php` to integrate image carousel
- Add carousel styling and JavaScript
- Test image display and cycling

---

## 📋 File Reference

### New Files Created:
- `api/faq.php` - FAQ management (GET/POST)
- `api/reports-generate.php` - Report generation
- `api/users-manage.php` - User management operations
- `api/incident-images.php` - Image fetching for carousel
- `public/admin-faq.php` - FAQ admin interface
- `public/barangay-add-incident.php` - Barangay incident form
- `sql/migration-faq-and-address.sql` - Database migration

### Files Needing Updates:
- `public/_navbar.php` - Add FAQ and Add Incident links
- `public/admin-dashboard.php` - Add report buttons and modal
- `public/barangay-dashboard.php` - Add report buttons and Add Incident link
- `public/admin-incidents.php` - Add image carousel to cards
- `public/admin-users.php` - Add management controls

---

## 🎨 Styling Notes

All new components use existing CSS variables:
- `var(--primary)` - Primary accent color
- `var(--surface)` - Card/panel background
- `var(--border)` - Border color
- `var(--text)` - Text color
- `var(--muted)` - Muted/secondary text
- `var(--background)` - Page background

Responsive design with mobile-first approach (flexbox/grid).

---

## 🔐 Security Features

- CSRF token validation on all POST requests
- Role-based access control (admin/barangay only)
- XSS protection via `escapeHtml()` functions
- SQL injection prevention via prepared statements
- Soft delete for users (data preservation)
- Self-protection (can't disable own account)

---

## 📊 API Endpoints Summary

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/faq.php` | GET/POST | FAQ CRUD operations |
| `/api/reports-generate.php?type=X&month=Y` | GET | Generate reports |
| `/api/users-manage.php` | GET/POST | User management |
| `/api/incident-images.php?incident_id=X` | GET | Fetch incident images |
| `/api/report.php` | POST | Submit incident report |
| `/api/filters.php` | GET | Crime types (for forms) |

---

## ⏳ Remaining Tasks for Polish

1. **Navbar Integration** - Add FAQ and Add Incident links
2. **Admin Dashboard Polish** - Integrate report modal (mostly done, needs minor tweaks)
3. **Barangay Dashboard Polish** - Add report buttons and Add Incident button
4. **Incident Card Carousel** - JavaScript to cycle through images (client-side)
5. **User Management UI** - Add status/delete controls to user table
6. **Styling** - Ensure consistent look across all pages
7. **Testing** - Full QA of all new features

---

## 💡 Future Enhancements

- PDF export for reports
- Email report generation
- Scheduled report generation
- Advanced filters for report data
- Image upload with preview during incident creation
- Email notifications on report generation
- Role-based dashboard customization
