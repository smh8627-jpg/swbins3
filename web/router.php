<?php
/**
 * `php -S 127.0.0.1:PORT web/router.php` 로 띄울 때 쓰는 라우터.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($uri) {
    case '/':
        require __DIR__ . '/index.php';
        return true;
    case '/action':
        require __DIR__ . '/action.php';
        return true;
    case '/file':
        require __DIR__ . '/file.php';
        return true;
    default:
        return false;
}
