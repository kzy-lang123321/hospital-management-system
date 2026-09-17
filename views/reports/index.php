<?php
/**
 * Hospital Management System (HMS) - Reports & Analytics Matrix
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

// 1. Financial Collections by Method
$collectionsByMethod = $db->query("
    SELECT `payment_method`, COUNT(*) AS tx_count, SUM(`amount_paid`) AS total_amount
    FROM `payments`
    GROUP BY `payment_method`
")->fetchAll();

// 2. Doctor OPD Volume & Revenue
$doctorStats = $db->query("
    SELECT u.full_name AS doctor_name, d.department_name,
           COUNT(e.id) AS total_consultations,
           u.consultation_fee,
           (COUNT(e.id) * u.consultation_fee) AS estimated_revenue
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.id
    LEFT JOIN `departments` d ON u.department_id = d.id
    LEFT JOIN `encounters` e ON e.attending_doctor_id = u.id
    WHERE r.slug = 'doctor'
    GROUP BY u.id
")->fetchAll();

// 3. Wards Occupancy Summary
$wardCensus = $db->query("
    SELECT w.ward_name, w.floor_number,
           COUNT(b.id) AS total_beds,
           SUM(CASE WHEN b.status = 'Occupied' THEN 1 ELSE 0 END) AS occupied,
           SUM(CASE WHEN b.status = 'Available' THEN 1 ELSE 0 END) AS available,
           SUM(CASE WHEN b.status = 'Cleaning' THEN 1 ELSE 0 END) AS cleaning
    FROM `wards` w
    LEFT JOIN `rooms` r ON w.id = r.ward_id
    LEFT JOIN `beds` b ON r.id = b.room_id
    GROUP BY w.id
")->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Executive Reports & Operational Analytics</h2>
            <p class="text-xs text-slate-500">Hospital census, daily cashier closeouts, and clinical department volumes</p>
        </div>
        <button onclick="window.print()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs rounded-lg shadow-sm transition-colors flex items-center gap-2">
            <i class="fa-solid fa-print"></i>
            <span>Print Report Ledger</span>
        </button>
    </div>

    <!-- Section 1: Financial Collections by Payment Method -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
        <h3 class="font-bold text-slate-900 text-sm mb-3 flex items-center gap-2">
            <i class="fa-solid fa-cash-register text-emerald-600"></i>
            <span>Cashier Collections by Payment Tender</span>
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <?php 
            $grandTotal = 0;
            foreach ($collectionsByMethod as $c): 
                $grandTotal += $c['total_amount'];
            ?>
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl">
                <div class="text-[11px] font-semibold text-slate-500 uppercase"><?= htmlspecialchars($c['payment_method']) ?></div>
                <div class="text-lg font-bold text-slate-900 mt-1 font-mono"><?= format_currency($c['total_amount']) ?></div>
                <div class="text-[10px] text-slate-400 mt-0.5"><?= $c['tx_count'] ?> Transactions logged</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Section 2: Inpatient Bed Census by Ward -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-sm">Ward Bed Occupancy Rate (BOR)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5">Ward Name</th>
                        <th class="px-4 py-3.5">Floor</th>
                        <th class="px-4 py-3.5">Total Licensed Beds</th>
                        <th class="px-4 py-3.5">Occupied</th>
                        <th class="px-4 py-3.5">Available</th>
                        <th class="px-4 py-3.5">Housekeeping</th>
                        <th class="px-6 py-3.5 text-right">Occupancy %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($wardCensus as $w): 
                        $rate = $w['total_beds'] > 0 ? round(($w['occupied'] / $w['total_beds']) * 100) : 0;
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-3.5 font-bold text-slate-800"><?= htmlspecialchars($w['ward_name']) ?></td>
                        <td class="px-4 py-3.5 text-slate-500"><?= htmlspecialchars($w['floor_number']) ?></td>
                        <td class="px-4 py-3.5 font-mono font-semibold"><?= $w['total_beds'] ?></td>
                        <td class="px-4 py-3.5 font-mono font-bold text-rose-600"><?= $w['occupied'] ?></td>
                        <td class="px-4 py-3.5 font-mono font-bold text-emerald-600"><?= $w['available'] ?></td>
                        <td class="px-4 py-3.5 font-mono text-orange-600"><?= $w['cleaning'] ?></td>
                        <td class="px-6 py-3.5 text-right font-mono font-bold <?= $rate > 75 ? 'text-rose-600' : 'text-slate-800' ?>">
                            <?= $rate ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 3: Clinician Consultation Volumes -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-sm">Consultant Clinical Volumes & Revenue Share</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5">Consultant Doctor</th>
                        <th class="px-4 py-3.5">Specialty / Department</th>
                        <th class="px-4 py-3.5">Consultation Fee</th>
                        <th class="px-4 py-3.5">Total Encounters</th>
                        <th class="px-6 py-3.5 text-right">Gross Billing Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($doctorStats as $doc): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-3.5 font-bold text-slate-800"><?= htmlspecialchars($doc['doctor_name']) ?></td>
                        <td class="px-4 py-3.5 text-slate-600"><?= htmlspecialchars($doc['department_name']) ?></td>
                        <td class="px-4 py-3.5 font-mono"><?= format_currency($doc['consultation_fee']) ?></td>
                        <td class="px-4 py-3.5 font-mono font-bold text-slate-800"><?= $doc['total_consultations'] ?></td>
                        <td class="px-6 py-3.5 text-right font-mono font-bold text-medical-800"><?= format_currency($doc['estimated_revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
