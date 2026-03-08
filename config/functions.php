<?php
// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect to a URL
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// Require a specific role to access a page
function requireRole($role) {
    if (!isLoggedIn() || $_SESSION['role'] !== $role) {
        die("Access denied: You must be a $role to access this page.");
    }
}

// Render page header
function renderHeader($title = '') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($title); ?> - Food Waste Management</title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/styles.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .form-group { margin-bottom: 15px; }
            input { padding: 8px; width: 250px; }
            button { padding: 8px 16px; }
            .error { color: red; margin-bottom: 15px; }
            .info-box { margin-top: 20px; padding: 10px; background: #f0f0f0; }
        </style>
    </head>
    <body>
    <div class="container">
    <?php
}

// Render page footer
function renderFooter() {
    ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    </body>
    </html>
    <?php
}