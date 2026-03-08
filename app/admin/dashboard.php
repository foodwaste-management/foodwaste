<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

// Only allow admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /foodwaste/index.php');
    exit;
}

?>
<h1>Admin Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?>!</p>
<a href="/foodwaste/auth/signout.php">Logout</a>