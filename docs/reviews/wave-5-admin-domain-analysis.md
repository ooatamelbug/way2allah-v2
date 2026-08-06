# Wave 5 (Admin Domain) — Analysis and Implementation Plan

**Status:** Superseded, in the specific sense that its §7 proposed amendments were carried forward and refined by `docs/reviews/wave-5-gap-reconciliation-proposal.md` (which answered the user's 8 reconciliation questions per item and is the document whose amendments were actually applied — see `wave-5-doc-sync-verification.md`). This document's own findings (§1-§6) remain the primary evidence record for Wave 5 and are unchanged/still accurate. Left as originally written, not rewritten.

**Method:** Blueprint §4/§6/§7/§9/§16/§17/§18 and Part IV re-read directly (not recalled). Roadmap Wave 5 (tasks 5.1-5.7) re-read directly. The existing `admincp.md` audit (61 files, already very thorough) was used as a map, but every claim that drives a scope or priority decision below was re-verified against the actual current legacy source in this pass — not assumed current from the audit alone. File modification times were checked first specifically to catch drift since `admincp.md` was written.

---

## 1. Headline finding: `admincp/`'s core login file has been substantially rewritten since the existing audit

Checking file mtimes before reading anything surfaced this immediately: `admincp/index.php` (2026-06-08), `authors/edit_author.php` (2026-02-16), `main.php`/`home.php`/`backup/index.php`/`authors/index.php` (2026-01-07) are all newer than every other file in the directory (baseline 2025-01-24, which is when `admincp.md`'s audit reads were performed). `survey/`, `soundcloud/`, `youtube/`, `locations/`, `chat/` are all still at the 2025-01-24 baseline — untouched since the audit.

Direct re-read of `admincp/index.php` (the file with the most recent mtime) confirms real, substantive changes:

- **Confirmed unchanged**: the bcrypt → MD5 → SHA1 → plaintext password-verification fallback chain (still present, same 4-tier shape `admincp.md` §5 documented).
- **New since the audit**: login now builds a `$_SESSION['w2a']` object (`->admin`, `->usertype`, `->vbuser`) mirroring the public site's own session shape, in addition to (not instead of) the original 7-cookie mechanism the audit described. `usertype` is now derived from a `radminsuper` flag (`'superadmin'` vs `'admin'`) — a simpler two-tier role signal layered on top of the old granular `nuke_authors.permissions` array, not documented anywhere in the prior audit.
- **New since the audit**: a debug logger, `w2a_debug_log()`, writing to `admincp/_login_debug.log` — not present in the audited version.
- **Confirmed unchanged**: `main.php`'s two live `var_dump()` calls (still present, still fire on every admin page load, before any login check completes) and `authors/index.php`'s add-admin `die('hhhh')` (still present, still unreachable).
- **New, and architecturally significant**: `main.php`'s page-gate still checks the *old* mechanism (`UserType($_COOKIE['newadmin'])`), not the *new* `$_SESSION['w2a']` state `index.php`'s login() now sets. The new session state is written but not yet the thing that actually gates subsequent page loads — two partially-integrated auth signals coexist right now, mid-transition.

**Why this matters for planning:** the existing audit's login-flow narrative (§2's sequence diagram, the "four cookies, no session" framing) is now the wrong mental model for `index.php` specifically, though its dead-code/security findings for the *other* recently-touched files (`authors/index.php`'s `die('hhhh')`, `authors/edit_author.php`'s weak MD5 password write, `backup/index.php`'s working duplicate) all still hold — directly re-verified, not assumed. Task 5.7 ("admin account creation: no fixed default password") and Business Confirmation #9 (weak/plaintext credentials) should be scoped against this current, partially-modernized state, not the audit's snapshot.

---

## 2. Critical finding: two entirely unrelated "survey" systems exist, and the admin-built one has no public consumer anywhere in this codebase

This surfaced while verifying Roadmap task 5.1/5.2's scope directly.

- **`admincp/survey/*`** (the Roadmap 5.1/5.2 target): a real, well-built admin CRUD system over `nuke_survey`/`nuke_survey_questions`/`nuke_survey_answers` — 11 question types, vBulletin-usergroup audience targeting, per-question and full-survey stats. Confirmed directly (`survey/index.php` re-read): delete works with a real dependent-question guard, links to `add_survey.php`/`add_question.php`/`stats.php`/`all_stats.php` all check out. This matches Blueprint §4's own description exactly: *"Survey/SurveyQuestion/SurveyAnswer model set | `admincp/survey/`'s existing engine | Admin domain."*
- **Its own public voting link goes nowhere in this codebase.** `admincp/survey/index.php` itself links each survey to `https://way2allah.com/survey/?id={id}` (singular `survey/`) — confirmed via `find`/`ls` that no such directory exists anywhere in this codebase snapshot. An exhaustive repo-wide grep for `nuke_survey`/`nuke_survey_questions`/`nuke_survey_answers` outside `admincp/` returns **zero matches**. The custom survey engine has admin tooling and nothing else — no public frontend to port, because none exists in this snapshot.
- **A completely different, already-fully-analyzed module — `surveys/` (plural)** — is the real "public poll voting" capability on this site: PHP-Nuke's own native poll system, over `nuke_poll_desc`/`nuke_poll_data`/`nuke_poll_check` (fixed 12-option-slot model, IP-based 30-minute vote deduplication). Confirmed directly (`surveys/polls.php`, `functions.php` re-read) and cross-checked against the existing, already-thorough `surveys.md` (lifecycle: **Analyzed**, not a stub). This is what Blueprint §6's `Poll`/`PollOption` aggregate and §7's *"`surveys` | Engagement | Public poll voting — distinct from `admincp/survey/`"* row actually refer to.
- **`surveys/` is reachable, but only via its raw file path** (`/surveys/polls.php`, `/surveys/item.php?pollID=...`) — every one of its intended pretty URLs (`survey-{id}.htm`, `surveys.htm`, etc.) routes through `.htaccess` to `new_modules.php`, the same confirmed-absent dispatcher IF-026 already found breaks ~130 other routes. Same "raw-path-only, needs a route + no pretty-URL redirect exists to protect" profile this project has handled repeatedly (`khotab/dump.php`, `pages/social.php` pre-fix).
- **`surveys/` also carries a confirmed, currently-live production bug**, already documented in `surveys.md` §3/§5/§8: `cookiedecode()` is called (`functions.php:27`, inside `pollMain()`) but is **not defined anywhere in the codebase** — a fatal error for any logged-in vBulletin visitor who views a voting form. Not new evidence this pass, but directly relevant here since it sits on the same capability the Roadmap gap below concerns.

**The gap:** Blueprint's own architecture already correctly separates and names both systems (§4 for the admin engine, §6/§7 for the public `Poll`/`PollOption` capability) — but **no Roadmap task anywhere in Waves 0-6 covers `surveys/` (the public one)**. It's fully analyzed, real, has a live production bug, and is simply absent from the task list. This is the same shape of gap the post-Wave-4 coverage audit found for `radio`/`chat_room`'s content half/`pages/social.php` — just in the Engagement domain instead of Content, and surfaced now instead of then because Wave 5's own scope never touched Engagement before.

---

## 3. Two more Blueprint-named Admin-Operations capabilities have no Roadmap task

Blueprint §7's bounded-context table states: *"Admin Operations | Permission model, Survey Engine, Location Directory, **Questionnaire Review**, **Upload-Team Tracking** | Content (CRUD target), Identity (admin auth)"* — five named capabilities. Roadmap tasks 5.1-5.7 cover three of them (Survey Engine → 5.1/5.2, permission model → 5.3, Location Directory → part of 5.4). **The other two have no task:**

- **`questionnaire/`** ("Questionnaire Review") — confirmed directly (`index.php` re-read): a real, functional read-only viewer over the `estebian` table (da'wah-caller registrations). Matches `admincp.md`'s classification exactly. Small, low-risk, well-understood.
- **`khotab/uploader.php` / `uploaders.php`** ("Upload-Team Tracking") — confirmed present and substantial (187 + 170 lines). `admincp.md` documents this as real and functional, including a genuine vBulletin-identity backfill tool (`op=vblink`). Not independently re-read line-by-line this pass (lower risk — file is at the audit's baseline mtime, and the capability is narrow and self-contained), but its existence and scope are confirmed via direct size/grep checks, not taken on the audit's word alone.

Neither is large. Both are real, Blueprint-named, audit-confirmed-functional capabilities currently missing from the task list — not confirmed-dead code being correctly excluded.

---

## 4. Everything else in `admincp.md` re-verified and confirmed still accurate

Directly re-read and confirmed unchanged from the audit's findings: `authors/index.php`'s add-admin `die('hhhh')` (still dead), `backup/index.php`'s working near-duplicate (still works, still writes the hardcoded `'way2allah'` default password), `authors/edit_author.php`'s permission editor (still correctly reads/writes real permissions; password reset still writes plain unsalted MD5 via the modal UI added since the audit — the UI changed, the underlying `md5()` write did not), `locations/add.php`'s INSERT (still commented out — confirmed dead, false "success" redirect still fires), `khotab/index.php` (still a near-byte-for-byte copy of `chat/index.php`'s room-list content, confirmed via direct `diff` — Pattern B still holds), `soundcloud/index.php` (still a simple, working single-option settings page), `chat/menu.php` (permission keys match the audit's citation exactly).

**Not re-verified line-by-line this pass** (lower risk, unchanged mtime, narrow/self-contained scope): `youtube/index.php`, `khotab/uploader(s).php`'s internals, `chat/edit_room.php`'s internals, `broadcasting/*`, `telawah/*`. Flagged here rather than silently assumed equivalent to the audit.

**One incidental, non-blocking observation:** `locations/add.php`'s (dead) redirect target uses a `/new/admincp/...` path prefix, not the bare `/admincp/...` every other file in this snapshot uses. Possibly a leftover from an in-progress path restructuring, possibly meaningless. Doesn't change the fix (the INSERT is dead either way) — noted for awareness, not investigated further.

---

## 5. Business Confirmations relevant to Wave 5 (Blueprint Part IV)

| # | Confirmation | Relevance |
|---|---|---|
| 6 | Khotab/telawah admin content-CRUD — design fresh, or source from `old.way2allah.com`? | **Blocks nothing in this plan** — confirmed absent from this codebase (Pattern B, re-verified §4 above), correctly excluded from Wave 5's own task list already. Net-new design work, explicitly out of scope until this resolves. |
| 7 | `admincp/backup/`'s `nuke_backup_booking` concept — active need or abandoned? | Relevant to task 5.6. The table and query are real; the only UI is a query bolted onto the authors-list page. Recommend building the authors/backup consolidation (5.6) without the backup-booking UI until this confirms, same "don't build ahead of a confirmation" discipline as Wave 6. |
| 9 | Does any `nuke_authors` row hold a weak/plaintext credential? | A live DB read, not resolvable from source. Directly relevant to task 5.7 and to how urgently the plaintext-fallback/MD5-write issues need remediating on the legacy side independent of the Laravel timeline. |
| 10 | Survey-close-time consistency (`SurveyAnswer` counter-cache) | Already addressed by decision-log's own standing guidance (build the default child+counter-cache now, don't block on the edge case) — no new action needed here. |

**New confirmation candidate this plan surfaces, not yet in Part IV:** whether `surveys/` (the public PHP-Nuke poll system, §2 above) should be migrated as part of this engagement at all, given it's Engagement-domain (not Admin) and was never scoped into any wave. Recommend raising this as a scope question for the user rather than assuming an answer — see §7's proposed amendments.

---

## 6. Recommended Implementation Plan

**Execution order** (extends `admincp.md`'s own recommended ordering, which this pass's re-verification confirms is still sound — most-complete/least-dead first, defer anything needing a confirmation):

1. **Task 5.1 — `Survey`/`SurveyQuestion`/`SurveyAnswer` models.** Confirmed the best 1:1 port candidate in the whole audit — no dead code, real relationships. No blockers.
2. **Task 5.2 — `admincp/survey/` full admin CRUD.** Depends on 5.1. No blockers. **Scope note:** build the admin CRUD only — do not build a public voting UI as part of this task; none exists in the legacy source to port (§2), and whether one should exist at all is a scope question for the user (§7), not an assumption to bake into this task.
3. **Task 5.3 — One real, unified permission-editor implementation on Spatie.** Every other admin feature depends on the `nuke_authors.permissions` gate (`sidebar.php`'s nav-gating), so this should land early, matching Blueprint §9's own sequencing note. Build against the *current* `authors/edit_author.php` (the one working copy, re-verified §4) as the behavioral reference, not the 3 broken copies — this is a **replace, not port**, per ADR-0010 (ADR-0010's own Migration Decision Classification already puts this in "Can be replaced").
4. **Task 5.4 — `soundcloud`/`youtube`/`locations` (minus the dead add-flow).** All confirmed simple, working, low-risk. `locations/add.php`'s dead INSERT gets rebuilt as a real, working add-flow (not ported broken), matching the same treatment already applied to `pages/social.php`'s bugs in the gap-closure phase.
5. **`questionnaire/`** (new, §3) — small, self-contained, read-only, no dependencies beyond the permission gate (3). Natural to slot in alongside 5.4's low-risk batch.
6. **Task 5.5 — `chat/`'s working half** (`index.php` + `edit_room.php` only — `automation_room.php` excluded, confirmed orphaned/dead, ~90% unmodified theme boilerplate, don't port any of it).
7. **`khotab/uploader(s).php`** (new, §3) — self-contained, includes the vBulletin-identity backfill tool. Slot in after `chat/`'s half since it shares no dependencies with it but is comparable in size/complexity.
8. **Task 5.6 — Rebuilt `authors`/`backup`.** Collapse into one real admin-staff management feature (the two are near-duplicates today, one broken). Depends on 5.3 (permission editor) being in place first, since staff management and permission editing are adjacent concerns admins will expect in one place.
9. **Task 5.7 — Admin account creation: no fixed default password.** Small, folds naturally into 5.6's rebuild (both `authors/index.php` and `backup/index.php`'s add-flows currently write the literal `'way2allah'` default) — build as part of 5.6 rather than a separate pass.

**khotab/telawah admin CRUD** — correctly excluded from this plan entirely, per Business Confirmation #6.

**Not in this plan, flagged for a separate decision (§2, §7):** `surveys/` (public PHP-Nuke polls) — Engagement domain, not Admin, and never scoped into any wave. Recommend resolving as its own small follow-up once Wave 5 is underway or complete, not folded into Wave 5's own task numbering without the user's explicit direction.

### Reusable Laravel components

- **`AdminGuard` / `AdminUser`** (Wave 0, already built) — the foundation every Wave 5 controller sits behind. No changes needed.
- **Spatie Laravel-Permission** (installed, Wave 0) — the target for task 5.3's unified permission editor; `RoleSeeder` (Wave 0) already reproduces the `$SuperAdmins`/`nuke_authors.permissions` baseline meaning.
- **`Hash` facade** — replaces the bcrypt/MD5/SHA1/plaintext fallback chain for verification, and the MD5-only password-write path for resets (task 5.3/5.6/5.7). `AdminGuard`'s existing rehash-on-login shim (Wave 0, decision-log context) is the established pattern to extend, not reinvent.
- **`$vbdb`/vBulletin connection** (Wave 0) — every recently-verified feature directory cross-references vBulletin (`user`, `avatar`, `usergroup` tables) for identity resolution. No new connection work needed, just consistent use of the existing `vbulletin` connection.
- **`VbUserReader`** (still deliberately not built, decision-log #2) — Wave 5 will be the second real consumer of vBulletin cross-referencing after the public-site Guard. Per decision-log #2's own revised trigger ("real implementation evidence of an actual need... not a headcount"), watch during 5.1-5.7 for genuine boundary-leakage signs (raw vBulletin column names spreading across multiple new controllers) rather than building it preemptively just because a second consumer now exists.
- **No new shared service needed** for the CRUD-shaped tasks (5.4's soundcloud/youtube/locations, questionnaire, uploader tracking) — these are simple enough for direct Eloquent models + controllers, matching this project's established anti-premature-abstraction discipline.

### Risks

- **Security remediation items (Blueprint §16) concentrated in this wave** — plaintext-password fallback, MD5-only password writes, hardcoded default password, non-`httponly` cookies, hardcoded Google Maps API key. All confirmed still present in this pass, not just carried from the audit. Treat every one as a **replace, not port** per ADR-0010 — none of these should reach the Laravel side in their current form.
- **The login-flow mid-transition state (§1)** — building against `index.php`'s current session-based state without understanding *why* `main.php`'s gate still uses the old cookie mechanism risks porting a half-finished pattern as if it were the final design. Recommend a brief clarifying conversation with the user/business before task 5.3 locks in the permission-editor's auth-state assumptions, rather than guessing at the intent behind the ongoing legacy-side change.
- **Business Confirmation #9** (live weak/plaintext credentials) is unresolved and needs a real DB read, not source analysis, before assuming today's hashes are "probably all MD5 in practice."
- **`nuke_backup_booking`'s unclear active-use status** (Confirmation #7) — don't build a dedicated backup-booking UI as part of 5.6 until this resolves, per Blueprint Appendix F's confirmation-gated discipline.
- **Two of the newly-found gaps (`questionnaire/`, `khotab/uploader(s).php`) were not independently re-read to the same depth as the higher-priority items** — low risk given their narrow, self-contained scope and unchanged mtimes, but worth a fresh, full read immediately before their own implementation task starts, not assumed complete from this pass alone.

---

## 7. Proposed minimal Blueprint/Roadmap amendments

All additive, matching the pattern already used for the post-Wave-4 gap-closure amendments — no existing task rewritten, no Part II decision changed.

1. **`00-implementation-roadmap.md`, Wave 5:** append **task 5.8 — `questionnaire/`** (small, read-only, depends only on 5.3's permission gate) and **task 5.9 — `khotab/uploader(s).php`** (Upload-Team Tracking, depends only on Wave 1's khotab model graph + 5.3).
2. **`00-master-migration-blueprint.md` §18, Wave 5 Contents:** append "; `questionnaire/`, `khotab/uploader(s).php` (Roadmap tasks 5.8/5.9)" — the same additive-note pattern already used for Wave 4's post-hoc additions.
3. **A scope question for the user, not a unilateral amendment:** whether `surveys/` (public PHP-Nuke polls, §2) should get a Roadmap task at all, and if so, in which wave (Engagement-domain, so arguably its own small wave or folded into wherever Engagement work eventually lands — no existing wave currently owns it). Recommend asking directly rather than picking a wave number for the user.
4. **No change needed** to Blueprint §4's Survey Engine consumer description (already correctly scoped as Admin-only) or §7's `Poll`/`PollOption`/"Public poll voting" language (already correctly distinct from `admincp/survey/`) — both were already accurate; the gap is a missing Roadmap task, not a Blueprint description to fix.

---

## Summary

Wave 5's existing 7-task scope (survey engine, permission editor, soundcloud/youtube/locations, chat's working half, authors/backup, account security) is **confirmed sound** — every dead-code and security finding the existing audit made for these areas was independently re-verified against current source, not taken on trust, and all still hold except where explicitly noted as changed (`admincp/index.php`'s login mechanics, §1). Three real gaps were found: two small, Blueprint-named Admin-Operations capabilities with no task (`questionnaire/`, upload-team tracking — proposed as new tasks 5.8/5.9), and one larger, genuinely separate-domain capability (`surveys/`, the public PHP-Nuke poll system) that was fully analyzed long ago but never scoped into any wave at all — flagged as a scope question, not assumed into this plan.

Recommended order: 5.1 → 5.2 → 5.3 → 5.4 → 5.8 (`questionnaire/`) → 5.5 → 5.9 (`khotab/uploader(s)`) → 5.6 → 5.7, with 5.7 folded into 5.6's own rebuild rather than run separately. Waiting on your review and approval before any implementation begins.
