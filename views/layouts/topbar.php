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

    <!-- Center: Universal Patient Search (Omni-Box) -->
    <div class="hidden md:flex items-center flex-1 max-w-md mx-4">
        <form action="index.php" method="GET" class="w-full relative">
            <input type="hidden" name="page" value="patients">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    name="search" 
                    id="globalPatientSearch"
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

    <!-- Right: Quick Status & Emergency Access -->
    <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
        <!-- Emergency Hotline Indicator from Database -->
        <div class="hidden xl:flex items-center gap-2 px-2.5 py-1 bg-rose-50 border border-rose-200 rounded-md text-xs text-rose-700">
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
            <span class="font-semibold">ER Hotline:</span> <?= htmlspecialchars($hProfile['emergency_hotline'] ?? '1122') ?>
        </div>

        <!-- Role Badge -->
        <div class="flex items-center gap-2 sm:border-l sm:border-slate-200 sm:pl-4">
            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-medical-50 text-medical-700 border border-medical-200 max-w-[120px] sm:max-w-none truncate" title="<?= htmlspecialchars($currentUser['role_name'] ?? 'User') ?>">
                <i class="fa-solid fa-user-shield text-[10px] mr-1"></i>
                <span class="truncate"><?= htmlspecialchars($currentUser['role_name'] ?? 'User') ?></span>
            </span>
        </div>
    </div>
</header>
