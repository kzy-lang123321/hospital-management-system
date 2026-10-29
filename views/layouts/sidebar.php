<?php
/**
 * Hospital Management System (HMS) - Sidebar Navigation (Mobile-Responsive Drawer)
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
<!-- Mobile Overlay Backdrop -->
<div id="sidebarBackdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-40 hidden md:hidden transition-opacity duration-300"></div>

<!-- Sidebar Drawer -->
<aside id="mainSidebar" class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-navy-950 text-slate-300 flex flex-col flex-shrink-0 h-full border-r border-slate-800 no-print transform -translate-x-full transition-transform duration-300 ease-in-out md:static md:w-64 md:translate-x-0 md:z-auto">
    <!-- Brand / Hospital Identity -->
    <div class="h-16 flex items-center justify-between px-4 bg-navy-900 border-b border-slate-800 gap-3">
        <div class="flex items-center gap-3 overflow-hidden">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-xl shadow-md flex-shrink-0" style="background-color: <?= htmlspecialchars($hProfile['theme_color'] ?? '#0d9488') ?>;">
                <i class="fa-solid fa-house-chimney-medical"></i>
            </div>
            <div class="leading-tight overflow-hidden">
                <h1 class="font-bold text-white text-sm truncate tracking-tight uppercase"><?= htmlspecialchars($hProfile['hospital_name']) ?></h1>
                <p class="text-[11px] text-medical-400 font-medium truncate"><?= htmlspecialchars($hProfile['tagline'] ?? 'Clinical Care') ?></p>
            </div>
        </div>
        <!-- Close button for mobile -->
        <button id="closeSidebarBtn" type="button" class="md:hidden text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors" aria-label="Close Navigation">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 overflow-y-auto custom-scrollbar py-4 px-3 space-y-6">

        <?php if (Auth::isDeveloper()): ?>
        <!-- Platform Developer / Provider Console (STRICT PRIVACY - INFRASTRUCTURE ONLY) -->
        <div class="p-3 rounded-2xl bg-gradient-to-b from-indigo-950/80 to-slate-900 border border-indigo-800/60 space-y-2 shadow-lg">
            <div class="text-[10px] font-bold text-indigo-300 uppercase tracking-wider px-2 flex items-center gap-1.5 mb-1">
                <i class="fa-solid fa-code"></i> Developer Console
            </div>
            <a href="index.php?page=developer_dashboard" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= nav_active('developer_dashboard', $currentPage) ?>">
                <i class="fa-solid fa-chart-pie w-4 text-center text-indigo-400"></i>
                <span>Platform Telemetry</span>
            </a>
            <a href="index.php?page=developer_registrations" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= nav_active('developer_registrations', $currentPage) ?>">
                <i class="fa-solid fa-file-signature w-4 text-center text-teal-400"></i>
                <span>Hospital Registrations</span>
            </a>
            <a href="index.php?page=developer_hospitals" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= nav_active('developer_hospitals', $currentPage) ?>">
                <i class="fa-solid fa-hospital-user w-4 text-center text-indigo-400"></i>
                <span>Hospital Facilities</span>
            </a>
            <a href="index.php?page=developer_profile" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= nav_active('developer_profile', $currentPage) ?>">
                <i class="fa-solid fa-key w-4 text-center text-amber-400"></i>
                <span>Developer Security</span>
            </a>
        </div>
        <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-[11px] text-slate-400 space-y-1">
            <div class="font-bold text-emerald-400 flex items-center gap-1.5">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Privacy Boundary Active</span>
            </div>
            <p class="text-[10px] text-slate-400 leading-normal">
                Clinical records, patient charts, and financial data are strictly shielded from platform developer view.
            </p>
        </div>

        <?php else: ?>

        <!-- Platform Super Admin Console (When authorized) -->
        <?php if (Auth::isPlatformAdmin()): ?>
        <div class="p-2.5 rounded-xl bg-indigo-950/60 border border-indigo-800/60 space-y-1">
            <div class="text-[10px] font-bold text-indigo-300 uppercase tracking-wider px-2 flex items-center gap-1.5 mb-1.5">
                <i class="fa-solid fa-server"></i> Platform Control
            </div>
            <a href="index.php?page=platform_dashboard" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= nav_active('platform_dashboard', $currentPage) ?>">
                <i class="fa-solid fa-chart-pie w-4 text-center text-indigo-400"></i>
                <span>Platform Metrics</span>
            </a>
            <a href="index.php?page=platform_hospitals" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= nav_active('platform_hospitals', $currentPage) ?>">
                <i class="fa-solid fa-hospital-user w-4 text-center text-indigo-400"></i>
                <span>Hospital Facilities</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Main Workspaces -->
        <div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Clinical Workspaces</div>
            <nav class="space-y-1">
                <a href="index.php?page=dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('dashboard', $currentPage) ?>">
                    <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>

                <a href="index.php?page=attendance" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('attendance', $currentPage) ?>">
                    <i class="fa-solid fa-user-clock w-5 text-center text-teal-400"></i>
                    <span>Staff Attendance</span>
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
                <a href="index.php?page=emergency" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('emergency', $currentPage) ?>">
                    <i class="fa-solid fa-truck-medical w-5 text-center text-red-400"></i>
                    <span>Emergency & ER</span>
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

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'doctor', 'nurse'])): ?>
                <a href="index.php?page=ot" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('ot', $currentPage) ?>">
                    <i class="fa-solid fa-syringe w-5 text-center text-teal-400"></i>
                    <span>Operation Theatre (OT)</span>
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

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'radiologist', 'doctor', 'lab_technician', 'nurse'])): ?>
                <a href="index.php?page=radiology" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('radiology', $currentPage) ?>">
                    <i class="fa-solid fa-x-ray w-5 text-center text-indigo-400"></i>
                    <span>Radiology & PACS</span>
                </a>
                <?php endif; ?>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'doctor', 'lab_technician', 'nurse'])): ?>
                <a href="index.php?page=blood_bank" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('blood_bank', $currentPage) ?>">
                    <i class="fa-solid fa-droplet w-5 text-center text-rose-400"></i>
                    <span>Blood Bank</span>
                </a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Financial & Operations -->
        <div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Revenue & Operations</div>
            <nav class="space-y-1">
                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'cashier'])): ?>
                <a href="index.php?page=billing" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('billing', $currentPage) ?>">
                    <i class="fa-solid fa-cash-register w-5 text-center"></i>
                    <span>Billing & Cashier</span>
                </a>
                <?php endif; ?>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'cashier', 'receptionist'])): ?>
                <a href="index.php?page=insurance" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('insurance', $currentPage) ?>">
                    <i class="fa-solid fa-file-shield w-5 text-center text-sky-400"></i>
                    <span>Insurance / TPA</span>
                </a>
                <?php endif; ?>

                <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'pharmacist'])): ?>
                <a href="index.php?page=inventory" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('inventory', $currentPage) ?>">
                    <i class="fa-solid fa-boxes-packing w-5 text-center text-amber-400"></i>
                    <span>Central Stores</span>
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

        <!-- Administration & Facility Customization -->
        <?php if (Auth::hasRole(['super_admin', 'hospital_admin', 'hospital_owner', 'platform_admin'])): ?>
        <div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Hospital Administration</div>
            <nav class="space-y-1">
                <a href="index.php?page=hospital_customization" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('hospital_customization', $currentPage) ?>">
                    <i class="fa-solid fa-sliders w-5 text-center text-teal-400"></i>
                    <span>Hospital Customization</span>
                </a>
                <a href="index.php?page=settings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= nav_active('settings', $currentPage) ?>">
                    <i class="fa-solid fa-gears w-5 text-center"></i>
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
