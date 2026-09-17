-- ============================================================================
-- HOSPITAL MANAGEMENT SYSTEM (HMS) - SEED DATA
-- Default Passwords for all accounts: Admin@123
-- Hash: $2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG
-- ============================================================================

USE `hms_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Hospital Profile
TRUNCATE TABLE `hospital_profiles`;
INSERT INTO `hospital_profiles` 
(`id`, `hospital_name`, `tagline`, `license_number`, `tax_number`, `address`, `city`, `country`, `phone`, `email`, `emergency_hotline`, `currency_symbol`, `default_tax_rate`, `time_zone`)
VALUES 
(1, 'Hayat Medicare Teaching Hospital', 'Compassionate Care, Advanced Clinical Excellence', 'MED-REG-2026-994', 'NTN-8839201-4', 'Sector B-3, Phase 5, Hayatabad', 'Peshawar', 'Pakistan', '+92 91 5892001', 'info@hayatmedicare.org', '+92 91 1122334', 'PKR ', 0.00, 'Asia/Karachi');

-- 2. Departments
TRUNCATE TABLE `departments`;
INSERT INTO `departments` (`id`, `department_name`, `code`, `type`, `description`, `status`) VALUES
(1, 'Administration', 'ADM', 'Administrative', 'Hospital Executive Management & IT', 'Active'),
(2, 'General Medicine', 'MED', 'Clinical', 'Internal medicine, chronic conditions and adult care', 'Active'),
(3, 'Cardiology', 'CARD', 'Clinical', 'Heart diseases, ECG, echo and cardiac catheterization', 'Active'),
(4, 'Pediatrics', 'PED', 'Clinical', 'Infant, child and adolescent health', 'Active'),
(5, 'General Surgery', 'SURG', 'Clinical', 'Laparoscopic, trauma and elective surgeries', 'Active'),
(6, 'Obstetrics & Gynecology', 'OBGYN', 'Clinical', 'Maternal health, labor room and women care', 'Active'),
(7, 'Emergency & Trauma (ER)', 'ER', 'Clinical', '24/7 Acute trauma, resuscitation and triage', 'Active'),
(8, 'Pathology & Laboratory', 'LAB', 'Ancillary', 'Hematology, biochemistry, microbiology & serology', 'Active'),
(9, 'Radiology & Imaging', 'RAD', 'Ancillary', 'Digital X-Ray, Ultrasound, CT Scan & MRI', 'Active'),
(10, 'Inpatient & Wards (IPD)', 'IPD', 'Support', 'Ward care, nursing stations and bed allocation', 'Active'),
(11, 'Pharmacy & Formulary', 'PHARM', 'Ancillary', 'Inpatient dispensing, outpatient pharmacy, formulary', 'Active'),
(12, 'Finance & Billing', 'FIN', 'Administrative', 'Cashier desk, revenue cycle, insurance & claims', 'Active');

-- 3. Roles
TRUNCATE TABLE `roles`;
INSERT INTO `roles` (`id`, `role_name`, `slug`, `description`, `is_system_role`) VALUES
(1, 'Super Administrator', 'super_admin', 'Full unrestricted access to system configurations, users and audit logs', 1),
(2, 'Hospital Administrator', 'hospital_admin', 'Executive management of hospital operations and departments', 1),
(3, 'Consultant Doctor', 'doctor', 'Outpatient consultation, clinical documentation, CPOE orders & rounding', 1),
(4, 'Staff Nurse', 'nurse', 'Inpatient bed care, vitals charting, nursing notes and MAR', 1),
(5, 'Pharmacist', 'pharmacist', 'Prescription review, drug dispensing, batch and inventory management', 1),
(6, 'Laboratory Technician', 'lab_technician', 'Specimen intake, analyzer operations and lab result entry', 1),
(7, 'Billing Cashier', 'cashier', 'Patient invoice generation, payments, refunds and cash drawer balancing', 1),
(8, 'Front Desk Receptionist', 'receptionist', 'Patient registration, appointments booking and token queue check-in', 1);

-- 4. Users (All pass: Admin@123)
TRUNCATE TABLE `users`;
INSERT INTO `users` 
(`id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role_id`, `department_id`, `designation`, `qualification`, `consultation_fee`, `status`)
VALUES
(1, 'superadmin', 'admin@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Engr. Tariq Farooq', '+92 300 1234567', 1, 1, 'Chief Technology Officer', 'MS Software Systems', 0.00, 'Active'),
(2, 'dr.tariq', 'dr.tariq@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Prof. Dr. Tariq Shah', '+92 333 9876543', 3, 3, 'Senior Interventional Cardiologist', 'MBBS, FCPS (Cardiology), FACC', 2500.00, 'Active'),
(3, 'dr.ayesha', 'dr.ayesha@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Dr. Ayesha Rehman', '+92 321 5551234', 3, 2, 'Consultant Physician', 'MBBS, MRCP (UK)', 2000.00, 'Active'),
(4, 'dr.bilal', 'dr.bilal@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Dr. Bilal Khan', '+92 345 8887766', 3, 4, 'Consultant Pediatrician', 'MBBS, FCPS (Pediatrics)', 1800.00, 'Active'),
(5, 'nurse.fatima', 'fatima.nurse@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Fatima Noor, RN', '+92 312 4443322', 4, 10, 'Head Ward Incharge', 'BS Nursing, PNC Certified', 0.00, 'Active'),
(6, 'pharm.kamran', 'kamran.pharm@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Kamran Akram', '+92 313 7776655', 5, 11, 'Chief Pharmacist', 'Pharm-D, RPh', 0.00, 'Active'),
(7, 'lab.usman', 'usman.lab@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Dr. Usman Ghani', '+92 315 9998877', 6, 8, 'Senior Clinical Pathologist', 'MBBS, M.Phil (Hematology)', 0.00, 'Active'),
(8, 'cashier.hassan', 'hassan.billing@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Hassan Raza', '+92 334 2221100', 7, 12, 'Senior Billing Executive', 'B.Com, Accounts Specialist', 0.00, 'Active'),
(9, 'reception.sana', 'sana.rec@hayatmedicare.org', '$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG', 'Sana Gul', '+92 336 1122334', 8, 1, 'Front Desk Lead Officer', 'BBA, Patient Relations', 0.00, 'Active');

-- 5. Wards, Rooms & Beds
TRUNCATE TABLE `wards`;
INSERT INTO `wards` (`id`, `ward_name`, `code`, `ward_type`, `floor_number`, `total_capacity`, `status`) VALUES
(1, 'Male Medical General Ward', 'MMW-1', 'General_Male', '1st Floor - Wing A', 10, 'Active'),
(2, 'Female Medical General Ward', 'FMW-1', 'General_Female', '1st Floor - Wing B', 10, 'Active'),
(3, 'Intensive Care Unit (ICU)', 'ICU-3', 'ICU', '3rd Floor - Critical Block', 6, 'Active'),
(4, 'Executive Private Suites', 'PVT-2', 'Private', '2nd Floor - VIP Wing', 5, 'Active'),
(5, 'Emergency Observation Bay', 'ER-BAY', 'Emergency_Bay', 'Ground Floor - Trauma Center', 8, 'Active');

TRUNCATE TABLE `rooms`;
INSERT INTO `rooms` (`id`, `ward_id`, `room_number`, `room_type`, `daily_rate`) VALUES
(1, 1, 'Ward-101', 'General Ward Shared', 1500.00),
(2, 2, 'Ward-102', 'General Ward Shared', 1500.00),
(3, 3, 'ICU-Room-01', 'ICU Negative Pressure', 8500.00),
(4, 4, 'Suite-201', 'VIP Single Deluxe Suite', 6000.00),
(5, 4, 'Suite-202', 'VIP Single Deluxe Suite', 6000.00),
(6, 5, 'ER-Triage-A', 'Acute Trauma Bay', 2500.00);

TRUNCATE TABLE `beds`;
INSERT INTO `beds` (`id`, `room_id`, `bed_number`, `bed_type`, `daily_tariff`, `status`, `current_encounter_id`) VALUES
(1, 1, 'MMW-B1', 'Standard', 1500.00, 'Occupied', NULL),
(2, 1, 'MMW-B2', 'Standard', 1500.00, 'Available', NULL),
(3, 1, 'MMW-B3', 'Standard', 1500.00, 'Available', NULL),
(4, 1, 'MMW-B4', 'Standard', 1500.00, 'Cleaning', NULL),
(5, 2, 'FMW-B1', 'Standard', 1500.00, 'Available', NULL),
(6, 2, 'FMW-B2', 'Standard', 1500.00, 'Available', NULL),
(7, 3, 'ICU-B1', 'ICU_Ventilated', 8500.00, 'Occupied', NULL),
(8, 3, 'ICU-B2', 'ICU_Ventilated', 8500.00, 'Available', NULL),
(9, 4, 'PVT-201', 'Electric', 6000.00, 'Reserved', NULL),
(10, 5, 'PVT-202', 'Electric', 6000.00, 'Available', NULL),
(11, 6, 'ER-T1', 'Emergency_Trolley', 2500.00, 'Available', NULL),
(12, 6, 'ER-T2', 'Emergency_Trolley', 2500.00, 'Occupied', NULL);

-- 6. Drug Categories, Items & Batches
TRUNCATE TABLE `drug_categories`;
INSERT INTO `drug_categories` (`id`, `category_name`, `description`) VALUES
(1, 'Antibiotics & Antimicrobials', 'Broad spectrum oral & intravenous antibiotics'),
(2, 'Cardiovascular & Antihypertensives', 'Blood pressure, statins, antiplatelet and heart drugs'),
(3, 'Analgesics & Antipyretics', 'Pain relievers, NSAIDs and fever management'),
(4, 'Gastrointestinal & Antacids', 'PPIs, H2 blockers, antiemetics and antidiarrheals'),
(5, 'Antidiabetics & Endocrine', 'Oral hypoglycemic agents and insulin formulations'),
(6, 'Respiratory & Bronchodilators', 'Inhalers, mucolytics and bronchodilators');

TRUNCATE TABLE `drug_items`;
INSERT INTO `drug_items` 
(`id`, `category_id`, `brand_name`, `generic_name`, `dosage_form`, `strength`, `reorder_level`, `unit_of_measure`, `requires_prescription`, `is_controlled_substance`, `status`)
VALUES
(1, 3, 'Panadol 500mg', 'Paracetamol', 'Tablet', '500mg', 100, 'Box of 200', 0, 0, 'Active'),
(2, 1, 'Augmentin 625mg', 'Amoxicillin + Clavulanic Acid', 'Tablet', '625mg', 50, 'Pack of 14', 1, 0, 'Active'),
(3, 4, 'Risek 40mg', 'Omeprazole', 'Capsule', '40mg', 60, 'Pack of 14', 1, 0, 'Active'),
(4, 2, 'Loprin 75mg', 'Aspirin (Low Dose)', 'Tablet', '75mg', 80, 'Box of 30', 0, 0, 'Active'),
(5, 2, 'Norvasc 5mg', 'Amlodipine Besylate', 'Tablet', '5mg', 50, 'Pack of 30', 1, 0, 'Active'),
(6, 5, 'Glucophage 500mg', 'Metformin HCl', 'Tablet', '500mg', 75, 'Box of 50', 1, 0, 'Active'),
(7, 1, 'Rocephin 1g IV', 'Ceftriaxone Sodium', 'Injection', '1000mg', 30, 'Vial', 1, 0, 'Active'),
(8, 3, 'Toradol 30mg', 'Ketorolac Tromethamine', 'Injection', '30mg/ml', 25, 'Ampoule', 1, 0, 'Active'),
(9, 6, 'Ventolin Inhaler', 'Salbutamol Sulfate', 'Inhaler', '100mcg/puff', 20, 'Canister (200 doses)', 1, 0, 'Active');

TRUNCATE TABLE `drug_batches`;
INSERT INTO `drug_batches` 
(`id`, `drug_item_id`, `batch_number`, `manufacturer`, `mfg_date`, `expiry_date`, `unit_purchase_price`, `unit_sale_price`, `initial_quantity`, `current_quantity`, `status`)
VALUES
(1, 1, 'BT-PAN-2401', 'GSK Pakistan', '2025-01-10', '2027-01-09', 2.00, 3.50, 2000, 1850, 'Active'),
(2, 2, 'BT-AUG-2399', 'GSK Pakistan', '2025-02-15', '2026-11-30', 38.00, 52.00, 500, 420, 'Active'),
(3, 3, 'BT-RSK-9901', 'Getz Pharma', '2025-03-01', '2027-02-28', 25.00, 36.00, 800, 710, 'Active'),
(4, 4, 'BT-LOP-1120', 'Highnoon Laboratories', '2024-11-01', '2026-10-31', 1.80, 3.00, 1500, 1400, 'Active'),
(5, 5, 'BT-NOR-5521', 'Pfizer Pakistan', '2025-01-05', '2027-01-04', 12.00, 18.00, 600, 550, 'Active'),
(6, 6, 'BT-GLU-7732', 'Merck Marker', '2025-04-10', '2027-04-09', 4.50, 7.50, 1000, 920, 'Active'),
(7, 7, 'BT-ROC-0012', 'Roche Pakistan', '2025-02-20', '2026-08-31', 280.00, 390.00, 200, 175, 'Active'),
(8, 8, 'BT-TOR-4401', 'Atco Laboratories', '2025-05-15', '2027-05-14', 45.00, 65.00, 300, 280, 'Active');

-- 7. Lab Categories, Tests & Parameters
TRUNCATE TABLE `lab_categories`;
INSERT INTO `lab_categories` (`id`, `category_name`, `code`) VALUES
(1, 'Clinical Hematology', 'HEM'),
(2, 'Clinical Biochemistry', 'BIO'),
(3, 'Chemical Pathology & Urine', 'CP'),
(4, 'Serology & Immunology', 'SERO');

TRUNCATE TABLE `lab_tests`;
INSERT INTO `lab_tests` (`id`, `category_id`, `test_name`, `test_code`, `specimen_type`, `standard_price`, `turnaround_hours`, `status`) VALUES
(1, 1, 'Complete Blood Count (CBC with ESR)', 'CBC-01', 'Blood', 850.00, 4, 'Active'),
(2, 2, 'Fasting Blood Glucose (FBG)', 'FBG-01', 'Plasma', 350.00, 2, 'Active'),
(3, 2, 'Renal Function Test (Urea, Creatinine)', 'RFT-01', 'Serum', 1100.00, 6, 'Active'),
(4, 2, 'Liver Function Test (LFT Profile)', 'LFT-01', 'Serum', 1400.00, 6, 'Active'),
(5, 2, 'Lipid Profile (Cholesterol, HDL, LDL, Triglycerides)', 'LIP-01', 'Serum', 1600.00, 8, 'Active'),
(6, 3, 'Urine Routine Examination (Urine R/E)', 'URE-01', 'Urine', 450.00, 2, 'Active'),
(7, 4, 'Hepatitis B Surface Antigen (HBsAg Rapid)', 'HBS-01', 'Serum', 750.00, 2, 'Active');

TRUNCATE TABLE `lab_test_parameters`;
INSERT INTO `lab_test_parameters` 
(`id`, `lab_test_id`, `parameter_name`, `unit`, `reference_range`, `normal_min`, `normal_max`, `critical_low`, `critical_high`) 
VALUES
(1, 1, 'Hemoglobin (Hb)', 'g/dL', '13.0 - 17.0 (M) / 12.0 - 15.5 (F)', 12.00, 17.00, 7.00, 20.00),
(2, 1, 'Total Leukocyte Count (TLC)', 'x10^3/uL', '4.0 - 11.0', 4.00, 11.00, 2.00, 30.00),
(3, 1, 'Platelet Count', 'x10^3/uL', '150 - 450', 150.00, 450.00, 50.00, 1000.00),
(4, 1, 'Erythrocyte Sedimentation Rate (ESR)', 'mm/1st hr', '0 - 15', 0.00, 15.00, 0.00, 80.00),
(5, 2, 'Fasting Plasma Glucose', 'mg/dL', '70 - 100 (Normal), 100 - 125 (Pre-diabetic)', 70.00, 100.00, 45.00, 400.00),
(6, 3, 'Serum Creatinine', 'mg/dL', '0.6 - 1.2', 0.60, 1.20, 0.40, 5.00),
(7, 3, 'Blood Urea', 'mg/dL', '15 - 45', 15.00, 45.00, 10.00, 120.00),
(8, 4, 'Serum Bilirubin (Total)', 'mg/dL', '0.2 - 1.2', 0.20, 1.20, 0.10, 8.00),
(9, 4, 'ALT (SGPT)', 'U/L', '10 - 45', 10.00, 45.00, 5.00, 300.00),
(10, 5, 'Total Serum Cholesterol', 'mg/dL', 'Desirable < 200', 120.00, 200.00, 80.00, 350.00);

-- 8. Tariff Services
TRUNCATE TABLE `tariff_services`;
INSERT INTO `tariff_services` (`id`, `department_id`, `service_name`, `service_code`, `category`, `standard_price`, `is_active`) VALUES
(1, 2, 'General Physician Consultation', 'CONS-GP', 'Consultation', 1500.00, 1),
(2, 3, 'Specialist Cardiology Consultation', 'CONS-CARD', 'Consultation', 2500.00, 1),
(3, 4, 'Pediatric Specialist Consultation', 'CONS-PED', 'Consultation', 1800.00, 1),
(4, 7, 'Emergency Trauma Registration & Triage', 'ER-TRIAGE', 'Emergency', 1000.00, 1),
(5, 3, '12-Lead Electrocardiogram (ECG)', 'DIAG-ECG', 'Procedure', 800.00, 1),
(6, 10, 'General Ward Daily Bed & Nursing Care', 'BED-GEN', 'Bed_Charge', 1500.00, 1),
(7, 10, 'ICU Ventilated Bed & Critical Nursing Care', 'BED-ICU', 'Bed_Charge', 8500.00, 1),
(8, 10, 'VIP Private Deluxe Suite Daily Tariff', 'BED-PVT', 'Bed_Charge', 6000.00, 1),
(9, 5, 'Wound Debridement & Minor Surgical Dressing', 'PROC-DRESS', 'Procedure', 1200.00, 1),
(10, 7, 'Nebulization Session', 'PROC-NEB', 'Procedure', 400.00, 1);

-- 9. Sample Patients
TRUNCATE TABLE `patients`;
INSERT INTO `patients` 
(`id`, `mrn`, `first_name`, `last_name`, `gender`, `dob`, `age`, `blood_group`, `marital_status`, `national_id`, `phone_primary`, `phone_secondary`, `email`, `address`, `city`, `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_phone`, `registered_by`, `status`)
VALUES
(1, 'MRN-2026-0001', 'Muhammad', 'Irfan', 'Male', '1982-04-12', 44, 'B+', 'Married', '17301-1234567-1', '+92 301 5558899', NULL, 'irfan82@gmail.com', 'House 45, Street 8, University Town', 'Peshawar', 'Zainab Irfan', 'Spouse', '+92 301 5558890', 9, 'Active'),
(2, 'MRN-2026-0002', 'Zahra', 'Bibi', 'Female', '1995-09-24', 30, 'O+', 'Married', '17301-7654321-2', '+92 332 4441122', NULL, 'zahra.bibi@yahoo.com', 'Flat 12, Gulberg Green Apartments', 'Peshawar', 'Farhan Ali', 'Brother', '+92 333 9998877', 9, 'Active'),
(3, 'MRN-2026-0003', 'Shahid', 'Afridi Khan', 'Male', '1968-11-03', 57, 'A+', 'Married', '17201-9988776-3', '+92 344 7771234', NULL, 'shahid.afridi@hotmail.com', 'Village Kheshgi Payan', 'Nowshera', 'Asad Khan', 'Son', '+92 345 1122334', 9, 'Active');

-- 10. Patient Allergies
TRUNCATE TABLE `patient_allergies`;
INSERT INTO `patient_allergies` (`id`, `patient_id`, `allergy_type`, `allergen_name`, `severity`, `reaction_details`, `recorded_by`) VALUES
(1, 1, 'Drug', 'Penicillin', 'Severe', 'Anaphylactic rash and bronchospasm in 2018', 2),
(2, 3, 'Drug', 'Sulfa Drugs', 'Moderate', 'Severe urticaria and periorbital edema', 3);

-- 11. Encounters
TRUNCATE TABLE `encounters`;
INSERT INTO `encounters` 
(`id`, `encounter_number`, `patient_id`, `encounter_type`, `department_id`, `attending_doctor_id`, `admission_datetime`, `discharge_datetime`, `encounter_status`, `triage_level`, `primary_diagnosis`, `created_by`)
VALUES
(1, 'ENC-2026-0001', 1, 'OPD', 3, 2, '2026-09-17 09:30:00', NULL, 'In-Progress', 'Routine', 'Essential Hypertension & Angina Pectoris', 9),
(2, 'ENC-2026-0002', 2, 'OPD', 2, 3, '2026-09-17 10:15:00', '2026-09-17 11:00:00', 'Completed', 'Routine', 'Acute Upper Respiratory Tract Infection', 9),
(3, 'ENC-2026-0003', 3, 'IPD', 2, 3, '2026-09-16 14:00:00', NULL, 'Admitted', 'Routine', 'Type 2 Diabetes Mellitus with Diabetic Foot Ulcer', 9);

-- 12. Clinical Vitals
TRUNCATE TABLE `clinical_vitals`;
INSERT INTO `clinical_vitals` 
(`id`, `encounter_id`, `patient_id`, `recorded_by`, `temperature_celsius`, `pulse_rate_bpm`, `systolic_bp`, `diastolic_bp`, `respiratory_rate`, `spo2_percentage`, `blood_glucose_mg_dl`, `height_cm`, `weight_kg`, `bmi`, `pain_scale`, `notes`)
VALUES
(1, 1, 1, 5, 36.8, 88, 150, 95, 18, 98, 142, 175.0, 84.5, 27.6, 3, 'Slight exertional chest discomfort'),
(2, 2, 2, 5, 38.4, 96, 115, 75, 20, 99, 95, 160.0, 58.0, 22.7, 4, 'Fever and sore throat since 2 days'),
(3, 3, 3, 5, 37.2, 82, 135, 85, 16, 97, 210, 170.0, 78.0, 27.0, 5, 'Right foot 1st metatarsal ulcer with cellulitis');

-- 13. Consultation Notes
TRUNCATE TABLE `consultation_notes`;
INSERT INTO `consultation_notes` 
(`id`, `encounter_id`, `doctor_id`, `chief_complaint`, `history_of_present_illness`, `past_medical_history`, `physical_examination`, `provisional_diagnosis`, `icd10_code`, `clinical_advice`, `follow_up_date`)
VALUES
(1, 1, 2, 'Chest heaviness on brisk walking and shortness of breath for past 2 weeks.', 'Patient reports retrosternal tightness radiating to left shoulder on physical exertion, relieved by 5 minutes rest. No syncope.', 'Hypertensive for 4 years on irregular medication. Known penicillin allergy.', 'S1, S2 heard. No murmurs. Chest clear bilaterally. Peripheral pulses palpable.', 'Ischemic Heart Disease (Stable Angina) & Stage 2 Hypertension', 'I20.9', 'Low salt diet, avoid strenuous exertion, repeat ECG and fasting lipid profile.', '2026-09-24');

-- 14. Prescriptions & Items
TRUNCATE TABLE `prescriptions`;
INSERT INTO `prescriptions` (`id`, `prescription_number`, `encounter_id`, `doctor_id`, `status`, `clinical_notes`)
VALUES
(1, 'RX-2026-0001', 1, 2, 'Pending', 'Strict compliance with antihypertensive therapy');

TRUNCATE TABLE `prescription_items`;
INSERT INTO `prescription_items` 
(`id`, `prescription_id`, `drug_item_id`, `dosage`, `frequency`, `duration_days`, `route`, `instructions`, `quantity_prescribed`, `quantity_dispensed`)
VALUES
(1, 1, 4, '1 Tab (75mg)', 'OD (Morning)', 30, 'Oral', 'After breakfast daily', 30, 0),
(2, 1, 5, '1 Tab (5mg)', 'OD (Night)', 30, 'Oral', 'At bedtime', 30, 0),
(3, 1, 3, '1 Cap (40mg)', 'OD (Morning)', 14, 'Oral', '30 minutes before breakfast', 14, 0);

-- 15. Lab Orders
TRUNCATE TABLE `lab_orders`;
INSERT INTO `lab_orders` (`id`, `order_number`, `encounter_id`, `ordered_by`, `order_datetime`, `priority`, `clinical_notes`, `status`)
VALUES
(1, 'LAB-2026-0001', 1, 2, '2026-09-17 09:45:00', 'Routine', 'Check baseline lipid status and fasting glucose', 'Sample_Collected');

TRUNCATE TABLE `lab_order_items`;
INSERT INTO `lab_order_items` (`id`, `lab_order_id`, `lab_test_id`, `sample_barcode`, `sample_collected_at`, `collected_by`, `item_status`)
VALUES
(1, 1, 2, 'BC-2026-0091', '2026-09-17 10:00:00', 7, 'Collected'),
(2, 1, 5, 'BC-2026-0092', '2026-09-17 10:00:00', 7, 'Collected');

-- 16. Inpatient Admissions & Bed Allocations
TRUNCATE TABLE `admissions`;
INSERT INTO `admissions` 
(`id`, `encounter_id`, `patient_id`, `admitting_doctor_id`, `admission_reason`, `initial_bed_id`, `current_bed_id`, `admission_datetime`, `expected_discharge_date`, `status`)
VALUES
(1, 3, 3, 3, 'Diabetic foot ulcer requiring intravenous antibiotic therapy and wound dressing', 1, 1, '2026-09-16 14:00:00', '2026-09-21', 'Admitted');

TRUNCATE TABLE `bed_allocations`;
INSERT INTO `bed_allocations` (`id`, `admission_id`, `bed_id`, `allocated_at`, `allocated_by`)
VALUES
(1, 1, 1, '2026-09-16 14:05:00', 5);

UPDATE `beds` SET `status` = 'Occupied', `current_encounter_id` = 3 WHERE `id` = 1;

-- 17. Sample Bills & Payments
TRUNCATE TABLE `bills`;
INSERT INTO `bills` 
(`id`, `bill_number`, `encounter_id`, `patient_id`, `bill_type`, `bill_date`, `gross_amount`, `discount_amount`, `tax_amount`, `net_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_by`)
VALUES
(1, 'INV-2026-0001', 1, 1, 'OPD', '2026-09-17 09:35:00', 4450.00, 0.00, 0.00, 4450.00, 2500.00, 1950.00, 'Partially-Paid', 8);

TRUNCATE TABLE `bill_items`;
INSERT INTO `bill_items` 
(`id`, `bill_id`, `item_type`, `reference_id`, `description`, `quantity`, `unit_price`, `subtotal`, `discount`, `net_price`)
VALUES
(1, 1, 'Consultation', 2, 'Specialist Cardiology Consultation - Prof. Dr. Tariq Shah', 1, 2500.00, 2500.00, 0.00, 2500.00),
(2, 1, 'Lab_Test', 2, 'Fasting Blood Glucose (FBG)', 1, 350.00, 350.00, 0.00, 350.00),
(3, 1, 'Lab_Test', 5, 'Lipid Profile (Cholesterol, HDL, LDL, Triglycerides)', 1, 1600.00, 1600.00, 0.00, 1600.00);

TRUNCATE TABLE `payments`;
INSERT INTO `payments` 
(`id`, `receipt_number`, `bill_id`, `patient_id`, `amount_paid`, `payment_method`, `transaction_reference`, `payment_datetime`, `received_by`)
VALUES
(1, 'RCPT-2026-0001', 1, 1, 2500.00, 'Cash', 'COUNTER-DRAWER-01', '2026-09-17 09:36:00', 8);

SET FOREIGN_KEY_CHECKS = 1;
