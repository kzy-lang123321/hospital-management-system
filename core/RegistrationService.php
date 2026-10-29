<?php
/**
 * Hospital Management System (HMS)
 * Public Hospital Registration & Developer Review Service
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Tenant.php';
require_once __DIR__ . '/AuditLogger.php';

class RegistrationService {

    /**
     * Submit a public hospital registration request
     */
    public static function submitRequest(array $data): array {
        $db = Database::getConnection();

        // 1. Validation
        $applicantName = trim($data['applicant_name'] ?? '');
        $applicantEmail = trim($data['applicant_email'] ?? '');
        $applicantPhone = trim($data['applicant_phone'] ?? '');
        $applicantDesignation = trim($data['applicant_designation'] ?? 'Hospital Owner');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        $hospitalName = trim($data['hospital_name'] ?? '');
        $hospitalType = trim($data['hospital_type'] ?? 'General Hospital');
        $licenseNumber = trim($data['license_number'] ?? '');
        $address = trim($data['address'] ?? '');
        $city = trim($data['city'] ?? '');
        $district = trim($data['district'] ?? '');
        $country = trim($data['country'] ?? 'Pakistan');
        $contactPhone = trim($data['contact_phone'] ?? $applicantPhone);
        $officialEmail = trim($data['official_email'] ?? $applicantEmail);
        $bedCount = !empty($data['bed_count']) ? (int)$data['bed_count'] : null;
        $timeZone = trim($data['time_zone'] ?? 'Asia/Karachi');
        $currencySymbol = trim($data['currency_symbol'] ?? 'PKR ');
        $notes = trim($data['notes'] ?? '');

        if (empty($applicantName) || empty($applicantEmail) || empty($applicantPhone) || empty($password)) {
            return ['success' => false, 'message' => 'Please provide all mandatory applicant credentials.'];
        }

        if (!filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters in length.'];
        }

        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        if (empty($hospitalName) || empty($address) || empty($city)) {
            return ['success' => false, 'message' => 'Please provide complete hospital name, city, and address.'];
        }

        // 2. Duplicate Detection
        // Check active users
        $chkUser = $db->prepare("SELECT id FROM `users` WHERE email = ? LIMIT 1");
        $chkUser->execute([$applicantEmail]);
        if ($chkUser->fetch()) {
            return ['success' => false, 'message' => 'An active user account with this email address already exists. Please log in or use another email.'];
        }

        // Check active hospitals
        $chkHosp = $db->prepare("SELECT id FROM `hospital_profiles` WHERE hospital_name = ? LIMIT 1");
        $chkHosp->execute([$hospitalName]);
        if ($chkHosp->fetch()) {
            return ['success' => false, 'message' => "A registered hospital with the name '$hospitalName' already exists on this platform."];
        }

        // Check pending requests
        $chkPending = $db->prepare("SELECT id FROM `hospital_registration_requests` WHERE (applicant_email = ? OR hospital_name = ?) AND status NOT IN ('Rejected') LIMIT 1");
        $chkPending->execute([$applicantEmail, $hospitalName]);
        if ($chkPending->fetch()) {
            return ['success' => false, 'message' => 'A pending hospital registration request with this email or hospital name is already undergoing review.'];
        }

        // 3. Departments and Modules
        $departments = $data['departments'] ?? ['OPD', 'IPD', 'Emergency', 'Pharmacy', 'Laboratory', 'Billing'];
        $modules = $data['modules'] ?? ['patients' => true, 'appointments' => true, 'clinical' => true, 'pharmacy' => true, 'laboratory' => true, 'billing' => true, 'attendance' => true];

        $deptJson = json_encode($departments);
        $modJson = json_encode($modules);

        // 4. Generate Unique Tracking Code
        $trackingCode = 'REG-' . date('Ym') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        // 5. Hash Password Securely
        $pwdHash = password_hash($password, PASSWORD_BCRYPT);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Web Browser', 0, 250);

        try {
            $stmt = $db->prepare("
                INSERT INTO `hospital_registration_requests` (
                    `tracking_code`, `applicant_name`, `applicant_email`, `applicant_phone`, `applicant_designation`, `password_hash`,
                    `hospital_name`, `hospital_type`, `license_number`, `address`, `city`, `district`, `country`,
                    `contact_phone`, `official_email`, `bed_count`, `departments_json`, `modules_json`,
                    `time_zone`, `currency_symbol`, `notes`, `status`, `ip_address`, `user_agent`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, 'Pending Approval', ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $trackingCode, $applicantName, $applicantEmail, $applicantPhone, $applicantDesignation, $pwdHash,
                $hospitalName, $hospitalType, $licenseNumber, $address, $city, $district, $country,
                $contactPhone, $officialEmail, $bedCount, $deptJson, $modJson,
                $timeZone, $currencySymbol, $notes, $ip, $ua
            ]);

            $requestId = (int)$db->lastInsertId();

            // 6. In-App Notification for Platform Developer
            $notifTitle = "New Hospital Registration Request: $hospitalName";
            $notifMsg = "Applicant $applicantName ($applicantEmail, $applicantPhone) submitted registration for '$hospitalName' ($city, $country). Tracking ID: $trackingCode";
            $notifUrl = "index.php?page=developer_registrations&request_id=" . $requestId;

            $nStmt = $db->prepare("
                INSERT INTO `platform_notifications` (`recipient_role`, `title`, `message`, `action_url`, `reference_type`, `reference_id`, `created_at`)
                VALUES ('developer', ?, ?, ?, 'hospital_registration', ?, NOW())
            ");
            $nStmt->execute([$notifTitle, $notifMsg, $notifUrl, $requestId]);

            // Audit Log
            AuditLogger::log('REGISTRATION_SUBMITTED', 'hospital_registration_requests', $requestId, null, [
                'tracking_code' => $trackingCode,
                'hospital_name' => $hospitalName,
                'applicant_email' => $applicantEmail
            ]);

            return [
                'success' => true,
                'tracking_code' => $trackingCode,
                'request_id' => $requestId,
                'hospital_name' => $hospitalName,
                'applicant_name' => $applicantName,
                'applicant_email' => $applicantEmail,
                'message' => 'Your hospital registration request has been submitted successfully and is under review.'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error while submitting request: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieve a request securely by tracking code and matching applicant email
     */
    public static function getRequestByTracking(string $trackingCode, ?string $email = null): ?array {
        $db = Database::getConnection();
        $trackingCode = strtoupper(trim($trackingCode));

        if (!empty($email)) {
            $email = trim($email);
            $stmt = $db->prepare("SELECT * FROM `hospital_registration_requests` WHERE tracking_code = ? AND applicant_email = ? LIMIT 1");
            $stmt->execute([$trackingCode, $email]);
        } else {
            $stmt = $db->prepare("SELECT * FROM `hospital_registration_requests` WHERE tracking_code = ? LIMIT 1");
            $stmt->execute([$trackingCode]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Submit additional information from the applicant
     */
    public static function updateApplicantInfo(string $trackingCode, string $email, string $replyNotes): array {
        $db = Database::getConnection();
        $req = self::getRequestByTracking($trackingCode, $email);

        if (!$req) {
            return ['success' => false, 'message' => 'Registration request not found or email does not match.'];
        }

        if ($req['status'] !== 'Additional Information Required') {
            return ['success' => false, 'message' => 'This request is not currently awaiting additional information (Current status: ' . $req['status'] . ').'];
        }

        $upd = $db->prepare("
            UPDATE `hospital_registration_requests` SET
                `applicant_reply` = ?,
                `status` = 'Under Review',
                `updated_at` = NOW()
            WHERE id = ?
        ");
        $upd->execute([$replyNotes, $req['id']]);

        // Notify Developer
        $notifTitle = "Applicant Clarification: {$req['hospital_name']}";
        $notifMsg = "Applicant {$req['applicant_name']} provided requested information for {$req['tracking_code']}.";
        $notifUrl = "index.php?page=developer_registrations&request_id=" . $req['id'];

        $db->prepare("
            INSERT INTO `platform_notifications` (`recipient_role`, `title`, `message`, `action_url`, `reference_type`, `reference_id`, `created_at`)
            VALUES ('developer', ?, ?, ?, 'hospital_registration', ?, NOW())
        ")->execute([$notifTitle, $notifMsg, $notifUrl, $req['id']]);

        AuditLogger::log('REGISTRATION_INFO_UPDATED', 'hospital_registration_requests', $req['id'], null, [
            'tracking_code' => $trackingCode,
            'status' => 'Under Review'
        ]);

        return ['success' => true, 'message' => 'Your clarification has been submitted. The request is now Under Review.'];
    }

    /**
     * Get registration requests list for Developer Console
     */
    public static function getRequests(string $status = '', string $search = ''): array {
        $db = Database::getConnection();
        $sql = "SELECT * FROM `hospital_registration_requests` WHERE 1=1";
        $params = [];

        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        if (!empty($search)) {
            $sql .= " AND (hospital_name LIKE ? OR applicant_name LIKE ? OR applicant_email LIKE ? OR tracking_code LIKE ? OR city LIKE ?)";
            $term = "%$search%";
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }

        $sql .= " ORDER BY id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get counts by status
     */
    public static function getCountsByStatus(): array {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'Pending Approval' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'Under Review' THEN 1 END) as under_review,
                COUNT(CASE WHEN status = 'Additional Information Required' THEN 1 END) as need_info,
                COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'Rejected' THEN 1 END) as rejected
            FROM `hospital_registration_requests`
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0, 'pending' => 0, 'under_review' => 0, 'need_info' => 0, 'approved' => 0, 'rejected' => 0
        ];
    }

    /**
     * Approve registration request and provision hospital & owner account
     */
    public static function approveRequest(int $requestId, int $developerUserId): array {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM `hospital_registration_requests` WHERE id = ?");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            return ['success' => false, 'message' => 'Registration request not found.'];
        }

        if ($req['status'] === 'Approved') {
            return ['success' => false, 'message' => 'This request has already been approved.'];
        }

        // Begin transaction
        $db->beginTransaction();

        try {
            // 1. Generate hospital code
            $words = explode(' ', $req['hospital_name']);
            $code = '';
            foreach ($words as $w) {
                if (strlen($w) > 0) $code .= strtoupper($w[0]);
            }
            if (strlen($code) < 3) $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $req['hospital_name']), 0, 4));
            $hospitalCode = substr($code, 0, 6) . '-' . rand(10, 99);

            // 2. Create hospital profile
            $insHosp = $db->prepare("
                INSERT INTO `hospital_profiles` (
                    `hospital_name`, `hospital_code`, `tagline`, `theme_color`, `mrn_prefix`, `invoice_prefix`,
                    `currency_symbol`, `default_tax_rate`, `date_format`, `time_zone`, `phone`, `emergency_hotline`,
                    `email`, `city`, `country`, `license_number`, `address`, `status`, `modules_enabled`, `created_at`
                ) VALUES (
                    ?, ?, 'Excellence in Healthcare', '#0d9488', ?, 'INV',
                    ?, 0.00, 'd M, Y', ?, ?, ?,
                    ?, ?, ?, ?, ?, 'Active', ?, NOW()
                )
            ");

            $mrnPrefix = strtoupper(substr($hospitalCode, 0, 4));
            $insHosp->execute([
                $req['hospital_name'], $hospitalCode,
                $mrnPrefix, $req['currency_symbol'] ?? 'PKR ', $req['time_zone'] ?? 'Asia/Karachi',
                $req['contact_phone'] ?? $req['applicant_phone'], $req['contact_phone'] ?? '1122',
                $req['official_email'] ?? $req['applicant_email'], $req['city'], $req['country'],
                $req['license_number'] ?? '', $req['address'], $req['modules_json'] ?? '{}'
            ]);

            $newHospitalId = (int)$db->lastInsertId();

            // 3. Create Hospital Owner / Super Admin user
            // Username from applicant name or email prefix
            $rawUser = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $req['applicant_email'])[0]));
            if (empty($rawUser)) $rawUser = 'owner';
            $username = $rawUser;
            $chkU = $db->prepare("SELECT id FROM `users` WHERE username = ?");
            $chkU->execute([$username]);
            if ($chkU->fetch()) {
                $username = $rawUser . rand(100, 999);
            }

            // Assign Super Admin role (role_id = 1 or slug 'super_admin' / 'hospital_owner')
            $rStmt = $db->query("SELECT id FROM `roles` WHERE slug IN ('super_admin', 'hospital_owner') ORDER BY id ASC LIMIT 1");
            $ownerRoleId = (int)$rStmt->fetchColumn() ?: 1;

            $insUser = $db->prepare("
                INSERT INTO `users` (
                    `hospital_id`, `username`, `email`, `password_hash`, `full_name`,
                    `phone`, `role_id`, `department_id`, `designation`, `status`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, NULL, ?, 'Active', NOW()
                )
            ");

            $insUser->execute([
                $newHospitalId, $username, $req['applicant_email'], $req['password_hash'],
                $req['applicant_name'], $req['applicant_phone'], $ownerRoleId,
                $req['applicant_designation'] ?: 'Hospital Super Admin'
            ]);

            $newUserId = (int)$db->lastInsertId();

            // 4. Seed default essential departments for new hospital
            $starterDepts = [
                ['Executive Administration', 'ADMIN', 'Non-Clinical', 'Executive suite & management'],
                ['Outpatient Department (OPD)', 'OPD', 'Clinical', 'General and specialist medical consultations'],
                ['Emergency & Trauma (ER)', 'ER', 'Clinical', '24/7 Acute emergency medical care'],
                ['General Medicine', 'MED', 'Clinical', 'Inpatient internal medicine'],
                ['Hospital Pharmacy', 'PHARM', 'Support', 'Central formulary and retail dispensing'],
                ['Pathology & Diagnostics', 'LAB', 'Support', 'Laboratory specimen diagnostic testing'],
                ['Central Finance & Billing', 'FIN', 'Administrative', 'Central patient accounting and invoicing']
            ];

            $deptStmt = $db->prepare("
                INSERT INTO `departments` (`hospital_id`, `department_name`, `code`, `type`, `description`, `status`)
                VALUES (?, ?, ?, ?, ?, 'Active')
            ");
            foreach ($starterDepts as $d) {
                try {
                    $deptStmt->execute([$newHospitalId, $d[0], $d[1], $d[2], $d[3]]);
                } catch (Exception $e) {
                    // Ignore duplicate code error if any
                }
            }

            // 5. Update Registration Request Status
            $updReq = $db->prepare("
                UPDATE `hospital_registration_requests` SET
                    `status` = 'Approved',
                    `reviewed_by` = ?,
                    `reviewed_at` = NOW(),
                    `created_hospital_id` = ?,
                    `created_user_id` = ?,
                    `developer_notes` = 'Registration request approved. Hospital tenant deployed and initial owner account activated.'
                WHERE id = ?
            ");
            $updReq->execute([$developerUserId, $newHospitalId, $newUserId, $requestId]);

            // 6. Record Audit Log
            AuditLogger::log('REGISTRATION_APPROVED', 'hospital_registration_requests', $requestId, null, [
                'hospital_id' => $newHospitalId,
                'hospital_name' => $req['hospital_name'],
                'owner_user_id' => $newUserId,
                'owner_username' => $username,
                'developer_id' => $developerUserId
            ]);

            $db->commit();

            return [
                'success' => true,
                'hospital_id' => $newHospitalId,
                'user_id' => $newUserId,
                'username' => $username,
                'hospital_name' => $req['hospital_name'],
                'message' => "Hospital '{$req['hospital_name']}' has been approved and deployed. Initial Super Admin username: @$username"
            ];

        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to approve and create hospital tenant: ' . $e->getMessage()];
        }
    }

    /**
     * Reject registration request
     */
    public static function rejectRequest(int $requestId, string $reason, int $developerUserId): array {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM `hospital_registration_requests` WHERE id = ?");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            return ['success' => false, 'message' => 'Request not found.'];
        }

        if (empty($reason)) {
            return ['success' => false, 'message' => 'A valid justification reason is required to reject a registration request.'];
        }

        $upd = $db->prepare("
            UPDATE `hospital_registration_requests` SET
                `status` = 'Rejected',
                `developer_notes` = ?,
                `reviewed_by` = ?,
                `reviewed_at` = NOW()
            WHERE id = ?
        ");
        $upd->execute([$reason, $developerUserId, $requestId]);

        AuditLogger::log('REGISTRATION_REJECTED', 'hospital_registration_requests', $requestId, null, [
            'reason' => $reason,
            'developer_id' => $developerUserId
        ]);

        return ['success' => true, 'message' => "Registration request #{$req['tracking_code']} has been marked as Rejected."];
    }

    /**
     * Request more information from applicant
     */
    public static function requestMoreInfo(int $requestId, string $notes, int $developerUserId): array {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM `hospital_registration_requests` WHERE id = ?");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            return ['success' => false, 'message' => 'Request not found.'];
        }

        if (empty($notes)) {
            return ['success' => false, 'message' => 'Please specify what additional information or documents are required.'];
        }

        $upd = $db->prepare("
            UPDATE `hospital_registration_requests` SET
                `status` = 'Additional Information Required',
                `developer_notes` = ?,
                `reviewed_by` = ?,
                `reviewed_at` = NOW()
            WHERE id = ?
        ");
        $upd->execute([$notes, $developerUserId, $requestId]);

        AuditLogger::log('REGISTRATION_MORE_INFO', 'hospital_registration_requests', $requestId, null, [
            'notes' => $notes,
            'developer_id' => $developerUserId
        ]);

        return ['success' => true, 'message' => "Applicant has been flagged for Additional Information Required (#{$req['tracking_code']})."];
    }

    /**
     * Get unread developer notifications count
     */
    public static function getUnreadNotificationsCount(): int {
        $db = Database::getConnection();
        return (int)$db->query("SELECT COUNT(*) FROM `platform_notifications` WHERE recipient_role = 'developer' AND is_read = 0")->fetchColumn();
    }
}
