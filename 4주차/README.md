# IoT 센서 데이터 모니터링 프로젝트

## 개요

가상 센서 데이터(온도·습도·기압)를 Python으로 자동 생성하여 MySQL(LAMP)에 저장하고,
Node-RED 및 Grafana 대시보드에서 실시간으로 모니터링하는 시스템입니다.

---

## 구성 요소

| 파일 | 설명 |
|---|---|
| `injector.py` | 5초마다 온도/습도/기압 난수 생성 → MySQL(monitordb) 저장 |
| `node-red-gen.py` | 5초마다 humid 난수 생성 → SQLite(shkkimdb1) 저장 |
| `sqlite-gen.py` | 10초마다 humid 난수 생성 → SQLite(giandb) 저장 |
| `mqtt-pub.py` | 5초마다 난수 생성 → MQTT topic `temp1` publish |
| `setup_db.sql` | MySQL monitordb / sensor_data 테이블 초기화 스크립트 |

---

## 전체 시스템 아키텍처

```mermaid
flowchart TD
    subgraph Python["Python 데이터 생성기"]
        A["injector.py
        ── 온도: 20~35°C
        ── 습도: 30~90%
        ── 기압: 980~1030hPa
        ── 5초 간격"]
        B["mqtt-pub.py
        ── 난수: 0~100
        ── 5초 간격
        ── topic: temp1"]
        C["node-red-gen.py
        ── humid: 0~100
        ── 5초 간격"]
        D["sqlite-gen.py
        ── humid: 0~100
        ── 10초 간격"]
    end

    subgraph Storage["저장소"]
        E[("MySQL
        monitordb.sensor_data
        LAMP · localhost:3306")]
        F[("SQLite
        shkkimdb1 / giandb")]
        G["Mosquitto Broker
        localhost:1883"]
    end

    subgraph Monitor["실시간 모니터링"]
        H["Node-RED Dashboard
        localhost:1880/ui"]
        I["Grafana Dashboard
        localhost:3000"]
    end

    A -->|"INSERT temperature, humidity, pressure"| E
    B -->|"publish"| G
    C -->|"INSERT humid"| F
    D -->|"INSERT humid"| F
    G -->|"subscribe"| H
    F -->|"SQLite node"| H
    E -->|"MySQL node"| H
    E -->|"Data Source"| I
```

---

## injector.py 동작 흐름

```mermaid
flowchart TD
    Start([프로그램 시작]) --> Init[MySQL 접속 설정 로드\nhost/user/password/database]
    Init --> Loop{무한 루프\n5초 간격}

    Loop --> Gen["generate_data()
    ── temperature = uniform(20.0, 35.0)
    ── humidity    = uniform(30.0, 90.0)
    ── pressure    = uniform(980.0, 1030.0)"]

    Gen --> Conn[get_connection()\nMySQL 연결]
    Conn --> Insert["insert_data()
    INSERT INTO sensor_data
    VALUES (temp, humid, pressure)"]
    Insert --> Commit[conn.commit()]
    Commit --> Print["콘솔 출력
    [저장됨] temp=xx°C  humid=xx%  pressure=xxhPa"]
    Print --> Close[cursor / conn 닫기]
    Close --> Sleep[time.sleep(5)]
    Sleep --> Loop

    Loop -->|Ctrl+C| End([프로그램 종료])
```

---

## 데이터 흐름 시퀀스

```mermaid
sequenceDiagram
    participant I as injector.py
    participant DB as MySQL (monitordb)
    participant NR as Node-RED
    participant GF as Grafana

    loop 5초마다
        I->>I: generate_data()<br/>난수 3종 생성
        I->>DB: INSERT INTO sensor_data<br/>(temperature, humidity, pressure)
        DB-->>I: commit OK

        Note over DB,NR: Node-RED inject 노드가 주기적으로 polling
        DB-->>NR: SELECT 최근 데이터 반환
        NR-->>NR: function 노드에서 JSON 가공
        NR-->>NR: ui_chart / ui_gauge 업데이트

        Note over DB,GF: Grafana가 $__timeFilter 쿼리로 갱신
        DB-->>GF: SELECT created_at, temperature,<br/>humidity, pressure
        GF-->>GF: Line Panel 실시간 렌더링
    end
```

---

## Node-RED Flow 구성

```mermaid
flowchart LR
    A["inject
    5초 간격"] --> B["MySQL node
    SELECT * FROM sensor_data
    ORDER BY id DESC LIMIT 20"]
    B --> C["function node
    JSON 가공"]
    C --> D["ui_chart
    Line Chart
    온도/습도/기압"]
    C --> E["ui_gauge
    현재 온도"]
    C --> F["ui_gauge
    현재 습도"]

    G["mqtt in
    topic: temp1"] --> H["ui_gauge
    MQTT 실시간값"]
```

---

## MySQL sensor_data 테이블 구조

```mermaid
erDiagram
    sensor_data {
        INT id PK "AUTO_INCREMENT"
        FLOAT temperature "20.0 ~ 35.0 °C"
        FLOAT humidity "30.0 ~ 90.0 %"
        FLOAT pressure "980.0 ~ 1030.0 hPa"
        DATETIME created_at "DEFAULT CURRENT_TIMESTAMP"
    }
```

---

## 실행 방법

### 1. MySQL DB 초기화 (최초 1회)
```bash
mysql -u root -p < setup_db.sql
```

### 2. 데이터 생성 시작
```bash
uv run python injector.py
```

### 3. MQTT publish (선택)
```bash
uv run python mqtt-pub.py
```

### 4. Node-RED 접속
```
http://localhost:1880
```

### 5. Grafana 접속
```
http://localhost:3000
```

---

## Grafana Panel 쿼리

```sql
SELECT
    created_at AS time,
    temperature,
    humidity,
    pressure
FROM sensor_data
WHERE $__timeFilter(created_at)
ORDER BY created_at ASC
```

---

## 환경

| 항목 | 내용 |
|---|---|
| OS | Zorin OS (Linux) |
| Python | 3.12 (uv 가상환경) |
| DB | MySQL (LAMP) / SQLite 내장 모듈 |
| MQTT | Mosquitto broker (localhost:1883) |
| Node-RED | localhost:1880 |
| Grafana | localhost:3000 |
