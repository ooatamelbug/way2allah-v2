<?php

namespace Tests\Support;

/**
 * A real HTTP source on 127.0.0.1, served by a child PHP process, for download
 * tests that must go through PHP's NATIVE http:// wrapper (status lines,
 * headers, redirects, chunked bodies, socket timeouts) rather than a userland
 * stream wrapper.
 *
 * Each accepted connection takes the next scripted reply, in order:
 *  - `raw`: the exact bytes written (status line, headers, body);
 *  - `after`: `close` (close at once) or `hold` (keep the connection open and
 *    silent until the client closes it, or 20 s pass);
 *  - `drip` (optional): after `raw`, write `chunk` `count` times, `interval`
 *    seconds apart (a slow upstream); a failed write marks `client-closed`;
 *  - `delay` (optional): seconds to wait before writing `raw` (a slow first byte).
 * Relative `Location: /path` redirects resolve against this same server.
 */
final class LocalHttpSource
{
    /** @var resource */
    private $process;

    /** @var array<int, resource> */
    private array $pipes;

    private function __construct(private readonly string $directory, public readonly string $address) {}

    /** @param list<array{raw: string, after?: 'close'|'hold', delay?: float, drip?: array{chunk: string, count: int, interval: float}}> $replies */
    public static function start(array $replies): self
    {
        $directory = sys_get_temp_dir().'/local-http-source-'.uniqid();
        mkdir($directory);
        file_put_contents($directory.'/replies.json', json_encode($replies));
        file_put_contents($directory.'/server.php', self::SERVER);

        $process = proc_open([PHP_BINARY, $directory.'/server.php', $directory], [1 => ['pipe', 'w']], $pipes);
        $source = new self($directory, trim((string) fgets($pipes[1])));
        $source->process = $process;
        $source->pipes = $pipes;

        return $source;
    }

    /** A 200 reply with the given body and its exact Content-Length. */
    public static function ok(string $body, string $type = 'video/mp4'): array
    {
        return ['raw' => "HTTP/1.1 200 OK\r\nContent-Type: {$type}\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body];
    }

    public function url(string $path = '/archive/lecture.mp4'): string
    {
        return 'http://'.$this->address.$path;
    }

    /** The request lines received so far, in order. */
    public function requests(): array
    {
        clearstatcache();
        $log = $this->directory.'/requests.log';

        return is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    }

    /** Whether the client closed a held connection, waiting up to `$seconds`. */
    public function sawClientClose(float $seconds = 3.0): bool
    {
        $until = microtime(true) + $seconds;
        do {
            clearstatcache();
            if (is_file($this->directory.'/client-closed')) {
                return true;
            }
            usleep(20_000);
        } while (microtime(true) < $until);

        return false;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) {
                @fclose($pipe);
            }
            proc_close($this->process);
        }
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    private const SERVER = <<<'PHP'
        <?php
        $directory = $argv[1];
        $replies = json_decode(file_get_contents($directory.'/replies.json'), true);
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        fwrite(STDOUT, stream_socket_get_name($server, false)."\n");
        fflush(STDOUT);
        foreach ($replies as $reply) {
            $client = @stream_socket_accept($server, 20);
            if (! $client) { exit; }
            $request = '';
            while (! str_contains($request, "\r\n\r\n") && is_string($line = fread($client, 4096)) && $line !== '') { $request .= $line; }
            file_put_contents($directory.'/requests.log', strtok($request, "\r\n")."\n", FILE_APPEND);
            if (isset($reply['delay'])) { usleep((int) ($reply['delay'] * 1000000)); }
            if ($reply['raw'] !== '') { fwrite($client, $reply['raw']); }
            if (isset($reply['drip'])) {
                for ($i = 0; $i < $reply['drip']['count']; $i++) {
                    usleep((int) ($reply['drip']['interval'] * 1000000));
                    if (@fwrite($client, $reply['drip']['chunk']) === false) { touch($directory.'/client-closed'); break; }
                }
            }
            if (($reply['after'] ?? 'close') === 'hold') {
                $deadline = time() + 20;
                while (time() < $deadline) {
                    $read = [$client]; $write = $except = null;
                    if (stream_select($read, $write, $except, 1) > 0) {
                        $data = fread($client, 4096);
                        if ($data === '' || $data === false) { touch($directory.'/client-closed'); break; }
                    }
                }
            }
            fclose($client);
        }
        PHP;
}
