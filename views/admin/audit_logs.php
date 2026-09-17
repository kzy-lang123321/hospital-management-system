<?php
/**
 * Hospital Management System (HMS) - Immutable Audit Logs
 */
require_once __DIR__ . '/../../config/database.php';
Auth::requireRole(['super_admin', 'hospital_admin']);

$db = Database::getConnection();

$logs = $db->query("
    SELECT a.*, u.full_name, u.username, r.role_name
    FROM `audit_logs` a
    LEFT JOIN `users` u ON a.user_id = u.id
    LEFT JOIN `roles` r ON u.role_id = r.id
    ORDER BY a.id DESC
    LIMIT 100
")->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Security & Clinical Audit Trail</h2>
            <p class="text-xs text-slate-500">Immutable forensic recording of user logins, record mutations, and data access</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold">
                <i class="fa-solid fa-shield-halved mr-1"></i> Audit Logging Active
            </span>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-sm">Event Log (Latest 100 Entries)</h3>
            <span class="text-xs text-slate-500 font-mono"><?= count($logs) ?> Recorded</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5">Timestamp</th>
                        <th class="px-4 py-3.5">User / Role</th>
                        <th class="px-4 py-3.5">Event Type</th>
                        <th class="px-4 py-3.5">Target Entity</th>
                        <th class="px-4 py-3.5">Record ID</th>
                        <th class="px-4 py-3.5">IP Address</th>
                        <th class="px-6 py-3.5">Client Device</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="px-6 py-8 text-center text-slate-400">No audit records logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                        <tr class="hover:bg-slate-50 font-mono text-[11px]">
                            <td class="px-6 py-3.5 text-slate-600 font-semibold"><?= format_datetime($l['created_at']) ?></td>
                            <td class="px-4 py-3.5">
                                <span class="font-sans font-bold text-slate-900"><?= htmlspecialchars($l['full_name'] ?? 'System') ?></span>
                                <span class="text-[10px] text-slate-400 block font-sans"><?= htmlspecialchars($l['role_name'] ?? 'Anonymous') ?></span>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= in_array($l['event_type'], ['LOGIN', 'LOGOUT']) ? 'bg-sky-100 text-sky-800' : 'bg-purple-100 text-purple-800' ?>">
                                    <?= htmlspecialchars($l['event_type']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-slate-700"><?= htmlspecialchars($l['table_name']) ?></td>
                            <td class="px-4 py-3.5 text-medical-800 font-bold"><?= htmlspecialchars($l['record_id'] ?? '—') ?></td>
                            <td class="px-4 py-3.5 text-slate-500"><?= htmlspecialchars($l['ip_address']) ?></td>
                            <td class="px-6 py-3.5 text-slate-400 truncate max-w-xs" title="<?= htmlspecialchars($l['user_agent'] ?? '') ?>">
                                <?= htmlspecialchars(substr($l['user_agent'] ?? '', 0, 45)) ?>...
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
