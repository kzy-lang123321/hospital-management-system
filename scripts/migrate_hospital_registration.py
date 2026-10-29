import pymysql

REGISTRATION_TABLE_SQL = """
CREATE TABLE IF NOT EXISTS `hospital_registration_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tracking_code` varchar(32) NOT NULL,
    `applicant_name` varchar(150) NOT NULL,
    `applicant_email` varchar(150) NOT NULL,
    `applicant_phone` varchar(50) NOT NULL,
    `applicant_designation` varchar(100) DEFAULT 'Hospital Owner',
    `password_hash` varchar(255) NOT NULL,
    `hospital_name` varchar(200) NOT NULL,
    `hospital_type` varchar(50) DEFAULT 'General Hospital',
    `license_number` varchar(100) DEFAULT NULL,
    `address` text NOT NULL,
    `city` varchar(100) NOT NULL,
    `district` varchar(100) DEFAULT NULL,
    `country` varchar(100) DEFAULT 'Pakistan',
    `contact_phone` varchar(50) DEFAULT NULL,
    `official_email` varchar(150) DEFAULT NULL,
    `logo_url` varchar(255) DEFAULT NULL,
    `bed_count` int(11) DEFAULT NULL,
    `departments_json` text DEFAULT NULL,
    `modules_json` text DEFAULT NULL,
    `time_zone` varchar(64) DEFAULT 'Asia/Karachi',
    `currency_symbol` varchar(16) DEFAULT 'PKR ',
    `notes` text DEFAULT NULL,
    `status` enum('Pending Approval','Under Review','Additional Information Required','Approved','Rejected') NOT NULL DEFAULT 'Pending Approval',
    `developer_notes` text DEFAULT NULL,
    `applicant_reply` text DEFAULT NULL,
    `reviewed_by` int(11) DEFAULT NULL,
    `reviewed_at` datetime DEFAULT NULL,
    `created_hospital_id` int(11) DEFAULT NULL,
    `created_user_id` int(11) DEFAULT NULL,
    `email_verified` tinyint(1) NOT NULL DEFAULT 1,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_reg_tracking` (`tracking_code`),
    KEY `idx_reg_email` (`applicant_email`),
    KEY `idx_reg_status` (`status`),
    KEY `idx_reg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

NOTIFICATIONS_TABLE_SQL = """
CREATE TABLE IF NOT EXISTS `platform_notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `recipient_role` varchar(50) NOT NULL DEFAULT 'developer',
    `title` varchar(200) NOT NULL,
    `message` text NOT NULL,
    `action_url` varchar(255) DEFAULT NULL,
    `reference_type` varchar(50) DEFAULT 'hospital_registration',
    `reference_id` int(11) DEFAULT NULL,
    `is_read` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_notif_role_read` (`recipient_role`, `is_read`),
    KEY `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

def check_and_add_column(cur, table, col, defn):
    cur.execute(f"SHOW COLUMNS FROM `{table}` LIKE '{col}'")
    if not cur.fetchone():
        try:
            cur.execute(f"ALTER TABLE `{table}` ADD COLUMN `{col}` {defn}")
            print(f"Added column `{col}` to `{table}`")
        except Exception as e:
            print(f"Notice adding column `{col}` to `{table}`: {e}")

def run_migration(conn, label):
    print(f"\n======================================")
    print(f"Running Registration Portal Migration on: {label}")
    print(f"======================================")
    cur = conn.cursor()
    cur.execute(REGISTRATION_TABLE_SQL)
    print("Table `hospital_registration_requests` created / verified.")
    check_and_add_column(cur, 'hospital_registration_requests', 'notes', 'text DEFAULT NULL')
    cur.execute(NOTIFICATIONS_TABLE_SQL)
    print("Table `platform_notifications` created / verified.")
    conn.commit()

def main():
    # 1. Local MariaDB
    try:
        local_conn = pymysql.connect(
            host='127.0.0.1',
            port=3306,
            user='root',
            password='',
            database='hms_db',
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor
        )
        run_migration(local_conn, "Local MariaDB (127.0.0.1:3306)")
        local_conn.close()
    except Exception as e:
        print(f"Error on Local MariaDB: {e}")

    # 2. TiDB Cloud
    try:
        cloud_conn = pymysql.connect(
            host='gateway01.ap-northeast-1.prod.aws.tidbcloud.com',
            port=4000,
            user='3VSX4nC8bwXZ48s.root',
            password='6ZCpy9OJaZvISJkS',
            database='hms_db',
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor,
            ssl={'ca': None}
        )
        run_migration(cloud_conn, "TiDB Cloud Serverless (gateway01.ap-northeast-1.prod.aws.tidbcloud.com:4000)")
        cloud_conn.close()
    except Exception as e:
        print(f"Error on TiDB Cloud: {e}")

if __name__ == '__main__':
    main()
