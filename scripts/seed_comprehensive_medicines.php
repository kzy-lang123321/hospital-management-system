<?php
/**
 * Hospital Management System (HMS)
 * Seed Comprehensive Medicines, Narcotics, and Expiry Tracking Batches
 */

require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "Seeding Comprehensive Medicines & Inventory Items...\n";

// 1. Ensure Drug Categories
$drugCategories = [
    ['Analgesics & Antipyretics', 'Pain management and fever reduction'],
    ['Antibiotics & Antimicrobials', 'Broad-spectrum and targeted antibacterial therapy'],
    ['Cardiovascular & Antihypertensives', 'Blood pressure, cardiac, and lipid control'],
    ['Gastrointestinal & Antiulcerants', 'Proton pump inhibitors and motility drugs'],
    ['Respiratory & Inhalers', 'Bronchodilators and respiratory agents'],
    ['Controlled Substances & Narcotics', 'Strict Schedule II/IV Opioids, sedatives, and controlled anesthetics'],
    ['Parenteral & IV Fluids', 'Intravenous crystalloids, colloids, and electrolyte infusions'],
    ['Emergency & Resuscitation', 'Crash cart critical adrenaline, atropine, and vasopressors']
];

foreach ($drugCategories as $dc) {
    $stmt = $db->prepare("INSERT INTO `drug_categories` (`category_name`, `description`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `description` = VALUES(`description`)");
    $stmt->execute([$dc[0], $dc[1]]);
}

$catMap = $db->query("SELECT category_name, id FROM `drug_categories`")->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Comprehensive Drug Items & Batches
$meds = [
    // --- NARCOTICS & CONTROLLED SUBSTANCES ---
    [
        'category' => 'Controlled Substances & Narcotics',
        'brand' => 'Morphine Inj 10mg/ml',
        'generic' => 'Morphine Sulphate',
        'dosage_form' => 'Injection',
        'strength' => '10mg/ml (1ml Ampoule)',
        'reorder' => 20,
        'uom' => 'Ampoule',
        'rx_required' => 1,
        'is_controlled' => 1,
        'schedule' => 'Schedule II (Opioid)',
        'storage' => 'Narcotics Double-Lock Safe',
        'batch' => 'BT-MORPH-2026A',
        'mfg' => date('Y-m-d', strtotime('-3 months')),
        'expiry' => date('Y-m-d', strtotime('+18 months')),
        'cost' => 180.00,
        'sale' => 260.00,
        'qty' => 50,
        'status' => 'Active'
    ],
    [
        'category' => 'Controlled Substances & Narcotics',
        'brand' => 'Fentanyl Inj 50mcg/ml',
        'generic' => 'Fentanyl Citrate',
        'dosage_form' => 'Injection',
        'strength' => '50mcg/ml (2ml Ampoule)',
        'reorder' => 15,
        'uom' => 'Ampoule',
        'rx_required' => 1,
        'is_controlled' => 1,
        'schedule' => 'Schedule II (Opioid)',
        'storage' => 'Narcotics Double-Lock Safe',
        'batch' => 'BT-FENT-2026B',
        'mfg' => date('Y-m-d', strtotime('-2 months')),
        'expiry' => date('Y-m-d', strtotime('+14 months')),
        'cost' => 320.00,
        'sale' => 450.00,
        'qty' => 40,
        'status' => 'Active'
    ],
    [
        'category' => 'Controlled Substances & Narcotics',
        'brand' => 'Valium (Diazepam) Inj 10mg',
        'generic' => 'Diazepam',
        'dosage_form' => 'Injection',
        'strength' => '10mg/2ml Ampoule',
        'reorder' => 25,
        'uom' => 'Ampoule',
        'rx_required' => 1,
        'is_controlled' => 1,
        'schedule' => 'Schedule IV (Sedative)',
        'storage' => 'Controlled Cabinet (15-25°C)',
        'batch' => 'BT-VAL-8821',
        'mfg' => date('Y-m-d', strtotime('-5 months')),
        'expiry' => date('Y-m-d', strtotime('+9 months')),
        'cost' => 95.00,
        'sale' => 140.00,
        'qty' => 80,
        'status' => 'Active'
    ],
    [
        'category' => 'Controlled Substances & Narcotics',
        'brand' => 'Midazolam Inj 5mg/ml',
        'generic' => 'Midazolam HCl',
        'dosage_form' => 'Injection',
        'strength' => '5mg/ml Ampoule',
        'reorder' => 20,
        'uom' => 'Ampoule',
        'rx_required' => 1,
        'is_controlled' => 1,
        'schedule' => 'Schedule IV (Sedative/Pre-op)',
        'storage' => 'Controlled Cabinet',
        'batch' => 'BT-MID-4401',
        'mfg' => date('Y-m-d', strtotime('-4 months')),
        'expiry' => date('Y-m-d', strtotime('+11 months')),
        'cost' => 145.00,
        'sale' => 210.00,
        'qty' => 60,
        'status' => 'Active'
    ],
    [
        'category' => 'Controlled Substances & Narcotics',
        'brand' => 'Tramal (Tramadol) 50mg',
        'generic' => 'Tramadol Hydrochloride',
        'dosage_form' => 'Capsule',
        'strength' => '50mg',
        'reorder' => 30,
        'uom' => 'Strip of 10',
        'rx_required' => 1,
        'is_controlled' => 1,
        'schedule' => 'Schedule IV (Opioid)',
        'storage' => 'Room Temperature',
        'batch' => 'BT-TRM-9912',
        'mfg' => date('Y-m-d', strtotime('-6 months')),
        'expiry' => date('Y-m-d', strtotime('+15 months')),
        'cost' => 180.00,
        'sale' => 250.00,
        'qty' => 120,
        'status' => 'Active'
    ],

    // --- EXPIRING SOON MEDICATIONS (< 90 DAYS) FOR EXPIRY TESTING ---
    [
        'category' => 'Antibiotics & Antimicrobials',
        'brand' => 'Augmentin 625mg Tab',
        'generic' => 'Amoxicillin + Clavulanic Acid',
        'dosage_form' => 'Tablet',
        'strength' => '625mg',
        'reorder' => 50,
        'uom' => 'Strip of 10',
        'rx_required' => 1,
        'is_controlled' => 0,
        'schedule' => 'Prescription Only',
        'storage' => 'Dry Cool Place (<25°C)',
        'batch' => 'BT-AUG-EXP-NEAR',
        'mfg' => date('Y-m-d', strtotime('-22 months')),
        'expiry' => date('Y-m-d', strtotime('+35 days')), // Near Expiry!
        'cost' => 260.00,
        'sale' => 320.00,
        'qty' => 45,
        'status' => 'Active'
    ],
    [
        'category' => 'Antibiotics & Antimicrobials',
        'brand' => 'Rocephin (Ceftriaxone) 1g IV',
        'generic' => 'Ceftriaxone Sodium',
        'dosage_form' => 'Injection',
        'strength' => '1g Vial + Water for Inj',
        'reorder' => 40,
        'uom' => 'Vial',
        'rx_required' => 1,
        'is_controlled' => 0,
        'schedule' => 'Prescription Only',
        'storage' => 'Cool Room Temp (<25°C)',
        'batch' => 'BT-ROCE-EXP-NEAR',
        'mfg' => date('Y-m-d', strtotime('-21 months')),
        'expiry' => date('Y-m-d', strtotime('+48 days')), // Near Expiry!
        'cost' => 380.00,
        'sale' => 490.00,
        'qty' => 35,
        'status' => 'Active'
    ],

    // --- EXPIRED / QUARANTINED BATCH FOR SAFETY TESTING ---
    [
        'category' => 'Analgesics & Antipyretics',
        'brand' => 'Dicloran 75mg Inj',
        'generic' => 'Diclofenac Sodium',
        'dosage_form' => 'Injection',
        'strength' => '75mg/3ml Ampoule',
        'reorder' => 20,
        'uom' => 'Ampoule',
        'rx_required' => 1,
        'is_controlled' => 0,
        'schedule' => 'Prescription Only',
        'storage' => 'Quarantine Shelf',
        'batch' => 'BT-DIC-EXPIRED',
        'mfg' => date('Y-m-d', strtotime('-26 months')),
        'expiry' => date('Y-m-d', strtotime('-18 days')), // Expired!
        'cost' => 35.00,
        'sale' => 60.00,
        'qty' => 15,
        'status' => 'Expired'
    ],

    // --- ORAL LIQUIDS & SYRUPS ---
    [
        'category' => 'Analgesics & Antipyretics',
        'brand' => 'Brufen Suspension 120ml',
        'generic' => 'Ibuprofen',
        'dosage_form' => 'Syrup',
        'strength' => '100mg/5ml (120ml Bottle)',
        'reorder' => 40,
        'uom' => 'Bottle',
        'rx_required' => 0,
        'is_controlled' => 0,
        'schedule' => 'OTC',
        'storage' => 'Room Temperature',
        'batch' => 'BT-BRU-5521',
        'mfg' => date('Y-m-d', strtotime('-4 months')),
        'expiry' => date('Y-m-d', strtotime('+20 months')),
        'cost' => 85.00,
        'sale' => 120.00,
        'qty' => 90,
        'status' => 'Active'
    ],

    // --- PARENTERAL & IV INFUSIONS ---
    [
        'category' => 'Parenteral & IV Fluids',
        'brand' => 'Normal Saline 0.9% 1000ml',
        'generic' => 'Sodium Chloride 0.9%',
        'dosage_form' => 'Infusion',
        'strength' => '0.9% w/v (1000ml Bottle)',
        'reorder' => 100,
        'uom' => 'IV Bottle',
        'rx_required' => 1,
        'is_controlled' => 0,
        'schedule' => 'Hospital IV Formulation',
        'storage' => 'Room Temperature',
        'batch' => 'BT-NS-1000-88',
        'mfg' => date('Y-m-d', strtotime('-3 months')),
        'expiry' => date('Y-m-d', strtotime('+24 months')),
        'cost' => 120.00,
        'sale' => 190.00,
        'qty' => 250,
        'status' => 'Active'
    ],
    [
        'category' => 'Parenteral & IV Fluids',
        'brand' => 'Ringer Lactate (Hartmann) 1000ml',
        'generic' => 'Compound Sodium Lactate',
        'dosage_form' => 'Infusion',
        'strength' => '1000ml IV Bottle',
        'reorder' => 80,
        'uom' => 'IV Bottle',
        'rx_required' => 1,
        'is_controlled' => 0,
        'schedule' => 'Hospital IV Formulation',
        'storage' => 'Room Temperature',
        'batch' => 'BT-RL-1000-77',
        'mfg' => date('Y-m-d', strtotime('-3 months')),
        'expiry' => date('Y-m-d', strtotime('+24 months')),
        'cost' => 140.00,
        'sale' => 220.00,
        'qty' => 180,
        'status' => 'Active'
    ],

    // --- RESPIRATORY & INHALERS ---
    [
        'category' => 'Respiratory & Inhalers',
        'brand' => 'Ventolin Evohaler 100mcg',
        'generic' => 'Salbutamol Sulphate',
        'dosage_form' => 'Inhaler',
        'strength' => '100mcg / Dose (200 Actuations)',
        'reorder' => 30,
        'uom' => 'Inhaler Device',
        'rx_required' => 1,
        'is_controlled' => 0,
        'schedule' => 'Prescription Only',
        'storage' => 'Below 30°C Protect from frost',
        'batch' => 'BT-VEN-3012',
        'mfg' => date('Y-m-d', strtotime('-5 months')),
        'expiry' => date('Y-m-d', strtotime('+16 months')),
        'cost' => 450.00,
        'sale' => 620.00,
        'qty' => 65,
        'status' => 'Active'
    ],

    // --- EMERGENCY & RESUSCITATION ---
    [
        'category' => 'Emergency & Resuscitation',
        'brand' => 'Adrenaline 1:1000 Inj',
        'generic' => 'Epinephrine 1mg/ml',
        'dosage_form' => 'Injection',
        'strength' => '1mg/ml Ampoule',
        'reorder' => 30,
        'uom' => 'Ampoule',
        'rx_required' => 1,
        'is_controlled' => 0,
        'schedule' => 'Crash Cart Life-Saving',
        'storage' => 'Protect from Light (2-8°C or <25°C)',
        'batch' => 'BT-ADR-9021',
        'mfg' => date('Y-m-d', strtotime('-2 months')),
        'expiry' => date('Y-m-d', strtotime('+14 months')),
        'cost' => 85.00,
        'sale' => 130.00,
        'qty' => 100,
        'status' => 'Active'
    ]
];

foreach ($meds as $m) {
    $catId = $catMap[$m['category']] ?? 1;

    // Check if drug item exists
    $stmt = $db->prepare("SELECT id FROM `drug_items` WHERE `brand_name` = ? LIMIT 1");
    $stmt->execute([$m['brand']]);
    $drugId = $stmt->fetchColumn();

    if (!$drugId) {
        $ins = $db->prepare("
            INSERT INTO `drug_items` 
            (`category_id`, `brand_name`, `generic_name`, `dosage_form`, `strength`, `reorder_level`, `unit_of_measure`, `requires_prescription`, `is_controlled_substance`, `narcotic_schedule`, `storage_condition`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        $ins->execute([
            $catId, $m['brand'], $m['generic'], $m['dosage_form'], $m['strength'],
            $m['reorder'], $m['uom'], $m['rx_required'], $m['is_controlled'],
            $m['schedule'], $m['storage']
        ]);
        $drugId = $db->lastInsertId();
    } else {
        $upd = $db->prepare("
            UPDATE `drug_items` 
            SET `is_controlled_substance` = ?, `narcotic_schedule` = ?, `storage_condition` = ?, `dosage_form` = ?, `generic_name` = ?
            WHERE `id` = ?
        ");
        $upd->execute([$m['is_controlled'], $m['schedule'], $m['storage'], $m['dosage_form'], $m['generic'], $drugId]);
    }

    // Insert or update batch
    $bStmt = $db->prepare("SELECT id FROM `drug_batches` WHERE `drug_item_id` = ? AND `batch_number` = ? LIMIT 1");
    $bStmt->execute([$drugId, $m['batch']]);
    $batchId = $bStmt->fetchColumn();

    if (!$batchId) {
        $insB = $db->prepare("
            INSERT INTO `drug_batches`
            (`drug_item_id`, `batch_number`, `manufacturer`, `mfg_date`, `expiry_date`, `unit_purchase_price`, `unit_sale_price`, `initial_quantity`, `current_quantity`, `status`)
            VALUES (?, ?, 'Global Pharma Ltd', ?, ?, ?, ?, ?, ?, ?)
        ");
        $insB->execute([
            $drugId, $m['batch'], $m['mfg'], $m['expiry'], $m['cost'], $m['sale'], $m['qty'], $m['qty'], $m['status']
        ]);
    } else {
        $updB = $db->prepare("
            UPDATE `drug_batches`
            SET `expiry_date` = ?, `unit_purchase_price` = ?, `unit_sale_price` = ?, `current_quantity` = ?, `status` = ?
            WHERE `id` = ?
        ");
        $updB->execute([$m['expiry'], $m['cost'], $m['sale'], $m['qty'], $m['status'], $batchId]);
    }

    // ALSO sync into inventory_items for Central Stores tracking
    $invStmt = $db->prepare("SELECT id FROM `inventory_items` WHERE `item_name` = ? LIMIT 1");
    $invStmt->execute([$m['brand']]);
    $invId = $invStmt->fetchColumn();

    $invCatId = 1; // Default consumable/pharma
    $itemCode = 'MED-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $m['brand']), 0, 6)) . '-' . rand(100, 999);

    if (!$invId) {
        $insInv = $db->prepare("
            INSERT INTO `inventory_items`
            (`category_id`, `item_name`, `generic_name`, `dosage_form`, `is_narcotic`, `narcotic_schedule`, `storage_condition`, `batch_number`, `expiry_date`, `item_code`, `unit_of_measure`, `current_stock`, `reorder_level`, `unit_cost_price`, `unit_sale_price`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        $insInv->execute([
            $invCatId, $m['brand'], $m['generic'], $m['dosage_form'], $m['is_controlled'],
            $m['schedule'], $m['storage'], $m['batch'], $m['expiry'], $itemCode, $m['uom'],
            $m['qty'], $m['reorder'], $m['cost'], $m['sale']
        ]);
    } else {
        $updInv = $db->prepare("
            UPDATE `inventory_items`
            SET `generic_name` = ?, `dosage_form` = ?, `is_narcotic` = ?, `narcotic_schedule` = ?,
                `storage_condition` = ?, `batch_number` = ?, `expiry_date` = ?, `current_stock` = ?,
                `unit_cost_price` = ?, `unit_sale_price` = ?
            WHERE `id` = ?
        ");
        $updInv->execute([
            $m['generic'], $m['dosage_form'], $m['is_controlled'], $m['schedule'],
            $m['storage'], $m['batch'], $m['expiry'], $m['qty'], $m['cost'], $m['sale'], $invId
        ]);
    }
}

echo "SUCCESS: Comprehensive medicines seeded across Pharmacy & Central Stores inventory with Narcotics & Expiry tracking!\n";
