<?php
/**
 * Hospital Management System (HMS)
 * Official Printable Credentials Document & PDF Portal
 */

Auth::requireRole(['super_admin', 'hospital_admin']);

$db = Database::getConnection();
$hProfile = hospital_profile();

// Load management credentials
$credsFile = __DIR__ . '/../../scripts/management_credentials.json';
$creds = file_exists($credsFile) ? json_decode(file_get_contents($credsFile), true) : [];

if (empty($creds)) {
    // Fallback directly from DB
    $creds = $db->query("
        SELECT u.id, u.username, u.full_name, u.designation, r.slug as role_slug, u.email, u.phone, 'Admin@123' as password
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.status = 'Active'
        ORDER BY u.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Staff Credentials Directory — <?= htmlspecialchars($hProfile['hospital_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; }
            .print-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen p-4 sm:p-8">

    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Top Action Bar (Screen Only) -->
        <div class="no-print bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="index.php?page=users" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm transition-colors">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Hospital Staff Credentials Directory</h2>
                    <p class="text-xs text-slate-500">Official Access Control & Key Registry</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="index.php?action=download_credentials_pdf" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-sm transition-all">
                    <i class="fa-solid fa-file-pdf text-rose-400"></i>
                    <span>Download Native PDF</span>
                </a>
                <button onclick="window.print()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-sm transition-all">
                    <i class="fa-solid fa-print"></i>
                    <span>Print Document</span>
                </button>
            </div>
        </div>

        <!-- Official Printable Document Card -->
        <div class="print-card bg-white rounded-2xl border border-slate-200 shadow-lg p-6 sm:p-10 space-y-6 relative overflow-hidden">
            
            <!-- Confidential Watermark (Print Friendly) -->
            <div class="absolute right-6 top-6 opacity-10 pointer-events-none text-right">
                <i class="fa-solid fa-shield-halved text-8xl text-slate-900"></i>
            </div>

            <!-- Hospital Official Letterhead -->
            <div class="flex items-start justify-between border-b-2 border-slate-900 pb-5">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-teal-700 text-white text-2xl flex items-center justify-center font-bold shadow-md">
                        <i class="fa-solid fa-house-chimney-medical"></i>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase"><?= htmlspecialchars($hProfile['hospital_name']) ?></h1>
                        <p class="text-xs font-semibold text-teal-700 uppercase tracking-wider mt-0.5"><?= htmlspecialchars($hProfile['tagline'] ?? 'Healthcare Information Systems') ?></p>
                        <p class="text-[11px] text-slate-500 mt-1">
                            <?= htmlspecialchars($hProfile['address']) ?> &bull; Emergency Hotline: <strong class="text-slate-800"><?= htmlspecialchars($hProfile['emergency_hotline']) ?></strong>
                        </p>
                    </div>
                </div>

                <div class="text-right">
                    <span class="inline-block px-2.5 py-1 bg-rose-100 text-rose-800 border border-rose-200 rounded-lg text-[10px] font-black uppercase tracking-wider">
                        RESTRICTED // CONFIDENTIAL
                    </span>
                    <p class="text-xs font-mono font-bold text-slate-700 mt-2">DOC: HMH-CRED-2026</p>
                    <p class="text-[11px] text-slate-500">Date: <?= date('d M Y, h:i A') ?></p>
                </div>
            </div>

            <!-- Purpose Notice Banner -->
            <div class="bg-amber-50/80 border border-amber-200 rounded-xl p-3 text-xs text-amber-900 flex items-start gap-3">
                <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm mt-0.5 flex-shrink-0"></i>
                <div class="space-y-0.5">
                    <p class="font-bold">Security & Governance Directive</p>
                    <p class="text-[11px] text-amber-800">
                        This registry contains authenticated operational credentials for department heads. In compliance with hospital policy, all user actions are audited in immutable electronic logs. Do not disclose or transmit via insecure channels.
                    </p>
                </div>
            </div>

            <!-- Credentials Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border border-slate-200 rounded-lg overflow-hidden">
                    <thead class="bg-slate-900 text-white text-[10px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-3 py-2.5 text-center">#</th>
                            <th class="px-3 py-2.5">Officer / Department Head</th>
                            <th class="px-3 py-2.5">System Role</th>
                            <th class="px-3 py-2.5">Username</th>
                            <th class="px-3 py-2.5">Secret Password</th>
                            <th class="px-3 py-2.5">Designation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($creds as $idx => $c): ?>
                        <tr class="<?= $idx % 2 === 0 ? 'bg-slate-50/70' : 'bg-white' ?>">
                            <td class="px-3 py-2.5 text-center font-mono font-bold text-slate-400 text-[11px]">
                                <?= $idx + 1 ?>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-bold text-slate-900 text-xs"><?= htmlspecialchars($c['full_name']) ?></div>
                                <div class="text-[10px] text-slate-500"><?= htmlspecialchars($c['email']) ?></div>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="px-2 py-0.5 bg-slate-200 text-slate-700 rounded text-[10px] font-mono font-semibold">
                                    <?= htmlspecialchars($c['role_slug'] ?? 'staff') ?>
                                </span>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-teal-700 font-bold text-xs">
                                @<?= htmlspecialchars($c['username']) ?>
                            </td>
                            <td class="px-3 py-2.5">
                                <code class="px-2 py-1 bg-slate-900 text-teal-300 rounded font-mono font-bold text-xs shadow-xs">
                                    <?= htmlspecialchars($c['password']) ?>
                                </code>
                            </td>
                            <td class="px-3 py-2.5 text-[11px] text-slate-600">
                                <?= htmlspecialchars($c['designation']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Signatures & Official Validation Block -->
            <div class="pt-6 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs text-slate-600">
                <div>
                    <p class="font-bold text-slate-800">Security Administration</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">Hospital Information System Lead</p>
                    <div class="h-10 border-b border-dashed border-slate-400 mt-4"></div>
                    <p class="text-[10px] text-slate-400 mt-1">Verified Signature</p>
                </div>

                <div>
                    <p class="font-bold text-slate-800">Clinical & Medical Director</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">Medical Superintendent (MS)</p>
                    <div class="h-10 border-b border-dashed border-slate-400 mt-4"></div>
                    <p class="text-[10px] text-slate-400 mt-1">Authorized Endorsement</p>
                </div>

                <div class="flex flex-col items-center justify-center p-3 border border-slate-300 rounded-xl bg-slate-50 text-center">
                    <i class="fa-solid fa-stamp text-slate-400 text-xl mb-1"></i>
                    <p class="font-bold text-slate-800 text-[11px] uppercase tracking-wider">Hospital Official Seal</p>
                    <p class="text-[10px] text-slate-400">Validated: <?= date('Y-m-d') ?></p>
                </div>
            </div>

            <!-- Footer -->
            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between text-[10px] text-slate-400 gap-2">
                <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($hProfile['hospital_name']) ?>. Protected Health Information (PHI).</span>
                <span class="font-mono">Page 1 of 1 &bull; Document Security Hash: <?= substr(md5(json_encode($creds)), 0, 16) ?></span>
            </div>

        </div>

    </div>

</body>
</html>
