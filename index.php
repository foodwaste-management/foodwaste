<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$error = '';

// Redirect already logged-in users
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {

    switch ($_SESSION['role']) {

        case 'admin':
            header('Location: /foodwaste/app/admin/dashboard.php');
            exit;

        case 'manager':
            header('Location: /foodwaste/app/manager/dashboard.php');
            exit;

        case 'user':
            header('Location: /foodwaste/app/users/dashboard.php');
            exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verified = 1");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        // Create session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];

        // Log successful login
        $log = $pdo->prepare("
        INSERT INTO activity_logs (user_id,email,activity,activity_type,ip_address)
        VALUES (?,?,?,?,?)
        ");

        $log->execute([
            $user['user_id'],
            $user['email'],
            'User logged in',
            'login',
            $_SERVER['REMOTE_ADDR']
        ]);

        // Redirect based on role
        switch ($user['role']) {

            case 'admin':
                header('Location: /foodwaste/app/admin/dashboard.php');
                exit;

            case 'manager':
                header('Location: /foodwaste/app/manager/dashboard.php');
                exit;

            case 'user':
                header('Location: /foodwaste/app/users/dashboard.php');
                exit;
        }

    } else {

        // Log failed login
        $log = $pdo->prepare("
        INSERT INTO activity_logs (email,activity,activity_type,ip_address)
        VALUES (?,?,?,?)
        ");

        $log->execute([
            $email,
            'Failed login attempt',
            'login_failed',
            $_SERVER['REMOTE_ADDR']
        ]);

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

body{
font-family:Arial, sans-serif;
background:#f0f2f5;
display:flex;
justify-content:center;
align-items:center;
height:100vh;
margin:0;
}

.login-container{
background:#fff;
padding:35px;
border-radius:8px;
box-shadow:0 5px 15px rgba(0,0,0,0.1);
width:360px;
}

h1{
text-align:center;
margin-bottom:25px;
color:#333;
}

.form-group{
margin-bottom:15px;
}

label{
display:block;
margin-bottom:5px;
color:#555;
}

input{
width:100%;
padding:10px;
border:1px solid #ccc;
border-radius:5px;
font-size:14px;
}

button{
width:100%;
padding:10px;
background:#667eea;
color:white;
border:none;
border-radius:5px;
font-size:16px;
cursor:pointer;
transition:0.3s;
}

button:hover{
background:#5563d6;
}

.error{
color:red;
margin-bottom:15px;
text-align:center;
}

.info-box{
font-size:12px;
color:#555;
margin-top:15px;
text-align:center;
}

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
<input type="email" name="email" required>
</div>

<div class="form-group">
<label>Password</label>
<input type="password" name="password" required>
</div>

<button type="submit">Login</button>

</form>

<div class="info-box">
<strong>Test Accounts:</strong><br>
Admin: admin@example.com<br>
Manager: manager@example.com<br>
User: user@example.com<br>
Password: <em>password123</em>
</div>

</div>

</body>
</html>