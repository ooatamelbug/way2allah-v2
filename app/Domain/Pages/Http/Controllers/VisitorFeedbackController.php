<?php

namespace App\Domain\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Replaces pages/estebian.php (Roadmap task 2.4).
 *
 * Route renamed from "estebian" per pages.md §5's own explicit
 * recommendation ("rename estebian.php during the port to remove the
 * naming confusion") — the legacy filename coincidentally shares a name
 * with the unrelated `estebian` DB table (written by root-level
 * wizard.php, a completely different preacher-volunteer form; Wave 6,
 * admincp/questionnaire/index.php). This page never reads or writes that
 * table — it's a generic visitor-feedback form delegating entirely to an
 * embedded Google Form. Renaming the route is a naming/routing choice, not
 * a behavior change — the page's content and function are unchanged; no
 * ADR required, same as any other controller/route naming decision made
 * throughout this implementation.
 */
class VisitorFeedbackController
{
    public function __invoke(): View
    {
        return view('pages.google-form', [
            'title' => 'إستبيان زوار شبكة الطريق إلى الله',
            'heading' => 'رأيك يهمنا ،، شاركنا برأيك لنحسن من تجربتنا ونضيف المزيد',
            'formUrl' => 'https://docs.google.com/forms/d/e/1FAIpQLScO4qokMiiajzHXR3sC1WTbNEMND_6KA_q3q62DOj7IBH4Jqw/viewform?embedded=true',
            'iframeHeight' => 4100,
        ]);
    }
}
