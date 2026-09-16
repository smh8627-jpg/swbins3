<#
  frames\ 의 프레임 GIF들을 애니메이션 GIF 하나로 조립한다.
  - -From/-To 로 구간을 자른다 (프레임 번호, 0부터)
  - -Every N 으로 N개 중 1개만 사용해 프레임을 솎는다
  - 직전 프레임과 동일한 화면은 넣지 않고 앞 프레임의 재생시간을 늘린다 (용량 절감)
#>
param(
    [int]$Fps = 8,
    [int]$From = 0,
    [int]$To = -1,
    [int]$Every = 1,
    [string]$FrameDir = "$PSScriptRoot\frames",
    [string]$Out = "$PSScriptRoot\preview.gif",
    [int]$HoldLastMs = 0
)

$ErrorActionPreference = 'Stop'

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

$files = @(Get-ChildItem $FrameDir -Filter '*.gif' | Sort-Object Name)
if ($files.Count -eq 0) { throw "프레임이 없습니다: $FrameDir" }
if ($To -lt 0 -or $To -ge $files.Count) { $To = $files.Count - 1 }
$selected = @()
for ($i = $From; $i -le $To; $i += $Every) { $selected += $files[$i] }
if ($selected.Count -eq 0) { throw '선택된 프레임이 없습니다.' }

$first = [System.IO.File]::ReadAllBytes($selected[0].FullName)
$os = New-Object System.IO.MemoryStream
$os.Write($first, 0, 13)
$loop = [byte[]](0x21, 0xFF, 0x0B) + [System.Text.Encoding]::ASCII.GetBytes('NETSCAPE2.0') + [byte[]](0x03, 0x01, 0x00, 0x00, 0x00)
$os.Write($loop, 0, $loop.Length)

$baseDelay = [int][math]::Round(100 / $Fps * $Every)
if ($baseDelay -lt 2) { $baseDelay = 2 }
$prevHash = $null
$lastGcePos = -1
$written = 0
$skipped = 0
$md5 = [System.Security.Cryptography.MD5]::Create()

foreach ($f in $selected) {
    $fb = [System.IO.File]::ReadAllBytes($f.FullName)
    $hash = [BitConverter]::ToString($md5.ComputeHash($fb))
    if ($hash -eq $prevHash -and $lastGcePos -ge 0) {
        # 화면이 그대로다 -> 앞 프레임 재생시간만 늘린다
        $cur = $os.Position
        $os.Position = $lastGcePos + 4
        $arr = $os.ToArray()
        $curDelay = $arr[$lastGcePos + 4] + $arr[$lastGcePos + 5] * 256
        $newDelay = [math]::Min(65535, $curDelay + $baseDelay)
        $os.WriteByte([byte]($newDelay -band 0xFF))
        $os.WriteByte([byte](($newDelay -shr 8) -band 0xFF))
        $os.Position = $cur
        $skipped++
        continue
    }
    $prevHash = $hash
    $mult = 1
    if ($f.Name -match '_x(\d+)\.gif$') { $mult = [int]$Matches[1] }
    $thisDelay = [math]::Min(65535, $baseDelay * $mult)
    $lastGcePos = $os.Position
    $gce = [byte[]](0x21, 0xF9, 0x04, 0x04, ($thisDelay -band 0xFF), (($thisDelay -shr 8) -band 0xFF), 0x00, 0x00)
    $os.Write($gce, 0, $gce.Length)
    $img = Get-ImageBlock $fb
    $os.Write($img, 0, $img.Length)
    $written++
}

if ($HoldLastMs -gt 0 -and $lastGcePos -ge 0) {
    $cur = $os.Position
    $arr = $os.ToArray()
    $curDelay = $arr[$lastGcePos + 4] + $arr[$lastGcePos + 5] * 256
    $newDelay = [math]::Min(65535, $curDelay + [int]($HoldLastMs / 10))
    $os.Position = $lastGcePos + 4
    $os.WriteByte([byte]($newDelay -band 0xFF))
    $os.WriteByte([byte](($newDelay -shr 8) -band 0xFF))
    $os.Position = $cur
}

$os.WriteByte(0x3B)
[System.IO.File]::WriteAllBytes($Out, $os.ToArray())
$os.Dispose()
$md5.Dispose()

$size = (Get-Item $Out).Length
$secs = ($written * $baseDelay + $skipped * $baseDelay) / 100.0
Write-Host ("완료: {0}" -f $Out)
Write-Host ("  프레임 {0}개 사용 / 중복 {1}개 병합 / 재생 약 {2:N1}초 / {3:N2} MB" -f $written, $skipped, $secs, ($size / 1MB))
