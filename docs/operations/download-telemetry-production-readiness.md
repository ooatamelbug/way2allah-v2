# Download telemetry: privacy, retention and production readiness (Phase 3B)

**Status:** Phase 3B.1 implemented the permissions, quarantine retention and log redaction locally. Rejection-record
retention is **blocked pending owner approval** (§7). Nothing here is enabled. Telemetry (`DOWNLOAD_TELEMETRY_SPOOL`, `DOWNLOAD_TELEMETRY_EVENTS`,
`DOWNLOAD_TELEMETRY_AGGREGATE`) and pruning (`DOWNLOAD_TELEMETRY_PRUNE`) all default to off. No schedule or cron entry
exists for rollup, prune or monitor.

## 1. Where raw IP addresses live

| Store | Raw IP | Lifetime today | Cleanup |
|---|---|---|---|
| `active/{minute}.ndjson` | yes, one per event | until sealed: about 2 minutes plus the grace period, while the sealer runs | sealer |
| `sealed/{batch}.ndjson` | yes | until rolled up | rollup (needs `aggregate=on` and a schedule) |
| `done/{batch}.ndjson` | yes | 24 h (`done_retention_hours`), also bounded at 256 MiB | rollup run |
| `quarantine/*.ndjson` (malformed, unsupported, stale) | yes | **14 days** once pruning is on (Phase 3B.1); until then, indefinitely | `downloads:telemetry-prune` (off) |
| `download_ip_ua_hourly` | yes, with the `uah` hash | 14 days **only once pruning is on**; until then, indefinitely | `downloads:telemetry-prune` (off) |
| `download_rejections` (existing, pre-telemetry) | yes (`client_ip`), plus a raw `user_agent`, `forwarded_for` and `referer` | **indefinitely** (no retention exists) | none |
| `laravel.log`, `DOWNLOAD_SOURCE_*` warnings | no IP; the source link with its **query string replaced by `?[redacted]`** and userinfo dropped (Phase 3B.1) | log rotation | log rotation |

If the sealer and rollup are not scheduled, `active/` and `sealed/` keep raw IPs until the 1 GiB bound is reached. After
that, new events are dropped and counted, and nothing old is deleted. **So the spool must never be enabled without the
sealer and rollup also being scheduled.**

## 2. File and directory permissions (measured)

- **Measured modes:** directories are created as `0775`; files are opened with PHP's default mode. Under umask 022 that
  gives directories `755` and files `644`; under umask 002 it gives `775` and `664`.
- **Consequence:** every local account can list the spool and read raw IPs, unless a parent directory blocks it.
- **Required before enabling:** create the spool root yourself as `0750`, owned by the web user, with the group that the
  cron user shares (`0770` if cron runs as a different user in that group). Because the root blocks traversal, the files
  inside are not reachable by other accounts, whatever their own mode.
- **Check:** confirm that `storage/` itself is not world-readable on the host.
- **Possible code change (not made):** create directories `0770` and `chmod` new files to `0660`. Proposed only; the
  operational step above is sufficient on its own.

## 3. Quarantine cleanup policy (proposal, not implemented)

- Quarantined files are kept for diagnosis. `downloads:telemetry-spool-status` reports how many there are.
- **Proposal:** delete quarantined files older than **14 days**, matching IP/UA retention, as part of `telemetry-prune`
  and behind the same switch. Log the count of deleted files and bytes; never log their contents. Until then: review
  quarantine weekly and delete it by hand.

## 4. IP/User-Agent hourly retention and pruning prerequisites

Pruning (`DOWNLOAD_TELEMETRY_PRUNE=on`) is safe to schedule only when all of these hold:

1. `RollupConfig::retentionProblems()` is empty. In particular, `stale_batch_max_age_days` (13) must stay below the
   shortest retention (14).
2. The rollup has run normally for longer than the stale boundary. Otherwise a late batch could recreate a pruned
   period.
3. The `rollup_db_lock` advisory lock is on (default). Prune and rollup must never run concurrently against the same
   periods.
4. A backup or snapshot policy for `download_*` tables is agreed. Pruning deletes rows.
5. The first run is a manual `--dry-run`, with its row counts reviewed.
6. The batch size and row cap (defaults: 5,000 rows per batch, 200,000 per table per run) are acceptable for the host's
   load at the chosen hour.

## 5. User-Agent hashing and `APP_KEY` rotation

- **How `uah` is computed:** `substr(hmac_sha256(User-Agent, APP_KEY), 0, 32)`. The raw User-Agent is never written by
  the recorder.
- **Rotating `APP_KEY`** changes every hash. IP/UA rows from before and after the rotation cannot be compared, and the
  distinct-IP+UA sketches for the rotation day double-count.
- **Practice:** rotate at a UTC day boundary and record the date. Never reuse an old key just to compare hashes.
- **Key exposure:** anyone holding `APP_KEY` can test whether a given User-Agent string produced a given hash, so key
  exposure also weakens this pseudonymisation.
- **Optional:** use a dedicated `DOWNLOAD_TELEMETRY_UA_KEY`, so that rotating `APP_KEY` (sessions, encryption) does
  not disturb telemetry. Proposed, not implemented.

## 6. Production-readiness checklist

Every item must be confirmed by the owner before any switch is turned on.

- [ ] Phase 3A/3B code deployed with all switches **off**; smoke-test downloads (GET, HEAD, mirror, 503 at capacity).
- [ ] HEAD counter change accepted (see `download-telemetry-recorder.md`); HEAD volume checked in the access logs.
- [ ] FastCGI disconnect behaviour verified on staging, the one known validation gap: one real disconnect, one
      stalled source, one completion, with slots observed through `downloads:monitor`.
- [ ] Spool modes confirmed for the host's users (default `0750`/`0640`; `0770`/`0660` if web and cron users differ); spool root on a local disk (not NFS: flock semantics); at least 2 GiB free beyond the
      1 GiB bound.
- [ ] Migration `2026_11_11_100000_create_download_telemetry_tables` applied in a maintenance window.
- [ ] Sealer and rollup scheduled (every minute, `withoutOverlapping`), first with `aggregate=dry-run`, then `on`.
- [ ] `downloads:monitor` run by hand after each step; its exit code wired into monitoring only after review.
- [ ] Events enabled (`DOWNLOAD_TELEMETRY_EVENTS=on`) only after the spool, sealer and rollup run cleanly for a day.
- [ ] Pruning prerequisites (§4) met; first prune run is a manual dry run.
- [ ] Quarantine policy decided (§3).
- [ ] Retention for the existing `download_rejections` table decided (30 days proposed; blocked, §7). It holds raw
      IPs, User-Agents and referers with no retention.
- [ ] `APP_KEY` rotation practice recorded (§5).
- [ ] Privacy notice or policy updated to mention IP-based download statistics, if required.

## 7. Phase 3B.1 changes and the blocked item

- **Permissions:** directories are created `0750` and data files (spool batches, ledger shards, `health.bin`, `ack`)
  are chmod-ed to `0640` when they are created. The `other` class is never granted.
  - `DOWNLOAD_TELEMETRY_SPOOL_DIR_MODE` and `DOWNLOAD_TELEMETRY_SPOOL_FILE_MODE` allow `0770`/`0660` when the web PHP
    process and the cron user differ but share a group. Invalid values fall back to the defaults.
  - Moves (seal, done, quarantine) are renames and keep the mode.
  - Empty lock and marker files are inside the `0750` root.
- **Quarantine:** `downloads:telemetry-prune` deletes `quarantine/*.ndjson` older than `retention_quarantine_days` (14),
  by mtime, at most 1,000 files per run, and supports a dry run. Pruning remains **off** and **unscheduled**.
- **IP/UA hourly:** existing 14-day retention, unchanged.
- **Logs:** `ProxiedDownloadStreamer::redactLink()` keeps scheme, host, port and path. The query becomes
  `?[redacted]`; userinfo and fragment are dropped. Local paths are unchanged.
  - The `DOWNLOAD_CAPACITY_FULL` warning still carries `client_ip` (operational, unchanged).
- **`download_rejections` 30-day retention: NOT implemented. Stopped for approval.** The table is under the
  owner-approved `NonEmptyTableGuard` (2026-11-08). That guard was put in place, alongside `admin_audit_log`, so that
  "no audit history or rejection record is lost", and dropping data from the table is defined as "a separate,
  owner-approved DBA action (after a backup)". Deleting rows is therefore an audit-relevant change. It needs an
  explicit decision on:
  1. whether rejection records have an audit role or are operational only;
  2. backup or export before the first deletion;
  3. 30 days measured on `created_at` (indexed `idx_dr_created`);
  4. whether it runs inside `telemetry-prune` (off, unscheduled) or as its own command.

## 8. Owner-controlled activation checklist (Phase 3C)

Nothing in this list has been done. Each step is a later owner decision, taken in order. A step is "done" only with
evidence written next to it: date, operator, command output.

### 8.1 Feature flags (all default **off**)

| Flag | Effect | Turn on only after |
|---|---|---|
| `DOWNLOAD_TELEMETRY_SPOOL` | the writer may create spool files | 8.3–8.6 verified |
| `DOWNLOAD_TELEMETRY_EVENTS` | the request-path recorder emits (also needs the spool) | spool, sealer and rollup clean for 24 h |
| `DOWNLOAD_TELEMETRY_AGGREGATE` | `off` / `dry-run` / `on` for the rollup | dry-run reviewed |
| `DOWNLOAD_TELEMETRY_PRUNE` | allows deletion in `downloads:telemetry-prune` | §4 prerequisites met |
| `DOWNLOAD_TELEMETRY_ROLLUP_DB_LOCK` | advisory lock around rollups (default **on**; keep on) | — |

Not behind a flag (active as soon as the code is deployed):

- the Khotab streamer fixes (timeouts, validation, `Content-Length`);
- HEAD without slot or counter on Khotab and the redirect routes;
- the allowlisted Content-Type with `nosniff`;
- YouTube/SoundCloud mirror redirects;
- log redaction;
- the slot acquisition-time stamp.

These are behaviour changes for visitors and need their own deployment approval.

### 8.2 PHP-CGI/FastCGI disconnect validation (staging)

- [ ] One real download cancelled mid-transfer by the client. Expected: `transfer_finished st=client_aborted`, with
      either no `k` (in-loop detection) or `k=shutdown_fallback`, and the slot freed within seconds (`downloads:monitor`).
- [ ] A stalled source (idle timeout), and one complete transfer whose bytes match its `Content-Length`.
- [ ] A worker killed mid-transfer by the host. Expected: the slot is freed, and the transfer shows as started
      without finished.

### 8.3 OS users, groups and permissions

- [ ] Identify the web PHP user and the cron user. If they are the same, use the defaults `0750`/`0640`. If they
      differ but share a group, set `DOWNLOAD_TELEMETRY_SPOOL_DIR_MODE=0770` and `..._FILE_MODE=0660`.
- [ ] Create the spool root by hand with that mode, owned by the right user, on a **local** disk (not NFS: flock).
- [ ] The slot directory (`downloads.slot_directory`) is writable by the web user and readable by the monitor's user.

### 8.4 `/proc/locks` visibility

- [ ] As the monitor's user, run `downloads:monitor --json` while one download is running. Expected:
      `slots.visibility=slot_locks_visible`, with the PID of the PHP-CGI process.
- [ ] With no download running, expect `probe_visible`. A result of `probe_not_visible`, `probe_unavailable` or
      `lock_table_unreadable` means the host hides locks (hardened `/proc`, a container), so occupancy cannot be
      monitored there.

### 8.5 Spool capacity and disk reserve

| Store | Bound |
|---|---|
| spool files | 1 GiB hard bound (32 files × 32 MiB) |
| `done/` | up to 256 MiB for 24 h |
| drop ledger | 16 shards × (96 + 40 × 10,080) bytes ≈ 6.5 MB |
| new files | refused below 2 GiB free (`disk_reserve_bytes`) |

At about 321 bytes per event and 3 events per download, the 32 MiB file cap covers about 35,000 downloads per minute.

- [ ] Free space on the spool filesystem ≥ 1 GiB + 256 MiB + 2 GiB reserve + headroom.

### 8.6 PHP CLI memory limit

- [ ] The CLI `memory_limit` for cron is ≥ 256M. Measured peak: 54.5 MiB for the whole test process rolling up 5 ×
      20,000-event batches. The rollup holds one batch's aggregates at a time.
- [ ] The cron time limit is ≥ `rollup_time_budget_seconds` (50 s) plus margin. Measured: about 14,000 events/s on
      MariaDB 10.11.

### 8.7 MariaDB isolation and the advisory lock

- [ ] The migration `2026_11_11_100000_create_download_telemetry_tables` is applied in a maintenance window. The
      tables live on `main` next to the legacy schema. Consider a dedicated user with `SELECT/INSERT/UPDATE/DELETE` on
      `download_*` only, plus `GET_LOCK`.
- [ ] No connection pooler or proxy (ProxySQL, MaxScale) between the cron and MariaDB that would break session-scoped
      `GET_LOCK`. The rollup's lock is held on a dedicated connection, and ownership is checked before each batch and
      before COMMIT.
- [ ] `innodb_lock_wait_timeout` is acceptable for one batch transaction. Measured: about 1.4 s per 20,000-event batch.

### 8.8 Scheduler (proposed; NOT configured)

| Command | Schedule | Options | Notes |
|---|---|---|---|
| `downloads:telemetry-seal` | every minute | `withoutOverlapping` | |
| `downloads:telemetry-rollup` | every minute | `withoutOverlapping` | `dry-run` first |
| `downloads:monitor --json --window=15` | every 5 minutes | | to the monitoring agent (see `download-monitoring.md`) |
| `downloads:telemetry-prune` | daily, off-peak | | only after §4 |

### 8.9 Retention and pruning

- [ ] §4 prerequisites met; first run `downloads:telemetry-prune --dry-run`, reviewed.
- [ ] `download_rejections`: **no** retention. It is audit evidence until a separate policy is approved. Run
      `downloads:rejections-report --json` monthly to watch its growth.

### 8.10 Alert recipients

- [ ] The owner names the destinations for the operations channel, the operations on-call and the telemetry owner.
      None is configured.
- [ ] The debounce, cooldown and recovery rules in `download-monitoring.md` are accepted or adjusted.

### 8.11 Rollback procedure

1. **Telemetry problem:** set `DOWNLOAD_TELEMETRY_EVENTS=off` (stops new events at once; the next request reads the
   config), then `config:cache` if config is cached. Downloads are unaffected either way.
2. **Spool or rollup problem:** also set `DOWNLOAD_TELEMETRY_SPOOL=off` and `DOWNLOAD_TELEMETRY_AGGREGATE=off`, and
   remove the scheduler entries.
   - Spool files can stay (bounded) or be removed later by the owner.
   - **Do not** roll back the telemetry migration. Leave its tables; dropping them is a separate DBA decision.
3. **Pruning problem:** set `DOWNLOAD_TELEMETRY_PRUNE=off`. Deleted rows are not recoverable except from backup, which
   is why the dry run and the backup come first.
4. **Behaviour changes in 8.1's second list:** these can be rolled back only by redeploying the previous release. The
   Khotab capacity guard keeps its own flag, `DOWNLOAD_CAPACITY_GUARD`.
5. After any rollback: `downloads:monitor`, to confirm `slots` is ok and no slot is stuck.
