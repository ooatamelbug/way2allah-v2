# AdminCP B2 / F03: root cause and resolution proposal

**Date:** 2026-11-16. **Phase:** investigation and recommendation only.
**Basis:** `docs/reports/admincp-b2-investigation.md` §1, which this report **refines and partly corrects**.

No code, schema or data was changed. Every database statement in this report was a `SELECT` / `EXPLAIN` against the
local `olddb` copy. One of my own long-running analysis `SELECT`s, into a session temporary table, was killed with
`KILL <its own thread>`; nothing else on the server was touched. **All counts below are from the local copy.
Production must be re-checked read-only before any repair** (§6.2 step 1).

---

## 0. Summary

- **The B2 "45 series" figure mixed three different things.** Only **7** of the 45 are genuinely broken counters.
  - **25** of the 45 are hidden series, so visitors never see them whatever their count.
  - **38** of the 45 contain **no item of their own**, only other authors' items (§1.3). For those, `count = 0` is
    right.
- **Correct F03-A scope: 22 visible series** whose `count` is `0` or **negative** while they hold their own visible
  items: 316 items, 10 authors. The 15 negative counters were missed by the B2 investigation, which looked only at
  `count = 0`.
- **Root cause (historical, evidenced):** a renumbering of 680 series by +800 ids around **2025-09-15/16**. This
  renumbering is not done by any code in Laravel, Gen 2 or OLD. It:
  1. collided with ids already referenced by 8,187 older items;
  2. left 1,349 items pointing at series ids that no longer exist;
  3. created 19 series copies whose counts describe items that were never remapped to them;
  4. is where the 22 counters are wrong.

  **No currently reachable code path creates such a counter**; series created since 2025-09-16 are all consistent.
- **The existing «إصلاح العدادات» must NOT be used for this.** For author 154 alone it would:
  - inflate 82 series with foreign items and list 8 of them although their pages are empty;
  - **overwrite `group_id` on 1,149 items belonging to 44 other authors**.
- **Recommendation:** a **targeted, guarded counter repair of the 22 series**: one `UPDATE` of `count` only, on 22
  primary-key rows. **No read-path change.** F03-B (admin view of empty series) is a separate code change, recommended
  with `khotab.editseries`. The wider renumbering damage (§1.5) needs its own reconciliation decision.

---

## 1. Verified findings

### 1.1 The visibility rule (unchanged from B2, CONFIRMED)

**Laravel** lists a series only when `ser.count > 0` (plus `hidden = 0`, and the group not hidden). It does this on
three surfaces (`ContentListingService`):
- `seriesByAuthorAndGroup()` (:241): group page, author page standalone series;
- `seriesByCategoryAndGroup()` (:290): category pages;
- :805.

**OLD `ListSeries()`** and **Gen 2 `ListSeries()`** use the same visitor rule. The series page itself
(`khotab-series-{id}`) does not check `count`; it lists items matching the series' author, group and media type
(`khotabItemsDefault()`).

### 1.2 The 22 series that are wrongly invisible (CONFIRMED, local)

These are visible series with `count ≤ 0` that hold visible items of their **own author**. The proposed `count` is
their own items (hidden included, as every writer counts; none are hidden). It equals what the series page shows.

| Series | Author | Group | Current `count` | Proposed `count` | Note |
|---|---|---|---|---|---|
| 15346 | 215 | 0 | −1 | 25 | |
| 15404 | 154 | 1138 | −1 | 8 | |
| 15495 | 154 | 1138 | 0 | 30 | |
| 15496 | 154 | 1138 | 0 | 30 | |
| 15498 | 154 | 1138 | 0 | 30 | |
| 15503 | 154 | 1138 | 0 | 30 | |
| 15509 | 154 | 1138 | 0 | 15 | |
| 15510 | 154 | 1138 | −1 | 27 | |
| 15561 | 16 | 0 | −2 | 26 | |
| 15616 | 154 | 1138 | −15 | 15 | |
| 15625 | 154 | 0 | −20 | 2 | also holds 1 foreign item (not counted) |
| 15627 | 154 | 232 | 0 | 24 | B2's main example |
| 15681 | 200 | 0 | −2 | 1 | audio |
| 15743 | 772 | 0 | −5 | 6 | audio |
| 15746 | 211 | 0 | −9 | 2 | |
| 15752 | 763 | 0 | −4 | 7 | |
| 15756 | 763 | 0 | −39 | 7 | |
| 15765 | 588 | 0 | −4 | 1 | |
| 15766 | 195 | 1298 | −3 | 3 | |
| 15884 | 22 | 0 | −32 | 1 | audio |
| 15949 | 154 | 1138 | 0 | 13 | |
| 15950 | 154 | 1138 | −1 | 13 | |

- **Totals:** 22 series, **316 items**, authors 154 (11 series), 215, 16, 200, 772, 211, 763 (2), 588, 195 and 22.
- **Groups:** 1138 (9 series), 232, 1298, or none.
- All 22 are renumbered series (`old_id = id − 800`), created 2025-03-20 → 2025-08-27.

### 1.3 Why the other 38 of the B2 "45" are not counter bugs (CONFIRMED)

For each of those 38 series, every item with its `ser_id` belongs to **another author**. Examples:
- 15314 (author 589) holds 10 items of author 748;
- 15322 (629) holds 45 items of 748;
- 15916–15920 (154) hold items of authors 754, 4, 124 and 200.

These items date from 2024; their series were created in 2025.
- The series page filters them out (author mismatch). So `count = 0` correctly describes the series' own content,
  and listing these series would show an empty page.
- 25 of the 45 are hidden series anyway.
- This is the B2 "D" class (items excluded from their own series page). It is a renumbering artifact (§2), not a
  counter defect.

### 1.4 Other counter findings

- **19 series have negative counters** (minimum −73); 15 of them are in §1.2.
- **16087 and 16088** (positive count, no items) belong to **19 series, 16082–16100**. These were created on
  2025-09-16/17 with positive counts and **no** admin-log entries. Each is a copy, at **id + 800**, of a series that
  no longer exists:
  - same author, same count, same title (for example 16082 "المحراب", author 15, 26 = missing 15282's 26 items);
  - their items still point at the old ids 15282–15300.

  Listed for visitors but empty. **Not part of F03-A**: fixing them means remapping items (§1.5).
- **Series created after the renumbering (896, since 2025-09-16):**
  - 874 have `count` equal to their item count; **0** have count ≤ 0 with items; **0** contain foreign items;
  - the other 22 are the 19 copies above plus 3 off by one (16407, 16954, 16958: count = items + 1).

### 1.5 Wider renumbering damage (reported, not proposed for repair here)

| Finding | Count |
|---|---|
| Items inside an unrelated series (foreign author, item older than its series) | **8,187 items in 565 series**, all created in 2025 or later |
| Items whose `ser_id` names no series at all | **1,349 items, 91 ids (15207–15300)**; 18 of those ids have an id + 800 copy (§1.4), 73 have none |
| Series created 2024-02-07 → 2025-03-16 present locally | **0**, although 14,405 items created in that window carry a `ser_id` |

## 2. Root-cause evidence

### 2.1 Historical: the series renumbering (strongly evidenced; executor unknown)

1. **Ids are not monotonic in time.** The last series created before 2025 has id **14513** (2024-02-06). Then 15314
   was created 2025-03-17, but 15301–15313 were created 2025-09-18 → 21.
2. **`old_id = id − 800` for exactly 680 series** (ids 15314–16006, created 2025-03-17 → 2025-09-15). No other series
   carries an `old_id`.
3. **The admin log recorded the original ids.** Example: `farog` created series **14827** on 2025-04-21 and added
   items to 14827 from 2025-04-24. That series is now **15627** (`old_id` 14827).
   - Of 1,679 `add_series` log rows, 680 match a series by `old_id` and 877 by `id`.
   - Admin logging itself begins 2025-03-17, the moment the first renumbered series was created.
4. **The renumbering happened between 2025-09-15 17:02** (last renumbered series) **and 2025-09-16 19:58** (first
   series after it). The renumbered series' own items were remapped (11,599 items; none still point at an
   `old_id`).
5. **The target range was already occupied:**
   - 8,187 items from 2024 reference ids 15314–16006 that now belong to the renumbered series;
   - 1,349 more reference ids 15207–15300, which have no series.

   This is consistent with the 2024 series rows having been lost (none created 2024-02-07 → 2025-03-16 exist
   locally) while their items survived.
6. **The 22 counters:**
   - 15627's 24 `add_item` log rows under 14827 mean its counter reached 24 through OLD's insert path. Its
     `lastupdate` (2025-05-21 09:41:13) equals its last item's time, which shows that `+1` ran.
   - It is 0 now, so the counter was lost **at or after** the renumbering.
   - The negative counters are 0-based counters decremented by later legitimate moves and deletes (971 `move_item`
     log rows since 2025-03).

**Not verified (not invented):**
- who ran the renumbering, and with what tool: no code in Laravel, Gen 2 or OLD writes `old_id` or renumbers series,
  and no project document describes it;
- why 22 of the 680 counters were lost while 560 survived;
- **whether production matches this local copy.**

### 2.2 Reachable code paths (traced)

| Path | OLD (local) | Laravel | Gen 2 | Can create a wrong series counter? |
|---|---|---|---|---|
| Add item to series | `ConfirmInsertKhotab` :1077 then `count+1, lastupdate` :1110 | `InsertKhotabSeriesItemAction` `count + 1` | no writer | **Yes, OLD only:** OLD re-selects the new id by `author, link, time` and `die()`s **before** `count+1` if that fails (:1099–1107). Not observed in data since 2025-09-16 |
| Add item to group / author | no series counter (ser_id 0) | same | — | no |
| Move item | `count−1` / `count+1` :1337 / :1340 | `MoveKhotabItemAction` ±1 | — | no, but carries a wrong counter forward |
| Detach item | `count−1` :1515 | `DetachKhotabItemAction` | — | same |
| Delete item | `count−1` :1764 | `DeleteKhotabItemAction` | — | same |
| Hide / unhide item | no counter change | same | — | no (counter includes hidden items) |
| Edit series (group change), detach series, delete group | items' `group_id` only | same | — | no (`count` is by `ser_id`) |
| Delete series | refused while items exist | same | — | no |
| Repair («إصلاح العدادات») | `RepairCount()` :3790 | `RepairKhotabAuthorCounters*` | — | **yes, inflates** (counts foreign items; §3.1) |

- **Confirmed minor defect (preserves, does not create):** the decrements have no floor, so a counter that is
  already 0 goes negative. This is how 15 of the 22 became negative.
- **Confirmed latent OLD risk:** the `die()` between insert and `count+1`. It cannot be ruled out for single items.
  It does not explain the 2025 pattern (whole series at 0, and 15627 shows the `+1` ran).

## 3. Approach A versus Approach B

### 3.1 A: repair stored counters

**A1, the existing «إصلاح العدادات» (`RepairKhotabAuthorCountersAction`, `khotab.editauthor`): NOT SAFE.**

| Question | Answer |
|---|---|
| Tables / columns written | `nuke_islamic_authors.audio`, `.vedio`; for **every** series of the author `nuke_islamic_series.count`; **`nuke_islamic_khotab.group_id` of every item with that `ser_id`**; `channel_id` of those items when the series has a channel; every group's `count` |
| Correct criterion? | **No.** `count` = all rows with the `ser_id`, **including other authors' items** (OLD semantics, which predate the collision) |
| Targetable? | per author, or all authors; **not** per series |
| Unrelated changes (author 154 alone, local) | 139 series counts change; **82 inflated by foreign items**; **15 series become listed, 8 of them with empty pages**; **1,149 items of 44 other authors get author 154's group ids** (plus 195 own items); 13 group counts; author totals 1,713 / 29,349 → 1,527 / 31,566 |
| Transaction / audit | one transaction, audited |
| Idempotent | yes, but every run re-propagates `group_id` |
| Load | per series: 3 statements on `nuke_islamic_khotab` by `ser_id`, which has no leading index; 139 series change for author 154 alone, so a long transaction holding row locks on many items |
| Legacy compatibility | same tables; but the `group_id` overwrite **moves other authors' items into author 154's groups for every system** |

**A2, a targeted counter repair (recommended).**
- **Scope:** `count` on the 22 series only. Value = own-author items with that `ser_id`, hidden included. This is the
  convention every writer follows; it ignores foreign items.
- **Footprint:** no other column or table. 22 primary-key row updates, milliseconds.
- **Idempotent:** guarded by `count <= 0`, so a second run changes nothing.
- **Triggers:** `trg_series_after_update` acts only when `cat` changes, so it is a no-op here.

### 3.2 B: change the series visibility logic

- **SQL:** on the three surfaces, replace `ser.count > 0` with
  `(ser.count > 0 OR EXISTS (SELECT 1 FROM nuke_islamic_khotab k WHERE k.author = ser.author_id AND k.vedio = ser.vedio AND k.ser_id = ser.id AND k.group_id = ser.group_id AND k.hidden = 0))`.
  - Using the series page's own filter matters: a plain `ser_id` existence check would list the 38 foreign-only
    series with empty pages.
- **Query plan (`EXPLAIN`, group 1138):**
  - the subquery is a `ref` on `idx_khotab_author_listing` (all 4 columns): **one indexed probe per candidate series,
    inside the same query**;
  - no extra query per series and **no N+1**;
  - the series table is scanned as today (no index on `author_id`).
  - The group-1138 listing grows from 253 to 263 series: the 9 targets in that group plus 15404 (all in §1.2).
- **Pagination / caching:**
  - group lists are not paginated;
  - category lists are, and their total-count query must change identically;
  - none of these lists is cached (`ContentListingService` caches only the home lists).
- **Visibility rules:** if `OR`-ed, it only adds series. If it replaced `count > 0`, it would also hide the 19 copies
  and every series with only hidden items: a broader rule change.
- **Legacy divergence:** **Gen 2 and OLD still hide the 22**, so while they serve pages, visitors would see different
  lists per system.
- **Display:** cards would still show **«0 مادة» or «-15 مادة»** from the stored counter.
- **Carrying cost:** a permanent workaround in three queries for 22 historical rows.

### 3.3 Comparison

| | A1 per-author repair | **A2 targeted repair** | B read path |
|---|---|---|---|
| Fixes the 22 for Laravel | yes | **yes** | yes |
| Fixes them for Gen 2 / OLD | yes | **yes** | no |
| Card count correct | inflated where foreign items exist | **yes** | no (0 / negative shown) |
| Collateral data change | **1,149 foreign items regrouped, 82 inflated series** | **none** | none |
| Code change | none | none (or a small optional command, §6.3) | 3 queries + tests |
| Runtime cost | heavy one-off | 22 PK writes | extra indexed probe on every listing |
| Reversible | hard (group ids) | **trivially (22 values)** | revert code |

## 4. Performance considerations

- **A2:** a single `UPDATE … WHERE id IN (22 ids) AND count <= 0` locks 22 series rows by primary key, in
  milliseconds. Pre- and post-check `SELECT`s read ≈ 316 item rows (`ser_id IN (…)`). This is a full scan of
  `nuke_islamic_khotab` without an `ser_id`-leading index (≈ 290 k rows locally, a fraction of a second; a
  `(author, vedio, ser_id, group_id)` probe per series is also available). Run outside peak hours anyway.
- **A1:** many unindexed `ser_id` scans and wide row locks inside one transaction. Avoid.
- **B:** adds a dependent indexed probe per listed series on every uncached group, author and category request.

## 5. Legacy compatibility

- **A2** writes only `nuke_islamic_series.count`, the shared column that Laravel, Gen 2 and OLD all read with the same
  `count > 0` rule. All three systems show the 22 series immediately. Nothing they write is affected; future
  `+1` / `−1` operate on a correct base.
- **B** fixes Laravel only and intentionally diverges from Gen 2 and OLD. It would need a decision-log entry.
- **A1** changes shared item placement (`group_id`) for all systems.

## 6. Proposed implementation

### 6.1 F03-A: targeted counter repair (recommended; owner-run data operation, no code)

The values must be derived **on production**, never copied from this local report.

**Step 1 (read-only): identify on production.**

```sql
SELECT s.id, s.author_id, s.group_id, s.vedio, s.hidden, s.count AS current_count,
       SUM(k.author = s.author_id) AS own_items,
       SUM(k.author = s.author_id AND k.hidden = 0) AS own_visible,
       SUM(k.author <> s.author_id) AS foreign_items
FROM nuke_islamic_series s
JOIN nuke_islamic_khotab k ON k.ser_id = s.id
WHERE s.count <= 0 AND s.hidden = 0
GROUP BY s.id
HAVING own_visible > 0
ORDER BY s.id;
```

- **Expected:** the 22 rows of §1.2. If production differs, **stop** and report back. A different result means
  production is not in the local state; nothing is assumed.

**Step 2: back up exactly those rows.** No schema change; a row dump, kept with the change ticket.

```sh
mysqldump --single-transaction --no-create-info --skip-triggers \
  --where="id IN (<the ids from step 1>)" <db> nuke_islamic_series > f03a-series-before.sql
```

Also save the step-1 result (id, `current_count`) as the rollback source.

**Step 3: the repair, in one transaction.** Literal values come from step 1's `own_items`. The guard makes it
idempotent and refuses to touch a row that changed in between.

```sql
START TRANSACTION;
UPDATE nuke_islamic_series
SET count = CASE id
    WHEN 15346 THEN 25  WHEN 15404 THEN 8   WHEN 15495 THEN 30  WHEN 15496 THEN 30
    WHEN 15498 THEN 30  WHEN 15503 THEN 30  WHEN 15509 THEN 15  WHEN 15510 THEN 27
    WHEN 15561 THEN 26  WHEN 15616 THEN 15  WHEN 15625 THEN 2   WHEN 15627 THEN 24
    WHEN 15681 THEN 1   WHEN 15743 THEN 6   WHEN 15746 THEN 2   WHEN 15752 THEN 7
    WHEN 15756 THEN 7   WHEN 15765 THEN 1   WHEN 15766 THEN 3   WHEN 15884 THEN 1
    WHEN 15949 THEN 13  WHEN 15950 THEN 13
  END
WHERE id IN (15346,15404,15495,15496,15498,15503,15509,15510,15561,15616,15625,15627,
             15681,15743,15746,15752,15756,15765,15766,15884,15949,15950)
  AND count <= 0;
-- expect: 22 rows affected (or the number from step 1); otherwise ROLLBACK
COMMIT;
```

These values are the **local** ones and are illustrative: the production run uses step 1's values.

**Step 4 (verification, read-only):**
- re-run step 1, which must return 0 rows;
- `SELECT id, count FROM nuke_islamic_series WHERE id IN (…)` must equal the planned values;
- visit `khotab-group-1138`, `khotab-group-232`, `khotab-group-1298` and the affected authors' pages
  (`khotab-video-154` …): the series are listed, open onto their items, and the cards show the right count.

**Not changed:**
- `lastupdate`: 21 of the 22 have `lastupdate = 0`, so they sort **last** in their group (`ORDER BY lastupdate
  DESC`). Setting it would move them to the top and is a presentation choice (owner decision, §9).
- Group counts, author totals and every item row.

### 6.2 Rollback

- With step 1's values: `UPDATE nuke_islamic_series SET count = CASE id WHEN … THEN <current_count> … END WHERE id IN
  (…)`.
- Or replay `f03a-series-before.sql` (row dump: `INSERT`s; use `REPLACE`, or delete-and-reinsert those 22 primary
  keys, inside a transaction).
- Rolling back makes the 22 series invisible again; nothing else depends on the new values.

### 6.3 Optional: the same repair as audited Laravel code

If the owner wants it in `admin_audit_log` and repeatable, a small artisan command could perform it:
- `khotab:repair-orphaned-series-counts --dry-run` (default) / `--apply`;
- same criterion as step 1, one transaction, guard `count <= 0`;
- one audit row with before/after per series.

This is new code, ≈ 1 class and tests. **Not needed for 22 rows; owner choice.**

### 6.4 F03-B: admin visibility of empty series on the group page (design only; not implemented)

- **Today:** `KhotabGroupController::show()` passes no admin flag, so admins see the visitor list. OLD showed admins
  every series of the group, hidden and empty. The author page already does this for standalone series, behind
  `AdminViewer::active()` with the «++مخفي++» marker and a private response.
- **Proposed:** when `AdminViewer::can('khotab.editseries')` (super-admin passes):
  - list the group's series **including hidden and `count ≤ 0`** (an `includeHidden` / admin variant of
    `seriesByAuthorAndGroup()`, without `count > 0`);
  - mark them:
    - hidden: the existing «++مخفي++» (`media-collection-grid` `markHidden`);
    - empty: a new «فارغة» label, shown when the stored count is ≤ 0;
  - call `markRendered()`, so the response is private (`no-store`), as on the author page.
- **Visitors and admins without the permission:** exactly today's query and output.
- **Permission:** **`khotab.editseries`** («تعديل سلسلة»). An empty series is only useful to someone who can edit it
  or add items to it. This follows the B4 precedent: edit permission gates hidden-content preview.
  - **Alternative (OLD and author-page parity):** `active()`, any admin. The two Khotab pages would then behave the
    same.
- **Order:** F03-A first; otherwise the 22 series would show «فارغة» although they hold items.

## 7. Tests required

**F03-A (if done as SQL):** no code tests. Steps 1 and 4 are the acceptance checks.

**F03-A (if done as the §6.3 command):**
- dry run writes nothing;
- apply fixes only series with `count ≤ 0` and own visible items;
- foreign items are not counted;
- foreign-only series, hidden series, positive counters and other columns (`group_id`, `lastupdate`, group and
  author counters) are unchanged;
- a second run is a no-op;
- the audit row lists before/after;
- a concurrent change (`count > 0` by then) is skipped.

**F03-B:**
- visitor group page unchanged (empty, hidden and count ≤ 0 series not listed; same order);
- an admin with `khotab.editseries` and a super-admin see hidden and empty series, marked, with `Cache-Control`
  private / `no-store`;
- an admin without it sees the visitor view;
- order (`lastupdate DESC`) unchanged;
- hidden group still 404;
- no extra queries per series.

**If B (read path) were chosen instead:**
- each of the three surfaces lists a count ≤ 0 series that has an own visible item;
- it does not list foreign-only, hidden-only or hidden series;
- the category pagination total matches;
- a query-count check.

## 8. Rollback strategy

| Change | Rollback |
|---|---|
| F03-A SQL | §6.2: restore 22 `count` values from step 1 / the row dump. Seconds; no dependency |
| F03-A command | same values from its audit row |
| F03-B | revert the code; visitor output never changed |

## 9. Owner approvals required

1. **F03-A: approve the targeted repair (A2) instead of «إصلاح العدادات» (A1, rejected as unsafe).** This replaces
   B2's "run the repair for 21 authors" recommendation.
2. **Execution:** owner or DBA runs §6.1 on production (step 1 first, stop on any difference), **or** approve the
   §6.3 audited command.
3. **`lastupdate`:** leave as is (series sort last; recommended, minimal) or set it to the latest item time.
4. **F03-B permission:** `khotab.editseries` (recommended) or `active()` (author-page parity). Implementation is a
   separate approval.
5. **Renumbering damage (§1.5):** whether to open a separate reconciliation investigation. Not proposed now; it needs
   production evidence and owner knowledge of the 2025-09-15/16 operation. It covers:
   - 8,187 foreign items in 565 series;
   - 1,349 orphaned items;
   - 19 copy series with false counts (16082–16100);
   - 4 other negative counters (series with no visible items of their own).
6. **The reported series id** is still unknown. If it was created after 2025-09-16 it is not an A case and is
   probably F03-B (an empty new series admins cannot see).
