-- Init Forms Database Script
-- This script is idempotent - it can be run multiple times safely

-- Create forms database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `forms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user if it doesn't exist
CREATE USER IF NOT EXISTS 'formadmin'@'%' IDENTIFIED BY 'FUC16A-qsh(<d!io';

-- Grant privileges (will update if user already exists)
GRANT ALL PRIVILEGES ON `forms`.* TO 'formadmin'@'%';

-- Flush privileges to apply changes
FLUSH PRIVILEGES;
