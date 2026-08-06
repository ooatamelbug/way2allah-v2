# Project Handoff — way2allah / 7amlat Legacy-to-Laravel Migration

**Last updated:** 2026-08-06. **Purpose:** the single document a new engineer or a new AI session should read first. Everything here is a summary of decisions and state already fully reasoned-through and recorded elsewhere — this document tells you *what's true now* and *where to go for the "why."* Don't re-derive anything below from scratch; verify it against the cited source if you need more depth, but treat this as accurate as of the date above.

---

## 1. Current Project Status

A ~20-year-old procedural PHP-Nuke-derived Islamic media site (`legacy-project/`) is being migrated to Laravel (`laravel-project/`) **side-by-side** — both run simultaneously against the same production database throughout, with URL-pattern ownership flipping from legacy to Laravel incrementally, module by module. No big-bang cutover. No legacy code is modified as part of this migration (the legacy tree is read-only reference).

**Phase just closed:** Wave 5 (Admin domain, all 10 tasks: 5.1-5.10) plus task 3.4 (`surveys/`, public poll voting, Engagement domain's first real capability) — planned via a full direct-evidence analysis and reconciliation pass, then fully implemented. **258/258 tests passing, 0 PHPStan errors (level 5, zero suppressions).** See `docs/reviews/wave-5-completion-report.md` for full detail.

**⚠️ Operational note:** `laravel-project/` is a git repository with **zero commits** — everything is currently untracked. Before any further work, either commit the current state as a baseline or confirm with the user why it's been left uncommitted this long. Don't assume version history exists to fall back on.

---

## 2. Completed Milestones

- **Wave 0 (Foundation):** Laravel scaffold, domain folder structure, 3 named DB connections (`main`, `vbulletin`, `flashchat`), both auth Guards (`VbulletinSessionGuard`, `AdminGuard` — ADR-0011), Spatie Laravel-Permission wired, `LegacyUrlCompatibility` skeleton, Pest arch tests in CI, PHPStan/Larastan at level 5.
- **Wave 1 (Shared services):** `ContentListingService`, `ContentSidebarWidget`, `RecordsView`/`ContentViewed` (view-counting), `MediaPathResolver`, shared `Channel` model.
- **Wave 2 (Zero-risk static wins):** `pages/privacy.php`, `help/about.php`, `pages/mobile-app.php`, 3 external-Google-Form pages, plus **`pages/social.php`** (task 2.5, added post-Wave-4).
- **Wave 3 (Simplest Core content):** `live-stream`, `channels`.
- **Wave 4 (Core content, main body):** `khotab` + `categories` (together), `gallery`, `w2acd` (post P-015 data migration), `anasheed` (absorbs `vars/`'s one live capability), `telawah`. Plus two post-Wave-4 additions: **`radio`** (task 4.10) and **`chat_room`'s lesson-browsing half** (task 4.11).
- **Gap-closure phase:** the coverage audit (`docs/reviews/legacy-vs-laravel-coverage.md`) found 4 legacy capabilities the Blueprint/Roadmap had never accounted for. All 4 are now closed: `pages/social.php`, `radio`, `chat_room`'s content half (above), plus `vars_categories` (confirmed a superseded duplicate of `categories/`, closed via 1 redirect route rather than reimplementation — 2 more redirects remain blocked on pre-existing deferred work, see §3).
- **Consistency verification (post-Wave-4 gap-closure phase):** two full review passes (`post-gap-closure-consistency-review.md`, `final-phase-verification.md`) confirmed Blueprint, Roadmap, Decision Log, Implementation Findings, module docs, implementation, and tests are mutually synchronized. One real code bug found and fixed in the process (a wrong static-asset path); one Blueprint drafting inconsistency found and corrected.
- **Wave 5 (Admin domain) — all 10 tasks done:** `Survey`/`SurveyQuestion`/`SurveyAnswer` models + full admin CRUD (5.1/5.2), a unified Spatie-based permission editor replacing 5 duplicated/mostly-broken legacy copies (5.3), `soundcloud`/`youtube`/`locations` (5.4, dead add-flow rebuilt), `questionnaire/` (5.8), `broadcasting/`'s working half (5.10), `chat/`'s working half (5.5, its edit form rebuilt from scratch — IF-034 found it had no legacy backend at all), `khotab/uploader(s).php` (5.9), and a consolidated `authors`/`backup` staff-management rebuild with no fixed default password (5.6/5.7).
- **Task 3.4 — `surveys/` (public PHP-Nuke poll voting), Engagement domain's first real capability:** `Poll`/`PollOption` models, vote/results/list, 2 known legacy bugs fixed (a fatal error for logged-in voters, a results page that never showed its own poll title), `RateLimiter`-based vote dedup replacing the manual IP-window table.

**Not started:** Wave 6 (Confirmation-gated items) — correctly, since every item there is gated on its own Business Confirmation.

---

## 3. Remaining Deferred Items (and why)

| Item | Deferred until | Reason |
|---|---|---|
| `vars-categories.htm` → `categories.htm` redirect | `categories.htm` (tree/index) itself is built | That target route doesn't exist in Laravel yet — it's a pre-existing Wave 4 deferral (`CategoryController`'s own docblock), not new. Redirecting to a nonexistent route would trade one broken URL for another (IF-031). |
| `vars-category-series-{id}-{id2}.htm` → `category-series-{id}-{id2}.htm` redirect | Same as above | Same reason — `category-series-*` is also a pre-existing Wave 4 deferral. |
| `chat_room`'s weekly-lesson-schedule feature (`nuke_hedaya_lessons`, `chat_room/table.php`) | Business Confirmation #4 resolves | Every row it renders links to a *live voice room*, not recorded content — building it ahead of knowing whether live rooms still matter risks the same wasted-effort pattern Blueprint Appendix F warns about for confirmation-gated work (IF-033). |
| `chat_room`'s live-room half generally (Roadmap task 6.5) | Business Confirmation #4 | The room *directory* page currently shows a Zoom meeting link, not the room list — real usage of the FlashChat-based room system is unconfirmed. |
| AEP-1 (`ContentGroup` hierarchy extraction — `W2acdGroup`/`AnasheedGroup`/`TelawahGroup`'s shared shape) | Wave 6 complete (Wave 5 now is) | Explicit user directive: architectural extractions should be driven by the complete application's shape, not a partial view of it. Wave 5 finished this phase — only Wave 6 remains before this can be revisited. |
| Everything in Roadmap Wave 6 (`fatawa`, `advanced-search`, `pages/ramadan.php`, `help/share.php`, khotab/telawah admin CRUD, `english/`'s fate) | Each item's own Business Confirmation (Part IV, see §4) | Explicit Blueprint governance rule: no confirmation-gated module should be implemented first, regardless of apparent business value — effort spent risks rework once the confirmation resolves. |
| `khotab/uploader.php`'s "add new uploader by forum-member-id" form | Product-intent confirmation | No backend exists anywhere in the legacy file to infer the intended behavior from (task 5.9's own scope note) — the list/sort/recompute/backfill parts of the same page are built and working. |
| `admincp/backup/`'s content-backup-booking UI (`nuke_backup_booking`) | Business Confirmation #7 | Real table, real query logic in the legacy source, but the only surfacing UI was a query bolted onto an unrelated staff-list page — task 5.6's rebuild deliberately omitted it pending confirmation this is an active need. |

---

## 4. Open Business Confirmations

From Blueprint Part IV (13 items) — **none of these block starting Wave 5**, resolve in parallel whenever convenient:

1. `fatawa`'s / `advanced-search`'s actual production reachability
2. `pages/ramadan.php`'s / `help/share.php`'s current promotional status
3. `english/`'s status — still offered, or dormant
4. `chat_room`'s live voice-room feature vs. the Zoom flow that may have superseded it
5. `nuke_users`'s significance and `vb5.php`'s disabled password check (ADR-0011 §9.4)
6. Khotab/telawah admin content-CRUD — design fresh, or source from `old.way2allah.com`?
7. `admincp/backup/`'s `nuke_backup_booking` concept — active need or abandoned?
8. Database relationship with `old.way2allah.com` (ADR-0009)
9. Whether any `nuke_authors` row still holds an exploitable weak/plaintext credential (needs a live DB read)
10. Survey-close-time consistency requirement (`SurveyAnswer` shape)
11. Team size / Laravel-DDD experience level
12. Project timeline/urgency
13. Whether the legacy URL set has real current SEO value worth full 217-rule preservation

Plus Part V (6 **infrastructure** confirmations — target hosting environment is the root question, resolves most of the rest automatically): hosting environment, Redis/Memcached availability, vBulletin DB network reachability, queue driver choice, the `media/` directory download (longest-standing blocker), and Search Console/analytics data for URL SEO value (feeds #13 above).

---

## 5. Current Architecture Overview

**Domain-driven modular monolith**, `app/Domain/{Content,Admin,Identity,Engagement,Pages}/` + `app/Support/`. No Domain/Application/Infrastructure layering — flow is `Controller → [Form Request] → Action/Service → Model → DB`. Boundary enforcement via Pest arch tests (`pestphp/pest-plugin-arch`), not a second static-analysis tool.

**Content domain** (by far the most built-out — 21 controllers, 22 models):
- `ContentListingService` — one explicitly-named method per confirmed legacy query shape (author-scoped, category-scoped, channel-scoped, location-scoped for `chat_room`, etc.). Never one generic parameterized method — the shapes genuinely differ per legacy call site, and forcing them together would hide real behavioral differences.
- `ContentSidebarWidget` — same one-method-per-shape discipline, for the "most downloaded / most recent" sidebar pattern duplicated across ~8 modules now.
- `RecordsView` trait + `ContentViewed` event/listener — the one real domain event besides `CommentPosted`, replacing 7+ independently-duplicated hit-counter implementations.
- `MediaPathResolver` — reproduces the legacy `floor(id/1000)` thumbnail-bucketing convention exactly; a compatibility shim for the *existing* media library, not a new-upload design.
- `Channel` — single shared model, referenced (never duplicated) by `khotab`, `live-stream`, `channels`.

**Identity domain:** two Guards, no unified user model. `VbulletinSessionGuard` (public site, validates legacy cookies directly against vBulletin's tables) and `AdminGuard` (backed by `nuke_authors`, rehash-on-login to bcrypt). See ADR-0011 for the full reasoning — this asymmetry is deliberate, not incomplete.

**Admin domain** (Wave 5, now built out — 9 controllers, 8 models, 2 Actions):
- **Permission model (decision-log #9):** one Spatie `Permission` per legacy `admincp/*/menu.php` authorization key, namespaced `{module}.{key}` (e.g. `survey.modsurvey`, `chat.listrooms`) — replaces `nuke_authors.permissions`'s serialized-array blob. Layered on top of Wave 0's coarse `super-admin`/`admin` Roles: a super-admin bypasses every permission check (`EnsureAdminHasPermission`, checked directly in the middleware, not via `Gate::before` — Spatie's own permission checks don't route through Laravel's Gate). Seeded by `database/seeders/AdminPermissionSeeder.php`.
- **`Survey`/`SurveyQuestion`/`SurveyAnswer`** — the custom survey engine (`admincp/survey/`), Admin-domain only; confirmed to have **no public voting UI anywhere in this codebase** (exhaustive grep) — do not confuse with `Poll`/`PollOption` below.
- **`Room`** (connection: `flashchat`) — FlashChat voice-room administration; `owner`/`speaker` are comma-separated vBulletin usernames, reproduced as-is.
- **`SiteOption`** — the legacy `get_option()`/`update_option()` key-value settings table (`nuke_options`), backing `soundcloud`/`youtube` embed config.
- Every Admin route requires `admin.role` (any authenticated admin) plus, per feature, `admin.permission:{module}.{key}` — see `routes/admin.php`'s own comments for the full gate-per-route map.

**Engagement domain** (task 3.4, first real capability — previously empty scaffolding):
- **`Poll`/`PollOption`** (PHP-Nuke's native poll system, `nuke_poll_desc`/`nuke_poll_data`) — genuinely unrelated to Admin's `Survey` despite the similar name; two independent systems, confirmed via exhaustive grep before either was built.
- Vote deduplication uses Laravel's `RateLimiter` (30-minute window), not a ported IP-check table.

**Database:** shared production MySQL throughout coexistence — Laravel connects to the *same* database the legacy app uses, never a copy. No big-bang schema migration; existing tables map onto Eloquent models as-is (`protected $table = 'nuke_islamic_khotab'`). Any schema change to a table legacy code still writes to must be additive-only (new nullable columns, never renames/drops) for the entire coexistence period.

**Routing:** no `op=` dispatch on the Laravel side — every legacy branch becomes an explicit named route. Two URL patterns, applied consistently:
1. Real, live `.htaccess` rule → Laravel route registered at the *exact same path* (no redirect).
2. No `.htaccess` rule at all → new clean Laravel path + a redirect from the raw legacy `.php` path, registered in `config/legacy-url-map.php`.
3. **A third pattern, added this phase** (decision-log #8): no `.htaccess` rule, but real standing site chrome (nav/header) already links to the pretty path → register the route at that exact pretty path directly, same as pattern 1, since the intent was always for it to resolve there.

**Testing:** every controller/route has a Feature test using an in-memory SQLite connection (`Tests\Support\InMemoryConnection` + `Tests\Support\Fixtures\MainSchema` — the canonical, shared table-fixture definitions; never redefine a table's columns locally in a test file). Legacy-vs-Laravel behavioral fidelity is the test target, not just "200 OK."

---

## 6. Current Roadmap Progress

Roadmap file: `legacy-project/docs/migration/00-implementation-roadmap.md` (511 lines — derived from the Blueprint, does not modify it).

| Wave | Status |
|---|---|
| 0 — Foundation | ✅ Done |
| 1 — Shared services | ✅ Done |
| 2 — Zero-risk static wins | ✅ Done (including post-Wave-4 task 2.5) |
| 3 — Simplest Core content | ✅ Done (including task 3.4, `surveys/`, added post-Wave-5-analysis) |
| 4 — Core content, main body | ✅ Done (including post-Wave-4 tasks 4.10, 4.11) |
| 5 — Admin, feature-by-feature | ✅ Done (all 10 tasks: 5.1-5.10) |
| 6 — Confirmation-gated | ⬜ Not started (correctly — gated on Part IV confirmations) |

**Important naming-history note:** this project went through an earlier phase where "Wave" was used informally for legacy-audit rounds (a *different* numbering, in `00-migration-roadmap.md`, describing the pre-implementation analysis phase) before the Blueprint's real Wave 0-6 implementation numbering existed. If you see "Wave 3" or similar in `00-migration-roadmap.md`, that is the **audit-phase** wave count, not the implementation Roadmap's wave count. Always check which of the two `00-*roadmap*.md` files you're reading. `00-implementation-roadmap.md` (in the same directory) is the one that governs actual build order.

---

## 7. Known Risks and Assumptions

- **`media/` directory (content thumbnails) is still absent** from the working tree — a long-standing blocker (Blueprint Part V #5). `MediaPathResolver` is built and tested against fixture data but unverified against the real media library's full ID range.
- **Target hosting environment is unconfirmed** (Part V #1) — resolves Redis availability, queue driver, and the PHP-isolation mechanism (legacy needs PHP 7.2 EOL; Laravel needs materially newer — the two cannot share one PHP-FPM pool). Don't build around a specific isolation mechanism before this lands.
- **`admincp/`'s security findings (Blueprint §16) — now substantially addressed by Wave 5, not all of them.** Fixed: the 5×-duplicated permission-editor template (replaced with one real Spatie-backed implementation, task 5.3), weak MD5 password writes (now `Hash::make()`/bcrypt), the hardcoded `'way2allah'` default password (task 5.7, random-generated + immediately hashed), and — item #12, added post-completion-report during the Wave 5 verification review — the fact that legacy's `nuke_authors.permissions` blob never enforced page-level access anywhere **within `admincp/`**, only nav-link visibility; every Wave 5 route now enforces real per-permission authorization, a deliberate hardening beyond legacy's own behavior (decision-log #10). This does not hold sitewide — `chat_room/`'s unmigrated live-room half has one confirmed real page-level use of the same data, out of scope for this wave (IF-035). **Not addressed by this phase** (out of this wave's scope — legacy-side fixes, not Laravel-side): the plaintext-password login fallback and the non-`httponly` cookies still exist in the *legacy* app (Laravel's own `AdminGuard`, Wave 0, never had either).
- **`fatawa`/`advanced-search` may be entirely unreachable in production** (routes depend on a `new_modules.php` file confirmed absent from this codebase snapshot, IF-026) — don't assume they're live without Business Confirmation #1.
- **This session's git repo has zero commits** (see §1) — treat everything as provisional until committed.
- **Module docs under `docs/migration/modules/*.md`** (the pre-implementation audit tree, in `legacy-project/`) are **not** actively kept in sync with implementation progress — they're pre-implementation reference material. Two were found stale and corrected this phase (`radio.md`, `categories-and-vars_categories.md`); others have not been re-verified against current implementation and shouldn't be assumed current without checking.
- **Assumption carried forward, not yet stress-tested:** the side-by-side coexistence routing split (path-based, web-server-layer) has been *designed* and partially rehearsed (Wave 2's rollback rehearsal) but no wave has yet gone through a real production cutover in this engagement — treat the mechanism as sound-on-paper, not battle-tested.

---

## 8. Recommended Next Phase

**Waves 0-5 are now all complete.** Only **Wave 6 (Confirmation-gated)** remains in the Roadmap's main task list, and every one of its items is explicitly gated on its own Business Confirmation (§4) — Blueprint governance is explicit that none of them should be started speculatively.

**Recommended immediate action, not a Wave:** resolve as many of the 13 Business Confirmations (§4) as possible — Waves 0-5 are now fully built and blocked on nothing *except* confirmations, so this is now the actual critical path to further progress, not a background task. In particular:
- **Business Confirmation #9** (live weak/plaintext `nuke_authors` credentials) can likely be answered quickly with a single read-only production query, and directly informs how urgently the legacy-side plaintext-fallback/cookie-security items (§7) need remediating.
- **Business Confirmation #4** (`chat_room`'s live-room half vs. Zoom) unblocks both task 6.5 and the deferred weekly-schedule feature (§3).
- **Business Confirmation #6** (khotab/telawah admin CRUD) unblocks the one Admin-domain capability confirmed genuinely absent from this codebase.

**Once any Wave 6 item's confirmation resolves**, that item alone can start — Wave 6 is explicitly not an all-or-nothing gate (Blueprint §20).

**Do not start:** AEP-1 (still gated on Wave 6 completing — Wave 5 is now done, only Wave 6 remains), any Wave 6 item without its own confirmation, the 2 remaining `vars_categories` redirects, `khotab/uploader.php`'s add-flow, or `nuke_backup_booking`'s UI (all blocked on unrelated pre-existing deferrals or new confirmations — see §3).

---

## 9. Where Things Live (orientation for a fresh session)

| What | Where |
|---|---|
| Legacy source (read-only reference) | `legacy-project/` |
| Laravel app | `laravel-project/` |
| Master Blueprint (frozen, "no silent edit") | `legacy-project/docs/migration/00-master-migration-blueprint.md` |
| Implementation Roadmap (living, derived from Blueprint) | `legacy-project/docs/migration/00-implementation-roadmap.md` |
| Pre-implementation audit wave table (historical, different numbering — see §6) | `legacy-project/docs/migration/00-migration-roadmap.md` |
| Per-module legacy analysis (pre-implementation reference, not auto-synced) | `legacy-project/docs/migration/modules/*.md` |
| Decision Log (cross-module implementation decisions) | `laravel-project/docs/decision-log.md` |
| Implementation Findings (legacy-behavior discoveries, IF-001 through IF-034) | `laravel-project/docs/implementation-findings.md` |
| Review/audit documents (coverage analysis, architecture reviews, consistency checks) | `laravel-project/docs/reviews/*.md` |
| This handoff document | `laravel-project/docs/PROJECT-HANDOFF.md` |

**Standing project discipline** (apply this without being asked, it's what's kept this migration traceable): evidence-first (read the actual legacy source before implementing, don't infer from doc summaries alone); when a plan or prior finding turns out wrong, correct it *visibly* — a dated note or addendum, never a silent rewrite; reserve decision-log entries for genuinely cross-module patterns, not local fixes (those get a code docblock instead); re-run the full test suite and PHPStan after every logical step, not batched at the end; keep Blueprint edits rare, dated, and explicitly approved — everything else (Roadmap, logs, findings) can be updated directly as living documents.
