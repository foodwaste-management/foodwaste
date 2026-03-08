<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

/* Check if user is logged in */
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /foodwaste/index.php");
    exit;
}

$user_email = $_SESSION['email'];

/* Example values (replace later with database values) */
$gas_used_today = "1.4 L";
$methane_status = "SAFE";
$gas_remaining  = "72%";

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>

<style>

body{
    margin:0;
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f6f9;
}

/* top navigation */
.navbar{
    background:#667eea;
    color:white;
    padding:15px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.navbar a{
    color:white;
    text-decoration:none;
    margin-left:20px;
}

/* dashboard container */

.container{
    padding:30px;
}

h2{
    margin-bottom:20px;
}

/* cards */

.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
}

.card{
    background:white;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

.card h3{
    margin-bottom:10px;
    color:#333;
}

.value{
    font-size:28px;
    font-weight:bold;
}

/* status colors */

.safe{
    color:green;
}

.warning{
    color:orange;
}

.danger{
    color:red;
}

</style>
</head>

<body>

<!-- Top Bar -->
<div class="navbar">
    <div>
        <strong>Biogas Monitoring System</strong>
    </div>

    <div>
        <?php echo htmlspecialchars($user_email); ?>
        <a href="/foodwaste/logout.php">Logout</a>
    </div>
</div>


<div class="container">

<h2>User Dashboard</h2>

<div class="cards">

    <!-- Gas Usage -->
    <div class="card">
        <h3>Gas Usage Today</h3>
        <div class="value"><?php echo $gas_used_today; ?></div>
        <p>Gas used from the barrel today.</p>
    </div>

    <!-- Methane Monitoring -->
    <div class="card">
        <h3>Methane Status</h3>
        <div class="value safe"><?php echo $methane_status; ?></div>
        <p>Current methane level detection.</p>
    </div>

    <!-- Gas Remaining -->
    <div class="card">
        <h3>Gas Remaining</h3>
        <div class="value"><?php echo $gas_remaining; ?></div>
        <p>Estimated gas left in the barrel.</p>
    </div>

</div>

</div>

</body>
</html>