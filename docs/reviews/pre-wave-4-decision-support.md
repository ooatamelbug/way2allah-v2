# Pre-Wave 4 Decision Support

Follow-up to `pre-wave-4-implementation-review.md`. Decision-support only — no code or Blueprint changes made here.

---

## 1. MediaPathResolver location

**Blueprint's own definitions, quoted exactly:**
- `Services/` (§1.2): "reusable, **often read-oriented, cross-model orchestration** (ContentListingService, MediaPathResolver)"
- Top-level `app/Support/` (§1): "Framework-adjacent, cross-domain infrastructure: caching helpers, the legacy-URL compatibility layer, shared traits" — Blueprint never defines a *domain-local* `Support/` at all; it only names the top-level one.

**Why `Support` fits MediaPathResolver better, by the Blueprint's own words:** `Services/`'s definition has two components — "reusable" and "cross-model orchestration." `MediaPathResolver` satisfies the first and fails the second: it takes an integer and a string, returns a string, and touches zero Eloquent models, zero DB connections, zero framework facades. `ContentListingService` (joins 4+ tables) and `ContentSidebarWidget` (queries a table, applies filters) both genuinely orchestrate; `MediaPathResolver` is pure arithmetic and string formatting. It's categorically a different kind of class from Blueprint's own example pairing.

**Is this a Blueprint correction, an implementation correction, or a documentation inconsistency?**

It's a **documentation inconsistency within the Blueprint itself** — the definition of `Services/` and the Blueprint's own example of what belongs there (naming `MediaPathResolver`) don't agree, independent of anything implementation did. That inconsistency existed the moment §1.2 was written; implementation just happened to land on the side that matches the *definition* rather than the *example*.

Given that framing, the correct resolution is a **Blueprint correction** (fix the example, and add a short, explicit definition of a domain-local `Support/` for pure, model-free utilities), not an implementation correction. Moving the file to match an example that contradicts the Blueprint's own defining sentence would resolve the inconsistency in the wrong direction.

**Confidence: High** that this is the right diagnosis. Not yet acted on — this is the same §7.2 proposal from the prior review, restated here with the specific reasoning you asked for.

---

## 2. VbUserReader proposal

**Original motivation (Blueprint §1.4 / ADR-0011 discussion):** a narrow interface over vBulletin's user data, justified as an anti-corruption-layer case — the value being that if vBulletin's schema ever changes, only one implementation needs to change, not every call site that touches it.

**New evidence found while writing this document, not in the prior review:** `VbulletinSessionGuard` doesn't only touch `VbUser` — it *also* queries `DB::connection('vbulletin')->table('session')` directly, bypassing any model or abstraction entirely (confirmed by re-reading the file just now). This means the anti-corruption boundary is **already porous today**, independent of whether `VbUserReader` exists: vBulletin schema knowledge (the `session` table's exact columns: `sessionhash`, `userid`, `idhash`, `lastactivity`) already leaks directly into the Guard, and a `VbUserReader` as Blueprint originally scoped it (user-focused) wouldn't have covered this — session lookups are a separate concern from user lookups.

**Has the motivation disappeared, or has implementation just not reached the scenario yet?**

Both, in different proportions:
- The motivation ("protect against a vendor schema change propagating pain") hasn't disappeared *in principle* — it's a real category of risk, and it's specifically the one case Blueprint carved out as different in kind from ordinary "wait for repeated duplication" extraction, because the value of an anti-corruption layer isn't about consumer *count*, it's about isolating blast radius from a system this application doesn't control.
- But the *scenario* that would prove its value — an actual vBulletin schema change, or enough call sites that a change would be genuinely costly to fix in multiple places — hasn't occurred. Only 2 call sites exist (`VbulletinSessionGuard`, `RoleSeeder`), both simple, both stable across 3 waves.
- The new evidence above adds a real complication: even if `VbUserReader` had been built exactly as Blueprint described it, it would only be *partially* effective today, since session-table access already bypasses the user-table boundary it was scoped to protect.

**Recommendation: neither keep the requirement as-is (build it now) nor formally drop it — narrow and defer it, with an explicit trigger.**

- Don't build it now: 2 stable call sites is weak evidence for swappability value, and building an interface today would be the same premature-abstraction pattern avoided everywhere else in this project.
- Don't formally delete the requirement either: this is the one case in the whole Blueprint where the justification model is genuinely different (external-system isolation, not deduplication), and that reasoning hasn't been invalidated, only unexercised.
- If it's ever built, its scope should be revisited to cover session lookups, not just user lookups — the original "user-only" framing is already narrower than what `VbulletinSessionGuard` actually needs to isolate.
- **Trigger to revisit (revised per decision 2026-08-04 — evidence-based, not a consumer count):** real implementation evidence of an actual need — additional vBulletin integration whose access pattern genuinely differs from the two existing call sites, observed vBulletin schema instability, or repeated boundary leakage (raw vBulletin table/column names appearing in more places the way `session`'s columns already do in `VbulletinSessionGuard`). A bare 3rd caller that looks just like the existing two is not, by itself, sufficient — the original "3 consumers" framing conflated headcount with evidence of actual pain, which is the same mistake this whole review process exists to avoid making elsewhere.

**Confidence: Medium.** High confidence the current 2-consumer state is real and stable; low-to-medium confidence on what a 3rd consumer's needs would actually look like, which is exactly why this is a "defer with a trigger" recommendation rather than a firm keep/drop.

---

## 3. Shared components inventory (Waves 0-3)

| Component | Current production consumers | Expected future consumers | Would I extract it today, knowing what I know now? |
|---|---|---|---|
| `LegacyPasswordVerifier` | 1 (`AdminGuard`) | Unlikely to grow — scoped to admin login specifically | **Yes.** Extracted for testability/isolation of 3 branchy hash-format checks, not deduplication — that justification doesn't depend on consumer count and has already paid off (4 focused unit tests). |
| `App\Support\Permission\{Role,Permission}` | 2 (`VbUser`, `AdminUser`) + `RoleSeeder` | Stable at 2 — both identity models are already built | **Yes, without hesitation.** Fixes a real bug found in production code (Eloquent connection inheritance), not a speculative extraction. |
| `UrlMapRouteRegistrar` / `UrlMapServiceProvider` | 7 rule entries across 2 domains | Grows every wave — this is the designated Blueprint §11 mechanism | **Yes.** Not discretionary; it's the specified solution to a named architectural requirement. |
| `EnsureAdminHasRole` | 0 real routes (`routes/admin.php` is empty; only test-registered dummy routes exercise it) | All of Wave 5's admin routes | **Yes, but this is the weakest-evidenced extraction in the inventory, and worth naming as such.** It prevents a real footgun (Spatie's bare `role:X` middleware silently checking the wrong guard) rather than fixing an already-hit bug, and unlike the others above, waiting until Wave 5 to build it would also have been entirely reasonable. Low cost (41 lines) is what tips this to "still yes," not strength of evidence. |
| `Channel` model (scope/relationship/behavior methods) | 3 (`LiveStreamController`, `ChannelController`, `ContentSidebarWidget::mostViewedLiveChannels()`) | Grows in Wave 4 (khotab/series reference it by ID) | **Yes.** Real, current, multi-consumer usage. |
| `Satellite` model | 1 (via `Channel::satellite()`, rendered in `live-stream/show.blade.php`) | Unlikely to grow much — small, stable table | **Yes, low-stakes either way** — 26 lines, matches the established "every confirmed table gets a model" convention, cleaner than raw joins at the one call site it has. |
| `ContentListingService` | 1 controller (`ChannelController`), using 3 of 12 methods | Wave 4 consumes the other 9 (khotab/categories shapes, already built from real evidence) | **Yes** — this is Blueprint §20's explicit planned sequencing (shared services before the modules that need them), not premature extraction. Flagging again here that 9 of 12 methods are still unconsumed, same honesty as the prior review. |
| `ContentSidebarWidget` | 2 controllers, 6 of 11 methods | Wave 4/5 consumes the remaining 5 (anasheed/w2acd/telawah pairs) | **Yes**, same reasoning. |
| `MediaPathResolver` | **0** | Wave 4 (khotab thumbnail resolution) | **Yes — but the justification I'd give today is different from "it'll have many consumers."** The honest justification is that this is a correctness-critical, silently-fails-if-wrong calculation (a wrong bucket path doesn't error, it just quietly serves the wrong or no thumbnail across the *entire* existing media library) — the kind of thing worth proving correct in isolation, exhaustively, before it's load-bearing, independent of how many callers exist yet. That's the same justification model as `LegacyPasswordVerifier` (isolate + heavily test something risky), just with higher stakes named explicitly in the Blueprint itself. |
| `TracksViews` / `ContentViewed` / `RecordsView` | 1 (`Channel::recordView()`) | Wave 4 adds 8+ more (khotab/anasheed/w2acd items and mirrors — P-014's original, already-confirmed call sites) | **Yes.** Built from real, cited legacy code (not speculative), and the low current count reflects planned sequencing, same as the two services above. |
| `Tests\Support\InMemoryConnection` + `MainSchema`/`VbulletinSchema` | 10 test files | Grows every wave | **Yes** — already validated in a prior turn, not re-litigated here. |

**If I had to name the one component in this inventory that's weakest on evidence:** `EnsureAdminHasRole`. Not because it was a bad call, but because — unlike `LegacyPasswordVerifier` and `Permission`/`Role` (which fixed problems already hit) or `MediaPathResolver`/`TracksViews` (built from already-confirmed legacy evidence with an explicit sequencing rationale) — it's pure prevention of a problem that hasn't occurred yet, and the cost of waiting for Wave 5 would have been genuinely low.

---

## 4. Wave 4 readiness — technical debt, prioritized

**Must do before Wave 4:**
- **Add PHPStan/Larastan.** Deferred twice already (Wave 2, Wave 3), each time with "before Wave 4's larger surface area" as the stated reason. This is that wave. Cost of adding now is low; cost of retrofitting after Wave 4's volume lands is not.
- **Replace the 4 `find()` + `abort_if(null, 404)` pairs with `findOrFail()`.** Trivial, safe (existing tests assert the externally-observable 404, not the mechanism), and prevents Wave 4's many new controllers from copying the longer pattern by example.

**Nice to do before Wave 4:**
- **Extract the shared Blade partial** for `channels/show.blade.php`/`author.blade.php`'s duplicated groups/series/items markup. Real, current duplication; Wave 4 will very likely add a third copy (khotab's own browse page) if not addressed first — but fixing it after a third copy exists is still manageable, just somewhat more work.
- **Resolve the `MediaPathResolver` `Services/` vs. `Support/` question (§1) and formalize `Providers/`/`Concerns/`** in the Blueprint's documented domain structure. Zero functional risk either way; doing it now just means Wave 4's likely new utility-shaped classes have a documented place to go instead of re-deciding this per class.
- **Reclassifying from the prior review:** I'd previously called site chrome (`layouts.app` having no real navigation/header/footer) a "must." On reflection for this specifically-technical-debt-focused pass, I think that overstated it — the missing chrome is a deliberate, tracked, explicitly-documented scope boundary each wave, not a shortcut that will require rework, and it doesn't block Wave 4's *correctness* (Blueprint's testing strategy is about behavioral fidelity, not visual fidelity). Moving it to "nice to do" — worth a deliberate decision before Wave 4 ships more chrome-less pages, but not a code-quality blocker the way the two "must do" items are.

**Can safely wait until after the migration (or resolve themselves naturally):**
- **The `VbUserReader` decision (§2).** Wave 4 is Content-domain work and never touches Identity — genuinely zero urgency.
- **`randomitems()` widget** (`channels/author.php`) — not really "waiting," it resolves itself the moment Wave 4 builds a real content-item model; no separate action possible before then.
- **`ORDER BY BINARY title` driver-aware compromise** — low risk, only meaningfully verifiable against real MySQL once Infrastructure Confirmation #1 lands.
- **`IF-008`** (missing `embedded=true` param) — cosmetic, already logged as a non-blocking future business question.
- **`TracksViews`/`recordView()` naming** ("view" vs. "visit") — better decided once Wave 4 adds several more real call sites and it's clear whether "view" reads naturally across all of them, not just `Channel`'s edge case.
