<?php

namespace App\Domain\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Replaces help/about.php (Roadmap task 2.1). Same shape as
 * PrivacyController — pure static content, no DB/business logic. Content
 * reproduced verbatim (including its inline MS-Word-pasted styling) in
 * resources/views/pages/about.blade.php.
 */
class AboutController
{
    public function __invoke(): View
    {
        return view('pages.about');
    }
}
