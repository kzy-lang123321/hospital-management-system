<?php
/**
 * Hospital Management System (HMS)
 * Platform Developer / Company Provider - Account & Security Profile
 */

Auth::requireRole(['developer', 'platform_admin']);

$user = Auth::user();
$db = Database::getInstance();

// Fetch latest developer user record
$stmt = $db->prepare("SELECT u.*, r.role_name, r.slug as role_slug 
                      FROM users u 
                      JOIN roles r ON u.role_id = r.id 
                      WHERE u.id = ?");
$stmt->execute([$user['id']]);
$devUser = $stmt->fetch(PDO::FETCH_ASSOC);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="max-w-6xl mx-auto space-y-6">

    <!-- Top Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 p-6 rounded-2xl text-white shadow-xl border border-indigo-900/40">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                    <i class="fa-solid fa-key mr-1"></i> Root Provider Account
                </span>
                <span class="text-xs text-indigo-300/80 font-mono">Platform Authentication & Security</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight">Developer Profile & Credentials</h1>
            <p class="text-sm text-slate-300 mt-1">Manage platform developer login credentials, security credentials, and identity credentials.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="index.php?page=developer_dashboard" class="px-4 py-2 bg-slate-800/80 hover:bg-slate-700 text-white rounded-xl text-xs font-medium border border-slate-700 transition-all flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Flash Alerts -->
    <?php if ($flashSuccess): ?>
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
            <span class="text-sm font-medium"><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-500 text-lg"></i>
            <span class="text-sm font-medium"><?= htmlspecialchars($flashError) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left: Account Summary Card -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <div class="text-center">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center mx-auto text-3xl font-bold shadow-lg shadow-indigo-600/30 mb-3">
                        <i class="fa-solid fa-code"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($devUser['full_name'] ?: 'Platform Developer') ?></h3>
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium mt-0.5"><?= htmlspecialchars($devUser['email']) ?></p>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 mt-3 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Platform Developer / Provider
                    </span>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-3 text-xs">
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-500 dark:text-slate-400">Username</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-white">@<?= htmlspecialchars($devUser['username']) ?></span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-500 dark:text-slate-400">Account Status</span>
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <i class="fa-solid fa-circle-check text-[10px]"></i> <?= htmlspecialchars($devUser['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-500 dark:text-slate-400">Hospital Scope</span>
                        <span class="text-slate-700 dark:text-slate-300 font-medium">Platform-Wide (Root)</span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-500 dark:text-slate-400">Created At</span>
                        <span class="text-slate-700 dark:text-slate-300 font-mono"><?= date('M d, Y', strtotime($devUser['created_at'] ?? 'now')) ?></span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-500 dark:text-slate-400">Current Session IP</span>
                        <span class="text-slate-700 dark:text-slate-300 font-mono"><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?></span>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 space-y-2">
                    <div class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white">
                        <i class="fa-solid fa-shield-halved text-indigo-500"></i>
                        <span>Privacy-Shield Boundary</span>
                    </div>
                    <p class="text-[11px] leading-relaxed">
                        In accordance with health privacy compliance standards, the Platform Developer role has system provisioning rights but is physically prohibited by server-side authorization from opening private patient health records (PHI) and clinical notes.
                    </p>
                </div>
            </div>
        </div>

        <!-- Right: Forms -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Profile Info Form -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-user-pen"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Account Information</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Update your developer display name, username, and primary notification email.</p>
                    </div>
                </div>

                <form method="POST" action="index.php?action=developer_update_profile" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                Full Name / Company Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="full_name" required 
                                   value="<?= htmlspecialchars($devUser['full_name'] ?? 'Platform Developer') ?>" 
                                   class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:text-white transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                Username <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 text-slate-400 font-mono text-sm">@</span>
                                <input type="text" name="username" required 
                                       value="<?= htmlspecialchars($devUser['username']) ?>" 
                                       class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:text-white transition-all">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Developer Email Address <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" name="email" required 
                               value="<?= htmlspecialchars($devUser['email']) ?>" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:text-white transition-all">
                        <p class="text-[11px] text-slate-400 mt-1">This email is used for platform security alerts, password resets, and hospital provider notifications.</p>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/30 transition-all flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save Profile Changes</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Change Form -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Change Master Password</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Ensure your account uses a strong, unique password to safeguard multi-hospital access.</p>
                    </div>
                </div>

                <form method="POST" action="index.php?action=developer_change_password" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Current Password <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="current_password" required 
                               placeholder="Enter your current developer password"
                               class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:text-white transition-all">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                New Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" name="new_password" required minlength="8"
                                   placeholder="At least 8 characters"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:text-white transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                Confirm New Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" name="confirm_password" required minlength="8"
                                   placeholder="Re-type new password"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:text-white transition-all">
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 space-y-1">
                        <p class="font-semibold text-slate-700 dark:text-slate-300"><i class="fa-solid fa-circle-info mr-1 text-indigo-500"></i> Password Security Requirements:</p>
                        <ul class="list-disc list-inside space-y-0.5 pl-2">
                            <li>Minimum 8 characters in length</li>
                            <li>Should include uppercase and lowercase letters</li>
                            <li>Should include numbers and special symbols (e.g. !@#$%^&*)</li>
                            <li>Never share this developer master password with hospital staff</li>
                        </ul>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-semibold shadow-md shadow-amber-600/30 transition-all flex items-center gap-2">
                            <i class="fa-solid fa-shield-keyhole"></i>
                            <span>Update Password</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </div>

</div>
