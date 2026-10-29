<?php
/**
 * Hospital Management System (HMS)
 * Platform Super Admin - Multi-Hospital Directory & Management
 */

Auth::requireRole(['platform_admin']);

$db = Database::getConnection();

// Fetch all registered hospitals with aggregated statistics
$hospitals = Tenant::allHospitals();

$totalHospitals = count($hospitals);
$activeHospitals = count(array_filter($hospitals, fn($h) => $h['status'] === 'Active'));
$suspendedHospitals = $totalHospitals - $activeHospitals;
$totalStaff = array_sum(array_column($hospitals, 'staff_count'));
$totalPatients = array_sum(array_column($hospitals, 'patient_count'));
?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 rounded-2xl text-white shadow-xl border border-slate-800">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                    <i class="fa-solid fa-server mr-1"></i> Platform Control Center
                </span>
                <span class="text-xs text-slate-400">Multi-Tenant Management</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight">Hospital Facilities & Tenant Directory</h1>
            <p class="text-sm text-slate-300 mt-1">Register independent hospital organizations, assign owners, enforce quotas and monitor operational health.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('registerHospitalModal').classList.remove('hidden')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Register New Hospital</span>
            </button>
            <a href="index.php?page=platform_dashboard" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-sm font-medium border border-slate-700 transition-all">
                <i class="fa-solid fa-chart-pie mr-1"></i> Platform Metrics
            </a>
        </div>
    </div>

    <!-- Operational KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Facilities</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($totalHospitals) ?></h3>
                <span class="text-xs text-emerald-600 font-medium"><i class="fa-solid fa-circle-check mr-1"></i> Multi-Tenant Ready</span>
            </div>
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl shadow-inner">
                <i class="fa-solid fa-hospital"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Active Status</p>
                <h3 class="text-2xl font-bold text-emerald-600 mt-1"><?= number_format($activeHospitals) ?></h3>
                <span class="text-xs text-slate-500"><?= $suspendedHospitals ?> Suspended</span>
            </div>
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl shadow-inner">
                <i class="fa-solid fa-shield-check"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Staff Accounts</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($totalStaff) ?></h3>
                <span class="text-xs text-blue-600 font-medium">Across all tenants</span>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl shadow-inner">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Patient Records</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($totalPatients) ?></h3>
                <span class="text-xs text-purple-600 font-medium">Isolated scopes</span>
            </div>
            <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-xl shadow-inner">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>

    <!-- Active Switch Notice if context switched -->
    <?php if (!empty($_SESSION['platform_active_hospital_id'])): ?>
    <div class="p-4 bg-amber-50 border border-amber-300 rounded-xl text-amber-900 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg bg-amber-200 text-amber-800 flex items-center justify-center font-bold">
                <i class="fa-solid fa-binoculars"></i>
            </span>
            <div>
                <p class="text-sm font-semibold">Active Hospital Context: <u><?= htmlspecialchars(Tenant::profile()['hospital_name']) ?></u> (ID #<?= Tenant::id() ?>)</p>
                <p class="text-xs text-amber-700">You are currently inspecting and managing this facility's records.</p>
            </div>
        </div>
        <form method="POST" action="index.php?action=clear_platform_context">
            <?= csrf_field() ?>
            <button type="submit" class="px-3 py-1.5 bg-amber-800 hover:bg-amber-900 text-white rounded-lg text-xs font-medium transition-all">
                <i class="fa-solid fa-arrow-left mr-1"></i> Exit Context
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Hospital Facilities Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($hospitals as $h): ?>
        <div class="bg-white rounded-2xl border <?= $h['id'] === Tenant::id() ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200' ?> shadow-sm hover:shadow-md transition-all flex flex-col justify-between overflow-hidden">
            <!-- Card Header with Theme Strip -->
            <div class="h-2" style="background-color: <?= htmlspecialchars($h['theme_color'] ?? '#0d9488') ?>;"></div>
            
            <div class="p-5 flex-1 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-sm" style="background-color: <?= htmlspecialchars($h['theme_color'] ?? '#0d9488') ?>;">
                            <?= strtoupper(substr($h['hospital_name'], 0, 2)) ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-base leading-snug"><?= htmlspecialchars($h['hospital_name']) ?></h3>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[11px] font-mono font-bold tracking-wider">
                                    <?= htmlspecialchars($h['hospital_code'] ?? 'HOSP') ?>
                                </span>
                                <span class="text-xs text-slate-500"><i class="fa-solid fa-location-dot mr-1"></i><?= htmlspecialchars($h['city'] ?? 'Facility') ?></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <?php if ($h['status'] === 'Active'): ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Suspended
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="text-xs text-slate-500 line-clamp-2 italic">"<?= htmlspecialchars($h['tagline'] ?? 'Healthcare facility') ?>"</p>

                <!-- Key Metrics Pills -->
                <div class="grid grid-cols-3 gap-2 py-2 border-y border-slate-100 text-center bg-slate-50/60 rounded-xl p-2">
                    <div>
                        <p class="text-[10px] text-slate-400 font-medium uppercase">Staff</p>
                        <p class="text-sm font-bold text-slate-700"><?= number_format($h['staff_count'] ?? 0) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-medium uppercase">Patients</p>
                        <p class="text-sm font-bold text-slate-700"><?= number_format($h['patient_count'] ?? 0) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-medium uppercase">Visits</p>
                        <p class="text-sm font-bold text-slate-700"><?= number_format($h['encounter_count'] ?? 0) ?></p>
                    </div>
                </div>

                <!-- Owner & Config Details -->
                <div class="space-y-1.5 text-xs text-slate-600">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Owner:</span>
                        <span class="font-medium text-slate-800"><?= htmlspecialchars($h['owner_name'] ?? 'Not Assigned') ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Plan:</span>
                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 font-semibold rounded text-[11px]"><?= htmlspecialchars($h['subscription_plan'] ?? 'Enterprise') ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Prefixes:</span>
                        <span class="font-mono text-[11px] text-slate-700">MRN: <strong><?= htmlspecialchars($h['mrn_prefix'] ?? 'MRN') ?></strong> | INV: <strong><?= htmlspecialchars($h['invoice_prefix'] ?? 'INV') ?></strong></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Currency / Tax:</span>
                        <span class="font-medium text-slate-700"><?= htmlspecialchars($h['currency_symbol'] ?? 'PKR ') ?> (<?= number_format((float)($h['default_tax_rate'] ?? 0), 1) ?>%)</span>
                    </div>
                </div>
            </div>

            <!-- Card Actions Footer -->
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                <form method="POST" action="index.php?action=switch_hospital" class="flex-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="hospital_id" value="<?= $h['id'] ?>">
                    <button type="submit" class="w-full px-3 py-2 bg-slate-900 hover:bg-indigo-600 text-white rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        <span>Enter Workspace</span>
                    </button>
                </form>

                <form method="POST" action="index.php?action=toggle_hospital_status" onsubmit="return confirm('Change status for <?= addslashes($h['hospital_name']) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="hospital_id" value="<?= $h['id'] ?>">
                    <button type="submit" class="p-2 border rounded-lg text-xs transition-all <?= $h['status'] === 'Active' ? 'border-amber-200 text-amber-700 hover:bg-amber-100' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-100' ?>" title="<?= $h['status'] === 'Active' ? 'Suspend Hospital' : 'Activate Hospital' ?>">
                        <i class="fa-solid <?= $h['status'] === 'Active' ? 'fa-ban' : 'fa-check' ?>"></i>
                    </button>
                </form>

                <a href="index.php?page=hospital_customization&hospital_id=<?= $h['id'] ?>" class="p-2 border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-lg text-xs transition-all" title="Customize Settings">
                    <i class="fa-solid fa-sliders"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Register New Hospital Modal -->
<div id="registerHospitalModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full border border-slate-200 overflow-hidden my-8">
        <div class="p-6 bg-gradient-to-r from-slate-900 to-indigo-950 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300">
                    <i class="fa-solid fa-hospital-user text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg">Register Independent Hospital & Owner Account</h3>
                    <p class="text-xs text-slate-300">Creates an isolated multi-tenant database scope and initial departments.</p>
                </div>
            </div>
            <button onclick="document.getElementById('registerHospitalModal').classList.add('hidden')" class="text-slate-400 hover:text-white text-xl">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=create_hospital" class="p-6 space-y-6">
            <?= csrf_field() ?>

            <!-- Facility Information Section -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-3 flex items-center gap-1.5">
                    <i class="fa-solid fa-building-circle-check"></i> 1. Hospital Facility Profile
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Hospital Name *</label>
                        <input type="text" name="hospital_name" required placeholder="e.g. Shifa International Hospital" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Facility Short Code *</label>
                        <input type="text" name="hospital_code" required placeholder="e.g. SIH" class="w-full px-3 py-2 text-sm uppercase font-mono border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tagline / Motto</label>
                        <input type="text" name="tagline" placeholder="e.g. Compassionate Care, World-Class Medicine" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">City / Region *</label>
                        <input type="text" name="city" required placeholder="e.g. Islamabad" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Emergency Hotline</label>
                        <input type="text" name="emergency_hotline" placeholder="e.g. 051-111-222" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Full Street Address</label>
                        <input type="text" name="address" placeholder="Sector H-8/4, Pitras Bukhari Road" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                </div>
            </div>

            <!-- Customization & Prefixes -->
            <div class="pt-4 border-t border-slate-200">
                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-3 flex items-center gap-1.5">
                    <i class="fa-solid fa-palette"></i> 2. Branding, Prefixes & Localization
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Patient MRN Prefix</label>
                        <input type="text" name="mrn_prefix" required placeholder="SIH" value="SIH" class="w-full px-3 py-2 text-sm uppercase font-mono border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" required placeholder="INV-SIH" value="INV" class="w-full px-3 py-2 text-sm uppercase font-mono border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Currency Symbol</label>
                        <input type="text" name="currency_symbol" required placeholder="PKR " value="PKR " class="w-full px-3 py-2 text-sm font-semibold border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Theme Brand Color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="theme_color" value="#0284c7" class="w-10 h-9 p-0.5 border border-slate-300 rounded-lg cursor-pointer">
                            <span class="text-xs text-slate-500 font-mono">Accent</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hospital Owner Account Section -->
            <div class="pt-4 border-t border-slate-200">
                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-3 flex items-center gap-1.5">
                    <i class="fa-solid fa-user-shield"></i> 3. Hospital Owner Account Credentials
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Owner Full Name *</label>
                        <input type="text" name="owner_name" required placeholder="Dr. Muhammad Tariq" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Owner Email *</label>
                        <input type="email" name="owner_email" required placeholder="owner@hospital.com" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Login Username *</label>
                        <input type="text" name="owner_username" required placeholder="shifa.admin" class="w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Login Password *</label>
                        <input type="password" name="owner_password" required placeholder="Strong password" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Owner Phone</label>
                        <input type="text" name="owner_phone" placeholder="0300-1234567" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Subscription Plan</label>
                        <select name="subscription_plan" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                            <option value="Enterprise" selected>Enterprise (Full Suite)</option>
                            <option value="Professional">Professional</option>
                            <option value="Starter">Starter</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                <button type="button" onclick="document.getElementById('registerHospitalModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-md shadow-indigo-600/30 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span>Register Hospital & Deploy Tenant</span>
                </button>
            </div>
        </form>
    </div>
</div>
