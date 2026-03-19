import random
import time
from pymongo import MongoClient
from pymongo.errors import ConnectionFailure

def get_client():
    return MongoClient(
        host="localhost",
        port=27017,
        username="gian",
        password="qwer1234",
        authSource="admin"
    )

def main():
    print("5초마다 humid 난수를 MongoDB shkimdb.sensor에 저장합니다. (종료: Ctrl+C)")

    client = get_client()
    try:
        client.admin.command("ping")
        print("MongoDB 연결 성공")
    except ConnectionFailure as e:
        print(f"MongoDB 연결 실패: {e}")
        return

    db = client["shkimdb"]
    collection = db["sensor"]

    while True:
        try:
            humid = round(random.uniform(0, 100), 2)
            doc = {"humid": humid}
            result = collection.insert_one(doc)
            print(f"저장됨 - _id: {result.inserted_id}, humid: {humid}")
        except Exception as e:
            print(f"저장 오류: {e}")

        time.sleep(5)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n프로그램 종료")
