$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$bat = Join-Path $root "backup_to_nas.bat"
if (-not (Test-Path $bat)) { throw "backup_to_nas.bat introuvable" }
$action = New-ScheduledTaskAction -Execute "cmd.exe" -Argument "/c `"$bat`""
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(5) -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName "BNC Local - Backup NAS horaire" -Action $action -Trigger $trigger -Principal $principal -Description "Sauvegarde transactionnellement cohérente de BNC Local vers le NAS" -Force | Out-Null
Write-Host "Tâche créée : BNC Local - Backup NAS horaire" -ForegroundColor Green
Write-Host "Conseil : utilisez un chemin UNC (\\NAS\\Partage) plutôt qu'une lettre de lecteur mappée."
