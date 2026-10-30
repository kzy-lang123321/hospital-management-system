<?php
/**
 * Hospital Management System (HMS) - Top Bar (Mobile-Responsive)
 */
$hProfile = hospital_profile();
?>
<header class="h-16 bg-white border-b border-slate-200 px-3 sm:px-6 flex items-center justify-between flex-shrink-0 no-print gap-2">
    <!-- Left: Hamburger Button & Page Title -->
    <div class="flex items-center gap-2 sm:gap-3 overflow-hidden">
        <button id="mobileMenuBtn" type="button" class="md:hidden flex items-center justify-center w-10 h-10 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors focus:outline-none flex-shrink-0" aria-label="Open Navigation Menu">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
        <div class="min-w-0">
            <h2 class="font-bold text-slate-800 text-sm sm:text-base leading-tight truncate"><?= clean_input($pageTitle ?? 'Hospital Management') ?></h2>
            <p class="text-[11px] sm:text-xs text-slate-500 truncate hidden xs:block"><?= htmlspecialchars($hProfile['hospital_name']) ?></p>
        </div>
    </div>

    <!-- Center: Universal Patient Search (Omni-Box) - Hidden for Developers (Strict Privacy) -->
    <?php if (!Auth::isDeveloper()): ?>
    <div class="hidden md:flex items-center flex-1 max-w-md mx-4">
        <form action="index.php" method="GET" class="w-full relative">
            <input type="hidden" name="page" value="patients">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    name="search" 
                    id="globalPatientSearch"
                    aria-label="Search patients by MRN, full name, phone or CNIC"
                    placeholder="Search Patient by MRN, Name, Phone, CNIC... (Ctrl + /)" 
                    class="w-full pl-9 pr-14 py-2 bg-slate-100 hover:bg-slate-50 focus:bg-white text-xs border border-slate-200 focus:border-medical-500 focus:ring-1 focus:ring-medical-500 rounded-lg outline-none transition-all"
                >
                <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-[10px] font-mono bg-white border border-slate-200 text-slate-500 rounded shadow-xs">Ctrl</kbd>
                    <kbd class="px-1.5 py-0.5 text-[10px] font-mono bg-white border border-slate-200 text-slate-500 rounded shadow-xs">/</kbd>
                </div>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="hidden md:flex items-center flex-1 max-w-md mx-4">
        <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 rounded-xl text-xs text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
            <i class="fa-solid fa-shield-halved text-emerald-500"></i>
            <span class="font-medium">Developer Mode: Privacy Shield Enabled (PHI Blocked)</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Right: Quick Status & Emergency Access -->
    <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
        <?php if (!Auth::isDeveloper()): ?>
        <!-- Emergency Hotline Indicator from Database -->
        <div class="hidden xl:flex items-center gap-2 px-2.5 py-1 bg-rose-50 border border-rose-200 rounded-md text-xs text-rose-700">
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
            <span class="font-semibold">ER Hotline:</span> <?= htmlspecialchars($hProfile['emergency_hotline'] ?? '1122') ?>
        </div>
        <?php endif; ?>

        <button type="button" id="workspaceOpen" class="workspace-tool" aria-haspopup="dialog" aria-controls="workspaceDialog" title="Find a workspace (Ctrl+K)">
            <i class="fa-solid fa-compass" aria-hidden="true"></i> <span class="workspace-tool-label">Workspaces <kbd>Ctrl K</kbd></span>
        </button>
        <button type="button" id="workspaceReadable" class="workspace-tool" aria-pressed="false" title="Toggle larger text and roomier tables" aria-label="Larger text and roomier tables">Aa</button>
        <!-- Role Badge & Hospital Context Switcher -->
        <div class="flex items-center gap-2 sm:border-l sm:border-slate-200 sm:pl-4">
            <?php if (Auth::isPlatformAdmin()): ?>
                <!-- Platform Admin Hospital Switcher Dropdown -->
                <?php
                $allFacilities = Tenant::allHospitals();
                $activeHospId = Tenant::id();
                $isSwitched = !empty($_SESSION['platform_active_hospital_id']);
                ?>
                <div class="relative group">
                    <button type="button" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold <?= $isSwitched ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' ?> hover:opacity-90 transition-all">
                        <i class="fa-solid <?= $isSwitched ? 'fa-binoculars' : 'fa-hospital' ?>"></i>
                        <span class="max-w-[120px] truncate"><?= htmlspecialchars($hProfile['hospital_name']) ?></span>
                        <i class="fa-solid fa-chevron-down text-[10px] ml-0.5"></i>
                    </button>
                    <!-- Dropdown Menu -->
                    <div class="hidden group-hover:block absolute right-0 mt-1 w-64 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50">
                        <div class="px-3 py-1.5 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            Switch Facility Context
                        </div>
                        <?php if ($isSwitched): ?>
                        <form method="POST" action="index.php?action=clear_platform_context">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-full text-left px-3 py-2 text-xs text-amber-700 hover:bg-amber-50 font-semibold flex items-center justify-between">
                                <span><i class="fa-solid fa-arrow-left mr-1.5"></i> Return to Platform Scope</span>
                                <span class="text-[10px] bg-amber-200 px-1.5 py-0.5 rounded">Reset</span>
                            </button>
                        </form>
                        <div class="border-t border-slate-100 my-1"></div>
                        <?php endif; ?>
                        
                        <div class="max-h-48 overflow-y-auto">
                            <?php foreach ($allFacilities as $fac): ?>
                            <form method="POST" action="index.php?action=switch_hospital">
                                <?= csrf_field() ?>
                                <input type="hidden" name="hospital_id" value="<?= $fac['id'] ?>">
                                <button type="submit" class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-slate-50 <?= $fac['id'] === $activeHospId ? 'bg-indigo-50/70 font-bold text-indigo-700' : 'text-slate-700' ?>">
                                    <span class="truncate pr-2"><?= htmlspecialchars($fac['hospital_name']) ?></span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-mono <?= $fac['status'] === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                        <?= htmlspecialchars($fac['hospital_code'] ?? 'HOSP') ?>
                                    </span>
                                </button>
                            </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-medical-50 text-medical-700 border border-medical-200 max-w-[120px] sm:max-w-none truncate" title="<?= htmlspecialchars($currentUser['role_name'] ?? 'User') ?>">
                <i class="fa-solid fa-user-shield text-[10px] mr-1"></i>
                <span class="truncate"><?= htmlspecialchars($currentUser['role_name'] ?? 'User') ?></span>
            </span>
        </div>
    </div>
</header>
