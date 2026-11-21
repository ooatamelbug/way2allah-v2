<?php

namespace Tests\Support\Telemetry;

use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\Telemetry\BatchInspector;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema;
use App\Support\Downloads\Telemetry\TelemetryEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;

/**
 * Phase 3C local shadow-validation harness: the real application behind PHP's built-in server
 * (tests/Support/Telemetry/http-front.php), optionally with several worker processes (PHP_CLI_SERVER_WORKERS), an
 * isolated SQLite `main` database file, scratch slot and spool directories, and per-request configuration (the
 * router re-reads its JSON config on every request, so a scenario switch needs no restart).
 *
 * Measures what the comparison needs: response status / headers / body hash / duration per request, the server
 * processes' CPU time, peak RSS and I/O counters (from /proc, Linux), spool growth, and the telemetry events written.
 *
 * NOT production-equivalent: PHP's CLI development server, not PHP-CGI/FastCGI behind Apache.
 */
final class ShadowServer
{
    public string $scratch;

    public string $db;

    public string $root;

    public string $slotDir;

    public int $port = 0;

    /** @var resource|null */
    private $process = null;

    private string $cfgFile;

    /** @var array<string, mixed> */
    public array $telemetry = [];

    /** @var array<string, mixed> */
    public array $downloads = [];

    public function __construct(private readonly int $workers = 1)
    {
        $this->scratch = sys_get_temp_dir().'/shadow-'.bin2hex(random_bytes(5));
        mkdir($this->scratch);
        $this->db = $this->scratch.'/main.sqlite';
        $this->root = $this->scratch.'/spool';
        $this->slotDir = $this->scratch.'/slots';
        $this->cfgFile = $this->scratch.'/config.json';
        touch($this->db);

        config(['database.connections.shadow' => ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '', 'foreign_key_constraints' => false]]);
        $tables = ['nuke_authors' => MainSchema::nukeAuthors(), 'nuke_config' => MainSchema::nukeConfig(), 'ips' => MainSchema::ips(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(), 'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
            'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(), DownloadRejectionRecorder::TABLE => MainSchema::downloadRejections()]
            + TelemetrySchema::tables();
        foreach ($tables as $table => $definition) {
            Schema::connection('shadow')->create($table, $definition);
        }
        DB::connection('shadow')->statement('PRAGMA journal_mode=WAL');   // concurrent workers update counters
        DB::connection('shadow')->table('nuke_config')->insert(['language' => 'arabic', 'multilingual' => 0, 'anonymous' => 'Anonymous']);
        DB::connection('shadow')->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);

        $this->downloads = ['downloads.guard' => true, 'downloads.max_concurrent' => 16, 'downloads.slot_directory' => $this->slotDir,
            'downloads.remote_connect_timeout' => 3.0, 'downloads.remote_idle_timeout' => 1.0, 'downloads.rejection_log' => true];
    }

    /** @param array<string, mixed>|null $telemetry null = telemetry fully disabled */
    public function configure(?array $telemetry, array $downloads = []): void
    {
        $this->downloads = $downloads + $this->downloads;
        $this->telemetry = $telemetry === null ? ['events' => 'off', 'spool' => 'off'] : $telemetry + [
            'events' => 'on', 'spool' => 'on', 'root' => $this->root, 'file_max_bytes' => 32 * 1024 * 1024, 'max_files' => 32,
            'disk_reserve_bytes' => 0, 'lock_budget_us' => 2000, 'ledger_lock_budget_us' => 50_000, 'ledger_slots' => 120,
            'ledger_shards' => 4, 'slow_write_us' => 50_000, 'breaker_fault_threshold' => 50, 'breaker_open_seconds' => 300,
            'seal_grace_minutes' => 0, 'aggregate' => 'on', 'connection' => 'main',
        ];
        $sqlite = ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '', 'foreign_key_constraints' => false, 'busy_timeout' => 5000];
        file_put_contents($this->cfgFile, json_encode([
            'base' => base_path(),
            'env' => ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => (string) config('app.key'), 'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->db, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
                'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single', 'BROADCAST_CONNECTION' => 'null', 'PULSE_ENABLED' => 'false',
                'TELESCOPE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false'],
            'config' => ['database.connections.main' => $sqlite, 'database.connections.sqlite' => $sqlite,
                'logging.channels.single.path' => $this->scratch.'/laravel.log', 'download_telemetry' => $this->telemetry] + $this->downloads,
        ], JSON_THROW_ON_ERROR));
        // The test process inspects the same slots and spool.
        config(['download_telemetry' => $this->telemetry] + $this->downloads);
    }

    public function start(): void
    {
        if ($this->telemetry === [] && $this->downloads !== []) {
            $this->configure(null);
        }
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $this->process = proc_open(
            [PHP_BINARY, '-d', 'memory_limit=256M', '-S', '127.0.0.1:'.$this->port, '-t', base_path('public'), base_path('tests/Support/Telemetry/http-front.php')],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->scratch.'/server.out', 'a'], 2 => ['file', $this->scratch.'/server.err', 'a']],
            $pipes, base_path(), ['W2A_IT_CONFIG' => $this->cfgFile, 'PATH' => (string) getenv('PATH'), 'PHP_CLI_SERVER_WORKERS' => (string) $this->workers],
        );
        $until = microtime(true) + 10;
        while (microtime(true) < $until && ! @stream_socket_client('tcp://127.0.0.1:'.$this->port, $e, $s, 0.2)) {
            usleep(50_000);
        }
        usleep(200_000);   // workers forked
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            foreach ($this->pids() as $pid) {
                @posix_kill($pid, SIGTERM);   // this harness's own isolated `php -S` processes only
            }
            proc_close($this->process);
        }
        $this->process = null;
    }

    public function destroy(): void
    {
        $this->stop();
        DB::purge('shadow');
        if (is_dir($this->scratch)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->scratch, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                @chmod($f->getPathname(), 0700);
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($this->scratch);
        }
    }

    /** The server's process ids: the master and (with workers) its children. @return list<int> */
    public function pids(): array
    {
        if (! is_resource($this->process)) {
            return [];
        }
        $master = (int) proc_get_status($this->process)['pid'];
        $children = trim((string) @file_get_contents("/proc/{$master}/task/{$master}/children"));

        return array_values(array_filter([$master, ...array_map('intval', $children === '' ? [] : explode(' ', $children))]));
    }

    /** CPU seconds (user+system), peak RSS (max over processes, KiB), syscall write bytes, block write bytes. @return array{cpu_s: float, rss_peak_kib: int, wchar: int, write_bytes: int} */
    public function processStats(): array
    {
        $tick = 100.0;
        $cpu = 0.0;
        $rss = 0;
        $wchar = $wb = 0;
        foreach ($this->pids() as $pid) {
            $stat = (string) @file_get_contents("/proc/{$pid}/stat");
            $fields = explode(' ', substr($stat, (int) strrpos($stat, ')') + 2));
            $cpu += ((int) ($fields[11] ?? 0) + (int) ($fields[12] ?? 0)) / $tick;
            if (preg_match('/VmHWM:\s+(\d+)/', (string) @file_get_contents("/proc/{$pid}/status"), $m) === 1) {
                $rss = max($rss, (int) $m[1]);
            }
            $io = (string) @file_get_contents("/proc/{$pid}/io");
            $wchar += preg_match('/wchar:\s+(\d+)/', $io, $m) === 1 ? (int) $m[1] : 0;
            $wb += preg_match('/^write_bytes:\s+(\d+)/m', $io, $m) === 1 ? (int) $m[1] : 0;
        }

        return ['cpu_s' => round($cpu, 2), 'rss_peak_kib' => $rss, 'wchar' => $wchar, 'write_bytes' => $wb];
    }

    public function addItem(string $link, int $downcount = 0): int
    {
        return (int) DB::connection('shadow')->table('nuke_islamic_khotab')->insertGetId(
            ['author' => 7, 'title' => 'ظل', 'vedio' => 1, 'link' => $link, 'linksize' => 1, 'downcount' => $downcount]);
    }

    public function downcount(int $id): int
    {
        return (int) DB::connection('shadow')->table('nuke_islamic_khotab')->where('id', $id)->value('downcount');
    }

    /** @return array{status: int, headers: array<string, string>, sha256: string, bytes: int, ms: float} */
    public function request(string $path, string $method = 'GET', ?int $abortAfterBytes = null): array
    {
        $started = microtime(true);
        $socket = stream_socket_client('tcp://127.0.0.1:'.$this->port, $errno, $errstr, 5);
        stream_set_timeout($socket, 30);
        fwrite($socket, "{$method} {$path} HTTP/1.0\r\nHost: 127.0.0.1\r\nUser-Agent: Mozilla/5.0 ShadowValidation\r\n\r\n");
        $raw = '';
        while (! feof($socket)) {
            $chunk = fread($socket, 65536);
            if ($chunk === false || ($chunk === '' && stream_get_meta_data($socket)['timed_out'])) {
                break;
            }
            $raw .= $chunk;
            if ($abortAfterBytes !== null && strlen($raw) >= $abortAfterBytes) {
                break;
            }
        }
        fclose($socket);

        return self::parse($raw, (microtime(true) - $started) * 1000);
    }

    /**
     * Concurrent GETs with curl_multi, `$parallel` in flight at once.
     *
     * @param  list<string>  $paths
     * @return list<array{status: int, headers: array<string, string>, sha256: string, bytes: int, ms: float}>
     */
    public function concurrent(array $paths, int $parallel): array
    {
        $multi = curl_multi_init();
        $results = [];
        $queue = $paths;
        $active = [];
        $add = function () use (&$queue, &$active, $multi) {
            $path = array_shift($queue);
            $h = curl_init('http://127.0.0.1:'.$this->port.$path);
            curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
                CURLOPT_USERAGENT => 'Mozilla/5.0 ShadowValidation', CURLOPT_TIMEOUT => 60]);
            curl_multi_add_handle($multi, $h);
            $active[(int) $h] = [$h, microtime(true)];
        };
        while (count($active) < $parallel && $queue !== []) {
            $add();
        }
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi, 0.05);
            while (($info = curl_multi_info_read($multi)) !== false) {
                $h = $info['handle'];
                [, $t0] = $active[(int) $h];
                $results[] = self::parse((string) curl_multi_getcontent($h), (microtime(true) - $t0) * 1000);
                curl_multi_remove_handle($multi, $h);
                unset($active[(int) $h]);
                if ($queue !== []) {
                    $add();
                }
            }
        } while ($active !== []);
        curl_multi_close($multi);

        return $results;
    }

    /** @return list<array<string, mixed>> */
    public function events(): array
    {
        $events = [];
        foreach (['active', 'sealed', 'done', 'quarantine'] as $state) {
            foreach (glob($this->root.'/'.$state.'/*.ndjson') ?: [] as $file) {
                BatchInspector::inspect($file, function (TelemetryEvent $e) use (&$events) {
                    $events[] = $e->toArray();
                });
            }
        }

        return $events;
    }

    public function spoolBytes(): int
    {
        $bytes = 0;
        if (is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)) as $f) {
                $bytes += $f->isFile() ? $f->getSize() : 0;
            }
        }

        return $bytes;
    }

    /** @return array{status: int, headers: array<string, string>, sha256: string, bytes: int, ms: float} */
    private static function parse(string $raw, float $ms): array
    {
        [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
        $lines = explode("\r\n", $head);
        preg_match('#^HTTP/\S+\s+(\d{3})#', $lines[0] ?? '', $m);
        $headers = [];
        foreach (array_slice($lines, 1) as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $name = strtolower(trim($name));
            if (! in_array($name, ['date', 'set-cookie', 'x-request-id'], true)) {   // per-request values
                $headers[$name] = trim($value);
            }
        }
        ksort($headers);

        return ['status' => (int) ($m[1] ?? 0), 'headers' => $headers, 'sha256' => hash('sha256', $body), 'bytes' => strlen($body), 'ms' => round($ms, 2)];
    }
}
