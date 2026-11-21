# Download System — Production Deployment & Activation Runbook (owner-controlled)

**Status:** documentation only. Nothing in this runbook has been executed. No production access was used to write it.
Every value marked `<…>` is environment-specific and must come from evidence on the target host, never from this
document.

**Authoritative order:** follow the phases in sequence. Each phase ends with a **gate**: do not start the next phase
until the gate is signed (date, operator, evidence).

**Detailed references** (not repeated here):

| Topic | Document |
|---|---|
| Release state and decisions | `docs/reports/download-system-release-readiness-review.md` |
| Capacity guard, slots, timeouts | `docs/operations/download-capacity.md` |
| HEAD / Content-Type / recorder events | `docs/operations/download-telemetry-recorder.md` |
| YouTube / SoundCloud redirects | `docs/operations/download-external-media-policy.md` |
| Spool | `docs/operations/download-telemetry-spool.md` |
| Rollup, periods, prune | `docs/operations/download-telemetry-aggregation.md` |
| Monitor, thresholds, alert proposal | `docs/operations/download-monitoring.md` |
| Privacy, permissions, activation checklist (§8) | `docs/operations/download-telemetry-production-readiness.md` |
| Rejection archive (format, state machine, backup confirmation, expiry, estimate) | `docs/operations/download-rejections-archive.md` |
| Local shadow and performance evidence | `docs/operations/download-telemetry-shadow-validation.md` |
| No-SSH production discovery (cPanel, WHM, support, external checks) | `docs/operations/download-production-discovery.md` |
| Local CGI/FastCGI validation | `docs/reports/download-local-fastcgi-validation.md` |

**Placeholders used below:**

| Placeholder | Meaning |
|---|---|
| `<APP>` | the Laravel application root on the host |
| `<PHP_CLI>` | the PHP CLI binary cron uses |
| `<WEB_USER>` / `<CRON_USER>` / `<GROUP>` | the OS accounts and group, from evidence |
| `<DB>` / `<DB_USER>` | the production MariaDB schema and user |
| `<SPOOL_ROOT>` / `<ARCHIVE_DIR>` / `<SLOT_DIR>` | the telemetry spool, rejection archive and capacity slot directories |
| `<BACKUP_TARGET>` | where file and DB backups are kept |
| `<RELEASE>` / `<PREVIOUS_RELEASE>` | the release being deployed, and the one it replaces |

---

## 0. Two separate things

| | What | When it takes effect |
|---|---|---|
| **A. Code deployment** | the new application release and its 3 migrations | **immediately** on deployment; some visitor-visible behaviour changes have **no flag** (§0.1) |
| **B. Feature activation** | telemetry spool, events, rollup, prune, rejection archive, expiry, monitor schedule | only when the owner sets each flag and schedule, one at a time (Phases 10–12) |

### 0.1 Behaviour that changes **immediately on deployment** (no flag)

| Change | Visitor-visible effect | Evidence / doc |
|---|---|---|
| Khotab streamer resilience | stalled sources end after the idle timeout; bad sources return a real 500 before any header; trustworthy `Content-Length` only | `download-capacity.md`, resilience tests |
| HEAD on `khotab-download`, `khotab-mirror` | no slot, **no `downcount` or `hits` increment**, no source contact; 200 even when the pool is full | `download-telemetry-recorder.md` |
| HEAD on 7 redirect routes (`khotab-item-pdf`, `lesson-download`, `recite-download`, `var-download`, `var-mirror`, `fatawa-download`, `mobile-file`) | same 302 and `Location`; **no counter increment** | same |
| `Content-Type` allowlist + `X-Content-Type-Options: nosniff` on proxied Khotab downloads | mp3 → `audio/mpeg`, mp4 → `video/mp4`, pdf, doc, docx; everything else `application/octet-stream` (was `text/html`) | same |
| YouTube / SoundCloud Khotab mirrors | `302` to the rebuilt `https://` page with `Cache-Control: no-store`; GET counts `hits` once, HEAD never; no slot | `download-external-media-policy.md` |
| Source-URL query strings redacted in logs | log content only | readiness doc §7 |
| Slot acquisition time stamp (`touch` of the slot file) | none (enables `held_seconds` in the monitor) | `download-monitoring.md` |
| `resource_title` written on capacity refusals | rejection rows gain a title; the recorder falls back if the column is missing | `download-rejections-archive.md` §8 |

**Unchanged on deployment:**

- Gallery ZIP, which still builds on HEAD; deferred to the Gallery phase.
- A GET for a missing Khotab file still increments `downcount` before its 404: legacy-compatible and pre-existing.
- Every telemetry and archive feature, all **off** by default.

---

## Phase 1 — Pre-deployment checks (no change made)

**Without SSH:** use `docs/operations/download-production-discovery.md`. It gives each fact below through cPanel, WHM,
hosting support or a safe external HTTP check, with an evidence matrix, and lists the **minimum facts needed before
code deployment** (its §6). The shell commands in this table apply only where a shell exists.

Run on the target host as an operator. Record every output.

| # | Check | Command (illustrative) | Expected / record |
|---|---|---|---|
| 1.1 | Web PHP SAPI | a phpinfo-style page **already approved for the host**, or the hosting panel | `Server API` = CGI/FastCGI (or FPM). Record version ≥ 8.3 |
| 1.2 | PHP CLI used by cron | `<PHP_CLI> -v` ; `<PHP_CLI> -r 'echo ini_get("memory_limit"), PHP_EOL;'` | PHP 8.3.x; memory_limit **≥ 256M** (measured rollup peak 54.5 MiB for 100k events) |
| 1.3 | `zend.exception_ignore_args` | `<PHP_CLI> -r 'var_dump(ini_get("zend.exception_ignore_args"));'`, and the web SAPI value | `"1"` on both (stack traces carry no arguments) |
| 1.4 | Web and cron users | `id <WEB_USER>` ; `id <CRON_USER>` ; the owner of the running PHP processes | same user → modes `0750`/`0640`; different users sharing `<GROUP>` → `0770`/`0660` |
| 1.5 | Storage filesystem | `df -hT <APP>/storage` ; `mount \| grep -F "$(df --output=target <APP>/storage \| tail -1)"` | a **local** filesystem (ext4/xfs), **not NFS** (flock semantics) |
| 1.6 | Free space | `df -B1 <APP>/storage` | ≥ spool bound 1 GiB + `done/` 256 MiB + spool reserve 2 GiB + archive estimate (Phase 2) + 512 MiB archive reserve + headroom |
| 1.7 | `/proc/locks` readable by `<CRON_USER>` | `sudo -u <CRON_USER> head -3 /proc/locks` | lines like `1: FLOCK ADVISORY WRITE <pid> <maj>:<min>:<inode> 0 EOF`; "Permission denied" or empty means occupancy monitoring will report **unknown** |
| 1.8 | Current download settings | `grep -E '^(DOWNLOAD_\|KHOTAB_DOWNLOAD_)' <APP>/.env` | **record the current values** (production evidently runs a 400-slot pool; the code default is guard **off** and 100 slots). They must be preserved |
| 1.9 | Config caching | `ls <APP>/bootstrap/cache/config.php` | if present, every flag change below needs `<PHP_CLI> <APP>/artisan config:cache` (or the host's equivalent) |
| 1.10 | MariaDB version and user | `SELECT VERSION(); SHOW GRANTS FOR CURRENT_USER();` | 10.3+ (instant `ADD COLUMN`; 10.11 tested); grants include CREATE/ALTER (for migrations) or a DBA runs them |
| 1.11 | Advisory locks | `SELECT GET_LOCK('way2allah:probe', 0); SELECT RELEASE_LOCK('way2allah:probe');` from the **cron** connection path | `1` then `1`. A pooler or proxy between cron and MariaDB (ProxySQL, MaxScale) must preserve session locks; record its absence |
| 1.12 | Existing tables | `SHOW TABLES LIKE 'download\_%';` ; `SHOW CREATE TABLE download_rejections\G` | record which exist. `download_rejections` should exist if the October migration ran |
| 1.13 | Production HEAD volume (affects counters) | access logs: `awk '$6 ~ /HEAD/' <access_log> \| grep -cE 'khotab-(download\|mirror)\|recite-download\|var-(download\|mirror)\|lesson-download\|fatawa-download\|mobile-file\|khotab-item-pdf'` per day | record the daily HEAD count; counters will grow by that much less after deployment |
| 1.14 | Backups exist and restore | the owner's backup tooling: the last DB backup and file backup, plus a restore test of each to a scratch location | a dated evidence line for each |

**Gate 1:** all values recorded; no unknowns among 1.2, 1.4, 1.5, 1.6, 1.8, 1.10, 1.11 and 1.14.

---

## Phase 2 — Historical rejection-log analysis; owner-controlled cleanup

The new release is not yet deployed, so use the analysis queries directly. The read-only commands are available only
after deployment (Phase 5).

1. **Analyse (read-only, bounded):**

   ```sql
   SELECT MIN(created_at), MAX(created_at) FROM download_rejections;             -- index ends
   SELECT table_rows, data_length, index_length, avg_row_length
     FROM information_schema.tables WHERE table_schema = '<DB>' AND table_name = 'download_rejections';
   SELECT created_at DIV 86400 AS day, COUNT(*) FROM download_rejections
     WHERE created_at >= UNIX_TIMESTAMP() - 30*86400 GROUP BY day ORDER BY day;   -- range on idx_dr_created
   ```

   An exact `COUNT(*)` scans the whole index; run it off-peak only if needed.
2. **Decide (owner):** keep the history, export it, or clear it. Readiness review §3 lists the risks.
3. **If clearing (owner executes, once):**
   - Take a backup or export first, and record its row count and SHA-256. The rows contain raw IPs and User-Agents.
   - Prefer a **chunked `DELETE` bounded by a `max_id` read once**:
     `DELETE FROM download_rejections WHERE id <= <max_id> LIMIT 5000;`, repeated with a pause, until 0 rows.
   - `TRUNCATE` resets ids and cannot be rolled back.
   - **Never** `migrate:rollback`: an **empty** table is no longer protected by `NonEmptyTableGuard`.
4. **If not clearing:** the first archive runs (Phase 12) will archive the history, up to 2,000,000 rows per run, with
   the backlog continuing in later runs. Each run still needs its backup confirmation.

**Gate 2:** the owner's decision is recorded, with the backup or export evidence if anything was deleted.

---

## Phase 3 — Database migrations (maintenance window; DBA-run)

Three Laravel-owned migrations on the `main` connection. None alters a legacy table.

| Migration | Effect | Safe properties |
|---|---|---|
| `2026_10_22_130000_create_download_rejections_table` | creates `download_rejections`, if not already applied | `down()` refuses while rows exist |
| `2026_11_11_100000_create_download_telemetry_tables` | creates `download_telemetry_batches`, `download_metrics_5m`, `download_metrics_daily`, `download_ip_ua_hourly`, `download_distinct_sketches`, `download_period_status`, `download_telemetry_coverage` (all empty) | `down()` refuses while any holds rows |
| `2026_11_11_300000_add_resource_title_to_download_rejections` | `ADD COLUMN resource_title VARCHAR(255) NULL` | instant on MariaDB 10.3+ (verified on 10.11); idempotent; `down()` refuses while any title exists |

1. Take a DB backup immediately before (Phase 1, check 1.14).
2. `<PHP_CLI> <APP>/artisan migrate --pretend` (with the new release's code available to the CLI), and review the SQL.
3. `<PHP_CLI> <APP>/artisan migrate --force`. If the host deploys without a shell, use the owner's established migration
   method.
4. **Verify:**
   - `SHOW TABLES LIKE 'download\_%';` lists 8 tables.
   - `SHOW COLUMNS FROM download_rejections LIKE 'resource_title';` returns `varchar(255)`, `YES`.

**Order note:** migrations may also run **after** the code. The recorder tolerates a missing `resource_title`, and
telemetry tables are not touched while telemetry is off. Running them first is preferred.

**Gate 3:** the migrations are applied and verified, and the pre-migration backup is recorded.

---

## Phase 4 — Application deployment (code only; every feature flag OFF)

1. Deploy `<RELEASE>` by the owner's release process (see `PROJECT-HANDOFF.md` for the project's deployment
   packaging). Keep `<PREVIOUS_RELEASE>` available for rollback.
2. In `<APP>/.env`, **keep** the existing capacity and timeout values recorded in check 1.8, and make sure **none** of
   these is set to an active value:

   ```
   DOWNLOAD_TELEMETRY_SPOOL=off
   DOWNLOAD_TELEMETRY_EVENTS=off
   DOWNLOAD_TELEMETRY_AGGREGATE=off
   DOWNLOAD_TELEMETRY_PRUNE=off
   DOWNLOAD_REJECTIONS_ARCHIVE=false
   ```

   These are also the code defaults. Setting them explicitly documents intent.
3. Clear and rebuild cached config, routes and views by the host's usual method.
4. No scheduler entry is added in this phase.

**Gate 4:** the release is live; `<PHP_CLI> <APP>/artisan downloads:monitor --json` runs (exit 0, 1 or 3 are all
acceptable here; the output is recorded).

---

## Phase 5 — Post-deployment compatibility checks

Use a test item `<ITEM>` (a primary Khotab item with a real mp3 or mp4 link), a mirror `<MIRROR>`, and a YouTube-page
mirror `<YT_MIRROR>`. Record the counters before and after, with a DB query.

| # | Check | Command | Expected |
|---|---|---|---|
| 5.1 | GET download | `curl -sS -o /tmp/f -D - https://<HOST>/khotab-download-<ITEM>.htm` | 200; `Content-Disposition: attachment; filename=…`; `Content-Type: audio/mpeg` (or the type for its extension; unknown → `application/octet-stream`); `X-Content-Type-Options: nosniff`; `Content-Length` only when the source declares a trustworthy one; the body matches the source |
| 5.2 | Counter on GET | `SELECT downcount FROM nuke_islamic_khotab WHERE id=<ITEM>;` | +1 |
| 5.3 | HEAD download | `curl -sS -I https://<HOST>/khotab-download-<ITEM>.htm` | 200, attachment, Content-Type, **no** Content-Length; `downcount` **unchanged** |
| 5.4 | Mirror GET and HEAD | the same for `/khotab-mirror-<ITEM>-<MIRROR>.htm` | GET +1 `hits`; HEAD unchanged |
| 5.5 | YouTube/SoundCloud mirror | `curl -sS -I https://<HOST>/khotab-mirror-<ITEM>-<YT_MIRROR>.htm` | `302`, `Location: https://www.youtube.com/…` (exact host, path and query preserved); `Cache-Control: no-store`; GET +1 `hits`, HEAD none |
| 5.6 | Redirect routes | `curl -sS -I` on one of each: `/recite-download-<ID>.htm`, `/var-download-<ID>.htm`, `/fatawa-download-<ID>.htm`, `/mobile-file-<LID>.htm`, `/khotab-item-pdf-<ID>.htm`, `/lesson-download-<ID>.htm` | the same status and `Location` as GET; counters unchanged by HEAD and +1 by GET |
| 5.7 | Missing file | a Khotab item whose local file is absent | 404 (and `downcount` +1 on GET: legacy-compatible) |
| 5.8 | Capacity rejection (staging only, or a deliberately tiny `DOWNLOAD_CAPACITY_MAX_CONCURRENT` in a maintenance window) | saturate the slots, then GET | `503`, `Retry-After`, no counter increment, one `download_rejections` row with `resource_title` |
| 5.9 | Client disconnect | start `curl` on a large file, interrupt it after a few MB, then wait 5 s | `downloads:monitor --json`: `slots.occupied` returns to its baseline; no stuck holder |
| 5.10 | Slot release | `<PHP_CLI> <APP>/artisan downloads:monitor --json \| jq .checks.slots` | `visibility` ∈ {`slot_locks_visible`, `probe_visible`, `no_slot_files`}; `occupied` matches the in-flight downloads |
| 5.11 | Logs | the application log after 5.1–5.9 | `DOWNLOAD_SOURCE_*` lines show `?[redacted]` instead of any query string |

**Gate 5:** every row matches. Any deviation means **NO-GO** for activation; consider rollback (Rollback R3).

---

## Phase 6 — Spool and archive directory preparation (no activation)

Modes follow check 1.4: `0750`/`0640` for the same user; `0770`/`0660` plus `DOWNLOAD_TELEMETRY_SPOOL_DIR_MODE`,
`…_FILE_MODE`, `DOWNLOAD_REJECTIONS_ARCHIVE_DIR_MODE` and `…_FILE_MODE` for split users.

```
install -d -m 0750 -o <WEB_USER> -g <GROUP> <SPOOL_ROOT>      # default <APP>/storage/app/download-telemetry
install -d -m 0750 -o <CRON_USER> -g <GROUP> <ARCHIVE_DIR>    # default <APP>/storage/app/download-rejections-archive
stat -c '%a %U:%G %n' <SPOOL_ROOT> <ARCHIVE_DIR> <SLOT_DIR>
```

Expected:

- both directories exist, with the modes and owners above, and **outside** the public web root (the archive refuses a
  directory under `public/`);
- `<SLOT_DIR>` is writable by `<WEB_USER>`, and readable or listable by `<CRON_USER>` (for the monitor).

If `<SPOOL_ROOT>` or `<ARCHIVE_DIR>` differ from the defaults, set `DOWNLOAD_TELEMETRY_SPOOL_ROOT` and
`DOWNLOAD_REJECTIONS_ARCHIVE_DIRECTORY`.

**Storage estimate** (read-only; sizes only, no rows exported):

```
<PHP_CLI> <APP>/artisan downloads:rejections-archive-estimate --days=30 --backup-copies=<N> --json
<PHP_CLI> <APP>/artisan downloads:rejections-report --days=30 --json
```

Record `recommended_free_bytes_on_archive_disk`, `backup_bytes_peak` and `confidence`. Confirm the free space from
check 1.6 exceeds the sum of:

- the telemetry spool bound, 1 GiB;
- `done/`, 256 MiB;
- the spool reserve, 2 GiB;
- `recommended_free_bytes_on_archive_disk`.

**Gate 6:** directories, modes and space are verified and recorded.

---

## Phase 7 — Backup configuration

| What | How (owner's tooling) | Retention |
|---|---|---|
| MariaDB (incl. `download_rejections`, telemetry tables) | existing DB backup | the owner's DB backup policy |
| `<ARCHIVE_DIR>/archives/` (`*.ndjson.gz` + `*.manifest.json`) **and** `<ARCHIVE_DIR>/audit.log` | file backup to `<BACKUP_TARGET>`, preferably off-host | **owner decision, separate from the 4-month archive retention** (see Backup and retention) |
| `<ARCHIVE_DIR>/runs/` (journals) | the same file backup | as above |
| Telemetry spool | **not** backed up (bounded, transient, raw IPs) | — |

**Integrity check of a backup** (each week, before confirming a run):

```
<PHP_CLI> <APP>/artisan downloads:rejections-archive-inspect --verify --json     # source side: integrity "ok" per segment
sha256sum <BACKUP_TARGET>/<segment>.ndjson.gz                                     # must equal the manifest's file_sha256
```

**Gate 7:** the backup job exists, includes the archive directory, and one test restore of a dummy file is evidenced.

---

## Phase 8 — Scheduler configuration (prepared; entries added one at a time in Phases 10–12)

Proposed entries; none is configured today. Cron must run as `<CRON_USER>`, with `<PHP_CLI>`.

| Command | Cadence | Enable in | Notes |
|---|---|---|---|
| `downloads:telemetry-seal` | every minute, without overlapping | Phase 10 | |
| `downloads:telemetry-rollup` | every minute, without overlapping | Phase 10 (with `AGGREGATE=dry-run` first) | |
| `downloads:monitor --json --window=15` | every 5 minutes | Phase 9 | output to the monitoring agent |
| `downloads:rejections-archive` | **daily**, at a quiet hour, without overlapping | Phase 12 | the command decides when a run is due (7 days, 1-hour tolerance) |
| `downloads:rejections-archive-expire` | **daily at 00:00–00:05 UTC** | Phase 12 | keeps archive expiry to minutes after each deadline |
| `downloads:telemetry-prune` | daily, off-peak | after §4 of the readiness doc; **first run `--dry-run` by hand** | optional |

**Dependencies:**

- seal before rollup;
- rollup only with the spool on;
- archive expiry only after the archive is enabled;
- monitor independent.

With Laravel's scheduler, one `* * * * * <PHP_CLI> <APP>/artisan schedule:run` line runs them all, once
`routes/console.php` has the entries. That is a code change, deliberately not made.

**Gate 8:** the schedule plan is approved, with the user and binary confirmed.

---

## Phase 9 — Monitoring setup (no alerts configured without owner approval)

**Command:** `<PHP_CLI> <APP>/artisan downloads:monitor --json --window=15`.

**Exit codes:**

| Exit | Meaning |
|---|---|
| `0` | ok |
| `1` | warn |
| `2` | critical |
| `3` | unknown |

A timeout or fatal error is treated as `3`.

**Cadence:** every 5 minutes (proposal).

**Thresholds** (defaults; `config/download_monitor.php`, archive keys in `config/download_rejections.php`):

| Check | Warn | Critical |
|---|---|---|
| `slots` | ≥ 80 % occupied, or a holder ≥ 2 h | ≥ 95 %, or a holder ≥ 12 h |
| `capacity_rejections` | ≥ 1 in the window | ≥ 100 |
| `transfers` | stream-failure ratio ≥ 5 % (min 10), or disconnect ratio ≥ 60 % | failure ratio ≥ 25 % |
| `spool` | ≥ 50 % of bound, or low disk | ≥ 80 %, or below reserve |
| `telemetry_drops` | any drop, or `accounting_exact=false` | ≥ 1,000 |
| `circuit_breaker` | — | open |
| `pending_batches` / `rollup_lag` | ≥ 5 batches or ≥ 10 min / ≥ 15 min | ≥ 20 or ≥ 1 h / ≥ 1 h |
| `period_quality` | any incomplete day | — |
| `ip_ua_growth` | growth warnings | rows-per-hour critical |
| `rejections_archive` | no successful run ≥ **8 days**; interrupted run; oldest live row > 20 days; expired segments present; **awaiting backup confirmation** | no successful run ≥ **14 days**; any **verification failure** |

**Routing, deduplication and recovery** (proposal, `download-monitoring.md`):

- **Destinations:**
  - operations (slots, rejections, transfers);
  - telemetry owner (spool, rollup, periods, growth, archive);
  - pages only for slot and breaker criticals.
- **Alert key:** `downloads:<check>:<status>`.
- **Debounce:** 2 consecutive runs (1 for slot and breaker criticals).
- **Repeat notices:** every 60 minutes for warnings, 30 for criticals.
- **Recovery:** after 2 ok runs.
- **Unknown:** opens a ticket after 3 consecutive unknowns, and never counts as ok.

**Destinations need owner approval: none is configured.**

**Gate 9:** the agent runs the command and records the exit codes; destinations are approved by the owner.

---

## Phase 10 — Limited activation (telemetry capture, no events yet)

1. Set `DOWNLOAD_TELEMETRY_SPOOL=on` and `DOWNLOAD_TELEMETRY_AGGREGATE=dry-run`, and rebuild the config cache.
2. Add the `seal` and `rollup` schedule entries.
3. `downloads:telemetry-spool-status --json`: `enabled: true`, the breaker closed, no drops.
4. `downloads:telemetry-rollup --dry-run --json`: no errors.
5. Then set `DOWNLOAD_TELEMETRY_AGGREGATE=on`. Keep `DOWNLOAD_TELEMETRY_EVENTS=off` for **24 h**: the spool, sealer and
   rollup run with no events, which proves the pipeline and its coverage.
6. Set `DOWNLOAD_TELEMETRY_EVENTS=on`. Downloads now emit events. Responses are unchanged: shadow-validated locally;
   recorder cost ≈ 0.25 ms per download.

## Phase 11 — Observation period (owner sets the length; ≥ 7 days proposed)

Daily, record:

- `downloads:monitor --json`;
- `downloads:telemetry-validate --days=2 --json`;
- `downloads:telemetry-growth --json`.

Expected:

- no breaker trips; drops 0 (or explained);
- `rollup_lag` ok;
- periods becoming `final`;
- download error rates unchanged versus the access logs;
- **staging or production FastCGI disconnect evidence:**
  - a cancelled download shows `transfer_finished st=client_aborted` (in-loop or `k=shutdown_fallback`);
  - the slot is freed within seconds.

## Phase 12 — Full activation

1. **Rejection archive:**
   - Set `DOWNLOAD_REJECTIONS_ARCHIVE=true`, and keep `DOWNLOAD_REJECTIONS_ARCHIVE_REQUIRE_BACKUP=true`.
   - Run once by hand: `downloads:rejections-archive --json`. Expect `awaiting_backup`, with `digest` and `segments`.
   - Back the segments up (Phase 7), verify the checksums, then confirm:
     `downloads:rejections-archive --confirm-backup=<run_id> --digest=<digest> --reference="<backup id>"`.
   - Run again: `completed`, with `deleted` equal to `rows`.
   - `downloads:rejections-archive-inspect --verify`: every segment `ok`.
   - Add the daily `rejections-archive` and `rejections-archive-expire` entries.
2. **Monitor schedule:** wire it to the approved destinations.
3. **Prune (optional, later):** set `DOWNLOAD_TELEMETRY_PRUNE=on` only after readiness §4, and only after a reviewed
   `--dry-run`.

---

## Backup and retention (approved policy)

| Item | Policy | Mechanism |
|---|---|---|
| Live `download_rejections` | **≤ 20 days under successful scheduling** | weekly run, cutoff = run time − 13 days. Late runs, or a late backup confirmation, extend it day for day; monitored |
| Archive frequency | **every 7 days** | due 7 days after the last completed run (1-hour tolerance) |
| Archive retention | **≤ 4 calendar months per record** | one segment per UTC creation day. Deadline = 00:00 UTC on (creation date + 4 months, month-end clamped). Records are **expired up to about a day before 4 complete months** (their time of day), and up to about 3 days more at the end of February (Oct 29–31 → Feb 28). **Never after** the deadline, apart from the expiry schedule's own delay |
| Raw IP and User-Agent | **retained** in the archive | files `0640`, directories `0750`; outside the web root |
| Live deletion | **only after a verified archive and a digest-bound backup confirmation** | `require_backup_confirmation=true` (default) |
| Archive expiry | **scheduled separately** (daily 00:00–00:05 UTC) | `downloads:rejections-archive-expire`; audited; COMPLETED runs only |
| Historical records | **no automatic deletion before owner approval** | Phase 2 is owner-executed; the archive never deletes unconfirmed rows |
| **Backup retention** | **owner decision, independent of the 4-month archive retention** | archive files deleted by expiry still exist in backups for the backup retention period. If backups must not outlive 4 months, the backup retention must be ≤ 4 months as well |

---

## Rollback and recovery

None of these procedures drops or truncates a populated telemetry or archive table.

### R1 — Disable telemetry

1. Set `DOWNLOAD_TELEMETRY_EVENTS=off`, and rebuild the config cache. New events stop on the next request; downloads
   are unaffected.
2. If needed, also set `DOWNLOAD_TELEMETRY_SPOOL=off` and `DOWNLOAD_TELEMETRY_AGGREGATE=off`.
3. Spool files and tables remain. They are bounded and inert.

### R2 — Stop scheduler jobs

Remove or comment out the specific entries (seal, rollup, monitor, archive, expire, prune). A running job finishes or
is released by its locks: flock plus `GET_LOCK`. No job leaves the database half-written: rollup batches are
single transactions, and archive state is journaled.

### R3 — Restore the previous application version

1. Redeploy `<PREVIOUS_RELEASE>` by the owner's release process. This reverts the immediate behaviour changes (§0.1),
   including the HEAD counting, Content-Type and media redirects.
2. **Leave the migrations applied.** The previous code ignores the new tables and the nullable column.
3. Rebuild the caches; `downloads:monitor` is no longer available in the old release.

### R4 — Recover sealed telemetry batches

- Sealed batches stay in `<SPOOL_ROOT>/sealed/` until applied, and are applied exactly once (ledger by `batch_id` and
  `sha256`).
- After an outage, simply re-run `downloads:telemetry-rollup`.
- A batch in `done/` that the ledger proves was never applied: `downloads:telemetry-replay <batch_id>`.
- Quarantined files: inspect them; never feed them back by hand.

### R5 — Recover archived rejection records

1. `downloads:rejections-archive-inspect <segment> --verify`.
2. `downloads:rejections-archive-restore <segment> --dry-run`.
3. `downloads:rejections-archive-restore <segment> --confirm=<segment>`. Rows go into `download_rejections_restored`
   (same structure), **never** the live table. Duplicates are skipped; conflicts are reported, never overwritten.
4. For a segment already expired: restore its files from `<BACKUP_TARGET>` into `<ARCHIVE_DIR>/archives/`, check
   `sha256sum` against the manifest, then follow steps 1–3.

### R6 — Incomplete archive runs

| State | Action |
|---|---|
| `COPYING` / `PENDING` (crash) | run `downloads:rejections-archive` again; it restarts the copy, and nothing was deleted |
| `VERIFIED` (awaiting backup) | back up and confirm with the run's digest; a changed manifest voids the confirmation, so confirm again after investigating |
| `DELETING` (crash) | run again; it resumes at the recorded segment and chunk, using the existing confirmation |
| `VERIFY_FAILED` | **new runs are blocked**. Inspect with `--verify`. Nothing was deleted. Then `downloads:rejections-archive --abandon=<run_id>`: files move to `rejected/` (kept), and the next run re-archives those rows |

### R7 — Migration rollback constraints

**Production `migrate:rollback` remains prohibited.**

| Migration | `down()` behaviour |
|---|---|
| telemetry tables | refuses while any table holds rows |
| `download_rejections` | refuses while it holds rows. After an owner cleanup to **zero rows**, a rollback **would drop it**, so never run one |
| `resource_title` | refuses while any title is stored |

Dropping populated objects is a separate, owner-approved DBA action, taken after a backup.

---

## Production gates (staging or production evidence required)

| # | Gate | Evidence required | Status |
|---|---|---|---|
| G1 | FastCGI disconnect behaviour | Phase 11: a cancelled download, `client_aborted`, the slot freed (local evidence used PHP's built-in server only) | **open** |
| G2 | `/proc/locks` visibility | check 1.7 and Phase 5, row 5.10: `slot_locks_visible` with a real download, `probe_visible` when idle | **open** |
| G3 | Web and cron users, and permissions | check 1.4 and Phase 6 | **open** |
| G4 | Actual archive storage | Phase 6 estimate on production data | **open** |
| G5 | Backup availability and restore | check 1.14 and Phase 7 | **open** |
| G6 | Production HEAD volume | check 1.13 (counter impact accepted) | **open** |
| G7 | Alert routing | Phase 9 destinations approved | **open** |
| G8 | Current capacity and timeout values preserved | check 1.8 versus the post-deploy `.env` | **open** |
| G9 | MariaDB grants, version and advisory locks | checks 1.10 and 1.11 | **open** |
| G10 | `zend.exception_ignore_args`, CLI memory | checks 1.2 and 1.3 | **open** |

## GO / NO-GO checklist

**Code deployment (Phases 3–5): GO only if all of these hold**

- [ ] Gate 1 signed (pre-checks recorded; G8, G9 and G10 evidenced).
- [ ] The owner accepts the immediate behaviour changes in §0.1 (HEAD counting, Content-Type, media redirects) and the
      HEAD volume (G6).
- [ ] A DB backup was taken and a restore tested (G5) within the maintenance window.
- [ ] Every flag in Phase 4, step 2 is off.
- [ ] `<PREVIOUS_RELEASE>` is ready for R3.

**NO-GO if:**

- any pre-check is unknown;
- `/proc/locks` is unreadable **and** the owner has not accepted "slot occupancy unknown";
- the storage is NFS;
- CLI memory is below 256M.

**Feature activation (Phases 10–12): GO only if all of these hold**

- [ ] Gate 5: the compatibility checks all pass.
- [ ] G2 and G3: lock visibility, users and permissions are verified.
- [ ] Gate 6: directories, modes and free space are verified (G4).
- [ ] Gate 7: the backup job includes the archive directory and a test restore succeeded.
- [ ] Gate 8: the schedule plan is approved.
- [ ] Gate 9 and G7: the monitor runs and the destinations are approved.
- [ ] Phase 2: the owner's historical-cleanup decision is recorded.

**Full activation (Phase 12) also requires:**

- [ ] the Phase 11 observation completed, with G1 (FastCGI disconnect) evidenced;
- [ ] a named operator assigned to the weekly backup confirmation;
- [ ] backup retention decided.

**NO-GO or STOP at any time on:**

- a breaker opening repeatedly;
- download behaviour differing from Phase 5;
- `VERIFY_FAILED`;
- a monitor critical that is not understood.

In those cases use R1 or R2.

---

## Unresolved owner decisions

1. Historical `download_rejections`: keep, export or clear, and when (Phase 2).
2. **Backup retention** for archive files and DB backups. It is separate from the 4-month archive retention; backups
   can otherwise outlive it.
3. Alert destinations, and acceptance of the debounce, cooldown and recovery rules (Phase 9).
4. Observation-period length before full activation (Phase 11).
5. The operator responsible for weekly backup confirmations, and the response time expected. A late confirmation
   extends the 20-day live retention.
6. Whether telemetry prune is enabled, and when (readiness §4).
7. Whether to accept "slot occupancy unknown" if the host hides `/proc/locks`.
8. Future fields kept in new rejection rows: full source URL with query string, full referer, raw User-Agent
   (readiness review §6, item 9).
9. Gallery ZIP HEAD behaviour: deferred to the Gallery phase.
