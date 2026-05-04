<?php
// Generate consolidated demo.sql with demo incidents and users with fresh bcrypt hashes
// Usage: php generate-seed.php > ../database/demo.sql

$adminPassword = getenv('CRIME_SEED_ADMIN_PASSWORD') ?: 'ChangeMeAdmin!123';
$barangayPassword = getenv('CRIME_SEED_BARANGAY_PASSWORD') ?: 'ChangeMeBarangay!123';

$adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);
$barangayHash = password_hash($barangayPassword, PASSWORD_DEFAULT);

$barangays = [
    1 => 'alapang', 2 => 'alno', 3 => 'ambiong', 4 => 'bahong', 5 => 'balili',
    6 => 'beckel', 7 => 'betag', 8 => 'bineng', 9 => 'cruz', 10 => 'lubas',
    11 => 'pico', 12 => 'poblacion', 13 => 'puguis', 14 => 'shilan', 15 => 'tawang', 16 => 'wangal',
];

echo "-- Demo data: Incidents and users\n";
echo "-- Run after: schema.sql\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Default credentials: admin/" . $adminPassword . ", brgy_*/" . $barangayPassword . "\n\n";

// Demo incidents
echo "-- Demo incidents (8 sample incidents)\n";
echo "INSERT INTO incidents (crime_type_id, title, description, barangay_id, latitude, longitude, occurred_at, severity, status, source, is_public) VALUES\n";
echo "(1, 'Assault reported near Balili', 'Witnesses report a physical altercation near the market.', 5, 16.45520000, 120.59010000, '2026-04-27 18:20:00', 'high', 'under_investigation', 'verified', 1),\n";
echo "(3, 'Theft incident at Pico', 'A small business reported missing equipment.', 11, 16.42510000, 120.59190000, '2026-04-26 10:15:00', 'medium', 'pending', 'reported', 0),\n";
echo "(6, 'Drug-related report in Poblacion', 'Barangay officials responded to a tip on illicit substances.', 12, 16.44810000, 120.58920000, '2026-04-25 21:30:00', 'high', 'action_taken', 'verified', 1),\n";
echo "(9, 'Traffic offense near Ambiong', 'Collision reported on the main road, no injuries.', 3, 16.45590000, 120.59370000, '2026-04-24 08:40:00', 'low', 'resolved', 'verified', 1),\n";
echo "(8, 'Vandalism near Bahong school', 'Graffiti reported on school property.', 4, 16.46980000, 120.56640000, '2026-04-23 19:05:00', 'medium', 'pending', 'reported', 0),\n";
echo "(4, 'Fraud report from Puguis', 'Suspicious collection activity reported by residents.', 13, 16.45740000, 120.57890000, '2026-04-22 14:10:00', 'medium', 'under_investigation', 'reported', 0),\n";
echo "(7, 'Online scam complaint in Cruz', 'Resident reported a social media marketplace scam.', 9, 16.46430000, 120.59750000, '2026-04-21 16:45:00', 'medium', 'pending', 'reported', 0),\n";
echo "(5, 'Public order disturbance in Tawang', 'Noise complaint filed after midnight.', 15, 16.44300000, 120.56950000, '2026-04-20 00:35:00', 'low', 'resolved', 'verified', 1);\n\n";

// Demo users
echo "-- Demo users (admin + 16 barangay officers with bcrypt hashes)\n";
echo "INSERT INTO users (username, email, contact, password_hash, role, barangay_id, created_at) VALUES\n";
echo "('admin', 'admin@crime.local', NULL, '" . addslashes($adminHash) . "', 'admin', NULL, NOW());\n\n";

echo "INSERT INTO users (username, email, contact, password_hash, role, barangay_id, created_at) VALUES\n";
$values = [];
for ($i = 1; $i <= 16; $i++) {
    $name = $barangays[$i];
    $contact = '+63900000' . str_pad($i, 4, '0', STR_PAD_LEFT);
    $values[] = "('brgy_$name', '$name@crime.local', '$contact', '" . addslashes($barangayHash) . "', 'barangay', $i, NOW())";
}
echo implode(",\n", $values) . ";\n";

