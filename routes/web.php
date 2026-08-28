<?php

use App\Domain\Content\Http\Controllers\HomeController;
use App\Http\Controllers\DeploymentInstallerController;
use Illuminate\Support\Facades\Route;

/** G-02 (Migration Gap Register) — replaces `index.php`/`new_content.php`'s stock `welcome` placeholder. */
Route::get('/', [HomeController::class, 'index'])->name('home');

/**
 * cPanel No-SSH Deployment Repackaging — one-time browser-driven deployment
 * installer. Gated by `DEPLOY_INSTALLER_ENABLED` + `DEPLOY_INSTALLER_TOKEN`
 * + a post-success lock file (`DeploymentInstallerController::abortIfUnavailable()`)
 * — see that controller's own docblock for the full defense-in-depth
 * reasoning. Not part of the application's real feature surface.
 */
Route::get('/deploy/install', [DeploymentInstallerController::class, 'show'])->name('deploy.install.show');
Route::post('/deploy/install', [DeploymentInstallerController::class, 'install'])->name('deploy.install');
