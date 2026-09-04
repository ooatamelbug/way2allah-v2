<?php

namespace App\Support\Performance;

/**
 * Enhancement Batch E-01 — request-duration classification.
 *
 * A pure value object built from `config('performance')` (never `env()`,
 * so it behaves identically with and without `config:cache`). Kept
 * separate from the middleware so the bucket boundaries can be tested
 * directly with fixed numbers rather than by timing a real request.
 */
readonly class PerformanceThresholds
{
    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_SLOW = 'slow';

    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        public int $warningMs,
        public int $slowMs,
        public int $criticalMs,
    ) {}

    /**
     * @param  array<string, mixed>  $config  the `performance` config array
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            warningMs: (int) ($config['warning_ms'] ?? 400),
            slowMs: (int) ($config['slow_ms'] ?? 1000),
            criticalMs: (int) ($config['critical_ms'] ?? 2500),
        );
    }

    /**
     * The highest bucket this duration reaches, or null when the request
     * was fast enough that nothing should be logged at all.
     *
     * Buckets are evaluated highest-first so a misconfigured `.env`
     * (e.g. slow_ms set below warning_ms) still yields the most severe
     * applicable label rather than a silently wrong one.
     */
    public function severityFor(float $durationMs): ?string
    {
        return match (true) {
            $durationMs >= $this->criticalMs => self::SEVERITY_CRITICAL,
            $durationMs >= $this->slowMs => self::SEVERITY_SLOW,
            $durationMs >= $this->warningMs => self::SEVERITY_WARNING,
            default => null,
        };
    }

    /** The PSR-3 level each bucket is written at. */
    public function logLevelFor(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 'error',
            self::SEVERITY_SLOW => 'warning',
            default => 'notice',
        };
    }
}
