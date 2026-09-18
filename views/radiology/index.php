<?php
/**
 * Hospital Management System (HMS) - Radiology & Diagnostic Imaging Workspace
 */

Auth::requireRole(['super_admin', 'hospital_admin', 'radiologist', 'doctor', 'lab_technician', 'nurse', 'receptionist']);

$db = Database::getConnection();

// Fetch statistics
$statsQuery = $db->query("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'Ordered' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'Scheduled' THEN 1 END) as scheduled_count,
        COUNT(CASE WHEN status = 'Reported' THEN 1 END) as reported_count,
        COUNT(CASE WHEN priority = 'STAT' AND status != 'Reported' THEN 1 END) as stat_urgent_count
    FROM `radiology_orders`
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Filters
$modalityFilter = clean_input($_GET['modality'] ?? '');
$statusFilter = clean_input($_GET['status'] ?? '');
$searchQuery = clean_input($_GET['q'] ?? '');

$sql = "
    SELECT 
        ro.*,
        rs.service_name, rs.service_code, rs.modality, rs.standard_tariff,
        p.id as patient_id, p.mrn, p.first_name, p.last_name, p.gender, p.age,
        u_doc.full_name as ordering_doctor_name,
        rr.id as report_id, rr.radiological_findings, rr.impression, rr.technique_used, rr.report_status, rr.finalized_at,
        u_rad.full_name as radiologist_name
    FROM `radiology_orders` ro
    JOIN `radiology_services` rs ON ro.service_id = rs.id
    JOIN `encounters` e ON ro.encounter_id = e.id
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` u_doc ON ro.ordered_by = u_doc.id
    LEFT JOIN `radiology_reports` rr ON rr.radiology_order_id = ro.id
    LEFT JOIN `users` u_rad ON rr.radiologist_id = u_rad.id
    WHERE 1=1
";

$params = [];

if (!empty($modalityFilter)) {
    $sql .= " AND rs.modality = ?";
    $params[] = $modalityFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND ro.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (ro.order_number LIKE ? OR p.mrn LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR rs.service_name LIKE ?)";
    $term = "%$searchQuery%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$sql .= " ORDER BY CASE WHEN ro.priority = 'STAT' AND ro.status != 'Reported' THEN 1 ELSE 2 END, ro.order_datetime DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Catalog of Radiology Services
$services = $db->query("SELECT * FROM `radiology_services` WHERE `status` = 'Active' ORDER BY modality ASC, service_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Active Encounters for direct ordering
$activeEncounters = $db->query("
    SELECT e.id, e.encounter_number, e.encounter_type, p.mrn, p.first_name, p.last_name 
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
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                        <i class="fa-solid fa-x-ray mr-1.5 text-indigo-600"></i> Imaging & Diagnostics
                    </span>
                    <h1 class="text-2xl font-bold text-slate-800">Radiology PACS & Modality Worklist</h1>
                </div>
                <p class="text-sm text-slate-500 mt-1">Digital Radiography (X-Ray), Ultrasonography, CT-Scan, MRI & Radiologist Electronic Reporting.</p>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('modal-radiology-order').classList.remove('hidden')" class="btn-primary flex items-center gap-2 shadow-sm font-semibold">
                    <i class="fa-solid fa-plus"></i>
                    <span>Order Imaging Study</span>
                </button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Scans</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['total_orders'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 font-medium mt-1">Cumulative Orders</div>
            </div>

            <a href="index.php?page=radiology&status=Ordered" class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm hover:shadow transition-shadow">
                <div class="text-[11px] font-bold uppercase text-blue-600 tracking-wider">Pending Scan</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['pending_count'] ?? 0) ?></div>
                <div class="text-xs text-blue-500 font-medium mt-1">Awaiting Modality</div>
            </a>

            <a href="index.php?page=radiology&status=Scheduled" class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm hover:shadow transition-shadow">
                <div class="text-[11px] font-bold uppercase text-amber-600 tracking-wider">Scheduled</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['scheduled_count'] ?? 0) ?></div>
                <div class="text-xs text-amber-600 font-medium mt-1">In Preparation</div>
            </a>

            <a href="index.php?page=radiology&status=Reported" class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm hover:shadow transition-shadow">
                <div class="text-[11px] font-bold uppercase text-emerald-600 tracking-wider">Reported & Signed</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['reported_count'] ?? 0) ?></div>
                <div class="text-xs text-emerald-600 font-medium mt-1">Verified Reports</div>
            </a>

            <div class="bg-white p-4 rounded-xl border border-red-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-red-600 tracking-wider">STAT Urgent</div>
                <div class="text-2xl font-black text-red-600 mt-1"><?= (int)($stats['stat_urgent_count'] ?? 0) ?></div>
                <div class="text-xs text-red-500 font-medium mt-1">Priority Trauma / ICU</div>
            </div>
        </div>

        <!-- Modality Filter Buttons & Search -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="index.php?page=radiology" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= empty($modalityFilter) ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All Modalities</a>
                <a href="index.php?page=radiology&modality=X-Ray" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $modalityFilter === 'X-Ray' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">X-Ray</a>
                <a href="index.php?page=radiology&modality=Ultrasound" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $modalityFilter === 'Ultrasound' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Ultrasound (USG)</a>
                <a href="index.php?page=radiology&modality=CT-Scan" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $modalityFilter === 'CT-Scan' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">CT-Scan</a>
                <a href="index.php?page=radiology&modality=MRI" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $modalityFilter === 'MRI' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">MRI</a>
                <a href="index.php?page=radiology&modality=Mammography" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $modalityFilter === 'Mammography' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Mammography</a>
            </div>

            <form method="GET" action="index.php" class="flex items-center gap-2 w-full md:w-auto">
                <input type="hidden" name="page" value="radiology">
                <?php if (!empty($modalityFilter)): ?>
                <input type="hidden" name="modality" value="<?= htmlspecialchars($modalityFilter) ?>">
                <?php endif; ?>
                <div class="relative flex-1 md:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search Order #, MRN, Study..." class="form-input pl-8 py-1.5 text-xs w-full">
                </div>
                <button type="submit" class="btn-secondary py-1.5 text-xs">Search</button>
            </form>
        </div>

        <!-- Radiology Worklist Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Order #</th>
                            <th class="py-3 px-4">Modality</th>
                            <th class="py-3 px-4">Imaging Procedure</th>
                            <th class="py-3 px-4">Patient (MRN)</th>
                            <th class="py-3 px-4">Ordered By / Date</th>
                            <th class="py-3 px-4">Priority</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-radiation text-4xl text-slate-200 mb-3 block"></i>
                                No imaging studies found in the current worklist.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $o['priority'] === 'STAT' ? 'bg-red-50/40' : '' ?>">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                <?= htmlspecialchars($o['order_number']) ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <?= htmlspecialchars($o['modality']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($o['service_name']) ?></div>
                                <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($o['service_code']) ?> &bull; <?= format_currency($o['standard_tariff']) ?></div>
                                <?php if (!empty($o['clinical_indication'])): ?>
                                <div class="text-[11px] text-slate-500 italic mt-0.5 truncate max-w-xs">Ind: <?= htmlspecialchars($o['clinical_indication']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <a href="index.php?page=patient_profile&id=<?= $o['patient_id'] ?>" class="font-semibold text-slate-900 hover:text-medical-600 block">
                                    <?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?>
                                </a>
                                <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($o['mrn']) ?> &bull; <?= htmlspecialchars($o['gender']) ?>, <?= (int)$o['age'] ?>y</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-medium text-slate-800">Dr. <?= htmlspecialchars($o['ordering_doctor_name']) ?></div>
                                <div class="text-[11px] text-slate-400"><?= date('d M Y, H:i', strtotime($o['order_datetime'])) ?></div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($o['priority'] === 'STAT'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-red-600 text-white animate-pulse">STAT</span>
                                <?php elseif ($o['priority'] === 'Urgent'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-white">Urgent</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600">Routine</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($o['status'] === 'Reported'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check mr-1"></i> Reported
                                    </span>
                                <?php elseif ($o['status'] === 'Scheduled'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                        Scheduled
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800">
                                        Ordered
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap space-x-1">
                                <?php if ($o['status'] === 'Reported'): ?>
                                <button onclick="viewReport(<?= htmlspecialchars(json_encode($o)) ?>)" class="btn-secondary py-1 px-2 text-xs" title="View Electronic Diagnostic Report">
                                    <i class="fa-solid fa-file-medical text-medical-600 mr-1"></i> View Report
                                </button>
                                <?php else: ?>
                                <button onclick="openReportEditor(<?= $o['id'] ?>, '<?= htmlspecialchars(addslashes($o['order_number'])) ?>', '<?= htmlspecialchars(addslashes($o['service_name'])) ?>', '<?= htmlspecialchars(addslashes($o['first_name'] . ' ' . $o['last_name'])) ?>', '<?= htmlspecialchars(addslashes($o['mrn'])) ?>')" class="btn-primary bg-indigo-600 hover:bg-indigo-700 py-1 px-2.5 text-xs text-white" title="Enter Radiologist Findings & Report">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Enter Report
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

    </div>
</main>

<!-- MODAL: DIRECT RADIOLOGY ORDER -->
<div id="modal-radiology-order" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-indigo-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-x-ray text-lg"></i>
                <h3 class="font-bold text-base">New Radiology & Imaging Order</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-radiology-order').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=radiology_order_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="form-label text-xs">Select Patient Encounter <span class="text-red-500">*</span></label>
                <select name="encounter_id" required class="form-input text-xs">
                    <option value="">-- Choose Active Encounter --</option>
                    <?php foreach ($activeEncounters as $ae): ?>
                    <option value="<?= $ae['id'] ?>">
                        <?= htmlspecialchars($ae['encounter_number']) ?> &bull; <?= htmlspecialchars($ae['first_name'] . ' ' . $ae['last_name']) ?> (<?= htmlspecialchars($ae['mrn']) ?>) [<?= htmlspecialchars($ae['encounter_type']) ?>]
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label text-xs">Radiology Service / Scan Study <span class="text-red-500">*</span></label>
                <select name="service_id" required class="form-input text-xs">
                    <option value="">-- Choose Study --</option>
                    <?php foreach ($services as $srv): ?>
                    <option value="<?= $srv['id'] ?>">
                        [<?= htmlspecialchars($srv['modality']) ?>] <?= htmlspecialchars($srv['service_name']) ?> (<?= format_currency($srv['standard_tariff']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Priority <span class="text-red-500">*</span></label>
                    <select name="priority" required class="form-input text-xs">
                        <option value="Routine">Routine</option>
                        <option value="Urgent">Urgent</option>
                        <option value="STAT">STAT (Trauma / Acute)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs">Order Schedule</label>
                    <input type="datetime-local" name="order_datetime" value="<?= date('Y-m-d\TH:i') ?>" class="form-input text-xs">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Clinical Indication / Suspected Pathology</label>
                <textarea name="clinical_indication" rows="2" placeholder="e.g. Rule out pneumonia, fracture tibia, acute abdomen..." class="form-input text-xs"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-radiology-order').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Submit Imaging Order
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: RADIOLOGIST REPORT EDITOR -->
<div id="modal-radiology-report" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div>
                <h3 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-file-waveform text-indigo-400"></i>
                    <span>Radiology Diagnostic Reporting</span>
                </h3>
                <p id="rep-subheading" class="text-xs text-slate-300 mt-0.5"></p>
            </div>
            <button type="button" onclick="document.getElementById('modal-radiology-report').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=radiology_report_save" class="p-6 space-y-4 overflow-y-auto">
            <?= csrf_field() ?>
            <input type="hidden" name="radiology_order_id" id="rep-order-id">

            <div>
                <label class="form-label text-xs">Technique / Projection / Contrast Protocol</label>
                <input type="text" name="technique_used" placeholder="e.g. Standard PA & Lateral erect projections, High-resolution non-contrast helical scan" class="form-input text-xs">
            </div>

            <div>
                <label class="form-label text-xs">Radiological Findings (Detailed Observations) <span class="text-red-500">*</span></label>
                <textarea name="radiological_findings" rows="6" required placeholder="Detailed radiological findings across anatomical structures..." class="form-input text-xs font-mono"></textarea>
            </div>

            <div>
                <label class="form-label text-xs">Impression / Final Diagnostic Conclusion <span class="text-red-500">*</span></label>
                <textarea name="impression" rows="3" required placeholder="Concise summary conclusion e.g. Normal study / Right middle lobe consolidation consistent with bacterial pneumonia" class="form-input text-xs font-semibold"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 items-center">
                <div>
                    <label class="form-label text-xs">Report Status</label>
                    <select name="report_status" class="form-input text-xs font-semibold">
                        <option value="Finalized">Finalized & Signed Off</option>
                        <option value="Draft">Save as Draft</option>
                    </select>
                </div>
                <div class="text-xs text-slate-400 pt-4">
                    <i class="fa-solid fa-signature"></i> Electronically signed with current logged-in clinician credentials.
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-radiology-report').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-circle-check mr-1"></i> Sign & Save Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: VIEW PRINTABLE REPORT -->
<div id="modal-view-report" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 bg-slate-800 text-white flex items-center justify-between no-print">
            <h3 class="font-bold text-base">Diagnostic Imaging Report</h3>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="btn-secondary text-xs py-1 px-2.5">
                    <i class="fa-solid fa-print mr-1"></i> Print
                </button>
                <button type="button" onclick="document.getElementById('modal-view-report').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <div class="p-8 overflow-y-auto space-y-6 text-slate-800" id="print-report-body">
            <!-- Dynamic Report Content Loaded by JS -->
        </div>
    </div>
</div>

<script>
function openReportEditor(orderId, orderNum, serviceName, patientName, mrn) {
    document.getElementById('rep-order-id').value = orderId;
    document.getElementById('rep-subheading').textContent = orderNum + ' • ' + serviceName + ' • Patient: ' + patientName + ' (' + mrn + ')';
    document.getElementById('modal-radiology-report').classList.remove('hidden');
}

function viewReport(data) {
    const body = document.getElementById('print-report-body');
    body.innerHTML = `
        <div class="border-b-2 border-slate-900 pb-4 flex justify-between items-start">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase">DEPARTMENT OF RADIOLOGY & IMAGING</h2>
                <div class="text-xs text-slate-500">Official Diagnostic Imaging Examination Report</div>
            </div>
            <div class="text-right font-mono text-xs">
                <div><strong>Order #:</strong> ${data.order_number}</div>
                <div><strong>Date:</strong> ${data.finalized_at || data.order_datetime}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 p-3 bg-slate-50 rounded-lg text-xs border border-slate-200">
            <div>
                <div><strong>Patient:</strong> ${data.first_name} ${data.last_name}</div>
                <div><strong>MRN:</strong> ${data.mrn}</div>
                <div><strong>Age / Gender:</strong> ${data.age} yrs / ${data.gender}</div>
            </div>
            <div>
                <div><strong>Procedure:</strong> ${data.service_name} (${data.modality})</div>
                <div><strong>Referring Physician:</strong> Dr. ${data.ordering_doctor_name}</div>
                <div><strong>Clinical Indication:</strong> ${data.clinical_indication || 'N/A'}</div>
            </div>
        </div>

        ${data.technique_used ? `
        <div>
            <h4 class="text-xs font-bold uppercase text-slate-500 tracking-wider mb-1">Technique</h4>
            <p class="text-xs text-slate-700 bg-white p-2 rounded border border-slate-100">${data.technique_used}</p>
        </div>` : ''}

        <div>
            <h4 class="text-xs font-bold uppercase text-slate-500 tracking-wider mb-1">Findings</h4>
            <div class="text-xs text-slate-800 whitespace-pre-line leading-relaxed bg-white p-3 rounded border border-slate-200 font-mono">
                ${data.radiological_findings || 'No findings recorded.'}
            </div>
        </div>

        <div class="p-3 bg-indigo-50/60 rounded-lg border border-indigo-200">
            <h4 class="text-xs font-bold uppercase text-indigo-900 tracking-wider mb-1">Impression</h4>
            <div class="text-xs font-bold text-slate-900 whitespace-pre-line">
                ${data.impression || 'Normal study.'}
            </div>
        </div>

        <div class="pt-8 flex justify-between items-end text-xs border-t border-slate-200 mt-8">
            <div class="text-slate-400 text-[10px]">
                Electronic Report • Verified by PACS • Turnaround Verified
            </div>
            <div class="text-right">
                <div class="font-bold text-slate-900">Dr. ${data.radiologist_name || 'Staff Radiologist'}</div>
                <div class="text-[11px] text-slate-500">Consultant Radiologist</div>
            </div>
        </div>
    `;
    document.getElementById('modal-view-report').classList.remove('hidden');
}
</script>
