@echo off
echo AI 도구 전체 서버를 종료합니다...
powershell -NoProfile -Command ^
  "Get-NetTCPConnection -LocalPort 7860,7862,7863,8611 -State Listen -ErrorAction SilentlyContinue |" ^
  " Select-Object -ExpandProperty OwningProcess -Unique |" ^
  " ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }"
echo 완료.
