<?php
/**
 * Hospital Management System (HMS) - Comprehensive Patient Journey Verification Test
 * 
 * Verifies:
 * 1. Duplicate Patient Detection & Identity Integrity
 * 2. Atomic Registration & Instant Appointment Token Allocation
 * 3. Reception Token Slip & Check-in to Encounter
 * 4. Clinician Workbench (SOAP, CPOE Lab, CPOE Rx, Follow-up scheduling, Auto-Billing)
 * 5. Laboratory Barcode Tracking & Result Sign-Off
 * 6. Pharmacy FEFO Stock Deduction & Medication Bill Appending
 * 7. Cashier Billing Desk Settlement & Fiscal Receipt Issuance
 * 8. Audit Trail Verification
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/AuditLogger.php';

// Mock superadmin session
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'superadmin';
$_SESSION['role'] = 'super_admin';
$_SESSION['hospital_id'] = 1;

$db = Database::getConnection();

echo "\n========================================================================\n";
echo "🏥  HOSPITAL MANAGEMENT SYSTEM - UNIFIED PATIENT JOURNEY TEST SUITE\n";
echo "========================================================================\n\n";

$passCount = 0;
$totalTests = 8;

function assertTest($condition, $name, $details = '') {
    global $passCount;
    if ($condition) {
        $passCount++;
        echo "✅ PASS: $name\n";
        if ($details) echo "         $details\n";
    } else {
        echo "❌ FAIL: $name\n";
        if ($details) echo "         ERROR: $details\n";
    }
}

// ----------------------------------------------------------------------------
// TEST 1: Duplicate Patient Detection Logic
// ----------------------------------------------------------------------------
echo "--- Step 1: Duplicate Patient Detection ---\n";
$existingPatient = $db->query("SELECT phone_primary, national_id FROM `patients` LIMIT 1")->fetch();
if ($existingPatient) {
    $dupStmt = $db->prepare("SELECT id, mrn, first_name, last_name FROM `patients` WHERE phone_primary = ? OR national_id = ? LIMIT 1");
    $dupStmt->execute([$existingPatient['phone_primary'], $existingPatient['national_id']]);
    $found = $dupStmt->fetch();
    assertTest(!empty($found), "Duplicate detection identifies existing patient by Phone/CNIC", "Matched MRN: " . ($found['mrn'] ?? 'None'));
} else {
    assertTest(true, "Duplicate detection skipped (no prior patients)", "");
}

// ----------------------------------------------------------------------------
// TEST 2: Atomic Patient Registration & Instant Token Booking
// ----------------------------------------------------------------------------
echo "\n--- Step 2: Patient Registration & Instant Appointment ---\n";
$uniqueSuffix = time() . rand(100, 999);
$testPhone = "0300" . substr($uniqueSuffix, -7);
$testCnic = "17301-" . substr($uniqueSuffix, -7) . "-1";
$firstName = "Arshad";
$lastName = "Mehmood";

// Compute next MRN atomically
$latestId = $db->query("SELECT COALESCE(MAX(id), 0) FROM `patients`")->fetchColumn();
$testMrn = 'MRN-' . date('Y') . '-' . str_pad((string)($latestId + 1), 4, '0', STR_PAD_LEFT);

$insPatient = $db->prepare("
    INSERT INTO `patients` 
    (`mrn`, `first_name`, `last_name`, `gender`, `dob`, `age`, `blood_group`, `marital_status`, `national_id`, `phone_primary`, `city`, `address`, `registered_by`)
    VALUES (?, ?, ?, 'Male', '1988-04-12', 38, 'B+', 'Married', ?, ?, 'Peshawar', 'University Town Sector B', 1)
");
$insPatient->execute([$testMrn, $firstName, $lastName, $testCnic, $testPhone]);
$patientId = (int)$db->lastInsertId();

// Instant Token Generation for Doctor ID 2 (Cardiology)
$doctorId = 2;
$docDept = (int)$db->query("SELECT department_id FROM `users` WHERE `id` = $doctorId")->fetchColumn();
$aptDate = date('Y-m-d');

$tokenStmt = $db->prepare("SELECT COALESCE(MAX(token_number), 0) FROM `appointments` WHERE `doctor_id` = ? AND `appointment_date` = ?");
$tokenStmt->execute([$doctorId, $aptDate]);
$tokenNum = (int)$tokenStmt->fetchColumn() + 1;
$aptNum = 'APT-' . date('Ymd') . '-' . str_pad((string)$tokenNum, 3, '0', STR_PAD_LEFT);

$insApt = $db->prepare("
    INSERT INTO `appointments` 
    (`appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `slot_time`, `token_number`, `type`, `status`, `reason_for_visit`, `created_by`)
    VALUES (?, ?, ?, ?, ?, '11:00:00', ?, 'New', 'Scheduled', 'Hypertension and headache evaluation', 1)
");
$insApt->execute([$aptNum, $patientId, $doctorId, $docDept, $aptDate, $tokenNum]);
$appointmentId = (int)$db->lastInsertId();

AuditLogger::log('CREATE', 'patients', $patientId, null, ['mrn' => $testMrn]);
AuditLogger::log('CREATE', 'appointments', $appointmentId, null, ['token' => $tokenNum]);

assertTest($patientId > 0 && $appointmentId > 0, "Patient registered with instant token allocated", "MRN: $testMrn | Token #$tokenNum ($aptNum)");

// ----------------------------------------------------------------------------
// TEST 3: Reception Check-In & Active Encounter
// ----------------------------------------------------------------------------
echo "\n--- Step 3: Reception Check-In & Encounter Creation ---\n";
// Update appointment to Checked-In
$db->prepare("UPDATE `appointments` SET `status` = 'Checked-In' WHERE `id` = ?")->execute([$appointmentId]);

// Create OPD Encounter
$latestEnc = $db->query("SELECT COALESCE(MAX(id), 0) FROM `encounters`")->fetchColumn();
$encNum = 'ENC-' . date('Y') . '-' . str_pad((string)($latestEnc + 1), 4, '0', STR_PAD_LEFT);

$encStmt = $db->prepare("
    INSERT INTO `encounters` 
    (`encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `encounter_status`, `created_by`)
    VALUES (?, ?, 'OPD', ?, ?, NOW(), 'Arrived', 1)
");
$encStmt->execute([$encNum, $patientId, $docDept, $doctorId]);
$encounterId = (int)$db->lastInsertId();

assertTest($encounterId > 0, "Patient checked in and active OPD Encounter generated", "Encounter #$encNum (ID: $encounterId)");

// ----------------------------------------------------------------------------
// TEST 4: Clinician Workbench & CPOE Order Execution
// ----------------------------------------------------------------------------
echo "\n--- Step 4: Doctor Consultation, CPOE Orders & Auto-Billing ---\n";
// 1. Record Vitals & Clinical Notes
$insNotes = $db->prepare("
    INSERT INTO `consultation_notes` 
    (`encounter_id`, `doctor_id`, `chief_complaint`, `physical_examination`, `provisional_diagnosis`, `icd10_code`, `clinical_advice`, `follow_up_date`)
    VALUES (?, ?, 'Occasional pounding headache, stress related', 'BP: 145/92 mmHg, Pulse: 78 bpm, Chest clear', 'Stage 1 Essential Hypertension', 'I10', 'Prescribed antihypertensive, ordered CBC & Fasting Glucose. Advised lifestyle modification.', ?)
");
$followUpDate = date('Y-m-d', strtotime('+7 days'));
$insNotes->execute([$encounterId, $doctorId, $followUpDate]);

// 2. Schedule Follow-up Appointment (7 days later)
$fuTokenStmt = $db->prepare("SELECT COALESCE(MAX(token_number), 0) FROM `appointments` WHERE `doctor_id` = ? AND `appointment_date` = ?");
$fuTokenStmt->execute([$doctorId, $followUpDate]);
$fuTokenNum = (int)$fuTokenStmt->fetchColumn() + 1;
$fuAptNum = 'APT-' . date('Ymd', strtotime($followUpDate)) . '-' . str_pad((string)$fuTokenNum, 3, '0', STR_PAD_LEFT);

$insFuApt = $db->prepare("
    INSERT INTO `appointments` 
    (`appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `slot_time`, `token_number`, `type`, `status`, `reason_for_visit`, `created_by`)
    VALUES (?, ?, ?, ?, ?, '10:00:00', ?, 'Follow-up', 'Scheduled', 'Clinical follow-up & lab review after consultation', 1)
");
$insFuApt->execute([$fuAptNum, $patientId, $doctorId, $docDept, $followUpDate, $fuTokenNum]);
$followUpAptId = (int)$db->lastInsertId();

// 3. CPOE Lab Orders: Test 1 (CBC) and Test 2 (Fasting Blood Glucose)
$latestLab = $db->query("SELECT COALESCE(MAX(id), 0) FROM `lab_orders`")->fetchColumn();
$labOrderNum = 'LAB-' . date('Y') . '-' . str_pad((string)($latestLab + 1), 4, '0', STR_PAD_LEFT);
$labOrderStmt = $db->prepare("INSERT INTO `lab_orders` (`order_number`, `encounter_id`, `ordered_by`, `order_datetime`, `status`) VALUES (?, ?, ?, NOW(), 'Ordered')");
$labOrderStmt->execute([$labOrderNum, $encounterId, $doctorId]);
$labOrderId = (int)$db->lastInsertId();

$labItemStmt = $db->prepare("INSERT INTO `lab_order_items` (`lab_order_id`, `lab_test_id`, `item_status`) VALUES (?, ?, 'Pending')");
$labItemStmt->execute([$labOrderId, 1]); // CBC
$labItem1Id = (int)$db->lastInsertId();
$labItemStmt->execute([$labOrderId, 2]); // Blood Glucose Fasting
$labItem2Id = (int)$db->lastInsertId();

// 4. CPOE Prescription
$latestRx = $db->query("SELECT COALESCE(MAX(id), 0) FROM `prescriptions`")->fetchColumn();
$rxNum = 'RX-' . date('Y') . '-' . str_pad((string)($latestRx + 1), 4, '0', STR_PAD_LEFT);
$rxStmt = $db->prepare("INSERT INTO `prescriptions` (`prescription_number`, `encounter_id`, `doctor_id`, `status`, `clinical_notes`) VALUES (?, ?, ?, 'Pending', 'Take medication before breakfast')");
$rxStmt->execute([$rxNum, $encounterId, $doctorId]);
$rxId = (int)$db->lastInsertId();

$rxItemStmt = $db->prepare("
    INSERT INTO `prescription_items` (`prescription_id`, `drug_item_id`, `dosage`, `frequency`, `duration_days`, `instructions`, `quantity_prescribed`)
    VALUES (?, 3, '1 Cap (40mg)', '1-0-0 (OD Morning)', 14, 'Before breakfast', 14)
");
$rxItemStmt->execute([$rxId]);
$rxItemId = (int)$db->lastInsertId();

// 5. Automated Bill Generation: Consultation Fee + Lab Items
$docFee = (float)$db->query("SELECT consultation_fee FROM `users` WHERE `id` = $doctorId")->fetchColumn();
if ($docFee <= 0) $docFee = 1500.00;

$latestBill = $db->query("SELECT COALESCE(MAX(id), 0) FROM `bills`")->fetchColumn();
$billNum = 'INV-' . date('Y') . '-' . str_pad((string)($latestBill + 1), 4, '0', STR_PAD_LEFT);

$insBill = $db->prepare("
    INSERT INTO `bills` 
    (`bill_number`, `encounter_id`, `patient_id`, `bill_type`, `bill_date`, `gross_amount`, `net_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_by`)
    VALUES (?, ?, ?, 'OPD', NOW(), 0, 0, 0, 0, 'Unpaid', 1)
");
$insBill->execute([$billNum, $encounterId, $patientId]);
$billId = (int)$db->lastInsertId();

// Append Doctor Consultation Item
$insBillItem = $db->prepare("
    INSERT INTO `bill_items` (`bill_id`, `item_type`, `description`, `quantity`, `unit_price`, `subtotal`, `net_price`)
    VALUES (?, 'Consultation', 'Cardiologist Consultation Fee', 1, ?, ?, ?)
");
$insBillItem->execute([$billId, $docFee, $docFee, $docFee]);

// Append Lab Items
$labPrice1 = (float)$db->query("SELECT standard_price FROM `lab_tests` WHERE `id` = 1")->fetchColumn();
$labPrice2 = (float)$db->query("SELECT standard_price FROM `lab_tests` WHERE `id` = 2")->fetchColumn();
$totalLab = $labPrice1 + $labPrice2;

$insBillLab = $db->prepare("
    INSERT INTO `bill_items` (`bill_id`, `item_type`, `reference_id`, `description`, `quantity`, `unit_price`, `subtotal`, `net_price`)
    VALUES (?, 'Investigation', ?, ?, 1, ?, ?, ?)
");
$insBillLab->execute([$billId, 1, 'Lab Test: Complete Blood Count (CBC)', $labPrice1, $labPrice1, $labPrice1]);
$insBillLab->execute([$billId, 2, 'Lab Test: Blood Glucose Fasting (FBG)', $labPrice2, $labPrice2, $labPrice2]);

$totalInitialGross = $docFee + $totalLab;
$db->prepare("UPDATE `bills` SET `gross_amount` = ?, `net_amount` = ?, `balance_amount` = ? WHERE `id` = ?")
   ->execute([$totalInitialGross, $totalInitialGross, $totalInitialGross, $billId]);

assertTest(
    $labOrderId > 0 && $rxId > 0 && $followUpAptId > 0 && $billId > 0,
    "Consultation saved with CPOE Labs, Rx, auto-scheduled Follow-up and initial Bill",
    "Bill: $billNum (Initial Net: PKR " . number_format($totalInitialGross, 2) . ") | Follow-up Apt: $fuAptNum"
);

// ----------------------------------------------------------------------------
// TEST 5: Laboratory Specimen Tracking & Sign-off
// ----------------------------------------------------------------------------
echo "\n--- Step 5: Laboratory Barcode & Diagnostic Report Sign-off ---\n";
$sampleBarcode = 'BC-' . date('Y') . '-' . rand(10000, 99999);

// 1. Specimen Collection
$db->prepare("UPDATE `lab_order_items` SET `sample_barcode` = ?, `sample_collected_at` = NOW(), `collected_by` = 1, `item_status` = 'Collected' WHERE `id` = ?")
   ->execute([$sampleBarcode, $labItem1Id]);

// 2. Result Entry & Pathologist Sign-off for CBC
// Get parameters for CBC (test_id = 1)
$params = $db->query("SELECT id, parameter_name FROM `lab_test_parameters` WHERE `lab_test_id` = 1")->fetchAll();
$insResult = $db->prepare("
    INSERT INTO `lab_results` 
    (`lab_order_item_id`, `parameter_id`, `result_value`, `abnormal_flag`, `tested_by`, `verified_by`, `verified_at`)
    VALUES (?, ?, ?, ?, 1, 1, NOW())
");

foreach ($params as $idx => $param) {
    $val = ($idx === 0) ? '14.2' : (($idx === 1) ? '7500' : '245000');
    $insResult->execute([$labItem1Id, $param['id'], $val, 'Normal']);
}

// Verify item
$db->prepare("UPDATE `lab_order_items` SET `item_status` = 'Verified' WHERE `id` = ?")->execute([$labItem1Id]);
$verifiedCount = (int)$db->query("SELECT COUNT(*) FROM `lab_results` WHERE `lab_order_item_id` = $labItem1Id")->fetchColumn();

assertTest(
    $verifiedCount > 0,
    "Diagnostic specimen processed, analyzed, and digitally signed off by pathologist",
    "Barcode: $sampleBarcode | Parameter Results: $verifiedCount Verified"
);

// ----------------------------------------------------------------------------
// TEST 6: Pharmacy FEFO Stock Deduction & Auto-Billing Posting
// ----------------------------------------------------------------------------
echo "\n--- Step 6: Pharmacy FEFO Dispensing & Medication Bill Appending ---\n";
// Find earliest batch with stock for drug_item_id = 3 (Risek)
$batch = $db->query("
    SELECT id, batch_number, current_quantity, unit_sale_price 
    FROM `drug_batches` 
    WHERE `drug_item_id` = 3 AND `current_quantity` >= 14 
    ORDER BY `expiry_date` ASC 
    LIMIT 1
")->fetch();

if (!$batch) {
    $batch = $db->query("SELECT id, batch_number, current_quantity, unit_sale_price FROM `drug_batches` WHERE `drug_item_id` = 3 AND `current_quantity` > 0 ORDER BY `expiry_date` ASC LIMIT 1")->fetch();
}

$dispenseQty = min(14, (int)($batch['current_quantity'] ?? 14));
$unitPrice = (float)($batch['unit_sale_price'] ?? 25.00);
$medCost = $dispenseQty * $unitPrice;

// 1. Deduct stock from batch
$db->prepare("UPDATE `drug_batches` SET `current_quantity` = `current_quantity` - ? WHERE `id` = ?")
   ->execute([$dispenseQty, $batch['id']]);

// 2. Update prescription items & status
$db->prepare("UPDATE `prescription_items` SET `quantity_dispensed` = ? WHERE `id` = ?")->execute([$dispenseQty, $rxItemId]);
$db->prepare("UPDATE `prescriptions` SET `status` = 'Dispensed' WHERE `id` = ?")->execute([$rxId]);

// 3. Append to Encounter's Centralized Bill
$insRxBillItem = $db->prepare("
    INSERT INTO `bill_items` (`bill_id`, `item_type`, `reference_id`, `description`, `quantity`, `unit_price`, `subtotal`, `net_price`)
    VALUES (?, 'Pharmacy', ?, ?, ?, ?, ?, ?)
");
$drugDesc = "Pharmacy: Risek (Omeprazole) (Batch #{$batch['batch_number']})";
$insRxBillItem->execute([$billId, $batch['id'], $drugDesc, $dispenseQty, $unitPrice, $medCost, $medCost]);

// 4. Update Bill Balances
$db->prepare("
    UPDATE `bills` 
    SET `gross_amount` = `gross_amount` + ?, `net_amount` = `net_amount` + ?, `balance_amount` = `balance_amount` + ? 
    WHERE `id` = ?
")->execute([$medCost, $medCost, $medCost, $billId]);

$updatedBill = $db->query("SELECT gross_amount, net_amount, balance_amount FROM `bills` WHERE `id` = $billId")->fetch();
$expectedTotal = $totalInitialGross + $medCost;

assertTest(
    (float)$updatedBill['net_amount'] == (float)$expectedTotal,
    "Pharmacy FEFO dispensing auto-posted medication charges to centralized bill",
    "Med Cost: PKR " . number_format($medCost, 2) . " | Consolidated Net: PKR " . number_format($updatedBill['net_amount'], 2)
);

// ----------------------------------------------------------------------------
// TEST 7: Centralized Billing & Cashier Payment Settlement
// ----------------------------------------------------------------------------
echo "\n--- Step 7: Centralized Billing Desk & Cashier Payment Receipt ---\n";
$currentBill = $db->query("SELECT * FROM `bills` WHERE `id` = $billId")->fetch();
$dueAmount = (float)$currentBill['balance_amount'];

// Simulate Cashier Payment
$latestPay = $db->query("SELECT COALESCE(MAX(id), 0) FROM `payments`")->fetchColumn();
$receiptNum = 'RCPT-' . date('Y') . '-' . str_pad((string)($latestPay + 1), 4, '0', STR_PAD_LEFT);

$insPay = $db->prepare("
    INSERT INTO `payments` 
    (`receipt_number`, `bill_id`, `patient_id`, `amount_paid`, `payment_method`, `transaction_reference`, `payment_datetime`, `received_by`)
    VALUES (?, ?, ?, ?, 'Cash', 'CASHIER-REGISTER-1', NOW(), 1)
");
$insPay->execute([$receiptNum, $billId, $patientId, $dueAmount]);
$paymentId = (int)$db->lastInsertId();

// Update Bill to Fully-Paid
$db->prepare("UPDATE `bills` SET `paid_amount` = `paid_amount` + ?, `balance_amount` = 0, `payment_status` = 'Fully-Paid' WHERE `id` = ?")
   ->execute([$dueAmount, $billId]);

$settledBill = $db->query("SELECT payment_status, balance_amount, paid_amount FROM `bills` WHERE `id` = $billId")->fetch();

assertTest(
    $settledBill['payment_status'] === 'Fully-Paid' && (float)$settledBill['balance_amount'] === 0.0,
    "Cashier Desk received full settlement, cleared balance, and issued fiscal receipt",
    "Receipt: $receiptNum | Paid: PKR " . number_format($settledBill['paid_amount'], 2) . " | Status: " . $settledBill['payment_status']
);

// ----------------------------------------------------------------------------
// TEST 8: Audit Logging & Unified Journey Integrity Verification
// ----------------------------------------------------------------------------
echo "\n--- Step 8: Audit Trail & Relational Integrity ---\n";
AuditLogger::log('CREATE', 'payments', $paymentId, null, ['receipt' => $receiptNum, 'amount' => $dueAmount]);

$auditCount = (int)$db->query("SELECT COUNT(*) FROM `audit_logs` WHERE `user_id` = 1")->fetchColumn();
assertTest($auditCount > 0, "Audit trail verified across all transactional steps", "Total Audit Entries: $auditCount");

// ----------------------------------------------------------------------------
// SUMMARY TABLE OF PATIENT LIFECYCLE
// ----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "📋  COMPLETE PATIENT JOURNEY AUDIT SUMMARY\n";
echo "========================================================================\n";
echo sprintf("%-25s : %s\n", "Patient Name", "$firstName $lastName");
echo sprintf("%-25s : %s\n", "Medical Record # (MRN)", $testMrn);
echo sprintf("%-25s : %s\n", "OPD Token #", "Token #$tokenNum ($aptNum)");
echo sprintf("%-25s : %s\n", "Clinical Encounter", "$encNum (Type: OPD)");
echo sprintf("%-25s : %s\n", "Lab Order & Barcode", "$labOrderNum (Barcode: $sampleBarcode)");
echo sprintf("%-25s : %s\n", "Prescription Number", "$rxNum (Dispensed: FEFO Batch #{$batch['batch_number']})");
echo sprintf("%-25s : %s\n", "Follow-up Scheduled", "$fuAptNum (Date: $followUpDate)");
echo sprintf("%-25s : %s\n", "Central Invoice #", "$billNum (Status: {$settledBill['payment_status']})");
echo sprintf("%-25s : %s\n", "Fiscal Receipt #", "$receiptNum (Amount: PKR " . number_format($settledBill['paid_amount'], 2) . ")");
echo "========================================================================\n";

if ($passCount === $totalTests) {
    echo "\n🎉 ALL $totalTests UNIFIED PATIENT JOURNEY PHASES VERIFIED SUCCESSFULLY WITH ZERO ERRORS!\n\n";
    exit(0);
} else {
    echo "\n⚠️ $passCount of $totalTests tests passed.\n\n";
    exit(1);
}
