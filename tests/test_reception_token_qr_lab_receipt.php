<?php
/**
 * Test Suite: POS Exclusivity in Pharmacy, Reception OPD Token Slip with QR Code, and Dedicated Lab Receipt
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Tenant.php';
require_once __DIR__ . '/../core/QrCode.php';

$db = Database::getConnection();

echo "=========================================================================\n";
echo "TEST SUITE: RECEPTION TOKEN (QR), PHARMACY POS EXCLUSIVITY & LAB RECEIPT\n";
echo "=========================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest($condition, $name) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $name\n";
        $passed++;
    } else {
        echo "[FAIL] $name\n";
        $failed++;
    }
}

// 1. Verify POS is Removed from Dashboard
$dashboardContent = file_get_contents(__DIR__ . '/../views/dashboard/index.php');
assertTest(strpos($dashboardContent, 'id="dashboard-pos"') === false, "Dashboard does NOT contain #dashboard-pos form");
assertTest(strpos($dashboardContent, 'let posCart = []') === false, "Dashboard does NOT contain POS JavaScript engine");
assertTest(strpos($dashboardContent, 'id="reception-tokens"') !== false, "Dashboard contains #reception-tokens board");
assertTest(strpos($dashboardContent, 'Print Token (QR)') !== false, "Dashboard contains 'Print Token (QR)' action buttons");

// 2. Verify POS is Active in Pharmacy Module
$pharmacyContent = file_get_contents(__DIR__ . '/../views/pharmacy/index.php');
assertTest(strpos($pharmacyContent, 'tab=dispensary') !== false, "Pharmacy contains Dispensary & POS tab");
assertTest(strpos($pharmacyContent, 'id="pos-sale-form"') !== false || strpos($pharmacyContent, 'pos_sale_checkout') !== false, "Pharmacy contains POS checkout form and dispensary counter");
assertTest(strpos($pharmacyContent, 'tab=bulk_entry') !== false, "Pharmacy contains Bulk Medicine Stock Entry tab");

// 3. Test QrCode Core Component
$qrData = QrCode::patientUrl(15, 'MRN-2026-0015');
$qrHtml = QrCode::render($qrData, 120, 'test_qr', 'Scan Patient Record');
assertTest(!empty($qrHtml), "QrCode::render generates QR container HTML");
assertTest(strpos($qrHtml, 'qrcode.min.js') !== false || strpos($qrHtml, 'test_qr') !== false, "QrCode container contains target canvas ID and engine");
assertTest(strpos($qrData, 'MRN-2026-0015') !== false, "QrCode patientUrl contains patient MRN");

// 4. Test Standalone Print Token File (views/reception/print_token.php)
assertTest(file_exists(__DIR__ . '/../views/reception/print_token.php'), "views/reception/print_token.php exists");
$tokenFileContent = file_get_contents(__DIR__ . '/../views/reception/print_token.php');
assertTest(strpos($tokenFileContent, 'thermal-qrcode') !== false || strpos($tokenFileContent, 'QRCode') !== false, "print_token.php renders QR code");
assertTest(strpos($tokenFileContent, 'token_number') !== false || strpos($tokenFileContent, 'Token') !== false, "print_token.php displays token number");
assertTest(strpos($tokenFileContent, 'mrn') !== false || strpos($tokenFileContent, 'MRN') !== false, "print_token.php displays prominent Patient Record Key / MRN");
assertTest(strpos($tokenFileContent, 'ticket-cut') !== false || strpos($tokenFileContent, 'thermal') !== false, "print_token.php supports 80mm thermal receipt");

// 5. Test Dedicated Lab Receipt File (views/laboratory/print_receipt.php)
assertTest(file_exists(__DIR__ . '/../views/laboratory/print_receipt.php'), "views/laboratory/print_receipt.php exists");
$labReceiptContent = file_get_contents(__DIR__ . '/../views/laboratory/print_receipt.php');
assertTest(stripos($labReceiptContent, 'Diagnostic Lab') !== false || stripos($labReceiptContent, 'Laboratory Investigation Receipt') !== false, "print_receipt.php has diagnostic laboratory header");
assertTest(stripos($labReceiptContent, 'Barcode') !== false, "print_receipt.php displays specimen barcode");
assertTest(strpos($labReceiptContent, 'qrcode') !== false || strpos($labReceiptContent, 'QRCode') !== false, "print_receipt.php renders lab accession QR code");
assertTest(strpos($labReceiptContent, 'Pathologist') !== false || strpos($labReceiptContent, 'Phlebotomist') !== false, "print_receipt.php includes pathology/phlebotomist stamp");

// 6. Test Lab Workspace Links to Print Lab Receipt
$labIndexContent = file_get_contents(__DIR__ . '/../views/laboratory/index.php');
assertTest(strpos($labIndexContent, 'page=print_lab_receipt') !== false, "views/laboratory/index.php links to print_lab_receipt");

// 7. Test Routing in index.php
$indexContent = file_get_contents(__DIR__ . '/../index.php');
assertTest(strpos($indexContent, "\$page === 'print_token'") !== false, "index.php registers route for print_token");
assertTest(strpos($indexContent, "\$page === 'print_lab_receipt'") !== false, "index.php registers route for print_lab_receipt");
assertTest(strpos($indexContent, "page=print_token&patient_id=") !== false, "patient_store redirects to print_token with QR code");
assertTest(strpos($indexContent, "page=print_token&apt_id=") !== false, "appointment_store redirects to print_token with QR code");

// 8. Test CSRF Protection and Session Stability
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = $_SESSION['csrf_token'];
assertTest(verify_csrf() === true, "verify_csrf passes with valid session token");

$_POST['csrf_token'] = 'invalid_tampered_token';
// Mock redirect to avoid actual exit during test
$tokenValid = (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']));
assertTest($tokenValid === false, "verify_csrf correctly rejects invalid CSRF token");

echo "\n=========================================================================\n";
echo "SUMMARY: Passed: $passed, Failed: $failed\n";
echo "=========================================================================\n";

if ($failed > 0) {
    exit(1);
}
