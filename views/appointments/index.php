<?php
/**
 * Hospital Management System (HMS) - Appointments & Scheduling
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$filterDate = clean_input($_GET['date'] ?? date('Y-m-d'));
$filterDoctor = (int)($_GET['doctor_id'] ?? 0);
$prefillPatientId = (int)($_GET['patient_id'] ?? 0);

// Fetch doctors list for current hospital
$hId = Tenant::id();
$docStmt = $db->prepare("
    SELECT u.id, u.full_name, u.consultation_fee, d.department_name 
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.id
    LEFT JOIN `departments` d ON u.department_id = d.id
    WHERE r.slug = 'doctor' AND u.status = 'Active' AND u.hospital_id = ?
");
$docStmt->execute([$hId]);
$doctors = $docStmt->fetchAll();

// Fetch patients for dropdown
$patStmt = $db->prepare("SELECT id, mrn, first_name, last_name, phone_primary FROM `patients` WHERE `status` = 'Active' AND hospital_id = ? ORDER BY id DESC LIMIT 50");
$patStmt->execute([$hId]);
$patients = $patStmt->fetchAll();

// Fetch appointments
$query = "
    SELECT a.*, p.mrn, p.first_name, p.last_name, p.phone_primary, p.gender, p.age,
           u.full_name AS doctor_name, u.consultation_fee, d.department_name
    FROM `appointments` a
    JOIN `patients` p ON a.patient_id = p.id
    JOIN `users` u ON a.doctor_id = u.id
    JOIN `departments` d ON a.department_id = d.id
    WHERE a.appointment_date = ? AND a.hospital_id = ?
";
$params = [$filterDate, $hId];

if ($filterDoctor > 0) {
    $query .= " AND a.doctor_id = ?";
    $params[] = $filterDoctor;
}

$printTokenId = (int)($_GET['print_token'] ?? 0);
$tokenSlipData = null;
if ($printTokenId > 0) {
    $slipStmt = $db->prepare("
        SELECT a.*, p.mrn, p.first_name, p.last_name, p.phone_primary, p.gender, p.age,
               u.full_name AS doctor_name, u.consultation_fee, d.department_name
        FROM `appointments` a
        JOIN `patients` p ON a.patient_id = p.id
        JOIN `users` u ON a.doctor_id = u.id
        JOIN `departments` d ON a.department_id = d.id
        WHERE a.id = ?
    ");
    $slipStmt->execute([$printTokenId]);
    $tokenSlipData = $slipStmt->fetch();
}

$query .= " ORDER BY a.slot_time ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Appointment Scheduling & Queue</h2>
            <p class="text-xs text-slate-500">Coordinate clinic sessions, consultation tokens and patient check-in</p>
        </div>
        <button onclick="document.getElementById('appointmentModal').classList.remove('hidden')" class="px-4 py-2 bg-medical-600 hover:bg-medical-700 text-white font-semibold text-xs rounded-lg shadow-sm transition-colors flex items-center gap-2 self-start sm:self-auto">
            <i class="fa-solid fa-calendar-plus"></i>
            <span>Book New Appointment</span>
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <form action="index.php" method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="page" value="appointments">

            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-slate-600">Date:</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
            </div>

            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-slate-600">Consultant:</label>
                <select name="doctor_id" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="">All Doctors</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>" <?= $filterDoctor === (int)$doc['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($doc['full_name']) ?> (<?= htmlspecialchars($doc['department_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg">
                Filter Schedule
            </button>
        </form>
    </div>

    <!-- Appointments List Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-sm">
                Roster for <?= format_date($filterDate) ?>
            </h3>
            <span class="text-xs text-slate-500 font-mono"><?= count($appointments) ?> Booked</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5">Token / Appt #</th>
                        <th class="px-4 py-3.5">Time Slot</th>
                        <th class="px-4 py-3.5">Patient Details</th>
                        <th class="px-4 py-3.5">Consultant Doctor</th>
                        <th class="px-4 py-3.5">Visit Type</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <i class="fa-solid fa-calendar-xmark text-3xl mb-2 text-slate-300"></i>
                                <p class="text-sm font-medium">No appointments scheduled for this date.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $apt): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-3.5">
                                <span class="w-8 h-8 rounded-lg bg-medical-50 text-medical-800 font-black text-xs inline-flex items-center justify-center border border-medical-200 mr-2">
                                    #<?= $apt['token_number'] ?>
                                </span>
                                <span class="font-mono text-slate-500"><?= htmlspecialchars($apt['appointment_number']) ?></span>
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-slate-800 font-mono">
                                <?= date('h:i A', strtotime($apt['slot_time'])) ?>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-800">
                                    <?= htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) ?>
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono">
                                    <?= htmlspecialchars($apt['mrn']) ?> &bull; <?= htmlspecialchars($apt['phone_primary']) ?>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-medium text-slate-800"><?= htmlspecialchars($apt['doctor_name']) ?></div>
                                <div class="text-[10px] text-slate-500"><?= htmlspecialchars($apt['department_name']) ?> (Fee: <?= format_currency($apt['consultation_fee']) ?>)</div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                    <?= htmlspecialchars($apt['type']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $apt['status'] === 'Checked-In' ? 'bg-sky-100 text-sky-800' : ($apt['status'] === 'Completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700') ?>">
                                    <?= htmlspecialchars($apt['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-right flex items-center justify-end gap-2">
                                <button type="button" onclick='openTokenSlip(<?= htmlspecialchars(json_encode([
                                    "apt_number" => $apt["appointment_number"],
                                    "token_number" => $apt["token_number"],
                                    "patient_name" => $apt["first_name"] . " " . $apt["last_name"],
                                    "mrn" => $apt["mrn"],
                                    "phone" => $apt["phone_primary"],
                                    "doctor_name" => $apt["doctor_name"],
                                    "department" => $apt["department_name"],
                                    "fee" => format_currency($apt["consultation_fee"]),
                                    "date" => format_date($apt["appointment_date"]),
                                    "time" => date("h:i A", strtotime($apt["slot_time"]))
                                ]), ENT_QUOTES, "UTF-8") ?>)' class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold transition-colors flex items-center gap-1" title="Print Token Slip">
                                    <i class="fa-solid fa-print text-[10px]"></i>
                                    <span>Slip</span>
                                </button>
                                <?php if ($apt['status'] !== 'Completed' && $apt['status'] !== 'Checked-In'): ?>
                                <a href="index.php?action=appointment_checkin&id=<?= $apt['id'] ?>" class="px-3 py-1 bg-medical-600 hover:bg-medical-700 text-white rounded text-xs font-semibold transition-colors">
                                    Check In
                                </a>
                                <?php else: ?>
                                <span class="text-xs text-slate-400">Checked</span>
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

<!-- Booking Modal -->
<div id="appointmentModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 <?= $prefillPatientId > 0 ? '' : 'hidden' ?>">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-800 text-base">Book Clinical Appointment</h3>
            <button onclick="document.getElementById('appointmentModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="index.php?action=appointment_store" method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Select Registered Patient <span class="text-rose-500">*</span></label>
                <select name="patient_id" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="">Choose Patient...</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $prefillPatientId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['mrn']) ?> &mdash; <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?> (<?= htmlspecialchars($p['phone_primary']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Consultant Doctor <span class="text-rose-500">*</span></label>
                <select name="doctor_id" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="">Choose Doctor...</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>">
                            <?= htmlspecialchars($doc['full_name']) ?> &mdash; <?= htmlspecialchars($doc['department_name']) ?> (<?= format_currency($doc['consultation_fee']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Appointment Date <span class="text-rose-500">*</span></label>
                    <input type="date" name="appointment_date" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Slot Time <span class="text-rose-500">*</span></label>
                    <input type="time" name="slot_time" required value="10:00" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Visit Type</label>
                <select name="type" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="New">New Consultation</option>
                    <option value="Follow-up">Follow-up Visit</option>
                    <option value="Routine">Routine Checkup</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Visit / Symptoms</label>
                <textarea name="reason_for_visit" rows="2" placeholder="Brief clinical complaints..." class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('appointmentModal').classList.add('hidden')" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-medical-600 hover:bg-medical-700 text-white rounded-lg text-xs font-bold shadow-sm">
                    Confirm Booking
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Printable Appointment & Token Slip Modal -->
<div id="tokenSlipModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 <?= $tokenSlipData ? '' : 'hidden' ?>">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 relative">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 no-print">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-ticket text-medical-600"></i>
                <span>OPD Consultation Token Slip</span>
            </h3>
            <button onclick="document.getElementById('tokenSlipModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Thermal / Slip Layout -->
        <div id="printableTokenArea" class="mt-4 p-5 border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 text-center font-sans space-y-3">
            <div class="border-b border-slate-200 pb-2">
                <div class="font-extrabold text-sm text-slate-900 tracking-tight uppercase"><?= APP_NAME ?></div>
                <div class="text-[10px] text-slate-500 font-medium">OPD Outpatient Department Token</div>
            </div>

            <div class="py-2">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Your Queue Token</div>
                <div id="slipTokenDisplay" class="text-4xl font-black font-mono text-medical-800 tracking-tight my-1">
                    <?= $tokenSlipData ? 'TOKEN #' . $tokenSlipData['token_number'] : 'TOKEN #--' ?>
                </div>
                <div class="text-[10px] text-slate-500 font-mono" id="slipAptNum">
                    <?= $tokenSlipData ? htmlspecialchars($tokenSlipData['appointment_number']) : '' ?>
                </div>
            </div>

            <div class="text-left bg-white p-3 rounded-lg border border-slate-200 text-xs space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-slate-500">Patient:</span>
                    <strong id="slipPatientName" class="text-slate-900 font-bold"><?= $tokenSlipData ? htmlspecialchars($tokenSlipData['first_name'] . ' ' . $tokenSlipData['last_name']) : '' ?></strong>
                </div>
                <div class="flex justify-between font-mono text-[11px]">
                    <span class="text-slate-500">MRN:</span>
                    <strong id="slipMRN" class="text-medical-800 font-bold"><?= $tokenSlipData ? htmlspecialchars($tokenSlipData['mrn']) : '' ?></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Doctor:</span>
                    <span id="slipDoctorName" class="font-semibold text-slate-800"><?= $tokenSlipData ? htmlspecialchars($tokenSlipData['doctor_name']) : '' ?></span>
                </div>
                <div class="flex justify-between text-[11px]">
                    <span class="text-slate-500">Department:</span>
                    <span id="slipDepartment" class="text-slate-700"><?= $tokenSlipData ? htmlspecialchars($tokenSlipData['department_name']) : '' ?></span>
                </div>
                <div class="flex justify-between text-[11px] pt-1.5 border-t border-slate-100">
                    <span class="text-slate-500">Date & Time:</span>
                    <span id="slipDateTime" class="font-semibold text-slate-800"><?= $tokenSlipData ? format_date($tokenSlipData['appointment_date']) . ' ' . date('h:i A', strtotime($tokenSlipData['slot_time'])) : '' ?></span>
                </div>
                <div class="flex justify-between text-[11px]">
                    <span class="text-slate-500">Consultation Fee:</span>
                    <strong id="slipFee" class="text-slate-900"><?= $tokenSlipData ? format_currency($tokenSlipData['consultation_fee']) : '' ?></strong>
                </div>
            </div>

            <div class="text-[10px] text-slate-400 pt-1">
                Please proceed to the OPD waiting area.<br>Listen for your token number announcement.
            </div>
        </div>

        <!-- Buttons -->
        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-end gap-2 no-print">
            <button type="button" onclick="document.getElementById('tokenSlipModal').classList.add('hidden')" class="px-3 py-1.5 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                Close
            </button>
            <button type="button" onclick="printTokenSlip()" class="px-4 py-1.5 bg-medical-600 hover:bg-medical-700 text-white rounded-lg text-xs font-bold shadow-sm flex items-center gap-1.5">
                <i class="fa-solid fa-print text-[11px]"></i>
                <span>Print Token Slip</span>
            </button>
        </div>
    </div>
</div>

<script>
function openTokenSlip(data) {
    document.getElementById('slipTokenDisplay').textContent = 'TOKEN #' + data.token_number;
    document.getElementById('slipAptNum').textContent = data.apt_number;
    document.getElementById('slipPatientName').textContent = data.patient_name;
    document.getElementById('slipMRN').textContent = data.mrn;
    document.getElementById('slipDoctorName').textContent = data.doctor_name;
    document.getElementById('slipDepartment').textContent = data.department;
    document.getElementById('slipDateTime').textContent = data.date + ' ' + data.time;
    document.getElementById('slipFee').textContent = data.fee;
    document.getElementById('tokenSlipModal').classList.remove('hidden');
}

function printTokenSlip() {
    const printContent = document.getElementById('printableTokenArea').innerHTML;
    const printWindow = window.open('', '', 'height=500,width=400');
    printWindow.document.write('<html><head><title>OPD Token Slip</title>');
    printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tailwindcss/typography@0.5.x/dist/typography.min.css">');
    printWindow.document.write('<style>body{font-family: monospace, sans-serif; padding: 15px; text-align: center;} .token{font-size: 32px; font-weight: 900;}</style>');
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
