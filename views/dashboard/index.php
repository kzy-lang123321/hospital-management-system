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

// 8. Pending OPD Prescriptions for 1-Click Fast Loading into POS
$pendingPrescriptionsList = $db->query("
    SELECT 
        pr.id AS prescription_id,
        pr.prescription_number,
        pr.encounter_id,
        pr.created_at,
        p.id AS patient_id,
        p.mrn,
        p.first_name,
        p.last_name,
        p.phone_primary,
        p.gender,
        p.age,
        doc.full_name AS doctor_name,
        (SELECT COUNT(*) FROM `prescription_items` pi WHERE pi.prescription_id = pr.id) AS item_count
    FROM `prescriptions` pr
    JOIN `encounters` e ON pr.encounter_id = e.id
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` doc ON pr.doctor_id = doc.id
    WHERE pr.status = 'Pending'
    ORDER BY pr.id DESC
    LIMIT 25
")->fetchAll(PDO::FETCH_ASSOC);

$pendingRxItemsMap = [];
if (!empty($pendingPrescriptionsList)) {
    $rxIds = array_column($pendingPrescriptionsList, 'prescription_id');
    $inClause = implode(',', array_map('intval', $rxIds));
    $itemsRaw = $db->query("
        SELECT 
            pi.prescription_id,
            pi.drug_item_id,
            pi.dosage,
            pi.frequency,
            pi.instructions,
            pi.quantity_prescribed,
            di.brand_name,
            di.generic_name,
            di.dosage_form,
            di.strength,
            di.is_controlled_substance,
            di.narcotic_schedule,
            (SELECT db.id FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_batch_id,
            (SELECT db.batch_number FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_batch_number,
            (SELECT db.expiry_date FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_expiry_date,
            (SELECT db.unit_sale_price FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_price,
            (SELECT db.current_quantity FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_stock
        FROM `prescription_items` pi
        JOIN `drug_items` di ON pi.drug_item_id = di.id
        WHERE pi.prescription_id IN ($inClause)
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($itemsRaw as $row) {
        $pendingRxItemsMap[$row['prescription_id']][] = $row;
    }
}

// 9. Quick-Tap Fast-Moving Essential OTC & Routine Medicines for Local Patients
$quickMeds = [];
$popularKeywords = ['Panadol', 'Augmentin', 'Risek', 'Brufen', 'Loprin', 'Glucophage', 'Norvasc', 'Ventolin', 'Saline'];
foreach ($popularKeywords as $kw) {
    foreach ($posDrugBatches as $b) {
        if (stripos($b['brand_name'], $kw) !== false) {
            $quickMeds[] = $b;
            break;
        }
    }
}
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

    <!-- ========================================================================= -->
    <!-- FLAGSHIP DASHBOARD POINT OF SALE (POS) & OUTPATIENT DISPENSARY CENTERPIECE -->
    <!-- ========================================================================= -->
    <div id="dashboard-pos" class="bg-white rounded-2xl border-2 border-emerald-500/30 shadow-xl overflow-hidden scroll-mt-6 transition-all duration-300">
        
        <!-- POS Terminal Header Banner -->
        <div class="bg-gradient-to-r from-emerald-800 via-teal-800 to-navy-900 text-white p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-400/20 text-emerald-300 border border-emerald-400/30 uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping inline-block"></span>
                        LIVE DISPENSARY TERMINAL
                    </span>
                    <span class="text-xs text-emerald-200/80 font-mono hidden sm:inline">&bull; Terminal #01 (Main Lobby)</span>
                </div>
                <h3 class="text-xl sm:text-2xl font-black tracking-tight flex items-center gap-2.5">
                    <i class="fa-solid fa-cash-register text-emerald-400"></i>
                    <span>Point of Sale (POS) &amp; Outpatient Dispensary</span>
                </h3>
                <p class="text-xs sm:text-sm text-emerald-100/80 mt-1 max-w-2xl">
                    Fast-track counter checkout for walk-in patients and OPD prescriptions &bull; Real-time FEFO stock deductions &bull; Bilingual dosage directions (طریقہ استعمال) &bull; 80mm thermal &amp; A4 invoicing
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 self-start md:self-center">
                <div class="bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-right">
                    <div class="text-[10px] text-emerald-200 uppercase font-semibold">Active Cashier</div>
                    <div class="text-xs font-bold text-white"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                </div>
                <div class="bg-black/30 border border-white/10 rounded-xl px-3.5 py-2 text-center">
                    <div class="text-[10px] text-emerald-200 uppercase font-semibold">Live Clock</div>
                    <div id="pos-live-clock" class="font-mono font-black text-sm text-emerald-300">--:--:--</div>
                </div>
            </div>
        </div>

        <!-- Fast-Moving Essentials Quick-Tap Bar (Popular OTC Routine Medications) -->
        <?php if (!empty($quickMeds)): ?>
        <div class="bg-emerald-50/80 border-b border-emerald-100 px-5 py-3 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-2 flex-shrink-0">
                <span class="w-6 h-6 rounded-md bg-amber-500 text-white flex items-center justify-center text-xs shadow-2xs">
                    <i class="fa-solid fa-bolt"></i>
                </span>
                <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Quick-Tap OTC Essentials:</span>
            </div>
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <?php foreach ($quickMeds as $qm): ?>
                <button type="button" 
                        onclick="addQuickMed(<?= (int)$qm['batch_id'] ?>, '<?= htmlspecialchars(addslashes($qm['brand_name'])) ?>', <?= (float)$qm['unit_sale_price'] ?>, '<?= htmlspecialchars(addslashes($qm['dosage_form'] . ' ' . $qm['strength'])) ?>', '<?= htmlspecialchars(addslashes($qm['batch_number'])) ?>', '<?= htmlspecialchars(addslashes($qm['expiry_date'])) ?>', <?= (int)$qm['current_quantity'] ?>)"
                        class="px-2.5 py-1 bg-white hover:bg-emerald-600 hover:text-white text-slate-700 border border-emerald-200 hover:border-emerald-600 rounded-lg text-xs font-semibold shadow-2xs transition-all flex items-center gap-1.5 group"
                        title="Click to instantly add 1 unit of <?= htmlspecialchars($qm['brand_name']) ?> to cart">
                    <span>💊 <?= htmlspecialchars($qm['brand_name']) ?></span>
                    <span class="text-[10px] text-slate-400 group-hover:text-emerald-100 font-normal"><?= htmlspecialchars($qm['strength']) ?></span>
                    <span class="text-[10px] font-mono font-bold text-emerald-700 group-hover:text-white bg-emerald-100 group-hover:bg-emerald-700/50 px-1.5 py-0.5 rounded">Rs. <?= number_format($qm['unit_sale_price'], 2) ?></span>
                    <span class="text-[9px] text-slate-400 group-hover:text-emerald-200 font-mono">(<?= $qm['current_quantity'] ?>)</span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Doctor OPD Prescription Fast Loader -->
        <div class="bg-slate-50 border-b border-slate-200 px-5 py-3 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-2 flex-shrink-0">
                <div class="w-6 h-6 rounded-md bg-teal-600 text-white flex items-center justify-center text-xs shadow-2xs">
                    <i class="fa-solid fa-file-prescription"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-800">Load Doctor OPD Prescription (نسخہ لوڈ کریں):</span>
                    <span class="text-[11px] text-slate-500 hidden lg:inline">&bull; 1-Click auto-populate cart with prescribed FEFO drugs &amp; dosage directions</span>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-1 max-w-2xl">
                <select id="pos_load_rx_select" class="w-full text-xs border border-slate-300 rounded-lg py-1.5 px-3 bg-white focus:ring-2 focus:ring-teal-500 font-medium">
                    <option value="">-- Select Pending Doctor OPD Prescription to Auto-fill Cart (<?= count($pendingPrescriptionsList) ?> Available) --</option>
                    <?php foreach ($pendingPrescriptionsList as $prx): ?>
                    <option value="<?= $prx['prescription_id'] ?>">
                        [<?= htmlspecialchars($prx['prescription_number']) ?>] <?= htmlspecialchars($prx['first_name'] . ' ' . $prx['last_name']) ?> (MRN: <?= htmlspecialchars($prx['mrn']) ?>) &bull; <?= $prx['item_count'] ?> Meds (Dr. <?= htmlspecialchars($prx['doctor_name']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" onclick="triggerLoadRx()" class="px-3.5 py-1.5 bg-teal-700 hover:bg-teal-800 text-white rounded-lg text-xs font-bold shadow-2xs transition-colors flex items-center gap-1.5 flex-shrink-0">
                    <i class="fa-solid fa-arrow-down-to-bracket"></i>
                    <span>Load Rx</span>
                </button>
            </div>
        </div>

        <!-- Dynamic Feedback Alert for Loaded Prescription -->
        <div id="pos-rx-loaded-alert" class="hidden bg-teal-50 border-b border-teal-200 px-5 py-2.5 flex items-center justify-between text-xs text-teal-900">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-teal-600 text-sm"></i>
                <span id="pos-rx-loaded-text">Prescription loaded successfully.</span>
            </div>
            <button type="button" onclick="document.getElementById('pos-rx-loaded-alert').classList.add('hidden')" class="text-teal-700 hover:text-teal-900 font-bold text-xs">&times;</button>
        </div>

        <!-- POS Checkout Form -->
        <form id="pos-sale-form" action="index.php?action=pos_sale_checkout" method="POST" onsubmit="return validatePosCheckout(event)">
            <input type="hidden" name="prescription_id" id="pos_loaded_prescription_id" value="">

            <div class="p-5 sm:p-6 grid grid-cols-1 xl:grid-cols-12 gap-6">

                <!-- LEFT SIDE (7 Cols): Patient, Drug Selector & Live Cart Table -->
                <div class="xl:col-span-7 space-y-5">

                    <!-- Section 1: Patient / Customer Details -->
                    <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                            <label class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-user-tag text-medical-600"></i>
                                <span>1. Customer / Patient Mode</span>
                            </label>
                            <!-- Segmented Switcher -->
                            <div class="inline-flex rounded-lg border border-slate-300 p-0.5 bg-white shadow-2xs">
                                <label class="cursor-pointer">
                                    <input type="radio" name="customer_type" value="walk_in" checked onchange="toggleCustomerType('walk_in')" class="sr-only peer">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-md inline-flex items-center gap-1 text-slate-600 peer-checked:bg-medical-600 peer-checked:text-white transition-all">
                                        <i class="fa-solid fa-person-walking"></i>
                                        <span>Walk-in / Local Counter</span>
                                    </span>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="customer_type" value="registered" onchange="toggleCustomerType('registered')" class="sr-only peer">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-md inline-flex items-center gap-1 text-slate-600 peer-checked:bg-medical-600 peer-checked:text-white transition-all">
                                        <i class="fa-solid fa-hospital-user"></i>
                                        <span>Registered Hospital Patient</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Walk-in Customer Form -->
                        <div id="pos-walkin-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Customer / Patient Name *</label>
                                <input type="text" name="customer_name" id="pos_customer_name" value="Walk-in Customer" required
                                       class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-medical-500 font-medium">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Customer Contact Phone (Optional)</label>
                                <input type="text" name="customer_phone" id="pos_customer_phone" placeholder="e.g. 0300-1234567"
                                       class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-medical-500 font-medium">
                            </div>
                        </div>

                        <!-- Registered Patient Selector (Hidden by Default) -->
                        <div id="pos-registered-fields" class="hidden space-y-2">
                            <!-- Real-time Patient Filter Input -->
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Quick Search Registered Patient</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                                    </span>
                                    <input type="text" id="pos_patient_search" placeholder="Type Name, MRN or Phone to live filter patients..." oninput="filterPosPatients(this.value)"
                                           class="w-full text-xs border border-slate-300 rounded-lg pl-8 pr-3 py-1.5 bg-white focus:ring-2 focus:ring-teal-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Select Patient File *</label>
                                <select name="patient_id" id="pos_patient_id" onchange="onSelectRegisteredPatient(this)"
                                        class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-medical-500 font-medium">
                                    <option value="">-- Choose Registered Patient (<?= count($posPatients) ?> Available) --</option>
                                    <?php foreach ($posPatients as $pat): ?>
                                    <option value="<?= $pat['id'] ?>"
                                            data-mrn="<?= htmlspecialchars($pat['mrn']) ?>"
                                            data-name="<?= htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']) ?>"
                                            data-phone="<?= htmlspecialchars($pat['phone_primary'] ?? '') ?>"
                                            data-gender="<?= htmlspecialchars($pat['gender'] ?? '') ?>"
                                            data-age="<?= htmlspecialchars((string)($pat['age'] ?? '')) ?>">
                                        <?= htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']) ?> (MRN: <?= htmlspecialchars($pat['mrn']) ?>) &bull; Ph: <?= htmlspecialchars($pat['phone_primary'] ?? 'N/A') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Patient Quick Info Card -->
                            <div id="pos-selected-pat-info" class="hidden p-2.5 bg-teal-50 border border-teal-200 rounded-lg text-xs text-teal-900 flex items-center justify-between">
                                <span id="pos-pat-summary"></span>
                                <span class="text-[10px] font-bold text-teal-700 uppercase bg-teal-200/60 px-2 py-0.5 rounded">Active File</span>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Medication Formulary & Batch Picker -->
                    <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-3">
                        <label class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                            <i class="fa-solid fa-pills text-teal-600"></i>
                            <span>2. Select Medication &amp; Batch (FEFO Ready)</span>
                        </label>
                        
                        <div>
                            <select id="pos_batch_selector" onchange="onPosBatchSelected(this)"
                                    class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-teal-500 font-medium">
                                <option value="">-- Choose Medication from Formulary Stock (<?= count($posDrugBatches) ?> Batches) --</option>
                                <?php foreach ($posDrugBatches as $b): ?>
                                <option value="<?= $b['batch_id'] ?>"
                                        data-brand="<?= htmlspecialchars($b['brand_name']) ?>"
                                        data-generic="<?= htmlspecialchars($b['generic_name']) ?>"
                                        data-dosage="<?= htmlspecialchars($b['dosage_form'] . ' ' . $b['strength']) ?>"
                                        data-batch="<?= htmlspecialchars($b['batch_number']) ?>"
                                        data-expiry="<?= htmlspecialchars($b['expiry_date']) ?>"
                                        data-stock="<?= (int)$b['current_quantity'] ?>"
                                        data-price="<?= (float)$b['unit_sale_price'] ?>"
                                        data-controlled="<?= (int)$b['is_controlled_substance'] ?>"
                                        data-schedule="<?= htmlspecialchars($b['narcotic_schedule'] ?? '') ?>">
                                    <?= htmlspecialchars($b['brand_name']) ?> (<?= htmlspecialchars($b['dosage_form'] . ' ' . $b['strength']) ?>) &bull; Batch: <?= htmlspecialchars($b['batch_number']) ?> &bull; Exp: <?= htmlspecialchars($b['expiry_date']) ?> &bull; Rs. <?= number_format($b['unit_sale_price'], 2) ?> (Stock: <?= $b['current_quantity'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Item Detail Preview Box (Revealed on selection) -->
                        <div id="pos-item-preview" class="hidden p-3 bg-white border border-teal-200 rounded-xl shadow-2xs space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                <div>
                                    <span id="preview-brand" class="font-bold text-slate-900 text-sm"></span>
                                    <span id="preview-generic" class="text-xs text-slate-500 ml-1"></span>
                                </div>
                                <div id="preview-controlled-badge"></div>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Batch No</span>
                                    <strong id="preview-batch" class="font-mono text-slate-700"></strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Expiry</span>
                                    <strong id="preview-expiry" class="font-mono text-slate-700"></strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Available Stock</span>
                                    <strong id="preview-stock" class="text-emerald-700 font-bold"></strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Unit Retail Rate</span>
                                    <strong id="preview-price" class="text-slate-900 font-mono font-bold"></strong>
                                </div>
                            </div>

                            <!-- Row for Dosage Preset & Quantity before adding -->
                            <div class="pt-2 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                                <div class="sm:col-span-5">
                                    <label class="block text-[10px] font-bold text-slate-600 mb-0.5">طریقہ استعمال (Dosage Preset)</label>
                                    <select id="pos_picker_dosage_preset" onchange="onPickerPresetChange(this.value)" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-slate-50 font-medium">
                                        <option value="">-- منتخب کریں / Presets --</option>
                                        <option value="1 صبح 1 شام کھانے کے بعد">1 صبح 1 شام کھانے کے بعد</option>
                                        <option value="1 صبح نہار منہ">1 صبح نہار منہ</option>
                                        <option value="1 صبح 1 دوپہر 1 شام کھانے کے بعد">1 صبح 1 دوپہر 1 شام کھانے کے بعد</option>
                                        <option value="ایک چمچ دن میں 3 بار">ایک چمچ دن میں 3 بار</option>
                                        <option value="1 گولی ضرورت پڑنے پر / درد میں">1 گولی ضرورت پڑنے پر / درد میں</option>
                                        <option value="رات کو سوتے وقت 1 گولی">رات کو سوتے وقت 1 گولی</option>
                                        <option value="حسب ہدایت معالج">حسب ہدایت معالج</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Instructions / تفصیل</label>
                                    <input type="text" id="pos_picker_instructions" placeholder="e.g. کھانے کے بعد" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1.5">
                                </div>
                                <div class="sm:col-span-3 flex items-end gap-2">
                                    <div class="w-16">
                                        <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Qty</label>
                                        <input type="number" id="pos_item_qty" value="1" min="1" class="w-full text-center text-xs font-bold font-mono border border-slate-300 rounded-lg px-2 py-1.5">
                                    </div>
                                    <button type="button" onclick="addSelectedToPosCart()" class="flex-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition-colors flex items-center justify-center gap-1">
                                        <i class="fa-solid fa-cart-plus"></i>
                                        <span>Add</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Live Cart Items Table -->
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs">
                        <div class="px-4 py-3 bg-slate-50/80 border-b border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-basket-shopping text-emerald-600 text-xs"></i>
                                <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wide">POS Billing Cart</h4>
                            </div>
                            <span id="pos-cart-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">0 Items</span>
                        </div>

                        <div class="overflow-x-auto max-h-[360px] custom-scrollbar">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-100/70 border-b border-slate-200 text-[11px] font-semibold text-slate-600">
                                        <th class="px-3 py-2 w-8">#</th>
                                        <th class="px-3 py-2">Medication &amp; Batch</th>
                                        <th class="px-3 py-2 text-right w-20">Rate</th>
                                        <th class="px-3 py-2 text-center w-28">Qty</th>
                                        <th class="px-3 py-2 w-44">طریقہ استعمال / Instructions</th>
                                        <th class="px-3 py-2 text-right w-24">Total</th>
                                        <th class="px-2 py-2 text-center w-8"></th>
                                    </tr>
                                </thead>
                                <tbody id="pos-cart-tbody" class="divide-y divide-slate-100">
                                    <tr id="pos-cart-empty">
                                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                            <div class="w-12 h-12 rounded-full bg-slate-100 mx-auto flex items-center justify-center text-slate-300 text-xl mb-2">
                                                <i class="fa-solid fa-cart-shopping"></i>
                                            </div>
                                            <p class="font-medium text-xs text-slate-500">POS Cart is currently empty.</p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Scan formulary items above, tap Quick-Tap essentials, or load an OPD prescription.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- RIGHT SIDE (5 Cols): Billing Calculation, Payment Method, Tender Calculator & Checkout -->
                <div class="xl:col-span-5 bg-slate-50/90 p-5 rounded-xl border border-slate-200 flex flex-col justify-between space-y-4">
                    
                    <div class="space-y-4">
                        <div class="border-b border-slate-200 pb-3 flex items-center justify-between">
                            <h4 class="font-black text-slate-800 text-sm uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-calculator text-emerald-600"></i>
                                <span>3. Billing Summary &amp; Checkout</span>
                            </h4>
                            <button type="button" onclick="resetPosCart()" class="text-[11px] text-slate-400 hover:text-rose-600 transition-colors flex items-center gap-1" title="Clear all cart items">
                                <i class="fa-solid fa-rotate-left text-[10px]"></i>
                                <span>Clear Cart</span>
                            </button>
                        </div>

                        <!-- Subtotal, Discount & Tax Breakdown -->
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Cart Subtotal:</span>
                                <span id="pos-subtotal-txt" class="font-mono font-bold text-slate-800">Rs. 0.00</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Discount (Rs.)</label>
                                    <input type="number" name="discount_amount" id="pos_discount" value="0.00" min="0" step="0.5" oninput="recalcPosTotals()"
                                           class="w-full text-xs font-mono font-semibold border border-slate-300 rounded-lg px-2.5 py-1.5 bg-white text-right focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Sales Tax / GST (Rs.)</label>
                                    <input type="number" name="tax_amount" id="pos_tax" value="0.00" min="0" step="0.5" oninput="recalcPosTotals()"
                                           class="w-full text-xs font-mono font-semibold border border-slate-300 rounded-lg px-2.5 py-1.5 bg-white text-right focus:ring-2 focus:ring-emerald-500">
                                </div>
                            </div>
                        </div>

                        <!-- Grand Total Highlight Card -->
                        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border-2 border-emerald-500/50 rounded-xl p-4 text-center shadow-xs">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-800">NET AMOUNT PAYABLE</span>
                            <div id="pos-net-total-txt" class="text-3xl font-black font-mono text-emerald-700 mt-0.5">Rs. 0.00</div>
                            <span class="text-[10px] text-emerald-600 font-medium">Includes all taxes &amp; discounts</span>
                        </div>

                        <!-- Payment Method Selector -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1.5">Payment Method *</label>
                            <select name="payment_method" id="pos_payment_method" onchange="onPaymentMethodChange(this.value)"
                                    class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 bg-white font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                                <option value="Cash">💵 Cash Settlement (Default)</option>
                                <option value="Card">💳 Debit / Credit Card</option>
                                <option value="Bank_Transfer">🏦 Bank Wire / Online Transfer</option>
                                <option value="Mobile_Wallet">📱 Mobile Wallet (Easypaisa / JazzCash)</option>
                                <option value="Bill_to_Encounter" id="opt-bill-encounter" disabled>🏥 Bill to Hospital Encounter (Registered Patient)</option>
                            </select>
                        </div>

                        <!-- Cash Settlement Calculator (Tendered & Change) -->
                        <div id="pos-cash-details" class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <label class="text-[11px] font-bold text-slate-700">Cash Received / Tendered (Rs.)</label>
                                <button type="button" onclick="setExactTender()" class="text-[10px] text-emerald-700 hover:underline font-bold">Exact</button>
                            </div>
                            <input type="number" name="amount_tendered" id="pos_amount_tendered" value="0.00" min="0" step="10" oninput="calcPosChange()"
                                   class="w-full text-sm font-mono font-bold border border-slate-300 rounded-lg px-3 py-1.5 bg-white text-right focus:ring-2 focus:ring-emerald-500">
                            
                            <!-- Quick Cash Buttons -->
                            <div class="flex flex-wrap gap-1 pt-0.5">
                                <button type="button" onclick="addTender(50)" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-mono font-bold">+50</button>
                                <button type="button" onclick="addTender(100)" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-mono font-bold">+100</button>
                                <button type="button" onclick="addTender(500)" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-mono font-bold">+500</button>
                                <button type="button" onclick="addTender(1000)" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-mono font-bold">+1,000</button>
                                <button type="button" onclick="addTender(5000)" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-mono font-bold">+5,000</button>
                            </div>

                            <!-- Change Display -->
                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                                <span class="text-slate-600 font-semibold">Change to Return:</span>
                                <span id="pos-change-txt" class="font-mono text-sm text-slate-500 font-bold">Rs. 0.00</span>
                            </div>
                        </div>

                        <!-- Reference Field (for Card / Bank / Wallet) -->
                        <div id="pos-ref-field" class="hidden">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Transaction Ref / Approval Code</label>
                            <input type="text" name="payment_reference" id="pos_payment_reference" placeholder="e.g. POS-TXN-9842"
                                   class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-emerald-500 font-medium">
                        </div>

                        <!-- Notes / Dispensing Instructions -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Pharmacist / Dispensing Notes (Optional)</label>
                            <input type="text" name="sale_notes" id="pos_sale_notes" placeholder="e.g. Patient advised regarding hydration..."
                                   class="w-full text-xs border border-slate-300 rounded-lg px-3 py-1.5 bg-white text-slate-700">
                        </div>
                    </div>

                    <!-- Final Submission Checkout Button -->
                    <div class="pt-3 border-t border-slate-200 space-y-2">
                        <button type="submit" id="btn-pos-submit" disabled
                                class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white rounded-xl font-bold text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-print text-emerald-200"></i>
                            <span>Process Sale &amp; Print Patient Slip (F9)</span>
                        </button>
                        <p class="text-[10px] text-center text-slate-400">
                            Press <kbd class="px-1.5 py-0.5 bg-slate-200 rounded text-slate-700 font-mono font-bold">F9</kbd> anywhere on screen for instant checkout &bull; Auto-prints bilingual slip
                        </p>
                    </div>

                </div>

            </div>
        </form>

        <!-- POS Counter Recent Transactions Ledger Drawer -->
        <?php if (!empty($recentPosSales)): ?>
        <div class="border-t border-slate-200 bg-slate-50/60 p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                <div>
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-slate-500"></i>
                        <span>Today's Counter POS Sales Ledger</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700"><?= count($recentPosSales) ?> Recent</span>
                    </h4>
                    <p class="text-[11px] text-slate-500">Recent completed transactions at this counter with 1-click re-print options</p>
                </div>
                <div class="text-xs text-slate-500 font-medium">
                    <span><?= date('l, d M Y') ?></span>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100/70 border-b border-slate-200 text-[11px] font-semibold text-slate-600">
                            <th class="px-4 py-2.5">Invoice #</th>
                            <th class="px-4 py-2.5">Date &amp; Time</th>
                            <th class="px-4 py-2.5">Customer / Patient</th>
                            <th class="px-4 py-2.5">Items Purchased</th>
                            <th class="px-4 py-2.5">Payment</th>
                            <th class="px-4 py-2.5 text-right">Net Amount</th>
                            <th class="px-4 py-2.5 text-right">Print Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($recentPosSales as $sale): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-4 py-2.5 font-mono font-bold text-slate-800">
                                <?= htmlspecialchars($sale['sale_invoice_number']) ?>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap">
                                <?= date('h:i A', strtotime($sale['created_at'])) ?>
                            </td>
                            <td class="px-4 py-2.5 font-medium text-slate-900">
                                <div><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                                <?php if (!empty($sale['mrn'])): ?>
                                <span class="text-[10px] text-teal-700 font-mono font-semibold">MRN: <?= htmlspecialchars($sale['mrn']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($sale['items_summary'] ?? '') ?>">
                                <span class="font-bold text-slate-700"><?= $sale['total_items'] ?> Meds:</span>
                                <span class="text-slate-500 text-[11px]"><?= htmlspecialchars($sale['items_summary'] ?? 'Items dispensed') ?></span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                    <?= str_replace('_', ' ', $sale['payment_method']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900">
                                <?= format_currency($sale['net_total']) ?>
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="index.php?page=print_pos_receipt&sale_id=<?= $sale['id'] ?>&mode=thermal&autoprint=1" target="_blank"
                                       class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-md font-bold text-[11px] flex items-center gap-1 transition-colors"
                                       title="Print 80mm POS Thermal Slip">
                                        <i class="fa-solid fa-receipt text-[10px]"></i>
                                        <span>Slip (80mm)</span>
                                    </a>
                                    <a href="index.php?page=print_pos_receipt&sale_id=<?= $sale['id'] ?>&mode=standard" target="_blank"
                                       class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-md font-semibold text-[11px] flex items-center gap-1 transition-colors"
                                       title="Print Standard A4 Hospital Invoice">
                                        <i class="fa-solid fa-file-invoice text-[10px]"></i>
                                        <span>A4</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

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

<!-- ========================================================================= -->
<!-- JAVASCRIPT: POINT OF SALE (POS) LIVE ENGINE & STATE CONTROLLER -->
<!-- ========================================================================= -->
<script>
let posCart = [];
const pendingRxData = <?= json_encode($pendingRxItemsMap) ?>;
const pendingRxList = <?= json_encode($pendingPrescriptionsList) ?>;

// 1. Live Real-time Clock for POS Terminal
function updatePosClock() {
    const el = document.getElementById('pos-live-clock');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
}
setInterval(updatePosClock, 1000);
updatePosClock();

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
        document.getElementById('pos_loaded_prescription_id').value = '';
    } else {
        walkinDiv.classList.add('hidden');
        regDiv.classList.remove('hidden');
        optBill.disabled = false;
        custNameInput.value = '';
    }
}

// 3. Filter Registered Patients by Name, Phone, or MRN
function filterPosPatients(keyword) {
    const term = keyword.toLowerCase().trim();
    const select = document.getElementById('pos_patient_id');
    const options = select.options;

    for (let i = 1; i < options.length; i++) {
        const opt = options[i];
        const text = opt.textContent.toLowerCase();
        const phone = (opt.dataset.phone || '').toLowerCase();
        const mrn = (opt.dataset.mrn || '').toLowerCase();

        if (!term || text.includes(term) || phone.includes(term) || mrn.includes(term)) {
            opt.hidden = false;
        } else {
            opt.hidden = true;
        }
    }
}

// 4. Registered Patient Auto-Selection Handler
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

// 5. Batch Selector Preview Handler
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

// 6. Formulary Picker Preset Handler
function onPickerPresetChange(val) {
    if (val) {
        document.getElementById('pos_picker_instructions').value = val;
    }
}

// 7. Add Selected Item from Formulary to POS Cart
function addSelectedToPosCart() {
    const sel = document.getElementById('pos_batch_selector');
    const qtyInput = document.getElementById('pos_item_qty');
    const instructionsInput = document.getElementById('pos_picker_instructions');
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
    const instructions = instructionsInput.value.trim() || 'حسب ہدایت معالج (As Directed)';

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
        if (instructions && instructions !== 'حسب ہدایت معالج (As Directed)') {
            posCart[existingIndex].dosage_instructions = instructions;
        }
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
            qty: qty,
            dosage_instructions: instructions
        });
    }

    renderPosCart();

    // Reset selector
    sel.value = '';
    qtyInput.value = 1;
    instructionsInput.value = '';
    document.getElementById('pos_picker_dosage_preset').value = '';
    document.getElementById('pos-item-preview').classList.add('hidden');
    sel.focus();
}

// 8. Quick-Tap Fast-Moving OTC Addition (1-Click Instant Add)
function addQuickMed(batchId, brandName, price, dosage, batchNumber, expiryDate, stock) {
    if (stock <= 0) {
        alert(`${brandName} is currently out of stock.`);
        return;
    }

    const existingIndex = posCart.findIndex(item => item.batchId === batchId);
    if (existingIndex !== -1) {
        const newQty = posCart[existingIndex].qty + 1;
        if (newQty > stock) {
            alert(`Cannot add more. Stock limit of ${stock} reached for ${brandName}.`);
            return;
        }
        posCart[existingIndex].qty = newQty;
    } else {
        // Default common sensible instruction for quick meds
        let defaultInstruction = 'حسب ہدایت معالج (As Directed)';
        if (brandName.toLowerCase().includes('panadol') || brandName.toLowerCase().includes('brufen')) {
            defaultInstruction = '1 گولی ضرورت پڑنے پر / درد میں';
        } else if (brandName.toLowerCase().includes('risek')) {
            defaultInstruction = '1 صبح نہار منہ';
        } else if (brandName.toLowerCase().includes('augmentin')) {
            defaultInstruction = '1 صبح 1 شام کھانے کے بعد';
        }

        posCart.push({
            batchId: batchId,
            brand: brandName,
            generic: '',
            dosage: dosage,
            batch: batchNumber,
            expiry: expiryDate,
            price: price,
            stock: stock,
            qty: 1,
            dosage_instructions: defaultInstruction
        });
    }

    renderPosCart();
}

// 9. Fast-Load Doctor OPD Prescription into POS Cart
function triggerLoadRx() {
    const sel = document.getElementById('pos_load_rx_select');
    if (!sel || !sel.value) {
        alert('Please select a pending doctor prescription from the list.');
        sel.focus();
        return;
    }
    loadPrescriptionIntoPos(parseInt(sel.value));
}

function onSelectPrescriptionToLoad(sel) {
    if (sel.value) {
        loadPrescriptionIntoPos(parseInt(sel.value));
    }
}

function loadPrescriptionIntoPos(rxId) {
    const rx = pendingRxList.find(r => parseInt(r.prescription_id) === rxId);
    if (!rx) {
        alert('Prescription details not found.');
        return;
    }

    const items = pendingRxData[rxId] || [];
    if (items.length === 0) {
        alert('No medication items found for this prescription.');
        return;
    }

    // 1. Switch to registered customer mode
    const regRadio = document.querySelector('input[name="customer_type"][value="registered"]');
    if (regRadio) {
        regRadio.checked = true;
        toggleCustomerType('registered');
    }

    // 2. Select patient
    const patSelect = document.getElementById('pos_patient_id');
    patSelect.value = rx.patient_id;
    onSelectRegisteredPatient(patSelect);

    // 3. Mark prescription ID
    document.getElementById('pos_loaded_prescription_id').value = rxId;

    // 4. Populate Cart with prescribed items using matched FEFO batches
    posCart = [];
    let loadedCount = 0;
    let missingStockItems = [];

    items.forEach(it => {
        const batchId = parseInt(it.matched_batch_id);
        const stock = parseInt(it.matched_stock) || 0;

        if (batchId && stock > 0) {
            const requestedQty = parseInt(it.quantity_prescribed) || 1;
            const dispenseQty = Math.min(requestedQty, stock);
            const instructions = (it.instructions && it.instructions.trim()) ? it.instructions.trim() : (it.dosage ? (it.dosage + ' ' + (it.frequency || '')) : 'حسب ہدایت معالج');

            posCart.push({
                batchId: batchId,
                brand: it.brand_name,
                generic: it.generic_name,
                dosage: it.dosage_form + ' ' + it.strength,
                batch: it.matched_batch_number,
                expiry: it.matched_expiry_date,
                price: parseFloat(it.matched_price) || 0,
                stock: stock,
                qty: dispenseQty,
                dosage_instructions: instructions
            });
            loadedCount++;
        } else {
            missingStockItems.push(it.brand_name);
        }
    });

    renderPosCart();

    // 5. Show alert message
    const alertBox = document.getElementById('pos-rx-loaded-alert');
    const alertText = document.getElementById('pos-rx-loaded-text');
    let msg = `Loaded Doctor Prescription #${rx.prescription_number} for ${rx.first_name} ${rx.last_name} (${loadedCount} items added from Dr. ${rx.doctor_name}).`;
    if (missingStockItems.length > 0) {
        msg += ` Note: ${missingStockItems.join(', ')} currently out of stock.`;
    }
    alertText.textContent = msg;
    alertBox.classList.remove('hidden');
}

// 10. Render Cart Table with Urdu Dosage Presets
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
        badge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600';
        submitBtn.disabled = true;
    } else {
        if (emptyRow) emptyRow.style.display = 'none';
        
        let totalUnits = 0;

        posCart.forEach((item, idx) => {
            totalUnits += item.qty;
            const lineTotal = (item.qty * item.price).toFixed(2);
            const currentDir = item.dosage_instructions || '';

            const tr = document.createElement('tr');
            tr.className = 'pos-cart-item-row hover:bg-slate-50/80 transition-colors';
            tr.innerHTML = `
                <td class="px-3 py-2.5 font-mono text-slate-400 text-[11px] align-middle">${idx + 1}</td>
                <td class="px-3 py-2.5 align-middle">
                    <div class="font-bold text-slate-900">${escapeHtml(item.brand)}</div>
                    <div class="text-[10px] text-slate-500">${escapeHtml(item.dosage)} &bull; <span class="font-mono text-slate-600">B:${escapeHtml(item.batch)}</span> &bull; <span class="text-slate-400">Exp:${escapeHtml(item.expiry)}</span></div>
                    <input type="hidden" name="item_batch_id[]" value="${item.batchId}">
                    <input type="hidden" name="item_unit_price[]" value="${item.price}">
                </td>
                <td class="px-3 py-2.5 text-right font-mono font-semibold text-slate-700 align-middle">
                    Rs. ${item.price.toFixed(2)}
                </td>
                <td class="px-3 py-2.5 text-center align-middle">
                    <div class="inline-flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden shadow-2xs">
                        <button type="button" onclick="adjustCartQty(${item.batchId}, -1)" class="px-2 py-0.5 text-slate-600 hover:bg-slate-100 font-bold text-xs">-</button>
                        <input type="number" name="item_quantity[]" value="${item.qty}" min="1" max="${item.stock}" onchange="setCartQty(${item.batchId}, this.value)" class="w-12 text-center text-xs font-bold font-mono border-x border-slate-200 py-0.5 outline-none">
                        <button type="button" onclick="adjustCartQty(${item.batchId}, 1)" class="px-2 py-0.5 text-slate-600 hover:bg-slate-100 font-bold text-xs">+</button>
                    </div>
                </td>
                <td class="px-3 py-2.5 align-middle">
                    <div class="space-y-1">
                        <select onchange="onRowPresetChange(${idx}, this.value)" class="w-full text-[10px] border border-slate-200 rounded px-1.5 py-0.5 bg-slate-50 text-slate-700">
                            <option value="">-- طریقہ استعمال (Preset) --</option>
                            <option value="1 صبح 1 شام کھانے کے بعد" ${currentDir === '1 صبح 1 شام کھانے کے بعد' ? 'selected' : ''}>1 صبح 1 شام کھانے کے بعد</option>
                            <option value="1 صبح نہار منہ" ${currentDir === '1 صبح نہار منہ' ? 'selected' : ''}>1 صبح نہار منہ</option>
                            <option value="1 صبح 1 دوپہر 1 شام کھانے کے بعد" ${currentDir === '1 صبح 1 دوپہر 1 شام کھانے کے بعد' ? 'selected' : ''}>1 صبح 1 دوپہر 1 شام کھانے کے بعد</option>
                            <option value="ایک چمچ دن میں 3 بار" ${currentDir === 'ایک چمچ دن میں 3 بار' ? 'selected' : ''}>ایک چمچ دن میں 3 بار</option>
                            <option value="1 گولی ضرورت پڑنے پر / درد میں" ${currentDir === '1 گولی ضرورت پڑنے پر / درد میں' ? 'selected' : ''}>1 گولی ضرورت پڑنے پر / درد میں</option>
                            <option value="رات کو سوتے وقت 1 گولی" ${currentDir === 'رات کو سوتے وقت 1 گولی' ? 'selected' : ''}>رات کو سوتے وقت 1 گولی</option>
                            <option value="حسب ہدایت معالج" ${currentDir === 'حسب ہدایت معالج' ? 'selected' : ''}>حسب ہدایت معالج</option>
                        </select>
                        <input type="text" name="item_dosage_instructions[]" value="${escapeHtml(currentDir)}" oninput="onRowDosageTextChange(${idx}, this.value)" placeholder="طریقہ استعمال / Instructions..." class="w-full text-xs border border-slate-200 rounded px-2 py-1 focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                    </div>
                </td>
                <td class="px-3 py-2.5 text-right font-mono font-bold text-slate-900 align-middle">
                    Rs. ${lineTotal}
                </td>
                <td class="px-2 py-2.5 text-center align-middle">
                    <button type="button" onclick="removePosCartItem(${item.batchId})" class="p-1 text-rose-500 hover:bg-rose-50 rounded transition-colors" title="Remove item">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        badge.textContent = `${totalUnits} Items (${posCart.length} SKU)`;
        badge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800';
        submitBtn.disabled = false;
    }

    recalcPosTotals();
}

// 11. Row Dosage Instruction Handlers
function onRowPresetChange(idx, val) {
    if (posCart[idx] && val) {
        posCart[idx].dosage_instructions = val;
        renderPosCart();
    }
}

function onRowDosageTextChange(idx, val) {
    if (posCart[idx]) {
        posCart[idx].dosage_instructions = val;
    }
}

// 12. Adjust Cart Item Quantity
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

// 13. Remove Item from Cart
function removePosCartItem(batchId) {
    posCart = posCart.filter(i => i.batchId !== batchId);
    renderPosCart();
}

// 14. Recalculate POS Financial Totals
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

// 15. Payment Method Switcher
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

// 16. Calculate Change Returned
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

// 17. Quick Tender Convenience Buttons
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

// 18. Reset Entire POS Form
function resetPosCart() {
    if (posCart.length > 0 && !confirm('Are you sure you want to reset and clear the current POS cart?')) {
        return;
    }
    posCart = [];
    document.getElementById('pos_discount').value = '0.00';
    document.getElementById('pos_tax').value = '0.00';
    document.getElementById('pos_amount_tendered').value = '0.00';
    document.getElementById('pos_batch_selector').value = '';
    document.getElementById('pos_loaded_prescription_id').value = '';
    document.getElementById('pos-rx-loaded-alert').classList.add('hidden');
    document.getElementById('pos-item-preview').classList.add('hidden');
    renderPosCart();
}

// 19. Validation Before Checkout
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
