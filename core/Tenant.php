<?php
/**
 * Hospital Management System (HMS)
 * Multi-Hospital / Multi-Tenant Service & Context Engine
 */

require_once __DIR__ . '/../config/database.php';

class Tenant {
    private static ?array $activeProfile = null;
    private static ?int $activeId = null;

    public static function reset(): void {
        self::$activeId = null;
        self::$activeProfile = null;
    }

    /**
     * Get the active hospital/tenant ID for the current session.
     */
    public static function id(): int {
        // 1. If Platform Super Admin, check selected active hospital session switch
        if (isset($_SESSION['user']['role_slug']) && $_SESSION['user']['role_slug'] === 'platform_admin') {
            if (!empty($_SESSION['platform_active_hospital_id'])) {
                return (int)$_SESSION['platform_active_hospital_id'];
            }
        }

        // 2. If user is logged in and assigned to a specific hospital
        if (isset($_SESSION['user']['hospital_id']) && $_SESSION['user']['hospital_id'] !== null) {
            return (int)$_SESSION['user']['hospital_id'];
        }

        // 3. Fallback to Primary Default Hospital (ID = 1)
        return 1;
    }

    /**
     * Switch active hospital context (Authorized for Platform Super Admin only)
     */
    public static function switchContext(int $hospitalId): bool {
        if (!self::isPlatformAdmin()) {
            return false;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM `hospital_profiles` WHERE id = ? LIMIT 1");
        $stmt->execute([$hospitalId]);
        if ($stmt->fetch()) {
            $_SESSION['platform_active_hospital_id'] = $hospitalId;
            self::$activeId = $hospitalId;
            self::$activeProfile = null;
            return true;
        }
        return false;
    }

    /**
     * Clear context switch (return to Platform Overview)
     */
    public static function clearPlatformContext(): void {
        unset($_SESSION['platform_active_hospital_id']);
        self::$activeId = null;
        self::$activeProfile = null;
    }

    /**
     * Retrieve the full profile of the active or specified hospital
     */
    public static function profile(?int $hospitalId = null): array {
        $targetId = $hospitalId ?? self::id();

        if ($targetId === self::$activeId && self::$activeProfile !== null) {
            return self::$activeProfile;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM `hospital_profiles` WHERE id = ? LIMIT 1");
            $stmt->execute([$targetId]);
            $profile = $stmt->fetch();

            if ($profile) {
                // Ensure default theme and codes
                $profile['theme_color'] = $profile['theme_color'] ?? '#0d9488';
                $profile['currency_symbol'] = $profile['currency_symbol'] ?? 'PKR ';
                $profile['mrn_prefix'] = $profile['mrn_prefix'] ?? 'MRN';
                $profile['invoice_prefix'] = $profile['invoice_prefix'] ?? 'INV';
                $profile['date_format'] = $profile['date_format'] ?? 'd M, Y';

                if ($targetId === self::id()) {
                    self::$activeProfile = $profile;
                }
                return $profile;
            }
        } catch (Exception $e) {
            // Fallback default
        }

        return [
            'id' => 1,
            'hospital_name' => 'Hospital Management System',
            'tagline' => 'Clinical Excellence & Patient Care',
            'theme_color' => '#0d9488',
            'currency_symbol' => 'PKR ',
            'mrn_prefix' => 'MRN',
            'invoice_prefix' => 'INV',
            'date_format' => 'd M, Y',
            'emergency_hotline' => '1122',
            'phone' => '',
            'email' => '',
            'address' => '',
            'city' => '',
            'default_tax_rate' => 0.00,
            'time_zone' => 'Asia/Karachi'
        ];
    }

    /**
     * Check if current user is Platform Developer / Company Provider
     */
    public static function isDeveloper(): bool {
        return isset($_SESSION['user']['role_slug']) && in_array($_SESSION['user']['role_slug'], ['developer', 'platform_admin'], true);
    }

    public static function isPlatformAdmin(): bool {
        return self::isDeveloper();
    }

    /**
     * Check if current user is Hospital Owner / Super Admin
     */
    public static function isHospitalOwner(): bool {
        $role = $_SESSION['user']['role_slug'] ?? '';
        return in_array($role, ['hospital_owner', 'super_admin'], true);
    }

    /**
     * Check if active session is authorized to access a record belonging to a given hospital ID
     */
    public static function isAuthorizedFor(int $recordHospitalId): bool {
        // Platform Developer is restricted from accessing clinical/operational records
        if (self::isDeveloper()) {
            return false;
        }
        return self::id() === $recordHospitalId;
    }

    /**
     * Verify tenant security scope for record access (halts execution with 403 if unauthorized)
     */
    public static function enforceScope(int $recordHospitalId): void {
        if (!self::isAuthorizedFor($recordHospitalId)) {
            http_response_code(403);
            die("
                <div style='font-family:sans-serif;max-width:600px;margin:4rem auto;padding:2rem;border:1px solid #fecaca;border-radius:12px;background:#fff5f5;text-align:center;'>
                    <h2 style='color:#dc2626;margin-bottom:0.5rem;'>403 — Tenant Isolation Breach Blocked</h2>
                    <p style='color:#475569;font-size:14px;line-height:1.5;'>You are not authorized to view or mutate records belonging to an independent hospital facility.</p>
                    <a href='index.php?page=dashboard' style='display:inline-block;margin-top:1.5rem;padding:0.6rem 1.2rem;background:#0d9488;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;'>Return to Safe Dashboard</a>
                </div>
            ");
        }
    }

    /**
     * Get list of all hospitals (for Platform Admin)
     */
    public static function allHospitals(): array {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT hp.*, 
                   u.full_name AS owner_name, 
                   u.email AS owner_email,
                   (SELECT COUNT(*) FROM `users` WHERE hospital_id = hp.id AND status = 'Active') AS staff_count,
                   (SELECT COUNT(*) FROM `patients` WHERE hospital_id = hp.id) AS patient_count,
                   (SELECT COUNT(*) FROM `encounters` WHERE hospital_id = hp.id) AS encounter_count
            FROM `hospital_profiles` hp
            LEFT JOIN `users` u ON hp.owner_user_id = u.id
            ORDER BY hp.id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Register a new Hospital with Owner Account atomically
     */
    public static function registerHospital(array $hData, array $oData): int {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Insert Hospital Profile
            $hStmt = $db->prepare("
                INSERT INTO `hospital_profiles` (
                    `hospital_name`, `hospital_code`, `tagline`, `license_number`, `tax_number`,
                    `address`, `city`, `country`, `phone`, `email`, `emergency_hotline`,
                    `currency_symbol`, `default_tax_rate`, `time_zone`, `theme_color`,
                    `date_format`, `mrn_prefix`, `invoice_prefix`, `status`, `subscription_plan`,
                    `modules_enabled`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, NOW()
                )
            ");

            $mrnPrefix = strtoupper(trim($hData['mrn_prefix'] ?? substr(preg_replace('/[^a-zA-Z]/', '', $hData['hospital_name']), 0, 4)));
            if (empty($mrnPrefix)) $mrnPrefix = 'HOSP';

            $invPrefix = strtoupper(trim($hData['invoice_prefix'] ?? 'INV'));
            $themeColor = $hData['theme_color'] ?? '#0d9488';
            $currency = $hData['currency_symbol'] ?? 'PKR ';
            $modules = json_encode([
                'opd' => true,
                'ipd' => true,
                'emergency' => true,
                'ot' => true,
                'pharmacy' => true,
                'laboratory' => true,
                'radiology' => true,
                'blood_bank' => true,
                'billing' => true,
                'inventory' => true,
                'insurance' => true,
                'reports' => true
            ]);

            $hStmt->execute([
                $hData['hospital_name'],
                $hData['hospital_code'] ?? $mrnPrefix,
                $hData['tagline'] ?? 'Excellence in Healthcare & Patient Care',
                $hData['license_number'] ?? null,
                $hData['tax_number'] ?? null,
                $hData['address'] ?? 'Hospital Main Campus',
                $hData['city'] ?? 'City',
                $hData['country'] ?? 'Pakistan',
                $hData['phone'] ?? '',
                $hData['email'] ?? '',
                $hData['emergency_hotline'] ?? '1122',
                $currency,
                $hData['default_tax_rate'] ?? 0.00,
                $hData['time_zone'] ?? 'Asia/Karachi',
                $themeColor,
                $hData['date_format'] ?? 'd M, Y',
                $mrnPrefix,
                $invPrefix,
                'Active',
                $hData['subscription_plan'] ?? 'Enterprise',
                $modules
            ]);

            $newHospitalId = (int)$db->lastInsertId();

            // 2. Insert Hospital Owner User Account (Role 11: hospital_owner)
            $pwdHash = password_hash($oData['password'], PASSWORD_BCRYPT);
            $uStmt = $db->prepare("
                INSERT INTO `users` (
                    `hospital_id`, `username`, `email`, `password_hash`, `full_name`,
                    `phone`, `role_id`, `department_id`, `designation`, `status`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, 11, NULL, ?, 'Active', NOW()
                )
            ");
            $uStmt->execute([
                $newHospitalId,
                $oData['username'],
                $oData['email'],
                $pwdHash,
                $oData['full_name'],
                $oData['phone'] ?? null,
                $oData['designation'] ?? 'Hospital Owner & Super Administrator'
            ]);

            $newOwnerId = (int)$db->lastInsertId();

            // Link owner to hospital profile
            $updHosp = $db->prepare("UPDATE `hospital_profiles` SET `owner_user_id` = ? WHERE id = ?");
            $updHosp->execute([$newOwnerId, $newHospitalId]);

            // 3. Seed Starter Operational Departments for the New Hospital
            $starterDepts = [
                ['Executive Administration', 'ADMIN', 'Non-Clinical', 'Executive suite & management'],
                ['Outpatient Department (OPD)', 'OPD', 'Clinical', 'General and specialist medical consultations'],
                ['Emergency & Trauma (ER)', 'ER', 'Clinical', '24/7 Acute emergency medical care'],
                ['General Medicine', 'MED', 'Clinical', 'Inpatient internal medicine'],
                ['General Surgery & OT', 'SURG', 'Clinical', 'Surgical procedures and operation theatre'],
                ['Pediatrics & Child Health', 'PED', 'Clinical', 'Infant and child specialist care'],
                ['Cardiology', 'CARD', 'Clinical', 'Cardiovascular clinical services'],
                ['Hospital Pharmacy', 'PHARM', 'Support', 'Central formulary and retail dispensing'],
                ['Pathology & Diagnostics', 'LAB', 'Support', 'Laboratory specimen diagnostic testing'],
                ['Radiology & Medical Imaging', 'RAD', 'Support', 'Digital X-Ray, Ultrasound and PACS imaging'],
                ['Central Finance & Billing', 'FIN', 'Administrative', 'Central patient accounting and invoicing']
            ];

            $deptStmt = $db->prepare("
                INSERT INTO `departments` (`hospital_id`, `department_name`, `code`, `type`, `description`, `status`)
                VALUES (?, ?, ?, ?, ?, 'Active')
            ");
            foreach ($starterDepts as $d) {
                $deptStmt->execute([$newHospitalId, $d[0], $d[1], $d[2], $d[3]]);
            }

            // 4. Seed Standard Ward and Bed for IPD
            $wardStmt = $db->prepare("
                INSERT INTO `wards` (`hospital_id`, `ward_name`, `code`, `ward_type`, `floor_number`, `total_capacity`, `status`)
                VALUES (?, 'General Ward A', 'GW-A', 'General_Male', '1st Floor', 10, 'Active')
            ");
            $wardStmt->execute([$newHospitalId]);
            $wardId = (int)$db->lastInsertId();

            $roomStmt = $db->prepare("
                INSERT INTO `rooms` (`ward_id`, `room_number`, `room_type`, `daily_rate`, `hospital_id`)
                VALUES (?, '101', 'Standard', 1500.00, ?)
            ");
            $roomStmt->execute([$wardId, $newHospitalId]);
            $roomId = (int)$db->lastInsertId();

            $bedStmt = $db->prepare("
                INSERT INTO `beds` (`room_id`, `bed_number`, `bed_type`, `daily_tariff`, `status`, `hospital_id`)
                VALUES (?, 'B-101', 'Standard', 1500.00, 'Available', ?), (?, 'B-102', 'Standard', 1500.00, 'Available', ?)
            ");
            $bedStmt->execute([$roomId, $newHospitalId, $roomId, $newHospitalId]);

            // 5. Seed Core Consultation Tariffs
            $tariffStmt = $db->prepare("
                INSERT INTO `tariff_services` (`hospital_id`, `department_id`, `service_name`, `service_code`, `category`, `standard_price`, `is_active`)
                VALUES 
                    (?, NULL, 'General Specialist Consultation', 'CON-001', 'Consultation', 1500.00, 1),
                    (?, NULL, 'Emergency Triage & Consultation', 'CON-ER-001', 'Emergency', 2000.00, 1),
                    (?, NULL, 'Follow-up Consultation', 'CON-FOL-001', 'Consultation', 800.00, 1)
            ");
            $tariffStmt->execute([$newHospitalId, $newHospitalId, $newHospitalId]);

            $db->commit();
            return $newHospitalId;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
