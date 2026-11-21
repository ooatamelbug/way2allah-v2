"""Section 5: identical download responses, counters and slot release under every telemetry / archive state, per handler."""
import threading, time

SCENARIOS = {
    'telemetry disabled': {'DOWNLOAD_TELEMETRY_SPOOL': 'off', 'DOWNLOAD_TELEMETRY_EVENTS': 'off', 'DOWNLOAD_REJECTIONS_ARCHIVE': 'false'},
    'telemetry enabled': {'DOWNLOAD_TELEMETRY_SPOOL': 'on', 'DOWNLOAD_TELEMETRY_EVENTS': 'on', 'DOWNLOAD_TELEMETRY_SPOOL_ROOT': '/app/storage/app/dt-enabled'},
    'spool unavailable (root is a file)': {'DOWNLOAD_TELEMETRY_SPOOL_ROOT': '/app/artisan'},
    'spool full (max_files reached)': {'DOWNLOAD_TELEMETRY_SPOOL_ROOT': '/app/storage/app/dt-full', 'DOWNLOAD_TELEMETRY_SPOOL_MAX_FILES': '1'},
    'breaker open': {'DOWNLOAD_TELEMETRY_SPOOL_ROOT': '/app/storage/app/dt-breaker', 'DOWNLOAD_TELEMETRY_SPOOL_MAX_FILES': '32'},
    'archive enabled (not run)': {'DOWNLOAD_TELEMETRY_SPOOL': 'off', 'DOWNLOAD_TELEMETRY_EVENTS': 'off', 'DOWNLOAD_REJECTIONS_ARCHIVE': 'true'},
}


def workload(L, port):
    out = {}
    items = {'local mp3': 101, 'remote 5 MiB (signed URL)': 201, 'missing file': 105, 'upstream html': 206}
    before = {k: L.counter('nuke_islamic_khotab', v, 'downcount') for k, v in items.items()}
    for k, v in items.items():
        out[f'GET {k}'] = L.request(port, f'/khotab-download-{v}.htm')
    out['HEAD local mp3'] = L.request(port, '/khotab-download-101.htm', method='HEAD')
    out['GET YouTube mirror'] = L.request(port, '/khotab-mirror-101-301.htm')
    # Capacity: hold the single slot with a slow transfer, then ask again → 503.
    L.set_env({'DOWNLOAD_CAPACITY_MAX_CONCURRENT': '1'})
    holder = threading.Thread(target=lambda: L.request(port, '/khotab-download-208.htm', read_limit=1572864, timeout=60))
    holder.start()
    time.sleep(3)
    out['GET at capacity'] = L.request(port, '/khotab-download-101.htm')
    holder.join()
    L.set_env({'DOWNLOAD_CAPACITY_MAX_CONCURRENT': '20'})
    disc = L.request(port, '/khotab-download-208.htm', read_limit=524288)
    freed = L.wait_slots_free(60)
    counters = {k: L.counter('nuke_islamic_khotab', v, 'downcount') - before[k] for k, v in items.items()}
    norm = {}
    for k, r in out.items():
        h = {hk: hv for hk, hv in r['headers'].items() if hk not in ('date', 'set-cookie', 'connection', 'transfer-encoding')}
        norm[k] = (r['status'], tuple(sorted(h.items())), r['sha256'] if r['status'] == 200 else None, r['bytes'] if r['status'] == 200 else None)
    return norm, counters, freed, disc['bytes']


def run(L):
    for mode, port in L.PORTS.items():
        baseline = None
        for name, env in SCENARIOS.items():
            L.set_env(env)
            if name.startswith('spool full'):
                L.dexec('sh', '-c', 'mkdir -p /app/storage/app/dt-full/quarantine && touch /app/storage/app/dt-full/quarantine/occupied.ndjson')
            if name == 'breaker open':
                L.dexec('sh', '-c', 'mkdir -p /app/storage/app/dt-breaker && touch -d "+1 hour" /app/storage/app/dt-breaker/breaker')
            norm, counters, freed, _ = workload(L, port)
            if baseline is None:
                baseline = (norm, counters)
            diffs = [k for k in norm if norm[k] != baseline[0].get(k)]
            L.record(f'isolation/{mode}', name, 'responses, counters and slot release identical to "telemetry disabled"',
                     f"diffs={diffs} counters={counters} slots_free_after_disconnect={freed}s statuses={ {k: v[0] for k, v in norm.items()} }",
                     diffs == [] and counters == baseline[1] and freed is not None)
        L.set_env({'DOWNLOAD_TELEMETRY_SPOOL': 'off', 'DOWNLOAD_TELEMETRY_EVENTS': 'off', 'DOWNLOAD_REJECTIONS_ARCHIVE': 'false',
                   'DOWNLOAD_TELEMETRY_SPOOL_ROOT': '/app/storage/app/download-telemetry', 'DOWNLOAD_TELEMETRY_SPOOL_MAX_FILES': '32'})
