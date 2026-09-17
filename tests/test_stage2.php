<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/AuditLogger.php';

session_start();
if (!Auth::attempt('superadmin', 'Admin@123')) {
    echo "Login failed!\n";
    exit(1);
}
echo "Auth succeeded! Logged in as: " . Auth::role() . "\n";

$pages = [
    'dashboard' => 'views/dashboard/index.php',
    'patients' => 'views/patients/index.php',
    'emergency' => 'views/emergency/index.php',
    'radiology' => 'views/radiology/index.php',
    'ot' => 'views/ot/index.php',
    'blood_bank' => 'views/blood_bank/index.php',
    'insurance' => 'views/insurance/index.php',
    'inventory' => 'views/inventory/index.php'
];

foreach ($pages as $pName => $file) {
    ob_start();
    $_GET['page'] = $pName;
    $currentPage = $pName;
    require __DIR__ . '/../' . $file;
    $out = ob_get_clean();
    echo "Page [{$pName}] rendered cleanly! Output size: " . strlen($out) . " bytes\n";
}

echo "ALL STAGE 2 VIEWS VERIFIED SUCCESSFULLY!\n";
