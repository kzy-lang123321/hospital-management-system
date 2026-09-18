<?php
/**
 * Hospital Management System (HMS)
 * Official Point of Sale (POS) & Pharmacy Thermal / Standard Receipt
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

Auth::requireLogin();

$db = Database::getConnection();
$hProfile = hospital_profile();

$saleId = (int)($_GET['sale_id'] ?? $_GET['id'] ?? 0);

if ($saleId <= 0) {
    // If no specific sale ID passed, grab the latest sale
    $saleId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM `pharmacy_sales`")->fetchColumn();
}

$saleStmt = $db->prepare("
    SELECT 
        ps.*,
        u.full_name AS cashier_name,
        u.designation AS cashier_designation,
        p.mrn,
        p.first_name,
        p.last_name,
        p.gender,
        p.age,
        p.phone_primary AS patient_phone,
        e.encounter_number,
        e.encounter_type,
        pr.prescription_number,
        doc.full_name AS doctor_name
    FROM `pharmacy_sales` ps
    JOIN `users` u ON ps.dispensed_by = u.id
    LEFT JOIN `patients` p ON ps.patient_id = p.id
    LEFT JOIN `encounters` e ON ps.encounter_id = e.id
    LEFT JOIN `prescriptions` pr ON ps.prescription_id = pr.id
    LEFT JOIN `users` doc ON pr.doctor_id = doc.id
    WHERE ps.id = ?
    LIMIT 1
");
$saleStmt->execute([$saleId]);
$sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die("<div style='font-family: sans-serif; text-align: center; padding: 40px;'><h2>POS Sale Not Found</h2><p>The requested transaction receipt does not exist.</p><a href='index.php?page=dashboard'>&larr; Return to Dashboard</a></div>");
}

// Fetch Items
$itemsStmt = $db->prepare("
    SELECT 
        psi.*,
        db.batch_number,
        db.expiry_date,
        di.brand_name,
        di.generic_name,
        di.dosage_form,
        di.strength,
        di.is_controlled_substance,
        di.narcotic_schedule
    FROM `pharmacy_sale_items` psi
    JOIN `drug_batches` db ON psi.drug_batch_id = db.id
    JOIN `drug_items` di ON db.drug_item_id = di.id
    WHERE psi.pharmacy_sale_id = ?
    ORDER BY psi.id ASC
");
$itemsStmt->execute([$saleId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';
$mode = $_GET['mode'] ?? 'thermal'; // 'thermal' (80mm) or 'standard' (A4)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Receipt #<?= htmlspecialchars($sale['sale_invoice_number']) ?> — <?= htmlspecialchars($hProfile['hospital_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        
        /* Thermal 80mm Receipt Styles */
        .thermal-slip {
            width: 80mm;
            max-width: 80mm;
            margin-left: auto;
            margin-right: auto;
            background: #ffffff;
            color: #000000;
            padding: 4mm 3mm;
            font-size: 11px;
            line-height: 1.35;
        }

        .thermal-slip .divider {
            border-top: 1px dashed #475569;
            margin: 6px 0;
        }

        .thermal-slip .double-divider {
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
                max-width: 100% !important;
                margin: 0 !important;
                padding: 10mm !important;
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

    <!-- Top Action Toolbar (Screen Only) -->
    <div class="no-print max-w-xl w-full mb-6 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="index.php?page=dashboard#dashboard-pos" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm transition-colors" title="Back to Dashboard POS">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h3 class="font-bold text-xs text-slate-800">POS Receipt #<?= htmlspecialchars($sale['sale_invoice_number']) ?></h3>
                <p class="text-[11px] text-slate-500">Live Hospital Point of Sale Terminal</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Format Switcher -->
            <div class="bg-slate-100 p-1 rounded-lg flex items-center text-xs font-semibold">
                <a href="index.php?page=print_pos_receipt&sale_id=<?= $saleId ?>&mode=thermal" class="px-2.5 py-1 rounded-md transition-colors <?= $mode === 'thermal' ? 'bg-white shadow-xs text-teal-700' : 'text-slate-500 hover:text-slate-800' ?>">
                    <i class="fa-solid fa-receipt mr-1"></i>80mm Slip
                </a>
                <a href="index.php?page=print_pos_receipt&sale_id=<?= $saleId ?>&mode=standard" class="px-2.5 py-1 rounded-md transition-colors <?= $mode === 'standard' ? 'bg-white shadow-xs text-teal-700' : 'text-slate-500 hover:text-slate-800' ?>">
                    <i class="fa-solid fa-file-lines mr-1"></i>A4 Invoice
                </a>
            </div>

            <!-- Print Button -->
            <button onclick="window.print()" class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i>
                <span>Print Receipt</span>
            </button>
        </div>
    </div>

    <?php if ($mode === 'thermal'): ?>
    <!-- ========================================================================= -->
    <!-- 80MM THERMAL RECEIPT SLIP -->
    <!-- ========================================================================= -->
    <div class="thermal-slip shadow-md rounded-lg border border-slate-200 print:rounded-none print:border-none">
        
        <!-- Header -->
        <div class="text-center">
            <div class="font-black text-sm tracking-wider uppercase leading-tight"><?= htmlspecialchars($hProfile['hospital_name'] ?? 'Hayat Medicare Hospital') ?></div>
            <div class="text-[10px] text-slate-600 font-medium"><?= htmlspecialchars($hProfile['tagline'] ?? 'Excellence in Patient Care & Clinical Service') ?></div>
            <div class="text-[9px] text-slate-500 mt-1"><?= htmlspecialchars($hProfile['address'] ?? '') ?> &bull; <?= htmlspecialchars($hProfile['city'] ?? '') ?></div>
            <div class="text-[9px] text-slate-500">Phone: <?= htmlspecialchars($hProfile['phone'] ?? '+92 91 5892001') ?> | Emerg: <?= htmlspecialchars($hProfile['emergency_hotline'] ?? '1122') ?></div>
            <div class="text-[9px] font-mono text-slate-600 mt-0.5">Drug Lic: DL-ISB-2024-8891 &bull; NTN: 8934120-7</div>
        </div>

        <div class="divider"></div>

        <div class="text-center font-bold uppercase tracking-widest text-[11px] bg-slate-100 py-0.5 rounded">
            *** PHARMACY POS RECEIPT ***
        </div>

        <!-- Meta Info -->
        <div class="mt-2 space-y-0.5 text-[10px]">
            <div class="flex justify-between">
                <span class="text-slate-600">Invoice #:</span>
                <span class="font-mono font-bold"><?= htmlspecialchars($sale['sale_invoice_number']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Date &amp; Time:</span>
                <span class="font-mono"><?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Terminal / Counter:</span>
                <span class="font-mono font-semibold">POS-MAIN-01</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Cashier:</span>
                <span class="font-semibold"><?= htmlspecialchars($sale['cashier_name']) ?></span>
            </div>
            <div class="divider"></div>

            <!-- Customer / Patient Info -->
            <div class="flex justify-between">
                <span class="text-slate-600">Customer:</span>
                <span class="font-bold text-slate-900"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></span>
            </div>
            <?php if (!empty($sale['customer_phone'])): ?>
            <div class="flex justify-between">
                <span class="text-slate-600">Mobile Phone:</span>
                <span class="font-mono"><?= htmlspecialchars($sale['customer_phone']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($sale['mrn'])): ?>
            <div class="flex justify-between">
                <span class="text-slate-600">Patient MRN:</span>
                <span class="font-mono font-bold text-teal-800"><?= htmlspecialchars($sale['mrn']) ?> (<?= htmlspecialchars($sale['gender']) ?>, <?= $sale['age'] ?>y)</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($sale['prescription_number'])): ?>
            <div class="flex justify-between">
                <span class="text-slate-600">Prescription Ref:</span>
                <span class="font-mono"><?= htmlspecialchars($sale['prescription_number']) ?> (Dr. <?= htmlspecialchars($sale['doctor_name'] ?? 'Attending') ?>)</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <!-- Items Table -->
        <table class="w-full text-left text-[10px]">
            <thead>
                <tr class="border-b border-slate-300 font-bold uppercase text-[9px]">
                    <th class="pb-1">Item Description</th>
                    <th class="pb-1 text-center">Qty</th>
                    <th class="pb-1 text-right">Rate</th>
                    <th class="pb-1 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="py-1.5 pr-1">
                        <div class="font-bold text-slate-900"><?= htmlspecialchars($item['brand_name']) ?></div>
                        <div class="text-[9px] text-slate-500"><?= htmlspecialchars($item['dosage_form']) ?> &bull; <?= htmlspecialchars($item['strength']) ?></div>
                        <div class="text-[8px] font-mono text-slate-400">B:<?= htmlspecialchars($item['batch_number']) ?> Exp:<?= date('m/y', strtotime($item['expiry_date'])) ?></div>
                        <?php if (!empty($item['dosage_instructions'])): ?>
                        <div class="text-[9px] font-bold text-teal-800 bg-teal-50 px-1 py-0.5 rounded mt-0.5 border border-teal-200/60">
                            <span class="text-[8px] uppercase tracking-wider text-teal-600 block">طریقہ استعمال / Instructions:</span>
                            <?= htmlspecialchars($item['dosage_instructions']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="py-1.5 text-center font-mono font-bold align-top"><?= (int)$item['quantity'] ?></td>
                    <td class="py-1.5 text-right font-mono align-top"><?= number_format($item['unit_price'], 2) ?></td>
                    <td class="py-1.5 text-right font-mono font-bold align-top"><?= number_format($item['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="double-divider"></div>

        <!-- Totals & Payment Summary -->
        <div class="space-y-1 text-[10px]">
            <div class="flex justify-between">
                <span class="text-slate-600">Gross Subtotal:</span>
                <span class="font-mono font-semibold"><?= format_currency($sale['subtotal']) ?></span>
            </div>
            <?php if ((float)$sale['discount_amount'] > 0): ?>
            <div class="flex justify-between text-emerald-700">
                <span>Discount Allowed:</span>
                <span class="font-mono font-semibold">- <?= format_currency($sale['discount_amount']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ((float)$sale['tax_amount'] > 0): ?>
            <div class="flex justify-between">
                <span class="text-slate-600">Tax / GST:</span>
                <span class="font-mono font-semibold">+ <?= format_currency($sale['tax_amount']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="flex justify-between text-xs font-black py-1 border-y border-black">
                <span>NET PAYABLE:</span>
                <span class="font-mono text-sm"><?= format_currency($sale['net_total']) ?></span>
            </div>

            <!-- Payment Breakdown -->
            <div class="flex justify-between pt-1">
                <span class="text-slate-600">Payment Mode:</span>
                <span class="font-bold uppercase font-mono"><?= htmlspecialchars($sale['payment_method']) ?></span>
            </div>
            <?php if (!empty($sale['payment_reference'])): ?>
            <div class="flex justify-between">
                <span class="text-slate-600">Payment Ref:</span>
                <span class="font-mono font-semibold"><?= htmlspecialchars($sale['payment_reference']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ((float)$sale['amount_tendered'] > 0): ?>
            <div class="flex justify-between">
                <span class="text-slate-600">Cash Tendered:</span>
                <span class="font-mono"><?= format_currency($sale['amount_tendered']) ?></span>
            </div>
            <div class="flex justify-between font-bold text-teal-800">
                <span>Change Returned:</span>
                <span class="font-mono"><?= format_currency($sale['change_amount']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <!-- Barcode & Disclaimer Footer -->
        <div class="text-center space-y-1 mt-2">
            <!-- Simulated 1D Barcode -->
            <div class="flex justify-center items-center py-1">
                <div class="font-mono text-base tracking-widest font-black uppercase text-slate-800" style="letter-spacing: 3px;">
                    ||| | |||| || ||| |||| | ||| ||||
                </div>
            </div>
            <div class="text-[9px] font-mono text-slate-500 font-bold"><?= htmlspecialchars($sale['sale_invoice_number']) ?></div>

            <p class="text-[8px] text-slate-500 leading-tight pt-1">
                * Note: Goods once sold cannot be returned after 3 days. Refrigerated &amp; opened items are strictly non-returnable.
            </p>
            <p class="text-[9px] font-bold text-slate-800 uppercase tracking-wider mt-1">
                Thank you &bull; Get Well Soon!
            </p>
        </div>

    </div>

    <?php else: ?>
    <!-- ========================================================================= -->
    <!-- STANDARD A4 HOSPITAL PHARMACY INVOICE -->
    <!-- ========================================================================= -->
    <div class="standard-slip max-w-4xl w-full bg-white p-8 rounded-2xl border border-slate-200 shadow-md">
        
        <!-- Hospital Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b-2 border-teal-600 gap-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-teal-600 text-white flex items-center justify-center text-3xl shadow-sm">
                    <i class="fa-solid fa-hospital"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight"><?= htmlspecialchars($hProfile['hospital_name'] ?? 'Hayat Medicare Hospital') ?></h1>
                    <p class="text-xs text-teal-700 font-semibold tracking-wider uppercase"><?= htmlspecialchars($hProfile['tagline'] ?? 'Excellence in Patient Care & Clinical Service') ?></p>
                    <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($hProfile['address'] ?? '') ?> &bull; Phone: <?= htmlspecialchars($hProfile['phone'] ?? '+92 91 5892001') ?></p>
                </div>
            </div>

            <div class="text-right">
                <span class="inline-block px-3 py-1 rounded bg-teal-100 text-teal-800 font-bold text-xs uppercase tracking-widest mb-1">
                    Tax Invoice / Cash Memo
                </span>
                <div class="font-mono font-bold text-lg text-slate-900"><?= htmlspecialchars($sale['sale_invoice_number']) ?></div>
                <div class="text-xs text-slate-500">Date: <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></div>
                <div class="text-[11px] font-mono text-slate-400">NTN: 8934120-7 | Drug Lic: DL-ISB-2024-8891</div>
            </div>
        </div>

        <!-- Bill To / Customer Details Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 py-4 border-b border-slate-200 text-xs">
            <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Customer / Patient</span>
                <strong class="text-slate-800 text-sm"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></strong>
                <?php if (!empty($sale['customer_phone'])): ?>
                <div class="text-slate-500 font-mono"><?= htmlspecialchars($sale['customer_phone']) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Patient MRN / Gender</span>
                <?php if (!empty($sale['mrn'])): ?>
                <strong class="text-teal-700 font-mono"><?= htmlspecialchars($sale['mrn']) ?></strong>
                <div class="text-slate-500"><?= htmlspecialchars($sale['gender']) ?>, <?= $sale['age'] ?> yrs</div>
                <?php else: ?>
                <span class="text-slate-500">Direct OTC Sale</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Terminal &amp; Cashier</span>
                <strong class="text-slate-800">POS-MAIN-01</strong>
                <div class="text-slate-500"><?= htmlspecialchars($sale['cashier_name']) ?></div>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px] uppercase font-semibold">Payment Status</span>
                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                    Paid via <?= htmlspecialchars($sale['payment_method']) ?>
                </span>
                <?php if (!empty($sale['payment_reference'])): ?>
                <div class="text-[10px] font-mono text-slate-500 mt-0.5">Ref: <?= htmlspecialchars($sale['payment_reference']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table of Items -->
        <div class="mt-4">
            <table class="w-full text-left text-xs border border-slate-200 rounded-lg overflow-hidden">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Medication Description</th>
                        <th class="px-4 py-3">Batch &amp; Expiry</th>
                        <th class="px-4 py-3 text-center">Qty</th>
                        <th class="px-4 py-3 text-right">Unit Price</th>
                        <th class="px-4 py-3 text-right">Total (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php 
                    $counter = 1;
                    foreach ($items as $item): 
                    ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-4 py-3 font-mono text-slate-400"><?= $counter++ ?></td>
                        <td class="px-4 py-3">
                            <strong class="text-slate-800 font-bold"><?= htmlspecialchars($item['brand_name']) ?></strong>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($item['generic_name']) ?> &bull; <?= htmlspecialchars($item['dosage_form']) ?> (<?= htmlspecialchars($item['strength']) ?>)</div>
                            <?php if (!empty($item['dosage_instructions'])): ?>
                            <div class="text-[11px] font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded mt-1 border border-teal-200">
                                <span class="text-[9px] uppercase tracking-wider text-teal-700 block">طریقہ استعمال / Patient Instructions:</span>
                                <?= htmlspecialchars($item['dosage_instructions']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-mono text-[11px]">
                            <span class="bg-slate-100 px-1.5 py-0.5 rounded font-bold text-slate-700"><?= htmlspecialchars($item['batch_number']) ?></span>
                            <span class="text-slate-500 ml-1">Exp: <?= date('d/m/Y', strtotime($item['expiry_date'])) ?></span>
                        </td>
                        <td class="px-4 py-3 text-center font-mono font-bold"><?= (int)$item['quantity'] ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="px-4 py-3 text-right font-mono font-bold"><?= number_format($item['total_price'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals & Signatures -->
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-end gap-6 pt-4 border-t border-slate-200">
            <div class="space-y-4 max-w-sm">
                <div class="text-[11px] text-slate-500 italic">
                    Certified that the medications listed above have been checked and dispensed as per official pharmacy standards.
                </div>
                <div class="pt-8">
                    <div class="w-48 border-b border-slate-400"></div>
                    <div class="text-[11px] font-bold text-slate-700 mt-1">Authorized Pharmacist / Stamp</div>
                </div>
            </div>

            <div class="w-72 space-y-2 text-xs bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div class="flex justify-between text-slate-600">
                    <span>Subtotal:</span>
                    <span class="font-mono font-bold"><?= format_currency($sale['subtotal']) ?></span>
                </div>
                <?php if ((float)$sale['discount_amount'] > 0): ?>
                <div class="flex justify-between text-emerald-700 font-medium">
                    <span>Discount:</span>
                    <span class="font-mono">- <?= format_currency($sale['discount_amount']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ((float)$sale['tax_amount'] > 0): ?>
                <div class="flex justify-between text-slate-600">
                    <span>Tax (GST):</span>
                    <span class="font-mono">+ <?= format_currency($sale['tax_amount']) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between text-sm font-black text-slate-900 border-t border-slate-300 pt-2">
                    <span>Grand Total:</span>
                    <span class="font-mono text-teal-800"><?= format_currency($sale['net_total']) ?></span>
                </div>
                <?php if ((float)$sale['amount_tendered'] > 0): ?>
                <div class="flex justify-between text-[11px] text-slate-500 border-t border-slate-200 pt-1">
                    <span>Cash Tendered:</span>
                    <span class="font-mono"><?= format_currency($sale['amount_tendered']) ?></span>
                </div>
                <div class="flex justify-between text-[11px] font-bold text-teal-800">
                    <span>Change Returned:</span>
                    <span class="font-mono"><?= format_currency($sale['change_amount']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <?php if ($autoprint): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
    <?php endif; ?>

</body>
</html>
