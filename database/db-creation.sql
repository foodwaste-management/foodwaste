-- 1. Drop database if it exists
DROP DATABASE IF EXISTS foodwaste_db;

-- 2. Create database
CREATE DATABASE foodwaste_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- 3. Use database
USE foodwaste_db;

-- 4. USERS TABLE (RETAINED)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','user') NOT NULL DEFAULT 'user',
    verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. GAS USAGE TABLE (Feature #1)
CREATE TABLE gas_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    flow_rate FLOAT,
    gas_used FLOAT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 6. METHANE MONITORING TABLE (Feature #2)
CREATE TABLE methane_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    methane_ppm FLOAT,
    status ENUM('SAFE','WARNING','LEAK') DEFAULT 'SAFE',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 7. GAS LEVEL TABLE (Feature #3)
CREATE TABLE gas_level (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    pressure_kpa FLOAT,
    gas_percentage FLOAT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 8. ACTIVITY LOGS TABLE (MODIFIED)
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(255),
    activity VARCHAR(255) NOT NULL,
    activity_type ENUM('login','sensor','system','admin'),
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);