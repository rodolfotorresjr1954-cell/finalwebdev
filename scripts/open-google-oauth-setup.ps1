# Opens your OAuth client in Google Cloud Console and copies redirect URIs to clipboard.
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$envFile = Join-Path $root '.env'
$clientId = ''
foreach ($line in Get-Content $envFile) {
  if ($line -match '^GOOGLE_CLIENT_ID=(.+)$') {
    $clientId = $Matches[1].Trim()
    break
  }
}
if (-not $clientId) {
  Write-Error 'GOOGLE_CLIENT_ID not found in .env'
}

# Match ACT1 src/config/api.ts API_HOST_OVERRIDE when possible
$ip = '192.168.254.110'
$act1Api = Join-Path (Split-Path -Parent $root) '..\KRISHA\ACT1\src\config\api.ts'
if (-not (Test-Path $act1Api)) {
  $act1Api = 'E:\KRISHA\ACT1\src\config\api.ts'
}
if (Test-Path $act1Api) {
  if ((Get-Content $act1Api -Raw) -match "API_HOST_OVERRIDE\s*=\s*'([^']+)'") {
    $ip = $Matches[1]
  }
}

$port = '8000'
$uris = @(
  "http://${ip}.nip.io:${port}/connect/google/check",
  "http://127.0.0.1:${port}/connect/google/check",
  "http://localhost:${port}/connect/google/check"
)

$text = ($uris -join "`r`n")
Set-Clipboard -Value $text

$url = "https://console.cloud.google.com/auth/clients/$([uri]::EscapeDataString($clientId))"
Write-Host "Copied to clipboard (paste under Authorized redirect URIs):"
$uris | ForEach-Object { Write-Host "  $_" }
Write-Host ""
Write-Host "Opening: $url"
Start-Process $url
