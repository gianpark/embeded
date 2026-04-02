# n8n + MySQL Docker 프로젝트

n8n 워크플로우 자동화 도구와 MySQL 데이터베이스를 Docker Compose로 연동하는 프로젝트입니다.

## 구성

| 서비스 | 포트 | 설명 |
|--------|------|------|
| n8n    | 5679 | 워크플로우 자동화 UI |
| MySQL  | 3308 | 데이터베이스 |

## 데이터베이스 구조

- `n8ndb` — n8n 내부 메타데이터 저장용
- `appdb` — 사용자 애플리케이션 데이터용
  - `users` — 샘플 사용자 테이블
  - `orders` — 샘플 주문 테이블
  - `logs` — 워크플로우 실행 로그 테이블

## 시작 방법

```bash
# 1. 프로젝트 폴더로 이동
cd docker-n8n-mysql

# 2. 컨테이너 시작
docker compose up -d

# 3. 로그 확인
docker compose logs -f
```

## 접속

- **n8n UI**: http://localhost:5679
- **MySQL**: localhost:3308 (툴: DBeaver, TablePlus 등)

## n8n에서 MySQL 연결 설정

1. n8n UI 접속 → **Credentials** → **New Credential**
2. **MySQL** 선택 후 아래 정보 입력:
   - Host: `mysql`
   - Port: `3306`
   - Database: `appdb`
   - User: `n8nuser`
   - Password: `n8npass`

## 샘플 워크플로우 가져오기

1. n8n UI → 우측 상단 메뉴 → **Import from File**
2. `sample-workflow.json` 파일 선택
3. MySQL 크리덴셜 연결 후 활성화

## 환경 변수 (.env)

`.env` 파일에서 포트 및 패스워드를 변경할 수 있습니다.

## 종료

```bash
# 컨테이너만 종료 (데이터 유지)
docker compose down

# 컨테이너 + 볼륨 모두 삭제 (데이터 초기화)
docker compose down -v
```
