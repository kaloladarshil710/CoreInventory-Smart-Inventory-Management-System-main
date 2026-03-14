<?php
require_once __DIR__ . '/includes/config.php';

// Signup is disabled — accounts are created by Admin/Manager only
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
} else {
    setFlash('warning', 'Account registration is by invitation only. Please contact your Administrator.');
    header('Location: ' . BASE_URL . '/login.php');
}
exit;


$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? 'staff';
    $terms    = isset($_POST['terms']);

    // Validate
    if (!$name || strlen($name) < 2)             $errors['name']     = 'Full name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email']  = 'Please enter a valid email address.';
    if (strlen($password) < 6)                   $errors['password'] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                  $errors['confirm']  = 'Passwords do not match.';
    if (!in_array($role, ['admin','manager','staff'])) $role = 'staff';
    if (!$terms)                                 $errors['terms']    = 'You must accept the terms.';

    if (empty($errors)) {
        $db = getDB();
        // Check duplicate email
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors['email'] = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
               ->execute([$name, $email, $hash, $role]);
            setFlash('success', "Account created! Welcome to CoreInventory. Please sign in.");
            header('Location: ' . BASE_URL . '/login.php');
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
    <title>Create Account — CoreInventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .role-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 0; }
        .role-card {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 13px 12px;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--bg3);
            text-align: center;
        }
        .role-card:hover { border-color: var(--border2); background: var(--bg4); }
        .role-card input[type="radio"] { display: none; }
        .role-card.selected {
            border-color: var(--accent);
            background: var(--accent-soft);
        }
        .role-icon { font-size: 22px; margin-bottom: 5px; }
        .role-name { font-size: 12.5px; font-weight: 600; color: var(--text); display: block; }
        .role-desc { font-size: 11px; color: var(--text3); display: block; margin-top: 2px; }
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
                Smarter Stock.<br>
                <span class="highlight">Zero Chaos.</span>
            </div>
            <div class="auth-sub">
                Join hundreds of warehouse teams who replaced spreadsheets and paper registers with CoreInventory's real-time, centralized system.
            </div>

            <div class="auth-features">
                <div class="auth-feature">
                    <div class="auth-feature-icon" style="background:var(--accent-soft);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    </div>
                    <div class="auth-feature-info">
                        <h4>Real-time Stock Tracking</h4>
                        <p>Every move logged instantly, across warehouses</p>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon" style="background:var(--green-soft);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    </div>
                    <div class="auth-feature-info">
                        <h4>Multi-Warehouse Support</h4>
                        <p>Manage all locations from one dashboard</p>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon" style="background:var(--orange-soft);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                    </div>
                    <div class="auth-feature-info">
                        <h4>Receipts, Deliveries & Transfers</h4>
                        <p>Full operational workflow, fully automated</p>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon" style="background:var(--purple-soft);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--purple)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="auth-feature-info">
                        <h4>Complete Stock Ledger</h4>
                        <p>Every movement logged with full audit trail</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-left-foot">
            &copy; <?= date('Y') ?> CoreInventory. Built for real warehouse teams.
        </div>
    </div>

    <!-- RIGHT FORM PANEL -->
    <div class="auth-right">
        <div class="auth-form-box">

            <div class="auth-form-head">
                <h2>Create your account</h2>
                <p>Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in here</a></p>
            </div>

            <!-- Step indicator -->
            <div class="auth-progress" style="margin-bottom:28px;">
                <div class="auth-prog-step active">
                    <div class="auth-prog-num">1</div>
                    <div class="auth-prog-label">Account Info</div>
                </div>
                <div class="auth-prog-line"></div>
                <div class="auth-prog-step active">
                    <div class="auth-prog-num">2</div>
                    <div class="auth-prog-label">Role & Access</div>
                </div>
                <div class="auth-prog-line"></div>
                <div class="auth-prog-step active">
                    <div class="auth-prog-num">3</div>
                    <div class="auth-prog-label">Security</div>
                </div>
            </div>

            <form method="POST" id="signupForm" novalidate>

                <!-- NAME -->
                <div class="form-group">
                    <label>Full Name</label>
                    <div class="input-wrap has-icon">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" name="name" placeholder="John Appleseed"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                            style="<?= isset($errors['name']) ? 'border-color:var(--red)' : '' ?>">
                    </div>
                    <?php if (isset($errors['name'])): ?>
                    <div class="field-error">⚠ <?= $errors['name'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- EMAIL -->
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrap has-icon">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" name="email" placeholder="john@company.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            style="<?= isset($errors['email']) ? 'border-color:var(--red)' : '' ?>">
                    </div>
                    <?php if (isset($errors['email'])): ?>
                    <div class="field-error">⚠ <?= $errors['email'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- ROLE -->
                <div class="form-group">
                    <label>Your Role</label>
                    <div class="role-cards">
                        <?php
                        $roles = [
                            'admin'   => ['👑', 'Admin',   'Full access'],
                            'manager' => ['📊', 'Manager', 'Manage ops'],
                            'staff'   => ['📦', 'Staff',   'Operations'],
                        ];
                        $selectedRole = $_POST['role'] ?? 'staff';
                        foreach ($roles as $rval => [$icon, $rname, $rdesc]):
                        ?>
                        <label class="role-card <?= $selectedRole === $rval ? 'selected' : '' ?>" onclick="selectRole(this, '<?= $rval ?>')">
                            <input type="radio" name="role" value="<?= $rval ?>" <?= $selectedRole === $rval ? 'checked' : '' ?>>
                            <div class="role-icon"><?= $icon ?></div>
                            <span class="role-name"><?= $rname ?></span>
                            <span class="role-desc"><?= $rdesc ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr class="divider">

                <!-- PASSWORD -->
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap" style="position:relative;">
                        <input type="password" name="password" id="pwField" placeholder="Min. 6 characters"
                            oninput="checkStrength(this.value)"
                            style="<?= isset($errors['password']) ? 'border-color:var(--red)' : '' ?>">
                        <button type="button" class="pw-toggle" onclick="togglePw('pwField', this)">
                            <svg id="pwEye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="pw-meter">
                        <div class="pw-bars">
                            <div class="pw-bar" id="bar1"></div>
                            <div class="pw-bar" id="bar2"></div>
                            <div class="pw-bar" id="bar3"></div>
                            <div class="pw-bar" id="bar4"></div>
                        </div>
                        <div class="pw-label" id="pwLabel">Enter a password</div>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                    <div class="field-error">⚠ <?= $errors['password'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- CONFIRM PASSWORD -->
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-wrap" style="position:relative;">
                        <input type="password" name="confirm_password" id="pwConfirm" placeholder="Repeat password"
                            style="<?= isset($errors['confirm']) ? 'border-color:var(--red)' : '' ?>">
                        <button type="button" class="pw-toggle" onclick="togglePw('pwConfirm', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <?php if (isset($errors['confirm'])): ?>
                    <div class="field-error">⚠ <?= $errors['confirm'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- TERMS -->
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-check" style="text-transform:none;letter-spacing:0;">
                        <input type="checkbox" name="terms" <?= isset($_POST['terms']) ? 'checked' : '' ?>
                            style="width:17px;height:17px;min-width:17px;padding:0;border-radius:4px;accent-color:var(--accent);">
                        <span class="form-check-label">
                            I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                        </span>
                    </label>
                    <?php if (isset($errors['terms'])): ?>
                    <div class="field-error" style="margin-top:6px;">⚠ <?= $errors['terms'] ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>
                    Create Account
                </button>

                <div style="margin-top:20px; text-align:center; font-size:12px; color:var(--text3);">
                    By signing up, you agree to our terms. Your data stays secure.
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function selectRole(el, val) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input').checked = true;
}

function togglePw(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.style.color = isText ? '' : 'var(--accent)';
}

function checkStrength(val) {
    const bars = [document.getElementById('bar1'), document.getElementById('bar2'),
                  document.getElementById('bar3'), document.getElementById('bar4')];
    const label = document.getElementById('pwLabel');
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = ['', 'weak', 'fair', 'good', 'strong'];
    const labels = ['Enter a password', 'Weak', 'Fair', 'Good', 'Strong!'];
    const colors = ['', 'var(--red)', 'var(--orange)', 'var(--yellow)', 'var(--green)'];

    bars.forEach((b, i) => { b.className = 'pw-bar'; if (i < score) b.classList.add(levels[score]); });
    label.textContent = labels[score];
    label.style.color = colors[score] || 'var(--text3)';
}
</script>
</body>
</html>
