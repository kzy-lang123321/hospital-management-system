<?php
/**
 * Hospital Management System (HMS) - Patient Registration
 */
require_once __DIR__ . '/../../config/database.php';
$db = Database::getConnection();

// Calculate next sequence MRN
$latestId = $db->query("SELECT COALESCE(MAX(id), 0) FROM `patients`")->fetchColumn();
$nextSeq = str_pad((string)($latestId + 1), 4, '0', STR_PAD_LEFT);
$proposedMRN = 'MRN-' . date('Y') . '-' . $nextSeq;

// Fetch active doctors for instant appointment booking
$doctors = $db->query("
    SELECT u.id, u.full_name, u.consultation_fee, d.department_name 
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.id
    LEFT JOIN `departments` d ON u.department_id = d.id
    WHERE r.slug = 'doctor' AND u.status = 'Active'
    ORDER BY u.full_name ASC
")->fetchAll();
?>

<div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-slate-400 text-xs mb-1">
                <a href="index.php?page=patients" class="hover:text-medical-600">Patients</a>
                <span>/</span>
                <span class="text-slate-600 font-medium">New Registration</span>
            </div>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Register New Patient</h2>
        </div>
        <div class="px-3 py-1.5 bg-medical-50 border border-medical-200 rounded-lg text-xs">
            <span class="text-slate-500 font-medium">Assigned MRN:</span>
            <strong class="font-mono text-medical-800 font-bold ml-1"><?= $proposedMRN ?></strong>
        </div>
    </div>

    <!-- Live Duplicate Patient Warning Banner (Hidden by default) -->
    <div id="duplicateAlertBanner" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl p-4 text-amber-900 shadow-sm transition-all animate-pulse-subtle">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-amber-200 text-amber-800 rounded-xl mt-0.5">
                <i class="fa-solid fa-triangle-exclamation text-base"></i>
            </div>
            <div class="flex-1 text-xs">
                <h4 class="font-bold text-sm text-amber-950 flex items-center gap-2">
                    <span>Potential Duplicate Patient Record Detected</span>
                    <span class="bg-amber-200 text-amber-900 px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-extrabold">Warning</span>
                </h4>
                <p class="mt-1 text-amber-800">
                    A patient with identical Phone / CNIC is already registered in the system:
                    <strong id="dupPatientName" class="text-slate-900 font-bold"></strong> 
                    (MRN: <strong id="dupPatientMRN" class="font-mono text-medical-800 font-bold"></strong>).
                </p>
                <div class="mt-3 flex items-center gap-3">
                    <a id="dupPatientLink" href="#" target="_blank" class="px-3 py-1.5 bg-amber-700 hover:bg-amber-800 text-white font-bold rounded-lg transition-colors flex items-center gap-1.5">
                        <i class="fa-solid fa-folder-open text-[11px]"></i>
                        <span>Open Existing Patient Profile</span>
                    </a>
                    <button type="button" onclick="document.getElementById('duplicateAlertBanner').classList.add('hidden')" class="px-3 py-1.5 bg-white border border-amber-300 text-amber-900 hover:bg-amber-100 rounded-lg font-semibold transition-colors">
                        Ignore & Proceed (New Family Member)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Form Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <form action="index.php?action=patient_store" method="POST" class="p-6 space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="mrn" value="<?= $proposedMRN ?>">

            <!-- Section 1: Demographics -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-id-card-clip text-medical-600"></i>
                    <span>Primary Demographic Details</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">First Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="first_name" required placeholder="e.g. Tariq" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Last Name / Father Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="last_name" required placeholder="e.g. Khan" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Gender <span class="text-rose-500">*</span></label>
                        <select name="gender" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Date of Birth</label>
                        <input type="date" name="dob" id="dobInput" onchange="calculateAge(this.value)" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Age (Years)</label>
                        <input type="number" name="age" id="ageInput" min="0" max="130" placeholder="e.g. 35" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Blood Group</label>
                        <select name="blood_group" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <option value="Unknown">Unknown / Not Tested</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                <option value="<?= $bg ?>"><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Marital Status</label>
                        <select name="marital_status" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">National ID / CNIC</label>
                        <input type="text" name="national_id" id="nationalIdInput" placeholder="17301-XXXXXXX-X" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                </div>
            </div>

            <!-- Section 2: Contact & Address -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-medical-600"></i>
                    <span>Contact & Location</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Primary Phone Number <span class="text-rose-500">*</span></label>
                        <input type="text" name="phone_primary" id="phonePrimaryInput" required placeholder="+92 3XX XXXXXXX" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Secondary / WhatsApp Phone</label>
                        <input type="text" name="phone_secondary" placeholder="+92 3XX XXXXXXX" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
                        <input type="email" name="email" placeholder="patient@example.com" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">City / Town</label>
                        <input type="text" name="city" value="Peshawar" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Full Residential Address</label>
                        <input type="text" name="address" placeholder="House #, Street, Mohallah / Sector" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                </div>
            </div>

            <!-- Section 3: Emergency Contact -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-phone-volume text-rose-600"></i>
                    <span>Next of Kin / Emergency Contact</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kin Full Name</label>
                        <input type="text" name="emergency_contact_name" placeholder="e.g. Asad Khan" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Relationship</label>
                        <input type="text" name="emergency_contact_relation" placeholder="e.g. Spouse / Brother / Father" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Emergency Phone</label>
                        <input type="text" name="emergency_contact_phone" placeholder="+92 3XX XXXXXXX" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                </div>
            </div>

            <!-- Section 4: Initial Medical Allergies -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                    <span>Recorded Drug or Food Allergies</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-amber-50/50 p-4 rounded-xl border border-amber-200">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Allergen Name</label>
                        <input type="text" name="allergen_name" placeholder="e.g. Penicillin, Sulfa, Aspirin" class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Allergy Type</label>
                        <select name="allergy_type" class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <option value="Drug">Drug / Pharmaceutical</option>
                            <option value="Food">Food / Nutritional</option>
                            <option value="Environmental">Environmental</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Clinical Severity</label>
                        <select name="allergy_severity" class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <option value="Mild">Mild (Rash, Itching)</option>
                            <option value="Moderate" selected>Moderate (Urticaria, Angioedema)</option>
                            <option value="Severe">Severe (Bronchospasm)</option>
                            <option value="Life-Threatening">Life-Threatening (Anaphylaxis)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 5: Instant Appointment & Token Booking Flow -->
            <div class="bg-medical-50/40 rounded-2xl border border-medical-200 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <input type="checkbox" id="bookAppointmentNow" name="book_appointment_now" value="1" onchange="toggleInstantBooking(this.checked)" class="w-4 h-4 text-medical-600 rounded border-medical-300 focus:ring-medical-500">
                        <label for="bookAppointmentNow" class="text-xs font-bold text-medical-950 cursor-pointer">
                            Book Appointment & Generate OPD Token Immediately (Fast Track Intake)
                        </label>
                    </div>
                    <span class="text-[10px] uppercase font-mono font-bold bg-medical-100 text-medical-800 px-2 py-0.5 rounded">1-Step Workflow</span>
                </div>

                <div id="instantBookingFields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-4 pt-3 border-t border-medical-200/60">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Attending Consultant Doctor <span class="text-rose-500">*</span></label>
                        <select name="instant_doctor_id" id="instantDoctorSelect" class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                            <option value="">Select Doctor...</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?= $doc['id'] ?>">
                                    <?= htmlspecialchars($doc['full_name']) ?> &mdash; <?= htmlspecialchars($doc['department_name']) ?> (<?= format_currency($doc['consultation_fee']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Appointment Date</label>
                        <input type="date" name="instant_appointment_date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Visit</label>
                        <input type="text" name="instant_reason" placeholder="e.g. Fever, Cough, Regular Follow-up" class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-lg outline-none focus:border-medical-500">
                    </div>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                <a href="index.php?page=patients" class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-medical-600 hover:bg-medical-700 text-white font-bold text-xs rounded-lg shadow-md shadow-medical-600/30 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Register & Create Patient File</span>
                </button>
            </div>

        </form>
    </div>

</div>

<script>
    function calculateAge(dobString) {
        if (!dobString) return;
        const dob = new Date(dobString);
        const diff = Date.now() - dob.getTime();
        const ageDate = new Date(diff);
        const calculatedAge = Math.abs(ageDate.getUTCFullYear() - 1970);
        if (!isNaN(calculatedAge)) {
            document.getElementById('ageInput').value = calculatedAge;
        }
    }

    function toggleInstantBooking(isChecked) {
        const fields = document.getElementById('instantBookingFields');
        const docSelect = document.getElementById('instantDoctorSelect');
        if (isChecked) {
            fields.classList.remove('hidden');
            docSelect.setAttribute('required', 'required');
        } else {
            fields.classList.add('hidden');
            docSelect.removeAttribute('required');
        }
    }

    // Debounce function for live duplicate lookup
    let duplicateTimer = null;
    function triggerDuplicateCheck() {
        clearTimeout(duplicateTimer);
        duplicateTimer = setTimeout(() => {
            const phone = document.getElementById('phonePrimaryInput').value.trim();
            const cnic = document.getElementById('nationalIdInput').value.trim();
            if (phone.length < 5 && cnic.length < 5) return;

            fetch(`index.php?action=check_patient_duplicate&phone=${encodeURIComponent(phone)}&cnic=${encodeURIComponent(cnic)}`)
                .then(res => res.json())
                .then(data => {
                    const banner = document.getElementById('duplicateAlertBanner');
                    if (data.exists && data.patient) {
                        document.getElementById('dupPatientName').textContent = data.patient.name;
                        document.getElementById('dupPatientMRN').textContent = data.patient.mrn;
                        document.getElementById('dupPatientLink').href = `index.php?page=patient_profile&id=${data.patient.id}`;
                        banner.classList.remove('hidden');
                    } else {
                        banner.classList.add('hidden');
                    }
                })
                .catch(err => console.error('Duplicate check error:', err));
        }, 400);
    }

    document.getElementById('phonePrimaryInput').addEventListener('input', triggerDuplicateCheck);
    document.getElementById('nationalIdInput').addEventListener('input', triggerDuplicateCheck);
</script>
