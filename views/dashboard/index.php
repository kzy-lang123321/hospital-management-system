<?php
/**
 * Hospital Management System (HMS) - Executive & Role Dashboard
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

// 1. Fetch Metrics
$totalPatients = $db->query("SELECT COUNT(*) FROM `patients` WHERE `status` = 'Active'")->fetchColumn();
$todayEncounters = $db->query("SELECT COUNT(*) FROM `encounters` WHERE DATE(`admission_datetime`) = CURDATE()")->fetchColumn();
$totalBeds = $db->query("SELECT COUNT(*) FROM `beds`")->fetchColumn();
$occupiedBeds = $db->query("SELECT COUNT(*) FROM `beds` WHERE `status` = 'Occupied'")->fetchColumn();
$bedOccupancyPercent = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100) : 0;

$todayRevenue = $db->query("SELECT COALESCE(SUM(`amount_paid`), 0) FROM `payments` WHERE DATE(`payment_datetime`) = CURDATE()")->fetchColumn();
$pendingLabs = $db->query("SELECT COUNT(*) FROM `lab_order_items` WHERE `item_status` IN ('Pending', 'Collected')")->fetchColumn();
$pendingPrescriptions = $db->query("SELECT COUNT(*) FROM `prescriptions` WHERE `status` = 'Pending'")->fetchColumn();

// 2. Fetch Today's Appointments & OPD Queue
$todayQueue = $db->query("
    SELECT e.*, p.mrn, p.first_name, p.last_name, p.gender, p.age, p.blood_group,
           u.full_name AS doctor_name, d.department_name
    FROM `encounters` e
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` u ON e.attending_doctor_id = u.id
    JOIN `departments` d ON e.department_id = d.id
    WHERE e.encounter_status = 'In-Progress'
    ORDER BY e.admission_datetime DESC
    LIMIT 10
")->fetchAll();

// 3. Fetch Bed Statuses by Ward
$wardSummary = $db->query("
    SELECT w.ward_name, w.ward_type, w.floor_number,
           COUNT(b.id) AS total_beds,
           SUM(CASE WHEN b.status = 'Occupied' THEN 1 ELSE 0 END) AS occupied_beds,
           SUM(CASE WHEN b.status = 'Available' THEN 1 ELSE 0 END) AS available_beds
    FROM `wards` w
    LEFT JOIN `rooms` r ON w.id = r.ward_id
    LEFT JOIN `beds` b ON r.id = b.room_id
    GROUP BY w.id
")->fetchAll();

// 4. Strictly Pharmacy Patients: Prescriptions & Dispensation Receipts Review
$pharmacyReviews = $db->query("
    SELECT 
        pr.id AS prescription_id,
        pr.prescription_number,
        pr.status AS rx_status,
        pr.created_at AS rx_date,
        p.id AS patient_id,
        p.mrn,
        p.first_name,
        p.last_name,
        p.gender,
        p.age,
        p.phone_primary,
        doc.full_name AS doctor_name,
        ps.id AS sale_id,
        ps.sale_invoice_number,
        ps.net_total AS sale_net_total,
        ps.created_at AS sale_date,
        ps.payment_method,
        u.full_name AS pharmacist_name,
        (SELECT COUNT(*) FROM `prescription_items` WHERE `prescription_id` = pr.id) AS total_prescribed_items,
        (SELECT GROUP_CONCAT(CONCAT(di.brand_name, ' (', pi.dosage, ')') SEPARATOR ', ')
         FROM `prescription_items` pi
         JOIN `drug_items` di ON pi.drug_item_id = di.id
         WHERE pi.prescription_id = pr.id) AS rx_medications,
        (SELECT b.bill_number 
         FROM `bills` b 
         JOIN `bill_items` bi ON bi.bill_id = b.id 
         WHERE b.encounter_id = pr.encounter_id AND bi.item_type = 'Medication' 
         LIMIT 1) AS linked_bill_number
    FROM `prescriptions` pr
    JOIN `encounters` e ON pr.encounter_id = e.id
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` doc ON pr.doctor_id = doc.id
    LEFT JOIN `pharmacy_sales` ps ON ps.prescription_id = pr.id
    LEFT JOIN `users` u ON ps.dispensed_by = u.id
    ORDER BY pr.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Top Banner / Clinical Context -->
    <div class="bg-gradient-to-r from-navy-900 via-navy-800 to-medical-900 text-white rounded-2xl p-6 shadow-md relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-medical-300 text-xs font-semibold tracking-wider uppercase mb-1">
                    <span class="w-2 h-2 rounded-full bg-medical-400"></span>
                    <span>Live Facility Status — Hayat Medicare</span>
                </div>
                <h2 class="text-xl md:text-2xl font-bold">Welcome back, <?= htmlspecialchars($currentUser['full_name']) ?></h2>
                <p class="text-xs md:text-sm text-slate-300 mt-0.5">
                    Role: <strong class="text-white"><?= htmlspecialchars($currentUser['role_name']) ?></strong> &bull; 
                    Department: <span class="text-medical-200"><?= htmlspecialchars($currentUser['department_name'] ?? 'General') ?></span>
                </p>
            </div>
            <!-- Quick Action Hotbuttons -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="index.php?page=patient_create" class="px-3.5 py-2 bg-medical-600 hover:bg-medical-500 text-white text-xs font-semibold rounded-lg shadow-sm transition-all flex items-center gap-2">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Register Patient</span>
                </a>
                <a href="index.php?page=appointments" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-semibold rounded-lg border border-white/20 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>New Appointment</span>
                </a>
                <a href="index.php?page=opd_consultation" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-semibold rounded-lg border border-white/20 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-stethoscope"></i>
                    <span>OPD Workbench</span>
                </a>
            </div>
        </div>
        <!-- Decorative Background Element -->
        <i class="fa-solid fa-staff-snake absolute right-4 -bottom-6 text-white/5 text-9xl pointer-events-none"></i>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        
        <!-- Total Patients -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center text-lg flex-shrink-0">
                <i class="fa-solid fa-hospital-user"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Total Patients</p>
                <h3 class="text-xl font-bold text-slate-800"><?= number_format($totalPatients) ?></h3>
                <p class="text-[10px] text-emerald-600 font-medium"><i class="fa-solid fa-id-card"></i> Unique MRNs</p>
            </div>
        </div>

        <!-- Today Encounters -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg flex-shrink-0">
                <i class="fa-solid fa-stethoscope"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Today's Visits</p>
                <h3 class="text-xl font-bold text-slate-800"><?= number_format($todayEncounters) ?></h3>
                <p class="text-[10px] text-slate-500">OPD & ER Intake</p>
            </div>
        </div>

        <!-- Bed Occupancy -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-lg flex-shrink-0">
                <i class="fa-solid fa-bed"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Bed Occupancy</p>
                <h3 class="text-xl font-bold text-slate-800"><?= $bedOccupancyPercent ?>%</h3>
                <p class="text-[10px] text-slate-500"><?= $occupiedBeds ?> / <?= $totalBeds ?> Beds in use</p>
            </div>
        </div>

        <!-- Pending Prescriptions -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center text-lg flex-shrink-0">
                <i class="fa-solid fa-pills"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Pharmacy Queue</p>
                <h3 class="text-xl font-bold text-slate-800"><?= number_format($pendingPrescriptions) ?></h3>
                <p class="text-[10px] text-amber-600 font-medium">To be Dispensed</p>
            </div>
        </div>

        <!-- Pending Labs -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-lg flex-shrink-0">
                <i class="fa-solid fa-vial-virus"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Pending Labs</p>
                <h3 class="text-xl font-bold text-slate-800"><?= number_format($pendingLabs) ?></h3>
                <p class="text-[10px] text-slate-500">In Process/Review</p>
            </div>
        </div>

        <!-- Daily Collections -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-lg flex-shrink-0">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Today Revenue</p>
                <h3 class="text-lg font-bold text-slate-800 truncate"><?= format_currency($todayRevenue) ?></h3>
                <p class="text-[10px] text-emerald-600 font-medium">Cashier Settled</p>
            </div>
        </div>

    </div>

    <!-- Main Content Section: Two Columns -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Active Encounters & Patient Queue -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Active In-Progress Encounters</h3>
                    <p class="text-xs text-slate-500">Patients currently undergoing consultation or active care</p>
                </div>
                <a href="index.php?page=opd_consultation" class="text-xs text-medical-600 font-semibold hover:underline">
                    View All Queue &rarr;
                </a>
            </div>

            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3">Patient Details</th>
                            <th class="px-4 py-3">Encounter / Type</th>
                            <th class="px-4 py-3">Attending Doctor</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($todayQueue)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-400">
                                    <i class="fa-solid fa-clipboard-check text-2xl mb-2 text-slate-300"></i>
                                    <p>No active in-progress encounters right now.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($todayQueue as $enc): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-3">
                                    <div class="font-semibold text-slate-800">
                                        <?= htmlspecialchars($enc['first_name'] . ' ' . $enc['last_name']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 flex items-center gap-2">
                                        <span class="font-mono text-medical-700 font-medium"><?= htmlspecialchars($enc['mrn']) ?></span>
                                        <span>&bull;</span>
                                        <span><?= htmlspecialchars($enc['gender']) ?>, <?= $enc['age'] ?>y</span>
                                        <span>&bull;</span>
                                        <span class="font-medium text-rose-600"><?= htmlspecialchars($enc['blood_group']) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $enc['encounter_type'] === 'Emergency' ? 'bg-rose-100 text-rose-700' : ($enc['encounter_type'] === 'IPD' ? 'bg-purple-100 text-purple-700' : 'bg-sky-100 text-sky-700') ?>">
                                        <?= htmlspecialchars($enc['encounter_type']) ?>
                                    </span>
                                    <div class="text-[10px] text-slate-400 mt-0.5 font-mono"><?= htmlspecialchars($enc['encounter_number']) ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-700"><?= htmlspecialchars($enc['doctor_name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($enc['department_name']) ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        <?= htmlspecialchars($enc['encounter_status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="index.php?page=opd_consultation&encounter_id=<?= $enc['id'] ?>" class="px-2.5 py-1 bg-medical-50 text-medical-700 hover:bg-medical-100 rounded text-xs font-semibold transition-colors">
                                            Consult
                                        </a>
                                        <a href="index.php?page=patient_profile&id=<?= $enc['patient_id'] ?>" class="p-1 text-slate-400 hover:text-slate-600 rounded" title="View Patient 360°">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right 1 Col: Ward Bed Census & Turnaround -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Ward Bed Census</h3>
                        <p class="text-xs text-slate-500">Real-time bed availability</p>
                    </div>
                    <a href="index.php?page=ipd_beds" class="text-xs text-medical-600 font-semibold hover:underline">
                        Bed Rack &rarr;
                    </a>
                </div>

                <!-- Wards List -->
                <div class="divide-y divide-slate-100 mt-2 space-y-2">
                    <?php foreach ($wardSummary as $w): 
                        $wPercent = $w['total_beds'] > 0 ? round(($w['occupied_beds'] / $w['total_beds']) * 100) : 0;
                    ?>
                    <div class="pt-2">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-semibold text-slate-700 truncate"><?= htmlspecialchars($w['ward_name']) ?></span>
                            <span class="font-mono text-[11px] text-slate-500">
                                <strong class="text-medical-700"><?= $w['available_beds'] ?></strong> / <?= $w['total_beds'] ?> Avail
                            </span>
                        </div>
                        <!-- Progress bar -->
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="h-full rounded-full <?= $wPercent > 80 ? 'bg-rose-500' : ($wPercent > 50 ? 'bg-amber-500' : 'bg-medical-500') ?>" style="width: <?= $wPercent ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Emergency Bed Reservation Trigger -->
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                <div class="flex items-center gap-2 text-slate-700 text-xs font-semibold mb-1">
                    <i class="fa-solid fa-truck-medical text-medical-600"></i>
                    <span>Need Immediate Admission?</span>
                </div>
                <p class="text-[11px] text-slate-500 mb-2">Check real-time ICU or General bed status directly in the interactive rack.</p>
                <a href="index.php?page=ipd_beds" class="block w-full text-center py-1.5 bg-navy-900 hover:bg-navy-800 text-white text-xs font-semibold rounded-lg transition-colors">
                    Open Bed Rack Matrix
                </a>
            </div>

        </div>

    </div>

    <!-- Pharmacy Patient Dispensation & Receipt Review Section (Strictly Pharmacy Patients) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gradient-to-r from-teal-50/70 to-emerald-50/40">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-pulse"></span>
                    <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-teal-600"></i>
                        <span>Pharmacy Patient Dispensation &amp; Receipt Review</span>
                    </h3>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Real-time pharmacy-dispensed and prescribed patient receipts, FEFO deductions, and billing audit</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="index.php?page=pharmacy&tab=queue" class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-prescription"></i>
                    <span>Open Dispensing Queue</span>
                </a>
                <a href="index.php?page=pharmacy&tab=stock" class="px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-boxes-stacked text-teal-600"></i>
                    <span>Formulary Batches</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3">Patient (Pharmacy Only)</th>
                        <th class="px-4 py-3">Prescription #</th>
                        <th class="px-4 py-3">Receipt / Invoice #</th>
                        <th class="px-4 py-3">Prescribed Regimen</th>
                        <th class="px-4 py-3">Attending / Pharmacist</th>
                        <th class="px-4 py-3">Total Amount</th>
                        <th class="px-4 py-3">Dispense Status</th>
                        <th class="px-6 py-3 text-right">Review Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($pharmacyReviews)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-slate-400">
                            <i class="fa-solid fa-file-prescription text-2xl mb-2 text-slate-300"></i>
                            <p>No pharmacy patient transactions recorded yet.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($pharmacyReviews as $rx): 
                        $isDisp = ($rx['rx_status'] === 'Dispensed');
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5">
                            <div class="font-bold text-slate-900">
                                <a href="index.php?page=patient_profile&id=<?= $rx['patient_id'] ?>" class="hover:text-teal-700">
                                    <?= htmlspecialchars($rx['first_name'] . ' ' . $rx['last_name']) ?>
                                </a>
                            </div>
                            <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                <span class="font-mono text-teal-700 font-semibold"><?= htmlspecialchars($rx['mrn']) ?></span>
                                <span>&bull;</span>
                                <span><?= htmlspecialchars($rx['gender']) ?>, <?= $rx['age'] ?>y</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-800 font-semibold">
                            <?= htmlspecialchars($rx['prescription_number']) ?>
                            <div class="text-[10px] text-slate-400 font-normal"><?= date('d M Y, h:i A', strtotime($rx['rx_date'])) ?></div>
                        </td>
                        <td class="px-4 py-3.5 font-mono">
                            <?php if (!empty($rx['sale_invoice_number'])): ?>
                                <span class="font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded border border-teal-200">
                                    <?= htmlspecialchars($rx['sale_invoice_number']) ?>
                                </span>
                            <?php elseif (!empty($rx['linked_bill_number'])): ?>
                                <span class="font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                                    <?= htmlspecialchars($rx['linked_bill_number']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400 text-[11px] italic">Pending Sale</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5 max-w-xs truncate">
                            <div class="text-slate-800 font-medium truncate" title="<?= htmlspecialchars($rx['rx_medications'] ?? 'None') ?>">
                                <?= htmlspecialchars($rx['rx_medications'] ?? 'No items listed') ?>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                <?= (int)$rx['total_prescribed_items'] ?> item(s) prescribed
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-slate-700 font-medium"><?= htmlspecialchars($rx['doctor_name']) ?></div>
                            <?php if (!empty($rx['pharmacist_name'])): ?>
                                <div class="text-[10px] text-teal-700 flex items-center gap-1 mt-0.5 font-medium">
                                    <i class="fa-solid fa-user-check text-[9px]"></i>
                                    <span><?= htmlspecialchars($rx['pharmacist_name']) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="text-[10px] text-amber-600 mt-0.5">Awaiting Dispenser</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-900">
                            <?php if (!empty($rx['sale_net_total'])): ?>
                                <span class="text-emerald-700"><?= format_currency($rx['sale_net_total']) ?></span>
                            <?php else: ?>
                                <span class="text-slate-400 font-normal">Calculated on Dispense</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isDisp ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200' ?>">
                                <i class="fa-solid <?= $isDisp ? 'fa-circle-check' : 'fa-clock' ?> mr-1 text-[9px]"></i>
                                <?= htmlspecialchars($rx['rx_status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                <a href="index.php?page=pharmacy&tab=queue&print_receipt=<?= $rx['prescription_id'] ?>" class="px-2.5 py-1 bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 rounded font-semibold text-xs flex items-center gap-1 transition-colors" title="Review Two-Part Dispensing Receipt">
                                    <i class="fa-solid fa-receipt text-[11px] text-teal-600"></i>
                                    <span>Review Receipt</span>
                                </a>
                                <?php if (!$isDisp): ?>
                                <a href="index.php?page=pharmacy&tab=queue&search=<?= urlencode($rx['mrn']) ?>" class="px-2.5 py-1 bg-medical-600 hover:bg-medical-700 text-white rounded font-bold text-xs flex items-center gap-1 transition-colors">
                                    <i class="fa-solid fa-box-open text-[10px]"></i>
                                    <span>Dispense</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
