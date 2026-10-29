<?php
/**
 * Hospital Management System (HMS)
 * Platform Developer - Multi-Hospital Directory & Lifecycle Management
 */

Auth::requireRole(['developer', 'platform_admin']);

require_once __DIR__ . '/../../core/Telemetry.php';

$search = clean_input($_GET['q'] ?? '');
$statusFilter = clean_input($_GET['status'] ?? '');
$sort = clean_input($_GET['sort'] ?? 'id_asc');

$hospitals = Telemetry::getHospitalDirectory($search, $statusFilter, $sort);
$summary = Telemetry::getPlatformSummary();
?>

<div class="space-y-6">

    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-950 to-indigo-950 p-6 rounded-2xl text-white shadow-xl border border-indigo-900/40">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                    <i class="fa-solid fa-server mr-1"></i> Multi-Hospital Operations
                </span>
                <span class="text-xs text-slate-400">Platform Developer Directory</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight">Hospital Facilities & Owner Directory</h1>
            <p class="text-sm text-slate-300 mt-1">Manage hospital tenant lifecycles, monitor activity status, and enforce account state.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('registerHospitalModal').classList.remove('hidden')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Register New Hospital</span>
            </button>
            <a href="index.php?page=developer_dashboard" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium border border-slate-700 transition-all">
                <i class="fa-solid fa-chart-pie mr-1"></i> Platform Overview
            </a>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="index.php" class="flex-1 flex flex-col sm:flex-row items-center gap-3">
            <input type="hidden" name="page" value="developer_hospitals">
            
            <div class="relative flex-1 w-full">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    name="q" 
                    value="<?= htmlspecialchars($search) ?>" 
                    placeholder="Search by hospital name, code, owner name, email or city..." 
                    class="w-full pl-9 pr-4 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                >
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <select name="status" class="px-3 py-2 text-xs border border-slate-300 rounded-lg outline-none bg-white">
                    <option value="">All Statuses (<?= $summary['total_hospitals'] ?>)</option>
                    <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active (<?= $summary['active_hospitals'] ?>)</option>
                    <option value="Suspended" <?= $statusFilter === 'Suspended' ? 'selected' : '' ?>>Suspended (<?= $summary['suspended_hospitals'] ?>)</option>
                    <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>

                <select name="sort" class="px-3 py-2 text-xs border border-slate-300 rounded-lg outline-none bg-white">
                    <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>ID (Ascending)</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                    <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="staff_desc" <?= $sort === 'staff_desc' ? 'selected' : '' ?>>Most Staff</option>
                </select>

                <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-xs font-semibold hover:bg-slate-800 transition-colors">
                    Filter
                </button>
            </div>
        </form>

        <span class="text-xs text-slate-500 font-medium whitespace-nowrap">
            Showing <?= count($hospitals) ?> hospital organization<?= count($hospitals) === 1 ? '' : 's' ?>
        </span>
    </div>

    <!-- Hospitals Table Directory -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                        <th class="py-3 px-4">Hospital Facility</th>
                        <th class="py-3 px-4">Owner & Contact</th>
                        <th class="py-3 px-4">Registration</th>
                        <th class="py-3 px-4">Staff Count</th>
                        <th class="py-3 px-4">Most Used Dept</th>
                        <th class="py-3 px-4">Usage Trend</th>
                        <th class="py-3 px-4 text-center">Account Status</th>
                        <th class="py-3 px-4 text-right">Lifecycle Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($hospitals)): ?>
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400">
                            No hospital organizations match the search criteria.
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($hospitals as $h): ?>
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-sm shadow-sm flex-shrink-0" style="background-color: <?= htmlspecialchars($h['theme_color'] ?? '#0d9488') ?>;">
                                    <?= strtoupper(substr($h['hospital_name'], 0, 2)) ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($h['hospital_name']) ?></h4>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="px-1.5 py-0.2 bg-slate-100 text-slate-600 rounded text-[10px] font-mono font-bold">
                                            <?= htmlspecialchars($h['hospital_code'] ?? 'HOSP') ?>
                                        </span>
                                        <span class="text-[11px] text-slate-400"><?= htmlspecialchars($h['city'] ?? 'City') ?>, <?= htmlspecialchars($h['country'] ?? 'Country') ?></span>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="py-3.5 px-4">
                            <div class="font-medium text-slate-800"><?= htmlspecialchars($h['owner_name'] ?? 'Not Assigned') ?></div>
                            <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($h['owner_email'] ?? '—') ?></div>
                            <?php if (!empty($h['owner_last_login'])): ?>
                            <div class="text-[10px] text-slate-400 mt-0.5">Login: <?= date('d M Y', strtotime($h['owner_last_login'])) ?></div>
                            <?php endif; ?>
                        </td>

                        <td class="py-3.5 px-4 whitespace-nowrap text-slate-600">
                            <div><?= date('d M, Y', strtotime($h['created_at'])) ?></div>
                            <span class="text-[10px] px-1.5 py-0.5 bg-indigo-50 text-indigo-700 font-medium rounded"><?= htmlspecialchars($h['subscription_plan'] ?? 'Enterprise') ?></span>
                        </td>

                        <td class="py-3.5 px-4 font-mono font-bold text-slate-700">
                            <?= number_format($h['staff_count'] ?? 0) ?> accounts
                        </td>

                        <td class="py-3.5 px-4 text-slate-700 font-medium">
                            <?= htmlspecialchars($h['most_used_department']) ?>
                        </td>

                        <td class="py-3.5 px-4">
                            <?php if ($h['usage_intensity'] === 'High'): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">High Activity</span>
                            <?php elseif ($h['usage_intensity'] === 'Moderate'): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">Moderate</span>
                            <?php elseif ($h['usage_intensity'] === 'Low'): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Low</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">Idle</span>
                            <?php endif; ?>
                        </td>

                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                            <?php if ($h['status'] === 'Active'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            <?php elseif ($h['status'] === 'Suspended'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Suspended
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                    <?= htmlspecialchars($h['status']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                            <button onclick="openStatusModal(<?= $h['id'] ?>, '<?= addslashes($h['hospital_name']) ?>', '<?= $h['status'] ?>')" class="px-2.5 py-1.5 border rounded-lg text-xs font-semibold transition-all <?= $h['status'] === 'Active' ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' ?>">
                                <i class="fa-solid <?= $h['status'] === 'Active' ? 'fa-ban' : 'fa-check' ?> mr-1"></i>
                                <?= $h['status'] === 'Active' ? 'Suspend' : 'Activate' ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Status Management Modal (With Mandatory Audit Reason) -->
<div id="statusModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-slate-200 overflow-hidden">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="font-bold text-sm" id="modalHospitalName">Update Hospital Lifecycle Status</h3>
            <button onclick="document.getElementById('statusModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=developer_hospital_status" class="p-5 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="hospital_id" id="statusHospitalId">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">New Lifecycle Status *</label>
                <select name="status" id="statusSelect" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <option value="Active">Active (Permit Staff & Clinical Operations)</option>
                    <option value="Suspended">Suspended (Halt Logins & Retain All Records)</option>
                    <option value="Inactive">Inactive (Decommissioned)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Status Change (Audit Log) *</label>
                <textarea name="reason" required rows="3" placeholder="e.g. Account subscription renewal completed / Payment pending / Client requested maintenance" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg"></textarea>
            </div>

            <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-[11px] text-amber-800 flex items-start gap-2">
                <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                <span>Suspending a hospital prevents staff from authenticating, but 100% of the hospital's patient charts, inventory, and invoices remain intact.</span>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-md">
                    Apply & Log Status
                </button>
            </div>
        </form>
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

<script>
function openStatusModal(hospitalId, hospitalName, currentStatus) {
    document.getElementById('statusHospitalId').value = hospitalId;
    document.getElementById('modalHospitalName').innerText = 'Update Status: ' + hospitalName;
    document.getElementById('statusSelect').value = currentStatus === 'Active' ? 'Suspended' : 'Active';
    document.getElementById('statusModal').classList.remove('hidden');
}
</script>
