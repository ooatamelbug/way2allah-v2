# Implementation Decision Log

Lightweight chronological record of implementation-phase decisions — distinct from `00-architecture-decisions/ADR-*.md` (the legacy audit's permanent, fully-reasoned records for major architecture decisions made *before* implementation began) and from `implementation-findings.md` (legacy-behavior discoveries, not process/tooling decisions). This log exists for exactly the kind of decision made at the end of the pre-Wave-4 review: real, but not individually significant enough to warrant its own ADR.

Format: Decision, Reason, Alternatives considered, Trade-offs, Impact, Confidence.

---

## 1. `MediaPathResolver`'s location (`Content/Support/`, not `Content/Services/`) is left as-is; not yet corrected in the Blueprint

- **Decision:** Neither move the class nor update the Blueprint now. The finding (`pre-wave-4-implementation-review.md` §1.1, `pre-wave-4-decision-support.md` §1) stands as a documented, accepted proposal — revisit after Wave 4, once more utility-style classes exist to generalize from.
- **Reason:** The Blueprint's own definition of `Services/` ("cross-model orchestration") and its own example (naming `MediaPathResolver` under `Services/`) already disagree with each other, independent of what implementation did — diagnosed as a documentation inconsistency in the Blueprint itself, not an implementation error. Correcting it now, from a single data point, risks over-fitting a general rule to one class.
- **Alternatives considered:** Move `MediaPathResolver` to `Services/` to match the Blueprint's literal example (rejected — would resolve the inconsistency in the direction that contradicts the Blueprint's own definition); update the Blueprint immediately to formalize a domain-local `Support/` (rejected for now — one example is thin evidence for a permanent documentation change).
- **Trade-offs:** Leaves a known, small Blueprint/implementation documentation gap open a while longer, in exchange for waiting for real corroborating evidence before making it permanent.
- **Impact:** `app/Domain/Content/Support/MediaPathResolver.php` (unchanged); `00-master-migration-blueprint.md` §1.2 (unchanged); the two review documents remain the record of this reasoning until revisited.
- **Confidence:** High that this is the right thing to wait on; Medium on what Wave 4 will actually show.

## 2. `VbUserReader` interface remains deferred; the revisit trigger is now evidence-based, not a consumer count

- **Decision:** Do not build `VbUserReader`. Do not remove it from the Blueprint (§1.4) or ADR-0011. Replace the prior "a 3rd consumer appears" trigger with: real implementation evidence of an actual need — additional vBulletin integration whose access pattern genuinely differs from the two existing call sites, observed vBulletin schema instability, or repeated boundary leakage (raw vBulletin table/column names appearing in more places, the way `session`'s columns already do in `VbulletinSessionGuard`).
- **Reason:** A bare 3rd caller shaped like the existing two would say nothing about whether an interface's actual value (isolation from a schema this application doesn't control) is needed — it would just be a headcount. The anti-corruption-layer justification is about demonstrated pain, not population size, and conflating the two would repeat exactly the mistake this whole review process exists to catch elsewhere.
- **Alternatives considered:** Build `VbUserReader` now, preemptively (rejected — 2 stable call sites is weak evidence for swappability value, and this project's whole discipline has been to wait for real evidence); formally drop the Blueprint requirement (rejected — this is the one case where the justification model is genuinely different from "wait for repeated duplication," and that reasoning hasn't been invalidated, only unexercised).
- **Trade-offs:** If vBulletin integration deepens quickly in a later wave, the interface will be built retroactively against existing call sites rather than designed in up front — a real but modest cost given there are only 2 call sites today.
- **Impact:** No code changes. `pre-wave-4-decision-support.md` §2 updated with the revised trigger wording.
- **Confidence:** Medium — same as the original proposal; this decision changes the trigger's precision, not the underlying confidence level.

## 3. PHPStan (via Larastan) added at level 5, wired into the project before Wave 4

- **Decision:** Installed `larastan/larastan`, configured at level 5 (`phpstan.neon`, analyzing `app/` and `routes/`), and fixed every finding from the first real run rather than suppressing any of them.
- **Reason:** Flagged as a "must do before Wave 4" item in the pre-Wave-4 review, having been deferred twice already (Wave 2, Wave 3) with "before Wave 4's larger surface area" as the stated reason each time.
- **What the first run actually found (9 errors, all fixed, none suppressed):**
  - `EnsureAdminHasRole` called `hasAnyRole()` on the Guard contract's generic `Authenticatable` return type — fixed with an `instanceof AdminUser` check, which also makes a future guard misconfiguration fail safely (401) instead of a fatal error, not just satisfying the type-checker.
  - `RecordsView`/`ContentViewed` called `TracksViews`-only methods (`tracksLastVisit()`, `viewCountColumn()`) against the base `Model` type — fixed by introducing a `Viewable` contract that `TracksViews`-consuming models now implement explicitly (`Channel`, plus the test-only `FixtureContentItem`/`FixtureMirror`), and typing `ContentViewed::$viewable` as `Viewable&Model`.
  - `AdminGuard`/`VbulletinSessionGuard`'s `user()` methods returned the wrong static type from `GuardHelpers`' generic `?Authenticatable` property — fixed with `instanceof` narrowing on the memoization check in both.
  - **A genuine, not cosmetic, gap:** `VbUser` implemented `Authorizable` but not `Authenticatable`, even though `VbulletinSessionGuard` implements the `Guard` contract (which is typed against `Authenticatable` throughout). This was a latent gap — any framework Guard convenience assuming `Authenticatable` (e.g. `getAuthIdentifier()`) could have broken at runtime — not merely a static-analysis complaint. `VbUser` now implements `Authenticatable` via the same trait `AdminUser` already uses.
  - `UrlMapRouteRegistrar` called a private method via `static::` instead of `self::` — fixed (private methods can't be overridden, so `static::` was misleading, not functional).
  - `Channel`/`VbUser` lacked `@property` annotations for their confirmed columns — added, scoped strictly to columns each model's own code actually reads (`VbUser`'s docblock explicitly says not to add more without the same evidence bar the rest of this migration holds itself to).
- **Alternatives considered:** A lower starting level (rejected — level 5 already found real, worth-fixing issues without demanding disproportionate generics/template annotations); suppressing findings via `@phpstan-ignore` (explicitly rejected per PHPStan's own guidance and this project's standing evidence discipline — every finding was fixed at its root cause).
- **Trade-offs:** A small amount of additional ceremony (the `Viewable` contract, `@property` annotations) for real type-safety guarantees, including one finding (`VbUser`/`Authenticatable`) that was a genuine functional gap, not just a style issue.
- **Impact:** `phpstan.neon` (new); `app/Domain/Content/Models/Contracts/Viewable.php` (new); `Channel.php`, `VbUser.php`, `ContentViewed.php`, `AdminGuard.php`, `VbulletinSessionGuard.php`, `EnsureAdminHasRole.php`, `UrlMapRouteRegistrar.php`, `RecordsViewTest.php`'s two fixture classes (all modified). Full test suite re-verified green (109/109) after every change.
- **Confidence:** High — this is now a real, clean, zero-suppression baseline, not a nominally-configured tool.

## 4. `findOrFail()` cleanup applied

- **Decision:** Replaced all 4 `Model::find($id); abort_if($model === null, 404);` pairs (2 in `LiveStreamController`, 2 in `ChannelController`) with `Model::findOrFail($id)`.
- **Reason:** Laravel's default exception handler already converts `ModelNotFoundException` to a 404 response automatically — the longer form was strictly more code for an identical observable result. Flagged as a "must do before Wave 4" item so Wave 4's many new controllers don't copy the longer pattern by example.
- **Alternatives considered:** Leave as-is (rejected — no benefit to the extra code, and Wave 4 was about to multiply the pattern).
- **Trade-offs:** None identified — this is a pure simplification with an identical externally-observable result, proven by the existing `assertNotFound()` tests passing unchanged.
- **Impact:** `LiveStreamController.php`, `ChannelController.php`. All 15 relevant tests re-verified green.
- **Confidence:** High.

## 5. Shared Blade partial extracted for `channels/show.blade.php` / `author.blade.php`

- **Decision:** Extracted `resources/views/channels/_listing.blade.php` (the groups/series/items rendering both views shared almost verbatim), parameterized by a `$showAuthorLinks` flag to preserve the one real difference between the two pages.
- **Reason:** Approved conditionally ("if it remains a small, local refactoring with no behavioral changes") — confirmed both by direct comparison before extraction and by the existing `ChannelControllerTest` suite (6 tests, including the one proving `author.php`'s empty sidebar/IF-012 behavior) passing unchanged afterward.
- **Alternatives considered:** Wait until Wave 4 adds a third similar view (rejected per the review's own reasoning — fixing after a third copy exists is strictly more work than after two, and the approval was already conditional on staying small).
- **Trade-offs:** One more include-boundary to be aware of; judged low-risk given the partial's only parameter is a boolean and both call sites were verified unchanged.
- **Impact:** `resources/views/channels/_listing.blade.php` (new), `show.blade.php`/`author.blade.php` (simplified). Tests unchanged and green.
- **Confidence:** High.

---

## 6. `KhotabGroup` added as a plain, referenced Eloquent model — a Blueprint gap, not a contradiction

- **Decision:** Add a `KhotabGroup` model over `nuke_islamic_groups` in Wave 4, treated exactly like `Category`/`Channel` under Blueprint §6's existing rule ("plain Eloquent models — no aggregate-boundary analysis needed"), referenced by ID from `Series` and `KhotabItem`, never owned.
- **Reason:** `00-master-migration-blueprint.md` §6's aggregate table and ER diagram name `Author`, `Series`, `KhotabItem`, `Mirror`, `Category`, `Channel`, `Comment` but never mention groups, even though direct reading of `khotab/group.php`, `khotab/functions.php`'s `ListGroup()`/`adminGroupControls()`, and the already-schema-documented `nuke_islamic_groups` table (own title/description/meta fields, own detail page, own breadcrumb entry, own admin CRUD, referenced independently by both `nuke_islamic_series.group_id` and `nuke_islamic_khotab.group_id`) show it is a real, distinct entity in the legacy data model — a level between Author and Series/KhotabItem that some series/items skip entirely (`group_id = 0`) and others don't. This is a Blueprint omission (the deep khotab dive happened after the Blueprint was frozen), not a place where the Blueprint states something this evidence contradicts — the same "documentation gap vs. architectural error" distinction already applied to `MediaPathResolver` (decision #1). Unlike that case, there is no live disagreement to referee: Blueprint §6 already gives the exact rule to apply ("plain Eloquent model... referenced, not owned") to any confirmed entity it didn't enumerate — applying that existing rule to newly-complete evidence is not itself a new architectural decision, so this did not go through a full Architecture Evolution Proposal.
- **Alternatives considered:** Fold groups into `Series` as a nullable "group name" field (rejected — groups have their own title/description/meta/admin lifecycle independent of any series, and some items reference a group directly with no series at all, `khotab/functions.php`'s `ListKhotab()` default branch); treat as a value object embedded in `Author` (rejected — groups are independently queried, listed, and linked to by URL, which is exactly Blueprint §6's own test for "needs to be its own model").
- **Trade-offs:** The Blueprint's frozen ER diagram is now incomplete until formally updated (tracked as an open item below, same pattern as `MediaPathResolver`'s open documentation gap) — accepted rather than blocking Wave 4 on a Blueprint edit for a mechanical, already-covered-by-existing-rule addition.
- **Impact:** `app/Domain/Content/Models/KhotabGroup.php` (new, Wave 4). `00-master-migration-blueprint.md` §6 not yet updated — revisit alongside `MediaPathResolver`'s same open item after Wave 4.
- **Confidence:** High — this is a direct, low-judgment application of an already-approved Blueprint rule to evidence the Blueprint's authors didn't have when §6 was written, not a new design decision.

---

## 7. Post-Wave-4 cross-wave review: `CommentPosted` event + `GeoIpLookup` service built; `W2acdController` regression fixed. AEP-1 explicitly deferred to post-Wave-6.

- **Decision:** Implement exactly the 3 engineering gaps the user approved from `docs/reviews/post-wave-4-cross-wave-architecture-review.md` — (1) dispatch `CommentPosted` (Blueprint §5) from both `storeComment()` implementations, without a listener (none has real behavior to perform yet — see the event's own docblock); (2) build `GeoIpLookup` (Blueprint §4's named "GeoIP lookup service," named consumers `khotab`/`anasheed` comment posting) and wire it into both `storeComment()` implementations instead of the hardcoded empty `code`; (3) fix `W2acdController::show()` to call `W2acdItem::recordView()` (now `implements Viewable`, `use TracksViews`) instead of hand-rolling the same `hits`/`lastvisit` update `RecordsView` already centralizes for `Channel`/`KhotabItem`/`AnasheedItem`/`Album`. Explicitly NOT executed: AEP-1 (the `ContentGroup`-shaped hierarchy extraction) — user directive is to hold it until Wave 5 and Wave 6 are both complete, so any extraction is driven by the full application's shape, not 65% of it. No renames (`W2acdItem`→`CdItem`, `TelawahItem`→`Recitation`), no `breadcrumbTrail()` addition to the group models, no Blueprint edits — all explicitly scoped out as "architectural refactoring," not "implementation fixes."
- **Reason:** All 3 fixed items were named Blueprint components (§4/§5) with confirmed, exercised consumers already in production code — not new scope, implementation catching up to decisions the Blueprint already made. The domain-boundary gap the same review found (comment `uid` never resolved via `VbulletinSessionGuard`) was named in §1 of the review but not in the user's list of 3 approved items — left open, not silently fixed under an expanded reading of "agreed fixes."
- **Alternatives considered:** Building real moderation/notification listeners for `CommentPosted` now (rejected — neither legacy source function performs any such action; inventing listener behavior with no evidence would be exactly the premature scaffolding this project's discipline avoids elsewhere, e.g. `EnsureAdminHasRole` sitting correctly unused since Wave 0); executing AEP-1 now since the evidence is already documented (rejected — explicit user directive, evidence quality isn't the blocker, timing is).
- **Trade-offs:** `CommentPosted` is dispatched into a void until a real listener exists — acceptable, matches the `ContentViewed`/`RecordsView` precedent of building the dispatch point before (not instead of) a consumer existing. The `ContentGroup` duplication (`W2acdGroup`/`AnasheedGroup`/`TelawahGroup`) and the missing `anasheed`/`telawah` breadcrumb trails remain live technical debt, unchanged from the review's own ranking — deferred, not forgotten.
- **Impact:** New: `app/Domain/Content/Events/CommentPosted.php`, `app/Domain/Content/Services/GeoIpLookup.php`, `tests/Feature/Content/GeoIpLookupTest.php`. Modified: `KhotabItemController::storeComment()`, `AnasheedItemController::storeComment()`, `W2acdController::show()`, `W2acdItem` (now `Viewable`), `MainSchema::ips()` (new fixture), `KhotabItemControllerTest.php`/`AnasheedItemControllerTest.php` (new test cases). `docs/implementation-findings.md`'s IF-026 entry gets a one-line addendum per the review's own §4 finding (documentation issue, not a code change). Full suite re-verified green (183/183) after every change, stable across repeated runs.
- **Confidence:** High for all 3 fixes (each closes a confirmed, named Blueprint gap with a direct, low-risk implementation). Medium on timing being right to defer AEP-1 specifically to "after Wave 6" rather than "after Wave 5" — a user judgment call on how much more evidence is enough, not something this log evaluates.

---

## 8. Legacy pretty-URL kept at its exact path when no `.htaccess` rule ever backed it, IF a real standing internal link already targets it

- **Decision:** When a legacy page has no `.htaccess` rewrite rule (so its pretty URL never actually resolved in production) but real, permanent site chrome (nav, header, footer — not a one-off/orphaned link) already targets that exact pretty path, register the Laravel route at that exact path directly (matching this codebase's "real, live `.htaccess` rule → kept at exact path" pattern), rather than treating it as a "no rule at all" case (which gets a new clean path + a `legacy-url-map.php` redirect from the raw `.php` file, this codebase's other established pattern for unrouted files). Applied for `pages/social.php` → `/social.htm` (task 2.5): `header.php`'s account dropdown links to `social.htm` twice, in permanent nav, even though `.htaccess` never had a matching rule.
- **Reason:** Neither of the two existing patterns actually fit this shape. "Real, live rule → keep exact path" assumes the path currently resolves (it doesn't — it 404s). "No rule at all → new path + redirect" assumes nothing in the app currently expects that exact pretty path (something does — real nav chrome, not a stray reference). Picking either existing pattern blindly would have meant either mischaracterizing a currently-broken URL as "live," or leaving a real, permanent, user-facing nav link 404ing after migration when a straightforward fix was available. A third, explicit rule closes the gap cleanly.
- **Alternatives considered:** Treat it as "no rule at all" and give it a new path + redirect from `pages/social.php` (rejected — this would fix the raw-file-path case but leave `header.php`'s actual, real link still 404ing, since nothing redirects *to* `social.htm`, only *from* `pages/social.php`); leave the header link broken and just fix the raw path (rejected — the whole point of task 2.5 was closing a confirmed, user-facing production bug, not just adding new dead-end coverage).
- **Trade-offs:** None identified for this one case; the general risk is over-applying "kept at exact path" to pages where the internal reference turns out to be stale/removable rather than a genuine standing link — each future case still needs its own direct verification (grep the actual reference), not a blanket rule.
- **Impact:** `routes/pages.php` (route registered at `/social.htm`, not a new clean path), `config/legacy-url-map.php` (defensive redirect from the raw `pages/social.php` path added anyway, in case anything links to the raw file directly — belt-and-suspenders, not a contradiction of this decision). See IF-030 for the full evidence this decision responds to.
- **Confidence:** High that the rule itself is right; Medium on how often it will actually recur (only 1 confirmed instance so far).

---

## 9. Wave 5 permission taxonomy: one Spatie `Permission` per legacy `menu.php` authorization key, namespaced `{module}.{key}`

- **Decision:** Every `admincp/*/menu.php`'s `$authorization[$module]` array becomes a set of real Spatie `Permission` rows on the `admin` guard, named `{module}.{key}` (e.g. `survey.modsurvey`, `chat.listrooms`, `locations.add_location`, `backup.backupkhotab`). `AdminUser` (already `HasRoles`, which includes Spatie's permission-assignment API) gets permissions assigned directly, not only roles. The existing coarse `super-admin`/`admin` Roles (Wave 0, `RoleSeeder`) are unchanged and unaffected — a `super-admin` implicitly passes every gate via Spatie's own `Gate::before` behavior for a designated super-admin role (wired in task 5.3), while a plain `admin`'s access is exactly the set of permissions assigned. This reproduces the legacy two-layer model (`radminsuper` coarse flag + `nuke_authors.permissions` fine-grained blob) with two real Spatie primitives instead of one hardcoded flag plus one hand-rolled serialized array.
- **Reason:** `sidebar.php`'s nav-gating (`admincp.md` §5, confirmed functional) is real, fine-grained legacy behavior — a plain `admin` can be authorized for `survey.modsurvey` without also getting `backup.allsite`. Wave 0's `RoleSeeder` deliberately only seeded the coarse `super-admin`/`admin` roles (real per-admin permission *data* isn't readable from source, per that seeder's own docblock) — but the coarse role alone cannot reproduce this behavior; task 5.3 ("one real permission-editor implementation on Spatie") needs a real permission *dimension* to edit, not just a role toggle. The `{module}.{key}` namespace prevents the collisions a bare-key scheme would hit immediately (`liststuff` appears in both `authors/` and, differently-named but same-shape, elsewhere; `listrooms`/`listlocation` etc. are all module-local concepts, not global ones).
- **Alternatives considered:** Reproduce `nuke_authors.permissions` as a single JSON/array column instead of real Spatie permissions (rejected — this is exactly ADR-0010's "Can be replaced" classification for this mechanism; keeping a hand-rolled blob would just port the current unmaintainable shape into Laravel); bare (non-namespaced) permission names matching each `menu.php`'s literal key (rejected — confirmed collision risk across modules, and loses the module grouping the permission-editor UI needs to render sensibly, matching the legacy UI's own per-module checkbox grouping).
- **Trade-offs:** ~25 permission rows seeded across the 9 real feature directories (excluding `khotab`/`telawah`, whose real CRUD doesn't exist in this codebase — Business Confirmation #6) — more upfront enumeration than a single blob column, in exchange for permissions that are individually assignable, testable, and queryable through Spatie's own API rather than requiring `unserialize()` everywhere a check is needed.
- **Impact:** `database/seeders/AdminPermissionSeeder.php` (new), every Wave 5 controller's route/middleware gating, task 5.3's permission-editor UI (iterates real `Permission::where('guard_name', 'admin')` rows grouped by module, replacing the legacy 5×-duplicated `menu.php`-introspection template).
- **Confidence:** High — this is a direct, mechanical translation of already-real, already-confirmed-functional legacy authorization data (the `menu.php` files, not inferred), not a new design invented from scratch.
- **Correction (2026-08-07, see entry #10):** the "Confidence: High — direct, mechanical translation of already-real, already-confirmed-functional legacy authorization data" claim above is wrong on one specific point, caught during Wave 5 verification review: `sidebar.php`'s check is real, but it only ever gated nav-link *visibility*, never page-level access — no legacy admincp page enforces the permissions array at all. The permission *data model* (namespaced `{module}.{key}` rows) is still a faithful translation of the `menu.php` authorization arrays; only the *enforcement* built on top of it in Wave 5 is new. Entry #10 ratifies that enforcement as an intentional hardening rather than retracting it. Separately: the Trade-offs line's "~25 permission rows... 9 real feature directories" count is also stale — the `khotab.uploaders` addition (task 5.9) brought the real seeded total to **27 permissions across 10 modules**, documented correctly in `AdminPermissionSeeder`'s own docblock but never back-ported to this line.

---

## 10. Wave 5 per-permission route enforcement ratified as a deliberate hardening — corrects entry #9's "mechanical translation" framing

- **Decision:** The `admin.permission:{module}.{key}` middleware enforcement added on every Wave 5 route is **kept as-is**, and is reclassified: it is a deliberate security hardening introduced during migration, not a reproduction of an existing legacy access-control mechanism. Legacy `admincp/` never enforced `nuke_authors.permissions` at the page or action level anywhere.
- **Reason:** An exhaustive, two-pass verification found no legacy admincp page checking the current admin's own permissions before allowing access. First pass (`wave-5-verification-review.md` Finding 1): a targeted grep for `authorization`/`$permissions[` across every Wave-5-relevant feature directory matched only each module's own `menu.php` (nav-link definitions, not enforcement) and the permission-editor templates (which read/write *another* admin's stored grid, not the acting admin's own access). Second pass, run specifically to stress-test that conclusion from a business-behavior angle before implementing this decision: a broader, pattern-agnostic grep for the literal substring `permissions` across the entirety of `admincp/` (not just the earlier regex) surfaced exactly 5 files — `sidebar.php` (nav visibility), `index.php` (loads the row into session; its own inline comment reads *"Try to load related VB user for permission-driven pages (sidebar, etc.)"* — direct evidence the original developer understood this mechanism as sidebar-only), `authors/edit_author.php` and `broadcasting/edit_author.php` (the permission-editor templates), and `survey/add_survey.php` (a `WHERE permissions LIKE '%s:9:"modsurvey"...%'` query — traced directly and confirmed to build a checkbox list of admins eligible to be assigned as a *survey's* editors, i.e. business data entered into `nuke_survey.editors`; a follow-up grep of every other file in `survey/` for any read of `editors` found none — it is displayed, never enforced). No code path anywhere checks the acting admin's own permissions before granting access to a page or action. Blueprint §16's security-remediation direction and ADR-0010's "can be replaced" latitude for confirmed-weak legacy mechanisms both support closing this gap now that it's understood precisely.
- **Alternatives considered:** Revert to nav-visibility-only parity with legacy (rejected — would mean building real Spatie permission plumbing and a real permission-editor UI, then deliberately not enforcing any of it at the point that matters, which has no defensible rationale once named explicitly); leave the stricter behavior in place but undocumented (rejected — exactly the silent-assumption problem the verification review exists to catch).
- **Trade-offs:** Every Wave 5 route is now strictly more restrictive than its legacy counterpart ever was — an admin who previously could reach any admincp page by URL, permissions blob notwithstanding, now genuinely cannot without the matching grant. `AdminPermissionSeeder`'s 27 permissions (10 modules) need real, correct assignment to every admin who currently expects unrestricted access, since the previous de facto behavior (any login = any page) no longer holds anywhere in the Laravel admin. This is an operational rollout concern (who gets which grant before cutover), not an architectural one — out of scope for this decision, flagged for whoever plans the cutover.
- **Impact:** No controller/model/middleware code changes (the mechanism built in Wave 5 was already correct; only its classification was wrong). `routes/admin.php`'s `locations`/`chat` route groups split to fix the permission-combination gaps this decision's stricter model exposed (`wave-5-verification-review.md` Finding 2) — an admin holding only one of two related permissions on a module could otherwise be locked out of the view needed to exercise it; `00-master-migration-blueprint.md` §9/§16 gain a clarifying line and a new remediation-table row.
- **Confidence:** High — a direct decision made after reviewing the evidence from two independent verification passes (code-level and business-behavior-level), not an inference.
- **Correction (2026-08-07, same day, second pass — IF-035):** the Reason line's "No code path anywhere checks the acting admin's own permissions before granting access to a page or action" overstates the evidence — it's true of `admincp/` (confirmed by both grep passes cited above) but not true sitewide. `chat_room/`'s unmigrated live-room half (`chat_room.php`, `functions.php`'s `list_chat_rooms()`) has one confirmed, real, page-level use of the same `nuke_authors.permissions` data — a visitor whose session is also linked to an admin account holding any `chat`-module permission can view disabled rooms. Already documented pre-Wave-5 in `chat_room.md` §3, but never cross-referenced against this entry until caught during this same round's independent re-verification. Does not change this entry's Decision, Trade-offs, or Impact — that code path is out of scope for both Wave 4 and Wave 5, deferred to Roadmap task 6.5 (Business Confirmation #4), and `admincp/`'s own behavior remains exactly as described above.

---

## 11. One-time exception to ADR-0001: legacy PHP edited directly to close a confirmed, live SQL injection (Wave 6, tasks 6.1/6.2)

- **Decision:** `ADR-0001`'s governing constraint — `legacy-project/` stays untouched for the life of the migration — was set aside, once, for one narrow purpose: closing the confirmed SQL injection in `fatawa/` (5 files) and the SQL-injection-adjacent pattern in `advanced-search/index.php` (Blueprint §16 items 5-6), both explicitly scoped by Roadmap tasks 6.1/6.2 as legacy-side fixes "independent of this task's timeline" — i.e. required regardless of whether `fatawa`/`advanced-search` are ever migrated to Laravel, since Business Confirmation #1 (their reachability) remains open and the vulnerability is live either way. Fixed in both `/mnt/Projects/Php/pure/7amlat` (the canonical, production-adjacent copy, where the fix has real effect) and `way2allah-migration/legacy-project` (the migration project's reference copy, kept in sync per `legacy-reference-copy-audit.md`'s own recommendation against future silent drift).
- **What changed, precisely (minimal, no refactor):** every confirmed unsanitized `$_GET`/`$_POST` value reaching a raw SQL `WHERE id=...`/`WHERE id LIKE ...` clause was wrapped in `intval()` at the exact point of use — `fatawa/single.php` (`$q`), `answer.php`/`answer2.php` (`$q`), `channel_fatawa.php` (`$id`), `sendemail.php` (`$id`), and `advanced-search/index.php`'s `Search` class (`$this->channel`/`$this->author`, 7 call sites across the `mawad`/`series` query branches). This exactly matches the `intval()`/`get_int_parameter()` sanitization convention already used everywhere else in this codebase (`fatawa.md` §5's own framing — this module was "the first confirmed exception" to that sitewide pattern; the fix simply brings it in line). `sendemail.php` additionally strips `\r`/`\n` from `$fmail` before it reaches the `mail()` headers (defense-in-depth for the email-header-injection finding bundled into the same §16 item 5 — though direct re-reading of `validEmail()` shows its own character-class regex already implicitly rejects CRLF in any email address that would pass validation, so this specific header path was likely not independently exploitable; the stripping is precautionary, not closing a proven-live second hole). No SQL query shape, no control flow, no unrelated code was touched — verified via `git diff` (6 files, 14 insertions/13 deletions total) and `php -l` (all 6 files, both copies, zero syntax errors).
- **Reason:** the vulnerability is real, confirmed by direct code reading (not inferred), and live regardless of this migration's own timeline or scope decisions — `fatawa`/`advanced-search`'s Laravel migration itself stays fully gated on Business Confirmation #1, per Wave 6's own analysis (`wave-6-analysis.md`) — but the underlying legacy files keep executing in production either way, and leaving a known, exploitable SQL injection unpatched for the remainder of an open-ended confirmation wait has no defensible justification once named explicitly. Explicitly authorized by the user as a one-time exception, not a precedent — no further legacy-side edits are authorized without the same explicit approval, and Wave 6 continues Laravel/documentation-only from here.
- **Alternatives considered:** leave it unpatched and only fix it inside whatever Laravel implementation eventually replaces `fatawa`/`advanced-search` (rejected — that implementation is itself gated on an unresolved confirmation with no timeline, during which the live legacy files remain exploitable); rewrite the vulnerable queries onto parameterized/prepared statements via ezSQL (rejected as unnecessarily broad — every other module in this codebase already defends this exact class of input with a plain `intval()`/`get_int_parameter()` cast, so matching that existing, proven convention is both sufficient and consistent, not a partial fix).
- **Impact:** `7amlat/fatawa/{single,answer,answer2,channel_fatawa,sendemail}.php`, `7amlat/advanced-search/index.php`, and the identical 6 files under `way2allah-migration/legacy-project/`. Blueprint §16 items 5-6 move from Open to Fixed (see the Blueprint's own revision-history entry for this change). No Laravel code, no Roadmap task status, no other Decision Log entry affected — `fatawa`/`advanced-search`'s Wave 6 migration tasks (6.1/6.2) remain exactly as gated on Business Confirmation #1 as before this fix.
- **Confidence:** High — direct, minimal, lint-verified, git-diff-verified changes closing a Fact-tagged (not inferred) vulnerability, explicitly authorized before being made.
- **Correction (2026-08-07, same day, second pass — user-requested verification):** `advanced-search/index.php:922`'s original fix left one occurrence unpatched — `tb1.place_of_fatwa LIKE '%".$this->channel."%'`, on the same line as the now-fixed `WHERE id=".intval($this->channel).")"`. Traced directly: `$this->channel` is set exactly once, in `Search::__construct()` → `prepare()` (`index.php:447`), as `$this->basic_securing($_POST["kh_channel"])` — `basic_securing()` (`index.php:407-416`) applies only `strip_tags`/`stripslashes`/`addslashes`, none of which validate or coerce numeric type. **`$this->channel` is not guaranteed numeric by the time it reaches this method** — confirmed, not assumed. Fixed by sanitizing once per the `if(!empty($this->channel))` block (`index.php:920`) — `$channel = intval($this->channel);` — and using `$channel` consistently for both the `WHERE id=` and `LIKE '%...%'` interpolations in both branches of the `is_fatwa` conditional. A repo-wide grep confirmed this was the only remaining raw (non-`intval()`-wrapped) SQL-adjacent use of `$this->channel`/`$this->author` anywhere in the file — every other occurrence (lines 924, 932, 1391, 1398, 1441, 1451) was already correctly wrapped in the original fix. Re-verified via `php -l` (both copies, zero errors) and `git diff` (one additional line, `advanced-search/index.php` only — no other file touched).

---

## 12. Retroactive entry: Roadmap task 4.4 (P-015 `w2acd` data migration) deliberately deferred; task 4.5 shipped without it

- **Decision:** Task 4.4 (a one-way migration of `w2acd`'s comma-delimited `link`/`cd`/`thumbnail` columns into the already-existing but unused `nuke_w2acd_mirror` table) was not attempted during Wave 4. Task 4.5 (`w2acd` models/controllers) was built anyway, against the legacy comma-delimited shape as-is — `W2acdItem::mirrorLinks()`/`thumbnailUrls()` reproduce `explode(',', ...)` parsing exactly, matching `w2acd/functions.php`'s own behavior.
- **Reason:** Task 4.4 was already flagged in the Roadmap itself, before Wave 4 began, as needing a mandatory dry-run prototype and an explicit rollback plan before starting — a materially higher-ceremony task than a normal model/controller pair. Per the Blueprint's own governing priority order (preserve behavior first, architecture second, performance third, code quality fourth), shipping working-but-unclean models now rather than blocking the whole module on an undated data migration is consistent with that order, not a violation of it — P-015 is a code-quality concern, not a behavior-preservation one.
- **This entry is retroactive**, added after an independent Wave 6 roadmap audit found the decision correctly reasoned about but never indexed here. Full original reasoning: `docs/reports/wave-4-implementation-report.md` (recorded at the time — "explicitly deferred with reasoning, not silently dropped"), `docs/reviews/post-wave-4-cross-wave-architecture-review.md` §4 (a dedicated later re-confirmation against the Blueprint's priority order), `docs/reviews/legacy-vs-laravel-coverage.md` (a third independent pass reaching the same conclusion). Not re-derived here — this entry exists so a future search of this log finds the decision without having to rediscover it across three review documents, as this audit had to.
- **Impact:** `nuke_w2acd_mirror` remains unpopulated and unmodeled; `w2acd` is, and will remain, the least data-normalized module in the Content domain until task 4.4 is actually picked up. `PROJECT-HANDOFF.md`'s Wave 4 milestone bullet corrected to state this accurately (previously read "post P-015 data migration," which was false).
- **Confidence:** High that the underlying decision was sound (three independent review passes agree). Confidence in the documentation gap itself is also high — a direct grep of this log for "w2acd"/"P-015" returned nothing prior to this entry, despite the decision's real significance.

---

## 13. Business Confirmation Reconciliation (2026-08-07): 10 of 13 Business Confirmations resolved by the Business Owner — Wave 6 scope updated, no implementation performed

- **Decision:** Documentation-only update recording the Business Owner's answers to Confirmations #1, #2, #3, #4, #5, #6, #7, #8, #9, #13 (`docs/business-confirmation-report.md`, full record in `docs/reviews/wave-6-business-confirmation-reconciliation.md`), plus two follow-up answers obtained directly from the Business Owner before any file was touched. No code, route, permission, or test file was changed by this entry — only `00-implementation-roadmap.md`, `ADR-0009.md`, `chat_room.md`, and `implementation-findings.md` (IF-033, IF-035 addenda).
  - **#1 (fatawa/advanced-search reachability) — Business-confirmed fact:** YES, live and required. Roadmap tasks 6.1/6.2 unblocked.
  - **#2 (Ramadan + Share banners) — Business-confirmed fact:** both required. **Business-confirmed fact (follow-up answer):** missing/replacement banner assets — use placeholders for now. No asset provider was named; no placeholder image was invented or sourced by this pass, only the decision to use one is recorded. Roadmap task 6.3 unblocked, with the placeholder decision noted as non-blocking.
  - **#3 (unspecified scope item) — Business-confirmed fact:** NO.
  - **#4 (live chat rooms) — Business-confirmed fact:** the original live-room feature is retired, not migrated; replaced by a Zoom-based approach. **Business-confirmed fact (follow-up answer):** the existing Wave 5 `ChatRoomAdminController` (task 5.5) tools are to be **retired**, not kept. This was asked directly rather than assumed, per explicit instruction not to decide the fate of existing admin tooling unilaterally. **Retirement is approved but not yet executed** — removing the controller, its routes, permissions, views, and tests is implementation work, out of scope for this documentation-only pass, and is deferred to whoever picks up task 6.5. Consequence: IF-033's weekly-lesson-schedule transfer target is closed (no remaining scope), IF-035's permission-harmonization question is closed as moot (the code path it concerned is retired), both addenda applied to `implementation-findings.md` this round.
  - **#5 (legacy cross-login bridge) — Business-confirmed fact:** preserve current legacy behavior during migration, hardening deferred to a future phase. **Explicitly not** authorization for an authentication redesign now, and **explicitly not** authorization to reproduce any already-confirmed security vulnerability — existing security fixes (decision-log #11, the SQL-injection fix) remain in place and are unaffected by this confirmation; "as-is" applies to the cross-login bridge's own behavior, not to vulnerabilities already closed elsewhere.
  - **#6 ("other dashboard" for khotab/telawah management) — Business-confirmed fact:** content management is handled by another dashboard/system, not built in this migration. **Business-confirmed fact (follow-up answer):** that system is explicitly **not** `old.way2allah.com`'s admin panel — it is a different system. **Still-unconfirmed detail, not investigated further per explicit instruction:** the actual system's name/identity. No further investigation into `old.way2allah.com` or any other system was performed to answer this — the question was put directly to the Business Owner instead, per explicit instruction not to "investigate outside the authorized scope or attempt to access another admin system just to answer this." Roadmap task 6.4 closed; `AdminPermissionSeeder`'s ~30 excluded khotab/telawah CRUD permission keys remain excluded regardless of which system this turns out to be.
  - **#7 (backup/booking tool, root `backup.php`) — Business-confirmed fact:** YES, in active use, external contract must be preserved. Roadmap task 6.8 added; full end-to-end read of `backup.php` (endpoint, auth/access-key behavior, all 5 affected tables, request/response format, callers, failure behavior, and whether it is backup, booking, or both) is Next Action, not yet performed — no redesign or renaming has been assumed from the filename alone.
  - **#8 (old.way2allah.com shares the same database) — Business-confirmed fact:** YES. **Technical evidence, unchanged by this confirmation:** the earlier controlled hit-counter test, where the two deployments' visit counters for the same content item did not move in lockstep. **Technical inference, explicitly labeled as unverified hypotheses, not established facts:** three possible explanations for that counter divergence (a caching layer on one deployment only; a separate counter column/increment path on the older front end; the older deployment's own view-counting disabled or broken) — none checked, none adopted as the actual explanation. **Explicitly does not follow from #8:** that `old.way2allah.com`'s admin panel is Confirmation #6's "other dashboard" — the Business Owner separately and explicitly confirmed that dashboard is a different, not-yet-named system (see #6 above). ADR-0009 Decision §4 ("database relationship stays open") is superseded, not deleted, via a dated addendum — full reasoning there, not duplicated here.
  - **#9 (admin passwords) — Business-confirmed fact:** neither an audit nor a reset is authorized. **Explicitly not** performed by this pass or any pass to date: inspection of password data, an audit, or a forced reset. Existing migration architecture and security mechanisms already implemented are preserved unchanged.
  - **#13 (217 URL patterns) — Business-confirmed fact:** preserve all 217, not reduced on SEO-risk grounds. **Explicitly not implied by this confirmation:** that all 217 require 217 independent Laravel controllers or routes — routing pattern reuse is still permitted where it preserves identical externally-observable behavior; this is a behavior-preservation requirement, not a 1:1 controller-count requirement.
- **Reason:** these are business decisions, not technical findings — they could only be resolved by asking the Business Owner directly, which is exactly what the prior reconciliation pass (`wave-6-business-confirmation-reconciliation.md`) and the follow-up 3-question round (via `AskUserQuestion`) did. This entry exists so a future search of this log finds all 10 resolutions and their exact evidence tier (business-confirmed fact vs. technical evidence vs. labeled-hypothesis inference vs. still-unconfirmed detail) in one place, without re-deriving it from the reconciliation document, the ADR-0009 addendum, and three separate Roadmap task edits.
- **Alternatives considered:** infer the "other dashboard" is `old.way2allah.com`, given #8's same-database confirmation makes it technically capable of being one (rejected, per explicit instruction — technical capability is not identity, and the Business Owner already separately ruled this specific system out); unilaterally decide to delete `ChatRoomAdminController` now that the live-room feature is retired (rejected — asked directly instead, per explicit instruction not to assume every admin tool tied to a retired feature must be deleted); treat #5's "as-is" as license to also harden the cross-login bridge's security now, since hardening is generally desirable (rejected — the confirmation's own wording is about behavior preservation, and unrequested authentication redesign during this phase was explicitly ruled out).
- **Trade-offs:** three genuine unknowns remain open and are not resolved by this entry — the identity of Confirmation #6's "other dashboard," the actual explanation for the hit-counter divergence, and `backup.php`'s full technical shape (auth flow, exact table semantics, failure behavior). None was force-resolved with a guess; each stays labeled open rather than being papered over, at the cost of leaving 3 follow-up items for a later phase.
- **Impact:** `00-implementation-roadmap.md` (tasks 6.1-6.6 status updates, task 6.8 added, bottom confirmation-status table updated); `00-architecture-decisions/ADR-0009.md` (Decision §4 addendum); `docs/migration/modules/chat_room.md` (`legacy-project/`, IF-035 cross-reference addendum); `implementation-findings.md` (IF-033, IF-035 addenda, `laravel-project/`). No `.php` file, route, permission, or test was touched — `ChatRoomAdminController`'s actual removal, `AdminPermissionSeeder`'s stale docblock comment referencing "pending Confirmation #6," and any route/permission changes remain implementation-phase work for a later entry.
- **Confidence:** High on every line tagged "Business-confirmed fact" (direct answers from the Business Owner, not inferred). Explicitly Low/unconfirmed on the three items named in Trade-offs — recorded as open, not guessed at.
- **Correction (2026-08-10): entry #4's "replaced by a Zoom-based approach" was this audit's own misreading, not a Business-confirmed fact.** The Business Owner has clarified directly that Confirmation #4 means **NO to the original FlashChat live-room feature, and ALSO NO to any Zoom-based replacement** — no rebuild, no Zoom integration, no landing page, no replacement live-room implementation of any kind. The entire live-room/chat-room feature family is retired and out of scope. This also reopens the `ChatRoomAdminController` line above: "retirement is approved" is superseded — the Business Owner has explicitly reinstated this as an open question (should the already-shipped admin tools remain temporarily for historical/data-management reasons, or be deprecated/removed?), not decided either way. See entry #14 for the full corrected record and every downstream document this touches.

---

## 14. Confirmation #4 corrected: live-room retirement means no replacement at all — "Zoom is the replacement path" was this audit's own error, not a business decision

- **Decision:** Correct every document that recorded "Zoom is the confirmed replacement path" (or equivalent — a Zoom-workflow landing page, a Zoom-based approach) for task 6.5, as a direct result of the Business Owner's explicit clarification: *"FlashChat / Live Voice Chat Rooms = NO. Zoom = ALSO NO. I do NOT want to migrate the old live voice-chat rooms. I do NOT want to build a Zoom replacement."* Task 6.5 is reclassified from "resolved, rescoped to a smaller Zoom-workflow task" to **CLOSED / RETIRED**, with no replacement of any kind. Corrected in place (with visible strikethrough/correction markers, not silent deletion) in: `00-implementation-roadmap.md` (task 6.5's own entry, its top status-update paragraph, and the bottom confirmation-status table row, all `legacy-project/`), `modules/chat_room.md` (the IF-035 cross-reference addendum, `legacy-project/`), `PROJECT-HANDOFF.md` (§3's deferred-items table, §4's confirmation list, §6's roadmap-progress table, §8's evidence-verification/deferred-item notes), and this log's own entry #13 (correction note above). `implementation-findings.md`'s IF-033/IF-035 addenda are **not** further corrected beyond entry #13's note — both already close cleanly on "the live-room feature is retired, no remaining scope," which was already correct; only their secondary, parenthetical mentions of "the new Zoom-landing-page scope" as a place future work *might* attach were already appropriately hedged ("if a future phase needs...") and are addressed by `PROJECT-HANDOFF.md`'s correction rather than needing their own edit.
- **`chat_room/rules.php` — evidence recorded, not migrated.** Per the Legacy Evidence Verification pass (`docs/reviews/wave-6-business-confirmation-reconciliation.md`'s follow-on verification), the file was read in full: a purely static page (no `$chatdb`, no session, no `chat_room/functions.php` include) whose Arabic content is a genuine mixture of general Islamic/community conduct guidance and FlashChat-specific technical instructions (moderator color legend, microphone-use rule, mic-icon troubleshooting, Mixlr radio fallback, "return to the voice room" link). Recorded in `chat_room.md` as evidence only. **Neither part is implemented or migrated under task 6.5** — the general-conduct portion is noted as a possible future/reference item only, contingent on a separate business requirement that does not currently exist.
- **`ChatRoomAdminController` (Wave 5, task 5.5) — reopened as an explicit unresolved decision.** Not decided either way in this entry. The Business Owner's exact framing, preserved verbatim for the next person who picks this up: *"Should the already-shipped `ChatRoomAdminController` / FlashChat administration tools remain temporarily for historical/data-management reasons, or should they also be deprecated/removed?"* The controller has not been touched, modified, or removed at any point in this migration to date, and remains untouched by this entry.
- **Reason:** the 2026-08-07 reconciliation pass (entry #13) read the Business Owner's original Confirmation #4 answer — "the original live-room feature is retired... replaced by a Zoom-based approach" — and treated "Zoom" as a confirmed replacement *target* to build toward, then independently found supporting-looking legacy evidence (`chat_room/room.php`'s existing Zoom links/banner) and built a "Zoom-landing-page" scope on top of it. The Business Owner's follow-up message makes clear this was an over-read: the original answer meant only that Zoom had *already* effectively displaced FlashChat in actual practice (a factual/historical observation), not that building or formalizing a Zoom-based page was requested or wanted as migration scope. This is the same category of error this log's own evidence discipline exists to catch — treating a plausible technical inference (real Zoom content exists in the legacy code, therefore a Zoom-based Laravel page seems like the natural next step) as though it were the business's actual instruction.
- **Alternatives considered:** silently edit entry #13 and the Roadmap in place to remove the Zoom framing (rejected — violates this project's own "visible correction, never silent rewrite" convention, applied consistently to every other correction in this log and the Blueprint); leave the Zoom framing standing until task 6.5 is actually picked up for implementation, correcting it then (rejected — the Business Owner's correction arrived now, unprompted by any implementation attempt, and several already-published documents (`PROJECT-HANDOFF.md`, the Roadmap) actively mis-describe current task 6.5 scope to anyone reading them today; leaving that live and uncorrected serves no purpose and risks the same error being repeated by a future session that trusts the existing text at face value).
- **Trade-offs:** none identified — this correction narrows scope (removes a whole invented implementation task) rather than adding any, and every affected document already had a working correction/addendum convention to apply it through.
- **Impact:** documentation-only, no `.php`/route/permission/test file touched. `00-implementation-roadmap.md`, `modules/chat_room.md` (both `legacy-project/`), `PROJECT-HANDOFF.md`, this log (`laravel-project/`). Net effect: task 6.5 goes from "open implementation task (Zoom landing page)" to "closed, no further action," and `ChatRoomAdminController`'s disposition goes from "approved, pending execution" back to "genuinely open, needs the Business Owner's direct answer" — a strictly more conservative (less presumptive) state than before this correction, consistent with not implementing anything not explicitly requested.
- **Confidence:** High — this is a direct, explicit, verbatim correction from the Business Owner, not a re-inference.

---

## 15. Fatawa SQL-injection surface — 12 additional `intval()` casts applied, closing the broader vulnerability found during Legacy Evidence Verification

- **Decision:** Applied `intval()` casts to 12 previously-unsanitized `$_GET` parameters across 5 `fatawa/` files, closing the broader SQL-injection surface found during the Legacy Evidence Verification pass (triggered by Business Confirmation #1 confirming `fatawa/` is live/required). Exact scope: `answer.php` (`$id`, `$cat_id`, `$auther_id`, `$channel` — 4 casts), `answer2.php` (same 4 parameters — 4 casts), `auther_profile.php` (`$auther_id` — 1 cast), `subtobics.php` (`$id`, `$cat_id` — 2 casts), `tobics.php` (`$id` — 1 cast). Each change is the identical narrow pattern as entry #11's original fix: `$x = $_GET['x'];` → `$x = intval($_GET['x']);`, nothing else. Applied to both `/mnt/Projects/Php/pure/7amlat` (canonical) and `way2allah-migration/legacy-project` (reference copy), verified byte-identical afterward (SHA-256 match on all 5 files). Explicitly approved by the Business Owner as a second, one-time exception to ADR-0001, after a dedicated read-only verification confirmed all 12 parameters are genuinely integer ID/filter values reaching raw SQL (table below).
- **This is distinct from, and additional to, entry #11's original fix — not a re-application or expansion of it.** Entry #11 (2026-08-07) closed 6 injection points across a different (overlapping) set of files: `single.php`, `answer.php`, `answer2.php`, `channel_fatawa.php`, `sendemail.php`'s `$q`/`$id` parameters, plus `advanced-search/index.php`'s 7 `$this->channel`/`$this->author` sites. Those 6 points (2 of which — `$q` in `answer.php`/`answer2.php` — sit in the same 2 files this entry also touches) were verified unchanged by this pass (`git diff` shows them only because it's measured against the repository's original commit, not because they were re-touched). This entry's 12 casts are net-new, found only during the 2026-08-10 Legacy Evidence Verification pass's exhaustive re-grep of `fatawa/`'s full `$_GET`/`$_POST` surface — a class of finding entry #11 explicitly did not claim to have found exhaustively.
- **Verification performed before implementation (per the Business Owner's explicit requirement):** every one of the 12 parameters was traced from its `$_GET` read through to its actual SQL usage before any cast was applied — all 12 confirmed as raw, unquoted numeric comparisons against integer primary/foreign keys (`nuke_fatwa_topics.id`, `nuke_w2a_cat.id`, `nuke_islamic_authors.id`, `nuke_sat_channels.id`, `nuke_fatwa_questions.auther_id`, `nuke_fatwa_topics.parent_id`/`main_cat`), consistent with the sitewide `intval()`/`get_int_parameter()` convention this codebase uses everywhere else. One immaterial edge-case noted during verification: `page_bar_channels()`/`mostdownload()`/`recentlyadd()` also recognize the literal string `'no'` as a "no channel" sentinel — no link anywhere in `fatawa/` ever constructs a `channel=no` URL to the affected pages, so this is a theoretical, not a real, behavior change.
- **What remains explicitly out of scope — not fixed, not claimed to be fixed:** this entry does **not** mean every SQL-injection risk in the legacy codebase, or even in `fatawa/`'s own dependency chain, is now closed. Specifically left untouched, on direct instruction:
  - **`w2a_config.php`'s `$_GET['page']` → `$offset` path** (feeds `LIMIT`/`OFFSET` in several of `fatawa/functions.php`'s own query functions) — lower severity (passes through `($page-1)` arithmetic before reaching SQL) but genuinely unaddressed; touches shared core code used sitewide, not just `fatawa/`, so explicitly excluded from this fatawa-scoped fix.
  - **`advanced-search/index.php`'s `$this->title`/`$this->from`/`$this->to`** — still reach raw, string-concatenated SQL unguarded by `intval()`; lower severity than the already-fixed channel/author case (protected by `addslashes()` in a quoted context) but not parameterized. `advanced-search/` was explicitly out of scope for this pass.
  - **`backup.php`, `chat_room/`, and every other module not covered by the two Legacy Evidence Verification passes to date** — no claim is made about their SQL-handling safety one way or the other; they simply haven't been audited for this class of issue.
- **Reason:** the Business Owner's Confirmation #1 answer (`fatawa/` is live/required) directly raised the stakes of the broader surface found during evidence verification — the same reasoning that justified entry #11's one-time ADR-0001 exception applies here: a live, production-serving legacy module carrying a confirmed, exploitable SQL-injection class has no defensible reason to stay unpatched while its Laravel migration remains pending on unrelated routing work.
- **Alternatives considered:** bundle this into a broader "fix everything found so far" pass covering `advanced-search`'s and `w2a_config.php`'s findings too (rejected — explicitly out of scope per direct instruction; each additional legacy-side exception to ADR-0001 needs its own explicit approval, not an inferred expansion); wait until `fatawa/`'s Laravel migration ships and let the rewrite naturally not reproduce the vulnerability (rejected, same reasoning as entry #11 — the legacy files keep serving production traffic in the meantime, on an unrelated, unbounded timeline).
- **Trade-offs:** none identified — narrow, mechanical, lint-verified, diff-verified, matching an already-proven-safe pattern.
- **Impact:** `fatawa/answer.php`, `answer2.php`, `auther_profile.php`, `subtobics.php`, `tobics.php` — both `/mnt/Projects/Php/pure/7amlat` and `way2allah-migration/legacy-project`. No route, permission, schema, test, or unrelated file touched. `git diff --stat`: 5 files, 14 insertions/14 deletions each copy (12 new casts + the 2 pre-existing, unmodified `$q` casts from entry #11, included only because the diff base predates entry #11). `php -l`: zero syntax errors, all 5 files, both copies.
- **Confidence:** High — every one of the 12 casts was individually traced to a confirmed raw-SQL integer comparison before being applied, not inferred from pattern-matching alone.

---

## 16. `backup.php`'s `get`/`getdown`/`put` production usage recorded as UNRESOLVED — explicitly not classified dead or active

- **Decision:** Record the production-usage status of `backup.php`'s `UpdateBackup()` operations (`op=KhotabUpdateBackup`, the `get`/`getdown`/`put` sub-branches) as **UNRESOLVED / UNKNOWN**, per direct Business Owner instruction. This is a documentation-of-finding entry, not an implementation decision — no code, route, or schema changed. `CreateSession()`/`LiveUpdate()` are recorded separately as **code-level reachable behavior, confirmed** (their variables are genuinely populated from the request under the deployed PHP 7.2 environment) — but their actual production *frequency* of use is likewise unconfirmed; "reachable" is a claim about the code, not about real-world call volume.
- **What was investigated, read-only, within authorized scope only** (`/mnt/Projects/Php/pure/7amlat` canonical + `way2allah-migration/legacy-project` reference — explicitly **not** `/mnt/Projects/Php/pure/app`, explicitly **not** the 885MB production DB dump, both out of scope for this phase by direct instruction):
  - The two dedicated `logs/backup-*.log` files (`2026-06-30`: empty; `2026-07-01`: one unauthenticated request, dies at the `Session` check before any booking logic runs) — already on record from the prior pass.
  - **New this round:** every sitewide query-performance log present in the canonical copy — `logs/query-performance/2026-07-27.log` through `2026-07-30.log`, plus root-level `2026-07-17.log`, `2026-07-18.log`, `2026-07-23.log`, `2026-07-24.log` (roughly 9 non-contiguous days spanning a month). Confirmed via `ez_sql_mysql.php:2,287,359` that `QueryPerformanceLogger::log()` is called from inside `ezSQL_mysqli::query()` itself — i.e. every SQL query executed through the app's shared DB layer is logged, tagged with the originating request's `uri`/`method`, regardless of which file issues it (and `backup.php` is confirmed to use this same instrumented `$db`/`$w2adb` connection, `backup.php:28,111,119,163,194`). Searched all of the above for `"uri":"/backup.php"` and for the literal table names `nuke_backup_sessions`/`nuke_backup_booking`: **zero matches, across every file.**
  - **New this round:** a repo-wide grep for the operation's literal op-name strings (`KhotabUpdateBackup`, `KhotabCheckLink`, `BackUpListDown`, `BackUpDownloadList`) to check for any in-codebase caller not previously found. Surfaced `english/modules/BackUp/admin/case.php` + `index.php` (1143 lines, read in full) — **confirmed NOT a caller of the root `backup.php`.** It is a structurally independent, self-contained PHP-Nuke admin-panel module living inside the confirmed-dormant `english/` sub-site (Business Confirmation #3: NO), querying its own local tables directly via `$db`, with no `curl`/`file_get_contents`/`fsockopen`/HTTP call anywhere in it, and no reference to `APIKey` anywhere. It shares op-name *vocabulary* and conceptual scope (the same 5-table content family) with the root `backup.php` — plausibly the same original author's naming convention — but is not evidence of a caller, live or otherwise.
- **Why this stops here, not "dead" or "confirmed active":** the combined ~9-day sample (dedicated log + sitewide query log) is meaningfully more evidence than the single log entry the prior pass had, but it remains a **sampled, non-contiguous window, not full production history**. An external client calling this endpoint on an infrequent or irregular schedule (weekly, monthly, on-demand/manual) would not necessarily appear in these particular sampled days. Per explicit instruction: "variables are never assigned in the visible source" (the earlier, code-level finding) is an observation about the code, not proof of non-use, and a multi-day negative log sample is the same category of evidence — stronger, but still not proof.
- **Reason:** the Business Owner explicitly does not know whether these operations are currently used in production, and explicitly instructed that this uncertainty be preserved rather than resolved by inference in either direction — matching this project's standing evidence discipline (never convert an absence-of-evidence observation into a confirmed conclusion).
- **Alternatives considered:** classify `get`/`getdown`/`put` as dead code based on the "never assigned" structural finding plus the log sample (rejected — explicitly instructed against; a structural/sampling observation is not proof of non-use); classify them as active because Business Confirmation #7 confirmed the tool overall is "in active use" (rejected — that confirmation is about the endpoint/mechanism as a whole and does not specify which of its operations; `CreateSession`/`LiveUpdate` alone already satisfy "in active use" without requiring `get`/`getdown`/`put` to also be exercised); pursue the 885MB DB dump or `/mnt/Projects/Php/pure/app` now to try to resolve this definitively (rejected — both explicitly out of scope for this phase, would require separate authorization).
- **Trade-offs:** the uncertainty is preserved rather than resolved — task 6.8's eventual implementation scope for the `get`/`getdown`/`put` half cannot be finalized yet. Accepted deliberately, per explicit instruction, rather than risking either building unneeded scope or silently dropping real (if infrequent) functionality.
- **Impact:** documentation-only. `00-implementation-roadmap.md` task 6.8 (`legacy-project/`) records the full finding and marks its "Next action" satisfied for this phase, with the booking/file-serving half's technical planning explicitly deferred. No `.php`, route, schema, or test file touched.
- **Confidence:** High that the investigation was thorough within its authorized scope and correctly reported. Explicitly **no confidence claimed** on the underlying question itself (production usage) — recorded as unresolved, not as a low-confidence guess in either direction.

---

## 17. Third, narrow SQL-injection fix — `w2a_config.php`'s `$_GET['page']` cast; `advanced-search`'s title/from/to explicitly deferred, not fixed

- **Decision:** Applied a third, one-time exception to ADR-0001: `w2a_config.php:69` — `if(isset($_GET['page'])) $page=$_GET['page']; else $page=1;` → `if(isset($_GET['page'])) $page=intval($_GET['page']); else $page=1;`. Exactly 1 line, 1 file, identical narrow pattern to entries #11 and #15. Applied to both `/mnt/Projects/Php/pure/7amlat` (canonical) and `way2allah-migration/legacy-project` (reference copy), verified byte-identical afterward (SHA-256 match). This was preceded by a dedicated read-only preparation pass (data-flow trace, blast-radius check) presented for approval before any code was touched, per the Business Owner's now-standing requirement for every legacy-side fix.
- **Full data flow, verified before applying:** `$_GET['page']` → `$page` (`w2a_config.php:69`) → `$offset = ($page-1) * $perpage` (`w2a_config.php:70`) → consumed via `global ... $offset` in 6 raw `LIMIT ... OFFSET $offset` SQL sites, all in `fatawa/`: `functions.php:359,408,461,529,633` and `fatawa-channels.php:24`. One other file (`w2acd/functions.php:3`) also declares `global ... $offset` but was checked and confirmed **not** to actually use it (it computes its own local, already-`intval()`'d `$position` instead) — not a real consumer, no additional blast radius beyond `fatawa/`.
- **`advanced-search/index.php`'s `$this->title`/`$this->from`/`$this->to` were explicitly evaluated and explicitly NOT fixed this round — a deliberate deferral, not an oversight.** Read-only tracing (before any code was touched) found `intval()` is not technically appropriate for any of the three: `$this->title` is a validated ≥4-character Arabic search string reaching `title_query()`'s `LIKE '%...%'` clause (`advanced-search/index.php:445,919,1390,1440`); `$this->from`/`$this->to` are expected date strings reaching `DATE('...')` clauses (`:451,453,928-931,1394-1397,1447-1450`). `intval()` on either would silently destroy the value (a title truncates to its leading digit run or `0`; a date like `"2026-01-15"` truncates to `2026`) — a functional break, not a safe cast. No parameterized-query conversion (the only real alternative identified) was applied either — that would be a structurally different, larger-shaped change (touching a shared function plus 3-6 call sites, not a single-variable cast) requiring its own dedicated review. **Recorded here as an explicit, deliberate open item, not silently dropped** — see Blueprint §16 row 15 (unchanged, Open) for the standing record.
- **Reason:** same reasoning as entries #11/#15 — a live, confirmed integer-context injection point in a shared core file used sitewide (though its only real current consumer is the confirmed-live `fatawa/` module) has no defensible reason to stay unpatched, and the fix is narrow, low-risk, and matches an already-proven-safe pattern exactly.
- **Alternatives considered:** bundle the `advanced-search` title/from/to fields into this same round using `intval()` for consistency (rejected — would break the search feature, per the technical trace above; explicitly instructed against); pursue the parameterized-query conversion for those fields now (rejected — out of scope for this round, needs its own dedicated review, explicitly deferred, not decided against).
- **Trade-offs:** none for the applied fix — narrow, mechanical, lint-verified, matching a proven-safe pattern. The deferred `advanced-search` item remains a real, if lower-severity (quoted-context, `addslashes()`-protected), residual exposure — accepted deliberately, not overlooked.
- **Impact:** `w2a_config.php` — both `/mnt/Projects/Php/pure/7amlat` and `way2allah-migration/legacy-project`. `git diff`: 1 file, 1 line changed, each copy. `php -l`: zero syntax errors, both copies. No route, permission, schema, test, or unrelated file touched. `advanced-search/index.php`: **unchanged**, confirmed via `git status` — no modification made or attempted.
- **Confidence:** High on the applied fix (traced end-to-end before applying, identical to two already-proven fixes). High on the decision not to apply `intval()` to `advanced-search`'s three fields (the SQL-context trace makes the functional-break outcome unambiguous, not a judgment call).

---

## 18. Task 6.1 (fatawa) implementation, increment 2 — literal `.htaccess` re-verification corrects two increment-1 route shapes; `showtree()` presentation explicitly assessed, not assumed equivalent

- **Decision:** Before starting increment 2's new capabilities, re-read the literal `.htaccess` text for every `fatawa`-related rule (lines 271-309) rather than continuing to rely on `fatawa.md`'s summarized routing table. This found two of increment 1's shipped routes had the wrong shape: `fatawa-topics-{category}.htm` was missing a mandatory `page` parameter (no 1-parameter variant of this rule exists at all), and `fatawa-group-{category}-{topic}.htm` had its two parameters in the wrong order (the real rule is topic-id-first, `t_id=$1&cat_id=$2`). Both fixed in place: `ContentListingService::fatwaTopicsByCategory()`/`fatwaGeneralQuestionsByTopic()` now return `LengthAwarePaginator` (Laravel's native pagination, matching the plan's explicit instruction not to recreate the legacy page/offset mechanism just fixed in entry #17) instead of a plain `Collection`; `FatwaTopicController::show()`/`questions()` signatures and `routes/content.php` updated to the corrected parameter shapes; the 2 Blade views linking to these routes updated to match; the affected Feature tests updated and re-verified.
- **Also completed, per explicit instruction not to silently declare presentation parity:** a full re-read of `fatawa.php:34-145` (`showtree()`/`have_sub()`) to determine whether the legacy nested-accordion category tree is a functional requirement or purely presentational. Finding: the accordion is pure CSS (a hidden-checkbox/label toggle, no JavaScript), and every node — expandable or leaf, at every nesting level — always renders a real link to `fatawa-topics-{id}.htm`, the same target the drill-down implementation (increment 1) serves. On this evidence, the single-page-vs-drill-down choice is presentation technology for the *reachability* question specifically — not a business behavior requirement. **Explicitly not settled by this evidence, and not decided here:** whether the business wants the "entire tree visible on one page" experience for its own sake, independent of reachability. Recorded as an open presentation question in `FatwaTopicController`'s docblock and `fatawa.md`'s addendum, not resolved.
- **Reason:** the Business Owner's own instruction, given after reviewing increment 1: "Do not leave an inferred route shape as 'confirmed.'" `fatawa.md`'s summary table (`modules.php?name=Fatwa&op=...`, one combined row for ~15 URL patterns) is accurate at the level it was written for, but not precise enough to derive exact parameter shapes from — the increment-1 routes were built from a plausible reading of that summary plus `subtobics.php`'s own PHP variable order, not from the literal rule text, and the reading turned out wrong on both counts.
- **Alternatives considered:** leave the two route shapes as shipped and fix only if a future increment happened to notice (rejected — explicitly instructed to fix now, and leaving a known-wrong public route shape live serves no purpose); infer the `showtree()` presentation question by analogy to how other modules' tree/listing widgets were handled (rejected — explicit instruction to assess this module's own evidence directly rather than reason by analogy, and the direct re-read fully settled the *reachability* half of the question anyway).
- **Trade-offs:** none identified for the route fixes — both are corrections to code that shipped only within this same task, not yet reviewed as final. The pagination change is a genuine scope addition beyond increment 1's original (unpaginated) delivery, needed because the real route shape requires a page parameter; sized appropriately (reused the existing `W2acdController`-established `paginate($perPage, [...], 'page', $page)` pattern, no new abstraction introduced).
- **Impact:** `app/Domain/Content/Services/ContentListingService.php` (2 methods' signatures/return types changed), `app/Domain/Content/Http/Controllers/FatwaTopicController.php` (both non-index actions' signatures changed, docblocks rewritten with the corrected evidence), `routes/content.php` (2 route entries corrected, one split into 2-and-3-parameter forms matching the real `.htaccess` pair), `resources/views/fatawa/topics-index.blade.php`/`topics-show.blade.php`/`questions.blade.php` (links + pagination), `tests/Feature/Content/FatwaTopicControllerTest.php` (updated + 1 new test for the 3-parameter route form). `legacy-project/docs/migration/modules/fatawa.md` gains a full literal-`.htaccess` mapping table and the `showtree()` assessment, addendum-only. Full suite re-verified green after every change.
- **Confidence:** High — every corrected route shape is now cited directly against the literal `.htaccess` line, not a summary or an inference.

---

## 19. Task 6.1 (fatawa) implementation, increment 3 — send-to-friend and day-based browse; two more "confirmed route, no confirmed implementing code" findings

- **Decision:** Implemented the final two capabilities named in the approved technical plan. **Send-to-friend** (`fatawa-friend-sendemail-{id}.htm`, `sendemail.php`): field validation reproduces legacy's own rules exactly (name fields required + minimum 2 characters, email fields required + valid format); mail sending uses a new `FatwaFriendMail` Mailable via `Mail::to()->send()`, not a port of legacy's raw `mail()`+hand-built-headers call, per the approved plan's own instruction. Subject line, sender address (`info@way2allah.com`, hardcoded, matching legacy exactly), and email body content are preserved verbatim in `resources/views/emails/fatawa-friend.blade.php`. **Day-based browse** (`fatwa-today.htm`/`fatwa-today-{page}.htm`, `fatwa-today.php`): the "featured questions" random-sample mechanism (`rand(1,7400)` offset, legacy's own hardcoded ceiling) and the date-scoped answer list are both reproduced exactly, including `ArabicDateConvert()`, ported verbatim (no equivalent existed anywhere in the app).
- **Two more confirmed-route-no-confirmed-code findings, same category as `fatawa-play-*`/`fatawa-brokenlink-*`/`auther-all-fatawa-*` (entry #18) — not invented, not implemented:**
  - `fatawa-friend-{id}.htm` (`.htaccess:307`, `op=friend`) — presumably a form-display step preceding the `sendemail` submit — has no implementing file among the 16 read for this module.
  - `fatwa-date-{d}-{m}-{y}-{page}.htm` (`.htaccess:285`, `op=day&d=$1&m=$2&y=$3&page=$4`) — re-read `fatwa-today.php` in full and confirmed it only ever reads a single `$_GET['date']` string, never separate `d`/`m`/`y` parameters. The client-side calendar widget that generates these URLs was not reproduced either (a ~180-line JS month-grid renderer — a presentation feature, not confirmed page-load data behavior, and out of scope per explicit instruction not to expand the investigation beyond what implementing the two named capabilities required).
- **One confirmed legacy behavior deliberately not reproduced, flagged rather than silently decided either way:** `sendemail.php`'s hand-rolled `validEmail()` performs a live DNS `MX`/`A` record lookup on the submitted email's domain, in addition to syntax checks. This was not reproduced — a DNS-dependent validator is a materially different technical mechanism (a live network call inside request validation, non-deterministic in automated tests) than what "preserve behavior while using the approved mail architecture" was understood to authorize. Recorded as an open technology question in `FatwaQuestionController::sendToFriend()`'s docblock, not decided.
- **One confirmed content quirk preserved, flagged rather than silently changed:** the email body's legacy copy links to `/chat` (the retired live-room feature — Confirmation #4, no Zoom or any replacement). Preserved verbatim as legacy's own marketing copy — editing old email text is a content decision outside this task's scope, not silently updated and not silently left without comment.
- **One piece of confirming evidence for an already-noted column-shape distinction:** `fatwa-today.php`'s featured-questions query joins `nuke_fatwa_questions.topic_id` to `nuke_fatwa_topics.id` via plain equality, not a pipe-delimited match — reinforcing (not new, but now doubly confirmed) that this column is a plain integer reference on `nuke_fatwa_questions`, distinct in shape from the pipe-delimited `general_question_id` column on the same table and from `topic_id` on `nuke_fatwa_general_questions`.
- **Reason:** these were the last two capabilities named in the approved technical plan; both were re-read from legacy source directly (not from `fatawa.md`'s summaries) before implementing, per your explicit instruction.
- **Alternatives considered:** invent a plausible `op=friend` form-display page and a `d/m/y`-parameter-reading day-browse variant, by analogy to the pages that *are* confirmed (rejected — explicitly instructed not to invent behavior for unconfirmed routes, and the precedent set in entry #18 for exactly this situation applies unchanged); reproduce `validEmail()`'s DNS check for full fidelity (rejected — a live network call inside a request validator is a genuinely different technical mechanism, not authorized, and would make the endpoint's tests non-deterministic/network-dependent).
- **Trade-offs:** the DNS-validation gap and the two unimplemented routes are real, if minor, behavioral differences from legacy — accepted deliberately and documented, not silently absorbed.
- **Impact:** new — `app/Domain/Content/Mail/FatwaFriendMail.php`, `app/Domain/Content/Http/Controllers/FatwaDayController.php`, `resources/views/emails/fatawa-friend.blade.php`, `resources/views/fatawa/day.blade.php`, `resources/views/fatawa/friend-sent.blade.php`, `tests/Feature/Content/FatwaDayControllerTest.php`. Modified — `app/Domain/Content/Http/Controllers/FatwaQuestionController.php` (new `sendToFriend()` action), `app/Domain/Content/Services/ContentListingService.php` (2 new methods), `app/Domain/Content/Models/FatwaQuestion.php` (added `@property-read` annotations for `author`/`channel`, required for Larastan to type the relation correctly — a documentation-only model change, no new relation logic), `routes/content.php` (4 new routes), `tests/Feature/Content/FatwaQuestionControllerTest.php` (3 new tests). Full suite: 304/304. PHPStan: 0 errors.
- **Confidence:** High on everything reproduced from directly-read source. Explicitly flagged, not silently assumed, on the two unconfirmed routes and the DNS-validation gap.

---

## 20. Visual parity phase, khotab-item-298784.htm Batch 4 — obsolete media-format fallback deferred by explicit product/owner decision

- **Decision:** `MediaPlayerService::renderPlayer()` (replacing `w2a_mada_play()`, `functions.php:794-855`) implements only the media formats still functional in a current browser — native `<audio>` (mp3), native `<video>` (mp4), YouTube iframe embed, and SoundCloud iframe embed. Legacy's RealPlayer ActiveX, Windows Media Player ActiveX, and Flash `.swf` object branches (covering `rm`/`rmvb`/`ra`/`ram`/other non-mp3 audio, `avi`/`wmv`/`asf`/`divx`/`div`, and the catch-all fallback respectively) are **not reproduced** — no player renders for these formats (`renderPlayer()` returns `null`), and per explicit instruction, no download-link fallback or "unsupported format" message was added either. This is a deliberate, owner-approved deferral, not an implementation gap discovered and left unaddressed.
- **Evidence gathered before the decision:** a full inspection of real khotab data (292,482 items with a non-empty `link`, `hidden=0`) found these obsolete-technology-triggering extensions are materially present, not negligible: `rm` 8,101, `rmvb` 5,467, `wmv` 699, `ram` 236, `ra` 130, `flv` 79, `rv` 57, `wma` 32, `avi` 18, `asf` 16 — combined ≈14,835 items, ≈5.1% of all linked items. (For contrast: `mp4` 232,928/79.6%, `mp3` 44,174/15.1%, `youtube`-pattern links 25.) This evidence was reported to the Business Owner before any fallback behavior was chosen, per the standing "do not silently decide obsolete-format handling" instruction for this phase.
- **Reason:** the Business Owner reviewed the ≈5.1% figure and explicitly chose to keep the current `null`/no-player behavior for these formats rather than build a fallback UI, confirming this is not a case where "do nothing" was assumed by default — it was evaluated against real data and selected deliberately.
- **Alternatives considered (all rejected, by the Business Owner, not by default):** reproducing the legacy RealPlayer/WMP ActiveX/Flash embeds verbatim (rejected — confirmed non-functional in every browser in current use, including on live production today, so reproducing them would only add dead markup); adding a plain "download this file instead" link for unsupported formats (rejected — explicitly ruled out this round); adding an "unsupported format" message (rejected — explicitly ruled out this round, no new UI to be introduced).
- **Trade-offs:** ≈5.1% of khotab content currently has no in-page player at all on the Laravel migration (same practical outcome as live legacy today, where these formats already render non-functional embeds) — a real, acknowledged gap, left open rather than papered over with an unreviewed fallback design.
- **Impact:** No code changed by this decision itself — `MediaPlayerService`'s existing `null`-return behavior for unsupported formats (already implemented) is confirmed as the final, intended behavior for this phase, not a placeholder awaiting a follow-up. `app/Domain/Content/Services/MediaPlayerService.php`'s own docblock already documents this data and the deferral; this entry is the durable project-level record of the decision itself.
- **Confidence:** High — the underlying data (extension distribution over real, current khotab content) was measured directly, not estimated, and the decision was made by the Business Owner against that exact evidence.

---

## 21. `video-advanced-search.htm` — owner-approved GET-based search request method, kept over legacy's POST-only form

- **Decision:** Keep the current Laravel implementation's GET-based advanced-search request (`KhotabSearchController::index()` reads filters from `$request->query(...)`, not `$request->post(...)`). Do not revert to legacy's POST-only form submission. This is an explicit Business Owner ratification of a design choice made during the original `khotab/search.php` port (see `KhotabSearchController`'s own docblock: "this action reads filters from the query string (GET) instead of legacy's POST-only form... a real improvement, not a silent change"), following the Final Visual-Parity Reconciliation Audit flagging that the choice, while documented in code, had not yet been formally recorded as owner-approved.
- **The two facts stay explicitly distinct, at the HTTP-method level only — this is not claimed as historic parity:**
  - `LEGACY_REQUEST_METHOD = POST` — confirmed directly from source: `khotab/search.php`'s own `<form ... method="post">` and its exact field set (`kh_title`, `kh_author_name`, `kh_channel`, `kh_from`, `kh_to`, plus 5 hidden `_h` companion fields), re-verified against both the repo copy and a fresh live fetch of `khotab/search.php` during the reconciliation audit — repo and live agree with each other exactly, i.e. this is a genuine legacy fact, not a stale assumption.
  - `LARAVEL_REQUEST_METHOD = GET` — `KhotabSearchController::index()`'s own field names (`title`, `author`, `channel`, `from`, `to`) and query-string binding, deliberately different from legacy's `kh_*` names, so the request stays idempotent/bookmarkable/shareable.
  - `OWNER_APPROVED_DEVIATION = KEEP_GET` — this decision.
- **What this decision does and does not cover:** it approves the HTTP transport/method choice only. It does not re-open or re-bless anything about search *business* semantics — those were already source-verified independently (title/author/channel/date-range filter logic, the ≥4-character title-length rule, `khotabSeriesAdvancedSearch()`/`khotabAdvancedSearch()`'s query construction) in the original port and in the reconciliation audit's own pass over this page, and are unaffected by which HTTP method carries the request.
- **Reason:** a search is a read/idempotent operation; a GET-based request makes results linkable/shareable (e.g. a user can copy the URL of a specific search and send it, or a browser back/forward button meaningfully restores prior results) — a real, deliberate usability improvement over legacy's POST-only form, which loses all filter state on refresh/back-navigation/sharing. The Business Owner reviewed this rationale directly (this entry) and approved keeping it rather than reverting to byte-for-byte HTTP-method parity.
- **Alternatives considered:** revert to a POST-only form matching legacy's exact `kh_*` field names/hidden `_h` companions, to achieve full request-contract parity (rejected by the Business Owner — would regress the bookmarkability improvement for no functional gain, since the underlying search behavior is already independently verified correct); support both GET and POST on the same route (rejected — adds real complexity, un-requested, for a page with no confirmed external POST-only integration depending on the legacy form contract); leave the classification ambiguous/undecided pending further review (rejected — the reconciliation audit specifically flagged this as needing an explicit decision record, which this entry now provides).
- **Trade-offs:** none newly introduced by this decision — it ratifies existing, already-shipped, already-tested behavior. The only trade-off is the one already accepted at implementation time: a URL built by hand against legacy's old `kh_*` POST field names (if any existed anywhere external) would not carry over to the new GET-based form; no such external dependency has been found in this project's own evidence base.
- **Impact:** Documentation-only. No code, route, or test file changed by this entry. `app/Domain/Content/Http/Controllers/KhotabSearchController.php` (unchanged) already carries the technical rationale in its own docblock; this entry is the durable, project-level owner-approval record the reconciliation audit found missing. Final classification for `video-advanced-search.htm`, recorded here explicitly: `PARITY_CONFIRMED_FROM_SOURCE — OWNER_APPROVED_GET_DEVIATION` (search fields semantically equivalent, filter/query behavior source-verified correct, result rendering source-parity, no security regression — the HTTP-method choice itself is the one, now formally owner-approved, deviation from legacy).
- **Confidence:** High — `LEGACY_REQUEST_METHOD`/`LARAVEL_REQUEST_METHOD` are both directly confirmed from source (repo and live agree for legacy; the controller's own code for Laravel), and the decision itself is a direct Business Owner ratification, not an inference.

---

## 22. `/admincp/` login + dashboard — Wave 5's "no login/dashboard UI" exclusion explicitly superseded

- **Decision:** Build a real, minimal, Laravel-native login page and functional dashboard for the `/admincp/` entry point, reusing the existing `AdminGuard`/`AdminUser`/`EnsureAdminHasRole`/`EnsureAdminHasPermission` architecture exactly as already built (Roadmap task 0.4, ADR-0011) — no second authentication system. This directly and explicitly supersedes `resources/views/layouts/admin.blade.php`'s own prior comment ("No dashboard/logout nav here — building a real login/logout flow is outside this wave's 10 tasks... nothing in the Roadmap asks for a new UI around it this round"), which is preserved history, not deleted — the file's comment itself now records the supersession inline, and this entry is the durable, project-level record of why.
- **Sequence preserved for the record:** Wave 5 (§ this log's earlier entries) explicitly excluded login/dashboard UI from its 10 tasks. A subsequent admin-migration audit (`admincp/` Full Migration & Route-Family Reconciliation, 2026-08-22) independently re-confirmed this was a deliberate, already-documented scope boundary — not an oversight — and classified it `BUSINESS_DECISION_REQUIRED`, explicitly declining to build it without an owner decision. The Business Owner subsequently reviewed that finding and approved implementation (this entry).
- **What was implemented:** `AdminAuthController` (`entry()`/`login()`/`logout()`) reproduces `admincp/index.php`'s own real CONTRACT faithfully — one URL (`/admincp/`), anonymous visitor sees the login form, authenticated admin sees the dashboard directly, matching legacy's own branching exactly. `AdminDashboardModules` derives the dashboard's module list directly from `routes/admin.php`'s own already-registered routes/permission gates (not re-typed from any external list), filtering per-admin via the same `hasAnyPermission()`/super-admin-bypass semantics `EnsureAdminHasPermission` already uses — navigation visibility only, not a new security boundary; every linked route's own middleware is unchanged and independently re-enforced.
- **What was explicitly NOT reproduced (`LEGACY_DEMO_DASHBOARD = DO_NOT_REPRODUCE`, `LEGACY_INSECURE_AUTH_BEHAVIOR = DO_NOT_REPRODUCE`):** `admincp/home.php`'s Metronic demo widgets/notification counts (confirmed, via full-file grep during the audit, to contain zero database queries — dead chrome, not real content); the 7-cookie legacy auth scheme (including a full author row serialized to JSON in a client-side cookie, no `httponly`/`secure`/`samesite`); the MD5/SHA1/plaintext password-verification fallback beyond what `LegacyPasswordVerifier` already intentionally supports (plaintext is confirmed rejected outright — `AdminGuardTest`'s own "never accepts a plaintext-stored value" test); GET-based logout; missing CSRF; `main.php`'s live `var_dump()` debug output. Laravel's already-hardened session/CSRF/Guard architecture remains authoritative throughout — this is an approved security deviation from literal legacy behavior, not a defect to "fix toward legacy."
- **Two real, already-migrated capabilities deliberately excluded from the dashboard's own module list, not by oversight:** the permission editor (`admin.permissions.*`) — already reachable via a per-row link on the Staff module's own index page, and gated by `admin.role:super-admin`, a role check this class's generic permission-driven filtering can't correctly express without inventing an unproven permission key (not done); `admin.broadcasting.*` — no channel-listing/index route exists anywhere in `routes/admin.php` (legacy's own `broadcasting/index.php` list page was never ported, Roadmap task 5.10's own scope note), so there is no real URL to honestly link a dashboard entry to without inventing a new page.
- **One confirmed navigation orphan fixed as part of this same task, not separately:** `admin.link-quality.khotab.large-files` (already built, already working, flagged with no in-view link by the prior audit) now has a real link from `admin.link-quality.khotab.index`'s own view — the same module, the same `khotab.repair` permission gate, an already-existing route/controller action, not new business behavior.
- **A genuine, test-environment-only defect found and worked around during implementation, not a production bug:** `AdminGuard`'s custom-driver registration (`IdentityServiceProvider::boot()`, `Auth::extend('admin-guard', fn ($app) => new AdminGuard($app->make('request'), ...))`) resolves and caches the current `Request` once per container lifetime. In production this is harmless (a fresh container per request, resolved after session middleware has already run). In Pest's HTTP test client, `actingAs()` can pre-resolve and cache the guard against a session-less bootstrap request before a later `post()` call in the same test runs the real middleware pipeline, so a logout test built on `actingAs()` hit a "Session store not set on request" error calling `AdminGuard::logout()`'s `$this->request->session()->forget(...)`. Not a bug in `AdminGuard` itself (its own dedicated unit tests already sidestep this by hand-constructing the guard) — worked around at the test level only, by logging in through the real `POST /admincp/login` flow (which naturally carries a properly-attached session) instead of `actingAs()`, for the two tests that exercise logout specifically. No production code was changed for this.
- **Reason:** the Business Owner reviewed the admin-migration audit's finding (11 already-migrated, tested, permission-gated feature areas with no operational entry point) and explicitly approved closing this specific gap, distinct from and without reopening any other Wave 5 scope decision.
- **Alternatives considered:** port `admincp/home.php`'s Metronic demo dashboard visually (rejected — explicitly ruled out; confirmed to be zero-database-query demo chrome, not real content to migrate); reproduce legacy's exact 7-cookie/plaintext-fallback auth mechanism for byte-level parity (rejected — explicitly ruled out; Laravel's hardened `AdminGuard` architecture is authoritative, this is a deliberate, approved security deviation); a separate `/admin/login` URL instead of reusing `/admincp/` (rejected — no strong architectural reason found to abandon the preserved legacy URL, and the task explicitly required keeping it canonical); give the permission editor its own dashboard entry (rejected — would require inventing an unproven permission key or exposing a role-gated page behind a permission-shaped filter that can't correctly represent a role check; the existing Staff→per-row link already reaches it correctly).
- **Trade-offs:** the dashboard cannot link to Broadcasting (no index route exists to link to) — a real, pre-existing structural gap, not introduced by this task, not silently worked around by inventing a new page. Fixing it would need its own, separately-scoped task (build a channel-listing index for `BroadcastingController`), not bundled into this one. **Addendum (2026-08-22):** this specific trade-off was closed the same day — see entry #23.
- **Impact:** New — `app/Domain/Admin/Http/Controllers/AdminAuthController.php`, `app/Domain/Admin/Support/AdminDashboardModules.php`, `resources/views/admin/auth/login.blade.php`, `resources/views/admin/dashboard.blade.php`, `tests/Feature/Admin/AdminAuthControllerTest.php`. Modified — `routes/admin.php` (3 new unguarded entry-point routes, added above the existing `admin.role`-gated group, which is otherwise unchanged), `resources/views/layouts/admin.blade.php` (minimal `@auth('admin')`-gated Home/Logout nav, superseding its own prior exclusion comment in place), `resources/views/admin/link-quality/khotab.blade.php` (one new link, the navigation-orphan fix above). No existing feature route's URL, controller, or authorization changed. Full suite: 862/861+1 skip, 0 failed (up from 840 before this task). PHPStan: 0 errors.
- **Confidence:** High — every reused piece (`AdminGuard`, permission keys, route names) was re-read directly from source before use, not assumed; the one new finding (the test-environment guard-caching artifact) was root-caused via the Guard's own actual registration code before being worked around, not guessed at.

---

## 23. Broadcasting dashboard residual closed — legacy channel-list branch reconstructed

- **Decision:** Close entry #22's one recorded trade-off by reconstructing legacy `admincp/broadcasting/index.php`'s `op=editstream` branch as `BroadcastingController::index()`, and add it to the dashboard's module list.
- **Investigation finding (re-read `index.php`/`menu.php`/`edit_stream.php`/`delete_stream.php` fresh, source-only, no filename-based inference):** `index.php`'s `op=editstream` branch is real and functional — `SELECT * FROM nuke_sat_channels WHERE streamcode != '' ORDER BY title ASC`, each result linking to `edit_stream.php?op=edit_stream&id=X`. It was never dead code. However legacy's own `menu.php` sidebar link never pointed at it — the only active `modulelinks[]` entry hardcodes `edit_stream.php?op=edit_stream&id=51`, a single specific channel, bypassing the list entirely; the list branch was reachable only by a hand-typed URL. `index.php`'s second branch (`op=addstream`) is doubly dead: gated by a permanently-false `1!=1` PHP condition AND its own `menu.php` link left commented out. `delete_stream.php` opens with `die();` before any other code runs — fully dead.
- **Classification:** `MISSING_INDEX_CAPABILITY` — the list behavior itself was real, functional, and unambiguous from source (not `NAVIGATION_GAP_ONLY`, since no Laravel route for it existed yet at all; not `LEGACY_INDEX_DEAD_NOT_REQUIRED` or `DIRECT_EDIT_ROUTE_IS_CANONICAL`, since the list branch itself was never dead, only unlinked from nav; not `BUSINESS_DECISION_REQUIRED`, since the query/link behavior needed no judgment call to reconstruct).
- **What was implemented:** `BroadcastingController::index()` — `Channel::where('streamcode', '<>', '')->whereNotNull('streamcode')->orderBy('title')->get()`, deliberately not reusing `Channel::scopeEligibleForLiveStream()` (which also filters `active = 0`, a condition legacy's admin list never applied). New route `GET /admincp/broadcasting` → `admin.broadcasting.index`, same `broadcasting.editstream` permission key already gating `edit`/`update` (legacy's own `$authorization['broadcasting']['editstream']` gated this exact operation; no new permission key introduced). New minimal view `resources/views/admin/broadcasting/index.blade.php`. `AdminDashboardModules` now lists Broadcasting.
- **What was explicitly NOT reproduced:** `op=addstream` (permanently unreachable in legacy, both by code gate and by its own commented-out nav link) and `delete_stream.php` (dead on its first executable line) — resurrecting either would be inventing new business behavior, not restoring existing behavior.
- **Reason:** entry #22's completion report classified admin migration `ADMIN_MIGRATION_COMPLETE` but flagged this as a known residual; a follow-up closure task required investigating and either closing or correctly classifying it before that classification could stand unqualified.
- **Alternatives considered:** leave Broadcasting excluded from the dashboard and classify `DIRECT_EDIT_ROUTE_IS_CANONICAL` on the theory that legacy nav only ever used the direct-edit link (rejected — that link was hardcoded to one channel, id=51; treating it as "canonical" would mean no admin could ever reach any other channel's stream editor from navigation, which is a limitation of legacy's nav wiring, not a deliberate design the migration should preserve as-is when the underlying list capability is real, functional, and trivially reconstructable).
- **Trade-offs:** none identified — the reconstructed list uses the exact legacy query/ordering, gated by the exact existing permission key, with no new business logic invented.
- **Impact:** Modified — `app/Domain/Admin/Http/Controllers/BroadcastingController.php` (new `index()` method), `routes/admin.php` (1 new route inside the existing `broadcasting.` group, same middleware), `app/Domain/Admin/Support/AdminDashboardModules.php` (1 new entry), `tests/Feature/Admin/BroadcastingControllerTest.php` (4 new tests), `tests/Feature/Admin/AdminAuthControllerTest.php` (1 assertion extended). New — `resources/views/admin/broadcasting/index.blade.php`. Full suite: 866/865+1 skip, 0 failed (up from 862). PHPStan: 0 errors.
- **Confidence:** High — every legacy file re-read fresh from source this task, not inferred from filenames or recalled from the prior audit; the query/ordering/link target were reproduced byte-for-byte from `index.php`'s own SQL and link construction.

---

## 24. AdminCP Full Visual/Layout Parity Reconstruction

- **Decision:** Reconstruct the real legacy AdminCP shell (Metronic layout4, RTL) from source and reconcile every migrated admin page against it, keeping `LEGACY_DEMO_DASHBOARD = DO_NOT_REPRODUCE` fully authoritative — no authentication, permission, or business-logic change of any kind.
- **Real-shell-vs-demo-content separation (source-verified, not inferred from appearance):**
  - REAL and reconstructed: `header.php`'s logo/sidebar-toggler/RTL chrome, `sidebar.php`'s permission-driven module iteration (real DB-backed: `nuke_authors.permissions`, unserialized per-uid), `footer.php`'s copyright line, `admin_functions.php`'s `open_div()`/`close_div()` portlet pattern (`.portlet.box.{color}` → `.portlet-title .caption` → `.portlet-body`), `w2a__login.php`'s login form structure — LIVE_RENDER_VERIFIED via an anonymous read-only GET of `https://way2allah.com/admincp/`, byte-identical to source (including the leaked `var_dump()` debug output at the very top of the response, confirming that legacy defect is real and currently live).
  - DEMO_CONTENT_DO_NOT_REPRODUCE, confirmed by full-file reads with zero DB queries found: `home.php`'s 4 dashboard-stat cards, 2 chart portlets (`sales_statistics`, `sparkline_bar`), regional-stats vmap portlet, and 2-tab system/activity feed (dozens of hardcoded fake log lines, e.g. "لا تنسوا الدعاء لأخيكم الأدمن رحمه الله"); `header.php`'s "Actions" dropdown (hardcoded New Post/Comment/Share, fake badge counts) and header search form (`action="extra_search.html"`, never wired to a real endpoint); `navigation_menu()`'s notification/inbox/todo dropdowns (hardcoded counts and list items).
  - DEAD_LEGACY (present in source, unreachable in practice): the user dropdown's Profile/Calendar/Messages/Tasks/Lock-Screen entries all point at `.html` targets that don't exist (`extra_profile.html`, `page_calendar.html`, `inbox.html`, `page_todo.html`, `extra_lock.html`); `admincp/w2a__lock.php` is real, functional-looking PHP but is never linked from anywhere reachable (the sidebar's own "شاشة القفل" item points at the dead `extra_lock.html`, not this file) and relies on the same insecure cookie scheme (`$_COOKIE['aid']`/`$_COOKIE['avatar']`) already excluded by `LEGACY_INSECURE_AUTH_BEHAVIOR = DO_NOT_REPRODUCE` — not reproduced.
  - MATCH (reproducing legacy's own hidden state by omission): the page-breadcrumb — legacy's own `<ul class="page-breadcrumb breadcrumb hide">` carries a `hide` class in every page that has one; never visually shown in legacy either.
  - NO_CANONICAL_LEGACY_VISUAL_REFERENCE: the 3 link-quality dashboard/sidebar entries (khotab/mirror/telawah repair tooling) — a Roadmap task 6.7 consolidation with no dedicated legacy `admincp/` subdirectory or `menu.php` of its own, so no canonical icon/label to reproduce; a reasonable icon (`icon-wrench`) was chosen, not sourced from legacy.
- **Asset audit:** every CSS/JS/font asset referenced by the reconstructed shell (font-awesome, simple-line-icons, bootstrap-rtl, components-rtl, plugins-rtl, layout4's layout/theme/custom RTL CSS, login-4-rtl CSS, jquery, bootstrap, bootstrap-hover-dropdown, jquery-slimscroll, app.min.js, layout4's layout.min.js) was already present and 200-reachable under Laravel's `public/assets/` — confirmed file-by-file, none copied. `uniform`/`bootstrap-switch` CSS+JS classified `PRESENT_BUT_UNUSED` (no current admin view uses a uniform-styled checkbox or switch) and not loaded. `demo.min.js`/`quick-sidebar.min.js` classified `DEMO_ONLY` (no layout-customizer or quick-sidebar panel exists to depend on them) and not loaded. `public/logo-light.png` was already present (byte-identical to legacy's own copy, `ALREADY_AVAILABLE_IN_LARAVEL`); `public/login-logo.png` was genuinely `SOURCE_MISSING` and was copied — the only asset file added by this task, a single real logo image, not code.
- **What was implemented:** `resources/views/layouts/admin.blade.php` rebuilt with a real header (logo, admin identity, real POST logout form), a real permission-aware sidebar (reusing `AdminDashboardModules`, extended with a real per-module `icon` sourced from each module's own legacy `menu.php` where one exists), a shared portlet wrapper around every page's `@yield('content')`, and a real footer. All 24 already-migrated feature views needed zero edits — every one already followed the `@extends('layouts.admin')`/`@section('title')`/`@section('content')` pattern, so the shell rebuild alone gives them full chrome/portlet parity. New `resources/views/layouts/admin-guest.blade.php` (login-only frame) and a rebuilt `resources/views/admin/auth/login.blade.php` reconstruct `w2a__login.php`'s real visual design — the hardened Laravel auth flow itself (POST, CSRF, `AdminGuard::attempt()`, generic invalid-credential error, `old('aid')` without `old('password')`) is unchanged. `AdminServiceProvider` gained one view composer supplying the sidebar's data to every `layouts.admin` render.
- **A real Blade `@extends` timing constraint found while implementing, not assumed:** a view composer bound to `layouts.admin` fires when the PARENT view is rendered, which happens only at the very end of a child's compiled output (Laravel's `@extends` renders the parent after the child's own `@section` blocks have already executed) — so composer-supplied data is visible to the parent layout's own markup (the sidebar) but NOT inside a child view's own `@section('content')`. Found by writing a temporary debug test against the real behavior, not by inspecting Blade's source in the abstract. Fixed by having `AdminAuthController::entry()` continue to pass the dashboard's own module list explicitly (`$modules`, used only inside `dashboard.blade.php`'s own content section) alongside the composer's `$sidebarModules` (used only inside the parent layout's own sidebar markup) — both computed via the same `AdminDashboardModules::visibleFor()`, no duplicated permission logic.
- **Reason:** the prior admin-migration work explicitly deferred visual parity (`ADMIN_VISUAL_PARITY_PENDING`); the Business Owner requested this dedicated follow-up once the functional migration was confirmed complete.
- **Alternatives considered:** wrap each of the 24 feature views individually in per-page portlet markup (rejected — the shared-layout approach achieves the same result with zero per-view edits and no duplicated markup, per the task's own "smallest maintainable architecture" instruction); give the dashboard's body list its data via the same view composer used for the sidebar (rejected — a real Blade `@extends` timing bug, see above; fixed via explicit controller-passed data instead); reproduce `demo.min.js`/`quick-sidebar.min.js` "just in case" (rejected — no markup depends on them, loading them would be importing fake-widget/customizer dependencies with nothing to attach to).
- **Trade-offs:** the reconstructed shell simplifies legacy's occasional multi-portlet feature pages (e.g. `edit_stream.php`'s 2 separate portlets) into a single shared portlet per page — verification is `HTML_SOURCE_PARITY_VERIFIED` (structure/classes/assets confirmed present and correctly conditioned), not pixel-level — no browser automation is available in this environment, so `BROWSER_RENDERING_UNVERIFIED` for actual pixel rendering. **Correction (2026-08-22, same day):** the multi-portlet simplification claim above was reviewed and found NOT sufficiently justified — "deliberate simplification" by engineering judgment alone is not a valid basis for dropping source-confirmed real structure, and no owner decision had approved it. See entry #25, which re-investigated every multi-portlet legacy page and fixed the confirmed gaps.
- **Impact:** New — `resources/views/layouts/admin-guest.blade.php`, `public/login-logo.png`, `tests/Feature/Admin/AdminVisualParityTest.php` (10 tests). Modified — `resources/views/layouts/admin.blade.php` (full shell rebuild), `resources/views/admin/auth/login.blade.php` (rebuilt to legacy visual design, auth flow unchanged), `resources/views/admin/dashboard.blade.php` (module list rendering unchanged in substance, icon added), `app/Domain/Admin/Support/AdminDashboardModules.php` (added `icon` per module), `app/Domain/Admin/Providers/AdminServiceProvider.php` (1 view composer), `app/Domain/Admin/Http/Controllers/AdminAuthController.php` (unchanged behavior, `$modules` still passed explicitly). No route, controller business logic, permission key, or already-migrated module's data/behavior changed. Full suite: 876/875+1 skip, 0 failed (up from 866 before this task). PHPStan: 0 errors.
- **Confidence:** High — every "real vs. demo" classification is grounded in a full-file source read confirming DB-query presence/absence (not appearance), the live production login page was independently render-verified byte-identical to source, and every referenced asset's presence was checked file-by-file rather than assumed from the directory listing alone.

---

## 25. AdminCP Final Page-Level Visual-Parity Verification

- **Decision:** Re-investigate every migrated admin feature page's CONTENT (not the already-accepted shared shell) against fresh legacy source, and fix every confirmed real portlet-count/caption/color gap recoverable from existing data without new queries or business-logic changes.
- **Why entry #24's "simplification" claim was wrong:** it asserted the layout's single auto-portlet-wrap was "a deliberate simplification, not a fidelity gap" without either an owner decision or a per-page investigation proving the extra legacy portlets were dead. Neither existed. A source re-read of `broadcasting/edit_stream.php` (the explicit starting point) confirmed both of its portlets are real and functional — an edit form, and a conditional live preview of the current stream embed.
- **Architecture change:** `layouts/admin.blade.php` no longer wraps `@yield('content')` in one generic portlet — it now owns only the shell/content container. A new `<x-admin-portlet>` component (`resources/views/components/admin-portlet.blade.php`) reconstructs BOTH real legacy portlet conventions found in source: hand-typed `<div class="portlet box {color}">` (used by `broadcasting/*`, `survey/index.php`, link-quality pages — default `purple`/`fa fa-comments`, the values every raw-markup page uses) and `admin_functions.php`'s `open_div()`/`close_div()` helper (default `blue`/`fa-folder-open` — a DIFFERENT default from the raw convention, confirmed by reading the helper's own function signature). `light`/`width` props reconstruct the "portlet light bordered" variant and `open_div()`'s side-by-side-width argument. Every one of the 26 admin views now uses this component — none render bare.
- **Full route → legacy-source matrix (source status per page, portlet count fixed where confirmed real):**
  - `admin.broadcasting.edit` → `broadcasting/edit_stream.php`, LIVE_FUNCTIONAL_SOURCE, 2 portlets (form + conditional current-code preview) — **fixed, was 1**.
  - `admin.broadcasting.index` → `broadcasting/index.php`'s `op=editstream` branch, FUNCTIONAL_SOURCE, 1 portlet ("تعديل بث قناة") — caption added, count already correct.
  - `admin.link-quality.khotab.index` → `khotab/stats_khotab.php`, FUNCTIONAL_SOURCE (real navigable link in `khotab/menu.php`, contrary to this task's own earlier "no canonical source" claim — corrected), 1 portlet, distinct caption from page title — **fixed**.
  - `admin.link-quality.khotab.large-files` → `khotab/stats_khotab_200mb.php`, FUNCTIONAL_SOURCE, 1 portlet; this page's `@section('title')` previously held the PORTLET caption instead of the page title (both pages share the same legacy page h1) — **fixed**.
  - `admin.link-quality.mirror.index` → `khotab/stats.php` ("الجودات البديلة" = mirror/alternate quality), FUNCTIONAL_SOURCE, 1 portlet — **fixed**.
  - `admin.link-quality.telawah.index` → `telawah/stats.php`, FUNCTIONAL_SOURCE, 1 portlet — **fixed**.
  - `admin.survey.index` → `survey/index.php`, FUNCTIONAL_SOURCE, 1 portlet ("قائمة الاستبيانات"); table was missing a real "#" column and a real "مشاهدة" (view, external link to the public survey) column — **fixed**.
  - `admin.survey.create` → `survey/add_survey.php`, FUNCTIONAL_SOURCE, 1 portlet (`open_div()`, default blue) — **fixed**.
  - `admin.survey.questions.*` → `survey/add_question.php`, FUNCTIONAL_SOURCE, 3 real portlets (add / reorder / delete-listing) in legacy; this page already merges delete into the reorder list (every question still individually deletable) rather than duplicating the list a second time — a real, non-regressive consolidation, not a dropped portlet — reconstructed as 2 portlets, both `open_div()` default blue — **fixed**.
  - `admin.survey.stats` → `survey/stats.php`, FUNCTIONAL_SOURCE, 1 portlet, dynamic caption; missing "#" column — **fixed**. (Legacy also loads a Google Charts donut-chart script whose target `<div>` is commented out in the same file — `CONFIGURED_BUT_INERT`, not reproduced.)
  - `admin.survey.answer.show` → `survey/answer.php`, FUNCTIONAL_SOURCE, 1 portlet, dynamic caption ("{survey} - {respondent}") — **fixed**.
  - `admin.survey.all-stats` → `survey/all_stats.php`, FUNCTIONAL_SOURCE, N "portlet light bordered" sub-portlets (one per question) — had none — **fixed**. (This page's own pre-existing fix — aggregating every respondent's tally instead of legacy's last-loop-leftover-respondent bug — is unrelated and unchanged.)
  - `admin.soundcloud.edit` → `soundcloud/index.php`, FUNCTIONAL_SOURCE, 1 portlet ("مقطع الساوندكلاود") — **fixed**.
  - `admin.youtube.edit` → `youtube/index.php`, FUNCTIONAL_SOURCE, 1 main portlet + N "light bordered" per-video sub-portlets — had none — **fixed**.
  - `admin.locations.index` → `locations/index.php`, FUNCTIONAL_SOURCE, 1 portlet, color explicitly `blue` in source (not the raw-markup default purple) — **fixed**; missing "#" column — **fixed**.
  - `admin.locations.create`/`.edit` → `locations/add.php`/`edit.php`, FUNCTIONAL_SOURCE, 2 real portlets — "بيانات الموقع" (form, blue, width 4) and "الخريطة" (an interactive Google Maps lat/lng picker, blue, width 8). The form portlet is **fixed**; the map portlet is a genuinely new JS+external-API integration, not a recoverable view-model change — **CONFIRMED_PAGE_MARKUP_GAP, deferred**, not fabricated.
  - `admin.questionnaire.index` → `questionnaire/index.php`'s list branch, FUNCTIONAL_SOURCE (confirmed 3 real portlets on this file overall via a raw-markup census), 1 portlet added; the exact legacy caption for this specific branch was not independently re-read this pass — lower confidence, flagged in the view's own comment, not asserted as verified.
  - `admin.questionnaire.show` → `questionnaire/index.php`'s `id>0` branch, FUNCTIONAL_SOURCE, "بيانات شخصية" portlet with `fa-gift` icon (confirmed exact) — **fixed**; a 2nd real portlet (remarks) was confirmed to exist but its exact caption was not re-read — a reasonable, non-fabricated label used, flagged lower-confidence.
  - `admin.chat.index` → `chat/index.php`, FUNCTIONAL_SOURCE, 2 portlets ("قائمة الغرف المفتوحة"/"قائمة الغرف المغلقة") — **fixed, was h2 sections**.
  - `admin.chat.edit` → `chat/edit_room.php`, FUNCTIONAL_SOURCE (IF-034: the legacy form/delete links have no legacy backend — this page's ACTIONS were already a real rebuild in an earlier wave, unrelated to this fix), 3 real portlets (main form blue, "قائمة المشرفين" red, "قائمة المحاضرين" green) — **fixed, was plain sections**.
  - `admin.uploaders.index` → `khotab/uploaders.php`, FUNCTIONAL_SOURCE, 1 portlet, caption matches this page's existing title exactly — **fixed**.
  - `admin.staff.index` → `authors/index.php`'s real branch (the other, `op=addstuff`, is DEAD — `die('hhhh')` mid-file), FUNCTIONAL_SOURCE, dynamically 1-or-2 portlets split by `radminsuper` ("قائمة الإدارة العليا للموقع" / "قائمة المشرفين") — **fixed, was 1 flat table with a synthesized rank column**. Legacy's `رقم الهاتف`/`الفيسبوك` columns are NOT reproduced — `AdminUser` has no `mobile`/`facebook` column in the current schema/fixture; adding them is a schema change, out of this task's protected scope — **CONFIRMED_PAGE_MARKUP_GAP, deferred**.
  - `admin.staff.create` → the DEAD `op=addstuff` branch's real chrome (caption/icon `fa-gift`) around an already-rebuilt real form (`OWNER_APPROVED_REBUILT_VARIANT` for the logic, established in an earlier wave) — portlet chrome **fixed** (chrome is separable from whether the underlying dead logic worked).
  - `admin.permissions.edit` → `authors/edit_author.php`, FUNCTIONAL_SOURCE, a real 4-portlet page: profile-sidebar (avatar/name/vBulletin rank/PM link/an inert "حذف كمشرف" dead button), profile-content (real live vBulletin member stats), N "portlet light" permission-checkbox blocks per module, and a password-change modal. Only the permission-checkbox portlets are **fixed** this pass (plain `<fieldset>` → `<x-admin-portlet light>`); the 2 profile portlets are **CONFIRMED_PAGE_MARKUP_GAP, deferred** — displaying real vBulletin stats needs a new cross-database query, not a pure view-model change; the password modal is kept as this page's existing inline form (a UI-pattern simplification of a fully-present feature, not missing content).
  - `admin.entry` (dashboard) → `home.php` is 100% demo/dead (unchanged finding from entry #24); `NO_CANONICAL_LEGACY_VISUAL_REFERENCE`, given a plain reasonable portlet wrap ("الأقسام الإدارية").
- **What was NOT changed:** no query, `AdminGuard` behavior, permission key, or route's authorization middleware — confirmed by the full suite passing unchanged in count/pass-rate for every pre-existing test, plus a fresh 403-still-works assertion added in this task's own new test file.
- **Reason:** the prior visual-parity report's classification (`ADMIN_MIGRATION_COMPLETE — FUNCTIONAL_AND_VISUAL_PARITY_RECONCILED`) was correctly withheld pending this investigation, per the owner's explicit standard that engineering-judgment "simplification" claims are not sufficient without either proof-of-dead-content or an owner decision.
- **Alternatives considered:** leave the single-portlet-per-page architecture and only fix Broadcasting (rejected — the same unjustified-simplification problem exists on every other multi-portlet page found, not just the one named example); build the Locations map picker and the Permissions profile-stats portlets to reach 100% page-level parity (rejected for this pass — both require new JS/external-API or new cross-database-query work genuinely beyond a "visual fix," left as honestly-classified deferred gaps rather than rushed).
- **Trade-offs:** 2 real, confirmed content gaps remain deferred (Locations' interactive map portlet; Permissions' vBulletin profile-stats portlets) — both require work beyond a view-only fix, not simplification by choice. **Addendum (2026-08-22, same day):** both were re-investigated fresh — see entry #26. The Permissions portlets were implemented (a real, already-existing `VbUser` model made the query genuinely minimal). The Locations map remains blocked, now on stronger evidence (a deprecated Maps API loading pattern and a hardcoded, non-reusable API key found in source).
- **Impact:** New — `resources/views/components/admin-portlet.blade.php`, `tests/Feature/Admin/AdminPageLevelParityTest.php` (8 tests). Modified — `resources/views/layouts/admin.blade.php` (no longer auto-wraps content) and all 24 previously-untouched feature views (portlet wrapping added/corrected per the matrix above), `docs/reviews/admincp-visual-parity-reconstruction.md`. No controller, route, query, or permission file touched. Full suite: 884/883+1 skip, 0 failed (up from 876 before this task). PHPStan: 0 errors.
- **Confidence:** High for every page explicitly listed as "fixed" above (each portlet count/caption/color re-read from source this task, not carried over from the prior report's assumptions). Medium for the 2 lower-confidence captions (`questionnaire.index`'s list-view caption, `questionnaire.show`'s 2nd portlet caption) — explicitly flagged as such in both this entry and the affected views' own comments, not presented as verified.

---

## 26. AdminCP Final Page-Level Visual-Parity Closure — Locations map and Permissions profile-stats resolved

- **Decision:** Re-investigate entry #25's 2 deferred findings fresh from source rather than trusting the prior classification, per the owner's explicit "do not blindly trust the prior finding" instruction. Implement whichever is genuinely recoverable without new business decisions; correctly block the other.
- **Locations map — re-investigated, `OWNER_DECISION_REQUIRED` confirmed, NOT implemented:** fresh full reads of `locations/add.php` and its page-specific `locations.js` found the map loads via `https://maps.google.com/maps/api/js?sensor=false&language=ar` — Google's own unversioned, keyless Maps JavaScript API loading pattern, deprecated and non-functional against Google's current infrastructure (Google now requires an API key and billed project for this API; the `sensor` parameter was removed years ago). The page's own server-side geocoding call (`getaddress()`) uses a **hardcoded API key found directly in legacy source** (`AIzaSyBdkUtocaKQkVuQz09HNL1PSuzSbQyqGJ8`) — reusing a credential found in old source without owner authorization is itself a real risk (unknown validity, unknown billing owner, unknown quota state), not merely an implementation inconvenience. Additionally, `add.php`'s own database INSERT is `//` commented out in the read source — even this file's core save path was non-functional as captured. This is exactly the trigger condition the task named for `OWNER_DECISION_REQUIRED` (new provider/API key/billing arrangement) — not implemented. Laravel's existing plain lat/lng text inputs remain the functional equivalent.
- **Permissions profile-stats — re-investigated, confirmed real/recoverable, IMPLEMENTED:** re-read `authors/edit_author.php` confirms 2 real portlets sourced from one read-only query, `SELECT * FROM user WHERE userid = {uid}` against vBulletin's own database (already-accessible, already-integrated: this application's `app/Domain/Identity/Models/VbUser.php` is a pre-existing, read-only Eloquent model on the `vbulletin` connection, `save()`/`delete()` already hard-blocked by design). The specific columns needed (`email`, `posts`, `joindate`, `lastactivity`, `lastpost`, `usertitle`) were already independently modeled in `tests/Support/Fixtures/VbulletinSchema::user()` before this change, and legacy's own code reads them unconditionally (no `isset()`/fallback guards), confirming they are real, always-present columns in production. `PermissionController::edit()` now does one `VbUser::find($admin->uid)` lookup (returns `null` gracefully if the admin has no linked vBulletin account — legacy has no such guard and would silently emit warnings/blank fields, a hardening not a fidelity loss) and passes it to the view. `resources/views/admin/permissions/edit.blade.php` renders 2 new portlets ("بيانات العضو": avatar/name/forum-PM link/usertitle; "الملف الشخصي": member id/email/post count/post rate/last activity/last post/join date) only when `$vbUser` resolves. Legacy's "حذف كمشرف" button (confirmed dead — a plain `<button>` with no handler anywhere in source) is NOT reproduced.
- **What was explicitly NOT touched:** `AdminGuard`, `LegacyPasswordVerifier`, Spatie permission architecture, permission keys, CSRF, permission-save semantics, super-admin-only authorization on this route — all confirmed unchanged by the full suite's unchanged pass count for every pre-existing test plus a fresh save-semantics-unaffected assertion in this task's own new tests.
- **Reason:** entry #25 deferred both findings without independently re-verifying them against fresh source (a real risk this task's own instruction explicitly warned against — "Do NOT blindly implement the Locations/Permissions findings merely because they were mentioned previously. Evidence first."). Re-investigation showed the two findings were NOT symmetric: one is genuinely blocked on an owner decision, the other was a straightforward, already-infrastructure-supported gap that should not have stayed deferred.
- **Alternatives considered:** implement a NEW map provider (Leaflet/OpenStreetMap) to avoid the Google Maps dependency entirely (rejected — explicitly named as out-of-bounds by this task's own instruction: "Do NOT invent a replacement map provider/API if the original dependency is unavailable"); reuse the hardcoded legacy Google API key found in source (rejected — an unauthorized credential-reuse risk, not a legitimate implementation path); defer the Permissions portlets again on the theory that "any cross-database read might be out of scope" (rejected — the query is read-only, uses an already-existing, already-approved model, and changes no business/auth semantics; the prior deferral was itself the overcautious error this task asked to correct).
- **Trade-offs:** none for the Permissions fix (purely additive, read-only, gracefully degrades when no vBulletin account is linked). The Locations map remains a real, named, owner-facing blocker — recommended as its own separately-scoped follow-up once a Maps provider/API key/billing decision is made.
- **Impact:** Modified — `app/Domain/Admin/Http/Controllers/PermissionController.php` (1 new read-only `VbUser::find()` lookup), `app/Domain/Identity/Models/VbUser.php` (10 new `@property` entries, evidence-backed per the model's own documented bar), `resources/views/admin/permissions/edit.blade.php` (2 new conditional portlets), `tests/Feature/Admin/PermissionControllerTest.php` (4 new tests). No route, query outside the one read-only lookup, permission key, or auth semantic changed. Full suite: 888/887+1 skip, 0 failed (up from 884 before this task). PHPStan: 0 errors.
- **Confidence:** High — the vBulletin columns used were independently corroborated by both legacy source (unconditional reads) and this project's own pre-existing test fixture (written before this task, for unrelated purposes); the map API's deprecated loading pattern and hardcoded key are both directly visible in the freshly-read source, not inferred.

---

## 27. AdminCP Locations Map — owner decision applied (Leaflet/OpenStreetMap, no Google dependency)

- **Decision:** `LOCATIONS_MAP = IMPLEMENT_WITH_OPEN_SOURCE_PROVIDER` (owner decision). Reconstructed the Locations map with Leaflet + OpenStreetMap tiles — no Google Maps, no reused legacy API key, no new billing account, no geocoding provider.
- **Fresh behavior-level re-investigation (not assumed from the prior pass):** re-read `locations/add.php`, `locations/edit.php`, and `locations.js` in full, specifically to answer the create-vs-edit and geocoding-necessity questions the prior pass hadn't resolved.
  - **New finding, corrects the prior pass:** `add.php`'s INSERT is commented out (`DEAD_LEGACY_CREATE_BEHAVIOR`, confirmed) — but `edit.php`'s UPDATE is **live and real**, not dead. The two pages are not symmetric; the prior pass's blanket "the map is deferred" framing didn't distinguish this.
  - Map click → sets `#loc_long`/`#loc_lat` from the click coordinate: `FUNCTIONAL_LEGACY` (real interaction, would work today if the underlying Maps API still loaded).
  - Map click → reverse-geocodes `#address`/`#country` via a client-side call to Google's Geocoding API, and a separate server-side `getaddress()` helper using a hardcoded key: both `CONFIGURED_BUT_INERT`/`PRESENTATION_ONLY` — the server-side call site is commented out on BOTH `add.php` and `edit.php`; address/country are plain manually-editable text inputs regardless (their own placeholder text — "click the map to fill this" — already implies manual entry was an accepted fallback). **Geocoding is not required for a functional workflow** — confirmed, not assumed, before deciding to omit it.
  - `edit.php`'s map never positions the existing marker from the location's own stored `lat`/`lng` — `locations.js`'s own `$(document).ready()` always calls `initialize(31.046109, 31.359602)`, the same hardcoded default, regardless of what `edit.php`'s surrounding PHP had available. A confirmed legacy bug, not a design choice — **not reproduced**; this component centers on and marks the real stored coordinate instead, per the owner's own "behavioral parity, not provider parity" framing.
- **Create vs. edit, handled independently, per instruction:** legacy's own `add.php` INSERT stays dead and is NOT resurrected. But Laravel's `admin.locations.store` action was already a real, working, independently-tested rebuild BEFORE this task (`SoundcloudYoutubeLocationsTest.php`'s own pre-existing test title: "the rebuilt add-flow actually persists a Location — proving the fix, not the legacy no-op INSERT") — adding a map widget to a page whose save action is Laravel's own already-working logic is not resurrecting legacy's dead behavior; it's adding a real input aid to an already-real save path. The map was added to both `locations/create.blade.php` and `locations/edit.blade.php` on that basis.
- **Asset decision:** Leaflet 1.9.4 downloaded and self-hosted, verified genuine via the file's own preserved header comment and matching known file sizes, not an unpinned/unverified CDN reference, matching this project's existing convention of vendoring third-party JS/CSS locally (the whole Metronic bundle is handled the same way) — with one correction found mid-implementation: the first attempt placed the files at `public/assets/global/plugins/leaflet/`, which was immediately caught because `laravel-project/public/assets` is itself a symlink to `legacy-project/assets` (a pre-existing symlink, dated before this session, explaining why every Metronic/jQuery/Bootstrap asset was already "present" without ever being copied) — writing there would have put new files inside the protected, off-limits legacy repo. Moved to `public/vendor/leaflet/` instead, a genuine Laravel-project-only path, confirmed via `readlink -f` and a clean `git status` on both repos after the move. Tiles: OpenStreetMap's standard public tile server (`{s}.tile.openstreetmap.org`), free, keyless, no billing account — requires outbound internet access from the *visitor's browser* at view-time to fetch tile images (not proxied through this application) and carries a required attribution link, kept in the map's own attribution control.
- **What was NOT changed:** Locations queries, validation, CRUD authorization, permission keys, database schema, or the stored `lat`/`lng` column format — confirmed by the existing `Location` model/columns being reused as-is and the full suite's unchanged pass count for every pre-existing Locations test.
- **Reason:** the prior report correctly declined to invent a Google-key-dependent or unauthorized-provider implementation and named the exact decision needed; the owner has now made that decision.
- **Alternatives considered:** reuse the legacy hardcoded Google API key (rejected — explicit owner instruction, and an unauthorized-credential-reuse risk regardless); add Nominatim or another geocoder to fully replicate legacy's reverse-geocoding convenience (rejected — confirmed not required for a functional workflow, and not pre-authorized by the owner decision; address/country remain manually editable, matching legacy's own accepted fallback); reproduce `edit.php`'s hardcoded-default-marker bug for strict byte-fidelity (rejected — the owner decision explicitly frames the objective as behavioral parity, and reproducing a confirmed bug serves no one).
- **Trade-offs:** none identified for the implemented scope. A future admin could still request reverse-geocoding as a separate, explicitly-authorized enhancement if wanted — not assumed here.
- **Impact:** New — `resources/views/components/admin-location-map.blade.php`, `public/vendor/leaflet/{leaflet.js,leaflet.css,images/*}` (vendored, pinned v1.9.4, Laravel-project-only path). Modified — `resources/views/admin/locations/create.blade.php`, `resources/views/admin/locations/edit.blade.php` (added the map portlet + `id` attributes on the lat/lng fields the component targets), `tests/Feature/Admin/SoundcloudYoutubeLocationsTest.php` (6 new tests). No controller, route, query, permission key, or schema file touched. Full suite: 894/893+1 skip, 0 failed (up from 888 before this task). PHPStan: 0 errors. `legacy-project` confirmed clean (0 changed files) both before and after the mid-task asset-path correction.
- **Confidence:** High — every behavioral claim (create INSERT dead, edit UPDATE live, geocoding call sites commented out, marker-positioning bug) is drawn from a fresh, complete re-read of the exact 3 legacy files this task named, not carried over from the prior pass's classification.

---

## 28. AdminCP Staff phone/Facebook — resolved as confirmed dead legacy behavior, no migration required

- **Decision:** Close the last remaining AdminCP visual-parity residual by re-investigating its legacy contract fresh, rather than acting on entry #25's original "deferred, schema-change-scoped" classification (itself already reclassified `SEPARATE_OWNER_DECISION_REQUIRED` by entry #26's provenance check, since no actual owner sign-off existed for that deferral either).
- **Fresh legacy contract, fully traced:** `authors/index.php`'s staff-list query (the only place `مبيل`/`facebook` labels appear anywhere in `admincp/`) is `SELECT id, uid, aid, email, thumb, radminsuper FROM nuke_authors ORDER BY radminsuper DESC, aid ASC` — it does **not** select `mobile` or `facebook`. The markup two lines later references `$staff->mobile` and `$staff->facebook` regardless, but since the query never fetched those columns, both are always empty/undefined on every row, in every production request — a confirmed, unconditional bug in legacy's own code, not a hypothesis. All 4 `edit_author.php` copies in `admincp/` (`authors/`, `broadcasting/`, `khotab/`, `chat/` — every copy `PermissionController`'s own docblock already names) were checked fresh: none has a `mobile` or `facebook` field, editable or otherwise. A repo-wide grep for any `nuke_authors` write touching either column found none — the only 2 matches anywhere in `admincp/` for `mobile\s*=`/`facebook\s*=` are `$staff->facebook = trim($staff->facebook);` in `authors/index.php` and `questionnaire/index.php` — both in-memory PHP variable reassignments, not database writes, and the `questionnaire/index.php` one belongs to a completely different table (`estebian`, the preacher-questionnaire responses, already correctly migrated under `QuestionnaireController`) — unrelated to Staff.
- **Classification: `LEGACY_DEAD_OR_NONCANONICAL — NO MIGRATION REQUIRED`.** These are not real, recoverable Staff content — they are vestigial markup referencing columns the page's own query never populates and no page anywhere ever writes. Implementing them in Laravel would show real, live data legacy itself never actually displayed to any admin — the opposite of parity, not a fulfillment of it.
- **Owner decision framing, addressed directly:** the task's own conditional owner-decision text (`STAFF_PHONE_FIELD = IMPLEMENT` / `SCHEMA_CHANGE = APPROVED...`) was explicitly gated on "if legacy source proves phone/mobile and Facebook are real persisted Staff fields" — the investigation proves the opposite, so that conditional owner decision does not trigger. No schema change was made; none was needed.
- **What was NOT changed:** `AdminUser`'s columns/fillable/casts, `AdminStaffController`, password generation, roles, permissions, auth behavior, or the consolidated Staff/`edit_author.php` architecture (ADR already covering the "one real permission-editor implementation" decision, untouched). The only file touched is `resources/views/admin/staff/index.blade.php`'s own explanatory comment, correcting its prior "deferred, schema-blocked" framing to the now fully-investigated "confirmed dead" finding — the view's actual rendered output is unchanged (it already correctly omitted these columns).
- **Reason:** this was the sole remaining classified AdminCP visual-parity blocker; the task required either implementing it (if real) or closing it as dead (if not), and explicitly forbade leaving it in an ambiguous "deferred" state without a fresh check.
- **Alternatives considered:** implement the columns anyway "for completeness" (rejected — would fabricate working behavior legacy never had, the opposite of the parity goal); leave the residual as `SEPARATE_OWNER_DECISION_REQUIRED` without further investigation (rejected — the task explicitly required re-investigating the legacy contract fresh before accepting any deferral, and doing so resolved the ambiguity outright).
- **Trade-offs:** none — no code behavior changed, only documentation accuracy.
- **Impact:** Modified — `resources/views/admin/staff/index.blade.php` (comment only). No controller, model, migration, route, or test file changed. Full suite: 894/893+1 skip, 0 failed (unchanged from before this task, as expected for a documentation-only closure). PHPStan: 0 errors.
- **Confidence:** High — the query's exact column list and every `edit_author.php` copy were re-read directly from source this task, not inferred from behavior or carried over from any prior classification.

---

## AdminCP Closure — Final Authoritative State (2026-08-22)

Entries #22-28 above are the complete AdminCP decision trail, each preserved as written and not rewritten by this note. This entry exists only to record the single current status a reader should trust, consolidating that trail:

| # | Item | Outcome |
|---|---|---|
| 22 | `/admincp/` login + dashboard | Implemented, superseding Wave 5's original exclusion |
| 23 | Broadcasting index/reachability | Implemented (`BroadcastingController::index()`) |
| 24 | Shared Metronic AdminCP shell | Reconstructed (later corrected by #25 — see below) |
| 25 | Page-level portlet/caption/color parity, all 11 modules | Reconciled; corrected #24's unjustified single-portlet simplification claim; deferred 2 findings + 1 residual rather than guessing |
| 26 | Permissions vBulletin profile/stats | Implemented (existing `VbUser` model, read-only) |
| 26 | Locations interactive map | Classified `OWNER_DECISION_REQUIRED` (deprecated Google API, hardcoded key found in source — not reused) |
| 27 | Locations interactive map | Owner decision applied — implemented with Leaflet + OpenStreetMap, no Google dependency |
| 28 | Staff phone/Facebook columns | Investigated fresh, classified `LEGACY_DEAD_OR_NONCANONICAL` — legacy's own query never selects these columns; no migration required |

**Historical note (2026-08-22, superseded below the same day):** this table once recorded `ADMIN_MIGRATION_COMPLETE — FUNCTIONAL_AND_VISUAL_PARITY_RECONCILED` as final. It was reopened the same day by owner-supplied browser evidence — see entry #29. That entry's own finding was itself a false alarm (both pages render identically once the real root cause, a slow remote asset, was fixed) — the classification below is correct and current as of entry #29's close.

---

## 29. AdminCP Login — browser-rendered visual mismatch investigated and closed (self-hosted background images)

- **Decision:** Reopen entry #27/#28's `ADMIN_LOGIN_VISUAL_PARITY_CONFIRMED` on the owner's own manual browser observation that the page still looked materially different — investigate with real rendering evidence, not HTML/source comparison alone, per explicit instruction not to dismiss the owner's observation just because markers/assets/tests already passed.
- **Method — real browser screenshots, not source inspection:** `google-chrome --headless --screenshot` was available in this environment and used directly against both `https://way2allah.com/admincp/` and `http://127.0.0.1:8123/admincp/`. First-pass screenshots (5-second virtual time budget) showed a real, visible difference: production rendered its full Islamic-geometric-pattern background image; the local page rendered a plain white/gray background with none of it — the single largest visible mismatch a human would notice, exactly matching what the owner reported.
- **Root-cause investigation, not a guess:** confirmed `jquery.backstretch.min.js` (the plugin `login-4.js` uses) was correctly self-hosted, byte-identical (md5) to production's own copy, and — contrary to an initial partial read — DOES define a static `$.backstretch(...)` alias (`a.backstretch=function(c,b){return a("body").backstretch(c,b)...}`, found later in the same minified file), so the plugin call itself was never broken. No browser console errors were found in headless logging either. Re-screenshotting with a longer virtual-time-budget (15s) showed the background rendering correctly and matching production closely — proving the code was correct but SLOW: the previous fix (entry from the prior login task) referenced the 4 background images directly from the live `way2allah.com` origin rather than self-hosting them, so every local page load triggered a fresh, uncached, cross-origin fetch of 4 unbundled images — a real, reproducible, screenshot-confirmed loading delay absent from production's own (presumably fast, local) asset serving. This is exactly what the owner would have seen opening the page and looking within the first few seconds.
- **Fix:** downloaded and self-hosted the 4 background images at `public/vendor/login-bg/{1..4}.jpg` — a genuine Laravel-only path (same convention as the Leaflet assets from the Locations map closure), verified as real JPEGs (`file` command, correct dimensions/EXIF). The login page's inline script now references `asset('vendor/login-bg/N.jpg')` instead of the remote origin. Re-screenshotting at the SAME 5-second budget that originally exposed the problem now shows the background rendering correctly, matching production closely (597,610 bytes vs. production's 606,861 bytes for the full-page PNG, vs. 103,607 bytes for the broken pre-fix state at the same budget) — a concrete, reproducible, before/after screenshot comparison, not an assumption.
- **What was NOT the bug, ruled out with evidence before concluding:** the `$.backstretch` static-alias theory (disproven by finding the alias later in the same file); a JS execution error (disproven — no console errors, and re-running the identical code with more time produced correct output); a version mismatch between local and production's backstretch.js (disproven — md5-identical).
- **What remains, correctly not "fixed":** the "تذكرني" (remember-me) checkbox is visible in every production screenshot and absent from every local one — this is the same already-documented, already-approved, deliberate exclusion from the prior login task (dead in legacy, out of this task's `AdminGuard`/`AdminAuthController` protected scope) — confirmed, not newly discovered, and not touched here.
- **Reason:** the owner's own manual browser inspection is stronger evidence than any HTML/CSS-marker comparison; the task explicitly required treating it as authoritative and finding a concrete explanation rather than re-asserting the prior HTML-level conclusion.
- **Alternatives considered:** proxy/cache the remote images through the Laravel app at request time (rejected — unnecessary complexity; self-hosting a copy is simpler, matches the existing Leaflet-vendoring convention, and removes the cross-origin dependency entirely); reduce backstretch's fade/duration timing to mask the delay (rejected — treats a symptom, not the actual root cause, and would still leave a real network dependency on a third-party origin for a purely decorative image).
- **Trade-offs:** none identified. The self-hosted copies are a point-in-time snapshot of the 4 images; if production's own background images are ever changed, this local copy will not automatically follow — a low-consequence, easily-refreshed drift for 4 decorative JPEGs, not tracked further here.
- **Impact:** New — `public/vendor/login-bg/{1,2,3,4}.jpg` (self-hosted, ~75KB each). Modified — `resources/views/admin/auth/login.blade.php` (background image source only), `tests/Feature/Admin/AdminVisualParityTest.php` (1 test updated, 1 new test — asset self-hosting confirmed, file existence checked directly). No `AdminGuard`, `AdminAuthController`, route, permission, dashboard, or other-module file touched. Full suite: 901/900+1 skip, 0 failed (up from 900 before this task — net +1, one test updated in place, one new). PHPStan: 0 errors.
- **Confidence:** High — every claim in this entry is backed by a concrete artifact (a screenshot file, an md5 checksum, a byte-count comparison, a `file`-command image-type verification), not visual impression or HTML-only inspection.

**Historical note (2026-08-22, superseded below the next day):** this table once recorded `ADMIN_MIGRATION_COMPLETE — FUNCTIONAL_AND_VISUAL_PARITY_RECONCILED` as final, covering login browser parity. The owner then reported the AUTHENTICATED dashboard/inner pages still looked materially different — see entry #30, which found a real, severe defect (not merely visual) undiscovered by entries #24-29's own login-only browser check.

---

## 30. AdminCP authenticated pages — real browser investigation, table-styling fix, and one severe pre-existing bug found

- **Decision:** Reopen authenticated-page visual parity (login parity from #29 explicitly stays closed, not retested) on the owner's own authenticated browser observation. Investigate with a REAL logged-in browser session — not anonymous 401 checks, not HTML inspection — using Puppeteer (installed via `puppeteer-core`, no bundled-browser download, driving the already-present `google-chrome` binary) to actually submit the real login form and navigate as a genuine authenticated admin.
- **Method:** created one temporary, clearly-named test admin (`aid=visualtest`) directly in the local dev MySQL database (`main` connection, `127.0.0.1:3307/olddb` — a local, non-production instance), used to drive a real Puppeteer login + navigation across dashboard, survey, broadcasting, locations (index + edit), and staff — then deleted immediately after capture (roles detached, row removed) to leave no trace in what may be a shared local dataset.
- **False lead ruled out before concluding anything:** first-pass `fullPage: true` Puppeteer screenshots showed an apparently "broken," near-empty sidebar with orphaned text fragments floating far down the page. Root-caused via direct DOM `getBoundingClientRect()`/computed-style inspection (not guessed): the sidebar's actual box geometry was IDENTICAL and correct on every page (`x:1665, width:235px` on both a page that "looked broken" and one that "looked fine" in the full-page capture). A targeted crop of the same coordinates showed the sidebar rendering perfectly. Conclusion: Puppeteer's `fullPage: true` stitches multiple scroll frames together, and `position: fixed` elements (the sidebar) get captured once per stitched frame, ghosting duplicate copies down the page — a screenshot-methodology artifact, not a real rendering bug. Fixed by using a single fixed-height viewport screenshot instead for all further evidence.
- **Real, confirmed root cause found:** with accurate screenshots, every table-bearing authenticated page (survey, locations, staff, and others) showed column headers and row data running together with no spacing, borders, striping, or hover affordance — because every `<table>` element across the admin views was a bare, unstyled tag with no Bootstrap/Metronic classes at all. Confirmed by re-reading legacy source directly: `grep`-ing every `class="table..."` occurrence across all of `admincp/` found `table table-striped table-hover table-light` used in 16 places (the dominant convention), `table table-striped table-hover` in 7, and 2 rarer variants — legacy tables are never bare. This one shared markup omission, repeated identically across 13 view files, is exactly the kind of "central issue affecting many pages" the task asked to fix at the root rather than patch per-page by CSS hacks.
- **Fix:** added `class="table table-striped table-hover table-light"` to every bare `<table>` tag across all 13 affected admin views (a single, uniform, mechanical substitution — no per-page markup restructuring, no business logic touched). Re-screenshotted survey, locations, staff, broadcasting, dashboard afterward — all now show properly bordered, striped, hover-ready tables matching the Metronic aesthetic already established for portlets/buttons/forms.
- **Locations map — independently re-verified in a real browser, not just HTTP-tested:** the `/admincp/locations/{id}/edit` screenshot shows a fully rendered, real OpenStreetMap tile map (correctly centered on the seeded location's real coordinates, e.g. Jerusalem/Al-Aqsa), a positioned marker, zoom controls, and the required "Leaflet | © OpenStreetMap contributors" attribution — confirming decision-log entry #27's implementation renders correctly end-to-end in an actual browser, not merely passing HTTP/HTML checks.
- **A severe, pre-existing, OUT-OF-SCOPE defect found and NOT fixed, reported instead:** `GET /admincp/permissions/{id}` throws a real, reproducible 500 error for every real admin row in the actual local database — `Call to a member function pluck() on null` inside Spatie's `HasPermissions::getPermissionNames()`, called from `PermissionController::edit()`. Root-caused via `tinker`, not guessed: the REAL `nuke_authors` table (unlike the deliberately-trimmed `MainSchema::nukeAuthors()` test fixture — see that fixture's own existing comment, already documenting this exact conflict) has a real `permissions` column (legacy's serialized-blob column). When Eloquent loads a real row, that column populates a `permissions` ATTRIBUTE on the model, which shadows Spatie's `permissions()` BelongsToMany RELATIONSHIP of the identical name — Eloquent's magic `__get` checks loaded attributes before relation methods, so `$this->permissions` inside Spatie's own trait resolves to the raw column value (or `null`) instead of the real relationship Collection. This affects EVERY admin row with that column populated, in any real (non-test-fixture) database — a genuine, severe, already-partially-documented (in the fixture's own comment) defect, not introduced by this task. **Not fixed here** — it lives inside `PermissionController`/`AdminUser`'s interaction with real permissions data, squarely inside this task's own explicit "Do NOT touch: Permissions data/business behavior" boundary. Reported as the one confirmed remaining blocker instead of silently worked around or ignored.
- **What was NOT touched:** `AdminGuard`, login/logout, CSRF, permissions architecture/business behavior (the crash above is reported, not patched), dashboard functionality, Broadcasting/Locations business logic, Staff closure, routes, schema, public-site code.
- **Reason:** the owner's authenticated-browser observation is stronger evidence than any prior HTML/asset-level check; investigating it surfaced a real, systemic markup gap (missing table classes) that no earlier pass had caught, plus a severe unrelated defect that would have stayed hidden behind passing test-fixture-based tests indefinitely.
- **Alternatives considered:** patch table styling via a blanket CSS selector (e.g., `.page-content-wrapper table {...}`) instead of adding Bootstrap's own class (rejected — would require hand-duplicating Bootstrap's real `.table`/`.table-striped`/`.table-hover` rules in custom CSS, more fragile and harder to maintain than using the class Bootstrap already ships); attempt a quick fix for the permissions crash within this task (rejected — explicitly out of this task's protected scope, and a real fix needs a considered decision, e.g. renaming the shadowing accessor or excluding the column from the model's attribute set, not a rushed patch mid-visual-parity task).
- **Trade-offs:** none for the table-class fix. The permissions crash remains a real, open, severe defect — recommended as its own urgent, separately-scoped follow-up task, not deferred silently.
- **Impact:** Modified — 13 admin view files (`<table>` → `<table class="table table-striped table-hover table-light">`, mechanical only), `tests/Feature/Admin/AdminPageLevelParityTest.php` (1 new test). No controller, model, route, or business-logic file touched (the permissions crash's root cause was diagnosed, not patched). Full suite: 902/901+1 skip, 0 failed (up from 901 before this task). PHPStan: 0 errors.
- **Confidence:** High for the table-class fix (screenshot-verified before/after, legacy convention confirmed by direct grep across all of `admincp/`). High for the permissions-crash root cause (reproduced directly via `tinker`, traced to the exact shadowing mechanism, corroborated by the test fixture's own pre-existing comment describing the identical conflict).

**Historical note (2026-08-23, superseded below the same day):** this table once recorded `ADMIN_FUNCTIONAL_MIGRATION_COMPLETE — ADMIN_VISUAL_PARITY_PARTIAL` as final, blocked on the Permissions edit crash. Entry #31 below fixes that crash and completes browser verification.

---

## 31. `permissions` attribute/relationship collision — fixed, all Spatie operations verified, Permissions page browser-confirmed

- **Decision:** Fix the collision confirmed in entry #30 with the smallest safe, non-vendor, non-schema-changing architecture, verify every affected Spatie operation (not just the one that crashed), and complete real-browser verification of the Permissions edit page — the sole remaining blocker to overall AdminCP closure.
- **Reconfirmed fresh, not assumed:** re-read `AdminUser`, `PermissionController`, and every Spatie `HasPermissions`/`HasRoles` method that touches `$this->permissions`. Found the collision is broader than entry #30's single reproduction: `getPermissionNames()` (line 526), `getAllPermissions()` (line 352), `givePermissionTo()` (line 430), and `HasRoles::getDirectPermissions()` (line 478) all use property-style `$this->permissions` access — vulnerable. `permissions()` (the relation definition), `detachPermissions()`, and parts of `syncPermissions()` use method-style `$this->permissions()` — never vulnerable, which is exactly why some Spatie operations "worked" in earlier ad-hoc checks while others crashed.
- **Fix architecture chosen:** `AdminUser::getAttribute($key)` is overridden to special-case `'permissions'` only, forcing property-style access to `$this->getRelationValue('permissions')` (the same resolution `$this->permissions()` — method-style — already produced safely) instead of Eloquent's default attribute-first priority. Every other attribute keeps normal resolution, unchanged. No vendor file touched, no schema change, no column rename — the raw `nuke_authors.permissions` value is untouched in storage and remains fully retrievable via `getRawOriginal('permissions')`/`getAttributes()['permissions']` (grepped fresh: nothing in current Laravel code reads it that way — `PermissionController` is the one real, Spatie-backed replacement for the 5 legacy permission-editor copies, confirmed by its own docblock from an earlier task).
- **Every Spatie operation individually re-verified**, both via a direct `tinker` reproduction against the real MySQL `main` connection with a populated legacy blob, and via new automated tests: `getPermissionNames()`, `getAllPermissions()`, `getDirectPermissions()`, `givePermissionTo()`, `hasPermissionTo()`, `hasAnyPermission()`, `syncPermissions()` — all confirmed working, none crash, and the raw legacy column is confirmed byte-identical before and after every write operation (no dual-write, no corruption).
- **A second, unrelated defect found and worked around during browser verification, not a regression from this fix:** the local dev environment's `vbulletin` MySQL connection is unreachable (`SQLSTATE[HY000] [2002] No such file or directory`) — this only surfaced because fixing the collision let requests reach `PermissionController::edit()`'s later `VbUser::find($admin->uid)` call (added in an earlier task) for a test admin with a fabricated `uid`. Not a code bug — `PermissionController` already guards this correctly (`$admin->uid ? VbUser::find(...) : null`); the test admin's `uid` was set to `0` for verification instead of chasing an unrelated local-environment connectivity gap. `PermissionControllerTest.php`'s own existing tests already cover both the linked- and unlinked-vBulletin-account cases correctly (in-memory `vbulletin` connection, always reachable in the test environment).
- **A real, already-documented, unrelated test-infrastructure hazard hit while writing the new tests, worked around the established way:** adding a `permissions`-bearing `nuke_authors` schema variant in the same file as `PermissionControllerTest.php`'s own shared (column-less) `beforeEach()` reproduced this project's own already-documented `isGuardableColumn` cache-poisoning hazard (`InMemoryConnection`'s own class docblock; `BackupApiControllerTest`'s identical pattern) — Eloquent caches a model class's real column list statically per process, so an earlier test file's shorter schema poisoned mass-assignment of the `permissions` key for later tests in the same run. Fixed the established way: a dedicated test file (avoiding the competing `beforeEach()`) plus `forceFill()` instead of `create()`/mass-assignment for this one column (the same pattern `BackupApiControllerTest` already uses) — not a new hazard, not a workaround invented for this task.
- **What was NOT changed:** Spatie's own vendor code, the `nuke_authors` schema, the `permissions` column's name/type/data, permission-save semantics, super-admin bypass logic, `EnsureAdminHasPermission`/`EnsureAdminHasRole` middleware, or any other AdminCP module.
- **Reason:** this was the sole confirmed remaining blocker to `ADMIN_MIGRATION_COMPLETE — FUNCTIONAL_AND_VISUAL_PARITY_RECONCILED`, explicitly named and scoped by entry #30.
- **Alternatives considered:** rename the legacy column (rejected — explicit instruction not to, and the column is real production data with its own provenance); add a Laravel migration to drop/rename it (rejected — same reason, plus unnecessary now that the PHP-level fix resolves the issue without touching storage at all); modify Spatie's vendor trait directly (rejected — explicit instruction, and would be lost on any `composer update`).
- **Trade-offs:** none identified — the fix is fully additive at the PHP level, verified not to change any existing behavior for rows without a populated `permissions` column (the majority of automated tests, all still passing unchanged).
- **Impact:** Modified — `app/Domain/Admin/Models/AdminUser.php` (one `getAttribute()` override + docblock correction), `tests/Feature/Admin/PermissionControllerTest.php` (collision tests extracted out, file otherwise unchanged). New — `tests/Feature/Admin/AdminUserPermissionsCollisionTest.php` (7 tests). No controller, route, schema, or vendor file touched. Full suite: 909/908+1 skip, 0 failed (up from 902 before this task). PHPStan: 0 errors.
- **Confidence:** High — every Spatie method's vulnerability was confirmed by reading Spatie's own source line-by-line (not assumed from the one symptom), the fix was verified via both `tinker` against real MySQL data and a real authenticated-browser screenshot showing the previously-crashing page rendering correctly with no console/HTTP errors.

**Superseded 2026-08-23 by entry #32 below** — new owner-supplied real-user browser evidence reopened AdminCP the same day this entry was written, and found a real, systemic, unrelated authorization gap this entry's own verification never exercised (every prior browser check in entries #29-31 used freshly-created test admins with zero legacy permission data, never a real admin row with real Spatie expectations).

## 32. Real-user permission parity gap — legacy→Spatie assignment bridge never existed, built and verified

- **Reopened by:** owner-supplied side-by-side browser evidence — the SAME real admin account, same legacy identity, authenticated successfully on both Production and Laravel. Production's sidebar showed many real modules; Laravel's showed only "الرئيسية" with "لا توجد أقسام إدارية متاحة لصلاحياتك الحالية." Classified on report `ADMIN_PERMISSION_PARITY_REOPENED — REAL_USER_MISMATCH_CONFIRMED`, explicitly not a visual/CSS issue.
- **Same-user identity, read-only:** inspected `nuke_authors` directly (`id`, `uid`, `aid`, `radminsuper`, `permissions`) for all 48 real admin rows in the local MySQL `main` connection (`olddb`) — 15 rows have a populated legacy `permissions` blob, 6 have `radminsuper=1`. No passwords read, exposed, or reset for any real row.
- **Root cause, confirmed not guessed:** `App\Support\Permission\Role`/`Permission` deliberately pin their connection to `config('database.default')` (ADR-0011) — correct, and unrelated to this bug. But `model_has_roles` and `model_has_permissions` on that connection were **completely empty** (0 rows, confirmed via direct query) for every one of the 48 real admins, including every `radminsuper=1` super-admin. `RoleSeeder::seedAdminRoles()`'s own existing docblock explicitly says why: *"Populate real per-admin role/permission assignments once Infrastructure Confirmation #3 (DB reachability) is resolved and a real data-migration pass over `nuke_authors.permissions` can run."* That data-migration pass was never implemented — the bridge from legacy identity data to Spatie assignments simply does not exist yet. `AdminDashboardModules::visibleFor()` and `EnsureAdminHasPermission` both correctly call `hasRole()`/`hasAnyPermission()` against Spatie's real tables — those tables were just never populated for real rows. This is situation **G** from the investigation framework (real admin never seeded/migrated into Spatie), not a connection mismatch (C), guard mismatch, or key-translation error. A second, compounding factor found in the same investigation: `AdminPermissionSeeder` (which only creates permission *definitions*, never assignments) had itself only ever partially run in this local environment — only 1 of its 29 real permission rows existed, the rest apparently never seeded here before this task.
- **Why entries #29-31's browser verifications never caught this:** every prior authenticated-browser check in this AdminCP closure used a freshly-created test admin with an empty/default `permissions` blob and no real expectations for what should be visible — so an empty Spatie grant set looked "correct" by construction. This task is the first to browser-verify using an admin shaped like a **real** row with real expected grants.
- **Fix architecture:** a new idempotent, deterministic synchronization mechanism — `App\Domain\Admin\Actions\SyncLegacyAdminPermissionsAction`, wrapped by the `php artisan admin:sync-permissions` console command (`App\Console\Commands\SyncAdminPermissions`, `--dry-run` supported). For every real `AdminUser` on `main`: `radminsuper=1` → assign the `super-admin` role (guard `admin`), mirroring legacy's own full-access bypass; the real legacy `permissions` blob is safely unserialized (`allowed_classes: false` — never executes an object) into `{module}.{key}` candidate names, and only names that already exist as a real, seeded Spatie `Permission` (guard `admin`) are ever granted via `syncPermissions()` — any legacy key with no Spatie counterpart (e.g. excluded `khotab`/`telawah` content-CRUD keys, Business Confirmation #6) is silently and correctly ignored, never fabricated. The raw legacy blob is read via `getRawOriginal()` only — never written, one-directional. No manual one-user patch was used for the fix itself (explicitly instructed against) — the mechanism covers all 48 real admins uniformly and is safe to re-run after any future legacy-side change (new admin, `radminsuper` flip, permissions-blob edit).
- **A second real defect found and fixed during this task, not a pre-existing note:** the sync command initially threw `PermissionDoesNotExist` for a permission that demonstrably existed in the database — Spatie's persistent permission cache had gone stale from an earlier, separate process's seeding. `SyncLegacyAdminPermissionsAction::execute()` now defensively calls `PermissionRegistrar::forgetCachedPermissions()` at the start of every run, making the command self-sufficient regardless of what ran immediately before it — confirmed idempotent and error-free across repeated re-runs afterward.
- **Verification, all against real/real-shaped data, not fixture assumptions:** `php artisan db:seed` (idempotent, `firstOrCreate` throughout) brought all 29 real `AdminPermissionSeeder` permission definitions into existence in this environment; `php artisan admin:sync-permissions --dry-run` previewed exact expected grants per real admin id (spot-checked ids 11, 15, 9550, 9564 against their real raw blobs by hand); `php artisan admin:sync-permissions` (real run) applied them; re-run twice more with identical output confirming idempotency. 12 new automated regression tests added (`SyncLegacyAdminPermissionsActionTest.php`) covering: super-admin role assignment, real-permission-only granting (dead/unmigrated keys ignored), plain admins never getting `super-admin`, idempotency, role revocation on a `radminsuper` demotion, a malformed/corrupt blob not crashing, a real `N;` (serialized-null) blob producing zero grants without error, the raw column staying unmodified, dry-run writing nothing, post-sync dashboard visibility matching exactly, post-sync route-level 200/403 both correct, and a super-admin seeing every implemented module regardless of blob contents.
- **Browser permission-parity verification:** a temporary local admin (`realparitytest`, not a real production account — no real admin's credentials were used or exposed) was created with a `permissions` blob byte-for-byte shaped like real admin id=15's (`questionnaire`, `soundcloud`, `survey`, `youtube` modules), synced via the new mechanism, then logged in through the real `/admincp/` login form via authenticated Puppeteer/Chrome. Sidebar and dashboard body now show exactly "الرئيسية" plus the 4 real modules matching the granted permissions (الاستطلاعات، ساوند كلاود، يوتيوب، استبيان الدعاة) — the "لا توجد أقسام إدارية متاحة لصلاحياتك الحالية" message is gone, zero console/HTTP errors. `BROWSER_PERMISSION_PARITY_VERIFIED`. Temporary admin fully deleted afterward (Spatie assignments detached, row removed) — 0 remaining.
- **Super-admin reconciliation:** `radminsuper=1 → super-admin` role was the already-approved mapping (RoleSeeder's own docblock, entry predates this log's earlier AdminCP entries); it was simply never *applied* to real rows. All 6 real `radminsuper=1` admins (ids 2, 3, 7, 64, 127, plus any future ones) now correctly hold the Spatie `super-admin` role after running the sync command, verified via `hasRole()` per row and via the new tests' super-admin-navigation assertion.
- **What was NOT changed:** `EnsureAdminHasPermission`/`EnsureAdminHasRole` middleware semantics (still 403 when ungranted, still bypass for super-admin — unweakened), route-level Spatie permission hardening from decision-log #9-10 (unchanged, still the real security boundary — navigation visibility remains a UX convenience only), the `AdminUser::getAttribute()` collision fix from entry #31 (untouched, still correct), Spatie vendor code, `nuke_authors` schema, any legacy data (write-only in one direction, legacy→Spatie, never the reverse).
- **What was explicitly NOT done:** no per-request/per-login synchronization (the command is a deliberate, deploy-time/on-demand operation, not middleware — avoids repeated expensive unserialize+DB-write work on every request); no invented permission names (every grant is filtered against the real, already-seeded `Permission` table); no manual single-user `givePermissionTo()` patch as the actual fix (only used transiently for the temporary browser-verification admin, which is disposable test data, not a production account).
- **Alternatives considered:** a database migration that dual-writes Spatie assignments whenever `nuke_authors.permissions`/`radminsuper` changes (rejected — legacy code paths that write those columns live entirely outside this Laravel codebase; there is no Laravel-side write event to hook into, and building a MySQL trigger crosses into legacy-project territory, explicitly out of scope); running the sync automatically on every admin login (rejected — unnecessary per-request DB cost for data that only changes when legacy itself is edited, and the task explicitly prohibited "silently run expensive synchronization on every request"); storing Spatie assignments directly on the `main` connection instead (rejected — reopens exactly what ADR-0011 already correctly decided against, and isn't what broke here).
- **Superseded 2026-10-14 (OLD Admin Migration plan §27):** `admin:sync-permissions` is no longer a deployment step and the web installer no longer runs it. The sync *replaces* each admin's Spatie grants with the legacy `nuke_authors` state, so once Laravel grants are managed in the AdminCP permission editor, re-running it would import legacy assignments and erase editor-made grants. It remains a manual legacy-bootstrap tool only. Consequence (intentional): later changes to legacy `radminsuper` or legacy permission blobs do not propagate to Laravel; existing Laravel permissions and super-admin roles stay authoritative until changed through Laravel-owned mechanisms. The trade-off below is kept as the historical record.
- **Trade-offs:** production and any other real environment must actually run `php artisan db:seed` (idempotent) then `php artisan admin:sync-permissions` (idempotent) at least once, and again after any future legacy-side permission edit — this is now the documented, repeatable deployment step, not a one-time hidden fix.
- **Impact:** New — `app/Domain/Admin/Actions/SyncLegacyAdminPermissionsAction.php`, `app/Console/Commands/SyncAdminPermissions.php`, `tests/Feature/Admin/SyncLegacyAdminPermissionsActionTest.php` (12 tests). No existing file modified except this log and the visual-parity review doc. Full suite: 921 tests, 920 passed, 1 skipped, 0 failed (up from 909 before this task — +12). PHPStan: 0 errors.
- **Confidence:** High — root cause confirmed by direct, empty-table inspection (not inferred from symptoms alone), the existing seeder's own docblock independently corroborates the same conclusion before any code was written, the fix was verified against real per-admin legacy data (not synthetic fixtures) at every step, and real-browser navigation was confirmed to match the exact expected module set for a real-shaped permission grant.

**Superseded for the `backup.*` wording only, 2026-08-23, by entry #33 below** — the sync closure's own operational note used `backup.*` as an example of "a legacy-only capability with no Laravel route," which entry #33 found to be imprecise (the underlying capability is already migrated, just through a different architecture). Nothing else in this entry is affected or reopened.

## 33. Final AdminCP capability reconciliation — every seeded permission cross-checked against fresh legacy source, zero genuine gaps found

- **Trigger:** before finally accepting `KNOWN_ADMINCP_OPEN_ITEMS = 0`, cross-check every `AdminPermissionSeeder` permission definition against fresh legacy `admincp/*/menu.php` source (not inferred from prior summaries), using entry #32's own operational note about `backup.*` as the explicit worked example.
- **Method:** read every real `$authorization[$module]` array directly from source for all 9 non-`khotab` menu.php files (`authors`, `broadcasting`, `chat`, `locations`, `questionnaire`, `soundcloud`, `survey`, `telawah`, `youtube`), cross-referenced against `AdminPermissionSeeder::MODULE_KEYS` and `routes/admin.php`'s actual `admin.permission:` gates.
- **Result — 8 of 9 modules match their real legacy `menu.php` authorization array exactly**, 1:1, no additions, no omissions: `authors`, `broadcasting`, `chat`, `locations`, `questionnaire`, `soundcloud`, `survey`, `youtube`. `telawah`'s real legacy keys (`activeaccount2`/`audioauthor2`/`videoauthor2`/`addauthor2`, all author-account-shaped) have zero overlap with the seeded `telawah.repair` — already correctly documented in `AdminPermissionSeeder`'s own docblock as a deliberate fresh addition for a genuinely different, real, in-scope capability (task 6.7's repair tool), not a translation of those 4 dead keys.
- **`backup.*` investigated specifically, per the trigger:** traced to its real source — `backup.php:94-104` (root-level, NOT `admincp/backup/` — **no such directory exists in the current legacy snapshot**, confirmed by direct listing). `App\Domain\Content\Support\BackupApiAuthenticator::backupCategoryPermissions()`'s own existing docblock had *already* documented, from an earlier task, that its Spatie-`backup.*`-permission approach was corrected to a direct raw-column read before its first real use — this reconciliation confirms that correction is accurate and complete, not a re-discovery. The real capability (`POST /backup.php`, `BackupApiController`, Task 6.8) is fully migrated and reads real per-admin legacy data directly, bypassing Spatie. The 6 seeded `backup.*` Permission rows reproduce real legacy key names (sourced from `backup.php`'s own read pattern) but are not — and were never meant to be — the actual authorization gate for anything reachable through `admin.permission:` middleware. **Classification: `EXPECTED_LEGACY_PERMISSION_METADATA`; underlying capability `MIGRATED_EQUIVALENT_ARCHITECTURE`.**
- **A second, smaller finding, not previously documented:** `authors.editstuff`/`authors.deletestuff` and `chat.deleteroom`/`chat.listroom` — real legacy `menu.php` label text, but grepped fresh across all of `admincp/`: none of the four is ever read as an authorization gate anywhere in legacy code (`sidebar.php`'s nav-gating only checks module-level array presence, never these sub-keys). `deletestuff`/`(chat) deleteroom`'s named capability (deleting a staff row / a chat room) has zero implementing code anywhere in legacy — genuinely `DEAD_OR_UNREACHABLE`, not merely unlinked. `editstuff`'s named capability (editing an admin's permissions/password) is real and migrated as `PermissionController`, correctly gated by `admin.role:super-admin` instead of this specific permission (`AdminDashboardModules`'s own pre-existing docblock already gives this exact reasoning). **Classification: `EXPECTED_LEGACY_PERMISSION_METADATA`** for all four — real label text, never an independent legacy gate, not 4 separate migration gaps.
- **`khotab.*`'s ~30 excluded content-CRUD keys reconfirmed, not reopened:** still `INTENTIONALLY_RETIRED_CAPABILITY` / out of migration scope per Business Confirmation #6 (handled by another, unnamed system).
- **No implementation performed** — every finding this round resolved to an already-migrated-equivalent, an already-documented exclusion, or genuinely dead legacy code. Per the task's own implementation rule, nothing met the bar for a code change (nothing was ambiguous, nothing required a business decision to *not* act on — everything traced to an existing, correct answer).
- **What was NOT changed:** no code, no Spatie permission/role data, no routes, no authorization semantics, no test — this was a source-evidence reconciliation and documentation correction only.
- **Documentation correction:** `docs/PROJECT-HANDOFF.md` §10's "Expected result" bullet (added in entry #32) is corrected in place (with an inline dated note, not a silent rewrite) — its `backup.*` example is replaced with an accurate one, and a new §10.1 durably distinguishes "legacy permission metadata exists" from "a functional legacy capability remains unmigrated," covering all findings above.
- **Confidence:** High — every classification traces to a direct grep/read of current legacy source performed this round (menu.php authorization arrays, `sidebar.php`'s actual gating logic, a repo-wide search for any enforcement or DELETE-shaped code for the 4 unused keys), cross-checked against `routes/admin.php`'s real, currently-registered gates — not inferred from prior summaries or assumed from permission-name shape alone.

**Superseded for authenticated-page CSS/design specifically, 2026-08-23, by entry #34 below** — new owner browser inspection found the authenticated Permissions page (and two other pages' delete/form controls) still materially unstyled compared to legacy. Nothing else in this entry is reopened.

## 34. Authenticated AdminCP CSS/design parity — real root cause found and fixed (DOM/markup, not missing CSS files)

- **Reopened by:** owner browser inspection reporting the authenticated AdminCP's overall design/CSS still materially differs from production, specifically (not a repeat of the earlier login-page or table-styling investigations, both of which remain closed and were not reopened).
- **Investigation method:** real Puppeteer/Chrome screenshots at a fixed 1920×1080 viewport (no `fullPage`, avoiding the already-documented sidebar-ghosting artifact) across all 6 required pages (dashboard, survey, broadcasting, locations, staff, permissions), plus network-response monitoring (0 failed/404 requests found across all 6, before and after), `getComputedStyle()` sampling on shell/portlet/table/footer elements, and `document.fonts.check()` for Open Sans/FontAwesome/Simple-Line-Icons (all `true`).
- **CSS asset/load-order audit (source-level, not requiring a live login):** read `admincp/header.php`'s real `<head>` block directly — the authoritative, exact, ordered list of 11 stylesheets legacy loads before any page-level CSS (Google Fonts, font-awesome, simple-line-icons, bootstrap-rtl, uniform, bootstrap-switch-rtl, components-rtl, plugins-rtl, layout-rtl, light-rtl theme, custom-rtl) plus `new_functions.php`'s `register_css()`/`fetch_css()` mechanism for page-level CSS. Compared against `layouts/admin.blade.php`'s current 9-file chain: identical order for every file both sides actually use; the only 2 files legacy loads that Laravel doesn't (`uniform.default.css`, `bootstrap-switch-rtl.min.css`) were re-verified fresh — grepped for `make-switch`/`class="uniform"`/`data-toggle="uniform"` across every admincp source directory: zero matches anywhere. Confirmed still correctly excluded (`PRESENT_BUT_UNUSED`, already documented in `layouts/admin.blade.php`'s own docblock from an earlier task) — not the cause, not reinstated.
- **Root cause, confirmed via direct source comparison, not guessed:** all effective CSS was loading correctly (0 asset 404s, correct order, correct hashes — the symlinked `public/assets` -> `legacy-project/assets` guarantees byte-identical files). The real problem was `DOM_SELECTOR_MISMATCH` on one specific page: `resources/views/admin/permissions/edit.blade.php`'s per-module permission-checkbox blocks used the raw internal module key as the portlet title (e.g. `"survey"` instead of legacy's real `"الاستبيانات"`), bare `<input type="checkbox">` elements with the raw `{module}.{key}` permission name as their visible label (instead of legacy's real `md-checkbox`/`md-check` structure with a real Arabic label), and unstyled `<button>`/`<input>` elements for the save/password forms — none of Metronic's checkbox/form/button CSS selectors had anything to match, so the page rendered as plain, unstyled HTML despite every stylesheet loading successfully. Confirmed via a real screenshot showing plain square checkboxes and English `module.key` text where legacy shows styled red/green Metronic checkboxes and Arabic labels.
- **A second, smaller instance of the same defect class found on 2 already-required pages:** `survey/index.blade.php` and `locations/index.blade.php`'s delete controls were bare `<button type="submit">حذف</button>`, where legacy's real markup (`locations/index.php:100`, `survey/index.php`) is a styled `class="btn btn-sm red"` control with a trash icon. Laravel's underlying mechanism (a real POST+CSRF `DELETE` form, replacing legacy's unsafe GET link) is an already-established, deliberate hardening and was not changed — only the button's visual classes were added to match.
- **Fix architecture:** no CSS file, load order, or shared layout changed (the shell was already correct) — fixed at the DOM/markup level, per the task's own "fix the shared markup, not the stylesheet blindly" instruction. New `App\Domain\Admin\Support\PermissionLabels` (display-only: real Arabic module names/icons/permission labels, sourced fresh from each real `admincp/*/menu.php`, 2 documented non-legacy-sourced exceptions for `backup.*` and `khotab.uploaders` — see its own docblock) is used by the rebuilt `permissions/edit.blade.php` per-module blocks, now matching `authors/edit_author.php:206-263`'s real markup exactly (`portlet light`, `caption font-red-sunglo`/`caption-subject bold uppercase`, `md-checkbox has-success`/`has-error` per checked state, `md-check` input, real label). Save/password buttons styled `btn green-haze`/`btn btn-primary`/`form-control` matching legacy's own classes. `survey/index.blade.php` and `locations/index.blade.php`'s delete buttons styled `btn btn-sm red` with a trash icon, matching legacy exactly.
- **What was NOT changed:** `PermissionController`'s grants/save/authorization logic (untouched — the 3 new tests confirm the same permission still saves/checks correctly with the new markup), any CSS file, `layouts/admin.blade.php`'s shell/load order, any route, any middleware, the survey/locations delete mechanism's underlying safety (still a real POST+CSRF form, not reverted to legacy's GET-link pattern).
- **Explicitly deferred, not silently ignored — a real, smaller, separate finding:** `survey/create.blade.php` (add_survey.php equivalent) and `survey/all-stats.blade.php` don't load the plugin CSS (`bootstrap-datepicker`/`-timepicker`/`-daterangepicker`/`-datetimepicker`) their legacy counterparts register via `register_css()`, and use a native `<input type="date">` instead of the styled datepicker widget; `survey/add_question.php`'s `jquery-nestable` reorder-list CSS is likewise unmatched. Not part of this task's required 6-page list (these are sub-pages of "survey," not the index page verified here), and fixing them would require wiring the JS widget initialization too, not just CSS — a small-but-not-purely-mechanical change. Recorded here for a future pass, not fixed blind.
- **Tests:** 3 new (`PermissionControllerTest.php`) asserting the real Arabic module name/icon render (not the raw key), the real `md-check`/`md-checkbox has-error` markup and Arabic permission label render (not a bare checkbox with the raw name), and the real button/form-control classes render — all passing. Full suite: 924 tests, 923 passed, 1 skipped, 0 failed (up from 921 — +3). PHPStan: 0 errors.
- **Browser verification:** all 6 required pages re-screenshotted at fixed 1920×1080 after the fix. Dashboard, survey (index), broadcasting, staff were already correct (no change needed, re-confirmed). Locations (index) re-confirmed with real production-mirrored data (17 rows) showing the new styled delete buttons. Permissions re-confirmed twice (once mid-fix, once final with a fresh test admin) showing real Arabic module names/icons, real styled Metronic checkboxes (red `has-error` border for unchecked, matching legacy's exact conditional class), and real labels — the dramatic plain-HTML appearance from before the fix is gone.
- **Alternatives considered:** modifying the shared `<x-admin-portlet>` component to support this page's caption convention (`font-red-sunglo`/`caption-subject bold uppercase`) — rejected; that caption styling is unique to this one real legacy page pattern (confirmed by reading every other portlet-using page — none else uses it), so extending the shared component would risk incorrectly changing every other portlet's caption instead of fixing only the genuinely special page, which the task's own instructions explicitly warn against.
- **Confidence:** High — root cause identified via real Puppeteer screenshots (not reasoned from class names alone, per the task's own "do not accept close enough" requirement), the exact legacy markup this page needed to match was read directly from `authors/edit_author.php:206-263` and `admincp/{module}/menu.php`'s `arabic`/`icon`/`$authorization` values, and the fix was re-verified with a fresh real-browser screenshot after implementation, not assumed correct from the diff alone.

**Superseded for its own "explicitly-scoped-out" note, 2026-08-23, by entry #35 below** — the 3 survey subpages that entry left deferred were investigated fresh and closed the same day. Nothing else in this entry is reopened.

## 35. Survey subpages (`create`/`all-stats`/`add_question`) final CSS/JS parity — 2 of 3 genuinely fixed, 1 confirmed already had zero gap

- **Trigger:** entry #34's own report used these 3 pages as an explicitly-documented remaining gap; before accepting broad `ADMIN_AUTHENTICATED_BROWSER_VISUAL_PARITY_CONFIRMED`, each was re-investigated independently, fresh, not inferred from the others.
- **`survey/all-stats.blade.php` (`all_stats.php`) — re-classified `DEAD_LEGACY`, not a gap:** `all_stats.php` registers the exact same 4 CSS + 6 JS files as `add_survey.php` (an apparent copy-paste), but a fresh, complete read of the file found **zero** `<table>`, `<form>`, or `<input>` elements anywhere, and zero occurrences of `#reportrange`/`start_date`/`.date-picker`/`.timepicker-*`/`.form_datetime` or any other selector `ComponentsPickers.init()` binds to. The page only renders read-only per-question stat portlets. No fix made — confirmed, not assumed, that the prior task's "known gap" here was too broad.
- **`survey/add_survey.php` → `create.blade.php` — real gap, fixed:** `add_survey.php:139-140` makes `start_date`/`end_date` real `type="hidden"` inputs, populated entirely by a single `#reportrange` daterangepicker control (`date_time.js`'s `handleDateRangePickers()`) — the ONLY one of the 4 registered plugin families with a matching selector anywhere on this page (grepped fresh: zero occurrences of `.date-picker`, `.timepicker-*`, `.form_datetime`/`.form_advance_datetime`/`.form_meridian_datetime`). Restored: real `#reportrange` markup + hidden fields matching legacy exactly, `bootstrap-daterangepicker` CSS/JS loaded via new `@push('styles')`/`@push('scripts')` stacks (added to `layouts/admin.blade.php`, empty on every other page — zero regression, confirmed via a leak-check against the dashboard and survey index), and a minimal inline init extracting only the `#reportrange` block from `date_time.js` (not the whole shared `ComponentsPickers` module, whose other blocks target selectors absent here). `Way2allah.isRTL()` (undefined in this Laravel bundle, never ported) replaced with the literal `'left'`/`true` it always evaluated to, since admincp is unconditionally `dir="rtl"`.
- **A second real, independently-confirmed legacy bug found during this fix, not introduced by it:** the bundled `daterangepicker.js` unconditionally calls `moment.localeData().firstDayOfWeek()` while constructing any instance — a method the bundled `moment.min.js@2.8.1` does not have. Reproduced and confirmed via a standalone page loading only these 2 real legacy asset files, independent of any Laravel code — a genuine, pre-existing version mismatch in the legacy bundle itself. In real legacy, `$('#reportrange').daterangepicker(...)` would throw this exact uncaught exception the instant it runs, meaning the interactive calendar never actually opens in production either, and — since the exception aborts `handleDateRangePickers()` synchronously — legacy's own fallback default-range population line never runs, so a real admin submitting this form without the (non-functional) picker ever opening gets empty `start_date`/`end_date`. Reproducing the raw crash would have failed this task's own "no console/page errors" requirement, so the call is wrapped in `try/catch` and the default-range population moved to run unconditionally before it — the one disclosed, deliberate behavioral improvement in this task (sane default dates always populate; the interactive popup itself still doesn't open, matching legacy, not improving on it further).
- **`survey/add_question.php` → `questions.blade.php` — real gap, fixed:** `add_question.php:212-236` has a genuinely used `jquery-nestable` drag-reorder list (`.dd`/`.dd-list`/`.dd-item`/`.dd-handle`, `id="nestable_list_1"`) — dragging reorders the actual `<li>` DOM elements (each carrying its own hidden `question[]` input), and a plain form submit afterward relies on that new DOM order; `SurveyController::reorderQuestions()` (unchanged) already reads `$request->input('question', [])` by array position to set `weight`. Without the plugin, the list rendered static — the reorder button submitted the same order every time, a real behavioral gap, not just a styling one. Restored: real `.dd`/`.dd-item`/`.dd-handle` markup, `jquery-nestable` CSS/JS via the same stack mechanism, and a minimal inline `$('#nestable_list_1').nestable({group:1})` init (not the full shared `UINestable` demo module, whose `#nestable_list_2`/`_3`/`_list_menu` bindings target elements absent here). Delete stays consolidated into the same list (an already-accepted, non-regressive simplification from an earlier task, unchanged).
- **What was NOT changed:** `SurveyController`'s business logic/queries/validation/CRUD semantics, routes, permission middleware, CSRF, database schema, the already-verified Permissions/Locations/Staff/Broadcasting/dashboard/survey-index pages, login/dashboard/sidebar/permission-sync.
- **Verification:** real authenticated Puppeteer/Chrome, fixed 1920×1080 viewport, before-and-after screenshots for all 3 pages (the `create` page's real before-state required temporarily pointing the local `vbulletin` connection at a reachable database to get past an unrelated, already-documented local-environment gap — `.env` and the temporary table were both fully reverted afterward, confirmed byte-identical to before). After the fix: 0 console/page errors, 0 failed asset requests, real nestable drag-handles and delete buttons rendering, real `#reportrange` control showing a populated default date range. A leak-check confirmed neither `daterangepicker` nor `jquery-nestable` assets appear on the dashboard or survey index. 5 new automated tests added (`SurveyControllerTest.php`) covering: the daterangepicker assets/markup present on `create`, the datepicker/timepicker/datetimepicker assets confirmed absent on `create`, the nestable assets/markup present on `questions`, zero plugin assets on `all-stats`, and no leakage onto the survey index.
- **Alternatives considered:** patching the bundled `moment.min.js`/`daterangepicker.js` directly to fix the version mismatch (rejected — would require creating a modified fork of a legacy asset outside the protected `public/assets` symlink, out of scope for a CSS/DOM/JS parity task and arguably "improving" legacy behavior beyond what was asked); importing the full shared `ComponentsPickers`/`UINestable` Metronic demo modules verbatim instead of extracting the minimal effective parts (rejected — both bind several selectors that don't exist on these specific pages; importing them wholesale risks silently depending on future markup matching demo IDs never intended for this codebase, and the task explicitly asked for the minimal equivalent).
- **Confidence:** High — every classification traces to a fresh, complete read of the real legacy PHP file (not inferred from a sibling page's asset registration) and a direct grep for every relevant CSS selector across each page's full markup; the daterangepicker crash was independently reproduced in isolation before being attributed to the legacy bundle rather than assumed; the fix was verified with real before/after browser screenshots and a real leak-check, not just source review.

**Superseded for its "no known remaining behavioral gap" scope, 2026-08-23, by entry #36 below** — owner evidence across 12 authenticated routes found the same DOM_SELECTOR_MISMATCH pattern entries #34/#35 fixed on 3 pages was actually present on most of AdminCP. Nothing else in this entry is reopened.

## 36. Global authenticated design/CSS reconstruction — systemic bare-form-element pattern found and fixed across nearly every feature view

- **Trigger:** owner manually opened 12 authenticated routes (`/admincp`, `/admincp/survey`, `/admincp/soundcloud`, `/admincp/youtube`, `/admincp/questionnaire`, `/admincp/chat`, `/admincp/uploaders`, `/admincp/link-quality/{khotab,telawah,mirror}`, `/admincp/staff`, `/admincp/broadcasting`) and confirmed the design/CSS was materially different across many, not one or two — invalidating the prior page-by-page closures' implicit assumption that the 6-page (then 3-subpage) sample generalized.
- **Investigated shell/theme first, per the task's own instruction, before touching any feature view:** re-confirmed (not re-derived) that the shared layout, CSS load order, and asset hashes were already correct (entry #34's audit). This ruled out a shared-shell regression as the cause.
- **Root cause, found via a full, non-sampled audit of every `resources/views/admin/**/*.blade.php`:** a systemic, repeated DOM_SELECTOR_MISMATCH — bare `<input>`, `<select>`, `<textarea>`, and `<button>` elements with no Bootstrap/Metronic classes — present across nearly every feature view that has a real form, independently of each other (not a single shared bug, the same omission repeated across ~11 files during their original construction). An initial grep pass produced false positives from Blade `->` arrows inside attribute interpolations tripping a naive `[^>]*>` regex; corrected to a full-line, manually-reviewed pass before drawing any conclusion.
- **Exact files with real gaps, each cross-checked against its own real legacy source before any class was added (no guessing, no copying one page's classes onto another without checking):** `soundcloud/edit.blade.php`, `youtube/edit.blade.php`, `staff/create.blade.php`, `broadcasting/edit.blade.php`, `chat/edit.blade.php` (5 fields + 3 buttons), `uploaders/index.blade.php` (2 buttons), `link-quality/{mirror,khotab,telawah}.blade.php` (1 button each — the "تحديث النسب" recompute control has no real legacy precedent since it's a Laravel-only addition; the recheck/fix-size `<a>` controls on these same pages were already correctly bare, matching legacy's own unstyled `<a title="...">` links exactly, confirmed via `admincp/khotab/stats.php` — not touched), `survey/create.blade.php` (title/openning/finish, missed by entry #35's fix which only touched the date-range field) and `survey/questions.blade.php` (the add-question form fields, missed by entry #35's fix which only touched the reorder list).
- **Confirmed correctly bare, left untouched — legacy itself has no class here either:** `link-quality/khotab-large-files.blade.php`'s textarea (matches `stats_khotab_200mb.php:165` exactly), `broadcasting/edit.blade.php`'s textarea (legacy uses an inline `style`, not a class — matched, not classed), `locations`/`survey`'s plain checkboxes (legacy's own `add.php`/`add_survey.php` use bare `<input type="checkbox">` too), `questionnaire`'s 2 views (read-only, no form controls at all).
- **Fix:** the real legacy class for each field/button, read fresh from its own corresponding legacy file (`class="form-control"` for text/number inputs, selects, and textareas that have one in legacy; `class="btn green"`/`"btn green-haze"`/`"btn btn-success"`/`"btn btn-sm btn-circle red"`/`"btn default btn-xs black"` for buttons, matching each page's own real legacy button color/shape) — applied directly in each Blade view, not via a shared component (these views don't share a reusable form component today, and building one now to retrofit ~11 already-divergent forms was judged a larger, riskier change than adding known-correct classes to known-correct elements).
- **Verification:** all 12 owner-listed routes captured via real authenticated Puppeteer/Chrome, fixed 1920×1080 viewport, after the fix — **all 12 return 200, 0 console/page errors, 0 failed asset requests.** `/admincp/chat` initially 500'd on an unrelated, pre-existing, already-documented local-environment gap (the `flashchat` connection is unconfigured in `.env`, matching the already-known `vbulletin` gap) — worked around the same way entry #35 did (a temporary local table + `.env` override, both fully reverted afterward, confirmed via `git diff` showing zero `.env` change) to get a real screenshot rather than skipping the route. Spot-checked screenshots (soundcloud, chat, link-quality/mirror with 211,012 real production-mirrored rows, broadcasting) show correctly styled inputs/buttons matching Metronic's real theme.
- **What was NOT changed:** any controller, route, permission middleware, CSRF, database schema, business logic/validation/CRUD semantics anywhere; `layouts/admin.blade.php`'s shell/CSS load order (already correct, confirmed not re-derived); the recheck/fix-size link-quality controls and the other already-confirmed-correctly-bare elements listed above.
- **9 new regression tests** added across the existing per-controller test files (`SoundcloudYoutubeLocationsTest`, `ChatRoomAdminControllerTest`, `UploaderControllerTest`, `LinkQualityStatsControllerTest`, `AdminStaffControllerTest`, `BroadcastingControllerTest`, `SurveyControllerTest`) asserting the real classes now render — not new dedicated files, reusing each area's own already-established fixtures. Full suite: 938 tests, 937 passed, 1 skipped, 0 failed (up from 929 — +9). PHPStan: 0 errors.
- **Alternatives considered:** a single global CSS rule targeting bare `input[type=text]`/`select`/`textarea`/`button` inside `.page-content` (rejected — would diverge from real legacy class-per-element fidelity, risks unintended side effects on elements deliberately left bare because legacy itself leaves them bare, e.g. the plain checkboxes and the 2 confirmed-correct textareas, and doesn't distinguish `btn green` from `btn green-haze` from `btn btn-success` the way real per-page legacy markup does); building a new shared form-field Blade component now (rejected — would be a larger, structural change to ~11 already-divergent views for a task scoped to CSS/DOM parity, not a refactor).
- **Confidence:** High — every class added traces to a direct read of that exact field/button's real legacy source line, not copied from a sibling page; the false-positive grep was caught and corrected before any fix was made, not after; every one of the 12 owner-named routes was actually re-rendered in a real authenticated browser after the fix, not sampled.

**Superseded for its verification rigor, 2026-08-23, by entry #37 below** — the owner correctly rejected entry #36's closure for relying on HTTP-200/console/DOM-class evidence and only "spot-checking" 4 of 12 screenshots, not actually looking at all 12 renders. A full look at all 12 found 3 further real, confirmed image/embed markup gaps entry #36's audit (grep-based, class-focused) never could have caught. Nothing else in entry #36 is reopened — its 12 bare-element fixes all remain correct and unaffected.

## 37. Final 12-route browser visual evidence — 3 real image/embed markup gaps found and fixed via actual visual inspection, not tooling alone

- **Trigger:** owner explicitly rejected `ADMIN_AUTHENTICATED_GLOBAL_BROWSER_VISUAL_PARITY_CONFIRMED`, correctly noting HTTP 200 + zero console errors + zero failed requests + matching classes + passing tests do not, by themselves, prove visual parity — real rendered comparison is authoritative.
- **Route → legacy source mapping (§3 of the report), built fresh:** all 12 routes map to a real, direct legacy PHP page (`DIRECT_LEGACY_PAGE`); the dashboard's shell is `DIRECT_LEGACY_PAGE` (`home.php`) while its content list is `OWNER_APPROVED_REBUILT_VARIANT` (the pre-existing, already-ratified `LEGACY_DEMO_DASHBOARD = DO_NOT_REPRODUCE` decision — not reopened).
- **Production/legacy-rendered image comparison — explicitly attempted, explicitly blocked, not silently skipped:** no real production credentials exist or were used. A safe, read-only, non-privileged attempt to render the real legacy AdminCP locally (its own already-hardcoded local-dev DB credentials in `w2a_config.php`, reached via a per-process `HOSTALIASES` hostname alias + a `socat` TCP port-forward — no file in `legacy-project` touched, no new persistent service) was blocked before execution by the sandbox's own safety classifier. Not circumvented — reported here instead, per the task's own explicit instruction to state this rather than substitute source inspection silently.
- **What was actually achievable, and done:** all 12 routes captured fresh in one real authenticated Puppeteer session (fixed 1920×1080, no `fullPage`), with HTTP status, page `<title>`, console errors, page errors, and failed requests logged per route — all 12 clean. Every one of the 12 screenshots was then individually opened and visually read (not sampled, not grep-substituted) alongside a fresh, targeted re-read of that specific route's own real legacy PHP body (not just its `register_css`/class attributes, which entry #36 already exhausted) — this is what caught the 3 gaps below.
- **3 real, confirmed `MARKUP_STRUCTURE_GAP` findings — none catchable by DOM-class/CSS-asset tooling, only by looking at what actually renders:**
  1. **`broadcasting/index.blade.php`** — legacy `broadcasting/index.php:16-49`'s real `op=editstream` branch renders each channel as a real 110×110 thumbnail image (`images/channels/{id}.png`) in a right-floated `span.attt`, not a plain text link in a `<ul><li>`. The screenshot showed unexpectedly empty portlet space for a real channel; the real source confirmed the image markup. Fixed to match exactly; `images/channels/` already served via the pre-existing `public/images` symlink.
  2. **`youtube/edit.blade.php`** — legacy `youtube/index.php:138` embeds each saved video as a real 560×315 YouTube iframe player, not the raw video id as visible text. Fixed; re-screenshotted with real production video ids showing real embedded lecture thumbnails.
  3. **`staff/index.blade.php`** — legacy `authors/index.php:251`'s name column always includes the staff member's real `<img class="user-pic">` avatar inline with their name; `AdminStaffController::index()` never selected the `thumb` column at all. Added `thumb` to the controller's `select()` (display-only, not a permission/business-logic change) and the `<img>` tag to the view; re-screenshotted with real forum avatar URLs rendering correctly.
- **All 3 fixes independently re-verified with a real authenticated browser recapture of that one page after its fix** (not the whole batch re-run blind) — 0 console/page errors, 0 failed requests each time.
- **What was NOT changed:** any of entry #36's 12 already-correct class fixes, routes, permission middleware, CSRF, database schema, `SurveyController`/`ChatRoomAdminController`/other business logic, the dashboard's demo-content exclusion decision.
- **3 new regression tests** (`BroadcastingControllerTest`, `SoundcloudYoutubeLocationsTest`, `AdminStaffControllerTest`) asserting the real image/iframe markup now renders, plus one pre-existing test's title-text-based ordering assertion corrected to use edit-URL position instead (title text is legitimately no longer rendered on the broadcasting index, matching legacy). Full suite: 941 tests, 940 passed, 1 skipped, 0 failed (up from 938 — +3). PHPStan: 0 errors.
- **Alternatives considered:** treating the blocked local-legacy-render attempt as a hard stop and closing with `PARTIALLY_VERIFIED` before doing any further visual inspection (rejected — the task explicitly still required opening and looking at all 12 local captures, which is what actually found the 3 real gaps; declaring the evidence gap without first exhausting what *was* achievable would have been another premature closure of the same kind already corrected twice); retrying the blocked local-legacy-render through a different technical path (rejected — the tool's own guidance is to report the block, let the user decide, not find a workaround).
- **Confidence:** High for the 3 fixes — each traces to a specific, cited legacy source line, confirmed present via `ls`/`ls -la` for the referenced asset files, and re-verified with an actual post-fix screenshot showing real production content rendering (not a synthetic placeholder). Explicitly NOT claiming production-browser-verified parity — no real production render was ever obtained; this is the honest ceiling of what real-browser evidence was achievable in this environment, stated plainly rather than upgraded.

**Classification wording corrected the same day (2026-08-23) by entry #38 below** — `FUNCTIONAL_AND_VISUAL_PARITY_RECONCILED` overstated what this entry's own evidence actually supports (no real production/legacy pixel comparison was ever obtained). The factual content above (3 real gaps found and fixed, 941/940+1 skip tests, 0 PHPStan errors, the blocked local-render attempt) is unchanged and not reopened — only the summary classification label is corrected.

## 38. Visual evidence ceiling — final documentation reconciliation (no code change)

- **Trigger:** a pure documentation-reconciliation pass to record, precisely and durably, the distinction between AdminCP's functional completeness, its locally-verified authenticated visual/markup state, and the still-unverified production/legacy pixel-level comparison — correcting entry #37's own classification label, which claimed more than its evidence supported.
- **Three distinct, deliberately separated status axes, not one blended "done":**
  1. **Functional:** `ADMIN_MIGRATION_COMPLETE` — all 11 Wave 5 feature modules, auth, the permission-sync bridge, and the capability reconciliation are implemented and test-verified. Unaffected by anything below; not reopened.
  2. **Local authenticated browser + legacy-source audit:** all 12 owner-named routes render (200, 0 console/page errors, 0 failed requests), were individually screenshotted and visually read (not sampled), and were cross-checked against their real legacy PHP source — which is what found and fixed the 3 real markup gaps in entry #37 (broadcasting thumbnails, YouTube iframe, staff avatars). This is real, genuine evidence — stronger than HTTP-200-only, but still not a pixel comparison against an actual rendered legacy/production page.
  3. **Production/legacy pixel-level visual parity:** **not verified.** No real production admin credentials exist or were used (none were requested, none would be appropriate to use without explicit owner-supplied access). No production writes were attempted. A safe, read-only, non-privileged attempt to render real legacy locally (its own already-hardcoded local-dev DB credentials, a per-process hostname alias, no file in `legacy-project` touched) was blocked by the sandbox's own safety classifier before it executed anything — reported at the time, not retried through a workaround. Source-level markup comparison, however rigorous, cannot substitute for an actual rendered pixel comparison — this is an evidence-availability limitation of the current environment, explicitly **not** a discovered code defect.
- **Corrected combined classification:** `ADMIN_MIGRATION_COMPLETE — AUTHENTICATED_VISUAL_PARITY_PARTIALLY_VERIFIED`. `FUNCTIONAL_AND_VISUAL_PARITY_RECONCILED` (used in entries #24, #30-37's summary lines) is retired as the standing label going forward — those entries are not rewritten (their own findings and fixes stand), but the phrase itself claimed a level of visual reconciliation (full production/legacy parity) this project's own evidence never actually reached, and should not be reused until §5's reopen condition below is met.
- **Closed, not to be reopened absent new contradictory evidence:** login visual parity (#29), authentication/`AdminGuard` (Wave 0), the legacy→Spatie permission-sync bridge (#32), the `permissions` attribute/relationship collision (#31), dashboard functionality (shell + demo-exclusion decision), sidebar authorization/permission filtering (#32), Broadcasting reachability (#23), the Locations interactive map (#27), the Permissions edit page (#31/#34), Staff (#28/#37), the global table/form/button class audit (#36), and the 3 image/embed markup gaps (#37, broadcasting thumbnails/YouTube iframe/staff avatars).
- **12-route local browser baseline, recorded as the durable reference point:** `/admincp`, `/admincp/survey`, `/admincp/soundcloud`, `/admincp/youtube`, `/admincp/questionnaire`, `/admincp/chat`, `/admincp/uploaders`, `/admincp/link-quality/{khotab,telawah,mirror}`, `/admincp/staff`, `/admincp/broadcasting` — all 12: local render verified, HTTP 200, 0 console/page errors, 0 failed requests, legacy source markup parity reviewed. Production browser visual comparison: explicitly unavailable, not attempted beyond the blocked safe local-render effort.
- **Future reopen condition, precisely scoped:** AdminCP visual parity is reopened only if (A) safe, explicitly owner-authorized authenticated production screenshots become available for the same 12 routes, or (B) a safe local rendering of the real, authenticated legacy AdminCP becomes available (e.g. the sandbox restriction is lifted, or the owner provides another safe rendering path) — in either case, compared directly against the already-captured Laravel baseline. Source inspection alone, however careful, is explicitly **not** sufficient grounds to reopen or to re-claim full visual parity.
- **No code changed in this task** — documentation only. Preserved baseline: 941 tests, 940 passed, 1 skipped, 0 failed; PHPStan 0 errors (both from entry #37, not rerun).
- **What was NOT changed:** any application code, route, test, or the underlying facts of entries #24-37 — this entry corrects a classification label and adds a durable evidence-ceiling record, nothing else.
- **Confidence:** High — this entry makes no new technical claim; it precisely restates what entries #24-37 already established and corrects only the summary label to match that evidence exactly.

**Current authoritative classification: `ADMIN_MIGRATION_COMPLETE — AUTHENTICATED_VISUAL_PARITY_PARTIALLY_VERIFIED`.** Functional migration is complete and unaffected. Authenticated AdminCP visual/markup state is verified locally (all 12 routes, real browser + legacy-source audit, 3 real gaps found and fixed in entry #37) but **production/legacy pixel-level parity remains unverified** — a disclosed evidence ceiling, not a known code gap. `KNOWN_ADMINCP_CODE_GAPS = 0` (nothing currently known to be broken); `PRODUCTION_VISUAL_COMPARISON = UNAVAILABLE` (not yet obtainable in this environment). Do not upgrade to a "reconciled"/"confirmed" production-parity classification until §5's reopen condition is met. **Standing operational note, unaffected:** any environment must still run `php artisan migrate --force`, then `php artisan db:seed --force --class=RoleSeeder` and `--class=AdminPermissionSeeder`, then `php artisan admin:sync-permissions` for real admin navigation/authorization to reflect legacy data — see `docs/PROJECT-HANDOFF.md` §10.

## 39. Final migration owner decisions — the last 3 open items closed, one implemented

- **Trigger:** the Final Whole-Project Migration Completion Audit found exactly 3 remaining `OWNER_DECISION_REQUIRED` items and 0 `CONFIRMED_MIGRATION_GAP`s. All 3 were presented together and answered in this round.

### Decision 1 — `ChatRoomAdminController`: `CHAT_ROOM_ADMIN = REMOVE`

- **Decision:** the already-shipped FlashChat live-voice-room admin tooling (Wave 5, task 5.5) is removed, not kept. The live-room feature itself remains retired with no replacement (Business Confirmation #4, decision-log #14) — keeping admin tooling for a feature that no longer exists was judged misleading/a maintenance risk, superseding the "reopened as unresolved" state recorded in decision-log #14.
- **Exact scope removed:** `app/Domain/Admin/Http/Controllers/ChatRoomAdminController.php`; `app/Domain/Admin/Models/Room.php` (the `flashchat` connection's `room` table model — used only by this controller, confirmed via a repo-wide grep before removal); `resources/views/admin/chat/{index,edit}.blade.php`; the `Route::prefix('chat')` group in `routes/admin.php` (`admin.chat.index/edit/update/owner.destroy/speaker.destroy`); the `admin.chat.index` ("الغرف الصوتية") entry in `AdminDashboardModules::MODULES`; `tests/Feature/Admin/ChatRoomAdminControllerTest.php` (7 tests).
- **Explicitly NOT removed:** the `chat.listrooms`/`chat.editroom`/`chat.deleteroom`/`chat.listroom` Spatie permission *definitions* in `AdminPermissionSeeder` — left seeded, matching the already-established `EXPECTED_LEGACY_PERMISSION_METADATA` precedent (decision-log #33) for harmless real-legacy-key-name metadata with no active gate; `App\Domain\Content\Http\Controllers\ChatRoomLessonController` and its 4 recorded-lesson-browsing routes (`chat_room.htm`, `chat_author_{id}.htm`, `chat_lesson_{id}.htm`, `lesson-download-{id}.htm`) — confirmed via source read to be a completely separate controller using `KhotabItem`/`Author`, never the `Room`/`flashchat` model, entirely unaffected.
- **Dependent tests fixed, not just deleted:** `AdminPageLevelParityTest.php`'s "Chat: 2 real portlets" test and its `Room`/`InMemoryConnection::setup('flashchat', ...)` usage removed; `AdminAuthControllerTest.php`'s "super-admin sees every dashboard module" test had its `admin.chat.index` assertion removed (the module itself no longer exists to see); `SyncLegacyAdminPermissionsActionTest.php`'s "an ungranted permission still 403s" test swapped its example route from the now-gone `admin.chat.index` to `admin.staff.index` (still a real route the same test admin genuinely lacks permission for).
- **New regression coverage:** `tests/Feature/Admin/AdminRetiredRoutesTest.php` (4 tests) — proves `Route::has('admin.chat.*')` is false, `GET /admincp/chat` 404s for a real authenticated super-admin (not 200/403), `ChatRoomAdminController`/`Room` classes no longer exist, and the dashboard no longer lists "الغرف الصوتية" while every other real module still does.

### Decision 2 — `khotab/uploader.php` add-flow: `KHOTAB_UPLOADER_ADD_FLOW = DO_NOT_BUILD`

- **Decision:** do not build a new "add uploader by forum-member-id" workflow. No legacy backend exists anywhere in `khotab/uploader.php`/`uploaders.php` to prove the intended behavior from (task 5.9's own scope note, unchanged) — behavior-first migration means not inventing business functionality the source cannot prove.
- **Classification:** `SOURCE_UNRECOVERABLE — OWNER_APPROVED_DO_NOT_BUILD`.
- **No implementation.** The already-migrated uploader listing/tracking capability (`UploaderController::index()`/`recompute()`/`backfillVbulletinIdentity()`) is unchanged, unaffected, not touched this round.

### Decision 3 — `locations.htm` main public index: `LOCATIONS_MAIN_INDEX = DO_NOT_BUILD_SOURCE_UNRECOVERABLE`

- **Decision:** do not build a new public `locations.htm` listing page. The location data model is proven (already backs the real, migrated `location-{id}.htm`/`alhedaya-room.htm`/author/item detail routes, Wave C), but no historical public template/DOM for the *index* page itself survives anywhere — inventing one would be designing new public-facing presentation the source never proves, the same standard already applied to RSS (IF-045), `fatawa-category-{id}.htm` (IF-038), and `cds-series-{id}.grx` (IF-044).
- **Classification:** `locations.htm = SOURCE_UNRECOVERABLE — OWNER_APPROVED_DO_NOT_BUILD`.
- **No implementation.** Every already-migrated Locations route (`location-{id}.htm`, `alhedaya-room.htm`, `location-{id}-author-{id2}.htm`, `location-{id}-item-{khotab}.htm`, and the full AdminCP Locations module) is unchanged, unaffected, not touched this round.

### Verification

- **Tests:** targeted (`AdminRetiredRoutesTest`, `AdminPageLevelParityTest`, `AdminAuthControllerTest`, `SyncLegacyAdminPermissionsActionTest` — 46 tests) green; full Admin suite (164 tests) green; **full application suite: 937 tests, 936 passed, 1 skipped, 0 failed** (down from 941 — net −4: −7 `ChatRoomAdminControllerTest`, +4 `AdminRetiredRoutesTest`, ±1 from other file adjustments, no failures anywhere). PHPStan: 0 errors.
- **What was NOT changed:** any other AdminCP module, `ChatRoomLessonController`/public chat-room-lesson routes, permission definitions, the Locations/Uploader modules' existing real capability, database schema, `legacy-project` (confirmed clean, 0 changed files throughout).
- **Confidence:** High — the exact removal scope was derived from a repo-wide grep before any deletion (not assumed), every dependent test was traced and fixed individually (not deleted wholesale), and the retired surface's actual absence is now proven by an automated test, not just asserted in prose.

**Final migration classification, this round: `MIGRATION_COMPLETE`.** All 3 owner decisions closed (1 implemented — `CHAT_ROOM_ADMIN = REMOVE`; 2 documentation-only — `KHOTAB_UPLOADER_ADD_FLOW = DO_NOT_BUILD`, `LOCATIONS_MAIN_INDEX = DO_NOT_BUILD_SOURCE_UNRECOVERABLE`). `CONFIRMED_MIGRATION_GAPS = 0`. `OWNER_DECISIONS_REQUIRED = 0`. This closes the Final Whole-Project Migration Completion Audit's own outstanding items — AdminCP's own separate `AUTHENTICATED_VISUAL_PARITY_PARTIALLY_VERIFIED` status (entry #38, an evidence-availability ceiling, not an owner decision or a code gap) is unaffected and unchanged by this closure.

## 40. Real production screenshot comparison finds and fixes a genuine shared-shell sidebar structure gap

- **Trigger:** the owner supplied 2 real screenshots of the same authenticated page — production (`way2allah.com/admincp/`) and local Laravel (`127.0.0.1:8123/admincp`) — as primary visual evidence, explicitly overriding any prior "parity" conclusion drawn from CSS hashes, class names, or passing tests alone.
- **Confirmed via direct visual comparison, then traced to source, not assumed:** production's real sidebar renders several items as expandable parents with a Metronic `<span class="arrow">` chevron (المشرفين, النسخ الاحتياطي, البث المباشر, غرفة الهداية, المرئيات, مساجد و أماكن, الاستبيان all show one; ساوندكلاود/اليوتيوب don't) — Laravel's sidebar rendered every item as a single flat link, no arrows anywhere. A fresh, complete read of `admincp/sidebar.php` explains this precisely: any module whose own `menu.php` defines a non-empty `$modulelinks` array renders as a real expandable parent (`<a href="javascript:;">` + `<span class="arrow">` + `<ul class="sub-menu">`, one `<li>` per link); a module with zero `$modulelinks` renders flat. This is a genuine `DOM_STRUCTURE_MISMATCH` at the **shared shell** level (`layouts/admin.blade.php`'s sidebar loop + `AdminDashboardModules`'s flat data shape) — it affects every authenticated page's sidebar, not just the dashboard, consistent with the owner's repeated "many pages look different" reports across this whole session that neither the CSS-load-order audits nor the bare-form-element audits (entries #34/#36) ever actually explained.
- **Every real module's `$modulelinks` re-read fresh before touching anything**, not inferred from one example: `survey`/`soundcloud`/`youtube`/`questionnaire` have **zero** entries — confirmed flat in legacy too, left flat, a genuine `MATCH`, not a gap. `locations` (2: list/add), `authors`/staff (2: add/list), `khotab` (7, of which 4 map to already-migrated Laravel routes: uploaders, link-quality-khotab, link-quality-khotab-large-files, link-quality-mirror), and `telawah` (1: stats → link-quality-telawah) have real, non-empty `$modulelinks` and are now grouped, using **only already-existing Laravel routes** — no new route, controller, or content-CRUD capability was added or invented. `broadcasting`'s one real `$modulelinks` entry hardcodes a single legacy channel id (`edit_stream.php?...&id=51`) — a legacy quirk, not a reusable pattern; deliberately left flat rather than reproduced, since `admin.broadcasting.index`'s real listing page is already a strictly better replacement.
- **`chat`'s real grouping (`غرفة الهداية`) is visible in this production screenshot too, and was deliberately NOT restored** — the owner's own immediately-prior decision (`CHAT_ROOM_ADMIN = REMOVE`, entry #39) already closed this; seeing it in a screenshot does not reopen a decision made moments earlier in the same session. Explicitly not reopened, per this task's own protected baseline.
- **Fix:** `AdminDashboardModules::MODULES` now supports parent entries (`label`/`icon`/`children`, no `route` of their own) alongside the existing flat leaf entries; `visibleFor()` filters each child by its own permission(s) and only shows a parent if at least one child is visible (super-admin bypass unchanged). `layouts/admin.blade.php`'s sidebar loop now renders the real Metronic parent/arrow/sub-menu markup for grouped entries, byte-shape-matching `sidebar.php`'s own real output, and the existing flat rendering unchanged for ungrouped entries. `dashboard.blade.php`'s body list (a Laravel-only navigation convenience, not literal legacy content) was updated to flatten each group's children into individual bullets rather than break on the new shape.
- **Verification:** real authenticated Puppeteer screenshot of the fixed dashboard shows the sidebar rendering 4 collapsed groups with visible chevrons (مساجد و أماكن, المرئيات, التلاوات, فريق الإدارة) exactly matching production's structural pattern — 0 console/page errors. One unresolved, disclosed, pre-existing nuance found and NOT treated as a new defect: the sidebar's real CSS class is `page-sidebar-menu-hover-submenu1` (with a "1" suffix) in **both** legacy's own `sidebar.php` and Laravel's (unchanged, pre-existing, not introduced this round) — the bundled `layout-rtl.min.css` only has hover-expand rules for the un-suffixed `page-sidebar-menu-hover-submenu` class, so a Puppeteer-simulated hover/click did not visibly expand a group in this headless test. Since this exact class/markup/JS bundle is identical to legacy's own and produces zero console errors, and neither of the 2 supplied screenshots shows an expanded state to compare against, this is recorded as an unresolved, low-confidence, out-of-scope-for-this-task observation — not chased further, not claimed fixed, not claimed broken.
- **4 new regression tests** (`AdminAuthControllerTest`) covering: grouped markup renders (4 arrows, 4 sub-menus, real routes present as real children), flat entries stay flat with real `href`s, per-child permission filtering (an admin with only `locations.del_location` sees the list sub-link but not the create one, matching `admin.locations.create`'s own real gate), and an admin with no matching permissions in any group sees no empty parent rendered. Full suite: 941 tests, 940 passed, 1 skipped, 0 failed (up from 937 — +4). PHPStan: 0 errors.
- **What was NOT changed:** any route, controller, permission definition/gate, business logic, database schema, `AdminGuard`/authentication, the permission-sync bridge, or any already-closed AdminCP/migration decision (`legacy-project` confirmed clean, 0 changed files).
- **Alternatives considered:** leaving the sidebar flat and treating the screenshot difference as cosmetic (rejected — the owner explicitly said screenshots override prior "parity" assumptions, and the root cause traces to real, unambiguous legacy source, not a judgment call); reproducing khotab's 3 remaining content-CRUD `$modulelinks` sub-items too (rejected — those have no Laravel destination, Business Confirmation #6 already excludes that content-CRUD from this migration; would be dead links, not a real fix); reproducing broadcasting's single hardcoded-id sub-link (rejected — a legacy quirk, not a pattern worth preserving, and strictly worse than the existing real listing page).
- **Confidence:** High for the structural fix itself — traced to a fresh, complete read of `sidebar.php`'s actual logic and every real module's actual `$modulelinks`, not inferred from the screenshot alone, and verified with a real post-fix screenshot showing the matching arrow/group pattern. Explicitly unresolved/low-confidence: the hover-expand interactive behavior, disclosed as such rather than claimed either way.

**Current authoritative classification, unaffected in scope:** `MIGRATION_COMPLETE` (entry #39) stands — this is a visual/shared-shell fix, not a migration-completeness or business-decision change. AdminCP's own `AUTHENTICATED_VISUAL_PARITY_PARTIALLY_VERIFIED` status (entry #38) is updated in spirit by this real production-screenshot evidence (the first genuine production-pixel comparison this whole engagement has had) but the formal classification label is unchanged pending a full re-verification pass across all 12 routes with this fix in place — not claimed as newly `CONFIRMED` on the strength of one page's screenshot alone.

## 41. Owner explicitly reverses `LEGACY_DEMO_DASHBOARD = DO_NOT_REPRODUCE` — real production screenshots prompt exact reproduction of home.php's demo content

- **Trigger:** the owner supplied 3 more real screenshots (2 production, 1 local) directly asking, in their own words, why the dashboard still looks different and asking to "return to legacy code... take from it the code with s[u]cce[ss] design."
- **Re-confirmed fresh, not assumed from memory:** a full, line-by-line read of `admincp/home.php` (1135 lines) reconfirms the standing finding from earlier in this engagement — every number, name, and timestamp on this page (17800, 1349, 567, 276, all percentages, the "نشاط الأعضاء" member table's 4 rows/avatars, all ~27 "السجلات"/"الأنشطة" activity-feed entries with their fixed relative timestamps) is **literally hardcoded in the HTML**, zero database queries anywhere in the file. A repo-wide grep additionally confirms the Morris.js chart (`#sales_statistics`), both sparklines (`#sparkline_bar`/`#sparkline_bar2`), and the region-stats world map (`#vmap_world` etc.) have **no initialization code anywhere** in the bundled JS (including the demo bundle) — they are inert/empty even in real production, which the owner's own second screenshot corroborates (the "إحصائيات بالدولة" card renders essentially empty, not a real map).
- **Explicit owner decision, asked directly via `AskUserQuestion` before implementing anything this large:** reproduce the content as an **exact visual copy, including the same hardcoded demo numbers** (not a "wire in real data" redesign, not a partial subset) — the owner's own choice among 3 presented options. This is a direct, explicit reversal of the standing `LEGACY_DEMO_DASHBOARD = DO_NOT_REPRODUCE` decision that governed this page since the original AdminCP Full Visual/Layout Parity Reconstruction — recorded here as a reversal, not a silent overwrite of that earlier reasoning (which was sound at the time, for the information available then; the owner has now seen the real side-by-side difference and decided differently).
- **Implementation:** `resources/views/admin/dashboard.blade.php` rewritten to reproduce `home.php`'s real markup verbatim — the 4 stat cards, the "إحصائيات مختصرة"/"نشاط الأعضاء" portlet row (including the real hardcoded member table with the 4 real staff-shaped names and their real bundled `avatar4/5/6/7.jpg` images, already present under the existing `public/assets` symlink), the "إحصائيات بالدولة"/"السجلات" portlet row (including the inert map/loading-gif placeholders and the full, real ~27-entry activity feed across both tabs) — every string and number transcribed directly from the legacy file, none invented. Raw portlet markup (`class="portlet light "`) used instead of `<x-admin-portlet>` for these specific portlets, matching legacy's own exact class shape (the shared component's `light` variant always adds `bordered`, which this page's real portlets don't have — the same discrepancy already found and worked around for the Permissions page in an earlier task). The old "الأقسام الإدارية" navigation-list body (a Laravel-only convenience with no legacy equivalent on this specific page) is removed — the sidebar already provides complete, permission-filtered navigation.
- **Verification:** real authenticated Puppeteer screenshots (top of page and scrolled) visually compared side-by-side against the owner's own supplied production screenshots — stat cards, member table, activity feed, and region-stats card all match in content, layout, and (for the numbers) exact values. 0 console/page/HTTP errors.
- **3 pre-existing tests updated to match the new, owner-directed reality, not silently left contradicting it:** `AdminVisualParityTest`'s `portlet box purple` assertion (this page no longer has that specific portlet) corrected to `portlet light`; its "does not render any legacy demo dashboard widgets" test — which enforced the now-reversed exclusion — replaced with a test asserting the real demo content **is** now present (renamed and re-documented as a deliberate reversal, not deleted quietly); its "sidebar matches dashboard body list" test corrected since the dashboard body no longer duplicates the sidebar's module links. `AdminAuthControllerTest`'s "empty dashboard" test (asserting the old "no modules available" message) corrected to assert the real content renders instead, since the dashboard is no longer permission-filtered content (matching legacy, where every admin sees the identical static page regardless of their permissions — only the sidebar differs per-admin).
- **What was NOT changed:** any route, controller business logic, permission gate, database schema, `AdminGuard`, or any already-closed decision besides the one explicitly reversed here.
- **What was explicitly NOT done:** wiring any of these numbers to real data (the owner's own chosen option was the exact-copy one, not the real-data one) — if real data is wanted for any specific widget later, that is a new, separate, explicit decision, not implied by this one.
- **Tests/PHPStan:** full suite 941 tests, 940 passed, 1 skipped, 0 failed (unchanged count from entry #40 — 3 tests corrected in place, not added/removed net). PHPStan 0 errors.
- **Confidence:** High — every reproduced string/number traces to a specific, re-read line in `admincp/home.php`; the "charts are inert everywhere" claim is independently corroborated by both a JS-init grep and the owner's own production screenshot; the scope decision itself was the owner's own explicit choice, not inferred.
- **Extended same day to the header, on further owner evidence:** 2 more real screenshots (production vs local) isolating just the collapsed header bar showed the same category of gap — production's real notification (bell, badge 7), inbox (envelope, badge 4), and todo (calendar, badge 3) dropdown triggers plus the header search box, which this layout never had (only the already-real user/logout dropdown existed). Traced to `admincp/header.php` (search form) + `admincp/navigation_menu.php` (the 3 dropdowns) and reproduced verbatim in `layouts/admin.blade.php` — including their English lorem-ipsum demo list content ("Lisa Wong", "Vivamus sed auctor nibh...", "New release v1.2" etc.) and their real `href="#"`/dead-`.html` targets, exactly as inert in legacy as here, not newly broken. The "Actions" dropdown and the user-menu's Profile/Calendar/Lock-Screen entries were explicitly left excluded — not visible in either supplied header screenshot, no evidence they differ. 1 pre-existing test (`AdminVisualParityTest`) that asserted the now-reversed exclusion was inverted and renamed rather than left contradicting the new behavior. Full suite unchanged at 941/940+1 skip, 0 failed; PHPStan 0 errors. Verified with a fresh authenticated header-only screenshot matching the owner's own production header exactly (same 3 badges, same colors, same order, same search icon).

- **Extended again same day, on 4 more real screenshots (2 of the opened user-dropdown, 2 of the sidebar):** two more real, distinct gaps confirmed and fixed.
  1. **User dropdown:** production's opened dropdown shows real Profile/Calendar/Messages(badge 3)/Tasks(badge 7)/divider/Lock-Screen entries above the real Logout — this layout only ever had the bare logout. Reproduced verbatim from `navigation_menu.php`'s own real "USER LOGIN DROPDOWN" markup (dead `href="#"` targets included, same as legacy's own equally-dead `extra_profile.html`/`page_calendar.html`/etc. — `admincp/w2a__lock.php` deliberately still NOT wired to "شاشة القفل", since legacy's own link doesn't point there either, and that page independently relies on the already-excluded insecure cookie scheme). Only the real, unchanged, CSRF-protected logout form remains functional.
  2. **Sidebar order and one label, root-caused not guessed:** a real sidebar screenshot showed a materially different item order than this layout produced. Re-read `sidebar.php`'s actual iteration — `glob($current_file_path.'/*')`, PHP's default *alphabetical* sort, not a curated list — so legacy's real order is simply each `admincp/` subdirectory's name alphabetically: `authors → broadcasting → khotab → locations → questionnaire → soundcloud → survey → telawah → youtube` (`chat` excluded, per the owner's own prior `CHAT_ROOM_ADMIN = REMOVE` decision, unaffected). `AdminDashboardModules::MODULES` reordered to match exactly, confirmed against the real screenshot's own sequence. Two further findings in the same pass: `authors`'s real `menu.php` arabic name is `المشرفين`, not the `فريق الإدارة` this class previously used (inherited from an unrelated earlier entry, now corrected to the real source value); `broadcasting` has 1 real `$modulelinks` entry (a channel id-51-hardcoded quirk) so it renders as a **grouped** item in real legacy too — grouped here as well, with its one child pointing to the real, superior `admin.broadcasting.index` listing instead of literally reproducing the hardcoded-id target.
  3. **Known, disclosed, NOT-implemented gap:** production's real sidebar also shows a `النسخ الاحتياطي` (backup) group, alphabetically between `authors` and `broadcasting`. Its `admincp/backup/` directory does not exist anywhere in the current `legacy-project` reference snapshot (confirmed via a repo-wide `find`, not assumed), so its real `menu.php`/`$modulelinks` cannot be read, and no Laravel admin page for it exists or was ever in scope (the real backup capability is the root `/backup.php` machine API, decision-log #33). Not fabricated without real source — recorded here as an open item, not silently skipped.
  - **4 pre-existing tests corrected** (2 in `AdminAuthControllerTest` — arrow/sub-menu count 4→5, the `فريق الإدارة`→`المشرفين` label swap in a route-content assertion, and the broadcasting-flat test renamed/clarified; 1 in `AdminVisualParityTest` for the same label swap; 2 in `PermissionControllerTest` tightened from a bare `'الملف الشخصي'` string check to `'icon-globe"></i>الملف الشخصي'`, since that bare string now also legitimately appears in every page's header user-dropdown and was no longer a reliable proxy for "the profile-content portlet specifically rendered"). Full suite: 941/940+1 skip, 0 failed (unchanged count — corrections in place, not net additions). PHPStan 0 errors. Verified with fresh authenticated screenshots (sidebar + opened user-dropdown) matching the owner's own production screenshots closely, backup group aside.

**Current authoritative classification, unaffected in scope:** `MIGRATION_COMPLETE` (entry #39) stands. AdminCP's `AUTHENTICATED_VISUAL_PARITY_PARTIALLY_VERIFIED` status (entry #38) is further strengthened by this round of real production-screenshot comparisons but remains formally unchanged pending the same full 12-route re-verification noted in entry #40 — not upgraded on the strength of individual pages/elements. One real, disclosed open item remains: the `النسخ الاحتياطي` (backup) sidebar group has no recoverable source and no Laravel destination page — not fabricated, not silently dropped.

**[Note 2026-11-06, #100: the `النسخ الاحتياطي` (backup) sidebar group above is still undispositioned — it is the one item #100 leaves pending an owner disposition.]**

**[Update 2026-11-06, #101: the backup group's source was found locally in `7amlat/admincp/backup/` (git-ignored by the
`backup/` pattern at `7amlat/.gitignore:125`, which is why the reference snapshot lacks it). Dispositioned: add admin,
edit admin permissions and the six `backup.*` permissions are COVERED; the read-only booking monitor is an
OWNER-APPROVED DEFERRED LOW item. This AdminCP sidebar group is distinct from the root `backup.php` machine API (#33,
already ported).]**

---

## 42. Local legacy-vs-Laravel browser parity — entry #38's reopen condition finally met, multiple rounds of real fixes, closed as TEMPORARY owner acceptance only

- **Trigger:** the owner asked for a true same-DB/same-admin browser comparison between a **locally-running legacy AdminCP** and local Laravel — not source inspection, not production screenshots. Port 8081 (initially assumed to be legacy) was actually phpMyAdmin; the owner then explicitly authorized standing up `legacy-project` in Docker.
- **Entry #38's reopen condition (B) — "a safe local rendering of real authenticated legacy becoming available" — is now met**, for the first time this whole engagement. Built `legacy-admincp-compare` (reuses the existing pre-built `7amlat_php` image, read-only bind mount of `legacy-project`, port 8083, on the same `admin_default` network as the shared `mysql57`/`olddb` this Laravel app already uses). Two real, non-`legacy-project`-touching blockers found and fixed at the container-runtime/local-comparison-infrastructure level only: `mod_rewrite` not actually enabled despite the Dockerfile listing it (`a2enmod` + graceful reload); `w2a_config.php` unconditionally opens a `w2acp_vb` vBulletin connection that didn't exist locally, crashing every page — created a new, disposable, separate database with only the tables each page actually queried (`usergroup` for survey moderator-group checkboxes), never touching `olddb`.
- **Root-caused a genuine login blocker, not a tooling problem:** `admincp/index.php` sets its post-login cookies with a hardcoded `.way2allah.com` domain, which a browser correctly refuses to store for `127.0.0.1`. Fixed via a browser-level DNS override (`--host-resolver-rules=MAP way2allah.com 127.0.0.1`) so the cookie domain legitimately matches the host — not a `legacy-project` edit, and request interception ensures the browser never actually reaches real production despite `$siteurl` resolving to the real domain in this production-data-mirroring snapshot.
- **Confirmed via real login that HOME already matched exactly** (`LOCAL_LEGACY_BROWSER_VISUAL_PARITY_VERIFIED`) — same stat-card numbers, same member table, same activity feed, byte-identical shell.
- **A deeper legacy defect found and NOT reproduced:** every module subpage (everything except the dashboard home) routes through `admincp/main.php`, which never populates `$w2a`/`$_SESSION['uid']` — so `sidebar.php`'s permission-lookup silently fails on every subpage, and two literal leftover `var_dump()` debug statements leak into every subpage's output. Both are real, confirmed, pre-existing production bugs (independently reproducible, not local-environment-specific) — classified `LEGACY_BUG_NOT_FOR_REPRODUCTION`, not chased into Laravel. Body-level comparison for the 9 previously-untested inner pages (survey, soundcloud, youtube, questionnaire, uploaders, khotab/mirror/telawah link-quality, staff, broadcasting) was instead done by reading each page's real canonical source (from its own `menu.php`) directly — 100% label/structure match, zero fabricated content.
- **A genuinely new, real permission-architecture finding, deliberately not acted on unilaterally:** `sidebar.php` has **no** `radminsuper` bypass anywhere — real legacy sidebar visibility is driven entirely by the raw, often-stale `nuke_authors.permissions` blob per module, confirmed by directly unserializing the real test admin's own blob (missing `survey`/`telawah` keys despite being `radminsuper=1`). Laravel's existing superadmin-bypass sidebar behavior diverges from this. Flagged for an owner decision rather than silently changed (out of this task's explicit scope — permission architecture). **Owner decision recorded:** `ADMIN_SUPERADMIN_PERMISSION_BEHAVIOR = KEEP_LARAVEL_BYPASS` — Laravel's bypass is intentional/hardened, not a bug to match legacy's data-drift-prone behavior. Not implemented as new code (no code change was needed — Laravel already behaved this way).
- **A follow-up closure pass** created disposable, clearly-named, fully-cleaned-up test rows (one survey, one question, one answer, one questionnaire response — IDs recorded before creation, deleted and verified back to the exact pre-insertion baseline afterward) and a temporary local `vbulletin` connection pointed at `w2acp_vb` (reverted to blank in `.env` afterward) to exercise the 7 previously-untestable routes (`survey/create`, `stats`, `all-stats`, `questions`, `answer.show`, `questionnaire/show`, `locations/edit`). Found and fixed 4 small, real content gaps: a wrong field label (`"الحد الأقصى للطول..."` → legacy's real `"الحد الأقصى لطول الإجابة"`), a plain-text view link that should be an icon-only badge, a generic `"عضو #ID"` placeholder that should resolve the real username via the same `LEFT JOIN users` legacy performs (same `main` connection, no new one needed), and a wrong 2nd portlet caption on the questionnaire detail page (`"ملاحظات الاستبيان"` → real `"بيانات الاستبيان"`).
- **A dedicated shared-shell pass** (navbar + sidebar specifically, since the owner kept seeing differences there) found and fixed 2 real, confirmed shared-layout defects: (1) `layout.min.js` self-initializes on its own last line (`jQuery(document).ready(()=>Layout.init())`) — `layouts/admin.blade.php` also called `Layout.init()` explicitly, double-binding every delegated sidebar click handler so clicking a parent module visibly did nothing (confirmed live: 6 handlers bound instead of legacy's real 3); removed the redundant call. (2) Two sibling children sharing a route-name prefix (`admin.link-quality.khotab.index`/`.large-files`) both lit up `active` together when either was visited — the active-state check now requires an exact-route match first, only falling back to the prefix wildcard when the current route isn't already one of that group's own listed siblings (preserves the wildcard's real purpose: keeping a parent highlighted from a non-sidebar-listed sub-route like `broadcasting.edit`). Also investigated and resolved, not left as an unexplained nuance: `page-sidebar-menu-hover-submenu1`'s "1" suffix means Metronic's real hover-only CSS/JS rule (targeting the un-suffixed class) never matches — real legacy behavior, confirmed live on both sides identically, is genuine click-to-expand, not hover.
- **Two further real DOM/interaction findings, this time in the navbar dropdowns specifically:** the `.dropdown-user` trigger was missing its real `<img class="img-circle">` avatar element entirely (legacy's own copy is broken — `$_COOKIE['avatar']` is never set by any login path — but the DOM element itself is real and occupies real layout space, confirmed 39×39px via `getComputedStyle`/`getBoundingClientRect` on the real render); restored using a safe 1×1 transparent PNG data URI (zero network requests, never a 404, not reusing the broken cookie semantics) that reproduces the exact measured geometry via the existing `height:39px` CSS rule alone. The logout row's visible element was a `<button>` inside a `<form>`, which `.dropdown-menu>li>a:hover{...}` structurally can never match — restructured to a real `<a>` (matching legacy's own `<a href="index.php?op=logout">` shape) that prevents its own dead `#` navigation and submits a hidden POST+CSRF `<form>`; hover background now confirmed identical on both sides (`rgb(91,111,124)`). AdminGuard's POST-only, CSRF-protected logout semantics were never touched — live-verified end to end (real POST captured, session cookie rotated, redirect to login, `GET /admincp/logout` still 405).
- **A distinct, real legacy source bug found and explicitly not chased further:** `navigation_menu.php`'s logout link references `$adminurl`, which the function never declares `global` — always undefined inside it, on every environment including production. On real production (`display_errors` off) this is completely invisible (an undefined variable echoes empty, and the resulting relative URL still resolves correctly from `/admincp/`) — confirmed by hitting the real logout endpoint directly and watching every auth cookie clear. Only in this local Docker image, with `display_errors=On`, does the warning's raw HTML (including a literal `"`) escape the `href` attribute and corrupt the link into garbage — reproduced and diagnosed, explicitly `LEGACY_BUG_NOT_FOR_REPRODUCTION`, a local-debug-setting artifact rather than a real functional gap.
- **Tests:** `AdminSidebarActiveStateTest` (new, 7 tests — flat/grouped active state, both khotab sibling-collision directions, the broadcasting prefix-fallback preserved, home-link scoping, no retired chat entry), plus several `AdminAuthControllerTest` additions/corrections across the sequence (avatar `<img>` presence, the real `<a>`+hidden-form+CSRF logout shape replacing an now-obsolete button-padding assertion, dead-link-stays-dead assertions, logout POST-only/session-termination coverage). Full suite: 951/952 passed, 1 pre-existing unrelated skip, throughout every step of this sequence. PHPStan: 0 errors throughout.
- **What was NOT changed, across the entire sequence:** `AdminGuard`, login, permission sync, database schema, business logic/CRUD semantics, any page body (only the shared shell/navbar/sidebar and the specific inner-page content gaps listed above), the public site, or the already-retired FlashChat chat tooling. `legacy-project` confirmed unchanged (`git status` clean) at every step, including across an unplanned container restart mid-sequence (a runtime-only event — `mod_rewrite` re-applied, nothing in `legacy-project` touched).
- **Explicit owner instruction governing this entry's classification:** record this round as **temporary acceptance only** — the owner is still manually reviewing the authenticated AdminCP and may find further differences. **Do not treat this as final AdminCP visual closure.**

  **`ADMINCP_USER_DROPDOWN_PARITY_VERIFIED — TEMPORARY_OWNER_ACCEPTANCE`**
  **`ADMINCP_VISUAL_PARITY_TEMPORARILY_ACCEPTED — SUBJECT_TO_OWNER_REVIEW`**

  This supersedes entry #38's `AUTHENTICATED_VISUAL_PARITY_PARTIALLY_VERIFIED` for the routes and elements this entry actually re-verified against a real local legacy render (HOME, the shared navbar/sidebar shell, the 9 previously-untested inner pages, the 7 previously-untestable sub-routes) — but explicitly does **not** promote AdminCP to any form of permanent/final visual-parity closure. Move to a permanent closure classification only after the owner explicitly confirms the AdminCP looks correct overall — not on the strength of any individual fix in this sequence, however well-evidenced.
- **[Owner decision 2026-11-06, #100: the super-admin sidebar divergence flagged above is resolved — Laravel's behaviour approved (super-admins may see every AdminCP module; every operation stays behind its own authorization). Visual parity keeps `SUBJECT_TO_OWNER_REVIEW` — no final visual approval.]**
- **Temporary local comparison infrastructure — deliberately left running, not torn down:** `legacy-admincp-compare` (Docker container) and `w2acp_vb` (the disposable local vBulletin-stand-in database, containing only its `usergroup` table — never `olddb`, never production) remain available for further side-by-side comparison until the owner is done reviewing. Both are `TEMPORARY_LOCAL_COMPARISON_INFRASTRUCTURE`, not part of the application or its migration.
- **Confidence:** High for every individual fix — each traces to a live-measured, real-browser-confirmed discrepancy on both sides (computed styles, bounding rects, actual click/POST/cookie behavior), not source inference alone. Explicitly not claimed: that this is the last round of AdminCP visual findings — the owner's own manual review is still active and may reopen further items.

---

## 43. Two reported legacy-shaped 404s reconciled — `khotab-fatwa-17.htm` and `fatawa-today.htm` — neither is a migration gap; `MIGRATION_COMPLETE` (entry #39) is unaffected

- **Trigger:** the owner reported two production URLs still 404ing — `khotab-fatwa-17.htm` and `fatawa-today.htm` — and asked for the same evidence-first reconciliation process used throughout this project (`.htaccess` trace, full legacy source trace, production HTTP check, current Laravel audit) before any fix.
- **`khotab-fatwa-17.htm` — SOURCE_UNRECOVERABLE (IF-055).** Exhaustive search (`.htaccess`'s 217 rules including commented-out ones, a repo-wide grep across every file type in `legacy-project`) found zero evidence this URL shape ever existed — no rewrite rule, no generated link, no dispatcher `op=` branch combining the Khotab and Fatwa concepts. Unlike IF-053 (`category-group-{id}-{id2}.htm`, a real rule pointing at the missing `new_modules.php` dispatcher), there is no rule here to even point anywhere. The trailing `17` cannot be attributed to any known convention with zero code to interpret it against. Read-only production check confirms a genuine Apache 404, consistent with "never existed." Not implemented — regression-pinned as 404 (`tests/Feature/Content/KhotabDeadRoutesTest.php`, new).
- **`fatawa-today.htm` — OWNER_DECISION_REQUIRED (IF-054), resolved same day.** A real, different situation: `header.php:278` (the shared site nav, rendered on every page) currently generates a live link to exactly this string (`<a href="fatawa-today.htm">حسب تاريخ الإضافة</a>`), but `.htaccess` only ever defined `fatwa-today.htm` (no extra "a") — a legacy typo that has always 404'd, confirmed via a fresh read-only request against real production today. The correctly-spelled `fatwa-today.htm`/`fatwa-today-{page}.htm` are already `MIGRATED_ALREADY_CORRECT` (`FatwaDayController`, tested). The sibling "المرئيات" (video) dropdown's identically-labeled 4th item (`khotab-video-today.htm`) does have a matching, working rule — strong, direct evidence of intent. **Owner decision:** redirect the typo to the canonical route — explicitly framed as a compatibility fix for a confirmed legacy typo, not new functionality; `fatwa-today.htm` stays canonical. Implemented as `Route::redirect('/fatawa-today.htm', '/fatwa-today.htm')` (`routes/content.php`, immediately after the canonical routes) — no new controller, no query/view logic duplicated.
- **Migration-completeness impact: none. `MIGRATION_COMPLETE` (entry #39) is not reopened.** Neither URL represents a capability that existed and was lost during migration — `khotab-fatwa-17.htm` never existed anywhere in scope, and `fatawa-today.htm` was already, independently dead in legacy before this project began. The redirect is a deliberate, owner-approved deviation from strict reproduction (legacy's typo stays broken forever; Laravel corrects it) — recorded here, not silently applied, and not a functional migration gap.
- **Tests:** targeted (`KhotabDeadRoutesTest`, `FatwaDeadRoutesTest`, `FatwaDayControllerTest`) — 12/12 passed after the redirect test was added. Full suite and PHPStan re-run below.
- **What changed:** `routes/content.php` (1 new `Route::redirect()`), `tests/Feature/Content/KhotabDeadRoutesTest.php` (new, pins `khotab-fatwa-17.htm` 404), `tests/Feature/Content/FatwaDayControllerTest.php` (+1 test, redirect assertion). No existing route, controller, or fatawa/khotab behavior otherwise touched. `legacy-project` confirmed unchanged (`git status` clean). No production data accessed — both production checks were read-only GETs.

---

## 44. Focused follow-up — `fatwa-today.htm` visual/structural parity restored; `khotab-fatwa-{id}.htm` re-traced at the family level (classification unchanged); a real, previously-open production question answered

- **Trigger:** the owner asked for two things only, explicitly not a broad sweep: (A) restore `fatwa-today.htm`'s legacy-visible markup, since the current Blade was a generic `<ul><li>` list, structurally nothing like the real page; (B) re-open `khotab-fatwa-{id}.htm` at the *family* level — was entry #43's SOURCE_UNRECOVERABLE conclusion for `id=17` actually true for the whole pattern, or just an artifact of testing one ID?
- **A major, previously-open production question got answered as a side effect of this pass's read-only production checks, directly relevant to both targets:** `fatwa-today.htm` routes through `new_modules.php` (`.htaccess:281`). IF-026 (pre-existing) already established `new_modules.php` is absent from this codebase's own snapshot but explicitly left "does it exist on live production?" as an open Business Confirmation question, not assumed either way. **That question is now answered: a fresh read-only request to `https://way2allah.com/fatwa-today.htm` returns a genuine Apache 404 — the same route already classified `MIGRATED_ALREADY_CORRECT` in entry #43 has, in fact, never been reachable on real production.** Broader spot-check (read-only): `modules.php` itself (a *different* dispatcher than `new_modules.php`) is ALSO confirmed absent from production — `https://way2allah.com/modules.php?name=Khotab` and 4 other `name=` values all 404 identically, while URLs whose `.htaccess` target is a real, direct PHP file (`khotab/day.php`, `categories/tree.php`, `channels/channels.php`, `anasheed/group.php`) all return real 200s. This is a sitewide fact about production's current state, not scoped to Fatwa. **Not investigated further here per the owner's own explicit "not a broad sweep" instruction** — recorded as a confirmed fact for whoever picks up IF-026's open item next, not chased into the other ~128 affected rules.
- **Practical consequence for Part A:** there is no live legacy render of `fatwa-today.htm` to browser-compare against — production 404s it too. Markup restoration below is therefore sourced entirely from `fatawa/fatwa-today.php` (362 lines, re-read in full) and its `get_all_questions_date()` per-row builder (`fatawa/functions.php:454-522`), not validated against a live screenshot, disclosed explicitly rather than silently substituting a different page as "close enough."
- **Part A — `fatwa-today.htm` visual/structural parity restored (`resources/views/fatawa/day.blade.php`, full rewrite):**
  - Real `.portlet.box.blue` wrappers/captions/icons for both the "فتاوى مختارة" featured block and the results block (previously bare `<ul><li>`/`<h2>`).
  - Featured block's real `.thumbs.col-xs-6` grid (previously a plain list).
  - The "تقويم الطريق إلى الله" calendar block, including its ~180-line client-side JS widget, reproduced verbatim — a deliberate, disclosed change from the controller's own prior documented decision not to reproduce it. Judgment: the widget is pure presentation with zero new data dependency (doesn't touch the still-correctly-unregistered `fatwa-date-*` route — clicking a day still 404s, which, per the finding above, now provably matches real legacy production's own current behavior too, not just a Laravel-side gap).
  - The real per-row `<tr>` structure (question+author two-column header, "مكان إصدار الفتوى" channel block) — this uncovered one genuine, small DATA-layer gap: legacy's `get_all_questions_date()` runs a second query per row to check whether `channel_id` resolves to a real `nuke_sat_channels` row, falling back to "بدون قناه" if not; the prior Laravel implementation didn't reproduce this metadata block at all. Added a `LEFT JOIN nuke_sat_channels` to `ContentListingService::fatwaQuestionsByDate()` (one extra join + one extra selected column, `channel_exists_id`) — reproduces the exact existence-check semantics (a `channel_id` pointing at a deleted channel correctly falls back, not just a `channel_id != 0` truthiness proxy). This is the one query change in this pass; it adds previously-unreproduced real legacy metadata, it does not alter any already-correct behavior.
  - Real empty-state row (`لا تـوجـــد فتاوى مضافـــة حاليا لهذا التصنيف`, legacy's own genuinely-inconsistent `colspan="5"` on a single-`<td>` row layout — preserved as-is, not "fixed").
  - Real per-row date span confirmed commented-out/dead in legacy source itself — correctly not rendered.
  - No breadcrumb: confirmed `fatwa-today.php` never calls `page_bar()`/`title()`/`breadcrumb()` (those exist in `fatawa/functions.php` for other pages only) — `<x-page-chrome>` correctly not used.
  - Legacy's own hand-rolled `pagination()` (URL-string-splicing) explicitly NOT ported — that's data/URL-generation logic, not markup; the existing, already-correct `$questions->links()` is kept, only its PLACEMENT is restored (rendered twice — above and below the table, matching legacy's own double call).
- **Part B — `khotab-fatwa-{id}.htm` re-traced at the family level.** See IF-055's reconciliation note for the full evidence trail (broader case-insensitive/whole-repo search, the one coincidental `khotab_id` form-field match traced and ruled out, 5 representative production IDs tested read-only, byte-identical 404s). **Classification unchanged: SOURCE_UNRECOVERABLE — now evidenced at the family level, not just for id=17.** No PARTIALLY_RECOVERABLE branch found; nothing implemented.
- **Tests:** `FatwaDayControllerTest` — 8/8 passed (2 new: restored-markup structural assertions, empty-state assertion; existing 6 updated only where the new `nuke_sat_channels` fixture/`date` view variable were needed, no assertion on already-correct behavior removed). Full `Content` module: 705/706, 1 pre-existing unrelated skip. Full suite: 960/961, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **Migration-completeness impact: none. `MIGRATION_COMPLETE` (entry #39) is not reopened.** Part A is a markup restoration to an already-correctly-implemented route (no new capability, no query/data behavior change except the one disclosed additive channel-existence join). Part B's classification is unchanged, just more broadly evidenced.
- **What changed:** `resources/views/fatawa/day.blade.php` (full rewrite), `app/Domain/Content/Services/ContentListingService.php` (`fatwaQuestionsByDate()` — added the channel `LEFT JOIN`), `app/Domain/Content/Http/Controllers/FatwaDayController.php` (docblock updated, `date` added to the view payload), `tests/Feature/Content/FatwaDayControllerTest.php` (+2 tests, fixture/payload updates), `docs/implementation-findings.md` (IF-055 reconciliation note). No other route/controller/page touched — `khotab-fatwa-{id}.htm` remains unimplemented (nothing to implement), no unrelated fatawa/khotab file changed. `legacy-project` confirmed unchanged. No production data accessed or modified — all production checks were read-only GETs.
- **Owner approval (2026-08-28) and closure:** Approved as-is, with one wording correction actioned before closing — `fatwa-date-{d}-{m}-{y}-{page}.htm` had drifted into `FatwaDeadRoutesTest.php`, a file whose own docblock declares its contents "confirmed-dead." That framing doesn't fit this route (a real `.htaccess` rule exists, `fatwa-today.php` is real and fully migrated; only the `d`/`m`/`y`-parameter shape and its calendar-widget link generator are unbuilt) — corrected by moving its test out to `FatwaDayControllerTest.php`, where an equivalent, already-correctly-neutrally-worded test already lived, and leaving an explanatory comment in `FatwaDeadRoutesTest.php` for why. `khotab-fatwa-{id}.htm` stays `SOURCE_UNRECOVERABLE` at the family level, no compatibility route added, per explicit owner instruction. **This focused recovery is now CLOSED.** Full suite and PHPStan re-run after the wording fix, both clean.
- **What changed (closure pass):** `tests/Feature/Content/FatwaDeadRoutesTest.php` (`fatwa-date-*` test removed, explanatory comment added), `tests/Feature/Content/FatwaDayControllerTest.php` (the equivalent, already-present test's description clarified). No production behavior changed by this pass — test/documentation wording only.

---

## 45. Fatwa Calendar Visual Dependency Audit — real missing stylesheet found and fixed, `fatawa/css/new-style.css` reachable via a narrow symlink

- **Trigger:** owner reported the restored "تقويم الطريق إلى الله" calendar (entry #44) renders functionally but visually unstyled/raw, and asked for a focused dependency trace before any CSS was written.
- **Root cause (category A — missing legacy stylesheet), proven not guessed:** `fatwa-today.php:20-22` itself explicitly `<link>`s `fatawa/css/new-style.css` via its own `$header['css']['custom']` mechanism — a real, 656-line file (`legacy-project/fatawa/css/new-style.css`) containing the calendar's entire actual styling (`.calendar`, `.calendar-ympicker`, `.calendar-header`, `.calendar-days`, `.calendar-body`) plus, incidentally, the `.fatawa-mokhtara`/`.calendar-block` portlet background/border and `.pagination` styling already relied on by other elements of the same restored page. Entry #44's parity pass ported the inline `<style>` block from the bottom of `fatwa-today.php` but never traced/wired up this separate external `<link>` — a genuine miss, not a deliberate omission. No image/font `url()` dependency in the stylesheet (grepped, none found); no additional JS/plugin dependency (the calendar widget only needs jQuery, already loaded sitewide).
- **Why it was unreachable:** no `public/fatawa` path existed at all locally. This is the SAME already-documented `ASSET_UNREACHABLE_LOCALLY` limitation independently affecting 6 OTHER fatawa pages (`topics-index`, `topics-show`, `question-all`, `channels-index`, `channel-show`, `questions`, each with their own pre-existing comment saying so) — confirmed genuinely systemic, not new. Per the owner's explicit "not a broad sweep"/"do not touch unrelated pages" instruction, **only `fatwa-today.htm` was fixed; the other 6 pages' identical gap is left as-is**, flagged here for a future, separately-scoped pass.
- **Fix (smallest fix, real dependency restored, not duplicated CSS):** `public/fatawa/css -> ../../../legacy-project/fatawa/css`, a narrow, single-subdirectory symlink — matching this codebase's own existing `public/gallery/lightbox` precedent exactly, not the whole `fatawa/` tree (which also contains live legacy PHP scripts that must stay unreachable/unexecuted through the new site). `resources/views/fatawa/day.blade.php` now `<link>`s `/fatawa/css/new-style.css` in `@push('styles')`, before the inline `<style>` block, matching legacy's own load order. This stylesheet load is page-scoped (`@push`), not added to the shared layout — no impact on any other migrated page verified (spot-checked `fatawa-categories.htm`/`fatawa-authors.htm`/`fatawa.htm`/`khotab-video-today.htm`, all unaffected, still 200).
- **Verified live:** `GET /fatwa-today.htm` → 200, contains the `<link>`; `GET /fatawa/css/new-style.css` → 200, `Content-Type: text/css`, real content, against the local dev server (a real HTTP round trip through the actual webserver, not just Laravel's router — see the test note below for why the test itself checks the filesystem contract instead).
- **`fatwa-date-*` behavior: unchanged.** No route registration, no query/data logic touched by this pass — CSS/reachability only.
- **Tests:** `FatwaDayControllerTest` — 9/9 passed (1 new: asserts the `<link>` is emitted and the symlink resolves to real calendar CSS on disk — via filesystem checks, not `$this->get()`, since static files aren't routed through Laravel's kernel in feature tests, only served directly by the real webserver). Full suite: 960/961, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **Migration-completeness impact: none.** A CSS-reachability fix to an already-correctly-implemented, already-approved page — no new capability, no data/query change.
- **What changed:** `public/fatawa/css` (new symlink), `resources/views/fatawa/day.blade.php` (+1 `<link>`, comment updated), `tests/Feature/Content/FatwaDayControllerTest.php` (+1 test). No other file touched. `legacy-project` confirmed unchanged. No production accessed or modified.

---

## 46. Fatwa Date Route Completion — `fatwa-date-{d}-{m}-{y}-{page}.htm` implemented; `khotab-fatwa-{id}.htm` re-confirmed SOURCE_UNRECOVERABLE, unchanged

- **Trigger:** owner asked to complete the previously out-of-scope `fatwa-date-*` route family, and separately re-verify (not blindly re-close) `khotab-fatwa-{id}.htm` stays 404 after the change.
- **Supersedes, does not rewrite, entry #19's original decision.** Entry #19 correctly recorded, at the time, that no file among the 16 `fatawa/` files reads `$_GET['d']`/`['m']`/`['y']` — that fact is unchanged and re-confirmed again this pass. What changed is the evidentiary basis for implementing it anyway (see `FatwaDayController`'s own docblock for the full chain): both `.htaccess` rules dispatch the identical `op=day` operation, differing only in parameter *source*; the calendar widget's own real, unmodified, already-ported generated links build exactly this URL shape when a day is clicked, unambiguously proving intent; and a repo-wide grep found `$offset` (pagination state) is ALSO never set anywhere in the 16 files, meaning parameter normalization for this operation happened outside those 16 files — `new_modules.php` is the only candidate site (every `op=day` rule routes through it), but it doesn't exist anywhere to trace, not in this codebase, not on real production (confirmed directly, entry #44).
- **Precision on what is and isn't proven (tightened per owner instruction, entry #47):** the `d`/`m`/`y`→date *intent* and the exact generated URL shape are source-backed — both cited directly to real, still-present legacy source (the `.htaccess` rule itself and the calendar widget's own generated href). The *actual dispatcher normalization mechanism* `new_modules.php` used is **not literally recoverable** — no version of that file exists anywhere to read. What's implemented is an **evidence-based reconstruction** of that missing step (reusing `fatwa-today.php`'s own proven `$_GET['date']` normalization algorithm as the most defensible available basis), not a traced port of `new_modules.php`'s real code. This distinction is stated explicitly rather than left to be inferred from "evidentiary basis" language alone.
- **Implemented:** `fatawa/day.blade.php`'s `FatwaDayController` gained a `date(int $day, int $month, int $year, int $page, ...)` action alongside the existing `index()`, both delegating to one shared private `render()` — "one shared Fatwa day rendering path," per the owner's own preferred shape, not a duplicated controller/view. `d`/`m`/`y` are normalized via the exact same `strtotime()`+`date('Y-m-d', ...)` round-trip `fatwa-today.php:16` already performs — real, verified PHP 8.3 behavior reproduced exactly: an impossible date (`31-2-2026`) permissively rolls over to `2026-03-03`; a genuinely unparseable one (`month=13`) falls back to the Unix epoch (`date('Y-m-d', false)` = `1970-01-01`, verified directly), naturally rendering the real empty-state row rather than any invented validation/rejection.
- **Pagination URL contract restored, for BOTH `fatwa-today.htm` and `fatwa-date-*` together (owner's explicit instruction — "It is NOW in scope"):** the prior pass's `$questions->links()` (Laravel's default, `?page=N` query strings) is replaced by a new `fatawa/partials/day-pagination.blade.php`, reproducing legacy's real `pagination()` markup/windowing (`fatawa/functions.php:708-751`) — including its own genuine off-by-one `$num = intval($count/$perpage)+1` quirk, preserved not "fixed" — with real pretty-URL-per-page generation via a `$pageUrl` closure the controller supplies (`route()`-based, correct for either context: `/fatwa-today-{page}.htm` or `/fatwa-date-{d}-{m}-{y}-{page}.htm`). This was necessary, not scope creep: `new-style.css`'s own `.pagination`/`.page_link`/`.prevnext`/`a.active` rules are written for this exact markup shape, not Laravel's default pagination view — using anything else would have repeated the same "looks unstyled" defect entry #45 just fixed, for pagination specifically.
- **One real bug found and fixed as a direct consequence of this pass, not a separate task:** the calendar's "future date" cutoff (`$calCutoff` in `day.blade.php`) previously computed from `$calDate` (the page's OWN date) rather than the real current date — silently correct only because "today" was the only date this page could ever represent before this pass. Legacy's own `fatwa-today.php:176` computes this from bare `date('m-d-Y')` (always real today, never the page's `$date`) — fixed to match, using `now()` instead of `$calDate`.
- **Calendar day-link generation: no JS changes needed.** The already-ported calendar widget (entry #44, verbatim from `fatwa-today.php`'s own inline `<script>`) already builds `/fatwa-date-{day}-{month}-{year}-1.htm` links client-side — it was correct all along, simply hitting a 404 until this route existed. Confirmed working end-to-end against the local dev server.
- **`khotab-fatwa-{id}.htm` — re-verified, unchanged.** No route/behavior/redirect touched. Confirmed no accidental collision: the new `fatwa-date-{day}-{month}-{year}-{page}.htm` route pattern (4 numeric segments) cannot match `khotab-fatwa-{id}.htm` (a `khotab-` prefix, different word entirely) under any circumstance — verified live, still 404. `SOURCE_UNRECOVERABLE` classification (entry #43/#44, IF-055) stands; no new evidence about `khotab-fatwa-*` was found incidentally during this trace.
- **Tests:** `FatwaDayControllerTest` — 13/13 passed (6 new: exact-date filtering, permissive-rollover normalization, epoch-fallback empty-state, historical-date page-2 + correct pretty-URL pagination links, `fatwa-today.htm`'s own pagination links also now pretty-URL-shaped). `KhotabDeadRoutesTest`/`FatwaDeadRoutesTest`: 5/5, unaffected. Full `Content` module: 709/710, 1 pre-existing unrelated skip. Full suite: 964/965, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **Migration-completeness impact:** `fatwa-date-{d}-{m}-{y}-{page}.htm` moves from `LEGACY_RULE_NO_CONFIRMED_CODE` (entry #19's original classification) to `CONFIRMED_MIGRATION_GAP → FIXED`. This is a genuine reopening of a previously out-of-scope item, recorded explicitly per the owner's own instruction, not silently folded into `MIGRATION_COMPLETE` (entry #39) without comment — `MIGRATION_COMPLETE` itself is unaffected in scope (this was already a disclosed, named open item, not a hidden gap discovered after the fact).
- **What changed:** `app/Domain/Content/Http/Controllers/FatwaDayController.php` (new `date()` action + shared `render()`, docblock rewritten), `routes/content.php` (+1 route), `resources/views/fatawa/day.blade.php` (pagination partial include, `$calCutoff` bug fix, doc comment updated), `resources/views/fatawa/partials/day-pagination.blade.php` (new), `tests/Feature/Content/FatwaDayControllerTest.php` (6 new tests, 2 existing updated for the new `pageUrl` view variable), `tests/Feature/Content/FatwaDeadRoutesTest.php`/its own `fatwa-date-*` reference already moved out in entry #44's closure — unaffected further here. No other route, controller, or unrelated fatawa/khotab page touched. `legacy-project` confirmed unchanged. No production accessed or modified — only read-only local dev-server checks were performed for this pass (no new production requests were needed; entry #44 already established the relevant production facts).

---

## 47. Fatwa Pagination Closure Check — `fatwa-today-{page}.htm` confirmed real/source-backed and correctly round-tripped; documentation wording tightened

- **Trigger:** entry #46 reported that the new pagination partial emits `/fatwa-today-{page}.htm` links but never separately traced/verified that URL family's own `.htaccess` backing or confirmed a real page-2 round trip (only that the LINK TEXT appeared, not that visiting it worked correctly) — owner asked to close that gap before considering the pass done.
- **`fatwa-today-{page}.htm` — confirmed real, not invented.** `.htaccess:283`: `RewriteRule ^fatwa-today-([0-9]*).htm new_modules.php?name=Fatwa&op=day&page=$1 [L]` — a real, distinct rule from `.htaccess:281`'s bare `fatwa-today.htm` (`page=1` hardcoded), both already correctly registered (`fatawa.day.today`/`fatawa.day.today.paged`) since entry #19's original implementation — unaffected by entry #46's changes, only its pagination-LINK generation was. Route audit (`php artisan route:list`): both present alongside `fatawa.day.date` and the typo redirect, no collisions.
- **Local HTTP + round-trip verification, not just 200:** `/fatwa-today.htm`, `/fatwa-today-1.htm`, `/fatwa-today-2.htm`, `/fatwa-date-16-8-2026-1.htm` all `200` against the local dev server. A new test (`FatwaDayControllerTest`) inserts 30 real fixture rows and confirms `/fatwa-today-1.htm` shows rows 1-25 (not row 26) and links to `/fatwa-today-2.htm`, while `/fatwa-today-2.htm` shows row 26 (not row 1) and its own "first page" link correctly resolves to the canonical `/fatwa-today.htm` (not `/fatwa-today-1.htm` — both are real registered routes, but the partial's page-1 special-case is confirmed to emit the canonical shape). No fix was required — the pagination partial's existing `$pageUrl` closures were already correct; this entry closes a verification gap, not a code gap.
- **Documentation wording tightened, per explicit instruction — `FatwaDayController`'s docblock and entry #46 above both now state explicitly, not just implicitly:** (1) the `d`/`m`/`y`→date *intent* and generated URL shape are source-backed (cited directly to the `.htaccess` rule and the calendar widget's real generated href); (2) the *actual `new_modules.php` normalization mechanism* is NOT literally recoverable — no version of that file exists anywhere; (3) the implemented normalization is an **evidence-based reconstruction** (reusing `fatwa-today.php`'s own proven `$_GET['date']` algorithm), explicitly NOT a traced port of `new_modules.php`'s real code. No implementation was undone — the trace didn't contradict it, only underspecified its own epistemic status.
- **Tests:** `FatwaDayControllerTest` — 14/14 passed (1 new). Full suite: 965/966, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **What changed:** `app/Domain/Content/Http/Controllers/FatwaDayController.php` (docblock wording only, no logic change), `docs/decision-log.md` (this entry + a precision note appended to entry #46, not rewritten), `tests/Feature/Content/FatwaDayControllerTest.php` (+1 test). No route, controller logic, or view markup changed. `legacy-project` confirmed unchanged. No production accessed — only local dev-server and `.htaccess`-file checks.
- **Pass status: CLOSED.**

---

## 48. `khotab-fatwa-{id}.htm` Author Route Reconciliation — real generator found, prior "no generated link" claim corrected, classification confirmed unchanged (SOURCE_UNRECOVERABLE)

- **Trigger:** owner observed a real, live generated `/khotab-fatwa-17.htm`/`/khotab-fatwa-79.htm` link by clicking an author on `/fatawa-authors.htm`, and asked to re-open (not assume valid) the prior `SOURCE_UNRECOVERABLE` closure, since prior passes claimed no generator existed.
- **The generator, found and traced:** `khotab/authors.php:80` — `href="khotab-<?php echo $op.'-'.$Author->id;?>.htm"` — builds this exact URL for EVERY op, including the real `else` branch (`$op='fatwa'`, `authors.php:22-27`, reached by `fatawa-authors.htm` → `khotab/authors.php?op=fatwa`). `{id}` is `nuke_islamic_authors.id` — proven directly from the query variable in the same loop, not inferred. Laravel's `resources/views/khotab/authors.blade.php:62` reproduces this exactly (`/khotab-{{ $op }}-{{ $row->author->id }}.htm`) — confirmed byte-faithful to legacy, NOT a migration-introduced bug (ruling out outcomes C/D from the owner's own framework).
- **Why the previous 2 passes' literal-string greps found nothing — a real methodological gap, corrected explicitly, not silently:** `khotab-fatwa` never appears as a literal substring anywhere in legacy source — it's assembled at runtime from `$op` (a variable) + a hardcoded author id. A grep for the final string can never find a URL built from a runtime variable; only reading the generating file's own control flow finds it. This is now the second time this project has hit this exact class of miss (the first was `fatawa-today.htm`'s typo, decision-log #43/44, though that one WAS a literal string — this is a distinct, worse case: no literal string ever exists to grep for at all).
- **Full evidence trace, both directions:** `.htaccess` still has exactly 3 rules for `khotab/author.php` (`khotab-video-*`/`khotab-audio-*`/`khotab-pdf-*` — re-confirmed, no 4th). `khotab/author.php` itself — the one file that could theoretically implement a "fatwa" detail page — treats any unrecognized `op`, `fatwa` included, as `pdf` (its own real `else` branch, `author.php:21-24`), never as a distinct listing. Two real `nuke_islamic_authors` rows verified read-only against `olddb`: id 17 = الحويني (fatwa count 12), id 79 = الحنبلي (fatwa count 449) — both real people with real fatwa-video counts, both correctly, faithfully still 404 on both local and (already established, entry #43) production.
- **Neither of the owner's 2 outcomes literally applies — a third, fully-evidenced one does.** Not OUTCOME A (no implementing code exists anywhere to recover, including in the one file that would need it). Not OUTCOME B either — Laravel isn't generating a "wrong" URL; it's correctly, faithfully reproducing a real, live, currently-unfixed LEGACY authoring bug: `authors.php`'s link-generation loop was never restricted to the 3 ops its own detail page (`author.php`) actually supports. No redirect target is defensible: `khotab-pdf-{id}.htm` (what `author.php` would silently fall back to) shows unrelated PDF content, not fatwa videos — sending real users there would show wrong content, worse than the current 404. Per the owner's own explicit rule ("Do NOT create a route just to make the current broken link return 200"), nothing was implemented.
- **Classification: SOURCE_UNRECOVERABLE — confirmed unchanged, now via a fully closed evidence loop** (a real, identified generator; a real, confirmed, complete absence of implementing code) rather than the prior open "nothing found" one. This is a strengthening of the same practical conclusion, not a reversal — exactly the kind of outcome the owner's "prove it, don't assume" instruction was designed to either confirm or overturn; here it confirms, with materially more depth.
- **`KhotabDeadRoutesTest.php` updated, not silently left wrong:** its docblock previously stated "no generated link... anywhere in legacy source" — corrected in place to describe the real generator and why the classification still holds, plus a second real ID (`khotab-fatwa-79.htm`) added alongside 17.
- **Tests:** `KhotabBrowsingControllersTest` — 56/56 passed (2 new: `fatawa-authors.htm` generates the real `khotab-fatwa-{id}.htm` link byte-faithfully, and the real `fatwa > 0` `WHERE` filter is reproduced). `KhotabDeadRoutesTest` — 1/1 (both ids). Full suite and PHPStan re-run below. Live-verified against the local dev server: `/fatawa-authors.htm` genuinely emits `/khotab-fatwa-17.htm` among its real links; visiting it is a genuine `404`.
- **Migration-completeness impact: none.** No capability existed to lose — this closes an evidence gap in the audit trail, not a functional gap in the product. `MIGRATION_COMPLETE` (entry #39) unaffected.
- **What changed:** `tests/Feature/Content/KhotabBrowsingControllersTest.php` (+2 tests), `tests/Feature/Content/KhotabDeadRoutesTest.php` (docblock corrected, +1 id), `docs/implementation-findings.md` (IF-055, second reconciliation note). No route, controller, or view changed — the existing generator and its target's absence were both already correct; only the documentation and test evidence needed correcting. `legacy-project` confirmed unchanged. No production accessed or modified — the two `olddb` lookups were read-only against the local MySQL mirror already established elsewhere in this project, not production.

---

## 49. `khotab-fatwa-{author}.htm` — BUSINESS_REPAIR_LOW_RISK compatibility redirect implemented, explicitly NOT legacy parity

- **Full progression, preserved not rewritten (owner requested this exact trail be visible):**
  1. Originally classified `SOURCE_UNRECOVERABLE` (IF-055) — no `.htaccess` rule, no generated link found by literal-string grep.
  2. Owner observed a real, live generated link by clicking through `/fatawa-authors.htm`; re-investigation found the actual dynamic generator (`khotab/authors.php:80`) — literal-string grep had missed it because the URL is assembled from a runtime variable (`$op`), never appearing as a literal substring in source (entry #48).
  3. Full trace proved the target was never implemented: `.htaccess` has exactly 3 rules for `khotab/author.php` (video/audio/pdf, no fatwa); `khotab/author.php` itself treats any unrecognized op as `pdf`. Confirmed a real, live, currently-unfixed **legacy authoring bug**, not an unrecovered feature — classification stayed `SOURCE_UNRECOVERABLE`, now via a closed evidence loop (entry #48).
  4. A design-only reuse audit (no code changed) found `ContentListingService::fatwaGeneralQuestionsByAuthor()` / `FatwaAuthorController::show()` / `/auther-questions-{author}.htm` already implement, correctly and completely, the exact same "this author's fatwas" content — real, source-backed (`fatawa/functions.php:622-634`), already tested. Classified `BUSINESS_REPAIR_LOW_RISK`: pure route/controller/view wiring over existing proven logic, not new business/query behavior.
  5. **Owner approved the repair.** Implemented this entry.
- **What was implemented:** `FatwaAuthorController::redirectFromKhotabFatwaUrl(int $author): RedirectResponse` — a new, single-purpose action returning `redirect()->route('fatawa.author.show', ['author' => $author])`. Registered as `Route::get('/khotab-fatwa-{author}.htm', [FatwaAuthorController::class, 'redirectFromKhotabFatwaUrl'])->whereNumber('author')->name('fatawa.author.khotab-fatwa-compat-redirect')`. Resolved via the named route (`route('fatawa.author.show', ...)`), not a hardcoded destination string — stays correct if that route's own URI ever changes, matching the owner's explicit instruction.
- **Why redirect over duplicate rendering:** the canonical page already exists, is already correct, and is already tested — a second rendering path would duplicate `fatwaGeneralQuestionsByAuthor()`/`FatwaAuthorController::show()`/`fatawa/author-show.blade.php` for zero behavioral benefit. `Author::findOrFail()`'s existence check is deliberately NOT duplicated in the redirect action either — a nonexistent author id (e.g. 999999) redirects unconditionally, and the real 404 correctly happens on the canonical page, matching this project's own "don't duplicate logic" standard elsewhere (e.g. the `fatawa-today.htm` typo redirect, entry #43/44, has the same shape).
- **Explicitly NOT legacy parity — stated plainly, not implied:** legacy never served content at this URL under any circumstance. This is a deliberate, owner-approved product repair of a currently-broken, currently-live link, using content legacy did successfully implement elsewhere (the author's own fatwa-question list) — not a reconstruction of anything legacy itself ever built at `khotab-fatwa-{id}.htm`.
- **Tests:** `FatwaAuthorControllerTest` — 8/8 passed (5 new: redirect target for id 17 and 79 both verified via the named route AND the literal resulting URL, 302 status, non-numeric segment 404s at the route level, no duplicated existence check for a nonexistent author). `tests/Feature/Content/KhotabDeadRoutesTest.php` — deleted; its single obsolete assertion (khotab-fatwa-* stays 404) is superseded by the redirect tests above, and its historical narrative is preserved here and in IF-055 rather than in a test docblock. `Khotab`+`Fatwa` filtered run: 313/313. Full suite: 971/972, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **Manual verification:** live against the local dev server — `/fatawa-authors.htm`'s real generated link (`/khotab-fatwa-17.htm`) → `302` → `/auther-questions-17.htm` → `200`, `<title>الشيخ أبو إسحاق الحويني - الطريق إلى الله</title>` (the correct author). Same for id 79.
- **Migration-completeness impact: none — this is explicitly a product repair, not a migration-completeness item.** `MIGRATION_COMPLETE` (entry #39) unaffected; this was never counted as a migration gap (entries #43/#48 already established that no legacy capability was lost).
- **Scope discipline confirmed:** no channel metadata added (explicitly out of scope for this task); `/auther-questions-*`'s own controller/service/view untouched (all 3 pre-existing tests for it still pass unmodified); no second controller/rendering path created.
- **What changed:** `app/Domain/Content/Http/Controllers/FatwaAuthorController.php` (+1 action), `routes/content.php` (+1 route), `tests/Feature/Content/FatwaAuthorControllerTest.php` (+5 tests), `tests/Feature/Content/KhotabDeadRoutesTest.php` (deleted). No `ContentListingService`, no Blade view, no other controller touched. `legacy-project` confirmed unchanged. No production accessed or modified.

---

## 50. Author Questions Page — visual parity restored; `auther-all-fatawa-{author}-{g_q_id}.htm` traced and classified `LEGACY_BROKEN_LINK`, reported not repaired

- **Trigger:** owner found `/auther-questions-17.htm` (already-migrated, working page) visually plain, and separately found one of its own generated links (`/auther-all-fatawa-17-3710.htm`) 404ing — asked for one evidence-driven investigation covering both.

### Part A — visual parity, implemented

- **Full re-read of `fatawa/auther_profile.php`** (98 lines) and `get_all_auther_questions()` (`fatawa/functions.php:622-670`, the real per-row builder). Real structure: `page_bar_auther()`'s own hand-rolled breadcrumb (icon *before* each `<li>`'s link, "قائمة الدعاة" as the first/home item — a genuinely different shape than the shared `<x-page-chrome>` component, correctly not reused here); a `.portlet.box.blue` wrapper (icon `fa-question`, caption "الأسئلة التى أفتى بها الشيخ" — legacy's own spelling, preserved); a real `<table id="sample_5">` with a `table_order` numbered column (`$i=$offset+1`); 2 sidebar portlets (`fa-download`/`fa-plus`).
- **A real, concrete link bug found and fixed as a direct consequence of this trace, not invented:** the sidebar's `mostDownloaded`/`mostRecent` items previously linked to `/fatawa-download-{id}.htm` — legacy's real `mostdownload()`/`recentlyadd()` (`functions.php:688`/`701`) link to `/fatawa-all-{general_id}.htm#{id}` with `class="add"` instead. `ContentSidebarWidget` already selected `general_question_id` (no service change needed); only the view's link construction was wrong. Fixed.
- **Pagination:** legacy calls the same real `pagination($count)` function `fatwa-today.php` calls — reused `fatawa.partials.pagination` (renamed from `day-pagination.blade.php`, since it's now proven generic across 2 real pages) via a `$pageUrl` closure mirroring `FatwaDayController`'s own pattern (`fatawa.author.show`/`fatawa.author.show.paged`, real pretty-URL contract, not `?page=`).
- **CSS:** page never loaded `fatawa/css/new-style.css` (confirmed via grep) — the symlink infrastructure already existed (decision-log #45), just needed wiring here too.
- **Deliberately not reproduced, disclosed not silent:** `page_bar_auther()`'s leading `<h1 style=""></h1>` (genuinely empty, zero visible effect); an empty-state message (`get_all_auther_questions()` has no such fallback, unlike `get_all_questions_date()` — a real, confirmed absence); an author photo (`auther_profile.php` never renders one, confirmed by the full source read).

### Part B — `auther-all-fatawa-{auther_id}-{g_q_id}.htm`, traced and classified, not implemented

- **Exact RewriteRule:** `.htaccess:298` — `auther-all-fatawa-([0-9]*)-([0-9]*).htm → modules.php?name=Fatwa&op=all_fatawa_for_auther&auther_id=$1&g_q_id=$2`. Both ids proven, not inferred: `functions.php:647` — `auther-all-fatawa-{$auther_id}-{$value->id}.htm`, where `$value` iterates `nuke_fatwa_general_questions` rows (`functions.php:633`'s own `SELECT id,question_text,topic_id FROM nuke_fatwa_general_questions ...`). For the concrete example: `17` = `nuke_islamic_authors.id` (already established, entry #48), `3710` = a real `nuke_fatwa_general_questions.id` this author has answered.
- **Exhaustive search for an implementation:** zero files among the 16 `fatawa/` files read `$_GET['g_q_id']` (grepped directly); no function named for `all_fatawa_for_auther` exists among `functions.php`'s 27 defined functions. `modules.php` itself (the dispatcher this op routes through) is confirmed absent both from this codebase and real production (entry #44).
- **Classification: `LEGACY_BROKEN_LINK`.** A real, live generator (`get_all_auther_questions()`, still executing today, still emitting this exact link on every author's real page); a real, matching `.htaccess` rule; genuinely no implementation anywhere to determine what the page was actually meant to show (specifically: whether `auther_id` was meant to filter the general question's answers to just this author, or was purely contextual/breadcrumb data — unprovable either way). Distinct from `khotab-fatwa-*`'s case (entry #48), where the equivalent canonical page was proven to show identical content — here, `fatawa-all-{g_q_id}.htm` (`FatwaQuestionController::showAll()`, already correct) is a strong candidate reuse target but NOT proven equivalent, since it shows every author's answers, not necessarily scoped the way `auther_id` might have intended.
- **Not implemented, per the task's own explicit rule** ("Do not automatically repair it. Report it first unless an already-approved low-risk canonical repair exists.") — no such approval exists yet for this specific URL; `khotab-fatwa-*`'s prior approval doesn't automatically extend here, since the two cases' evidence shapes differ (that one had zero ambiguity about the correct target; this one has a real, unresolved one). Reported for the owner's own decision, not assumed.
- **Live-verified, both examples:** `/khotab-fatwa-17.htm` → `302` → `/auther-questions-17.htm` → `200`, real content ("الشيخ أبو إسحاق الحويني"); the page's own real generated link `/auther-all-fatawa-17-3710.htm` (the exact literal example reported) → genuine `404`, matching the classification exactly, not accidentally fixed by anything else changed in this pass. Same end-to-end flow confirmed for author 79 (الحنبلي, 449 fatwas — real pagination triggers correctly).
- **Tests:** `FatwaAuthorControllerTest` — 11/11 (3 new: restored breadcrumb/portlet/table_order, corrected sidebar link shape, pretty-URL pagination contract). `Khotab`+`Fatwa` filtered run: 316/316. Full suite: 974/975, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **Migration-completeness impact: none.** Part A is a markup restoration to an already-correct page. Part B remains exactly what it was before this pass (a disclosed, reported, unimplemented item) — not newly discovered as a gap, not silently resolved.
- **What changed:** `app/Domain/Content/Http/Controllers/FatwaAuthorController.php` (docblock + `$pageUrl`), `resources/views/fatawa/author-show.blade.php` (full rewrite), `resources/views/fatawa/partials/day-pagination.blade.php` → renamed `pagination.blade.php` (+ its 2 references in `day.blade.php` updated), `tests/Feature/Content/FatwaAuthorControllerTest.php` (+3 tests). No `ContentListingService`, route, or other controller changed — `auther-all-fatawa-*` was investigated and reported, not implemented. `legacy-project` confirmed unchanged. No production accessed or modified.

---

## 51. `auther-all-fatawa-{author}-{generalQuestion}.htm` — author-scoped BUSINESS_REPAIR implemented; unscoped `fatawa-all-*` confirmed unaffected

- **Full evidence trail, preserved not rewritten (owner requested this exact history stay visible):** (1) `auther-questions-*`'s real, live `get_all_auther_questions()` genuinely generates this URL (entry #50); (2) `.htaccess:298` has a real matching rule (`op=all_fatawa_for_auther`); (3) no file among the 16 `fatawa/` files implements that op — classified `LEGACY_BROKEN_LINK`, not repaired without approval (entry #50); (4) a data audit found ~7.5% of real general questions (784 of 10,413) have answers from more than one scholar — concrete example, general question 10007 ("التصوير"), 9+ distinct scholars including author 79 — so an unconditional redirect to the unscoped `fatawa-all-*` page would discard real author context in a non-trivial minority of cases; classified `OWNER_DECISION_REQUIRED` (feasibility report, no code changed); (5) **owner explicitly selected author-scoped semantics** — implemented this entry.
- **Baseline traced first, in full:** `fatawa-all-{generalQuestion}.htm` → `FatwaQuestionController::showAll()` → `ContentListingService::fatwaQuestionsForGeneralQuestion()` → `fatawa.question-all` (the `answer2.php` owner-approved reconstruction, already a mature, fully-documented, fully-restored view — portlets, per-answer table, channel/media/download/send-to-friend actions, category-scoped sidebar).
- **Implementation — reuse, not redesign, exactly as directed:** `ContentListingService::fatwaQuestionsForGeneralQuestion()` gained one optional `?int $autherId = null` parameter (adds a single `->where('q.auther_id', ...)` via `->when()`; every existing unscoped caller is byte-for-byte unaffected — verified, all 30 pre-existing `FatwaQuestionControllerTest` cases pass unmodified). `FatwaQuestionController` gained `showAllForAuthor(int $author, int $generalQuestion, ...)`; both it and `showAll()` now delegate to one new shared private `renderAllAnswers()` (extracted, not duplicated) for every downstream decision (view count, topic/category chain, channel lookup, sidebar) — the ONLY difference between the two entry points is whether `$answers` was pre-filtered by author. **Zero changes to `fatawa.question-all.blade.php`** — the exact same view renders both the scoped and unscoped datasets, per the explicit "primary page model is still fatawa-all, do not redesign" instruction.
- **Route:** `GET /auther-all-fatawa-{author}-{generalQuestion}.htm`, both `whereNumber`, historical spelling ("auther") preserved exactly.
- **Entity semantics:** `Author::findOrFail($author)` / `FatwaGeneralQuestion::findOrFail($generalQuestion)` — invalid author or invalid general question both 404 normally, no silent fallback to the unscoped page.
- **Valid pair, no relationship (author never answered that general question) — deliberately NOT a 404:** both parent resources genuinely exist; the page renders with an empty answers area (the view's `@foreach` simply produces nothing), matching this project's own established precedent (`get_all_auther_questions()` has no invented "no results" message for the analogous case either) rather than a misleading 404 or fabricated fallback content.
- **Critical multi-author proof, live-verified:** general question 10007 has 9 distinct answering scholars. `/auther-all-fatawa-79-10007.htm` shows exactly 1 (`/auther-questions-79.htm`, no other author link anywhere on the page); `/fatawa-all-10007.htm` (unchanged) still shows all 9. Same proof captured as an automated test using fictional author names (real scholar names collide with the shared layout's sitewide `w2a_autocomplete/authors.txt` include, which contains real production names regardless of test fixtures — a real methodological trap avoided, not hit blindly: an `assertDontSee()` on a real name would have been a false-negative-proof).
- **17/1924 and 17/3710 (the exact pairs from the original investigation) both verified working, correctly scoped.**
- **Tests:** `FatwaQuestionControllerTest` — 36/36 (6 new: multi-author exclusion proof, the 2 original real pairs, invalid author 404, invalid general question 404, valid-pair-no-relationship empty render, `auther-questions-*`'s own generator still emits the historical link unaffected). `FatwaDeadRoutesTest`'s own now-obsolete `auther-all-fatawa-{id}-{id2}.htm stays 404` assertion corrected in place (moved out, explanatory comment added — same pattern already used twice before in this file, not silently deleted). `Khotab`+`Fatwa` filtered run: 321/321. Full suite: 979/980, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **Migration-completeness impact: none — explicitly a business repair, not a migration-completeness item**, same standing as entry #49's `khotab-fatwa-*` repair. `MIGRATION_COMPLETE` (entry #39) unaffected; this was never counted as a lost legacy capability (entry #50 already established that).
- **What changed:** `app/Domain/Content/Services/ContentListingService.php` (`fatwaQuestionsForGeneralQuestion()` — added the optional `$autherId` parameter), `app/Domain/Content/Http/Controllers/FatwaQuestionController.php` (new `showAllForAuthor()` + extracted `renderAllAnswers()`), `routes/content.php` (+1 route), `tests/Feature/Content/FatwaQuestionControllerTest.php` (+6 tests), `tests/Feature/Content/FatwaDeadRoutesTest.php` (1 obsolete assertion corrected). No Blade view changed — `fatawa.question-all.blade.php` is reused verbatim. `legacy-project` confirmed unchanged. No production accessed — all DB evidence for this and the prior feasibility audit came from the local `olddb` mirror, read-only.

---

## 52. Repair Batch 1 — `/recite-news.htm` implemented, `fatawa-topics` breadcrumb generator fixed; the other 3 sitewide-audit findings deliberately untouched

- **Trigger:** owner approved 2 of the 5 findings from `docs/reviews/sitewide-internal-404-audit.md` for repair — explicitly, only these 2; the `.grx` 500, the orphaned khotab series data, and the w2acd relative-nav bug were explicitly excluded from this batch.

### Part A — `/recite-news.htm` (finding #1, `MISSING_MIGRATION_ROUTE`)

- **Full trace:** the only real legacy generator is `home_functions.php:282`'s "المزيد .." link inside `list_latest_telawahs()` (the homepage's own "latest telawahs" widget, already correctly reproduced, untouched by this task). `.htaccess:332` routes through the missing `new_modules.php` dispatcher (`op=MoreTelawah`), but the real target file, `telawah/more.php` (76 lines, read in full), is fully present: 24 latest telawah items (plain inner join to groups, `ORDER BY id DESC`), a `.portlet.box.blue` table wrapper, and a single sidebar box (`most_downloaded_list()`, `telawah/functions.php:309-329` — top-10-by-hits, `w2a_open_div()`'s real `color="blue top_side"` markup, and its own `if($TotalList>0)` gate wrapping the ENTIRE box, not just its contents).
- **Reuse, not duplication:** `ContentListingService::homeLatestTelawahs()` (already built for the homepage widget at `limit=7`) is now called with `limit=24` and was extended (not forked) to select 2 extra columns (`hits`, the group's own `id`) the homepage widget never needed — confirmed via grep that `home.blade.php` doesn't read either, so this is additive, not a behavior change for the existing caller. `ContentSidebarWidget::telawahMostDownloaded()` (already built, unrelated prior caller) reused unchanged.
- **One deliberate, disclosed non-reproduction:** legacy's query is a plain inner join; `homeLatestTelawahs()` already used a `LEFT JOIN` when it was originally built (a pre-existing decision, not revisited or re-litigated by this task) — reusing it as-is was preferred over forking the query for one new caller.
- **Implemented:** `TelawahLatestController::index()` (new), `resources/views/telawah/latest.blade.php` (new, full legacy-visible markup — portlet/table/sidebar, not modernized), route `GET /recite-news.htm` named `telawah.latest`.

### Part B — `fatawa-topics` breadcrumb (finding #4, `WRONG_LARAVEL_GENERATOR`)

- **Legacy contract confirmed:** only `Route::get('/fatawa-topics-{category}-{page}.htm', ...)` (`routes/content.php`, named `fatawa.topics.show`) is registered — no 1-segment variant exists, matching `.htaccess:301-302`'s own "page defaults to 1 in the 2-parameter form" note (already documented elsewhere in this file).
- **Fix:** `resources/views/fatawa/question-all.blade.php`'s category-chain breadcrumb link now resolves via `route('fatawa.topics.show', ['category' => $category->id, 'page' => 1])` instead of a hand-built 1-segment string — resolved through the named route (not hardcoded), matching this project's own established preference. No compatibility route added for the old broken shape — not needed, the generator itself was the defect.
- **Live-verified** against a real general question with a 2-level category chain (`/fatawa-all-16473.htm`): breadcrumb now emits `/fatawa-topics-1-1.htm`/`/fatawa-topics-383-1.htm` (both `200`), the old 1-segment shape no longer appears anywhere in the output.

### Validation

- **Tests:** `TelawahLatestControllerTest` (new, 6 tests: 24-item limit + newest-first ordering, hit count, sidebar reuse, the sidebar's whole-box-gone-when-empty behavior, CSS load), `HomeControllerTest` (+1: homepage still generates the `/recite-news.htm` link, unaffected), `FatwaQuestionControllerTest` (+1: breadcrumb emits the corrected 2-segment URL, the old 1-segment shape is gone, both corrected links independently resolve `200`). `Telawah`+`Fatwa`+`Khotab`+`Home` filtered run: 389/389. Full suite: 986/987, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **Regression flows re-verified, all healthy, no change:** `/fatawa-authors.htm`, `/khotab-fatwa-17.htm` (302), `/auther-questions-17.htm`, `/auther-all-fatawa-17-1924.htm`, `/fatawa-all-1924.htm`, `/fatwa-today.htm`.
- **What explicitly did NOT change, per direct instruction:** the `.grx` iconv 500 (finding #5), the 2 orphaned-series khotab items (finding #2), the w2acd relative-nav legacy bug (finding #3) — all 3 remain exactly as documented in the audit, untouched.
- **Migration-completeness impact: none.** `/recite-news.htm` was already-disclosed as a real migration gap in the audit itself, not newly discovered here — closing it doesn't reopen `MIGRATION_COMPLETE` (entry #39), it resolves an already-tracked item. The breadcrumb fix is a bug fix to already-shipped code, not a completeness question.
- **What changed:** `app/Domain/Content/Http/Controllers/TelawahLatestController.php` (new), `resources/views/telawah/latest.blade.php` (new), `app/Domain/Content/Services/ContentListingService.php` (`homeLatestTelawahs()` — 2 extra selected columns), `routes/content.php` (+1 route, +1 use import), `resources/views/fatawa/question-all.blade.php` (1 link corrected), `tests/Feature/Content/TelawahLatestControllerTest.php` (new), `tests/Feature/Content/HomeControllerTest.php` (+1 test), `tests/Feature/Content/FatwaQuestionControllerTest.php` (+1 test), `docs/reviews/sitewide-internal-404-audit.md` (findings #1 and #4 marked repaired with exact evidence; findings #2/#3/#5 left unaltered). `legacy-project` confirmed unchanged. No production accessed or modified.

---

## 53. `khotab-series-{id}.grx` iconv failure — repaired as `BUSINESS_REPAIR_LOW_RISK`, explicitly NOT legacy parity

- **Trigger:** owner reviewed the investigation (entry #52's Repair Batch 2 groundwork) and explicitly rejected reproducing legacy's own real behavior (a silent empty-body `200`) — approved narrow normalization of exactly the proven character classes plus controlled, non-silent failure handling for anything not yet identified.
- **Legacy behavior, re-confirmed:** `functions.php:744` — `echo iconv('utf-8', 'windows-1256', $Content); exit();` — no failure handling at all. `iconv()` returning `false` on an unrepresentable character makes legacy `echo false`, i.e. an empty body, with the real headers already sent — a genuine, undesirable, silent legacy defect, not a crash.
- **Laravel's prior behavior:** the same bare `iconv()` call, but this project's own warning-to-exception escalation turned that same condition into an uncontrolled `500`.
- **Implemented — narrow, explicitly-scoped normalization, not a general sanitizer:** `CategoryDownItemsController::normalizeForWindows1256Export()` (new private method, applied to both the series title/`$folder` and each item's own title before the existing sanitization/`iconv()` call) handles exactly 3 proven classes: Arabic-Indic digits → ASCII digits (semantic value preserved); U+200F (RIGHT-TO-LEFT MARK, invisible) stripped; the `ﷺ` ligature (U+FDFA) expanded to its own plain-letter phrase "صلى الله عليه وسلم" — **verified to round-trip cleanly through Windows-1256 before this was approved**, per the owner's own explicit precondition.
- **`iconv()` deliberately kept strict** — no `//IGNORE`/`//TRANSLIT` added, per explicit instruction, so a genuinely new unsupported character surfaces as a real, loggable failure rather than silently vanishing.
- **Defensive failure handling, narrowly scoped (not a global error-handling change):** `@iconv(...)` suppresses the warning for this one call only; if `$body === false` even after normalization, `Log::error()` records the series/category id (matching this project's existing `DeploymentInstallerController` logging convention — no exception detail leaked into the response), then `abort(500)` — a controlled failure, not the old accidental crash and explicitly not the old silent-empty-body-200 either.
- **Real-world validation found exactly what this design was built to handle correctly:** re-sampling the same 150 real series found **3 NEW, previously-unidentified unsupported characters** — U+0670 (ARABIC LETTER SUPERSCRIPT ALEF), U+0654/U+0655 (combining hamza marks), U+06E1, U+0671, U+0653 — all Quranic-diacritic marks appearing in titles that quote Quranic verses verbatim with full tashkeel. **Per explicit instruction, NOT silently normalized** — the defensive handling caught all 3, logged them with their series ids (12578, 6329, 11140), and returned a controlled `500` — proving the "unexpected future character" path works exactly as designed, on real, previously-unseen data, not just in theory. Reported here, not fixed — a decision for a future pass.
- **150-series re-sample result:** 142 now succeed with a real, non-empty body (up from 129 before this repair); 5 return an empty `200` body — **confirmed, by direct query, to be genuinely empty series (zero non-hidden items) — a pre-existing, unrelated condition, not the encoding-failure path**; 3 correctly return a controlled `500` for the newly-identified diacritic characters above.
- **Healthy-file regression: proven, not just tested.** None of the 5 previously-healthy sampled series (8952, 5176, 6319, 2310, 3824) contain any of the 3 normalized character classes — confirmed by direct query — so normalization is a mathematically guaranteed no-op for them; all 6 pre-existing `CategoryDownItemsControllerTest` cases (covering headers, category filtering, Windows-1256 round-trip, title sanitization, hidden-item exclusion, 404s) pass unmodified, and live byte-size/hash stability was independently re-verified.
- **Manual verification:** `/khotab-series-15972.grx` (the original failing example) → `200`, non-empty (9,829 bytes), correct headers, decoded back from Windows-1256 to confirm the ﷺ ligature correctly became "صلى الله عليه وسلم" mid-title with everything else byte-identical to the real source data.
- **Tests:** `CategoryDownItemsControllerTest` — 12/12 (6 new: each of the 3 character classes in isolation, all 3 combined matching the real 15972 shape, the new-unsupported-character controlled-500 path using a real discovered example, the pre-existing empty-series-is-unrelated case). `Khotab`+`CategoryDownItems` filtered run: 213/213. Full suite: 992/993, 1 pre-existing unrelated skip. PHPStan: 0 errors.
- **Classification: `BUSINESS_REPAIR_LOW_RISK`, explicitly NOT recovered legacy behavior or exact parity** — stated plainly per owner instruction, not implied.
- **Migration-completeness impact: none.** A bug-fix/product-repair to an already-implemented feature, not a completeness question. `MIGRATION_COMPLETE` (entry #39) unaffected.
- **What changed:** `app/Domain/Content/Http/Controllers/CategoryDownItemsController.php` (normalization helper + defensive failure handling), `tests/Feature/Content/CategoryDownItemsControllerTest.php` (+6 tests). No other controller, service, or route touched — findings #2 (orphaned khotab series data) and #3 (w2acd relative-nav) remain untouched, per standing instruction. `legacy-project` confirmed unchanged. No production accessed — all DB evidence read-only against the local `olddb` mirror.

---

## 54. `khotab-series-{id}.grx` — remaining Quranic Unicode codepoints, `BUSINESS_REPAIR_LOW_RISK` + owner-approved extension, both implemented

- **Trigger:** a follow-up, investigation-only pass traced entry #53's 3 newly-discovered codepoints (series 12578/6329/11140) to exact characters and classifications, then simulated the fix against a broader 150-series sample and found 6 *further* unsupported codepoints outside the originally-named 3 series. Reported, not implemented, pending owner review. Owner reviewed both sets and **explicitly approved both**: the originally-scoped 6 codepoints as `BUSINESS_REPAIR_LOW_RISK`, and the additional 6 found in the broader sample as an explicitly-approved extension (owner's own message frames this as "B set is now explicitly owner-approved" — not a silent scope expansion).
- **All 12 codepoints, real-data classification:**
  - **Recomposition (not removal):** `ا`+U+0654 → `أ`; `ا`+U+0655 → `إ`; `ا`+U+0653 → `آ` — decomposed NFD hamza/maddah sequences recomposed into the precomposed letter Windows-1256 already supports. Lossless, verified round-trip.
  - **Letter substitution, exactly 2, each corpus-proven:** U+0671 (ALEF WASLA) → ا (item 100710, same corpus, spells the identical root "الَّذِينَ" with plain ا); U+06CC (ARABIC LETTER FARSI YEH) → ي (314 corpus occurrences of plain ي vs. 2 of ی, both minority occurrences confined to Quranic-verse-quoting titles from Uthmani/Persian-influenced typesetting, not a deliberate distinct-word usage).
  - **Removal, 8 exact codepoints, no letter-identity function:** U+0670 (dagger alif), U+06E1, U+0653 (standalone only — the ا+U+0653 sequence is consumed by recomposition first), U+06DA, U+06E6, U+06E5, U+06E4, U+08F1 — all Quranic recitation/annotation marks; before/after examples confirmed every base letter and word boundary intact (e.g. `هَٰذِهِ` → `هَذِهِ`, `قَدْرِهِۦ` → `قَدْرِهِ`).
- **Implemented as an explicit codepoint allowlist, not a Unicode range/category rule**, per owner instruction: `CategoryDownItemsController::LETTER_SUBSTITUTIONS` and `::REMOVABLE_MARKS` (new class constants), applied in `normalizeForWindows1256Export()` in the owner-specified order (recompose → substitute → remove marks/U+200F → digits → ﷺ expansion) — each step's character set is disjoint from the others, so this order does not itself change the result, but is followed exactly as specified. The pre-existing digit/ﷺ normalization (#53) is unchanged, not regressed.
- **`iconv()` still kept strict** — no `//IGNORE`/`//TRANSLIT`, unchanged from #53. Global error handling untouched.
- **Failure logging improved (owner-requested):** on a residual `iconv()` failure, `show()` now performs one extra pass over the series' own items to identify and log which specific `item_id` failed to encode (in addition to the existing `series_id`/`category_id`), making the next genuinely-unknown character directly diagnosable from the log alone — no content/secrets logged, matching this project's existing `Log::error()` convention.
- **Real-data verification, all 6 affected real series, hit against the live app (not simulation):** series 6329 (item 100712), 11140 (187318, 187348), 12578 (218707, 225928), 7209 (110233, 110249), 12831 (224908, 224914), 15723 (261626, 265409, 266199) — all 6 return `200`, correct headers (`Content-Type: application/force-download`, real `Content-Disposition` filename, `Content-Transfer-Encoding: binary`), non-empty bodies, and decode cleanly from Windows-1256 with every one of the 12 target items present and readable (e.g. `فِي هَذِهِ الْحَيَاةِ`, `الأعلى`, `الإخلاص`, `عَلَى قُلُوبِهِمْ`, `كَذَلِكَ ... الَّذِينَ فَسَقُواْ`, `نِحْلَةً`, `أَشْهُرٌ`, `سُبُلَنَا`, `قَدْرِهِ`/`قَبْضَتُهُ`, `أَمرِهِ`, `وَقَالَ نِسوَة فِي المَدِينَةِ`, `فَتَيَانِ`).
- **150-series re-sample (deterministic reconstruction — the exact id list from #53's own sample was not persisted to a file, so this is a fresh, disclosed sample: the 6 known reference series + a stratified every-97th-by-id spread across the full 14,552-series catalog), hit against the live app:** 150 series sampled, 147 return `200` with a non-empty body, 3 return a legitimate empty `200` body (**confirmed by direct query: series 5886/7109/15323 have zero khotab rows at all, hidden or not — a pre-existing, unrelated condition**), **0 controlled `500`s**, 0 unsupported codepoints encountered beyond the 12 now handled.
- **Healthy-file regression, proven at the item level:** of 2,535 non-hidden items across the 150 series, 16 contain one of the 12 target codepoints (all 16 now succeed); the remaining 2,519 are provably unaffected — the normalization functions are no-ops by construction when none of the target characters are present, independently confirmed by direct byte comparison (0 mismatches).
- **Tests:** `CategoryDownItemsControllerTest` — 18/18 (6 new: recomposition of all 3 hamza/maddah sequences, the 2 letter substitutions together, all 8 mark removals together, the 5 originally-failing real items together, the 7 additionally-discovered real items together, a still-genuinely-unknown character (outside this allowlist entirely) still triggering a controlled `500`; 1 pre-existing test updated since its premise — U+0670 as "not yet normalized" — no longer holds after this repair). `Khotab` filtered run: 204/204. Full suite: 998/999, 1 pre-existing unrelated skip. PHPStan: 0 errors at project level 5.
- **Classification: `BUSINESS_REPAIR_LOW_RISK` (originally-scoped 6) + owner-approved extension (additional 6) — explicitly NOT recovered legacy behavior or exact parity.** Unknown future unsupported codepoints still fail visibly (logged, controlled `500`), not silently.
- **`normalizeForWindows1256Export()` remains a bounded compatibility layer**, not a general-purpose sanitizer — every codepoint handled is an explicit, individually-justified constant, not a Unicode range/category rule; any future addition requires the same evidentiary bar (real-data trace, classification, Windows-1256 round-trip, corpus-consistency proof for letter substitutions).
- **Migration-completeness impact: none.** A bug-fix/product-repair to an already-implemented, already-repaired feature. `MIGRATION_COMPLETE` (entry #39) unaffected.
- **Sitewide audit update:** `docs/reviews/sitewide-internal-404-audit.md` finding #5 (the `.grx` iconv 500) marked fully repaired — both the original 3-character-class repair (#53) and this Quranic-codepoint extension (#54) — after validation succeeded, per owner instruction to update only after validation, not before.
- **What changed:** `app/Domain/Content/Http/Controllers/CategoryDownItemsController.php` (2 new class constants, extended `normalizeForWindows1256Export()`, item-level failure logging in `show()`), `tests/Feature/Content/CategoryDownItemsControllerTest.php` (+6 tests, 1 updated), `docs/reviews/sitewide-internal-404-audit.md` (finding #5 marked repaired). No other controller, service, or route touched. `legacy-project` confirmed unchanged. No production accessed — all DB evidence and live verification against the local `olddb` mirror / local dev server only.

---

## 55. `khotab-item-{id}.htm` — nonexistent-series 404 repaired as `MIGRATION_STRICTNESS_DEFECT`, explicitly NOT legacy parity; hidden-series branch deliberately left open

- **Trigger:** a dedicated, investigation-only pass traced the sitewide audit's finding #2 (originally 2 known items, `khotab-item-267572.htm`/`267573.htm`, classified `DATA_SPECIFIC_STALE_LINK`) end to end. Owner reviewed the investigation and **approved a repair scoped to exactly one of the 2 conditions found**: items whose `ser_id > 0` references a series row that does **not exist at all**. Explicitly excluded: items whose referenced series row exists but is `hidden` (834 items) — a separate, unresolved owner decision, since a hidden series may be an intentional editorial choice, unlike a genuinely missing row.
- **Real scope, not a 2-item edge case:** **1,349 visible items** reference **91 distinct genuinely-missing series ids** (confirmed via `LEFT JOIN ... series.id IS NULL`; the missing ids sit in an otherwise-populated id range, 15207-15300, with no recoverable trace in `nuke_islamic_khotab_backup` or `nuke_islamic_logs` — the series data itself is unrecoverable, but that is a separate question from whether the item can still render). Author/group/channel foreign keys for all 1,349 items were checked and are clean — a series-only defect, not a broader integrity problem.
- **Legacy behavior, fully traced (`khotab/item.php:62-68`, read in full):** legacy does **NOT** 404 for a missing or hidden series — it calls `w2a_header($header)` (prints the full page chrome) then `return;`, executing none of the item's own detail-rendering code and never calling `w2a_footer()` — a genuinely broken, un-footered `HTTP 200` page with an empty content area. Confirmed via `header.php`: no status-code call anywhere in `w2a_header()`. Laravel's `abort_if($series === null, 404)` is a real, controller-introduced divergence from this — but legacy's own behavior is not a standard worth reproducing either (both are broken, differently).
- **Item content is independent of series, proven not assumed:** `$series`/`$Series` is read in exactly one place in both the legacy file (an optional breadcrumb crumb) and the Laravel `khotab.item` Blade view (`item.blade.php:53-54`, already `@if ($series)`-guarded) — title, author, description, media/download links, PDF action, mirrors, comments, and every sidebar widget are drawn from the item's own row or its (valid) author/group, never from `$series`.
- **Implemented — `KhotabItemController::show()`'s series lookup refactored to distinguish the 2 conditions a single `->where('hidden', 0)->first()` call could not tell apart:**
  ```php
  $seriesRow = Series::find($khotabItem->ser_id);
  if ($seriesRow !== null) {
      abort_if((int) $seriesRow->hidden !== 0, 404); // hidden series: unchanged, still 404
      $series = $seriesRow;
  }
  // else: no row at all — $series stays null, item renders normally
  ```
  No fallback series/group is invented, no `ser_id` is rewritten, no database row is touched — the item renders with whatever valid context actually exists, per explicit instruction.
- **Real-data verification:** `/khotab-item-267572.htm`/`/khotab-item-267573.htm` → live-confirmed `200`, correct title, correct group breadcrumb crumb (group 1315 is valid), series crumb correctly absent, download/PDF/media links intact, source pages (`/`, `/dumped-lectures.htm`) unchanged. A representative 12-item sample spanning 12 distinct missing series ids (chosen via `ORDER BY RAND()` across the full 91-id set) → all `200`. A real hidden-series item (id 43627, series 3332) → still `404`, unchanged, confirming the excluded branch was not silently touched.
- **Tests:** `KhotabItemControllerTest` — 55/55 (4 new: nonexistent-series renders with content and no series crumb; download/PDF/mirrors/comments all still render for an orphan-series item; `ser_id=0` unaffected; a valid visible series still renders its crumb unchanged; the pre-existing hidden-series-still-404s test — unmodified — continues to pass, serving as the hidden-series regression guard). `Khotab` filtered run: 208/208. Full suite: 1002/1003, 1 pre-existing unrelated skip. PHPStan: 0 errors at project level 5.
- **Classification: `MIGRATION_STRICTNESS_DEFECT`** (a Laravel controller assumption stricter than legacy ever was) **with a secondary `LEGACY_DATA_INTEGRITY_DEFECT`** (91 series ids apparently never created, no recoverable trace) — explicitly **NOT legacy parity**: legacy's own real behavior for this condition (a broken, blank `200`) was deliberately not reproduced; this is a controlled migration repair that renders a genuinely correct, complete page instead.
- **Hidden-series branch (834 items) remains open, unresolved** — same code path previously, now explicitly separated; still `404`s exactly as before, pending its own owner decision.
- **Migration-completeness impact: none.** A bug-fix/product-repair to an already-implemented feature. `MIGRATION_COMPLETE` (entry #39) unaffected.
- **Sitewide audit update:** `docs/reviews/sitewide-internal-404-audit.md` finding #2 marked **partially repaired** (nonexistent-series branch only) — the hidden-series branch and finding #3 remain explicitly open, not folded into this closure.
- **What changed:** `app/Domain/Content/Http/Controllers/KhotabItemController.php` (series-lookup refactor in `show()`, extended class docblock), `tests/Feature/Content/KhotabItemControllerTest.php` (+4 tests), `docs/reviews/sitewide-internal-404-audit.md` (finding #2 marked partially repaired). No other controller, service, or route touched. No database rows modified. `legacy-project` confirmed unchanged. No production accessed — all DB evidence and live verification against the local `olddb` mirror / local dev server only.

---

## 56. `khotab-item-{id}.htm` — hidden-series branch repaired as owner-approved `ITEM_VISIBILITY_IS_INDEPENDENT`, explicitly NOT legacy parity; `KhotabSeriesController` deliberately untouched

- **Trigger:** a dedicated, investigation-only pass traced the hidden-series branch left open by decision #55 (834 visible items referencing 61 hidden series). Classified `BUSINESS_CONFIRMATION_REQUIRED` — technical evidence strongly favored item-level independence, but business intent for at least one real subgroup (old political/event-coverage series) could not be established from available evidence, so no repair was made pending explicit owner input. Owner reviewed both candidate semantics (A: keep strict parent visibility; B: item visibility is independent) and **selected B**.
- **Evidence supporting B, gathered before the decision:** `series.hidden` is architecturally identical to `item.hidden`/`group.hidden` throughout legacy — same `$hiddenSql`-gated pattern in `item.php`, `series.php`, `ListSeries()`, `ListKhotab()` — and nothing in the schema or any legacy function cascades a hidden series into its children's own `hidden` value. Of 857 total children of the 73 hidden series, **834 (97.3%) remain `item.hidden=0`**. Legacy's own `ListPDF()` (and its faithful Laravel port `khotabPdfItemsByAuthor()`) already lists these items on the author's PDF page regardless of series-hidden status — a real, live-confirmed, pre-existing legacy inconsistency (`/khotab-pdf-10.htm` linked to 3 of these items before this repair), not something Laravel introduced. The `series` title column both queries select for display was confirmed, by reading every consuming Blade view, to be dead/unused — no hidden series metadata was ever actually rendered anywhere, before or after this repair.
- **Implemented — merged into the same null-series branch decision #55 already built**, not a separate code path:
  ```php
  $series = null;
  if ($khotabItem->ser_id > 0) {
      $seriesRow = Series::find($khotabItem->ser_id);
      if ($seriesRow !== null && (int) $seriesRow->hidden === 0) {
          $series = $seriesRow;
      }
  }
  ```
  A hidden series and a nonexistent series now resolve identically: `$series = null`, item renders normally, the view's pre-existing `@if ($series)` guard omits just the breadcrumb crumb. No database data changed, no generator changed, no fallback series/group invented.
- **`KhotabSeriesController` deliberately untouched, per explicit instruction** — a hidden series' own `/khotab-series-{id}.htm` page still `404`s. These 2 semantics (item-level visibility vs. series-entity-page visibility) are kept fully separate; rendering an item under a hidden series does not make the series itself browsable.
- **Real-data verification, 6 representative samples spanning 5 distinct hidden series, both video/audio, item ids from 2011 to 2025** (43627/series 3332, 255137/series 15368, 264477/series 15605, 258944/series 15572, 53469/series 3934, 183715/series 10848): all 6 items → live-confirmed `200`, correct content, correct breadcrumb (series crumb absent, hidden series title never appears anywhere on the page); all 6 corresponding series pages → still `404`, unchanged.
- **Tests:** `KhotabItemControllerTest` — 56/56 (the stale pre-existing "404s when the item belongs to a hidden series" test replaced in place — not silently deleted — with 2 new tests: hidden-series item renders with no breadcrumb crumb and no leaked series title; download/PDF/mirrors/comments all still render). `KhotabBrowsingControllersTest`'s own pre-existing "series show: 404s for a hidden series" test — left completely untouched — continues to pass, serving as the regression guard for `KhotabSeriesController`'s unchanged behavior. `Khotab` filtered run: 209/209. Full suite: 1003/1004, 1 pre-existing unrelated skip. PHPStan: 0 errors at project level 5.
- **Classification: owner-approved `ITEM_VISIBILITY_IS_INDEPENDENT` (candidate B) — explicitly NOT legacy parity.** Legacy's own real behavior for a hidden series (same broken, blank `200` as the missing-series case) was not reproduced; this is a controlled migration repair, and a genuine business decision, not an inferred technical fix.
- **Migration-completeness impact: none.** A bug-fix/product-repair to an already-implemented feature. `MIGRATION_COMPLETE` (entry #39) unaffected.
- **Sitewide audit update:** `docs/reviews/sitewide-internal-404-audit.md` finding #2 now marked **fully repaired** (both the nonexistent-series and hidden-series branches); finding #3 (w2acd relative-nav) remains the only open item in the audit.
- **What changed:** `app/Domain/Content/Http/Controllers/KhotabItemController.php` (hidden-series branch merged into the existing null-series path, extended class docblock), `tests/Feature/Content/KhotabItemControllerTest.php` (1 stale test replaced, +1 new test), `docs/reviews/sitewide-internal-404-audit.md` (finding #2 marked fully repaired). No other controller, service, or route touched — `KhotabSeriesController` explicitly untouched. No database rows modified. `legacy-project` confirmed unchanged. No production accessed — all DB evidence and live verification against the local `olddb` mirror / local dev server only.

---

## 57. Shared navigation relative-`href` bug repaired as `BUSINESS_REPAIR_LOW_RISK`, explicitly NOT legacy parity — sitewide audit fully closed

- **Trigger:** the final remaining open finding from the sitewide 404 audit (#3) — the shared nav partial's bare-relative hrefs, byte-faithful to legacy's own `header.php`, break on any genuinely nested-path page. Investigation-first pass; owner authorized implementation in the same pass if evidence proved fully unambiguous.
- **Full affected link set, enumerated not sampled:** 34 distinct internal `.htm` hrefs in `resources/views/layouts/partials/navigation.blade.php`, plus the shared search form's `action="search.htm"` — every one confirmed, by direct live HTTP check, to return `200` (one `302`, `fatawa-today.htm`'s own known redirect) at its root-relative destination and `404` at its previously-rendered bare-relative one. No destination was itself missing — a pure resolution-path defect, not a masked separate finding.
- **Affected page set:** `/w2acd/cds.php` (the audit's known example) **and `/w2acd/item.php`** (a 2nd genuinely nested-path page rendering the same partial, discovered during this pass — not previously listed in the audit). No other public, non-admin route was found nested deep enough to trigger the bug from `layouts.app`'s own composition (`admincp/*` uses an entirely separate layout, confirmed out of scope; a few other nested routes — `khotab/dump`, `khotab/search`, `live-stream/featured`, `polls/{id}` — share the same partial and incidentally benefit from this same fix, but were not separately investigated or tested, staying within this task's own stated w2acd-plus-shared-nav scope).
- **Root cause, fully traced:** legacy's own `header.php:255` has the identical bare-relative `href="categories.htm"` — confirmed faithfully reproduced, not migration-introduced. Every other href in the shared layout (`layouts/app.blade.php` itself, and the w2acd module's own views) was already root-relative or absolute — the entire defect was isolated to this one partial.
- **Classification: `BUSINESS_REPAIR_LOW_RISK`** — all 4 required conditions met: intended destinations unambiguous (34/34 verified real routes); links broken only by relative resolution (not missing destinations, not ambiguous semantics); root-relative repair changes no business semantics (same target, same route, only how the browser resolves the string); no hidden data/access behavior change; no database change required.
- **Implemented:** every bare-relative `.htm` href and the form `action` in `navigation.blade.php` now carries a leading `/` — a root-relative URL, not a hardcoded domain, not a `route()` conversion (the existing literal-string style was kept, minimizing the diff to exactly the leading-slash fix — matching the task's own "SAFE: href=\"/khotab.htm\"" example). `#`-only dropdown toggles, the already-correct `href="/"` home link, and every `https://` external link were left untouched — confirmed via diff review, not assumed.
- **Blast-radius, proven not assumed:** root-relative and bare-relative hrefs resolve identically from any root-level (flat) URL — live-verified on the homepage both before and after this change (identical rendered destinations, `200`). The fix is a provable no-op on every existing flat page and the actual repair for the 2 nested ones.
- **Real-data verification:** live-fetched both `/w2acd/cds.php` and `/w2acd/item.php?khid=1` post-fix — 0 remaining bare-relative `.htm` hrefs on either page (confirmed by direct search); every nav href, resolved via `urljoin()` against each page's own real URL, computed to its correct root-level target; all 34 targets re-confirmed `200`/`302` live. Homepage re-checked separately — nav renders identically, no regression.
- **Tests:** `W2acdControllerTest` (+2: no bare-relative hrefs/action render on `/w2acd/cds.php` or `/w2acd/item.php`; the module's own `?page=2` pagination link — intentionally local to `/w2acd/` — remains untouched, proving Class B was correctly excluded). `HomeControllerTest` (+1: the same shared nav renders identical root-relative hrefs on a flat root page, the regression guard). One initially-added test (clicking through to unrelated controllers' own routes from within `W2acdControllerTest`'s narrow w2acd-only DB fixture) was removed as unnecessarily brittle — that verification was already done, more reliably, via direct live HTTP checks against the real running app, not duplicated as an automated test with a mismatched fixture. Full suite: 1007/1008, 1 pre-existing unrelated skip. PHPStan: 0 errors at project level 5.
- **Migration-completeness impact: none.** A bug-fix to already-shipped global chrome, not a completeness question. `MIGRATION_COMPLETE` (entry #39) unaffected.
- **Sitewide audit update:** `docs/reviews/sitewide-internal-404-audit.md` finding #3 marked fully repaired. **This closes the sitewide audit completely — all 5 findings (#1 through #5) are now repaired; the audit document is fully closed.**
- **What changed:** `resources/views/layouts/partials/navigation.blade.php` (34 hrefs + 1 form action root-relative-prefixed, extended docblock), `tests/Feature/Content/W2acdControllerTest.php` (+2 tests), `tests/Feature/Content/HomeControllerTest.php` (+1 test), `docs/reviews/sitewide-internal-404-audit.md` (finding #3 marked fully repaired, audit-wide closure noted). No route, controller, or database change. `legacy-project` confirmed unchanged. No production accessed — all verification against the local dev server only.

## 58. Production index reconciliation — 7 proposed indexes resolved to 3 deployed, 4 already covered; name-only migration guards replaced with structural left-prefix detection

- **Trigger:** the performance programme (E-01…E-13) produced 7 legacy-schema indexes across 4 migrations, all measured against the **local** `olddb` mirror. Before deploying them to production via phpMyAdmin (no SSH), the real production index inventory was read and reconciled against them. Production turned out to carry a richer, deliberately-shaped index set added outside this programme — invalidating the "before" state every local measurement had assumed.
- **Production environment, verified directly (read-only, `information_schema` + session variables):** schema **`w2acp_laravel`**; **MySQL 5.7.44 Community Server (GPL)**; `nuke_islamic_khotab` and `nuke_islamic_mirror` both **InnoDB**, **DYNAMIC** row format; **`innodb_file_per_table = 1`**; ≈287,259 and ≈399,776 rows respectively. Identical server version and engine to the local mirror, so local build times and index sizes transferred with unusual confidence. `ALGORITHM=INPLACE, LOCK=NONE` is therefore valid for adding secondary B-tree indexes here — and is stated explicitly so MySQL *rejects* a statement it cannot perform online rather than silently falling back to a blocking table copy.
- **Critical operational hazard, recorded because it nearly caused a mistake:** tables of these exact names exist in **six** schemas (`w2acp_laravel`, `w2acp_2026`, `w2acp_db2025-1`, `w2acp_db2025-2`, `w2acp_english`, `w2acp_utf`), and the `w2acp_english` copy is **MyISAM** — where `LOCK=NONE` is not supported at all and an index build locks the table for its full duration. Every deployed statement was schema-qualified to `` `w2acp_laravel` ``; an unqualified `ALTER` run with the wrong database selected would have hit a backup or the MyISAM copy.
- **3 indexes deployed and verified on production**, one `ALTER` at a time, each with pre-check, `SHOW INDEX` verification and a post-deployment `EXPLAIN`:
  - **`idx_khotab_news_listing (vedio, weight, time)`** — `weight` appeared in **no** production index in any position, so no existing index could serve `ORDER BY weight DESC, time DESC`. After: `EXPLAIN` uses the index and **`Using filesort` disappeared** (the binary pass signal for this one; its `rows` estimate stays high because `LIMIT 50` terminates the ordered scan early).
  - **`idx_khotab_author_listing (author, vedio, ser_id, group_id)`** — before: `index_merge` intersecting `idx_ser_id_hidden` ∩ `idx_group_id`, **~30,456 rows**, `filtered 0.24%`. After: **`ref`, ~278 rows**. Filesort remains and is correct — the index covers the four equality predicates, not the `weight, time` ordering.
  - **`idx_khotab_channel_listing (channel_id, vedio, ser_id, group_id)`** — before: same `index_merge`, **~30,456 rows**, `filtered 1.52%`. After: **`ref`, ~69 rows**. Filesort likewise remains, correctly.
- **4 indexes deliberately NOT deployed — production already has ordered left-prefix structural coverage:**
  - `idx_mirror_khid (khid)` — covered by **`idx_khid (khid, id)`**
  - `idx_khotab_pdf (pdf)` — covered by **`idx_pdf_time (pdf, pdf_time)`** (the `pdf > 0` range predicate makes the second column irrelevant to ordering either way, so the leading column supplies identical narrowing)
  - `idx_khotab_day_listing (vedio, time)` — covered by **`idx_vedio_time (vedio, time)`**, a structurally exact duplicate under a different name
  - `idx_khotab_location (location_id)` — covered by **`idx_location_author_hidden (location_id, author, hidden)`**, which is *strictly better* for the author-scoped chat-room routes than the bare single column would have been
- **The migration bug this exposed.** All four migrations guarded themselves on index **name** alone (`SHOW INDEX … WHERE Key_name = ?`). That is correct only when a hand-applied index carries the same name the migration would use — the case the earlier phpMyAdmin-first deployments tested. Production presented the other case: equivalent indexes under *different* names. **MySQL permits duplicate indexes with a warning, not an error**, so a `migrate` run there would have created **4 redundant indexes and appeared to succeed** — costing ~20 MB, extra write amplification, and handing the optimizer more equivalent candidates on a table that had already demonstrated surprising plan choices. This was found before any migration was run against production; no duplicate index was ever created.
- **Repair — `App\Support\Database\LegacyIndexGuard`.** `up()` now skips creation when the desired index's ordered column list is an **exact left-prefix of any existing index on the same table**, or when the name already exists (a name collision fails `ADD INDEX` regardless of columns). Left-prefix redundancy is a sound equivalence, not a heuristic: a longer index beginning with the same columns serves everything the shorter one does, both narrowing and ordering. Two deliberate refinements: a partial-prefix column (`Sub_part`, e.g. `title(10)`) **truncates** the index rather than being dropped from it — `(a, b(10), c)` fully serves `(a)` but not `(a, b)`, and dropping the entry would collapse it to `[a, c]` and falsely report `(a, c)` as covered; and an empty desired column list is never treated as covered, since it would otherwise be a left-prefix of everything.
- **[Superseded 2026-11-08, plan register row N4 — owner-approved:** the four migrations' `down()` no longer drops anything at all. Production carries hand-deployed indexes under the very names `down()` would drop by name, so even a name-based drop could remove an index the migration never created there. `up()` is unchanged; `LegacyIndexGuard` is unchanged (its docblock still describes the old name-based `down()`); removing an index is a separate DBA action; production `migrate:rollback` remains prohibited. The paragraph below is the historical record.]**
- **`down()` remains name-based, deliberately and permanently.** A structure-aware drop would delete indexes the migration never created — on production that means dropping `idx_vedio_time` or `idx_pdf_time`. Separated into its own `namedIndexExists()` method and documented at every call site so the asymmetry cannot be "tidied up" later by mistake.
- **Verification beyond unit tests.** Production-shaped tables (all 15 `nuke_islamic_khotab` and 2 `nuke_islamic_mirror` indexes, production's exact names and column orders) were built in real MySQL 5.7 locally and the guard run against them through real `SHOW INDEX`: **0 duplicate `CREATE INDEX` operations across all 7 proposals**. Scratch tables dropped afterwards. Separately, a full local migration round-trip — `migrate` (no DDL, 8 indexes unchanged) → roll back all four (down to the pre-programme baseline of 2 khotab + 1 mirror index) → re-apply (all 7 recreated with exact column orders) — proved both directions still work.
- **Tests:** `tests/Unit/Support/LegacyIndexGuardTest.php`, 13 tests / 29 assertions — same-name skip, equivalent-different-name skip, longer-index left-prefix skip, **wrong column order does NOT count as equivalent**, missing index still created, plus the edges that would fail silently (reverse-direction prefix, case-insensitivity, empty list, `SHOW INDEX` row-order independence, partial-prefix truncation). Two are decisive: a fixture of the **real production inventory** asserting all 7 proposals skip, and a bare-baseline fixture asserting all 7 are still *created* — guarding against the dangerous failure mode where the guard degenerates into "skip everything". Full suite: **1086/1087**, 1 pre-existing unrelated skip. PHPStan: 0 errors at level 5. `DeploymentInstallerControllerTest` 5/5 — the SQLite installer path is unaffected, since the `applicable()` driver-and-table guard is unchanged.
- **Two earlier claims corrected visibly, not silently:** (1) the readiness audit's "7 index migrations (~51 MB) not yet applied" counted *proposals*, not production gaps — the real gap was **3 indexes, ~30 MB**. (2) The channel listing query was described mid-analysis as ordering by `weight, time` like the author query; it orders by **`time` alone** (`khotabItemsByChannel`). That correction mattered: it meant production's `idx_channel_vedio_time` could in principle have served the sort, so filesort presence was ruled out as a decision signal and the verdict rested on rows-examined instead — which the production `EXPLAIN` then confirmed.
- **Related observation, recorded not acted on:** production already carries **`idx_vedio_hits (vedio, hits)`** — the exact index the E-06 investigation examined and declined on write-amplification grounds. The cold-sidebar cost that decision accepted as debt is therefore likely already absent in production. This does not change the E-06 reasoning; it means the decision was moot for production all along.
- **Migration-completeness impact: none.** Schema/performance work against the shared legacy database, not a migrated capability. `MIGRATION_COMPLETE` (entry #39) unaffected.
- **What changed:** `app/Support/Database/LegacyIndexGuard.php` (new), the 4 index migrations (`2026_09_02_120000`, `_140000`, `_160000`, `_180000` — guards rewired, column definitions moved from SQL fragment strings to arrays), `tests/Unit/Support/LegacyIndexGuardTest.php` (new), `docs/decision-log.md` and `docs/PROJECT-HANDOFF.md` (this entry and the §1/§7 status updates). **No application code, controller, service, view, route or query behavior changed. No existing index dropped or renamed anywhere. No production write beyond the 3 approved, verified `ALTER … ADD INDEX` statements executed manually via phpMyAdmin. `legacy-project` confirmed unchanged.**

---

## 59. OLD administration migration closed (2026-10-15) — governing rule: working legacy behavior is preserved unless explicitly retired, externally owned or excluded

- **Status:** **MIGRATION IMPLEMENTATION CLOSED / LOCALLY VERIFIED** — known migration-owned functional gaps: **0**.
  **PRODUCTION CUTOVER / DEPLOYMENT NOT STARTED.** Final record: `docs/reviews/old-admin-master-execution-plan.md` §30.
- **Governing rule (owner, applied in the final pass):** working legacy behavior that was not explicitly retired,
  externally owned or excluded must be preserved. Two clarifications apply alongside it:
  - "working" follows plan §26.5 — a PHP 7/8 incompatibility is a runtime defect, not a retirement;
  - a capability that merely stayed `DEFERRED` in an earlier phase is not thereby retired.
- **Final additions caused by applying the rule:**
  - **Telawah mirror edit / delete administration** (plan §29.1) — `telawah.editmirror` / `telawah.delmirror`, OLD's
    own token names; authorized, transactional, audited; AdminCP and contextual reachability.
  - **Public promotional files** (plan §30.1) — `khotab-advfile-{id}.htm`, `khotab-advfilefull-{id}.htm` and
    `khotab-advfiles.htm` kept at their exact paths; the library sub-pages get pretty paths; the item-page popup
    button. The stored HTML is rendered through a strict allowlist (`SafeLegacyHtmlRenderer`) and never raw, per the
    owner's security decision.
  - **Public poll comments** (plan §30.2) — threading, single comment, reply, preview and member posting; three OLD URL
    shapes kept, the `pid` form redirects; Phase 4's delete offered contextually.
  - **Public Telawah mirror list** (plan §30.3).
- **Intentional non-migrations established in the final pass** (dead, broken, disabled by OLD itself, or unused — not
  owner retirements):
  - **Telawah mirror insert** — OLD disabled it itself: «هذه الخاصية غير متاحة في الوقت الحالي»; the form is commented
    out and `ConfirmInsertMirror()` `die()`s before writing.
  - **Promotional files:**
    - the library's `Detailes` page — its payload query uses a nonexistent `khid`, and it plays through a RealPlayer
      `<embed>`;
    - `KhotabSearch` — reachable only from dead "Copy of" templates, and its results lead to `Detailes`;
    - `MoreVedio` / `MoreAudio` / `AuthorVedio` — no handler;
    - `Repair` — counter repair, DEAD by plan §2 row 14;
    - all Flash behavior. Legacy players show «الملف غير متوفر حالياً» unless a browser-playable media URL can be
      extracted.
  - **Poll comments:**
    - `survey-comments.htm` and the poll-less `survey-showreply` URL form — no poll id, so OLD rendered nothing;
    - `DisplayBabies()` — never called;
    - comment scoring (`moderate`) — every stored `reason` is 0 and every score a creation default;
    - the PHP-Nuke karma moderation queue — empty;
    - PHP-Nuke account, journal, private-message and website links, and `update_points()`.
- **Security corrections, not parity defects:**
  - stored promotional HTML and comments are never rendered raw;
  - new comments are stored as escaped text;
  - poster emails and IPs are never shown;
  - CSRF on the comment form;
  - Arabic names and subjects are no longer replaced with «زائر».
- **Verification:**
  - full suite 1897 passed, 1 existing skip; PHPStan 0 errors;
  - changed PHP files Pint-clean, except two import-order findings already present at HEAD;
  - local MySQL walkthroughs 27/27, 57/57 and 14/14;
  - a real-data render audit of 1,133 promotional files and 752 comments: 0 unsafe output.
  - **Known limitation:** there is no local vBulletin database, so member poll-comment posting is covered by tests,
    not by a real local member session.
- **Not closed by this decision:** OLD / Gen 2 retirement and cutover (plan Definition of Done items 2, 3, 5a, 5b, 6
  and 9 — see plan §7) and all production deployment work.

---

## 60. Khotab closed locally (2026-10-22) — page-by-page OLD public admin parity re-review and final public-parity batch

- **Status:** **KHOTAB CLOSED LOCALLY** for the OLD → Laravel parity phase — **no known local Khotab parity gaps**.
  **PRODUCTION DEPLOYMENT NOT STARTED.** Full record: `docs/reviews/old-admin-master-execution-plan.md` §31.

  | Surface | Status |
  |---|---|
  | Khotab Group (`khotab-group-{id}.htm`) | OWNER APPROVED |
  | Khotab Series (`khotab-series-{id}.htm`) | OWNER APPROVED |
  | Khotab Item (`khotab-item-{id}.htm`) | OWNER APPROVED |
  | Khotab Author (`khotab-video-{id}.htm` / `khotab-audio-{id}.htm`) | OWNER APPROVED |
  | Khotab Main (`khotab-video.htm` / `khotab-audio.htm`) | OWNER APPROVED |
  | Khotab public parity (final follow-up batch) | COMPLETE |

- **How it was closed — not from a broad audit alone.** After #59, the owner set a stricter rule (2026-10-21): every
  *working* OLD public admin control is preserved in Laravel, even when similar functionality exists in another AdminCP,
  unless the owner explicitly retired or excluded it, or source proves it dead / unreachable. Earlier inferred
  "externally owned" classifications (including the Business Confirmation #6 boundary for item add / edit / move /
  delete and media) no longer justify an omission. Khotab was therefore re-reviewed **page by page and operation by
  operation** against the production-matching OLD working copy (`modules/Khotab/admin/index.php` and
  `modules/Khotab/index.php` checksums owner-verified), each page in two stages (investigation report → owner-approved
  implementation), with OLD-vs-Laravel browser comparison, OLD-vs-Laravel database behavior runs on disposable
  fixtures, and **owner visual approval of every page** before the next began.
- **Reviewed surfaces, in order:** 1) Group, 2) Series, 3) Item, 4) Author, 5) Main, 6) the final public-parity
  follow-up batch.
- **Intentional differences from OLD (summary):**
  - every mutation is an authorized, CSRF-protected POST / PUT / DELETE, transactional and audited — OLD's GET mutation
    links and GET «نعم» confirmations became forms;
  - hidden content: visitors follow Laravel's 404 policy where approved (hidden item, item of a hidden group); admins
    keep OLD's contextual visibility (hidden / empty groups and series, hidden items, all authors on Main), with those
    responses private / no-store;
  - broken PHP 8 / runtime behavior was not reproduced where the intended capability was recoverable (e.g. «سحب الصور»,
    add author after OLD's `sql_nextid()` fatal, the banner embed-code pages);
  - counter maintenance is deterministic and server-side: the Author page repairs the invoked author only (OLD repaired
    the *next* author and swept onward by meta refresh); Main's «إصلاح العدادات» is one set-wise sweep over every
    author with the same per-author semantics;
  - OLD's broken pending-comments pagination (a literal `{PAGE}` in every link, production-confirmed) was not
    reproduced — all pending comments are reachable;
  - the dead / unreachable `KhotabListDown` («ملفات مفقودة») was not implemented; `KhotabWaitingFB` stays retired,
    `KhotabCopyKhotab` and the adv-file edit / delete handlers stay dead;
  - `khotab-pdf-{id}.htm` keeps the Gen 2 / Laravel author-transcripts meaning first, then 301-redirects unambiguous
    OLD item-PDF URLs to `/khotab-item-pdf-{id}.htm`, which counts the download as OLD's `getpdf()` did;
  - the public item banner and its HTML / VBulletin / phpBB embed-code pages (`khotab-code-{id}-{type}.htm`) are
    restored, never as a broken image;
  - OLD's author-JPG sidebar thumbnail fallback (`media/authors/{id/1000}/{id}.jpg` for frame-less items on author
    lists) is restored — the profile card is unchanged;
  - visitors can no longer download a hidden item directly (`khotab-download-{id}.htm` → 404); admins can;
  - `nuke_islamic_letters` is not recomputed (absent in production, owner-verified); `authors.txt` is regenerated
    through the configurable `media.autocomplete_root`, never a hard-coded server path.
- **Open, non-blocking — these do not keep Khotab open:**
  - **A. Deployment verification — production `authors.txt` mapping:** production `config('media.autocomplete_root')`;
    PHP runtime write access to it; deployment must not overwrite the regenerated file with the stale tracked
    `public/w2a_autocomplete/authors.txt` (or must regenerate after deploying); after an author add / edit / delete, the
    header autocomplete reflects it.
  - **B. Deployment verification — upload ownership / permissions:** PHP runtime umask, PHP write access and web-server
    read access for `media_root/banner`, `media_root/pdf`, `media_root/docx` and `media_root/authors`. Laravel already
    writes `0666 & ~umask` files and `0775 & ~umask` directories; **do not use chmod 0777**.
  - **C. Performance / schema follow-up:** possible indexes `nuke_islamic_khotab(ser_id)` and
    `nuke_islamic_khotab(group_id)` (today a full index scan of ~290k rows per lookup; behavior would not change).
    No migration or `ALTER` is approved. Before any production change: MariaDB version, `SHOW INDEX`, online-DDL
    capability / behavior, and a deployment and rollback plan.
- **Regression rule:** do not reopen or modify Khotab during later domain work unless a demonstrated regression requires
  it, or new concrete OLD / production evidence proves a missed parity behavior. Do not refactor Khotab merely to make
  adjacent Anasheed / Telawah code more generic.
- **Verification at closure:** full suite 2,023 tests — 2,022 passed, 1 existing skip; PHPStan 0 errors; changed files
  Pint-clean (import-order findings already present at HEAD in `routes/admin.php` and `routes/content.php`).
- **Not closed by this decision:** the rest of the OLD public admin parity review (Anasheed, Telawah, Fatwa, …) and all
  production deployment work.

## 61. Anasheed Group contextual admin controls — CLOSED LOCALLY, OWNER APPROVED (2026-10-23)

- **Status: CLOSED LOCALLY — OWNER APPROVED (2026-10-23).** The owner reviewed the panel's appearance and each of the
  five controls locally, visually and functionally, and approved all of them. Do not reopen this surface unless a
  demonstrated regression or new concrete OLD / production evidence requires it. This closes **only** the Anasheed
  Group contextual-admin-control surface (Investigation #1 → Implementation #1); it does **not** mean Anasheed parity
  is complete. *(Historical, as of that date: the Anasheed OLD public admin-controls migration was later closed — #67, 2026-10-29; Anasheed visitor parity is a separate, unstarted scope.)*

- **Scope: contextual ADMIN controls only.** This stage implements the working controls of OLD's
  «لوحة تحكم قسم المنوعات» (`themes/way2allah/templates/Anasheed/group_cp.htm`, rendered by OLD
  `modules/Anasheed/index.php` `Group()`) on Laravel's existing `var-group-{id}.htm`. The visitor-parity findings of
  Anasheed Investigation #1 (item order, page size, the 98/16 merge, series-download visibility and `.grx` content,
  sub-group cards, descriptions, missing-group 404, Main, Anasheed News) were **not acted on**; visitor output of the
  group page is byte-identical before and after (captured and diffed for the group, root, paged, `.grx` and 404
  responses).
- **Preserved (five):** «اضافة صورة للقسم» (`AnasheedGroupIcon` / `UploadIcon`), «تعديل القسم» (`AnasheedEditGroup` /
  `ConfirmEditGroup`), «إضافة مادة» (`AnasheedInsertAnasheed` / `ConfirmInsertAnasheed`), «إضافة قسم فرعي»
  (`AnasheedAddGroup` / `ConfirmAddGroup`), «حذف القسم» (`AnasheedDeleteGroup` / `ConfirmDeleteGroup`). Routes
  `admin.anasheed.groups.*`; permissions `anasheed.addicon` / `editgroup` / `addanasheed` / `addgroup` / `delgroup` —
  OLD's own `nuke_anasheed_mod` tokens (definitions only, nothing granted). The item insert supersedes the 2026-10-15
  note that Anasheed item CRUD was externally owned (owner).
- **Excluded:** «ترتيب القسم» (`Order_anasheed_in_group`). Its page selects `nuke_anasheed_anasheed.banner`;
  production `information_schema` has no such column (owner-verified, read-only), so in OLD the form never rendered.
  **DEAD_OR_UNREACHABLE — production-schema confirmed.** Not repaired, not recreated, no column added, no replacement
  sorting UI. OLD Main's `Group(0)` panel (empty id, e.g. "add root group") is out of scope: the routes bind only an
  existing group, and `var-group-0.htm` stays 404.
- **Source version — as first recorded (SUPERSEDED, kept for history):** "OLD HEAD ran on PHP 5.6 (`.htaccess`
  `ea-php56`), where these handlers work end to end; the old-project working copy's PHP 8.1 edits add an
  `is_published` write production cannot accept. Laravel follows HEAD and production's schema: **no `is_published`**."
  This rested on an unverified assumption and is **wrong** — see the correction below.
- **Production-evidence correction (2026-10-23, owner-verified, read-only):** live OLD is `old.way2allah.com`
  (DocumentRoot `/home/php8way2allah/public_html`) on **PHP 8.1** (`application/x-httpd-ea-php81`). Its
  `modules/Anasheed/admin/index.php` (SHA-256 `3a7f176f…b61fd`) and `includes/logs.php` (`4d6b0d8d…c1d06`) match the
  local old-project **working copy**, not HEAD. Production **has** `nuke_anasheed_anasheed.is_published`
  (`NOT NULL DEFAULT 0`); `nuke_anasheed_anasheed.banner`, `nuke_anasheed_groups.banner` and
  `nuke_anasheed_groups.count` are absent. (The 2025 dump lacks `is_published`; the local `olddb` copy lacked it too until it was aligned on 2026-10-23 — see the local note below.)
  Production OLD's «إضافة مادة» form has the checkbox «نشر علي المنصة» (`is_published`, value 1, checked by default)
  and `ConfirmInsertAnasheed` writes `is_published = 1` when checked, `0` when not. Laravel's first implementation
  omitted the column, so the database default made every Laravel-added item unpublished (0) where OLD's default
  submission publishes (1) — a confirmed parity defect.
- **Targeted reopening (2026-10-23):** the closed Group surface was reopened **only** for that defect, under the
  regression rule (new concrete production evidence). Correction: the add-item form now shows «نشر علي المنصة»
  checked by default; the request accepts only `0` / `1`; the action always writes `is_published` (checked → 1,
  unchecked → 0). No other Group control, no visitor behavior, and no Item-page control changed. Verification: Group
  panel tests 28/28 (3 new); full suite 2,074 — 2,073 passed, 1 existing skip; PHPStan 0 errors; changed PHP files
  Pint-clean. **Local note:** the local `olddb` copy lacked `is_published`, so add-item against it failed
  (`Unknown column 'is_published'`). On 2026-10-23 the local development copy was aligned with the verified production
  schema — `ALTER TABLE nuke_anasheed_anasheed ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0`, local only
  (127.0.0.1:3307 `olddb`); no application migration was made and production was not touched.
  Status after the correction: **CLOSED LOCALLY — OWNER APPROVED**, with this production-evidence correction recorded.
- **Intentional differences from OLD:**
  - every mutation is authorized server-side (the route's permission and the action's own check), CSRF-protected,
    validated, transactional and audited (`upload_group_icon`, `edit_group`, `add_item`, `add_group`, `del_group`);
    OLD's GreenZone was inert and its panel showed to any admin regardless of module rights;
  - **delete — deliberate safety correction:** OLD refused a group with items but not one with sub-groups, which
    orphaned the children. Laravel refuses both, and never cascades;
  - edit accepts only the parents OLD's own select offered (up one level, stay, or a sibling — never a descendant);
    OLD accepted any posted id;
  - maintained counters (`child`, `anasheed`) move in the same transaction as the row they count, never on a failed
    write, and are never taken below 0; the item's stored root (`parent_id`) is not recomputed on a group move, as in
    OLD;
  - icon upload ignores the uploaded name (always `{id}.jpg` under `admin_anasheed.media_root`), checks the detected
    JPEG type as well as `.jpg`, keeps OLD's effective 10 000-byte limit (`admin_anasheed.icon_max_bytes`), and puts
    the previous file back if the database step fails;
  - a title is required for edit / add sub-group / add item, and a link for add item; lengths follow the columns;
  - add item writes `is_published` from OLD's «نشر علي المنصة» checkbox (production-evidence correction above);
  - OLD's `weight = id WHERE weight = 0` (table-wide) targets the new item only — the local copy has no other
    `weight = 0` row;
  - where OLD redirected to Anasheed Main (after a delete, after an icon on a root group), the admin lands on the
    parent group, the group itself, or the home page, since Laravel has no Main.
- **Khotab:** untouched. `AuditText` (Khotab namespace) is used read-only, not changed.
- **Verification:** 25 new tests (`tests/Feature/Admin/AnasheedGroupPanelParityTest.php`); focused related suites
  331/331; full suite 2,071 tests — 2,070 passed, 1 existing skip; PHPStan 0 errors; changed files Pint-clean (the
  import-order finding in `routes/admin.php` is already present at HEAD).
- **Owner approval (2026-10-23):** panel appearance, «اضافة صورة للقسم», «تعديل القسم», «إضافة مادة», «إضافة قسم
  فرعي», «حذف القسم» — all APPROVED. The delete safety correction (refuse while sub-groups exist) is approved and
  stays. «ترتيب القسم» stays excluded (DEAD_OR_UNREACHABLE — production-schema confirmed): never implement or repair
  it. Visitor behavior was intentionally out of scope and is unchanged. Khotab stays closed and untouched. No
  deployment, no commit.
- **Not closed by this decision:** every other Anasheed surface and control (item page, Main, News, visitor parity);
  Telawah; Fatwa; deployment (grant the new permissions through the permission editor; check web-server write access
  to `media_root/anasheed/icons`).

## 62. Anasheed Item page contextual admin controls (2026-10-23) — MANUAL REVIEW COMPLETE, OWNER APPROVED (2026-10-28)

- **Scope: contextual ADMIN controls of `var-item-{id}.htm` only** (OLD `Detailes()`'s «لوحة تحكم المادة»,
  `item_cp.htm`, plus `Mirror()`, `Tree()` and `Comments()` in `modules/Anasheed/index.php`). Visitor behavior was out
  of scope and is unchanged: visitor HTML is byte-identical before / after (item page, paged item page, group page,
  missing item).
- **Production truth used:** live OLD `old.way2allah.com` (DocumentRoot `/home/php8way2allah/public_html`) on PHP 8.1
  runs the old-project **working copy** (SHA-256-verified); production has `nuke_anasheed_anasheed.is_published`
  (`TINYINT(1) NOT NULL DEFAULT 0`; the local `olddb` was aligned) and has no `nuke_anasheed_anasheed.banner`,
  `nuke_anasheed_groups.banner` or `nuke_anasheed_groups.count`.
- **Investigation #2 inventory (17 OLD controls):**
  - **Implemented (5):** T1 «اضافة صورة للمادة» (`AnasheedDetailes` → `UploadBanner`: the item FRAME), T4
    «تعديل المادة» (`AnasheedEditAnasheed` → `ConfirmEditAnasheed`), T6 «حذف المادة» (`AnasheedDeleteAnasheed` →
    `ConfirmDeleteAnasheed`), K1 «إضافة تعليق إداري» (`AnasheedAddModComment`), K2 «(تعديل التعليق)»
    (`AnasheedWaitingList&id=` → `AddComment` publish / `DelComment` hide).
  - **Already preserved, left intact (5):** add mirror (Phase 1), edit / delete mirror beside each mirror (Phase 1), add
    category (Phase 3), remove category beside each category (Phase 3).
  - **Dead, not implemented (5):** «تعديل / حذف صورة للمادة» (always HTML-commented out in OLD: their guard reads the
    absent `nuke_anasheed_groups.banner`; their handlers read / write the absent item `banner`), «رفع / تعديل / حذف
    البانر» (each handler `die('قيد التطوير')` in the production file).
  - **Owner-retired (2026-10-15), not implemented (2):** «اضافة الى قائمة الشير» (`AnasheedWaitingFB`); the link-status
    block (`AdvancedStats()` — also never called by `Detailes()`, template absent).
- **Toolbar:** «لوحة تحكم المادة», in OLD's order — «اضافة صورة للمادة», «تعديل المادة», «إضافة وصلة», «حذف المادة»,
  «إضافة تصنيف». The comment controls sit in the comments block, as in OLD (shown when the item's `comments` counter
  is above 0).
- **Permissions (owner-approved, OLD `nuke_anasheed_mod` tokens, definitions only):** T1 `anasheed.addbanner`, T4
  `anasheed.editanasheed`, T6 `anasheed.delanasheed`, K1 `anasheed.addcomment`, K2 edit page + publish
  `anasheed.addcomment`, K2 hide `anasheed.delcomment` (corrected 2026-10-27, see «K2 permission parity correction»
  below; originally all three were `anasheed.delcomment`). Routes `admin.anasheed.items.{frame,frame.store,edit,update,delete,destroy,comments.store}` and
  `admin.anasheed.comments.{edit,publish,hide}`; every mutation is CSRF-protected, FormRequest-validated, authorized by
  the route and the action, transactional and audited (`upload_image`, `edit_item`, `del_item`, `add_mod_comment`,
  `publish_comment`, `hide_comment`).
- **Behavior and owner-approved corrections to OLD:**
  - **T1:** a real JPEG (detected type and `.jpg`), at most 40 000 bytes (OLD's `MAXFSIZE`;
    `admin_anasheed.frame_max_bytes`), stored as `media/anasheed/frame/{floor(id/1000)}/{id}.jpg` under
    `admin_anasheed.media_root` (uploaded name ignored), then `frame = 1`; the previous file is restored if the database
    step fails. No banner column is written.
  - **T4:** OLD's columns (title, description, meta_*, link, trial = 0, group_id, channel_id / author_id with NULL for
    «غير مصنف», vedio, fixed, **is_published**, notes); only groups OLD's select offered (158, the stored root, the
    current group, its sub-groups). **Size correction:** OLD never wrote `linksize`; Laravel writes it (MB → bytes as
    Add Item), and an unchanged 2-decimal prefill keeps the stored bytes. **parent_id correction:** on a group change the
    item's root is recomputed from the new group's hierarchy in the same transaction (old group `anasheed − 1`, new
    `+ 1`, floored); unchanged group → `parent_id` untouched; `cat_id` never touched. OLD's `RepairCount()` (no effect in
    production) is not reproduced.
  - **T6 safe delete:** OLD removed only the item row. Laravel, after a confirmation page, deletes in one transaction the
    item's mirrors, all its comments, its `nuke_anasheed_advanced` row (`id = item id` — OLD's only writer,
    `backup.php` cat 4, keys it by item id; never by mirror id, since those collide with other items' rows), releases
    each category in `cat_id` (`anasheed_count − 1`, floored; categories kept), deletes the row and takes 1 from the
    group. The frame file is moved aside first, restored on failure, removed after commit (a later unlink failure is
    reported, never undoing the commit). Shared references (groups, categories, channels, authors), logs and the
    transient `nuke_backup_booking` queue are not touched.
  - **K1:** a published comment (`view = 1`, `uid = 0`, empty name → shown as «مشرف التعليقات», IP, country code via
    `ips`, time). **Counter correction:** OLD did not update `comments`; Laravel recounts the item's `view = 1` comments.
    Text is stored as submitted and escaped on output (OLD's `filter_text()` markup handling not reproduced).
  - **K2:** publish replaces the text and sets `view = 1`; hide sets `view = 2` (not deleted, text unchanged); both
    recount `comments` and return to the item. The separate pending-comments AdminCP queue is out of scope.
- **Verification:** 22 new tests (`tests/Feature/Admin/AnasheedItemPanelParityTest.php`); focused Item / contextual /
  mirror / category / permission suites 206/206; Anasheed Group regression 49/49; Khotab panel suites 101/101; full
  suite 2,096 — 2,095 passed, 1 existing skip; PHPStan 0 errors; changed files Pint-clean (the import-order finding in
  `routes/admin.php` predates this work).
- **T1 manual review (2026-10-23): FAILED → FIXED LOCALLY** (owner retest passed; OWNER APPROVED 2026-10-28). A file named `.jpg` was refused
  with «هذا الإمتداد غير مسموح به». Cause: `extensions:jpg` (filename) and `mimetypes:image/jpeg` (fileinfo on the
  bytes) shared that one message, and a file merely named `.jpg` (e.g. WebP / PNG content) fails the content check.
  The tests had not caught it because `UploadedFile::fake()` reports a MIME type guessed from the name. Fix: the content
  failure now says «الملف ليس صورة JPG حقيقية، يرجى حفظ الصورة بصيغة JPG ثم إعادة رفعها»; validation is unchanged
  (`jpg` only, genuine `image/jpeg`, ≤ 40 000 bytes, no conversion). T1 tests now upload real files: genuine JPEG passes,
  PNG / WebP bytes named `.jpg` get the new message, JPEG named `.jpeg` / `.png` gets the extension message, a JPEG over
  40 000 bytes gets the size message. The Group icon test fixture got the same real-file correction plus a
  disguised-content test (Group production code and messages unchanged).
- **T1 manual retest #2 (2026-10-23): HTTP 500 after a successful upload → FIXED LOCALLY** (owner retest passed; OWNER
  APPROVED 2026-10-28).
  A genuine JPEG under 40 KB passed validation, the frame was stored and `frame = 1` + `upload_image` were committed
  (item 13486: file present, two audit rows from a double submit) — then the response crashed with
  `FileNotFoundException: The file "/tmp/php6TA1ka" does not exist`. **Root cause:** the shared web-group middleware
  `HonorContextualReturn` ran after the controller and called `$request->has('return_to')`; `has()` → `all()` →
  `allFiles()` re-wraps the BASE request's Symfony `UploadedFile` (`UploadedFile::createFromBase` → `is_file()`), but the
  FormRequest controller (a separate request copy with its own converted files) had already `move()`d PHP's temporary
  file. HTTP tests could not see it: Laravel's test client puts Illuminate `UploadedFile`s in the files bag, which are
  never re-wrapped. **Approved root-cause fix** (applied manually by the owner, because Claude Code's edit-safety check
  was unavailable; verified unchanged otherwise): the middleware reads `return_to` once through
  `$request->input('return_to')` (JSON / form body, then query — never files), exits on `null`, and passes the same value
  to `ContextualReturn::safeTarget()`. Outcome is identical to `has()` for every input; the `return_to` contract,
  precedence (body over query), authorization, CSRF, producers and the upload actions (normal `move()`) are unchanged.
  The **Anasheed Group icon** upload (FormRequest + `move()`) had the same demonstrated mechanism and is corrected by the
  same fix; its targeted owner retest **PASSED — OWNER APPROVED** (2026-10-28: «إضافة صورة للقسم» with the genuine
  JPEG used for the T1 retest uploaded successfully, no HTTP 500, normal flow completed). Khotab uploads (plain `Request`, files converted on the base request
  before the move) were not affected and are unchanged. CD release create / update (FormRequest, multipart, `move()`,
  `admin.cds.update` in `ROUTES`) follow the same code path and are covered by the fix — inferred, not reproduced.
  **Regression coverage:** `ContextualReturnUploadLifecycleTest` (a stale moved Symfony upload never makes the middleware
  throw and is never re-wrapped; the real base-request / FormRequest / `move()` / middleware lifecycle for the item frame
  and the group icon) and four new `ContextualReturnFlowTest` contract cases (query-only on a mutation, body wins over
  query, JSON body, an uploaded file named `return_to` ignored), alongside the existing body / unsafe-target /
  outside-routes cases. **Verification:** contextual suites 104/104; T1 5/5; Item 51/51; Anasheed Group 44/44; Khotab
  panels 101/101; CD 48/48; full suite 2,105 — 2,104 passed, 1 existing skip; PHPStan 0 errors; changed files
  Pint-clean.
- **K1 / K2 manual review (2026-10-27), item 6233 / comment 21979:** K1 add (`add_mod_comment`, counter 2 → 3),
  K2 publish-with-edit (`publish_comment`, view 1 → 1, text changed) and K2 hide (`hide_comment`, view 1 → 2, text
  unchanged, row kept, counter 3 → 2) verified by DB and audit evidence. «مسح التعليق للمادة» is OLD's own label for
  `DelComment()` (`UPDATE … SET view = 2`); OLD's `addcomment.htm` offers only «نشر التعليق للمادة» and «مسح التعليق
  للمادة», and OLD never deletes a comment row outside the item delete — so no Hide button or delete semantics are
  missing.
- **K2 permission parity correction (2026-10-27): CONFIRMED GAP → FIXED LOCALLY.** OLD gates `WaitingList(id)` (the
  comment page) and `AddComment()` («نشر التعليق للمادة») with `GreenZone('addcomment')`, and only `DelComment()`
  («مسح التعليق للمادة») with `GreenZone('delcomment')` (`modules/Anasheed/admin/index.php`). Laravel had put the whole
  `admin.anasheed.comments.*` group behind `anasheed.delcomment`, and `AnasheedItemComments::publish()` authorized
  `anasheed.delcomment`. Now: `comments.edit` and `comments.publish` → `anasheed.addcomment` (route and action);
  `comments.hide` → `anasheed.delcomment` (route and action, unchanged). Consequence, as in OLD: the public
  «(تعديل التعليق)» link (route-access-driven) shows to `addcomment` holders, not to `delcomment`-only holders.
  Comment behavior, UI labels, redirects and visitor output unchanged; the permission keys are unchanged and both
  were already defined in `AdminPermissionSeeder` (no seeding run). **Tests** (`AnasheedItemPanelParityTest`): the
  K2 page / publish tests now use `addcomment`; the per-permission visibility test is updated; new: addcomment-only
  opens and publishes but cannot hide (403), delcomment-only hides but cannot open or publish (403), the action
  itself refuses publish without `addcomment` and hide without `delcomment`, super-admin unchanged. **Verification:**
  Item 26/26; Anasheed Group + contextual 133/133; full suite 2,108 — 2,107 passed, 1 existing skip; PHPStan 0
  errors; changed files Pint-clean apart from the pre-existing `routes/admin.php` import-order finding. **Permission
  labels — RESOLVED (2026-10-28):** the permission-editor labels (`PermissionLabels`, display only) now match the
  split: `anasheed.addcomment` → «إضافة تعليق إداري / تعديل ونشر التعليقات», `anasheed.delcomment` → «مسح (إخفاء)
  التعليقات» (was «تعديل / نشر / مسح التعليقات»). Keys, routes, authorization and assignments unchanged.
- **Status (2026-10-28): MANUAL REVIEW COMPLETE — OWNER APPROVED.** Owner / manual verification: T1 «اضافة صورة
  للمادة», T4 «تعديل المادة», T6 «حذف المادة», K1 «إضافة تعليق إداري», K2 comment edit / publish / «مسح» (hide via
  `view = 2`) — each OWNER APPROVED. Final K2 permission parity: `anasheed.addcomment` → add an admin comment and edit /
  publish comments; `anasheed.delcomment` → «مسح» / hide comments (final labels: «K2 permission parity correction»
  above). *(Superseded status, kept for history: "IMPLEMENTED LOCALLY — AWAITING OWNER VISUAL / FUNCTIONAL REVIEW
  (T1: fixed locally, awaiting owner retest)".)* No local permission seeder run. Not Anasheed parity completion *(Historical, as of that date: the Anasheed OLD public admin-controls migration was later closed — #67, 2026-10-29; Anasheed visitor parity is a separate, unstarted scope.)*. Anasheed Group stays CLOSED LOCALLY — OWNER APPROVED,
  with this shared upload-response defect corrected; its icon upload targeted retest PASSED — OWNER APPROVED
  (2026-10-28). Khotab and the Anasheed Group surface untouched. No deployment, no commit.
- **Deployment notes:** grant the five new permissions through the permission editor; check web-server write access to
  `media_root/anasheed/frame`. Possible follow-up (not done): if production has `nuke_anasheed_advanced_m`, rows for
  a deleted item's mirrors stay (the Phase 1 mirror delete leaves them too).

---

## 63. Anasheed Main contextual admin controls — CLOSED, 0 preserve candidates, no Laravel implementation (2026-10-28)

- **Scope:** only the contextual admin controls of OLD Anasheed Main — the dispatcher's `default:` → `Group(0)`
  (`modules/Anasheed/index.php:1082`), reached at `modules.php?name=Anasheed` / `new_modules.php?name=Anasheed`,
  `var-group-0.htm` / `var-group-.htm` (`.htaccess:445`, `([0-9]*)`), and OLD's own post-edit / post-delete admin
  redirects (`admin/index.php` :878, :982, :1278, :1366, :1381). Laravel has no Main (`var-group-0.htm` 404, no
  `modules.php` route); that is a visitor-flow difference and is not migration work here. Source: the old-project
  working copy (= production).
- **Controls:** Main renders «لوحة تحكم قسم المنوعات» (`group_cp.htm`) for `is_w2a_admin`, but `$Group` has no row
  for id 0, so every link carries an empty id:

  | OLD control | Behavior on Main | Classification |
  |---|---|---|
  | اضافة صورة للقسم | `GroupIcon('')` → id 0 → «لم يتم العثور على هذه المادة…» (`admin/index.php:1784-1788`) | DEAD_OR_UNREACHABLE |
  | تعديل القسم | `EditGroup('')` → same message (:1135-1139) | DEAD_OR_UNREACHABLE |
  | إضافة مادة | form renders with hidden `group_id=''`; `ConfirmInsertAnasheed` dies «القسم غير موجود» (:619-636) | DEAD_OR_UNREACHABLE |
  | إضافة قسم فرعي | `ConfirmAddGroup` runs `INSERT … parent_id = ''` (:1088-1125); OLD's PDO layer swallows query errors (`db/mysql_modern.php:46-54`) and the connection never sets `sql_mode` | DEAD_OR_UNREACHABLE — **production-confirmed** (below) |
  | حذف القسم | `DeleteGroup('')` → «لم يتم العثور…» (:1328-1332) | DEAD_OR_UNREACHABLE |
  | ترتيب القسم | `Order_anasheed_in_group` | DEAD_OR_UNREACHABLE — production-schema confirmed (#61) |

  Nothing else on Main is admin-only (`Group()` counts pending comments but `group_cp.htm` never shows it; `ListGroup`,
  `ListAnasheed`, `Logo` carry no admin markup).
- **Production evidence (2026-10-28, owner-run, read-only):** `old.way2allah.com`, DocumentRoot
  `/home/php8way2allah/public_html`, EA-PHP 8.1, using OLD's own `config-w2a.php` connection:
  - `@@GLOBAL.sql_mode` = `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION`
  - `@@SESSION.sql_mode` = `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION`

  Under `STRICT_TRANS_TABLES`, `parent_id = ''` into the INT column is an error, so the insert fails (silently) and no
  root group is created; OLD then redirects to `var-group-.htm` — Main again. «إضافة قسم فرعي» on Main is therefore
  **reclassified UNRESOLVED → DEAD_OR_UNREACHABLE**. (Consistent with local data: only the five original root groups
  12 / 16 / 57 / 98 / 158 exist.)
- **Decision:** every contextual admin control on Anasheed Main is DEAD_OR_UNREACHABLE. **Anasheed Main admin-controls
  parity is CLOSED with 0 preserve candidates; no Laravel implementation is required.** No code, DB, test or
  permission change.
- **Anasheed News** (`exclusive-news.htm`, `cartoon-news.htm`, `documentary-news.htm`, `anasheed-news.htm` →
  `MoreAnasheed()` / `ListNews()`): its templates (`anasheed_head_news.htm`, `anasheed_cont.htm`, `anasheed_foot.htm`)
  expose **no OLD public admin controls**, so it is **out of current work**; its visitor differences are not migration
  work here.
- **Next investigation surface:** `anasheed_without_cats.htm` (OLD `anasheed_without_cats()`,
  `modules/Anasheed/index.php:259`; `.htaccess:495-498`) **only**. `anasheed_without_authors.htm` and
  `anasheed_without_channels.htm` were identified in the same investigation and are **not** to be investigated or
  implemented yet.
- Khotab, Anasheed Group (#61) and Anasheed Item (#62) untouched. No deployment, no commit.

---

## 64. Anasheed `anasheed_without_cats.htm` — MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)

- **OLD surface:** `.htaccess:494` / `:497` → `new_modules.php?name=Anasheed&op=anasheed_without_cats[&page=N]` →
  `modules/Anasheed/index.php:1071-1072` → `anasheed_without_cats()` (`:259-301`): an admin-only list of
  `nuke_anasheed_anasheed` rows with `cat_id IS NULL OR cat_id = '' OR cat_id = '|'`, `ORDER BY id DESC`, 50 per
  page (`PS_Pagination`, links `anasheed_without_cats-page-#.htm`), columns م / إسم المادة / تعديل
  (`without_{head,body,foot}.htm`), each title linking to `var-item-{id}.htm` and each row offering «إضافة تصنيف»
  → `admin.php?op=cat_add_to_anasheed&item_id={id}&redirct_to=…` (`:291`). Linked from nowhere in OLD.
- **«إضافة تصنيف» trace:** `modules/Cat/admin/index.php:385-452` — the **same handler as the item page's T7**
  (`item_cp.htm:60`): no `cat_id` → the category tree picker; `cat_id` → appends it if absent (duplicates refused),
  `anasheed_count + 1`, `RecordLogs('add_cat_anasheed')`, then **always** `Location: var-item-{id}.htm` (unless
  `back=true`). It **never reads `redirct_to`** — dead on this path. No per-operation check (`GreenZone()` returns
  at `mainfile.php:1917`).
- **Production evidence (owner-run, read-only, 2026-10-28):** SHA-256 of `.htaccess`, `new_modules.php`,
  `admin.php`, `modules/Anasheed/index.php`, `includes/ps_pagination.php`, the three `without_*` templates,
  `modules/Cat/admin/case.php` and `modules/Cat/admin/index.php` match the old-project working copy exactly.
  `mainfile.php` differs (production `47b9c161…e35d`; matches no local revision); its two relevant sections were
  extracted (redacted, read-only): the GET / POST → `$GlobalVariables` → `extract()` behavior is present, and
  production `is_w2a_admin()` calls `w2a_load_shared_admin()`, then requires `$_SESSION['uid']`, a matching
  `nuke_authors` row and `$_SESSION['w2a']->usertype == 'superadmin'` — so on production the page and
  `admin.php` were **super-admin only**. *(Correction: the investigation first described the local working copy's
  HMAC-cookie check; production's function does not do that itself.)*
- **Classification:** the page + its pagination — PRESERVE_CANDIDATE (working, admin-gated, not retired, missing in
  Laravel); «إضافة تصنيف» behavior — ALREADY_PRESERVED (Phase 3 / T7); its entry point from this page —
  PRESERVE_CANDIDATE; `redirct_to` — DEAD_OR_UNREACHABLE. The item-update `return_to` contract is not involved.
- **Owner decisions (2026-10-29):** (1) page permission `anasheed.addcat` — the super-admin bypass keeps OLD
  production access, and an `anasheed.addcat` holder already has the same assignment power from the item page, so
  no new write power is exposed; (2) a successful add returns to `/var-item-{id}.htm` (OLD's redirect), not to the
  list; T7's duplicate / error behavior unchanged. Unauthorized access follows the existing admin-route pattern.
- **Implementation:** routes `admin.anasheed.without-cats` (`GET /anasheed_without_cats.htm`) and
  `admin.anasheed.without-cats.paged` (`GET /anasheed_without_cats-page-{page}.htm`, numeric) at OLD's public paths,
  outside `/admincp`, behind `admin.role` + `admin.permission:anasheed.addcat` (visitor / member 401, admin without
  the permission 403, super-admin passes). `AnasheedWithoutCatsController` (read-only query on OLD's exact condition,
  `id DESC`, 50 per page, a paginator whose links are OLD's `-page-N.htm` form, page 1 the bare URL); view
  `admin/anasheed/without-cats.blade.php` in the site layout; per-row «إضافة تصنيف» via
  `AnasheedWithoutCatsControls::addCategory()` — a contextual link to the existing T7 picker
  (`admin.categories.anasheed.item`) carrying `return_to=/var-item-{id}.htm`, which the picker passes to
  `admin.categories.anasheed.item.add` (already in `ContextualReturn::ROUTES`). No new write logic, no change to
  `ContextualControl`, `ContextualReturn`, T7 or any visitor page. The response is marked `no-store, private` by the
  existing contextual-controls component.
- **Not reproduced (owner-approved):** negative numbering, unescaped titles, a blank out-of-range page (an empty-state
  message is shown instead), `redirct_to`. Laravel's canonical `|5|` category format (Phase 3) is written, not OLD's
  `5|`.
- **Verification:** 7 new tests (`tests/Feature/Admin/AnasheedWithoutCatsParityTest.php`: selection semantics,
  `id DESC`, 50 per page + numbering + OLD page URLs, item link, picker link + contextual return to the item with
  exactly one T7 write, no writes of its own, 401 / 403, super-admin); Anasheed Item / Group / T7 category /
  contextual suites 167/167; full suite 2,115 — 2,114 passed, 1 existing skip; PHPStan 0 errors; new files
  Pint-clean (pre-existing `routes/admin.php` import-order finding).
- **Owner manual review (2026-10-29), as super-admin:**
  1. `/anasheed_without_cats.htm` opened, rendered correctly inside the site layout, with the expected columns and
     rows; every row exposed «إضافة تصنيف».
  2. `/anasheed_without_cats-page-2.htm`: the legacy pagination URL worked, page 2 started at row 51, and the
     list / pagination rendered correctly.
  3. Item #17258 «الحلقة 20 (نموش)»: «إضافة تصنيف» opened the existing T7 picker, which correctly showed no existing
     categories; a category was added successfully, with no HTTP 500; the contextual return landed on
     `/var-item-17258.htm`, where the assigned category appeared; back on `/anasheed_without_cats-page-2.htm`, item
     #17258 was no longer in the unclassified list.
- **Status:** **MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)** (previously "IMPLEMENTED LOCALLY — AWAITING
  OWNER MANUAL REVIEW"). At the time, `anasheed_without_authors.htm` and `anasheed_without_channels.htm` were not
  investigated (both later done — #65, #66). No local permission seeder run, no deployment, no commit.

---

## 65. Anasheed `anasheed_without_authors.htm` — MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)

- **OLD surface:** `.htaccess:501` / `:504` → `new_modules.php?name=Anasheed&op=anasheed_without_authors[&page=N]` →
  `modules/Anasheed/index.php:1068-1069` → `anasheed_without_authors()` (`:303-345`): gated by `is_w2a_admin()`
  (production: super-admin only, #64; otherwise a 200 «عفوا ، ليس لديك الصلاحية لدخول هذه الصفحة»), it lists
  `nuke_anasheed_anasheed` rows with **`author_id IS NULL`** only (`author_id = 0`, «بدون شيخ», is not listed;
  `''` cannot exist — `int(11) NULL`; no hidden / published filter), `ORDER BY id DESC`, 50 per page
  (`PS_Pagination`, `anasheed_without_authors-page-#.htm`), columns م / إسم المادة / تعديل (`without_{head,body,foot}.htm`),
  title → `var-item-{id}.htm`, and per row «تعديل» →
  `admin.php?op=AnasheedEditAnasheed&id={id}&redirct_to={$nukeurl}/anasheed_without_authors-page-{page}.htm`
  (`:330`, `:333`). Linked from nowhere in OLD. Local data: 2,333 NULL, 10,175 zero, 4,017 positive of 16,525.
- **«تعديل» trace:** `admin.php:255-261` → `modules/Anasheed/admin/case.php:21` → `admin/index.php` (module gate
  `:20-35`, `radminsuper` or listed module admin) → `EditAnasheed()` (`:704-860`, the same handler as the item page's
  T4) → `ConfirmEditAnasheed()` (`:862-945`): `Author` `''` → NULL, else `(int)`; the T4 `UPDATE`, group counters,
  `RecordLogs('edit_item')`, `RepairCount()` (every query fails harmlessly on production's schema; its `Location` is
  replaced). **Unlike #64, `redirct_to` is live:** `EditAnasheed()` re-emits it as a hidden field (`:757-759`) and
  `ConfirmEditAnasheed()` redirects to it (`:939-940`), else to `modules.php?name=Anasheed&op=Detailes&khid={id}`. A
  successful edit opened from the list therefore returned to the same list page; «غير مصنف» kept the item listed, an
  author or «بدون شيخ» removed it.
- **Production evidence (owner-run, read-only, 2026-10-29):** SHA-256 of `modules/Anasheed/admin/case.php`
  (`8bbd006a…2d77`), `modules/Anasheed/admin/language/lang-arabic.php` (`c346a36b…19a7`, defines `_EditAnasheed`) and
  `includes/sql_layer.php` (`f3d4ceea…20bc`) match the old-project working copy; production `mainfile.php:231-232`
  requires `includes/sql_layer.php` and sets `$dbi = sql_connect(…)`; `$dbtype = "MySQL"`. Together with #61
  (`modules/Anasheed/admin/index.php` `3a7f176f…b61fd`, `includes/logs.php`) and #64 (`.htaccess`, `new_modules.php`,
  `admin.php`, `modules/Anasheed/index.php`, `ps_pagination.php`, `without_*` templates, globals, `is_w2a_admin()`),
  the whole page → edit → save → `redirct_to` path is production-verified.
- **Classification:** the page + its pagination — PRESERVE_CANDIDATE; «تعديل» edit capability — ALREADY_PRESERVED (T4,
  #62, owner approved); its entry point from this page — PRESERVE_CANDIDATE; the return to the list page after a
  successful edit — working OLD behavior, owner decision (below); T4 error paths — ALREADY_PRESERVED (T4 validation).
- **Owner decisions (2026-10-29):** (1) page permission `anasheed.editanasheed` (the page's only capability; no new
  write power; the super-admin bypass keeps OLD production access; no special super-admin check); (2) preserve
  `redirct_to`: a successful edit opened from the list returns to the same list page, page 1 as the bare
  `/anasheed_without_authors.htm` (never `-page-.htm`). **T4 regression rule:** the return is strictly opt-in — T4
  without `return_to` keeps its owner-approved redirect to `/var-item-{id}.htm`.
- **Implementation:** routes `admin.anasheed.without-authors` (`GET /anasheed_without_authors.htm`) and
  `admin.anasheed.without-authors.paged` (`GET /anasheed_without_authors-page-{page}.htm`, numeric) behind
  `admin.role` + `admin.permission:anasheed.editanasheed`; `AnasheedWithoutAuthorsController` (`whereNull('author_id')`,
  `id DESC`, 50 per page, OLD page URLs) and `admin/anasheed/without-authors.blade.php` in the site layout; per-row
  «تعديل» via `AnasheedWithoutAuthorsControls::edit()` → `admin.anasheed.items.edit?return_to={canonical list page}`.
  T4 changes, opt-in only: the edit form renders the existing `<x-admin.contextual-return />` (a hidden `return_to`
  only when `ContextualReturn::safeTarget()` accepts the query value or old input — so it survives a validation
  redisplay), and `AnasheedItemAdminController::update()` redirects to `safeTarget(return_to)` when valid, else to
  `/var-item-{id}.htm` as before (same message, same flash key) — the controller-level pattern already used by
  `KhotabGroupAdminController::detachItem()`. `ContextualReturn` (and its `ROUTES`), `HonorContextualReturn`,
  `UpdateAnasheedItemRequest`, `UpdateAnasheedItemAction` and the item page's T4 control (`returns: false`) are
  unchanged. No new write logic.
- **Not reproduced (owner-approved):** negative numbering, unescaped titles, `-page-.htm` return targets, a blank
  out-of-range page (an empty-state message is shown), the 200 "no permission" page (401 / 403 instead), OLD's
  absolute `$nukeurl` target (a same-site path).
- **Verification:** 11 new tests (`tests/Feature/Admin/AnasheedWithoutAuthorsParityTest.php`: exact `IS NULL` selection
  incl. hidden / unpublished, `0` and sheikh excluded, `id DESC`, 50 per page + numbering + OLD page URLs, title link,
  edit link with the canonical return (bare for page 1, also from `-page-1.htm`), no writes, 401 / 403, super-admin;
  return to the bare page and to page 2, NULL kept listed, author / «بدون شيخ» removed, T4 without `return_to` →
  item page, off-site / AdminCP / malformed `return_to` → item page, return kept across a validation failure);
  Anasheed + contextual suites 199/199; full suite 2,126 — 2,125 passed, 1 existing skip; PHPStan 0 errors; new files Pint-clean (pre-existing
  `routes/admin.php` import-order finding).
- **Owner manual review (2026-10-29), as super-admin — all PASS:**
  1. `/anasheed_without_authors.htm`: opened with no 401 / 403 / 500, in the site layout, title and RTL table
     correct, columns م / إسم المادة / تعديل, items listed, «تعديل» on every row.
  2. `/anasheed_without_authors-page-2.htm`: the legacy page URL worked, numbering started at 51 and continued, same
     layout, «تعديل» on every row.
  3. From page 2, «تعديل» on item #11751 «فضل يوم عاشوراء» opened the existing T4 form with the right item, the edit
     URL carried `return_to`, author «غير مصنف».
  4. Saved #11751 unchanged (author still «غير مصنف»): returned to `/anasheed_without_authors-page-2.htm` with the
     success message; the item stayed listed, at row 51.
  5. From page 2, item #11708 «خير أيام الدنيا» (`return_to` present, author «غير مصنف»): changed only the author to
     «بدون شيخ» and saved: returned to the list, and the item had left it — `author_id = 0` is not listed.
  6. T4 regression: item #9876 edited from its own item page via `/admincp/anasheed/items/9876/edit` (no
     `return_to`), saved unchanged: returned to `/var-item-9876.htm` — the owner-approved T4 redirect is unchanged and
     the contextual return is opt-in.
- **Status:** **MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)** (previously "IMPLEMENTED LOCALLY — AWAITING
  OWNER MANUAL REVIEW"), including the T4 contextual-return extension, manually regression-verified both from
  `without_authors` and without `return_to`. At the time, `anasheed_without_channels.htm` was not investigated (later done — #66). No local permission
  seeder run, no deployment, no commit.

---

## 66. Anasheed `anasheed_without_channels.htm` — MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)

- **OLD surface:** `.htaccess:507` / `:510` → `new_modules.php?name=Anasheed&op=anasheed_without_channels[&page=N]` →
  `modules/Anasheed/index.php:1074-1075` → `anasheed_without_channels()` (`:345-387`): gated by `is_w2a_admin()`
  (production: super-admin only, #64; otherwise a 200 «عفوا ، ليس لديك الصلاحية لدخول هذه الصفحة»), columns
  م / إسم المادة / تعديل (`without_{head,body,foot}.htm`), title → `var-item-{id}.htm`, one per-row control «تعديل» →
  `admin.php?op=AnasheedEditAnasheed&id={id}&redirct_to={$nukeurl}/anasheed_without_channels-page-{page}.htm`
  (`:372`, `:375`); no page-level controls. Linked from nowhere in OLD.
- **OLD query mismatch:** the count / `PS_Pagination` query is `channel_id IS NULL` (`:350`, `:359`), but with more
  than 50 such rows the listed rows come from `(channel_id = 0 OR channel_id IS NULL OR channel_id='') ORDER BY id DESC
  LIMIT …` (`:362`) — so each page shows NULL and 0 rows while the page links stop at ceil(NULL / 50), leaving the
  tail reachable only by a typed URL (`$page` is floored at 1, never capped; the pager's own `LIMIT` result is unused).
  With ≤ 50 NULL rows OLD would list NULL only. No hidden / published filter.
- **«تعديل» trace:** the same production-verified path as #65 (`admin.php` → `admin/case.php:21` → `EditAnasheed()`
  → `ConfirmEditAnasheed()`): Channel select `''` «غير مصنف» (NULL) / `0` «بدون قناة» / `nuke_sat_channels` ids
  (`admin/index.php:777-789`); save `Channel !== ''` → `(int)`, else NULL (`:894-898`, `:917`), no existence check;
  `redirct_to` re-emitted (`:757-759`) and honoured (`:939-940`) — a successful edit returned to the same list page.
- **Production evidence (owner-run, read-only, 2026-10-29):** `nuke_anasheed_anasheed.channel_id` is `int(11)`, NULL,
  default NULL; counts — NULL **8,532**, zero **2,025**, `channel_id = ''` comparison **2,025**, positive **6,082**,
  total **16,639**. Production is therefore always in the paginated branch: admins saw the NULL + 0 rows, and the
  mismatch is real on production; `= ''` matches exactly the `= 0` rows. Every source file on the path was already
  verified (#61, #64, #65).
- **Classification:** the page + pagination — PRESERVE_CANDIDATE; the listed set — owner decision (below); «تعديل»
  edit capability incl. channel NULL / 0 / id handling — ALREADY_PRESERVED (T4, #62); its entry point —
  PRESERVE_CANDIDATE; the return to the list page — PRESERVE_CANDIDATE, supported by the opt-in return of #65. Not
  copied: the NULL-only page count and unreachable tail, unescaped titles, `-page-.htm`, negative numbering, the blank
  out-of-range page, the 200 "no permission" page, the absolute `$nukeurl` target.
- **Owner decisions (2026-10-29):** (1) list exactly what OLD production showed — `channel_id IS NULL OR channel_id =
  0` (no separate `''` condition: impossible on `int(11) NULL`, same set as `0`) — and page **that same set**; positive
  channels excluded; hidden / unpublished included; (2) page permission `anasheed.editanasheed` (the page exposes only
  T4; the super-admin bypass keeps OLD access; no new permission); (3) a successful edit returns to the same list page,
  page 1 as the bare `/anasheed_without_channels.htm`, through the existing opt-in return; normal T4 unchanged.
- **Implementation:** routes `admin.anasheed.without-channels` (`GET /anasheed_without_channels.htm`) and
  `admin.anasheed.without-channels.paged` (`GET /anasheed_without_channels-page-{page}.htm`, numeric) behind
  `admin.role` + `admin.permission:anasheed.editanasheed`; `AnasheedWithoutChannelsController` (`whereNull('channel_id')
  ->orWhere('channel_id', 0)`, grouped, `id DESC`, one paginator over that set, 50 per page, OLD page URLs) and
  `admin/anasheed/without-channels.blade.php` in the site layout; per-row «تعديل» via
  `AnasheedWithoutChannelsControls::edit()` → `admin.anasheed.items.edit?return_to={canonical list page}`. **No T4 or
  contextual-return change:** the #65 opt-in (`<x-admin.contextual-return />` + `update()`'s `safeTarget()` fallback)
  already serves this page. No new write logic.
- **Verification:** 10 new tests (`tests/Feature/Admin/AnasheedWithoutChannelsParityTest.php`: NULL and 0 listed,
  positive excluded, hidden / unpublished NULL and 0 included, `id DESC`, paging over the combined set (70 NULL + 50
  zero → 3 pages, where OLD linked 2), 50 per page, numbering, OLD page URLs, empty-state out of range, title link,
  canonical return (bare for page 1, also from `-page-1.htm`), no writes, POST 405, 401 / 403, super-admin; return to
  the bare list and to page 2; NULL kept, NULL → 0 and 0 → NULL kept listed, a positive channel removed; T4 without
  `return_to` → item page; off-site / AdminCP / malformed `return_to` → item page; T4's channel existence validation
  kept, with the return surviving the redisplay); Anasheed + contextual suites (incl. without_cats / without_authors)
  209/209; full suite 2,136 — 2,135 passed, 1 existing skip; PHPStan 0 errors; new files Pint-clean (pre-existing `routes/admin.php` import-order finding).
- **Owner manual review (2026-10-29), as super-admin — all PASS:**
  1. `/anasheed_without_channels.htm`: opened with no 401 / 403 / 500, in the site layout, title «قائمة بالمنوعات التي
     لا تندرج تحت قناة بعينها», RTL table, columns م / إسم المادة / تعديل, items listed, «تعديل» on every row.
  2. `/anasheed_without_channels-page-2.htm`: the legacy page URL worked, numbering started at 51 and continued, same
     layout, «تعديل» on every row.
  3. From page 2, «تعديل» opened the existing T4 form; saved unchanged → returned to
     `/anasheed_without_channels-page-2.htm`, the item still listed.
  4. From page 2, another item changed to «بدون قناة» → returned to page 2, the item still listed (`channel_id = 0`
     stays in the work queue).
  5. From page 2, another item given a real channel → returned to page 2, the item gone (`channel_id > 0` excluded).
  6. T4 regression: an item edited from its own item page carried no `without_channels` return; the save returned to
     its `/var-item-{id}.htm` — the contextual return is opt-in and normal T4 is unchanged.

  Manually verified semantics: NULL → listed, 0 → listed, > 0 → not listed — the production-effective NULL + 0 set,
  without OLD's NULL-only page count.
- **Status:** **MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)** (previously "IMPLEMENTED LOCALLY — AWAITING
  OWNER MANUAL REVIEW"). No local permission seeder run, no deployment, no commit.

---

## 67. Anasheed OLD public admin-controls migration — COMPLETE — OWNER CLOSED (2026-10-29)

- **Final sweep (source-driven, 2026-10-29):** every `name=Anasheed` rewrite (`.htaccess:433-510`); both dispatchers
  (`new_modules.php:45-94` includes any `modules/Anasheed/{file}.php` without `..`; `modules.php` the same files); every
  reachable module file (`index.php` — all 26 functions, the switch `:1032-1084`, all 9 `is_w2a_admin` checks;
  `_index.php`; `Comments.php`; `friend.php`); all 79 Anasheed templates; `admin/index.php` / `admin/case.php` only to
  classify public-link destinations; blocks, `includes/index.php`, `way2allah_functions.php`, the theme; semantic searches
  for admin gates, `admin.php` / `$admin_file` links, `op=Anasheed…` / category / order / `anasheed_without` ops and
  Arabic control labels.
- **Reconciliation:** every reachable OLD public admin control maps to an existing decision — Group (#61: icon, edit,
  add item, add subgroup, safe delete approved; «ترتيب القسم» dead), Item (#62: T1, T4, T6, K1, K2 approved; mirrors
  Phase 1 and categories Phase 3 preserved; image edit / delete and banner upload / edit / delete dead — the latter
  `die('قيد التطوير')`, `admin/index.php:2555` / `:2576` / `:2610`; «اضافة الى قائمة الشير» and the `AdvancedStats`
  link-status block owner-retired), Main (#63, 0 candidates), `anasheed_without_cats` (#64), `anasheed_without_authors`
  (#65), `anasheed_without_channels` (#66). Newly confirmed dead: `ArrangeAnasheed()` / `AnasheedOrder` arrows
  (`index.php:726-753`, no caller anywhere); Anasheed's own `AdvancedStats()` (`:862-930`) has no caller in the module;
  the `Channel` op (rewrite and dispatch commented out, `.htaccess:491`). `Comments.php`, `friend.php` and the visitor ops
  carry no admin code; admin-side templates (`main_admin_menu`, `order_in_group_*`, `admin_add_to_share`, …) are rendered
  only by `admin.php` handlers; the `w2a_cd` / `w2acd` CD modules are separate modules, outside this sweep.
- **Initial result:** NEW_UNREVIEWED_CANDIDATE = **0**; PRODUCTION_EVIDENCE_REQUIRED = **1** — `modules/Anasheed/_index.php`,
  an older module copy reachable only by direct URL (`new_modules.php?name=Anasheed&file=_index&op=Group|Detailes…`),
  whose only distinct admin content is the CD panels (`group_cp_for_cd.htm`, `item_cp_cd.htm`, group 387) and a
  `cds_main` op that prints "cds main". Locally it fatals on PHP 8 before either admin block: `Group()` evaluates
  `$Group[title]` outside a string at `:86` (admin check `:87`), `Detailes()` `$Anasheed[id]` at `:234` (admin check
  `:239`) — `Error: Undefined constant`.
- **Production evidence (owner-run, read-only, 2026-10-29):** `sha256sum modules/Anasheed/_index.php` on
  `/home/php8way2allah/public_html` = `3ec5351009923b8a058a6071b0bc22e9834453aaa3559a09a2df77f732a0267f`, identical to the
  local copy; production runs PHP 8.1 (#61). **`_index.php` → DEAD_OR_UNREACHABLE.**
- **Final counts:** NEW_UNREVIEWED_CANDIDATE = **0**; PRODUCTION_EVIDENCE_REQUIRED = **0**.
- **Status:** **ANASHEED OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-10-29).** The individual
  statuses stand: Group CLOSED LOCALLY — OWNER APPROVED (#61); Item MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-28,
  #62); Main CLOSED — 0 preserve candidates (#63); `anasheed_without_cats` / `without_authors` / `without_channels` MANUAL
  REVIEW COMPLETE — OWNER APPROVED (2026-10-29, #64–#66). Anasheed visitor parity is a separate scope, not started;
  Anasheed News has no OLD public admin controls. Regression rule: Anasheed is not reopened without new concrete OLD /
  production evidence or a demonstrated regression. No code, test, database or permission change; no seeder run, no
  deployment, no commit. Telawah / Fatwa not started.
- **Historical note (2026-10-29):** "Telawah not started" was true at this closure; Telawah's OLD public admin-controls
  migration is now COMPLETE — OWNER CLOSED (2026-10-29, #68).

---

## 68. Telawah OLD public admin-controls migration — COMPLETE — OWNER CLOSED (2026-10-29)

- **Scope (owner, 2026-10-29):** the eight working OLD Telawah public admin controls that Business Confirmation #6 had
  excluded only as "handled by another dashboard" — no longer a valid reason under the 2026-10-21 rule. Source: OLD
  `modules/Telawah/index.php` (`Group()` `:164-186`, `AdvancedStats()` `:560-627`) and `modules/Telawah/admin/index.php`.
- **Production evidence (owner-run, read-only, 2026-10-29):** the Telawah module files, both language files and every
  control / form template match the investigated working copy; `modules/Telawah/admin/index.php` =
  `d93db419a7a33cdbff0dc6efc580c760fea96771ba6b1366ed595956774aaff4` (the working copy, not HEAD `1afbbcb7…525f223d`).
  `themes/way2allah/templates/Telawah/user_table.htm` is ABSENT. `nuke_telawah_groups` = id, title `varchar(150)` NULL,
  time NULL, hits / child / telawah NOT NULL DEFAULT 0, parent_id NULL, des NULL — no `count`, `author_id` or `weight`;
  `nuke_telawah_telawah` has no NOT NULL / no-default column the OLD insert omits. Items with `comments > 0` = 0;
  `nuke_telawah_comments` = 76 rows, all `view = 0`. (`way2allah_functions.php`'s production hash differs, which does
  not reopen C1: the template is absent and no item qualifies.)
- **Implemented (contextual, on the migrated public pages; `TelawahControls`, `TelawahGroupAdminController`,
  `TelawahItemAdminController`, `app/Domain/Admin/Actions/Telawah/*`):**

  | # | Surface / OLD label | Laravel route | Permission | Lands on |
  |---|---|---|---|---|
  | M1 | `recite.htm` «لوحة التحكم» → «إضافة قارىء» | `admin.telawah.main.groups.create/store` | `telawah.addgroup` | `/recite.htm` (OLD `Group&id=0`) |
  | G1 | group «لوحة تحكم الاقسام الفرعية» → «تعديل القسم» | `admin.telawah.groups.edit/update` | `telawah.editgroup` | the group |
  | G3 | → «إضافة تلاوة» | `admin.telawah.groups.items.create/store` | `telawah.addtelawah` | the group |
  | G4 | → «إضافة قسم فرعى» | `admin.telawah.groups.subgroups.create/store` | `telawah.addgroup` | the (parent) group |
  | G5 | → «حذف القسم» | `admin.telawah.groups.delete/destroy` | `telawah.delgroup` | `/recite.htm` |
  | I1 | item «لوحة تحكم المشرفين» → «تعديل» | `admin.telawah.items.edit/update` | `telawah.edittelawah` | the item |
  | I2 | → «مسح» | `admin.telawah.items.delete/destroy` | `telawah.deltelawah` | the former group (Main if it is gone) |
  | I3 | → «نقل إلى قسم شيخ أخر» | `admin.telawah.items.move/move.save` + `move-options.sub-groups` | `telawah.edittelawah` | the item |

  The item panel also carries the existing «حالة الوصلة» (§28.3 T1, unchanged). Fields are OLD's: groups title +
  comment (`des`); recitations title, link, size (MB → bytes, `× 1024 × 1024`), audio / video, sura
  (`nuke_telawah_sorahlist` 1–114, «لا يوجد» = 0), notes, and — edit only — group. I3 keeps OLD's two selects (reciter,
  then that reciter's sub-groups loaded by AJAX).
- **Owner-approved safety decisions:** (1) G1 never touches `parent_id` (OLD reset it to 0 through a field its form never
  rendered); (2) G5 refuses while the group has recitations (OLD) **or sub-groups** (added), cascades nothing, floors the
  parent's `child`; (3) I1 on a group change recomputes `parent_id` from the new group's hierarchy and moves both
  `telawah` counters (old floored at 0); I2 deletes, in one transaction, the item, its mirrors (`telawa_id`), its
  comments in every state (`khid`) and its `nuke_telawah_advanced` row (keyed by the item id — OLD `backup.php` cat 3),
  and floors the group counter; I3 validates the destination (a root reciter; an optional direct sub-group of it), updates
  `group_id`, recomputes `parent_id` and moves both counters — the item's own group is a no-op; (4) OLD's 3-second
  meta-refresh pages are replaced by direct redirects with the admin notice. I3's destination is the **item page** (OLD
  stayed on its own form with «تم نقل التلاوة إلى القسم الجديد»; the item page is where the control lives).
- **Further clear corrections (same bug policy):** writes to non-existent columns are not reproduced (`count`, `weight`
  on insert; `RepairCount()` on group / item edit — every statement it runs targets a column or table production lacks);
  the add-group title / comment are bound, not interpolated unescaped; an unchanged edit-form size keeps the stored bytes
  (OLD rewrote the 2-decimal prefill, rounding it — the approved Anasheed T4 rule); validation added where OLD would
  have failed under strict mode or created broken rows (title / link required; notes ≤ 255; size fits `linksize`; sura
  exists; I3 refuses «إختر الشيخ», which OLD saved as `group_id = 0`). Every mutation: authorized, one transaction with
  the row locked, audited (`telawah` module: `add_group`, `edit_group`, `del_group`, `add_item`, `edit_item`,
  `del_item`, `move_item` — the project's existing `del_*` form, as Khotab / Anasheed).
- **Not touched by I2:** groups and the sura list (shared); `nuke_backup_booking` (transient queue); `nuke_brokenlinks`
  (written only by Khotab, `divid = 1`; OLD Telawah's read of it is a copied Khotab query that collides with Khotab ids).
- **Not implemented (dead / retired):** M2 Main «إضافة تلاوة» (`group_id = 0`); G2 «نقل القسم» (no handler); G6–G8
  (HTML-commented); I4 «اضافة وصلة بديلة» (disabled by OLD, §29.1); I5 share queue (owner-retired, §28.1); I7 (commented
  code); C1 «مسح التعليق» — **DEAD_OR_UNREACHABLE on production evidence** (its `Comments()` renders only for an item with
  `comments > 0`, production has none, and its template `user_table.htm` is absent on production); C2 / C3 (no handler /
  no caller); O1 `ArrangeTelawah()` (no caller). Mirror edit / delete (§29.1) and the link-status page (§28.3 T1) stay
  closed and unchanged.
- **Permissions:** `telawah.addgroup` / `editgroup` / `delgroup` / `addtelawah` / `edittelawah` / `deltelawah` — OLD's
  own `nuke_telawah_mod` tokens — defined in `AdminPermissionSeeder` (definitions only; granted to nobody; seeder not
  run); super-admin bypass unchanged; no move token (owner mapping to `edittelawah`).
- **Verification:** `TelawahAdminControlsParityTest` 28 tests; related regression set (Telawah, contextual, mirror
  permissions, every Anasheed and Khotab panel test, every user of the extended Telawah fixture) 476 / 476; full suite
  2,164 tests — 2,163 passed, 1 skipped (pre-existing), 0 failed; PHPStan 0 errors; Pint clean on every changed file except `routes/admin.php`'s pre-existing
  import-order finding.
- **Implementation status (historical, superseded below):** IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.
- **Owner manual review (local, 2026-10-29) — PASSED; all eight controls OWNER APPROVED:**
  - **M1** — `recite.htm`: super-admin sees «لوحة التحكم» with «إضافة قارئ» only (no «إضافة تلاوة» on Main); a visitor /
    private window sees no panel; the form (name, comment) created a test reciter, redirected to `/recite.htm` and the
    reciter appeared in the listing; the same title again was refused, no duplicate created.
  - **Group panel** showed exactly G1, G3, G4, G5; no dead / unreachable Telawah control appeared.
  - **G1** — form has title and comment, no parent / move selector; comment edited with the title unchanged, saved,
    returned to the group, the group stayed in its hierarchy (OLD's accidental `parent_id = 0` reset confirmed absent).
  - **G4** — title / comment form, no parent selector; test sub-group created under the reciter; correct redirect /
    listing; same title under the same parent refused, no duplicate.
  - **G5** — three cases: a group with a sub-group → refused, both intact; a sub-group holding a recitation → refused;
    a genuinely empty sub-group → deleted. No cascade observed.
  - **G3** — form has sura, title, audio / video, link, size (MB), notes, scoped to the current group; test recitation
    created, redirect correct, listed in its group, then used for the item tests.
  - **Item panel** «لوحة تحكم المشرفين» showed I1, I2, I3 and the existing «حالة الوصلة»; no dead item control.
  - **I1** — form has sura, title, audio / video, link, size, notes, group. (A) notes-only edit saved, returned to the
    item, the value persisted (verified by reopening Edit — the public item page does not render notes), group
    unchanged; (B) group changed from the test sub-group to the parent reciter, saved, item confirmed in the new group.
  - **I3** — two-stage reciter → sub-group form; item moved back to the test sub-group, landed on the item page with
    «تم نقل التلاوة إلى القسم الجديد», confirmed in the sub-group; re-submitting the same reciter / sub-group caused no
    error, duplicate or disappearance.
  - **I2** — confirmation page; deletion succeeded, returned to the former group, item gone from the listing and no
    longer presented as existing (related-record cleanup and counter floors covered by the automated suite).
  - **Link-quality regression (PASS)** — «حالة الوصلة» from the shared panel on a real item opened the existing Telawah
    link-quality page (identity, URL, status data, «إعادة الفحص», «عرض التلاوة في الموقع»); no 404 / 500 / layout
    failure. Not reopened.
- **C1 «مسح التعليق» — final: DEAD_OR_UNREACHABLE** (`user_table.htm` absent on production; 0 items with
  `comments > 0`; all 76 `nuke_telawah_comments` rows `view = 0`). Not implemented.
- **Closure verification (2026-10-29):** no file changed after the implementation report; `TelawahAdminControlsParityTest`
  28 / 28 (430 assertions); PHPStan 0 errors. Full suite not re-run (working tree unchanged since its 2,164-test pass).
- **Status:** **TELAWAH OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-10-29).** Known local Telawah
  admin-control parity gaps: **0**. This is local migration parity closure only — production deployment NOT STARTED;
  no permission granted and no seeder run in production; no cutover. No commit. Fatwa not started.

## 69. Fatwa OLD public admin-controls migration — COMPLETE — OWNER CLOSED (2026-11-01)

- **Scope (owner, 2026-10-30):** the fourteen working OLD Fatwa public admin controls (the "externally owned"
  classification is superseded by the 2026-10-21 preservation rule). Source: OLD `modules/Fatwa/index.php`
  (`AdvancedStats()` `:379-433`, `all_Details()` `:864-948` + `details.htm`) and `modules/Fatwa/admin/index.php`.
- **Production evidence (owner-run, read-only):** `modules/Fatwa/admin/index.php` =
  `8e54214c6459b6322094d41137e5699101e29ea9fa0a5d320cd68147b20f9fa6` (= the working copy) — the eight
  PRODUCTION_EVIDENCE_REQUIRED controls became preserve candidates; no prefixless `_fatwa%` tables (T3 / Q3 stay dead);
  `place_of_fatwa` non-empty = 291; fatwas under more than one question = 0; questions in more than one topic = 3.
- **Implemented (contextual; `FatwaControls`, `FatwaTopicAdminController`, `FatwaGeneralQuestionAdminController`,
  `FatwaAdminController`, `app/Domain/Admin/Actions/Fatwa/*`; one permission `fatwa.manage` — owner decision 1):**

  | # | Surface / OLD label | Laravel route | Lands on |
  |---|---|---|---|
  | C1 | `fatawa-topics-{cat}` / `fatawa-category-{cat}` «اضافة موضوع للتصنيف» | `admin.fatwa.categories.topics.create/store` | the category page (OLD lost its `redire`) |
  | T1 | `fatawa-group-{t}-{c}` «اضافة سؤال للموضوع» | `admin.fatwa.topics.questions.create/store` | the topic page |
  | T2 | → «تعديل الموضوع» | `admin.fatwa.topics.edit/update` | the topic page |
  | Q1 | `fatawa-all-{q}` / `auther-all-fatawa-{a}-{q}` «اضافة فتوى للسؤال» | `admin.fatwa.questions.fatwas.create/store` | the page, `#fatwa` |
  | Q2 | → «تعديل السؤال» | `admin.fatwa.questions.edit/update` | the page |
  | Q4 | → «اضافة لموضوع أخر» | `admin.fatwa.questions.topics.add(.save)` | the page |
  | Q5 | → «نقل لموضوع أخر» (question in ≤ 1 topic) | `admin.fatwa.questions.topics.move(.save)` | the page |
  | Q6 | → «حذف من موضوع» (question in > 1 topic) | `admin.fatwa.questions.topics.remove(.save)` | the page |
  | A1 | per fatwa «اضافة الى اسئلة اخرى» | `admin.fatwa.fatwas.questions.add(.save)` | the page, `#fatwa` |
  | A2 | → «نقل الفتوى من سؤال لأخر» (fatwa under ≤ 1 question) | `admin.fatwa.fatwas.questions.move(.save)` | the page, `#fatwa` |
  | A3 | → «حذف الفتوى من على سؤال» (fatwa under > 1 question) | `admin.fatwa.fatwas.questions.remove(.save)` | the page, `#fatwa` |
  | A4 | → «تعديل» | `admin.fatwa.fatwas.edit/update` | the page, `#fatwa` |
  | A5 | → «حذف» (confirmation page) | `admin.fatwa.fatwas.delete/destroy` | the page |
  | X2 | Q1 / A4 size lookup (OLD `getfilesize`) | `admin.fatwa.media-size` (JSON) | — |

  "The page" = the validated `return_to` the control carried (`ContextualReturn::safeTarget`), else OLD's `redire`.
  Direct redirects with the admin-only notice; no meta-refresh.
- **Not implemented (dead in production):** C2, T3, Q3, D1–D7 (the single-fatwa page keeps no admin control), X1.
- **Owner-approved data-integrity corrections:** (1) `nuke_islamic_authors.fatwa` recounted from actual rows
  (`COUNT(*)`) for the sheikh(s) touched by Q1 / A5 / an A4 sheikh change — never negative, both sheikhs exact, inside the
  transaction; no historical sweep (`AuthorFatwaCounter`); (2) `nuke_fatwa_questions.topic_id` = first topic of the first
  general question, derived server-side on Q1 (not the panel's URL topic — OLD's last-topic value on `fatawa-all`) and
  A4, recomputed after A1 / A2 / A3 for the fatwa and after Q4 / Q5 / Q6 for every fatwa whose first question it is
  (`FatwaFilingTopic`); the 27 historical drifted rows are not swept (a touched row is re-filed); (3) Q4 / A1 append to
  the stored order (OLD's alphabetical rebuild not reproduced); Q6 / A3 keep the remaining order and refuse removing the
  last item server-side; Q5 / A2 refuse a multi-member subject server-side (OLD's own rule); (4) A4 never writes
  `place_of_fatwa`; media_size (DOUBLE, MB) keeps decimals and an unchanged prefill keeps the stored value; a cleared date
  is NULL and an unchanged date is not rewritten; (5) sheikhs, channels (0 = «بدون قناة»), topics and questions must
  exist — Q1 refuses «أختر اسم الشيخ» (0); (6) T1's duplicate check uses the target topic (OLD compared `|category|`);
  (7) C1 increments `q_count` once on the category and once on each ancestor (OLD gave the category +2) — Laravel reads
  `q_count` only as `> 0` and an activity order; (8) bound SQL, one transaction per operation with rows locked, audited
  (`fatwa` module: `add_topic`, `edit_topic`, `add_general_question`, `edit_general_question`, `add_fatwa`,
  `edit_fatwa`, `delete_fatwa`, `add_question_topics`, `move_question_topic`, `remove_question_topics`,
  `add_fatwa_questions`, `move_fatwa_question`, `remove_fatwa_questions`; free text digested).
- **X2:** `LinkSizeChecker::headContentLength()` (new method; `check()` unchanged) — a HEAD request (OLD's cURL
  `NOBODY`), http(s) only, 10 s timeout; MB truncated to 2 decimals as OLD's script. Failure returns null and the typed
  value is kept; the size is typed manually otherwise.
- **Blocker found and fixed (owner-approved, 2026-10-30).** (a) **R1 / R2 / R3 — membership readers.** Laravel's
  visitor readers used an exact `'|id|'` match (ported from Gen 2), OLD uses `LIKE '%|id|%'` (`questions()` `:498-500`,
  `all_questions()` `:566`, `all_fatawa_for_auther()` `:1313`, `get_num_of_fatawa_for_question()` `:453`). The exact
  match made the approved multi-membership controls unusable: A3 was never rendered, A1 hid the fatwa from every
  question page, Q4 hid the question from its topic lists. Now `fatwaGeneralQuestionsByTopic()` (rows and paginator
  count — one builder), `fatwaQuestionsForGeneralQuestion()` (`fatawa-all-{q}` and `auther-all-fatawa-{a}-{q}`) and
  `fatwaAnswerCountForGeneralQuestion()` use `FatwaPipeIds::whereContains()` — delimiter-aware (an int id between
  pipes: 12 matches `|12|`, `|7|12|45|`, never `|112|` / `|120|`). Every stored value is canonical (0 exceptions
  locally), so single-membership rows read exactly as before. (b) **`general_question_id` multi-value readers.** A1
  creates valid `|1|3|` relationships; readers that did `str_replace('|', '', …)` read that as question 13 — a different
  real question. Corrected, after tracing each call site: `fatwaGeneralQuestionIdsForAuthor()`,
  `fatwaQuestionsForChannel()` and `fatwaChannelAuthorsByGeneralQuestion()` (every id — OLD
  `all_questions_for_auther()` / `list_Fatawa_for_channel()` explode every id; the channel author is still the
  lowest-id answer containing the question); `fatwaDistinctQuestionCountsByAuthor()` (the aggregate stays the fast
  path, authors with a multi-question row are recounted from their parsed ids); and the contextless link builders
  `sidebar-ranking`, `fatawa/author-show`, `fatawa/day`, `fatawa/channel-show`, `search/results` (the first stored id,
  OLD `get_the_first_id()`). Already correct and unchanged: `HomeController::withFatwaLinkIds()`, `fatawa/latest`; the
  question page itself keeps its own question as context. A value holding one id reads exactly as before
  (`FatwaPipeIds`). Advanced-search readers untouched. `FatwaTopicControllerTest`'s test pinning the superseded
  Gen 2 exact match was rewritten to the approved membership semantics.
- **Separate follow-up — NOT fixed:** `(int) str_replace('|', '', …)` on a general question's `topic_id`
  (`FatwaQuestionController::renderAllAnswers()`, `ContentListingService` `fatwaQuestionsForChannel()` topic lookup,
  `fatwaTopicsKeyedById()`, `fatwaGeneralQuestionsByAuthor()` topic lookup) turns `|258|91|` into `25891`, so a
  multi-topic question page loses its breadcrumb and sidebars. Pre-existing, independent of the fixes above; awaits a
  separate owner approval.
- **Verification:** focused Fatwa admin-control + membership-reader + pipe-list tests 51 / 51; Fatwa / Fatawa / Search /
  Home / Contextual / Sidebar regression 562 / 562; full suite 2,215 tests — 2,214 passed, 1 skipped (pre-existing), 0
  failed; PHPStan level 5: 0 errors; Pint: new / Fatwa files pass (`ContentListingService.php` and
  `FatwaTopicControllerTest.php` already failed Pint at HEAD with the same fixers — not reformatted).
- **Implementation status (historical, superseded below):** FATWA ADMIN CONTROLS — IMPLEMENTED LOCALLY — AWAITING OWNER
  MANUAL REVIEW (2026-10-30).
- **Owner manual review (local, 2026-10-31 – 2026-11-01) — PASSED; all fourteen controls OWNER APPROVED:**
  - **C1** — topic created, returned to the contextual category page, the new topic listed.
  - **T1**, **T2**, **Q2** — passed.
  - **Q1** — fatwa created under the correct question; sheikh and answer rendered; manually typed `media_size` saved;
    «بدون قناة» saved; an empty fatwa date rendered as unknown; the fatwa's contextual controls appeared. The HTML
    `required` size on Q1 (and its absence on A4) was investigated and confirmed to match OLD; decimal acceptance is the
    approved correction.
  - **Q4** — the question became multi-topic, stayed listed under its original topic and appeared under the second; Q6
    became reachable (the membership-reader fix verified manually).
  - **Q5** — true move: the question left its topic for the destination and returned to a single-topic state.
  - **Q6** — removed only the selected topic, at least one remained, the question stayed listed there; Q6 disappeared
    once the question was single-topic again.
  - **A1** — test fatwa 20900 became `|7729|13565|`: still listed under its original question, listed under the added
    one, the first relationship kept first; A3 became reachable.
  - **A2** — fatwa 20900 moved from question 16475 to 7729: the old association gone, the destination listed it,
    `topic_id` re-filed 2349 → 111 by rule B; destination page verified visually.
  - **A3** — removed only the selected question from a multi-question fatwa; the remaining one intact; the fatwa not
    deleted.
  - **A4** — text edited; the fatwa stayed listed with its controls.
  - **A5** — test fatwa 20900 deleted.
  - **X2** — on the Add Fatwa form a real direct MP3 URL was entered in «رابط المادة»; on blur the lookup ran and filled
    «حجم المادة» with `12.19` MB (browser script + endpoint + real outbound HEAD); the fatwa was not saved.
- **Dead / unreachable — unchanged:** C2, T3, Q3, D1–D7, X1 (not implemented; not reclassified).
- **Closure verification (2026-11-01):** no application, route, view, test or config file changed after the green run
  above (newest source file 2026-10-30 20:31, test-result cache 20:35); the owner's review changed local DB data only.
  Tests not re-run.
- **Open follow-ups — NOT closed by this entry:**
  - **FATWA LEGACY-ROUTE PAGINATION FIX — the immediate next engineering task.** Found in owner review; pre-existing,
    confirmed real. `fatwaTopicsByCategory()` (`/fatawa-topics-{category}-{page}.htm`) and
    `fatwaGeneralQuestionsByTopic()` (`/fatawa-group-{topic}-{category}[-{page}].htm`) take the page from the legacy
    route segment (`paginate(25, ['*'], 'page', $page)`), but the views' `->links()` emit `?page=N` on the current path,
    which is ignored — the pager reloads the same page (locally: `/fatawa-topics-21-1.htm?page=2` / `?page=3` = page 1;
    `/fatawa-topics-21-2.htm` / `-3.htm` return the correct distinct sets). OLD `pagination()` builds segment URLs.
    Required: segment paginator URLs on both surfaces (precedent `AnasheedWithoutCatsController::withLegacyUrls()`);
    queries, routes and data semantics unchanged; regression tests that the links carry no `?page=N` and that the pages'
    result sets differ correctly. Not implemented here — implemented and owner closed in #70 (2026-11-01).
  - **Multi-topic `topic_id` cast bug** — the separate follow-up above (`|258|91|` → `25891`). Still open; not fixed. Implemented and owner closed in #71 (2026-11-01).
- **Boundary:** this closes the Fatwa OLD public admin-controls scope only. It does not close Fatwa visitor parity, the
  pagination bug or the cast bug, and does not start production deployment or cutover. Visitor behaviour was not
  intentionally broadened by this phase beyond the owner-approved membership-reader fix.
- **Status:** **FATWA OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-11-01).** All fourteen preserved
  controls implemented and owner manually approved. Known local Fatwa OLD-public-admin-control gaps: **0**. Local parity
  closure only — production deployment NOT STARTED; `fatwa.manage` granted to nobody and no seeder run in production; no
  historical data repair; no commit.


## 70. FATWA LEGACY-ROUTE PAGINATION FIX — COMPLETE — OWNER CLOSED (2026-11-01)

- **Scope:** the immediate follow-up recorded in #69 — only the pager link URLs of the two Fatwa legacy-route lists.
  Fatwa admin controls (#69) not reopened.
- **Root cause (current code):** `FatwaTopicController::show()` / `questions()` take `$page` from the route segment and
  pass it to `ContentListingService::fatwaTopicsByCategory()` / `fatwaGeneralQuestionsByTopic()`
  (`paginate(25, ['*'], 'page', $page)` — the explicit page wins over the request). The views
  (`fatawa/topics-show.blade.php`, `fatawa/questions.blade.php`) render `->links('components.content.premium-pagination')`,
  whose default URLs are the current path + `?page=N` — ignored, so every link reloaded the same page. OLD
  `pagination()` (`modules/Fatwa/index.php`:232-285) links to the segment.
- **Fix:** `FatwaTopicController::withLegacyUrls()` re-wraps the reader's own paginator (same rows, total, per-page,
  current page, path) in an anonymous `LengthAwarePaginator` subclass whose `url($page)` is
  `route('fatawa.topics.show', [category, page])` → `/fatawa-topics-{c}-{n}.htm`, or
  `route('fatawa.topics.questions.paged', [topic, category, page])` → `/fatawa-group-{t}-{c}-{n}.htm` — the project's
  existing pattern (`AnasheedWithoutCatsController::withLegacyUrls()`). Page 1 links to the explicit `-1.htm` form, as
  OLD's links did; `fatawa-category-{c}.htm` (page 1) pages on to `fatawa-topics-{c}-{n}.htm`. From a
  `fatawa-group-…-{n}.htm` page OLD's generic fallback built `-{n}-{i}.htm`, which no rewrite matched — not reproduced.
  Unchanged: routes, readers / queries, page size 25, order, membership filter, views, the pager component, admin
  panels.
- **Tests:** `tests/Feature/Content/FatwaLegacyPaginationTest.php` (9; deterministic fixtures — 60 + 5 interleaved
  topics, 31 member questions incl. a `|9|1|` multi-topic member and `|11|` / `|10|` non-members; the page does not read
  a question's own topic list, so the open cast bug is not exercised). The five link tests fail without the fix.
- **Verification:** new 9 / 9; Fatwa focused (Content Fatwa*, Admin Fatwa*, pipe list) 219 / 219; related regression
  (+ Search, Home, sidebar widgets, Contextual, Anasheed without-* pagers) 471 / 471; PHPStan level 5: 0 errors; Pint:
  the new test passes, the controller's only Pint rewrite is the pre-existing HEAD `\Illuminate\Support\Collection`
  import finding (not reformatted). Full suite not run (one controller, two lists).
- **Still open, separate:** the multi-topic `topic_id` cast bug (#69). Not touched.
- **Implementation status (historical, superseded below):** FATWA LEGACY-ROUTE PAGINATION FIX — IMPLEMENTED LOCALLY —
  AWAITING OWNER MANUAL REVIEW (2026-11-01).
- **Owner browser verification (local, 2026-11-01) — PASS:** opened `/fatawa-topics-21-1.htm`, clicked page 3 in the
  pager; the browser went to the route-segment page, the correct page was shown and no `?page=3` query-string problem
  remained ("worked perfectly"). No application code changed after the automated verification above.
- **Status:** **FATWA LEGACY-ROUTE PAGINATION FIX — COMPLETE — OWNER CLOSED (2026-11-01).** Implemented locally; automated 9 / 9, Fatwa focused
  219 / 219, related regression 471 / 471, PHPStan level 5: 0 errors; owner browser PASS. Known pagination gap on the
  two affected Fatwa surfaces: **0**. Local closure only — production deployment / cutover NOT STARTED; no seeder; no
  commit. The multi-topic `topic_id` cast bug (#69) stays **OPEN** — not part of this fix.


## 71. FATWA MULTI-TOPIC GENERAL-QUESTION topic_id FIX — COMPLETE — OWNER CLOSED (2026-11-01)

- **Scope:** the separate open follow-up recorded in #69 — readers that collapsed a general question's pipe topic list
  (`nuke_fatwa_general_questions.topic_id`, `|258|91|`) into one integer (`(int) str_replace('|', '', …)` → 25891). Fatwa
  admin controls (#69) and the pagination fix (#70) not reopened.
- **Audit — every Laravel reader of that column:**

  | Reader | Surface | Before | Now |
  |---|---|---|---|
  | `ContentListingService::fatwaGeneralQuestionsByTopic()` | `fatawa-group-{t}-{c}` | pipe-aware membership (`FatwaPipeIds::whereContains`, #69) | unchanged |
  | `ContentListingService::fatwaGeneralQuestionCountForTopic()` | topic badges on `fatawa-topics-{c}` | pipe-aware (`LIKE '%\|id\|%'`) | unchanged |
  | `FatwaQuestionController::renderAllAnswers()` | `fatawa-all-{q}`, `auther-all-fatawa-{a}-{q}` | **cast bug** | first topic |
  | `ContentListingService::fatwaQuestionsForChannel()` + `fatwaTopicsKeyedById()` | `fatawa-channel-{ch}[-{p}]` | **cast bug** | first topic |
  | `ContentListingService::fatwaGeneralQuestionsByAuthor()` | `auther-questions-{a}[-{p}]` | **cast bug** | first topic |
  | `ContentListingService::countGeneralQuestionsForTopic()` | advanced search topic results | scalar `topic_id = {id}` — OLD `modules/search` `get_num_of_general_questions_for_topic()`'s own `topic_id={$t_id}`, reproduced literally | unchanged (OLD behavior; advanced search out of scope) |
  | `ContentListingService::fatwaGeneralQuestionsAdvancedSearch()` | advanced search | raw pass-through (`tb1.*`; the view does not read `topic_id`) | unchanged |
  | Admin `PipeIdList` readers — `FatwaControls::question()`, `FatwaGeneralQuestionAdminController::topics()`, `GeneralQuestionTopicsAction`, `FatwaFilingTopic` | admin | pipe-aware | unchanged |

  `FatwaQuestionController::show()` (`fatawa-{id}`) reads `nuke_fatwa_questions.topic_id` — the fatwa's own integer filing
  topic (#69 rule B), not this list — and is unrelated.
- **OLD (`modules/Fatwa/index.php`):** a page showing one topic for a question takes its FIRST topic —
  `get_topic_id_for_general_question()` (`:360-372`, `explode('|')[1]`; used by `all_fatawa_for_auther()` `:1318`), the
  author list (`all_questions_for_auther()` `:1279-1285`) and the channel list (`list_Fatawa_for_channel()`
  `:1487-1493`); `all_questions()` (`fatawa-all`, `:564-660`) also computes the first topic (`$g_q_t`, `:572-575`) and
  renders one breadcrumb trail per topic. The Laravel `fatawa-all` layout is the owner-approved `answer2.php`
  reconstruction (one trail; Gen 2 `answer2.php:25` is where the `str_replace` cast came from).
- **Fix:** `FatwaPipeIds::firstId()` (`ids()[0] ?? 0`; a single-token value keeps the legacy reading, so single-topic
  rows render exactly as before; empty / `||` / `|0|` / non-numeric → 0, no topic) used at the four cast sites. No query,
  ordering, pagination, visibility, view or schema change; no data rewritten.
- **Tests:** `tests/Feature/Content/FatwaGeneralQuestionTopicTest.php` (9): the helper (`|258|91|` → 258, never 25891;
  `|91|258|` → 91; empty / malformed → 0); `fatawa-all` single, multi-first, multi-not-first, empty / malformed;
  `auther-all-fatawa`; author and channel list rows; topic membership (258 → 1, 2, 3; 91 → 2, 3; lookalike 25 and the
  concatenated 25891 → none). Topic 25891 exists in the fixture as the trap; the five surface tests fail with the old cast.
- **Verification:** new 9 / 9; Fatwa Content 177 / 177; Fatwa admin controls + pipe list 51 / 51; pagination 9 / 9;
  related regression 480 / 480; PHPStan level 5: 0 errors; Pint: the new test passes; `FatwaQuestionController.php` and
  `ContentListingService.php` report the same fixers as at HEAD (not reformatted). Full suite not run.
- **Recorded, not changed (visitor parity, separate):** OLD `fatawa-all` shows a breadcrumb trail for every topic of a
  multi-topic question, and its sidebars / admin panel used the LAST topic's category (a loop-variable leak); Laravel's
  owner-approved layout shows one trail, now the first topic, and the first topic's category sidebars. The channel list
  links a topicless question to `fatawa-group-0-0.htm` (pre-existing view fallback).
- **Implementation status (historical, superseded below):** FATWA MULTI-TOPIC GENERAL-QUESTION topic_id FIX — IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW (2026-11-01).
- **Owner browser verification (local, 2026-11-01) — PASS:** `/fatawa-all-6244.htm` (question 6244, `topic_id =
  |258|91|`) rendered the breadcrumb «فقه الجنايات والحدود» → «موضوع شبهات» → «حكم التزوير بالشهادات لمصلحة معينة؟» —
  topic 258, the first topic; no topic 25891 appeared. No application code changed after the automated verification.
- **Status:** **FATWA MULTI-TOPIC GENERAL-QUESTION topic_id FIX — COMPLETE — OWNER CLOSED (2026-11-01).** The confirmed `|258|91|` → `25891` cast defect is fixed on all four
  readers; automated verification green (above); owner browser PASS. Known local gaps for this cast defect: **0**. No
  schema change, no data rewrite. Local closure only — production deployment / cutover NOT STARTED; no seeder; no commit.
  The three visitor-parity observations above (multi-topic `fatawa-all` trails, OLD's last-topic sidebar / admin
  category, the channel list's `fatawa-group-0-0.htm` fallback) stay separate and NOT STARTED — not failures of this fix.


## 72. REMAINING OLD PUBLIC ADMIN-CONTROLS DISCOVERY — COMPLETE — AWAITING OWNER REVIEW OF INVENTORY (2026-11-01)

- **Scope:** discovery / classification only, under the owner's governing rule (every working OLD public contextual admin
  control is preserved unless owner-retired or proven dead / unreachable; "externally owned", BC#6, AdminCP-owned or
  duplicate are not exclusions). Khotab, Anasheed, Telawah, Fatwa (#60, #67, #68, #69) not reopened. Inventory and
  method: plan §43.
- **Result (58 scanned):** REVIEW_REQUIRED 3 — `w2acd`, `search`, `advanced_search`; ALREADY_PROVEN_PRESERVED 3 —
  `Surveys`, `Mobile`, `advfiles`; DEAD_OR_UNREACHABLE 3 — `Locations`, `BannersRequest`, `modules/index.php`;
  OWNER_RETIRED 0; NO_OLD_PUBLIC_ADMIN_CONTROLS 48; INSUFFICIENT_EVIDENCE 1 — `w2a_cd`.
- **W2ACD (REVIEW_REQUIRED, MEDIUM).** `modules/w2acd/index.php` `Group()` `:85-92` (`cds-main.htm` = `Group(0)`,
  `cds-group-{id}.htm`) renders `group_cp_for_cd.htm`: `W2ACDGroupIcon`, `W2ACDEditGroup`, `W2ACDInsertW2ACD`,
  `W2ACDAddGroup`, `W2ACDDeleteGroup`. `Detailes()` `:285-312` (`cds-item-{id}.htm`) renders `item_cp_cd.htm` —
  `W2ACDEditW2ACD` (تعديل, نقل), `W2ACDDeleteW2ACD`, `W2ACDWaitingFB` — and inline `W2ACDAdd_banner` / `Edit_banner` /
  `Delete_banner`. Laravel: item edit / delete (§28 W1–W2); «نقل» opens the same edit form. Missing: the three banner
  controls, the share-queue control, the group panel. §25.3 excluded those as "never used" (0 groups), "output not
  consumed" (banners) or "not CD administration" (share queue) — not exclusions under the rule unless dead. Owner inputs
  the review needs: whether the 2026-10-15 retirement of the Khotab / Anasheed / Telawah share queues covers
  `W2ACDWaitingFB` (not named); the banner path inconsistency (OLD writes `.gif`, its displays look for `.jpg`); a group
  panel when Laravel has no `cds-group` page; §28.1's owner exclusion of CD create on group pages stands. Dead, not
  candidates: per-mirror edit / delete and the comment edit / admin-comment form — `Mirror()` and `Comments()` have no
  caller. `Detailes()` fatals on PHP 8 at `:367` (`mktime()`) after the panel — a runtime defect (§26.5).
- **[SUPERSEDED 2026-11-03 by #79 / #80 — the source shows neither page can render the icon: `search` never selects `group_id`, `advanced_search` tests an unassigned `$group_id`; both DEAD_OR_UNREACHABLE.]**
- **search / advanced_search (REVIEW_REQUIRED, SMALL, one unit).** `modules/search/index.php` `media_series_view()`
  `:631-633` and `modules/advanced_search/index.php` `:209-212` emit, for an admin and a series inside a group,
  `$admin_file.php?op=KhotabUpSeries&id=…&group_id=…` into `series_cont.htm` (`$hook`). Handler live:
  `modules/Khotab/admin/index.php:174` → `UpSeries()`, preserved in Laravel as `DetachKhotabSeriesAction`
  (`admin.khotab…series.detach`, group page). Laravel's search surfaces carry no contextual control. Pass 3C counted these
  modules as "visibility only" and plan §2 row 19 covered only the Generation 2 copy. Both files fatal at line 2
  (`eregi()`) on PHP 8 — a defect, historically working (§26.5).
- **Already proven:** Surveys — OLD's four links (`polledit` ×2 on the list, `create` / `polledit` on results,
  `RemovePollComment`) = §28 P1–P3 + §30.2 «مسح»; Mobile — `DownloadsModDownload` on the six live listings = §28 M1;
  advfiles — credit + edit / delete on the file pages and beside listed items = §30.1. None relied on BC#6.
- **Dead / unreachable:** Locations — its admin code is only in `Detailes()` / `Mirror()` (no rewrite reaches them;
  `location-{l}-item-{k}.htm` → `name=Khotab&op=Detailes`, and `Detailes()` calls `AdminPanel()`, undefined in the module)
  and `ListCats()` (no caller); the reachable location item page is the Khotab item, which Laravel delegates to
  `KhotabItemController` with its controls. The earlier "eregi" reason is superseded; the conclusion stands on these
  grounds. BannersRequest — no rewrite, no inbound link, `nuke_modules.active = 0`, `nuke_islamic_bannerrequest` absent
  (local copy), and the cancel control is an AJAX call to `https://way2allah.com/modules.php`; Khotab «طلب بانر» is
  preserved. `modules/index.php` — an older Khotab copy reachable only as `new_modules.php` without `name`, unlinked, and
  its template folder (`templates/modules/`) does not exist.
- **[SUPERSEDED 2026-11-03 by #81 / #82 — `w2a_cd` evidence satisfied; WC1–WC6 DEAD_OR_UNREACHABLE; OWNER CLOSED.]**
- **Insufficient evidence:** `w2a_cd` — an Anasheed copy with Anasheed / CD panels, no rewrite, no inbound link,
  `nuke_modules.active = 1` but no Modules block in `nuke_blocks`, PHP 8 fatal at `:11`. Same class as Anasheed
  `_index.php` (#67): `sha256sum modules/w2a_cd/index.php` on production would settle it.
- **Prior conclusions invalid under the rule:** §28.1 "Everything EXTERNALLY_OWNED … stays excluded (88 operations)" as
  a blanket exclusion; §2 rows 8–13 / 15 as exclusions; §25.3's "never used / output not consumed / not CD administration"
  for reachable W2ACD controls; §2 row 22's "eregi ⇒ dead" reasoning (Locations; conclusion holds on other grounds);
  Pass 3C's "visibility only" for `search` / `advanced_search`.
- **Recorded, not controls:** admins see hidden content on Cat, Satellite channel and search pages (the Khotab owner
  decision of 2026-10-21 covered Khotab); Your_Account's admin greeting and AdminCP links on `login.htm`; closed-module
  copy files not explicitly dispositioned — `Fatwa/Before_shams_index.php`, `Fatwa/index_strange.php` (direct `file=`
  only), `Khotab/_index.php` ("underscore backup copy", pre-rule) — same class as Anasheed `_index.php`; not reopened.
- **Status:** **REMAINING OLD PUBLIC ADMIN-CONTROLS DISCOVERY — COMPLETE — AWAITING OWNER REVIEW OF INVENTORY.** Next recommended: W2ACD. No code changed, no OLD file changed, nothing implemented, no
  commit, no deployment, no production change.


## 73. W2ACD OLD PUBLIC ADMIN CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER DECISIONS (2026-11-01)

- **Scope:** page-by-page investigation of the first REVIEW_REQUIRED module from #72. Nothing implemented; search /
  advanced_search not started. Matrix with stable IDs (M1–M5, G1–G5, I1–I7, D1–D4): plan §44.
- **Pages:** `cds-main.htm` → `Group(0)`; `cds-group-{id}.htm` (+ `-page-{n}`) → `Group(id)`, linked from each sub-group
  card (`group_cd_content.htm`) and the breadcrumb (`Logo()`); `cds-item-{id}.htm` → `Detailes()`. Group pages show the
  admin panel, a series-download box (`parent_id > 0`), sub-groups (icon `media/w2acd/icons/…/{id}.jpg` when `icon = 1`)
  and the group's CDs (24 per page); groups nest by `parent_id`. Local: 0 groups, 39 CDs (all group 0), 1 with a banner.
- **Group panel at the root is mostly inert:** `GroupIcon(0)` prints «لم يتم العثور»; `ConfirmEditGroup(0)` updates no row;
  `ConfirmDeleteGroup(0)` finds 39 CDs and refuses (its `delgrouperror.htm` is missing, so the refusal renders an error,
  still without deleting). `AddGroup(0)` works — it creates a top-level group (`parent_id = 0`, `module_type = 'cd'`,
  duplicate title under the same parent refused, redirect to the absolute `https://way2allah.com/cds-group-0.htm`).
- **On a real group:** EditGroup changes title / parent (grandparent, parent or siblings) / description, overwrites
  `channel_id` with '' (no field), moves `child` counters; DeleteGroup refuses while CDs remain, otherwise deletes the row
  without checking sub-groups and without decrementing the parent's `child` (its `$Group` is never loaded), then goes to
  `cds-main`; GroupIcon uploads gif / jpg / png (≤ 10 000 bytes in the form, 40 000 in config) as `{id}.jpg`, sets
  `icon = 1`, returns to the parent group.
- **InsertW2ACD (M3, G3) — OWNER_RETIRED.** Plan §28.1, owner decisions of 2026-10-15: "CD create on `cds-group` pages —
  Intentionally absent — there is no Laravel public group page, and it is not moved to `cds-main`." An explicit owner
  decision covering both panels; its stated reason was structural (no Laravel group page), not external ownership. It
  predates the 2026-10-21 rule; not reopened.
- **Item:** edit (I1) and delete (I3) match Laravel's §25 operations (counter moves floored; edit returns to the item,
  delete to `cds-main` — OLD returned to the CD's group page, identical for group 0). «نقل» (I2) opens the same edit form;
  Laravel has the operation (group selector when groups exist) but no second control.
- **Share list (I4):** `AddToShare` / `ConfirmAddToShare` write `nuke_waiting_fb` and the cross-database
  `way2all_nuke.nuke_fb_autopost` — the same subsystem the retired `KhotabWaitingFB` / `AnasheedWaitingFB` /
  `TelawahWaitingFB` write, consumed only by the Facebook cron (`auto_post.php`, `fbcron/`); no public consumer; the local
  queue is empty. The 2026-10-15 retirement named only those three ops. Owner decision.
- **Banners (I5–I7):** upload `file2upl[]`, gif / jpg / png (`checkExt`, `PUB_MODE = 1`), ≤ 40 000 bytes, always saved as
  `media/w2acd/banner/{⌊id/1000⌋}/{id}.gif` with `banner = 1`; delete sets `banner = 0` and keeps the file. Consumers:
  `Codes()` (`cds-code-{id}-{type}.htm`, HTML / vBulletin / phpBB embed codes, linked from the admin banner preview) reads
  the `.gif` — works; `ListNews()` (`cds-news.htm`) builds `…/{id}.jpg` — never written by OLD, so a broken image unless a
  `.jpg` exists on disk. `add_banner`'s own preview looks in `media/banner/` (Khotab's folder). Operational; not dead.
- **Production evidence (read-only, optional — no classification depends on it):** group count; banner files on disk;
  share-queue use. Details in the investigation report.
- **Status:** **W2ACD OLD PUBLIC ADMIN CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER DECISIONS.** No code changed, no OLD file changed, nothing implemented, no commit, no deployment, no
  production change.


## 74. W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 1 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW (2026-11-01)

- **Owner decisions (2026-11-01), on the #73 matrix:** governing implementation rule — preserve OLD functionality,
  behavior and flow, implemented properly in Laravel; do not copy OLD defects; do not redesign; do not drop working
  OLD functionality because Laravel lacks it. **I4** «اضافة الى قائمة الشير» — OWNER_RETIRED (the retirement of the
  shared Facebook / share-list subsystem covers W2ACD). **I5 / I6 / I7** banners — PRESERVE, with OLD's consumer
  `cds-code-{id}-{type}.htm` restored; `cds-news.htm` excluded (its `.jpg` reader is separate visitor-parity / OLD-defect
  territory). **Public `cds-group-{id}.htm`** — restoration approved for a later phase. **OLD group defects** — corrected
  in the group phase (no orphaned sub-groups, correct counters, same-site redirects, proper validation / error
  responses). **I2** «نقل الإسطوانة» — PRESERVE (may reuse the edit form). **M3 / G3** «إضافة إسطوانة» — remain
  OWNER_RETIRED.
- **Phase 1 scope:** the existing `cds-item-{id}.htm` only — I2, I5, I6, I7 and `cds-code`. I1 / I3 unchanged; no
  group work; search / advanced_search untouched.
- **I2:** `PublicPageControls::cd()` adds «نقل الإسطوانة» → `admin.cds.edit` (with return_to), between «تعديل» and
  «حذف» as `item_cp_cd.htm` had it; the existing form's group selector and `UpdateCdReleaseAction` (both counters moved,
  floored — §25.5) are the move. No second implementation. The panel is titled «لوحة تحكم الاسطوانة» (OLD).
- **I5 / I6 / I7:** `CdBannerController` (`admin.cds.banner`, `.upload`, `.delete`, `.destroy`, under
  `admin.permission:w2acd.managecds`) + `CdBannerFiles` (authorized again inside, one locked transaction, audited
  `upload_banner` / `delete_banner`, module `w2acd`, entity `cd_release`). Controls: `banner = 0` → «رفع بانر»;
  otherwise «تعديل البانر» / «حذف البانر». Upload: gif / jpg / png (extension, detected MIME and decoded type), at most
  40 000 bytes (OLD `MAXFSIZE`) and 16 000 000 pixels; stored at OLD's `media/w2acd/banner/{floor(id/1000)}/{id}.gif`
  under the new `admin_cds.media_root` (default `public/media`), replacing any earlier file; `banner = 1`; back to the CD
  page with OLD's message. **Correction:** OLD renamed the bytes, so a jpg / png sat under a `.gif` name; now a GIF is
  kept byte for byte and a JPEG / PNG is re-encoded as a real GIF with GD (as `ThumbnailService`), transparency
  flattened onto white; written beside the target and renamed, so a failure never replaces the current banner. Delete:
  confirmation only for `banner = 1` with the file present (OLD), then `banner = 0`, file kept (OLD), back to the CD
  page. The upload page shows OLD's warning, the current banner and `banner.htm`'s three code links.
- **`cds-code-{id}-{type}.htm`:** public (`.htaccess:561`), `W2acdController::bannerCode()` + `w2acd/banner-code`,
  modelled on `khotab-code`. OLD `Codes()`: `banner = '1' AND hidden = '0'`; type 3 phpBB, 2 vBulletin, otherwise HTML;
  image `media/w2acd/banner/…/{id}.gif` at its own size; links to `cds-item-{id}.htm`. Corrected: only types 1–3 (OLD
  rendered HTML for any number), 404 for no CD / no banner / hidden / missing file (OLD printed an empty page or a broken
  code), every value escaped, the HTML caption keeps the title (OLD dropped it; its alt kept it), the vBulletin line has
  no author (CDs have none; OLD printed an empty one), the phpBB code is well-formed (OLD's last line had no opening
  tags).
- **Tests:** `tests/Feature/Admin/CdPagePublicAdminParityTest.php` (17); `CdAuthorizationAuditPhase6Test` extended to
  the four new routes (now 11) and to `CdBannerFiles` in the in-action permission check.
- **Verification:** new 17 / 17; CD authorization + new 34 / 34; whole `tests/Feature/Admin` + `W2acdControllerTest`
  1,037 / 1,037; PHPStan level 5: 0 errors; Pint: every new / changed PHP file passes except `routes/admin.php` /
  `routes/content.php`, whose `ordered_imports` finding is pre-existing at HEAD. Real local MySQL (read-only):
  `/cds-code-2-1.htm` 200 with CD 2's real banner, `/cds-code-1-1.htm` and `/cds-code-2-9.htm` 404.
- **Status:** **W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 1 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.** Not owner closed. No production change, no seeder, no commit.
- **Update (2026-11-02):** owner manual review complete — I2, I5, I6, I7 OWNER MANUAL PASS; see #75.

## 75. W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 1 OWNER MANUAL REVIEW COMPLETE; PHASE 2 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW (2026-11-02)

### A. Phase 1 — owner manual review (2026-11-02)

- **I2 «نقل الإسطوانة» — OWNER MANUAL PASS.** On `/cds-item-1.htm` the control appeared in «لوحة تحكم الاسطوانة» and
  opened the existing edit form at `/admincp/cds/1/edit?return_to=%2Fcds-item-1.htm`, which correctly represents the
  edit / move flow. Local data has no W2ACD groups, so the group selector had nothing to choose — a local-data
  limitation, not a failure (the move itself is covered by the automated I2 test).
- **I5 «رفع بانر» — OWNER MANUAL PASS.** Real browser upload; redirect to `/cds-item-1.htm` with the success message;
  the panel switched from «رفع بانر» to «تعديل البانر» + «حذف البانر».
- **I6 «تعديل البانر» — OWNER MANUAL PASS.** The first attempt looked unchanged to the owner. A read-only investigation
  showed the server side worked: audit #48 (I5: PNG → 2,802-byte GIF) and #49 (I6: PNG → 9,194-byte GIF); the request
  reached the handler; the file was atomically replaced; the write path and the served path are the same file
  (`public/media` → `legacy-project/media`); the browser was served the new bytes under a new `?v=` URL. The owner
  repeated I6 with a clearly different image and the banner visibly changed. **No I6 defect; no I6 code change.**
  Non-blocking hardening note only: the admin preview's `?v=` is the file mtime (one-second resolution); not changed.
- **I7 «حذف البانر» — OWNER MANUAL PASS.** The confirmation showed the current banner, the question and «نعم / لا»;
  confirming showed «تم حذف بانر المادة», returned to `/cds-item-1.htm`, removed «تعديل البانر» / «حذف البانر»,
  restored «رفع بانر», and left the other CD controls intact.
- **Phase 1 known local control gaps: 0.** Status: **W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 1 OWNER MANUAL REVIEW
  COMPLETE.** The W2ACD module is NOT owner closed (Phase 2 remains).

### B. Phase 2 — the public `cds-group-{id}.htm` page and its group controls

- **OLD source confirmed before coding** (`modules/w2acd/index.php` `Group()` / `ListGroup()` / `ListW2ACD()` /
  `Logo()`; `modules/w2acd/admin/index.php` group handlers; `themes/way2allah/templates/w2acd/group_cp_for_cd.htm`,
  `group_cd_content.htm`, `w2acd_cont.htm`, `listblock.htm`, `addgroup.htm`, `delgroup.htm`, `groupicon.htm`;
  `.htaccess:522-525`):
  - lookup by id, no visibility column on groups; title «مجموعة {title}»; breadcrumb «الاسطوانات الدعوية» → ancestors
    (linked) → the group (plain);
  - admin panel for `is_w2a_admin()`: «اضافة صورة للقسم», «تعديل القسم», «إضافة إسطوانة», «إضافة قسم فرعي», «حذف القسم»;
  - «تحميل سلسلة» box when `parent_id > 0` and the group has CDs (`DownSeries()`); ads position 9;
  - sub-groups `parent_id = id ORDER BY id DESC`, shown only when present; card = title, icon
    (`media/w2acd/icons/{⌊id/1000⌋}/{id}.jpg` when `icon = 1`, else `images/way2_cddefault.png`), «الأقسام الفرعية :
    {child} قسم», «الإسطوانات : {anasheed} إسطوانة» (a column this table lacks — always blank), «الزيارات : {hits}
    زيارة», «التعليق : {des | بدون تعليق}»;
  - CDs `group_id = id ORDER BY order_in_group DESC, mytime DESC`, **no `hidden` filter**, 24 a page, pages at
    `cds-group-{id}-page-{n}.htm`, shown only when present; then `hits + 1`.
  - Handlers: GroupIcon / UploadIcon — gif / jpg / png (`checkExt`), ≤ 10,000 bytes (the form's `MAX_FILE_SIZE`),
    raw bytes saved as `{id}.jpg`, `icon = 1`, back to the PARENT group (`Group(0)` = cds-main for a root); no icon
    delete. EditGroup / ConfirmEditGroup — title, parent (grandparent / parent / siblings), des; `module_type = 'cd'`;
    `channel_id` overwritten with an empty value; `child` −1 / +1 on a move; back to the group. AddGroup /
    ConfirmAddGroup — duplicate title under the same parent refused; insert parent_id, title, des, `module_type = 'cd'`;
    parent `child + 1`; back to the parent (this) group. DeleteGroup / ConfirmDeleteGroup — refused while CDs remain
    (`delgrouperror.htm` missing → error page); otherwise the row deleted with no sub-group check; the intended parent
    `child − 1` and return to the parent never happened (`$Group` unloaded) — always cds-main.
- **Controls implemented (discovery classification):** G1 «اضافة صورة للقسم» PRESERVE; G2 «تعديل القسم» PRESERVE;
  G4 «إضافة قسم فرعي» PRESERVE; G5 «حذف القسم» PRESERVE — `CdGroupControls::panel()`, OLD order and labels, panel
  «لوحة تحكم قسم الاسطوانات». **Not restored:** G3 «إضافة إسطوانة» (OWNER_RETIRED, §28.1); I4 stays OWNER_RETIRED;
  M1–M5 (`cds-main` panel; M4 PRESERVE is on `cds-main`, outside this phase's surface — not started).
- **Public page:** `W2acdController::group()` + `w2acd/group.blade.php`; routes `w2acd.group` (`/cds-group-{group}.htm`)
  and `w2acd.group.paged` (`/cds-group-{group}-page-{page}.htm`). The CD query is now one private method shared with
  `cds-main` (`releasesInGroup()`), and the CD card grid one partial (`w2acd/_cd-grid.blade.php`) — `cds-main` output
  unchanged. `W2acdGroup::breadcrumbTrail()` (ancestor walk, cycle-safe). `CdGroupIcon` (path, disk path, cache-busted
  URL, default image). Corrected, not reproduced: unknown group → 404; `cds-group-0.htm` → 301 `/cds-main.htm` (OLD's
  `Group(0)`); the card's CD / sub-group counts are live counts (OLD printed a non-existent column); `icon = 1` with a
  missing file shows the default; pager links go to the `-page-{n}.htm` segment (the #70 pattern). Kept: no `hidden`
  filter (as OLD and the existing `cds-main`); `hits + 1`.
- **G1 icon:** `CdGroupAdminController::icon` / `uploadIcon` + `CdGroupIconFiles` — gif / jpg / png by extension,
  detected MIME and decoded image type; ≤ 10,000 bytes; ≤ 16,000,000 pixels; stored at OLD's
  `media/w2acd/icons/{⌊id/1000⌋}/{id}.jpg` under `admin_cds.media_root`; overwrite; `icon = 1`; audit
  `upload_group_icon`; back to the parent group (cds-main for a root) with OLD's message. **Correction (as the Phase 1
  banner):** a JPEG is kept byte for byte, a GIF / PNG is re-encoded as a real JPEG (GD, flattened onto white) — OLD
  stored the raw bytes under `.jpg`; written beside the target and renamed. No icon delete (OLD had none).
- **G2 edit:** `UpdateCdGroupAction` + `CdGroupParents` — title (required, ≤ 100, the column), parent (only OLD's
  offered set: «بدون مجموعة» / grandparent, current parent, siblings — never a descendant, so no cycle), des;
  `module_type = 'cd'`; `child` −1 (floored) / +1 on a move; audit `edit_group`; back to the group with «تم تعديل القسم».
  **Correction:** `channel_id` is left untouched (OLD blanked it).
- **G4 add sub-group:** `InsertCdSubGroupAction` — parent = the bound group (never a posted id), duplicate title under the
  same parent refused with OLD's message, title required (≤ 100); insert parent_id, title, des, `module_type = 'cd'`,
  `size = 0`, `dur = 0` (NOT NULL without default — OLD relied on non-strict MySQL); parent `child + 1`; audit
  `add_group`; back to this group.
- **G5 delete:** `DeleteCdGroupAction` — **the Anasheed group semantic the owner approved on 2026-10-23
  (`DeleteAnasheedGroupAction`), reused, so no new owner decision was needed:** neither → deleted, parent `child − 1`
  (floored), back to the parent group (cds-main for a root); CDs → refused («… يجب عليك أولا مسح أو نقل جميع
  الإسطوانات الخاصة بهذا القسم»); sub-groups → refused («… جميع الأقسام الفرعية …»); both → refused (CD message,
  OLD's check order). Nothing is cascade-deleted, no sub-group is orphaned, no CD is moved; the icon file is kept (as
  OLD). Audit `del_group`. One locked transaction.
- **Authorization:** all eight routes `admin.cds.groups.*` sit in the existing `admin.permission:w2acd.managecds` group
  (super-admin passes); every action re-checks `w2acd.managecds` through `AdminRecordAuthorizer`; the panel renders only
  for admins who can reach the target routes (`AdminRouteAccess`); visitors' page has no panel. Mutations are CSRF
  POST / PUT / DELETE; OLD's absolute `https://way2allah.com/…` redirects are site-relative.
- **OLD defects found and corrected (no business change):** sub-group card CD count read a non-existent column;
  ConfirmEditGroup blanked `channel_id`; ConfirmAddGroup omitted NOT NULL `size` / `dur`; ConfirmDeleteGroup orphaned
  sub-groups, skipped the parent counter and always went to cds-main; `delgrouperror.htm` missing; icon bytes renamed
  to `.jpg`; absolute redirects; unvalidated parent ids; `Logo()` loops forever on a parent cycle.
- **Not restored — needs an owner decision if wanted:** the «تحميل سلسلة» box / `cds-series-{id}.grx` (`DownSeries()`
  lists Anasheed `var-download-{id}.htm` links built from CD ids — broken in OLD) and ads position 9. Also noted, not
  changed: Laravel's `cds-main.htm` lists only group-0 CDs, not OLD `Group(0)`'s top-level group cards, so `cds-group`
  pages are reached by URL / breadcrumb only until the `cds-main` surface (M4 + group cards) is taken up.
- **Tests:** `tests/Feature/Admin/CdGroupPagePublicAdminParityTest.php` (33 incl. datasets): page, breadcrumb, orders,
  no hidden filter, hits, empty group, 404 / group 0, 24-per-page segment links, icon / default; visitor / unauthorized /
  manager / super-admin panels, OLD order, no G3, no return_to; every route forbidden and nothing written for a
  non-manager; in-action re-checks; G1 JPEG byte-for-byte, PNG / GIF → JPEG, replacement (hash + URL change, no temp
  file), four rejections; G2 parent options, update + `channel_id` kept, sibling move counters, descendant / empty
  title refused; G4 create + counter + page, explicit `size` / `dur`, duplicate / empty refused; G5 empty delete +
  counter + redirect, root → cds-main, floored counter, CDs / sub-groups / both refused with nothing changed, icon file
  kept. A mutation check (sub-group guard removed) fails the suite. `CdAuthorizationAuditPhase6Test` extended to the
  eight routes (now 19) and the four group actions. Fixture `nukeW2acdGroups` gains the real columns time, child, size,
  dur, author_id, channel_id, icon.
- **Verification:** new 33 / 33; `CdAuthorizationAuditPhase6Test` 17 / 17; `tests/Feature/Admin` + `tests/Feature/Content`
  2,050 passed, 1 skipped (pre-existing); `tests/Feature/Pages` + `tests/Feature/Support` + `tests/Unit` 180 / 180;
  PHPStan level 5: 0 errors; Pint: new / changed files pass except the pre-existing `ordered_imports` finding in
  `routes/admin.php` / `routes/content.php`.
- **Local manual review:** the local database has 0 W2ACD groups (all 39 CDs in group 0), so no group page exists yet;
  a one-row local-only insert is proposed to the owner and not run without approval.
- **Local manual review (2026-11-02):** owner approved the one-row local insert (group #1). G4 create proven manually
  (child «قسم فرعي تجريبي» under group #1). Owner flagged the sub-group card; a read-only OLD comparison
  (`group_cd_content.htm`, `css/style.css` `.var_group` / `a.cd_bg_group img`) found a **visual parity defect**: the
  card reused the shared Anasheed 48×48 font-icon box, so the 75×100 image overflowed onto the title, and the three
  counts sat in one horizontal row. **Fixed (view + scoped CSS only):** the card keeps the three-column grid and frame;
  inside, OLD's order — the title on its own line, then the 89×88 icon (own box, `overflow:hidden`, `object-fit:cover`)
  on the right with four stacked lines: sub-groups, CDs (the live count — OLD's `$Group[anasheed]` was always blank),
  visits, «التعليق : …» / «بدون تعليق». W2ACD-only classes (`w2a-cd-subgroup-*`) under `.w2a-cds-page` in
  `content-refresh.css`; the shared Anasheed card is untouched. Not restored: `group_cd.png` frame, `Graphic()` badge.
  New test (order + comment label): `CdGroupPagePublicAdminParityTest` 34 / 34. G4 OWNER MANUAL PASS still waits for
  the owner's visual re-check.
- **Status:** **W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 2 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.** Not
  owner closed. No production change, no seeder, no commit, no deployment; search / advanced_search untouched.
- **Update (2026-11-03):** owner manual review complete — G1, G2, G4, G5 OWNER MANUAL PASS; see #76.

---

## 76. W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 2 OWNER MANUAL REVIEW COMPLETE (2026-11-03)

Owner manual review of Phase 2 (#75 B) on the local group #1 created for it (#75 B, owner-approved one-row insert).
Documentation only — no application code, data, production, commit or deployment change in this step.

- **G1 «اضافة صورة للقسم» — OWNER MANUAL PASS.** The form opened for group #1; a real image was uploaded and saved;
  the uploaded image displayed correctly.
- **G2 «تعديل القسم» — OWNER MANUAL PASS.** The form opened; group #1 renamed «قسم تجريبي محلي» → «قسم تجريبي محلي
  معدل»; the save succeeded; the new title appeared on the public group page and in the breadcrumb.
- **G4 «إضافة قسم فرعي» — OWNER MANUAL PASS.** The form opened under group #1; «قسم فرعي تجريبي» (#2) was created and
  appeared under the parent. After the sub-group card visual parity correction (#75 B), the owner re-reviewed the card:
  **the corrected card layout is OWNER APPROVED.**
- **G5 «حذف القسم» — OWNER MANUAL PASS**, both paths:
  - *Refusal:* deleting group #1 while it held «قسم فرعي تجريبي» was refused with «عفواً .... لن تتمكن من مسح '' قسم
    تجريبي محلي معدل '' من قاعدة بيانات الموقع - يجب عليك أولا مسح أو نقل جميع الأقسام الفرعية الخاصة بهذا القسم»;
    the parent stayed intact.
  - *Success:* on sub-group #2 the confirmation page rendered; confirming deleted it, redirected to the parent
    `/cds-group-1.htm` with the success message for «قسم فرعي تجريبي», and the card disappeared from the parent page.
- **Phase 2 known local admin-control gaps: 0.**
- **Preserved, unchanged:** Phase 1 (#75 A) — I2, I5, I6, I7 OWNER MANUAL PASS, Phase 1 known local gaps 0; the I6
  first-attempt investigation stays resolved with no application defect; the mtime `?v=` hardening note stays
  non-blocking. G3 «إضافة إسطوانة» and I4 (share list) stay OWNER_RETIRED.
- **Kept separate (not admin-control gaps, not started):** the «تحميل سلسلة» series box (`DownSeries()`, broken in OLD)
  and ads position 9 — owner decision if wanted; Laravel `cds-main.htm` shows no top-level group cards (OLD `Group(0)`
  did) — visitor-parity observation.
- **Status:** **W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 2 OWNER MANUAL REVIEW COMPLETE.** Phase 2 closed. The W2ACD
  module is **NOT owner closed**: the `cds-main` panel (M1–M5; M4 «إضافة قسم» PRESERVE) is not started. No next phase
  started.

---

## 77. W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 3 (`cds-main` panel, M4) IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW (2026-11-03)

- **OLD source re-verified before coding.** `.htaccess:516` `^cds-main.htm` → `new_modules.php?name=w2acd` (no `op`) →
  `modules/w2acd/index.php` `default: Group(0)`. `Group(0)` renders «لوحة تحكم قسم الاسطوانات» for `is_w2a_admin()`
  (session / signed cookie — `new_modules.php`'s `$admin = false` does not affect it; the same gate as the owner-proven
  `cds-group` panel) with the **same** template as group pages, `group_cp_for_cd.htm`, and `$Group['id'] = 0`.
  Admin handlers: `modules/w2acd/admin/index.php`.

  | ID | OLD label | OLD URL / handler | With id 0 | Classification |
  |---|---|---|---|---|
  | M1 | اضافة صورة للقسم | `admin.php?op=W2ACDGroupIcon&id=0` → `GroupIcon(0)` | no row → «لم يتم العثور على هذه المادة …» | DEAD_OR_UNREACHABLE (confirmed) |
  | M2 | تعديل القسم | `op=W2ACDEditGroup&id=0` → `EditGroup` / `ConfirmEditGroup` | empty form; `$Group[id]` is null ≠ '0', so no UPDATE; redirect only | DEAD_OR_UNREACHABLE (confirmed) |
  | M3 | إضافة إسطوانة | `op=W2ACDInsertW2ACD&group_id=0` → `InsertW2ACD(0)` | works (a CD in group 0) | OWNER_RETIRED (§28.1, unchanged) |
  | M4 | إضافة قسم فرعي | `op=W2ACDAddGroup&id=0` → `AddGroup(0)` / `ConfirmAddGroup` | **creates a top-level group** | PRESERVE (confirmed) |
  | M5 | حذف القسم | `op=W2ACDDeleteGroup&id=0` → `DeleteGroup` / `ConfirmDeleteGroup` | refused while group 0 holds CDs; else `DELETE … id = 0` — no row | DEAD_OR_UNREACHABLE (confirmed) |

  The OLD label on `cds-main` is «إضافة قسم فرعي» (shared template), not «إضافة قسم»; kept verbatim. No source
  contradicts the §44 classification.
- **OLD M4 behaviour.** `AddGroup(0)`: `addgroup.htm` — «عنوان القسم» (`Title`), a disabled «القسم الرئيسي» (bound to
  an undefined `$author[name]`, so empty), «التعليق على القسم» (`Des`), hidden `id = 0`, button «أضــف قسم فرعي».
  `ConfirmAddGroup($Title, 0, $Des, $Channel)`: refuses when `title = Title AND parent_id = '0'` exists (error page
  «عفواً .... لن تتمكن من إضافة قسم '' … '' …»); otherwise `INSERT parent_id = 0, title, des, module_type = 'cd'`,
  `child = child + 1 WHERE id = 0` (no row — no effect), redirect to `https://way2allah.com/cds-group-0.htm` (= OLD
  `Group(0)`, the `cds-main` content).
- **Implementation.** `CdGroupControls::mainPanel()` (M4 only) on `cds-main.htm` (route `w2acd.index.pretty` only —
  not Gen 2's `/w2acd/cds.php`), inside `@contextualAdmin`. Routes `admin.cds.groups.create` (GET
  `admincp/cds/groups/create`) / `admin.cds.groups.store` (POST `admincp/cds/groups`) in the existing
  `admin.permission:w2acd.managecds` group. `CdGroupAdminController::createRootGroup` / `storeRootGroup` (validation
  shared with G4 via `validatedGroup()`); the Phase 2 form `admin/cds/groups/subgroup-create.blade.php` reused with no
  parent. `InsertCdSubGroupAction::executeRoot()` — G4's `execute()` keeps the same steps (duplicate check, insert,
  parent `child + 1`, audit), now via shared private `insert()` / `recordAudit()`.
- **Corrections (OLD defects not reproduced):** authorized (`w2acd.managecds`; OLD's `GreenZone()` was inert) and
  re-checked in the action; parameter bindings (OLD interpolated `Title` / `Des` into SQL); `size` / `dur` written as 0
  (NOT NULL, no default — Phase 2's strict-DB handling); OLD's `print_r($Group)` debug output dropped; the no-op
  `child + 1 WHERE id = 0` not issued; the parent is always 0 — any posted `id` / `parent_id` is ignored; the empty
  «القسم الرئيسي» shows «بدون مجموعة» (OLD's own label for parent 0 in `EditGroup`); a real validation message instead
  of OLD's error page with `history.go(-1)`; site-relative redirect straight to `/cds-main.htm` (OLD's absolute
  `cds-group-0.htm`, which Laravel 301s there) with the flash «تمت إضافة القسم '' … ''»; insert + audit in one
  transaction; audit `add_group`, `parent_id = 0`.
- **Tests:** new `CdMainPublicAdminParityTest` 10 / 10 — visitor / non-manager see no panel; manager and super-admin
  see M4 only (no M1 / M2 / M3 / M5 labels, no `/groups/0` links); no panel on `/w2acd/cds.php`; guest 401, non-manager
  403 on both routes with nothing written; action re-check; form (OLD labels, «بدون مجموعة», back link); create —
  parent 0, OLD columns, `size` / `dur` / `child` / `hits` / `icon` 0, existing rows and CDs unchanged, audit, redirect
  + flash, the new group's page opens; explicit `size` / `dur` in the INSERT; duplicate top-level title (OLD message)
  and empty title refused; a title used only by a sub-group is not a duplicate. Mutation (action authorization
  removed) fails the suite. `CdAuthorizationAuditPhase6Test` extended to 21 routes and `executeRoot()`.
- **Verification:** W2ACD (Phase 1 + 2 + 3 + Phase 6) 78 / 78; `tests/Feature/Admin` + `tests/Feature/Content` 2,061
  passed, 1 skipped (pre-existing); Pages + Support + Unit 180 / 180; PHPStan level 5: 0 errors; Pint: changed PHP
  files pass.
- **Unchanged / not started:** Phase 1 and Phase 2 behaviour; the «تحميل سلسلة» box and ads position 9; `cds-main`
  top-level group cards (visitor parity — a new top-level group is reachable at `/cds-group-{id}.htm`, not listed on
  `cds-main`); search / advanced_search; `w2a_cd`. No local test data created.
- **Status:** **W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 3 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.** Not
  owner pass; module NOT owner closed. No production change, no commit, no deployment.
- **Update (2026-11-03):** M4 OWNER MANUAL PASS; W2ACD OLD public admin controls OWNER CLOSED — see #78.

---

## 78. W2ACD OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-11-03)

Documentation only — no application code, data, production, commit or deployment change in this step. The local
manual-test groups (#1 and the top-level «قسم رئيسي تجريبي») are left as they are.

- **Phase 3 owner manual review — M4 «إضافة قسم فرعي» on `cds-main`: OWNER MANUAL PASS.** On `/cds-main.htm` the panel
  «لوحة تحكم قسم الاسطوانات» rendered with only «إضافة قسم فرعي» (M1 / M2 / M3 / M5 absent, as classified); the form
  showed «عنوان القسم», «القسم الرئيسي: بدون مجموعة», «التعليق على القسم» and «أضــف قسم فرعي»; a real local
  top-level group «قسم رئيسي تجريبي» was submitted; the admin returned to `/cds-main.htm` with «تمت إضافة القسم '' قسم
  رئيسي تجريبي ''» and the panel unchanged. **W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 3 OWNER MANUAL REVIEW
  COMPLETE.** Phase 3 known local admin-control gaps: 0.
- **Locked results (unchanged):** Phase 1 (#75 A) — I2, I5, I6, I7 OWNER MANUAL PASS, gaps 0; the I6 first attempt
  resolved with no application defect; the mtime `?v=` note non-blocking hardening only. Phase 2 (#76) — G1, G2, G4,
  G5 OWNER MANUAL PASS, gaps 0. Phase 3 — M4 OWNER MANUAL PASS, gaps 0.
- **Final control table (§44 matrix, all 21 rows dispositioned):**

  | IDs | Disposition |
  |---|---|
  | M4; G1, G2, G4, G5; I2, I5, I6, I7 | PRESERVE — implemented, OWNER MANUAL PASS |
  | I1, I3 | ALREADY_PRESERVED (plan §28 W1 / W2) |
  | M3, G3, I4 | OWNER_RETIRED |
  | M1, M2, M5; D1–D4 | DEAD_OR_UNREACHABLE (M1 / M2 / M5 re-verified from source, #77) |

  Supporting public pages restored in scope: `cds-code-{id}-{type}.htm` (Phase 1), `cds-group-{id}.htm` (Phase 2).
- **Known local W2ACD OLD-public-admin-control gaps: 0.**
- **Status:** **W2ACD OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-11-03).** Local parity closure
  only — production deployment, `w2acd.managecds` grants and seeder runs NOT STARTED. Not W2ACD visitor parity.
- **Separate, not blockers, statuses unchanged (not started / not investigated here):** the «تحميل سلسلة» series box
  (`DownSeries()`, broken in OLD) and ads position 9 — owner decision if wanted; `cds-main` visitor parity (no
  top-level group cards; top-level groups reachable by `/cds-group-{id}.htm`); `cds-news.htm` excluded (#74);
  `search` + `advanced_search` — REVIEW_REQUIRED (#72); `w2a_cd` — INSUFFICIENT_EVIDENCE, one production SHA check
  needed (#72).
- **Next per plan §43's recommended order:** `search` + `advanced_search` as one review unit (the `KhotabUpSeries`
  «out» icon; investigation first). Not started.

---

## 79. SEARCH + ADVANCED_SEARCH OLD PUBLIC ADMIN-CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER REVIEW (2026-11-03)

Investigation only — no application code, data, production, commit or deployment change. Source: OLD `.htaccess:149` /
`:154`, `new_modules.php`, `modules/search/index.php`, `modules/advanced_search/index.php`,
`themes/way2allah/templates/{search,advanced_search}/*.htm`, `js/inc.js:38-120`, `way2allah_functions.php`
`apply_search()`, `modules/Khotab/admin/index.php:174` / `:2210-2222`. Local `nuke_islamic_series` read (SELECT only).

- **Entry points.** `^search.htm*` → `new_modules.php?name=search` → `modules/search/index.php` (class `Search`;
  sitewide header form + `new_advanced_search_mawad()` / `new_advanced_search_series()` POSTs with `kh_dept`).
  `^advanced_search.htm` → `new_modules.php?name=advanced_search&op=main_search` → `modules/advanced_search/index.php`
  `Main_search()` → `ListSeries()` / `ListKhotab()` (reached by the «بحث متقدم» form of `apply_search()` on Khotab
  author pages `modules/Khotab/index.php:261` and Cat pages `modules/Cat/index.php:132`, and by
  `advanced_search_series()` / `advanced_search_khotab()` — `mode:"ajax_khotab"` *is* matched by this module).
  `advanced_search/index.php:2` calls `eregi()` — a fatal on production PHP 8.1, a runtime defect under §26.5, **not**
  used as a reason below.
- **Every admin-gated output (both modules and all their templates grepped):** search `:486` / `:776` / `:829`,
  advanced_search `:140` / `:293` — hidden-content visibility (SQL filter + «++مخفي++»), not controls (recorded as such
  in #72); search `:631-632` and advanced_search `:209-211` — the «out» icon. No template contains admin markup except
  `series_cont.htm`'s `<td>$hook</td>` (both folders).
- **S1 — `search.htm` series «out» icon (`images/out.png` → `admin.php?op=KhotabUpSeries&id={series}&group_id={group}`).**
  Rendered only by `media_series_view()` (departments video / audio / dumped_files) when
  `is_w2a_admin($admin) && $Series["group_id"] > 0`. The row comes from `ListSeries()` with `series_fields =
  "tb1.id,tb1.title,tb1.time,tb1.count,tb1.hidden,tb1.channel_id,tb1.author_id"` (`media_search()`, `:153`) plus
  `tb2.name, tb2.prename` — **`group_id` is never selected**, so `$Series["group_id"]` is always unset (null) and the
  condition is false on any PHP version, for any data. The column exists and is used (local: 3,175 of 14,552 series have
  `group_id > 0`), so this is not a local-data argument. **DEAD_OR_UNREACHABLE (source-proven).**
- **A1 — `advanced_search.htm` series «out» icon (same URL).** `ListSeries()` `:209` tests `is_w2a_admin($admin) AND
  $group_id > 0`; `$group_id` is neither a parameter, a `global`, nor assigned in the function (and the query does not
  select `group_id` either) — `new_modules.php`'s request-to-variable copy only creates globals, which a function does
  not see. Always false, on PHP 5.6 as on 8.1. **DEAD_OR_UNREACHABLE (source-proven).**
- **The handler they point to — `KhotabUpSeries` → `UpSeries($id, $group_id)`** (`modules/Khotab/admin/index.php:174`,
  `:2210-2222`): `radminsuper` or two hard-coded admin names; `UPDATE nuke_islamic_series SET group_id = '0' WHERE
  id = X` and `UPDATE nuke_islamic_khotab SET group_id = '0' WHERE ser_id = X` — nothing else (no counter); redirect to
  `modules.php?name=Khotab&op=Group&id={group_id}`; otherwise the text «هذه الخاصية متاحة فقط للمشرف العام …». Working,
  and **already preserved** for its real, live caller (the Khotab group page) as `DetachKhotabSeriesAction` /
  `admin.khotab…series.detach` (owner-closed Khotab, 2026-10-21). Neither search page could ever reach it.
- **Correction to #72:** its row said both pages emit the icon "for an admin and a series inside a group"; the source
  shows neither can. The REVIEW_REQUIRED classification is resolved by this investigation.
- **Laravel:** `POST /search.htm` (`SearchController`) has no contextual controls; `advanced_search.htm` is not
  registered. Nothing missing for admin controls.
- **Final:** PRESERVE_MISSING 0; ALREADY_PRESERVED 0 on these pages (the handler itself: preserved on Khotab);
  DEAD_OR_UNREACHABLE 2 (S1, A1); OWNER_RETIRED 0; NEEDS_EVIDENCE 0. One review unit is right (same control, same
  template, same handler, same outcome). Recommended implementation: **none**.
- **Separate, not admin controls, not started:** Laravel does not register `advanced_search.htm`, which OLD's
  `apply_search()` «بحث متقدم» form (Khotab author / Cat pages) posts to — visitor parity; admin hidden-content
  visibility on search results — recorded, not a control (#72).
- **Status:** **SEARCH + ADVANCED_SEARCH OLD PUBLIC ADMIN-CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER REVIEW.**
- **Update (2026-11-03):** OWNER CLOSED — see #80.

---

## 80. SEARCH + ADVANCED_SEARCH OLD PUBLIC ADMIN-CONTROLS — COMPLETE — OWNER CLOSED (2026-11-03)

Owner decision on #79. Documentation only — no application code, data, production, commit or deployment change.

| ID | Page | Control | Final |
|---|---|---|---|
| S1 | `search.htm` | series «out» icon → `KhotabUpSeries` | DEAD_OR_UNREACHABLE — gate `$Series["group_id"] > 0`, `group_id` never selected |
| A1 | `advanced_search.htm` | series «out» icon → `KhotabUpSeries` | DEAD_OR_UNREACHABLE — gate `$group_id > 0`, never defined in `ListSeries()` |

PRESERVE_MISSING 0; ALREADY_PRESERVED (on these pages) 0; OWNER_RETIRED 0; NEEDS_EVIDENCE 0. `KhotabUpSeries` /
`UpSeries()` is a working handler, but that does not make these callers reachable; its real caller (the Khotab group
page) is preserved as `DetachKhotabSeriesAction` — Khotab stays OWNER CLOSED, not reopened. No implementation required.

- **Supersedes:** #72's search / advanced_search bullet and plan §43's two REVIEW_REQUIRED rows, which said both pages
  show a working «out» icon.
- **Known local OLD-public-admin-control gaps for this unit: 0.**
- **Outside this closure — not admin controls, NOT STARTED:** Laravel's missing `advanced_search.htm` (visitor
  functionality; OLD's `apply_search()` «بحث متقدم» form posts to it); admin visibility of hidden search results; any
  search / advanced_search visitor-parity differences.
- **Status:** **SEARCH + ADVANCED_SEARCH OLD PUBLIC ADMIN-CONTROLS — COMPLETE — OWNER CLOSED (2026-11-03).** Next
  unstarted admin-control task per plan §43: `w2a_cd` (INSUFFICIENT_EVIDENCE — one read-only production
  `sha256sum modules/w2a_cd/index.php`). Not started.

---

## 81. W2A_CD OLD PUBLIC ADMIN-CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER REVIEW (2026-11-03)

Investigation only — no application code, data, production, commit or deployment change. Local data read with SELECT
only.

- **Production evidence (owner-run, read-only, 2026-11-03):** `sha256sum modules/w2a_cd/index.php` on
  `/home/php8way2allah/public_html` = `95d929b62b889d409d589e8f3a70e01781c266029d155c0624e68d2e5d54c59e`, identical to the
  local OLD file — the evidence #72 asked for is satisfied.
- **Entry points.** No `.htaccess` rule names `w2a_cd`, and no OLD page, template or script links to `name=w2a_cd` (every
  other `w2a_cd` string in OLD is the `nuke_w2a_cds` table, the separate AdminCP `cd` module or an icon file name).
  Reachable only by typing `modules.php?name=w2a_cd` (`nuke_modules.active = 1`, `view = 0` locally) or
  `new_modules.php?name=w2a_cd`; `op` defaults to `Group(0)`. The module is a copy of the Anasheed module (it reads and
  writes `nuke_anasheed_*`, its admin links are `admin.php?op=Anasheed…`) with CD-flavoured templates for the Anasheed
  tree under group 387. Template folder `themes/way2allah/templates/w2a_cd/` holds only `group_cp_for_cd`,
  `item_cp_cd`, `group_cd_content`, `detailes_cd`, `detailes_cd_links` (+ its AdminCP's list / insert / edit / delete
  forms); 29 of the 34 templates `index.php` loads do not exist (`listblock`, `group_cp`, `item_cp`, `comment_admin`,
  `group_head` / `group_cont` / `group_foot`, `anasheed_*`, `mirror_*`, `top10`, …).
- **Runtime facts.** `:11` calls `eregi()` — a fatal on production PHP 8.1; under §26.5 a runtime defect, so not relied
  on alone. **Decisive, on every PHP version:** every admin block is gated by `is_admin($admin)`, and `is_admin()` is
  defined nowhere in OLD's PHP-Nuke code — not in `mainfile.php`, `php83_compatibility.php`, `way2allah_functions.php`
  or `includes/`, not in git HEAD or history (OLD renamed it `is_w2a_admin()`; the only other caller is the dead
  `Khotab/_index.php`); the only definitions are inside the unrelated WordPress copies under `special/` and
  `alden-elmo3amalh/`, which `mainfile.php` never loads. Reaching the gate is a "Call to undefined function" fatal, for
  visitors and admins alike, so no panel can render. (Through `new_modules.php` `$admin` is also forced to `false`.)
- **Candidates (every admin-conditional output of `modules/w2a_cd/index.php` and its templates):**

  | ID | Where | OLD controls | Gate / why | Classification |
  |---|---|---|---|---|
  | WC1 | `Group()` «لوحة تحكم القسم» `:85-95` | `group_cp_for_cd.htm` (group 387 tree): اضافة صورة للقسم, تعديل القسم, إضافة إسطوانة, إضافة قسم فرعي, حذف القسم, ترتيب القسم → `admin.php?op=AnasheedGroupIcon / EditGroup / InsertAnasheed / AddGroup / DeleteGroup / Order_anasheed_in_group`; otherwise `group_cp.htm` (missing) | `is_admin()` undefined | DEAD_OR_UNREACHABLE |
  | WC2 | `Detailes()` «لوحة تحكم الإسطوانة / المادة» `:211-227` | `item_cp_cd.htm`: تعديل الإسطوانة, نقل الإسطوانة, حذف الإسطوانة, اضافة الى قائمة الشير → `AnasheedEditAnasheed` ×2 / `DeleteAnasheed` / `WaitingFB`; otherwise `item_cp.htm` (missing) | `is_admin()` undefined | DEAD_OR_UNREACHABLE |
  | WC3 | `Mirror()` `:333-335` | per-link تعديل / حذف الوصلة → `AnasheedEditMirror` / `AnasheedDeleteMirror` | only called from `Detailes()` after `:211`; `is_admin()` undefined; mirror templates missing | DEAD_OR_UNREACHABLE |
  | WC4 | `Comments()` `:364`, `:374-375` | `comment_admin.htm` (missing) and «(تعديل التعليق)» → `AnasheedWaitingList` | only called from `Detailes()` after `:211`; `is_admin()` undefined | DEAD_OR_UNREACHABLE |
  | WC5 | `ArrangeAnasheed()` `:545-570` | up / down → `AnasheedOrder` | no caller | DEAD_OR_UNREACHABLE |
  | WC6 | `AdvancedStats()` `:608-682` | تعديل, مسح, إضافة وصلة, رفع بانر, طلب رئيسية, طلب بانر | no caller; `advanced_stats.htm` missing | DEAD_OR_UNREACHABLE |

  `cds_main` prints "cds main" only. The `admin.php?op=Anasheed…` targets are the Anasheed module's own handlers; their
  real public callers (the Anasheed group / item pages) are preserved and OWNER CLOSED (#61, #62, #67) — not reopened.
- **Laravel:** no `w2a_cd` route or page; nothing to compare beyond the preserved Anasheed / W2ACD surfaces.
  PRESERVE_MISSING 0; ALREADY_PRESERVED 0; DEAD_OR_UNREACHABLE 6; OWNER_RETIRED 0; NEEDS_EVIDENCE 0.
- **Earlier records:** #72 / plan §43 INSUFFICIENT_EVIDENCE — **superseded** (evidence satisfied, now
  DEAD_OR_UNREACHABLE); §25.3 "NOT W2ACD" — **confirmed** (an Anasheed copy, not CD administration), but its reasoning is
  not what classifies it here; #67 "outside this sweep" — **resolved**. Same class as Anasheed `_index.php` (#67:
  unlinked copy, production-identical, fatal before its admin blocks), with the added version-independent proof above.
  No BC#6 / external-ownership reasoning used.
- **Inventory:** with this, every item of the #72 / §43 remaining-modules inventory is dispositioned (W2ACD #78,
  search + advanced_search #80, `w2a_cd` here). The copy files #72 recorded as "not explicitly dispositioned"
  (`Fatwa/Before_shams_index.php`, `Fatwa/index_strange.php`, `Khotab/_index.php`) stay as #72 recorded them.
- **Recommended:** owner close with 0 gaps; no implementation.
- **Status:** **W2A_CD OLD PUBLIC ADMIN-CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER REVIEW.**
- **Update (2026-11-03):** production `is_admin` check run; OWNER CLOSED — see #82.

---

## 82. W2A_CD OLD PUBLIC ADMIN-CONTROLS — COMPLETE — OWNER CLOSED; REMAINING DISCOVERY INVENTORY — COMPLETE (2026-11-03)

Owner decision on #81. Documentation only — no application code, data, production, commit or deployment change.

- **Production evidence (owner-run, read-only, `/home/php8way2allah/public_html`):**
  1. `sha256sum modules/w2a_cd/index.php` = `95d929b62b889d409d589e8f3a70e01781c266029d155c0624e68d2e5d54c59e`; local OLD
     `modules/w2a_cd/index.php` = the same — **MATCH, byte-for-byte identical.**
  2. `grep -rln "function is_admin" --include='*.php' . | grep -vE '^\./(special|alden-elmo3amalh)/'` — **no output**:
     `is_admin()` is defined nowhere in the applicable production PHP tree outside the two excluded WordPress copies,
     so the `is_admin()` gates of `w2a_cd` cannot resolve on production either.
- **Final classification:** WC1 (`Group()` panel), WC2 (`Detailes()` panel), WC3 (`Mirror()` links), WC4 (`Comments()`
  admin form / «(تعديل التعليق)»), WC5 (`ArrangeAnasheed()`), WC6 (`AdvancedStats()`) — all **DEAD_OR_UNREACHABLE**.
  WC5 and WC6 additionally have no caller. PRESERVE_MISSING 0; ALREADY_PRESERVED 0; OWNER_RETIRED 0; NEEDS_EVIDENCE 0.
- The `admin.php?op=Anasheed…` handlers these dead links name have their real public callers in the Anasheed migration,
  already OWNER CLOSED (#61, #62, #67) — not reopened.
- **Supersedes:** #72's / plan §43's INSUFFICIENT_EVIDENCE for `w2a_cd`.
- **Known local W2A_CD OLD-public-admin-control gaps: 0.**
- **Status:** **W2A_CD OLD PUBLIC ADMIN-CONTROLS — COMPLETE — OWNER CLOSED (2026-11-03).**
- **OLD PUBLIC ADMIN-CONTROLS REMAINING DISCOVERY INVENTORY — COMPLETE (2026-11-03).** Every item of the #72 / §43
  inventory is dispositioned: W2ACD — OWNER CLOSED (#78); search + advanced_search — OWNER CLOSED (#80); `w2a_cd` —
  OWNER CLOSED (this entry); the other 55 rows as #72 classified them. **Scope of this statement: the discovery
  inventory only.** It does not say the Laravel migration, visitor parity, production readiness, or deployment /
  cutover is complete — those are separate and NOT STARTED (visitor-parity items recorded in #71, #74–#80; production
  deployment NOT STARTED, plan §28.6 / §34).
- **[Correction 2026-11-06, #100 (R6): "plan §34" above should read "plan §30.5" — §30.5 holds the remaining-items list; §34 is Anasheed Main. Text left as written.]**

---

## 83. WAY2ALLAH WHOLE-MIGRATION CLOSURE REVIEW — AUDIT COMPLETE — AWAITING OWNER REVIEW (2026-11-03)

Audit / reconciliation only — no code, data, production, permission, seeder, commit or deployment change. Full register:
plan §54. It does **not** declare the migration, local migration, production readiness, visitor parity or deployment
complete.

- **Findings that change the picture:** (1) nothing since commit `4309357` (2026-10-08) is committed — 246 working-tree
  entries (60 modified, 186 untracked) hold Phases 0–7 and every owner closure through #82; (2) the last *whole-suite*
  run is #69 (2,215 tests, 1 skip, 2026-11-01); W2ACD Phases 1–3 changed code since, verified only by partial runs that
  omit `tests/Architecture`, `Feature/Engagement`, `Feature/Identity` and `DeploymentInstallerControllerTest`; (3) the
  cPanel package `way2allah-laravel-app-cpanel-no-ssh.zip` dates from 2026-09-14 (468 `app` / `resources` / `routes`
  files are newer); (4) 81 permission definitions now (43 at §29.1), no grant plan document; (5) a second deployable
  migration, `2026_10_22_130000_create_download_rejections_table` (documented in `docs/operations/download-capacity.md`),
  is not in plan §10.5; (6) the OLD / Gen 2 retirement list exists only as scattered per-phase notes — "closure review
  §5" names no existing document, and §31–§53 added no cutover notes; (7) the plan banner, §7.1 and §30 still present the
  2026-10-15 closure as final, and `PROJECT-HANDOFF.md` §7 / §8 hold stale current statements; (8) #82 cites "plan §34"
  for the remaining-items list — it is §30.5 (§34 is Anasheed Main).
- **Re-verified today:** DoD 4 (no `old.way2allah.com` / `inlineAdminUrl` in `app` / `routes` / `resources` /
  `config`); DoD 9 Laravel side (`GreenZone` / `AvAdmin` appear only in docblocks; no `nuke_*_mod` reference); Laravel
  emits no link to `advanced_search.htm`; Gen 2 still renders inline OLD admin (`7amlat/khotab/functions.php:296`
  «طلب رئيسية» via `$inlineAdminUrl`; 8 Gen 2 files use `is_w2a_admin`).
- **Status:** **WAY2ALLAH WHOLE-MIGRATION CLOSURE REVIEW — AUDIT COMPLETE — AWAITING OWNER REVIEW.**
- **Register update (2026-11-03, #84):** R2 (fresh whole-suite + PHPStan + Pint) → **COMPLETE_EVIDENCED** — R2 FULL
  QUALITY GATE — COMPLETE — OWNER APPROVED: 2,303 tests (2,302 passed, 1 skipped, 0 failed), Architecture 5 / 5 inside
  the normal suite, PHPStan 0, repository-wide Pint PASS. Finding (2) above is resolved; the other findings and register
  rows are unchanged. R1's working tree has grown from 246 to 297 entries (the R2 architecture relocation and formatting).

---

## 84. R2 ARCHITECTURE FIX — Content no longer depends on Admin; Architecture joins the normal suite (2026-11-03)

- **Trigger:** the R2 full quality gate (closure register R2, plan §54) found `tests/Architecture/DomainBoundaryTest.php`
  failing «the Content domain must not depend on the Admin domain's internals» (Blueprint v1.0 §2) — a rule that had
  never run under `composer test` / CI because `phpunit.xml` listed only Unit and Feature. Owner-approved resolution:
  four contained fixes, one per root cause; the rule itself is unchanged.
- **RC1 (new with the contextual-admin work) — `App\Support\Http\AdminViewer`:** a port with `active()`,
  `adminId()`, `markRendered()`. `ContextualAdmin` implements it and `ContextualAdminServiceProvider` registers it as an
  **alias** of the per-request `ContextualAdmin` — never a second binding — so a Content controller's `markRendered()`
  sets the state `KeepContextualPagesPrivate` reads (`no-store, private`). `KhotabItemController`,
  `KhotabSeriesController`, `KhotabAuthorController` and `LocationController` type-hint `AdminViewer`;
  `App\Support\Downloads\DownloadRejectionRecorder` uses it too (its `class_exists(ContextualAdmin)` lookup is gone;
  `admin_id` / `is_contextual_admin` recorded as before). Guarded by `tests/Feature/Admin/Contextual/AdminViewerPortTest.php`
  (a deliberately independent binding fails 2 of its 4 tests).
- **RC2 (pre-existing since 2026-08-12) — Backup API relocated to Admin:** `BackupApiController` →
  `App\Domain\Admin\Http\Controllers`; `BackupApiAuthenticator`, `BackupApiAuthResult` → `App\Domain\Admin\Support`.
  `BackupSession` / `BackupBooking` stay in Content. Same `POST /backup.php` route, authentication, responses and writes.
- **RC3 (pre-existing since 2026-08-18) — `SiteOption` → `App\Support\Settings\SiteOption`:** the `nuke_options`
  key/value store is cross-domain settings (Home reads, Admin Soundcloud / Youtube write). Model unchanged.
- **RC4 (pre-existing since 2026-08-12) — `Location` → `App\Domain\Content\Models\Location`:** the public
  `location-{id}.htm` pages read it and Admin's `LocationsController` edits it (Admin → Content is the permitted
  direction, as with `Channel`). This departs from Blueprint §7's mapping of "Location Directory" to Admin Operations;
  table, model and both surfaces unchanged.
- **`phpunit.xml`:** adds the `Architecture` testsuite (`tests/Architecture`), so `php artisan test` / `vendor/bin/pest`
  (CI) now run `DomainBoundaryTest`.
- **Verification:** Architecture 5 / 5; no `App\Domain\Admin` reference left in `app/Domain/Content`; focused
  regression 559 / 559 (31 files); PHPStan level 5: 0 errors; normal suite 2,303 tests — 2,302 passed, 1 skipped
  (pre-existing), 0 failed, Architecture included. Pint on the touched files: all pass except `ContentListingService.php`
  and `BackupApiControllerTest.php`, which fail with exactly the fixers their HEAD versions already fail.
- **Status at the architecture step (superseded below):** R2 ARCHITECTURE FIX — PASS — AWAITING OWNER REVIEW. R2 itself
  was **not** complete at that point: the repository-wide Pint baseline (41 files, all failing at HEAD `4309357`) remained
  open.
- **Pint baseline — impact assessment (same day, assessment only):** the 41 files failing `vendor/bin/pint --test` (default
  Laravel preset; no `pint.json`) all failed identically at HEAD `4309357`. Pint was run in fix mode on a complete temporary
  copy of the repository only: +171 / −155 lines; rules — import ordering, fully-qualified names shortened to imports,
  `fn (` / concatenation / negation spacing, `{}` empty bodies, `phpdoc_align`, single quotes, `new X` without
  parentheses, 3 unused imports and 6 unused closure captures (`use ($hasGd)`) removed — each checked as unreferenced. On
  that copy: repository-wide Pint PASS, PHPStan 0, `php -l` clean, full suite 2,303 / 2,302 passed / 1 skipped / 17,661
  assertions. No behaviour-sensitive change found.
- **Pint baseline — formatting pass (owner-approved):** Pint run on exactly those 41 files (no repository-wide fix run, no
  configuration change), after re-confirming the list, each file's content and the working tree against the preview. Git
  status before the pass: 32 files unchanged since HEAD, 9 modified (`routes/content.php`, `routes/admin.php`, `Poll.php`,
  `ContentListingService.php`, `FatwaTopicController.php`, `FatwaQuestionController.php`,
  `DeploymentInstallerController.php`, `BackupApiControllerTest.php`, `FatwaTopicControllerTest.php`); no untracked file.
  The assessment report's "33 / 8" split predated the architecture step touching `BackupApiControllerTest.php`. Result:
  41 files formatted, +171 / −155, every file byte-identical to the approved preview; formatting only.
- **Final R2 verification (2026-11-03):** `vendor/bin/pint --test` PASS (0 files); `phpstan analyse` level 5: 0 errors;
  `php artisan test --no-ansi`: 2,303 tests — 2,302 passed, 0 failed, 1 skipped (pre-existing), 17,661 assertions, the
  Architecture suite included automatically; `php artisan test tests/Architecture`: 5 / 5. No staging, no commit, no
  production access, no local business-data change.
- **Final status:** **R2 FULL QUALITY GATE — COMPLETE — OWNER APPROVED (2026-11-03).** Closure register R2 →
  COMPLETE_EVIDENCED (plan §54).

---

## 85. ADVANCED SEARCH VISITOR PARITY — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-03)

Owner priority (2026-11-03): no push or production deployment until every confirmed HIGH and MEDIUM visitor-parity gap is
resolved and owner-approved; R1 (versioned baseline) deferred. First module: OLD `advanced_search.htm`
(`.htaccess:154` → `modules/advanced_search` `Main_search()` / `ListSeries()` / `ListKhotab()`; entry points: direct
URL and the «بحث متقدم في سلاسل و مواد الداعية» form `apply_search("author", $id)` shown to every visitor on
`khotab-video-{id}` / `khotab-audio-{id}`). OLD's `eregi()` guard is a PHP 8 runtime defect, not a reason to drop it.
Dead in OLD, not restored: the category form (`category-{id}.htm` → `modules/Cat/index.php` dies on its own
direct-access guard), the commented-out Satellite calls, `header2.php` / `indexpage.htm` links (never included). The
`routes/content.php` note that called `advanced_search.htm` dead rested on Gen 2 evidence and is corrected.

- **Approved and implemented:** G1 (HIGH) criteria kept across pages — both paginators `withQueryString()`; G2 (HIGH)
  independent pages — series `series_page`, items `page`; G3 (MEDIUM) `GET /advanced_search.htm` →
  `KhotabSearchController::index` (`khotab.search.legacy`), identical to `/video-advanced-search.htm`; G4 (MEDIUM) the
  sheikh-page form (video and audio, not the transcripts page; every visitor) — GET to `/advanced_search.htm` with the
  sheikh fixed, title, OLD's per-sheikh channel menu (`ContentListingService::channelsForAuthorSeries()` = OLD
  `get_channels_for_author_as_menu()`), from / to; G5 (MEDIUM) items `ORDER BY tb1.weight DESC` (OLD `ListKhotab()`);
  G6 (LOW) 25 per page; G7 (LOW) series total «N سلسلة».
- **Owner decisions applied:** both URLs identical; title-less search kept (IF-024); GET kept (#21). Deferred with owner
  approval: G8 «new» badges, G9 empty-search message.
- **Unchanged:** filters (`vedio=1`, items `hidden=0`, series `hidden=0 AND count>0`), the 4-byte title rule, highlighting,
  `POST /search.htm` (service defaults kept: 20 per page, page name `page`). No schema or data change.
- **Engineering note (performance only, results unchanged):** with OLD's weight order the GET search is the same query
  shape as `search.htm`'s video department, so it takes the same results-only `STRAIGHT_JOIN` hint (C-1); the guard
  test that pinned the GET caller to `['tb1.time', false, false]` now pins `['tb1.weight', false, true]`, and its spy
  subclass takes the two new optional parameters (the old signature was a fatal on load).
- **Files:** `KhotabSearchController.php`, `KhotabAuthorController.php`, `ContentListingService.php`
  (`$perPage` / `$pageName` on the two search methods; `channelsForAuthorSeries()`), `routes/content.php`,
  `khotab/search.blade.php` (G7), `khotab/author.blade.php` (form, outside `premium-panel` so the page's panel structure
  is unchanged), `content-refresh.css` (toggle styles); tests: new `AdvancedSearchVisitorParityTest.php` (12),
  updated `SearchStraightJoinBehaviorTest.php`.
- **Verification:** new tests 12 / 12 (mutation: dropping `withQueryString()` and the series page name fails G1 and
  G2); targeted search / author / Khotab admin / listing set 190 / 190; Architecture 5 / 5; PHPStan level 5: 0 errors;
  repository-wide `pint --test` PASS; full suite 2,315 tests — 2,314 passed, 0 failed, 1 skipped (pre-existing). Live
  check against the local :8123 server not possible — the server was not running.
- **Status:** **ADVANCED SEARCH VISITOR PARITY — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.** Not owner
  closed. No staging, commit, push or deployment.
- **UI refinement (owner, 2026-11-03, during manual browser verification):** confirmed issues in the results lists —
  excessive vertical whitespace, title and sheikh far apart, highlighted words visually fragmented, count badges badly
  placed, weak hierarchy. Cause: each row nested Bootstrap rows in an `<h5>` plus a `page-header` block (large margins /
  border), and `custom.css` sets `.red_sub { vertical-align: top }` on top of the base `sub` shift. Fix (presentation
  only, both lists): each row is now a compact block — the highlighted title (`h3.w2a-result__title`) and one wrapping
  line `.w2a-result__meta` with «الداعية:» link, «القناة:» icon link and a pill badge («المواد: N» / «الزيارات: N»);
  styles in `content-refresh.css`, scoped to `.w2a-video-search-results` (search.htm and other `red_sub` users untouched;
  `!important` only against the global legacy-table rules that already use it); the highlight stays a
  `<sub class="red_sub">` but sits on the text line; phone widths (< 576px) tighten padding and type. Same information,
  links, numbering, totals, empty states and pagination. New test: compact markup, all fields present, no
  `page-header` / `<h5>`. Verification: relevant tests 136 / 136; Architecture 5 / 5; PHPStan 0; Pint PASS; full suite
  2,316 — 2,315 passed, 1 skipped. Visual check: headless Chrome screenshots of `advanced_search.htm?title=الصلاة` on the
  local server at 1366px and 390px — compact rows, inline highlight, RTL meta line; not a substitute for the owner's
  browser review. **Status: ADVANCED SEARCH UI REFINEMENT — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.**
- **Update (2026-11-04):** OWNER CLOSED LOCALLY — see #86.

---

## 86. ADVANCED SEARCH VISITOR PARITY — OWNER CLOSED LOCALLY (2026-11-04)

Owner decision on #85 after manual browser verification. Documentation only — no application code, test, data, staging,
commit, push, deployment or production change.

| ID | Gap | Priority | Final |
|---|---|---|---|
| G1 | Search criteria kept across pages | HIGH | OWNER MANUAL PASS |
| G2 | Independent series / items pagination | HIGH | OWNER MANUAL PASS |
| G3 | `/advanced_search.htm` restored | MEDIUM | OWNER MANUAL PASS |
| G4 | «بحث متقدم» on video and audio sheikh pages | MEDIUM | OWNER MANUAL PASS |
| G5 | Items by OLD `weight DESC` | MEDIUM | AUTOMATED PASS (owner-accepted) |
| G6 | 25 per page | LOW | AUTOMATED PASS (owner-accepted) |
| G7 | Series total «سلسلة» | LOW | OWNER MANUAL PASS |
| G8 | «new» (1 / 3 / 7-day) badges on result rows | LOW | **DEFERRED — owner-approved**, not a blocker |
| G9 | Message when a search is submitted with nothing entered | LOW | **DEFERRED — owner-approved**, not a blocker |

- **UI refinement (#85 addendum):** compact RTL result rows, keyword highlighting and the results layout — OWNER APPROVED.
- **Regression:** `search.htm` manually tested by the owner (searching after choosing the required category) — no
  regression observed; automated `search.htm` guards unchanged and passing.
- **Automated quality gate (as of #85's addendum):** full suite 2,316 tests — 2,315 passed, 0 failed, 1 skipped
  (pre-existing); Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide `pint --test` PASS.
- **Known HIGH / MEDIUM Advanced Search visitor-parity gaps: 0.** Implementation and test evidence: #85.
- **Not claimed:** whole-migration completion, production verification, deployment / cutover. Admin-control work stays
  closed and untouched. Remaining visitor-parity modules: plan §54 visitor-parity update (2026-11-04) — not yet
  investigated.
- **Status:** **ADVANCED SEARCH VISITOR PARITY — OWNER CLOSED LOCALLY (2026-11-04).**

---

## 87. ANASHEED VISITOR PARITY — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-04)

Owner-approved scope after the Anasheed visitor-parity investigation and the A2 archive.org verification. OLD
`modules/Anasheed` governs visitor behaviour (owner rule 2026-11-03; it settles the open "OLD or Gen 2" question of the
earlier Anasheed Stage A Investigation #1). Admin controls (#61–#67) not reopened.

- **Confirmed gaps (local data):** A1 HIGH — news pages selected by `group_id` (Gen 2) instead of the department
  (`parent_id`): 98: 191 vs 3,711 items; 57: newest 2020-07 vs 2026-06; 158: 2023-12 vs 2026-06. A2 HIGH — no archive.org
  normalisation: 1,198 items / 2,187 mirrors on storage-node URLs. A3 MEDIUM — group order reversed (12,205 items in
  1,038 groups carry `order_in_group`). A4 MEDIUM — group 98 page merged group 16 (191 shown, 1 own). A5 MEDIUM — notes
  missing (225 items). A6 MEDIUM — hidden items shown in full (32 items, `hidden=2`). A7 / A8 LOW — page sizes; `.grx`.
- **A2 verification (read-only, 2026-11-04, 6 samples, HEAD + 16-byte ranged GET, no media downloaded):** item 17327
  (`ia601301` node) — raw: connection timeout on https and http; canonical `/download/`: 206, MP4 bytes. Item 3
  (`ia331419`, 248 items / 34 mirrors) — raw https: TLS name mismatch, raw http: 500; canonical: 206, RealMedia bytes.
  Item 17346 (`dn…ca` node) and mirror 8276 (`ia601500`) — work both ways. Mirror 8493 (`/details/{file}`) — raw: an HTML
  details page; OLD's rewrite: 404. Item 17300 (canonical control) — works. Classification: CONFIRMED HIGH.
- **Implemented:**
  - **A1** `AnasheedNewsController`: items whose `parent_id` is the department (98 → 98 and 16); every pinned item,
    then the newest 24, both `mytime DESC` (OLD `ListNews()`).
  - **A2** new `App\Support\Media\ArchiveOrgLink::normalize()`: only archive.org hosts with `/items/{folder}/{file…}`
    or `/download/{folder}/{file…}` become `https://archive.org/download/{folder}/{file…}`; everything else (other hosts,
    `/details/`, paths without a folder and a file) is returned unchanged. Applied to `var-download`, `var-mirror` and
    the Anasheed item / mirror player sources. Stored links untouched; counters and redirects kept. No Content → Admin
    dependency.
  - **A3 / A4 / A7** `AnasheedGroupController::itemsForGroup()`: own `group_id` only, `order_in_group DESC, mytime
    DESC`, 24 per page; the now-unused `AnasheedItem::scopeInGroup()` (Gen 2's 98 / 16 rule on `group_id`) removed.
    Item comments 24 per page.
  - **A5** notes passed to the details card, labelled «تعليق على المادة» (new `notesLabel` prop, default unchanged for
    Khotab / Telawah / chat-room); escaped output.
  - **A6** hidden items (`hidden ≠ 0`): OLD `Detailes()` hides the details, actions, mirrors and comments for every
    viewer while the chrome, admin panel, sidebar and hit counter stay — reproduced. OLD's other endpoints: `PlayMirror()`,
    `Codes()` and `DownSeries()` require `hidden='0'`; `getit()` / `getmirror()` lacked the check (reachable only from the
    hidden details block). Implemented: the item player already filtered hidden; the mirror player now requires a
    visible parent item (OLD `PlayMirror`); item and mirror downloads answer 404 for a hidden item, the existing
    not-available response (owner review point — OLD's download handlers alone had no check); `.grx` filters hidden
    (A8). Comment and send-to-friend POSTs keep OLD's no-check behaviour (no content exposed); their buttons and forms
    go with the hidden block. Listings (group, news, sidebar) keep listing hidden items, as OLD did.
  - **A8** `.grx`: `hidden = 0`, `ORDER BY weight ASC`, folder «الأناشيد».
- **Tests:** new `tests/Unit/Support/ArchiveOrgLinkTest.php` (20) and `tests/Feature/Content/AnasheedVisitorParityTest.php`
  (15); updated to the approved behaviour, assertions not loosened: `AnasheedGroupControllerTest` (98 lists only its
  own items), `AnasheedItemControllerTest` (24 comments a page), `AnasheedNewsControllerTest` (department fixtures carry
  `parent_id`), `MediaPlayerControllerTest` (mirror fixtures gain their visible parent item). Mutation check: restoring
  `group_id` selection, dropping the download hidden check and the `.grx` order fails 6 tests.
- **Verification:** targeted Anasheed visitor + admin-control + player + home + helper set 256 / 256 (16 files);
  Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide `pint --test` PASS; full suite 2,351 tests — 2,350
  passed, 0 failed, 1 skipped (pre-existing).
- **Unresolved links (not normalised, likely broken in OLD and Laravel alike; not checked outbound):** 3 items and 8
  mirrors stored with a leading space and `/download/{one segment}` (no folder, e.g. item 8818, mirror 10854); mirror
  5034 (`www.archive.org/download/{one segment}`); mirror 8493 (`/details/{file}`). All other stored archive.org links
  either normalise (1,200 items, 2,186 mirrors) or are already canonical.
- **Remaining, not in this phase:** A9 sub-group cards (icon image, «التعليق», count at 0), A10 series block on roots and
  GetRight link, A11 pager above the list, A12 sidebar scope / per-department title, A13 group description, A14 Main and
  missing-group 404 — LOW, awaiting owner decision. Outside Anasheed (recorded only): the home page's «أحدث المقاطع
  والأناشيد» block; Khotab / Telawah public downloads without the archive.org rule.
- **Status:** **ANASHEED VISITOR PARITY — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.** Not owner closed. No
  staging, commit, push or deployment.
- **News pages UI refresh (owner manual QA, 2026-11-04):** `cartoon-news.htm` looked unfinished (oversized headings, bare
  bulleted links, no separation). Presentation only — `anasheed/news.blade.php` now uses the shared page heading /
  breadcrumb («أحدث المواد بقسم …», OLD's page title), two `w2a-refresh-panel` sections («المواد المثبتة» / «المواد
  الجديدة») with the site's media cards (thumbnail, title, date only when `mytime > 0`), an empty list omitted as OLD
  `ListNews()` did; `content-refresh.css` adds panel spacing and, under 576px, compact horizontal cards (scoped to
  `.w2a-anasheed-news-page`). Queries, selection, ordering, routes and links unchanged. New test (card markup, conditional
  date, empty panel omitted, order intact); targeted set 95 / 95; Pint PASS. Headless-Chrome screenshots of all four
  pages at 1366px and 390px checked. Status unchanged.
- **Owner manual QA (2026-11-04):** A1–A7 PASS (cartoon news, item 17327 playback and download, mirror 8276, group
  ordering, group 98, notes, hidden item 2510, group 394 pagination); A8 automated. The 13 unresolved archive.org links
  were checked outbound: all 13 broken in OLD and Laravel alike (11 + mirror 5034 store a literal `...` — folder and
  file lost; mirror 8493 a `/details/` page, OLD's rewrite 404) — CONFIRMED LEGACY DATA, 0 Laravel gaps. Four more
  mirrors (10848, 10853, 10913, 10914) store the same truncated URLs. Owner data decision open: mirror 8493's file is
  verified at `https://archive.org/download/Han3alekRasolEllahUshtam.flv/Han3alekRasolEllahUshtam.flv`.
- **LOW closure sprint A9–A14 (owner, 2026-11-04):**
  - **A9 FIXED** — sub-group cards (`components/content/anasheed-subgroup-grid`, Anasheed only): OLD `ListGroup()` /
    `group_cont.htm` — the group icon image (`icon = 1` → `media/anasheed/icons/…`, else `images/pix001.gif`) in place
    of the folder glyph, the item count shown at 0, and «التعليق : {des}» or «بدون تعليق». OLD's «new» badge
    (`Graphic($time)`) not restored.
  - **A10 FIXED** — the series download block only on a sub-group (`parent_id > 0`) with items, as OLD `Group()`;
    the items list itself still shows on roots. OLD `series_download.htm`'s «رابط تحميل برنامج التحميل» and its notice
    restored. Deviation for owner review: OLD's `http://download.getright.com/getright-download.exe` answers 403 (https
    no answer), so the link opens `https://getright.com/` (200) in a new tab, `rel="noopener noreferrer"`.
  - **A11 FIXED** — the group pager above and below the items (OLD `ListAnasheed()` calls `pagination()` twice);
    nothing when one page.
  - **A12 FIXED** — item sidebar (OLD `Top10(true, '', main_parent)`): the department is the root of the item's group
    chain; both lists take items whose `group_id` is one of the department's direct sub-groups (none → no filter, as
    OLD), `hits DESC` / `mytime DESC`, six rows (was the item's own group, seven); the second list is titled per
    department — 98 «جديد الأناشيد», 16 «جديد المقاطع المؤثرة», 12 «جديد الأفلام الوثائقية», 57 «جديد الكرتون»,
    158 «جديد إصدارات الموقع», any other «جديد الأناشيد». `ContentSidebarWidget::anasheedMost*()` now take the group
    list; their only caller is the Anasheed item page. OLD's ad slot 5 between the lists not restored.
  - **A13 NO-OP** — OLD `Group()` never prints a group description; `des` appears only on sub-group cards (A9). The
    local database has 0 non-empty `description` values, so Laravel's conditional description panel never shows —
    no visible gap, no code.
  - **A14 NOT CHANGED** — OLD `var-group-0.htm` (`Group(0)`, also the module default) renders a title-less page of the
    five root groups; no OLD page, template or block links to it, and the owner-closed admin-control test
    (`AnasheedGroupPanelParityTest`) asserts 404 for it. A missing group in OLD renders an empty page; Laravel keeps 404.
    Restoring either is an owner decision, not a small parity fix.
  - **Tests:** `AnasheedVisitorParityTest` +9 (A9, A10, A11, A12 × 6 departments); `ContentSidebarWidgetTest` 7 → 6
    rows and group-list filter, +1. Updated to the approved behaviour, assertions not loosened: two
    `AnasheedGroupControllerTest` cards tests (folder glyph / no pix001 → icon image / pix001), its portlet-count test
    (group 1 made a sub-group so the series block still counts), and `AnasheedItemControllerTest`'s sidebar title.
  - **Verification:** targeted 40 / 40 and 43 / 43; Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide
    `pint --test` PASS; full suite 2,362 tests — 2,361 passed, 0 failed, 1 skipped (pre-existing).
  - **Remaining LOW (owner review):** the GetRight link target; A14; the sidebar «most downloaded» line shows
    `downcount` while OLD printed `hits` (its sort key); OLD's ad slots 5 / 9 and «new» badges.
  - **Status unchanged:** IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION; not owner closed.
- **Owner manual QA + decisions (2026-11-04):** A9, A10, A11 and A12 — OWNER MANUAL PASS on the local site. A13 recorded
  as NO-OP. Owner accepted: (1) the GetRight link to the official site `https://getright.com/` instead of OLD's dead
  installer link; (2) A14 — `var-group-0.htm` and missing groups keep 404; (3) the broken legacy archive.org links (the
  13 investigated, the four same-pattern mirrors, mirror 8493's data correction) tracked separately as data, not as a
  Laravel parity gap; (4) the remaining LOW items — OLD's ad slots 5 / 9 between the lists / above the sub-groups and
  the «new» badges — deferred.
- **Sidebar count fix (owner, 2026-11-04):** the item sidebar's «الأكثر تحميلا» list (ordered `hits DESC`) displayed
  `downcount`; OLD `Top10()` printed `hits` («مرات التحميل : number_format(hits)»). `ContentSidebarWidget::
  anasheedMostDownloaded()` now selects `hits` instead of `downcount` and `components/content/anasheed-sidebar-list`
  prints `hits`. Ordering, department filter and six rows unchanged; the component and method are used only by the
  Anasheed item page; no other sidebar module touched. `AnasheedItemControllerTest`'s sidebar test asserts `1,234 مرة`
  from `hits` and not the `downcount` value. Verification: Anasheed item / group / visitor-parity / sidebar widget
  83 / 83; Architecture 5 / 5; PHPStan 0 errors; repository-wide `pint --test` PASS; full suite not re-run (no shared
  behaviour touched; last full run 2,361 / 2,362, 1 skipped). Known Anasheed HIGH / MEDIUM visitor-parity gaps: 0.
  Status: **IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION**; not owner closed.

---

## 88. ANASHEED VISITOR PARITY — OWNER CLOSED LOCALLY (2026-11-04)

Owner decision on #87 after manual browser verification. Visitor parity only — the Anasheed OLD public admin controls
were closed separately (#61–#67) and are not reopened. Documentation only — no application code, test, data, staging,
commit, push, deployment or production change.

| ID | Gap | Priority | Final |
|---|---|---|---|
| A1 | News pages by department (98 with 16; pinned, then newest 24) | HIGH | OWNER MANUAL PASS |
| A2 | archive.org links normalised for downloads and the player | HIGH | OWNER MANUAL PASS |
| A3 | Group order `order_in_group DESC, mytime DESC` | MEDIUM | OWNER MANUAL PASS |
| A4 | Group 98 lists only its own items | MEDIUM | OWNER MANUAL PASS |
| A5 | Item notes «تعليق على المادة» | MEDIUM | OWNER MANUAL PASS |
| A6 | Hidden items: details hidden, downloads / player refused | MEDIUM | OWNER MANUAL PASS |
| A7 | 24 per page (group items, comments) | LOW | OWNER MANUAL PASS |
| A8 | `.grx`: `hidden = 0`, `weight ASC`, «الأناشيد» folder | LOW | AUTOMATED PASS (owner-accepted) |
| A9 | Sub-group cards: icon image, «التعليق», zero counts | LOW | OWNER MANUAL PASS |
| A10 | Series block on sub-groups only; GetRight link and notice | LOW | OWNER MANUAL PASS — link to the official `https://getright.com/` accepted (OLD's installer link answers 403) |
| A11 | Pager above and below the group items | LOW | OWNER MANUAL PASS |
| A12 | Item sidebar by department, six rows, per-department title; «الأكثر تحميلا» shows `hits` as OLD | LOW | OWNER MANUAL PASS (sidebar count corrected) |
| A13 | Group description | LOW | NO CHANGE REQUIRED — OLD never prints it; no local data |
| A14 | `var-group-0.htm` and missing groups | LOW | **OWNER DECISION: keep 404** |

- **Legacy data, tracked separately (not a Laravel gap):** the 13 investigated archive.org links (broken in OLD and
  Laravel alike), the four mirrors with the same truncated URLs (10848, 10853, 10913, 10914) and the optional data
  correction for mirror 8493 (#87 addendum).
- **Deferred LOW, owner-approved:** OLD's ad slots 5 / 9 and the «new» badges.
- **Quality evidence:** last full suite 2,362 tests — 2,361 passed, 0 failed, 1 skipped (pre-existing); latest targeted
  83 / 83; Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide `pint --test` PASS; owner manual browser
  verification complete. Implementation and test evidence: #87.
- **Known HIGH / MEDIUM Anasheed visitor-parity gaps: 0.**
- **Not claimed:** whole-migration completion, production verification, deployment / cutover. R1 stays deferred. The
  remaining visitor-parity modules (plan §54: Fatwa #71 items, W2ACD, admin hidden-content visibility, handoff §3 items)
  are not yet investigated; the no-push / no-deploy rule still applies.
- **Status:** **ANASHEED VISITOR PARITY — OWNER CLOSED LOCALLY (2026-11-04).**

---

## 89. FATWA VISITOR PARITY — F6 / F5b — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-04)

Targeted investigation of the known Fatwa items (#71's three observations, handoff §3 `fatawa-category-{id}`), then the
owner-approved HIGH / MEDIUM fixes. OLD `modules/Fatwa/index.php` and OLD `.htaccess` govern. Fatwa admin controls
(#69–#71) not reopened.

- **Investigation (read-only; local data 15,865 general questions, 13,364 fatawa):**

  | ID | Item | OLD | Laravel before | Classification |
  |---|---|---|---|---|
  | F1 | `fatawa-all-{q}` breadcrumbs | one trail per topic | one trail, first topic (owner-approved `answer2.php` layout, #71) | LOW — 3 multi-topic questions (2990, 3447, 6244) — **owner: deferred** |
  | F2 | `fatawa-all-{q}` sidebar | `Top10()` with the LAST topic's category (loop leak) | first topic's category | LOW — differs for 2 questions — **owner: deferred** |
  | F3 | admin panel category | `AdvancedStats()` with the last topic | — | admin-only; admin controls OWNER CLOSED — out of scope |
  | F4 | channel-list `fatawa-group-0-0.htm` fallback | empty-text link `fatawa-group-{t}-.htm` | empty-text link `fatawa-group-0-0.htm` | NO GAP — the 19 general questions with an unresolved first topic have no fatwa, so never appear on a channel list |
  | F5 | `fatawa-category-{id}.htm` | no rewrite rule, no generated link in OLD | owner-approved alias of `fatawa-topics` (IF-038) | NO GAP |
  | **F5b** | `fatawa-topics-{cat}.htm` (no page) | `.htaccess:631` serves it; OLD's live `index.php` links every category this way (`MainPage()` tree base link, `Logo()` breadcrumbs) | **404** — only `-{page}.htm` registered; a view comment called OLD's link dead, judged against Gen 2's `.htaccess` | **MEDIUM** — every external / bookmarked / indexed OLD category URL broken |
  | **F6** | `fatawa-channel-0.htm` «بدون قناة» | `list_Fatawa_for_channel(0)`: the `channel_id = 0` questions, no details block, `Top10(0, 0)` sitewide sidebar | **404** — `Channel::findOrFail(0)`, no row 0 — while Laravel links it from the channels index's first card, every channel-less fatwa's «مكان الإصدار» and the day page | **HIGH** — 11,574 of 13,364 fatawa (87%) have `channel_id = 0` |

- **Implemented:**
  - **F6** `FatwaChannelController::show()`: channel 0 uses an unsaved `Channel` (`id 0`, title «بدون قناة», `satellite`
    relation set to null — no query, never saved; no database row created); questions from the existing
    `fatwaQuestionsForChannel(0, …)` (`channel_id = 0`, 25 a page, `fatawa-channel-0-{page}.htm`); sidebar
    `fatwaMostDownloadedSitewide()` / `fatwaMostRecentSitewide()` (OLD `Top10(0, 0)` skips an empty channel filter).
    `fatawa/channel-show.blade.php`: the «بيانات قناة» block skipped for channel 0 (OLD `if(!empty($id))`); document
    title OLD's channel-0 form «بدون قناة - الفتاوى المرئية - …» (no «قناة» prefix, no satellite). Real channels
    unchanged; unknown ids still 404 via `findOrFail`.
  - **F5b** `routes/content.php`: `GET /fatawa-topics-{category}.htm` → `FatwaTopicController::show`, page 1
    (`fatawa.topics.show.legacy`), same page as `-1.htm`; paged routes, `fatawa-category-{id}` alias, breadcrumbs,
    counters and queries unchanged. The stale "dead link" comment in `fatawa/topics-show.blade.php` corrected.
- **Tests:** new `tests/Feature/Content/FatwaVisitorParityTest.php` (9): channel 0 page 1 / 2 (25 + 5, only
  `channel_id = 0`), title and captions; no details block; sitewide sidebar vs a real channel's scoped one; a real channel
  unchanged; unknown channel 404 (plain and paged); no row 0 created; `fatawa-topics-{cat}.htm` byte-identical to `-1.htm`
  (CSRF token aside); missing category 404 on both forms; paged route and `fatawa-category` alias unchanged. No existing
  test changed.
- **Verification:** new 9 / 9; targeted Fatwa set (17 files, Content + `FatwaAdminControlsParityTest` +
  `FatwaMultiMembershipReadersTest`) 246 / 246; Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide
  `pint --test` PASS; full suite 2,371 tests — 2,370 passed, 0 failed, 1 skipped (pre-existing).
- **Known HIGH / MEDIUM Fatwa visitor-parity gaps after this:** 0 among the investigated items (F1–F6). Not a whole-module
  audit.
- **Status:** **FATWA VISITOR PARITY (F6 / F5b) — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.** Not owner closed.
  No staging, commit, push or deployment.
- **Owner manual QA (2026-11-04):** F6 functional PASS — `fatawa-channel-0.htm` 200, 11,574 questions paginated, sitewide
  sidebar, no details block. Presentation not yet approved (titles centred, metadata in small detached boxes, large gaps,
  «التالي» wrapping alone).
- **F6 UI refinement (presentation only):** `fatawa/channel-show.blade.php` (the only view with this row markup; not a
  shared component) — each row is a compact right-aligned card (`.w2a-fatwa-q__card`: the question title, then «الموضوع»
  and «الشيخ» grouped beneath on one line, stacked on phones); the list wrapper gains `.w2a-fatwa-channel-list`. Scoped
  CSS appended to `content-refresh.css`, outranking the global `table#sample_5` rules (the centred, 60px-wide first cell;
  row borders) only inside that wrapper; the pager there puts its summary above the controls so they keep one row on
  desktop, and uses compact controls on phones (two centred rows). Links, text, `table#sample_5`, controller, query,
  ordering, page size and routes unchanged; other Fatwa pages and the shared pagination component untouched.
  Headless-Chrome screenshots at 1366px and 390px: channel 0 (pages 1 and 200) and channel 3. Fatwa channel tests
  50 / 50; Fatwa set 229 / 229; Pint PASS (no PHP touched). Status unchanged — awaiting owner browser verification.

---

## 90. FATWA VISITOR PARITY — OWNER CLOSED LOCALLY (2026-11-05)

Owner decision on #89 after manual browser verification. Scope: the investigated Fatwa visitor-parity items F1–F6 — not a
whole-module audit. Fatwa admin controls (#69–#71) were closed separately and are not reopened. Documentation only — no
application code, test, data, staging, commit, push, deployment or production change.

| ID | Item | Priority | Final |
|---|---|---|---|
| F6 | `fatawa-channel-0.htm` «بدون قناة» (channel-0 questions, pagination, sitewide sidebar, no details block) | HIGH | **OWNER MANUAL PASS** — listing and pagination verified; the redesigned question cards OWNER APPROVED |
| F5b | Legacy `fatawa-topics-{cat}.htm` (same page as `-1.htm`) | MEDIUM | **OWNER MANUAL PASS** — `/fatawa-topics-22.htm` and `/fatawa-topics-22-1.htm` both verified |
| F1 | Breadcrumb per topic on multi-topic `fatawa-all-{q}` | LOW | **DEFERRED — owner-approved** (3 questions) |
| F2 | `fatawa-all-{q}` sidebar from the last topic's category | LOW | **DEFERRED — owner-approved** (2 questions) |
| F3 | Admin panel category | — | out of scope — admin-only; admin controls OWNER CLOSED |
| F4 | Channel-list `fatawa-group-0-0.htm` fallback | — | NO GAP (unreachable with current data) |
| F5 | `fatawa-category-{id}.htm` | — | NO GAP (no such OLD URL; owner-approved alias kept) |

- **Implementation and test evidence:** #89 (F6 unsaved `Channel` for id 0 — no database row; F5b route
  `fatawa.topics.show.legacy`; the scoped `.w2a-fatwa-channel-list` card / pager presentation).
- **Automated quality evidence:** last full suite 2,371 tests — 2,370 passed, 0 failed, 1 skipped (pre-existing);
  latest Fatwa targeted 229 / 229; Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide `pint --test` PASS.
- **Known HIGH / MEDIUM gaps within the investigated Fatwa visitor-parity scope: 0.**
- **Not claimed:** whole-migration completion, production verification, deployment / cutover. R1 stays deferred. The
  no-push / no-deploy rule still applies until the remaining visitor-parity modules (plan §54) are reviewed.
- **Status:** **FATWA VISITOR PARITY — OWNER CLOSED LOCALLY (2026-11-05).**

---

## 91. W2ACD VISITOR PARITY — W3 — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-05)

Targeted investigation of the three recorded W2ACD visitor items (#75–#78: the series box, ads position 9, the `cds-main`
group cards), then the owner-approved fix. OLD `modules/w2acd/index.php` governs. `cds-news` stays owner-excluded (#74);
the W2ACD admin controls (#73–#78, OWNER CLOSED) are not reopened.

- **Investigation (read-only; local data: 3 W2ACD groups, all top-level, none with sub-groups; 39 CDs, all in group 0;
  `nuke_ads` 1 row, position 4, `show = 0`):**

  | ID | Item | OLD | Laravel before | Classification |
  |---|---|---|---|---|
  | W1 | «تحميل سلسلة» box + `cds-series-{id}.grx` | box only on a sub-group (`parent_id > 0`) with CDs; `DownSeries()` lists Anasheed `var-download-{CD id}.htm` links — wrong files | box absent; `.grx` 404 | **LOW — owner: deferred.** Not visible with current data; OLD's output wrong; neither reproduced nor rebuilt |
  | W2 | Ad slot 9 (`show_ads_byposition(9)`) | a random active position-9 ad, else nothing | nothing | **LOW — owner: deferred.** No position-9 ad exists; OLD printed nothing |
  | **W3** | `cds-main.htm` group cards | `Group(0)` → `ListGroup(0)`: every top-level group (`parent_id = 0 ORDER BY id DESC`) as a card above the general CDs | none — top-level groups reachable only by URL; a group added by the owner-closed M4 redirected to `cds-main` and did not appear | **MEDIUM** — Laravel regression |

- **Correction recorded:** `cds-series-{id}.grx` was classified SOURCE UNRECOVERABLE (IF-044, handoff §3). OLD's
  `DownSeries()` exists, but produces incorrect Anasheed download links. Dated corrections added to IF-044 and the handoff
  §3 row; the historical text is kept.
- **Implemented (W3):**
  - `W2acdController`: a private `groupCards(int $parentId)` — the group page's existing sub-group query, unchanged
    (`parent_id`, `id DESC`, live `children` / `items` counts, `CdGroupIcon::forCard()`) — now used by `group()` and by
    `index()` for group 0 (`cds-main.htm`, and Gen 2's `/w2acd/cds.php` with no id). The CD query, ordering, 24 per page,
    routes, redirects and counters unchanged (cds-main increments no group).
  - Views: the sub-group card panel moved verbatim from `w2acd/group.blade.php` into `w2acd/_subgroup-cards.blade.php`
    (same markup, classes, 89×88 icon, «قائمة الأقسام الفرعية» panel); `group.blade.php` includes it (group page
    unchanged); `w2acd/index.blade.php` includes it above «قائمة الإسطوانات العامة», only when top-level groups exist.
- **Tests:** new `tests/Feature/Content/W2acdVisitorParityTest.php` (8): top-level only, `id DESC`, nested excluded; panel
  above the general list; links, live counts, visits, comment / «بدون تعليق»; icon when `icon = 1` and the file exists,
  else `way2_cddefault.png`; no panel without top-level groups; an M4-created group first after the redirect; group-0 CDs
  only, 24 a page, no group hit; the group page's own cards unchanged. No existing test changed.
- **Verification:** new 8 / 8; W2ACD content + admin-control set (7 files) 146 / 146; Architecture 5 / 5; PHPStan level
  5: 0 errors; repository-wide `pint --test` PASS; full suite 2,379 tests — 2,378 passed, 0 failed, 1 skipped
  (pre-existing).
- **Local data untouched:** the existing groups (1, 3, 4 — partly owner test records) and CDs are left as they are.
- **Status:** **W2ACD VISITOR PARITY (W3) — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.** W1 / W2 deferred LOW
  (owner-approved). Not owner closed. No staging, commit, push or deployment.

---

## 92. W2ACD VISITOR PARITY — OWNER CLOSED LOCALLY (2026-11-05)

Owner decision on #91 after manual browser verification. Scope: only the three investigated items W1, W2 and W3 — not a
whole-module or whole-migration audit. The W2ACD admin controls (#73–#78) stay OWNER CLOSED and are not reopened;
`cds-news` stays excluded by owner decision (#74). Documentation only — no application code, test, route, data,
migration, staging, commit, push, deployment or production change.

| ID | Item | Priority | Final |
|---|---|---|---|
| W3 | `cds-main.htm` top-level group cards (`parent_id = 0`, `id DESC`, above the general CDs) | MEDIUM | **OWNER MANUAL PASS** — cards appear in order 4, 3, 1 with icons, counts, visits and comments; card styling and RTL layout approved; the general CD list stays below. `/cds-group-1.htm` opens with its title and breadcrumb, no regression; `/cds-group-0.htm` → `/cds-main.htm` |
| W1 | «تحميل سلسلة» box + `cds-series-{id}.grx` | LOW | **DEFERRED — owner-approved.** OLD `DownSeries()` exists but generates incorrect Anasheed download links; that behaviour is not reproduced and no new `.grx` is built (dated correction in IF-044 and handoff §3, historical text kept) |
| W2 | Ad slot 9 | LOW | **DEFERRED — owner-approved.** No active advertisement exists at that position |

- **Implementation and test evidence:** #91.
- **Automated quality evidence:** W2ACD tests 146 / 146; full suite 2,379 tests — 2,378 passed, 0 failed, 1 skipped
  (pre-existing); Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide `pint --test` PASS.
- **Local test data:** the existing groups and CDs are left unchanged.
- **Known HIGH / MEDIUM gaps within the investigated W2ACD scope: 0.**
- **Not claimed:** production verification, whole-module or whole-migration completion, deployment / cutover. R1 (the
  versioned git baseline) stays deferred; the no-push / no-deploy rule still applies until the remaining visitor-parity
  items (plan §54) are reviewed.
- **Status:** **W2ACD VISITOR PARITY — OWNER CLOSED LOCALLY (2026-11-05).**

---

## 93. ADMIN HIDDEN-CONTENT VISIBILITY — V1 IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-05)

Targeted investigation of #72's recorded item ("admins see hidden content on Cat, Satellite channel and search pages"),
then the owner-approved fix. A visibility question, not admin controls — the owner-closed admin controls are not reopened.

- **OLD (source):** `is_w2a_admin()` (`mainfile.php:377`) is true for any valid `nuke_authors` admin (signed bridge
  cookie), no per-module permission. **Search** (`modules/search`): video / audio / dumped files — visitors `hidden = 0`
  (series also `count > 0`), admins everything, hidden series marked «++مخفي++» (`:585/:608/:629`); Anasheed departments —
  items filtered for visitors; gallery / CDs / Fatwa — no filter for anyone; `advanced_search` `:140/:293` the same admin
  branch. **Cat** (`modules/Cat`): `category-{id}.htm` rewrites straight to `modules/Cat/index.php`, whose
  `modules\.php` guard dies — that OLD URL is dead; `category-series` / `category-group` (via `new_modules.php`) show
  admins hidden items (no marker) and hidden series («++مخفي++»); `Series()` never checks the series' own `hidden`.
  **Satellite** (`modules/Satellite`): `list_series()` has no hidden filter for anyone (visitors saw hidden channel series);
  standalone items and Anasheed — admins see hidden ones, no marker.
- **Laravel before:** search, advanced search, `category-{id}`, `category-series` items and `channel-{id}` always
  `hidden = 0` (series `count > 0`) for visitors and admins; Khotab item / series pages follow the owner decision of
  2026-10-21 (#55 / #56: visitors 404, any admin sees it, private); `category-series-{id}-{cat}.htm` loaded the series
  with **no hidden check for anyone**.
- **Local data:** hidden — 73 / 14,552 series, 87 / 292,592 items, 0 / 328 groups; 46 hidden series and 2 hidden
  standalone video items on a channel; 45 hidden series filed in categories; 61 hidden series contain 834 non-hidden items.

| ID | Difference | Decision |
|---|---|---|
| **V1** | `category-series-{id}-{cat}.htm` rendered a hidden series (title, breadcrumb, its visible items) for visitors — direct URL only (no Laravel listing links it), inherited from OLD `Series()`; inconsistent with `khotab-series` | **MEDIUM — implemented** |
| V2 | Admins do not see hidden / empty series and hidden items in Search / Advanced Search (OLD did, with «++مخفي++») | **DEFERRED LOW — owner-approved** |
| V3 | Admins do not see hidden content in Cat listings | **DEFERRED LOW — owner-approved** |
| V4 | Admins do not see hidden standalone items in Satellite listings | **DEFERRED LOW — owner-approved** |
| V5 | OLD showed visitors hidden channel series and hidden gallery / CD / Fatwa search results | **Intentional — Laravel's safer visitor visibility preserved** |

- **Implemented (V1):** `CategorySeriesController::show()` takes `App\Support\Http\AdminViewer` (the existing port, the
  per-request `ContextualAdmin`; no new authentication) and, as its **first** step — before the category lookup, the item
  and sidebar queries or any output — loads the series with `when(! $admin, where('hidden', 0))->findOrFail()`, exactly
  as `KhotabSeriesController`. Visitors and signed-in vBulletin members: a hidden series is a 404. Any signed-in
  administrator: 200, and `markRendered()` makes `KeepContextualPagesPrivate` (web group) send `no-store, private`. The
  page has no counters or other side effects. Listed items keep `hidden = 0` for everyone (item rule unchanged); visible
  series unchanged for visitors (public caching kept).
- **Tests:** new `tests/Feature/Content/CategorySeriesHiddenVisibilityTest.php` (6): anonymous visitor → 404, no title or
  items; signed-in member → 404; plain admin and super-admin → 200 with the hidden series' visible items, `no-store,
  private`; visible series for a visitor → 200, hidden item absent from the listing, no `no-store`; admin on a visible
  series → hidden items still filtered, private; nonexistent series / category → 404 for visitor and admin. No existing
  test changed.
- **Verification:** new 6 / 6; CategorySeries + Khotab item / series visibility set 170 / 170; Architecture 5 / 5;
  PHPStan level 5: 0 errors; repository-wide `pint --test` PASS; full suite 2,385 tests — 2,384 passed, 0 failed,
  1 skipped (pre-existing).
- **NEW FINDING — OPEN, POTENTIAL HIGH, NOT FIXED (outside V1's approved scope; owner decision required):** while testing,
  the `category-series` sidebar showed a hidden item's title to a visitor. `ContentSidebarWidget::
  khotabMostDownloadedByCategoryForSeries()` / `khotabMostRecentByCategoryForSeries()` deliberately carry no `hidden`
  filter (ported from Gen 2 `categories/series.php`; OLD `Cat` `Top10()` had none either), and `khotabRandomFeatured()`
  — used on seven pages (Khotab author / group / news / series / item, `category-{id}`, `category-series`, the Anasheed
  category page) — has none either. Visitors can therefore see hidden items' titles and links in these sidebars (the
  item pages themselves still answer 404). Not changed here; the V1 tests assert the main listing only.
- **UNCLASSIFIED FOLLOW-UP (not investigated, not implemented):** Cat / Satellite legacy-route and visitor-parity review.
  Laravel's Cat and channel pages follow Gen 2's `categories/` / `channels/` code rather than OLD's `Cat` / `Satellite`
  modules; OLD routes possibly missing in Laravel: `series_list-*`, `var-channel*`, `channel(s)-ramadan*`,
  `category-group-*`. Priority to be set only after investigation.
- **Status:** **ADMIN HIDDEN-CONTENT VISIBILITY — V1 IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.** Not owner
  closed. No staging, commit, push or deployment.
- **[Clarification 2026-11-08, #133: superseded — V1 OWNER CLOSED LOCALLY in #97 (2026-11-05). Original wording kept.]**

---

## 94. HIDDEN-CONTENT SIDEBAR EXPOSURE — SECURITY FIX — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-05)

The open finding of #93, approved as a narrowly scoped HIGH remediation within the local pre-deployment review. Owner-closed
admin controls not reopened; no data, migration, staging, commit, push, deployment or production change.

- **Confirmed exposure:** hidden Khotab items' **titles and links** (`/khotab-item-{id}.htm`, plus the author name and the
  frame / gif image path in the featured card) were offered to visitors by three public sidebar queries in
  `ContentSidebarWidget` that carried no `hidden` filter. The item pages themselves already answer 404 to visitors; no
  evidence of full-content or media-file exposure.
  - `khotabMostDownloadedByCategoryForSeries()` / `khotabMostRecentByCategoryForSeries()` — selected `id, title, author,
    frame, hits, downcount, time`; rendered by `components/content/top-items` («الأكثر تحميلا» / «جديد المواد») on
    `category-series-{ser}-{cat}.htm` only. Ported deliberately from Gen 2 `categories/series.php` (IF-039; OLD `Cat`
    `Top10()` had no filter either).
  - `khotabRandomFeatured()` — selected `id, channel_id, author, title, comments, time, hits, gif, frame` + author name;
    rendered by `components/content/featured-items` («مادة مختارة») on eight views: Khotab author (`khotab-{op}-{author}`),
    group, news (`khotab-{op}_news`), series, item, `category-{id}`, `category-series`, and the Anasheed category page
    (`var-category-{id}`).
- **Fix (`ContentSidebarWidget`, three methods only):** `->where('kh.hidden', 0)` added — both category-series queries,
  and both scans (forward and backward) of the random pick. Joins, category / video constraints, ordering, limits (5; 1),
  selected columns and response shapes unchanged; no extra query; no controller or view change; admin authorization
  untouched.
- **Cache safety:** the category-series pair is cached through `rememberRows()` (`Cache::remember`, TTL 300 s), keyed
  `sidebar:category-series-khotab:category={id}:limit=5:order={hits|time}`; entries written before the fix could hold
  hidden rows. The key now carries `hidden=0` (`…:category={id}:hidden=0:limit=5:order=…`), so pre-fix entries are never
  read again and simply expire within 300 s; no cache flush, no other key touched. `khotabRandomFeatured()` is not
  cached.
- **Tests:** new `tests/Feature/Content/SidebarHiddenItemsTest.php` (11): the pair excludes hidden rows while keeping
  visible order, limit 5, the category and video filters; a pre-fix cache entry holding a hidden row is ignored and the
  versioned keys are written; the random pick never returns a hidden item over 40 random starts, and returns nothing when
  only hidden items have a visual; on all seven page families (Khotab author, group, news, series, item, category,
  category-series) a visitor's featured card shows the visible item and never the hidden title or link, and the
  category / category-series pages contain the hidden title nowhere. Mutation check: without the filters 6 of the 11 fail.
  Updated to the approved behaviour, not loosened: `CategorySeriesControllerTest` IF-039 (now asserts the hidden item is
  **absent** and a visible one present); `CategorySeriesProbeTest` ×2 and `ContentSidebarWidgetCacheTest` (the versioned
  key; TTL unchanged); the latter's C-2 non-collision test now proves separation with sentinel entries, since both lists
  exclude hidden rows.
- **Verification:** new 11 / 11; sidebar, CategorySeries and Khotab content + admin set 465 / 465 after the updates;
  Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide `pint --test` PASS; full suite 2,396 tests — 2,395
  passed, 0 failed, 1 skipped (pre-existing).
- **REMAINING VISIBILITY RISK — same unsafe pattern, OUTSIDE this approved scope, not changed (owner decision):** seven
  more public Khotab sidebar lists carry no `hidden` filter — `khotabMostDownloadedByAuthor()` /
  `khotabMostRecentByAuthor()` (Khotab author, group and series pages), `khotabMostDownloadedByVideoFlag()` /
  `khotabMostRecentByVideoFlag()` (Khotab item, news and day pages), `radioMostRecentByVideoFlag()` (radio page) — all
  through the private `topitems()` helper, which applies only the filters its callers pass — and
  `channelMostDownloadedKhotabItems()` / `channelMostRecentKhotabItems()` (`channel-{id}`). The same titles-and-links
  exposure therefore remains in those lists.
- **Status:** **HIDDEN-CONTENT SIDEBAR EXPOSURE — FIX IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.** The Admin
  Hidden-Content Visibility scope (#93) is not owner closed.
- **[Clarification 2026-11-08, #133: superseded — closed within #97 (2026-11-05). Original wording kept.]**

---

## 95. HIDDEN-CONTENT SIDEBAR EXPOSURE — FOLLOW-UP SECURITY FIX — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-05)

Owner-approved, narrowly scoped follow-up to #94 for the remaining public Khotab sidebar lists. Owner-closed admin
controls not reopened; V2 / V3 / V4 stay deferred LOW; V5 (Laravel's safer visitor visibility) preserved. No data,
migration, staging, commit, push, deployment or production change.

- **Scope reconciled:** #94 named seven methods (by author ×2, by video flag ×2, radio, channel ×2). A full scan of every
  `ContentSidebarWidget` method that reads `nuke_islamic_khotab` (or calls `topitems()`) found **two more, previously
  omitted: `liveStreamMostViewed()` / `liveStreamMostRecent()`**. Nine methods in total; none had an admin branch; every
  one returned hidden rows.

  | Method | Query path | Cached (TTL / key) | Callers → public pages | Rendered |
  |---|---|---|---|---|
  | `khotabMostDownloadedByAuthor` / `khotabMostRecentByAuthor` | `topitems()` | 300 s, `sidebar:topitems:{filters}:limit:order` | KhotabAuthor / Group / Series → `khotab-{op}-{author}`, `khotab-group-{id}`, `khotab-series-{id}` | `id, title, author, frame, hits, downcount, time` → title, link, thumbnail |
  | `khotabMostDownloadedByVideoFlag` / `khotabMostRecentByVideoFlag` | `topitems()` | 300 s, same | KhotabItem / Day / News → `khotab-item-{id}`, `khotab-{op}-today`, `khotab-{op}date-…`, `khotab-{op}_news` | same |
  | `radioMostRecentByVideoFlag` | `topitems()` | 300 s, same | Radio → `radio.htm`, `radio-mobile.htm` | same |
  | `channelMostDownloadedKhotabItems` / `channelMostRecentKhotabItems` | own query | 300 s, `sidebar:channel-khotab:channel:limit:order` | Channel → `channel-{id}` | `id, title, author, frame, hits, time` |
  | `liveStreamMostViewed` / `liveStreamMostRecent` | shared `query()` | not cached | LiveStream → `live-channel-{id}` | `id, title` |

- **Shared helpers:** `topitems()` has exactly five callers — all five above, all public, all needing the same rule — so
  each caller now passes `'hidden' => 0` in its filter list (explicit; the helper body is unchanged). The generic
  `query()` helper is shared with Telawah, W2ACD and Anasheed and is **not** changed; the live-stream pair now uses its
  own private `liveStreamKhotab()` — the same `id, title`, optional `channel_id`, `ORDER BY … DESC`, 10 rows — plus
  `hidden = 0`.
- **Exact changes (`ContentSidebarWidget` only):** `'hidden' => 0` in the five `topitems()` filter lists;
  `->where('hidden', 0)` in both channel queries; the live-stream query above. Ordering, limits (5 / 10), the author,
  channel and video filters, selected columns, response shapes and query counts unchanged; no controller or view change.
- **Cache safety:** `topitems()` keys are built from the filter list, so adding `hidden=0` versions them automatically
  (`sidebar:topitems:author=1:hidden=0:limit=5:order=hits:vedio=1`, …); the channel pair's key gains `hidden=0`
  explicitly. Pre-fix entries are never read again and expire within their unchanged 300 s TTL; no flush, no other key
  touched. The live-stream pair is not cached.
- **Tests:** new `tests/Feature/Content/SidebarHiddenItemsFollowUpTest.php` (21): each of the nine methods (plus the
  live stream without a channel) excludes the hidden item while keeping exact visible order, limits and the author /
  channel / video filters (decoys: another author, another channel, the other video flag); entries planted under all
  seven pre-fix cache keys are ignored and the versioned keys are written; the #94 methods stay safe; on nine page
  families — Khotab author, group, series, item, news, day, radio, Satellite channel, live channel — a visitor's page
  contains the hidden title and link **nowhere** and still renders the visible item. Mutation check: without this
  round's filters 20 of the 21 fail (the #94 test still passes). No existing test changed; #94's 11 still pass.
- **Verification:** new 21 / 21; related sidebar, cache, Khotab, Channel, Radio and live-stream set 458 / 458 (before
  the new file); Architecture 5 / 5; PHPStan level 5: 0 errors; repository-wide `pint --test` PASS (Pint applied to the
  new test file only); full suite 2,417 tests — 2,416 passed, 0 failed, 1 skipped (pre-existing).
- **Remaining known visibility risks:** within `ContentSidebarWidget`, every method reading Khotab items now excludes
  hidden ones. Not audited here (outside this scope): Telawah sidebar lists (`query()`, no hidden filter); other public
  widgets outside `ContentSidebarWidget` (e.g. the home page). Anasheed and W2ACD lists keep listing hidden items by
  owner-closed decision (Anasheed A6; W2ACD as OLD).
- **Status:** **HIDDEN-CONTENT SIDEBAR EXPOSURE — FOLLOW-UP FIX IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.**
  Admin Hidden-Content Visibility (#93–#95) is not owner closed.
- **[Clarification 2026-11-08, #133: superseded — closed within #97 (2026-11-05). Original wording kept.]**

---

## 96. KHOTAB AUTHOR SIDEBAR «جديد المواد» — LOW UI REFINEMENT — OWNER VERIFIED (2026-11-05)

Owner visual review of `/khotab-video-89.htm`: in «جديد المواد» the dates ran past the cards' left edge and were
clipped. **LOW UI refinement**, CSS only. Not changed: sidebar queries, hidden-content filters (#94 / #95), cache keys
and TTLs, links, ordering, pagination, controllers, views, data, admin controls.

- **Cause:** the shared `x-content.sidebar-ranking` component (12 views) renders each date as
  `.w2a-ranking-meta-tag`, which was `white-space: nowrap`. In the Khotab author sidebar (`col-lg-3`) the text column
  beside the badge and thumbnail is ~108 px at 1366 px; a date such as «الخميس 10 يناير 2019 مـ» is 151–166 px, so it
  overflowed the card by up to 48 px and the panel (`overflow: hidden`) clipped it. At 390 px the column is wide enough.
- **Fix (`public/assets/frontend/layout/css/content-refresh.css`, one appended block after `.w2a-ranking-meta-tag`):**
  the tag is `inline-block`, `max-width: 100%`, `white-space: normal`, `overflow-wrap: break-word`, side padding
  8 → 6 px (icon inline, 2 px margin) — it stays on one line where it fits and wraps inside the column where it does
  not; `.w2a-ranking-item__link` / `__meta` capped at 100 %; titles keep their 2-line clamp plus
  `overflow-wrap: anywhere` for long unbroken words. The 2 px padding trim keeps download tags that were already
  ~1 px too wide (e.g. «19,909 تحميل», 107.7 px in a 106.5 px column) on one line instead of newly wrapping.
- **Verification (headless Chrome, fresh profile, 1366 / 390 px):** all five «جديد المواد» dates on
  `/khotab-video-89.htm` fully inside their cards at both widths (desktop: two lines; mobile: one line, unchanged);
  badges and thumbnails aligned; no horizontal page scroll. Shared-component regression, before (original rules
  re-injected) vs after, every ranking panel on `/khotab-video-89.htm`, `/khotab-audio-89.htm`, `/khotab-video-2.htm`,
  `/khotab-video_news.htm`, `/dumped-lectures.htm`, `/recite-news.htm`, `/live-stream.htm`, `/fatawa-topics-1.htm`,
  `/var-category-1.htm`: overflowing tags 10 → 0, card heights unchanged everywhere except the formerly clipped date
  panels, document width = viewport. Pages that count views (item / group / series, `live-channel-*`) not opened.
  Related tests 150 / 150; Pint PASS (no PHP changed).
- **Status:** **LOW UI REFINEMENT — OWNER VERIFIED (2026-11-05).** The owner visually approved the fix on
  `/khotab-video-89.htm`: all visible dates fully contained; RTL alignment, thumbnails and ranking badges acceptable;
  no further UI changes required. To be included in the documentation-only Admin Hidden-Content Visibility closure.
  Documentation-only update; no code, test or data change. Admin Hidden-Content Visibility (#93–#95) is not yet
  recorded as owner closed.

---

## 97. ADMIN HIDDEN-CONTENT VISIBILITY — OWNER CLOSED LOCALLY (2026-11-05)

Documentation-only owner closure of the **investigated** Admin Hidden-Content Visibility scope (#93–#96). No application
code, test, data, migration, staging, commit, push, deployment or production change in this entry. R1 stays deferred.

- **Owner manual verification — PASS** (performed before the sidebar UI refinement):
  1. `/khotab-video-89.htm` — visible item 73784 appears; hidden item 75722 does not.
  2. `/channel-3.htm` — hidden items 75722, 38529 and 68586 do not appear.
  3. `/category-series-2940-38.htm` — an anonymous visitor receives 404.
  4. Sidebar UI refinement — corrected dates visually approved; #96 OWNER VERIFIED.
  The V1 administrator path (200, `Cache-Control: no-store, private`) is covered by automated tests
  (`CategorySeriesHiddenVisibilityTest`), not by manual browser verification.
- **Automated verification (as reported in #95):** full suite 2,417 tests — 2,416 passed, 0 failed, 1 skipped;
  Architecture 5 / 5; PHPStan 0 errors; Pint PASS.
- **Closed scope:**

  | Item | Severity | Disposition | Record |
  |---|---|---|---|
  | V1 `category-series-{ser}-{cat}.htm` hidden series → 404 for visitors / members, private 200 for any admin | MEDIUM | Fixed and verified | #93 |
  | Initial sidebar exposure — `category-series` pair + `khotabRandomFeatured()` | HIGH | Fixed | #94 |
  | Follow-up sidebar exposure — author ×2, video flag ×2, radio, channel ×2, live stream ×2 | HIGH | Fixed | #95 |
  | «جديد المواد» sidebar date wrapping | LOW (UI) | OWNER VERIFIED | #96 |
  | V2 search, V3 Cat, V4 Satellite admin visibility of hidden content | LOW | Owner-approved deferred | #93 |
  | V5 OLD's visitor exposure of hidden content | — | Not reproduced — Laravel's safer visitor behaviour preserved | #93 |

- **Outside the closed scope (unclassified follow-ups where applicable, not investigated here):** Telawah sidebar
  visibility (generic `ContentSidebarWidget::query()`, no hidden filter); homepage and other public widgets outside
  `ContentSidebarWidget`; Cat / Satellite legacy-route parity (`series_list-*`, `var-channel*`, `channel(s)-ramadan*`,
  `category-group-*`); RSS feeds (handoff §3).
- **Not claimed:** a complete sitewide security audit; production verification; whole-migration closure.
- **Status:** **ADMIN HIDDEN-CONTENT VISIBILITY — OWNER CLOSED LOCALLY (2026-11-05).** Known HIGH / MEDIUM gaps in the
  investigated scope: 0. Local closure only — production verification and deployment NOT STARTED; no push / deploy.

---

## 98. HOMEPAGE KHOTAB VISIBILITY — H1 — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-05)

Owner-approved fix from the remaining public-widget visibility investigation (Telawah sidebar widgets, homepage
widgets and their immediate shared components). No migration, no local data changed, no staging, commit, push,
deployment or production access. Owner-closed scopes (Advanced Search, Anasheed, Fatwa, W2ACD, #97) not reopened.

- **Investigation result:** no current hidden-item exposure in local data. Data-dependent risk (H1): the three
  homepage Khotab widgets had no `hidden` filter (nor had OLD `index.php`); their top 3 by `lastmirror` / `pdf_time`
  does not change when an item is hidden, so hiding one of the newest items kept its title, link, thumbnail and
  author on `/` (link → visitor 404, #55 / #56), plus up to 300 s of cache. Local: videos 3 hidden matching rows
  (newest at rank 82,063), audios 0, PDFs 21 (newest at rank 78).
- **H1 MEDIUM — implemented (`ContentListingService`):** `->where('hidden', 0)` inside the derived table of
  `homeLatestVideos()`, `homeLatestAudios()` and `homeLatestDumpFiles()`, before its ORDER BY / LIMIT — each widget
  still shows up to 3 visible items. Ordering, `vedio` / `newslist` / `pdf` filters, joins, selected columns, limits,
  response shape and query count unchanged. `homeLatestDumpFiles()`'s docblock no longer lists "no hidden filter" as a
  deliberate difference from `khotabPdfDump()`.
- **Cache:** only the three keys versioned — `home-latest-videos:hidden0`, `home-latest-audios:hidden0`,
  `home-latest-dump-files-{limit}:hidden0` (private constants); TTL 300 s unchanged; pre-fix entries are never read and
  expire on their own; no flush.
- **Tests:** new `tests/Feature/Content/HomeKhotabHiddenVisibilityTest.php` (8, in-memory `main` connection, isolated
  data): hidden video / audio / PDF excluded with the next visible items in order, limit 3, filter decoys excluded;
  columns unchanged; entries planted under the pre-fix keys ignored, versioned keys written; homepage HTML has no
  hidden title or `/khotab-item-{id}.htm` link while the three widgets render; Anasheed A6 hidden items still listed on
  the homepage; Telawah homepage widget unchanged. Mutation check: without the three filters 4 / 8 fail (the three
  widget cases and the HTML case); source restored byte-identical. Updated existing tests: `ContentListingServiceTest`
  — the test asserting a hidden PDF row still appears now asserts its exclusion; the file-cache round-trip test and
  `HomeControllerTest`'s stale-audio-cache test use the versioned keys. Related set 218 / 218; full suite 2,425 —
  2,424 passed, 0 failed, 1 skipped; Architecture 5 / 5; PHPStan 0 errors; Pint PASS (repository-wide).
- **T1 — DEFERRED LOW (owner):** Telawah `hidden` is dormant — no OLD or Laravel writer, 0 local rows; OLD `Top10()`
  and the homepage never filtered it. Defensive option recorded, not implemented: own `hidden = 0` query for
  `telawahMostDownloaded()` / `telawahMostRecent()` (shared `query()` untouched) and `homeLatestTelawahs()`; an item-page
  404 would be a separate authorization decision. Telawah sidebar, homepage Telawah and item authorization unchanged.
- **Anasheed A6 — PRESERVED:** homepage Anasheed widgets keep listing hidden items (owner-closed A6; e.g. 2510 in
  «تشاهدون الآن», details withheld on its page). Anasheed `is_published` is a publish-to-platform flag with no public
  reader in OLD or Laravel. Not changed; Anasheed visitor parity not reopened.
- **Homepage slider — UNCLASSIFIED FOLLOW-UP:** OLD `index.php` shows `nuke_7amalat` rows with `status = 1 AND
  website = 0`; Laravel `homeSliderItems()` shows `status = 1 AND website = 1`. Both filter `status = 1` (no
  unpublished slide exposed). Not classified as a regression without further investigation; slider unchanged.
- **[Reconciliation 2026-11-07, #124: the slider difference above was investigated and resolved — Gen 2 `slider.php:3`
  uses `status=1 AND website=1 ORDER BY order_index ASC LIMIT 10`, which Laravel matches; OLD's `website = 0` / LIMIT 8
  is an OLD-to-Gen 2 behavioural change, not a Laravel regression. Owner closed locally as FUNCTIONALLY EQUIVALENT.
  Original text kept.]**
- **No exposure:** Fatawa and category-487 widgets (no visibility column), poll (OLD's standalone poll), shared homepage
  components (presentation only), no public view composers.
- **Status:** **HOMEPAGE KHOTAB VISIBILITY H1 — IMPLEMENTED LOCALLY — AWAITING OWNER VERIFICATION.** Not owner closed.

---

## 99. HOMEPAGE KHOTAB VISIBILITY (H1) — OWNER CLOSED LOCALLY (2026-11-06)

Documentation-only owner closure of #98. No application code, test, data, staging, commit, push, deployment or
production change. R1 stays deferred.

- **Owner verification:** manual homepage visual PASS on `http://127.0.0.1:8123/` — «أحدث المرئيات», «جديد الصوتيات»
  and «أحدث المواد المفرغة» render normally. The hidden-item exclusion itself is verified by automated tests, not by
  modifying local data.
- **Evidence (#98):** the three homepage queries (`homeLatestVideos()`, `homeLatestAudios()`, `homeLatestDumpFiles()`)
  exclude hidden Khotab items; their three cache keys versioned (`:hidden0`), 300 s TTL preserved, no flush; 8 new
  tests passed; related 218 / 218; full suite 2,425 — 2,424 passed, 0 failed, 1 skipped; Architecture 5 / 5;
  PHPStan 0 errors; Pint PASS.
- **Preserved:** T1 Telawah `hidden` defensive filter — deferred LOW; Anasheed A6 owner-approved listing behaviour;
  homepage slider (`website = 0` OLD vs `website = 1` Laravel) — unclassified follow-up.
- **[Reconciliation 2026-11-07, #124: the homepage slider follow-up above is resolved — FUNCTIONALLY EQUIVALENT to
  Gen 2, closed locally. Original text kept.]**
- **Status:** **HOMEPAGE KHOTAB VISIBILITY (H1) — OWNER CLOSED LOCALLY (2026-11-06).** Local closure only — production
  verification and deployment NOT STARTED.

---

## 100. ADMIN CONTROLS — LOCAL FUNCTIONAL CLOSURE ACCEPTED BY THE OWNER — ONE DISCLOSED ITEM PENDING DISPOSITION; DOCUMENTATION RECONCILIATION R3–R7 (2026-11-06)

Owner decisions of 2026-11-06 on the Admin Controls readiness inventory. Documentation only — no application code,
test, data, permission, staging, commit, push, deployment or production change. R1 stays deferred.

- **1. Admin functional closure — accepted by the owner, recorded with one pending item.** The owner accepted the
  evidence-supported verdict **ADMIN CONTROLS FUNCTIONALLY COMPLETE LOCALLY**: AdminCP / backend Phases 0–7 owner-accepted
  2026-10-15 (#59, plan §30, 0 known functional gaps; #38 `ADMIN_MIGRATION_COMPLETE`); OLD public admin controls owner
  closed module by module — Khotab #60, Anasheed #61–#67, Telawah #68, Fatwa #69–#71, W2ACD #73–#78, search /
  advanced_search #79–#80, `w2a_cd` #81–#82, the other 55 modules as #72 classified them; no known HIGH / MEDIUM Admin gap.
  **Reconciliation finding while recording this decision:** #41 records a "known, disclosed, NOT-implemented" AdminCP
  item — production's AdminCP sidebar shows a `النسخ الاحتياطي` (backup) group whose source is not in the reference
  snapshot — and `PROJECT-HANDOFF.md` §3 calls the `admincp/backup/` row "still-open". The 2026-11-06 inventory did not
  list it. Read-only local facts: OLD `old-project` has no `admincp/`; `legacy-project/admincp` has no `backup/`; the
  canonical Gen 2 tree `7amlat/admincp/backup/` holds only `edit_author.php` (page title «قائمة المشرفين», a staff
  edit page) and an `error_log`, and no `menu.php` — `sidebar.php` renders a group only from `menu.php`, so the group
  cannot render from this tree; production's own `menu.php` remains unrecoverable. Laravel's staff management is the
  Wave 5 tasks 5.6 / 5.7 rebuild ("authors / backup"). **Status of decision 1: OWNER ACCEPTED — FINAL WORDING PENDING the
  owner's disposition of this one item** (e.g. covered by the 5.6 / 5.7 staff rebuild, dead / unrecoverable, or a
  production check). Not classified here; no HIGH / MEDIUM functional gap is claimed for it.
- **Scope of the closure:** local functional only. Not claimed: production readiness, production verification, final
  visual parity, whole-migration completion, OLD Admin retirement.
- **2. AdminCP visual parity:** unchanged — `ADMINCP_VISUAL_PARITY_TEMPORARILY_ACCEPTED — SUBJECT_TO_OWNER_REVIEW` (#42).
  No final visual approval; no UI change.
- **3. Super-admin sidebar (D1c / #42) — APPROVED (owner):** super-admins may see all AdminCP modules; every underlying
  operation stays protected by its existing authorization. No permission-logic or UI change.
- **4. Documentation reconciliation (R3–R7) — done, historical text preserved, superseded statements marked:**
  - R3 — plan §7.1 gains a 2026-11-06 status refresh (evidence updated; open items unchanged).
  - R4 — the plan banner and §30 carry a note that the 2026-10-15 record is historical and later work (§31–§55,
    #60–#100) governs current status.
  - R5 — `PROJECT-HANDOFF.md` §7 marked partly historical (stale lines named) and §8 marked superseded.
  - R6 — #82's "§34" reference corrected by an inline note (→ §30.5).
  - R7 — plan §55: one consolidated OLD / Gen 2 retirement checklist compiled from the existing records.
  - Plan §28.1's blanket "88 operations stay excluded" sentence marked superseded by #72 and the module reviews.
- **5. Production readiness — all OPEN, nothing marked complete:** R1 (deferred), R8–R16 as applicable, R20, R21, R14
  business / infrastructure confirmations, DoD 2 / 3 production verification, DoD 5a / 5b / 6 / 9 and the §55
  retirement tasks.
- **Status:** **ADMIN CONTROLS — LOCAL FUNCTIONAL CLOSURE ACCEPTED BY THE OWNER (2026-11-06) — FINAL RECORD PENDING
  OWNER DISPOSITION OF THE ADMINCP BACKUP GROUP (#41).** Production deployment NOT STARTED.
- **[Correction 2026-11-06, #101 — factual error above:** the directory listing used for this entry was truncated
  (`ls … | head -5`). `7amlat/admincp/backup/` **does** contain `menu.php`, `index.php` and `edit_author.php` (plus
  `error_log` and `mysql_errors.log`). The statements "holds only `edit_author.php` … and no `menu.php`" and "the group
  cannot render from this tree" are wrong: `menu.php` registers the group, so `sidebar.php` renders it for any admin
  whose permissions carry a `backup` key. The group's functions and their disposition: #101. Original text kept.**]**

---

## 101. ADMIN CONTROLS — FUNCTIONALLY COMPLETE LOCALLY (2026-11-06) — WITH OWNER-APPROVED LOW DEFERRALS; ADMINCP BACKUP GROUP DISPOSITIONED

Explicit owner approval, 2026-11-06. Documentation only — no application code, test, data, permission, staging,
commit, push, deployment or production change. R1 stays deferred. Supersedes #100's pending condition.

- **AdminCP backup sidebar group «النسخ الإحتياطي» — evidence (local, read-only).** Source: `7amlat/admincp/backup/`
  — `menu.php` (2025-01-24), `index.php` (2026-01-07), `edit_author.php` (2025-01-24), `error_log`, `mysql_errors.log`;
  untracked, matched by the bare `backup/` rule at `7amlat/.gitignore:125` (hence absent from `legacy-project`; OLD
  `old-project` has no `admincp/`). `menu.php`: label «النسخ الإحتياطي», one link «المتصلون الآن» →
  `backup/index.php?op=activesessions`, and `$authorization['backup']` = `allsite`, `backupkhotab`, `backupkhotabmirror`,
  `backuptelawah`, `backupanasheed`, `backupanasheedmirror`. `sidebar.php` shows the group to admins whose permission
  blob holds a `backup` key; `authors/edit_author.php` builds its permission checkboxes from every `menu.php`.
  Production reach on record: `mysql_errors.log` (2019-01-20, `https://way2allah.com/new/admincp/backup/index.php?op=activesessions`,
  user "Admin"; the error was vBulletin's) and #41 (group visible in production's sidebar). Production's current copy
  was not compared (no production access).
- **Four functions, final disposition (owner):**

  | # | Function | Legacy source | Laravel | Disposition |
  |---|---|---|---|---|
  | 1 | Add admin (vBulletin member id → `nuke_authors`, hard-coded password `'way2allah'`) | `backup/index.php` `op=addstuff` (`:93-140`; not in the menu) | `AdminStaffController::store()` (tasks 5.6 / 5.7; random hashed password) | **COVERED** |
  | 2 | Edit admin permissions (`nuke_authors.permissions`) | `backup/edit_author.php` (older copy of `authors/edit_author.php`) | `PermissionController` (task 5.3, super-admin only) | **COVERED** |
  | 3 | Register the six `backup.*` permissions | `backup/menu.php` `$authorization['backup']` | `AdminPermissionSeeder`, `PermissionLabels`, checked by `BackupApiAuthenticator` (legacy `allsite` / `backupallsite` mismatch reproduced as the documented dead flag) | **COVERED** |
  | 4 | Read-only Khotab booking monitor | `backup/index.php` default branch (reached by «المتصلون الآن», which has no `activesessions` branch) | none | **BACKUP BOOKING MONITOR — OWNER-APPROVED DEFERRED LOW (2026-11-06)** |

- **Backup Booking Monitor — preserved for possible future work.** Not implemented in Laravel; not dead, retired or
  unrecoverable — a known, intentionally deferred LOW functional difference. Legacy route: `admincp/backup/index.php`
  (`?op=activesessions` from the menu; any op other than `addstuff`). Query:
  `SELECT booking.*, item.title, authors.aid FROM nuke_backup_booking AS booking LEFT JOIN nuke_authors AS authors ON
  authors.uid = booking.uid LEFT JOIN nuke_islamic_khotab AS item ON booking.catid = item.id WHERE booking.catid = '1'
  ORDER BY booking.id ASC` (`$dbtables[1..5]` maps catid → khotab / islamic_mirror / telawah / anasheed /
  anasheed_mirror; only `$mode = 1` is used; the item join compares `booking.catid` to `item.id`, as written). Output
  per row: counter, `$book->thumb` image, `aid` linked to `edit_author.php?op=liststuff&id=<booking.id>` (a booking id
  passed as an author id — defect), `mobile`, `email` (mailto), `tinydatetime(createtime)`,
  `CoolShortDate(updatetime)`; headers are the staff list's (الاسم / رقم الهاتف / البريد الألكتروني / الفيسبوك) and do
  not match the cells. Read-only — no writes. Writers of `nuke_backup_booking` are the root `backup.php` API (ported,
  `BackupApiController`) and item deletes (`DeleteAnasheedItemAction`, `DeleteTelawahItemAction`); Laravel model
  `App\Domain\Content\Models\BackupBooking`. Local data: 14,207 rows (catid 1: 4,449, latest 2023-12-13). Wave 5
  record: `AdminStaffController` docblock — booking JOIN "deliberately not surfaced" pending Business Confirmation #7.
- **Root `backup.php` is separate:** a machine-to-machine content-backup / booking API for an external tool
  (Confirmation #7, task 6.8), ported as `POST /backup.php` → `BackupApiController` (#33, #84). It is not the AdminCP
  sidebar group and is not affected by this entry.
- **ADMIN CONTROLS — FUNCTIONALLY COMPLETE LOCALLY (2026-11-06) — WITH OWNER-APPROVED LOW DEFERRALS.** Evidence:
  AdminCP core functionally complete (#38 `ADMIN_MIGRATION_COMPLETE`, #22–#42); backend administration Phases 0–7
  owner-accepted (#59, plan §30); OLD public admin controls dispositioned module by module (#60–#82; plan §31–§53);
  backup group functions accounted for (this entry); no known HIGH or MEDIUM Admin functional gap. Closure inventory:
  the 2026-11-06 readiness inventory and #100; register plan §54; retirement checklist plan §55; status plan §56.
  **Not claimed:** that every legacy feature is implemented (owner retirements, intentional non-migrations and LOW
  deferrals stand), production readiness, production verification or deployment, OLD Admin retirement, whole-migration
  completion.
- **Owner-approved LOW deferrals (unchanged plus this one):** Backup Booking Monitor (this entry); admin visibility of
  hidden content on search / Cat / Satellite — V2 / V3 / V4 (#93, #97). Owner retirements unchanged: social share
  queues, Anasheed link status, CD create on group pages (plan §28.1). Intentional non-migrations unchanged (#59, #63,
  #60).
- **AdminCP visual parity:** unchanged — `SUBJECT_TO_OWNER_REVIEW` (#42); no final visual approval.
- **Super-admin sidebar:** approved (#100), unchanged.
- **Production / cutover:** all OPEN — R1 (deferred), R8–R16, R20, R21, R14, DoD 2 / 3 / 5a / 5b / 6 / 9, plan §55.
- **Status:** **ADMIN CONTROLS — FUNCTIONALLY COMPLETE LOCALLY (2026-11-06) — WITH OWNER-APPROVED LOW DEFERRALS.**
  Production deployment NOT STARTED.

---

## 102. VISITOR PARITY BASELINE — PRIMARY: LEGACY / GEN 2 · SUPPLEMENTARY: OLD · TARGET: LARAVEL; KHOTAB COMMENT-PAGE URL COMPATIBILITY (2026-11-06)

Explicit owner decision, 2026-11-06, following the read-only visitor route-coverage reconciliation of the same day
(OLD `old-project/.htaccess` vs Gen 2 `7amlat/.htaccess` vs Laravel's registered routes). Admin Controls (#101) not
reopened; owner-closed visitor scopes and approved deferrals unchanged. No database, production, staging, commit,
push or deployment change. R1 stays deferred.

- **Baseline (owner):**
  - **PRIMARY — LEGACY / GEN 2** (`7amlat`, the current main website `way2allah.com`): the reference for
    visitor-facing functional behaviour.
  - **SUPPLEMENTARY — OLD** (`old-project`, `old.way2allah.com`): the reference for historical functionality,
    historical public URLs, missing or changed features and previously unresolved behaviour.
  - **TARGET — LARAVEL:** preserves the approved visitor functionality and the required backward-compatible URLs.
  - OLD-only functionality is **not** automatically DEAD, RETIRED, LOW or out of scope, and is **not** automatically
    reimplemented because it exists in OLD — each unresolved difference needs evidence or an explicit owner decision.
- **Distinctions used from now on:** *functional parity* (the visitor can do the same thing); *historical URL
  compatibility* (an old URL keeps working, by route or redirect); *intentional website evolution* (Gen 2 changed or
  replaced OLD behaviour — evidence required); *owner-approved retirement*; *owner-approved LOW deferral*;
  *unresolved OLD-only functionality* (present in OLD, absent from Gen 2 and Laravel, no established reason).
- **Route reconciliation facts (2026-11-06, read-only):** OLD `.htaccess` 233 active RewriteRules (213 unique patterns;
  200 target `new_modules.php`, which exists in OLD); Gen 2 `.htaccess` 217 active (208 unique; 124 target
  `new_modules.php`, absent from Gen 2 — IF-026's "130" is 124 in the current file). Production `way2allah.com`
  serves Gen 2 and 404s `new_modules.php` / `modules.php` targets (#44); `old.way2allah.com` serves OLD (#61). Laravel
  leaves 86 of Gen 2's 208 patterns unmatched (72 already target the missing dispatcher on Gen 2; 14 live Gen 2
  targets — replaced endpoints, documented-dead radio ops, retired chat, the `modules.php` redirect and the comment-page
  URL below) and 102 of OLD's 213. Gen 2-based "SOURCE UNRECOVERABLE" conclusions (IF-026 premise, IF-045 RSS, IF-053
  `category-group`, handoff §8's Satellite / Feedback / FAQ / flashxml / `recite-play` list) do not hold for OLD, whose
  modules exist; production 404 evidence for `way2allah.com` still stands. Cat / Satellite priority routes:
  `category-group-*` OLD-only (Gen 2 rule targets the missing dispatcher; Gen 2's only generator is malformed and never
  called; Laravel's `khotab-group-*` shows the whole group); `series_list-*` an AJAX sort dropped in Gen 2 (evolution);
  `var-channel*` OLD-only, direct-URL only; `channel(s)-ramadan*` a 2012-only Ramadan view, Gen 2 / Laravel serve
  `ramadan.htm` and `pages/ramadan*.php`. **None of these is classified here** — recorded as unresolved below.
- **[Clarification 2026-11-07, #111: the `series_list` AJAX sort was indeed dropped in Gen 2, but Gen 2's channel page
  does load DataTables (`scripts/khotab_tables.js`) configured for client-side sorting, search and paging of its
  tables; the tables lack `<thead>`, so initialization most likely fails (source inference only, not browser-verified).
  The 2026-11-06 reconciliation report's remark that neither Gen 2 nor Laravel offers column sorting on the channel page
  is corrected accordingly. Original text kept.]**
- **Khotab comment-page URL compatibility — implemented (owner-approved fix):** Gen 2 serves and generates
  `khotab-item-{id}-page-{n}.htm` (`.htaccess:216` → `khotab/item.php`; generator `khotab/item.php:413`); Laravel
  served the same pages only at `khotab-item-{id}.htm?page={n}`. New route `khotab.item.legacy-comment-page`
  (`routes/content.php`, `whereNumber(['khotab', 'page'])`) → invokable
  `KhotabItemLegacyCommentPageRedirectController` → **301** to `khotab.item.show` with `?page={n}`; page 0 (Gen 2's
  first page, `item.php:88`) → the canonical URL without a query. Permanent because the mapping is one-to-one: 1-based
  pages, 10 comments a page, `view = 1`, newest first on both sides (Gen 2 `item.php:87-89, :371-372`,
  `classes/ps_pagination.php:99-103`; Laravel `KhotabItemController` `paginate(10, …, 'page')`). `KhotabItemController`
  and its pagination are unchanged; no second rendering path; visibility stays with the canonical route.
- **Tests:** new `tests/Feature/Content/KhotabItemLegacyCommentPageRedirectTest.php` (18): 301 + exact target for pages
  2 / 1 / large ids / page 0; the followed redirect shows Gen 2's page-2 comments (ids 15…6 of 25); canonical URL
  unchanged; invalid ids / pages / missing page / extra segments 404; a hidden item still 404s after the redirect;
  neighbouring Khotab routes (`show`, `download-pdf`, `download`, `download-mirror`, `banner-code`) still match their
  own handlers. Related Khotab item / public-parity set 92 / 92; Architecture 5 / 5; PHPStan 0 errors; Pint PASS on
  the changed files; full suite 2,443 — 2,442 passed, 0 failed, 1 skipped.
- **Unresolved (recorded, not classified, not implemented):** (1) OLD-only visitor functionality — e.g.
  `category-group-*`, `var-channel*`, `channel(s)-ramadan*`, `series_list-*`, Feedback ×5, FAQ ×2, RSS ×15,
  `flashxml` ×2, Locations group / series, `help-*`, `hedaya_table` ×3, `home-file-*`, `questionnaire.htm`,
  `dumped-lectures-{author}`, Khotab Play / Thumbs / PlayMirror / Banner, `recite-play`, `fatawa-play` / `-brokenlink` /
  `-friend`, `download-album`, `nashr-hedayah`, W2ACD comments / send-to-friend, `stats`, `noor`, `alQuran`; (2) the
  Business Confirmation #13 route inventory — a disposition for each Gen 2 pattern without a Laravel route; (3) the
  `modules.php` redirect evidence conflict (Gen 2 `.htaccess:188` 301 → `khotab-audio.htm` vs #44's production 404s);
  (4) homepage slider classification (#98); (5) documentation inconsistencies — IF-026 count and premise, IF-045,
  IF-053, handoff §3 / §8 rows, `routes/engagement.php:15, :33` comments, `legacy-vs-laravel-coverage.md` (Gen 2 basis).
- **Status:** **VISITOR PARITY BASELINE — DECIDED (2026-11-06); KHOTAB COMMENT-PAGE URL COMPATIBILITY — IMPLEMENTED
- **[Correction 2026-11-06, #103: the route counts above are superseded — a corrected parse (escaped spaces inside
  patterns) gives Gen 2 **210** unique patterns (not 208), and after this entry's redirect **82** unmatched (not 86);
  Laravel 487 routes. The "`modules.php` redirect evidence conflict" is resolved — the three Gen 2 `modules.php` rules
  are query-gated 301s (`.htaccess:187-188, 193-194, 265-266`) and #44 tested other query strings. The comment-page
  redirect was manually verified by the owner (`khotab-item-298784-page-2.htm` → `khotab-item-298784.htm?page=2`).
  Original text kept.]**
  LOCALLY — AWAITING OWNER VERIFICATION.** Visitor parity is **not** declared complete. Production deployment NOT
  STARTED.

---

## 103. BUSINESS CONFIRMATION #13 — OWNER-APPROVED CLARIFICATION; 82-PATTERN URL RECONCILIATION RECORDED (2026-11-06)

Owner-approved clarification, 2026-11-06. Documentation only — no application code, test, data, redirect, staging,
commit, push, deployment or production change. R1 stays deferred. **Business Confirmation #13 is clarified, not
deleted or replaced:** its original wording — «YES, preserve all 217 patterns. Does not imply 217 independent Laravel
controllers/routes — routing pattern reuse remains permitted where externally-visible behavior is preserved»
(`PROJECT-HANDOFF.md` §4 #13; `business-confirmation-report.md` Confirmation #13, Choice A; #13's sub-decision in this
log) — stands, read with this clarification. #102's baseline (PRIMARY Legacy / Gen 2, SUPPLEMENTARY OLD, TARGET
Laravel) stays authoritative.

- **Clarification (owner):** preserving the 217 legacy RewriteRules means preserving **approved, functional
  visitor-facing URL behaviour**. It does **not** require automatically restoring every historical URL that was
  already non-functional in Gen 2.
- **URL compatibility policy:** (A) functional Gen 2 URLs — preserve the approved behaviour, with URL compatibility or
  an explicitly approved equivalent where required; (B) non-functional Gen 2 URLs — not automatically reimplemented, but
  a missing local handler does not establish a production 404: verified production evidence and local inference are
  kept apart; (C) OLD-only functionality — not automatically retired, dead, LOW or out of scope; kept open for bounded
  family-level review; (D) existing owner closures, retirements and LOW deferrals preserved, not reopened without
  concrete new evidence.
- **Corrected counts (2026-11-06 reconciliation, read-only):** Gen 2 `7amlat/.htaccess` — **217** active RewriteRules,
  **210** unique URL patterns (7 repeats: `^modules\.php$` ×3, `download-album` ×3, `khotab-pdf` / `khotab-mirror` /
  `play-mirror` ×2), **124** rules → `new_modules.php`, **20** → `modules.php` (neither file exists in Gen 2). Laravel:
  **487** registered routes. Unmatched: **82** unique patterns. Superseded: 208 unique / 86 unmatched (#102 — truncated
  parse; −1 by #102's redirect, −3 Mobile slug patterns that match once parsed correctly) and IF-026's "130".
- **The 82, by evidence class:** A LIVE_URL_MISSING 4 — the three query-gated `modules.php` 301s (one pattern,
  `.htaccess:188 / 194 / 266`) and three radio operation URLs (`:343-345`, Gen 2 renders the radio page and ignores the
  operation); B FUNCTIONALLY_REPLACED 4 — `add-anasheed-comment-*`, `add-khotab-comment-*`, `send-friend-khotab-*`,
  `get-mada-player.htm` (`routes/content.php:100-119, :358`); **LOCAL_GEN2_HANDLER_MISSING 59** and
  **PRODUCTION_404_VERIFIED 5** (together the former "ALREADY_BROKEN_IN_GEN2" 64) — production 404 is recorded only for
  `way2allah.rss`, `khotab-audio_news.rss`, `khotab-video-1.rss` (one instance of `khotab-video-{id}.rss`),
  `var-news.rss`, `more-fatawa.rss` (IF-045); the other 59 rest on local Gen 2 source only (no handler file) and are
  **not** generalised from #44's sample; neither class is a retirement; OWNER_APPROVED_RETIRED 8 — `cds-news.htm`
  (#74, #91), `chat_*`, `2nd_chat_room`, `3rd_chat_room`, `w2a_chat` (Business Confirmation #4, #14, #39),
  `mobile-toprated-*`, `mobile-mostpopular-*`, `mobile-outside-*` (plan §26.10); OWNER_APPROVED_DEFERRED 1 —
  `cds-series-{id}.grx` (W1 LOW, #91 / #92); COVERED_BY_LARAVEL 1 — `mobile-seldate-{ts}` (sampling artifact);
  UNKNOWN 0. Total 82. Full pattern-by-pattern inventory: the 2026-11-06 reconciliation report.
- **Kept OPEN — separate owner disposition required (no fix or retirement approved here):** (A) the three query-gated
  `modules.php` redirects; (B) the three radio operation URLs; (C) the four functionally replaced form / AJAX URLs
  (whether URL compatibility is required); (D) OLD-only visitor functionality families — Feedback (5), RSS (15),
  flashxml (2), Locations index / group / series (3), Cat `category-group` (1), Satellite ramadan / `series_list` /
  `var-channel` (6), per-author dumps (1), Khotab play / thumb / playmirror / banner / `play-mirror` (5), W2ACD comment
  page / download / mirror / comments / friend (7), Fatwa play / brokenlink / friend (3), Telawah `recite-play` (1),
  live admin URL (1), login / logout (2), album download / `nashr-hedayah` (2), FAQ (2), poll-less survey comment URLs
  (2, #59), help (1), hedaya_table (3), home-file (1), questionnaire (1); (E) the homepage slider (#98); (F) remaining
  documentation inconsistencies (below).
- **Documentation reconciled in this entry (dated notes, history kept):** #102's counts; IF-026, IF-045, IF-053
  (`implementation-findings.md`); `PROJECT-HANDOFF.md` §2 (`questionnaire.htm`, Locations group / series), §3
  (`cds-series .grx`, `fatawa-category`, RSS, `fatawa` / `advanced-search`, root `backup.php`), §4 #13, §8;
  `reviews/legacy-vs-laravel-coverage.md` (Gen 2 basis); plan §54.
- **Not reconciled — needs approval:** `routes/engagement.php:15, :33` carry the stale "confirmed-absent
  `new_modules.php`" wording in code comments; editing them is an application-file change outside this
  documentation-only task.
- **Status:** **BUSINESS CONFIRMATION #13 — CLARIFIED (OWNER-APPROVED, 2026-11-06).** #102's Khotab comment-page
  redirect — owner manually verified. Visitor parity is **not** declared complete.
- **[Reconciliation 2026-11-07, #122: the (D) family `hedaya_table (3)` above is now classified and closed locally —
  `more-hedaya.htm` already owner-retired (Business Confirmation #4, #14, IF-033); `update-hedaya-list.htm` and
  `hedaya_cache_files.htm` OLD-only retired maintenance endpoints; no route or redirect. Original text kept.]**
- **[Reconciliation 2026-11-07, #123: the (D) family `home-file (1)` above is now classified and closed locally —
  `home-file-{id}.htm` OLD-only, retired before Gen 2; historical URL compatibility only; no route or redirect. The
  homepage slider (E, #98) is unaffected and stays OPEN. Original text kept.]**
- **[Reconciliation 2026-11-07, #124: item (E) above — the homepage slider (#98) — is now investigated and closed
  locally as FUNCTIONALLY EQUIVALENT to Gen 2. Original text kept.]**
- **[Reconciliation 2026-11-07, #125: the (D) family `questionnaire (1)` above is now classified and closed locally —
  `questionnaire.htm` (OLD internal staff HR / training-needs survey) OLD-only, retired before Gen 2; historical URL
  compatibility only; no route or redirect. Original text kept.]**
- **[Reconciliation 2026-11-07, #126: the (D) family `login / logout (2)` above is now classified and closed locally —
  `login.htm` / `logout.htm` (historical PHP-Nuke `Your_Account` URLs) historical URL compatibility only; no route or
  redirect. Business Confirmation #5 is not closed or modified; the separate Gen 2 `vb5.php` bridge stays an OPEN
  dependency (plan register R25). Original text kept.]**
- **[Reconciliation 2026-11-08, #127: the (D) family `flashxml (2)` above is now classified and closed locally —
  `var-news.xml` / `khotab-news.xml` historical URL compatibility only; no route, redirect or XML endpoint. Original
  text kept.]**
- **[Reconciliation 2026-11-08, #128: the (D) group `album download / nashr-hedayah (2)` above pairs two unrelated
  workflows — the album ZIP (`download-album-{id}.htm`) was closed under #107; `nashr-hedayah.htm` is a separate OLD
  client-side Picasa album browser (`gal2`), now closed locally under #128 as historical URL compatibility only.
  Original inventory kept.]**
- **[Reconciliation 2026-11-08, #129: the (D) label `live admin URL (1)` above is misleading — `live-channel-admin.htm`
  was not an administrative endpoint; it rendered the public OLD live-TV page with a disabled (commented-out) preview
  flag. Now closed locally under #129 as historical URL compatibility only. With #129, every currently identified
  historical URL family in this inventory has an owner disposition — visitor parity is NOT thereby declared closed.
  Original inventory kept.]**
- **[Reconciliation 2026-11-08, #130–#132: #102's unresolved list named three OLD-only families — `stats`, `noor`,
  `alQuran` — that the (D) inventory above omitted. They are now investigated and owner-decided individually:
  `stats.htm` / `stats-{n}.htm` (#130), `noor.htm` (#131) and `alQuran.htm` (#132) — each HISTORICAL URL
  COMPATIBILITY ONLY, CLOSED LOCALLY; none has a Gen 2 rule or handler, so no Gen 2 visitor parity gap remains from
  these URLs. The #129 note above ("every currently identified historical URL family ... has an owner disposition")
  was true of this (D) list only; it now holds for all identified families. Original inventory kept.]**

---

## 104. VISITOR URL COMPATIBILITY — OWNER-APPROVED A–C DISPOSITION — IMPLEMENTED AND TESTED LOCALLY (2026-11-06)

Owner disposition of the three open URL groups from #103, under #102's baseline (PRIMARY Gen 2, SUPPLEMENTARY OLD,
TARGET Laravel) and #103's clarification of Business Confirmation #13. No database, production, staging, commit, push
or deployment change. R1 stays deferred. Admin Controls (#101) and owner-closed scopes not reopened.

- **A — two `modules.php` redirects: IMPLEMENTED AND TESTED LOCALLY.** Gen 2 rules (`7amlat/.htaccess:187-188,
  193-194`) reproduced with their exact matching semantics: the whole raw query string must equal
  `name=Khotab&op=Audio` → **301** `/khotab-audio.htm`, or match
  `^name=Khotab&op=Day&video=0&d=([0-9]*)&m=([0-9]*)&y=([0-9]*)$` → **301** `/khotab-audiodate-{d}-{m}-{y}.htm`
  (empty parts passed through, as Gen 2 did); the query is dropped. Any other `/modules.php` request — other module or
  op, reordered or extra parameters, different case, non-numeric date parts — is a **404**, as on Gen 2 (no
  `modules.php` file). New route `GET /modules.php` (`legacy.modules-php`) → invokable
  `LegacyModulesPhpRedirectController`. Note: Gen 2's own `khotab-audiodate-*` rule (`:192`) targets the missing
  dispatcher, so this redirect now reaches a page Laravel serves (`khotab.day.audio-date`).
- **B — third `modules.php` redirect (`name=w2acd&op=DownSeries&id=`, `.htaccess:265-266`): NOT implemented** — the
  owner-approved LOW deferral of `cds-series .grx` (W1, #91 / #92) is preserved; the URL stays a 404 (tested).
- **C — three radio operation URLs: functional reimplementation DECLINED (owner); GET compatibility IMPLEMENTED AND
  TESTED LOCALLY.** `GET /remove-playlist-item-{spec}.htm` (`[0-9]*-[a-z]*`), `GET /playlist-item-{id}.htm`
  (`[0-9]*`), `GET /save-last-listen.htm` → invokable `RadioLegacyOperationRedirectController` → **301** `/radio.htm`
  (the page Gen 2 rendered there, IF-032). Gen 2 patterns kept, empty parts included. No playlist read or write (no
  query issued — tested). **Exception, recorded honestly:** Gen 2's callers of these URLs are **POST** AJAX
  (`7amlat/scripts/w2a_radio.js:211-212, 258-259, 317-318`, `add_radio.js:6-8`), and Laravel's radio page still loads
  its copy (`resources/views/radio/index.blade.php:56` → `public/scripts/w2a_radio.js:211-212, 258-259, 317-318`).
  POST is **not** redirected or handled (owner rule): those AJAX calls received 404 before and now receive **405**
  (the path exists for GET); the scripts ignore both. Left unresolved for the owner. `public/scripts/add_radio.js:7`
  has the same POST but no view loads it.
- **D — four form / AJAX URLs: FUNCTIONAL REPLACEMENT APPROVED; historical URL compatibility not required** unless
  new evidence establishes a consumer. Read-only consumer check (Laravel `resources`, `public`, `app`, `routes`):
  `add-khotab-comment-*`, `send-friend-khotab-*` — no consumer; the Khotab item page uses
  `POST /khotab-item-{id}/comments` and `/send-friend`. `get-mada-player.htm` — no live consumer: `w2a_play()` is defined
  by `components/content/media-player-script.blade.php:47-70` against `POST /media-player`;
  `public/scripts/w2a_play.js:3` still names the old URL but no view loads it. **Exception — a live consumer:**
  `add-anasheed-comment-{id}.htm` is still called by `public/scripts/anasheed_scripts.js:32` (POST, bound to
  `#send_comment` at `:16`), which `resources/views/anasheed/item.blade.php:25` loads for the comment modal
  (`:107-128`, form without an action). No Laravel route matches that POST (router check: `NotFoundHttpException`);
  the replacement is `POST /var-item-{id}/comments` (`anasheed.item.store-comment`, tested directly by
  `AnasheedItemControllerTest`, not through this script). The Anasheed item page's comment submission therefore
  appears to reach a 404 in Laravel (source evidence; not browser-tested). Not fixed — reported for an owner decision.
- **Tests:** new `tests/Feature/Content/VisitorUrlCompatibilityTest.php` (34): both redirects with targets served by
  Laravel; empty date parts; 12 non-matching `modules.php` variants → 404 (incl. the deferred `w2acd` redirect,
  reordering, case, extra parameter); POST → 405; radio GET ×6 shapes → 301 `/radio.htm` with zero queries; radio POST
  ×3 → 405; non-Gen-2 shapes → 404; existing radio / Khotab routes unchanged. Updated
  `RadioControllerTest` (IF-032 test: 404 → 301 for GET, POST 405). Related set 218 / 218; Architecture 5 / 5; PHPStan
  0 errors; Pint PASS on the changed files; full suite 2,477 — 2,476 passed, 0 failed, 1 skipped. Status: **TESTED
  LOCALLY** — not owner verified.
- **Status:** **VISITOR URL COMPATIBILITY A–C — IMPLEMENTED AND TESTED LOCALLY — AWAITING OWNER VERIFICATION.** Open:
- **[Owner approval 2026-11-06 — Radio POST exception: CLOSED LOCALLY — NO CHANGE REQUIRED (Option A).** The three
  historical radio POST operations (`remove-playlist-item-{id}-{s}.htm`, `playlist-item-{id}.htm`, `save-last-listen.htm`)
  need no implementation or change. Source evidence (no browser run): Laravel's radio page renders no
  `.remove-item-playlist` or `.add-pl-item` control (`resources/views/radio/index.blade.php:146-165`, notes `:38-52`), and
  `save_last_listen()` runs only when `logged_in` is set by a `#w2a_l` element (`public/scripts/w2a_radio.js:313-315,
  :327-333`) that neither Laravel nor Gen 2's live `radio/index.php` renders; Gen 2's live page behaves the same (the
  controls exist only in the unrouted `radio/indexXX.php` path, IF-032); playback does not depend on these requests.
  Source-evidence closure — not browser-verified. No radio JavaScript change, no POST route, no playlist persistence.
  Current state: **GET compatibility — IMPLEMENTED AND TESTED LOCALLY; POST operations — OWNER-APPROVED NO CHANGE —
  CLOSED LOCALLY.** Related: Anasheed comments #105 — OWNER VERIFIED LOCALLY — CLOSED; Anasheed send-to-friend #106 —
  OWNER VERIFIED LOCALLY — CLOSED for the visitor-facing form workflow (production email delivery not verified).**]**
  the radio-script POST behaviour and the Anasheed comment-script consumer (above); OLD-only families; homepage slider;
  `routes/engagement.php` comments. Visitor parity is **not** declared complete.
- **[Owner acceptance 2026-11-08, #133: A (`modules.php` 301s) and C (radio GET 301s) — OWNER-ACCEPTED ON AUTOMATED
  EVIDENCE (`VisitorUrlCompatibilityTest`, `RadioControllerTest`), not a new manual test. B (`w2acd DownSeries`) stays
  deferred LOW (#92). The "awaiting owner verification" wording above is superseded. Original text kept.]**

---

## 105. ANASHEED ITEM COMMENT FORM — FUNCTIONAL GAP CONFIRMED AND FIXED — TESTED LOCALLY (2026-11-06)

Follow-up to #104's consumer check, owner-authorized targeted fix. No database, production, staging, commit, push or
deployment change. R1 stays deferred. Radio, `modules.php`, Khotab comment pagination, OLD-only families, the slider,
OLD / Gen 2 and Admin Controls not touched.

- **Confirmed gap (source and router evidence):** `resources/views/anasheed/item.blade.php:25` loads
  `public/scripts/anasheed_scripts.js`; its `#send_comment` handler (`:16`) posted to the Gen 2 URL
  `add-anasheed-comment-{id}.htm` (`:32`), which no Laravel route matches (router: `NotFoundHttpException`), and sent
  **no CSRF token** while Laravel's CSRF protection applies to the canonical `POST /var-item-{id}/comments`
  (`anasheed.item.store-comment`; only `backup.php` is exempt, `bootstrap/app.php:21-23`). So the visible comment
  form could not save a comment: 404 at the old URL, and the token would have produced 419 at the new one. The
  handler, payload (`user_nickname`, `user_comment`), method (POST), validation codes (`2` / `3`) and success code
  (`1`) already matched `AnasheedItemController::storeComment()`. Existing tests posted to the endpoint directly and
  never through the page's script.
- **Correction (smallest, existing endpoint, controller and response contract unchanged):** the comment form
  (`item.blade.php`) gains `data-comment-url="{{ route('anasheed.item.store-comment', id) }}"` and `@csrf`; the script's
  comment request uses `$("#comments_form").data("comment-url")` and sends the form's `_token`; the script tag carries
  `?v=20261106` so browsers do not keep the old cached copy. Same pattern as the Khotab item page
  (`khotab/item.blade.php:164-168, :414-421`). No legacy compatibility route; `add-anasheed-comment-*` stays unrouted
  (tested). Visual behaviour and success / error messages unchanged.
- **Tests:** new `tests/Feature/Content/AnasheedCommentFormIntegrationTest.php` (8) — the rendered form's endpoint
  equals the canonical route and its token equals the session token, and the page loads the versioned script; the
  script's handler (static check) posts by POST to `data-comment-url` with `_token` and handles `1 / 2 / 3`; with CSRF
  **enforced** (production behaviour, the framework's unit-test skip disabled for the test) the request built from the
  page returns `1` and stores the comment unmoderated (`view = 0`); no / wrong token → 419, nothing stored; missing
  nickname → `2`, missing comment → `3`; the old URL still 404s. Mutation check: without `@csrf` 4 / 8 fail (view
  restored byte-identical). Related Anasheed / comment set 100 / 100; Architecture 5 / 5; PHPStan 0 errors; Pint PASS;
  full suite 2,485 — 2,484 passed, 0 failed, 1 skipped.
- **Not verified:** no browser run — the JavaScript was checked statically, not executed. **Manual verification
  remaining:** on a visible Anasheed item (e.g. `/var-item-{id}.htm`), open «اضافة تعليق», submit a nickname and
  comment → the success message «شكرا لك ، تم اضافة التعليق بنجاح…»; empty fields show the inline errors. Note: an
  unhandled server error (e.g. 419 after a stale page) leaves the button at «جاري الارسال», as before and as on Khotab.
- **Found, not fixed (out of scope):** the same script's send-to-friend request (`anasheed_scripts.js:49-51`, POST to
  the routed `send-friend-anasheed-{id}.htm`) also sends **no CSRF token**, so in production it would be refused with
  419 — reported for an owner decision.
- **Status:** **ANASHEED ITEM COMMENT FORM — FIXED — TESTED LOCALLY** (not owner verified). Visitor parity is **not**
- **[Owner verification 2026-11-06: ANASHEED COMMENT FORM — OWNER VERIFIED LOCALLY — CLOSED.** The owner manually
  tested `http://127.0.0.1:8123/var-item-9877.htm`: (1) valid name + valid comment → success message; (2) empty name +
  valid comment → the correct validation message; (3) valid name + empty comment → the correct validation message. All
  three PASSED. Not reopened by #106 (which bumps the script version only).**]**
  declared complete.

---

## 106. ANASHEED SEND-TO-FRIEND — FRONTEND CSRF INTEGRATION FIXED — TESTED LOCALLY (2026-11-06)

Follow-up to #105's finding, owner-authorized targeted fix. No database, production, staging, commit, push or
deployment change; no real email (local mailer `log`; tests use `Mail::fake()`). R1 stays deferred. Radio,
`modules.php`, Khotab, OLD / Gen 2, other OLD-only families, the slider, Admin Controls and Anasheed comments (#105,
owner closed) not touched.

- **Already implemented in Laravel (unchanged):** `POST /send-friend-anasheed-{id}.htm` (`anasheed.item.send-to-friend`,
  `routes/content.php:373`, `web` middleware only) → `AnasheedItemController::sendToFriend()` (`:215-236`): your /
  friend name required, both emails `FILTER_VALIDATE_EMAIL`, else `'2'`; otherwise `AnasheedFriendMail` to the friend
  (From = the visitor's name / email, subject «{title} - موقع الطريق الى الله») and `'1'`. Visitors reach it from the
  item page's share button (`components/content/media-details-card.blade.php:90`) and the `#sendFriendModal` form.
- **Gen 2 (primary baseline) — same workflow:** `.htaccess:102` → `anasheed/item.php?op=send_friend` →
  `anasheed_send_friend()` (`anasheed/functions.php:647-677`): same four fields and validation (`echo 2`), PHP `mail()`
  via `shams_mail_no_spam()` (`functions.php:942+`, From / Reply-To = the visitor), `echo 1` on success, `-1` if
  sending fails. No rate limiting or anti-abuse in Gen 2.
- **Root cause (confirmed by source and test):** the page script (`public/scripts/anasheed_scripts.js`,
  `anasheed_send_friend()`) posted the right fields by POST to the existing route (relative `send-friend-anasheed-{id}.htm`),
  but sent **no CSRF token**; Laravel's CSRF protection applies (only `backup.php` is exempt), so a real submission
  is refused with **419** and nothing is sent. Response codes `1` / `2` already match the script; Laravel never answers
  `-1` (a mail failure raises a server error instead).
- **Correction (smallest, existing route / controller / validation / response unchanged):** `#sendFriend_form`
  (`resources/views/anasheed/item.blade.php`) gains `data-send-url="{{ route('anasheed.item.send-to-friend', id) }}"`
  and `@csrf`; the script posts to `$("#sendFriend_form").data("send-url")` with that form's `_token`; the script tag's
  version moves to `?v=20261106-2` (the owner's browser already holds `?v=20261106`). No new route, controller, CSRF
  exemption or email logic.
- **Tests:** new `tests/Feature/Content/AnasheedSendToFriendIntegrationTest.php` (10): the rendered form's endpoint =
  the route, its token = the session token, the page loads `?v=20261106-2` and shows the share button; the script
  (static check) posts by POST to `data-send-url` with `_token` and handles `1 / -1 / 2`; with CSRF **enforced** the
  page-built request returns `1` and exactly one `AnasheedFriendMail` to the friend (faked); no / wrong token → 419, no
  mail; four invalid payloads → `2`, no mail; the comment form on the same page still works and sends no mail.
  Mutation check: without the form's `@csrf` 6 / 10 fail (view restored byte-identical). Related Anasheed / comment set
  110 / 110; Architecture 5 / 5; PHPStan 0 errors; Pint PASS; full suite 2,495 — 2,494 passed, 0 failed, 1 skipped.
- **Not verified:** no browser run — JavaScript checked statically. **Manual verification remaining:** on a visible
  Anasheed item, open «ارسل لصديق», submit valid names and emails → «شكرا لك ، تم ارسال المادة الى صديقك بنجاح»
  (locally the mail goes to the `log` mailer, not out); invalid input → the inline errors / «يجب عليك اكمال البيانات
  المطلوبة».
- **Open limitations — owner decision, not changed here:** (1) no rate limiting or anti-abuse — any visitor can make
  the site send mail to any address (as in Gen 2); (2) the mail's From is the visitor-supplied address (Gen 2 behaviour,
  kept in G-11-02) — likely to fail SPF / DMARC on a real mail server; (3) a mail failure gives a server error the
  script does not handle (button stays «جاري الارسال»), where Gen 2 answered `-1`.
- **Status:** **ANASHEED SEND-TO-FRIEND — FIXED — TESTED LOCALLY** (not owner verified). Visitor parity is **not**
- **[Owner verification 2026-11-06: ANASHEED SEND-TO-FRIEND — OWNER VERIFIED LOCALLY — CLOSED for the visitor-facing
  form workflow.** The owner manually tested Send-to-Friend on the local Laravel site: (1) a valid submission
  succeeded; (2) the success message appeared; (3) invalid / incomplete input showed the validation. **Limits of this
  verification:** the local mailer is `log` — actual email delivery was **not** verified, and the production mail
  configuration was **not** tested; no end-to-end delivery is claimed. **Unresolved pre-production concerns — not
  owner-approved deferrals or retirements:** (a) no rate limiting / anti-abuse; (b) the visitor-controlled email From
  address; (c) no handling of a mail failure (server error, button stays «جاري الارسال»).**]**
  declared complete.

- **[#106 hardening 2026-11-08 (owner-approved policy) — IMPLEMENTED AND TESTED LOCALLY — PRODUCTION MAIL VERIFICATION PENDING.**
  Scope: the three send-to-friend endpoints — `POST /khotab-item-{id}/send-friend`, `POST /send-friend-anasheed-{id}.htm`,
  `POST /fatawa-friend-sendemail-{id}.htm` (routes, names, field names and success responses unchanged).
  - **Rate limits (shared by all three):** at most 3 sends per client IP in any rolling 10 minutes and 10 per normalised
    recipient in any rolling 24 hours; refused requests get HTTP 429 with a neutral message (no recipient shown); no mail is
    dispatched. One service, `App\Domain\Content\Services\SendToFriend\SendToFriendRateLimiter`: per-subject timestamp
    logs in the limiter cache store (`cache.limiter`, else the default), both subjects locked in a fixed order (cache locks)
    for the check-and-record — a true rolling window, and a refusal by one limit never consumes the other. Keys are an HMAC
    (app key) of the IP / address, never the raw value. Recipient normalisation: trim, lower-case the domain only (local part
    kept). Client IP = `$request->ip()`; no proxies are trusted, so forwarding headers are ignored.
  - **Accounting:** CSRF failures (419) and validation failures consume nothing; an attempt is recorded once, after
    validation and the item lookup and before dispatch; a mail-transport failure does not refund it.
  - **Validation:** names <= 100, email addresses <= 254 characters on all three, added to the existing rules (Khotab /
    Anasheed still answer `2`; Fatwa keeps its 2-character minimum and redirects with errors).
  - **Sender:** From is always `info@way2allah.com`; the visitor's validated email and name go into Reply-To (shared
    `Mail\Concerns\SendToFriendMessage`); extra posted fields are ignored and CR/LF in names cannot add headers.
  - **Links (N3, these three mails only):** absolute links use `APP_URL`, not the request Host / scheme. Other mail paths
    were not reviewed — N3 is not closed overall.
  - **Mail failure:** only mail-transport exceptions are caught (`SendToFriendMailer`) — Khotab / Anasheed answer legacy
    `-1`, Fatwa a 503 page; never a success. The log line holds module, item id and exception class only. Both page
    scripts now show a message for `-1` (Khotab) and for 429 / other HTTP errors (Khotab, Anasheed — script `?v=20261108`).
  - **Tests:** `tests/Feature/Content/SendToFriendSecurityTest.php` (46, all three modules; `Mail::fake()` or the in-memory
    `array` transport, no real mail; frozen time for windows); updated: two From assertions (Khotab, Anasheed) and the
    Anasheed script-version assertion, made obsolete by the approved policy. Mutations: limiter disabled / links back on
    `url()` → 13 / 46 fail. Send-to-friend set 193 / 193; full suite 2,651 — 2,650 passed, 1 skipped; PHPStan 0; Pint PASS.
  - **Production dependencies (OPEN):** a cache store whose locks are shared by every worker (database, Redis or
    memcached; the default `file` store is atomic only on one host); correct `APP_URL`; trusted-proxy configuration if a
    reverse proxy / CDN fronts the site (otherwise every visitor shares the proxy's IP quota); the production mail
    transport and SPF / DKIM / DMARC for `info@way2allah.com`; one production mail smoke test.]**

---

## 107. GALLERY FULL-ALBUM ZIP DOWNLOAD — RESTORED — IMPLEMENTED AND TESTED LOCALLY, AWAITING OWNER VERIFICATION (2026-11-06)

Owner-approved implementation (A), following the read-only album-download investigation of the same day; first
OLD-only family resolved under #102 / #103. No database schema change, no data change, no OLD-server dependency,
no production access or production ZIP, no staging, commit, push or deployment. R1 stays deferred.

- **Why restored:** Laravel and Gen 2 both showed the «حفظ الألبوم» / «تحميل الألبوم بالكامل (ZIP)» buttons for
  `is_compressed = 1` albums (28 of 84 locally, 1,335 images) while the button URL `download-album-{id}.htm` had no
  working handler (Gen 2 → missing `new_modules.php`; Laravel → no route, IF-027); a visible control did nothing.
- **Differences from OLD:** OLD's admin pre-built `{docroot}/zipped_albums/{id}.zip` (`new_album/admin/index.php:451-490`)
  and the visitor handlers only echoed a URL (OLD → `way2allah.com/zipped_albums/`, Gen 2 → `old.way2allah.com/…`).
  Laravel builds on demand, keeps its own private cache, and never reads OLD's directory or server. OLD flattened
  entries to bare file names (same-named files overwrote each other); Laravel prefixes the display position
  (`001-…`) and sanitises names. `is_compressed = 1` keeps its meaning as the availability flag (no schema change);
  OLD's admin compress / delete-compressed actions are not involved.
- **Endpoint (`routes/content.php`, `gallery.download-album`):** `GET /download-album-{album}.htm` (numeric) →
  `GalleryController::downloadAlbum()` → `AlbumZipService::archiveFor()` → `BinaryFileResponse` download
  (`Content-Type: application/zip`, `attachment; filename=album-{id}.zip`, streamed from disk). Album missing or
  `is_compressed ≠ 1` → 404; no valid image left or over the limits → 404 (generic, no path disclosed); another build
  of the album still running after the wait → 503 with `Retry-After: 30`. **Buttons (owner-approved):** both handlers
  (`gallery/index.blade.php`, `gallery/show.blade.php`) now navigate directly (`window.location.href =
  '/download-album-{id}.htm'`) instead of AJAX-then-redirect, which expected a URL body; button markup unchanged.
- **ZIP generation (`app/Domain/Content/Services/AlbumZipService.php`, ZipArchive):** the album's
  `nuke_albums_images` rows in display order (`order`, then `image_id`); each `url` must resolve (realpath) to a
  readable file **inside** `public/` with an image extension (jpg / jpeg / png / gif / webp / bmp) — traversal,
  absolute paths, outside files and non-images are skipped and logged (`Log::warning`, ids only); entry names flat,
  sanitised, position-prefixed; entries stored uncompressed (images are already compressed); ZipArchive reads files at
  close — no image is loaded into PHP memory; originals untouched; an album with no valid image yields no ZIP.
- **Cache lifecycle:** private `local` disk, `storage/app/private/gallery-zips/album-{id}-{fingerprint}.zip`;
  fingerprint = SHA-256 (v1) of each eligible entry's id, url, order, file size and mtime — a deleted, replaced or
  reordered image changes the name and the next request rebuilds; an existing archive is served without a rebuild.
  Builds run under a per-album cache lock (`gallery-album-zip:{id}`, 300 s, waiting up to 30 s), are written to a
  temporary file in the same directory and renamed into place (atomic), and a failed build deletes its temporary file.
  After a build, other archives of the album are deleted (at most one per album); a refused album (no valid image /
  over the limits) also has its archives removed. Limits: 500 images, 512 MiB of images per album.
- **Storage / deployment prerequisites:** `ext-zip` is now required (`composer.json` `require`; `composer.lock`
  content-hash and platform updated only) — **production PHP must have the zip extension** (locally it was missing until
  the owner installed `php8.3-zip` on 2026-11-06; production not checked); `storage/app/private/gallery-zips` must be
  writable; full cache today ≈ 154 MB (largest album ≈ 39.5 MB); the cache lock uses the configured cache store (`file`
  locally).
- **Operational limitations:** the first download of an album builds the archive inside the request (largest today
  ≈ 40 MB, uncompressed store); an album whose `is_compressed` is set to 0 keeps its last archive until it is next
  refused or rebuilt (bounded, one per album, never served); there is no rate limiting on the endpoint (it serves a
  cached static file after the first build); images missing from `public/` (e.g. on a server without the media
  library) are skipped.
- **Tests:** new `tests/Feature/Content/GalleryAlbumZipTest.php` (19, isolated temp media root, `Storage::fake`,
  in-memory DB): eligible download (headers, order, names, exact contents); not-compressed / missing / non-numeric → 404,
  nothing built; missing images skipped and logged, none left → 404 and no ZIP; unsafe paths (traversal, absolute,
  `.php`) excluded; name sanitising and no collisions; image-count and size limits; cache hit (same file, not rebuilt);
  invalidation by reorder / delete / file replacement (new archive, old removed); concurrent build protection (lock held
  → 503 + Retry-After, no build; service throws LockTimeoutException); failed build leaves no archive or temp file and
  releases the lock; unavailable exception; both buttons navigate directly (no AJAX), markup classes unchanged;
  single-image download route unchanged. Mutation check: without the media-root check the unsafe-path test fails (file
  restored byte-identical). Gallery set 55 / 55; Architecture 5 / 5; PHPStan 0 errors; Pint PASS; full suite 2,514 —
  2,513 passed, 0 failed, 1 skipped. No real ZIP was written outside test storage.
- **Manual verification (owner):** at `http://127.0.0.1:8123/gallery.htm`, click «تحميل» on an album that shows it →
  `album-{id}.zip` downloads and contains the album's images in order; open that album (`/gallery-{id}.htm`) and click
  «تحميل الألبوم بالكامل (ZIP)» → the same archive downloads (second download immediate, served from
  `storage/app/private/gallery-zips/`).
- **Status:** **GALLERY FULL-ALBUM ZIP DOWNLOAD — IMPLEMENTED AND TESTED LOCALLY, AWAITING OWNER VERIFICATION.**
- **[Owner manual test 2026-11-06 — FAILED, then fixed (owner-approved).** `GET http://127.0.0.1:8123/download-album-1.htm`
  returned **404**. **Root cause:** `public/media` is a symlink to the approved `../../legacy-project/media`; the
  service required each image's `realpath` to lie inside `realpath(public/)`, so all 37 images of album 1 (eligible,
  `is_compressed = 1`, every file present) resolved to `legacy-project/media/…` and were rejected — no valid image →
  404 (`storage/logs/laravel.log`: 37 «image skipped» warnings at 2026-11-06 21:49:59). Route, album, eligibility and
  files were all correct. **Why the tests passed:** they used a temporary media root of real directories — the
  symlinked layout was never reproduced; the earlier "1,335 present" check used `is_file()`, which follows symlinks.
  **Fix (`AlbumZipService` only):** realpath containment kept, against an explicit list of trusted roots — the resolved
  public directory and the resolved target of the known `public/media` location only (no other symlink under `public/`
  is trusted; a root resolving to the filesystem root is refused); containment is directory-bounded
  (`root . DIRECTORY_SEPARATOR`, so `/x/media` never admits `/x/media-backup`). Extension allowlist, readability, limits,
  fingerprint cache, atomic build and lock unchanged. **Regression tests (+4, sandboxed layout; the real
  `legacy-project/media` untouched):** approved `public/media` symlink archived; normal media directory still works;
  unapproved external symlink, unapproved symlink to a prefix-sharing directory, `media/../media-backup` prefix collision
  and traversal all rejected; an album with only unapproved images → 404, nothing built. Mutation checks: trusting only
  `public/` → 2 / 23 fail; dropping the boundary separator → 2 / 23 fail (file restored byte-identical). Read-only
  real-data check: album 1 → 37 / 37 images accepted; trusted roots = `laravel-project/public`, `legacy-project/media`;
  no archive built. Gallery set 59 / 59; Architecture 5 / 5; PHPStan 0 errors; Pint PASS; full suite 2,518 — 2,517
  passed, 0 failed, 1 skipped. **Status unchanged: IMPLEMENTED AND TESTED LOCALLY, AWAITING OWNER VERIFICATION.**]**
- **[Owner verification 2026-11-06: GALLERY ALBUM ZIP DOWNLOAD — OWNER VERIFIED LOCALLY — CLOSED.** After the
  symlink fix the owner confirmed: (1) the album 1 ZIP downloaded successfully; (2) the archive contains the expected
  images; (3) the download works without an admin login — public visitor access without authentication confirmed.
  **Not production-verified.** Deployment prerequisites kept open: PHP `ext-zip` on the production PHP; writable private
  ZIP cache storage (`storage/app/private/gallery-zips`, ≈154 MB for a full cache); production media path compatibility
  (gallery images must resolve inside `public/` or the resolved `public/media` target).**]**
  Visitor parity is **not** declared complete.
- **[Clarification 2026-11-08, #128: the heading and **Status** lines above still read "AWAITING OWNER VERIFICATION";
  they are superseded by the owner-verification note above (2026-11-06) — Gallery album ZIP is OWNER VERIFIED LOCALLY —
  CLOSED, consistent with plan §54. Not reopened; production verification still pending. Original wording kept.]**

---

## 108. FEEDBACK FAMILY — OWNER-APPROVED RETIRED OLD-ONLY WORKFLOWS; GEN 2 CONTACT PARITY SATISFIED — CLOSED LOCALLY (2026-11-06)

Owner business decision on the read-only Feedback-family investigation of the same day. Documentation only — no
application code, database, staging, commit, push, deployment or production change. R1 stays deferred. Visitor parity
remains OPEN.

- **Clarification (owner):** "Legacy" means **Gen 2**, the website immediately preceding Laravel (`7amlat`,
  `way2allah.com`). Gen 2 is the PRIMARY parity reference; OLD PHP-Nuke (`old-project`) is supplementary — as in #102.
- **Evidence (read-only):** OLD `modules/Feedback` had (a) the «راسلنا» contact form (`feedback.htm` → `nuke_contact_us`,
  no email, no anti-spam), (b) threaded replies (admin reply mail with a `continue-feedback-{id}.htm` link → email check
  → `confirm-feedback.htm`, `nuke_contact_us_replies`), (c) the «برنامج المجموعات والإتصال» volunteer registration
  (`communication.htm` + AJAX `list_cities.htm` → `nuke_communication`). Gen 2's header «اتصل بنا» → «راسلنا» links to
  Facebook Messenger (`header.php:348`, `https://m.me/Way2allahCom`); `feedback.htm` is linked only from the retired
  chat rules page; the five URLs target the missing `new_modules.php` locally (production not tested). Laravel's
  «راسلنا» links to the same Messenger address (`resources/views/layouts/partials/navigation.blade.php:123`); no broken
  visible UI. `/visitor-feedback` (Gen 2 `pages/estebian.php`) is a separate Google Forms visitor survey, not the
  contact workflow. Data: `nuke_contact_us` 20,161 rows (≈3.7–3.9k / year in 2020–2021, stopping in 2022-01; a
  140-message burst from 11 senders in 2026-04; all recent rows unprocessed), replies last 2016-02, categories 0,
  `nuke_communication` 1 row (2012).
- **Decision (owner):**
  1. Facebook Messenger stays the visitor contact channel — **Gen 2 contact parity satisfied**.
  2. OLD's internal Feedback contact form — **retired** (not restored).
  3. Threaded replies — **retired** (not restored).
  4. OLD's volunteer registration (`communication.htm`, `list_cities.htm`) — **retired** (not restored).
  5. All historical tables and records (`nuke_contact_us`, `nuke_contact_us_replies`, `nuke_contact_us_cats`,
     `nuke_communication`, `nuke_governments`) — **preserved unchanged**.
  6. The five historical URLs (`feedback.htm`, `continue-feedback-{id}.htm`, `confirm-feedback.htm`,
     `communication.htm`, `list_cities.htm`) — **not implemented at this stage**.
  7. Historical URL compatibility stays a separate concern, reopened only on concrete evidence of active use (e.g.
     traffic to `continue-feedback-*` links sent by email until 2016).
- **Status:** **FEEDBACK FAMILY — OWNER-APPROVED RETIRED OLD-ONLY WORKFLOWS. GEN 2 CONTACT PARITY SATISFIED. CLOSED
  LOCALLY (2026-11-06).** Not production-verified. Visitor parity is **not** declared complete.

---

## 109. CATEGORY GROUP — FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-06)

Owner decision (Option A) on the read-only Category Group investigation of the same day. Documentation only — no
application code, test, database, staging, commit, push, deployment or production change. R1 stays deferred. Khotab
#60 and Feedback #108 unchanged; visitor parity remains OPEN.

- **Historical route:** `category-group-{g}-{c}.htm` → `new_modules.php?name=Cat&op=Group&id={g}&cat_id={c}` (OLD
  `.htaccess:573`, Gen 2 `.htaccess:177`) → OLD `modules/Cat/index.php:165-184` `Group()` (dispatch `:655`).
- **OLD behaviour (supplementary):** heading «مجموعة {group} - تصنيف : {category}» and group breadcrumb; content =
  `ListSeries($cat, $group)` — **all** series of the category (group filter commented out, `:261`) — and
  `ListKhotab($cat, 0, $group, $author)` — **all** standalone items of the category (query uses only category and
  `ser_id = 0`, `:356-363`); `id DESC`, no pagination, POST search box; group author's top-10 sidebar (`:181`);
  "all categories of the group's items" tree when counts differ (`GroupTree`, `:439-444`). No login. Linked only from the
  `Series()` breadcrumb (`:204`) and `GroupTree` (`:491`); OLD's category page does not list groups (`:131`
  commented out). In visitor terms: OLD's category listing under a group heading.
- **Gen 2 (primary):** route declared; target `new_modules.php` and a Cat module absent locally (production for this URL
  untested); Gen 2's only generators (`categories/functions.php:32`, `vars_categories/functions.php:37`, `ListGroup()`)
  emit a malformed one-id URL the rule cannot match and are never called; no active visitor entry point. Equivalent
  content: `category-{id}.htm` (`categories/category.php` — the category's series and items) and
  `khotab-group-{id}.htm` (`khotab/group.php`).
- **Laravel (target):** `category-{category}.htm` (`categories.show`, `CategoryController::show` — series + standalone
  items, sidebars as Gen 2) and `khotab-group-{group}.htm` (`khotab.group.show`, owner-closed under #60); no
  `category-group` route, no Laravel page links to one — no broken control, no unreachable category, group, series or item.
  Data (read-only): 328 groups, 309 with category lists, none hidden.
- **Comparison:** equivalent — category content and group browsing; missing vs Gen 2 — none; URL-only — the historical
  `category-group-*` URL; presentation not carried (already absent in Gen 2) — OLD's group heading on a category listing,
  the group-author top-10 sidebar, the group-categories tree; unknown — production behaviour of this URL on either host.
  Priority LOW. No new evidence to reopen #60.
- **Decision (owner):** **FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED.** No `category-group` redirect or
  compatibility route at this stage; reconsider only on concrete evidence of active historical URL use. Not
  production-verified.
- **Status:** **CATEGORY GROUP — FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-06).**

---

## 110. SATELLITE RAMADAN — FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-06)

Owner decision (Option A) on the read-only Satellite Ramadan investigation of the same day. Documentation only — no
application code, test, database, staging, commit, push, deployment or production change. R1 stays deferred. #60,
#108 and #109 unchanged; visitor parity remains OPEN.

- **Historical URLs:** `channels-ramadan.htm` (OLD `.htaccess:668`, Gen 2 `:316`) and `channel-ramadan-{id}.htm`
  (OLD `:674`, Gen 2 `:317`) → `new_modules.php?name=Satellite&op=channels_ramadan` / `&op=channel_ramadan&id=` → OLD
  `modules/Satellite/index.php` `w2a_sat_ramadan_list()` (`:526-549`, dispatch `:807`) and `w2a_channel_ramadan_list()`
  (`:80-113`, with `list_ramadan_series()` `:303+` and `List_ramadan_Khotab()` `:443+`, dispatch `:801`).
- **OLD behaviour (supplementary):** a fixed **Ramadan 1433** snapshot — the hard-coded 2012-07-15 to 2012-08-21 window
  (`is_ramadan_channel()` `:551-585`); the channel list links each channel to `channel-ramadan-{id}`
  (`channel_block_ramadan.htm:2`), which shows that channel's series and items from the window. Recorded programmes
  only — no live stream, schedule, external service, login, form or admin side; data from `nuke_islamic_series`,
  `nuke_islamic_khotab`, `nuke_sat_channels`. Not linked from OLD navigation (direct URL only).
  `w2a_channel_ramadan_list()` uses bare array keys (`$Sat[pos]`, `$Channel[title]`) — a PHP 8 fatal by source
  inference (not tested).
- **Gen 2 (primary):** both rules declared; target `new_modules.php` and a Satellite module absent locally; no active
  visitor link generated. Replacement: Gen 2's year-based Ramadan pages (`pages/ramadan*.php`, Ramadan-flagged series
  from Hijri 1434) and the normal channel pages (`channel-{id}.htm` → `channels/channel.php`). Production behaviour of
  the two URLs is unknown — no production 404 is claimed.
- **Laravel (target):** `/ramadan.htm` (`pages.ramadan`, `RamadanController` — consolidates Gen 2's Ramadan pages,
  Hijri 1434–1447, `ContentListingService::ramadanSeriesByYear()`), consistent with the Gen 2 scope; `channel-{id}.htm`
  (`channels.show` — all visible series with items and standalone items of the channel, no date filter); series and
  author pages. No `channel(s)-ramadan` route; nothing links to one; no broken control.
- **Data (read-only):** the 2012 window holds 147 series (146 visible, 1 Ramadan-flagged; 130 on 19 channels) and 1,717
  items (all visible; 1,429 with a channel, 24 standalone); `channels-ramadan` would list 19 of 70 channels. All of it is
  reachable through channel, series and author pages; nothing exclusively through the historical URLs.
- **Comparison:** equivalent — the 2012 content and year-based Ramadan browsing from 1434; missing vs Gen 2 — none;
  URL-only — the two historical URLs; replaced — OLD's 2012 channel snapshot by Gen 2's year-based Ramadan page and
  channel pages; unknown — production behaviour of the URLs on either host. **Priority LOW.**
- **Decision (owner):** **FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED.** No redirect or compatibility route
  for the two URLs at this stage; reconsider only on concrete evidence of active historical URL use. Not
  production-verified.
- **Status:** **SATELLITE RAMADAN — FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-06).**

---

## 111. SERIES LIST — FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-07)

Owner decision (Option A) on the read-only Series List investigation. Documentation only — no application code, test,
database, staging, commit, push, deployment or production change. R1 stays deferred. #60 and #107–#110 unchanged;
visitor parity remains OPEN.

- **Historical URLs:** `series_list-{id}-{col}-{sort}.htm` (OLD `.htaccess:680`, Gen 2 `:318`) and
  `series_list-{id}-{col}-{sort}-{author}.htm` (OLD `:684`, Gen 2 `:320`) → `new_modules.php?name=Satellite&op=series_list`
  → OLD `modules/Satellite/index.php` `list_series()` (`:114-248`, dispatch `:813-815`); an HTML fragment, no login.
- **OLD behaviour (supplementary):** AJAX sorting of a Satellite channel's series — arrow images in the series header
  (`templates/Satellite/sat_series_head_ajax.htm:5-8`) → `getdata('series_list-…','series-list')` (`js/ajax.js:4`) →
  fragment into `#series-list`. Columns: 1 title, 2 author name, 3 last update, 4 item count (else id); `up` = DESC,
  otherwise ASC; initial list author name ASC (`w2a_channel_list()` `:42-78`); separate POST search; no pagination.
  Source defects: "up" links drop the author, `-2-down$aut` lacks a hyphen, `if ($col_no=2)` is an assignment.
- **Gen 2 (primary):** both rules declared; no Satellite handler locally; `series_list` URLs not generated or used by
  active visitor code. Channel series listed server-side `lastupdate DESC` (`channels/functions.php:88-99`) inside a
  400 px scroll box (`.series-overflow`, `custom.css:486-490`).
- **Source correction — DataTables (source inference only):** Gen 2's channel page loads DataTables 1.10.10 and
  `scripts/khotab_tables.js`, which configures client-side sorting, a search box and paging for `#tabelgrp`, `#tabelser`
  and `#tabelkht` (init on document ready). The table markup has no `<thead>` (`channels/functions.php:29, :118, :206`),
  and with `order: [[0, "asc"]]` DataTables 1.10.x most likely fails to initialize (`aDataSort` TypeError). **No browser
  execution was performed** — this is not stated as a confirmed production failure. Supersedes the earlier remark that
  Gen 2 had no DataTables configuration / no column sorting on the channel page.
- **Laravel (target):** `channels/_listing.blade.php` (channel and channel-author pages) — series from
  `seriesByChannel()` ordered `lastupdate DESC`, same conditions as Gen 2, shown as a card grid
  (`<x-content.media-collection-grid>`); the items table `#tabelkht` also lacks `<thead>` (same DataTables situation as
  Gen 2). No `series_list` route, no link to one, no broken control; no series unreachable.
- **Comparison:** equivalent — content, order (`lastupdate DESC`), no pagination; missing vs Gen 2 — none confirmed; URL-only
  — the two patterns; replaced — OLD's AJAX sort by Gen 2's fixed order; unknown — Gen 2 DataTables behaviour in a browser,
  production behaviour of the URLs. **Priority LOW.** No new evidence to reopen #60.
- **Separate UX observation — UNVERIFIED, not a functional parity gap:** Gen 2 shows channel series in a 400 px scrolling
  container; Laravel's card grid shows every series on the page — some channels have hundreds (channel 2: 968, channel
  51: 821 visible video series), which may make unusually long pages. No UI change made; #60 not reopened.
- **Decision (owner):** **FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED.** No redirect or compatibility route at
  this stage; reconsider only on concrete evidence of active historical URL use. Production behaviour unverified.
- **Status:** **SERIES LIST — FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-07).**

---

## 112. VAR CHANNEL — FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-07)

Owner decision (Option A) on the read-only Var Channel investigation. Documentation only — no application code, test,
database, staging, commit, push, deployment or production change. R1 stays deferred. #60, #88 and #107–#111 unchanged;
visitor parity remains OPEN.

- **Historical URLs:** `var-channel.htm` (OLD `.htaccess:687`, Gen 2 `:321`) → `new_modules.php?name=Satellite&op=var_channel`
  → OLD `modules/Satellite/index.php:587-606` `w2a_sat_list_var()` (dispatch `:817-819`); `var-channel-{id}.htm` (OLD
  `:690`, Gen 2 `:322`) → `…op=var_channel_details&id=` → `list_var_channel_list_details()` (`:608-640`, with
  `list_series_var()` `:641-714` and `ListAnasheed()` `:743+`, dispatch `:821-823`).
- **OLD behaviour (supplementary):** Anasheed («المنوعات») browsing grouped by satellite channel — the list of channels
  with Anasheed items or groups (each linking to `var-channel-{id}`, `templates/Satellite/channel_block_var.htm:2`),
  and per channel its Anasheed groups (`nuke_anasheed_groups`, `channel_id`, `parent_id <> 387`) and items
  (`nuke_anasheed_anasheed`, `channel_id`, `group_id <> 387`, `weight DESC`, hidden filtered for visitors); group 387
  does not exist in the current data. Recorded content only; no external service, no login; the group sort links have
  their `ORDER BY` commented out (`:693`); no pagination or search. `var-channel.htm` is not linked from OLD navigation.
  `list_var_channel_list_details()` uses bare array keys (`:614-620`) — a PHP 8 fatal by **source inference only**.
- **Gen 2 (primary):** both rules declared; the target dispatcher and a Satellite module are absent locally; no active
  visitor entry point generates either URL; no Anasheed-by-channel page anywhere. Production behaviour unverified.
- **Laravel (target):** no dedicated Anasheed-by-channel listing (the models' `channel_id` drives no visitor page); no
  `var-channel` route, no link to one, no broken control.
- **Data (read-only):** 6,084 channel-tagged Anasheed items (all visible, 36 channels) — **every one belongs to a group**
  (`group_id = 0`: 0) and stays reachable through existing Laravel pages (`var-group-{id}`, `var-category-{id}`, Anasheed
  news, `var-item-{id}`); 257 channel-tagged groups (12 channels) reachable through existing routes; `var-channel.htm`
  would list 36 of 70 channels. Nothing is reachable only through the historical URLs.
- **Comparison:** equivalent — access to all channel-tagged Anasheed groups and items; missing vs Gen 2 — none;
  URL-only — the two URLs; OLD-only view (already absent in Gen 2) — browsing Anasheed by satellite channel; unknown —
  production behaviour of the URLs on either host. **Priority LOW.** No new evidence to reopen Anasheed #88.
- **Separate observation — UNVERIFIED, not a Laravel parity gap:** Gen 2's Anasheed category listing (`ListVar()`,
  `categories/functions.php`, about `:473`; called from `categories/category.php:77`) links an item's channel name to
  `channel-{channel}-{category}.htm` — the Khotab channel-by-author route, receiving an Anasheed category id as the
  author id; possibly an incorrect historical link. Not investigated or fixed; #88 not reopened.
- **Decision (owner):** **FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED.** No redirect or compatibility route
  for `var-channel.htm` / `var-channel-{id}.htm`; reconsider only on concrete evidence of active historical URL use.
- **Status:** **VAR CHANNEL — FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-07).**

---

## 113. RSS FAMILY — OWNER-APPROVED RETIRED FROM GEN 2 BASELINE — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07)

Owner business decision on the read-only RSS family investigation. Documentation only — no application code, test,
database, staging, commit, push, deployment or production change. R1 stays deferred. #60, #88 and #107–#112 unchanged;
visitor parity remains OPEN. **This is a scope decision — it does not claim that Laravel provides RSS (not
"functionally covered").**

- **Inventory — 15 RewriteRules, identical in OLD (`.htaccess:159-201`) and Gen 2 (`.htaccess:132-146`), all
  `new_modules.php?name=RSS&op=…` → OLD `modules/RSS/index.php`:** `way2allah.rss` (`Way2Allah` — no case),
  `khotab-audio_news.rss` (`MoreAudio` → `ListItems(0)`), `khotab-video_news.rss` (`MoreVideo` → `ListItems(1)`),
  `khotab-audio-{id}.rss` (`Author`), `khotab-video-{id}.rss` (`AuthorVideo`), `khotab-group-{id}.rss` (`Group`),
  `khotab-series-{id}.rss` (`Series`), `var-news.rss` (`MoreAnasheed` — no case), `var-group-{id}.rss` (`VGroup`),
  `recite-news.rss` (`MoreTelawah`), `recite-group-{id}.rss` (`RGroup` — no case), `news-bar.rss` (`MoreNews` — vBulletin
  forum threads, needs the forums DB config), `more-fatawa.rss` (`MoreFatawa`), `more-hedaya.rss` (`MoreHedaya` — the
  retired «هداية» lessons), `dumped-lectures.rss` (`MoreDumb` → `MoreDumbFiles()`). (`var-news.xml` /
  `khotab-news.xml` are `flashxml`, not RSS — not covered by this entry.)
- **OLD (supplementary):** RSS 2.0, one channel; items title / link / description / guid only (no pubDate, author
  element, category or enclosure); public; newest first, limits 10 / 20 / 50. Source defects: three `op`s without a
  handler (empty response); `ListItems()` and `MoreDumbFiles()` call an undefined global `sql_query()` and `MoreDumb`
  omits a required argument (fatal on PHP 8 — source inference); no Content-Type header; `windows-1256` declared over
  UTF-8 data; titles not XML-escaped.
- **Gen 2 (primary):** all 15 rules declared; no RSS handler, dispatcher or other RSS generator locally; no
  `<link rel="alternate" type="application/rss+xml">`; the only feed links in visitor code are commented out
  (`home_functions.php:66, 92, 180, 283, 325, 399`; `new_content.php:91, 136, 162, 362, 391`) — deliberately
  de-advertised.
- **Laravel (target):** no RSS / Atom route, feed endpoint, advertised feed or RSS link; the underlying content is
  available as HTML pages (which is not RSS compatibility).
- **Production evidence:** five URLs verified **HTTP 404 on 2026-08-12** on `https://way2allah.com/` —
  `way2allah.rss`, `khotab-audio_news.rss`, `khotab-video-1.rss`, `var-news.rss`, `more-fatawa.rss`
  (`implementation-findings.md` IF-045; redirect following and response type not recorded). The other patterns and ids
  are **not production-verified**.
- **External usage: UNKNOWN.** No access logs locally; Gen 2's performance logs (2026-07, apparently production copies)
  show 2 crawler requests to malformed `/index.php/…​.rss` paths, and cannot capture direct `.rss` requests (those never
  reach PHP).
- **Assessment kept distinct:** visitor functional parity — **LOW concern** (no active Gen 2 feed missing); external RSS
  dependency — **UNKNOWN**; production verification — **incomplete**.
- **Decision (owner):** retire the historical RSS family from the Laravel migration scope — do not restore the 15 feeds;
  no new RSS endpoints; no historical RSS redirects; no redirects of RSS URLs to HTML; no placeholder RSS responses; no
  change to content listings; no restoration of retired OLD functionality to support feeds. **Reconsider only** on
  concrete evidence of active external subscribers or integrations, or a new owner-identified content-syndication
  requirement — any future RSS work needs its own scope and explicit approval; the existence of the historical
  RewriteRules does not reopen this decision.
- **Status:** **RSS FAMILY — OWNER-APPROVED RETIRED FROM GEN 2 BASELINE — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY
  (2026-11-07).**

---

## 114. LOCATIONS — TARGETED DEFECT FIXED AND TESTED LOCALLY — OWNER-APPROVED UI — CLOSED LOCALLY (2026-11-07)

Owner-approved targeted fix of the Locations family plus a targeted UI correction, both implemented and tested locally;
the owner manually reviewed and approved the updated Locations / Chat Room UI locally. No database change, migration,
new route, redirect, staging, commit, push, deployment or production access. R1 stays deferred. #39, #60, #88 and
#107–#113 unchanged; visitor parity remains OPEN; **production verification remains pending.**

- **Seven historical patterns** (`.htaccess:152-162`, identical in OLD and Gen 2): `locations.htm`, `location-{l}.htm`,
  `location-{l}-author-{a}.htm`, `location-{l}-group-{g}.htm`, `location-{l}-series-{s}.htm` (all
  `new_modules.php?name=Locations…`), `location-{l}-item-{k}.htm` (`name=Khotab&op=Detailes`) and `alhedaya-room.htm`
  (`name=Locations&location=10`). The commented `alhedaya-author/group/series-XXXXX.htm` lines are not rules.
- **Gen 2 (primary):** all seven rules target `new_modules.php`, which is absent from the Gen 2 tree (no production 404
  is inferred from that alone). The only Gen 2 location code is the chat room's, hard-coded to location 10:
  `chat_room/alhedaya_room.php` (`$location = 10`) and `chat_room/author.php` (`$location_id = 10`), reached via the
  chat-room URLs. **OLD (supplementary):** `modules/Locations` `Author($id, $location)` scopes `ListGroup` /
  `ListSeries` / `ListKhotab` / `Top10` to the requested `$location`; `locations.htm` was an empty `Location('')` page,
  not an index.
- **Laravel (target), generic:** `location-{l}.htm` / `alhedaya-room.htm` (`LocationController::show`, any location),
  `location-{l}-author-{a}.htm` and `location-{l}-item-{k}.htm` (pass-through to `KhotabItemController::show`,
  unchanged). Group / series location URLs stay URL-only (not routed) and `locations.htm` stays unbuilt — **#39
  Decision 3 (`DO_NOT_BUILD`) preserved**; no historical group / series routes or redirects without new evidence.
- **Confirmed defect:** `location-{l}-author-{a}.htm` was fixed to location 10 (IF-048's pass-through to the chat-room
  author page). Location pages link every author to `location-{l}-author-{a}.htm`, so an author registered only at
  another location returned 404 (20 of 104 author links in the local investigation), and an author registered at
  location 10 and another location showed location-10 content on the other location's page.
- **Fix:** `ChatRoomLessonController::authorAtGivenLocation()` (called by `LocationController::locationAuthor()`)
  scopes the author check, groups, series, items and both sidebars (`chatRoomMostViewedLessons` /
  `chatRoomMostRecentLessons`, now location-parameterised, default 10) to the requested location. Hidden-content rules
  unchanged (hidden author → 404; hidden lessons excluded).
- **Location 10 preserved:** `chat_author_{a}.htm` and `location-10-author-{a}.htm` keep location 10, the
  `<title>` «… - غرفة الهداية الدعوية», the breadcrumb link to `/chat_room.htm` and the `chat_author_{a}.htm` avatar
  link, with no extra query. Another location shows its own name (title, heading, breadcrumb → `location-{l}.htm`) and
  links the avatar to its own author page.
- **Missing location (owner decision):** a nonexistent location is 404 (`Location::findOrFail`, as
  `location-{l}.htm`), including when an orphaned author-location link row exists — a deliberate difference from OLD,
  which never 404s there. Existing + linked → 200; existing + unlinked → 404.
- **Orphaned relationships left unchanged:** the local database has 3 `nuke_islamic_authors_location` rows pointing to
  2 location ids with no location row (107 rows in all); not modified. Production counts not checked.
- **Targeted UI (owner-approved):** `chat-room/author`, `chat-room/lesson` and `locations/show` now use the shared
  `<x-page-chrome>` heading / breadcrumb and `<x-content.premium-panel>` sections, the Khotab author card
  (`w2a-author-profile-card` / 96 px `w2a-author-avatar`, replacing the unconstrained `#author-info` image) and the
  Khotab column split with `w2a-media-layout`; empty groups / series panels are hidden (as Gen 2), the items panel
  always renders, with the existing `w2a-empty-state` message (`chat-item-list`, chat-room pages only); the location
  page drops the duplicate heading and the empty sidebar column (no sidebar added), with a responsive image and a
  full-width `table-responsive` author table. No CSS file, query, route, player or download change.
- **Owner approval:** the owner manually reviewed and approved the updated UI locally (2026-11-07). No automated
  browser verification was performed.
- **Automated verification (final working tree, 2026-11-07):** full suite **2,537 tests — 2,536 passed, 1 skipped**
  (18,695 assertions); PHPStan **0 errors**; Pint **pass** (whole project); Architecture **5/5**. Tests:
  `LocationAuthorScopingTest` (new), `LocationControllerTest` and `ChatRoomLessonControllerTest` (updated / extended);
  mutation checks confirmed the scoping, missing-location and UI assertions fail when the behaviour is reverted.
- **Pending:** production verification (routes, location data, orphan rows, visual check).
- **Status:** **LOCATIONS — TARGETED DEFECT FIXED AND TESTED LOCALLY — OWNER-APPROVED UI — CLOSED LOCALLY
  (2026-11-07).**

---

## 115. KHOTAB HISTORICAL MEDIA URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY — OWNER DECISION APPROVED — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only Khotab historical media URL investigation. Documentation only — no application code,
route, redirect, CSS, test, database, staging, commit, push, deployment or production change. **#60 (Khotab) stays
CLOSED LOCALLY and is not reopened.** #39, #88 and #107–#114 unchanged; visitor parity remains OPEN; R1 deferred;
production verification pending.

- **Exactly five unique patterns** (the five counted in #103 (D); six Gen 2 rules, `play-mirror` twice), all
  `new_modules.php?name=Khotab&op=…` → OLD `modules/Khotab/index.php`:
  `khotab-play-{id}.htm` (`op=Play`; OLD `.htaccess:408`, Gen 2 `:240`), `play-mirror-{khid}-{id}.htm`
  (`op=PlayMirror`; OLD `:387`/`:390`, Gen 2 `:237`/`:238`), `khotab-playmirror-{khid}-{id}.htm` (`op=PlayMirror`;
  OLD `:414`, Gen 2 `:242`), `khotab-thumb-{id}.htm` (`op=Thumbs`; OLD `:411`, Gen 2 `:241`), `khotab-banner.htm`
  (`op=Banner`; OLD `:417`, Gen 2 `:243`). `thumb/khotab/{id}-{n}.jpg` (`op=GetThumb`) is active in OLD (`:381`) but
  commented out in Gen 2 (`:235`) — not one of the five. None of the five is a media file URL.
- **OLD (supplementary):** `Play()` (`:1506`) / `PlayMirror()` (`:1522`) return headerless HTML player fragments
  (`ShowPlayer()`, archive.org media) that `js/inc.js` loaded by AJAX into a dialog, and that the mirror table's play
  icon (`mirror_cont_for_video.htm:4`) opened in a Modalbox; `Thumbs()` (`:1458`) is a full «صور من المادة» page of
  the item's pipe-separated external frame grabs (linked from `detailes.htm:61` only when present); `Banner()`
  (`:1798`) renders one random `banner=1` item's banner plus embed-code links — no OLD template or script links to
  `khotab-banner.htm`; `get_thumb()` (`:1363`) 301-redirects to the external image. Public; only `Codes()` filters
  `hidden`. Source defects: `Play()` passes an unselected `thumbnail` column; Thumbs' per-image
  `khotab-thumb-{id}-{key}.htm` links match no rule; `get_thumb()` sends a malformed status line and an empty Location
  for an out-of-range index; `Banner()` uses undefined `$Author` / `$Dimention`.
- **Gen 2 (primary):** no active code generates any of the five. `inc.js` is loaded (`header.php:151`) but its player
  functions have no live caller (only commented / unrouted `khotab/old.php` and `channels/old.php`). Playback was
  replaced: `khotab/item.php` uses `w2a_play(id,'khotab')` / `w2a_play(mirror_id,'khotab_mirror')` → POST
  `get-mada-player.htm` (`get_w2a_mada_player()`); mirrors download via `khotab-mirror-{khid}-{id}.htm`; no thumbnail
  gallery and no banner block on the item page; the item frame (`media/khotab_frames` / `khotab_gifs`) is used only
  for `og:image` / `twitter:image` (`header.php:43-73`). No visitor control depends on the five URLs.
- **Laravel (target):** `POST /media-player` plays `khotab` and `khotab_mirror` (hidden items / mirrors excluded);
  `media-quality-list` gives each mirror a play button and `/khotab-mirror-{khid}-{id}.htm` download; main download
  and PDF routes exist; the item page carries the OLD-parity banner block with working `khotab-code-{id}-{1,2,3}.htm`
  links. No route for the five; the `thumbnail` field is not displayed (as in Gen 2).
- **Local data (read-only, aggregates):** 292,592 items (292,505 visible); 426,052 mirrors (421,774 on visible items,
  228,642 items; 4,025 orphaned); 6,466 visible items with a `thumbnail` list (≈141,000 URLs, almost all archive.org);
  1,363 `banner=1` items (all visible; 1,383 banner GIFs present). All playable items, mirrors, downloads and banners are
  reachable through `khotab-item-{id}.htm`; only the frame-grab gallery existed solely behind a historical URL.
- **No confirmed missing Gen 2 workflow.**
- **Decision (owner): B — historical URL compatibility only.** Patterns 1–3: historical playback fragments; Gen 2
  replaced the workflow and Laravel preserves main and mirror playback — no implementation. Pattern 4: OLD-only
  gallery — **do not restore**; existing `thumbnail` data preserved. Pattern 5: OLD-only random banner fragment —
  **do not restore the endpoint**. **No routes and no redirects** (the old endpoints returned HTML fragments, not item
  pages — no misleading redirects).
- **Unknowns kept explicit:** historical RewriteRules do not establish active functionality; a missing Gen 2 local
  handler does not prove a production 404; production responses for the five URLs are UNKNOWN (no production or
  access-log verification); external inbound links and external embedding of `khotab-banner.htm` are UNKNOWN.
- **Separate finding (outside this family):** social-sharing metadata — Gen 2 emits item-specific `og:image` /
  `twitter:image`; no Laravel Blade template emits `og:` / `twitter:` meta. Recorded as **OPEN investigation candidate
  IF-056** (`implementation-findings.md`); not implemented, does not reopen #60.
- **Status:** **KHOTAB HISTORICAL MEDIA URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY — OWNER DECISION APPROVED — NO
  IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07).**

---

## 116. FATWA HISTORICAL URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY, WITH TARGETED PLAYBACK DEFECT FIXED — CLOSED LOCALLY (2026-11-07)

Owner closure of the Fatwa historical URL family after a read-only investigation, one owner-approved targeted fix and
owner local browser checks. No route, redirect, controller, service, CSS or database change; the only application
change is one Blade attribute plus one regression test (below). R1 stays deferred. #18, #19, #20, #60, #106, #115 and
all other closures unchanged; IF-056 stays OPEN; visitor parity remains OPEN; **production verification pending.**

- **Inventory — five patterns** (Gen 2 `.htaccess:304-308` → the absent `modules.php?name=Fatwa&op=…`; OLD
  `.htaccess:645-657` → `new_modules.php`; OLD handlers in `modules/Fatwa/index.php`); no production evidence recorded:
  1. `fatawa-play-{id}.htm` — AJAX player fragment. OLD `case "play"` calls a `play()` that exists nowhere in the OLD
     tree (source inference: fatal). Gen 2: generated only by uncalled `inc.js:258/312`. **Replaced; no implementation.**
  2. `fatawa-download-{id}.htm` — redirect to the media file. Gen 2 generates it (`single.php:154`, `answer.php:131`,
     `answer2.php:191`). Laravel `FatwaQuestionController@download`. **Already supported.**
  3. `fatawa-brokenlink-{id}.htm` — OLD-only, admin-only (link and handler gated on admin), writes nothing, sends no
     email. No Gen 2 generator or handler. **Do not restore.**
  4. `fatawa-friend-{id}.htm` — OLD AJAX form fragment (`friend_form.htm`). Gen 2: generated only by uncalled
     `inc.js:394`; replaced by the inline modal (`single.php:164-227`) posting to `fatawa/sendemail.php`. **Do not
     restore.**
  5. `fatawa-friend-sendemail-{id}.htm` — form submit. Laravel `POST` → `FatwaQuestionController@sendToFriend` (CSRF,
     validation, fixed From). **Already supported.**
- **Gen 2 (primary):** playback `w2a_play(id,'fatawa')` → POST `get-mada-player.htm` with the page's raw `media_link`
  (`single.php:145`, `:286`); download link; inline send-to-friend modal; no broken-link control.
- **Laravel:** `w2a_play` → `POST /media-player` (`MediaPlayerService::fromFatwaQuestion()`, stored link, played as
  video); download and send-to-friend routes as above; no routes for patterns 1, 3, 4 — **no historical routes or
  redirects** (the old endpoints returned fragments, not pages).
- **Targeted defect fixed (owner-approved):** `resources/views/fatawa/question-show.blade.php:176` had
  `onclick="w2a_play({{ $fatwaQuestion->id }}, "fatawa")"` — the inner double quotes ended the attribute — corrected to
  `'fatawa'` (as `question-all.blade.php:229`). Regression test in `FatwaQuestionControllerTest` parses the rendered HTML
  and asserts the play button's `onclick` is exactly `w2a_play(50, 'fatawa')`, plus the download link and media player;
  it failed against the original markup (attribute parsed as `w2a_play(50, `) and passes after the fix. Results: focused
  1/1; `FatwaQuestionControllerTest` 38/38; Fatwa + media-player tests 218/218; Architecture 5/5; PHPStan 0; Pint pass;
  full suite **2,538 tests — 2,537 passed, 1 skipped** (18,703 assertions).
- **Owner local browser verification (owner-reported, 2026-11-07; not automated, not production, not generalised to
  all records):** `fatawa-2103.htm`, `fatawa-4814.htm`, `fatawa-11019.htm`, `fatawa-all-1865.htm` — playback working;
  `fatawa-36.htm` — unsupported-media message (`.avi`), expected; `fatawa-all-3552.htm` — partial playback explained
  (36 `.avi` unsupported; 4814 and 11019 play).
- **Decision #20 applies to Fatwa `.avi`** (shared player): 89 records unchanged — no ActiveX, no transcoding, no media
  URL replacement (IF-057, **RESOLVED LOCALLY**).
- **Kept open / deferred:** IF-058 — 10 malformed and 11 empty `media_link` values, a data-quality issue, **OPEN**, no
  database change approved; the player's «يمكنك تجربة جودة أخرى» hint (misleading for Fatwa) — **DEFERRED** UX; the
  #106 send-to-friend rate-limiting / anti-abuse concern — **unchanged**; IF-056 (social metadata) — **OPEN**.
- **Status:** **FATWA HISTORICAL URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY, WITH TARGETED PLAYBACK DEFECT FIXED —
  CLOSED LOCALLY (2026-11-07).** No further implementation required for the investigated URL family.

---

## 117. TELAWAH HISTORICAL RECITE-PLAY URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY, WITH TARGETED MP4 PLAYBACK GAP FIXED — CLOSED LOCALLY (2026-11-07)

Owner closure after a read-only investigation, one owner-approved one-line fix and owner local browser checks. No
route, redirect, controller, Blade, JavaScript, CSS or database change. Earlier Telawah functional-parity work is not
reopened. R1 stays deferred. #20, #60, #106, #113, #115, #116 and all other closures unchanged; IF-056 and IF-058 stay
OPEN; visitor parity remains OPEN; **production verification pending.**

- **Inventory — exactly one playback pattern:** `recite-play-{id}.htm` — OLD `.htaccess:719` and Gen 2 `:330`, both
  → `new_modules.php?name=Telawah&op=Play&id=$1` (absent from the Gen 2 tree). No production evidence recorded.
  Related, already served by Laravel and not playback: `recite-item-{id}.htm`, `recite-download-{id}.htm`,
  `recite.htm`, `recite-group-{id}.htm`, `recite-news.htm` (`.rss` patterns retired under #113).
- **OLD (supplementary):** `modules/Telawah/index.php:544` `Play()` — headerless `ShowPlayer()` fragment
  (archive.org link normalised; `o.k` text for an unknown id), loaded by AJAX into a dialog from the item template
  (`detailes.htm:28` → `show_Tlawa()`, `js/inc.js:296`); public, no hidden filter. Source defect: non-mp3 alternative
  links call `show_mirror_video()`, which targets Khotab's `play-mirror-` endpoint.
- **Gen 2 (primary):** `telawah/item.php` → `draw_telawah()` — «سماع المادة» `w2a_play(id,'telawat')` → POST
  `get-mada-player.htm` (`get_w2a_mada()` `SELECT *`, `functions.php:859`; `get_w2a_mada_player()` uses the audio branch
  only when `vedio == "0"`, else video); «حفظ المادة» → `recite-download-{id}.htm`; no mirror list. No active code
  generates `recite-play` (`inc.js:299` `show_Tlawa` loaded, never called).
- **Laravel:** `recite-item-{id}.htm` — `media-details-card` (`module="telawat"`, correctly quoted
  `w2a_play(id,'telawat')`) → `POST /media-player`; download route; OLD-parity alternative links. **No historical route
  or redirect** (the old endpoint returned a fragment, not a page).
- **Targeted defect fixed (owner-approved):** `MediaPlayerService::fromTelawahItem()` hard-coded `'video' => false`, so
  `.mp4` items with `vedio=1` got no player; changed to `'video' => (bool) $item->vedio` (Gen 2's selection). Affected
  local records: 9205 «سورة الفاتحة» and 9207 «سورة آل عمران» (both `.mp4`, `vedio=1`, group 252). `renderPlayer()`,
  shared format support and other modules unchanged (`fromTelawahItem()` serves `telawat` only); of the other `vedio=1`
  rows (125 rm, 184 rmvb, 21 YouTube `videoplayback`, 1 zip) none gains a player.
- **Regression tests (`MediaPlayerControllerTest`):** mp4 + `vedio=1` → `<video>` with the stored link; mp3 + `vedio=0` →
  `<audio>`; mp3 + `vedio=1` → `<audio>` (mp3 is checked before the flag); rm (`vedio=0`) / rmvb (`vedio=1`) → empty body.
  Restoring `'video' => false` fails the mp4 test. Results: `MediaPlayerControllerTest` 36/36 (126 assertions); Telawah
  + Fatwa + Khotab item tests 155/155; full suite **2,543 tests — 2,542 passed, 1 skipped** (18,727 assertions); PHPStan
  0; Pint pass; Architecture 5/5.
- **Owner local browser verification (owner-reported, 2026-11-07; not automated, not production, not generalised to
  all Telawah media):** `recite-item-9205.htm` and `recite-item-9207.htm` — video playback works.
- **Decision #20 applies** to Telawah obsolete formats (rm / rmvb / wma / ra / ram): no player, no ActiveX, no
  transcoding, no media URL replacement.
- **Data observations (not blockers; no new finding opened; no database change):** of 8,860 local recitations, 30
  YouTube links are expired 2011 `googlevideo` `videoplayback` signed URLs (dead in Gen 2 and Laravel), and 72 links are
  non-media or odd (60 zip, 2 rar, 2 m3u, 8 without a usable extension).
- **Unknowns:** production responses for `recite-play-{id}.htm`, external inbound links, archive.org availability
  beyond the two checked items.
- **Status:** **TELAWAH HISTORICAL RECITE-PLAY URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY, WITH TARGETED MP4
  PLAYBACK GAP FIXED — CLOSED LOCALLY (2026-11-07).** No further implementation required for this URL family.

---

## 118. W2ACD HISTORICAL URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only W2ACD historical URL investigation. Documentation only — no application code, test,
route, redirect, CSS, database, staging, commit, push, deployment or production change. **#74 (`cds-news` excluded) and
#92 (`cds-series .grx` deferred LOW) unchanged**; the W2ACD admin controls (#73–#78) and visitor parity (#91 / #92) are
not reopened. #102, #103, #113, #115–#117 and all other closures unchanged; visitor parity remains OPEN; R1 deferred;
production verification pending. No confirmed Laravel functional gap against the active Gen 2 workflows.

- **Inventory — 14 active Gen 2 patterns** (`.htaccess:251-268`, all → the absent `new_modules.php?name=w2acd…`; the same
  14 in OLD `.htaccess:516-561` → `modules/w2acd/index.php`, `Comments.php`, `friend.php`). Not counted: the query-gated
  `modules.php` 301 (`:266`, covered by #103) and the commented `cds-channel` (`:269`). No production evidence recorded.
  - **A. Functionally covered:** `cds-main.htm`, `cds-group-{id}.htm`, `cds-group-{id}-page-{n}.htm`, `cds-item-{id}.htm`
    (also Gen 2's raw `/w2acd/cds.php`, `/w2acd/item.php`), `cds-code-{id}-{type}.htm`.
  - **B. Historical URL compatibility only — no route or redirect:** `cds-item-{id}-page-{n}.htm` (not generated by
    Gen 2), `cds-download-{id}.htm` (OLD `getit()` `Location: $W2ACD[link]`), `cds-mirror-{khid}-{id}.htm` (OLD
    `getmirror()` → `nuke_w2acd_mirror`).
  - **C. OLD-only, retired before Gen 2 — do not restore:** `cds-comments-{id}.htm` (OLD `Comments.php` `reply()` /
    `CreateTopic()` insert), `cds-comments.htm` (`file=comments`, lower case vs `Comments.php`), `cds-friend-{id}.htm`
    (`friend.php` `FriendSend()` / `SendStory()` `mail()`), `cds-friend.htm` (no `op` → no case, blank).
  - **D. Previous owner decisions, unchanged:** `cds-news.htm` — **excluded (#74)**; `cds-series-{id}.grx` — **deferred
    LOW (#92**, IF-044).
- **Functional parity (Gen 2 and Laravel):** main CD listing; group listing and pagination; CD detail pages with
  metadata and images (images only when `hidden = 0`); «روابط الاسطوانة» — direct external links from the comma-separated
  `link` / `cd` columns (Gen 2 `list_w2acd_mirrors()`, `w2acd/functions.php:132`; Laravel `W2acdItem::mirrorLinks()`);
  embed codes (`cds-code`, Laravel). No player, comments or send-to-friend in either; no `onclick` / form markup on the
  Laravel CD page. External CD files were not downloaded or verified.
- **Local data (read-only):** 39 CDs (none hidden, none with an empty link), 3 groups, 75 direct link entries (all
  http(s); 44 iso, 25 php, 2 png, 2 no extension, 1 torrent, 1 rar), 29 CDs with multiple links, 0
  `nuke_w2acd_mirror` rows, 0 `nuke_w2acd_comments` rows. All 39 CDs reachable through their detail pages; nothing
  depends on the B / C URLs.
- **Historical workflow evidence:** OLD's real CD page (`Detailes()` → `detailes_cd.htm` / `detailes_cd_links.htm`)
  lists direct links and calls neither `Mirror()` nor `Comments()`; `cds-download` redirected to the raw `link` value, so
  a multi-link CD (29 of 39) produced an invalid destination; comment and send-to-friend links exist only in unused
  templates copied from Anasheed (`detailes.htm`, `user_audio.htm`, `user_vedio.htm`, via `modules.php?…`); OLD
  `SendStory()` built the `From` header from visitor input (header-injection concern); Gen 2 has no W2ACD comments or
  send-to-friend workflow, and no active Gen 2 code generates any B / C URL. Historical defects are not reproduced.
- **Unknowns:** production responses for the historical URLs; external inbound links; external CD file availability.
  The missing local `new_modules.php` is not taken as proof of production behaviour. The Gen 2 sidebar's `var-item-`
  links (`w2acd/functions.php:227-230`) are outside this family and not investigated.
- **Status:** **W2ACD HISTORICAL URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY — NO IMPLEMENTATION REQUIRED — CLOSED
  LOCALLY (2026-11-07).** Still deferred: `cds-series .grx` (#92). Still excluded: `cds-news` (#74).

---

## 119. HISTORICAL PER-AUTHOR PDF DOWNLOADS — HISTORICAL URL COMPATIBILITY ONLY — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only investigation of the "per-author dumps" item in #103 (D). Documentation only — no
application code, test, route, redirect, database, media-file, staging, commit, push, deployment or production change.
**Khotab functional parity (#60) stays CLOSED LOCALLY and is not duplicated here.** #102, #103, #113, #115–#118 and
all other closures unchanged; visitor parity remains OPEN; R1 deferred; production verification pending. No Laravel
functional gap against active Gen 2 behaviour.

- **Inventory and dispositions** (no production evidence recorded; none require authentication):
  1. `dumped-lectures-{author}.htm` — OLD `.htaccess:423` → `Dump_files_for_author()`
     (`modules/Dump_files/index.php:79`): HTML list of the author's `pdf > 0`, `hidden = 0` items, `pdf_time DESC`,
     ≤ 500. Gen 2 `:222` → the absent `new_modules.php`; no Gen 2 call site — **replaced by `khotab-pdf-{author}.htm`**
     (`khotab/dump.php:45`). **Historical URL only — no route, no redirect.**
  2. `authors-dump-files.htm` — OLD-only (`.htaccess:426` → `Authors_Dump_files()`, `:99`); no Gen 2 rule; it ran
     `UPDATE nuke_islamic_authors SET pdf = …` for every author on each GET. Same content in Gen 2 / Laravel via
     `khotab-pdf.htm`. **OLD-only — not restored; the GET-time database write is not reproduced.**
  3. `dumped-lectures.htm` — active Gen 2 (`khotab/dump.php`, latest 50; home link `home_functions.php:398`); Laravel
     `KhotabDumpController` (author links → `/khotab-pdf-{author}.htm`). **Covered — preserved.**
  4. `khotab-pdf.htm` — active Gen 2 author index (`:198` → `khotab/authors.php?op=pdf`); Laravel `khotab-{op}.htm`.
     **Covered — preserved.**
  5. `khotab-pdf-{author}.htm` — active Gen 2 per-author list (`:206` → `khotab/author.php?op=pdf` → `ListPDF()`,
     `khotab/functions.php:768-784`, hidden filtered, `pdf_time DESC`, no limit); Laravel `khotab-{op}-{author}.htm`.
     **Covered — preserved.**
  6. `khotab-item-pdf-{id}.htm` — active Gen 2 item download (IF-020); Laravel `KhotabItemController::downloadPdf()`
     (`pdf = pdf + 1`, redirect to the file; `/` when `pdf = 0`). **Covered — preserved.**
- **Static PDF model:** admin-uploaded transcript files at `media/pdf/{bucket}/{id}.pdf` (OLD `getpdf` and Laravel
  `downloadPdf` redirect to them). **No dynamic PDF generation** in OLD, Gen 2 or Laravel for this feature; none required.
- **Local data (read-only):** 1,154 items with `pdf > 0` (1,133 visible), 52 authors with visible PDFs (none hidden), largest
  author list 169 items (OLD's 500 cap never applies). All PDF items reachable via `/khotab-pdf-{author}.htm`,
  `/dumped-lectures.htm` and the item page.
- **Local media availability observation:** 1,132 of the 1,133 visible PDF files are present locally; **item 269113's file
  is missing locally** — a local media-availability observation, not a Laravel code regression, not claimed missing in
  production; no replacement file created, no record changed, no new finding opened.
- **Historical URL ambiguity (inherited from Gen 2):** OLD `khotab-pdf-{itemid}.htm` (`:378` → `getpdf`) was an item PDF
  download; in Gen 2 the earlier `:206` rule matches the same shape as the author PDF page (shadowing `:234`), and Laravel
  follows Gen 2. Not a Laravel regression; external inbound usage UNKNOWN; **no compatibility fix approved** (IF-020 note).
- **Unknowns:** production responses for the historical URLs; external inbound links; production availability of the
  PDF files.
- **Status:** **HISTORICAL PER-AUTHOR PDF DOWNLOADS — HISTORICAL URL COMPATIBILITY ONLY — NO IMPLEMENTATION REQUIRED —
  CLOSED LOCALLY (2026-11-07).**

---

## 120. FAQ HISTORICAL URL FAMILY — OLD-ONLY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only FAQ investigation. Documentation only — no application code, test, route, redirect,
database, staging, commit, push, deployment or production change. #102, #103, #108–#119 and all other closures
unchanged; visitor parity remains OPEN; R1 deferred; production verification pending. No Laravel functional gap against
the active Gen 2 baseline. **FAQ is not restored.**

- **Inventory — exactly two patterns** (no production evidence recorded; neither requires authentication):
  1. `faq.htm` — OLD `.htaccess:796` / Gen 2 `:376` → `new_modules.php?name=FAQ`; OLD `modules/FAQ/index.php` category
     index (`:71-90`). **OLD-only, retired — no route, no redirect.**
  2. `faq-{id_cat}-{categories}.htm` — OLD `:799` / Gen 2 `:377` → `…&myfaq=yes&id_cat=$1&categories=$2`; OLD
     `ShowFaq()` + `ShowFaqAll()` (`:25-69`), a category's questions and answers. **OLD-only, retired — no route, no
     redirect.**
- **OLD (supplementary, source recovered and inspected):** the stock read-only PHP-Nuke FAQ — category list
  (`nuke_faqcategories`, optional language filter), questions as in-page anchors, then answers (`nuke_faqanswer`) with
  «back to top» / «back to index» links. No search, pagination, question submission, visitor database write or email;
  admin side in `modules/FAQ/admin/`. No OLD page outside the module links to FAQ. Source defects (not reproduced): the
  index links via `modules.php?name=FAQ…` with an undefined `$catname` (empty `categories`, never the `faq-…` URL);
  answers are output with HTML allowed (admin-authored).
- **Gen 2 (primary):** the two RewriteRules exist, but there is no local FAQ module, handler, replacement page or active
  visitor link (the only "faq" matches are Metronic demo-template links to a non-existent `extra_faq.html` in
  `categories/ui_tree.html:920` / `vars_categories/ui_tree.html:920`, and third-party library comments). No production
  behaviour is inferred from the missing local handler.
- **Laravel:** no FAQ route, controller, view, navigation link or test — none needed.
- **Local data (read-only):** `nuke_faqcategories` 0 rows; `nuke_faqanswer` 0 rows; no orphans — no local FAQ content
  becomes unreachable. **Production table contents UNKNOWN** (not claimed empty).
- **Documentation correction:** `PROJECT-HANDOFF.md` listed FAQ (2) as "SOURCE UNRECOVERABLE"; a dated correction
  records that OLD's source (`modules/FAQ/index.php`) is recoverable and was inspected, and this closure (history kept).
- **Unknowns:** production responses for the two URLs; external inbound links; production FAQ table contents.
  Reconsider only on concrete production or inbound-link evidence, as a separate owner decision.
- **Status:** **FAQ HISTORICAL URL FAMILY — OLD-ONLY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED
  LOCALLY (2026-11-07).** No implementation required.

---

## 121. HELP HISTORICAL URL FAMILY — `help-{id}.htm` OLD-ONLY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only Help investigation. Documentation only — no application code, test, route, redirect,
database, staging, commit, push, deployment or production change. #102, #103, #108–#120 and all other closures
unchanged; visitor parity remains OPEN; R1 deferred; production verification pending. No Laravel functional gap against
the active Gen 2 baseline. **Contextual Help popups are not restored.**

- **Existing Help decisions (preserved, not reopened):** `help/about.php` — Wave 2 task 2.1 (`AboutController`,
  `/about`); `landing_page.htm` — Four-Route Migration Gap Audit (`routes/pages.php:30-45`, same `AboutController`, no
  redirect); `help/share.php` — Roadmap task 6.3, Business Confirmation #2 (placeholder banners approved), IF-046
  (`/share.htm`); raw `help/about.php` / `help/share.php` redirect to `/about` / `/share.htm`. #103 (D) listed
  `help (1)` — that one pattern is `help-{id}.htm`, classified here. Admin-side Help management (OLD
  `modules/help/admin/`) is outside this visitor investigation.
- **Inventory — two patterns under OLD `#Help`** (neither requires authentication; no production evidence recorded):
  1. `landing_page.htm` — OLD `.htaccess:907` / Gen 2 `:417` → `new_modules.php?name=help&op=about_page`; OLD
     `modules/help/index.php` `about_page()` (`:34-42`); Gen 2 `help/about.php`, linked from `header.php:346`
     («من نحن»). Laravel `routes/pages.php:45` → `AboutController`, nav `navigation.blade.php:121`.
     **ALREADY FUNCTIONALLY COVERED** — `StaticPagesTest`, `LegacyPageUrlRedirectsTest`, `ShareControllerTest` 23 / 23
     (77 assertions) at investigation time.
  2. `help-([0-9]*).htm` — OLD `.htaccess:904` / Gen 2 `:416` → `new_modules.php?name=help&id=$1`; OLD `help_page()`
     (`:21-33`). **OLD-ONLY, RETIRED — no route, no redirect, no replacement.**
- **OLD popup functionality (supplementary, source inspected):** contextual «شرح» popups — about 35 OLD templates carry
  `<a class="help" href="help-NN.htm" data-fancybox-type="iframe">` (ids 12–15, 18–23, 25–27; Khotab, Locations, Cat,
  Fatwa templates, e.g. `templates/Khotab/main_head.htm:6`, `modules/Cat/index.php:67`), bound by
  `templates/index/indexpage.htm:53` (fancybox iframe) and styled by `style.css:1180`. `help_page()` reads `nuke_help`
  by `intval($id)`, echoes the stored HTML as a fragment (no header / footer), then increments `hits`. No search,
  visitor-facing categories, forms, email, downloads, external redirects or authentication-dependent behaviour.
- **Gen 2 retirement evidence (primary):** the RewriteRule remains, but there is no local `help-*` handler (`help/` holds
  only `about.php` / `share.php`; `new_modules.php` absent), no active `help-NN.htm` link, no `class="help"` / «شرح»
  popup binding, and no replacement popup workflow. No production behaviour is inferred from the missing local handler.
- **Laravel:** no Help popup route, controller, view or workflow, and no active visitor dependency; nothing references
  `nuke_help` — matching Gen 2.
- **Local `nuke_help` data (read-only; the table is NOT empty):** 103 rows — Khotab 54 (26 empty `html`, 28 non-empty,
  up to 6,331 chars, last update 2014-02-02); Anasheed 49 (48 empty, 1 non-empty, ≤ 46 chars). The 13 ids referenced by
  OLD visitor templates are Khotab rows holding real Arabic contextual help text (410–5,030 chars). No active Gen 2 or
  Laravel visitor page references any row. **Stored data is preserved — not deleted or modified.** Production contents
  UNKNOWN (not assumed to match local).
- **Historical source defects (recorded, not reproduced):** (1) a visitor GET increments `hits` (`:31`); (2) the local
  `nuke_help` schema (`id, title, html, module, lastupdate`) has no `hits` column; (3) `$Item[html]` uses an unquoted
  array key; (4) raw admin-authored HTML is echoed without modern safeguards; (5) the RewriteRule's `([0-9]*)` accepts
  an empty id (`help-.htm` → id 0 → blank response).
- **Decision:** no new route, no redirect, no replacement implementation. Restoring contextual help in future requires a
  separate owner-approved content and UX decision.
- **Unknowns:** production responses for `help-*.htm`; external inbound links; production `nuke_help` contents.
  Reconsider only on concrete new evidence, as a separate owner decision.
- **Status:** **HELP HISTORICAL URL FAMILY — CLOSED LOCALLY (2026-11-07).** `landing_page.htm` ALREADY COVERED;
  `help-{id}.htm` OLD-ONLY RETIRED — HISTORICAL URL COMPATIBILITY ONLY. No implementation required.

---

## 122. HEDAYA_TABLE HISTORICAL URL FAMILY — ALREADY RESOLVED BY PRIOR OWNER DECISION — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only `hedaya_table` investigation. Documentation only — no application code, test, route,
redirect, database, media, staging, commit, push, deployment or production change. #102, #103, #108–#121 and all other
closures unchanged; visitor parity remains OPEN; R1 deferred; production verification pending. No Laravel functional gap
against the active Gen 2 baseline.

- **Decision boundary:** this is **not** a new business decision. Live rooms, FlashChat, any Zoom replacement and the
  weekly live-lesson schedule were already retired — Business Confirmation #4, its correction #14, IF-033, and the
  `chat_room.htm` owner-approved partial reconstruction (`resources/views/chat-room/index.blade.php:3-12`,
  `ChatRoomLessonController.php:24-34`; `PROJECT-HANDOFF.md:177` "Closed — no remaining scope"). This entry only
  classifies the three remaining historical URLs; none of those decisions, `ChatRoomLessonController` parity,
  recorded-lesson browsing or Locations (#114) is reopened. `more-hedaya.rss` stays with the RSS family; the OLD
  admin-only `get_hedaya_author_img` op is outside visitor scope.
- **Inventory — three patterns** (OLD `.htaccess:912 / 915 / 918`, Gen 2 `:420-422`, all →
  `new_modules.php?name=hedaya_table…`; none requires authentication; no production evidence recorded):
  1. `more-hedaya.htm` (`op=More_hedaya`) — OLD `modules/hedaya_table/index.php` `More_hedaya()` (`:27-86`) →
     `w2a_lessons_table()` (`:87+`): «الجدول الإسبوعي لغرفة الهداية», the next 7 days' `ls_show='1'` lessons (≤ 30),
     Hedaya / Masajed tabs switched by AJAX (OLD `js/inc.js:1321`, `op=Lessons_table&_room=…`); database reads only, no
     forms, search, pagination or email; linked from OLD `index.php:245`, `indexpage.htm:508`, `modules/Chat/index.php:36`.
     Gen 2: the schedule logic moved into `chat_room/` (`table.php`, `functions.php:373` `get_lessons_table()`,
     `list_today_lessons()` on `chat_room.htm`), still tied to the live rooms (joins `$chatdb` `room`, links
     `chat_{room_id}.htm`); the only `more-hedaya.htm` link (`chat_room/room.php:28`) is on a page no RewriteRule or
     include reaches; Gen 2 `inc.js:997` `load_room_lessons()` still posts to the missing `new_modules.php`. Laravel:
     intentionally omitted. **ALREADY OWNER-APPROVED RETIREMENT — no route, no redirect, no replacement, no AJAX
     replacement.**
  2. `update-hedaya-list.htm` (no `op` → `default` → `Update_hedaya_lessons()`, `:21-26`) — a visitor-callable
     maintenance endpoint: a malformed `UPDATE` then «welcome to Way2allah». No Gen 2 handler or call site found
     locally; no Laravel equivalent. **OLD-ONLY RETIRED MAINTENANCE ENDPOINT — no route, no redirect; the database write
     is not reproduced.**
  3. `hedaya_cache_files.htm` (`op=hedaya_cache_files`, switch `:360`) — the module's own function is commented out
     (`:344-349`); the real cache writer is a class method (`hedaya.wait.class.php:213`, run from OLD `header.php:205-207`
     and `hedaya_wait_cron.php`). No Gen 2 handler or call site found locally; no Laravel equivalent. **OLD-ONLY RETIRED
     MAINTENANCE ENDPOINT — no route, no redirect; the cache writer is not restored.**
- **Local data (read-only; preserved — nothing modified or deleted):** `nuke_hedaya_lessons` 5 rows (4 hedaya,
  1 masajed; all `ls_show = 0`; 2019-04-15 – 2021-05-22; no future lessons; no orphan authors);
  `nuke_hedaya_waiting` 0; `nuke_hedaya_authors_images` 44. Production contents UNKNOWN.
- **Historical OLD source defects (recorded, not reproduced; not Laravel vulnerabilities):** (1) malformed SQL `UPDATE`
  in the maintenance endpoint (`SET ls_expired =  WHERE …`, undefined `ls` alias); (2) unsanitised `$_GET['_room']` in
  SQL (`:90`, `:104`); (3) unsanitised `$_POST['author_id']` in SQL (`:233-235`); (4) the routed cache operation calls
  an undefined global function; (5) visitor page views trigger database maintenance and file writes (`header.php:205-207`).
- **Decision:** no routes, redirects or replacements. Any future schedule feature needs a separate owner decision.
- **Unknowns:** production responses for the three URLs; external inbound links (e.g. bookmarks to `more-hedaya.htm`);
  production `nuke_hedaya_*` contents.
- **#103 reconciliation:** a dated note on #103 records that `hedaya_table (3)` is now classified and closed (history kept).
- **Status:** **HEDAYA_TABLE HISTORICAL URL FAMILY — ALREADY RESOLVED BY PRIOR OWNER DECISION — CLOSED LOCALLY
  (2026-11-07).** No application changes required.

---

## 123. HOME-FILE HISTORICAL URL FAMILY — OLD-ONLY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only home-file investigation. Documentation only — no application code, test, route,
redirect, database, media, staging, commit, push, deployment or production change. #102, #103, #108–#122 and all other
closures unchanged; visitor parity remains OPEN; R1 deferred; production verification pending. No Laravel functional gap
against the active Gen 2 baseline. **The OLD functionality is not restored.**

- **Existing coverage:** #103 (D) listed `home-file (1)` as unclassified; plan `old-admin-master-execution-plan.md:3685`
  covers only the admin side (admin branch unreachable — `WHERE hf_show='yes'`; NO_OLD_PUBLIC_ADMIN_CONTROLS), not
  reopened; `resources/views/home.blade.php:12-15` records `w2a_index_files()` («رسائل دعوية») as dead in
  `new_content.php` and not reproduced (an implementation finding, now confirmed by this owner decision).
- **URL — one pattern** (no authentication; no production evidence recorded): `home-file-([0-9]*).htm` — OLD
  `.htaccess:921` / Gen 2 `:423` → `new_modules.php?name=home_files&op=home_file&hf_id=$1`. **OLD-ONLY RETIRED — no
  route, no redirect, no replacement.**
- **OLD (supplementary, source inspected):** `modules/home_files/index.php` `Show_HomeFile_details()` (`:62-105`) — a
  database-driven homepage-file detail page: title, description, audio and thumbnail, «المواد المتعلقة» (related
  materials, `nuke_home_files_mawad`) and «ملفات أخرى» (ten other recent files). No forms, downloads, redirects,
  database writes or email. Linked from the OLD homepage block «رسائل دعوية» (`way2allah_functions.php:1243`), a
  hardcoded `home-file-33.htm` (`indexpage.htm:601`) and the «ملفات أخرى» list. `More_home_files()` (`:22-61`) has no
  identified RewriteRule and is not an active Gen 2 workflow — not implemented or exposed.
- **Gen 2 retirement evidence (primary):** the RewriteRule remains, but no local handler exists (`new_modules.php`
  absent); the only generator, `home_functions.php:182-205` `w2a_index_files()`, is commented out at both known call
  sites (`new_content.php:178`, `:189`); no active Gen 2 visitor navigation generates `home-file-*` URLs. No production
  behaviour is inferred from the missing local handler.
- **Laravel:** no home-file route, controller, view or visitor dependency; the retired «رسائل دعوية» block is
  intentionally not reproduced and is not restored. **This is not the homepage slider** — #98 stays OPEN and
  independent; the existing homepage implementation is unchanged.
- **Local data (read-only; preserved — nothing modified or deleted, media untouched):** `nuke_home_files` 1 row (id 166,
  `hf_show = 'yes'`, 2020-08-26, with audio file and thumbnail); `nuke_home_files_mawad` 1 row, no orphan. No active Gen 2
  or Laravel visitor page references either. Production contents UNKNOWN.
- **Historical OLD source issues (recorded, not reproduced; not Laravel vulnerabilities):** (1) `hf_id` concatenated
  into SQL without parameter binding (`:64`); (2) the RewriteRule's `([0-9]*)` allows an empty id; (3) the admin
  preview branch is unreachable under its own `hf_show='yes'` query; (4) `More_home_files()` uses `$db` / `$prefix`
  without declaring them global (broken variable scope).
- **Decision:** no route, redirect or replacement; any future homepage-files feature needs a separate owner decision.
- **Unknowns:** production response for `home-file-{id}.htm`; external inbound links; production `nuke_home_files` /
  `nuke_home_files_mawad` contents.
- **#103 reconciliation:** a dated note on #103 records that `home-file (1)` is closed (history kept).
- **Status:** **HOME-FILE HISTORICAL URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07).**
  No implementation required.

---

## 124. HOMEPAGE SLIDER (#98 FOLLOW-UP) — FUNCTIONALLY EQUIVALENT TO GEN 2 — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only homepage slider investigation. Documentation only — no application code, query, Blade,
JavaScript, CSS, test, route, redirect, database, media, staging, commit, push, deployment or production change. #102,
#103, #108–#123 and all other closures unchanged; home-file (#123) is a separate feature and not reopened; visitor parity
remains OPEN; R1 deferred; production verification pending. **No Laravel functional regression; no implementation
required.**

- **Original uncertainty (#98, repeated in #99, #103 (E), plan row "UNCLASSIFIED FOLLOW-UP — not investigated"):** OLD
  `index.php` selects `status = 1 AND website = 0`, Laravel `status = 1 AND website = 1`; not classified without
  investigation.
- **Gen 2 primary baseline:** `index.php:5-6` sets `$display_slider = true`; `header.php:532-534` includes `slider.php`
  only then (homepage only). `slider.php:3` — `SELECT * FROM nuke_7amalat WHERE status=1 AND website=1 ORDER BY
  order_index ASC LIMIT 10`; image `$image_url.$Item->image` (`w2a_config.php:64`), link `href = url`, nothing rendered
  when there are no rows (`:7`).
- **OLD (supplementary):** `old-project/index.php:173` — `WHERE status=1 AND website=0 ORDER BY order_index ASC LIMIT 8`
  (decodes `&amp;`; image, title and `des`, link in a new tab). `old-project/includes/index.php:88` runs the same query
  with no `status` / `website` filter — `website = 0` is not consistent even within OLD.
- **Laravel:** `HomeController:53` → `ContentListingService::homeSliderItems()` (`:2310-2319`) — `status = 1`,
  `website = 1`, `order_index` ascending, limit 10 — **matches Gen 2's selection criteria.** Images via
  `MediaUrl::asset()` (configured media base), link `href = url`, no slider markup when empty.
- **Interpretation:** the source establishes which `website` value each implementation uses, not its business meaning;
  no meaning is assigned to 0 or 1. The difference is recorded as an **OLD-to-Gen 2 behavioural change, not a Laravel
  regression.**
- **Local data (read-only):** `nuke_7amalat` 38 rows — `website = 0`: 9 inactive, 4 active; `website = 1`: 16 inactive,
  9 active. Selected ids — OLD `2694, 1055, 2568, 2602`; Gen 2 `2725, 2726, 2689, 2690, 2707, 2698, 2700, 2688, 2691`;
  Laravel identical to Gen 2 in the observed local results. All nine selected images exist in the inspected local media
  paths (Gen 2 `media/7amlat/`, Laravel `public/media/7amlat/`). Production data UNKNOWN.
- **Presentational differences (source review only — no claim of complete visual parity or browser verification; no UI
  change approved):** OLD — jQuery carousel, title and description, links in a new tab; Gen 2 — Revolution Slider, no
  caption, same tab; Laravel — custom carousel, title overlay, «عرض المزيد» call to action, arrows and dots,
  accessibility attributes, same tab.
- **Inherited ordering limitation:** several rows share an `order_index` (two at 1, three at 2); Gen 2 and Laravel both
  order by `order_index` with no tie-breaker, so order within ties is not guaranteed by SQL (the observed local order
  matched). Not a confirmed Laravel gap; no secondary sort without a separate decision.
- **Tests:** `HomeControllerTest` 29 / 29 (117 assertions) at investigation time — covers `website = 1` filtering,
  `status = 0` exclusion, `order_index` ordering, image paths, link destinations and the empty state. Not directly
  covered (optional regression-test improvements, not blocking, not created): LIMIT 10; same-tab links.
- **Unknowns:** production `nuke_7amalat` contents and the production homepage response; production media availability.
- **Reconciliation:** dated notes added to #98, #99 and #103 (E) (history kept).
- **Status:** **HOMEPAGE SLIDER (#98) — FUNCTIONALLY EQUIVALENT TO GEN 2 — CLOSED LOCALLY (2026-11-07).** No application
  changes required.

---

## 125. QUESTIONNAIRE HISTORICAL URL FAMILY — OLD STAFF HR SURVEY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)

Owner decision on the read-only questionnaire investigation. Documentation only — no application code, test, route,
redirect, database, email, form submission, data export, staging, commit, push, deployment or production change. #102,
#103, #108–#124 and all other closures unchanged; IF-050 and Wave 5 task 5.8 not reopened; visitor parity remains OPEN;
R1 deferred; production verification pending. No confirmed Laravel functional gap. **No route, redirect, replacement
form, email notification or database write endpoint.**

- **URL — one pattern:** `questionnaire.htm` — OLD `.htaccess` / Gen 2 `:426` → `new_modules.php?name=questionnaire`;
  GET renders the form, POST to the same URL (`action=""`) submits it. **OLD-ONLY RETIRED — historical URL compatibility
  only.**
- **OLD (supplementary, source recovered and inspected):** `modules/questionnaire/index.php` `drow_questionnaire()`
  (`:21-227`, 236 lines) and `themes/way2allah/templates/questionnaire/new-questionnaire-content.htm` (992 lines, one
  form) — an **internal staff HR and training-needs survey** («إستبيان موقع الطريق إلى الله»), with fixed hardcoded
  questions collecting personal and employment information; a database INSERT into `nuke_w2a_questionnaire`, an HTML
  email to the HR address, «تم الإرسال بنجاح» then a 4-second refresh to the homepage (failure / empty-name messages
  otherwise). No authentication required. OLD admin `modules/questionnaire/admin/` (list / show / delete over the same
  table) — admin side, outside this visitor investigation. `op=get_hedaya_author_img` calls a function this module does
  not define (dead). No active OLD or Gen 2 navigation link was identified locally.
- **Gen 2 baseline (primary):** the RewriteRule remains, but there is no local questionnaire module, handler, active
  navigation, include, submission endpoint or replacement workflow; the local Gen 2 `new_modules.php` is unavailable. No
  production behaviour is inferred from that fact alone.
- **Laravel:** no `questionnaire.htm` route, staff questionnaire form, submission handler or reader for
  `nuke_w2a_questionnaire` — consistent with the locally identified Gen 2 baseline. Not implemented.
- **Two separate systems — not equivalent:** **System A** (this entry) — the OLD staff questionnaire,
  `nuke_w2a_questionnaire`, not consumed by Laravel. **System B** — the wizard questionnaire, `estebian`, written by
  Gen 2 `wizard.php` / Laravel `/wizard.php` (IF-050) and read by `admin.questionnaire.*` (Wave 5 task 5.8). The Laravel
  admin viewer does **not** read `nuke_w2a_questionnaire`. `WizardController`, `QuestionnaireController`, their routes and
  screens, and both tables are unchanged.
- **Local data (read-only aggregates only; nothing printed, exported, modified or deleted):** `nuke_w2a_questionnaire` —
  65 responses, 2014-06-07 → 2014-09-24 (18 distinct days), 63 columns, all with a non-empty name, no foreign keys, no
  identified active Gen 2 or Laravel reader. `estebian` — 8 rows (System B, for distinction only). Production data
  UNKNOWN.
- **Historical OLD source issues (recorded, not reproduced; not Laravel vulnerabilities):** (1) SQL injection from raw
  `$_POST` interpolation (`:31-92`); (2) email header injection from the user-controlled email in `From:` / `Reply-To:`
  (`:204-206`; `strip_tags` does not remove CR/LF); (3) no CSRF protection; (4) no rate limiting; (5) no server-side
  validation beyond the `username` check; (6) unauthenticated database writes and outbound email; (7) potential stored
  XSS in OLD admin output — UNVERIFIED; (8) historical staff personal data remains stored locally. Data retention is a
  separate owner decision outside visitor parity; no deletion is authorized.
- **Documentation correction:** `PROJECT-HANDOFF.md` §2 (Wave C) recorded `questionnaire.htm` as "SOURCE UNRECOVERABLE"
  with "zero surviving code anywhere" (already partly corrected by #103 as a Gen 2-only finding); a dated #125 correction
  records the recovered OLD visitor handler, template, admin module and local table, and that source recovery does not
  imply active Gen 2 usage (history kept).
- **Unknowns:** production response for `questionnaire.htm`; historical direct-link usage (e.g. links shared with
  staff); production `nuke_w2a_questionnaire` contents.
- **#103 reconciliation:** a dated note on #103 records that `questionnaire (1)` is closed (history kept).
- **Status:** **QUESTIONNAIRE HISTORICAL URL FAMILY — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07).**
  No implementation required.

---

## 126. LOGIN / LOGOUT HISTORICAL URL FAMILY — PHP-NUKE `Your_Account` — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07); `vb5.php` BRIDGE — SEPARATE OPEN DEPENDENCY

Owner decision on the read-only login / logout investigation. Documentation only — no application code, test, route,
redirect, guard, cookie, session, database, `vb5.php`, staging, commit, push, deployment or production change; no login,
logout, session or cookie action performed. #101, #102, #103, #108–#125, Business Confirmation #5, ADR-0011, R14 and R21
unchanged; visitor parity remains OPEN; R1 deferred; production verification pending. **This entry closes only the two
historical URLs.**

- **Four distinct mechanisms — not combined:** (1) historical PHP-Nuke login / logout (`Your_Account`) — this entry;
  (2) vBulletin forum login / logout — owned by the forum, outside Laravel; (3) vBulletin session recognition — Laravel
  `VbulletinSessionGuard`; (4) admin authentication — `AdminGuard`, resolved by #101.
- **`login.htm`** — OLD `.htaccess` / Gen 2 `:360` → `new_modules.php?name=Your_Account`; OLD
  `modules/Your_Account/index.php` `main()` (`:22-157`: a guest gets an included vBulletin login form, a member an admin
  greeting / AdminCP links — "recorded, not controls", `decision-log.md:2024`, plan `:3687`) and POST `op=login`
  (`login()`, `:186-252`: checks the vBulletin `user` table, writes the FlashChat `users` row, sets a 30-day PHP-Nuke
  `user` cookie, redirects to `login.html`). Not an active Gen 2 visitor workflow in the inspected source.
  **HISTORICAL URL COMPATIBILITY ONLY — no route, no redirect, no replacement PHP-Nuke login.**
- **`logout.htm`** — Gen 2 `:361` → `…&op=logout`; OLD `logout()` (`:160-179`) cleared the PHP-Nuke `user` cookie and
  deleted the `nuke_session` / `nuke_bbsessions` rows, but did **not** terminate the vBulletin forum session (which
  `is_user()` reads), then refreshed to `login.htm`. **HISTORICAL URL COMPATIBILITY ONLY — no route, no redirect, no
  replacement PHP-Nuke logout.**
- **Gen 2 evidence (primary):** both RewriteRules remain; no local `Your_Account` handler or navigation for either URL
  was identified; the old header login entry is commented out (`header.php:229`, and it pointed to `page-login.html`).
  The locally missing `new_modules.php` does not establish the production response — production behaviour UNKNOWN.
  Gen 2 recognises members through `VB5()` / `is_user()` (`new_functions.php:35-72`, the `bbsessionhash` cookie against
  the vBulletin `session` / `user` tables), used e.g. for poll-comment member posting (`surveys/functions.php:26, :175`).
- **Laravel guard evidence:** no visitor login / logout route; default guard `vbulletin` (`config/auth.php:23, :42`);
  `VbulletinSessionGuard` reproduces Gen 2's session check (`bb_sessionhash` with `idhash` and the 15-minute activity
  window, or the `bb_userid` + salted `bb_password` pair) and is used by `PollCommentController:191`,
  `MobileController:300` and `DownloadRejectionRecorder:94`. **Source-level implementation equivalence was established;
  the vBulletin database is unavailable locally, so end-to-end production integration was NOT verified.** R14 / R21
  pre-deployment verification items are preserved; visitor authentication is not claimed production-verified.
- **Separate OPEN dependency — Gen 2 root `vb5.php`:** the cross-login bridge reached from the `admincp/w2a_vb_login.php`
  form (`:66`); it looks up `nuke_users`, has the `nuke_users` password check commented out (`:251-255`), calls the
  vBulletin API `login2` (which verifies credentials and sets the forum cookies) and redirects to the forum home or the
  vBulletin admin panel; it bootstraps a vBulletin 5 install outside this codebase. Business Confirmation #5 requires
  preserving the legacy cross-login behaviour during migration (hardening deferred); Laravel does not route `/vb5.php`;
  admin login is `AdminGuard` (#101). Whether production serves `vb5.php` outside the Laravel application is UNKNOWN.
  **Not a confirmed Laravel regression, not retired, no route implemented** — recorded as plan register **R25**
  (OPEN_OWNER_DECISION; blocks production deployment, not local closure), cross-referencing Business Confirmation #5,
  #101, R14 and R21. Its disposition requires a separate investigation and owner decision.
- **Historical security notes (recorded, not reproduced; not Laravel vulnerabilities):** OLD — the PHP-Nuke cookie
  carried the password hash; PHP-Nuke logout did not invalidate vBulletin sessions; unsafe redirect handling (unescaped
  `$redirect` in the logout meta refresh); legacy credential handling (raw `$_POST['currentpassword']` interpolated into
  the FlashChat `users` write). Gen 2 observations — `vb5.php` disables the `nuke_users` password check, with vBulletin
  `login2` performing credential verification; `vb5.php` disables CSRF protection; hard-coded database credentials were
  identified in its source (not reproduced); some historical admin-visibility checks trust raw `bbuserid` cookies
  (admin visibility already closed, not reopened). Laravel's guard validates the session hash or the salted password
  cookie rather than trusting `bb_userid` alone. Any credential rotation or remediation requires a separate authorized
  process; `vb5.php` (which carries an existing uncommitted local change) is not modified.
- **Local data (read-only, aggregates / column names only):** `nuke_users` 30,632 rows (not read by Laravel);
  `nuke_session` 1; `nuke_bbsessions` absent; `users` 146,393 (`user_id`, `user_name` locally; usernames read by
  `SurveyController`); `nuke_authors` 48; the vBulletin connection is unreachable locally. No hashes, tokens, cookies or
  emails queried or printed.
- **Unknowns:** production responses for `login.htm` / `logout.htm`; whether production serves `vb5.php` and who links
  to it; production vBulletin cookie domain / salt and guard integration (R14 / R21); production `nuke_users` contents.
- **#103 reconciliation:** a dated note on #103 records that `login / logout (2)` is closed (history kept).
- **Status:** **`login.htm` — CLOSED LOCALLY; `logout.htm` — CLOSED LOCALLY; HISTORICAL URL COMPATIBILITY ONLY
  (2026-11-07). `vb5.php` — SEPARATE OPEN DEPENDENCY (R25).**
- **[Clarification 2026-11-07, R25 investigation (documentation only, no new decision): source-level evidence accepted —
  classification E (production evidence required), provisional default A (bridge legacy-served), not finally approved;
  R25 stays OPEN as a production deployment blocker. The "source-level implementation equivalence" of
  `VbulletinSessionGuard` above does not establish end-to-end cookie compatibility: a production-dependent cookie-name
  (`bb` prefix → `bbsessionhash` vs the guard's `bb_sessionhash`) and remember-me-scheme risk is recorded against
  R14 / R21 (plan §30.5 R25 cookie-compatibility note). The committed Gen 2 `vb5.php` still contains
  `var_dump($nukeUser)`; only the existing uncommitted local change removes it. Original text kept.]**

---

## 127. FLASH XML HISTORICAL URL FAMILY — `var-news.xml`, `khotab-news.xml` — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)

Owner decision on the read-only Flash XML investigation. Documentation only — no application code, test, route, redirect,
XML endpoint, database, Flash execution, staging, commit, push, deployment or production change. #102, #103, #108–#126,
#113 (RSS — already distinguishes Flash XML from RSS, unchanged), R14, R21 and R25 unchanged; visitor parity remains
OPEN; R1 deferred; production verification pending. **No XML compatibility endpoint, no Flash player restoration.**
Flash XML is not RSS.

- **`var-news.xml`** — OLD `.htaccess:206` / Gen 2 `:149` → `new_modules.php?name=flashxml&op=Anasheed`; OLD
  `modules/flashxml/index.php` `ListAnasheed()` (`:11-28`): `nuke_anasheed_anasheed` ⨝ `nuke_anasheed_advanced`,
  `ORDER BY lastvisit DESC LIMIT 20`, no `hidden` filter; an XML-like `<ut_response><video_list><video>…` feed.
  Historical consumer: `recentplayer.swf?x=var-news.xml`, embedded in OLD `themes/way2allah/style/index2.php:430-446`,
  which has no identified active include. **HISTORICAL URL COMPATIBILITY ONLY — no route, no redirect.**
- **`khotab-news.xml`** — OLD `:209` / Gen 2 `:150` → `…&op=Khotab`; `ListVideo()` (`:31-45`): `nuke_islamic_khotab` ⨝
  `nuke_islamic_advanced`, `thumbnail != '' AND newslist = '1'`, `ORDER BY lastvisit DESC LIMIT 20`, no `hidden`
  filter; the same XML-like format. No active OLD consumer identified. **HISTORICAL URL COMPATIBILITY ONLY — no route,
  no redirect.**
- **Gen 2 baseline (primary):** both RewriteRules remain; the inspected local source has no Flash XML handler, SWF embed,
  JavaScript or template consumer, or navigation link. The locally unavailable `new_modules.php` does not establish
  production endpoint behaviour.
- **Anasheed functional replacement (content coverage, not format preservation):** Gen 2 replaced the Flash
  recently-viewed presentation with the HTML homepage block «تشاهدون الآن» (`new_content.php:628-638`); Laravel
  reproduces it via `ContentListingService::homeTrendingAnasheed()` (`:2344-2360`) used by `HomeController:52`, tested in
  `ContentListingServiceTest:567+` — the corresponding Anasheed join, `lastvisit` ordering and LIMIT 16. This is
  **functional content coverage**, not preservation of the XML format or the Flash player. Existing homepage widget
  decisions (incl. #98 Anasheed A6) are not reopened.
- **Khotab feed — retired, distinct:** the OLD Khotab Flash feed has no identified active Gen 2 consumer or functional
  replacement, and Laravel does not reproduce it — an OLD-only feed retired under the inspected Gen 2 baseline. Laravel
  implements **no** equivalent Khotab Flash feed. No confirmed active Gen 2 functional gap.
- **Source recovery:** recoverable — `old-project/modules/flashxml/index.php` (132 lines; `ListAnasheed()`,
  `ListVideo()`, the unrouted `Author` / `AuthorVideo` / `Group` / `Series` operations and the broken `MoreVideo` /
  `MoreAudio`; XML-like response generation) plus `admin/`. Recovering OLD source does not imply active Gen 2
  functionality. `PROJECT-HANDOFF.md` §8 ("flashxml (2) … SOURCE UNRECOVERABLE") receives a dated correction (history
  and the existing #103 correction kept).
- **Local data (read-only aggregates; nothing modified or exported):** Anasheed join 11,579 rows; Khotab
  `newslist = 1` 285,319 rows, of which 5,794 also have a thumbnail and an advanced row. Production data UNKNOWN.
- **Historical OLD source issues (recorded, not reproduced; not Laravel vulnerabilities):** (1) XML values not escaped
  (`&`, `<`, `"` break the XML / allow injection); (2) mixed encoding (`utf8_encode` wrapper, `convert_charset` items);
  (3) no explicit XML `Content-Type`; (4) hidden item titles may be exposed (no `hidden` filter); (5) some unrelated
  `flashxml` operations are broken (`ListItems()` called with the wrong signature; `ListVideo()` reads an undefined
  `$Anasheed`); (6) no caching.
- **Unknowns:** production responses for both URLs; external consumers (cached SWF embeds, feed readers); production
  table contents.
- **#103 reconciliation:** a dated note on #103 records that `flashxml (2)` is closed (history kept).
- **Status:** **`var-news.xml` — CLOSED LOCALLY; `khotab-news.xml` — CLOSED LOCALLY; HISTORICAL URL COMPATIBILITY
  ONLY (2026-11-08).** No implementation required. R25 remains OPEN.

---

## 128. NASHR-HEDAYAH HISTORICAL URL — OLD PICASA ALBUM BROWSER (`gal2`) — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08); LEGACY `gal2` HELPER FILES — OPEN SECURITY ITEM (R26)

Owner decision on the read-only `nashr-hedayah` investigation. Documentation only — no application code, OLD code,
test, route, redirect, Picasa integration, compatibility endpoint, database, server configuration, staging, commit,
push, deployment or production change. #102, #103, #107 (Gallery ZIP — remains CLOSED, not reopened), #108–#127, R25
(still OPEN) unchanged; visitor parity remains OPEN; R1 deferred; production verification pending.

- **Owner clarification:** OLD PHP-Nuke will be permanently retired after the completed Laravel application is deployed;
  no migration development time is spent repairing retired OLD functionality. It is **not** assumed that OLD has
  already been removed from production.
- **URL:** `nashr-hedayah.htm` — OLD `.htaccess:786` / Gen 2 `:370` → `new_modules.php?name=gal2` (GET, no parameters).
  **HISTORICAL URL COMPATIBILITY ONLY — no route, no redirect, no Picasa integration, no compatibility endpoint.**
- **OLD source:** `old-project/modules/gal2/index.php` (67 lines) — a client-side Picasa Web Albums browser titled
  «الألبوم الدعوي»: site header, Picasa Web Integrator (`./gallery/pwi-0.2.js`) with old jQuery plugins, a Google
  Analytics snippet, and an empty `#pic_container` («يتم عرض الألبومات هنا»). The required `./gallery/` assets are missing
  from the expected OLD path (the only `pwi-0.2.js` copy is inside a WordPress plugin), and Picasa Web Albums is
  discontinued. No database query, write, upload or email. Its `eregi()` guard is fatal on PHP 7+. `index.htm` is an
  unused standalone template.
- **Gen 2 baseline (primary):** the RewriteRule remains; no `gal2` handler, navigation, homepage or footer link,
  JavaScript consumer, template reference or form was identified locally. The missing local `new_modules.php` does not
  establish production behaviour.
- **Laravel:** no `nashr-hedayah.htm` route — intentional under this classification. Laravel preserves the **separate**
  Gen 2 local gallery (`gallery.htm`, `gallery-{album}.htm`, `albumimg-download-{image}.htm`,
  `download-album-{album}.htm`; Gallery ZIP closed under #107) — **not** a direct replacement for the Picasa page.
- **Local data (read-only):** `gal2` uses no tables; the separate gallery has `nuke_albums` 84, `nuke_albums_images`
  2,522, `nuke_gallery_albums_zipped_files` 2,783; OLD admin-only `nuke_default_picasa_album` 1 row (not read by Laravel).
- **Legacy-only security findings (OLD PHP-Nuke; NOT Laravel vulnerabilities):** (1) `modules/gal2/loader.php` calls
  unrestricted `readfile()` on the user-controlled `$_GET['file']` — potential arbitrary local file disclosure if
  publicly reachable; (2) `modules/gal2/__loader.php` places `$_GET['file']` into inline JavaScript without escaping —
  potential reflected XSS if publicly reachable. Neither helper is called by the module; the inspected local Gen 2 and
  Laravel trees do not contain them. Production exposure UNKNOWN. Not repaired, not ported, no replacement endpoint, no
  OLD or Laravel code change. **Not resolved by the planned migration:** tracked as plan register **R26 — OPEN,
  PRODUCTION EXPOSURE UNKNOWN**; closure evidence = verified public inaccessibility or removal of both files. If they are
  publicly reachable before cutover, that is a separate current-production security concern requiring prompt
  owner-controlled mitigation, not deferred to the migration. Independent of visitor parity.
- **Production cutover requirement (added to plan register R13, deploy-time checks):** verify that (1) OLD PHP-Nuke is no
  longer publicly served after the Laravel cutover; (2) OLD `modules/gal2/loader.php` and (3) `modules/gal2/__loader.php`
  are not publicly accessible; (4) old web roots, aliases, virtual hosts and static-file mappings do not expose retired
  PHP-Nuke files; (5) Laravel does not serve or copy these helper files. No production testing or server change now.
- **Unknowns:** production response for `nashr-hedayah.htm`; external inbound links; whether any OLD tree (including
  `modules/gal2/`) is still publicly served; the intended Picasa user / albums.
- **Reconciliation:** dated notes on #103 (the album ZIP / `nashr-hedayah` grouping) and #107 (status wording) (history
  kept).
- **Status:** **`nashr-hedayah.htm` — CLOSED LOCALLY — HISTORICAL URL COMPATIBILITY ONLY (2026-11-08). Gallery ZIP #107 —
  REMAINS CLOSED. Legacy `gal2` helper findings — OPEN, PRODUCTION VERIFICATION REQUIRED (R26).**

---

## 129. LIVE-CHANNEL-ADMIN HISTORICAL URL — PUBLIC OLD LIVE-TV PAGE WITH A DISABLED PREVIEW FLAG — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)

Owner decision on the read-only `live-channel-admin.htm` investigation. Documentation only — no application code, test,
route, redirect, replacement endpoint, administrative-functionality restoration, database, staging, commit, push,
deployment or production change. #102, #103, #107, #108–#128, the closed live-stream work (Wave 3, IF-007 / IF-009 /
IF-010) unchanged; R25 and R26 still OPEN; visitor parity remains OPEN; R1 deferred; production verification pending.

- **URL:** `live-channel-admin.htm` — OLD `.htaccess:730` / Gen 2 `:338` → `new_modules.php?name=live&isadmin=true`.
  **HISTORICAL URL COMPATIBILITY ONLY — no route, no redirect, no replacement.**
- **OLD implementation:** `old-project/modules/live/index.php` (78 lines) — the only (default) operation invokes
  `activate($cid)`, which renders the **public** OLD live-TV page: reads `nuke_live` by `intval($cid)` (title and the
  stored `ch_script` channel embed), a welcome block, the TV area, «أكثر القنوات مشاهد», the visible-channel list
  (`get_channels_as_list()`, `ch_status = 'show'`, `ch_order`) and further public widgets. No database writes, forms,
  email or administrative actions. Local `nuke_live`: 13 rows, 9 marked `show` (local counts only).
- **`isadmin` clarification:** `isadmin=true` was **not** an authentication or authorization mechanism — it was a
  historical preview switch for an under-construction page, used only in a conditional block that is commented out
  (`:21-27`), so it has no effect in the inspected OLD source. The URL exposed no separate administrative workflow; OLD
  administration was handled through `admin.php` (`modules/live/admin/`). **Not an admin endpoint.**
- **Gen 2 baseline (primary):** active live-stream functionality moved to `live-stream/live-stream.php`
  (`live-stream.htm`, `:336`) and `live-stream/live-channel.php` (`live-channel-{id}.htm`, `:337`); the admin-named URL
  remains only as a RewriteRule. No active navigation, template, form or JavaScript consumer identified. The missing local
  `new_modules.php` does not establish production behaviour.
- **Laravel:** implements the Gen 2 live-stream visitor workflows — `live-stream.htm`, `live-channel-{channel}.htm`,
  `live-stream/featured`, `live-stream/live.php` — on `nuke_sat_channels` (as Gen 2 `live-stream/functions.php:6, :44`),
  not OLD `nuke_live`. `nuke_live` is not ported for historical URL compatibility; closed live-stream functionality is not
  reopened.
- **[Clarification 2026-11-08, #133: `live-stream/live.php` is a redirect to `/live-stream/featured`, which reproduces Gen 2
  `live.php` (hardcoded channel 51) — not a separate workflow. Original wording kept.]**
- **OLD-only security observations (not Laravel vulnerabilities; no general audit):** (1) the client-controlled `isadmin`
  flag was not authorization; (2) OLD renders admin-authored `ch_script` embed content raw; (3) OLD uses PHP-Nuke template
  evaluation (`eval(IncMe(...))`). No SQL injection (`intval`) or write operation identified in this handler.
- **Unknowns:** production response for `live-channel-admin.htm`; external inbound links / bookmarks; production
  `nuke_live` contents.
- **Inventory status:** with #129, all currently identified historical URL families have an owner disposition. **This
  does not mean visitor parity is fully closed.**
- **[Clarification 2026-11-08, #130–#132: the inventory statement above was premature — `stats`, `noor` and
  `alQuran`, listed by #102 but omitted from #103's (D) inventory, were still unresolved. They are now closed locally
  under #130, #131 and #132 (historical URL compatibility only); with those decisions the statement holds. Original
  wording kept.]**
- **#103 reconciliation:** a dated note on #103 corrects the "live admin URL" label (history kept).
- **Status:** **`live-channel-admin.htm` — CLOSED LOCALLY — HISTORICAL URL COMPATIBILITY ONLY (2026-11-08).** No
  implementation required. Visitor parity OPEN; R1 DEFERRED; R25 OPEN; R26 OPEN.

---

## 130. STATS HISTORICAL URLS — `stats.htm`, `stats-{n}.htm` — OLD-ONLY READ-ONLY STATISTICS PAGE — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)

Owner decision (2026-11-08) on the read-only investigation of the three OLD-only URL families listed by #102 but
omitted from #103's (D) inventory. Documentation only — no application code, test, route, redirect, replacement
endpoint, restoration of OLD functionality, database, media, server-configuration, staging, commit, push, deployment
or production change. #102, #103, #108–#129 unchanged except for the dated notes recorded below; visitor parity
remains OPEN; R1 deferred; R25 OPEN; R26 OPEN; production verification pending. **The OLD pages are not inferred to
be inaccessible in production.**

- **URLs:** `stats.htm` (OLD `.htaccess:246` → `new_modules.php?name=stats`) and `stats-{n}.htm` (`:249` →
  `new_modules.php?name=stats&stat=N`). **HISTORICAL URL COMPATIBILITY ONLY — no route, no redirect, no replacement.**
- **OLD implementation:** `old-project/modules/stats/` — `index.php` dispatches on `intval($stat)`: 0 / 1 → `op1.php`,
  Google Charts (legacy `https://www.google.com/jsapi` loader) of item counts by content section (Khotab video /
  audio, five Anasheed departments, Fatwa); 2 → `op2.php`, a chart of hard-coded placeholder values (2010 / 2020 /
  2030), not real data; any other value → an empty page. Database: read-only `SELECT id` row counts. No writes,
  forms or email.
- **Reachability evidence:** no active OLD or Gen 2 link identified (direct URL only). The local module registry
  marks `stats` inactive (`nuke_modules.active = 0`, local data); OLD `new_modules.php` — owner-verified identical to
  production on 2026-10-28 (#64 evidence) — does not check `active`. Production `nuke_modules` values and the
  production `modules/stats/` files were not verified.
- **Gen 2 baseline (primary):** no rule, handler or link. Gen 2's `stats/` directory is an unrelated amCharts vendor
  bundle (coverage review §1.19), not this module.
- **Laravel:** no equivalent; none required. No Gen 2 visitor parity gap.
- **Unknowns:** production response for both URLs; inbound links / bookmarks.
- **Status:** **`stats.htm` / `stats-{n}.htm` — CLOSED LOCALLY — HISTORICAL URL COMPATIBILITY ONLY (2026-11-08).** No
  implementation required.

---

## 131. NOOR HISTORICAL URL — `noor.htm` — OLD «نور البيان» RELEASE PAGE — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)

Owner decision (2026-11-08) on the read-only investigation of the three OLD-only URL families listed by #102 but
omitted from #103's (D) inventory. Documentation only — no application code, test, route, redirect, replacement
endpoint, restoration of OLD functionality, database, media, server-configuration, staging, commit, push, deployment
or production change. #102, #103, #108–#129 unchanged except for the dated notes recorded below; visitor parity
remains OPEN; R1 deferred; R25 OPEN; R26 OPEN; production verification pending. **The OLD pages are not inferred to
be inaccessible in production.**

- **URL:** `noor.htm` (OLD `.htaccess:696` → `new_modules.php?name=noor`). **HISTORICAL URL COMPATIBILITY ONLY — no
  route, no redirect, no replacement.**
- **OLD implementation:** `old-project/modules/noor/index.php` («نور البيان») — prints a banner image and renders a
  historical release template `modules/noor/tmp/{cd}.w2a` (`cd` cast to an integer, default `index`) through `eval`;
  a missing template shows «هذا الإصدار غير موجود بقواعد البيانات». Its secondary operation `op=getid` (read-only
  `nuke_w2a_cds.link` by `intval($id)`; no pretty URL) is not a reason to recreate the page. No writes, forms or
  email.
- **Local state:** the `modules/noor/tmp/` template directory is missing locally — **not** taken as evidence that
  production lacks it; the page's actual production content is unknown.
- **Navigation:** OLD's active theme (`Default_Theme = way2allah`) linked `/noor.htm` («طريقة نور البيان») in
  «أقسام خاصة» (`themes/way2allah/header.php:372`). Gen 2 `header.php:318` keeps the same menu with this link
  **commented out**; Laravel's navigation follows Gen 2.
- **Gen 2 baseline (primary):** no rule, handler or active link — intentional removal from the Gen 2 site.
- **Laravel:** no equivalent; none required. No Gen 2 visitor parity gap.
- **Unknowns:** production response; production template / image files; inbound links / bookmarks.
- **Status:** **`noor.htm` — CLOSED LOCALLY — HISTORICAL URL COMPATIBILITY ONLY (2026-11-08).** No implementation
  required.

---

## 132. ALQURAN HISTORICAL URL — `alQuran.htm` — OLD THIRD-PARTY QURAN READER — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08); OLD-ONLY SECURITY CONCERNS TRACKED UNDER R26

Owner decision (2026-11-08) on the read-only investigation of the three OLD-only URL families listed by #102 but
omitted from #103's (D) inventory. Documentation only — no application code, test, route, redirect, replacement
endpoint, restoration of OLD functionality, database, media, server-configuration, staging, commit, push, deployment
or production change. #102, #103, #108–#129 unchanged except for the dated notes recorded below; visitor parity
remains OPEN; R1 deferred; R25 OPEN; R26 OPEN; production verification pending. **The OLD pages are not inferred to
be inaccessible in production.**

- **URL:** `alQuran.htm` (OLD `.htaccess:699` → `new_modules.php?name=alQuran`). **HISTORICAL URL COMPATIBILITY ONLY —
  no route, no redirect, no replacement.** The owner explicitly approves **not rebuilding the OLD Quran reader** as
  part of this migration.
- **OLD implementation:** `old-project/modules/alQuran/` — a third-party PHP-Nuke Quran module (v1.1–1.5, 2003–2005):
  a 604-page Mushaf reader, surah / ayah navigation (`gosoorah`, `Show`), ayah search (`dosearch`), a surah index
  (`quranindex`), historical per-ayah WAV playback (`<embed>` / `<bgsound>`), Asbab al-Nuzul (`Asbab.php`, 89 static
  pages) and Murattal (`Murattal.php`, 16 static pages). Reads the `quran` table (6,237 local rows). No database
  writes, forms-to-storage or email found in the investigated visitor workflow. The pretty URL reaches the main
  screen; the other operations use query strings.
- **Navigation:** OLD's theme linked the reader («خدمات قرآنية», `themes/way2allah/header.php:385`; also
  `header-new.php`, `blocks/block-tarbawy.php`). Gen 2 `header.php:331` keeps the same menu with this link
  **commented out**; Laravel's navigation follows Gen 2.
- **Gen 2 baseline (primary):** no rule, handler or active link — intentional removal from the Gen 2 site.
- **Laravel:** no Quran reader. The Laravel `quran-memorization-application` page (an external form) is unrelated and
  is **not** a replacement. No Gen 2 visitor parity gap. `quran` table data preserved.
- **OLD-only security concerns (source level; NOT Laravel vulnerabilities; production exposure UNKNOWN; exploitation
  and public reachability NOT verified):** (1) potential SQL injection — `dosearch()` places the unescaped
  `$searchterm` (from request-variable extraction) into its SQL; (2) potential unsafe dynamic includes —
  `Murattal.php` (`include "Murattal/$tartil.html"`) and `Asbab.php` (`include "Asbab/$get.htm"`); (3) legacy
  `eregi()` guards in `Murattal.php` / `Asbab.php` — fatal on PHP 8, so their runtime behaviour depends on OLD
  production's PHP version. Tracked as **R26 sub-item R26-b** with cutover checks under **R13** (plan §54); OLD code
  not modified; R26 not closed.
- **Unknowns:** production response for `alQuran.htm` and its query-string operations; OLD production PHP version;
  production images / sound files; inbound links / bookmarks.
- **Status:** **`alQuran.htm` — CLOSED LOCALLY — HISTORICAL URL COMPATIBILITY ONLY (2026-11-08).** No implementation
  required. OLD-only alQuran security concerns — OPEN, PRODUCTION EXPOSURE UNKNOWN (R26-b).
- **Inventory status (#130–#132):** with these decisions, every currently identified historical URL family (#103's
  (D) inventory plus #102's `stats`, `noor`, `alQuran`) has an owner disposition. **Visitor parity is NOT thereby
  declared closed** — formal closure awaits owner approval of the final evidence.

---

## 133. VISITOR PARITY — CLOSED LOCALLY — OWNER-ACCEPTED (2026-11-08)

Owner confirmations and the final evidence reconciliation (read-only), recorded as documentation only — no application
code, test, route, database, migration, staging, commit, push, deployment or production change. **Local closure only:
production deployment is NOT approved by this decision.** Baseline unchanged: #102 (Gen 2 primary, OLD supplementary,
Laravel target), #103 (functional visitor behaviour, not every historical URL); all dispositions through #132 stand.

- **Owner manual verification (new):** (1) **Channel Author UI & Navigation** (`channel-{id}-{author}.htm`: two-column
  layout, Gen 2 heading / breadcrumb, sidebar profile / «اخترنا لك هذه المادة» / empty IF-012 boxes, full-width item
  rows, Gen 2 group / series author links) — **OWNER VERIFIED — CLOSED LOCALLY**; (2) **Live Stream**
  (`/live-stream.htm`, `/live-channel-{id}.htm`) — **OWNER VERIFIED — CLOSED LOCALLY**.
- **Owner-accepted on automated evidence (closure dispositions, not new manual tests):** #104 A / C (`modules.php` and
  radio GET 301s); Gallery; Telawah; Channels; Radio; Homepage — evidence as listed in the final visitor-parity evidence
  review (route reconciliation #103; module tests; earlier owner checks where recorded: #97, #99, #107, #117, IF-056;
  the 2026-08-22 source / live visual audit for `gallery*.htm`, `recite.htm`, `channels.htm`, `channel-{id}.htm`,
  `radio.htm`).
- **Reconciled notes:** **IF-012** — the two empty boxes are Gen 2 parity and stay empty by explicit owner decision
  (channel-author decision 2); the related «اخترنا لك هذه المادة» LOW gap is fixed and owner verified. **Historical
  `live.php`** — Gen 2 behaviour reproduced (`/live-stream/featured`, redirect from `/live-stream/live.php`); the old
  "purpose" question is a non-blocking historical note, not a parity gap. **`/live-stream/featured`** was not part of the
  owner's manual check (automated tests only).
- **HIGH / MEDIUM visitor-parity gaps: 0 open** — every confirmed one resolved and owner-verified (Advanced Search #86,
  Anasheed #88, Fatwa #90, W2ACD #92, hidden-content visibility #97, homepage H1 #99, IF-056, channel author).
- **LOW items — each with a documented owner decision:** fixed and verified — Anasheed comment form / send-to-friend
  form workflow (#105, #106), Gallery ZIP (#107), IF-056, channel-author recommendation and layout; owner-approved
  deferrals — G8 «new» badges, G9 empty-search message (#86); Anasheed ad slots 5 / 9 and «new» badges (#88); F1
  per-topic breadcrumbs, F2 last-topic sidebar (#90); W1 `cds-series .grx` (incl. its `modules.php` redirect, #104 B), W2
  ad slot 9 (#92); V2–V4 admin visibility of hidden content (#97); T1 Telawah `hidden` filter (#98 / #99); the Fatwa
  player «يمكنك تجربة جودة أخرى» wording (#116); obsolete media formats, no in-page player (#20); retired / not
  reproduced — V5 OLD visitor exposure (#97), radio POST operations (#104).
- **Recorded, non-blocking (not parity gaps):** IF-058 Fatwa `media_link` data quality (Gen 2 has the same data; a
  separate data task needs its own approval, #116); the channel series page-length UX observation (#111); the Gen 2
  Anasheed category channel-link observation (#112).
- **Historical URLs:** every identified family has an owner disposition — #103's (D) inventory (#107–#129) plus
  `stats`, `noor`, `alQuran` (#130–#132); #103 A–C (#104).
- **Stale statements reconciled (2026-11-08, dated notes, history kept):** #93 / #94 / #95 / #104 status lines; #129's
  `live.php` wording; `PROJECT-HANDOFF.md` §3 RSS row and §7 `media/` line; IF-057 heading, IF-026 escalation line and
  IF-012 notes (`implementation-findings.md`); coverage review §1.6 / §1.7 / §1.8 / §1.10; plan §54 rows. **Not changed
  (code / test files, outside documentation-only scope):** the stale comments at `routes/engagement.php:15, :33` and the
  "A–B" / "B" labels in `tests/Feature/Content/VisitorUrlCompatibilityTest.php` (radio is #104 C).
- **Production dependencies — all still OPEN, none marked complete:** #106 send-to-friend anti-abuse (Khotab, Anasheed and
  Fatwa endpoints: rate limiting, visitor-controlled From, unhandled mail failure); R1 release baseline; R8 migrations;
  R9 permission grants; R10 production super-admin; R11 package rebuild; R12 backup / rollback; R13 deploy-time checks incl.
  OLD retirement; R14 / R21 forum cookies and member posting; R25 `vb5.php` ownership; R26-a OLD `gal2` exposure; R26-b
  OLD `alQuran` exposure; production media / configuration (production `APP_URL`, public social images, `khotab_gifs`,
  `ext-zip`, writable ZIP cache, media path, `authors.txt`, upload permissions); R15–R20 after deployment.
- **Status:** **VISITOR PARITY — CLOSED LOCALLY — OWNER-ACCEPTED (2026-11-08).** Production deployment NOT approved; R1
  DEFERRED; R25 OPEN; R26 OPEN.

## 134. N4 ADMIN GRANT SAFETY & MIGRATION ROLLBACK HARDENING — CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING (2026-11-08)

Owner acceptance after the final acceptance review (read-only), recorded as documentation only — no application code,
test, migration, database, staging, commit, push, deployment or production change. **Local closure only: production
readiness is NOT complete and production deployment is NOT approved by this decision.** Implementation detail: plan
register row N4; operational detail: `PROJECT-HANDOFF.md` §10 "Production runbook — admin grants and migration rollback".

- **Implemented (local):** Laravel admin creation safeguard (`AdminStaffController::store()` — insert inside a `main`
  transaction, refuse and roll back when Spatie grants already exist under the new `nuke_authors.id`, fail closed,
  duplicate-username refusal, creation lock); read-only `php artisan admin:audit-grants [--json]` (exit 0 / 1 / 2 / 64);
  non-destructive `down()` for the four `2026_09_02` index migrations (`up()` unchanged); `down()` of the two October
  `main` tables refuses to drop a non-empty table. Tests: `AdminGrantSafetyTest` (15), `MigrationRollbackSafetyTest` (17);
  full suite 2,739 passed, 1 skipped, 0 failed; PHPStan level 5 clean; Pint clean on changed files.
- **Final acceptance review:** lock / transaction, migration rollback semantics and audit completeness — each **PASS
  WITH DOCUMENTED LIMITATION**; **no confirmed defects**.
- **Owner decisions:** the optional lock-ownership check before commit is **not** implemented; N4 is **not** reopened for
  the documented low-probability lease-expiry race.
- **Production prerequisites — all OPEN:** verify `nuke_authors` uses InnoDB; verify a shared cache store; run
  `admin:audit-grants` before and after R9 / R10; preserve the prohibition on production `migrate:rollback`,
  `migrate:reset`, `migrate:refresh` and `migrate:fresh`; retire legacy admin management per X9 before cutover.
- **Residual risks — accepted:** legacy (Gen 2 / OLD) admin creation bypasses the Laravel safeguards; session id reuse;
  rare cache-lock lease-expiry race; partial migration rollback across a batch; historical identity reuse cannot be
  detected without historical data.
- **Status:** **N4 — CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING.** R9 and R10 remain OPEN.

## 135. P0 SPLIT LAYOUT & MEDIA ROOTS — OWNER DECISIONS D1–D6 — IMPLEMENTED LOCALLY — PRODUCTION VERIFICATION PENDING (2026-11-09)

Owner decisions after the read-only P0 investigation (2026-11-08), implemented locally — no production access,
filesystem change, database write, migration, seeder, staging, commit, push or deployment. **Production readiness is
NOT complete and P0 is NOT production-verified.** Detail: plan register row N5; `docs/operations/split-layout-media-roots.md`.

- **D1 — explicit absolute media roots (Option A).** The five variables are required in production `.env`;
  `bootstrap/app.php` and `public/index.php` are not changed. Reason: `public_path()` is the web root only in HTTP;
  `config:cache` builds a fresh application without it.
- **D2 — read-only `php artisan media:check-roots`.**
- **D3 — production media is never moved, copied, deleted or overwritten automatically;** misplaced files are an
  owner-approved manual reconciliation.
- **D4 — S1 (CD image extension) and S2 (gallery download containment) fixed in this workstream.**
- **D5 — enforceable packaging safeguards:** `php artisan deploy:verify-package` and the canonical
  `deploy/split-layout/newapp-index.php`; the manual, owner-controlled procedure is kept.
- **D6 — `config:cache` is kept,** provided the five roots are explicitly configured and verified.
- **Also changed (owner-approved G2):** four visitor readers resolve media under the configured Khotab root;
  URLs and fallbacks unchanged. One existing test fixture moved accordingly (assertions unchanged).
- **Status:** **P0 — IMPLEMENTED LOCALLY — PRODUCTION VERIFICATION PENDING.**

## 136. P0 SPLIT LAYOUT & MEDIA ROOTS — CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING (2026-11-09)

Owner acceptance after the final acceptance review and the verifier correction, recorded as documentation only — no
application code, test, migration, database, staging, commit, push, deployment or production change. **Local closure
only: production readiness is NOT complete and production deployment is NOT approved by this decision.** Decision #135
(D1–D6) and all earlier history stand. Detail: plan register row N5; `docs/operations/split-layout-media-roots.md`;
`PROJECT-HANDOFF.md` §10 "P0 closure".

- **Final acceptance:** CD image content-type agreement and visitor media-root coverage — PASS WITH DOCUMENTED
  LIMITATION; deployment package safety — FAIL, then fixed. **All confirmed verifier defects fixed:** D1 unsafe ZIP
  entry names, D2 duplicate entries, D4 ZIP and staged-directory symlinks, absolute ZIP paths (now rejected), hidden
  NUL entry names (raw central-directory names cross-checked), fake ZIP directory markers; `--full` added for full
  releases. No further P0 implementation is required on currently confirmed findings.
- **Quality gates:** full suite 2,805 passed, 1 skipped, 0 failed (20,146 assertions); PHPStan level 5 — 0 errors;
  Pint clean.
- **Package finding — REJECTED FOR FUTURE DEPLOYMENT:** `way2allah-laravel-app-cpanel-no-ssh.zip` (outside the repository, `way2allah-migration/`; built 2026-09-14; SHA-256 `83109454dd62d6aade46a75a43b09c16c090ccd390f2a15e8a37f58622163126`, matching its manifest; 7 036 entries) — 6 entries fail
  `deploy:verify-package --full` (3 runtime autocomplete lists, 2 bootstrap cache files, 1 noncanonical front
  controller). Not modified or deleted; no replacement generated.
- **Accepted limitations:** ZIP64 archives are unsupported (reported as unreadable, exit 2). Extractor behaviour (cPanel File Manager) is not production-verified. Non-NUL control characters remain allowed in package entry names (escaped in output). The CD image MIME-agreement branch (`getimagesize()` vs finfo) has no deterministic regression test. Visitor page-level and writer-to-reader coverage gaps remain (readers are tested directly). CLI `public_path()` is intentionally not unified with HTTP `public_path()` (D1, Option A).
- **Production prerequisites — all OPEN:** Verify the five absolute media roots (`KHOTAB_MEDIA_ROOT`, `ANASHEED_MEDIA_ROOT`, `W2ACD_MEDIA_ROOT`, `W2ACD_IMAGE_ROOT`, `AUTOCOMPLETE_ROOT`). Verify directory ownership and permissions for the web PHP user. Verify the active split front controller (`newapp/index.php`). Inspect for misplaced media files (`laravel-app/public/`). Verify the config cache values. Run `php artisan media:check-roots --expect-web-root=…` (exit 0). Require `php artisan deploy:verify-package` for every new deployment package. Require `--full` for full releases. Confirm the extraction procedure (location `/home/USER/`, extractor behaviour). Reconcile any misplaced files only after explicit owner approval.
- **Status:** **P0 — CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING.**

## 137. TRUSTED PROXIES & HOSTS — TOPOLOGY-INDEPENDENT CORRECTIONS — OWNER DECISIONS — D2 FIXED LOCALLY; D1 OPEN (2026-11-09)

Owner decisions after the read-only Trusted Proxies & Hosts investigation, implemented locally — no production access,
configuration change, migration, schema change, staging, commit, push or deployment. **Production readiness is NOT
complete and deployment is NOT approved.** Detail: plan register row N6; `PROJECT-HANDOFF.md` §10.

- **Decision 1 — Trusted Proxies are not implemented yet** (no topology evidence); trusted hosts not enabled.
- **Decision 2 — forum authentication is not modified yet:** D1 (forum session IP binding differs from vBulletin)
  remains OPEN pending the production vBulletin `proxyiplist` / `ipcheck` facts and the topology.
- **Decision 3 — D2 fixed locally after checking every use of the stored IP:** Visitor comment IPs (`KhotabItemController::storeComment()`, `AnasheedItemController::storeComment()`) are fitted to the legacy `varchar(15)` columns (`nuke_islamic_comments.ip`, `nuke_anasheed_comments.ip`) — first 15 characters, as the AdminCP comment path already did — so an IPv6 visitor's comment no longer fails the strict-mode insert. The column is write-only attribution (no reader in Laravel, Gen 2 or OLD; not used for authentication, rate limiting, duplicate detection or any security decision); the country code is still computed from the full address. No schema change.
- **Decision 4 — legacy schema preserved** (no column change, no migration).
- **Decisions 5 / 6 — template only:** `.env.production.example` now carries `APP_FORCE_ROOT_URL=true` and `SESSION_SECURE_COOKIE=true` — TEMPLATE ONLY; not activated in production, which requires a verified https `APP_URL` and HTTPS service (owner-approved step).
- **Limitations:** Limitations: an IPv6 (or IPv4-mapped IPv6) address is stored truncated and not reversible, so stored comment IPs cannot identify an IPv6 visitor; GeoIP remains IPv4-only (`''` for IPv6, unchanged); other legacy IP columns of similar width (e.g. `nuke_poll_check.ip` varchar(20), not written by Laravel today) were not changed.
- **Open:** D1 (forum session IP binding differs from vBulletin: vBulletin derives its idhash IP from `CF-Connecting-IP` / the first public `X-Forwarded-For` entry / `REMOTE_ADDR`, or the proxy address when `proxyiplist` matches, with `4 - ipcheck` octets; Laravel uses `REMOTE_ADDR` with 3 octets) remains OPEN — forum authentication unchanged by owner decision. Trusted Proxy configuration remains OPEN (none configured; forwarded headers ignored); trusted hosts not enabled. Production topology remains UNKNOWN (proxy / CDN, HTTPS termination, origin access, vBulletin `proxyiplist` / `ipcheck`, IPv6 reachability).
- **Status:** **D2 — FIXED LOCALLY; D1, Trusted Proxies and production topology — OPEN.**

## 138. D1 FORUM SESSION IP BINDING — OPTION C — IMPLEMENTED LOCALLY — PRODUCTION CONFIGURATION UNVERIFIED (2026-11-09)

Owner decisions after the read-only D1 compatibility review, implemented locally — no production access, database
write, schema change, migration, Trusted Proxy / Trusted Host change, production `.env` change, staging, commit, push or
deployment. **Production readiness is NOT complete and deployment is NOT approved.** Supersedes the D1 "OPEN" status of
#137 for the local code only. Detail: plan register row N6; `PROJECT-HANDOFF.md` §10.

- **Finding behind it:** vBulletin accepts a session only when BOTH IP checks pass — `idhash` from ALT_IP and `session.host`
  against IPADDRESS (`session.php:299`, `:347`); Laravel used a REMOTE_ADDR-only idhash and had no host check.
- **OD1 — Option C approved and implemented:** `App\Domain\Identity\Support\VbulletinSessionBinding` reproduces the legacy vBulletin request / session source (local copy 5.5.5 / SIMPLE_VERSION 562): IPADDRESS / ALT_IP from raw `REMOTE_ADDR`, public `CF-Connecting-IP`, the first public `X-Forwarded-For` entry, the mirrored proxy list (exact or `*` prefix) and proxy header, the proxied swap, IPv6 canonicalisation, `ipSubstring` with `4 - ipcheck` parts and the `vBulletin` User-Agent fallback — never `request()->ip()`. `VbulletinSessionGuard` now requires, for the session-hash cookie: not expired (`cookietimeout`), `idhash` = md5(UA . ipSubstring(ALT_IP)), AND `session.host` matching IPADDRESS (vBulletin's second check, previously missing). Configuration: `VBULLETIN_IPCHECK` (default 1), `VBULLETIN_IPCHECK_ALLOW_UNBOUND` (default false; required for ipcheck=4), `VBULLETIN_COOKIE_TIMEOUT` (default 900), `VBULLETIN_PROXY_IP_LIST` / `VBULLETIN_PROXY_IP_HEADER` (default empty) — raw scalars, config:cache-safe; malformed or unsafe values (invalid ipcheck, unapproved ipcheck=4, `all` / `*`, malformed entries, invalid header name) make the session cookie fail closed with a logged reason (no secrets). Read-only guard (no `lastactivity` refresh); cookie handling, remember-me and missing-salt behaviour unchanged.
- **OD2 — `proxyiplist = all` refused** (and the equivalent bare `*`), fail closed with a logged, diagnosable reason;
  validation is never silently weakened. **ipcheck=4** is refused unless `VBULLETIN_IPCHECK_ALLOW_UNBOUND=true`;
  invalid `ipcheck` / `cookietimeout` values are refused.
- **OD3 — guard stays read-only** (no forum `lastactivity` refresh).
- **OD4 — remember-me unchanged;** R25 stays separate.
- **Verification:** Tests: `tests/Feature/Identity/VbulletinSessionBindingTest.php` (96) against `tests/Support/Fixtures/VbulletinIpGoldenVectors.php` — 28 golden vectors produced by executing the legacy vBulletin request constructor and IP utility (PHP 7.4 with its real traits and PHP 8.3, identical; no vBulletin code stored); existing guard fixtures gain vBulletin's `session.host` column (assertions unchanged); 12 mutation checks caught.
- **Limitations:** Deviations by design: `all` / `*` refused; ipcheck=4 needs explicit approval; the host comparison is strict (`===`) where vBulletin uses PHP's loose `==` (stricter, never weaker). Not verified: the production vBulletin version (headers 5.5.5, SIMPLE_VERSION 562), `ipcheck`, `cookietimeout`, `proxyiplist` / `proxyipheader`, and that the forum and Laravel see the same `REMOTE_ADDR` / forwarded headers.
- **Open:** Production configuration UNVERIFIED (defaults reproduce the previous behaviour); forum authentication production verification PENDING; Trusted Proxies remain OPEN (Laravel's `TrustProxies` is still unconfigured and no longer affects forum binding); R25 remember-me (vB5 sha224 token) remains SEPARATE; members browsing only Laravel pages still expire after `cookietimeout` (read-only guard, OD3).
- **Status:** **D1 — IMPLEMENTED AND TESTED LOCALLY — PRODUCTION CONFIGURATION UNVERIFIED.**

## 139. D1 FORUM SESSION IP BINDING — CLOSED LOCALLY — ACCEPTED WITH DOCUMENTED LIMITATIONS — PRODUCTION VERIFICATION PENDING (2026-11-09)

Owner acceptance after the D1 final acceptance review (verdict B — accept with documented limitations), recorded with
tests and documentation only — no application code, authentication behaviour, configuration, database, migration,
staging, commit, push, deployment or production change. **Local closure only: production readiness is NOT complete,
Trusted Proxies are NOT complete and deployment is NOT approved.** Decision #138 and all earlier history stand. Detail:
plan register row N6; `PROJECT-HANDOFF.md` §10 "D1 closure".

- **F1 — security precondition for proxy mode:** when `VBULLETIN_PROXY_IP_LIST` is non-empty, forum session security depends entirely on the proxy's header handling. A request from a listed proxy binds `idhash` to the proxy's own address (shared by every client) and `session.host` to the configured header — or, when that header is missing, multi-valued or invalid, to the client's `CF-Connecting-IP` / first public `X-Forwarded-For` entry (vBulletin's own fallback). Behind a proxy that does not overwrite the header and sanitise client headers, a stolen session cookie plus User-Agent satisfies BOTH checks (demonstrated by an adversarial test; identical in vBulletin). The default empty list is not affected. **If the conditions below cannot be verified, do not enable proxy mode.**
- **Preconditions before enabling proxy mode in production (all OPEN):** (1) Verify the actual proxy topology (which proxy / CDN sits in front of the origin). (2) Verify the configured proxy egress IPs (`VBULLETIN_PROXY_IP_LIST` holds exactly those addresses). (3) Verify the proxy overwrites the configured client-IP header (`VBULLETIN_PROXY_IP_HEADER`) with exactly one valid IP on every request. (4) Verify client-supplied `CF-Connecting-IP` and `X-Forwarded-For` cannot reach the origin without sanitisation (a missing or invalid configured header falls back to them). (5) Verify the origin cannot be reached through an unintended path that defeats the trust boundary. (6) Verify forum and Laravel requests see compatible network information (same vhost / proxy chain, same `REMOTE_ADDR` and headers). (7) Verify the actual vBulletin proxy settings (`Misc.proxyiplist` / `Misc.proxyipheader`).
- **F2 — wildcard breadth:** the literal `all` and bare `*` are refused, but broad wildcard lists (e.g. `1*, 2*, …`) still create excessive trust. Configure only verified proxy egress addresses.
- **Verification:** D1 closure tests: `VbulletinSessionBindingTest` now 140 tests / 393 assertions, adding 18 adversarial golden vectors (`tests/Support/Fixtures/VbulletinIpAdversarialVectors.php`, executed from the vBulletin source under PHP 7.4 and 8.3 — identical): multi-value / missing / invalid configured proxy header, CF-Connecting-IP and X-Forwarded-For fallbacks, the F1 spoofed-fallback acceptance under unsafe proxy handling (and its rejection when sent directly or with the default list), the ipcheck=2 numeric-prefix case (`1.10` vs `1.1`: vBulletin's loose `==` accepts, Laravel's strict `===` rejects — deliberate), IPv6 CF-Connecting-IP, uppercase IPv6 X-Forwarded-For, ULA / link-local filtering, IPv4 with port, CGNAT 100.64/10 (public, as in vBulletin), missing and whitespace REMOTE_ADDR, Trusted Proxies / Hosts still unconfigured, both checks mandatory with the default list. 7 further mutation checks caught. No production code changed. Existing security confirmed: default empty proxy list safe; both IP checks mandatory;
  invalid configurations fail closed; ipcheck=4 needs explicit opt-in; remember-me unchanged; no forum database writes;
  Trusted Proxies and Trusted Hosts unconfigured.
- **Accepted limitations: F1 (proxy mode safe only under the verified preconditions); F2 (wildcard breadth); strict `===` deviation for ipcheck=2 numeric prefixes (stricter, rare false logout); a missing REMOTE_ADDR yields empty addresses, as in vBulletin (never in HTTP); a refused configuration is logged only when a matching session cookie is presented, and on every such request; the approval flag accepts boolean spellings such as `yes` / `on`; production `session.host` width, PHP 7.2 filter equivalence (verified on 7.4.33 / 8.3.6) and vBulletin version / options unverified.**
- **Status:** **D1 — CLOSED LOCALLY — ACCEPTED WITH DOCUMENTED LIMITATIONS — PRODUCTION VERIFICATION PENDING.** Trusted Proxies remain OPEN; R25 remember-me remains separate.

---

## Summary

All 5 pre-Wave-4 decisions from `pre-wave-4-decision-support.md` were applied or formally deferred with a precise trigger before Wave 4 began. Decision #6 (`KhotabGroup`) and decision #7 (post-Wave-4 review fixes: `CommentPosted`, `GeoIpLookup`, `W2acdController` regression) were both made during/after Wave 4. Decision #8 (a third legacy-URL-preservation pattern, `pages/social.php`) was made during the post-Wave-4 gap-closure pass (`docs/reviews/gap-closure-action-plan.md`), alongside tasks 4.10 (`radio`) and 4.11 (`chat_room`'s lesson-browsing half) — neither of the latter two needed their own decision-log entry (no new cross-module pattern, per this log's own standing bar; see IF-031/IF-032/IF-033 in `implementation-findings.md` for what was found and decided there instead). AEP-1 (`ContentGroup` hierarchy extraction, `post-wave-4-cross-wave-architecture-review.md` §9) is explicitly deferred until Wave 5 and Wave 6 are both complete — not rejected, not forgotten. Full suite as of the gap-closure pass: 207/207 tests, stable across repeated runs. PHPStan: 0 errors at level 5, zero suppressions.
