import time
import random
import mysql.connector
from mysql.connector import Error

DB_CONFIG = {
    "host":     "localhost",
    "user":     "gianpark",
    "password": "qwer1234",
    "database": "monitordb",
}

INTERVAL = 5  # 초


def get_connection():
    return mysql.connector.connect(**DB_CONFIG)


def generate_data():
    
    """가상 센서 데이터 생성"""
    temperature = round(random.uniform(20.0, 35.0), 2)   # 온도: 20~35°C
    humidity    = round(random.uniform(30.0, 90.0), 2)   # 습도: 30~90%
    pressure    = round(random.uniform(980.0, 1030.0), 2) # 기압: 980~1030 hPa
    return temperature, humidity, pressure


def insert_data(cursor, temperature, humidity, pressure):
    sql = """
        INSERT INTO sensor_data (temperature, humidity, pressure)
        VALUES (%s, %s, %s)
    """
    cursor.execute(sql, (temperature, humidity, pressure))


def main():
    print(f"[injector] {INTERVAL}초마다 가상 센서 데이터를 monitordb.sensor_data에 저장합니다.")
    print("[injector] 종료: Ctrl+C\n")

    while True:
        conn = None
        try:
            temperature, humidity, pressure = generate_data()

            conn = get_connection()
            cursor = conn.cursor()
            insert_data(cursor, temperature, humidity, pressure)
            conn.commit()

            print(
                f"[저장됨] temp={temperature}°C  "
                f"humid={humidity}%  "
                f"pressure={pressure}hPa"
            )

        except Error as e:
            print(f"[DB 오류] {e}")

        finally:
            if conn and conn.is_connected():
                cursor.close()
                conn.close()

        time.sleep(INTERVAL)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n[injector] 프로그램 종료")
