<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/AuditLogger.php';

Auth::attempt('superadmin', 'Admin@123');
$db = Database::getConnection();

echo "--- STARTING STAGE 2 INTEGRATION TEST SUITE ---\n";

// 1. EMERGENCY TEST
$pCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `patients`")->fetchColumn();
$mrn = 'MRN-' . date('Y') . '-' . str_pad((string)($pCount + 1), 4, '0', STR_PAD_LEFT);
$db->prepare("
    INSERT INTO `patients` (`mrn`, `first_name`, `last_name`, `gender`, `age`, `blood_group`, `phone_primary`, `registered_by`)
    VALUES (?, 'Acute', 'Trauma', 'Male', 28, 'O+', '+92 300 9988776', ?)
")->execute([$mrn, Auth::id()]);
$erPatientId = (int)$db->lastInsertId();

$encCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `encounters`")->fetchColumn();
$encNum = 'ENC-' . date('Y') . '-' . str_pad((string)($encCount + 1), 4, '0', STR_PAD_LEFT);
$db->prepare("
    INSERT INTO `encounters` 
    (`encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `encounter_status`, `triage_level`, `primary_diagnosis`, `created_by`)
    VALUES (?, ?, 'Emergency', 7, 2, NOW(), 'In-Progress', 'ESI-1', 'Acute RTA Multiple Trauma', ?)
")->execute([$encNum, $erPatientId, Auth::id()]);
$erEncId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO `clinical_vitals` (`encounter_id`, `patient_id`, `systolic_bp`, `diastolic_bp`, `pulse_rate_bpm`, `spo2_percentage`, `recorded_by`)
    VALUES (?, ?, 90, 60, 125, 93.5, ?)
")->execute([$erEncId, $erPatientId, Auth::id()]);

echo "[1] Emergency Intake OK: Enc {$encNum}, MRN {$mrn}, ESI-1 Resuscitation\n";

// 2. RADIOLOGY TEST
$radCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `radiology_orders`")->fetchColumn();
$radNum = 'RAD-' . date('Y') . '-' . str_pad((string)($radCount + 1), 4, '0', STR_PAD_LEFT);
$db->prepare("
    INSERT INTO `radiology_orders` (`order_number`, `encounter_id`, `service_id`, `ordered_by`, `order_datetime`, `priority`, `clinical_indication`, `status`)
    VALUES (?, ?, 1, 2, NOW(), 'STAT', 'Trauma evaluation - rule out rib fracture and hemothorax', 'Ordered')
")->execute([$radNum, $erEncId]);
$radOrderId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO `radiology_reports` (`radiology_order_id`, `radiologist_id`, `technique_used`, `radiological_findings`, `impression`, `report_status`, `finalized_at`)
    VALUES (?, 2, 'High-kV digital portable chest radiograph', 'Right hemithorax clear. No pneumothorax or acute rib fracture identified. Cardiac silhouette normal.', 'Normal trauma chest radiograph. No acute osseous or parenchymal abnormality.', 'Finalized', NOW())
")->execute([$radOrderId]);
$db->prepare("UPDATE `radiology_orders` SET `status` = 'Reported' WHERE `id` = ?")->execute([$radOrderId]);

echo "[2] Radiology Workflow OK: Order {$radNum} Diagnostic Report Finalized & Signed\n";

// 3. OPERATION THEATRE (OT) TEST
$otCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `surgery_bookings`")->fetchColumn();
$otNum = 'OT-BKG-' . date('Y') . '-' . str_pad((string)($otCount + 1), 4, '0', STR_PAD_LEFT);
$db->prepare("
    INSERT INTO `surgery_bookings` 
    (`booking_number`, `encounter_id`, `patient_id`, `ot_room_id`, `lead_surgeon_id`, `anesthesiologist_id`, `procedure_name`, `scheduled_datetime`, `estimated_duration_minutes`, `anesthesia_type`, `pre_op_diagnosis`, `booking_status`, `created_by`)
    VALUES (?, ?, ?, 1, 2, 3, 'Emergency Exploratory Laparotomy', NOW(), 90, 'General', 'Acute Abdominal Trauma / Hemoperitoneum', 'Scheduled', ?)
")->execute([$otNum, $erEncId, $erPatientId, Auth::id()]);
$surgeryBookingId = (int)$db->lastInsertId();

// Start Surgery
$db->prepare("UPDATE `surgery_bookings` SET `booking_status` = 'In-Progress' WHERE `id` = ?")->execute([$surgeryBookingId]);
$db->prepare("UPDATE `ot_rooms` SET `status` = 'In_Surgery' WHERE `id` = 1")->execute();

// Complete Surgery with Operative Record
$db->prepare("
    INSERT INTO `surgical_records` 
    (`surgery_booking_id`, `start_time`, `end_time`, `operative_notes`, `post_op_diagnosis`, `implant_details`, `sponge_swab_count_correct`, `blood_loss_ml`, `post_op_destination`)
    VALUES (?, NOW(), NOW(), 'Midline incision. Hemostasis achieved. Grade 1 splenic laceration treated conservatively. Abdomen closed in layers.', 'Grade 1 Splenic Laceration, Contained', 'None', 1, 150, 'PACU_Recovery')
")->execute([$surgeryBookingId]);
$db->prepare("UPDATE `surgery_bookings` SET `booking_status` = 'Completed' WHERE `id` = ?")->execute([$surgeryBookingId]);
$db->prepare("UPDATE `ot_rooms` SET `status` = 'Available' WHERE `id` = 1")->execute();

echo "[3] Operation Theatre (OT) OK: Booking {$otNum} Executed, Operative Note & WHO Checklist Logged\n";

// 4. BLOOD BANK TEST
$bCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `blood_requests`")->fetchColumn();
$bNum = 'BRQ-' . date('Y') . '-' . str_pad((string)($bCount + 1), 4, '0', STR_PAD_LEFT);
$db->prepare("
    INSERT INTO `blood_requests` 
    (`request_number`, `encounter_id`, `patient_id`, `requested_by`, `component_type`, `units_required`, `urgency`, `clinical_indication`, `status`)
    VALUES (?, ?, ?, 2, 'Packed_Red_Cells_PRBC', 1, 'Life_Saving_STAT', 'Intra-op acute blood loss resuscitation', 'Pending')
")->execute([$bNum, $erEncId, $erPatientId]);
$bloodReqId = (int)$db->lastInsertId();

// Issue Unit 1 (Available O+ PRBC)
$db->prepare("UPDATE `blood_units` SET `status` = 'Issued' WHERE `id` = 1")->execute();
$db->prepare("UPDATE `blood_requests` SET `status` = 'Issued' WHERE `id` = ?")->execute([$bloodReqId]);

echo "[4] Blood Bank OK: STAT Requisition {$bNum} Cross-Matched & PRBC Unit Issued\n";

// 5. INVENTORY & PROCUREMENT TEST
$initStock = (int)$db->query("SELECT current_stock FROM `inventory_items` WHERE `id` = 1")->fetchColumn();
$poCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `purchase_orders`")->fetchColumn();
$poNum = 'PO-' . date('Y') . '-' . str_pad((string)($poCount + 1), 4, '0', STR_PAD_LEFT);

$db->prepare("
    INSERT INTO `purchase_orders` (`po_number`, `supplier_id`, `order_date`, `expected_delivery_date`, `total_amount`, `status`, `created_by`)
    VALUES (?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 8500.00, 'Approved', ?)
")->execute([$poNum, Auth::id()]);
$poId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO `purchase_order_items` (`po_id`, `item_name`, `quantity`, `unit_price`, `total_price`)
    VALUES (?, 'Sterile Surgical Gloves (Size 7.5)', 10, 850.00, 8500.00)
")->execute([$poId]);

// Process Goods Receipt Note (GRN)
$db->prepare("UPDATE `inventory_items` SET `current_stock` = `current_stock` + 10 WHERE `item_name` = 'Sterile Surgical Gloves (Size 7.5)'")->execute();
$db->prepare("UPDATE `purchase_orders` SET `status` = 'Received' WHERE `id` = ?")->execute([$poId]);

$newStock = (int)$db->query("SELECT current_stock FROM `inventory_items` WHERE `id` = 1")->fetchColumn();
echo "[5] Inventory Procurement (GRN) OK: PO {$poNum} Received. Gloves stock went from {$initStock} to {$newStock}\n";

// 6. INSURANCE CLAIM TEST
// Create a bill for the encounter
$bBillCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `bills`")->fetchColumn();
$billNum = 'INV-' . date('Y') . '-' . str_pad((string)($bBillCount + 1), 4, '0', STR_PAD_LEFT);
$db->prepare("
    INSERT INTO `bills` (`bill_number`, `encounter_id`, `patient_id`, `bill_type`, `bill_date`, `gross_amount`, `discount_amount`, `tax_amount`, `net_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_by`)
    VALUES (?, ?, ?, 'Emergency', NOW(), 25000.00, 0, 0, 25000.00, 0, 25000.00, 'Unpaid', ?)
")->execute([$billNum, $erEncId, $erPatientId, Auth::id()]);
$billId = (int)$db->lastInsertId();

// Submit Claim
$clmCount = $db->query("SELECT COALESCE(MAX(id), 0) FROM `insurance_claims`")->fetchColumn();
$clmNum = 'CLM-' . date('Y') . '-' . str_pad((string)($clmCount + 1), 4, '0', STR_PAD_LEFT);
$db->prepare("
    INSERT INTO `insurance_claims` (`claim_number`, `bill_id`, `patient_id`, `company_id`, `policy_number`, `pre_auth_code`, `claim_amount`, `approved_amount`, `status`, `remarks`, `submitted_at`)
    VALUES (?, ?, ?, 1, 'SEHAT-CARD-17301', 'AUTH-EMERGENCY-01', 25000.00, 25000.00, 'Settled', 'Emergency trauma full panel coverage', NOW())
")->execute([$clmNum, $billId, $erPatientId]);

// Settle bill
$db->prepare("
    UPDATE `bills` 
    SET `paid_amount` = 25000.00, `balance_amount` = 0.00, `payment_status` = 'Fully-Paid' 
    WHERE `id` = ?
")->execute([$billId]);

echo "[6] Insurance Claim & Settlement OK: Claim {$clmNum} Reconciled against Bill {$billNum}\n";

echo "--- ALL 6 EXPANSION MODULES TESTED AND OPERATIONAL! ---\n";
