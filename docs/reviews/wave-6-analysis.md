# Wave 6 — Fresh Architectural Analysis

**Date:** 2026-08-07. **Status: analysis only — no code or documentation changed.** Produced per your instruction to re-derive Wave 6 from direct legacy-code inspection, cross-checked against the Blueprint, Roadmap, Decision Log, Implementation Findings, PROJECT-HANDOFF, and every relevant module doc, rather than trusting what any of them currently say. Findings are reported, not fixed — every proposed change below is a proposal awaiting your approval.

**Method:** every one of Wave 6's 6 Roadmap tasks (6.1-6.6) was re-verified by reading the actual current legacy files, `.htaccess` rules, and (where they exist) directory listings — not by re-reading the audit's prior conclusions and assuming they still hold. Several genuinely new findings resulted; they're marked **NEW** below. Everything else is a confirmation that a prior finding still holds, cited to what was actually checked this round, not merely re-asserted.

---

## 0. Headline finding: every Wave 6 task is still gated on an unresolved Business Confirmation — none are "ready to implement" in the normal sense

Cross-checked all six tasks against Blueprint Part IV and against every document produced through Wave 5: **not one of Confirmations #1, #2, #3, #4, or #6 has been resolved.** This isn't a gap in Wave 0-5's work — Appendix F explicitly says not to touch Wave 6 early, and nothing did. But it means the honest classification for most of Wave 6's capabilities is **Business confirmation required**, not a design or implementation gap. Where I found evidence this round that meaningfully sharpens (though doesn't formally resolve) a confirmation, it's called out per task below — several of these are strong enough that whoever owns the confirmation should see them before answering, not after.

---

## Task 6.1 / 6.2 — `fatawa` / `advanced-search`

**Legacy re-verification performed:** re-read `.htaccess`'s `fatawa-*`/`advanced_search` rules, confirmed both `modules.php` and `new_modules.php` are absent from the working tree, re-read `fatawa/single.php`/`answer.php` directly, and re-read the full `old.way2allah.com` investigation and ADR-0009.

- **Confirmed unchanged:** `modules.php`/`new_modules.php` still do not exist (`ls` both fail). 23 of `fatawa`'s pretty-URL rules route through `modules.php?name=Fatwa`; `advanced_search.htm` routes through `new_modules.php?name=advanced_search`. Neither dispatcher exists, so every one of these routes is dead on the current codebase, exactly as documented.
- **Confirmed unchanged, live SQL injection:** `fatawa/single.php:14-15` and `fatawa/answer.php:21` still build `WHERE id=$q` from raw `$_GET['q']` with no `intval()` — unpatched, matches Blueprint §16 item 5's "Open" status exactly, re-verified line-for-line.
- **NEW, worth surfacing to whoever answers Confirmation #1:** `old-domain-investigation.md` (already in the repo, predates Wave 5) is stronger evidence than either the Roadmap or `fatawa.md`/`advanced-search.md` currently foreground. It directly observed, via live HTTP checks: `old.way2allah.com/fatawa.htm` returns a full working page whose title is byte-for-byte identical to this codebase's own hardcoded string; `old.way2allah.com/search.htm`/`advanced_search.htm` both return 200; and — new detail worth naming explicitly — **the old domain's own search form POSTs to `https://way2allah.com/search.htm`, the *current* domain, not itself.** That's a live, deliberate cross-domain integration point, not a frozen relic. Combined with the hit-counter test's content-freshness finding (a specific khotab item dated ~10 days old at test time, present identically on both domains), this is meaningfully stronger evidence that `fatawa`/`advanced-search` are real, currently-used business capabilities (just served from `old.way2allah.com`, not this codebase) than "reachability unconfirmed" conveys. It doesn't resolve Confirmation #1 by itself — ADR-0009 already correctly declined to treat `old.way2allah.com` as authoritative without business/ops input — but it should materially raise the odds assigned to "still needed" when the question is actually asked.
- **Classification: Business confirmation required** (#1) for both tasks, unchanged from the Roadmap — but the confirmation itself should be asked with the `old-domain-investigation.md` evidence attached, not asked cold.
- **No scope, dependency, or architecture change proposed.** The Roadmap's existing task shape (full migration if live, reclassify if dead, SQL-injection fix independent of either outcome) is still correct.

---

## Task 6.3 — `pages/ramadan.php` + `help/share.php`

**Legacy re-verification performed:** re-read `.htaccess`'s `ramadan.htm`/`share.htm`/`channels-ramadan.htm` rules, grepped the whole codebase for `channels_ramadan` implementation.

- **Confirmed unchanged:** both routes still point at the missing `new_modules.php`, matching `pages.md`/`help.md`.
- **NEW, small Roadmap gap:** `.htaccess:316` — `channels-ramadan.htm` → `new_modules.php?name=Satellite&op=channels_ramadan` — is a third `new_modules.php`-dependent, ramadan-related route, in the `channels` module's namespace, not currently named in task 6.3 (which only covers `pages/ramadan*.php` + `help/share.php`) or anywhere else on the Roadmap. A repo-wide grep for `channels_ramadan` found **zero** matching code anywhere — not dead-but-present code, no code at all. This is a pure `.htaccess` rule with nothing behind it even if the dispatcher existed.
  - **Classification: Dead code** (the route itself — no implementation exists to reclassify as live or dead-but-present). Proposal: add one line to task 6.3 (or a standalone note) recording this route as confirmed-unimplemented, so it isn't silently dropped or silently assumed to be part of `channels`' already-completed Wave 4 migration.
- **Classification for the task itself: Business confirmation required** (#2), unchanged.

---

## Task 6.4 — khotab/telawah admin CRUD

**Legacy re-verification performed:** listed `admincp/khotab/` and `admincp/telawah/` in full, read both `index.php` files completely, diffed both against `admincp/chat/index.php`, read `khotab/menu.php`, `telawah/menu.php`, and the header of all `stats*.php` files in both directories.

This task got the most new evidence of the six, and it changes the shape of Business Confirmation #6, not just its evidence base.

- **NEW: `admincp/khotab/index.php` and `admincp/telawah/index.php` are not "missing CRUD" — they are broken, misnamed, copy-pasted duplicates of `admincp/chat/index.php`.** A direct diff shows both are near-identical to `chat/index.php`: same `SELECT * FROM room ORDER BY enable DESC, sequence ASC` query against `$chatdb` (the FlashChat connection — nothing to do with khotab/telawah content at all), same "open rooms / closed rooms" table layout, even the same room-status icons markup. The only differences are two regressions relative to `chat/index.php`: they reference `$room->id` (chat's real column is `room_id` — this would emit a PHP notice and render blank on every row) and link to `edit_author.php?op=liststuff` instead of `chat/index.php`'s real `edit_room.php?op=edit_room`. `khotab/menu.php` compounds this: its nav array uses `op=addroom`/`op=listrooms` (chat's own vocabulary) but labels `addroom` as **"اضافة مادة جديدة"** ("add new material") — i.e., someone edited the *label* to look like content management without changing the underlying (chat-room) `op` values or building any corresponding logic. This is stronger and more specific than the existing "Pattern B: misplaced content" classification — it's not merely absent, it's actively wrong, unrelated code sitting where content CRUD would be.
  - **Classification: Dead code**, with direct evidence (not inferred from a menu.php permission key, as the existing classification was). Whoever answers Confirmation #6 should know explicitly that there is nothing usable to build on here, including as a rough starting point — not even broken khotab-shaped code exists, only a broken duplicate of a different feature.
- **NEW: real, working khotab/telawah-specific admin capability exists that is on neither Wave 5's task 5.9 nor Wave 6's task 6.4 — a mirror/link-quality stats-and-repair tool, 4 files.** `admincp/khotab/stats.php` unconditionally runs `UPDATE nuke_islamic_mirror SET percent = (online/linksize)*100` on every page load, then lists/repairs mirror-link records via `getsizeid`/`fixsize` GET params. `admincp/khotab/stats_khotab.php` does the identical pattern against `nuke_islamic_khotab` directly; `admincp/khotab/stats_khotab_200mb.php` is byte-for-byte the same query as `stats_khotab.php` (a third, undifferentiated duplicate — worth a full read before building, to confirm whether the "200mb" distinction is a real filter further down the file or genuinely dead duplication). `admincp/telawah/stats.php` runs the same pattern against `nuke_telawah_telawah`. None of these touch `nuke_uploaders` (task 5.9's table) — this is a materially different table/capability.
  - **Classification: Needs clarification** — this is a real, distinct, currently-undocumented-on-any-task capability. Proposal: either fold it into task 6.4's scope explicitly (it's much smaller and much better-evidenced than the CRUD question, and isn't blocked by the "design fresh vs. `old.way2allah.com`" fork task 6.4's own gate exists for), or give it its own task number. Recommend the former — this doesn't need Confirmation #6 to build, since it's a straightforward, already-working legacy pattern (unconditional recompute + paginated repair list), not a CRUD design question.
- **Confirmed unchanged:** `telawah/menu.php` has no add/edit link at all, only `stats.php` — directly confirms telawah has zero admin content-management surface of any kind, consistent with the existing classification.
- **Classification for the CRUD question itself: Business confirmation required** (#6), unchanged — but the "design fresh" option is now more clearly the only real option, since "source from `old.way2allah.com` as reference" would require someone with `old.way2allah.com` admin credentials to look at its khotab/telawah admin screens specifically, which this audit has explicitly never attempted (ADR-0009 §, no login attempts).

---

## Task 6.5 — `chat_room`'s live-room half

**Legacy re-verification performed:** read `chat_room/room.php`, `chat_room/chat_rooms.php`, `chat_room/chat_room.php` (full), `chat_room_script_block()` in `functions.php`, and every `.htaccess` rule touching `chat_room/`, `chat-rules`, `alhedaya`, and `hedaya`.

This task's Confirmation #4 ("live voice-room vs. the Zoom flow that may have superseded it") got a materially sharper, mixed-evidence answer this round — not a clean resolution, but real enough that it should reach whoever answers it.

- **NEW: direct evidence of an unfinished Zoom migration, not a completed one.** `chat_room/room.php` — the file `.htaccess` does **not** route to — contains a static Zoom meeting link (`https://zoom.us/j/3318526127`), a Zoom-branded banner image, and a Zoom-client download link, in place of any FlashChat iframe. Its call to `list_today_lessons()` (the exact function IF-033 moved into this task's scope) is commented out. This reads unmistakably as a built, Zoom-based replacement page.
- **But the pages `.htaccess` actually routes to still use the old system, unchanged:** `chat_room.htm` → `chat_room/chat_rooms.php` (confirmed via `.htaccess:348`) calls `list_chat_rooms()` (still `$chatdb`-based, still permission-gated per IF-035) and `list_today_lessons()` (**not** commented out here). `chat_{id}.htm` → `chat_room/chat_room.php?id=$id` (`.htaccess:349`) still calls `chat_room_script_block()`, which — confirmed by reading the function directly — still renders a live `<iframe src="http://188.40.113.136:35555/htmlchat/...">` (the third-party FlashChat server) when access is granted, with a `"عفوا ، الغرفة غير متاحة الأن"` ("sorry, the room isn't available now") fallback otherwise.
- **`room.php` itself is unreachable:** a repo-wide grep for `room.php` as a link/route target (outside `chat_room.php`/`chat_rooms.php` themselves) found zero references — no `.htaccess` rule, no internal link anywhere in the codebase. It's built but not wired in.
- **What this means, precisely:** someone built a Zoom-based replacement for the room-entry experience, but the site's actual live routing was never switched over to it — the reachable pages still attempt the old FlashChat embed. This is neither "Zoom has superseded FlashChat" (the Roadmap's phrasing implies) nor "FlashChat is definitely still live and used" (the alternative) — it's **evidence of intent to migrate away from FlashChat, not evidence the migration happened**, and a real risk that the reachable pages are currently serving a broken/dead third-party embed to whatever users still land on them. Whether the FlashChat server (`188.40.113.136:35555`) still responds at all is unknown — this audit did not probe a third-party IP directly, consistent with the scope discipline already applied to `old.way2allah.com` (client-owned subdomains only, never third-party infrastructure).
  - **Classification: Business confirmation required** (#4) — but the confirmation should now specifically ask "has the live room already been replaced by Zoom in practice, even though the code was never fully switched over?", not the more open-ended original framing. This is new, decision-relevant evidence, not a restatement.
- **NEW architectural finding — a real reuse opportunity is blocked by an already-enforced boundary rule.** Wave 5 already built `App\Domain\Admin\Models\Room` (`flashchat` connection, `ownerUsernames()`/`speakerUsernames()`/etc.) — exactly the model task 6.5's public-facing controller would need. But Blueprint §2's boundary rule — **"the Content domain must not depend on the Admin domain's internals"** — is a real, currently-passing Pest arch test (`tests/Architecture/DomainBoundaryTest.php:38-40`, confirmed by reading it directly), not just a stated intention. Task 4.11's already-built `ChatRoomLessonController` lives in `App\Domain\Content`. If task 6.5 is implemented as a Content-domain controller (the natural continuation of 4.11, same legacy directory), it **cannot** `use App\Domain\Admin\Models\Room` without failing that test.
  - **Classification: Needs clarification** — an explicit decision is needed before implementation, not during it: move `Room` to a neutral location both domains can use (e.g. alongside `Poll`/`PollOption` in `Domain\Engagement`, which already models a not-quite-Content, not-quite-Admin, real-time/interactive capability), duplicate a second Room model in Content, or place task 6.5's controller in Engagement instead of Content. Recommend flagging this for the same review pass that resolves Confirmation #4, since the domain placement decision doesn't need the confirmation itself to be made.

---

## Task 6.6 — `english/`'s fate

**Legacy re-verification performed:** re-confirmed the single outbound link (`header.php:211`) still exists, unchanged, still the only integration point.

- **Confirmed unchanged**, nothing new. `english.md`'s vendor-integration-surface documentation (already thorough, ADR-0002-compliant) still matches the current source exactly.
- **Classification: Business confirmation required** (#3), unchanged. No scope or architecture change proposed.

---

## Roadmap completeness gap: 5 open Business Confirmations have no task at all — not just unresolved, structurally absent from Wave 6

Blueprint Part IV lists 13 Business Confirmations. Wave 6's 6 tasks map to #1, #2, #3, #4, #6. **#5, #7, #9, #10, and #13 have no Roadmap task anywhere** — not deferred, not out of scope, simply never given a task number. Re-checked each directly:

- **#7 — `nuke_backup_booking`'s active-use status.** This is the most consequential of the five gaps, because of a genuinely new finding this round. **NEW:** Wave 5's `AdminStaffController` docblocks and the completion report cite `admincp/backup/index.php` extensively (the working add-admin flow, `thumb` assignment, hardcoded `'way2allah'` password) as their source. **That directory does not exist in the current working tree** (`admincp/backup/` — confirmed absent via direct listing; `admincp/`'s current 10 subdirectories are `authors`, `broadcasting`, `chat`, `khotab`, `locations`, `questionnaire`, `soundcloud`, `survey`, `telawah`, `youtube` — no `backup`). What *does* exist is a same-named-but-unrelated file at the site root, `backup.php` (478 lines, not under `admincp/` at all, not linked from any `.htaccess` rule or internal PHP link — reachable only via its raw URL, consistent with being a machine API for an external desktop backup/download-automation tool). Reading it directly: it's real, working code — `SELECT ... WHERE down=... AND trial<MaxTrials AND booking=0`, then `INSERT INTO nuke_backup_booking` (a checkout/lock record) and later `DELETE FROM nuke_backup_booking` — a genuine content-item checkout mechanism for whatever external tool consumes this endpoint, not the "unclear, possibly-abandoned" characterization currently in `PROJECT-HANDOFF.md`/the Wave 5 review docs.
  - This raises a real evidence-trail question I can't resolve from this session alone: was `admincp/backup/index.php` deleted from the legacy snapshot at some point after Wave 5 read it, or was Wave 5's citation always to a file that, in this exact form, no longer matches what's on disk? I have no version history for `legacy-project` covering that period to check (its own git log is 5 commits, all pre-dating this migration effort, none touching `admincp/backup/`). **Classification: Needs clarification** — not a Wave 6 implementation question, but a real gap in the evidence trail behind an already-shipped Wave 5 task that should be resolved (at minimum, acknowledged) before treating Wave 5's `backup/index.php` citations as re-verifiable going forward.
  - Separately, and regardless of that question: `backup.php`'s real `nuke_backup_booking` mechanism is itself new, directly-read evidence for Confirmation #7 — it's a genuinely working, actively-shaped feature (booking/trial/unbooking semantics), not evidence of an abandoned table. Whoever answers #7 should see this code, not just the "bolted onto an unrelated staff-list page" characterization currently on record. **Proposal:** give #7 its own Roadmap task (even a small one), now that there's real code to point it at.
- **#5 — `nuke_users`/`vb5.php`'s disabled password check.** Re-confirmed directly: `verifyNukePassword()` still exists (`vb5.php:32`) and its only call site is still commented out (`vb5.php:253`), unchanged since it was last checked. Blueprint §16 item 2 and §9 both point to "§11.4" for resolution — **NEW: §11.4 does not exist.** The Blueprint's own §11 ("Legacy URL Compatibility & SEO") has no numbered subsections at all; this is a dangling internal cross-reference, appearing twice, that should actually point to Business Confirmation #5 in Part IV. **Classification: this specific citation is documentation drift** (small, mechanical) — the underlying confirmation (#5) is correctly **Business confirmation required**, just with no Roadmap task, same gap as #7.
- **#9** (live DB read for weak/plaintext `nuke_authors` credentials) **and #13** (URL-redirect-subset value question) are not code-verifiable at all — #9 needs direct database access this audit has never had, #13 needs SEO/analytics input, not source reading. Confirmed no Roadmap task exists for either; correctly so, given neither is resolvable by implementation work. **Classification: Business confirmation required**, no task needed until answered.
- **#10** (Survey-close-time read consistency) was explicitly deferred in Blueprint Part III pending this same confirmation, and Wave 5 built `Survey`/`SurveyAnswer` without addressing it (consistent with the deferral, not an oversight). **Classification: Depends on another task** — specifically, it depends on #10 being answered, and only then on revisiting Wave 5's `SurveyAnswer`, not on any Wave 6 task. No change proposed.

**Proposal for this section as a whole:** add two small Roadmap entries — one for #7 (khotab/telawah's sibling "small, real, already-working" pattern, per this round's stats.php finding, could plausibly sit next to task 6.4) and a placeholder noting #5's disabled password check needs a task once ADR-0011's design work happens — and fix the dangling "§11.4" citation in two places. Everything else in this section is correctly gated on business/infrastructure input no amount of further code reading will produce.

---

## Summary classification table

| Item | Classification | Evidence basis |
|---|---|---|
| `fatawa` full migration | **Business confirmation required** (#1) | Unchanged; `old-domain-investigation.md` evidence should accompany the ask |
| `fatawa` SQL injection fix | **Ready to implement** (legacy-side fix, independent of #1's outcome) | Re-confirmed live, unpatched, `single.php:15`/`answer.php:21` |
| `advanced-search` full migration | **Business confirmation required** (#1) | Same gate as `fatawa` |
| `advanced-search` SQL-injection-adjacent fix | **Ready to implement** (legacy-side, independent of #1) | Not re-verified this round (already Fact-tagged in `advanced-search.md`, no new evidence needed) |
| `channels-ramadan.htm` (`op=channels_ramadan`) | **Dead code** | **NEW** — zero implementation anywhere, confirmed by repo-wide grep |
| `pages/ramadan.php` + `help/share.php` | **Business confirmation required** (#2) | Unchanged |
| khotab/telawah `index.php` "CRUD" (`admincp/khotab/`, `admincp/telawah/`) | **Dead code** | **NEW** — confirmed broken duplicate of `chat/index.php`, not absent-but-implied |
| khotab/telawah mirror/link-quality stats tool (4 files) | **Needs clarification** (scope placement) | **NEW** — real, working, currently unassigned to any task |
| khotab/telawah real content CRUD (add/edit khotab & telawah items) | **Business confirmation required** (#6) | Unchanged in substance; "design fresh" now the only realistic option |
| `chat_room`'s live-room half (routes, iframe, room list) | **Business confirmation required** (#4) | Sharpened this round — evidence of unfinished Zoom migration, not completed |
| `chat_room/room.php` (Zoom-based page) | **Dead code** (currently — unrouted) | **NEW** — confirmed unreachable via any route or internal link |
| `Room` model domain placement for task 6.5 | **Needs clarification** | **NEW** — blocked by a real, passing arch test, not a hypothetical concern |
| `english/`'s fate | **Business confirmation required** (#3) | Unchanged |
| `nuke_backup_booking` real mechanism (`backup.php`) | **Needs clarification** (scope + evidence-trail gap vs. Wave 5) + feeds **Business confirmation required** (#7) | **NEW** — real code found, not previously read by any prior session |
| `admincp/backup/` (Wave 5's cited source) | **Needs clarification** | **NEW** — directory does not exist in current working tree; discrepancy vs. Wave 5's own citations unresolved |
| `vb5.php`'s disabled password check | **Business confirmation required** (#5), no task yet | Unchanged; Blueprint's own cross-reference to it is broken ("§11.4") |
| Survey-close-time consistency | **Depends on another task** (#10, then a Wave 5 revisit) | Unchanged, correctly deferred |
| DB-read-only confirmations (#9) / SEO-value question (#13) | **Business confirmation required**, no task needed | Not code-resolvable |

---

## What I'm proposing, pending your approval — nothing has been changed yet

1. Attach `old-domain-investigation.md`'s specific evidence to how Confirmation #1 is actually asked (not a document change — a note on how the question gets posed).
2. Add one line to task 6.3 (or `00-unknowns.md`) recording `channels-ramadan.htm`/`op=channels_ramadan` as confirmed dead-with-no-implementation.
3. Strengthen task 6.4's evidence base with the `chat/index.php`-duplicate finding (upgrades "Pattern B: misplaced content" to a direct, cited "dead/wrong code" finding) and decide where the mirror/link-quality stats tool (4 files) gets a home — proposed: fold into task 6.4, your call if you'd rather it stand alone.
4. Sharpen Confirmation #4's actual question per the Zoom-migration-in-progress finding, and flag the `Room`-model domain-placement decision as something to resolve alongside it, before task 6.5 starts.
5. Give Confirmation #7 a real Roadmap task now that `backup.php`'s working `nuke_backup_booking` mechanism has been read directly — and separately, resolve (or explicitly accept as unresolved) the `admincp/backup/` discrepancy against Wave 5's own citations.
6. Fix the dangling "§11.4" cross-reference in two places (Blueprint §9 and §16) — should point at Business Confirmation #5, Part IV.

None of this changes any Wave 6 task's fundamental gating (every task still needs its Business Confirmation before implementation) — it sharpens the evidence each confirmation should be answered with, and surfaces two areas (the mirror-stats tool, the backup-booking mechanism) that don't need a confirmation at all and could be scoped independently.
