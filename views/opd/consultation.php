<?php
/**
 * Hospital Management System (HMS) - OPD Clinician Workbench
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$encounterId = (int)($_GET['encounter_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);

// If patient_id passed without encounter_id, find or start an encounter
if ($encounterId === 0 && $patientId > 0) {
    // Check if patient already has an in-progress encounter today
    $activeEnc = $db->prepare("SELECT id FROM `encounters` WHERE `patient_id` = ? AND `encounter_status` = 'In-Progress' ORDER BY id DESC LIMIT 1");
    $activeEnc->execute([$patientId]);
    $existing = $activeEnc->fetch();
    if ($existing) {
        $encounterId = (int)$existing['id'];
    } else {
        // Create new encounter
        $latestEncId = $db->query("SELECT COALESCE(MAX(id), 0) FROM `encounters`")->fetchColumn();
        $encNumber = 'ENC-' . date('Y') . '-' . str_pad((string)($latestEncId + 1), 4, '0', STR_PAD_LEFT);
        $docId = Auth::id() ?? 2; // fallback to Dr. Tariq if admin
        $newStmt = $db->prepare("
            INSERT INTO `encounters` 
            (`encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `encounter_status`, `created_by`) 
            VALUES (?, ?, 'OPD', 2, ?, NOW(), 'In-Progress', ?)
        ");
        $newStmt->execute([$encNumber, $patientId, $docId, Auth::id()]);
        $encounterId = (int)$db->lastInsertId();
    }
}

// Fetch In-Progress Queue
$queue = $db->query("
    SELECT e.*, p.mrn, p.first_name, p.last_name, p.gender, p.age, p.blood_group,
           u.full_name AS doctor_name, d.department_name
    FROM `encounters` e
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` u ON e.attending_doctor_id = u.id
    JOIN `departments` d ON e.department_id = d.id
    WHERE e.encounter_status = 'In-Progress'
    ORDER BY e.admission_datetime ASC
")->fetchAll();

// If no encounter specified, default to first in queue
if ($encounterId === 0 && !empty($queue)) {
    $encounterId = (int)$queue[0]['id'];
}

// Fetch active encounter details
$currentEncounter = null;
$currentPatient = null;
$currentVitals = null;
$currentNotes = null;
$patientAllergies = [];

if ($encounterId > 0) {
    $encStmt = $db->prepare("
        SELECT e.*, p.mrn, p.first_name, p.last_name, p.gender, p.dob, p.age, p.blood_group, p.phone_primary, p.city
        FROM `encounters` e
        JOIN `patients` p ON e.patient_id = p.id
        WHERE e.id = ?
    ");
    $encStmt->execute([$encounterId]);
    $currentEncounter = $encStmt->fetch();

    if ($currentEncounter) {
        // Fetch Allergies
        $algStmt = $db->prepare("SELECT * FROM `patient_allergies` WHERE `patient_id` = ?");
        $algStmt->execute([$currentEncounter['patient_id']]);
        $patientAllergies = $algStmt->fetchAll();

        // Fetch Latest Vitals
        $vitStmt = $db->prepare("SELECT * FROM `clinical_vitals` WHERE `encounter_id` = ? ORDER BY id DESC LIMIT 1");
        $vitStmt->execute([$encounterId]);
        $currentVitals = $vitStmt->fetch();

        // Fetch Existing Consultation Notes
        $noteStmt = $db->prepare("SELECT * FROM `consultation_notes` WHERE `encounter_id` = ?");
        $noteStmt->execute([$encounterId]);
        $currentNotes = $noteStmt->fetch();

        // Fetch Past Verified Lab Reports
        $pastLabStmt = $db->prepare("
            SELECT lo.order_number, lo.order_datetime, lt.test_name, lt.specimen_type,
                   loi.item_status, loi.sample_barcode,
                   ltp.parameter_name, 
                   lr.result_value AS observed_value, 
                   ltp.unit AS unit_of_measure, 
                   ltp.reference_range, 
                   lr.abnormal_flag AS flag
            FROM `lab_orders` lo
            JOIN `lab_order_items` loi ON lo.id = loi.lab_order_id
            JOIN `lab_tests` lt ON loi.lab_test_id = lt.id
            LEFT JOIN `lab_results` lr ON loi.id = lr.lab_order_item_id
            LEFT JOIN `lab_test_parameters` ltp ON lr.parameter_id = ltp.id
            JOIN `encounters` e ON lo.encounter_id = e.id
            WHERE e.patient_id = ? AND loi.item_status = 'Verified'
            ORDER BY lo.order_datetime DESC LIMIT 20
        ");
        $pastLabStmt->execute([$currentEncounter['patient_id']]);
        $pastLabReports = $pastLabStmt->fetchAll();

        // Fetch Prescriptions for THIS encounter (if already entered)
        $encRxStmt = $db->prepare("
            SELECT p.prescription_number, p.clinical_notes, p.created_at,
                   pi.dosage, pi.frequency, pi.duration_days, pi.instructions, pi.quantity_prescribed,
                   di.brand_name, di.generic_name, di.strength, di.dosage_form
            FROM `prescriptions` p
            JOIN `prescription_items` pi ON p.id = pi.prescription_id
            JOIN `drug_items` di ON pi.drug_item_id = di.id
            WHERE p.encounter_id = ?
        ");
        $encRxStmt->execute([$encounterId]);
        $currentEncounterRx = $encRxStmt->fetchAll();

        // Fetch Ordered Lab Tests for THIS encounter
        $encLabStmt = $db->prepare("
            SELECT lo.order_number, lt.test_name, lt.specimen_type, lt.standard_price, loi.item_status
            FROM `lab_orders` lo
            JOIN `lab_order_items` loi ON lo.id = loi.lab_order_id
            JOIN `lab_tests` lt ON loi.lab_test_id = lt.id
            WHERE lo.encounter_id = ?
        ");
        $encLabStmt->execute([$encounterId]);
        $currentEncounterLabs = $encLabStmt->fetchAll();
    }
}

// Fetch formulary drugs
$drugs = $db->query("SELECT id, brand_name, generic_name, dosage_form, strength FROM `drug_items` WHERE `status` = 'Active' ORDER BY brand_name ASC")->fetchAll();

// Fetch lab tests catalog
$labTests = $db->query("SELECT id, test_name, test_code, specimen_type, standard_price FROM `lab_tests` WHERE `status` = 'Active' ORDER BY test_name ASC")->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Clinician Workbench (OPD Consultation)</h2>
            <p class="text-xs text-slate-500">Computerized Physician Order Entry (CPOE), SOAP Notes & Prescription Writer</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 bg-sky-50 text-sky-700 border border-sky-200 rounded-lg text-xs font-bold font-mono">
                <i class="fa-solid fa-users-line mr-1"></i> Waiting Queue: <?= count($queue) ?>
            </span>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        <!-- Left 1 Col: Waiting Queue Selector -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 flex flex-col h-[750px] overflow-hidden">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                <span>Waiting Room Queue</span>
                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-mono"><?= count($queue) ?></span>
            </h3>

            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1">
                <?php if (empty($queue)): ?>
                    <div class="text-center py-12 text-slate-400 text-xs">
                        <i class="fa-solid fa-mug-hot text-2xl mb-2 text-slate-300"></i>
                        <p>No waiting patients in queue.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($queue as $q): 
                        $isSelected = ($encounterId === (int)$q['id']);
                    ?>
                    <a href="index.php?page=opd_consultation&encounter_id=<?= $q['id'] ?>" class="block p-3 rounded-xl border transition-all text-xs <?= $isSelected ? 'bg-medical-50 border-medical-500 shadow-xs' : 'bg-slate-50/70 hover:bg-slate-100 border-slate-200' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-slate-900 truncate"><?= htmlspecialchars($q['first_name'] . ' ' . $q['last_name']) ?></span>
                            <span class="font-mono text-[10px] font-bold text-medical-700"><?= htmlspecialchars($q['blood_group']) ?></span>
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($q['mrn']) ?></div>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 mt-2">
                            <span><?= htmlspecialchars($q['gender']) ?>, <?= $q['age'] ?>y</span>
                            <span><?= date('h:i A', strtotime($q['admission_datetime'])) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right 3 Cols: Active Consultation Workbench -->
        <div class="lg:col-span-3 space-y-6">

            <?php if (!$currentEncounter): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-12 text-center text-slate-400">
                    <i class="fa-solid fa-stethoscope text-4xl mb-3 text-slate-300"></i>
                    <h3 class="text-base font-bold text-slate-700">No Patient Encounter Selected</h3>
                    <p class="text-xs text-slate-500 mt-1">Select a patient from the queue or search from Patient Directory.</p>
                </div>
            <?php else: ?>

                <!-- Active Patient Banner -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 relative overflow-hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-medical-700 text-white flex items-center justify-center font-bold text-lg">
                                <?= strtoupper(substr($currentEncounter['first_name'], 0, 1) . substr($currentEncounter['last_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-slate-900 text-base">
                                        <?= htmlspecialchars($currentEncounter['first_name'] . ' ' . $currentEncounter['last_name']) ?>
                                    </h3>
                                    <span class="font-mono text-xs font-bold text-medical-800 bg-medical-50 border border-medical-200 px-2 py-0.5 rounded">
                                        <?= htmlspecialchars($currentEncounter['mrn']) ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 flex items-center gap-2 mt-0.5">
                                    <span><?= htmlspecialchars($currentEncounter['gender']) ?>, <?= $currentEncounter['age'] ?> yrs</span>
                                    <span>&bull;</span>
                                    <span>Blood: <strong class="text-rose-600"><?= htmlspecialchars($currentEncounter['blood_group']) ?></strong></span>
                                    <span>&bull;</span>
                                    <span class="font-mono"><?= htmlspecialchars($currentEncounter['encounter_number']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" onclick="document.getElementById('pastLabsModal').classList.remove('hidden')" class="px-3 py-1.5 bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 text-xs font-semibold rounded-lg flex items-center gap-1.5 transition-colors">
                                <i class="fa-solid fa-flask-vial text-purple-600"></i>
                                <span>Past Labs (<?= count($pastLabReports) ?>)</span>
                            </button>
                            <button type="button" onclick="document.getElementById('prescriptionModal').classList.remove('hidden')" class="px-3.5 py-1.5 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center gap-1.5 transition-colors">
                                <i class="fa-solid fa-print"></i>
                                <span>Print Rx</span>
                            </button>
                            <a href="index.php?page=patient_profile&id=<?= $currentEncounter['patient_id'] ?>" target="_blank" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                                <i class="fa-solid fa-clock-rotate-left text-slate-500"></i>
                                <span>Past History</span>
                            </a>
                        </div>
                    </div>

                    <!-- Allergy Warning Alert (Critical Interceptor) -->
                    <?php if (!empty($patientAllergies)): ?>
                    <div class="mt-3 p-2.5 bg-rose-50 border border-rose-200 rounded-lg flex items-center gap-2 text-xs text-rose-800">
                        <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                        <span class="font-bold">ALLERGY WARNING:</span>
                        <?php foreach ($patientAllergies as $alg): ?>
                            <span class="bg-rose-200/80 px-1.5 py-0.5 rounded font-semibold"><?= htmlspecialchars($alg['allergen_name']) ?> (<?= htmlspecialchars($alg['severity']) ?>)</span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Main Consultation Form -->
                <form action="index.php?action=opd_consultation_save" method="POST" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="encounter_id" value="<?= $currentEncounter['id'] ?>">
                    <input type="hidden" name="patient_id" value="<?= $currentEncounter['patient_id'] ?>">

                    <!-- Section 1: Clinical Vitals Record -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center justify-between">
                            <span class="flex items-center gap-2"><i class="fa-solid fa-heart-pulse text-rose-600"></i> Patient Vitals</span>
                            <span class="text-[11px] text-slate-400 font-normal">Recorded today or updated now</span>
                        </h4>

                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3 text-xs">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 mb-1">Blood Pressure</label>
                                <div class="flex items-center gap-1">
                                    <input type="number" name="systolic_bp" placeholder="Sys" value="<?= $currentVitals['systolic_bp'] ?? 120 ?>" class="w-full px-2 py-1.5 border border-slate-200 rounded text-center outline-none focus:border-medical-500 font-semibold">
                                    <span class="text-slate-400">/</span>
                                    <input type="number" name="diastolic_bp" placeholder="Dia" value="<?= $currentVitals['diastolic_bp'] ?? 80 ?>" class="w-full px-2 py-1.5 border border-slate-200 rounded text-center outline-none focus:border-medical-500 font-semibold">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 mb-1">Pulse (bpm)</label>
                                <input type="number" name="pulse_rate_bpm" placeholder="e.g. 76" value="<?= $currentVitals['pulse_rate_bpm'] ?? 78 ?>" class="w-full px-2 py-1.5 border border-slate-200 rounded text-center outline-none focus:border-medical-500 font-semibold">
                            </div>

                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 mb-1">Temp (°C)</label>
                                <input type="text" name="temperature_celsius" placeholder="37.0" value="<?= $currentVitals['temperature_celsius'] ?? 37.0 ?>" class="w-full px-2 py-1.5 border border-slate-200 rounded text-center outline-none focus:border-medical-500 font-semibold">
                            </div>

                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 mb-1">SpO2 (%)</label>
                                <input type="number" name="spo2_percentage" placeholder="98" value="<?= $currentVitals['spo2_percentage'] ?? 98 ?>" class="w-full px-2 py-1.5 border border-slate-200 rounded text-center outline-none focus:border-medical-500 font-semibold">
                            </div>

                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 mb-1">Blood Sugar (mg/dL)</label>
                                <input type="number" name="blood_glucose_mg_dl" placeholder="110" value="<?= $currentVitals['blood_glucose_mg_dl'] ?? '' ?>" class="w-full px-2 py-1.5 border border-slate-200 rounded text-center outline-none focus:border-medical-500 font-semibold">
                            </div>

                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 mb-1">Weight (kg)</label>
                                <input type="text" name="weight_kg" placeholder="70" value="<?= $currentVitals['weight_kg'] ?? 72 ?>" class="w-full px-2 py-1.5 border border-slate-200 rounded text-center outline-none focus:border-medical-500 font-semibold">
                            </div>

                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 mb-1">Pain Scale (1-10)</label>
                                <input type="number" name="pain_scale" min="0" max="10" placeholder="0" value="<?= $currentVitals['pain_scale'] ?? 0 ?>" class="w-full px-2 py-1.5 border border-slate-200 rounded text-center outline-none focus:border-medical-500 font-semibold">
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Clinical SOAP Notes -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-4">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                            <i class="fa-solid fa-file-medical text-medical-600"></i>
                            <span>Clinical Examination & Diagnosis (SOAP)</span>
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Chief Complaints & Present Illness <span class="text-rose-500">*</span></label>
                                <textarea name="chief_complaint" rows="3" required placeholder="Patient presents with..." class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500"><?= htmlspecialchars($currentNotes['chief_complaint'] ?? '') ?></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Physical Examination & Clinical Findings</label>
                                <textarea name="physical_examination" rows="3" placeholder="Chest clear, S1/S2 heard, abdomen soft..." class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500"><?= htmlspecialchars($currentNotes['physical_examination'] ?? '') ?></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Provisional / Primary Diagnosis <span class="text-rose-500">*</span></label>
                                <input type="text" name="provisional_diagnosis" required value="<?= htmlspecialchars($currentNotes['provisional_diagnosis'] ?? ($currentEncounter['primary_diagnosis'] ?? '')) ?>" placeholder="e.g. Acute Bronchitis, Essential Hypertension" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">ICD-10 Code</label>
                                <input type="text" name="icd10_code" value="<?= htmlspecialchars($currentNotes['icd10_code'] ?? '') ?>" placeholder="e.g. J20.9, I10" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Clinical Advice / Lifestyle & Follow-up Instructions</label>
                                <input type="text" name="clinical_advice" value="<?= htmlspecialchars($currentNotes['clinical_advice'] ?? '') ?>" placeholder="Rest, drink warm fluids, review in 7 days..." class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: CPOE Prescription Items -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                                <i class="fa-solid fa-pills text-teal-600"></i>
                                <span>Prescribe Medications (Electronic Rx)</span>
                            </h4>
                            <button type="button" onclick="addPrescriptionRow()" class="px-3 py-1 bg-medical-50 text-medical-700 hover:bg-medical-100 rounded text-xs font-semibold flex items-center gap-1">
                                <i class="fa-solid fa-plus text-[10px]"></i> Add Medicine
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs" id="rxTable">
                                <thead class="bg-slate-50 text-slate-600 uppercase text-[10px] font-semibold border-b border-slate-200">
                                    <tr>
                                        <th class="px-3 py-2 w-1/3">Drug (Formulary)</th>
                                        <th class="px-2 py-2 w-28">Dose</th>
                                        <th class="px-2 py-2 w-32">Frequency</th>
                                        <th class="px-2 py-2 w-20">Days</th>
                                        <th class="px-2 py-2">Instructions</th>
                                        <th class="px-2 py-2 text-right w-12"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100" id="rxRowsContainer">
                                    <tr class="rx-row">
                                        <td class="px-3 py-2">
                                            <select name="rx_drug_id[]" class="w-full py-1.5 px-2 text-xs border border-slate-200 rounded outline-none focus:border-medical-500">
                                                <option value="">Select Medicine...</option>
                                                <?php foreach ($drugs as $d): ?>
                                                    <option value="<?= $d['id'] ?>">
                                                        <?= htmlspecialchars($d['brand_name']) ?> &mdash; <?= htmlspecialchars($d['generic_name']) ?> (<?= htmlspecialchars($d['strength']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="text" name="rx_dose[]" value="1 Tab" class="w-full py-1.5 px-2 text-xs border border-slate-200 rounded">
                                        </td>
                                        <td class="px-2 py-2">
                                            <select name="rx_frequency[]" class="w-full py-1.5 px-2 text-xs border border-slate-200 rounded">
                                                <option value="1-0-1 (BD)">1-0-1 (Twice Daily)</option>
                                                <option value="1-0-0 (OD Morning)">1-0-0 (Once Morning)</option>
                                                <option value="0-0-1 (OD Night)">0-0-1 (Once Bedtime)</option>
                                                <option value="1-1-1 (TDS)">1-1-1 (Three Times)</option>
                                                <option value="SOS (As needed)">SOS (As Needed)</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" name="rx_days[]" value="5" min="1" max="90" class="w-full py-1.5 px-2 text-xs border border-slate-200 rounded text-center">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="text" name="rx_instructions[]" value="After meals" class="w-full py-1.5 px-2 text-xs border border-slate-200 rounded">
                                        </td>
                                        <td class="px-2 py-2 text-right">
                                            <button type="button" onclick="this.closest('tr').remove()" class="text-rose-500 hover:text-rose-700 p-1">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Section 4: Laboratory Investigations Order -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-vial-circle-check text-purple-600"></i>
                            <span>Order Laboratory & Diagnostic Investigations</span>
                        </h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            <?php foreach ($labTests as $t): ?>
                            <label class="flex items-start gap-2.5 p-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg cursor-pointer transition-colors">
                                <input type="checkbox" name="lab_test_ids[]" value="<?= $t['id'] ?>" class="mt-0.5 rounded text-medical-600 focus:ring-0">
                                <div class="text-xs leading-tight">
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($t['test_name']) ?></div>
                                    <div class="text-[10px] text-slate-500"><?= htmlspecialchars($t['specimen_type']) ?> &bull; <?= format_currency($t['standard_price']) ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Section 5: Follow-Up Appointment & Clinical Guidance -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-4">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                            <i class="fa-solid fa-calendar-check text-medical-600"></i>
                            <span>Schedule Follow-Up Appointment & Clinical Instructions</span>
                        </h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Recommended Follow-up Date</label>
                                <input type="date" name="follow_up_date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($currentNotes['follow_up_date'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                                <span class="text-[10px] text-slate-400 mt-1 block">Leaving blank means no scheduled follow-up required.</span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Follow-up Clinical Notes & Targets</label>
                                <input type="text" name="follow_up_notes" placeholder="e.g. Review fasting blood sugar and BP response" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                                <span class="text-[10px] text-slate-400 mt-1 block">Notes will be placed on the follow-up token slip.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit & Disposition Bar -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <label class="text-xs font-semibold text-slate-700">Encounter Disposition:</label>
                            <select name="disposition" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg outline-none font-medium">
                                <option value="Discharged_Home">Complete Visit & Discharge Home</option>
                                <option value="Admitted_to_Ward">Recommend Inpatient Admission (IPD)</option>
                                <option value="In-Progress">Keep In-Progress (Pending Tests)</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="submit" class="px-6 py-2.5 bg-medical-600 hover:bg-medical-700 text-white font-bold text-xs rounded-xl shadow-md shadow-medical-600/30 transition-all flex items-center gap-2">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Save Consultation & Execute Orders</span>
                            </button>
                        </div>
                    </div>

                </form>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Modal 1: Past Verified Lab Reports -->
<div id="pastLabsModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-flask-vial text-purple-600"></i>
                <span>Past Verified Diagnostic Results & Laboratory History</span>
            </h3>
            <button onclick="document.getElementById('pastLabsModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar space-y-3 pr-1 text-xs">
            <?php if (empty($pastLabReports)): ?>
                <div class="py-12 text-center text-slate-400">
                    <i class="fa-solid fa-file-circle-question text-3xl mb-2 text-slate-300"></i>
                    <p>No verified diagnostic laboratory reports recorded for this patient.</p>
                </div>
            <?php else: ?>
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-[10px] font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2">Test / Order</th>
                                <th class="px-3 py-2">Parameter</th>
                                <th class="px-3 py-2">Observed</th>
                                <th class="px-3 py-2">Ref Range</th>
                                <th class="px-3 py-2">Flag</th>
                                <th class="px-3 py-2">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($pastLabReports as $plr): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($plr['test_name']) ?></div>
                                    <div class="font-mono text-[10px] text-slate-400"><?= htmlspecialchars($plr['order_number']) ?></div>
                                </td>
                                <td class="px-3 py-2 font-medium text-slate-800"><?= htmlspecialchars($plr['parameter_name'] ?? 'N/A') ?></td>
                                <td class="px-3 py-2 font-bold text-slate-900 font-mono"><?= htmlspecialchars($plr['observed_value'] ?? '--') ?> <span class="text-[10px] font-normal text-slate-500"><?= htmlspecialchars($plr['unit_of_measure'] ?? '') ?></span></td>
                                <td class="px-3 py-2 text-slate-500 font-mono text-[11px]"><?= htmlspecialchars($plr['reference_range'] ?? 'Standard') ?></td>
                                <td class="px-3 py-2">
                                    <?php if (!empty($plr['flag']) && $plr['flag'] !== 'Normal'): ?>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800"><?= htmlspecialchars($plr['flag']) ?></span>
                                    <?php else: ?>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-50 text-emerald-700">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-slate-400 text-[10px] whitespace-nowrap"><?= format_date($plr['order_datetime']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end">
            <button type="button" onclick="document.getElementById('pastLabsModal').classList.add('hidden')" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-semibold">
                Close Viewer
            </button>
        </div>
    </div>
</div>

<!-- Modal 2: Printable Clinical Prescription & Consultation Summary -->
<?php 
$shouldOpenRx = !empty($_GET['print_rx']) && $currentEncounter;
$docUser = null;
if ($currentEncounter) {
    $docStmt = $db->prepare("
        SELECT u.full_name, u.email, d.department_name, u.consultation_fee 
        FROM `users` u 
        LEFT JOIN `departments` d ON u.department_id = d.id 
        WHERE u.id = ?
    ");
    $docStmt->execute([$currentEncounter['attending_doctor_id']]);
    $docUser = $docStmt->fetch();
}
?>
<div id="prescriptionModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 <?= $shouldOpenRx ? '' : 'hidden' ?>">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] flex flex-col relative">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 no-print">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-file-prescription text-teal-600"></i>
                <span>Official Clinical Prescription & Medical Record</span>
            </h3>
            <button onclick="document.getElementById('prescriptionModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Printable Document Canvas -->
        <div id="printablePrescriptionArea" class="flex-1 overflow-y-auto custom-scrollbar p-6 border-2 border-dashed border-slate-200 rounded-xl bg-white space-y-4 font-sans text-xs">
            <!-- Letterhead -->
            <div class="flex items-center justify-between border-b-2 border-slate-800 pb-3">
                <div>
                    <div class="font-black text-lg text-slate-900 tracking-tight uppercase"><?= APP_NAME ?></div>
                    <div class="text-[11px] font-semibold text-slate-600"><?= htmlspecialchars($docUser['department_name'] ?? 'Outpatient Department') ?></div>
                    <div class="text-[10px] text-slate-400">Peshawar Medical Complex, Khyber Pakhtunkhwa</div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($docUser['full_name'] ?? 'Attending Consultant') ?></div>
                    <div class="text-[11px] text-slate-500">Consultant Physician & Specialist</div>
                    <div class="text-[10px] font-mono text-slate-400">Encounter: <?= htmlspecialchars($currentEncounter['encounter_number'] ?? '') ?></div>
                </div>
            </div>

            <!-- Patient Demographic & Vitals Bar -->
            <div class="grid grid-cols-4 gap-2 bg-slate-50 p-3 rounded-lg border border-slate-200 text-[11px]">
                <div>
                    <span class="text-slate-400 block text-[9px] uppercase font-bold">Patient Name</span>
                    <strong class="text-slate-900"><?= htmlspecialchars($currentEncounter['first_name'] . ' ' . $currentEncounter['last_name']) ?></strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[9px] uppercase font-bold">MRN / ID</span>
                    <span class="font-mono font-bold text-medical-800"><?= htmlspecialchars($currentEncounter['mrn']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[9px] uppercase font-bold">Age / Gender</span>
                    <span class="text-slate-800"><?= $currentEncounter['age'] ?> Yrs &bull; <?= htmlspecialchars($currentEncounter['gender']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[9px] uppercase font-bold">Visit Date</span>
                    <span class="text-slate-800"><?= format_date($currentEncounter['admission_datetime']) ?></span>
                </div>
                <?php if ($currentVitals): ?>
                <div class="col-span-4 pt-2 border-t border-slate-200/80 flex flex-wrap items-center gap-3 text-[10px] text-slate-600 font-mono">
                    <span><strong>BP:</strong> <?= $currentVitals['systolic_bp'] ?>/<?= $currentVitals['diastolic_bp'] ?> mmHg</span>
                    <span><strong>Pulse:</strong> <?= $currentVitals['pulse_rate_bpm'] ?> bpm</span>
                    <span><strong>Temp:</strong> <?= $currentVitals['temperature_celsius'] ?> °C</span>
                    <span><strong>SpO2:</strong> <?= $currentVitals['spo2_percentage'] ?>%</span>
                    <span><strong>Weight:</strong> <?= $currentVitals['weight_kg'] ?> kg</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Diagnosis -->
            <div class="border-b border-slate-100 pb-2">
                <span class="text-slate-400 text-[9px] uppercase font-bold block">Clinical Diagnosis / Problem</span>
                <div class="font-bold text-slate-900 text-sm flex items-center gap-2 mt-0.5">
                    <span><?= htmlspecialchars($currentNotes['provisional_diagnosis'] ?? ($currentEncounter['primary_diagnosis'] ?? 'Under Evaluation')) ?></span>
                    <?php if (!empty($currentNotes['icd10_code'])): ?>
                        <span class="font-mono text-xs font-semibold bg-slate-100 text-slate-700 px-2 py-0.5 rounded">[ICD-10: <?= htmlspecialchars($currentNotes['icd10_code']) ?>]</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rx Symbol & Medicines Table -->
            <div>
                <div class="font-serif font-black text-xl text-teal-800 mb-1">℞ Prescribed Medications</div>
                <table class="w-full text-left text-xs border border-slate-200 rounded-lg overflow-hidden">
                    <thead class="bg-slate-50 font-bold uppercase text-[9px] text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-1.5">Medicine Name</th>
                            <th class="px-2 py-1.5">Dosage</th>
                            <th class="px-2 py-1.5">Frequency</th>
                            <th class="px-2 py-1.5">Duration</th>
                            <th class="px-3 py-1.5">Instructions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-[11px]">
                        <?php if (empty($currentEncounterRx)): ?>
                            <tr><td colspan="5" class="px-3 py-3 text-center text-slate-400">No oral/injectable medications prescribed in this encounter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($currentEncounterRx as $erx): ?>
                            <tr>
                                <td class="px-3 py-2 font-bold text-slate-900">
                                    <?= htmlspecialchars($erx['brand_name']) ?>
                                    <span class="text-[10px] font-normal text-slate-500 block"><?= htmlspecialchars($erx['generic_name']) ?> (<?= htmlspecialchars($erx['strength']) ?>)</span>
                                </td>
                                <td class="px-2 py-2 text-slate-700"><?= htmlspecialchars($erx['dosage']) ?></td>
                                <td class="px-2 py-2 font-semibold text-slate-800"><?= htmlspecialchars($erx['frequency']) ?></td>
                                <td class="px-2 py-2 text-slate-700"><?= $erx['duration_days'] ?> Days</td>
                                <td class="px-3 py-2 text-slate-600 italic"><?= htmlspecialchars($erx['instructions']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Ordered Diagnostic Investigations -->
            <?php if (!empty($currentEncounterLabs)): ?>
            <div class="bg-purple-50/50 p-3 rounded-lg border border-purple-200">
                <span class="text-purple-900 text-[10px] uppercase font-bold block mb-1">Ordered Laboratory & Diagnostic Tests:</span>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($currentEncounterLabs as $el): ?>
                        <span class="px-2 py-1 bg-white border border-purple-300 rounded font-semibold text-purple-900 text-xs shadow-xs">
                            <?= htmlspecialchars($el['test_name']) ?> (<?= htmlspecialchars($el['specimen_type']) ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Advice & Follow-up Guidance -->
            <div class="grid grid-cols-2 gap-3 pt-2">
                <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                    <span class="text-slate-400 text-[9px] uppercase font-bold block">Doctor Advice & Lifestyle</span>
                    <p class="text-slate-800 mt-1"><?= htmlspecialchars($currentNotes['clinical_advice'] ?? 'Follow prescribed medication and diet as instructed.') ?></p>
                </div>
                <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                    <span class="text-slate-400 text-[9px] uppercase font-bold block">Scheduled Review / Follow-up</span>
                    <strong class="text-medical-800 font-bold text-xs mt-1 block">
                        <?= !empty($currentNotes['follow_up_date']) ? format_date($currentNotes['follow_up_date']) : 'As needed / SOS' ?>
                    </strong>
                </div>
            </div>

            <!-- Sign-off Block -->
            <div class="pt-8 flex justify-between items-end border-t border-slate-200">
                <div class="text-[9px] text-slate-400">
                    Generated via <?= APP_NAME ?> EMR &bull; Computer generated prescription
                </div>
                <div class="text-center w-48 border-t border-slate-800 pt-1">
                    <span class="font-bold text-slate-900 block"><?= htmlspecialchars($docUser['full_name'] ?? 'Doctor') ?></span>
                    <span class="text-[9px] text-slate-500">Authorized Medical Signature & Stamp</span>
                </div>
            </div>
        </div>

        <!-- Print Action Buttons -->
        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-end gap-2 no-print">
            <button type="button" onclick="document.getElementById('prescriptionModal').classList.add('hidden')" class="px-4 py-1.5 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                Close
            </button>
            <button type="button" onclick="printPrescriptionDoc()" class="px-5 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-sm flex items-center gap-1.5">
                <i class="fa-solid fa-print text-[11px]"></i>
                <span>Print Official Prescription</span>
            </button>
        </div>
    </div>
</div>

<script>
    function addPrescriptionRow() {
        const container = document.getElementById('rxRowsContainer');
        const firstRow = container.querySelector('.rx-row');
        if (firstRow) {
            const clone = firstRow.cloneNode(true);
            clone.querySelectorAll('input').forEach(i => {
                if (i.name === 'rx_days[]') i.value = '5';
                else if (i.name === 'rx_instructions[]') i.value = 'After meals';
                else if (i.name === 'rx_dose[]') i.value = '1 Tab';
                else i.value = '';
            });
            container.appendChild(clone);
        }
    }

    function printPrescriptionDoc() {
        const printContent = document.getElementById('printablePrescriptionArea').innerHTML;
        const printWindow = window.open('', '', 'height=750,width=650');
        printWindow.document.write('<html><head><title>Prescription - <?= htmlspecialchars($currentEncounter['mrn'] ?? '') ?></title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tailwindcss/typography@0.5.x/dist/typography.min.css">');
        printWindow.document.write('<style>body{font-family: sans-serif; padding: 25px;} table{width:100%; border-collapse:collapse;} th,td{border:1px solid #e2e8f0; padding:6px;}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContent);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
</script>
