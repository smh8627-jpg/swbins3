<#
  화면 녹화 -> 프레임 저장 + 미리보기 GIF (설치 불필요, .NET만 사용)

  - 캡처 영역: EditPlus 메인 창(기본) 또는 -Rect 로 직접 지정
  - 마스킹: EditPlus 접속 다이얼로그(#32770)의 계정 라벨(Static 1482)을 매 프레임 추적해 가림
  - 마우스 커서 포함
  - 종료: -Seconds 경과 또는 -StopFile 생성 시
  - 프레임은 frames\ 에 남는다 (build-gif.ps1 로 구간을 잘라 최종본 조립)
#>
param(
    [int]$Fps = 8,
    [int]$Seconds = 60,
    [double]$Scale = 0.5,
    [string]$Out = "$PSScriptRoot\preview.gif",
    [string]$StopFile = "$PSScriptRoot\stop.flag",
    [string]$Rect = '',
    [string]$MaskLabel = '계정@서버',
    [switch]$NoMask,
    [string]$Follow = '',
    [switch]$FollowMonitor
)

$ErrorActionPreference = 'Stop'

# -File 실행에서는 param 기본값의 $PSScriptRoot 가 비어 루트를 가리키므로 여기서 채운다
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not $Out -or $Out -eq '\preview.gif') { $Out = Join-Path $root 'preview.gif' }
if (-not $StopFile -or $StopFile -eq '\stop.flag') { $StopFile = Join-Path $root 'stop.flag' }

Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName PresentationCore
Add-Type -AssemblyName WindowsBase

$sig = @'
using System;
using System.Text;
using System.Drawing;
using System.Runtime.InteropServices;
public class W {
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h, out RECT r);
    [DllImport("user32.dll")] public static extern IntPtr GetDlgItem(IntPtr h, int id);
    [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)] public static extern int GetClassName(IntPtr h, StringBuilder s, int n);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)] public static extern int GetWindowTextW(IntPtr h, StringBuilder s, int n);
    [DllImport("user32.dll")] public static extern bool EnumWindows(EnumProc cb, IntPtr p);
    [DllImport("user32.dll")] public static extern bool GetCursorInfo(ref CURSORINFO ci);
    [DllImport("user32.dll")] public static extern IntPtr GetForegroundWindow();
    [DllImport("user32.dll")] public static extern int GetWindowThreadProcessId(IntPtr h, out int pid);
    [DllImport("user32.dll")] public static extern bool DrawIconEx(IntPtr hdc, int x, int y, IntPtr hIcon, int w, int h, int step, IntPtr brush, int flags);
    public delegate bool EnumProc(IntPtr h, IntPtr p);
    [StructLayout(LayoutKind.Sequential)] public struct RECT { public int Left, Top, Right, Bottom; }
    [StructLayout(LayoutKind.Sequential)] public struct POINT { public int X, Y; }
    [StructLayout(LayoutKind.Sequential)] public struct CURSORINFO { public int cbSize; public int flags; public IntPtr hCursor; public POINT pt; }

    public static IntPtr FindDialog() {
        IntPtr found = IntPtr.Zero;
        EnumWindows(delegate(IntPtr h, IntPtr p) {
            if (!IsWindowVisible(h)) return true;
            StringBuilder c = new StringBuilder(64);
            GetClassName(h, c, 64);
            if (c.ToString() != "#32770") return true;
            if (GetDlgItem(h, 1482) == IntPtr.Zero) return true;
            found = h;
            return false;
        }, IntPtr.Zero);
        return found;
    }
    public static void DrawCursor(Graphics g, int offX, int offY) {
        CURSORINFO ci = new CURSORINFO();
        ci.cbSize = Marshal.SizeOf(typeof(CURSORINFO));
        if (!GetCursorInfo(ref ci)) return;
        if (ci.flags != 1 || ci.hCursor == IntPtr.Zero) return;
        IntPtr hdc = g.GetHdc();
        try { DrawIconEx(hdc, ci.pt.X - offX, ci.pt.Y - offY, ci.hCursor, 0, 0, 0, IntPtr.Zero, 3); }
        finally { g.ReleaseHdc(hdc); }
    }
}
'@
Add-Type -TypeDefinition $sig -ReferencedAssemblies System.Drawing

function Get-Rect([IntPtr]$h) {
    $r = New-Object W+RECT
    [void][W]::GetWindowRect($h, [ref]$r)
    return $r
}

# ---------- 캡처 영역 ----------
$rectNums = @()
if ($Rect) { $rectNums = @($Rect -split '[,\s]+' | Where-Object { $_ -ne '' } | ForEach-Object { [int]$_ }) }
if ($rectNums.Count -eq 4) {
    $capX = $rectNums[0]; $capY = $rectNums[1]; $capW = $rectNums[2]; $capH = $rectNums[3]
}
else {
    $ep = Get-Process editplus -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $ep) { throw 'EditPlus가 실행 중이 아닙니다. -Rect 로 영역을 지정하세요.' }
    $r = Get-Rect $ep.MainWindowHandle
    $capX = $r.Left; $capY = $r.Top; $capW = $r.Right - $r.Left; $capH = $r.Bottom - $r.Top
}
$capW = $capW - ($capW % 2); $capH = $capH - ($capH % 2)
$outW = [int]([math]::Floor($capW * $Scale / 2) * 2)
$outH = [int]([math]::Floor($capH * $Scale / 2) * 2)

$frameDir = Join-Path $root 'frames'
if (Test-Path $frameDir) { Remove-Item $frameDir -Recurse -Force }
New-Item -ItemType Directory -Path $frameDir | Out-Null
if (Test-Path $StopFile) { Remove-Item $StopFile -Force }

Write-Host ("캡처 영역: {0},{1} {2}x{3}  ->  출력 {4}x{5}  {6}fps  최대 {7}초" -f $capX, $capY, $capW, $capH, $outW, $outH, $Fps, $Seconds)
Write-Host "중지하려면: $StopFile 파일 생성"

$maskFont = New-Object System.Drawing.Font('Segoe UI', ([math]::Max(9, [int](12 * $Scale))), [System.Drawing.FontStyle]::Bold)
$interval = [int](1000 / $Fps)
$sw = [System.Diagnostics.Stopwatch]::StartNew()
$n = 0
$total = 0
$hold = 1
$prevHash = $null
$lastFile = $null
$md5 = [System.Security.Cryptography.MD5]::Create()
$maskedFrames = 0
$maskLog = @()

while ($sw.Elapsed.TotalSeconds -lt $Seconds) {
    $tick = [System.Diagnostics.Stopwatch]::StartNew()

    # 활성 창이 있는 모니터로 캡처 영역을 옮긴다 (어느 화면에서 작업하든 따라감)
    if ($FollowMonitor) {
        $fg = [W]::GetForegroundWindow()
        if ($fg -ne [IntPtr]::Zero) {
            $fr = Get-Rect $fg
            $cx = [int](($fr.Left + $fr.Right) / 2)
            $cy = [int](($fr.Top + $fr.Bottom) / 2)
            foreach ($scr in [System.Windows.Forms.Screen]::AllScreens) {
                $b = $scr.Bounds
                if ($cx -ge $b.X -and $cx -lt ($b.X + $b.Width) -and $cy -ge $b.Y -and $cy -lt ($b.Y + $b.Height)) {
                    $capX = $b.X
                    $capY = $b.Y
                    break
                }
            }
        }
    }

    # 지정한 프로그램의 창이 활성 상태면 캡처 위치를 그 창에 맞춘다 (크기는 고정)
    if ($Follow) {
        $fg = [W]::GetForegroundWindow()
        if ($fg -ne [IntPtr]::Zero) {
            $fgPid = 0
            [void][W]::GetWindowThreadProcessId($fg, [ref]$fgPid)
            $fgProc = Get-Process -Id $fgPid -ErrorAction SilentlyContinue
            if ($fgProc -and $fgProc.ProcessName -match $Follow) {
                $fr = Get-Rect $fg
                if (($fr.Right - $fr.Left) -gt 200) {
                    $capX = $fr.Left
                    $capY = $fr.Top
                }
            }
        }
    }

    $bmp = New-Object System.Drawing.Bitmap $capW, $capH, ([System.Drawing.Imaging.PixelFormat]::Format32bppRgb)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.CopyFromScreen($capX, $capY, 0, 0, (New-Object System.Drawing.Size $capW, $capH))

    if (-not $NoMask) {
        $dlg = [W]::FindDialog()
        if ($dlg -ne [IntPtr]::Zero) {
            $lbl = [W]::GetDlgItem($dlg, 1482)
            if ($lbl -ne [IntPtr]::Zero) {
                $lr = Get-Rect $lbl
                $x = $lr.Left - $capX; $y = $lr.Top - $capY
                $w = $lr.Right - $lr.Left; $h = $lr.Bottom - $lr.Top
                if ($w -gt 0 -and $h -gt 0) {
                    $g.FillRectangle([System.Drawing.Brushes]::Black, $x, $y, $w, $h)
                    $g.DrawString($MaskLabel, $maskFont, [System.Drawing.Brushes]::White, ([single]($x + 2)), ([single]($y + 1)))
                    $maskedFrames++
                    if ($maskLog.Count -lt 3) { $maskLog += ("frame {0}: 라벨 {1},{2} {3}x{4}" -f $n, $x, $y, $w, $h) }
                }
            }
        }
    }
    [W]::DrawCursor($g, $capX, $capY)
    $g.Dispose()

    $small = New-Object System.Drawing.Bitmap $outW, $outH
    $g2 = [System.Drawing.Graphics]::FromImage($small)
    $g2.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g2.DrawImage($bmp, 0, 0, $outW, $outH)
    $g2.Dispose()
    $bmp.Dispose()

    $ms = New-Object System.IO.MemoryStream
    $small.Save($ms, [System.Drawing.Imaging.ImageFormat]::Bmp)
    $small.Dispose()
    $ms.Position = 0
    $dec = New-Object System.Windows.Media.Imaging.BmpBitmapDecoder($ms, [System.Windows.Media.Imaging.BitmapCreateOptions]::PreservePixelFormat, [System.Windows.Media.Imaging.BitmapCacheOption]::OnLoad)
    $enc = New-Object System.Windows.Media.Imaging.GifBitmapEncoder
    $enc.Frames.Add($dec.Frames[0])
    $gs = New-Object System.IO.MemoryStream
    $enc.Save($gs)
    $bytes = $gs.ToArray()
    $gs.Dispose()
    $ms.Dispose()

    # 화면이 직전과 똑같으면 파일을 만들지 않고 유지 횟수만 센다 (대기 중 용량 폭증 방지)
    $hash = [BitConverter]::ToString($md5.ComputeHash($bytes))
    if ($hash -eq $prevHash) {
        $hold++
    }
    else {
        if ($lastFile -and $hold -gt 1) {
            $newName = '{0}_x{1}.gif' -f [System.IO.Path]::GetFileNameWithoutExtension($lastFile), $hold
            Rename-Item $lastFile $newName
        }
        $hold = 1
        $lastFile = Join-Path $frameDir ("f{0:D5}.gif" -f $n)
        [System.IO.File]::WriteAllBytes($lastFile, $bytes)
        $prevHash = $hash
        $n++
    }
    $total++
    if (Test-Path $StopFile) { break }
    $rest = $interval - $tick.ElapsedMilliseconds
    if ($rest -gt 0) { Start-Sleep -Milliseconds $rest }
}
$sw.Stop()
if ($lastFile -and $hold -gt 1) {
    $newName = '{0}_x{1}.gif' -f [System.IO.Path]::GetFileNameWithoutExtension($lastFile), $hold
    Rename-Item $lastFile $newName
}
$md5.Dispose()
Write-Host ("캡처 {0}틱 / 저장 프레임 {1}개 / {2:N1}초 / 마스킹된 프레임 {3}개" -f $total, $n, $sw.Elapsed.TotalSeconds, $maskedFrames)
$maskLog | ForEach-Object { Write-Host "  $_" }
if ($n -eq 0) { throw '캡처된 프레임이 없습니다.' }

& (Join-Path $root 'build-gif.ps1') -Fps $Fps -Out $Out
