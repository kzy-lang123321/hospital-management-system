<?php
/**
 * Hospital Management System (HMS) - Hospital Masters & System Settings
 */
require_once __DIR__ . '/../../config/database.php';
Auth::requireRole(['super_admin', 'hospital_admin']);

$db = Database::getConnection();
$activeTab = clean_input($_GET['tab'] ?? 'profile');

// 1. Fetch Hospital Profile
$hpStmt = $db->query("SELECT * FROM `hospital_profiles` ORDER BY id ASC LIMIT 1");
$profile = $hpStmt->fetch() ?: [];

// 2. Fetch Departments
$departments = $db->query("SELECT * FROM `departments` ORDER BY department_name ASC")->fetchAll();

// 3. Fetch Wards & Rooms
$wards = $db->query("SELECT * FROM `wards` ORDER BY id ASC")->fetchAll();
$rooms = $db->query("SELECT r.*, w.ward_name FROM `rooms` r JOIN `wards` w ON r.ward_id = w.id ORDER BY r.id ASC")->fetchAll();

// 4. Fetch Tariffs
$tariffs = $db->query("SELECT t.*, d.department_name FROM `tariff_services` t LEFT JOIN `departments` d ON t.department_id = d.id ORDER BY t.service_name ASC")->fetchAll();

// 5. Fetch Drug Categories & Items
$drugCategories = $db->query("SELECT * FROM `drug_categories` ORDER BY category_name ASC")->fetchAll();
$drugItems = $db->query("SELECT d.*, c.category_name FROM `drug_items` d JOIN `drug_categories` c ON d.category_id = c.id ORDER BY d.brand_name ASC")->fetchAll();

// 6. Fetch Lab Categories & Tests
$labCategories = $db->query("SELECT * FROM `lab_categories` ORDER BY category_name ASC")->fetchAll();
$labTests = $db->query("SELECT lt.*, lc.category_name FROM `lab_tests` lt JOIN `lab_categories` lc ON lt.category_id = lc.id ORDER BY lt.test_name ASC")->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Hospital Masters & Dynamic Configuration</h2>
            <p class="text-xs text-slate-500">Configure facility profile, clinical departments, wards, tariffs, formulary, and test catalogs</p>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 flex flex-wrap items-center gap-4 text-xs font-semibold">
        <?php
        $settingsTabs = [
            'profile' => ['label' => 'Facility Profile', 'icon' => 'fa-hospital'],
            'departments' => ['label' => 'Departments', 'icon' => 'fa-sitemap'],
            'wards' => ['label' => 'Wards & Beds', 'icon' => 'fa-bed'],
            'tariffs' => ['label' => 'Service Tariffs', 'icon' => 'fa-tag'],
            'drugs' => ['label' => 'Drug Formulary', 'icon' => 'fa-pills'],
            'labs' => ['label' => 'Lab Catalog', 'icon' => 'fa-flask'],
        ];
        foreach ($settingsTabs as $key => $t):
            $isActive = ($activeTab === $key);
        ?>
        <a href="index.php?page=settings&tab=<?= $key ?>" class="pb-3 flex items-center gap-2 border-b-2 transition-all <?= $isActive ? 'border-medical-600 text-medical-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid <?= $t['icon'] ?>"></i>
            <span><?= $t['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Tab 1: Facility Profile Form -->
    <?php if ($activeTab === 'profile'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 max-w-4xl">
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-hospital-user text-medical-600"></i>
            <span>Hospital Profile & Identity Settings</span>
        </h3>

        <form action="index.php?action=settings_update" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="profile_id" value="<?= $profile['id'] ?? 1 ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Hospital Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="hospital_name" required value="<?= htmlspecialchars($profile['hospital_name'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-bold text-slate-800">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tagline / Motto</label>
                    <input type="text" name="tagline" value="<?= htmlspecialchars($profile['tagline'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Emergency Hotline Phone <span class="text-rose-500">*</span></label>
                    <input type="text" name="emergency_hotline" required value="<?= htmlspecialchars($profile['emergency_hotline'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-bold text-rose-600">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">General Reception Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Official Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Currency Symbol</label>
                    <input type="text" name="currency_symbol" value="<?= htmlspecialchars($profile['currency_symbol'] ?? 'PKR ') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono font-bold">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">License / Accreditation #</label>
                    <input type="text" name="license_number" value="<?= htmlspecialchars($profile['license_number'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">National Tax # (NTN)</label>
                    <input type="text" name="tax_number" value="<?= htmlspecialchars($profile['tax_number'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($profile['city'] ?? 'Peshawar') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Country</label>
                    <input type="text" name="country" value="<?= htmlspecialchars($profile['country'] ?? 'Pakistan') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Physical Street Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($profile['address'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-medical-600 hover:bg-medical-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save Hospital Profile Settings</span>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Tab 2: Departments Master -->
    <?php if ($activeTab === 'departments'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-medical-600"></i>
                <span>Add New Department</span>
            </h3>
            <form action="index.php?action=department_store" method="POST" class="space-y-3">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Department Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="department_name" required placeholder="e.g. Orthopedics & Trauma" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Code <span class="text-rose-500">*</span></label>
                    <input type="text" name="code" required placeholder="e.g. ORTHO" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 uppercase font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Type</label>
                    <select name="type" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                        <option value="Clinical">Clinical</option>
                        <option value="Ancillary">Ancillary</option>
                        <option value="Administrative">Administrative</option>
                        <option value="Support">Support</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" placeholder="Brief clinical scope..." class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500"></textarea>
                </div>
                <button type="submit" class="w-full py-2 bg-medical-600 hover:bg-medical-700 text-white font-bold text-xs rounded-lg shadow-sm">
                    Create Department
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 text-sm">Configured Hospital Departments</h3>
                <span class="text-xs text-slate-500 font-mono"><?= count($departments) ?> Listed</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3">Code</th>
                            <th class="px-4 py-3">Department Name</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($departments as $d): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-mono font-bold text-medical-800"><?= htmlspecialchars($d['code']) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars($d['department_name']) ?></td>
                            <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($d['type']) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800"><?= htmlspecialchars($d['status']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 3: Wards & Beds Master -->
    <?php if ($activeTab === 'wards'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Add Ward Form -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-5">
            <div>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle text-medical-600"></i>
                    <span>Create New Ward</span>
                </h3>
                <form action="index.php?action=ward_store" method="POST" class="space-y-3">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ward Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="ward_name" required placeholder="e.g. Neonatal ICU (NICU)" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ward Code <span class="text-rose-500">*</span></label>
                        <input type="text" name="code" required placeholder="e.g. NICU-1" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 uppercase font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ward Classification</label>
                        <select name="ward_type" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <option value="General_Male">General Male</option>
                            <option value="General_Female">General Female</option>
                            <option value="Semi-Private">Semi-Private</option>
                            <option value="Private">Private</option>
                            <option value="ICU">ICU</option>
                            <option value="CCU">CCU</option>
                            <option value="Emergency_Bay">Emergency Bay</option>
                            <option value="Isolation">Isolation</option>
                            <option value="Maternity">Maternity</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Floor Location</label>
                        <input type="text" name="floor_number" value="2nd Floor" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                    <button type="submit" class="w-full py-2 bg-medical-600 hover:bg-medical-700 text-white font-bold text-xs rounded-lg shadow-sm">
                        Create Ward
                    </button>
                </form>
            </div>

            <!-- Add Bed Form -->
            <div class="pt-5 border-t border-slate-100">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-bed text-purple-600"></i>
                    <span>Add Bed to Room/Ward</span>
                </h3>
                <form action="index.php?action=bed_store" method="POST" class="space-y-3">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Select Room & Ward <span class="text-rose-500">*</span></label>
                        <select name="room_id" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['room_number']) ?> &mdash; <?= htmlspecialchars($r['ward_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Bed Number / Label <span class="text-rose-500">*</span></label>
                        <input type="text" name="bed_number" required placeholder="e.g. ICU-B3, BED-105" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Bed Type</label>
                        <select name="bed_type" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <option value="Standard">Standard</option>
                            <option value="Electric">Electric</option>
                            <option value="ICU_Ventilated">ICU Ventilated</option>
                            <option value="Emergency_Trolley">Emergency Trolley</option>
                            <option value="Pediatric_Cot">Pediatric Cot</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Daily Tariff (PKR) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" name="daily_tariff" required value="2000.00" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono font-bold">
                    </div>
                    <button type="submit" class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-lg shadow-sm">
                        Add Bed
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 text-sm">Wards & Bed Directory</h3>
                <span class="text-xs text-slate-500 font-mono"><?= count($wards) ?> Wards</span>
            </div>
            <div class="p-4 space-y-4">
                <?php foreach ($wards as $w): 
                    $wBeds = $db->prepare("SELECT b.*, r.room_number FROM `beds` b JOIN `rooms` r ON b.room_id = r.id WHERE r.ward_id = ? ORDER BY b.id ASC");
                    $wBeds->execute([$w['id']]);
                    $bedsInWard = $wBeds->fetchAll();
                ?>
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($w['ward_name']) ?></span>
                            <span class="text-xs text-slate-500 ml-2">(<?= htmlspecialchars($w['ward_type']) ?> &bull; <?= htmlspecialchars($w['floor_number']) ?>)</span>
                        </div>
                        <span class="text-xs font-mono font-bold text-medical-800"><?= count($bedsInWard) ?> Beds</span>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-2">
                        <?php foreach ($bedsInWard as $b): ?>
                        <span class="px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-xs font-mono font-semibold flex items-center gap-1.5 shadow-2xs">
                            <span class="w-2 h-2 rounded-full <?= $b['status'] === 'Available' ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                            <span><?= htmlspecialchars($b['bed_number']) ?></span>
                            <span class="text-[10px] text-slate-400">(<?= format_currency($b['daily_tariff']) ?>)</span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 4: Tariffs & Services Master -->
    <?php if ($activeTab === 'tariffs'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-medical-600"></i>
                <span>Add Hospital Service Tariff</span>
            </h3>
            <form action="index.php?action=tariff_store" method="POST" class="space-y-3">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Service Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="service_name" required placeholder="e.g. Echocardiography (ECHO)" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Service Code <span class="text-rose-500">*</span></label>
                    <input type="text" name="service_code" required placeholder="e.g. DIAG-ECHO" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 uppercase font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Category</label>
                    <select name="category" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                        <option value="Consultation">Consultation</option>
                        <option value="Procedure">Procedure</option>
                        <option value="Nursing">Nursing</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Emergency">Emergency</option>
                        <option value="Bed_Charge">Bed Charge</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Department</label>
                    <select name="department_id" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                        <option value="">Hospital General</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Standard Price (PKR) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" name="standard_price" required placeholder="e.g. 3500.00" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono font-bold">
                </div>
                <button type="submit" class="w-full py-2 bg-medical-600 hover:bg-medical-700 text-white font-bold text-xs rounded-lg shadow-sm">
                    Add Tariff Service
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 text-sm">Official Service Tariff Master</h3>
                <span class="text-xs text-slate-500 font-mono"><?= count($tariffs) ?> Services</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3">Code</th>
                            <th class="px-4 py-3">Service Description</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-6 py-3 text-right">Standard Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($tariffs as $t): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-mono font-bold text-slate-700"><?= htmlspecialchars($t['service_code']) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars($t['service_name']) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700"><?= htmlspecialchars($t['category']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($t['department_name'] ?? 'Hospital General') ?></td>
                            <td class="px-6 py-3 text-right font-mono font-bold text-medical-800"><?= format_currency($t['standard_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 5: Drug Formulary Master -->
    <?php if ($activeTab === 'drugs'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-5">
            <div>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-pills text-teal-600"></i>
                    <span>Add New Drug to Formulary</span>
                </h3>
                <form action="index.php?action=drug_store" method="POST" class="space-y-3">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Brand Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="brand_name" required placeholder="e.g. Ciproxin 500mg" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Generic Molecule <span class="text-rose-500">*</span></label>
                        <input type="text" name="generic_name" required placeholder="e.g. Ciprofloxacin HCl" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Category</label>
                            <select name="category_id" class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                                <?php foreach ($drugCategories as $dc): ?>
                                    <option value="<?= $dc['id'] ?>"><?= htmlspecialchars($dc['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Dosage Form</label>
                            <select name="dosage_form" class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                                <option value="Tablet">Tablet</option>
                                <option value="Capsule">Capsule</option>
                                <option value="Syrup">Syrup</option>
                                <option value="Injection">Injection</option>
                                <option value="Infusion">Infusion</option>
                                <option value="Ointment">Ointment</option>
                                <option value="Eye_Drops">Eye Drops</option>
                                <option value="Inhaler">Inhaler</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Strength (e.g. 500mg, 10mg/ml) <span class="text-rose-500">*</span></label>
                        <input type="text" name="strength" required placeholder="500mg" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                    <button type="submit" class="w-full py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-lg shadow-sm">
                        Add Drug Item
                    </button>
                </form>
            </div>

            <!-- Add Batch Form -->
            <div class="pt-5 border-t border-slate-100">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked text-amber-600"></i>
                    <span>Receive / Add Batch Stock</span>
                </h3>
                <form action="index.php?action=drug_batch_store" method="POST" class="space-y-3">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Select Drug <span class="text-rose-500">*</span></label>
                        <select name="drug_item_id" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <?php foreach ($drugItems as $di): ?>
                                <option value="<?= $di['id'] ?>"><?= htmlspecialchars($di['brand_name']) ?> (<?= htmlspecialchars($di['strength']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Batch Number <span class="text-rose-500">*</span></label>
                        <input type="text" name="batch_number" required placeholder="e.g. BT-CIP-9901" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Expiry Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="expiry_date" required class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Quantity <span class="text-rose-500">*</span></label>
                            <input type="number" name="quantity" required value="500" class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Cost Price</label>
                            <input type="number" step="0.01" name="unit_purchase_price" value="10.00" class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Sale Price <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" name="unit_sale_price" required value="15.00" class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono font-bold">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-lg shadow-sm">
                        Record Batch Stock
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 text-sm">Formulary Medicines Catalog</h3>
                <span class="text-xs text-slate-500 font-mono"><?= count($drugItems) ?> Medicines</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3">Brand Name</th>
                            <th class="px-4 py-3">Generic Molecule</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Dosage Form</th>
                            <th class="px-6 py-3 text-right">Strength</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($drugItems as $di): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-bold text-slate-900"><?= htmlspecialchars($di['brand_name']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($di['generic_name']) ?></td>
                            <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($di['category_name']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($di['dosage_form']) ?></td>
                            <td class="px-6 py-3 text-right font-mono font-semibold text-teal-700"><?= htmlspecialchars($di['strength']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 6: Lab Test Catalog -->
    <?php if ($activeTab === 'labs'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-medical-600"></i>
                <span>Add New Laboratory Test</span>
            </h3>
            <form action="index.php?action=lab_test_store" method="POST" class="space-y-3">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Test Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="test_name" required placeholder="e.g. Thyroid Profile (TSH, FT3, FT4)" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Test Code <span class="text-rose-500">*</span></label>
                    <input type="text" name="test_code" required placeholder="e.g. TFT-01" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 uppercase font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Category</label>
                    <select name="category_id" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                        <?php foreach ($labCategories as $lc): ?>
                            <option value="<?= $lc['id'] ?>"><?= htmlspecialchars($lc['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Specimen Required</label>
                    <select name="specimen_type" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                        <option value="Blood">Blood</option>
                        <option value="Serum">Serum</option>
                        <option value="Plasma">Plasma</option>
                        <option value="Urine">Urine</option>
                        <option value="Stool">Stool</option>
                        <option value="CSF">CSF</option>
                        <option value="Sputum">Sputum</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Turnaround Time (Hours)</label>
                    <input type="number" name="turnaround_hours" value="6" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Standard Price (PKR) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" name="standard_price" required value="1200.00" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono font-bold">
                </div>
                <button type="submit" class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-lg shadow-sm">
                    Add Test to Catalog
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 text-sm">Diagnostic Pathology Catalog</h3>
                <span class="text-xs text-slate-500 font-mono"><?= count($labTests) ?> Tests</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3">Code</th>
                            <th class="px-4 py-3">Test Name</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Specimen</th>
                            <th class="px-4 py-3">TAT</th>
                            <th class="px-6 py-3 text-right">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($labTests as $lt): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-mono font-bold text-purple-800"><?= htmlspecialchars($lt['test_code']) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars($lt['test_name']) ?></td>
                            <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($lt['category_name']) ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($lt['specimen_type']) ?></td>
                            <td class="px-4 py-3 font-mono text-slate-500"><?= $lt['turnaround_hours'] ?>h</td>
                            <td class="px-6 py-3 text-right font-mono font-bold text-medical-800"><?= format_currency($lt['standard_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
