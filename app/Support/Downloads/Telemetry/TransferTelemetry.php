<?php

namespace App\Support\Downloads\Telemetry;

use Illuminate\Http\Request;

/**
 * One proxied download's telemetry (Phase 3A) — at most ONE terminal outcome per transfer.
 *
 * Event sequence for a GET that passed the capacity check:
 *   download_accepted → (source_open_failed | source_rejected)                      [terminal, no transfer]
 *                     → transfer_started → transfer_finished(st = …)                [terminal]
 * For a HEAD: head_answered (one event, head=true; nothing streamed, never a download attempt)
 *
 * `transfer_finished.st`: completed | incomplete (source ended before its declared size — the visitor got a
 * truncated body) | stalled (source idle-read timeout) | read_failed | client_aborted | interrupted (the process ended
 * mid-stream for another reason). `completed` is the ONLY outcome that means the whole file was sent.
 *
 * If PHP aborts the request mid-stream (a client disconnect with ignore_user_abort off), no `finally` block runs, so
 * a shutdown function emits the terminal event instead (`client_aborted` when the connection was aborted, otherwise
 * `interrupted`), marked `k=shutdown_fallback`. Its byte count is the last chunk counted before the abort. This object never holds the download slot or the source handle, so telemetry cannot keep either alive.
 */
final class TransferTelemetry
{
    public int $bytes = 0;

    private bool $terminal = false;

    private bool $shutdownRegistered = false;

    private ?float $startedAt = null;

    public function __construct(
        private readonly DownloadTelemetry $recorder,
        private readonly Request $request,
        private readonly DownloadTelemetryContext $context,
    ) {}

    /** GET only — a HEAD is recorded once, as `head_answered`, so it is never counted as a download attempt. */
    public function accepted(): void
    {
        if ($this->request->isMethod('HEAD')) {
            return;
        }
        $this->emit('download_accepted');
    }

    public function head(): void
    {
        $this->terminal('head_answered', ['st' => 'head']);
    }

    /** @param 'source_open_failed'|'source_rejected' $event */
    public function sourceFailed(string $event, ?string $reason, ?int $status, ?string $host): void
    {
        $this->terminal($event, ['k' => $reason, 'ss' => $status, 'sh' => $host]);
    }

    /** An external-media page link answered by a redirect (GET): terminal, never a transfer. */
    public function redirected(string $host, string $kind): void
    {
        $this->terminal('redirect_issued', ['st' => 'redirect', 'sh' => $host, 'k' => $kind]);
    }

    public function started(?string $host, ?int $status, ?int $declaredLength, ?int $openMs): void
    {
        $this->startedAt = microtime(true);
        $this->emit('transfer_started', ['sh' => $host, 'ss' => $status, 'dl' => $declaredLength, 'ttfb' => $openMs]);

        if (! $this->shutdownRegistered && $this->recorder->enabled()) {
            $this->shutdownRegistered = true;
            register_shutdown_function(function (): void {
                $this->finished(connection_aborted() === 1 ? 'client_aborted' : 'interrupted', 'shutdown_fallback');
            });
        }
    }

    /** @param string|null $via `shutdown_fallback` when emitted by the shutdown function (no `finally` ran) */
    public function finished(string $outcome, ?string $via = null): void
    {
        $duration = $this->startedAt === null ? null : (int) round((microtime(true) - $this->startedAt) * 1000);
        $this->terminal('transfer_finished', ['st' => $outcome, 'b' => $this->bytes, 'd' => $duration, 'k' => $via]);
    }

    public function isTerminal(): bool
    {
        return $this->terminal;
    }

    /** @param array<string, bool|int|float|string|null> $fields */
    private function terminal(string $event, array $fields = []): void
    {
        if ($this->terminal) {
            return;   // exactly one terminal outcome
        }
        $this->terminal = true;
        $this->emit($event, $fields);
    }

    /** @param array<string, bool|int|float|string|null> $fields */
    private function emit(string $event, array $fields = []): void
    {
        $this->recorder->emit($this->request, $event, $this->context, $fields);
    }
}
