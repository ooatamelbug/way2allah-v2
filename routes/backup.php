<?php

use App\Domain\Content\Http\Controllers\BackupApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Backup API Route (Roadmap task 6.8)
|--------------------------------------------------------------------------
|
| backup.php (site root) is a machine-to-machine content-backup/booking
| API, not a browser-facing page. No .htaccess rule exists for it at all
| (Task 6.8 investigation §4) — it has always been called directly at
| this exact raw path by an external client, so it is registered here at
| that same literal path rather than under any pretty-URL scheme.
|
| CSRF is explicitly exempted for this exact path in bootstrap/app.php —
| the external client cannot supply a Laravel CSRF token.
|
*/

Route::post('/backup.php', BackupApiController::class)->name('backup.api');
