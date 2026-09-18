<?php
/**
 * Hospital Management System (HMS) - Laboratory Diagnostic Workbench
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$selectedItemId = (int)($_GET['item_id'] ?? 0);
$searchQuery = clean_input($_GET['search'] ?? '');

// Fetch Lab Orders & Test Items with optional search
$orderSql = "
    SELECT loi.*, lo.order_number, lo.order_datetime, lo.priority,
           lt.test_name, lt.test_code, lt.specimen_type, lt.standard_price,
           p.id AS patient_id, p.mrn, p.first_name, p.last_name, p.gender, p.age,
           u.full_name AS doctor_name
    FROM `lab_order_items` loi
    JOIN `lab_orders` lo ON loi.lab_order_id = lo.id
    JOIN `lab_tests` lt ON loi.lab_test_id = lt.id
    JOIN `encounters` e ON lo.encounter_id = e.id
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` u ON lo.ordered_by = u.id
";
$orderParams = [];
if (!empty($searchQuery)) {
    $orderSql .= " WHERE lo.order_number LIKE ? OR p.mrn LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR loi.sample_barcode LIKE ?";
    $searchTerm = "%$searchQuery%";
    $orderParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}
$orderSql .= " ORDER BY loi.id DESC";
$stmt = $db->prepare($orderSql);
$stmt->execute($orderParams);
$orderItems = $stmt->fetchAll();

// If an item is selected for result entry, fetch its parameters
$selectedItem = null;
$parameters = [];
$existingResults = [];
$billPaymentInfo = null;

if ($selectedItemId > 0) {
    $selStmt = $db->prepare("
        SELECT loi.*, lo.order_number, lo.order_datetime, lt.test_name, lt.test_code, lt.specimen_type, lt.standard_price,
               p.id AS patient_id, p.mrn, p.first_name, p.last_name, p.gender, p.age, p.phone_primary,
               u.full_name AS doctor_name, d.department_name, e.encounter_number
        FROM `lab_order_items` loi
        JOIN `lab_orders` lo ON loi.lab_order_id = lo.id
        JOIN `lab_tests` lt ON loi.lab_test_id = lt.id
        JOIN `encounters` e ON lo.encounter_id = e.id
        JOIN `patients` p ON e.patient_id = p.id
        JOIN `users` u ON lo.ordered_by = u.id
        LEFT JOIN `departments` d ON u.department_id = d.id
        WHERE loi.id = ?
    ");
    $selStmt->execute([$selectedItemId]);
    $selectedItem = $selStmt->fetch();

    if ($selectedItem) {
        $paramStmt = $db->prepare("SELECT * FROM `lab_test_parameters` WHERE `lab_test_id` = ? ORDER BY id ASC");
        $paramStmt->execute([$selectedItem['lab_test_id']]);
        $parameters = $paramStmt->fetchAll();

        $resStmt = $db->prepare("SELECT * FROM `lab_results` WHERE `lab_order_item_id` = ?");
        $resStmt->execute([$selectedItemId]);
        foreach ($resStmt->fetchAll() as $r) {
            $existingResults[$r['parameter_id']] = $r;
        }

        // Check Payment Status for this investigation
        $payCheckStmt = $db->prepare("
            SELECT b.id, b.bill_number, b.payment_status, b.net_amount, b.paid_amount, b.balance_amount
            FROM `bills` b
            JOIN `lab_orders` lo ON lo.encounter_id = b.encounter_id
            JOIN `lab_order_items` loi ON loi.lab_order_id = lo.id
            WHERE loi.id = ?
            ORDER BY b.id DESC LIMIT 1
        ");
        $payCheckStmt->execute([$selectedItemId]);
        $billPaymentInfo = $payCheckStmt->fetch();
    }
}
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Clinical Pathology & Laboratory Workspace</h2>
            <p class="text-xs text-slate-500">Specimen intake, barcode assignment, parameter entry, and pathologist verification</p>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 1 Col: Lab Order Worklist -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 flex flex-col h-[750px] overflow-hidden">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-2 pb-2 border-b border-slate-100 flex items-center justify-between">
                <span>Diagnostic Worklist</span>
                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-mono"><?= count($orderItems) ?></span>
            </h3>

            <!-- Search / Scan Bar -->
            <form action="index.php" method="GET" class="mb-3">
                <input type="hidden" name="page" value="laboratory">
                <div class="relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Scan Barcode, LAB#, MRN..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-purple-500 font-mono">
                    <i class="fa-solid fa-barcode absolute left-2.5 top-2 text-slate-400 text-xs"></i>
                </div>
            </form>

            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1">
                <?php if (empty($orderItems)): ?>
                    <div class="text-center py-12 text-slate-400 text-xs">No lab investigations ordered.</div>
                <?php else: ?>
                    <?php foreach ($orderItems as $item): 
                        $isSelected = ($selectedItemId === (int)$item['id']);
                        $isVerified = ($item['item_status'] === 'Verified');
                    ?>
                    <a href="index.php?page=laboratory&item_id=<?= $item['id'] ?>" class="block p-3 rounded-xl border transition-all text-xs <?= $isSelected ? 'bg-purple-50 border-purple-500 shadow-xs' : 'bg-slate-50/70 hover:bg-slate-100 border-slate-200' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-slate-900 truncate"><?= htmlspecialchars($item['test_name']) ?></span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold <?= $isVerified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                <?= htmlspecialchars($item['item_status']) ?>
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-600 font-medium">
                            <?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?> &bull; <span class="font-mono text-medical-700"><?= htmlspecialchars($item['mrn']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 mt-2 font-mono">
                            <span>Barcode: <?= htmlspecialchars($item['sample_barcode'] ?? 'Unassigned') ?></span>
                            <span><?= date('d M, h:i A', strtotime($item['order_datetime'])) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right 2 Cols: Result Entry & Verification -->
        <div class="lg:col-span-2 space-y-6">

            <?php if (!$selectedItem): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-12 text-center text-slate-400">
                    <i class="fa-solid fa-flask-vial text-4xl mb-3 text-slate-300"></i>
                    <h3 class="text-base font-bold text-slate-700">Select an Investigation to Process</h3>
                    <p class="text-xs text-slate-500 mt-1">Pick a sample from the diagnostic worklist to record values or verify results.</p>
                </div>
            <?php else: 
                $isPaid = ($billPaymentInfo && $billPaymentInfo['payment_status'] === 'Fully-Paid');
            ?>

                <!-- Selected Specimen Card -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-3 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-slate-900 text-base"><?= htmlspecialchars($selectedItem['test_name']) ?></h3>
                                <span class="font-mono text-xs font-bold text-purple-700 bg-purple-50 border border-purple-200 px-2 py-0.5 rounded">
                                    <?= htmlspecialchars($selectedItem['test_code']) ?>
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                Patient: <strong><?= htmlspecialchars($selectedItem['first_name'] . ' ' . $selectedItem['last_name']) ?></strong> 
                                (<span class="font-mono text-medical-800 font-semibold"><?= htmlspecialchars($selectedItem['mrn']) ?></span> &bull; <?= htmlspecialchars($selectedItem['gender']) ?>, <?= $selectedItem['age'] ?>y)
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <?php if ($selectedItem['item_status'] === 'Verified' || !empty($existingResults)): ?>
                            <button type="button" onclick="document.getElementById('labReportModal').classList.remove('hidden')" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-bold shadow-sm flex items-center gap-1.5 transition-colors">
                                <i class="fa-solid fa-print text-[11px]"></i>
                                <span>Print Lab Report</span>
                            </button>
                            <?php endif; ?>
                            <div class="text-right">
                                <div class="text-xs text-slate-500">Specimen: <strong class="text-slate-800"><?= htmlspecialchars($selectedItem['specimen_type']) ?></strong></div>
                                <div class="text-[11px] font-mono text-slate-400 mt-0.5">Order: <?= htmlspecialchars($selectedItem['order_number']) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Status Verification Guard -->
                    <div class="mt-3 flex items-center justify-between p-2.5 rounded-xl border text-xs <?= $isPaid ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-900' ?>">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid <?= $isPaid ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-amber-600' ?> text-sm"></i>
                            <div>
                                <span class="font-bold">Payment Status:</span>
                                <span class="font-semibold uppercase tracking-wider <?= $isPaid ? 'text-emerald-700' : 'text-amber-800' ?>">
                                    <?= $billPaymentInfo ? htmlspecialchars($billPaymentInfo['payment_status']) : 'Unbilled / Pending' ?>
                                </span>
                                <span class="text-[11px] text-slate-500 ml-2">(Standard Fee: <?= format_currency($selectedItem['standard_price']) ?>)</span>
                            </div>
                        </div>
                        <?php if (!$isPaid && $billPaymentInfo): ?>
                            <a href="index.php?page=billing&bill_id=<?= $billPaymentInfo['id'] ?>" target="_blank" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg text-[11px] transition-colors">
                                Settle at Cashier Desk
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Barcode Assignment if missing -->
                    <?php if (empty($selectedItem['sample_barcode'])): ?>
                    <form action="index.php?action=lab_collect_sample" method="POST" class="mt-3 p-3 bg-sky-50 border border-sky-200 rounded-xl flex items-center justify-between gap-3 text-xs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_item_id" value="<?= $selectedItem['id'] ?>">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-barcode text-sky-600 text-base"></i>
                            <span class="font-semibold text-sky-900">Specimen Not Collected Yet:</span>
                        </div>
                        <button type="submit" class="px-4 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-bold shadow-xs">
                            Assign Barcode & Collect Sample
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Parameter Observation Entry Form -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center justify-between">
                        <span>Analyte Parameters & Reference Ranges</span>
                        <span class="text-[11px] text-slate-400 font-normal">Flagged automatically</span>
                    </h4>

                    <form action="index.php?action=lab_save_result" method="POST" class="space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_item_id" value="<?= $selectedItem['id'] ?>">

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-600 uppercase text-[10px] font-semibold border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-2.5">Parameter / Analyte</th>
                                        <th class="px-4 py-2.5">Unit</th>
                                        <th class="px-4 py-2.5">Reference Range</th>
                                        <th class="px-4 py-2.5 w-36">Result Value</th>
                                        <th class="px-4 py-2.5">Flag / Clinical Note</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($parameters as $param): 
                                        $val = $existingResults[$param['id']]['result_value'] ?? '';
                                        $flag = $existingResults[$param['id']]['abnormal_flag'] ?? 'Normal';
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-800">
                                            <?= htmlspecialchars($param['parameter_name']) ?>
                                            <input type="hidden" name="param_ids[]" value="<?= $param['id'] ?>">
                                        </td>
                                        <td class="px-4 py-3 font-mono text-slate-500"><?= htmlspecialchars($param['unit'] ?? '—') ?></td>
                                        <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($param['reference_range'] ?? '—') ?></td>
                                        <td class="px-4 py-3">
                                            <input 
                                                type="text" 
                                                name="result_values[<?= $param['id'] ?>]" 
                                                value="<?= htmlspecialchars($val) ?>" 
                                                placeholder="Enter value"
                                                class="w-full px-2.5 py-1.5 border border-slate-200 rounded font-semibold text-slate-900 outline-none focus:border-medical-500"
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="flags[<?= $param['id'] ?>]" class="py-1.5 px-2 text-xs border border-slate-200 rounded outline-none">
                                                <option value="Normal" <?= $flag === 'Normal' ? 'selected' : '' ?>>Normal</option>
                                                <option value="High" <?= $flag === 'High' ? 'selected' : '' ?>>High</option>
                                                <option value="Low" <?= $flag === 'Low' ? 'selected' : '' ?>>Low</option>
                                                <option value="Critical_High" <?= $flag === 'Critical_High' ? 'selected' : '' ?>>Critical High (Panic)</option>
                                                <option value="Critical_Low" <?= $flag === 'Critical_Low' ? 'selected' : '' ?>>Critical Low (Panic)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Verification & Action Bar -->
                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                            <div class="text-[11px] text-slate-500">
                                Verified by: <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<?= htmlspecialchars($currentUser['role_name']) ?>)
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" name="action_type" value="save_draft" class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold">
                                    Save Draft
                                </button>
                                <button type="submit" name="action_type" value="verify_and_sign" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-bold shadow-sm flex items-center gap-1.5">
                                    <i class="fa-solid fa-stamp text-xs"></i>
                                    <span>Verify & Digitally Sign Report</span>
                                </button>
                            </div>
                        </div>

                    </form>
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Printable Diagnostic Laboratory Report Modal -->
<?php if ($selectedItem): ?>
<div id="labReportModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] flex flex-col relative">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 no-print">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-flask-vial text-purple-600"></i>
                <span>Official Diagnostic Laboratory Report</span>
            </h3>
            <button onclick="document.getElementById('labReportModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Printable Document Canvas -->
        <div id="printableLabReportArea" class="flex-1 overflow-y-auto custom-scrollbar p-6 border-2 border-dashed border-slate-200 rounded-xl bg-white space-y-4 font-sans text-xs">
            <!-- Header -->
            <div class="flex items-center justify-between border-b-2 border-slate-800 pb-3">
                <div>
                    <div class="font-black text-lg text-slate-900 tracking-tight uppercase"><?= APP_NAME ?></div>
                    <div class="text-[11px] font-semibold text-purple-800 uppercase tracking-wider">Department of Pathology & Laboratory Medicine</div>
                    <div class="text-[10px] text-slate-400">Peshawar Medical Complex, Khyber Pakhtunkhwa</div>
                </div>
                <div class="text-right">
                    <div class="font-mono font-bold text-slate-900 text-sm">ORDER # <?= htmlspecialchars($selectedItem['order_number']) ?></div>
                    <div class="font-mono text-[10px] text-slate-500">Barcode: <?= htmlspecialchars($selectedItem['sample_barcode'] ?? 'Unassigned') ?></div>
                    <div class="text-[10px] text-slate-400"><?= format_datetime($selectedItem['order_datetime']) ?></div>
                </div>
            </div>

            <!-- Patient Information Bar -->
            <div class="grid grid-cols-4 gap-2 bg-slate-50 p-3 rounded-lg border border-slate-200 text-[11px]">
                <div>
                    <span class="text-slate-400 block text-[9px] uppercase font-bold">Patient Name</span>
                    <strong class="text-slate-900"><?= htmlspecialchars($selectedItem['first_name'] . ' ' . $selectedItem['last_name']) ?></strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[9px] uppercase font-bold">MRN</span>
                    <span class="font-mono font-bold text-medical-800"><?= htmlspecialchars($selectedItem['mrn']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[9px] uppercase font-bold">Age / Gender</span>
                    <span class="text-slate-800"><?= $selectedItem['age'] ?> Yrs &bull; <?= htmlspecialchars($selectedItem['gender']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[9px] uppercase font-bold">Ordering Consultant</span>
                    <span class="text-slate-800 font-medium"><?= htmlspecialchars($selectedItem['doctor_name']) ?></span>
                </div>
                <div class="col-span-2 pt-1.5 border-t border-slate-200/80">
                    <span class="text-slate-400 text-[9px] uppercase font-bold">Specimen Type:</span>
                    <strong class="text-slate-800"><?= htmlspecialchars($selectedItem['specimen_type']) ?></strong>
                </div>
                <div class="col-span-2 pt-1.5 border-t border-slate-200/80 text-right">
                    <span class="text-slate-400 text-[9px] uppercase font-bold">Investigation:</span>
                    <strong class="text-purple-900"><?= htmlspecialchars($selectedItem['test_name']) ?> (<?= htmlspecialchars($selectedItem['test_code']) ?>)</strong>
                </div>
            </div>

            <!-- Test Parameters & Observed Results Table -->
            <table class="w-full text-left text-xs border border-slate-200 rounded-lg overflow-hidden mt-3">
                <thead class="bg-slate-50 font-bold uppercase text-[9px] text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-3 py-2">Test Parameter / Analyte</th>
                        <th class="px-3 py-2">Observed Value</th>
                        <th class="px-3 py-2">Unit</th>
                        <th class="px-3 py-2">Biological Reference Interval</th>
                        <th class="px-3 py-2 text-center">Status / Flag</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[11px]">
                    <?php if (empty($parameters)): ?>
                        <tr><td colspan="5" class="px-4 py-4 text-center text-slate-400">No specific parameters configured for this panel.</td></tr>
                    <?php else: ?>
                        <?php foreach ($parameters as $p): 
                            $r = $existingResults[$p['id']] ?? null;
                            $val = $r['result_value'] ?? 'Pending';
                            $flag = $r['abnormal_flag'] ?? 'Normal';
                            $isAbnormal = in_array($flag, ['High', 'Low', 'Critical_High', 'Critical_Low']);
                        ?>
                        <tr class="<?= $isAbnormal ? 'bg-amber-50/40' : '' ?>">
                            <td class="px-3 py-2 font-semibold text-slate-900"><?= htmlspecialchars($p['parameter_name']) ?></td>
                            <td class="px-3 py-2 font-mono font-bold <?= $isAbnormal ? 'text-rose-700' : 'text-slate-900' ?>">
                                <?= htmlspecialchars($val) ?>
                            </td>
                            <td class="px-3 py-2 font-mono text-slate-500"><?= htmlspecialchars($p['unit'] ?? '—') ?></td>
                            <td class="px-3 py-2 text-slate-600 font-mono text-[10px]"><?= htmlspecialchars($p['reference_range'] ?? 'Standard Range') ?></td>
                            <td class="px-3 py-2 text-center">
                                <?php if ($flag === 'Normal'): ?>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Normal</span>
                                <?php elseif ($isAbnormal): ?>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 border border-rose-300"><?= htmlspecialchars($flag) ?></span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded text-[9px] text-slate-400">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pathology Clinical Notes & Certification -->
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-[10px] text-slate-600 space-y-1">
                <div class="font-bold text-slate-800 uppercase text-[9px]">Diagnostic Remarks:</div>
                <p>All automated testing performed under accredited clinical standard protocols with certified controls.</p>
                <p class="text-[9px] text-slate-400">Pathology tests are clinical aids and must be correlated with clinical findings.</p>
            </div>

            <!-- Pathologist Sign-off Block -->
            <div class="pt-8 flex justify-between items-end border-t border-slate-200">
                <div class="text-[9px] text-slate-400">
                    <div>Verification Status: <strong><?= htmlspecialchars($selectedItem['item_status']) ?></strong></div>
                    <div>Electronically verified & signed report</div>
                </div>
                <div class="text-center w-52 border-t border-slate-800 pt-1">
                    <span class="font-bold text-slate-900 block"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                    <span class="text-[9px] text-slate-500">Consultant Pathologist & Lab Director</span>
                </div>
            </div>
        </div>

        <!-- Buttons -->
        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-end gap-2 no-print">
            <button type="button" onclick="document.getElementById('labReportModal').classList.add('hidden')" class="px-4 py-1.5 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                Close
            </button>
            <button type="button" onclick="printLabReportDoc()" class="px-5 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-bold shadow-sm flex items-center gap-1.5">
                <i class="fa-solid fa-print text-[11px]"></i>
                <span>Print Official Diagnostic Report</span>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function printLabReportDoc() {
    const printContent = document.getElementById('printableLabReportArea').innerHTML;
    const printWindow = window.open('', '', 'height=750,width=650');
    printWindow.document.write('<html><head><title>Diagnostic Report - <?= htmlspecialchars($selectedItem['mrn'] ?? '') ?></title>');
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
