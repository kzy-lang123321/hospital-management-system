<?php
/**
 * Hospital Management System (HMS) - Root Front Controller & Router
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/AuditLogger.php';

$action = $_GET['action'] ?? null;
$page = $_GET['page'] ?? 'dashboard';

// ----------------------------------------------------------------------------
// ACTION DISPATCHER (POST / STATE MUTATION HANDLERS)
// ----------------------------------------------------------------------------

if ($action !== null) {

    // 1. User Login Action
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $username = clean_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::attempt($username, $password)) {
            set_flash('success', 'Authenticated successfully. Welcome to ' . APP_NAME);
            header("Location: index.php?page=dashboard");
            exit;
        } else {
            set_flash('danger', 'Invalid username or password. Please check your credentials.');
            header("Location: index.php?page=login");
            exit;
        }
    }

    // 2. User Logout Action
    if ($action === 'logout') {
        Auth::logout();
        set_flash('info', 'You have been securely signed out.');
        header("Location: index.php?page=login");
        exit;
    }

    // ALL SUBSEQUENT ACTIONS REQUIRE AUTHENTICATION
    Auth::requireLogin();

    $db = Database::getConnection();

    // 3. Patient Registration Action
    if ($action === 'patient_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse', 'cashier']);

        $firstName = clean_input($_POST['first_name'] ?? '');
        $lastName = clean_input($_POST['last_name'] ?? '');
        $gender = clean_input($_POST['gender'] ?? 'Male');
        $dob = !empty($_POST['dob']) ? clean_input($_POST['dob']) : null;
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $bloodGroup = clean_input($_POST['blood_group'] ?? 'Unknown');
        $maritalStatus = clean_input($_POST['marital_status'] ?? 'Single');
        $nationalId = clean_input($_POST['national_id'] ?? '');
        $phonePrimary = clean_input($_POST['phone_primary'] ?? '');
        $phoneSecondary = clean_input($_POST['phone_secondary'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $city = clean_input($_POST['city'] ?? 'Peshawar');
        $address = clean_input($_POST['address'] ?? '');
        $emergencyName = clean_input($_POST['emergency_contact_name'] ?? '');
        $emergencyRelation = clean_input($_POST['emergency_contact_relation'] ?? '');
        $emergencyPhone = clean_input($_POST['emergency_contact_phone'] ?? '');

        // Generate next MRN atomically
        $latestId = $db->query("SELECT COALESCE(MAX(id), 0) FROM `patients`")->fetchColumn();
        $mrn = 'MRN-' . date('Y') . '-' . str_pad((string)($latestId + 1), 4, '0', STR_PAD_LEFT);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO `patients` 
                (`mrn`, `first_name`, `last_name`, `gender`, `dob`, `age`, `blood_group`, `marital_status`, `national_id`, `phone_primary`, `phone_secondary`, `email`, `address`, `city`, `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_phone`, `registered_by`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $mrn, $firstName, $lastName, $gender, $dob, $age, $bloodGroup, $maritalStatus,
                $nationalId, $phonePrimary, $phoneSecondary, $email, $address, $city,
                $emergencyName, $emergencyRelation, $emergencyPhone, Auth::id()
            ]);
            $newPatientId = (int)$db->lastInsertId();

            // Record initial allergy if provided
            $allergenName = clean_input($_POST['allergen_name'] ?? '');
            if (!empty($allergenName)) {
                $allergyType = clean_input($_POST['allergy_type'] ?? 'Drug');
                $allergySeverity = clean_input($_POST['allergy_severity'] ?? 'Moderate');
                $algStmt = $db->prepare("
                    INSERT INTO `patient_allergies` (`patient_id`, `allergy_type`, `allergen_name`, `severity`, `recorded_by`)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $algStmt->execute([$newPatientId, $allergyType, $allergenName, $allergySeverity, Auth::id()]);
            }

            AuditLogger::log('CREATE', 'patients', $newPatientId, null, ['mrn' => $mrn, 'name' => "$firstName $lastName"]);
            $db->commit();

            set_flash('success', "Patient record registered successfully with unique MRN: $mrn");
            header("Location: index.php?page=patient_profile&id=" . $newPatientId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Failed to register patient: " . $e->getMessage());
            header("Location: index.php?page=patient_create");
            exit;
        }
    }

    // 4. Appointment Booking Action
    if ($action === 'appointment_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'receptionist', 'doctor']);

        $patientId = (int)($_POST['patient_id'] ?? 0);
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $appointmentDate = clean_input($_POST['appointment_date'] ?? date('Y-m-d'));
        $slotTime = clean_input($_POST['slot_time'] ?? '10:00:00');
        $type = clean_input($_POST['type'] ?? 'New');
        $reason = clean_input($_POST['reason_for_visit'] ?? '');

        // Fetch Doctor's Department
        $docDept = $db->prepare("SELECT department_id FROM `users` WHERE `id` = ?");
        $docDept->execute([$doctorId]);
        $deptId = (int)$docDept->fetchColumn();

        // Calculate token for that doctor on that date
        $tokenStmt = $db->prepare("SELECT COALESCE(MAX(token_number), 0) FROM `appointments` WHERE `doctor_id` = ? AND `appointment_date` = ?");
        $tokenStmt->execute([$doctorId, $appointmentDate]);
        $tokenNumber = (int)$tokenStmt->fetchColumn() + 1;

        $aptNum = 'APT-' . date('Ymd') . '-' . str_pad((string)$tokenNumber, 3, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO `appointments` 
            (`appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `slot_time`, `token_number`, `type`, `status`, `reason_for_visit`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?)
        ");
        $stmt->execute([$aptNum, $patientId, $doctorId, $deptId, $appointmentDate, $slotTime, $tokenNumber, $type, $reason, Auth::id()]);

        AuditLogger::log('CREATE', 'appointments', $db->lastInsertId(), null, ['apt_number' => $aptNum]);
        set_flash('success', "Appointment booked successfully with Token #$tokenNumber ($aptNum).");
        header("Location: index.php?page=appointments&date=" . $appointmentDate);
        exit;
    }

    // 5. Appointment Check-in Action
    if ($action === 'appointment_checkin') {
        Auth::requireRole(['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse']);
        $aptId = (int)($_GET['id'] ?? 0);

        $aptStmt = $db->prepare("SELECT * FROM `appointments` WHERE `id` = ?");
        $aptStmt->execute([$aptId]);
        $apt = $aptStmt->fetch();

        if ($apt) {
            $db->beginTransaction();
            // Update appointment status
            $upApt = $db->prepare("UPDATE `appointments` SET `status` = 'Checked-In' WHERE `id` = ?");
            $upApt->execute([$aptId]);

            // Create active OPD Encounter
            $latestEnc = $db->query("SELECT COALESCE(MAX(id), 0) FROM `encounters`")->fetchColumn();
            $encNum = 'ENC-' . date('Y') . '-' . str_pad((string)($latestEnc + 1), 4, '0', STR_PAD_LEFT);

            $newEnc = $db->prepare("
                INSERT INTO `encounters` 
                (`encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `encounter_status`, `created_by`)
                VALUES (?, ?, 'OPD', ?, ?, NOW(), 'In-Progress', ?)
            ");
            $newEnc->execute([$encNum, $apt['patient_id'], $apt['department_id'], $apt['doctor_id'], Auth::id()]);
            $encId = (int)$db->lastInsertId();

            $db->commit();
            set_flash('success', "Patient checked in and queued for consultation (#$encNum).");
            header("Location: index.php?page=opd_consultation&encounter_id=" . $encId);
            exit;
        }
    }

    // 6. Save OPD Consultation (SOAP + Rx + Labs)
    if ($action === 'opd_consultation_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor']);

        $encounterId = (int)($_POST['encounter_id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);

        $chiefComplaint = clean_input($_POST['chief_complaint'] ?? '');
        $physicalExam = clean_input($_POST['physical_examination'] ?? '');
        $diagnosis = clean_input($_POST['provisional_diagnosis'] ?? '');
        $icdCode = clean_input($_POST['icd10_code'] ?? '');
        $clinicalAdvice = clean_input($_POST['clinical_advice'] ?? '');
        $disposition = clean_input($_POST['disposition'] ?? 'Discharged_Home');

        try {
            $db->beginTransaction();

            // 1. Save / Update Vitals
            $systolic = !empty($_POST['systolic_bp']) ? (int)$_POST['systolic_bp'] : null;
            $diastolic = !empty($_POST['diastolic_bp']) ? (int)$_POST['diastolic_bp'] : null;
            $pulse = !empty($_POST['pulse_rate_bpm']) ? (int)$_POST['pulse_rate_bpm'] : null;
            $temp = !empty($_POST['temperature_celsius']) ? (float)$_POST['temperature_celsius'] : null;
            $spo2 = !empty($_POST['spo2_percentage']) ? (int)$_POST['spo2_percentage'] : null;
            $glucose = !empty($_POST['blood_glucose_mg_dl']) ? (int)$_POST['blood_glucose_mg_dl'] : null;
            $weight = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;
            $pain = !empty($_POST['pain_scale']) ? (int)$_POST['pain_scale'] : 0;

            $vitStmt = $db->prepare("
                INSERT INTO `clinical_vitals` 
                (`encounter_id`, `patient_id`, `recorded_by`, `temperature_celsius`, `pulse_rate_bpm`, `systolic_bp`, `diastolic_bp`, `spo2_percentage`, `blood_glucose_mg_dl`, `weight_kg`, `pain_scale`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $vitStmt->execute([$encounterId, $patientId, Auth::id(), $temp, $pulse, $systolic, $diastolic, $spo2, $glucose, $weight, $pain]);

            // 2. Save / Update Consultation Notes
            $delNote = $db->prepare("DELETE FROM `consultation_notes` WHERE `encounter_id` = ?");
            $delNote->execute([$encounterId]);

            $noteStmt = $db->prepare("
                INSERT INTO `consultation_notes` 
                (`encounter_id`, `doctor_id`, `chief_complaint`, `physical_examination`, `provisional_diagnosis`, `icd10_code`, `clinical_advice`)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $noteStmt->execute([$encounterId, Auth::id(), $chiefComplaint, $physicalExam, $diagnosis, $icdCode, $clinicalAdvice]);

            // 3. Save Prescriptions if any drug chosen
            $rxDrugs = $_POST['rx_drug_id'] ?? [];
            $rxDoses = $_POST['rx_dose'] ?? [];
            $rxFreqs = $_POST['rx_frequency'] ?? [];
            $rxDays = $_POST['rx_days'] ?? [];
            $rxInstructions = $_POST['rx_instructions'] ?? [];

            $validRxItems = [];
            foreach ($rxDrugs as $idx => $drugId) {
                if (!empty($drugId)) {
                    $validRxItems[] = [
                        'drug_id' => (int)$drugId,
                        'dose' => clean_input($rxDoses[$idx] ?? '1 Tab'),
                        'frequency' => clean_input($rxFreqs[$idx] ?? '1-0-1'),
                        'days' => (int)($rxDays[$idx] ?? 5),
                        'instructions' => clean_input($rxInstructions[$idx] ?? 'After meals')
                    ];
                }
            }

            if (!empty($validRxItems)) {
                $latestRx = $db->query("SELECT COALESCE(MAX(id), 0) FROM `prescriptions`")->fetchColumn();
                $rxNumber = 'RX-' . date('Y') . '-' . str_pad((string)($latestRx + 1), 4, '0', STR_PAD_LEFT);

                $rxStmt = $db->prepare("
                    INSERT INTO `prescriptions` (`prescription_number`, `encounter_id`, `doctor_id`, `status`, `clinical_notes`)
                    VALUES (?, ?, ?, 'Pending', ?)
                ");
                $rxStmt->execute([$rxNumber, $encounterId, Auth::id(), $clinicalAdvice]);
                $rxId = (int)$db->lastInsertId();

                $rxItemStmt = $db->prepare("
                    INSERT INTO `prescription_items` 
                    (`prescription_id`, `drug_item_id`, `dosage`, `frequency`, `duration_days`, `instructions`, `quantity_prescribed`)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($validRxItems as $vi) {
                    $qtyPrescribed = max(1, $vi['days'] * 2);
                    $rxItemStmt->execute([$rxId, $vi['drug_id'], $vi['dose'], $vi['frequency'], $vi['days'], $vi['instructions'], $qtyPrescribed]);
                }
            }

            // 4. Save Lab Orders if selected
            $labTestsChosen = $_POST['lab_test_ids'] ?? [];
            if (!empty($labTestsChosen)) {
                $latestLab = $db->query("SELECT COALESCE(MAX(id), 0) FROM `lab_orders`")->fetchColumn();
                $labOrderNum = 'LAB-' . date('Y') . '-' . str_pad((string)($latestLab + 1), 4, '0', STR_PAD_LEFT);

                $labStmt = $db->prepare("
                    INSERT INTO `lab_orders` (`order_number`, `encounter_id`, `ordered_by`, `order_datetime`, `status`)
                    VALUES (?, ?, ?, NOW(), 'Ordered')
                ");
                $labStmt->execute([$labOrderNum, $encounterId, Auth::id()]);
                $labOrderId = (int)$db->lastInsertId();

                $itemStmt = $db->prepare("INSERT INTO `lab_order_items` (`lab_order_id`, `lab_test_id`, `item_status`) VALUES (?, ?, 'Pending')");
                foreach ($labTestsChosen as $ltId) {
                    $itemStmt->execute([$labOrderId, (int)$ltId]);
                }
            }

            // 5. Update Encounter Status & Diagnosis
            $encStatus = ($disposition === 'In-Progress') ? 'In-Progress' : 'Completed';
            $encUpdate = $db->prepare("
                UPDATE `encounters` 
                SET `primary_diagnosis` = ?, `encounter_status` = ?, `discharge_disposition` = ?, `discharge_datetime` = IF(? = 'Completed', NOW(), NULL)
                WHERE `id` = ?
            ");
            $encUpdate->execute([$diagnosis, $encStatus, $disposition, $encStatus, $encounterId]);

            // 6. Create Initial Bill if not already generated
            $billCheck = $db->prepare("SELECT id FROM `bills` WHERE `encounter_id` = ?");
            $billCheck->execute([$encounterId]);
            if (!$billCheck->fetch()) {
                $docFeeStmt = $db->prepare("SELECT consultation_fee FROM `users` WHERE `id` = ?");
                $docFeeStmt->execute([Auth::id()]);
                $docFee = (float)$docFeeStmt->fetchColumn();

                $latestBill = $db->query("SELECT COALESCE(MAX(id), 0) FROM `bills`")->fetchColumn();
                $billNum = 'INV-' . date('Y') . '-' . str_pad((string)($latestBill + 1), 4, '0', STR_PAD_LEFT);

                $billInsert = $db->prepare("
                    INSERT INTO `bills` 
                    (`bill_number`, `encounter_id`, `patient_id`, `bill_type`, `bill_date`, `gross_amount`, `net_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_by`)
                    VALUES (?, ?, ?, 'OPD', NOW(), ?, ?, 0.00, ?, 'Unpaid', ?)
                ");
                $billInsert->execute([$billNum, $encounterId, $patientId, $docFee, $docFee, $docFee, Auth::id()]);
                $newBillId = (int)$db->lastInsertId();

                $billItemInsert = $db->prepare("
                    INSERT INTO `bill_items` (`bill_id`, `item_type`, `description`, `quantity`, `unit_price`, `subtotal`, `net_price`)
                    VALUES (?, 'Consultation', 'Specialist Doctor Consultation', 1, ?, ?, ?)
                ");
                $billItemInsert->execute([$newBillId, $docFee, $docFee, $docFee]);
            }

            AuditLogger::log('UPDATE', 'encounters', $encounterId, null, ['diagnosis' => $diagnosis, 'status' => $encStatus]);
            $db->commit();

            set_flash('success', "Consultation completed and clinical orders processed successfully.");
            header("Location: index.php?page=opd_consultation");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Failed to save consultation: " . $e->getMessage());
            header("Location: index.php?page=opd_consultation&encounter_id=" . $encounterId);
            exit;
        }
    }

    // 7. IPD Inpatient Admission Action
    if ($action === 'ipd_admit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'nurse', 'doctor', 'receptionist']);

        $patientId = (int)($_POST['patient_id'] ?? 0);
        $bedId = (int)($_POST['bed_id'] ?? 0);
        $admittingDoctorId = (int)($_POST['admitting_doctor_id'] ?? 0);
        $admissionReason = clean_input($_POST['admission_reason'] ?? '');

        try {
            $db->beginTransaction();

            // Create IPD Encounter
            $latestEnc = $db->query("SELECT COALESCE(MAX(id), 0) FROM `encounters`")->fetchColumn();
            $encNum = 'ENC-' . date('Y') . '-' . str_pad((string)($latestEnc + 1), 4, '0', STR_PAD_LEFT);

            $encStmt = $db->prepare("
                INSERT INTO `encounters` 
                (`encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `encounter_status`, `primary_diagnosis`, `created_by`)
                VALUES (?, ?, 'IPD', 10, ?, NOW(), 'Admitted', ?, ?)
            ");
            $encStmt->execute([$encNum, $patientId, $admittingDoctorId, $admissionReason, Auth::id()]);
            $encId = (int)$db->lastInsertId();

            // Create Admission Record
            $admStmt = $db->prepare("
                INSERT INTO `admissions` 
                (`encounter_id`, `patient_id`, `admitting_doctor_id`, `admission_reason`, `initial_bed_id`, `current_bed_id`, `admission_datetime`, `status`)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 'Admitted')
            ");
            $admStmt->execute([$encId, $patientId, $admittingDoctorId, $admissionReason, $bedId, $bedId]);
            $admId = (int)$db->lastInsertId();

            // Record Bed Allocation
            $allocStmt = $db->prepare("
                INSERT INTO `bed_allocations` (`admission_id`, `bed_id`, `allocated_at`, `allocated_by`)
                VALUES (?, ?, NOW(), ?)
            ");
            $allocStmt->execute([$admId, $bedId, Auth::id()]);

            // Update Bed status to Occupied
            $bedUp = $db->prepare("UPDATE `beds` SET `status` = 'Occupied', `current_encounter_id` = ? WHERE `id` = ?");
            $bedUp->execute([$encId, $bedId]);

            AuditLogger::log('CREATE', 'admissions', $admId, null, ['patient_id' => $patientId, 'bed_id' => $bedId]);
            $db->commit();

            set_flash('success', "Inpatient admitted successfully to bed.");
            header("Location: index.php?page=ipd_beds");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Admission failed: " . $e->getMessage());
            header("Location: index.php?page=ipd_beds");
            exit;
        }
    }

    // 8. IPD Discharge Action
    if ($action === 'ipd_discharge') {
        Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse']);
        $bedId = (int)($_GET['bed_id'] ?? 0);

        try {
            $db->beginTransaction();

            $bedStmt = $db->prepare("SELECT current_encounter_id FROM `beds` WHERE `id` = ?");
            $bedStmt->execute([$bedId]);
            $encId = (int)$bedStmt->fetchColumn();

            if ($encId > 0) {
                // Update Encounter
                $encUp = $db->prepare("UPDATE `encounters` SET `encounter_status` = 'Completed', `discharge_datetime` = NOW() WHERE `id` = ?");
                $encUp->execute([$encId]);

                // Update Admission
                $admUp = $db->prepare("UPDATE `admissions` SET `status` = 'Discharged' WHERE `encounter_id` = ?");
                $admUp->execute([$encId]);
            }

            // Mark bed for cleaning
            $bedClean = $db->prepare("UPDATE `beds` SET `status` = 'Cleaning', `current_encounter_id` = NULL WHERE `id` = ?");
            $bedClean->execute([$bedId]);

            AuditLogger::log('UPDATE', 'beds', $bedId, null, ['status' => 'Cleaning']);
            $db->commit();

            set_flash('success', "Patient discharged. Bed marked for housekeeping / cleaning turnaround.");
            header("Location: index.php?page=ipd_beds");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Discharge failed: " . $e->getMessage());
            header("Location: index.php?page=ipd_beds");
            exit;
        }
    }

    // 9. Mark Bed Available
    if ($action === 'bed_mark_available') {
        Auth::requireRole(['super_admin', 'hospital_admin', 'nurse']);
        $bedId = (int)($_GET['id'] ?? 0);
        $up = $db->prepare("UPDATE `beds` SET `status` = 'Available' WHERE `id` = ?");
        $up->execute([$bedId]);

        AuditLogger::log('UPDATE', 'beds', $bedId, null, ['status' => 'Available']);
        set_flash('success', "Bed sanitized and marked Available for new patients.");
        header("Location: index.php?page=ipd_beds");
        exit;
    }

    // 10. Pharmacy Dispense Action
    if ($action === 'pharmacy_dispense' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

        $rxId = (int)($_POST['prescription_id'] ?? 0);

        try {
            $db->beginTransaction();

            $itemsStmt = $db->prepare("SELECT * FROM `prescription_items` WHERE `prescription_id` = ?");
            $itemsStmt->execute([$rxId]);
            $items = $itemsStmt->fetchAll();

            foreach ($items as $item) {
                // Find batch with earliest expiry that has stock (FEFO)
                $batchStmt = $db->prepare("
                    SELECT id, current_quantity, unit_sale_price 
                    FROM `drug_batches` 
                    WHERE `drug_item_id` = ? AND `current_quantity` > 0 
                    ORDER BY `expiry_date` ASC 
                    LIMIT 1
                ");
                $batchStmt->execute([$item['drug_item_id']]);
                $batch = $batchStmt->fetch();

                if ($batch) {
                    $dispenseQty = min($item['quantity_prescribed'], $batch['current_quantity']);
                    $newQty = $batch['current_quantity'] - $dispenseQty;

                    // Deduct batch quantity
                    $deductStmt = $db->prepare("UPDATE `drug_batches` SET `current_quantity` = ? WHERE `id` = ?");
                    $deductStmt->execute([$newQty, $batch['id']]);

                    // Update prescription item quantity dispensed
                    $upItem = $db->prepare("UPDATE `prescription_items` SET `quantity_dispensed` = ? WHERE `id` = ?");
                    $upItem->execute([$dispenseQty, $item['id']]);
                }
            }

            // Mark prescription Dispensed
            $rxUp = $db->prepare("UPDATE `prescriptions` SET `status` = 'Dispensed' WHERE `id` = ?");
            $rxUp->execute([$rxId]);

            AuditLogger::log('UPDATE', 'prescriptions', $rxId, null, ['status' => 'Dispensed']);
            $db->commit();

            set_flash('success', "Prescription verified and medications dispensed from FEFO inventory batches.");
            header("Location: index.php?page=pharmacy");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Dispense failed: " . $e->getMessage());
            header("Location: index.php?page=pharmacy");
            exit;
        }
    }

    // 11. Laboratory Sample Collection
    if ($action === 'lab_collect_sample' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'lab_technician', 'nurse']);

        $itemId = (int)($_POST['order_item_id'] ?? 0);
        $barcode = 'BC-' . date('Y') . '-' . str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            UPDATE `lab_order_items` 
            SET `sample_barcode` = ?, `sample_collected_at` = NOW(), `collected_by` = ?, `item_status` = 'Collected'
            WHERE `id` = ?
        ");
        $stmt->execute([$barcode, Auth::id(), $itemId]);

        AuditLogger::log('UPDATE', 'lab_order_items', $itemId, null, ['barcode' => $barcode, 'status' => 'Collected']);
        set_flash('success', "Specimen collected and assigned Barcode #$barcode.");
        header("Location: index.php?page=laboratory&item_id=" . $itemId);
        exit;
    }

    // 12. Laboratory Save Result & Sign-off
    if ($action === 'lab_save_result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'lab_technician', 'doctor']);

        $itemId = (int)($_POST['order_item_id'] ?? 0);
        $paramIds = $_POST['param_ids'] ?? [];
        $resultValues = $_POST['result_values'] ?? [];
        $flags = $_POST['flags'] ?? [];
        $actionType = clean_input($_POST['action_type'] ?? 'save_draft');

        try {
            $db->beginTransaction();

            $delExisting = $db->prepare("DELETE FROM `lab_results` WHERE `lab_order_item_id` = ?");
            $delExisting->execute([$itemId]);

            $resStmt = $db->prepare("
                INSERT INTO `lab_results` 
                (`lab_order_item_id`, `parameter_id`, `result_value`, `abnormal_flag`, `tested_by`, `verified_by`, `verified_at`)
                VALUES (?, ?, ?, ?, ?, ?, IF(? = 'verify_and_sign', NOW(), NULL))
            ");

            $verifiedBy = ($actionType === 'verify_and_sign') ? Auth::id() : null;

            foreach ($paramIds as $pId) {
                $val = clean_input($resultValues[$pId] ?? '');
                $flag = clean_input($flags[$pId] ?? 'Normal');
                if ($val !== '') {
                    $resStmt->execute([$itemId, (int)$pId, $val, $flag, Auth::id(), $verifiedBy, $actionType]);
                }
            }

            // Update item status
            $newStatus = ($actionType === 'verify_and_sign') ? 'Verified' : 'Tested';
            $upStatus = $db->prepare("UPDATE `lab_order_items` SET `item_status` = ? WHERE `id` = ?");
            $upStatus->execute([$newStatus, $itemId]);

            AuditLogger::log('UPDATE', 'lab_order_items', $itemId, null, ['status' => $newStatus]);
            $db->commit();

            set_flash('success', $actionType === 'verify_and_sign' ? "Diagnostic report digitally verified and published." : "Results saved as draft.");
            header("Location: index.php?page=laboratory&item_id=" . $itemId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Failed to save lab results: " . $e->getMessage());
            header("Location: index.php?page=laboratory&item_id=" . $itemId);
            exit;
        }
    }

    // 13. Billing Cashier Payment Receipt
    if ($action === 'billing_pay' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'cashier']);

        $billId = (int)($_POST['bill_id'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $amountPaid = (float)($_POST['amount_paid'] ?? 0);
        $paymentMethod = clean_input($_POST['payment_method'] ?? 'Cash');
        $txRef = clean_input($_POST['transaction_reference'] ?? 'COUNTER-PAY');

        if ($amountPaid <= 0) {
            set_flash('danger', 'Payment amount must be greater than zero.');
            header("Location: index.php?page=billing&bill_id=" . $billId);
            exit;
        }

        try {
            $db->beginTransaction();

            // Generate receipt number
            $latestPay = $db->query("SELECT COALESCE(MAX(id), 0) FROM `payments`")->fetchColumn();
            $receiptNum = 'RCPT-' . date('Y') . '-' . str_pad((string)($latestPay + 1), 4, '0', STR_PAD_LEFT);

            $payStmt = $db->prepare("
                INSERT INTO `payments` (`receipt_number`, `bill_id`, `patient_id`, `amount_paid`, `payment_method`, `transaction_reference`, `payment_datetime`, `received_by`)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $payStmt->execute([$receiptNum, $billId, $patientId, $amountPaid, $paymentMethod, $txRef, Auth::id()]);

            // Update bill paid and balance amounts
            $billStmt = $db->prepare("SELECT net_amount, paid_amount FROM `bills` WHERE `id` = ?");
            $billStmt->execute([$billId]);
            $b = $billStmt->fetch();

            $newPaid = $b['paid_amount'] + $amountPaid;
            $newBalance = max(0, $b['net_amount'] - $newPaid);
            $payStatus = ($newBalance <= 0) ? 'Fully-Paid' : 'Partially-Paid';

            $billUp = $db->prepare("UPDATE `bills` SET `paid_amount` = ?, `balance_amount` = ?, `payment_status` = ? WHERE `id` = ?");
            $billUp->execute([$newPaid, $newBalance, $payStatus, $billId]);

            AuditLogger::log('CREATE', 'payments', $db->lastInsertId(), null, ['receipt' => $receiptNum, 'amount' => $amountPaid]);
            $db->commit();

            set_flash('success', "Payment received ($amountPaid PKR) and official receipt issued: $receiptNum");
            header("Location: index.php?page=billing&bill_id=" . $billId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', "Payment processing failed: " . $e->getMessage());
            header("Location: index.php?page=billing&bill_id=" . $billId);
            exit;
        }
    }

    // 14. Update Hospital Profile Settings
    if ($action === 'settings_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin']);

        $name = clean_input($_POST['hospital_name'] ?? '');
        $tagline = clean_input($_POST['tagline'] ?? '');
        $hotline = clean_input($_POST['emergency_hotline'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $address = clean_input($_POST['address'] ?? '');
        $city = clean_input($_POST['city'] ?? '');
        $country = clean_input($_POST['country'] ?? 'Pakistan');
        $license = clean_input($_POST['license_number'] ?? '');
        $tax = clean_input($_POST['tax_number'] ?? '');
        $currency = clean_input($_POST['currency_symbol'] ?? 'PKR ');

        $stmt = $db->prepare("
            UPDATE `hospital_profiles` 
            SET `hospital_name` = ?, `tagline` = ?, `emergency_hotline` = ?, `phone` = ?, `email` = ?,
                `address` = ?, `city` = ?, `country` = ?, `license_number` = ?, `tax_number` = ?, `currency_symbol` = ?
            WHERE `id` = 1
        ");
        $stmt->execute([$name, $tagline, $hotline, $phone, $email, $address, $city, $country, $license, $tax, $currency]);

        AuditLogger::log('UPDATE', 'hospital_profiles', 1, null, ['hospital_name' => $name]);
        set_flash('success', 'Hospital profile and facility settings updated successfully.');
        header("Location: index.php?page=settings&tab=profile");
        exit;
    }

    // 15. Create Department
    if ($action === 'department_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin']);

        $deptName = clean_input($_POST['department_name'] ?? '');
        $code = clean_input($_POST['code'] ?? '');
        $type = clean_input($_POST['type'] ?? 'Clinical');
        $desc = clean_input($_POST['description'] ?? '');

        $stmt = $db->prepare("INSERT INTO `departments` (`department_name`, `code`, `type`, `description`, `status`) VALUES (?, ?, ?, ?, 'Active')");
        $stmt->execute([$deptName, $code, $type, $desc]);

        AuditLogger::log('CREATE', 'departments', $db->lastInsertId(), null, ['code' => $code, 'name' => $deptName]);
        set_flash('success', "Department '$deptName' created successfully.");
        header("Location: index.php?page=settings&tab=departments");
        exit;
    }

    // 16. Create Ward
    if ($action === 'ward_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin']);

        $wardName = clean_input($_POST['ward_name'] ?? '');
        $code = clean_input($_POST['code'] ?? '');
        $type = clean_input($_POST['ward_type'] ?? 'General_Male');
        $floor = clean_input($_POST['floor_number'] ?? '1st Floor');

        $stmt = $db->prepare("INSERT INTO `wards` (`ward_name`, `code`, `ward_type`, `floor_number`, `status`) VALUES (?, ?, ?, ?, 'Active')");
        $stmt->execute([$wardName, $code, $type, $floor]);
        $wardId = (int)$db->lastInsertId();

        // Create a default room inside this ward
        $rStmt = $db->prepare("INSERT INTO `rooms` (`ward_id`, `room_number`, `room_type`, `daily_rate`) VALUES (?, ?, 'Standard Room', 1500.00)");
        $rStmt->execute([$wardId, $code . '-R1']);

        AuditLogger::log('CREATE', 'wards', $wardId, null, ['code' => $code, 'name' => $wardName]);
        set_flash('success', "Ward '$wardName' created successfully.");
        header("Location: index.php?page=settings&tab=wards");
        exit;
    }

    // 17. Create Bed
    if ($action === 'bed_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin']);

        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedNumber = clean_input($_POST['bed_number'] ?? '');
        $bedType = clean_input($_POST['bed_type'] ?? 'Standard');
        $tariff = (float)($_POST['daily_tariff'] ?? 1500.00);

        $stmt = $db->prepare("INSERT INTO `beds` (`room_id`, `bed_number`, `bed_type`, `daily_tariff`, `status`) VALUES (?, ?, ?, ?, 'Available')");
        $stmt->execute([$roomId, $bedNumber, $bedType, $tariff]);

        AuditLogger::log('CREATE', 'beds', $db->lastInsertId(), null, ['bed_number' => $bedNumber]);
        set_flash('success', "Bed '$bedNumber' added and ready for admissions.");
        header("Location: index.php?page=settings&tab=wards");
        exit;
    }

    // 18. Create Service Tariff
    if ($action === 'tariff_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin']);

        $name = clean_input($_POST['service_name'] ?? '');
        $code = clean_input($_POST['service_code'] ?? '');
        $cat = clean_input($_POST['category'] ?? 'Procedure');
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $price = (float)($_POST['standard_price'] ?? 0.00);

        $stmt = $db->prepare("INSERT INTO `tariff_services` (`department_id`, `service_name`, `service_code`, `category`, `standard_price`, `is_active`) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$deptId, $name, $code, $cat, $price]);

        AuditLogger::log('CREATE', 'tariff_services', $db->lastInsertId(), null, ['service_code' => $code]);
        set_flash('success', "Service '$name' added to official tariff catalog.");
        header("Location: index.php?page=settings&tab=tariffs");
        exit;
    }

    // 19. Create Drug Item
    if ($action === 'drug_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

        $brand = clean_input($_POST['brand_name'] ?? '');
        $generic = clean_input($_POST['generic_name'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 1);
        $form = clean_input($_POST['dosage_form'] ?? 'Tablet');
        $strength = clean_input($_POST['strength'] ?? '500mg');

        $stmt = $db->prepare("INSERT INTO `drug_items` (`category_id`, `brand_name`, `generic_name`, `dosage_form`, `strength`, `status`) VALUES (?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([$catId, $brand, $generic, $form, $strength]);

        AuditLogger::log('CREATE', 'drug_items', $db->lastInsertId(), null, ['brand' => $brand]);
        set_flash('success', "Medicine '$brand' added to formulary.");
        header("Location: index.php?page=settings&tab=drugs");
        exit;
    }

    // 20. Add Drug Batch Stock
    if ($action === 'drug_batch_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

        $drugId = (int)($_POST['drug_item_id'] ?? 0);
        $batchNo = clean_input($_POST['batch_number'] ?? '');
        $expiry = clean_input($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+1 year')));
        $qty = (int)($_POST['quantity'] ?? 100);
        $cost = (float)($_POST['unit_purchase_price'] ?? 0.00);
        $sale = (float)($_POST['unit_sale_price'] ?? 0.00);

        $stmt = $db->prepare("
            INSERT INTO `drug_batches` 
            (`drug_item_id`, `batch_number`, `expiry_date`, `unit_purchase_price`, `unit_sale_price`, `initial_quantity`, `current_quantity`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        $stmt->execute([$drugId, $batchNo, $expiry, $cost, $sale, $qty, $qty]);

        AuditLogger::log('CREATE', 'drug_batches', $db->lastInsertId(), null, ['batch_number' => $batchNo, 'qty' => $qty]);
        set_flash('success', "Batch '$batchNo' ($qty units) received into pharmacy inventory.");
        header("Location: index.php?page=settings&tab=drugs");
        exit;
    }

    // 21. Create Lab Test
    if ($action === 'lab_test_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin', 'lab_technician']);

        $name = clean_input($_POST['test_name'] ?? '');
        $code = clean_input($_POST['test_code'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 1);
        $specimen = clean_input($_POST['specimen_type'] ?? 'Blood');
        $tat = (int)($_POST['turnaround_hours'] ?? 6);
        $price = (float)($_POST['standard_price'] ?? 500.00);

        $stmt = $db->prepare("INSERT INTO `lab_tests` (`category_id`, `test_name`, `test_code`, `specimen_type`, `turnaround_hours`, `standard_price`, `status`) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([$catId, $name, $code, $specimen, $tat, $price]);
        $testId = (int)$db->lastInsertId();

        // Add primary parameter
        $pStmt = $db->prepare("INSERT INTO `lab_test_parameters` (`lab_test_id`, `parameter_name`, `unit`, `reference_range`) VALUES (?, ?, 'units', 'Standard Range')");
        $pStmt->execute([$testId, $name]);

        AuditLogger::log('CREATE', 'lab_tests', $testId, null, ['code' => $code]);
        set_flash('success', "Diagnostic test '$name' added to lab catalog.");
        header("Location: index.php?page=settings&tab=labs");
        exit;
    }

    // 22. Create Staff Account
    if ($action === 'user_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        Auth::requireRole(['super_admin', 'hospital_admin']);

        $username = clean_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? 'Admin@123';
        $fullName = clean_input($_POST['full_name'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 3);
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = clean_input($_POST['designation'] ?? '');
        $fee = (float)($_POST['consultation_fee'] ?? 0.00);
        $email = clean_input($_POST['email'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            INSERT INTO `users` 
            (`username`, `email`, `password_hash`, `full_name`, `phone`, `role_id`, `department_id`, `designation`, `consultation_fee`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        $stmt->execute([$username, $email, $hash, $fullName, $phone, $roleId, $deptId, $designation, $fee]);

        AuditLogger::log('CREATE', 'users', $db->lastInsertId(), null, ['username' => $username, 'role_id' => $roleId]);
        set_flash('success', "Staff account for '$fullName' created successfully.");
        header("Location: index.php?page=users");
        exit;
    }

    // Default fallback if action unknown
    header("Location: index.php?page=dashboard");
    exit;
}

// ----------------------------------------------------------------------------
// PAGE VIEW DISPATCHER
// ----------------------------------------------------------------------------

if ($page === 'login') {
    if (Auth::check()) {
        header("Location: index.php?page=dashboard");
        exit;
    }
    require_once __DIR__ . '/views/auth/login.php';
    exit;
}

// All inner pages require authentication
Auth::requireLogin();

// Map route to view files and titles
$routes = [
    'dashboard' => ['file' => 'views/dashboard/index.php', 'title' => 'Executive & Clinical Dashboard'],
    'patients' => ['file' => 'views/patients/index.php', 'title' => 'Patient Directory (Master Patient Index)'],
    'patient_create' => ['file' => 'views/patients/create.php', 'title' => 'Patient Registration'],
    'patient_profile' => ['file' => 'views/patients/view.php', 'title' => 'Patient 360° Comprehensive Profile'],
    'appointments' => ['file' => 'views/appointments/index.php', 'title' => 'Appointments & Token Scheduling'],
    'opd_consultation' => ['file' => 'views/opd/consultation.php', 'title' => 'Clinician Workbench (OPD)'],
    'ipd_beds' => ['file' => 'views/ipd/beds.php', 'title' => 'Wards & Interactive Bed Rack (IPD)'],
    'pharmacy' => ['file' => 'views/pharmacy/index.php', 'title' => 'Pharmacy & Dispensing Counter'],
    'laboratory' => ['file' => 'views/laboratory/index.php', 'title' => 'Clinical Pathology & Laboratory'],
    'billing' => ['file' => 'views/billing/index.php', 'title' => 'Centralized Billing & Cashier Desk'],
    'reports' => ['file' => 'views/reports/index.php', 'title' => 'Reports & Operational Analytics'],
    'settings' => ['file' => 'views/admin/settings.php', 'title' => 'Hospital Masters & Dynamic Settings'],
    'users' => ['file' => 'views/admin/users.php', 'title' => 'Hospital Staff & System Accounts'],
    'audit_logs' => ['file' => 'views/admin/audit_logs.php', 'title' => 'Forensic Security & Audit Trail'],
];

if (!isset($routes[$page])) {
    $page = 'dashboard';
}

$targetRoute = $routes[$page];
$pageTitle = $targetRoute['title'];

// Render Layout & View
require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar.php';
echo '<div class="flex-1 flex flex-col h-full overflow-hidden">';
require_once __DIR__ . '/views/layouts/topbar.php';
require_once __DIR__ . '/' . $targetRoute['file'];
echo '</div>';
require_once __DIR__ . '/views/layouts/footer.php';
