-- ============================================================================
-- HOSPITAL MANAGEMENT SYSTEM (HMS) - RELATIONAL DATABASE SCHEMA
-- Target Engine: MariaDB 10.4+ / MySQL 8.0+ (InnoDB, UTF8mb4)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `hms_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hms_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- DOMAIN 1: SYSTEM, AUTHENTICATION & FACILITY CONFIGURATION
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `hospital_profiles`;
CREATE TABLE `hospital_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hospital_name` VARCHAR(150) NOT NULL,
    `tagline` VARCHAR(255) DEFAULT 'Excellence in Patient Care & Clinical Service',
    `license_number` VARCHAR(100) DEFAULT NULL,
    `tax_number` VARCHAR(100) DEFAULT NULL,
    `address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `country` VARCHAR(100) DEFAULT 'Pakistan',
    `phone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `emergency_hotline` VARCHAR(50) NOT NULL,
    `currency_symbol` VARCHAR(10) DEFAULT 'PKR ',
    `default_tax_rate` DECIMAL(5,2) DEFAULT 0.00,
    `time_zone` VARCHAR(50) DEFAULT 'Asia/Karachi',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `type` ENUM('Clinical', 'Ancillary', 'Administrative', 'Support') DEFAULT 'Clinical',
    `description` TEXT DEFAULT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_system_role` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module` VARCHAR(50) NOT NULL,
    `permission_key` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `role_id` INT NOT NULL,
    `department_id` INT DEFAULT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `qualification` VARCHAR(150) DEFAULT NULL,
    `consultation_fee` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` VARCHAR(50) DEFAULT NULL,
    `old_values` LONGTEXT DEFAULT NULL,
    `new_values` LONGTEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_event` (`user_id`, `event_type`),
    INDEX `idx_table_record` (`table_name`, `record_id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- DOMAIN 2: MASTER PATIENT INDEX & DEMOGRAPHICS
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mrn` VARCHAR(30) NOT NULL UNIQUE,
    `first_name` VARCHAR(60) NOT NULL,
    `last_name` VARCHAR(60) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `dob` DATE DEFAULT NULL,
    `age` INT DEFAULT NULL,
    `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') DEFAULT 'Unknown',
    `marital_status` ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Other') DEFAULT 'Single',
    `national_id` VARCHAR(30) DEFAULT NULL,
    `phone_primary` VARCHAR(30) NOT NULL,
    `phone_secondary` VARCHAR(30) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(60) DEFAULT 'Peshawar',
    `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
    `emergency_contact_relation` VARCHAR(50) DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(30) DEFAULT NULL,
    `registered_by` INT DEFAULT NULL,
    `status` ENUM('Active', 'Deceased', 'Archived') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_mrn` (`mrn`),
    INDEX `idx_phone` (`phone_primary`),
    INDEX `idx_national_id` (`national_id`),
    INDEX `idx_name` (`first_name`, `last_name`),
    FOREIGN KEY (`registered_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `patient_allergies`;
CREATE TABLE `patient_allergies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `allergy_type` ENUM('Drug', 'Food', 'Environmental', 'Other') DEFAULT 'Drug',
    `allergen_name` VARCHAR(100) NOT NULL,
    `severity` ENUM('Mild', 'Moderate', 'Severe', 'Life-Threatening') DEFAULT 'Moderate',
    `reaction_details` TEXT DEFAULT NULL,
    `recorded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- DOMAIN 3: ENCOUNTERS, APPOINTMENTS & OPD CLINICAL CARE
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `encounters`;
CREATE TABLE `encounters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `encounter_number` VARCHAR(30) NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `encounter_type` ENUM('OPD', 'Emergency', 'IPD') NOT NULL,
    `department_id` INT NOT NULL,
    `attending_doctor_id` INT NOT NULL,
    `admission_datetime` DATETIME NOT NULL,
    `discharge_datetime` DATETIME DEFAULT NULL,
    `encounter_status` ENUM('In-Progress', 'Completed', 'Admitted', 'Cancelled') DEFAULT 'In-Progress',
    `triage_level` ENUM('ESI-1', 'ESI-2', 'ESI-3', 'ESI-4', 'ESI-5', 'Routine') DEFAULT 'Routine',
    `primary_diagnosis` VARCHAR(255) DEFAULT NULL,
    `discharge_disposition` ENUM('Discharged_Home', 'Admitted_to_Ward', 'Admitted_to_ICU', 'Transferred', 'LAMA', 'Deceased') DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_enc_patient` (`patient_id`),
    INDEX `idx_enc_status` (`encounter_status`),
    INDEX `idx_enc_type` (`encounter_type`),
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
    FOREIGN KEY (`attending_doctor_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `appointment_number` VARCHAR(30) NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `appointment_date` DATE NOT NULL,
    `slot_time` TIME NOT NULL,
    `token_number` INT DEFAULT 1,
    `type` ENUM('New', 'Follow-up', 'Routine') DEFAULT 'New',
    `status` ENUM('Scheduled', 'Confirmed', 'Checked-In', 'In-Consultation', 'Completed', 'Cancelled', 'No-Show') DEFAULT 'Scheduled',
    `reason_for_visit` TEXT DEFAULT NULL,
    `cancellation_reason` VARCHAR(255) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_doc_date` (`doctor_id`, `appointment_date`),
    INDEX `idx_patient_apt` (`patient_id`),
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `clinical_vitals`;
CREATE TABLE `clinical_vitals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `encounter_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `recorded_by` INT NOT NULL,
    `temperature_celsius` DECIMAL(4,1) DEFAULT NULL,
    `pulse_rate_bpm` INT DEFAULT NULL,
    `systolic_bp` INT DEFAULT NULL,
    `diastolic_bp` INT DEFAULT NULL,
    `respiratory_rate` INT DEFAULT NULL,
    `spo2_percentage` INT DEFAULT NULL,
    `blood_glucose_mg_dl` INT DEFAULT NULL,
    `height_cm` DECIMAL(5,1) DEFAULT NULL,
    `weight_kg` DECIMAL(5,1) DEFAULT NULL,
    `bmi` DECIMAL(4,1) DEFAULT NULL,
    `pain_scale` TINYINT DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `consultation_notes`;
CREATE TABLE `consultation_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `encounter_id` INT NOT NULL UNIQUE,
    `doctor_id` INT NOT NULL,
    `chief_complaint` TEXT NOT NULL,
    `history_of_present_illness` TEXT DEFAULT NULL,
    `past_medical_history` TEXT DEFAULT NULL,
    `physical_examination` TEXT DEFAULT NULL,
    `provisional_diagnosis` TEXT NOT NULL,
    `icd10_code` VARCHAR(50) DEFAULT NULL,
    `clinical_advice` TEXT DEFAULT NULL,
    `follow_up_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- DOMAIN 4: WARDS, ROOMS, BEDS & IPD ADMISSIONS
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `wards`;
CREATE TABLE `wards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ward_name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `ward_type` ENUM('General_Male', 'General_Female', 'Semi-Private', 'Private', 'ICU', 'CCU', 'Emergency_Bay', 'Isolation', 'Maternity') NOT NULL,
    `floor_number` VARCHAR(20) DEFAULT '1st Floor',
    `total_capacity` INT DEFAULT 10,
    `status` ENUM('Active', 'Under_Maintenance', 'Inactive') DEFAULT 'Active'
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ward_id` INT NOT NULL,
    `room_number` VARCHAR(30) NOT NULL,
    `room_type` VARCHAR(50) DEFAULT 'Standard',
    `daily_rate` DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (`ward_id`) REFERENCES `wards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `beds`;
CREATE TABLE `beds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `bed_number` VARCHAR(30) NOT NULL,
    `bed_type` ENUM('Standard', 'Electric', 'ICU_Ventilated', 'Emergency_Trolley', 'Pediatric_Cot') DEFAULT 'Standard',
    `daily_tariff` DECIMAL(10,2) NOT NULL DEFAULT 1500.00,
    `status` ENUM('Available', 'Reserved', 'Occupied', 'Cleaning', 'Maintenance') DEFAULT 'Available',
    `current_encounter_id` INT DEFAULT NULL,
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`current_encounter_id`) REFERENCES `encounters`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `admissions`;
CREATE TABLE `admissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `encounter_id` INT NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `admitting_doctor_id` INT NOT NULL,
    `admission_reason` TEXT NOT NULL,
    `initial_bed_id` INT NOT NULL,
    `current_bed_id` INT NOT NULL,
    `admission_datetime` DATETIME NOT NULL,
    `expected_discharge_date` DATE DEFAULT NULL,
    `status` ENUM('Admitted', 'Discharged', 'Transferred') DEFAULT 'Admitted',
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
    FOREIGN KEY (`admitting_doctor_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`initial_bed_id`) REFERENCES `beds`(`id`),
    FOREIGN KEY (`current_bed_id`) REFERENCES `beds`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `bed_allocations`;
CREATE TABLE `bed_allocations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admission_id` INT NOT NULL,
    `bed_id` INT NOT NULL,
    `allocated_at` DATETIME NOT NULL,
    `released_at` DATETIME DEFAULT NULL,
    `transfer_reason` VARCHAR(255) DEFAULT NULL,
    `allocated_by` INT NOT NULL,
    FOREIGN KEY (`admission_id`) REFERENCES `admissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`bed_id`) REFERENCES `beds`(`id`),
    FOREIGN KEY (`allocated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `nursing_notes`;
CREATE TABLE `nursing_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `encounter_id` INT NOT NULL,
    `nurse_id` INT NOT NULL,
    `note_type` ENUM('Routine_Care', 'Shift_Handover', 'Doctor_Order_Executed', 'Incident_Report', 'Pain_Assessment') DEFAULT 'Routine_Care',
    `content` TEXT NOT NULL,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`nurse_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `discharge_summaries`;
CREATE TABLE `discharge_summaries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `encounter_id` INT NOT NULL UNIQUE,
    `doctor_id` INT NOT NULL,
    `admission_diagnosis` TEXT NOT NULL,
    `final_diagnosis` TEXT NOT NULL,
    `hospital_course` TEXT NOT NULL,
    `procedures_performed` TEXT DEFAULT NULL,
    `condition_at_discharge` ENUM('Cured', 'Improved', 'Stable', 'Unchanged', 'Guarded', 'Critical') DEFAULT 'Stable',
    `discharge_medications` LONGTEXT DEFAULT NULL,
    `dietary_instructions` TEXT DEFAULT NULL,
    `follow_up_instructions` TEXT DEFAULT NULL,
    `next_visit_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- DOMAIN 5: PHARMACY & MEDICATION MANAGEMENT
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `drug_categories`;
CREATE TABLE `drug_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `drug_items`;
CREATE TABLE `drug_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `brand_name` VARCHAR(100) NOT NULL,
    `generic_name` VARCHAR(150) NOT NULL,
    `dosage_form` ENUM('Tablet', 'Capsule', 'Syrup', 'Injection', 'Infusion', 'Ointment', 'Eye_Drops', 'Inhaler', 'Other') NOT NULL,
    `strength` VARCHAR(50) NOT NULL,
    `reorder_level` INT DEFAULT 50,
    `unit_of_measure` VARCHAR(30) DEFAULT 'Strip / Box',
    `requires_prescription` TINYINT(1) DEFAULT 1,
    `is_controlled_substance` TINYINT(1) DEFAULT 0,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (`category_id`) REFERENCES `drug_categories`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `drug_batches`;
CREATE TABLE `drug_batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `drug_item_id` INT NOT NULL,
    `batch_number` VARCHAR(50) NOT NULL,
    `manufacturer` VARCHAR(100) DEFAULT NULL,
    `mfg_date` DATE DEFAULT NULL,
    `expiry_date` DATE NOT NULL,
    `unit_purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `unit_sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `initial_quantity` INT NOT NULL,
    `current_quantity` INT NOT NULL,
    `status` ENUM('Active', 'Quarantined', 'Expired', 'Depleted') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_batch_exp` (`expiry_date`),
    FOREIGN KEY (`drug_item_id`) REFERENCES `drug_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `prescription_number` VARCHAR(30) NOT NULL UNIQUE,
    `encounter_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `status` ENUM('Pending', 'Partially-Dispensed', 'Dispensed', 'Cancelled') DEFAULT 'Pending',
    `clinical_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `prescription_items`;
CREATE TABLE `prescription_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `prescription_id` INT NOT NULL,
    `drug_item_id` INT NOT NULL,
    `dosage` VARCHAR(50) NOT NULL,
    `frequency` VARCHAR(50) NOT NULL,
    `duration_days` INT NOT NULL DEFAULT 5,
    `route` ENUM('Oral', 'IV', 'IM', 'SC', 'Topical', 'Sublingual', 'Inhalation') DEFAULT 'Oral',
    `instructions` VARCHAR(255) DEFAULT 'After meals',
    `quantity_prescribed` INT NOT NULL,
    `quantity_dispensed` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`drug_item_id`) REFERENCES `drug_items`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `pharmacy_sales`;
CREATE TABLE `pharmacy_sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_invoice_number` VARCHAR(30) NOT NULL UNIQUE,
    `prescription_id` INT DEFAULT NULL,
    `encounter_id` INT DEFAULT NULL,
    `patient_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(100) DEFAULT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
    `net_total` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('Cash', 'Card', 'Bill_to_Encounter') DEFAULT 'Cash',
    `dispensed_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`dispensed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `pharmacy_sale_items`;
CREATE TABLE `pharmacy_sale_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pharmacy_sale_id` INT NOT NULL,
    `drug_batch_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`pharmacy_sale_id`) REFERENCES `pharmacy_sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`drug_batch_id`) REFERENCES `drug_batches`(`id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- DOMAIN 6: DIAGNOSTICS (LABORATORY & RADIOLOGY)
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `lab_categories`;
CREATE TABLE `lab_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `lab_tests`;
CREATE TABLE `lab_tests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `test_name` VARCHAR(150) NOT NULL,
    `test_code` VARCHAR(30) NOT NULL UNIQUE,
    `specimen_type` ENUM('Blood', 'Serum', 'Plasma', 'Urine', 'Stool', 'CSF', 'Sputum', 'Biopsy_Tissue', 'Swab') DEFAULT 'Blood',
    `standard_price` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    `turnaround_hours` INT DEFAULT 12,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (`category_id`) REFERENCES `lab_categories`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `lab_test_parameters`;
CREATE TABLE `lab_test_parameters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lab_test_id` INT NOT NULL,
    `parameter_name` VARCHAR(100) NOT NULL,
    `unit` VARCHAR(30) DEFAULT NULL,
    `reference_range` VARCHAR(100) DEFAULT NULL,
    `normal_min` DECIMAL(8,2) DEFAULT NULL,
    `normal_max` DECIMAL(8,2) DEFAULT NULL,
    `critical_low` DECIMAL(8,2) DEFAULT NULL,
    `critical_high` DECIMAL(8,2) DEFAULT NULL,
    FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `lab_orders`;
CREATE TABLE `lab_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `encounter_id` INT NOT NULL,
    `ordered_by` INT NOT NULL,
    `order_datetime` DATETIME NOT NULL,
    `priority` ENUM('Routine', 'Urgent', 'STAT') DEFAULT 'Routine',
    `clinical_notes` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Ordered', 'Sample_Collected', 'Processing', 'Completed', 'Cancelled') DEFAULT 'Ordered',
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ordered_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `lab_order_items`;
CREATE TABLE `lab_order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lab_order_id` INT NOT NULL,
    `lab_test_id` INT NOT NULL,
    `sample_barcode` VARCHAR(50) DEFAULT NULL,
    `sample_collected_at` DATETIME DEFAULT NULL,
    `collected_by` INT DEFAULT NULL,
    `item_status` ENUM('Pending', 'Collected', 'Tested', 'Verified') DEFAULT 'Pending',
    FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests`(`id`),
    FOREIGN KEY (`collected_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `lab_results`;
CREATE TABLE `lab_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lab_order_item_id` INT NOT NULL,
    `parameter_id` INT NOT NULL,
    `result_value` VARCHAR(100) NOT NULL,
    `abnormal_flag` ENUM('Normal', 'Low', 'High', 'Critical_Low', 'Critical_High') DEFAULT 'Normal',
    `remarks` VARCHAR(255) DEFAULT NULL,
    `tested_by` INT NOT NULL,
    `verified_by` INT DEFAULT NULL,
    `verified_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`lab_order_item_id`) REFERENCES `lab_order_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parameter_id`) REFERENCES `lab_test_parameters`(`id`),
    FOREIGN KEY (`tested_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- DOMAIN 7: CENTRALIZED BILLING, CHARGES & RECEIPTS
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `tariff_services`;
CREATE TABLE `tariff_services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT DEFAULT NULL,
    `service_name` VARCHAR(150) NOT NULL,
    `service_code` VARCHAR(30) NOT NULL UNIQUE,
    `category` ENUM('Consultation', 'Nursing', 'Bed_Charge', 'Procedure', 'Equipment', 'Emergency', 'Other') NOT NULL,
    `standard_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) DEFAULT 1,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `bills`;
CREATE TABLE `bills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bill_number` VARCHAR(30) NOT NULL UNIQUE,
    `encounter_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `bill_type` ENUM('OPD', 'IPD', 'Emergency') NOT NULL,
    `bill_date` DATETIME NOT NULL,
    `gross_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `net_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `balance_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('Unpaid', 'Partially-Paid', 'Fully-Paid', 'Refunded') DEFAULT 'Unpaid',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bill_patient` (`patient_id`),
    INDEX `idx_bill_enc` (`encounter_id`),
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `bill_items`;
CREATE TABLE `bill_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bill_id` INT NOT NULL,
    `item_type` ENUM('Consultation', 'Bed_Charge', 'Medication', 'Lab_Test', 'Procedure', 'Nursing', 'Other') NOT NULL,
    `reference_id` INT DEFAULT NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `discount` DECIMAL(10,2) DEFAULT 0.00,
    `net_price` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `receipt_number` VARCHAR(30) NOT NULL UNIQUE,
    `bill_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `amount_paid` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('Cash', 'Credit_Card', 'Debit_Card', 'Bank_Transfer', 'Online') DEFAULT 'Cash',
    `transaction_reference` VARCHAR(100) DEFAULT NULL,
    `payment_datetime` DATETIME NOT NULL,
    `received_by` INT NOT NULL,
    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
