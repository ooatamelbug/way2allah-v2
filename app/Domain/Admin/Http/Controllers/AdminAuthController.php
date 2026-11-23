<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminDashboardModules;
use App\Domain\Admin\Support\AdminLoginThrottle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * `/admincp/` Login + Dashboard Completion — owner decision (2026-08-22)
 * superseding Wave 5's explicit "no dashboard/logout nav... outside this
 * wave's 10 tasks" exclusion (`resources/views/layouts/admin.blade.php`'s
 * former comment, now updated). Reuses `AdminGuard`/`AdminUser` exactly as
 * built (ADR-0011, Roadmap task 0.4) — no new password verification, no
 * second auth system. `admincp/index.php`'s own real CONTRACT is
 * reproduced faithfully (one URL, anonymous → login form, authenticated →
 * dashboard); its insecure MECHANISM (7 cookies incl. a JSON'd full author
 * row, no httponly/secure/samesite, MD5/SHA1/plaintext fallback, no CSRF)
 * is deliberately not reproduced — Laravel's already-hardened session/
 * CSRF/AdminGuard architecture is authoritative, per explicit instruction.
 *
 * `home.php`'s own content (Metronic demo widgets/notification counts, 0
 * DB queries, confirmed dead chrome — admincp.md §5) is not ported; the
 * dashboard's only real content is `AdminDashboardModules`'s
 * permission-filtered list of already-migrated feature areas.
 */
class AdminAuthController
{
    /**
     * `GET /admincp/` — the single, preserved legacy entry-point URL.
     * Branches exactly like `admincp/index.php`'s own `login()` function:
     * no session → login form; authenticated → dashboard. Not two routes
     * behind a redirect — one route, matching the real legacy contract
     * (and avoiding any anonymous/authenticated redirect loop).
     */
    public function entry(AdminDashboardModules $modules): View
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin instanceof AdminUser) {
            return view('admin.auth.login');
        }

        // The layout's own sidebar gets its (identically-computed) module
        // list from AdminServiceProvider's `layouts.admin` view composer —
        // that data isn't usable here too: `@extends` captures a child
        // view's `@section('content')` before the parent (and its
        // composer) ever runs, so `$modules` must be passed explicitly for
        // the dashboard's own body content to see it (found by testing,
        // not assumed).
        return view('admin.dashboard', [
            'admin' => $admin,
            'modules' => $modules->visibleFor($admin),
        ]);
    }

    /**
     * `POST /admincp/login`. Validates then delegates entirely to
     * `AdminGuard::attempt()` (`aid`/`password` — the guard's own existing
     * credential-array contract, unchanged). `AdminGuard::login()` already
     * calls `$request->session()->regenerate()` internally (read directly
     * before writing this — not duplicated here, which would be a
     * redundant, not merely harmless, second regenerate in the same
     * request). Does not reveal whether a given `aid` exists — same
     * generic error for "unknown admin" and "wrong password".
     *
     * N2 (owner-approved 2026-11-08): brute-force throttling around the
     * unchanged attempt — see AdminLoginThrottle. After validation, an
     * exceeded counter answers 429 + Retry-After with the login page and a
     * generic message, WITHOUT calling the guard (a correct password is not
     * even checked); a failed attempt records one failure on both counters; a
     * success clears only the username + IP counter.
     */
    public function login(Request $request, AdminLoginThrottle $throttle): RedirectResponse|Response
    {
        $credentials = $request->validate([
            'aid' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $clientIp = (string) $request->ip();
        $username = (string) $credentials['aid'];

        if ($throttle->tooManyAttempts($clientIp, $username)) {
            $throttle->logThrottled($clientIp, $username);
            $request->session()->flashInput(['aid' => $username]);

            return response()
                ->view('admin.auth.login', [
                    'errors' => (new ViewErrorBag)->put('default', new MessageBag(['aid' => AdminLoginThrottle::MESSAGE])),
                ], 429)
                ->header('Retry-After', (string) $throttle->availableIn($clientIp, $username));
        }

        if (! Auth::guard('admin')->attempt($credentials)) {
            $throttle->recordFailure($clientIp, $username);

            return back()
                ->withInput($request->only('aid'))
                ->withErrors(['aid' => 'بيانات الدخول غير صحيحة.']);
        }

        $throttle->clearUsername($clientIp, $username);

        return redirect()->route('admin.entry');
    }

    /**
     * `POST /admincp/logout` — POST only (never GET, per explicit
     * instruction — a GET logout is a real CSRF/prefetch hazard).
     * `AdminGuard::logout()` already forgets the session key and
     * regenerates the session ID; this additionally regenerates the CSRF
     * token specifically (`regenerateToken()` is a distinct concern from
     * the guard's own session-ID regeneration, not a duplicate of it —
     * confirmed by reading `Illuminate\Session\Store`'s two separate
     * methods before adding this).
     *
     * Admin session indicator (2026-11-23): an optional `return_to` sends the
     * administrator back to the public page they logged out from. It is
     * validated by `ContextualReturn::safeTarget()` (same host only, never
     * /admincp or /deploy, no scheme / credential / port tricks); anything
     * else falls back to the AdminCP login page exactly as before. Only the
     * admin key leaves the session — forum (vBulletin) cookies are untouched.
     */
    public function logout(Request $request): RedirectResponse
    {
        $returnTo = ContextualReturn::safeTarget($request->input(ContextualReturn::PARAM), $request);

        Auth::guard('admin')->logout();

        $request->session()->regenerateToken();

        return $returnTo !== null ? redirect()->to($returnTo) : redirect()->route('admin.entry');
    }
}
