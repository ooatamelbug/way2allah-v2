<?php

namespace App\Support\Performance;

use Illuminate\Support\Str;

/**
 * Enhancement Batch E-01 — per-request performance metric accumulator.
 *
 * Deliberately a container-`scoped()` instance, not static state: a
 * `scoped` binding is rebuilt for every request AND explicitly reset by
 * Octane/queue workers between jobs, so long-running workers can never
 * leak one request's counters into the next. `reset()` is also called
 * explicitly at the start of every request by
 * `MonitorsRequestPerformance`, so correctness does not depend on the
 * container's lifecycle alone.
 *
 * Holds no clock of its own — every timestamp is passed in by the caller.
 * That keeps the whole class deterministic and testable with fixed
 * values, with no `sleep()` anywhere in its tests.
 */
class RequestMetrics
{
    private string $requestId;

    private ?float $startedAt = null;

    private int $queryCount = 0;

    private float $totalQueryMs = 0.0;

    private float $slowestQueryMs = 0.0;

    public function __construct()
    {
        $this->requestId = (string) Str::uuid();
    }

    /**
     * A new correlation id is minted per request (never taken from an
     * inbound header in this batch — an attacker-supplied value would
     * need validating/sanitising before it could be trusted in a log,
     * and our own UUID is strictly safer).
     */
    public function requestId(): string
    {
        return $this->requestId;
    }

    public function start(float $timestamp): void
    {
        $this->startedAt = $timestamp;
    }

    public function startedAt(): ?float
    {
        return $this->startedAt;
    }

    /** Duration in milliseconds, or null if the request was never started. */
    public function durationMs(float $now): ?float
    {
        if ($this->startedAt === null) {
            return null;
        }

        return round(($now - $this->startedAt) * 1000, 2);
    }

    /** Records one executed query. `$timeMs` is Laravel's own `QueryExecuted::$time`. */
    public function recordQuery(float $timeMs): void
    {
        $this->queryCount++;
        $this->totalQueryMs = round($this->totalQueryMs + $timeMs, 2);
        $this->slowestQueryMs = max($this->slowestQueryMs, $timeMs);
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function totalQueryMs(): float
    {
        return $this->totalQueryMs;
    }

    public function slowestQueryMs(): float
    {
        return $this->slowestQueryMs;
    }

    /**
     * Clears every per-request counter and mints a fresh correlation id.
     * Called at the start of each request so a reused container instance
     * (Octane, a queue worker, a test issuing several requests) can never
     * report another request's numbers.
     */
    public function reset(): void
    {
        $this->requestId = (string) Str::uuid();
        $this->startedAt = null;
        $this->queryCount = 0;
        $this->totalQueryMs = 0.0;
        $this->slowestQueryMs = 0.0;
    }
}
