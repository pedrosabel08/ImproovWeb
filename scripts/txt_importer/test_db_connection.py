#!/usr/bin/env python3
"""Quick DB connection tester for the importer.

Usage: py -3 test_db_connection.py

It reads DB connection info from the same .env variables as the importer
and performs a TCP check followed by a mysql.connector.connect() attempt
with short timeouts. Prints exceptions and timing to help diagnose hangs.
"""
import time
import socket
import traceback
import os
from dotenv import load_dotenv
load_dotenv()

try:
    import mysql.connector
    from mysql.connector import errorcode
except Exception as e:
    print("mysql.connector not available:", e)
    raise


def main():
    host = os.getenv('IMPORT_DB_HOST', 'mysql.improov.com.br')
    port = int(os.getenv('IMPORT_DB_PORT', '3306'))
    user = os.getenv('IMPORT_DB_USER', 'improov')
    password = os.getenv('IMPORT_DB_PASSWORD', 'Impr00v')
    database = os.getenv('IMPORT_DB_NAME', 'improov')

    print(f"Target: {user}@{host}:{port} (database={database})")

    # TCP test
    try:
        t0 = time.time()
        with socket.create_connection((host, port), timeout=5):
            t1 = time.time()
            print(f"TCP connect OK (elapsed {t1-t0:.2f}s)")
    except Exception as e:
        print(f"TCP connect FAILED: {e}")
        traceback.print_exc()
        return

    # Try mysql.connector with explicit timeouts
    conn_cfg = {
        'host': host,
        'port': port,
        'user': user,
        'password': password,
        'database': database,
        # options to avoid long hangs
        'connection_timeout': 10,
        'connect_timeout': 10,
        'use_pure': True,
    }

    try:
        print("Attempting mysql.connector.connect() with connect_timeout=10")
        t0 = time.time()
        conn = mysql.connector.connect(**conn_cfg)
        t1 = time.time()
        print(f"Connected OK in {t1-t0:.2f}s")
        cur = conn.cursor()
        cur.execute('SELECT 1')
        print('SELECT 1 ->', cur.fetchone())
        cur.close()
        conn.close()
    except mysql.connector.Error as err:
        print('mysql.connector.Error:', err)
        traceback.print_exc()
    except Exception as e:
        print('Exception:', e)
        traceback.print_exc()


if __name__ == '__main__':
    main()
