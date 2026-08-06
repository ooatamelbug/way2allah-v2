<?php

namespace App\Domain\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Replaces pages/tatw3-w2a-team.php (Roadmap task 2.4). No naming-confusion
 * finding for this one (unlike estebian.php) — route name chosen to match
 * its actual purpose ("نموذج التطوع بفرق عمل" — team volunteering form).
 */
class VolunteerController
{
    public function __invoke(): View
    {
        return view('pages.google-form', [
            'title' => 'نموذج التطوع بفرق عمل شبكة الطريق إلى الله',
            'heading' => 'شارك معنا وكن واحدا من فرق العمل بشبكة الطريق إلى الله',
            'formUrl' => 'https://docs.google.com/forms/d/e/1FAIpQLSey90EU6LJY9pTm6qsRSgDOVZPeSNmgz8vrh4jwRVdTnNRGIQ/viewform?embedded=true',
            'iframeHeight' => 3000,
        ]);
    }
}
