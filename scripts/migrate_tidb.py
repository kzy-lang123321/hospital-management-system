import pymysql

host = 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com'
port = 4000
user = '3VSX4nC8bwXZ48s.root'
password = '6ZCpy9OJaZvISJkS'
database = 'hms_db'

conn = pymysql.connect(host=host, port=port, user=user, password=password, database=database, ssl={'ssl': True})

tables = [
    'users', 'departments', 'patients', 'patient_allergies', 'encounters', 'clinical_vitals',
    'consultation_notes', 'nursing_notes', 'appointments', 'prescriptions', 'prescription_items',
    'drug_categories', 'drug_items', 'drug_batches', 'pharmacy_sales', 'pharmacy_sale_items',
    'lab_categories', 'lab_tests', 'lab_test_parameters', 'lab_orders', 'lab_order_items', 'lab_results',
    'radiology_services', 'radiology_orders', 'radiology_reports', 'wards', 'rooms', 'beds',
    'bed_allocations', 'admissions', 'discharge_summaries', 'ot_rooms', 'surgery_bookings',
    'surgical_records', 'tariff_services', 'bills', 'bill_items', 'payments', 'insurance_companies',
    'insurance_claims', 'inventory_categories', 'inventory_items', 'suppliers', 'purchase_orders',
    'purchase_order_items', 'blood_donors', 'blood_units', 'blood_requests', 'staff_shifts',
    'staff_roster', 'audit_logs'
]

with conn.cursor() as cur:
    # 1. hospital_profiles columns
    profile_cols = [
        ('hospital_code', 'VARCHAR(50) NULL'),
        ('logo_path', 'VARCHAR(255) NULL'),
        ('theme_color', "VARCHAR(30) DEFAULT '#0d9488'"),
        ('date_format', "VARCHAR(20) DEFAULT 'd M, Y'"),
        ('mrn_prefix', "VARCHAR(20) DEFAULT 'MRN'"),
        ('invoice_prefix', "VARCHAR(20) DEFAULT 'INV'"),
        ('status', "ENUM('Active', 'Suspended', 'Pending') DEFAULT 'Active'"),
        ('owner_user_id', 'INT(11) NULL'),
        ('subscription_plan', "VARCHAR(50) DEFAULT 'Enterprise'"),
        ('modules_enabled', 'TEXT NULL')
    ]
    for col, col_type in profile_cols:
        try:
            cur.execute(f"ALTER TABLE `hospital_profiles` ADD COLUMN `{col}` {col_type};")
            print(f"Added {col} to hospital_profiles")
        except Exception as e:
            if 'Duplicate column' not in str(e):
                print(f"Notice on {col}: {e}")

    try:
        cur.execute("""
            UPDATE `hospital_profiles` SET
                `hospital_code` = 'HAYAT',
                `theme_color` = '#0d9488',
                `date_format` = 'd M, Y',
                `mrn_prefix` = 'MRN',
                `invoice_prefix` = 'INV',
                `status` = 'Active',
                `owner_user_id` = 1,
                `subscription_plan` = 'Enterprise',
                `modules_enabled` = '{"opd":true,"ipd":true,"emergency":true,"ot":true,"pharmacy":true,"laboratory":true,"radiology":true,"blood_bank":true,"billing":true,"inventory":true,"insurance":true,"reports":true}'
            WHERE `id` = 1;
        """)
        print("Updated hospital_profiles record 1")
    except Exception as e:
        print("hospital_profiles update error:", e)

    # Roles
    try:
        cur.execute("""
            INSERT INTO `roles` (`id`, `role_name`, `slug`, `description`, `is_system_role`, `created_at`)
            VALUES 
                (10, 'Platform Super Administrator', 'platform_admin', 'Full unrestricted control across all hospitals, subscriptions and platform audits', 1, NOW()),
                (11, 'Hospital Owner / Super Admin', 'hospital_owner', 'Full executive administration and customization of the assigned hospital', 1, NOW())
            ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
        """)
        print("Inserted platform & hospital_owner roles")
    except Exception as e:
        print("Role insert error:", e)

    # 2. Add hospital_id to each table
    for t in tables:
        try:
            cur.execute(f"ALTER TABLE `{t}` ADD COLUMN `hospital_id` INT(11) NOT NULL DEFAULT 1;")
            print(f"Added hospital_id to {t}")
        except Exception as e:
            if 'Duplicate column' not in str(e):
                print(f"Error on {t}: {e}")

    # Allow NULL for users and audit_logs
    try:
        cur.execute("ALTER TABLE `users` MODIFY COLUMN `hospital_id` INT(11) NULL DEFAULT 1;")
        cur.execute("ALTER TABLE `audit_logs` MODIFY COLUMN `hospital_id` INT(11) NULL DEFAULT 1;")
    except Exception as e:
        pass

    # Create platform.admin
    try:
        cur.execute("""
            INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role_id`, `hospital_id`, `designation`, `status`, `created_at`)
            SELECT 'platform.admin', 'platform.admin@hms-system.org', '$2y$10$wK1Rk2p8z5.rEpeR3Rk8p7fWw2Nq3c8hJ1Z7g8u9w12345', 'Platform Super Administrator', 10, NULL, 'Chief Platform Architect', 'Active', NOW()
            FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'platform.admin');
        """)
        print("Created platform.admin user")
    except Exception as e:
        print("Platform user error:", e)

conn.commit()
print("ALL TABLES UPGRADED ON TIDB CLOUD SUCCESSFULLY!")
conn.close()
