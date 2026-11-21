<?php

namespace App\Support\Downloads;

use App\Support\Downloads\Telemetry\DownloadTelemetry;
use App\Support\Downloads\Telemetry\DownloadTelemetryContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F04 «حفظ المادة» (owner-approved 2026-11-17: reuse the existing protected proxy for the remaining modules).
 *
 * The Khotab download pattern (KhotabItemController::download / downloadMirror), in one place, for the Variety,
 * Telawah, Fatwa and chat-room-lesson save links. The caller has already loaded the row and checked visibility; this
 * decides HOW the file is delivered, in this order:
 *
 *  1. YouTube / SoundCloud PAGE link (ExternalMediaLink) → 302 to the rebuilt https URL: no slot, no fetch (as Khotab
 *     mirrors; these were redirects before too). GET counts once; HEAD counts nothing.
 *  2. A host the site operates whose attachment rule is configured (SaveAttachmentLink, `download_save.*`) → 302 with
 *     `w2a_save=1`: that host answers as an attachment, so no PHP worker carries the bytes.
 *  3. A link the proxy may NOT fetch (see proxyable()) → the previous behaviour: count, then 302 to the link (or `/`
 *     when empty). Never a local-file read, never a private/loopback address.
 *  4. HEAD → ProxiedDownloadStreamer::head(): attachment headers, no slot, no count, no source contact.
 *  5. GET → DownloadCapacityGuard::tryAcquire() BEFORE anything is counted or opened; none free → 503 + Retry-After
 *     with the rejection recorded; otherwise count, then ProxiedDownloadStreamer::stream(), which validates the source
 *     before committing (no HTML or partial response is ever sent as a file), releases the DB connection before the
 *     copy, applies the connect / idle timeouts and releases the slot on every path.
 *
 * Nothing here changes the pool, the limits, the timeouts or the streamer; these endpoints simply join the ONE global
 * pool (docs/operations/download-capacity.md, "The rule").
 */
final class ProxiedDownloadResponder
{
    public function __construct(
        private readonly DownloadCapacityGuard $guard,
        private readonly ProxiedDownloadStreamer $streamer,
    ) {}

    /**
     * @param  Closure(): mixed  $count  increments the module's own download counter (called at most once, never for HEAD; its return value is ignored)
     * @param  Closure(): DownloadRejectionContext  $rejection  built only when the pool is full
     * @param  bool  $fallbackAway  how a non-proxyable link was redirected before F04: `redirect()->away($link ?: '/')`
     *                              (Fatwa) or `redirect($link)` (the others; a relative path becomes a site URL)
     */
    public function respond(
        Request $request,
        string $link,
        DownloadTelemetryContext $context,
        Closure $count,
        Closure $rejection,
        ?string $connectionToRelease,
        bool $fallbackAway = false,
    ): Response {
        $isHead = $request->isMethod('HEAD');
        $telemetry = app(DownloadTelemetry::class)->transfer($request, $context);

        $media = ExternalMediaLink::classify($link);
        if ($media !== null) {
            if ($isHead) {
                $telemetry->head();
            } else {
                $count();
                $telemetry->redirected($media['host'], $media['kind']);
            }

            return redirect()->away($media['url'], 302, ['Cache-Control' => 'no-store, private']);
        }

        $marked = SaveAttachmentLink::apply($link);
        if ($marked !== $link) {
            if (! $isHead) {
                $count();
            }

            return redirect()->away($marked, 302, ['Cache-Control' => 'no-store, private']);
        }

        if (! self::proxyable($link)) {
            if (! $isHead) {
                $count();
            }

            return $fallbackAway ? redirect()->away($link !== '' ? $link : '/') : redirect($link);
        }

        if ($isHead) {
            return $this->streamer->head($link, $telemetry);
        }

        $slot = $this->guard->tryAcquire();
        if ($slot === null) {
            return $this->guard->refuse($request, $rejection());
        }

        $count();
        $telemetry->accepted();

        return $this->streamer->stream($slot, $link, $connectionToRelease, $telemetry);
    }

    /**
     * May the proxy fetch this link for one of these modules? Remote http(s) only — a stored link here is never read
     * as a local file (the streamer would, for legacy Khotab parity) — with no userinfo, no "@" / "\" tricks, no
     * whitespace or control characters, and a host that is not `localhost` nor a loopback / private /
     * link-local / reserved IP literal. Required for security, not a content allowlist: any public host still streams.
     * (DNS names are not resolved and redirect hops are not re-validated — the same as the existing Khotab proxy.)
     */
    public static function proxyable(string $link): bool
    {
        if ($link === '' || strlen($link) > ExternalMediaLink::MAX_BYTES || preg_match('/[\x00-\x20\x7F]/', $link) === 1) {
            return false;
        }
        $parts = parse_url($link);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true) || ! str_starts_with(strtolower($link), $scheme.'://')) {
            return false;
        }
        $authority = substr($link, strlen($scheme) + 3, strcspn($link, '/?#', strlen($scheme) + 3));
        if (str_contains($authority, '@') || str_contains($link, '\\')) {
            return false;
        }

        $host = rtrim(strtolower(trim($parts['host'], '[]')), '.');
        if ($host === '') {
            return false;
        }
        if (! config('download_save.proxy_block_private_hosts', true)) {
            return true;
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // A bare decimal / hex / octal number is an IP in disguise for most resolvers.
        return preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+)){0,3}$/', $host) !== 1;
    }
}
