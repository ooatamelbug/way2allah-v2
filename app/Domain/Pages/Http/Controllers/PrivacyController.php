<?php

namespace App\Domain\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Replaces pages/privacy.php (Roadmap task 2.1). Legacy source: pure
 * static content — no DB queries, no user input, no business logic
 * beyond rendering the site's shared header/breadcrumb/footer chrome
 * around a fixed block of Arabic text. Content reproduced verbatim in
 * resources/views/pages/privacy.blade.php.
 */
class PrivacyController
{
    public function __invoke(): View
    {
        return view('pages.privacy');
    }
}
