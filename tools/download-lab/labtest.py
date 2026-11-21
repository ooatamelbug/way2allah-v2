#!/usr/bin/env python3
"""Way2Allah local production-like validation harness (lab only: 127.0.0.1 ports of disposable containers)."""
import hashlib, json, os, socket, subprocess, sys, threading, time

LAB = os.path.dirname(os.path.abspath(__file__))
DBPW = open(os.path.join(LAB, '.dbpw')).read().strip()
PORTS = {'fcgid': 18081, 'cgi': 18082}
RESULTS = []


def sh(*args, check=False):
    p = subprocess.run(list(args), capture_output=True, text=True)
    if check and p.returncode != 0:
        raise RuntimeError(p.stderr)
    return p.stdout


def dexec(*cmd, user='w2a'):
    return sh('docker', 'exec', '-u', user, 'w2a-lab-web', *cmd)


def sql(q):
    out = sh('docker', 'exec', 'w2a-lab-db', 'mariadb', '-uroot', '-p' + DBPW, 'w2a_lab', '-N', '-B', '-e', q)
    return [line.split('\t') for line in out.strip().split('\n') if line]


def counter(table, ident, col):
    return int(sql(f'SELECT {col} FROM {table} WHERE id={ident}')[0][0])


def set_env(pairs):
    for k, v in pairs.items():
        dexec('sh', '-c', f"grep -q '^{k}=' /app/.env && sed -i 's#^{k}=.*#{k}={v}#' /app/.env || echo '{k}={v}' >> /app/.env")


def monitor():
    try:
        return json.loads(dexec('php', '/app/artisan', 'downloads:monitor', '--json'))
    except Exception as e:  # noqa
        return {'error': str(e)}


def slots():
    s = monitor().get('checks', {}).get('slots', {})
    return {k: s.get(k) for k in ('status', 'occupied', 'limit', 'visibility', 'oldest_held_seconds')} | {'holders': [h['pid'] for h in s.get('holders', [])]}


def procs():
    out = dexec('ps', '-eo', 'pid,rss,pcpu,args', user='root')
    php = [l.split(None, 3) for l in out.strip().split('\n')[1:] if 'php-cgi' in l or '/usr/lib/cgi-bin/php8.3' in l]
    apache = [l for l in out.strip().split('\n')[1:] if 'apache2' in l]
    return {'php_cgi': len(php), 'php_rss_mib': round(sum(int(p[1]) for p in php) / 1024, 1),
            'php_rss_avg_mib': round(sum(int(p[1]) for p in php) / 1024 / len(php), 1) if php else 0, 'apache': len(apache)}


def stats():
    out = sh('docker', 'stats', '--no-stream', '--format', '{{.CPUPerc}} {{.MemUsage}}', 'w2a-lab-web').strip()
    return out


def source_log():
    return sh('docker', 'exec', 'w2a-lab-source', 'cat', '/tmp/source.log').strip().split('\n')


def events():
    code = r'''$e=[];foreach(glob("/app/storage/app/download-telemetry/active/*.ndjson")?:[] as $f){foreach(file($f) as $l){$j=json_decode(substr($l,0),true);if(is_array($j)){$e[]=$j;}}}echo json_encode($e);'''
    try:
        return json.loads(dexec('php', '-r', code) or '[]')
    except Exception:
        return []


def wait_slots_free(limit_s=60.0):
    t0 = time.time()
    while time.time() - t0 < limit_s:
        s = slots()
        if s['occupied'] == 0 and s['visibility'] in ('probe_visible', 'no_slot_files'):
            return round(time.time() - t0, 2)
        time.sleep(0.25)
    return None


def request(port, path, method='GET', read_limit=None, read_delay=0.0, rcvbuf=None, timeout=120, headers=None):
    """Raw HTTP/1.0 request. read_limit: disconnect after N body bytes. read_delay: sleep per 4 KiB read (slow client)."""
    t0 = time.time()
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    if rcvbuf:
        s.setsockopt(socket.SOL_SOCKET, socket.SO_RCVBUF, rcvbuf)
    s.settimeout(timeout)
    s.connect(('127.0.0.1', port))
    extra = ''.join(f'{k}: {v}\r\n' for k, v in (headers or {}).items())
    s.sendall(f'{method} {path} HTTP/1.0\r\nHost: lab.local\r\nUser-Agent: W2ALab/1.0\r\n{extra}\r\n'.encode())
    raw = b''
    head_end = -1
    body_len = 0
    h = hashlib.sha256()
    try:
        while True:
            chunk = s.recv(4096 if read_delay else 65536)
            if not chunk:
                break
            if head_end < 0:
                raw += chunk
                head_end = raw.find(b'\r\n\r\n')
                if head_end >= 0:
                    body = raw[head_end + 4:]
                    raw = raw[:head_end]
                    h.update(body)
                    body_len += len(body)
            else:
                h.update(chunk)
                body_len += len(chunk)
            if read_limit is not None and head_end >= 0 and body_len >= read_limit:
                break
            if read_delay:
                time.sleep(read_delay)
    except socket.timeout:
        pass
    s.close()
    lines = raw.decode('latin-1').split('\r\n')
    status = int(lines[0].split()[1]) if lines and len(lines[0].split()) > 1 else 0
    hdrs = {}
    for l in lines[1:]:
        if ':' in l:
            k, v = l.split(':', 1)
            hdrs[k.strip().lower()] = v.strip()
    return {'status': status, 'headers': hdrs, 'bytes': body_len, 'sha256': h.hexdigest(), 'seconds': round(time.time() - t0, 3)}


def record(section, name, expected, actual, ok, **extra):
    RESULTS.append({'section': section, 'name': name, 'expected': expected, 'actual': actual, 'ok': bool(ok), **extra})
    print(f"[{'PASS' if ok else 'FAIL'}] {section} / {name}: {actual}", flush=True)


def file_sha(path):
    return dexec('sha256sum', path).split()[0]


def upstream_body_sha(n):
    chunk = (b'W2A-LAB-BYTES-0123456789abcdef' * 546) + b'xyzw'
    h = hashlib.sha256()
    while n > 0:
        piece = chunk[:min(16384, n)]
        h.update(piece)
        n -= len(piece)
    return h.hexdigest()


def save():
    with open(os.path.join(LAB, 'results.json'), 'w') as f:
        json.dump(RESULTS, f, indent=1, ensure_ascii=False)


if __name__ == '__main__':
    import importlib
    mod = importlib.import_module(sys.argv[1])
    mod.run(sys.modules[__name__])
    save()
