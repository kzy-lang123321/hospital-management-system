<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

$hash = password_hash('Platform@123', PASSWORD_BCRYPT);
$stmt = $db->prepare("UPDATE `users` SET `password_hash` = ? WHERE `username` = 'platform.admin'");
$stmt->execute([$hash]);
echo "Updated Local DB hash: $hash\n";

// Update TiDB Cloud as well
try {
    $tidb = new PDO("mysql:host=gateway01.ap-northeast-1.prod.aws.tidbcloud.com;port=4000;dbname=hms_db", "3VSX4nC8bwXZ48s.root", "6ZCpy9OJaZvISJkS", [
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $tStmt = $tidb->prepare("UPDATE `users` SET `password_hash` = ? WHERE `username` = 'platform.admin'");
    $tStmt->execute([$hash]);
    echo "Updated TiDB Cloud DB hash successfully.\n";
} catch (Exception $e) {
    echo "TiDB update notice: " . $e->getMessage() . "\n";
}
