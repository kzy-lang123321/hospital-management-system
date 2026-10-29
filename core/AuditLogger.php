<?php
/**
 * Hospital Management System (HMS)
 * Immutable Audit Logger Service - Multi-Hospital Aware
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Tenant.php';

class AuditLogger {
    public static function log(string $eventType, string $tableName, $recordId = null, $oldValues = null, $newValues = null): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO `audit_logs` 
                (`hospital_id`, `user_id`, `event_type`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $hospitalId = Tenant::id();
            $userId = $_SESSION['user']['id'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Browser', 0, 250);

            $oldJson = $oldValues !== null ? (is_string($oldValues) ? $oldValues : json_encode($oldValues)) : null;
            $newJson = $newValues !== null ? (is_string($newValues) ? $newValues : json_encode($newValues)) : null;

            $stmt->execute([
                $hospitalId,
                $userId,
                $eventType,
                $tableName,
                (string)$recordId,
                $oldJson,
                $newJson,
                $ipAddress,
                $userAgent
            ]);
        } catch (Exception $e) {
            error_log("HMS Audit Log Error: " . $e->getMessage());
        }
    }
}
