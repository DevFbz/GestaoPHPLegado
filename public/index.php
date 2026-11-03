<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Support\JsonSnapshotConflict;

// PHP built-in dev server: serve existing static files directly.
if (PHP_SAPI === 'cli-server') {
    // Decode percent-encoded filenames (spaces/UTF-8) before checking static assets.
    $requestPath = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'));
    $publicRoot = realpath(__DIR__);
    $file = realpath(__DIR__ . $requestPath);
    if ($publicRoot !== false && $file !== false
        && is_file($file)
        && str_starts_with($file, $publicRoot . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

try {
    require dirname(__DIR__) . '/app/bootstrap.php';

    try {
        $request = Request::fromGlobals();
    } catch (HttpException $e) {
        Response::fromException($e)->send();
        exit;
    }

    App::create()->handle($request)->send();
} catch (JsonSnapshotConflict $e) {
    Response::json([
        'ok' => false,
        'error' => [
            'code' => 'CONCURRENT_UPDATE',
            'message' => 'Outra gravação ocorreu ao mesmo tempo. Atualize a tela e tente novamente; a gravação não substituiu os dados confirmados.',
        ],
    ], 409)->send();
}
