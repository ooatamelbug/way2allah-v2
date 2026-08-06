# Wave 5 Gap-Reconciliation Proposal

**Status:** Approved (2026-08-06). All 5 items' proposed Roadmap tasks and Blueprint amendments below have been applied — see `docs/reviews/wave-5-doc-sync-verification.md` for the closing synchronization check. This document is left as originally written (a proposal), not rewritten as a record of what happened — the "Proposed minimal Blueprint/Roadmap amendments" section below carries its own resolution markers.

Answers the 7 questions for each of the 4 items requested, plus a 5th item surfaced while completing the "nothing unclassified" requirement (flagged transparently below, not silently folded in). Ends with a full classification of every Admin-domain legacy capability and a minimal amendment set.

**New evidence gathered for this pass** (beyond `wave-5-admin-domain-analysis.md`): `khotab/uploader.php`/`uploaders.php` read in full (previously only size/grep-checked); `AdminGuard`/`AdminUser`/`LegacyPasswordVerifier` (the actual Wave 0 Laravel code) read in full, to answer the reuse question for item 4 with certainty rather than inference; `broadcasting/`'s Roadmap coverage re-checked directly.

---

## 1. `surveys/` (public PHP-Nuke poll system)

1. **In scope?** Yes. Real, reachable (raw path), fully analyzed (`surveys.md`, lifecycle Analyzed), carries a confirmed live production bug (`cookiedecode()` undefined → fatal error for logged-in visitors). Blueprint §6 (`Poll`/`PollOption` aggregate) and §7 (*"Public poll voting — distinct from `admincp/survey/`"*) already name this exact capability architecturally — the gap is a missing Roadmap task, not a missing Blueprint decision.
2. **New task, merge, or out of scope?** New task. Doesn't belong under Wave 5 (Admin) at all — it's Engagement domain, has zero dependency on anything Admin-domain, and nothing Admin-domain depends on it. Recommend appending it to **Wave 3** as a post-hoc addition, using the same "added post-Wave-N" convention already established for `radio`/`chat_room` under Wave 4: Wave 3's own stated selection criteria (*"least-blocked Core workflows... no auth dependency beyond the public Guard, no `media/` dependency, no open confirmation items"*) describes this module exactly, and `surveys.md`'s own conclusion (*"Difficulty: Easy... one of the strongest candidates in Wave 3"*) already said so independently, before this reconciliation pass.
3. **Effort: Small.** 3 tables, no comments/mirrors/hierarchy, `surveys.md` §9 already rates it "Easy."
4. **Reusable Laravel components:** none of the Content-domain services apply (different domain, different tables). New, small: `Poll`/`PollOption` Eloquent models (Blueprint §6 already names these two exactly — no naming decision needed). Laravel's `RateLimiter` replaces the manual `nuke_poll_check` IP-window dedup table, per `surveys.md`'s own Migration Summary recommendation, not a new idea introduced here.
5. **Business confirmation:** None required to build the core capability. The `cookiedecode()` crash gets fixed as part of the port (a confirmed bug, not behavior worth preserving — same "don't port confirmed crashes" treatment already applied throughout this project), not treated as needing sign-off.
6. **Dependencies:** Wave 0/1 shared-core only.
7. **Recommended execution order:** independent of Wave 5 — can run before, during, or after it. Suggest after Wave 5's own items are underway, since Wave 5 is the current focus, but nothing technical blocks doing it in parallel if capacity allows.
8. **Changes the critical path?** No. It's a third, independent thread alongside Blueprint's existing Content-path/Admin-path split — no dependents, no new dependencies, doesn't lengthen or gate either existing chain.

---

## 2. `questionnaire/`

1. **In scope?** Yes. Confirmed real, functional, read-only. Blueprint §7 names it explicitly ("Questionnaire Review").
2. **New task, merge, or out of scope?** New task (5.8) — kept separate from 5.4's soundcloud/youtube/locations batch since it's a structurally different shape (read-only viewer over `estebian`, not a settings/CRUD page), not because it's harder.
3. **Effort: Small.** Single table, no writes, no known dead code.
4. **Reusable components:** `AdminGuard` (exists). A small `QuestionnaireResponse` Eloquent model — **Blueprint §6 already names this exact model** in its "plain Eloquent models, no aggregate-boundary analysis needed" list, alongside `Category`/`Channel`/`Location`/`Uploader`. No new naming or modeling decision required; Blueprint anticipated this precisely.
5. **Business confirmation:** None.
6. **Dependencies:** Task 5.3 (permission gate) — soft dependency; the model/controller could be built earlier, but should wire into the real Spatie permission check once 5.3 lands rather than inventing a one-off gate.
7. **Recommended execution order:** alongside/after 5.4, once 5.3 is in place.
8. **Changes the critical path?** No — small, parallel-track addition within Wave 5, doesn't gate or extend anything else.

---

## 3. `khotab/uploader(s).php` (Upload-Team Tracking)

1. **In scope?** Yes, with one new nuance found this pass: `uploader.php`'s "add new uploader by forum-member-id" form (`vbuid` field) has **no server-side POST handler anywhere in the file** — read in full this pass, confirmed. This isn't quite the same as `locations/add.php`'s pattern (an INSERT that was written then commented out, clear evidence of intent) — here there's no backend code at all, just the form markup. A third, previously-undocumented incomplete flow in `admincp/`, on top of the ones `admincp.md` already found. The **list + sort + recompute (`?op=update`) + vBulletin-backfill (`?op=vblink`)** parts are all confirmed real and functional, matching `admincp.md` exactly.
2. **New task, merge, or out of scope?** New task (5.9).
3. **Effort: Small-Medium.** The list/sort/recompute/backfill parts are Small; the missing add-flow needs either a product decision or a from-scratch rebuild (the intent is fairly guessable — add an uploader by vBulletin member id, the same shape as `backup/index.php`'s already-working add-admin flow — but nothing in this codebase shows what the finished version was meant to do), which pushes the total to Small-Medium rather than pure Small.
4. **Reusable components:** the existing `vbulletin` DB connection (Wave 0). A small `Uploader` Eloquent model — **also already named in Blueprint §6's plain-model list**, same as `QuestionnaireResponse` above. The recompute/backfill operations fit this project's `Actions/` convention (single-purpose write use-cases, Blueprint §1's own domain-internal structure) rather than controller methods.
5. **Business confirmation:** Recommended, not blocking — confirm the intended behavior of the missing add-uploader flow before building it fresh, rather than guessing. Everything else needs no confirmation.
6. **Dependencies:** Wave 1's khotab model graph (`KhotabItem`, already built — the recompute correlates against `nuke_islamic_khotab.uploader`), the `vbulletin` connection (exists), task 5.3.
7. **Recommended execution order:** after 5.5 (chat's working half), unchanged from the original analysis — no new evidence changes this ordering.
8. **Changes the critical path?** No.

---

## 4. The admincp authentication transition (session vs. cookie)

This item is different in kind from the other three — it isn't a legacy capability to port, it's a finding about the *current state* of legacy code that could have implied rework for already-built Laravel code. Direct verification this pass resolved it cleanly:

- **`AdminGuard`/`AdminUser`/`LegacyPasswordVerifier` (Wave 0, already shipped) were checked directly against the current `admincp/index.php`, not assumed compatible.** Result: `AdminGuard` is **already** purely session-based (`$this->request->session()->put(...)`) — it was never built around the old cookie mechanism in the first place. `AdminUser`'s own docblock states its column shape ("id, uid, aid, name, email, pwd, password, thumb, admlanguage, radminsuper, permissions") and `password`-over-`pwd` priority were "confirmed from `admincp/index.php`'s own login query" — an exact match to the current file, not the older version `admincp.md` describes. `LegacyPasswordVerifier` reproduces the bcrypt/MD5/SHA1 detection exactly, and **already deliberately excludes** the plaintext fallback per Blueprint §16 item 3 ("Laravel side never has this fallback at all").
- **Conclusion: no gap exists.** The Wave 0 Identity-domain work already matches the current legacy behavior precisely — the recent legacy-side rewrite (toward session state) has, if anything, moved the legacy code *closer* to what Laravel already independently implemented, not further away.

1. **In scope?** Already fully in scope and already done — this resolves to **Implemented**, not a new item.
2. **New task / merge / out of scope?** None of the three apply — there's nothing left to build.
3. **Effort:** None.
4. **Reusable components:** N/A — `AdminGuard`, `AdminUser`, `LegacyPasswordVerifier` already exist and are confirmed correct; no changes needed.
5. **Business confirmation:** None for the Laravel work. **Operational note, not a confirmation:** `admincp/`'s core files cluster around several 2026 modification dates (Jan/Feb/June), well after this migration's audit baseline — someone appears to be actively maintaining/patching the legacy admin panel in parallel with this migration. Worth a quick check with the business on who's making these changes and whether Wave 5 work should coordinate with them, purely to avoid the Laravel port and an in-flight legacy change targeting the same page at the same time — not a blocker, just worth knowing.
6. **Dependencies:** None new.
7. **Execution order:** N/A.
8. **Changes the critical path?** No.

**Recommended documentation action:** correct `admincp.md`'s login-flow narrative (§2's sequence diagram, §5's cookie-only framing) with a visible addendum noting the current session-based state — the same "correction note, not silent rewrite" pattern already used for `radio.md`/`categories-and-vars_categories.md`. This is a module-doc fix, not a Blueprint/Roadmap change.

---

## 5. Found while completing the classification (not one of the 4 requested — flagged transparently)

**`broadcasting/`'s working half (`edit_stream.php`, satellite-channel livestream code management) has no Roadmap task anywhere.** Checked directly: the only two Roadmap mentions of `broadcasting/` are both citing its *dead* files (`delete_stream.php`, `edit_author.php`'s no-op save) as examples of what not to port, inside task 5.6's text — a citation, not a task assignment. Its real, confirmed-functional capability (admincp.md: "Editing is functional... admin-side counterpart to `channels.md`'s public consumption") has never been assigned anywhere. Since the "nothing unclassified" requirement in your request would otherwise leave this capability unclassified, it's included here rather than silently left out.

1. **In scope?** Yes — real, functional, cross-references an already-analyzed Wave 4 table (`nuke_sat_channels.streamcode`).
2. **New task, merge, or out of scope?** New task (5.10) — small, structurally similar to 5.4's batch (a single settings-editing page), could reasonably be grouped with it, but kept as its own number to match the granularity already used for the other new items.
3. **Effort: Small.** One working file (`edit_stream.php`), the dead files explicitly excluded.
4. **Reusable components:** `Channel` model (already exists, shared with `live-stream`/`channels`, Wave 1/3/4) — `edit_stream.php` writes the same `nuke_sat_channels.streamcode` column that module already models. No new model needed at all, just a controller action.
5. **Business confirmation:** None.
6. **Dependencies:** `Channel` model (exists), task 5.3.
7. **Recommended execution order:** alongside 5.4/5.8, since it shares 5.4's shape and has no dependency on anything later in the sequence.
8. **Changes the critical path?** No.

---

## Recommended execution order (all 5 items folded into the Wave 5 sequence)

5.1 → 5.2 → 5.3 → 5.4 → 5.8 (`questionnaire/`) → 5.10 (`broadcasting/`'s working half) → 5.5 → 5.9 (`khotab/uploader(s)`) → 5.6 → 5.7, with `surveys/` (item 1) run independently, any time, not numbered into this sequence at all.

**No change to Wave 5's critical path.** Every new item is either a small, dependency-light addition within the wave (5.8, 5.9, 5.10) or entirely outside it (`surveys/`). Blueprint's own Critical Path section (Content path / Admin path, reconverging only once both finish independently) is unaffected — none of the 5 items sit on either chain in a way that lengthens it.

---

## Proposed minimal Blueprint/Roadmap amendments

All additive, no existing task rewritten, no Part II decision changed — same discipline as the post-Wave-4 amendments.

1. **`00-implementation-roadmap.md`, Wave 5:** append **5.8 — `questionnaire/`**, **5.9 — `khotab/uploader(s).php`**, **5.10 — `broadcasting/`'s working half (`edit_stream.php` only)**. **✅ Applied 2026-08-06** — also cleaned up a pre-existing citation error found while inserting: task 5.6's text previously cited `broadcasting/`'s dead files as if they were part of `authors`/`backup`'s scope; moved to 5.10 where they actually belong.
2. **`00-implementation-roadmap.md`, Wave 3:** append **3.4 — `surveys/` (public PHP-Nuke polls)**, following the same "added post-Wave-N" convention as Wave 4's own post-hoc additions, even though Wave 3 itself is long since closed — precedent already set, not a new pattern. **✅ Applied 2026-08-06.**
3. **`00-master-migration-blueprint.md` §18:** append the 3 new Wave 5 tasks to the Wave 5 Contents cell, and `surveys/` to the Wave 3 Contents cell. **✅ Applied 2026-08-06**, with a dated revision-history entry at the document's own top, per its "no silent edit" rule.
4. **No Blueprint §4/§6/§7 changes needed** — every new model this proposal names (`Poll`, `PollOption`, `QuestionnaireResponse`, `Uploader`) is **already** present in Blueprint §6's model list, and §7's domain mapping already correctly separates `admincp/survey/` from public poll voting. The gap was purely a missing Roadmap task in every one of these 5 cases, not a missing or wrong Blueprint decision — a reassuring consistency finding, not just a list of fixes. **Confirmed, no action needed.**
5. **`admincp.md` (module doc, not Blueprint):** add a correction note to §2/§5 recording the current session-based login state, per item 4 above. **✅ Applied 2026-08-06.** Also added a small cross-reference note to `surveys.md` recording its new task 3.4, not originally proposed here but the same class of fix.

---

## Final classification — every legacy Admin-domain (and now cross-referenced Engagement) capability

| Capability | Classification | Basis |
|---|---|---|
| Admin login/session (`index.php`, `AdminGuard`) | **Implemented** | Wave 0, re-verified this pass against current legacy source — exact match |
| Permission-gated nav (`sidebar.php`'s real half) | **Planned** (task 5.3) | Working legacy mechanism, needs Spatie replacement per ADR-0010 |
| Dashboard chrome (`home.php`, `navigation_menu.php`'s demo content) | **Dead/Unreachable** | Confirmed zero real data anywhere in either file (admincp.md §5); Blueprint §17 "Can be removed" |
| 4 login UI variants beyond the 1 in active use | **Dead/Unreachable** → **Out of scope** | Blueprint §17 "Can be removed" |
| Survey Engine (`admincp/survey/`) | **Planned** (5.1, 5.2) | Confirmed complete, working; Blueprint §4 explicit |
| Permission-editor template (5 copies, 3 broken) | **Planned** (5.3), replace not port | ADR-0010 "Can be replaced" |
| `soundcloud/`, `youtube/` | **Planned** (5.4) | Confirmed simple, working |
| `locations/` edit+delete | **Planned** (5.4) | Confirmed working |
| `locations/add.php` | **Planned** (5.4), rebuilt not ported | Confirmed dead (commented-out INSERT) |
| `chat/index.php` + `edit_room.php` | **Planned** (5.5) | Confirmed working, correct source (Pattern B) |
| `chat/edit_author.php` | **Planned** (5.3's replacement covers it) | Confirmed broken copy of the permission-editor template |
| `chat/automation_room.php` | **Dead/Unreachable** → **Out of scope** | Confirmed orphaned, zero inbound references, ~90% unmodified theme boilerplate |
| `authors/`, `backup/` (staff management) | **Planned** (5.6) | Real capability, 2 broken/duplicated implementations to consolidate |
| Hardcoded default admin password | **Planned** (5.7, folded into 5.6) | Confirmed anti-pattern, ADR-0010 "Can be removed" |
| `nuke_backup_booking` UI | **Deferred** | Business Confirmation #7 (active need or abandoned?) |
| `khotab/index.php`, `telawah/index.php` (misplaced content) | **Out of scope for this codebase** | Confirmed absent real CRUD (Pattern B); real khotab/telawah admin CRUD is **Deferred** pending Business Confirmation #6 |
| `khotab/uploader(s).php` list/recompute/backfill | **Planned** (new, 5.9) | Confirmed functional |
| `khotab/uploader.php`'s add-uploader form | **Deferred** | No backend exists to port; needs a product decision on intended behavior (§3 above) |
| `khotab/stats*.php`, `telawah/stats.php` | **Out of scope for this proposal** | Not requested; carries known Pattern E/F performance issues (`admincp.md` §8) — recommend its own small follow-up analysis when Wave 5 reaches it, not silently deferred by omission |
| `questionnaire/` | **Planned** (new, 5.8) | Confirmed functional |
| `broadcasting/edit_stream.php` | **Planned** (new, 5.10) | Confirmed functional |
| `broadcasting/delete_stream.php`, `edit_author.php` | **Dead/Unreachable** → rebuilt, not ported, as part of 5.10's scope | Confirmed dead (`die()`, commented-out query) |
| `forumConfig/` | **Out of scope** | Blueprint §18 "Never," confirmed never executed |
| `surveys/` (public PHP-Nuke polls) | **Planned** (new, 3.4) | Confirmed functional (raw path), Blueprint §6/§7 already named it, no Roadmap task existed |
| `surveys/`'s `cookiedecode()` crash | **Planned**, fixed not ported, as part of 3.4's scope | Confirmed live production bug (`surveys.md`) |
| `admincp/survey/`'s custom engine's public voting UI | **Dead/Unreachable — genuinely absent, not merely unreachable** | Exhaustive grep confirms zero references to `nuke_survey*` tables outside `admincp/`; nothing to port |

**Nothing above is unclassified.**

---

Waiting on your review before any documentation updates or implementation begin.
