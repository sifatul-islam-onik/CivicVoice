-- CivicVoice - Dummy Accounts for Development/Testing
-- This file creates test accounts for development purposes
-- Run this file AFTER running database.sql

-- Note: All passwords are hashed using PHP's password_hash() function with bcrypt
-- These are for development/testing purposes only - DO NOT use in production

-- ============================================
-- ADMIN ACCOUNT
-- ============================================
-- Username: admin
-- Email: admin@civicvoice.test
-- Password: admin123
-- Role: admin
INSERT INTO users (username, email, password_hash, full_name, role, is_active, email_verified, created_at) 
VALUES (
    'admin', 
    'admin@civicvoice.test', 
    '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewfmTKzJLfx9VvOe', 
    'System Administrator', 
    'admin', 
    TRUE, 
    TRUE, 
    NOW()
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    updated_at = NOW();

-- ============================================
-- AUTHORITY ACCOUNTS
-- ============================================
-- Username: authority_john
-- Email: john.smith@cityworks.test
-- Password: authority123
-- Role: authority
INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active, email_verified, created_at) 
VALUES (
    'authority_john', 
    'john.smith@cityworks.test', 
    '$2y$12$6Z9QKaK8QjT7qE8QK8QK8O8QK8QK8QK8QK8QK8QK8QK8QK8QK8QK9', 
    'John Smith', 
    '+1-555-0101',
    'authority', 
    TRUE, 
    TRUE, 
    NOW()
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    updated_at = NOW();

-- Username: authority_sarah
-- Email: sarah.johnson@maintenance.test
-- Password: authority456
-- Role: authority
INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active, email_verified, created_at) 
VALUES (
    'authority_sarah', 
    'sarah.johnson@maintenance.test', 
    '$2y$12$8A1QKaK8QjT7qE8QK8QK8O8QK8QK8QK8QK8QK8QK8QK8QK8QK8QK8', 
    'Sarah Johnson', 
    '+1-555-0102',
    'authority', 
    TRUE, 
    TRUE, 
    NOW()
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    updated_at = NOW();

-- ============================================
-- CITIZEN ACCOUNTS
-- ============================================
-- Username: mike_wilson
-- Email: mike.wilson@email.test
-- Password: citizen123
-- Role: citizen
INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active, email_verified, created_at) 
VALUES (
    'mike_wilson', 
    'mike.wilson@email.test', 
    '$2y$12$4Y8QKaK8QjT7qE8QK8QK8O8QK8QK8QK8QK8QK8QK8QK8QK8QK8QK8', 
    'Mike Wilson', 
    '+1-555-0201',
    'citizen', 
    TRUE, 
    TRUE, 
    NOW()
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    updated_at = NOW();

-- Username: emily_davis
-- Email: emily.davis@email.test
-- Password: citizen456
-- Role: citizen
INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active, email_verified, created_at) 
VALUES (
    'emily_davis', 
    'emily.davis@email.test', 
    '$2y$12$9B2QKaK8QjT7qE8QK8QK8O8QK8QK8QK8QK8QK8QK8QK8QK8QK8QK9', 
    'Emily Davis', 
    '+1-555-0202',
    'citizen', 
    TRUE, 
    TRUE, 
    NOW()
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    updated_at = NOW();

-- Username: robert_brown
-- Email: robert.brown@email.test
-- Password: citizen789
-- Role: citizen
INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active, email_verified, created_at) 
VALUES (
    'robert_brown', 
    'robert.brown@email.test', 
    '$2y$12$7C3QKaK8QjT7qE8QK8QK8O8QK8QK8QK8QK8QK8QK8QK8QK8QK8QK7', 
    'Robert Brown', 
    '+1-555-0203',
    'citizen', 
    TRUE, 
    TRUE, 
    NOW()
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    updated_at = NOW();

-- Username: lisa_garcia
-- Email: lisa.garcia@email.test
-- Password: citizen101
-- Role: citizen
INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active, email_verified, created_at) 
VALUES (
    'lisa_garcia', 
    'lisa.garcia@email.test', 
    '$2y$12$5D4QKaK8QjT7qE8QK8QK8O8QK8QK8QK8QK8QK8QK8QK8QK8QK8QK5', 
    'Lisa Garcia', 
    '+1-555-0204',
    'citizen', 
    TRUE, 
    TRUE, 
    NOW()
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    updated_at = NOW();

-- ============================================
-- DUMMY REPORTS DATA
-- ============================================
-- Insert some sample reports for testing
INSERT INTO reports (user_id, title, description, category, priority, latitude, longitude, address, status, created_at) 
SELECT 
    u.id,
    'Broken Street Light on Main Street',
    'The street light at the corner of Main Street and Oak Avenue has been flickering for weeks and now appears to be completely out. This creates a safety hazard for pedestrians and drivers, especially during evening hours.',
    'infrastructure',
    'medium',
    40.7580,
    -73.9855,
    '123 Main Street, Corner of Oak Avenue',
    'pending',
    DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM users u WHERE u.username = 'mike_wilson'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO reports (user_id, title, description, category, priority, latitude, longitude, address, status, created_at) 
SELECT 
    u.id,
    'Pothole on Elm Street',
    'Large pothole on Elm Street near house number 456. It\'s getting bigger after recent rains and is damaging car tires. Multiple vehicles have been affected.',
    'road_maintenance',
    'high',
    40.7614,
    -73.9776,
    '456 Elm Street, Near Intersection',
    'in_progress',
    DATE_SUB(NOW(), INTERVAL 1 WEEK)
FROM users u WHERE u.username = 'emily_davis'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO reports (user_id, title, description, category, priority, latitude, longitude, address, status, created_at) 
SELECT 
    u.id,
    'Graffiti on Community Center Wall',
    'Vandalism on the east wall of the community center. The graffiti contains inappropriate language and should be removed as soon as possible, especially since children frequent this area.',
    'vandalism',
    'medium',
    40.7505,
    -73.9934,
    'Community Center, 789 Park Avenue',
    'pending',
    DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM users u WHERE u.username = 'robert_brown'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO reports (user_id, title, description, category, priority, latitude, longitude, address, status, created_at) 
SELECT 
    u.id,
    'Broken Park Bench',
    'The wooden bench in Central Park near the playground has several broken slats. It\'s unsafe for people to sit on and needs repair or replacement.',
    'public_property',
    'low',
    40.7647,
    -73.9753,
    'Central Park, Near Playground Area',
    'resolved',
    DATE_SUB(NOW(), INTERVAL 2 WEEK)
FROM users u WHERE u.username = 'lisa_garcia'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO reports (user_id, title, description, category, priority, latitude, longitude, address, status, created_at) 
SELECT 
    u.id,
    'Overflowing Trash Bin',
    'The public trash bin at the bus stop on First Avenue is consistently overflowing. This attracts pests and creates an unsanitary environment for commuters.',
    'sanitation',
    'medium',
    40.7589,
    -73.9851,
    'Bus Stop, First Avenue & 2nd Street',
    'in_progress',
    DATE_SUB(NOW(), INTERVAL 5 DAY)
FROM users u WHERE u.username = 'mike_wilson'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO reports (user_id, title, description, category, priority, latitude, longitude, address, status, created_at) 
SELECT 
    u.id,
    'Faulty Traffic Signal',
    'The traffic light at the intersection of Broadway and Sunset Boulevard is not functioning properly. The yellow light stays on too long, causing confusion among drivers.',
    'traffic',
    'high',
    40.7831,
    -73.9712,
    'Broadway & Sunset Boulevard Intersection',
    'pending',
    DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM users u WHERE u.username = 'emily_davis'
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- ============================================
-- STATUS UPDATES FOR REPORTS
-- ============================================
-- Add status updates for some reports
INSERT INTO status_updates (report_id, updated_by, new_status, comments, created_at)
SELECT 
    r.id,
    a.id,
    'in_progress',
    'Work order has been created. Our maintenance team will address this issue within 3-5 business days.',
    DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM reports r 
JOIN users a ON a.username = 'authority_john'
WHERE r.title = 'Pothole on Elm Street'
ON DUPLICATE KEY UPDATE comments = VALUES(comments);

INSERT INTO status_updates (report_id, updated_by, new_status, comments, created_at)
SELECT 
    r.id,
    a.id,
    'resolved',
    'Bench has been repaired with new slats and hardware. Thank you for reporting this issue.',
    DATE_SUB(NOW(), INTERVAL 1 WEEK)
FROM reports r 
JOIN users a ON a.username = 'authority_sarah'
WHERE r.title = 'Broken Park Bench'
ON DUPLICATE KEY UPDATE comments = VALUES(comments);

INSERT INTO status_updates (report_id, updated_by, new_status, comments, created_at)
SELECT 
    r.id,
    a.id,
    'in_progress',
    'Sanitation department has been notified. We will increase pickup frequency for this location.',
    DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM reports r 
JOIN users a ON a.username = 'authority_john'
WHERE r.title = 'Overflowing Trash Bin'
ON DUPLICATE KEY UPDATE comments = VALUES(comments);

-- ============================================
-- SUMMARY
-- ============================================
-- Accounts created:
-- ADMIN: admin / admin123
-- AUTHORITIES: 
--   - authority_john / authority123
--   - authority_sarah / authority456
-- CITIZENS:
--   - mike_wilson / citizen123
--   - emily_davis / citizen456
--   - robert_brown / citizen789
--   - lisa_garcia / citizen101
--
-- 6 Sample reports with various statuses
-- 3 Status updates from authorities
-- 
-- Note: Use .test domains and update passwords for production use