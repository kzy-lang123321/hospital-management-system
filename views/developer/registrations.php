<?php
/**
 * Hospital Management System (HMS)
 * Platform Developer - Hospital Registration Review Console
 */

Auth::requireRole(['developer', 'platform_admin']);

require_once __DIR__ . '/../../core/RegistrationService.php';

$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$activeRequestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

$counts = RegistrationService::getCountsByStatus();
$requests = RegistrationService::getRequests($filterStatus, $search);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="space-y-6">

    <!-- Top Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 p-6 rounded-2xl text-white shadow-xl border border-indigo-900/40">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-teal-500/20 text-teal-300 border border-teal-500/30">
                    <i class="fa-solid fa-hospital-user mr-1"></i> Tenant Intake Portal
                </span>
                <span class="text-xs text-indigo-300/80 font-mono">Platform Review & Provisioning</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight">Hospital Registration Requests</h1>
            <p class="text-sm text-slate-300 mt-1">Review public hospital onboarding submissions, inspect compliance details, and securely provision new tenant workspaces.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="index.php?page=developer_dashboard" class="px-4 py-2 bg-slate-800/80 hover:bg-slate-700 text-white rounded-xl text-xs font-medium border border-slate-700 transition-all flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Platform Dashboard</span>
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

    <!-- Status KPI Badges & Quick Filters -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <a href="index.php?page=developer_registrations" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border <?= empty($filterStatus) ? 'border-indigo-600 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-800' ?> shadow-sm hover:border-indigo-400 transition-all">
            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">All Requests</span>
            <span class="text-xl font-extrabold text-slate-900 dark:text-white"><?= $counts['total'] ?></span>
        </a>
        <a href="index.php?page=developer_registrations&status=Pending+Approval" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border <?= $filterStatus === 'Pending Approval' ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-slate-200 dark:border-slate-800' ?> shadow-sm hover:border-amber-400 transition-all">
            <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider block mb-1">Pending Approval</span>
            <span class="text-xl font-extrabold text-amber-600 dark:text-amber-400"><?= $counts['pending'] ?></span>
        </a>
        <a href="index.php?page=developer_registrations&status=Under+Review" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border <?= $filterStatus === 'Under Review' ? 'border-sky-500 ring-2 ring-sky-500/20' : 'border-slate-200 dark:border-slate-800' ?> shadow-sm hover:border-sky-400 transition-all">
            <span class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-wider block mb-1">Under Review</span>
            <span class="text-xl font-extrabold text-sky-600 dark:text-sky-400"><?= $counts['under_review'] ?></span>
        </a>
        <a href="index.php?page=developer_registrations&status=Additional+Information+Required" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border <?= $filterStatus === 'Additional Information Required' ? 'border-purple-500 ring-2 ring-purple-500/20' : 'border-slate-200 dark:border-slate-800' ?> shadow-sm hover:border-purple-400 transition-all">
            <span class="text-[11px] font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider block mb-1">Needs Info</span>
            <span class="text-xl font-extrabold text-purple-600 dark:text-purple-400"><?= $counts['need_info'] ?></span>
        </a>
        <a href="index.php?page=developer_registrations&status=Approved" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border <?= $filterStatus === 'Approved' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200 dark:border-slate-800' ?> shadow-sm hover:border-emerald-400 transition-all">
            <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block mb-1">Approved</span>
            <span class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= $counts['approved'] ?></span>
        </a>
        <a href="index.php?page=developer_registrations&status=Rejected" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border <?= $filterStatus === 'Rejected' ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 dark:border-slate-800' ?> shadow-sm hover:border-rose-400 transition-all">
            <span class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider block mb-1">Rejected</span>
            <span class="text-xl font-extrabold text-rose-600 dark:text-rose-400"><?= $counts['rejected'] ?></span>
        </a>
    </div>

    <!-- Search & Requests Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Registration Application Queue</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Applications submitted from public registration requiring review and tenant provisioning.</p>
            </div>
            <form method="GET" action="index.php" class="flex items-center gap-2">
                <input type="hidden" name="page" value="developer_registrations">
                <?php if (!empty($filterStatus)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <?php endif; ?>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search hospital, owner, code..." class="pl-8 pr-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white outline-none w-52">
                </div>
                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded-xl text-xs font-semibold hover:bg-indigo-500 transition-all">Search</button>
                <?php if (!empty($search) || !empty($filterStatus)): ?>
                    <a href="index.php?page=developer_registrations" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Tracking Code</th>
                        <th class="px-4 py-3">Hospital Facility</th>
                        <th class="px-4 py-3">Applicant / Owner</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Submission Date</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-500 block"></i>
                                No registration requests match the selected filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <?php
                            $st = $req['status'];
                            $bCls = 'bg-slate-500/10 text-slate-400 border-slate-500/30';
                            switch ($st) {
                                case 'Pending Approval': $bCls = 'bg-amber-500/10 text-amber-500 border-amber-500/30'; break;
                                case 'Under Review': $bCls = 'bg-sky-500/10 text-sky-500 border-sky-500/30'; break;
                                case 'Additional Information Required': $bCls = 'bg-purple-500/10 text-purple-500 border-purple-500/30'; break;
                                case 'Approved': $bCls = 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30'; break;
                                case 'Rejected': $bCls = 'bg-rose-500/10 text-rose-500 border-rose-500/30'; break;
                            }
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                    <?= htmlspecialchars($req['tracking_code']) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($req['hospital_name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($req['hospital_type']) ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($req['applicant_name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($req['applicant_email']) ?> • <?= htmlspecialchars($req['applicant_phone']) ?></div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                    <?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['country']) ?>
                                </td>
                                <td class="px-4 py-3 text-slate-500 font-mono">
                                    <?= date('M d, Y h:i A', strtotime($req['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-semibold border <?= $bCls ?>">
                                        <?= htmlspecialchars($st) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" onclick='openReviewModal(<?= json_encode($req, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded-lg font-semibold transition-all flex items-center gap-1.5 ml-auto">
                                        <i class="fa-solid fa-eye"></i>
                                        <span>Inspect</span>
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

<!-- Inspection & Review Modal -->
<div id="reviewModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-6">

        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
                <span id="m_code" class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400"></span>
                <h3 id="m_hosp_name" class="text-lg font-bold text-slate-900 dark:text-white"></h3>
            </div>
            <button onclick="closeReviewModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Detail Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 space-y-1.5">
                <h4 class="font-bold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-user-shield text-teal-500"></i> Applicant & Owner Account
                </h4>
                <p><span class="text-slate-400">Name:</span> <strong id="m_app_name" class="text-slate-900 dark:text-white"></strong></p>
                <p><span class="text-slate-400">Designation:</span> <span id="m_app_desig" class="text-slate-700 dark:text-slate-300"></span></p>
                <p><span class="text-slate-400">Email:</span> <span id="m_app_email" class="font-mono text-slate-700 dark:text-slate-300"></span></p>
                <p><span class="text-slate-400">Phone:</span> <span id="m_app_phone" class="font-mono text-slate-700 dark:text-slate-300"></span></p>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 space-y-1.5">
                <h4 class="font-bold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-hospital text-indigo-500"></i> Facility & Operational Info
                </h4>
                <p><span class="text-slate-400">Type:</span> <span id="m_type" class="text-slate-700 dark:text-slate-300"></span></p>
                <p><span class="text-slate-400">License #:</span> <span id="m_license" class="text-slate-700 dark:text-slate-300 font-mono"></span></p>
                <p><span class="text-slate-400">Location:</span> <span id="m_loc" class="text-slate-700 dark:text-slate-300"></span></p>
                <p><span class="text-slate-400">Bed Capacity:</span> <span id="m_beds" class="text-slate-700 dark:text-slate-300"></span></p>
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 text-xs space-y-1">
            <span class="text-slate-400 block font-semibold">Physical Address:</span>
            <p id="m_address" class="text-slate-800 dark:text-slate-200 font-medium"></p>
        </div>

        <!-- Notes from applicant -->
        <div id="notesContainer" class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 text-xs space-y-1 hidden">
            <span class="text-slate-400 block font-semibold">Applicant Special Requirements / Notes:</span>
            <p id="m_notes" class="text-slate-800 dark:text-slate-200 italic"></p>
        </div>

        <!-- Clarification Reply from applicant if any -->
        <div id="replyContainer" class="p-3.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 text-xs space-y-1 hidden">
            <span class="text-purple-600 dark:text-purple-300 font-bold flex items-center gap-1.5">
                <i class="fa-solid fa-reply"></i> Applicant Clarification Received:
            </span>
            <p id="m_reply" class="text-purple-900 dark:text-purple-200"></p>
        </div>

        <!-- Previous Reviewer Notes if any -->
        <div id="reviewNotesContainer" class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs space-y-1 hidden">
            <span class="text-amber-700 dark:text-amber-400 font-bold">Reviewer Logged Remarks:</span>
            <p id="m_rev_notes" class="text-amber-800 dark:text-amber-200"></p>
        </div>

        <!-- Review Actions Box -->
        <div class="border-t border-slate-100 dark:border-slate-800 pt-5 space-y-4">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Review & Provisioning Actions</h4>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Approve Button -->
                <form method="POST" action="index.php?action=developer_approve_registration" onsubmit="return confirm('Approve and provision this hospital tenant? This will create an active hospital workspace and assign the applicant as initial Hospital Super Admin.')">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="request_id" id="act_approve_id">
                    <button type="submit" id="btnApprove" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-check-to-slot"></i>
                        <span>Approve & Provision Hospital</span>
                    </button>
                </form>

                <!-- Request More Info Button -->
                <button type="button" onclick="showMoreInfoBox()" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-semibold shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>Request More Info</span>
                </button>

                <!-- Reject Button -->
                <button type="button" onclick="showRejectBox()" class="px-4 py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-ban"></i>
                    <span>Reject Application</span>
                </button>
            </div>

            <!-- Dynamic Reason / More Info Form -->
            <div id="actionInputBox" class="hidden p-4 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                <form method="POST" id="actionForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="request_id" id="act_input_id">

                    <label id="actionInputLabel" class="block text-xs font-semibold text-slate-800 dark:text-white"></label>
                    <textarea name="reason" id="actionReasonText" rows="3" required class="w-full px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white outline-none"></textarea>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick="hideActionBox()" class="px-3 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium">Cancel</button>
                        <button type="submit" id="actionSubmitBtn" class="px-4 py-1.5 rounded-lg text-xs font-bold text-white"></button>
                    </div>
                </form>
            </div>

        </div>

    </div>
</div>

<script>
let currentModalReq = null;

function openReviewModal(req) {
    currentModalReq = req;
    document.getElementById('m_code').textContent = '#' + req.tracking_code + ' (' + req.status + ')';
    document.getElementById('m_hosp_name').textContent = req.hospital_name;
    document.getElementById('m_app_name').textContent = req.applicant_name;
    document.getElementById('m_app_desig').textContent = req.applicant_designation || 'Hospital Owner';
    document.getElementById('m_app_email').textContent = req.applicant_email;
    document.getElementById('m_app_phone').textContent = req.applicant_phone;

    document.getElementById('m_type').textContent = req.hospital_type;
    document.getElementById('m_license').textContent = req.license_number || 'Not Provided';
    document.getElementById('m_loc').textContent = req.city + ', ' + req.country;
    document.getElementById('m_beds').textContent = req.bed_count ? req.bed_count + ' Beds' : 'Not Specified';
    document.getElementById('m_address').textContent = req.address;

    document.getElementById('act_approve_id').value = req.id;
    document.getElementById('act_input_id').value = req.id;

    // Notes
    if (req.notes) {
        document.getElementById('m_notes').textContent = req.notes;
        document.getElementById('notesContainer').classList.remove('hidden');
    } else {
        document.getElementById('notesContainer').classList.add('hidden');
    }

    // Reply
    if (req.applicant_reply) {
        document.getElementById('m_reply').textContent = req.applicant_reply;
        document.getElementById('replyContainer').classList.remove('hidden');
    } else {
        document.getElementById('replyContainer').classList.add('hidden');
    }

    // Developer notes
    if (req.developer_notes) {
        document.getElementById('m_rev_notes').textContent = req.developer_notes;
        document.getElementById('reviewNotesContainer').classList.remove('hidden');
    } else {
        document.getElementById('reviewNotesContainer').classList.add('hidden');
    }

    // Toggle button state if already approved
    const btnApprove = document.getElementById('btnApprove');
    if (req.status === 'Approved') {
        btnApprove.disabled = true;
        btnApprove.className = 'px-5 py-2.5 bg-slate-300 dark:bg-slate-700 text-slate-500 rounded-xl text-xs font-bold cursor-not-allowed';
    } else {
        btnApprove.disabled = false;
        btnApprove.className = 'px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/30 transition-all flex items-center gap-2';
    }

    hideActionBox();
    document.getElementById('reviewModal').classList.remove('hidden');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
}

function showMoreInfoBox() {
    const box = document.getElementById('actionInputBox');
    const form = document.getElementById('actionForm');
    const label = document.getElementById('actionInputLabel');
    const btn = document.getElementById('actionSubmitBtn');

    form.action = 'index.php?action=developer_request_more_info';
    label.textContent = 'Specify Additional Information or Document Required:';
    btn.textContent = 'Send Information Request to Applicant';
    btn.className = 'px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-purple-600 hover:bg-purple-500';
    box.classList.remove('hidden');
}

function showRejectBox() {
    const box = document.getElementById('actionInputBox');
    const form = document.getElementById('actionForm');
    const label = document.getElementById('actionInputLabel');
    const btn = document.getElementById('actionSubmitBtn');

    form.action = 'index.php?action=developer_reject_registration';
    label.textContent = 'Reason for Rejection (Recorded in Audit Trail):';
    btn.textContent = 'Confirm Rejection';
    btn.className = 'px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-rose-600 hover:bg-rose-500';
    box.classList.remove('hidden');
}

function hideActionBox() {
    document.getElementById('actionInputBox').classList.add('hidden');
}

// Auto-open if query param passed
document.addEventListener('DOMContentLoaded', () => {
    const targetId = <?= json_encode($activeRequestId) ?>;
    if (targetId > 0) {
        const rows = <?= json_encode($requests) ?>;
        const target = rows.find(r => r.id == targetId);
        if (target) openReviewModal(target);
    }
});
</script>
