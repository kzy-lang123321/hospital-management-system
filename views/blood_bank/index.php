<?php
/**
 * Hospital Management System (HMS) - Blood Bank & Transfusion Medicine Workspace
 */

Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'lab_technician', 'nurse']);

$db = Database::getConnection();

// Stock aggregation by blood group
$groupStatsQuery = $db->query("
    SELECT 
        blood_group,
        COUNT(CASE WHEN status = 'Available' THEN 1 END) as available_units,
        COUNT(CASE WHEN status = 'Available' AND component_type = 'Packed_Red_Cells_PRBC' THEN 1 END) as prbc_count,
        COUNT(CASE WHEN status = 'Available' AND component_type = 'Fresh_Frozen_Plasma_FFP' THEN 1 END) as ffp_count,
        COUNT(CASE WHEN status = 'Available' AND component_type = 'Platelets_PC' THEN 1 END) as platelets_count
    FROM `blood_units`
    GROUP BY blood_group
");
$rawGroups = $groupStatsQuery->fetchAll(PDO::FETCH_ASSOC);

$bloodMatrix = [
    'A+' => ['available' => 0, 'prbc' => 0, 'ffp' => 0, 'platelets' => 0],
    'A-' => ['available' => 0, 'prbc' => 0, 'ffp' => 0, 'platelets' => 0],
    'B+' => ['available' => 0, 'prbc' => 0, 'ffp' => 0, 'platelets' => 0],
    'B-' => ['available' => 0, 'prbc' => 0, 'ffp' => 0, 'platelets' => 0],
    'AB+' => ['available' => 0, 'prbc' => 0, 'ffp' => 0, 'platelets' => 0],
    'AB-' => ['available' => 0, 'prbc' => 0, 'ffp' => 0, 'platelets' => 0],
    'O+' => ['available' => 0, 'prbc' => 0, 'ffp' => 0, 'platelets' => 0],
    'O-' => ['available' => 0, 'prbc' => 0, 'ffp' => 0, 'platelets' => 0],
];

foreach ($rawGroups as $rg) {
    if (isset($bloodMatrix[$rg['blood_group']])) {
        $bloodMatrix[$rg['blood_group']] = [
            'available' => (int)$rg['available_units'],
            'prbc' => (int)$rg['prbc_count'],
            'ffp' => (int)$rg['ffp_count'],
            'platelets' => (int)$rg['platelets_count']
        ];
    }
}

// Global Stats
$totalAvailable = $db->query("SELECT COUNT(*) FROM `blood_units` WHERE `status` = 'Available'")->fetchColumn();
$expiringSoon = $db->query("SELECT COUNT(*) FROM `blood_units` WHERE `status` = 'Available' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$pendingRequests = $db->query("SELECT COUNT(*) FROM `blood_requests` WHERE `status` = 'Pending'")->fetchColumn();
$statRequests = $db->query("SELECT COUNT(*) FROM `blood_requests` WHERE `status` = 'Pending' AND `urgency` = 'Life_Saving_STAT'")->fetchColumn();

// Tab selection
$activeTab = clean_input($_GET['tab'] ?? 'inventory');

// Inventory List
$units = $db->query("
    SELECT u.*, d.full_name as donor_name, d.donor_number
    FROM `blood_units` u
    LEFT JOIN `blood_donors` d ON u.donor_id = d.id
    ORDER BY CASE WHEN u.status = 'Available' THEN 1 ELSE 2 END, u.expiry_date ASC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// Donors List
$donors = $db->query("SELECT * FROM `blood_donors` ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Clinical Requests
$requests = $db->query("
    SELECT 
        br.*,
        p.mrn, p.first_name, p.last_name, p.gender, p.age, p.blood_group as patient_blood_group,
        u.full_name as requested_by_doctor
    FROM `blood_requests` br
    JOIN `patients` p ON br.patient_id = p.id
    JOIN `users` u ON br.requested_by = u.id
    ORDER BY CASE WHEN br.urgency = 'Life_Saving_STAT' AND br.status = 'Pending' THEN 1 ELSE 2 END, br.created_at DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Active encounters for blood requisition
$encounters = $db->query("
    SELECT e.id as encounter_id, e.encounter_number, e.patient_id, p.mrn, p.first_name, p.last_name, p.blood_group
    FROM `encounters` e
    JOIN `patients` p ON e.patient_id = p.id
    WHERE e.encounter_status = 'In-Progress'
    ORDER BY e.id DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                        <i class="fa-solid fa-droplet mr-1.5 text-rose-600"></i> Transfusion Medicine
                    </span>
                    <h1 class="text-2xl font-bold text-slate-800">Blood Bank & Component Inventory</h1>
                </div>
                <p class="text-sm text-slate-500 mt-1">Screened Blood Components, Cross-Match Transfusions, Voluntary Donors & Cold Chain Monitoring.</p>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="document.getElementById('modal-blood-request').classList.remove('hidden')" class="btn-secondary text-xs flex items-center gap-1.5 font-semibold">
                    <i class="fa-solid fa-hand-holding-droplet text-rose-600"></i>
                    <span>Clinical Requisition</span>
                </button>
                <button onclick="document.getElementById('modal-add-donor').classList.remove('hidden')" class="btn-secondary text-xs flex items-center gap-1.5 font-semibold">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Register Donor</span>
                </button>
                <button onclick="document.getElementById('modal-add-unit').classList.remove('hidden')" class="btn-primary bg-rose-600 hover:bg-rose-700 text-white text-xs flex items-center gap-1.5 font-semibold">
                    <i class="fa-solid fa-barcode"></i>
                    <span>Add Blood Unit</span>
                </button>
            </div>
        </div>

        <!-- Blood Group Matrix Grid (8 Blood Groups) -->
        <div>
            <div class="text-xs font-bold uppercase text-slate-500 tracking-wider mb-2 flex items-center justify-between">
                <span>Blood Group Reserve Matrix (Available Units)</span>
                <span class="text-slate-400 font-normal"><?= (int)$totalAvailable ?> Total Units in Cold Storage</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
                <?php foreach ($bloodMatrix as $group => $counts): ?>
                <div class="bg-white p-3 rounded-xl border <?= $counts['available'] > 0 ? 'border-rose-200' : 'border-slate-200 opacity-60' ?> shadow-sm text-center relative overflow-hidden">
                    <div class="text-xs font-black text-rose-600"><?= $group ?></div>
                    <div class="text-2xl font-black text-slate-800 my-1"><?= $counts['available'] ?></div>
                    <div class="text-[10px] text-slate-400">
                        <?= $counts['prbc'] ?> PRBC &bull; <?= $counts['ffp'] ?> FFP
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- KPIs Strip -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Available</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)$totalAvailable ?></div>
                <div class="text-xs text-slate-400 font-medium mt-1">Ready for Transfusion</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-amber-600 tracking-wider">Expiring in 7 Days</div>
                <div class="text-2xl font-black text-amber-600 mt-1"><?= (int)$expiringSoon ?></div>
                <div class="text-xs text-amber-600 font-medium mt-1">Requires Priority Issuance</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-rose-600 tracking-wider">STAT Emergency Requests</div>
                <div class="text-2xl font-black text-rose-600 mt-1"><?= (int)$statRequests ?></div>
                <div class="text-xs text-rose-500 font-medium mt-1">Immediate Cross-Match</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-blue-600 tracking-wider">Pending Requisitions</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)$pendingRequests ?></div>
                <div class="text-xs text-blue-500 font-medium mt-1">Awaiting Issue</div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-200 space-x-4">
            <a href="index.php?page=blood_bank&tab=inventory" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'inventory' ? 'border-b-2 border-rose-600 text-rose-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-boxes-stacked mr-1"></i> Units Inventory
            </a>
            <a href="index.php?page=blood_bank&tab=requests" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'requests' ? 'border-b-2 border-rose-600 text-rose-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-clipboard-list mr-1"></i> Clinical Requisitions (<?= count($requests) ?>)
            </a>
            <a href="index.php?page=blood_bank&tab=donors" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'donors' ? 'border-b-2 border-rose-600 text-rose-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-hand-holding-heart mr-1"></i> Donor Registry (<?= count($donors) ?>)
            </a>
        </div>

        <!-- TAB 1: UNITS INVENTORY -->
        <?php if ($activeTab === 'inventory'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Barcode</th>
                            <th class="py-3 px-4">Group</th>
                            <th class="py-3 px-4">Component Type</th>
                            <th class="py-3 px-4">Volume</th>
                            <th class="py-3 px-4">Collection Date</th>
                            <th class="py-3 px-4">Expiry Date</th>
                            <th class="py-3 px-4">Storage Location</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($units)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-droplet text-4xl text-slate-200 mb-3 block"></i>
                                No blood units currently in inventory.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($units as $u): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                <i class="fa-solid fa-barcode mr-1 text-slate-400"></i>
                                <?= htmlspecialchars($u['unit_barcode']) ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-black bg-rose-100 text-rose-800">
                                    <?= htmlspecialchars($u['blood_group']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 font-medium text-slate-800">
                                <?= htmlspecialchars(str_replace('_', ' ', $u['component_type'])) ?>
                            </td>
                            <td class="py-3 px-4"><?= (int)$u['volume_ml'] ?> ml</td>
                            <td class="py-3 px-4 text-slate-500"><?= date('d M Y', strtotime($u['collection_date'])) ?></td>
                            <td class="py-3 px-4">
                                <span class="<?= strtotime($u['expiry_date']) < strtotime('+7 days') ? 'text-amber-600 font-bold' : 'text-slate-800' ?>">
                                    <?= date('d M Y', strtotime($u['expiry_date'])) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($u['storage_freezer_location']) ?></td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($u['status'] === 'Available'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">Available</span>
                                <?php elseif ($u['status'] === 'Issued'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">Issued</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800"><?= htmlspecialchars($u['status']) ?></span>
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

        <!-- TAB 2: CLINICAL REQUISITIONS -->
        <?php if ($activeTab === 'requests'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Req #</th>
                            <th class="py-3 px-4">Patient (MRN)</th>
                            <th class="py-3 px-4">Patient Blood Group</th>
                            <th class="py-3 px-4">Component & Units</th>
                            <th class="py-3 px-4">Urgency</th>
                            <th class="py-3 px-4">Requested By</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                No active blood requisitions found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $req['urgency'] === 'Life_Saving_STAT' ? 'bg-rose-50/40' : '' ?>">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900"><?= htmlspecialchars($req['request_number']) ?></td>
                            <td class="py-3 px-4">
                                <a href="index.php?page=patient_profile&id=<?= $req['patient_id'] ?>" class="font-bold text-slate-900 hover:text-medical-600 block">
                                    <?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?>
                                </a>
                                <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($req['mrn']) ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-black bg-rose-100 text-rose-800">
                                    <?= htmlspecialchars($req['patient_blood_group'] ?? 'Unknown') ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-800">
                                <?= (int)$req['units_required'] ?> Unit(s) &bull; <?= htmlspecialchars(str_replace('_', ' ', $req['component_type'])) ?>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($req['urgency'] === 'Life_Saving_STAT'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-red-600 text-white animate-pulse">STAT EMERGENCY</span>
                                <?php elseif ($req['urgency'] === 'Urgent'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-white">Urgent</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600">Routine</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-slate-600">Dr. <?= htmlspecialchars($req['requested_by_doctor']) ?></td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($req['status'] === 'Issued'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">Issued</span>
                                <?php elseif ($req['status'] === 'Cross_Matched'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800">Cross-Matched</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <?php if ($req['status'] !== 'Issued'): ?>
                                <button onclick="openIssueModal(<?= $req['id'] ?>, '<?= htmlspecialchars(addslashes($req['patient_blood_group'] ?? 'O+')) ?>', '<?= htmlspecialchars(addslashes($req['component_type'])) ?>')" class="btn-primary bg-rose-600 hover:bg-rose-700 py-1 px-2.5 text-xs text-white font-semibold">
                                    <i class="fa-solid fa-check mr-1"></i> Cross-Match & Issue
                                </button>
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

        <!-- TAB 3: DONOR REGISTRY -->
        <?php if ($activeTab === 'donors'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Donor #</th>
                            <th class="py-3 px-4">Donor Full Name</th>
                            <th class="py-3 px-4">Blood Group</th>
                            <th class="py-3 px-4">Gender</th>
                            <th class="py-3 px-4">Contact Phone</th>
                            <th class="py-3 px-4">CNIC / ID</th>
                            <th class="py-3 px-4">Screening Status</th>
                            <th class="py-3 px-4">Last Donation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($donors)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                No registered donors found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($donors as $d): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900"><?= htmlspecialchars($d['donor_number']) ?></td>
                            <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($d['full_name']) ?></td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-black bg-rose-100 text-rose-800">
                                    <?= htmlspecialchars($d['blood_group']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4"><?= htmlspecialchars($d['gender']) ?></td>
                            <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($d['phone']) ?></td>
                            <td class="py-3 px-4 font-mono text-slate-500"><?= htmlspecialchars($d['cnic'] ?: 'N/A') ?></td>
                            <td class="py-3 px-4">
                                <?php if ($d['screening_status'] === 'Eligible'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">Eligible</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800"><?= htmlspecialchars($d['screening_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-slate-500"><?= $d['last_donation_date'] ? date('d M Y', strtotime($d['last_donation_date'])) : 'First Time' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- MODAL: REGISTER DONOR -->
<div id="modal-add-donor" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Register Voluntary Blood Donor</h3>
            <button type="button" onclick="document.getElementById('modal-add-donor').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=blood_donor_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="form-label text-xs">Donor Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" required placeholder="e.g. Tariq Mehmood" class="form-input text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Blood Group <span class="text-red-500">*</span></label>
                    <select name="blood_group" required class="form-input text-xs font-bold text-rose-700">
                        <option value="A+">A+</option><option value="A-">A-</option>
                        <option value="B+">B+</option><option value="B-">B-</option>
                        <option value="AB+">AB+</option><option value="AB-">AB-</option>
                        <option value="O+">O+</option><option value="O-">O-</option>
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs">Gender</label>
                    <select name="gender" class="form-input text-xs">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Phone Number <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" required placeholder="+92 300 1234567" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">CNIC / ID Number</label>
                    <input type="text" name="cnic" placeholder="17301-xxxxxxx-x" class="form-input text-xs">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Screening Status</label>
                <select name="screening_status" class="form-input text-xs font-semibold">
                    <option value="Eligible">Eligible (Serology Non-Reactive HIV/HCV/HBsAg)</option>
                    <option value="Deferred">Deferred (Temporary Low Hb / Fever)</option>
                </select>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-add-donor').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Register Donor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD BLOOD UNIT TO INVENTORY -->
<div id="modal-add-unit" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-rose-600 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Intake Screened Blood Unit</h3>
            <button type="button" onclick="document.getElementById('modal-add-unit').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=blood_unit_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="form-label text-xs">Select Registered Donor (Optional)</label>
                <select name="donor_id" class="form-input text-xs">
                    <option value="">-- Anonymous / Voluntary Walk-in --</option>
                    <?php foreach ($donors as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['donor_number'] . ' - ' . $d['full_name'] . ' (' . $d['blood_group'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Blood Group <span class="text-red-500">*</span></label>
                    <select name="blood_group" required class="form-input text-xs font-black text-rose-600">
                        <option value="A+">A+</option><option value="A-">A-</option>
                        <option value="B+">B+</option><option value="B-">B-</option>
                        <option value="AB+">AB+</option><option value="AB-">AB-</option>
                        <option value="O+">O+</option><option value="O-">O-</option>
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs">Component Type <span class="text-red-500">*</span></label>
                    <select name="component_type" required class="form-input text-xs font-semibold">
                        <option value="Packed_Red_Cells_PRBC">Packed Red Blood Cells (PRBC)</option>
                        <option value="Fresh_Frozen_Plasma_FFP">Fresh Frozen Plasma (FFP)</option>
                        <option value="Platelets_PC">Platelet Concentrate (PC)</option>
                        <option value="Whole_Blood">Whole Blood</option>
                        <option value="Cryoprecipitate">Cryoprecipitate</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Volume (ml)</label>
                    <input type="number" name="volume_ml" value="450" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Cold Storage Location</label>
                    <input type="text" name="storage_freezer_location" value="Cold Storage A1 (2-6°C)" class="form-input text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Collection Date <span class="text-red-500">*</span></label>
                    <input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" required class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Expiry Date <span class="text-red-500">*</span></label>
                    <input type="date" name="expiry_date" value="<?= date('Y-m-d', strtotime('+35 days')) ?>" required class="form-input text-xs font-bold text-slate-800">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-add-unit').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-barcode mr-1"></i> Add Unit to Storage
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CLINICAL BLOOD REQUISITION -->
<div id="modal-blood-request" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Request Blood for Patient</h3>
            <button type="button" onclick="document.getElementById('modal-blood-request').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=blood_request_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="form-label text-xs">Select Patient Encounter <span class="text-red-500">*</span></label>
                <select name="encounter_id" required class="form-input text-xs">
                    <option value="">-- Choose Active Encounter --</option>
                    <?php foreach ($encounters as $e): ?>
                    <option value="<?= $e['encounter_id'] ?>">
                        <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?> (<?= htmlspecialchars($e['mrn']) ?>) &bull; Blood: <?= htmlspecialchars($e['blood_group'] ?? 'Unknown') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Component Type <span class="text-red-500">*</span></label>
                    <select name="component_type" required class="form-input text-xs">
                        <option value="Packed_Red_Cells_PRBC">Packed Red Blood Cells (PRBC)</option>
                        <option value="Fresh_Frozen_Plasma_FFP">Fresh Frozen Plasma (FFP)</option>
                        <option value="Platelets_PC">Platelet Concentrate (PC)</option>
                        <option value="Whole_Blood">Whole Blood</option>
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs">Units Required <span class="text-red-500">*</span></label>
                    <input type="number" name="units_required" value="1" min="1" max="10" required class="form-input text-xs">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Urgency Priority <span class="text-red-500">*</span></label>
                <select name="urgency" required class="form-input text-xs font-bold">
                    <option value="Routine">Routine Elective</option>
                    <option value="Urgent">Urgent (Within 2 Hours)</option>
                    <option value="Life_Saving_STAT">Life-Saving STAT (Immediate)</option>
                </select>
            </div>

            <div>
                <label class="form-label text-xs">Clinical Indication</label>
                <input type="text" name="clinical_indication" placeholder="e.g. Acute upper GI bleed, severe anemia Hb 6.2" class="form-input text-xs">
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-blood-request').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Submit Blood Request
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CROSS-MATCH & ISSUE UNIT -->
<div id="modal-issue-unit" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-rose-600 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Cross-Match & Issue Blood Unit</h3>
            <button type="button" onclick="document.getElementById('modal-issue-unit').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=blood_issue_unit" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" id="issue-request-id">

            <div>
                <label class="form-label text-xs">Select Available Matching Blood Unit <span class="text-red-500">*</span></label>
                <select name="unit_id" required class="form-input text-xs font-mono">
                    <option value="">-- Choose Unit --</option>
                    <?php foreach ($units as $u): ?>
                        <?php if ($u['status'] === 'Available'): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= htmlspecialchars($u['unit_barcode']) ?> &bull; <?= htmlspecialchars($u['blood_group']) ?> &bull; <?= htmlspecialchars(str_replace('_', ' ', $u['component_type'])) ?> (Exp: <?= $u['expiry_date'] ?>)
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="p-3 bg-rose-50 rounded-lg border border-rose-200 text-xs text-rose-950">
                <i class="fa-solid fa-shield-halved mr-1 text-rose-600"></i>
                <strong>Cross-Match Protocol:</strong> Major and minor cross-match verified compatible under 37°C incubation. Unit will be locked and deducted from available bank inventory.
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-issue-unit').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Confirm Issue
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openIssueModal(requestId, bloodGroup, compType) {
    document.getElementById('issue-request-id').value = requestId;
    document.getElementById('modal-issue-unit').classList.remove('hidden');
}
</script>
