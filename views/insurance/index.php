<?php
/**
 * Hospital Management System (HMS) - Insurance & Corporate TPA Claims
 */

Auth::requireRole(['super_admin', 'hospital_admin', 'cashier', 'receptionist']);

$db = Database::getConnection();

// Summary stats
$statsQuery = $db->query("
    SELECT 
        COUNT(*) as total_claims,
        COALESCE(SUM(claim_amount), 0) as total_claimed_val,
        COALESCE(SUM(approved_amount), 0) as total_approved_val,
        COUNT(CASE WHEN status = 'Submitted' THEN 1 END) as pending_claims,
        COUNT(CASE WHEN status = 'Settled' THEN 1 END) as settled_claims
    FROM `insurance_claims`
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Filters
$statusFilter = clean_input($_GET['status'] ?? '');
$companyFilter = clean_input($_GET['company'] ?? '');
$searchQuery = clean_input($_GET['q'] ?? '');

$sql = "
    SELECT 
        ic.*,
        comp.company_name, comp.company_code,
        b.bill_number, b.net_amount as bill_total, b.paid_amount as bill_paid, b.balance_amount as bill_balance,
        p.mrn, p.first_name, p.last_name, p.phone_primary
    FROM `insurance_claims` ic
    JOIN `insurance_companies` comp ON ic.company_id = comp.id
    JOIN `bills` b ON ic.bill_id = b.id
    JOIN `patients` p ON ic.patient_id = p.id
    WHERE 1=1
";

$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND ic.status = ?";
    $params[] = $statusFilter;
}

if (!empty($companyFilter)) {
    $sql .= " AND ic.company_id = ?";
    $params[] = $companyFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (ic.claim_number LIKE ? OR ic.policy_number LIKE ? OR p.mrn LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)";
    $term = "%$searchQuery%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$sql .= " ORDER BY ic.id DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Panel Companies
$companies = $db->query("SELECT * FROM `insurance_companies` ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Available Bills for Claim Filing
$availableBills = $db->query("
    SELECT b.id, b.bill_number, b.net_amount as total_amount, b.balance_amount, p.id as patient_id, p.mrn, p.first_name, p.last_name 
    FROM `bills` b 
    JOIN `patients` p ON b.patient_id = p.id 
    ORDER BY b.id DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-800 border border-sky-200">
                        <i class="fa-solid fa-file-shield mr-1.5 text-sky-600"></i> Corporate & Government TPA
                    </span>
                    <h1 class="text-2xl font-bold text-slate-800">Insurance & TPA Claims Desk</h1>
                </div>
                <p class="text-sm text-slate-500 mt-1">Sehat Sahulat Card, Corporate Health Panels, Pre-Authorization, Deductibles & Claim Reconciliation.</p>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="document.getElementById('modal-add-company').classList.remove('hidden')" class="btn-secondary text-xs flex items-center gap-1.5 font-semibold">
                    <i class="fa-solid fa-building-shield"></i>
                    <span>Add Panel Company</span>
                </button>
                <button onclick="document.getElementById('modal-new-claim').classList.remove('hidden')" class="btn-primary bg-sky-600 hover:bg-sky-700 text-white text-xs flex items-center gap-1.5 font-semibold">
                    <i class="fa-solid fa-file-circle-plus"></i>
                    <span>Submit New Claim</span>
                </button>
            </div>
        </div>

        <!-- KPIs Strip -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Claims</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['total_claims'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 font-medium mt-1">Processed Applications</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-blue-600 tracking-wider">Gross Claimed Value</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= format_currency($stats['total_claimed_val']) ?></div>
                <div class="text-xs text-blue-500 font-medium mt-1">Receivable Pool</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-emerald-600 tracking-wider">Approved & Settled</div>
                <div class="text-2xl font-black text-emerald-600 mt-1"><?= format_currency($stats['total_approved_val']) ?></div>
                <div class="text-xs text-emerald-600 font-medium mt-1"><?= (int)$stats['settled_claims'] ?> Claims Reconciled</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-amber-600 tracking-wider">Pending Approvals</div>
                <div class="text-2xl font-black text-amber-600 mt-1"><?= (int)$stats['pending_claims'] ?></div>
                <div class="text-xs text-amber-600 font-medium mt-1">Awaiting TPA Adjudication</div>
            </div>
        </div>

        <!-- Filter & Search Strip -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            <form method="GET" action="index.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <input type="hidden" name="page" value="insurance">
                
                <div class="relative flex-1 md:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search Claim #, Policy, Patient..." class="form-input pl-8 py-1.5 text-xs w-full">
                </div>

                <select name="status" onchange="this.form.submit()" class="form-input py-1.5 text-xs">
                    <option value="">All Statuses</option>
                    <option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="Submitted" <?= $statusFilter === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Partially_Approved" <?= $statusFilter === 'Partially_Approved' ? 'selected' : '' ?>>Partially Approved</option>
                    <option value="Settled" <?= $statusFilter === 'Settled' ? 'selected' : '' ?>>Settled</option>
                    <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>

                <select name="company" onchange="this.form.submit()" class="form-input py-1.5 text-xs">
                    <option value="">All Panel Companies</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $companyFilter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($companyFilter)): ?>
                <a href="index.php?page=insurance" class="text-xs text-slate-500 hover:text-slate-800 underline">Reset</a>
                <?php endif; ?>
            </form>

            <div class="text-xs text-slate-500">
                Found <strong><?= count($claims) ?></strong> claims.
            </div>
        </div>

        <!-- Claims Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Claim #</th>
                            <th class="py-3 px-4">Patient (MRN)</th>
                            <th class="py-3 px-4">Panel Company</th>
                            <th class="py-3 px-4">Policy / Pre-Auth</th>
                            <th class="py-3 px-4">Bill Reference</th>
                            <th class="py-3 px-4">Claim Amount</th>
                            <th class="py-3 px-4">Approved Amount</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($claims)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-file-shield text-4xl text-slate-200 mb-3 block"></i>
                                No insurance claims found matching the filters.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($claims as $cl): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                <?= htmlspecialchars($cl['claim_number']) ?>
                            </td>
                            <td class="py-3 px-4">
                                <a href="index.php?page=patient_profile&id=<?= $cl['patient_id'] ?>" class="font-bold text-slate-900 hover:text-medical-600 block">
                                    <?= htmlspecialchars($cl['first_name'] . ' ' . $cl['last_name']) ?>
                                </a>
                                <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($cl['mrn']) ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-slate-800"><?= htmlspecialchars($cl['company_name']) ?></span>
                                <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($cl['company_code']) ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-mono font-semibold text-slate-800"><?= htmlspecialchars($cl['policy_number']) ?></div>
                                <div class="text-[11px] text-slate-400">Auth: <?= htmlspecialchars($cl['pre_auth_code'] ?: 'None') ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-mono text-slate-800"><?= htmlspecialchars($cl['bill_number']) ?></span>
                                <div class="text-[10px] text-slate-400">Total: <?= format_currency($cl['bill_total']) ?></div>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">
                                <?= format_currency($cl['claim_amount']) ?>
                            </td>
                            <td class="py-3 px-4 font-bold text-emerald-600">
                                <?= format_currency($cl['approved_amount']) ?>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($cl['status'] === 'Settled'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check mr-1"></i> Settled
                                    </span>
                                <?php elseif ($cl['status'] === 'Approved'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800">
                                        Approved
                                    </span>
                                <?php elseif ($cl['status'] === 'Submitted'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                        Submitted
                                    </span>
                                <?php elseif ($cl['status'] === 'Rejected'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800">
                                        Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700">
                                        <?= htmlspecialchars($cl['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <?php if ($cl['status'] !== 'Settled'): ?>
                                <button onclick="openSettleModal(<?= $cl['id'] ?>, '<?= htmlspecialchars(addslashes($cl['claim_number'])) ?>', '<?= $cl['claim_amount'] ?>', '<?= $cl['approved_amount'] ?>', '<?= $cl['status'] ?>')" class="btn-secondary py-1 px-2.5 text-xs text-sky-700 border-sky-200 font-semibold" title="Update Adjudication / Settle Claim">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Adjudicate
                                </button>
                                <?php else: ?>
                                <span class="text-xs text-emerald-600 font-medium"><i class="fa-solid fa-circle-check"></i> Reconciled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<!-- MODAL: SUBMIT NEW CLAIM -->
<div id="modal-new-claim" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-sky-600 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Submit TPA / Insurance Claim</h3>
            <button type="button" onclick="document.getElementById('modal-new-claim').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=insurance_claim_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="form-label text-xs">Select Patient Bill <span class="text-red-500">*</span></label>
                <select name="bill_id" required class="form-input text-xs" onchange="autoFillBillAmount(this)">
                    <option value="">-- Choose Invoice --</option>
                    <?php foreach ($availableBills as $b): ?>
                    <option value="<?= $b['id'] ?>" data-patient="<?= $b['patient_id'] ?>" data-balance="<?= $b['balance_amount'] ?>" data-total="<?= $b['total_amount'] ?>">
                        <?= htmlspecialchars($b['bill_number']) ?> &bull; <?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?> (<?= htmlspecialchars($b['mrn']) ?>) - Total: <?= format_currency($b['total_amount']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="patient_id" id="claim-patient-id">
            </div>

            <div>
                <label class="form-label text-xs">Insurance / TPA Provider <span class="text-red-500">*</span></label>
                <select name="company_id" required class="form-input text-xs font-semibold">
                    <option value="">-- Select Company --</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['company_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Policy / Card Number <span class="text-red-500">*</span></label>
                    <input type="text" name="policy_number" required placeholder="e.g. SLIC-998822 or CNIC" class="form-input text-xs font-mono">
                </div>
                <div>
                    <label class="form-label text-xs">Pre-Auth Approval Code</label>
                    <input type="text" name="pre_auth_code" placeholder="e.g. AUTH-2026-99" class="form-input text-xs font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Claim Amount (Rs.) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="claim_amount" id="claim-amount-input" required class="form-input text-xs font-bold text-sky-700">
                </div>
                <div>
                    <label class="form-label text-xs">Patient Co-Pay Amount (Rs.)</label>
                    <input type="number" step="0.01" name="patient_copay_amount" value="0.00" class="form-input text-xs">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Remarks / Admission Justification</label>
                <textarea name="remarks" rows="2" placeholder="Clinical justification for panel coverage..." class="form-input text-xs"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-new-claim').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Submit Claim
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: SETTLE / ADJUDICATE CLAIM -->
<div id="modal-settle-claim" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div>
                <h3 class="font-bold text-base">Adjudicate Insurance Claim</h3>
                <p id="settle-claim-num" class="text-xs text-slate-300"></p>
            </div>
            <button type="button" onclick="document.getElementById('modal-settle-claim').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=insurance_claim_update" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="claim_id" id="settle-claim-id">

            <div>
                <label class="form-label text-xs">Adjudication Status <span class="text-red-500">*</span></label>
                <select name="status" id="settle-status" required class="form-input text-xs font-bold">
                    <option value="Approved">Approved (Awaiting Cheque/Disbursement)</option>
                    <option value="Settled">Settled & Credited (Closes Patient Bill)</option>
                    <option value="Partially_Approved">Partially Approved</option>
                    <option value="Rejected">Rejected / Denied by TPA</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Original Claim</label>
                    <input type="text" id="settle-original-amt" readonly class="form-input text-xs bg-slate-100 font-bold">
                </div>
                <div>
                    <label class="form-label text-xs">Approved Amount (Rs.) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="approved_amount" id="settle-approved-amt" required class="form-input text-xs font-bold text-emerald-600">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Bank Cheque / Electronic Transfer Ref</label>
                <input type="text" name="settlement_ref" placeholder="e.g. Cheque # 882910 or IBAN Ref" class="form-input text-xs font-mono">
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-settle-claim').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Save Adjudication
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD PANEL COMPANY -->
<div id="modal-add-company" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Add Insurance / TPA Partner</h3>
            <button type="button" onclick="document.getElementById('modal-add-company').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=insurance_company_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="form-label text-xs">Company / TPA Name <span class="text-red-500">*</span></label>
                <input type="text" name="company_name" required placeholder="e.g. State Life Insurance Corporation" class="form-input text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Company Code <span class="text-red-500">*</span></label>
                    <input type="text" name="company_code" required placeholder="e.g. SLIC-01" class="form-input text-xs font-mono">
                </div>
                <div>
                    <label class="form-label text-xs">Credit Period (Days)</label>
                    <input type="number" name="credit_period_days" value="30" class="form-input text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Contact Person</label>
                    <input type="text" name="contact_person" placeholder="Manager Claims" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Phone Number</label>
                    <input type="text" name="contact_phone" placeholder="+92 21 111..." class="form-input text-xs">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Email Address</label>
                <input type="email" name="contact_email" placeholder="claims@company.com" class="form-input text-xs">
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-add-company').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Save Company
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function autoFillBillAmount(select) {
    const opt = select.options[select.selectedIndex];
    if (opt && opt.dataset) {
        document.getElementById('claim-patient-id').value = opt.dataset.patient || '';
        document.getElementById('claim-amount-input').value = opt.dataset.balance || opt.dataset.total || '0';
    }
}

function openSettleModal(claimId, claimNum, origAmt, apprAmt, status) {
    document.getElementById('settle-claim-id').value = claimId;
    document.getElementById('settle-claim-num').textContent = claimNum;
    document.getElementById('settle-original-amt').value = parseFloat(origAmt).toFixed(2);
    document.getElementById('settle-approved-amt').value = parseFloat(apprAmt > 0 ? apprAmt : origAmt).toFixed(2);
    document.getElementById('settle-status').value = status === 'Draft' ? 'Approved' : status;
    document.getElementById('modal-settle-claim').classList.remove('hidden');
}
</script>
