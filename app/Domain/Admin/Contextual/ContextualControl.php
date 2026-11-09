<?php

namespace App\Domain\Admin\Contextual;

/**
 * One contextual administration control shown on a public page (OLD Admin
 * Migration, contextual surface). It is only a pointer to an EXISTING
 * Laravel admin route: no business logic and no permission of its own — who
 * may see it is decided by the target route's own `admin.role` /
 * `admin.permission` middleware (`AdminRouteAccess`), so the control and the
 * server-side check can never disagree.
 *
 * GET controls render as links. Every other method renders as a form with
 * `@csrf` and, for PUT/DELETE, method spoofing — a mutation is never a GET.
 */
final readonly class ContextualControl
{
    /**
     * @param  array<string, mixed>  $parameters  route parameters
     * @param  array<string, scalar>  $fields  hidden form fields (non-GET only)
     * @param  string|null  $returnTo  where a successful operation should land;
     *                                 null = the page the control is shown on
     * @param  bool  $returns  false for plain navigation (a status page), which
     *                         carries no return_to at all
     * @param  string|null  $gateRoute  a second route the admin must also pass —
     *                                  for a link that opens a shared page but
     *                                  names one operation on it (e.g. "add")
     */
    public function __construct(
        public string $label,
        public string $icon,
        public string $routeName,
        public array $parameters = [],
        public string $method = 'GET',
        public array $fields = [],
        public ?string $confirm = null,
        public ?string $returnTo = null,
        public bool $returns = true,
        public ?string $gateRoute = null,
    ) {}

    /** @param array<string, mixed> $parameters */
    public static function link(string $label, string $icon, string $routeName, array $parameters = [], bool $returns = true, ?string $gateRoute = null): self
    {
        return new self($label, $icon, $routeName, $parameters, returns: $returns, gateRoute: $gateRoute);
    }

    /** @return list<string> every route whose authorization the admin must pass */
    public function authorizingRoutes(): array
    {
        return $this->gateRoute === null ? [$this->routeName] : [$this->routeName, $this->gateRoute];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, scalar>  $fields
     */
    public static function action(string $label, string $icon, string $routeName, array $parameters, string $method, array $fields = [], ?string $confirm = null, ?string $returnTo = null): self
    {
        return new self($label, $icon, $routeName, $parameters, strtoupper($method), $fields, $confirm, $returnTo);
    }

    public function isLink(): bool
    {
        return $this->method === 'GET';
    }

    /** The method the HTML form must spoof, or null for a plain POST. */
    public function spoofedMethod(): ?string
    {
        return in_array($this->method, ['GET', 'POST'], true) ? null : $this->method;
    }

    /** The link (GET) or form action (otherwise); a link carries return_to in its query. */
    public function url(?string $returnTo): string
    {
        $url = route($this->routeName, $this->parameters);

        if (! $this->isLink() || $returnTo === null) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([ContextualReturn::PARAM => $returnTo]);
    }
}
