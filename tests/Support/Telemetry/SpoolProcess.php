<?php

namespace Tests\Support\Telemetry;

use RuntimeException;

/**
 * One child PHP process running tests/Support/Telemetry/spool-child.php, with
 * deterministic synchronisation through barrier files: the child stops at a
 * named protocol point ("after_open", "after_lock", "mid_write",
 * "create_locked", "sealer_locked", "sealer_before_rename",
 * "sealer_after_rename") until the test releases or kills it. No sleeps
 * decide an outcome; timeouts only guard against a hung test.
 */
final class SpoolProcess
{
    /** @var resource */
    private $process;

    private function __construct(
        private readonly string $id,
        private readonly string $barrier,
        private readonly string $outFile,
        private readonly string $errFile,
    ) {}

    /** @param array<string, mixed> $spec */
    public static function start(array $spec, string $barrier, ?int $fileSizeLimitBlocks = null, string $script = 'spool-child.php'): self
    {
        $id = (string) $spec['id'];
        $spec['barrier'] = $barrier;
        $specFile = "{$barrier}/{$id}.spec.json";
        file_put_contents($specFile, json_encode($spec, JSON_THROW_ON_ERROR));

        $child = __DIR__.'/'.$script;
        $command = $fileSizeLimitBlocks === null
            ? [PHP_BINARY, $child, $specFile]
            : ['/bin/sh', '-c', 'ulimit -f '.(int) $fileSizeLimitBlocks.' && exec "$0" "$1" "$2"', PHP_BINARY, $child, $specFile];

        $self = new self($id, $barrier, "{$barrier}/{$id}.out", "{$barrier}/{$id}.err");
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $self->outFile, 'w'], 2 => ['file', $self->errFile, 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot start spool child.');
        }
        $self->process = $process;

        return $self;
    }

    public static function go(string $barrier): void
    {
        touch("{$barrier}/go");
    }

    public function waitReady(float $timeout = 30.0): void
    {
        $this->waitFile("{$this->barrier}/{$this->id}.ready", $timeout);
    }

    public function waitReached(string $point, float $timeout = 30.0): void
    {
        $this->waitFile("{$this->barrier}/{$this->id}.{$point}.reached", $timeout);
    }

    public function release(string $point): void
    {
        touch("{$this->barrier}/{$this->id}.{$point}.release");
    }

    /** SIGKILL — no finally blocks, no shutdown functions: a real crash. */
    public function kill(): void
    {
        $this->signal(9);
    }

    /** Deliver a signal (9 = SIGKILL, 15 = SIGTERM; PHP CLI installs no SIGTERM handler, so both end it at once). */
    public function signal(int $signal): void
    {
        proc_terminate($this->process, $signal);
        $this->wait(30.0, false);
    }

    /** @return array<string, mixed> the child's JSON result */
    public function wait(float $timeout = 120.0, bool $expectResult = true): array
    {
        $until = microtime(true) + $timeout;
        while (proc_get_status($this->process)['running']) {
            if (microtime(true) > $until) {
                proc_terminate($this->process, 9);
                throw new RuntimeException("Spool child {$this->id} timed out: ".@file_get_contents($this->errFile));
            }
            usleep(1000);
        }
        proc_close($this->process);

        if (! $expectResult) {
            return [];
        }
        $out = (string) @file_get_contents($this->outFile);
        $result = json_decode($out, true);
        if (! is_array($result)) {
            throw new RuntimeException("Spool child {$this->id} gave no result. stderr: ".@file_get_contents($this->errFile).' stdout: '.substr($out, 0, 500));
        }

        return $result;
    }

    private function waitFile(string $file, float $timeout): void
    {
        $until = microtime(true) + $timeout;
        while (! file_exists($file)) {
            if (! proc_get_status($this->process)['running']) {
                throw new RuntimeException("Spool child {$this->id} exited before {$file}: ".@file_get_contents($this->errFile).@file_get_contents($this->outFile));
            }
            if (microtime(true) > $until) {
                throw new RuntimeException("Timed out waiting for {$file}");
            }
            usleep(200);
        }
    }
}
