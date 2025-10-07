<?php
session_start();
require_once __DIR__ . '/admin_config.php';

$err = '';
if (isset($_POST['username']) && isset($_POST['password'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];
    if ($u === ADMIN_USER && $p === ADMIN_PASS) {
        $_SESSION['admin_logged'] = true;
    header('Location: admin_management.php');
        exit;
    } else {
        $err = 'Invalid username or password';
    }
}
if (!empty($_SESSION['admin_logged'])) {
    header('Location: admin_management.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../css/custom.css">
    <style>
        .auth-wrap{max-width:360px;margin:60px auto}
    </style>
</head>
<body>
    <div class="auth-wrap">
        <div class="appointment-container">
            <h2>Admin Login</h2>
            <form method="post">
                <div class="form-group">
                    <label>Username</label>
                    <input class="form-control" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input class="form-control" name="password" type="password" required>
                </div>
                <button class="btn btn-primary" type="submit">Sign in</button>
            </form>
            <?php if ($err): ?>
                <div class="err"><?php echo htmlspecialchars($err); ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
