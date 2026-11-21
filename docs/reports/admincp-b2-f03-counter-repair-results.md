# AdminCP B2 / F03-A: series counter repair command — results

**Date:** 2026-11-16.
**Approval:** "B2 / F03-A — Implement a Safe, Audited Counter Repair Command". Local code and testing only.
**Basis:** `docs/reports/admincp-b2-f03-resolution-proposal.md` (scope §1.2, procedure §6).
**Decision log:** #145.
**Status:** **command implementation CLOSED LOCALLY (2026-11-16) after validation passed. The production data repair has NOT been run and is NOT
complete.**

**What was done:**
- No production access and no request to any host.
- **No write to the shared local database.** The command was run there once in its default read-only dry run (§3).
  The `admin_audit_log` row count and `CHECKSUM TABLE nuke_islamic_series` were identical before and after, and no
  backup directory was created.
- No migration, no change to the legacy trees, nothing committed, pushed or deployed.
- «إصلاح العدادات» and the public visibility queries are untouched.

---

## 1. Command

```text
php artisan khotab:repair-f03-series-counts
    [--apply]                 write the repair (default: dry run, read-only)
    [--rollback=<file>]       restore the original counts from a backup (dry run unless --apply)
    [--skip-conflicts]        with --rollback --apply: restore unchanged rows, leave conflicting rows untouched
    [--actor=<aid>]           AdminCP aid the write is attributed to (required for any write)
    [--backup-dir=<dir>]      backup directory (default storage/app/maintenance/khotab-f03a, git-ignored)
    [--json]                  machine-readable report
```

**Exit codes:**
- `0`: ready, applied, already repaired, or rolled back without conflict.
- `1`: blocked, refused, failed, or any rollback conflict.

**Files:**

| File | Role |
|---|---|
| `app/Domain/Admin/Actions/Khotab/F03SeriesCountRepair.php` | **new**: baseline, plan, apply, backup, rollback |
| `app/Console/Commands/KhotabRepairF03SeriesCounts.php` | **new**: the command (auto-discovered) |
| `tests/Feature/Admin/KhotabF03SeriesCountRepairTest.php` | **new**: 28 tests |
| `docs/decision-log.md` (#145), `docs/reports/admincp-feedback-implementation-plan.md`, this report | docs |

No other code changed.

## 2. What it checks and writes

**Approved baseline** (constant `F03SeriesCountRepair::BASELINE`): exactly 22 series. For each:
- identity: `author_id`, `group_id`, `vedio`, `old_id`; `hidden = 0` is required;
- the approved current count;
- the approved repaired count.

Expected total: **316** own items. Nothing is discovered at run time.

**Recount (per series):** items whose `ser_id` is the series and whose `author` is the series' author, **hidden
included**. Other authors' items in the same series are never counted (for example the one foreign item in 15625).
At least one own item must be visible.

**Row states:**

| State | Meaning |
|---|---|
| `pending` | identity intact; stored = approved current; recount = approved repaired; a visible own item exists |
| `repaired` | as above, but stored = approved repaired |
| `missing` | the series row no longer exists |
| `identity_changed` | author, group, media type, `old_id` or `hidden` differs |
| `group_mismatch` | the group is gone, or no longer the author's or the same media type |
| `items_changed` | the recount differs from the approved value |
| `no_visible_items` | every own item is hidden |
| `count_changed` | stored count is neither the approved current nor the approved repaired value |

**Verdicts:**
- **`ready`**: all 22 are `pending` and the total is 316.
- **`already_repaired`**: all 22 are `repaired`. "No repair necessary", nothing written.
- **`blocked`**: any other state, the wrong total, or **a mix of repaired and unrepaired rows**. A partially
  repaired state is never assumed safe.

**`--apply`**, in this order (any failure ends with nothing written):
1. The actor must exist and hold super-admin or `khotab.editauthor` (`AdminRecordAuthorizer`).
2. Dry-run plan; it must be `ready` (`already_repaired` → exit 0, no write).
3. **The backup is written and verified** (§4). If it fails, the command aborts.
4. **One transaction** on `main`:
   - the 22 series rows are read `FOR UPDATE` and the **whole plan is re-validated** under the lock, recounting
     items;
   - then each row gets
     `UPDATE nuke_islamic_series SET count = <approved> WHERE id = ? AND author_id = ? AND group_id = ? AND vedio = ? AND hidden = 0 AND old_id = ? AND count = <approved current>`.
     Each must affect exactly 1 row, otherwise the transaction rolls back;
   - one `admin_audit_log` row per series: `repair_series_count`, module `khotab`, entity `series`, metadata
     `{from, to, reason: b2_f03a, backup_sha256}`.
5. After commit, the plan must be `already_repaired`.

**Only `nuke_islamic_series.count` is written.** `lastupdate`, `group_id`, `author_id`, `hidden`, `old_id`, every
item row, group counters and author counters are untouched; tests compare full snapshots.

**Concurrency:**
- Item rows are read without locks (a consistent read; locally MySQL scans about 290 k rows, sub-second).
- A concurrent Laravel item insert or move updates the series row in its own transaction, so it waits for the lock
  and applies its `±1` on top of the repaired value.
- **Residual:** OLD's non-transactional insert commits the item before its `count+1`. If it lands exactly between the
  recount and the update, the counter ends one too high. This needs OLD activity on these 22 series during the
  sub-second window. To avoid it, run at a quiet time; the post-check and a later dry run show it.

## 3. Dry-run output (shared local database, read-only)

```text
DRY RUN (no database write)
| series | author | group | stored | approved before | recomputed | visible own | approved after | state   | eligible |
| 15346  | 215    | 0     | -1     | -1              | 25         | 25          | 25             | pending | yes      |
| 15404  | 154    | 1138  | -1     | -1              | 8          | 8           | 8              | pending | yes      |
| 15495  | 154    | 1138  | 0      | 0               | 30         | 30          | 30             | pending | yes      |
| 15496  | 154    | 1138  | 0      | 0               | 30         | 30          | 30             | pending | yes      |
| 15498  | 154    | 1138  | 0      | 0               | 30         | 30          | 30             | pending | yes      |
| 15503  | 154    | 1138  | 0      | 0               | 30         | 30          | 30             | pending | yes      |
| 15509  | 154    | 1138  | 0      | 0               | 15         | 15          | 15             | pending | yes      |
| 15510  | 154    | 1138  | -1     | -1              | 27         | 27          | 27             | pending | yes      |
| 15561  | 16     | 0     | -2     | -2              | 26         | 26          | 26             | pending | yes      |
| 15616  | 154    | 1138  | -15    | -15             | 15         | 15          | 15             | pending | yes      |
| 15625  | 154    | 0     | -20    | -20             | 2          | 2           | 2              | pending | yes      |
| 15627  | 154    | 232   | 0      | 0               | 24         | 24          | 24             | pending | yes      |
| 15681  | 200    | 0     | -2     | -2              | 1          | 1           | 1              | pending | yes      |
| 15743  | 772    | 0     | -5     | -5              | 6          | 6           | 6              | pending | yes      |
| 15746  | 211    | 0     | -9     | -9              | 2          | 2           | 2              | pending | yes      |
| 15752  | 763    | 0     | -4     | -4              | 7          | 7           | 7              | pending | yes      |
| 15756  | 763    | 0     | -39    | -39             | 7          | 7           | 7              | pending | yes      |
| 15765  | 588    | 0     | -4     | -4              | 1          | 1           | 1              | pending | yes      |
| 15766  | 195    | 1298  | -3     | -3              | 3          | 3           | 3              | pending | yes      |
| 15884  | 22     | 0     | -32    | -32             | 1          | 1           | 1              | pending | yes      |
| 15949  | 154    | 1138  | 0      | 0               | 13         | 13          | 13             | pending | yes      |
| 15950  | 154    | 1138  | -1     | -1              | 13         | 13          | 13             | pending | yes      |
Own items: 316 (expected 316). Verdict: ready
```

Discrepancies, when present, print as `Discrepancy: series <id>: <state>` with `Verdict: blocked` and exit `1`.

## 4. Backup

- **Format:** one JSON file:
  `format: w2a.khotab.f03a.series-count-backup`, `version: 1`, `created_at` (UTC), `actor_aid`,
  `connection: main`, `database`, `total_own_items`, and `series[22]` with `{id, author_id, group_id, vedio, old_id,
  count_before, count_after}`. `sha256` covers the canonical JSON of all other fields. No credentials or host are
  stored.
- **Location:** `storage/app/maintenance/khotab-f03a/series-counts-<UTC timestamp>-<random>.json` (or
  `--backup-dir`).
  - Directory mode 0750, file mode 0640.
  - `storage/app` is git-ignored.
  - Copy the file off the server with the change ticket.
- **Durability:** written to a temporary file in the same directory, `fflush` + `fsync`, `rename` into place, then
  **read back and verified**: format, version, checksum, exactly the 22 approved ids, every value equal to the
  baseline. Any failure (directory cannot be created, not writable, short write, read-back mismatch) aborts
  **before** the transaction: "nothing was written".
- The file is written only when a repair is about to run. A dry run or an already-repaired state writes none.

## 5. Rollback

```text
php artisan khotab:repair-f03-series-counts --rollback=<backup file>                       # dry run
php artisan khotab:repair-f03-series-counts --rollback=<backup file> --apply --actor=<aid>
php artisan khotab:repair-f03-series-counts --rollback=<backup file> --apply --actor=<aid> --skip-conflicts
```

- **Backup check:** the file is verified as above. A tampered file (checksum), a foreign file or an unreadable file is
  refused, and so is a file whose values differ from the approved baseline.
- **Per series, under row locks in one transaction:**
  - **restorable**: the identity is intact and the count is still exactly the repaired value;
  - **restored**: it already holds the original value; nothing is done;
  - **conflict**: anything else, for example a legitimate item added since (count 25 instead of 24), or a changed
    identity.
- **Any conflict without `--skip-conflicts` → nothing is written** (exit 1). With `--skip-conflicts`, only restorable
  rows are restored and conflicting rows are left untouched (exit 1, reported).
- **Each restore:**
  - `UPDATE … SET count = <original> WHERE id = ? AND <identity> AND count = <repaired>`, exactly 1 row, or the
    transaction rolls back;
  - audited `rollback_series_count`.
- Repeating a rollback changes nothing.
- **Note:** rolling back makes the 22 series invisible to visitors again (that is the original state).

## 6. Tests

`tests/Feature/Admin/KhotabF03SeriesCountRepairTest.php`: **28 passed (686 assertions)**.
- Every test runs on the harness's isolated in-memory SQLite `main`, asserted in the last test.
- The fixture has the 22 series with their approved identities and counts, and 316 own items (19 of them hidden, still
  counted).
- It adds one foreign item in 15625 and four control series: a foreign-only series at 0, a count-without-items copy,
  a healthy series, and an **eligible-looking but unapproved** series.

| Requirement | Test |
|---|---|
| default dry run | prints all 22 with stored / approved / recomputed counts, `ready`; snapshot unchanged, no audit row, no backup directory |
| exact 22-series scope | JSON ids = the baseline; the eligible-looking control (17001, count 0 with own visible items) is never touched |
| ownership / visibility | the foreign item is not counted (15625 → 2); hidden own items counted (15346 → 25, 24 visible); all-hidden → `no_visible_items`, refused |
| controlled repair | 22 counts set; **full snapshot of every other series column, every item and every group unchanged** (incl. `lastupdate`, `group_id`, item `ser_id`); 22 audit rows with actor and backup checksum; backup verified, mode 0640 |
| transaction rollback on failure | a trigger aborts the 12th update (15627): all 11 earlier updates rolled back, no audit row |
| changed baseline | `count_changed` → refused, no backup, nothing written |
| missing series | `missing` → refused |
| unexpected item changes | an own item added / moved away → `items_changed`; identity changes (group, hidden, `old_id`), group ownership → refused |
| partially repaired | one row at its repaired value, the rest original → refused |
| repeated execution | the second apply: "No repair necessary", no write, no new audit row or backup |
| backup creation failure | the directory cannot be created → refused before any write |
| actor | missing, unknown, or without `khotab.editauthor` → refused; an admin with `khotab.editauthor` → allowed |
| rollback | dry run then apply restores all 22 (audited); a repeat is a no-op |
| conflicting rollback values | one row changed after the repair → rollback refused, nothing written; `--skip-conflicts` restores 21 and leaves the changed row |
| tampered backup | edited value (checksum), foreign JSON, missing file → refused |

**Related:** the F03-A file, `KhotabMainPanelParityTest` (the existing «إصلاح العدادات») and `tests/Architecture`:
**50 passed**.

**Full suite** (`timeout 1500 vendor/bin/pest --log-junit … < /dev/null`): **3,866 tests: 3,827 passed, 39 skipped, 0 failures, 0 errors** (32,723 assertions, ~400 s), exit 0, no hang (3,838 before + the 28 new tests).

**PHPStan:** passed, 0 errors. **Pint:** passed.

## 7. Production prerequisites (not done; each needs owner action)

1. **Deploy** the two new classes (no migration, no configuration).
2. **Production dry run first** (read-only): `php artisan khotab:repair-f03-series-counts`.
   - It must print `Verdict: ready` with all 22 `pending` and 316 items.
   - **Any discrepancy: stop.** Production is not in the local state; report back. The baseline is never edited to
     fit.
3. Pick an **actor** (super-admin or `khotab.editauthor`) and confirm `storage/app/maintenance/khotab-f03a` is
   writable by the deploy user and persistent (not wiped by deploys).
4. Take the usual **database backup** (independent of the command's own row backup).
5. Run at a **quiet time** (§2, OLD insert residual): `… --apply --actor=<aid>`.
6. **Keep the backup file** (copy it off the server with the change ticket); note its path and sha256 from the audit
   rows.
7. **Verify:**
   - a second dry run shows `already_repaired`;
   - `khotab-group-1138`, `-232` and `-1298`, and the affected authors' pages, list the series and open onto their
     items;
   - Gen 2 / OLD show them too (same `count > 0` rule).
8. **Rollback** if needed: §5.

**Not covered by this command** (proposal §1.5, separate decision):
- 8,187 foreign items in 565 series;
- 1,349 orphaned items;
- 19 copy series 16082–16100 with false counts;
- 4 other negative counters.

**Still open:** F03-B (admin view on the group page) and `lastupdate` (left unchanged, as approved).
