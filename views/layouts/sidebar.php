<?php
/**
 * Hospital Management System (HMS) - Sidebar Navigation
 */
$currentPage = $_GET['page'] ?? 'dashboard';
$userRole = Auth::role();
$hProfile = hospital_profile();

function nav_active(string $pageName, string $currentPage): string {
    return ($pageName === $currentPage) 
        ? 'bg-medical-700 text-white font-medium shadow-sm' 
        : 'text-slate-300 hover:bg-slate-800 hover:text-white';
}
?>
<!-- Sidebar Container -->
<aside class="w-64 bg-navy-950 text-slate-300 flex flex-col flex-shrink-0 h-full border-r border-slate-800 no-print">
    <!-- Brand / Hospital Identity -->
    <div class="h-16 flex items-center px-4 bg-navy-900 border-b border-slate-800 gap-3">
        <div class="w-10 h-10 rounded-lg bg-medical-600 flex items-center justify-center text-white font-bold text-xl shadow-md flex-shrink-0">
            <i class="fa-solid fa-house-chimney-medical"></i>
        </div>
        <div class="leading-tight overflow-hidden">
            <h1 class="font-bold text-white text-sm truncate tracking-tight uppercase"><?= htmlspecialchars($hProfile['hospital_name']) ?></h1>
            <p class="text-[11px] text-medical-400 font-medium truncate"><?= htmlspecialchars($hProfile['tagline'] ?? 'Clinical Care') ?></p>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 overflow-y-auto custom-scrollbar py-4 px-3 space-y-6">
        
        <!-- Main Workspaces -->
        <div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Clinical Workspaces</div>
            <nav class="space-y-1">
                <a href="index.php?page=dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('dashboard', $currentPage) ?>">
                    <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'receptionist', 'doctor', 'nurse', 'cashier'])): ?>
                <a href="index.php?page=patients" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('patients', $currentPage) ?>">
                    <i class="fa-solid fa-user-injured w-5 text-center"></i>
                    <span>Patients (MRN)</span>
                </a>
                <?php endif; ?>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'receptionist', 'doctor'])): ?>
                <a href="index.php?page=appointments" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('appointments', $currentPage) ?>">
                    <i class="fa-solid fa-calendar-check w-5 text-center"></i>
                    <span>Appointments</span>
                </a>
                <?php endif; ?>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <a href="index.php?page=opd_consultation" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('opd_consultation', $currentPage) ?>">
                    <i class="fa-solid fa-stethoscope w-5 text-center"></i>
                    <span>OPD Consultation</span>
                </a>
                <?php endif; ?>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'nurse', 'doctor', 'receptionist'])): ?>
                <a href="index.php?page=ipd_beds" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('ipd_beds', $currentPage) ?>">
                    <i class="fa-solid fa-bed-pulse w-5 text-center"></i>
                    <span>Wards & Bed Rack</span>
                </a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Clinical Ancillaries -->
        <div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Ancillary Services</div>
            <nav class="space-y-1">
                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'pharmacist', 'doctor'])): ?>
                <a href="index.php?page=pharmacy" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('pharmacy', $currentPage) ?>">
                    <i class="fa-solid fa-pills w-5 text-center"></i>
                    <span>Pharmacy (Dispense)</span>
                </a>
                <?php endif; ?>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'lab_technician', 'doctor'])): ?>
                <a href="index.php?page=laboratory" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('laboratory', $currentPage) ?>">
                    <i class="fa-solid fa-flask-vial w-5 text-center"></i>
                    <span>Laboratory & Tests</span>
                </a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Financial & Operations -->
        <div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Revenue & Analytics</div>
            <nav class="space-y-1">
                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'cashier'])): ?>
                <a href="index.php?page=billing" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('billing', $currentPage) ?>">
                    <i class="fa-solid fa-cash-register w-5 text-center"></i>
                    <span>Billing & Cashier</span>
                </a>
                <?php endif; ?>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'cashier'])): ?>
                <a href="index.php?page=reports" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('reports', $currentPage) ?>">
                    <i class="fa-solid fa-file-invoice-dollar w-5 text-center"></i>
                    <span>Financial Reports</span>
                </a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Administration -->
        <?php if (Auth::hasRole(['super_admin', 'hospital_admin'])): ?>
        <div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Administration</div>
            <nav class="space-y-1">
                <a href="index.php?page=settings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('settings', $currentPage) ?>">
                    <i class="fa-solid fa-sliders w-5 text-center"></i>
                    <span>Hospital Masters</span>
                </a>
                <a href="index.php?page=users" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('users', $currentPage) ?>">
                    <i class="fa-solid fa-user-shield w-5 text-center"></i>
                    <span>Staff & Roles</span>
                </a>
                <a href="index.php?page=audit_logs" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('audit_logs', $currentPage) ?>">
                    <i class="fa-solid fa-fingerprint w-5 text-center"></i>
                    <span>Audit Logs</span>
                </a>
            </nav>
        </div>
        <?php endif; ?>

    </div>

    <!-- User Profile Strip at Bottom -->
    <div class="p-3 bg-navy-900 border-t border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2.5 overflow-hidden">
            <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">
                <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="leading-tight overflow-hidden">
                <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($currentUser['full_name'] ?? 'Guest') ?></div>
                <div class="text-[10px] text-slate-400 capitalize truncate"><?= htmlspecialchars($currentUser['role_name'] ?? 'Staff') ?></div>
            </div>
        </div>
        <a href="index.php?action=logout" title="Sign Out" class="text-slate-400 hover:text-red-400 p-1.5 transition-colors">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </a>
    </div>
</aside>
