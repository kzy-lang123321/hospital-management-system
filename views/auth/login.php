<?php
/**
 * Hospital Management System (HMS) - Login Page
 */
require_once __DIR__ . '/../../config/app.php';
$flash = get_flash();
$hProfile = hospital_profile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= htmlspecialchars($hProfile['hospital_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-navy-900 to-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full">
        <!-- Logo & Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-teal-600 text-white text-2xl shadow-xl shadow-teal-500/20 mb-3">
                <i class="fa-solid fa-house-chimney-medical"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight uppercase"><?= htmlspecialchars($hProfile['hospital_name']) ?></h1>
            <p class="text-xs text-teal-400 font-medium tracking-wide uppercase mt-0.5"><?= htmlspecialchars($hProfile['tagline'] ?? 'Hospital Management System') ?></p>
        </div>

        <!-- Login Card -->
        <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-2xl p-6 sm:p-8 shadow-2xl">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-white">Clinical & Staff Portal</h2>
                <p class="text-xs text-slate-300 mt-1">Sign in with your authenticated hospital credentials</p>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 p-3 rounded-lg text-xs <?= $flash['type'] === 'danger' ? 'bg-rose-500/20 border border-rose-500/50 text-rose-200' : 'bg-emerald-500/20 border border-emerald-500/50 text-emerald-200' ?>">
                <i class="fa-solid fa-circle-exclamation mr-1.5"></i>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
            <?php endif; ?>

            <form action="index.php?action=login" method="POST" class="space-y-4">
                <?= csrf_field() ?>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Username or Hospital Email</label>
                    <div class="relative">
                        <i class="fa-solid fa-user-shield absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input 
                            type="text" 
                            name="username" 
                            id="loginUsername" 
                            required 
                            placeholder="e.g. superadmin, dr.tariq"
                            value="superadmin"
                            class="w-full pl-9 pr-4 py-2.5 bg-slate-900/60 border border-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 rounded-lg text-sm text-white placeholder-slate-500 outline-none transition-all"
                        >
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Secret Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input 
                            type="password" 
                            name="password" 
                            id="loginPassword" 
                            required 
                            placeholder="••••••••"
                            value="Arshad!3453"
                            class="w-full pl-9 pr-10 py-2.5 bg-slate-900/60 border border-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 rounded-lg text-sm text-white placeholder-slate-500 outline-none transition-all"
                        >
                        <button 
                            type="button" 
                            id="togglePasswordBtn" 
                            onclick="togglePasswordVisibility()" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-teal-400 p-1 transition-colors focus:outline-none" 
                            title="Show or hide password"
                            aria-label="Toggle password visibility"
                        >
                            <i class="fa-solid fa-eye text-xs" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" checked class="rounded bg-slate-800 border-slate-700 text-teal-600 focus:ring-0">
                        <span>Remember workstation</span>
                    </label>
                    <span class="text-teal-400 text-xs">ER: <?= htmlspecialchars($hProfile['emergency_hotline'] ?? '1122') ?></span>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-2.5 px-4 bg-gradient-to-r from-teal-600 to-teal-700 hover:from-teal-500 hover:to-teal-600 text-white font-semibold text-sm rounded-lg shadow-lg shadow-teal-700/30 transition-all flex items-center justify-center gap-2"
                >
                    <span>Secure Sign In</span>
                    <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                </button>
            </form>

            <!-- Quick Demo Role Switcher Pills -->
            <div class="mt-6 pt-5 border-t border-slate-800">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2.5 flex items-center justify-between">
                    <span>Quick Management Switcher</span>
                    <span class="text-[10px] text-teal-400 font-mono">1-Click Auto-Fill</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 text-[11px]">
                    <button type="button" onclick="setCredentials('superadmin', 'Arshad!3453')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="CEO / Executive Director">
                        <i class="fa-solid fa-crown text-amber-400 mr-1"></i> Admin
                    </button>
                    <button type="button" onclick="setCredentials('dr.tariq', 'Tariq%6991')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="Chief of OPD">
                        <i class="fa-solid fa-user-doctor text-sky-400 mr-1"></i> OPD Doctor
                    </button>
                    <button type="button" onclick="setCredentials('dr.imran', 'Imran@7929')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="Director of Surgery / OT">
                        <i class="fa-solid fa-hand-holding-medical text-indigo-400 mr-1"></i> OT Surgeon
                    </button>
                    <button type="button" onclick="setCredentials('nurse.fatima', 'Fatima@3117')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="Nursing Superintendent / Wards">
                        <i class="fa-solid fa-user-nurse text-rose-400 mr-1"></i> Nurse (IPD)
                    </button>
                    <button type="button" onclick="setCredentials('pharm.kamran', 'Kamran@4277')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="Chief Pharmacist">
                        <i class="fa-solid fa-pills text-emerald-400 mr-1"></i> Pharmacy
                    </button>
                    <button type="button" onclick="setCredentials('lab.usman', 'Usman#2457')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="Head of Pathology">
                        <i class="fa-solid fa-flask text-purple-400 mr-1"></i> Laboratory
                    </button>
                    <button type="button" onclick="setCredentials('radio.farooq', 'Farooq#5473')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="Head of Radiology & PACS">
                        <i class="fa-solid fa-x-ray text-blue-400 mr-1"></i> Radiology
                    </button>
                    <button type="button" onclick="setCredentials('cashier.hassan', 'Hassan%9562')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="Finance & Billing Head">
                        <i class="fa-solid fa-cash-register text-teal-400 mr-1"></i> Cashier
                    </button>
                    <button type="button" onclick="setCredentials('reception.sana', 'Sana#7271')" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-left truncate transition-colors" title="Front Desk Reception Head">
                        <i class="fa-solid fa-id-card-clip text-pink-400 mr-1"></i> Reception
                    </button>
                </div>
            </div>
        </div>

        <div class="text-center mt-6 text-xs text-slate-500">
            &copy; 2026 <?= htmlspecialchars($hProfile['hospital_name']) ?>. All Rights Reserved.<br>
            Protected Health Information (PHI) Access Monitored & Audited.
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const pwdInput = document.getElementById('loginPassword');
            const icon = document.getElementById('togglePasswordIcon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pwdInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function setCredentials(username, password) {
            document.getElementById('loginUsername').value = username;
            document.getElementById('loginPassword').value = password;
        }
    </script>
</body>
</html>
