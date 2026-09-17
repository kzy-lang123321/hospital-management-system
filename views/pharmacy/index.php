<?php
/**
 * Hospital Management System (HMS) - Pharmacy & Dispensing Counter
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$activeTab = clean_input($_GET['tab'] ?? 'queue');

// Fetch Prescriptions Queue
$prescriptions = $db->query("
    SELECT p.*, e.encounter_number, e.encounter_type,
           pt.id AS patient_id, pt.first_name, pt.last_name, pt.mrn, pt.gender, pt.age,
           u.full_name AS doctor_name,
           (SELECT COUNT(*) FROM `prescription_items` WHERE `prescription_id` = p.id) AS total_items
    FROM `prescriptions` p
    JOIN `encounters` e ON p.encounter_id = e.id
    JOIN `patients` pt ON e.patient_id = pt.id
    JOIN `users` u ON p.doctor_id = u.id
    ORDER BY p.id DESC
")->fetchAll();

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
        <?php if (empty($prescriptions)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
                <i class="fa-solid fa-prescription text-3xl mb-2 text-slate-300"></i>
                <p>No prescriptions waiting in dispensing queue.</p>
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
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $rx['encounter_type'] === 'Emergency' ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-700' ?>">
                                    <?= htmlspecialchars($rx['encounter_type']) ?>
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5">
                                Prescribed by <strong><?= htmlspecialchars($rx['doctor_name']) ?></strong> &bull; <?= format_datetime($rx['created_at']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $isDispensed ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                            <?= htmlspecialchars($rx['status']) ?>
                        </span>
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
