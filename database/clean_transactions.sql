-- ============================================================================
-- HOSPITAL MANAGEMENT SYSTEM (HMS) - RESET TRANSACTIONAL DATA
-- Cleans out all mock/random patients, visits, appointments, and test bills
-- Leaves pristine operational foundation: Departments, Roles, Staff Users, 
-- Wards, Rooms, Beds, Drug Formulary & Batches, Lab Tests, and Tariffs.
-- ============================================================================

USE `hms_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Reset all transactional clinical and financial tables
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `bill_items`;
TRUNCATE TABLE `bills`;
TRUNCATE TABLE `lab_results`;
TRUNCATE TABLE `lab_order_items`;
TRUNCATE TABLE `lab_orders`;
TRUNCATE TABLE `pharmacy_sale_items`;
TRUNCATE TABLE `pharmacy_sales`;
TRUNCATE TABLE `prescription_items`;
TRUNCATE TABLE `prescriptions`;
TRUNCATE TABLE `discharge_summaries`;
TRUNCATE TABLE `nursing_notes`;
TRUNCATE TABLE `bed_allocations`;
TRUNCATE TABLE `admissions`;
TRUNCATE TABLE `consultation_notes`;
TRUNCATE TABLE `clinical_vitals`;
TRUNCATE TABLE `appointments`;
TRUNCATE TABLE `encounters`;
TRUNCATE TABLE `patient_allergies`;
TRUNCATE TABLE `patients`;

-- 2. Reset all beds to Available and clear any encounter linkage
UPDATE `beds` SET `status` = 'Available', `current_encounter_id` = NULL;

-- 3. Reset drug batch quantities to full initial capacity
UPDATE `drug_batches` SET `current_quantity` = `initial_quantity`, `status` = 'Active';

-- 4. Clean audit logs of test runs, add initial system initialization entry
TRUNCATE TABLE `audit_logs`;
INSERT INTO `audit_logs` (`user_id`, `event_type`, `table_name`, `record_id`, `new_values`, `ip_address`, `user_agent`)
VALUES (1, 'SYSTEM_INIT', 'system', '1', '{"status":"Pristine database ready for live operation"}', '127.0.0.1', 'CLI/Migration');

SET FOREIGN_KEY_CHECKS = 1;
