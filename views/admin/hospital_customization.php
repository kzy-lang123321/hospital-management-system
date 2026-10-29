<?php
/**
 * Hospital Management System (HMS)
 * Hospital Owner / Super Admin - Facility Customization & Settings Panel
 */

Auth::requireRole(['hospital_owner', 'super_admin', 'platform_admin']);

$db = Database::getConnection();

// Determine target hospital ID
$targetHospitalId = Tenant::id();
if (Auth::isPlatformAdmin() && isset($_GET['hospital_id'])) {
    $targetHospitalId = (int)$_GET['hospital_id'];
}

$profile = Tenant::profile($targetHospitalId);
if (!$profile) {
    set_flash('danger', 'Requested hospital profile not found.');
    header("Location: index.php?page=dashboard");
    exit;
}

// Fetch departments belonging to this hospital
$deptStmt = $db->prepare("SELECT * FROM `departments` WHERE hospital_id = ? ORDER BY department_name ASC");
$deptStmt->execute([$targetHospitalId]);
$hospitalDepts = $deptStmt->fetchAll();

// Decode enabled modules
$modules = !empty($profile['modules_enabled']) ? json_decode($profile['modules_enabled'], true) : [];
$allModules = [
    'opd' => ['label' => 'Outpatient Consultations (OPD)', 'icon' => 'fa-stethoscope'],
    'ipd' => ['label' => 'Inpatient & Wards (IPD)', 'icon' => 'fa-bed-pulse'],
    'emergency' => ['label' => 'Emergency & Trauma Care (ER)', 'icon' => 'fa-truck-medical'],
    'ot' => ['label' => 'Operation Theatres (OT)', 'icon' => 'fa-hospital-user'],
    'pharmacy' => ['label' => 'Hospital Pharmacy & POS', 'icon' => 'fa-pills'],
    'laboratory' => ['label' => 'Clinical Pathology & Lab', 'icon' => 'fa-flask-vial'],
    'radiology' => ['label' => 'Radiology PACS & Imaging', 'icon' => 'fa-x-ray'],
    'blood_bank' => ['label' => 'Blood Bank & Transfusions', 'icon' => 'fa-droplet'],
    'billing' => ['label' => 'Cashier & Patient Billing', 'icon' => 'fa-file-invoice-dollar'],
    'insurance' => ['label' => 'Insurance & Corporate TPA', 'icon' => 'fa-shield-halved'],
    'inventory' => ['label' => 'Procurement & Inventory', 'icon' => 'fa-boxes-stacked'],
    'reports' => ['label' => 'Operational Analytics', 'icon' => 'fa-chart-mixed']
];

$theme = $profile['theme_color'] ?? '#0d9488';
?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-2xl text-white shadow-lg border border-slate-700/50" style="background: linear-gradient(135deg, #0f172a 0%, <?= htmlspecialchars($theme) ?>dd 100%);">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white border border-white/30">
                    <i class="fa-solid fa-sliders mr-1"></i> Hospital Customization
                </span>
                <span class="text-xs text-white/80">Facility ID #<?= $profile['id'] ?> (<?= htmlspecialchars($profile['hospital_code'] ?? 'HOSP') ?>)</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight"><?= htmlspecialchars($profile['hospital_name']) ?> — Configuration</h1>
            <p class="text-sm text-white/90 mt-1">Configure branding, print headers, numbering prefixes, currency, departments, and active clinical modules.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white/20 text-white backdrop-blur-sm border border-white/30">
                <i class="fa-solid fa-certificate mr-1"></i> Plan: <?= htmlspecialchars($profile['subscription_plan'] ?? 'Enterprise') ?>
            </span>
            <?php if (Auth::isPlatformAdmin()): ?>
            <a href="index.php?page=platform_hospitals" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-medium border border-white/30 transition-all">
                <i class="fa-solid fa-arrow-left mr-1"></i> Hospital Directory
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Settings Form -->
    <form method="POST" action="index.php?action=save_hospital_customization" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="hospital_id" value="<?= $profile['id'] ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Main Configuration Sections -->
            <div class="lg:col-span-2 space-y-6">

                <!-- 1. Identity & Branding -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white" style="background-color: <?= htmlspecialchars($theme) ?>;">
                            <i class="fa-solid fa-paintbrush text-sm"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800">1. Hospital Identity & Theme Branding</h2>
                            <p class="text-xs text-slate-500">Customize how your hospital appears to clinical staff, on portals, and on print receipts.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Hospital Display Name *</label>
                            <input type="text" name="hospital_name" value="<?= htmlspecialchars($profile['hospital_name']) ?>" required class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Facility Short Code *</label>
                            <input type="text" name="hospital_code" value="<?= htmlspecialchars($profile['hospital_code'] ?? '') ?>" required class="w-full px-3.5 py-2 text-sm font-mono uppercase border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Primary Theme Accent Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="theme_color" value="<?= htmlspecialchars($profile['theme_color'] ?? '#0d9488') ?>" class="w-12 h-10 p-1 border border-slate-300 rounded-lg cursor-pointer">
                                <input type="text" value="<?= htmlspecialchars($profile['theme_color'] ?? '#0d9488') ?>" readonly class="w-28 px-3 py-2 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg text-slate-600">
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tagline / Mission Motto</label>
                            <input type="text" name="tagline" value="<?= htmlspecialchars($profile['tagline'] ?? '') ?>" class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Document & Prescription Footer Note</label>
                            <textarea name="footer_notes" rows="2" placeholder="e.g. This is a computer-generated medical prescription. Not valid for court without official stamp." class="w-full px-3.5 py-2 text-xs border border-slate-300 rounded-lg"><?= htmlspecialchars($profile['footer_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 2. Prefixes, Numbering & Financials -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-slate-800">
                            <i class="fa-solid fa-barcode text-sm"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800">2. Identifier Prefixes, Localization & Billing</h2>
                            <p class="text-xs text-slate-500">Configure unique patient MRN formats, invoice sequences, currency and taxes.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Patient MRN Prefix</label>
                            <input type="text" name="mrn_prefix" value="<?= htmlspecialchars($profile['mrn_prefix'] ?? 'MRN') ?>" required class="w-full px-3 py-2 text-sm font-mono uppercase border border-slate-300 rounded-lg">
                            <p class="text-[10px] text-slate-400 mt-0.5">e.g. <?= htmlspecialchars($profile['mrn_prefix'] ?? 'MRN') ?>-2026-0001</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Invoice Prefix</label>
                            <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($profile['invoice_prefix'] ?? 'INV') ?>" required class="w-full px-3 py-2 text-sm font-mono uppercase border border-slate-300 rounded-lg">
                            <p class="text-[10px] text-slate-400 mt-0.5">e.g. <?= htmlspecialchars($profile['invoice_prefix'] ?? 'INV') ?>-2026-0001</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Currency Symbol</label>
                            <input type="text" name="currency_symbol" value="<?= htmlspecialchars($profile['currency_symbol'] ?? 'PKR ') ?>" required class="w-full px-3 py-2 text-sm font-semibold border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Default Tax (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="default_tax_rate" value="<?= htmlspecialchars($profile['default_tax_rate'] ?? '0.00') ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Date Format</label>
                            <select name="date_format" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                                <option value="d M, Y" <?= ($profile['date_format'] ?? '') === 'd M, Y' ? 'selected' : '' ?>>29 Oct, 2026</option>
                                <option value="Y-m-d" <?= ($profile['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>2026-10-29</option>
                                <option value="m/d/Y" <?= ($profile['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>10/29/2026</option>
                                <option value="d/m/Y" <?= ($profile['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>29/10/2026</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Time Zone</label>
                            <select name="time_zone" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                                <option value="Asia/Karachi" <?= ($profile['time_zone'] ?? '') === 'Asia/Karachi' ? 'selected' : '' ?>>Asia/Karachi (GMT+5)</option>
                                <option value="Asia/Riyadh" <?= ($profile['time_zone'] ?? '') === 'Asia/Riyadh' ? 'selected' : '' ?>>Asia/Riyadh (GMT+3)</option>
                                <option value="Asia/Dubai" <?= ($profile['time_zone'] ?? '') === 'Asia/Dubai' ? 'selected' : '' ?>>Asia/Dubai (GMT+4)</option>
                                <option value="UTC" <?= ($profile['time_zone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC (Coordinated Universal)</option>
                                <option value="Europe/London" <?= ($profile['time_zone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>Europe/London (GMT+0/+1)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3. Physical Location & Contact Info -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-blue-600">
                            <i class="fa-solid fa-map-location-dot text-sm"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800">3. Facility Contacts & Registration Details</h2>
                            <p class="text-xs text-slate-500">Official physical address, emergency line, phone, and healthcare commission license.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Official Telephone</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">24/7 Emergency Hotline</label>
                            <input type="text" name="emergency_hotline" value="<?= htmlspecialchars($profile['emergency_hotline'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Public Inquiry Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">City & Country</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="city" value="<?= htmlspecialchars($profile['city'] ?? '') ?>" placeholder="City" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                                <input type="text" name="country" value="<?= htmlspecialchars($profile['country'] ?? 'Pakistan') ?>" placeholder="Country" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Healthcare Commission License #</label>
                            <input type="text" name="license_number" value="<?= htmlspecialchars($profile['license_number'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">National Tax Number (NTN)</label>
                            <input type="text" name="tax_number" value="<?= htmlspecialchars($profile['tax_number'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Street Address</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($profile['address'] ?? '') ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Enabled Modules & Departments Overview -->
            <div class="space-y-6">

                <!-- 4. Module Activation Toggles -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-purple-600">
                            <i class="fa-solid fa-cubes text-sm"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800">Operational Modules</h2>
                            <p class="text-xs text-slate-500">Enable or disable departmental subsystems for this facility.</p>
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        <?php foreach ($allModules as $key => $m): ?>
                        <?php $isEnabled = !empty($modules) ? !empty($modules[$key]) : true; ?>
                        <label class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:bg-slate-50 cursor-pointer transition-all">
                            <div class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-xs">
                                    <i class="fa-solid <?= $m['icon'] ?>"></i>
                                </span>
                                <span class="text-xs font-semibold text-slate-700"><?= $m['label'] ?></span>
                            </div>
                            <input type="checkbox" name="modules[<?= $key ?>]" value="1" <?= $isEnabled ? 'checked' : '' ?> class="w-4 h-4 text-teal-600 rounded border-slate-300 focus:ring-teal-500">
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 5. Active Departments Summary -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-sitemap text-indigo-600"></i>
                            <h3 class="text-sm font-bold text-slate-800">Departments (<?= count($hospitalDepts) ?>)</h3>
                        </div>
                        <a href="index.php?page=settings#departments" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Manage</a>
                    </div>

                    <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1 text-xs">
                        <?php foreach ($hospitalDepts as $dept): ?>
                        <div class="p-2 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-slate-800"><?= htmlspecialchars($dept['department_name']) ?></span>
                                <span class="text-[10px] text-slate-400 ml-1 font-mono">[<?= htmlspecialchars($dept['code']) ?>]</span>
                            </div>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-white text-slate-600 border border-slate-200 font-medium">
                                <?= htmlspecialchars($dept['type']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Save Action Button -->
                <div class="sticky bottom-6">
                    <button type="submit" class="w-full py-3.5 px-6 rounded-xl text-white font-bold text-sm shadow-xl transition-all flex items-center justify-center gap-2" style="background-color: <?= htmlspecialchars($theme) ?>;">
                        <i class="fa-solid fa-floppy-disk text-base"></i>
                        <span>Save Hospital Customization</span>
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>
