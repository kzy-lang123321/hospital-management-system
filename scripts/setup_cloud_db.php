<?php
/**
 * Setup and Seed Cloud Database (TiDB Cloud)
 */

$host = 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = '3VSX4nC8bwx748s.root';
$pass = '6ZCpy9OJaZvISJkS';
$dbname = 'hms_db';

echo "[*] Connecting to TiDB Cloud ($host:$port)...\n";

$caCert = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_SSL_CA => $caCert,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

try {
    // 1. Connect without db name first
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "[OK] Successfully connected to TiDB Cloud cluster with SSL!\n";

    // 2. Create hms_db database if not exists
    echo "[*] Creating database `$dbname`...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "[OK] Database `$dbname` is ready.\n";

    // 3. Connect to hms_db
    $dsnDb = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdoDb = new PDO($dsnDb, $user, $pass, $options);

    // 4. Import schema.sql
    echo "[*] Importing schema.sql...\n";
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at $schemaFile");
    }
    $schemaSql = file_get_contents($schemaFile);
    $pdoDb->exec($schemaSql);
    echo "[OK] Schema successfully imported!\n";

    // 5. Import clean_transactions.sql
    echo "[*] Importing clean_transactions.sql...\n";
    $seedFile = __DIR__ . '/../database/clean_transactions.sql';
    if (!file_exists($seedFile)) {
        throw new Exception("clean_transactions.sql not found at $seedFile");
    }
    $seedSql = file_get_contents($seedFile);
    $pdoDb->exec($seedSql);
    echo "[OK] Operational seed data successfully imported!\n";

    // 6. Verify tables
    $stmt = $pdoDb->query("SHOW TABLES;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "[SUCCESS] Found " . count($tables) . " tables in `$dbname`:\n";
    echo implode(', ', array_slice($tables, 0, 10)) . "... and more.\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
