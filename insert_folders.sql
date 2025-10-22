INSERT INTO folders (name, parent_id, created_at, updated_at) VALUES 
('inbox', NULL, NOW(), NOW()),
('imported', NULL, NOW(), NOW()),
('sent', NULL, NOW(), NOW()),
('draft', NULL, NOW(), NOW()),
('trash', NULL, NOW(), NOW());

-- Create subfolders under inbox
INSERT INTO folders (name, parent_id, created_at, updated_at) 
SELECT 'Important', id, NOW(), NOW() FROM folders WHERE name = 'inbox';

INSERT INTO folders (name, parent_id, created_at, updated_at) 
SELECT 'Archive', id, NOW(), NOW() FROM folders WHERE name = 'inbox';