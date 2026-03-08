-- 1. Drop database if it exists (optional)
DROP DATABASE IF EXISTS foodwaste_db;

-- 2. Create the database
CREATE DATABASE foodwaste_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- 3. Use the database
USE foodwaste_db;

-- 4. Create Users Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','user') NOT NULL DEFAULT 'user',
    verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Create Activity Logs Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(255) NULL,
    action VARCHAR(255) NOT NULL,
    status ENUM('success','failed') NOT NULL,
    ip_address VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 6. Create System Stats Table
CREATE TABLE system_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL,
    total_food_waste DECIMAL(10,2) DEFAULT 0,  -- in kg
    organic_compost DECIMAL(10,2) DEFAULT 0,   -- in kg
    biogas_produced DECIMAL(10,2) DEFAULT 0,   -- in cubic meters
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

