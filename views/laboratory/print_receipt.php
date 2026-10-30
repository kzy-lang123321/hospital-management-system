<?php
/**
 * Hospital Management System (HMS)
 * Official Laboratory Investigation Receipt & Specimen Collection Voucher
 * 
 * Specifically designed for Laboratory & Diagnostic Pathology Department:
 * - Lab Order # and Sample Barcode
 * - Ordered Tests, Specimen Types (EDTA, Serum, Urine), Turnaround Time (TAT)
 * - Patient Demographics & Bold Universal MRN
 * - Lab QR Code for sample accessioning
 * - Pathologist / Phlebotomist Sign & Stamp
 * - Supports Thermal 80mm Voucher & Standard A5/A4 Slip
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/QrCode.php';

Auth::requireLogin();

$db = Database::getConnection();
$hProfile = hospital_profile();
$hId = Tenant::id();

$orderId = (int)($_GET['order_id'] ?? $_GET['id'] ?? 0);
$orderItemId = (int)($_GET['item_id'] ?? 0);

// If item_id provided, find the order_id
if ($orderItemId > 0 && $orderId <= 0) {
    $iLookup = $db->prepare("SELECT lab_order_id FROM `lab_order_items` WHERE id = ?");
    $iLookup->execute([$orderItemId]);
    $orderId = (int)$iLookup->fetchColumn();
}

// If no order_id passed, get the latest lab order
if ($orderId <= 0) {
    $orderId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `lab_orders` WHERE `hospital_id` = {$hId}")->fetchColumn();
}

// Fetch Lab Order Master
$orderStmt = $db->prepare("
    SELECT lo.*, 
           p.mrn, p.first_name, p.last_name, p.gender, p.age, p.phone_primary, p.blood_group,
           u.full_name AS doctor_name, d.department_name,
           e.encounter_number, e.encounter_type,
           b.bill_number, b.payment_status AS bill_payment_status
    FROM `lab_orders` lo
    JOIN `patients` p ON lo.patient_id = p.id
    JOIN `users` u ON lo.doctor_id = u.id
    LEFT JOIN `departments` d ON u.department_id = d.id
    LEFT JOIN `encounters` e ON lo.encounter_id = e.id
    LEFT JOIN `bills` b ON b.encounter_id = lo.encounter_id AND b.payment_status != 'Refunded'
    WHERE lo.id = ? AND lo.hospital_id = ?
");
$orderStmt->execute([$orderId, $hId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("<div style='font-family: sans-serif; text-align: center; padding: 40px;'><h2>Lab Order Not Found</h2><p>The requested laboratory order receipt does not exist.</p><a href='index.php?page=laboratory'>&larr; Return to Laboratory</a></div>");
}

// Fetch Ordered Test Items
$itemsStmt = $db->prepare("
    SELECT loi.*, lt.test_name, lt.test_code, lt.specimen_type, lt.turnaround_hours, lt.standard_price,
           lc.category_name
    FROM `lab_order_items` loi
    JOIN `lab_tests` lt ON loi.lab_test_id = lt.id
    LEFT JOIN `lab_categories` lc ON lt.category_id = lc.id
    WHERE loi.lab_order_id = ?
    ORDER BY loi.id ASC
");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalLabAmount = 0.00;
foreach ($orderItems as $it) {
    $totalLabAmount += (float)$it['standard_price'];
}

$sampleBarcode = !empty($orderItems[0]['sample_barcode']) ? $orderItems[0]['sample_barcode'] : ('SMP-' . date('Ymd') . '-' . str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT));
$qrText = $order['order_number'] . '|' . $order['mrn'];

$mode = clean_input($_GET['mode'] ?? 'standard'); // 'standard' (A5/A4) or 'thermal' (80mm)
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Investigation Receipt #<?= htmlspecialchars($order['order_number']) ?> &bull; <?= htmlspecialchars($order['mrn']) ?> — <?= htmlspecialchars($hProfile['hospital_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

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
                max-width: 160mm !important;
                margin: 0 auto !important;
                padding: 6mm !important;
                box-shadow: none !important;
                border: none !important;
            }
            @page {
                size: auto;
                margin: 4mm;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen p-4 sm:p-6 flex flex-col items-center">

    <!-- Top Action Toolbar (Never Printed) -->
    <div class="no-print max-w-xl w-full mb-4 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="index.php?page=laboratory" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-xs transition-colors" title="Back to Laboratory Worklist">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="text-xs font-bold text-slate-800">Laboratory Investigation Receipt</div>
                <div class="text-[10px] text-slate-400 font-mono">Order: <?= htmlspecialchars($order['order_number']) ?></div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="index.php?page=print_lab_receipt&order_id=<?= $orderId ?>&mode=thermal" class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all <?= $mode === 'thermal' ? 'bg-purple-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                <i class="fa-solid fa-receipt mr-1"></i> 80mm Slip
            </a>
            <a href="index.php?page=print_lab_receipt&order_id=<?= $orderId ?>&mode=standard" class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all <?= $mode === 'standard' ? 'bg-purple-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                <i class="fa-solid fa-file-invoice mr-1"></i> Standard Voucher
            </a>
            <button onclick="window.print()" class="px-3.5 py-1.5 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-print text-[11px]"></i>
                <span>Print</span>
            </button>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- PRINTABLE LAB INVESTIGATION RECEIPT CANVAS                              -->
    <!-- ======================================================================= -->
    <?php if ($mode === 'thermal'): ?>

    <!-- 80MM THERMAL LAB SLIP -->
    <div class="thermal-slip shadow-md border border-slate-300 rounded-xl bg-white">
        
        <!-- Header -->
        <div class="text-center pb-1">
            <div class="text-[13px] font-black uppercase text-black leading-tight"><?= htmlspecialchars($hProfile['hospital_name']) ?></div>
            <div class="text-[9px] uppercase font-bold text-purple-900 mt-0.5">Clinical Pathology &amp; Diagnostic Lab</div>
            <div class="text-[8px] text-slate-500 font-mono">Specimen Accessioning &amp; Investigation Slip</div>
        </div>

        <div class="dashed-line"></div>

        <!-- LAB ORDER & BARCODE -->
        <div class="text-center my-1 py-1.5 bg-purple-950 text-white rounded-lg">
            <div class="text-[8.5px] font-bold uppercase tracking-wider text-purple-300">LAB INVESTIGATION ORDER</div>
            <div class="text-xl font-black font-mono my-0.5"><?= htmlspecialchars($order['order_number']) ?></div>
            <div class="text-[9px] font-mono text-slate-300">Sample Barcode: <?= htmlspecialchars($sampleBarcode) ?></div>
        </div>

        <!-- PATIENT IDENTIFICATION -->
        <div class="bg-slate-100 border border-slate-300 rounded p-1.5 my-1 text-center">
            <div class="text-[8.5px] uppercase font-bold text-slate-500">Universal Patient Key</div>
            <div class="text-base font-black font-mono text-black"><?= htmlspecialchars($order['mrn']) ?></div>
            <div class="text-[10px] font-extrabold text-black truncate"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></div>
            <div class="text-[9px] text-slate-600 font-mono"><?= (int)$order['age'] ?>y &bull; <?= htmlspecialchars($order['gender']) ?> &bull; Blood: <?= htmlspecialchars($order['blood_group'] ?? 'N/A') ?></div>
        </div>

        <div class="dashed-line"></div>

        <!-- CLINICAL DETAILS -->
        <div class="space-y-0.5 text-[9.5px]">
            <div class="flex justify-between">
                <span class="text-slate-500 uppercase">Consultant:</span>
                <span class="font-bold text-black text-right"><?= htmlspecialchars($order['doctor_name']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500 uppercase">Department:</span>
                <span class="font-semibold text-slate-700"><?= htmlspecialchars($order['department_name'] ?? 'OPD') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500 uppercase">Encounter:</span>
                <span class="font-mono text-slate-700"><?= htmlspecialchars($order['encounter_number'] ?? 'OPD') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500 uppercase">Order Date:</span>
                <span class="font-mono text-slate-700"><?= format_datetime($order['order_date'] ?? $order['created_at']) ?></span>
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- ORDERED LAB TESTS TABLE -->
        <div class="text-[9px] uppercase font-bold text-slate-600 mb-1">Prescribed Laboratory Tests:</div>
        <table class="w-full text-left text-[10px]">
            <thead class="border-b border-black text-[8px] uppercase">
                <tr>
                    <th class="py-0.5">Test Description</th>
                    <th class="py-0.5 text-center">Specimen</th>
                    <th class="py-0.5 text-right">Fee</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($orderItems as $it): ?>
                <tr>
                    <td class="py-1">
                        <span class="font-bold text-black block"><?= htmlspecialchars($it['test_name']) ?></span>
                        <span class="text-[8px] font-mono text-purple-800">[<?= htmlspecialchars($it['test_code']) ?>]</span>
                    </td>
                    <td class="py-1 text-center font-mono text-[8.5px] text-slate-700">
                        <?= htmlspecialchars($it['specimen_type']) ?>
                    </td>
                    <td class="py-1 text-right font-mono font-bold text-black">
                        <?= format_currency($it['standard_price']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="border-t border-black font-mono font-bold">
                <tr>
                    <td colspan="2" class="py-1 text-right uppercase text-[8.5px]">Total Investigation Fee:</td>
                    <td class="py-1 text-right text-[10px] text-black"><?= format_currency($totalLabAmount) ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="dashed-line"></div>

        <!-- LAB QR CODE -->
        <div class="text-center py-1 flex flex-col items-center">
            <div id="lab-thermal-qrcode" class="p-1 bg-white border border-slate-300 rounded inline-block" style="width: 95px; height: 95px;"></div>
            <div class="text-[8px] font-mono font-bold text-black uppercase mt-1">Scan for Lab Specimen Accession</div>
        </div>

        <div class="dashed-line"></div>

        <!-- INSTRUCTIONS -->
        <div class="text-[8px] text-slate-600 space-y-0.5">
            <div>&bull; Present this slip at Phlebotomy Counter for sample collection.</div>
            <div>&bull; Routine Reports available within 4 to 6 hours.</div>
            <div>&bull; Fasting blood tests require 10-12 hours overnight fasting.</div>
        </div>

        <div class="solid-double"></div>

        <div class="flex justify-between items-end pt-1 text-[8px] text-slate-500 font-mono">
            <span>Status: <?= htmlspecialchars($order['order_status']) ?></span>
            <span class="text-right border-t border-slate-400 pt-0.5 text-slate-800 font-bold">Phlebotomist Stamp</span>
        </div>
    </div>

    <?php else: ?>

    <!-- STANDARD A5/A4 LAB VOUCHER -->
    <div class="standard-slip max-w-xl w-full bg-white rounded-2xl border-2 border-slate-300 p-6 shadow-md text-xs">
        
        <!-- Header -->
        <div class="flex items-start justify-between border-b-2 border-purple-900 pb-3 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-purple-700 text-white flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid fa-flask-vial"></i>
                </div>
                <div>
                    <h1 class="text-base font-black text-slate-900 uppercase leading-tight"><?= htmlspecialchars($hProfile['hospital_name']) ?></h1>
                    <p class="text-[11px] text-purple-800 font-bold uppercase tracking-wide">Department of Pathology &amp; Diagnostic Laboratory Services</p>
                    <p class="text-[10px] text-slate-500 font-mono">Specimen Collection &bull; Clinical Biochemistry &bull; Hematology &bull; Microbiology</p>
                </div>
            </div>
            <div class="text-right">
                <span class="px-2.5 py-1 bg-purple-900 text-white rounded-lg text-xs font-mono font-bold block">LAB VOUCHER</span>
                <span class="text-[10px] text-slate-500 font-mono mt-0.5 block"><?= format_date(date('Y-m-d')) ?></span>
            </div>
        </div>

        <!-- Order & Patient Grid -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-purple-50/70 border border-purple-200 p-3 rounded-xl">
                <div class="text-[10px] font-bold text-purple-900 uppercase tracking-wider">Lab Order Information</div>
                <div class="text-base font-black font-mono text-purple-950 mt-0.5"><?= htmlspecialchars($order['order_number']) ?></div>
                <div class="text-[11px] text-slate-600 mt-1">Barcode: <strong class="font-mono text-slate-900"><?= htmlspecialchars($sampleBarcode) ?></strong></div>
                <div class="text-[11px] text-slate-600">Doctor: <strong><?= htmlspecialchars($order['doctor_name']) ?></strong> (<?= htmlspecialchars($order['department_name'] ?? 'OPD') ?>)</div>
            </div>

            <div class="bg-slate-50 border border-slate-200 p-3 rounded-xl">
                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Patient Identification</div>
                <div class="text-sm font-black text-slate-900 mt-0.5"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></div>
                <div class="text-[11px] text-teal-800 font-mono font-bold mt-1">MRN: <?= htmlspecialchars($order['mrn']) ?> &bull; ID: #<?= (int)$order['patient_id'] ?></div>
                <div class="text-[11px] text-slate-600 font-mono"><?= (int)$order['age'] ?> Yrs &bull; <?= htmlspecialchars($order['gender']) ?> &bull; Phone: <?= htmlspecialchars($order['phone_primary'] ?? 'N/A') ?></div>
            </div>
        </div>

        <!-- Ordered Tests Table -->
        <div class="border border-slate-200 rounded-xl overflow-hidden mb-4">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100 text-slate-600 uppercase text-[10px] font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-3 py-2">Test Name &amp; Code</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2 text-center">Specimen Container</th>
                        <th class="px-3 py-2 text-center">TAT</th>
                        <th class="px-3 py-2 text-right">Fee</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($orderItems as $it): ?>
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-3 py-2.5">
                            <span class="font-bold text-slate-900 block"><?= htmlspecialchars($it['test_name']) ?></span>
                            <span class="text-[10px] font-mono text-purple-700 font-semibold">[<?= htmlspecialchars($it['test_code']) ?>]</span>
                        </td>
                        <td class="px-3 py-2.5 text-slate-600 text-[11px]">
                            <?= htmlspecialchars($it['category_name'] ?? 'General Pathology') ?>
                        </td>
                        <td class="px-3 py-2.5 text-center text-[11px] font-mono font-semibold text-slate-800">
                            <?= htmlspecialchars($it['specimen_type']) ?>
                        </td>
                        <td class="px-3 py-2.5 text-center font-mono text-[11px] text-slate-600">
                            <?= (int)$it['turnaround_hours'] ?> hrs
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono font-bold text-slate-900">
                            <?= format_currency($it['standard_price']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 border-t-2 border-slate-800 font-mono">
                    <tr>
                        <td colspan="4" class="px-3 py-2 text-right font-bold text-slate-800 uppercase text-[11px]">Net Laboratory Charges:</td>
                        <td class="px-3 py-2 text-right font-black text-slate-900 text-sm"><?= format_currency($totalLabAmount) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- QR Code & Instructions Footer -->
        <div class="grid grid-cols-4 gap-3 items-center border-t border-slate-200 pt-3">
            <div class="flex flex-col items-center text-center">
                <div id="lab-standard-qrcode" class="p-1 bg-white border border-slate-300 rounded" style="width: 90px; height: 90px;"></div>
                <span class="text-[8px] font-mono text-slate-600 mt-1 uppercase font-bold">Accession QR</span>
            </div>

            <div class="col-span-3 text-[10px] text-slate-600 space-y-1">
                <div class="font-bold text-slate-800 uppercase text-[10px]">Patient Collection Notice:</div>
                <p>1. Present this voucher to the Phlebotomist at the Diagnostic Specimen Center.</p>
                <p>2. Ensure patient identity (MRN &amp; Name) matches specimen tube label before drawing sample.</p>
                <p>3. Online &amp; counter lab reports will be released after pathologist sign-off.</p>
                <div class="pt-2 flex justify-between items-center text-slate-500 font-mono text-[9px]">
                    <span>Order Date: <?= format_datetime($order['order_date'] ?? $order['created_at']) ?></span>
                    <span class="border-t border-slate-400 pt-0.5 font-bold text-slate-800">Authorized Pathologist Sign / Stamp</span>
                </div>
            </div>
        </div>

    </div>

    <?php endif; ?>

    <!-- QR Code Script -->
    <script>
    function renderLabQRCodes() {
        const qrText = <?= json_encode($qrText) ?>;

        const thermalEl = document.getElementById('lab-thermal-qrcode');
        if (thermalEl) {
            thermalEl.innerHTML = '';
            if (typeof QRCode !== 'undefined') {
                new QRCode(thermalEl, {
                    text: qrText,
                    width: 85,
                    height: 85,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } else {
                renderImgFallback(thermalEl, qrText, 85);
            }
        }

        const standardEl = document.getElementById('lab-standard-qrcode');
        if (standardEl) {
            standardEl.innerHTML = '';
            if (typeof QRCode !== 'undefined') {
                new QRCode(standardEl, {
                    text: qrText,
                    width: 80,
                    height: 80,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } else {
                renderImgFallback(standardEl, qrText, 80);
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
        document.addEventListener('DOMContentLoaded', renderLabQRCodes);
    } else {
        renderLabQRCodes();
    }

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
