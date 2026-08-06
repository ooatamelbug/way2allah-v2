# Wave 4 Implementation Report

**Status:** Complete for `khotab`, `categories` (detail page only), `w2acd`, `gallery`, `anasheed`, `telawah`. Two sub-tasks explicitly deferred with reasoning (categories' tree/series pages; w2acd's P-015 comma-column data migration) — not silently dropped. Production routing cutover remains externally blocked, same as every prior Wave.

---

## 1. Executive Summary

Implemented the largest single Wave in the migration: the full `khotab` module (the site's largest feature), category browsing, and four smaller structurally-similar content modules (`w2acd`, `gallery`, `anasheed`, `telawah`). This Wave generated more new Implementation Findings (15, IF-014 through IF-028) than all three prior Waves combined (13), reflecting both `khotab`'s size and a deliberate mid-Wave checkpoint (approved by the user) that validated the findings before continuing.

- **`khotab`**: full item detail (mirrors, comments, downloads, PDF), author directory + per-author browsing (video/audio/pdf), series, groups, day-browsing, PDF "dump" listing, newest listings, and advanced search — 8 controllers, 9 models (`Author`, `KhotabGroup`, `Series`, `KhotabItem`, `Mirror`, `Comment`, `KhotabAdvanced`, `MirrorAdvanced`, plus `Category`), 13 views, ~30 tests.
- **`categories`**: single-category detail page (`category-{id}.htm`) — series/items scoped via the existing `khotab_category_index`/`series_category_index` junction tables, breadcrumb trail via `Category::breadcrumbTrail()`. The category tree/index page (`categories.htm` → `tree.php`) and `category-series-{id}.htm` are explicitly deferred, not built.
- **`w2acd`**: CD listing + item detail. A pre-audited bug (`$id=0` assignment-in-argument typo, `w2acd.md` §5) was fixed rather than silently ported — group filtering now actually works.
- **`gallery`**: album listing + album detail + image download. A pre-audited dead sort clause and a hardcoded absolute legacy-server path were both fixed rather than ported.
- **`anasheed`**: item detail (mirrors, comments, downloads) + group browsing, including a confirmed hardcoded special case (group 98 aggregates group 16's items too) reproduced exactly.
- **`telawah`**: reciter directory + group browsing + item detail/download. Confirmed and preserved a genuine legacy gap: `hits` is displayed but never incremented anywhere in this module (unlike every other content module) — not "fixed," since the audit already flagged this as intentional-or-unnoticed, not a typo.
- **A significant cross-cutting discovery (IF-026)**: 130 of the site's ~217 `.htaccess` pretty-URL rules route to `new_modules.php`, a file absent from this snapshot — spanning `w2acd`, RSS feeds, `Mobile`, `Fatwa`, `Surveys`, `Satellite`, and more. Flagged as the highest-priority open Business Confirmation in the findings log, not assumed to be a production bug (this snapshot's absence isn't proof of production's).
- **One Blueprint gap resolved** (mid-Wave, user-approved): `KhotabGroup` added as a plain referenced model, applying Blueprint §6's existing rule to a real entity (`nuke_islamic_groups`) the frozen ER diagram never enumerated. Treated as documentation completion, not an Architecture Evolution Proposal, per explicit user direction.
- **One real bug in this Wave's own code**, caught by its own test: Laravel binds controller-method scalar parameters positionally (not by name) — `KhotabAuthorController::show(int $author, string $op, ...)` silently received the wrong values until reordered to match the route's `{op}-{author}` capture order. Documented in that controller as a reusable lesson for the remaining multi-segment routes still ahead (`fatawa`, `chat_room`, `admincp`), per the user's guidance to record framework pitfalls only when likely to recur, not as a blanket practice.
- **Two decisions deliberately kept out of `decision-log.md`**, per the user's explicit conservative-logging guidance this Wave: keeping `KhotabAdvanced`/`MirrorAdvanced` as separate models (a one-model-local choice, documented in that model's own docblock only) and the download-counting-vs-view-counting distinction (`Mirror`/`AnasheedMirror`'s `incrementDownloadCount()`, also docblock-only, since it doesn't yet influence more than one module's design).

**Blueprint sections now backed by real implementation:** §4 (`ContentListingService`/`ContentSidebarWidget` proven against `khotab`'s full 9-shape query set plus category, `w2acd`, `anasheed`, `telawah` variants), §6 (`KhotabItem`/`Mirror` aggregate boundary; `Author`/`Category`/`Channel`/`KhotabGroup` as plain referenced models — the largest real test of this rule so far), the `CommentPosted`-shaped comment-moderation gate (confirmed real via `view` column analysis, not yet wired to an actual event since no listener consumer exists yet).

## 2. Verification

- **Tests:** 175 passing suite-wide (66 added this Wave, across 15 new `tests/Feature/Content/*.php` files).
- **Assertions:** 5,380 suite-wide.
- **Static Analysis:** PHPStan/Larastan level 5, 0 errors, 0 suppressions — clean after every change this Wave (re-run after each module, not only at the end).
- **Stability check:** Full suite run at least twice consecutively after every module (`khotab`, `categories`, `w2acd`, `gallery`, `anasheed`, `telawah`) — no flakiness observed at any point.
- **Overall verification result: PASS.**

## 3. Files Changed

**Added (models, 20):** `Author`, `KhotabGroup`, `Series`, `KhotabItem`, `Mirror`, `Comment`, `KhotabAdvanced`, `MirrorAdvanced`, `Category`, `W2acdGroup`, `W2acdItem`, `Album`, `AlbumImage`, `AnasheedGroup`, `AnasheedItem`, `AnasheedAdvanced`, `AnasheedMirror`, `AnasheedComment`, `TelawahGroup`, `TelawahItem`.

**Added (controllers, 16):** `KhotabItemController`, `KhotabAuthorController`, `KhotabSeriesController`, `KhotabGroupController`, `KhotabDayController`, `KhotabDumpController`, `KhotabNewsController`, `KhotabSearchController`, `CategoryController`, `W2acdController`, `GalleryController`, `AnasheedItemController`, `AnasheedGroupController`, `TelawahAuthorController`, `TelawahGroupController`, `TelawahItemController`.

**Added (views):** `resources/views/{khotab,categories,w2acd,gallery,anasheed,telawah}/*.blade.php` (~20 files).

**Added (tests, 15 files):** `tests/Feature/Content/{KhotabItemController,KhotabBrowsingControllers,KhotabSearchController,CategoryController,W2acdController,GalleryController,AnasheedItemController,AnasheedGroupController,TelawahControllers}Test.php` and others.

**Modified:**
- `app/Domain/Content/Services/ContentListingService.php` — 3 new methods (`khotabPdfItemsByAuthor`, `khotabPdfDump`, `khotabSeriesAdvancedSearch`/`khotabAdvancedSearch` + shared filter helper).
- `app/Domain/Content/Services/ContentSidebarWidget.php` — 9 new methods (author/video-flag/pdf/category-scoped khotab variants, `khotabRandomFeatured()`).
- `routes/content.php` — every route for this Wave's 6 modules.
- `config/legacy-url-map.php` — `khotab/dump.php`, `khotab/search.php` (raw-path-only pages, IF-026-adjacent).
- `tests/Support/Fixtures/MainSchema.php` — extended/added table definitions for every new table this Wave touched.
- `docs/implementation-findings.md` — IF-014 through IF-028 (15 new findings), index table, escalation list.
- `docs/decision-log.md` — decision #6 (`KhotabGroup`).
- `docs/reviews/wave-4-khotab-checkpoint-review.md` — the mid-Wave user-requested review (new).

**Deleted:** none.

## 4. New Implementation Findings

**Confirmed bugs, fixed (khotab, 11):** IF-014 (item.php sidebar `->video` typo, evidence upgraded to Fact via `topitems()`'s own shim), IF-015 (series.php's array-vs-object sidebar bug), IF-016 (day.php's undefined-`$Author` title), IF-017/IF-021 (news.php/author.php's unset-`$ob->video` PDF-page sidebar, same root cause as IF-014 in 2 more files), IF-018 (search.php's broken author link — fixed, tested), IF-019 (item.php's `flags/` vs `images/flags/` path), IF-020 (item.php's PDF button had no route at all), IF-022 (day.php's dated URLs never actually read their date parameters), IF-023 (search.php's own undefined-`$Author` title), IF-024 (search.php's title-length validation blocked every non-title search).

**Pre-audited findings carried into implementation (not newly discovered, applied now that Wave 4 reaches them):** IF-025 (`w2acd/cds.php`'s `$id=0` group-filter bug, `w2acd.md` §5), IF-027 (`gallery`'s dead `@order` sort + hardcoded absolute path, `gallery.md` §5).

**New cross-module/cross-cutting finding:** IF-026 (130/217 `.htaccess` rules route to a nonexistent `new_modules.php` — see §5).

**New confirmed bug outside khotab:** IF-028 (`anasheed/functions.php`'s comment flags have the identical `flags/` typo as IF-019 — a third occurrence of the same copy-pasted bug).

**Confirmed-not-a-bug, explicitly preserved (documented so it isn't mistaken for a gap later):** `anasheed/item.php` never filters `hidden` (a genuine legacy behavioral gap vs. `khotab`, flagged by the original audit as needing product-owner input, not fixed unilaterally); `telawah` never increments `hits` anywhere in its own code (same treatment).

## 5. Architectural Impact

**One Blueprint documentation gap resolved, not a contradiction:** `KhotabGroup` (decision-log #6) — `nuke_islamic_groups` is a real, independently-queried, independently-linked entity the frozen Blueprint §6 ER diagram never enumerated, because the deep `khotab/group.php` read happened after the Blueprint was frozen. Applying §6's own already-existing "plain Eloquent model, referenced not owned" rule to this newly-confirmed entity is not a new design decision — this was explicitly confirmed by the user's mid-Wave review approval, and handled as documentation completion, not a new Architecture Evolution Proposal or ADR.

**IF-026 is the one finding from this Wave that could theoretically bear on architecture**, but not in a way requiring action now: if `new_modules.php` is confirmed absent from production too, it would mean the `LegacyUrlCompatibility` mechanism's assumed "217 confirmed live rules" input is significantly smaller than believed — a correction to `00-url-inventory.md`'s data, not to the Blueprint's URL-compatibility *mechanism* (which remains correctly designed regardless of how many rules actually need it). No Blueprint change made or proposed; flagged as the top Business Confirmation candidate instead.

**No Architecture Evolution Proposals this Wave.** Two recurring-pattern candidates were explicitly *not* extracted, per the user's own standing "wait for a second module's real evidence" discipline:
- Download-counting (`incrementDownloadCount()`, now independently present on `KhotabItem`, `Mirror`, `AnasheedItem`, `AnasheedMirror`, `TelawahItem`, `Album`'s view-counting) has now appeared in enough modules that it is close to a real extraction candidate — flagged explicitly as the most likely thing to propose at the start of Wave 5, not decided here.
- The `ContentGroup`-shaped hierarchy (`KhotabGroup`/`W2acdGroup`/`AnasheedGroup`/`TelawahGroup`, all near-identical `parent_id`-self-referencing shape) was noted in `AnasheedGroup`'s own docblock as a candidate, same "wait for more evidence" treatment — this Wave itself is what supplied the 4th data point, so this is genuinely ready to reassess next Wave, unlike download-counting which was already flagged pre-Wave-4.

## 6. Technical Debt

| Item | Classification | Notes |
|---|---|---|
| `khotab_send_friend()`/`anasheed_send_friend()` (email-a-friend) not ported | Deferred, scoped | An email side-feature independent of core detail/download/comment behavior, documented in each controller's own docblock, not silently dropped. |
| `download_var_group_getright()` (`.grx` GetRight-format bulk download) not ported | Deferred, scoped | A 2000s-era download-accelerator format; low confidence it's still meaningfully used. Documented, not silently dropped. |
| `gallery`'s bulk album-zip download not ported | Confirmed unreachable, not deferred | Its own trigger route is dead (IF-026), and the function itself doesn't create the zip it claims to serve — not a real feature to preserve. |
| `categories/tree.php` (category index/tree) and `category-series-{id}.htm` not built | Deferred, scoped | `CategoryController`'s own docblock names both explicitly; only the single-category detail page was in scope this Wave. |
| `telawah/more.php` (newest listing) not built | Deferred, scoped | Its only route (`recite-news.htm`) is IF-026-dead; the raw-path page itself was not prioritized this Wave given its confirmed unreachability. |
| w2acd's P-015 comma-column data migration (task 4.4) not attempted | Deferred, as planned | Explicitly flagged pre-Wave as needing a dry-run prototype first, not a model/controller task — `W2acdItem` reproduces the comma-delimited columns as-is in the meantime. |
| IF-026's production status unconfirmed | Open Business Confirmation | The single highest-priority open question from this Wave — see §5. |
| Full site chrome still absent from `layouts.app` | Carried over from Wave 2/3 | Unchanged. |

## 7. Blockers

**Infrastructure blockers:** the production routing cutover — same Infrastructure Confirmation #1 blocking every prior Wave's equivalent task.

**Business confirmations required (new this Wave):** IF-026 (does `new_modules.php` exist in production — affects ~60% of the site's pretty-URL surface); whether `anasheed`'s missing `hidden` filter and `telawah`'s never-incremented `hits` are known/accepted gaps or should be fixed; category 487's (categories module) and anasheed group 98's hardcoded special cases (neither explained in code, both reproduced exactly pending explanation).

**External dependencies:** none new.

## 8. Migration Progress

- **Completed Waves:** 0, 1, 2 (except routing cutover), 3 (except routing cutover). Wave 4 complete for its 6 in-scope modules, 2 sub-tasks explicitly deferred (see §6).
- **Current Wave:** 4 (closing out) → 5 next (`fatawa`, `channels`'s remaining gaps, `chat_room`, `vars`, per the original Wave 4 grouping's "Independent, larger" tier, or `surveys`/`live-stream` gaps — exact Wave 5 scope not yet finalized).
- **Modules completed this Wave:** `khotab` (full), `w2acd`, `gallery`, `anasheed`, `telawah` (each for their confirmed-live capabilities). `categories` partially complete (single-category detail only).
- **Modules remaining:** `fatawa`, `chat_room`, `vars`/`vars_categories`, `surveys`, all 9 `admincp` feature directories, `advanced-search`, `w2a_autocomplete`, `cds`, plus `categories`' tree/series pages and the deferred items in §6.
- **Blueprint completion:** ≈ **28 of 43 Roadmap tasks** (Wave 0: 8/8, Wave 1: 6/6, Wave 2: 3/4, Wave 3: 3/4, Wave 4: 8/9 — task 4.4 deferred) ≈ **65%** by the same rough task-count-proxy metric flagged as imprecise in every prior report (not effort-weighted; Wave 4's 8 completed tasks represent substantially more real work than 8 tasks from any earlier Wave).
- **Total tests:** 175.
- **Total assertions:** 5,380.
- **Total ADRs:** 11 (unchanged — no new ADRs this Wave; the one new architectural item, `KhotabGroup`, was explicitly handled as documentation completion, not an ADR, per user direction).
- **Total decision-log entries:** 6 (`KhotabGroup` added; two candidate one-model-local decisions this Wave were deliberately kept out per the user's conservative-logging guidance — see §1).
- **Total Implementation Findings:** 28 (`IF-001` through `IF-028`; 15 new this Wave).
- **Outstanding Business Confirmations:** 13 from Blueprint Part IV (unresolved, unchanged) + IF-026 (new, highest-priority) + `anasheed`/`telawah`'s two confirmed-gap items (new) + category-487/anasheed-group-98's unexplained special cases (new) + prior Waves' carried-over items (IF-004/IF-006/IF-012/IF-013, unchanged).
