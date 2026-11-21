# Download rejections archive (Phase 3D)

**Status:** implemented locally. **Disabled by default** (`DOWNLOAD_REJECTIONS_ARCHIVE=false`) and **not scheduled**.
Historical cleanup remains owner-controlled and is not part of this feature.

## 1. Retention (owner-approved 2026-11-11)

| Setting | Value | Key |
|---|---|---|
| Active-table maximum | 20 days | `active_retention_days` |
| Archive frequency | 7 days | `frequency_days` |
| Archive cutoff | 13 days: rows with `created_at <` (run time − 13 days) are archived | `cutoff_days` |
| Archive retention | at most 4 **calendar months** per record: each record's deadline is 00:00 UTC on (its creation date + 4 months) (Phase 3D.1) | `retention_months` |

- **Why 13 days:** 13 + 7 = 20.
  - **Just after a run:** no live row is older than 13 days.
  - **Just before the next run (7 days later):** none is older than 20 days.
  - **The cutoff is exact, not aligned to a UTC day.** Day alignment would let a run late in the day keep rows up to 23
    h older, which was found and corrected while implementing this phase.
  - **Due time:** a run is due **1 hour before** the 7 days elapse (`DUE_TOLERANCE_SECONDS`), so a daily scheduler
    firing at the same minute never slips a whole day.
  - **Tests:** 8 weekly runs at 23:00 keep the oldest live row ≤ 20.0 days, and a scheduler firing 30 minutes early
    still finds the run due.
- **Refusal:** settings with `cutoff_days + frequency_days > active_retention_days` are refused.
- **Delayed or failed runs** let the live table exceed 20 days until the next successful run. A run that fires *late*
  adds its lateness directly; one missed week means up to 27 days. Monitoring reports `active_retention_exceeded`, and
  the backlog in rows.
- **Archive retention, per record (Phase 3D.1):**
  - **Deadline:** a record's deadline is `ArchiveFiles::deadline(created_at)` = **00:00 UTC on (its UTC creation date + N
    calendar months)**. When the target month is shorter, the day is clamped to the month's last day, with no overflow
    into the next month.
  - **At most 4 months:** a record is never kept longer than 4 calendar months. It is kept less by the time of day it
    was created, and by any month-end clamp, at most about 3 days at the end of February.
  - **Examples:**

    | Created | Deadline |
    |---|---|
    | 2026-10-31 15:00 | 2027-02-28 00:00 |
    | 2026-10-29 … 2026-10-31 | 2027-02-28 00:00 |
    | 2026-11-01 | 2027-03-01 00:00 |
    | 2027-01-31 | 2027-05-31 00:00 |
    | 2027-10-29 … 2027-10-31 | 2028-02-29 00:00 (leap year) |
    | 2028-02-29 | 2028-06-29 00:00 |

  - **Why day segments:** an archive run writes **one segment file per UTC creation day**, so every record in a segment
    has the same deadline. Expiry deletes a whole segment exactly at that deadline, without rewriting any compressed
    file:
    - nothing is deleted before its deadline;
    - nothing outlives it, beyond the delay until the next expiry run.
  - **What it corrects:** Phase 3D expired an archive only when its *newest* record expired, which kept older records
    in the same file up to a week too long.
- **Scheduler-delay limitations, stated explicitly:**
  - **Expiry:** expiry runs whenever `downloads:rejections-archive` (after a run) or `downloads:rejections-archive-expire`
    is invoked. A record outlives its deadline by exactly the time until the next invocation. Scheduling
    `downloads:rejections-archive-expire` **daily at 00:00–00:05 UTC** bounds that to minutes. If the scheduler is
    down, records stay until it returns: they are never deleted early, and the monitor reports
    `expired_archives_present`.
  - **Segments waiting on a run:** segments of a run that has not COMPLETED (awaiting backup confirmation, or failed)
    are not expired. Their rows are still in the live table at that point, so nothing is lost; the monitor reports it.
  - **Live table:** a run that fires late, or is skipped, lets the live table exceed 20 days by the delay (see above).

## 2. Format

| File | Content |
|---|---|
| `archives/{segment}.ndjson.gz` | one file per UTC creation day of each run: `{run_id}-{YYYYmmdd}`. gzip of NDJSON. One record per line, ordered by (`created_at`, `id`), the order of `idx_dr_created`; **every column** of the row (all existing columns plus `resource_title`, raw IP and User-Agent preserved), in table column order. Each non-null value is a JSON string; a value that is not valid UTF-8 (a raw User-Agent can be anything) is `{"$b64": "…"}`, so bytes round-trip exactly. |
| `archives/{segment}.manifest.json` | `format` `w2a-download-rejections-archive`, `version` 2, `archive_id` (the segment), `run_id`, `day`, `order`, `table`, `columns`, `rows`, `min_id`/`max_id`, `min_created_at`/`max_created_at`, `cutoff`, `high_id`, `content_sha256` (uncompressed NDJSON), `file_sha256` (.gz), `file_bytes`, `created_at`, `retention_months`, `expires_at` |
| `runs/{id}.json` | the run's durable journal (state machine) |
| `audit.log` | one JSON line per expiry, abandon, backup confirmation and restore |
| `tmp/`, `rejected/` | unpublished work files; abandoned archives kept for investigation (never deleted automatically) |

- **Ids:** runs are `rej-YYYYmmddTHHMMSSZ-<8 hex>`, unique; segments are `<run id>-<YYYYmmdd>`.
- **Location:** `storage/app/download-rejections-archive` by default. A directory inside `public/` is refused.
- **Permissions:** directories `0750`, files `0640` (configurable to `0770`/`0660`; "other" is never granted).

## 3. State machine and crash recovery

`PENDING → COPYING → VERIFIED → DELETING → COMPLETED`, plus `VERIFY_FAILED` (blocks new runs) and `ABANDONED`
(operator). The journal is replaced atomically (temp file, fsync, rename) at every transition and after every chunk.

| Stage | What happens | Crash recovery |
|---|---|---|
| PENDING | the **fixed selection** is recorded before any data moves: `cutoff` and `high_id` = `MAX(id)` | resume continues with the same selection |
| COPYING | keyset chunks (`id > last AND id ≤ high_id AND created_at < cutoff ORDER BY id LIMIT chunk_rows`) into `tmp/{id}.ndjson.gz.part`; at most `max_rows_per_run`, with the rest flagged as backlog (the next run is due at once) | the copy **restarts from scratch**: nothing was published or deleted |
| verify | the part file is re-read **from disk**: decompressed, counted, hashed, and **every record compared byte-for-byte with its row in the table** | a crash restarts the copy; a mismatch sets VERIFY_FAILED, with nothing published and nothing deleted |
| publish | data, then manifest, are renamed into `archives/` and the directory is fsync'ed; the manifest's presence means "published and verified" | a data file without a manifest is discarded and recopied; a manifest present while the journal says COPYING is re-verified and the run continues |
| VERIFIED | `require_backup_confirmation` is **on by default**: the run waits for a confirmation bound to its digest (§3a) | — |
| DELETING | the published file is **re-verified** (checksums, count); then **its own ids** are deleted in chunks: `id IN (…) AND id ≤ high_id AND created_at < cutoff`; progress is recorded per chunk | resumes at the recorded chunk; repeating a chunk deletes nothing more |
| COMPLETED | done; archive expiry may run | — |

The guarantees, and how they are met:

| Guarantee | How |
|---|---|
| Concurrent inserts | they get ids above `high_id` and are never read or deleted, even with an old `created_at` (tested) |
| Deletion | never by timestamp alone, always by the archive's own ids |
| No deletion before verification | enforced by the states, and re-checked just before deletion |
| No duplicates | one run at a time (host `flock` + MariaDB `GET_LOCK`); one incomplete run at a time (it is resumed before any new one starts); each run's set is fixed at start; deleted rows cannot be selected again |
| A failed verification | blocks further runs until an operator investigates and abandons it, so a corrupt published archive can never be followed by a second archive of the same rows |
| Errors | recorded as the exception class and code only: database messages embed row values |

## 3a. Backup confirmation (Phase 3D.1)

**What it is.** An **operator-controlled acknowledgement**. The system cannot see the backup system, so it does **not**
verify that a backup completed. It makes the acknowledgement precise and binding instead.

**How it works:**

- **Required by default** (`DOWNLOAD_REJECTIONS_ARCHIVE_REQUIRE_BACKUP=true`). Without a valid confirmation, a verified
  run deletes nothing; it reports `awaiting_backup` on every run, and the monitor warns
  `awaiting_backup_confirmation`.
- **The run digest:** `SHA-256` over the run's segment manifests (each segment id plus the SHA-256 of its manifest
  file). Each manifest in turn records its data file's SHA-256, row count and content hash, so the digest identifies
  every byte that will be relied on. It is printed by the run (`awaiting_backup`) and by
  `downloads:rejections-archive-inspect`.
- **Confirming:**
  `downloads:rejections-archive --confirm-backup=<run_id> --digest=<digest> [--reference="<backup job / snapshot id>"]`.
  It is accepted only if **all** of these hold:
  - the run exists and is VERIFIED;
  - the digest equals both the run's recorded digest **and** the digest of its manifests on disk now;
  - every segment passes its integrity check.

  There is no generic flag. A confirmation names one run and one digest, so it cannot authorize another run, nor this
  run's files if they change afterwards.
- **Storage:** the confirmation is stored in the run's journal, so it persists across retries and crashes; a deletion
  interrupted by a crash resumes without re-confirmation. It is also recorded in `audit.log` with the digest, the
  segments and the reference.
- **Re-checked before deletion:** before deleting anything, the confirmation's digest must still equal the digest of
  the manifests on disk. If not, nothing is deleted, the run stays VERIFIED with an error, and it must be confirmed
  again. A corrupted data file fails integrity first (VERIFY_FAILED).
- **Turning it off:** only with explicit owner approval (`DOWNLOAD_REJECTIONS_ARCHIVE_REQUIRE_BACKUP=false`).

**Operational consequence.** With confirmation required, the 20-day live-table maximum holds only if the operator
confirms each weekly run promptly. Every day of waiting adds a day.

## 3b. Storage estimate (Phase 3D.1)

`php artisan downloads:rejections-archive-estimate [--sample=2000] [--days=30] [--backup-copies=1] [--json]`

- **Read-only:** it works while archiving is disabled, writes nothing and exports nothing.
- **Bounded:** no full-table scan. On MariaDB, every query was EXPLAINed with no full scan. It uses:
  - table statistics from `information_schema`;
  - the newest `sample` rows by primary key, encoded exactly as archived and **gzip-compressed in memory** (only the
    resulting sizes are reported);
  - daily volume from the `created_at` index.
- **Reported:**
  - raw and compressed bytes per row, and InnoDB bytes per row;
  - average and busiest-day volume;
  - archive growth per day;
  - steady-state archive size at the longest 4-month window (124 days), average and peak;
  - one run's temporary space;
  - the live table at its 20-day maximum;
  - backup bytes;
  - the disk reserve, and the recommended free space on the archive disk.
- **Uncertainty (stated in the output as `confidence`):**
  - Compression depends on how repetitive real rows are. On the synthetic test data, gzip reaches about 27 bytes per
    row (from about 705 raw); real User-Agents and URLs will compress less.
  - The sample is the newest rows only.
  - Peaks assume the busiest recent day repeats for the whole window.
  - With no rows, 120 bytes per row is **assumed** and flagged.

## 4. Commands

| Command | Effect |
|---|---|
| `downloads:rejections-archive [--force] [--no-expire] [--json]` | run or resume an archive, then expire due archives. Exit 1 on failed, blocked or refused |
| `downloads:rejections-archive --confirm-backup=<run_id> --digest=<digest> [--reference=…]` | confirm the external backup of exactly that run's verified segments (§3a) |
| `downloads:rejections-archive-estimate [--sample] [--days] [--backup-copies] [--json]` | **read-only** storage estimate (§3b) |
| `downloads:rejections-archive --abandon=<id>` | give up a VERIFY_FAILED run; its files move to `rejected/`, nothing is deleted |
| `downloads:rejections-archive-expire [--dry-run] [--json]` | expire **segments** at their records' deadline: COMPLETED runs only, bounded per run, audit line (with the deadline) **before** deletion |
| `downloads:rejections-archive-inspect [id] [--verify] [--json]` | **read-only**: list archives (rows, record dates, expiry, size, state), show one manifest and journal, or `--verify` integrity |
| `downloads:rejections-archive-restore <id> [--dry-run] [--confirm=<id>]` | controlled restore (§5) |

Every command except inspect does nothing while archiving is disabled.

## 5. Restore procedure

1. `downloads:rejections-archive-inspect <id> --verify`: confirm the archive is intact.
2. `downloads:rejections-archive-restore <id> --dry-run`: it reports the rows, how many would be inserted, the
   duplicates and the conflicts. It changes nothing; not even the target table is created.
3. `downloads:rejections-archive-restore <id> --confirm=<id>` (or answer the interactive prompt).
   - The integrity is checked first.
   - Rows go into **`download_rejections_restored`**, created on MariaDB with `CREATE TABLE … LIKE download_rejections`
     (same columns, keys and indexes). **They never go into the live table**, so a restored row is never re-archived,
     never mixed with new refusals, and never collides with a reused id.
   - Inserts are bounded (≤ 1,000 rows per statement).
   - An existing identical row is skipped as a duplicate; a different one is a conflict and is never overwritten.
   - The restore is audited.
4. When the investigation is finished, the owner drops `download_rejections_restored`. That is a manual, separate step.

## 6. Monitoring (`downloads:monitor` → `rejections_archive`)

| Status | When |
|---|---|
| disabled | archiving disabled |
| warn | no successful run yet; ≥ 8 days without one; a failed or interrupted run (an error recorded, or no progress for 24 h); oldest live row > 20 days; expired segments still present; a run awaiting backup confirmation |
| critical | ≥ 14 days without a successful run; any VERIFY_FAILED run |

Reported values:

- the last successful run and its age;
- the oldest active rejection and its age;
- the backlog (rows past the cutoff, counted up to 100,000);
- verification failures and incomplete runs;
- the archive count and bytes;
- the expired backlog.

The check is read-only: it never creates a directory or takes the archive lock.

## 7. Performance (local, disposable MariaDB 10.11)

| Measurement | Result |
|---|---|
| 12,000 rows (3 chunks), copy + verify + publish + delete, with concurrent inserts | 1.4–1.5 s |
| Compressed size | 27 bytes per row on repetitive synthetic data; real rows will compress less. Expect roughly 60–150 bytes per row, and measure with `downloads:rejections-report` and the first archive |
| `ADD COLUMN resource_title VARCHAR(255) NULL` | accepted with `ALGORITHM=INSTANT` on a 2,000-row production-shaped table |

## 8. Origin tracking: `resource_title`

- **Migration** `2026_11_11_300000_add_resource_title_to_download_rejections`:
  - one nullable `VARCHAR(255)`, utf8mb4;
  - all existing columns are preserved; existing rows get NULL;
  - idempotent;
  - skipped under tests, like its sibling;
  - `down()` refuses to drop the column while any row holds a title.
- **What it records:** the material's title **at refusal time**, from the record already loaded, so there is **no
  extra query** on the refusal path.
  - Primary item: `title` was added to the controller's existing `select`.
  - Mirror: the parent item's title rides in the same query as a primary-key subselect.
- **Deployed ahead of the migration:** the recorder retries the INSERT once without the column on "unknown column", so
  refusals are never lost.

## 9. Production prerequisites (owner)

1. Apply the `resource_title` migration (instant ADD COLUMN), or accept rows without titles until then.
2. Backup: `require_backup_confirmation` is **on**. Set up the external backup of the archive directory, and the weekly
   operator step of confirming each run with its digest (§3a). Turning the gate off requires owner approval.
3. Create the archive directory on a local disk with the right owner and group: `0750` if web and cron share a user,
   otherwise `0770` with `DOWNLOAD_REJECTIONS_ARCHIVE_DIR_MODE` and `..._FILE_MODE`. Allow free space for the
   `min_free_bytes` reserve (512 MiB) plus about 4 months of archives.
4. Run the owner's one-off historical cleanup first (`download-system-release-readiness-review.md` §3), or let the first
   run archive the history. The first run may then need several runs, at `max_rows_per_run` (2,000,000) each.
5. Enable `DOWNLOAD_REJECTIONS_ARCHIVE=true`, run once by hand, and review it with `downloads:rejections-archive-inspect --verify`.
6. Schedule `downloads:rejections-archive` **daily** (the command decides when a run is due) with
   `withoutOverlapping`, at a quiet hour. Schedule `downloads:rejections-archive-expire` **daily at 00:00–00:05 UTC**.
   Neither is configured.
8. Run `downloads:rejections-archive-estimate --json` on production, read-only, to size the archive disk.
7. Wire `rejections_archive` into the monitoring agent with the routing in `download-monitoring.md`.
