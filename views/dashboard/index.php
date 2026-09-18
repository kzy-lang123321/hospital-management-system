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

// 5. In-Stock Active Medication Batches for Point of Sale (POS)
$posDrugBatches = $db->query("
    SELECT 
        db.id AS batch_id,
        db.batch_number,
        db.expiry_date,
        DATEDIFF(db.expiry_date, CURDATE()) AS days_to_expiry,
        db.unit_sale_price,
        db.current_quantity,
        di.id AS drug_id,
        di.brand_name,
        di.generic_name,
        di.dosage_form,
        di.strength,
        di.is_controlled_substance,
        di.narcotic_schedule,
        di.storage_condition
    FROM `drug_batches` db
    JOIN `drug_items` di ON db.drug_item_id = di.id
    WHERE db.status = 'Active' AND db.current_quantity > 0 AND db.expiry_date > CURDATE()
    ORDER BY di.brand_name ASC, db.expiry_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 6. Registered Patients for POS
$posPatients = $db->query("
    SELECT p.id, p.mrn, p.first_name, p.last_name, p.phone_primary, p.gender, p.age
    FROM `patients` p
    WHERE p.status = 'Active'
    ORDER BY p.first_name ASC
    LIMIT 150
")->fetchAll(PDO::FETCH_ASSOC);

// 7. Recent POS Transactions (Today's Sales)
$recentPosSales = $db->query("
    SELECT ps.*, u.full_name AS cashier_name, p.mrn, p.first_name, p.last_name,
           (SELECT COUNT(*) FROM `pharmacy_sale_items` psi WHERE psi.pharmacy_sale_id = ps.id) AS total_items,
           (SELECT GROUP_CONCAT(CONCAT(di.brand_name, ' (x', psi.quantity, ')') SEPARATOR ', ')
            FROM `pharmacy_sale_items` psi
            JOIN `drug_batches` db ON psi.drug_batch_id = db.id
            JOIN `drug_items` di ON db.drug_item_id = di.id
            WHERE psi.pharmacy_sale_id = ps.id) AS items_summary
    FROM `pharmacy_sales` ps
    JOIN `users` u ON ps.dispensed_by = u.id
    LEFT JOIN `patients` p ON ps.patient_id = p.id
    ORDER BY ps.id DESC
    LIMIT 6
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
                <a href="#dashboard-pos" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center gap-2 border border-emerald-400/30">
                    <i class="fa-solid fa-cash-register"></i>
                    <span>Quick POS Counter</span>
                </a>
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

    <?php if (isset($_GET['pos_sale_id'])): 
        $completedSaleId = (int)$_GET['pos_sale_id'];
        $completedSale = $db->query("SELECT sale_invoice_number, customer_name, net_total FROM `pharmacy_sales` WHERE id = $completedSaleId")->fetch(PDO::FETCH_ASSOC);
    ?>
        <?php if ($completedSale): ?>
        <!-- POS Sale Success Banner -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-2xl p-5 shadow-lg flex flex-col md:flex-row items-center justify-between gap-4 animate-fade-in border border-emerald-400/40">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl flex-shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-200">Point of Sale (POS) Transaction Completed</div>
                    <h4 class="text-base sm:text-lg font-black">Invoice #<?= htmlspecialchars($completedSale['sale_invoice_number']) ?> &bull; <?= format_currency($completedSale['net_total']) ?></h4>
                    <p class="text-xs text-emerald-100">Customer: <strong class="text-white"><?= htmlspecialchars($completedSale['customer_name'] ?? 'Walk-in Customer') ?></strong> &bull; Inventory deducted &bull; Ready for printing.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="index.php?page=print_pos_receipt&sale_id=<?= $completedSaleId ?>&mode=thermal&autoprint=1" target="_blank" class="px-3.5 py-2 bg-white text-emerald-900 hover:bg-emerald-50 rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-receipt text-emerald-600"></i>
                    <span>Print 80mm POS Slip</span>
                </a>
                <a href="index.php?page=print_pos_receipt&sale_id=<?= $completedSaleId ?>&mode=standard" target="_blank" class="px-3.5 py-2 bg-white/15 hover:bg-white/25 text-white rounded-xl text-xs font-bold border border-white/20 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>A4 Invoice</span>
                </a>
                <a href="index.php?page=dashboard" class="p-2 text-white/70 hover:text-white text-sm" title="Dismiss">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

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

    <!-- ========================================================================= -->
    <!-- COMPREHENSIVE POINT OF SALE (POS) COUNTER & TERMINAL -->
    <!-- ========================================================================= -->
    <div id="dashboard-pos" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden scroll-mt-6">
        
        <!-- POS Top Bar -->
        <div class="px-6 py-4 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold tracking-tight">Hospital &amp; Pharmacy Point of Sale (POS)</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>POS-01 READY</span>
                        </span>
                    </div>
                    <p class="text-xs text-slate-300 mt-0.5">
                        Cashier: <strong class="text-white"><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<?= htmlspecialchars($currentUser['role_name']) ?>) &bull;
                        Active Formulary: <span class="text-emerald-400 font-semibold"><?= count($posDrugBatches) ?> In-Stock Batches</span>
                    </p>
                </div>
            </div>

            <!-- Real-time Live Clock & Quick Reset -->
            <div class="flex items-center gap-3 text-xs">
                <div class="bg-slate-800/80 px-3 py-1.5 rounded-lg border border-slate-700 font-mono text-slate-300 flex items-center gap-2">
                    <i class="fa-regular fa-clock text-emerald-400"></i>
                    <span id="pos-live-clock"><?= date('h:i:s A') ?></span>
                </div>
                <button type="button" onclick="resetPosCart()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg border border-slate-700 transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate-right"></i>
                    <span>Reset POS</span>
                </button>
            </div>
        </div>

        <!-- POS Workspace Form -->
        <form method="POST" action="index.php?action=pos_sale_checkout" id="pos-sale-form" onsubmit="return validatePosCheckout(event)">
            <?= csrf_field() ?>

            <div class="p-6 grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Left Column: Customer Details, Product Search & Cart (Cols 1-7) -->
                <div class="lg:col-span-7 space-y-4">
                    
                    <!-- 1. Customer / Patient Context Selector Card -->
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-user-tag text-teal-600"></i>
                                <span>Customer / Patient Type</span>
                            </label>
                            <!-- Switcher Radio Buttons -->
                            <div class="inline-flex bg-slate-200/80 p-0.5 rounded-lg text-xs font-medium">
                                <label class="cursor-pointer px-3 py-1 rounded-md transition-all flex items-center gap-1.5 has-[:checked]:bg-white has-[:checked]:text-teal-800 has-[:checked]:font-bold has-[:checked]:shadow-xs text-slate-600">
                                    <input type="radio" name="customer_type" value="walk_in" checked class="sr-only" onchange="toggleCustomerType('walk_in')">
                                    <i class="fa-solid fa-person-walking"></i>
                                    <span>Walk-in / OTC</span>
                                </label>
                                <label class="cursor-pointer px-3 py-1 rounded-md transition-all flex items-center gap-1.5 has-[:checked]:bg-white has-[:checked]:text-teal-800 has-[:checked]:font-bold has-[:checked]:shadow-xs text-slate-600">
                                    <input type="radio" name="customer_type" value="registered" class="sr-only" onchange="toggleCustomerType('registered')">
                                    <i class="fa-solid fa-hospital-user"></i>
                                    <span>Registered Patient</span>
                                </label>
                            </div>
                        </div>

                        <!-- Walk-in Customer Fields -->
                        <div id="pos-walkin-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-[11px]">Customer / Buyer Name</label>
                                <input type="text" name="customer_name" id="pos_customer_name" value="Walk-in Customer" class="form-input text-xs" placeholder="e.g. Ali Khan">
                            </div>
                            <div>
                                <label class="form-label text-[11px]">Contact Phone Number</label>
                                <input type="text" name="customer_phone" id="pos_customer_phone" class="form-input text-xs" placeholder="e.g. 0300-1234567">
                            </div>
                        </div>

                        <!-- Registered Patient Selector (Hidden by default) -->
                        <div id="pos-registered-fields" class="hidden space-y-2">
                            <div>
                                <label class="form-label text-[11px]">Select Registered Hospital Patient <span class="text-rose-500">*</span></label>
                                <select name="patient_id" id="pos_patient_id" class="form-input text-xs" onchange="onSelectRegisteredPatient(this)">
                                    <option value="">-- Choose Hospital Patient (MRN / Name) --</option>
                                    <?php foreach ($posPatients as $pat): ?>
                                    <option value="<?= $pat['id'] ?>" 
                                            data-name="<?= htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']) ?>" 
                                            data-phone="<?= htmlspecialchars($pat['phone_primary']) ?>"
                                            data-mrn="<?= htmlspecialchars($pat['mrn']) ?>"
                                            data-gender="<?= htmlspecialchars($pat['gender']) ?>"
                                            data-age="<?= (int)$pat['age'] ?>">
                                        <?= htmlspecialchars($pat['mrn']) ?> &bull; <?= htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']) ?> (<?= htmlspecialchars($pat['gender']) ?>, <?= (int)$pat['age'] ?>y) - <?= htmlspecialchars($pat['phone_primary']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="pos-selected-pat-info" class="text-[11px] text-teal-800 bg-teal-50/70 p-2 rounded-lg border border-teal-200 hidden">
                                <i class="fa-solid fa-id-badge text-teal-600 mr-1"></i>
                                <span id="pos-pat-summary"></span>
                            </div>
                        </div>

                    </div>

                    <!-- 2. Product Search & Quick Add Bar -->
                    <div class="p-4 bg-teal-50/30 border border-teal-200 rounded-xl space-y-3">
                        <label class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-barcode text-teal-600"></i>
                                <span>Select Medication / Drug Batch</span>
                            </span>
                            <span class="text-[10px] text-slate-500 font-normal">Real-time Batch Stock &amp; Pricing</span>
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                            <!-- Drug Select -->
                            <div class="sm:col-span-8">
                                <select id="pos_batch_selector" class="form-input text-xs font-medium" onchange="onPosBatchSelected(this)">
                                    <option value="">-- Search &amp; Select Medicine from Formulary --</option>
                                    <?php foreach ($posDrugBatches as $b): 
                                        $isControlled = (bool)$b['is_controlled_substance'];
                                        $nearExp = $b['days_to_expiry'] <= 90;
                                    ?>
                                    <option value="<?= $b['batch_id'] ?>"
                                            data-brand="<?= htmlspecialchars($b['brand_name']) ?>"
                                            data-generic="<?= htmlspecialchars($b['generic_name']) ?>"
                                            data-dosage="<?= htmlspecialchars($b['dosage_form'] . ' ' . $b['strength']) ?>"
                                            data-batch="<?= htmlspecialchars($b['batch_number']) ?>"
                                            data-expiry="<?= date('d/m/Y', strtotime($b['expiry_date'])) ?>"
                                            data-price="<?= (float)$b['unit_sale_price'] ?>"
                                            data-stock="<?= (int)$b['current_quantity'] ?>"
                                            data-controlled="<?= $isControlled ? '1' : '0' ?>"
                                            data-schedule="<?= htmlspecialchars($b['narcotic_schedule'] ?? '') ?>">
                                        <?= htmlspecialchars($b['brand_name']) ?> (<?= htmlspecialchars($b['dosage_form']) ?> <?= htmlspecialchars($b['strength']) ?>) &bull; Batch: <?= htmlspecialchars($b['batch_number']) ?> &bull; Stock: <?= (int)$b['current_quantity'] ?> &bull; Rs. <?= number_format($b['unit_sale_price'], 2) ?> <?= $isControlled ? '⚠️ [CONTROLLED]' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Quantity Input -->
                            <div class="sm:col-span-2">
                                <input type="number" id="pos_item_qty" min="1" max="999" value="1" class="form-input text-xs text-center font-bold font-mono" placeholder="Qty" onkeydown="if(event.key==='Enter'){event.preventDefault();addSelectedToPosCart();}">
                            </div>

                            <!-- Add Button -->
                            <div class="sm:col-span-2">
                                <button type="button" onclick="addSelectedToPosCart()" class="w-full h-full min-h-[34px] bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-xs transition-colors flex items-center justify-center gap-1">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Add</span>
                                </button>
                            </div>
                        </div>

                        <!-- Dynamic Drug Context Details Bar -->
                        <div id="pos-item-preview" class="hidden text-xs bg-white p-2.5 rounded-lg border border-teal-200/80 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-800" id="preview-brand"></span>
                                <span class="text-slate-500" id="preview-generic"></span>
                                <span id="preview-controlled-badge"></span>
                            </div>
                            <div class="flex items-center gap-3 font-mono text-[11px]">
                                <span>Batch: <strong id="preview-batch" class="text-slate-700"></strong></span>
                                <span>Exp: <strong id="preview-expiry" class="text-slate-700"></strong></span>
                                <span>Stock: <strong id="preview-stock" class="text-emerald-700"></strong></span>
                                <span>Rate: <strong id="preview-price" class="text-teal-800"></strong></span>
                            </div>
                        </div>

                    </div>

                    <!-- 3. POS Cart Table -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                        <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between">
                            <span class="font-bold text-xs text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-cart-shopping text-teal-600"></i>
                                <span>POS Billing Cart</span>
                            </span>
                            <span id="pos-cart-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800">
                                0 Items
                            </span>
                        </div>

                        <div class="max-h-64 overflow-y-auto custom-scrollbar">
                            <table class="w-full text-left text-xs" id="pos-cart-table">
                                <thead class="bg-slate-50/70 text-slate-500 font-semibold uppercase text-[10px] border-b border-slate-100 sticky top-0 bg-white">
                                    <tr>
                                        <th class="px-3 py-2">#</th>
                                        <th class="px-3 py-2">Medicine / Batch</th>
                                        <th class="px-3 py-2 text-right">Unit Price</th>
                                        <th class="px-3 py-2 text-center">Qty</th>
                                        <th class="px-3 py-2 text-right">Total</th>
                                        <th class="px-3 py-2 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="pos-cart-tbody" class="divide-y divide-slate-100">
                                    <!-- Empty Row placeholder -->
                                    <tr id="pos-cart-empty">
                                        <td colspan="6" class="py-8 text-center text-slate-400">
                                            <i class="fa-solid fa-basket-shopping text-2xl mb-1.5 text-slate-300"></i>
                                            <p class="text-xs">No items in the POS cart yet.</p>
                                            <p class="text-[11px] text-slate-400">Search and add medications above to start billing.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Financial Summary & Checkout Drawer (Cols 8-12) -->
                <div class="lg:col-span-5 flex flex-col justify-between space-y-4 bg-slate-50/70 p-5 rounded-xl border border-slate-200">
                    
                    <div class="space-y-4">
                        <div class="border-b border-slate-200 pb-2">
                            <h4 class="font-bold text-xs text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-file-invoice-dollar text-teal-600"></i>
                                <span>POS Checkout &amp; Payment Summary</span>
                            </h4>
                        </div>

                        <!-- Calculations Box -->
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center text-slate-600">
                                <span>Subtotal:</span>
                                <span class="font-mono font-bold text-slate-800" id="pos-subtotal-txt">Rs. 0.00</span>
                            </div>

                            <!-- Discount Input -->
                            <div class="flex justify-between items-center gap-2">
                                <label class="text-slate-600 flex items-center gap-1">
                                    <span>Discount (PKR):</span>
                                </label>
                                <input type="number" step="0.01" min="0" name="discount_amount" id="pos_discount" value="0.00" class="form-input text-xs w-28 text-right font-mono" oninput="recalcPosTotals()">
                            </div>

                            <!-- Tax Input -->
                            <div class="flex justify-between items-center gap-2">
                                <label class="text-slate-600 flex items-center gap-1">
                                    <span>Tax / GST (PKR):</span>
                                </label>
                                <input type="number" step="0.01" min="0" name="tax_amount" id="pos_tax" value="0.00" class="form-input text-xs w-28 text-right font-mono" oninput="recalcPosTotals()">
                            </div>

                            <!-- Grand Total Box -->
                            <div class="p-3.5 bg-gradient-to-r from-teal-900 to-slate-900 text-white rounded-xl flex items-center justify-between shadow-xs">
                                <div>
                                    <div class="text-[10px] text-teal-300 font-bold uppercase tracking-wider">Net Amount Payable</div>
                                    <div class="text-xs text-slate-400">Includes all discounts &amp; taxes</div>
                                </div>
                                <div class="text-xl font-black font-mono text-emerald-400" id="pos-net-total-txt">
                                    Rs. 0.00
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method Selector -->
                        <div class="space-y-1.5">
                            <label class="form-label text-[11px]">Payment Method <span class="text-rose-500">*</span></label>
                            <select name="payment_method" id="pos_payment_method" class="form-input text-xs font-semibold" onchange="onPaymentMethodChange(this.value)">
                                <option value="Cash">Cash (Immediate Counter Payment)</option>
                                <option value="Card">Credit / Debit Card (POS Machine)</option>
                                <option value="Bank_Transfer">Bank Transfer / Online IBFT</option>
                                <option value="Mobile_Wallet">Mobile Wallet (JazzCash / EasyPaisa)</option>
                                <option value="Bill_to_Encounter" id="opt-bill-encounter" disabled>Bill to Patient Hospital Encounter</option>
                            </select>
                        </div>

                        <!-- Tendered & Change (For Cash payments) -->
                        <div id="pos-cash-details" class="space-y-2 p-3 bg-white border border-slate-200 rounded-lg">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-700">Cash Received / Tendered:</span>
                                <input type="number" step="0.01" min="0" name="amount_tendered" id="pos_amount_tendered" value="0.00" class="form-input text-xs w-28 text-right font-mono font-bold text-slate-900" oninput="calcPosChange()">
                            </div>

                            <!-- Quick Cash Buttons -->
                            <div class="flex items-center gap-1.5 pt-1">
                                <span class="text-[10px] text-slate-400 font-semibold">Quick:</span>
                                <button type="button" onclick="setExactTender()" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] rounded font-semibold transition-colors">Exact</button>
                                <button type="button" onclick="addTender(500)" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] rounded font-semibold transition-colors">500</button>
                                <button type="button" onclick="addTender(1000)" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] rounded font-semibold transition-colors">1,000</button>
                                <button type="button" onclick="addTender(5000)" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] rounded font-semibold transition-colors">5,000</button>
                            </div>

                            <!-- Change Display -->
                            <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-xs font-bold">
                                <span class="text-slate-600">Change Due:</span>
                                <span class="font-mono text-sm text-teal-800" id="pos-change-txt">Rs. 0.00</span>
                            </div>
                        </div>

                        <!-- Card / Bank Ref Input (Hidden by default) -->
                        <div id="pos-ref-field" class="hidden">
                            <label class="form-label text-[11px]">Card Slip # / Transaction Reference</label>
                            <input type="text" name="payment_reference" id="pos_payment_ref" class="form-input text-xs" placeholder="e.g. CARD-AUTH-88219 or IBFT-9912">
                        </div>

                        <!-- Sale Notes -->
                        <div>
                            <label class="form-label text-[11px]">Sale Notes / Prescriber Memo (Optional)</label>
                            <input type="text" name="sale_notes" class="form-input text-xs" placeholder="e.g. OTC cold relief, prescribed by Dr. Tariq">
                        </div>
                    </div>

                    <!-- Checkout & Print Action Buttons -->
                    <div class="pt-2 space-y-2">
                        <button type="submit" id="btn-pos-submit" disabled class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-receipt text-sm"></i>
                            <span>Process POS Sale &amp; Print Receipt</span>
                        </button>
                        <p class="text-[10px] text-center text-slate-400">
                            Stock is automatically deducted via FEFO &bull; Centralized invoice updated
                        </p>
                    </div>

                </div>

            </div>
        </form>

        <!-- POS Recent Transactions Drawer (Today's Sales) -->
        <div class="border-t border-slate-200 bg-slate-50/50 p-6">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-teal-600"></i>
                    <span>Recent POS Sales Counter Ledger</span>
                </h4>
                <a href="index.php?page=pharmacy&tab=queue" class="text-xs text-teal-700 font-semibold hover:underline">
                    View Complete Pharmacy Ledger &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <thead class="bg-slate-100/70 text-slate-600 font-semibold uppercase text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-2.5">Invoice #</th>
                            <th class="px-4 py-2.5">Date &amp; Time</th>
                            <th class="px-4 py-2.5">Customer / Patient</th>
                            <th class="px-4 py-2.5">Items Sold</th>
                            <th class="px-4 py-2.5">Payment Method</th>
                            <th class="px-4 py-2.5 text-right">Net Total</th>
                            <th class="px-4 py-2.5 text-right">Print Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($recentPosSales)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                                <i class="fa-solid fa-cash-register text-xl mb-1 text-slate-300"></i>
                                <p>No POS sales recorded today yet.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentPosSales as $sale): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-4 py-2.5 font-mono font-bold text-teal-800">
                                <?= htmlspecialchars($sale['sale_invoice_number']) ?>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">
                                <?= date('d M, h:i A', strtotime($sale['created_at'])) ?>
                            </td>
                            <td class="px-4 py-2.5 font-medium text-slate-800">
                                <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?>
                                <?php if (!empty($sale['mrn'])): ?>
                                <span class="text-[10px] font-mono text-teal-700 ml-1">(<?= htmlspecialchars($sale['mrn']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 max-w-xs truncate text-[11px] text-slate-600" title="<?= htmlspecialchars($sale['items_summary'] ?? '') ?>">
                                <span class="font-bold text-slate-800"><?= (int)$sale['total_items'] ?> item(s):</span> <?= htmlspecialchars($sale['items_summary'] ?? 'N/A') ?>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                    <?= htmlspecialchars($sale['payment_method']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900">
                                <?= format_currency($sale['net_total']) ?>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="index.php?page=print_pos_receipt&sale_id=<?= $sale['id'] ?>&mode=thermal" target="_blank" class="px-2 py-1 bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 rounded font-semibold text-[11px] flex items-center gap-1 transition-colors" title="Print 80mm POS Slip">
                                        <i class="fa-solid fa-receipt text-[10px] text-teal-600"></i>
                                        <span>Slip</span>
                                    </a>
                                    <a href="index.php?page=print_pos_receipt&sale_id=<?= $sale['id'] ?>&mode=standard" target="_blank" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded font-semibold text-[11px] flex items-center gap-1 transition-colors" title="Print A4 Invoice">
                                        <i class="fa-solid fa-file-invoice text-[10px]"></i>
                                        <span>A4</span>
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

<!-- ========================================================================= -->
<!-- JAVASCRIPT: POINT OF SALE (POS) LIVE ENGINE & STATE CONTROLLER -->
<!-- ========================================================================= -->
<script>
let posCart = [];

// 1. Live Real-time Clock for POS Terminal
function updatePosClock() {
    const el = document.getElementById('pos-live-clock');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
}
setInterval(updatePosClock, 1000);

// 2. Customer Type Switcher (Walk-in vs Registered Patient)
function toggleCustomerType(type) {
    const walkinDiv = document.getElementById('pos-walkin-fields');
    const regDiv = document.getElementById('pos-registered-fields');
    const optBill = document.getElementById('opt-bill-encounter');
    const payMethodSel = document.getElementById('pos_payment_method');
    const custNameInput = document.getElementById('pos_customer_name');
    const custPhoneInput = document.getElementById('pos_customer_phone');
    const patSelect = document.getElementById('pos_patient_id');

    if (type === 'walk_in') {
        walkinDiv.classList.remove('hidden');
        regDiv.classList.add('hidden');
        optBill.disabled = true;
        if (payMethodSel.value === 'Bill_to_Encounter') {
            payMethodSel.value = 'Cash';
            onPaymentMethodChange('Cash');
        }
        custNameInput.value = 'Walk-in Customer';
        custPhoneInput.value = '';
        patSelect.value = '';
        document.getElementById('pos-selected-pat-info').classList.add('hidden');
    } else {
        walkinDiv.classList.add('hidden');
        regDiv.classList.remove('hidden');
        optBill.disabled = false;
        custNameInput.value = '';
    }
}

// 3. Registered Patient Auto-Selection Handler
function onSelectRegisteredPatient(sel) {
    const opt = sel.options[sel.selectedIndex];
    const infoBox = document.getElementById('pos-selected-pat-info');
    const summarySpan = document.getElementById('pos-pat-summary');
    const custNameInput = document.getElementById('pos_customer_name');
    const custPhoneInput = document.getElementById('pos_customer_phone');

    if (opt && opt.value) {
        const name = opt.dataset.name || '';
        const phone = opt.dataset.phone || '';
        const mrn = opt.dataset.mrn || '';
        const gender = opt.dataset.gender || '';
        const age = opt.dataset.age || '';

        custNameInput.value = name;
        custPhoneInput.value = phone;

        summarySpan.innerHTML = `<strong>${name}</strong> &bull; MRN: <span class="font-mono font-bold">${mrn}</span> &bull; ${gender}, ${age} yrs &bull; Phone: ${phone || 'N/A'}`;
        infoBox.classList.remove('hidden');
    } else {
        infoBox.classList.add('hidden');
        custNameInput.value = '';
        custPhoneInput.value = '';
    }
}

// 4. Batch Selector Preview Handler
function onPosBatchSelected(sel) {
    const opt = sel.options[sel.selectedIndex];
    const previewBox = document.getElementById('pos-item-preview');
    const qtyInput = document.getElementById('pos_item_qty');

    if (opt && opt.value) {
        document.getElementById('preview-brand').textContent = opt.dataset.brand + ' (' + opt.dataset.dosage + ')';
        document.getElementById('preview-generic').textContent = '• ' + opt.dataset.generic;
        document.getElementById('preview-batch').textContent = opt.dataset.batch;
        document.getElementById('preview-expiry').textContent = opt.dataset.expiry;
        document.getElementById('preview-stock').textContent = opt.dataset.stock + ' units';
        document.getElementById('preview-price').textContent = 'Rs. ' + parseFloat(opt.dataset.price).toFixed(2);

        const ctrlBadge = document.getElementById('preview-controlled-badge');
        if (opt.dataset.controlled === '1') {
            ctrlBadge.innerHTML = `<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">⚠️ Controlled (${opt.dataset.schedule})</span>`;
        } else {
            ctrlBadge.innerHTML = '';
        }

        qtyInput.max = opt.dataset.stock;
        qtyInput.value = 1;
        previewBox.classList.remove('hidden');
        qtyInput.focus();
        qtyInput.select();
    } else {
        previewBox.classList.add('hidden');
    }
}

// 5. Add Item to POS Cart
function addSelectedToPosCart() {
    const sel = document.getElementById('pos_batch_selector');
    const qtyInput = document.getElementById('pos_item_qty');
    const opt = sel.options[sel.selectedIndex];

    if (!opt || !opt.value) {
        alert('Please select a medication from the formulary.');
        sel.focus();
        return;
    }

    const batchId = parseInt(opt.value);
    const qty = parseInt(qtyInput.value) || 0;
    const stock = parseInt(opt.dataset.stock) || 0;
    const price = parseFloat(opt.dataset.price) || 0;

    if (qty <= 0) {
        alert('Quantity must be at least 1.');
        qtyInput.focus();
        return;
    }

    if (qty > stock) {
        alert(`Requested quantity (${qty}) exceeds available stock (${stock}).`);
        qtyInput.value = stock;
        return;
    }

    // Check if batch already exists in cart
    const existingIndex = posCart.findIndex(item => item.batchId === batchId);
    if (existingIndex !== -1) {
        const newQty = posCart[existingIndex].qty + qty;
        if (newQty > stock) {
            alert(`Cannot add more. Total in cart (${newQty}) exceeds stock (${stock}).`);
            return;
        }
        posCart[existingIndex].qty = newQty;
    } else {
        posCart.push({
            batchId: batchId,
            brand: opt.dataset.brand,
            generic: opt.dataset.generic,
            dosage: opt.dataset.dosage,
            batch: opt.dataset.batch,
            expiry: opt.dataset.expiry,
            price: price,
            stock: stock,
            qty: qty
        });
    }

    renderPosCart();

    // Reset selector
    sel.value = '';
    qtyInput.value = 1;
    document.getElementById('pos-item-preview').classList.add('hidden');
    sel.focus();
}

// 6. Render Cart Table
function renderPosCart() {
    const tbody = document.getElementById('pos-cart-tbody');
    const emptyRow = document.getElementById('pos-cart-empty');
    const badge = document.getElementById('pos-cart-badge');
    const submitBtn = document.getElementById('btn-pos-submit');

    // Remove old item rows
    const rows = tbody.querySelectorAll('.pos-cart-item-row');
    rows.forEach(r => r.remove());

    if (posCart.length === 0) {
        if (emptyRow) emptyRow.style.display = '';
        badge.textContent = '0 Items';
        badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600';
        submitBtn.disabled = true;
    } else {
        if (emptyRow) emptyRow.style.display = 'none';
        
        let totalUnits = 0;

        posCart.forEach((item, idx) => {
            totalUnits += item.qty;
            const lineTotal = (item.qty * item.price).toFixed(2);

            const tr = document.createElement('tr');
            tr.className = 'pos-cart-item-row hover:bg-slate-50/80 transition-colors';
            tr.innerHTML = `
                <td class="px-3 py-2 font-mono text-slate-400 text-[11px] align-middle">${idx + 1}</td>
                <td class="px-3 py-2 align-middle">
                    <div class="font-bold text-slate-900">${escapeHtml(item.brand)}</div>
                    <div class="text-[10px] text-slate-500">${escapeHtml(item.dosage)} &bull; <span class="font-mono text-slate-600">B:${escapeHtml(item.batch)}</span> &bull; <span class="text-slate-400">Exp:${escapeHtml(item.expiry)}</span></div>
                    <input type="hidden" name="item_batch_id[]" value="${item.batchId}">
                    <input type="hidden" name="item_unit_price[]" value="${item.price}">
                </td>
                <td class="px-3 py-2 text-right font-mono font-semibold text-slate-700 align-middle">
                    Rs. ${item.price.toFixed(2)}
                </td>
                <td class="px-3 py-2 text-center align-middle">
                    <div class="inline-flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden shadow-2xs">
                        <button type="button" onclick="adjustCartQty(${item.batchId}, -1)" class="px-2 py-0.5 text-slate-600 hover:bg-slate-100 font-bold text-xs">-</button>
                        <input type="number" name="item_quantity[]" value="${item.qty}" min="1" max="${item.stock}" onchange="setCartQty(${item.batchId}, this.value)" class="w-12 text-center text-xs font-bold font-mono border-x border-slate-200 py-0.5 outline-none">
                        <button type="button" onclick="adjustCartQty(${item.batchId}, 1)" class="px-2 py-0.5 text-slate-600 hover:bg-slate-100 font-bold text-xs">+</button>
                    </div>
                </td>
                <td class="px-3 py-2 text-right font-mono font-bold text-slate-900 align-middle">
                    Rs. ${lineTotal}
                </td>
                <td class="px-3 py-2 text-center align-middle">
                    <button type="button" onclick="removePosCartItem(${item.batchId})" class="p-1 text-rose-500 hover:bg-rose-50 rounded transition-colors" title="Remove item">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        badge.textContent = `${totalUnits} Items (${posCart.length} SKU)`;
        badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800';
        submitBtn.disabled = false;
    }

    recalcPosTotals();
}

// 7. Adjust Cart Item Quantity
function adjustCartQty(batchId, delta) {
    const item = posCart.find(i => i.batchId === batchId);
    if (item) {
        const newQty = item.qty + delta;
        if (newQty <= 0) {
            removePosCartItem(batchId);
            return;
        }
        if (newQty > item.stock) {
            alert(`Cannot exceed available stock (${item.stock}).`);
            return;
        }
        item.qty = newQty;
        renderPosCart();
    }
}

function setCartQty(batchId, val) {
    const item = posCart.find(i => i.batchId === batchId);
    if (item) {
        let q = parseInt(val) || 1;
        if (q <= 0) q = 1;
        if (q > item.stock) {
            alert(`Quantity capped at available stock (${item.stock}).`);
            q = item.stock;
        }
        item.qty = q;
        renderPosCart();
    }
}

// 8. Remove Item from Cart
function removePosCartItem(batchId) {
    posCart = posCart.filter(i => i.batchId !== batchId);
    renderPosCart();
}

// 9. Recalculate POS Financial Totals
function recalcPosTotals() {
    let subtotal = 0;
    posCart.forEach(i => {
        subtotal += (i.qty * i.price);
    });

    const discInput = document.getElementById('pos_discount');
    const taxInput = document.getElementById('pos_tax');

    const discount = parseFloat(discInput.value) || 0;
    const tax = parseFloat(taxInput.value) || 0;

    const netTotal = Math.max(0, subtotal - discount + tax);

    document.getElementById('pos-subtotal-txt').textContent = 'Rs. ' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('pos-net-total-txt').textContent = 'Rs. ' + netTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    calcPosChange();
}

// 10. Payment Method Switcher
function onPaymentMethodChange(method) {
    const refField = document.getElementById('pos-ref-field');
    const cashDiv = document.getElementById('pos-cash-details');

    if (method === 'Cash') {
        cashDiv.classList.remove('hidden');
        refField.classList.add('hidden');
    } else if (method === 'Card' || method === 'Bank_Transfer' || method === 'Mobile_Wallet') {
        cashDiv.classList.remove('hidden');
        refField.classList.remove('hidden');
    } else if (method === 'Bill_to_Encounter') {
        cashDiv.classList.add('hidden');
        refField.classList.add('hidden');
    }
    calcPosChange();
}

// 11. Calculate Change Returned
function calcPosChange() {
    let subtotal = 0;
    posCart.forEach(i => subtotal += (i.qty * i.price));
    const discount = parseFloat(document.getElementById('pos_discount').value) || 0;
    const tax = parseFloat(document.getElementById('pos_tax').value) || 0;
    const netTotal = Math.max(0, subtotal - discount + tax);

    const tenderedInput = document.getElementById('pos_amount_tendered');
    const tendered = parseFloat(tenderedInput.value) || 0;
    const changeBox = document.getElementById('pos-change-txt');

    const change = tendered - netTotal;

    if (tendered === 0 && netTotal === 0) {
        changeBox.textContent = 'Rs. 0.00';
        changeBox.className = 'font-mono text-sm text-slate-500';
    } else if (change >= 0) {
        changeBox.textContent = 'Rs. ' + change.toFixed(2);
        changeBox.className = 'font-mono text-sm text-emerald-700 font-bold';
    } else {
        const remaining = Math.abs(change);
        changeBox.textContent = `Rs. 0.00 (Rs. ${remaining.toFixed(2)} due)`;
        changeBox.className = 'font-mono text-xs text-rose-600 font-bold';
    }
}

// 12. Quick Tender Convenience Buttons
function setExactTender() {
    let subtotal = 0;
    posCart.forEach(i => subtotal += (i.qty * i.price));
    const discount = parseFloat(document.getElementById('pos_discount').value) || 0;
    const tax = parseFloat(document.getElementById('pos_tax').value) || 0;
    const netTotal = Math.max(0, subtotal - discount + tax);

    document.getElementById('pos_amount_tendered').value = netTotal.toFixed(2);
    calcPosChange();
}

function addTender(amount) {
    const cur = parseFloat(document.getElementById('pos_amount_tendered').value) || 0;
    document.getElementById('pos_amount_tendered').value = (cur + amount).toFixed(2);
    calcPosChange();
}

// 13. Reset Entire POS Form
function resetPosCart() {
    if (posCart.length > 0 && !confirm('Are you sure you want to reset and clear the current POS cart?')) {
        return;
    }
    posCart = [];
    document.getElementById('pos_discount').value = '0.00';
    document.getElementById('pos_tax').value = '0.00';
    document.getElementById('pos_amount_tendered').value = '0.00';
    document.getElementById('pos_batch_selector').value = '';
    document.getElementById('pos-item-preview').classList.add('hidden');
    renderPosCart();
}

// 14. Validation Before Checkout
function validatePosCheckout(e) {
    if (posCart.length === 0) {
        alert('POS Cart is empty. Please add at least one medication to proceed.');
        e.preventDefault();
        return false;
    }

    const custType = document.querySelector('input[name="customer_type"]:checked').value;
    if (custType === 'registered') {
        const patId = document.getElementById('pos_patient_id').value;
        if (!patId) {
            alert('Please select a registered patient or switch to Walk-in mode.');
            e.preventDefault();
            return false;
        }
    }

    const payMethod = document.getElementById('pos_payment_method').value;
    if (payMethod === 'Cash') {
        let subtotal = 0;
        posCart.forEach(i => subtotal += (i.qty * i.price));
        const discount = parseFloat(document.getElementById('pos_discount').value) || 0;
        const tax = parseFloat(document.getElementById('pos_tax').value) || 0;
        const netTotal = Math.max(0, subtotal - discount + tax);
        const tendered = parseFloat(document.getElementById('pos_amount_tendered').value) || 0;

        if (tendered < netTotal && tendered > 0) {
            if (!confirm(`Cash tendered (Rs. ${tendered.toFixed(2)}) is less than Net Payable (Rs. ${netTotal.toFixed(2)}). Proceed anyway?`)) {
                e.preventDefault();
                return false;
            }
        }
    }

    return true;
}

// Helper: Escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Keyboard shortcuts: F9 completes checkout
window.addEventListener('keydown', function(e) {
    if (e.key === 'F9') {
        const submitBtn = document.getElementById('btn-pos-submit');
        if (submitBtn && !submitBtn.disabled) {
            e.preventDefault();
            document.getElementById('pos-sale-form').requestSubmit();
        }
    }
});
</script>
