<?php

namespace App\Domain\Admin\Contextual;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A public page that rendered admin controls must never be stored by a
 * shared cache and replayed to a visitor. Responses without controls — every
 * visitor response — are left exactly as they were.
 *
 * Admin session indicator (2026-11-23): every HTML page rendered for a
 * signed-in administrator carries their name in the top bar, so it is kept
 * private as well, controls or not. Only HTML: images, downloads and other
 * non-page responses keep their own headers; a session-less response has
 * no administrator and is left alone. `active()` reads only the session
 * key, so visitor requests touch no database here.
 */
class KeepContextualPagesPrivate
{
    public function __construct(private readonly ContextualAdmin $contextual) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->contextual->rendered() || ($request->hasSession() && $this->isHtml($response) && $this->contextual->active())) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html');
    }
}
