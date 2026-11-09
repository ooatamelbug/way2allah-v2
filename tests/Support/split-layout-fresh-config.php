<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ConfigCacheCommand;

/*
 * Test helper for SplitLayoutMediaRootsTest — runs in its own PHP process. Read-only: it never writes a
 * cache file. It reproduces exactly what `php artisan config:cache` does when invoked from a split-layout
 * HTTP request (the deployment installer): the outer application has `usePublicPath(<web root>)` applied,
 * then ConfigCacheCommand::getFreshConfiguration() builds a *fresh* application and returns the
 * configuration it would write to bootstrap/cache/config.php.
 *
 * Usage: php split-layout-fresh-config.php <laravel base path> <split web root>
 */

[, $base, $webRoot] = $argv;

require $base.'/vendor/autoload.php';

$app = require $base.'/bootstrap/app.php';
$app->usePublicPath($webRoot);

$command = new ConfigCacheCommand(new Filesystem);
$command->setLaravel($app);
$fresh = (new ReflectionMethod($command, 'getFreshConfiguration'))->invoke($command);

$roots = [];
foreach (['admin_khotab.media_root', 'admin_anasheed.media_root', 'admin_cds.media_root', 'admin_cds.image_root', 'media.autocomplete_root'] as $key) {
    [$file, $name] = explode('.', $key, 2);
    $roots[$key] = $fresh[$file][$name] ?? null;
}

echo json_encode(['roots' => $roots, 'http_public_path' => $app->publicPath()], JSON_UNESCAPED_SLASHES);
