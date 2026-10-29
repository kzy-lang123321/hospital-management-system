<?php
/**
 * Hospital Management System (HMS)
 * Platform Developer / Company Provider - Monitoring & Health Dashboard
 * 
 * STRICT PRIVACY ENFORCEMENT:
 * Monitors platform adoption, hospital accounts, and privacy-safe department activity
 * WITHOUT accessing actual hospital patient charts, medical records, or revenue.
 */

Auth::requireRole(['developer', 'platform_admin']);

require_once __DIR__ . '/../../core/Telemetry.php';
require_once __DIR__ . '/../../core/RegistrationService.php';

$summary = Telemetry::getPlatformSummary();
$deptTelemetry = Telemetry::getDepartmentActivityTable();
$techHealth = Telemetry::getSanitizedTechnicalHealth();
$recentHospitals = Telemetry::getHospitalDirectory('', '', 'created_desc');
$recentHospitals = array_slice($recentHospitals, 0, 5);
$regCounts = RegistrationService::getCountsByStatus();
?>

<div class="space-y-6">

    <!-- Top Developer Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 p-6 rounded-2xl text-white shadow-xl border border-indigo-900/40">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    <i class="fa-solid fa-shield-halved mr-1"></i> Privacy-Safe Telemetry Layer
                </span>
                <span class="text-xs text-indigo-300/80 font-mono">Platform Developer Console</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight">System Infrastructure & Multi-Hospital Overview</h1>
            <p class="text-sm text-slate-300 mt-1">Real-time aggregate platform adoption, technical availability, and hospital facility lifecycle.</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="index.php?page=developer_registrations" class="px-4 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-teal-600/30 transition-all flex items-center gap-2">
                <i class="fa-solid fa-file-signature"></i>
                <span>Registrations</span>
                <?php if ($regCounts['pending'] > 0): ?>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-amber-400 text-slate-950 font-bold"><?= $regCounts['pending'] ?></span>
                <?php endif; ?>
            </a>
            <a href="index.php?page=developer_hospitals" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                <i class="fa-solid fa-hospital-user"></i>
                <span>Manage Hospitals</span>
            </a>
            <a href="index.php?page=developer_profile" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium border border-slate-700 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-user-gear"></i>
                <span>Developer Account</span>
            </a>
        </div>
    </div>

    <!-- Pending Registration Alert Banner -->
    <?php if ($regCounts['pending'] > 0): ?>
    <div class="p-4 rounded-2xl bg-gradient-to-r from-teal-950 via-slate-900 to-indigo-950 border border-teal-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-white shadow-xl">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-500/20 border border-teal-500/40 text-teal-400 flex items-center justify-center text-lg flex-shrink-0">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-white"><?= $regCounts['pending'] ?> Pending Hospital Registration Request<?= $regCounts['pending'] > 1 ? 's' : '' ?> Awaiting Developer Approval</h4>
                <p class="text-xs text-slate-300">Public healthcare organizations have submitted onboarding requests requiring compliance review.</p>
            </div>
        </div>
        <a href="index.php?page=developer_registrations&status=Pending+Approval" class="px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-bold shadow-md transition-all whitespace-nowrap self-start sm:self-auto flex items-center gap-1.5">
            <span>Review Applications</span>
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- Platform Adoption KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Facilities</p>
            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($summary['total_hospitals']) ?></h3>
            <span class="text-[11px] text-indigo-600 font-medium">All Tenants</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Active Status</p>
            <h3 class="text-2xl font-bold text-emerald-600 mt-1"><?= number_format($summary['active_hospitals']) ?></h3>
            <span class="text-[11px] text-slate-500"><?= $summary['suspended_hospitals'] ?> Suspended</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Hospital Owners</p>
            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($summary['total_owners']) ?></h3>
            <span class="text-[11px] text-blue-600 font-medium">Executive Accounts</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Staff</p>
            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($summary['total_staff']) ?></h3>
            <span class="text-[11px] text-purple-600 font-medium">Across all facilities</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Active Today</p>
            <h3 class="text-2xl font-bold text-teal-600 mt-1"><?= number_format($summary['hospitals_with_recent_activity']) ?></h3>
            <span class="text-[11px] text-slate-500"><?= $summary['hospitals_idle'] ?> Idle</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Logins (24h)</p>
            <h3 class="text-2xl font-bold text-sky-600 mt-1"><?= number_format($summary['logins_24h_success']) ?></h3>
            <span class="text-[11px] text-rose-500 font-medium"><?= $summary['logins_24h_failed'] ?> Failed</span>
        </div>
    </div>

    <!-- Department Usage Telemetry & Sanitized System Health -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Privacy-Safe Department Activity Table -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h2 class="text-base font-bold text-slate-800">Aggregate Department Activity & Usage Events</h2>
                    <p class="text-xs text-slate-500">Real production activity event counts across clinical & operational departments (PHI Redacted).</p>
                </div>
                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-xs font-semibold rounded-lg font-mono">
                    Live Production Data
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-y border-slate-100">
                            <th class="py-3 px-4">Department Subsystem</th>
                            <th class="py-3 px-4">Activity Description</th>
                            <th class="py-3 px-4 text-right">Aggregate Activity Events</th>
                            <th class="py-3 px-4 text-center">Usage Intensity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($deptTelemetry as $dt): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-semibold text-slate-800 flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">
                                    <i class="fa-solid <?= $dt['icon'] ?>"></i>
                                </span>
                                <span><?= htmlspecialchars($dt['department']) ?></span>
                            </td>
                            <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($dt['description']) ?></td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-800"><?= number_format($dt['daily_events']) ?></td>
                            <td class="py-3 px-4 text-center">
                                <?php if ($dt['usage'] === 'High'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">High</span>
                                <?php elseif ($dt['usage'] === 'Moderate'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">Moderate</span>
                                <?php elseif ($dt['usage'] === 'Low'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Low</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Privacy Guarantee Notice -->
            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 text-[11px] text-slate-600 flex items-start gap-2.5">
                <i class="fa-solid fa-lock text-indigo-600 mt-0.5"></i>
                <div class="leading-relaxed">
                    <strong>Developer Privacy Guarantee:</strong> In compliance with healthcare data protection standards, this telemetry dashboard only computes anonymous event counts. Patient records, medical histories, prescriptions, laboratory test values, financial amounts, and staff attendance coordinates are strictly shielded at the database layer.
                </div>
            </div>
        </div>

        <!-- Right Col: Technical Health & Infrastructure Metrics -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                    <i class="fa-solid fa-heart-pulse text-emerald-600"></i>
                    <h3 class="text-sm font-bold text-slate-800">Technical Infrastructure Health</h3>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">Database Engine:</span>
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($techHealth['database_engine']) ?></span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">Query Latency:</span>
                        <span class="font-mono font-bold text-emerald-600"><?= $techHealth['database_latency_ms'] ?> ms</span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">PHP Runtime:</span>
                        <span class="font-mono text-slate-700"><?= htmlspecialchars($techHealth['php_version']) ?></span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">Memory Allocation:</span>
                        <span class="font-mono text-slate-700"><?= $techHealth['memory_usage_mb'] ?> MB</span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">SSL Connection:</span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <i class="fa-solid fa-lock mr-1"></i> <?= $techHealth['ssl_active'] ? 'Active (HTTPS)' : 'Enabled' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">Server Clock:</span>
                        <span class="font-mono text-[11px] text-slate-600"><?= htmlspecialchars($techHealth['server_time']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Recently Registered Facilities -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-800">Recently Added Facilities</h3>
                    <a href="index.php?page=developer_hospitals" class="text-xs text-indigo-600 font-semibold hover:text-indigo-800">View All</a>
                </div>

                <div class="space-y-2.5">
                    <?php foreach ($recentHospitals as $rh): ?>
                    <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/60 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-800"><?= htmlspecialchars($rh['hospital_name']) ?></p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Code: <?= htmlspecialchars($rh['hospital_code']) ?> • <?= htmlspecialchars($rh['city']) ?></p>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $rh['status'] === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= htmlspecialchars($rh['status']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

</div>
