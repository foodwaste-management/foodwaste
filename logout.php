<?php
require_once __DIR__ . '/config/config.php';

// Only start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {

    $user_id = $_SESSION['user_id'];
    $email   = $_SESSION['email'];

    // Log the logout event
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, email, activity, activity_type, ip_address)
        VALUES (?, ?, 'User logged out', 'logout', ?)
    ");

    $stmt->execute([$user_id, $email, $_SERVER['REMOTE_ADDR']]);
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header("Location: /foodwaste/index.php");
exit;