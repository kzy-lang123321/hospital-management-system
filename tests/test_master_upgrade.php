<?php
/**
 * Master Verification Suite for Multi-Hospital Platform & Developer System
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Tenant.php';
require_once __DIR__ . '/../core/Telemetry.php';
require_once __DIR__ . '/../core/AttendanceService.php';

$passed = 0;
$failed = 0;

function assertTest($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $description\n";
        $passed++;
    } else {
        echo "[FAIL] $description\n";
        $failed++;
    }
}

echo "=== 1. TESTING DEVELOPER AUTHENTICATION & SECURITY ===\n";
$db = Database::getConnection();

// Verify role exists
$rStmt = $db->query("SELECT id, role_name, slug FROM `roles` WHERE id = 10");
$role10 = $rStmt->fetch(PDO::FETCH_ASSOC);
assertTest($role10 && $role10['slug'] === 'developer', "Role ID 10 exists with slug 'developer'");

// Verify Developer User account
$uStmt = $db->query("SELECT id, username, email, password_hash, role_id, hospital_id FROM `users` WHERE email = 'khanabbasi5642@gmail.com'");
$devUser = $uStmt->fetch(PDO::FETCH_ASSOC);
assertTest($devUser !== false, "Developer user 'khanabbasi5642@gmail.com' exists in database");
assertTest($devUser['role_id'] == 10, "Developer user has role_id 10");
assertTest(empty($devUser['hospital_id']), "Developer has hospital_id NULL (Root Platform Scope)");

// Verify Developer Password Hash
$passVerify = password_verify('Developer@2026!', $devUser['password_hash']);
assertTest($passVerify === true, "Developer password 'Developer@2026!' verifies successfully with bcrypt");

// Simulate Developer Session & RBAC
$_SESSION['user'] = [
    'id' => $devUser['id'],
    'username' => $devUser['username'],
    'email' => $devUser['email'],
    'full_name' => 'Platform Developer',
    'role_id' => 10,
    'role' => 'developer',
    'role_slug' => 'developer',
    'role_name' => 'Platform Developer',
    'hospital_id' => null
];

assertTest(Auth::isDeveloper() === true, "Auth::isDeveloper() returns true for developer session");
assertTest(Auth::hasRole('developer') === true, "Auth::hasRole('developer') returns true");
assertTest(Auth::hasRole(['developer', 'platform_admin']) === true, "Auth::hasRole(['developer', 'platform_admin']) returns true");

echo "\n=== 2. TESTING STRICT DEVELOPER PRIVACY BARRIER ===\n";
// Developers must NOT have clinical or cashier roles
assertTest(Auth::hasRole('doctor') === false, "Developer has NO 'doctor' clinical role");
assertTest(Auth::hasRole('nurse') === false, "Developer has NO 'nurse' clinical role");
assertTest(Auth::hasRole('cashier') === false, "Developer has NO 'cashier' billing role");
assertTest(Auth::hasRole('receptionist') === false, "Developer has NO 'receptionist' role");

// Verify Tenant authorization barrier
assertTest(Tenant::isAuthorizedFor(1) === false, "Tenant::isAuthorizedFor(1) physically blocks developer from clinical records");

echo "\n=== 3. TESTING PRIVACY-SAFE TELEMETRY SERVICE ===\n";
$summary = Telemetry::getPlatformSummary();
assertTest(isset($summary['total_hospitals']) && $summary['total_hospitals'] >= 1, "Platform summary returns total_hospitals >= 1");
assertTest(isset($summary['active_hospitals']), "Platform summary returns active_hospitals");
assertTest(isset($summary['total_staff']) && $summary['total_staff'] >= 1, "Platform summary returns registered staff count");

$deptActivity = Telemetry::getDepartmentActivityTable();
assertTest(count($deptActivity) === 6, "Telemetry returns exactly 6 aggregate department categories");
$categories = array_column($deptActivity, 'department');
assertTest(in_array('Reception (Front Desk)', $categories), "Includes Reception category");
assertTest(in_array('Clinical (Doctor & Nursing)', $categories), "Includes Clinical Encounters category");
assertTest(in_array('Hospital Pharmacy', $categories), "Includes Pharmacy category");
assertTest(in_array('Clinical Pathology & Laboratory', $categories), "Includes Laboratory category");

// Verify no PHI is leaked in telemetry
$sampleEvent = json_encode($deptActivity);
assertTest(strpos($sampleEvent, 'diagnosis') === false, "Telemetry contains NO diagnosis fields");
assertTest(strpos($sampleEvent, 'patient_name') === false, "Telemetry contains NO patient names");
assertTest(strpos($sampleEvent, 'mrn') === false, "Telemetry contains NO MRNs");

$techHealth = Telemetry::getSanitizedTechnicalHealth();
assertTest($techHealth['database_status'] === 'Operational', "Technical health reports database Operational");
assertTest(isset($techHealth['php_version']), "Technical health returns sanitized PHP version");

echo "\n=== 4. TESTING LOCATION-VERIFIED STAFF ATTENDANCE ENGINE ===\n";
// Test Haversine distance
$hLat = 34.01513700;
$hLon = 71.52491500;

// Same coordinate = 0 distance
$distZero = AttendanceService::calculateDistance($hLat, $hLon, $hLat, $hLon);
assertTest($distZero == 0.0, "Haversine distance for identical coordinates is 0.0m");

// Slight offset (~50 meters away)
$distNear = AttendanceService::calculateDistance($hLat, $hLon, $hLat + 0.0003, $hLon);
assertTest($distNear > 0 && $distNear < 100, "Haversine distance for near coordinate is ~33m ($distNear m)");

// Far offset (~10km away)
$distFar = AttendanceService::calculateDistance($hLat, $hLon, $hLat + 0.1, $hLon);
assertTest($distFar > 5000, "Haversine distance for far coordinate is > 5km ($distFar m)");

// Test Check-In Logic for a staff member (User 1 - Admin)
$adminUser = $db->query("SELECT id, hospital_id FROM users WHERE role_id != 10 AND hospital_id IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($adminUser) {
    // Clear any test attendance for today
    $db->prepare("DELETE FROM staff_attendance WHERE user_id = ? AND attendance_date = ?")->execute([$adminUser['id'], date('Y-m-d')]);

    // Check in within perimeter
    $inRes = AttendanceService::processCheckIn($adminUser['id'], $hLat, $hLon, 15.0, "Automated Verification Test");
    assertTest($inRes['success'] === true, "Staff Check-In succeeds within hospital coordinates");
    assertTest($inRes['status'] === 'Verified' || $inRes['status'] === 'Late', "Check-In status is Verified or Late depending on time of day");

    // Test Check-Out
    $outRes = AttendanceService::processCheckOut($adminUser['id'], $hLat, $hLon, 15.0);
    assertTest($outRes['success'] === true, "Staff Check-Out succeeds");
    assertTest(isset($outRes['total_hours']), "Total duty hours calculated on checkout");

    // Clean up test attendance
    $db->prepare("DELETE FROM staff_attendance WHERE id = ?")->execute([$inRes['attendance_id']]);
}

echo "\n=== 5. TESTING CLEAN LOGIN PAGE (NO QUICK TOOLS) ===\n";
$loginContent = file_get_contents(__DIR__ . '/../views/auth/login.php');
assertTest(strpos($loginContent, 'Quick Management Switcher') === false, "Quick Management Switcher heading is REMOVED");
assertTest(strpos($loginContent, 'quick-fill') === false, "Quick-fill demo buttons are REMOVED");
assertTest(strpos($loginContent, 'Admin@123') === false, "Hardcoded Admin@123 demo pill is REMOVED");
assertTest(strpos($loginContent, 'Doc@123') === false, "Hardcoded Doc@123 demo pill is REMOVED");
assertTest(strpos($loginContent, 'Nurse@123') === false, "Hardcoded Nurse@123 demo pill is REMOVED");
assertTest(strpos($loginContent, 'name="username"') !== false, "Username input field is preserved");
assertTest(strpos($loginContent, 'name="password"') !== false, "Password input field is preserved");
assertTest(strpos($loginContent, 'page=forgot_password') !== false, "Forgot password link is present");

echo "\n=======================================================\n";
echo "SUMMARY: Passed: $passed | Failed: $failed\n";
if ($failed === 0) {
    echo "ALL MASTER REQUIREMENTS VERIFIED SUCCESSFULLY!\n";
} else {
    echo "SOME TESTS FAILED - PLEASE REVIEW!\n";
}
