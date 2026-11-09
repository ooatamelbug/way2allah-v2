<?php

namespace App\Support\Downloads;

use App\Support\Http\AdminViewer;
use App\Support\Performance\RequestMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Operational telemetry for downloads refused because the global capacity
 * pool (DownloadCapacityGuard) was full: a structured warning in the
 * application log, and one `download_rejections` row (`main` connection) when
 * `downloads.rejection_log` is on. Not the admin audit log — nobody acted;
 * the server was full.
 *
 * Overload protection must not add load, so everything recorded is something
 * the request already had: the caller's DownloadRejectionContext (built from
 * the row it loaded — no HEAD request, no remote open), the request's own
 * headers, and the per-request id (RequestMetrics). The client IP is
 * Laravel's resolved `$request->ip()`, which honours only the trusted-proxy
 * configuration; the raw `X-Forwarded-For` header is kept apart, as a
 * diagnostic, and never replaces it. Cookies, authorization headers and
 * request bodies are never recorded.
 *
 * Never throws. A failure to log or to store the row is reported to the
 * normal error log; the caller's refusal stands either way.
 *
 * No constructor dependencies, on purpose: the guard is built for every
 * protected download, so nothing the telemetry needs may be able to fail
 * that construction. The optional services (the contextual admin, the request
 * id) are looked up only while recording, inside the error handling.
 */
final class DownloadRejectionRecorder
{
    public const TABLE = 'download_rejections';

    public const REASON_CAPACITY_FULL = 'DOWNLOAD_CAPACITY_FULL';

    public function record(Request $request, DownloadRejectionContext $context, int $activeDownloads, int $limit, int $retryAfter): void
    {
        $requestId = $this->requestId();
        $clientIp = (string) $request->ip();

        try {
            Log::warning('Download refused: global capacity full', [
                'event' => self::REASON_CAPACITY_FULL,
                'pool' => DownloadCapacityGuard::POOL,
                'module' => $context->module,
                'resource_type' => $context->resourceType,
                'resource_id' => $context->resourceId,
                'variant' => $context->variant,
                'variant_id' => $context->variantId,
                'active_downloads' => $activeDownloads,
                'download_limit' => $limit,
                'retry_after_seconds' => $retryAfter,
                'file_size' => $context->fileSize,
                'client_ip' => $clientIp,
                'request_id' => $requestId,
            ]);
        } catch (Throwable) {
            // A broken log channel must not break the refusal.
        }

        if (! config('downloads.rejection_log')) {
            return;
        }

        try {
            [$adminId, $isAdmin] = $this->admin();

            DB::connection('main')->table(self::TABLE)->insert([
                'created_at' => time(),
                'reason' => self::REASON_CAPACITY_FULL,
                'pool' => DownloadCapacityGuard::POOL,
                'module' => self::cut($context->module, 32) ?? '',
                'resource_type' => self::cut($context->resourceType, 64) ?? '',
                'resource_id' => max(0, $context->resourceId),
                'variant' => self::cut($context->variant, 32) ?? '',
                'variant_id' => $context->variantId,
                'requested_url' => self::cut($request->fullUrl(), 2048) ?? '',
                'resolved_download_url' => self::cut($context->resolvedDownloadUrl, 2048),
                'file_size' => $context->fileSize,
                'file_size_source' => $context->fileSizeSource,
                'client_ip' => self::cut($clientIp, 45) ?? '',
                'forwarded_for' => self::cut($request->headers->get('X-Forwarded-For'), 255),
                'user_agent' => self::cut($request->userAgent(), 512),
                'referer' => self::cut($request->headers->get('referer'), 1024),
                // A member id only when this request already resolved one — never
                // a vBulletin lookup just for telemetry.
                'user_id' => Auth::guard('vbulletin')->hasUser() ? (int) Auth::guard('vbulletin')->id() : null,
                'admin_id' => $adminId,
                'is_contextual_admin' => $isAdmin,
                'active_downloads' => max(0, $activeDownloads),
                'download_limit' => max(0, $limit),
                // The column is a SMALLINT UNSIGNED; an absurd configured
                // interval must not turn into a failed insert.
                'retry_after_seconds' => min(65535, max(0, $retryAfter)),
                'request_id' => $requestId,
            ]);
        } catch (Throwable $e) {
            try {
                // The exception class and code only: a query exception's message
                // carries the bound values.
                Log::error('Download rejection telemetry could not be stored', [
                    'event' => 'DOWNLOAD_REJECTION_TELEMETRY_FAILED',
                    'module' => $context->module,
                    'resource_id' => $context->resourceId,
                    'request_id' => $requestId,
                    'exception' => $e::class,
                    'code' => $e->getCode(),
                ]);
            } catch (Throwable) {
                // Nothing further to do; the refusal stands.
            }
        }
    }

    /**
     * The signed-in administrator's id, and whether one is viewing (the same
     * check the public pages use).
     *
     * @return array{0: ?int, 1: bool}
     */
    private function admin(): array
    {
        try {
            $viewer = app(AdminViewer::class);

            return [$viewer->adminId(), $viewer->active()];
        } catch (Throwable) {
            return [null, false];
        }
    }

    private function requestId(): ?string
    {
        if (! class_exists(RequestMetrics::class)) {
            return null;
        }

        try {
            return app(RequestMetrics::class)->requestId();
        } catch (Throwable) {
            return null;
        }
    }

    private static function cut(?string $value, int $length): ?string
    {
        return $value === null || $value === '' ? null : mb_substr($value, 0, $length);
    }
}
