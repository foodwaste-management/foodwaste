<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear session
session_unset();
session_destroy();

// Redirect to login page
header('Location: /foodwaste/index.php');
exit;