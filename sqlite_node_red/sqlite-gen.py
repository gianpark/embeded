import sqlite3
import random
import time

DB_PATH = "giandb"

conn = sqlite3.connect(DB_PATH)
cursor = conn.cursor()

cursor.execute("""
    CREATE TABLE IF NOT EXISTS sensor (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        humid REAL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )
""")
conn.commit()

print("DB 준비 완료. 10초마다 난수를 저장합니다. (종료: Ctrl+C)")

try:
    while True:
        value = random.randint(0, 100)
        cursor.execute("INSERT INTO sensor (humid) VALUES (?)", (value,))
        conn.commit()
        print(f"저장: humid = {value}")
        time.sleep(10)
except KeyboardInterrupt:
    print("\n종료합니다.")
finally:
    conn.close()
