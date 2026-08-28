# Sitewide Internal 404 / Broken Route Audit

**Repair status update (2026-08-28):** **all 5 findings are now fully REPAIRED** — **#1** (`/recite-news.htm`), **#4** (`/fatawa-topics-{category}.htm` breadcrumb) (decision-log #52, Repair Batch 1); **#5** (`.grx` iconv 500) (decision-log #53 + #54, `BUSINESS_REPAIR_LOW_RISK` + owner-approved Quranic-codepoint extension — not legacy parity); **#2** (orphaned/hidden khotab series data) (decision-log #55 + #56, `MIGRATION_STRICTNESS_DEFECT` + owner-approved `ITEM_VISIBILITY_IS_INDEPENDENT` — not legacy parity; 1,349 nonexistent-series items + 834 hidden-series items, both branches); **#3** (w2acd shared-nav relative-href bug) (decision-log #57, `BUSINESS_REPAIR_LOW_RISK` — not legacy parity). **This sitewide audit is now fully closed** — see each finding below for exact evidence.

**Date:** 2026-08-28. **Target:** `http://127.0.0.1:8123` (local dev server only — no production access). **Scope:** discovery + classification only, per explicit instruction — no application fixes applied in this pass.

## Methodology

A bounded, read-only, GET-only crawler (`urllib`/`html.parser`, no external deps) started from 30 seed listing pages (home, khotab video/audio/pdf directories, categories, channels, fatawa directory pages, gallery, radio, telawah, var-group pages, w2acd, live-stream, surveys, static pages) and did a breadth-first crawl, extracting every `<a href>`, `<form action>` (method noted, never submitted for POST), and `<iframe src>`, normalizing to same-origin application paths and excluding static assets (css/js/images/fonts). Budget: **350 pages fetched**, discovering **36,008 link occurrences** resolving to **14,698 unique concrete URLs**, grouped into **131 unique route families** (numeric segments normalized to `{id}`). Redirects were followed manually (up to 6 hops) recording the full chain and final status; external-domain redirects were detected and stopped at the boundary, not followed off-site. No admincp routes were seeded or reached. 6 POST form actions were discovered and correctly never submitted.

Supplemented with a **targeted multi-ID sampling pass** (202 URLs) against real database rows (read-only queries against the local `olddb` mirror) for the families explicitly named in scope: authors, khotab items/groups/series, categories, fatwa general-questions/topics/questions, channels, telawah, anasheed, locations — to catch data-dependent branches the organic crawl might not have organically linked to.

Every non-200/non-redirect-healthy signal from both passes was individually traced to its generator, cross-checked against real database state, and — where relevant — against real legacy source, before classification. Several apparent findings were **disproven** during this trace and are recorded as such (see §Audit coverage limitations), not silently dropped.

## Summary counts

| Metric | Count |
|---|---|
| Pages crawled | 350 |
| Link occurrences extracted | 36,008 |
| Unique concrete URLs discovered | 14,698 |
| Unique route families | 131 |
| Families with ≥1 non-200 example | 33 (before tracing) → **7 genuine distinct defects** after tracing (rest were false positives, expected behavior, or external-redirect non-issues) |
| Multi-ID sample URLs tested | 202 (40 non-200, mostly resolved to expected/false-positive on trace) |

## Findings

### P0 — critical, homepage-linked

#### 1. `/recite-news.htm` — MISSING_MIGRATION_ROUTE (A) — **REPAIRED (decision-log #52, Repair Batch 1)**
- **Example:** `/recite-news.htm`
- **Source page(s):** `/` (homepage), 1 occurrence
- **Laravel generator:** `resources/views/home.blade.php:236`
- **Legacy generator:** `home_functions.php` (confirmed real, live generator)
- **Legacy route:** `.htaccess:332` — `RewriteRule ^recite-news.htm new_modules.php?name=Telawah&op=MoreTelawah [L]`
- **Legacy target:** `telawah/more.php` — a real, complete, fully-readable file (24 latest telawah items, real query, real markup) — never migrated to Laravel.
- **Confidence:** High.
- **Repair evidence:** `telawah/more.php`'s real behavior (24 latest telawah items, `LEFT JOIN` groups, portlet+table markup, `most_downloaded_list()` sidebar) is now implemented via a new `TelawahLatestController` (mirroring `FatwaLatestController`'s existing pattern exactly, as originally recommended). Reused, not duplicated: `ContentListingService::homeLatestTelawahs()` (already built for the homepage widget) extended with an optional `limit` already present and 2 extra selected columns; `ContentSidebarWidget::telawahMostDownloaded()` reused unchanged. Live-verified: `/recite-news.htm` → `200`, real portlets, real data. Full evidence in decision-log #52.

#### 2. `/khotab-item-267572.htm`, `/khotab-item-267573.htm` — **PARTIALLY REPAIRED (decision-log #55) — `MIGRATION_STRICTNESS_DEFECT`, nonexistent-series branch only, NOT legacy parity**
- **Example:** `/khotab-item-267572.htm`, `/khotab-item-267573.htm`
- **Source page(s):** `/` (homepage), `/dumped-lectures.htm` — 4 occurrences, 2 source pages
- **Laravel generator:** whatever homepage/dumped-lectures widget lists these 2 items (both real, non-hidden `nuke_islamic_khotab` rows)
- **Root cause, proven via direct DB query:** both items have `ser_id = 15223`, and `nuke_islamic_series` has **no row with id 15223** — a dangling/orphaned series reference. `KhotabItemController::show()` (`app/Domain/Content/Http/Controllers/KhotabItemController.php:51-58`) correctly treats a nonexistent referenced series as `abort_if($series === null, 404)`.
- **Confidence:** High on root cause. **Unverified:** whether legacy's own `khotab/item.php` would also 404 on an orphaned `ser_id`, or silently render without series context — not traced in this pass (scope discipline; this needs its own dedicated trace before any fix, not assumed).
- **Recommended next action:** Trace `khotab/item.php`'s real handling of a query returning no series row, before deciding whether Laravel's 404 is faithful or overly strict.
- **Follow-up investigation (2026-08-28, read-only):** full trace completed — see `docs/decision-log.md` for the dedicated entry. Key findings: (1) legacy's real `item.php:62-68` does **NOT** 404 on a missing/hidden series — it calls `w2a_header()` (full page chrome) then `return;`, producing a genuinely broken, un-footered, HTTP `200` page with an empty content area — not a 404, not a valid render either; Laravel's `abort_if($series===null, 404)` is a real divergence from legacy, but legacy's own behavior is not a standard worth reproducing. (2) The item's own data (title, author, description, media/download links, mirrors, comments, all sidebar widgets) never reads `$series` anywhere in either the legacy file or the Laravel `khotab.item` Blade view — the view already guards its one `$series` use (the optional breadcrumb crumb) with `@if ($series)`. (3) This is systemic, not a 2-item edge case: **1,349 visible items reference 91 distinct genuinely-missing series ids** (no recovery path found — no relevant table/log preserves the original series data), plus a related-but-distinct **834 visible items reference a series that exists but is hidden** (same code path, same 404, different DB root cause). (4) Author/group/channel foreign keys for all 1,349 items were checked and are clean — this is a series-only defect. (5) Structurally unreachable via the standard author/group/series browse hierarchy (which only lists items with `ser_id=0` at each level); reachable only via listings that don't filter on `ser_id` (`/dumped-lectures.htm` + homepage PDF-dump widget for the 41 items with `pdf>0`; "most recent"/"most downloaded" sidebar widgets, shown across many pages, for the rest, subject to their own recency/popularity ranking).
- **Repaired (decision-log #55) — nonexistent-series branch only, owner-approved:** `KhotabItemController::show()`'s series lookup now distinguishes "no row exists" (renders the item normally, `$series = null`, breadcrumb crumb simply omitted — no other content affected) from "row exists but hidden" (unchanged, still `404` — a separate, unresolved owner decision, since a hidden series may be intentional). Live-verified: `/khotab-item-267572.htm` and `/khotab-item-267573.htm` → `200`, correct title/content/breadcrumb (group crumb still present, series crumb correctly absent, download/PDF/media all intact); a 12-item sample spanning 12 distinct missing series ids → all `200`; a real hidden-series item (43627, series 3332) → still `404`, unchanged. **Not legacy parity** — legacy's own broken/blank-page behavior for this condition was explicitly not reproduced. The 834 hidden-series items remain **open, unresolved**, deliberately excluded from this repair.
- **Hidden-series branch — investigation completed (2026-08-28, read-only):** `series.hidden` is architecturally identical to `item.hidden`/`group.hidden` throughout legacy (a plain admin-visibility boolean, strictly 0/1 in practice, with the same `$hiddenSql`-gated admin-bypass pattern in `item.php`, `series.php`, `ListSeries()`, `ListKhotab()`) — nothing in the schema or code cascades a hidden series to its items. **Direct evidence the flag is not intended to hide child content:** of 857 total children of the 73 hidden series, **834 (97.3%) remain `item.hidden=0`**. Two identifiable, evidence-backed sub-patterns (not a single uniform cause): (1) ~22 series, mostly author 154, titled around 2011-2012 Egyptian election/political coverage, whose `count` metadata matches reality exactly; (2) ~15 series with a **same-author, identically-titled, currently-visible sibling series**, plus stale `count=0` metadata — consistent with a duplicate/superseded series whose items were never migrated to the replacement id. Legacy's own `ListPDF()` (and its faithful Laravel port `khotabPdfItemsByAuthor()`) already lists these items on the author's PDF page regardless of series-hidden status, a pre-existing legacy inconsistency, not a Laravel-introduced one.
- **Repaired (decision-log #56) — owner-approved `ITEM_VISIBILITY_IS_INDEPENDENT`, explicitly NOT legacy parity:** `KhotabItemController::show()`'s hidden-series branch now merges into the same null-series path as the missing-series repair (#55) — a hidden series resolves to `$series = null`, the item renders normally, only the breadcrumb crumb is omitted, no hidden series title/id is exposed anywhere (live-confirmed). `KhotabSeriesController` deliberately untouched — the hidden series' own `/khotab-series-{id}.htm` page still `404`s (regression-guarded by the pre-existing `KhotabBrowsingControllersTest` "series show: 404s for a hidden series" test, unaffected by this change). Live-verified against 6 real samples spanning 5 distinct hidden series, both video/audio, item ids from 2011 to 2025: all now `200`; all 6 series pages still `404`. **Finding #2 is now fully repaired** (both the nonexistent-series and hidden-series branches).

### P1 — common, user-facing

#### 3. Shared navigation menu uses relative `href`s — breaks entirely on any nested-path page — **FULLY REPAIRED (decision-log #57), `BUSINESS_REPAIR_LOW_RISK`, explicitly NOT legacy parity**
- **Example:** every link in the primary nav (`/categories.htm`, `/khotab-video.htm`, `/channels.htm`, `/fatawa-*.htm`, etc.) rendered as `/w2acd/categories.htm`, `/w2acd/khotab-video.htm`, etc. when viewed from `/w2acd/cds.php` — 34 distinct affected internal hrefs (plus the shared search form's `action`) from this 1 shared partial.
- **Source page:** `/w2acd/cds.php` — and, discovered during this repair's own investigation, `/w2acd/item.php` (a 2nd genuinely nested-path page rendering the same partial, previously unlisted).
- **Laravel generator:** `resources/views/layouts/partials/navigation.blade.php` (e.g. line 25: `<a href="categories.htm">`, no leading `/`).
- **Legacy generator, verified directly:** `legacy-project/header.php:255` — was identical, `href="categories.htm"`, no leading slash.
- **Root cause:** browsers resolve a bare-relative href against the current path's directory. On any flat page (`/categories.htm`), the relative target resolved correctly. On `/w2acd/cds.php`/`/w2acd/item.php` (the only 2 genuinely nested paths in the public site), it resolved into `/w2acd/` — a real, live, byte-faithful legacy bug (`legacy-project/header.php` has the exact same bare-relative hrefs), not a migration-introduced regression.
- **Repaired:** all 34 distinct internal `.htm` hrefs plus the search form's `action` in `navigation.blade.php` are now root-relative (a leading `/`) — proven, not assumed, to be a no-op on every flat page (root-relative and bare-relative resolve identically from a root-level URL) and the actual fix for both nested pages. No route, controller, or database changed; no external/`#`-toggle/asset href touched. Full evidence, live verification (34/34 destinations confirmed `200`/`302`, zero remaining bare-relative hrefs on either nested page, root-page nav unchanged) in decision-log #57.
- **Confidence:** High (both source lines read directly, byte-identical).
- **Recommended next action:** Not required for legacy fidelity, but a genuinely low-risk, high-value fix: add leading slashes to `navigation.blade.php`'s hrefs. This cannot regress any other page (absolute and relative resolution are identical everywhere else in the site, since no other page has a nested path) and would fix real navigation on the one page where it's broken today. Flagged as a strong candidate for a future low-risk repair batch, not applied here per this task's explicit discovery-only mandate.

#### 4. `/fatawa-topics-{category}.htm` (missing required `{page}` segment) — WRONG_LARAVEL_GENERATOR (B) — **REPAIRED (decision-log #52, Repair Batch 1)**
- **Example:** `/fatawa-topics-1.htm`, `/fatawa-topics-13.htm`
- **Source page(s):** `/fatawa-all-16473.htm`, `/fatawa-all-16471.htm`, `/fatawa-all-16470.htm` (any `fatawa-all-*` page whose general question has a resolvable topic/category chain) — 3 occurrences, 3 source pages
- **Laravel generator:** `resources/views/fatawa/question-all.blade.php:121` — `<a href="/fatawa-topics-{{ $category->id }}.htm">` — only 1 segment.
- **Registered route:** `routes/content.php:516` — `Route::get('/fatawa-topics-{category}-{page}.htm', ...)` — **requires 2 segments**, no 1-segment variant exists.
- **Confidence:** High — this is the same defect class already found and fixed twice before in this project (decision-log entries for `fatawa-topics-index`/`fatawa-channels-index`'s own links, both corrected during an earlier increment) — this is a **third, previously-unnoticed instance** of the identical mistake, in a different view.
- **Repair evidence:** `resources/views/fatawa/question-all.blade.php`'s category breadcrumb link now resolved via the named route `route('fatawa.topics.show', ['category' => $category->id, 'page' => 1])` instead of a hand-built 1-segment string. Live-verified against a real general question with a 2-level category chain (`/fatawa-all-16473.htm`): the breadcrumb now emits `/fatawa-topics-1-1.htm` and `/fatawa-topics-383-1.htm` (both `200`), no `/fatawa-topics-1.htm`/`/fatawa-topics-383.htm` (the old broken shape) anywhere in the output. Full evidence in decision-log #52.

### Confirmed healthy / expected (not defects) — reported for completeness, per "do not report a clean site without genuine coverage" and "do not silently drop a traced finding"

| URL / family | Initial signal | Resolution |
|---|---|---|
| `/fatawa-channel-0.htm` | 404 | **F — EXPECTED_404.** `FatwaChannelController`'s own docblock confirms legacy always shows a "no channel" (id=0) entry, which was never a real clickable row either — already deliberately reproduced, not new. |
| `/location-{location}-author-{author}.htm` (random cross-products) | 404 (all 10 sampled) | **F — EXPECTED_404.** My own sampling used arbitrary, unrelated location/author pairs. Re-tested with 6 REAL pairs from `nuke_islamic_authors_location` — all 6 returned 200. Route is healthy; the finding was a sampling artifact. |
| `/fatawa-topics-{id}-1.htm` (6 sampled `nuke_fatwa_topics` ids) | 404 (5/6) | **Sampling error, not a defect.** The route parameter is `{category}` (`nuke_w2a_cat.id`), not `nuke_fatwa_topics.id` — re-tested with 6 real category ids, all 6 returned 200. |
| `/khotab-item-pdf-{id}.htm` | 404 (via redirect target) | **Not a route defect.** The route itself correctly issues a `302` to the computed bucketed path (verified: `/khotab-item-pdf-269113.htm` → `302` → `/media/pdf/269/269113.pdf`). The 404 is on the *target PDF file*, which is absent from the local `media/` mirror — this may be a local-environment media-corpus completeness gap, not a verified production defect (not evidenced against production, per this task's own read-only-local-only constraint). |
| `/khotab-video-{id}.htm` (13 ids, "ERROR" during crawl) | Connection error mid-crawl | **Tooling artifact, not a defect.** Re-tested individually outside crawl load: all 13 return `200` in ~2.5-3s. The crawler's rapid back-to-back requests transiently overloaded the single-process local dev server. |
| `/khotab-download-*`, `/khotab-item-*` (5 ids, "ERROR" during sampling) | Timeout in the sampler (8s) | **Tooling artifact.** Re-tested with a longer timeout: all return real `200`/`302` in 7-20s — these specific pages are genuinely slow to render locally (worth a separate performance look, out of scope for this audit), not broken. |
| `/fatawa-download-*`, `/var-download-*`, `/var-mirror-*`, `/recite-download-*` (external redirects) | Flagged as "not 200" by the grouping script | **Healthy, expected behavior.** These routes correctly `redirect()->away(...)` to archive.org/external media hosts — that's their real, designed function, not a broken link. |
| `/telawah.htm`, `/advanced-search.htm`, `/mobile-app.htm`, `/contact.htm` | 404 (crawled directly) | **Not a site defect — my own seed-list guesses.** Confirmed via the edge index: no real crawled page ever links to any of these 4 exact paths. Real equivalents exist under different names (`/recite.htm`, `/video-advanced-search.htm`, `/mobile-app`, none for "contact"). |

## Classification counts

| Classification | Count |
|---|---|
| A. MISSING_MIGRATION_ROUTE | 1 |
| B. WRONG_LARAVEL_GENERATOR | 1 |
| C. LEGACY_BROKEN_LINK | 0 (new) |
| D. SOURCE_UNRECOVERABLE | 0 (new) |
| E. ROUTE_EXISTS_BUT_BROKEN | 1 |
| F. EXPECTED_404 | 3 (1 legacy-faithful bug + 2 confirmed-by-design) |
| G. DATA_SPECIFIC_STALE_LINK | 1 |
| H. COMPATIBILITY_REPAIR_CANDIDATE | 0 (new) |

## E. ROUTE_EXISTS_BUT_BROKEN — detail

#### 5. `/khotab-series-{series}.grx` — real 500 error on at least 1 real series id — **REPAIRED (decision-log #53, Repair Batch 2) — `BUSINESS_REPAIR_LOW_RISK`, explicitly NOT legacy parity**
- **Example:** `/khotab-series-15972.grx`
- **Route:** `routes/content.php:218` → `CategoryDownItemsController::show()`
- **Confirmed real exception** (`storage/logs/laravel.log`): `iconv(): Detected an illegal character in input string` at `CategoryDownItemsController.php:77`.
- **Root cause, fully traced:** systemic, not a one-off — 21/150 (14%) of a random sample failed, driven by legitimate Unicode Arabic typography (the `ﷺ` religious ligature, Arabic-Indic digits, U+200F marks). Legacy's own real `functions.php:744` has the exact same bare `iconv()` call with no failure handling — legacy's real behavior for these rows is a silent, empty-body `200`, not a crash; this project's warning-to-exception escalation is what turned it into a `500`.
- **Owner decision:** do not reproduce legacy's silent-empty-file defect either. Repaired via narrow normalization of exactly the 3 proven character classes (`CategoryDownItemsController::normalizeForWindows1256Export()`) plus controlled, logged, non-silent failure handling for anything not yet identified — `iconv()` kept strict (no `//IGNORE`), so new unsupported characters surface as real, loggable failures instead of vanishing.
- **Validated on real data:** `/khotab-series-15972.grx` now `200`, non-empty, correct content. Re-sampling the same 150 series found 3 genuinely NEW unsupported characters (Quranic diacritic marks: U+0670, U+0654, U+0655, U+06E1, U+0671, U+0653) — correctly caught by the defensive handling (logged, controlled `500`), NOT silently normalized, per explicit instruction. Reported as a distinct, separate, unresolved item — not folded into this repair.
- **Fully closed (decision-log #54):** the 6 codepoints found above, plus 6 more discovered by a broader 150-series simulation, were traced, classified, and — after owner review — both sets explicitly approved and implemented (recomposition of decomposed hamza/maddah sequences; 2 corpus-proven letter substitutions; removal of 8 Quranic annotation marks with no letter-identity function — an explicit codepoint allowlist, not a Unicode range/category rule). Re-verified live against all 6 real affected series and a fresh 150-series sample: `0` controlled `500`s, 3 legitimate empty series (confirmed zero-item, unrelated to encoding), 0 remaining unsupported codepoints in-sample. Still explicitly NOT legacy parity — legacy's own silent-empty-body defect remains un-reproduced by design — and unknown future characters still fail visibly (logged, controlled `500`), not silently.
- **Confidence:** High (real stack trace, real round-trip verification, 2 independent real 150-series re-samples, all 6 originally-affected series individually re-verified live post-repair). Full evidence in decision-log #53 and #54.

## Redirect-to-404 findings

None found among the 350 fully-crawled pages' own redirect chains. The one redirect-then-404 case found (`khotab-item-pdf-*` → real PDF path → 404) is a redirect-to-missing-*file*, not redirect-to-broken-*route*, and is already covered above as an audit-coverage limitation rather than a route defect.

## Previously repaired flows — regression result

All confirmed healthy, live-tested in this pass:

| Flow | Result |
|---|---|
| `/fatawa-authors.htm` | `200` |
| `/khotab-fatwa-17.htm` | `302` → `/auther-questions-17.htm` |
| `/auther-questions-17.htm` | `200` |
| `/auther-all-fatawa-17-1924.htm` | `200` |
| `/fatawa-all-1924.htm` | `200` (unscoped, unaffected) |
| `/fatwa-today.htm` | `200` |
| `/fatwa-date-16-8-2026-1.htm` | `200` |

**No regressions.**

## Audit coverage limitations

- **Bounded crawl, not exhaustive.** 350 pages out of 14,698 unique discovered URLs were actually fetched-and-parsed-for-further-links; the remaining ~14,300 were tested for status only where they fell into a family already sampled, not individually crawled for their own onward links. A materially larger crawl budget would likely surface additional, deeper findings this pass did not reach (e.g., pagination tails, rarer category/topic combinations, deeper khotab series/group nesting).
- **No admincp coverage.** Excluded entirely per the safety instruction (authentication + mutation risk) — this audit says nothing about admin-side internal links.
- **GET-only; 6 real POST form actions discovered but never submitted** (`survey-vote-{id}.htm`, `search.htm`, `w2acd/search.htm`, 3× `fatawa-friend-sendemail-*`) — their actual submission behavior is unaudited by design.
- **GET-method search/filter forms were not submitted with real query values** — only their bare action URL would have been reachable via crawling if linked elsewhere; form-driven search result pages are not covered.
- **Local dev server performance affected tooling accuracy** — several pages are genuinely slow (7-20s) under back-to-back automated requests, producing transient false "ERROR" signals that required manual re-verification; this does not indicate real breakage, but is itself worth a separate performance look.
- **`media/` corpus completeness is a known local-only gap** — the `khotab-item-pdf-*` finding may not reproduce on real production, which was never accessed.
- **Multi-ID sampling covered a fixed, moderate set of real ids per family (typically 5-8)** — not exhaustive across the full row count of any table; a rare id-specific issue outside the sampled set would not be caught here.

## Recommended repair batches

1. **Batch A — trivial, low-risk, high-confidence.** ✅ **`fatawa-topics-{category}.htm` missing-page-segment link (finding #4) — REPAIRED, decision-log #52.**
2. **Batch B — real gap, moderate effort.** ✅ **`/recite-news.htm` (finding #1) — REPAIRED, decision-log #52.** `TelawahLatestController` built, mirroring the existing `FatwaLatestController` pattern as recommended.
3. **Batch C — needs its own trace before fixing.** ✅ **The `khotab-series-*.grx` iconv 500 (finding #5) — FULLY REPAIRED, decision-log #53 + #54 (`BUSINESS_REPAIR_LOW_RISK` + owner-approved Quranic-codepoint extension, not legacy parity).** ✅ **The orphaned/hidden-series khotab items (finding #2) — FULLY REPAIRED, decision-log #55 + #56 (`MIGRATION_STRICTNESS_DEFECT` + owner-approved `ITEM_VISIBILITY_IS_INDEPENDENT`, not legacy parity)** — 1,349 nonexistent-series items + 834 hidden-series items (2,183 total) now render normally; `KhotabSeriesController` (the `khotab-series-{id}.htm` route) was deliberately left untouched throughout — a hidden series' own page still `404`s, unchanged. ✅ **The w2acd shared-nav relative-href bug (finding #3) — FULLY REPAIRED, decision-log #57 (`BUSINESS_REPAIR_LOW_RISK`, not legacy parity)** — 34 internal hrefs + the search form action in `navigation.blade.php` are now root-relative; no regression on any flat page.

**All 5 findings are now closed. This sitewide audit is fully complete.**
