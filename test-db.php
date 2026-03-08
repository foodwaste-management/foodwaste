<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if config.php exists
$configPath = __DIR__ . '/config/config.php';
var_dump(file_exists($configPath));  // Should return bool(true)

require_once $configPath;

// Check if $pdo is created
var_dump(isset($pdo));

if (!isset($pdo)) {
    die("PDO is not set. Check config.php");
}

$stmt = $pdo->query("SELECT NOW() as now");
$row = $stmt->fetch();
echo "Database connected successfully. Current time: " . $row['now'];