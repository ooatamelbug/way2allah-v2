# Wave 3 Implementation Report

**Status:** Complete except the production routing cutover sub-step (externally blocked, same as Waves 2's task 2.3).

---

## 1. Executive Summary

Implemented Roadmap Wave 3: the first two real Content-domain modules, `live-stream` and `channels`. Both were fully re-read from legacy source (not characterized from the audit alone) — this surfaced 5 new Implementation Findings and one real correction to the pre-implementation audit's own characterization (IF-009).

- **`live-stream`**: channel directory (`live-stream.htm`), watch-a-channel (`live-channel-{id}.htm`), and the orphaned hardcoded-channel-51 page (`live.php`, no `.htaccess` rule, ported to `/live-stream/featured` + a legacy-path redirect).
- **`channels`**: channel directory (`channels.htm`), browse-by-channel (`channel-{id}.htm`), browse-by-channel-and-author (`channel-{id}-{id2}.htm`). The 3 confirmed-orphaned files (`item.php`, `authors.php`, `channe___l.php`) and `old.php` were not ported, per ADR-0010.
- Extended `ContentListingService` with 3 new methods (`groupsByChannel`, `seriesByChannel`, `khotabItemsByChannel`) — a *fifth* confirmed independent implementation of the P-011 listing shape, exactly the consolidation Blueprint §4 already named `channels` as a consumer for. Not a new Architecture Evolution Proposal — the already-decided extraction being carried out.
- Extended `ContentSidebarWidget` with 3 new methods, including a genuinely different "most downloaded/newest" code path (`channels/channel.php`'s use of shared-core's `topitems()`) than `live-stream`'s own pair — confirmed via direct read to differ in LIMIT, an added video filter, and the "most recent" ordering column.
- Generalized the Wave 1 `TracksViews`/`RecordsView` component to support a configurable counter-column name (`viewCountColumn()`), after finding `live-stream`'s `ch_visits` is P-014's same pattern under a different column name than the `hits` every prior instance used. Classified as **Implementation Refactoring** — an extension of an already-built, already-flexible component, not a new shared-service decision.
- Also applied a self-critique from the Wave 1 code review: `RecordsView` now uses Eloquent's built-in `increment()` instead of a hand-rolled raw SQL expression.

**Blueprint sections now backed by real implementation:** §4 (`ContentListingService`/`ContentSidebarWidget` proven against a 4th and 5th/9th-and-10th real query shape respectively), §6 (`Channel`'s aggregate-boundary treatment — shared-reference-only, never owned — now has a real second/third consumer), §7 (first routes that keep their exact legacy path rather than needing a redirect, per §11's "transparently, no redirect" case).

## 2. Verification

- **Tests:** 109 passing suite-wide (27 added this Wave: 5 on `ContentListingService`, 3 on `ContentSidebarWidget`, 4 on `Channel`, 9 on `LiveStreamController`, 6 on `ChannelController`).
- **Assertions:** 5,217 suite-wide.
- **Static Analysis:** Still not configured (Wave 2's flagged gap, unchanged).
- **Stability check:** Full suite run twice consecutively post-Wave, both 109/109, 5,217 assertions — no flakiness.
- **Overall verification result: PASS.**

## 3. Files Changed

**Added:**
- `app/Domain/Content/Models/Satellite.php`
- `app/Domain/Content/Http/Controllers/{LiveStream,Channel}Controller.php` (2)
- `resources/views/live-stream/{index,show,featured}.blade.php` (3)
- `resources/views/channels/{index,show,author}.blade.php` (3)
- `routes/content.php`
- `tests/Feature/Content/{LiveStreamController,ChannelController}Test.php` (2)
- `docs/reports/wave-3-implementation-report.md` (this file)

**Modified:**
- `app/Domain/Content/Models/Channel.php` — `eligibleForLiveStream()` scope, `satellite()` relationship, `beamForDisplay()`, `TracksViews` trait usage + `viewCountColumn()`/`tracksLastVisit()` overrides
- `app/Domain/Content/Models/Concerns/TracksViews.php` — added `viewCountColumn()` hook
- `app/Domain/Content/Listeners/RecordsView.php` — column-name-agnostic, simplified to Eloquent's `increment()`
- `app/Domain/Content/Services/ContentListingService.php` — 3 new methods + docblock
- `app/Domain/Content/Services/ContentSidebarWidget.php` — 3 new methods + docblock
- `app/Domain/Content/Providers/ContentServiceProvider.php` — loads `routes/content.php`
- `tests/Feature/Content/{ChannelTest,ContentListingServiceTest,ContentSidebarWidgetTest}.php` — new test cases
- `tests/Support/Fixtures/MainSchema.php` — added `nukeSatSats()`
- `config/legacy-url-map.php` — `live-stream/live.php` redirect
- `docs/implementation-findings.md` — `IF-009` through `IF-013`

**Deleted:** none.

## 4. New Implementation Findings

- **IF-009**: `live-channel.php`/`live.php`'s direct-view queries don't check `streamcode` emptiness — narrower than the pre-implementation audit's own "4 places" characterization (actually 2). A correction to prior documentation, not just a fresh discovery.
- **IF-010**: `live.php` never increments `ch_visits` — the only channel view in the module that doesn't. Also the finding that motivated generalizing `RecordsView`'s counter-column name.
- **IF-011**: `channels/channels.php`'s panel title is driven by an undefined `$Anasheed` variable and renders blank in production.
- **IF-012**: `channels/author.php`'s "Most Downloaded"/"Newest" sidebar boxes are confirmed empty, unlike `channel.php`'s otherwise-similar page.
- **IF-013**: `channels/author.php`'s profile picture uses a third, non-bucketed media-path convention (`media/authors/sq/{id}.png`), distinct from both `MediaPathResolver`'s scheme and `IF-004`'s `thid`-based fallback.

**Superseded findings:** none directly superseded, but IF-009 materially corrects `live-stream.md` §5's own characterization — noted in IF-009's own entry rather than silently overwriting the audit doc.

## 5. Architectural Impact

**No architectural challenges discovered.** Every finding this Wave was about legacy behavior precision (query conditions, column names, dead variables, empty widget sections), not about the Blueprint's own decisions. The `TracksViews`/`RecordsView` generalization extends an already-designed extensibility point rather than revising any Blueprint-level decision — Blueprint §4/§5 never specified `hits` as the only possible counter-column name, so this isn't a reversal of anything decided.

One Roadmap-level (not Blueprint-level) imprecision worth naming: task 3.1 said "LiveStream model" — no such table/entity exists. Blueprint §5's own model list only ever named `Channel`; this wave's actual work (extend `Channel`, add `Satellite`) is consistent with the Blueprint as written, just not with the Roadmap task's colloquial phrasing. Not escalated as a challenge since nothing at the Blueprint level was contradicted.

## 6. Technical Debt

| Item | Classification | Notes |
|---|---|---|
| `channels/author.php`'s "Recommended For You" widget (`randomitems()`) not reproduced | Deferred, scoped | Needs a real content-item model to pick a random row from — Wave 4 territory. Documented in `ChannelController::showAuthor()`'s own docblock, not a silent gap. |
| `ORDER BY BINARY title ASC` is driver-aware (exact MySQL clause in production, a portable fallback for SQLite tests) | Accepted, documented | SQLite has no `BINARY` keyword in this position (confirmed — genuine syntax error, not just a behavior difference) — the fallback exists solely so the test suite can execute the query at all. Production (the only environment where this is observable) gets the exact legacy clause. Worth re-verifying against real MySQL once Infra Confirmation #1 lands. |
| Still no PHPStan/Larastan | Carried over from Wave 2 | Unchanged gap, restated rather than silently dropped from tracking. |
| Full site chrome still absent from `layouts.app` | Carried over from Wave 2 | Unchanged — every Wave 2/3 page still renders without real navigation/header/footer. |

## 7. Blockers

**Infrastructure blockers:** the production routing cutover for this Wave's URL patterns — same Infrastructure Confirmation #1 (real hosting/web-server config) blocking Wave 2's task 2.3.

**Business confirmations required:** none newly blocking. Four items logged as future (non-blocking) business questions: IF-004/IF-013 (unify or confirm the 3 divergent author-photo path conventions), IF-006 (should the two different group-count computations agree), IF-012 (should the author-filtered channel page also show download/newest recommendations). `live.php`'s own purpose (already tracked pre-Wave-3 in `live-stream.md` §8) remains open, not newly introduced.

**External dependencies:** none new.

## 8. Migration Progress

- **Completed Waves:** 0, 1. Wave 2 complete except its routing cutover. Wave 3 complete except its routing cutover.
- **Current Wave:** 3 (closing out) → 4 (`khotab`+`categories`, the main content body) next.
- **Remaining Waves:** 4, 5, 6.
- **Modules completed:** `live-stream` and `channels` are now **fully implemented** for their confirmed-live capabilities (first two modules in the whole migration to reach this state, vs. Wave 2's partial `pages`/`help`). `channels/item.php`, `authors.php`, `channe___l.php`, `old.php` are excluded outright (confirmed dead), not counted as gaps.
- **Modules remaining:** `khotab`, `categories`, `gallery`, `w2acd`, `anasheed`, `telawah`, `fatawa`, `surveys`, `chat_room`, all 9 `admincp` feature directories, `advanced-search`, plus the Wave-6-gated remainder of `pages`/`help`.
- **Blueprint completion:** ≈ **21 of 43 Roadmap tasks** (Wave 0: 8/8, Wave 1: 6/6, Wave 2: 3/4, Wave 3: 3/4) ≈ **49%** by the same task-count-proxy metric flagged in the Wave 2 report as rough, not effort-weighted. Wave 4 (9 tasks, the largest single module in the system) will not move this percentage proportionally to its real effort.
- **Total tests:** 109.
- **Total assertions:** 5,217.
- **Total ADRs:** 11 (unchanged — no new ADRs this Wave, consistent with §5's "no architectural challenges").
- **Total Implementation Findings:** 13 (`IF-001` through `IF-013`).
- **Outstanding Business Confirmations:** 13 from Blueprint Part IV (unresolved) + 2 candidate additions from Wave 1/2 not yet formally added (`IF-004`/`IF-006`) + 2 new candidates from this Wave (`IF-012`, and `IF-013` reinforcing `IF-004` rather than adding a distinct new one) + 1 minor non-blocking item from Wave 2 (`IF-008`).
