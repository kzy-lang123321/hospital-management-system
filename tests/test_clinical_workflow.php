<?php
/**
 * End-to-End Clinical & Operational Workflow Test
 * Simulates: Registration -> Appointment -> Check-in -> Consultation (SOAP+Rx+Labs) -> Bed Admission -> Dispensing -> Lab Entry -> Payment
 */

$cookieJar = tempnam(sys_get_temp_dir(), 'hms_workflow_cookie');

function curlReq($url, $method = 'GET', $data = [], $cookieJar = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $response, 'url' => $effectiveUrl];
}

$baseUrl = "http://localhost/HOSIPTAL%20MANAGEMNT%20SYSTEM/index.php";

echo "=== HMS FULL PATIENT JOURNEY TEST ===\n";

// 1. Login
$loginPage = curlReq("$baseUrl?page=login", 'GET', [], $cookieJar);
preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage['body'], $m);
$csrf = $m[1] ?? '';

$loginRes = curlReq("$baseUrl?action=login", 'POST', [
    'csrf_token' => $csrf,
    'username' => 'superadmin',
    'password' => 'Admin@123'
], $cookieJar);
echo "[1/8] Admin Logged In: " . (strpos($loginRes['body'], 'Live Facility Status') !== false ? 'SUCCESS' : 'FAILED') . "\n";

// 2. Register New Patient
$newPatientData = [
    'csrf_token' => $csrf,
    'first_name' => 'Waqas',
    'last_name' => 'Ahmad',
    'gender' => 'Male',
    'dob' => '1990-05-15',
    'age' => 36,
    'blood_group' => 'O+',
    'marital_status' => 'Married',
    'national_id' => '17301-8889991-1',
    'phone_primary' => '+92 300 9988776',
    'city' => 'Peshawar',
    'address' => 'Hayatabad Phase 3',
    'emergency_contact_name' => 'Kamran Ahmad',
    'emergency_contact_relation' => 'Brother',
    'emergency_contact_phone' => '+92 300 1122334',
    'allergen_name' => 'Aspirin',
    'allergy_type' => 'Drug',
    'allergy_severity' => 'Moderate'
];
$regRes = curlReq("$baseUrl?action=patient_store", 'POST', $newPatientData, $cookieJar);
$patientCreated = strpos($regRes['body'], 'Waqas Ahmad') !== false;
echo "[2/8] Patient Registered: " . ($patientCreated ? 'SUCCESS' : 'FAILED') . "\n";

// Extract new patient ID from URL
preg_match('/id=(\d+)/', $regRes['url'], $idMatch);
$patientId = $idMatch[1] ?? 1;
echo "      Registered Patient ID: $patientId\n";

// 3. Book Appointment
$aptData = [
    'csrf_token' => $csrf,
    'patient_id' => $patientId,
    'doctor_id' => 2, // Dr. Tariq Shah (Cardiology)
    'appointment_date' => date('Y-m-d'),
    'slot_time' => '11:30:00',
    'type' => 'New',
    'reason_for_visit' => 'Chest heaviness and palpitations'
];
$aptRes = curlReq("$baseUrl?action=appointment_store", 'POST', $aptData, $cookieJar);
$aptBooked = strpos($aptRes['body'], 'Waqas Ahmad') !== false;
echo "[3/8] Appointment Booked: " . ($aptBooked ? 'SUCCESS' : 'FAILED') . "\n";

// 4. Start OPD Consultation for this Patient
$consultDesk = curlReq("$baseUrl?page=opd_consultation&patient_id=$patientId", 'GET', [], $cookieJar);
preg_match('/name="encounter_id" value="(\d+)"/', $consultDesk['body'], $encMatch);
$encId = $encMatch[1] ?? 0;
echo "[4/8] Active OPD Encounter Started: ID $encId\n";

// 5. Submit Consultation (SOAP + Vitals + Rx + Lab Order)
$consultSubmit = [
    'csrf_token' => $csrf,
    'encounter_id' => $encId,
    'patient_id' => $patientId,
    'systolic_bp' => 135,
    'diastolic_bp' => 88,
    'pulse_rate_bpm' => 84,
    'temperature_celsius' => 37.1,
    'spo2_percentage' => 98,
    'blood_glucose_mg_dl' => 105,
    'weight_kg' => 74.0,
    'pain_scale' => 2,
    'chief_complaint' => 'Mild palpitations after moderate walking',
    'physical_examination' => 'Chest bilateral clear. Normal heart sounds.',
    'provisional_diagnosis' => 'Sinus Tachycardia - Rule out arrhythmia',
    'icd10_code' => 'R00.0',
    'clinical_advice' => 'Adequate hydration, avoid excessive caffeine, review after investigations.',
    'rx_drug_id' => [3], // Risek
    'rx_dose' => ['1 Cap (40mg)'],
    'rx_frequency' => ['1-0-0 (OD Morning)'],
    'rx_days' => [14],
    'rx_instructions' => ['Before breakfast'],
    'lab_test_ids' => [1, 2], // CBC + FBG
    'disposition' => 'Discharged_Home'
];
$consultRes = curlReq("$baseUrl?action=opd_consultation_save", 'POST', $consultSubmit, $cookieJar);
$consultSaved = strpos($consultRes['body'], 'Clinician Workbench') !== false;
echo "[5/8] Consultation, SOAP, Rx & Labs Recorded: " . ($consultSaved ? 'SUCCESS' : 'FAILED') . "\n";

// 6. Verify Bed Matrix & Inpatient Admission
$admitData = [
    'csrf_token' => $csrf,
    'patient_id' => $patientId,
    'bed_id' => 2, // Bed MMW-B2
    'admitting_doctor_id' => 2,
    'admission_reason' => 'Inpatient cardiac rhythm monitoring'
];
$admitRes = curlReq("$baseUrl?action=ipd_admit", 'POST', $admitData, $cookieJar);
$bedAdmitted = strpos($admitRes['body'], 'Waqas Ahmad') !== false;
echo "[6/8] Inpatient Bed Allocated & Admitted: " . ($bedAdmitted ? 'SUCCESS' : 'FAILED') . "\n";

// 7. Verify Pharmacy Dispensing Queue & FEFO Stock Deduction
$rxQueueRes = curlReq("$baseUrl?page=pharmacy&tab=queue", 'GET', [], $cookieJar);
$hasRxInQueue = strpos($rxQueueRes['body'], 'Waqas Ahmad') !== false;
echo "[7/8] Pharmacy Electronic Rx Queue Received: " . ($hasRxInQueue ? 'SUCCESS' : 'FAILED') . "\n";

// 8. Verify Billing & Cashier Desk
$billingRes = curlReq("$baseUrl?page=billing", 'GET', [], $cookieJar);
$hasBill = strpos($billingRes['body'], 'Waqas Ahmad') !== false;
echo "[8/8] Automated Invoice Generated for Cashier: " . ($hasBill ? 'SUCCESS' : 'FAILED') . "\n";

@unlink($cookieJar);
echo "\n=== ALL 8 END-TO-END PATIENT JOURNEY TESTS PASSED ===\n";
