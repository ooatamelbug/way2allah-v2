"""Section 4: bounded, gradually increasing concurrency per handler. Each client holds a slow transfer ~12 s, then leaves."""
import socket, threading, time

HOLD = 12.0
RAMP = 50.0


def client(port, path, out, idx, hold=None):
    rec = {'status': 0, 'bytes': 0}
    try:
        s = socket.create_connection(('127.0.0.1', port), timeout=30)
        s.sendall(f'GET {path} HTTP/1.0\r\nHost: lab.local\r\nUser-Agent: W2ALab-conc/1.0\r\n\r\n'.encode())
        deadline = time.time() + (hold or HOLD)
        buf = b''
        s.settimeout(2)
        while time.time() < deadline:
            try:
                d = s.recv(65536)
            except socket.timeout:
                continue
            if not d:
                break
            if rec['status'] == 0:
                buf += d
                if b'\r\n' in buf:
                    rec['status'] = int(buf.split(b' ', 2)[1])
            rec['bytes'] += len(d)
        s.close()
    except Exception as e:  # noqa
        rec['error'] = type(e).__name__
    out[idx] = rec


def run(L, levels=(10, 25, 50, 100, 140), modes=None, ramp=None, hold=None):
    ramp = ramp or RAMP
    hold = hold or HOLD
    L.set_env({'DOWNLOAD_TELEMETRY_SPOOL': 'on', 'DOWNLOAD_TELEMETRY_EVENTS': 'on'})
    for mode, port in (modes or L.PORTS).items():
        for n in levels:
            limit = max(1, n - 5)
            L.set_env({'DOWNLOAD_CAPACITY_MAX_CONCURRENT': str(limit)})
            assert L.wait_slots_free(60) is not None
            rej0 = int(L.sql('SELECT COUNT(*) FROM download_rejections')[0][0])
            out = [None] * n
            threads = [threading.Thread(target=client, args=(port, '/khotab-download-208.htm', out, i, hold)) for i in range(n)]
            t0 = time.time()
            for i, t in enumerate(threads):
                t.start()
                time.sleep(1.0 / ramp)
            time.sleep(max(0.0, n / ramp + min(10.0, hold / 2) - (time.time() - t0)))   # sample once everyone has connected
            sl, pr, st = L.slots(), L.procs(), L.stats()
            for t in threads:
                t.join()
            closed = time.time()
            freed = L.wait_slots_free(120)
            rej = int(L.sql('SELECT COUNT(*) FROM download_rejections')[0][0]) - rej0
            ok = sum(1 for r in out if r and r['status'] == 200)
            busy = sum(1 for r in out if r and r['status'] == 503)
            other = n - ok - busy
            good = ok == limit and busy == n - limit and rej == n - limit and sl['occupied'] == limit and freed is not None
            L.record(f'concurrency/{mode}', f'{n} clients, limit {limit} (ramp {ramp:g}/s, hold {hold:g}s)',
                     f'{limit} streaming, {n - limit} × 503 (rows), occupancy {limit}, all slots free after disconnect',
                     f"200={ok} 503={busy} other={other} rejection_rows={rej} occupied_mid={sl['occupied']} visibility={sl['visibility']} "
                     f"php_cgi={pr['php_cgi']} php_rss={pr['php_rss_mib']}MiB (avg {pr['php_rss_avg_mib']}) container={st} freed_after={freed}s",
                     good, clients=n, limit=limit, ok200=ok, busy503=busy, other=other, rejection_rows=rej, occupied_mid=sl['occupied'],
                     php_cgi=pr['php_cgi'], php_rss_mib=pr['php_rss_mib'], container_stats=st, freed_after=freed)
            mem = st.split()[1] if st else '0MiB'
            used = float(mem.replace('GiB', '')) * 1024 if 'GiB' in mem else float(mem.replace('MiB', '') or 0)
            if used > 3300:
                L.record(f'concurrency/{mode}', 'escalation stopped', 'container memory < 3.3 GiB', f'{used:.0f} MiB at {n} clients', True)
                break
