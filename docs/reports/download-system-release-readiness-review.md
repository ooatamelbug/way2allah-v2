# Download System — Release Readiness Review

**Date:** 2026-11-11
**Status:** local work only. Nothing committed, deployed, enabled or scheduled.

This review consolidates the download-system work: the capacity-incident fixes, and telemetry Phases 1–3C. It records
the owner decision of 2026-11-11 on archiving `download_rejections`. The detail behind each line lives in
`docs/operations/`:

- `download-capacity.md`
- `download-telemetry-spool.md`
- `download-telemetry-aggregation.md`
- `download-telemetry-recorder.md`
- `download-monitoring.md`
- `download-telemetry-production-readiness.md`
- `download-telemetry-shadow-validation.md`
- `download-external-media-policy.md`

## 1. Release state

| Area | State | Gate before release |
|---|---|---|
| Khotab streamer resilience (timeouts, validation, `Content-Length`, DB release, client-abort) | done locally, tested | FastCGI disconnect check on staging |
| HEAD without slot or counter (Khotab, 7 redirect routes) | done locally (3B/3B.1) | owner deployment approval; HEAD volume check in access logs |
| Allowlisted Content-Type + `nosniff` | done locally (3B.1) | deployment approval |
| YouTube/SoundCloud mirror redirect (HTTPS normalisation) | done locally; policy approved (3C) | deployment approval |
| Log redaction (source query strings, rollup errors) | done locally (3B.1, 3C) | — |
| Telemetry spool, rollup, monitor, prune | done locally, every flag **off** | activation checklist (`production-readiness.md` §8) |
| `download_rejections` archive (Phase 3D) | **implemented locally**, disabled and unscheduled (`docs/operations/download-rejections-archive.md`) | §8 prerequisites |
| `download_rejections.resource_title` (Phase 3D) | additive migration written, verified instant on MariaDB 10.11; not applied anywhere | owner applies it in a maintenance window |
| Gallery ZIP HEAD | deferred to the Gallery phase | — |

Latest local evidence (Phase 3C):

- full serial suite with MariaDB 10.11: 3,286 passed, 5 skipped, 0 failed;
- PHPStan and Pint clean;
- shadow comparison: responses identical with telemetry on or off in all five configurations.

## 2. `download_rejections`: what it is today

- **Table:** created by `2026_10_22_130000_create_download_rejections_table` on `main`, one row per download refused
  because the global pool was full.
- **Indexes:** primary key `id` (BIGINT auto-increment), plus `idx_dr_created`, `idx_dr_resource`, `idx_dr_ip` and
  `idx_dr_reason`.

**Writers and readers (complete list, from the code):**

| Component | Use |
|---|---|
| `DownloadRejectionRecorder` | the **only writer**: one INSERT per refusal, on the refusal path; failure is logged and never blocks the 503 |
| `DownloadRejection` (Eloquent model) | used only by tests; no admin page, report or API reads the table |
| `downloads:monitor` (`capacity_rejections`) | bounded COUNT and MAX over `created_at`, current window only |
| `downloads:rejections-report` | MIN, MAX, daily counts and storage statistics; read-only |
| `NonEmptyTableGuard` | the migration's `down()` refuses to drop the table while it holds rows (owner-approved 2026-11-08) |
| Tests | `DownloadRejectionSchemaDriftTest` keeps the fixture equal to the migration; several feature tests insert and read rows |

**Nothing depends on old rows.** No code reads a row older than the monitor's window, and no foreign key, view or
report joins on `id`.

## 3. Initial historical cleanup (owner-executed, once, before deployment)

**Not performed.** Execution is entirely the owner's. These are the dependencies and risks to weigh.

### 3.1 Before

1. **Measure:** run `php artisan downloads:rejections-report --exact --json` on production (read-only) to get the row
   count, the oldest and newest rows, storage, and daily growth.
2. **Decide whether the data is kept anywhere:**
   - a full logical backup of the database exists and is restorable; or
   - a one-off export of this table, for example
     `mysqldump --single-transaction --no-create-info <db> download_rejections | gzip`, with its row count and SHA-256
     recorded.

   The rows contain raw client IPs, User-Agents, referers and full source URLs. Any export inherits those privacy
   obligations.
3. **Choose the method** (next section).

### 3.2 Method and its risks

| Method | Effect | Risks |
|---|---|---|
| `TRUNCATE TABLE download_rejections` | instant, frees space, **resets AUTO_INCREMENT to 1** | needs a brief metadata lock (DDL); it cannot be rolled back; in a replicated setup it is replicated as DDL; ids restart, so any export keyed by `id` overlaps with future ids (see §4.5, which keys archives on `(source_id, created_at)` for this reason) |
| `DELETE … WHERE id <= :max_id` in chunks (e.g. 5,000) | keeps AUTO_INCREMENT; online; concurrent refusals continue | slower; undo/binlog volume proportional to rows; space is not returned to the OS until `OPTIMIZE TABLE` (itself a table rebuild) |

**Recommendation:** chunked `DELETE` bounded by a `max_id` read once at the start. New refusals inserted during the
cleanup (higher ids) are never touched, and ids never restart. Run it in a quiet period.

### 3.3 After

- **Empty-table rollback risk:** `NonEmptyTableGuard` protects only a **non-empty** table. Immediately after the
  cleanup, an accidental `migrate:rollback` **would drop the empty table**. Production `migrate:rollback` remains
  prohibited.
- **Monitoring:** `capacity_rejections` will report `ok` and `last_rejection_at = null` until the next refusal. That is
  expected; there is no false alert.
- **Nothing else** (code, cache, throttle state file) holds references to the deleted rows.

## 4. Recurring archive: design (NOT implemented, disabled and unscheduled by default)

### 4.1 Settings: independent, and only one is owner-stated

| Setting (proposed key, `config/download_rejections.php`) | Value | Status |
|---|---|---|
| `archive.enabled` | `false` | default off; no schedule entry exists |
| `archive.frequency_days` | **7** | **owner preference** |
| `archive.active_retention_days` | 30 | proposed, **not approved** |
| `archive.archive_retention_days` | 365 (≈ 12 months) | proposed, **not approved** |
| `archive.storage` | `file` (see 4.2) | proposed |
| `archive.delete_after_verify` | `false` for the first runs | proposed: "copy and verify only" first |
| `archive.chunk_rows` / `max_rows_per_run` / `pause_ms` | 5,000 / 2,000,000 / 50 | proposed |

How the settings interact:

- The frequency only decides **when** a run is due: a run does nothing if the last successful run is younger than
  `frequency_days`. The scheduler can therefore call the command daily, and a missed week catches up on the next call.
- Active retention decides **what** is archived: rows with `created_at < start of UTC day − active_retention_days`.
- With 7 and 30, the active table holds between 30 and 37 days of rows.
- Archive retention is applied to the archive only, and never to the active table.

### 4.2 Storage options

| | A. MariaDB archive table `download_rejections_archive` | B. Compressed files (NDJSON.gz + manifest) |
|---|---|---|
| Shape | the same columns, plus `archive_run_id` and `archived_at`; UNIQUE (`source_id`, `created_at`); optional monthly RANGE partitions on `created_at` | one file per run: `rejections-<from>-<to>-<run>.ndjson.gz`, and a `.manifest.json` with row count, id range, `created_at` range, SHA-256 and schema version |
| Retrieval | SQL, immediate | `zcat \| jq`, or a proposed `downloads:rejections-archive-import <file>` into a scratch table |
| Disk | InnoDB ≈ data + 4 indexes; per row in the same order as the live table (measure with `rejections-report`: `bytes_per_row`) | gzip of NDJSON, typically 6–10× smaller than the raw row text |
| Database impact | grows `main` and every DB backup; INSERT…SELECT load on the primary | none, apart from the SELECT and DELETE chunks |
| Backup | automatic with the DB backup (and so retained as long as backups are) | the archive directory must be added to the file backup and, preferably, copied off-host |
| Retention enforcement | `DROP PARTITION` (instant) or chunked DELETE | delete whole files by manifest date range |
| Privacy | raw IPs stay in the live DB and its backups for the archive retention | raw IPs in files; directory `0750`, files `0640` (as the telemetry spool) |
| New schema | yes: one table (owner approval) | no DB schema; a directory on local disk |

**Recommendation: B (compressed files) as the primary archive.** It keeps `main` and its backups lean, and needs no
new table on the shared legacy database. Retrieval needs are occasional investigation, not live queries. Option A
remains available if the owner wants SQL access to the archive; the design below works for both, with only the
"write" and "verify" steps differing.

### 4.3 The run (one invocation, safe to repeat)

1. **Lock.** Take a host `flock` on `storage/app/download-rejections-archive/archive.lock` and a MariaDB
   `GET_LOCK('way2allah:download_rejections:archive', 0)` on a dedicated connection. If either is busy, exit 0
   ("busy").
2. **Due?** If the last *completed* run (from the run journal) is younger than `frequency_days` and no run is
   incomplete, exit 0.
3. **Resume or start.**
   - **Resume:** if the journal holds an incomplete run, continue it with its stored cutoff, `high_id` and progress.
   - **Start:** otherwise compute `cutoff` = UTC day start − `active_retention_days`. Read `high_id` = `MAX(id)` among
     rows with `created_at < cutoff` (index `idx_dr_created`), and `low_id` = the previous run's `high_id`, or 0.
     Record `{run_id, cutoff, low_id, high_id, state: copying}` **before any data moves**.
4. **Copy.** Read `id > last_copied AND id <= high_id AND created_at < cutoff ORDER BY id LIMIT chunk_rows`, a
   primary-key range scan.
   - **B:** append to `<run>.ndjson.gz.part`, flush and fsync, then record `last_copied` and the running count and hash.
   - **A:** `INSERT IGNORE` into the archive table (the UNIQUE key makes a repeated chunk a no-op), in its own short
     transaction, then record `last_copied`.
5. **Verify.**
   - **Source side:** compute, over `id ∈ (low_id, high_id] AND created_at < cutoff`, the `COUNT(*)` and
     `BIT_XOR(CRC32(CONCAT_WS('|', id, created_at, …all columns…)))`.
   - **B:** close the part file, re-read and decompress it **from disk**, and recompute the same count and checksum over
     the parsed rows. Write the manifest, fsync, and rename `.part` to its final name, so a final name always means
     verified.
   - **A:** compute the same aggregate over the archive rows of `archive_run_id`.
   - On any mismatch: state `verify_failed`, no deletion, exit 2 (the monitor alerts). The next run retries copying the
     range from scratch: B truncates the part file; A relies on `INSERT IGNORE`.
6. **Off-host copy** (B, optional; owner decision). Copy to off-host storage and verify the SHA-256 there before
   deletion is allowed.
7. **Delete** (only when `delete_after_verify` is true and the state is `verified`). Delete in id-range chunks:
   `DELETE … WHERE id > :a AND id <= :b AND id <= :high_id AND created_at < :cutoff`. Each chunk is its own short
   transaction, with `pause_ms` between chunks, and `last_deleted` is recorded after each one. The archive is verified
   for the whole range, so an interrupted deletion simply resumes.
8. **Complete.** Set state `completed` with counts, bytes, duration and checksum. Release both locks.
9. **Archive retention.**
   - **B:** delete archive files (and their manifests) whose manifest `max_created_at` is older than
     `archive_retention_days`.
   - **A:** drop partitions or chunk-delete.

   Logged as counts only.

### 4.4 The properties the owner asked for

- **Resumable.** Every step records its progress in the journal *after* the step is durable. A crash at any point
  resumes from the last recorded position.
- **Idempotent and duplicate-free.**
  - Each run owns the half-open id range `(low_id, high_id]`, fixed at start; consecutive runs cannot overlap.
  - Within a run: B rewrites its own part file from the recorded position, and A's UNIQUE (`source_id`, `created_at`)
    makes a repeated insert a no-op.
  - Before starting, the run checks that no existing manifest (B) or archive row (A) covers its range.
- **Concurrent inserts.** New refusals get ids above `high_id` and a `created_at` of "now", far after the cutoff. Both
  predicates exclude them, so nothing being written is ever copied or deleted. The writer path is never locked: all
  reads are consistent snapshot reads, and deletes touch only old id ranges.
- **Partial failures.**

  | Failure | Effect |
  |---|---|
  | crash while copying | resume |
  | verify mismatch | stop, alert, no deletion |
  | crash while deleting | resume deleting; the verified archive already covers the range |
  | disk full while writing (B) | the copy fails, and the part file is discarded on retry |
  | database unavailable | exit non-zero; nothing is deleted |

- **Safe deletion.** Rows are deleted only after a successful verification of the same range, with no gap. Deletion can
  be disabled entirely (`delete_after_verify=false`) to run the archive in copy-only mode for the first weeks.
- **Integrity verification.** Row count, an order-independent per-row CRC32 XOR checksum (source vs archive), and the
  SHA-256 of the file in the manifest. A proposed `--verify <file>` re-checks any archive file on demand.

### 4.5 Ids and the initial cleanup

If the owner chooses `TRUNCATE` (§3), ids restart from 1.

- **B:** a manifest records `created_at` ranges as well as ids, and a run's range starts from the journal, which is
  reset with the table, so files stay unambiguous.
- **A:** keys on (`source_id`, `created_at`) rather than `id` alone.
- **Either way:** the first archive run after a cleanup starts with an empty journal.

### 4.6 Performance and disk usage

- **Weekly volume:** one week of refusals. Read with primary-key range scans in 5,000-row chunks, and deleted in
  5,000-row chunks with 50 ms pauses. Both take a single-digit number of seconds per 100,000 rows on MariaDB 10.11.
  Measure before deciding chunk sizes; the figure is indicative, from the telemetry rollup measurements.
- **Undo, binlog and replication:** proportional to the deleted rows; the chunks keep each transaction small.
- **Disk:**
  - B ≈ weekly rows × compressed bytes per row;
  - A ≈ weekly rows × InnoDB bytes per row;
  - plus 52 weeks of retention at 12 months.

  Example: at the incident-day refusal rate, if it repeated daily, a week of rows can be hundreds of MB raw. Real
  figures come from `downloads:rejections-report` on production.
- **Schedule (proposal, not configured):** daily at a quiet hour; the command decides whether a run is due.

### 4.7 Backup and recovery

- **B:** the archive directory is part of the file backup, with an off-host copy.
- **Restore** with the proposed import command into a scratch table, never straight into the live table, to avoid id
  collisions.
- **A:** covered by DB backups. The archive table has no `NonEmptyTableGuard`-style protection yet; if one is wanted,
  it is an owner decision.

### 4.8 Archive growth monitoring (additions to `downloads:monitor`)

New check `rejections_archive` (`disabled` while `archive.enabled=false`):

| Status | When |
|---|---|
| warn | the last completed run is older than 2 × `frequency_days`; or the oldest live row is older than `active_retention_days + 2 × frequency_days` (archiving is not keeping up); or the archive size passes a configurable threshold |
| critical | a `verify_failed` run; or an incomplete run older than 24 h |

Reported values: last run, rows and bytes archived, archive size, and archive file count.

## 5. Origin tracking (investigation only; no schema change made)

**What is already recorded.** Every row already identifies the material by its stable keys:

- `module` (`khotab`) and `resource_type` (`khotab_item`);
- `resource_id`: the Khotab item id. For a mirror, this is the **parent** item, validated as the mirror's `khid`;
- `variant` (`primary` or `mirror`), and `variant_id`: the mirror id;
- `resolved_download_url`: the link as stored **at refusal time**, which survives later link edits;
- `requested_url`: the public URL asked for.

These come from records the controller has already loaded (`KhotabItem` by primary key; `Mirror` by id and `khid`),
so they are reliable for every row written by the current code.

**What is missing.** A human-readable identity that survives the content changing:

- the item's title, and its series or category, are not stored;
- if an item or mirror is later deleted, or its title changes, an old rejection can no longer be resolved to "which
  lecture" by joining;
- the archive (§4) makes this more likely, because rows outlive the content they point to.

**Options, smallest first:**

| Option | Schema change | Request-path cost | Comment |
|---|---|---|---|
| 0. No change; snapshot at archive time | none on the live table | none | the archive run adds the item title (one PK lookup per distinct item per run) to each archived record; resolvable for as long as the item exists until archiving, which is at most 37 days after the refusal |
| **1. One nullable column `resource_title VARCHAR(255) NULL`** | additive `ALTER TABLE … ADD COLUMN … NULL` (instant ADD COLUMN on MariaDB 10.3+ for a trailing nullable column) | none for primary items if `title` is added to the controller's existing `select([...])`; for mirrors, one extra primary-key lookup of the parent **on the refusal path only** | the smallest change that makes each row self-describing at the moment of refusal |
| 2. `resource_title` + `resource_group_id` (series or category) | two nullable columns | as option 1 | only if reports by series or category are wanted |

**Recommendation:**

- **Option 0** if the archive is approved, because it needs no schema change at all.
- **Option 1** if rows must be self-describing even in the live table.
- No existing column is changed or removed in any option. **Nothing is implemented until the owner approves the
  schema.** If option 1 is chosen, `MainSchema::downloadRejections()` and `DownloadRejectionSchemaDriftTest` change
  together with the migration.

## 6. Outstanding owner decisions

1. **Historical cleanup:**
   - export first or not;
   - `TRUNCATE` or chunked `DELETE` (§3.2, chunked DELETE recommended);
   - timing.
2. **Active-table retention:** the proposed 30 days is **not approved**.
3. **Archive retention:** the proposed 12 months is **not approved**.
4. **Archive storage:** A (archive table) or B (compressed files, recommended); off-host copy yes or no, and where.
5. **IP and User-Agent in the archive:** keep raw for the archive retention, or pseudonymise at archive time (for
   example HMAC of the IP), which reduces the privacy exposure but also the forensic value.
6. **Rollout mode:** copy-only (`delete_after_verify=false`) for the first N runs (recommended), then deletion.
7. **Origin tracking:** option 0, 1 or 2 (§5).
8. **Archive monitoring thresholds and alert routing:** as in `download-monitoring.md`.
9. **Already-captured fields** (from the Phase 3C privacy review, L1): whether future rows should keep the full source
   URL with its query string, the full referer, and the raw User-Agent.
10. **The other open release gates in §1:** staging FastCGI validation, deployment approval for the behaviour
    changes, activation flags, and alert recipients.

## 7. What was not done (per the decision update)

- No historical records deleted.
- No archive table created.
- No archive job or command implemented.
- Nothing scheduled.
- No production access.
- No commit, push or deployment.
- No change to `NonEmptyTableGuard`, and `download_rejections` is not part of `downloads:telemetry-prune`.

## 8. Phase 3D update (2026-11-11): owner-approved archiving, implemented locally

**Approved decisions:**

| Decision | Value |
|---|---|
| Active retention | ≤ 20 days |
| Archive frequency | 7 days |
| Archive cutoff | 13 days (exact: run time − 13 days) |
| Archive retention | 4 calendar months from each archive's newest record |
| Raw IP and User-Agent | preserved in the archive |
| Format | compressed NDJSON with a verified manifest (option B, §4.2) |
| Historical cleanup | owner-controlled (§3, unchanged) |

**Implemented** (details in `docs/operations/download-rejections-archive.md`):

- the durable state machine `PENDING → COPYING → VERIFIED → DELETING → COMPLETED`, plus `VERIFY_FAILED` and
  `ABANDONED`;
- deletion only by the verified archive's own ids;
- inspect, restore (into `download_rejections_restored` only, with confirmation) and expire commands;
- the `rejections_archive` monitor check;
- the additive `resource_title` column;
- an optional backup-confirmation gate.

These §4 proposals were superseded by the approval: active retention 30 → **20** days; archive retention 12 → **4
calendar months**; cutoff "UTC day start − 30" → **exact run time − 13 days**. The day-aligned cutoff was found to
break the 20-day maximum by up to 23 h.

**Remaining owner prerequisites before activation:**

1. Apply the `resource_title` migration, or accept untitled rows until it is applied (the recorder tolerates it).
2. Decide `require_backup_confirmation` (default off) and the off-host copy; include the archive directory in backups.
3. Run the historical cleanup (§3), or let the first runs archive the history (up to 2,000,000 rows per run).
4. Create the archive directory with the host's user and group (`0750`/`0640`, or `0770`/`0660`), with free space for
   the 512 MiB reserve plus about 4 months of archives.
5. Enable it, run once by hand, and review it with `downloads:rejections-archive-inspect --verify`.
6. Schedule it daily (the command decides when a run is due). This is not configured.
7. Route the `rejections_archive` monitor alerts.

## 9. Phase 3D.1 update (2026-11-11): retention hardening

**Backup confirmation:**

- **Reviewed:** in 3D it was an operator acknowledgement by run id only. It was off by default, stored in the journal,
  and not tied to the archive's content.
- **Now:**
  - **on by default**;
  - names the run **and its manifest digest**;
  - accepted only for a VERIFIED run whose manifests on disk match and whose segments pass integrity;
  - persists across retries; audited with an optional backup reference;
  - **re-checked against the files on disk before any deletion**.
- **Not done, deliberately:** no automated backup verification exists, because there is no backup system to query.

**Four-month retention:**

- **Before:** an archive expired only when its newest record expired.
- **Now:** one segment per UTC creation day. Each record's deadline is 00:00 UTC on (creation date + 4 calendar months,
  month-end clamped), shared by its whole segment, so segments expire exactly on time without rewriting files.
- **Tested:** month ends, the leap day, and "none early, none late" at every boundary.
- **The only overshoot** is the scheduler delay; schedule expiry daily at 00:00–00:05 UTC.

**Storage estimate:** `downloads:rejections-archive-estimate` is read-only, with bounded queries and in-memory
compression of a sample. Its uncertainty is stated in the output.

**Additional prerequisites (owner):**

1. A weekly operator step to back up each archive run and confirm its digest. Until it is confirmed, the live table
   keeps the rows, past 20 days if the confirmation is late.
2. A daily expiry schedule at 00:00–00:05 UTC.
3. A production run of the estimate, to size the archive disk and its backups.

## 10. Production runbook

The authoritative, owner-controlled deployment and activation sequence, with operational checks, compatibility
checks, rollback procedures, production gates, the GO / NO-GO checklist and the unresolved owner decisions, is
`docs/operations/download-production-runbook.md`. It supersedes the shorter checklist in
`download-telemetry-production-readiness.md` §8; that checklist remains for detail.
