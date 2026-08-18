<?php

use App\Domain\Content\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/** G-02 (Migration Gap Register) — replaces `index.php`/`new_content.php`'s stock `welcome` placeholder. */
Route::get('/', [HomeController::class, 'index'])->name('home');
