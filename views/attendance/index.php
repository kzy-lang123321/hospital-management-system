<?php
/**
 * Hospital Management System (HMS)
 * Location-Verified Staff Attendance & Management Center
 */

Auth::requireLogin();

require_once __DIR__ . '/../../core/AttendanceService.php';
require_once __DIR__ . '/../../core/Tenant.php';

$currentUser = Auth::user();
$hId = (int)($currentUser['hospital_id'] ?? 0);

if ($hId <= 0 && !Auth::isDeveloper()) {
    echo "<div class='p-6 bg-amber-500/10 text-amber-700 dark:text-amber-300 rounded-xl'>You must be assigned to an active hospital to record attendance.</div>";
    return;
}

// Check if user is supervisor/admin
$isSupervisor = Auth::hasRole(['superadmin', 'admin', 'hr', 'hospital_owner', 'medical_director']);

$hProfile = Tenant::profile($hId);
$hLat = (float)($hProfile['hospital_latitude'] ?? 34.01513700);
$hLon = (float)($hProfile['hospital_longitude'] ?? 71.52491500);
$geofenceRadius = (int)($hProfile['geofence_radius_meters'] ?? 300);
$graceMins = (int)($hProfile['attendance_grace_minutes'] ?? 15);

// Fetch Staff Data
$todayRecord = AttendanceService::getTodayRecord((int)$currentUser['id']);
$userHistory = AttendanceService::getUserHistory((int)$currentUser['id'], 10);

// For supervisor:
$filterDate = $_GET['filter_date'] ?? date('Y-m-d');
$filterStatus = $_GET['filter_status'] ?? '';
$hospitalRegister = $isSupervisor ? AttendanceService::getHospitalRegister($hId, $filterDate, $filterStatus) : [];
$pendingExceptions = $isSupervisor ? AttendanceService::getPendingExceptions($hId) : [];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if (!function_exists('attBadgeClass')) {
    function attBadgeClass($status) {
        switch ($status) {
            case 'Verified': return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30';
            case 'Late': return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30';
            case 'Out_Of_Bounds': return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30';
            case 'Exception': return 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/30';
            default: return 'bg-slate-500/10 text-slate-600 border-slate-500/30';
        }
    }
}
?>

<div class="space-y-6">

    <!-- Top Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 rounded-2xl text-white shadow-xl border border-indigo-900/40">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    <i class="fa-solid fa-location-crosshairs mr-1"></i> GPS Geofence & Duty System
                </span>
                <span class="text-xs text-indigo-300/80 font-mono"><?= htmlspecialchars($hProfile['name'] ?? 'Hospital Facility') ?></span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight">Staff Attendance & Geofenced Time Clock</h1>
            <p class="text-sm text-slate-300 mt-1">Tamper-resistant duty verification using browser HTML5 Geolocation, Haversine perimeter validation, and shift audit trail.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right bg-slate-800/80 px-4 py-2.5 rounded-xl border border-slate-700/80">
                <div class="text-[10px] uppercase font-mono tracking-wider text-slate-400">Current Time</div>
                <div id="liveClock" class="text-lg font-mono font-bold text-white tracking-wide">--:--:--</div>
            </div>
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

    <!-- Navigation Tabs if Supervisor -->
    <?php if ($isSupervisor): ?>
    <div class="flex border-b border-slate-200 dark:border-slate-800 space-x-2">
        <button onclick="switchTab('my-attendance')" id="tabBtn-my-attendance" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 flex items-center gap-2">
            <i class="fa-solid fa-user-clock"></i>
            <span>My Duty Clock</span>
        </button>
        <button onclick="switchTab('hospital-register')" id="tabBtn-hospital-register" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-user"></i>
            <span>Hospital Register</span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-mono"><?= count($hospitalRegister) ?></span>
        </button>
        <button onclick="switchTab('exceptions-queue')" id="tabBtn-exceptions-queue" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-2">
            <i class="fa-solid fa-envelope-open-text"></i>
            <span>Exception Appeals</span>
            <?php if (count($pendingExceptions) > 0): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-amber-500 text-white font-mono animate-pulse"><?= count($pendingExceptions) ?></span>
            <?php endif; ?>
        </button>
        <button onclick="switchTab('geofence-config')" id="tabBtn-geofence-config" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-2">
            <i class="fa-solid fa-sliders"></i>
            <span>Geofence & Policy</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- TAB 1: MY ATTENDANCE CLOCK -->
    <div id="tabContent-my-attendance" class="space-y-6">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Check-In / Clock Control Panel -->
            <div class="lg:col-span-1 bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-street-view text-indigo-600 dark:text-indigo-400"></i>
                        <span>Duty Verification</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Browser GPS confirms you are within <?= $geofenceRadius ?>m of the facility.</p>
                </div>

                <!-- GPS Status Display -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Device Location Status</span>
                        <span id="gpsBadge" class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 flex items-center gap-1">
                            <i class="fa-solid fa-spinner fa-spin text-[10px]"></i> Acquiring GPS...
                        </span>
                    </div>

                    <div class="space-y-1.5 text-xs font-mono">
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Latitude:</span>
                            <span id="dispLat" class="font-semibold text-slate-900 dark:text-white">--</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Longitude:</span>
                            <span id="dispLon" class="font-semibold text-slate-900 dark:text-white">--</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Accuracy:</span>
                            <span id="dispAcc" class="font-semibold text-slate-900 dark:text-white">--</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Distance to Facility:</span>
                            <span id="dispDist" class="font-semibold text-indigo-600 dark:text-indigo-400">--</span>
                        </div>
                    </div>

                    <button type="button" onclick="acquireLocation(true)" class="w-full py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span>Refresh GPS Coordinates</span>
                    </button>
                </div>

                <!-- Clock In / Out Forms -->
                <?php if (!$todayRecord): ?>
                    <!-- Not Checked In Yet -->
                    <form method="POST" action="index.php?action=attendance_checkin" id="checkInForm" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="latitude" id="inLat">
                        <input type="hidden" name="longitude" id="inLon">
                        <input type="hidden" name="accuracy" id="inAcc">

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Duty Notes (Optional)</label>
                            <input type="text" name="notes" placeholder="e.g., Morning Shift On-Duty" 
                                   class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>

                        <button type="submit" id="btnCheckIn" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-600/30 transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-right-to-bracket text-base"></i>
                            <span>Clock In for Duty</span>
                        </button>
                    </form>

                <?php elseif (empty($todayRecord['check_out_time'])): ?>
                    <!-- Checked In, Active Session -->
                    <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-center space-y-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            Active Duty Session
                        </span>
                        <div class="text-xs text-slate-600 dark:text-slate-400">
                            Clocked in at <span class="font-bold text-slate-900 dark:text-white"><?= date('h:i A', strtotime($todayRecord['check_in_time'])) ?></span>
                        </div>
                        <div class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                            Status: <?= htmlspecialchars($todayRecord['check_in_status']) ?>
                        </div>
                    </div>

                    <form method="POST" action="index.php?action=attendance_checkout" id="checkOutForm" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="latitude" id="outLat">
                        <input type="hidden" name="longitude" id="outLon">
                        <input type="hidden" name="accuracy" id="outAcc">

                        <button type="submit" id="btnCheckOut" class="w-full py-3.5 bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-rose-600/30 transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-right-from-bracket text-base"></i>
                            <span>Clock Out & End Duty</span>
                        </button>
                    </form>

                <?php else: ?>
                    <!-- Completed for Today -->
                    <div class="p-5 rounded-xl bg-slate-100 dark:bg-slate-800 text-center space-y-3">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto text-xl">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">Duty Completed Today</h4>
                        <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1">
                            <p>Clocked In: <span class="font-semibold text-slate-800 dark:text-slate-200"><?= date('h:i A', strtotime($todayRecord['check_in_time'])) ?></span></p>
                            <p>Clocked Out: <span class="font-semibold text-slate-800 dark:text-slate-200"><?= date('h:i A', strtotime($todayRecord['check_out_time'])) ?></span></p>
                            <p>Total Recorded: <span class="font-bold text-indigo-600 dark:text-indigo-400"><?= $todayRecord['total_hours'] ?> Hours</span></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($todayRecord && $todayRecord['check_in_status'] === 'Out_Of_Bounds'): ?>
                    <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-xs text-rose-800 dark:text-rose-300 space-y-2">
                        <div class="font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span>Out of Bounds Flagged</span>
                        </div>
                        <p class="text-[11px] leading-relaxed">
                            Your check-in was logged outside the <?= $geofenceRadius ?>m hospital boundary (<?= $todayRecord['check_in_distance_meters'] ?>m away). You may submit an appeal for supervisor review.
                        </p>
                        <button type="button" onclick="openExceptionModal(<?= $todayRecord['id'] ?>, 'Out_Of_Bounds')" class="px-3 py-1.5 bg-rose-600 text-white rounded-lg text-xs font-semibold hover:bg-rose-500 transition-all">
                            Submit Exception Appeal
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Today's Summary & Attendance History -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Today's Details Card -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-day text-indigo-600"></i>
                        <span>Today's Shift Log (<?= date('l, M d, Y') ?>)</span>
                    </h3>

                    <?php if ($todayRecord): ?>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 block mb-1">Check-In Time</span>
                                <span class="text-sm font-bold text-slate-900 dark:text-white"><?= date('h:i:s A', strtotime($todayRecord['check_in_time'])) ?></span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 block mb-1">Verification</span>
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold border <?= attBadgeClass($todayRecord['check_in_status']) ?>">
                                    <?= htmlspecialchars($todayRecord['check_in_status']) ?>
                                </span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 block mb-1">Check-Out Time</span>
                                <span class="text-sm font-bold text-slate-900 dark:text-white">
                                    <?= !empty($todayRecord['check_out_time']) ? date('h:i:s A', strtotime($todayRecord['check_out_time'])) : '<span class="text-amber-500">In Progress</span>' ?>
                                </span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 block mb-1">Total Hours</span>
                                <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                    <?= $todayRecord['total_hours'] ?? '0.00' ?> hrs
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-6 text-center text-slate-500 dark:text-slate-400 text-xs">
                            No attendance recorded yet for today. Use the "Clock In for Duty" button to start your shift.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Personal History Table -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                            <span>My Recent Duty History (Last 10 Records)</span>
                        </h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 uppercase font-semibold">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Clock In</th>
                                    <th class="px-4 py-3">Clock Out</th>
                                    <th class="px-4 py-3">Hours</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Distance</th>
                                    <th class="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php if (empty($userHistory)): ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-slate-400">No previous attendance records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($userHistory as $row): ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white"><?= date('M d, Y', strtotime($row['attendance_date'])) ?></td>
                                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?= date('h:i A', strtotime($row['check_in_time'])) ?></td>
                                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?= !empty($row['check_out_time']) ? date('h:i A', strtotime($row['check_out_time'])) : '-' ?></td>
                                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white"><?= $row['total_hours'] ?? '-' ?> hrs</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= attBadgeClass($row['check_in_status']) ?>">
                                                    <?= htmlspecialchars($row['check_in_status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-slate-500 font-mono"><?= $row['check_in_distance_meters'] !== null ? round($row['check_in_distance_meters']) . 'm' : '-' ?></td>
                                            <td class="px-4 py-3 text-right">
                                                <button type="button" onclick="openExceptionModal(<?= $row['id'] ?>, 'Regularization')" class="text-indigo-600 hover:text-indigo-500 font-medium text-xs">
                                                    Appeal
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <?php if ($isSupervisor): ?>
    <!-- TAB 2: HOSPITAL REGISTER -->
    <div id="tabContent-hospital-register" class="space-y-6 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Facility Attendance Register</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">All staff clock-in records verified with GPS coordinates and timestamps.</p>
                </div>
                <form method="GET" action="index.php" class="flex items-center gap-2">
                    <input type="hidden" name="page" value="attendance">
                    <input type="date" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>" class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs">
                    <select name="filter_status" class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs">
                        <option value="">All Statuses</option>
                        <option value="Verified" <?= $filterStatus === 'Verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="Late" <?= $filterStatus === 'Late' ? 'selected' : '' ?>>Late</option>
                        <option value="Out_Of_Bounds" <?= $filterStatus === 'Out_Of_Bounds' ? 'selected' : '' ?>>Out of Bounds</option>
                    </select>
                    <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded-xl text-xs font-semibold">Filter</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">Staff Member</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Clock In</th>
                            <th class="px-4 py-3">Clock Out</th>
                            <th class="px-4 py-3">Total Time</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Perimeter Distance</th>
                            <th class="px-4 py-3">Suspicious Flag</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (empty($hospitalRegister)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">No attendance records for <?= htmlspecialchars($filterDate) ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($hospitalRegister as $reg): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($reg['staff_name']) ?>
                                        <div class="text-[10px] text-slate-400 font-mono">@<?= htmlspecialchars($reg['username']) ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?= htmlspecialchars($reg['department_name'] ?? 'General') ?></td>
                                    <td class="px-4 py-3 text-slate-800 dark:text-slate-200"><?= date('h:i A', strtotime($reg['check_in_time'])) ?></td>
                                    <td class="px-4 py-3 text-slate-800 dark:text-slate-200"><?= !empty($reg['check_out_time']) ? date('h:i A', strtotime($reg['check_out_time'])) : '<span class="text-amber-500 font-medium">On Duty</span>' ?></td>
                                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white"><?= $reg['total_hours'] ?? '-' ?> hrs</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= attBadgeClass($reg['check_in_status']) ?>">
                                            <?= htmlspecialchars($reg['check_in_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-400"><?= $reg['check_in_distance_meters'] !== null ? round($reg['check_in_distance_meters']) . 'm' : '-' ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($reg['is_suspicious']): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] bg-rose-500 text-white font-bold" title="<?= htmlspecialchars($reg['suspicion_reason'] ?? '') ?>">Flagged</span>
                                        <?php else: ?>
                                            <span class="text-emerald-500 text-xs"><i class="fa-solid fa-check"></i> Clean</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: EXCEPTION APPEALS QUEUE -->
    <div id="tabContent-exceptions-queue" class="space-y-6 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Pending Attendance Exception Appeals</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Review staff appeals for out-of-bounds check-ins, device GPS inaccuracies, or late arrivals.</p>
            </div>

            <?php if (empty($pendingExceptions)): ?>
                <div class="p-8 text-center text-slate-400 text-xs">
                    <i class="fa-solid fa-circle-check text-3xl text-emerald-500/40 mb-2 block"></i>
                    No pending attendance exception appeals.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($pendingExceptions as $exc): ?>
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-slate-900 dark:text-white"><?= htmlspecialchars($exc['staff_name']) ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/10 text-indigo-600 border border-indigo-500/30"><?= htmlspecialchars($exc['request_type']) ?></span>
                                    <span class="text-[10px] text-slate-400"><?= date('M d, Y h:i A', strtotime($exc['created_at'])) ?></span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-300 font-medium">"<?= htmlspecialchars($exc['reason']) ?>"</p>
                                <div class="text-[11px] text-slate-400">
                                    Linked Check-In: <?= $exc['check_in_time'] ? date('h:i A', strtotime($exc['check_in_time'])) : 'N/A' ?> | Status: <?= $exc['check_in_status'] ?? 'N/A' ?> | Distance: <?= $exc['check_in_distance_meters'] ? round($exc['check_in_distance_meters']) . 'm' : 'N/A' ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="index.php?action=attendance_exception_review" class="flex items-center gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <input type="hidden" name="exception_id" value="<?= $exc['id'] ?>">
                                    <input type="hidden" name="status" value="Approved">
                                    <button type="submit" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold transition-all">
                                        Approve
                                    </button>
                                </form>
                                <form method="POST" action="index.php?action=attendance_exception_review" class="flex items-center gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <input type="hidden" name="exception_id" value="<?= $exc['id'] ?>">
                                    <input type="hidden" name="status" value="Rejected">
                                    <button type="submit" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-semibold transition-all">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 4: GEOFENCE & POLICY CONFIG -->
    <div id="tabContent-geofence-config" class="space-y-6 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm max-w-2xl">
            <div class="mb-6">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Hospital Perimeter Geofence & Duty Settings</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Configure coordinates and acceptable radius for location-verified duty check-ins.</p>
            </div>

            <form method="POST" action="index.php?action=attendance_settings_save" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Hospital Latitude</label>
                        <input type="text" name="hospital_latitude" id="cfgLat" required value="<?= htmlspecialchars($hLat) ?>" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Hospital Longitude</label>
                        <input type="text" name="hospital_longitude" id="cfgLon" required value="<?= htmlspecialchars($hLon) ?>" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono">
                    </div>
                </div>

                <button type="button" onclick="populateConfigWithCurrentLocation()" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium flex items-center gap-2">
                    <i class="fa-solid fa-location-crosshairs text-indigo-600"></i>
                    <span>Set from My Current Device Coordinates</span>
                </button>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Geofence Radius (Meters)</label>
                        <input type="number" name="geofence_radius_meters" required min="50" max="5000" value="<?= htmlspecialchars($geofenceRadius) ?>" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs">
                        <span class="text-[10px] text-slate-400 mt-1 block">Default is 300 meters from hospital center point.</span>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Late Grace Period (Minutes)</label>
                        <input type="number" name="attendance_grace_minutes" required min="0" max="120" value="<?= htmlspecialchars($graceMins) ?>" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs">
                        <span class="text-[10px] text-slate-400 mt-1 block">Staff can check in within grace minutes without being flagged Late.</span>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-md transition-all flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Geofence Settings</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Exception Appeal Submission -->
<div id="exceptionModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Submit Attendance Appeal</h3>
            <button onclick="closeExceptionModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=attendance_exception_submit" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="attendance_id" id="modalAttId">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Request Type</label>
                <select name="request_type" id="modalReqType" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs">
                    <option value="Out_Of_Bounds">Out of Bounds Appeal (GPS inaccuracy)</option>
                    <option value="Late_Arrival">Late Arrival Justification (Emergency / Shift Delay)</option>
                    <option value="Missed_Punch">Missed Check-In/Out Punch</option>
                    <option value="Device_Issue">Device / Browser Glitch</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Reason & Explanation <span class="text-rose-500">*</span></label>
                <textarea name="reason" rows="3" required placeholder="Please provide specific reason for supervisor verification..." class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeExceptionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-md">Submit Appeal</button>
            </div>
        </form>
    </div>
</div>

<script>
// Live Clock
function updateClock() {
    const now = new Date();
    const clockEl = document.getElementById('liveClock');
    if (clockEl) {
        clockEl.textContent = now.toLocaleTimeString();
    }
}
setInterval(updateClock, 1000);
updateClock();

// Geolocation Handling
let currentCoords = null;
const hospitalLat = <?= json_encode($hLat) ?>;
const hospitalLon = <?= json_encode($hLon) ?>;
const geofenceRadius = <?= json_encode($geofenceRadius) ?>;

function calculateClientDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return Math.round(R * c);
}

function acquireLocation(manual = false) {
    const badge = document.getElementById('gpsBadge');
    if (badge) {
        badge.className = "px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 flex items-center gap-1";
        badge.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[10px]"></i> Acquiring GPS...';
    }

    if (!navigator.geolocation) {
        if (badge) {
            badge.className = "px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700 border border-rose-200";
            badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> GPS Not Supported';
        }
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            currentCoords = pos.coords;
            const lat = pos.coords.latitude;
            const lon = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy);

            const dispLat = document.getElementById('dispLat');
            const dispLon = document.getElementById('dispLon');
            const dispAcc = document.getElementById('dispAcc');
            const dispDist = document.getElementById('dispDist');

            if (dispLat) dispLat.textContent = lat.toFixed(6);
            if (dispLon) dispLon.textContent = lon.toFixed(6);
            if (dispAcc) dispAcc.textContent = `±${acc} meters`;

            // Calculate distance
            const dist = calculateClientDistance(lat, lon, hospitalLat, hospitalLon);
            if (dispDist) dispDist.textContent = `${dist} meters (${dist <= geofenceRadius ? 'Within Boundary' : 'Out of Bounds'})`;

            // Populate forms
            const inLat = document.getElementById('inLat');
            const inLon = document.getElementById('inLon');
            const inAcc = document.getElementById('inAcc');
            if (inLat) inLat.value = lat;
            if (inLon) inLon.value = lon;
            if (inAcc) inAcc.value = acc;

            const outLat = document.getElementById('outLat');
            const outLon = document.getElementById('outLon');
            const outAcc = document.getElementById('outAcc');
            if (outLat) outLat.value = lat;
            if (outLon) outLon.value = lon;
            if (outAcc) outAcc.value = acc;

            if (badge) {
                if (dist <= geofenceRadius) {
                    badge.className = "px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 flex items-center gap-1";
                    badge.innerHTML = '<i class="fa-solid fa-circle-check text-[10px]"></i> Location Verified';
                } else {
                    badge.className = "px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 flex items-center gap-1";
                    badge.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-[10px]"></i> Out of Geofence (${dist}m)`;
                }
            }
        },
        (err) => {
            console.warn("Geolocation error:", err.message);
            if (badge) {
                badge.className = "px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700 border border-rose-200";
                badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> GPS Permission Denied';
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

// Auto-run on load
document.addEventListener('DOMContentLoaded', () => {
    acquireLocation(false);
});

// Tab Switcher
function switchTab(tabId) {
    const tabs = ['my-attendance', 'hospital-register', 'exceptions-queue', 'geofence-config'];
    tabs.forEach(t => {
        const btn = document.getElementById('tabBtn-' + t);
        const content = document.getElementById('tabContent-' + t);
        if (btn && content) {
            if (t === tabId) {
                btn.className = "tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 flex items-center gap-2";
                content.classList.remove('hidden');
            } else {
                btn.className = "tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-2";
                content.classList.add('hidden');
            }
        }
    });
}

function openExceptionModal(attendanceId, type) {
    document.getElementById('modalAttId').value = attendanceId;
    if (type) document.getElementById('modalReqType').value = type;
    document.getElementById('exceptionModal').classList.remove('hidden');
}

function closeExceptionModal() {
    document.getElementById('exceptionModal').classList.add('hidden');
}

function populateConfigWithCurrentLocation() {
    if (currentCoords) {
        document.getElementById('cfgLat').value = currentCoords.latitude.toFixed(8);
        document.getElementById('cfgLon').value = currentCoords.longitude.toFixed(8);
    } else {
        acquireLocation(true);
        setTimeout(() => {
            if (currentCoords) {
                document.getElementById('cfgLat').value = currentCoords.latitude.toFixed(8);
                document.getElementById('cfgLon').value = currentCoords.longitude.toFixed(8);
            }
        }, 1500);
    }
}
</script>
