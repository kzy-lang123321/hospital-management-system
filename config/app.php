<?php
/**
 * Hospital Management System (HMS)
 * Application Core Configuration & Global Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Karachi');

// Determine Base URL dynamically
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = rtrim($scriptName, '/');
define('BASE_URL', $baseUrl);

define('APP_VERSION', '1.0.0');

// Dynamic Hospital Profile Helper
function hospital_profile(): array {
    static $profile = null;
    if ($profile === null) {
        try {
            require_once __DIR__ . '/database.php';
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM `hospital_profiles` ORDER BY id ASC LIMIT 1");
            $data = $stmt->fetch();
            if ($data) {
                $profile = $data;
            } else {
                $profile = [
                    'hospital_name' => 'Hayat Medicare Hospital',
                    'tagline' => 'Excellence in Patient Care & Clinical Service',
                    'emergency_hotline' => '091-1122334',
                    'phone' => '+92 91 5892001',
                    'email' => 'info@hayatmedicare.org',
                    'address' => 'Sector B-3, Phase 5, Hayatabad',
                    'city' => 'Peshawar',
                    'currency_symbol' => 'PKR ',
                    'default_tax_rate' => 0.00,
                ];
            }
        } catch (Exception $e) {
            $profile = [
                'hospital_name' => 'Hospital Management System',
                'tagline' => 'Healthcare Information System',
                'emergency_hotline' => '1122',
                'phone' => '',
                'email' => '',
                'address' => '',
                'city' => '',
                'currency_symbol' => 'PKR ',
                'default_tax_rate' => 0.00,
            ];
        }
    }
    return $profile;
}

$hp = hospital_profile();
define('APP_NAME', $hp['hospital_name']);
define('CURRENCY', $hp['currency_symbol'] ?? 'PKR ');

// Security & CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            die("<div style='font-family:sans-serif;padding:2rem;color:red;'><h2>Security Exception</h2><p>Invalid or expired CSRF token. Please refresh the page and try again.</p></div>");
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
