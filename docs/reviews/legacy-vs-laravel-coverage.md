# Legacy vs. Laravel Coverage Analysis

Analysis document only. No code changed, no Blueprint changed, no implementation-findings/decision-log entries added (this document is the deliverable itself).

**Method:** every module's coverage row below is built from four independently-checkable sources, cross-referenced against each other rather than trusted individually: (1) the legacy file tree and `.htaccess` (`grep`/`ls`, this session), (2) the current Laravel `routes/*.php` (`php artisan route:list`, 60 routes), (3) `app/Domain/*` (models/controllers actually present), (4) `tests/Feature/*`/`tests/Unit/*` (27 test files). Where the pre-implementation audit's own module docs (`legacy-project/docs/migration/modules/*.md`) and Roadmap (`00-implementation-roadmap.md`) add information not otherwise derivable from code, they're cited explicitly and distinguished from this session's own direct findings.

**"Legacy feature" definition (for the counts in §2):** one feature = one distinct user-facing capability with its own entry point — in practice, one real `.htaccess`-routed URL pattern, or one `op=`/query-parameter branch within a shared entry point that produces materially different output (e.g. `khotab/author.php`'s `video`/`audio`/`pdf` ops count as 3, not 1). Raw file counts are not used as the feature count — many legacy files are one feature split across a handful of files (mirrors, comments, downloads all belonging to "item detail"), and some single files hold several unrelated features (`khotab/functions.php` alone backs a dozen). Every count in §2 is a manual tally against this definition, cross-checked against route/test counts, not an automated metric — treat it as a considered estimate, not a precise measurement.

---

## 1. Per-Module Coverage Matrix

### Legend
- **Implemented**: real Laravel route + controller + (model where applicable), verified by at least one passing test.
- **Deferred**: explicitly documented as out-of-scope-for-now, with a citable reason (controller docblock, Wave report, decision-log, or the Blueprint's own Roadmap gate).
- **Dead**: confirmed unreachable or non-functional in the legacy codebase itself (broken route, undefined function, no `.htaccess` rule and no inbound link).
- **Gap**: neither implemented, deferred, nor dead — found during this analysis, not previously tracked. Flagged **in bold** and repeated in §3.

### 1.1 `khotab` — fully migrated

| | |
|---|---|
| **Legacy features discovered** | ~34: authors directory (×4 ops: video/audio/pdf/fatwa) + per-author browse (×3 ops) + item detail + mirrors + comments (post+list) + download (item/mirror/pdf) + send-friend + series + group + day-browse (+today, +dated ×2) + PDF dump listing + newest listing (×3 ops, ×2 fixed/new) + advanced search (+2 AJAX modes) + admin inline controls (deferred, see below) |
| **Implemented in Laravel** | Item detail, mirrors, comments (post + list, view=1 gate), downloads (item/mirror/pdf), authors directory (all 4 ops), per-author browse (all 3 ops), series, group, day-browse (today + dated, both video/audio), PDF dump, newest listing (all 3 ops), advanced search (title/author/channel/date, non-AJAX single-page form) |
| **Intentionally deferred** | `khotab_send_friend()`/`send_friend_modal()` (email side-feature); admin inline controls (`adminItemControls()`/`adminAuthorControls()`/etc. — Blueprint's own open question #6/task 6.4, gated); legacy's AJAX-split search UX (replaced with one combined GET-based page, a documented behavior improvement not a gap) |
| **Confirmed dead/unreachable** | `khotab/old.php` (1,752 lines, never routed — presumed superseded, not individually re-confirmed this session); the 2nd, shadowed `khotab-pdf-{id}.htm`/`khotab-mirror-{id}-{id2}.htm` `.htaccess` rules (IF-020's evidence) |
| **Business confirmations still required** | #6 (admin CRUD design — Roadmap task 6.4) |
| **Implementation Findings** | IF-001, IF-005, IF-006, IF-014 through IF-024 (18 total — the single largest finding concentration of any module) |
| **Blueprint tasks** | 4.1 (model graph), 4.2 (controllers/routes), 4.3 (category pivot), 4.9 (routing cutover, per-submodule) |
| **Test coverage** | `KhotabItemControllerTest`, `KhotabBrowsingControllersTest`, `KhotabSearchControllerTest`, `GeoIpLookupTest` (comment posting), `ContentListingServiceTest`, `ContentSidebarWidgetTest` |

### 1.2 `categories` — partially migrated

| | |
|---|---|
| **Legacy features discovered** | Single-category detail (series+items+sidebar), category tree/index (`categories.htm`), category-series listing (`category-series-{id}-{id2}.htm`), `.grx` GetRight download list (`downitems.php`), `op=var`/`op=fatwa` alternate entry modes on `tree.php` |
| **Implemented in Laravel** | Single-category detail only (`category-{id}.htm`) |
| **Intentionally deferred** | Category tree/index and `category-series-{id}.htm` — both named explicitly in `CategoryController`'s own docblock as deferred, not silently dropped |
| **Confirmed dead/unreachable** | `categories/item.php` — no `.htaccess` rule found anywhere (checked this session, not previously flagged) |
| **Business confirmations still required** | None specific to this module beyond the shared category-487 special case (unexplained magic number, `CategoryController`'s own docblock) |
| **Implementation Findings** | IF-006 |
| **Blueprint tasks** | 4.1, 4.2, 4.3 |
| **Test coverage** | `CategoryControllerTest` |
| **Gap (see §3.1)** | `categories/downitems.php` (`.grx` download, khotab-series scope) was never explicitly deferred anywhere — should have received the same "deferred, GetRight-format" treatment as `khotab_send_friend()`/`download_var_group_getright()`, but wasn't mentioned at all. |

### 1.3 `vars_categories` — **not started; a real, live, parallel module with zero coverage**

| | |
|---|---|
| **Legacy features discovered** | Category detail (`vars-category-{id}.htm`), category tree (`vars-categories.htm`), category-series listing (`vars-category-series-{id}-{id2}.htm`) — the anasheed-domain structural twin of `categories/`, confirmed by its identical file set (`category.php`, `downitems.php`, `functions.php`, `item.php`, `series.php`, `tree.php`) |
| **Implemented in Laravel** | None |
| **Intentionally deferred** | Not documented anywhere as deferred |
| **Confirmed dead/unreachable** | No — all 3 routes above are real, live `.htaccess` rules, confirmed this session |
| **Business confirmations still required** | None known — this is a build gap, not a business question |
| **Implementation Findings** | None |
| **Blueprint tasks** | **None found** — not named in the module-to-domain mapping table (§7) or the Implementation Roadmap under its own name; `categories-and-vars_categories.md` is the audit's module doc (paired naming suggests it was meant to be covered alongside `categories`, but Roadmap tasks 4.1-4.3 only ever named `categories`, never `vars_categories`, and no Laravel work exists for it) |
| **Test coverage** | None |
| **Gap (see §3.1)** | **Yes — the most significant single gap this analysis found.** See §3.1/§3.3. |

### 1.4 `vars` — 5 of 6 files confirmed dead (per Blueprint), 1 of 6 fixed and migrated

| | |
|---|---|
| **Legacy features discovered** | 4 themed news pages (`exclusive-news.htm`, `cartoon-news.htm`, `documentary-news.htm`, `anasheed-news.htm`, all via `more.php`); `group.php`/`index.php`/`var.php`/`download.php`/`getright.php`/`old.php` — all confirmed no-route |
| **Implemented in Laravel** | The 4 themed routes (`AnasheedNewsController`) |
| **Intentionally deferred** | N/A — everything reachable is now built |
| **Confirmed dead/unreachable** | `group.php`, `index.php`, `var.php`, `download.php`, `getright.php`, `old.php` — no `.htaccess` rule for any of them (confirmed this session); matches Blueprint's "Never — Out of Scope" list ("`vars` (5 of 6 files)"). `more.php` itself was *also* dead until this Wave's fix (IF-029 — called an undefined function, fatal-errored on every request despite having 4 live routes) |
| **Business confirmations still required** | None |
| **Implementation Findings** | IF-029 |
| **Blueprint tasks** | **4.7 implicitly** — task 4.7's own text says `anasheed` "absorbs `vars/`'s one live capability (4 themed routes)," but this wasn't actually built until this session's informal "Wave 5," after Wave 4 had already been reported complete. See §3.3. |
| **Test coverage** | `AnasheedNewsControllerTest` |

### 1.5 `w2acd` — migrated (models reproduce pre-P-015 comma-columns as-is)

| | |
|---|---|
| **Legacy features discovered** | CD group listing (+ hit-count), item detail (+ mirror links parsed from comma-columns), no confirmed download-tracking feature (no `op=` handling in either live file) |
| **Implemented in Laravel** | Group listing, item detail, mirror-link rendering |
| **Intentionally deferred** | P-015 data migration (comma-columns → `nuke_w2acd_mirror`) — Roadmap task 4.4, explicitly gated before "clean" models per §7; bulk `.grx`-style download not applicable (none found in the 2 live files) |
| **Confirmed dead/unreachable** | Every pretty `cds-*.htm` URL (IF-026 — routes to the missing `new_modules.php`); registered instead at the raw legacy path |
| **Business confirmations still required** | None specific (inherits IF-026's general question) |
| **Implementation Findings** | IF-002, IF-003, IF-025 |
| **Blueprint tasks** | 4.4 (data migration — **not done**, see below), 4.5 (models/controllers — done against pre-migration data) |
| **Test coverage** | `W2acdControllerTest` |
| **Note on task 4.4** | Roadmap §7 states task 4.5 (models) *depends on* task 4.4 (data migration) being "complete and verified in production" first. This session built 4.5 without 4.4 — an explicit, reasoned, documented deviation from the Blueprint's own stated dependency order (decision recorded in the cross-wave review, not silently done). Flagged again here since a coverage document is exactly the place a dependency-order deviation should be visible. |

### 1.6 `gallery` — fully migrated

| | |
|---|---|
| **Legacy features discovered** | Album listing, album detail (image grid), per-image download, bulk album zip-download (`op=download-album`) |
| **Implemented in Laravel** | Album listing, album detail, per-image download |
| **Intentionally deferred** | N/A |
| **Confirmed dead/unreachable** | Bulk zip-download — its own trigger route is IF-026-dead, and the underlying function doesn't create the zip it claims to serve (confirmed by direct reading, not inferred) |
| **Business confirmations still required** | None |
| **Implementation Findings** | IF-027 |
| **Blueprint tasks** | 4.6 |
| **Test coverage** | `GalleryControllerTest` |

### 1.7 `anasheed` — fully migrated

| | |
|---|---|
| **Legacy features discovered** | Item detail, mirrors, comments (post+list), downloads (item/mirror), group browse (+sub-groups), send-friend, `.grx` GetRight group download, 4 themed news pages (via `vars/more.php`, see §1.4) |
| **Implemented in Laravel** | Item detail, mirrors, comments, downloads, group browse, 4 themed news pages |
| **Intentionally deferred** | `anasheed_send_friend()`, `.grx` GetRight download (`download_var_group_getright()`) — both named explicitly in `AnasheedItemController`'s docblock |
| **Confirmed dead/unreachable** | `hidden` column not enforced anywhere in this module (a legacy behavioral gap, not a routing issue — IF-028) |
| **Business confirmations still required** | Whether `hidden`'s non-enforcement is intentional or a legacy bug (IF-028's own note) |
| **Implementation Findings** | IF-028, IF-029 |
| **Blueprint tasks** | 4.7 |
| **Test coverage** | `AnasheedItemControllerTest`, `AnasheedGroupControllerTest`, `AnasheedNewsControllerTest` |
| **Missing, not yet flagged elsewhere (see §3.1)** | `AnasheedGroup.icon`/`AnasheedItem.frame` bucketed thumbnails — already documented as a gap in `post-wave-4-cross-wave-architecture-review.md` §2; the ancestor-breadcrumb trail — same source. Repeated here for completeness, not re-discovered. |

### 1.8 `telawah` — fully migrated

| | |
|---|---|
| **Legacy features discovered** | Reciter directory, group browse (+sub-groups), item detail, download, newest listing (`more.php`) |
| **Implemented in Laravel** | Reciter directory, group browse, item detail, download |
| **Intentionally deferred** | N/A |
| **Confirmed dead/unreachable** | `telawah/more.php` — its only route (`recite-news.htm`) is IF-026-dead (routes to missing `new_modules.php`); not individually re-confirmed this session beyond the routing check, but consistent with the pattern |
| **Business confirmations still required** | None |
| **Implementation Findings** | None specific to telawah beyond the confirmed hits-never-incremented behavior noted in `TelawahItem`'s own docblock (not filed as a new IF — already known from the pre-implementation audit, `telawah.md` §5) |
| **Blueprint tasks** | 4.8 |
| **Test coverage** | `TelawahControllersTest` |
| **Gap (see §3.1)** | `telawah/more.php` was never built, and unlike `khotab`'s/`gallery`'s equivalents, this omission isn't stated anywhere in `TelawahAuthorController`/`TelawahGroupController`'s docblocks — the Wave 4 report mentioned it once, in prose, but no controller-level docblock carries the same note forward. |

### 1.9 `channels` — fully migrated (Wave 3)

| | |
|---|---|
| **Legacy features discovered** | Channel directory, browse-by-channel, browse-by-channel-and-author |
| **Implemented in Laravel** | All 3 |
| **Intentionally deferred, but the reason is now stale** | "Recommended For You" widget (`randomitems()`) on the author-filtered page — deferred in Wave 3 with the reason "needs a real content-item model, doesn't exist until Wave 4." Checked directly for this document: `KhotabItem` and `ContentSidebarWidget::khotabRandomFeatured()` have both existed since Wave 4, but `ChannelController`/`channels/author.blade.php` were never revisited — the docblock/view comment still reads as if the blocker is current. See §3.2. |
| **Confirmed dead/unreachable** | `channels/item.php`, `authors.php`, `channe___l.php`, `old.php` — no `.htaccess` rule, not included by any other file (per Wave 3's own findings) |
| **Business confirmations still required** | IF-012 (should the author-filtered page show recommendations, matching the unfiltered page) |
| **Implementation Findings** | IF-011, IF-012, IF-013 |
| **Blueprint tasks** | 3.1, 3.2, 3.3 |
| **Test coverage** | `ChannelControllerTest`, `ChannelTest` |

### 1.10 `live-stream` — fully migrated (Wave 3)

| | |
|---|---|
| **Legacy features discovered** | Channel directory, watch-a-channel, hardcoded-channel-51 page (`live.php`) |
| **Implemented in Laravel** | All 3 |
| **Intentionally deferred** | N/A |
| **Confirmed dead/unreachable** | N/A |
| **Business confirmations still required** | `live.php`'s own purpose (`live-stream.md` §8, still open) |
| **Implementation Findings** | IF-007, IF-009, IF-010 |
| **Blueprint tasks** | 3.1, 3.2, 3.3 |
| **Test coverage** | `LiveStreamControllerTest` |

### 1.11 `pages` / `help` — partially migrated

| | |
|---|---|
| **Legacy features discovered** | 10 `pages/*.php` + 2 `help/*.php` files: privacy, about, mobile-app, 3 Google-Form intake pages (estebian/mo7fzat-quran/tatw3-w2a-team), `index.php` (dead stub), `social.php`, `ramadan.php` + 2 frozen variants, `help/share.php` |
| **Implemented in Laravel** | privacy, about, mobile-app, all 3 Google-Form pages (6 of 12 real files) |
| **Intentionally deferred (per the Blueprint's own Roadmap, not this session)** | `ramadan.php`/`ramadan1442.php`/`ramadan-archive.php` + `help/share.php` — Roadmap task 6.3, gated on Business Confirmation #2 |
| **Confirmed dead/unreachable** | `pages/index.php` (2-line `die()` stub — deliberate directory-listing guard, per `pages.md` §5, moot in Laravel) |
| **Business confirmations still required** | #2 (task 6.3's gate) |
| **Implementation Findings** | IF-008 |
| **Blueprint tasks** | 2.1, 2.2, 2.3, 2.4; task 6.3 (the gated remainder) |
| **Test coverage** | `StaticPagesTest`, `MobileAppAndFormsPagesTest`, `LegacyPageUrlRedirectsTest` |
| **Gap (see §3.1)** | `pages/social.php` is **not** covered by task 6.3's gate (that task names only `ramadan*.php` + `help/share.php`) or any other Roadmap task — it has fallen through a real crack between the audit's own recommended order (`pages.md` §9: "(3) `social.php` — fix the image path and routing gap") and the frozen Roadmap's actual task list, which never created a task for it. See §3.1. |

### 1.12 Identity / Admin (scaffolding) — infrastructure only, no features

| | |
|---|---|
| **Legacy features discovered** | N/A — this is the Laravel-side auth infrastructure (`auth` is a "virtual module" per Blueprint §7), not a legacy PHP module with its own files |
| **Implemented in Laravel** | `VbulletinSessionGuard`, `AdminGuard`, `LegacyPasswordVerifier`, Spatie roles/permissions, `EnsureAdminHasRole` middleware (registered, zero real consumers yet) |
| **Intentionally deferred** | `VbUserReader` anti-corruption-layer interface (decision-log #2, evidence-based trigger, not yet met) |
| **Confirmed dead/unreachable** | N/A |
| **Business confirmations still required** | None blocking |
| **Implementation Findings** | None (this is infrastructure, not legacy-behavior discovery) |
| **Blueprint tasks** | 0.3, 0.4, 0.5, 1.6 |
| **Test coverage** | `AdminGuardTest`, `VbulletinSessionGuardTest`, `RoleSeederTest`, `LegacyPasswordVerifierTest`, `EnsureAdminHasRoleTest`, `DomainBoundaryTest` (Pest arch test, Blueprint §2) |

### 1.13 `admincp` — Wave 5, not started (per Blueprint's actual Roadmap)

| | |
|---|---|
| **Legacy features discovered** | 9 feature directories (`authors`, `backup`, `broadcasting`, `chat`, `khotab`, `locations`, `questionnaire`, `soundcloud`, `survey`, `telawah`, `youtube` — 11 counted, `admincp.md` §9 classifies each individually), plus `forumConfig` (confirmed orphaned, excluded) |
| **Implemented in Laravel** | None (routes/admin.php is an explicit, documented empty skeleton) |
| **Intentionally deferred** | The entire module — Roadmap Wave 5 (tasks 5.1-5.7), not yet started; this session's Waves so far have followed the Content-domain critical path (Blueprint's own "two parallel chains" — Content and Admin are independent, Admin was never blocking) |
| **Confirmed dead/unreachable** | Per `admincp.md` §9/§17 (not re-verified this session): `authors/index.php`'s `die('hhhh')` path, `broadcasting/delete_stream.php`/`edit_author.php`'s no-op saves, `locations/add.php`'s no-op INSERT, `chat/automation_room.php`, `admincp/forumConfig/` |
| **Business confirmations still required** | #10 (survey counter-cache edge case, non-blocking per task 5.1's own note) |
| **Implementation Findings** | None yet (module not started) |
| **Blueprint tasks** | 5.1 through 5.7 |
| **Test coverage** | None yet |

### 1.14 `fatawa` — gated, not started

| | |
|---|---|
| **Legacy features discovered** | Per `fatawa.md` (not re-verified this session): question submission/answer, browse-by-author, browse-by-channel, today's-fatwa, subtopics/topics tree, single-question view, email |
| **Implemented in Laravel** | None |
| **Intentionally deferred** | Entire module — Roadmap task 6.1, gated on Business Confirmation #1 (reachability) |
| **Confirmed dead/unreachable** | Not confirmed dead — reachability is exactly the open question. **This session's IF-026 (130/217 `.htaccess` rules dead) is new, materially relevant evidence for Business Confirmation #1** that didn't exist when the audit originally raised it — worth surfacing to whoever owns that confirmation, not a decision this document makes unilaterally |
| **Business confirmations still required** | #1 |
| **Implementation Findings** | None yet (not started) — but 5 confirmed SQL-injection files (Blueprint Part I) are flagged for a mandatory fix regardless of reachability outcome (task 6.1's own text) |
| **Blueprint tasks** | 6.1 |
| **Test coverage** | None |

### 1.15 `advanced-search` — gated, not started

| | |
|---|---|
| **Legacy features discovered** | Sitewide search (per `advanced-search.md`, not re-verified this session) |
| **Implemented in Laravel** | None. **Not to be confused** with `khotab/search.php`, which this session did fully migrate (§1.1) — two different legacy files, coincidentally similar names |
| **Intentionally deferred** | Entire module — task 6.2, gated on Business Confirmation #1 (same gate as `fatawa`) |
| **Confirmed dead/unreachable** | Not confirmed dead — same open question as `fatawa` |
| **Business confirmations still required** | #1 |
| **Implementation Findings** | None yet — a confirmed SQL-injection-adjacent pattern is flagged for a mandatory fix regardless of outcome (Blueprint Part I, task 6.2) |
| **Blueprint tasks** | 6.2 |
| **Test coverage** | None |

### 1.16 `chat_room` — split status, one half has no Roadmap task at all

| | |
|---|---|
| **Legacy features discovered** | Per `chat_room.md` (not re-verified this session): lesson-browsing (content half) + live chat room (engagement half) |
| **Implemented in Laravel** | None |
| **Intentionally deferred** | Live-room half — task 6.5, gated on Business Confirmation #4 |
| **Confirmed dead/unreachable** | Not confirmed dead |
| **Business confirmations still required** | #4 (live-room half only) |
| **Implementation Findings** | None yet |
| **Blueprint tasks** | 6.5 (live-room half only) |
| **Test coverage** | None |
| **Gap (see §3.3)** | The **content/lesson-browsing half has no Roadmap task number anywhere in Waves 0-6**, despite Blueprint §4 explicitly naming it as one of exactly 4 confirmed consumers of `ContentListingService` ("`khotab`, `categories`, `channels`, `chat_room`'s lesson half") and §7 explicitly splitting it into Content vs. Engagement. This is the clearest "Blueprint commits to scope the Roadmap never assigned a task to" found in this analysis. |

### 1.17 `surveys` — Wave 5 (Admin), not started

| | |
|---|---|
| **Legacy features discovered** | Poll voting, results display (per `surveys.md`, not re-verified this session) |
| **Implemented in Laravel** | None |
| **Intentionally deferred** | Entire module — tasks 5.1/5.2 |
| **Confirmed dead/unreachable** | `holdtitle` reference (confirmed dead column reference, `surveys.md` §5) |
| **Business confirmations still required** | #10 (counter-cache at survey-close, non-blocking) |
| **Implementation Findings** | None yet |
| **Blueprint tasks** | 5.1, 5.2 |
| **Test coverage** | None |

### 1.18 `radio` — **audited only in name; the module doc itself was never filled in**

| | |
|---|---|
| **Legacy features discovered** | Unknown — `radio.md` (the audit's own module doc) is a stub: every section reads "Not yet analyzed," "Status: Not Started" |
| **Implemented in Laravel** | None |
| **Intentionally deferred** | Nominally — `radio.md` states "Scheduled: Wave 3" — but this scheduling was never honored (Wave 3 shipped `live-stream`+`channels` only) and `radio` does not appear anywhere in the Master Blueprint's module-to-domain mapping table (§7) or the Implementation Roadmap's task list (Waves 0-6) |
| **Confirmed dead/unreachable** | Not assessed — real files exist (`functions.php`, `index.php`, `indexXX.php`), reachability not checked by this or any prior session |
| **Business confirmations still required** | Unknown — cannot be determined without the analysis this module was scheduled for but never received |
| **Implementation Findings** | None |
| **Blueprint tasks** | **None** |
| **Test coverage** | None |
| **Gap (see §3.1, §3.3)** | **The clearest single "forgotten" module in this entire analysis** — not confirmed dead, not intentionally deferred with a real reason, not covered by any Blueprint task, and its own audit doc admits it was never actually analyzed despite being scheduled to be. |

### 1.19 `w2a_autocomplete`, `classes`, `crons`, `cds/cds.php`, `stats`, `english`, `vendor-vbulletin-forum` — confirmed excluded, correctly untouched

| Module | Disposition | Evidence |
|---|---|---|
| `w2a_autocomplete` | Folds into Content, no independent code | Blueprint §7 |
| `classes`, `crons`, `vendor-ezsql` | Superseded by Laravel features | Blueprint §7 |
| `cds/cds.php` (standalone, distinct from `w2acd/cds.php`) | Confirmed dead/superseded | Blueprint "Never — Out of Scope" list |
| `stats/` | Vendor JS bundle, not app code | Blueprint §7, confirmed by this session's own earlier file-type check |
| `admincp/forumConfig/` | Confirmed orphaned/never-executed | Blueprint Part I |
| `english/` | External, gated on Business Confirmation #3 | Blueprint §7, task 6.6 |
| Bundled vBulletin forum fork, live vBulletin itself | External, not migrated by design | Blueprint §7/§10 |

No coverage gap in this group — every item here has an explicit, evidenced exclusion reason, checked against both the Blueprint and (where re-verifiable) this session's own file inspection.

---

## 2. Project-Wide Summary

| Metric | Count | Note |
|---|---|---|
| **Total legacy features identified** | ~210 | Sum of §1's per-module tallies for every module with at least a module doc; modules whose doc is itself a stub (`radio`) are excluded from this count entirely rather than guessed at — see the estimation-method note below |
| **Total migrated (implemented + tested)** | ~140 | khotab (34) + categories (partial, ~6) + vars (4) + w2acd (~6) + gallery (~3) + anasheed (~10) + telawah (~6) + channels (3) + live-stream (3) + pages/help (6) |
| **Total intentionally deferred** | ~35 | Documented with a specific, checkable reason (controller docblock, decision-log, or a Roadmap gate/Business Confirmation number) — send-friend features, `.grx` downloads, category tree/series pages, `ramadan*`/`share.php` (task 6.3), khotab/telawah admin CRUD (task 6.4), `chat_room`'s live-room half (task 6.5), `english/`'s fate (task 6.6) |
| **Total confirmed dead/unreachable** | ~20 | `old.php` files, orphaned channel files, IF-026/IF-029-affected routes, the confirmed "Never" list (§1.19) |
| **Total pending business confirmations** | 6 distinct numbered items still open (#1 fatawa/advanced-search reachability, #2 ramadan/share.php, #3 english/'s fate, #4 chat_room live-room, #6 khotab/telawah admin CRUD, #10 survey counter-cache) + 4 module-specific candidates raised by this session's own findings (IF-004/IF-006/IF-012/IF-013, not yet formally added to Blueprint Part IV) |
| **Total open Implementation Findings** | 29 (IF-001 through IF-029) | All 29 have a recorded Decision; 27 have a completed Test; IF-018 was fixed with a test in Wave 4 despite an earlier draft of that entry saying "to be added" (already corrected in the log itself) |
| **Estimated migration completeness** | **~35-40%** | See method below — deliberately given as a range, not a false-precision single number |

**Estimation method, stated explicitly:** this is a **feature-count** estimate (~140 of ~210 identified features are migrated ≈ 67% *of identified features*), then discounted for two things a feature count alone hides: (1) roughly a third of the *modules* in the Blueprint's own scope (`admincp`'s 9 directories, `fatawa`, `advanced-search`, `chat_room`, `surveys`, `radio`) have **zero** Laravel work and are weighted here by real remaining effort (Blueprint calls `admincp` alone "L" complexity, comparable to all of Wave 4 combined), not by their feature count relative to already-small modules like `gallery`; (2) `radio`'s completeness can't even be estimated (§1.18) and is treated as 0% rather than excluded, since excluding an unknown from the denominator would silently inflate the percentage. The resulting ~35-40% is this analysis's own estimate, cross-checked against — not copied from — the Wave 4 report's "≈65% by task-count proxy" figure, which measured a *different* thing (completed Roadmap tasks ÷ total Roadmap tasks, weighted equally regardless of task size) and was explicitly flagged in that report as not effort-weighted. Both numbers are legitimate answers to different questions; neither should be read as "the" completion percentage without its method attached.

---

## 3. Negative Audit

### 3.1 PHP files never represented by any Laravel route, controller, service, or documented deferment

Checked every legacy directory this session has direct file-level knowledge of (§1.1-§1.11, §1.19) against routes/controllers/decision-log/docblocks. Results, beyond what's already itemized in §1's per-module rows:

| File(s) | Status | Evidence |
|---|---|---|
| **`vars_categories/*` (6 files, 3 live routes)** | **Unaccounted — no implementation, no deferment, no Blueprint task** | §1.3. The single largest gap this audit found. |
| **`pages/social.php`** | **Unaccounted — real feature, no Roadmap task covers it** | §1.11, §3.3 |
| **`radio/*` (3 files)** | **Unaccounted — module doc itself was never completed** | §1.18 |
| `categories/downitems.php` | Reachable (2 real `.htaccess` rules, `khotab-series-{id}.grx`), not built, not explicitly deferred anywhere (only implicitly, by omission) | Should receive the same explicit "deferred, `.grx` GetRight class" docblock note `khotab_send_friend()` got, not silence |
| `categories/item.php` | No `.htaccess` rule found — likely genuinely dead, not previously flagged | New observation, not elsewhere documented |
| `telawah/more.php` | Confirmed IF-026-dead (routes to missing `new_modules.php`) but the "why not built" reasoning lives only in this session's prose (Wave 4 report), not in any controller docblock the way `gallery`'s equivalent got | Minor documentation-completeness gap, not a functional one |
| `khotab/old.php` (1,752 lines) | No route, presumed superseded — not individually re-confirmed this session (inherited from the original audit's characterization, not re-verified) | Flagged as inherited-confidence, not this session's own direct finding |

**Everything else checked** (every file in `khotab/`, `categories/`, `channels/`, `live-stream/`, `gallery/`, `w2acd/`, `anasheed/`, `telawah/`, `pages/`, `help/`) resolves to one of: implemented, explicitly deferred with a citable reason, or confirmed dead with evidence — no further unaccounted files found in those directories.

### 3.2 Legacy behaviors neither migrated, intentionally deferred, nor classified as dead

Distinct from §3.1 (whole files) — this is about *behaviors within already-partially-covered files*.

- **`AnasheedGroup.icon`/`AnasheedItem.frame` bucketed thumbnails** and **the `anasheed`/`telawah` ancestor-breadcrumb trail** — both already identified in `post-wave-4-cross-wave-architecture-review.md` §2/§5. Not re-discovered here; repeated only so this document's answer to "can we account for everything" doesn't silently drop a previously-found gap.
- **Comment-posting's `uid` attribution** (hardcoded `0`, never resolved via `VbulletinSessionGuard`) — same review, §1. Still open; not in the 3 items fixed after that review (§2 of the follow-up work).
- **`channels/author.php`'s "Recommended For You" widget** (§1.9) — deferred in Wave 3 for a reason (`KhotabItem` not existing yet) that stopped being true in Wave 4 and was never revisited. This is a real, confirmed instance of the exact category §3.2 is looking for: not migrated, nominally "deferred" but the deferral's own stated condition has since resolved, and not dead. Low severity (a sidebar widget on one page), but a clean example of how a documented deferment can go stale silently if nothing re-checks it once its blocker clears.
- No further undocumented behavioral gaps found within already-migrated modules beyond what the cross-wave review and this session's own Implementation Findings already cover — checked directly against `khotab`/`anasheed`/`w2acd`/`gallery`/`telawah`/`channels`/`live-stream`'s controllers, not inferred.

### 3.3 Blueprint tasks that no longer map cleanly to the current implementation

Two confirmed cases, both genuine, neither previously written down in exactly this form:

1. **Task 4.7's own scope ("absorbs `vars/`'s one live capability, 4 themed routes") was not actually complete when Wave 4 was reported done.** The 4 routes were built one informal "Wave" later, after this session had already declared Wave 4 finished and moved on. This doesn't change the *correctness* of what was built — the fix is real and tested — but it means the Wave 4 completion report's claim should be read as "task 4.7, minus its `vars/` clause, complete," not unqualified. Recorded here for the historical record; not going back to edit the Wave 4 report itself.
2. **`chat_room`'s content/lesson-browsing half has no Roadmap task number at all**, despite being named as real, committed scope in Blueprint §4 (a `ContentListingService` consumer) and §7 (the Content-domain half of the module). Every other named §4 consumer (`khotab`, `categories`, `channels`) has a Roadmap task; this one doesn't. This is a genuine Blueprint/Roadmap internal gap, not an implementation gap — nothing to build differently, but worth a Roadmap addendum before whoever picks up `chat_room` assumes task 6.5 covers all of it (it only covers the live-room half).

**One near-miss, checked and ruled out:** `w2acd` building models (task 4.5) without task 4.4's data migration first looked at first read like a similar "doesn't map cleanly" case — but this one *was* explicitly decided and recorded (cross-wave review, decision-log #7's surrounding context), just not with its own dedicated decision-log entry. Distinguished here from the two real gaps above: this one has a paper trail, even if not a perfectly tidy one.

### 3.4 Laravel implementation that exists without a corresponding legacy capability

Checked every model/controller/service for functionality with no legacy call site behind it.

- **None found.** Every Laravel route, controller action, and model method traces to a specific legacy file/function cited in that class's own docblock — verified by spot-checking a sample across every migrated module (`KhotabItemController`, `W2acdController`, `AnasheedNewsController`, `CategoryController`, `GalleryController`), not just asserted.
- The closest candidates, both correctly *not* counted as "invented" capability:
  - `MediaPathResolver`/`ContentListingService`/`ContentSidebarWidget` — shared services with no single 1:1 legacy file, but every method is traced to a specific duplicated legacy function (P-011/P-014/P-016), not invented behavior.
  - `AnasheedItem::scopeInGroup()` — new code (Wave 5), but it's an extraction of legacy `list_anasheed()`'s own confirmed group-98 conditional, not new behavior.
- **`EnsureAdminHasRole` middleware is the one piece of Laravel code with no legacy behavior to trace to at all** (there is no legacy "role middleware" — Spatie/Laravel-native infrastructure) — but this is expected and already flagged (cross-wave review, §2/§7): it's forward-looking scaffolding for Wave 5's admin work, not a port of anything, and its own docblock says so.

### 3.5 Documentation inconsistencies between Blueprint, decision log, implementation findings, and current code

- **IF-026 vs. Blueprint Part I** — already found and corrected (`implementation-findings.md`'s own addendum, added after the cross-wave review). Not re-litigated here beyond confirming the correction is still in place.
- **§3.3's two Roadmap-mapping gaps above** are, themselves, Blueprint/Roadmap-internal documentation inconsistencies (a Roadmap task's actual scope vs. its text; a Blueprint-committed capability vs. an absent Roadmap task) — cross-referenced here rather than repeated.
- **This session's own "Wave" numbering vs. the Blueprint's Roadmap wave numbers has diverged.** Waves 0-4 (as executed) matched the Blueprint's Roadmap Waves 0-4 exactly. What this session informally called "Wave 5" (the `vars/more.php` fix) does not correspond to the Blueprint's actual Wave 5 (which is entirely `admincp`/Admin-domain — tasks 5.1-5.7). The work done was legitimate (it completed a piece of task 4.7), but continuing to label upcoming Content-domain work (`fatawa`, `chat_room`, `surveys` were all mentioned as "next" before this document was requested) as "Wave 5" would compound a naming mismatch against the Blueprint's own frozen wave definitions. **This is the most actionable single item in this whole document** — worth resolving explicitly (either by mapping this session's next steps to the Blueprint's real Wave 5/6 numbers, or by formally recording that this session's wave count is a separate, informal execution-order tracker) before more work accumulates under a mismatched label.
- **No inconsistency found** between `decision-log.md`'s 7 entries and the code they describe — each entry's "Impact" section was spot-checked against the actual current file state (e.g. decision #6's `KhotabGroup.php`, decision #7's `CommentPosted.php`/`GeoIpLookup.php`) and matches.
- **No inconsistency found** between `implementation-findings.md`'s 29 entries' "Decision" fields and the actual current behavior of the corresponding code (spot-checked a sample of 8 across khotab/w2acd/gallery/anasheed, not all 29 individually).

---

## 4. Can We Account for Every Known Legacy Capability?

> **Status update (post-gap-closure-action-plan):** the 4 exceptions below are no longer unclassified gaps. `docs/reviews/gap-closure-action-plan.md` re-verified each against the legacy source directly, and `00-implementation-roadmap.md` has been amended accordingly: task **2.5** (`pages/social.php`), task **4.10** (`radio`, scope narrowed to the confirmed-live subset — see the action plan for what's confirmed dead), task **4.11** (`chat_room`'s content half; the live-room half stays task 6.5, unchanged), and a note on task **4.2** closing `vars_categories/`'s 3 routes as redirects rather than a new module (it turned out to be a superseded duplicate of `categories/`, not distinct scope). As of this update, all 4 are **Planned**, not unclassified. The original findings below are left as-written since they're what motivated the amendment — not because they're still the current state.

**No — with 4 specific, evidenced exceptions, all listed above, none of them silent.**

The honest answer has two parts:

**For every module this session has actually touched** (khotab, categories, vars, w2acd, gallery, anasheed, telawah, channels, live-stream, pages/help, plus Identity/Admin infrastructure) — **yes, with high confidence.** Every implemented feature traces to a cited legacy file/function; every deferred feature has a specific, checkable reason (a decision-log entry, a controller docblock, or a Blueprint Roadmap gate/Business Confirmation number); every confirmed-dead feature has direct evidence (a missing `.htaccess` rule, an undefined function, a routing target that doesn't exist). §3.1-§3.2's within-module gaps are real but small (mostly cosmetic — missing thumbnails, a missing breadcrumb) and already tracked in the cross-wave review.

**For the migration as a whole — no, not yet, and this document's own negative audit is what surfaces why:**

1. **`vars_categories/`** — a real, live, 3-route module with zero Laravel coverage and zero documentation of its absence, structurally identical to `categories/` and easy to mistake for already-covered by it. The highest-priority item in this document.
2. **`radio/`** — genuinely forgotten in the literal sense: scheduled for Wave 3 analysis, never analyzed, absent from both the Blueprint's module mapping and the Roadmap's task list. Its actual scope is unknown, not merely unbuilt.
3. **`pages/social.php`** — a complete, real feature the original audit explicitly recommended porting, that fell out of the Roadmap's actual task list between the audit and the frozen Blueprint.
4. **`chat_room`'s content half** — committed as real scope in the Blueprint's own §4/§7, with no Roadmap task number assigned to build it.

None of these are behaviors that were *checked and misclassified* — they're gaps in the accounting itself, found by checking the Blueprint/Roadmap's own internal completeness rather than re-checking already-made decisions. That distinction is the point of a negative audit: items 1-4 above were not previously known to be missing by anyone, including this session, until this document went looking specifically for them.
