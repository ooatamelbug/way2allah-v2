# AdminCP B4: Variety item visibility (F08)

**Date:** 2026-11-14; owner review 2026-11-15.

**Approval:** owner, "Owner Approval: B4 Variety Visibility". Reviewed: "B4 Owner Review" (§9).

**Plan:** `docs/reports/admincp-feedback-implementation-plan.md` §F08 (F08.1–F08.4).

**Decision log:** #141.

**Status:** **CLOSED LOCALLY** (2026-11-15, after the owner review and the final adjustment in §9; validation green): the hidden-field semantics are
established and no Laravel-local correctness or security defect remains. **Not deployed, not production-verified.
Deployment remains BLOCKED** on production routing and legacy-exposure verification (§8).

- Not committed, not pushed, not deployed. No production access.
- No schema change and no data change. The local MySQL copy was read only, and no record was modified.
- `is_published` is untouched. No new visibility column. No media file is deleted.
- `7amlat` and `old-project` are unmodified. They were read for the legacy review, and one test reads Gen 2's
  `.htaccess`.
- **Deployment blockers remain (§8): production routing and legacy exposure verification.**

---

## 1. Hidden-field semantics (owner review, priority 1)

`nuke_anasheed_anasheed.hidden` is `tinyint(1) NOT NULL DEFAULT 0`.

### 1.1 Evidence

| Source | What it does with `hidden` |
|---|---|
| **Writers, OLD and Gen 2** (every `UPDATE` / `INSERT` on the table, scanned as full statements: 49 in OLD's Anasheed, w2a_cd, BackUp, Cat and Khotab modules and 5 in Gen 2; plus `.sql`, `.sh` and `.py` files and Gen 2's `crons/`) | **none writes `hidden`.** The Anasheed admin's `hidden = '$hidden'` (`admin/index.php:1411`) is `RecordLogs()`, a write to `nuke_islamic_logs`, not to the item |
| **AdminCP controls** (OLD's Anasheed admin, OLD's w2a_cd admin, Gen 2's `admincp/`) | **none:** no form field, select or option for an item's `hidden` (OLD's edit form has no such input) |
| **Readers, OLD public** `modules/Anasheed/index.php` | tests **only `== '0'`**: `Detailes()` :472 (`if hidden == '0'` show details, mirrors and comments; else render nothing, the `user_hidden.htm` template commented out as "not found"), `PlayMirror()` :610, `Codes()` :655, `DownSeries()` :935, and the same in w2a_cd :427 |
| **Readers, OLD other** | `Satellite` :754 (`hidden='0'`); BackUp admin statistics :1902–1948 (`hidden = 0`) |
| **Readers, Gen 2** | `vars/getright.php:15` (`hidden=0`); `vars/old.php:372, 394` (`hidden='0'`). The live item, group, listing and download scripts **ignore the column** |
| **No reader anywhere** | compares with `1`, `2`, `> 0`, `<> 0` or any other value |
| **Data (local 2025 copy)** | `0`: 16,493 rows; `2`: **32** rows; `1`: **0** rows |
| **The 32 rows** | all in the Cartoon department (`parent_id = 57`), groups 57, 135, 141, 173, 176, 203, 217, 224, 228 and 229, added 2009-04-27 to 2010-03-12. `is_published = 0`, as for 16,492 of the 16,493 visible rows, so it does not distinguish them. **All were visited in 2026** (`lastvisit` 2026-06 to 2026-11), because Gen 2's live item page ignores `hidden` |
| **History** | `nuke_islamic_logs` starts on 2025-03-17, so there is no record of who set the 2s. Khotab items and series use `1` (87 and 73 rows); only Variety items carry `2` |
| **Prior owner decision** | visitor parity A6 (2026-11-04) already treated `hidden ≠ 0` as hidden. Its owner-reviewed test fixtures used `hidden = 2` |

### 1.2 Conclusion

| Value | Meaning, established from code behaviour |
|---|---|
| `0` | **public** in every legacy reader |
| `2` | **hidden (not public).** The only legacy implementation that consults the column (OLD) treats it exactly as "not `0`": no details, mirrors or comments; mirror player, embed codes and series download refused; excluded from the satellite channel lists and admin statistics. **No code anywhere gives `2` a different state** |
| `1` | **hidden as well** by the same rule (every reader tests only `== '0'`). It never occurs in Variety data. It is the value Khotab uses, and the one B4's toggle writes |

**Not recoverable:** *why* the historical rows carry `2` rather than `1`. No writer exists in either codebase and the
log history starts in 2025. The pattern (one department, a 2009–2010 batch) suggests a one-off manual or SQL edit,
but that is inference, not evidence.

**B4 handling (unchanged, confirmed):**
- every non-zero value is hidden;
- existing `2`s are **not normalized** and stay `2` until an admin explicitly unhides the item (then `0`, with the
  audit recording `before: 2`);
- hiding writes `1`.

**Visible effect to note (G5).** On today's live Gen 2 site these 32 items are **fully public**, because Gen 2
ignores `hidden`. Under OLD and under Laravel (A6, then B4) they are hidden. Deploying B4 makes their
`var-item-{id}` pages 404.

**Owner approval (B4 Final Adjustment, 2026-11-15): the 32 items with `hidden = 2` must remain hidden.**

After deployment:
- public Laravel pages return HTTP 404 for them;
- they appear in no public listing or search;
- authorized administrators (`anasheed.editanasheed`, super-admins) can still manage them;
- **no `hidden` value is modified and no data migration is required.**

Decision-log #141.

## 2. Visibility behaviour

**The control (new; OLD and Gen 2 had none).** «إخفاء المادة» / «إظهار المادة»:
- **Where:** on the AdminCP edit page (the «ظهور المادة للزوار» panel) and in the item page's admin toolbar.
- **Request:** `PUT admincp/anasheed/items/{id}/visibility` with the **target** state, CSRF-protected.
- **Authorization:** `anasheed.editanasheed`, via route middleware and again in the action; the super-admin bypass is
  preserved.
- **Write:** one transaction with the row locked; **writes only `hidden`**.
- **Audit:** `hide_item` / `unhide_item` (module `anasheed`, entity `item`), with `{hidden: {before, after}}`.
- **No-op:** setting the current state writes and records nothing.

**Visitors** (Laravel surfaces):

| Surface | Hidden item |
|---|---|
| Group page `var-group-{id}` (and `-page-{n}`) | not listed (new filter) |
| Department news `anasheed-` / `cartoon-` / `documentary-` / `exclusive-news.htm` | not listed (new) |
| Home widgets: latest per department and «تشاهدون الآن» | not listed (new) |
| Related lists on an item page | not listed (new; OLD listed them) |
| Advanced search, category `var-category-{id}`, group `.grx` | not listed (existing filters) |
| **Direct `var-item-{id}` and `var-item-{id}-page-{n}`** | **HTTP 404**, and the hit counter does not move. This supersedes A6's "200 with suppressed details" (decision-log #141) |
| Comment post, send-to-friend | 404 |
| Player `POST /media-player` (item and mirror) | refused (empty response; existing) |
| `var-download-{id}` (GET and HEAD), `var-mirror-{id}-{m}` | 404, counters unchanged (existing) |

**Other public status codes are unchanged.** Visible items, missing items (404 as before), groups and every
non-Variety route behave as before; the existing visible-item and parity tests pass unchanged.

### 2.1 Administrative authorization (owner review §3)

Hidden-item access on public pages is restricted to **holders of `anasheed.editanasheed`**, and to **super-admins**
(the bypass is preserved). Before the review, any signed-in administrator had it.

| Viewer | Hidden item page (direct and paged) | Group page | Player | Downloads | Toggle |
|---|---|---|---|---|---|
| Visitor | **404** | not listed | refused | 404 | redirect / 401 |
| Admin **without** `anasheed.editanasheed` (none; other Anasheed keys; a Khotab key) | **404** | not listed, no marker | refused | 404 | **403** |
| Admin **with** `anasheed.editanasheed` | 200: A6 rendering (details, player, mirrors and comments suppressed), the «هذه المادة مخفية عن الزوار» notice and «إظهار المادة», `no-store, private` | listed with «مخفية», private | refused | 404 | allowed |
| Super-admin | as the holder | as the holder | refused | 404 | allowed |

**Mechanism.** `App\Support\Http\AdminViewer` gained `can(string $permission)`, so Content code does not depend on
Admin. It is implemented by `ContextualAdmin` with the same rule as `EnsureAdminHasPermission`: the `super-admin`
role, or `hasAnyPermission([...])`, which does not throw for an unseeded key. It is false for visitors without a
database query.

**AdminCP itself** (edit, frame, delete, mirrors, categories) binds items without any visibility filter, under each
route's own permission, so hidden items stay manageable there.

## 3. Changed files

**New:**

| File | Role |
|---|---|
| `app/Domain/Admin/Actions/Anasheed/SetAnasheedItemVisibilityAction.php` | authorized, locked, audited `hidden` write |
| `tests/Support/AnasheedVisibilityHarness.php` | every table the surfaces read, admins, permissions and audit |
| `tests/Feature/Anasheed/AnasheedVisibilityB4Test.php` | toggle, authorization, CSRF, audit, every surface, cache, preview authorization |
| `tests/Feature/Anasheed/AnasheedLegacyRouteOwnershipB4Test.php` | route ownership against Gen 2's rewrite rules |
| `tests/Feature/Anasheed/AnasheedGroupCardCountsB4Test.php` | the card-count fix *(final adjustment)* |

**Edited (application):**

| File | Change |
|---|---|
| `routes/admin.php` | `PUT …/items/{id}/visibility` in the existing `anasheed.editanasheed` group |
| `app/Domain/Admin/Http/Controllers/AnasheedItemAdminController.php` | `setVisibility()` |
| `app/Domain/Admin/Contextual/Controls/AnasheedItemControls.php` | toolbar control |
| `resources/views/admin/anasheed/items/edit.blade.php` | the visibility panel |
| `app/Support/Http/AdminViewer.php`, `app/Domain/Admin/Contextual/ContextualAdmin.php` | `can(permission)` *(owner review)* |
| `app/Domain/Content/Http/Controllers/AnasheedItemController.php` | visitor and non-holder 404; holder preview; comment and send-to-friend guards |
| `app/Domain/Content/Http/Controllers/AnasheedGroupController.php` | filter; holder listing with the marker (private); the card counts *(final adjustment)* |
| `app/Domain/Content/Http/Controllers/AnasheedNewsController.php` | filter (pinned and newest) |
| `app/Domain/Content/Services/ContentSidebarWidget.php` | filter in the related-list query |
| `app/Domain/Content/Services/ContentListingService.php` | filter in both home widget queries; `anasheedVisibleItemCountsByGroup()` *(final adjustment)* |
| `resources/views/anasheed/item.blade.php` | the holder-only notice |
| `resources/views/anasheed/group.blade.php`, `resources/views/components/content/anasheed-media-grid.blade.php` | the «مخفية» marker |
| `resources/views/components/content/anasheed-subgroup-grid.blade.php` (and `anasheed/group.blade.php`) | the card shows the supplied public count *(final adjustment)* |
| `docs/decision-log.md` | #141 |

**Edited (an unrelated test defect found by the B4 full run):** `tests/Support/AdsRenderingHarness.php` (B6c-1) now
freezes the clock. **Kept as corrected** (B6c-1 report §10).

**Edited (tests asserting superseded behaviour):**

| Test | Old assertion | New assertion |
|---|---|---|
| `AnasheedItemControllerTest` "hidden items remain viewable" | 200 | 404 for visitors |
| `AnasheedVisitorParityTest` A6 "keeps its chrome" | 200 with details suppressed, hits +1 | 404 for visitors, hits unchanged. The holder-preview half moved to the B4 test |
| `HomeKhotabHiddenVisibilityTest` "A6 preserved: … home widgets" | present | absent |
| `AnasheedVisitorParityTest` A9 (cards) *(final adjustment)* | «4 مقطع» from a stored counter with no rows | the same «4 مقطع», now from 4 real fixture items (the fixture changed, not the expectation) |
| `AnasheedGroupControllerTest` "renders premium subgroup …" *(final adjustment)* | «5 مقطع» from a stored counter with no rows | the same «5 مقطع», now from 5 real fixture items (the fixture changed, not the expectation) |

No other test expectation was changed.

## 4. Legacy route exposure matrix (owner review §4)

**Owning application** is what each pattern's code belongs to.

**Production reachability:** whether a production request reaches that code. It is **UNKNOWN for every legacy
row**: there was no production access. The recorded production docroot is Laravel's `newapp/`, but its contents and
rewrite rules are unverified. A Laravel equivalent does **not** make the legacy route unreachable: production may
still route it.

| # | Route family (pattern) | Owning application | Filters hidden items? | Laravel equivalent | Production reachability | Required deployment verification |
|---|---|---|---|---|---|---|
| L1 | `var-item-{id}`, `-page-{n}` | **Laravel** | **yes:** 404 for visitors and non-holders | — | confirmed only if production sends the URL to Laravel | rewrite / docroot shows Laravel's front controller; a hidden id returns 404 |
| L2 | `var-group-{id}`, `-page-{n}` | **Laravel** | yes | — | as L1 | as L1 |
| L3 | `anasheed-`, `cartoon-`, `documentary-`, `exclusive-news.htm` | **Laravel** | yes | — | as L1 | as L1 |
| L4 | `var-category-{id}`, `var-categories.htm` | **Laravel** | yes (items); tree gate on a stored counter (§5) | — | as L1 | as L1 |
| L5 | `search.htm` (varieties) | **Laravel** | yes | — | as L1 | as L1 |
| L6 | `var-download-{id}`, `var-mirror-{id}-{m}`, `var-series-{id}.grx`, `POST media-player` | **Laravel** | yes | — | as L1 | as L1 |
| L7 | `POST var-item-{id}/comments`, `send-friend-anasheed-{id}` | **Laravel** | yes (404) | — | as L1 | as L1 |
| G1 | Gen 2 `anasheed/item.php` (item page; `op=getit` download; `op=down_mirror`; `op=add_comment`; `op=send_friend`) | Gen 2 | **no** (`item.php:28`; `functions.php:459, 594` select by id only) | yes (L1, L6, L7) | **UNKNOWN** | confirm production does **not** serve `/anasheed/*.php`, and that the Gen 2 rules `var-item`, `var-download`, `var-mirror`, `add-anasheed-comment` and `send-friend-anasheed` (`.htaccess:96-104`) are not active |
| G2 | Gen 2 `anasheed/group.php` (group page; **`op=down_serious`, the `var-series-{id}.grx` playlist** via `functions.php:270`), `anasheed/more.php`, `anasheed/functions.php` listings (`:241`, `:314`, `:850`, `:870`) | Gen 2 | **no** | yes (L2, L3, L6 `.grx`, related lists) | **UNKNOWN** | as G1 (`var-group`, `var-series`, `-news` rules, `.htaccess:97-100, 105-108`) |
| G3 | Gen 2 `vars/var.php`, `vars/download.php`, `vars/more.php`, `vars/group.php`, `vars/index.php` | Gen 2 | **no** (`var.php:7`, `download.php:20`) | partly. Only the four `-news.htm` rules route to `vars/more.php`; the rest are raw paths | **UNKNOWN** | confirm `/vars/*.php` is not served |
| G4 | Gen 2 `vars/getright.php` (raw path only; no rewrite targets it) | Gen 2 | **yes** | yes (`.grx`) | UNKNOWN | as G3 |
| O1 | OLD comment routes: `add-anasheed-comment-{id}.htm` (Gen 2 rule → Gen 2 `item.php`); OLD `modules.php?name=Anasheed&op=Comment` (→ `file=Comments&op=Reply`) | Gen 2 / OLD | **no** (no check on the item) | yes (L7, `POST`) | **UNKNOWN** | confirm neither is served |
| O2 | OLD RSS / XML: `var-news.rss`, `var-group-{id}.rss` (`new_modules.php?name=RSS`, `VGroup()` and the news feed); `var-news.xml` (`name=flashxml&op=Anasheed`) | OLD (`new_modules.php` exists only in OLD) | **no:** titles and `var-item` links of hidden items | **no** | **UNKNOWN** | confirm `new_modules.php` is not served, or the feeds are removed |
| O3 | OLD satellite-channel pages: `var-channel.htm`, `var-channel-{id}.htm` (`name=Satellite&op=var_channel[_details]`) | OLD | **yes** (`Satellite/index.php:754`, `hidden='0'`) | no | **UNKNOWN** | confirm whether served. Hidden items are not listed there even if it is |
| O4 | OLD Anasheed module `modules.php?name=Anasheed&op=…` | OLD | **mixed:** `Detailes` suppresses details only (page 200); `Group` / `MoreAnasheed` listings **no**; `Play` (item player) **no** (`:584`); `getit` (download) **no** (`:629`); `getmirror` **no** (`:640`); `PlayMirror` **yes**; `Codes` **yes**; `DownSeries` **yes** | yes (L1–L7) | **UNKNOWN** | confirm OLD's public `modules.php` / `new_modules.php` is not served (Laravel's own `/modules.php` route only redirects two Khotab queries and returns 404 otherwise) |
| M1 | Media files: `archive.org` and `media/anasheed/…` URLs stored in `link` and the mirrors | external host or static files | **no** (a direct file URL is public by nature) | Laravel never prints them for hidden items | n/a | none for B4. A known file URL stays downloadable, which is inherent to public hosting |

**Conclusion.** Hiding is complete on every Laravel-served surface (L1–L7, tested), and **B4 does not claim
visitor-side hiding beyond that.** G1–G3, O1, O2 and O4 expose hidden items if production still routes to them.

## 5. Stored counts (owner review §5) and the card-count fix (B4 Final Adjustment)

### 5.1 Findings (owner review)

| Visitor-visible number | Source | Filtered by visibility? | Discrepancy |
|---|---|---|---|
| Sub-group card «N مقطع» (`anasheed-subgroup-grid`, on `var-group-{id}`) | **was** the stored `nuke_anasheed_groups.anasheed`; **now** dynamic (§5.2) | **was no; now yes** | **Fixed** (§5.2) |
| Group page badge «N مقطع متاح» | dynamic: count of the current page of the filtered query | yes for visitors (holders' view includes hidden, marked) | none |
| Group page items portlet gate (`total() > 0`) | dynamic, filtered | yes | none. A group whose items are all hidden shows no item list to visitors |
| Category tree `var-categories.htm` | stored `nuke_w2a_cat.anasheed_count`, used only as a `> 0` gate, never displayed | no | **none locally:** the 32 hidden items have no categories. Possible in future: a category whose only items are hidden would still appear in the tree and open to an empty list. **Not changed** (not in the approved scope) |
| Department news, home widgets, search, related lists | no counts shown | — | — |

### 5.2 The card count (owner-approved 2026-11-15)

**Semantics checked before replacing the stored value.**
- The stored counter is maintained per item by OLD's and Laravel's insert, delete and move actions on `group_id`, so
  it means **"items directly in this group"**. There is no descendant roll-up, no 98 + 16 merge, and no
  type, `vedio` or `is_published` condition.
- This is the same scope as the group page's own item list, whose only public condition is `hidden = 0`.
- The new count keeps exactly that scope and adds only `hidden = 0`.

**Calculation.** `ContentListingService::anasheedVisibleItemCountsByGroup(array $groupIds)`:

```sql
SELECT group_id, COUNT(*) AS visible_items
FROM nuke_anasheed_anasheed
WHERE group_id IN (…displayed sub-group ids…) AND hidden = 0
GROUP BY group_id
```

- **One query per page** for all displayed cards. Groups without visible items get 0, and an empty page runs no
  query.
- `AnasheedGroupController::show()` passes the map to the grid. The stored counter remains only as a fallback for a
  caller that supplies no map (none does).

**Unchanged:**
- the card order (`parent_id = X ORDER BY id DESC`) and which sub-groups are shown;
- the page's own item list (24 per page, `order_in_group DESC, mytime DESC`, visibility rules);
- the other card fields (sub-group count `child`, `hits`, notes).

**Stored values are never read for display and never written** (tested).

**Viewers:** the card shows the **public** count to every viewer, holders included. Hidden items are counted nowhere.

**Effect on the local copy** (1,204 sub-groups that can appear as cards; read only):
- **24 displayed numbers change**:
  - **9** because of hidden items, the 32 hidden items being spread over 10 groups. Group 57 itself is a root
    department and never appears as a card; a test fixture reproduces its stored 142 → visible 137;
  - **15** because the stored counter had **drifted** from the actual rows, independent of `hidden`, by 1–6: 13
    lower and 2 higher, for example 1224: 75 → 69; 396: 0 → 2.
- **1,180 are unchanged.**
- The drift corrections follow directly from "display the actual number of publicly visible items".

**Query performance** (local MySQL 5.7 copy, 16,525 rows, `SQL_NO_CACHE`, the largest real page with 474
sub-groups):

| Query | Time |
|---|---|
| The aggregate | **12–30 ms** |
| The page's existing item list | 20 ms (already on the page) |
| The page's existing count | 15 ms (already on the page) |

- `EXPLAIN`: a full table scan. **`group_id` has no index** in the legacy schema, so the page's existing item query
  scans the table too.
- **No index was added** (owner restriction). A `(group_id, hidden)` index would make both index lookups; it needs
  separate approval.
- **No N+1:** a page with 40 sub-groups runs exactly one aggregate and no more item-table queries than a page with 5
  (tested).

## 6. Cache behaviour

| Cache | Holds Variety rows? | Handling |
|---|---|---|
| Laravel application cache (home latest videos, audios and dump files; Khotab sidebar widgets; Khotab search) | **no:** all `nuke_islamic_khotab` | none needed |
| Variety queries (all L1–L7 surfaces) | **uncached** | immediate |
| HTTP caching | visitor Variety pages are not publicly cacheable (tested); holder pages with hidden content are `no-store, private` | none needed |
| Gen 2 `SimpleCache` | Gen 2 pages only | part of the legacy exposure (§4) |
| CDN or reverse proxy | **UNVERIFIED** | deployment prerequisite 3 |

**No five-minute delay exists.** A test warms every surface, hides the item, checks every surface immediately, then
unhides and checks again.

## 7. Tests and results

### Targeted

| File | Tests | Covers |
|---|---|---|
| `AnasheedVisibilityB4Test` | **28 passed** | the earlier 22 (toggle, audit, no-op, legacy `2`, validation, media files, authorization, CSRF, controls, all surfaces, refusals, cache, holder preview, group marker, HTTP caching), **plus 6 from the review:** non-holders (no keys; five other Anasheed keys; a Khotab key) get visitor behaviour on the direct and paged URLs, download, mirror, player (item and mirror), group page and toggle (403); a super-admin and a holder may preview, with media still refused; `AdminViewer::can()` for visitor, non-holder, holder and super-admin |
| `AnasheedLegacyRouteOwnershipB4Test` | **4 passed** | Gen 2 rule list; 14 Laravel-owned URLs; 6 unowned URLs and 9 raw scripts not served by Laravel |
| `AnasheedGroupCardCountsB4Test` *(final adjustment)* | **7 passed** | public counts in the unchanged card order, covering group 57's fixture (stored 142 → 137, with hidden values 1 and 2), a group without hidden items, an all-hidden group (0), an empty group and a drifted counter (10 → 3); the service (zeros, empty input); hide → 136 and unhide → 138 immediately (including a legacy `2`); stored counters never written; one aggregate and no extra item queries for 40 vs 5 sub-groups; the page's own pagination (24) and order unchanged; holders see the same public counts |
| `AnasheedVisitorParityTest` A9 (cards) | passes | fixture given 4 real items, so the existing «4 مقطع» expectation now reflects real rows (expectation unchanged) |
| **B4 total** | **39 passed** | |
| Relevant authorization tests (`tests/Feature/Admin/Contextual`, including `AdminViewerPortTest`) | passed with B4 | 134 tests together |

**Mutation checks:**
- Removing any one of the 9 visibility filters or guards fails a B4 test.
- Reverting either controller's preview check from `can('anasheed.editanasheed')` to "any signed-in admin" fails 3
  tests.
- Dropping `hidden = 0` from the card aggregate fails 5 count tests; reverting the card to the stored counter fails 4.

### Full validation

| Check | Result |
|---|---|
| **Full suite, final (after the final adjustment)** | **3,652 tests: 3,613 passed, 0 failed, 0 errors, 39 skipped**; 31,152 assertions; exit 0 (+7 count tests since 3,645). One earlier run found the `AnasheedGroupControllerTest` fixture (stored «5» without rows; fixed in the fixture, §3); one run was terminated externally (exit 143, no result) and rerun. Skips unchanged (MariaDB 34, benchmarks 4, GD 1). The B6c-1 frozen-clock fix is kept |
| Full suite after the owner review | 3,645 tests: 3,606 passed, 0 failed, 39 skipped |
| PHPStan | **0 errors** |
| Pint `--test` | **passed** |

## 8. Deployment blockers and prerequisites

**Blockers (must be cleared before deployment, even though B4 is closed locally):**

1. **Production routing** (plan F08.4): verify read-only that production sends every Variety URL (L1–L7) to Laravel.
2. **Legacy exposure**: verify that the Gen 2 scripts (`/anasheed/*.php`, `/vars/*.php`), OLD's `modules.php` /
   `new_modules.php` (Anasheed, RSS, flashxml) and the Gen 2 rewrite rules G1–G3, O1, O2 and O4 are not served, or
   are disabled. Until then, "hidden" means hidden on Laravel's surfaces only.

**Prerequisites:**

3. Confirm that no CDN or reverse proxy caches public HTML for longer than Laravel's headers allow.
4. ~~The 32 `hidden = 2` items~~: **resolved.** The owner approved that they remain hidden (2026-11-15). No data
   change.
5. Assign `anasheed.editanasheed` as today; no new permission, seeder or migration.

## 9. Owner review ("B4 Owner Review", 2026-11-15)

| Item | Result |
|---|---|
| 1. Hidden-field semantics | **Established from code behaviour** (§1): `0` public; `2`, and equally `1`, hidden. No different state exists in any code; only the provenance of the historical `2`s is unrecoverable. Existing records were not modified or normalized |
| 2. Direct URL behaviour | as approved: 404 for visitors, manageable in AdminCP, A6's 200 superseded. **Recorded in decision-log #141** (updated with the review). Unrelated status codes unchanged |
| 3. Administrator preview authorization | **restricted** to `anasheed.editanasheed` holders and super-admins (§2.1); regression tests added and mutation-checked |
| 4. Legacy route exposure | matrix in §4; every legacy row's production reachability is UNKNOWN. **This also corrects §3 of the first report:** OLD's `Play`, `getit`, `getmirror` and `Group` do **not** filter hidden items, and the satellite pages **do** |
| 5. Stored counts | §5.1. Initially nothing was changed; the card count was then fixed under the final adjustment (§5.2) |
| 6. Tests | §7 |

**B4 Final Adjustment (2026-11-15):**
- **32 hidden items:** approved to remain hidden (§1.2, decision-log #141).
- **Group card counts:** fixed to show the publicly visible count, with one aggregate query, no schema or data
  change, and stored counters preserved (§5.2).
