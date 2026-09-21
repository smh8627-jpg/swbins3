# 로컬 AI 생성 기능 — 받아야 할 모델 파일 목록

회사 방화벽이 `huggingface.co` (전문/특화 AI 기타 정책)를 막고 있어서, 아래 파일들은 IT 예외 요청 후 다운로드하거나 다른 네트워크에서 받아서 옮겨야 합니다.

IT에 예외 요청할 때 도메인: `huggingface.co` + `*.cdn-lfs*.huggingface.co` (또는 `*.huggingface.co` 전체), `ollama.com`, `registry.ollama.ai`, `openaipublic.azureedge.net`(3D 생성용, 7번 참고)

## 1. 이미지 생성용 체크포인트 (필수)

- 파일: `v1-5-pruned-emaonly.safetensors`
- 용량: 약 4GB
- 받는 곳: https://huggingface.co/stable-diffusion-v1-5/stable-diffusion-v1-5/resolve/main/v1-5-pruned-emaonly.safetensors
- 넣을 위치: `C:\swbins3\sd-webui\models\Stable-diffusion\`
- 상태: ❌ 미다운로드 (방화벽 차단)

## 2. 동영상 생성용 모션 모듈 (필수)

- 파일: `mm_sd_v15_v2.safetensors`
- 용량: 약 1.7GB
- 받는 곳: https://huggingface.co/conrevo/AnimateDiff-A1111/tree/main (fp16/safetensors 버전 권장)
- 넣을 위치: `C:\swbins3\sd-webui\extensions\sd-webui-animatediff\model\`
- 상태: ❌ 미다운로드 (방화벽 차단)

## 3. 음악 생성 모델 (자동 다운로드, 수동 작업 불필요)

- 모델: `facebook/musicgen-small` (Hugging Face `transformers` 라이브러리가 최초 실행 시 자동으로 받음)
- 용량: 약 2GB
- 받는 곳: huggingface.co (자동, 캐시 위치 `%USERPROFILE%\.cache\huggingface\`)
- 상태: ⏳ 서버 코드 준비 중, 방화벽 풀리면 최초 실행 시 자동 다운로드됨

## 4. 문서 생성 모델 (Ollama, 명령어로 자동 다운로드)

- 모델: `qwen2.5:7b` (Ollama로 관리, 이미 이 PC에 Ollama 설치되어 있음)
- 용량: 약 4.7GB
- 받는 곳: `registry.ollama.ai` (자동)
- 방화벽 풀리면 실행할 명령: `ollama pull qwen2.5:7b`
- 상태: ❌ 미다운로드 (방화벽 차단 — huggingface.co와 같은 "전문/특화 AI 기타" 정책)

## 5. 코드/앱 생성 모델 (Ollama, 명령어로 자동 다운로드)

- 모델: `qwen2.5-coder:7b`
- 용량: 약 4.7GB
- 받는 곳: `registry.ollama.ai` (자동, 4번과 같은 도메인)
- 방화벽 풀리면 실행할 명령: `ollama pull qwen2.5-coder:7b`
- 상태: ❌ 미다운로드 (방화벽 차단)

## 6. 음성 생성 모델 (자동 다운로드, 수동 작업 불필요)

- 모델: `coqui/XTTS-v2` (Coqui `TTS` 라이브러리가 최초 실행 시 자동으로 받음)
- 용량: 약 1.8GB
- 받는 곳: huggingface.co (자동)
- ⚠️ 라이선스: XTTS-v2는 비상업적 용도(CPML 라이선스)입니다. `voice-gen/run.bat`에서 `COQUI_TOS_AGREED=1`로 동의를 자동 처리해뒀습니다 — 사내 업무용으로만 쓰고 상업적 배포는 하지 마세요.
- 상태: ❌ 미다운로드 (방화벽 차단)

## 7. 3D 에셋 생성 모델 (자동 다운로드, 수동 작업 불필요)

- 모델: `transmitter`, `text300M`(텍스트→3D), `image300M`(이미지→3D) — OpenAI Shap-E가 최초 실행 시 자동으로 받음
- 용량: 세 개 합쳐 약 2~3GB
- 받는 곳: `openaipublic.azureedge.net` (자동, 캐시 위치 `%USERPROFILE%\.cache\`) — huggingface.co와는 다른 도메인이라 **별도로 방화벽 예외 필요**
- IT에 추가로 예외 요청할 도메인: `openaipublic.azureedge.net`
- 확인: `curl.exe -I https://openaipublic.azureedge.net` 이 막혀 있으면(현재 상태) `3d-gen` 탭에서 "모델 로드 실패" 에러가 남
- 상태: ❌ 미다운로드 (방화벽 차단, huggingface.co와 별개 도메인)

## 확인 방법

방화벽 예외가 풀리면 아래로 접속이 되는지 먼저 확인:

```powershell
curl.exe -I https://huggingface.co
```

`200 OK`가 나오면 위 1·2번 파일을 받아서 해당 경로에 넣고, 음악은 그냥 사이트에서 "음악 생성"을 눌러보면 됩니다(첫 실행만 자동 다운로드로 시간이 걸림).
