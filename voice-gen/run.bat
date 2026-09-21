@echo off
cd /d "%~dp0"
set COQUI_TOS_AGREED=1
venv\Scripts\python.exe server.py
