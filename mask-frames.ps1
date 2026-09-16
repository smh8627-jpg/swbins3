<#
  이미 저장된 프레임(GIF)에 사각형 마스크를 덧칠해 다시 저장한다.
  좌표는 프레임(축소된) 기준. -Rects "x,y,w,h,x,y,w,h,..."
  -From/-To 로 일부 프레임에만 적용할 수 있다.
#>
param(
    [Parameter(Mandatory = $true)][string]$Rects,
    [string]$FrameDir = '',
    [int]$From = 0,
    [int]$To = -1,
    [string]$Color = 'Black'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not $FrameDir) { $FrameDir = Join-Path $root 'frames' }

Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName PresentationCore
Add-Type -AssemblyName WindowsBase

$nums = @($Rects -split '[,\s]+' | Where-Object { $_ -ne '' } | ForEach-Object { [int]$_ })
$masks = @()
for ($i = 0; $i + 3 -lt $nums.Count; $i += 4) {
    $masks += , @($nums[$i], $nums[$i + 1], $nums[$i + 2], $nums[$i + 3])
}
if ($masks.Count -eq 0) { throw '마스크 좌표가 없습니다.' }

$files = @(Get-ChildItem $FrameDir -Filter '*.gif' | Sort-Object Name)
if ($To -lt 0 -or $To -ge $files.Count) { $To = $files.Count - 1 }
$done = 0

for ($i = $From; $i -le $To; $i++) {
    $path = $files[$i].FullName
    $img = [System.Drawing.Image]::FromFile($path)
    $bmp = New-Object System.Drawing.Bitmap $img
    $img.Dispose()
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $brush = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromName($Color))
    foreach ($m in $masks) { $g.FillRectangle($brush, $m[0], $m[1], $m[2], $m[3]) }
    $brush.Dispose()
    $g.Dispose()

    $ms = New-Object System.IO.MemoryStream
    $bmp.Save($ms, [System.Drawing.Imaging.ImageFormat]::Bmp)
    $bmp.Dispose()
    $ms.Position = 0
    $dec = New-Object System.Windows.Media.Imaging.BmpBitmapDecoder($ms, [System.Windows.Media.Imaging.BitmapCreateOptions]::PreservePixelFormat, [System.Windows.Media.Imaging.BitmapCacheOption]::OnLoad)
    $enc = New-Object System.Windows.Media.Imaging.GifBitmapEncoder
    $enc.Frames.Add($dec.Frames[0])
    $fs = [System.IO.File]::Create($path)
    $enc.Save($fs)
    $fs.Close()
    $ms.Dispose()
    $done++
}
Write-Host ("마스크 {0}곳을 프레임 {1}~{2} ({3}개)에 적용했습니다." -f $masks.Count, $From, $To, $done)
