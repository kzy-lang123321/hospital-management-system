<?php
/**
 * Test Patient Registration with Instant Appointment Booking
 */
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

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/AuditLogger.php';

$db = Database::getConnection();

echo "=== TESTING PATIENT REGISTRATION WITH INSTANT APPOINTMENT BOOKING ===" . PHP_EOL;

// Generate MRN & Appointment Number
$mrn = generate_patient_mrn($db);
$aptNum = generate_appointment_number($db, date('Y-m-d'));

echo "Proposed MRN: $mrn" . PHP_EOL;
echo "Proposed Appointment Number: $aptNum" . PHP_EOL;

// Check existing appointments
$existingApts = $db->query("SELECT appointment_number FROM appointments")->fetchAll(PDO::FETCH_COLUMN);
echo "Existing Appointment Numbers in DB: " . implode(', ', $existingApts) . PHP_EOL;

if (in_array($aptNum, $existingApts)) {
    die("[FAIL] Proposed appointment number $aptNum ALREADY EXISTS!" . PHP_EOL);
} else {
    echo "[PASS] Proposed appointment number $aptNum is completely unique!" . PHP_EOL;
}

// Simulate patient registration transaction
$db->beginTransaction();
try {
    $firstName = "TestPatient";
    $lastName = "Automation";
    $gender = "Male";
    $age = 35;
    $bloodGroup = "O+";
    $phone = "0300-9998877";

    $stmt = $db->prepare("
        INSERT INTO `patients` 
        (`mrn`, `first_name`, `last_name`, `gender`, `age`, `blood_group`, `phone_primary`, `registered_by`)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$mrn, $firstName, $lastName, $gender, $age, $bloodGroup, $phone]);
    $newPatId = (int)$db->lastInsertId();

    // Instant appointment booking for doctor 2 (or 3)
    $docId = 2;
    $aptDate = date('Y-m-d');
    $slotTime = '10:00:00';
    $visitReason = 'General Checkup';

    $docDept = $db->prepare("SELECT department_id FROM `users` WHERE `id` = ?");
    $docDept->execute([$docId]);
    $deptId = (int)$docDept->fetchColumn();

    $tokenStmt = $db->prepare("SELECT COALESCE(MAX(token_number), 0) FROM `appointments` WHERE `doctor_id` = ? AND `appointment_date` = ?");
    $tokenStmt->execute([$docId, $aptDate]);
    $tokenNum = (int)$tokenStmt->fetchColumn() + 1;

    $insApt = $db->prepare("
        INSERT INTO `appointments` 
        (`appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `slot_time`, `token_number`, `type`, `status`, `reason_for_visit`, `created_by`)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'New', 'Scheduled', ?, 1)
    ");
    $insApt->execute([$aptNum, $newPatId, $docId, $deptId, $aptDate, $slotTime, $tokenNum, $visitReason]);
    $newAptId = (int)$db->lastInsertId();

    $db->commit();
    echo "[PASS] Patient successfully registered with ID $newPatId, MRN $mrn, and Appointment $aptNum (Apt ID: $newAptId, Token #$tokenNum)!" . PHP_EOL;

} catch (Exception $e) {
    $db->rollBack();
    die("[FAIL] Registration failed with error: " . $e->getMessage() . PHP_EOL);
}

// Clean up test record
$db->query("DELETE FROM `appointments` WHERE `id` = $newAptId");
$db->query("DELETE FROM `patients` WHERE `id` = $newPatId");
echo "[PASS] Test cleanup completed cleanly." . PHP_EOL;
echo "=== ALL REGISTRATION & APPOINTMENT CHECKS PASSED 100%! ===" . PHP_EOL;
