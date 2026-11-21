# Download telemetry — Phase 2: idempotent database aggregation

Status: implemented locally, **off by default** (`DOWNLOAD_TELEMETRY_AGGREGATE=off`), not scheduled, not deployed,
no download route emits events yet. Code: `app/Support/Downloads/Telemetry/Aggregation/`, schema:
`app/Support/Downloads/Telemetry/Database/TelemetrySchema.php`, migration
`2026_11_11_100000_create_download_telemetry_tables.php` (additive; `main`; skipped under tests).

## Tables (all new; no legacy table touched)

| Table | Key | Content |
|---|---|---|
| `download_telemetry_batches` | `batch_id` (+ unique `sha256`) | exactly-once ledger |
| `download_metrics_5m` / `download_metrics_daily` | time + module, event, outcome, variant, source host | additive counters, bytes, durations, histogram — exact for captured events |
| `download_ip_ua_hourly` | hour, IP, User-Agent hash | one row = one **IP/User-Agent combination** in the hour (never a person); exact resource set ≤ 64 |
| `download_distinct_sketches` | day, dimension | HyperLogLog p = 12 (4 KiB): **approximate** daily distinct IPs, IP/UA combinations, per-module IPs and resources |
| `download_period_status` | granularity (hour/day), start | quality state, counters, coverage, drops, reasons |
| `download_telemetry_coverage` | minute | evidence that the spool was enabled and writable in that minute |

Definitions: exact distinct **IP addresses** per hour = `COUNT(DISTINCT ip)`; exact **IP/User-Agent combinations** per
hour = `COUNT(*)` of `download_ip_ua_hourly` — both exact only for `final`/`corrected` hours. Daily distinct
figures are **estimates** (± ~1.6 %; measured 0.4–2.6 %). Nothing is ever labelled "unique users".

## Batch lifecycle and transaction boundary

```
active/{minute}  ──seal──▶  sealed/{batch_id}  ──[ BEGIN · INSERT ledger · merge aggregates · period updates · COMMIT ]──▶  done/{batch_id}  ──retention──▶ deleted
                                   │ unsupported version                                         │ already applied / same content
                                   └──▶ quarantine/                                               └──▶ done/ (nothing re-applied)
                                   │ same id, different content ──▶ quarantine/*.conflict-*.ndjson
```

- The ledger row and every aggregate change commit in **one transaction**; the file moves only after COMMIT.
- Duplicate `batch_id` or `sha256` aborts the transaction; nothing of the batch is applied twice.
- Crash before COMMIT → rollback, file still in `sealed/`, applied on the next run. Crash after COMMIT → ledger row
  exists, the next run only moves the file.
- One rollup at a time (`rollup.lock`, non-blocking); a second worker exits. Even without the lock, the ledger's
  unique keys let only one transaction through (tested with two processes).
- A failing batch ends that run's applying (it stays in `sealed/`), but finalization and retention still run.
- `done/`: deleted after `done_retention_hours` (24) and oldest-first beyond `done_max_bytes` (256 MiB); the ledger
  outlives the files, so a re-submitted old batch is recognised by its SHA-256 and never re-applied.
- `downloads:telemetry-replay {batch}` re-queues a `done/` batch only if the ledger has no record of its id or
  content (i.e. it was never applied).

## Period quality

| State | When |
|---|---|
| `provisional` | the period has not ended + grace (15 min hour / 2 h day), or batches for it still wait in the spool (up to 24 h) |
| `final` | ALL: 60/60 minutes with coverage evidence; no pending or quarantined batch; no recorded drop; no ledger uncertainty (torn slot, unreadable shard, reboot window) and the ledger ring still covers the minutes; no malformed line. Day: all 24 hours final/corrected |
| `incomplete` | otherwise, with reasons (`coverage_gap`, `spool_disabled`, `unprocessed_batches`, `quarantined_batch`, `dropped_events`, `ledger_uncertain`, `drop_ledger_wrapped`, `malformed_lines`, `late_drop_evidence`, `hours_incomplete`, `expired`). Counts are lower bounds |
| `corrected` | a late batch changed a final period (counts updated, `corrected_at`, `late_batches`) |

**Coverage = enabled AND operational (Phase 2.1).** The rollup records one row per minute it runs in (start and end of
every run). A minute counts as covered only when ALL hold: the spool is enabled; a probe file can be created, written,
fsynced and removed in `active/` (the writers' own directory) and every existing drop-ledger shard opens for writing;
the circuit breaker is closed; the spool is below its file limit and disk reserve. Otherwise the hour is incomplete
with `spool_disabled`, `spool_unwritable`, `breaker_open`, `spool_full`, or `coverage_gap` (no row — the scheduler did
not run). The scheduler merely running is never coverage. Limitation: the probe runs as the rollup's user; a writer
running as a different OS user with different permissions would not be detected (production runs both as `w2acp`).

**Coverage is a point-in-time observation.** Each coverage row records what the rollup's probe saw at the moment it
ran in that minute; it does not prove the spool stayed operational for the whole minute, or that every request in it
was observed. `final` therefore means: **no known coverage or accounting gap under the documented checks** — every
minute had a successful operational observation, and no drop, malformed line, quarantined batch, ledger uncertainty or
late evidence is known. It never guarantees that every request was observed (see also the pre-recording blind spot of
the drop ledger). All uncertainty and late-evidence handling below still applies after a period is final.

After finalization the rollup acknowledges the drop ledger up to the earliest still-provisional hour; the
acknowledged minute is also kept in `drops/ack`, so shards created later start from it. Every run also stamps the
current boot id into every existing ledger shard, so a reboot is recorded even for shards no drop touches afterwards.

**Late evidence (Phase 2.1).**

| Evidence | Detected | Effect |
|---|---|---|
| a later batch for a finalized period | when applied | `final` → `corrected` (counts updated) |
| drop/uncertainty in a finalized hour's ledger slots | re-check for 24 h after finalization | `incomplete` (`late_drop_evidence` / `ledger_uncertain`) |
| a drop recorded for an already-acknowledged minute, at ANY later time | ledger header flag (`late_min/max_minute`, `late_events`) | every final/corrected hour in the flagged range → `incomplete` (`late_drop_evidence`), its day too; flag cleared only after handling, and only if unchanged |
| a drop for a minute whose ring slot already holds a newer minute | ledger header (`late_unslotted_events`); the newer slot is never overwritten | same as above |

Limitations: the reopened range is conservative (the whole flagged min–max range); periods whose rows were pruned
cannot be reopened; a drop that could not be recorded at all (ledger unusable → error log only) and a drop lost before
recording (killed process) cannot reopen anything. `final` therefore means "no known gap", never proof of completeness.

As in Phase 1, recorded drop counts cannot reveal a drop lost before it was recorded (a killed process); `final`
means "no known gap", not a proof of completeness.

## Commands

- `downloads:telemetry-rollup [--dry-run] [--json]` — the scheduled job (not scheduled yet). Dry run: no database
  write, no file moved.
- `downloads:telemetry-validate [--days=2] [--json]` — read-only: ledger vs `done/` (SHA-256), batches awaiting a
  move, quarantine, period states and reasons, coverage minutes; exit 1 on inconsistency.
- `downloads:telemetry-replay {batch}` — guarded re-queue (see above).

## Measured database work (SQLite stand-in; MariaDB timings will differ, counts will not)

| events / minute | apply time / batch | statements / batch | `download_ip_ua_hourly` rows per hour |
|---|---|---|---|
| 1,000 | 124 ms | 22 | ≈ distinct IP/UA combinations |
| 10,000 | 835 ms | 85 | |
| 60,000 | 3.9 s | 368 | |

All database work happens in the scheduled rollup; none on a request.

## Concurrency (Phase 2.1, measured on MariaDB 10.11)

Aggregates are read-merged-written. The supported deployment runs ONE rollup per spool root, serialized by
`rollup.lock`. Measured with two workers deliberately bypassing that lock (two spool roots, shared database):

| isolation | contested rows | result |
|---|---|---|
| REPEATABLE READ (MariaDB default) | existing | serialized by `SELECT … FOR UPDATE`; exact |
| REPEATABLE READ | new | InnoDB deadlock (1213): the victim rolls back completely, next run applies it; exact |
| READ COMMITTED | new | **lost update reproduced** (5-minute metrics 25 of 50; both batches in the ledger) |
| any | one spool root | `rollup.lock` serializes; exact |

Residual risk therefore required BOTH a second concurrent rollup outside `rollup.lock` (a second spool root or host)
AND a READ COMMITTED session.

### Database advisory lock (Phase 2.2 — defence in depth, on by default)

After `rollup.lock`, the rollup takes `GET_LOCK('way2allah:download_telemetry:rollup', 0)` on a **dedicated**
connection (`<connection>__rollup_lock`) and records that session's id. It never waits: if another session holds it,
the run reports `db_lock: busy` and does nothing. Ownership is verified (`IS_USED_LOCK` = recorded id =
`CONNECTION_ID()`; any error = not owned) before every batch and inside each batch's transaction immediately before
COMMIT. If ownership is lost — the lock session was killed, or Laravel silently reconnected it (a new session never
holds the old lock) — the current transaction rolls back and the run writes nothing further (no finalization, no
acknowledgement). Released in `finally` (`RELEASE_LOCK`, connection closed); a crashed worker's lock is released by the
server when its session ends. `DOWNLOAD_TELEMETRY_ROLLUP_DB_LOCK=false` disables it (unsupported). SQLite (tests only)
has no advisory locks and relies on its database-level write lock.

Measured (MariaDB 10.11.19, three runs each): separate spool roots on one database, READ COMMITTED and REPEATABLE READ,
new and existing rows — the second worker always got `db_lock: busy`, totals always exact. With the advisory lock
disabled, the lost update still reproduced (1 of 3 runs). Residual window: between the last ownership check and the
COMMIT itself (milliseconds).

## Retention (Phase 2.2) — `downloads:telemetry-prune` (off by default, never scheduled)

| Table | Retention | Rule |
|---|---|---|
| `download_metrics_5m` | 35 days | `bucket` older |
| `download_metrics_daily` | 400 days | `day` older |
| `download_ip_ua_hourly` | 14 days | `hour` older |
| `download_distinct_sketches` | 400 days | `day` older |
| `download_telemetry_coverage` | 400 days | `minute` older |
| `download_telemetry_batches` | 400 days | `batch_minute` and `applied_at` older, and never newer than the oldest batch file in the spool − 1 day |
| `download_period_status` | not pruned | — |
| `done/` files | 24 h | rollup (unchanged) |

Enable with `DOWNLOAD_TELEMETRY_PRUNE=on`; `--dry-run` reports without deleting. Bounded: `prune_batch_rows` (5,000)
per DELETE, at most `prune_max_rows_per_table` (200,000) per table per run; a second run continues; idempotent. Takes
the same locks as the rollup. Refuses to run if the stale-batch boundary is not below the shortest retention.

## Stale-batch guard (Phase 2.2)

The **safe replay boundary** is `now − stale_batch_max_age_days` (13 days, deliberately below the shortest retention,
14 days). A batch with ANY event older than the boundary, or whose minute is older, is never applied: it is moved to
`quarantine/{batch_id}.stale.ndjson` (kept for inspection, counted as `stale`, never deleted automatically), and
`downloads:telemetry-replay` refuses it. Because nothing older than 13 days can be applied and nothing younger than
14 days is pruned, pruning can never let a batch be applied twice (its ledger row is gone only after 400 days) and
never partially recreates an expired row. An old batch that cannot be replayed is permanently excluded from the
aggregates; the hours it describes were already finalized `incomplete` (`unprocessed_batches`) after the 24 h wait,
so the loss is visible, not silent.

## IP/User-Agent growth monitoring (Phase 2.2) — `downloads:telemetry-growth` (read-only)

Per-hour counts of `download_ip_ua_hourly` (IP/User-Agent combinations, not people) for the last N hours, each bounded
by a LIMIT subquery (2 × the critical threshold; a capped count is reported as "at least"); the median of earlier
hours; projected storage = average rows/hour × 24 × retention days × average row size (default 1,000 B; measured
0.6–1.5 KB); the actual table size from `information_schema` on MySQL/MariaDB. Warnings: `rows_per_hour_warning`
(20,000), `rows_per_hour_critical` (100,000), `abnormal_growth` (≥ 5 × median), `projected_storage` (≥ 2 GiB) — exit
code 2 and a `DOWNLOAD_TELEMETRY_IP_UA_GROWTH` log warning. It never deletes, caps or merges rows; exact hourly
counts stay exact. Only the approved retention ever removes rows.
