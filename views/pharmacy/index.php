<?php
/**
 * Hospital Management System (HMS) - Pharmacy & Out-Patient Dispensary
 * 
 * Features:
 * 1. Medicine Out-Patient Dispensary (OPD Counter / POS with Live Cart & Thermal/A4 Receipt)
 * 2. Closed-Loop Prescription Dispense Queue (Doctor Encounters & FEFO Sync)
 * 3. Drug Formulary & Batch Inventory (FEFO Tracking & Expiry Alerts)
 * 4. Pharmacy-Exclusive Bulk Medicine Stock Entry (Multi-Row Fast Grid & CSV Importer)
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();
$hId = Tenant::id();

$activeTab = clean_input($_GET['tab'] ?? 'dispensary');
$rxSearch = clean_input($_GET['search'] ?? '');
$printReceiptRxId = (int)($_GET['print_receipt'] ?? 0);
$completedSaleId = (int)($_GET['pos_sale_id'] ?? 0);

// ----------------------------------------------------------------------------
// 1. DATA FOR OUT-PATIENT DISPENSARY (OPD POS COUNTER)
// ----------------------------------------------------------------------------

// A. Active In-Stock Batches for Out-Patient Cart (FEFO Sorted)
$activeStockBatches = $db->query("
    SELECT db.*, di.id AS drug_id, di.brand_name, di.generic_name, di.dosage_form, di.strength, di.unit_of_measure,
           di.is_controlled_substance, di.narcotic_schedule, di.storage_condition
    FROM `drug_batches` db
    JOIN `drug_items` di ON db.drug_item_id = di.id
    WHERE db.status = 'Active' AND db.current_quantity > 0 AND db.expiry_date >= CURDATE() AND db.hospital_id = {$hId}
    ORDER BY di.brand_name ASC, db.expiry_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// B. Registered Patients for OPD Dispensary Selection
$posPatients = $db->query("
    SELECT p.id, p.mrn, p.first_name, p.last_name, p.phone_primary, p.gender, p.age
    FROM `patients` p
    WHERE p.status = 'Active' AND p.hospital_id = {$hId}
    ORDER BY p.id DESC
    LIMIT 150
")->fetchAll(PDO::FETCH_ASSOC);

// C. Pending Doctor OPD Prescriptions for 1-Click Fast Loading into Dispensary
$pendingPrescriptionsList = $db->query("
    SELECT pr.id AS prescription_id, pr.prescription_number, pr.created_at,
           p.id AS patient_id, p.mrn, p.first_name, p.last_name, p.phone_primary,
           doc.full_name AS doctor_name,
           (SELECT COUNT(*) FROM `prescription_items` WHERE prescription_id = pr.id) AS item_count
    FROM `prescriptions` pr
    JOIN `encounters` e ON pr.encounter_id = e.id
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` doc ON pr.doctor_id = doc.id
    WHERE pr.status = 'Pending' AND pr.hospital_id = {$hId}
    ORDER BY pr.id DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

// D. Pending items map for Rx loader JS
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
            pi.duration_days,
            pi.quantity_prescribed,
            pi.instructions,
            di.brand_name,
            di.generic_name,
            di.dosage_form,
            di.strength,
            di.is_controlled_substance,
            (SELECT db.id FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 AND db.expiry_date >= CURDATE() AND db.hospital_id = {$hId} ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_batch_id,
            (SELECT db.batch_number FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 AND db.expiry_date >= CURDATE() AND db.hospital_id = {$hId} ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_batch_number,
            (SELECT db.expiry_date FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 AND db.expiry_date >= CURDATE() AND db.hospital_id = {$hId} ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_expiry_date,
            (SELECT db.unit_sale_price FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 AND db.expiry_date >= CURDATE() AND db.hospital_id = {$hId} ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_price,
            (SELECT db.current_quantity FROM `drug_batches` db WHERE db.drug_item_id = di.id AND db.status = 'Active' AND db.current_quantity > 0 AND db.expiry_date >= CURDATE() AND db.hospital_id = {$hId} ORDER BY db.expiry_date ASC, db.id ASC LIMIT 1) AS matched_stock
        FROM `prescription_items` pi
        JOIN `drug_items` di ON pi.drug_item_id = di.id
        WHERE pi.prescription_id IN ($inClause)
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($itemsRaw as $row) {
        $pendingRxItemsMap[$row['prescription_id']][] = $row;
    }
}

// E. Quick OTC items for quick-tap dispensing
$quickMeds = $db->query("
    SELECT db.id AS batch_id, di.brand_name, di.generic_name, di.dosage_form, di.strength,
           db.batch_number, db.expiry_date, db.unit_sale_price, db.current_quantity
    FROM `drug_batches` db
    JOIN `drug_items` di ON db.drug_item_id = di.id
    WHERE db.status = 'Active' AND db.current_quantity > 0 AND db.expiry_date >= CURDATE() AND db.hospital_id = {$hId}
      AND (di.brand_name LIKE '%Panadol%' OR di.brand_name LIKE '%Augmentin%' OR di.brand_name LIKE '%Risek%' 
           OR di.brand_name LIKE '%Brufen%' OR di.brand_name LIKE '%Loprin%' OR di.brand_name LIKE '%Flagyl%'
           OR di.brand_name LIKE '%Disprin%' OR di.brand_name LIKE '%Cac%')
    GROUP BY di.id
    ORDER BY db.current_quantity DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// F. Recent Out-Patient Dispensations Ledger
$recentDispensations = $db->query("
    SELECT ps.*, u.full_name AS pharmacist_name,
           p.mrn, p.first_name, p.last_name,
           (SELECT COUNT(*) FROM `pharmacy_sale_items` WHERE pharmacy_sale_id = ps.id) AS item_count,
           (SELECT GROUP_CONCAT(CONCAT(di.brand_name, ' (x', psi.quantity, ')') SEPARATOR ', ')
            FROM `pharmacy_sale_items` psi
            JOIN `drug_batches` db ON psi.drug_batch_id = db.id
            JOIN `drug_items` di ON db.drug_item_id = di.id
            WHERE psi.pharmacy_sale_id = ps.id) AS items_summary
    FROM `pharmacy_sales` ps
    JOIN `users` u ON ps.dispensed_by = u.id
    LEFT JOIN `patients` p ON ps.patient_id = p.id
    WHERE ps.hospital_id = {$hId}
    ORDER BY ps.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// G. Completed Sale if returning from checkout
$completedSale = null;
if ($completedSaleId > 0) {
    $cStmt = $db->prepare("SELECT sale_invoice_number, customer_name, net_total FROM `pharmacy_sales` WHERE id = ? AND hospital_id = ?");
    $cStmt->execute([$completedSaleId, $hId]);
    $completedSale = $cStmt->fetch(PDO::FETCH_ASSOC);
}

// ----------------------------------------------------------------------------
// 2. DATA FOR PRESCRIPTION DISPENSE QUEUE
// ----------------------------------------------------------------------------
$rxQuery = "
    SELECT p.*, e.encounter_number, e.encounter_type,
           pt.id AS patient_id, pt.first_name, pt.last_name, pt.mrn, pt.gender, pt.age,
           u.full_name AS doctor_name,
           (SELECT COUNT(*) FROM `prescription_items` WHERE `prescription_id` = p.id) AS total_items
    FROM `prescriptions` p
    JOIN `encounters` e ON p.encounter_id = e.id
    JOIN `patients` pt ON e.patient_id = pt.id
    JOIN `users` u ON p.doctor_id = u.id
    WHERE p.hospital_id = ?
";
$rxParams = [$hId];
if (!empty($rxSearch)) {
    $rxQuery .= " AND (p.prescription_number LIKE ? OR pt.mrn LIKE ? OR pt.first_name LIKE ? OR pt.last_name LIKE ?)";
    $searchTerm = "%$rxSearch%";
    $rxParams = array_merge($rxParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}
$rxQuery .= " ORDER BY p.id DESC";
$rxStmt = $db->prepare($rxQuery);
$rxStmt->execute($rxParams);
$prescriptions = $rxStmt->fetchAll();

// Receipt data for queue modal
$receiptData = null;
$receiptItems = [];
if ($printReceiptRxId > 0) {
    $recStmt = $db->prepare("
        SELECT p.*, e.encounter_number, e.encounter_type,
               pt.id AS patient_id, pt.first_name, pt.last_name, pt.mrn, pt.gender, pt.age, pt.phone_primary,
               u.full_name AS doctor_name, d.department_name
        FROM `prescriptions` p
        JOIN `encounters` e ON p.encounter_id = e.id
        JOIN `patients` pt ON e.patient_id = pt.id
        JOIN `users` u ON p.doctor_id = u.id
        LEFT JOIN `departments` d ON u.department_id = d.id
        WHERE p.id = ?
    ");
    $recStmt->execute([$printReceiptRxId]);
    $receiptData = $recStmt->fetch();

    if ($receiptData) {
        $recItemStmt = $db->prepare("
            SELECT pi.*, di.brand_name, di.generic_name, di.strength, di.dosage_form,
                   COALESCE((SELECT unit_sale_price FROM `drug_batches` WHERE drug_item_id = di.id AND hospital_id = {$hId} ORDER BY expiry_date ASC LIMIT 1), 0) AS unit_price
            FROM `prescription_items` pi
            JOIN `drug_items` di ON pi.drug_item_id = di.id
            WHERE pi.prescription_id = ?
        ");
        $recItemStmt->execute([$printReceiptRxId]);
        $receiptItems = $recItemStmt->fetchAll();
    }
}

// ----------------------------------------------------------------------------
// 3. DATA FOR DRUG STOCK & BATCHES (FORMULARY)
// ----------------------------------------------------------------------------
$stockFilter = clean_input($_GET['stock_filter'] ?? 'all');
$stockSql = "
    SELECT b.*, di.brand_name, di.generic_name, di.dosage_form, di.strength, di.unit_of_measure,
           di.is_controlled_substance, di.narcotic_schedule, di.storage_condition,
           c.category_name
    FROM `drug_batches` b
    JOIN `drug_items` di ON b.drug_item_id = di.id
    JOIN `drug_categories` c ON di.category_id = c.id
    WHERE b.hospital_id = {$hId}
";
if ($stockFilter === 'narcotics') {
    $stockSql .= " AND di.is_controlled_substance = 1";
} elseif ($stockFilter === 'near_expiry') {
    $stockSql .= " AND b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
} elseif ($stockFilter === 'expired') {
    $stockSql .= " AND (b.expiry_date < CURDATE() OR b.status = 'Expired')";
}
$stockSql .= " ORDER BY b.expiry_date ASC";
$batches = $db->query($stockSql)->fetchAll();

$batchCounts = $db->query("
    SELECT 
        COUNT(*) as total_batches,
        COUNT(CASE WHEN di.is_controlled_substance = 1 THEN 1 END) as narcotics_batches,
        COUNT(CASE WHEN b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 1 END) as near_expiry_batches,
        COUNT(CASE WHEN b.expiry_date < CURDATE() OR b.status = 'Expired' THEN 1 END) as expired_batches
    FROM `drug_batches` b
    JOIN `drug_items` di ON b.drug_item_id = di.id
    WHERE b.hospital_id = {$hId}
")->fetch(PDO::FETCH_ASSOC);

// 4. Formularies & Categories for Stock Entry Dropdowns
$allDrugItems = $db->query("
    SELECT di.id, di.brand_name, di.generic_name, di.dosage_form, di.strength, di.category_id, c.category_name
    FROM `drug_items` di
    LEFT JOIN `drug_categories` c ON di.category_id = c.id
    WHERE di.status = 'Active' AND (di.hospital_id = {$hId} OR di.hospital_id IS NULL)
    ORDER BY di.brand_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$drugCategories = $db->query("
    SELECT * FROM `drug_categories`
    WHERE hospital_id = {$hId} OR hospital_id IS NULL
    ORDER BY category_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-6 space-y-6">

    <!-- Top Header & Primary Navigation -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-100 text-teal-800 border border-teal-200">
                    <i class="fa-solid fa-pills mr-1.5 text-teal-600"></i> Pharmacy Department
                </span>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Pharmacy &amp; Out-Patient Dispensary</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Outpatient medicine dispensing, prescription verification, FEFO batch ledger &amp; exclusive bulk stock entry.</p>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="document.getElementById('modal-single-batch').classList.remove('hidden')" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-xl text-xs font-bold shadow-2xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-plus text-teal-600"></i>
                <span>Add Single Batch</span>
            </button>
            <a href="index.php?page=pharmacy&tab=bulk_entry" class="px-3.5 py-1.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-table-cells"></i>
                <span>Bulk Stock Entry</span>
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 flex flex-wrap items-center gap-2 sm:gap-6 text-xs font-semibold">
        <a href="index.php?page=pharmacy&tab=dispensary" class="pb-3 flex items-center gap-2 border-b-2 transition-all <?= $activeTab === 'dispensary' ? 'border-teal-600 text-teal-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-cash-register"></i>
            <span>Out-Patient Dispensary (OPD)</span>
            <span class="px-1.5 py-0.2 rounded-full bg-teal-100 text-teal-800 font-mono text-[10px]">POS</span>
        </a>
        <a href="index.php?page=pharmacy&tab=queue" class="pb-3 flex items-center gap-2 border-b-2 transition-all <?= $activeTab === 'queue' ? 'border-teal-600 text-teal-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-file-prescription"></i>
            <span>Prescription Queue</span>
            <?php if (count($pendingPrescriptionsList) > 0): ?>
                <span class="px-1.5 py-0.2 rounded-full bg-amber-100 text-amber-800 font-mono text-[10px]"><?= count($pendingPrescriptionsList) ?></span>
            <?php endif; ?>
        </a>
        <a href="index.php?page=pharmacy&tab=stock" class="pb-3 flex items-center gap-2 border-b-2 transition-all <?= $activeTab === 'stock' ? 'border-teal-600 text-teal-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>Medicine Stock &amp; Batches (FEFO)</span>
            <span class="px-1.5 py-0.2 rounded-full bg-slate-100 text-slate-700 font-mono text-[10px]"><?= (int)($batchCounts['total_batches'] ?? 0) ?></span>
        </a>
        <a href="index.php?page=pharmacy&tab=bulk_entry" class="pb-3 flex items-center gap-2 border-b-2 transition-all <?= $activeTab === 'bulk_entry' ? 'border-teal-600 text-teal-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-table-cells"></i>
            <span>Bulk Stock Entry</span>
            <span class="px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-800 font-mono text-[10px]">Pharmacy Only</span>
        </a>
    </div>

    <!-- ======================================================================= -->
    <!-- TAB 1: OUT-PATIENT DISPENSARY (OPD POS COUNTER)                         -->
    <!-- ======================================================================= -->
    <?php if ($activeTab === 'dispensary'): ?>
    
        <!-- Success Alert if returning from a sale checkout -->
        <?php if ($completedSale): ?>
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-2xl p-5 shadow-lg flex flex-col md:flex-row items-center justify-between gap-4 animate-fade-in border border-emerald-400/40">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl flex-shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-200">Out-Patient Dispensary Transaction Completed</div>
                    <h4 class="text-base sm:text-lg font-black">Invoice #<?= htmlspecialchars($completedSale['sale_invoice_number']) ?> &bull; <?= format_currency($completedSale['net_total']) ?></h4>
                    <p class="text-xs text-emerald-100">Customer: <strong class="text-white"><?= htmlspecialchars($completedSale['customer_name'] ?? 'Walk-in Customer') ?></strong> &bull; Inventory deducted via FEFO &bull; Ready for printing.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="index.php?page=print_pos_receipt&sale_id=<?= $completedSaleId ?>&mode=thermal&autoprint=1" target="_blank" class="px-3.5 py-2 bg-white text-emerald-900 hover:bg-emerald-50 rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-receipt text-emerald-600"></i>
                    <span>Print 80mm Slip</span>
                </a>
                <a href="index.php?page=print_pos_receipt&sale_id=<?= $completedSaleId ?>&mode=standard" target="_blank" class="px-3.5 py-2 bg-white/15 hover:bg-white/25 text-white rounded-xl text-xs font-bold border border-white/20 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>A4 Slip</span>
                </a>
                <a href="index.php?page=pharmacy&tab=dispensary" class="p-2 text-white/70 hover:text-white text-sm" title="Dismiss">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- POS Terminal Container -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            
            <!-- Terminal Header Banner -->
            <div class="bg-gradient-to-r from-emerald-800 via-teal-800 to-navy-900 text-white p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-400/20 text-emerald-300 border border-emerald-400/30 uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping inline-block"></span>
                            LIVE OPD DISPENSARY COUNTER
                        </span>
                        <span class="text-xs text-emerald-200/80 font-mono hidden sm:inline">&bull; Pharmacy Central Counter</span>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black tracking-tight flex items-center gap-2.5">
                        <i class="fa-solid fa-cash-register text-emerald-400"></i>
                        <span>Medicine Out-Patient Dispensary</span>
                    </h3>
                    <p class="text-xs sm:text-sm text-emerald-100/80 mt-1 max-w-2xl">
                        Fast dispensing for out-patients &amp; walk-ins &bull; FEFO stock deduction &bull; Doctor prescription auto-fill &bull; Thermal 80mm &amp; A4 invoicing.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 self-start md:self-center">
                    <div class="bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-right">
                        <div class="text-[10px] text-emerald-200 uppercase font-semibold">Active Dispenser</div>
                        <div class="text-xs font-bold text-white"><?= htmlspecialchars($currentUser['full_name'] ?? 'Pharmacist') ?></div>
                    </div>
                    <div class="bg-black/30 border border-white/10 rounded-xl px-3.5 py-2 text-center">
                        <div class="text-[10px] text-emerald-200 uppercase font-semibold">Live Clock</div>
                        <div id="dispensary-clock" class="font-mono font-black text-sm text-emerald-300">--:--:--</div>
                    </div>
                </div>
            </div>

            <!-- Quick-Tap OTC Essentials Bar -->
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
                            title="Click to add 1 unit to cart">
                        <span>💊 <?= htmlspecialchars($qm['brand_name']) ?></span>
                        <span class="text-[10px] text-slate-400 group-hover:text-emerald-100"><?= htmlspecialchars($qm['strength']) ?></span>
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
                        <span class="text-xs font-bold text-slate-800">Load Doctor OPD Prescription:</span>
                        <span class="text-[11px] text-slate-500 hidden lg:inline">&bull; 1-Click auto-populate cart with prescribed FEFO drugs &amp; dosage directions</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-1 max-w-2xl">
                    <select id="pos_load_rx_select" class="w-full text-xs border border-slate-300 rounded-lg py-1.5 px-3 bg-white focus:ring-2 focus:ring-teal-500 font-medium">
                        <option value="">-- Select Pending Doctor OPD Prescription (<?= count($pendingPrescriptionsList) ?> Available) --</option>
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

            <!-- Outpatient Checkout Form -->
            <form id="pos-sale-form" action="index.php?action=pos_sale_checkout" method="POST" onsubmit="return validatePosCheckout(event)">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="pharmacy">
                <input type="hidden" name="prescription_id" id="pos_loaded_prescription_id" value="">

                <div class="p-5 sm:p-6 grid grid-cols-1 xl:grid-cols-12 gap-6">

                    <!-- LEFT SIDE (7 Cols): Patient, Drug Selector & Live Cart Table -->
                    <div class="xl:col-span-7 space-y-5">

                        <!-- Section 1: Patient / Customer Details -->
                        <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                <label class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                    <i class="fa-solid fa-user-tag text-teal-600"></i>
                                    <span>1. Patient / Customer Selection</span>
                                </label>
                                <!-- Mode Switcher -->
                                <div class="inline-flex rounded-lg border border-slate-300 p-0.5 bg-white shadow-2xs">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="customer_type" value="walk_in" checked onchange="toggleCustomerType('walk_in')" class="sr-only peer">
                                        <span class="px-3 py-1 text-xs font-semibold rounded-md inline-flex items-center gap-1 text-slate-600 peer-checked:bg-teal-600 peer-checked:text-white transition-all">
                                            <i class="fa-solid fa-person-walking"></i>
                                            <span>Walk-in Out-Patient</span>
                                        </span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="customer_type" value="registered" onchange="toggleCustomerType('registered')" class="sr-only peer">
                                        <span class="px-3 py-1 text-xs font-semibold rounded-md inline-flex items-center gap-1 text-slate-600 peer-checked:bg-teal-600 peer-checked:text-white transition-all">
                                            <i class="fa-solid fa-hospital-user"></i>
                                            <span>Registered Patient (MRN)</span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Registered Patient Mode -->
                            <div id="patient-registered-fields" class="hidden space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Select Registered Hospital Patient</label>
                                    <select name="patient_id" id="pos_patient_id" onchange="onRegisteredPatientSelect(this)" class="w-full text-xs border border-slate-300 rounded-lg p-2.5 bg-white focus:ring-2 focus:ring-teal-500 font-medium">
                                        <option value="">-- Search &amp; Select Patient by MRN or Name --</option>
                                        <?php foreach ($posPatients as $pt): ?>
                                        <option value="<?= $pt['id'] ?>" data-name="<?= htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']) ?>" data-phone="<?= htmlspecialchars($pt['phone_primary'] ?? '') ?>" data-mrn="<?= htmlspecialchars($pt['mrn']) ?>">
                                            [<?= htmlspecialchars($pt['mrn']) ?>] <?= htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']) ?> &bull; <?= htmlspecialchars($pt['gender'] ?? 'N/A') ?>, <?= htmlspecialchars($pt['age'] ?? 'N/A') ?>y &bull; <?= htmlspecialchars($pt['phone_primary'] ?? 'No Phone') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Walk-in Customer Fields -->
                            <div id="patient-walkin-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Customer / Patient Name</label>
                                    <input type="text" name="customer_name" id="pos_customer_name" placeholder="e.g. Muhammad Ali or Walk-in" value="Walk-in Customer" class="w-full text-xs border border-slate-300 rounded-lg p-2 bg-white focus:ring-2 focus:ring-teal-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contact Phone (Optional)</label>
                                    <input type="text" name="customer_phone" id="pos_customer_phone" placeholder="0300-1234567" class="w-full text-xs border border-slate-300 rounded-lg p-2 bg-white focus:ring-2 focus:ring-teal-500 font-mono">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Medication Search & Live Stock Selector -->
                        <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200">
                            <label class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5 mb-2">
                                <i class="fa-solid fa-magnifying-glass text-teal-600"></i>
                                <span>2. Search &amp; Add Medication from Formulary</span>
                            </label>
                            
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="relative flex-1">
                                    <select id="pos_drug_selector" class="w-full text-xs border border-slate-300 rounded-lg p-2.5 bg-white focus:ring-2 focus:ring-teal-500 font-medium">
                                        <option value="">-- Choose In-Stock Medication (FEFO Batch &amp; Expiry) --</option>
                                        <?php foreach ($activeStockBatches as $dbatch): ?>
                                        <option value="<?= $dbatch['id'] ?>"
                                                data-batch-id="<?= $dbatch['id'] ?>"
                                                data-brand="<?= htmlspecialchars($dbatch['brand_name']) ?>"
                                                data-generic="<?= htmlspecialchars($dbatch['generic_name']) ?>"
                                                data-form="<?= htmlspecialchars($dbatch['dosage_form'] . ' ' . $dbatch['strength']) ?>"
                                                data-batch-number="<?= htmlspecialchars($dbatch['batch_number']) ?>"
                                                data-expiry="<?= htmlspecialchars($dbatch['expiry_date']) ?>"
                                                data-stock="<?= (int)$dbatch['current_quantity'] ?>"
                                                data-price="<?= (float)$dbatch['unit_sale_price'] ?>"
                                                data-controlled="<?= (int)$dbatch['is_controlled_substance'] ?>">
                                            💊 <?= htmlspecialchars($dbatch['brand_name']) ?> (<?= htmlspecialchars($dbatch['dosage_form'] . ' ' . $dbatch['strength']) ?>) &bull; Batch: <?= htmlspecialchars($dbatch['batch_number']) ?> &bull; Exp: <?= format_date($dbatch['expiry_date']) ?> &bull; Avail: <?= $dbatch['current_quantity'] ?> &bull; Rs. <?= number_format($dbatch['unit_sale_price'], 2) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" onclick="addSelectedDrugToCart()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-2xs transition-colors flex items-center justify-center gap-1.5 flex-shrink-0">
                                    <i class="fa-solid fa-cart-plus"></i>
                                    <span>Add to Cart</span>
                                </button>
                            </div>
                        </div>

                        <!-- Section 3: Live Dispensary Cart Table -->
                        <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-2xs">
                            <div class="bg-slate-100 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between">
                                <div class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                    <i class="fa-solid fa-basket-shopping text-teal-600"></i>
                                    <span>3. Out-Patient Dispensing Cart</span>
                                    <span id="pos-cart-badge" class="ml-1 px-2 py-0.2 rounded-full bg-teal-600 text-white text-[10px] font-mono font-bold">0 Items</span>
                                </div>
                                <button type="button" onclick="clearPosCart()" class="text-[11px] text-rose-600 hover:text-rose-800 font-semibold flex items-center gap-1">
                                    <i class="fa-solid fa-trash-can"></i>
                                    <span>Clear Cart</span>
                                </button>
                            </div>

                            <div class="overflow-x-auto max-h-[360px] custom-scrollbar">
                                <table class="w-full text-left text-xs" id="pos-cart-table">
                                    <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200 sticky top-0 z-10">
                                        <tr>
                                            <th class="px-3 py-2.5">Medication &amp; Batch</th>
                                            <th class="px-2 py-2.5 text-center w-20">Stock</th>
                                            <th class="px-2 py-2.5 text-center w-24">Qty</th>
                                            <th class="px-2 py-2.5 text-right w-24">Price</th>
                                            <th class="px-3 py-2.5 min-w-[150px]">Dosage Directions (طریقہ)</th>
                                            <th class="px-3 py-2.5 text-right w-24">Total</th>
                                            <th class="px-2 py-2.5 text-center w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="pos-cart-tbody" class="divide-y divide-slate-100">
                                        <tr id="pos-cart-empty-row">
                                            <td colspan="7" class="py-10 text-center text-slate-400">
                                                <i class="fa-solid fa-cart-arrow-down text-3xl mb-2 text-slate-300"></i>
                                                <p class="font-medium text-xs">Dispensary cart is empty.</p>
                                                <p class="text-[11px] text-slate-400 mt-0.5">Select a medicine above or click a Quick-Tap item to begin.</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- RIGHT SIDE (5 Cols): Calculation, Payment, & Final Checkout -->
                    <div class="xl:col-span-5 space-y-5">
                        
                        <!-- Financial Summary Box -->
                        <div class="bg-gradient-to-br from-slate-900 to-navy-950 text-white p-5 rounded-2xl shadow-md border border-slate-800 space-y-4">
                            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                                <span class="text-xs font-bold uppercase tracking-wider text-teal-300 flex items-center gap-1.5">
                                    <i class="fa-solid fa-receipt"></i>
                                    <span>Billing Summary</span>
                                </span>
                                <span class="text-[11px] text-slate-400 font-mono">Currency: PKR</span>
                            </div>

                            <div class="space-y-2 text-xs">
                                <div class="flex items-center justify-between text-slate-300">
                                    <span>Subtotal:</span>
                                    <span class="font-mono font-bold text-white text-sm" id="pos-summary-subtotal">Rs. 0.00</span>
                                </div>

                                <div class="flex items-center justify-between gap-3 pt-1">
                                    <label class="text-slate-300 text-xs">Discount (Rs.):</label>
                                    <div class="w-32">
                                        <input type="number" step="0.50" min="0" name="discount_amount" id="pos_discount_amount" value="0.00" oninput="calculatePosTotals()" class="w-full text-right text-xs bg-slate-800 border border-slate-700 rounded-lg p-1.5 text-white font-mono focus:border-teal-400">
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <label class="text-slate-300 text-xs">Tax Rate (%):</label>
                                    <div class="w-32">
                                        <input type="number" step="0.50" min="0" name="tax_rate" id="pos_tax_rate" value="0.00" oninput="calculatePosTotals()" class="w-full text-right text-xs bg-slate-800 border border-slate-700 rounded-lg p-1.5 text-white font-mono focus:border-teal-400">
                                        <input type="hidden" name="tax_amount" id="pos_tax_amount" value="0.00">
                                    </div>
                                </div>
                            </div>

                            <!-- Net Amount Display -->
                            <div class="bg-white/10 rounded-xl p-3.5 border border-white/10 flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-teal-300">Net Payable Amount</div>
                                    <div class="text-2xl sm:text-3xl font-black font-mono text-emerald-300" id="pos-summary-net">Rs. 0.00</div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 text-[10px] font-bold uppercase">Ready to Bill</span>
                                </div>
                            </div>

                            <!-- Cash Register Calculations -->
                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-[11px] text-slate-300 mb-1">Amount Tendered</label>
                                    <input type="number" step="1" min="0" name="amount_tendered" id="pos_amount_tendered" placeholder="0.00" oninput="calculatePosTotals()" class="w-full text-xs bg-slate-800 border border-slate-700 rounded-lg p-2 text-emerald-300 font-mono font-bold focus:border-emerald-400">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-slate-300 mb-1">Change Return</label>
                                    <div id="pos-change-display" class="w-full text-xs bg-slate-900 border border-slate-800 rounded-lg p-2 font-mono font-bold text-amber-300">Rs. 0.00</div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment & Final Checkout Details -->
                        <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Payment Method</label>
                                <select name="payment_method" id="pos_payment_method" class="w-full text-xs border border-slate-300 rounded-lg p-2.5 bg-white focus:ring-2 focus:ring-teal-500 font-semibold text-slate-800">
                                    <option value="Cash">💵 Cash Counter</option>
                                    <option value="Card">💳 Credit / Debit Card (POS Terminal)</option>
                                    <option value="Mobile_Wallet">📱 Mobile Wallet (Easypaisa / JazzCash / Raast)</option>
                                    <option value="Bank_Transfer">🏦 Direct Bank Transfer</option>
                                    <option value="Bill_to_Encounter">📋 Bill to Active Patient Encounter / IPD</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Transaction Ref / Counter Notes (Optional)</label>
                                <input type="text" name="sale_notes" placeholder="e.g. Card Auth #98124 or OPD Token #45" class="w-full text-xs border border-slate-300 rounded-lg p-2 bg-white focus:ring-2 focus:ring-teal-500">
                            </div>

                            <!-- Big Submit Button -->
                            <button type="submit" id="btn-pos-submit" disabled class="w-full py-3 bg-gradient-to-r from-teal-600 via-emerald-600 to-teal-700 hover:from-teal-700 hover:to-emerald-800 text-white font-bold text-sm rounded-xl shadow-md transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-receipt"></i>
                                <span>Complete Dispensation &amp; Print (F9)</span>
                            </button>
                            <p class="text-[10px] text-center text-slate-400">Inventory stock is automatically depleted via FEFO &bull; Keyboard shortcut: <kbd class="px-1.5 py-0.5 bg-slate-200 rounded text-slate-700 font-mono">F9</kbd></p>
                        </div>

                    </div>
                </div>
            </form>

            <!-- Bottom: Recent Out-Patient Dispensations Ledger -->
            <div class="border-t border-slate-200 bg-slate-50/50 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <i class="fa-solid fa-clock-rotate-left text-teal-600"></i>
                        <span>Recent Out-Patient Dispensations</span>
                    </h4>
                    <span class="text-[11px] text-slate-500">Showing latest transactions</span>
                </div>

                <div class="overflow-x-auto bg-white rounded-xl border border-slate-200 shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-[10px] font-bold border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-2.5">Invoice #</th>
                                <th class="px-4 py-2.5">Customer / Patient</th>
                                <th class="px-4 py-2.5">Dispensed Meds</th>
                                <th class="px-4 py-2.5 text-right">Net Amount</th>
                                <th class="px-4 py-2.5">Method</th>
                                <th class="px-4 py-2.5">Dispensed By</th>
                                <th class="px-4 py-2.5 text-center">Receipt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($recentDispensations)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-400">No recent outpatient dispensations recorded yet.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentDispensations as $rd): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-4 py-2.5 font-mono font-bold text-teal-700">
                                    <?= htmlspecialchars($rd['sale_invoice_number']) ?>
                                    <span class="text-[10px] text-slate-400 block font-normal"><?= format_datetime($rd['created_at']) ?></span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($rd['customer_name'] ?? 'Walk-in') ?></div>
                                    <?php if (!empty($rd['mrn'])): ?>
                                        <span class="font-mono text-[10px] text-teal-700 bg-teal-50 px-1.5 py-0.2 rounded border border-teal-200"><?= htmlspecialchars($rd['mrn']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2.5 max-w-xs truncate text-[11px] text-slate-600" title="<?= htmlspecialchars($rd['items_summary'] ?? '') ?>">
                                    <?= htmlspecialchars($rd['items_summary'] ?? ($rd['item_count'] . ' item(s)')) ?>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900">
                                    <?= format_currency($rd['net_total']) ?>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                        <?= htmlspecialchars(str_replace('_', ' ', $rd['payment_method'])) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 text-[11px]">
                                    <?= htmlspecialchars($rd['pharmacist_name']) ?>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="index.php?page=print_pos_receipt&sale_id=<?= $rd['id'] ?>&mode=thermal&autoprint=1" target="_blank" class="px-2 py-1 bg-teal-50 hover:bg-teal-100 text-teal-700 rounded text-[10px] font-bold transition-colors" title="Print 80mm Slip">
                                            <i class="fa-solid fa-receipt mr-0.5"></i> 80mm
                                        </a>
                                        <a href="index.php?page=print_pos_receipt&sale_id=<?= $rd['id'] ?>&mode=standard" target="_blank" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-bold transition-colors" title="Print A4 Invoice">
                                            <i class="fa-solid fa-file-invoice mr-0.5"></i> A4
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

    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 2: PRESCRIPTION DISPENSE QUEUE                                      -->
    <!-- ======================================================================= -->
    <?php if ($activeTab === 'queue'): ?>
    <div class="space-y-4">

        <!-- Search Bar -->
        <form action="index.php" method="GET" class="flex items-center gap-3">
            <input type="hidden" name="page" value="pharmacy">
            <input type="hidden" name="tab" value="queue">
            <div class="relative flex-1 max-w-md">
                <input type="text" name="search" value="<?= htmlspecialchars($rxSearch) ?>" placeholder="Scan Rx Token (RX-XXXX), MRN, or Patient Name..." class="w-full pl-9 pr-3 py-2 text-xs bg-white border border-slate-200 rounded-xl outline-none focus:border-teal-500 font-mono shadow-xs">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
            </div>
            <?php if (!empty($rxSearch)): ?>
                <a href="index.php?page=pharmacy&tab=queue" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 font-semibold">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($prescriptions)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
                <i class="fa-solid fa-prescription text-3xl mb-2 text-slate-300"></i>
                <p>No prescriptions found in dispensing queue.</p>
            </div>
        <?php else: ?>
            <?php foreach ($prescriptions as $rx): 
                $itemsStmt = $db->prepare("
                    SELECT pi.*, di.brand_name, di.generic_name, di.strength, di.dosage_form
                    FROM `prescription_items` pi
                    JOIN `drug_items` di ON pi.drug_item_id = di.id
                    WHERE pi.prescription_id = ?
                ");
                $itemsStmt->execute([$rx['id']]);
                $rxItems = $itemsStmt->fetchAll();
                $isDispensed = ($rx['status'] === 'Dispensed');
            ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 transition-all">
                <div class="flex flex-col md:flex-row md:items-center justify-between pb-3 border-b border-slate-100 gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl <?= $isDispensed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?> flex items-center justify-center text-base font-bold">
                            <i class="fa-solid <?= $isDispensed ? 'fa-check' : 'fa-clock' ?>"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-sm">
                                    <a href="index.php?page=patient_profile&id=<?= $rx['patient_id'] ?>" class="hover:text-teal-600">
                                        <?= htmlspecialchars($rx['first_name'] . ' ' . $rx['last_name']) ?>
                                    </a>
                                </span>
                                <span class="font-mono text-xs text-teal-800 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded font-semibold">
                                    <?= htmlspecialchars($rx['mrn']) ?>
                                </span>
                                <span class="font-mono text-xs text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                                    <?= htmlspecialchars($rx['prescription_number']) ?>
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $rx['encounter_type'] === 'Emergency' ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-700' ?>">
                                    <?= htmlspecialchars($rx['encounter_type']) ?>
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5">
                                Prescribed by <strong><?= htmlspecialchars($rx['doctor_name']) ?></strong> &bull; <?= format_datetime($rx['created_at']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $isDispensed ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                            <?= htmlspecialchars($rx['status']) ?>
                        </span>
                        <a href="index.php?page=pharmacy&tab=queue&print_receipt=<?= $rx['id'] ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg flex items-center gap-1.5 transition-colors" title="Print Pharmacy Dispensation Receipt">
                            <i class="fa-solid fa-receipt text-slate-500 text-[11px]"></i>
                            <span>Receipt</span>
                        </a>
                        <?php if (!$isDispensed): ?>
                        <form action="index.php?action=pharmacy_dispense" method="POST" onsubmit="return confirm('Dispense this prescription and deduct stock via FEFO?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="prescription_id" value="<?= $rx['id'] ?>">
                            <button type="submit" class="px-4 py-1.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                                <i class="fa-solid fa-box-open text-[10px]"></i>
                                <span>Verify &amp; Dispense</span>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Prescribed Items Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 mt-3">
                    <?php foreach ($rxItems as $item): ?>
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                        <div class="font-bold text-slate-900"><?= htmlspecialchars($item['brand_name']) ?></div>
                        <div class="text-[11px] text-slate-500"><?= htmlspecialchars($item['generic_name']) ?> (<?= htmlspecialchars($item['strength']) ?>)</div>
                        <div class="mt-2 text-xs font-mono font-semibold text-teal-800">
                            <?= htmlspecialchars($item['dosage']) ?> &bull; <?= htmlspecialchars($item['frequency']) ?> &bull; <?= $item['duration_days'] ?> Days
                        </div>
                        <div class="text-[10px] text-slate-500 mt-1">Qty Prescribed: <strong><?= $item['quantity_prescribed'] ?></strong> &bull; <?= htmlspecialchars($item['instructions']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 3: MEDICINE STOCK & BATCHES (FORMULARY FEFO)                         -->
    <!-- ======================================================================= -->
    <?php if ($activeTab === 'stock'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        
        <!-- Header & Filter Pills -->
        <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50/60">
            <div class="flex flex-wrap items-center gap-2">
                <a href="index.php?page=pharmacy&tab=stock&stock_filter=all" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $stockFilter === 'all' ? 'bg-teal-700 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' ?>">
                    All Batches (<?= (int)($batchCounts['total_batches'] ?? 0) ?>)
                </a>
                <a href="index.php?page=pharmacy&tab=stock&stock_filter=narcotics" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $stockFilter === 'narcotics' ? 'bg-purple-700 text-white shadow-xs' : 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100' ?>">
                    <i class="fa-solid fa-skull mr-1 text-purple-400"></i> Controlled / Narcotics (<?= (int)($batchCounts['narcotics_batches'] ?? 0) ?>)
                </a>
                <a href="index.php?page=pharmacy&tab=stock&stock_filter=near_expiry" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $stockFilter === 'near_expiry' ? 'bg-amber-500 text-white shadow-xs' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100' ?>">
                    <i class="fa-solid fa-clock-rotate-left mr-1 text-amber-500"></i> Near-Expiry &lt;90d (<?= (int)($batchCounts['near_expiry_batches'] ?? 0) ?>)
                </a>
                <a href="index.php?page=pharmacy&tab=stock&stock_filter=expired" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $stockFilter === 'expired' ? 'bg-rose-700 text-white shadow-xs' : 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100' ?>">
                    <i class="fa-solid fa-ban mr-1 text-rose-400"></i> Expired (<?= (int)($batchCounts['expired_batches'] ?? 0) ?>)
                </a>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="document.getElementById('modal-single-batch').classList.remove('hidden')" class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-lg text-xs font-semibold shadow-2xs">
                    <i class="fa-solid fa-plus text-teal-600 mr-1"></i> Add Batch
                </button>
                <a href="index.php?page=pharmacy&tab=bulk_entry" class="px-3 py-1 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-2xs">
                    <i class="fa-solid fa-table-cells mr-1"></i> Bulk Entry
                </a>
                <span class="text-xs text-slate-500 font-mono ml-2"><?= count($batches) ?> Batches</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5">Medicine Name</th>
                        <th class="px-4 py-3.5">Category &amp; Storage</th>
                        <th class="px-4 py-3.5">Batch #</th>
                        <th class="px-4 py-3.5">Manufacturer</th>
                        <th class="px-4 py-3.5">Expiry Date (FEFO)</th>
                        <th class="px-4 py-3.5">Available Qty</th>
                        <th class="px-4 py-3.5">Purchase Cost</th>
                        <th class="px-4 py-3.5">Unit Sale Price</th>
                        <th class="px-6 py-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($batches)): ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-slate-400">
                            No medication batches found matching the selected filter.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($batches as $b): 
                        $daysToExpiry = round((strtotime($b['expiry_date']) - time()) / 86400);
                        $isExpired = $daysToExpiry < 0;
                        $isExpiring = $daysToExpiry >= 0 && $daysToExpiry <= 90;
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors <?= $isExpired ? 'bg-rose-50/40' : ($isExpiring ? 'bg-amber-50/30' : '') ?>">
                        <td class="px-6 py-3.5">
                            <div class="font-bold text-slate-900 flex items-center gap-1.5 flex-wrap">
                                <span><?= htmlspecialchars($b['brand_name']) ?></span>
                                <?php if (!empty($b['is_controlled_substance'])): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                        <i class="fa-solid fa-skull mr-1 text-purple-600"></i> <?= htmlspecialchars($b['narcotic_schedule'] ?? 'Controlled') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($b['generic_name']) ?> (<?= htmlspecialchars($b['strength']) ?>)</div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-slate-700 font-semibold"><?= htmlspecialchars($b['category_name']) ?></div>
                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($b['dosage_form']) ?></div>
                            <?php if (!empty($b['storage_condition'])): ?>
                                <div class="text-[10px] text-sky-700 mt-0.5 flex items-center gap-1">
                                    <i class="fa-solid fa-snowflake text-sky-400"></i>
                                    <span><?= htmlspecialchars($b['storage_condition']) ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5 font-mono font-semibold text-slate-700"><?= htmlspecialchars($b['batch_number']) ?></td>
                        <td class="px-4 py-3.5 text-slate-600"><?= htmlspecialchars($b['manufacturer'] ?? 'Standard') ?></td>
                        <td class="px-4 py-3.5 font-mono <?= $isExpired ? 'text-rose-600 font-bold' : ($isExpiring ? 'text-amber-600 font-bold' : 'text-slate-700') ?>">
                            <?= format_date($b['expiry_date']) ?>
                            <?php if ($isExpired): ?>
                                <span class="px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 text-[9px] font-extrabold uppercase block w-fit mt-0.5">EXPIRED</span>
                            <?php elseif ($isExpiring): ?>
                                <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 text-[9px] font-bold block w-fit mt-0.5"><?= $daysToExpiry ?> days left</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-900">
                            <?= number_format($b['current_quantity']) ?> <span class="text-[10px] font-normal text-slate-400">/ <?= $b['initial_quantity'] ?></span>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-600">
                            <?= format_currency($b['unit_purchase_price']) ?>
                        </td>
                        <td class="px-4 py-3.5 font-semibold text-emerald-700 font-mono">
                            <?= format_currency($b['unit_sale_price']) ?>
                        </td>
                        <td class="px-6 py-3.5">
                            <?php if ($isExpired): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                    <i class="fa-solid fa-ban mr-0.5"></i> Quarantined
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $b['status'] === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' ?>">
                                    <?= htmlspecialchars($b['status']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 4: BULK MEDICINE STOCK ENTRY (EXCLUSIVE TO PHARMACY)                -->
    <!-- ======================================================================= -->
    <?php if ($activeTab === 'bulk_entry'): ?>
    <div class="space-y-6">

        <!-- Exclusivity Banner -->
        <div class="bg-gradient-to-r from-teal-800 to-navy-900 text-white rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-teal-400/20 text-teal-300 border border-teal-400/30 uppercase tracking-wider">
                        PHARMACY DEPARTMENT EXCLUSIVE
                    </span>
                    <span class="text-xs text-teal-200/80 font-mono">&bull; Bulk Stock Receiving Portal</span>
                </div>
                <h2 class="text-xl font-black tracking-tight">Bulk Medicine &amp; Batch Stock Entry</h2>
                <p class="text-xs text-teal-100/80 mt-0.5">
                    Fast tabular receiving of manufacturer consignments &bull; Automatic drug formulary linking &bull; Instant purchase vs. retail valuation.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="document.getElementById('csv-import-panel').classList.toggle('hidden')" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/20 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-file-csv text-teal-300"></i>
                    <span>Paste / Import CSV</span>
                </button>
                <button type="button" onclick="downloadSampleCsvTemplate()" class="px-3.5 py-2 bg-white text-slate-800 hover:bg-teal-50 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-2xs">
                    <i class="fa-solid fa-download text-teal-600"></i>
                    <span>Sample CSV Template</span>
                </button>
            </div>
        </div>

        <!-- Collapsible CSV Paste & Parse Panel -->
        <div id="csv-import-panel" class="hidden bg-slate-50 border border-slate-200 rounded-2xl p-5 shadow-2xs space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-paste text-teal-600"></i>
                    <span>Paste CSV or Tab-Separated Data from Excel/Distributor Invoice</span>
                </h4>
                <button type="button" onclick="document.getElementById('csv-import-panel').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-sm">&times;</button>
            </div>
            <p class="text-[11px] text-slate-500">
                Format: <code>Brand Name, Generic Name, Dosage Form, Strength, Batch #, Manufacturer, Expiry (YYYY-MM-DD), Purchase Cost, Sale Price, Quantity</code>
            </p>
            <textarea id="csv_paste_input" rows="4" placeholder="Augmentin 625mg, Amoxicillin+Clav, Tablet, 625mg, BT-AUG-881, GSK, 2027-06-30, 48.00, 65.00, 500&#10;Panadol 500mg, Paracetamol, Tablet, 500mg, BT-PAN-992, Haleon, 2027-12-31, 2.80, 4.00, 2000" class="w-full text-xs font-mono border border-slate-300 rounded-xl p-3 bg-white focus:ring-2 focus:ring-teal-500"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="parseAndLoadCsv()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-2xs">
                    <i class="fa-solid fa-arrow-down-short-wide"></i>
                    <span>Load Data into Bulk Grid</span>
                </button>
            </div>
        </div>

        <!-- Bulk Stock Form -->
        <form method="POST" action="index.php?action=pharmacy_bulk_stock_store" id="bulk-stock-form" onsubmit="return validateBulkForm(event)">
            <?= csrf_field() ?>

            <!-- Table Container -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-md bg-teal-600 text-white flex items-center justify-center text-xs shadow-2xs">
                            <i class="fa-solid fa-table-list"></i>
                        </span>
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Multi-Line Medicine Consignment Grid</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="addBulkRow()" class="px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 rounded-lg text-xs font-bold transition-all flex items-center gap-1">
                            <i class="fa-solid fa-plus text-teal-600"></i>
                            <span>Add Medicine Row</span>
                        </button>
                        <button type="button" onclick="clearAllBulkRows()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition-all">
                            Reset Grid
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[500px] custom-scrollbar">
                    <table class="w-full text-left text-xs" id="bulk-stock-table">
                        <thead class="bg-slate-100 text-slate-600 uppercase text-[10px] font-bold border-b border-slate-200 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 w-8 text-center">#</th>
                                <th class="px-3 py-3 min-w-[200px]">Medicine / Brand Name <span class="text-rose-500">*</span></th>
                                <th class="px-2 py-3 min-w-[140px]">Generic Formula</th>
                                <th class="px-2 py-3 min-w-[120px]">Form / Strength</th>
                                <th class="px-2 py-3 min-w-[130px]">Category</th>
                                <th class="px-2 py-3 min-w-[110px]">Batch # <span class="text-rose-500">*</span></th>
                                <th class="px-2 py-3 min-w-[120px]">Manufacturer</th>
                                <th class="px-2 py-3 min-w-[110px]">Expiry Date <span class="text-rose-500">*</span></th>
                                <th class="px-2 py-3 min-w-[90px] text-right">Cost (Rs.)</th>
                                <th class="px-2 py-3 min-w-[90px] text-right">Sale Price</th>
                                <th class="px-2 py-3 min-w-[80px] text-center">Qty <span class="text-rose-500">*</span></th>
                                <th class="px-3 py-3 min-w-[90px] text-right">Total Cost</th>
                                <th class="px-2 py-3 w-10 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="bulk-stock-tbody" class="divide-y divide-slate-100">
                            <!-- Dynamic rows appended via Javascript -->
                        </tbody>
                    </table>
                </div>

                <!-- Live Audit & Financial Strip -->
                <div class="p-5 bg-gradient-to-r from-slate-900 to-navy-950 text-white flex flex-col md:flex-row items-center justify-between gap-4 border-t border-slate-800">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 w-full md:w-auto">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Total Batches</div>
                            <div class="text-lg font-black font-mono text-teal-300" id="audit-total-rows">0</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Total Quantity</div>
                            <div class="text-lg font-black font-mono text-white" id="audit-total-qty">0 Units</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Purchase Valuation</div>
                            <div class="text-lg font-black font-mono text-amber-300" id="audit-total-cost">Rs. 0.00</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Projected Retail</div>
                            <div class="text-lg font-black font-mono text-emerald-300" id="audit-total-retail">Rs. 0.00</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                        <button type="button" onclick="addBulkRow()" class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition-all border border-white/20 flex items-center gap-1.5">
                            <i class="fa-solid fa-plus"></i>
                            <span>Add Row</span>
                        </button>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white rounded-xl text-xs font-black shadow-lg shadow-teal-900/30 transition-all flex items-center gap-2">
                            <i class="fa-solid fa-boxes-packing"></i>
                            <span>Receive &amp; Store Batches</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </div>
    <?php endif; ?>

</div>

<!-- ======================================================================= -->
<!-- MODAL: ADD SINGLE MEDICINE BATCH (PHARMACY EXCLUSIVE)                   -->
<!-- ======================================================================= -->
<div id="modal-single-batch" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4 overflow-x-hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 bg-teal-700 text-white flex items-center justify-between flex-shrink-0">
            <div>
                <h3 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-pills"></i>
                    <span>Receive Medicine Batch into Pharmacy</span>
                </h3>
                <p class="text-xs text-teal-100">Add a single consignment batch to existing formulary medication.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-single-batch').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=pharmacy_stock_store" class="p-6 space-y-4 overflow-y-auto custom-scrollbar flex-1">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Select Medicine from Formulary <span class="text-rose-500">*</span></label>
                <select name="drug_item_id" required class="w-full text-xs border border-slate-300 rounded-lg p-2.5 bg-white focus:ring-2 focus:ring-teal-500 font-semibold">
                    <option value="">-- Choose Medication --</option>
                    <?php foreach ($allDrugItems as $di): ?>
                    <option value="<?= $di['id'] ?>">
                        <?= htmlspecialchars($di['brand_name']) ?> (<?= htmlspecialchars($di['dosage_form'] . ' ' . $di['strength']) ?>) &bull; <?= htmlspecialchars($di['generic_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Batch Number <span class="text-rose-500">*</span></label>
                    <input type="text" name="batch_number" required placeholder="e.g. BT-2026-09" class="w-full text-xs border border-slate-300 rounded-lg p-2 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Manufacturer / Vendor</label>
                    <input type="text" name="manufacturer" placeholder="e.g. GSK / Abbott / Getz" class="w-full text-xs border border-slate-300 rounded-lg p-2">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Mfg Date (Optional)</label>
                    <input type="date" name="mfg_date" class="w-full text-xs border border-slate-300 rounded-lg p-2 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Expiry Date <span class="text-rose-500">*</span></label>
                    <input type="date" name="expiry_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" class="w-full text-xs border border-slate-300 rounded-lg p-2 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Unit Cost (Rs.)</label>
                    <input type="number" step="0.01" min="0" name="unit_purchase_price" placeholder="45.00" class="w-full text-xs border border-slate-300 rounded-lg p-2 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Unit Retail Price <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="unit_sale_price" required placeholder="65.00" class="w-full text-xs border border-slate-300 rounded-lg p-2 font-mono font-bold text-emerald-700">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Quantity Received <span class="text-rose-500">*</span></label>
                    <input type="number" min="1" name="quantity" required placeholder="100" value="100" class="w-full text-xs border border-slate-300 rounded-lg p-2 font-mono font-bold">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-single-batch').classList.add('hidden')" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-semibold hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-2xs">
                    Receive Batch
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================================= -->
<!-- MODAL: PRINTABLE TWO-PART PHARMACY RECEIPT (PRESCRIPTION QUEUE)         -->
<!-- ======================================================================= -->
<?php if ($receiptData): ?>
<div id="pharmacyReceiptModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] flex flex-col relative">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 no-print">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-receipt text-teal-600"></i>
                <span>Pharmacy Dispensing Receipt (Two-Part Slip)</span>
            </h3>
            <button onclick="document.getElementById('pharmacyReceiptModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <div id="printablePharmacyArea" class="flex-1 overflow-y-auto custom-scrollbar p-5 border-2 border-dashed border-slate-200 rounded-xl bg-white space-y-5 font-sans text-xs">
            <?php 
            $copies = ['PATIENT DISPENSATION COPY', 'PHARMACY AUDIT / DISPENSARY LEDGER COPY'];
            $grandTotal = 0;
            foreach ($receiptItems as $it) {
                $grandTotal += ($it['quantity_dispensed'] > 0 ? $it['quantity_dispensed'] : $it['quantity_prescribed']) * (float)$it['unit_price'];
            }
            foreach ($copies as $cIdx => $copyTitle): 
            ?>
            <div class="<?= $cIdx > 0 ? 'pt-4 border-t-2 border-dashed border-slate-400' : '' ?>">
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <div>
                        <div class="font-extrabold text-sm text-slate-900 uppercase tracking-tight"><?= APP_NAME ?></div>
                        <div class="text-[10px] text-teal-800 font-bold uppercase">Formulary Pharmacy Dispensary</div>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[9px] font-bold block"><?= $copyTitle ?></span>
                        <span class="font-mono text-[10px] text-slate-500 font-bold"><?= htmlspecialchars($receiptData['prescription_number']) ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 py-2 text-[10px] border-b border-slate-100">
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">Patient:</span>
                        <strong class="text-slate-900"><?= htmlspecialchars($receiptData['first_name'] . ' ' . $receiptData['last_name']) ?></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">MRN:</span>
                        <span class="font-mono text-teal-800 font-bold"><?= htmlspecialchars($receiptData['mrn']) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">Doctor:</span>
                        <span class="text-slate-800"><?= htmlspecialchars($receiptData['doctor_name']) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">Date &amp; Time:</span>
                        <span class="text-slate-700"><?= format_datetime($receiptData['created_at']) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">Encounter:</span>
                        <span class="font-mono text-slate-700"><?= htmlspecialchars($receiptData['encounter_number']) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">Status:</span>
                        <span class="font-bold text-emerald-700"><?= htmlspecialchars($receiptData['status']) ?></span>
                    </div>
                </div>

                <table class="w-full text-left text-[11px] mt-2">
                    <thead class="border-b border-slate-200 text-[9px] uppercase text-slate-500 font-bold">
                        <tr>
                            <th class="py-1">Medication Item</th>
                            <th class="py-1 text-center">Qty</th>
                            <th class="py-1 text-right">Unit Price</th>
                            <th class="py-1 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($receiptItems as $item): 
                            $qty = ($item['quantity_dispensed'] > 0 ? $item['quantity_dispensed'] : $item['quantity_prescribed']);
                            $lineTotal = $qty * (float)$item['unit_price'];
                        ?>
                        <tr>
                            <td class="py-1">
                                <span class="font-semibold text-slate-900"><?= htmlspecialchars($item['brand_name']) ?></span>
                                <span class="text-[9px] text-slate-400 block"><?= htmlspecialchars($item['generic_name']) ?> (<?= htmlspecialchars($item['dosage']) ?> &bull; <?= htmlspecialchars($item['frequency']) ?>)</span>
                            </td>
                            <td class="py-1 text-center font-bold text-slate-800"><?= $qty ?></td>
                            <td class="py-1 text-right font-mono text-slate-600"><?= format_currency($item['unit_price']) ?></td>
                            <td class="py-1 text-right font-mono font-bold text-slate-900"><?= format_currency($lineTotal) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-800">
                        <tr>
                            <th colspan="3" class="py-1.5 text-right font-bold text-slate-800 uppercase text-[10px]">Net Medication Amount:</th>
                            <th class="py-1.5 text-right font-mono font-black text-slate-900 text-xs"><?= format_currency($grandTotal) ?></th>
                        </tr>
                    </tfoot>
                </table>

                <div class="pt-2 flex justify-between items-center text-[9px] text-slate-400">
                    <span>Medicines once dispensed cannot be returned. Keep below 25°C.</span>
                    <span class="border-t border-slate-400 pt-0.5 text-slate-700 font-semibold">Pharmacist Stamp / Signature</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-end gap-2 no-print">
            <button type="button" onclick="document.getElementById('pharmacyReceiptModal').classList.add('hidden')" class="px-4 py-1.5 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                Close
            </button>
            <button type="button" onclick="printPharmacyReceiptDoc()" class="px-5 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-sm flex items-center gap-1.5">
                <i class="fa-solid fa-print text-[11px]"></i>
                <span>Print Two-Part Dispensing Receipt</span>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================================= -->
<!-- JAVASCRIPT: INTERACTIVE OPD DISPENSARY CART & BULK STOCK MANAGER        -->
<!-- ======================================================================= -->
<script>
// Live Clock
function updateClock() {
    const el = document.getElementById('dispensary-clock');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString();
    }
}
setInterval(updateClock, 1000);
updateClock();

// Pass JSON Maps to JS
const pendingRxMap = <?= json_encode($pendingRxItemsMap) ?>;
const formularyList = <?= json_encode($allDrugItems) ?>;
const drugCategoriesList = <?= json_encode($drugCategories) ?>;

// ----------------------------------------------------------------------------
// A. OPD DISPENSARY LIVE CART LOGIC
// ----------------------------------------------------------------------------
let cartItems = [];

function toggleCustomerType(mode) {
    const regDiv = document.getElementById('patient-registered-fields');
    const walkDiv = document.getElementById('patient-walkin-fields');
    const nameInput = document.getElementById('pos_customer_name');

    if (mode === 'registered') {
        regDiv.classList.remove('hidden');
        walkDiv.classList.add('hidden');
        nameInput.value = '';
    } else {
        regDiv.classList.add('hidden');
        walkDiv.classList.remove('hidden');
        if (!nameInput.value) nameInput.value = 'Walk-in Customer';
        document.getElementById('pos_patient_id').value = '';
    }
}

function onRegisteredPatientSelect(select) {
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('pos_customer_name').value = opt.getAttribute('data-name') || '';
        document.getElementById('pos_customer_phone').value = opt.getAttribute('data-phone') || '';
    }
}

function addSelectedDrugToCart() {
    const sel = document.getElementById('pos_drug_selector');
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) {
        alert('Please choose an in-stock medication to add.');
        return;
    }

    const batchId = parseInt(opt.getAttribute('data-batch-id'));
    const brand = opt.getAttribute('data-brand');
    const form = opt.getAttribute('data-form');
    const batchNumber = opt.getAttribute('data-batch-number');
    const expiry = opt.getAttribute('data-expiry');
    const stock = parseInt(opt.getAttribute('data-stock'));
    const price = parseFloat(opt.getAttribute('data-price'));

    addItemToCart(batchId, brand, form, batchNumber, expiry, stock, price, 1, '1 tab TDS pc (دن میں 3 بار کھانے کے بعد)');
    sel.selectedIndex = 0;
}

function addQuickMed(batchId, brand, price, form, batchNumber, expiry, stock) {
    addItemToCart(batchId, brand, form, batchNumber, expiry, stock, price, 1, 'As directed (حسبِ ہدایت)');
}

function addItemToCart(batchId, brand, form, batchNumber, expiry, stock, price, qty = 1, instructions = 'As directed') {
    const existing = cartItems.find(it => it.batchId === batchId);
    if (existing) {
        if (existing.qty + qty > stock) {
            alert(`Cannot add more units. Available stock is ${stock}.`);
            return;
        }
        existing.qty += qty;
    } else {
        if (qty > stock) {
            alert(`Cannot add ${qty} units. Available stock is only ${stock}.`);
            return;
        }
        cartItems.push({
            batchId: batchId,
            brand: brand,
            form: form,
            batchNumber: batchNumber,
            expiry: expiry,
            stock: stock,
            price: price,
            qty: qty,
            instructions: instructions
        });
    }
    renderCart();
}

function triggerLoadRx() {
    const rxSelect = document.getElementById('pos_load_rx_select');
    const rxId = parseInt(rxSelect.value);
    if (!rxId || !pendingRxMap[rxId]) {
        alert('Please select a valid pending prescription.');
        return;
    }

    const items = pendingRxMap[rxId];
    document.getElementById('pos_loaded_prescription_id').value = rxId;

    // Set Patient Mode to registered
    const radioReg = document.querySelector('input[name="customer_type"][value="registered"]');
    if (radioReg) {
        radioReg.checked = true;
        toggleCustomerType('registered');
    }

    // Try finding patient
    const opt = rxSelect.options[rxSelect.selectedIndex].text;
    const mrnMatch = opt.match(/MRN:\s*([^)]+)/);
    if (mrnMatch) {
        const mrn = mrnMatch[1].trim();
        const patientSelect = document.getElementById('pos_patient_id');
        for (let i = 0; i < patientSelect.options.length; i++) {
            if (patientSelect.options[i].text.includes(mrn)) {
                patientSelect.selectedIndex = i;
                onRegisteredPatientSelect(patientSelect);
                break;
            }
        }
    }

    // Populate cart
    cartItems = [];
    let loadedCount = 0;

    items.forEach(it => {
        if (it.matched_batch_id && parseInt(it.matched_stock) > 0) {
            const reqQty = parseInt(it.quantity_prescribed) || 1;
            const availStock = parseInt(it.matched_stock);
            const finalQty = Math.min(reqQty, availStock);
            const instr = `${it.dosage || '1 dose'} - ${it.frequency || 'OD'} (${it.instructions || 'As directed'})`;

            cartItems.push({
                batchId: parseInt(it.matched_batch_id),
                brand: it.brand_name,
                form: `${it.dosage_form || ''} ${it.strength || ''}`,
                batchNumber: it.matched_batch_number,
                expiry: it.matched_expiry_date,
                stock: availStock,
                price: parseFloat(it.matched_price) || 0.00,
                qty: finalQty,
                instructions: instr
            });
            loadedCount++;
        }
    });

    renderCart();

    const alertBox = document.getElementById('pos-rx-loaded-alert');
    const alertText = document.getElementById('pos-rx-loaded-text');
    alertText.textContent = `Prescription loaded with ${loadedCount} in-stock FEFO items into cart.`;
    alertBox.classList.remove('hidden');
}

function renderCart() {
    const tbody = document.getElementById('pos-cart-tbody');
    const emptyRow = document.getElementById('pos-cart-empty-row');
    const badge = document.getElementById('pos-cart-badge');
    const submitBtn = document.getElementById('btn-pos-submit');

    if (cartItems.length === 0) {
        tbody.innerHTML = `
            <tr id="pos-cart-empty-row">
                <td colspan="7" class="py-10 text-center text-slate-400">
                    <i class="fa-solid fa-cart-arrow-down text-3xl mb-2 text-slate-300"></i>
                    <p class="font-medium text-xs">Dispensary cart is empty.</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Select a medicine above or click a Quick-Tap item to begin.</p>
                </td>
            </tr>
        `;
        badge.textContent = '0 Items';
        submitBtn.disabled = true;
        calculatePosTotals();
        return;
    }

    submitBtn.disabled = false;
    badge.textContent = `${cartItems.length} Item${cartItems.length > 1 ? 's' : ''}`;

    let html = '';
    cartItems.forEach((it, idx) => {
        const lineTotal = (it.qty * it.price).toFixed(2);
        html += `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-3 py-2.5">
                    <div class="font-bold text-slate-900">${escapeHtml(it.brand)}</div>
                    <div class="text-[10px] text-slate-500 font-mono">
                        <span class="bg-teal-50 text-teal-800 px-1 rounded font-bold">${escapeHtml(it.batchNumber)}</span> &bull; Exp: ${it.expiry}
                    </div>
                    <input type="hidden" name="item_batch_id[]" value="${it.batchId}">
                </td>
                <td class="px-2 py-2.5 text-center font-mono font-bold text-slate-600">
                    ${it.stock}
                </td>
                <td class="px-2 py-2.5 text-center">
                    <input type="number" name="item_quantity[]" value="${it.qty}" min="1" max="${it.stock}" 
                           onchange="updateCartQty(${idx}, this.value)" 
                           class="w-16 text-center text-xs border border-slate-300 rounded p-1 font-bold font-mono focus:border-teal-500">
                </td>
                <td class="px-2 py-2.5 text-right font-mono">
                    <input type="number" step="0.50" min="0" name="item_unit_price[]" value="${it.price.toFixed(2)}" 
                           onchange="updateCartPrice(${idx}, this.value)" 
                           class="w-20 text-right text-xs border border-slate-300 rounded p-1 font-mono text-emerald-700 font-semibold focus:border-teal-500">
                </td>
                <td class="px-3 py-2.5">
                    <input type="text" name="item_dosage_instructions[]" value="${escapeHtml(it.instructions)}" 
                           placeholder="Dosage directions..." 
                           class="w-full text-[11px] border border-slate-200 rounded p-1 text-slate-700 focus:border-teal-500">
                </td>
                <td class="px-3 py-2.5 text-right font-mono font-bold text-slate-900">
                    Rs. ${lineTotal}
                </td>
                <td class="px-2 py-2.5 text-center">
                    <button type="button" onclick="removeCartItem(${idx})" class="text-slate-400 hover:text-rose-600 p-1 transition-colors" title="Remove item">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    calculatePosTotals();
}

function updateCartQty(idx, val) {
    const qty = parseInt(val) || 1;
    if (qty > cartItems[idx].stock) {
        alert(`Stock limit reached! Available stock is ${cartItems[idx].stock}.`);
        cartItems[idx].qty = cartItems[idx].stock;
    } else {
        cartItems[idx].qty = Math.max(1, qty);
    }
    renderCart();
}

function updateCartPrice(idx, val) {
    const price = parseFloat(val) || 0.00;
    cartItems[idx].price = Math.max(0, price);
    renderCart();
}

function removeCartItem(idx) {
    cartItems.splice(idx, 1);
    renderCart();
}

function clearPosCart() {
    if (cartItems.length > 0 && confirm('Are you sure you want to clear all items from the dispensary cart?')) {
        cartItems = [];
        renderCart();
    }
}

function calculatePosTotals() {
    let subtotal = 0.00;
    cartItems.forEach(it => {
        subtotal += (it.qty * it.price);
    });

    const discountInput = document.getElementById('pos_discount_amount');
    const taxRateInput = document.getElementById('pos_tax_rate');
    const tenderedInput = document.getElementById('pos_amount_tendered');

    const discount = parseFloat(discountInput ? discountInput.value : 0) || 0.00;
    const taxRate = parseFloat(taxRateInput ? taxRateInput.value : 0) || 0.00;
    const taxAmount = (subtotal - discount > 0) ? ((subtotal - discount) * (taxRate / 100)) : 0.00;

    const net = Math.max(0, subtotal - discount + taxAmount);
    const tendered = parseFloat(tenderedInput ? tenderedInput.value : 0) || 0.00;
    const change = (tendered > net) ? (tendered - net) : 0.00;

    const taxAmountHidden = document.getElementById('pos_tax_amount');
    if (taxAmountHidden) taxAmountHidden.value = taxAmount.toFixed(2);

    const subEl = document.getElementById('pos-summary-subtotal');
    const netEl = document.getElementById('pos-summary-net');
    const changeEl = document.getElementById('pos-change-display');

    if (subEl) subEl.textContent = `Rs. ${subtotal.toFixed(2)}`;
    if (netEl) netEl.textContent = `Rs. ${net.toFixed(2)}`;
    if (changeEl) changeEl.textContent = `Rs. ${change.toFixed(2)}`;
}

function validatePosCheckout(e) {
    if (cartItems.length === 0) {
        alert('Cannot complete checkout: Cart has no items.');
        e.preventDefault();
        return false;
    }
    return true;
}

// ----------------------------------------------------------------------------
// B. BULK MEDICINE STOCK ENTRY GRID MANAGER
// ----------------------------------------------------------------------------
let bulkRows = [];

function initBulkGrid() {
    const tbody = document.getElementById('bulk-stock-tbody');
    if (!tbody) return;
    bulkRows = [];
    for (let i = 0; i < 5; i++) {
        addBulkRow(false);
    }
    renderBulkRows();
}

function addBulkRow(shouldRender = true) {
    const rowId = 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    bulkRows.push({
        id: rowId,
        drug_id: '',
        brand_name: '',
        generic_name: '',
        category_id: 1,
        dosage_form: 'Tablet',
        strength: '500mg',
        batch_number: 'BT-' + new Date().getFullYear() + '-' + String(bulkRows.length + 1).padStart(3, '0'),
        manufacturer: 'Standard Pharma',
        expiry_date: getOneYearAheadDate(),
        purchase_price: 25.00,
        sale_price: 40.00,
        quantity: 100
    });
    if (shouldRender) renderBulkRows();
}

function getOneYearAheadDate() {
    const d = new Date();
    d.setFullYear(d.getFullYear() + 1);
    return d.toISOString().split('T')[0];
}

function removeBulkRow(idx) {
    bulkRows.splice(idx, 1);
    if (bulkRows.length === 0) addBulkRow(false);
    renderBulkRows();
}

function clearAllBulkRows() {
    if (confirm('Reset bulk grid to empty rows?')) {
        initBulkGrid();
    }
}

function onBulkDrugSelect(idx, selectEl) {
    const drugId = parseInt(selectEl.value);
    if (drugId > 0) {
        const item = formularyList.find(d => d.id == drugId);
        if (item) {
            bulkRows[idx].drug_id = item.id;
            bulkRows[idx].brand_name = item.brand_name;
            bulkRows[idx].generic_name = item.generic_name;
            bulkRows[idx].dosage_form = item.dosage_form;
            bulkRows[idx].strength = item.strength;
            bulkRows[idx].category_id = item.category_id || 1;
            renderBulkRows();
        }
    }
}

function renderBulkRows() {
    const tbody = document.getElementById('bulk-stock-tbody');
    if (!tbody) return;

    let html = '';
    let totalQty = 0;
    let totalCost = 0.00;
    let totalRetail = 0.00;

    bulkRows.forEach((r, idx) => {
        const lineCost = (r.quantity * r.purchase_price);
        const lineRetail = (r.quantity * r.sale_price);
        totalQty += (parseInt(r.quantity) || 0);
        totalCost += lineCost;
        totalRetail += lineRetail;

        html += `
            <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="px-3 py-2 text-center text-slate-400 font-mono text-[11px]">${idx + 1}</td>
                <td class="px-3 py-2">
                    <div class="space-y-1">
                        <select onchange="onBulkDrugSelect(${idx}, this)" class="w-full text-[11px] border border-slate-200 rounded p-1 bg-white font-medium text-slate-700">
                            <option value="">-- Choose or Type New --</option>
                            ${formularyList.map(di => `<option value="${di.id}" ${di.id == r.drug_id ? 'selected' : ''}>${escapeHtml(di.brand_name)} (${escapeHtml(di.strength)})</option>`).join('')}
                        </select>
                        <input type="text" name="brand_name[]" value="${escapeHtml(r.brand_name)}" placeholder="Brand / Medicine Name *" 
                               oninput="bulkRows[${idx}].brand_name = this.value" required
                               class="w-full text-xs font-bold border border-slate-300 rounded p-1.5 focus:border-teal-500">
                        <input type="hidden" name="drug_item_id[]" value="${r.drug_id || 0}">
                    </div>
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="generic_name[]" value="${escapeHtml(r.generic_name)}" placeholder="Active Formula" 
                           oninput="bulkRows[${idx}].generic_name = this.value"
                           class="w-full text-xs border border-slate-200 rounded p-1.5 text-slate-700">
                </td>
                <td class="px-2 py-2">
                    <div class="space-y-1">
                        <select name="dosage_form[]" onchange="bulkRows[${idx}].dosage_form = this.value" class="w-full text-[11px] border border-slate-200 rounded p-1 bg-white">
                            <option value="Tablet" ${r.dosage_form === 'Tablet' ? 'selected' : ''}>Tablet</option>
                            <option value="Capsule" ${r.dosage_form === 'Capsule' ? 'selected' : ''}>Capsule</option>
                            <option value="Syrup" ${r.dosage_form === 'Syrup' ? 'selected' : ''}>Syrup</option>
                            <option value="Injection" ${r.dosage_form === 'Injection' ? 'selected' : ''}>Injection</option>
                            <option value="Infusion" ${r.dosage_form === 'Infusion' ? 'selected' : ''}>Infusion</option>
                            <option value="Ointment" ${r.dosage_form === 'Ointment' ? 'selected' : ''}>Ointment</option>
                            <option value="Eye_Drops" ${r.dosage_form === 'Eye_Drops' ? 'selected' : ''}>Drops</option>
                            <option value="Inhaler" ${r.dosage_form === 'Inhaler' ? 'selected' : ''}>Inhaler</option>
                        </select>
                        <input type="text" name="strength[]" value="${escapeHtml(r.strength)}" placeholder="e.g. 500mg" 
                               oninput="bulkRows[${idx}].strength = this.value"
                               class="w-full text-xs border border-slate-200 rounded p-1 text-slate-700">
                    </div>
                </td>
                <td class="px-2 py-2">
                    <select name="category_id[]" onchange="bulkRows[${idx}].category_id = parseInt(this.value)" class="w-full text-[11px] border border-slate-200 rounded p-1.5 bg-white">
                        ${drugCategoriesList.map(c => `<option value="${c.id}" ${c.id == r.category_id ? 'selected' : ''}>${escapeHtml(c.category_name)}</option>`).join('')}
                    </select>
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="batch_number[]" value="${escapeHtml(r.batch_number)}" placeholder="Batch # *" 
                           oninput="bulkRows[${idx}].batch_number = this.value" required
                           class="w-full text-xs font-mono font-bold border border-slate-300 rounded p-1.5 text-teal-800">
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="manufacturer[]" value="${escapeHtml(r.manufacturer)}" placeholder="GSK, Abbott..." 
                           oninput="bulkRows[${idx}].manufacturer = this.value"
                           class="w-full text-xs border border-slate-200 rounded p-1.5 text-slate-700">
                </td>
                <td class="px-2 py-2">
                    <input type="date" name="expiry_date[]" value="${r.expiry_date}" required 
                           min="<?= date('Y-m-d') ?>"
                           onchange="bulkRows[${idx}].expiry_date = this.value"
                           class="w-full text-xs font-mono border border-slate-300 rounded p-1.5">
                </td>
                <td class="px-2 py-2 text-right">
                    <input type="number" step="0.50" min="0" name="unit_purchase_price[]" value="${parseFloat(r.purchase_price).toFixed(2)}" 
                           oninput="bulkRows[${idx}].purchase_price = parseFloat(this.value) || 0; updateBulkAudit();"
                           class="w-20 text-right text-xs font-mono border border-slate-200 rounded p-1.5">
                </td>
                <td class="px-2 py-2 text-right">
                    <input type="number" step="0.50" min="0" name="unit_sale_price[]" value="${parseFloat(r.sale_price).toFixed(2)}" 
                           oninput="bulkRows[${idx}].sale_price = parseFloat(this.value) || 0; updateBulkAudit();"
                           class="w-20 text-right text-xs font-mono font-bold text-emerald-700 border border-slate-300 rounded p-1.5">
                </td>
                <td class="px-2 py-2 text-center">
                    <input type="number" min="1" name="quantity[]" value="${r.quantity}" required 
                           oninput="bulkRows[${idx}].quantity = parseInt(this.value) || 0; updateBulkAudit();"
                           class="w-16 text-center text-xs font-mono font-bold border border-slate-300 rounded p-1.5">
                </td>
                <td class="px-3 py-2 text-right font-mono font-bold text-slate-800" id="bulk-line-cost-${idx}">
                    Rs. ${lineCost.toFixed(2)}
                </td>
                <td class="px-2 py-2 text-center">
                    <button type="button" onclick="removeBulkRow(${idx})" class="text-slate-400 hover:text-rose-600 p-1" title="Remove Row">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    updateBulkAudit();
}

function updateBulkAudit() {
    let totalQty = 0;
    let totalCost = 0.00;
    let totalRetail = 0.00;

    bulkRows.forEach((r, idx) => {
        const qty = parseInt(r.quantity) || 0;
        const cost = parseFloat(r.purchase_price) || 0;
        const sale = parseFloat(r.sale_price) || 0;
        const line = qty * cost;
        totalQty += qty;
        totalCost += line;
        totalRetail += (qty * sale);

        const lineEl = document.getElementById(`bulk-line-cost-${idx}`);
        if (lineEl) lineEl.textContent = `Rs. ${line.toFixed(2)}`;
    });

    const elRows = document.getElementById('audit-total-rows');
    const elQty = document.getElementById('audit-total-qty');
    const elCost = document.getElementById('audit-total-cost');
    const elRetail = document.getElementById('audit-total-retail');

    if (elRows) elRows.textContent = bulkRows.length;
    if (elQty) elQty.textContent = totalQty.toLocaleString() + ' Units';
    if (elCost) elCost.textContent = `Rs. ${totalCost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    if (elRetail) elRetail.textContent = `Rs. ${totalRetail.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

function parseAndLoadCsv() {
    const raw = document.getElementById('csv_paste_input').value.trim();
    if (!raw) {
        alert('Please paste CSV or Tab-separated text first.');
        return;
    }

    const lines = raw.split(/\r?\n/);
    let parsedCount = 0;

    lines.forEach(line => {
        line = line.trim();
        if (!line) return;

        // Comma or Tab delimiter
        const delimiter = line.includes('\t') ? '\t' : ',';
        const parts = line.split(delimiter).map(p => p.trim());

        if (parts.length >= 2) {
            const brand = parts[0] || '';
            const generic = parts[1] || '';
            const form = parts[2] || 'Tablet';
            const strength = parts[3] || '500mg';
            const batch = parts[4] || ('BT-' + Date.now().toString().slice(-4));
            const mfg = parts[5] || 'Standard Pharma';
            const expiry = parts[6] || getOneYearAheadDate();
            const cost = parseFloat(parts[7]) || 20.00;
            const sale = parseFloat(parts[8]) || 35.00;
            const qty = parseInt(parts[9]) || 100;

            bulkRows.push({
                id: 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000),
                drug_id: '',
                brand_name: brand,
                generic_name: generic,
                category_id: 1,
                dosage_form: form,
                strength: strength,
                batch_number: batch,
                manufacturer: mfg,
                expiry_date: expiry,
                purchase_price: cost,
                sale_price: sale,
                quantity: qty
            });
            parsedCount++;
        }
    });

    renderBulkRows();
    document.getElementById('csv-import-panel').classList.add('hidden');
    alert(`Successfully loaded ${parsedCount} medicine rows into bulk grid.`);
}

function downloadSampleCsvTemplate() {
    const csvContent = "data:text/csv;charset=utf-8," +
        "Brand Name,Generic Name,Dosage Form,Strength,Batch Number,Manufacturer,Expiry Date (YYYY-MM-DD),Purchase Cost,Retail Price,Quantity\n" +
        "Augmentin 625mg,Amoxicillin + Clavulanic Acid,Tablet,625mg,BT-AUG-2026,GSK,2027-08-30,48.00,65.00,500\n" +
        "Panadol 500mg,Paracetamol,Tablet,500mg,BT-PAN-2026,Haleon,2027-12-31,2.80,4.00,2000\n" +
        "Risek 40mg,Omeprazole,Capsule,40mg,BT-RSK-9902,Getz Pharma,2027-04-15,28.00,38.00,600\n" +
        "Loprin 75mg,Aspirin,Tablet,75mg,BT-LOP-4401,Atco Pharma,2026-11-30,2.10,3.50,1500\n";
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "pharmacy_bulk_stock_template.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function validateBulkForm(e) {
    let validRows = 0;
    for (let r of bulkRows) {
        if (r.brand_name && r.batch_number && r.expiry_date && r.quantity > 0) {
            validRows++;
        }
    }
    if (validRows === 0) {
        alert('Please fill out at least one complete medicine row with Brand Name, Batch #, Expiry Date, and Quantity.');
        e.preventDefault();
        return false;
    }
    return confirm(`Confirm receiving ${validRows} medicine batch(es) into Pharmacy Stock?`);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Keyboard Shortcut: F9 for Instant POS Checkout
window.addEventListener('keydown', function(e) {
    if (e.key === 'F9') {
        const submitBtn = document.getElementById('btn-pos-submit');
        if (submitBtn && !submitBtn.disabled) {
            e.preventDefault();
            document.getElementById('pos-sale-form').requestSubmit();
        }
    }
});

// Initialize Bulk Grid on tab load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($activeTab === 'bulk_entry'): ?>
    initBulkGrid();
    <?php endif; ?>
});
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    <?php if ($activeTab === 'bulk_entry'): ?>
    initBulkGrid();
    <?php endif; ?>
}
</script>
