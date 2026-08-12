# Wave 6 — Business Confirmation Reconciliation Pass

**Date:** 2026-08-07. **Status: reconciliation only — no code, route, permission, or test changed.** Produced against the 10 business answers now provided (Confirmations #1-#9, #13). Confirmations #10 (Survey close-time), #11 (team size), #12 (timeline) were not answered in this round and remain as previously documented — not addressed further here.

> **Correction (2026-08-10) — Confirmation #4 was over-read in this document.** Every statement below treating "Zoom" as a confirmed replacement path or scoping a "Zoom-landing-page" task for 6.5 was this reconciliation pass's own inference, not something the Business Owner actually said. The Business Owner has since clarified directly: **NO to the original FlashChat live-room feature, and ALSO NO to any Zoom-based replacement** — the entire live-room/chat-room feature family is retired, with no replacement of any kind. Per this project's "visible correction, never silent rewrite" convention, the text below is left as originally written, with corrections marked inline where the Zoom framing appears. The authoritative corrected record is `decision-log.md` entry #14 and `00-implementation-roadmap.md` task 6.5 (both reflecting the correction in full).

**Git state, as required before any large change:** both repositories remain at zero commits, everything currently uncommitted. `laravel-project`: 5 tracked-modified files, 9 untracked new paths (all from the already-approved Wave 6.7 work and the Business Confirmation Report). `legacy-project`: 7 tracked-modified files (the already-approved SQL-injection fix, `.gitignore`/`vb5.php` predate this session). **Recommendation:** establish a baseline commit on both repos before any further implementation work begins in this next phase — not done as part of this reconciliation pass, since it's a repository action, not a documentation one, and wasn't asked for here.

---

## 1. Confirmed Business Decision Matrix

| # | Business Decision | Engineering Consequence | Status |
|---|---|---|---|
| 1 | Fatawa / advanced-search — **YES, required** | Full migration scope confirmed. Missing dispatcher (`modules.php`/`new_modules.php`) must be replaced by real Laravel routes, not treated as evidence of retirement. | Unblocked — scope confirmed, needs task breakdown |
| 2 | Ramadan page / share banners — **YES, required** | Full migration scope confirmed for both. Missing banner images are a separate asset-recovery problem, tracked independently. | Unblocked — scope confirmed, needs asset sourcing decision |
| 3 | English site — **NO, dormant** | Formally closed. Separate `english/` codebase excluded from migration. Only the single nav link in `header.php` needs a decision (remove vs. repoint). | Closed — one small follow-up item remains |
| 4 | Live voice-chat rooms — **NO, retired**; ~~Zoom is the path forward~~ **Correction 2026-08-10: NO Zoom replacement either — entirely closed, no replacement of any kind (`decision-log.md` #14)** | ~~Original FlashChat live-room build is retired, not migrated. Minimum Zoom-workflow implementation identified from existing evidence (see §3).~~ Original FlashChat live-room build is retired, not migrated; no replacement build of any kind. Two already-shipped and one already-deferred item are directly affected — see contradictions below. | ~~Unblocked (Zoom landing page) / Closed (original live-room build) — mixed~~ **Closed entirely.** |
| 5 | Legacy cross-login bridge — **preserve as-is now, harden later** | No authentication redesign this phase. Matches architecture already in place since Wave 0 (see §3 — no contradiction found). | Confirmed — no engineering change required |
| 6 | Khotab/telawah admin CRUD — **handled by another dashboard/system** | No CMS built in Laravel Admin domain. ~30 excluded permission keys can be closed permanently rather than left "pending." Relationship to the other system needs one direct follow-up question (see §3). | Closed (CRUD) — one clarifying question outstanding |
| 7 | Backup/booking tool — **YES, still used** | Remains in scope. Legacy behavior (5-table `booking`/`trial` mechanism, API-key gate) to be preserved, not redesigned. | Unblocked — scope confirmed, needs task breakdown |
| 8 | `old.way2allah.com` database relationship — **YES, same database** | Supersedes ADR-0009's "unknown" classification. One directly-observed piece of technical evidence (the hit-counter test) now needs reconciling, not discarding — see §3. | Confirmed, with one addendum required |
| 9 | Admin password audit — **neither audit nor reset** | No new engineering work. Already-shipped Wave 0 rehash-on-login mechanism already achieves exactly this outcome (gradual, automatic convergence, no audit, no forced reset). | Confirmed — already satisfied by existing architecture |
| 13 | Legacy URL preservation — **YES, full 217-rule fidelity** | Confirms the Blueprint's already-default position (§11: "every currently-indexed, currently-linked legacy URL pattern resolves to something correct"). No reduction to be considered. | Confirmed — already the default; no change needed |

---

## 2. Documentation Changes Required

| Document | Section | Current statement | New confirmed decision | Required update |
|---|---|---|---|---|
| `00-implementation-roadmap.md` | Task 6.1 (`fatawa`) | "Gate: Business Confirmation #1... If confirmed live: full migration" | Confirmed live | Remove the conditional framing; state the migration is authorized, retaining the existing "standard pattern" scope note |
| `00-implementation-roadmap.md` | Task 6.2 (`advanced-search`) | Same conditional framing as 6.1 | Confirmed live | Same update as 6.1 |
| `00-implementation-roadmap.md` | Task 6.3 (`ramadan`/`share`) | "If still promotionally active: build... If not: no migration" | Confirmed active | Remove the conditional; retain the "one parameterized view" scope note; add the missing-banner-assets item as a named, separate sub-task, not a retirement reason |
| `00-implementation-roadmap.md` | Task 6.4 (khotab/telawah CRUD) | "do not prototype UI or data model before this lands" | Handled by another system | Reclassify from "Blocked" to "Closed — will not be built in Laravel Admin domain," pending the one clarifying question in §3 |
| `00-implementation-roadmap.md` | Task 6.5 (`chat_room` live-room) | Full scope including weekly-lesson-schedule, plus the unresolved IF-035 permission-harmonization decision | ~~Retired; Zoom is the replacement path~~ **Correction 2026-08-10: retired entirely, no replacement of any kind** | ~~Reclassify from "Blocked" to "Superseded" — replace the live-room rebuild scope with the much smaller Zoom-landing-page scope identified in §3~~; Reclassify from "Blocked" to **"Closed/Retired"** — no replacement scope of any kind; close IF-035's open harmonization question as moot (there is no longer a live-room permission gate to harmonize) |
| `00-implementation-roadmap.md` | Task 6.6 (`english/`) | "If still offered:... If dormant: no work" | Dormant | Close the task; add the single remaining action item (nav-link disposition) |
| `00-implementation-roadmap.md` | New item — `nuke_backup_booking` | No task number exists; only referenced in the bottom summary table (Confirmation #7 row) | Confirmed still used | Add as a new numbered task (task 6.8, next available number), scoped from `backup.php`'s already-read source |
| `00-implementation-roadmap.md` | Bottom summary table | Confirmation #5/#7/#9/#10 rows list tasks with no number (or "within 5.6") | #7 now has real scope; #9 needs no task at all | Update the #7 row to point at the new task 6.8; #9's row can be removed entirely — nothing to schedule |
| `00-master-migration-blueprint.md` | Part IV | Confirmations #1-#9, #13 listed as open | 10 of 13 now answered | Part IV itself is historical (frozen document); per its own "no silent edit" rule, add a dated revision-history entry recording that these are now resolved, pointing at this reconciliation document — do not delete or rewrite the original questions |
| `00-master-migration-blueprint.md` | §7 module-mapping table / ADR-0009 | Frames the database relationship with `old.way2allah.com` as unresolved | Confirmed same database | Add a revision-history entry; ADR-0009 itself needs the addendum described below, not a rewrite |
| `00-architecture-decisions/ADR-0009.md` | Decision, §4 "database relationship stays open" | "The database-relationship question stays open... no dedicated investigation time is allocated to it going forward" | Confirmed same database | **Addendum, not rewrite** (matching this project's own established convention) — record the business confirmation, and explicitly reconcile it against the hit-counter test's own finding (the two visit counters did not move in lockstep) rather than silently deleting that observation |
| `chat_room.md` (module doc) | §3, IF-035's cross-reference | Frames the room-visibility permission rule as needing a harmonization decision when task 6.5 is picked up | Task 6.5's live-room half is retired | Add a correction addendum: the rule is now moot, not resolved — there is no live-room feature left to gate |
| `PROJECT-HANDOFF.md` | §3 Remaining Deferred Items, §4 Open Business Confirmations, §6 Roadmap Progress, §8 Recommended Next Phase | Lists Confirmations #1-#9, #13 as open; Wave 6 as "not started, correctly gated" | 10 confirmations resolved | Rewrite these sections to reflect resolved-vs-remaining status; this is the document's own stated purpose (living project state) — a substantive update, not an addendum |
| `docs/decision-log.md` | New entry required | No entry currently addresses any of these 10 answers | Multiple genuine new decisions now made | One new entry (see §3 for exactly which sub-decisions warrant it) |
| `docs/implementation-findings.md` | IF-033 (weekly-lesson-schedule scope note), IF-035 (permission-rule cross-reference) | Both assume task 6.5 will eventually be picked up in its original form | 6.5's live-room half retired | Addendum on each, not a rewrite — both findings remain historically accurate about what was true when written |
| `docs/business-confirmation-report.md` | Entire document | Presents all 10 items as open questions | 10 now answered | Should be marked closed/superseded once these updates are applied — out of scope for this reconciliation pass itself |

---

## 3. Contradictions and Consequences Requiring Explicit Attention

Per your instruction, none of these have been resolved — each is reported for a decision.

### 3.1 — The `old.way2allah.com` hit-counter evidence does not simply disappear (Confirmation #8)

**What conflicts:** `old-domain-investigation.md`'s own controlled test found the two domains' visit counters for the same content item did **not** move together — the old domain's counter incremented by roughly 1 per this investigation's own request, while the current domain's counter jumped by an amount far larger than this investigation's traffic could explain. That finding was the specific, direct reason ADR-0009 classified the relationship as "unknown" rather than "confirmed shared."

**Why it conflicts:** the business confirmation states the two deployments share the same database. Taken literally and naively, a single shared database would be expected to show the same counter value read from both front ends, in lockstep — which is exactly what was *not* observed.

**Which source has authority:** the business confirmation. Operational knowledge of infrastructure (which database a deployment actually connects to) is not something source-code analysis or black-box HTTP testing can ever fully settle — this is precisely the category of question this report existed to escalate. The technical observation is not wrong; it is incomplete.

**What should change:** ADR-0009 gets a dated addendum (not a rewrite) recording the confirmation and explicitly reconciling it: the counter divergence is consistent with several explanations that don't contradict a shared database — a caching layer in front of one deployment, a separate counter column or increment path used only by the older front end, or the older deployment's own view-counting code path being disabled or broken. Which of these it actually is remains unverified and is now a much lower-priority technical curiosity, not a scope-blocking question.

**Decision-log entry required:** yes — see §3.4 below, folded into one combined entry with 3.2 and 3.3.

### 3.2 — Two already-shipped Wave 5 admin features may now be building for a retired capability (Confirmation #4)

**What conflicts:** Wave 5, task 5.5 (`ChatRoomAdminController`, shipped and approved) built real, working admin tools for managing FlashChat live-chat rooms — editing room settings, removing owners/speakers. These tools manage exactly the capability Confirmation #4 now retires.

**Why it conflicts:** the business decision doesn't explicitly address whether *already-shipped* work tied to the retired capability should also be deprecated, left in place for historical/administrative reasons, or removed.

**Which source has authority:** neither — this is a genuine gap between an old decision (build the admin tools, made before Confirmation #4 existed) and a new one (retire the underlying feature), and resolving it is a product/engineering-priorities call, not something either source settles on its own.

**What should change:** nothing yet. This needs an explicit answer: keep the Wave 5 admin chat-room tools (e.g., for any residual/historical room data), or deprecate them alongside the live-room retirement. Flagged here rather than assumed either way.

**Decision-log entry required:** yes, once answered.

### 3.3 — The weekly-lesson-schedule feature's fate follows directly from the evidence, not an assumption

**What conflicts:** nothing, technically — this is a case where the evidence already resolves the question, but it's worth stating explicitly rather than silently closing it. IF-033's own finding was that the schedule feature (`chat_room/table.php`) "schedules attendance at the LIVE voice rooms... every row it renders links to a live voice room... not recorded content, making it meaningless without this same confirmation." Confirmation #4 has now answered that same confirmation, in the negative.

**Which source has authority:** the Roadmap's own already-recorded reasoning (IF-033), now completed by Confirmation #4's answer.

**What should change:** the weekly-lesson-schedule feature is confirmed out of scope — not because this pass is deciding it, but because the Roadmap already said it would be exactly this outcome if the live-room confirmation came back negative, and it has. Business confirmation #4's instruction to "review the previously deferred weekly lesson schedule... under this decision" is satisfied by this review: a direct read of `chat_room/table.php` (done earlier this session) found no Zoom reference anywhere in it — it is entirely shaped around the FlashChat room mechanism being retired, with nothing salvageable for a Zoom-based reinterpretation without being rebuilt from nothing.

**Decision-log entry required:** yes — closing a previously-open Roadmap item counts as a genuine decision under this project's own standing bar.

### 3.4 — Combined decision-log entry covering 3.1-3.3

One new entry, not three, since all three are direct consequences of Confirmations #4 and #8 landing. Recommended content: (a) record both confirmations as resolved: business decisions, not inferred; (b) record the ADR-0009 hit-counter reconciliation from 3.1; (c) record the weekly-lesson-schedule closure from 3.3; (d) explicitly flag 3.2 (the Wave 5 admin chat-room tools' fate) as still open, not resolved by this entry.

### 3.5 — Confirmation #6's "another dashboard/system" is not yet identified — evidence points toward, but does not confirm, `old.way2allah.com`

**What conflicts:** nothing directly — this is an evidence gap, not a contradiction. Flagged because your instructions were explicit: investigate the relationship, do not invent an integration mechanism, and do not assume.

**Evidence available:** the only other admin-panel-shaped system this entire audit has ever found, anywhere, is `old.way2allah.com`'s own `admin.php` — confirmed live and reachable (`old-domain-investigation.md`), never logged into (out of this audit's authorized scope throughout). Confirmation #8 has now separately established that `old.way2allah.com` shares the current site's database. Combining these two facts makes `old.way2allah.com`'s admin panel a strong candidate for "the other dashboard" — but the business's own answer to #6 did not name it explicitly, and this reconciliation pass is not authorized to treat a strong inference as a confirmed fact.

**What this means technically, regardless of which system it turns out to be:** because Confirmation #8 confirms a shared database, and Laravel's existing public khotab/telawah read-path (`KhotabItem`/`TelawahItem`, built in Wave 4) already reads directly from that same database, **no integration work is required on Laravel's side under any candidate for "the other dashboard."** Laravel continues exactly as it already does today — reading content state, not writing it, and not calling any external API. This holds whether the other system is `old.way2allah.com`'s admin panel or something else entirely.

**Recommended next step:** one direct clarifying question — "is the 'other dashboard' `old.way2allah.com`'s existing admin panel, or a different system?" — purely for documentation accuracy; it does not change the technical consequence above either way.

**Decision-log entry required:** not yet — once the system is named, yes.

### 3.6 — No contradiction found for Confirmations #5 and #9

Checked directly, stated for completeness since the instructions asked contradictions to be actively looked for, not just reported where found: Confirmation #5's "preserve now, harden later" principle is already exactly how `AdminGuard`/`LegacyPasswordVerifier` were built in Wave 0 — legacy hash formats are accepted and verified, then transparently rehashed to bcrypt on next login, with no forced reset and no plaintext fallback ever reproduced on the Laravel side. Confirmation #9's "neither audit nor reset" is already the natural behavior of that same mechanism. Both business answers ratify architecture that already exists; neither requires a design change.

---

## 4. Wave 6 Updated Status

```text
Task: 6.1 — fatawa
Status: Unblocked — scope confirmed, ready for technical planning
Reason: Confirmation #1 answered YES. Missing-dispatcher routing gap must be
        replaced with real Laravel routes, not treated as a retirement signal.
Next action: Direct legacy-source re-read of all 16 fatawa/ files against
        fatawa.md's existing findings before any implementation plan is written.

Task: 6.2 — advanced-search
Status: Unblocked — scope confirmed, ready for technical planning
Reason: Confirmation #1 answered YES (same gate as 6.1).
Next action: Direct re-read of advanced-search/index.php's full Search class
        (already partially read for the SQL-adjacent fix) against
        advanced-search.md's existing findings.

Task: 6.3 — pages/ramadan.php + help/share.php
Status: Unblocked — scope confirmed, ready for technical planning
Reason: Confirmation #2 answered YES for both. Banner-asset gap is a
        separate, parallel-track problem, not a scope blocker.
Next action: Confirm with the business whether replacement banner assets
        will be supplied, or whether share.php ships without them initially.

Task: 6.4 — khotab/telawah admin CRUD
Status: Closed by business decision
Reason: Confirmation #6 — handled by another system, not built in Laravel.
Next action: One clarifying question (§3.5) to formally name the other
        system, for documentation accuracy only — no engineering follow-up
        blocks on the answer.

Task: 6.5 — chat_room's live-room half
Status: ~~Changed in scope — from "rebuild the original feature" to
        "small Zoom-landing-page replacement"~~
        CORRECTED 2026-08-10: CLOSED / RETIRED entirely — no replacement
        scope of any kind (decision-log #14).
Reason: ~~Confirmation #4 retires the original live-room build; Zoom is the
        confirmed replacement path.~~ Confirmation #4 retires the original
        live-room build AND rules out any Zoom-based replacement — the
        "Zoom is the replacement path" reading was this document's own
        over-read of the Business Owner's answer, corrected directly by
        the Business Owner on 2026-08-10. Weekly-lesson-schedule sub-scope
        closed (§3.3, unaffected by this correction). Two already-shipped
        Wave 5 admin tools' fate remains open (§3.2) — reopened, not
        resolved, per the same 2026-08-10 correction.
Next action: ~~Direct read of chat_room/rules.php's actual content (not yet
        done this session) to determine if it's FlashChat-specific
        instructions or general conduct guidelines still relevant to Zoom,
        before finalizing the landing-page's own scope.~~
        None — task closed. rules.php has since been read (Legacy
        Evidence Verification pass) and recorded as evidence only in
        chat_room.md; neither its FlashChat-specific nor general-conduct
        content is being implemented.

Task: 6.6 — english/'s fate
Status: Closed by business decision
Reason: Confirmation #3 — dormant.
Next action: Decide remove-vs-repoint for the single header.php nav link;
        trivial, no further investigation needed.

Task: 6.7 — link-quality stats & repair tool
Status: Unaffected — already complete, approved, and locked
Reason: Never confirmation-gated; none of today's 10 answers touch its scope.
Next action: None.

Task: 6.8 (new) — backup/booking tool integration
Status: Newly unblocked — scope confirmed, not yet task-numbered in the
        Roadmap
Reason: Confirmation #7 answered YES.
Next action: Add the task number to the Roadmap (documentation step, §2);
        then a full read of backup.php's remaining, not-yet-fully-analyzed
        logic before any implementation plan.
```

---

## 5. Newly Unblocked Work

Genuinely ready to move from "confirmation-gated" to "technical scoping," in the order recommended by evidence weight (most-understood first):

1. ~~**Task 6.5's Zoom-landing-page replacement** — the smallest, best-evidenced of the newly-unblocked items. `chat_room/room.php`'s own existing structure (already fully read this session) already shows almost the entire needed shape: a static Zoom link/banner, plus links to two already-built features (task 4.11's lesson browsing, the existing sidebar widgets) and one not-yet-verified page (`rules.php`).~~ **Correction 2026-08-10: this item does not exist. Task 6.5 is closed/retired entirely — there is no Zoom-landing-page replacement to build.** See `decision-log.md` #14.
2. **Task 6.8, backup/booking tool** — real, working legacy code already partially read (`backup.php`); scope is bounded (5 tables, one access-key gate) even though not yet fully analyzed.
3. **Tasks 6.1/6.2 (fatawa/advanced-search)** — largest of the newly-unblocked items; scope is well-documented in existing module docs (`fatawa.md`, `advanced-search.md`) but needs a fresh full read given the missing-dispatcher routing gap means a genuinely new routing layer, not a like-for-like port.
4. **Task 6.3 (ramadan/share)** — well-evidenced (production error-log history for the Ramadan half), smaller than 6.1/6.2, blocked only on the separate banner-asset question.

## 6. Closed / Retired Work

- **`english/`'s separate codebase** — excluded from migration entirely (Confirmation #3).
- **The original FlashChat live-voice-room build** (`chat_room.htm`, `chat_{id}.htm`, the underlying third-party chat-server integration) — retired, not migrated (Confirmation #4). **Correction 2026-08-10: also add — no Zoom-based or any other replacement is being built either.** This document's original text (§1 row 4, §2, §4, §5 item 1) treated Zoom as the confirmed replacement path; the Business Owner has since clarified that reading was incorrect. See `decision-log.md` #14 for the full corrected record.
- **The weekly-lesson-schedule feature** (`chat_room/table.php`) — confirmed out of scope as a direct consequence of the above (§3.3).
- **Khotab/telawah admin content-management CRUD inside the Laravel Admin domain** — will not be built; ~30 previously-excluded-pending-confirmation permission keys can be marked permanently excluded rather than provisionally excluded (Confirmation #6).
- **IF-035's permission-harmonization decision** — closed as moot, not resolved (§3.3) — there is no longer a live-room feature whose visibility rule needs harmonizing with anything.

## 7. Remaining Business Blockers

Only what is genuinely still unresolved:

- **Confirmation #10** (Survey close-time consistency) — not addressed this round; low urgency, safe default already in place.
- **Confirmation #11** (team size/experience) and **#12** (timeline/urgency) — not addressed this round; resourcing questions, not module-level decisions.
- **§3.2** (fate of Wave 5's already-shipped chat-room admin tools) — a genuine new open question surfaced by Confirmation #4, not a pre-existing numbered confirmation.
- **§3.5** (naming "the other dashboard" from Confirmation #6) — a documentation-accuracy clarification, not a blocker to any engineering work.
- **Ramadan/share banner assets** — whether replacements will be supplied (Confirmation #2's own named follow-on, not a new item).

---

## Recommended Next Engineering Step

**One recommendation, per the requested sequence (Business Confirmation → Documentation reconciliation → Legacy evidence verification → ... → Implementation):**

**Apply the documentation updates in §2 first, in full, before any legacy-source re-verification or implementation planning begins on any of the four newly-unblocked tasks.** This reconciliation pass has identified *what* needs to change and *why*, but per this project's own standing discipline, none of it has been written yet — the Roadmap, `PROJECT-HANDOFF.md`, ADR-0009, `chat_room.md`, and one new decision-log entry all still describe a project state that no longer reflects the confirmed business decisions. Writing code against a Roadmap that still says "Gate: Business Confirmation #1" would itself be a documentation-drift risk of exactly the kind this project has caught and corrected multiple times already this session. This is a documentation step, not an implementation one, and should be completed and reviewed before task 6.1, 6.2, 6.3, 6.5, or 6.8's own legacy-evidence-verification work begins.
