param(
  [Parameter(Mandatory=$true)][string]$OutputPath
)
$ErrorActionPreference = 'Stop'
# Uses the Windows Image Acquisition common dialog. Brother DS-740D exposes WIA/TWAIN drivers on Windows.
$dialog = New-Object -ComObject WIA.CommonDialog
# 1 = ImageTypeColor, format JPEG GUID
$image = $dialog.ShowAcquireImage(1, 1, 1, "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}", $false, $true, $false)
if ($null -eq $image) { throw "Acquisition annulée ou aucune image reçue." }
if (Test-Path $OutputPath) { Remove-Item $OutputPath -Force }
$image.SaveFile($OutputPath)
Write-Host "Scan enregistré: $OutputPath"
