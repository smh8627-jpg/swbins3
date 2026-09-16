<#
  화면 녹화 · 녹음 통합 CLI.

  swbin봇(웹/트레이)과 프롬프트(claude -p) 양쪽이 이 파일 하나만 호출한다.
  상태는 rec-state.json 에 남기고, 결과물은 out\ 에 쌓인다.

      rec.ps1 -Action list                      열린 창 목록 (JSON)
      rec.ps1 -Action start -Target "Back-Office" -Format gif
      rec.ps1 -Action start -Target monitor2 -Format mp4 -Audio mic
      rec.ps1 -Action stop
      rec.ps1 -Action status
      rec.ps1 -Action audio -Seconds 60         소리만 녹음
      rec.ps1 -Action shrink -File out\a.gif -MaxMB 5

  Target 지정 방식
      창 제목 일부      "Back-Office"      그 창만 직접 캡처(가려져 있어도 잡힘)
      monitor1..3       그 모니터 전체
      screen            활성 창이 있는 모니터를 따라감
      x,y,w,h           화면 좌표 영역
#>
[CmdletBinding()]
param(
    [ValidateSet('list', 'start', 'stop', 'status', 'audio', 'build', 'mask', 'shrink', 'devices')]
    [string]$Action = 'status',

    [string]$Target = 'screen',
    [ValidateSet('gif', 'mp4')]
    [string]$Format = 'gif',
    [ValidateSet('none', 'mic')]
    [string]$Audio = 'none',

    [int]$Fps = 6,
    [double]$Scale = 0.5,
    [int]$Seconds = 600,
    [string]$Name = '',
    [string]$File = '',
    [string]$Rects = '',
    [int]$From = 0,
    [int]$To = -1,
    [double]$MaxMB = 5
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$outDir = Join-Path $root 'out'
$stateFile = Join-Path $root 'rec-state.json'
$stopFile = Join-Path $root 'stop.flag'
$frameDir = Join-Path $root 'frames'
$logFile = Join-Path $root 'rec.log'
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir -Force | Out-Null }

function Write-Log([string]$msg) {
    $line = '[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $msg
    try { Add-Content -LiteralPath $logFile -Value $line -Encoding UTF8 } catch { }
}

function Write-State($obj) {
    $json = $obj | ConvertTo-Json -Depth 6
    [System.IO.File]::WriteAllText($stateFile, $json, $Utf8NoBom)
}

function Read-State {
    if (-not (Test-Path $stateFile)) { return $null }
    try { return (Get-Content $stateFile -Raw -Encoding UTF8 | ConvertFrom-Json) } catch { return $null }
}

function Get-Ffmpeg {
    # winget 이 만든 Links\ffmpeg.exe 는 앱 실행 별칭이라, 실행하면 실제 프로세스가
    # 다른 pid 로 뜨고 Get-Process 로도 안 잡힌다. 실제 exe 를 먼저 찾는다.
    $pkg = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (Test-Path $pkg) {
        $real = Get-ChildItem $pkg -Filter 'ffmpeg.exe' -Recurse -ErrorAction SilentlyContinue |
            Where-Object { $_.DirectoryName -like '*\bin' } | Select-Object -First 1
        if ($real) { return $real.FullName }
    }
    $c = Get-Command ffmpeg -ErrorAction SilentlyContinue
    if ($c) { return $c.Source }
    $p = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Links\ffmpeg.exe'
    if (Test-Path $p) { return $p }
    return $null
}

# ffmpeg 의 장치 목록은 stderr 로 나온다. PS 5.1 에서 native exe 의 2>&1 은
# NativeCommandError 를 일으키므로 파일로 받아서 읽는다.
function Get-DshowLines {
    $ff = Get-Ffmpeg
    if (-not $ff) { return @() }
    $tmp = Join-Path $env:TEMP ('dshow-' + [guid]::NewGuid().ToString('N') + '.txt')
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $ff
    $psi.Arguments = '-list_devices true -f dshow -i dummy'
    $psi.RedirectStandardError = $true
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true
    $p = [System.Diagnostics.Process]::Start($psi)
    $text = $p.StandardError.ReadToEnd()
    $p.WaitForExit()
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    return ($text -split "`r?`n")
}

function Get-AudioDevice {
    foreach ($line in (Get-DshowLines)) {
        if ($line -match '"(.+)"\s+\(audio\)') { return $Matches[1] }
    }
    return $null
}

# ffmpeg 인자는 배열로 넘기면 공백이 든 장치명이 쪼개진다. 문자열 한 줄로 넘긴다.
function Invoke-Ffmpeg([string]$argLine, [switch]$Wait) {
    $ff = Get-Ffmpeg
    if (-not $ff) { throw 'ffmpeg 를 찾지 못했습니다.' }
    if ($Wait) {
        # 끝까지 기다리는 경우에만 stderr 를 파이프로 읽는다
        $psi = New-Object System.Diagnostics.ProcessStartInfo
        $psi.FileName = $ff
        $psi.Arguments = $argLine
        $psi.UseShellExecute = $false
        $psi.CreateNoWindow = $true
        $psi.RedirectStandardError = $true
        $p = [System.Diagnostics.Process]::Start($psi)
        $err = $p.StandardError.ReadToEnd()
        $p.WaitForExit()
        if ($p.ExitCode -ne 0) { Write-Log ("ffmpeg exit=$($p.ExitCode) :: " + (($err -split "`r?`n" | Select-Object -Last 3) -join ' | ')) }
        return $p
    }
    # 백그라운드 녹화.
    #  - stderr 를 파이프로 받으면 진행 로그가 버퍼를 채워 ffmpeg 가 멈춘다.
    #  - 파일로 리다이렉트하면 부모 프로세스가 끝날 때 같이 죽는다.
    #  - Start-Process -WindowStyle Hidden(ShellExecute 경로)로 띄우면 gdigrab 이 화면을 못 잡는다.
    # 그래서 CreateNoWindow + UseShellExecute=false 로, 리다이렉트 없이 띄운다.
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $ff
    $psi.Arguments = $argLine
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true
    return [System.Diagnostics.Process]::Start($psi)
}

# 실제로 캡처를 돌리는 프로세스만 센다.
# ('screen-rec' 문자열만 보면 이 CLI 를 호출한 셸 명령줄까지 잡혀 오탐이 난다)
function Get-RecProcess {
    Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe' OR Name = 'ffmpeg.exe'" |
        Where-Object {
            $_.ProcessId -ne $PID -and (
                $_.CommandLine -like '*record-window.ps1*' -or
                $_.CommandLine -like '*record-screen.ps1*' -or
                ($_.Name -eq 'ffmpeg.exe' -and $_.CommandLine -like '*gdigrab*')
            )
        }
}

function Get-Windows {
    & powershell -ExecutionPolicy Bypass -NoProfile -File (Join-Path $root 'list-windows.ps1')
}

# ---------------------------------------------------------------- 동작

switch ($Action) {

    'devices' {
        $ff = Get-Ffmpeg
        if (-not $ff) { Write-Host 'ffmpeg 없음 — 소리 녹음과 mp4는 쓸 수 없습니다.' -ForegroundColor Yellow; return }
        Write-Host ("ffmpeg: {0}" -f $ff)
        foreach ($line in (Get-DshowLines)) {
            if ($line -match '"(.+)"\s+\((audio|video)\)') {
                Write-Host ("  [{0}] {1}" -f $Matches[2], $Matches[1])
            }
        }
        return
    }

    'list' {
        Get-Windows
        return
    }

    'status' {
        $st = Read-State
        # 살아있는지는 결과 파일이 지금도 커지는지로 본다.
        # (앱 실행 별칭/WMI 조회 누락 때문에 프로세스 목록만으로는 놓치는 경우가 있다)
        # 캡처 루프(powershell)의 pid 가 가장 확실하다
        $alive = $false
        if ($st -and $st.pid) { $alive = [bool](Get-Process -Id $st.pid -ErrorAction SilentlyContinue) }
        if (-not $alive) { $alive = (@(Get-RecProcess).Count -gt 0) }
        $frames = if (Test-Path $frameDir) { @(Get-ChildItem $frameDir -Filter '*.gif').Count } else { 0 }
        if ($alive -and $st -and $st.state -eq 'recording') {
            Write-Host ('녹화 중 — 대상 {0} / {1} / 시작 {2} / 프레임 {3}개' -f $st.target, $st.format, $st.started, $frames) -ForegroundColor Green
        }
        else {
            Write-Host '녹화 중이 아닙니다.' -ForegroundColor DarkGray
        }
        $recent = @(Get-ChildItem $outDir -File -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 5)
        if ($recent.Count) {
            Write-Host '최근 결과물:'
            $recent | ForEach-Object { Write-Host ('  {0}  {1:N2} MB  {2}' -f $_.Name, ($_.Length / 1MB), $_.LastWriteTime.ToString('MM-dd HH:mm')) }
        }
        return
    }

    'start' {
        $prev = Read-State
        if ($prev -and $prev.state -eq 'recording' -and $prev.pid -and (Get-Process -Id $prev.pid -ErrorAction SilentlyContinue)) {
            Write-Host '이미 녹화 중입니다. 먼저 stop 하세요.' -ForegroundColor Yellow; return
        }
        Remove-Item $stopFile -Force -ErrorAction SilentlyContinue
        if (-not $Name) { $Name = 'rec-' + (Get-Date -Format 'yyyyMMdd-HHmmss') }
        $outPath = Join-Path $outDir ("{0}.{1}" -f $Name, $Format)

        # 화면 캡처는 자체 구현(.NET)으로 통일한다.
        # 이 PC에서는 ffmpeg gdigrab 이 실시간으로 돌지 않고(빨리 감기), ddagrab 은 권한이 막혀 있다.
        # mp4 로 달라고 하면 stop 할 때 변환한다.
        $gifPath = Join-Path $outDir ("{0}.gif" -f $Name)
        $script = if ($Target -match '^(monitor\d|screen|\d+,\d+,\d+,\d+)$') { 'record-screen.ps1' } else { 'record-window.ps1' }
        $a = @('-ExecutionPolicy', 'Bypass', '-NoProfile', '-File', (Join-Path $root $script),
            '-Seconds', "$Seconds", '-Fps', "$Fps", '-Scale', "$Scale", '-Out', $gifPath)
        if ($script -eq 'record-window.ps1') {
            $a += @('-WindowTitle', $Target)
            if ($Rects) { $a += @('-MaskRects', $Rects) }
        }
        else {
            $a += @('-NoMask')
            if ($Target -eq 'screen') {
                Add-Type -AssemblyName System.Windows.Forms
                $b = [System.Windows.Forms.Screen]::PrimaryScreen.Bounds
                $a += @('-Rect', "$($b.X),$($b.Y),$($b.Width),$($b.Height)", '-FollowMonitor')
            }
            elseif ($Target -match '^monitor(\d)$') {
                Add-Type -AssemblyName System.Windows.Forms
                $b = (@([System.Windows.Forms.Screen]::AllScreens)[[int]$Matches[1] - 1]).Bounds
                $a += @('-Rect', "$($b.X),$($b.Y),$($b.Width),$($b.Height)")
            }
            else {
                $a += @('-Rect', $Target)
            }
        }
        $proc = Start-Process powershell -ArgumentList $a -WindowStyle Hidden -PassThru
        $recPid = $proc.Id

        # 소리는 별도로 받아 두었다가 stop 에서 합친다
        $audioPath = ''
        if ($Audio -eq 'mic') {
            $devName = Get-AudioDevice
            if ($devName) {
                # 이 PC에서는 ffmpeg 캡처(dshow/gdigrab)가 자식 프로세스로 띄우면 동작하지 않는다.
                # 화면과 동시에 소리를 받으려면 아래 명령을 사람이 콘솔에서 직접 실행해야 한다.
                Write-Host '소리는 이 창에서 함께 받을 수 없습니다. 필요하면 다른 콘솔에서 아래를 실행하세요:' -ForegroundColor Yellow
                Write-Host ("  powershell -ExecutionPolicy Bypass -File `"{0}`" -Action audio -Seconds {1} -Name {2}" -f $PSCommandPath, $Seconds, $Name)
                Write-Log 'audio: 자식 프로세스에서는 dshow 캡처가 되지 않아 안내만 출력'
            }
            else { Write-Host '녹음 장치를 찾지 못해 소리 없이 녹화합니다.' -ForegroundColor Yellow }
        }

        Write-State ([ordered]@{
                state   = 'recording'
                target  = $Target
                format  = $Format
                audio   = $Audio
                out     = $outPath
                gif     = $gifPath
                audioOut = $audioPath
                pid     = $recPid
                started = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
            })
        Write-Log ("start target=$Target format=$Format audio=$Audio out=$outPath")
        Start-Sleep -Seconds 2
        Write-Host ('녹화를 시작했습니다 → {0}' -f $outPath) -ForegroundColor Green
        Write-Host '멈추려면: rec.ps1 -Action stop'
        return
    }

    'stop' {
        New-Item -ItemType File -Path $stopFile -Force | Out-Null
        $prev = Read-State
        if ($prev -and $prev.pid) {
            # 캡처 루프는 stop.flag 를 보고 스스로 정리하므로 잠시 기다린다
            for ($i = 0; $i -lt 20; $i++) {
                if (-not (Get-Process -Id $prev.pid -ErrorAction SilentlyContinue)) { break }
                Start-Sleep -Milliseconds 500
            }
        }
        # 오디오는 지정 시간을 채우려 하므로 끊어 준다
        foreach ($p in @(Get-CimInstance Win32_Process -Filter "Name = 'ffmpeg.exe'" | Where-Object { $_.CommandLine -like '*dshow*' })) {
            Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue
        }
        Start-Sleep -Seconds 2

        if (-not $prev) { Write-Host '중지했습니다.'; return }
        $prev.state = 'stopped'
        Write-State $prev

        $gif = $prev.gif
        if (-not $gif -or -not (Test-Path $gif)) {
            Write-Host '중지했습니다. 결과 파일이 아직 없습니다(조립이 끝나지 않았을 수 있습니다).' -ForegroundColor Yellow
            return
        }

        $final = $gif
        if ($prev.format -eq 'mp4') {
            $mp4 = [System.IO.Path]::ChangeExtension($gif, '.mp4')
            $line = "-y -nostdin -nostats -loglevel error -i `"$gif`""
            if ($prev.audioOut -and (Test-Path $prev.audioOut)) {
                $line += " -i `"$($prev.audioOut)`" -c:a aac -shortest"
            }
            $line += " -movflags +faststart -pix_fmt yuv420p -vf `"scale=trunc(iw/2)*2:trunc(ih/2)*2`" `"$mp4`""
            [void](Invoke-Ffmpeg $line -Wait)
            if (Test-Path $mp4) {
                Remove-Item $gif -Force -ErrorAction SilentlyContinue
                if ($prev.audioOut -and (Test-Path $prev.audioOut)) { Remove-Item $prev.audioOut -Force -ErrorAction SilentlyContinue }
                $final = $mp4
            }
            else {
                Write-Host 'mp4 변환에 실패해 gif 로 남겨 둡니다.' -ForegroundColor Yellow
            }
        }
        elseif ($prev.audioOut -and (Test-Path $prev.audioOut)) {
            Write-Host ('소리는 따로 저장했습니다 → {0}' -f $prev.audioOut)
        }

        $size = (Get-Item $final).Length / 1MB
        Write-Host ('중지했습니다 → {0} ({1:N2} MB)' -f $final, $size) -ForegroundColor Green
        Write-Log ("stop -> $final")
        return
    }

    'audio' {
        $ff = Get-Ffmpeg
        if (-not $ff) { throw '녹음에는 ffmpeg가 필요합니다.' }
        if (-not $Name) { $Name = 'audio-' + (Get-Date -Format 'yyyyMMdd-HHmmss') }
        $outPath = Join-Path $outDir ("{0}.m4a" -f $Name)
        $devName = Get-AudioDevice
        if (-not $devName) { throw '녹음 장치를 찾지 못했습니다.' }
        Write-Host ('녹음 장치: {0}' -f $devName)
        [void](Invoke-Ffmpeg "-y -f dshow -i `"audio=$devName`" -t $Seconds -c:a aac -b:a 128k `"$outPath`"" -Wait)
        Write-Host ('저장 → {0} ({1:N2} MB)' -f $outPath, ((Get-Item $outPath).Length / 1MB)) -ForegroundColor Green
        Write-Log "audio $outPath"
        return
    }

    'build' {
        $out = if ($File) { $File } else { Join-Path $outDir ('build-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.gif') }
        & powershell -ExecutionPolicy Bypass -NoProfile -File (Join-Path $root 'build-gif.ps1') -FrameDir $frameDir -From $From -To $To -Fps $Fps -Out $out
        return
    }

    'mask' {
        if (-not $Rects) { throw '-Rects "x,y,w,h,..." 가 필요합니다.' }
        & powershell -ExecutionPolicy Bypass -NoProfile -File (Join-Path $root 'mask-frames.ps1') -FrameDir $frameDir -From $From -To $To -Rects $Rects
        return
    }

    'shrink' {
        if (-not $File) { throw '-File 로 줄일 GIF를 지정하세요.' }
        $src = (Resolve-Path $File).Path
        $scale = 1.0
        for ($i = 0; $i -lt 6; $i++) {
            $cur = (Get-Item $src).Length / 1MB
            if ($cur -le $MaxMB) { Write-Host ('{0:N2} MB — 목표 이하입니다.' -f $cur) -ForegroundColor Green; break }
            $scale = [math]::Round($scale * [math]::Sqrt($MaxMB / $cur) * 0.95, 3)
            $tmp = [System.IO.Path]::ChangeExtension($src, ".shrink.gif")
            & powershell -ExecutionPolicy Bypass -NoProfile -File (Join-Path $root 'shrink-gif.ps1') -In $src -Out $tmp -Scale $scale
            Move-Item $tmp $src -Force
            $scale = 1.0
        }
        return
    }
}
