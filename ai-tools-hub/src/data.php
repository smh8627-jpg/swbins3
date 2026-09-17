<?php
/**
 * data/*.json 로더 + 이스케이프 헬퍼.
 */

define('AIHUB_DATA', dirname(__DIR__) . '/data');

function aihubLoadJson(string $file): array
{
    $path = AIHUB_DATA . '/' . $file;
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function aihubLoadTools(): array
{
    return aihubLoadJson('tools.json');
}

function aihubLoadTips(): array
{
    return aihubLoadJson('token-saving.json');
}

function aihubEsc($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
