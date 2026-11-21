# AdminCP B2 / F03-B: empty and hidden series on group pages — results

**Date:** 2026-11-16.
**Approval:** "AdminCP Feedback B2 / F03-B — APPROVED FOR LOCAL IMPLEMENTATION".
**Basis:** `docs/reports/admincp-b2-f03-resolution-proposal.md` §6.4.
**Decision log:** #146.
**Status:** **F03-B CLOSED LOCALLY (2026-11-16) after validation passed; not deployed.**
- **F03-A:** the repair command is closed locally; **the data repair has NOT been run**.
- **B2 is not closed:** F07 remains.

Local code only. No database write, migration, production access, request to any host, Gen 2 / OLD change, commit,
push or deployment. Every database statement run against the local copy was `SELECT` / `EXPLAIN`.

---

## 1. Files changed

| File | Change |
|---|---|
| `app/Domain/Content/Services/ContentListingService.php` | new `adminSeriesByAuthorAndGroup()` (admin-only listing + one aggregate); public methods untouched |
| `app/Domain/Content/Http/Controllers/KhotabGroupController.php` | `AdminViewer` injected; `ADMIN_SERIES_PERMISSION = 'khotab.editseries'`; admin branch + `markRendered()`; `$adminSeries` to the view |
| `resources/views/khotab/group.blade.php` | passes `:mark-hidden` / `:mark-empty` (= `$adminSeries`) to the series grid |
| `resources/views/components/content/media-collection-grid.blade.php` | optional `markEmpty` prop: «فارغة» label when a row's `is_empty` is true (default off; no other caller passes it) |
| `tests/Feature/Content/KhotabGroupAdminSeriesF03bTest.php` | **new**, 18 tests |
| docs | this report, decision-log #146, plan |

## 2. Admin visibility behaviour

On `khotab-group-{id}.htm`, for **a super-admin or an administrator holding `khotab.editseries`**:
- **Listed:** every series of the group's author, group and media type. That is the visitor list plus **hidden
  series** and series whose **stored `count` is ≤ 0**.
- **Order:** the same as visitors (`lastupdate DESC`).
- **Labels:**
  - **«++مخفي++»** (the existing OLD marker, `markHidden`) after the count of a hidden series;
  - **«فارغة»** (`<span class="label label-warning w2a-empty-series-marker">`) when the series is empty (§3).
- **The count shown stays the stored counter**, as on every page. It is not recomputed or repaired here, so an
  unrepaired F03-A series shows for example «-15 مادة» without «فارغة» (it has items).
- **Existing per-series controls** (`KhotabGroupControls::forSeries`, «إخراج السلسلة من المجموعة») appear on every
  listed row, hidden and empty ones included. They stay gated by their own route permission (`khotab.moveitem`).
- **Unchanged:**
  - a hidden group still 404s for everyone;
  - the group's own item list ("قائمة المواد") and its rules;
  - the series pages, and the RTL card layout. No redesign: one inline label.

**For everyone else** (visitors, and admins without `khotab.editseries`, whatever their other rights):
- exactly the previous query (`seriesByAuthorAndGroup()`: `count > 0`, `hidden = 0`, group visible), order, markup
  and headers;
- no `hidden` column is selected, so no marker can appear.

## 3. Definition of «فارغة»

> **No item that the series page would list for this administrator**: items whose `author`, `vedio`, `ser_id` and
> `group_id` equal the series' author, media type, id and group, **hidden items included**.

Why this definition, of the three candidates:

| Candidate | Problem |
|---|---|
| No associated items (`ser_id` only) | wrong for renumbering collisions: a series holding only another author's items opens onto an **empty** page, yet would not be marked |
| No visible own items | wrong for an admin: the series page shows an admin **hidden** items too, so a series whose items are all hidden is not empty to them |
| **Matches the series-page filter, hidden included (chosen)** | exactly what `KhotabSeriesController::show()` → `khotabItemsDefault(author, id, group, vedio, includeHidden: true)` lists for an admin |

- **Never inferred from the stored counter.** The 22 F03-A series have `count ≤ 0` but items, so they are **not**
  labelled. Copies such as 16082–16100 (count > 0, no items) **are** labelled «فارغة».
- A test opens each fixture series and checks that the label matches whether the admin's series page lists items.
- No counter is written or "repaired" (asserted).

## 4. Permission enforcement

- **Rule:** `AdminViewer::can('khotab.editseries')`, the existing Spatie check (`hasRole('super-admin') ||
  hasAnyPermission([...])`) on the **admin session guard**.
- **Not enough on their own:** another admin right (`addseries`, `additem`, `editgroup`, `moveitem`, `editauthor` were
  tested) and plain authentication.
- **No request parameter is read:** `?admin=1`, `?hidden=1&include_hidden=1`, `?permission=…` and `?page=2` were
  tested as a visitor and as a non-permitted admin, and return the visitor list.
- **Unchanged:** the hidden series' own page still 404s for visitors. Search, feeds, category pages, author pages and
  APIs are untouched.

## 5. Cache isolation

**Current architecture (inspected):**
- the group page has **no application cache**: `seriesByAuthorAndGroup()` and the controller do not use `Cache`;
  `ContentListingService` caches only the home lists;
- it has **no public `Cache-Control`**: Laravel's default `no-cache, private`;
- a global `web` middleware, `KeepContextualPagesPrivate`, sets **`Cache-Control: no-store, private`** on any
  response that called `ContextualAdmin::markRendered()`.

**Strategy:**
- The admin branch always calls `markRendered()`, so **every response containing the admin list is `no-store,
  private`**, independent of whether row controls were output.
- The list is built per request from the session; nothing admin-specific is stored in any cache key.
- Visitor responses keep exactly their previous header, so no caching is disabled globally.
- **Tested:** visitor → authorized admin → visitor. The admin response is `no-store, private`; the visitor response
  after it has the same header as before and the public list without markers.

## 6. Performance

- **Public path:** unchanged SQL, unchanged query count.
- **Admin path: two queries, independent of the number of series (asserted):**
  1. the series list: the public query without `count > 0` / `hidden = 0` (same table scan as today: the series table
     has no `author_id` index);
  2. **one** aggregate:
     `SELECT ser_id, COUNT(*) FROM nuke_islamic_khotab WHERE author = ? AND vedio = ? AND ser_id IN (…) AND group_id = ? GROUP BY ser_id`.
- **No N+1 and no per-series query.** The result set is bounded by the group (largest locally: 265 series).
- **Local measurement, largest group 1138 (`EXPLAIN` / profiling, read-only):**
  - the aggregate is a `range` on `idx_khotab_author_listing`: **≈ 6 ms**, 178 of 265 series with items, 3,006
    items;
  - the series list is a scan of about 14.9 k series rows: **≈ 9 ms**. This is the same plan as the public query.
- **Local scale:** group 1138 lists 253 series to visitors and 265 to an authorized admin; 87 of those 265 would be
  labelled «فارغة».

## 7. Tests

`tests/Feature/Content/KhotabGroupAdminSeriesF03bTest.php`: **18 passed (96 assertions)**, on the isolated in-memory
database (harness). The fixture group has 8 series:
- normal ×2;
- hidden;
- counted but item-less (copy);
- only hidden items;
- F03-A-style broken counter (−15, 4 items);
- only another author's item;
- new and empty;

plus series of another media type and another group, which are never listed.

| # | Requirement | Test |
|---|---|---|
| 1 | visitor unchanged | exact public ids and order; no markers; no `no-store` |
| 2 | unauthorized admin | no permission / five other Khotab rights → the visitor list |
| 3 | admin sees empty | `khotab.editseries` → all 8, including the empty ones |
| 4 | admin sees hidden | same |
| 5 | hidden label | «3 مادة ++مخفي++» |
| 6 | empty label | exactly 3 «فارغة»: copy, foreign-only, new |
| 7 | incorrect historical counters | «-15 مادة», listed, **not** «فارغة»; counters unchanged after the request |
| 8 | pagination | the series list has none (`?page=2` ignored); the group's item list is identical for visitor and admin; a hidden direct item stays hidden |
| 9 | ordering | `lastupdate DESC` for both lists |
| 10 | cache isolation | admin response `no-store, private`; the following visitor response is identical to the first |
| 11 | no hidden exposure | request parameters ignored; hidden series page 404 for visitors |
| 12 | existing controls | super-admin: «إخراج» on all 8 rows; `khotab.editseries` alone: the series but not that control |
| 13 | super-admin | the same admin list |
| 14 | series pages unchanged | visitor: visible items only; admin: hidden series and hidden items reachable; the «فارغة» definition checked against each series page |
| — | performance | 1 series-list query + 1 aggregate with 28 series in the group |

**Related existing tests:** every test file touching the group page, the grid component or the listing service, plus
`Admin/Contextual` and `Architecture`: **675 passed** (38 files).

**Full suite** (`timeout 1500 vendor/bin/pest --log-junit … < /dev/null`): **3,884 tests: 3,845 passed, 39 skipped, 0 failures, 0 errors** (32,819 assertions, ~382 s), exit 0, no hang (3,866 before + the 18 new tests).

**PHPStan:** passed, 0 errors. **Pint:** passed.

## 8. Remaining F03 risks

- **F03-A data repair not run.** Until it is, the 22 series stay invisible to visitors (in all three systems). An
  authorized admin now sees them on the group page with their wrong stored count (for example «-15 مادة», «0 مادة»)
  and **without** «فارغة», which correctly signals that they have items.
- **The renumbering damage remains** (proposal §1.5):
  - 8,187 foreign items in 565 series, which an admin may now see labelled «فارغة» when they hold no own items;
  - 1,349 orphaned items;
  - 19 counted-but-empty copies, labelled «فارغة» for admins, still listed for visitors.
- **Standalone series on the author page** keep their existing rule (any admin, `active()`, «++مخفي++» only, no
  «فارغة»). Aligning that page with F03-B (permission and label) would be a separate decision.
- **Legacy:** Gen 2 and OLD group pages are unchanged. OLD admins already saw all series; Gen 2 admins do not.
- **The reported series id** was never supplied. A series created after 2025-09-16 that "cannot be found" is now
  visible to an authorized admin on its group page.
