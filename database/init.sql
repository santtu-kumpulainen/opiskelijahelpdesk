CREATE DATABASE IF NOT EXISTS opiskelijahelpdesk
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE opiskelijahelpdesk;

// users taulu
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'support', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);