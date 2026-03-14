<?php
// ============================================================
// CoreInventory — Email Configuration
// File: includes/mailer.php
// ============================================================
// Requires PHPMailer. Install via Composer:
//   composer require phpmailer/phpmailer
//
// OR manually download PHPMailer and place in:
//   vendor/phpmailer/phpmailer/src/
// ============================================================

// ── EMAIL SETTINGS — Update these ──────────────────────────

require_once __DIR__ . '/../vendor/autoload.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'kaloladarshil710@gmail.com');
define('MAIL_PASSWORD', 'vqzg xcjh ecpp jeno');
define('MAIL_FROM', 'kaloladarshil710@gmail.com');
define('MAIL_FROM_NAME', 'coreinventory');
define('MAIL_ENCRYPTION', 'tls');



// For other SMTP providers:
// Outlook/Office365: smtp.office365.com, port 587, tls
// Yahoo Mail:        smtp.mail.yahoo.com, port 587, tls
// Custom SMTP:       set your own host/port
// ────────────────────────────────────────────────────────────

/**
 * Send an email using PHPMailer
 * Returns true on success, error string on failure
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool|string
{
    // Try to find PHPMailer — supports both Composer and manual install
    $autoloads = [
        __DIR__ . '/../vendor/autoload.php',                          // Composer
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php', // Manual
    ];

    $composerLoaded = false;
    foreach ($autoloads as $path) {
        if (file_exists($path)) {
            require_once $path;
            $composerLoaded = true;
            break;
        }
    }

    // If manual install, also load SMTP and Exception classes
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $manual = [
            __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
            __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php',
            __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php',
        ];
        foreach ($manual as $f) {
            if (file_exists($f)) require_once $f;
        }
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // PHPMailer not installed — log and return error
        error_log('CoreInventory: PHPMailer not found. Install via composer require phpmailer/phpmailer');
        return 'PHPMailer is not installed. See includes/mailer.php for instructions.';
    }



    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('CoreInventory Mailer Error: ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}

/**
 * Build the OTP email HTML template
 */
function buildOtpEmailHtml(string $userName, string $otp, string $expiresIn = '15 minutes'): string
{
    $appName  = APP_NAME;
    $baseUrl  = BASE_URL;
    $year     = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reset Your Password — {$appName}</title>
</head>
<body style="margin:0;padding:0;background:#0d1117;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1117;padding:40px 20px;">
  <tr>
    <td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">

        <!-- HEADER -->
        <tr>
          <td align="center" style="padding-bottom:28px;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:linear-gradient(135deg,#4f8eff,#2563eb);border-radius:12px;padding:12px 14px;vertical-align:middle;">
                  <span style="color:#fff;font-size:18px;">&#9632;</span>
                </td>
                <td style="padding-left:12px;vertical-align:middle;">
                  <span style="font-size:22px;font-weight:800;color:#eef0f6;letter-spacing:-0.5px;">{$appName}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CARD -->
        <tr>
          <td style="background:#0e1219;border:1px solid rgba(255,255,255,0.07);border-radius:16px;overflow:hidden;">

            <!-- Top accent bar -->
            <tr>
              <td style="background:linear-gradient(90deg,#4f8eff,#22d3ee);height:3px;display:block;line-height:3px;">&nbsp;</td>
            </tr>

            <!-- Body -->
            <tr>
              <td style="padding:36px 40px;">

                <p style="margin:0 0 6px;font-size:13px;color:#4a5568;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Password Reset</p>
                <h1 style="margin:0 0 20px;font-size:26px;font-weight:800;color:#eef0f6;letter-spacing:-0.5px;">Verify your identity</h1>

                <p style="margin:0 0 24px;font-size:15px;color:#8892a4;line-height:1.7;">
                  Hi <strong style="color:#eef0f6;">{$userName}</strong>,<br>
                  We received a request to reset your password for your <strong style="color:#eef0f6;">{$appName}</strong> account.
                  Use the OTP code below to continue.
                </p>

                <!-- OTP Box -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                  <tr>
                    <td align="center">
                      <div style="background:#141a24;border:2px dashed rgba(79,142,255,0.4);border-radius:12px;padding:24px 32px;display:inline-block;text-align:center;">
                        <p style="margin:0 0 8px;font-size:11px;color:#4a5568;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;">Your One-Time Password</p>
                        <p style="margin:0;font-size:42px;font-weight:800;letter-spacing:14px;color:#4f8eff;font-family:'Courier New',monospace;">{$otp}</p>
                        <p style="margin:10px 0 0;font-size:12px;color:#4a5568;">Expires in <strong style="color:#f59e42;">{$expiresIn}</strong></p>
                      </div>
                    </td>
                  </tr>
                </table>

                <!-- Warning -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                  <tr>
                    <td style="background:rgba(245,158,66,0.08);border:1px solid rgba(245,158,66,0.2);border-radius:8px;padding:14px 18px;">
                      <p style="margin:0;font-size:13px;color:#f59e42;">
                        <strong>&#9888; Security Notice:</strong>
                        Never share this OTP with anyone. {$appName} staff will never ask for your OTP.
                        If you did not request this, please ignore this email — your account is safe.
                      </p>
                    </td>
                  </tr>
                </table>

                <p style="margin:0;font-size:13px;color:#4a5568;line-height:1.6;">
                  This OTP is valid for <strong style="color:#8892a4;">{$expiresIn}</strong> from the time it was sent.
                  After that, you will need to request a new one.
                </p>

              </td>
            </tr>

            <!-- Footer inside card -->
            <tr>
              <td style="background:#080b12;border-top:1px solid rgba(255,255,255,0.05);padding:18px 40px;">
                <p style="margin:0;font-size:12px;color:#2d3748;text-align:center;">
                  This is an automated message from <strong style="color:#4a5568;">{$appName}</strong>.
                  Please do not reply to this email.
                </p>
              </td>
            </tr>

          </td>
        </tr>

        <!-- Bottom footer -->
        <tr>
          <td align="center" style="padding-top:24px;">
            <p style="margin:0;font-size:11px;color:#2d3748;">
              &copy; {$year} {$appName} · Inventory Management System
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
HTML;
}
