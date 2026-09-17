<?php
/**
 * Hospital Management System (HMS) - Centralized Billing & Cashier Desk
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();
$hProfile = hospital_profile();

$selectedBillId = (int)($_GET['bill_id'] ?? 0);

// Fetch All Bills
$bills = $db->query("
    SELECT b.*, e.encounter_number, e.encounter_type,
           p.id AS patient_id, p.mrn, p.first_name, p.last_name, p.phone_primary,
           u.full_name AS cashier_name
    FROM `bills` b
    JOIN `encounters` e ON b.encounter_id = e.id
    JOIN `patients` p ON b.patient_id = p.id
    JOIN `users` u ON b.created_by = u.id
    ORDER BY b.id DESC
")->fetchAll();

// If no bill selected, default to first bill if available
if ($selectedBillId === 0 && !empty($bills)) {
    $selectedBillId = (int)$bills[0]['id'];
}

// Fetch details for selected bill
$selectedBill = null;
$billItems = [];
$billPayments = [];

if ($selectedBillId > 0) {
    $billStmt = $db->prepare("
        SELECT b.*, e.encounter_number, e.encounter_type, e.admission_datetime,
               p.id AS patient_id, p.mrn, p.first_name, p.last_name, p.gender, p.age, p.phone_primary, p.address, p.city,
               u.full_name AS cashier_name
        FROM `bills` b
        JOIN `encounters` e ON b.encounter_id = e.id
        JOIN `patients` p ON b.patient_id = p.id
        JOIN `users` u ON b.created_by = u.id
        WHERE b.id = ?
    ");
    $billStmt->execute([$selectedBillId]);
    $selectedBill = $billStmt->fetch();

    if ($selectedBill) {
        $itemStmt = $db->prepare("SELECT * FROM `bill_items` WHERE `bill_id` = ? ORDER BY id ASC");
        $itemStmt->execute([$selectedBillId]);
        $billItems = $itemStmt->fetchAll();

        $payStmt = $db->prepare("
            SELECT pay.*, u.full_name AS received_by_name 
            FROM `payments` pay 
            JOIN `users` u ON pay.received_by = u.id 
            WHERE pay.bill_id = ? 
            ORDER BY pay.payment_datetime DESC
        ");
        $payStmt->execute([$selectedBillId]);
        $billPayments = $payStmt->fetchAll();
    }
}
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Centralized Billing & Cashier Desk</h2>
            <p class="text-xs text-slate-500">Revenue cycle management, itemized charges, and fiscal receipts</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($selectedBill): ?>
            <button onclick="window.print()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs rounded-lg shadow-sm transition-colors flex items-center gap-2">
                <i class="fa-solid fa-print"></i>
                <span>Print Invoice / Receipt</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 1 Col: Invoices Ledger Worklist -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 flex flex-col h-[750px] overflow-hidden no-print">
            <h3 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                <span>Invoices Ledger</span>
                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-mono"><?= count($bills) ?></span>
            </h3>

            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1">
                <?php if (empty($bills)): ?>
                    <div class="text-center py-16 text-slate-400 text-xs">
                        <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300"></i>
                        <p class="font-medium text-slate-600">No invoices generated yet.</p>
                        <p class="text-[11px] text-slate-400 mt-1">Invoices are automatically created during OPD consultations or inpatient admissions.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($bills as $b): 
                        $isSelected = ($selectedBillId === (int)$b['id']);
                        $isPaid = ($b['payment_status'] === 'Fully-Paid');
                    ?>
                    <a href="index.php?page=billing&bill_id=<?= $b['id'] ?>" class="block p-3 rounded-xl border transition-all text-xs <?= $isSelected ? 'bg-amber-50 border-amber-500 shadow-xs' : 'bg-slate-50/70 hover:bg-slate-100 border-slate-200' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-mono font-bold text-slate-900"><?= htmlspecialchars($b['bill_number']) ?></span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold <?= $isPaid ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                <?= htmlspecialchars($b['payment_status']) ?>
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-700 font-semibold truncate">
                            <?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?> &bull; <span class="font-mono text-medical-800"><?= htmlspecialchars($b['mrn']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] mt-2">
                            <span class="font-bold text-slate-900"><?= format_currency($b['net_amount']) ?></span>
                            <span class="text-[10px] text-slate-400 font-mono"><?= date('d M, Y', strtotime($b['bill_date'])) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right 2 Cols: Selected Invoice & Payment Counter -->
        <div class="lg:col-span-2 space-y-6">

            <?php if (!$selectedBill): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-16 text-center text-slate-400">
                    <i class="fa-solid fa-cash-register text-4xl mb-3 text-slate-300"></i>
                    <h3 class="text-base font-bold text-slate-700">Billing Counter Ready</h3>
                    <p class="text-xs text-slate-500 mt-1">When consultations, lab orders, or ward bed allocations occur, bills will populate here for cashier payment.</p>
                </div>
            <?php else: ?>
            
            <!-- Official Printable Invoice / Receipt View -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-8 relative overflow-hidden" id="printableReceipt">
                
                <!-- Hospital Letterhead (Dynamically Loaded from DB) -->
                <div class="flex items-start justify-between pb-6 border-b border-slate-200">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-medical-700 text-white flex items-center justify-center font-bold text-xl">
                            <i class="fa-solid fa-house-chimney-medical"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-black text-slate-900 tracking-tight uppercase"><?= htmlspecialchars($hProfile['hospital_name']) ?></h1>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($hProfile['address']) ?>, <?= htmlspecialchars($hProfile['city']) ?> &bull; Tel: <?= htmlspecialchars($hProfile['phone']) ?></p>
                            <p class="text-[10px] text-slate-400 font-mono">National Tax: <?= htmlspecialchars($hProfile['tax_number'] ?? 'N/A') ?> &bull; Reg: <?= htmlspecialchars($hProfile['license_number'] ?? 'N/A') ?></p>
                        </div>
                    </div>

                    <div class="text-right">
                        <span class="px-3 py-1 rounded-full text-xs font-bold font-mono uppercase tracking-wider <?= $selectedBill['payment_status'] === 'Fully-Paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= htmlspecialchars($selectedBill['payment_status']) ?>
                        </span>
                        <div class="text-sm font-mono font-black text-slate-900 mt-1"><?= htmlspecialchars($selectedBill['bill_number']) ?></div>
                        <div class="text-xs text-slate-500"><?= format_datetime($selectedBill['bill_date']) ?></div>
                    </div>
                </div>

                <!-- Patient & Encounter Meta Strip -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 py-4 border-b border-slate-100 text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-semibold">Patient Name</span>
                        <strong class="text-slate-900"><?= htmlspecialchars($selectedBill['first_name'] . ' ' . $selectedBill['last_name']) ?></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-semibold">Patient MRN</span>
                        <span class="font-mono text-medical-800 font-bold"><?= htmlspecialchars($selectedBill['mrn']) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-semibold">Encounter / Type</span>
                        <span class="font-mono text-slate-700 font-medium"><?= htmlspecialchars($selectedBill['encounter_number']) ?> (<?= $selectedBill['encounter_type'] ?>)</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-semibold">Billed By</span>
                        <span class="text-slate-700"><?= htmlspecialchars($selectedBill['cashier_name']) ?></span>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="py-4">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-500 uppercase text-[10px] font-semibold border-b border-slate-200">
                            <tr>
                                <th class="py-2.5">Item Description</th>
                                <th class="py-2.5">Category</th>
                                <th class="py-2.5 text-center">Qty</th>
                                <th class="py-2.5 text-right">Unit Price</th>
                                <th class="py-2.5 text-right">Net Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($billItems as $item): ?>
                            <tr>
                                <td class="py-2.5 font-medium text-slate-900"><?= htmlspecialchars($item['description']) ?></td>
                                <td class="py-2.5 text-slate-500 text-[11px]"><?= htmlspecialchars($item['item_type']) ?></td>
                                <td class="py-2.5 text-center font-mono"><?= $item['quantity'] ?></td>
                                <td class="py-2.5 text-right font-mono text-slate-600"><?= format_currency($item['unit_price']) ?></td>
                                <td class="py-2.5 text-right font-mono font-bold text-slate-900"><?= format_currency($item['net_price']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals & Balances -->
                <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-start gap-4">
                    <!-- Payments Log -->
                    <div class="w-full sm:w-1/2 text-xs">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Payment Receipts History:</span>
                        <?php if (empty($billPayments)): ?>
                            <p class="text-slate-400 italic">No payments logged yet.</p>
                        <?php else: ?>
                            <div class="space-y-1">
                                <?php foreach ($billPayments as $pay): ?>
                                <div class="p-2 bg-slate-50 rounded border border-slate-200 flex items-center justify-between font-mono text-[11px]">
                                    <div>
                                        <strong class="text-emerald-700"><?= htmlspecialchars($pay['receipt_number']) ?></strong>
                                        <span class="text-slate-500">(<?= htmlspecialchars($pay['payment_method']) ?>)</span>
                                    </div>
                                    <div class="font-bold text-slate-800"><?= format_currency($pay['amount_paid']) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Financial Summary Box -->
                    <div class="w-full sm:w-72 bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Gross Amount:</span>
                            <span class="font-mono font-semibold"><?= format_currency($selectedBill['gross_amount']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Discount / Waiver:</span>
                            <span class="font-mono"><?= format_currency($selectedBill['discount_amount']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-slate-900 font-bold pt-2 border-t border-slate-200 text-sm">
                            <span>Net Payable:</span>
                            <span class="font-mono text-medical-800"><?= format_currency($selectedBill['net_amount']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-emerald-700 font-semibold">
                            <span>Total Paid:</span>
                            <span class="font-mono"><?= format_currency($selectedBill['paid_amount']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-rose-700 font-bold pt-1 border-t border-slate-200">
                            <span>Balance Due:</span>
                            <span class="font-mono text-base"><?= format_currency($selectedBill['balance_amount']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Footer Disclaimers -->
                <div class="pt-6 mt-6 border-t border-slate-200 text-[10px] text-slate-400 text-center">
                    This is an electronically generated hospital receipt valid without physical signature.<br>
                    Thank you for placing your trust in <?= htmlspecialchars($hProfile['hospital_name']) ?>.
                </div>
            </div>

            <!-- Collect Payment Form (No-Print) -->
            <?php if ($selectedBill['balance_amount'] > 0): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 no-print">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-cash-register text-emerald-600"></i>
                    <span>Receive Payment & Issue Fiscal Receipt</span>
                </h4>

                <form action="index.php?action=billing_pay" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bill_id" value="<?= $selectedBill['id'] ?>">
                    <input type="hidden" name="patient_id" value="<?= $selectedBill['patient_id'] ?>">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Payment Amount (PKR) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" name="amount_paid" max="<?= $selectedBill['balance_amount'] ?>" value="<?= $selectedBill['balance_amount'] ?>" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none font-bold text-slate-900 focus:border-medical-500 font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Payment Method <span class="text-rose-500">*</span></label>
                        <select name="payment_method" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-semibold">
                            <option value="Cash">Cash Currency</option>
                            <option value="Credit_Card">Credit Card</option>
                            <option value="Debit_Card">Debit Card</option>
                            <option value="Bank_Transfer">Bank Wire / Online</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Transaction Ref / Slip #</label>
                        <input type="text" name="transaction_reference" placeholder="e.g. POS-SLIP-991" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div class="sm:col-span-3 flex justify-end pt-2">
                        <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/30 transition-all flex items-center gap-2">
                            <i class="fa-solid fa-receipt"></i>
                            <span>Process Payment & Issue Receipt</span>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php endif; ?>

        </div>

    </div>

</div>
