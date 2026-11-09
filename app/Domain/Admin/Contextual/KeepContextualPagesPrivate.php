<?php

namespace App\Domain\Admin\Contextual;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A public page that rendered admin controls must never be stored by a
 * shared cache and replayed to a visitor. Responses without controls — every
 * visitor response — are left exactly as they were.
 */
class KeepContextualPagesPrivate
{
    public function __construct(private readonly ContextualAdmin $contextual) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->contextual->rendered()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
