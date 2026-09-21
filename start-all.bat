@echo off
cd /d "%~dp0"
echo AI 도구 전체 서버를 백그라운드(최소화 창)로 띄웁니다...

start "sd-webui" /min cmd /c "cd sd-webui && webui-user.bat"
start "music-gen" /min cmd /c "cd music-gen && run.bat"
start "voice-gen" /min cmd /c "cd voice-gen && run.bat"
start "ai-tools-hub" /min powershell -ExecutionPolicy Bypass -File "ai-tools-hub\serve.ps1"

echo.
echo 다 띄웠습니다. 모델 로딩 때문에 처음엔 1~2분 걸릴 수 있습니다.
echo http://127.0.0.1:8611/ 에서 "서버 상태" 탭으로 확인하세요.
