<?php
// ===========================================
// CoreInventory — Config + RBAC v3 (Final)
// ===========================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'coreinventory');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME',   'CoreInventory');
define('APP_VERSION','1.0.0');

// Auto-detect BASE_URL — works with any folder name
(function() {
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname(dirname(__FILE__)));
    $docRoot   = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $subfolder = rtrim(str_replace($docRoot, '', $scriptDir), '/');
    define('BASE_URL', $protocol . '://' . $host . $subfolder);
})();

session_start();

// ============================================================
// DATABASE
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;padding:20px;color:red;">DB Error: '.$e->getMessage().'</div>');
        }
    }
    return $pdo;
}

// ============================================================
// RBAC PERMISSION MAP
// ============================================================
/*
  Feature                   Admin   Manager   Staff
  ──────────────────────────────────────────────────
  view_dashboard              ✓       ✓         ✓
  view_products               ✓       ✓         ✓
  manage_products             ✓       ✓         ✗   (create/edit/delete)
  view_receipts               ✓       ✓         ✓
  manage_receipts             ✓       ✓         ✓   (create)
  validate_receipts           ✓       ✓         ✗   (validate/cancel)
  view_deliveries             ✓       ✓         ✓
  manage_deliveries           ✓       ✓         ✓   (create)
  validate_deliveries         ✓       ✓         ✗
  view_transfers              ✓       ✓         ✓
  manage_transfers            ✓       ✓         ✓   (create)
  validate_transfers          ✓       ✓         ✗
  view_adjustments            ✓       ✓         ✓
  manage_adjustments          ✓       ✗         ✗   (create/validate)
  view_ledger                 ✓       ✓         ✓
  view_warehouses             ✓       ✓         ✗
  manage_warehouses           ✓       ✗         ✗
  view_users                  ✓       ✓         ✗
  create_manager              ✓       ✗         ✗
  create_staff                ✓       ✓         ✗
  edit_user                   ✓       ✓*        ✗   (* only staff)
  delete_user                 ✓       ✗         ✗
  manage_categories           ✓       ✓         ✗
*/

define('PERMISSIONS', [
    'admin' => [
        'view_dashboard',
        'view_products',    'manage_products',
        'view_receipts',    'manage_receipts',    'validate_receipts',
        'view_deliveries',  'manage_deliveries',  'validate_deliveries',
        'view_transfers',   'manage_transfers',   'validate_transfers',
        'view_adjustments', 'manage_adjustments',
        'view_ledger',
        'view_warehouses',  'manage_warehouses',
        'view_users', 'create_manager', 'create_staff', 'edit_user', 'delete_user',
        'manage_categories',
    ],
    'manager' => [
        'view_dashboard',
        'view_products',    'manage_products',
        'view_receipts',    'manage_receipts',    'validate_receipts',
        'view_deliveries',  'manage_deliveries',  'validate_deliveries',
        'view_transfers',   'manage_transfers',   'validate_transfers',
        'view_adjustments',
        'view_ledger',
        'view_warehouses',
        'view_users', 'create_staff', 'edit_user',
        'manage_categories',
    ],
    'staff' => [
        'view_dashboard',
        'view_products',
        'view_receipts',   'manage_receipts',
        'view_deliveries', 'manage_deliveries',
        'view_transfers',  'manage_transfers',
        'view_adjustments',
        'view_ledger',
    ],
]);

// Check if current user has a permission
function can(string $permission): bool {
    $role  = $_SESSION['user']['role'] ?? '';
    $perms = PERMISSIONS[$role] ?? [];
    return in_array($permission, $perms);
}

// Deny with redirect if permission missing
function requirePermission(string $permission): void {
    requireLogin();
    if (!can($permission)) {
        setFlash('error', 'Access denied. You do not have permission for this action.');
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
        exit;
    }
}

// Deny silently (for POST action guards — returns false instead of redirecting)
function denyAction(string $permission, string $redirect): void {
    if (!can($permission)) {
        setFlash('error', 'Access denied.');
        header('Location: ' . BASE_URL . $redirect);
        exit;
    }
}

function userRole(): string   { return $_SESSION['user']['role'] ?? ''; }
function isAdmin(): bool      { return userRole() === 'admin'; }
function isManagerOrAbove(): bool { return in_array(userRole(), ['admin','manager']); }

// ============================================================
// AUTH
// ============================================================
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function currentUser(): array { return $_SESSION['user'] ?? []; }

// ============================================================
// FLASH
// ============================================================
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

// ============================================================
// HELPERS
// ============================================================
function generateRef(string $prefix): string {
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function fmtNum($n, int $dec = 2): string {
    return number_format((float)$n, $dec);
}

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

function roleBadge(string $role): string {
    return match($role) {
        'admin'   => 'badge-done',
        'manager' => 'badge-ready',
        'staff'   => 'badge-draft',
        default   => 'badge-draft',
    };
}

// Load shared UI helpers
require_once __DIR__ . '/functions.php';
