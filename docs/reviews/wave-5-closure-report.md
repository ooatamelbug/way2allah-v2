# Wave 5 — Final Closure Report

**Date:** 2026-08-07. Closes the Wave 5 verification cycle opened by `wave-5-verification-review.md`, resolved by `wave-5-permission-hardening-proposal.md`, and refined by one correction found during that proposal's own implementation.

---

## 1. What changed

**A. Business-behavior re-validation, before writing any code** (per your explicit instruction to re-check the core assumption from a business angle, not just re-read code): re-ran the permission-enforcement question with a broader, pattern-agnostic grep across all of `admincp/` (not the narrower regex Finding 1 used), which surfaced `survey/add_survey.php`'s `WHERE permissions LIKE '%modsurvey%'` query. Traced it fully: it builds a checkbox list of admins eligible to be assigned as a *survey's* editors — business data, never read back anywhere else in `survey/` to gate access. Also found `admincp/index.php:169`'s own inline comment — *"Try to load related VB user for permission-driven pages (sidebar, etc.)"* — direct first-party evidence the original developer understood this data as sidebar-only. Conclusion strengthened, not weakened. Proceeded to implementation.

**B. Ratification (decision-log #10 + Blueprint):**
- `docs/decision-log.md` — new entry **#10**: Wave 5's `admin.permission:{module}.{key}` enforcement is kept as-is, reclassified as a deliberate hardening rather than a legacy port. Entry #9 gets a forward-pointing addendum (not a silent rewrite) noting the correction and the stale "~25 permissions/9 modules" count (now 27/10).
- `00-master-migration-blueprint.md` — §9 gains a clarifying line, §16's Security Remediation table gains row #12, plus a dated revision-history entry.

**C. Findings 2/3 fixed (route-level, no new mechanism):**
- `routes/admin.php` — `locations`: view routes (`index`, `edit`) now accept either `add_location` or `del_location`; mutations stay split as before. `chat`: view routes (`index`, `edit`) now accept either `listrooms` or `editroom`; mutations stay `editroom`-only. Both reuse `EnsureAdminHasPermission`'s existing `hasAnyPermission()` OR-matching — the same pattern `questionnaire`'s routes already used, no middleware or controller changes. Folded in while touching these blocks: the stale "IF-036" comment on the `chat` group corrected to "IF-034"; `PermissionController`'s super-admin-only comment now points at decision-log #10 instead of standing alone.
- Two new tests added (`SoundcloudYoutubeLocationsTest.php`, `ChatRoomAdminControllerTest.php`) proving the previously-unreachable permission combinations now work, plus one confirming `del_location`-only still can't create.

**D. An unrelated pre-existing bug, found incidentally:** the new locations test was the first to call `index()` via HTTP with real data, which surfaced `LocationsController::index()`'s MySQL-only `orderByRaw('BINARY title ASC')` failing under the SQLite test connection — untested until now. Fixed with the same driver-aware pattern already established in `LiveStreamController::titleOrderClause()` (production keeps the exact legacy MySQL clause; SQLite gets a plain sort, test-only difference, not a behavior change).

**E. A genuine correction found during independent re-verification, before declaring anything done** (per your instruction to actively try to disprove the implementation): a broader grep across the *entire* legacy application, not just `admincp/`, found `chat_room/chat_room.php` and `chat_room/functions.php`'s `list_chat_rooms()` — public, non-admincp pages — do real, page-level, business-meaningful enforcement against the same `nuke_authors.permissions` data: a visitor who is also an admin holding any `chat`-module permission can see/enter disabled live chat rooms (the code's own comment: *"this member is manager for chat rooms module"*). This was already documented pre-Wave-5 in `chat_room.md` §3, but had never been cross-referenced against decision-log #9's or the Blueprint's claims. Per your decision: this does **not** invalidate Wave 5 (that code path is entirely inside `chat_room/`'s unmigrated live-room half, out of scope for both Wave 4 and Wave 5, deferred to Roadmap task 6.5). It does mean the Blueprint/decision-log/PROJECT-HANDOFF wording needed narrowing from a sitewide claim to an `admincp/`-scoped one. Fixed:
  - New finding **IF-035** in `implementation-findings.md`, cross-referenced from `chat_room.md` §3, Roadmap task 6.5, the Blueprint, and decision-log #10.
  - Blueprint §9's line narrowed to "within `admincp/`," plus a second same-day revision-history entry recording the correction.
  - Decision-log #10 gets its own addendum narrowing the Reason line's "anywhere" claim.
  - `PROJECT-HANDOFF.md`'s §16-summary line narrowed the same way.
  - Roadmap task 6.5 gains an explicit note: whoever picks up that task must decide, deliberately, whether to reproduce this rule as-is or harmonize it with Wave 5's new permission model — not silently drop or silently port it.
- No code, route, or test changed as a result of this finding — by design, it's a documentation-scope correction only, exactly as you directed.

---

## 2. Why the decision is now correctly documented and justified

The chain of evidence is now layered and cross-referenced rather than resting on a single grep pass:
1. Finding 1's original targeted grep (`authorization`/`$permissions[` across `admincp/`'s feature directories).
2. A broader, pattern-agnostic re-grep of the literal substring `permissions` across all of `admincp/`, run specifically to stress-test Finding 1 from a business-behavior angle — surfaced `survey/add_survey.php`, traced and ruled out as an access gate.
3. `admincp/index.php`'s own developer comment, corroborating original intent.
4. A final, sitewide grep (not scoped to `admincp/`) — the one that found the real counter-example, `chat_room/`'s live-room half — confirming that when the claim is honestly bounded to `admincp/`, it holds; when it isn't, it doesn't, and the documentation now says exactly that, nowhere overclaiming.

Every one of these passes is cited, with file:line references, in decision-log #9's addendum, decision-log #10 and its own addendum, IF-035, and the Blueprint's two 2026-08-07 revision-history entries. Nothing rests on an unverified assumption anymore, and the one place an assumption crept back in during implementation (the unscoped Blueprint sentence) was caught by the same adversarial discipline you asked for, before it was presented as final.

---

## 3. Test suite and PHPStan results

Re-run fresh after every substantive change (route split, `LocationsController` fix, and again after all documentation corrections):

```
Tests:   261 passed (5,584 assertions)
PHPStan: 0 errors (level 5, zero suppressions)
```

Started this round at 258/258 (the verification review touched no code). +3 net: 2 new permission-reachability tests, 1 new negative test (`del_location`-only still can't create).

---

## 4. Is Wave 5 now 100% complete and internally consistent?

**Yes.** Specifically:
- Findings 1 (Critical) and 3 (High) from the verification review: resolved by explicit decision (decision-log #10), not silently — the stricter model is kept, correctly classified, and documented everywhere it's referenced.
- Finding 2 (High): fixed at the route level, tested.
- Findings 4-9 (Medium/Low): unchanged, explicitly still deferred, exactly as you directed in the prior turn — not part of this closure.
- The one issue found during this round's own independent re-verification (the sitewide-vs-`admincp/`-scope error) was caught, not shipped silently, and corrected narrowly without touching any code or Wave 5 behavior — cross-referenced in 6 files (`chat_room.md`, the Blueprint in two places, the Roadmap, decision-log, `implementation-findings.md`, `PROJECT-HANDOFF.md`) so it can't be lost or re-discovered from scratch later.
- Blueprint, Roadmap, Decision Log, Implementation Findings, module documentation (`admincp.md`, `chat_room.md`), and the actual implementation all agree — checked directly this round (grepped for every remaining unscoped claim after each edit, re-ran `route:list`, re-ran the full suite/PHPStan after every change, not just at the end).

**Deferred, not blocking:** Findings 4-9 from the verification review, and the new obligation on Roadmap task 6.5 (IF-035) — both are real, both are documented, neither touches Wave 5's own scope or correctness.

## 5. Is it safe to proceed to Wave 6?

**Yes, no remaining architectural concerns from this review block it.** The permission model Wave 6 will build on top of is now correctly classified, internally consistent, and its one real edge case (the `chat_room` exception) is fenced off to task 6.5 with an explicit decision requirement already recorded — Wave 6 does not need to resolve it. Recommend carrying Findings 4-9 forward as a small independent cleanup pass (your call on timing — before or interleaved with Wave 6, none of them are Wave-6 blockers) rather than folding them into Wave 6's own scope.
