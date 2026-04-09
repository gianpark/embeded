# 🦙 n8n + Ollama 로컬 챗봇

WSL2 + Docker + n8n + Ollama를 활용한 완전 로컬 LLaMA 챗봇

## 아키텍처
WSL2 → Docker (n8n:5678) → Ollama (host:11434) → LLaMA 모델

## 빠른 시작

### 1. Ollama 설치 및 모델 다운로드
```bash
curl -fsSL https://ollama.ai/install.sh | sh
sudo mkdir -p /etc/systemd/system/ollama.service.d
sudo tee /etc/systemd/system/ollama.service.d/override.conf << 'END'
[Service]
Environment="OLLAMA_HOST=0.0.0.0"
END
sudo systemctl daemon-reload
sudo systemctl restart ollama
ollama pull llama3.2:1b
```

### 2. Docker로 n8n 실행
```bash
docker compose up -d
```

### 3. 워크플로우 Import
- http://localhost:5678 접속
- ollama_chatbot_workflow.json import
- Active 토글 ON

### 4. 챗봇 실행
```bash
python3 chatbot_client.py
```

## API 사용법

### 챗봇
```bash
curl -X POST http://localhost:5678/webhook/chat \
  -H "Content-Type: application/json" \
  -d '{"prompt": "안녕!", "model": "llama3.2:1b"}'
```

### 모델 목록
```bash
curl http://localhost:5678/webhook/models
```

## 지원 모델
| 모델 | 크기 | 용도 |
|------|------|------|
| llama3.2:1b | 1.2GB | 초경량, 빠른 응답 |
| llama3.2 | 2GB | 일반 대화 |
| llama3.1:8b | 8GB | 고품질 응답 |
| codellama | 4GB | 코드 생성 |
| mistral | 4GB | 고품질 텍스트 |

## 파일 구조
n8n-ollama/
├── docker-compose.yml           # n8n Docker 설정
├── ollama_chatbot_workflow.json # n8n 워크플로우
├── chatbot_client.py            # Python CLI 클라이언트
├── test.sh                      # 연동 테스트 스크립트
└── README.md
