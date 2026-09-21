<#
  AI 도구 모음 웹 UI를 띄운다.
  사용: powershell -ExecutionPolicy Bypass -File serve.ps1 [-Port 8611]
#>
param(
    [int]$Port = 8611
)

$root = $PSScriptRoot
Write-Host "http://127.0.0.1:$Port/ 에서 엽니다 (Ctrl+C 로 중지)"
# PHP 내장 서버의 기본 max_execution_time(30초)은 이미지 생성 요청(번역 최대 30초 +
# sd-webui 생성 최대 180초, sdapi.php 참고)보다 짧아서 생성 중간에 스크립트가 강제
# 종료되고, 그 PHP 에러 페이지가 JSON 대신 브라우저로 내려가 "Unexpected token '<'"
# 파싱 오류로 나타난다. 240초로 넉넉히 올려서 방지한다.
php -d max_execution_time=240 -S "127.0.0.1:$Port" -t "$root\public" "$root\public\index.php"
