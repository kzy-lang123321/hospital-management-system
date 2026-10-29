<?php
/**
 * Hospital Management System (HMS) - Patient Master Directory
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

$search = clean_input($_GET['search'] ?? '');
$genderFilter = clean_input($_GET['gender'] ?? '');
$bloodFilter = clean_input($_GET['blood_group'] ?? '');

$hId = Tenant::id();
$query = "SELECT p.*, 
                 (SELECT COUNT(*) FROM `encounters` WHERE `patient_id` = p.id) AS total_encounters,
                 (SELECT `allergen_name` FROM `patient_allergies` WHERE `patient_id` = p.id LIMIT 1) AS sample_allergy
          FROM `patients` p 
          WHERE p.hospital_id = ?";
$params = [$hId];

if ($search !== '') {
    $query .= " AND (p.mrn LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone_primary LIKE ? OR p.national_id LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($genderFilter !== '') {
    $query .= " AND p.gender = ?";
    $params[] = $genderFilter;
}

if ($bloodFilter !== '') {
    $query .= " AND p.blood_group = ?";
    $params[] = $bloodFilter;
}

$query .= " ORDER BY p.id DESC LIMIT 50";
$stmt = $db->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Patient Directory (Master Patient Index)</h2>
            <p class="text-xs text-slate-500">Centralized electronic medical records anchored to unique MRN</p>
        </div>
        <a href="index.php?page=patient_create" class="px-4 py-2 bg-medical-600 hover:bg-medical-700 text-white font-semibold text-xs rounded-lg shadow-sm transition-colors flex items-center gap-2 self-start sm:self-auto">
            <i class="fa-solid fa-user-plus"></i>
            <span>Register New Patient</span>
        </a>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <form action="index.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <input type="hidden" name="page" value="patients">
            
            <div class="md:col-span-2 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    name="search" 
                    value="<?= htmlspecialchars($search) ?>" 
                    placeholder="Search by MRN, Name, Phone, National ID (CNIC)..."
                    class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 focus:border-medical-500 focus:ring-1 focus:ring-medical-500 rounded-lg outline-none"
                >
            </div>

            <div>
                <select name="gender" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="">All Genders</option>
                    <option value="Male" <?= $genderFilter === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $genderFilter === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <select name="blood_group" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    <option value="">All Blood Groups</option>
                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                        <option value="<?= $bg ?>" <?= $bloodFilter === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-semibold">Filter</button>
            </div>
        </form>
    </div>

    <!-- Patients Master Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5">MRN / Patient Name</th>
                        <th class="px-4 py-3.5">Demographics</th>
                        <th class="px-4 py-3.5">Primary Contact</th>
                        <th class="px-4 py-3.5">National ID</th>
                        <th class="px-4 py-3.5">Allergies / Warnings</th>
                        <th class="px-4 py-3.5">Visits</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($patients)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <i class="fa-solid fa-user-slash text-3xl mb-2 text-slate-300"></i>
                                <p class="text-sm font-medium">No patient records found matching query.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($patients as $p): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-3.5">
                                <a href="index.php?page=patient_profile&id=<?= $p['id'] ?>" class="font-bold text-slate-800 hover:text-medical-600 transition-colors">
                                    <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                                </a>
                                <div class="font-mono text-[11px] text-medical-700 font-semibold mt-0.5">
                                    <?= htmlspecialchars($p['mrn']) ?>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="text-slate-700 font-medium"><?= htmlspecialchars($p['gender']) ?>, <?= $p['age'] ?> yrs</div>
                                <div class="text-[10px] text-slate-400">DOB: <?= format_date($p['dob']) ?></div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-mono text-slate-800"><?= htmlspecialchars($p['phone_primary']) ?></div>
                                <div class="text-[10px] text-slate-500 truncate max-w-xs"><?= htmlspecialchars($p['city']) ?></div>
                            </td>
                            <td class="px-4 py-3.5 font-mono text-slate-600">
                                <?= htmlspecialchars($p['national_id'] ?? '—') ?>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    <i class="fa-solid fa-droplet mr-1"></i> <?= htmlspecialchars($p['blood_group']) ?>
                                </span>
                                <?php if ($p['sample_allergy']): ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-amber-50 text-amber-700 border border-amber-200 ml-1" title="Allergy recorded">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= htmlspecialchars($p['sample_allergy']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-full font-bold text-[10px]">
                                    <?= $p['total_encounters'] ?> Encounters
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="index.php?page=patient_profile&id=<?= $p['id'] ?>" class="px-2.5 py-1 bg-medical-50 text-medical-700 hover:bg-medical-100 rounded text-xs font-semibold transition-colors flex items-center gap-1">
                                        <i class="fa-solid fa-folder-open text-[10px]"></i>
                                        <span>File 360°</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
