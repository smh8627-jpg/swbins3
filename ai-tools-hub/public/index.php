<?php
/**
 * Slim 프론트 컨트롤러.
 * 실행: php -S 127.0.0.1:PORT -t public public/index.php  (serve.ps1 참고)
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/data.php';
require __DIR__ . '/../src/render.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response) {
    $html = aihubRenderHome(aihubLoadTools(), aihubLoadTips());
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->run();
