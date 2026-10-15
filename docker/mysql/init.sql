-- Create the testing database alongside the default dev database
CREATE DATABASE IF NOT EXISTS `wms_testing`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Ensure root can access both databases from any host
GRANT ALL PRIVILEGES ON `Wms`.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON `wms_testing`.* TO 'root'@'%';
FLUSH PRIVILEGES;
