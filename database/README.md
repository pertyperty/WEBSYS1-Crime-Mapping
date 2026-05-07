# Database Management

Centralized SQL database files for Crime Mapping system.

## Files

### `schema.sql`
- Complete database schema with all tables and indexes
- Includes static seed data: barangays (16) and crime type categories (47), default FAQs (4)
- ~220 lines (includes faqs table and user address column)
- **Import first**: `mysql crime_mapping < schema.sql`

### `demo.sql`
- Demo incidents (8 sample reports across barangays)
- Demo user accounts: 1 admin + 16 barangay officers
- All passwords hashed with bcrypt
- **Import after schema**: `mysql crime_mapping < demo.sql`

## Quick Start

### 1. Create Schema
```bash
mysql crime_mapping < database/schema.sql
```

### 2. Load Demo Data (Optional)
```bash
mysql crime_mapping < database/demo.sql
```

### 3. Via PHP Seed Loader
```bash
php Crime-mapping/tools/seed.php
```
This loader:
- Reads the SQL files and executes them in order
- Supports `--regenerate-users` flag to create fresh password hashes

### Migration Files
**Note:** The migration file `sql/migration-faq-and-address.sql` is no longer needed. All schema updates are now included in the main `schema.sql` file, which includes:
- `faqs` table for FAQ management
- `address` column in users table
- All indexes and constraints

## Demo Credentials

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `ChangeMeAdmin!123` |
| Barangay Officers (16) | `brgy_alapang`, `brgy_alno`, ... `brgy_wangal` | `ChangeMeBarangay!123` |

## Managing User Passwords

To generate new bcrypt hashes:
```bash
php Crime-mapping/tools/generate-seed.php > database/demo.sql
```

Environment variables (optional):
- `CRIME_SEED_ADMIN_PASSWORD` – Override admin password
- `CRIME_SEED_BARANGAY_PASSWORD` – Override barangay password

## Database Structure

**Reference Tables:**
- `barangays` – 16 La Trinidad barangays
- `crime_types` – 47 comprehensive crime types organized by 8 categories:
  - **Violent**: Murder, Assault, Robbery (with force), Kidnapping, Sexual Assault, Domestic Violence
  - **Property**: Theft, Burglary, Arson, Vandalism, Car Theft, Trespassing
  - **White-collar**: Fraud, Embezzlement, Tax Evasion, Bribery, Money Laundering, Identity Theft
  - **Drug**: Possession, Trafficking, Manufacturing, Paraphernalia, Prescription Fraud, DUI
  - **Cybercrime**: Hacking, Phishing, Cyberbullying, Malware, Data Collection, Piracy
  - **Public Order**: Noise, Drunk Conduct, Loitering, Intoxication, Jaywalking, Littering, Indecent Exposure, Disorderly Conduct, Street Racing, Illegal Gambling
  - **Traffic**: Speeding, Red Light, Reckless Driving, Hit & Run, No License, Illegal Parking
  - **Status Offenses**: Underage Drinking, Truancy, Curfew Violations, Tobacco Possession

**Core Tables:**
- `users` – Admins, barangay officers, registered users
- `incidents` – Crime reports/incidents
- `incident_images` – Attached images to incidents
- `incident_logs` – Audit trail for incident changes
- `incident_validations` – Community validation (credible/not_credible)
- `notifications` – User alerts and notifications

## File Optimization

- **Schema**: Removed excessive comments, condensed formatting → ~190 lines
- **Demo**: Combined incidents + users in one file → ~40 lines
- **Removed**: Separate seed files for users/incidents (now consolidated)
- **Idempotent**: Uses `ON DUPLICATE KEY UPDATE` for safe re-runs
