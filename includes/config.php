<?php
// ===========================================
// CoreInventory - Configuration
// ===========================================

// Database Configuration — Update these values
define('DB_HOST', 'localhost');
define('DB_NAME', 'coreinventory');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// App Config
define('APP_NAME', 'CoreInventory');
define('APP_VERSION', '1.0.0');

// Auto-detect BASE_URL — works with any folder name
(function() {
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Walk up from /includes/ to project root
    $scriptDir = str_replace('\\', '/', dirname(dirname(__FILE__)));
    $docRoot   = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $subfolder = str_replace($docRoot, '', $scriptDir);
    $subfolder = rtrim($subfolder, '/');
    define('BASE_URL', $protocol . '://' . $host . $subfolder);
})();

// Session
session_start();

// PDO Connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Auth Helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

// Flash Messages
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// Generate Reference Numbers
function generateRef(string $prefix): string {
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// Sanitize Input
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

// Format Number
function fmtNum($n, int $dec = 2): string {
    return number_format((float)$n, $dec);
}

// Status Badge Color
function statusColor(string $status): string {
    return match($status) {
        'done'     => 'badge-done',
        'ready'    => 'badge-ready',
        'waiting'  => 'badge-waiting',
        'draft'    => 'badge-draft',
        'canceled' => 'badge-canceled',
        default    => 'badge-draft',
    };
}