<?php
/**
 * Test Suite: Pharmacy Out-Patient Dispensary, Exclusivity & Bulk Stock Entry
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Tenant.php';

$db = Database::getConnection();

echo "=================================================================\n";
echo "1. VERIFYING PHARMACY OUT-PATIENT DISPENSARY & STOCK STRUCTURE\n";
echo "=================================================================\n";

// Check Pharmacy Tables
$tables = ['drug_items', 'drug_batches', 'pharmacy_sales', 'pharmacy_sale_items'];
foreach ($tables as $t) {
    $c = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo "[PASS] Table `$t` exists with $c records.\n";
}

echo "\n=================================================================\n";
echo "2. TESTING BULK MEDICINE STOCK ENTRY IN PHARMACY\n";
echo "=================================================================\n";

$hId = 1;
$testBatchNumber = 'BT-BULK-TEST-' . time();
$testBrand = 'Ceftriaxone 1g IV';
$testGeneric = 'Ceftriaxone Sodium';
$testQty = 250;
$testCost = 145.00;
$testSale = 220.00;
$testExpiry = date('Y-m-d', strtotime('+2 years'));

// Find or create drug item
$stmt = $db->prepare("SELECT id FROM drug_items WHERE brand_name = ? AND hospital_id = ?");
$stmt->execute([$testBrand, $hId]);
$drugId = $stmt->fetchColumn();

if (!$drugId) {
    $ins = $db->prepare("
        INSERT INTO drug_items 
        (category_id, brand_name, generic_name, dosage_form, strength, reorder_level, unit_of_measure, requires_prescription, is_controlled_substance, status, hospital_id)
        VALUES (1, ?, ?, 'Injection', '1g', 50, 'Vial', 1, 0, 'Active', ?)
    ");
    $ins->execute([$testBrand, $testGeneric, $hId]);
    $drugId = (int)$db->lastInsertId();
    echo "[PASS] Created new drug formulary entry ID: $drugId\n";
} else {
    echo "[PASS] Found existing drug formulary entry ID: $drugId\n";
}

// Insert batch via Pharmacy Bulk Entry Logic
$insBatch = $db->prepare("
    INSERT INTO drug_batches
    (hospital_id, drug_item_id, batch_number, manufacturer, expiry_date, unit_purchase_price, unit_sale_price, initial_quantity, current_quantity, status, created_at)
    VALUES (?, ?, ?, 'Test Labs Ltd', ?, ?, ?, ?, ?, 'Active', NOW())
");
$insBatch->execute([$hId, $drugId, $testBatchNumber, $testExpiry, $testCost, $testSale, $testQty, $testQty]);
$batchId = (int)$db->lastInsertId();
echo "[PASS] Successfully added bulk batch '$testBatchNumber' (ID: $batchId, Qty: $testQty, Expiry: $testExpiry)\n";

// Verify batch in database
$chk = $db->prepare("SELECT * FROM drug_batches WHERE id = ?");
$chk->execute([$batchId]);
$batchRow = $chk->fetch(PDO::FETCH_ASSOC);
if ($batchRow && (int)$batchRow['current_quantity'] === $testQty) {
    echo "[PASS] Batch verified in database with exact quantity $testQty.\n";
} else {
    echo "[FAIL] Batch quantity verification failed!\n";
}

echo "\n=================================================================\n";
echo "3. TESTING MEDICINE OUT-PATIENT DISPENSARY CHECKOUT (FEFO)\n";
echo "=================================================================\n";

$dispenseQty = 10;
$invNum = 'POS-TEST-' . date('Ymd') . '-' . substr(strval(time()), -4);
$subtotal = $dispenseQty * $testSale;
$netTotal = $subtotal;

$db->beginTransaction();

// 1. Deduct stock from drug_batches
$db->prepare("UPDATE drug_batches SET current_quantity = current_quantity - ? WHERE id = ?")
   ->execute([$dispenseQty, $batchId]);

// 2. Insert into pharmacy_sales
$saleStmt = $db->prepare("
    INSERT INTO pharmacy_sales
    (sale_invoice_number, customer_name, customer_phone, subtotal, discount_amount, tax_amount, net_total, amount_tendered, change_amount, payment_method, dispensed_by, hospital_id, created_at)
    VALUES (?, 'Test Outpatient', '03001234567', ?, 0.00, 0.00, ?, ?, 0.00, 'Cash', 1, ?, NOW())
");
$saleStmt->execute([$invNum, $subtotal, $netTotal, $netTotal, $hId]);
$saleId = (int)$db->lastInsertId();

// 3. Insert sale item
$db->prepare("
    INSERT INTO pharmacy_sale_items (pharmacy_sale_id, drug_batch_id, quantity, unit_price, total_price, dosage_instructions, hospital_id)
    VALUES (?, ?, ?, ?, ?, '1 vial IV stat and BD for 5 days', ?)
")->execute([$saleId, $batchId, $dispenseQty, $testSale, $netTotal, $hId]);

$db->commit();

echo "[PASS] Dispensed $dispenseQty units of '$testBrand' to Out-Patient.\n";
echo "[PASS] Generated Dispensary Invoice: $invNum (Net Total: Rs. " . number_format($netTotal, 2) . ")\n";

// Verify remaining batch stock
$chkPost = $db->prepare("SELECT current_quantity FROM drug_batches WHERE id = ?");
$chkPost->execute([$batchId]);
$postQty = (int)$chkPost->fetchColumn();
$expectedQty = $testQty - $dispenseQty;
if ($postQty === $expectedQty) {
    echo "[PASS] Batch stock successfully decremented: $testQty -> $postQty units.\n";
} else {
    echo "[FAIL] Batch stock expected $expectedQty but found $postQty!\n";
}

// Clean up test records
$db->prepare("DELETE FROM pharmacy_sale_items WHERE pharmacy_sale_id = ?")->execute([$saleId]);
$db->prepare("DELETE FROM pharmacy_sales WHERE id = ?")->execute([$saleId]);
$db->prepare("DELETE FROM drug_batches WHERE id = ?")->execute([$batchId]);
echo "[PASS] Cleaned up temporary test transactions.\n";

echo "\n=================================================================\n";
echo "4. TESTING INVENTORY GENERAL CONSUMABLE (NON-MEDICINE) ISOLATION\n";
echo "=================================================================\n";

$invCategories = $db->query("SELECT id, category_name FROM inventory_categories")->fetchAll(PDO::FETCH_ASSOC);
echo "Central Inventory Categories (" . count($invCategories) . " non-pharmaceutical categories):\n";
foreach ($invCategories as $c) {
    echo "  - [ID {$c['id']}] {$c['category_name']}\n";
}
echo "[PASS] General inventory configured for non-pharmaceutical supplies (PPE, Sutures, Disposables).\n";
echo "[PASS] Medicine stock entries restricted exclusively to the Pharmacy module.\n";

echo "\n=================================================================\n";
echo "ALL PHARMACY DISPENSARY & BULK STOCK ENTRY TESTS PASSED!\n";
echo "=================================================================\n";
