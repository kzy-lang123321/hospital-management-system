-- Upgrade Inventory & Pharmacy for All Medicine Types, Narcotics & Expiry Management
USE `hms_db`;

-- Enhance inventory_items with comprehensive pharmaceutical, narcotics, and batch expiry attributes
ALTER TABLE `inventory_items` 
    ADD COLUMN IF NOT EXISTS `generic_name` VARCHAR(150) DEFAULT NULL AFTER `item_name`,
    ADD COLUMN IF NOT EXISTS `dosage_form` VARCHAR(50) DEFAULT 'General' AFTER `generic_name`,
    ADD COLUMN IF NOT EXISTS `is_narcotic` TINYINT(1) DEFAULT 0 AFTER `dosage_form`,
    ADD COLUMN IF NOT EXISTS `narcotic_schedule` VARCHAR(30) DEFAULT NULL AFTER `is_narcotic`,
    ADD COLUMN IF NOT EXISTS `storage_condition` VARCHAR(50) DEFAULT 'Room Temperature' AFTER `narcotic_schedule`,
    ADD COLUMN IF NOT EXISTS `batch_number` VARCHAR(50) DEFAULT NULL AFTER `storage_condition`,
    ADD COLUMN IF NOT EXISTS `expiry_date` DATE DEFAULT NULL AFTER `batch_number`,
    ADD COLUMN IF NOT EXISTS `unit_sale_price` DECIMAL(10,2) DEFAULT 0.00 AFTER `unit_cost_price`;

-- Enhance drug_items with narcotic schedule and storage condition
ALTER TABLE `drug_items` 
    ADD COLUMN IF NOT EXISTS `narcotic_schedule` VARCHAR(30) DEFAULT NULL AFTER `is_controlled_substance`,
    ADD COLUMN IF NOT EXISTS `storage_condition` VARCHAR(50) DEFAULT 'Room Temperature' AFTER `narcotic_schedule`;
