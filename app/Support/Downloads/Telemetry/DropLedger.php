<?php

namespace App\Support\Downloads\Telemetry;

use Closure;
use Throwable;

/**
 * The reserved, fixed-size record of everything the spool could NOT persist — the explicit data-loss indicator.
 *
 * Phase 1.1 design (owner, reliability corrections): SHARDED, per-event durable counters, with a bounded fallback.
 *
 *  - `drops/shard-NN.bin` × `ledger_shards` (default 16): each pre-allocated once (64-byte header + one 40-byte slot
 *    per minute in a ring) and only ever updated IN PLACE — they never grow, so they keep working on a full disk and
 *    at full spool capacity.
 *  - A drop is recorded synchronously, before write() returns, in whichever shard can be locked first: a sweep of
 *    non-blocking attempts starting at a per-process shard. Simultaneous drops therefore rarely meet on one lock.
 *    The total wait is bounded by `ledger_lock_budget_us`.
 *  - Fallback, ONLY if no shard could be updated within the budget (or the files are unusable): the delta is kept
 *    in process memory, merged into the next successful update, and flushed once more at process shutdown. Whatever
 *    still cannot be recorded is written to PHP's error log (an external record, on a different path) as a
 *    `W2A_TELEMETRY_UNRECORDED_DROPS` line, and counted in `unrecorded()`.
 *
 * Uncertainty is never hidden. snapshot() reports, per minute and overall: slots whose CRC does not match (a write
 * torn by a crash or power loss), reboot windows (the kernel boot id in a shard header changed: page-cache writes of
 * the previous boot after its last recorded minute may be missing), unreadable shards, and ring slots overwritten
 * before they were acknowledged. Counts in an uncertain minute are lower bounds.
 *
 * BLIND SPOT (irreducible): a process killed (SIGKILL, or SIGTERM without a handler) after deciding to drop an event
 * but before its slot write completes loses that one drop record — at most one per killed process — and leaves no
 * trace inside the spool. Every indicator above concerns counters that were recorded; a ledger with no known
 * uncertainty therefore does NOT prove that no drop was lost before it could be recorded.
 *
 * Slot (10 × uint32, big-endian): minute, dropped_events, dropped_bytes, reasons (DropReason bits), torn_repaired,
 * slow_writes, health_faults, max_latency_us, flags, crc32 (of the first nine).
 * Header (96 bytes, format 3): magic `W2ADROP3`, version, slots, slot size, shard index, overwritten_events (uint64),
 * ack_minute, boot id (16 bytes), last_minute, reboot_minute, reboot_previous_last_minute, late_min_minute,
 * late_max_minute, late_events, late_unslotted_events, 16 reserved bytes.
 */
final class DropLedger
{
    public const FLAG_TORN = 1;

    private const MAGIC = 'W2ADROP3';

    private const VERSION = 3;

    public const HEADER_BYTES = 96;

    public const SLOT_BYTES = 40;

    /** @var array<string, resource> open shard handles, per process */
    private static array $handles = [];

    /** @var array<int, array<string, int>> minute => delta not yet durably recorded (fallback only) */
    private static array $pending = [];

    private static bool $shutdownRegistered = false;

    private static int $unrecorded = 0;

    private static int $spins = 0;

    private static ?string $bootIdOverride = null;

    /** @param (Closure(string, array<string, mixed>): void)|null $hook tests only */
    public function __construct(private readonly SpoolConfig $config, private readonly ?Closure $hook = null) {}

    /** Dropped events this process could not record anywhere durable (after its final flush attempt). */
    public static function unrecorded(): int
    {
        return self::$unrecorded;
    }

    /** Dropped events currently held in this process's memory, awaiting a durable record. */
    public static function pendingEvents(): int
    {
        return array_sum(array_map(fn (array $d) => $d['dropped_events'] ?? 0, self::$pending));
    }

    /** Failed non-blocking shard-lock attempts in this process (ledger contention). */
    public static function spins(): int
    {
        return self::$spins;
    }

    /** Tests: pretend the machine has this boot id (32 hex chars), or null for the real one. */
    public static function overrideBootIdForTesting(?string $hex): void
    {
        self::$bootIdOverride = $hex;
    }

    /** Tests: forget this process's handles, pending deltas and counters. */
    public static function resetProcessStateForTesting(): void
    {
        foreach (self::$handles as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        self::$handles = [];
        self::$pending = [];
        self::$unrecorded = 0;
        self::$spins = 0;
    }

    /**
     * Add to minute's counters. True when recorded durably (together with any pending fallback deltas).
     *
     * @param  array{dropped_events?: int, dropped_bytes?: int, reason?: int, torn_repaired?: int, slow_writes?: int, health_faults?: int, latency_us?: int}  $delta
     */
    public function record(int $minute, array $delta): bool
    {
        $batch = self::$pending;
        self::merge($batch, $minute, $delta);

        try {
            if ($this->applyToAnyShard($batch, $this->config->ledgerLockBudgetUs)) {
                self::$pending = [];

                return true;
            }
        } catch (Throwable) {
            // fall through to the fallback
        }

        self::$pending = $batch;
        $this->registerShutdownFlush();

        return false;
    }

    /** Last attempt to record pending deltas; whatever remains is written to the error log and counted. */
    public function flushPending(): void
    {
        if (self::$pending === []) {
            return;
        }

        try {
            if ($this->applyToAnyShard(self::$pending, $this->config->ledgerLockBudgetUs)) {
                self::$pending = [];

                return;
            }
        } catch (Throwable) {
            // reported below
        }

        $events = self::pendingEvents();
        self::$unrecorded += $events;
        @error_log(sprintf('W2A_TELEMETRY_UNRECORDED_DROPS events=%d minutes=%s pid=%d', $events, implode(',', array_keys(self::$pending)), getmypid()));
        self::$pending = [];
    }

    /**
     * Totals per minute across all shards, with every uncertainty indicator.
     *
     * @return array{shards: int, shards_unreadable: int, slots: int, overwritten_events: int, late_pending_events: int, torn_slots: int, reboots: list<array{shard: int, detected_minute: int, previous_last_minute: int}>, entries: list<array{minute: int, dropped_events: int, dropped_bytes: int, reasons: int, torn_repaired: int, slow_writes: int, health_faults: int, max_latency_us: int, uncertain: bool}>}
     */
    public function snapshot(): array
    {
        $out = ['shards' => $this->config->ledgerShards, 'shards_unreadable' => 0, 'slots' => 0, 'overwritten_events' => 0,
            'late_pending_events' => 0, 'torn_slots' => 0, 'reboots' => [], 'entries' => []];
        $byMinute = [];

        for ($shard = 0; $shard < $this->config->ledgerShards; $shard++) {
            $path = $this->config->ledgerShardPath($shard);
            if (! is_file($path)) {
                continue;   // never needed yet: nothing recorded there, nothing lost
            }
            $handle = @fopen($path, 'rb');
            $header = $handle === false ? null : $this->readHeader($handle);
            if ($handle === false || $header === null) {
                $out['shards_unreadable']++;
                if ($handle !== false) {
                    fclose($handle);
                }

                continue;
            }

            $out['slots'] = $header['slots'];
            $out['overwritten_events'] += $header['overwritten_events'];
            $out['late_pending_events'] += $header['late_events'] + ($header['late_min_minute'] > 0 && $header['late_events'] === 0 ? 1 : 0);
            if ($header['reboot_minute'] > 0) {
                $out['reboots'][] = ['shard' => $shard, 'detected_minute' => $header['reboot_minute'], 'previous_last_minute' => $header['reboot_previous_last_minute']];
            }

            fseek($handle, self::HEADER_BYTES);
            $raw = (string) fread($handle, $header['slots'] * self::SLOT_BYTES);
            fclose($handle);

            for ($i = 0; $i < $header['slots']; $i++) {
                $slot = self::decodeSlot(substr($raw, $i * self::SLOT_BYTES, self::SLOT_BYTES));
                if ($slot === null || $slot['minute'] === 0) {
                    continue;
                }
                $torn = ! $slot['crc_ok'] || ($slot['flags'] & self::FLAG_TORN) !== 0;
                $out['torn_slots'] += $torn ? 1 : 0;

                $m = $slot['minute'];
                $byMinute[$m] ??= ['minute' => $m, 'dropped_events' => 0, 'dropped_bytes' => 0, 'reasons' => 0, 'torn_repaired' => 0,
                    'slow_writes' => 0, 'health_faults' => 0, 'max_latency_us' => 0, 'uncertain' => false];
                foreach (['dropped_events', 'dropped_bytes', 'torn_repaired', 'slow_writes', 'health_faults'] as $k) {
                    $byMinute[$m][$k] += $slot[$k];
                }
                $byMinute[$m]['reasons'] |= $slot['reasons'];
                $byMinute[$m]['max_latency_us'] = max($byMinute[$m]['max_latency_us'], $slot['max_latency_us']);
                $byMinute[$m]['uncertain'] = $byMinute[$m]['uncertain'] || $torn;
            }
        }

        foreach ($out['reboots'] as $reboot) {
            foreach ($byMinute as $m => $entry) {
                if ($m >= $reboot['previous_last_minute'] && $m <= $reboot['detected_minute']) {
                    $byMinute[$m]['uncertain'] = true;
                }
            }
        }
        ksort($byMinute);
        $out['entries'] = array_values($byMinute);

        return $out;
    }

    /**
     * Evidence for the minutes [$from, $to] across all shards (Phase 2 finalization): recorded drops, and every known
     * uncertainty. `wrapped` = some of these minutes are older than the ring can still hold (their slots may already
     * have been reused), so the absence of recorded drops proves nothing.
     *
     * @return array{dropped_events: int, uncertain_minutes: int, unreadable_shards: int, reboot_overlap: bool, wrapped: bool}
     */
    public function evidence(int $from, int $to, int $currentMinute): array
    {
        $out = ['dropped_events' => 0, 'uncertain_minutes' => 0, 'unreadable_shards' => 0, 'reboot_overlap' => false,
            'wrapped' => $from <= $currentMinute - $this->config->ledgerSlots];
        $uncertain = [];

        for ($shard = 0; $shard < $this->config->ledgerShards; $shard++) {
            $path = $this->config->ledgerShardPath($shard);
            if (! is_file($path)) {
                continue;
            }
            $handle = @fopen($path, 'rb');
            $header = $handle === false ? null : $this->readHeader($handle);
            if ($handle === false || $header === null) {
                $out['unreadable_shards']++;
                if ($handle !== false) {
                    fclose($handle);
                }

                continue;
            }
            if ($header['reboot_minute'] >= $from && $header['reboot_previous_last_minute'] <= $to) {
                $out['reboot_overlap'] = true;
            }
            for ($m = $from; $m <= $to; $m++) {
                fseek($handle, self::HEADER_BYTES + ($m % $header['slots']) * self::SLOT_BYTES);
                $slot = self::decodeSlot((string) fread($handle, self::SLOT_BYTES));
                if ($slot === null || $slot['minute'] !== $m) {
                    continue;
                }
                $out['dropped_events'] += $slot['dropped_events'];
                if (! $slot['crc_ok'] || ($slot['flags'] & self::FLAG_TORN) !== 0) {
                    $uncertain[$m] = true;
                }
            }
            fclose($handle);
        }
        $out['uncertain_minutes'] = count($uncertain);

        return $out;
    }

    /**
     * Mark every minute up to $minute as read (Phase 2, after finalization): from then on, reusing those ring slots is
     * expected and is no longer reported as `overwritten_events`. Best effort per shard; returns the shards updated.
     */
    public function acknowledge(int $minute): int
    {
        // The acknowledged minute also lives in drops/ack, so a shard created LATER starts from it (a drop for an
        // old minute is then still recognised as late). Written atomically; never moves backwards.
        if ($minute > $this->storedAck()) {
            $dir = dirname($this->config->ledgerShardPath(0));
            if (is_dir($dir) || @mkdir($dir, $this->config->dirMode, true)) {
                $tmp = $dir.'/ack.tmp-'.bin2hex(random_bytes(4));
                if (@file_put_contents($tmp, (string) $minute) !== false) {
                    $this->config->protect($tmp);
                    @rename($tmp, $dir.'/ack');
                }
                @unlink($tmp);
            }
        }

        $updated = 0;
        for ($shard = 0; $shard < $this->config->ledgerShards; $shard++) {
            $path = $this->config->ledgerShardPath($shard);
            $handle = is_file($path) ? @fopen($path, 'r+b') : false;
            if ($handle === false) {
                continue;
            }
            try {
                if (! FileLock::acquire($handle, $this->config->ledgerLockBudgetUs)) {
                    continue;
                }
                try {
                    $header = $this->readHeader($handle);
                    if ($header !== null && $header['ack_minute'] < $minute) {
                        $header['ack_minute'] = $minute;
                        $this->writeHeader($handle, $header);
                        $updated++;
                    }
                } finally {
                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $updated;
    }

    /** The acknowledged minute recorded in drops/ack (0 when none). */
    public function storedAck(): int
    {
        $raw = @file_get_contents(dirname($this->config->ledgerShardPath(0)).'/ack');

        return is_string($raw) && ctype_digit(trim($raw)) ? (int) trim($raw) : 0;
    }

    /**
     * Called by every rollup run (Phase 2.1): a shard whose header still carries a PREVIOUS boot id gets its reboot
     * window recorded now — [its last minute in the previous boot, this minute] — even if no drop ever touches it
     * again. Without this, reboot detection depended on the same shard being written before and after the reboot.
     * Returns the shards stamped with a reboot.
     */
    public function stampBoot(int $currentMinute): int
    {
        $boot = self::bootId();
        if ($boot === str_repeat("\0", 16)) {
            return 0;   // boot id unknown: detection unavailable
        }
        $stamped = 0;
        for ($shard = 0; $shard < $this->config->ledgerShards; $shard++) {
            $path = $this->config->ledgerShardPath($shard);
            $handle = is_file($path) ? @fopen($path, 'r+b') : false;
            if ($handle === false) {
                continue;
            }
            try {
                if (! FileLock::acquire($handle, $this->config->ledgerLockBudgetUs)) {
                    continue;
                }
                try {
                    $header = $this->readHeader($handle);
                    if ($header === null || $header['boot_id'] === $boot) {
                        continue;
                    }
                    if ($header['boot_id'] !== str_repeat("\0", 16) && $header['last_minute'] > 0) {
                        $header['reboot_previous_last_minute'] = $header['last_minute'];
                        $header['reboot_minute'] = $currentMinute;
                        $stamped++;
                    }
                    $header['boot_id'] = $boot;
                    $this->writeHeader($handle, $header);
                } finally {
                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $stamped;
    }

    /** The highest acknowledged minute across shards (0 when none). */
    public function acknowledgedMinute(): int
    {
        $ack = 0;
        for ($shard = 0; $shard < $this->config->ledgerShards; $shard++) {
            $handle = @fopen($this->config->ledgerShardPath($shard), 'rb');
            if ($handle === false) {
                continue;
            }
            $header = $this->readHeader($handle);
            fclose($handle);
            $ack = max($ack, $header['ack_minute'] ?? 0);
        }

        return $ack;
    }

    /**
     * Late evidence flagged in the shard headers (Phase 2.1): drops recorded for already-acknowledged minutes, or for
     * minutes whose slot already held a newer minute. Per shard, so clearLate() can clear exactly what was handled.
     *
     * @return array{min_minute: int, max_minute: int, events: int, unslotted_events: int, shards: array<int, array{min: int, max: int, events: int, unslotted: int}>}
     */
    public function lateEvidence(): array
    {
        $out = ['min_minute' => 0, 'max_minute' => 0, 'events' => 0, 'unslotted_events' => 0, 'shards' => []];
        for ($shard = 0; $shard < $this->config->ledgerShards; $shard++) {
            $handle = @fopen($this->config->ledgerShardPath($shard), 'rb');
            if ($handle === false) {
                continue;
            }
            $header = $this->readHeader($handle);
            fclose($handle);
            if ($header === null || $header['late_min_minute'] === 0) {
                continue;
            }
            $out['shards'][$shard] = ['min' => $header['late_min_minute'], 'max' => $header['late_max_minute'],
                'events' => $header['late_events'], 'unslotted' => $header['late_unslotted_events']];
            $out['min_minute'] = $out['min_minute'] === 0 ? $header['late_min_minute'] : min($out['min_minute'], $header['late_min_minute']);
            $out['max_minute'] = max($out['max_minute'], $header['late_max_minute']);
            $out['events'] += $header['late_events'];
            $out['unslotted_events'] += $header['late_unslotted_events'];
        }

        return $out;
    }

    /**
     * Clear late-evidence flags that were handled — only where the header still holds exactly what was read, so a
     * flag raised in between is kept for the next run.
     *
     * @param  array<int, array{min: int, max: int, events: int, unslotted: int}>  $handled
     */
    public function clearLate(array $handled): void
    {
        foreach ($handled as $shard => $seen) {
            $handle = @fopen($this->config->ledgerShardPath($shard), 'r+b');
            if ($handle === false) {
                continue;
            }
            try {
                if (! FileLock::acquire($handle, $this->config->ledgerLockBudgetUs)) {
                    continue;
                }
                try {
                    $header = $this->readHeader($handle);
                    if ($header !== null && $header['late_min_minute'] === $seen['min'] && $header['late_max_minute'] === $seen['max']
                        && $header['late_events'] === $seen['events'] && $header['late_unslotted_events'] === $seen['unslotted']) {
                        $header['late_min_minute'] = $header['late_max_minute'] = $header['late_events'] = $header['late_unslotted_events'] = 0;
                        $this->writeHeader($handle, $header);
                    }
                } finally {
                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }
    }

    /** @param array<int, array<string, int>> $batch */
    private function applyToAnyShard(array $batch, int $budgetUs): bool
    {
        $shards = $this->config->ledgerShards;
        $start = (getmypid() + random_int(0, $shards - 1)) % $shards;
        $deadline = hrtime(true) + $budgetUs * 1000;

        while (true) {
            $opened = 0;
            for ($k = 0; $k < $shards; $k++) {
                $shard = ($start + $k) % $shards;
                $handle = $this->handle($shard);
                if ($handle === null) {
                    continue;
                }
                $opened++;
                if (! flock($handle, LOCK_EX | LOCK_NB)) {
                    self::$spins++;

                    continue;
                }

                try {
                    $this->checkpoint('ledger_locked', ['shard' => $shard]);

                    return $this->applyBatch($handle, $batch);
                } finally {
                    flock($handle, LOCK_UN);
                }
            }

            // Phase 3C: no shard could even be opened or created (unwritable directory, disk error) — that is not
            // contention, and retrying for the rest of the budget only adds latency to the download (measured:
            // ~50 ms per dropped event). Give up at once; the caller's in-memory fallback and shutdown report apply.
            if ($opened === 0) {
                return false;
            }
            $remaining = intdiv($deadline - hrtime(true), 1000);
            if ($remaining <= 0) {
                return false;
            }
            usleep(min(20, $remaining));
        }
    }

    /**
     * @param  resource  $handle  locked
     * @param  array<int, array<string, int>>  $batch
     */
    private function applyBatch(mixed $handle, array $batch): bool
    {
        $header = $this->readHeader($handle);
        if ($header === null) {
            return false;
        }
        $headerChanged = false;

        $boot = self::bootId();
        if ($boot !== str_repeat("\0", 16)) {
            if ($header['boot_id'] !== $boot) {
                if ($header['boot_id'] !== str_repeat("\0", 16) && $header['last_minute'] > 0) {
                    $header['reboot_previous_last_minute'] = $header['last_minute'];
                    $header['reboot_minute'] = max(array_keys($batch));
                }
                $header['boot_id'] = $boot;
                $headerChanged = true;
            }
        }

        foreach ($batch as $minute => $delta) {
            $offset = self::HEADER_BYTES + ($minute % $header['slots']) * self::SLOT_BYTES;
            fseek($handle, $offset);
            $slot = self::decodeSlot((string) fread($handle, self::SLOT_BYTES)) ?? self::emptySlot(0);
            if (! $slot['crc_ok'] && $slot['minute'] !== 0) {
                $slot['flags'] |= self::FLAG_TORN;   // keep the values (a lower bound), flag the slot
            }

            // LATE EVIDENCE (Phase 2.1): a drop for a minute that was already acknowledged (its period may already be
            // final), or for a minute whose ring slot now holds a NEWER minute, is flagged in the header so the
            // finalizer reopens the affected periods. A newer minute's slot is never overwritten with an older one.
            $dropped = (int) ($delta['dropped_events'] ?? 0);
            $late = $minute <= $header['ack_minute'] || ($slot['minute'] !== 0 && $slot['minute'] > $minute);
            if ($late && ($dropped > 0 || ($delta['health_faults'] ?? 0) > 0)) {
                $header['late_min_minute'] = $header['late_min_minute'] === 0 ? $minute : min($header['late_min_minute'], $minute);
                $header['late_max_minute'] = max($header['late_max_minute'], $minute);
                $header['late_events'] += $dropped;
                $headerChanged = true;
            }
            if ($slot['minute'] !== 0 && $slot['minute'] > $minute) {
                $header['late_unslotted_events'] += $dropped;   // its own slot is gone: counted in the header only
                $headerChanged = true;

                continue;
            }

            if ($slot['minute'] !== $minute) {
                if ($slot['minute'] > $header['ack_minute'] && $slot['dropped_events'] > 0) {
                    $header['overwritten_events'] += $slot['dropped_events'];
                    $headerChanged = true;
                }
                $slot = self::emptySlot($minute);
            }

            $cap = 0xFFFFFFFF;
            $slot['dropped_events'] = min($cap, $slot['dropped_events'] + ($delta['dropped_events'] ?? 0));
            $slot['dropped_bytes'] = min($cap, $slot['dropped_bytes'] + ($delta['dropped_bytes'] ?? 0));
            $slot['reasons'] |= ($delta['reason'] ?? 0);
            $slot['torn_repaired'] = min($cap, $slot['torn_repaired'] + ($delta['torn_repaired'] ?? 0));
            $slot['slow_writes'] = min($cap, $slot['slow_writes'] + ($delta['slow_writes'] ?? 0));
            $slot['health_faults'] = min($cap, $slot['health_faults'] + ($delta['health_faults'] ?? 0));
            $slot['max_latency_us'] = min($cap, max($slot['max_latency_us'], $delta['latency_us'] ?? 0));

            fseek($handle, $offset);
            if (fwrite($handle, self::encodeSlot($slot)) !== self::SLOT_BYTES) {
                return false;
            }

            if ($minute > $header['last_minute']) {
                $header['last_minute'] = $minute;
                $headerChanged = true;
            }
        }

        if ($headerChanged) {
            $this->writeHeader($handle, $header);
        }

        return true;
    }

    /** @return resource|null the shard opened read-write (cached per process), created pre-allocated when missing */
    private function handle(int $shard): mixed
    {
        $path = $this->config->ledgerShardPath($shard);
        if (isset(self::$handles[$path]) && is_resource(self::$handles[$path])) {
            return self::$handles[$path];
        }

        $handle = @fopen($path, 'r+b');
        if ($handle === false) {
            $handle = $this->create($path, $shard);
        }
        if ($handle === null) {
            return null;
        }

        return self::$handles[$path] = $handle;
    }

    /** @return resource|null */
    private function create(string $path, int $shard): mixed
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, $this->config->dirMode, true) && ! is_dir($dir)) {
            return null;
        }

        // Pre-allocate the whole shard under a temporary name, then link() it into place:
        // link() fails if another process won the race, so each shard is created exactly once.
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(6));
        $slots = $this->config->ledgerSlots;
        $out = @fopen($temporary, 'xb');
        if ($out === false) {
            return null;
        }
        $this->config->protect($temporary);
        $ok = fwrite($out, $this->encodeHeader(['slots' => $slots, 'shard' => $shard, 'overwritten_events' => 0, 'ack_minute' => $this->storedAck(),
            'boot_id' => str_repeat("\0", 16), 'last_minute' => 0, 'reboot_minute' => 0, 'reboot_previous_last_minute' => 0,
            'late_min_minute' => 0, 'late_max_minute' => 0, 'late_events' => 0, 'late_unslotted_events' => 0])) === self::HEADER_BYTES;
        $chunk = str_repeat("\0", 256 * self::SLOT_BYTES);
        for ($remaining = $slots * self::SLOT_BYTES; $ok && $remaining > 0; $remaining -= strlen($piece)) {
            $piece = $remaining >= strlen($chunk) ? $chunk : substr($chunk, 0, $remaining);
            $ok = fwrite($out, $piece) === strlen($piece);
        }
        $ok = $ok && fflush($out);
        fclose($out);

        if ($ok) {
            @link($temporary, $path);
        }
        @unlink($temporary);

        $handle = @fopen($path, 'r+b');

        return $handle === false ? null : $handle;
    }

    /**
     * @param  resource  $handle
     * @return array{slots: int, shard: int, overwritten_events: int, ack_minute: int, boot_id: string, last_minute: int, reboot_minute: int, reboot_previous_last_minute: int, late_min_minute: int, late_max_minute: int, late_events: int, late_unslotted_events: int}|null
     */
    private function readHeader(mixed $handle): ?array
    {
        fseek($handle, 0);
        $raw = fread($handle, self::HEADER_BYTES);
        if (! is_string($raw) || strlen($raw) !== self::HEADER_BYTES || ! str_starts_with($raw, self::MAGIC)) {
            return null;
        }
        $f = unpack('Nversion/Nslots/Nslot_bytes/Nshard/Joverwritten/Nack', substr($raw, 8, 28));
        $tail = unpack('Nlast/Nreboot/Nprev/Nlate_min/Nlate_max/Nlate_events/Nlate_unslotted', substr($raw, 52, 28));
        if (! is_array($f) || ! is_array($tail) || $f['version'] !== self::VERSION || $f['slots'] < 1 || $f['slot_bytes'] !== self::SLOT_BYTES) {
            return null;
        }

        return ['slots' => $f['slots'], 'shard' => $f['shard'], 'overwritten_events' => $f['overwritten'], 'ack_minute' => $f['ack'],
            'boot_id' => substr($raw, 36, 16), 'last_minute' => $tail['last'], 'reboot_minute' => $tail['reboot'],
            'reboot_previous_last_minute' => $tail['prev'], 'late_min_minute' => $tail['late_min'], 'late_max_minute' => $tail['late_max'],
            'late_events' => $tail['late_events'], 'late_unslotted_events' => $tail['late_unslotted']];
    }

    /** @param array{slots: int, shard: int, overwritten_events: int, ack_minute: int, boot_id: string, last_minute: int, reboot_minute: int, reboot_previous_last_minute: int, late_min_minute: int, late_max_minute: int, late_events: int, late_unslotted_events: int} $h */
    private function encodeHeader(array $h): string
    {
        return self::MAGIC.pack('NNNNJN', self::VERSION, $h['slots'], self::SLOT_BYTES, $h['shard'], $h['overwritten_events'], $h['ack_minute'])
            .str_pad(substr($h['boot_id'], 0, 16), 16, "\0").pack('NNNNNNN', $h['last_minute'], $h['reboot_minute'], $h['reboot_previous_last_minute'],
                $h['late_min_minute'], $h['late_max_minute'], min(0xFFFFFFFF, $h['late_events']), min(0xFFFFFFFF, $h['late_unslotted_events']))
            .str_repeat("\0", 16);
    }

    /**
     * @param  resource  $handle
     * @param  array{slots: int, shard: int, overwritten_events: int, ack_minute: int, boot_id: string, last_minute: int, reboot_minute: int, reboot_previous_last_minute: int, late_min_minute: int, late_max_minute: int, late_events: int, late_unslotted_events: int}  $header
     */
    private function writeHeader(mixed $handle, array $header): void
    {
        fseek($handle, 0);
        fwrite($handle, $this->encodeHeader($header));
    }

    /** @return array{minute: int, dropped_events: int, dropped_bytes: int, reasons: int, torn_repaired: int, slow_writes: int, health_faults: int, max_latency_us: int, flags: int, crc_ok: bool}|null */
    private static function decodeSlot(string $raw): ?array
    {
        if (strlen($raw) !== self::SLOT_BYTES) {
            return null;
        }
        $v = unpack('N10', $raw);
        if (! is_array($v)) {
            return null;
        }
        $v = array_values($v);
        $blank = $raw === str_repeat("\0", self::SLOT_BYTES);

        return ['minute' => $v[0], 'dropped_events' => $v[1], 'dropped_bytes' => $v[2], 'reasons' => $v[3], 'torn_repaired' => $v[4],
            'slow_writes' => $v[5], 'health_faults' => $v[6], 'max_latency_us' => $v[7], 'flags' => $v[8],
            'crc_ok' => $blank || crc32(substr($raw, 0, 36)) === $v[9]];
    }

    /** @param array{minute: int, dropped_events: int, dropped_bytes: int, reasons: int, torn_repaired: int, slow_writes: int, health_faults: int, max_latency_us: int, flags: int, crc_ok: bool} $s */
    private static function encodeSlot(array $s): string
    {
        $body = pack('N9', $s['minute'], $s['dropped_events'], $s['dropped_bytes'], $s['reasons'], $s['torn_repaired'],
            $s['slow_writes'], $s['health_faults'], $s['max_latency_us'], $s['flags']);

        return $body.pack('N', crc32($body));
    }

    /** @return array{minute: int, dropped_events: int, dropped_bytes: int, reasons: int, torn_repaired: int, slow_writes: int, health_faults: int, max_latency_us: int, flags: int, crc_ok: bool} */
    private static function emptySlot(int $minute): array
    {
        return ['minute' => $minute, 'dropped_events' => 0, 'dropped_bytes' => 0, 'reasons' => 0, 'torn_repaired' => 0,
            'slow_writes' => 0, 'health_faults' => 0, 'max_latency_us' => 0, 'flags' => 0, 'crc_ok' => true];
    }

    /**
     * @param  array<int, array<string, int>>  $batch
     * @param  array<string, int>  $delta
     */
    private static function merge(array &$batch, int $minute, array $delta): void
    {
        $current = $batch[$minute] ?? [];
        foreach (['dropped_events', 'dropped_bytes', 'torn_repaired', 'slow_writes', 'health_faults'] as $k) {
            $current[$k] = ($current[$k] ?? 0) + ($delta[$k] ?? 0);
        }
        $current['reason'] = ($current['reason'] ?? 0) | ($delta['reason'] ?? 0);
        $current['latency_us'] = max($current['latency_us'] ?? 0, $delta['latency_us'] ?? 0);
        $batch[$minute] = $current;
    }

    /** 16 raw bytes of the kernel boot id (zeros when unknown: reboot detection then disabled). */
    private static function bootId(): string
    {
        static $cached = null;
        $hex = self::$bootIdOverride;
        if ($hex === null) {
            $cached ??= strtolower(str_replace('-', '', trim((string) @file_get_contents('/proc/sys/kernel/random/boot_id'))));
            $hex = $cached;
        }

        return strlen($hex) === 32 && ctype_xdigit($hex) ? (string) hex2bin($hex) : str_repeat("\0", 16);
    }

    private function registerShutdownFlush(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        $ledger = $this;
        register_shutdown_function(static function () use ($ledger): void {
            try {
                $ledger->flushPending();
            } catch (Throwable) {
                // never let telemetry fail a request's shutdown
            }
        });
    }

    /** @param array<string, mixed> $context */
    private function checkpoint(string $point, array $context): void
    {
        if ($this->hook !== null) {
            ($this->hook)($point, $context);
        }
    }
}
