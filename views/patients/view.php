<?php
/**
 * Hospital Management System (HMS) - Patient 360° Comprehensive Profile
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$patientId = (int)($_GET['id'] ?? 0);
$activeTab = clean_input($_GET['tab'] ?? 'overview');

// 1. Fetch Patient Info
$stmt = $db->prepare("SELECT * FROM `patients` WHERE `id` = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    echo "<div class='p-6 text-center text-rose-600 font-bold'>Patient not found.</div>";
    return;
}

// 2. Fetch Allergies
$allergiesStmt = $db->prepare("SELECT * FROM `patient_allergies` WHERE `patient_id` = ?");
$allergiesStmt->execute([$patientId]);
$allergies = $allergiesStmt->fetchAll();

// 3. Fetch Encounters
$encountersStmt = $db->prepare("
    SELECT e.*, u.full_name AS doctor_name, d.department_name 
    FROM `encounters` e
    JOIN `users` u ON e.attending_doctor_id = u.id
    JOIN `departments` d ON e.department_id = d.id
    WHERE e.patient_id = ?
    ORDER BY e.admission_datetime DESC
");
$encountersStmt->execute([$patientId]);
$encounters = $encountersStmt->fetchAll();

// 4. Fetch Vitals History
$vitalsStmt = $db->prepare("
    SELECT v.*, u.full_name AS nurse_name, e.encounter_number
    FROM `clinical_vitals` v
    JOIN `users` u ON v.recorded_by = u.id
    JOIN `encounters` e ON v.encounter_id = e.id
    WHERE v.patient_id = ?
    ORDER BY v.recorded_at DESC
");
$vitalsStmt->execute([$patientId]);
$vitalsList = $vitalsStmt->fetchAll();

// 5. Fetch Prescriptions
$rxStmt = $db->prepare("
    SELECT p.*, u.full_name AS doctor_name, e.encounter_number,
           (SELECT COUNT(*) FROM `prescription_items` WHERE `prescription_id` = p.id) AS total_items
    FROM `prescriptions` p
    JOIN `encounters` e ON p.encounter_id = e.id
    JOIN `users` u ON p.doctor_id = u.id
    WHERE e.patient_id = ?
    ORDER BY p.created_at DESC
");
$rxStmt->execute([$patientId]);
$prescriptions = $rxStmt->fetchAll();

// 6. Fetch Lab Orders & Results
$labStmt = $db->prepare("
    SELECT o.*, u.full_name AS doctor_name, e.encounter_number
    FROM `lab_orders` o
    JOIN `encounters` e ON o.encounter_id = e.id
    JOIN `users` u ON o.ordered_by = u.id
    WHERE e.patient_id = ?
    ORDER BY o.order_datetime DESC
");
$labStmt->execute([$patientId]);
$labOrders = $labStmt->fetchAll();

// 7. Fetch Bills & Invoices
$billsStmt = $db->prepare("
    SELECT b.*, e.encounter_number, u.full_name AS cashier_name
    FROM `bills` b
    JOIN `encounters` e ON b.encounter_id = e.id
    JOIN `users` u ON b.created_by = u.id
    WHERE b.patient_id = ?
    ORDER BY b.bill_date DESC
");
$billsStmt->execute([$patientId]);
$bills = $billsStmt->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Breadcrumb & Back -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-slate-400 text-xs">
            <a href="index.php?page=patients" class="hover:text-medical-600">Patients</a>
            <span>/</span>
            <span class="text-slate-600 font-medium">Patient 360° Profile</span>
        </div>
        <a href="index.php?page=patients" class="text-xs text-slate-600 hover:text-slate-900 font-semibold flex items-center gap-1">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Directory
        </a>
    </div>

    <!-- Patient Master Identity Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            
            <!-- Avatar & Demographics -->
            <div class="flex items-start gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-medical-600 to-medical-800 text-white flex items-center justify-center text-2xl font-bold shadow-md shadow-medical-600/20 flex-shrink-0">
                    <?= strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h2 class="text-xl font-bold text-slate-900">
                            <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-medical-50 text-medical-800 border border-medical-200">
                            <?= htmlspecialchars($patient['mrn']) ?>
                        </span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                            <?= htmlspecialchars($patient['blood_group']) ?>
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                        <span><i class="fa-solid fa-venus-mars text-slate-400 mr-1"></i> <?= htmlspecialchars($patient['gender']) ?></span>
                        <span><i class="fa-solid fa-cake-candles text-slate-400 mr-1"></i> <?= $patient['age'] ?> yrs (DOB: <?= format_date($patient['dob']) ?>)</span>
                        <span><i class="fa-solid fa-phone text-slate-400 mr-1"></i> <?= htmlspecialchars($patient['phone_primary']) ?></span>
                        <span><i class="fa-solid fa-location-dot text-slate-400 mr-1"></i> <?= htmlspecialchars($patient['city']) ?></span>
                    </div>

                    <div class="text-[11px] text-slate-400 mt-1">
                        Kin: <strong><?= htmlspecialchars($patient['emergency_contact_name'] ?? 'N/A') ?></strong> (<?= htmlspecialchars($patient['emergency_contact_relation'] ?? '') ?>) &bull; <?= htmlspecialchars($patient['emergency_contact_phone'] ?? '') ?>
                    </div>
                </div>
            </div>

            <!-- Quick Action Hotbuttons for This Patient -->
            <div class="flex flex-wrap items-center gap-2 self-start md:self-center">
                <a href="index.php?page=appointments&patient_id=<?= $patient['id'] ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-calendar-plus text-slate-500"></i>
                    <span>Schedule Appt</span>
                </a>
                <a href="index.php?page=opd_consultation&patient_id=<?= $patient['id'] ?>" class="px-3.5 py-1.5 bg-medical-600 hover:bg-medical-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-stethoscope"></i>
                    <span>Start Consultation</span>
                </a>
            </div>

        </div>

        <!-- Severe Allergy Banner (If Recorded) -->
        <?php if (!empty($allergies)): ?>
        <div class="mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
            </div>
            <div class="text-xs">
                <span class="font-bold text-rose-800 uppercase tracking-wide">Recorded Clinical Allergies:</span>
                <?php foreach ($allergies as $alg): ?>
                    <span class="inline-block font-semibold text-rose-900 bg-rose-200/60 px-2 py-0.5 rounded ml-1">
                        <?= htmlspecialchars($alg['allergen_name']) ?> (<?= htmlspecialchars($alg['severity']) ?>)
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Navigation Tabs for 360° View -->
    <div class="border-b border-slate-200 flex items-center gap-6 text-xs font-semibold">
        <?php 
        $tabs = [
            'overview' => ['label' => 'Encounters & Care', 'icon' => 'fa-stethoscope'],
            'vitals' => ['label' => 'Vitals Timeline', 'icon' => 'fa-heart-pulse'],
            'prescriptions' => ['label' => 'Prescriptions', 'icon' => 'fa-pills'],
            'labs' => ['label' => 'Laboratory Reports', 'icon' => 'fa-flask-vial'],
            'billing' => ['label' => 'Invoices & Receipts', 'icon' => 'fa-receipt'],
        ];
        foreach ($tabs as $key => $t):
            $isActive = ($activeTab === $key);
        ?>
        <a href="index.php?page=patient_profile&id=<?= $patient['id'] ?>&tab=<?= $key ?>" class="pb-3 flex items-center gap-2 border-b-2 transition-all <?= $isActive ? 'border-medical-600 text-medical-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid <?= $t['icon'] ?>"></i>
            <span><?= $t['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Tab 1: Encounters History -->
    <?php if ($activeTab === 'overview'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-sm">Longitudinal Visit & Encounter History</h3>
            <span class="text-xs text-slate-400"><?= count($encounters) ?> total recorded</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3">Encounter ID</th>
                        <th class="px-4 py-3">Type / Triage</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Attending Doctor</th>
                        <th class="px-4 py-3">Date & Time</th>
                        <th class="px-4 py-3">Diagnosis</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($encounters)): ?>
                        <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">No encounters on file.</td></tr>
                    <?php else: ?>
                        <?php foreach ($encounters as $enc): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-mono font-semibold text-slate-700"><?= htmlspecialchars($enc['encounter_number']) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $enc['encounter_type'] === 'Emergency' ? 'bg-rose-100 text-rose-700' : ($enc['encounter_type'] === 'IPD' ? 'bg-purple-100 text-purple-700' : 'bg-sky-100 text-sky-700') ?>">
                                    <?= htmlspecialchars($enc['encounter_type']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($enc['department_name']) ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($enc['doctor_name']) ?></td>
                            <td class="px-4 py-3 text-slate-500"><?= format_datetime($enc['admission_datetime']) ?></td>
                            <td class="px-4 py-3 text-slate-700 max-w-xs truncate"><?= htmlspecialchars($enc['primary_diagnosis'] ?? '—') ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= $enc['encounter_status'] === 'Completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                                    <?= htmlspecialchars($enc['encounter_status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <a href="index.php?page=opd_consultation&encounter_id=<?= $enc['id'] ?>" class="px-2.5 py-1 bg-medical-50 text-medical-700 hover:bg-medical-100 rounded text-xs font-semibold">
                                    Open
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 2: Vitals Timeline -->
    <?php if ($activeTab === 'vitals'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-sm">Hemodynamic Vitals Charting</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3">Date / Encounter</th>
                        <th class="px-4 py-3">Blood Pressure</th>
                        <th class="px-4 py-3">Pulse</th>
                        <th class="px-4 py-3">Temp (°C)</th>
                        <th class="px-4 py-3">SpO2</th>
                        <th class="px-4 py-3">Blood Sugar</th>
                        <th class="px-4 py-3">BMI</th>
                        <th class="px-6 py-3">Recorded By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($vitalsList)): ?>
                        <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">No vitals recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vitalsList as $v): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <div class="font-semibold text-slate-800"><?= format_datetime($v['recorded_at']) ?></div>
                                <div class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($v['encounter_number']) ?></div>
                            </td>
                            <td class="px-4 py-3 font-semibold <?= ($v['systolic_bp'] > 140 || $v['diastolic_bp'] > 90) ? 'text-rose-600' : 'text-slate-800' ?>">
                                <?= $v['systolic_bp'] ?? '—' ?> / <?= $v['diastolic_bp'] ?? '—' ?> mmHg
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?= $v['pulse_rate_bpm'] ?? '—' ?> bpm</td>
                            <td class="px-4 py-3 <?= ($v['temperature_celsius'] > 37.5) ? 'text-rose-600 font-bold' : 'text-slate-700' ?>">
                                <?= $v['temperature_celsius'] ?? '—' ?> °C
                            </td>
                            <td class="px-4 py-3 <?= ($v['spo2_percentage'] < 95) ? 'text-amber-600 font-bold' : 'text-slate-700' ?>">
                                <?= $v['spo2_percentage'] ?? '—' ?> %
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?= $v['blood_glucose_mg_dl'] ?? '—' ?> mg/dL</td>
                            <td class="px-4 py-3 text-slate-700"><?= $v['bmi'] ?? '—' ?></td>
                            <td class="px-6 py-3 text-slate-500"><?= htmlspecialchars($v['nurse_name']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 3: Prescriptions -->
    <?php if ($activeTab === 'prescriptions'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-sm">Medical Prescriptions & Regimens</h3>
            <a href="index.php?page=pharmacy" class="text-xs text-medical-600 font-semibold hover:underline">Pharmacy Counter &rarr;</a>
        </div>
        <div class="p-6 space-y-4">
            <?php if (empty($prescriptions)): ?>
                <div class="text-center py-8 text-slate-400">No prescriptions recorded for this patient.</div>
            <?php else: ?>
                <?php foreach ($prescriptions as $rx): 
                    // Fetch items for this prescription
                    $rxItemsStmt = $db->prepare("
                        SELECT pi.*, di.brand_name, di.generic_name, di.strength, di.dosage_form
                        FROM `prescription_items` pi
                        JOIN `drug_items` di ON pi.drug_item_id = di.id
                        WHERE pi.prescription_id = ?
                    ");
                    $rxItemsStmt->execute([$rx['id']]);
                    $items = $rxItemsStmt->fetchAll();
                ?>
                <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-xs text-medical-800"><?= htmlspecialchars($rx['prescription_number']) ?></span>
                            <span class="text-xs text-slate-400">&bull;</span>
                            <span class="text-xs text-slate-600">Prescribed by <strong><?= htmlspecialchars($rx['doctor_name']) ?></strong></span>
                            <span class="text-xs text-slate-400">&bull;</span>
                            <span class="text-xs text-slate-500"><?= format_datetime($rx['created_at']) ?></span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $rx['status'] === 'Dispensed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                            <?= htmlspecialchars($rx['status']) ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach ($items as $item): ?>
                        <div class="p-3 bg-white border border-slate-200 rounded-lg">
                            <div class="font-bold text-xs text-slate-900"><?= htmlspecialchars($item['brand_name']) ?></div>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($item['generic_name']) ?> (<?= htmlspecialchars($item['strength']) ?>)</div>
                            <div class="mt-2 text-xs font-mono font-semibold text-medical-700">
                                <?= htmlspecialchars($item['dosage']) ?> &bull; <?= htmlspecialchars($item['frequency']) ?> &bull; <?= $item['duration_days'] ?>d
                            </div>
                            <div class="text-[10px] text-slate-500 mt-1 italic"><?= htmlspecialchars($item['instructions']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 4: Laboratory Results -->
    <?php if ($activeTab === 'labs'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-sm">Laboratory Diagnostic Investigations</h3>
            <a href="index.php?page=laboratory" class="text-xs text-medical-600 font-semibold hover:underline">Lab Workspace &rarr;</a>
        </div>
        <div class="p-6 space-y-4">
            <?php if (empty($labOrders)): ?>
                <div class="text-center py-8 text-slate-400">No laboratory investigations requested.</div>
            <?php else: ?>
                <?php foreach ($labOrders as $order): 
                    $orderItemsStmt = $db->prepare("
                        SELECT loi.*, lt.test_name, lt.test_code, lt.specimen_type
                        FROM `lab_order_items` loi
                        JOIN `lab_tests` lt ON loi.lab_test_id = lt.id
                        WHERE loi.lab_order_id = ?
                    ");
                    $orderItemsStmt->execute([$order['id']]);
                    $tests = $orderItemsStmt->fetchAll();
                ?>
                <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-xs text-slate-800"><?= htmlspecialchars($order['order_number']) ?></span>
                            <span class="text-xs text-slate-400">&bull;</span>
                            <span class="text-xs text-slate-600">Ordered by <?= htmlspecialchars($order['doctor_name']) ?></span>
                            <span class="text-xs text-slate-400">&bull;</span>
                            <span class="text-xs text-slate-500"><?= format_datetime($order['order_datetime']) ?></span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-700">
                            <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </div>

                    <div class="space-y-2">
                        <?php foreach ($tests as $t): ?>
                        <div class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-lg text-xs">
                            <div>
                                <span class="font-bold text-slate-800"><?= htmlspecialchars($t['test_name']) ?></span>
                                <span class="font-mono text-[10px] text-slate-400 ml-1">(<?= htmlspecialchars($t['test_code']) ?>)</span>
                                <div class="text-[10px] text-slate-500">Specimen: <?= htmlspecialchars($t['specimen_type']) ?> &bull; Barcode: <?= htmlspecialchars($t['sample_barcode'] ?? 'Pending') ?></div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700">
                                <?= htmlspecialchars($t['item_status']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 5: Invoices & Receipts -->
    <?php if ($activeTab === 'billing'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-sm">Financial Invoices & Receipts</h3>
            <a href="index.php?page=billing" class="text-xs text-medical-600 font-semibold hover:underline">Cashier Desk &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3">Invoice #</th>
                        <th class="px-4 py-3">Encounter</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Gross</th>
                        <th class="px-4 py-3">Net Payable</th>
                        <th class="px-4 py-3">Paid</th>
                        <th class="px-4 py-3">Balance</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($bills)): ?>
                        <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">No invoices generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bills as $b): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-mono font-bold text-slate-800"><?= htmlspecialchars($b['bill_number']) ?></td>
                            <td class="px-4 py-3 font-mono text-slate-500"><?= htmlspecialchars($b['encounter_number']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= format_date($b['bill_date']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= format_currency($b['gross_amount']) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-800"><?= format_currency($b['net_amount']) ?></td>
                            <td class="px-4 py-3 font-semibold text-emerald-600"><?= format_currency($b['paid_amount']) ?></td>
                            <td class="px-4 py-3 font-semibold <?= $b['balance_amount'] > 0 ? 'text-rose-600' : 'text-slate-500' ?>">
                                <?= format_currency($b['balance_amount']) ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $b['payment_status'] === 'Fully-Paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                    <?= htmlspecialchars($b['payment_status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
