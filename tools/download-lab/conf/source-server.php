<?php

// Scripted upstream for the lab (forks per connection). Paths:
//   /file/<bytes>/<name>            200, Content-Length, Content-Type: application/x-upstream-says-this, full speed
//   /slow/<bytes>/<bps>/<name>      drips at <bps> bytes/s in 16 KiB writes
//   /stall/<bytes>/<after>/<name>   sends <after> bytes then goes silent (holds the socket up to 900 s)
//   /drop/<bytes>/<after>/<name>    sends <after> bytes then closes cleanly (truncated body)
//   /reset/<bytes>/<after>/<name>   sends <after> bytes then aborts with RST (SO_LINGER 0)
//   /html                           200 text/html page;   /missing → 404
// Logs "OPEN id path" and "CLOSE id path sent=… reason=…" with timestamps to stdout.
$server = stream_socket_server('tcp://0.0.0.0:8090', $errno, $errstr);
pcntl_signal(SIGCHLD, SIG_IGN);
$n = 0;
function out(string $m): void
{
    fwrite(STDOUT, sprintf("%.3f %s\n", microtime(true), $m));
    fflush(STDOUT);
}
while (true) {
    $c = @stream_socket_accept($server, -1);
    if (! $c) {
        continue;
    }
    $n++;
    if (pcntl_fork() !== 0) {
        fclose($c);

        continue;
    }
    $req = '';
    while (! str_contains($req, "\r\n\r\n") && ($l = fread($c, 4096)) !== false && $l !== '') {
        $req .= $l;
    }
    [$method, $path] = array_pad(explode(' ', strtok($req, "\r\n")), 2, '/');
    $p = explode('/', trim($path, '/'));
    out("OPEN $n $method $path");
    $sent = 0;
    $reason = 'complete';
    $chunk = str_repeat('W2A-LAB-BYTES-0123456789abcdef', 546).'xyzw';   // 16384 bytes, deterministic
    $send = function (int $bytes, int $bps = 0) use ($c, $chunk, &$sent, &$reason): bool {
        while ($bytes > 0) {
            $piece = substr($chunk, 0, min(16384, $bytes));
            if (@fwrite($c, $piece) === false || ($bps > 0 && false)) {
                $reason = 'client_closed';

                return false;
            }
            $sent += strlen($piece);
            $bytes -= strlen($piece);
            if ($bps > 0) {
                usleep((int) (strlen($piece) / $bps * 1e6));
            }
            $meta = stream_get_meta_data($c);
            if (feof($c) && $meta['eof']) {
                $reason = 'client_closed';

                return false;
            }
        }

        return true;
    };
    $head = fn (int $len, string $type = 'application/x-upstream-says-this') => "HTTP/1.1 200 OK\r\nContent-Type: {$type}\r\nContent-Length: {$len}\r\nConnection: close\r\n\r\n";
    switch ($p[0] ?? '') {
        case 'file':  fwrite($c, $head((int) $p[1]));
            if ($method !== 'HEAD') {
                $send((int) $p[1]);
            } break;
        case 'slow':  fwrite($c, $head((int) $p[1]));
            $send((int) $p[1], (int) $p[2]);
            break;
        case 'stall': fwrite($c, $head((int) $p[1]));
            $send((int) $p[2]);
            $reason = 'stalled';
            $until = time() + 900;
            while (time() < $until) {
                $r = [$c];
                $w = $e = null;
                if (@stream_select($r, $w, $e, 1) > 0) {
                    $d = fread($c, 1);
                    if ($d === '' || $d === false) {
                        $reason = 'stalled_client_closed';
                        break;
                    }
                }
            } break;
        case 'drop':  fwrite($c, $head((int) $p[1]));
            $send((int) $p[2]);
            $reason = 'dropped';
            break;
        case 'reset': fwrite($c, $head((int) $p[1]));
            $send((int) $p[2]);
            $reason = 'reset';
            $sock = socket_import_stream($c);
            socket_set_option($sock, SOL_SOCKET, SO_LINGER, ['l_onoff' => 1, 'l_linger' => 0]);
            break;
        case 'html':  $b = '<html><body>video page</body></html>';
            fwrite($c, $head(strlen($b), 'text/html; charset=utf-8').$b);
            break;
        default:      fwrite($c, "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
            $reason = '404';
    }
    out("CLOSE $n $path sent=$sent reason=$reason");
    fclose($c);
    exit(0);
}
