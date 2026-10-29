<?php
/**
 * Hospital Management System (HMS) - Public Registration Status Tracker
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/RegistrationService.php';

$flash = get_flash();
$trackingCode = strtoupper(trim($_GET['code'] ?? $_POST['tracking_code'] ?? ''));
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');

$requestData = null;
$searched = false;

if (!empty($trackingCode)) {
    $searched = true;
    $requestData = RegistrationService::getRequestByTracking($trackingCode, $email ?: null);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Status — <?= htmlspecialchars(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-slate-100 min-h-screen py-10 px-4 sm:px-6">

    <div class="max-w-2xl mx-auto space-y-6">

        <!-- Header Branding -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-teal-600 text-white text-2xl shadow-xl shadow-teal-500/20 mb-1">
                <i class="fa-solid fa-file-waveform"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Hospital Registration Status</h1>
            <p class="text-xs sm:text-sm text-slate-400">
                Track your hospital application review, supply required clarifications, or access your approved tenant workspace.
            </p>
        </div>

        <!-- Flash Alert -->
        <?php if ($flash): ?>
        <div class="p-4 rounded-xl text-xs <?= $flash['type'] === 'danger' ? 'bg-rose-500/20 border border-rose-500/40 text-rose-200' : 'bg-emerald-500/20 border border-emerald-500/40 text-emerald-200' ?> flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-base"></i>
            <span><?= htmlspecialchars($flash['message']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Search Form Card -->
        <div class="bg-slate-900/90 backdrop-blur-lg border border-slate-800 rounded-3xl p-6 shadow-2xl">
            <form method="GET" action="index.php" class="space-y-4">
                <input type="hidden" name="page" value="registration_status">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Tracking Request ID <span class="text-rose-400">*</span></label>
                        <div class="relative">
                            <i class="fa-solid fa-hashtag absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="code" required value="<?= htmlspecialchars($trackingCode) ?>" placeholder="e.g. REG-202610-A7B2" class="w-full pl-9 pr-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm font-mono text-white placeholder-slate-500 outline-none uppercase transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Applicant Email Address</label>
                        <div class="relative">
                            <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="e.g. name@hospital.com" class="w-full pl-9 pr-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-semibold shadow-md shadow-teal-600/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Check Status</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Result Display -->
        <?php if ($searched && $requestData): ?>
            <?php
            $status = $requestData['status'];
            $statusBadge = 'bg-slate-500/10 text-slate-400 border-slate-500/30';
            $statusIcon = 'fa-circle-info';
            switch ($status) {
                case 'Pending Approval':
                    $statusBadge = 'bg-amber-500/10 text-amber-400 border-amber-500/30';
                    $statusIcon = 'fa-clock';
                    break;
                case 'Under Review':
                    $statusBadge = 'bg-sky-500/10 text-sky-400 border-sky-500/30';
                    $statusIcon = 'fa-spinner fa-spin';
                    break;
                case 'Additional Information Required':
                    $statusBadge = 'bg-purple-500/10 text-purple-300 border-purple-500/30';
                    $statusIcon = 'fa-circle-question';
                    break;
                case 'Approved':
                    $statusBadge = 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30';
                    $statusIcon = 'fa-circle-check';
                    break;
                case 'Rejected':
                    $statusBadge = 'bg-rose-500/10 text-rose-400 border-rose-500/30';
                    $statusIcon = 'fa-circle-xmark';
                    break;
            }
            ?>

            <div class="bg-slate-900/90 backdrop-blur-lg border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">

                <!-- Header Info -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-5">
                    <div>
                        <span class="text-[11px] font-mono text-teal-400 tracking-wider">REQUEST #<?= htmlspecialchars($requestData['tracking_code']) ?></span>
                        <h2 class="text-xl font-bold text-white"><?= htmlspecialchars($requestData['hospital_name']) ?></h2>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($requestData['city']) ?>, <?= htmlspecialchars($requestData['country']) ?> • Submitted on <?= date('M d, Y h:i A', strtotime($requestData['created_at'])) ?></p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border <?= $statusBadge ?> self-start sm:self-auto">
                        <i class="fa-solid <?= $statusIcon ?> text-xs"></i>
                        <span><?= htmlspecialchars($status) ?></span>
                    </span>
                </div>

                <!-- Status Explanations -->
                <?php if ($status === 'Pending Approval' || $status === 'Under Review'): ?>
                    <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300 space-y-2">
                        <div class="font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-hourglass-half text-amber-400"></i>
                            <span>Application Currently in Review Queue</span>
                        </div>
                        <p class="text-slate-400 leading-relaxed">
                            Your registration request has been submitted to the Platform Provider team. You will receive an automated status update once hospital verification is completed.
                        </p>
                    </div>

                <?php elseif ($status === 'Additional Information Required'): ?>
                    <div class="p-5 rounded-2xl bg-purple-950/40 border border-purple-800/60 space-y-4">
                        <div class="space-y-1">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 uppercase">Action Needed</span>
                            <h3 class="text-sm font-bold text-white">Reviewer Inquiry / Information Request</h3>
                            <p class="text-xs text-purple-200/90 font-medium bg-slate-900/60 p-3 rounded-xl border border-purple-900/40 mt-2">
                                "<?= htmlspecialchars($requestData['developer_notes'] ?: 'Please provide hospital regulatory license or contact verification.') ?>"
                            </p>
                        </div>

                        <!-- Clarification Reply Form -->
                        <form method="POST" action="index.php?action=applicant_submit_clarification" class="space-y-3 pt-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="tracking_code" value="<?= htmlspecialchars($requestData['tracking_code']) ?>">
                            <input type="hidden" name="email" value="<?= htmlspecialchars($requestData['applicant_email']) ?>">

                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Your Clarification & Details</label>
                                <textarea name="reply_notes" rows="3" required placeholder="Type requested information or clarification details here..." class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 focus:border-purple-500 rounded-xl text-xs text-white placeholder-slate-500 outline-none transition-all"></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-semibold shadow-md transition-all flex items-center gap-2">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <span>Submit Clarification</span>
                                </button>
                            </div>
                        </form>
                    </div>

                <?php elseif ($status === 'Approved'): ?>
                    <div class="p-6 rounded-2xl bg-emerald-950/40 border border-emerald-800/60 text-center space-y-4">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto text-2xl border border-emerald-500/30">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-lg font-bold text-white">Congratulations! Hospital Approved & Activated</h3>
                            <p class="text-xs text-emerald-300/90 max-w-md mx-auto">
                                Your facility tenant has been successfully created. You can now log in using your registered email and the master password you created.
                            </p>
                        </div>
                        <div class="pt-2">
                            <a href="index.php?page=login" class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 transition-all">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <span>Sign In to Hospital Portal</span>
                            </a>
                        </div>
                    </div>

                <?php elseif ($status === 'Rejected'): ?>
                    <div class="p-5 rounded-2xl bg-rose-950/40 border border-rose-800/60 space-y-2">
                        <div class="font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                            <span>Application Not Approved</span>
                        </div>
                        <p class="text-xs text-rose-200/90 leading-relaxed">
                            Reason provided: "<?= htmlspecialchars($requestData['developer_notes'] ?: 'Registration criteria not met.') ?>"
                        </p>
                        <p class="text-[11px] text-slate-400 pt-1">
                            If you believe this was an error or wish to submit an amended application, please contact the platform provider.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Summary Details Table -->
                <div class="border-t border-slate-800 pt-4 space-y-2 text-xs">
                    <h4 class="font-bold text-slate-300">Application Summary</h4>
                    <div class="grid grid-cols-2 gap-2 text-slate-400">
                        <div><span class="text-slate-500">Applicant:</span> <?= htmlspecialchars($requestData['applicant_name']) ?> (<?= htmlspecialchars($requestData['applicant_designation']) ?>)</div>
                        <div><span class="text-slate-500">Contact:</span> <?= htmlspecialchars($requestData['applicant_phone']) ?></div>
                        <div><span class="text-slate-500">Facility Type:</span> <?= htmlspecialchars($requestData['hospital_type']) ?></div>
                        <div><span class="text-slate-500">License #:</span> <?= htmlspecialchars($requestData['license_number'] ?: 'Not Provided') ?></div>
                    </div>
                </div>

            </div>

        <?php elseif ($searched && !$requestData): ?>
            <div class="p-8 rounded-3xl bg-slate-900/90 border border-slate-800 text-center space-y-2">
                <i class="fa-solid fa-file-circle-question text-3xl text-slate-600 mb-1 block"></i>
                <h3 class="text-sm font-bold text-white">No Matching Request Found</h3>
                <p class="text-xs text-slate-400 max-w-md mx-auto">
                    We could not find an active registration request matching tracking code <span class="font-mono text-teal-400"><?= htmlspecialchars($trackingCode) ?></span>. Please verify the code or check your confirmation email.
                </p>
            </div>
        <?php endif; ?>

        <!-- Footer Navigation Links -->
        <div class="flex flex-col sm:flex-row items-center justify-between text-xs text-slate-400 gap-3 px-2">
            <a href="index.php?page=login" class="hover:text-teal-400 transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Login</span>
            </a>
            <a href="index.php?page=register_hospital" class="hover:text-teal-400 transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-hospital-user"></i>
                <span>Register a New Hospital</span>
            </a>
        </div>

    </div>

</body>
</html>
