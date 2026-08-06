# Pre-Wave 4 Implementation Review

**Scope:** everything built in Waves 0-3 (94 → 109 tests, 12 domain classes, 2 real content controllers, 6 static-page controllers, 3 shared services). **No code changed in this review** — findings only. Where a finding implies the Blueprint itself should change, it's presented as an Architecture Evolution Proposal per your instruction, not applied.

---

## 1. Architecture Drift

Compared against `00-master-migration-blueprint.md` §1 and §1.2's own documented internal domain structure.

### 1.1 `MediaPathResolver` is in `Support/`, not `Services/` — a literal deviation from the Blueprint's own example

**Confidence: High (direct text comparison).**

Blueprint §1.2 lists the internal structure of a domain and gives a concrete example:
> `Services/` # reusable, often read-oriented, cross-model orchestration (ContentListingService, **MediaPathResolver**)

Implementation has `app/Domain/Content/Support/MediaPathResolver.php` — a domain-local `Support/` folder that Blueprint §1.2's structure list never mentions at all. This wasn't a deliberate, documented choice; it happened in Wave 1 without cross-checking the Blueprint's own text.

**Is this actually wrong, though?** Arguably not. Blueprint's own definition of `Services/` is "cross-model orchestration" — `MediaPathResolver` doesn't orchestrate across models at all; it's a pure, stateless utility (arithmetic + string formatting). By Blueprint's own definition, `Support/` is a *better* fit than `Services/`, which suggests the drift may have been an accidental improvement, not a mistake. See §7.1 below for a proposal to resolve this properly rather than just moving the file to match stale text.

**Recommendation:** Do not silently move the file. Resolve via §7.1's proposal (formalize a domain-local `Support/` convention in the Blueprint) so the *next* pure-utility class has a documented place to go, rather than each domain reinventing this decision independently in Wave 4+.

### 1.2 Two undocumented-but-necessary folders appeared in every domain: `Providers/` and `Concerns/`

**Confidence: High.**

Every domain built so far (`Content`, `Admin`, `Identity`, `Pages`) has its own `Providers/` folder (`ContentServiceProvider`, `AdminServiceProvider`, `IdentityServiceProvider`, `PagesServiceProvider`) registering that domain's routes/events/middleware. `Content/Models/Concerns/TracksViews.php` uses the `Concerns/` convention Laravel's own framework uses for model traits. Neither appears in Blueprint §1.2's structure list.

Unlike §1.1, I don't think this is drift in any meaningful sense — Blueprint §1.2's list was illustrative, not exhaustive, and every domain needing its own wiring point is a mechanical Laravel necessity, not a design choice that could have gone another way. Still, three implementation waves independently reaching for the same two folder names in every domain is real evidence they belong in the documented structure, not left implicit.

**Recommendation:** Fold into §7.1's proposal — add `Providers/` and `Concerns/` to Blueprint §1.2's example structure as standard, expected subfolders.

### 1.3 No drift found in: domain boundaries, routing strategy, database connection strategy, event usage discipline

**Confidence: High (verified, not assumed).** Grepped `app/Domain/Content/` for any reference to `App\Domain\Admin` and vice versa — zero results, confirmed clean. Re-ran the Pest architecture-test suite (5 tests) — still green. Domain Events usage is still exactly the two cases the Wave 0 review settled on (`ContentViewed`, and `CommentPosted` still unbuilt/unneeded) — no creeping "event for everything" observed. Routing still has zero `op=`-style dispatch anywhere. Database access still goes through the same three named connections with no ad-hoc new connections introduced.

---

## 2. Over-Engineering

### 2.1 `ContentListingService` is 457 lines / 12 methods — watch, don't cut yet

**Confidence: Medium.**

This is the single largest class in the codebase by a wide margin (next largest is `ContentSidebarWidget` at 233 lines). Every one of its 12 methods is independently justified by a real, distinct, cited legacy query shape — this isn't speculative generality, it's the direct consequence of P-011 being confirmed *5 times independently* across khotab/categories/channels. I don't think splitting it today would improve anything: the methods don't share enough structure to factor further (that was the whole finding of Wave 1's design — no shared query-building helper exists here, unlike `ContentSidebarWidget`, because the shapes are genuinely too different), and splitting by content-type would recreate per-module duplication at the class level instead of the method level.

**What I'd actually do:** nothing now. Re-assess after Wave 4, specifically once khotab's *own* `ListGroup`/`ListSeries`/`ListKhotab` methods are wired into real controllers — if the class crosses roughly 600-700 lines with genuinely low cohesion between method groups, split by entity (`GroupListingQueries`/`SeriesListingQueries`/`KhotabItemListingQueries`) at that point, not preemptively.

### 2.2 The driver-aware `ORDER BY BINARY title` workaround is proportionate, not over-built

**Confidence: High.** One `private` method, one ternary, clearly commented with why SQLite can't run the real clause. This is the minimum necessary code to keep the test suite executing the real production SQL rather than a divergent approximation. Nothing to simplify here.

### 2.3 Nothing else in Waves 0-3 reads as over-engineered

Reviewed `AdminGuard`, `VbulletinSessionGuard`, `EnsureAdminHasRole`, `LegacyPasswordVerifier`, `RecordsView`/`TracksViews`, the two Wave 3 controllers — every one maps to a concrete, cited piece of legacy behavior or a real framework contract (e.g. `AdminGuard` implementing the full `StatefulGuard` interface, already reviewed and accepted in the Wave 1 code review as "correctly fulfilling a framework contract, not gratuitous"). No new candidates found this pass.

---

## 3. Under-Engineering

Two concrete, evidence-backed findings — both small, both safe to fix, both worth doing **before** Wave 4 multiplies them.

### 3.1 `findOrFail()` would replace 4 repeated `find() + abort_if(null, 404)` pairs

**Confidence: High.**

`LiveStreamController::show()`/`featured()` and `ChannelController::show()`/`showAuthor()` all repeat the same two-line shape:
```php
$model = Channel::find($id);
abort_if($model === null, 404);
```
Laravel's `Model::findOrFail()` already throws `ModelNotFoundException`, which the framework's default exception handler already converts to a 404 response automatically — this is standard, long-standing Laravel behavior, not a new dependency. The two-line pattern is strictly more code for an identical observable result.

**Recommendation:** Replace all 4 occurrences with `findOrFail()` before Wave 4 adds more controllers that would otherwise copy the longer pattern by example. Low risk — the existing 404 tests (`LiveStreamControllerTest`, `ChannelControllerTest`) already assert the externally-observable behavior (`assertNotFound()`), not the internal mechanism, so they'd continue to pass unchanged and would themselves prove the refactor safe.

### 3.2 `channels/show.blade.php` and `channels/author.blade.php` duplicate the groups/series/items rendering almost verbatim

**Confidence: High (direct file comparison).**

Both views render the same three `@foreach` blocks (groups → `khotab-group-{id}.htm`, series → `khotab-series-{id}.htm`, items → `khotab-item-{id}.htm`) with only minor per-view differences (whether an author link is shown alongside each row). This is real, current, already-duplicated markup — not a hypothetical future risk.

**Recommendation:** Extract a shared partial (e.g. `channels/_listing.blade.php`, accepting the three collections and a `$showAuthorLinks` flag) before Wave 4. Reasoning for doing it now rather than after Wave 4: khotab's own browse page (Wave 4) will need the *same* groups/series/items rendering shape a third time, and fixing the duplication after three copies exist is strictly more work than after two.

### 3.3 Nothing else rises to this bar

Checked `LiveStreamController` vs `ChannelController` for a shared base-controller case — both use `ContentListingService`/`ContentSidebarWidget` via injection and share the find-or-404 shape (fixed by §3.1 instead), but their actual actions don't share enough structure to justify a shared base class with only 2 real controllers to generalize from. Consistent with the discipline already applied in Wave 1 (`ContentListingService` itself wasn't extracted until *multiple* modules showed the *same* shape) — extracting a controller base class from 2 instances now would repeat the exact premature-abstraction mistake that discipline exists to prevent.

---

## 4. Shared Components Review

Every shared service, trait, and abstraction built so far, checked against "at least two real consumers, or another explicit justification."

| Component | Real consumers today | Justification if < 2 |
|---|---|---|
| `ContentListingService` | **1 real controller** (`ChannelController`, 3 of 12 methods). The other 9 methods (khotab/categories shapes) have zero controller callers yet. | **Explicitly planned sequencing, not premature extraction.** Blueprint §20 states shared services are built in Wave 1 *specifically before* the content modules that need them, "since otherwise it'd be built 3-4 times over." The 9 unused methods were built from real, already-read khotab/categories source in Wave 1 — not speculative design. Wave 4 is the wave that consumes them. Confidence: High that this is legitimate, not a violation — but flagging the raw number plainly rather than rounding it up to "justified" without evidence. |
| `ContentSidebarWidget` | **2 real controllers** (`LiveStreamController`, `ChannelController`) at the class level. At the method level: 5 of 11 methods (anasheed/w2acd/telawah pairs) have zero callers yet. | Same reasoning as above — planned-ahead, not accidental. Class-level justification is solid (2 real, live modules depend on it today); method-level utilization will catch up in Wave 4/5. |
| `MediaPathResolver` | **Zero real consumers.** Proven only by its own 5,000+-assertion test suite. | Explicitly named in the Roadmap as the single highest silent-failure-risk component, deliberately built and hardened in Wave 1 *ahead* of Wave 4's real usage, per the Roadmap's own "prototype first" flag on this exact component. Legitimate, but worth stating plainly that after 3 waves, this is still an entirely unproven-in-production piece of code. |
| `TracksViews` / `ContentViewed` / `RecordsView` | **1 real consumer** (`Channel::recordView()`, called from `LiveStreamController::show()`). | Built for P-014's 9+ eventual call sites (khotab/anasheed/w2acd items+mirrors), none of which exist until Wave 4. Same "planned ahead" pattern as the two services above — but genuinely 1 real caller today is worth naming rather than implying broader current adoption. |
| `EnsureAdminHasRole` | **Zero real route consumers** — `routes/admin.php` has no real routes yet (Wave 5). Only test-registered dummy routes exercise it. | Built in Wave 0/1 for Wave 5. Same pattern. |
| `LegacyPasswordVerifier` | **1 real consumer** (`AdminGuard`). | Different justification model than the others: extracted for testability/isolation of a security-critical code path (3 independent hash-format branches, each needing its own test matrix), not for deduplication across modules. A single-consumer extraction is appropriate here for that reason — flagged as legitimate on different grounds, not waved through by the same "planned ahead" logic. |
| `UrlMapRouteRegistrar` / `UrlMapServiceProvider` | **7 real rule entries** across 2 domains (Pages, Content). | Solidly justified — this is Blueprint §11's designated mechanism, not a discretionary extraction. |
| `App\Support\Permission\{Role,Permission}` | **2 real consumers** (`VbUser`, `AdminUser`) plus `RoleSeeder`. | Solidly justified — this is the fix for a real, previously-hit Eloquent connection-inheritance bug (Wave 1), not speculative. |
| `Tests\Support\InMemoryConnection` + `MainSchema`/`VbulletinSchema` | **10 test files.** | Solidly justified, previously reviewed and approved as an internal testing-infrastructure improvement, not re-litigated here. |

**Overall assessment (Confidence: Medium-High):** No component was extracted *speculatively* — every one traces to either real, already-confirmed legacy evidence or a real, previously-hit bug. But roughly half of the shared production components have thin-to-zero current consumer counts, and that thinness is currently indistinguishable from "extracted too early" without the Roadmap-sequencing context supplying the justification. **Recommendation:** no action needed now, but Wave 4's own report should explicitly confirm each of these components' consumer count actually grew as planned — if `ContentListingService`'s khotab-shape methods, `MediaPathResolver`, or `TracksViews`' additional call sites *don't* materialize in Wave 4 as expected, that would be the point to revisit whether they were genuinely justified.

---

## 5. Naming Review

### 5.1 `TracksViews`/`recordView()` is a slightly imprecise name now that it also covers channel "visits"

**Confidence: Medium.**

Originally named for P-014's hit-*counter* pattern on content items ("views"). Wave 3 extended it to `Channel::ch_visits`, which the legacy code itself calls a "visit," not a "view" — the column name, the Arabic UI label ("عدد الزيارات" — "number of visits"), and the concept (watching a live channel) all lean toward "visit" more than "view." This was flagged as a real (if minor) naming tension already in the Wave 1 code review, before the generalization even happened — Wave 3 makes it slightly more relevant, not less.

**Recommendation:** Not urgent — "view" is still a defensible umbrella term (watching a channel is a kind of viewing it), and renaming now touches 3 files (trait, listener, event) for a cosmetic gain. Worth a final naming decision once Wave 4 adds the *actual* P-014 content-item call sites (khotab/anasheed/w2acd) and it's clear whether "view" reads naturally across all real consumers, not just Channel's edge case.

### 5.2 Everything else reads clearly

Checked every class/method name introduced across 3 waves against what it actually does: `Channel::eligibleForLiveStream()`, `beamForDisplay()`, `viewCountColumn()`, `ContentListingService`'s 12 method names, `ContentSidebarWidget`'s 11, the 8 controller class names, all read as accurate, specific, and free of the "mode string"/generic-verb anti-patterns the Blueprint was explicitly designed to avoid. `ChannelController::show()` and `LiveStreamController::show()` both being named `show()` is normal Laravel convention (namespaced by controller), not a collision. No renaming recommended anywhere else.

---

## 6. Technical Debt Review

Consolidating the debt items logged across the Wave 0 review, Wave 2 report, and Wave 3 report, re-evaluated now against "before Wave 4" urgency specifically (Wave 4 being the largest, most business-critical wave changes the calculus for several of these).

| Item | Age | Recommendation |
|---|---|---|
| No PHPStan/Larastan configured | Since Wave 2, restated Wave 3 | **Address before Wave 4 starts.** Flagged twice already as "before Wave 4's larger surface area" — this *is* that wave. Deferring a third time means the largest, most complex wave in the whole migration ships with the least static verification of any wave so far. |
| No site chrome in `layouts.app` | Since Wave 2, restated Wave 3 | **Address before or very early in Wave 4.** Two waves of chrome-less pages was acceptable for zero-risk statics and a small module; shipping khotab/categories — the modules that justify the whole migration's business value — without real navigation is a much larger user-facing gap. |
| `findOrFail()` simplification (§3.1) | New this review | **Address before Wave 4** — small, safe, and Wave 4's controllers will otherwise copy the longer pattern by example. |
| `channels/*.blade.php` listing duplication (§3.2) | New this review | **Address before Wave 4** — same reasoning, a third copy is coming otherwise. |
| `MediaPathResolver` `Services/` vs `Support/` placement (§1.1/§7.1) | New this review | **Resolve the Blueprint question before Wave 4** (see §7.1) — Wave 4 is exactly where more pure-utility classes are likely to appear (e.g. anything KhotabItem-thumbnail-related), and they need a documented place to go. |
| `randomitems()` widget not reproduced (`channels/author.php`) | Wave 3 | **Cannot be addressed before Wave 4** — genuinely blocked on a real content-item model. No action possible until Wave 4 itself. |
| `ORDER BY BINARY title` driver-aware compromise | Wave 3 | **Safe to leave.** Low risk, already documented, only verifiable against real MySQL once Infrastructure Confirmation #1 lands — revisit then, not before. |
| `IF-008` (missing `embedded=true` param) | Wave 2 | **Safe to leave indefinitely** — cosmetic, non-blocking, already logged as a future business question. |

---

## 7. Blueprint Challenge

Per your instruction: assuming the Blueprint may be wrong, challenged with implementation evidence rather than defended. Two findings below are substantial enough to warrant a formal Architecture Evolution Proposal rather than a silent fix; the rest are considered-and-dismissed.

### 7.1 PROPOSAL: Drop the `VbUserReader` interface requirement — `VbUser` itself already achieves what it was for

**The duplicated production behavior:** N/A — this isn't a duplication finding, it's a Blueprint-vs-implementation gap.

**What the Blueprint says:** §1.4 states the *one* exception to "no Repository pattern" is "a narrow `VbUserReader` interface over data this application doesn't own" for the Identity/vBulletin boundary — explicitly justified as "a genuine anti-corruption-layer case." ADR-0011 references this same design.

**Evidence from implementation:** No `VbUserReader` class or interface exists anywhere in the codebase (confirmed by search). `VbulletinSessionGuard` and `RoleSeeder` — the 2 real consumers of vBulletin user data built so far — both call `VbUser::on('vbulletin')->find(...)` directly against the Eloquent model, with no interface indirection. This has worked without incident across Waves 0-3: `VbUser` already self-enforces the anti-corruption boundary Blueprint wanted the interface for, by overriding `save()`/`delete()` to throw (Wave 0) — the model itself refuses to be misused as a write path, which is the actual property the `VbUserReader` interface was meant to guarantee.

**Why this looks like a reusable-concept / architecture question, not a bug:** an interface's value is swappability or test-doubling. Nothing in 3 waves has needed either — tests already fake the whole `vbulletin` connection via SQLite rather than mocking a reader interface, which is arguably more realistic testing anyway (it exercises real query logic, not a hand-written fake).

**Proposed shared component / change:** None — the proposal is to *remove* a planned-but-unbuilt abstraction, not add one.

**Pros of dropping it:** One less layer between the Guard/Seeder and the data; the self-defending model already provides the safety property; less code to maintain for zero lost protection.

**Cons of dropping it:** If a second, genuinely different consumer of vBulletin data later needs to swap implementations (e.g., an API-based reader instead of direct DB access), retrofitting an interface after several direct callers exist is more work than having built it up front. Also a philosophical inconsistency risk: Blueprint explicitly reserved this as the *one* Repository-pattern exception; dropping it removes the last trace of that pattern from the codebase entirely, which is a bigger statement than it sounds ("we don't use Repository, anywhere, period" vs. "we don't use Repository, except this one deliberate case").

**Migration impact:** None to existing code either way — no interface exists to remove, and no existing call site would change under either resolution. This is purely a decision about whether to build the interface retroactively before more consumers accumulate, or formally drop the requirement.

**Classification: Blueprint Refinement.** Confidence: Medium — the evidence that direct access has worked fine is solid (High), but whether a future consumer will need the swappability the interface would have provided is unknowable from 3 waves of evidence (Low-Medium). Recommend the business/architecture owner make this call explicitly rather than defaulting either way.

### 7.2 PROPOSAL: Formalize `Providers/` and `Concerns/` as standard per-domain subfolders; resolve `Services/` vs. domain-local `Support/` for pure utilities

**Evidence:** §1.2/§1.1 above — 3 waves, 4 domains, `Providers/` appeared in every single one; `Concerns/` and a domain-local `Support/` each appeared once, both for defensible reasons (a model trait, a pure non-orchestrating utility).

**Proposed change:** Add `Providers/` to Blueprint §1.2's example structure unconditionally (every domain needs one). For `Concerns/` and domain-local `Support/`: formalize both as *optional* subfolders, with an explicit rule distinguishing them from `Services/` — e.g. "`Services/`: orchestrates across models or external calls; domain-local `Support/`: pure functions/utilities with no model dependency at all." This would resolve `MediaPathResolver`'s placement question by turning it from an accidental deviation into a correctly-categorized example.

**Pros:** Documentation matches reality; the next pure-utility class (plausible in Wave 4, e.g. anything around khotab thumbnail/duration formatting) has a clear, precedented place to go instead of each domain re-deciding.

**Cons:** Minor — one more folder-naming rule to remember; a redundant "why not just put it in Services/" question every new contributor might ask once, until documented.

**Migration impact:** Zero — no code moves under this proposal, only the Blueprint's own documentation of what already exists.

**Classification: Blueprint Refinement.** Confidence: High — this is a low-stakes, purely descriptive change with no behavioral impact and clear supporting evidence from every domain built so far.

### 7.3 Considered and dismissed: "Channel now has real methods — does this violate 'plain Eloquent model, no aggregate ceremony'?"

Checked against the whole-Blueprint simplification pass's ruling that `Channel` (among others) should be "a plain Eloquent model — no aggregate-boundary analysis needed." `Channel` now has a scope, a relationship, and two behavior methods (`recordView()`, `beamForDisplay()`). Concluded this is **not** a violation: "no aggregate ceremony" was about not spending design effort on formal consistency-boundary/invariant analysis (Channel still has no owned children, no complex invariants) — it was never a ban on a plain model having ordinary scopes and accessor-shaped methods. No proposal warranted. Confidence: High.

### 7.4 Considered and dismissed: "Should Deptrac be added now, given real domain boundaries exist to violate?"

Checked for actual cross-domain violations across `app/Domain/Content` ↔ `app/Domain/Admin` — none found, Pest arch tests still catch what they're supposed to (re-verified, not assumed, in §1.3). No evidence yet that Pest arch tests are insufficient. Blueprint Part III's deferral stands. Confidence: High.

### 7.5 Considered and dismissed: "Should caching be added now that real read-heavy controllers exist?"

`LiveStreamController`/`ChannelController` both run multiple uncached queries per request today. This is exactly the shape Blueprint §13 anticipated caching for "once real controllers exist" — that condition is now true. However, Infrastructure Confirmations #1/#2 (hosting environment, Redis availability) are still unresolved, and Blueprint's own reasoning for deferring was specifically infrastructure-dependent, not usage-dependent. Real controllers existing doesn't resolve the infrastructure unknown. No new evidence to override the deferral — restating it, not challenging it. Confidence: High.

---

## Summary of Concrete Recommendations Before Wave 4

1. Replace the 4 `find()` + `abort_if(null, 404)` pairs with `findOrFail()` (§3.1).
2. Extract a shared Blade partial for `channels/show.blade.php`/`author.blade.php`'s duplicated listing markup (§3.2).
3. Add PHPStan/Larastan (§6) — deferred twice already; Wave 4 is the wave that deferral was pointing at.
4. Build real site chrome in `layouts.app`, or explicitly decide to defer it a third time with a stated reason (§6).
5. Decide §7.1 (drop or build `VbUserReader`) and §7.2 (formalize `Providers/`/`Concerns/`/domain-local `Support/`) — both are quick decisions, not large work, but both are currently silent gaps between documentation and reality that will only get more expensive to reconcile the longer Wave 4 builds on top of them.

Everything else reviewed — domain boundaries, event usage, the bulk of shared-component extraction, naming, the overall service/controller shape — held up under scrutiny with no changes recommended.
