@echo off
cd /d "%~dp0"
if not exist ".venv\Scripts\python.exe" (
  echo Installation absente. Lancez install.ps1.
  pause
  exit /b 1
)
start "BNC Local" /min ".venv\Scripts\python.exe" run.py
ping 127.0.0.1 -n 3 > nul
start "" http://127.0.0.1:8787
