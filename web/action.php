<?php
/**
 * 녹화 페이지의 시작 / 중지 처리.
 * 화면을 캡처하는 기능이므로 이 PC에서 온 요청만 받는다.
 */

require __DIR__ . '/lib.php';

function recBack($msg, $isError = false) {
    header('Location: /?msg=' . urlencode($msg) . ($isError ? '&err=1' : ''));
    exit;
}

if (!recIsLocalRequest()) {
    http_response_code(403);
    recBack('이 PC에서만 실행할 수 있습니다.', true);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    recBack('잘못된 요청입니다.', true);
}

$do = isset($_POST['do']) ? $_POST['do'] : '';

switch ($do) {

    case 'start':
        $target = isset($_POST['target']) ? trim($_POST['target']) : 'screen';
        $format = (isset($_POST['format']) && $_POST['format'] === 'gif') ? 'gif' : 'mp4';
        $fps = isset($_POST['fps']) ? max(1, min(30, (int)$_POST['fps'])) : 10;
        $seconds = isset($_POST['seconds']) ? max(5, min(3600, (int)$_POST['seconds'])) : 300;

        $cmd = sprintf(
            "& powershell -ExecutionPolicy Bypass -NoProfile -File '%s\\rec.ps1' -Action start -Target '%s' -Format %s -Audio none -Fps %d -Seconds %d",
            REC_ROOT, recQuote($target), $format, $fps, $seconds
        );
        $out = recPowerShell($cmd);
        if (strpos($out, '녹화를 시작했습니다') !== false) {
            recBack('녹화를 시작했습니다. 끝나면 [중지하고 저장]을 누르세요.');
        }
        recBack('시작하지 못했습니다: ' . mb_substr(trim($out), 0, 200), true);
        break;

    case 'stop':
        $out = recPowerShell("& powershell -ExecutionPolicy Bypass -NoProfile -File '" . REC_ROOT . "\\rec.ps1' -Action stop");
        recBack(trim($out) !== '' ? mb_substr(trim($out), 0, 200) : '중지했습니다.');
        break;

    default:
        recBack('알 수 없는 요청입니다.', true);
}
