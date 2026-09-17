<?php
/**
 * Hospital Management System (HMS) - Laboratory Diagnostic Workbench
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$selectedItemId = (int)($_GET['item_id'] ?? 0);

// Fetch Lab Orders & Test Items
$orderItems = $db->query("
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
    ORDER BY loi.id DESC
")->fetchAll();

// If an item is selected for result entry, fetch its parameters
$selectedItem = null;
$parameters = [];
$existingResults = [];

if ($selectedItemId > 0) {
    $selStmt = $db->prepare("
        SELECT loi.*, lo.order_number, lt.test_name, lt.test_code, lt.specimen_type,
               p.mrn, p.first_name, p.last_name, p.gender, p.age
        FROM `lab_order_items` loi
        JOIN `lab_orders` lo ON loi.lab_order_id = lo.id
        JOIN `lab_tests` lt ON loi.lab_test_id = lt.id
        JOIN `encounters` e ON lo.encounter_id = e.id
        JOIN `patients` p ON e.patient_id = p.id
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
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                <span>Diagnostic Worklist</span>
                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-mono"><?= count($orderItems) ?></span>
            </h3>

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
            <?php else: ?>

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

                        <div class="text-right">
                            <div class="text-xs text-slate-500">Specimen Type: <strong class="text-slate-800"><?= htmlspecialchars($selectedItem['specimen_type']) ?></strong></div>
                            <div class="text-[11px] font-mono text-slate-400 mt-0.5">Order: <?= htmlspecialchars($selectedItem['order_number']) ?></div>
                        </div>
                    </div>

                    <!-- Barcode Assignment if missing -->
                    <?php if (empty($selectedItem['sample_barcode'])): ?>
                    <form action="index.php?action=lab_collect_sample" method="POST" class="mt-4 p-3 bg-sky-50 border border-sky-200 rounded-xl flex items-center justify-between gap-3 text-xs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_item_id" value="<?= $selectedItem['id'] ?>">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-barcode text-sky-600 text-base"></i>
                            <span class="font-semibold text-sky-900">Sample Not Collected Yet:</span>
                        </div>
                        <button type="submit" class="px-4 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-bold">
                            Print Barcode & Collect Sample
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
