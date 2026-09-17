<?php
/**
 * Automated HTTP Integration Test for HMS Endpoints
 */

$cookieJar = tempnam(sys_get_temp_dir(), 'hmscookies');

function makeRequest($url, $method = 'GET', $data = [], $cookieJar = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $response, 'url' => $effectiveUrl];
}

$baseUrl = "http://localhost/HOSIPTAL%20MANAGEMNT%20SYSTEM/index.php";

echo "=== HMS INTEGRATION TEST SUITE ===\n";

// 1. Get Login Page & CSRF Token
$step1 = makeRequest("$baseUrl?page=login", 'GET', [], $cookieJar);
preg_match('/name="csrf_token" value="([^"]+)"/', $step1['body'], $matches);
$csrfToken = $matches[1] ?? '';
echo "1. Login Page: HTTP {$step1['code']}, CSRF Token: " . substr($csrfToken, 0, 10) . "...\n";

// 2. Perform Login as Super Admin
$loginData = [
    'csrf_token' => $csrfToken,
    'username' => 'superadmin',
    'password' => 'Admin@123'
];
$step2 = makeRequest("$baseUrl?action=login", 'POST', $loginData, $cookieJar);
echo "2. Login Action: HTTP {$step2['code']}, Landed at: {$step2['url']}\n";
$dashboardCheck = strpos($step2['body'], 'Engr. Tariq Farooq') !== false ? 'PASS' : 'FAIL';
echo "   Dashboard User Render: $dashboardCheck\n";

// 3. Test All Protected Modules
$modules = [
    'dashboard' => 'Live Facility Status',
    'patients' => 'Patient Directory',
    'patient_create' => 'Register New Patient',
    'appointments' => 'Appointment Scheduling',
    'opd_consultation' => 'Clinician Workbench',
    'ipd_beds' => 'Interactive Bed Rack Matrix',
    'pharmacy' => 'Pharmacy & Dispensing',
    'laboratory' => 'Clinical Pathology & Laboratory',
    'billing' => 'Centralized Billing & Cashier Desk',
    'reports' => 'Executive Reports & Operational Analytics',
    'settings' => 'Hospital Masters & Dynamic Configuration',
    'users' => 'Active Staff Directory',
    'audit_logs' => 'Security & Clinical Audit Trail'
];

foreach ($modules as $pageKey => $expectedString) {
    $res = makeRequest("$baseUrl?page=$pageKey", 'GET', [], $cookieJar);
    $contains = strpos($res['body'], $expectedString) !== false;
    $statusText = ($res['code'] === 200 && $contains) ? 'PASS' : 'FAIL';
    echo "Module [$pageKey]: HTTP {$res['code']} | Match: $statusText\n";
    if ($statusText === 'FAIL') {
        echo "   Body snippet: " . substr(strip_tags($res['body']), 0, 150) . "...\n";
    }
}

@unlink($cookieJar);
echo "\n=== TEST RUN FINISHED ===\n";
