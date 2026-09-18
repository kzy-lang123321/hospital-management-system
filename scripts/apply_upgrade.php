<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

$sql = file_get_contents(__DIR__ . '/../database/upgrade_inventory_narcotics.sql');
try {
    $db->exec($sql);
    echo "SUCCESS: Database upgraded for inventory medicines, narcotics, and expiry tracking.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
