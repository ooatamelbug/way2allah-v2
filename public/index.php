<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// cPanel No-SSH Deployment Repackaging — explicitly tells Laravel this
// file's own directory is the real public path, instead of letting it
// default to basePath('public') (Application::publicPath()). Several
// real content-serving code paths call public_path() for actual
// filesystem reads, not just URL generation (ThumbnailService,
// GalleryController, HomeController, Location, Author,
// ContentSidebarWidget — grepped, not guessed) — those would silently
// break if `public/` and the app code ever live in physically separate
// directories (the cPanel no-SSH package's laravel-app/ + public_html/
// split) without this. Harmless here: in the normal co-located layout
// (this file living at <app>/public/index.php, same as every prior
// deployment) `__DIR__` already equals the default basePath('public')
// exactly, so this is a no-op locally and in any single-directory
// deployment — verified via a real request before relying on it.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
