<?php
/**
 * Hospital Management System (HMS) - Staff & User Accounts
 */
require_once __DIR__ . '/../../config/database.php';
Auth::requireRole(['super_admin', 'hospital_admin']);

$db = Database::getConnection();

$users = $db->query("
    SELECT u.*, r.role_name, d.department_name
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.id
    LEFT JOIN `departments` d ON u.department_id = d.id
    ORDER BY u.id ASC
")->fetchAll();

$roles = $db->query("SELECT * FROM `roles` ORDER BY id ASC")->fetchAll();
$departments = $db->query("SELECT * FROM `departments` ORDER BY department_name ASC")->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Hospital Staff & System Accounts</h2>
            <p class="text-xs text-slate-500">Role-Based Access Control (RBAC) credentials and departmental assignments</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="index.php?page=print_credentials" target="_blank" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg border border-slate-200 shadow-xs transition-colors flex items-center gap-2">
                <i class="fa-solid fa-print text-slate-500"></i>
                <span>Print Credentials Sheet</span>
            </a>
            <a href="index.php?action=download_credentials_pdf" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs rounded-lg shadow-xs transition-colors flex items-center gap-2">
                <i class="fa-solid fa-file-pdf text-rose-400"></i>
                <span>Download Credentials PDF</span>
            </a>
            <button onclick="document.getElementById('userModal').classList.remove('hidden')" class="px-4 py-2 bg-medical-600 hover:bg-medical-700 text-white font-semibold text-xs rounded-lg shadow-sm transition-colors flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i>
                <span>Add Staff Account</span>
            </button>
        </div>
    </div>

<?php
$roleBadges = [
    'super_admin' => 'bg-rose-50 text-rose-700 border-rose-200',
    'hospital_admin' => 'bg-purple-50 text-purple-700 border-purple-200',
    'doctor' => 'bg-blue-50 text-blue-700 border-blue-200',
    'nurse' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'pharmacist' => 'bg-amber-50 text-amber-700 border-amber-200',
    'lab_technician' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
    'radiologist' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'cashier' => 'bg-teal-50 text-teal-700 border-teal-200',
    'receptionist' => 'bg-pink-50 text-pink-700 border-pink-200',
];
?>

    <!-- Quick Credentials & Role Reference Callout -->
    <div class="bg-gradient-to-r from-medical-900 to-slate-900 text-white rounded-2xl p-4 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-medical-500/30 text-medical-200 border border-medical-400/30 font-mono">RBAC SECURITY MATRIX</span>
                <span class="text-xs text-slate-300">Multi-User Account Governance Active</span>
            </div>
            <p class="text-xs text-slate-300">
                Hospital department management accounts are active with name-based random passwords. Administrators can add new staff or reset passwords anytime using the <span class="text-amber-300 font-semibold"><i class="fa-solid fa-key"></i> Reset Key</span> action.
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap text-[11px]">
            <span class="px-2 py-1 bg-white/10 rounded-lg text-slate-200 font-mono">🧪 Lab: @lab.usman</span>
            <span class="px-2 py-1 bg-white/10 rounded-lg text-slate-200 font-mono">💊 Pharm: @pharm.kamran</span>
            <span class="px-2 py-1 bg-white/10 rounded-lg text-slate-200 font-mono">🩻 Radio: @radio.farooq</span>
            <span class="px-2 py-1 bg-white/10 rounded-lg text-slate-200 font-mono">🩺 OPD: @dr.tariq</span>
            <span class="px-2 py-1 bg-white/10 rounded-lg text-slate-200 font-mono">🔪 OT: @dr.imran</span>
            <span class="px-2 py-1 bg-white/10 rounded-lg text-slate-200 font-mono">🏥 Ward: @nurse.fatima</span>
            <span class="px-2 py-1 bg-white/10 rounded-lg text-slate-200 font-mono">💵 Cashier: @cashier.hassan</span>
        </div>
    </div>

    <!-- Staff Directory Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-sm">Active Staff Directory</h3>
            <span class="text-xs text-slate-500 font-mono"><?= count($users) ?> Accounts</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5">User / Full Name</th>
                        <th class="px-4 py-3.5">Assigned Role</th>
                        <th class="px-4 py-3.5">Department</th>
                        <th class="px-4 py-3.5">Designation & Qualifications</th>
                        <th class="px-4 py-3.5">Consultation Fee</th>
                        <th class="px-4 py-3.5">Contact</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-4 py-3.5 text-right">Last Login</th>
                        <th class="px-6 py-3.5 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): 
                        $badgeClass = $roleBadges[$u['slug'] ?? ''] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                        $isActive = ($u['status'] === 'Active');
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-3.5">
                            <div class="font-bold text-slate-900"><?= htmlspecialchars($u['full_name']) ?></div>
                            <div class="text-[11px] font-mono text-slate-400">@<?= htmlspecialchars($u['username']) ?></div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $badgeClass ?>">
                                <?= htmlspecialchars($u['role_name']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-slate-700 font-medium"><?= htmlspecialchars($u['department_name'] ?? 'General') ?></td>
                        <td class="px-4 py-3.5">
                            <div class="text-slate-800 font-medium"><?= htmlspecialchars($u['designation'] ?? '—') ?></div>
                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($u['qualification'] ?? '') ?></div>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-800 font-semibold">
                            <?= $u['consultation_fee'] > 0 ? format_currency($u['consultation_fee']) : '—' ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-slate-700"><?= htmlspecialchars($u['email']) ?></div>
                            <div class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($u['phone'] ?? '') ?></div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                                <?= htmlspecialchars($u['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right text-slate-400 font-mono text-[11px]">
                            <?= !empty($u['last_login_at']) ? format_datetime($u['last_login_at']) : 'Never' ?>
                        </td>
                        <td class="px-6 py-3.5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                <?php if ((int)$u['id'] !== (int)Auth::id()): ?>
                                <form action="index.php?action=user_status_toggle" method="POST" class="inline" onsubmit="return confirm('Change status for account @<?= htmlspecialchars(addslashes($u['username'])) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="px-2 py-1 rounded text-[10px] font-bold border transition-colors <?= $isActive ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' ?>" title="Toggle Active / Inactive">
                                        <?= $isActive ? '<i class="fa-solid fa-user-slash"></i> Deactivate' : '<i class="fa-solid fa-user-check"></i> Activate' ?>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <button type="button" onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')" class="px-2 py-1 rounded text-[10px] font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200 transition-colors" title="Reset Password">
                                    <i class="fa-solid fa-key"></i> Reset Key
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Add User Modal -->
<div id="userModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-800 text-base">Add New Staff / Doctor Account</h3>
            <button onclick="document.getElementById('userModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="index.php?action=user_store" method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                    <input type="text" name="username" required placeholder="e.g. dr.zahid" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Password <span class="text-rose-500">*</span></label>
                    <input type="password" name="password" required value="Admin@123" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name <span class="text-rose-500">*</span></label>
                <input type="text" name="full_name" required placeholder="e.g. Dr. Zahid Farooq" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Role <span class="text-rose-500">*</span></label>
                    <select name="role_id" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-semibold">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Department</label>
                    <select name="department_id" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                        <option value="">None / General</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Designation</label>
                    <input type="text" name="designation" placeholder="e.g. Consultant Neurosurgeon" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Consultation Fee (PKR)</label>
                    <input type="number" step="0.01" name="consultation_fee" value="0.00" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" required placeholder="staff@hayatmedicare.org" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number</label>
                    <input type="text" name="phone" placeholder="+92 3XX XXXXXXX" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('userModal').classList.add('hidden')" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-medical-600 hover:bg-medical-700 text-white rounded-lg text-xs font-bold shadow-sm">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-key text-medical-600"></i> Reset Staff Password
            </h3>
            <button type="button" onclick="document.getElementById('resetPasswordModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="index.php?action=user_reset_password" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" id="reset_user_id" value="">

            <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                <p class="text-[11px] text-slate-500 uppercase tracking-wider font-bold">Target Account</p>
                <p class="text-sm font-bold text-slate-800" id="reset_fullname_badge">-</p>
                <p class="text-xs text-medical-600 font-mono font-semibold" id="reset_username_badge">-</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">New Password <span class="text-rose-500">*</span></label>
                <input type="password" name="new_password" id="reset_new_password" required minlength="6" placeholder="Enter new password (min 6 chars)" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500 font-mono">
                <p class="text-[10px] text-slate-400 mt-1">Minimum 6 characters. Recommended: strong letters + numbers.</p>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('resetPasswordModal').classList.add('hidden')" class="px-3 py-1.5 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-sm">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(userId, username, fullName) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username_badge').textContent = '@' + username;
    document.getElementById('reset_fullname_badge').textContent = fullName;
    document.getElementById('reset_new_password').value = '';
    document.getElementById('resetPasswordModal').classList.remove('hidden');
}
</script>

