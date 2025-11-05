-- Create databases for privateforms (both names to be safe)
CREATE DATABASE IF NOT EXISTS `forms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user for privateforms (this will only run on first init, so user won't exist yet)
CREATE USER IF NOT EXISTS 'privatescan-api-api'@'%' IDENTIFIED BY 'privatescan-api-api';

-- Grant privileges to the user for both databases
GRANT ALL PRIVILEGES ON `forms`.* TO 'privatescan-api-api'@'%';

FLUSH PRIVILEGES;
