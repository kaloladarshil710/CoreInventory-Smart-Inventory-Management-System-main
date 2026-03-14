<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = [
                'id'   => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
                'email'=> $user['email'],
            ];
            header('Location: ' . BASE_URL . '/pages/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoreInventory — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">Core<strong>Inv</strong></div>
        <div class="auth-subtitle">Inventory Management System</div>

        <h2>Sign In</h2>

        <?php if ($error): ?>
        <div class="flash-message flash-error" style="margin-bottom:18px;">
            <?= clean($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="admin@coreinventory.com"
                    value="<?= clean($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <div style="margin-bottom:16px; text-align:right;">
                <a href="<?= BASE_URL ?>/pages/forgot_password.php" style="font-size:12px;color:var(--accent);text-decoration:none;">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Sign In</button>
        </form>

        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);font-size:12px;color:var(--text3);text-align:center;">
            Default: admin@coreinventory.com / password
        </div>
    </div>
</div>
</body>
</html>
