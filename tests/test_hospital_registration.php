<?php
/**
 * Test Suite: Public Hospital Registration Portal, Developer Review & Tenant Provisioning
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Tenant.php';
require_once __DIR__ . '/../core/RegistrationService.php';

$passed = 0;
$failed = 0;

function assertReg($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $description\n";
        $passed++;
    } else {
        echo "[FAIL] $description\n";
        $failed++;
    }
}

echo "=== 1. TESTING LOGIN PAGE REGISTRATION ACCESS ===\n";
$loginHtml = file_get_contents(__DIR__ . '/../views/auth/login.php');
assertReg(strpos($loginHtml, 'Register Your Hospital') !== false, "Login page contains prominent 'Register Your Hospital' button");
assertReg(strpos($loginHtml, 'page=register_hospital') !== false, "Register button links to index.php?page=register_hospital");
assertReg(strpos($loginHtml, 'page=registration_status') !== false, "Login page contains link to check registration status");
assertReg(strpos($loginHtml, 'action=login') !== false, "Existing login action is completely intact");

echo "\n=== 2. TESTING PUBLIC REGISTRATION SUBMISSION ===\n";
$db = Database::getConnection();

// Unique test email and hospital name
$uniq = rand(1000, 9999);
$testEmail = "applicant_{$uniq}@testmed.com";
$testHosp = "Apex Test Hospital {$uniq}";

$subData = [
    'applicant_name' => "Dr. Tariq Test {$uniq}",
    'applicant_email' => $testEmail,
    'applicant_phone' => "+92 300 987{$uniq}",
    'applicant_designation' => 'Chief of Surgery',
    'password' => 'OwnerSecure@2026!',
    'confirm_password' => 'OwnerSecure@2026!',
    'hospital_name' => $testHosp,
    'hospital_type' => 'Specialized Clinic',
    'license_number' => "TEST-LIC-{$uniq}",
    'address' => 'Plot 45, Medical Enclave, Sector G-10',
    'city' => 'Peshawar',
    'district' => 'KPK',
    'country' => 'Pakistan',
    'bed_count' => 35,
    'currency_symbol' => 'PKR ',
    'notes' => 'Testing automated tenant onboarding workflow.'
];

$res = RegistrationService::submitRequest($subData);
if (!$res['success']) {
    echo "SUBMIT ERROR: " . ($res['message'] ?? 'Unknown error') . "\n";
}
assertReg($res['success'] === true, "Public registration request submitted successfully");
assertReg(!empty($res['tracking_code']) && strpos($res['tracking_code'], 'REG-') === 0, "Tracking code generated in format REG-YYYYMM-XXXX ({$res['tracking_code']})");

$requestId = $res['request_id'];
$trackingCode = $res['tracking_code'];

// Check database record
$reqRecord = RegistrationService::getRequestByTracking($trackingCode, $testEmail);
assertReg($reqRecord !== null, "Registration record retrieved from database");
assertReg($reqRecord['status'] === 'Pending Approval', "Initial status is 'Pending Approval'");
assertReg(password_verify('OwnerSecure@2026!', $reqRecord['password_hash']), "Applicant password securely hashed with bcrypt");

// Check Developer In-App Notification
$notifStmt = $db->prepare("SELECT * FROM `platform_notifications` WHERE reference_type = 'hospital_registration' AND reference_id = ? ORDER BY id DESC LIMIT 1");
$notifStmt->execute([$requestId]);
$notif = $notifStmt->fetch(PDO::FETCH_ASSOC);
assertReg($notif !== false, "In-app notification generated for Platform Developer");
assertReg(strpos($notif['title'], $testHosp) !== false, "Notification references hospital name");

// Check Duplicate Detection
$dupRes = RegistrationService::submitRequest($subData);
assertReg($dupRes['success'] === false, "Duplicate hospital submission rejected by validation");

echo "\n=== 3. TESTING STATUS TRACKING & CLARIFICATION WORKFLOW ===\n";
// Lookup without matching email fails or prevents data leak
$mismatchLookup = RegistrationService::getRequestByTracking($trackingCode, 'wrong_email@hospital.com');
assertReg($mismatchLookup === null, "Status lookup with mismatched email is blocked (Privacy protected)");

// Developer requests more info
$devUser = $db->query("SELECT id FROM users WHERE role_id = 10 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$devId = $devUser ? (int)$devUser['id'] : 1;

$moreInfoRes = RegistrationService::requestMoreInfo($requestId, "Please supply official PMC / Healthcare Commission certificate.", $devId);
assertReg($moreInfoRes['success'] === true, "Developer can request additional information");

$reqAfterInfo = RegistrationService::getRequestByTracking($trackingCode, $testEmail);
assertReg($reqAfterInfo['status'] === 'Additional Information Required', "Status updated to 'Additional Information Required'");
assertReg(strpos($reqAfterInfo['developer_notes'], 'Healthcare Commission') !== false, "Developer inquiry notes recorded");

// Applicant replies with clarification
$replyRes = RegistrationService::updateApplicantInfo($trackingCode, $testEmail, "Uploaded license registration copy #REG-98172.");
assertReg($replyRes['success'] === true, "Applicant clarification reply submitted");

$reqAfterReply = RegistrationService::getRequestByTracking($trackingCode, $testEmail);
assertReg($reqAfterReply['status'] === 'Under Review', "Status transitions to 'Under Review' after clarification");
assertReg(!empty($reqAfterReply['applicant_reply']), "Applicant reply recorded in database");

echo "\n=== 4. TESTING DEVELOPER APPROVAL & TENANT PROVISIONING ===\n";
$apprRes = RegistrationService::approveRequest($requestId, $devId);
if (!$apprRes['success']) {
    echo "APPROVE ERROR: " . ($apprRes['message'] ?? 'Unknown error') . "\n";
}
assertReg($apprRes['success'] === true, "Developer approves registration request");
assertReg(!empty($apprRes['hospital_id']) && $apprRes['hospital_id'] > 0, "New hospital tenant provisioned with ID {$apprRes['hospital_id']}");
assertReg(!empty($apprRes['user_id']) && $apprRes['user_id'] > 0, "Hospital Super Admin account created with ID {$apprRes['user_id']}");

$newHospId = $apprRes['hospital_id'];
$newUserId = $apprRes['user_id'];

// Verify created hospital profile
$hProf = $db->query("SELECT * FROM `hospital_profiles` WHERE id = {$newHospId}")->fetch(PDO::FETCH_ASSOC);
assertReg($hProf !== false, "Hospital profile exists in database");
assertReg($hProf['hospital_name'] === $testHosp, "Hospital profile has correct official name");
assertReg($hProf['status'] === 'Active', "Hospital profile status is 'Active'");

// Verify created owner user account
$oUser = $db->query("SELECT * FROM `users` WHERE id = {$newUserId}")->fetch(PDO::FETCH_ASSOC);
assertReg($oUser !== false, "Owner user account exists in database");
assertReg($oUser['hospital_id'] == $newHospId, "Owner user is assigned to newly created hospital_id");
assertReg($oUser['status'] === 'Active', "Owner user status is 'Active'");
assertReg(password_verify('OwnerSecure@2026!', $oUser['password_hash']), "Owner user can log in with their submitted password");

// Verify essential departments seeded
$deptCount = (int)$db->query("SELECT COUNT(*) FROM `departments` WHERE hospital_id = {$newHospId}")->fetchColumn();
assertReg($deptCount >= 4, "Essential departments seeded for new hospital ($deptCount departments)");

// Verify request record updated to Approved
$reqFinal = RegistrationService::getRequestByTracking($trackingCode, $testEmail);
assertReg($reqFinal['status'] === 'Approved', "Registration request marked 'Approved'");
assertReg($reqFinal['created_hospital_id'] == $newHospId, "Registration request linked to created hospital_id");

// Verify duplicate approval prevented
$dupAppr = RegistrationService::approveRequest($requestId, $devId);
assertReg($dupAppr['success'] === false, "Duplicate approval action safely prevented");

// Clean up test data
$db->prepare("DELETE FROM `departments` WHERE hospital_id = ?")->execute([$newHospId]);
$db->prepare("DELETE FROM `users` WHERE id = ?")->execute([$newUserId]);
$db->prepare("DELETE FROM `hospital_profiles` WHERE id = ?")->execute([$newHospId]);
$db->prepare("DELETE FROM `platform_notifications` WHERE reference_type = 'hospital_registration' AND reference_id = ?")->execute([$requestId]);
$db->prepare("DELETE FROM `hospital_registration_requests` WHERE id = ?")->execute([$requestId]);

echo "\n=======================================================\n";
echo "SUMMARY: Passed: $passed | Failed: $failed\n";
if ($failed === 0) {
    echo "ALL HOSPITAL REGISTRATION TESTS PASSED PERFECTLY!\n";
} else {
    echo "SOME TESTS FAILED - PLEASE REVIEW!\n";
}
