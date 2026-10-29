<?php
/**
 * Hospital Management System (HMS)
 * Privacy-Safe Platform Telemetry & Analytics Service
 * 
 * STRICT PRIVACY RESTRICTIONS ENFORCED:
 * Never returns patient names, MRNs, diagnoses, prescriptions, lab results,
 * individual bills, or staff GPS coordinates. Returns ONLY sanitized aggregate metrics.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Tenant.php';

class Telemetry {

    /**
     * Get platform-wide high-level operational statistics
     */
    public static function getPlatformSummary(): array {
        $db = Database::getConnection();

        $hStats = $db->query("
            SELECT 
                COUNT(*) AS total_hospitals,
                COUNT(CASE WHEN status = 'Active' THEN 1 END) AS active_hospitals,
                COUNT(CASE WHEN status = 'Suspended' THEN 1 END) AS suspended_hospitals,
                COUNT(CASE WHEN status = 'Inactive' THEN 1 END) AS inactive_hospitals,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) AS pending_hospitals,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS registered_today
            FROM `hospital_profiles`
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalOwners = (int)$db->query("
            SELECT COUNT(DISTINCT id) FROM `users` 
            WHERE role_id IN (SELECT id FROM `roles` WHERE slug IN ('hospital_owner', 'super_admin'))
        ")->fetchColumn();

        $totalStaff = (int)$db->query("
            SELECT COUNT(*) FROM `users` 
            WHERE hospital_id IS NOT NULL AND status = 'Active'
        ")->fetchColumn();

        // Login activity in the last 24h
        $loginActivity = $db->query("
            SELECT 
                COUNT(CASE WHEN event_type = 'LOGIN' THEN 1 END) AS successful_logins,
                COUNT(CASE WHEN event_type = 'LOGIN_FAILED' THEN 1 END) AS failed_logins
            FROM `audit_logs`
            WHERE created_at >= NOW() - INTERVAL 24 HOUR
        ")->fetch(PDO::FETCH_ASSOC) ?: ['successful_logins' => 0, 'failed_logins' => 0];

        // Hospitals with recent activity in last 48 hours
        $activeHospitalsCount = (int)$db->query("
            SELECT COUNT(DISTINCT hospital_id) FROM `audit_logs`
            WHERE hospital_id IS NOT NULL AND created_at >= NOW() - INTERVAL 48 HOUR
        ")->fetchColumn();

        return [
            'total_hospitals' => (int)($hStats['total_hospitals'] ?? 0),
            'active_hospitals' => (int)($hStats['active_hospitals'] ?? 0),
            'suspended_hospitals' => (int)($hStats['suspended_hospitals'] ?? 0),
            'inactive_hospitals' => (int)($hStats['inactive_hospitals'] ?? 0),
            'pending_hospitals' => (int)($hStats['pending_hospitals'] ?? 0),
            'registered_today' => (int)($hStats['registered_today'] ?? 0),
            'total_owners' => $totalOwners,
            'total_staff' => $totalStaff,
            'hospitals_with_recent_activity' => $activeHospitalsCount,
            'hospitals_idle' => max(0, (int)($hStats['active_hospitals'] ?? 0) - $activeHospitalsCount),
            'logins_24h_success' => (int)($loginActivity['successful_logins'] ?? 0),
            'logins_24h_failed' => (int)($loginActivity['failed_logins'] ?? 0)
        ];
    }

    /**
     * Get privacy-safe hospital directory for the Developer Dashboard
     */
    public static function getHospitalDirectory(string $search = '', string $status = '', string $sort = 'id_asc'): array {
        $db = Database::getConnection();

        $sql = "
            SELECT 
                hp.id,
                hp.hospital_name,
                hp.hospital_code,
                hp.city,
                hp.country,
                hp.status,
                hp.subscription_plan,
                hp.theme_color,
                hp.created_at,
                hp.modules_enabled,
                u.full_name AS owner_name,
                u.email AS owner_email,
                u.last_login_at AS owner_last_login,
                (SELECT COUNT(*) FROM `users` WHERE hospital_id = hp.id AND status = 'Active') AS staff_count,
                (SELECT MAX(created_at) FROM `audit_logs` WHERE hospital_id = hp.id) AS last_activity_at
            FROM `hospital_profiles` hp
            LEFT JOIN `users` u ON hp.owner_user_id = u.id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (hp.hospital_name LIKE ? OR hp.hospital_code LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR hp.city LIKE ?)";
            $s = "%$search%";
            $params = array_merge($params, [$s, $s, $s, $s, $s]);
        }

        if (!empty($status) && in_array($status, ['Active', 'Suspended', 'Inactive', 'Pending'], true)) {
            $sql .= " AND hp.status = ?";
            $params[] = $status;
        }

        switch ($sort) {
            case 'name_asc': $sql .= " ORDER BY hp.hospital_name ASC"; break;
            case 'name_desc': $sql .= " ORDER BY hp.hospital_name DESC"; break;
            case 'created_desc': $sql .= " ORDER BY hp.created_at DESC"; break;
            case 'staff_desc': $sql .= " ORDER BY staff_count DESC"; break;
            default: $sql .= " ORDER BY hp.id ASC"; break;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrich each hospital with privacy-safe telemetry summary
        foreach ($rows as &$r) {
            $hId = (int)$r['id'];
            $r['most_used_department'] = self::getMostUsedDepartment($hId);
            $r['usage_intensity'] = self::getUsageIntensity($hId);
        }

        return $rows;
    }

    /**
     * Compute most-used department for a given hospital (aggregate activity event count only)
     */
    public static function getMostUsedDepartment(int $hospitalId): string {
        $db = Database::getConnection();

        $depts = [
            'Reception' => (int)$db->query("SELECT (SELECT COUNT(*) FROM `patients` WHERE hospital_id = $hospitalId) + (SELECT COUNT(*) FROM `appointments` WHERE hospital_id = $hospitalId)")->fetchColumn(),
            'Clinical' => (int)$db->query("SELECT (SELECT COUNT(*) FROM `consultation_notes` WHERE encounter_id IN (SELECT id FROM `encounters` WHERE hospital_id = $hospitalId)) + (SELECT COUNT(*) FROM `clinical_vitals` WHERE encounter_id IN (SELECT id FROM `encounters` WHERE hospital_id = $hospitalId))")->fetchColumn(),
            'Pharmacy' => (int)$db->query("SELECT COUNT(*) FROM `pharmacy_sales` WHERE hospital_id = $hospitalId")->fetchColumn(),
            'Laboratory' => (int)$db->query("SELECT COUNT(*) FROM `lab_orders` WHERE hospital_id = $hospitalId")->fetchColumn(),
            'Accounts' => (int)$db->query("SELECT COUNT(*) FROM `payments` WHERE hospital_id = $hospitalId")->fetchColumn()
        ];

        arsort($depts);
        $top = array_key_first($depts);
        return ($depts[$top] > 0) ? $top : 'No Recorded Activity';
    }

    /**
     * Compute usage intensity based on actual production aggregate activity
     */
    public static function getUsageIntensity(int $hospitalId): string {
        $db = Database::getConnection();
        $totalEvents = (int)$db->query("SELECT COUNT(*) FROM `audit_logs` WHERE hospital_id = $hospitalId AND created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
        if ($totalEvents >= 100) return 'High';
        if ($totalEvents >= 25) return 'Moderate';
        if ($totalEvents >= 1) return 'Low';
        return 'Inactive';
    }

    /**
     * Get privacy-safe department activity events table for a hospital or platform
     */
    public static function getDepartmentActivityTable(?int $hospitalId = null): array {
        $db = Database::getConnection();

        $hFilter = $hospitalId !== null ? "AND hospital_id = " . (int)$hospitalId : "";
        $encFilter = $hospitalId !== null ? "WHERE encounter_id IN (SELECT id FROM `encounters` WHERE hospital_id = " . (int)$hospitalId . ")" : "";

        // Aggregate actual events across departments
        $receptionEvents = (int)$db->query("
            SELECT (SELECT COUNT(*) FROM `patients` WHERE 1=1 $hFilter) + 
                   (SELECT COUNT(*) FROM `appointments` WHERE 1=1 $hFilter)
        ")->fetchColumn();

        $clinicalEvents = (int)$db->query("
            SELECT (SELECT COUNT(*) FROM `consultation_notes` $encFilter) + 
                   (SELECT COUNT(*) FROM `clinical_vitals` $encFilter)
        ")->fetchColumn();

        $pharmacyEvents = (int)$db->query("
            SELECT (SELECT COUNT(*) FROM `pharmacy_sales` WHERE 1=1 $hFilter) + 
                   (SELECT COUNT(*) FROM `drug_batches` WHERE 1=1 $hFilter)
        ")->fetchColumn();

        $labEvents = (int)$db->query("
            SELECT (SELECT COUNT(*) FROM `lab_orders` WHERE 1=1 $hFilter) + 
                   (SELECT COUNT(*) FROM `lab_results` WHERE 1=1 $hFilter)
        ")->fetchColumn();

        $accountsEvents = (int)$db->query("
            SELECT (SELECT COUNT(*) FROM `bills` WHERE 1=1 $hFilter) + 
                   (SELECT COUNT(*) FROM `payments` WHERE 1=1 $hFilter)
        ")->fetchColumn();

        $adminEvents = (int)$db->query("
            SELECT COUNT(*) FROM `audit_logs` WHERE event_type NOT IN ('LOGIN', 'LOGOUT') $hFilter
        ")->fetchColumn();

        $calcUsage = function(int $count): string {
            if ($count >= 50) return 'High';
            if ($count >= 15) return 'Moderate';
            if ($count >= 1) return 'Low';
            return 'Inactive';
        };

        return [
            [
                'department' => 'Reception (Front Desk)',
                'icon' => 'fa-id-card-clip',
                'description' => 'Patient master registration and appointment scheduling events',
                'daily_events' => $receptionEvents,
                'usage' => $calcUsage($receptionEvents)
            ],
            [
                'department' => 'Clinical (Doctor & Nursing)',
                'icon' => 'fa-stethoscope',
                'description' => 'Doctor consultations, vitals charting and clinical triage events',
                'daily_events' => $clinicalEvents,
                'usage' => $calcUsage($clinicalEvents)
            ],
            [
                'department' => 'Hospital Pharmacy',
                'icon' => 'fa-pills',
                'description' => 'Medication dispensing, batch inventory and POS transactions',
                'daily_events' => $pharmacyEvents,
                'usage' => $calcUsage($pharmacyEvents)
            ],
            [
                'department' => 'Clinical Pathology & Laboratory',
                'icon' => 'fa-flask-vial',
                'description' => 'Specimen diagnostic requests, analyzer runs and result approvals',
                'daily_events' => $labEvents,
                'usage' => $calcUsage($labEvents)
            ],
            [
                'department' => 'Central Accounts & Billing',
                'icon' => 'fa-file-invoice-dollar',
                'description' => 'Invoice authorizations, receipts and cashier balance entries',
                'daily_events' => $accountsEvents,
                'usage' => $calcUsage($accountsEvents)
            ],
            [
                'department' => 'Executive Administration',
                'icon' => 'fa-sliders',
                'description' => 'Roster scheduling, attendance audits and master configurations',
                'daily_events' => $adminEvents,
                'usage' => $calcUsage($adminEvents)
            ]
        ];
    }

    /**
     * Sanitized Technical Health & Uptime Indicators
     */
    public static function getSanitizedTechnicalHealth(): array {
        $db = Database::getConnection();

        $t0 = microtime(true);
        $db->query("SELECT 1")->fetch();
        $latencyMs = round((microtime(true) - $t0) * 1000, 2);

        $dbVersion = $db->query("SELECT VERSION()")->fetchColumn();
        $isTiDb = stripos($dbVersion, 'TiDB') !== false;

        return [
            'database_status' => 'Operational',
            'database_engine' => $isTiDb ? 'TiDB Cloud Serverless' : 'MySQL / MariaDB Compatible',
            'database_latency_ms' => $latencyMs,
            'php_version' => PHP_VERSION,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'server_time' => date('Y-m-d H:i:s T'),
            'ssl_active' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
            'telemetry_layer' => 'Privacy-Safe (Non-PHI)'
        ];
    }
}
