<?php
/**
 * Slim 프론트 컨트롤러.
 * 실행: php -S 127.0.0.1:PORT -t public public/index.php  (serve.ps1 참고)
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/data.php';
require __DIR__ . '/../src/render.php';
require __DIR__ . '/../src/sdapi.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response) {
    $html = aihubRenderHome(aihubLoadTools(), aihubLoadTips());
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->post('/generate/image', function (Request $request, Response $response) {
    $body = json_decode((string)$request->getBody(), true);
    $result = aihubGenerateImage(is_array($body) ? $body : []);
    $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$app->post('/generate/video', function (Request $request, Response $response) {
    $body = json_decode((string)$request->getBody(), true);
    $result = aihubGenerateVideo(is_array($body) ? $body : []);
    $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$app->post('/generate/music', function (Request $request, Response $response) {
    $body = json_decode((string)$request->getBody(), true);
    $result = aihubGenerateMusic(is_array($body) ? $body : []);
    $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$app->post('/generate/document', function (Request $request, Response $response) {
    $body = json_decode((string)$request->getBody(), true);
    $result = aihubGenerateDocument(is_array($body) ? $body : []);
    $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$app->post('/generate/code', function (Request $request, Response $response) {
    $body = json_decode((string)$request->getBody(), true);
    $result = aihubGenerateCode(is_array($body) ? $body : []);
    $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$app->post('/generate/voice', function (Request $request, Response $response) {
    $body = json_decode((string)$request->getBody(), true);
    $result = aihubGenerateVoice(is_array($body) ? $body : []);
    $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$app->run();
