"""Section 3: download behaviour against the compatibility contract, per handler, telemetry at its shipped default (off)."""
import threading, time


def run(L):
    L.set_env({'DOWNLOAD_TELEMETRY_SPOOL': 'off', 'DOWNLOAD_TELEMETRY_EVENTS': 'off', 'DOWNLOAD_CAPACITY_MAX_CONCURRENT': '20'})
    for mode, port in L.PORTS.items():
        S = f'behaviour/{mode}'

        # Local files: GET, Content-Type allowlist, Content-Disposition, exact bytes, counter +1.
        for item, name, ctype in [(101, 'lecture.mp3', 'audio/mpeg'), (102, 'lecture.mp4', 'video/mp4'),
                                  (103, 'transcript.pdf', 'application/pdf'), (104, 'archive.rar', 'application/octet-stream')]:
            before = L.counter('nuke_islamic_khotab', item, 'downcount')
            r = L.request(port, f'/khotab-download-{item}.htm')
            sha = L.file_sha(f'/data/{name}')
            after = L.counter('nuke_islamic_khotab', item, 'downcount')
            h = r['headers']
            L.record(S, f'GET local {name}', f'200 {ctype} attachment nosniff exact-bytes +1',
                     f"{r['status']} {h.get('content-type')} {h.get('content-disposition')} {h.get('x-content-type-options')} len={h.get('content-length')} bytes={r['bytes']} +{after - before}",
                     r['status'] == 200 and h.get('content-type') == ctype and h.get('content-disposition', '').startswith('attachment')
                     and h.get('x-content-type-options') == 'nosniff' and r['sha256'] == sha and after - before == 1)

        # HEAD: same headers, no body, no Content-Length, no counter, no slot.
        before = L.counter('nuke_islamic_khotab', 101, 'downcount')
        r = L.request(port, '/khotab-download-101.htm', method='HEAD')
        L.record(S, 'HEAD local mp3', '200 audio/mpeg attachment, no body, no Content-Length, counter unchanged',
                 f"{r['status']} {r['headers'].get('content-type')} cl={r['headers'].get('content-length')} bytes={r['bytes']} +{L.counter('nuke_islamic_khotab', 101, 'downcount') - before}",
                 r['status'] == 200 and r['headers'].get('content-type') == 'audio/mpeg' and 'content-length' not in r['headers']
                 and r['bytes'] == 0 and L.counter('nuke_islamic_khotab', 101, 'downcount') == before)

        # Mirrors: local mirror counts hits; YouTube / SoundCloud → 302 (GET +1, HEAD 0); look-alike host → proxied → 500.
        for mirror, label, expect_status, expect_loc in [(304, 'local mirror', 200, None),
                                                         (301, 'YouTube mirror', 302, 'https://www.youtube.com/watch?v=abc123&list=PL9'),
                                                         (302, 'SoundCloud mirror', 302, 'https://soundcloud.com/way2allah/lecture-1'),
                                                         (303, 'look-alike HTML mirror', 500, None)]:
            b = L.counter('nuke_islamic_mirror', mirror, 'hits')
            g = L.request(port, f'/khotab-mirror-101-{mirror}.htm')
            a = L.counter('nuke_islamic_mirror', mirror, 'hits')
            hd = L.request(port, f'/khotab-mirror-101-{mirror}.htm', method='HEAD')
            a2 = L.counter('nuke_islamic_mirror', mirror, 'hits')
            loc_ok = expect_loc is None or (g['headers'].get('location') == expect_loc and hd['headers'].get('location') == expect_loc
                                            and 'no-store' in g['headers'].get('cache-control', ''))
            L.record(S, f'{label} GET+HEAD', f'{expect_status} {expect_loc or ""} GET +1 hits, HEAD +0',
                     f"GET {g['status']} {g['headers'].get('location', '')} +{a - b}; HEAD {hd['status']} +{a2 - a}",
                     g['status'] == expect_status and hd['status'] == (expect_status if expect_status != 500 else 200) and loc_ok
                     and a - b == 1 and a2 == a)

        # Missing local file: 404 (counter +1 before the check — legacy-compatible).
        b = L.counter('nuke_islamic_khotab', 105, 'downcount')
        r = L.request(port, '/khotab-download-105.htm')
        L.record(S, 'missing local file', '404, downcount +1 (legacy)', f"{r['status']} +{L.counter('nuke_islamic_khotab', 105, 'downcount') - b}",
                 r['status'] == 404 and L.counter('nuke_islamic_khotab', 105, 'downcount') - b == 1)

        # Remote sources.
        r = L.request(port, '/khotab-download-201.htm')
        L.record(S, 'remote fast (signed URL, upstream says x-upstream-says-this)', '200 audio/mpeg CL=5242880 exact bytes',
                 f"{r['status']} {r['headers'].get('content-type')} cl={r['headers'].get('content-length')} bytes={r['bytes']}",
                 r['status'] == 200 and r['headers'].get('content-type') == 'audio/mpeg' and r['headers'].get('content-length') == '5242880'
                 and r['sha256'] == L.upstream_body_sha(5242880))
        for item, label in [(206, 'upstream text/html'), (207, 'upstream 404')]:
            r = L.request(port, f'/khotab-download-{item}.htm')
            L.record(S, label, '500 before any download header', f"{r['status']} disp={r['headers'].get('content-disposition')}",
                     r['status'] == 500 and 'content-disposition' not in r['headers'])

        # Stalled upstream (512 KiB then silence; idle timeout 10 s): short body, ended within ~10-25 s, slot freed.
        r = L.request(port, '/khotab-download-203.htm', timeout=90)
        free = L.wait_slots_free(30)
        L.record(S, 'stalled upstream', '200, CL=10485760, body 524288, ends after idle timeout, slot freed',
                 f"{r['status']} cl={r['headers'].get('content-length')} bytes={r['bytes']} in {r['seconds']}s; slots free after {free}s",
                 r['status'] == 200 and r['bytes'] == 524288 and 9 <= r['seconds'] <= 40 and free is not None)

        # Truncated upstream (clean close after 1 MiB) and reset (RST after 1 MiB).
        for item, label in [(204, 'upstream closes early (truncated)'), (205, 'upstream reset (read failure)')]:
            r = L.request(port, f'/khotab-download-{item}.htm', timeout=60)
            free = L.wait_slots_free(20)
            L.record(S, label, '200, CL=10485760, short body ~1 MiB, slot freed',
                     f"{r['status']} cl={r['headers'].get('content-length')} bytes={r['bytes']} in {r['seconds']}s; slots free after {free}s",
                     r['status'] == 200 and 0 < r['bytes'] < 10485760 and free is not None)

        # Slow client: reads 4 KiB every 20 ms (≈200 KiB/s) from a 2 MiB upstream dripping at 64 KiB/s → completes.
        r = L.request(port, '/khotab-download-202.htm', read_delay=0.02, rcvbuf=8192, timeout=120)
        L.record(S, 'slow client on slow upstream', '200, all 2097152 bytes, exact', f"{r['status']} bytes={r['bytes']} in {r['seconds']}s",
                 r['status'] == 200 and r['bytes'] == 2097152 and r['sha256'] == L.upstream_body_sha(2097152))
        free = L.wait_slots_free(10)
        L.record(S, 'slots after behaviour block', 'occupied 0 with evidence', str(L.slots()), free is not None)
