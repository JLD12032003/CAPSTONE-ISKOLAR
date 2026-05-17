-- Create and use the correct database
CREATE DATABASE IF NOT EXISTS ISKOLAR_101 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE ISKOLAR_101;

-- =========================
-- USERS TABLE
-- =========================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    google_id VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'donor', 'foundation') NOT NULL DEFAULT 'student',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- EMAIL VERIFICATION TOKENS
-- =========================
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX (user_id),

    CONSTRAINT fk_email_user 
        FOREIGN KEY (user_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================
-- OTP CODES TABLE
-- =========================
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX (user_id),

    CONSTRAINT fk_otp_user 
        FOREIGN KEY (user_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB;