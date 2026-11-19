<?php

declare(strict_types=1);

use TaskFlow\App;
use TaskFlow\Core\Database;
use TaskFlow\Core\Env;
use TaskFlow\Core\Request;

require dirname(__DIR__) . '/bootstrap.php';

$request = Request::fromGlobals();

$allowedOrigin = (string) Env::get('CORS_ALLOWED_ORIGIN', '*');

header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Vary: Origin');

if ($request->method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $app = new App(Database::connect());
    $app->handle($request)->send();
} catch (Throwable $exception) {
    error_log((string) $exception);

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    $message = Env::bool('APP_DEBUG')
        ? $exception->getMessage()
        : 'Unable to start the application.';

    echo json_encode([
        'error' => [
            'code' => 'bootstrap_error',
            'message' => $message,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
