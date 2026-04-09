#!/bin/bash
# n8n + Ollama 연동 빠른 테스트 스크립트

N8N_URL="http://localhost:5678/webhook"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🦙 n8n + Ollama 연동 테스트"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 1. Docker 컨테이너 상태 확인
echo -e "\n${YELLOW}[1] Docker 컨테이너 상태${NC}"
docker ps --filter "name=n8n-ollama" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# 2. Ollama 서비스 확인
echo -e "\n${YELLOW}[2] Ollama 서비스 확인${NC}"
if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Ollama 실행 중${NC}"
    echo "설치된 모델:"
    curl -s http://localhost:11434/api/tags | python3 -c "
import json,sys
data=json.load(sys.stdin)
for m in data.get('models',[]):
    size_gb = m['size']/1024/1024/1024
    print(f\"  - {m['name']:<25} ({size_gb:.1f} GB)\")
"
else
    echo -e "${RED}❌ Ollama 미실행. 'ollama serve' 실행 필요${NC}"
fi

# 3. n8n 웹훅 테스트 - 모델 목록
echo -e "\n${YELLOW}[3] n8n 모델 목록 API 테스트${NC}"
MODELS_RESP=$(curl -s --max-time 10 "$N8N_URL/models" 2>&1)
if echo "$MODELS_RESP" | python3 -c "import json,sys; d=json.load(sys.stdin); print('✅ n8n 모델 API 정상 -', d['count'], '개 모델')" 2>/dev/null; then
    echo -e "${GREEN}성공${NC}"
else
    echo -e "${RED}❌ n8n 웹훅 미응답. 워크플로우 활성화 확인 필요${NC}"
fi

# 4. 챗봇 API 테스트 (llama3.2)
echo -e "\n${YELLOW}[4] 챗봇 API 테스트 (llama3.2)${NC}"
echo "  프롬프트: '안녕하세요! 한 문장으로 자기소개 해주세요.'"
echo "  (응답까지 30초 정도 소요될 수 있습니다...)"

CHAT_RESP=$(curl -s --max-time 120 -X POST "$N8N_URL/chat" \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "안녕하세요! 한 문장으로 자기소개 해주세요.",
    "model": "llama3.2",
    "temperature": 0.7
  }')

if echo "$CHAT_RESP" | python3 -c "
import json,sys
d=json.load(sys.stdin)
if d.get('success'):
    print('✅ 응답 성공!')
    print('  모델:', d.get('model'))
    print('  응답:', d.get('response','')[:200])
    print('  처리시간:', d.get('total_duration_ms'), 'ms')
else:
    print('❌ 오류:', d.get('error','알 수 없음'))
" 2>/dev/null; then
    echo ""
else
    echo -e "${RED}❌ 챗봇 API 응답 없음${NC}"
    echo "응답: $CHAT_RESP"
fi

echo -e "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ 테스트 완료"
echo "  📡 n8n UI: http://localhost:5678"
echo "  🐍 클라이언트: python3 chatbot_client.py"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
