# AI 도구 모음

AI 동영상·이미지·음악·웹툰·문서·앱 제작 서비스를 카테고리별로 모아보는 큐레이션 사이트이면서,
같은 항목 중 이미지·동영상·음악·문서·코드·음성은 **이 PC에 설치된 로컬 AI로 직접 생성**도 됩니다
(외부로 전송되지 않음). 추가로 토큰(비용) 절약 팁 탭도 있습니다.

Slim 프레임워크(경량 PHP 프레임워크) 기반이며, PHP 내장 서버로 띄웁니다.

## 실행

```powershell
# 최초 1회: 의존성 설치 (이미 vendor/ 가 있으면 생략)
php composer.phar install

# 서버 실행 (기본 포트 8611)
powershell -ExecutionPolicy Bypass -File serve.ps1
powershell -ExecutionPolicy Bypass -File serve.ps1 -Port 8700
```

`http://127.0.0.1:8611/` 에서 카테고리 탭(영상·이미지·음악·웹툰·문서·앱 제작·토큰 절약법)을 눌러가며 보고, 상단 검색창으로 이름·설명·태그를 필터링할 수 있습니다.

## 로컬 생성 기능

"이미지 생성"·"동영상 생성"·"음악 생성"·"문서 생성"·"코드/앱 생성"·"음성 생성" 탭은 아래 로컬 백엔드를 호출합니다.
`C:\swbins3\start-all.bat`으로 한 번에 켜고, `stop-all.bat`으로 끕니다. "서버 상태" 탭에서 가동 여부를 확인할 수 있습니다.

| 탭 | 백엔드 | 포트 |
|---|---|---|
| 이미지·동영상 생성 | `sd-webui`(AUTOMATIC1111 + AnimateDiff) | 7860 |
| 음악 생성 | `music-gen`(MusicGen) | 7862 |
| 음성 생성 | `voice-gen`(Coqui XTTS-v2) | 7863 |
| 문서·코드 생성 | Ollama | 11434 |

필요한 모델 파일은 `C:\swbins3\AI_MODELS_TODO.md`에 정리되어 있습니다.

## 도구 추가/수정

코드를 건드릴 필요 없이 JSON 파일만 고치면 됩니다.

- `data/tools.json` — 카테고리별 도구 목록. 각 항목은 `name`·`url`·`description`·`pricing`·`tags`
- `data/token-saving.json` — 토큰 절약법 팁 목록 (`general`, `claude_code` 두 그룹)

⚠️ 도구 링크는 서비스 개편으로 주소가 바뀔 수 있어 주기적으로 확인이 필요합니다.

## 구조

| 경로 | 하는 일 |
|---|---|
| `public/index.php` | Slim 프론트 컨트롤러. 라우트 `GET /` 하나만 있음 |
| `src/data.php` | `data/*.json` 로더 + HTML 이스케이프 헬퍼 |
| `src/render.php` | 메인 페이지 HTML 렌더링 |
| `data/tools.json` | 카테고리별 AI 도구 목록 |
| `data/token-saving.json` | 토큰 절약법 팁 |
| `serve.ps1` | 개발 서버 실행 (`php -S`) |
| `composer.phar` | Composer 실행 파일 (의존성 설치용) |
| `vendor/` | Composer가 설치한 라이브러리 (git 추적 안 함) |
