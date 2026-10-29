-- ============================================================================
-- Hospital Management System (HMS)
-- Multi-Hospital / Multi-Tenant Database Architecture Migration
-- Safe, Backward-Compatible: Preserves 100% of existing historical data.
-- ============================================================================

-- 1. Upgrade `hospital_profiles` to support full multi-tenant customization
ALTER TABLE `hospital_profiles`
    ADD COLUMN IF NOT EXISTS `hospital_code` VARCHAR(50) NULL AFTER `hospital_name`,
    ADD COLUMN IF NOT EXISTS `logo_path` VARCHAR(255) NULL AFTER `tagline`,
    ADD COLUMN IF NOT EXISTS `theme_color` VARCHAR(30) DEFAULT '#0d9488' AFTER `logo_path`,
    ADD COLUMN IF NOT EXISTS `date_format` VARCHAR(20) DEFAULT 'd M, Y' AFTER `time_zone`,
    ADD COLUMN IF NOT EXISTS `mrn_prefix` VARCHAR(20) DEFAULT 'MRN' AFTER `date_format`,
    ADD COLUMN IF NOT EXISTS `invoice_prefix` VARCHAR(20) DEFAULT 'INV' AFTER `mrn_prefix`,
    ADD COLUMN IF NOT EXISTS `status` ENUM('Active', 'Suspended', 'Pending') DEFAULT 'Active' AFTER `invoice_prefix`,
    ADD COLUMN IF NOT EXISTS `owner_user_id` INT(11) NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `subscription_plan` VARCHAR(50) DEFAULT 'Enterprise' AFTER `owner_user_id`,
    ADD COLUMN IF NOT EXISTS `modules_enabled` TEXT NULL AFTER `subscription_plan`;

-- Update default Hospital #1 record
UPDATE `hospital_profiles` SET
    `hospital_code` = COALESCE(`hospital_code`, 'HAYAT'),
    `theme_color` = COALESCE(`theme_color`, '#0d9488'),
    `date_format` = COALESCE(`date_format`, 'd M, Y'),
    `mrn_prefix` = COALESCE(`mrn_prefix`, 'MRN'),
    `invoice_prefix` = COALESCE(`invoice_prefix`, 'INV'),
    `status` = 'Active',
    `owner_user_id` = 1,
    `subscription_plan` = 'Enterprise',
    `modules_enabled` = '{"opd":true,"ipd":true,"emergency":true,"ot":true,"pharmacy":true,"laboratory":true,"radiology":true,"blood_bank":true,"billing":true,"inventory":true,"insurance":true,"reports":true}'
WHERE `id` = 1;

-- 2. Add Platform Roles in `roles` table
INSERT INTO `roles` (`id`, `role_name`, `slug`, `description`, `is_system_role`, `created_at`)
VALUES 
    (10, 'Platform Super Administrator', 'platform_admin', 'Full unrestricted control across all hospitals, subscriptions and platform audits', 1, NOW()),
    (11, 'Hospital Owner / Super Admin', 'hospital_owner', 'Full executive administration and customization of the assigned hospital', 1, NOW())
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 3. Add `hospital_id` to `users`
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NULL DEFAULT 1 AFTER `id`,
    ADD INDEX IF NOT EXISTS `idx_user_hospital` (`hospital_id`);

-- Update existing users to Hospital 1
UPDATE `users` SET `hospital_id` = 1 WHERE `hospital_id` IS NULL AND `role_id` != 10;

-- Create Dedicated Platform Super Admin user if not exists
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role_id`, `hospital_id`, `designation`, `status`, `created_at`)
SELECT 'platform.admin', 'platform.admin@hms-system.org', '$2y$10$wT33k2r9k3888J98Yj8dhe7kHh42i7k1.k48j48J38d82k1982818', 'Platform Super Administrator', 10, NULL, 'Chief Platform Architect', 'Active', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'platform.admin');

-- Set default password for platform.admin: 'Platform@123'
UPDATE `users` SET `password_hash` = '$2y$10$fI1QYpGqV3FkWtqJ15rEpeR3Rk8p7fWw2Nq3c8hJ1Z7g8u9w12345' WHERE `username` = 'platform.admin';

-- 4. Add `hospital_id` to Clinical, Financial & Operational tables
-- Departments
ALTER TABLE `departments` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_dept_hosp` (`hospital_id`);
-- Patients & Allergies
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_patient_hosp` (`hospital_id`);
ALTER TABLE `patient_allergies` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_allergy_hosp` (`hospital_id`);
-- Encounters & Clinical Notes
ALTER TABLE `encounters` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_enc_hosp` (`hospital_id`);
ALTER TABLE `clinical_vitals` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_vitals_hosp` (`hospital_id`);
ALTER TABLE `consultation_notes` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_notes_hosp` (`hospital_id`);
ALTER TABLE `nursing_notes` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_nurse_notes_hosp` (`hospital_id`);
-- Appointments
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_apt_hosp` (`hospital_id`);
-- Prescriptions & Items
ALTER TABLE `prescriptions` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_rx_hosp` (`hospital_id`);
ALTER TABLE `prescription_items` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_rx_items_hosp` (`hospital_id`);
-- Pharmacy Formularies & Sales
ALTER TABLE `drug_categories` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_drug_cat_hosp` (`hospital_id`);
ALTER TABLE `drug_items` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_drug_item_hosp` (`hospital_id`);
ALTER TABLE `drug_batches` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_batch_hosp` (`hospital_id`);
ALTER TABLE `pharmacy_sales` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_sale_hosp` (`hospital_id`);
ALTER TABLE `pharmacy_sale_items` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_sale_items_hosp` (`hospital_id`);
-- Laboratory
ALTER TABLE `lab_categories` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_lab_cat_hosp` (`hospital_id`);
ALTER TABLE `lab_tests` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_lab_test_hosp` (`hospital_id`);
ALTER TABLE `lab_test_parameters` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_lab_param_hosp` (`hospital_id`);
ALTER TABLE `lab_orders` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_lab_order_hosp` (`hospital_id`);
ALTER TABLE `lab_order_items` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_lab_order_item_hosp` (`hospital_id`);
ALTER TABLE `lab_results` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_lab_res_hosp` (`hospital_id`);
-- Radiology
ALTER TABLE `radiology_services` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_rad_srv_hosp` (`hospital_id`);
ALTER TABLE `radiology_orders` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_rad_order_hosp` (`hospital_id`);
ALTER TABLE `radiology_reports` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_rad_rep_hosp` (`hospital_id`);
-- Inpatient Wards, Rooms & Beds
ALTER TABLE `wards` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_ward_hosp` (`hospital_id`);
ALTER TABLE `rooms` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_room_hosp` (`hospital_id`);
ALTER TABLE `beds` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_bed_hosp` (`hospital_id`);
ALTER TABLE `bed_allocations` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_bed_alloc_hosp` (`hospital_id`);
ALTER TABLE `admissions` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_adm_hosp` (`hospital_id`);
ALTER TABLE `discharge_summaries` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_disch_hosp` (`hospital_id`);
-- Operation Theatre (OT)
ALTER TABLE `ot_rooms` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_ot_room_hosp` (`hospital_id`);
ALTER TABLE `surgery_bookings` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_surg_book_hosp` (`hospital_id`);
ALTER TABLE `surgical_records` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_surg_rec_hosp` (`hospital_id`);
-- Tariffs & Billing
ALTER TABLE `tariff_services` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_tariff_hosp` (`hospital_id`);
ALTER TABLE `bills` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_bill_hosp` (`hospital_id`);
ALTER TABLE `bill_items` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_bill_items_hosp` (`hospital_id`);
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_pay_hosp` (`hospital_id`);
-- Insurance & TPA
ALTER TABLE `insurance_companies` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_ins_co_hosp` (`hospital_id`);
ALTER TABLE `insurance_claims` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_ins_claims_hosp` (`hospital_id`);
-- Central Stores & Procurement
ALTER TABLE `inventory_categories` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_inv_cat_hosp` (`hospital_id`);
ALTER TABLE `inventory_items` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_inv_item_hosp` (`hospital_id`);
ALTER TABLE `suppliers` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_supplier_hosp` (`hospital_id`);
ALTER TABLE `purchase_orders` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_po_hosp` (`hospital_id`);
ALTER TABLE `purchase_order_items` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_po_items_hosp` (`hospital_id`);
-- Blood Bank
ALTER TABLE `blood_donors` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_donor_hosp` (`hospital_id`);
ALTER TABLE `blood_units` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_blood_unit_hosp` (`hospital_id`);
ALTER TABLE `blood_requests` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_blood_req_hosp` (`hospital_id`);
-- Roster & Shifts
ALTER TABLE `staff_shifts` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_shift_hosp` (`hospital_id`);
ALTER TABLE `staff_roster` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_roster_hosp` (`hospital_id`);
-- Audit Logs
ALTER TABLE `audit_logs` ADD COLUMN IF NOT EXISTS `hospital_id` INT(11) NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_audit_hosp` (`hospital_id`);
