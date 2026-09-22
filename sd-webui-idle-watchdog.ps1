<#
  sd-webui checkpoint idle-unload watchdog.

  music-gen/voice-gen/3d-gen already unload their models after 5 minutes of inactivity,
  but sd-webui (AUTOMATIC1111) has no such feature and keeps a loaded checkpoint resident
  forever. This script does that job instead: ai-tools-hub (PHP) writes the current time to
  logs\sd-last-used.txt on every generation request, and this script checks that file every
  30 seconds - if IdleSeconds have passed since, it calls /sdapi/v1/unload-checkpoint to free
  the checkpoint. The next generation request makes sd-webui reload it automatically.

  NOTE: keep this file's comments ASCII-only. Korean text needs a UTF-8 BOM for Windows
  PowerShell 5.1 to parse correctly (see the root README's script-encoding note) - without
  the BOM the console codepage misreads the multibyte sequences and the parser breaks
  (hit this exact bug once already: "MissingEndCurlyBrace" from a garbled comment).

  Started alongside the other servers by start-all.ps1.
#>
param(
    [int]$IdleSeconds = 300,
    [string]$SdApiUrl = "http://127.0.0.1:7860"
)

$root = $PSScriptRoot
$logs = Join-Path $root "logs"
New-Item -ItemType Directory -Force -Path $logs | Out-Null
$lastUsedFile = Join-Path $logs "sd-last-used.txt"
$unloadedFlag = Join-Path $logs "sd-unloaded.flag"

while ($true) {
    Start-Sleep -Seconds 30

    if (Test-Path $unloadedFlag) { continue }
    if (-not (Test-Path $lastUsedFile)) { continue }

    try {
        $lastUsed = [long](Get-Content $lastUsedFile -Raw -ErrorAction Stop).Trim()
    } catch {
        continue
    }

    $idleElapsed = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds() - $lastUsed
    if ($idleElapsed -lt $IdleSeconds) { continue }

    try {
        Invoke-RestMethod -Uri "$SdApiUrl/sdapi/v1/unload-checkpoint" -Method Post -TimeoutSec 10 -ErrorAction Stop | Out-Null
        New-Item -ItemType File -Force -Path $unloadedFlag | Out-Null
        Write-Host ("[sd-idle-watchdog] unloaded checkpoint after {0}s idle" -f $idleElapsed)
    } catch {
        # sd-webui is off or not responding right now - retry next cycle (30s)
    }
}
