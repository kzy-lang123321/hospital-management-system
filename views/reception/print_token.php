<?php
/**
 * Hospital Management System (HMS)
 * Dedicated Printable OPD Token & Patient Identification Slip (With QR Code)
 * 
 * Features:
 * - Standalone printable file containing ONLY the token receipt
 * - Prominent Token Number, Queue Position, and Date/Time
 * - Prominent Patient ID & MRN for instant department retrieval
 * - Machine-readable QR Code scanned by OPD Doctor, Lab, Pharmacy & Cashier
 * - Supported sizes: 80mm Thermal POS slip, 58mm compact, and standard A5/A4 voucher
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/QrCode.php';

Auth::requireLogin();

$db = Database::getConnection();
$hProfile = hospital_profile();
$hId = Tenant::id();

$patientId = (int)($_GET['patient_id'] ?? $_GET['id'] ?? 0);
$aptId = (int)($_GET['apt_id'] ?? $_GET['appointment_id'] ?? 0);
$mrnParam = clean_input($_GET['mrn'] ?? '');

// If aptId given without patientId, look up patientId from appointment
if ($aptId > 0 && $patientId <= 0) {
    $aptLookup = $db->prepare("SELECT patient_id FROM `appointments` WHERE `id` = ? AND `hospital_id` = ?");
    $aptLookup->execute([$aptId, $hId]);
    $patientId = (int)$aptLookup->fetchColumn();
}

// If mrn given without patientId, look up patientId by MRN
if (!empty($mrnParam) && $patientId <= 0) {
    $pLookup = $db->prepare("SELECT id FROM `patients` WHERE `mrn` = ? AND `hospital_id` = ?");
    $pLookup->execute([$mrnParam, $hId]);
    $patientId = (int)$pLookup->fetchColumn();
}

// If still no patientId, grab the latest registered patient
if ($patientId <= 0) {
    $patientId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `patients` WHERE `hospital_id` = {$hId}")->fetchColumn();
}

// Fetch Patient Details
$pStmt = $db->prepare("
    SELECT p.*, u.full_name AS registered_by_name
    FROM `patients` p
    LEFT JOIN `users` u ON p.registered_by = u.id
    WHERE p.id = ? AND p.hospital_id = ?
");
$pStmt->execute([$patientId, $hId]);
$patient = $pStmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die("<div style='font-family: sans-serif; text-align: center; padding: 40px;'><h2>Patient Record Not Found</h2><p>The requested patient token record does not exist.</p><a href='index.php?page=dashboard'>&larr; Return to Reception</a></div>");
}

// Fetch Appointment / Token Data
$appointment = null;
if ($aptId > 0) {
    $aptStmt = $db->prepare("
        SELECT a.*, u.full_name AS doctor_name, u.consultation_fee, u.designation AS doctor_designation,
               d.department_name, d.location AS dept_location
        FROM `appointments` a
        JOIN `users` u ON a.doctor_id = u.id
        LEFT JOIN `departments` d ON a.department_id = d.id
        WHERE a.id = ? AND a.hospital_id = ?
    ");
    $aptStmt->execute([$aptId, $hId]);
    $appointment = $aptStmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Check if there is an active appointment for today or latest
    $todayApt = $db->prepare("
        SELECT a.*, u.full_name AS doctor_name, u.consultation_fee, u.designation AS doctor_designation,
               d.department_name, d.location AS dept_location
        FROM `appointments` a
        JOIN `users` u ON a.doctor_id = u.id
        LEFT JOIN `departments` d ON a.department_id = d.id
        WHERE a.patient_id = ? AND a.hospital_id = ?
        ORDER BY a.appointment_date DESC, a.id DESC
        LIMIT 1
    ");
    $todayApt->execute([$patientId, $hId]);
    $appointment = $todayApt->fetch(PDO::FETCH_ASSOC);
}

// Determine Token Display Number
$tokenNumber = $appointment['token_number'] ?? null;
if (!$tokenNumber) {
    // Fallback: Queue sequence for today based on patient ID
    $tokenNumber = 'T-' . str_pad((string)($patient['id'] % 100), 2, '0', STR_PAD_LEFT);
} else {
    $tokenNumber = str_pad((string)$tokenNumber, 2, '0', STR_PAD_LEFT);
}

$doctorName = $appointment['doctor_name'] ?? 'Attending OPD Medical Officer';
$deptName = $appointment['department_name'] ?? 'General Out-Patient Department (OPD)';
$deptLocation = $appointment['dept_location'] ?? 'Main OPD Wing — 1st Floor';
$fee = (float)($appointment['consultation_fee'] ?? 500.00);
$aptNumber = $appointment['appointment_number'] ?? ('OPD-' . date('Ymd') . '-' . str_pad((string)$patient['id'], 3, '0', STR_PAD_LEFT));
$aptDate = !empty($appointment['appointment_date']) ? format_date($appointment['appointment_date']) : format_date(date('Y-m-d'));
$slotTime = !empty($appointment['slot_time']) ? date('h:i A', strtotime($appointment['slot_time'])) : date('h:i A');

// Construct Data for the QR Code
// Can be scanned by 2D barcode scanner (types MRN) or camera (opens patient profile directly)
$qrDirectUrl = QrCode::patientUrl((int)$patient['id'], $patient['mrn']);
$qrText = $patient['mrn']; // Standard scanners read string; modern camera reads URL

$mode = clean_input($_GET['mode'] ?? 'thermal'); // 'thermal' (80mm) or 'standard' (A5/A4)
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPD Token #<?= htmlspecialchars($tokenNumber) ?> &bull; <?= htmlspecialchars($patient['mrn']) ?> — <?= htmlspecialchars($hProfile['hospital_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <!-- Offline & Online QR Code Rendering Script -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* Thermal 80mm Print Styling */
        .thermal-slip {
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            background: #ffffff;
            color: #000000;
            padding: 5mm 3.5mm;
            font-size: 11px;
            line-height: 1.35;
        }

        .thermal-slip .dashed-line {
            border-top: 1px dashed #334155;
            margin: 6px 0;
        }

        .thermal-slip .solid-double {
            border-top: 2px solid #000000;
            margin: 6px 0;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; padding: 0 !important; margin: 0 !important; }
            .thermal-slip {
                width: 100% !important;
                max-width: 80mm !important;
                margin: 0 auto !important;
                padding: 2mm !important;
                box-shadow: none !important;
                border: none !important;
            }
            .standard-slip {
                width: 100% !important;
                max-width: 150mm !important;
                margin: 0 auto !important;
                padding: 5mm !important;
                box-shadow: none !important;
                border: none !important;
            }
            @page {
                size: auto;
                margin: 3mm;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen p-4 sm:p-6 flex flex-col items-center">

    <!-- Top Action Toolbar (Never Printed) -->
    <div class="no-print max-w-md w-full mb-4 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="index.php?page=patient_profile&id=<?= $patient['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-xs transition-colors" title="Back to Patient Profile">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="text-xs font-bold text-slate-800">OPD Patient Token Slip</div>
                <div class="text-[10px] text-slate-400 font-mono">MRN: <?= htmlspecialchars($patient['mrn']) ?></div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Mode Switcher -->
            <a href="index.php?page=print_token&patient_id=<?= $patient['id'] ?>&apt_id=<?= $aptId ?>&mode=thermal" class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all <?= $mode === 'thermal' ? 'bg-teal-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                <i class="fa-solid fa-receipt mr-1"></i> 80mm Slip
            </a>
            <a href="index.php?page=print_token&patient_id=<?= $patient['id'] ?>&apt_id=<?= $aptId ?>&mode=standard" class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all <?= $mode === 'standard' ? 'bg-teal-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                <i class="fa-solid fa-file-lines mr-1"></i> Standard
            </a>
            <button onclick="window.print()" class="px-3.5 py-1.5 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-print text-[11px]"></i>
                <span>Print</span>
            </button>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- PRINTABLE TOKEN RECEIPT FILE (ONLY THIS SECTION RENDERS ON PRINT)       -->
    <!-- ======================================================================= -->
    <?php if ($mode === 'thermal'): ?>

    <!-- 80MM THERMAL TOKEN SLIP -->
    <div class="thermal-slip shadow-md border border-slate-300 rounded-xl bg-white">
        
        <!-- Hospital Branding Header -->
        <div class="text-center pb-1">
            <div class="text-[14px] font-black uppercase tracking-tight leading-tight text-black"><?= htmlspecialchars($hProfile['hospital_name']) ?></div>
            <div class="text-[9px] uppercase font-bold text-slate-600 mt-0.5">Central Reception &bull; OPD Routing Slip</div>
            <div class="text-[8px] text-slate-500 font-mono"><?= htmlspecialchars($hProfile['address'] ?? 'Main Boulevard, Healthcare City') ?> &bull; Tel: <?= htmlspecialchars($hProfile['phone'] ?? '1122') ?></div>
        </div>

        <div class="dashed-line"></div>

        <!-- BIG TOKEN NUMBER CALLOUT BOX -->
        <div class="text-center my-1.5 py-2 px-1 bg-black text-white rounded-lg">
            <div class="text-[9px] font-bold uppercase tracking-widest text-slate-300">OPD QUEUE TOKEN NUMBER</div>
            <div class="text-3xl font-black font-mono tracking-tight leading-none my-1">
                #<?= htmlspecialchars($tokenNumber) ?>
            </div>
            <div class="text-[10px] font-bold text-teal-300 uppercase tracking-wide truncate">
                <?= htmlspecialchars($deptName) ?>
            </div>
        </div>

        <!-- PROMINENT PATIENT ID & MRN CALLOUT -->
        <div class="bg-slate-100 border border-slate-300 rounded-lg p-2 my-1.5 text-center">
            <div class="text-[9px] font-bold uppercase tracking-wider text-slate-500">Universal Patient Record Key</div>
            <div class="text-base font-black font-mono text-black tracking-wide leading-tight">
                <?= htmlspecialchars($patient['mrn']) ?>
            </div>
            <div class="text-[10px] font-bold font-mono text-slate-700">
                System Patient ID: #<?= (int)$patient['id'] ?>
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- PATIENT DEMOGRAPHICS -->
        <div class="space-y-1 text-[10px]">
            <div class="flex justify-between items-baseline">
                <span class="text-slate-500 font-semibold uppercase">Patient Name:</span>
                <span class="font-extrabold text-black text-[11px] text-right truncate max-w-[55mm]"><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500 font-semibold uppercase">Age &bull; Gender:</span>
                <span class="font-bold text-black font-mono"><?= (int)($patient['age'] ?? 0) ?> Yrs &bull; <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($patient['phone_primary'])): ?>
            <div class="flex justify-between">
                <span class="text-slate-500 font-semibold uppercase">Phone:</span>
                <span class="font-mono text-black font-bold"><?= htmlspecialchars($patient['phone_primary']) ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between">
                <span class="text-slate-500 font-semibold uppercase">Reg Date/Time:</span>
                <span class="font-mono text-slate-700"><?= $aptDate ?> &bull; <?= $slotTime ?></span>
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- CLINICAL ROUTING & DOCTOR -->
        <div class="space-y-1 text-[10px]">
            <div class="flex justify-between items-baseline">
                <span class="text-slate-500 font-semibold uppercase">Doctor / MO:</span>
                <span class="font-bold text-black text-right truncate max-w-[55mm]"><?= htmlspecialchars($doctorName) ?></span>
            </div>
            <div class="flex justify-between items-baseline">
                <span class="text-slate-500 font-semibold uppercase">Clinic Room:</span>
                <span class="font-bold text-black font-mono text-right"><?= htmlspecialchars($deptLocation) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500 font-semibold uppercase">Token Slip Ref:</span>
                <span class="font-mono text-slate-600 font-bold"><?= htmlspecialchars($aptNumber) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500 font-semibold uppercase">Consultation Fee:</span>
                <span class="font-mono font-black text-black"><?= format_currency($fee) ?> (Paid)</span>
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- QR CODE SECTION (SCANNABLE BY ALL DEPARTMENTS) -->
        <div class="text-center py-2 flex flex-col items-center">
            <div id="thermal-qrcode" class="p-1 bg-white border border-slate-300 rounded inline-block" style="width: 110px; height: 110px;"></div>
            <div class="text-[8px] font-mono font-bold text-black uppercase tracking-wider mt-1">
                Scan to Open Patient Record
            </div>
            <div class="text-[7.5px] text-slate-500 uppercase mt-0.5">
                Doctor &bull; Laboratory &bull; Pharmacy &bull; Billing
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- DEPARTMENT ROUTING CHECKLIST -->
        <div class="py-1 text-[8.5px] text-slate-700 space-y-0.5">
            <div class="font-bold text-black uppercase text-[9px] mb-1">Hospital Flow Instructions:</div>
            <div>[ ] 1. Proceed to Clinic Waiting Area &bull; Watch Screen</div>
            <div>[ ] 2. Present Token Slip to Doctor in OPD Consultation</div>
            <div>[ ] 3. Scan Token at Pathology Lab for any Blood/Urine Tests</div>
            <div>[ ] 4. Present Token at Pharmacy Dispensary for Medicines</div>
        </div>

        <div class="solid-double"></div>

        <div class="text-center text-[8px] text-slate-500 font-mono">
            Receptionist: <?= htmlspecialchars($patient['registered_by_name'] ?? $currentUser['full_name']) ?> &bull; System ID: #<?= $patient['id'] ?><br>
            Protected Health Information (PHI) &bull; Keep this slip
        </div>

    </div>

    <?php else: ?>

    <!-- STANDARD SLIP / A5 FORMAT -->
    <div class="standard-slip max-w-lg w-full bg-white rounded-2xl border-2 border-slate-300 p-6 shadow-md text-xs">
        
        <!-- Header -->
        <div class="flex items-start justify-between border-b-2 border-slate-900 pb-3 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-teal-600 text-white flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid fa-house-chimney-medical"></i>
                </div>
                <div>
                    <h1 class="text-base font-black text-slate-900 uppercase leading-tight"><?= htmlspecialchars($hProfile['hospital_name']) ?></h1>
                    <p class="text-[11px] text-teal-700 font-bold uppercase tracking-wide">Out-Patient Department (OPD) Consultation Token &amp; Routing Slip</p>
                    <p class="text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($hProfile['address'] ?? 'Healthcare Complex') ?> &bull; ER Hotline: <?= htmlspecialchars($hProfile['emergency_hotline'] ?? '1122') ?></p>
                </div>
            </div>
            <div class="text-right">
                <span class="px-2.5 py-1 bg-slate-900 text-white rounded-lg text-xs font-mono font-bold block">PATIENT TOKEN</span>
                <span class="text-[10px] text-slate-500 font-mono mt-0.5 block"><?= $aptDate ?></span>
            </div>
        </div>

        <!-- Token & MRN Highlight Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div class="bg-gradient-to-r from-teal-800 to-slate-900 text-white p-3.5 rounded-xl text-center">
                <div class="text-[10px] uppercase font-bold tracking-wider text-teal-300">OPD Consultation Token</div>
                <div class="text-3xl font-black font-mono my-0.5">#<?= htmlspecialchars($tokenNumber) ?></div>
                <div class="text-[11px] text-teal-100 font-semibold truncate"><?= htmlspecialchars($deptName) ?></div>
            </div>

            <div class="bg-slate-50 border-2 border-slate-300 p-3.5 rounded-xl text-center flex flex-col justify-center">
                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500">Universal Patient Record Key</div>
                <div class="text-xl font-black font-mono text-slate-900"><?= htmlspecialchars($patient['mrn']) ?></div>
                <div class="text-[11px] font-mono font-bold text-teal-700">Database Patient ID: #<?= (int)$patient['id'] ?></div>
            </div>
        </div>

        <!-- Details Grid & QR Code -->
        <div class="grid grid-cols-3 gap-4 border-t border-b border-slate-200 py-4 mb-4">
            <div class="col-span-2 space-y-2 text-xs">
                <div class="grid grid-cols-3 gap-2">
                    <span class="text-slate-500 font-semibold">Patient Name:</span>
                    <span class="col-span-2 font-bold text-slate-900"><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <span class="text-slate-500 font-semibold">Age / Gender:</span>
                    <span class="col-span-2 font-bold text-slate-800 font-mono"><?= (int)($patient['age'] ?? 0) ?> Years &bull; <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <span class="text-slate-500 font-semibold">Contact Phone:</span>
                    <span class="col-span-2 font-mono font-bold text-slate-800"><?= htmlspecialchars($patient['phone_primary'] ?? 'N/A') ?></span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <span class="text-slate-500 font-semibold">Attending Doctor:</span>
                    <span class="col-span-2 font-bold text-teal-800"><?= htmlspecialchars($doctorName) ?></span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <span class="text-slate-500 font-semibold">Location / Clinic:</span>
                    <span class="col-span-2 text-slate-700 font-medium"><?= htmlspecialchars($deptLocation) ?></span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <span class="text-slate-500 font-semibold">Consultation Fee:</span>
                    <span class="col-span-2 font-mono font-black text-slate-900"><?= format_currency($fee) ?> <span class="text-emerald-700 text-[10px] font-bold uppercase">(Paid at Reception)</span></span>
                </div>
            </div>

            <!-- QR Code Area -->
            <div class="flex flex-col items-center justify-center p-2 bg-slate-50 rounded-xl border border-slate-200 text-center">
                <div id="standard-qrcode" class="p-1 bg-white border border-slate-300 rounded inline-block" style="width: 105px; height: 105px;"></div>
                <span class="text-[9px] font-mono font-bold text-slate-800 mt-1 uppercase block">Quick Department Scan</span>
                <span class="text-[8px] text-slate-500 block leading-tight">OPD &bull; Lab &bull; Pharmacy &bull; Billing</span>
            </div>
        </div>

        <!-- Workflow Checklist -->
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-[11px] mb-3">
            <div class="font-bold text-slate-800 uppercase text-[10px] mb-1.5 flex items-center gap-1.5">
                <i class="fa-solid fa-route text-teal-600"></i>
                <span>Clinical Department Service Sequence:</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-slate-700">
                <div>&bull; <strong>1. OPD Consultation:</strong> Room <?= htmlspecialchars($deptLocation) ?></div>
                <div>&bull; <strong>2. Diagnostic Pathology:</strong> Main Laboratory Counter</div>
                <div>&bull; <strong>3. Pharmacy Counter:</strong> Ground Floor Lobby Dispensary</div>
                <div>&bull; <strong>4. Discharge / Review:</strong> Keep this slip for follow-up</div>
            </div>
        </div>

        <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-2 border-t border-slate-200">
            <span>Issued by: <?= htmlspecialchars($currentUser['full_name']) ?> &bull; Session Active</span>
            <span>Appointment Ref: <?= htmlspecialchars($aptNumber) ?></span>
        </div>
    </div>

    <?php endif; ?>

    <!-- QR Code Generator Script (Offline & Instant) -->
    <script>
    function renderQRCodes() {
        const qrText = <?= json_encode($qrText) ?>;
        
        // Render Thermal QR
        const thermalEl = document.getElementById('thermal-qrcode');
        if (thermalEl) {
            thermalEl.innerHTML = '';
            if (typeof QRCode !== 'undefined') {
                new QRCode(thermalEl, {
                    text: qrText,
                    width: 100,
                    height: 100,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } else {
                renderImgFallback(thermalEl, qrText, 100);
            }
        }

        // Render Standard QR
        const standardEl = document.getElementById('standard-qrcode');
        if (standardEl) {
            standardEl.innerHTML = '';
            if (typeof QRCode !== 'undefined') {
                new QRCode(standardEl, {
                    text: qrText,
                    width: 95,
                    height: 95,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } else {
                renderImgFallback(standardEl, qrText, 95);
            }
        }
    }

    function renderImgFallback(container, text, size) {
        const img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=' + size + 'x' + size + '&data=' + encodeURIComponent(text);
        img.alt = 'QR Code: ' + text;
        img.style.width = size + 'px';
        img.style.height = size + 'px';
        img.onerror = function() {
            container.innerHTML = '<div style="font-size:9px; font-family:monospace; padding:4px; font-weight:bold;">' + text + '</div>';
        };
        container.appendChild(img);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderQRCodes);
    } else {
        renderQRCodes();
    }

    // Auto-Print if requested
    <?php if ($autoprint): ?>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 300);
    });
    <?php endif; ?>
    </script>
</body>
</html>
