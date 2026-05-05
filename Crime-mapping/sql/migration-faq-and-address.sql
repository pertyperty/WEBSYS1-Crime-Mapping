-- FAQ and Users Table Enhancement Migration
-- Run this to add FAQ management support and address field to users

-- Create FAQ table
CREATE TABLE IF NOT EXISTS faqs (
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

-- Add address column to users table (if it doesn't exist)
ALTER TABLE users ADD COLUMN address TEXT NULL AFTER contact;

-- Seed some default FAQs
INSERT INTO faqs (question, answer, category, sort_order, is_active) VALUES
('What data is shown?', 'Only verified or approved incidents are displayed on the public map. Pending reports stay private until barangay or admin review.', 'General', 1, 1),
('How do I report an incident?', 'Create a registered account, open the map, and submit a report with location, category, and optional media.', 'Reporting', 2, 1),
('What is community validation?', 'Guests and registered users can add a thumbs up or down to help moderators assess report credibility.', 'General', 3, 1),
('Who can manage incidents?', 'Barangay officials verify reports in their area, while admin users manage the full dataset and system settings.', 'Verification', 4, 1)
ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order);
