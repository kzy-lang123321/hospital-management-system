<?php
/**
 * Hospital Management System (HMS)
 * Authentication & Authorization (RBAC) Service
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuditLogger.php';

class Auth {
    public static function attempt(string $username, string $password): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.slug AS role_slug, r.role_name, d.department_name 
            FROM `users` u
            JOIN `roles` r ON u.role_id = r.id
            LEFT JOIN `departments` d ON u.department_id = d.id
            WHERE (u.username = ? OR u.email = ?) AND u.status = 'Active'
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Setup Session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role_id' => $user['role_id'],
                'role_slug' => $user['role_slug'],
                'role_name' => $user['role_name'],
                'department_id' => $user['department_id'],
                'department_name' => $user['department_name'],
                'designation' => $user['designation'],
            ];

            // Update last login
            $updateStmt = $db->prepare("UPDATE `users` SET `last_login_at` = NOW() WHERE `id` = ?");
            $updateStmt->execute([$user['id']]);

            AuditLogger::log('LOGIN', 'users', $user['id'], null, ['username' => $user['username']]);
            return true;
        }

        return false;
    }

    public static function check(): bool {
        return isset($_SESSION['user']['id']);
    }

    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): ?string {
        return $_SESSION['user']['role_slug'] ?? null;
    }

    public static function hasRole(array $allowedRoles): bool {
        if (!self::check()) return false;
        $currentRole = self::role();
        if ($currentRole === 'super_admin') return true; // Super admin bypasses all role checks
        return in_array($currentRole, $allowedRoles, true);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header("Location: index.php?page=login");
            exit;
        }
    }

    public static function requireRole(array $allowedRoles): void {
        self::requireLogin();
        if (!self::hasRole($allowedRoles)) {
            http_response_code(403);
            die("
                <div style='font-family:sans-serif;max-width:600px;margin:4rem auto;padding:2rem;border:1px solid #e2e8f0;border-radius:8px;background:#fff;'>
                    <h2 style='color:#dc2626;'>403 - Access Prohibited</h2>
                    <p style='color:#475569;'>Your current role (<strong>" . htmlspecialchars(self::user()['role_name'] ?? '') . "</strong>) does not have authorization to access this clinical/financial module.</p>
                    <a href='index.php?page=dashboard' style='display:inline-block;margin-top:1rem;padding:0.5rem 1rem;background:#0d9488;color:#fff;text-decoration:none;border-radius:4px;'>Return to Dashboard</a>
                </div>
            ");
        }
    }

    public static function logout(): void {
        if (self::check()) {
            AuditLogger::log('LOGOUT', 'users', self::id(), null, ['username' => self::user()['username']]);
        }
        unset($_SESSION['user']);
        session_destroy();
    }
}
