<?php

/**
 * cPanel No-SSH Deployment Repackaging — config for the one-time,
 * browser-driven deployment installer
 * (`App\Http\Controllers\DeploymentInstallerController`). Both values
 * come from `.env` only — `installer_enabled` defaults to `false` so a
 * fresh checkout/deploy never exposes the installer unless the owner
 * deliberately opts in; `installer_token` has no default, an empty
 * string can never authenticate (checked explicitly in the controller).
 */
return [
    'installer_enabled' => env('DEPLOY_INSTALLER_ENABLED', false),
    'installer_token' => env('DEPLOY_INSTALLER_TOKEN', ''),
];
