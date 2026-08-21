<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ThumbnailGdUnavailableException;
use App\Domain\Content\Services\ThumbnailRejectedException;
use App\Domain\Content\Services\ThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Laravel-owned replacement for legacy's `thumbnails.php` (TimThumb
 * 2.8.14, `legacy-project/thumbnails.php`). Registered at the exact
 * legacy path so every already-emitted `/thumbnails.php?...` URL
 * (HomeController, ContentSidebarWidget, gallery/w2acd views) keeps
 * working unchanged — no caller was modified for this.
 *
 * The deployment-ownership investigation found no operationally-proven
 * mechanism guaranteeing legacy keeps serving this URL in the coexistence
 * deployment (the "remains legacy-served (ADR-0001)" comment this
 * replaces mis-cited ADR-0001, which only says legacy-project/ stays
 * untouched — it says nothing about routing). Laravel now owns it
 * directly; see ThumbnailService for exactly what contract is
 * reproduced and what's deliberately not.
 *
 * Error behaviour is generic 400 for any rejected input (matching
 * TimThumb's own always-400 `serveErrors()` — this legacy install has no
 * `timthumb-config.php` overriding NOT_FOUND_IMAGE/ERROR_IMAGE, so every
 * TimThumb error path already produces a plain 400 today) and a distinct
 * 503 when ext-gd isn't loaded, since that's a runtime/environment
 * condition, not a bad request. Neither path echoes the resolved path,
 * the query string, or any exception detail into the response body.
 */
class ThumbnailController
{
    /** @var list<string> */
    private const INT_PARAMS = ['w', 'h', 'q', 'zc'];

    public function show(Request $request, ThumbnailService $thumbnails): Response
    {
        $src = $request->query('src');
        if (! is_string($src) || $src === '') {
            abort(400);
        }

        foreach (self::INT_PARAMS as $param) {
            $value = $request->query($param);
            if ($value !== null && $value !== '' && ! ctype_digit((string) $value)) {
                abort(400);
            }
        }

        $w = $request->query('w') !== null && $request->query('w') !== '' ? (int) $request->query('w') : null;
        $h = $request->query('h') !== null && $request->query('h') !== '' ? (int) $request->query('h') : null;
        $zc = $request->query('zc') !== null && $request->query('zc') !== '' ? (int) $request->query('zc') : 1;
        $q = $request->query('q') !== null && $request->query('q') !== '' ? (int) $request->query('q') : 90;

        try {
            $result = $thumbnails->render($src, $w, $h, $zc, $q);
        } catch (ThumbnailGdUnavailableException) {
            abort(503);
        } catch (ThumbnailRejectedException) {
            abort(400);
        }

        return response($result['binary'], 200)
            ->header('Content-Type', $result['mime'])
            // TimThumb's own BROWSER_CACHE_MAX_AGE default (thumbnails.php:46) — 10 days.
            ->header('Cache-Control', 'public, max-age=864000');
    }
}
