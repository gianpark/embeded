-- MySQL LAMP 초기 설정
-- 실행: mysql -u root -p < setup_db.sql

CREATE DATABASE IF NOT EXISTS monitordb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'gianpark'@'localhost' IDENTIFIED BY 'qwer1234';
GRANT ALL PRIVILEGES ON monitordb.* TO 'gianpark'@'localhost';
FLUSH PRIVILEGES;

USE monitordb;

CREATE TABLE IF NOT EXISTS sensor_data (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    temperature FLOAT,
    humidity    FLOAT,
    pressure    FLOAT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
