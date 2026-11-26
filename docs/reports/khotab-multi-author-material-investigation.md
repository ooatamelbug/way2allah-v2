# Khotab — Multi-Author Material Creation: Investigation Report and Implementation Plan

**Version 7 — 2026-11-26 — DEPLOYED & SMOKE TESTED — AWAITING FIRST REAL MATERIAL CREATION** (version 2 + Addendum v3 — decisions, concurrency, backup, Arabic search, production SQL — + Addendum v4 — the local implementation and its verification — + Addendum v5 — local technical acceptance, two image defects and two UI defects fixed — + Addendum v6 — owner approvals, link-change verification, deployment and rollback plan, readiness — + Addendum v7 — production deployment record, at the end). Investigation and planning only: no application code, schema, data or production change.
**Baseline:** branch at `e9896f4`, clean tree (this report is the only new file).
**Evidence:** code at that commit; the local `olddb` copy (MySQL 5.7.44, InnoDB, `REPEATABLE-READ`, read-only `SELECT` / `SHOW` only); OLD and Gen2 sources (read-only).
**Legend:** **[C]** confirmed in code or data · **[A]** assumption · **[P]** must be verified on production (section 11).

---

## 0. Owner decisions (2026-11-25) and how the plan applies them

| # | Decision | Applied in |
|---|---|---|
| 1 | All three forms (author, group, series); no new page | §3 |
| 2 | Any selected author already has the link → reject everything, name every conflicting author | §5.3 |
| 3 | Hidden authors not selectable | §4, §5.2 |
| 4 | «الظهور في قائمة الجديد» and «التثبيت» on the primary only | §5.4 |
| 5 | Channel and location copied (neither is author-specific **[C]**: `nuke_sat_channels` / `nuke_islamic_locations` are global lists) | §5.4 |
| 6 | Backup: investigate | §7 |
| 7 | Content fields copied; counters start at the existing defaults | §5.4 |
| 8 | No persistent relation between copies; reuse the activity log | §5.6 |
| 9 | At most 10 additional authors, one central setting | §5.2 |
| 10 | Copies outside the primary's group / series | §5.4 |
| 11 | No migration | whole plan |
| 12 | Edit / Delete unchanged | §9 |
| 13 | One transaction for all records | §6 |
| 14 | Server-side authorization per destination author and media type | §5.5 |

---

## 1. Confirmed facts from version 1 (summary)

- `nuke_islamic_khotab` has no unique key besides `PRIMARY(id)`; `link` is `text` and not indexed; the only duplicate rule is PHP's *same link + same author* (`InsertKhotabItemAction.php:88`, `InsertKhotabAuthorItemAction.php:65`, `InsertKhotabSeriesItemAction.php:82`) **[C]**.
- 975 links are already shared by items of 2+ different authors (2,052 items); OLD / Gen2 never had a multi-author feature — these were made by hand **[C]**.
- Every local file of an item is keyed by its own id (GIF, frame, cover, banner, PDF, DOCX); delete / update / move write `WHERE id = item` only; delete removes no file **[C]**.
- Groups and series are author-owned; reusing them across authors would couple counters, listings and group/series deletion **[C]** (version 1 §4).
- Only these three actions insert Khotab items (`InsertKhotabItemAction.php:108`, `InsertKhotabSeriesItemAction.php:108`, `InsertKhotabAuthorItemAction.php:85`) **[C]**.

---

## 2. Final architecture

**Independent records, one operation, one transaction.**

```
storeItem (one of the three existing controllers, unchanged route)
 └─ existing Insert…Action::execute(actor, context, data)
     ├─ authorize primary (unchanged)  +  authorize every additional author (new, §5.5)
     ├─ DB transaction (main)                                    ← one, all-or-nothing
     │   1. lock group / series row (group / series flows, as today)
     │   2. lock primary + additional author rows, ascending id   ← moved before the check (§6)
     │   3. duplicate-link check for ALL authors → one error naming every conflict
     │   4. primary: exactly today's writes
     │   5. copies: KhotabAdditionalAuthorCopies writes N standalone rows
     │   6. one `add_item` audit row per record
     └─ after commit
         7. primary images: exactly today's download (unchanged code)
         8. copies: copy the primary's downloaded files to each copy's id path (§8)
         9. result: ids created + any image problems → flash message (§8.3)
```

When no additional author is posted, steps 3–9 behave exactly as today; the only internal difference is the earlier author lock (step 2, §6.4).

---

## 3. Forms and the reusable selector

### 3.1 The three forms **[C]**

| Form | View | Create / store | Primary author |
|---|---|---|---|
| Author page | `resources/views/admin/khotab/authors/item-create.blade.php` | `KhotabAuthorAdminController::createItem/storeItem` (`:125-142`) | route `{author}` |
| Group page | `resources/views/admin/khotab/groups/item-create.blade.php` | `KhotabGroupAdminController::createItem/storeItem` (`:164-180`) | `group.author_id` |
| Series page | `resources/views/admin/khotab/series/item-create.blade.php` | `KhotabSeriesAdminController::createItem/storeItem` (`:158-180`) | `series.author_id` |

### 3.2 One shared partial

`resources/views/admin/khotab/_additional-authors.blade.php` (new), included once in each form just above the existing «أضف» button. It receives only the primary author id, the section (`video` / `audio`) and — after a failed submission — the up-to-10 previously selected authors (id + name) so the selection is not lost. **No author list is rendered on page load.**

Markup (RTL, Bootstrap 3 classes already on the page):
- `<fieldset>` with `<legend>` «دعاة مشاركون (اختياري)».
- Search box (`<label>`, `type="search"`, `autocomplete="off"`), hint «اكتب حرفين على الأقل».
- Results list: one `<label><input type="checkbox"> name</label>` per result, «عرض المزيد» button when more pages exist.
- Selected list: one chip per author with a «×» remove button and a hidden `<input name="additional_authors[]" value="id">`.
- Counter «المختارون: N من 10», `aria-live="polite"` status line (searching / no results / limit reached / error).

Behaviour (inline vanilla JS, ~120 lines; jQuery is on the page but not needed):
- 350 ms debounce; no request under 2 characters (after trimming).
- Stale responses: every request carries an increasing sequence number and its own `AbortController`; a new keystroke aborts the previous request, and a response whose sequence number is not the latest is ignored.
- «عرض المزيد» requests the next page of the same query and appends.
- The selection lives in a `Map` (id → name), so it survives new searches and pages; results already selected render checked; at 10 selected, unchecked boxes are disabled.
- Unticking a result or pressing «×» removes it; the hidden inputs always mirror the Map.
- With nothing selected, no `additional_authors[]` field is posted → the request is identical to today's.

**No new dependency.** `public/assets/global/plugins/select2` exists but no AdminCP view uses it **[C]**; one small inline script is less risk than introducing a plugin and its CSS to three pages.

---

## 4. Author search endpoint

### 4.1 Existing endpoints reviewed **[C]**
- No admin author search exists. The move page loads every author into a `<select>` (`KhotabItemAdminController.php:80`); the public advanced search caches the whole list for a menu (`KhotabSearchController.php:83`); `KhotabAuthorsAutocomplete` only writes the public `authors.txt` file.
- AdminCP AJAX is mostly HTML fragments (e.g. `move-options/groups`); one JSON endpoint exists (`FatwaAdminController.php:131`). JSON is used here because the client builds checkboxes and needs only id + name.

### 4.2 Route
`GET /admincp/khotab/author-search` → `admin.khotab.author-search`, in the existing admin group with `admin.permission:khotab.additem` (the same key as the three forms). Query: `q`, `media` (`video|audio`), `exclude` (primary id), `page` (1-based).

### 4.3 Server rules
- `media` maps to `vedio`; the action authorizes `khotab.additem` on `new KhotabMediaTarget($vedio)` → a moderator of the other section gets 403 **[C]** (same constraint as the forms).
- `q` normalized (§4.4); fewer than 2 characters after normalization → empty result (no query).
- `hidden = 0` only; `id <> exclude`.
- `ORDER BY name, id`; `LIMIT 21 OFFSET (page-1)*20` → 20 returned, `more` = whether a 21st existed. Offset paging is adequate for 736 rows.
- `LIKE` wildcards in the input (`%`, `_`, `\`) escaped.
- Response: `{"results":[{"id":12,"name":"الشيخ …"}],"more":true}` — `name` = `trim(prename.' '.name)`; nothing else.
- Throttle: `throttle:60,1` on the route (cheap insurance; debounce already limits traffic).
- The endpoint is a convenience only — **the submission re-validates everything** (§5.2).

### 4.4 Arabic search behaviour **[C]**
`nuke_islamic_authors.name` / `prename` are `utf8_unicode_ci` locally (production **[P]**). Tested on that collation:

| Pair | Equal? |
|---|---|
| أحمد / احمد, إبراهيم / ابراهيم, آمنة / امنة | **no** |
| فاطمة / فاطمه, مصطفى / مصطفي | **no** |
| الشيخ / الشيـخ (tatweel) | **no** |
| محمّد / محمد (shadda) | yes |

So a plain `LIKE` misses common spelling variants. Both sides are normalized the same way:
- query (PHP): remove tatweel and harakat; `أ إ آ ٱ → ا`, `ة → ه`, `ى → ي`;
- column (SQL): the same mapping as nested `REPLACE()` on `name` — portable to MySQL, MariaDB and SQLite (tests).

### 4.5 Performance **[C]**
`nuke_islamic_authors` has only `PRIMARY(id)`; `LIKE '%…%'` cannot use a B-tree index anyway. Measured: full scan of 736 rows, **≈ 2.4 ms** per query (`EXPLAIN type=ALL`). No index, cache or migration needed.

---

## 5. Validation and authorization

### 5.1 Request
`StoreKhotabItemRequest` (inherited by the author and series requests) gains:
- `additional_authors` → `nullable`, `array`, `max:` *central limit*;
- `additional_authors.*` → `integer`, `min:1`, `distinct`.

### 5.2 Central limit and server checks
- One setting: `config/admin_khotab.php` → `max_additional_authors = 10` (the file already holds `archive_timeout`, `media_root`); read by the request rule, the action, the partial (counter) and the search endpoint. No hard-coded 10 anywhere else.
- In the action, inside the transaction after the locks: every id must exist, have `hidden = 0` and differ from the primary — otherwise a field error on `additional_authors` and nothing is written.

### 5.3 Duplicate links (decision 2)
Checked for the primary **and** every additional author after the locks. If any author already has the link, the whole submission is refused with one message listing all of them, e.g.
«هذه الوصلة مضافة بالفعل لدى: الشيخ أ (المادة #123 …)، الشيخ ب (المادة #456 …)».
With no additional authors the existing message and field (`link`) are kept exactly.

### 5.4 What each copy receives
| Field | Copy |
|---|---|
| `title`, `description`, `meta_keywords`, `meta_description`, `meta_index`, `meta_follow`, `link`, `linksize`, `notes` (`MoreInfo`), `vedio` | same as primary |
| `channel_id` | the primary's **final** value (group flow 0; series flow the series' channel or the chosen one; author flow the chosen one) |
| `location_id` | same as primary; each copy's author location count updated (`KhotabLocationCounts::update('author', …)`) |
| `author` | the additional author |
| `group_id`, `ser_id` | **0** (decision 10) |
| `newslist`, `fixed` | **0** (decision 4) |
| `time`, `lastmirror` | the same `now` as the primary |
| `down` | 2 — the existing creation default (§7.6) |
| `weight` | `= id` (as today) |
| `hits`, `downcount`, `comments`, `mirror`, `gif`, `frame`, … | table defaults, as today; `gif` / `frame` set after the image copy (§8) |

Author counter `vedio` / `audio` + 1 for every copy's author. The primary's group / series counters move once (unchanged).

### 5.5 Authorization (decision 14)
- Primary: unchanged (`khotab.additem` on the group / series record, or on `KhotabMediaTarget($vedio)` for the author page).
- **Each copy**: `authorize($actor, 'khotab.additem', new KhotabMediaTarget($vedio))` before anything is written. The middleware `admin.khotab-media` only sees the route's own record, never body-supplied author ids **[C]** — the action is the enforcement point (same lesson as the mirror move of 2026-11-21).
- There is no per-author moderator scope in the code today **[C]**; authorizing per copy means one added later applies automatically.

### 5.6 Activity log (decision 8)
One existing `add_item` audit row per record (primary and each copy), inside the transaction. Copies' details carry `author_id` and `created_with: <primary id>` — a log entry only, no relation column or table. (If the owner prefers no reference at all, drop that key.)

---

## 6. Transaction and concurrency

### 6.1 The race today **[C]**
The author flow reads the duplicate check **before** locking the author row (`InsertKhotabAuthorItemAction.php:65` then `:70`); the group / series flows lock their group / series row, check, then lock the author (`InsertKhotabItemAction.php:85, 88, 93`). Two requests for the same author through different paths (or a double click on the author page) can both pass the check and both insert.

### 6.2 Design (no migration)
1. **Serialization point = the author row.** Every creation path (single and multi) takes `SELECT … FOR UPDATE` on `nuke_islamic_authors` for **all** authors it will write, in **ascending id order**, **before** the duplicate check.
2. **Duplicate check after the locks, as a plain read.** Under InnoDB `REPEATABLE READ` the read view is created by the transaction's first *consistent* read; locking reads do not create it. The closures' first consistent read is the duplicate check, so it runs after the locks and sees every transaction that committed before them **[C — MySQL/InnoDB semantics; verified by the integration test in §10.4]**. Rule written into the code: no consistent read before the author locks.
   - A *locking* duplicate check (`… FOR SHARE`) was considered and rejected: `link` has no index, so it would lock every index entry of the author — 33,093 rows for author 154 **[C]**.
3. **Lock order** (deadlock avoidance): group or series row (as today) → authors ascending → item writes → counter updates. Two multi-author requests with overlapping authors take them in the same order → no cycle.
4. **Transaction scope:** primary + copies + counters + location counts + audit rows. Images and files after commit only.
5. **Deadlock retry:** the combined transaction uses Laravel's `transaction($callback, 3)` (retries only on a deadlock / lock-wait concurrency error; the closure has no external side effects, so a retry is safe). Lock wait timeout is 50 s locally **[C]**, production **[P]**.

### 6.3 Scenarios
| Concurrency | Result |
|---|---|
| Two multi-author submissions sharing an author and link | Second waits on that author's lock, then its check sees the first's row → refused, names the conflict. |
| Multi-author vs single-author (any path) for the same author and link | Same, because both paths lock the author before checking (§6.4). |
| Double click | Second request waits, then is refused with the conflict message naming the item just created. Optional: the partial disables «أضف» after the first submit (owner choice — it would also affect single-author submissions). |
| Different authors | No shared lock → no waiting. |

### 6.4 Effect on existing single-author creation
Only the **position** of the author lock moves (before the check). Rows, counters, audit, messages and redirects stay identical. Without this change, duplicates between a single-author and a multi-author submission cannot be prevented — **approval needed** (Q-A).

### 6.5 Not covered (pre-existing, out of scope)
Edit can change a link and Move can change an author without any duplicate check (`UpdateKhotabItemAction`, `MoveKhotabItemAction`) **[C]**. Unchanged by this feature; listed for awareness.

---

## 7. Backup system investigation

### 7.1 Components **[C]**
- Server: `POST /backup.php` → `BackupApiController` (`routes/backup.php`; CSRF-exempt). Operations: `CreateSession`, `LiveUpdate`, `KhotabUpdateBackup` with `myop=get` / `put`.
- Client: an external Windows **Desktop App** (VB6; files cited in the controller: `Project1.vbp`, `Form4.frm`, `DownloadForm.frm`, `frmMain`, `frmBackup`). **Its source is not in this repository, the docs, or anywhere on this machine** (searched).
- Tables: `nuke_islamic_khotab` (`down`, `trial`, `booking`, `linksize`, `online`, `downloader`, `checktime`), `nuke_backup_sessions`, `nuke_backup_booking`, `nuke_islamic_advanced` (media details by item id).

### 7.2 The `down` field **[C]**
| Value | Meaning | Set by |
|---|---|---|
| **2** | New, waiting for the backup client. `trial` = attempts; `trial > 10` = OLD's «روابط غير صحيحة» list (`old-project/modules/Khotab/admin/index.php:4295`) | every insert (`'down' => 2`); column default 2 |
| **1** | Downloaded by the client; `online` vs `linksize` = link health («تعمل» / «لا تعمل», OLD `modules/Khotab/index.php:907-915`; «وصلات لا تعمل» list `:4299`) | `put` (`BackupApiController.php:410-420`) |
| **0** | OLD's manual «Export» moved all `down = 2` to 0 after exporting a download list (`admin/index.php:3658`); now the «الوصلات المفقودة» worklist (`KhotabLinkReportController.php:43`) | legacy export only |

Further effect: with `down = 1` the link becomes read-only except for a super-admin (`UpdateKhotabItemAction::linkEditable`, OLD `admin/index.php:1564`).
Local distribution: `down=1` 186,455 · `down=2` 76,767 · `down=0` 29,370.

### 7.3 Lifecycle **[C]**
1. Insert → `down = 2, trial = 0, booking = 0`.
2. Client `get` (`:228-330`): per mode, up to **20** rows `down = 2 AND booking = 0 AND trial < 10 AND time < now − 24 h`, `ORDER BY id`; each row gets `trial + 1`, `booking = now`, a `nuke_backup_booking` row; the client receives `<ID>{id}<URL>{link}<FILE>@KhotabPath@{floor(id/100)}\{id}.{ext}`.
3. Client downloads the URL to that file, then `put` (`:391-471`): `down = 1, trial = 0, linksize = online = size, downloader, checktime = now, booking = 0` **for that id**, and (with `Adv=1`) replaces `nuke_islamic_advanced` row **for that id**.
4. Failure: no `put` → booking cleared after 24 h (one stale booking per mode per `get`, `:261-264`) → offered again until `trial` reaches 10 → stays `down = 2` on the invalid-links list.

**Everything is keyed by item id**: the hand-out, the target file name, the status update, the media details. Nothing is keyed by URL or file hash on the server side.

### 7.4 Evidence from the 975 shared-link clusters **[C]**
| Fact | Value |
|---|---|
| Items in clusters | 2,052 (`down=1` 1,280 · `down=2` 121 · `down=0` 651) |
| Clusters where every copy is `down=1` | 581; mixed states: 45 |
| Clusters with 2+ backed-up copies | 582 — in **581** every copy has its **own `checktime`** → downloaded separately |
| Same `linksize` on all backed-up copies | 542 of 582 |
| Backed-up copies with their own `nuke_islamic_advanced` row | 1,094 of 1,236 |

Conclusion: today the client downloads each copy separately, and each copy keeps its own backup status and media details.

### 7.5 What cannot be verified without the client source
- whether the client skips a download whose URL or content it already has (local de-duplication);
- whether it stores one physical file per id (the server tells it `{id}.{ext}`, so separate files are expected **[A]**) or hard-links / de-duplicates;
- retry delays, parallelism, throttling against archive.org;
- what the backup storage holds and how it is pruned.
**Needed:** the Desktop App source (the VB6 project above) or the operator's description of the backup machine's storage and download behaviour.

### 7.6 Recommendation
**Keep `down = 2` for every copy (the existing default) and leave the backup API and client unchanged.**
- Correctness: each copy needs its own backup state, because link health («تعمل» / «لا تعمل»), the super-admin-only link lock, the media details shown on the item page and the invalid / lost-link lists are all per item id **[C]**. Marking copies as backed up without a real `put` would invent `online` / `checktime` values and lock links that were never verified.
- Cost: up to 10 extra downloads and (probably **[A]**) 10 extra files per shared material; copies have consecutive ids and the same `time`, so they become eligible together and usually land in the same 20-item `get` batch → one client session fetches the same URL several times in a row. Today's equivalent load already exists (2,052 items, ≈ 0.7 % of all items).
- No server CPU cost; the queue grows by N rows per shared material.
- A de-duplicating backup (hand out one id per link, fan out the `put`) would change the client contract → **separate decision**, needs the client source; not part of this feature.
- Rejected: inserting copies with `down = 0` (puts them on the lost-links worklist) or `down = 1` (false backup state).

---

## 8. Archive.org images

### 8.1 Approach
- **Primary:** exactly today's code path (group flow: its own download in `InsertKhotabItemAction`; author / series: `KhotabArchiveImages::store`) — untouched.
- **Copies:** after the primary's download, for each image the primary actually received (`gif` / `frame` flag set), **copy the primary's file** (`media/khotab_gifs/{b}/{primary}.gif`, `media/khotab_frames/{b}/{primary}.jpg`) to each copy's own path, then set that copy's flag. One network download per image regardless of N; each copy owns an independent file (deleting / replacing one never affects another).
- Implementation: a new method on `KhotabArchiveImages` (e.g. `copyFromItem(int $sourceId, array $flags, array $targetIds): array`) using `copy()` under the existing `admin_khotab.media_root`; directories created as today.

### 8.2 Failure classes
| Failure | Database | Files | Message |
|---|---|---|---|
| Validation / authorization / duplicate | nothing written | nothing | field errors, input kept (as today) |
| Transaction error (deadlock after retries, DB down) | rolled back entirely | nothing (images run after commit) | generic error (as today) |
| Primary image download fails | all records kept | none for that image on any record (nothing to copy) | success + warning naming the material ids without that image |
| Copy to one copy's path fails | all records kept | that copy lacks the image; others fine | success + warning naming that id |
| Partial availability (GIF ok, frame failed) | kept | per flag | warning names the missing image type |

Recovery: the existing «سحب الصور» (`LeechKhotabItemImagesAction`, `khotab.edititem`) works per item **[C]** (it reads archive.org again and picks the middle thumbnail, which may differ from the frame chosen at creation).

### 8.3 Messages
- No additional authors: unchanged («تمت إضافة المادة», no new warnings).
- With additional authors: «تمت إضافة المادة لـ N دعاة (#ids)»; if any image step failed, a second line «تعذّر حفظ الصور للمواد: #… — استخدم «سحب الصور» من صفحة المادة».
- The contextual flash component renders one success line today (`components/admin/contextual-flash.blade.php`); it would gain an optional warning line (a new session key, rendered only when present).

---

## 9. Compatibility (with decisions applied)

| Area | Effect |
|---|---|
| Single-author creation | Unchanged except the earlier author lock (§6.4). |
| Edit / Delete / Move | Unchanged; operate per id **[C]**. |
| Item URLs | One new `/khotab-item-{id}.htm` per copy; nothing existing changes. |
| Author pages | Each copy listed on its author's page (standalone) **[C]**. |
| Group / series pages | Only the primary appears (copies are standalone). |
| Home «latest» / pinned list | Only the primary (copies `newslist = 0`, `fixed = 0`). |
| Search | One result per record (different sheikh names). |
| Channel / location pages | Every record listed (channel and location copied — decision 5). |
| Categories | Each record categorised separately afterwards (trigger keyed by id) **[C]**. |
| Backup | One backup job per record (§7.6). |
| SEO | No canonical tags on the site **[C]**; identical title / description per record (accepted, decision 7). |
| OLD / Gen2 | Ordinary rows; identical in shape to the 975 existing clusters. |

---

## 10. Implementation plan

### 10.1 Expected files (no migration)

| File | Change |
|---|---|
| `config/admin_khotab.php` | `max_additional_authors` (10), `author_search_page_size` (20), `author_search_min_length` (2) |
| `routes/admin.php` | one GET route `admin.khotab.author-search` (`khotab.additem`, `throttle:60,1`) |
| `app/Domain/Admin/Http/Controllers/KhotabAuthorSearchController.php` (new) | the endpoint (§4) |
| `app/Domain/Admin/Support/ArabicSearchText.php` (new) | the normalization of §4.4 (PHP side + SQL expression) |
| `app/Domain/Admin/Http/Requests/Khotab/StoreKhotabItemRequest.php` | `additional_authors` rules (§5.1) |
| `app/Domain/Admin/Actions/Khotab/KhotabAdditionalAuthorCopies.php` (new) | lock / validate / duplicate check for the additional authors; insert the copies; counters; location counts; audit |
| `app/Domain/Admin/Actions/Khotab/InsertKhotabItemAction.php`, `InsertKhotabAuthorItemAction.php`, `InsertKhotabSeriesItemAction.php` | author lock before the check; call the copier inside the transaction; image copy after commit; return the created ids and image problems (a small result object) |
| `app/Domain/Admin/Actions/Khotab/KhotabArchiveImages.php` | `copyFromItem()` (§8.1) |
| `KhotabAuthorAdminController`, `KhotabGroupAdminController`, `KhotabSeriesAdminController` | `createItem`: pass the primary id, section and (after a failed submit only) the up-to-10 selected authors; `storeItem`: messages (§8.3) |
| `resources/views/admin/khotab/_additional-authors.blade.php` (new) | the selector (§3.2) |
| the three `item-create.blade.php` | one `@include` above «أضف» |
| `resources/views/components/admin/contextual-flash.blade.php` | optional warning line |
| tests (new) | §10.2 |

Not changed: Edit / Delete / Move code and views, backup API, public pages, the group flow's own download code.

### 10.2 Tests

**Backward compatibility** — no additional authors on each of the three forms: identical rows, counters, audit row, message, redirect, images; existing `KhotabAuthorPanelParityTest`, `KhotabGroupPanelParityTest`, `KhotabSeriesPanelParityTest`, `KhotabMediaScopeTest` pass unchanged; the create pages contain no author names besides the primary's.

**Search endpoint** — guest → login redirect; no `khotab.additem` → 403; audio-only moderator with `media=video` → 403; under 2 characters → empty; hidden excluded; primary excluded; 20 + `more`; page 2; `احمد` finds `أحمد`, `فاطمه` finds `فاطمة`, `مصطفي` finds `مصطفى`, tatweel ignored; `%` / `_` treated literally; payload keys exactly `id`, `name`; standalone and forum-linked admins.

**Creation** — primary + 3 on each form: 4 rows; content fields equal; `group_id = ser_id = 0`, `newslist = fixed = 0` on copies; channel and location copied; author counters +1 each; group / series counters +1 once; location counts per author; 4 audit rows; message lists ids.

**Validation** — primary in the list, duplicates in the list, unknown id, hidden id, 11 ids → refused, nothing written; the limit read from config (test with a changed config value).

**Duplicate links** — one and two conflicting additional authors (and the primary) → one message naming all; nothing written.

**Authorization** — audio-only / video-only / both / neither moderators against video and audio forms; super-admin; hand-crafted POSTs with additional authors to a section the admin cannot add to → 403, nothing written.

**Transaction** — a forced failure in the 3rd copy (test double) → no rows, counters, location counts or audit rows left.

**Images** (`Http::fake`) — success → every record has its own GIF / frame file and flags; primary download fails → no flags, warning; one copy's target unwritable → only that id flagged missing; «سحب الصور» on a copy works; deleting one copy leaves the others' files, rows and counters.

**Independence** — edit, move, delete one copy → others unchanged.

**Front-end (browser, as for the session indicator)** — debounce (one request per pause), stale-response guard (slow first response ignored), selection kept across searches and «عرض المزيد», chips remove, 10-limit disables boxes, keyboard and screen-reader labels, RTL at 320 / 768 / 1366 px, no request and no author list on page load.

**Regression** — full `tests/Feature/Admin`, `tests/Feature/Content`, PHPStan, Pint.

### 10.3 Concurrency verification
SQLite tests cannot reproduce InnoDB row locks. A one-off integration script on a **disposable MySQL copy** (`am_disp` + restricted `am_user`, dropped afterwards, as in earlier rounds) runs two parallel PHP processes: (a) two multi-author submissions with an overlapping author and link; (b) one multi-author and one single-author (author-page) submission; (c) the same request twice. Expected: exactly one row per author and link, the other request refused, no deadlock left unretried.

### 10.4 Order of work (after approval)
1. Search endpoint + normalization + tests.
2. Copier + action changes + request rules + tests (no UI yet).
3. Image copy + messages + tests.
4. Selector partial on the three forms + browser checks.
5. Disposable-MySQL concurrency run; full regression; report.

---

## 11. Production verification (read-only, run by the owner before deployment)

Connected to the main database (`w2acp_db2025-2`):

```sql
-- 1. Triggers the creation / category lifecycle depends on (expect 6 rows)
SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING, EVENT_OBJECT_TABLE, ACTION_STATEMENT
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE IN ('nuke_islamic_khotab', 'nuke_islamic_series')
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;
-- expected: trg_khotab_after_insert / _after_update / _after_delete,
--           trg_series_after_insert / _after_update / _after_delete

-- 2. Procedures the triggers call (expect sync_khotab_categories, sync_series_categories,
--    fill_khotab_category_index, fill_series_category_index)
SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() ORDER BY ROUTINE_NAME;
SHOW CREATE PROCEDURE sync_khotab_categories;   -- needs SHOW ROUTINE / owner privileges

-- 3. Category index table and its cascade
SHOW CREATE TABLE khotab_category_index;
SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'nuke_islamic_khotab';
-- expected: fk_khotab_category, CASCADE

-- 4. No unique key on link; down default
SHOW INDEX FROM nuke_islamic_khotab;
SHOW COLUMNS FROM nuke_islamic_khotab WHERE Field IN ('down', 'trial', 'booking', 'link');
-- expected: only PRIMARY unique; down default 2, trial default 0

-- 5. Engine, isolation, lock wait (MariaDB 10.11 uses tx_isolation)
SELECT ENGINE FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('nuke_islamic_khotab', 'nuke_islamic_authors');
SELECT @@tx_isolation, @@innodb_lock_wait_timeout, @@version;
-- expected: InnoDB, REPEATABLE-READ

-- 6. Author name collation and Arabic comparisons
SHOW FULL COLUMNS FROM nuke_islamic_authors WHERE Field IN ('name', 'prename');
SELECT 'أحمد' = 'احمد' COLLATE utf8_unicode_ci AS hamza,
       'فاطمة' = 'فاطمه' COLLATE utf8_unicode_ci AS taa;   -- adjust COLLATE to the column's collation

-- 7. Author list size and hidden authors
SELECT COUNT(*) AS authors, SUM(hidden = 1) AS hidden FROM nuke_islamic_authors;
```

If any trigger or procedure is missing on production, category assignment is already broken there independently of this feature — report before deployment.

---

## 12. Risks and blockers

| Severity | Item | Status / mitigation |
|---|---|---|
| **Blocker** | Production triggers / procedures (§11 queries 1–3) | Owner to run before deployment. |
| **Blocker** | Approval to move the author lock before the duplicate check in all three existing flows (§6.4, Q-A) | Needed for duplicate prevention across paths. |
| High | Permission bypass via body-supplied author ids | Per-copy authorization in the action; hand-crafted request tests. |
| High | Partial creation | One transaction; images after commit only. |
| Medium | Backup: N downloads (and probably N files) per shared material | Accept (§7.6) or decide a separate backup redesign; client behaviour unverifiable without its source. |
| Medium | Arabic search misses variants | Normalization §4.4; production collation §11.6. |
| Medium | Deadlocks with unrelated writers (Delete / Move update group, series and author counters in other orders) | Retry ×3; lock order documented; integration test. |
| Low | Double-click shows a conflict error for the second request | Optional submit guard (Q-B). |
| Low | Edit / Move can still create same-author duplicate links (pre-existing) | Out of scope; listed. |
| Low | Recovery «سحب الصور» picks the middle thumbnail, not the frame chosen at creation | Existing behaviour. |

---

## 13. Remaining questions for the owner

- **Q-A** Approve moving the author lock before the duplicate check in the three existing creation flows (no visible change; closes the pre-existing race; required for §6)?
- **Q-B** Disable «أضف» after the first click on all three forms (also affects single-author submissions), or leave the button as is?
- **Q-C** Backup: accept one backup job per copy as today (§7.6)? Can the Desktop App source or a description of the backup machine be provided to settle §7.5?
- **Q-D** Keep `created_with: <primary id>` in the copies' audit details (log only), or omit it?
- **Q-E** Show image-failure warnings only for multi-author submissions (recommended, keeps single-author behaviour identical), or for every creation?

---

# Addendum — Version 3 (2026-11-25)

## A1. Owner decisions recorded

| Item | Decision |
|---|---|
| Q-A author lock before the duplicate check, all three flows | Approved in principle (conditions in A2) |
| Q-B submit-button protection, single and multi-author | Approved (design in A5) |
| Q-C one backup job per copy | **Not approved** — per-item backup lifecycle stays as it is; clarifications in A3 |
| Q-D `created_with` in the copies' existing audit rows | Approved (`metadata.created_with = <primary id>`; `admin_audit_log.metadata` is the existing structured field, `AdminAuditRecorder::record()`) |
| Q-E image-failure warnings, multi-author only | Approved |
| Server-side author search (§4) | Approved |

## A2. Concurrency — conditions of Q-A

### A2.1 Isolation level
- Local: InnoDB, `@@transaction_isolation = REPEATABLE-READ`, `innodb_lock_wait_timeout = 50` **[C]**.
- No code sets an isolation level: `config/database.php` has no `isolation_level`, and no `SET TRANSACTION`, `ISOLATION` or `WITH CONSISTENT SNAPSHOT` exists in `app/`, `config/`, `bootstrap/` **[C]** → the server default applies.
- Production (MariaDB 10.11): **[P]** — query 5 in A6. MariaDB 10.11's default is REPEATABLE-READ.
- Behaviour per level: REPEATABLE-READ — design as described; READ-COMMITTED — each statement reads the latest committed data, so the check after the lock is fresh by construction; SERIALIZABLE — plain reads become shared-locking reads; still correct, more waiting.

### A2.2 No stale snapshot before the duplicate check
Under InnoDB REPEATABLE-READ the transaction's read view is created by its first *consistent* (non-locking) read; `SELECT … FOR UPDATE` does not create it, and Laravel opens transactions with a plain `START TRANSACTION` (no consistent-snapshot option).
Statements inside each closure **before** the duplicate check, after the change:

| Flow | Before the check (all locking reads) |
|---|---|
| Group | `nuke_islamic_groups … FOR UPDATE` → `nuke_islamic_authors … FOR UPDATE` (primary + additional, ascending id; returns `id`, `hidden`) |
| Series | `nuke_islamic_series … FOR UPDATE` → authors as above |
| Author | authors as above |

Everything before the transaction (route-model binding, `AdminRecordAuthorizer`, the guard's admin lookup) runs in autocommit outside the transaction and cannot fix the snapshot.
Rules to keep it true, enforced in code and review:
1. No consistent read inside the closure before the author locks (the author validity / `hidden` check uses the rows returned by the locking read itself).
2. The actions must not run inside an outer transaction on `main` (an outer transaction could already hold an older read view). Implementation adds a guard: `transactionLevel() === 0` on `main` at entry outside tests; the test harness's own transaction handling is checked before this guard is finalised.

### A2.3 Observing committed competing submissions
Logic: request B waits on request A's author row lock; A commits (rows visible); B gets the lock; B's first consistent read — the duplicate check — creates B's read view now, so it includes A's rows → B is refused.
**Verification:** this requires two concurrent writers and therefore a write-capable database. Proposed (not run, needs approval): a scratch MySQL database on the local Docker server (`am_disp` + restricted `am_user`, created and dropped by the script; `olddb` untouched) with two parallel PHP processes for: (a) two multi-author submissions sharing an author and link, (b) multi-author vs single-author on the author page, (c) the same request twice, (d) a forced deadlock to exercise the retry. Expected: one row per author and link; the other request refused; no residual locks.

### A2.4 Deadlock retries and side effects
`DB::connection('main')->transaction($callback, 3)` retries only when Laravel classifies the error as a concurrency error (deadlock / lock wait) and only at transaction level 1. Inside the callback there are only `main` writes:
- item rows, counters, `KhotabLocationCounts` (DB only) **[C]**;
- `AdminAuditRecorder` → `AdminAuditEntry` on connection `main` (`AdminAuditEntry.php:35`), enlisted in the same transaction **[C]**; its optional legacy write runs in a savepoint and is off by default (`config('admin_audit.legacy_compatibility_writes', false)`); its only non-DB effect, a `report()` log line on failure, could repeat on a retry — harmless.
- No HTTP, filesystem, mail, events or queued jobs **[C]** (no model observers on `KhotabItem`).
Image downloads and file copies run after `transaction()` returns, i.e. after the final successful attempt → a retry cannot duplicate them. A rolled-back attempt only consumes auto-increment values (id gaps).

### A2.5 Other writers of (author, link)

| Writer | Can create a same-author duplicate link? | Takes the author lock? |
|---|---|---|
| Three creation flows | Prevented after Q-A | Yes (after Q-A) |
| **Edit** — `UpdateKhotabItemAction:79-82` | **Yes**: changes `link` with no duplicate check (locks only the item row) | No |
| **Move** — `MoveKhotabItemAction:81` | **Yes**: changes `author` with no duplicate check | No (locks the item row; updates counters) |
| Khotab mirrors (insert / update / move / delete) | No — they write `nuke_islamic_mirror.link`, never the item's `link` / `author` **[C]** | — |
| Link quality (recheck, fix size) | No — size / status fields only **[C]** | — |
| Backup API `put` | No — `down, trial, linksize, online, downloader, checktime, booking` only (`BackupApiController.php:410-420`) **[C]** | — |
| OLD / Gen2 admin, if still reachable on production | Yes — OLD's insert has the same check without locks; OLD edit / move as above | No **[P]**: owner to confirm whether `modules/Khotab/admin` or Gen2 `admincp` are reachable on production |
| Direct SQL (phpMyAdmin, scripts) | Yes | No |

**Remaining limitations (documented, not in scope):** Edit and Move can still produce a same-author duplicate link, sequentially or concurrently, as they can today; legacy or direct writers are outside any application lock. Extending the protocol to Edit (when the link changes) and Move (when the author changes) is possible without a migration but needs separate approval.

## A3. Backup clarifications (Q-C)

| Question | Answer | Basis |
|---|---|---|
| Do the 581 clusters prove separate **physical downloads**? | **No.** They prove separate **processing**: each copy was handed out and reported back (`put`) separately, at its own time, with its own size and (1,094 of 1,236) its own media-analysis row. They do not show whether the client fetched the file again or reused a file it already had. | §7.4; `put` carries only id, size, category and media fields |
| Does the timing help? | Inconclusive. Consecutive copies of files > 100 MB completed a median ≈ 59 min apart, but 11 of 62 such pairs completed < 10 s apart — consistent with either parallel fast downloads or reuse. 578 of 582 clusters were processed by a single `downloader` (one operator / machine). | read-only analysis of `checktime`, `linksize`, `downloader` |
| Where is the media fetched from? | From the **external source**: the API hands out the item's own `link` (archive.org links rewritten to `http://www.archive.org/download/…`, `BackupApiController.php:480-494`). In the shared-link clusters 2,044+ of 2,052 links are archive.org hosts. Not through the Way2Allah server **[C server side]**; that the client does not proxy is **[A]**. | §7.3; host distribution |
| Extra production server load? | Only the API calls: one `get` returns ≤ 20 rows per mode and writes `trial`, `booking` and one `nuke_backup_booking` row per item; one `put` updates one row (+ one media row). N copies = N small row writes. No file traffic on the production server. | `BackupApiController.php:257-315, 409-464` |
| Is physical backup storage duplicated? | **Unknown.** The server instructs one file per id (`@KhotabPath@{floor(id/100)}\{id}.{ext}`), so duplicates are expected unless the client de-duplicates. | `BackupApiController.php:305` |
| Can the existing endpoints settle it? | **No.** The server never learns whether a download happened: `LiveUpdate` writes empty telemetry (`size`, `downloaded`, `count`, `speed` are never read from the request, `:165-194`) and `put` has no download evidence. Laravel's `backup_api.*` info log lines (production `storage/logs`) give per-id `get` / `put` timestamps — timing only. | code |

**What would resolve it**
1. Desktop App source (VB6): `Project1.vbp`; `frmBackup`, `frmMain`; `Form4.frm` (the cited `LiveUpdate` / `put` calls, lines ~1251, 2251, 2262); `DownloadForm.frm` (the `@KhotabPath@` substitution and the download routine); every `.bas` / `.cls` module with the HTTP download code (e.g. `URLDownloadToFile`, WinInet, `Inet` control) — specifically any "file exists / skip" logic.
2. The client's configuration: values of `@KhotabPath@` (and the other path tokens), any cache / temp folder, parallel-download setting (INI file or registry keys).
3. Runtime evidence from the backup machine (read-only): the files for a known cluster, e.g. folder `@KhotabPath@\320\` → `32052.*`, `32070.*`–`32074.*` (one link, 6 authors, §2.3): whether all six exist, their sizes and timestamps; the client's own log, if any.
4. Optionally, the production `storage/logs/laravel*.log` lines `backup_api.khotab_update_backup_get` / `_put` for those ids.

Until then: copies are created with the existing default `down = 2`, i.e. the per-item backup lifecycle exactly as it works today; no change to backup behaviour, `down` values, endpoints or the Desktop App.

## A4. Arabic search normalization — confirmed

Applied to the search text in PHP and to the column **inside the query expression only** (nested `REPLACE()` in the `WHERE` clause); stored author names are never modified.

| Class | Characters | Becomes |
|---|---|---|
| Alef variants | أ إ آ ٱ | ا |
| Alef maqsura / Farsi ya | ى ی | ي |
| Ta marbuta | ة | ه |
| Tatweel | ـ | (removed) |
| Diacritics | ً ٌ ٍ َ ُ ِ ّ ْ ٰ | (removed) |

Not included (not requested): hamza on waw / ya (ؤ ئ).
Measured on the local author table (hidden excluded), read-only:

| Query | Plain `LIKE` | Normalized |
|---|---|---|
| احمد | 6 | 62 |
| أحمد | 56 | 62 |
| ابراهيم | 1 | 20 |
| مصطفي | 0 | 13 |
| الحوينى | 0 | 1 |
| اسامه | 0 | 9 |
| عبد الله | 61 | 61 |

Cost: ≈ 5.8 ms per normalized query (full scan of 736 rows). Two names contain tatweel.

## A5. Submit-button protection (Q-B)

Inline script in the shared partial (present on all three forms):
- listens to the form's `submit` event — it only fires after the browser's own constraint validation has passed; if another handler cancelled the event (`defaultPrevented`), it does nothing;
- disables «أضف», sets its text to «جارٍ الحفظ…» and `aria-busy="true"`;
- server-side validation failure → redirect back → a fresh page with an enabled button (input kept with `old()`);
- browser back / restored page (`pageshow` with `persisted`) → button re-enabled and text restored;
- network failure → the browser's error page; going back restores the button the same way.
Server-side duplicate prevention (A2) remains the authority.

## A6. Production database verification (read-only)

Run while connected to the main database **`w2acp_db2025-2`** (`USE \`w2acp_db2025-2\`;`). Every statement is a read.

```sql
-- 1. Triggers on the two tables (expect exactly 6 rows)
SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, DEFINER, ACTION_STATEMENT
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE IN ('nuke_islamic_khotab', 'nuke_islamic_series')
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;

-- 2. Every trigger in the database (to spot unexpected ones on other tables)
SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() ORDER BY EVENT_OBJECT_TABLE;

-- 3. Stored procedures (expect the four below)
SELECT ROUTINE_NAME, ROUTINE_TYPE, DEFINER
FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() ORDER BY ROUTINE_NAME;
SHOW CREATE PROCEDURE sync_khotab_categories;    -- may need SHOW CREATE ROUTINE privilege
SHOW CREATE PROCEDURE sync_series_categories;

-- 4. Category index tables and the cascade
SHOW CREATE TABLE khotab_category_index;
SHOW CREATE TABLE series_category_index;
SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IN ('nuke_islamic_khotab', 'nuke_islamic_series');

-- 5. Engine, isolation level, lock wait (MariaDB 10.11 names)
SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('nuke_islamic_khotab', 'nuke_islamic_authors', 'nuke_islamic_groups', 'nuke_islamic_series', 'admin_audit_log');
SELECT @@GLOBAL.tx_isolation AS global_isolation, @@SESSION.tx_isolation AS session_isolation,
       @@innodb_lock_wait_timeout AS lock_wait, @@version AS version;

-- 6. Keys and defaults that the design relies on
SHOW INDEX FROM nuke_islamic_khotab;
SHOW COLUMNS FROM nuke_islamic_khotab WHERE Field IN ('link', 'author', 'down', 'trial', 'booking', 'channel_id');

-- 7. Author names: collation and the Arabic comparisons
SHOW FULL COLUMNS FROM nuke_islamic_authors WHERE Field IN ('name', 'prename', 'hidden');
SELECT COUNT(*) AS authors, SUM(hidden = 1) AS hidden FROM nuke_islamic_authors;
```

**Expected (from the local copy)**

| Trigger | Timing / event | Table | Essential behaviour |
|---|---|---|---|
| `trg_khotab_after_insert` | AFTER INSERT | `nuke_islamic_khotab` | `CALL sync_khotab_categories(NEW.id, NEW.cat)` |
| `trg_khotab_after_update` | AFTER UPDATE | `nuke_islamic_khotab` | `IF OLD.cat <> NEW.cat THEN CALL sync_khotab_categories(NEW.id, NEW.cat)` |
| `trg_khotab_after_delete` | AFTER DELETE | `nuke_islamic_khotab` | `DELETE FROM khotab_category_index WHERE khotab_id = OLD.id` |
| `trg_series_after_insert` | AFTER INSERT | `nuke_islamic_series` | `CALL sync_series_categories(NEW.id, NEW.cat)` |
| `trg_series_after_update` | AFTER UPDATE | `nuke_islamic_series` | `IF OLD.cat <> NEW.cat THEN CALL sync_series_categories(NEW.id, NEW.cat)` |
| `trg_series_after_delete` | AFTER DELETE | `nuke_islamic_series` | `DELETE FROM series_category_index WHERE series_id = OLD.id` |

Procedures: `sync_khotab_categories`, `sync_series_categories`, `fill_khotab_category_index`, `fill_series_category_index`.
Constraint: `fk_khotab_category` (`khotab_category_index.khotab_id` → `nuke_islamic_khotab.id`), `DELETE_RULE = CASCADE`.
Other expectations: all listed tables InnoDB; isolation REPEATABLE-READ; `nuke_islamic_khotab` with `PRIMARY` as the only unique index; `down` default 2, `trial` default 0; `name` collation reported (local: `utf8_unicode_ci`); about 736 authors, 6 hidden.

**Interpretation**

| Result | Meaning | Action |
|---|---|---|
| All six triggers, four procedures and the cascade present and as above | Category maintenance matches local; the feature adds nothing new to it | Proceed |
| Khotab insert / update triggers missing | `khotab_category_index` is not maintained on production **today**: category assignments made in AdminCP (`KhotabItemCategoryAction` writes only `cat`) do not reach category pages. Pre-existing, independent of this feature | Report; recreating triggers is a schema change needing separate approval |
| Triggers present but a procedure missing | Every insert / category update on that table fails today ("PROCEDURE … does not exist") — would already be visible as AdminCP errors | Report immediately |
| A trigger's `DEFINER` user does not exist | Same failure mode ("The user specified as a definer does not exist") | Report immediately |
| Delete trigger missing but cascade present (or vice versa) | Cleanup still happens through the other one | Note only |
| Extra triggers on `nuke_islamic_khotab` / `nuke_islamic_authors` (e.g. BEFORE INSERT, or on authors) | Unknown side effects would run once per created record (N+1 per submission) and could take locks | Send definitions for review before implementation |
| Isolation READ-COMMITTED | Design still correct (each statement sees the latest committed data) | Note |
| Isolation SERIALIZABLE | Correct; more lock waiting | Note |
| Any engine other than InnoDB (e.g. MyISAM) on a listed table | Row locks and transactions do not apply to it | Blocker |
| A unique index on `link` (or `author, link`) exists | Duplicates already impossible at DB level; error handling must map the constraint violation | Report |
| Collation other than `utf8_unicode_ci` / `utf8mb4_unicode_ci` | Arabic comparison results may differ; normalization still applies | Send query 7 output |

## A7. Status of the remaining blockers

| Blocker | State |
|---|---|
| 1. Production trigger verification | SQL ready (A6); waiting for the owner's output |
| 2. Backup decision | Clarified (A3); unresolved without the Desktop App source or backup-machine evidence; copies keep today's per-item lifecycle |
| 3. Isolation and concurrency assumptions | Local confirmed (A2.1); production via A6 query 5; empirical proof needs the scratch-database run (A2.3) — **approval requested** |
| Open scope question | Extending the lock protocol to Edit (link change) and Move (author change) — not in scope unless approved (A2.5) |


---

# Addendum — Version 4: local implementation and verification (2026-11-25)

Implemented locally under the final owner decisions. Nothing staged, committed, pushed or deployed; no production access; no migration; `olddb` only read; the disposable database was dropped afterwards.

## B1. Production verification results (owner) — consequences

- MariaDB 10.11.19, REPEATABLE-READ (global, session and Laravel `main`), InnoDB on all seven tables, exactly six triggers (update triggers NULL-safe), definers exist → the design applies as planned.
- `fill_khotab_category_index` / `fill_series_category_index` are **not** runtime dependencies: no Laravel code references them (`grep` over `app/ config/ database/ routes/ resources/ bootstrap/ tests/`), and `sync_khotab_categories` (body read on the local copy) only deletes / inserts `khotab_category_index` rows. Not recreated.
- `channel_id` (NOT NULL, no default): every copy receives the primary's final `channel_id` (group flow 0, series flow the series' or chosen channel, author flow the chosen one) — covered by tests.
- `name` / `prename` nullable, `utf8mb3_unicode_ci`: the search folds `COALESCE(name, '')`; the collation behaves like the local `utf8_unicode_ci` (same comparisons).

## B2. Changed files

| File | Change |
|---|---|
| `config/admin_khotab.php` | `additional_authors.max` (10), `search_page_size` (20), `search_min_length` (2) — the only place these limits live |
| `app/Domain/Admin/Actions/Khotab/KhotabAdditionalAuthorCopies.php` (new) | the protocol and the copies: per-copy authorization, `transaction()` (no outer transaction on `main`; 3 attempts on deadlock / lock wait), author locks (one locking read, ascending id), selection checks (exists, visible, not the primary, ≤ limit), multi-author duplicate check (one indexed query per author, every conflict named), copy insert (+ counters, location counts, audit with `created_with`), post-commit image copy and messages |
| `app/Domain/Admin/Actions/Khotab/KhotabItemCreation.php` (new) | result: primary id, copies, missing images |
| `app/Domain/Admin/Actions/Khotab/InsertKhotabItemAction.php`, `InsertKhotabAuthorItemAction.php`, `InsertKhotabSeriesItemAction.php` | author lock before the duplicate check; without additional authors the old check / message / writes unchanged; copies inside the same transaction; image copy after commit; return `KhotabItemCreation` |
| `app/Domain/Admin/Actions/Khotab/KhotabArchiveImages.php` | `copyFromItem()` — copies the primary's stored GIF / frame to each copy's own path |
| `app/Domain/Admin/Support/ArabicSearchText.php` (new) | search folding (PHP + SQL expression), literal `LIKE` pattern (`ESCAPE '!'`, portable) |
| `app/Domain/Admin/Http/Controllers/KhotabAuthorSearchController.php` (new) | `GET admin.khotab.author-search/{media}` JSON: `{results:[{id,name}], more}` |
| `routes/admin.php` | the search route inside the Khotab group (`admin.khotab-media`, `khotab.additem`, `throttle:60,1`) |
| `app/Domain/Admin/Http/Requests/Khotab/StoreKhotabItemRequest.php` | `additional_authors` rules + Arabic messages (inherited by the author / series requests) |
| `KhotabAuthorAdminController`, `KhotabGroupAdminController`, `KhotabSeriesAdminController` | `createItem`: up to 10 previously selected authors after a failed submit only; `storeItem`: message from the result + optional image warning |
| `resources/views/admin/khotab/_additional-authors.blade.php` (new) | the selector + submit protection (inline vanilla JS, no dependency); since v5 also: «أضف» held while archive.org is read, archive.org fields kept after a refused submission, `[hidden]` enforced, hint toggled (C3) |
| `resources/views/admin/khotab/{authors,groups,series}/item-create.blade.php` | one `@include` above «أضف»; since v5 the «التالي» script announces its archive.org request (`w2a:archive-start` / `w2a:archive-end`) |
| `resources/views/components/admin/contextual-flash.blade.php` | the image warning line |
| `tests/Feature/Admin/KhotabMultiAuthorCreationTest.php` (new) | 52 tests (47 in v4 + 5 in v5) |

Single-author behaviour: identical rows, counters, audit row, duplicate message and redirect message; the only changes are the approved earlier author lock (plus its deadlock retry) and the submit-button protection.

## B3. Test results

| Suite | Result |
|---|---|
| `tests/Feature/Admin/KhotabMultiAuthorCreationTest.php` | 47 / 47 (52 / 52 in v5, C3) |
| `tests/Feature/Admin` (incl. the author / group / series parity suites and `KhotabMediaScopeTest`) | 1525 passed, 13 skipped, 0 failed |
| `tests/Feature/Content` | 1672 passed, 1 skipped |
| `tests/Feature/Engagement` | 28 passed |
| `tests/Architecture` | 7 passed |
| PHPStan (12 changed / new PHP files) | 0 errors |
| Pint (all changed PHP files) | passed |

Covered: the three contexts; single-author regression (rows, message, duplicate message); multi-author creation (content copied, own ids, standalone, `newslist = fixed = 0`, channel / location copied, `down = 2, trial = 0, booking = 0`, counters, primary group / series counters once, location counts, audit `created_with`); primary / hidden / unknown / duplicate / malformed selections; the limit (11 refused; changed config honoured); duplicate links naming every conflict; per-destination authorization (exactly one extra section check per additional author, via a spy), section moderators allowed / refused, standalone and forum-linked; full rollback on a failing copy (all three contexts); the outer-transaction guard; independence after edit and delete (files, counters); one download per image, copy per record, download failure and copy failure warnings, single-author silence; the selector on the forms without any author list; old-input rehydration; Arabic search (Alef variants, Alef maqsura / Ya, Ta marbuta, tatweel, diacritics), NULL names, hidden / primary exclusion, minimum length, literal wildcards, paging, authorization.
Not runnable on SQLite (covered below on MySQL): row locks, InnoDB snapshots, the category triggers. The group create page's existing `BINARY title` ordering cannot run on SQLite, so its markup was checked in the browser.

## B4. Concurrency proof (disposable MySQL)

Database `am_disp` on the local `mysql57` container (MySQL 5.7.44, InnoDB, REPEATABLE-READ), user `am_user` restricted to it; structure copied from `olddb` (no data); the procedure and the three Khotab triggers recreated in their production form (`IF NOT (OLD.cat <=> NEW.cat)`), `fk_khotab_category` with `ON DELETE CASCADE`. Workers ran the **real actions** in separate PHP processes (authorization stubbed — covered by the feature tests). In every race a blocker transaction held the contended author row(s) and `INNODB_TRX` confirmed **2 transactions in LOCK WAIT** before release. Dropped afterwards (`am_user` and `am_disp` confirmed gone). Production is MariaDB 10.11; the locking and read-view semantics used are the same InnoDB REPEATABLE-READ behaviour.

| Scenario | Result |
|---|---|
| S1 single vs single, same author / link (author page) | 1 created, 1 refused with the existing message; 1 row; counter + 1; 1 audit row |
| S2 multi vs multi, overlapping authors 7 & 9 (author page vs group page) | 1 created (3 rows), 1 refused naming authors 7 and 9 |
| S3 single (author 8) vs multi (7 + 8, series page) | 1 created, the other refused naming author 8; 1 row for author 8 |
| S4 the same multi-author request twice | 1 created, 1 refused naming all three; group count + 1 once |
| **S5 negative control: the old check-then-lock order** | **2 duplicate rows** — the harness reproduces the race the new order prevents |
| S6 forced deadlock (heavy transaction vs the action) | InnoDB rolled back the action; Laravel retried (2 attempts); 1 primary + 1 copy, 2 audit rows, group + 1 once, **1** archive.org request, 2 image files |
| S7 failure on one copy (BEFORE INSERT signal) | 0 items, 0 category-index rows, 0 audit rows, counters and group count unchanged, 0 image files |
| S8 category triggers | creation adds no index rows; categorising two copies indexes each by its own id; deleting one copy removes only its rows |
| All workers | `@@transaction_isolation = REPEATABLE-READ` |

## B5. Performance

| Measurement | Value |
|---|---|
| Duplicate check, single author (existing query), `olddb` | `ref` on `idx_khotab_author_listing`; 1.5 ms (typical author), 90–110 ms (author 154, 33,093 items) |
| `author IN (11 largest authors)` | optimizer chose a full scan (`ALL`, 289,891 rows): 447 ms — **not used** |
| One indexed query per author (used), 11 largest / 11 typical authors | 266 ms / 62 ms |
| Author search, normalized `LIKE` over 736 authors | ≈ 5.8 ms |
| Whole creation on MySQL (small tables): single / primary + 10 copies with one image | 31.5 ms / 175 ms (1 download, 11 files) |

No index added. The worst case (10 copies for the largest authors) holds the author locks for about a quarter of a second longer.

## B6. Browser verification (headless Chrome, local)

The real author create page (rendered by the app) with the site's assets and a local stand-in for the search endpoint using the real `ArabicSearchText`; external requests blocked. 18 / 18 checks passed: no request and no list on load; one character → no request, hint; one request per typing burst (350 ms debounce) with `exclude` = primary; 20 results + «عرض المزيد», page 2 appended; ticking adds chips, hidden inputs and the count; Arabic variant search; selections kept across searches; a slow (stale) response never replaces newer results; at 10, unticked boxes disabled and the limit announced; an 11th refused; removing a chip re-enables; Enter in the search box never submits; «أضف» disabled with «جارٍ الحفظ…» and `aria-busy` while the request is in flight; the POST carries exactly the selected ids and no search text; back navigation restores an enabled «أضف»; 390 px without horizontal overflow; RTL.
**Defect found and fixed by this check:** the submit protection looked up «أضف» when the script ran — above the button, so it found nothing; it now takes the button from the submit event.
**Gap in this check (found in v5, C3):** «عرض المزيد» was verified through its `hidden` attribute, not its computed visibility; with the theme's CSS the attribute had no effect (UI-01). The v5 checks assert computed visibility on the real page.

## B7. Remaining limitations and risks

| Item | Note |
|---|---|
| Edit and Move | Can still create a same-author duplicate link (owner decision: unchanged, outside the protocol) |
| External writers | Legacy panels, direct SQL and the backup client are outside the Laravel lock protocol (legacy out of scope) |
| Backup | Each copy is a normal backup job (`down = 2`); duplicate downloads / storage accepted; de-duplication deferred |
| Lock duration | Up to ≈ 0.3 s extra for 10 copies of the largest authors |
| Stale selection | The search reflects the moment of the search; the submission re-checks everything |
| Back-navigation restore | Relies on the browser's page cache event (`pageshow`); a browser without it reloads the page, which also re-enables the button |
| Proof engine | MySQL 5.7 InnoDB locally; production MariaDB 10.11 InnoDB (same semantics; production isolation confirmed by the owner) |
| SEO | Copies share title / description (accepted) |

## B8. Manual UI verification checklist (AdminCP, after deployment)

*v5: every item below was executed locally on the real application and `olddb` (section C4); after deployment the list remains the post-deployment smoke check.*

1. Open «إضافة مادة» from a sheikh page, a group and a series (video and audio): after «التالي» the block «دعاة مشاركون (اختياري)» appears above «أضف»; no names are listed before typing.
2. Type one letter → hint only; type «احمد» → names with «أحمد» appear; type «ابراهيم», «مصطفي», «فاطمه» → the hamza / ya / ta-marbuta spellings are found.
3. The page's own sheikh and hidden sheikhs never appear.
4. Tick three names, search something else, tick one more → four chips; «×» removes one; the counter follows.
5. At ten, the remaining boxes are greyed out and the message shows.
6. Press Enter in the search box → the form is not submitted.
7. Submit with two additional sheikhs → «أضف» turns into «جارٍ الحفظ…»; the page shows «تمت إضافة المادة لـ 3 دعاة (المواد: #…)»; each sheikh's page lists the material; the copies are not in the group / series, not on the home «new» list, not pinned.
8. Submit a link one of the selected sheikhs already has → one error naming him; nothing created.
9. Submit with no additional sheikh → exactly the old message «تمت إضافة المادة».
10. With archive.org images: every created material shows its own frame / GIF; if an image fails, the yellow warning names the materials and «سحب الصور» repairs them.
11. Edit and delete one copy → the others are unchanged.
12. A video-only moderator cannot use the selector on audio pages (and vice versa).

## B9. Deployment inventory (proposed) and rollback

*Superseded by D4–D6 (final inventory with checksums, gated deployment and rollback plan).*

Runtime files, all under `/home/w2acp/laravel-app` (nothing under `public_html`, no `.env`, no database change):

```
config/admin_khotab.php
routes/admin.php
app/Domain/Admin/Actions/Khotab/KhotabAdditionalAuthorCopies.php        (new)
app/Domain/Admin/Actions/Khotab/KhotabItemCreation.php                  (new)
app/Domain/Admin/Actions/Khotab/InsertKhotabItemAction.php
app/Domain/Admin/Actions/Khotab/InsertKhotabAuthorItemAction.php
app/Domain/Admin/Actions/Khotab/InsertKhotabSeriesItemAction.php
app/Domain/Admin/Actions/Khotab/KhotabArchiveImages.php
app/Domain/Admin/Support/ArabicSearchText.php                           (new)
app/Domain/Admin/Http/Controllers/KhotabAuthorSearchController.php      (new)
app/Domain/Admin/Http/Controllers/KhotabAuthorAdminController.php
app/Domain/Admin/Http/Controllers/KhotabGroupAdminController.php
app/Domain/Admin/Http/Controllers/KhotabSeriesAdminController.php
app/Domain/Admin/Http/Requests/Khotab/StoreKhotabItemRequest.php
resources/views/admin/khotab/_additional-authors.blade.php              (new)
resources/views/admin/khotab/authors/item-create.blade.php
resources/views/admin/khotab/groups/item-create.blade.php
resources/views/admin/khotab/series/item-create.blade.php
resources/views/components/admin/contextual-flash.blade.php
```

Order: back up the 14 existing files; upload the 5 new files first (unused until referenced), then the PHP classes, the request and controllers, `config/admin_khotab.php`, `routes/admin.php`, and the views last; then `php artisan config:clear` (or `config:cache` if production caches config), `php artisan route:clear` (or `route:cache`), `php artisan view:clear`; reload PHP-FPM if OPcache does not revalidate timestamps.
Rollback: restore the 14 backed-up files, delete the 5 new files, clear config / route / view caches. No schema or data to revert; materials already created with copies stay ordinary independent items.

## B10. Concurrency proof repeated on MariaDB 10.11.19 (2026-11-25)

Isolated container `w2a-mariadb-proof` from the local image `mariadb:10.11` (`1:10.11.19+maria~ubu2204`, the production version), `127.0.0.1:3310`, data on tmpfs (512 MB, nothing on disk), `--transaction-isolation=REPEATABLE-READ --innodb-lock-wait-timeout=50`; schema = structure-only dump of the ten tables from `olddb` (read-only), the procedure and the three Khotab triggers in production form, `fk_khotab_category` CASCADE; user `am_user` limited to `am_disp`; random credentials in 0600 files, never printed. Same nine scenarios, same real actions (scripts only switched to port 3310 and MariaDB's `@@tx_isolation`). Every worker reported `REPEATABLE-READ on 10.11.19-MariaDB-ubu2204`.

| Scenario | MariaDB 10.11.19 result |
|---|---|
| S1 single vs single | 2 waiting; 1 created, 1 refused (existing message); 1 row, counter + 1, 1 audit row |
| S2 multi vs multi (7 & 9 shared) | 2 waiting; 1 created (3 rows), 1 refused naming 7 and 9 |
| S3 single vs multi | 2 waiting; 1 created, the multi refused naming 8; 1 row for 8 |
| S4 double submission | 2 waiting; 1 created, 1 refused naming 7, 8, 9; group + 1 once |
| S5 negative control (old order) | **2 duplicate rows** — the race is real without the new order |
| S6 deadlock | the action was the victim (heavy transaction got the group lock), retried (2 attempts); 2 rows, 2 audit rows, group + 1, 1 archive.org request, 2 files |
| S7 failing copy | 0 items, 0 category-index rows, 0 audit rows, counters and group unchanged, 0 files |
| S8 category triggers | per-record index; deleting one copy removes only its rows |
| S9 timing | single 27.5 ms; primary + 10 copies with one image 95.3 ms |

Teardown: container removed (tmpfs data discarded), both credential files deleted, port 3310 released; `mysql57`, `olddb`, `maria-final` and every other container untouched; no pruning.

---

# Addendum — Version 5: local technical acceptance (2026-11-26)

Executed on the **real local application** (`php artisan serve`, `127.0.0.1:8123`), its `main` connection = local `olddb` (MySQL 5.7, docker `mysql57`), real archive.org over the internet, driven by headless Chrome (DevTools protocol) and `curl`. Admin sessions were minted locally for super-admin **2** and for admin **12** (moderator, temporary Spatie permissions in the local SQLite database). No production access, no deployment, no git staging / commit / push, no schema change. Every test material was titled «اختبار قبول …» and removed afterwards (C5). Harness and raw results: scratchpad `accept/` (not in the repository).

## C1. T10 — cause confirmed

| Evidence (owner's T10, rows as stored) | Value |
|---|---|
| #298793 / #298794 / #298795 `gif`, `frame` | 0 / 0 on all three; no files on disk |
| #298793 `linksize` | **26 214 400** = exactly 25 MB → the hand-typed «حجم المادة» field |
| Control #298796 (single author, same link) `linksize` | **26 489 512** = archive.org's `original_size` |

`archive_gif`, `archive_frame` and `original_size` are all hidden inputs of the same «التالي» archive.org fragment. Their joint absence means **the T10 POST did not contain the fragment**: the form was submitted before «التالي»'s request had returned (or it never returned). The image download / copy code was never given anything to fetch. No warning appeared because the warning reports images that were *offered and not stored*; with nothing offered the server cannot know.

Reproduced on the unchanged code (rows deleted afterwards):

| Path | What was done | Result |
|---|---|---|
| R1 — submit while «التالي» is pending | archive.org request held; title, size «25», sheikh 24; «أضف» (enabled) | POST without the three fields; #298797 / #298798 `gif = frame = 0`, `linksize = 26 214 400`, clean success message, no warning — **identical to T10** |
| R2 — resubmit after a refusal | fragment loaded; sheikhs 68 + 178 → refused (178 has the link); «×» on 178; «أضف» | the re-rendered form had lost the three fields and hides «التالي»; #298799 / #298800 `gif = frame = 0`, `linksize = 0`, no warning |

Both paths are pre-existing (they apply to single-author creation and to OLD's flow); multi-author creation multiplies the loss and makes R2 a routine step (conflict → remove sheikh → resubmit).

## C2. Fixes (local, no PHP change)

| # | Defect | Fix | Files |
|---|---|---|---|
| 1 | R1 — submission possible before archive.org's data joined the form | the «التالي» script dispatches `w2a:archive-start` / `w2a:archive-end` on the form; the block keeps «أضف» disabled, `aria-busy`, labelled «جارٍ جلب بيانات archive.org…» until the request settles (answer, «not found», or network error → OLD's `alert('error')`, then re-enabled); a submit event while pending is cancelled (Enter in a field, scripted submit) | three `item-create` views (2 lines each), `_additional-authors` |
| 2 | R2 — archive.org fields lost by a refused submission | the previous POST's `archive_gif`, `archive_frame`, `original_size` are rendered again as hidden inputs with the note «صور archive.org التي جُلبت مع المحاولة السابقة محفوظة وستُضاف مع المادة.»; editing the link removes them, so they are never stored against another link | `_additional-authors` |
| 3 | UI-01 «عرض المزيد» shown when it should be hidden | the theme's `.btn{display:inline-block}` (`bootstrap-rtl.min.css`) overrode the `hidden` attribute → `.w2a-aa [hidden]{display:none !important}` | `_additional-authors` |
| 4 | UI-02 «اكتب حرفين على الأقل.» stayed visible | the hint is shown only while the text is shorter than two characters; the status line no longer repeats it | `_additional-authors` |

Unchanged: server validation and authorization; the server still fetches only `http(s)` archive.org URLs, so kept values (user-posted, as before) cannot widen what is fetched.
**For owner review:** fix 1 also applies to single-author submissions — «أضف» now waits for «التالي»'s archive.org request (bounded by `admin_khotab.archive_timeout`, 20 s), in line with Q-B. Reverting it means removing the two `dispatchEvent` lines per view.

## C3. Automated tests and static analysis

5 tests added to `KhotabMultiAuthorCreationTest.php` (now 52): kept archive fields after a refusal (author, series), no archive fields on a fresh form and the guard / `[hidden]` rule rendered (author, series), the group view's guard events (its page uses MySQL-only ordering, so it is rendered in the browser check).

| Suite | Result |
|---|---|
| `KhotabMultiAuthorCreationTest.php` | 52 / 52 |
| `tests/Feature/Admin` | 1530 passed, 13 skipped, 0 failed |
| `tests/Feature/Content` | 1672 passed, 1 skipped |
| `tests/Feature/Engagement` | 28 passed |
| `tests/Architecture` | 7 passed |
| PHPStan (whole project) | 0 errors |
| Pint (whole project) | passed |

## C4. Acceptance results (all executed locally; every count read back from `olddb`)

| Test | Result | Evidence |
|---|---|---|
| T1 selector, no list on load | **Passed** | block above «أضف», «المختارون: 0 من 10», 0 names, 0 search requests |
| T2 Arabic variants | **Passed** | «احمد حطيبه» → 24, «ابراهيم الزبيدي» → 68, «مصطفي العدوي» → 3, «الغرفه الدعويه» → 58; one request per pause; one character → no request |
| T3 hidden / own sheikh | **Passed** | «اغسل» never 12, «الرضواني» never 248, «المالكي» never 70 |
| T4 persistent selection | **Passed** | 4 chips / hidden inputs / counter after two searches; earlier ticks shown again; «×» → 3 |
| T5 limit 10 | **Passed** | at 10 all 13 unticked boxes disabled, «بلغت الحد الأقصى (10 دعاة).»; 11th impossible; removal re-enables |
| T6 Enter in search | **Passed** | searches at once, 0 POST |
| T7 three contexts | **Passed** (owner, v4) and re-run with images | author: #298801–03; group 53: #298804 (`group_id = 53`) + #298805 (58, standalone); series 7352: #298806 (`ser_id = 7352`) + #298807 (24, standalone); copies `group_id = ser_id = newslist = fixed = 0`; title, description, notes, meta, link, size, time, channel, location, `down = 2`, `trial = booking = 0` equal to the primary; `weight = id`; group / series `count` + 1 once |
| T8 conflict | **Passed** (owner) and re-run | «هذه الوصلة مضافة بالفعل لدى: الشيخ أسامة بن عبد الله خياط (المادة #127012 …)»; nothing created; chips, input and (since C2) archive.org data kept; «أضف» enabled |
| T9 single author | **Passed** (owner) and re-run on the fixed code | exactly «تمت إضافة المادة»; #298816 `gif = frame = 1`, `linksize = 26 489 512`, files present; resubmission → «هذه الوصلة مضافة بقاعدة بيانات الموقع في المادة : #298816 …»; audit without `created_with` |
| **T10 images** | **Passed** (fixed code) | #298801 (70, pinned as ticked), #298802 (24), #298803 (68): `gif = frame = 1`, `linksize = 26 489 512`; each id has its own GIF (347 891 B) and JPEG (9 226 B), SHA-256 identical to the files fetched directly from archive.org; same for group (399 851 B / 5 981 B) and series (399 279 B / 7 697 B) |
| T10b chosen frame across a refusal | **Passed** | frame `_000490` clicked → refused (178) → kept → resubmitted: #298811 (3) and #298812 (58) both store that frame (7 787 B, SHA-256 equal to archive.org) |
| T10c archive.org failure | **Passed** | frame URL pointed at a missing archive.org file: success message + yellow warning «… #298817 (الصورة الثابتة)، #298818 (الصورة الثابتة) — استخدم «سحب الصور» …»; GIFs stored; «سحب الصور» on #298818 → `frame = 1` (5 981 B) |
| T11 independence | **Passed** | title of copy #298802 edited in the real edit form → only that row's title changed (+ `linksize`, see C6); copy #298803 deleted from its confirmation page → only sheikh 68 `vedio` − 1; every remaining file byte-identical; sheikh pages updated accordingly |
| T12 sections | **Passed** | video-only moderator (12): video page 200 with the selector, video search 200 (`{results:[{id,name}], more}` only, no primary / hidden), audio page 403, audio search 403, audio POST with additional sheikhs 403 and nothing created, video POST with 24 + 68 → #298813–15, audit `actor_admin_id = 12`, `created_with = 298813`; audio-only moderator: the mirror image; no permission: search 403; signed out: 401; unknown media: 404 |
| Audio creation | **Passed** | `/authors/70/audio`: search requests only `/author-search/audio`; #298808–10 `vedio = 0`, `audio` + 1 for 70, 58, 68 and no `vedio` change |
| Archive guard / UI-01 / UI-02 | **Passed** | 25 / 25 browser checks on the three forms (held request: no POST by Enter, click or scripted submit; release → «أضف» and the three fields back; network failure and non-archive link re-enable «أضف»; «عرض المزيد» hidden / shown / appends 20 → 40 / hidden again; hint shown only below two characters); no console errors; 390 px: no horizontal overflow, labels within the screen |

Counters after the creations, against the baseline: sheikh 70 `vedio` + 3 / `audio` + 1, 24 `vedio` + 2, 68 `vedio` + 1 / `audio` + 1, 58 `vedio` + 2 / `audio` + 1, 3 `vedio` + 1, others 0; group 53 + 1; series 7352 + 1; 12 materials; author / group / series location counts equal to the real number of materials. Audit: one `add_item` per material, `created_with` on every copy = its primary, none on primaries.

## C5. Cleanup (only test-created data)

| What | Done |
|---|---|
| Owner's T7–T10 materials + control (#298785–#298796, 12) | deleted through `DeleteKhotabItemAction` (counters reversed: 70 − 5, 24 − 3, 68 − 2, 3 − 1, 1 − 1, group 53 − 1, series 7352 − 3) |
| Reproduction and acceptance materials (#298797–#298818, 22; #298803 deleted in T11) | deleted the same way (guarded: title must start with «اختبار قبول») |
| Final state | sheikh / group / series counters, material count (292 592) and highest id (298 784) **identical** to the baseline taken before any of these writes |
| Image files | 25 files (#298796, #298801–#298818) and the two directories the tests created (`khotab_gifs/298`, `khotab_frames/298`, created with #298796) removed |
| Location residue of the owner's runs | 9 zero-count rows (`authors_location` 109–115, `groups_location` 54, `series_location` 260) removed; older zero rows untouched |
| Moderator 12 | temporary permissions revoked (0 permissions, 0 roles) |
| Local sessions minted for the tests | 28 files removed; the owner's own session kept |
| Kept on purpose | `admin_audit_log` 72–141 (34 `add_item`, 34 `del_item`) — append-only history |

## C6. Observations outside the feature (pre-existing, unchanged)

- Edit recomputes `linksize` from the two-decimal MB field (26 489 512 → 26 487 029 on #298802) — `UpdateKhotabItemAction`, committed, any edit; Edit is out of scope by owner decision.
- Deleting a material never removes its image files; location-count rows stay at 0 after deletions.
- After a refused submission the manual «حجم المادة» field reappears empty although archive.org's size is kept (and used); cosmetic.

## C7. Limitations

- One download per image for all copies is shown by the file identity and the copy code path; the outbound request count was proven in S6 and the automated tests, not observed on the network here.
- Browser coverage: headless Chrome at 1366 px and 390 px. Other browsers and the subjective look of the new label / note are for the owner.
- The Desktop backup client's handling of copies (`down = 2`) cannot be tested locally (source unavailable; accepted in A3).
- Production behaviour (caches, OPcache) is covered by the deployment steps in B9, not by these tests.

## C8. Final deployment inventory

Unchanged from B9 — 19 runtime files, 5 of them new; v5 touched only files already listed (`_additional-authors.blade.php` and the three `item-create` views). The test file and this report are not deployed. No `.env`, no `public_html`, no database change.

## C9. Status

*Superseded by D7: both owner decisions were approved and the remaining verification is complete.*

All technical acceptance tests (T1–T12, audio, image failure and repair, archive guard, UI-01, UI-02) **passed locally**, with the regression suites and static analysis in C3. Two image-loss paths and two UI defects were found and fixed in this round. Before deployment the owner still needs to (1) review the C2 changes, in particular the single-author effect of fix 1, and (2) give subjective UX approval of the new label and note. Deployment itself remains owner-managed (B9).

---

# Addendum — Version 6: owner approvals, final verification, deployment plan (2026-11-26)

## D1. Owner approvals (2026-11-26)

| Decision | Approved |
|---|---|
| Archive lookup submit protection (C2 fix 1) | for the author, group and series forms, single- and multi-author submissions |
| Loading label | «جارٍ جلب بيانات المادة من الأرشيف…» (replaces «جارٍ جلب بيانات archive.org…») |
| Image-preservation note after a refused submission | approved as is |
| Kept outside this implementation | Edit's size rounding, image files left after deletion, Desktop backup-client compatibility |

## D2. Link-change verification — three defects found and fixed

Probe on the v5 code (real app): (a) after «التالي», editing the link left the old link's GIF, frame, thumbnails and archive.org size in the form and kept «التالي» hidden — a submission would have stored the old link's images and size against the new link; (b) editing the link while «التالي»'s request was pending left «أضف» blocked with no way to fetch again, and the late answer for the old link then filled the form; (c) the browser waited indefinitely if the server never answered.

Fix — the «التالي» script of the three `item-create` views (identical in all three; no PHP change): each lookup is numbered and an answer for an older number is ignored (stale); editing the link discards everything archive.org supplied (hidden fields, thumbnails, preview, size block → the manual size field again), offers «التالي» again and releases «أضف»; a lookup is abandoned after `2 × archive_timeout + 15 s` (55 s: the server makes at most two 20 s requests), with OLD's «error» alert. In `_additional-authors`: the approved label, and a held button is never re-captured by a second lookup.

Browser verification on the real application (36 checks across the three contexts — all passed):

| Check | Result |
|---|---|
| Edit the link after the lookup | GIF, frame, size, thumbnails and preview cleared; manual size field back; «التالي» offered; «أضف» enabled (author, group, series) |
| «التالي» again | fetches the new link's data (new GIF, size 30 240 017) |
| Pending lookup | «أضف» disabled, `aria-busy`, «جارٍ جلب بيانات المادة من الأرشيف…»; Enter, click and scripted submit → 0 POST |
| Edit during the lookup | «أضف» enabled, form clean, «التالي» offered; the old answer released after the new one never populates the form; an old answer released with no new lookup leaves the form clean |
| Failure / timeout | network failure → alert, «أضف» restored; no answer → abandoned after 55 s, alert, restored; the late answer ignored |
| Refused submission | kept archive data shown; editing the link drops it and offers «التالي» |
| Creations after a link change | author (multi, 70 + 24), group 53 (multi, 70 + 58), series 7352 (single): every row has the new link's GIF and frame (SHA-256 equal to archive.org), size and `gif = frame = 1`; copies standalone; single-author message unchanged |

All test materials (#298819–#298823), their 10 image files, the directories they created and 15 local test sessions were removed; counters and material count equal the baseline (292 592, highest id 298 784).

Targeted regression: `KhotabMultiAuthorCreationTest` 52 / 52; all 17 Khotab admin test files 340 / 340; PHPStan 0 errors; Pint passed (whole project).

## D3. Remaining verification status

| Item | Status |
|---|---|
| T1–T12, audio, image failure / repair, archive guard, UI-01, UI-02 (C4) | passed locally |
| Link change, stale answer, pending block, failure, timeout (D2) | passed locally, three contexts |
| Concurrency on MySQL 5.7 and MariaDB 10.11.19 (B4, B10) | passed (disposable databases) |
| Production | not accessed; gates in D5 |

## D4. Final runtime inventory

19 files under `/home/w2acp/laravel-app` (local source: `/mnt/Projects/Php/pure/way2allah-migration/laravel-project`, same relative paths) — 5 new, 14 replaced; no `public_html`, `.env`, server configuration, database or Composer change. Checksums of every file after deployment and of the 14 originals expected on production are in the deployment kit (`release-files.sha256`, `baseline-expected.sha256`) and in its manifest's section 3.

| New | Replaced |
|---|---|
| `app/Domain/Admin/Actions/Khotab/KhotabItemCreation.php`<br>`app/Domain/Admin/Actions/Khotab/KhotabAdditionalAuthorCopies.php`<br>`app/Domain/Admin/Support/ArabicSearchText.php`<br>`app/Domain/Admin/Http/Controllers/KhotabAuthorSearchController.php`<br>`resources/views/admin/khotab/_additional-authors.blade.php` | `app/Domain/Admin/Actions/Khotab/KhotabArchiveImages.php`, `InsertKhotabItemAction.php`, `InsertKhotabAuthorItemAction.php`, `InsertKhotabSeriesItemAction.php`<br>`app/Domain/Admin/Http/Requests/Khotab/StoreKhotabItemRequest.php`<br>`app/Domain/Admin/Http/Controllers/KhotabAuthorAdminController.php`, `KhotabGroupAdminController.php`, `KhotabSeriesAdminController.php`<br>`config/admin_khotab.php`, `routes/admin.php`<br>`resources/views/components/admin/contextual-flash.blade.php`<br>`resources/views/admin/khotab/{authors,groups,series}/item-create.blade.php` |

Not deployed: `tests/Feature/Admin/KhotabMultiAuthorCreationTest.php`, this report.
Production compatibility: the 14 replaced files are expected at release 2026-11-19 (`a31fda7` + release), whose content equals the committed versions — the later commits touched none of them; the 88 existing classes the change uses exist at that state (the three changed later are referenced by class name only, from an otherwise unchanged `routes/admin.php`); PHP 8.3, Laravel 13; Composer's optimized (not authoritative) class map finds the new classes through PSR-4.

## D5–D6. Deployment and rollback plan

Kit: `/mnt/Projects/Php/pure/way2allah-migration/release-2026-11-26-khotab-multi-author/` — `khotab-multi-author-2026-11-26.zip` (SHA-256 `5e26bbbe74df50e61ed1a8758e6497454b66c1a106ef7cb82375fe36a7e12d4d`), the checksum and file lists, and `deployment-manifest.md` with the complete procedure:

1. Gates (read-only): G1 kit integrity; **G2 the 14 production files equal the expected checksums (else stop)**; G3 the 5 new files absent; G4 the 88 dependencies present; G5 PHP 8.3 / Laravel 13 via `/usr/local/bin/ea-php83`; G6 autoloader not authoritative; G7 record existing caches.
2. Backup of the 14 files to `/home/w2acp/deploy-backups/khotab-multi-author-<timestamp>/` (tar with permissions, checksums, ownership / mode listing, new-file list), verified by extraction and comparison with the expected baseline.
3. Staging outside the application with checksum verification, then a dependency-ordered `install -m 0644` loop as `w2acp` (`-o w2acp -g w2acp` if root), then in-place checksum and ownership verification.
4. Caches: `config:clear` / `config:cache`, `route:clear` / `route:cache` according to G7, `view:clear`; OPcache note.
5. Health: `php -l` on the PHP files, `route:list --path=author-search`, `config:show admin_khotab.additional_authors`, class loading, home page 200, signed-out search 401, log tail.
6. Smoke tests: read-only checks on the author, group, series and audio forms; optional first real multi-author material with read-only SQL checks.
7. Rollback: restore the 14 files from the backup (checksum-verified), delete the 5 new files, clear caches; no data to revert.

**Rehearsed locally:** the whole procedure (G1–G4, G6, backup and its verification, staging, install, post-checks, rollback back to the baseline checksums) was executed against an export of the committed tree standing in for production — every step passed; the rehearsal copy was deleted.

## D7. Readiness

*Superseded by E1–E5: deployed to production by the owner on 2026-11-26.*

**Ready for owner-managed production deployment**, conditional on gates G1–G7 passing on the server. Evidence: all acceptance and targeted verification passed locally on the real application; concurrency proven on MySQL 5.7 and MariaDB 10.11.19; regression and static analysis clean; deployment and rollback rehearsed. Production has not been accessed or changed; nothing was staged, committed, pushed or deployed. Deployment waits for the owner's explicit authorization.

Deferred limitations (owner decisions): Edit's size rounding; image files left after deletion; Desktop backup-client compatibility (copies are ordinary `down = 2` jobs; duplicate downloads accepted); Edit and Move outside the locking protocol; legacy / external writers; copies share title and description (SEO). Browser coverage: Chrome at 1366 px and 390 px.

---

# Addendum — Version 7: production deployment record (2026-11-26)

Deployed manually by the owner to `/home/w2acp/laravel-app` on **2026-11-26**, following the kit `release-2026-11-26-khotab-multi-author/` (`deployment-manifest.md`). Results as reported by the owner; this addendum is documentation only (no runtime file, staging, commit, push or deployment by the assistant).

## E1. Deployment results

| Step (manifest section) | Result |
|---|---|
| Pre-deployment gates G1–G7 (§5) | **all passed** (kit integrity, production baseline checksums, new files absent, dependencies present, PHP 8.3 / Laravel 13, autoloader, cache state) |
| Rollback backup (§6–§7) | created and verified |
| Install (§8) | all **19** runtime files deployed (5 new, 14 replaced); every file verified by SHA-256 against `release-files.sha256` |
| Caches (§9) | rebuilt — passed |
| Health checks (§10) | PHP syntax, class autoloading, configuration (`admin_khotab.additional_authors`) and route (`author-search`) checks passed; homepage **HTTP 200**; unauthenticated author-search endpoint **HTTP 401** |
| Browser smoke tests (§11) | completed by the owner on the **Author, Group and Series** «إضافة مادة» pages — passed |
| Not performed | no database migration, no `.env` change, no server-configuration change |

## E2. Production data

No test materials were created in production. Creating a real material through the feature is intentionally deferred to the **first legitimate material submission**; its result is to be checked with the read-only queries of the manifest's §11 (one row per sheikh; copies `group_id = ser_id = newslist = fixed = 0`, `down = 2`; `gif` / `frame` = 1 when archive.org offered images; `created_with` on each copy's audit row).

## E3. Rollback

Available unchanged: the verified backup taken in §6 and the procedure in §12 (restore the 14 files, delete the 5 new files, clear caches; no data to revert).

## E4. Separate issue (not part of this feature)

Existing **GRX Windows-1256 encoding errors** observed in production were confirmed by the owner to **predate this deployment**. They are outside this feature and must be tracked as a separate issue.

## E5. Status

**DEPLOYED & SMOKE TESTED — AWAITING FIRST REAL MATERIAL CREATION.**

Open item: verify the first real multi-author material (E2). Deferred limitations unchanged (D7): Edit's size rounding; image files left after deletion; Desktop backup-client compatibility; Edit and Move outside the locking protocol; legacy / external writers; copies share title and description (SEO).
