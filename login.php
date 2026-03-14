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

        if ($user && password_verify($password, $user['password']) && ($user['is_active'] ?? 1)) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'role'  => $user['role'],
                'email' => $user['email'],
            ];
            header('Location: ' . BASE_URL . '/pages/dashboard.php');
            exit;
        } else {
            if ($user && password_verify($password, $user['password']) && !$user['is_active']) {
            $error = 'Your account has been deactivated. Contact your Admin.';
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — CoreInventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        /* Floating stats on left panel */
        .stat-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 10px;
            transition: border-color 0.3s;
        }
        .stat-pill:hover { border-color: var(--border2); }
        .stat-pill-icon {
            width: 38px; height: 38px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .stat-pill-icon svg { width: 18px; height: 18px; }
        .stat-val { font-family: var(--font-head); font-size: 22px; font-weight: 800; line-height: 1; color: var(--text); }
        .stat-lbl { font-size: 11.5px; color: var(--text3); margin-top: 2px; }
    </style>
</head>
<body>
<div class="auth-page">

    <!-- LEFT PANEL -->
    <div class="auth-left">
        <div class="auth-orb-green"></div>

        <div class="auth-brand-logo">
            <div class="auth-brand-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                    <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
                </svg>
            </div>
            <div class="auth-brand-text">Core<em>Inv</em></div>
        </div>

        <div class="auth-hero">
            <div class="auth-tagline">
                Your Inventory.<br>
                <span class="highlight">Under Control.</span>
            </div>
            <div class="auth-sub">
                A complete Inventory Management System — receipts, deliveries, transfers, adjustments and real-time stock insights in one place.
            </div>

            <!-- Live Stats Pills -->
            <?php
            try {
                $db = getDB();
                $totalProds = $db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
                $totalWH    = $db->query("SELECT COUNT(*) FROM warehouses WHERE is_active=1")->fetchColumn();
                $totalMoves = $db->query("SELECT COUNT(*) FROM stock_ledger")->fetchColumn();
            } catch(Exception $e) {
                $totalProds = '—'; $totalWH = '—'; $totalMoves = '—';
            }
            ?>

            <div class="auth-features" style="margin-top:0;">
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--accent-soft);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?= $totalProds ?></div>
                        <div class="stat-lbl">Active Products Tracked</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--green-soft);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?= $totalWH ?></div>
                        <div class="stat-lbl">Warehouses Connected</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--orange-soft);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?= $totalMoves ?></div>
                        <div class="stat-lbl">Total Stock Movements</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-left-foot">
            &copy; <?= date('Y') ?> CoreInventory — Inventory Management System
        </div>
    </div>

    <!-- RIGHT FORM PANEL -->
    <div class="auth-right">
        <div class="auth-form-box">

            <?php if ($flash): ?>
            <div class="flash-message flash-<?= $flash['type'] ?>" style="margin:0 0 24px; border-radius:var(--radius-sm);">
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
            <?php endif; ?>

            <div class="auth-form-head">
                <h2>Welcome back 👋</h2>
                <p>Sign in to your account to continue managing inventory.<br>
                No account yet? <a href="<?= BASE_URL ?>/signup.php">Create one free</a>.</p>
            </div>

            <?php if ($error): ?>
            <div class="flash-message flash-error" style="margin:0 0 22px; border-radius:var(--radius-sm);">
                ⚠ <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrap has-icon">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" name="email" required
                            placeholder="you@company.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:7px;">
                        <label style="margin-bottom:0;">Password</label>
                        <a href="<?= BASE_URL ?>/pages/forgot_password.php" style="font-size:12px; color:var(--text3);">Forgot password?</a>
                    </div>
                    <div class="input-wrap" style="position:relative;">
                        <input type="password" name="password" id="loginPw" required placeholder="••••••••">
                        <button type="button" class="pw-toggle" onclick="togglePw()">
                            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-check" style="text-transform:none; letter-spacing:0;">
                        <input type="checkbox" name="remember" style="width:17px;height:17px;min-width:17px;padding:0;border-radius:4px;accent-color:var(--accent);">
                        <span style="font-size:13px; color:var(--text2);">Keep me signed in for 30 days</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    Sign In to CoreInventory
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="17" height="17"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>

                <div style="margin-top:22px; padding:16px; background:var(--bg3); border:1px solid var(--border); border-radius:var(--radius-sm);">
                    <div style="font-size:11px; font-weight:700; color:var(--text3); letter-spacing:0.8px; text-transform:uppercase; margin-bottom:8px;">Demo Credentials</div>
                    <div style="font-family:var(--font-mono); font-size:12.5px; color:var(--text2);">
                        admin@coreinventory.com<br>
                        <span style="color:var(--accent);">password</span>
                    </div>
                </div>

                <div style="text-align:center; margin-top:20px;">
                    <span style="font-size:13px; color:var(--text3);">New to CoreInventory? </span>
                    <a href="<?= BASE_URL ?>/signup.php" style="font-size:13px; font-weight:600; color:var(--accent);">Create a free account →</a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function togglePw() {
    const input = document.getElementById('loginPw');
    const icon  = document.getElementById('eyeIcon');
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    icon.style.stroke = isText ? '' : 'var(--accent)';
}
</script>
</body>
</html>
