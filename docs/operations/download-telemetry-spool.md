# Download telemetry — Phase 1: the bounded local spool

Status: implemented locally, **off by default** (`DOWNLOAD_TELEMETRY_SPOOL=off`), not called from any download path
yet, not deployed. Phase 2 (database aggregation) will consume the sealed batches.

## What it is

A local, bounded, append-only store of telemetry events (one JSON object per line, schema v1) that the download
hot path can write to without touching the database or the network, and without ever waiting longer than a
configured budget. Code: `app/Support/Downloads/Telemetry/`.

```
storage/app/download-telemetry/
  active/{YYYYmmddHHMM}.ndjson      the minute being written (UTC)
  sealed/{batch_id}.ndjson          finished, immutable batches — batch_id = {minute}-{sealMicros}-{8 hex}
  quarantine/{batch_id}.ndjson      batches containing a newer schema version (kept, never misread)
  drops/shard-NN.bin                fixed-size, sharded drop ledger (data-loss indicator)
  health.bin                        60-slot ring of health faults (breaker input)
  create.lock  seal.lock  breaker   lock files and the circuit-breaker marker (empty files)
```

## Event schema v1

`TelemetryEvent`: required `t` (unix time), `ev` (one of 12 event types), `mod`; optional typed fields only
(unknown fields are rejected). Strings are cut to their byte limit; invalid UTF-8 is replaced; a line never exceeds
2,048 bytes and always begins `{"v":1,`. A line is classified as `ok`, `malformed` (incl. a torn fragment) or
`unsupported` (newer version).

Identity fields (owner correction B): `ip` is one IP address as Laravel resolved it; `uah` is a keyed hash of the
User-Agent. An `ip`+`uah` pair is a distinct IP/User-Agent combination — not a person, not a "unique user".

## Writer / sealer protocol

Both lock the **same inode** — the open spool file — with `flock(LOCK_EX)`.

Writer (`SpoolWriter::write`, never throws, returns WRITTEN / DROPPED / DISABLED):
1. Open `active/{minute}` with `r+b` (never creates). If missing, create it under `create.lock`: re-check, enforce
   `max_files` (active + sealed + quarantine) and the disk reserve, then `x+b`.
2. `flock` within `lock_budget_us` (non-blocking attempts + 50 µs sleeps) — else drop `lock_timeout`.
3. Under the lock, check the path still names this inode (`fstat` vs `stat`); if not, the file was sealed: release,
   reopen, retry (at most `rotation_retries`, else drop `rotation_retries`).
4. If the file does not end in `\n` (a writer was killed mid-write), write `\n` first — the fragment becomes one
   isolated malformed line; the repair is counted.
5. Write only if the file stays within `file_max_bytes` (else drop `file_full`). A short write is rolled back with
   `ftruncate` to the previous size and dropped as `io_error` — I/O failure never leaves a torn record.

Sealer (`SpoolSealer::seal`, one at a time via `seal.lock`): for each file whose minute ended at least
`seal_grace_minutes` ago, queue for the file's lock (`FileLock::acquireQueued`: a blocking `flock` bounded by
SIGALRM, so writers cannot starve it), verify the path still names the inode, `fsync`, and rename it to
`sealed/{batch_id}` **while holding the lock**. A batch with any newer-version record moves to `quarantine/`.

Guarantees (each proved by a test, see below): a writer cannot append to a sealed batch; a sealer cannot rename a
file while a writer holds it; a writer that opened a file before it was sealed detects it under the lock and
retries; an event is written at most once (retries happen only before writing; failed writes are rolled back and
dropped, never retried); partial writes are isolated and reported.

## Capacity bound (writer-enforced; no scheduler needed)

- Files are created only under `create.lock`, after counting `active` + `sealed` + `quarantine` → at most
  `max_files` files. Sealing and quarantining only rename (no new files).
- A file grows only under its lock and only if the result stays ≤ `file_max_bytes`.
- Hence spool files ≤ `max_files × file_max_bytes` (defaults: 32 × 32 MiB = 1 GiB), plus the fixed-size ledger
  (shards × (64 + 40 × slots) bytes; default 6.2 MiB), `health.bin` (480 bytes) and three empty lock/marker files.
- Sustained rate within one minute file: `file_max_bytes / 60 s / event size` — ≈ 1,690 events/s at 32 MiB and
  ~331-byte events (≈ 420/s at 8 MiB). Above that, the rest of the minute is dropped and counted (`file_full`).
- Backlog if the sealer/consumer stops: `bound / (rate × event size × 60)` minutes — ≈ 54 min at 1,000 events/s,
  ≈ 160 h at 13 events/s (the observed daily average of capacity rejections), then new minutes are refused
  (`file_count`), counted, and the bound holds.
- Defaults chosen by the Phase 1 performance validation (configurations A–D, 1,000 and 5,000 events/s, 3 × 60 s each).

## Drop accounting (Phase 1.1: sharded, per-event durable)

Every drop is recorded synchronously, before `write()` returns, in `drops/shard-NN.bin` × `ledger_shards` (16):
each shard is pre-allocated once (64-byte header + one 40-byte slot per minute, 7-day ring) and only updated in
place, so it keeps working on a full disk and at full spool capacity. A drop goes to whichever shard can be locked
first (non-blocking sweep from a per-process start), bounded by `ledger_lock_budget_us` (50 ms). Slot: minute,
dropped events/bytes, reasons, torn records repaired, slow writes, health faults, max latency, flags, CRC32.
Fixed size: shards × (64 + 40 × slots) — 6.2 MiB by default.

Fallback, only if no shard can be updated within the budget: the delta is kept in process memory, merged into the
next successful update, flushed once more at process shutdown, and whatever still fails is written to PHP's error
log as `W2A_TELEMETRY_UNRECORDED_DROPS events=N minutes=… pid=…` (an external record on another path).

| Failure | What happens to drop counts | Indicator |
|---|---|---|
| Normal process exit | Exact: recorded before `write()` returns; pending deltas flushed at shutdown | — |
| Shard locks contended | Exact: another shard is used; only after 50 ms does the fallback apply | `ledger_failed` stat (bench) |
| Counter files cannot be updated | Held in memory, retried, flushed at shutdown; else reported | error-log line; `unrecorded()` |
| Filesystem unavailable | As above (nothing on that filesystem can be written) | error-log line; Phase 2 health probe |
| SIGTERM / SIGKILL | At most ONE drop per killed process (decided, not yet written) is lost; a torn slot write is detected | CRC → minute marked uncertain; the irreducible ≤ 1 loss leaves no trace in the spool |
| Power loss / reboot | Page-cache writes not yet on disk may be lost | boot id change → minutes between the previous boot's last update and the new boot marked uncertain |
| Ring overwrite (> 7 days unread) | Old counts replaced | `overwritten_events` |

`SpoolStatus::collect()` / `downloads:telemetry-spool-status` report `accounting_exact` and, when false,
`accounting_uncertainty` (torn slots, reboot windows, unreadable shards, overwritten events, unrecorded or pending
drops). Counts in an uncertain minute are lower bounds — never presented as exact.

**What `accounting_exact` means — and does not mean.** It means the drop counters that *were recorded* are
consistent under the documented operational assumptions: no torn slot, no reboot window, no unreadable shard, no
overwritten slot, no failed or pending update. It is **not** a guarantee that every attempted event has been
accounted for. **Blind spot:** a process killed (SIGKILL, or SIGTERM without a handler) between deciding to drop an
event and writing that drop to the ledger loses the record, at most one per killed process, and leaves no trace —
so a clean ledger does not prove that no pre-recording loss occurred. Monitoring and reports must use qualified
labels ("recorded drop counters: no known uncertainty"), never "exact" or "complete" on their own.

## Circuit breaker (Phase 1.1 classification policy)

| Class | Reasons | Feeds the breaker |
|---|---|---|
| Health | `io_error`; a successful write slower than `slow_write_us` | **yes** |
| Contention | `lock_timeout`, `rotation_retries` | no |
| Capacity | `file_full`, `file_count`, `disk_reserve` | no |
| Other | `invalid_event`, `breaker_open` | no |

Health faults are counted per minute in `health.bin` (60-slot ring, touched only when a health fault happens). At
`breaker_fault_threshold` (50) health faults in a minute, the `breaker` marker is touched with a future mtime
(`breaker_open_seconds`, 300 s); until then writes are dropped as `breaker_open` (one `stat` per event). It closes
by itself; no code or configuration change is needed. A full spool or heavy lock contention can never open it.

## Observability

- `php artisan downloads:telemetry-spool-status [--window=60] [--json]` — files/bytes per state vs the bound,
  oldest unsealed minute, breaker, drops by reason, torn repairs, slow writes, faults, max latency.
- `php artisan downloads:telemetry-seal [--all]` — seal finished minutes (not scheduled in Phase 1).
- Benchmark: `php tests/benchmarks/spool-writer-bench.php [seconds]`.

## Settings (`config/download_telemetry.php`)

| Variable | Default |
|---|---|
| `DOWNLOAD_TELEMETRY_SPOOL` | `off` |
| `DOWNLOAD_TELEMETRY_SPOOL_ROOT` | `storage/app/download-telemetry` |
| `DOWNLOAD_TELEMETRY_SPOOL_FILE_MAX_BYTES` | 32 MiB |
| `DOWNLOAD_TELEMETRY_SPOOL_MAX_FILES` | 32 |
| `DOWNLOAD_TELEMETRY_SPOOL_DISK_RESERVE_BYTES` | 2 GiB |
| `DOWNLOAD_TELEMETRY_WRITER_LOCK_BUDGET_US` | 2000 |
| `DOWNLOAD_TELEMETRY_LEDGER_LOCK_BUDGET_US` | 50000 |
| `DOWNLOAD_TELEMETRY_WRITER_ROTATION_RETRIES` | 3 |
| `DOWNLOAD_TELEMETRY_DROP_LEDGER_SHARDS` | 16 |
| `DOWNLOAD_TELEMETRY_DROP_LEDGER_SLOTS` | 10080 (7 days) |
| `DOWNLOAD_TELEMETRY_SLOW_WRITE_US` | 50000 |
| `DOWNLOAD_TELEMETRY_BREAKER_FAULTS` / `_BREAKER_OPEN_SECONDS` | 50 health faults / 300 s |
| `DOWNLOAD_TELEMETRY_SEAL_GRACE_MINUTES` | 2 |
| `DOWNLOAD_TELEMETRY_SEAL_LOCK_BUDGET_US` | 2000000 |

## Known limitations

- Power loss: PHP cannot fsync a directory, so the last renames/appends before a power cut may be lost (never
  duplicated).
- A writer SIGKILLed mid-write loses that one event; its fragment is isolated and counted as malformed.
- A process killed between deciding to drop an event and writing its ledger slot loses that one drop record
  (≤ 1 per killed process), with no trace inside the spool.
- A sealer crash after rename but before the quarantine check leaves a newer-version batch in `sealed/`; the
  consumer must inspect every batch (Phase 2 does).
- `acquireQueued` uses SIGALRM (whole seconds) when pcntl is available; without pcntl the sealer polls and can be
  deferred under sustained contention (safe; it retries next run).
