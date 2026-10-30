<?php
/**
 * Hospital Management System (HMS)
 * Database-Backed Session Handler (Serverless / Multi-Instance Safe)
 */

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName): bool {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function close(): bool {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT `data` FROM `sessions` WHERE `id` = ? AND `expiry` > ? LIMIT 1");
            $stmt->execute([$id, time()]);
            $data = $stmt->fetchColumn();
            return $data !== false ? (string)$data : '';
        } catch (Exception $e) {
            return '';
        }
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data): bool {
        try {
            $lifetime = 86400 * 7; // 7 days session retention
            $expiry = time() + $lifetime;
            $stmt = $this->pdo->prepare("REPLACE INTO `sessions` (`id`, `data`, `expiry`) VALUES (?, ?, ?)");
            return (bool)$stmt->execute([$id, $data, $expiry]);
        } catch (Exception $e) {
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `sessions` WHERE `id` = ?");
            return (bool)$stmt->execute([$id]);
        } catch (Exception $e) {
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function gc($maxlifetime) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `sessions` WHERE `expiry` < ?");
            $stmt->execute([time()]);
            return (int)$stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function register(PDO $pdo): void {
        if (headers_sent() || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $handler = new self($pdo);
        session_set_save_handler($handler, true);
    }
}
