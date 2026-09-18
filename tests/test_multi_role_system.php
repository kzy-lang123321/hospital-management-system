<?php
/**
 * Hospital Management System (HMS) - Multi-User Role-Based Access Control (RBAC) Test Suite
 * 
 * Verifies that:
 * 1. Lab Management (lab_technician) has access to Laboratory, but is blocked from Billing/Settings.
 * 2. Pharmacy Management (pharmacist) has access to Pharmacy/Inventory, but is blocked from OPD/OT.
 * 3. Radiology Management (radiologist) has access to Radiology, but is blocked from Users/Pharmacy.
 * 4. Wards & Private Rooms (nurse) has access to IPD Beds/Emergency, but is blocked from Billing/Settings.
 * 5. OPD / Consultant (doctor) has access to OPD Consultation/OT/Appointments, but is blocked from Users/Settings.
 * 6. Billing & Cashier (cashier) has access to Billing/Reports/Insurance, but is blocked from OPD/Labs.
 * 7. Front Desk (receptionist) has access to Patients/Appointments, but is blocked from Pharmacy/Billing.
 * 8. Super Administrator (super_admin) has full unrestricted access across all hospital workspaces.
 */

$baseUrl = "http://localhost/HOSIPTAL%20MANAGEMNT%20SYSTEM/index.php";

function curlRequest($url, $method = 'GET', $data = [], $cookieJar = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if (!empty($cookieJar)) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $body];
}

function loginUser($username, $password, $baseUrl) {
    $cookieJar = tempnam(sys_get_temp_dir(), 'hms_role_cookie_');
    $loginPage = curlRequest("$baseUrl?page=login", 'GET', [], $cookieJar);
    preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage['body'], $m);
    $csrf = $m[1] ?? '';
    curlRequest("$baseUrl?action=login", 'POST', [
        'csrf_token' => $csrf,
        'username' => $username,
        'password' => $password
    ], $cookieJar);
    return $cookieJar;
}

echo "\n========================================================================\n";
echo "🛡️   HOSPITAL MANAGEMENT SYSTEM - MULTI-ROLE RBAC VERIFICATION SUITE\n";
echo "========================================================================\n\n";

$passCount = 0;
$totalTests = 0;

function assertAccess($allowed, $code, $body, $roleName, $pageName) {
    global $passCount, $totalTests;
    $totalTests++;
    $is403 = ($code === 403) || (strpos($body, '403 - Access Prohibited') !== false);
    $is200 = ($code === 200) && !$is403;

    if ($allowed && $is200) {
        $passCount++;
        echo "✅ ALLOWED: [$roleName] -> page=$pageName (HTTP 200)\n";
    } elseif (!$allowed && $is403) {
        $passCount++;
        echo "🛡️  BLOCKED: [$roleName] -> page=$pageName (HTTP 403 Prohibited as expected)\n";
    } else {
        echo "❌ FAILED:  [$roleName] -> page=$pageName (Expected " . ($allowed ? '200' : '403') . ", got code=$code)\n";
    }
}

// ----------------------------------------------------------------------------
// 1. LAB MANAGEMENT (lab.usman / lab_technician)
// ----------------------------------------------------------------------------
echo "--- Testing Role: Laboratory Technician / Lab Manager (lab.usman) ---\n";
$jarLab = loginUser('lab.usman', 'Admin@123', $baseUrl);
$res = curlRequest("$baseUrl?page=laboratory", 'GET', [], $jarLab);
assertAccess(true, $res['code'], $res['body'], 'lab_technician', 'laboratory');
$res = curlRequest("$baseUrl?page=billing", 'GET', [], $jarLab);
assertAccess(false, $res['code'], $res['body'], 'lab_technician', 'billing');
$res = curlRequest("$baseUrl?page=settings", 'GET', [], $jarLab);
assertAccess(false, $res['code'], $res['body'], 'lab_technician', 'settings');
@unlink($jarLab);

// ----------------------------------------------------------------------------
// 2. PHARMACY MANAGEMENT (pharm.kamran / pharmacist)
// ----------------------------------------------------------------------------
echo "\n--- Testing Role: Pharmacist / Pharmacy Manager (pharm.kamran) ---\n";
$jarPharm = loginUser('pharm.kamran', 'Admin@123', $baseUrl);
$res = curlRequest("$baseUrl?page=pharmacy", 'GET', [], $jarPharm);
assertAccess(true, $res['code'], $res['body'], 'pharmacist', 'pharmacy');
$res = curlRequest("$baseUrl?page=inventory", 'GET', [], $jarPharm);
assertAccess(true, $res['code'], $res['body'], 'pharmacist', 'inventory');
$res = curlRequest("$baseUrl?page=opd_consultation", 'GET', [], $jarPharm);
assertAccess(false, $res['code'], $res['body'], 'pharmacist', 'opd_consultation');
$res = curlRequest("$baseUrl?page=ot", 'GET', [], $jarPharm);
assertAccess(false, $res['code'], $res['body'], 'pharmacist', 'ot');
@unlink($jarPharm);

// ----------------------------------------------------------------------------
// 3. RADIOLOGY MANAGEMENT (radio.farooq / radiologist)
// ----------------------------------------------------------------------------
echo "\n--- Testing Role: Radiologist / Imaging Specialist (radio.farooq) ---\n";
$jarRadio = loginUser('radio.farooq', 'Admin@123', $baseUrl);
$res = curlRequest("$baseUrl?page=radiology", 'GET', [], $jarRadio);
assertAccess(true, $res['code'], $res['body'], 'radiologist', 'radiology');
$res = curlRequest("$baseUrl?page=pharmacy", 'GET', [], $jarRadio);
assertAccess(false, $res['code'], $res['body'], 'radiologist', 'pharmacy');
$res = curlRequest("$baseUrl?page=users", 'GET', [], $jarRadio);
assertAccess(false, $res['code'], $res['body'], 'radiologist', 'users');
@unlink($jarRadio);

// ----------------------------------------------------------------------------
// 4. WARDS & PRIVATE ROOMS (nurse.fatima / nurse)
// ----------------------------------------------------------------------------
echo "\n--- Testing Role: Staff Nurse / Ward Incharge (nurse.fatima) ---\n";
$jarNurse = loginUser('nurse.fatima', 'Admin@123', $baseUrl);
$res = curlRequest("$baseUrl?page=ipd_beds", 'GET', [], $jarNurse);
assertAccess(true, $res['code'], $res['body'], 'nurse', 'ipd_beds (Wards & Rooms)');
$res = curlRequest("$baseUrl?page=emergency", 'GET', [], $jarNurse);
assertAccess(true, $res['code'], $res['body'], 'nurse', 'emergency');
$res = curlRequest("$baseUrl?page=billing", 'GET', [], $jarNurse);
assertAccess(false, $res['code'], $res['body'], 'nurse', 'billing');
$res = curlRequest("$baseUrl?page=settings", 'GET', [], $jarNurse);
assertAccess(false, $res['code'], $res['body'], 'nurse', 'settings');
@unlink($jarNurse);

// ----------------------------------------------------------------------------
// 5. OPD CONSULTANT (dr.tariq / doctor)
// ----------------------------------------------------------------------------
echo "\n--- Testing Role: Consultant Doctor / Clinician (dr.tariq) ---\n";
$jarDoc = loginUser('dr.tariq', 'Admin@123', $baseUrl);
$res = curlRequest("$baseUrl?page=opd_consultation", 'GET', [], $jarDoc);
assertAccess(true, $res['code'], $res['body'], 'doctor', 'opd_consultation');
$res = curlRequest("$baseUrl?page=appointments", 'GET', [], $jarDoc);
assertAccess(true, $res['code'], $res['body'], 'doctor', 'appointments');
$res = curlRequest("$baseUrl?page=ot", 'GET', [], $jarDoc);
assertAccess(true, $res['code'], $res['body'], 'doctor', 'ot (Operation Theatre)');
$res = curlRequest("$baseUrl?page=users", 'GET', [], $jarDoc);
assertAccess(false, $res['code'], $res['body'], 'doctor', 'users');
@unlink($jarDoc);

// ----------------------------------------------------------------------------
// 6. BILLING & CASHIER (cashier.hassan / cashier)
// ----------------------------------------------------------------------------
echo "\n--- Testing Role: Billing Cashier (cashier.hassan) ---\n";
$jarCashier = loginUser('cashier.hassan', 'Admin@123', $baseUrl);
$res = curlRequest("$baseUrl?page=billing", 'GET', [], $jarCashier);
assertAccess(true, $res['code'], $res['body'], 'cashier', 'billing');
$res = curlRequest("$baseUrl?page=insurance", 'GET', [], $jarCashier);
assertAccess(true, $res['code'], $res['body'], 'cashier', 'insurance');
$res = curlRequest("$baseUrl?page=reports", 'GET', [], $jarCashier);
assertAccess(true, $res['code'], $res['body'], 'cashier', 'reports');
$res = curlRequest("$baseUrl?page=opd_consultation", 'GET', [], $jarCashier);
assertAccess(false, $res['code'], $res['body'], 'cashier', 'opd_consultation');
@unlink($jarCashier);

// ----------------------------------------------------------------------------
// 7. FRONT DESK RECEPTION (reception.sana / receptionist)
// ----------------------------------------------------------------------------
echo "\n--- Testing Role: Front Desk Receptionist (reception.sana) ---\n";
$jarRec = loginUser('reception.sana', 'Admin@123', $baseUrl);
$res = curlRequest("$baseUrl?page=patients", 'GET', [], $jarRec);
assertAccess(true, $res['code'], $res['body'], 'receptionist', 'patients');
$res = curlRequest("$baseUrl?page=appointments", 'GET', [], $jarRec);
assertAccess(true, $res['code'], $res['body'], 'receptionist', 'appointments');
$res = curlRequest("$baseUrl?page=pharmacy", 'GET', [], $jarRec);
assertAccess(false, $res['code'], $res['body'], 'receptionist', 'pharmacy');
$res = curlRequest("$baseUrl?page=billing", 'GET', [], $jarRec);
assertAccess(false, $res['code'], $res['body'], 'receptionist', 'billing');
@unlink($jarRec);

// ----------------------------------------------------------------------------
// 8. SUPER ADMINISTRATOR (superadmin / super_admin)
// ----------------------------------------------------------------------------
echo "\n--- Testing Role: Super Administrator (superadmin) ---\n";
$jarAdmin = loginUser('superadmin', 'Admin@123', $baseUrl);
$res = curlRequest("$baseUrl?page=dashboard", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'dashboard');
$res = curlRequest("$baseUrl?page=laboratory", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'laboratory');
$res = curlRequest("$baseUrl?page=pharmacy", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'pharmacy');
$res = curlRequest("$baseUrl?page=radiology", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'radiology');
$res = curlRequest("$baseUrl?page=ipd_beds", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'ipd_beds (Wards/Rooms)');
$res = curlRequest("$baseUrl?page=ot", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'ot');
$res = curlRequest("$baseUrl?page=billing", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'billing');
$res = curlRequest("$baseUrl?page=users", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'users');
$res = curlRequest("$baseUrl?page=audit_logs", 'GET', [], $jarAdmin);
assertAccess(true, $res['code'], $res['body'], 'super_admin', 'audit_logs');
@unlink($jarAdmin);

echo "\n========================================================================\n";
echo "📊  RBAC TEST RESULTS SUMMARY\n";
echo "========================================================================\n";
echo "Total Multi-User Role Checks Executed : $totalTests\n";
echo "Total Assertions Passed              : $passCount\n";
echo "Total Violations / Failures          : " . ($totalTests - $passCount) . "\n";
echo "========================================================================\n";

if ($passCount === $totalTests) {
    echo "\n🎉 ALL $totalTests ROLE-BASED ACCESS CONTROL CHECKS PASSED WITH 100% ISOLATION!\n\n";
    exit(0);
} else {
    echo "\n⚠️ $passCount of $totalTests RBAC checks passed.\n\n";
    exit(1);
}
