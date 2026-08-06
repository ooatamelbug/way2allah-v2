# Wave 4 Checkpoint Review — `khotab` Module

Scope: only the work completed since the last approval gate (post pre-Wave-4 decisions) — the `khotab` model graph, controllers, views, routes, and the 9 new implementation findings surfaced while reading the remaining legacy source files. No code changes are made in this document; it is a review only, per the same gate process used before Wave 4 began.

---

## 1. IF-014 through IF-022

All 9 findings share one shape: a legacy page reads an undefined or wrong PHP property/variable, PHP silently coerces it to an empty/null value, and that empty value either survives into a SQL filter (via MySQL's well-documented string→int coercion) or into a broken URL/asset path. None were reproduced in the port. Each has a passing regression test except IF-018 (its controller, `khotab/search.php`, is not yet built).

### IF-014 — item.php sidebar shows audio content on video pages

- **Evidence:** `item.php:467,476` reads `$Khotab->video` (undefined — no `video` column exists) instead of `$Khotab->vedio` (used correctly at lines 91, 126, 194, 197 in the same file). Independently corroborated at higher confidence than usual: `topitems()` (root `functions.php:992-1004`) contains a normalization shim added in prior-session performance work, with a code comment that names this exact bug and states it was observed at 180K+ calls/day in production query logs. This moved the finding's evidence tag from Inferred to **Fact** — not a reasoned inference about MySQL semantics, a directly documented fact.
- **Why confirmed bug, not legacy behavior:** No other file in the audit ever uses `video` as a khotab column name; every other reference in this same file uses the correct `vedio`. A typo, not a design choice.
- **Fix:** The two sidebar queries use the item's real `vedio` value.
- **Test:** `KhotabItemControllerTest.php` — a video item's sidebar shows video items and not audio (and the symmetric audio-item case).

### IF-015 — series.php's "Most Downloaded" box breaks for ungrouped series

- **Evidence:** `series.php:34-36` sets `$Group = array()` when a series has no group. Line 146 then reads `$Group->author_id` unconditionally — a property access on a plain array. The adjacent "Newest" box (line 155) correctly uses `$Author->id` instead.
- **Why confirmed bug:** Inconsistent with the immediately adjacent, correctly-written block in the same file; no other file in the audit prefers `$Group->author_id` when `$Author->id`/`$Series->author_id` is already available.
- **Fix:** Both sidebar boxes use the series' own `author_id` consistently.
- **Test:** `KhotabBrowsingControllersTest.php` — an ungrouped series still shows its author's "Most Downloaded" items instead of an empty result.

### IF-016 — day.php's page title references a variable that doesn't exist

- **Evidence:** `day.php:100` calls `title($Author->prename . ' ' . $Author->name)`, but `$Author` is never assigned anywhere in this file (unlike every other khotab page that has real author context).
- **Why confirmed bug:** Copy-paste leftover from an author-context page template; this page has no author at all (it lists items across all authors for one date).
- **Fix:** Page title built from the browsed date instead, mirroring the breadcrumb text legacy already builds correctly.
- **Test:** `KhotabBrowsingControllersTest.php` — title reflects the browsed date.

### IF-017 — news.php's PDF-listing sidebar coerced to audio

- **Evidence:** `news.php`'s op-branching sets `$ob->video` for `video`/`audio` ops only; the `pdf` branch never sets it. The sidebar box runs unconditionally for all three ops, reading the unset `$ob->video`.
- **Why confirmed bug:** Same directly-confirmed `topitems()` shim mechanism as IF-014 — an unset variable silently coerced by the shim to `vedio=0`, not an intended "default to audio" behavior.
- **Fix:** The PDF listing page's sidebar is scoped to `pdf > 0`, matching `dump.php`'s already-correct pattern for the same kind of page.
- **Test:** `KhotabBrowsingControllersTest.php` — pdf op's sidebar shows pdf content, not audio.

### IF-018 — search.php's author links are broken on every result (not yet fixed)

- **Evidence:** `ListSearchKhotab()`'s `SELECT` never includes `author_id` (only `author`), but line 568 builds the link from `$Khotab->author_id`. The sibling function `ListSearchSeries()` does select `author_id` and uses it correctly — the two functions disagree.
- **Why confirmed bug:** An inconsistency between two near-identical functions in the same file, one right and one wrong.
- **Fix:** Not yet implemented — `khotab/search.php` (advanced search) is still pending. The fix is understood (use the already-selected `author` value) but not built.
- **Test:** Not yet written — tracked as pending in the finding itself.

### IF-019 — item.php's comment flags are missing a path segment

- **Evidence:** `item.php:395` renders `{$siteurl}flags/{code}.png`; every other flag reference in the codebase uses `images/flags/{code}.png`. Confirmed on disk: `images/flags/` exists with real files; a top-level `flags/` does not exist anywhere.
- **Why confirmed bug:** Directly confirmed by filesystem inspection — not a guess about intent, a verifiable path that 404s.
- **Fix:** Comment flags render from `images/flags/{code}.png`.
- **Test:** `KhotabItemControllerTest.php` — asserts the correct path segment in the rendered flag `src`.

### IF-020 — item.php's PDF download button has no route at all

- **Evidence:** `item.php:210` links to `khotab-item-pdf-{id}.htm`. An exhaustive `.htaccess` search for every `khotab-*pdf*`/`*pdf*khotab*` pattern finds 4 rules, none matching this one (one is even a second, unreachable duplicate of another). The function that would actually serve this (`download_khotab_pdf()`) is wired to a different URL entirely.
- **Why confirmed bug:** Not a naming mismatch or coercion — a URL with genuinely no server-side route. Distinct in kind from IF-014/017/018/019.
- **Fix:** The route now exists at the exact path the button already generates, backed by `KhotabItemController::downloadPdf()`.
- **Test:** `KhotabItemControllerTest.php` — asserts both the rendered link and that requesting it redirects to the real PDF path.

### IF-021 — author.php's PDF-op sidebar has IF-017's same bug, author-scoped

- **Evidence:** Same shape as IF-017, in a different file: `author.php`'s pdf branch never sets `$ob->video`; the sidebar (author+vedio filtered) runs unconditionally for all ops.
- **Why confirmed bug:** Same mechanism, independently confirmed a second time in a different file — reinforces rather than merely repeats IF-017.
- **Fix:** This author's PDF-listing sidebar is scoped to `author + pdf > 0`.
- **Test:** `KhotabBrowsingControllersTest.php` — an author's pdf-op sidebar is scoped correctly, distinguishing it from a different author's pdf items and from this author's audio items.

### IF-022 — day.php's dated URLs are entirely dead

- **Evidence:** `.htaccess` maps `khotab-videodate-{d}-{m}-{y}.htm` (and the audio equivalent) to `day.php` with `d`/`m`/`y` query parameters. `day.php` itself never reads `$_GET['d']`, `$_GET['m']`, or `$_GET['y']` anywhere — confirmed by a full read of the file, not a sample.
- **Why confirmed bug:** Since these are `GET` requests with no POST body, `day.php`'s only date source (`$_POST['date']`) is always empty, so it silently falls back to "today" every time. Not a deliberate simplification — a genuinely unwired parameter.
- **Fix:** The Laravel route reads the date directly from its own route parameters.
- **Test:** `KhotabBrowsingControllersTest.php` — a dated URL scopes the main list to that date (verified by extracting the relevant HTML section, since the sidebar itself is correctly date-*unscoped* and would otherwise mask the assertion).
- **Note:** Whether this URL pattern is actually linked to anywhere on the live site (vs. purely orphaned) was not confirmed this pass — left as an open question, same treatment as other possibly-orphaned features found earlier in the audit.

---

## 2. The `KhotabGroup` Blueprint gap

**Why was it missing from the original Blueprint?** The Blueprint's aggregate table and ER diagram (§6) were written from the pre-implementation audit's survey-level understanding of `khotab`. `nuke_islamic_groups` is real — it has its own title/description/meta fields, its own detail page (`group.php`), its own admin CRUD, and both `nuke_islamic_series.group_id` and `nuke_islamic_khotab.group_id` reference it independently — but that depth of detail only became available once `khotab/group.php` and the group-handling parts of `khotab/functions.php` were read in full, which happened *after* the Blueprint was frozen. This is the same category of gap as `MediaPathResolver`'s placement (decision #1): the Blueprint's authors didn't have the evidence yet, not a place where they looked at this evidence and decided differently.

**Why a referenced model, not an aggregate root?** Blueprint §6 already has a standing rule: entities with no real invariants to protect are "plain Eloquent models... referenced, not owned," and names `Category`/`Channel` as examples of exactly this. `KhotabGroup` fits the same test — it's independently queried, listed, and linked to by URL (Series and KhotabItem reference it by id; it doesn't reference or contain them), and some series/items skip it entirely (`group_id = 0`), so it can't be a required part of another aggregate's invariants. Applying an *existing* rule to a newly-confirmed entity is different from writing a new rule — that's why this was handled as a decision-log entry rather than a full Architecture Evolution Proposal or ADR.

**Does this change any existing architectural decision?** No. It fills a gap in the Blueprint's ER diagram; it doesn't reverse or contradict anything the Blueprint previously stated. The diagram itself is still technically out of date until formally edited — tracked as an open item alongside `MediaPathResolver`'s same kind of pending documentation update, deliberately deferred to after Wave 4 rather than done piecemeal mid-wave.

---

## 3. The Laravel route-parameter-binding issue

**The mistake:** `KhotabAuthorController::show()` was first written as:

```php
public function show(int $author, string $op, ContentListingService $listing, ContentSidebarWidget $sidebar): View
```

bound to the route `Route::get('/khotab-{op}-{author}.htm', [KhotabAuthorController::class, 'show'])`. This looks reasonable — the parameter *names* (`$author`, `$op`) match the route placeholder names exactly. It failed at request time with a real `TypeError`: `Argument #1 ($author) must be of type int, string given`.

**The framework behavior:** Laravel's controller dispatcher does not match route parameters to method parameters by name for plain scalar types. `Illuminate\Routing\ResolvesRouteDependencies::resolveMethodDependencies()` calls `$values = array_values($parameters)` on the route's captured parameters before matching them against the method's reflected parameter list — that call discards the parameter *names* and leaves only *position*. Class-typed parameters (like `ContentListingService $listing`) are resolved separately via the container and spliced in wherever they appear, but scalar parameters are filled strictly in the order the route captured them. The route `/khotab-{op}-{author}.htm` captures `op` first and `author` second; the method declared `$author` first — so the `op` value (`'pdf'`, a string) was passed into the `int $author` slot.

**How future controllers should avoid this:** Method parameter *order* must match the route pattern's left-to-right placeholder order, not just parameter *names*. This already worked correctly by coincidence in the existing `ChannelController::showAuthor(int $channel, int $author, ...)` (route `/channel-{channel}-{author}.htm`) because both order and names happened to align — that precedent didn't actually prove name-based matching, it just never exposed the distinction. The fix here was to reorder to `show(string $op, int $author, ...)`, and a caution comment was added directly in `KhotabAuthorController` (not a new shared doc) so the next multi-segment route in Wave 4 — categories, w2acd, gallery — doesn't repeat it. This was caught by the route's own test, not shipped silently.

---

## 4. The new model graph

```
Author ──┬── hasMany ──> Series
         ├── hasMany ──> KhotabItem      (via `author` column, not `author_id` — legacy's own FK name)
         └── hasMany ──> KhotabGroup

KhotabGroup ──┬── belongsTo ──> Author
              ├── belongsTo ──> Channel
              ├── hasMany   ──> Series
              └── hasMany   ──> KhotabItem

Series ──┬── belongsTo ──> Author
         ├── belongsTo ──> Channel
         ├── belongsTo ──> KhotabGroup
         ├── hasMany   ──> KhotabItem
         └── belongsToMany ──> Category   (via series_category_index)

KhotabItem ──┬── belongsTo(authorModel) ──> Author   (named `authorModel`, not `author` — `author` is the raw FK column)
             ├── belongsTo ──> Channel
             ├── belongsTo ──> Series
             ├── belongsTo ──> KhotabGroup
             ├── hasOne    ──> KhotabAdvanced   (shared id, 1:1 extension)
             ├── hasMany   ──> Mirror
             ├── hasMany   ──> Comment
             └── belongsToMany ──> Category   (via khotab_category_index — different junction table from Series')

Mirror ──┬── belongsTo ──> KhotabItem
         └── hasOne    ──> MirrorAdvanced   (shared id, 1:1 extension)

Comment ──── belongsTo ──> KhotabItem

Category ──┬── belongsTo ──> Category (self, `parent`)
           ├── hasMany   ──> Category (self, `children`)
           ├── belongsToMany ──> KhotabItem
           └── belongsToMany ──> Series
```

`KhotabAdvanced`/`MirrorAdvanced` (technical/encoding metadata, 1:1 with `KhotabItem`/`Mirror` respectively) and `Comment` complete the graph but have no further relationships of their own worth diagramming.

### Relationships that required judgment, not direct reproduction

- **`KhotabAdvanced`/`MirrorAdvanced` kept as separate models, not merged into `KhotabItem`/`Mirror`.** The pre-existing schema documentation (from an earlier wave) explicitly flagged this as an open question — "natural candidate for either a `hasOne` relationship or merging into the main model/table entirely... not decided here." This session made that call: kept them separate, preserving the legacy table structure exactly rather than collapsing it. Lower-risk and reversible, but a real decision, not a mechanical port — and it was made only in a model docblock, not recorded in `decision-log.md`. Flagged in §5 below as something worth formally recording.
- **`Mirror` deliberately does *not* use the existing `TracksViews`/`Viewable`/`ContentViewed` machinery**, despite having a `hits` column with the exact same name as every other view-counter column that pipeline already handles. The first draft of `Mirror` did wire it in, by pattern-matching `Channel`'s precedent. On inspection this was wrong: a mirror has no page of its own — its `hits` increments only on *download*, the same event that increments `KhotabItem.downcount`, not on *view*. Reusing the view-tracking abstraction for a download-counting event would have quietly conflated two different concepts under a shared column name. `KhotabItem`/`Mirror` each got their own small, un-shared `incrementDownloadCount()` instead, deliberately not generalized into a new trait yet — only one module (`khotab`) has evidence of this pattern so far, and the project's standing rule (matching `VbUserReader`'s deferred trigger) is to wait for a second module's evidence before extracting a shared abstraction, not to build one preemptively.
- **`KhotabItem::authorModel()` naming.** `author` is already the legacy database column name (an integer FK), so the relationship method couldn't be named `author()` without colliding with the raw attribute. This is a naming decision imposed by the legacy schema, not a business-logic judgment call, but worth noting since it's the one relationship whose name doesn't match its legacy-obvious label.
- **`KhotabGroup` naming** (not `Group`) to avoid colliding with the unrelated Spatie permission concept and with other modules' own separate `*_groups` tables (`w2acd`, `anasheed` both have their own) — a naming decision to prevent future collisions, not derived from legacy naming, which just calls it "group" throughout.

---

## 5. Does anything here challenge the Blueprint or need a new ADR?

**No new Blueprint contradiction, and no new ADR beyond the `KhotabGroup` decision already made.** Everything else in this session's work was either a direct, evidence-grounded legacy port, a bug fix at the technical-correctness tier (matching the precedent already set for similar fixes in earlier waves — no separate ADR needed for those either), or a judgment call scoped narrowly enough to record in a docblock rather than escalate.

Three things are worth flagging as follow-up, none of them urgent or blocking:

1. **The `KhotabAdvanced`/`MirrorAdvanced` "keep separate" decision should be added to `decision-log.md`.** It's a real architectural choice — currently only documented in a model docblock — and the log's own stated purpose is exactly this tier of decision ("real, but not individually significant enough to warrant its own ADR"). Not done yet since this is a review, not an implementation step.
2. **Download-counting (`incrementDownloadCount()`) is a genuine candidate for a future Architecture Evolution Proposal, but not yet** — only one module has evidence. This was already on the watch list from the pre-Wave-4 review (alongside comment-posting and `randomitems()`); `khotab` is the first data point, not the second. Per the same discipline already applied to `VbUserReader`, this should stay un-extracted until `w2acd`/`anasheed`/`telawah` (all structurally similar, per the Blueprint's own Wave 3 grouping) confirm whether they share this shape or not.
3. **The comment-moderation gate (`view` defaults to `0`; nothing in the audited khotab code ever sets it to `1`) is a new dependency worth escalating**, not a Blueprint challenge. It means the comment-posting feature just built is only useful in production once `admincp`'s (not yet audited) moderation flow exists — worth adding to `implementation-findings.md`'s "Open items requiring escalation" list alongside IF-004/IF-006/IF-012, as a heads-up for Wave 6 (admin) planning, not something that needs deciding now.

None of these require pausing Wave 4 or revisiting the Blueprint's frozen structure — they're small, recorded, and low-risk to pick up later.
