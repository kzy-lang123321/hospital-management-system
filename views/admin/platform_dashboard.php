<?php
/**
 * Hospital Management System (HMS)
 * Multi-Hospital Platform Overview & Executive Control Dashboard
 */

Auth::requireRole(['platform_admin']);

$db = Database::getConnection();

// 1. Platform-Wide Aggregated Statistics (No private patient clinical details exposed)
$totalHospitals = (int)$db->query("SELECT COUNT(*) FROM `hospital_profiles`")->fetchColumn();
$activeHospitals = (int)$db->query("SELECT COUNT(*) FROM `hospital_profiles` WHERE `status` = 'Active'")->fetchColumn();
$suspendedHospitals = (int)$db->query("SELECT COUNT(*) FROM `hospital_profiles` WHERE `status` = 'Suspended'")->fetchColumn();

$totalStaff = (int)$db->query("SELECT COUNT(*) FROM `users` WHERE `status` = 'Active' AND `role_id` != 10")->fetchColumn();
$totalPatients = (int)$db->query("SELECT COUNT(*) FROM `patients`")->fetchColumn();
$totalEncounters = (int)$db->query("SELECT COUNT(*) FROM `encounters`")->fetchColumn();

// 2. Fetch All Hospitals
$hospitals = Tenant::allHospitals();

// 3. Platform Audit Logs
$recentPlatformLogs = $db->query("
    SELECT al.*, u.username, u.full_name, hp.hospital_name
    FROM `audit_logs` al
    LEFT JOIN `users` u ON al.user_id = u.id
    LEFT JOIN `hospital_profiles` hp ON al.hospital_id = hp.id
    ORDER BY al.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$currentTenantId = Tenant::id();
$currentTenantProfile = Tenant::profile();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-6 space-y-6">

    <!-- Platform Header Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-navy-900 to-indigo-950 text-white rounded-2xl p-6 shadow-xl border border-indigo-900/50 relative overflow-hidden">
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-indigo-400 text-xs font-bold tracking-wider uppercase mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Platform Super Administration & Cloud Architecture</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Multi-Hospital Platform Master Console</h1>
                <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl">
                    Centralized multi-tenant management for independent healthcare facilities. Monitor global capacity, provision new hospital organizations, and enforce compliance isolation.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <a href="index.php?page=platform_hospitals" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-square-plus text-sm"></i>
                    <span>Register New Hospital</span>
                </a>
                <?php if (!empty($_SESSION['platform_active_hospital_id'])): ?>
                <a href="index.php?action=clear_platform_context" class="px-3.5 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <i class="fa-solid fa-arrow-rotate-left"></i>
                    <span>Exit Hospital Focus (<?= htmlspecialchars($currentTenantProfile['hospital_name']) ?>)</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <i class="fa-solid fa-network-wired absolute right-4 -bottom-6 text-white/5 text-9xl pointer-events-none"></i>
    </div>

    <!-- Active Focus Context Indicator -->
    <?php if (!empty($_SESSION['platform_active_hospital_id'])): ?>
    <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-amber-300 text-xs">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center text-amber-400 font-bold">
                <i class="fa-solid fa-building-user"></i>
            </div>
            <div>
                <span class="font-bold text-amber-200">Active Hospital Focus Mode:</span>
                <span>You are currently managing <strong><?= htmlspecialchars($currentTenantProfile['hospital_name']) ?></strong> (Code: <?= htmlspecialchars($currentTenantProfile['hospital_code']) ?>). All clinical and operational workspaces are currently filtered to this hospital.</span>
            </div>
        </div>
        <a href="index.php?action=clear_platform_context" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-lg text-xs self-start sm:self-auto transition-colors">
            Reset to Global Platform
        </a>
    </div>
    <?php endif; ?>

    <!-- Platform KPI Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl flex-shrink-0">
                <i class="fa-solid fa-hospital"></i>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Hospitals Enrolled</div>
                <div class="text-2xl font-bold text-slate-900 mt-0.5"><?= number_format($totalHospitals) ?></div>
                <div class="text-[11px] text-emerald-600 font-medium flex items-center gap-1 mt-0.5">
                    <i class="fa-solid fa-circle-check text-[10px]"></i>
                    <span><?= $activeHospitals ?> Active, <?= $suspendedHospitals ?> Suspended</span>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl flex-shrink-0">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Clinical & Staff Users</div>
                <div class="text-2xl font-bold text-slate-900 mt-0.5"><?= number_format($totalStaff) ?></div>
                <div class="text-[11px] text-slate-500 mt-0.5">Across all facilities</div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl flex-shrink-0">
                <i class="fa-solid fa-hospital-user"></i>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Aggregated Patients</div>
                <div class="text-2xl font-bold text-slate-900 mt-0.5"><?= number_format($totalPatients) ?></div>
                <div class="text-[11px] text-teal-600 mt-0.5">Total unique patient files</div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl flex-shrink-0">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Clinical Encounters</div>
                <div class="text-2xl font-bold text-slate-900 mt-0.5"><?= number_format($totalEncounters) ?></div>
                <div class="text-[11px] text-purple-600 mt-0.5">OPD & IPD Lifetime Visits</div>
            </div>
        </div>
    </div>

    <!-- Hospitals Directory Card Matrix -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-800">Licensed Healthcare Facilities</h2>
                <p class="text-xs text-slate-500">Each hospital operates in isolated multi-tenant data space with custom branding and staff roles.</p>
            </div>
            <a href="index.php?page=platform_hospitals" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1.5 transition-colors">
                <span>View Full Directory</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="divide-y divide-slate-100">
            <?php foreach ($hospitals as $h): 
                $isCurrentFocus = ($h['id'] == Tenant::id());
                $themeColor = htmlspecialchars($h['theme_color'] ?? '#0d9488');
            ?>
            <div class="p-5 hover:bg-slate-50/80 transition-colors flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-start gap-4">
                    <!-- Brand Icon with Hospital's Custom Theme Color -->
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white text-xl font-bold flex-shrink-0 shadow-md" style="background-color: <?= $themeColor ?>;">
                        <i class="fa-solid fa-house-chimney-medical"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-slate-900 text-base leading-tight"><?= htmlspecialchars($h['hospital_name']) ?></h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200"><?= htmlspecialchars($h['hospital_code'] ?? 'HOSP') ?></span>
                            <?php if ($h['status'] === 'Active'): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                            <?php else: ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">Suspended</span>
                            <?php endif; ?>
                            <?php if ($isCurrentFocus): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">Current Focus</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($h['tagline'] ?? '') ?></p>
                        
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-slate-600">
                            <span><i class="fa-solid fa-location-dot text-slate-400 mr-1"></i> <?= htmlspecialchars($h['city']) ?>, <?= htmlspecialchars($h['country']) ?></span>
                            <span><i class="fa-solid fa-phone text-slate-400 mr-1"></i> <?= htmlspecialchars($h['phone'] ?: 'N/A') ?></span>
                            <span><i class="fa-solid fa-user-shield text-slate-400 mr-1"></i> Owner: <strong><?= htmlspecialchars($h['owner_name'] ?? 'System Admin') ?></strong></span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 self-end lg:self-center">
                    <!-- Statistics Badges -->
                    <div class="flex items-center gap-3 text-xs bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl">
                        <div class="text-center">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">Staff</span>
                            <span class="font-bold text-slate-800"><?= $h['staff_count'] ?></span>
                        </div>
                        <div class="h-6 w-px bg-slate-200"></div>
                        <div class="text-center">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">Patients</span>
                            <span class="font-bold text-slate-800"><?= $h['patient_count'] ?></span>
                        </div>
                        <div class="h-6 w-px bg-slate-200"></div>
                        <div class="text-center">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">Plan</span>
                            <span class="font-bold text-indigo-600"><?= htmlspecialchars($h['subscription_plan'] ?? 'Enterprise') ?></span>
                        </div>
                    </div>

                    <!-- Action Controls -->
                    <form action="index.php?action=switch_hospital" method="POST" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="hospital_id" value="<?= $h['id'] ?>">
                        <button type="submit" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl border border-slate-200 transition-colors flex items-center gap-1.5" title="Switch platform view to this hospital">
                            <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                            <span><?= $isCurrentFocus ? 'Refresh View' : 'Focus Hospital' ?></span>
                        </button>
                    </form>

                    <form action="index.php?action=toggle_hospital_status" method="POST" class="inline" onsubmit="return confirm('Change operational status for <?= htmlspecialchars($h['hospital_name']) ?>?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="hospital_id" value="<?= $h['id'] ?>">
                        <?php if ($h['status'] === 'Active'): ?>
                        <button type="submit" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs rounded-xl border border-rose-200 transition-colors" title="Suspend hospital">
                            <i class="fa-solid fa-pause mr-1"></i> Suspend
                        </button>
                        <?php else: ?>
                        <button type="submit" class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold text-xs rounded-xl border border-emerald-200 transition-colors" title="Reactivate hospital">
                            <i class="fa-solid fa-play mr-1"></i> Activate
                        </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Platform Audit Logs & Security Trails -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-200">
            <h2 class="text-base font-bold text-slate-800">Global Platform Activity Trail</h2>
            <p class="text-xs text-slate-500">Real-time immutable security logs recording user logins, tenancy changes, and system modifications.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Timestamp</th>
                        <th class="px-4 py-3">Facility / Tenant</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Event Type</th>
                        <th class="px-4 py-3">Entity</th>
                        <th class="px-4 py-3">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentPlatformLogs)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No recent platform events recorded.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recentPlatformLogs as $log): ?>
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-4 py-3 font-mono text-slate-500"><?= format_datetime($log['created_at']) ?></td>
                        <td class="px-4 py-3 font-semibold text-slate-800"><?= htmlspecialchars($log['hospital_name'] ?? 'Platform Core') ?></td>
                        <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'System') ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded font-mono font-bold text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200">
                                <?= htmlspecialchars($log['event_type']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-slate-600"><?= htmlspecialchars($log['table_name']) ?> (ID: <?= htmlspecialchars($log['record_id']) ?>)</td>
                        <td class="px-4 py-3 font-mono text-slate-500"><?= htmlspecialchars($log['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
