<?php
/**
 * Hospital Management System (HMS) - Executive & Role Dashboard
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$hId = Tenant::id();

// 1. Fetch Metrics for active hospital scope
$totalPatients = $db->query("SELECT COUNT(*) FROM `patients` WHERE `status` = 'Active' AND hospital_id = {$hId}")->fetchColumn();
$todayEncounters = $db->query("SELECT COUNT(*) FROM `encounters` WHERE DATE(`admission_datetime`) = CURDATE() AND hospital_id = {$hId}")->fetchColumn();
$totalBeds = $db->query("SELECT COUNT(*) FROM `beds` b JOIN `rooms` r ON b.room_id = r.id JOIN `wards` w ON r.ward_id = w.id WHERE w.hospital_id = {$hId}")->fetchColumn();
$occupiedBeds = $db->query("SELECT COUNT(*) FROM `beds` b JOIN `rooms` r ON b.room_id = r.id JOIN `wards` w ON r.ward_id = w.id WHERE b.`status` = 'Occupied' AND w.hospital_id = {$hId}")->fetchColumn();
$bedOccupancyPercent = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100) : 0;

$todayRevenue = $db->query("SELECT COALESCE(SUM(`amount_paid`), 0) FROM `payments` WHERE DATE(`payment_datetime`) = CURDATE() AND hospital_id = {$hId}")->fetchColumn();
$pendingLabs = $db->query("SELECT COUNT(*) FROM `lab_order_items` WHERE `item_status` IN ('Pending', 'Collected') AND hospital_id = {$hId}")->fetchColumn();
$pendingPrescriptions = $db->query("SELECT COUNT(*) FROM `prescriptions` WHERE `status` = 'Pending' AND hospital_id = {$hId}")->fetchColumn();

// 2. Fetch Today's Appointments & OPD Queue
$todayQueue = $db->query("
    SELECT e.*, p.mrn, p.first_name, p.last_name, p.gender, p.age, p.blood_group,
           u.full_name AS doctor_name, d.department_name
    FROM `encounters` e
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` u ON e.attending_doctor_id = u.id
    JOIN `departments` d ON e.department_id = d.id
    WHERE e.encounter_status = 'In-Progress' AND e.hospital_id = {$hId}
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
    WHERE w.hospital_id = {$hId}
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
        (SELECT COUNT(*) FROM `prescription_items` WHERE `prescription_id` = pr.id) AS total_items,
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
    WHERE pr.hospital_id = {$hId}
    ORDER BY pr.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Today's Appointments & Reception OPD Token Queue
$todayAppointments = $db->query("
    SELECT 
        a.id AS apt_id,
        a.appointment_number,
        a.token_number,
        a.appointment_date,
        a.slot_time,
        a.status,
        a.type,
        a.reason_for_visit,
        p.id AS patient_id,
        p.mrn,
        p.first_name,
        p.last_name,
        p.phone_primary,
        p.gender,
        p.age,
        u.full_name AS doctor_name,
        d.department_name
    FROM `appointments` a
    JOIN `patients` p ON a.patient_id = p.id
    LEFT JOIN `users` u ON a.doctor_id = u.id
    LEFT JOIN `departments` d ON a.department_id = d.id
    WHERE a.hospital_id = {$hId} AND a.appointment_date = CURDATE()
    ORDER BY a.token_number ASC, a.slot_time ASC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$totalTokensToday = count($todayAppointments);
$waitingTokensToday = count(array_filter($todayAppointments, fn($a) => $a['status'] === 'Scheduled'));
$checkedInTokensToday = count(array_filter($todayAppointments, fn($a) => $a['status'] === 'Checked-In'));
$completedTokensToday = count(array_filter($todayAppointments, fn($a) => $a['status'] === 'Completed'));
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
                <a href="#reception-tokens" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center gap-2 border border-indigo-400/30">
                    <i class="fa-solid fa-ticket"></i>
                    <span>OPD Token Queue</span>
                </a>
                <a href="index.php?page=patient_create" class="px-3.5 py-2 bg-medical-600 hover:bg-medical-500 text-white text-xs font-semibold rounded-lg shadow-sm transition-all flex items-center gap-2">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Register Patient</span>
                </a>
                <a href="index.php?page=appointments" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-semibold rounded-lg border border-white/20 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>New Appointment</span>
                </a>
                <a href="index.php?page=pharmacy&tab=dispensary" class="px-3.5 py-2 bg-emerald-600/90 hover:bg-emerald-600 text-white text-xs font-semibold rounded-lg shadow-sm transition-all flex items-center gap-2 border border-emerald-400/30">
                    <i class="fa-solid fa-pills"></i>
                    <span>Pharmacy POS</span>
                </a>
            </div>
        </div>
        <!-- Decorative Background Element -->
        <i class="fa-solid fa-staff-snake absolute right-4 -bottom-6 text-white/5 text-9xl pointer-events-none"></i>
    </div>

    <!-- Hospital Owner Setup & Onboarding Wizard (Requirement 18.E) -->
    <?php if (Auth::isHospitalOwner() && !isset($_COOKIE['dismiss_setup_wizard_' . $hId])): ?>
    <?php
    $hInfo = hospital_profile();
    $staffCount = (int)$db->query("SELECT COUNT(*) FROM `users` WHERE hospital_id = {$hId}")->fetchColumn();
    $deptCount = (int)$db->query("SELECT COUNT(*) FROM `departments` WHERE hospital_id = {$hId}")->fetchColumn();
    $hasGeofence = !empty($hInfo['hospital_latitude']) && !empty($hInfo['hospital_longitude']);
    
    $completedSteps = 0;
    if (!empty($hInfo['hospital_name'])) $completedSteps++;
    if ($deptCount >= 3) $completedSteps++;
    if ($staffCount >= 2) $completedSteps++;
    if ($hasGeofence) $completedSteps++;
    if (!empty($hInfo['mrn_prefix'])) $completedSteps++;
    $progressPct = round(($completedSteps / 5) * 100);
    ?>
    <div class="bg-gradient-to-r from-teal-950 via-slate-900 to-indigo-950 rounded-2xl p-6 border border-teal-800/60 shadow-xl text-white space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-teal-500/20 border border-teal-500/40 text-teal-400 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-500/20 text-teal-300 border border-teal-500/30 uppercase">Hospital Setup Wizard</span>
                        <span class="text-xs text-slate-400 font-mono"><?= $progressPct ?>% Ready</span>
                    </div>
                    <h3 class="text-lg font-bold text-white mt-0.5">Welcome to your new Hospital Workspace</h3>
                    <p class="text-xs text-slate-300">Complete these initial steps to fully configure your facility's clinical departments, staff, and attendance.</p>
                </div>
            </div>
            <button onclick="document.cookie='dismiss_setup_wizard_<?= $hId ?>=1;path=/;max-age=86400';this.closest('.bg-gradient-to-r').remove()" class="text-slate-400 hover:text-white text-xs px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 transition-all self-start sm:self-auto">
                Dismiss for now
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2 text-xs">
            <a href="index.php?page=hospital_customization" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/70 transition-all flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check <?= !empty($hInfo['hospital_name']) ? 'text-emerald-400' : 'text-slate-500' ?> text-base"></i>
                <div>
                    <div class="font-bold text-white">1. Hospital Profile</div>
                    <div class="text-[10px] text-slate-400">Name, logo, currency</div>
                </div>
            </a>
            <a href="index.php?page=settings" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/70 transition-all flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check <?= $deptCount >= 3 ? 'text-emerald-400' : 'text-slate-500' ?> text-base"></i>
                <div>
                    <div class="font-bold text-white">2. Departments</div>
                    <div class="text-[10px] text-slate-400"><?= $deptCount ?> Configured</div>
                </div>
            </a>
            <a href="index.php?page=users" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/70 transition-all flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check <?= $staffCount >= 2 ? 'text-emerald-400' : 'text-slate-500' ?> text-base"></i>
                <div>
                    <div class="font-bold text-white">3. Staff Accounts</div>
                    <div class="text-[10px] text-slate-400"><?= $staffCount ?> Accounts Active</div>
                </div>
            </a>
            <a href="index.php?page=attendance" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/70 transition-all flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check <?= $hasGeofence ? 'text-emerald-400' : 'text-slate-500' ?> text-base"></i>
                <div>
                    <div class="font-bold text-white">4. GPS Geofence</div>
                    <div class="text-[10px] text-slate-400">Perimeter radius & shifts</div>
                </div>
            </a>
            <a href="index.php?page=hospital_customization" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/70 transition-all flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check <?= !empty($hInfo['mrn_prefix']) ? 'text-emerald-400' : 'text-slate-500' ?> text-base"></i>
                <div>
                    <div class="font-bold text-white">5. MRN & Billing</div>
                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($hInfo['mrn_prefix'] ?? 'MRN') ?> Prefix</div>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

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

    <!-- ========================================================================= -->
    <!-- CENTRAL RECEPTION & OPD TOKEN / APPOINTMENT QUEUE BOARD -->
    <!-- ========================================================================= -->
    <div id="reception-tokens" class="bg-white rounded-2xl border-2 border-indigo-500/20 shadow-xl overflow-hidden scroll-mt-6 transition-all duration-300">
        
        <!-- Reception Header Banner -->
        <div class="bg-gradient-to-r from-indigo-900 via-slate-900 to-medical-900 text-white p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-400/20 text-indigo-300 border border-indigo-400/30 uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping inline-block"></span>
                        CENTRAL RECEPTION & OPD TOKEN DESK
                    </span>
                    <span class="text-xs text-indigo-200/80 font-mono hidden sm:inline">&bull; Main Lobby Counter &bull; Today: <?= date('d M Y') ?></span>
                </div>
                <h3 class="text-xl sm:text-2xl font-black tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-ticket text-indigo-400"></i>
                    <span>OPD Consultation Token Board & Patient Routing</span>
                </h3>
                <p class="text-xs text-slate-300 mt-1 max-w-2xl">
                    Every token generated prints a patient identification slip with a scannable QR code and patient ID, allowing OPD, Lab, Pharmacy, and Billing to instantly retrieve the patient record.
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <a href="index.php?page=patient_create" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-user-plus text-[11px]"></i>
                    <span>Register Patient</span>
                </a>
                <a href="index.php?page=appointments" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-semibold border border-white/20 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-calendar-plus text-[11px]"></i>
                    <span>New Appointment / Token</span>
                </a>
                <a href="index.php?page=pharmacy&tab=dispensary" title="Out-Patient Medicine Dispensary and POS are exclusively located in Pharmacy" class="px-3.5 py-2 bg-emerald-600/30 hover:bg-emerald-600/40 text-emerald-200 border border-emerald-400/40 rounded-xl text-xs font-medium transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-pills text-emerald-300"></i>
                    <span>Pharmacy POS &rarr;</span>
                </a>
            </div>
        </div>

        <!-- Token Summary Counters -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-4 bg-slate-50/80 border-b border-slate-200">
            <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Today's Tokens</span>
                    <span class="text-2xl font-black text-slate-900"><?= $totalTokensToday ?></span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-ticket"></i>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-amber-600 uppercase tracking-wider block">Waiting / Scheduled</span>
                    <span class="text-2xl font-black text-amber-700"><?= $waitingTokensToday ?></span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-sky-600 uppercase tracking-wider block">Checked-In / Consulting</span>
                    <span class="text-2xl font-black text-sky-700"><?= $checkedInTokensToday ?></span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block">Completed</span>
                    <span class="text-2xl font-black text-emerald-700"><?= $completedTokensToday ?></span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
        </div>

        <!-- Quick Patient Lookup & Scan Bar -->
        <div class="p-4 bg-white border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="relative flex-1 max-w-lg">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
                <input type="text" id="token-filter-input" placeholder="Quick filter queue by patient name, MRN, ID, token number, or doctor..." class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl outline-none focus:border-indigo-500 focus:bg-white transition-all font-mono">
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-[11px] font-medium">
                    <i class="fa-solid fa-info-circle text-amber-600"></i>
                    <span>Notice: Medicine POS is located strictly in Pharmacy.</span>
                </span>
            </div>
        </div>

        <!-- Today's Live Token Queue Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs" id="reception-token-table">
                <thead class="bg-slate-50 text-slate-600 uppercase tracking-wider text-[10px] font-semibold border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3 text-center w-20">Token #</th>
                        <th class="px-5 py-3">Patient Record Key</th>
                        <th class="px-5 py-3">Patient Demographics</th>
                        <th class="px-5 py-3">Doctor & Clinic</th>
                        <th class="px-5 py-3">Slot Time & Type</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Routing & Print</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($todayAppointments)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-ticket text-4xl mb-3 text-slate-300 block"></i>
                            <p class="font-bold text-slate-600 text-sm">No OPD tokens issued for today yet.</p>
                            <p class="text-xs text-slate-400 mt-1">Register a patient or book an appointment to generate tokens with scannable QR codes.</p>
                            <div class="mt-4 flex items-center justify-center gap-2">
                                <a href="index.php?page=patient_create" class="px-3.5 py-1.5 bg-indigo-600 text-white rounded-lg font-bold text-xs hover:bg-indigo-700">
                                    <i class="fa-solid fa-user-plus mr-1"></i> Register Patient
                                </a>
                                <a href="index.php?page=appointments" class="px-3.5 py-1.5 bg-slate-100 text-slate-700 rounded-lg font-semibold text-xs hover:bg-slate-200">
                                    <i class="fa-solid fa-calendar-plus mr-1"></i> Book Appointment
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($todayAppointments as $apt): 
                        $status = $apt['status'];
                        if ($status === 'Checked-In') {
                            $statusBadge = 'bg-sky-100 text-sky-800 border-sky-200';
                        } elseif ($status === 'Completed') {
                            $statusBadge = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                        } else {
                            $statusBadge = 'bg-amber-100 text-amber-800 border-amber-200';
                        }
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors token-row" data-search="<?= strtolower(htmlspecialchars($apt['mrn'] . ' ' . $apt['first_name'] . ' ' . $apt['last_name'] . ' ' . $apt['doctor_name'] . ' ' . $apt['token_number'] . ' ' . $apt['patient_id'])) ?>">
                        <!-- Token Badge -->
                        <td class="px-5 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-600 text-white font-black text-base shadow-sm tracking-tight">
                                #<?= str_pad((string)$apt['token_number'], 2, '0', STR_PAD_LEFT) ?>
                            </span>
                        </td>

                        <!-- Patient Record Key -->
                        <td class="px-5 py-3">
                            <div class="font-mono font-bold text-indigo-700 text-xs">
                                <?= htmlspecialchars($apt['mrn']) ?>
                            </div>
                            <div class="text-[11px] font-mono text-slate-500 font-semibold mt-0.5">
                                System ID: <strong class="text-slate-800">#<?= $apt['patient_id'] ?></strong>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">
                                Appt: <?= htmlspecialchars($apt['appointment_number']) ?>
                            </div>
                        </td>

                        <!-- Patient Demographics -->
                        <td class="px-5 py-3">
                            <a href="index.php?page=patient_profile&id=<?= $apt['patient_id'] ?>" class="font-bold text-slate-900 hover:text-indigo-600 text-xs flex items-center gap-1.5">
                                <span><?= htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) ?></span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-slate-400"></i>
                            </a>
                            <div class="text-[11px] text-slate-500 mt-0.5">
                                <?= htmlspecialchars($apt['gender']) ?>, <?= $apt['age'] ?> yrs
                                <?php if (!empty($apt['phone_primary'])): ?>
                                &bull; <span class="font-mono"><?= htmlspecialchars($apt['phone_primary']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Doctor & Clinic -->
                        <td class="px-5 py-3">
                            <div class="font-semibold text-slate-800 text-xs">
                                Dr. <?= htmlspecialchars($apt['doctor_name'] ?? 'Assigned Medical Officer') ?>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                <?= htmlspecialchars($apt['department_name'] ?? 'General OPD') ?>
                            </div>
                        </td>

                        <!-- Slot & Type -->
                        <td class="px-5 py-3 font-mono">
                            <div class="text-slate-700 font-semibold text-xs">
                                <?= date('h:i A', strtotime($apt['slot_time'])) ?>
                            </div>
                            <div class="text-[10px] text-slate-400">
                                <?= htmlspecialchars($apt['type'] ?? 'Consultation') ?>
                            </div>
                        </td>

                        <!-- Status -->
                        <td class="px-5 py-3 text-center">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $statusBadge ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                        </td>

                        <!-- Actions / Dedicated Printable Token Slip -->
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="index.php?page=print_token&apt_id=<?= $apt['apt_id'] ?>&autoprint=1" target="_blank" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition-colors flex items-center gap-1.5" title="Print Dedicated Token Receipt Slip with QR Code">
                                    <i class="fa-solid fa-qrcode text-[11px]"></i>
                                    <span>Print Token (QR)</span>
                                </a>

                                <?php if ($status !== 'Completed' && $status !== 'Checked-In'): ?>
                                <a href="index.php?action=appointment_checkin&id=<?= $apt['apt_id'] ?>" class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-semibold transition-colors" title="Check In for Consultation">
                                    Check In
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

        <!-- Footer Help Note -->
        <div class="p-3 bg-slate-50 border-t border-slate-200 text-center text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>
                <i class="fa-solid fa-lightbulb text-amber-500 mr-1"></i>
                Token slips include both 80mm thermal receipt and A5 standard slip options with embedded patient QR code.
            </span>
            <div class="flex items-center gap-3">
                <a href="index.php?page=appointments" class="text-indigo-600 hover:underline font-semibold">Full Appointment Calendar &rarr;</a>
                <span class="text-slate-300">|</span>
                <a href="index.php?page=pharmacy&tab=dispensary" class="text-emerald-700 hover:underline font-semibold">Pharmacy POS Counter &rarr;</a>
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

<script>
// Filter token rows in live reception board
document.getElementById('token-filter-input')?.addEventListener('input', function(e) {
    const val = e.target.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#reception-token-table tbody .token-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        r.style.display = (val === '' || text.includes(val)) ? '' : 'none';
    });
});
</script>

