<?php

namespace App\Domain\Admin\Contextual;

use Illuminate\Http\Request;

/**
 * The approved same-site `return_to` mechanism (owner decision 3,
 * 2026-10-15): an operation entered from a public page lands back on that
 * page when it succeeds.
 *
 * `safeTarget()` is the only gate. It accepts a same-site *public* path and
 * nothing else:
 *
 *   - a relative path starting with exactly one `/`, or an absolute http(s)
 *     URL whose host and port are this request's own (reduced to its path);
 *   - no backslash, no control character or whitespace, no user-info, at
 *     most 2048 bytes;
 *   - never an `/admincp` or `/deploy` path — the point is to return to the
 *     public page, and refusing these keeps the mechanism from being used to
 *     chain into other admin actions.
 *
 * Anything else yields null, and callers then keep the operation's own
 * AdminCP redirect. There is no open redirect: the value that reaches
 * `redirect()->to()` is always a path on this host.
 */
final class ContextualReturn
{
    public const PARAM = 'return_to';

    /** Re-keyed success message, rendered on the public page for the admin only. */
    public const FLASH = 'contextual_admin_success';

    /**
     * The mutation routes a contextual control can lead to, directly or via
     * the AdminCP form it opens. Only these honour return_to; every other
     * route ignores the parameter entirely.
     */
    public const ROUTES = [
        'admin.mirrors.khotab.store',
        'admin.mirrors.khotab.update',
        'admin.mirrors.khotab.destroy',
        'admin.mirrors.khotab.pick',
        'admin.mirrors.khotab.move',
        'admin.mirrors.khotab.clipboard.clear',
        'admin.mirrors.anasheed.store',
        'admin.mirrors.anasheed.update',
        'admin.mirrors.anasheed.destroy',
        'admin.mirrors.telawah.update',
        'admin.mirrors.telawah.destroy',
        'admin.categories.khotab.item.add',
        'admin.categories.khotab.item.remove',
        'admin.categories.khotab.group.add-all',
        'admin.categories.khotab.group.remove-all',
        'admin.categories.khotab.series.add-all',
        'admin.categories.khotab.series.remove-all',
        'admin.categories.anasheed.item.add',
        'admin.categories.anasheed.item.remove',
        'admin.advfiles.request',
        'admin.advfiles.update',
        'admin.advfiles.unpublish',
        'admin.polls.store',
        'admin.polls.update',
        'admin.polls.comments.destroy',
        'admin.cds.update',
        'admin.cds.destroy',
        'admin.mobile.materials.update',
        'admin.mobile.materials.destroy',
    ];

    public static function safeTarget(mixed $value, Request $request): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2048) {
            return null;
        }

        // Backslashes are normalised to "/" by browsers ("/\evil" → "//evil");
        // control characters and whitespace have no place in a path.
        if (str_contains($value, '\\') || preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            return null;
        }

        if (str_starts_with($value, '/')) {
            if (str_starts_with($value, '//')) {
                return null;
            }
            $parts = parse_url('http://same.invalid'.$value);
            if ($parts === false || ($parts['host'] ?? null) !== 'same.invalid' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                return null;
            }
        } else {
            $parts = parse_url($value);
            $scheme = strtolower(is_array($parts) ? ($parts['scheme'] ?? '') : '');
            if ($parts === false
                || ! in_array($scheme, ['http', 'https'], true)
                || strcasecmp($parts['host'] ?? '', $request->getHost()) !== 0
                || (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)) !== (int) $request->getPort()
                || isset($parts['user']) || isset($parts['pass'])) {
                return null;
            }
        }

        $path = '/'.ltrim($parts['path'] ?? '/', '/');

        if (preg_match('#^/(admincp|deploy)(/|$)#i', $path) === 1) {
            return null;
        }

        return $path.(isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '');
    }

    /** The current public page, as a return target for the controls rendered on it. */
    public static function currentPage(Request $request): ?string
    {
        return self::safeTarget($request->getRequestUri(), $request);
    }
}
