<?php
// ============================================================
// CoreInventory — Forgot Password (Email OTP Reset)
// Flow: Step 1 → Enter email → Step 2 → Enter OTP → Step 3 → New password
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$db   = getDB();
$step = $_SESSION['reset_step'] ?? 'email';
$err  = '';
$msg  = '';

// ── STEP 1: Send OTP to email ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_otp') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidate old unused tokens
            $db->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")
               ->execute([$user['id']]);

            // Generate new OTP + token
            $otp   = sprintf('%06d', random_int(100000, 999999));
            $token = bin2hex(random_bytes(32));

            $db->prepare("INSERT INTO password_resets (user_id, token, otp, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
               ->execute([$user['id'], $token, $otp]);

            // Build email
            $html   = buildOtpEmailHtml($user['name'], $otp);
            $result = sendMail($email, $user['name'], 'Your CoreInventory Password Reset OTP', $html);

            // Store in session (token to verify OTP later)
            $_SESSION['reset_step']  = 'verify';
            $_SESSION['reset_token'] = $token;
            $_SESSION['reset_email'] = $email;

            if ($result === true) {
                $msg = "OTP sent to <strong>{$email}</strong>. Check your inbox (and spam folder).";
            } else {
                // Email failed — show OTP on screen as fallback (dev mode)
                $_SESSION['reset_otp_fallback'] = $otp;
                $msg = "Email delivery failed. Dev fallback OTP shown below.";
                error_log("Mailer error: $result");
            }
        } else {
            // Always show same message to prevent email enumeration
            $_SESSION['reset_step']  = 'verify';
            $_SESSION['reset_email'] = $email;
            $msg = "If that email is registered, an OTP has been sent.";
        }
        $step = 'verify';
    }
}

// ── STEP 2: Verify OTP ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_otp') {
    $otp   = trim($_POST['otp'] ?? '');
    $token = $_SESSION['reset_token'] ?? '';

    if (!$token) {
        $err = 'Session expired. Please start again.';
        $step = 'email';
        unset($_SESSION['reset_step'], $_SESSION['reset_token'], $_SESSION['reset_email']);
    } else {
        $stmt = $db->prepare("
            SELECT pr.*, u.name, u.email
            FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.token = ?
              AND pr.otp = ?
              AND pr.expires_at > NOW()
              AND pr.used = 0
            LIMIT 1
        ");
        $stmt->execute([$token, $otp]);
        $reset = $stmt->fetch();

        if ($reset) {
            $_SESSION['reset_step']    = 'new_password';
            $_SESSION['reset_user_id'] = $reset['user_id'];
            $_SESSION['reset_verified_token'] = $token;
            unset($_SESSION['reset_otp_fallback']);
            $step = 'new_password';
        } else {
            $err  = 'Invalid or expired OTP. Please try again or request a new one.';
            $step = 'verify';

            // Count failed attempts
            $_SESSION['reset_attempts'] = ($_SESSION['reset_attempts'] ?? 0) + 1;
            if ($_SESSION['reset_attempts'] >= 5) {
                $err = 'Too many failed attempts. Please request a new OTP.';
                unset($_SESSION['reset_step'], $_SESSION['reset_token'],
                      $_SESSION['reset_email'], $_SESSION['reset_attempts']);
                $step = 'email';
            }
        }
    }
}

// ── STEP 3: Set new password ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $uid   = (int)($_SESSION['reset_user_id'] ?? 0);
    $vtoken = $_SESSION['reset_verified_token'] ?? '';

    if (!$uid || !$vtoken) {
        $err = 'Session expired. Please start again.';
        $step = 'email';
        unset($_SESSION['reset_step'], $_SESSION['reset_user_id'], $_SESSION['reset_verified_token']);
    } elseif (strlen($pass) < 6) {
        $err = 'Password must be at least 6 characters.';
        $step = 'new_password';
    } elseif ($pass !== $pass2) {
        $err = 'Passwords do not match.';
        $step = 'new_password';
    } else {
        // Update password
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);

        // Mark reset token as used
        $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$vtoken]);

        // Clear all session reset data
        unset(
            $_SESSION['reset_step'], $_SESSION['reset_token'],
            $_SESSION['reset_email'], $_SESSION['reset_user_id'],
            $_SESSION['reset_verified_token'], $_SESSION['reset_attempts'],
            $_SESSION['reset_otp_fallback']
        );

        setFlash('success', 'Password reset successfully! Please sign in with your new password.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Resend OTP action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_otp') {
    unset($_SESSION['reset_step'], $_SESSION['reset_token'], $_SESSION['reset_email'],
          $_SESSION['reset_attempts'], $_SESSION['reset_otp_fallback']);
    $step = 'email';
    $msg  = 'Please enter your email again to receive a new OTP.';
}

$step = $_SESSION['reset_step'] ?? $step;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        /* OTP Input boxes */
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 8px;
        }
        .otp-box {
            width: 52px; height: 60px;
            background: var(--bg3);
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            text-align: center;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            -moz-appearance: textfield;
        }
        .otp-box::-webkit-inner-spin-button,
        .otp-box::-webkit-outer-spin-button { -webkit-appearance: none; }
        .otp-box:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-soft);
        }
        .otp-box.filled { border-color: var(--accent); color: var(--accent); }
        .otp-box.error  { border-color: var(--red); animation: shake 0.4s; }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60%  { transform: translateX(-6px); }
            40%,80%  { transform: translateX(6px); }
        }

        .step-pill {
            display: flex;
            gap: 8px;
            margin-bottom: 28px;
            background: var(--bg3);
            border: 1px solid var(--border);
            border-radius: var(--radius-pill);
            padding: 4px;
        }
        .step-pill-item {
            flex: 1;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 12px;
            border-radius: var(--radius-pill);
            color: var(--text3);
            transition: all 0.2s;
        }
        .step-pill-item.active {
            background: var(--accent);
            color: #fff;
        }
        .step-pill-item.done {
            background: var(--green-soft);
            color: var(--green);
        }

        .email-sent-box {
            background: var(--green-soft);
            border: 1px solid rgba(34,211,160,0.2);
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 13.5px;
            color: var(--green);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .dev-otp-box {
            background: var(--bg3);
            border: 2px dashed rgba(79,142,255,0.3);
            border-radius: var(--radius-sm);
            padding: 14px;
            margin-bottom: 18px;
            text-align: center;
        }

        .countdown {
            font-size: 12px;
            color: var(--text3);
            text-align: center;
            margin-top: 8px;
        }
        .countdown span { color: var(--orange); font-weight: 600; }

        .pw-strength-bar {
            height: 4px;
            background: var(--bg4);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        .pw-strength-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
        }
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
                Secure<br>
                <span class="highlight">Password Reset</span>
            </div>
            <div class="auth-sub">
                We use a one-time password (OTP) sent directly to your registered email to verify your identity before allowing a password change.
            </div>

            <div class="auth-features">
                <div class="auth-feature">
                    <div class="auth-feature-icon" style="background:var(--accent-soft);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <div class="auth-feature-info">
                        <h4>Email Verification</h4>
                        <p>OTP sent to your registered email only</p>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon" style="background:var(--orange-soft);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="auth-feature-info">
                        <h4>15-Minute Expiry</h4>
                        <p>OTP expires after 15 minutes for security</p>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon" style="background:var(--green-soft);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div class="auth-feature-info">
                        <h4>Single Use</h4>
                        <p>Each OTP can only be used once</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-left-foot">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> — Secure Identity Verification
        </div>
    </div>

    <!-- RIGHT FORM PANEL -->
    <div class="auth-right">
        <div class="auth-form-box">

            <!-- Step Indicator -->
            <div class="step-pill">
                <div class="step-pill-item <?= $step === 'email' ? 'active' : 'done' ?>">
                    <?= $step !== 'email' ? '✓ ' : '' ?>Email
                </div>
                <div class="step-pill-item <?= $step === 'verify' ? 'active' : ($step === 'new_password' ? 'done' : '') ?>">
                    <?= $step === 'new_password' ? '✓ ' : '' ?>OTP Code
                </div>
                <div class="step-pill-item <?= $step === 'new_password' ? 'active' : '' ?>">
                    New Password
                </div>
            </div>

            <?php if ($err): ?>
            <div class="flash-message flash-error" style="margin:0 0 20px;">
                <span>⚠ <?= clean($err) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($msg): ?>
            <div class="email-sent-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?= $msg ?></span>
            </div>
            <?php endif; ?>


            <?php
            // ═══════════════════════════════════════════
            // STEP 1 — Enter Email
            // ═══════════════════════════════════════════
            if ($step === 'email'):
            ?>
            <div class="auth-form-head">
                <h2>Forgot your password?</h2>
                <p>Enter your registered email address and we'll send you a 6-digit OTP to reset your password.</p>
            </div>

            <form method="POST" id="emailForm">
                <input type="hidden" name="action" value="send_otp">
                <div class="form-group">
                    <label>Registered Email Address</label>
                    <div class="input-wrap has-icon">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" name="email" id="emailInput" required
                            placeholder="you@company.com"
                            autofocus>
                    </div>
                    <div class="form-helper">We'll send a 6-digit OTP to this address.</div>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" id="sendBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Send OTP to Email
                </button>
            </form>


            <?php
            // ═══════════════════════════════════════════
            // STEP 2 — Enter OTP
            // ═══════════════════════════════════════════
            elseif ($step === 'verify'):
            ?>
            <div class="auth-form-head">
                <h2>Check your email</h2>
                <p>Enter the 6-digit OTP sent to <strong style="color:var(--text);"><?= clean($_SESSION['reset_email'] ?? '') ?></strong></p>
            </div>

            <?php if (isset($_SESSION['reset_otp_fallback'])): ?>
            <div class="dev-otp-box">
                <div style="font-size:11px;color:var(--text3);font-weight:700;letter-spacing:0.8px;text-transform:uppercase;margin-bottom:8px;">⚠ Dev Mode — Email not configured</div>
                <div style="font-family:var(--font-mono);font-size:36px;font-weight:800;color:var(--accent);letter-spacing:10px;"><?= $_SESSION['reset_otp_fallback'] ?></div>
                <div style="font-size:11px;color:var(--text3);margin-top:6px;">Configure SMTP in includes/mailer.php to send real emails</div>
            </div>
            <?php endif; ?>

            <form method="POST" id="otpForm">
                <input type="hidden" name="action" value="verify_otp">
                <input type="hidden" name="otp" id="otpHidden">

                <div class="form-group" style="text-align:center;">
                    <label style="text-align:center;display:block;margin-bottom:14px;">Enter 6-digit OTP</label>
                    <div class="otp-inputs" id="otpInputs">
                        <input type="number" class="otp-box" maxlength="1" min="0" max="9" inputmode="numeric" autofocus>
                        <input type="number" class="otp-box" maxlength="1" min="0" max="9" inputmode="numeric">
                        <input type="number" class="otp-box" maxlength="1" min="0" max="9" inputmode="numeric">
                        <input type="number" class="otp-box" maxlength="1" min="0" max="9" inputmode="numeric">
                        <input type="number" class="otp-box" maxlength="1" min="0" max="9" inputmode="numeric">
                        <input type="number" class="otp-box" maxlength="1" min="0" max="9" inputmode="numeric">
                    </div>
                    <div class="countdown" id="countdown">OTP expires in <span id="timer">15:00</span></div>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" id="verifyBtn" disabled>
                    Verify OTP
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="17" height="17"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
            </form>

            <div style="margin-top:18px;text-align:center;">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="resend_otp">
                    <button type="submit" style="background:none;border:none;color:var(--accent);cursor:pointer;font-size:13px;font-family:var(--font-body);">
                        Didn't receive it? Send a new OTP →
                    </button>
                </form>
            </div>


            <?php
            // ═══════════════════════════════════════════
            // STEP 3 — New Password
            // ═══════════════════════════════════════════
            else:
            ?>
            <div class="auth-form-head">
                <h2>Set new password</h2>
                <p>OTP verified ✓ — Choose a strong new password for your account.</p>
            </div>

            <form method="POST" id="resetForm">
                <input type="hidden" name="action" value="reset_password">

                <div class="form-group">
                    <label>New Password</label>
                    <div style="position:relative;">
                        <input type="password" name="password" id="newPw" required minlength="6"
                            placeholder="Min. 6 characters" autofocus
                            oninput="checkStrength(this.value)">
                        <button type="button" class="pw-toggle" onclick="togglePw('newPw',this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="pw-strength-bar">
                        <div class="pw-strength-fill" id="strengthFill" style="width:0%;background:var(--red);"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:5px;">
                        <span style="font-size:11px;color:var(--text3);" id="strengthLabel">Enter a password</span>
                        <span style="font-size:11px;color:var(--text3);" id="strengthHint"></span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:24px;">
                    <label>Confirm New Password</label>
                    <div style="position:relative;">
                        <input type="password" name="password2" id="confirmPw" required
                            placeholder="Repeat your password"
                            oninput="checkMatch()">
                        <button type="button" class="pw-toggle" onclick="togglePw('confirmPw',this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div id="matchMsg" style="font-size:12px;margin-top:5px;"></div>
                </div>

                <!-- Requirements -->
                <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:22px;">
                    <div style="font-size:11px;font-weight:700;color:var(--text3);letter-spacing:0.8px;text-transform:uppercase;margin-bottom:10px;">Password Requirements</div>
                    <div id="req1"  class="pw-req">&#x25CB; At least 6 characters</div>
                    <div id="req2"  class="pw-req">&#x25CB; At least one number</div>
                    <div id="req3"  class="pw-req">&#x25CB; At least one uppercase letter</div>
                    <div id="req4"  class="pw-req">&#x25CB; At least one special character (!@#$...)</div>
                </div>

                <button type="submit" class="btn btn-success btn-full btn-lg" id="resetBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="17" height="17"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Reset Password & Sign In
                </button>
            </form>

            <?php endif; ?>


            <div style="margin-top:24px;text-align:center;border-top:1px solid var(--border);padding-top:20px;">
                <a href="<?= BASE_URL ?>/login.php" style="font-size:13px;color:var(--text3);">
                    ← Back to Sign In
                </a>
            </div>

        </div>
    </div>
</div>

<style>
.pw-req {
    font-size: 12px;
    color: var(--text3);
    margin-bottom: 5px;
    transition: color 0.2s;
}
.pw-req.met { color: var(--green); }
.form-helper { font-size: 12px; color: var(--text3); margin-top: 6px; }
</style>

<script>
// ── OTP Inputs — auto-advance & paste support ──────────────
const boxes    = document.querySelectorAll('.otp-box');
const hidden   = document.getElementById('otpHidden');
const verifyBtn = document.getElementById('verifyBtn');

function getOtp() {
    return Array.from(boxes).map(b => b.value).join('');
}

function updateVerifyBtn() {
    const otp = getOtp();
    if (verifyBtn) {
        verifyBtn.disabled = otp.length < 6;
    }
    if (hidden) hidden.value = otp;
    boxes.forEach(b => b.classList.toggle('filled', b.value !== ''));
}

boxes.forEach((box, i) => {
    box.addEventListener('input', function() {
        // Keep only last digit
        if (this.value.length > 1) this.value = this.value.slice(-1);
        if (this.value && i < boxes.length - 1) boxes[i + 1].focus();
        updateVerifyBtn();
    });

    box.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value && i > 0) {
            boxes[i - 1].focus();
            boxes[i - 1].value = '';
            updateVerifyBtn();
        }
    });

    box.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((ch, idx) => { if (boxes[idx]) boxes[idx].value = ch; });
        const next = Math.min(pasted.length, boxes.length - 1);
        boxes[next].focus();
        updateVerifyBtn();
    });
});

// OTP form submit — add error shake
const otpForm = document.getElementById('otpForm');
if (otpForm) {
    otpForm.addEventListener('submit', function() {
        const otp = getOtp();
        if (otp.length < 6) {
            boxes.forEach(b => { b.classList.add('error'); setTimeout(() => b.classList.remove('error'), 500); });
            return false;
        }
        if (hidden) hidden.value = otp;
    });
}

// ── Countdown Timer (15 min) ───────────────────────────────
const timerEl = document.getElementById('timer');
if (timerEl) {
    let seconds = 15 * 60;
    const tick = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            clearInterval(tick);
            timerEl.textContent = 'Expired';
            timerEl.style.color = 'var(--red)';
            if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.textContent = 'OTP Expired'; }
            return;
        }
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;
        if (seconds < 120) timerEl.style.color = 'var(--red)';
    }, 1000);
}

// ── Password strength ──────────────────────────────────────
function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    const hint  = document.getElementById('strengthHint');

    const r1 = val.length >= 6;
    const r2 = /[0-9]/.test(val);
    const r3 = /[A-Z]/.test(val);
    const r4 = /[^A-Za-z0-9]/.test(val);

    // Update requirement indicators
    [
        [document.getElementById('req1'), r1],
        [document.getElementById('req2'), r2],
        [document.getElementById('req3'), r3],
        [document.getElementById('req4'), r4],
    ].forEach(([el, met]) => {
        if (!el) return;
        el.classList.toggle('met', met);
        el.innerHTML = (met ? '&#x25CF; ' : '&#x25CB; ') + el.textContent.replace(/^[●○] /, '');
    });

    const score = [r1, r2, r3, r4].filter(Boolean).length;
    const levels = [
        {w:'0%',   bg:'var(--bg4)',    lbl:'',        ht:''},
        {w:'25%',  bg:'var(--red)',    lbl:'Weak',     ht:'Add numbers or symbols'},
        {w:'50%',  bg:'var(--orange)', lbl:'Fair',     ht:'Add uppercase letters'},
        {w:'75%',  bg:'var(--yellow)', lbl:'Good',     ht:'Add a special character'},
        {w:'100%', bg:'var(--green)',  lbl:'Strong!',  ht:'Great password'},
    ];
    const lvl = levels[score];
    if (fill)  { fill.style.width = lvl.w; fill.style.background = lvl.bg; }
    if (label) { label.textContent = lvl.lbl; label.style.color = lvl.bg; }
    if (hint)  hint.textContent = lvl.ht;

    checkMatch();
}

// ── Password match check ───────────────────────────────────
function checkMatch() {
    const p1  = document.getElementById('newPw')?.value;
    const p2  = document.getElementById('confirmPw')?.value;
    const msg = document.getElementById('matchMsg');
    const btn = document.getElementById('resetBtn');
    if (!msg || !p2) return;

    if (!p2) { msg.textContent = ''; return; }
    if (p1 === p2) {
        msg.innerHTML = '<span style="color:var(--green)">✓ Passwords match</span>';
        if (btn) btn.disabled = false;
    } else {
        msg.innerHTML = '<span style="color:var(--red)">✗ Passwords do not match</span>';
        if (btn) btn.disabled = true;
    }
}

// ── Toggle password visibility ─────────────────────────────
function togglePw(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'text' ? 'password' : 'text';
    btn.style.color = input.type === 'text' ? 'var(--accent)' : '';
}

// ── Send button loading state ──────────────────────────────
const sendBtn = document.getElementById('sendBtn');
const emailForm = document.getElementById('emailForm');
if (emailForm && sendBtn) {
    emailForm.addEventListener('submit', function() {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;"></span> Sending...';
    });
}
</script>

</body>
</html>
