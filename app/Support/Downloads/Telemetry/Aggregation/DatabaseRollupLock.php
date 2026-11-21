<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Defence in depth for the rollup (Phase 2.2): a MySQL/MariaDB advisory lock, in ADDITION to the filesystem
 * `rollup.lock`. The filesystem lock serializes workers that share a spool root; this one serializes every worker
 * that shares the DATABASE — a second spool root, a second host, or anything else that bypasses the file lock.
 *
 * - Taken with `GET_LOCK(name, 0)` (no waiting) on a DEDICATED connection (`<base>__rollup_lock`), held for the whole
 *   run, released in `finally` with `RELEASE_LOCK`, and the connection is closed.
 * - The server releases an advisory lock when its session ends, so a crashed worker cannot leave it held.
 * - Ownership is verified, not assumed: owned() is true only if `IS_USED_LOCK(name)` equals the session id recorded at
 *   acquisition AND that is still this connection's `CONNECTION_ID()`. If the connection was lost — even if Laravel
 *   transparently reconnected it, which gives a NEW session that does not hold the lock — owned() is false. Any error
 *   while checking also counts as "not owned" (fail closed).
 * - The rollup checks owned() before every batch and again inside each batch's transaction immediately before COMMIT;
 *   when ownership is uncertain it rolls back and stops applying.
 *
 * Residual window: between that last check and the COMMIT itself (milliseconds), a lost lock session could let a
 * second worker start. Both would still be in REPEATABLE READ unless configured otherwise; see the Phase 2.1 report.
 */
final class DatabaseRollupLock
{
    public const NAME = 'way2allah:download_telemetry:rollup';

    private ?Connection $connection = null;

    private ?int $sessionId = null;

    /** @param (Closure(string, array<string, mixed>): void)|null $hook tests only */
    public function __construct(private readonly string $baseConnection, private readonly ?Closure $hook = null) {}

    public static function supported(Connection $db): bool
    {
        return in_array($db->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function connectionName(): string
    {
        return $this->baseConnection.'__rollup_lock';
    }

    /** True when acquired; false when another session holds it or the database cannot be reached. Never waits. */
    public function acquire(): bool
    {
        try {
            config(['database.connections.'.$this->connectionName() => config('database.connections.'.$this->baseConnection)]);
            DB::purge($this->connectionName());
            $this->connection = DB::connection($this->connectionName());
            $row = $this->connection->selectOne('SELECT GET_LOCK(?, 0) AS got, CONNECTION_ID() AS session_id', [self::NAME]);
            if ($row === null || (int) $row->got !== 1) {
                $this->close();

                return false;
            }
            $this->sessionId = (int) $row->session_id;
            $this->checkpoint('advisory_lock_acquired', ['session_id' => $this->sessionId]);

            return true;
        } catch (Throwable) {
            $this->close();

            return false;
        }
    }

    /** Fail-closed ownership check (see the class notes). */
    public function owned(): bool
    {
        if ($this->connection === null || $this->sessionId === null) {
            return false;
        }

        try {
            $row = $this->connection->selectOne('SELECT IS_USED_LOCK(?) AS holder, CONNECTION_ID() AS session_id', [self::NAME]);

            return $row !== null && $row->holder !== null
                && (int) $row->holder === $this->sessionId && (int) $row->session_id === $this->sessionId;
        } catch (Throwable) {
            return false;
        }
    }

    public function sessionId(): ?int
    {
        return $this->sessionId;
    }

    /** Release (if still owned) and close the dedicated connection. Never throws. */
    public function release(): void
    {
        try {
            if ($this->owned() && $this->connection !== null) {
                $this->connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [self::NAME]);
            }
        } catch (Throwable) {
            // closing the session below releases it anyway
        } finally {
            $this->close();
        }
    }

    private function close(): void
    {
        try {
            DB::purge($this->connectionName());
        } catch (Throwable) {
            // nothing else to do
        }
        $this->connection = null;
        $this->sessionId = null;
    }

    /** @param array<string, mixed> $context */
    private function checkpoint(string $point, array $context): void
    {
        if ($this->hook !== null) {
            ($this->hook)($point, $context);
        }
    }
}
