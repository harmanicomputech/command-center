<?php

/*
 * Front controller for DirectAdmin / cPanel hosting.
 *
 * This file lives in the domain's web root (public_html). The application
 * itself lives in a "command-center" folder *outside* the web root, next to
 * public_html (or up to two levels higher), so .env and the code can never
 * be downloaded.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appPath = null;

foreach ([__DIR__.'/../command-center', __DIR__.'/../../command-center', __DIR__.'/../../../command-center'] as $candidate) {
    if (is_file($candidate.'/bootstrap/app.php')) {
        $appPath = realpath($candidate);
        break;
    }
}

if ($appPath === null) {
    http_response_code(500);
    exit('Command Center: the "command-center" folder was not found next to this web root.');
}

// A zip built in CI ships its .env with {{PLACEHOLDERS}}: fill them with
// fresh secrets on the first request, so no secret is ever published. The
// setup key is then ADMIN_PASSWORD in that .env (File Manager).
$env = $appPath.'/.env';
if (is_file($env) && is_writable($env) && str_contains($contents = (string) file_get_contents($env), '{{APP_KEY}}')) {
    $contents = str_replace(
        ['{{APP_KEY}}', '{{ADMIN_PASSWORD}}'],
        ['base64:'.base64_encode(random_bytes(32)), bin2hex(random_bytes(12))],
        $contents
    );
    file_put_contents($env, $contents, LOCK_EX);
}

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
