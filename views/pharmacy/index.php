<?php
/**
 * Hospital Management System (HMS) - Pharmacy & Dispensing Counter
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$activeTab = clean_input($_GET['tab'] ?? 'queue');
$rxSearch = clean_input($_GET['search'] ?? '');
$printReceiptRxId = (int)($_GET['print_receipt'] ?? 0);

// Fetch Prescriptions Queue with optional search
$rxQuery = "
    SELECT p.*, e.encounter_number, e.encounter_type,
           pt.id AS patient_id, pt.first_name, pt.last_name, pt.mrn, pt.gender, pt.age,
           u.full_name AS doctor_name,
           (SELECT COUNT(*) FROM `prescription_items` WHERE `prescription_id` = p.id) AS total_items
    FROM `prescriptions` p
    JOIN `encounters` e ON p.encounter_id = e.id
    JOIN `patients` pt ON e.patient_id = pt.id
    JOIN `users` u ON p.doctor_id = u.id
";
$rxParams = [];
if (!empty($rxSearch)) {
    $rxQuery .= " WHERE p.prescription_number LIKE ? OR pt.mrn LIKE ? OR pt.first_name LIKE ? OR pt.last_name LIKE ?";
    $searchTerm = "%$rxSearch%";
    $rxParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}
$rxQuery .= " ORDER BY p.id DESC";
$rxStmt = $db->prepare($rxQuery);
$rxStmt->execute($rxParams);
$prescriptions = $rxStmt->fetchAll();

// If print receipt requested, fetch receipt details
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
                   COALESCE((SELECT unit_sale_price FROM `drug_batches` WHERE drug_item_id = di.id ORDER BY expiry_date ASC LIMIT 1), 0) AS unit_price
            FROM `prescription_items` pi
            JOIN `drug_items` di ON pi.drug_item_id = di.id
            WHERE pi.prescription_id = ?
        ");
        $recItemStmt->execute([$printReceiptRxId]);
        $receiptItems = $recItemStmt->fetchAll();
    }
}

// Fetch Drug Stock & Batches
$batches = $db->query("
    SELECT b.*, di.brand_name, di.generic_name, di.dosage_form, di.strength, di.unit_of_measure, c.category_name
    FROM `drug_batches` b
    JOIN `drug_items` di ON b.drug_item_id = di.id
    JOIN `drug_categories` c ON di.category_id = c.id
    ORDER BY b.expiry_date ASC
")->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Pharmacy & Dispensing Counter</h2>
            <p class="text-xs text-slate-500">FEFO-driven formulary stock deduction and closed-loop prescription verification</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-slate-200 flex items-center gap-6 text-xs font-semibold">
        <a href="index.php?page=pharmacy&tab=queue" class="pb-3 flex items-center gap-2 border-b-2 transition-all <?= $activeTab === 'queue' ? 'border-medical-600 text-medical-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-file-prescription"></i>
            <span>Prescription Dispense Queue</span>
        </a>
        <a href="index.php?page=pharmacy&tab=stock" class="pb-3 flex items-center gap-2 border-b-2 transition-all <?= $activeTab === 'stock' ? 'border-medical-600 text-medical-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>Drug Formulary & Batches (FEFO)</span>
        </a>
    </div>

    <!-- Tab 1: Prescriptions Dispense Queue -->
    <?php if ($activeTab === 'queue'): ?>
    <div class="space-y-4">

        <!-- Search / Scan Bar -->
        <form action="index.php" method="GET" class="flex items-center gap-3">
            <input type="hidden" name="page" value="pharmacy">
            <input type="hidden" name="tab" value="queue">
            <div class="relative flex-1 max-w-md">
                <input type="text" name="search" value="<?= htmlspecialchars($rxSearch) ?>" placeholder="Scan Rx Token (RX-XXXX), MRN, or Patient Name..." class="w-full pl-9 pr-3 py-2 text-xs bg-white border border-slate-200 rounded-xl outline-none focus:border-medical-500 font-mono shadow-xs">
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
                                    <a href="index.php?page=patient_profile&id=<?= $rx['patient_id'] ?>" class="hover:text-medical-600">
                                        <?= htmlspecialchars($rx['first_name'] . ' ' . $rx['last_name']) ?>
                                    </a>
                                </span>
                                <span class="font-mono text-xs text-medical-800 bg-medical-50 border border-medical-200 px-2 py-0.5 rounded font-semibold">
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
                            <button type="submit" class="px-4 py-1.5 bg-medical-600 hover:bg-medical-700 text-white font-bold text-xs rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                                <i class="fa-solid fa-box-open text-[10px]"></i>
                                <span>Verify & Dispense</span>
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
                        <div class="mt-2 text-xs font-mono font-semibold text-medical-800">
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

    <!-- Tab 2: Drug Formulary & Batches -->
    <?php if ($activeTab === 'stock'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-sm">Formulary Inventory Batches (FEFO Sort)</h3>
            <span class="text-xs text-slate-500 font-mono"><?= count($batches) ?> Active Batches</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5">Medicine Name</th>
                        <th class="px-4 py-3.5">Category / Form</th>
                        <th class="px-4 py-3.5">Batch #</th>
                        <th class="px-4 py-3.5">Expiry Date</th>
                        <th class="px-4 py-3.5">Available Qty</th>
                        <th class="px-4 py-3.5">Unit Sale Price</th>
                        <th class="px-6 py-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($batches as $b): 
                        $daysToExpiry = (strtotime($b['expiry_date']) - time()) / 86400;
                        $isExpiring = $daysToExpiry < 90;
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5">
                            <div class="font-bold text-slate-900"><?= htmlspecialchars($b['brand_name']) ?></div>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($b['generic_name']) ?> (<?= htmlspecialchars($b['strength']) ?>)</div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-slate-700"><?= htmlspecialchars($b['category_name']) ?></div>
                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($b['dosage_form']) ?></div>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-700"><?= htmlspecialchars($b['batch_number']) ?></td>
                        <td class="px-4 py-3.5 font-mono <?= $isExpiring ? 'text-rose-600 font-bold' : 'text-slate-700' ?>">
                            <?= format_date($b['expiry_date']) ?>
                            <?php if ($isExpiring): ?>
                                <span class="text-[10px] text-rose-500 block">Expiring soon</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-900">
                            <?= number_format($b['current_quantity']) ?> <span class="text-[10px] font-normal text-slate-400">/ <?= $b['initial_quantity'] ?></span>
                        </td>
                        <td class="px-4 py-3.5 font-semibold text-slate-800">
                            <?= format_currency($b['unit_sale_price']) ?>
                        </td>
                        <td class="px-6 py-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $b['status'] === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' ?>">
                                <?= htmlspecialchars($b['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Printable Two-Part Pharmacy Dispensing Receipt Modal -->
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

        <!-- Printable Canvas -->
        <div id="printablePharmacyArea" class="flex-1 overflow-y-auto custom-scrollbar p-5 border-2 border-dashed border-slate-200 rounded-xl bg-white space-y-5 font-sans text-xs">
            
            <?php 
            $copies = [
                'PATIENT DISPENSATION COPY',
                'PHARMACY AUDIT / DISPENSARY LEDGER COPY'
            ];
            $grandTotal = 0;
            foreach ($receiptItems as $it) {
                $grandTotal += ($it['quantity_dispensed'] > 0 ? $it['quantity_dispensed'] : $it['quantity_prescribed']) * (float)$it['unit_price'];
            }
            foreach ($copies as $cIdx => $copyTitle): 
            ?>
            <div class="<?= $cIdx > 0 ? 'pt-4 border-t-2 border-dashed border-slate-400' : '' ?>">
                <!-- Receipt Header -->
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

                <!-- Patient & Prescription Info -->
                <div class="grid grid-cols-3 gap-2 py-2 text-[10px] border-b border-slate-100">
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">Patient:</span>
                        <strong class="text-slate-900"><?= htmlspecialchars($receiptData['first_name'] . ' ' . $receiptData['last_name']) ?></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">MRN:</span>
                        <span class="font-mono text-medical-800 font-bold"><?= htmlspecialchars($receiptData['mrn']) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">Doctor:</span>
                        <span class="text-slate-800"><?= htmlspecialchars($receiptData['doctor_name']) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block uppercase font-bold">Date & Time:</span>
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

                <!-- Dispensed Items Table -->
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
                    <span>Medicines once dispensed cannot be returned. Keep at &lt; 25°C.</span>
                    <span class="border-t border-slate-400 pt-0.5 text-slate-700 font-semibold">Pharmacist Stamp / Signature</span>
                </div>
            </div>
            <?php endforeach; ?>

        </div>

        <!-- Buttons -->
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

<script>
function printPharmacyReceiptDoc() {
    const printContent = document.getElementById('printablePharmacyArea').innerHTML;
    const printWindow = window.open('', '', 'height=750,width=550');
    printWindow.document.write('<html><head><title>Pharmacy Receipt - <?= htmlspecialchars($receiptData['prescription_number'] ?? '') ?></title>');
    printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tailwindcss/typography@0.5.x/dist/typography.min.css">');
    printWindow.document.write('<style>body{font-family: sans-serif; padding: 20px;} table{width:100%; border-collapse:collapse;}</style>');
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
