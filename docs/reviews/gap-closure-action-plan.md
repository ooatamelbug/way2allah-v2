# Gap-Closure Action Plan: `vars_categories`, `radio`, `pages/social.php`, `chat_room` (content half)

**Status:** Proposal only. No code changed, no Blueprint file edited. Produced in response to the 4 confirmed gaps identified in `docs/reviews/legacy-vs-laravel-coverage.md` §4, which this document treats as the source of truth for what's missing.

**Scope of this document:** for each of the 4 items, answer the user's 7 questions with fresh, first-hand evidence (not the coverage document's summaries alone — every claim below was re-verified against the actual legacy source in this pass). Then propose the minimal set of Blueprint/Roadmap amendments needed to close the gap, and confirm every known legacy capability across the whole migration now classifies as exactly one of: Implemented / Planned / Deferred / Dead / Out of scope.

> **Correction made during implementation (IF-031):** item 1's proposed redirect set below assumed `categories.htm` (the category tree/index) already had a live Laravel route. It doesn't — `CategoryController`'s own docblock already correctly listed `categories.htm` and `category-series-{id}-{id2}.htm` as deferred; this document's author missed that when drafting the proposal. Only the `vars-category-{id}.htm` → `category-{id}.htm` redirect was actually built. The original text is left as-written below since it's what was approved and what motivated the work — not because it's fully accurate. See IF-031 for the full evidence and correction.

---

## 1. `vars_categories/`

**New evidence this pass, not in the original coverage doc:** `vars_categories/` is not a distinct feature — it is an older, superseded duplicate of `categories/`, confirmed by direct diff.

- `vars_categories/functions.php`'s `ListGroup()`/`ListSeries()`/`ListKhotab()`/`Cat_Breadcrumb()` query the exact same tables as `categories/functions.php` (`nuke_islamic_khotab`, `nuke_islamic_groups`, `nuke_islamic_series`, `nuke_w2a_cat`, `khotab_category_index`) — same category-id space, same content-id space. `categories/`'s versions are a strict superset: they add meta-SEO fields (`meta_keywords`/`meta_description`/`meta_index`/`meta_follow`), an `op=var` branch (`ListVar()`, anasheed content), a `cat_id==487` special case (`ListMediaCoverage()`), and richer card markup. `vars_categories/`'s versions are missing all of that, and its `topitems()` sidebar calls are missing the `hidden = 0` filter `categories/category.php` has — a real, if minor, bug in the older copy (could surface hidden content in the sidebar).
- `vars_categories/tree.php` is byte-for-byte the same file as `categories/tree.php` (same `op=fatawa` branch, same `generateTree()`), just reachable at a second URL with no `op` passed — it renders the identical default (video-count) tree `categories.htm` already produces.
- `vars_categories/category.php` vs `categories/category.php`: same core query and breadcrumb logic, `vars_categories/`'s is simply the file `categories/category.php` was evidently evolved from.
- `vars_categories/series.php` vs `categories/series.php`: same, differing only in the same meta-tag/markup gap.
- Live `.htaccess` routes (confirmed, 3 total): `vars-categories.htm → vars_categories/tree.php`, `vars-category-{id}.htm → vars_categories/category.php?id=$1`, `vars-category-series-{id}-{id2}.htm → vars_categories/series.php`. Note the naming collision this creates: `var-category-{id}.htm` (singular, → `categories/category.php?op=var`, i.e. `categories/`'s anasheed branch) and `vars-category-{id}.htm` (plural, → the duplicate module) are two different, live, easily-confused URLs.

**Answers:**

1. **In scope?** Yes, but only as a URL-compatibility concern — not as new business logic. The content these 3 routes serve is already fully covered by `category-{id}.htm` (built, `CategoryController`) for 2 of the 3 routes, and by the deferred-but-planned `category-series-{id}-{id2}.htm` for the third.
2. **Roadmap disposition:** Merge into existing task **4.2** (`khotab` + `categories` controllers/routes) as a URL-compatibility footnote — not a new task. No new controller, service, or model is needed.
3. **Effort:** **Small** — 3 redirect rules (`Route::redirect()` or `config/legacy-url-map.php` entries) mapping `vars-category-{id}.htm` → `category-{id}.htm` (same id) and `vars-categories.htm` → `categories.htm`. `vars-category-series-{id}-{id2}.htm` redirects to whatever `category-series-{id}-{id2}.htm` resolves to once that route itself is built (currently deferred per `CategoryController`'s own docblock) — so this third redirect is blocked on that pre-existing deferred item, not on new work.
4. **Reuse:** `CategoryController`, `Category` model — no new component.
5. **Business confirmation:** None required — the duplication is evidenced directly from source, not inferred. Optional, low-priority: confirm nobody has bookmarked/indexed `vars-category-*.htm` URLs expecting content distinct from `category-*.htm` (unlikely, since the content is identical).
6. **Dependencies:** None beyond `CategoryController` already existing (it does).
7. **Execution order:** Last of the 4 — trivial and fully unblocked, but lowest business value (pure URL hygiene, no user-facing gap since the content is already served correctly at the canonical URL).

---

## 2. `radio/`

**New evidence this pass, materially changing the picture from the original coverage doc:** only 2 of the 5 "live" routes are functionally live. The other 3 are silently broken in production today.

- All 5 `.htaccess` rules route to `radio/index.php` (confirmed: `radio.htm`, `radio-mobile.htm`, `remove-playlist-item-{id}-{section}.htm?op=remove-playlist-item`, `playlist-item-{id}.htm?op=playlist-item`, `save-last-listen.htm?op=save-last-listen`).
- `radio/index.php` (the actual, routed-to file, read in full) contains **no `op=` handling whatsoever** — it unconditionally renders one page: an anonymous, non-personalized continuous-playlist view built from a direct JOIN (`nuke_islamic_mirror`/`nuke_islamic_khotab`/`nuke_islamic_authors`, filtered `.mp3`/`broken=0`/`hidden=0`, `LIMIT 40`) plus two `topitems()`-shaped sidebar boxes (newest video / newest audio, `LIMIT 10`).
- `radio/functions.php` (read in full) *does* implement `delete_playlist_item()`, `get_playlist_item()`, `save_last_listen()` — the logic the 3 op-routes are named for — but these are only ever called from `radio/indexXX.php`, a second, more feature-rich entry point (personalized playlist backed by a per-user `nuke_radio_playlists` table) that **no `.htaccess` rule points to**. It is unreachable.
- Conclusion: `remove-playlist-item-*.htm`, `playlist-item-*.htm`, and `save-last-listen.htm` are live URLs that currently do nothing but re-render the full radio page, ignoring their own query parameters. The personalized-playlist feature (login-aware playlist, "add to playlist," "most-listened" widget) is confirmed dead code, not merely undocumented.

**Answers:**

1. **In scope?** Yes for the 2 genuinely-live routes (`radio.htm`, `radio-mobile.htm` — same page, mobile flag only). The personalized-playlist backend is **not** in scope — it's dead code, not a deferred feature.
2. **Roadmap disposition:** New task, appended to Wave 4 as **4.10 — `radio`**. It belongs in Wave 4 (Content domain) because it reuses exactly the models Wave 4 already built (`KhotabItem`, `Mirror`, `Author`) — it has no dependency on anything Wave-5/6-shaped, and needs no business confirmation.
3. **Effort:** **Small** — one controller/route (`radio.htm`), reusing existing Eloquent models for the JOIN query and the existing `topitems()`-shaped sidebar pattern (a new `ContentSidebarWidget` method with `LIMIT 10`, distinct from khotab's own `LIMIT 5` sidebar methods per this project's "one method per confirmed call-site shape" convention). `mobile_me=true` is just a `$_GET` flag the current page reads to toggle a hidden form field — trivial to reproduce or drop per the mobile-view decision already made elsewhere in the app.
4. **Reuse:** `KhotabItem`, `Mirror`, `Author` models (all exist, Wave 4). New: one `RadioController`, one `ContentSidebarWidget` method for the LIMIT-40 mixed-media JOIN (this exact shape — `.mp3`-filtered, cross-media — doesn't exist yet; closest existing method is khotab-only).
5. **Business confirmation:** Recommended but not blocking: flag to the business that the personalized playlist feature (save-last-listen, add/remove playlist items) does not currently function in production, in case this is news to them and they want it *rebuilt* rather than dropped. Frame as a heads-up, not a migration blocker — the page itself works fine without it today, so the migration can proceed on the confirmed-live subset regardless of the answer.
6. **Dependencies:** None — Wave 4's model graph already covers everything this needs.
7. **Execution order:** Do this before `chat_room`'s content half (item 4) since it's smaller and fully unblocked; do it after `vars_categories`'s trivial redirects only because those are even smaller, not because of any real dependency.

---

## 3. `pages/social.php`

**New evidence this pass:** confirmed there is genuinely no `.htaccess` rule for `social.htm` anywhere (exhaustive grep, zero matches) — but the header's account dropdown menu links to it twice (`header.php:340,347`, `href="social.htm"`, label "تابعنا" / "Follow us"), in permanent site navigation, not a stray/orphaned link. **This is a real, currently-broken link in production** — a visitor clicking a standing header menu item gets a 404 today.

- Content itself (`pages/social.php`, read in full): fully static — ~30 hardcoded social-platform entries across 6 grouped sections (Facebook, YouTube, Instagram, Telegram, "various platforms," podcasts), each just a name/link/image-filename triple rendered in a loop. No database queries, no business logic.
- Confirmed image-path bug, independent of the routing bug: every entry renders `<?= $image_url ?>media/social-images/<?= $page['image']?>`, but `media/social-images/` does not exist on disk — the real directory is `pages/social-images/` (confirmed: `ls` succeeds on the latter, fails on the former). Every social icon on this page is currently a broken image, on top of the page itself being unreachable via its intended URL.
2. **Roadmap disposition:** New task, appended to Wave 2 as **2.5 — `pages/social.php`**. It belongs in Wave 2 (Zero-Risk Static Wins) — same shape as that wave's other static-page tasks (2.1, 2.4), no DB, no auth, no business logic to preserve beyond the link list itself.
3. **Effort:** **Small** — a single Blade view + a config/array of the ~30 link entries (or a small `SocialLink` value-object list), one route, fixing the `media/` → `pages/social-images/`-equivalent asset path as part of the port (this is a bug fix, not a behavior change — the *intent* was clearly to reference real images).
4. **Reuse:** Nothing existing — this is genuinely new (a first static content-list page in the Content/Pages area with no DB backing), but trivially small.
5. **Business confirmation:** None required for the content itself (it's literally just current social-media links, verifiable by visiting each). Recommended, non-blocking: confirm the link list is still current before porting (platforms/handles change) — a 5-minute check, not a formal Business Confirmation gate.
6. **Dependencies:** None.
7. **Execution order:** Do this first among the 4 — it's the only one fixing an active, user-visible production bug (broken header link + broken images), independent of everything else, and the smallest unit of new work.

---

## 4. `chat_room`'s content/lesson-browsing half

> **Correction made during implementation (IF-033):** the text below scopes the `nuke_hedaya_lessons` weekly-schedule table into this item's work. Direct reading of `chat_room/table.php` during implementation showed that feature schedules attendance at the LIVE voice rooms (every row links to `chat_{room_id}.htm`), not recorded content — it was moved to task 6.5's scope instead and was NOT built as part of task 4.11. The text below is left as originally written since it's what was approved; see IF-033 for the full evidence.

**Grounded in the existing `chat_room.md` module doc** (already fully analyzed, 9/9 files read, cited here rather than re-derived) — this module was already correctly split into two capabilities: a live voice-chat room (already correctly gated behind Business Confirmation #4 as Roadmap task **6.5**) and a location-scoped ("Hedaya," `location_id=10`) slice of khotab-style recorded-lesson browsing, which has **no Roadmap task number at all** — the gap this action plan addresses.

- The content half reuses `nuke_islamic_khotab` directly (same table Wave 4's `KhotabItem` model already wraps), filtered through 3 new junction tables (`nuke_islamic_authors_location`, `nuke_islamic_groups_location`, `nuke_islamic_series_location`) plus `nuke_islamic_locations`, and a separate `nuke_hedaya_lessons` scheduling table for the weekly-lesson timetable.
- Confirmed live routes: `chat_room.htm`, `chat_{id}.htm`, `chat_author_{id}.htm`, `chat_lesson_{id}.htm`, `lesson-download-{id}.htm`.
- This is explicitly the module doc's own recommendation (`chat_room.md` §6): "the lesson-browsing half joins the Content domain (reusing the same `Khotab` model, scoped by a `location_id` relationship)... structurally, same blockers as khotab's equivalent workflows, nothing unique to this module." It needs **no** business confirmation — only the live-room half (task 6.5) does.

**Answers:**

1. **In scope?** Yes — confirmed live, confirmed Content-domain, confirmed no product-decision blocker (that blocker is specific to the live-room half, already correctly scoped separately).
2. **Roadmap disposition:** New task, appended to Wave 4 as **4.11 — `chat_room`'s lesson-browsing half**. Explicitly cross-referenced against 6.5 in both tasks' text so a future reader doesn't conflate the two halves of this one legacy directory.
3. **Effort:** **Medium** — larger than `radio/`'s Small: needs a `location()` scope/relationship added to the existing `Khotab`(`KhotabItem`) model, plus the 3 new junction-table relationships and the `nuke_hedaya_lessons` schedule table's own small model. Still meaningfully smaller than a from-scratch content module since the core browsing/detail/download/mirror pattern is a direct copy of khotab's already-built equivalent.
4. **Reuse:** `KhotabItem`, `Mirror`, `Author` models and the existing khotab controller pattern (author/group/series/item browsing, `hits`/`downcount` counters via the existing `TracksViews` pipeline). New: `location()` scope, 3 junction-table relationships, one small `HedayaLesson`-style model for the schedule table, one controller.
5. **Business confirmation:** None for this half. (The live-room half's Business Confirmation #4 is unaffected and unchanged — task 6.5 stays as-is.)
6. **Dependencies:** Wave 4's `KhotabItem`/`Mirror`/`Author` model graph (already built).
7. **Execution order:** Last of the 4 — largest effort, and while unblocked, it has the most surface area to get wrong (3 new junction tables, a new schedule table), so doing the 3 smaller items first de-risks the team's remaining Wave-4-shaped work before tackling this one.

---

## Recommended execution order (all 4 items)

1. `pages/social.php` — fixes an active production bug (broken header link, broken images), smallest unit of new work, zero dependencies.
2. `vars_categories/` redirects — trivial, zero new logic, closes the URL-compatibility gap.
3. `radio/` — Small, fully unblocked, reuses existing models directly.
4. `chat_room`'s content half — Medium, reuses existing models but with more new relationship surface (3 junction tables + a schedule table); benefits from the team having just re-exercised the khotab model graph on `radio/` immediately before.

---

## Proposed Blueprint/Roadmap amendments (minimal set)

All 4 amendments are **additive** — no existing wave, task, or Blueprint section is rewritten or renumbered.

1. **`00-implementation-roadmap.md`, Wave 2:** append task **2.5 — `pages/social.php`**, same shape as 2.1/2.4 (static Pages-domain controller + view, `LegacyUrlCompatibility` rule for `social.htm` since no `.htaccess` rule currently exists for it — same "raw-path-only" profile already used for `khotab/dump.php`/`live.php` elsewhere in this Roadmap).
2. **`00-implementation-roadmap.md`, Wave 4:** append task **4.10 — `radio`** and task **4.11 — `chat_room`'s lesson-browsing half**, both dependent on 4.1 (Wave 4's core model graph) only. Task 4.11's text should explicitly note it covers only the content half of `chat_room`, cross-referencing task 6.5 (the live-room half) so the split isn't ambiguous to a future reader.
3. **`00-implementation-roadmap.md`, task 4.2 (`khotab` + `categories` controllers/routes):** add a short note documenting `vars_categories/`'s 3 live routes as a confirmed duplicate of `categories/`'s functionality, closed via 3 redirect rules rather than new logic — no new task number needed.
4. **`legacy-vs-laravel-coverage.md`:** once the above 3 amendments land, update its §4 "no, with 4 exceptions" answer to reflect that all 4 are now Planned (with task numbers), not unclassified gaps — this document itself does not need a code or Blueprint change, just a status update once the Roadmap amendment is approved.

No amendment to the Blueprint's frozen wave *structure* (Wave 0-6 objectives, prerequisites, critical path) is needed — every new task fits cleanly inside an existing wave's stated scope and dependency shape.

---

## Final classification check

With the above 4 amendments applied, every legacy capability the coverage document could name is accounted for as exactly one of: Implemented (everything Wave 0-4 already shipped), Planned (now including 2.5, 4.10, 4.11, and 4.2's redirect note), Deferred (task 6.5's live-room half, `category-series-{id}-{id2}.htm`, and every other item the coverage document already listed as deferred-with-reason), Dead/Unreachable (now also `radio/`'s personalized-playlist backend, and `vars_categories/`'s 3 routes as duplicate — not new content), or Out of scope (the Blueprint's existing "Never" list, unchanged). Nothing from the 4 target items remains unclassified.
