<?php
/**
 * Hospital Management System (HMS) - Emergency & Trauma Care Workspace
 */

Auth::requireRole(['super_admin', 'hospital_admin', 'doctor', 'nurse', 'receptionist']);

$db = Database::getConnection();

$hId = Tenant::id();

// Fetch Emergency statistics for current hospital
$statsQuery = $db->query("
    SELECT 
        COUNT(CASE WHEN encounter_status = 'In-Progress' THEN 1 END) as active_cases,
        COUNT(CASE WHEN encounter_status = 'In-Progress' AND triage_level = 'ESI-1' THEN 1 END) as esi_1_count,
        COUNT(CASE WHEN encounter_status = 'In-Progress' AND triage_level = 'ESI-2' THEN 1 END) as esi_2_count,
        COUNT(CASE WHEN encounter_status = 'In-Progress' AND triage_level = 'ESI-3' THEN 1 END) as esi_3_count,
        COUNT(CASE WHEN encounter_status = 'In-Progress' AND triage_level IN ('ESI-4', 'ESI-5') THEN 1 END) as non_critical_count,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() AND encounter_status != 'In-Progress' THEN 1 END) as resolved_today
    FROM `encounters`
    WHERE `encounter_type` = 'Emergency' AND hospital_id = {$hId}
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Filters
$triageFilter = clean_input($_GET['triage'] ?? '');
$searchQuery = clean_input($_GET['q'] ?? '');

$sql = "
    SELECT 
        e.*,
        p.mrn, p.first_name, p.last_name, p.gender, p.age, p.blood_group, p.phone_primary,
        u.full_name as doctor_name,
        d.department_name,
        v.systolic_bp, v.diastolic_bp, v.pulse_rate_bpm as pulse_rate, v.spo2_percentage, v.temperature_celsius as temperature_f,
        TIMESTAMPDIFF(MINUTE, e.admission_datetime, NOW()) as wait_minutes
    FROM `encounters` e
    JOIN `patients` p ON e.patient_id = p.id
    JOIN `users` u ON e.attending_doctor_id = u.id
    JOIN `departments` d ON e.department_id = d.id
    LEFT JOIN (
        SELECT cv1.* FROM `clinical_vitals` cv1
        INNER JOIN (
            SELECT encounter_id, MAX(recorded_at) as max_rec FROM `clinical_vitals` GROUP BY encounter_id
        ) cv2 ON cv1.encounter_id = cv2.encounter_id AND cv1.recorded_at = cv2.max_rec
    ) v ON v.encounter_id = e.id
    WHERE e.encounter_type = 'Emergency' AND e.hospital_id = ?
";

$params = [$hId];

if (!empty($triageFilter)) {
    $sql .= " AND e.triage_level = ?";
    $params[] = $triageFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (p.mrn LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR e.primary_diagnosis LIKE ?)";
    $term = "%$searchQuery%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$sql .= " ORDER BY CASE 
    WHEN e.encounter_status = 'In-Progress' AND e.triage_level = 'ESI-1' THEN 1
    WHEN e.encounter_status = 'In-Progress' AND e.triage_level = 'ESI-2' THEN 2
    WHEN e.encounter_status = 'In-Progress' AND e.triage_level = 'ESI-3' THEN 3
    WHEN e.encounter_status = 'In-Progress' THEN 4
    ELSE 5 END, e.admission_datetime DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Doctors for assignment
$doctors = $db->query("SELECT id, full_name, designation FROM `users` WHERE `role_id` = 3 AND `status` = 'Active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Available Beds for Emergency Admission
$beds = $db->query("
    SELECT b.id, b.bed_number, w.ward_name, w.ward_type 
    FROM `beds` b 
    JOIN `rooms` r ON b.room_id = r.id
    JOIN `wards` w ON r.ward_id = w.id 
    WHERE b.status = 'Available' 
    ORDER BY w.ward_name ASC, b.bed_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Recent registered patients for quick intake
$patientsList = $db->query("SELECT id, mrn, first_name, last_name, phone_primary FROM `patients` ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Page Header & Action Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-600 mr-1.5 animate-pulse"></span> 24/7 Red Code Trauma
                    </span>
                    <h1 class="text-2xl font-bold text-slate-800">Emergency & Triage Bay</h1>
                </div>
                <p class="text-sm text-slate-500 mt-1">Emergency Severity Index (ESI) Triage, Trauma Bays, and Rapid Inpatient Disposition.</p>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('modal-emergency-intake').classList.remove('hidden')" class="btn-primary bg-red-600 hover:bg-red-700 text-white flex items-center gap-2 shadow-sm font-semibold">
                    <i class="fa-solid fa-truck-medical"></i>
                    <span>Rapid ER Intake</span>
                </button>
            </div>
        </div>

        <!-- Triage KPI Level Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <!-- ESI-1 -->
            <a href="index.php?page=emergency&triage=ESI-1" class="bg-white p-4 rounded-xl border border-red-200 shadow-sm hover:shadow transition-shadow relative overflow-hidden group">
                <div class="absolute top-0 left-0 w-1.5 h-full bg-red-600"></div>
                <div class="text-[11px] font-bold uppercase text-red-600 tracking-wider">ESI-1 Resuscitation</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['esi_1_count'] ?? 0) ?></div>
                <div class="text-xs text-red-500 font-medium mt-1">Immediate (0 min)</div>
            </a>

            <!-- ESI-2 -->
            <a href="index.php?page=emergency&triage=ESI-2" class="bg-white p-4 rounded-xl border border-orange-200 shadow-sm hover:shadow transition-shadow relative overflow-hidden group">
                <div class="absolute top-0 left-0 w-1.5 h-full bg-orange-500"></div>
                <div class="text-[11px] font-bold uppercase text-orange-600 tracking-wider">ESI-2 Emergent</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['esi_2_count'] ?? 0) ?></div>
                <div class="text-xs text-orange-500 font-medium mt-1">Life threat (< 10 min)</div>
            </a>

            <!-- ESI-3 -->
            <a href="index.php?page=emergency&triage=ESI-3" class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm hover:shadow transition-shadow relative overflow-hidden group">
                <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-400"></div>
                <div class="text-[11px] font-bold uppercase text-amber-600 tracking-wider">ESI-3 Urgent</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['esi_3_count'] ?? 0) ?></div>
                <div class="text-xs text-amber-600 font-medium mt-1">Multi-resource stable</div>
            </a>

            <!-- ESI-4 & 5 -->
            <a href="index.php?page=emergency" class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm hover:shadow transition-shadow relative overflow-hidden group">
                <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
                <div class="text-[11px] font-bold uppercase text-emerald-600 tracking-wider">ESI 4 & 5 Routine</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['non_critical_count'] ?? 0) ?></div>
                <div class="text-xs text-emerald-600 font-medium mt-1">Low acuity / Clinic</div>
            </a>

            <!-- Resolved Today -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1.5 h-full bg-slate-400"></div>
                <div class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Cleared / Admitted</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['resolved_today'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 font-medium mt-1">Past 24 Hours</div>
            </div>
        </div>

        <!-- Filter & Search Strip -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            <form method="GET" action="index.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <input type="hidden" name="page" value="emergency">
                
                <div class="relative flex-1 md:w-72">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search MRN, Patient Name..." class="form-input pl-8 py-1.5 text-xs w-full">
                </div>

                <select name="triage" onchange="this.form.submit()" class="form-input py-1.5 text-xs">
                    <option value="">All Triage Levels</option>
                    <option value="ESI-1" <?= $triageFilter === 'ESI-1' ? 'selected' : '' ?>>ESI-1 (Resuscitation)</option>
                    <option value="ESI-2" <?= $triageFilter === 'ESI-2' ? 'selected' : '' ?>>ESI-2 (Emergent)</option>
                    <option value="ESI-3" <?= $triageFilter === 'ESI-3' ? 'selected' : '' ?>>ESI-3 (Urgent)</option>
                    <option value="ESI-4" <?= $triageFilter === 'ESI-4' ? 'selected' : '' ?>>ESI-4 (Less Urgent)</option>
                    <option value="ESI-5" <?= $triageFilter === 'ESI-5' ? 'selected' : '' ?>>ESI-5 (Non-Urgent)</option>
                </select>

                <?php if (!empty($searchQuery) || !empty($triageFilter)): ?>
                <a href="index.php?page=emergency" class="text-xs text-slate-500 hover:text-slate-800 underline">Reset</a>
                <?php endif; ?>
            </form>

            <div class="text-xs text-slate-500 flex items-center gap-2">
                <i class="fa-solid fa-circle-info text-blue-500"></i>
                <span>Showing <strong><?= count($cases) ?></strong> Emergency Records</span>
            </div>
        </div>

        <!-- Cases Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Triage (ESI)</th>
                            <th class="py-3 px-4">Patient / MRN</th>
                            <th class="py-3 px-4">Arrival / Elapsed</th>
                            <th class="py-3 px-4">Attending Doctor</th>
                            <th class="py-3 px-4">Latest Vitals</th>
                            <th class="py-3 px-4">Chief Complaint / Diagnosis</th>
                            <th class="py-3 px-4">Encounter Status</th>
                            <th class="py-3 px-4 text-right">Emergency Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($cases)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-shield-heart text-4xl text-slate-200 mb-3 block"></i>
                                No active emergency cases found matching the criteria.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($cases as $c): ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $c['triage_level'] === 'ESI-1' ? 'bg-red-50/50' : '' ?>">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($c['triage_level'] === 'ESI-1'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-black bg-red-600 text-white shadow-sm animate-pulse">
                                        ESI-1 RESUS
                                    </span>
                                <?php elseif ($c['triage_level'] === 'ESI-2'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-orange-500 text-white shadow-sm">
                                        ESI-2 EMERGENT
                                    </span>
                                <?php elseif ($c['triage_level'] === 'ESI-3'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-400 text-slate-900">
                                        ESI-3 URGENT
                                    </span>
                                <?php elseif ($c['triage_level'] === 'ESI-4'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                        ESI-4 LESS URGENT
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-300">
                                        <?= htmlspecialchars($c['triage_level']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <a href="index.php?page=patient_profile&id=<?= $c['patient_id'] ?>" class="font-bold text-slate-900 hover:text-medical-600 block">
                                    <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                                </a>
                                <div class="text-[11px] text-slate-400">
                                    <span class="font-mono"><?= htmlspecialchars($c['mrn']) ?></span> &bull; 
                                    <?= htmlspecialchars($c['gender']) ?>, <?= (int)$c['age'] ?>y &bull;
                                    <span class="font-bold text-red-600"><?= htmlspecialchars($c['blood_group'] ?? 'Unknown') ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-800"><?= date('H:i', strtotime($c['admission_datetime'])) ?></div>
                                <div class="text-[11px] <?= $c['wait_minutes'] > 60 ? 'text-red-600 font-bold' : 'text-slate-400' ?>">
                                    <i class="fa-regular fa-clock"></i> <?= (int)$c['wait_minutes'] ?> mins ago
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-800"><?= htmlspecialchars($c['doctor_name']) ?></div>
                                <div class="text-[10px] text-slate-400">Trauma Team</div>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($c['pulse_rate'] !== null): ?>
                                    <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 text-[11px]">
                                        <span>BP: <strong><?= $c['systolic_bp'] ?>/<?= $c['diastolic_bp'] ?></strong></span>
                                        <span>HR: <strong><?= $c['pulse_rate'] ?> bpm</strong></span>
                                        <span>SpO2: <strong class="<?= $c['spo2_percentage'] < 92 ? 'text-red-600 font-bold' : '' ?>"><?= $c['spo2_percentage'] ?>%</strong></span>
                                        <span>Temp: <strong><?= $c['temperature_f'] ?>°F</strong></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-amber-600 font-medium italic"><i class="fa-solid fa-triangle-exclamation"></i> Vitals Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 max-w-xs truncate">
                                <span class="font-medium text-slate-800"><?= htmlspecialchars($c['primary_diagnosis'] ?: 'Initial Assessment Pending') ?></span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($c['encounter_status'] === 'In-Progress'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mr-1 animate-ping"></span> In ER Bay
                                    </span>
                                <?php elseif ($c['encounter_status'] === 'Admitted'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-purple-100 text-purple-800">
                                        Admitted to Ward
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        <?= htmlspecialchars($c['discharge_disposition'] ?? 'Discharged') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap space-x-1">
                                <a href="index.php?page=opd_consultation&encounter_id=<?= $c['id'] ?>" class="p-1.5 text-medical-600 hover:bg-medical-50 rounded" title="Clinical Notes & CPOE Orders">
                                    <i class="fa-solid fa-stethoscope"></i>
                                </a>

                                <?php if ($c['encounter_status'] === 'In-Progress'): ?>
                                <button onclick="openDispositionModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['first_name'] . ' ' . $c['last_name'])) ?>', '<?= $c['patient_id'] ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Emergency Disposition / Admit / Discharge">
                                    <i class="fa-solid fa-hospital-user"></i>
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

<!-- MODAL: RAPID ER INTAKE -->
<div id="modal-emergency-intake" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-red-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-truck-medical text-lg"></i>
                <h3 class="font-bold text-base">Rapid Emergency Intake & Triage</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-emergency-intake').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=emergency_intake" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <!-- Patient Mode: Select Existing vs Quick Walkin -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label text-xs">Select Existing Patient (MRN / Name)</label>
                    <select name="existing_patient_id" id="intake-patient-select" class="form-input text-xs">
                        <option value="">-- Or register quick patient below --</option>
                        <?php foreach ($patientsList as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['mrn'] . ' - ' . $p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['phone_primary'] . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label text-xs">Attending ER Physician <span class="text-red-500">*</span></label>
                    <select name="attending_doctor_id" required class="form-input text-xs">
                        <option value="">-- Assign Doctor --</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>">Dr. <?= htmlspecialchars($doc['full_name']) ?> (<?= htmlspecialchars($doc['designation']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Quick Unregistered Patient Fields (if no existing selected) -->
            <div id="quick-patient-box" class="p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
                <div class="text-[11px] font-bold text-slate-500 uppercase">Fast-Track Patient Details (If Unregistered)</div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <input type="text" name="quick_first_name" placeholder="First Name *" class="form-input text-xs">
                    </div>
                    <div>
                        <input type="text" name="quick_last_name" placeholder="Last Name" class="form-input text-xs">
                    </div>
                    <div>
                        <select name="quick_gender" class="form-input text-xs">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <input type="number" name="quick_age" placeholder="Est. Age (Years)" class="form-input text-xs">
                    </div>
                    <div>
                        <input type="text" name="quick_phone" placeholder="Contact Phone" class="form-input text-xs">
                    </div>
                    <div>
                        <select name="quick_blood_group" class="form-input text-xs">
                            <option value="Unknown">Blood Group: Unknown</option>
                            <option value="A+">A+</option><option value="A-">A-</option>
                            <option value="B+">B+</option><option value="B-">B-</option>
                            <option value="AB+">AB+</option><option value="AB-">AB-</option>
                            <option value="O+">O+</option><option value="O-">O-</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Triage Severity Selection -->
            <div>
                <label class="form-label text-xs">Emergency Severity Index (ESI Triage) <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-5 gap-2">
                    <label class="border border-red-300 rounded-lg p-2 text-center cursor-pointer hover:bg-red-50 flex flex-col items-center">
                        <input type="radio" name="triage_level" value="ESI-1" required class="mb-1 text-red-600">
                        <span class="text-xs font-black text-red-600">ESI-1</span>
                        <span class="text-[10px] text-slate-500">Resuscitation</span>
                    </label>

                    <label class="border border-orange-300 rounded-lg p-2 text-center cursor-pointer hover:bg-orange-50 flex flex-col items-center">
                        <input type="radio" name="triage_level" value="ESI-2" class="mb-1 text-orange-500">
                        <span class="text-xs font-bold text-orange-600">ESI-2</span>
                        <span class="text-[10px] text-slate-500">Emergent</span>
                    </label>

                    <label class="border border-amber-300 rounded-lg p-2 text-center cursor-pointer hover:bg-amber-50 flex flex-col items-center">
                        <input type="radio" name="triage_level" value="ESI-3" checked class="mb-1 text-amber-500">
                        <span class="text-xs font-bold text-amber-600">ESI-3</span>
                        <span class="text-[10px] text-slate-500">Urgent</span>
                    </label>

                    <label class="border border-emerald-300 rounded-lg p-2 text-center cursor-pointer hover:bg-emerald-50 flex flex-col items-center">
                        <input type="radio" name="triage_level" value="ESI-4" class="mb-1 text-emerald-600">
                        <span class="text-xs font-semibold text-emerald-600">ESI-4</span>
                        <span class="text-[10px] text-slate-500">Less Urgent</span>
                    </label>

                    <label class="border border-slate-300 rounded-lg p-2 text-center cursor-pointer hover:bg-slate-50 flex flex-col items-center">
                        <input type="radio" name="triage_level" value="ESI-5" class="mb-1 text-slate-600">
                        <span class="text-xs font-semibold text-slate-600">ESI-5</span>
                        <span class="text-[10px] text-slate-500">Non-Urgent</span>
                    </label>
                </div>
            </div>

            <!-- Chief Complaint & Primary Assessment -->
            <div>
                <label class="form-label text-xs">Chief Complaint / Trauma Mechanism <span class="text-red-500">*</span></label>
                <textarea name="chief_complaint" rows="2" required placeholder="e.g. Acute chest pain radiating to left arm, RTA polytrauma, shortness of breath..." class="form-input text-xs"></textarea>
            </div>

            <!-- Rapid Vitals -->
            <div>
                <label class="form-label text-xs">Initial Triage Vitals (Optional / Acute)</label>
                <div class="grid grid-cols-4 gap-3">
                    <div>
                        <input type="number" name="systolic_bp" placeholder="Sys BP (mmHg)" class="form-input text-xs">
                    </div>
                    <div>
                        <input type="number" name="diastolic_bp" placeholder="Dia BP (mmHg)" class="form-input text-xs">
                    </div>
                    <div>
                        <input type="number" name="pulse_rate" placeholder="Pulse (bpm)" class="form-input text-xs">
                    </div>
                    <div>
                        <input type="number" name="spo2_percentage" placeholder="SpO2 (%)" class="form-input text-xs">
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-emergency-intake').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-red-600 hover:bg-red-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Register & Admit to Bay
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EMERGENCY DISPOSITION -->
<div id="modal-emergency-disposition" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-slate-800 text-white flex items-center justify-between">
            <div>
                <h3 class="font-bold text-base">Emergency Case Disposition</h3>
                <p id="disp-patient-name" class="text-xs text-slate-300"></p>
            </div>
            <button type="button" onclick="document.getElementById('modal-emergency-disposition').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=emergency_disposition" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="encounter_id" id="disp-encounter-id">
            <input type="hidden" name="patient_id" id="disp-patient-id">

            <div>
                <label class="form-label text-xs">Primary Final Diagnosis <span class="text-red-500">*</span></label>
                <input type="text" name="primary_diagnosis" required placeholder="e.g. Acute Coronary Syndrome, Closed Femur Fracture" class="form-input text-xs">
            </div>

            <div>
                <label class="form-label text-xs">Disposition Decision <span class="text-red-500">*</span></label>
                <select name="discharge_disposition" id="disp-select" onchange="toggleBedSelection(this.value)" required class="form-input text-xs font-semibold">
                    <option value="Discharged_Home">Discharged Home (Stable)</option>
                    <option value="Admitted_to_Ward">Admit to Inpatient Ward (IPD)</option>
                    <option value="Admitted_to_ICU">Admit to Intensive Care Unit (ICU)</option>
                    <option value="Transferred">Transferred to Tertiary Center</option>
                    <option value="LAMA">Left Against Medical Advice (LAMA)</option>
                    <option value="Deceased">Brought Dead / Expired</option>
                </select>
            </div>

            <!-- Inpatient Bed Rack Allocation (Shown only if Admitted) -->
            <div id="bed-allocation-box" class="hidden p-3 bg-purple-50 rounded-lg border border-purple-200 space-y-2">
                <label class="form-label text-xs font-bold text-purple-900">Select Available Inpatient Bed</label>
                <select name="bed_id" class="form-input text-xs bg-white">
                    <option value="">-- Choose Bed --</option>
                    <?php foreach ($beds as $b): ?>
                    <option value="<?= $b['id'] ?>">
                        <?= htmlspecialchars($b['ward_name']) ?> &bull; Bed <?= htmlspecialchars($b['bed_number']) ?> (<?= htmlspecialchars($b['ward_type']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label text-xs">Clinical Summary / Instructions</label>
                <textarea name="clinical_summary" rows="3" placeholder="Condition at disposition, instructions, referral summary..." class="form-input text-xs"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-emergency-disposition').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary text-xs font-bold">
                    <i class="fa-solid fa-save mr-1"></i> Save Disposition
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDispositionModal(encounterId, patientName, patientId) {
    document.getElementById('disp-encounter-id').value = encounterId;
    document.getElementById('disp-patient-id').value = patientId;
    document.getElementById('disp-patient-name').textContent = 'Patient: ' + patientName;
    document.getElementById('modal-emergency-disposition').classList.remove('hidden');
}

function toggleBedSelection(val) {
    const box = document.getElementById('bed-allocation-box');
    if (val === 'Admitted_to_Ward' || val === 'Admitted_to_ICU') {
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
    }
}
</script>
