<?php
/**
 * Hospital Management System (HMS) - Inpatient (IPD) & Bed Rack Matrix
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

// Fetch Wards and their Beds
$wards = $db->query("SELECT * FROM `wards` WHERE `status` = 'Active' ORDER BY id ASC")->fetchAll();

// Fetch Bed counts by status
$statusCounts = $db->query("
    SELECT `status`, COUNT(*) AS count 
    FROM `beds` 
    GROUP BY `status`
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch patients for admission modal
$patients = $db->query("SELECT id, mrn, first_name, last_name, gender, age FROM `patients` WHERE `status` = 'Active' ORDER BY id DESC LIMIT 50")->fetchAll();

// Fetch doctors
$doctors = $db->query("
    SELECT u.id, u.full_name, d.department_name 
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.id
    LEFT JOIN `departments` d ON u.department_id = d.id
    WHERE r.slug IN ('doctor', 'hospital_admin') AND u.status = 'Active'
")->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Interactive Bed Rack Matrix (IPD)</h2>
            <p class="text-xs text-slate-500">Real-time facility bed allocation, inpatient transfers, and ward occupancy census</p>
        </div>
        <button onclick="document.getElementById('admitModal').classList.remove('hidden')" class="px-4 py-2 bg-medical-600 hover:bg-medical-700 text-white font-semibold text-xs rounded-lg shadow-sm transition-colors flex items-center gap-2 self-start sm:self-auto">
            <i class="fa-solid fa-hospital-user"></i>
            <span>Admit Inpatient</span>
        </button>
    </div>

    <!-- Status Legend & Counters -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4 text-xs">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-emerald-500 shadow-xs"></span>
                <span class="font-medium text-slate-700">Available (<strong class="text-emerald-700"><?= $statusCounts['Available'] ?? 0 ?></strong>)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-rose-500 shadow-xs"></span>
                <span class="font-medium text-slate-700">Occupied (<strong class="text-rose-700"><?= $statusCounts['Occupied'] ?? 0 ?></strong>)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-amber-500 shadow-xs"></span>
                <span class="font-medium text-slate-700">Reserved (<strong class="text-amber-700"><?= $statusCounts['Reserved'] ?? 0 ?></strong>)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-orange-500 shadow-xs"></span>
                <span class="font-medium text-slate-700">Cleaning / Turnaround (<strong class="text-orange-700"><?= $statusCounts['Cleaning'] ?? 0 ?></strong>)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-slate-400 shadow-xs"></span>
                <span class="font-medium text-slate-700">Maintenance (<strong class="text-slate-600"><?= $statusCounts['Maintenance'] ?? 0 ?></strong>)</span>
            </div>
        </div>
    </div>

    <!-- Wards and Bed Grid -->
    <div class="space-y-6">
        <?php foreach ($wards as $ward): 
            // Fetch beds in this ward with patient details if occupied
            $bedsStmt = $db->prepare("
                SELECT b.*, r.room_number, r.room_type,
                       p.id AS patient_id, p.first_name, p.last_name, p.mrn, p.gender, p.age,
                       a.id AS admission_id, a.admission_datetime, u.full_name AS doctor_name
                FROM `beds` b
                JOIN `rooms` r ON b.room_id = r.id
                LEFT JOIN `encounters` e ON b.current_encounter_id = e.id
                LEFT JOIN `patients` p ON e.patient_id = p.id
                LEFT JOIN `admissions` a ON a.encounter_id = e.id
                LEFT JOIN `users` u ON a.admitting_doctor_id = u.id
                WHERE r.ward_id = ?
                ORDER BY b.id ASC
            ");
            $bedsStmt->execute([$ward['id']]);
            $beds = $bedsStmt->fetchAll();
        ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
            <!-- Ward Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-hospital"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($ward['ward_name']) ?></h3>
                        <p class="text-[11px] text-slate-500">Floor: <?= htmlspecialchars($ward['floor_number']) ?> &bull; Type: <?= htmlspecialchars($ward['ward_type']) ?></p>
                    </div>
                </div>
                <span class="text-xs font-mono font-semibold text-slate-600">
                    <?= count($beds) ?> Beds Listed
                </span>
            </div>

            <!-- Beds Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($beds as $bed): 
                    $isOccupied = ($bed['status'] === 'Occupied');
                    $isAvailable = ($bed['status'] === 'Available');
                    $isCleaning = ($bed['status'] === 'Cleaning');
                    $isReserved = ($bed['status'] === 'Reserved');

                    $cardBorder = $isOccupied ? 'border-rose-300 bg-rose-50/40' : 
                                 ($isAvailable ? 'border-emerald-200 bg-emerald-50/30 hover:border-emerald-400' : 
                                 ($isCleaning ? 'border-orange-200 bg-orange-50/30' : 'border-amber-200 bg-amber-50/30'));
                    
                    $badgeBg = $isOccupied ? 'bg-rose-500 text-white' : 
                              ($isAvailable ? 'bg-emerald-500 text-white' : 
                              ($isCleaning ? 'bg-orange-500 text-white' : 'bg-amber-500 text-white'));
                ?>
                <div class="border <?= $cardBorder ?> rounded-xl p-4 transition-all flex flex-col justify-between relative shadow-xs">
                    <div>
                        <!-- Bed Number & Status Badge -->
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-mono font-bold text-sm text-slate-800"><?= htmlspecialchars($bed['bed_number']) ?></span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $badgeBg ?>">
                                <?= htmlspecialchars($bed['status']) ?>
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-500 mb-3 font-mono">
                            <?= htmlspecialchars($bed['room_number']) ?> &bull; <?= format_currency($bed['daily_tariff']) ?>/day
                        </div>

                        <!-- Occupant Details if Occupied -->
                        <?php if ($isOccupied && $bed['patient_id']): ?>
                            <div class="p-2.5 bg-white border border-rose-200 rounded-lg text-xs space-y-1">
                                <div class="font-bold text-slate-900 truncate">
                                    <a href="index.php?page=patient_profile&id=<?= $bed['patient_id'] ?>" class="hover:text-medical-600">
                                        <?= htmlspecialchars($bed['first_name'] . ' ' . $bed['last_name']) ?>
                                    </a>
                                </div>
                                <div class="font-mono text-[10px] text-medical-800 font-semibold"><?= htmlspecialchars($bed['mrn']) ?></div>
                                <div class="text-[10px] text-slate-500">
                                    Admitted: <?= format_date($bed['admission_datetime']) ?>
                                </div>
                                <div class="text-[10px] text-slate-600 truncate">
                                    Doctor: <?= htmlspecialchars($bed['doctor_name'] ?? 'Attending') ?>
                                </div>
                            </div>
                        <?php elseif ($isCleaning): ?>
                            <div class="p-3 bg-white/70 border border-orange-200 rounded-lg text-center text-xs text-orange-800">
                                <i class="fa-solid fa-broom text-base mb-1 text-orange-500"></i>
                                <p class="text-[11px]">Sanitization & Linen Change Required</p>
                            </div>
                        <?php else: ?>
                            <div class="p-3 bg-white/70 border border-emerald-200 rounded-lg text-center text-xs text-emerald-800">
                                <i class="fa-solid fa-bed text-base mb-1 text-emerald-600"></i>
                                <p class="text-[11px] font-medium">Ready for Inpatient Intake</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Bar on Card -->
                    <div class="pt-3 mt-3 border-t border-slate-200/60 flex items-center justify-between text-xs">
                        <?php if ($isAvailable): ?>
                            <button onclick="openAdmitWithBed(<?= $bed['id'] ?>)" class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-center font-semibold text-[11px] transition-colors">
                                Admit Patient
                            </button>
                        <?php elseif ($isOccupied): ?>
                            <div class="w-full flex items-center gap-1.5">
                                <a href="index.php?action=ipd_discharge&bed_id=<?= $bed['id'] ?>" onclick="return confirm('Confirm medical discharge for this inpatient? Bed will be marked for cleaning.')" class="flex-1 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-center font-semibold text-[11px] transition-colors">
                                    Discharge
                                </a>
                            </div>
                        <?php elseif ($isCleaning): ?>
                            <a href="index.php?action=bed_mark_available&id=<?= $bed['id'] ?>" class="w-full py-1 bg-slate-800 hover:bg-slate-900 text-white rounded text-center font-semibold text-[11px] transition-colors">
                                Mark Available
                            </a>
                        <?php else: ?>
                            <span class="text-[11px] text-slate-400 text-center w-full">No Actions</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Inpatient Admission Modal -->
<div id="admitModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-800 text-base">Inpatient Admission (IPD)</h3>
            <button onclick="document.getElementById('admitModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="index.php?action=ipd_admit" method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Select Patient <span class="text-rose-500">*</span></label>
                <select name="patient_id" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="">Choose Patient...</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['mrn']) ?> &mdash; <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?> (<?= htmlspecialchars($p['gender']) ?>, <?= $p['age'] ?>y)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Assign Bed <span class="text-rose-500">*</span></label>
                <select name="bed_id" id="modalBedSelect" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="">Choose Available Bed...</option>
                    <?php 
                    $availBeds = $db->query("
                        SELECT b.id, b.bed_number, b.daily_tariff, w.ward_name, r.room_number 
                        FROM `beds` b 
                        JOIN `rooms` r ON b.room_id = r.id 
                        JOIN `wards` w ON r.ward_id = w.id 
                        WHERE b.status = 'Available'
                    ")->fetchAll();
                    foreach ($availBeds as $ab): ?>
                        <option value="<?= $ab['id'] ?>">
                            <?= htmlspecialchars($ab['bed_number']) ?> &mdash; <?= htmlspecialchars($ab['ward_name']) ?> (<?= format_currency($ab['daily_tariff']) ?>/day)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Admitting Consultant Doctor <span class="text-rose-500">*</span></label>
                <select name="admitting_doctor_id" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="">Choose Doctor...</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>">
                            <?= htmlspecialchars($doc['full_name']) ?> (<?= htmlspecialchars($doc['department_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Clinical Indication / Reason for Admission <span class="text-rose-500">*</span></label>
                <textarea name="admission_reason" required rows="2" placeholder="e.g. Post-operative observation, IV antibiotic therapy" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('admitModal').classList.add('hidden')" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-medical-600 hover:bg-medical-700 text-white rounded-lg text-xs font-bold shadow-sm">
                    Confirm Admission
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAdmitWithBed(bedId) {
        document.getElementById('modalBedSelect').value = bedId;
        document.getElementById('admitModal').classList.remove('hidden');
    }
</script>
