<?php

namespace App\Support\Downloads\Monitoring;

/**
 * Which download slots are held, by whom and for how long — WITHOUT touching a lock (Phase 3B).
 *
 * Trying a slot's lock to see whether it is free would, for that instant, BE a slot holder (a concurrent download
 * could be refused) — so this never calls flock() on a slot file. Instead it matches the slot files' device and inode against the
 * kernel's lock table (`/proc/locks`, Linux), and reads each slot's acquisition time from its mtime (stamped by
 * DownloadCapacityGuard when the slot is taken). Read-only and bounded: one stat per slot file (at most the limit plus
 * a few), and at most MAX_LOCKS_BYTES of the lock table.
 *
 * Where `/proc/locks` is unavailable, occupancy is reported as unknown — never guessed.
 *
 * Zero needs evidence (Phase 3B.1): a held slot found in the table proves the table shows this filesystem's locks.
 * When NONE is found, the inspector locks its own probe file in the slot directory (`.monitor-visibility-probe`, never a
 * slot: the guard only ever opens `slot-NNN.lock`) and looks for THAT lock in the table. Only if the probe is visible is
 * "0 occupied" reported; otherwise occupancy is unknown (e.g. a filesystem whose locks the kernel table does not list,
 * a different mount namespace, an unwritable directory, or a truncated table).
 */
final class SlotInspector
{
    public const MAX_LOCKS_BYTES = 8 * 1024 * 1024;

    public const PROBE = '.monitor-visibility-probe';

    /** @return array{available: bool, occupied: int|null, limit: int, slot_files: int, holders: list<array{slot: int, pid: int, held_seconds: int|null}>, oldest_held_seconds: int|null, lock_table_truncated: bool, visibility: string} */
    public static function inspect(string $directory, int $limit, float $now, string $locksPath = '/proc/locks'): array
    {
        $slots = [];   // "dev:ino" => slot number
        foreach (@scandir($directory) ?: [] as $name) {
            if (preg_match('/^slot-(\d{3,})\.lock$/', $name, $m) !== 1 || count($slots) > $limit + 64) {
                continue;
            }
            $stat = @stat($directory.'/'.$name);
            if ($stat !== false) {
                $slots[self::deviceKey((int) $stat['dev']).':'.$stat['ino']] = ['slot' => (int) $m[1], 'mtime' => (int) $stat['mtime']];
            }
        }

        $result = ['available' => false, 'occupied' => null, 'limit' => $limit, 'slot_files' => count($slots), 'holders' => [],
            'oldest_held_seconds' => null, 'lock_table_truncated' => false, 'visibility' => 'unverified'];

        $table = self::readLockTable($locksPath);
        if ($table === null) {
            $result['visibility'] = 'lock_table_unreadable';

            return $result;
        }
        $result['lock_table_truncated'] = $table['truncated'];

        $holders = [];
        foreach ($table['locks'] as [$key, $pid]) {
            if (isset($slots[$key]) && ! isset($holders[$key])) {
                $held = max(0, (int) $now - $slots[$key]['mtime']);
                $holders[$key] = ['slot' => $slots[$key]['slot'], 'pid' => $pid, 'held_seconds' => $held];
            }
        }

        if ($holders === [] && $slots === []) {
            $result['visibility'] = 'no_slot_files';   // no slot file exists, so no slot can be held: zero is proven
        } elseif ($holders === []) {
            // No evidence yet that this table can show the slot directory's locks: prove it with a probe.
            $visible = self::probeVisible($directory, $locksPath);
            if ($visible !== true || $table['truncated']) {
                $result['visibility'] = $table['truncated'] ? 'lock_table_truncated' : ($visible === null ? 'probe_unavailable' : 'probe_not_visible');

                return $result;   // occupancy stays unknown
            }
            $result['visibility'] = 'probe_visible';
        } else {
            $result['visibility'] = 'slot_locks_visible';
        }

        $holders = array_values($holders);
        usort($holders, fn ($a, $b) => $b['held_seconds'] <=> $a['held_seconds']);

        $result['available'] = true;
        $result['occupied'] = count($holders);
        $result['holders'] = $holders;
        $result['oldest_held_seconds'] = $holders[0]['held_seconds'] ?? null;

        return $result;
    }

    /** @return array{locks: list<array{0: string, 1: int}>, truncated: bool}|null "dev:ino" + pid of every held FLOCK write lock */
    private static function readLockTable(string $locksPath): ?array
    {
        $handle = @fopen($locksPath, 'rb');
        if ($handle === false) {
            return null;
        }
        $read = 0;
        $locks = [];
        $truncated = false;
        while (($line = fgets($handle, 512)) !== false) {
            $read += strlen($line);
            if ($read > self::MAX_LOCKS_BYTES) {
                $truncated = true;
                break;
            }
            // "12: FLOCK  ADVISORY  WRITE 4242 08:02:1311 0 EOF"; waiters are marked "->" and hold nothing.
            if (str_contains($line, '->') || preg_match('/\bFLOCK\s+\S+\s+WRITE\s+(\d+)\s+([0-9a-f]+):([0-9a-f]+):(\d+)\s/i', $line, $m) !== 1) {
                continue;
            }
            $locks[] = [hexdec($m[2]).':'.hexdec($m[3]).':'.$m[4], (int) $m[1]];
        }
        fclose($handle);

        return ['locks' => $locks, 'truncated' => $truncated];
    }

    /** true = our probe lock appears in the table; false = it does not; null = no probe could be locked. */
    private static function probeVisible(string $directory, string $locksPath): ?bool
    {
        $path = $directory.'/'.self::PROBE;
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            return null;
        }
        try {
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                return null;   // another monitor is probing right now
            }
            $stat = fstat($handle);
            $table = $stat === false ? null : self::readLockTable($locksPath);
            if ($table === null) {
                return null;
            }
            $key = self::deviceKey((int) $stat['dev']).':'.$stat['ino'];
            foreach ($table['locks'] as [$k, $pid]) {
                if ($k === $key && $pid === getmypid()) {
                    return true;
                }
            }

            return false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** "major:minor" of a Linux st_dev, as /proc/locks prints it (in hex there; decimal here after hexdec()). */
    private static function deviceKey(int $dev): string
    {
        $major = (($dev >> 8) & 0xFFF) | (($dev >> 32) & ~0xFFF);
        $minor = ($dev & 0xFF) | (($dev >> 12) & ~0xFF);

        return $major.':'.$minor;
    }
}
