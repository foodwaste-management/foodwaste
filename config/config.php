<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'foodwaste_db');  // Must match your database name
define('DB_USER', 'root');           // XAMPP default
define('DB_PASS', '');               // XAMPP default is empty

define('BASE_URL', 'http://localhost/foodwaste');

// PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}