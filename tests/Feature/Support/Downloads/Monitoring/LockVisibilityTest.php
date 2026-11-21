<?php

/*
 * Phase 3B.1 — lock visibility with REAL held slot locks from ANOTHER process (exactly as an Apache/PHP-CGI worker
 * holds them: an open handle with flock(LOCK_EX) on slot-NNN.lock). The monitor must see them with the holder's pid,
 * must report zero only with evidence (its own probe lock visible in the table), must report unknown when the table
 * cannot show the locks, and must never take or release a slot.
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\Monitoring\SlotInspector;

/** Start a child process that takes the given slots with flock(LOCK_EX) and holds them until stopped. */
function lvHolder(string $dir, array $slots): array
{
    $code = <<<'PHP'
        $dir = $argv[1]; $handles = [];
        @mkdir($dir, 0775, true);
        foreach (array_slice($argv, 2) as $n) {
            $h = fopen(sprintf('%s/slot-%03d.lock', $dir, $n), 'c');
            if (! flock($h, LOCK_EX | LOCK_NB)) { fwrite(STDOUT, "busy\n"); exit(1); }
            touch(sprintf('%s/slot-%03d.lock', $dir, $n));
            $handles[] = $h;
        }
        fwrite(STDOUT, getmypid()."\n"); fflush(STDOUT);
        fgets(STDIN);   // hold until the parent closes stdin
        PHP;
    $process = proc_open([PHP_BINARY, '-r', $code, $dir, ...array_map('strval', $slots)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);

    return ['process' => $process, 'pipes' => $pipes, 'pid' => (int) trim((string) fgets($pipes[1]))];
}

function lvStop(array $holder): void
{
    fclose($holder['pipes'][0]);
    fclose($holder['pipes'][1]);
    proc_close($holder['process']);   // the child exits on EOF; its locks go with it
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/lv-'.bin2hex(random_bytes(5));
    mkdir($this->dir);
    config(['downloads.guard' => true, 'downloads.max_concurrent' => 4, 'downloads.slot_directory' => $this->dir]);
    foreach (range(1, 4) as $n) {
        touch(sprintf('%s/slot-%03d.lock', $this->dir, $n));
    }
});

afterEach(function () {
    foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [] as $f) {
        is_file($f) && @unlink($f);
    }
    @rmdir($this->dir);
});

it('sees slots held by another process, with that process\'s pid, from the real kernel lock table', function () {
    $holder = lvHolder($this->dir, [1, 3]);
    try {
        $info = SlotInspector::inspect($this->dir, 4, (float) time());

        expect($info)->toMatchArray(['available' => true, 'occupied' => 2, 'visibility' => 'slot_locks_visible'])
            ->and(array_column($info['holders'], 'pid'))->toBe([$holder['pid'], $holder['pid']])
            ->and(array_column($info['holders'], 'slot'))->toEqualCanonicalizing([1, 3]);

        // The two other slots are genuinely free — and the inspector took none of them.
        $a = app(DownloadCapacityGuard::class)->tryAcquire();
        $b = app(DownloadCapacityGuard::class)->tryAcquire();
        expect($a?->isHeld())->toBeTrue()->and($b?->isHeld())->toBeTrue()
            ->and(app(DownloadCapacityGuard::class)->tryAcquire())->toBeNull();
        $a->release();
        $b->release();
    } finally {
        lvStop($holder);
    }

    // After the holder exits, zero is reported — with evidence: the probe lock was visible in the table.
    expect(SlotInspector::inspect($this->dir, 4, (float) time()))
        ->toMatchArray(['available' => true, 'occupied' => 0, 'visibility' => 'probe_visible']);
});

it('the probe is never a slot: it does not match the slot pattern and leaves every slot free', function () {
    SlotInspector::inspect($this->dir, 4, (float) time());

    expect(is_file($this->dir.'/'.SlotInspector::PROBE))->toBeTrue()
        ->and(preg_match('/^slot-\d{3,}\.lock$/', SlotInspector::PROBE))->toBe(0);
    $held = array_map(fn () => app(DownloadCapacityGuard::class)->tryAcquire(), range(1, 4));
    expect(array_filter($held, fn ($s) => $s?->isHeld()))->toHaveCount(4);
    array_map(fn ($s) => $s->release(), $held);
});

it('reports unknown — not zero — when the table cannot show this directory\'s locks', function () {
    // A table that is readable but lists nothing (as for a filesystem whose locks the kernel table does not show).
    file_put_contents($this->dir.'/../lv-empty-locks', '');
    $info = SlotInspector::inspect($this->dir, 4, (float) time(), $this->dir.'/../lv-empty-locks');
    @unlink($this->dir.'/../lv-empty-locks');

    expect($info)->toMatchArray(['available' => false, 'occupied' => null, 'visibility' => 'probe_not_visible']);
});

it('reports unknown when no probe can be taken (directory not writable)', function () {
    chmod($this->dir, 0555);
    if (is_writable($this->dir)) {
        chmod($this->dir, 0775);
        $this->markTestSkipped('running as a user that ignores file permissions');
    }
    try {
        $info = SlotInspector::inspect($this->dir, 4, (float) time());
    } finally {
        chmod($this->dir, 0775);
    }

    expect($info)->toMatchArray(['available' => false, 'occupied' => null, 'visibility' => 'probe_unavailable']);
});

it('a held slot is evidence on its own, even when the probe could not be taken', function () {
    $holder = lvHolder($this->dir, [2]);
    chmod($this->dir, 0555);
    try {
        $info = SlotInspector::inspect($this->dir, 4, (float) time());
    } finally {
        chmod($this->dir, 0775);
        lvStop($holder);
    }

    expect($info)->toMatchArray(['available' => true, 'occupied' => 1, 'visibility' => 'slot_locks_visible']);
});
