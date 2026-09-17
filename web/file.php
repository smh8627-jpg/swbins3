<?php
/**
 * 녹화 결과물 내려받기.
 * out\ 안의 파일만 내보낸다(경로 조작 차단).
 */

require __DIR__ . '/lib.php';

$name = isset($_GET['f']) ? $_GET['f'] : '';

if ($name === '' || preg_match('/[\\\\\\/]|\.\./', $name)) {
    http_response_code(400);
    echo 'bad request';
    exit;
}

$path = REC_OUT . '\\' . $name;
if (!is_file($path)) {
    http_response_code(404);
    echo 'not found';
    exit;
}

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$types = [
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'm4a' => 'audio/mp4',
    'png' => 'image/png',
    'wav' => 'audio/wav',
];
if (!isset($types[$ext])) {
    http_response_code(415);
    echo 'unsupported';
    exit;
}

header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
