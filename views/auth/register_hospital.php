<?php
/**
 * Hospital Management System (HMS) - Public Hospital Registration Portal
 */
require_once __DIR__ . '/../../config/app.php';
$flash = get_flash();
$hProfile = hospital_profile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Your Hospital — <?= htmlspecialchars(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-slate-100 min-h-screen py-8 px-4 sm:px-6">

    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Header Branding -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-teal-600 to-emerald-500 text-white text-3xl shadow-xl shadow-teal-500/20 mb-1">
                <i class="fa-solid fa-hospital"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Register Your Hospital Facility</h1>
            <p class="text-xs sm:text-sm text-slate-400 max-w-lg mx-auto">
                Deploy an independent hospital workspace with customized branding, electronic medical records, clinical workflows, and verified staff attendance.
            </p>
        </div>

        <!-- Flash Alert -->
        <?php if ($flash): ?>
        <div class="p-4 rounded-xl text-xs <?= $flash['type'] === 'danger' ? 'bg-rose-500/20 border border-rose-500/40 text-rose-200' : 'bg-emerald-500/20 border border-emerald-500/40 text-emerald-200' ?> flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-base"></i>
            <span><?= htmlspecialchars($flash['message']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Step Wizard Progress Bar -->
        <div class="bg-slate-900/80 backdrop-blur-md border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-xl">
            <div class="grid grid-cols-3 gap-2 sm:gap-4 text-center">
                <div id="stepIndicator1" class="step-indicator flex flex-col sm:flex-row items-center justify-center gap-2 p-2 rounded-xl bg-teal-500/20 text-teal-400 border border-teal-500/40 font-semibold text-xs transition-all">
                    <span class="w-6 h-6 rounded-full bg-teal-500 text-slate-950 flex items-center justify-center text-xs font-bold">1</span>
                    <span>Owner Account</span>
                </div>
                <div id="stepIndicator2" class="step-indicator flex flex-col sm:flex-row items-center justify-center gap-2 p-2 rounded-xl bg-slate-800/60 text-slate-400 border border-slate-700/60 font-semibold text-xs transition-all">
                    <span class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-xs font-bold">2</span>
                    <span>Hospital Info</span>
                </div>
                <div id="stepIndicator3" class="step-indicator flex flex-col sm:flex-row items-center justify-center gap-2 p-2 rounded-xl bg-slate-800/60 text-slate-400 border border-slate-700/60 font-semibold text-xs transition-all">
                    <span class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-xs font-bold">3</span>
                    <span>Review & Submit</span>
                </div>
            </div>
        </div>

        <!-- Multi-Step Registration Form Container -->
        <div class="bg-slate-900/90 backdrop-blur-lg border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl">
            <form id="registrationForm" method="POST" action="index.php?action=public_register_hospital" class="space-y-6">
                <?= csrf_field() ?>

                <!-- STEP 1: OWNER ACCOUNT -->
                <div id="stepContent1" class="space-y-5">
                    <div>
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-user-shield text-teal-400"></i>
                            <span>Hospital Owner & Administrator Profile</span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">This will be the root Super Admin account managing hospital configuration, staff roles, and clinical settings.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Full Name <span class="text-rose-400">*</span></label>
                            <input type="text" name="applicant_name" id="app_name" required placeholder="e.g. Dr. Arshad Khan" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Official Designation / Title</label>
                            <input type="text" name="applicant_designation" id="app_desig" placeholder="e.g. Medical Director / Hospital Owner" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Email Address (Login Identity) <span class="text-rose-400">*</span></label>
                            <input type="email" name="applicant_email" id="app_email" required placeholder="name@hospital.com" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Mobile / Phone Number <span class="text-rose-400">*</span></label>
                            <input type="tel" name="applicant_phone" id="app_phone" required placeholder="e.g. +92 300 1234567" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Master Password (Min. 8 characters) <span class="text-rose-400">*</span></label>
                            <input type="password" name="password" id="app_pwd" required minlength="8" placeholder="Create strong password" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Confirm Master Password <span class="text-rose-400">*</span></label>
                            <input type="password" name="confirm_password" id="app_pwd_conf" required minlength="8" placeholder="Re-enter password" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="button" onclick="goToStep(2)" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-teal-600/30 transition-all flex items-center gap-2">
                            <span>Next: Hospital Information</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: HOSPITAL INFORMATION -->
                <div id="stepContent2" class="space-y-5 hidden">
                    <div>
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-hospital text-teal-400"></i>
                            <span>Hospital & Facility Information</span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">Specify clinical facility details, address, licensing, and operational modules.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Official Hospital Name <span class="text-rose-400">*</span></label>
                            <input type="text" name="hospital_name" id="h_name" required placeholder="e.g. Al-Shifa Specialized Hospital" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Facility Type <span class="text-rose-400">*</span></label>
                            <select name="hospital_type" id="h_type" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white outline-none transition-all">
                                <option value="General Hospital">General Hospital (Full Service)</option>
                                <option value="Specialized Clinic">Specialized Clinic</option>
                                <option value="Medical & Surgical Center">Medical & Surgical Center</option>
                                <option value="Maternity & Child Hospital">Maternity & Child Hospital</option>
                                <option value="Diagnostic & Lab Center">Diagnostic & Lab Center</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">City <span class="text-rose-400">*</span></label>
                            <input type="text" name="city" id="h_city" required placeholder="e.g. Peshawar" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">District / Region</label>
                            <input type="text" name="district" id="h_district" placeholder="e.g. Khyber Pakhtunkhwa" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Country</label>
                            <input type="text" name="country" id="h_country" value="Pakistan" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Complete Physical Address <span class="text-rose-400">*</span></label>
                        <textarea name="address" id="h_address" rows="2" required placeholder="Building, Street, Sector, Landmark..." class="w-full px-3.5 py-2 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all"></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Healthcare License / Reg #</label>
                            <input type="text" name="license_number" id="h_license" placeholder="e.g. HCC-2026-9812" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Bed Capacity (Optional)</label>
                            <input type="number" name="bed_count" id="h_beds" min="0" placeholder="e.g. 50" class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Currency Symbol</label>
                            <input type="text" name="currency_symbol" id="h_currency" value="PKR " class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white outline-none transition-all">
                        </div>
                    </div>

                    <!-- Required Modules Selection -->
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-2">Required Modules & Clinical Units</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs text-slate-300">
                            <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/60 border border-slate-700/60 cursor-pointer hover:bg-slate-800">
                                <input type="checkbox" name="modules[patients]" checked class="rounded text-teal-600 focus:ring-0">
                                <span>Patient EMR / MRN</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/60 border border-slate-700/60 cursor-pointer hover:bg-slate-800">
                                <input type="checkbox" name="modules[appointments]" checked class="rounded text-teal-600 focus:ring-0">
                                <span>Appointments & Tokens</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/60 border border-slate-700/60 cursor-pointer hover:bg-slate-800">
                                <input type="checkbox" name="modules[clinical]" checked class="rounded text-teal-600 focus:ring-0">
                                <span>OPD Consultation</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/60 border border-slate-700/60 cursor-pointer hover:bg-slate-800">
                                <input type="checkbox" name="modules[emergency]" checked class="rounded text-teal-600 focus:ring-0">
                                <span>Emergency & ER</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/60 border border-slate-700/60 cursor-pointer hover:bg-slate-800">
                                <input type="checkbox" name="modules[ipd]" checked class="rounded text-teal-600 focus:ring-0">
                                <span>Wards & IPD Beds</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/60 border border-slate-700/60 cursor-pointer hover:bg-slate-800">
                                <input type="checkbox" name="modules[pharmacy]" checked class="rounded text-teal-600 focus:ring-0">
                                <span>Pharmacy & POS</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/60 border border-slate-700/60 cursor-pointer hover:bg-slate-800">
                                <input type="checkbox" name="modules[laboratory]" checked class="rounded text-teal-600 focus:ring-0">
                                <span>Laboratory & Tests</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/60 border border-slate-700/60 cursor-pointer hover:bg-slate-800">
                                <input type="checkbox" name="modules[attendance]" checked class="rounded text-teal-600 focus:ring-0">
                                <span>GPS Staff Attendance</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Additional Requirements or Special Instructions (Optional)</label>
                        <textarea name="notes" id="h_notes" rows="2" placeholder="e.g. Need migration assistance for existing patient records..." class="w-full px-3.5 py-2 bg-slate-800/80 border border-slate-700 focus:border-teal-500 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all"></textarea>
                    </div>

                    <div class="flex justify-between pt-4">
                        <button type="button" onclick="goToStep(1)" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition-all flex items-center gap-2">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Back to Account</span>
                        </button>
                        <button type="button" onclick="goToStep(3)" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-teal-600/30 transition-all flex items-center gap-2">
                            <span>Next: Review & Submit</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: REVIEW & SUBMIT -->
                <div id="stepContent3" class="space-y-5 hidden">
                    <div>
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-clipboard-check text-teal-400"></i>
                            <span>Review & Confirm Registration Request</span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">Please verify all information before final submission. An initial approval verification will be conducted.</p>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/70 space-y-2 text-xs">
                            <div class="font-bold text-teal-400 border-b border-slate-700/60 pb-1.5 flex items-center gap-1.5">
                                <i class="fa-solid fa-user-shield"></i> Hospital Super Admin Account
                            </div>
                            <div class="space-y-1">
                                <p><span class="text-slate-400">Name:</span> <span id="rev_name" class="font-semibold text-white">--</span></p>
                                <p><span class="text-slate-400">Designation:</span> <span id="rev_desig" class="text-white">--</span></p>
                                <p><span class="text-slate-400">Email:</span> <span id="rev_email" class="text-white font-mono">--</span></p>
                                <p><span class="text-slate-400">Phone:</span> <span id="rev_phone" class="text-white font-mono">--</span></p>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/70 space-y-2 text-xs">
                            <div class="font-bold text-teal-400 border-b border-slate-700/60 pb-1.5 flex items-center gap-1.5">
                                <i class="fa-solid fa-hospital"></i> Facility Details
                            </div>
                            <div class="space-y-1">
                                <p><span class="text-slate-400">Hospital:</span> <span id="rev_hosp" class="font-semibold text-white">--</span></p>
                                <p><span class="text-slate-400">Type:</span> <span id="rev_type" class="text-white">--</span></p>
                                <p><span class="text-slate-400">Location:</span> <span id="rev_loc" class="text-white">--</span></p>
                                <p><span class="text-slate-400">License:</span> <span id="rev_license" class="text-white">--</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Terms & Agreement Checkbox -->
                    <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="termsCheck" required class="mt-1 rounded text-teal-600 focus:ring-0">
                            <span class="text-xs text-slate-300 leading-relaxed">
                                I confirm that I am authorized to register this medical facility. I certify that all details submitted are accurate and agree to adhere to healthcare compliance, patient data privacy (PHI shielding), and standard platform terms of use.
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-between pt-4">
                        <button type="button" onclick="goToStep(2)" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition-all flex items-center gap-2">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Back to Hospital Info</span>
                        </button>
                        <button type="submit" id="submitBtn" class="px-8 py-3 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-500 hover:to-emerald-500 text-white rounded-xl text-sm font-bold shadow-xl shadow-teal-600/30 transition-all flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Submit Registration Request</span>
                        </button>
                    </div>
                </div>

            </form>
        </div>

        <!-- Footer Navigation Links -->
        <div class="flex flex-col sm:flex-row items-center justify-between text-xs text-slate-400 gap-3 px-2">
            <a href="index.php?page=login" class="hover:text-teal-400 transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Already have an account? Sign In</span>
            </a>
            <a href="index.php?page=registration_status" class="hover:text-teal-400 transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Check Your Registration Status</span>
            </a>
        </div>

    </div>

    <script>
    function goToStep(step) {
        // Validation before leaving Step 1
        if (step === 2) {
            const name = document.getElementById('app_name').value.trim();
            const email = document.getElementById('app_email').value.trim();
            const phone = document.getElementById('app_phone').value.trim();
            const pwd = document.getElementById('app_pwd').value;
            const pwdConf = document.getElementById('app_pwd_conf').value;

            if (!name || !email || !phone || !pwd) {
                alert('Please fill in all required fields in the Owner Profile.');
                return;
            }
            if (pwd.length < 8) {
                alert('Password must be at least 8 characters long.');
                return;
            }
            if (pwd !== pwdConf) {
                alert('Passwords do not match. Please re-enter.');
                return;
            }
        }

        // Validation before leaving Step 2
        if (step === 3) {
            const hName = document.getElementById('h_name').value.trim();
            const city = document.getElementById('h_city').value.trim();
            const address = document.getElementById('h_address').value.trim();

            if (!hName || !city || !address) {
                alert('Please fill in the official hospital name, city, and physical address.');
                return;
            }

            // Populate Review Summary
            document.getElementById('rev_name').textContent = document.getElementById('app_name').value;
            document.getElementById('rev_desig').textContent = document.getElementById('app_desig').value || 'Hospital Owner';
            document.getElementById('rev_email').textContent = document.getElementById('app_email').value;
            document.getElementById('rev_phone').textContent = document.getElementById('app_phone').value;

            document.getElementById('rev_hosp').textContent = document.getElementById('h_name').value;
            document.getElementById('rev_type').textContent = document.getElementById('h_type').value;
            document.getElementById('rev_loc').textContent = document.getElementById('h_city').value + ', ' + document.getElementById('h_country').value;
            document.getElementById('rev_license').textContent = document.getElementById('h_license').value || 'Not Provided';
        }

        // Toggle Views
        for (let s = 1; s <= 3; s++) {
            const content = document.getElementById('stepContent' + s);
            const indicator = document.getElementById('stepIndicator' + s);
            if (s === step) {
                content.classList.remove('hidden');
                indicator.className = 'step-indicator flex flex-col sm:flex-row items-center justify-center gap-2 p-2 rounded-xl bg-teal-500/20 text-teal-400 border border-teal-500/40 font-semibold text-xs transition-all';
                indicator.querySelector('span:first-child').className = 'w-6 h-6 rounded-full bg-teal-500 text-slate-950 flex items-center justify-center text-xs font-bold';
            } else if (s < step) {
                content.classList.add('hidden');
                indicator.className = 'step-indicator flex flex-col sm:flex-row items-center justify-center gap-2 p-2 rounded-xl bg-emerald-950/40 text-emerald-400 border border-emerald-800/40 font-semibold text-xs transition-all';
                indicator.querySelector('span:first-child').className = 'w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold';
            } else {
                content.classList.add('hidden');
                indicator.className = 'step-indicator flex flex-col sm:flex-row items-center justify-center gap-2 p-2 rounded-xl bg-slate-800/60 text-slate-400 border border-slate-700/60 font-semibold text-xs transition-all';
                indicator.querySelector('span:first-child').className = 'w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-xs font-bold';
            }
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    </script>
</body>
</html>
