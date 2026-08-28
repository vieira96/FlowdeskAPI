CREATE DATABASE IF NOT EXISTS flowdesk_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON flowdesk_testing.* TO 'flowdesk'@'%';
FLUSH PRIVILEGES;
