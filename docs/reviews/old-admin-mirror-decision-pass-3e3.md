# Pass 3E.3 — Mirror Administration Decision Pass

**Status:** DECISION PASS ONLY — no source investigation, no production queries, no implementation, no deployment, no commit.
**Date:** 2026-10-12
**Predecessors:** Pass 3E.1 (data/schema contract, CLOSED) · Pass 3E.2 (authorization/audit contract, CLOSED).
**Scope:** the five decisions those passes marked as requiring explicit choice, for the fixed first unit — Khotab insert/edit/delete/re-parent, Anasheed insert/edit/delete. **7 operations, 2 domains.**

**Reading rule for this document:** every "Recommended" row is a **recommendation, not a finding**. Findings live in 3E.1 and 3E.2 and are cited. Rows marked **⚠ POLICY** require a business or security owner to choose; engineering cannot derive them from the evidence.

---

## 0. Two facts that bound every decision below

Both are findings from 3E.2, restated here because all five decisions turn on them.

**F1 — Neither permission store is validated against actual operators.** `GreenZone()` is dead, so nothing has enforced a mirror grant in either store. The populated grants in System 2 (`addmirror` 33, `editmirror` 32, `delmirror` 28 for Khotab; 9/10/9 for Anasheed) and System 1 (`addmirror` 7, `editmirror` 6, `delmirror` 6 of 48 admins) are therefore **statements of granted intent, not records of who actually performs the work.** Any mapping from either store is a judgement about intent.

**F2 — The work is live and high-volume.** `nuke_islamic_logs` holds **87,547 `add_mirror`** rows, with the most recent at 2026-10-12 14:21:03 and `edit_mirror` / `del_mirror` activity the day before. Real people administer mirrors today. **A mapping that under-grants will block live operational work at cutover.** `move_mirror` is the exception: **1 row** in ~19 months.

Together: the choice is not "which store is correct" — it is "which statement of intent do we trust, knowing neither was ever enforced, while not interrupting work that is demonstrably happening."

---

## 1. Decision D1 — How legacy authorization state maps into Spatie

Spatie is the intended future enforcement authority; this is not a "System 1 vs System 2" question but a mapping question. It separates into three genuinely independent sub-decisions, which 3E.2 §7.1 kept apart and which must not be collapsed.

### D1a ⚠ POLICY — Mapping the populated-but-unenforced mirror grants

**Exact decision:** when `khotab.addmirror` / `.editmirror` / `.delmirror` (and the Anasheed equivalents, per D2) are seeded as Spatie permissions, which legacy state — if any — is used to grant them to existing admins?

| Option | Production behavior affected | Compatibility consequence | Security consequence | Migration consequence |
|---|---|---|---|---|
| **A. System 1 only** (the existing `SyncLegacyAdminPermissionsAction` path) | None today — nothing enforces either store | Reuses the already-built, idempotent, `--dry-run`-capable bridge. **No new mechanism.** | Grants ~7/6/6. Relative to System 2's stated intent this is a **net restriction of roughly 26 admins** for `addmirror`. No capability is *removed* (none was enforced), but a large body of granted intent is discarded. | Lowest effort. **Highest risk against F2:** if the people doing the 87,547 inserts are moderators recorded in System 2 rather than AdminCP admins in System 1, mirror administration breaks for them at cutover. |
| **B. System 2 only** (one-off mapping from `nuke_*_mod`) | None today | Needs a **new one-off mapping mechanism**; Laravel has never read `*_mod`. | Grants ~33/32/28 and 9/10/9. **Net expansion relative to System 1**, granting capability to admins the AdminCP editor never granted it to. | Moderate effort. Strongest semantic claim: System 2 is the **content-moderation** store, its UI (`ListModertors`) is the only one carrying the full mirror vocabulary, and **all 36 / 15 rows are active**. Mirror CRUD is content capability. |
| **C. Union** (either store grants) | None today | Needs the System 2 mechanism as well | **Maximal expansion, and unsizeable.** Overlap between the two populations is unknown — identities were deliberately not requested — so the resulting grant count cannot be bounded in advance. It confers the *more permissive* store's answer on every admin, including for `delmirror`, the destructive operation. | Worst security posture of the five; no semantic justification exists for preferring whichever store happens to be more permissive. **Not recommended.** |
| **D. Intersection** (both stores must grant) | None today | Needs both mechanisms | **Maximal restriction, and also unsizeable.** Bounded above by System 1's smaller counts (≤7/6/6) and potentially far lower — conceivably near zero if the populations barely overlap, which cannot be known without identities. | Directly collides with F2: plausibly locks out most or all current operators. **Not recommended.** |
| **E. Seed only; grant nothing automatically** — explicit re-grant in Laravel | None today | No legacy mapping at all | **Safest posture.** Mirror capability starts at zero and is conferred deliberately, by a named person, in the new system. No legacy intent is inherited unexamined. | Highest operational friction: someone must re-grant before any non-super-admin can administer mirrors. Given F2, that re-grant is on the cutover critical path. |

**Recommended: B (System 2 only), executed behind a reviewable dry-run, with E as the fallback if review is not acceptable.**

Why: System 2 has the stronger semantic claim — it is the store whose UI was built for content-moderation capability, whose vocabulary is complete for mirrors, and whose every row is active. Option A's appeal is purely that the mechanism already exists, which is an implementation convenience, not an authorization argument; and under F2 it carries real risk of breaking live work. C and D are rejected on substance: neither union nor intersection has a defensible authorization rationale, and critically **neither can be sized before it is applied**, so neither can be reviewed. B preserves the fullest statement of intent while remaining a *reviewable* list — which is what makes the expansion acceptable rather than reckless.

**⚠ This is a who-can-do-what decision and must be approved by the Product Owner.** Engineering can state the consequences, as above; it cannot choose which statement of intent the organization trusts. The one question that resolves it: *which UI does the organization consider the source of truth for who may administer mirrors — the moderator grid (`ListModertors`, System 2) or the AdminCP permission editor (System 1)?*

Note also: whichever option is chosen, F1 means the resulting grant list has **never been tested against reality**. A review step before enforcement is worth having under any option, not only under B.

### D1b — Preserving the currently-enforced Khotab `audio` / `vedio` scope

**Exact decision:** does the live media-type scope survive into Laravel, and as what kind of check?

| Option | Production behavior affected | Compatibility consequence | Security consequence | Migration consequence |
|---|---|---|---|---|
| **A. Preserve as a per-record check** | **Yes — this is the only live authorization on the surface.** `AvAdmin()` governs Khotab edit and delete, reading `nuke_islamic_mod.audio` (17 grants) / `.vedio` (26 grants) | Behaviour-preserving | Maintains the existing division of labour between audio and video moderators | **Requires introducing a per-record authorization layer.** The check depends on the *item's* `vedio` value, not on the route, and Laravel currently has **no policy or gate layer** (3E.2 §1.6) |
| **B. Preserve as a static route permission** | Yes | Not behaviour-preserving | Coarser: an admin either may edit all mirrors or none, losing the audio/video split | Cheaper, reuses existing middleware — but it is a different rule wearing the same name |
| **C. Drop it** | Yes | **Authorization regression** | Removes the only enforced control on the surface; the 17/26 split shows it discriminates in practice, so this widens real access | Cheapest |

**Recommended: A.** This is the one place where OLD still enforces something, and the grant counts (17 of 36, 26 of 36) show it is not vestigial. Dropping it would be the only change in this unit that *reduces* effective security, and B silently replaces the rule while keeping its name.

**Engineering default on the *whether*.** Preserving live, enforced authorization needs no policy approval. *How* the per-record layer is built is implementation design and is out of scope for this pass. One consequence to record: choosing A means this unit is the first in the codebase to need a per-record authorization layer.

### D1c — Super-admin bypass

**Exact decision:** does super-admin continue to bypass mirror permission checks?

| Option | Production behavior affected | Compatibility | Security | Migration |
|---|---|---|---|---|
| **A. Preserve** | `AvAdmin()` bypasses on `$_SESSION['w2a']->usertype === 'superadmin'`; `GreenZone()` would have bypassed on `radminsuper = 1`; Laravel already maps `radminsuper` → the `super-admin` role (5 admins) | Behaviour-preserving, and already implemented | Unchanged. 5 accounts hold it | **Zero work** — the mechanism exists |
| **B. Require explicit grants even for super-admins** | Yes | Diverges from all three generations | Tighter in principle; in practice super-admins can grant themselves anything, so the gain is audit clarity, not containment | New work, and it would need its own justification |

**Recommended: A. Engineering default.** All three generations agree, Laravel already implements it, and no evidence suggests the bypass is a problem. B's only real benefit is audit legibility, which the audit contract (D5) addresses more directly.

---

## 2. Decision D2 ⚠ POLICY-ADJACENT — Anasheed permission namespace

**Exact decision:** dedicated `anasheed.addmirror` / `.editmirror` / `.delmirror`, or scope Anasheed mirror operations under the existing `khotab.*` names?

| Option | Production behavior affected | Compatibility consequence | Security consequence | Migration consequence |
|---|---|---|---|---|
| **A. Dedicated `anasheed.*`** | `nuke_anasheed_mod` holds 15 active rows with 9/10/9 mirror grants — a population and grant count clearly distinct from Khotab's 36 and 33/32/28 | Matches the established `{module}.{key}` convention (3E.2 §1.6) | **Correctly scoped.** Anasheed capability is conferred independently of Khotab | **`admincp/` contains no `anasheed` directory**, so System 1 has no home for these keys and the existing sync can *never* populate them. They can only be granted via a System 2 mapping (D1a option B) or manually |
| **B. Reuse `khotab.*`** | Same | Works with the existing sync under D1a option A | **Unjustified cross-domain expansion.** Anyone holding `khotab.editmirror` (32 grants) could edit Anasheed mirrors, where Anasheed's own store grants only 10 — roughly a 3× widening across a domain boundary that both legacy stores keep separate | Cheapest, but it encodes a security expansion as a naming convenience |
| **C. Defer Anasheed from the first unit** | Anasheed mirror administration continues in OLD | No new names needed | No change | Reduces the unit from 7 operations to 4. Contradicts the fixed scope, so it is listed for completeness only |

**Recommended: A.** Two legacy stores independently model Anasheed as a separate capability domain with a materially smaller grant population; collapsing them into `khotab.*` would silently widen access ~3× across that boundary. The `{module}.{key}` convention already supports A.

**Why this is policy-adjacent rather than a clean default:** A is **coupled to D1a**. If D1a selects option A (System 1 only), then `anasheed.*` names can never be populated from legacy state, and Anasheed mirror administration becomes **super-admin-only** until someone grants manually. That is a defensible outcome, but it is an operational consequence the owner should accept knowingly rather than discover at cutover. **D1a and D2 should be decided together.**

---

## 3. Decision D3 — Anasheed `audio` / `vedio` media scope

**Exact decision:** should Anasheed mirror administration gain a media-type scope equivalent to Khotab's?

Framed as 3E.2 required: this would be a **new authorization rule, not parity.** `nuke_anasheed_mod` has **no `audio` or `vedio` columns at all** (E1), Anasheed has **no `AvAdmin()`**, and therefore **no one has ever been granted or denied Anasheed capability on media type.**

| Option | Production behavior affected | Compatibility consequence | Security consequence | Migration consequence |
|---|---|---|---|---|
| **A. Do not add** | None — no such rule has ever existed for Anasheed | Behaviour-preserving | Neither tightens nor loosens. The 9/10/9 grant-holders keep exactly the scope they were granted | No work. Leaves an asymmetry between the two domains, which accurately reflects the legacy design |
| **B. Add it** | None today, but it would newly **restrict** the 9/10/9 holders, who have no media grant to satisfy it | Divergence: a rule in Laravel with no legacy counterpart | Tighter in principle — but with no grant data to drive it, every Anasheed grant-holder would fail the check, making Anasheed mirror administration effectively **super-admin-only** until new grants are created | Requires inventing a grant dimension *and* populating it. Highest effort, and it silently disables working capability |
| **C. Defer** | None | None | None | Revisit only if a uniform media-type division of labour is wanted organisation-wide |

**Recommended: A (do not add). Engineering default.** No legacy intent exists — the columns were never created, so no one ever expressed a view. There is no stated requirement. And B's practical effect is not "tighter authorization" but **disablement**, because the rule would have no data to satisfy it. Adding an authorization rule with no grant data behind it is the pattern that produced F1 in the first place.

**One ⚠ note for the owner:** if the business *does* want media-type division of labour applied uniformly across content domains, that is a legitimate product requirement — but it is a new feature with its own grant-population problem, not part of this unit.

---

## 4. Decision D4 — Khotab re-parent permission

**Exact decision:** does re-parent reuse `khotab.editmirror`, as OLD intended, or get a new finer-grained `khotab.movemirror`?

Relevant findings: `MoveMirror` calls `GreenZone('editmirror')` (3E.2 §2), so **OLD never minted a `movemirror` capability**; `move_mirror` has **1** audit row in ~19 months (E4); and re-parent is among the least-governed mutations in OLD — `JoinMe`'s bulk variant has no authorization and no mirror-level audit at all (3E.2 §4).

| Option | Production behavior affected | Compatibility consequence | Security consequence | Migration consequence |
|---|---|---|---|---|
| **A. Reuse `khotab.editmirror`** | Matches the dead-but-explicit legacy token | Preserves OLD's stated intent exactly | Re-parent is available to whoever may edit mirrors (32 legacy grants). Re-parent is arguably more consequential than edit — it moves content between items — so this is the more permissive reading | Zero extra work. No new name to populate |
| **B. Mint `khotab.movemirror`** | No legacy grant exists for it | Diverges from OLD intent | Tighter — but because **no one holds it**, re-parent becomes super-admin-only on day one. Given the operation has 1 recorded use in 19 months, that may be exactly right; it is nonetheless a **silent disablement presented as granularity**, which is the same shape as D3 option B | Needs a new name and a grant decision. Low volume makes the operational cost near-zero |

**Recommended: A, with B noted as genuinely defensible.** A preserves intent and is populated. The honest argument for B is narrow but real: the operation is near-unused, it is destructive, and 3E.1 §3.5 showed re-parent is one of the four unguarded paths that can create duplicate state. If the owner wants re-parent restricted, B is the clean way — but it should be chosen as a deliberate tightening, with the day-one super-admin-only consequence accepted, not adopted as a default because finer granularity sounds safer.

**Engineering default: A.** Escalate only if the owner wants re-parent deliberately restricted.

---

## 5. Decision D5 — Audit coexistence strategy

E4 makes audit a **migration requirement**, not an option. This decision splits into the write target (⚠ policy) and the defect fixes (engineering default).

### D5a ⚠ POLICY — Write target

| Option | Production behavior affected | Compatibility consequence | Security consequence | Migration consequence |
|---|---|---|---|---|
| **A. Write only `nuke_islamic_logs`** | 253,499 rows, 3 modules, active to 2026-10-12 | **Best continuity.** One trail; OLD's moderator-log views see Laravel's writes; Generation 2's rename-integrity maintenance keeps working | Inherits the legacy shape: `extrainfo` is a display string that OLD renders, so avoiding raw-URL embedding may degrade that display (see D5b); `ip` retention inherited with no policy | Lowest effort. Laravel gains no structured audit of its own, and the legacy schema becomes a long-term constraint |
| **B. Laravel-only** | Same table continues to be written by OLD | **Structurally incomplete during side-by-side.** OLD mirror administration stays reachable (Pass 3D), so both systems can mutate mirrors concurrently: a Laravel-only trail cannot answer "who changed this mirror" for OLD-made changes, and OLD's readers cannot see Laravel's | Cleanest — structured fields, no inherited PII conventions, all defects fixable | Clean contract, but it knowingly creates a **split audit record** for the whole migration window |
| **C. Dual-write for the migration window** | Both | Continuity **and** a clean native trail | Allows the native trail to hold structured, minimal data while the compatibility row stays renderable by OLD. Also doubles the places PII lands, so retention must cover both | Two writes per operation; a reconciliation/stop question at cutover. Highest effort |

**Recommended: C (dual-write for the migration window).** B is rejected on substance: with OLD reachable and concurrently mutating, a Laravel-only trail is incomplete *by construction* in both directions — exactly the question an audit trail exists to answer. A is workable and cheap but makes the legacy schema a permanent constraint and leaves the `extrainfo` tension unresolved. C is the only option under which the trail stays answerable while both systems are live, and it degrades gracefully: when OLD is retired, the compatibility write is dropped.

**⚠ Requires Product Owner approval**, for two reasons engineering cannot settle: **tolerance for a split audit record**, and **how long side-by-side operation is expected to last** — if the window is short, A's simplicity may well win.

### D5b ⚠ SECURITY POLICY — `extrainfo` content

**Exact decision:** does the compatibility row reproduce `extrainfo`'s raw mirror URL?

3E.2 §8.3: `extrainfo` embeds raw mirror URLs — twice per Khotab insert (HTML-escaped) and **unescaped** in all three Anasheed calls — plus HTML markup. But `extrainfo` is also **what OLD's moderator-log view renders**.

| Option | Security | Compatibility |
|---|---|---|
| **A. Minimal `extrainfo`** — identifiers and a short label, no URL | No URL leakage into 253,499+ rows; URLs resolve from the mirror row | OLD's log display loses the inline link for Laravel-written rows. **Degrades, does not break** |
| **B. Reproduce the legacy string, URL included** | Continues the leakage, now also from Laravel. Anasheed's unescaped form would also be an HTML-injection sink if reproduced literally | Full display fidelity |
| **C. Minimal `extrainfo`, plus structured before/after URL fields on the Laravel-native trail only** | URL retained where genuinely needed for edit forensics, in a store with a defined retention policy, not in a shared display string | Same degradation as A on the legacy view |

**Recommended: C** (or A if structured URL capture is judged unnecessary). The before/after URL has real forensic value for `edit_mirror` specifically; it does not need to live in a rendered display string in a 253,499-row shared table.

**⚠ This is a security/privacy choice** — it trades OLD's log display fidelity against URL and PII exposure, and interacts with `ip` retention. It should be made by whoever owns data retention, not by engineering default.

### D5c — Defect fixes

All five exclusions the brief listed, plus two 3E.2 found. **None has policy content; all are strict improvements.**

| Defect | Fix | Consequence |
|---|---|---|
| **Incorrect target attribution** — Khotab insert logs `type='mirror'` with the *khotab* id (`$id_number = sql_nextid()` commented out at `:520`); affects all 87,547 `add_mirror` rows in aggregate | Log the real mirror id | Laravel-written rows become correctly attributable. **Historical rows stay wrong** — any reader joining on `target_id` must handle both eras |
| **Parent-only Anasheed granularity** — all three calls use `type='item'` with the parent `khid` | Apply Khotab's dual-row (`mirror` + `item`) convention to both domains | Anasheed's trail becomes able to answer "what happened to this mirror" |
| **Silent audit failure** — `sql_query()`'s result discarded | Surface the failure | No more silent, unattributed mutations |
| **Non-transactional audit write** | Place the audit write inside the transaction 3E.1 invariant **I-10** already requires for the mirror write plus the parent-counter write | Mutation and its audit record commit or roll back together |
| **Raw URL leakage** | D5b | ⚠ policy |
| **IP-source inconsistency** — `RecordLogs` prefers `REMOTE_ADDR` then `REMOTE_HOST`; `RecordLog` reverses it | Pick one | Consistent `ip` semantics going forward |
| **Inconsistent escaping** — `RecordLog` escapes only `extrainfo` | Parameterise everything | Removes an injection surface |

**Preserved unchanged** (3E.2 §8.1), because production readers depend on them: the action vocabulary `add_mirror` / `edit_mirror` / `del_mirror` / `move_mirror`; the `module` discriminator; `type` + `target_id`; `mytime` as a unix integer; the `hidden` flag; actor attribution via `aid`; and **coverage of all seven operations**. `move_mirror` is retained despite its single row — it is the audit record for the unit's most destructive single-row operation, and low volume is not grounds to drop it.

**Engineering default** for every row above except raw-URL handling.

---

## 6. Summary — defaults vs Product Owner approval

### Can be accepted as engineering defaults

| # | Decision | Default | Why it needs no policy input |
|---|---|---|---|
| D1b | Preserve Khotab `audio` / `vedio` scope | **Preserve, as a per-record check** | It is the only *live* authorization on the surface; dropping it is the one change in this unit that reduces real security. Consequence to note: first per-record authorization layer in the codebase |
| D1c | Super-admin bypass | **Preserve** | All three generations agree; Laravel already implements it; zero work |
| D3 | Anasheed media scope | **Do not add** | No legacy intent, no stated requirement, and its practical effect would be disablement rather than tightening |
| D4 | Re-parent permission | **Reuse `khotab.editmirror`** | Matches OLD's explicit token and is populated. Escalate only if the owner wants re-parent deliberately restricted |
| D5c | Audit defect fixes | **Fix all** (attribution, Anasheed granularity, loud failure, transactional coupling, IP consistency, escaping) | Strict improvements with no policy content |

### Genuinely require Product Owner approval

| # | Decision | Why it cannot be an engineering default | Question to answer |
|---|---|---|---|
| **D1a** | Which legacy state maps into Spatie | A who-can-do-what decision. Both stores are populated with materially different counts (33 vs 7), **F1** means neither was ever enforced so neither is validated, and **F2** means under-granting blocks live work. Union and intersection are rejected on substance — neither has an authorization rationale and **neither can be sized before it is applied** | *Which UI is the source of truth for who may administer mirrors — the moderator grid (System 2) or the AdminCP permission editor (System 1)?* |
| **D2** | Anasheed namespace | Recommendation is clear (`anasheed.*`), but it is **coupled to D1a**: under System 1 only, those names can never be populated and Anasheed mirror administration becomes super-admin-only. **Decide D1a and D2 together** | *Accept Anasheed as an independently-granted domain, and accept the population consequence that follows from D1a?* |
| **D5a** | Audit write target | Depends on business facts engineering does not hold | *What is the tolerance for a split audit record, and how long is side-by-side operation expected to last?* |
| **D5b** | `extrainfo` content | Security/privacy trade against OLD's log display fidelity; interacts with `ip` retention | *May mirror URLs be written into the shared 253,499-row log, or should they be confined to a structured store with a retention policy?* |

### Not decided here, by instruction

No implementation classes, controllers or services are designed. Once D1a, D2, D5a and D5b are answered, implementation design can begin: Pass 3E.1 fixes the data contract (12 invariants), Pass 3E.2 fixes the authorization and audit contract, and this pass fixes the five open choices.
