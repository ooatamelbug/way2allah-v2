<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes (Blueprint v1.0 §7, Roadmap task 1.6)
|--------------------------------------------------------------------------
|
| Skeleton — no real routes until Wave 5 (admincp/survey/ first). The
| pattern every admin route group follows:
|
|   Route::middleware(['admin.role'])->group(function () {
|       // any authenticated admin
|   });
|
|   Route::middleware(['admin.role:super-admin'])->group(function () {
|       // only admins holding the 'super-admin' role (guard: admin)
|   });
|
*/

Route::middleware(['admin.role'])->prefix('admincp')->group(function () {
    //
});
