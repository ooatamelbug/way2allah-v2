<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminDashboardModules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'aid' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials)) {
            return back()
                ->withInput($request->only('aid'))
                ->withErrors(['aid' => 'بيانات الدخول غير صحيحة.']);
        }

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
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->regenerateToken();

        return redirect()->route('admin.entry');
    }
}
