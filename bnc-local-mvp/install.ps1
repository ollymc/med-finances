$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot
Write-Host "=== Installation de BNC Local ===" -ForegroundColor Cyan

$py = Get-Command py -ErrorAction SilentlyContinue
if (-not $py) {
  $py = Get-Command python -ErrorAction SilentlyContinue
}
if (-not $py) {
  Write-Host "Python 3.11+ n'est pas installé." -ForegroundColor Yellow
  Write-Host "Installez Python depuis python.org puis relancez ce script. Pendant l'installation, cochez 'Add Python to PATH'."
  exit 1
}

if (Get-Command py -ErrorAction SilentlyContinue) {
  py -3 -m venv .venv
} else {
  python -m venv .venv
}

& .\.venv\Scripts\python.exe -m pip install --upgrade pip
& .\.venv\Scripts\python.exe -m pip install -r requirements.txt

if (-not (Test-Path .\config.json)) {
  Copy-Item .\config.example.json .\config.json
}
New-Item -ItemType Directory -Force -Path .\data\documents, .\data\incoming, .\data\exports | Out-Null
& .\.venv\Scripts\python.exe -c "from app.main import init_db; init_db(); print('Base initialisée')"

$desktop = [Environment]::GetFolderPath('Desktop')
$shortcutPath = Join-Path $desktop 'BNC Local.lnk'
$ws = New-Object -ComObject WScript.Shell
$sc = $ws.CreateShortcut($shortcutPath)
$sc.TargetPath = Join-Path $PSScriptRoot 'start_bnc_local.bat'
$sc.WorkingDirectory = $PSScriptRoot
$sc.Description = 'BNC Local - comptabilité locale'
$sc.Save()

Write-Host ""
Write-Host "Installation terminée." -ForegroundColor Green
Write-Host "Un raccourci 'BNC Local' a été créé sur le Bureau."
Write-Host "Le logiciel écoute uniquement sur 127.0.0.1 par défaut."
Write-Host "Configurez le chemin NAS dans Paramètres avant la première sauvegarde."
