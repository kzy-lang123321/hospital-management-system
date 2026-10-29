<?php
/**
 * Hospital Management System (HMS) - Root Front Controller & Router
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/AuditLogger.php';

$db = Database::getConnection();

$action = $_GET['action'] ?? null;
$page = $_GET['page'] ?? 'dashboard';

// ----------------------------------------------------------------------------
// ACTION DISPATCHER (POST / STATE MUTATION HANDLERS)
// ----------------------------------------------------------------------------

if ($action !== null) {

    // 1. User Login Action
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $username = clean_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::attempt($username, $password)) {
            set_flash('success', 'Authenticated successfully. Welcome to ' . APP_NAME);
            if (Auth::isDeveloper()) {
                header("Location: index.php?page=developer_dashboard");
            } elseif (Auth::isPlatformAdmin() && empty($_SESSION['platform_active_hospital_id'])) {
                header("Location: index.php?page=platform_dashboard");
            } else {
                header("Location: index.php?page=dashboard");
            }
            exit;
        } else {
            set_flash('danger', 'Invalid username or password. Please check your credentials.');
            header("Location: index.php?page=login");
            exit;
        }
    }

    // 2. User Logout Action
    if ($action === 'logout') {
        Auth::logout();
        set_flash('info', 'You have been securely signed out.');
        header("Location: index.php?page=login");
        exit;
    }

    // 2.0.1 Password Recovery Request (Public - No Login Required)
    if ($action === 'forgot_password_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $identity = clean_input($_POST['identity'] ?? '');
        if (!empty($identity)) {
            $stmt = $db->prepare("SELECT id, email, username, full_name FROM `users` WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$identity, $identity]);
            $u = $stmt->fetch();
            if ($u) {
                AuditLogger::log('PASSWORD_RESET_REQUEST', 'users', $u['id'], null, ['identity' => $identity]);
            }
        }
        set_flash('success', 'If an account matches your details, password recovery instructions or an administrator review ticket has been dispatched.');
        header("Location: index.php?page=forgot_password");
        exit;
    }

    // 2.0.2 Public Hospital Registration Submission (Public - No Login Required)
    if ($action === 'public_register_hospital' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        require_once __DIR__ . '/core/RegistrationService.php';

        $res = RegistrationService::submitRequest($_POST);
        if ($res['success']) {
            set_flash('success', "Your hospital registration request has been submitted! Tracking Code: {$res['tracking_code']}. Please save this tracking code.");
            header("Location: index.php?page=registration_status&code=" . urlencode($res['tracking_code']) . "&email=" . urlencode($res['applicant_email']));
            exit;
        } else {
            set_flash('danger', $res['message']);
            header("Location: index.php?page=register_hospital");
            exit;
        }
    }

    // 2.0.3 Applicant Additional Information Clarification (Public - No Login Required)
    if ($action === 'applicant_submit_clarification' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        require_once __DIR__ . '/core/RegistrationService.php';

        $code = clean_input($_POST['tracking_code'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $reply = clean_input($_POST['reply_notes'] ?? '');

        $res = RegistrationService::updateApplicantInfo($code, $email, $reply);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        header("Location: index.php?page=registration_status&code=" . urlencode($code) . "&email=" . urlencode($email));
        exit;
    }

    // ALL SUBSEQUENT ACTIONS REQUIRE AUTHENTICATION
    Auth::requireLogin();

    // ========================================================================
    // MULTI-TENANT & PLATFORM SUPER ADMIN ACTIONS
    // ========================================================================

    // 2.1 Switch Hospital Workspace (Platform Super Admin Only)
    if ($action === 'switch_hospital' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['platform_admin']);
        $hospId = (int)($_POST['hospital_id'] ?? 0);
        if ($hospId > 0 && Tenant::switchContext($hospId)) {
            $p = Tenant::profile($hospId);
            set_flash('info', 'Workspace context switched to: ' . $p['hospital_name']);
            header("Location: index.php?page=dashboard");
            exit;
        } else {
            set_flash('danger', 'Failed to switch context to specified hospital.');
            header("Location: index.php?page=platform_hospitals");
            exit;
        }
    }

    // 2.2 Clear Platform Context (Return to Platform Overview)
    if ($action === 'clear_platform_context' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['platform_admin']);
        Tenant::clearPlatformContext();
        set_flash('info', 'Returned to Platform Super Admin control scope.');
        header("Location: index.php?page=platform_dashboard");
        exit;
    }

    // 2.3 Create New Hospital & Owner Account (Platform Super Admin Only)
    if ($action === 'create_hospital' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['platform_admin', 'developer']);

        $hospitalName = clean_input($_POST['hospital_name'] ?? '');
        $hospitalCode = clean_input($_POST['hospital_code'] ?? '');
        $ownerUsername = clean_input($_POST['owner_username'] ?? '');
        $ownerEmail = clean_input($_POST['owner_email'] ?? '');
        $ownerPassword = $_POST['owner_password'] ?? '';
        $ownerName = clean_input($_POST['owner_name'] ?? '');
        $backPage = Auth::isDeveloper() ? 'developer_hospitals' : 'platform_hospitals';

        if (empty($hospitalName) || empty($ownerUsername) || empty($ownerEmail) || empty($ownerPassword)) {
            set_flash('danger', 'Please provide all mandatory hospital and owner credentials.');
            header("Location: index.php?page=" . $backPage);
            exit;
        }

        // Validate uniqueness of username and email
        $chk = $db->prepare("SELECT id FROM `users` WHERE username = ? OR email = ? LIMIT 1");
        $chk->execute([$ownerUsername, $ownerEmail]);
        if ($chk->fetch()) {
            set_flash('danger', 'A staff or owner account with this username or email already exists.');
            header("Location: index.php?page=" . $backPage);
            exit;
        }

        try {
            $hData = [
                'hospital_name' => $hospitalName,
                'hospital_code' => $hospitalCode,
                'tagline' => clean_input($_POST['tagline'] ?? ''),
                'city' => clean_input($_POST['city'] ?? 'City'),
                'address' => clean_input($_POST['address'] ?? ''),
                'emergency_hotline' => clean_input($_POST['emergency_hotline'] ?? '1122'),
                'mrn_prefix' => clean_input($_POST['mrn_prefix'] ?? 'HOSP'),
                'invoice_prefix' => clean_input($_POST['invoice_prefix'] ?? 'INV'),
                'currency_symbol' => clean_input($_POST['currency_symbol'] ?? 'PKR '),
                'theme_color' => clean_input($_POST['theme_color'] ?? '#0284c7'),
                'subscription_plan' => clean_input($_POST['subscription_plan'] ?? 'Enterprise'),
            ];

            $oData = [
                'username' => $ownerUsername,
                'email' => $ownerEmail,
                'password' => $ownerPassword,
                'full_name' => $ownerName,
                'phone' => clean_input($_POST['owner_phone'] ?? ''),
                'designation' => 'Hospital Owner & Super Administrator'
            ];

            $newHospId = Tenant::registerHospital($hData, $oData);
            AuditLogger::log('CREATE', 'hospital_profiles', $newHospId, null, [
                'hospital_name' => $hospitalName,
                'owner' => $ownerUsername
            ]);

            set_flash('success', "Hospital '$hospitalName' deployed successfully with owner account '$ownerUsername'.");
            header("Location: index.php?page=" . $backPage);
            exit;
        } catch (Exception $e) {
            set_flash('danger', 'Failed to register hospital facility: ' . $e->getMessage());
            header("Location: index.php?page=" . $backPage);
            exit;
        }
    }

    // 2.3.1 Toggle Hospital Active/Suspended Status (Platform Super Admin & Developer)
    if ($action === 'toggle_hospital_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['platform_admin', 'developer']);

        $targetId = (int)($_POST['hospital_id'] ?? 0);
        $backPage = Auth::isDeveloper() ? 'developer_hospitals' : 'platform_hospitals';

        if ($targetId === 1) {
            set_flash('warning', 'The primary default hospital cannot be suspended.');
            header("Location: index.php?page=" . $backPage);
            exit;
        }

        $stmt = $db->prepare("SELECT status, hospital_name FROM `hospital_profiles` WHERE id = ?");
        $stmt->execute([$targetId]);
        $row = $stmt->fetch();

        if ($row) {
            $newStatus = ($row['status'] === 'Active') ? 'Suspended' : 'Active';
            $upd = $db->prepare("UPDATE `hospital_profiles` SET status = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$newStatus, $targetId]);

            AuditLogger::log('UPDATE', 'hospital_profiles', $targetId, ['status' => $row['status']], ['status' => $newStatus]);
            set_flash('success', "Hospital '{$row['hospital_name']}' status changed to $newStatus.");
        }
        header("Location: index.php?page=" . $backPage);
        exit;
    }

    // 2.3.1.2 Developer Hospital Status Management with Audit Reason
    if ($action === 'developer_hospital_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['developer', 'platform_admin']);

        $targetId = (int)($_POST['hospital_id'] ?? 0);
        $newStatus = clean_input($_POST['status'] ?? 'Active');
        $reason = clean_input($_POST['reason'] ?? '');

        if (!in_array($newStatus, ['Active', 'Suspended', 'Inactive'], true)) {
            $newStatus = 'Active';
        }

        if ($targetId === 1 && $newStatus !== 'Active') {
            set_flash('warning', 'Default Primary Hospital cannot be suspended.');
            header("Location: index.php?page=developer_hospitals");
            exit;
        }

        $stmt = $db->prepare("SELECT status, hospital_name FROM `hospital_profiles` WHERE id = ?");
        $stmt->execute([$targetId]);
        $row = $stmt->fetch();

        if ($row) {
            $upd = $db->prepare("UPDATE `hospital_profiles` SET status = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$newStatus, $targetId]);

            AuditLogger::log('STATUS_CHANGE', 'hospital_profiles', $targetId, ['status' => $row['status']], ['status' => $newStatus, 'reason' => $reason]);
            set_flash('success', "Hospital '{$row['hospital_name']}' status updated to $newStatus.");
        }
        header("Location: index.php?page=developer_hospitals");
        exit;
    }

    // 2.3.1.2.1 Developer Approve Registration & Provision Hospital
    if ($action === 'developer_approve_registration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['developer', 'platform_admin']);
        require_once __DIR__ . '/core/RegistrationService.php';

        $reqId = (int)($_POST['request_id'] ?? 0);
        $res = RegistrationService::approveRequest($reqId, (int)Auth::id());
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        header("Location: index.php?page=developer_registrations&request_id=" . $reqId);
        exit;
    }

    // 2.3.1.2.2 Developer Reject Registration
    if ($action === 'developer_reject_registration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['developer', 'platform_admin']);
        require_once __DIR__ . '/core/RegistrationService.php';

        $reqId = (int)($_POST['request_id'] ?? 0);
        $reason = clean_input($_POST['reason'] ?? '');
        $res = RegistrationService::rejectRequest($reqId, $reason, (int)Auth::id());
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        header("Location: index.php?page=developer_registrations&request_id=" . $reqId);
        exit;
    }

    // 2.3.1.2.3 Developer Request More Information
    if ($action === 'developer_request_more_info' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['developer', 'platform_admin']);
        require_once __DIR__ . '/core/RegistrationService.php';

        $reqId = (int)($_POST['request_id'] ?? 0);
        $notes = clean_input($_POST['reason'] ?? '');
        $res = RegistrationService::requestMoreInfo($reqId, $notes, (int)Auth::id());
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        header("Location: index.php?page=developer_registrations&request_id=" . $reqId);
        exit;
    }

    // 2.3.1.3 Developer Profile Update
    if ($action === 'developer_update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['developer', 'platform_admin']);

        $userId = (int)Auth::id();
        $fullName = clean_input($_POST['full_name'] ?? '');
        $username = clean_input($_POST['username'] ?? '');
        $email = clean_input($_POST['email'] ?? '');

        if (empty($fullName) || empty($username) || empty($email)) {
            set_flash('danger', 'Please provide full name, username, and valid email.');
            header("Location: index.php?page=developer_profile");
            exit;
        }

        // Check for duplicates
        $chk = $db->prepare("SELECT id FROM `users` WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
        $chk->execute([$username, $email, $userId]);
        if ($chk->fetch()) {
            set_flash('danger', 'A user account with that username or email already exists.');
            header("Location: index.php?page=developer_profile");
            exit;
        }

        $upd = $db->prepare("UPDATE `users` SET full_name = ?, username = ?, email = ? WHERE id = ?");
        $upd->execute([$fullName, $username, $email, $userId]);
        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['email'] = $email;

        AuditLogger::log('PROFILE_UPDATE', 'users', $userId, null, ['username' => $username, 'email' => $email]);
        set_flash('success', 'Developer credentials and contact email updated successfully.');
        header("Location: index.php?page=developer_profile");
        exit;
    }

    // 2.3.1.4 Developer Password Change
    if ($action === 'developer_change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['developer', 'platform_admin']);

        $userId = (int)Auth::id();
        $currPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 8) {
            set_flash('danger', 'New password must be at least 8 characters in length.');
            header("Location: index.php?page=developer_profile");
            exit;
        }

        if ($newPass !== $confPass) {
            set_flash('danger', 'New password and confirmation do not match.');
            header("Location: index.php?page=developer_profile");
            exit;
        }

        $stmt = $db->prepare("SELECT password_hash FROM `users` WHERE id = ?");
        $stmt->execute([$userId]);
        $currentHash = $stmt->fetchColumn();

        if (!password_verify($currPass, $currentHash)) {
            set_flash('danger', 'Current master password is incorrect. Verification failed.');
            header("Location: index.php?page=developer_profile");
            exit;
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $upd = $db->prepare("UPDATE `users` SET password_hash = ? WHERE id = ?");
        $upd->execute([$newHash, $userId]);

        AuditLogger::log('PASSWORD_CHANGE', 'users', $userId, null, ['action' => 'developer_master_pwd_changed']);
        set_flash('success', 'Master developer password changed successfully.');
        header("Location: index.php?page=developer_profile");
        exit;
    }

    // 2.3.1.5 Location-Verified Staff Attendance Actions
    if ($action === 'attendance_checkin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        require_once __DIR__ . '/core/AttendanceService.php';

        $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $lon = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $acc = !empty($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;
        $notes = clean_input($_POST['notes'] ?? '');

        $res = AttendanceService::processCheckIn((int)Auth::id(), $lat, $lon, $acc, $notes);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        header("Location: index.php?page=attendance");
        exit;
    }

    if ($action === 'attendance_checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        require_once __DIR__ . '/core/AttendanceService.php';

        $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $lon = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $acc = !empty($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;

        $res = AttendanceService::processCheckOut((int)Auth::id(), $lat, $lon, $acc);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        header("Location: index.php?page=attendance");
        exit;
    }

    if ($action === 'attendance_exception_submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        require_once __DIR__ . '/core/AttendanceService.php';

        $attId = !empty($_POST['attendance_id']) ? (int)$_POST['attendance_id'] : null;
        $reqType = clean_input($_POST['request_type'] ?? 'Out_Of_Bounds');
        $reason = clean_input($_POST['reason'] ?? '');

        $res = AttendanceService::submitException((int)Auth::id(), $attId, $reqType, $reason);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        header("Location: index.php?page=attendance");
        exit;
    }

    if ($action === 'attendance_exception_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'superadmin', 'hospital_admin', 'admin', 'hr', 'hospital_owner', 'medical_director', 'platform_admin']);
        require_once __DIR__ . '/core/AttendanceService.php';

        $excId = (int)($_POST['exception_id'] ?? 0);
        $status = clean_input($_POST['status'] ?? '');
        $reviewNotes = clean_input($_POST['review_notes'] ?? '');

        $res = AttendanceService::reviewException($excId, $status, $reviewNotes, (int)Auth::id());
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        header("Location: index.php?page=attendance");
        exit;
    }

    if ($action === 'attendance_settings_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'superadmin', 'hospital_admin', 'admin', 'hospital_owner', 'platform_admin']);

        $hId = (int)Tenant::id();
        $lat = (float)($_POST['hospital_latitude'] ?? 34.01513700);
        $lon = (float)($_POST['hospital_longitude'] ?? 71.52491500);
        $radius = (int)($_POST['geofence_radius_meters'] ?? 300);
        $grace = (int)($_POST['attendance_grace_minutes'] ?? 15);

        $upd = $db->prepare("UPDATE `hospital_profiles` SET hospital_latitude = ?, hospital_longitude = ?, geofence_radius_meters = ?, attendance_grace_minutes = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$lat, $lon, $radius, $grace, $hId]);

        AuditLogger::log('GEOFENCE_UPDATE', 'hospital_profiles', $hId, null, ['lat' => $lat, 'lon' => $lon, 'radius' => $radius]);
        set_flash('success', 'Hospital geofence perimeter and attendance policy saved.');
        header("Location: index.php?page=attendance");
        exit;
    }

    // 2.3.2 Save Hospital Customization & Settings (Hospital Owner & Admins)
    if ($action === 'save_hospital_customization' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['hospital_owner', 'super_admin', 'platform_admin']);

        $targetId = (int)($_POST['hospital_id'] ?? Tenant::id());
        Tenant::enforceScope($targetId);

        $hospitalName = clean_input($_POST['hospital_name'] ?? '');
        $hospitalCode = clean_input($_POST['hospital_code'] ?? '');
        $tagline = clean_input($_POST['tagline'] ?? '');
        $themeColor = clean_input($_POST['theme_color'] ?? '#0d9488');
        $footerNotes = clean_input($_POST['footer_notes'] ?? '');
        $mrnPrefix = strtoupper(clean_input($_POST['mrn_prefix'] ?? 'MRN'));
        $invPrefix = strtoupper(clean_input($_POST['invoice_prefix'] ?? 'INV'));
        $currency = clean_input($_POST['currency_symbol'] ?? 'PKR ');
        $taxRate = (float)($_POST['default_tax_rate'] ?? 0);
        $dateFormat = clean_input($_POST['date_format'] ?? 'd M, Y');
        $timeZone = clean_input($_POST['time_zone'] ?? 'Asia/Karachi');
        $phone = clean_input($_POST['phone'] ?? '');
        $emergencyHotline = clean_input($_POST['emergency_hotline'] ?? '1122');
        $email = clean_input($_POST['email'] ?? '');
        $city = clean_input($_POST['city'] ?? '');
        $country = clean_input($_POST['country'] ?? 'Pakistan');
        $licenseNum = clean_input($_POST['license_number'] ?? '');
        $taxNum = clean_input($_POST['tax_number'] ?? '');
        $address = clean_input($_POST['address'] ?? '');

        // Module settings
        $enabledModules = [];
        if (!empty($_POST['modules']) && is_array($_POST['modules'])) {
            foreach ($_POST['modules'] as $modKey => $val) {
                $enabledModules[clean_input($modKey)] = true;
            }
        }
        $modulesJson = json_encode($enabledModules);

        try {
            $stmt = $db->prepare("
                UPDATE `hospital_profiles` SET
                    `hospital_name` = ?,
                    `hospital_code` = ?,
                    `tagline` = ?,
                    `theme_color` = ?,
                    `footer_notes` = ?,
                    `mrn_prefix` = ?,
                    `invoice_prefix` = ?,
                    `currency_symbol` = ?,
                    `default_tax_rate` = ?,
                    `date_format` = ?,
                    `time_zone` = ?,
                    `phone` = ?,
                    `emergency_hotline` = ?,
                    `email` = ?,
                    `city` = ?,
                    `country` = ?,
                    `license_number` = ?,
                    `tax_number` = ?,
                    `address` = ?,
                    `modules_enabled` = ?,
                    `updated_at` = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $hospitalName, $hospitalCode, $tagline, $themeColor, $footerNotes,
                $mrnPrefix, $invPrefix, $currency, $taxRate, $dateFormat, $timeZone,
                $phone, $emergencyHotline, $email, $city, $country,
                $licenseNum, $taxNum, $address, $modulesJson,
                $targetId
            ]);

            AuditLogger::log('UPDATE', 'hospital_profiles', $targetId, null, [
                'hospital_name' => $hospitalName,
                'theme_color' => $themeColor,
                'mrn_prefix' => $mrnPrefix
            ]);

            set_flash('success', 'Hospital customization preferences saved successfully.');
            header("Location: index.php?page=hospital_customization&hospital_id=" . $targetId);
            exit;
        } catch (Exception $e) {
            set_flash('danger', 'Failed to update hospital customization: ' . $e->getMessage());
            header("Location: index.php?page=hospital_customization&hospital_id=" . $targetId);
            exit;
        }
    }

    // 2.4 Download Official Credentials PDF Action
    if ($action === 'download_credentials_pdf') {
        Auth::requireRole(['super_admin', 'hospital_admin']);
        require_once __DIR__ . '/core/PdfCredentials.php';
        
        $pdfPath = __DIR__ . '/exports/Hospital_Management_Credentials.pdf';
        if (!file_exists($pdfPath)) {
            $credsFile = __DIR__ . '/scripts/management_credentials.json';
            $creds = file_exists($credsFile) ? json_decode(file_get_contents($credsFile), true) : [];
            PdfCredentials::generate($creds, $pdfPath);
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Hospital_Management_Credentials.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        readfile($pdfPath);
        exit;
    }

    // 2.5 AJAX: Duplicate Patient Detection Endpoint
    if ($action === 'check_patient_duplicate') {
        header('Content-Type: application/json');
        $phone = clean_input($_GET['phone'] ?? $_POST['phone'] ?? '');
        $cnic = clean_input($_GET['cnic'] ?? $_POST['cnic'] ?? '');

        if (empty($phone) && empty($cnic)) {
            echo json_encode(['exists' => false]);
            exit;
        }

        $conditions = [];
        $params = [Tenant::id()];
        if (!empty($phone)) {
            $conditions[] = "phone_primary = ?";
            $params[] = $phone;
        }
        if (!empty($cnic)) {
            $conditions[] = "national_id = ?";
            $params[] = $cnic;
        }

        $sql = "SELECT id, mrn, first_name, last_name, phone_primary, national_id, created_at FROM `patients` WHERE hospital_id = ? AND (" . implode(' OR ', $conditions) . ") LIMIT 1";
        $dupStmt = $db->prepare($sql);
        $dupStmt->execute($params);
        $found = $dupStmt->fetch();

        if ($found) {
            echo json_encode([
                'exists' => true,
                'patient' => [
                    'id' => $found['id'],
                    'mrn' => $found['mrn'],
                    'name' => $found['first_name'] . ' ' . $found['last_name'],
                    'phone' => $found['phone_primary'],
                    'national_id' => $found['national_id'],
                    'registered_at' => date('d M Y', strtotime($found['created_at']))
                ]
            ]);
        } else {
            echo json_encode(['exists' => false]);
        }
        exit;
    }

    // 3. Patient Registration Action
    if ($action === 'patient_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse', 'cashier']);

        $firstName = clean_input($_POST['first_name'] ?? '');
        $lastName = clean_input($_POST['last_name'] ?? '');
        $gender = clean_input($_POST['gender'] ?? 'Male');
        $dob = !empty($_POST['dob']) ? clean_input($_POST['dob']) : null;
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $bloodGroup = clean_input($_POST['blood_group'] ?? 'Unknown');
        $maritalStatus = clean_input($_POST['marital_status'] ?? 'Single');
        $nationalId = clean_input($_POST['national_id'] ?? '');
        $phonePrimary = clean_input($_POST['phone_primary'] ?? '');
        $phoneSecondary = clean_input($_POST['phone_secondary'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $city = clean_input($_POST['city'] ?? 'Peshawar');
        $address = clean_input($_POST['address'] ?? '');
        $emergencyName = clean_input($_POST['emergency_contact_name'] ?? '');
        $emergencyRelation = clean_input($_POST['emergency_contact_relation'] ?? '');
        $emergencyPhone = clean_input($_POST['emergency_contact_phone'] ?? '');

        // Generate next MRN atomically
        $mrn = generate_patient_mrn($db);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO `patients` 
                (`hospital_id`, `mrn`, `first_name`, `last_name`, `gender`, `dob`, `age`, `blood_group`, `marital_status`, `national_id`, `phone_primary`, `phone_secondary`, `email`, `address`, `city`, `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_phone`, `registered_by`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                Tenant::id(), $mrn, $firstName, $lastName, $gender, $dob, $age, $bloodGroup, $maritalStatus,
                $nationalId, $phonePrimary, $phoneSecondary, $email, $address, $city,
                $emergencyName, $emergencyRelation, $emergencyPhone, Auth::id()
            ]);
            $newPatientId = (int)$db->lastInsertId();

            // Record initial allergy if provided
            $allergenName = clean_input($_POST['allergen_name'] ?? '');
            if (!empty($allergenName)) {
                $allergyType = clean_input($_POST['allergy_type'] ?? 'Drug');
                $allergySeverity = clean_input($_POST['allergy_severity'] ?? 'Moderate');
                $algStmt = $db->prepare("
                    INSERT INTO `patient_allergies` (`hospital_id`, `patient_id`, `allergy_type`, `allergen_name`, `severity`, `recorded_by`)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $algStmt->execute([Tenant::id(), $newPatientId, $allergyType, $allergenName, $allergySeverity, Auth::id()]);
            }

            // Check if instant appointment booking was requested
            $bookNow = !empty($_POST['book_appointment_now']);
            $newAptId = 0;
            if ($bookNow) {
                $docId = (int)($_POST['instant_doctor_id'] ?? 0);
                $aptDate = clean_input($_POST['instant_appointment_date'] ?? date('Y-m-d'));
                $slotTime = clean_input($_POST['instant_slot_time'] ?? '10:00:00');
                $visitReason = clean_input($_POST['instant_reason'] ?? 'Initial Consultation');

                if ($docId > 0) {
                    $docDept = $db->prepare("SELECT department_id FROM `users` WHERE `id` = ?");
                    $docDept->execute([$docId]);
                    $deptId = (int)$docDept->fetchColumn();

                    $tokenStmt = $db->prepare("SELECT COALESCE(MAX(token_number), 0) FROM `appointments` WHERE `doctor_id` = ? AND `appointment_date` = ?");
                    $tokenStmt->execute([$docId, $aptDate]);
                    $tokenNum = (int)$tokenStmt->fetchColumn() + 1;

                    $aptNum = generate_appointment_number($db, $aptDate);
                    $insApt = $db->prepare("
                        INSERT INTO `appointments` 
                        (`hospital_id`, `appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `slot_time`, `token_number`, `type`, `status`, `reason_for_visit`, `created_by`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'New', 'Scheduled', ?, ?)
                    ");
                    $insApt->execute([Tenant::id(), $aptNum, $newPatientId, $docId, $deptId, $aptDate, $slotTime, $tokenNum, $visitReason, Auth::id()]);
                    $newAptId = (int)$db->lastInsertId();
                }
            }

            AuditLogger::log('CREATE', 'patients', $newPatientId, null, ['mrn' => $mrn, 'name' => "$firstName $lastName"]);
            $db->commit();

            set_flash('success', "Patient record registered successfully with unique MRN: $mrn" . ($newAptId > 0 ? " and booked for consultation." : ""));
            $redirectUrl = "index.php?page=patient_profile&id=" . $newPatientId . "&registered=1";
            if ($newAptId > 0) {
                $redirectUrl .= "&apt_id=" . $newAptId;
            }
            header("Location: " . $redirectUrl);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Failed to register patient: " . $e->getMessage());
            header("Location: index.php?page=patient_create");
            exit;
        }
    }

    // 4. Appointment Booking Action
    if ($action === 'appointment_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'receptionist', 'doctor']);

        $patientId = (int)($_POST['patient_id'] ?? 0);
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $appointmentDate = clean_input($_POST['appointment_date'] ?? date('Y-m-d'));
        $slotTime = clean_input($_POST['slot_time'] ?? '10:00:00');
        $type = clean_input($_POST['type'] ?? 'New');
        $reason = clean_input($_POST['reason_for_visit'] ?? '');

        // Fetch Doctor's Department
        $docDept = $db->prepare("SELECT department_id FROM `users` WHERE `id` = ?");
        $docDept->execute([$doctorId]);
        $deptId = (int)$docDept->fetchColumn();

        // Calculate token for that doctor on that date
        $tokenStmt = $db->prepare("SELECT COALESCE(MAX(token_number), 0) FROM `appointments` WHERE `doctor_id` = ? AND `appointment_date` = ?");
        $tokenStmt->execute([$doctorId, $appointmentDate]);
        $tokenNumber = (int)$tokenStmt->fetchColumn() + 1;

        $aptNum = generate_appointment_number($db, $appointmentDate);

        $stmt = $db->prepare("
            INSERT INTO `appointments` 
            (`hospital_id`, `appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `slot_time`, `token_number`, `type`, `status`, `reason_for_visit`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?)
        ");
        $stmt->execute([Tenant::id(), $aptNum, $patientId, $doctorId, $deptId, $appointmentDate, $slotTime, $tokenNumber, $type, $reason, Auth::id()]);

        $newAptId = (int)$db->lastInsertId();
        AuditLogger::log('CREATE', 'appointments', $newAptId, null, ['apt_number' => $aptNum]);
        set_flash('success', "Appointment booked successfully with Token #$tokenNumber ($aptNum).");
        header("Location: index.php?page=appointments&date=" . $appointmentDate . "&print_token=" . $newAptId);
        exit;
    }

    // 5. Appointment Check-in Action
    if ($action === 'appointment_checkin') {
        Auth::requireRole(['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse']);
        $aptId = (int)($_GET['id'] ?? 0);

        $aptStmt = $db->prepare("SELECT * FROM `appointments` WHERE `id` = ?");
        $aptStmt->execute([$aptId]);
        $apt = $aptStmt->fetch();

        if ($apt) {
            $db->beginTransaction();
            // Update appointment status
            $upApt = $db->prepare("UPDATE `appointments` SET `status` = 'Checked-In' WHERE `id` = ?");
            $upApt->execute([$aptId]);

            // Create active OPD Encounter
            $latestEnc = $db->query("SELECT COALESCE(MAX(id), 0) FROM `encounters`")->fetchColumn();
            $encNum = 'ENC-' . date('Y') . '-' . str_pad((string)($latestEnc + 1), 4, '0', STR_PAD_LEFT);

            $newEnc = $db->prepare("
                INSERT INTO `encounters` 
                (`encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `encounter_status`, `created_by`)
                VALUES (?, ?, 'OPD', ?, ?, NOW(), 'In-Progress', ?)
            ");
            $newEnc->execute([$encNum, $apt['patient_id'], $apt['department_id'], $apt['doctor_id'], Auth::id()]);
            $encId = (int)$db->lastInsertId();

            $db->commit();
            set_flash('success', "Patient checked in and queued for consultation (#$encNum).");
            header("Location: index.php?page=opd_consultation&encounter_id=" . $encId);
            exit;
        }
    }

    // 6. Save OPD Consultation (SOAP + Rx + Labs)
    if ($action === 'opd_consultation_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor']);

        $encounterId = (int)($_POST['encounter_id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);

        $chiefComplaint = clean_input($_POST['chief_complaint'] ?? '');
        $physicalExam = clean_input($_POST['physical_examination'] ?? '');
        $diagnosis = clean_input($_POST['provisional_diagnosis'] ?? '');
        $icdCode = clean_input($_POST['icd10_code'] ?? '');
        $clinicalAdvice = clean_input($_POST['clinical_advice'] ?? '');
        $disposition = clean_input($_POST['disposition'] ?? 'Discharged_Home');

        try {
            $db->beginTransaction();

            // 1. Save / Update Vitals
            $systolic = !empty($_POST['systolic_bp']) ? (int)$_POST['systolic_bp'] : null;
            $diastolic = !empty($_POST['diastolic_bp']) ? (int)$_POST['diastolic_bp'] : null;
            $pulse = !empty($_POST['pulse_rate_bpm']) ? (int)$_POST['pulse_rate_bpm'] : null;
            $temp = !empty($_POST['temperature_celsius']) ? (float)$_POST['temperature_celsius'] : null;
            $spo2 = !empty($_POST['spo2_percentage']) ? (int)$_POST['spo2_percentage'] : null;
            $glucose = !empty($_POST['blood_glucose_mg_dl']) ? (int)$_POST['blood_glucose_mg_dl'] : null;
            $weight = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;
            $pain = !empty($_POST['pain_scale']) ? (int)$_POST['pain_scale'] : 0;

            $vitStmt = $db->prepare("
                INSERT INTO `clinical_vitals` 
                (`encounter_id`, `patient_id`, `recorded_by`, `temperature_celsius`, `pulse_rate_bpm`, `systolic_bp`, `diastolic_bp`, `spo2_percentage`, `blood_glucose_mg_dl`, `weight_kg`, `pain_scale`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $vitStmt->execute([$encounterId, $patientId, Auth::id(), $temp, $pulse, $systolic, $diastolic, $spo2, $glucose, $weight, $pain]);

            // 2. Save / Update Consultation Notes
            $delNote = $db->prepare("DELETE FROM `consultation_notes` WHERE `encounter_id` = ?");
            $delNote->execute([$encounterId]);

            $followUpDate = !empty($_POST['follow_up_date']) ? clean_input($_POST['follow_up_date']) : null;
            $followUpNotes = clean_input($_POST['follow_up_notes'] ?? '');

            $noteStmt = $db->prepare("
                INSERT INTO `consultation_notes` 
                (`encounter_id`, `doctor_id`, `chief_complaint`, `physical_examination`, `provisional_diagnosis`, `icd10_code`, `clinical_advice`, `follow_up_date`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $noteStmt->execute([$encounterId, Auth::id(), $chiefComplaint, $physicalExam, $diagnosis, $icdCode, $clinicalAdvice, $followUpDate]);

            // Auto-schedule Follow-up Appointment if date provided
            if ($followUpDate) {
                $docDeptStmt = $db->prepare("SELECT department_id FROM `users` WHERE `id` = ?");
                $docDeptStmt->execute([Auth::id()]);
                $fDeptId = (int)$docDeptStmt->fetchColumn();

                $fTokenStmt = $db->prepare("SELECT COALESCE(MAX(token_number), 0) FROM `appointments` WHERE `doctor_id` = ? AND `appointment_date` = ?");
                $fTokenStmt->execute([Auth::id(), $followUpDate]);
                $fTokenNum = (int)$fTokenStmt->fetchColumn() + 1;

                $fAptNum = generate_appointment_number($db, $followUpDate);
                $fAptStmt = $db->prepare("
                    INSERT INTO `appointments` 
                    (`appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `slot_time`, `token_number`, `type`, `status`, `reason_for_visit`, `created_by`)
                    VALUES (?, ?, ?, ?, ?, '10:00:00', ?, 'Follow-up', 'Scheduled', ?, ?)
                ");
                $fAptStmt->execute([$fAptNum, $patientId, Auth::id(), $fDeptId, $followUpDate, $fTokenNum, !empty($followUpNotes) ? $followUpNotes : "Follow-up for $diagnosis", Auth::id()]);
            }

            // 3. Save Prescriptions if any drug chosen
            $rxDrugs = $_POST['rx_drug_id'] ?? [];
            $rxDoses = $_POST['rx_dose'] ?? [];
            $rxFreqs = $_POST['rx_frequency'] ?? [];
            $rxDays = $_POST['rx_days'] ?? [];
            $rxInstructions = $_POST['rx_instructions'] ?? [];

            $validRxItems = [];
            foreach ($rxDrugs as $idx => $drugId) {
                if (!empty($drugId)) {
                    $validRxItems[] = [
                        'drug_id' => (int)$drugId,
                        'dose' => clean_input($rxDoses[$idx] ?? '1 Tab'),
                        'frequency' => clean_input($rxFreqs[$idx] ?? '1-0-1'),
                        'days' => (int)($rxDays[$idx] ?? 5),
                        'instructions' => clean_input($rxInstructions[$idx] ?? 'After meals')
                    ];
                }
            }

            if (!empty($validRxItems)) {
                $latestRx = $db->query("SELECT COALESCE(MAX(id), 0) FROM `prescriptions`")->fetchColumn();
                $rxNumber = 'RX-' . date('Y') . '-' . str_pad((string)($latestRx + 1), 4, '0', STR_PAD_LEFT);

                $rxStmt = $db->prepare("
                    INSERT INTO `prescriptions` (`prescription_number`, `encounter_id`, `doctor_id`, `status`, `clinical_notes`)
                    VALUES (?, ?, ?, 'Pending', ?)
                ");
                $rxStmt->execute([$rxNumber, $encounterId, Auth::id(), $clinicalAdvice]);
                $rxId = (int)$db->lastInsertId();

                $rxItemStmt = $db->prepare("
                    INSERT INTO `prescription_items` 
                    (`prescription_id`, `drug_item_id`, `dosage`, `frequency`, `duration_days`, `instructions`, `quantity_prescribed`)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($validRxItems as $vi) {
                    $qtyPrescribed = max(1, $vi['days'] * 2);
                    $rxItemStmt->execute([$rxId, $vi['drug_id'], $vi['dose'], $vi['frequency'], $vi['days'], $vi['instructions'], $qtyPrescribed]);
                }
            }

            // 4. Save Lab Orders if selected
            $labTestsChosen = $_POST['lab_test_ids'] ?? [];
            $totalLabCost = 0.0;
            if (!empty($labTestsChosen)) {
                $latestLab = $db->query("SELECT COALESCE(MAX(id), 0) FROM `lab_orders`")->fetchColumn();
                $labOrderNum = 'LAB-' . date('Y') . '-' . str_pad((string)($latestLab + 1), 4, '0', STR_PAD_LEFT);

                $labStmt = $db->prepare("
                    INSERT INTO `lab_orders` (`order_number`, `encounter_id`, `ordered_by`, `order_datetime`, `status`)
                    VALUES (?, ?, ?, NOW(), 'Ordered')
                ");
                $labStmt->execute([$labOrderNum, $encounterId, Auth::id()]);
                $labOrderId = (int)$db->lastInsertId();

                $itemStmt = $db->prepare("INSERT INTO `lab_order_items` (`lab_order_id`, `lab_test_id`, `item_status`) VALUES (?, ?, 'Pending')");
                foreach ($labTestsChosen as $ltId) {
                    $itemStmt->execute([$labOrderId, (int)$ltId]);
                }
            }

            // 5. Update Encounter Status & Diagnosis
            $encStatus = ($disposition === 'In-Progress') ? 'In-Progress' : 'Completed';
            $encUpdate = $db->prepare("
                UPDATE `encounters` 
                SET `primary_diagnosis` = ?, `encounter_status` = ?, `discharge_disposition` = ?, `discharge_datetime` = IF(? = 'Completed', NOW(), NULL)
                WHERE `id` = ?
            ");
            $encUpdate->execute([$diagnosis, $encStatus, $disposition, $encStatus, $encounterId]);

            // 6. Create / Update Bill with Consultation & Lab charges
            $billCheck = $db->prepare("SELECT id FROM `bills` WHERE `encounter_id` = ?");
            $billCheck->execute([$encounterId]);
            $existingBill = $billCheck->fetch();

            $targetBillId = 0;
            if (!$existingBill) {
                $docFeeStmt = $db->prepare("SELECT consultation_fee FROM `users` WHERE `id` = ?");
                $docFeeStmt->execute([Auth::id()]);
                $docFee = (float)$docFeeStmt->fetchColumn();

                $latestBill = $db->query("SELECT COALESCE(MAX(id), 0) FROM `bills`")->fetchColumn();
                $billNum = 'INV-' . date('Y') . '-' . str_pad((string)($latestBill + 1), 4, '0', STR_PAD_LEFT);

                $billInsert = $db->prepare("
                    INSERT INTO `bills` 
                    (`bill_number`, `encounter_id`, `patient_id`, `bill_type`, `bill_date`, `gross_amount`, `net_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_by`)
                    VALUES (?, ?, ?, 'OPD', NOW(), ?, ?, 0.00, ?, 'Unpaid', ?)
                ");
                $billInsert->execute([$billNum, $encounterId, $patientId, $docFee, $docFee, $docFee, Auth::id()]);
                $targetBillId = (int)$db->lastInsertId();

                $billItemInsert = $db->prepare("
                    INSERT INTO `bill_items` (`bill_id`, `item_type`, `description`, `quantity`, `unit_price`, `subtotal`, `net_price`)
                    VALUES (?, 'Consultation', 'Specialist Doctor Consultation Fee', 1, ?, ?, ?)
                ");
                $billItemInsert->execute([$targetBillId, $docFee, $docFee, $docFee]);
            } else {
                $targetBillId = (int)$existingBill['id'];
            }

            // Append lab items to bill if any were ordered
            if ($targetBillId > 0 && !empty($labTestsChosen)) {
                $ltPriceStmt = $db->prepare("SELECT test_name, standard_price FROM `lab_tests` WHERE `id` = ?");
                $bItemStmt = $db->prepare("
                    INSERT INTO `bill_items` (`bill_id`, `item_type`, `reference_id`, `description`, `quantity`, `unit_price`, `subtotal`, `net_price`)
                    VALUES (?, 'Investigation', ?, ?, 1, ?, ?, ?)
                ");
                foreach ($labTestsChosen as $ltId) {
                    $ltPriceStmt->execute([(int)$ltId]);
                    $ltData = $ltPriceStmt->fetch();
                    if ($ltData) {
                        $price = (float)$ltData['standard_price'];
                        $totalLabCost += $price;
                        $bItemStmt->execute([$targetBillId, (int)$ltId, 'Lab Test: ' . $ltData['test_name'], $price, $price, $price]);
                    }
                }
                if ($totalLabCost > 0) {
                    $upBill = $db->prepare("
                        UPDATE `bills` 
                        SET `gross_amount` = `gross_amount` + ?, `net_amount` = `net_amount` + ?, `balance_amount` = `balance_amount` + ? 
                        WHERE `id` = ?
                    ");
                    $upBill->execute([$totalLabCost, $totalLabCost, $totalLabCost, $targetBillId]);
                }
            }

            AuditLogger::log('UPDATE', 'encounters', $encounterId, null, ['diagnosis' => $diagnosis, 'status' => $encStatus]);
            $db->commit();

            set_flash('success', "Consultation completed and clinical orders executed successfully.");
            header("Location: index.php?page=opd_consultation&encounter_id=" . $encounterId . "&print_rx=1");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Failed to save consultation: " . $e->getMessage());
            header("Location: index.php?page=opd_consultation&encounter_id=" . $encounterId);
            exit;
        }
    }

    // 7. IPD Inpatient Admission Action
    if ($action === 'ipd_admit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'nurse', 'doctor', 'receptionist']);

        $patientId = (int)($_POST['patient_id'] ?? 0);
        $bedId = (int)($_POST['bed_id'] ?? 0);
        $admittingDoctorId = (int)($_POST['admitting_doctor_id'] ?? 0);
        $admissionReason = clean_input($_POST['admission_reason'] ?? '');

        try {
            $db->beginTransaction();

            // Create IPD Encounter
            $latestEnc = $db->query("SELECT COALESCE(MAX(id), 0) FROM `encounters`")->fetchColumn();
            $encNum = 'ENC-' . date('Y') . '-' . str_pad((string)($latestEnc + 1), 4, '0', STR_PAD_LEFT);

            $encStmt = $db->prepare("
                INSERT INTO `encounters` 
                (`encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `encounter_status`, `primary_diagnosis`, `created_by`)
                VALUES (?, ?, 'IPD', 10, ?, NOW(), 'Admitted', ?, ?)
            ");
            $encStmt->execute([$encNum, $patientId, $admittingDoctorId, $admissionReason, Auth::id()]);
            $encId = (int)$db->lastInsertId();

            // Create Admission Record
            $admStmt = $db->prepare("
                INSERT INTO `admissions` 
                (`encounter_id`, `patient_id`, `admitting_doctor_id`, `admission_reason`, `initial_bed_id`, `current_bed_id`, `admission_datetime`, `status`)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 'Admitted')
            ");
            $admStmt->execute([$encId, $patientId, $admittingDoctorId, $admissionReason, $bedId, $bedId]);
            $admId = (int)$db->lastInsertId();

            // Record Bed Allocation
            $allocStmt = $db->prepare("
                INSERT INTO `bed_allocations` (`admission_id`, `bed_id`, `allocated_at`, `allocated_by`)
                VALUES (?, ?, NOW(), ?)
            ");
            $allocStmt->execute([$admId, $bedId, Auth::id()]);

            // Update Bed status to Occupied
            $bedUp = $db->prepare("UPDATE `beds` SET `status` = 'Occupied', `current_encounter_id` = ? WHERE `id` = ?");
            $bedUp->execute([$encId, $bedId]);

            AuditLogger::log('CREATE', 'admissions', $admId, null, ['patient_id' => $patientId, 'bed_id' => $bedId]);
            $db->commit();

            set_flash('success', "Inpatient admitted successfully to bed.");
            header("Location: index.php?page=ipd_beds");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Admission failed: " . $e->getMessage());
            header("Location: index.php?page=ipd_beds");
            exit;
        }
    }

    // 8. IPD Discharge Action
    if ($action === 'ipd_discharge') {
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse']);
        $bedId = (int)($_GET['bed_id'] ?? 0);

        try {
            $db->beginTransaction();

            $bedStmt = $db->prepare("SELECT current_encounter_id FROM `beds` WHERE `id` = ?");
            $bedStmt->execute([$bedId]);
            $encId = (int)$bedStmt->fetchColumn();

            if ($encId > 0) {
                // Update Encounter
                $encUp = $db->prepare("UPDATE `encounters` SET `encounter_status` = 'Completed', `discharge_datetime` = NOW() WHERE `id` = ?");
                $encUp->execute([$encId]);

                // Update Admission
                $admUp = $db->prepare("UPDATE `admissions` SET `status` = 'Discharged' WHERE `encounter_id` = ?");
                $admUp->execute([$encId]);
            }

            // Mark bed for cleaning
            $bedClean = $db->prepare("UPDATE `beds` SET `status` = 'Cleaning', `current_encounter_id` = NULL WHERE `id` = ?");
            $bedClean->execute([$bedId]);

            AuditLogger::log('UPDATE', 'beds', $bedId, null, ['status' => 'Cleaning']);
            $db->commit();

            set_flash('success', "Patient discharged. Bed marked for housekeeping / cleaning turnaround.");
            header("Location: index.php?page=ipd_beds");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Discharge failed: " . $e->getMessage());
            header("Location: index.php?page=ipd_beds");
            exit;
        }
    }

    // 9. Mark Bed Available
    if ($action === 'bed_mark_available') {
        Auth::requireRole(['super_admin', 'hospital_admin', 'nurse']);
        $bedId = (int)($_GET['id'] ?? 0);
        $up = $db->prepare("UPDATE `beds` SET `status` = 'Available' WHERE `id` = ?");
        $up->execute([$bedId]);

        AuditLogger::log('UPDATE', 'beds', $bedId, null, ['status' => 'Available']);
        set_flash('success', "Bed sanitized and marked Available for new patients.");
        header("Location: index.php?page=ipd_beds");
        exit;
    }

    // 10. Pharmacy Dispense Action
    if ($action === 'pharmacy_dispense' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

        $rxId = (int)($_POST['prescription_id'] ?? 0);

        try {
            $db->beginTransaction();

            $itemsStmt = $db->prepare("SELECT * FROM `prescription_items` WHERE `prescription_id` = ?");
            // Fetch encounter and patient for this prescription
            $encRxStmt = $db->prepare("SELECT p.encounter_id, e.patient_id FROM `prescriptions` p JOIN `encounters` e ON p.encounter_id = e.id WHERE p.id = ?");
            $encRxStmt->execute([$rxId]);
            $rxEncData = $encRxStmt->fetch();

            $totalPharmacyCost = 0.0;
            $dispensedBatchItems = [];

            foreach ($items as $item) {
                // Find batch with earliest expiry that has stock (FEFO)
                $batchStmt = $db->prepare("
                    SELECT id, batch_number, current_quantity, unit_sale_price 
                    FROM `drug_batches` 
                    WHERE `drug_item_id` = ? AND `current_quantity` > 0 
                    ORDER BY `expiry_date` ASC 
                    LIMIT 1
                ");
                $batchStmt->execute([$item['drug_item_id']]);
                $batch = $batchStmt->fetch();

                if ($batch) {
                    $dispenseQty = min($item['quantity_prescribed'], $batch['current_quantity']);
                    $newQty = $batch['current_quantity'] - $dispenseQty;

                    // Deduct batch quantity
                    $deductStmt = $db->prepare("UPDATE `drug_batches` SET `current_quantity` = ? WHERE `id` = ?");
                    $deductStmt->execute([$newQty, $batch['id']]);

                    // Update prescription item quantity dispensed
                    $upItem = $db->prepare("UPDATE `prescription_items` SET `quantity_dispensed` = ? WHERE `id` = ?");
                    $upItem->execute([$dispenseQty, $item['id']]);

                    $itemCost = $dispenseQty * (float)$batch['unit_sale_price'];
                    $totalPharmacyCost += $itemCost;
                    $dispensedBatchItems[] = [
                        'drug_item_id' => $item['drug_item_id'],
                        'batch_id' => $batch['id'],
                        'batch_number' => $batch['batch_number'],
                        'quantity' => $dispenseQty,
                        'unit_price' => (float)$batch['unit_sale_price'],
                        'subtotal' => $itemCost
                    ];
                }
            }

            // Post to Centralized Billing Ledger
            if ($rxEncData && $totalPharmacyCost > 0) {
                $pEncId = (int)$rxEncData['encounter_id'];
                $pPatId = (int)$rxEncData['patient_id'];

                $bStmt = $db->prepare("SELECT id FROM `bills` WHERE `encounter_id` = ? ORDER BY id DESC LIMIT 1");
                $bStmt->execute([$pEncId]);
                $existingBill = $bStmt->fetch();

                $targetBillId = 0;
                if (!$existingBill) {
                    $latestBill = $db->query("SELECT COALESCE(MAX(id), 0) FROM `bills`")->fetchColumn();
                    $billNum = 'INV-' . date('Y') . '-' . str_pad((string)($latestBill + 1), 4, '0', STR_PAD_LEFT);
                    $insBill = $db->prepare("
                        INSERT INTO `bills` 
                        (`bill_number`, `encounter_id`, `patient_id`, `bill_type`, `bill_date`, `gross_amount`, `net_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_by`)
                        VALUES (?, ?, ?, 'Pharmacy', NOW(), 0, 0, 0, 0, 'Unpaid', ?)
                    ");
                    $insBill->execute([$billNum, $pEncId, $pPatId, Auth::id()]);
                    $targetBillId = (int)$db->lastInsertId();
                } else {
                    $targetBillId = (int)$existingBill['id'];
                }

                if ($targetBillId > 0) {
                    $insBItem = $db->prepare("
                        INSERT INTO `bill_items` (`bill_id`, `item_type`, `reference_id`, `description`, `quantity`, `unit_price`, `subtotal`, `net_price`)
                        VALUES (?, 'Pharmacy', ?, ?, ?, ?, ?, ?)
                    ");
                    $dNameStmt = $db->prepare("SELECT brand_name FROM `drug_items` WHERE `id` = ?");

                    foreach ($dispensedBatchItems as $dbi) {
                        $dNameStmt->execute([$dbi['drug_item_id']]);
                        $dName = $dNameStmt->fetchColumn() ?: 'Medication';
                        $desc = "Pharmacy: $dName (Batch #{$dbi['batch_number']})";
                        $insBItem->execute([$targetBillId, $dbi['batch_id'], $desc, $dbi['quantity'], $dbi['unit_price'], $dbi['subtotal'], $dbi['subtotal']]);
                    }

                    $upBill = $db->prepare("
                        UPDATE `bills` 
                        SET `gross_amount` = `gross_amount` + ?, `net_amount` = `net_amount` + ?, `balance_amount` = `balance_amount` + ? 
                        WHERE `id` = ?
                    ");
                    $upBill->execute([$totalPharmacyCost, $totalPharmacyCost, $totalPharmacyCost, $targetBillId]);
                }
            }

            // Mark prescription Dispensed
            $rxUp = $db->prepare("UPDATE `prescriptions` SET `status` = 'Dispensed' WHERE `id` = ?");
            $rxUp->execute([$rxId]);

            AuditLogger::log('UPDATE', 'prescriptions', $rxId, null, ['status' => 'Dispensed', 'total_cost' => $totalPharmacyCost]);
            $db->commit();

            set_flash('success', "Prescription verified and medications dispensed from FEFO inventory batches. Charges posted to billing ledger.");
            header("Location: index.php?page=pharmacy&print_receipt=" . $rxId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Dispense failed: " . $e->getMessage());
            header("Location: index.php?page=pharmacy");
            exit;
        }
    }

    // 11. Laboratory Sample Collection
    if ($action === 'lab_collect_sample' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'lab_technician', 'nurse']);

        $itemId = (int)($_POST['order_item_id'] ?? 0);
        $barcode = 'BC-' . date('Y') . '-' . str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            UPDATE `lab_order_items` 
            SET `sample_barcode` = ?, `sample_collected_at` = NOW(), `collected_by` = ?, `item_status` = 'Collected'
            WHERE `id` = ?
        ");
        $stmt->execute([$barcode, Auth::id(), $itemId]);

        AuditLogger::log('UPDATE', 'lab_order_items', $itemId, null, ['barcode' => $barcode, 'status' => 'Collected']);
        set_flash('success', "Specimen collected and assigned Barcode #$barcode.");
        header("Location: index.php?page=laboratory&item_id=" . $itemId);
        exit;
    }

    // 12. Laboratory Save Result & Sign-off
    if ($action === 'lab_save_result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'lab_technician', 'doctor']);

        $itemId = (int)($_POST['order_item_id'] ?? 0);
        $paramIds = $_POST['param_ids'] ?? [];
        $resultValues = $_POST['result_values'] ?? [];
        $flags = $_POST['flags'] ?? [];
        $actionType = clean_input($_POST['action_type'] ?? 'save_draft');

        try {
            $db->beginTransaction();

            $delExisting = $db->prepare("DELETE FROM `lab_results` WHERE `lab_order_item_id` = ?");
            $delExisting->execute([$itemId]);

            $resStmt = $db->prepare("
                INSERT INTO `lab_results` 
                (`lab_order_item_id`, `parameter_id`, `result_value`, `abnormal_flag`, `tested_by`, `verified_by`, `verified_at`)
                VALUES (?, ?, ?, ?, ?, ?, IF(? = 'verify_and_sign', NOW(), NULL))
            ");

            $verifiedBy = ($actionType === 'verify_and_sign') ? Auth::id() : null;

            foreach ($paramIds as $pId) {
                $val = clean_input($resultValues[$pId] ?? '');
                $flag = clean_input($flags[$pId] ?? 'Normal');
                if ($val !== '') {
                    $resStmt->execute([$itemId, (int)$pId, $val, $flag, Auth::id(), $verifiedBy, $actionType]);
                }
            }

            // Update item status
            $newStatus = ($actionType === 'verify_and_sign') ? 'Verified' : 'Tested';
            $upStatus = $db->prepare("UPDATE `lab_order_items` SET `item_status` = ? WHERE `id` = ?");
            $upStatus->execute([$newStatus, $itemId]);

            AuditLogger::log('UPDATE', 'lab_order_items', $itemId, null, ['status' => $newStatus]);
            $db->commit();

            set_flash('success', $actionType === 'verify_and_sign' ? "Diagnostic report digitally verified and published." : "Results saved as draft.");
            header("Location: index.php?page=laboratory&item_id=" . $itemId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Failed to save lab results: " . $e->getMessage());
            header("Location: index.php?page=laboratory&item_id=" . $itemId);
            exit;
        }
    }

    // 13. Billing Cashier Payment Receipt
    if ($action === 'billing_pay' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'cashier']);

        $billId = (int)($_POST['bill_id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $amountPaid = (float)($_POST['amount_paid'] ?? 0);
        $paymentMethod = clean_input($_POST['payment_method'] ?? 'Cash');
        $txRef = clean_input($_POST['transaction_reference'] ?? 'COUNTER-PAY');

        if ($amountPaid <= 0) {
            set_flash('danger', 'Payment amount must be greater than zero.');
            header("Location: index.php?page=billing&bill_id=" . $billId);
            exit;
        }

        try {
            $db->beginTransaction();

            // Generate receipt number
            $latestPay = $db->query("SELECT COALESCE(MAX(id), 0) FROM `payments`")->fetchColumn();
            $receiptNum = 'RCPT-' . date('Y') . '-' . str_pad((string)($latestPay + 1), 4, '0', STR_PAD_LEFT);

            $payStmt = $db->prepare("
                INSERT INTO `payments` (`receipt_number`, `bill_id`, `patient_id`, `amount_paid`, `payment_method`, `transaction_reference`, `payment_datetime`, `received_by`)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $payStmt->execute([$receiptNum, $billId, $patientId, $amountPaid, $paymentMethod, $txRef, Auth::id()]);

            // Update bill paid and balance amounts
            $billStmt = $db->prepare("SELECT net_amount, paid_amount FROM `bills` WHERE `id` = ?");
            $billStmt->execute([$billId]);
            $b = $billStmt->fetch();

            $newPaid = $b['paid_amount'] + $amountPaid;
            $newBalance = max(0, $b['net_amount'] - $newPaid);
            $payStatus = ($newBalance <= 0) ? 'Fully-Paid' : 'Partially-Paid';

            $billUp = $db->prepare("UPDATE `bills` SET `paid_amount` = ?, `balance_amount` = ?, `payment_status` = ? WHERE `id` = ?");
            $billUp->execute([$newPaid, $newBalance, $payStatus, $billId]);

            AuditLogger::log('CREATE', 'payments', $db->lastInsertId(), null, ['receipt' => $receiptNum, 'amount' => $amountPaid]);
            $db->commit();

            set_flash('success', "Payment received ($amountPaid PKR) and official receipt issued: $receiptNum");
            header("Location: index.php?page=billing&bill_id=" . $billId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Payment processing failed: " . $e->getMessage());
            header("Location: index.php?page=billing&bill_id=" . $billId);
            exit;
        }
    }

    // 14. Update Hospital Profile Settings
    if ($action === 'settings_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin']);

        $name = clean_input($_POST['hospital_name'] ?? '');
        $tagline = clean_input($_POST['tagline'] ?? '');
        $hotline = clean_input($_POST['emergency_hotline'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $address = clean_input($_POST['address'] ?? '');
        $city = clean_input($_POST['city'] ?? '');
        $country = clean_input($_POST['country'] ?? 'Pakistan');
        $license = clean_input($_POST['license_number'] ?? '');
        $tax = clean_input($_POST['tax_number'] ?? '');
        $currency = clean_input($_POST['currency_symbol'] ?? 'PKR ');

        $stmt = $db->prepare("
            UPDATE `hospital_profiles` 
            SET `hospital_name` = ?, `tagline` = ?, `emergency_hotline` = ?, `phone` = ?, `email` = ?,
                `address` = ?, `city` = ?, `country` = ?, `license_number` = ?, `tax_number` = ?, `currency_symbol` = ?
            WHERE `id` = 1
        ");
        $stmt->execute([$name, $tagline, $hotline, $phone, $email, $address, $city, $country, $license, $tax, $currency]);

        AuditLogger::log('UPDATE', 'hospital_profiles', 1, null, ['hospital_name' => $name]);
        set_flash('success', 'Hospital profile and facility settings updated successfully.');
        header("Location: index.php?page=settings&tab=profile");
        exit;
    }

    // 15. Create Department
    if ($action === 'department_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']);

        $deptName = clean_input($_POST['department_name'] ?? '');
        $code = clean_input($_POST['code'] ?? '');
        $type = clean_input($_POST['type'] ?? 'Clinical');
        $desc = clean_input($_POST['description'] ?? '');

        $stmt = $db->prepare("INSERT INTO `departments` (`hospital_id`, `department_name`, `code`, `type`, `description`, `status`) VALUES (?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([Tenant::id(), $deptName, $code, $type, $desc]);

        AuditLogger::log('CREATE', 'departments', $db->lastInsertId(), null, ['code' => $code, 'name' => $deptName]);
        set_flash('success', "Department '$deptName' created successfully.");
        header("Location: index.php?page=settings&tab=departments");
        exit;
    }

    // 16. Create Ward
    if ($action === 'ward_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']);

        $wardName = clean_input($_POST['ward_name'] ?? '');
        $code = clean_input($_POST['code'] ?? '');
        $type = clean_input($_POST['ward_type'] ?? 'General_Male');
        $floor = clean_input($_POST['floor_number'] ?? '1st Floor');

        $stmt = $db->prepare("INSERT INTO `wards` (`hospital_id`, `ward_name`, `code`, `ward_type`, `floor_number`, `status`) VALUES (?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([Tenant::id(), $wardName, $code, $type, $floor]);
        $wardId = (int)$db->lastInsertId();

        // Create a default room inside this ward
        $rStmt = $db->prepare("INSERT INTO `rooms` (`ward_id`, `room_number`, `room_type`, `daily_rate`) VALUES (?, ?, 'Standard Room', 1500.00)");
        $rStmt->execute([$wardId, $code . '-R1']);

        AuditLogger::log('CREATE', 'wards', $wardId, null, ['code' => $code, 'name' => $wardName]);
        set_flash('success', "Ward '$wardName' created successfully.");
        header("Location: index.php?page=settings&tab=wards");
        exit;
    }

    // 17. Create Bed
    if ($action === 'bed_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']);

        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedNumber = clean_input($_POST['bed_number'] ?? '');
        $bedType = clean_input($_POST['bed_type'] ?? 'Standard');
        $tariff = (float)($_POST['daily_tariff'] ?? 1500.00);

        $stmt = $db->prepare("INSERT INTO `beds` (`room_id`, `bed_number`, `bed_type`, `daily_tariff`, `status`) VALUES (?, ?, ?, ?, 'Available')");
        $stmt->execute([$roomId, $bedNumber, $bedType, $tariff]);

        AuditLogger::log('CREATE', 'beds', $db->lastInsertId(), null, ['bed_number' => $bedNumber]);
        set_flash('success', "Bed '$bedNumber' added and ready for admissions.");
        header("Location: index.php?page=settings&tab=wards");
        exit;
    }

    // 18. Create Service Tariff
    if ($action === 'tariff_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']);

        $name = clean_input($_POST['service_name'] ?? '');
        $code = clean_input($_POST['service_code'] ?? '');
        $cat = clean_input($_POST['category'] ?? 'Procedure');
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $price = (float)($_POST['standard_price'] ?? 0.00);

        $stmt = $db->prepare("INSERT INTO `tariff_services` (`hospital_id`, `department_id`, `service_name`, `service_code`, `category`, `standard_price`, `is_active`) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([Tenant::id(), $deptId, $name, $code, $cat, $price]);

        AuditLogger::log('CREATE', 'tariff_services', $db->lastInsertId(), null, ['service_code' => $code]);
        set_flash('success', "Service '$name' added to official tariff catalog.");
        header("Location: index.php?page=settings&tab=tariffs");
        exit;
    }

    // 19. Create Drug Item
    if ($action === 'drug_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist', 'hospital_owner', 'platform_admin']);

        $brand = clean_input($_POST['brand_name'] ?? '');
        $generic = clean_input($_POST['generic_name'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 1);
        $form = clean_input($_POST['dosage_form'] ?? 'Tablet');
        $strength = clean_input($_POST['strength'] ?? '500mg');

        $stmt = $db->prepare("INSERT INTO `drug_items` (`hospital_id`, `category_id`, `brand_name`, `generic_name`, `dosage_form`, `strength`, `status`) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([Tenant::id(), $catId, $brand, $generic, $form, $strength]);

        AuditLogger::log('CREATE', 'drug_items', $db->lastInsertId(), null, ['brand' => $brand]);
        set_flash('success', "Medicine '$brand' added to formulary.");
        header("Location: index.php?page=settings&tab=drugs");
        exit;
    }

    // 20. Add Drug Batch Stock
    if ($action === 'drug_batch_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist', 'hospital_owner', 'platform_admin']);

        $drugId = (int)($_POST['drug_item_id'] ?? 0);
        $batchNo = clean_input($_POST['batch_number'] ?? '');
        $expiry = clean_input($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+1 year')));
        $qty = (int)($_POST['quantity'] ?? 100);
        $cost = (float)($_POST['unit_purchase_price'] ?? 0.00);
        $sale = (float)($_POST['unit_sale_price'] ?? 0.00);

        $stmt = $db->prepare("
            INSERT INTO `drug_batches` 
            (`hospital_id`, `drug_item_id`, `batch_number`, `expiry_date`, `unit_purchase_price`, `unit_sale_price`, `initial_quantity`, `current_quantity`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        $stmt->execute([Tenant::id(), $drugId, $batchNo, $expiry, $cost, $sale, $qty, $qty]);

        AuditLogger::log('CREATE', 'drug_batches', $db->lastInsertId(), null, ['batch_number' => $batchNo, 'qty' => $qty]);
        set_flash('success', "Batch '$batchNo' ($qty units) received into pharmacy inventory.");
        header("Location: index.php?page=settings&tab=drugs");
        exit;
    }

    // 21. Create Lab Test
    if ($action === 'lab_test_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'lab_technician', 'hospital_owner', 'platform_admin']);

        $name = clean_input($_POST['test_name'] ?? '');
        $code = clean_input($_POST['test_code'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 1);
        $specimen = clean_input($_POST['specimen_type'] ?? 'Blood');
        $tat = (int)($_POST['turnaround_hours'] ?? 6);
        $price = (float)($_POST['standard_price'] ?? 500.00);

        $stmt = $db->prepare("INSERT INTO `lab_tests` (`hospital_id`, `category_id`, `test_name`, `test_code`, `specimen_type`, `turnaround_hours`, `standard_price`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([Tenant::id(), $catId, $name, $code, $specimen, $tat, $price]);
        $testId = (int)$db->lastInsertId();

        // Add primary parameter
        $pStmt = $db->prepare("INSERT INTO `lab_test_parameters` (`lab_test_id`, `parameter_name`, `unit`, `reference_range`) VALUES (?, ?, 'units', 'Standard Range')");
        $pStmt->execute([$testId, $name]);

        AuditLogger::log('CREATE', 'lab_tests', $testId, null, ['code' => $code]);
        set_flash('success', "Diagnostic test '$name' added to lab catalog.");
        header("Location: index.php?page=settings&tab=labs");
        exit;
    }

    // 22. Create Staff Account
    if ($action === 'user_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']);

        $username = clean_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? 'Admin@123';
        $fullName = clean_input($_POST['full_name'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 3);
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = clean_input($_POST['designation'] ?? '');
        $fee = (float)($_POST['consultation_fee'] ?? 0.00);
        $email = clean_input($_POST['email'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');

        if (empty($username) || empty($fullName) || empty($email)) {
            set_flash('danger', "Please provide username, full name, and email address.");
            header("Location: index.php?page=users");
            exit;
        }

        // Duplicate username or email check
        $dupStmt = $db->prepare("SELECT id FROM `users` WHERE `username` = ? OR `email` = ? LIMIT 1");
        $dupStmt->execute([$username, $email]);
        if ($dupStmt->fetch()) {
            set_flash('danger', "Username '@$username' or email '$email' is already registered in the system.");
            header("Location: index.php?page=users");
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $db->prepare("
                INSERT INTO `users` 
                (`hospital_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role_id`, `department_id`, `designation`, `consultation_fee`, `status`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $stmt->execute([Tenant::id(), $username, $email, $hash, $fullName, $phone, $roleId, $deptId, $designation, $fee]);
            $newUserId = (int)$db->lastInsertId();

            AuditLogger::log('CREATE', 'users', $newUserId, null, ['username' => $username, 'role_id' => $roleId, 'full_name' => $fullName]);
            set_flash('success', "Staff account for '$fullName' (@$username) created successfully.");
            header("Location: index.php?page=users");
            exit;
        } catch (Exception $e) {
            set_flash('danger', "Failed to create staff account: " . $e->getMessage());
            header("Location: index.php?page=users");
            exit;
        }
    }

    // 22.1 Toggle User Status (Active / Inactive)
    if ($action === 'user_status_toggle') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']);

        $targetUserId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

        if ($targetUserId === Auth::id()) {
            set_flash('danger', "Security constraint: You cannot deactivate your own currently active administrative account.");
            header("Location: index.php?page=users");
            exit;
        }

        $uStmt = $db->prepare("SELECT id, hospital_id, username, full_name, status FROM `users` WHERE `id` = ?");
        $uStmt->execute([$targetUserId]);
        $targetUser = $uStmt->fetch();

        if ($targetUser) {
            Tenant::enforceScope((int)$targetUser['hospital_id']);
            $newStatus = ($targetUser['status'] === 'Active') ? 'Inactive' : 'Active';
            $db->prepare("UPDATE `users` SET `status` = ? WHERE `id` = ?")->execute([$newStatus, $targetUserId]);

            AuditLogger::log('UPDATE', 'users', $targetUserId, null, ['status' => $newStatus, 'username' => $targetUser['username']]);
            set_flash('success', "Account '@{$targetUser['username']}' ({$targetUser['full_name']}) is now marked $newStatus.");
        }
        header("Location: index.php?page=users");
        exit;
    }

    // 22.2 Reset Staff Password
    if ($action === 'user_reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']);

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? 'Admin@123';

        if (empty($newPassword)) {
            $newPassword = 'Admin@123';
        }

        $uStmt = $db->prepare("SELECT id, hospital_id, username, full_name FROM `users` WHERE `id` = ?");
        $uStmt->execute([$targetUserId]);
        $targetUser = $uStmt->fetch();

        if ($targetUser) {
            Tenant::enforceScope((int)$targetUser['hospital_id']);
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $db->prepare("UPDATE `users` SET `password_hash` = ? WHERE `id` = ?")->execute([$hash, $targetUserId]);

            AuditLogger::log('UPDATE', 'users', $targetUserId, null, ['action' => 'password_reset', 'username' => $targetUser['username']]);
            set_flash('success', "Password for '@{$targetUser['username']}' ({$targetUser['full_name']}) has been reset successfully.");
        }
        header("Location: index.php?page=users");
        exit;
    }

    // ------------------------------------------------------------------------
    // STAGE 2 ACTION HANDLERS
    // ------------------------------------------------------------------------

    // Emergency Intake Action
    if ($action === 'emergency_intake' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse', 'receptionist']);

        $existingPatientId = !empty($_POST['existing_patient_id']) ? (int)$_POST['existing_patient_id'] : null;
        $doctorId = (int)($_POST['attending_doctor_id'] ?? Auth::id());
        $triageLevel = clean_input($_POST['triage_level'] ?? 'ESI-3');
        $chiefComplaint = clean_input($_POST['chief_complaint'] ?? 'Acute ER Presentation');

        try {
            $db->beginTransaction();

            if ($existingPatientId) {
                $patientId = $existingPatientId;
            } else {
                $quickFirst = clean_input($_POST['quick_first_name'] ?? 'Trauma');
                $quickLast = clean_input($_POST['quick_last_name'] ?? 'Patient');
                $quickGender = clean_input($_POST['quick_gender'] ?? 'Male');
                $quickAge = !empty($_POST['quick_age']) ? (int)$_POST['quick_age'] : 30;
                $quickPhone = clean_input($_POST['quick_phone'] ?? '');
                $quickBlood = clean_input($_POST['quick_blood_group'] ?? 'Unknown');

                $latestId = $db->query("SELECT COALESCE(MAX(id), 0) FROM `patients`")->fetchColumn();
                $mrn = 'MRN-' . date('Y') . '-' . str_pad((string)($latestId + 1), 4, '0', STR_PAD_LEFT);

                $pStmt = $db->prepare("
                    INSERT INTO `patients` (`mrn`, `first_name`, `last_name`, `gender`, `age`, `blood_group`, `phone_primary`, `registered_by`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $pStmt->execute([$mrn, $quickFirst, $quickLast, $quickGender, $quickAge, $quickBlood, $quickPhone, Auth::id()]);
                $patientId = (int)$db->lastInsertId();
            }

            // Generate encounter number
            $encCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `encounters`")->fetchColumn();
            $encNum = 'ENC-' . date('Y') . '-' . str_pad((string)($encCount + 1), 4, '0', STR_PAD_LEFT);

            $encStmt = $db->prepare("
                INSERT INTO `encounters` 
                (`encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `encounter_status`, `triage_level`, `primary_diagnosis`, `created_by`)
                VALUES (?, ?, 'Emergency', 7, ?, NOW(), 'In-Progress', ?, ?, ?)
            ");
            $encStmt->execute([$encNum, $patientId, $doctorId, $triageLevel, $chiefComplaint, Auth::id()]);
            $encounterId = (int)$db->lastInsertId();

            // Record initial vitals if provided
            $sys = !empty($_POST['systolic_bp']) ? (int)$_POST['systolic_bp'] : null;
            $dia = !empty($_POST['diastolic_bp']) ? (int)$_POST['diastolic_bp'] : null;
            $pulse = !empty($_POST['pulse_rate']) ? (int)$_POST['pulse_rate'] : null;
            $spo2 = !empty($_POST['spo2_percentage']) ? (float)$_POST['spo2_percentage'] : null;

            if ($sys || $pulse || $spo2) {
                $vStmt = $db->prepare("
                    INSERT INTO `clinical_vitals` (`encounter_id`, `patient_id`, `systolic_bp`, `diastolic_bp`, `pulse_rate_bpm`, `spo2_percentage`, `recorded_by`)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $vStmt->execute([$encounterId, $patientId, $sys, $dia, $pulse, $spo2, Auth::id()]);
            }

            AuditLogger::log('CREATE', 'encounters', $encounterId, null, ['type' => 'Emergency', 'triage' => $triageLevel, 'patient_id' => $patientId]);
            $db->commit();

            set_flash('success', "Emergency Case registered ($encNum) with Triage $triageLevel.");
            header("Location: index.php?page=emergency");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Emergency intake failed: " . $e->getMessage());
            header("Location: index.php?page=emergency");
            exit;
        }
    }

    // Emergency Disposition Action
    if ($action === 'emergency_disposition' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse']);

        $encounterId = (int)($_POST['encounter_id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $primaryDiag = clean_input($_POST['primary_diagnosis'] ?? 'Emergency Evaluation Completed');
        $disposition = clean_input($_POST['discharge_disposition'] ?? 'Discharged_Home');
        $bedId = !empty($_POST['bed_id']) ? (int)$_POST['bed_id'] : null;
        $summary = clean_input($_POST['clinical_summary'] ?? '');

        try {
            $db->beginTransaction();

            if (in_array($disposition, ['Admitted_to_Ward', 'Admitted_to_ICU']) && $bedId) {
                // Admit to Inpatient
                $db->prepare("
                    UPDATE `encounters` 
                    SET `encounter_status` = 'Admitted', `discharge_disposition` = ?, `primary_diagnosis` = ? 
                    WHERE `id` = ?
                ")->execute([$disposition, $primaryDiag, $encounterId]);

                $admStmt = $db->prepare("
                    INSERT INTO `admissions` (`encounter_id`, `patient_id`, `admitting_doctor_id`, `admission_reason`, `initial_bed_id`, `current_bed_id`, `admission_datetime`, `status`)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 'Admitted')
                ");
                $admStmt->execute([$encounterId, $patientId, Auth::id(), $primaryDiag, $bedId, $bedId]);
                $admId = (int)$db->lastInsertId();

                $db->prepare("UPDATE `beds` SET `status` = 'Occupied' WHERE `id` = ?")->execute([$bedId]);
                $db->prepare("INSERT INTO `bed_allocations` (`admission_id`, `bed_id`, `allocated_at`, `allocated_by`) VALUES (?, ?, NOW(), ?)")
                   ->execute([$admId, $bedId, Auth::id()]);
            } else {
                // Discharged / Transferred / Other
                $db->prepare("
                    UPDATE `encounters` 
                    SET `encounter_status` = 'Completed', `discharge_disposition` = ?, `discharge_datetime` = NOW(), `primary_diagnosis` = ? 
                    WHERE `id` = ?
                ")->execute([$disposition, $primaryDiag, $encounterId]);
            }

            if (!empty($summary)) {
                $db->prepare("
                    INSERT INTO `consultation_notes` (`encounter_id`, `doctor_id`, `plan`, `assessment`)
                    VALUES (?, ?, ?, ?)
                ")->execute([$encounterId, Auth::id(), "Disposition: $disposition - $summary", $primaryDiag]);
            }

            AuditLogger::log('UPDATE', 'encounters', $encounterId, null, ['disposition' => $disposition, 'diagnosis' => $primaryDiag]);
            $db->commit();

            set_flash('success', "Emergency case disposition saved ($disposition).");
            header("Location: index.php?page=emergency");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Disposition update failed: " . $e->getMessage());
            header("Location: index.php?page=emergency");
            exit;
        }
    }

    // Radiology Order Store Action
    if ($action === 'radiology_order_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'radiologist', 'receptionist']);

        $encounterId = (int)($_POST['encounter_id'] ?? 0);
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $priority = clean_input($_POST['priority'] ?? 'Routine');
        $orderDt = !empty($_POST['order_datetime']) ? clean_input($_POST['order_datetime']) : date('Y-m-d H:i:s');
        $indication = clean_input($_POST['clinical_indication'] ?? '');

        $orderCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `radiology_orders`")->fetchColumn();
        $orderNum = 'RAD-' . date('Y') . '-' . str_pad((string)($orderCount + 1), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO `radiology_orders` (`order_number`, `encounter_id`, `service_id`, `ordered_by`, `order_datetime`, `priority`, `clinical_indication`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Ordered')
        ");
        $stmt->execute([$orderNum, $encounterId, $serviceId, Auth::id(), $orderDt, $priority, $indication]);

        AuditLogger::log('CREATE', 'radiology_orders', $db->lastInsertId(), null, ['order' => $orderNum, 'service_id' => $serviceId]);
        set_flash('success', "Radiology order ($orderNum) placed successfully.");
        header("Location: index.php?page=radiology");
        exit;
    }

    // Radiology Report Save Action
    if ($action === 'radiology_report_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'radiologist', 'lab_technician']);

        $orderId = (int)($_POST['radiology_order_id'] ?? 0);
        $technique = clean_input($_POST['technique_used'] ?? '');
        $findings = clean_input($_POST['radiological_findings'] ?? '');
        $impression = clean_input($_POST['impression'] ?? '');
        $reportStatus = clean_input($_POST['report_status'] ?? 'Finalized');

        $stmt = $db->prepare("
            INSERT INTO `radiology_reports` (`radiology_order_id`, `radiologist_id`, `technique_used`, `radiological_findings`, `impression`, `report_status`, `finalized_at`)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                `technique_used` = VALUES(`technique_used`),
                `radiological_findings` = VALUES(`radiological_findings`),
                `impression` = VALUES(`impression`),
                `report_status` = VALUES(`report_status`),
                `finalized_at` = NOW()
        ");
        $stmt->execute([$orderId, Auth::id(), $technique, $findings, $impression, $reportStatus]);

        $db->prepare("UPDATE `radiology_orders` SET `status` = 'Reported' WHERE `id` = ?")->execute([$orderId]);

        AuditLogger::log('UPDATE', 'radiology_orders', $orderId, null, ['status' => 'Reported']);
        set_flash('success', "Radiology diagnostic report finalized and signed.");
        header("Location: index.php?page=radiology");
        exit;
    }

    // OT Room Status Action
    if ($action === 'ot_room_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse']);

        $roomId = (int)($_POST['room_id'] ?? 0);
        $status = clean_input($_POST['status'] ?? 'Available');

        $db->prepare("UPDATE `ot_rooms` SET `status` = ? WHERE `id` = ?")->execute([$status, $roomId]);
        set_flash('info', "OT Room status updated to $status.");
        header("Location: index.php?page=ot");
        exit;
    }

    // OT Booking Store Action
    if ($action === 'ot_booking_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor']);

        $encounterId = (int)($_POST['encounter_id'] ?? 0);
        $otRoomId = (int)($_POST['ot_room_id'] ?? 0);
        $leadSurgeonId = (int)($_POST['lead_surgeon_id'] ?? Auth::id());
        $anesthesiologistId = !empty($_POST['anesthesiologist_id']) ? (int)$_POST['anesthesiologist_id'] : null;
        $procName = clean_input($_POST['procedure_name'] ?? '');
        $scheduledDt = clean_input($_POST['scheduled_datetime'] ?? date('Y-m-d H:i:s'));
        $duration = (int)($_POST['estimated_duration_minutes'] ?? 120);
        $anesType = clean_input($_POST['anesthesia_type'] ?? 'General');
        $preOpDiag = clean_input($_POST['pre_op_diagnosis'] ?? '');

        $patientId = $db->query("SELECT `patient_id` FROM `encounters` WHERE `id` = $encounterId")->fetchColumn();

        $bookingCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `surgery_bookings`")->fetchColumn();
        $bookingNum = 'OT-BKG-' . date('Y') . '-' . str_pad((string)($bookingCount + 1), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO `surgery_bookings` 
            (`booking_number`, `encounter_id`, `patient_id`, `ot_room_id`, `lead_surgeon_id`, `anesthesiologist_id`, `procedure_name`, `scheduled_datetime`, `estimated_duration_minutes`, `anesthesia_type`, `pre_op_diagnosis`, `booking_status`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)
        ");
        $stmt->execute([$bookingNum, $encounterId, $patientId, $otRoomId, $leadSurgeonId, $anesthesiologistId, $procName, $scheduledDt, $duration, $anesType, $preOpDiag, Auth::id()]);

        AuditLogger::log('CREATE', 'surgery_bookings', $db->lastInsertId(), null, ['booking' => $bookingNum, 'procedure' => $procName]);
        set_flash('success', "Surgery booked successfully with reference $bookingNum.");
        header("Location: index.php?page=ot");
        exit;
    }

    // OT Start Surgery Action
    if ($action === 'ot_start_surgery' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse']);

        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $bkg = $db->query("SELECT * FROM `surgery_bookings` WHERE `id` = $bookingId")->fetch(PDO::FETCH_ASSOC);

        if ($bkg) {
            $db->prepare("UPDATE `surgery_bookings` SET `booking_status` = 'In-Progress' WHERE `id` = ?")->execute([$bookingId]);
            $db->prepare("UPDATE `ot_rooms` SET `status` = 'In_Surgery' WHERE `id` = ?")->execute([$bkg['ot_room_id']]);
            set_flash('warning', "Surgery {$bkg['booking_number']} marked In-Progress. Room is now locked in surgery.");
        }
        header("Location: index.php?page=ot");
        exit;
    }

    // OT Operative Record Store Action
    if ($action === 'ot_record_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor']);

        $bookingId = (int)($_POST['surgery_booking_id'] ?? 0);
        $startTime = clean_input($_POST['start_time'] ?? date('Y-m-d H:i:s'));
        $endTime = clean_input($_POST['end_time'] ?? date('Y-m-d H:i:s'));
        $postOpDiag = clean_input($_POST['post_op_diagnosis'] ?? '');
        $notes = clean_input($_POST['operative_notes'] ?? '');
        $bloodLoss = (int)($_POST['blood_loss_ml'] ?? 50);
        $implants = clean_input($_POST['implant_details'] ?? '');
        $destination = clean_input($_POST['post_op_destination'] ?? 'PACU_Recovery');
        $spongeCheck = !empty($_POST['sponge_swab_count_correct']) ? 1 : 0;

        $bkg = $db->query("SELECT * FROM `surgery_bookings` WHERE `id` = $bookingId")->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            INSERT INTO `surgical_records` 
            (`surgery_booking_id`, `start_time`, `end_time`, `operative_notes`, `post_op_diagnosis`, `implant_details`, `sponge_swab_count_correct`, `blood_loss_ml`, `post_op_destination`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$bookingId, $startTime, $endTime, $notes, $postOpDiag, $implants, $spongeCheck, $bloodLoss, $destination]);

        $db->prepare("UPDATE `surgery_bookings` SET `booking_status` = 'Completed' WHERE `id` = ?")->execute([$bookingId]);
        if ($bkg) {
            $db->prepare("UPDATE `ot_rooms` SET `status` = 'Sterilization' WHERE `id` = ?")->execute([$bkg['ot_room_id']]);
        }

        AuditLogger::log('CREATE', 'surgical_records', $db->lastInsertId(), null, ['booking_id' => $bookingId, 'post_op' => $postOpDiag]);
        set_flash('success', "Operative record saved. Surgery marked Completed and OT set to Sterilization.");
        header("Location: index.php?page=ot");
        exit;
    }

    // Blood Donor Store Action
    if ($action === 'blood_donor_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'lab_technician']);

        $fullName = clean_input($_POST['full_name'] ?? '');
        $bloodGroup = clean_input($_POST['blood_group'] ?? 'O+');
        $gender = clean_input($_POST['gender'] ?? 'Male');
        $phone = clean_input($_POST['phone'] ?? '');
        $cnic = clean_input($_POST['cnic'] ?? '');
        $status = clean_input($_POST['screening_status'] ?? 'Eligible');

        $dCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `blood_donors`")->fetchColumn();
        $donorNum = 'DNR-' . date('Y') . '-' . str_pad((string)($dCount + 1), 3, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO `blood_donors` (`donor_number`, `full_name`, `gender`, `blood_group`, `phone`, `cnic`, `screening_status`, `last_donation_date`)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([$donorNum, $fullName, $gender, $bloodGroup, $phone, $cnic, $status]);

        AuditLogger::log('CREATE', 'blood_donors', $db->lastInsertId(), null, ['donor' => $donorNum, 'group' => $bloodGroup]);
        set_flash('success', "Blood Donor '$fullName' ($donorNum) registered successfully.");
        header("Location: index.php?page=blood_bank&tab=donors");
        exit;
    }

    // Blood Unit Store Action
    if ($action === 'blood_unit_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'lab_technician']);

        $donorId = !empty($_POST['donor_id']) ? (int)$_POST['donor_id'] : null;
        $bloodGroup = clean_input($_POST['blood_group'] ?? 'O+');
        $compType = clean_input($_POST['component_type'] ?? 'Packed_Red_Cells_PRBC');
        $volume = (int)($_POST['volume_ml'] ?? 450);
        $location = clean_input($_POST['storage_freezer_location'] ?? 'Cold Storage A1');
        $colDate = clean_input($_POST['collection_date'] ?? date('Y-m-d'));
        $expDate = clean_input($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+35 days')));

        $barcode = 'BLD-' . str_replace(['+', '-'], ['P', 'N'], $bloodGroup) . '-' . date('ymd') . '-' . rand(100, 999);

        $stmt = $db->prepare("
            INSERT INTO `blood_units` 
            (`unit_barcode`, `donor_id`, `component_type`, `blood_group`, `volume_ml`, `collection_date`, `expiry_date`, `storage_freezer_location`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Available')
        ");
        $stmt->execute([$barcode, $donorId, $compType, $bloodGroup, $volume, $colDate, $expDate, $location]);

        AuditLogger::log('CREATE', 'blood_units', $db->lastInsertId(), null, ['barcode' => $barcode, 'group' => $bloodGroup]);
        set_flash('success', "Blood unit ($barcode) placed into storage.");
        header("Location: index.php?page=blood_bank&tab=inventory");
        exit;
    }

    // Blood Request Store Action
    if ($action === 'blood_request_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse']);

        $encounterId = (int)($_POST['encounter_id'] ?? 0);
        $compType = clean_input($_POST['component_type'] ?? 'Packed_Red_Cells_PRBC');
        $unitsReq = (int)($_POST['units_required'] ?? 1);
        $urgency = clean_input($_POST['urgency'] ?? 'Routine');
        $indication = clean_input($_POST['clinical_indication'] ?? '');

        $patientId = $db->query("SELECT `patient_id` FROM `encounters` WHERE `id` = $encounterId")->fetchColumn();

        $reqCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `blood_requests`")->fetchColumn();
        $reqNum = 'BRQ-' . date('Y') . '-' . str_pad((string)($reqCount + 1), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO `blood_requests` 
            (`request_number`, `encounter_id`, `patient_id`, `requested_by`, `component_type`, `units_required`, `urgency`, `clinical_indication`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$reqNum, $encounterId, $patientId, Auth::id(), $compType, $unitsReq, $urgency, $indication]);

        AuditLogger::log('CREATE', 'blood_requests', $db->lastInsertId(), null, ['req' => $reqNum, 'urgency' => $urgency]);
        set_flash('success', "Blood requisition ($reqNum) submitted to blood bank.");
        header("Location: index.php?page=blood_bank&tab=requests");
        exit;
    }

    // Blood Issue Unit Action
    if ($action === 'blood_issue_unit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'lab_technician']);

        $requestId = (int)($_POST['request_id'] ?? 0);
        $unitId = (int)($_POST['unit_id'] ?? 0);

        $db->prepare("UPDATE `blood_units` SET `status` = 'Issued' WHERE `id` = ?")->execute([$unitId]);
        $db->prepare("UPDATE `blood_requests` SET `status` = 'Issued' WHERE `id` = ?")->execute([$requestId]);

        AuditLogger::log('UPDATE', 'blood_units', $unitId, null, ['status' => 'Issued', 'request_id' => $requestId]);
        set_flash('success', "Blood unit cross-matched and issued for transfusion.");
        header("Location: index.php?page=blood_bank&tab=requests");
        exit;
    }

    // Insurance Company Store Action
    if ($action === 'insurance_company_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'cashier']);

        $name = clean_input($_POST['company_name'] ?? '');
        $code = clean_input($_POST['company_code'] ?? '');
        $period = (int)($_POST['credit_period_days'] ?? 30);
        $person = clean_input($_POST['contact_person'] ?? '');
        $phone = clean_input($_POST['contact_phone'] ?? '');
        $email = clean_input($_POST['contact_email'] ?? '');

        $stmt = $db->prepare("
            INSERT INTO `insurance_companies` (`company_name`, `company_code`, `contact_person`, `contact_phone`, `contact_email`, `credit_period_days`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $code, $person, $phone, $email, $period]);

        AuditLogger::log('CREATE', 'insurance_companies', $db->lastInsertId(), null, ['name' => $name, 'code' => $code]);
        set_flash('success', "Panel Insurance partner '$name' registered.");
        header("Location: index.php?page=insurance");
        exit;
    }

    // Insurance Claim Store Action
    if ($action === 'insurance_claim_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'cashier']);

        $billId = (int)($_POST['bill_id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $companyId = (int)($_POST['company_id'] ?? 0);
        $policyNum = clean_input($_POST['policy_number'] ?? '');
        $preAuth = clean_input($_POST['pre_auth_code'] ?? '');
        $claimAmt = (float)($_POST['claim_amount'] ?? 0.00);
        $copay = (float)($_POST['patient_copay_amount'] ?? 0.00);
        $remarks = clean_input($_POST['remarks'] ?? '');

        $cCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `insurance_claims`")->fetchColumn();
        $claimNum = 'CLM-' . date('Y') . '-' . str_pad((string)($cCount + 1), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO `insurance_claims` 
            (`claim_number`, `bill_id`, `patient_id`, `company_id`, `policy_number`, `pre_auth_code`, `claim_amount`, `patient_copay_amount`, `status`, `remarks`, `submitted_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', ?, NOW())
        ");
        $stmt->execute([$claimNum, $billId, $patientId, $companyId, $policyNum, $preAuth, $claimAmt, $copay, $remarks]);

        AuditLogger::log('CREATE', 'insurance_claims', $db->lastInsertId(), null, ['claim' => $claimNum, 'amount' => $claimAmt]);
        set_flash('success', "Insurance claim ($claimNum) submitted to panel.");
        header("Location: index.php?page=insurance");
        exit;
    }

    // Insurance Claim Update & Settlement Action
    if ($action === 'insurance_claim_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'cashier']);

        $claimId = (int)($_POST['claim_id'] ?? 0);
        $status = clean_input($_POST['status'] ?? 'Approved');
        $approvedAmt = (float)($_POST['approved_amount'] ?? 0.00);
        $ref = clean_input($_POST['settlement_ref'] ?? '');

        $claim = $db->query("SELECT * FROM `insurance_claims` WHERE `id` = $claimId")->fetch(PDO::FETCH_ASSOC);

        if ($claim) {
            $db->prepare("UPDATE `insurance_claims` SET `status` = ?, `approved_amount` = ? WHERE `id` = ?")
               ->execute([$status, $approvedAmt, $claimId]);

            // If Settled, credit bill payment
            if ($status === 'Settled' && $approvedAmt > 0) {
                $billId = $claim['bill_id'];
                $payCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `payments`")->fetchColumn();
                $recNum = 'RCT-TPA-' . date('Y') . '-' . str_pad((string)($payCount + 1), 4, '0', STR_PAD_LEFT);

                $pStmt = $db->prepare("
                    INSERT INTO `payments` (`receipt_number`, `bill_id`, `amount_paid`, `payment_method`, `transaction_reference`, `received_by`)
                    VALUES (?, ?, ?, 'Insurance_TPA', ?, ?)
                ");
                $pStmt->execute([$recNum, $billId, $approvedAmt, $ref, Auth::id()]);

                // Update bill balance
                $db->prepare("
                    UPDATE `bills` 
                    SET `paid_amount` = `paid_amount` + ?,
                        `balance_amount` = GREATEST(0, `balance_amount` - ?),
                        `payment_status` = CASE WHEN (`balance_amount` - ?) <= 0 THEN 'Fully-Paid' ELSE 'Partially-Paid' END
                    WHERE `id` = ?
                ")->execute([$approvedAmt, $approvedAmt, $approvedAmt, $billId]);
            }

            AuditLogger::log('UPDATE', 'insurance_claims', $claimId, null, ['status' => $status, 'approved' => $approvedAmt]);
            set_flash('success', "Insurance Claim {$claim['claim_number']} updated to $status.");
        }
        header("Location: index.php?page=insurance");
        exit;
    }

    // Inventory Item Store Action
    if ($action === 'inventory_item_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

        $name = clean_input($_POST['item_name'] ?? '');
        $genericName = clean_input($_POST['generic_name'] ?? '');
        $dosageForm = clean_input($_POST['dosage_form'] ?? 'Tablets');
        $code = clean_input($_POST['item_code'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 1);
        $uom = clean_input($_POST['unit_of_measure'] ?? 'Pack');
        $stock = (int)($_POST['current_stock'] ?? 0);
        $reorder = (int)($_POST['reorder_level'] ?? 20);
        $cost = (float)($_POST['unit_cost_price'] ?? 0.00);
        $salePrice = (float)($_POST['unit_sale_price'] ?? 0.00);
        $isNarcotic = !empty($_POST['is_narcotic']) ? 1 : 0;
        $narcoticSchedule = !empty($_POST['narcotic_schedule']) ? clean_input($_POST['narcotic_schedule']) : null;
        $storageCondition = clean_input($_POST['storage_condition'] ?? 'Room Temperature (15-25°C)');
        $batchNumber = clean_input($_POST['batch_number'] ?? '');
        $expiryDate = !empty($_POST['expiry_date']) ? clean_input($_POST['expiry_date']) : null;

        $stmt = $db->prepare("
            INSERT INTO `inventory_items` 
            (`category_id`, `item_name`, `generic_name`, `dosage_form`, `is_narcotic`, `narcotic_schedule`, `storage_condition`, `batch_number`, `expiry_date`, `item_code`, `unit_of_measure`, `current_stock`, `reorder_level`, `unit_cost_price`, `unit_sale_price`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$catId, $name, $genericName, $dosageForm, $isNarcotic, $narcoticSchedule, $storageCondition, $batchNumber, $expiryDate, $code, $uom, $stock, $reorder, $cost, $salePrice]);

        AuditLogger::log('CREATE', 'inventory_items', $db->lastInsertId(), null, ['name' => $name, 'code' => $code, 'is_narcotic' => $isNarcotic]);
        set_flash('success', "Item '$name' ($code) added to inventory catalog with batch tracking.");
        header("Location: index.php?page=inventory&tab=stock");
        exit;
    }

    // Supplier Store Action
    if ($action === 'supplier_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

        $name = clean_input($_POST['supplier_name'] ?? '');
        $code = clean_input($_POST['code'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');
        $person = clean_input($_POST['contact_person'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $address = clean_input($_POST['address'] ?? '');

        $stmt = $db->prepare("
            INSERT INTO `suppliers` (`supplier_name`, `code`, `contact_person`, `phone`, `email`, `address`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $code, $person, $phone, $email, $address]);

        AuditLogger::log('CREATE', 'suppliers', $db->lastInsertId(), null, ['name' => $name, 'code' => $code]);
        set_flash('success', "Vendor '$name' registered successfully.");
        header("Location: index.php?page=inventory&tab=suppliers");
        exit;
    }

    // Purchase Order Store Action
    if ($action === 'po_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $delDate = !empty($_POST['expected_delivery_date']) ? clean_input($_POST['expected_delivery_date']) : null;
        $itemName = clean_input($_POST['item_name'] ?? 'Consumables');
        $qty = (int)($_POST['quantity'] ?? 1);
        $unitPrice = (float)($_POST['unit_price'] ?? 0.00);
        $totalAmt = $qty * $unitPrice;

        $poCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `purchase_orders`")->fetchColumn();
        $poNum = 'PO-' . date('Y') . '-' . str_pad((string)($poCount + 1), 4, '0', STR_PAD_LEFT);

        try {
            $db->beginTransaction();

            $poStmt = $db->prepare("
                INSERT INTO `purchase_orders` (`po_number`, `supplier_id`, `order_date`, `expected_delivery_date`, `total_amount`, `status`, `created_by`)
                VALUES (?, ?, CURDATE(), ?, ?, 'Approved', ?)
            ");
            $poStmt->execute([$poNum, $supplierId, $delDate, $totalAmt, Auth::id()]);
            $poId = (int)$db->lastInsertId();

            $itemStmt = $db->prepare("
                INSERT INTO `purchase_order_items` (`po_id`, `item_name`, `quantity`, `unit_price`, `total_price`)
                VALUES (?, ?, ?, ?, ?)
            ");
            $itemStmt->execute([$poId, $itemName, $qty, $unitPrice, $totalAmt]);

            AuditLogger::log('CREATE', 'purchase_orders', $poId, null, ['po' => $poNum, 'amount' => $totalAmt]);
            $db->commit();

            set_flash('success', "Purchase Order ($poNum) issued for " . format_currency($totalAmt));
            header("Location: index.php?page=inventory&tab=pos");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Failed to issue PO: " . $e->getMessage());
            header("Location: index.php?page=inventory&tab=pos");
            exit;
        }
    }

    // Purchase Order Receive (GRN) Action
    if ($action === 'po_receive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

        $poId = (int)($_POST['po_id'] ?? 0);

        try {
            $db->beginTransaction();

            $poItems = $db->query("SELECT * FROM `purchase_order_items` WHERE `po_id` = $poId")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($poItems as $pItem) {
                // Increment inventory stock
                $db->prepare("
                    UPDATE `inventory_items` 
                    SET `current_stock` = `current_stock` + ? 
                    WHERE `item_name` = ?
                ")->execute([(int)$pItem['quantity'], $pItem['item_name']]);
            }

            $db->prepare("UPDATE `purchase_orders` SET `status` = 'Received' WHERE `id` = ?")->execute([$poId]);

            AuditLogger::log('UPDATE', 'purchase_orders', $poId, null, ['status' => 'Received']);
            $db->commit();

            set_flash('success', "Goods Receipt Note (GRN) processed. Warehouse stock updated.");
            header("Location: index.php?page=inventory&tab=pos");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Failed to receive goods: " . $e->getMessage());
            header("Location: index.php?page=inventory&tab=pos");
            exit;
        }
    }

    // Pharmacy Dispense Action with Automatic FEFO Stock Deduction & Invoicing
    if ($action === 'pharmacy_dispense' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist', 'doctor']);

        $rxId = (int)($_POST['prescription_id'] ?? 0);
        if ($rxId <= 0) {
            set_flash('danger', 'Invalid prescription ID.');
            header("Location: index.php?page=pharmacy&tab=queue");
            exit;
        }

        // Fetch prescription with encounter & patient
        $rxStmt = $db->prepare("
            SELECT p.*, e.id AS enc_id, e.encounter_number, e.encounter_type, e.patient_id,
                   pt.first_name, pt.last_name, pt.mrn
            FROM `prescriptions` p
            JOIN `encounters` e ON p.encounter_id = e.id
            JOIN `patients` pt ON e.patient_id = pt.id
            WHERE p.id = ?
        ");
        $rxStmt->execute([$rxId]);
        $rx = $rxStmt->fetch(PDO::FETCH_ASSOC);

        if (!$rx) {
            set_flash('danger', 'Prescription record not found.');
            header("Location: index.php?page=pharmacy&tab=queue");
            exit;
        }

        if ($rx['status'] === 'Dispensed') {
            set_flash('warning', 'Prescription #' . $rx['prescription_number'] . ' has already been dispensed.');
            header("Location: index.php?page=pharmacy&tab=queue&print_receipt=" . $rxId);
            exit;
        }

        // Fetch prescription items
        $itemsStmt = $db->prepare("
            SELECT pi.*, di.id AS drug_id, di.brand_name, di.generic_name, di.dosage_form
            FROM `prescription_items` pi
            JOIN `drug_items` di ON pi.drug_item_id = di.id
            WHERE pi.prescription_id = ?
        ");
        $itemsStmt->execute([$rxId]);
        $rxItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rxItems)) {
            set_flash('danger', 'No items found in this prescription.');
            header("Location: index.php?page=pharmacy&tab=queue");
            exit;
        }

        try {
            $db->beginTransaction();

            $totalSaleAmount = 0.00;
            $dispensedBatchAllocations = [];

            // 1. Process each medication item via FEFO
            foreach ($rxItems as $item) {
                $qtyNeeded = (int)($item['quantity_prescribed'] ?? 1);
                $qtyFulfilled = 0;

                // Find active batches sorted by FEFO (expiry_date ASC, id ASC)
                $batchStmt = $db->prepare("
                    SELECT * FROM `drug_batches`
                    WHERE `drug_item_id` = ? AND `current_quantity` > 0 AND `status` = 'Active'
                    ORDER BY `expiry_date` ASC, `id` ASC
                ");
                $batchStmt->execute([$item['drug_id']]);
                $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($batches as $batch) {
                    if ($qtyFulfilled >= $qtyNeeded) {
                        break;
                    }

                    $availableInBatch = (int)$batch['current_quantity'];
                    $takeQty = min($availableInBatch, ($qtyNeeded - $qtyFulfilled));
                    $unitPrice = (float)$batch['unit_sale_price'];
                    $lineTotal = $takeQty * $unitPrice;

                    // Deduct from batch
                    $newBatchQty = $availableInBatch - $takeQty;
                    $newStatus = ($newBatchQty <= 0) ? 'Depleted' : 'Active';
                    $updBatch = $db->prepare("UPDATE `drug_batches` SET `current_quantity` = ?, `status` = ? WHERE `id` = ?");
                    $updBatch->execute([$newBatchQty, $newStatus, $batch['id']]);

                    // Sync central inventory_items if matching item exists
                    if (!empty($item['brand_name']) || !empty($item['generic_name'])) {
                        $updInv = $db->prepare("
                            UPDATE `inventory_items` 
                            SET `current_stock` = GREATEST(0, current_stock - ?)
                            WHERE `item_name` LIKE ? OR `generic_name` LIKE ?
                            LIMIT 1
                        ");
                        $updInv->execute([$takeQty, '%' . $item['brand_name'] . '%', '%' . $item['generic_name'] . '%']);
                    }

                    $dispensedBatchAllocations[] = [
                        'drug_item_id' => $item['drug_id'],
                        'brand_name' => $item['brand_name'],
                        'dosage' => $item['dosage'],
                        'batch_id' => $batch['id'],
                        'batch_number' => $batch['batch_number'],
                        'expiry_date' => $batch['expiry_date'],
                        'quantity' => $takeQty,
                        'unit_price' => $unitPrice,
                        'total_price' => $lineTotal
                    ];

                    $qtyFulfilled += $takeQty;
                    $totalSaleAmount += $lineTotal;
                }

                // If no batch had stock, fallback to default price for billing
                if ($qtyFulfilled == 0) {
                    $fallbackPrice = 50.00;
                    $lineTotal = $qtyNeeded * $fallbackPrice;
                    $dispensedBatchAllocations[] = [
                        'drug_item_id' => $item['drug_id'],
                        'brand_name' => $item['brand_name'],
                        'dosage' => $item['dosage'],
                        'batch_id' => null,
                        'batch_number' => 'STANDARD',
                        'expiry_date' => null,
                        'quantity' => $qtyNeeded,
                        'unit_price' => $fallbackPrice,
                        'total_price' => $lineTotal
                    ];
                    $qtyFulfilled = $qtyNeeded;
                    $totalSaleAmount += $lineTotal;
                }

                // Update quantity_dispensed on prescription_items
                $updItem = $db->prepare("UPDATE `prescription_items` SET `quantity_dispensed` = ? WHERE `id` = ?");
                $updItem->execute([$qtyFulfilled, $item['id']]);
            }

            // 2. Generate Pharmacy Sale Record
            $saleCount = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `pharmacy_sales`")->fetchColumn();
            $saleInvoiceNum = 'PH-INV-' . date('Ymd') . '-' . str_pad((string)($saleCount + 1), 4, '0', STR_PAD_LEFT);

            $saleStmt = $db->prepare("
                INSERT INTO `pharmacy_sales` 
                (`sale_invoice_number`, `prescription_id`, `encounter_id`, `patient_id`, `customer_name`, `subtotal`, `discount_amount`, `tax_amount`, `net_total`, `payment_method`, `dispensed_by`, `created_at`)
                VALUES (?, ?, ?, ?, ?, ?, 0.00, 0.00, ?, 'Bill_to_Encounter', ?, NOW())
            ");
            $custName = $rx['first_name'] . ' ' . $rx['last_name'];
            $saleStmt->execute([$saleInvoiceNum, $rxId, $rx['enc_id'], $rx['patient_id'], $custName, $totalSaleAmount, $totalSaleAmount, Auth::id()]);
            $saleId = (int)$db->lastInsertId();

            // Insert pharmacy_sale_items
            $psiStmt = $db->prepare("
                INSERT INTO `pharmacy_sale_items` (`pharmacy_sale_id`, `drug_batch_id`, `quantity`, `unit_price`, `total_price`)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($dispensedBatchAllocations as $alloc) {
                if ($alloc['batch_id']) {
                    $psiStmt->execute([$saleId, $alloc['batch_id'], $alloc['quantity'], $alloc['unit_price'], $alloc['total_price']]);
                }
            }

            // 3. Automatic Invoicing Synchronization
            // Check if active bill exists for this encounter
            $chkBillStmt = $db->prepare("
                SELECT id, bill_number, gross_amount, net_amount, paid_amount, balance_amount 
                FROM `bills` 
                WHERE `encounter_id` = ? AND `payment_status` != 'Refunded' 
                ORDER BY `id` DESC LIMIT 1
            ");
            $chkBillStmt->execute([$rx['enc_id']]);
            $existingBill = $chkBillStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingBill) {
                $billId = (int)$existingBill['id'];
                $billNumber = $existingBill['bill_number'];

                $newGross = (float)$existingBill['gross_amount'] + $totalSaleAmount;
                $newNet = (float)$existingBill['net_amount'] + $totalSaleAmount;
                $paid = (float)$existingBill['paid_amount'];
                $newBal = $newNet - $paid;
                $newPayStatus = ($paid <= 0) ? 'Unpaid' : (($paid < $newNet) ? 'Partially-Paid' : 'Fully-Paid');

                $updBillStmt = $db->prepare("
                    UPDATE `bills` 
                    SET `gross_amount` = ?, `net_amount` = ?, `balance_amount` = ?, `payment_status` = ?
                    WHERE `id` = ?
                ");
                $updBillStmt->execute([$newGross, $newNet, $newBal, $newPayStatus, $billId]);
            } else {
                $billCount = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `bills`")->fetchColumn();
                $billNumber = 'INV-' . date('Y') . '-' . str_pad((string)($billCount + 1), 4, '0', STR_PAD_LEFT);
                $bType = in_array($rx['encounter_type'], ['OPD', 'IPD', 'Emergency']) ? $rx['encounter_type'] : 'OPD';

                $insBillStmt = $db->prepare("
                    INSERT INTO `bills` 
                    (`bill_number`, `encounter_id`, `patient_id`, `bill_type`, `bill_date`, `gross_amount`, `discount_amount`, `tax_amount`, `net_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_by`, `created_at`)
                    VALUES (?, ?, ?, ?, NOW(), ?, 0.00, 0.00, ?, 0.00, ?, 'Unpaid', ?, NOW())
                ");
                $insBillStmt->execute([$billNumber, $rx['enc_id'], $rx['patient_id'], $bType, $totalSaleAmount, $totalSaleAmount, $totalSaleAmount, Auth::id()]);
                $billId = (int)$db->lastInsertId();
            }

            // Insert medication line items into bill_items
            $billItemStmt = $db->prepare("
                INSERT INTO `bill_items` 
                (`bill_id`, `item_type`, `reference_id`, `description`, `quantity`, `unit_price`, `subtotal`, `discount`, `net_price`, `created_at`)
                VALUES (?, 'Medication', ?, ?, ?, ?, ?, 0.00, ?, NOW())
            ");

            foreach ($dispensedBatchAllocations as $alloc) {
                $itemDesc = "Pharmacy: " . $alloc['brand_name'] . " (" . $alloc['dosage'] . ") [Batch: " . $alloc['batch_number'] . ($alloc['expiry_date'] ? ", Exp: " . date('m/y', strtotime($alloc['expiry_date'])) : "") . "]";
                $billItemStmt->execute([
                    $billId,
                    $saleId,
                    $itemDesc,
                    $alloc['quantity'],
                    $alloc['unit_price'],
                    $alloc['total_price'],
                    $alloc['total_price']
                ]);
            }

            // 4. Mark Prescription as Dispensed
            $db->prepare("UPDATE `prescriptions` SET `status` = 'Dispensed' WHERE `id` = ?")->execute([$rxId]);

            // 5. Audit Logger
            AuditLogger::log('DISPENSE', 'prescriptions', $rxId, null, [
                'sale_invoice' => $saleInvoiceNum,
                'bill_number' => $billNumber,
                'total_amount' => $totalSaleAmount
            ]);

            $db->commit();

            set_flash('success', "Prescription {$rx['prescription_number']} dispensed! Stock deducted via FEFO, Pharmacy Sale $saleInvoiceNum recorded, and Invoice $billNumber synchronized.");
            header("Location: index.php?page=pharmacy&tab=queue&print_receipt=" . $rxId);
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Pharmacy dispensing failed: " . $e->getMessage());
            header("Location: index.php?page=pharmacy&tab=queue");
            exit;
        }
    }

    // 12. Point of Sale (POS) Sale Checkout Action
    if ($action === 'pos_sale_checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $customerType = clean_input($_POST['customer_type'] ?? 'walk_in');
            $patientId = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
            $prescriptionId = !empty($_POST['prescription_id']) ? (int)$_POST['prescription_id'] : null;
            $customerName = clean_input($_POST['customer_name'] ?? '');
            $customerPhone = clean_input($_POST['customer_phone'] ?? '');
            $paymentMethod = clean_input($_POST['payment_method'] ?? 'Cash');
            $paymentRef = clean_input($_POST['payment_reference'] ?? '');
            $discountAmount = (float)($_POST['discount_amount'] ?? 0.00);
            $taxAmount = (float)($_POST['tax_amount'] ?? 0.00);
            $amountTendered = (float)($_POST['amount_tendered'] ?? 0.00);
            $saleNotes = clean_input($_POST['sale_notes'] ?? '');
            
            $itemBatches = $_POST['item_batch_id'] ?? [];
            $itemQuantities = $_POST['item_quantity'] ?? [];
            $itemPrices = $_POST['item_unit_price'] ?? [];
            $itemInstructions = $_POST['item_dosage_instructions'] ?? [];

            if (empty($itemBatches) || !is_array($itemBatches)) {
                throw new Exception("Please add at least one medication item to the POS cart.");
            }

            // If registered patient selected, get patient name and active encounter if available
            $activeEncounterId = null;
            if ($patientId > 0) {
                $pRow = $db->prepare("SELECT first_name, last_name, phone_primary FROM `patients` WHERE `id` = ?");
                $pRow->execute([$patientId]);
                $pData = $pRow->fetch(PDO::FETCH_ASSOC);
                if ($pData) {
                    if (empty($customerName)) {
                        $customerName = $pData['first_name'] . ' ' . $pData['last_name'];
                    }
                    if (empty($customerPhone)) {
                        $customerPhone = $pData['phone_primary'];
                    }
                }

                // Check active encounter
                $encRow = $db->prepare("SELECT id, encounter_type FROM `encounters` WHERE `patient_id` = ? AND `encounter_status` = 'In-Progress' ORDER BY `id` DESC LIMIT 1");
                $encRow->execute([$patientId]);
                $encData = $encRow->fetch(PDO::FETCH_ASSOC);
                if ($encData) {
                    $activeEncounterId = (int)$encData['id'];
                }
            }

            if (empty($customerName)) {
                $customerName = 'Walk-in Customer';
            }

            // Validate items and calculate subtotal
            $subtotal = 0.00;
            $itemsToInsert = [];

            foreach ($itemBatches as $idx => $batchId) {
                $batchId = (int)$batchId;
                $qty = (int)($itemQuantities[$idx] ?? 0);
                $unitPrice = (float)($itemPrices[$idx] ?? 0.00);
                $instructions = clean_input($itemInstructions[$idx] ?? 'As directed by physician');

                if ($batchId <= 0 || $qty <= 0) {
                    continue;
                }

                // Lock batch record for stock deduction
                $batchStmt = $db->prepare("
                    SELECT db.*, di.id AS drug_id, di.brand_name, di.generic_name, di.dosage_form, di.strength, di.unit_of_measure
                    FROM `drug_batches` db
                    JOIN `drug_items` di ON db.drug_item_id = di.id
                    WHERE db.id = ? AND db.status = 'Active'
                    FOR UPDATE
                ");
                $batchStmt->execute([$batchId]);
                $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

                if (!$batch) {
                    throw new Exception("Selected medication batch is inactive or does not exist.");
                }

                if ($batch['current_quantity'] < $qty) {
                    throw new Exception("Insufficient stock for {$batch['brand_name']} (Batch {$batch['batch_number']}). Requested: $qty, Available: {$batch['current_quantity']}.");
                }

                $lineTotal = round($qty * $unitPrice, 2);
                $subtotal += $lineTotal;

                // Deduct batch stock
                $newQty = $batch['current_quantity'] - $qty;
                $newStatus = ($newQty == 0) ? 'Depleted' : 'Active';

                $updBatch = $db->prepare("UPDATE `drug_batches` SET `current_quantity` = ?, `status` = ? WHERE `id` = ?");
                $updBatch->execute([$newQty, $newStatus, $batchId]);

                // Sync central inventory_items if matching item exists
                $db->prepare("
                    UPDATE `inventory_items` 
                    SET `current_stock` = GREATEST(0, `current_stock` - ?) 
                    WHERE `item_name` LIKE ? OR `generic_name` LIKE ?
                ")->execute([$qty, '%' . $batch['brand_name'] . '%', '%' . $batch['generic_name'] . '%']);

                $itemsToInsert[] = [
                    'batch_id' => $batchId,
                    'drug_id' => $batch['drug_id'],
                    'brand_name' => $batch['brand_name'],
                    'generic_name' => $batch['generic_name'],
                    'dosage_form' => $batch['dosage_form'],
                    'strength' => $batch['strength'],
                    'batch_number' => $batch['batch_number'],
                    'expiry_date' => $batch['expiry_date'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'dosage_instructions' => $instructions
                ];
            }

            if (empty($itemsToInsert)) {
                throw new Exception("Please select valid items with quantity greater than 0.");
            }

            $netTotal = max(0, round($subtotal - $discountAmount + $taxAmount, 2));
            $changeAmount = ($amountTendered > $netTotal) ? round($amountTendered - $netTotal, 2) : 0.00;

            // Generate POS Invoice Number
            $saleCount = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `pharmacy_sales`")->fetchColumn();
            $saleInvoiceNum = 'POS-' . date('Ymd') . '-' . str_pad((string)($saleCount + 1), 4, '0', STR_PAD_LEFT);

            // Allowed payment methods
            $allowedMethods = ['Cash', 'Card', 'Bill_to_Encounter', 'Bank_Transfer', 'Mobile_Wallet'];
            if (!in_array($paymentMethod, $allowedMethods)) {
                $paymentMethod = 'Cash';
            }

            // Insert into pharmacy_sales
            $saleStmt = $db->prepare("
                INSERT INTO `pharmacy_sales`
                (`sale_invoice_number`, `prescription_id`, `encounter_id`, `patient_id`, `customer_name`, `customer_phone`, `subtotal`, `discount_amount`, `tax_amount`, `net_total`, `amount_tendered`, `change_amount`, `payment_method`, `payment_reference`, `sale_notes`, `dispensed_by`, `created_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $saleStmt->execute([
                $saleInvoiceNum,
                $prescriptionId,
                $activeEncounterId,
                $patientId,
                $customerName,
                $customerPhone,
                $subtotal,
                $discountAmount,
                $taxAmount,
                $netTotal,
                $amountTendered,
                $changeAmount,
                $paymentMethod,
                $paymentRef,
                $saleNotes,
                Auth::id()
            ]);
            $saleId = (int)$db->lastInsertId();

            // Insert items into pharmacy_sale_items with dosage_instructions
            $psiStmt = $db->prepare("
                INSERT INTO `pharmacy_sale_items` (`pharmacy_sale_id`, `drug_batch_id`, `quantity`, `unit_price`, `total_price`, `dosage_instructions`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($itemsToInsert as $it) {
                $psiStmt->execute([$saleId, $it['batch_id'], $it['quantity'], $it['unit_price'], $it['total_price'], $it['dosage_instructions']]);
            }

            // If a doctor prescription was linked, mark as Dispensed
            if ($prescriptionId > 0) {
                $db->prepare("UPDATE `prescriptions` SET `status` = 'Dispensed' WHERE `id` = ?")->execute([$prescriptionId]);
            }

            // If registered patient with active encounter or bill-to-encounter selected:
            if ($patientId && ($activeEncounterId || $paymentMethod === 'Bill_to_Encounter')) {
                // Find or create bill
                $chkBill = $db->prepare("SELECT id, bill_number, gross_amount, net_amount, paid_amount, balance_amount FROM `bills` WHERE `patient_id` = ? AND `payment_status` != 'Refunded' ORDER BY `id` DESC LIMIT 1");
                $chkBill->execute([$patientId]);
                $existingBill = $chkBill->fetch(PDO::FETCH_ASSOC);

                if ($existingBill) {
                    $billId = (int)$existingBill['id'];
                    $billNumber = $existingBill['bill_number'];
                    $newGross = (float)$existingBill['gross_amount'] + $netTotal;
                    $newNet = (float)$existingBill['net_amount'] + $netTotal;
                    $paid = (float)$existingBill['paid_amount'];
                    if ($paymentMethod !== 'Bill_to_Encounter') {
                        $paid += $netTotal; // Paid at POS counter
                    }
                    $newBal = max(0, $newNet - $paid);
                    $newPayStatus = ($paid <= 0) ? 'Unpaid' : (($paid < $newNet) ? 'Partially-Paid' : 'Fully-Paid');

                    $db->prepare("UPDATE `bills` SET `gross_amount` = ?, `net_amount` = ?, `paid_amount` = ?, `balance_amount` = ?, `payment_status` = ? WHERE `id` = ?")
                       ->execute([$newGross, $newNet, $paid, $newBal, $newPayStatus, $billId]);
                } else {
                    $billCount = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `bills`")->fetchColumn();
                    $billNumber = 'INV-' . date('Y') . '-' . str_pad((string)($billCount + 1), 4, '0', STR_PAD_LEFT);
                    $paid = ($paymentMethod !== 'Bill_to_Encounter') ? $netTotal : 0.00;
                    $bal = max(0, $netTotal - $paid);
                    $payStatus = ($paid >= $netTotal) ? 'Fully-Paid' : 'Unpaid';

                    $db->prepare("
                        INSERT INTO `bills` (`bill_number`, `encounter_id`, `patient_id`, `bill_type`, `bill_date`, `gross_amount`, `discount_amount`, `tax_amount`, `net_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_by`, `created_at`)
                        VALUES (?, ?, ?, 'OPD', NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ")->execute([$billNumber, $activeEncounterId, $patientId, $subtotal, $discountAmount, $taxAmount, $netTotal, $paid, $bal, $payStatus, Auth::id()]);
                    $billId = (int)$db->lastInsertId();
                }

                // Insert line items
                $biStmt = $db->prepare("
                    INSERT INTO `bill_items` (`bill_id`, `item_type`, `reference_id`, `description`, `quantity`, `unit_price`, `subtotal`, `discount`, `net_price`, `created_at`)
                    VALUES (?, 'Medication', ?, ?, ?, ?, ?, 0.00, ?, NOW())
                ");
                foreach ($itemsToInsert as $it) {
                    $desc = "POS Pharmacy: " . $it['brand_name'] . " (" . $it['dosage_form'] . ") [Batch: " . $it['batch_number'] . ", Exp: " . date('m/y', strtotime($it['expiry_date'])) . "]";
                    $biStmt->execute([$billId, $saleId, $desc, $it['quantity'], $it['unit_price'], $it['total_price'], $it['total_price']]);
                }

                // If cash/card payment paid at counter for registered patient:
                if ($paymentMethod !== 'Bill_to_Encounter') {
                    $rcptCount = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `payments`")->fetchColumn();
                    $receiptNum = 'REC-' . date('Ymd') . '-' . str_pad((string)($rcptCount + 1), 4, '0', STR_PAD_LEFT);
                    $pMethodEnum = in_array($paymentMethod, ['Cash', 'Bank_Transfer']) ? $paymentMethod : ($paymentMethod === 'Card' ? 'Debit_Card' : 'Cash');
                    
                    $db->prepare("
                        INSERT INTO `payments` (`receipt_number`, `bill_id`, `patient_id`, `amount_paid`, `payment_method`, `transaction_reference`, `payment_datetime`, `received_by`)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                    ")->execute([$receiptNum, $billId, $patientId, $netTotal, $pMethodEnum, $saleInvoiceNum, Auth::id()]);
                }
            }

            // Audit Log
            AuditLogger::log('POS_SALE', 'pharmacy_sales', $saleId, null, [
                'invoice' => $saleInvoiceNum,
                'net_total' => $netTotal,
                'customer' => $customerName,
                'items_count' => count($itemsToInsert)
            ]);

            $db->commit();

            set_flash('success', "POS Sale $saleInvoiceNum completed successfully! Total: Rs. " . number_format($netTotal, 2));
            header("Location: index.php?page=dashboard&pos_sale_id=" . $saleId . "#dashboard-pos");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "POS Transaction Failed: " . $e->getMessage());
            header("Location: index.php?page=dashboard#dashboard-pos");
            exit;
        }
    }

    // Default fallback if action unknown
    header("Location: index.php?page=dashboard");
    exit;
}

// ----------------------------------------------------------------------------
// PAGE VIEW DISPATCHER
// ----------------------------------------------------------------------------

if ($page === 'login') {
    if (Auth::check()) {
        if (Auth::isDeveloper()) {
            header("Location: index.php?page=developer_dashboard");
        } else {
            header("Location: index.php?page=dashboard");
        }
        exit;
    }
    require_once __DIR__ . '/views/auth/login.php';
    exit;
}

if ($page === 'forgot_password') {
    if (Auth::check()) {
        header("Location: index.php?page=dashboard");
        exit;
    }
    require_once __DIR__ . '/views/auth/forgot_password.php';
    exit;
}

if ($page === 'register_hospital') {
    if (Auth::check()) {
        header("Location: index.php?page=dashboard");
        exit;
    }
    require_once __DIR__ . '/views/auth/register_hospital.php';
    exit;
}

if ($page === 'registration_status') {
    require_once __DIR__ . '/views/auth/registration_status.php';
    exit;
}

if ($page === 'print_credentials') {
    Auth::requireLogin();
    Auth::requireRole(['super_admin', 'hospital_admin']);
    require_once __DIR__ . '/views/admin/print_credentials.php';
    exit;
}

if ($page === 'print_pos_receipt') {
    Auth::requireLogin();
    require_once __DIR__ . '/views/pos/print_receipt.php';
    exit;
}

// All inner pages require authentication
Auth::requireLogin();

// Map route to view files, titles, and allowed RBAC roles
$routes = [
    'dashboard' => [
        'file' => 'views/dashboard/index.php', 
        'title' => 'Executive & Clinical Dashboard',
        'roles' => ['super_admin', 'hospital_admin', 'doctor', 'nurse', 'pharmacist', 'lab_technician', 'radiologist', 'cashier', 'receptionist']
    ],
    'patients' => [
        'file' => 'views/patients/index.php', 
        'title' => 'Patient Directory (Master Patient Index)',
        'roles' => ['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse', 'cashier']
    ],
    'patient_create' => [
        'file' => 'views/patients/create.php', 
        'title' => 'Patient Registration',
        'roles' => ['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse', 'cashier']
    ],
    'patient_profile' => [
        'file' => 'views/patients/view.php', 
        'title' => 'Patient 360° Comprehensive Profile',
        'roles' => ['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse', 'cashier', 'pharmacist', 'lab_technician', 'radiologist']
    ],
    'appointments' => [
        'file' => 'views/appointments/index.php', 
        'title' => 'Appointments & Token Scheduling',
        'roles' => ['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse']
    ],
    'emergency' => [
        'file' => 'views/emergency/index.php', 
        'title' => 'Emergency & Trauma Care (ER)',
        'roles' => ['super_admin', 'hospital_admin', 'doctor', 'nurse', 'receptionist']
    ],
    'opd_consultation' => [
        'file' => 'views/opd/consultation.php', 
        'title' => 'Clinician Workbench (OPD)',
        'roles' => ['super_admin', 'hospital_admin', 'doctor', 'nurse', 'receptionist']
    ],
    'ipd_beds' => [
        'file' => 'views/ipd/beds.php', 
        'title' => 'Wards & Interactive Bed Rack (IPD)',
        'roles' => ['super_admin', 'hospital_admin', 'nurse', 'doctor', 'receptionist']
    ],
    'ot' => [
        'file' => 'views/ot/index.php', 
        'title' => 'Operation Theatre (OT) Suites',
        'roles' => ['super_admin', 'hospital_admin', 'doctor', 'nurse']
    ],
    'pharmacy' => [
        'file' => 'views/pharmacy/index.php', 
        'title' => 'Pharmacy & Dispensing Counter',
        'roles' => ['super_admin', 'hospital_admin', 'pharmacist', 'doctor']
    ],
    'laboratory' => [
        'file' => 'views/laboratory/index.php', 
        'title' => 'Clinical Pathology & Laboratory',
        'roles' => ['super_admin', 'hospital_admin', 'lab_technician', 'doctor']
    ],
    'radiology' => [
        'file' => 'views/radiology/index.php', 
        'title' => 'Radiology PACS & Imaging',
        'roles' => ['super_admin', 'hospital_admin', 'radiologist', 'doctor', 'lab_technician', 'nurse', 'receptionist']
    ],
    'blood_bank' => [
        'file' => 'views/blood_bank/index.php', 
        'title' => 'Blood Bank & Transfusion Medicine',
        'roles' => ['super_admin', 'hospital_admin', 'doctor', 'lab_technician', 'nurse']
    ],
    'billing' => [
        'file' => 'views/billing/index.php', 
        'title' => 'Centralized Billing & Cashier Desk',
        'roles' => ['super_admin', 'hospital_admin', 'cashier']
    ],
    'insurance' => [
        'file' => 'views/insurance/index.php', 
        'title' => 'Insurance & Corporate TPA Claims',
        'roles' => ['super_admin', 'hospital_admin', 'cashier', 'receptionist']
    ],
    'inventory' => [
        'file' => 'views/inventory/index.php', 
        'title' => 'Central Inventory & Procurement',
        'roles' => ['super_admin', 'hospital_admin', 'pharmacist']
    ],
    'reports' => [
        'file' => 'views/reports/index.php', 
        'title' => 'Reports & Operational Analytics',
        'roles' => ['super_admin', 'hospital_admin', 'cashier']
    ],
    'platform_dashboard' => [
        'file' => 'views/admin/platform_dashboard.php',
        'title' => 'Platform Super Admin Console',
        'roles' => ['platform_admin']
    ],
    'platform_hospitals' => [
        'file' => 'views/admin/platform_hospitals.php',
        'title' => 'Hospital Facilities & Tenant Directory',
        'roles' => ['platform_admin']
    ],
    'hospital_customization' => [
        'file' => 'views/admin/hospital_customization.php',
        'title' => 'Hospital Customization & Branding',
        'roles' => ['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']
    ],
    'settings' => [
        'file' => 'views/admin/settings.php', 
        'title' => 'Hospital Masters & Dynamic Settings',
        'roles' => ['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']
    ],
    'users' => [
        'file' => 'views/admin/users.php', 
        'title' => 'Hospital Staff & System Accounts',
        'roles' => ['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']
    ],
    'audit_logs' => [
        'file' => 'views/admin/audit_logs.php', 
        'title' => 'Forensic Security & Audit Trail',
        'roles' => ['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin']
    ],
    'developer_dashboard' => [
        'file' => 'views/developer/dashboard.php',
        'title' => 'Platform Developer / Telemetry & Health',
        'roles' => ['developer', 'platform_admin']
    ],
    'developer_registrations' => [
        'file' => 'views/developer/registrations.php',
        'title' => 'Hospital Registration Review',
        'roles' => ['developer', 'platform_admin']
    ],
    'developer_hospitals' => [
        'file' => 'views/developer/hospitals.php',
        'title' => 'Multi-Hospital Platform Directory',
        'roles' => ['developer', 'platform_admin']
    ],
    'developer_profile' => [
        'file' => 'views/developer/profile.php',
        'title' => 'Developer Security & Master Account',
        'roles' => ['developer', 'platform_admin']
    ],
    'attendance' => [
        'file' => 'views/attendance/index.php',
        'title' => 'Staff Attendance & Geofenced Time Clock',
        'roles' => ['developer', 'platform_admin', 'super_admin', 'superadmin', 'hospital_admin', 'admin', 'hospital_owner', 'doctor', 'nurse', 'pharmacist', 'lab_technician', 'radiologist', 'cashier', 'receptionist', 'hr', 'medical_director']
    ],
];

// If platform developer, default to developer_dashboard
if (Auth::isDeveloper() && (!isset($_GET['page']) || $_GET['page'] === 'dashboard')) {
    $page = 'developer_dashboard';
}

// If platform admin and no hospital context active, default to platform dashboard
if (Auth::isPlatformAdmin() && empty($_SESSION['platform_active_hospital_id']) && (!isset($_GET['page']) || $_GET['page'] === 'dashboard')) {
    $page = 'platform_dashboard';
}

if (!isset($routes[$page])) {
    if (Auth::isDeveloper()) {
        $page = 'developer_dashboard';
    } elseif (Auth::isPlatformAdmin() && empty($_SESSION['platform_active_hospital_id'])) {
        $page = 'platform_dashboard';
    } else {
        $page = 'dashboard';
    }
}

$targetRoute = $routes[$page];
$pageTitle = $targetRoute['title'];

// Enforce strict Role-Based Access Control (RBAC) per workspace
if (!empty($targetRoute['roles'])) {
    Auth::requireRole($targetRoute['roles']);
}

// Render Layout & View
require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar.php';
echo '<div class="flex-1 flex flex-col h-full w-full min-w-0 overflow-hidden">';
require_once __DIR__ . '/views/layouts/topbar.php';
require_once __DIR__ . '/' . $targetRoute['file'];
echo '</div>';
require_once __DIR__ . '/views/layouts/footer.php';
