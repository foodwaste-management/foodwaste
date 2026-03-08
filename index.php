<?php


require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$error = '';

// Redirect logged-in users to their dashboards
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /foodwaste/app/admin/dashboard.php');
            exit;
        case 'manager':
            header('Location: /foodwaste/app/manager/dashboard.php');
            exit;
        case 'user':
            header('Location: /foodwaste/app/user/dashboard.php');
            exit;
    }
}

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verified = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];

        // Redirect based on role
        switch ($user['role']) {
            case 'admin':
                header('Location: /foodwaste/app/admin/dashboard.php');
                exit;
            case 'manager':
                header('Location: /foodwaste/app/manager/dashboard.php');
                exit;
            case 'user':
                header('Location: /foodwaste/app/user/dashboard.php');
                exit;
        }
    } else {
        $error = "Wrong email/password or account not verified.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Food Waste Management - Login</title>
    <style>
        body { font-family: Arial,sans-serif; background:#f0f2f5; display:flex; justify-content:center; align-items:center; height:100vh; }
        .login-container { background:#fff; padding:30px 40px; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.1); width:350px; }
        h1 { text-align:center; margin-bottom:25px; color:#333; }
        .form-group { margin-bottom:15px; }
        label { display:block; margin-bottom:5px; color:#555; }
        input[type="email"], input[type="password"] { width:100%; padding:10px; border:1px solid #ccc; border-radius:5px; font-size:14px; }
        button { width:100%; padding:10px; background:#667eea; color:#fff; border:none; border-radius:5px; font-size:16px; cursor:pointer; transition: background 0.3s; }
        button:hover { background:#5563d6; }
        .error { color:red; margin-bottom:15px; text-align:center; }
        .info-box { font-size:12px; color:#555; margin-top:15px; text-align:center; }
    </style>
</head>
<body>
<div class="login-container">
    <h1>Login</h1>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="admin@example.com" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="password123" required>
        </div>
        <button type="submit">Login</button>
    </form>

    <div class="info-box">
        <strong>Test Accounts:</strong><br>
        Admin: admin@example.com | Manager: manager@example.com | User: user@example.com<br>
        Password: <em>password123</em>
    </div>
</div>
</body>
</html>