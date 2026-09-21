@echo off
echo Stopping AI generation servers...
powershell -NoProfile -Command "Get-NetTCPConnection -LocalPort 7860,7862,7863,7864,8611 -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }"
echo Done.
