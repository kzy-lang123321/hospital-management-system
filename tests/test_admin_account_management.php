<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$baseUrl = 'http://localhost/HOSIPTAL%20MANAGEMNT%20SYSTEM';
$cookieFile = __DIR__ . '/test_admin_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);


function makeRequest($url, $method = 'GET', $postData = [], $cookieFile = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body];
}

function extractCsrf($html) {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

echo "====================================================\n";
echo "🏥 AUTOMATED TEST. ADMIN ACCOUNT MANAGEMENT SYSTEM\n";
echo "====================================================\n\n";

$res = makeRequest($baseUrl . '/index.php?page=login', 'GET', [], $cookieFile);
$csrf = extractCsrf($res['body']);

$loginRes = makeRequest($baseUrl . '/index.php?action=login', 'POST', [
    'csrf_token' => $csrf,
    'username'   => 'superadmin',
    'password'   => 'Admin@123'
], $cookieFile);

if ($loginRes['code'] !== 302) {
    die("FAILED: Superadmin login failed with status " . $loginRes['code'] . "\n");
}
echo "1. Superadmin login successful (HTTP 302).\n";

$usersRes = makeRequest($baseUrl . '/index.php?page=users', 'GET', [], $cookieFile);
if ($usersRes['code'] !== 200) {
    die("FAILED: Access to index.php?page=users returned HTTP " . $usersRes['code'] . "\n");
}
$pageCsrf = extractCsrf($usersRes['body']);
echo "2. User management page rendered successfully (HTTP 200).\n";

$db = Database::getConnection();
$db->exec("DELETE FROM users WHERE username = 'test.specialist'");

$createRes = makeRequest($baseUrl . '/index.php?action=user_store', 'POST', [
    'csrf_token'       => $pageCsrf,
    'username'         => 'test.specialist',
    'password'         => 'Spec@12345',
    'full_name'        => 'Dr. Test Specialist',
    'email'            => 'test.specialist@hayatmedicare.org',
    'phone'            => '+92 300 9998877',
    'role_id'          => 3,
    'department_id'    => 2,
    'designation'      => 'Senior Fellow Cardiology',
    'consultation_fee' => '2200.00'
], $cookieFile);

$stmt = $db->prepare("SELECT * FROM users WHERE username = 'test.specialist'");
$stmt->execute();
$newUser = $stmt->fetch();
if (!$newUser) die("FAILED: Account not created in database.\n");
echo "3. Admin successfully created staff account 'test.specialist' (ID: " . $newUser['id'] . ", Role: Doctor, Status: " . $newUser['status'] . ")\n";

$toggleRes = makeRequest($baseUrl . '/index.php?action=user_status_toggle', 'POST', [
    'csrf_token' => $pageCsrf,
    'user_id'    => $newUser['id']
], $cookieFile);
$stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$newUser['id']]);
$chk1 = $stmt->fetch();
if ($chk1['status'] !== 'Inactive') die("FAILED: Status not toggled to Inactive.\n");
echo "4. Status toggled to Inactive successfully.\n";

$toggleRes2 = makeRequest($baseUrl . '/index.php?action=user_status_toggle', 'POST', [
    'csrf_token' => $pageCsrf,
    'user_id'    => $newUser['id']
], $cookieFile);
$stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$newUser['id']]);
$chk2 = $stmt->fetch();
if ($chk2['status'] !== 'Active') die("FAILED: Status not toggled to Active.\n");
echo "5. Status toggled back to Active successfully.\n";

$resetRes = makeRequest($baseUrl . '/index.php?action=user_reset_password', 'POST', [
    'csrf_token'   => $pageCsrf,
    'user_id'      => $newUser['id'],
    'new_password' => 'NewSecuredPass@2026'
], $cookieFile);
echo "6. Admin reset password for 'test.specialist' executed.\n";

$staffCookie = __DIR__ . '/test_staff_cookie.txt';
if (file_exists($staffCookie)) unlink($staffCookie);

$staffLoginInit = makeRequest($baseUrl . '/index.php?page=login', 'GET', [], $staffCookie);
$staffCsrf = extractCsrf($staffLoginInit['body']);

$staffAuth = makeRequest($baseUrl . '/index.php?action=login', 'POST', [
    'csrf_token' => $staffCsrf,
    'username'   => 'test.specialist',
    'password'   => 'NewSecuredPass@2026'
], $staffCookie);
if ($staffAuth['code'] !== 302) die("FAILED: New staff could not log in with new password.\n");
echo "7. New staff account successfully authenticated with reset password!\n";

$staffOpd = makeRequest($baseUrl . '/index.php?page=opd_consultation', 'GET', [], $staffCookie);
if ($staffOpd['code'] !== 200) die("FAILED: New doctor cannot access OPD.\n");
echo "8. New doctor account granted access to OPD Clinician Workbench (HTTP 200).\n";

$delUser = $db->prepare("DELETE FROM users WHERE id = ?");
$delUser->execute([$newUser['id']]);

if (file_exists($cookieFile)) unlink($cookieFile);
if (file_exists($staffCookie)) unlink($staffCookie);

echo "\n====================================================\n";
echo "✅ ALL ADMIN ACCOUNT MANAGEMENT ASSERTIONS PASSED!!\n";
echo "====================================================\n";