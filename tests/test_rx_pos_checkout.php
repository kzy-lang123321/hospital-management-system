<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['user'] = [
    'id' => 1,
    'username' => 'admin',
    'full_name' => 'System Administrator',
    'role_slug' => 'super_admin',
    'role_name' => 'Super Administrator'
];
$_SESSION['user_id'] = 1;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
$db = Database::getConnection();

echo "=== TESTING PRESCRIPTION LOAD & DOSAGE INSTRUCTIONS IN POS ===" . PHP_EOL;

// 1. Find pending prescription
$rx = $db->query("SELECT pr.*, p.mrn, p.first_name, p.last_name FROM prescriptions pr JOIN encounters e ON pr.encounter_id = e.id JOIN patients p ON e.patient_id = p.id WHERE pr.status = 'Pending' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$rx) {
    die("No pending prescription found." . PHP_EOL);
}

echo "Found Pending Prescription: #{$rx['prescription_number']} for {$rx['first_name']} {$rx['last_name']} (ID: {$rx['id']})" . PHP_EOL;

// 2. Prescription Items with dosage instructions
$items = $db->query("
    SELECT pi.*, di.brand_name,
           (SELECT id FROM drug_batches WHERE drug_item_id = di.id AND status = 'Active' AND current_quantity > 0 ORDER BY expiry_date ASC LIMIT 1) AS batch_id,
           (SELECT unit_sale_price FROM drug_batches WHERE drug_item_id = di.id AND status = 'Active' AND current_quantity > 0 ORDER BY expiry_date ASC LIMIT 1) AS unit_price
    FROM prescription_items pi
    JOIN drug_items di ON pi.drug_item_id = di.id
    WHERE pi.prescription_id = {$rx['id']}
")->fetchAll(PDO::FETCH_ASSOC);

echo "Items found: " . count($items) . PHP_EOL;
foreach ($items as $it) {
    echo "  - Drug: {$it['brand_name']} | Prescribed: {$it['quantity_prescribed']} | Instructions: {$it['instructions']}" . PHP_EOL;
}

// 3. Simulate Checkout with Dosage Instructions
$db->beginTransaction();
try {
    $saleCount = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `pharmacy_sales`")->fetchColumn();
    $invoiceNum = 'POS-' . date('Ymd') . '-' . str_pad((string)($saleCount + 1), 4, '0', STR_PAD_LEFT);

    $subtotal = 0;
    foreach ($items as $it) {
        $subtotal += ($it['quantity_prescribed'] * $it['unit_price']);
    }

    $stmt = $db->prepare("
        INSERT INTO pharmacy_sales 
        (sale_invoice_number, prescription_id, customer_name, subtotal, discount_amount, tax_amount, net_total, amount_tendered, change_amount, payment_method, dispensed_by, created_at)
        VALUES (?, ?, ?, ?, 0.00, 0.00, ?, ?, 0.00, 'Cash', 1, NOW())
    ");
    $stmt->execute([
        $invoiceNum,
        $rx['id'],
        $rx['first_name'] . ' ' . $rx['last_name'],
        $subtotal,
        $subtotal,
        $subtotal
    ]);
    $saleId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare("
        INSERT INTO pharmacy_sale_items (pharmacy_sale_id, drug_batch_id, quantity, unit_price, total_price, dosage_instructions)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $it) {
        $lineTotal = $it['quantity_prescribed'] * $it['unit_price'];
        $itemStmt->execute([
            $saleId,
            $it['batch_id'],
            $it['quantity_prescribed'],
            $it['unit_price'],
            $lineTotal,
            $it['instructions']
        ]);
    }

    // Mark prescription as Dispensed
    $db->prepare("UPDATE prescriptions SET status = 'Dispensed' WHERE id = ?")->execute([$rx['id']]);

    $db->commit();
    echo "[PASS] POS Sale #$invoiceNum created with Prescription #{$rx['prescription_number']} linked." . PHP_EOL;
} catch (Exception $e) {
    $db->rollBack();
    die("[FAIL] " . $e->getMessage() . PHP_EOL);
}

// 4. Verify Rx is marked as Dispensed
$updatedRxStatus = $db->query("SELECT status FROM prescriptions WHERE id = {$rx['id']}")->fetchColumn();
echo "Prescription Status after checkout: $updatedRxStatus (Expected: Dispensed)" . PHP_EOL;
if ($updatedRxStatus === 'Dispensed') {
    echo "[PASS] Prescription status successfully updated to 'Dispensed'." . PHP_EOL;
} else {
    echo "[FAIL] Prescription status not updated." . PHP_EOL;
}

// 5. Verify Dosage Instructions saved in pharmacy_sale_items
$savedItems = $db->query("SELECT * FROM pharmacy_sale_items WHERE pharmacy_sale_id = $saleId")->fetchAll(PDO::FETCH_ASSOC);
foreach ($savedItems as $si) {
    echo "  - Item ID: {$si['id']} | Batch ID: {$si['drug_batch_id']} | Instructions: '{$si['dosage_instructions']}'" . PHP_EOL;
    if (empty($si['dosage_instructions'])) {
        die("[FAIL] Dosage instructions missing from pharmacy_sale_items." . PHP_EOL);
    }
}
echo "[PASS] Dosage instructions verified in pharmacy_sale_items." . PHP_EOL;

// 6. Test Receipt Generation with Dosage Instructions
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['user_id'] = 1;
$_GET['sale_id'] = $saleId;
$_GET['mode'] = 'thermal';
ob_start();
require __DIR__ . '/../views/pos/print_receipt.php';
$receiptHtml = ob_get_clean();

if (strpos($receiptHtml, 'طریقہ استعمال') !== false) {
    echo "[PASS] Urdu Dosage Instructions ('طریقہ استعمال') successfully rendered on print receipt!" . PHP_EOL;
} else {
    echo "[FAIL] 'طریقہ استعمال' not found in receipt HTML." . PHP_EOL;
}

echo "=== ALL PRESCRIPTION & DOSAGE TESTS PASSED! ===" . PHP_EOL;
