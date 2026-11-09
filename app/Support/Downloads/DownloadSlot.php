<?php

namespace App\Support\Downloads;

/**
 * One held slot of the global download-capacity pool (DownloadCapacityGuard).
 *
 * Holds an open file handle with an exclusive `flock()` on it. The slot is
 * occupied exactly as long as that lock is held:
 *
 * - `release()` (or the destructor, if the slot simply goes out of scope)
 *   unlocks and closes it;
 * - if the PHP process dies, is killed, or the request is torn down, the OS
 *   closes the descriptor and drops the lock by itself — nothing persistent
 *   is left behind that could shrink the pool.
 *
 * An "unguarded" slot (guard disabled, or failing open) holds nothing and
 * releases nothing.
 */
final class DownloadSlot
{
    /** @param resource|null $handle */
    private function __construct(private mixed $handle) {}

    /** @param resource $handle an open handle on which this process holds LOCK_EX */
    public static function held(mixed $handle): self
    {
        return new self($handle);
    }

    public static function unguarded(): self
    {
        return new self(null);
    }

    public function isHeld(): bool
    {
        return is_resource($this->handle);
    }

    /** Idempotent. */
    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }

        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
