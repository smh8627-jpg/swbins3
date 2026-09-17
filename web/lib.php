<?php
/**
 * screen-rec 웹 UI 공통 헬퍼. swbins2 lib.php 의 해당 함수를 그대로 옮겼다.
 */

define('REC_ROOT', dirname(__DIR__));
define('REC_OUT', REC_ROOT . '\\out');

/** 요청이 이 PC에서 온 것인지(같은 호스트의 다른 IP 포함) */
function recIsLocalRequest() {
    $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if ($remote === '') {
        return false;
    }
    if (in_array($remote, ['127.0.0.1', '::1'], true)) {
        return true;
    }
    $ips = gethostbynamel(gethostname());
    return is_array($ips) && in_array($remote, $ips, true);
}

/** PowerShell 명령을 UTF-8로 돌리고 결과를 받는다 */
function recPowerShell($command) {
    $full = '$ProgressPreference = "SilentlyContinue"; ' .
        '[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; ' . $command;
    $encoded = base64_encode(mb_convert_encoding($full, 'UTF-16LE', 'UTF-8'));
    $cmd = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand ' . $encoded;
    $out = trim((string)shell_exec($cmd));

    if (strpos($out, '#< CLIXML') === 0) {
        $lines = preg_split('/\r?\n/', $out);
        $clean = [];
        foreach ($lines as $line) {
            if (strpos($line, '#< CLIXML') === 0 || strpos($line, '<Objs') === 0) {
                continue;
            }
            $clean[] = $line;
        }
        $out = trim(implode("\n", $clean));
    }
    return $out;
}

/** PowerShell 작은따옴표 안에 넣을 문자열 이스케이프 */
function recQuote($s) {
    return str_replace("'", "''", $s);
}

function recSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    return number_format($bytes / 1024) . ' KB';
}
