<?php
/**
 * Hospital Management System (HMS) - Operation Theatre (OT) & Surgical Suite
 */

Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse']);

$db = Database::getConnection();

// OT Rooms status
$otRooms = $db->query("SELECT * FROM `ot_rooms` ORDER BY room_number ASC")->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$statsQuery = $db->query("
    SELECT 
        COUNT(*) as total_surgeries,
        COUNT(CASE WHEN booking_status = 'Scheduled' AND DATE(scheduled_datetime) = CURDATE() THEN 1 END) as today_scheduled,
        COUNT(CASE WHEN booking_status = 'In-Progress' THEN 1 END) as in_surgery_count,
        COUNT(CASE WHEN booking_status = 'Completed' AND DATE(scheduled_datetime) = CURDATE() THEN 1 END) as completed_today
    FROM `surgery_bookings`
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Surgery Bookings
$statusFilter = clean_input($_GET['status'] ?? '');
$roomFilter = clean_input($_GET['room'] ?? '');
$searchQuery = clean_input($_GET['q'] ?? '');

$sql = "
    SELECT 
        sb.*,
        r.room_name, r.room_number, r.theatre_type,
        p.mrn, p.first_name, p.last_name, p.gender, p.age, p.blood_group,
        u_surg.full_name as lead_surgeon_name,
        u_anes.full_name as anesthesiologist_name,
        sr.id as record_id, sr.start_time, sr.end_time, sr.operative_notes, sr.post_op_diagnosis, sr.blood_loss_ml, sr.post_op_destination
    FROM `surgery_bookings` sb
    JOIN `ot_rooms` r ON sb.ot_room_id = r.id
    JOIN `patients` p ON sb.patient_id = p.id
    JOIN `users` u_surg ON sb.lead_surgeon_id = u_surg.id
    LEFT JOIN `users` u_anes ON sb.anesthesiologist_id = u_anes.id
    LEFT JOIN `surgical_records` sr ON sr.surgery_booking_id = sb.id
    WHERE 1=1
";

$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND sb.booking_status = ?";
    $params[] = $statusFilter;
}

if (!empty($roomFilter)) {
    $sql .= " AND sb.ot_room_id = ?";
    $params[] = $roomFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (sb.booking_number LIKE ? OR p.mrn LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR sb.procedure_name LIKE ?)";
    $term = "%$searchQuery%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$sql .= " ORDER BY CASE WHEN sb.booking_status = 'In-Progress' THEN 1 WHEN sb.booking_status = 'Scheduled' THEN 2 ELSE 3 END, sb.scheduled_datetime ASC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Surgeons & Anesthetists
$surgeons = $db->query("SELECT id, full_name, designation FROM `users` WHERE `role_id` = 3 AND `status` = 'Active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Patients with active encounters
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
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-100 text-teal-800 border border-teal-200">
                        <i class="fa-solid fa-syringe mr-1.5 text-teal-600"></i> Surgical Suites
                    </span>
                    <h1 class="text-2xl font-bold text-slate-800">Operation Theatre (OT) Management</h1>
                </div>
                <p class="text-sm text-slate-500 mt-1">Surgical Scheduling, Operating Room Readiness, Anesthesia Records & WHO Safety Checklists.</p>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('modal-schedule-surgery').classList.remove('hidden')" class="btn-primary bg-teal-700 hover:bg-teal-800 text-white flex items-center gap-2 shadow-sm font-semibold">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>Schedule Surgery</span>
                </button>
            </div>
        </div>

        <!-- OT Rooms Live Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($otRooms as $room): ?>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden flex flex-col justify-between">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs font-mono font-bold text-slate-400"><?= htmlspecialchars($room['room_number']) ?></div>
                        <h3 class="font-bold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($room['room_name']) ?></h3>
                        <div class="text-[11px] text-slate-500 mt-0.5 capitalize"><?= str_replace('_', ' ', $room['theatre_type']) ?></div>
                    </div>
                    <div>
                        <?php if ($room['status'] === 'Available'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span> Available
                            </span>
                        <?php elseif ($room['status'] === 'In_Surgery'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 animate-pulse">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-600 mr-1.5"></span> In Surgery
                            </span>
                        <?php elseif ($room['status'] === 'Sterilization'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600 mr-1.5"></span> Sterilizing
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                                Maintenance
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-400">Change Status:</span>
                    <form method="POST" action="index.php?action=ot_room_status" class="flex items-center gap-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                        <select name="status" onchange="this.form.submit()" class="text-[11px] py-0.5 px-2 rounded border border-slate-200 bg-slate-50 text-slate-700 font-semibold cursor-pointer">
                            <option value="Available" <?= $room['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="In_Surgery" <?= $room['status'] === 'In_Surgery' ? 'selected' : '' ?>>In Surgery</option>
                            <option value="Sterilization" <?= $room['status'] === 'Sterilization' ? 'selected' : '' ?>>Sterilization</option>
                            <option value="Maintenance" <?= $room['status'] === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                        </select>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- KPIs Strip -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Today Scheduled</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['today_scheduled'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 font-medium mt-1">Elective & Emergency Cases</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-red-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-red-600 tracking-wider">Active In-Surgery</div>
                <div class="text-2xl font-black text-red-600 mt-1"><?= (int)($stats['in_surgery_count'] ?? 0) ?></div>
                <div class="text-xs text-red-500 font-medium mt-1">Under Anesthesia Now</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-emerald-600 tracking-wider">Completed Today</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['completed_today'] ?? 0) ?></div>
                <div class="text-xs text-emerald-600 font-medium mt-1">Moved to PACU/Ward</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Surgeries</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['total_surgeries'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 font-medium mt-1">All-time Logged</div>
            </div>
        </div>

        <!-- Filter & Search Strip -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            <form method="GET" action="index.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <input type="hidden" name="page" value="ot">
                
                <div class="relative flex-1 md:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search Booking #, Patient, Procedure..." class="form-input pl-8 py-1.5 text-xs w-full">
                </div>

                <select name="status" onchange="this.form.submit()" class="form-input py-1.5 text-xs">
                    <option value="">All Booking Statuses</option>
                    <option value="Scheduled" <?= $statusFilter === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="In-Progress" <?= $statusFilter === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                    <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <select name="room" onchange="this.form.submit()" class="form-input py-1.5 text-xs">
                    <option value="">All OT Rooms</option>
                    <?php foreach ($otRooms as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $roomFilter == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['room_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($roomFilter)): ?>
                <a href="index.php?page=ot" class="text-xs text-slate-500 hover:text-slate-800 underline">Reset</a>
                <?php endif; ?>
            </form>

            <div class="text-xs text-slate-500">
                Found <strong><?= count($bookings) ?></strong> surgical bookings.
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Booking #</th>
                            <th class="py-3 px-4">Scheduled Time</th>
                            <th class="py-3 px-4">OT Room</th>
                            <th class="py-3 px-4">Patient (MRN)</th>
                            <th class="py-3 px-4">Procedure & Pre-Op Diagnosis</th>
                            <th class="py-3 px-4">Surgical Team</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-procedures text-4xl text-slate-200 mb-3 block"></i>
                                No surgical bookings found in this roster.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $b['booking_status'] === 'In-Progress' ? 'bg-red-50/40' : '' ?>">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                <?= htmlspecialchars($b['booking_number']) ?>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-bold text-slate-900"><?= date('d M Y', strtotime($b['scheduled_datetime'])) ?></div>
                                <div class="text-[11px] text-teal-700 font-semibold"><?= date('h:i A', strtotime($b['scheduled_datetime'])) ?> (<?= (int)$b['estimated_duration_minutes'] ?>m)</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-slate-800"><?= htmlspecialchars($b['room_number']) ?></span>
                                <div class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($b['room_name']) ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <a href="index.php?page=patient_profile&id=<?= $b['patient_id'] ?>" class="font-bold text-slate-900 hover:text-medical-600 block">
                                    <?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?>
                                </a>
                                <div class="text-[11px] text-slate-400 font-mono">
                                    <?= htmlspecialchars($b['mrn']) ?> &bull; 
                                    <span class="font-bold text-red-600"><?= htmlspecialchars($b['blood_group'] ?? '') ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($b['procedure_name']) ?></div>
                                <div class="text-[11px] text-slate-500 italic truncate max-w-xs">Pre-Op: <?= htmlspecialchars($b['pre_op_diagnosis'] ?: 'Elective') ?></div>
                                <div class="text-[10px] text-slate-400">Anesth: <strong><?= htmlspecialchars($b['anesthesia_type']) ?></strong></div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-800">Surg: Dr. <?= htmlspecialchars($b['lead_surgeon_name']) ?></div>
                                <div class="text-[11px] text-slate-500">Anesth: <?= $b['anesthesiologist_name'] ? 'Dr. ' . htmlspecialchars($b['anesthesiologist_name']) : '<span class="text-amber-600 italic">Unassigned</span>' ?></div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($b['booking_status'] === 'In-Progress'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-800 animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-600 mr-1"></span> In-Progress
                                    </span>
                                <?php elseif ($b['booking_status'] === 'Completed'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check mr-1"></i> Completed
                                    </span>
                                <?php elseif ($b['booking_status'] === 'Cancelled'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">
                                        Cancelled
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800">
                                        Scheduled
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap space-x-1">
                                <?php if ($b['booking_status'] === 'Scheduled'): ?>
                                    <form method="POST" action="index.php?action=ot_start_surgery" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-secondary py-1 px-2 text-xs text-red-600 hover:bg-red-50 border-red-200 font-semibold" title="Start Surgery in OT">
                                            <i class="fa-solid fa-play mr-1"></i> Start
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($b['booking_status'] === 'In-Progress'): ?>
                                    <button onclick="openOperativeNotesModal(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['booking_number'])) ?>', '<?= htmlspecialchars(addslashes($b['procedure_name'])) ?>', '<?= htmlspecialchars(addslashes($b['first_name'] . ' ' . $b['last_name'])) ?>')" class="btn-primary bg-emerald-600 hover:bg-emerald-700 py-1 px-2 text-xs text-white font-semibold" title="Complete & Save Operative Record">
                                        <i class="fa-solid fa-clipboard-check mr-1"></i> Record & Finish
                                    </button>
                                <?php endif; ?>

                                <?php if ($b['booking_status'] === 'Completed'): ?>
                                    <button onclick="viewOperativeRecord(<?= htmlspecialchars(json_encode($b)) ?>)" class="btn-secondary py-1 px-2 text-xs" title="View Surgical & Operative Record">
                                        <i class="fa-solid fa-file-waveform text-teal-700 mr-1"></i> Operative Note
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

<!-- MODAL: SCHEDULE SURGERY -->
<div id="modal-schedule-surgery" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-teal-800 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-syringe text-lg"></i>
                <h3 class="font-bold text-base">Schedule Surgical Procedure</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-schedule-surgery').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=ot_booking_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label text-xs">Select Patient Encounter <span class="text-red-500">*</span></label>
                    <select name="encounter_id" required class="form-input text-xs">
                        <option value="">-- Choose Encounter --</option>
                        <?php foreach ($encounters as $enc): ?>
                        <option value="<?= $enc['encounter_id'] ?>">
                            <?= htmlspecialchars($enc['first_name'] . ' ' . $enc['last_name']) ?> (<?= htmlspecialchars($enc['mrn']) ?>) &bull; <?= htmlspecialchars($enc['encounter_number']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label text-xs">Select Operating Suite (OT) <span class="text-red-500">*</span></label>
                    <select name="ot_room_id" required class="form-input text-xs">
                        <option value="">-- Choose OT Suite --</option>
                        <?php foreach ($otRooms as $r): ?>
                        <option value="<?= $r['id'] ?>">
                            <?= htmlspecialchars($r['room_name']) ?> (<?= htmlspecialchars($r['room_number']) ?>) [<?= htmlspecialchars($r['status']) ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Surgical Procedure Name <span class="text-red-500">*</span></label>
                <input type="text" name="procedure_name" required placeholder="e.g. Laparoscopic Cholecystectomy, Open Reduction & Internal Fixation (ORIF)" class="form-input text-xs">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label text-xs">Lead Surgeon <span class="text-red-500">*</span></label>
                    <select name="lead_surgeon_id" required class="form-input text-xs">
                        <option value="">-- Select Surgeon --</option>
                        <?php foreach ($surgeons as $s): ?>
                        <option value="<?= $s['id'] ?>">Dr. <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['designation']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label text-xs">Consultant Anesthesiologist</label>
                    <select name="anesthesiologist_id" class="form-input text-xs">
                        <option value="">-- Select Anesthesiologist --</option>
                        <?php foreach ($surgeons as $s): ?>
                        <option value="<?= $s['id'] ?>">Dr. <?= htmlspecialchars($s['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label text-xs">Scheduled Datetime <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="scheduled_datetime" value="<?= date('Y-m-d\T09:00') ?>" required class="form-input text-xs">
                </div>

                <div>
                    <label class="form-label text-xs">Est. Duration (Mins)</label>
                    <input type="number" name="estimated_duration_minutes" value="120" class="form-input text-xs">
                </div>

                <div>
                    <label class="form-label text-xs">Anesthesia Type</label>
                    <select name="anesthesia_type" class="form-input text-xs">
                        <option value="General">General Anesthesia (GA)</option>
                        <option value="Spinal">Spinal Block</option>
                        <option value="Epidural">Epidural</option>
                        <option value="Local">Local Anesthesia</option>
                        <option value="Sedation">Procedural Sedation</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Pre-Operative Clinical Diagnosis</label>
                <input type="text" name="pre_op_diagnosis" placeholder="e.g. Symptomatic Cholelithiasis with Chronic Cholecystitis" class="form-input text-xs">
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-schedule-surgery').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Confirm & Book OT
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: INTRA-OPERATIVE & OPERATIVE RECORD -->
<div id="modal-operative-record" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div>
                <h3 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-file-signature text-teal-400"></i>
                    <span>Operative Notes & WHO Surgical Safety Checklist</span>
                </h3>
                <p id="rec-subheading" class="text-xs text-slate-300 mt-0.5"></p>
            </div>
            <button type="button" onclick="document.getElementById('modal-operative-record').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=ot_record_store" class="p-6 space-y-4 overflow-y-auto">
            <?= csrf_field() ?>
            <input type="hidden" name="surgery_booking_id" id="rec-booking-id">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label text-xs">Incision Start Time <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="start_time" value="<?= date('Y-m-d\TH:i') ?>" required class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Surgery End Time <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="end_time" value="<?= date('Y-m-d\TH:i') ?>" required class="form-input text-xs">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Post-Operative Final Diagnosis <span class="text-red-500">*</span></label>
                <input type="text" name="post_op_diagnosis" required placeholder="e.g. Acute Gangrenous Appendicitis without perforation" class="form-input text-xs">
            </div>

            <div>
                <label class="form-label text-xs">Operative Findings & Surgical Procedure Notes <span class="text-red-500">*</span></label>
                <textarea name="operative_notes" rows="6" required placeholder="Detailed operative narrative: position, prepping/draping, incision, steps, findings, closure..." class="form-input text-xs font-mono"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label text-xs">Est. Blood Loss (ml)</label>
                    <input type="number" name="blood_loss_ml" value="50" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Implants / Prosthetics Used</label>
                    <input type="text" name="implant_details" placeholder="e.g. Mesh 15x15cm, K-wire, None" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Post-Op Disposition</label>
                    <select name="post_op_destination" class="form-input text-xs font-semibold">
                        <option value="PACU_Recovery">PACU / Recovery Bay</option>
                        <option value="Surgical_ICU">Surgical ICU (Critical)</option>
                        <option value="Ward">Inpatient Surgical Ward</option>
                    </select>
                </div>
            </div>

            <!-- WHO Surgical Safety Checklist -->
            <div class="p-3 bg-teal-50 rounded-lg border border-teal-200">
                <div class="text-[11px] font-bold text-teal-900 uppercase mb-2">WHO Surgical Safety Checklist Verification</div>
                <label class="flex items-center gap-2 cursor-pointer text-xs text-teal-950">
                    <input type="checkbox" name="sponge_swab_count_correct" value="1" checked class="rounded text-teal-700">
                    <span><strong>Sponge, Needle & Swab Count:</strong> Verified correct by scrub nurse and surgeon prior to wound closure.</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-operative-record').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-check-double mr-1"></i> Sign Off & Complete Surgery
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: VIEW OPERATIVE RECORD -->
<div id="modal-view-op-record" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 bg-slate-800 text-white flex items-center justify-between no-print">
            <h3 class="font-bold text-base">Operative Record & Surgical Summary</h3>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="btn-secondary text-xs py-1 px-2.5">
                    <i class="fa-solid fa-print mr-1"></i> Print
                </button>
                <button type="button" onclick="document.getElementById('modal-view-op-record').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <div class="p-8 overflow-y-auto space-y-6 text-slate-800" id="print-op-body">
            <!-- Loaded by JS -->
        </div>
    </div>
</div>

<script>
function openOperativeNotesModal(bookingId, bookingNum, procedureName, patientName) {
    document.getElementById('rec-booking-id').value = bookingId;
    document.getElementById('rec-subheading').textContent = bookingNum + ' • ' + procedureName + ' • ' + patientName;
    document.getElementById('modal-operative-record').classList.remove('hidden');
}

function viewOperativeRecord(data) {
    const body = document.getElementById('print-op-body');
    body.innerHTML = `
        <div class="border-b-2 border-slate-900 pb-4 flex justify-between items-start">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase">OPERATION THEATRE & SURGICAL SUITE</h2>
                <div class="text-xs text-slate-500">Official Intra-Operative & Surgical Record</div>
            </div>
            <div class="text-right font-mono text-xs">
                <div><strong>Booking #:</strong> ${data.booking_number}</div>
                <div><strong>OT Suite:</strong> ${data.room_number}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 p-3 bg-slate-50 rounded-lg text-xs border border-slate-200">
            <div>
                <div><strong>Patient:</strong> ${data.first_name} ${data.last_name}</div>
                <div><strong>MRN:</strong> ${data.mrn} &bull; Blood: <strong>${data.blood_group || 'N/A'}</strong></div>
                <div><strong>Lead Surgeon:</strong> Dr. ${data.lead_surgeon_name}</div>
                <div><strong>Anesthetist:</strong> Dr. ${data.anesthesiologist_name || 'N/A'}</div>
            </div>
            <div>
                <div><strong>Procedure:</strong> ${data.procedure_name}</div>
                <div><strong>Anesthesia:</strong> ${data.anesthesia_type}</div>
                <div><strong>Post-Op Diagnosis:</strong> ${data.post_op_diagnosis || 'Completed'}</div>
                <div><strong>Blood Loss:</strong> ${data.blood_loss_ml || 0} ml &bull; Destination: ${data.post_op_destination || 'PACU'}</div>
            </div>
        </div>

        <div>
            <h4 class="text-xs font-bold uppercase text-slate-500 tracking-wider mb-1">Operative Narrative</h4>
            <div class="text-xs text-slate-800 whitespace-pre-line leading-relaxed bg-white p-3 rounded border border-slate-200 font-mono">
                ${data.operative_notes || 'No operative notes recorded.'}
            </div>
        </div>

        <div class="p-3 bg-teal-50 rounded-lg border border-teal-200 text-xs text-teal-900">
            <strong>WHO Surgical Safety Checklist:</strong> Sponge, needle, and instrument counts verified correct before wound closure.
        </div>

        <div class="pt-8 flex justify-between items-end text-xs border-t border-slate-200 mt-8">
            <div class="text-slate-400 text-[10px]">
                Hospital Management System • Surgical Service Unit
            </div>
            <div class="text-right">
                <div class="font-bold text-slate-900">Dr. ${data.lead_surgeon_name}</div>
                <div class="text-[11px] text-slate-500">Consultant Surgeon</div>
            </div>
        </div>
    `;
    document.getElementById('modal-view-op-record').classList.remove('hidden');
}
</script>
