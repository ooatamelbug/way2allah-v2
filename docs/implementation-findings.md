# Implementation Findings Log

Living record of undocumented legacy behavior discovered while implementing Blueprint v1.0, starting Wave 1. Distinct from the audit's own `00-migration-patterns.md`/`00-unknowns.md` (which cover what was found during the pre-implementation audit) — this log covers what implementation itself surfaces, which is often more precise than what a documentation-only pass could find, because building a faithful port forces reading a function completely rather than characterizing it.

**Governing rules for this log and everything it feeds (per direct instruction, 2026-08-04):**

1. The legacy system is the source of truth unless there is a confirmed bug or an ADR-0010-approved redesign. Default to preserving observed behavior, not "likely intended" behavior.
2. Every discovery of undocumented legacy behavior gets an entry here: Finding ID, Location, Evidence, Decision, Impact on the migration.
3. Every intentional preservation of legacy behavior — even behavior that looks wrong — states explicitly why it's preserved.
4. Every intentional improvement or redesign is marked as an ADR decision, never a silent implementation choice.
5. Every finding is backed by an executable test proving the decision, not just a comment asserting it.
6. If implementation evidence suggests a Blueprint architectural decision is no longer right, that's raised explicitly and challenged — not silently worked around.

**Finding ID scheme:** `IF-NNN`, sequential, never reused or renumbered even if a finding is later superseded (superseding entries reference the one they replace).

---

## IF-001: khotab's `ListGroup()` has a duplicate SELECT alias — a live aggregate silently shadows a stored column

- **Location:** `khotab/functions.php:342-345`
- **Evidence:** The query selects both `grp.count` and `COUNT(kh.id) as count` — two different values under the identical alias `count`. The result mapping resolves this collision in favor of the aggregate, and that's what the template actually displays (`khotab/functions.php:382`, `<?php echo $item->count;?>`). This is what production has been serving, not a hypothetical worst case.
- **Decision:** Preserve the *serving* behavior exactly — `ContentListingService::groupsByAuthor()` returns the live `COUNT(kh.id)` aggregate as `count`. The shadowed stored value is additionally exposed as `stored_group_count` rather than discarded, since building the query correctly costs nothing extra and nothing should be silently lost. This is preservation, not a fix — ADR-0010 applies (Must preserve: confirmed, currently-active behavior with no evidence anyone considers it broken).
- **Impact on the migration:** Any Wave 4 khotab-domain code must read `count`, not `stored_group_count`, to match current site behavior. If the business later confirms the stored column was the *intended* display value, that reversal is a redesign decision requiring its own ADR, not a default fix.
- **Test:** `tests/Feature/Content/ContentListingServiceTest.php` — "uses the LIVE COUNT(kh.id) aggregate, not the stored grp.count column".

## IF-002: w2acd's sidebar functions accept a `$Group` parameter that is never used

- **Location:** `w2acd/functions.php:181-211` (`most_downloaded_list($Group="")`, `most_recent_list($Group="")`)
- **Evidence:** Both functions declare `$Group` but never reference it in either function body — confirmed by reading both in full, not by pattern-matching the signature. Contrast: `anasheed/functions.php:842-881`'s equivalent functions accept the same-shaped parameter and do use it (`$Group->id` in a WHERE clause).
- **Decision:** Not reproduced. `ContentSidebarWidget::w2acdMostDownloaded()`/`w2acdMostRecent()` take zero parameters. A parameter that silently does nothing is worse than no parameter — it misrepresents a capability that never existed in production.
- **Impact on the migration:** Any future assumption (by analogy with anasheed) that w2acd's sidebar can be scoped to a group is wrong and must be corrected at the point it's made, not carried forward.
- **Test:** `tests/Feature/Content/ContentSidebarWidgetTest.php` — reflection assertion that `w2acdMostDownloaded` takes 0 parameters.

## IF-003: w2acd's sidebar HTML builder computes a value it never uses

- **Location:** `w2acd/functions.php:212-242` (`most_recent_html()`), specifically line 218
- **Evidence:** `$basefolder = floor($item->id/1000);` is computed and never referenced again — the actual thumbnail path is built from the `thumbnail` column's first comma-separated segment under `images/cds_image2/` (lines 217-220), not the `media/` bucketing convention at all.
- **Decision:** Not reproduced anywhere. `MediaPathResolver`'s bucketing convention is confirmed **not** to be what w2acd's sidebar currently uses for its own thumbnail. This is left as an open question for whichever Wave 4 view rebuilds w2acd's sidebar rendering — it must not default to assuming `MediaPathResolver` applies here.
- **Impact on the migration:** Prevents a plausible but wrong assumption in Wave 4 (that all content-type sidebars use the same thumbnail convention) from being made silently.
- **Test:** Implicit — `ContentSidebarWidget`'s w2acd methods return raw columns only (`thumbnail`, `banner`), no path construction, so there's nothing to test being absent; the finding is recorded here so the *absence* is a documented decision, not an oversight discovered later.

## IF-004: Author-photo fallback bucket uses a different id column in two different legacy call sites

- **Location:** `home_functions.php:39-40` vs. `functions.php:1055` (`topitems()`)
- **Evidence:** `home_functions.php`'s `list_latest_videos()` computes the author-photo fallback bucket as `floor($Item->thid/1000)`. `functions.php`'s `topitems()` computes the same conceptual fallback as `floor($item->author/1000)`. Two different columns (`thid` vs. `author`) feeding what reads as the identical "show the author's photo" case.
- **Decision:** Not resolved. `MediaPathResolver` provides only the bucketing math and path construction (`bucket()`, `path()`); which id feeds it for the author-photo case is explicitly deferred to whichever Wave 3/4 model accessor implements it, once real data can confirm which source is actually correct. Silently picking one now would be an unverified, undocumented behavior change for whichever call site turned out to be wrong.
- **Impact on the migration:** Blocks unifying the two call sites' author-photo logic until resolved. **Should be added to Blueprint Part IV (Business Confirmations Required) as a new item** — this is genuine new scope the Blueprint didn't know about when it was frozen, per the standing rule that new ADRs (or in this case, new confirmation items) are how the Blueprint evolves, not silent edits to it.
- **Test:** `tests/Unit/Content/MediaPathResolverTest.php` proves the bucketing math itself; no test picks a side on `thid` vs. `author` since neither is implemented yet — deliberately.

## IF-005: khotab's `ListKhotab()` filters `author`/`ser_id`/`group_id` unconditionally in its default mode, but only conditionally in its `fixed`/`new` mode

- **Location:** `khotab/functions.php:541-564` (fixed/new branch) vs. `khotab/functions.php:599-616` (default/else branch)
- **Evidence:** The fixed/new branch only adds `kh.author = ?` (etc.) to the WHERE clause when the value is `> 0` (lines 542-559). The default branch always includes `kh.author=? AND kh.vedio=? AND kh.ser_id=? AND kh.group_id=?` regardless of value (line 608) — meaning `author_id=0` in default mode actively filters to rows where `author` is literally `0`, not "no author filter."
- **Decision:** Both behaviors preserved exactly, as two separate methods — `khotabItemsFixedOrNew()` (conditional) and `khotabItemsDefault()` (unconditional). This is an internal inconsistency within one legacy function's own branches, not a bug to normalize — both branches are independently reachable and in active use, so ADR-0010's "must preserve" applies to each on its own terms rather than forcing them to agree with each other.
- **Impact on the migration:** Any Wave 4 caller must choose the correct method deliberately. Calling `khotabItemsDefault()` with a real `authorId=0` returns only "authorless" items, not "all items" — a caller expecting the `fixedOrNew` semantics would get silently wrong results if they called the wrong method.
- **Test:** `tests/Feature/Content/ContentListingServiceTest.php` — "khotabItemsDefault: filters author/ser/group unconditionally, even when 0" and the paired `khotabItemsFixedOrNew` conditional-filter test.

## IF-006: `categories`' and `khotab`'s group listings compute the displayed item count differently, not just filter differently

- **Location:** `categories/functions.php:5-8` vs. `khotab/functions.php:342-345`
- **Evidence:** `categories`' `ListGroup()` selects `grp.count` directly with no JOIN to `nuke_islamic_khotab` at all. `khotab`'s `ListGroup()` INNER JOINs to `nuke_islamic_khotab` and computes `COUNT(kh.id)` live (see IF-001). These aren't two filter variants of the same computation — they are two different computations of "how many items does this group have," one cached/stored, one live.
- **Decision:** Both preserved exactly, as `groupsByAuthor()` (live) and `groupsByCategory()` (stored) — not normalized to one shared computation.
- **Impact on the migration:** If the by-author and by-category group-browsing surfaces are ever shown side by side, they may display different counts for the same group. **Candidate for a new business question** ("should these two counts ever agree, and if so which one is correct?") rather than something implementation should decide.
- **Test:** `tests/Feature/Content/ContentListingServiceTest.php` — the `groupsByCategory` "trusts the stored count directly, never joining khotab" test, contrasted with IF-001's test for `groupsByAuthor`.

## IF-007: live-stream's "most recent" sidebar orders by `id DESC`, not a time column

- **Location:** `live-stream/functions.php:169-192` (`most_recent_list()`)
- **Evidence:** `ORDER BY id DESC` (line 180) — unlike `anasheed`/`w2acd`/`telawah`'s equivalent functions, which all order by a `mytime` column. The table this function queries (`nuke_islamic_khotab`) does have a `time` column, but this specific legacy function doesn't use it — it uses the row's own primary key as a recency proxy instead.
- **Decision:** Preserved exactly — `ContentSidebarWidget::liveStreamMostRecent()` orders by `id DESC`.
- **Impact on the migration:** `id` and `kh.time` are not guaranteed to correlate perfectly (e.g. a backfilled or edited row could have an old `time` but a recent `id`, or vice versa). If a future redesign wants "most recent" to mean "most recently time-stamped" instead, that is an explicit ADR-marked change, not a default assumption to make while porting.
- **Test:** `tests/Feature/Content/ContentSidebarWidgetTest.php` — "orders by id DESC, not a time column" (constructs a row with far more hits but a lower id specifically to fail if ordering used anything else).

---

## IF-008: `mo7fzat-quran.php`'s embedded Google Form is missing the `embedded=true` parameter its two structural siblings both have

- **Location:** `pages/mo7fzat-quran.php:27` vs. `pages/estebian.php:27` and `pages/tatw3-w2a-team.php:27`
- **Evidence:** All three pages embed a Google Form in an iframe with the same template shape. `estebian.php` and `tatw3-w2a-team.php` both use `.../viewform?embedded=true`; `mo7fzat-quran.php` uses `.../viewform` with no query string at all. The pre-implementation audit (`pages.md` §5) characterized these three pages as differing "only in title, embedded form URL, and iframe height" — a direct read of all three during implementation shows a fourth, uncaptured difference: the `embedded=true` parameter's presence. Google Forms typically shows extra Google-branded chrome (header/footer) inside an iframe when this parameter is absent, though this wasn't independently verified by loading the form.
- **Decision:** Reproduced exactly — `QuranMemorizationApplicationController` uses the bare `/viewform` URL with no `embedded=true`. Not "fixed" by adding the parameter to match its siblings, since there's no confirmation this is a copy-paste omission rather than an intentional (if unexplained) difference.
- **Impact on the migration:** None functionally — the iframe still renders the correct form either way. Worth a quick, cheap business question ("should this form also hide the Google Forms chrome, like the other two?") the next time anyone touches this page, but not migration-blocking.
- **Test:** `tests/Feature/Pages/MobileAppAndFormsPagesTest.php` — "IF-008: iframe URL has no embedded=true param, unlike its siblings".

---

## IF-009: live-channel.php's and live.php's direct-view queries don't check `streamcode` emptiness — narrower than the pre-implementation audit's own characterization

- **Location:** `live-stream/live-channel.php:10`, `live-stream/live.php:17` vs. `live-stream/functions.php:6,44`
- **Evidence:** `live-stream.md` §5 states "all three entry files independently re-run the same 'active=0 AND streamcode not empty' channel-eligibility filter." A direct read of all 4 files during implementation shows this is imprecise: `list_live_channels()` and `most_viewed_channels()` (both in `functions.php`) do check `streamcode IS NOT NULL AND streamcode <> ''`; `live-channel.php` and `live.php` check only `active = 0 AND id = ?` — no streamcode condition at all. Two places have the fuller filter, not four.
- **Decision:** `Channel::eligibleForLiveStream()` (the 3-condition scope) is used only for the directory listing and the "most viewed channels" widget — `LiveStreamController::show()`/`featured()` query `active = 0` alone, matching each call site exactly rather than applying the stricter scope everywhere for consistency.
- **Impact on the migration:** A channel with `active = 0` but an empty `streamcode` is unreachable from the directory page (correctly excluded there) but still directly viewable if someone has or guesses its `/live-channel-{id}.htm` URL — this is what legacy actually does, not a bug introduced here. A future redesign that wants direct URLs to also enforce the streamcode check would be a real behavior change requiring an ADR.
- **Test:** `tests/Feature/Content/LiveStreamControllerTest.php` — "IF-009 — a channel with an empty streamcode is still directly viewable".

## IF-010: `live.php` never increments `ch_visits` — the only channel view in the module that doesn't

- **Location:** `live-stream/live.php` (entire file — confirmed by its complete absence of any `UPDATE` statement) vs. `live-stream/live-channel.php:37-38`
- **Evidence:** `live-channel.php` ends with an unconditional `UPDATE nuke_sat_channels SET ch_visits = (ch_visits+1) WHERE id = ...`. `live.php` has no equivalent statement anywhere — confirmed by reading the full 30-line file, not by absence-of-mention. This is also the first confirmed instance of P-014's atomic-counter pattern under a column name other than `hits` (`ch_visits`), which drove a small generalization of the existing `TracksViews`/`RecordsView` Wave 1 component (see `viewCountColumn()`, an Implementation Refactoring, not a new shared-component decision).
- **Decision:** `LiveStreamController::show()` calls `Channel::recordView()`; `LiveStreamController::featured()` (the `live.php` route) does not call it at all.
- **Impact on the migration:** Channel 51's visit count, if this route is ever used, will under-report relative to a normal channel view. Preserved as-is — "fixing" this to also count would be a redesign decision, not an implementation default, and this page's purpose is itself unconfirmed (Business Confirmation candidate already tracked in `live-stream.md` §8).
- **Test:** `tests/Feature/Content/LiveStreamControllerTest.php` — "featured: renders the hardcoded channel 51 and does NOT increment ch_visits".

## IF-011: `channels/channels.php`'s panel title comes from an undefined variable and renders blank

- **Location:** `channels/channels.php:28`
- **Evidence:** `$data = array('title'=> $Anasheed->title, ...);` — `$Anasheed` is never assigned anywhere in this file or its includes. This produces a PHP warning ("Undefined variable", then "Attempt to read property on null") and the panel title evaluates to `null`. Almost certainly a copy-paste artifact from an unrelated (anasheed-related) template, not a real feature — but per rule 1, an inferred cause doesn't authorize a silent fix.
- **Decision:** `ChannelController::index()` passes an explicit empty string as the panel title, reproducing the actual current output (blank), not inventing a plausible title ("Channels"/"القنوات") that was never really there.
- **Impact on the migration:** Cosmetic only — the channel grid itself renders correctly, only its section heading is blank. Low-priority candidate for a future content fix once the business is asked what the heading should say (it never has said, effectively, in production).
- **Test:** `tests/Feature/Content/ChannelControllerTest.php` covers the channel grid rendering; the blank-title reproduction itself is asserted structurally (empty string passed to the view) rather than by a dedicated visual test, since there is no meaningful "wrong" text to assert against.

## IF-012: `channels/author.php`'s "Most Downloaded"/"Newest" sidebar boxes are confirmed empty — unlike `channel.php`'s otherwise-similar page

- **Location:** `channels/author.php:84-98` vs. `channels/channel.php:94-111`
- **Evidence:** Both files render a box titled "الأكثر تحميلا" (Most Downloaded) and "جديد المواد" (Newest). `channel.php` populates them via `topitems(...)` (see `ContentSidebarWidget::channelMostDownloadedKhotabItems()`/`channelMostRecentKhotabItems()`). `author.php`'s equivalent boxes are `w2a_open_div($data); w2a_close_div();` with nothing rendered between them — confirmed by reading the full file, not inferred from similarity to `channel.php`.
- **Decision:** `ChannelController::showAuthor()` does not call either sidebar method at all. The two boxes render with headings only, no items — matching legacy exactly, not "completing" what looks like an oversight.
- **Impact on the migration:** A visitor browsing a channel filtered to one author sees no downloads/newest recommendations, while the unfiltered channel page does show them — a real, confirmed inconsistency in the current site, not introduced by this port. Good candidate for a future business question ("should author-filtered pages have this too?"), not resolved here.
- **Test:** `tests/Feature/Content/ChannelControllerTest.php` — "showAuthor: ... leaves the most-downloaded/newest boxes empty (IF-012)", which also proves an unrelated author's high-hit item never leaks into the (absent) sidebar.

## IF-013: `channels/author.php`'s profile picture uses a third, non-bucketed media path convention

- **Location:** `channels/author.php:69`
- **Evidence:** `<img src="media/authors/sq/<?php echo $author_id;?>.png" ...>` — a flat `authors/sq/` subfolder keyed directly by author id, with no `floor(id/1000)` bucketing at all. Distinct from both `MediaPathResolver`'s bucketed convention and `home_functions.php`'s `media/authors/{floor(thid/1000)}/{thid}.jpg` fallback (IF-004) — a third author-photo path shape found in the codebase, not two.
- **Decision:** Not routed through `MediaPathResolver` (which would compute the wrong path for this convention). The literal `/media/authors/sq/{id}.png` path is reproduced directly in `channels/author.blade.php`.
- **Impact on the migration:** Confirms author-photo path conventions are genuinely inconsistent across the codebase (now 3 confirmed shapes, not 1), reinforcing IF-004's point that unifying them without real data/business input would be guessing, not migrating. Worth surfacing to the business alongside IF-004 rather than as a separate question.
- **Test:** Implicit — `channels/author.blade.php` hardcodes this exact path; no dedicated assertion beyond the view rendering without error, since there's no bucketing logic to prove correct or incorrect here.

## IF-014: `khotab/item.php`'s sidebar widgets filter on an undefined `$Khotab->video` (not `->vedio`) — likely showing the wrong content type on every video item's page

- **Location:** `khotab/item.php:467,476`
- **Evidence:** Every other reference to the video/audio flag in this same file uses `$Khotab->vedio` (the real, confirmed column — lines 91, 126, 194, 197, all consistent with the rest of the audit). Lines 467 and 476, inside the "الأكثر تحميلا" (Most Downloaded) and "جديد المواد" (Newest) sidebar boxes, instead read `$Khotab->video` — a property that doesn't exist on the query result (`SELECT * FROM nuke_islamic_khotab`, confirmed no `video` column anywhere in this codebase). PHP would emit an "Undefined property" warning and the expression evaluates to `null`, which PHP casts to `''` in the string concatenation building `topitems('hits', "vedio ='" . $Khotab->video . "'", ...)` — producing the literal WHERE fragment `vedio =''`.
- **Consequence (Fact, upgraded from Inferred — directly confirmed by `functions.php`'s own `topitems()`, root-level, lines 992-1004):** `topitems()` itself contains a compatibility shim, added in prior-session performance work, that normalizes exactly this pattern: `$where = preg_replace("/\b(vedio|author|channel_id|ser_id|group_id)\s*=\s*''/", '$1=0', $where);`, with an explicit code comment: *"Callers build $where from row objects such as $Khotab->video, but the column (and the object property populated from it) is actually spelled `vedio` throughout this schema - the mismatch reads as an undefined property and produces the literal `vedio=''` seen dominating the query log (180K+ calls/day). Normalize it to a real int so the vedio indexes below can be used; this is a compatibility shim, not a fix for the underlying `->video`/`->vedio` naming bug in the callers."* This independently confirms, from first-party evidence rather than inferred MySQL semantics, that: (a) this exact bug is real and was already observed in production query logs at high volume, (b) it was deliberately left unfixed at the caller level and only patched for performance (making the coercion explicit/index-friendly rather than implicit), and (c) the resulting behavior is exactly `vedio=0` as reasoned below — no longer a coercion inference, a directly documented fact. On a **video** item's page (`vedio=1`), these two sidebar boxes show **audio** items — the wrong content type. On an **audio** item's page (`vedio=0`), the shim's `vedio=0` happens to match the correct type, so the bug is invisible there.
- **Decision:** Not reproduced. This is being classified as a **confirmed bug** (a typo, `video` vs `vedio`, not a deliberate design choice — no other file in this codebase has ever used `video` as a khotab column name) rather than legacy behavior worth preserving. The Wave 4 Laravel port uses the correct `vedio` value for these two sidebar queries. Flagged here per rule 3 (explicitly explaining a preservation-vs-correction call), even though this is the *correction* side of that rule, since the fix is small enough not to warrant a separate ADR (matches the precedent set by `LiveStreamController`'s 404-vs-`die()` change and `AdminGuard`'s missing-plaintext-fallback — technical-correctness-tier fixes for confirmed bugs, not business-behavior redesigns).
- **Impact on the migration:** A real, positive fix users would actually notice (correct "most downloaded"/"newest" recommendations on every video item page, once this port ships) — not just an internal cleanup. Worth flagging to the business as a fixed bug, not a silent behavior change, when Wave 4 ships.
- **Test:** Done — `tests/Feature/Content/KhotabItemControllerTest.php`, `'show: fixes IF-014 — a VIDEO item's "Most Downloaded"/"Newest" sidebar shows video items, not audio'` and its audio-item counterpart, both asserting the correct content type appears and the wrong one doesn't.

---

## IF-015: `khotab/series.php`'s "Most Downloaded" sidebar box reads `$Group->author_id` on a variable that is sometimes a plain array, not an object — breaks the filter for series with no group

- **Location:** `khotab/series.php:34-36` (assignment), `khotab/series.php:146` (use)
- **Evidence:** Lines 32-36: `if($Series->group_id>0){ $Group = $w2adb->get_row(...); } else { $Group = array(); }` — when a series has no group, `$Group` is a plain empty PHP array, not a DB-row object. Line 146, in the "الأكثر تحميلا" (Most Downloaded) sidebar box, reads `$Group->author_id` unconditionally: `topitems('hits', "author = '". $Group->author_id . "' AND vedio ='" . $Series->vedio . "'", "hits DESC", 5);`. The very next block (line 155, "جديد المواد" / Newest) uses `$Author->id` instead — the correct, always-available value — showing the two boxes were not written consistently.
- **Consequence (Inferred from PHP's documented behavior — property access on array):** For any series with `group_id > 0`, `$Group` is a real object and this works (redundantly duplicating `$Author->id`, since `nuke_islamic_series.author_id` and the group's `author_id` are expected to agree). For any series with `group_id <= 0` (ungrouped series — confirmed to occur, `group.php`/`ListSeries()` both branch on `group_id>0` as an optional case), `$Group->author_id` on an array emits an "Attempt to read property on array" warning and evaluates to `null`, which casts to `''` in the string concat — producing `author = ''`, coerced by MySQL to `author = 0`. The "Most Downloaded" box on an ungrouped series' page would filter to author id 0 (no real author has this id) instead of the series' actual author — effectively always empty, while the "Newest" box directly below it correctly shows the author's items.
- **Decision:** Not reproduced. Classified as a confirmed bug (inconsistent with the immediately adjacent, correctly-written "Newest" block, and with no other file in this audit ever using `$Group->author_id` in preference to an available `$Author->id`/`$Series->author_id`). The Wave 4 Laravel port uses the series' own author consistently for both sidebar widgets.
- **Impact on the migration:** A real, positive fix — ungrouped series' detail pages currently show an empty/wrong "Most Downloaded" box; the Laravel port shows real data there.
- **Test:** Done — `tests/Feature/Content/KhotabBrowsingControllersTest.php`, `'series show: IF-015 fix — an UNGROUPED series still shows its author's "Most Downloaded" items, not an empty result'`.

## IF-016: `khotab/day.php`'s page `<title>` references an `$Author` variable that is never defined on this page

- **Location:** `khotab/day.php:100`
- **Evidence:** `title($Author->prename . ' ' . $Author->name);` — `$Author` does not appear anywhere else in `day.php` (no `$w2adb->get_row(...)` assigns it, unlike every other `khotab/*.php` page that displays an author, e.g. `author.php:10`, `series.php:31`, `group.php:9`). This "browse by day" page has no author context at all (it lists items across all authors for a given date).
- **Consequence (Inferred, standard PHP undefined-variable/property behavior):** `$Author` is `null`, so `$Author->prename` and `$Author->name` each emit an "Attempt to read property on null" warning and evaluate to `''` — the page's `<title>`/heading renders blank instead of something like "المواد المنشورة بتاريخ ...". A cosmetic/SEO defect (blank browser tab title, blank on-page heading via `title()`), not a data-correctness one.
- **Decision:** Not reproduced. Classified as a confirmed bug (leftover from copy-pasting an author-context page template without removing the author-specific title call). The Wave 4 Laravel port titles this page using the date/content-type being browsed (mirroring the breadcrumb text already correctly built at `day.php:99`, `' المواد المنشورة بتاريخ ' . $mydate`), not a nonexistent author.
- **Impact on the migration:** Minor positive fix — real page title instead of a blank one on every "browse by day" page.
- **Test:** Done — `tests/Feature/Content/KhotabBrowsingControllersTest.php`, `'day: IF-016 fix — the page title reflects the browsed date, not a blank/undefined author'`.

## IF-017: `khotab/news.php`'s PDF-listing branch never sets `$ob->video`, so its "Most Downloaded" sidebar box hits the same MySQL string-to-int coercion bug as IF-014

- **Location:** `khotab/news.php:11-19` (branch), `khotab/news.php:118` (use)
- **Evidence:** The `if`/`elseif`/`else` at lines 7-19 sets `$ob->video = 1` for `op=video` (line 10) and `$ob->video = 0` for the `else` (audio) branch (line 18), but the `elseif ($op == 'pdf')` branch (lines 11-13) sets only `$title`/`$op_title` — `$ob->video` is left unset. Line 118, unconditional for all three ops: `topitems('hits', "vedio ='" . $ob->video . "'", "hits DESC", 5);`.
- **Consequence (Fact, same directly-confirmed mechanism as IF-014 — see that entry's updated Evidence for `topitems()`'s own shim/comment, `functions.php:992-1004`):** On the PDF "newest transcriptions" listing page, `$ob->video` is undefined → `''` in the concatenation → `vedio=''`, which `topitems()`'s own normalization shim explicitly rewrites to `vedio=0` ("audio") before running the query. The "Most Downloaded" sidebar box on the PDF listing page shows audio items rather than being scoped to PDF-flagged content (there is no direct `pdf`-column equivalent of this filter being applied at all, unlike `dump.php:76`'s correct `"(pdf > 0) AND hidden=0"`).
- **Decision:** Not reproduced. Classified as a confirmed bug, same root cause and same fix pattern as IF-014 (an unset/mistyped video-flag variable silently coerced by MySQL rather than filtering as intended). The Wave 4 Laravel port scopes this sidebar box on the PDF listing page by `pdf > 0`, matching `dump.php`'s already-correct pattern, instead of an accidental `vedio=0` filter.
- **Impact on the migration:** A real, positive fix — the PDF listing page's "Most Downloaded" box will show relevant (transcribed) content instead of unrelated audio items.
- **Test:** Done — `tests/Feature/Content/KhotabBrowsingControllersTest.php`, `'news: IF-017 fix — the pdf op's "Most Downloaded" sidebar is scoped to pdf content, not coerced to audio'`.

## IF-018: `khotab/search.php`'s advanced-search results link every matched item's author using a column that was never selected — every "الداعية" (author) link in search results is broken

- **Location:** `khotab/search.php:471-509` (query), `khotab/search.php:568` (use)
- **Evidence:** `ListSearchKhotab()`'s `SELECT` list (lines 474-486, both the admin and public branches) selects `tb1.id, tb1.title, tb1.author, tb1.hits, tb1.time, tb1.weight, tb1.vedio, tb1.channel_id, tb1.mirror, tb1.hits, tb1.hidden, tb2.name, tb2.prename` — the author id comes back as `$Khotab->author` (line 476/487), never `author_id`. Line 568 nonetheless builds the author link as `'khotab-video-'.$Khotab->author_id.'.htm'`. By contrast, the sibling function `ListSearchSeries()` in the same file *does* select `tb1.author_id` explicitly (line 297) and correctly uses `$Series->author_id` at line 377 — the two functions were not written consistently.
- **Consequence (Inferred, standard PHP undefined-property behavior):** `$Khotab->author_id` is undefined on every row returned by `ListSearchKhotab()`, producing a link of the literal form `khotab-video-.htm` (empty id segment) for every item in advanced-search results — a broken/dead link on every single search result, not an edge case.
- **Decision:** Not reproduced. Classified as a confirmed bug (an inconsistency between two near-identical sibling functions in the same file, one correct and one not). The Wave 4 Laravel port's khotab-item search results link using the already-selected author id (`author`), matching `ListSearchSeries`'s correct pattern.
- **Impact on the migration:** A real, positive fix — every advanced-search result's author link currently 404s; the Laravel port's will work.
- **Reachability note (Fact, exhaustive `.htaccess` search):** `search.php` itself is orphaned — no rule maps to `khotab/search.php`, and no rule matches `video-advanced-search.htm` either (the breadcrumb/AJAX URL the file references for itself). `advanced_search.htm` (no `video-` prefix) exists as a rule but points at an entirely different script (`new_modules.php?name=advanced_search&op=main_search`), not this file. Same "raw-path-only" profile as `khotab/dump.php`/`live-stream/live.php` — fixing IF-018 is still correct regardless, but whether this page is reachable by real users at all is an open question, not confirmed here.
- **Test:** Done — `tests/Feature/Content/KhotabSearchControllerTest.php`, asserts a search result's author link contains the correct author id.

---

## IF-019: `khotab/item.php`'s comment flag images are missing the `images/` path segment every other flag reference uses — broken image on every comment

- **Location:** `khotab/item.php:395`
- **Evidence:** `<img src="<?php echo $siteurl;?>flags/<?php echo $comment->code;?>.png" ...>` — no `images/` segment. Every other flag reference in the audited codebase (root `functions.php`'s `listcomments()`, per 00-database-schema.md's `nuke_islamic_comments` migration note) uses `images/flags/<?php echo $item->code;?>.png`. Confirmed on disk: `images/flags/` exists (25 country-code `.png` files observed, e.g. `ad.png`, `ae.png`); a top-level `flags/` directory does **not** exist anywhere in the codebase.
- **Consequence (Fact — directly confirmed by filesystem inspection, not inferred):** Every comment-flag `<img>` on a khotab item's detail page 404s. A purely cosmetic defect (broken flag icon, comment text/author/date still render correctly) — not a data-correctness issue, but a real, visible one on every single comment shown.
- **Decision:** Not reproduced. Classified as a confirmed bug (an isolated path typo, inconsistent with the one other confirmed flag-rendering call site in the codebase). The Wave 4 Laravel port renders comment flags from `images/flags/{code}.png`, matching the correct, already-established convention.
- **Impact on the migration:** Minor positive fix — comment flags will actually render.
- **Test:** Done — `tests/Feature/Content/KhotabItemControllerTest.php`, `'show: IF-019 fix — comment flags render from images/flags/, not flags/'`.

---

## IF-020: `khotab/item.php`'s "PDF transcript" download button links to a URL with no matching `.htaccess` rule at all — broken on every item that has a PDF

- **Location:** `khotab/item.php:210` (link generation), `.htaccess` (absence)
- **Evidence:** `item.php:210` builds the PDF-download button as `href="khotab-item-pdf-<?php echo $Khotab->id;?>.htm"`. Grepping `.htaccess` for every `khotab-*pdf*` and `*pdf*khotab*` rule finds exactly 4 matches, none of which is `khotab-item-pdf-`: `khotab-pdf.htm` → `khotab/authors.php?op=pdf` (listing), `khotab-pdf_news.htm` → `khotab/news.php?op=pdf` (listing), `khotab-pdf-([0-9]*).htm` → `khotab/author.php?op=pdf&id=$1` (per-author listing, line 206), and a second, unreachable `khotab-pdf-([0-9]*).htm` rule → `new_modules.php?op=getpdf&khid=$1` (line 234 — dead, shadowed by line 206's identical pattern appearing first with `[L]`). `khotab/functions.php`'s own `download_khotab_pdf($id)` (the function actually wired to `item.php`'s `op=getpdf` branch, confirmed working via `khotab-download-` and its own single-item redirect logic) is never reachable from this specific button at all.
- **Consequence (Fact — confirmed by exhaustive `.htaccess` search, not inferred):** Every khotab item with `pdf != 0` renders a "ملف تفريغ" (PDF transcript) button that 404s. This is a distinct bug from IF-014/IF-017/IF-018/IF-019 — not a naming mismatch or coercion, a URL with genuinely no server-side route at all.
- **Decision:** Not reproduced. Classified as a confirmed bug. The Wave 4 Laravel port's PDF-download link uses the working `op=getpdf` path (already implemented as `KhotabItemController::downloadPdf()`, backing `download_khotab_pdf()`'s logic), not the dead `khotab-item-pdf-{id}.htm` pattern.
- **Impact on the migration:** A real, positive fix — the PDF download button will work for the first time.
- **Test:** Done — `tests/Feature/Content/KhotabItemControllerTest.php`, `'show: IF-020 fix — the PDF link resolves to a real route'` (asserts both the rendered link and that hitting it redirects to the actual PDF path).

---

## IF-021: `khotab/author.php`'s PDF-listing page has the same unset-`$ob->video` sidebar bug as IF-017, this time combined with an author filter

- **Location:** `khotab/author.php:11-25` (branch), `khotab/author.php:154,163` (use)
- **Evidence:** Same shape as IF-017: the `if`/`elseif`/`else` sets `$ob->video` for `op=video`/`op=audio` only; the `pdf` (`else`) branch (lines 21-25) never sets it. Lines 154/163's sidebar boxes run unconditionally for all three ops: `topitems('hits', "author = '". $ob->author_id . "' AND vedio ='" . $ob->video . "'", ...)`.
- **Consequence (Fact, same directly-confirmed `topitems()` shim as IF-014/IF-017):** On an author's PDF-listing page, `vedio=''` → the shim's `vedio=0` — the sidebar shows this author's **audio** items instead of being scoped to their transcribed (PDF) content, which is what the page is actually about.
- **Decision:** Not reproduced. Same fix pattern as IF-017: the Wave 4 port scopes this page's sidebar boxes to this author's `pdf > 0` items instead.
- **Impact on the migration:** Minor positive fix, same shape as IF-017 but on a different page.
- **Test:** Done — `tests/Feature/Content/KhotabBrowsingControllersTest.php`, `'author show: IF-021 fix — the pdf op's sidebar is scoped to this author's pdf items, not coerced to audio'`.

---

## IF-022: `khotab/day.php` never reads the `d`/`m`/`y` date parameters its own `.htaccess` rules pass it — every dated "browse by day" link shows today's items instead

- **Location:** `khotab/day.php` (whole file — confirmed absence), `.htaccess:192,194,219`
- **Evidence:** `.htaccess` maps `khotab-videodate-{d}-{m}-{y}.htm` and `khotab-audiodate-{d}-{m}-{y}.htm` to `khotab/day.php?op=Day&video=1|0&d=$1&m=$2&y=$3` (Fact). `day.php` itself only ever reads `$_GET['video']` and `$_POST['date']` (Fact, confirmed by a full read of the file) — `$_GET['d']`/`$_GET['m']`/`$_GET['y']` are never referenced anywhere in it.
- **Consequence (Fact — confirmed by the complete absence of any read of these 3 parameters, not inferred):** Since these URLs are plain `GET` requests with no POST body, `$_POST['date']` is always empty, so `day.php:91-94`'s fallback fires every time: `$date = date("Y").'-'.date("m").'-'.date("d")` — "today," regardless of what date the URL actually named. Any calendar/archive navigation link using this URL pattern is effectively dead — it always redisplays today's items.
- **Decision:** Not reproduced. Classified as a confirmed bug (a genuinely unwired route parameter, not a deliberate simplification). The Wave 4 Laravel port's day-browsing route reads the date from its own route parameters.
- **Impact on the migration:** A real fix, if this URL pattern is actually linked to anywhere in the current site (not confirmed in this pass — the "Business Confirmation" question of whether this feature is reachable/used is left open, consistent with how similarly-orphaned features have been handled elsewhere in the audit).
- **Test:** Done — `tests/Feature/Content/KhotabBrowsingControllersTest.php`, `'day: IF-022 fix — a dated URL scopes the main list to that date's items, not today's'`.

---

## IF-023: `khotab/search.php`'s page title references an undefined `$Author`, same shape as IF-016 but a different file

- **Location:** `khotab/search.php:97`
- **Evidence:** `title($Author->prename . ' ' . $Author->name);` — `$Author` is never assigned anywhere in `search.php` (confirmed by a full read of the file); this page has no single-author context (it's a sitewide advanced search).
- **Consequence (Inferred, same standard PHP undefined-variable behavior as IF-016):** Blank page title/heading.
- **Decision:** Not reproduced. Same fix pattern as IF-016: title reflects what the page actually is ("البحث المتقدم في المرئيات," legacy's own `$title` variable, already correct and unused for the actual `<title>` call).
- **Impact on the migration:** Minor positive fix, same shape as IF-016, independently found in a second file — reinforces that this specific copy-paste mistake (an author-context title call left in a non-author page) recurred at least twice, not once.
- **Test:** Done — `tests/Feature/Content/KhotabSearchControllerTest.php`, asserts the page title is the search page's own title, not blank.

---

## IF-024: `khotab/search.php`'s "title must be ≥4 characters" validation applies even when no title was entered — channel/author/date-only searches are always rejected

- **Location:** `khotab/search.php:180-189`
- **Evidence:** Two sequential checks: (1) `if(trim($_POST["kh_title"])=='' && $_POST["kh_channel"]==0 && $_POST["kh_author_name"]==0 && trim($_POST["kh_to"])=='' && trim($_POST["kh_from"])=='')` — "you must search by at least one criterion," correctly requiring *any* filter, not specifically title. (2) Immediately after, unconditionally: `if(strlen($_POST["kh_title"]) < 4)` — "you must enter at least 4 characters" — with no guard on whether title is actually the filter being used. An empty string has `strlen() === 0`, which is always `< 4`.
- **Consequence (Fact — directly derivable from the two conditions' logic, not inferred):** Any search that supplies only a channel, only an author, or only a date range (title left blank) passes check (1) — a channel/author/date filter is present — but always fails check (2), since the blank title has length 0. The result: this page can *only* ever return results when a title of ≥4 characters is provided. Channel-only, author-only, and date-only searches are unconditionally blocked, even though `ListSearchSeries()`/`ListSearchKhotab()` themselves build these filters correctly and would return correct results if the validation let the query run.
- **Decision:** Not reproduced. Classified as a confirmed bug (check (2) doesn't match its own error message's intent, and directly undermines check (1), which already correctly allows any single criterion). The Wave 4 Laravel port applies the length check only when a title was actually supplied, matching the two checks' evident combined intent — a value provided.
- **Impact on the migration:** A significant, positive fix — the advanced search feature becomes substantially more usable (channel/author/date searches now actually work) rather than a silent behavior change users would need to be told about defensively.
- **Test:** Done — `tests/Feature/Content/KhotabSearchControllerTest.php`, asserts a channel-only search (no title) returns results instead of being rejected.

---

## IF-025: `w2acd/cds.php`'s group-id argument is overwritten by an assignment-in-argument typo — every CD group page silently shows group 0's listing, and increments group 0's hit count

- **Location:** `w2acd/cds.php:27,36-37`
- **Evidence (already fully diagnosed by the pre-implementation audit — `modules/w2acd.md` §5, not re-derived here, only carried forward into implementation now that Wave 4 actually reaches this module):** `$id = (!empty($_GET['id'])) ? intval($_GET['id']) : 0;` (line 27) correctly resolves the requested group from the URL. Two lines later, `ListW2ACD($id=0, $page);` — PHP evaluates the assignment expression `$id=0` before passing it as the argument, which both passes `0` to `ListW2ACD()` **and** overwrites the caller's `$id` to `0` as a side effect. Line 37's hit-counter (`update nuke_w2acd_groups set hits=hits+1 WHERE id=$id`) then also uses this now-zeroed `$id`.
- **Why confirmed bug, not legacy behavior:** A single assignment-in-argument typo (`$id=0` where `$id` was clearly meant), not a deliberate design choice — every other page in this audit that reads a group/item id from `$_GET` uses it as read, not immediately overwritten. The audit's own w2acd.md flagged this as a genuine open product question ("does the business know group-filtering is broken?") rather than assuming intent — but the same technical-correctness-tier classification already applied consistently elsewhere in this implementation (IF-014, `LiveStreamController`'s 404-vs-`die()`, `AdminGuard`'s missing-plaintext-fallback) applies here too: this is a typo with an unambiguous correct fix, not a business-behavior redesign requiring a separate decision.
- **Consequence (Fact, per the audit's own diagnosis):** Every CD group page renders the same `group_id=0` listing regardless of which group's URL was visited (group filtering is functionally disabled sitewide), and every visit's hit-count increments `nuke_w2acd_groups` row `id=0`, not the group actually viewed — that column's stored values don't reflect real per-group traffic today.
- **Decision:** Not reproduced. The Wave 4 Laravel port passes the actually-resolved group id to its listing/hit-counting logic.
- **Impact on the migration:** A real, positive fix — CD group pages will actually filter by group for the first time. Existing `hits` data on `nuke_w2acd_groups` should not be trusted as real per-group traffic when/if ported, per the audit's own migration note.
- **Test:** Done — `tests/Feature/Content/W2acdControllerTest.php`, `'index: IF-025 fix — a specific group's page only lists that group's items'` and `'...visiting a group increments THAT group's hits, not group 0'`.

---

## IF-026: 130 of the site's ~217 `.htaccess` pretty-URL rules route to `new_modules.php`, a file that does not exist anywhere in this codebase

- **Location:** `.htaccess` (site root), sitewide — not module-specific.
- **Evidence:** While tracing `w2acd`'s real routing (every `cds-*.htm` rule resolves to `new_modules.php?name=w2acd&op=...`, none to `w2acd/cds.php`/`w2acd/item.php` — the files this session had just finished reading), an exhaustive check found `new_modules.php` absent from the entire working tree (`find` from the site root, zero matches, in the root directory and everywhere else). A broader count: `grep -c "new_modules.php" .htaccess` → 130 matches, spanning `name=Mobile` (23), `name=Fatwa` (23), `name=Khotab` (18, including the already-known-dead duplicate mirror/pdf rules), `name=RSS` (15), `name=Surveys` (9), `name=Satellite`/`name=Locations` (6 each), `name=Feedback` (5), `name=Chat`/`name=Telawah`/others (fewer each) — effectively every module's RSS feeds, and large parts of `Mobile`/`Fatwa`/`Surveys`/`Satellite`/`Locations`/`Feedback`/`Chat` routing.
- **Why this is NOT classified as a confirmed bug (unlike every other finding in this log):** This working copy is a snapshot (the same caveat already applies to the `images/`/`media/` asset directories earlier in the audit) — `new_modules.php` may exist on the live production server and simply not be part of this snapshot (e.g. deployed separately, excluded from whatever process produced this copy). Concluding "130 rules are dead in production" from a missing file in a snapshot would be an unverified guess presented as fact, exactly what the evidence policy prohibits. This is recorded as a **confirmed fact about this snapshot** (the file is absent here) with an **explicitly open question about production** — not a bug classification.
- **Practical impact on Wave 4 work:** For any module whose real (or only) `.htaccess`-registered pretty URLs route through `new_modules.php` (confirmed so far: `w2acd`'s `cds-*.htm` set; also affects `khotab`'s already-known dead duplicate mirror/pdf rules, IF-020's evidence), this session's controllers/routes are registered at the module's raw legacy path instead (e.g. `w2acd/cds.php`, matching the already-established `khotab/dump.php`/`khotab/search.php` raw-path-only pattern), since that is the only path this session can actually confirm is servable. The pretty `cds-*.htm`-style URLs are deliberately NOT wired up as legacy-compat redirects yet — redirecting to a URL shape that may never have actually worked would be inventing a compatibility guarantee this audit can't back up.
- **Recommendation:** A genuine Business Confirmation candidate — ask whether `new_modules.php` exists on the live server (and if so, get a copy for the audit) before finalizing `00-url-inventory.md`'s treatment of these 130 rules or building `LegacyUrlCompatibility` entries for the modules they cover. If it turns out not to exist in production either, roughly 60% of the site's pretty-URL surface has been silently broken for an unknown period — worth surfacing to the business either way, not something to quietly work around.
- **Test:** Not applicable — this is a routing/reachability fact, not a behavior to regression-test.
- **Addendum (post-Wave-4 cross-wave review, `docs/reviews/post-wave-4-cross-wave-architecture-review.md` §4):** this was written up above as if freshly discovered. It wasn't, fully — `00-master-migration-blueprint.md` Part I's Confirmed Facts table already states "`fatawa/` and `advanced-search/`'s primary public routing is confirmed unreachable via this codebase's own dispatcher (missing `new_modules.php`)," predating this entry. This finding's real contribution is scope: Part I named 2 affected modules, this entry's `.htaccess` grep confirms 130 rules across at least 10 modules. Should have been written as "narrows/expands an existing Confirmed Fact," not a new discovery — recorded here as a correction, not a rewrite of the entry above.


---

## IF-027: `gallery` module — two pre-audited defects carried forward into implementation (dead `@order` sort, hardcoded absolute filesystem path)

- **Location:** `gallery/functions.php:6` (`get_albums()`), `gallery/functions.php:41` (`download_album_img()`).
- **Evidence (already fully diagnosed by the pre-implementation audit — `modules/gallery.md` §5 — not re-derived, only applied now that Wave 4 reaches this module):**
  1. `get_albums()`'s `ORDER BY CASE WHEN @order > 0 THEN 'order' END ASC, CASE WHEN @order = 0 THEN 'last_update' END DESC` references a MySQL session variable `@order` that is never set anywhere in this query or the surrounding code — an uninitialized user variable is `NULL` in MySQL, so neither `CASE` branch ever actually fires; the `order` column's real data values exist but never drive display order.
  2. `download_album_img()` builds the download path as `'/home2/w2acp/public_html/' . $image_obj->url` — a hardcoded absolute filesystem path tied to one specific old server (P-017), not a portable relative reference.
- **Why confirmed bug, not legacy behavior:** Both are already-classified, unambiguous technical defects in the source audit, not business decisions — a dead sort clause and a non-portable hardcoded path.
- **Decision:** Not reproduced. The Wave 4 port orders albums by `album_id` (the closest honest approximation of "no effective sort is actually applied" — see `GalleryController`'s docblock for the reasoning) and resolves image downloads from the stored `url` column as a relative path under the app's own storage, not a hardcoded legacy server path.
- **Impact on the migration:** Neutral-to-positive — the dead sort clause has no current user-visible effect to preserve (removing it changes nothing observable), and the path fix is required for the download feature to work at all outside the original legacy server.
- **Test:** Done — `tests/Feature/Content/GalleryControllerTest.php`, asserts album download resolves from the app's own storage disk, not a hardcoded external path.

---

## IF-028: `anasheed/functions.php`'s comment flags have the same missing `images/` path segment as IF-019 — a third confirmed occurrence of the same typo

- **Location:** `anasheed/functions.php:819` (`list_anasheed_comments()`).
- **Evidence:** `<img src="<?php echo $siteurl;?>flags/<?php echo $comment->code;?>.png" ...>` — identical to IF-019's `khotab/item.php:395`, byte-for-byte the same missing `images/` segment. Given `khotab/item.php` and `anasheed/functions.php` are different files maintained separately, this is very likely the same code having been copy-pasted between modules with the bug intact both times, not independently made twice.
- **Why confirmed bug, not legacy behavior:** Same reasoning as IF-019 — `images/flags/` is confirmed on disk; a bare `flags/` is not.
- **Decision:** Not reproduced — same fix as IF-019 (`images/flags/{code}.png`).
- **Also confirmed while reading (already known — `modules/anasheed.md` §5, carried forward, not re-derived):** `anasheed/item.php`'s queries never filter on `hidden` at all, unlike `khotab/item.php`'s enforcement of the same column — reproduced as found (not filtered), matching the audit's own note that this needs a product-owner confirmation, not a silent fix, since it's a genuine behavioral gap between two structurally similar modules rather than an unambiguous typo.
- **Impact on the migration:** Minor positive fix (flags render), same as IF-019.
- **Test:** Done — `tests/Feature/Content/AnasheedItemControllerTest.php`, asserts the rendered flag path contains `images/flags/`.

---

## IF-029: `vars/more.php` calls an undefined function (`listanasheed()`) — a fatal error on every request to 4 confirmed-live `.htaccess` routes

- **Location:** `vars/more.php:25,39`.
- **Evidence:** `more.php` calls `listanasheed($cat->id, $arr)` twice (a "pinned/fixed" listing and a "newest" listing, scoped to one `nuke_anasheed_groups` row). No function named `listanasheed` (no underscore) exists anywhere in the codebase — confirmed by an exhaustive `grep -rn "function listanasheed"` across every `.php` file. The real, actually-defined function with equivalent behavior is `anasheed/functions.php`'s `list_anasheed($Group, $args=[])` (with an underscore) — a different identifier, not a case-sensitivity false alarm (PHP function names are case-insensitive, but `listanasheed` and `list_anasheed` differ by more than case). `more.php` itself never includes `anasheed/functions.php` at all (only root `functions.php`), so even the correctly-named function wouldn't be in scope if the typo were fixed in place.
- **Reachability (Fact, resolves the apparent Blueprint §7 contradiction flagged in `docs/reviews/post-wave-4-cross-wave-architecture-review.md` §4):** `.htaccess` maps exactly 4 real, live routes to `vars/more.php`: `exclusive-news.htm` (id=158), `cartoon-news.htm` (id=57), `documentary-news.htm` (id=12), `anasheed-news.htm` (id=98). No other `vars/*.php` file (`index.php`, `var.php`, `group.php`, `download.php`, `getright.php`, `old.php`) has any `.htaccess` rule at all. This confirms Blueprint §7's two rows were never actually contradictory: "`vars`, `cds`: Excluded — confirmed dead/superseded" correctly describes every file except `more.php`; "`anasheed` absorbs `vars/`'s one live capability (4 themed routes)" correctly describes `more.php`'s 4 routes specifically. Both are true simultaneously — the cross-wave review's "internal contradiction" finding is superseded by this entry; recorded as a correction, not by editing that review document.
- **Why confirmed bug, not legacy behavior:** An undefined-function fatal error has no observable "behavior" to preserve — every request to all 4 routes currently 500s.
- **Also newly confirmed:** group id 98 (`anasheed-news.htm`'s target) is the same magic group id already found hardcoded in `anasheed/functions.php`'s `list_anasheed()` (`$parentString = ($Group->id!='98') ? ... : "(group_id='98' OR group_id='16')"`, noted but unexplained in `AnasheedGroupController`'s own docblock, Wave 4). This resolves that mystery: group 98 is specifically the "anasheed-news" themed aggregation, and the `OR group_id='16'` clause is that theme's own business rule, not an unrelated coincidence.
- **Decision:** Not reproduced. The Wave 5 Laravel port serves these 4 routes using `list_anasheed()`'s real, correctly-defined behavior (already ported as `ContentListingService`/`AnasheedGroupController` logic in Wave 4) — a "pinned" list (`fixed=1`, limit 100) and a "newest" list (limit 32), both scoped to the route's hardcoded group id, matching `more.php`'s actual (if currently broken) intent.
- **Impact on the migration:** A real, positive fix — 4 previously-500ing public pages become real pages for the first time.
- **Test:** Done — `tests/Feature/Content/AnasheedNewsControllerTest.php`, all 4 themed routes render, the group-98/16 special case, and the pinned/newest split.

---

## IF-030: `pages/social.php` — a standing header-nav link 404s (no `.htaccess` rule ever existed for `social.htm`), and every image on the page is broken (wrong asset directory)

- **Location:** `header.php:340,347` (link source); `pages/social.php` (target, image paths throughout).
- **Evidence (routing):** exhaustive `grep -n "social" .htaccess` returns zero matches — no rewrite rule maps `social.htm` to `pages/social.php` or anywhere else. Yet `header.php`'s account dropdown menu links to `href="social.htm"` twice (line 340: main toggle; line 347: menu item, label "تابعنا" — "Follow us"), in permanent site navigation present on every page, not a stray/orphaned link. A visitor clicking this standing menu item gets a 404 today.
- **Evidence (image path):** every one of the ~30 social-link entries renders `<?= $image_url?>media/social-images/<?= $page['image']?>`. `media/social-images/` does not exist (confirmed: `ls` fails). The real assets are at `pages/social-images/` (confirmed: `ls` succeeds, 30 files present, one per entry). Every social-platform icon on this page is currently a broken image in production, independent of the routing bug.
- **Why confirmed bug, not legacy behavior:** a 404 on a standing nav link and a directory that doesn't exist have no observable "behavior" worth preserving — both are unambiguous defects, not design choices.
- **Decision:** Not reproduced. The Laravel port registers a real route at the exact path the nav already expects (`/social.htm`, kept at its legacy path rather than given a new clean path + redirect — see `routes/pages.php`'s comment for why this differs from this task's Wave-2 siblings), and references the fixed asset path at `/pages/social-images/...` — the exact path Blueprint §12 names as the fix target ("same relative paths preserved").
- **Addendum (post-gap-closure consistency review, Finding 1):** the image path was initially implemented as `/images/social-images/...` instead — an over-generalization from `khotab/item.blade.php`'s `/images/flags/` (a real subfolder of `images/`; `pages/social-images/` never was one). Caught during the follow-up consistency review, not by this entry's own test at the time (`SocialPageTest.php`'s original assertion validated the code against itself, not against Blueprint §12's stated value). Corrected in `resources/views/pages/social.blade.php`, `SocialController.php`, and the test's assertion.
- **Impact on the migration:** A real, positive fix — a permanently-linked page becomes reachable for the first time, and its icons render for the first time.
- **Test:** Done — `tests/Feature/Pages/SocialPageTest.php`: page renders at `/social.htm` with real content, image paths use the fixed, Blueprint-correct directory, raw `pages/social.php` path redirects to `/social.htm`.

---

## IF-031: `gap-closure-action-plan.md`'s `vars_categories/` redirect proposal assumed 2 Laravel routes that don't actually exist yet

- **Location:** `docs/reviews/gap-closure-action-plan.md` §1 (item 1's proposed redirect set); `routes/content.php` (actual current route state).
- **Evidence:** the approved action plan proposed 3 redirects: `vars-category-{id}.htm` → `category-{id}.htm`, `vars-categories.htm` → `categories.htm`, and `vars-category-series-{id}-{id2}.htm` → `category-series-{id}-{id2}.htm` (the third already flagged in the plan itself as blocked). Checking `routes/content.php` directly during implementation: only `category-{id}.htm` (`CategoryController::show`) is actually routed. `categories.htm` (the tree/index) and `category-series-{id}-{id2}.htm` are both still explicitly deferred — per `CategoryController`'s own docblock, neither has ever been built. The plan's assumption that `categories.htm` was already live was wrong; only 1 of the 3 proposed redirects has a real destination to point to today.
- **Why this matters:** redirecting to a route that doesn't exist would produce a `RouteNotFoundException` (or, worse, a silently-swallowed broken URL) — trading one broken path for another, not a fix. Per this project's standing evidence-first rule, the mismatch was verified against the actual route table rather than assumed correct because the proposal said so.
- **Decision:** implemented only the 1 buildable redirect (`vars-category-{id}.htm` → `category-{id}.htm`, `routes/content.php`). The other 2 remain unbuilt, tracked the same way `categories.htm`/`category-series-{id}-{id2}.htm` themselves already are ("deferred, not silently dropped") — they become buildable the moment those 2 pre-existing deferred routes are.
- **Impact on the migration:** no functional regression (both blocked `vars-*` routes are, today, still served correctly — if duplicately — by the still-running legacy app, since nothing has cut them over). Documents a real gap between an approved planning document and the codebase's actual current state, worth a finding in its own right, not just a quiet correction.
- **Test:** Done — `tests/Feature/Content/CategoryControllerTest.php`, the one buildable redirect.

---

## IF-032: `radio/`'s 3 personalized-playlist op-routes are live URLs that silently do nothing — the code that implements them is unreachable

- **Location:** `.htaccess` (route source); `radio/index.php` (actual routed-to file); `radio/indexXX.php` (the file that implements the ops, unreachable); `radio/functions.php` (the op implementations themselves).
- **Evidence:** all 5 `.htaccess` rules for this module route to `radio/index.php` — including the 3 op-named ones (`remove-playlist-item-{id}-{section}.htm?op=remove-playlist-item`, `playlist-item-{id}.htm?op=playlist-item`, `save-last-listen.htm?op=save-last-listen`). `radio/index.php`, read in full, contains no `$_GET['op']` handling anywhere — it unconditionally renders one static page regardless of query parameters. `radio/functions.php`'s `delete_playlist_item()`/`get_playlist_item()`/`save_last_listen()` — the real, complete implementations of exactly these 3 ops, including a per-user `nuke_radio_playlists` table — are only ever called from `radio/indexXX.php`, a second, more feature-rich entry point with its own `op=` dispatch (read in full, confirmed). No `.htaccess` rule, nor any other file in the codebase (grepped), references `indexXX.php`. It is unreachable.
- **Why confirmed dead, not merely unbuilt:** this isn't a missing feature — it's a real, complete, working feature (login-aware playlist add/remove/resume) that the routing table simply never connects to. The 3 op-routes are live, 200-OK URLs in production today that silently ignore their own parameters and re-render the full radio page instead.
- **Decision:** Not reproduced. `RadioController` (task 4.10) ports `radio/index.php`'s actual page only. No route is registered for the 3 op-paths — a `RouteNotFoundException`/404 for those exact paths is the honest current state, not a regression, since they never did anything in the first place.
- **Impact on the migration:** Narrows `radio/`'s migration scope from what `gap-closure-action-plan.md`'s initial estimate assumed (see that document's own item 2, which already flagged this exact finding when it was made) — confirms Small effort was correct, and confirms no personalized-playlist backend needs building. Recommended, non-blocking: flag to the business that this feature does not currently work in production, in case a rebuild is wanted.
- **Test:** Done — `tests/Feature/Content/RadioControllerTest.php`, asserts the 3 op-paths are not reproduced as working routes.

---

## IF-033: `chat_room`'s weekly-lesson-schedule feature belongs with the live-room half (task 6.5), not the content-browsing half (task 4.11) — a scoping correction found while implementing

- **Location:** `chat_room/table.php`, `chat_room/functions.php:91-195` (`list_today_lessons()`), `chat_room/functions.php:373` (`get_lessons_table()`); `docs/reviews/gap-closure-action-plan.md` item 4 (the original, incorrect scoping); `00-implementation-roadmap.md` task 4.11 (as first drafted).
- **Evidence:** the action plan's item 4, and this Roadmap's task 4.11 as first written, both listed a `nuke_hedaya_lessons` schedule model as part of the content-browsing half's scope. Reading `chat_room/table.php`/`list_today_lessons()` in full during implementation shows the opposite: this feature joins `nuke_hedaya_lessons` against `$chatdb`'s `room` table (`list_today_lessons()`, lines 110-117) and every row links to `chat_{room_id}.htm` — the LIVE voice-room page, not a recorded-lesson page. It has no relationship to `chat_author_{id}.htm`/`chat_lesson_{id}.htm` at all; nothing in `author.php`/`lesson.php` calls it.
- **Why this matters:** the schedule feature is meaningless without knowing whether the live rooms it schedules attendance for are still real (the same open question already gating task 6.5, Business Confirmation #4). Building it under task 4.11 would have meant building real scope ahead of that confirmation — exactly what Blueprint Appendix F warns against for confirmation-gated work (already quoted in the Roadmap's own Wave 6 Risks section).
- **Decision:** NOT built under task 4.11. `ChatRoomLessonController`/task 4.11 covers only `author.php`/`lesson.php`'s browse/detail/download flow. The schedule feature's scope is transferred to task 6.5's notes, to be picked up only once Business Confirmation #4 resolves, alongside the rest of the live-room half.
- **Impact on the migration:** narrows task 4.11's actual scope (no `HedayaLesson` model, no schedule view) — the "Medium effort" estimate from the action plan still holds without it (the removed scope and the discovered author/group/series/item complexity roughly offset). `00-implementation-roadmap.md` task 4.11 and `gap-closure-action-plan.md` item 4 both updated with a visible correction note rather than silently rewritten.
- **Test:** N/A (a scoping decision, not a code path — the schedule feature has no route or controller in this pass to test).

---

## IF-034: `admincp/chat/edit_room.php` has no backend for its own edit form or its owner/speaker delete links — `admincp.md`'s "Editing is functional" classification for this file does not hold

- **Location:** `admincp/chat/edit_room.php` (396 lines, read in full during Wave 5 task 5.5 implementation).
- **Evidence:** the file renders a complete-looking room-edit form (name, open/closed status, welcome message, password, capacity, comment, 4 feature checkboxes) and, in its owner/speaker tables, real-looking delete links (`edit_room.php?op=delowner&roomid=...&userid=...`, `?op=delspeaker&...`). A full read of the file, and a targeted `grep -n "_POST\|op.*delowner\|op.*delspeaker"`, finds **zero** `$_POST` handling anywhere and **zero** `$_GET['op']` branching for `delowner`/`delspeaker` — only the two link `href`s exist, pointing at ops the file itself never checks for. This is a genuinely different shape from this module's other confirmed-dead flows (`authors/index.php`'s `die('hhhh')`, `locations/add.php`'s commented-out INSERT) — there is no dead code marking the intent, just an absence of any backend at all.
- **Why this contradicts the existing audit:** `admincp.md` §2/§5/§7/§9 repeatedly classifies `chat/index.php` + `edit_room.php` together as "work and are the correct, original source" / "Editing is functional." That classification holds for `chat/index.php` (a plain SELECT + list, confirmed) but not for `edit_room.php` — this was not caught in the original audit pass.
- **Decision:** Not reproduced. `ChatRoomAdminController::update()`/`removeOwner()`/`removeSpeaker()` (task 5.5) are real, working implementations built fresh, per ADR-0010 — there was nothing functional to port.
- **Impact on the migration:** none on scope or sequencing — task 5.5 already budgeted "rebuild the room editor" as part of its own plan; this just confirms that framing was necessary, not optional. `admincp.md` corrected with a visible addendum, not a silent rewrite (see below).
- **Test:** Done — `tests/Feature/Admin/ChatRoomAdminControllerTest.php`, proves both the room-update and owner-removal actions actually persist.

---

## IF-035: `chat_room/`'s live-room half has a real, page-level enforcement use of `nuke_authors.permissions` — the permissions blob is not sidebar-only sitewide, only within `admincp/`

- **Location:** `chat_room/chat_room.php:19-38`, `chat_room/functions.php:20-27` (`list_chat_rooms()`); already documented, before Wave 5 began, in `docs/migration/modules/chat_room.md` §3 (Dependencies) — cross-referenced here for the first time.
- **Evidence:** `chat_room/chat_room.php` (a public, non-admincp page — viewing a single live chat room) and `chat_room/functions.php`'s `list_chat_rooms()` (the public room-list page) both unserialize `$_SESSION['w2a']->admin->permissions` and check `in_array('chat', array_keys($current_user_permisions))`. A visitor whose session is *also* linked to an admin account holding **any** `chat`-module permission key can view/enter **disabled** rooms and see them in the room list; every other visitor is restricted to `enable=1` rooms only. `chat_room/functions.php:23`'s own comment states the business intent directly: *"this member is manager for chat rooms module."* This is real, confirmed, page-level authorization enforcement driven by the permissions blob — the same data structure Wave 5's verification review (`wave-5-verification-review.md` Finding 1) and decision-log #9/#10 characterized as enforcing nothing beyond `sidebar.php`'s nav-link visibility.
- **Why this contradicts the existing audit:** it doesn't contradict `admincp.md` or decision-log #9/#10's specific claim about `admincp/` — an exhaustive re-grep of every `admincp/` feature directory (Finding 1, and a broader pattern-agnostic follow-up grep across all of `admincp/` for the literal substring `permissions`) still confirms no `admincp/` page anywhere enforces this data. What it corrects is an over-generalization introduced while implementing this round's Blueprint clarification (`00-master-migration-blueprint.md` §9): a sentence stating the permissions blob "only ever controlled admin-panel nav-link visibility, never page-level access" with no scope qualifier, which is false sitewide — this exact counter-example was already sitting in `chat_room.md`'s own Dependencies section, described there as *"the first confirmed sub-role/permission-scoped check found in the codebase, distinct from the blanket admin/superadmin gate every other module uses,"* but was never cross-referenced against decision-log #9 when it was originally written, nor against this round's Blueprint edit until this independent re-verification pass caught it.
- **Decision:** No implementation change. This code path lives entirely in `chat_room/`'s **live voice-chat room** half (`chat_room.php`, `functions.php`'s room-list branch) — explicitly out of scope for both Wave 4 (task 4.11 covers only the recorded-lesson-browsing half, per that task's own docblock) and Wave 5 (Admin domain). It is deferred, unbuilt, gated on Business Confirmation #4, as Roadmap task 6.5. The Blueprint §9 sentence is corrected to read "...within `admincp/`, never page-level access" rather than an unqualified sitewide claim; decision-log #10's own Reason line gets the same narrowing.
- **Impact on the migration:** none on Wave 5's scope, sequencing, or its permission-hardening decision, which concerns `admincp/` specifically and remains correct. Real impact is on task 6.5, not yet started: whoever implements it must make an explicit, documented decision about this rule — reproduce it as-is (Spatie `chat.*`-permission-holders can see disabled rooms) or supersede/harmonize it with Wave 5's new `admin.permission:{module}.{key}` model — rather than silently dropping or silently porting it. Flagged directly on task 6.5's Roadmap entry.
- **Test:** N/A — no code was built or changed by this finding; `chat_room/`'s live-room half remains entirely unmigrated legacy PHP.

---

## Index by module

| Module | Findings |
|---|---|
| `khotab` | IF-001, IF-005, IF-006, IF-014, IF-015, IF-016, IF-017, IF-018, IF-019, IF-020, IF-021, IF-022, IF-023, IF-024 |
| `categories` | IF-006 |
| `w2acd` | IF-002, IF-003, IF-025 |
| `home_functions.php` / `functions.php` (shared-core) | IF-004 |
| `live-stream` | IF-007, IF-009, IF-010 |
| `pages` | IF-008, IF-030 |
| `channels` | IF-011, IF-012, IF-013 |
| `.htaccess` / routing (shared-core) | IF-026 |
| `gallery` | IF-027 |
| `anasheed` | IF-028 |
| `vars` | IF-029 |
| `vars_categories` / `categories` | IF-031 |
| `radio` | IF-032 |
| `chat_room` | IF-033, IF-035 |
| `admincp` (Wave 5) | IF-034 |

## Open items requiring escalation beyond this log

- **IF-026** is the highest-priority Business Confirmation candidate in this log — whether `new_modules.php` exists in production affects the reachability of roughly 60% of the site's pretty-URL surface across nearly every module, not just the one currently being implemented.
- **IF-004** should be added to Blueprint Part IV (Business Confirmations Required) — genuinely new scope, not covered when the Blueprint was frozen. **IF-013** strengthens this same item (a third divergent media-path convention, not a second).
- **IF-006** is a candidate for a similar addition once/if the by-author and by-category surfaces are confirmed to need to agree.
- **IF-012** is a candidate for a new business question: should the author-filtered channel page also show download/newest recommendations, matching the unfiltered page?

Neither of these has been added to `00-master-migration-blueprint.md` yet — per the Blueprint's own freeze rule, that requires an explicit decision to update it, not a silent edit triggered by this log.
