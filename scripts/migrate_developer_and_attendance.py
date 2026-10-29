import pymysql

MIGRATION_SQL = """
-- 1. Ensure Developer Role exists with slug 'developer'
INSERT INTO `roles` (`id`, `role_name`, `slug`, `description`, `is_system_role`, `created_at`)
VALUES (10, 'Platform Developer / Company Provider', 'developer', 'Full platform monitoring, hospital lifecycle management and aggregate analytics', 1, NOW())
ON DUPLICATE KEY UPDATE 
    `role_name` = 'Platform Developer / Company Provider',
    `slug` = 'developer',
    `description` = 'Full platform monitoring, hospital lifecycle management and aggregate analytics';

-- 2. Seed Developer Account: khanabbasi5642@gmail.com
-- Default password: DevProvider@2026!
-- Hash generated via bcrypt
INSERT INTO `users` (
    `hospital_id`, `username`, `email`, `password_hash`, `full_name`,
    `phone`, `role_id`, `department_id`, `designation`, `status`, `created_at`
) VALUES (
    NULL,
    'khanabbasi',
    'khanabbasi5642@gmail.com',
    '$2y$10$wL4P1Hh98PZsqZ0Vv83xye18V5eJtz5/gJkG59p9z0K.r5cWfKq5W',
    'Platform Developer',
    '0300-1234567',
    10,
    NULL,
    'Platform Developer / Company Provider',
    'Active',
    NOW()
) ON DUPLICATE KEY UPDATE 
    `role_id` = 10,
    `password_hash` = '$2y$10$wL4P1Hh98PZsqZ0Vv83xye18V5eJtz5/gJkG59p9z0K.r5cWfKq5W',
    `status` = 'Active',
    `designation` = 'Platform Developer / Company Provider';

-- 3. Add GPS Geofence & Attendance Settings to hospital_profiles if not present
"""

def check_and_add_column(cur, table, col, defn):
    cur.execute(f"SHOW COLUMNS FROM `{table}` LIKE '{col}'")
    if not cur.fetchone():
        try:
            cur.execute(f"ALTER TABLE `{table}` ADD COLUMN `{col}` {defn}")
            print(f"Added column `{col}` to `{table}`")
        except Exception as e:
            print(f"Notice adding column `{col}` to `{table}`: {e}")

ATTENDANCE_TABLE_SQL = """
CREATE TABLE IF NOT EXISTS `staff_attendance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `hospital_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `shift_id` int(11) DEFAULT NULL,
    `attendance_date` date NOT NULL,
    `check_in_time` datetime NOT NULL,
    `check_in_latitude` decimal(10,8) DEFAULT NULL,
    `check_in_longitude` decimal(11,8) DEFAULT NULL,
    `check_in_accuracy` float DEFAULT NULL,
    `check_in_distance_meters` float DEFAULT NULL,
    `check_in_status` enum('Verified','Late','Out_Of_Bounds','Exception','Rejected') NOT NULL DEFAULT 'Verified',
    `check_out_time` datetime DEFAULT NULL,
    `check_out_latitude` decimal(10,8) DEFAULT NULL,
    `check_out_longitude` decimal(11,8) DEFAULT NULL,
    `check_out_accuracy` float DEFAULT NULL,
    `check_out_distance_meters` float DEFAULT NULL,
    `check_out_status` enum('Verified','Early_Departure','Out_Of_Bounds','Auto_Closed') DEFAULT NULL,
    `total_hours` decimal(5,2) DEFAULT NULL,
    `verification_method` enum('GPS_GeoFence','Kiosk_QR','Supervisor_Manual') NOT NULL DEFAULT 'GPS_GeoFence',
    `is_suspicious` tinyint(1) NOT NULL DEFAULT 0,
    `suspicion_reason` varchar(255) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_att_hosp_date` (`hospital_id`, `attendance_date`),
    KEY `idx_att_user_date` (`user_id`, `attendance_date`),
    KEY `idx_att_status` (`check_in_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

EXCEPTIONS_TABLE_SQL = """
CREATE TABLE IF NOT EXISTS `attendance_exceptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `hospital_id` int(11) NOT NULL,
    `attendance_id` int(11) DEFAULT NULL,
    `user_id` int(11) NOT NULL,
    `request_type` enum('Missed_Checkin','Location_Override','Late_Exemption','Leave_Request') NOT NULL,
    `reason` text NOT NULL,
    `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `reviewed_by` int(11) DEFAULT NULL,
    `review_notes` text DEFAULT NULL,
    `reviewed_at` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_exc_hosp` (`hospital_id`),
    KEY `idx_exc_user` (`user_id`),
    KEY `idx_exc_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

def run_migration(conn, label):
    print(f"\n======================================")
    print(f"Running Developer & Attendance Migration on: {label}")
    print(f"======================================")
    cur = conn.cursor()
    
    # 1. Update roles & seed developer
    for statement in MIGRATION_SQL.strip().split(';'):
        stmt = statement.strip()
        if stmt:
            try:
                cur.execute(stmt)
            except Exception as e:
                print(f"Notice executing statement: {e}")
    conn.commit()
    print("Role & Developer account seeded.")
    
    # 2. Add GPS Geofence columns to hospital_profiles
    check_and_add_column(cur, 'hospital_profiles', 'hospital_latitude', 'decimal(10,8) DEFAULT 34.01513700')
    check_and_add_column(cur, 'hospital_profiles', 'hospital_longitude', 'decimal(11,8) DEFAULT 71.52491500')
    check_and_add_column(cur, 'hospital_profiles', 'geofence_radius_meters', 'int(11) DEFAULT 300')
    check_and_add_column(cur, 'hospital_profiles', 'attendance_grace_minutes', 'int(11) DEFAULT 15')
    check_and_add_column(cur, 'hospital_profiles', 'attendance_window_before_mins', 'int(11) DEFAULT 30')
    check_and_add_column(cur, 'hospital_profiles', 'attendance_window_after_mins', 'int(11) DEFAULT 120')
    conn.commit()
    print("Hospital GPS Geofence columns verified.")
    
    # 3. Create staff_attendance table
    try:
        cur.execute(ATTENDANCE_TABLE_SQL)
        conn.commit()
        print("staff_attendance table ready.")
    except Exception as e:
        print(f"Notice creating staff_attendance: {e}")
        
    # 4. Create attendance_exceptions table
    try:
        cur.execute(EXCEPTIONS_TABLE_SQL)
        conn.commit()
        print("attendance_exceptions table ready.")
    except Exception as e:
        print(f"Notice creating attendance_exceptions: {e}")
        
    print(f"Migration completed for {label}!")

# Execute on Local MariaDB
try:
    local_conn = pymysql.connect(host='127.0.0.1', port=3306, user='root', password='', database='hms_db')
    run_migration(local_conn, "Local MariaDB (127.0.0.1:3306)")
    local_conn.close()
except Exception as e:
    print(f"Local MariaDB Migration Error: {e}")

# Execute on TiDB Cloud
try:
    tidb_conn = pymysql.connect(
        host='gateway01.ap-northeast-1.prod.aws.tidbcloud.com',
        port=4000,
        user='3VSX4nC8bwXZ48s.root',
        password='6ZCpy9OJaZvISJkS',
        database='hms_db',
        ssl={'ssl': True}
    )
    run_migration(tidb_conn, "TiDB Cloud Serverless (gateway01.ap-northeast-1.prod.aws.tidbcloud.com)")
    tidb_conn.close()
except Exception as e:
    print(f"TiDB Cloud Migration Error: {e}")
