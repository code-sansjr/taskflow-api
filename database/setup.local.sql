-- Local development setup for Laragon/MySQL.
-- Run as a MySQL administrator:
--   mysql -u root < database/setup.local.sql
--
-- These credentials are only for local development. Never use them in production.

CREATE DATABASE IF NOT EXISTS taskflow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS taskflow_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'taskflow'@'localhost' IDENTIFIED BY 'taskflow';
CREATE USER IF NOT EXISTS 'taskflow'@'127.0.0.1' IDENTIFIED BY 'taskflow';

GRANT ALL PRIVILEGES ON taskflow.* TO 'taskflow'@'localhost';
GRANT ALL PRIVILEGES ON taskflow.* TO 'taskflow'@'127.0.0.1';
GRANT ALL PRIVILEGES ON taskflow_test.* TO 'taskflow'@'localhost';
GRANT ALL PRIVILEGES ON taskflow_test.* TO 'taskflow'@'127.0.0.1';

FLUSH PRIVILEGES;
