<?php

namespace App\Domain\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Replaces pages/mo7fzat-quran.php (Roadmap task 2.4).
 *
 * IF-008 (see docs/implementation-findings.md): this form's iframe URL has
 * no `?embedded=true` query parameter, unlike its two structural siblings
 * (VisitorFeedbackController, VolunteerController). Reproduced exactly as
 * found — not "fixed" by adding the parameter — per rule 1 (legacy is the
 * source of truth absent a confirmed bug or an approved ADR-0010
 * redesign). Whether this is a copy-paste omission or intentional is not
 * determinable from source alone.
 */
class QuranMemorizationApplicationController
{
    public function __invoke(): View
    {
        return view('pages.google-form', [
            'title' => 'إستمارة التقدم لـ(محفظات القرآن الكريم)',
            'heading' => 'انضمي الان كمعلمة إلى مشروع تحفيظ القران الكريم بشبكة الطريق إلى الله',
            'formUrl' => 'https://docs.google.com/forms/d/e/1FAIpQLSdnZsVVBeAH6wRpWwo7B_Hv45b2ErkBBVqrr2bEtBTENy5d3w/viewform',
            'iframeHeight' => 1000,
        ]);
    }
}
