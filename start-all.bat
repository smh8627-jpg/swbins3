@echo off
cd /d "%~dp0"
echo Starting all AI generation servers in background (minimized windows)...

start "sd-webui" /min cmd /c "cd sd-webui && webui-user.bat"
start "music-gen" /min cmd /c "cd music-gen && run.bat"
start "voice-gen" /min cmd /c "cd voice-gen && run.bat"
start "3d-gen" /min cmd /c "cd 3d-gen && run.bat"
start "ai-tools-hub" /min powershell -ExecutionPolicy Bypass -File "ai-tools-hub\serve.ps1"

echo.
echo All started. First run may take 1-2 minutes to load models.
echo Check http://127.0.0.1:8611/ - "Server Status" tab.
