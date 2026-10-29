<?php
/**
 * Hospital Management System (HMS)
 * Location-Verified Staff Attendance & Anti-Fraud Engine
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Tenant.php';
require_once __DIR__ . '/AuditLogger.php';

class AttendanceService {

    /**
     * Calculate Haversine distance between two coordinates in meters
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $earthRadius = 6371000; // in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    /**
     * Process staff GPS check-in
     */
    public static function processCheckIn(int $userId, ?float $latitude, ?float $longitude, ?float $accuracy, string $notes = ''): array {
        $db = Database::getConnection();

        // 1. Fetch user details & hospital
        $uStmt = $db->prepare("SELECT id, hospital_id, full_name, department_id FROM `users` WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['hospital_id'])) {
            return ['success' => false, 'message' => 'Staff member is not assigned to a valid hospital organization.'];
        }

        $hId = (int)$user['hospital_id'];

        // 2. Check for active unclosed check-in session today
        $today = date('Y-m-d');
        $activeStmt = $db->prepare("
            SELECT id, check_in_time FROM `staff_attendance` 
            WHERE user_id = ? AND attendance_date = ? AND check_out_time IS NULL 
            LIMIT 1
        ");
        $activeStmt->execute([$userId, $today]);
        if ($activeStmt->fetch()) {
            return ['success' => false, 'message' => 'You already have an active check-in session for today. Please check out before checking in again.'];
        }

        // 3. Fetch Hospital Geofence Settings
        $hProfile = Tenant::profile($hId);
        $hLat = (float)($hProfile['hospital_latitude'] ?? 34.01513700);
        $hLon = (float)($hProfile['hospital_longitude'] ?? 71.52491500);
        $maxRadius = (int)($hProfile['geofence_radius_meters'] ?? 300);
        $graceMins = (int)($hProfile['attendance_grace_minutes'] ?? 15);

        // 4. Geolocation distance verification
        $distance = null;
        $isOutOfBounds = false;
        $isSuspicious = 0;
        $suspicionReason = null;

        if ($latitude !== null && $longitude !== null) {
            $distance = self::calculateDistance($latitude, $longitude, $hLat, $hLon);

            // Anti-fraud: excessive inaccuracy (> 500m)
            if ($accuracy !== null && $accuracy > 500) {
                $isSuspicious = 1;
                $suspicionReason = "Excessive GPS inaccuracy ({$accuracy}m radius). Potential spoofed or degraded signal.";
            }

            if ($distance > $maxRadius) {
                $isOutOfBounds = true;
                if (!$isSuspicious) {
                    $suspicionReason = "Location outside registered hospital geofence ($distance meters away, max permitted: $maxRadius meters).";
                }
            }
        } else {
            $isOutOfBounds = true;
            $suspicionReason = "Device GPS coordinates were not provided or blocked by browser.";
        }

        // 5. Shift & Time-Window Validation (Check against active shift roster or default 08:00 start)
        $shiftStmt = $db->prepare("
            SELECT ss.* FROM `staff_roster` sr
            JOIN `staff_shifts` ss ON sr.shift_id = ss.id
            WHERE sr.user_id = ? AND sr.duty_date = ?
            LIMIT 1
        ");
        $shiftStmt->execute([$userId, $today]);
        $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

        $shiftId = $shift['id'] ?? null;
        $shiftStart = $shift['start_time'] ?? '08:00:00';
        $shiftStartTimestamp = strtotime($today . ' ' . $shiftStart);
        $currentTimestamp = time();

        $isLate = false;
        if ($currentTimestamp > ($shiftStartTimestamp + ($graceMins * 60))) {
            $isLate = true;
        }

        // Determine status
        if ($isOutOfBounds) {
            $checkInStatus = 'Out_Of_Bounds';
        } elseif ($isLate) {
            $checkInStatus = 'Late';
        } else {
            $checkInStatus = 'Verified';
        }

        // 6. Record Attendance
        $ins = $db->prepare("
            INSERT INTO `staff_attendance` (
                `hospital_id`, `user_id`, `shift_id`, `attendance_date`, `check_in_time`,
                `check_in_latitude`, `check_in_longitude`, `check_in_accuracy`, `check_in_distance_meters`,
                `check_in_status`, `verification_method`, `is_suspicious`, `suspicion_reason`, `notes`
            ) VALUES (
                ?, ?, ?, ?, NOW(),
                ?, ?, ?, ?,
                ?, 'GPS_GeoFence', ?, ?, ?
            )
        ");

        $ins->execute([
            $hId, $userId, $shiftId, $today,
            $latitude, $longitude, $accuracy, $distance,
            $checkInStatus, $isSuspicious, $suspicionReason, $notes
        ]);

        $attId = (int)$db->lastInsertId();

        AuditLogger::log('CHECK_IN', 'staff_attendance', $attId, null, [
            'status' => $checkInStatus,
            'distance_m' => $distance,
            'user' => $user['full_name']
        ]);

        return [
            'success' => true,
            'attendance_id' => $attId,
            'status' => $checkInStatus,
            'distance_meters' => $distance,
            'is_out_of_bounds' => $isOutOfBounds,
            'is_late' => $isLate,
            'message' => $isOutOfBounds 
                ? "Check-in logged as Out-of-Bounds ({$distance}m away). Please submit an exception appeal for supervisor approval." 
                : ($isLate ? "Check-in verified (Late arrival logged)." : "Check-in successfully verified within hospital perimeter.")
        ];
    }

    /**
     * Process staff GPS check-out
     */
    public static function processCheckOut(int $userId, ?float $latitude, ?float $longitude, ?float $accuracy): array {
        $db = Database::getConnection();

        $today = date('Y-m-d');
        $stmt = $db->prepare("
            SELECT sa.*, hp.hospital_latitude, hp.hospital_longitude, hp.geofence_radius_meters 
            FROM `staff_attendance` sa
            JOIN `hospital_profiles` hp ON sa.hospital_id = hp.id
            WHERE sa.user_id = ? AND sa.attendance_date = ? AND sa.check_out_time IS NULL
            ORDER BY sa.id DESC LIMIT 1
        ");
        $stmt->execute([$userId, $today]);
        $att = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$att) {
            return ['success' => false, 'message' => 'No active check-in session found for today.'];
        }

        $distance = null;
        $outStatus = 'Verified';
        if ($latitude !== null && $longitude !== null) {
            $hLat = (float)($att['hospital_latitude'] ?? 34.01513700);
            $hLon = (float)($att['hospital_longitude'] ?? 71.52491500);
            $distance = self::calculateDistance($latitude, $longitude, $hLat, $hLon);
            if ($distance > (int)($att['geofence_radius_meters'] ?? 300)) {
                $outStatus = 'Out_Of_Bounds';
            }
        }

        // Calculate total hours
        $checkInTime = strtotime($att['check_in_time']);
        $checkOutTime = time();
        $totalHours = round(($checkOutTime - $checkInTime) / 3600, 2);

        $upd = $db->prepare("
            UPDATE `staff_attendance` SET
                `check_out_time` = NOW(),
                `check_out_latitude` = ?,
                `check_out_longitude` = ?,
                `check_out_accuracy` = ?,
                `check_out_distance_meters` = ?,
                `check_out_status` = ?,
                `total_hours` = ?
            WHERE id = ?
        ");
        $upd->execute([$latitude, $longitude, $accuracy, $distance, $outStatus, $totalHours, $att['id']]);

        AuditLogger::log('CHECK_OUT', 'staff_attendance', $att['id'], null, [
            'total_hours' => $totalHours,
            'check_out_status' => $outStatus
        ]);

        return [
            'success' => true,
            'total_hours' => $totalHours,
            'message' => "Check-out successfully completed. Total time recorded: {$totalHours} hours."
        ];
    }

    /**
     * Submit attendance exception or regularization request
     */
    public static function submitException(int $userId, ?int $attendanceId, string $requestType, string $reason): array {
        $db = Database::getConnection();

        $uStmt = $db->prepare("SELECT hospital_id FROM `users` WHERE id = ?");
        $uStmt->execute([$userId]);
        $hId = (int)$uStmt->fetchColumn();

        if ($hId <= 0) {
            return ['success' => false, 'message' => 'Invalid hospital affiliation.'];
        }

        $stmt = $db->prepare("
            INSERT INTO `attendance_exceptions` (`hospital_id`, `attendance_id`, `user_id`, `request_type`, `reason`, `status`, `created_at`)
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        $stmt->execute([$hId, $attendanceId, $userId, $requestType, $reason]);

        return ['success' => true, 'message' => 'Exception appeal submitted successfully for supervisor review.'];
    }

    /**
     * Review attendance exception (Approve or Reject)
     */
    public static function reviewException(int $exceptionId, string $status, ?string $reviewNotes, int $reviewerUserId): array {
        $db = Database::getConnection();

        if (!in_array($status, ['Approved', 'Rejected'], true)) {
            return ['success' => false, 'message' => 'Invalid status parameter.'];
        }

        $stmt = $db->prepare("SELECT * FROM `attendance_exceptions` WHERE id = ?");
        $stmt->execute([$exceptionId]);
        $exc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exc) {
            return ['success' => false, 'message' => 'Exception request not found.'];
        }

        Tenant::enforceScope((int)$exc['hospital_id']);

        $upd = $db->prepare("
            UPDATE `attendance_exceptions` SET
                `status` = ?,
                `review_notes` = ?,
                `reviewed_by` = ?,
                `reviewed_at` = NOW()
            WHERE id = ?
        ");
        $upd->execute([$status, $reviewNotes, $reviewerUserId, $exceptionId]);

        // If approved and linked to attendance record, regularize attendance status
        if ($status === 'Approved' && !empty($exc['attendance_id'])) {
            $regStmt = $db->prepare("UPDATE `staff_attendance` SET `check_in_status` = 'Exception', `is_suspicious` = 0 WHERE id = ?");
            $regStmt->execute([$exc['attendance_id']]);
        }

        AuditLogger::log('REVIEW_EXCEPTION', 'attendance_exceptions', $exceptionId, null, [
            'status' => $status,
            'reviewer' => $reviewerUserId
        ]);

        return ['success' => true, 'message' => "Exception request has been marked $status."];
    }

    /**
     * Get user attendance for today
     */
    public static function getTodayRecord(int $userId): ?array {
        $db = Database::getConnection();
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT * FROM `staff_attendance` WHERE user_id = ? AND attendance_date = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get user recent attendance history
     */
    public static function getUserHistory(int $userId, int $limit = 15): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM `staff_attendance` WHERE user_id = ? ORDER BY attendance_date DESC, id DESC LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get hospital daily attendance register
     */
    public static function getHospitalRegister(int $hospitalId, string $date, ?string $status = null): array {
        $db = Database::getConnection();
        $sql = "
            SELECT sa.*, u.full_name as staff_name, u.username, d.name as department_name, r.role_name
            FROM `staff_attendance` sa
            JOIN `users` u ON sa.user_id = u.id
            LEFT JOIN `departments` d ON u.department_id = d.id
            LEFT JOIN `roles` r ON u.role_id = r.id
            WHERE sa.hospital_id = ? AND sa.attendance_date = ?
        ";
        $params = [$hospitalId, $date];
        if (!empty($status)) {
            $sql .= " AND sa.check_in_status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY sa.check_in_time DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending exceptions for supervisor review
     */
    public static function getPendingExceptions(int $hospitalId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT ae.*, u.full_name as staff_name, u.username, sa.check_in_time, sa.check_in_status, sa.check_in_distance_meters
            FROM `attendance_exceptions` ae
            JOIN `users` u ON ae.user_id = u.id
            LEFT JOIN `staff_attendance` sa ON ae.attendance_id = sa.id
            WHERE ae.hospital_id = ? AND ae.status = 'Pending'
            ORDER BY ae.created_at DESC
        ");
        $stmt->execute([$hospitalId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
