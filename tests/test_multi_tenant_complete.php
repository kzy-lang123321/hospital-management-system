<?php
/**
 * Automated Multi-Tenant & Multi-Hospital Verification Test Suite
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Tenant.php';

$db = Database::getConnection();

echo "====================================================\n";
echo "HMS Multi-Tenant & Multi-Hospital Verification Suite\n";
echo "====================================================\n\n";

$testCount = 0;
$passCount = 0;

function assert_test(string $name, bool $condition, string $details = '') {
    global $testCount, $passCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo " [PASS] $name\n";
    } else {
        echo " [FAIL] $name: $details\n";
    }
}

// ----------------------------------------------------------------------------
// Test 1: Verify Default Hospital #1 and Platform Admin User
// ----------------------------------------------------------------------------
$h1 = Tenant::profile(1);
assert_test("Default Hospital #1 exists", !empty($h1['id']) && $h1['id'] === 1, "Hospital 1 ID: " . ($h1['id'] ?? 'null'));
assert_test("Hospital #1 Name preserved", !empty($h1['hospital_name']), "Name: " . ($h1['hospital_name'] ?? ''));

$pAdmin = $db->query("SELECT u.*, r.slug FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.slug = 'platform_admin' LIMIT 1")->fetch();
assert_test("Platform Admin user exists with role platform_admin", !empty($pAdmin), "User: " . ($pAdmin['username'] ?? 'None'));

// ----------------------------------------------------------------------------
// Test 2: Atomic Creation of Hospital #2 via Tenant::registerHospital()
// ----------------------------------------------------------------------------
$testHospCode = 'TEST-' . rand(100, 999);
$testHospName = "City Medical Center ($testHospCode)";
$testOwnerUser = "owner_" . strtolower($testHospCode);
$testOwnerEmail = "$testOwnerUser@example.com";

$hData = [
    'hospital_name' => $testHospName,
    'hospital_code' => $testHospCode,
    'tagline' => 'Excellence in Tertiary Care',
    'city' => 'Islamabad',
    'address' => 'Blue Area, Jinnah Avenue',
    'emergency_hotline' => '051-998877',
    'mrn_prefix' => $testHospCode,
    'invoice_prefix' => 'INV-' . $testHospCode,
    'currency_symbol' => 'PKR ',
    'theme_color' => '#2563eb',
    'subscription_plan' => 'Enterprise'
];

$oData = [
    'username' => $testOwnerUser,
    'email' => $testOwnerEmail,
    'password' => 'HospitalOwner@123',
    'full_name' => 'Dr. Test Owner',
    'phone' => '0300-9998877',
    'designation' => 'Managing Director'
];

$newHospId = Tenant::registerHospital($hData, $oData);
assert_test("Tenant::registerHospital creates new hospital ID > 1", $newHospId > 1, "New ID: $newHospId");

// Check Hospital 2 Profile
$h2 = Tenant::profile($newHospId);
assert_test("Hospital #2 Profile retrieved correctly", $h2['hospital_name'] === $testHospName, "Retrieved: " . $h2['hospital_name']);
assert_test("Hospital #2 Theme color is customized (#2563eb)", $h2['theme_color'] === '#2563eb', "Color: " . $h2['theme_color']);
assert_test("Hospital #2 MRN prefix matches code", $h2['mrn_prefix'] === $testHospCode, "Prefix: " . $h2['mrn_prefix']);

// Check Hospital 2 Starter Departments
$depts = $db->prepare("SELECT COUNT(*) FROM `departments` WHERE hospital_id = ?");
$depts->execute([$newHospId]);
$deptCount = (int)$depts->fetchColumn();
assert_test("Hospital #2 has auto-seeded starter departments", $deptCount >= 10, "Dept count: $deptCount");

// ----------------------------------------------------------------------------
// Test 3: Hospital #2 Owner Authentication & Session Scoping
// ----------------------------------------------------------------------------
$authResult = Auth::attempt($testOwnerUser, 'HospitalOwner@123');
assert_test("Hospital #2 Owner can authenticate successfully", $authResult);
assert_test("Active Tenant ID matches Hospital #2", Tenant::id() === $newHospId, "Tenant ID: " . Tenant::id());
assert_test("User role is hospital_owner", Auth::role() === 'hospital_owner', "Role: " . Auth::role());
assert_test("Auth::isHospitalOwner() returns true", Auth::isHospitalOwner());

// ----------------------------------------------------------------------------
// Test 4: Dynamic MRN Generation for Hospital #2
// ----------------------------------------------------------------------------
$newMrn = generate_patient_mrn($db);
assert_test("MRN generated uses Hospital #2 custom prefix ($testHospCode)", str_starts_with($newMrn, $testHospCode . '-'), "Generated MRN: $newMrn");

// ----------------------------------------------------------------------------
// Test 5: Tenant Data Isolation
// ----------------------------------------------------------------------------
// Hospital #2 should see 0 patients initially
$patCountStmt = $db->prepare("SELECT COUNT(*) FROM `patients` WHERE hospital_id = ?");
$patCountStmt->execute([$newHospId]);
$h2PatientCount = (int)$patCountStmt->fetchColumn();
assert_test("Hospital #2 has 0 patients initially (isolated from Hospital #1)", $h2PatientCount === 0, "Count: $h2PatientCount");

// Create a patient under Hospital #2
$insPat = $db->prepare("
    INSERT INTO `patients` 
    (`hospital_id`, `mrn`, `first_name`, `last_name`, `gender`, `dob`, `age`, `blood_group`, `phone_primary`, `registered_by`, `status`)
    VALUES (?, ?, 'Isolated', 'Patient', 'Female', '1995-05-10', 31, 'O+', '0333-1112233', ?, 'Active')
");
$insPat->execute([$newHospId, $newMrn, Auth::id()]);
$h2PatientId = (int)$db->lastInsertId();
assert_test("Patient registered in Hospital #2 scope", $h2PatientId > 0, "Patient ID: $h2PatientId");

// Verify Hospital #1 still does NOT see Hospital #2's patient
$h1CountStmt = $db->prepare("SELECT COUNT(*) FROM `patients` WHERE hospital_id = 1 AND mrn = ?");
$h1CountStmt->execute([$newMrn]);
$h1SeesH2Pat = (int)$h1CountStmt->fetchColumn();
assert_test("Hospital #1 cannot see Hospital #2 patient in query scope", $h1SeesH2Pat === 0, "Found in H1: $h1SeesH2Pat");

// ----------------------------------------------------------------------------
// Test 6: Tenant Scope Security Enforcement (Tenant::isAuthorizedFor)
// ----------------------------------------------------------------------------
// While logged into Hospital #2, check if authorized for Hospital #1
$canAccessH1 = Tenant::isAuthorizedFor(1);
assert_test("Cross-tenant access correctly denied by security layer", !$canAccessH1, "Hospital 2 user accessing Hospital 1: " . ($canAccessH1 ? 'Allowed' : 'Denied'));

$canAccessH2 = Tenant::isAuthorizedFor($newHospId);
assert_test("Authorized for own hospital scope", $canAccessH2);

// ----------------------------------------------------------------------------
// Test 7: Platform Super Admin Context Switching
// ----------------------------------------------------------------------------
// Log in as platform admin
Auth::attempt('platform.admin', 'Platform@123');
assert_test("Platform Super Admin login successful", Auth::isPlatformAdmin());
assert_test("Platform Super Admin defaults to root platform scope", empty($_SESSION['platform_active_hospital_id']));

// Switch context to Hospital #2
$switchSuccess = Tenant::switchContext($newHospId);
assert_test("Platform Super Admin switches context to Hospital #2", $switchSuccess);
assert_test("Active Tenant ID is now Hospital #2", Tenant::id() === $newHospId, "Tenant ID: " . Tenant::id());

// Clear context
Tenant::clearPlatformContext();
assert_test("Platform context cleared successfully", empty($_SESSION['platform_active_hospital_id']));

// Clean up test hospital records
$db->prepare("DELETE FROM `patients` WHERE hospital_id = ?")->execute([$newHospId]);
$db->prepare("DELETE FROM `departments` WHERE hospital_id = ?")->execute([$newHospId]);
$db->prepare("DELETE FROM `wards` WHERE hospital_id = ?")->execute([$newHospId]);
$db->prepare("DELETE FROM `tariff_services` WHERE hospital_id = ?")->execute([$newHospId]);
$db->prepare("DELETE FROM `users` WHERE hospital_id = ?")->execute([$newHospId]);
$db->prepare("DELETE FROM `hospital_profiles` WHERE id = ?")->execute([$newHospId]);

echo "\n----------------------------------------------------\n";
echo "SUMMARY: Passed $passCount of $testCount automated verification tests.\n";
echo "====================================================\n";
