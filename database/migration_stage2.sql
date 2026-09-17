-- ============================================================================
-- HOSPITAL MANAGEMENT SYSTEM (HMS) - STAGE 2 EXPANSION SCHEMA & MASTERS
-- Adds: Emergency, Radiology, Operation Theatre, Blood Bank, Insurance,
-- Inventory & Procurement, and Staff Shifts.
-- ============================================================================

USE `hms_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. RADIOLOGY & IMAGING
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `radiology_services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `service_name` VARCHAR(150) NOT NULL,
    `service_code` VARCHAR(30) NOT NULL UNIQUE,
    `modality` ENUM('X-Ray', 'Ultrasound', 'CT-Scan', 'MRI', 'Mammography', 'Fluoroscopy') NOT NULL,
    `standard_tariff` DECIMAL(10,2) NOT NULL DEFAULT 1500.00,
    `preparation_notes` TEXT DEFAULT NULL,
    `turnaround_hours` INT DEFAULT 12,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `radiology_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `encounter_id` INT NOT NULL,
    `service_id` INT NOT NULL,
    `ordered_by` INT NOT NULL,
    `order_datetime` DATETIME NOT NULL,
    `priority` ENUM('Routine', 'Urgent', 'STAT') DEFAULT 'Routine',
    `clinical_indication` TEXT DEFAULT NULL,
    `status` ENUM('Ordered', 'Scheduled', 'Exam_Completed', 'Reported', 'Cancelled') DEFAULT 'Ordered',
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `radiology_services`(`id`),
    FOREIGN KEY (`ordered_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `radiology_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `radiology_order_id` INT NOT NULL UNIQUE,
    `radiologist_id` INT NOT NULL,
    `radiological_findings` LONGTEXT NOT NULL,
    `impression` TEXT NOT NULL,
    `technique_used` VARCHAR(255) DEFAULT NULL,
    `report_status` ENUM('Draft', 'Finalized') DEFAULT 'Draft',
    `finalized_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`radiology_order_id`) REFERENCES `radiology_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`radiologist_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 2. OPERATION THEATRE (OT)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ot_rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_name` VARCHAR(100) NOT NULL,
    `room_number` VARCHAR(30) NOT NULL UNIQUE,
    `theatre_type` ENUM('Major_General', 'Laparoscopy_Endo', 'Orthopedic_Trauma', 'Cardiac_OT', 'Obstetric_Labor') DEFAULT 'Major_General',
    `status` ENUM('Available', 'In_Surgery', 'Sterilization', 'Maintenance') DEFAULT 'Available'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `surgery_bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_number` VARCHAR(30) NOT NULL UNIQUE,
    `encounter_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `ot_room_id` INT NOT NULL,
    `lead_surgeon_id` INT NOT NULL,
    `anesthesiologist_id` INT DEFAULT NULL,
    `procedure_name` VARCHAR(255) NOT NULL,
    `scheduled_datetime` DATETIME NOT NULL,
    `estimated_duration_minutes` INT DEFAULT 120,
    `anesthesia_type` ENUM('General', 'Spinal', 'Epidural', 'Local', 'Sedation') DEFAULT 'General',
    `pre_op_diagnosis` VARCHAR(255) DEFAULT NULL,
    `booking_status` ENUM('Scheduled', 'In-Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
    FOREIGN KEY (`ot_room_id`) REFERENCES `ot_rooms`(`id`),
    FOREIGN KEY (`lead_surgeon_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`anesthesiologist_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `surgical_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `surgery_booking_id` INT NOT NULL UNIQUE,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME DEFAULT NULL,
    `operative_notes` LONGTEXT NOT NULL,
    `post_op_diagnosis` VARCHAR(255) DEFAULT NULL,
    `implant_details` VARCHAR(255) DEFAULT NULL,
    `sponge_swab_count_correct` TINYINT(1) DEFAULT 1,
    `blood_loss_ml` INT DEFAULT 50,
    `post_op_destination` ENUM('PACU_Recovery', 'Surgical_ICU', 'Ward') DEFAULT 'PACU_Recovery',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`surgery_booking_id`) REFERENCES `surgery_bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 3. BLOOD BANK
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_donors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `donor_number` VARCHAR(30) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `dob` DATE DEFAULT NULL,
    `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `cnic` VARCHAR(30) DEFAULT NULL,
    `last_donation_date` DATE DEFAULT NULL,
    `screening_status` ENUM('Eligible', 'Deferred', 'Permanently_Ineligible') DEFAULT 'Eligible',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `blood_units` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `unit_barcode` VARCHAR(50) NOT NULL UNIQUE,
    `donor_id` INT DEFAULT NULL,
    `component_type` ENUM('Whole_Blood', 'Packed_Red_Cells_PRBC', 'Fresh_Frozen_Plasma_FFP', 'Platelets_PC', 'Cryoprecipitate') NOT NULL,
    `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    `volume_ml` INT DEFAULT 450,
    `collection_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `storage_freezer_location` VARCHAR(50) DEFAULT 'Refrigerated Bay A',
    `status` ENUM('Available', 'Reserved_CrossMatch', 'Issued', 'Expired', 'Discarded') DEFAULT 'Available',
    FOREIGN KEY (`donor_id`) REFERENCES `blood_donors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `blood_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_number` VARCHAR(30) NOT NULL UNIQUE,
    `encounter_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `requested_by` INT NOT NULL,
    `component_type` ENUM('Whole_Blood', 'Packed_Red_Cells_PRBC', 'Fresh_Frozen_Plasma_FFP', 'Platelets_PC', 'Cryoprecipitate') NOT NULL,
    `units_required` INT NOT NULL DEFAULT 1,
    `urgency` ENUM('Routine', 'Urgent', 'Life_Saving_STAT') DEFAULT 'Routine',
    `clinical_indication` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Pending', 'Cross_Matched', 'Partially_Issued', 'Issued', 'Cancelled') DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`encounter_id`) REFERENCES `encounters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
    FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 4. INSURANCE & CORPORATE TPA
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `insurance_companies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(150) NOT NULL,
    `company_code` VARCHAR(30) NOT NULL UNIQUE,
    `contact_person` VARCHAR(100) DEFAULT NULL,
    `contact_phone` VARCHAR(50) DEFAULT NULL,
    `contact_email` VARCHAR(100) DEFAULT NULL,
    `credit_period_days` INT DEFAULT 30,
    `status` ENUM('Active', 'Suspended') DEFAULT 'Active'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `insurance_claims` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `claim_number` VARCHAR(30) NOT NULL UNIQUE,
    `bill_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `company_id` INT NOT NULL,
    `policy_number` VARCHAR(100) NOT NULL,
    `pre_auth_code` VARCHAR(50) DEFAULT NULL,
    `claim_amount` DECIMAL(12,2) NOT NULL,
    `approved_amount` DECIMAL(12,2) DEFAULT 0.00,
    `patient_copay_amount` DECIMAL(12,2) DEFAULT 0.00,
    `status` ENUM('Draft', 'Submitted', 'Approved', 'Partially_Approved', 'Rejected', 'Settled') DEFAULT 'Draft',
    `remarks` TEXT DEFAULT NULL,
    `submitted_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
    FOREIGN KEY (`company_id`) REFERENCES `insurance_companies`(`id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 5. INVENTORY & PROCUREMENT
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `supplier_name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `contact_person` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `ntn_tax_number` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('Active', 'Blacklisted') DEFAULT 'Active'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `inventory_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `item_code` VARCHAR(30) NOT NULL UNIQUE,
    `unit_of_measure` VARCHAR(30) DEFAULT 'Box / Pack',
    `current_stock` INT NOT NULL DEFAULT 0,
    `reorder_level` INT NOT NULL DEFAULT 20,
    `unit_cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (`category_id`) REFERENCES `inventory_categories`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_number` VARCHAR(30) NOT NULL UNIQUE,
    `supplier_id` INT NOT NULL,
    `order_date` DATE NOT NULL,
    `expected_delivery_date` DATE DEFAULT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Draft', 'Approved', 'Received', 'Cancelled') DEFAULT 'Draft',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 6. STAFF SHIFTS & ROSTER
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `shift_name` VARCHAR(50) NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `description` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `staff_roster` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `shift_id` INT NOT NULL,
    `duty_date` DATE NOT NULL,
    `department_id` INT NOT NULL,
    `status` ENUM('Scheduled', 'Present', 'Absent', 'On_Leave') DEFAULT 'Scheduled',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shift_id`) REFERENCES `staff_shifts`(`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- INITIAL SEED MASTERS FOR EXPANDED MODULES
-- ----------------------------------------------------------------------------

-- Radiology Services
INSERT IGNORE INTO `radiology_services` (`id`, `service_name`, `service_code`, `modality`, `standard_tariff`, `preparation_notes`, `turnaround_hours`) VALUES
(1, 'Digital Chest X-Ray (PA View)', 'RAD-CXR', 'X-Ray', 1200.00, 'Remove metallic jewelry and shirts with metallic buttons.', 4),
(2, 'Abdomen & Pelvis Ultrasound (USG)', 'RAD-USG-ABD', 'Ultrasound', 2200.00, 'Fasting for 6 hours required; full bladder for pelvic scan.', 6),
(3, 'Brain CT Scan (Non-Contrast)', 'RAD-CT-BRN', 'CT-Scan', 6500.00, 'No specific prep for non-contrast.', 8),
(4, 'Lumbar Spine MRI (L-Spine)', 'RAD-MRI-LSP', 'MRI', 12500.00, 'Check for pacemakers, metallic implants, or foreign bodies.', 12),
(5, 'X-Ray Knee Joint (AP & Lateral)', 'RAD-XR-KN', 'X-Ray', 1400.00, 'No preparation required.', 4);

-- Operation Theatres
INSERT IGNORE INTO `ot_rooms` (`id`, `room_name`, `room_number`, `theatre_type`, `status`) VALUES
(1, 'General & Laparoscopic OT Suite 1', 'OT-01', 'Laparoscopy_Endo', 'Available'),
(2, 'Orthopedic & Trauma Laminar Flow OT', 'OT-02', 'Orthopedic_Trauma', 'Available'),
(3, 'Emergency & Acute Surgical Suite', 'OT-03', 'Major_General', 'Available');

-- Blood Component Types & Units (Initial inventory)
INSERT IGNORE INTO `blood_donors` (`id`, `donor_number`, `full_name`, `gender`, `blood_group`, `phone`, `cnic`, `screening_status`) VALUES
(1, 'DNR-2026-001', 'Hamza Qureshi', 'Male', 'O+', '+92 300 1122334', '17301-1122334-1', 'Eligible'),
(2, 'DNR-2026-002', 'Adnan Malik', 'Male', 'A+', '+92 333 4455667', '17301-5566778-2', 'Eligible'),
(3, 'DNR-2026-003', 'Rashid Mehmood', 'Male', 'B+', '+92 345 7788990', '17301-9988112-3', 'Eligible');

INSERT IGNORE INTO `blood_units` (`id`, `unit_barcode`, `donor_id`, `component_type`, `blood_group`, `volume_ml`, `collection_date`, `expiry_date`, `storage_freezer_location`, `status`) VALUES
(1, 'BLD-OP-2601', 1, 'Packed_Red_Cells_PRBC', 'O+', 450, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'Cold Storage A1', 'Available'),
(2, 'BLD-AP-2602', 2, 'Packed_Red_Cells_PRBC', 'A+', 450, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'Cold Storage A2', 'Available'),
(3, 'BLD-BP-2603', 3, 'Packed_Red_Cells_PRBC', 'B+', 450, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'Cold Storage A3', 'Available'),
(4, 'BLD-FFP-2604', 1, 'Fresh_Frozen_Plasma_FFP', 'O+', 250, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 'Deep Freezer B1 (-30C)', 'Available');

-- Insurance / Corporate TPA Companies
INSERT IGNORE INTO `insurance_companies` (`id`, `company_name`, `company_code`, `contact_person`, `contact_phone`, `contact_email`, `credit_period_days`) VALUES
(1, 'State Life Health TPA', 'SLIC-HEALTH', 'Tariq Javed', '+92 51 9201122', 'claims@statelife.com.pk', 45),
(2, 'Jubilee Life Healthcare', 'JUBILEE-INS', 'Saima Rizvi', '+92 21 111111555', 'healthclaims@jubileelife.com', 30),
(3, 'Pak Qatar Takaful', 'PQ-TAKAFUL', 'Farhan Siddiqui', '+92 91 5889900', 'corporate@pakqatar.com.pk', 30);

-- Inventory Suppliers & Consumables
INSERT IGNORE INTO `suppliers` (`id`, `supplier_name`, `code`, `contact_person`, `phone`, `email`, `status`) VALUES
(1, 'MedTech Surgical Supplies Ltd.', 'SUP-MEDTECH', 'Zubair Shah', '+92 91 5271122', 'orders@medtech.com.pk', 'Active'),
(2, 'Siemens Healthcare Pakistan', 'SUP-SIEMENS', 'Arsalan Baig', '+92 21 34567890', 'service.pk@siemens-healthineers.com', 'Active');

INSERT IGNORE INTO `inventory_categories` (`id`, `category_name`, `code`) VALUES
(1, 'Surgical Consumables & Sutures', 'SURG-CONS'),
(2, 'Sterile PPE & Disposables', 'PPE-DISP'),
(3, 'Biomedical Instruments & Sensors', 'BIOMED');

INSERT IGNORE INTO `inventory_items` (`id`, `category_id`, `item_name`, `item_code`, `unit_of_measure`, `current_stock`, `reorder_level`, `unit_cost_price`) VALUES
(1, 1, 'Sterile Surgical Gloves (Size 7.5)', 'INV-GLV-75', 'Box of 50 pairs', 120, 30, 850.00),
(2, 1, 'Vicryl 3-0 Absorbable Suture Pack', 'INV-SUT-V30', 'Box of 12', 45, 15, 3200.00),
(3, 2, 'Disposable N95 Surgical Masks', 'INV-N95-MSK', 'Box of 20', 80, 25, 600.00),
(4, 2, 'IV Cannula 20G (Pink)', 'INV-CAN-20G', 'Box of 100', 95, 20, 1400.00);

-- Staff Shifts
INSERT IGNORE INTO `staff_shifts` (`id`, `shift_name`, `start_time`, `end_time`, `description`) VALUES
(1, 'Morning Shift', '08:00:00', '14:00:00', 'Primary OPD and elective surgery roster'),
(2, 'Evening Shift', '14:00:00', '20:00:00', 'Second round clinical coverage and admissions'),
(3, 'Night Duty', '20:00:00', '08:00:00', 'Overnight critical care and 24/7 ER coverage');

SET FOREIGN_KEY_CHECKS = 1;
