<?php
/**
 * Hospital Management System (HMS)
 * Application Core Configuration & Global Helpers
 */

if (!ob_get_level()) {
    ob_start();
}

// Suppress notices/deprecations from polluting HTTP headers before session_start()
if (!headers_sent()) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
}

// Multi-Instance / Serverless-Safe Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_samesite', 'Lax');

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        if ($isHttps) {
            ini_set('session.cookie_secure', '1');
        }
    }

    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/../core/SessionHandler.php';
    try {
        $sessionPdo = Database::getConnection();
        DatabaseSessionHandler::register($sessionPdo);
    } catch (Exception $e) {
        // Fallback to default file session if DB fails
    }

    if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

date_default_timezone_set('Asia/Karachi');

// Determine Base URL dynamically
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = rtrim($scriptName, '/');
define('BASE_URL', $baseUrl);

define('APP_VERSION', '1.0.0');

// PHP 7.4 compatibility polyfills
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

// Dynamic Multi-Hospital Profile Helper
require_once __DIR__ . '/../core/Tenant.php';

function hospital_profile(?int $hospitalId = null): array {
    return Tenant::profile($hospitalId);
}

$hp = hospital_profile();
define('APP_NAME', $hp['hospital_name']);
define('CURRENCY', $hp['currency_symbol'] ?? 'PKR ');

// Security & CSRF (Double-Submit Cookie + Serverless Database-Session Sync)
if (empty($_SESSION['csrf_token'])) {
    if (!empty($_COOKIE['csrf_token']) && preg_match('/^[a-f0-9]{32,64}$/i', $_COOKIE['csrf_token'])) {
        $_SESSION['csrf_token'] = $_COOKIE['csrf_token'];
    } else {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Sync Cookie with Session
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if (!headers_sent() && (empty($_COOKIE['csrf_token']) || $_COOKIE['csrf_token'] !== $_SESSION['csrf_token'])) {
    setcookie('csrf_token', $_SESSION['csrf_token'], [
        'expires' => time() + 86400 * 7,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
}

function csrf_token(): string {
    return $_SESSION['csrf_token'] ?? $_COOKIE['csrf_token'] ?? '';
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $cookieToken = $_COOKIE['csrf_token'] ?? '';

        $isValid = false;
        if (!empty($token)) {
            if (!empty($sessionToken) && hash_equals($sessionToken, $token)) {
                $isValid = true;
            } elseif (!empty($cookieToken) && hash_equals($cookieToken, $token)) {
                $isValid = true;
                $_SESSION['csrf_token'] = $cookieToken;
            }
        }

        if (!$isValid) {
            set_flash('danger', 'Your session or security token expired. Please try again.');
            $redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php?page=login';
            header("Location: $redirectUrl");
            exit;
        }
    }
    return true;
}

// Flash Message Helpers
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Formatting Helpers
function format_currency($amount): string {
    return CURRENCY . number_format((float)$amount, 2);
}

function format_datetime($dt): string {
    if (!$dt) return '—';
    return date('d M Y, h:i A', strtotime($dt));
}

function format_date($dt): string {
    if (!$dt) return '—';
    return date('d M Y', strtotime($dt));
}

function clean_input($data): string {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a guaranteed unique Appointment Reference Number (e.g. APT-20260918-0005)
 */
function generate_appointment_number(PDO $db, string $dateStr = ''): string {
    $datePrefix = !empty($dateStr) ? date('Ymd', strtotime($dateStr)) : date('Ymd');
    $hProfile = Tenant::profile();
    $prefix = !empty($hProfile['hospital_code']) ? $hProfile['hospital_code'] . '-APT' : 'APT';
    
    $latestId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `appointments`")->fetchColumn();
    $seq = $latestId + 1;
    $aptNum = $prefix . '-' . $datePrefix . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    
    $check = $db->prepare("SELECT COUNT(*) FROM `appointments` WHERE `appointment_number` = ?");
    $check->execute([$aptNum]);
    while ((int)$check->fetchColumn() > 0) {
        $seq++;
        $aptNum = $prefix . '-' . $datePrefix . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $check->execute([$aptNum]);
    }
    return $aptNum;
}

/**
 * Generate a guaranteed unique Patient MRN according to active Hospital configuration (e.g. MRN-2026-0003 or SIH-2026-0001)
 */
function generate_patient_mrn(PDO $db): string {
    $hProfile = Tenant::profile();
    $prefix = !empty($hProfile['mrn_prefix']) ? $hProfile['mrn_prefix'] : 'MRN';
    $hId = Tenant::id();
    
    // Count patients in current hospital
    $stmt = $db->prepare("SELECT COALESCE(COUNT(*), 0) FROM `patients` WHERE hospital_id = ?");
    $stmt->execute([$hId]);
    $seq = (int)$stmt->fetchColumn() + 1;
    
    $mrn = $prefix . '-' . date('Y') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    
    $check = $db->prepare("SELECT COUNT(*) FROM `patients` WHERE `mrn` = ?");
    $check->execute([$mrn]);
    while ((int)$check->fetchColumn() > 0) {
        $seq++;
        $mrn = $prefix . '-' . date('Y') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $check->execute([$mrn]);
    }
    return $mrn;
}

/**
 * Generate a guaranteed unique Invoice Number according to active Hospital configuration
 */
function generate_invoice_number(PDO $db): string {
    $hProfile = Tenant::profile();
    $prefix = !empty($hProfile['invoice_prefix']) ? $hProfile['invoice_prefix'] : 'INV';
    $hId = Tenant::id();
    
    $stmt = $db->prepare("SELECT COALESCE(COUNT(*), 0) FROM `bills` WHERE hospital_id = ?");
    $stmt->execute([$hId]);
    $seq = (int)$stmt->fetchColumn() + 1;
    
    $invNum = $prefix . '-' . date('Ymd') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    
    $check = $db->prepare("SELECT COUNT(*) FROM `bills` WHERE `bill_number` = ?");
    $check->execute([$invNum]);
    while ((int)$check->fetchColumn() > 0) {
        $seq++;
        $invNum = $prefix . '-' . date('Ymd') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $check->execute([$invNum]);
    }
    return $invNum;
}

