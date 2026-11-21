# Download telemetry: Phase 3C local shadow validation

**Status:** local only. Nothing is enabled, scheduled or deployed. Every measurement below comes from PHP's CLI
development server (`php -S`, with worker processes) running the real HTTP kernel on a 4-CPU workstation, not from
PHP-CGI/FastCGI behind Apache.

- **Load:** load average was 1.0–2.2 for the benchmark run and about 6 for the first shadow runs. Absolute timings are
  indicative only; the off/on comparisons were alternated, so drift and load affected both modes.
- **Harness:** `tests/Support/Telemetry/ShadowServer.php`.
- **Tests:** `ShadowValidationTest`, `ShadowFailureTest`, and `ShadowBenchmarkTest` (opt-in, `W2A_SHADOW_BENCH=1`).

## 1. Shadow comparison (same workload, five configurations)

**Workload per configuration (35 requests against one server with 8 workers):**

- 6 sequential local downloads (2 MiB, incompressible);
- 2 remote downloads (300 kB, via a real local HTTP upstream with a signed query string);
- 1 HEAD;
- 1 missing file (404);
- 1 request with all 16 slots held (503);
- 24 concurrent downloads (8 in flight).

**Compared for every request:** status, all headers (minus the per-request `Date` and `Set-Cookie`), body SHA-256,
byte count. Also compared: counter deltas and slot release.

| Configuration | Responses vs disabled | Counter deltas (local, remote, missing) | Slots free after | Events written | Drops counted |
|---|---|---|---|---|---|
| disabled | baseline | 30, 2, 1 | yes | — | — |
| healthy spool | **identical** (35/35) | 30, 2, 1 | yes | 100 | 0 |
| spool full (`max_files` reached) | **identical** | 30, 2, 1 | yes | 0 | **100** (all) |
| breaker open | **identical** | 30, 2, 1 | yes | 0 | **100** (all) |
| failed ledger (spool full + `drops/` unwritable) | **identical** | 30, 2, 1 | yes | 0 | 0 recorded → `W2A_TELEMETRY_UNRECORDED_DROPS` in the server error log |

**Response differences explained:** none. Every status, header and body hash is identical across all five.

**The missing-file counter:** a GET for a missing file increments `downcount` once before its 404. That is existing,
legacy-compatible behaviour: the counter precedes the file check, as in `download_khotab()`. It is identical in every
configuration, telemetry on or off.

**Healthy run, events by type:**

| Event | Count |
|---|---|
| `download_accepted` | 33 |
| `transfer_started` | 32 |
| `transfer_finished` (`completed`) | 32 |
| `head_answered` | 1 |
| `source_open_failed` (`not_found`) | 1 |
| `capacity_rejected` | 1 |

**Database rollup of the healthy run (SQLite):**

- 1 batch applied in 27 ms, with no errors;
- metrics account for all 100 events;
- `transfer_finished:completed` = 32 events and **63,514,560 bytes**, exactly the bytes of the 200 responses;
- `download_ip_ua_hourly` totals 100 events;
- periods are reported as they really are: `day:provisional=1`, `hour:provisional=1`. Nothing is presented as final.

**Server-process figures (per configuration, whole workload):**

| Configuration | CPU s | Peak RSS (KiB) | Spool growth (bytes) | Wall s |
|---|---|---|---|---|
| disabled | 2.53 | 55,920 | 0 | 3.75 |
| healthy | 1.90 | 56,536 | 32,192 | 2.70 |
| spool full | 1.84 | 56,732 | 19,584 (ledger shards only) | 2.32 |
| breaker open | 1.71 | 56,732 | 19,584 (ledger shards only) | 1.96 |
| failed ledger | 1.76 | 56,736 | 0 | 2.11 |

The disabled run is first and includes the workers' warm-up (opcache, autoload), so its CPU and wall time are higher.
The controlled off/on comparison is in §2.

**Finding fixed in this phase:** before the fix, the failed-ledger run took **6.3 s and 4.1 CPU-s**. When no
drop-ledger shard could even be opened, every dropped event spent the whole 50 ms ledger budget retrying `open` and
`create`. That is about 150 ms of added latency per download, so telemetry failure did affect downloads.
`DropLedger::applyToAnyShard()` now gives up at once when a full pass opens no shard (contention still retries). The
in-memory fallback and the shutdown report are unchanged. Regression test: `ShadowFailureTest`, "fails fast".

## 2. Performance

### Recorder overhead (20,000 events each, one process, real spool)

| Case | µs/event mean | p50 | p99 |
|---|---|---|---|
| disabled | 0.10 | 0.10 | 0.17 |
| enabled (healthy spool) | 83 | 78 | 136 |
| breaker open (dropped and counted) | 59 | 57 | 98 |
| spool full (dropped and counted) | 167 | 149 | 287 |

A proxied download emits 3 events, about 0.25 ms per download when enabled, against a server CPU cost of about 40 ms
per 1 MiB download.

### Lock contention (8 processes × 5,000 events into one spool minute)

- **Throughput:** 24,400 events/s; 39,771 written and 229 dropped, a **0.57 %** drop rate, all `lock_timeout` at the
  2 ms budget, and all counted.
- **Latency:** p50 110 µs and p99 2.1 ms in the worst process.
- **Context:** this is far beyond realistic production rates, where events are bounded by about 3 per download and by
  the 400-slot pool. It matches the Phase 1 Configuration C characterisation.

### Downloads through the real server (1 MiB file, 4 workers, alternated off/on/off/on after warm-up)

| Clients / slots | Mode | req/s | p50 ms | p95 ms | p99 ms | Server CPU ms/req | Slot held p50 / p95 ms | Spool bytes/req | Drop rate |
|---|---|---|---|---|---|---|---|---|---|
| 4 / 16 | off | 41.0, 42.8 | 78.9, 78.4 | 147, 138 | 208, 144 | 40.4, 40.5 | — | 0 | — |
| 4 / 16 | on | 41.1, 39.9 | 78.2, 78.5 | 155, 147 | 199, 203 | 41.7, 42.2 | 2 / 6 | 965 | 0 |
| 16 / 8 | off | 57.5, 60.8 | 238, 237 | 435, 394 | 521, 580 | 43.8, 44.6 | — | 0 | — |
| 16 / 8 | on | 57.5, 58.5 | 244, 246 | 361, 392 | 498, 534 | 44.6, 44.5 | 2 / 6 | 965 | 0 |

- **Throughput and latency:** indistinguishable within run-to-run noise.
- **CPU:** server CPU per request rose by about 1.5 ms (≈ 3–4 %) at 4 clients, with no measurable difference at 16.
- **Spool growth:** 965 bytes per download (3 events), about 321 bytes per event.
- **Slot occupancy:** "slot held" is the transfer duration recorded in `transfer_finished.d`; for a local 1 MiB file it
  is 2 ms p50.
- **Stress limit:** the 16-client stress run never reached the 8-slot limit (0 rejections), because the built-in
  server ran 4 workers and so at most 4 requests were inside PHP at once. Capacity exhaustion itself is validated
  functionally (§1, and the recorder and capacity tests).

### Rollup and monitoring

| Measurement | SQLite file | MariaDB 10.11 (disposable container) |
|---|---|---|
| Rollup of 100,000 events (5 one-minute batches, 6,000 IP/UA rows) | 4.8–5.0 s, about 20,000–21,000 events/s | 7.1 s, about 14,000 events/s |
| Spool size | 25.6 MB, 256 bytes/event (synthetic) | same |
| Peak PHP memory (whole test process) | 54.5 MiB | 54.5 MiB |
| `downloads:monitor` full report, populated spool and DB, 400 slot files | p50 7–15 ms | p50 45 ms (max 59) |

## 3. Failure isolation

| Scenario | Evidence | Download availability |
|---|---|---|
| Source timeout | `DownloadHttpIntegrationTest` (stall → `stalled`, short body, slot freed) | unaffected |
| Client disconnect | `DownloadHttpIntegrationTest` (`client_aborted`, shutdown fallback, 5/5) | unaffected |
| Capacity exhaustion | §1 (503 identical with telemetry on/off), `DownloadRecorderTest` | identical |
| Spool exhaustion | §1, `ShadowFailureTest` (sealer and rollup stopped: bound reached, 9/9 drops counted, recovery after seal + rollup) | identical |
| Ledger failure | §1 (unrecorded drops reported to the error log), fast-fail regression test | identical, **after the fix** |
| Database unavailable (rollup) | `ShadowFailureTest`: sanitized error, batches stay sealed, applied exactly once when the DB returns | unaffected (downloads never touch the rollup) |
| Database unavailable (download path) | `ShadowFailureTest`: identical 500 with telemetry on/off, no slot leaked | identical (the item lookup needs the DB) |
| Rollup stopped / sealer stopped | `ShadowFailureTest` (above) | unaffected |
| Process termination (SIGKILL mid-transfer) | see below | unaffected |
| Late batches | `RollupTest` ("late batch … corrected"), `CoverageAndLateEvidenceTest` | n/a |
| Unsupported event versions | `RollupTest` ("quarantines … unsupported (newer) schema version") | n/a |
| Rollup crash mid-run | `RollupCrashTest`, `MariaDbIntegrationTest` (exactly once) | n/a |

**Process termination, in detail (`ShadowFailureTest`):**

- **Result:** the kernel freed the slot, the spool parses cleanly (0 malformed), and the rollup shows the
  unterminated transfer as `transfer_started − transfer_finished = 1`.
- **No terminal event:** SIGKILL runs no PHP code, so the transfer has none. Operators read "started without finished"
  as interrupted transfers.
- **Built-in server artefact:** PHP's built-in server does not survive losing a worker, so the test restarts it.
  Production PHP-CGI uses one process per request.

## 4. Privacy review

**Telemetry-owned leaks found and fixed:**

1. **Rollup errors embedded exception messages.** These went into the run report and command output, and so into cron
   mail. A `QueryException` message contains the SQL **with its bound values** (IP addresses, User-Agent hashes), and a
   driver message can quote a duplicate key.
   - `RollupRunner::describe()` now reports only the class, the code and the SQLSTATE.
   - The one deliberate abort written by this code base (`RollupAborted`, "advisory lock ownership lost") keeps its
     fixed text.
   - Test: `TelemetryPrivacyTest`.
2. **Stream read exceptions** were logged with their message, which can quote the source URL, including a signed
   query string. `ProxiedDownloadStreamer::redactMessage()` now redacts every URL in it. (The link itself has been
   redacted since 3B.1.)

**Verified clean:**

- Spool events: no URL, path or query (the source host only), no cookies, no `Authorization` header, no raw
  User-Agent, no `X-Forwarded-For` value. This is tested, including a signed query string and cookie and authorization
  headers.
- Telemetry tables: a raw IP (by design, with 14-day retention once pruning is on) and the keyed User-Agent hash; no
  URLs.
- Monitor output: slot-holder PIDs and aggregates, no IPs. The rejection report: aggregates only.
- Slow-query log: SQL text without bindings. Request-performance log: the route pattern only (no query string), no
  User-Agent, no IP.
- `abort()` 404/500 responses are not reported by Laravel. Locally `zend.exception_ignore_args=On`, so stack traces
  carry no arguments.

**Separate, unrelated or pre-existing issues (reported, NOT changed):**

- **L1 — `download_rejections` rows** store:
  - `requested_url` (full URL, including our own query string);
  - `resolved_download_url` (the **full source link, including any signed query string**);
  - `referer` (full, up to 1,024 chars);
  - `user_agent` (raw, 512);
  - `forwarded_for` (raw).

  These records are owner-designated audit evidence, and changing what is written needs a separate decision.
- **L2 — `DOWNLOAD_CAPACITY_FULL` warning** logs `client_ip`. This is operational and throttled.
- **L3 — `AlbumZipService`** logs the exception message on a ZIP build failure, which may include filesystem paths
  (Gallery phase).
- **L4 — exception traces:** if production PHP has `zend.exception_ignore_args=Off`, a stack trace of an unexpected
  exception could include call arguments such as the source link. The production `php.ini` is unverified.

## 5. Rejection-log report

`php artisan downloads:rejections-report [--days=30] [--exact] [--json]` is read-only. It never deletes, exports or
modifies a row, and the table is not part of `downloads:telemetry-prune`. `NonEmptyTableGuard` is unchanged.

| Output | Query |
|---|---|
| Oldest / newest | `MIN`/`MAX(created_at)` from the index ends |
| Daily growth | zero-filled rows per UTC day for up to 90 days; a covering range scan of `idx_dr_created` |
| Total | on MariaDB, the `information_schema.tables` estimate by default; `--exact` does a `COUNT(*)` over the index (cost grows with the table; run it off-peak) |
| Storage | `data_length + index_length`, bytes per row, projected rows and bytes per year |

It was not run against production (no access), so the actual figures are for the owner to collect.

**Archival options for later (none approved):**

1. A monthly `SELECT … INTO OUTFILE`, or `mysqldump --where="created_at < …"`, to compressed off-host storage before
   any deletion.
2. A partitioned archive table, `download_rejections_archive`, with rows moved by `created_at` range, keeping the live
   table small.
3. Summarising old rows into daily aggregates (counts by module, resource and reason) and keeping the raw rows for N
   days.
4. Minimising the stored fields (L1), for example dropping the source query string and truncating the referer to its
   origin. Applies to future rows only.

## 6. Production readiness

See `download-telemetry-production-readiness.md` §8.
