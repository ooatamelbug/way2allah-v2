<?php

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;

/*
 * Router script for PHP's built-in web server (`php -S … tests/Support/Telemetry/http-front.php`), used ONLY by
 * DownloadHttpIntegrationTest. It boots the real application through the real HTTP kernel, exactly like
 * public/index.php, after applying a test-only environment and configuration read from the JSON file named by
 * W2A_IT_CONFIG (an isolated SQLite file for `main`, a scratch spool and slot directory). It never reads production
 * settings: every database connection the request can use is pointed at the scratch SQLite file.
 */

$cfg = json_decode((string) file_get_contents((string) getenv('W2A_IT_CONFIG')), true, 512, JSON_THROW_ON_ERROR);

foreach ($cfg['env'] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $cfg['base'].'/vendor/autoload.php';

$app = require $cfg['base'].'/bootstrap/app.php';
$app->usePublicPath($cfg['base'].'/public');
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($cfg) {
    foreach ($cfg['config'] as $key => $value) {
        $app['config']->set($key, $value);
    }
});

$app->handleRequest(Request::capture());
