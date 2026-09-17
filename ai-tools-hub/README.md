# AI 도구 모음

AI 동영상·이미지·음악·웹툰·문서·앱 제작 서비스를 카테고리별로 모아보는 큐레이션 사이트입니다.
직접 생성 기능은 없고, 잘 알려진 외부 AI 서비스로 연결하는 링크 모음입니다. 추가로 토큰(비용) 절약 팁 탭도 있습니다.

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
