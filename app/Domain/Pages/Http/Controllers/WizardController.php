<?php

namespace App\Domain\Pages\Http\Controllers;

use App\Domain\Admin\Models\QuestionnaireResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Replaces `wizard.php` (root, no `.htaccess` rule — raw-path-only, same
 * profile as `khotab/dump.php`) — Wave C ("Public Locations & Da'wah
 * Registration Surfaces"). The public 4-step "استبيان الدعاة" form feeding
 * the `estebian` table, whose admin-review half already existed
 * (`QuestionnaireResponse`/`QuestionnaireController`, Wave 5 task 5.8) —
 * that model's own docblock already named `wizard.php` as the real writer,
 * cross-referenced here, not re-derived.
 *
 * Confirmed quirks reproduced exactly, not "cleaned up":
 * - The step-1 phone-number field is literally named `password`
 *   (`name="password"`, legacy `wizard.php:164`) and is written to the
 *   `mobile` column — not a typo in this port, a faithful reproduction of
 *   the legacy field name itself.
 * - `rpassword` (the phone-confirmation field, collected in step 1's
 *   markup) is never referenced in legacy's own `INSERT` statement at all
 *   — collected client-side only, silently discarded server-side. Not
 *   read here either.
 * - No server-side validation of any kind exists in legacy (only the
 *   client-side `jquery.validate`/`form-wizard.js` plugin, not
 *   reproduced) — none added here either.
 * - Legacy's own POST branch (`wizard.php:2-8`) inserts, then falls
 *   through to render the exact same empty-form HTML again in the same
 *   response — no redirect, no success banner. Reproduced exactly:
 *   `store()` returns the identical view `show()` does, not a redirect.
 *
 * This page uses its own standalone HTML shell (`assets/admin/layout4/...`
 * — the ADMIN panel's own theme, not the public site's), confirmed
 * deliberate (a complete, distinct `<head>`/asset list), not an accident —
 * `resources/views/pages/wizard.blade.php` does NOT `@extends('layouts.app')`,
 * matching that choice.
 */
class WizardController
{
    public function show(): View
    {
        return view('pages.wizard');
    }

    public function store(Request $request): View
    {
        QuestionnaireResponse::create([
            'username' => $request->input('username'),
            'mobile' => $request->input('password'),
            'facebook' => $request->input('facebook'),
            'email' => $request->input('email'),
            'remarks1' => $request->input('remarks1'),
            'remarks2' => $request->input('remarks2'),
            'remarks3' => $request->input('remarks3'),
            'remarks4' => $request->input('remarks4'),
            'remarks5' => $request->input('remarks5'),
            'remarks6' => $request->input('remarks6'),
            'remarks7' => $request->input('remarks7'),
            'remarks8' => $request->input('remarks8'),
            'remarks9' => $request->input('remarks9'),
            'remarks10' => $request->input('remarks10'),
            'remarks11' => $request->input('remarks11'),
        ]);

        return view('pages.wizard');
    }
}
