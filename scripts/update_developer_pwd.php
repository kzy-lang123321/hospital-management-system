<?php
require_once __DIR__ . '/../config/database.php';

$newPassword = 'Developer@2026!';
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

echo "Generated hash: $hash\n";

// 1. Update Local DB
$localDb = Database::getConnection();
$stmt = $localDb->prepare("UPDATE `users` SET `password_hash` = ?, `status` = 'Active' WHERE `email` = 'khanabbasi5642@gmail.com'");
$stmt->execute([$hash]);
echo "Updated Local DB. Rows affected: " . $stmt->rowCount() . "\n";

// 2. Update TiDB Cloud DB
$tidbHost = 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com';
$tidbPort = 4000;
$tidbUser = '3VSX4nC8bwXZ48s.root';
$tidbPass = '6ZCpy9OJaZvISJkS';
$tidbDb   = 'hms_db';

try {
    $dsn = "mysql:host=$tidbHost;port=$tidbPort;dbname=$tidbDb;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    $cloudDb = new PDO($dsn, $tidbUser, $tidbPass, $options);
    $cStmt = $cloudDb->prepare("UPDATE `users` SET `password_hash` = ?, `status` = 'Active' WHERE `email` = 'khanabbasi5642@gmail.com'");
    $cStmt->execute([$hash]);
    echo "Updated TiDB Cloud DB. Rows affected: " . $cStmt->rowCount() . "\n";
} catch (Exception $e) {
    echo "TiDB Cloud update error: " . $e->getMessage() . "\n";
}
