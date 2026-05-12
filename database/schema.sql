-- Crime Mapping Database Schema
-- MySQL/MariaDB
-- Usage: mysql crime_mapping < schema.sql

CREATE DATABASE IF NOT EXISTS crime_mapping;
USE crime_mapping;

-- Reference tables
CREATE TABLE barangays (
    barangay_id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE crime_types (
    crime_type_id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('violent', 'property', 'white_collar', 'drug', 'cybercrime', 'public_order', 'traffic', 'status_offense') NOT NULL,
    type_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crime_type (category, type_name)
);

-- Users and roles
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    contact VARCHAR(20) NULL,
    address TEXT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'barangay', 'registered') NOT NULL,
    barangay_id INT NULL,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Incidents (Reports)
CREATE TABLE incidents (
    incident_id INT AUTO_INCREMENT PRIMARY KEY,
    crime_type_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    barangay_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    occurred_at DATETIME NOT NULL,
    severity ENUM('low', 'medium', 'high') NOT NULL,
    status ENUM('pending', 'under_investigation', 'action_taken', 'resolved', 'dismissed') NOT NULL DEFAULT 'pending',
    source ENUM('reported', 'verified', 'imported') NOT NULL DEFAULT 'reported',
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    reported_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (crime_type_id) REFERENCES crime_types(crime_type_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE incident_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE incident_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    remarks TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Community validation
CREATE TABLE incident_validations (
    validation_id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    user_id INT NULL,
    guest_token VARCHAR(64) NULL,
    reaction ENUM('credible', 'not_credible') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY uq_validation_user (incident_id, user_id),
    UNIQUE KEY uq_validation_guest (incident_id, guest_token)
);

-- Notifications
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    barangay_id INT NULL,
    incident_id INT NULL,
    notification_type ENUM('new_report', 'status_update', 'high_severity', 'mention') NOT NULL,
    message VARCHAR(255) NOT NULL,
    sms_status ENUM('pending', 'sent', 'failed') NULL DEFAULT NULL,
    sms_sent_at TIMESTAMP NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- SMS notification queue
CREATE TABLE notification_sms_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message VARCHAR(255) NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts TINYINT(1) NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    locked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_status (status),
    INDEX idx_locked_at (locked_at)
);

-- FAQ Management
CREATE TABLE faqs (
    faq_id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
);

-- Performance indexes
CREATE INDEX idx_incident_barangay ON incidents(barangay_id);
CREATE INDEX idx_incident_status ON incidents(status);
CREATE INDEX idx_incident_severity ON incidents(severity);
CREATE INDEX idx_incident_date ON incidents(occurred_at);
CREATE INDEX idx_incident_location ON incidents(latitude, longitude);
CREATE INDEX idx_validation_incident ON incident_validations(incident_id);

-- Seed: Barangays (16 barangays of La Trinidad)
INSERT INTO barangays (barangay_name) VALUES
('Alapang'), ('Alno'), ('Ambiong'), ('Bahong'), ('Balili'), ('Beckel'), ('Betag'), ('Bineng'),
('Cruz'), ('Lubas'), ('Pico'), ('Poblacion'), ('Puguis'), ('Shilan'), ('Tawang'), ('Wangal');

-- Seed: Crime type categories (47 crime types across 8 categories)
INSERT INTO crime_types (category, type_name) VALUES
-- Violent crimes (6)
('violent', 'Murder / Homicide'),
('violent', 'Assault and Battery'),
('violent', 'Robbery (theft with force)'),
('violent', 'Kidnapping'),
('violent', 'Sexual Assault / Rape'),
('violent', 'Domestic Violence'),
-- Property crimes (6)
('property', 'Theft / Shoplifting'),
('property', 'Burglary (break and enter)'),
('property', 'Arson'),
('property', 'Vandalism'),
('property', 'Car Theft / Carjacking'),
('property', 'Trespassing'),
-- White-collar crimes (6)
('white_collar', 'Fraud (credit card, insurance)'),
('white_collar', 'Embezzlement'),
('white_collar', 'Tax Evasion'),
('white_collar', 'Bribery / Corruption'),
('white_collar', 'Money Laundering'),
('white_collar', 'Identity Theft'),
-- Drug crimes (6)
('drug', 'Possession of Illegal Drugs'),
('drug', 'Drug Trafficking / Dealing'),
('drug', 'Manufacturing Drugs'),
('drug', 'Drug Paraphernalia Possession'),
('drug', 'Prescription Drug Fraud'),
('drug', 'DUI / Driving Under Influence'),
-- Cybercrime (6)
('cybercrime', 'Hacking / Unauthorized Access'),
('cybercrime', 'Phishing and Online Scams'),
('cybercrime', 'Cyberbullying / Online Harassment'),
('cybercrime', 'Malware / Ransomware Attacks'),
('cybercrime', 'Unauthorized Data Collection'),
('cybercrime', 'Online Piracy'),
-- Public order / nuisance offenses (10)
('public_order', 'Noise Complaints / Disturbing Peace'),
('public_order', 'Drunk and Disorderly Conduct'),
('public_order', 'Loitering'),
('public_order', 'Public Intoxication'),
('public_order', 'Jaywalking'),
('public_order', 'Littering / Illegal Dumping'),
('public_order', 'Indecent Exposure'),
('public_order', 'Disorderly Conduct'),
('public_order', 'Illegal Street Racing'),
('public_order', 'Illegal Gambling'),
-- Traffic offenses (6)
('traffic', 'Speeding'),
('traffic', 'Running a Red Light'),
('traffic', 'Reckless Driving'),
('traffic', 'Hit and Run'),
('traffic', 'Driving Without a License'),
('traffic', 'Illegal Parking'),
-- Status offenses (4)
('status_offense', 'Underage Drinking'),
('status_offense', 'Truancy (skipping school)'),
('status_offense', 'Curfew Violations'),
('status_offense', 'Minors in Possession of Tobacco');

-- Seed: Default FAQs
INSERT INTO faqs (question, answer, category, sort_order, is_active) VALUES
('What data is shown?', 'Only verified or approved incidents are displayed on the public map. Pending reports stay private until barangay or admin review.', 'General', 1, 1),
('How do I report an incident?', 'Create a registered account, open the map, and submit a report with location, category, and optional media.', 'Reporting', 2, 1),
('What is community validation?', 'Guests and registered users can add a thumbs up or down to help moderators assess report credibility.', 'General', 3, 1),
('Who can manage incidents?', 'Barangay officials verify reports in their area, while admin users manage the full dataset and system settings.', 'Verification', 4, 1);
