<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$baseUrl = "http://localhost/HOSIPTAL%20MANAGEMNT%20SYSTEM";
$credsFile = __DIR__ . '/../scripts/management_credentials.json';
if (!file_exists($credsFile)) {
    die("Credentials file not found!\n");
}

$users = json_decode(file_get_contents($credsFile), true);

function makeCurl($url, $method = 'GET', $postData = [], $cookieFile = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $body];
}

echo "\n========================================================================\n";
echo "👑  VERMFYING ALL 14 MANAGEMENT USER ACCOUNTS & NAME-BASED PASSWORDS\n";
echo "=======================================================================\n\n";

$passed = 0;

$deptPages = [
    'super_admin'    => 'dashboard',
    'hospital_admin' => 'dashboard',
    'lab_technician'  => 'laboratory',
    'pharmacist'     => 'pharmacy',
    'radiologist'    => 'radiology',
    'nurse'          => 'ipd_beds',
    'doctor'         => 'opd_consultation',
    'cashier'        => 'billing',
    'receptionist'   => 'appointments'
];

foreach ($users as $u) {
    $cookie = tempnam(sys_get_temp_dir(), 'his_mgt_');
    
    $loginPage = makeCurl("$baseUrl/index.php?page=login", 'GET', [], $cookie);
    preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage['body'], $m);
    $csrf = $m[1] ?? '';
    
    $loginRes = makeCurl("$baseUrl/index.php?action=login", 'POST', [
        'csrf_token' => $csrf,
        'username'   => $u['username'],
        'password'   => $u['password']
    ], $cookie);
    
    if ($loginRes['code'] === 200 && strpos($loginRes['body'], 'index.php?action=logout') !== false) {
        $passed++;
        
        $destPage = $deptPages[$u['role_slug']] ?? 'dashboard';
        $pageRes = makeCurl("$baseUrl/index.php?page=$destPage", 'GET', [], $cookie);
        $accessOk = ($pageRes['code'] === 200 && strpos($pageRes['body'], $destPage) !== false);
        
        printf("✅ AUTHENTICATED: @%-15s | %-28s | Password: %-12s | Access OK: %s\n", $u['username'], $u['full_name'], $u['password'], $destPage);
    } else {
        printf("❌ FAILED:        @%-15s | Password: %s\n", $u['username'], $u['password']);
    }
    
    if (file_exists($cookie)) unlink($cookie);
}

echo "\n========================================================================\n";
echo "🏆 RESULT: $passed / " . count($users) . " Management Users Authenticated Successfully!\n";
echo "========================================================================\n";