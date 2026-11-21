"""Section 3/4: client disconnect, slow client, interrupted transfers — slot release, upstream close, telemetry outcome."""
import time


def terminal_for(L, since_t, item):
    evs = [e for e in L.events() if e.get('t', 0) >= since_t and e.get('rid') == item]
    fin = [e for e in evs if e['ev'] in ('transfer_finished', 'source_open_failed', 'source_rejected')]
    return [f"{e['ev']}:{e.get('st', '')}:{e.get('k', '')}:b={e.get('b', '')}" for e in fin], len(fin)


def source_close_for(L, path_fragment, since_line):
    lines = L.source_log()[since_line:]
    return [l for l in lines if 'CLOSE' in l and path_fragment in l]


def run(L, label=''):
    L.set_env({'DOWNLOAD_TELEMETRY_SPOOL': 'on', 'DOWNLOAD_TELEMETRY_EVENTS': 'on', 'DOWNLOAD_TELEMETRY_SPOOL_DISK_RESERVE_BYTES': '0',
               'DOWNLOAD_CAPACITY_MAX_CONCURRENT': '20'})
    for mode, port in L.PORTS.items():
        S = f'disconnect/{mode}{label}'
        cases = [
            ('client leaves a slow remote transfer (512 KiB of 100 MiB)', 208, dict(read_limit=524288), '/slow/104857600'),
            ('client leaves a fast local transfer (64 KiB of 5 MiB, small receive buffer)', 101, dict(read_limit=65536, rcvbuf=4096), None),
            ('client leaves while the upstream is STALLED (after 512 KiB)', 203, dict(read_limit=524288), '/stall/'),
            ('slow client (8 KiB rcvbuf, 4 KiB / 50 ms) leaves after 256 KiB', 208, dict(read_limit=262144, read_delay=0.05, rcvbuf=8192), '/slow/104857600'),
        ]
        for name, item, kw, upstream in cases:
            assert L.wait_slots_free(30) is not None
            log_mark = len(L.source_log())
            t0 = time.time()
            r = L.request(port, f'/khotab-download-{item}.htm', timeout=60, **kw)
            t_close = time.time()
            held = L.slots()
            freed = L.wait_slots_free(90)
            time.sleep(1.0)
            terms, nterm = terminal_for(L, t0 - 1, item)
            closes = source_close_for(L, upstream, log_mark) if upstream else ['(local file)']
            L.record(S, name, 'slot freed promptly (stall: within idle timeout); exactly one terminal event; upstream socket closed',
                     f"read {r['bytes']}B in {r['seconds']}s; held right after close: occupied={held['occupied']}; slot free after {freed}s; "
                     f"terminal={terms}; upstream={[c.split(' ', 1)[1] for c in closes][-1:]}",
                     freed is not None and nterm == 1 and (upstream is None or closes),
                     slot_free_seconds=freed, terminal=terms, upstream=closes[-1:])
