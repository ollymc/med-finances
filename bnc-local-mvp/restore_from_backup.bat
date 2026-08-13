@echo off
cd /d "%~dp0"
set /p BACKUP=Chemin complet du dossier de sauvegarde a restaurer: 
".venv\Scripts\python.exe" scripts\restore_backup.py "%BACKUP%"
pause
