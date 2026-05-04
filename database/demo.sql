-- Demo data: Incidents and users
-- Run after: schema.sql
-- Default credentials: admin/ChangeMeAdmin!123, brgy_*/ChangeMeBarangay!123

-- Demo incidents (8 sample incidents)
INSERT INTO incidents (crime_type_id, title, description, barangay_id, latitude, longitude, occurred_at, severity, status, source, is_public) VALUES
(1, 'Assault reported near Balili', 'Witnesses report a physical altercation near the market.', 5, 16.45520000, 120.59010000, '2026-04-27 18:20:00', 'high', 'under_investigation', 'verified', 1),
(3, 'Theft incident at Pico', 'A small business reported missing equipment.', 11, 16.42510000, 120.59190000, '2026-04-26 10:15:00', 'medium', 'pending', 'reported', 0),
(6, 'Drug-related report in Poblacion', 'Barangay officials responded to a tip on illicit substances.', 12, 16.44810000, 120.58920000, '2026-04-25 21:30:00', 'high', 'action_taken', 'verified', 1),
(9, 'Traffic offense near Ambiong', 'Collision reported on the main road, no injuries.', 3, 16.45590000, 120.59370000, '2026-04-24 08:40:00', 'low', 'resolved', 'verified', 1),
(8, 'Vandalism near Bahong school', 'Graffiti reported on school property.', 4, 16.46980000, 120.56640000, '2026-04-23 19:05:00', 'medium', 'pending', 'reported', 0),
(4, 'Fraud report from Puguis', 'Suspicious collection activity reported by residents.', 13, 16.45740000, 120.57890000, '2026-04-22 14:10:00', 'medium', 'under_investigation', 'reported', 0),
(7, 'Online scam complaint in Cruz', 'Resident reported a social media marketplace scam.', 9, 16.46430000, 120.59750000, '2026-04-21 16:45:00', 'medium', 'pending', 'reported', 0),
(5, 'Public order disturbance in Tawang', 'Noise complaint filed after midnight.', 15, 16.44300000, 120.56950000, '2026-04-20 00:35:00', 'low', 'resolved', 'verified', 1);

-- Demo users (admin + 16 barangay officers with bcrypt hashes)
INSERT INTO users (username, email, contact, password_hash, role, barangay_id, created_at) VALUES
('admin', 'admin@crime.local', NULL, '$2y$12$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86.GOtLWysm', 'admin', NULL, NOW());

INSERT INTO users (username, email, contact, password_hash, role, barangay_id, created_at) VALUES
('brgy_alapang', 'alapang@crime.local', '+639000000001', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 1, NOW()),
('brgy_alno', 'alno@crime.local', '+639000000002', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 2, NOW()),
('brgy_ambiong', 'ambiong@crime.local', '+639000000003', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 3, NOW()),
('brgy_bahong', 'bahong@crime.local', '+639000000004', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 4, NOW()),
('brgy_balili', 'balili@crime.local', '+639000000005', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 5, NOW()),
('brgy_beckel', 'beckel@crime.local', '+639000000006', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 6, NOW()),
('brgy_betag', 'betag@crime.local', '+639000000007', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 7, NOW()),
('brgy_bineng', 'bineng@crime.local', '+639000000008', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 8, NOW()),
('brgy_cruz', 'cruz@crime.local', '+639000000009', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 9, NOW()),
('brgy_lubas', 'lubas@crime.local', '+639000000010', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 10, NOW()),
('brgy_pico', 'pico@crime.local', '+639000000011', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 11, NOW()),
('brgy_poblacion', 'poblacion@crime.local', '+639000000012', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 12, NOW()),
('brgy_puguis', 'puguis@crime.local', '+639000000013', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 13, NOW()),
('brgy_shilan', 'shilan@crime.local', '+639000000014', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 14, NOW()),
('brgy_tawang', 'tawang@crime.local', '+639000000015', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 15, NOW()),
('brgy_wangal', 'wangal@crime.local', '+639000000016', '$2y$12$6Le3v0mkK6YjxJ3dN8pMyOJmG4YXF1O0cj8UlIV2YJNhNYmI7BkFO', 'barangay', 16, NOW());
