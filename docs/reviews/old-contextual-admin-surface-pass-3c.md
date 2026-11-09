# Pass 3C — OLD Contextual / Inline Admin Surface Reconstruction

**Status:** ANALYSIS ONLY — no implementation, no deployment, no commit.
**Date:** 2026-10-12
**Predecessors:** Pass 1 (architecture/auth bridge), Pass 2A (Khotab inline), Business Confirmation #6 reconciliation, Scope Reset, Pass 3A (Advfiles), Pass 3B (inline dependency map).
**Successor gate:** OA-3 (Category assignment) remains NOT started until this pass is reviewed.

---

## 1. Purpose, scope and method

Pass 3B produced a dependency map built from one mechanism: Generation 2's `$inlineAdminUrl` hyperlink delegation (76 operation tokens / 78 real operations / 8 workflow units). Pass 3C asks a different and larger question:

> **From the administrator's point of view, what contextual controls exist on normal content pages, and what breaks if OLD administration disappears today?**

Three constraints from the brief shaped the method:

- `$inlineAdminUrl` is **not** the definition of this surface. It is one delivery mechanism in one generation.
- `AdminPanel()` alone is **not** the definition. It is one renderer among several.
- **Do not use one regex as the entire discovery method.**

Accordingly, discovery used four independent signals, intersected rather than unioned-blindly:

| # | Signal | What it finds | What it misses |
|---|--------|---------------|----------------|
| S1 | Admin authorization guards (`is_w2a_admin()`, `$admintest`, `GreenZone`) | Pages that *decide* to render differently for admins | Guards that gate data visibility, not controls |
| S2 | Operation-link emission (`op=…` in public module source) | The actual control targets | Links built by string concatenation across lines |
| S3 | Renderer functions (`AdminPanel()`, `adminItemControls()`, `adminGroupControls()`, `adminAuthorControls()`, `adminAnswerControls()`) | The grouping of controls into toolbars, and the item context each carries | Controls emitted inline without a renderer |
| S4 | Reachability (`.htaccess` rewrite references, file-level include graph) | Whether a discovered control can actually be reached today | Nothing — this is the filter, not a finder |

S1+S2+S3 answer *what exists*. S4 answers *what is live*. The distinction matters: a large part of the discovered surface turns out to be unreachable historical snapshot, and a smaller part turns out to be reachable but **broken**.

**Important scoping note.** An early sweep for `$admin_file` over all of Generation 1 polluted the result set with PHP-Nuke *core* administration operations (user/block/module management) that were never part of Way2Allah content administration. All figures below are computed over **verified public content modules only** — the pages an administrator reaches by browsing the site, not by opening a control panel.

---

## 2. Generation 1 — contextual contexts inventory

19 Generation-1 public contexts render admin-conditional output. Decomposed by signal:

| Context (`old-project/modules/…`) | S1 guards | S3 renderers | S2 distinct `op=` | Admin-relevant ops |
|---|---|---|---|---|
| `Khotab/index.php` | 21 | 2 | 45 | 34 |
| `modules/index.php` (dispatcher) | 20 | 2 | 27 | — (dispatch only) |
| `Fatwa/` | 12 | 1 | 32 | 26 |
| `Anasheed/` | 9 | 1 | 25 | 20 |
| `Telawah/` | 7 | 1 | 36 | 23 |
| `Mobile/` | 7 | — | 35 | 15 (Downloads family) |
| `Locations/` | 7 | 1 | 6 | 6 |
| `Satellite/` | 6 | — | 1 | 1 |
| `w2acd/` | 5 | — | 26 | 22 |
| `advfiles/` | 5 | — | 7 | 4 |
| `search/` | 4 | — | — | 0 (visibility only) |
| `Surveys/` | 3 | — | 16 | 8 |
| `Cat/` | 3 | — | 12 | 8 |
| `advanced_search/` | 3 | — | — | 0 (visibility only) |
| `BannersRequest/` | 2 | — | 5 | 2 |
| `Your_Account/` | 2 | — | — | 0 |
| `home_files/` | 2 | — | 1 | 1 |
| `new_album/` | 1 | — | 3 | 1 |
| `w2a_cd/` | 0 | — | 23 | 18 |

**Deduplicated across contexts: 127 distinct admin-relevant contextual operations.**

Two observations that follow directly from the table:

- **`search/`, `advanced_search/` and `Your_Account/` contribute zero controls.** Their guards change *what rows are visible* (hidden content), not *what an admin can do*. This is the same visibility-vs-control split found in Generation 2 (§3) and is a recurring source of over-counting.
- **`w2a_cd/` has 0 guards but 18 admin ops.** It renders controls unconditionally — reachable by URL without an admin check in the page itself. Authorization depends entirely on the target handler, and `GreenZone($MyPerm)` in `old-project/mainfile.php:1855` begins with a bare `return;`, so **all per-operation authorization in Generation 1 is dead code**. This was established in Pass 1 and is re-confirmed here as a property of the contextual surface, not just of the central admin.

---

## 3. Generation 2 — contextual contexts inventory

Generation 2 defines its own `is_w2a_admin()` at `7amlat/functions.php:18` and uses two distinct mechanisms.

**Mechanism M1 — `$inlineAdminUrl` delegation to Generation 1.** 120 references across 7 files:

| File | Refs | Renderer functions | Context carried |
|---|---|---|---|
| `khotab/functions.php` | 43 | `adminAuthorControls()`, `adminGroupControls()`, `adminItemControls()`, `adminSeriesControls()` | author id, group id, item id, series id |
| `fatawa/functions.php` | 30 | `adminAnswerControls()`, `adminAnswerMoreControls()`, `adminGroupControls()` | fatwa id, question id, category id |
| `anasheed/functions.php` | 20 | `adminGroupControls()`, `adminItemControls()` | group id, item id |
| `telawah/functions.php` | 16 | `adminControls()`, `adminGroupControls()`, `adminItemControls()` | group id, item id |
| `functions.php` | 5 | inline | page-level |
| `surveys/functions.php` | 4 | inline | poll id |
| `gallery/functions.php` | 2 | inline | album id |

Yields **76 distinct operation tokens** — Pass 3B's map. Confirmed unchanged.

**Mechanism M2 — local `$admin_file` controls.** Two live call sites emit a control targeting Generation 2 itself rather than Generation 1:

- `advanced-search/index.php:1170`
- `khotab/search.php:357`

both emitting `'<a href="' . $admin_file . '.php?op=KhotabUpSeries&id=…&group_id=…">'`.

**`$admin_file` is not defined anywhere in `7amlat/w2a_config.php`, and Generation 2 has no `admin.php` at its document root.** These links therefore render as `.php?op=KhotabUpSeries&…` and resolve to nothing. **`KhotabUpSeries` is a reachable-but-broken contextual control in production today.** It is the only operation in this category.

**Mechanism M3 — visibility-only guards.** `advanced-search/index.php:935,1402,1455` and `khotab/search.php:288,471` drop the `hidden='0'` predicate for admins. These emit no controls. Laravel has a documented deliberate non-reproduction of admin-visible-hidden-content in search; that decision is unaffected by this pass.

**Generation 2's `w2acd/` module contains zero admin conditionals** — `grep -cE 'is_w2a_admin|inlineAdminUrl|admin_file'` returns `0` for all three of `w2acd/item.php`, `w2acd/functions.php`, `w2acd/cds.php`, and there are **0** `op=W2ACD*` references anywhere in Generation 2. See §9.

---

## 4. Set arithmetic — Pass 3B's map against the true contextual surface

| Set | Count |
|---|---|
| Generation-1 contextual admin operations (§2) | **127** |
| Generation-2 `$inlineAdminUrl` map (Pass 3B) | **76** |
| In both — Generation 2 re-exposed the OLD control | **52** |
| Generation-1 only — **no Generation-2 equivalent exists** | **75** |
| Generation-2 only — no Generation-1 public-module origin | **24** |
| **Union — true contextual admin surface** | **151** |

**Pass 3B's map covers 52 of 127 Generation-1 contextual operations — 41%.** This is the central finding of Pass 3C.

The 24 Generation-2-only operations are not inventions. They are group/series-structure operations (`KhotabAddGroup`, `KhotabEditGroup`, `KhotabDeleteGroup`, `KhotabEditSeries`, `KhotabDeleteSeries`, `KhotabInsertSeries`, `KhotabInsertKhotab`, `KhotabReArrange`, `KhotabEditAuthor`, `KhotabDeleteAuthor`, `cat_fastcopy`, `AnasheedAddGroup`, `AnasheedEditGroup`, `AnasheedDeleteGroup`, `AnasheedGroupIcon`, `AnasheedInsertAnasheed`, `AnasheedEditImage`, `AnasheedDeleteImage`, `AnasheedWaitingFB`, `Order_anasheed_in_group`, `TelawahEditGroup`, `TelawahDeleteGroup`, `TelawahMoveGroup`, `fatwa_many_questions`) that in Generation 1 were reached from central admin screens, and that Generation 2 **re-sited into page context** via its `adminAuthorControls()` / `adminGroupControls()` renderers. Generation 2 did not merely delegate; it moved controls *into* context that previously were not.

That is a behavioral difference worth recording: **the two generations disagree about which operations are contextual.** Any migration target set must be the union, not either generation's view.

---

## 5. Master matrix — admin browsing normal content → contextual control → business capability → current owner

Grouped by capability rather than by operation, because the same capability recurs across content domains with different operation names.

| Admin is browsing… | Contextual control offered | Business capability | Reached via | Current owner |
|---|---|---|---|---|
| Khotab / Telawah / Anasheed / W2ACD item page | edit item, delete item, item details | **Item CRUD** | G2 `adminItemControls()` → OLD; G1 direct | **OLD (gen 1)** — `MIGRATION_GAP` in Laravel for Khotab/Telawah; `EXTERNALLY_OWNED` per BC#6 for the content domains the other system administers |
| any media item page | add / edit / delete / move a download mirror | **Mirror & link management** | G1 only for 10 of 12 ops | **OLD (gen 1)** — no Laravel owner; Laravel has `AnasheedMirror` model (read) only |
| item inside a group or series | move up, reorder, set weight | **Ordering / weight** | G2 `KhotabReArrange`, `Order_anasheed_in_group`; G1 `*Order`, `KhotabUpKhotab`, `KhotabUpSeries` | **OLD (gen 1)**; `KhotabUpSeries` **broken** in G2 (§3 M2) |
| author / group / series page | create, edit, delete the container; set icon | **Content structure (author/group/series)** | G2 re-sited into context | **OLD (gen 1)** — `MIGRATION_GAP` |
| item page | attach, replace or delete an advertised file | **Advfiles (OA-1)** | G1 `AdvfilesEditAdvFile`, `AdvfilesDeleteAdvFile`, `*RequestAdvFile` | **OLD (gen 1)** — Pass 3A analysed, parked |
| item page | request / reserve / delete a banner | **Banner lifecycle** | G1 `*RequestBanner`, `W2ACD*_banner`, `BannersRequest/DelRequest` | **OLD (gen 1)**; public request half is user-facing, admin half is OLD-only |
| item or category page | add to / remove from category | **Category assignment (OA-3)** | G2 `cat_add_to_*`; G1 `cat_del_from_*`, `KhotabEditCat`, `w2a_cat_*` | **split** — *add* delegated via G2, *remove/edit* OLD-only |
| category / channel page | list moderators, view moderator action log | **Moderator oversight & audit** | G1 only — `*ListModertors`, `CatModertorsLogs`, `TelawahModertorsLogs` | **OLD (gen 1)** — **absent from Pass 3B entirely** (§10) |
| listing page | open the pending/waiting queue for this context | **Moderation queue** | G1 `*WaitingList`, `Waitinglist`; G2 `*WaitingFB` | **OLD (gen 1)** |
| author / channel page | repair counters for this container | **Counter repair** | G2 `KhotabCount`; G1 `AnasheedCount`, `TelawahCount`, `W2ACDCount` | **OLD (gen 1)** |
| item page | upload / replace / delete PDF or DOCX | **Document attachments** | G2 Khotab PDF+DOCX ops; G1 `AnasheedUploadPdf`, `W2ACDUploadPdf` | **OLD (gen 1)** |
| fatwa answer page | add / edit / delete / move question; bulk topic ops | **Fatwa Q&A administration** | G2 `adminAnswerControls()`, `adminAnswerMoreControls()` | **OLD (gen 1)** — Laravel has no fatwa admin controller |
| poll / survey page | edit poll, delete comment, manage categories | **Survey administration** | G2 `polledit`, `create`; G1 `polledit_select`, `*Category`, `RemovePollComment` | **partly Laravel** — `app/Domain/Admin/Http/Controllers/SurveyController.php` exists; category + comment ops not covered |
| CD / album page | the entire W2ACD admin toolbar (22 ops) | **CD distribution administration** | **G1 only** | **OLD (gen 1)** — G2 serves W2ACD content with **no** admin controls (§9) |
| any listing | broken-link audit | **Link integrity** | G1 `KhotabLostedLink` | **RECLASSIFIED by Pass 3D** — `KhotabLostedLink` is reporting-only (its repair form posts `op=KhotabRepairLostedLink`, which has no handler anywhere, with an undefined `$id`). Laravel owns the `down`/`trial` lifecycle via `BackupApiController` and all link measurement; it lacks only the `down = 0` worklist report. `BEHAVIOR_MIGRATED / REPORTING_GAP`. See pass-3d §2 |
| location page | location-scoped item/mirror/advfile controls | ~~Location administration~~ **DISSOLVED by Pass 3D** | G1 `Locations/` (6 ops) | **n/a** — none of the 6 is a Locations capability; they de-duplicate into the mirror, Advfiles and category families. Laravel owns Location entity CRUD; OLD had no item↔location assignment op at all. See `old-contextual-admin-ownership-reconciliation-pass-3d.md` §3 |
| mobile downloads section | 15 Downloads-family moderation ops | **Downloads moderation** | G1 `Mobile/` only | **OLD (gen 1)** — never examined in any prior pass |

---

## 6. Capability families — the 75 Generation-1-only operations

Grouping the 75 operations that have no Generation-2 equivalent:

| Family | Count | Operations |
|---|---|---|
| **W2ACD domain** (see §9) | 22 | `W2ACDAdd_banner`, `W2ACDCount`, `W2ACDDelete_banner`, `W2ACDDeleteMirror`, `W2ACDDeleteW2ACD`, `W2ACDDetailes`, `W2ACDEdit_banner`, `W2ACDEditMirror`, `W2ACDEditW2ACD`, `W2ACDInsertMirror`, `W2ACDListModertors`, `W2ACDMain`, `W2ACDOrder`, `W2ACDRequestAdvFile`, `W2ACDRequestBanner`, `W2ACDUploadPdf`, `W2ACDWaitingList`, `del_cd_img`, `AnasheedCount`, `AnasheedListModertors`, `AnasheedUploadPdf`, `AnasheedRequestAdvFile` |
| **Central-admin landing / listing** (navigation, not controls) | 15 | `adminMain`, `AdvfilesMain`, `AnasheedMain`, `KhotabMain`, `TelawahMain`, `KhotabCatList`, `w2a_cat_main`, `w2a_fatwa_main`, `w2a_fatwa_list_all`, `w2a_fatwa_list_all_questions`, `w2a_fatwa_list_all_general_questions`, `w2a_home_files_main`, `w2a_sat_main`, `W2ACDMain` (counted above), `Waitinglist` |
| **Mirror & link management** | 7 | `AnasheedDeleteMirror`, `AnasheedEditMirror`, `KhotabDeleteMirror`, `KhotabEditMirror`, `KhotabMoveMirror`, `TelawahDeleteMirror`, `TelawahEditMirror` |
| **Moderator oversight & audit** (§10) | 5 | `AnasheedListModertors`, `CatListModertors`, `CatModertorsLogs`, `TelawahListModertors`, `TelawahModertorsLogs` |
| **Category assignment (OA-3) — removal half** | 6 | `cat_del_from_anasheed`, `cat_del_from_item`, `KhotabEditCat`, `w2a_cat_add`, `w2a_cat_del`, `w2a_cat_edit` |
| **Ordering / weight** | 5 | `AnasheedOrder`, `KhotabOrder`, `KhotabUpKhotab`, `KhotabUpSeries`, `TelawahOrder` |
| **Survey administration** | 6 | `AddCategory`, `DelCategory`, `EditCategory`, `polledit_select`, `remove`, `RemovePollComment` |
| **Advfiles (OA-1)** | 2 | `AdvfilesDeleteAdvFile`, `AdvfilesEditAdvFile` |
| **Moderation queue** | 2 | `AnasheedWaitingList`, `KhotabWaitingList` |
| **Generic item CRUD (Telawah)** | 2 | `ItemDeleteItem`, `ItemEditItem` |
| **Misc** | 3 | `KhotabInsertAuthor`, `KhotabLostedLink`, `DelRequest` |

Note that 15 of the 75 are **landing/listing navigation**, not contextual controls. The genuine contextual-control gap is therefore **~60 operations**, not 75. Stating this explicitly prevents the next pass from inheriting an inflated number — the same error Pass 3A made in the other direction (§13).

---

## 7. Reachability — what is live, what is dead, what is broken

| Classification | Evidence | Operations affected |
|---|---|---|
| **Live** | file referenced by `.htaccess` rewrite, or reachable through `modules/index.php` dispatch | the Generation-1 contextual surface, and the 76 Generation-2 delegated ops |
| **Reachable but BROKEN** | `advanced-search/index.php:1170`, `khotab/search.php:357` emit `$admin_file` which is undefined in Generation 2 | `KhotabUpSeries` (1 op) |
| **Unreachable historical snapshot** | `khotab/old.php`, `channels/old.php`, `vars/old.php` — **0** `.htaccess` references each | 32 ops, preserving the complete pre-rewrite OLD inline toolbar |
| **Live (contrast)** | `khotab/group.php`, `khotab/series.php` — 1 `.htaccess` reference each | current group/series toolbars |

The three `old.php` files are the single best surviving specification of the pre-rewrite contextual toolbar. They are **evidence, not production dependency** — nothing can reach them. They must not be counted as live risk, and they must not be discarded as noise either: 12 of the 13 operations that Pass 3B's map missed and that are not reachable today appear in these snapshots, and 10 of those 12 are independently confirmed live in Generation 1's own public modules (e.g. `KhotabEditMirror` / `KhotabDeleteMirror` at `Khotab/index.php:1216`; `AdvfilesEditAdvFile` / `AdvfilesDeleteAdvFile` at `:1037`, per Pass 2A).

---

## 8. Authorization reality across the surface

Re-confirmed, because it changes the risk profile of every row in §5:

- Generation 1 gates *rendering* with `is_w2a_admin()` (`old-project/mainfile.php:377`), which reads `$_COOKIE['w2a_user']`.
- Generation 1 gates *execution* with `GreenZone($MyPerm)` (`old-project/mainfile.php:1855`), whose body **begins with a bare `return;`**. Every per-operation permission check is therefore inert.
- `w2a_cd/` renders 18 admin operations with **no page-level guard at all** (§2).

Consequence: the contextual surface's effective authorization model is **"holds a valid `w2a_user` bridge cookie" → may invoke any of the 151 operations.** There is no per-capability authorization anywhere in the OLD contextual surface. Any migration of these capabilities into Laravel will be the first time they acquire real per-operation authorization — which is an improvement, but also means **Laravel cannot replicate OLD behavior faithfully and be correct at the same time.** Permission granularity must be designed, not ported. Laravel already has `app/Domain/Admin/Http/Controllers/PermissionController.php` and `AdminStaffController.php` to build on.

---

## 9. Finding — W2ACD is a fifth content domain with an OLD-exclusive admin surface

Pass 3B's map spans four content domains: Khotab, Anasheed, Fatawa, Telawah. W2ACD (CD / album distribution) is a fifth.

- Generation 1 `modules/w2acd/` + `modules/w2a_cd/`: **22 + 18 admin operations**, a full toolbar (item CRUD, mirrors, banners, ordering, counters, waiting list, moderators, PDF).
- Generation 2 `7amlat/w2acd/`: serves the content publicly — `item.php`, `cds.php`, `functions.php` — with **zero** admin conditionals and **zero** `op=W2ACD*` references.
- Laravel: `app/Domain/Content/Models/W2acdGroup.php` exists and `ContentSidebarWidget` / `ThumbnailService` reference W2ACD, so **content is read**. There is no W2ACD admin controller.

So all three generations serve W2ACD content, and **only Generation 1 can administer it.** Generation 2 never delegated these controls because it never rendered them — which is precisely why a mechanism-based discovery (Pass 3B) could not see this domain at all.

This is the clearest answer to "why was one mechanism insufficient."

---

## 10. Finding — moderator oversight is a capability family absent from every prior pass

Five Generation-1 contextual operations implement a capability no prior pass recorded:

- `CatListModertors`, `CatModertorsLogs` (`modules/Cat/`)
- `TelawahListModertors`, `TelawahModertorsLogs` (`modules/Telawah/`)
- `AnasheedListModertors`, `W2ACDListModertors`

Browsing a category or channel, an administrator can list who moderates it and read that moderator's action log. Supporting schema exists in Generation 1 (`moderation_*` / `last_moderation_ip` / `moderator` identifiers found across `modules` and `admin`).

Laravel's only `moderator`-related file is `app/Domain/Admin/Http/Controllers/SurveyController.php`. **Per-category moderator assignment and moderator audit logging have no owner outside Generation 1.** For an organization with delegated content moderation this is an accountability capability, not a convenience — it is the only place that answers "who changed this, and were they allowed to."

Recorded as a **new candidate workflow unit**, not folded into an existing one.

---

## 11. Current-owner analysis

Laravel's `app/Domain/Admin/Http/Controllers/` contains: `AdminAuthController`, `AdminStaffController`, `BroadcastingController`, `LinkQualityStatsController`, `LocationsController`, `PermissionController`, `QuestionnaireController`, `SoundcloudController`, `SurveyController`, `UploaderController`, `YoutubeController`.

Read structurally: **Laravel's admin domain owns identity, permissions, locations, surveys, questionnaires, broadcasting, uploads and third-party integrations. It owns no content-item administration for any of the five content domains.**

That is consistent with Business Confirmation #6 as corrected in the reconciliation pass: #6 said content administration is `EXTERNALLY_OWNED` / out-of-scope. It did **not** say any capability was retired, and `decision-log.md:521`'s `INTENTIONALLY_RETIRED_CAPABILITY` label remains unsupported. Pass 3C neither strengthens nor weakens #6; it establishes how much surface #6's scope boundary actually covers — and the answer is most of the 151 operations.

Two owner assignments need verification rather than assumption:

- **`KhotabLostedLink` vs `LinkQualityStatsController`** — Laravel may already own broken-link auditing. Reconcile before classifying as a gap.
- **`Locations/` 6 ops vs `LocationsController`** — likewise.

---

## 12. What breaks if OLD administration disappears today?

Answering the brief's central question, restricted to **live** surface (§7) and excluding landing/listing navigation (§6).

**Breaks immediately and completely — no alternative owner anywhere:**

1. **W2ACD administration** (§9) — ~22 operations. CD/album content becomes read-only across all three generations.
2. **Mirror & link management** — 7+ operations across Khotab, Anasheed, Telawah. Download mirrors cannot be added, corrected, moved or removed. This is the capability most likely to cause *visible public breakage over time*, because a dead mirror is a broken download on a live page with no way to fix it.
3. **Moderator oversight & audit** (§10) — 5 operations. Delegated moderation loses both its assignment mechanism and its accountability trail.
4. **Downloads moderation** (`Mobile/`, ~15 operations) — never examined by any prior pass; no Generation-2 or Laravel equivalent found.
5. **Category assignment — removal half** — `cat_del_from_*`, `w2a_cat_del`, `KhotabEditCat`. *Adding* to a category is delegated via Generation 2 (and so breaks too, since the target is OLD); *removing* exists only in OLD. A mis-filed item becomes permanent. This is OA-3.
6. **Advfiles** (OA-1) — Pass 3A's subject.
7. **Ordering / weight** — listings freeze in their current order; editorial curation stops.
8. **Moderation queues** — pending submissions can no longer be approved or rejected.
9. **Counter repair** — drift becomes permanent (low business impact; listed for completeness).
10. **Fatwa Q&A administration** — all of `adminAnswerControls()` / `adminAnswerMoreControls()`; no Laravel fatwa admin exists.
11. **Document attachments** (PDF/DOCX) and **banner lifecycle (admin half)**.

**Breaks but is already broken:** `KhotabUpSeries` via Generation 2 (§3 M2). Zero additional impact.

**Does not break:** search/advanced-search admin visibility (Generation 2 has its own `is_w2a_admin()` and its own queries, independent of OLD); the three `old.php` snapshots (unreachable); Laravel-owned identity/permissions/locations/surveys/questionnaires/broadcasting/uploads/integrations.

**Does not break because Generation 2 is also affected, not protected:** every one of the 76 `$inlineAdminUrl` operations. Generation 2's admin pages would continue to *render* their toolbars — `is_w2a_admin()` is local — while every control in them 404s. Administrators would see a fully populated, entirely non-functional admin UI. That failure mode is worse than an absent UI, and it argues for Generation 2's toolbars being the **first** thing addressed in any decommissioning sequence, independently of capability migration.

---

## 13. Corrections to earlier passes

Per the brief, Pass 3A and 3B conclusions are rewritten only where a factual error is found.

| Pass | Statement | Status |
|---|---|---|
| 3A | Generation-2 → OLD surface is ~40 references / ~35 operations | **Already corrected in 3B §1** (regex `inlineAdminUrl[^"']*op=` terminated at the first quote). Actual: 101→120 references, 76 tokens, 78 operations. No further correction. |
| 3B | 76 tokens / 78 operations / 8 workflow units | **Factually correct for its stated scope** (`$inlineAdminUrl` delegation). **Not** the contextual admin surface — it covers 52 of 127 Generation-1 contextual operations (41%). Scope clarification, not an error; 3B's conclusions stand. |
| 3B | 8 workflow units | **Incomplete as a migration target set.** Add at minimum: W2ACD administration (§9), moderator oversight & audit (§10), Downloads moderation (§12 item 4). |
| 3B | Laravel coverage: MIGRATED 2, PARTIALLY_MIGRATED 3, MIGRATION_GAP 21, EXTERNALLY_OWNED 37, DEFERRED 13 | Correct over 76. Over the 151-operation union the uncovered proportion is substantially larger; not re-tallied here because owner verification is pending for two families (§11). |
| 3C interim | an over-broad `$admin_file` grep pulled PHP-Nuke core admin operations into the contextual set | Already discarded; §1 records the scoping rule that replaced it. |

---

## 14. Model — admin browsing normal content → contextual control → business capability → current owner

```
   ┌──────────────────────────────────────────────────────────────────────────┐
   │  ADMIN BROWSES A NORMAL CONTENT PAGE                                     │
   │  (an item, an author, a group, a series, a category, a channel, a CD)    │
   └───────────────────────────────┬──────────────────────────────────────────┘
                                   │  page asks: is_w2a_admin() ?
                                   │  (gen 1: mainfile.php:377 — cookie bridge)
                                   │  (gen 2: functions.php:18 — its own copy)
                                   ▼
   ┌──────────────────────────────────────────────────────────────────────────┐
   │  A CONTEXTUAL CONTROL IS RENDERED, CARRYING THIS PAGE'S ITEM CONTEXT     │
   │                                                                          │
   │   M1  gen2 adminItemControls()/adminGroupControls()/adminAuthorControls() │
   │       → $inlineAdminUrl → https://old.way2allah.com/…?op=…   [76 ops]    │
   │   M2  gen2 $admin_file . '.php?op=KhotabUpSeries'  → UNDEFINED → BROKEN  │
   │   M3  gen1 AdminPanel() / inline op= links on its own pages  [127 ops]   │
   │   M4  gen1 w2a_cd/: controls rendered with NO page guard at all [18 ops] │
   │                                                                          │
   │   Union = 151 distinct contextual operations                             │
   └───────────────────────────────┬──────────────────────────────────────────┘
                                   │  admin clicks
                                   ▼
   ┌──────────────────────────────────────────────────────────────────────────┐
   │  GENERATION 1 EXECUTES — authorization is effectively absent              │
   │  GreenZone($MyPerm) at mainfile.php:1855 begins `return;`                │
   │  ⇒ valid w2a_user cookie ⇒ may invoke ANY of the 151 operations          │
   └───────────────────────────────┬──────────────────────────────────────────┘
                                   ▼
   ┌──────────────────────────────────────────────────────────────────────────┐
   │  BUSINESS CAPABILITY (17 families, §5)                                   │
   │  item CRUD · mirrors · ordering · structure · advfiles · banners ·       │
   │  category assignment · moderator oversight · moderation queues ·         │
   │  counters · documents · fatwa Q&A · surveys · CD distribution ·          │
   │  link integrity · locations · downloads moderation                       │
   └───────────────────────────────┬──────────────────────────────────────────┘
                                   ▼
   ┌──────────────────────────────────────────────────────────────────────────┐
   │  CURRENT OWNER                                                           │
   │   Laravel  ── identity, permissions, locations, surveys, questionnaires, │
   │               broadcasting, uploads, integrations                        │
   │               (NO content-item administration, any domain)               │
   │   Gen 2    ── renders toolbars; owns NO operation. Pure hyperlink proxy. │
   │   Gen 1    ── owns ~all 151 contextual operations                        │
   │   External ── per BC#6, for the content domains the other system         │
   │               administers (scope boundary, NOT retirement)               │
   └──────────────────────────────────────────────────────────────────────────┘
```

**The one-sentence model:** Generation 2 shows the administrator a contextual toolbar it cannot execute, Generation 1 executes every one of those operations with no effective authorization, and Laravel owns the administrative periphery but none of the content-administration centre — so the contextual admin surface is a 151-operation capability set whose single point of both delivery and failure is `old.way2allah.com`.

---

## 15. Candidate next tasks — ranked by business risk, not operation count

> **Superseded by Pass 3D for ranks 1–2.** The rank-1 reconciliation was performed in `old-contextual-admin-ownership-reconciliation-pass-3d.md`. Outcome: the **Locations family is deleted** (0 genuine gaps; 6 ops de-duplicated), **link "repair" is withdrawn** as an OLD capability (no handler ever existed), and the **mirror family is confirmed OLD-exclusive and re-scoped upward** to ~10 ops across 4 content domains — Laravel can measure a link but cannot change one. Pass 3D's §10 ranking replaces the table below; its recommended first unit is **mirror/link CRUD with the moderator permission model as its authorization spec**. The three open questions below are re-answered in pass-3d §13 (all three ownership halves resolved from source; only the usage halves remain).


Per the brief: operation count is explicitly **not** the ranking criterion, and OA-3 is **not** resumed automatically.

| Rank | Candidate | Why this rank | Ops |
|---|---|---|---|
| 1 | **Reconcile owner for `LinkQualityStats` and `Locations`** (§11) | Pure analysis, no implementation, and it may *remove* two families from the gap set. Cheapest possible correction to the target set; must precede any ranking that claims to be final. | ~7 |
| 2 | **Mirror & link management** | Highest *public-visible* decay risk: a dead mirror is a broken download on a live page with no repair path. Spans 4 domains, so one design serves several. | 7–12 |
| 3 | **W2ACD administration** (§9) | An entire content domain with exactly one administering system and no fallback. Also the clearest proof that the target set was previously under-scoped. | ~22 |
| 4 | **Moderator oversight & audit** (§10) | Accountability, not convenience. Also a natural fit for Laravel's existing `PermissionController` / `AdminStaffController`, so it may be unusually cheap relative to its value. | 5 |
| 5 | **OA-3 Category assignment** | Real risk (mis-filing becomes permanent) and already scoped — but the *add* half is delegated and the *remove* half is OLD-only, so the workflow unit needs re-specification against §5 before implementation. | ~6 |
| 6 | **Downloads moderation (`Mobile/`)** | Genuine gap, but never examined by any pass — needs a discovery pass of its own before it can be ranked against the others honestly. | ~15 |
| — | **OA-1 Advfiles** | Remains parked. Pass 3A's analysis stands; its business risk is lower than ranks 2–5. | 2–4 |

**Recommended immediate next step:** rank 1 (owner reconciliation). It is analysis-only, it can invalidate part of the gap set, and every subsequent ranking depends on its outcome.

**Independent of capability migration:** §12's last paragraph identifies a decommissioning-sequence hazard — Generation 2's toolbars render from a local `is_w2a_admin()` and would survive OLD's removal as a fully-populated, entirely non-functional admin UI. Whoever plans the decommissioning should address Generation 2's rendering **before** OLD's availability, regardless of which capabilities have migrated by then. This is a sequencing constraint, not a migration task.

### Open questions for the owner

1. Does the external system from Business Confirmation #6 administer W2ACD / CD distribution? If not, §9 is an unowned domain, not an out-of-scope one — and that changes rank 3 materially.
2. Is delegated content moderation still operationally used? §10's value depends entirely on the answer.
3. Is the `Mobile/` Downloads section still live for administrators, or is it a legacy module retained only for public read?

---

## Appendix — reproduction

All figures above are reproducible read-only. Primary commands:

```bash
# Generation-2 delegation map (76 tokens)
grep -rhoE 'inlineAdminUrl[^;]*' --include=*.php /mnt/Projects/Php/pure/7amlat \
  | grep -oE 'op=[A-Za-z_0-9]+' | sed 's/op=//' | sort -u

# Generation-1 contextual surface (127 admin-relevant, after excluding public ops)
grep -rhoE 'op=[A-Za-z_0-9]+' /mnt/Projects/Php/pure/old-project/modules/{Khotab,Anasheed,Telawah,Fatwa,Locations,w2acd,advfiles,Cat,w2a_cd,Surveys,BannersRequest,home_files,Satellite,new_album} \
  | sed 's/op=//' | sort -u

# Per-context renderer → control mapping
awk '/^function /{fn=$2} /inlineAdminUrl/{if(match($0,/op=[A-Za-z_0-9]+/)) print fn" -> "substr($0,RSTART+3,RLENGTH-3)}' \
  /mnt/Projects/Php/pure/7amlat/khotab/functions.php | sort -u

# W2ACD admin absence in Generation 2 (all three return 0)
grep -cE 'is_w2a_admin|inlineAdminUrl|admin_file' /mnt/Projects/Php/pure/7amlat/w2acd/*.php

# Reachability of the historical snapshots (0 references each)
grep -c 'old\.php' /mnt/Projects/Php/pure/7amlat/.htaccess
```
