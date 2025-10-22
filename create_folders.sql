-- Create default folders
INSERT IGNORE INTO folders (id, name, parent_id, created_at, updated_at) VALUES 
(1, 'inbox', NULL, NOW(), NOW()),
(2, 'imported', NULL, NOW(), NOW()),
(3, 'sent', NULL, NOW(), NOW()),
(4, 'draft', NULL, NOW(), NOW()),
(5, 'trash', NULL, NOW(), NOW());

-- Create subfolders under inbox
INSERT IGNORE INTO folders (id, name, parent_id, created_at, updated_at) VALUES 
(6, 'Important', 1, NOW(), NOW()),
(7, 'Archive', 1, NOW(), NOW());