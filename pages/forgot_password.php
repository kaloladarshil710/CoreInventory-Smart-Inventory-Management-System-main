<?php
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$step = $_SESSION['otp_step'] ?? 'email';
$msg  = '';
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_otp') {
        $email = trim($_POST['email'] ?? '');
        $stmt  = $db->prepare("SELECT * FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = sprintf('%06d', rand(100000, 999999));
            $db->prepare("UPDATE users SET otp=?, otp_expires_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id=?")
               ->execute([$otp, $user['id']]);
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_step']  = 'verify';
            // In production: send via email/SMS. For demo, show in UI.
            $_SESSION['otp_demo'] = $otp;
            $step = 'verify';
            $msg  = "OTP sent! (Demo: check below)";
        } else {
            $err = 'No account found with that email.';
        }
    }

    if ($action === 'verify_otp') {
        $otp   = trim($_POST['otp'] ?? '');
        $email = $_SESSION['otp_email'] ?? '';
        $stmt  = $db->prepare("SELECT * FROM users WHERE email=? AND otp=? AND otp_expires_at > NOW()");
        $stmt->execute([$email, $otp]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['otp_step']    = 'reset';
            $_SESSION['otp_user_id'] = $user['id'];
            $step = 'reset';
        } else {
            $err  = 'Invalid or expired OTP.';
            $step = 'verify';
        }
    }

    if ($action === 'reset_password') {
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if (strlen($pass) < 6) {
            $err = 'Password must be at least 6 characters.';
            $step = 'reset';
        } elseif ($pass !== $pass2) {
            $err = 'Passwords do not match.';
            $step = 'reset';
        } else {
            $uid  = $_SESSION['otp_user_id'] ?? 0;
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=?, otp=NULL, otp_expires_at=NULL WHERE id=?")->execute([$hash, $uid]);
            unset($_SESSION['otp_step'], $_SESSION['otp_email'], $_SESSION['otp_user_id'], $_SESSION['otp_demo']);
            setFlash('success', 'Password reset successfully. Please log in.');
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoreInventory — Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">Core<strong>Inv</strong></div>
        <div class="auth-subtitle">Password Reset</div>

        <?php if ($err): ?>
        <div class="flash-message flash-error" style="margin-bottom:16px;"><?= clean($err) ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
        <div class="flash-message flash-success" style="margin-bottom:16px;"><?= clean($msg) ?></div>
        <?php endif; ?>

        <?php if ($step === 'email'): ?>
        <h2>Find your account</h2>
        <form method="POST">
            <input type="hidden" name="action" value="send_otp">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Send OTP</button>
        </form>

        <?php elseif ($step === 'verify'): ?>
        <h2>Enter OTP</h2>
        <?php if (isset($_SESSION['otp_demo'])): ?>
        <div style="background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:10px 14px;margin-bottom:16px;font-family:var(--font-mono);font-size:22px;font-weight:700;color:var(--accent);text-align:center;">
            <?= $_SESSION['otp_demo'] ?>
            <div style="font-size:11px;color:var(--text3);font-family:var(--font-body);font-weight:400;margin-top:4px;">Demo OTP — configure email in production</div>
        </div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="verify_otp">
            <div class="form-group">
                <label>6-digit OTP</label>
                <input type="text" name="otp" maxlength="6" required placeholder="000000" style="text-align:center;letter-spacing:6px;font-size:20px;">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Verify OTP</button>
        </form>

        <?php else: ?>
        <h2>New Password</h2>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <div class="form-group"><label>New Password</label><input type="password" name="password" required minlength="6"></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" name="password2" required></div>
            <button type="submit" class="btn btn-primary btn-full">Reset Password</button>
        </form>
        <?php endif; ?>

        <div style="margin-top:20px;text-align:center;">
            <a href="<?= BASE_URL ?>/index.php" style="color:var(--text3);font-size:13px;text-decoration:none;">← Back to Login</a>
        </div>
    </div>
</div>
</body>
</html>
