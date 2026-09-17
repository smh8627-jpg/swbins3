<#
  화면 녹화 웹 UI를 띄운다.
  사용: powershell -ExecutionPolicy Bypass -File serve.ps1 [-Port 8610]
#>
param(
    [int]$Port = 8610
)

$root = $PSScriptRoot
Write-Host "http://127.0.0.1:$Port/ 에서 엽니다 (Ctrl+C 로 중지)"
php -S "127.0.0.1:$Port" -t "$root\web" "$root\web\router.php"
