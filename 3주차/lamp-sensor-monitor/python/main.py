import time
import random
import mysql.connector
from mysql.connector import Error

def get_connection():
    return mysql.connector.connect(
        host="localhost",
        user="gianpark",
        password="qwer1234",
        database="jungjudb"
    )

def main():
    print("5초마다 humid 난수를 sensor 테이블에 저장합니다. (종료: Ctrl+C)")
    while True:
        try:
            humid = round(random.uniform(0, 100), 2)
            conn = get_connection()
            cursor = conn.cursor()
            cursor.execute("INSERT INTO sensor (humid) VALUES (%s)", (humid,))
            conn.commit()
            print(f"저장됨 - humid: {humid}")

        except Error as e:
            print(f"DB 오류: {e}")

        finally:
            if 'cursor' in locals(): cursor.close()
            if 'conn' in locals() and conn.is_connected(): conn.close()

        time.sleep(5)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n프로그램 종료")