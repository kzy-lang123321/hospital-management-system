import pymysql
import ssl

TABLE_KEYS = [
    ('departments', 'code', 'code'),
    ('wards', 'code', 'code'),
    ('tariff_services', 'service_code', 'service_code'),
    ('lab_tests', 'test_code', 'test_code'),
    ('radiology_services', 'service_code', 'service_code'),
    ('suppliers', 'code', 'code'),
    ('ot_rooms', 'room_number', 'room_number'),
    ('patients', 'mrn', 'mrn'),
    ('appointments', 'appointment_number', 'appointment_number'),
    ('bills', 'bill_number', 'bill_number'),
    ('prescriptions', 'prescription_number', 'prescription_number'),
    ('pharmacy_sales', 'sale_invoice_number', 'sale_invoice_number'),
    ('lab_orders', 'order_number', 'order_number'),
    ('radiology_orders', 'order_number', 'order_number'),
    ('encounters', 'encounter_number', 'encounter_number'),
    ('insurance_claims', 'claim_number', 'claim_number'),
    ('purchase_orders', 'po_number', 'po_number'),
    ('surgery_bookings', 'booking_number', 'booking_number'),
    ('drug_categories', 'category_name', 'category_name'),
    ('lab_categories', 'category_name', 'category_name'),
    ('lab_categories', 'code', 'code'),
    ('inventory_categories', 'code', 'code'),
    ('inventory_items', 'item_code', 'item_code'),
    ('insurance_companies', 'company_code', 'company_code'),
    ('blood_donors', 'donor_number', 'donor_number'),
    ('blood_requests', 'request_number', 'request_number'),
    ('blood_units', 'unit_barcode', 'unit_barcode'),
]

def migrate_db(conn, label):
    print(f"\n--- Migrating {label} ---")
    cur = conn.cursor()
    for table, index_name, col_name in TABLE_KEYS:
        # Check if existing index exists
        cur.execute(f"SHOW INDEX FROM `{table}` WHERE Key_name = '{index_name}'")
        idx = cur.fetchone()
        if idx:
            try:
                cur.execute(f"ALTER TABLE `{table}` DROP INDEX `{index_name}`")
                print(f"Dropped index `{index_name}` on `{table}`")
            except Exception as e:
                print(f"Notice dropping `{index_name}` on `{table}`: {e}")
        
        # Add compound unique index (hospital_id, col_name)
        new_idx_name = f"uk_hosp_{table}_{col_name}"
        cur.execute(f"SHOW INDEX FROM `{table}` WHERE Key_name = '{new_idx_name}'")
        if not cur.fetchone():
            try:
                cur.execute(f"ALTER TABLE `{table}` ADD UNIQUE KEY `{new_idx_name}` (`hospital_id`, `{col_name}`)")
                print(f"Added compound unique index `{new_idx_name}` (`hospital_id`, `{col_name}`) on `{table}`")
            except Exception as e:
                print(f"Error adding compound index to `{table}`: {e}")
    conn.commit()
    print(f"Done migrating {label}.")

# 1. Local MySQL
try:
    local_conn = pymysql.connect(host='127.0.0.1', port=3306, user='root', password='', database='hms_db')
    migrate_db(local_conn, "Local MariaDB")
    local_conn.close()
except Exception as e:
    print(f"Local migration error: {e}")

# 2. TiDB Cloud MySQL
try:
    tidb_conn = pymysql.connect(
        host='gateway01.ap-northeast-1.prod.aws.tidbcloud.com',
        port=4000,
        user='3VSX4nC8bwXZ48s.root',
        password='6ZCpy9OJaZvISJkS',
        database='hms_db',
        ssl={'ssl': True}
    )
    migrate_db(tidb_conn, "TiDB Cloud")
    tidb_conn.close()
except Exception as e:
    print(f"TiDB Cloud migration error: {e}")
