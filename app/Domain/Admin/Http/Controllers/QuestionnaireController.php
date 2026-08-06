<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\QuestionnaireResponse;
use Illuminate\Contracts\View\View;

/**
 * Replaces `admincp/questionnaire/index.php` — Roadmap task 5.8. Confirmed
 * read-only in the legacy source (`menu.php` defines a `deletequest`
 * authorization key, but no delete handling exists anywhere in
 * `index.php` — same "permission key defined, no corresponding code"
 * shape already confirmed elsewhere in this module; not built here
 * either, matching what's actually real rather than the permission
 * taxonomy's full nominal surface).
 */
class QuestionnaireController
{
    public function index(): View
    {
        $responses = QuestionnaireResponse::orderBy('id')->get(['id', 'username', 'mobile', 'email', 'facebook']);

        return view('admin.questionnaire.index', compact('responses'));
    }

    public function show(QuestionnaireResponse $response): View
    {
        return view('admin.questionnaire.show', compact('response'));
    }
}
