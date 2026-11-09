<?php

namespace App\Domain\Admin\Contextual;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the validated `return_to` after an operation has run, without
 * touching the operation's controller:
 *
 *   1. only a non-GET request on one of `ContextualReturn::ROUTES`;
 *   2. only when the operation itself answered with a redirect and flashed
 *      `success` in THIS request — validation failures (`errors`), refusals
 *      and no-ops (`error`) keep the operation's own AdminCP redirect, where
 *      the message is shown;
 *   3. only to a target `ContextualReturn::safeTarget()` accepts.
 *
 * Authorization, CSRF and the operation run exactly as before — this sits
 * after them and only swaps the final Location. The success message is
 * re-keyed so the public page shows it once, in the admin-only notice,
 * rather than in a public flash slot.
 *
 * Pushed onto the `web` group. Requests without `return_to` cost one array
 * lookup.
 * It never touches the uploaded-files bag (see handle()).
 */
class HonorContextualReturn
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Read once through input() (JSON / form body, then query). Not has():
        // it walks allFiles(), and after a FormRequest controller has move()d
        // PHP's temporary upload, the base request still holds a stale Symfony
        // UploadedFile whose re-wrap throws FileNotFoundException.
        $returnTo = $request->input(ContextualReturn::PARAM);

        if ($returnTo === null
            || $request->isMethodSafe()
            || ! $response instanceof RedirectResponse
            || ! in_array($request->route()?->getName(), ContextualReturn::ROUTES, true)) {
            return $response;
        }

        $session = $request->session();
        $flashedNow = (array) $session->get('_flash.new', []);

        if (! in_array('success', $flashedNow, true) || in_array('error', $flashedNow, true) || in_array('errors', $flashedNow, true)) {
            return $response;
        }

        $target = ContextualReturn::safeTarget($returnTo, $request);

        if ($target === null) {
            return $response;
        }

        $session->flash(ContextualReturn::FLASH, $session->get('success'));
        $session->forget('success');

        return redirect()->to($target);
    }
}
