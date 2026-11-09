<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Canonical split-layout front controller (P0 split layout, 2026-11-09). Ships as `newapp/index.php` in every
// deployment package that contains a front controller — never `public/index.php`, which is the co-located
// local variant (`__DIR__.'/../vendor'`). `php artisan deploy:verify-package` enforces byte identity with this
// file. The web root (`/home/USER/newapp/`) and the application (`/home/USER/laravel-app/`) are siblings.

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../laravel-app/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../laravel-app/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../laravel-app/bootstrap/app.php';

// This file's own directory is the real public path for HTTP requests (Application::publicPath() would
// otherwise default to laravel-app/public, which does not exist in the split layout). Artisan, queue workers
// and `config:cache` never run this file — which is why the five media roots must be set explicitly in .env
// (KHOTAB_MEDIA_ROOT, ANASHEED_MEDIA_ROOT, W2ACD_MEDIA_ROOT, W2ACD_IMAGE_ROOT, AUTOCOMPLETE_ROOT).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
