<#
  sd-webui 체크포인트 유휴 언로드 워치독.

  music-gen/voice-gen/3d-gen은 자체적으로 5분 미사용 시 모델을 GPU에서 내리지만,
  sd-webui(AUTOMATIC1111)는 그런 기능이 없어서 한 번 로드한 체크포인트를 계속 물고 있는다.
  이 스크립트가 그 역할을 대신한다: ai-tools-hub(PHP)가 생성 요청마다
  logs\sd-last-used.txt 에 현재 시각을 남기면, 이 스크립트가 30초마다 그 파일을 확인해서
  IdleSeconds 이상 지났으면 /sdapi/v1/unload-checkpoint 를 호출해 체크포인트를 내린다.
  다음 생성 요청이 오면 sd-webui가 자동으로 다시 로드한다(첫 응답만 느려짐).

  start-all.ps1이 다른 서버들과 함께 백그라운드로 띄운다.
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
        Write-Host "[sd-idle-watchdog] ${idleElapsed}초 유휴 - 체크포인트 언로드 완료"
    } catch {
        # sd-webui가 꺼져 있거나 일시적으로 응답이 없음 - 다음 주기(30초 후)에 재시도
    }
}
