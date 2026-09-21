# 새 PC에서 로컬 AI 생성 환경 다시 만들기

git에는 `ai-tools-hub`, `music-gen/server.py`, `voice-gen/server.py`, `start-all.bat` 등 코드만 있고,
`sd-webui/`, `music-gen/venv/`, `voice-gen/venv/`, 모델 파일은 용량 때문에 `.gitignore`로 빠져 있습니다.
새 PC에서는 이 문서대로 그 무거운 부분을 다시 만들어야 합니다.

## 0. 사전 준비

- Python 3.10, Git 설치되어 있어야 함
- NVIDIA GPU + 드라이버 (VRAM 6GB 이상 권장)
- Ollama 설치: https://ollama.com (문서·코드 생성용, 이 저장소에 포함 안 됨)
- 회사 네트워크라면 `huggingface.co`, `*.cdn-lfs*.huggingface.co`, `registry.ollama.ai`, `ollama.com` 방화벽 예외 필요 (전에 겪은 문제: "전문/특화 AI 기타" 정책으로 차단됨)

## 1. sd-webui (이미지·동영상)

```powershell
cd C:\swbins3
git clone --depth 1 https://github.com/AUTOMATIC1111/stable-diffusion-webui.git sd-webui
```

`sd-webui\webui-user.bat`을 열어 이렇게 설정:

```bat
set COMMANDLINE_ARGS=--api
set STABLE_DIFFUSION_REPO=https://github.com/w-e-w/stablediffusion.git
```

(원래 `Stability-AI/stablediffusion` 저장소가 삭제되어서 미러로 바꿔야 함 — 안 하면 "Repository not found" 에러)

AnimateDiff(동영상) 확장 설치:

```powershell
cd sd-webui\extensions
git clone --depth 1 https://github.com/continue-revolution/sd-webui-animatediff.git
```

한국어 로케일(선택):

```powershell
git clone --depth 1 https://github.com/AUTOMATIC1111/stable-diffusion-webui-old-localizations.git
```

```powershell
cd C:\swbins3\sd-webui
webui-user.bat
```

최초 실행 시 venv 생성 + torch 등 설치가 진행됩니다. **아래 에러가 나면**:

- `Couldn't install clip` / `ModuleNotFoundError: No module named 'pkg_resources'`
  ```powershell
  venv\Scripts\python.exe -m pip install --upgrade "setuptools<70" wheel
  venv\Scripts\python.exe -m pip install --no-build-isolation "https://github.com/openai/CLIP/archive/d50d76daa670286dd6cacf3bcd80b5e4823fc8e1.zip"
  ```
  그 다음 `webui-user.bat` 다시 실행.

- AnimateDiff가 `No module named 'av'` 로 실패하면: `venv\Scripts\python.exe -m pip install av` 후 재실행.

- 한국어 로케일 적용: 서버 뜬 뒤
  ```powershell
  curl.exe -X POST -H "Content-Type: application/json" -d "{\"localization\":\"ko_KR\"}" http://127.0.0.1:7860/sdapi/v1/options
  ```
  (또는 웹 UI Settings → User Interface → Localization 에서 `ko_KR` 선택)

모델 파일은 `AI_MODELS_TODO.md`의 1·2번 참고.

## 2. music-gen (음악)

```powershell
cd C:\swbins3
mkdir music-gen
cd music-gen
python -m venv venv
venv\Scripts\python.exe -m pip install --upgrade pip
venv\Scripts\python.exe -m pip install torch --index-url https://download.pytorch.org/whl/cu121
venv\Scripts\python.exe -m pip install transformers scipy flask accelerate
```

`server.py`, `run.bat`은 git에 이미 있습니다(clone하면 옴). 모델(`facebook/musicgen-small`)은 첫 요청 때 자동 다운로드됩니다.

## 3. voice-gen (음성)

```powershell
cd C:\swbins3
mkdir voice-gen
cd voice-gen
python -m venv venv
venv\Scripts\python.exe -m pip install --upgrade pip
venv\Scripts\python.exe -m pip install pip-system-certs
```

`pip-system-certs`는 회사 TLS 검사 프록시의 자체 서명 인증서를 Python이 못 믿어서 나는
`SSL: CERTIFICATE_VERIFY_FAILED` 에러를 막아줍니다(Windows 인증서 저장소를 쓰도록 패치).

```powershell
venv\Scripts\python.exe -m pip install torch --index-url https://download.pytorch.org/whl/cu121
venv\Scripts\python.exe -m pip install torchaudio --index-url https://download.pytorch.org/whl/cu121
$env:SETUPTOOLS_USE_DISTUTILS = "stdlib"
venv\Scripts\python.exe -m pip install coqui-tts flask
venv\Scripts\python.exe -m pip install "transformers>=4.57,<5"
```

⚠️ 주의사항:
- 원본 `TTS` 패키지가 아니라 **`coqui-tts`**(커뮤니티 유지보수 포크)를 설치해야 합니다. 원본은 MSVC 빌드 도구가 없으면
  `Unable to find vcvarsall.bat` 에러로 설치가 안 됩니다.
- `transformers`를 5.x로 두면 `isin_mps_friendly` import 에러가 납니다. 반드시 `<5`로 고정.
- `run.bat`에 `COQUI_TOS_AGREED=1`이 이미 들어있습니다 — XTTS-v2 라이선스(CPML, **비상업적 용도**) 동의를
  헤드리스로 자동 처리하는 것이니, 사내 업무용으로만 쓰세요.

모델(`coqui/XTTS-v2`, 약 1.8GB)은 첫 요청 때 자동 다운로드되고, 목소리 샘플(5~10초 wav)은 웹 UI에서 처음 한 번 업로드하면 됩니다.

## 4. Ollama (문서·코드)

```powershell
ollama pull qwen2.5:7b
ollama pull qwen2.5-coder:7b
```

## 5. ai-tools-hub (웹 UI)

```powershell
cd C:\swbins3\ai-tools-hub
php composer.phar install
```

## 6. 한 번에 켜기

```powershell
C:\swbins3\start-all.bat
```

`http://127.0.0.1:8611/` → "서버 상태" 탭에서 4개 다 켜졌는지 확인.
