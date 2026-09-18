-- Seed pending prescriptions for local patients to test 1-click POS loading
INSERT INTO `prescriptions` (`prescription_number`, `encounter_id`, `doctor_id`, `clinical_notes`, `status`, `created_at`)
VALUES 
('RX-2026-0003', 1, 2, 'Acute Pharyngitis & Fever: Prescribed oral antibiotic, analgesic, and PPI.', 'Pending', NOW()),
('RX-2026-0004', 2, 2, 'Hypertension & Gastritis: Routine OPD follow-up for BP control and acid peptic symptoms.', 'Pending', NOW())
ON DUPLICATE KEY UPDATE `status` = 'Pending';

-- Get the prescription IDs
SET @rx1 = (SELECT id FROM `prescriptions` WHERE `prescription_number` = 'RX-2026-0003');
SET @rx2 = (SELECT id FROM `prescriptions` WHERE `prescription_number` = 'RX-2026-0004');

DELETE FROM `prescription_items` WHERE `prescription_id` IN (@rx1, @rx2);

-- Items for RX-2026-0003 (Augmentin, Panadol, Risek)
INSERT INTO `prescription_items` (`prescription_id`, `drug_item_id`, `dosage`, `frequency`, `duration_days`, `route`, `instructions`, `quantity_prescribed`, `quantity_dispensed`)
VALUES 
(@rx1, 2, '625mg', 'Twice Daily (BID)', 5, 'Oral', '1 صبح 1 شام (کھانے کے بعد)', 10, 0),
(@rx1, 1, '500mg', 'Thrice Daily (TID)', 3, 'Oral', '1 گولی دن میں 3 بار یا بخار میں', 10, 0),
(@rx1, 3, '40mg', 'Once Daily (OD)', 7, 'Oral', '1 کیپسول صبح نہار منہ', 7, 0);

-- Items for RX-2026-0004 (Norvasc, Loprin)
INSERT INTO `prescription_items` (`prescription_id`, `drug_item_id`, `dosage`, `frequency`, `duration_days`, `route`, `instructions`, `quantity_prescribed`, `quantity_dispensed`)
VALUES 
(@rx2, 5, '5mg', 'Once Daily (OD)', 30, 'Oral', '1 گولی روزانہ صبح ناشتے کے بعد', 30, 0),
(@rx2, 4, '75mg', 'Once Daily (OD)', 30, 'Oral', '1 گولی روزانہ دوپہر کے کھانے کے بعد', 30, 0);
