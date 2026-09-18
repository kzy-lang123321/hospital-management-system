-- ============================================================================
-- HMS UPGRADE: POINT OF SALE (POS) ENHANCEMENTS
-- ============================================================================

ALTER TABLE `pharmacy_sales` 
  MODIFY COLUMN `payment_method` ENUM('Cash', 'Card', 'Bill_to_Encounter', 'Bank_Transfer', 'Mobile_Wallet') DEFAULT 'Cash',
  ADD COLUMN IF NOT EXISTS `customer_phone` VARCHAR(30) DEFAULT NULL AFTER `customer_name`,
  ADD COLUMN IF NOT EXISTS `payment_reference` VARCHAR(100) DEFAULT NULL AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `amount_tendered` DECIMAL(10,2) DEFAULT 0.00 AFTER `net_total`,
  ADD COLUMN IF NOT EXISTS `change_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount_tendered`,
  ADD COLUMN IF NOT EXISTS `sale_notes` VARCHAR(255) DEFAULT NULL AFTER `change_amount`;
