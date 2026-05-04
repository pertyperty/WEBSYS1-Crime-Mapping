# La Trinidad Crime Mapping

Crime mapping website for La Trinidad, Benguet. Features a public dashboard and interactive map with reporting, validation, and role-based workflows for barangay and admin users.

## Core Features
- Guest dashboard with stats, mini map, and recent incidents
- Full map view with filters, search, and details sidebar
- Registered user reporting with optional image upload
- Community validation (thumbs up/down)
- Barangay workflow for verification and status updates
- Admin workflow for user management, categories, and analytics
- Notifications for new reports and high-severity alerts

## User Roles
- Guest: Browse dashboard, map, and incident details; validate reports
- Registered: All guest features plus reporting
- Barangay: Dashboard with KPIs; verify and manage incidents in area
- Admin: Global management and monitoring

## Tech Stack
- HTML, CSS, JavaScript
- PHP (PDO) + AJAX
- MySQL/MariaDB (phpMyAdmin)
- Leaflet map
- XAMPP for local testing

## Project Structure (Planned)
- public/ (index, map, login, register)
- api/ (PHP endpoints)
- assets/ (css, js, images)
- uploads/ (incident images)
- sql/ (database scripts)

## Database Setup

All SQL files are centralized in the `/database` folder. See [database/README.md](database/README.md) for detailed documentation.

### Quick Setup

1. **Create schema**:
   ```bash
   mysql crime_mapping < database/schema.sql
   ```

2. **Load demo data** (optional):
   ```bash
   mysql crime_mapping < database/demo.sql
   ```

Or use the PHP seed loader:
   ```bash
   php Crime-mapping/tools/seed.php
   ```

Set environment variables for database and demo credentials per [Crime-mapping/.env.example](Crime-mapping/.env.example).

## Map Behavior (Functional Summary)
- Default filter shows all incidents
- Filter by crime type, date range, barangay, and status
- Toggle marker styles (colored dots vs icon)
- Hover shows preview; click opens right-side detail panel

## API Endpoints (Planned)
- GET /api/incidents
- GET /api/incident
- POST /api/report
- POST /api/incident/status
- POST /api/validation
- GET /api/stats
- GET /api/notifications
- POST /api/auth/login
- POST /api/auth/register
- POST /api/auth/logout

## Next Steps
- Align UI flow with the PDF requirements
- Implement the Phase plan in implementation-phase.md
- Build out the public dashboard and map first

