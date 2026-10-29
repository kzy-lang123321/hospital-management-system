import pymysql

SESSIONS_TABLE_SQL = """
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` varchar(128) NOT NULL,
    `data` mediumtext NOT NULL,
    `expiry` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_expiry` (`expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

def run_migration(conn, label):
    print(f"\n======================================")
    print(f"Creating Sessions Table on: {label}")
    print(f"======================================")
    cur = conn.cursor()
    cur.execute(SESSIONS_TABLE_SQL)
    print("Table `sessions` created / verified.")
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
            charset='utf8mb4'
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
            ssl={'ca': None}
        )
        run_migration(cloud_conn, "TiDB Cloud Serverless (gateway01.ap-northeast-1.prod.aws.tidbcloud.com:4000)")
        cloud_conn.close()
    except Exception as e:
        print(f"Error on TiDB Cloud: {e}")

if __name__ == '__main__':
    main()
