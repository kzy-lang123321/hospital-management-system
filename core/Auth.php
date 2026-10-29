<?php
/**
 * Hospital Management System (HMS)
 * Authentication & Authorization (RBAC) Service - Multi-Hospital Aware
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Tenant.php';
require_once __DIR__ . '/AuditLogger.php';

class Auth {
    public static function attempt(string $username, string $password): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.slug AS role_slug, r.role_name, d.department_name,
                   hp.status AS hospital_status, hp.hospital_name, hp.hospital_code
            FROM `users` u
            JOIN `roles` r ON u.role_id = r.id
            LEFT JOIN `departments` d ON u.department_id = d.id
            LEFT JOIN `hospital_profiles` hp ON u.hospital_id = hp.id
            WHERE (u.username = ? OR u.email = ?) AND u.status = 'Active'
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check Hospital Suspension Status (if user belongs to a hospital)
            if (!empty($user['hospital_id']) && $user['hospital_status'] === 'Suspended') {
                set_flash('danger', 'Your hospital facility account is currently suspended. Please contact the Platform Administrator.');
                return false;
            }

            // Setup Session
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'hospital_id' => $user['hospital_id'] !== null ? (int)$user['hospital_id'] : null,
                'hospital_name' => $user['hospital_name'] ?? 'Platform Core',
                'hospital_code' => $user['hospital_code'] ?? 'SYSTEM',
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role_id' => (int)$user['role_id'],
                'role_slug' => $user['role_slug'],
                'role_name' => $user['role_name'],
                'department_id' => $user['department_id'] ? (int)$user['department_id'] : null,
                'department_name' => $user['department_name'],
                'designation' => $user['designation'],
            ];

            Tenant::reset();

            // If platform admin, reset any previous active hospital switch
            if ($user['role_slug'] === 'platform_admin') {
                unset($_SESSION['platform_active_hospital_id']);
            }

            // Update last login
            $updateStmt = $db->prepare("UPDATE `users` SET `last_login_at` = NOW() WHERE `id` = ?");
            $updateStmt->execute([$user['id']]);

            AuditLogger::log('LOGIN', 'users', $user['id'], null, [
                'username' => $user['username'],
                'hospital_id' => $user['hospital_id']
            ]);
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

    public static function hospitalId(): int {
        return Tenant::id();
    }

    public static function role(): ?string {
        return $_SESSION['user']['role_slug'] ?? null;
    }

    public static function isDeveloper(): bool {
        return in_array(self::role(), ['developer', 'platform_admin'], true);
    }

    public static function isPlatformAdmin(): bool {
        return self::isDeveloper();
    }

    public static function isHospitalOwner(): bool {
        $role = self::role();
        return in_array($role, ['hospital_owner', 'super_admin'], true);
    }

    public static function hasRole($allowedRoles): bool {
        if (!self::check()) return false;
        if (is_string($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        $currentRole = self::role();
        
        // Developer / Platform Admin: strict least-privilege platform access (cannot access clinical/financial records)
        if (in_array($currentRole, ['developer', 'platform_admin'], true)) {
            return in_array('developer', $allowedRoles, true) || in_array('platform_admin', $allowedRoles, true);
        }
        
        // Hospital Super Admin or Owner has full hospital module access (cannot access platform developer console)
        if (in_array($currentRole, ['super_admin', 'hospital_owner'], true)) {
            if (!in_array('developer', $allowedRoles, true) && !in_array('platform_admin', $allowedRoles, true)) {
                return true;
            }
        }

        return in_array($currentRole, $allowedRoles, true);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header("Location: index.php?page=login");
            exit;
        }
    }

    public static function requireRole($allowedRoles): void {
        self::requireLogin();
        if (is_string($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        if (!self::hasRole($allowedRoles)) {
            http_response_code(403);
            die("
                <div style='font-family:sans-serif;max-width:600px;margin:4rem auto;padding:2rem;border:1px solid #e2e8f0;border-radius:12px;background:#fff;'>
                    <h2 style='color:#dc2626;'>403 - Access Prohibited</h2>
                    <p style='color:#475569;'>Your current role (<strong>" . htmlspecialchars(self::user()['role_name'] ?? '') . "</strong>) does not have authorization to access this clinical/financial module.</p>
                    <a href='index.php?page=dashboard' style='display:inline-block;margin-top:1rem;padding:0.5rem 1rem;background:#0d9488;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;'>Return to Dashboard</a>
                </div>
            ");
        }
    }

    public static function logout(): void {
        if (self::check()) {
            AuditLogger::log('LOGOUT', 'users', self::id(), null, ['username' => self::user()['username']]);
        }
        unset($_SESSION['user']);
        unset($_SESSION['platform_active_hospital_id']);
        session_destroy();
    }
}
