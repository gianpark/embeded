#!/usr/bin/env python3
import requests
import json

N8N_BASE_URL = "http://localhost:5678/webhook"
CHAT_ENDPOINT = f"{N8N_BASE_URL}/chat"
MODELS_ENDPOINT = f"{N8N_BASE_URL}/models"

MODELS = {
    "1": {"id": "llama3.2:1b", "name": "LLaMA 3.2 1B",  "desc": "초경량, 최고 속도"},
    "2": {"id": "llama3.2",    "name": "LLaMA 3.2 (3B)", "desc": "빠른 응답, 일반 대화"},
    "3": {"id": "llama3.1:8b", "name": "LLaMA 3.1 (8B)", "desc": "균형잡힌 성능"},
    "4": {"id": "codellama",   "name": "Code LLaMA",      "desc": "코드 생성 특화"},
    "5": {"id": "mistral",     "name": "Mistral 7B",      "desc": "고품질 텍스트 생성"},
}

def select_model():
    print("\n" + "="*50)
    print("  🦙 사용할 모델을 선택하세요")
    print("="*50)
    for key, m in MODELS.items():
        print(f"  [{key}] {m['name']:<20} - {m['desc']}")
    print("="*50)
    while True:
        choice = input("  선택 (1-5, 기본값 1): ").strip() or "1"
        if choice in MODELS:
            return MODELS[choice]["id"], MODELS[choice]["name"]
        print("  ❌ 1~5 사이 숫자를 입력하세요.")

def chat(prompt, model, history, system_prompt, temperature=0.7):
    payload = {
        "prompt": prompt,
        "model": model,
        "system_prompt": system_prompt,
        "temperature": temperature,
        "max_tokens": 2000,
        "history": history
    }
    try:
        resp = requests.post(CHAT_ENDPOINT, json=payload, timeout=120)
        # 빈 응답 체크
        if not resp.text or resp.text.strip() == "":
            return {"success": False, "error": "n8n에서 빈 응답 반환. 워크플로우 Executions 탭 확인 필요"}
        return resp.json()
    except requests.exceptions.JSONDecodeError:
        return {"success": False, "error": f"JSON 파싱 실패. 원본 응답: '{resp.text[:200]}'"}
    except requests.exceptions.Timeout:
        return {"success": False, "error": "응답 시간 초과 (120초)"}
    except requests.exceptions.ConnectionError:
        return {"success": False, "error": "n8n 서버 연결 실패. Docker 실행 확인"}
    except Exception as e:
        return {"success": False, "error": str(e)}

def main():
    print("\n" + "🔷"*25)
    print("   🦙 n8n + Ollama 로컬 챗봇")
    print("🔷"*25)

    model_id, model_name = select_model()

    print(f"\n  ✅ 선택 모델: {model_name}")
    print("  📝 시스템 프롬프트 (Enter = 기본값):")
    custom_sys = input("  > ").strip()
    system_prompt = custom_sys if custom_sys else \
        "You are a helpful assistant. Please respond in the same language as the user."

    temp_input = input("\n  🌡 Temperature (기본 0.7): ").strip()
    try:
        temperature = float(temp_input) if temp_input else 0.7
        temperature = max(0.0, min(2.0, temperature))
    except ValueError:
        temperature = 0.7

    print(f"\n  🚀 {model_name} 시작! (quit=종료, clear=초기화, model=모델변경)")
    print("─"*50)

    history = []

    while True:
        try:
            user_input = input(f"\n  👤 나 : ").strip()
        except (KeyboardInterrupt, EOFError):
            print("\n\n  👋 종료합니다.")
            break

        if not user_input:
            continue
        if user_input.lower() in ("quit", "exit", "종료"):
            print("\n  👋 종료합니다!")
            break
        elif user_input.lower() in ("clear", "초기화"):
            history = []
            print("  🗑  히스토리 초기화")
            continue
        elif user_input.lower() in ("model", "모델"):
            model_id, model_name = select_model()
            print(f"  ✅ 모델 변경: {model_name}")
            continue

        print(f"  🦙 [{model_name}] 생각 중...", end="", flush=True)
        result = chat(user_input, model_id, history, system_prompt, temperature)
        print("\r" + " "*50 + "\r", end="")

        if result.get("success"):
            print(f"  🤖 AI  : {result['response']}")
            history = result.get("history", history)
            ms = result.get("total_duration_ms", 0)
            tok = result.get("completion_tokens", 0)
            print(f"\n  ⏱ {ms}ms | 🔢 {tok} tokens")
        else:
            print(f"\n  ❌ 오류: {result.get('error')}")

        print("─"*50)

if __name__ == "__main__":
    main()
