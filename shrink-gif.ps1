<#
  애니메이션 GIF를 축소해 다시 인코딩한다. 프레임 수와 각 프레임의 재생시간은 그대로 유지한다.
  -Scale 0.8  : 가로세로 80%
  -Every  2   : 프레임 2개당 1개만 사용(재생시간은 합쳐서 보존)
#>
param(
    [Parameter(Mandatory = $true)][string]$In,
    [Parameter(Mandatory = $true)][string]$Out,
    [double]$Scale = 0.8,
    [int]$Every = 1
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName PresentationCore
Add-Type -AssemblyName WindowsBase

function Get-ImageBlock([byte[]]$b) {
    $packed = $b[10]
    $gct = ($packed -band 0x80) -ne 0
    $pos = 13
    if ($gct) { $pos += 3 * [int][math]::Pow(2, ($packed -band 7) + 1) }
    while ($pos -lt $b.Length) {
        $bb = $b[$pos]
        if ($bb -eq 0x2C) {
            $start = $pos
            $p2 = $b[$pos + 9]
            $lct = ($p2 -band 0x80) -ne 0
            $pos += 10
            if ($lct) { $pos += 3 * [int][math]::Pow(2, ($p2 -band 7) + 1) }
            $pos++
            while ($b[$pos] -ne 0) { $pos += $b[$pos] + 1 }
            $pos++
            return , $b[$start..($pos - 1)]
        }
        elseif ($bb -eq 0x21) {
            $pos += 2
            while ($b[$pos] -ne 0) { $pos += $b[$pos] + 1 }
            $pos++
        }
        else { break }
    }
    throw '이미지 블록을 찾지 못했습니다.'
}

$src = [System.Drawing.Image]::FromFile((Resolve-Path $In))
$dim = New-Object System.Drawing.Imaging.FrameDimension ($src.FrameDimensionsList[0])
$count = $src.GetFrameCount($dim)
$delays = @()
try {
    $pi = $src.GetPropertyItem(0x5100)
    for ($i = 0; $i -lt $count; $i++) { $delays += [BitConverter]::ToInt32($pi.Value, $i * 4) }
}
catch {
    for ($i = 0; $i -lt $count; $i++) { $delays += 10 }
}

$outW = [int]([math]::Floor($src.Width * $Scale / 2) * 2)
$outH = [int]([math]::Floor($src.Height * $Scale / 2) * 2)
Write-Host ("원본 {0}x{1} {2}프레임 -> {3}x{4}, {5}개당 1프레임" -f $src.Width, $src.Height, $count, $outW, $outH, $Every)

$os = New-Object System.IO.MemoryStream
$header = $null
$written = 0
$pending = 0

for ($i = 0; $i -lt $count; $i++) {
    $pending += $delays[$i]
    if (($i % $Every) -ne 0 -and $i -ne ($count - 1)) { continue }

    [void]$src.SelectActiveFrame($dim, $i)
    $small = New-Object System.Drawing.Bitmap $outW, $outH
    $g = [System.Drawing.Graphics]::FromImage($small)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.DrawImage($src, 0, 0, $outW, $outH)
    $g.Dispose()

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

    if (-not $header) {
        $os.Write($bytes, 0, 13)
        $loop = [byte[]](0x21, 0xFF, 0x0B) + [System.Text.Encoding]::ASCII.GetBytes('NETSCAPE2.0') + [byte[]](0x03, 0x01, 0x00, 0x00, 0x00)
        $os.Write($loop, 0, $loop.Length)
        $header = $true
    }
    $d = [math]::Min(65535, [math]::Max(2, $pending))
    $pending = 0
    $gce = [byte[]](0x21, 0xF9, 0x04, 0x04, ($d -band 0xFF), (($d -shr 8) -band 0xFF), 0x00, 0x00)
    $os.Write($gce, 0, $gce.Length)
    $img = Get-ImageBlock $bytes
    $os.Write($img, 0, $img.Length)
    $written++
}
$src.Dispose()
$os.WriteByte(0x3B)
[System.IO.File]::WriteAllBytes($Out, $os.ToArray())
$os.Dispose()

$size = (Get-Item $Out).Length
Write-Host ("완료: {0}  ({1}프레임, {2:N2} MB)" -f $Out, $written, ($size / 1MB))
