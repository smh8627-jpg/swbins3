<#
  지정한 창을 직접 캡처해서 프레임으로 저장한다 (PrintWindow 방식).
  다른 창이 위에 겹쳐 있어도, 다른 모니터에 있어도 그 창의 내용만 잡힌다.

  - -WindowTitle : 캡처할 창 제목의 일부 (예: 'Back-Office')
  - -MaskRects   : 가릴 영역들. 창 기준 좌표를 x,y,w,h 순서로 4개씩 나열
  - 종료         : -Seconds 경과 또는 -StopFile 생성
  - 정지 화면은 파일로 저장하지 않고 유지 횟수만 센다 (파일명 _xN)
#>
param(
    [Parameter(Mandatory = $true)][string]$WindowTitle,
    [int]$Fps = 6,
    [int]$Seconds = 240,
    [double]$Scale = 0.45,
    [string]$Out = '',
    [string]$StopFile = '',
    [string]$MaskRects = '',
    [string]$FrameDir = ''
)

$ErrorActionPreference = 'Stop'

# -File 실행에서는 param 기본값의 $PSScriptRoot 가 비어 루트에 폴더가 생기므로 여기서 채운다
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not $Out) { $Out = Join-Path $root 'preview-window.gif' }
if (-not $StopFile) { $StopFile = Join-Path $root 'stop.flag' }
if (-not $FrameDir) { $FrameDir = Join-Path $root 'frames' }

Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName PresentationCore
Add-Type -AssemblyName WindowsBase

Add-Type -TypeDefinition @'
using System;
using System.Text;
using System.Drawing;
using System.Runtime.InteropServices;
public class CW {
    [DllImport("user32.dll")] public static extern bool PrintWindow(IntPtr h, IntPtr hdc, int flags);
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h, out RECT r);
    [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
    [DllImport("user32.dll")] public static extern bool IsIconic(IntPtr h);
    [DllImport("user32.dll")] public static extern bool EnumWindows(EnumProc cb, IntPtr p);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)] public static extern int GetWindowTextW(IntPtr h, StringBuilder s, int n);
    [DllImport("user32.dll")] public static extern bool GetCursorInfo(ref CURSORINFO ci);
    [DllImport("user32.dll")] public static extern bool DrawIconEx(IntPtr hdc, int x, int y, IntPtr hIcon, int w, int h, int step, IntPtr brush, int flags);
    public delegate bool EnumProc(IntPtr h, IntPtr p);
    [StructLayout(LayoutKind.Sequential)] public struct RECT { public int Left, Top, Right, Bottom; }
    [StructLayout(LayoutKind.Sequential)] public struct POINT { public int X, Y; }
    [StructLayout(LayoutKind.Sequential)] public struct CURSORINFO { public int cbSize; public int flags; public IntPtr hCursor; public POINT pt; }

    public static IntPtr FindByTitle(string needle) {
        IntPtr found = IntPtr.Zero;
        EnumWindows(delegate(IntPtr h, IntPtr p) {
            if (!IsWindowVisible(h)) return true;
            StringBuilder t = new StringBuilder(512);
            GetWindowTextW(h, t, 512);
            if (t.ToString().IndexOf(needle, StringComparison.OrdinalIgnoreCase) < 0) return true;
            RECT r; GetWindowRect(h, out r);
            if (r.Right - r.Left < 300) return true;
            found = h;
            return false;
        }, IntPtr.Zero);
        return found;
    }
    public static string TitleOf(IntPtr h) {
        StringBuilder s = new StringBuilder(512);
        GetWindowTextW(h, s, 512);
        return s.ToString();
    }
    // 창 안에 커서가 있으면 창 기준 좌표로 그린다
    public static void DrawCursor(Graphics g, int winX, int winY) {
        CURSORINFO ci = new CURSORINFO();
        ci.cbSize = Marshal.SizeOf(typeof(CURSORINFO));
        if (!GetCursorInfo(ref ci)) return;
        if (ci.flags != 1 || ci.hCursor == IntPtr.Zero) return;
        IntPtr hdc = g.GetHdc();
        try { DrawIconEx(hdc, ci.pt.X - winX, ci.pt.Y - winY, ci.hCursor, 0, 0, 0, IntPtr.Zero, 3); }
        finally { g.ReleaseHdc(hdc); }
    }
}
'@ -ReferencedAssemblies System.Drawing

$h = [CW]::FindByTitle($WindowTitle)
if ($h -eq [IntPtr]::Zero) { throw "창을 찾지 못했습니다: $WindowTitle" }
$r = New-Object CW+RECT
[void][CW]::GetWindowRect($h, [ref]$r)
$capW = $r.Right - $r.Left
$capH = $r.Bottom - $r.Top
$capW = $capW - ($capW % 2); $capH = $capH - ($capH % 2)
$outW = [int]([math]::Floor($capW * $Scale / 2) * 2)
$outH = [int]([math]::Floor($capH * $Scale / 2) * 2)

if (Test-Path $FrameDir) { Remove-Item $FrameDir -Recurse -Force }
New-Item -ItemType Directory -Path $FrameDir | Out-Null
if (Test-Path $StopFile) { Remove-Item $StopFile -Force }

$masks = @()
if ($MaskRects) {
    $nums = @($MaskRects -split '[,\s]+' | Where-Object { $_ -ne '' } | ForEach-Object { [int]$_ })
    for ($i = 0; $i + 3 -lt $nums.Count; $i += 4) {
        $masks += , @($nums[$i], $nums[$i + 1], $nums[$i + 2], $nums[$i + 3])
    }
}

Write-Host ("창: [{0}] {1}x{2}  ->  출력 {3}x{4}  {5}fps  최대 {6}초  가림 {7}곳" -f [CW]::TitleOf($h), $capW, $capH, $outW, $outH, $Fps, $Seconds, $masks.Count)

$interval = [int](1000 / $Fps)
$sw = [System.Diagnostics.Stopwatch]::StartNew()
$n = 0; $total = 0; $hold = 1; $prevHash = $null; $lastFile = $null; $failed = 0
$md5 = [System.Security.Cryptography.MD5]::Create()

while ($sw.Elapsed.TotalSeconds -lt $Seconds) {
    $tick = [System.Diagnostics.Stopwatch]::StartNew()

    $bmp = New-Object System.Drawing.Bitmap $capW, $capH, ([System.Drawing.Imaging.PixelFormat]::Format32bppRgb)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $hdc = $g.GetHdc()
    $ok = [CW]::PrintWindow($h, $hdc, 2)
    $g.ReleaseHdc($hdc)
    if (-not $ok) { $failed++ }

    foreach ($m in $masks) {
        $g.FillRectangle([System.Drawing.Brushes]::Black, $m[0], $m[1], $m[2], $m[3])
    }
    [void][CW]::GetWindowRect($h, [ref]$r)
    [CW]::DrawCursor($g, $r.Left, $r.Top)
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
    $gs.Dispose(); $ms.Dispose()

    $hash = [BitConverter]::ToString($md5.ComputeHash($bytes))
    if ($hash -eq $prevHash) {
        $hold++
    }
    else {
        if ($lastFile -and $hold -gt 1) {
            Rename-Item $lastFile ('{0}_x{1}.gif' -f [System.IO.Path]::GetFileNameWithoutExtension($lastFile), $hold)
        }
        $hold = 1
        $lastFile = Join-Path $FrameDir ("f{0:D5}.gif" -f $n)
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
    Rename-Item $lastFile ('{0}_x{1}.gif' -f [System.IO.Path]::GetFileNameWithoutExtension($lastFile), $hold)
}
$md5.Dispose()
Write-Host ("캡처 {0}틱 / 저장 프레임 {1}개 / {2:N1}초 / 실패 {3}틱" -f $total, $n, $sw.Elapsed.TotalSeconds, $failed)
