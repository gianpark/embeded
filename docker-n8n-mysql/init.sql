-- n8n 내부 메타데이터 DB 생성
CREATE DATABASE IF NOT EXISTS n8ndb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 앱용 샘플 DB
CREATE DATABASE IF NOT EXISTS appdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- n8nuser에게 두 DB 모두 권한 부여
GRANT ALL PRIVILEGES ON n8ndb.* TO 'n8nuser'@'%';
GRANT ALL PRIVILEGES ON appdb.* TO 'n8nuser'@'%';
FLUSH PRIVILEGES;

-- 샘플 테이블 (n8n 워크플로우에서 활용)
USE appdb;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    age INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product VARCHAR(200) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    source VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 샘플 데이터
INSERT INTO users (name, email, age) VALUES
    ('홍길동', 'hong@example.com', 30),
    ('김철수', 'kim@example.com', 25),
    ('이영희', 'lee@example.com', 35),
    ('박민수', 'park@example.com', 28);

INSERT INTO orders (user_id, product, amount, status) VALUES
    (1, '노트북', 1200000, 'completed'),
    (1, '마우스', 35000, 'completed'),
    (2, '키보드', 89000, 'pending'),
    (3, '모니터', 450000, 'completed'),
    (4, '헤드셋', 75000, 'pending');

INSERT INTO logs (level, message, source) VALUES
    ('info', '시스템 시작', 'system'),
    ('info', '데이터베이스 초기화 완료', 'init.sql');
