# AdminCP administrator management: OLD `mod_authors` functional parity

**Date:** 2026-11-17.

> **Final parity pass (supersedes the owner model in §1, D3 in §7 and the count in §4).** Owners are no longer
> configured by id. `config/admin_owners.php` now holds OLD's literal rule from `admin/modules/authors.php`:
> - **Owner** (may manage administrators and grant / revoke super user; line 21): `radminsuper = 1` AND
>   (name `Good` OR login name `Admin` / `مؤمن يحب الله ورسوله`). The login-name test is case-insensitive, because OLD
>   compared the typed or forum login after a case-insensitive MySQL match (`admincp.php:55`, `vb5.php:121`).
> - **Protected main account:** name exactly `Good` (OLD lines 48, 57, 238, 436, 721). It is never deactivated or
>   demoted. As in OLD, an owner may demote or deactivate another owner.
> - **Reserved identities:** these login names and names (and `Good`) cannot be created through Laravel.
>
> On the local data, the rule yields owners **3 and 64**, derived rather than configured. No `Good` account exists.
> In production the rule is evaluated on production rows; if none matches, owner-only operations stay blocked.
>
> **Functional run:** 68 / 68 checks pass on a fresh disposable copy. The added checks cover:
> - existing admins keep access: synced grants are idempotent; Gen 2 `survey.*` admin 5 opens its module;
>   super-admins open every page;
> - the second owner, id 3, logs in by its Arabic login name;
> - owner-on-owner demote, promote, deactivate and reactivate (OLD parity);
> - main-account protection, using a synthetic `Good` on the disposable copy;
> - reserved identities.
>
> **Tests:** 240 focused tests pass, PHPStan 0. Shared `olddb` / `w2acp_vb`: no table modified, audit content hash
> unchanged. **Local only: nothing committed, pushed or deployed.** OLD and Gen 2 were read-only. No
production access. The shared local `olddb` / `w2acp_vb` were only read (§6).

## 1. Implemented

| OLD `mod_authors` capability | Laravel now | Notes |
|---|---|---|
| Admin list | `GET /admincp/staff` (unchanged gate `authors.liststuff`) | Legacy two-portlet grouping by `radminsuper` kept. New badges: «مالك» (owner), «مشرف عام» (Laravel super-admin role). Deactivate / reactivate links shown only where the policy allows |
| Add: name, nickname, email, URL, password, modules, super user | `GET/POST /admincp/staff/create` (`authors.addstuff`) | Forum member id (kept) + nickname («الكنية», login name, ≤ 25), name («الإسم», ≤ 50), email, URL (http/https), optional initial password (8–72, confirmed; else random unknown), permission grid (**super-admin actors only**), «مشرف عام» (**owner actors only**). All optional, defaulting to the forum account, so a vbuid-only request behaves as before |
| Edit profile | `PUT /admincp/staff/{id}/profile` (super-admin) | Email and URL. Name read-only, as in OLD. Nickname **not** editable (decision D2) |
| Module permissions | Existing editor `PUT /admincp/permissions/{id}` | Unchanged UI; now refuses protected targets for non-owners |
| Super user promote / demote | `POST` / `DELETE /admincp/staff/{id}/super-admin` | **Owners only.** Writes Laravel's Spatie `super-admin` role only; legacy `radminsuper` unchanged (decision D1) |
| Delete | **Deactivation** (F02, unchanged flow) | Record and history kept. Owner accounts can never be deactivated; another super-admin only by an owner |
| Owner-only access to the screen (`Good` / `Admin` / `مؤمن يحب الله ورسوله`) | `config/admin_owners.php` → `ADMIN_OWNER_IDS` (`nuke_authors.id` list) | **Empty by default = fail closed**: no role changes, and no one can manage another super-admin. Local candidates: **3** (`مؤمن يحب الله ورسوله`, forum uid 16715) and **64** (`Admin`, forum uid 1), matching Gen 2's `$SuperAdmins = [1, 16715]`. Production ids to be confirmed by the owner |

**Rules (one place: `AdminManagementPolicy`):**
- Managing an account (profile, password, permissions, deactivate, reactivate) needs a Laravel super-admin.
- An **owner** account: only an owner may manage it; it is **never** deactivated or demoted.
- **Another super-admin** (Spatie role *or* legacy `radminsuper`): only an owner may manage it.
- Promote / demote: owner only, never on oneself, never on an owner, never a deactivated target; at least one
  active Laravel super-admin always remains; serialised by a lock.
- Create: permissions only from a super-admin; «مشرف عام» only from an owner. Refused before anything is written.

**Identity safety (create):** the row is always linked to the forum member by `uid` (one admin per forum
member, as before). Nickname and name must be unique among admins (DB collation). A nickname different from the
member's forum username must not be **another** forum member's username. Markup / control characters refused.
Avatar set as Gen 2 did (forum avatar, else the site logo).

**Audit (`admin_audit_log`, names only, never passwords or values):** `create_admin`, `update_admin_profile`
(changed field names), `promote_super_admin`, `demote_super_admin`, plus the existing `update_permissions`,
`set_password`, `deactivate_admin`, `reactivate_admin`. The create audit is written in the creation transaction;
grants are written only after commit (grant-safety rule unchanged).

**Pre-existing defect fixed in passing:** on the real schema `nuke_authors.thumb` is `NOT NULL` without a default.
The previous Laravel add-admin insert omitted it and, under Laravel's strict mode, **always failed** (reproduced on a
disposable copy: `ERROR 1364 Field 'thumb' doesn't have a default value`). The in-memory test fixture had hidden it.

## 2. Files

**New:**
- `config/admin_owners.php`
- `app/Domain/Admin/Support/AdminOwners.php`, `app/Domain/Admin/Support/AdminManagementPolicy.php`
- `app/Domain/Admin/Actions/Staff/AdminManagementRefused.php`, `ChangeSuperAdminRoleAction.php`,
  `UpdateAdminProfileAction.php`
- `tests/Feature/Admin/AdminManagementParityTest.php` (14 tests)
- `docs/reports/evidence/old-module-assignments-summary.csv`, `old-module-assignments-detail.csv`

**Changed:**
- `app/Domain/Admin/Http/Controllers/AdminStaffController.php` (create / store extended; `updateProfile`,
  `promote`, `demote`; list badges and policy-filtered links)
- `app/Domain/Admin/Http/Controllers/PermissionController.php` (policy guard on update / password; editor flags)
- `app/Domain/Admin/Actions/Staff/DeactivateAdminAction.php`, `ReactivateAdminAction.php` (owner / protected rules),
  `AdminDeactivationRefused.php` (now extends `AdminManagementRefused`), `AdminStaffSafety.php`
  (`spatieSuperAdminIds()` public)
- `routes/admin.php` (3 routes in the existing super-admin staff group)
- `resources/views/admin/staff/create.blade.php`, `staff/index.blade.php`, `permissions/edit.blade.php`
- Tests: `MainSchema::nukeAuthors()` gains the real `url` and `thumb` columns; `AdminStaffControllerTest`,
  `AdminStaffPermissionControlsTest`, `AdminGrantSafetyTest` gain the audit-table fixture; three
  `AdminDeactivationF02Test` cases now configure an owner actor (deactivating another super-admin is owner-only by
  design).

## 3. Test evidence

- **Focused suites:** 162 tests (staff, permission editor, grant safety, F02, page parity, guard, roles, sync,
  audit) + 193 tests (auth, login throttle, sidebar, visual parity, backup API, other `nuke_authors` users) — all
  pass. After the view fix: new + permission suites 30 / 30.
- **PHPStan** (changed files): 0 errors. **Pint:** clean.

## 4. Functional verification (real routes, disposable copy)

**Environment:** `am_disp` = full copy of local `olddb` (240 tables, checksum-identical at copy time) and `am_vb` =
copy of the forum `user` table plus 3 synthetic members; a restricted MySQL user; `php artisan serve` with `main` /
`vbulletin` pointed at them, scratch SQLite for Spatie, cookie sessions, `ADMIN_OWNER_IDS=3,64`, deactivation on.
Grants came from the real `admin:sync-permissions` on that copy. Logins through the real login form; every request
with the page's CSRF token. Actors: owner **64**, non-owner super-admin **2**, regular admin **5**.

**Result: 51 / 51 checks pass** (after one product fix and two harness fixes, below):

| Area | Verified |
|---|---|
| List | both legacy portlets; owner badges on 3 and 64; no deactivate link for owners; a non-owner sees no deactivate link for other super-admins (7, 127) but does for regular admins; regular admin without the key → 403 |
| Create | owner form has the super-admin flag; a non-owner super-admin's does not; refused: super-admin flag from a non-owner, another member's username as nickname, an existing admin's nickname, `javascript:` URL — nothing written; created with nickname, name, email, URL, forum uid, `radminsuper` 0, avatar, bcrypt password, 2 permissions; audit without the password; **the new admin logs in with the initial password**; owner-created super-admin gets the Laravel role, `radminsuper` stays 0; vbuid-only request still works on the real schema |
| Edit | email + URL saved, name unchanged, audited; non-owner refused on the owner and on another super-admin; regular admin → 403 |
| Permissions | assign `authors.liststuff` + revoke `fatwa.manage` → the admin's access changes immediately (403 → 200); non-owner refused on the owner's password (hash unchanged) and on another super-admin's permissions |
| Promote / demote | non-owner super-admin refused; regular admin promoting itself → 403; owner promotes (role added, `radminsuper` unchanged, audited); the promoted admin gains the editor but cannot promote others; owner cannot demote itself or the other owner; owner demotes (audited); demoted admin loses the editor (403) |
| Deactivate | owner account refused even by an owner; non-owner refused on another super-admin; self refused; regular admin → 403; super-admin deactivates a regular admin (record kept, audited); the existing session is blocked (401) and a fresh login fails; profile / promote of the deactivated target refused; reactivation with a new password restores access |

**Fixes found by the functional run:**
- **Product:** the editor's promote / demote buttons never rendered (a `??` on a null value). Server-side rules were
  correct; the view now uses a boolean, and a feature test asserts the buttons.
- **Harness only:** Arabic login names exported through a latin1 client; body-less POST / DELETE sent without the
  CSRF token.

## 5. OLD module assignments → Laravel permissions (investigation; nothing imported)

**Source:** `nuke_modules.admins` (comma list of `nuke_authors.name`) on the local 2025 copy, read-only. OLD module
admin = whole-module access; super-admins were exempt. 38 modules have lists; 241 assignments. Evidence (ids and
modules only, no names): `docs/reports/evidence/old-module-assignments-summary.csv` (per module: listed, existing,
deleted admins, proposed keys, admins who would gain keys) and `…-detail.csv` (per assignment: admin id, proposed
keys, keys already granted by the Gen 2 blob, keys that would be added).

| Status | OLD modules | Proposed Laravel keys |
|---|---|---|
| **MAP** (Laravel code names this OLD module as the one it replaced) | Khotab, Telawah, Anasheed | all keys of `khotab.*` / `telawah.*` / `anasheed.*` (owner may narrow) |
| | Fatwa, Mobile, ads, w2acd, Surveys (= PHP-Nuke polls), advfiles, Cat | `fatwa.manage`, `mobile.managedownloads`, `ads.manageads`, `w2acd.managecds`, `polls.managepolls`, `khotab.addadv`, `khotab.addcat` + `anasheed.addcat` |
| **OWNER DECISION** (plausible, not certain) | w2a_cd (its admin writes `nuke_anasheed_*`), banners_ads / report_ads (OLD inactive, Laravel implemented), BackUp, questionnaire, live, update_khotab_count, docx (inactive), chat_server (inactive), Classificate_anasheed (inactive) | see CSV; chat_server and Classificate_anasheed have no proposal |
| **NO MAPPING** (no Laravel admin: retired / not implemented) | Montada, Tutorials, Dump_files, fb_share_list, flashxml, hedaya_table, home_files, new_album, default_picasa_album, Quiz, ramadan_competition, contact, help, FAQ, Feedback, high_rank, cd, Satellite | none (no permission invented) |

Notes: Cat lists 75 names, 43 of them no longer existing admins (Tutorials 8 of 10); deleted names are kept as
evidence only. Super-admins in a list are covered by the role. No import tool was built: a one-time import is an
owner decision (D4), and must not become a recurring sync.

## 6. Shared data

- `olddb` and `w2acp_vb`: only read. After teardown **no table in either schema has an `UPDATE_TIME`** since the
  server started (uptime 5.5 h, covering this work); `admin_audit_log` still 57 rows, ids 1–57, newest
  2026-11-03, content MD5 `f13b4e8c…` stable across reads.
- `CHECKSUM TABLE` is **not reliable** for `admin_audit_log` (JSON column): two identical fresh copies gave
  different values and the same table changed value between reads with no write. Earlier reports' "checksum
  unchanged" for this one table should be read as the row / content evidence, not the checksum.
- Disposable databases, the restricted user, scratch SQLite and test passwords were removed.

## 7. Decisions required (not guessed)

| # | Decision | Why it matters | Current behaviour |
|---|---|---|---|
| **D1** | Should promote / demote also write legacy `nuke_authors.radminsuper`? | Gen 2 and OLD grant full legacy power from that flag; writing it changes privileges outside Laravel | Laravel role only; the UI says so |
| **D2** | Allow renaming the nickname (`aid`)? | It is the login name for Laravel and Gen 2, and the attribution key of legacy logs and lists | Not editable after creation (settable at creation) |
| **D3** | Production owner ids for `ADMIN_OWNER_IDS` | Owner protection and role changes depend on it; empty = fail closed | Locally verified with 3,64 |
| **D4** | Import OLD module assignments (§5)? Which OWNER-DECISION rows? Full module keys or narrower? | Adds grants to real accounts | Evidence only |
| **D5** | Retire or guard `admin:sync-permissions` after cutover | It derives the role from `radminsuper` and **replaces** permissions from the Gen 2 blob, so a rerun would undo Laravel-only promotions and editor changes (already true for editor changes before this work) | Unchanged; run only as a one-time cutover step |
| **D6** | Staff list grouping | Grouped by legacy `radminsuper` (tests and legacy parity); a Laravel-only super-admin appears under «المشرفين» with a «مشرف عام» badge | Kept |

## 8. Remaining gaps

- OLD's per-admin language (`admlanguage`) and `feed_back` are not edited (Laravel is Arabic-only; `feed_back` was
  OLD's super-admin marker). Not ported by design.
- Deactivation does not reach OLD / Gen 2 sessions (F02 documented limitation, unchanged).
- Laravel admin management does not write OLD's `nuke_modules.admins` (no permanent sync, by instruction).

## 9. Verdict

**Functional parity with OLD `mod_authors`: ACHIEVED LOCALLY for Laravel**, with deliberate differences: delete →
deactivation; the name stays immutable (as in OLD) and the nickname is not renamable (D2); super user → Laravel role
only (D1). All workflows were verified through the real AdminCP routes on a disposable copy. **Not production-ready
until D3 is set** (owner-only actions stay closed without it), and the D5 cutover rule must be followed. OLD module
assignments are mapped as evidence, not imported (D4).

## 10. Closure pass: login rename and existing-admin permission parity (2026-11-17)

**Login rename: implemented and verified, behind `ADMIN_RENAME_ENABLED` (default off).**
- **Route and UI:** `PUT /admincp/staff/{id}/login-name`, with a form in the permission editor.
- **Code:** `RenameAdminAction`; `AdminManagementPolicy::renameRefusal()`; `config/admin_rename.php`.
- **Who:** owners only, as in OLD.
- **Refused:**
  - renaming an account whose ownership depends on its login name;
  - a reserved owner login name or `Good`;
  - a duplicate, compared case- and trailing-space-insensitively (OLD's collation);
  - another forum member's username;
  - a deactivated target;
  - an `informant` that would overflow `varchar(20)`.
- **Writes, exactly as OLD did:**
  - `nuke_authors.aid`;
  - `nuke_stories.aid`, plus `informant` where it equalled the lower-cased old login name.
- **Preserved:** the id, forum uid, name, email, passwords, `radminsuper`, Spatie grants (keyed by id), and every
  audit row.
- **Audit:** `rename_admin` (old → new, number of stories re-attributed).
- **Tests:** 6 in `AdminLoginRenameTest`. Functional run: 24 / 24 on a disposable full copy (stories attributed to
  admin 5 there as a synthetic fixture). Existing workflows: 68 / 68.

**Why the switch is off: it breaks OLD while OLD runs.** Verified from OLD's source:
- OLD's module access compares `nuke_modules.admins` (names) with the **login name**.
- The forum bridge logs in with **aid = forum username**.
- OLD's fine-grained grants (`nuke_islamic_mod` 36 rows, `nuke_anasheed_mod` 14, `nuke_telawah_mod` 7,
  `nuke_cat_mod` 11, `nuke_islamic_cat_mod` 11, `nuke_w2acd_mod` 1) are keyed by **login name**.
- OLD's own rename only re-attributed `nuke_stories`.
- Legacy `nuke_islamic_logs` (222,815 rows matching current admins) keep the name they were written under, as in OLD.

Enable it only after OLD is retired. Laravel and Gen 2 log in by `aid` + password and keep working.

**Existing-admin permission parity** (`docs/reports/evidence/admin-permission-parity.csv`; mapped OLD modules only;
compared against the local Laravel grants and against the cutover sync from the Gen 2 blob; read-only):
- **41 regular admins compared.** 36 have mapped OLD modules.
- **Missing:** every expected OLD-module grant is missing for all 36. Typically Khotab (28 keys) + Cat (2), and for
  some also Anasheed, Telawah, Fatwa, advfiles, Surveys, ads, Mobile, w2acd. The one exception is 9564, where the
  cutover sync supplies 2 of 56 keys. Without an import, OLD's Khotab / Anasheed / Telawah moderators cannot
  administer those sections in Laravel.
- **Unexpected grants in mapped scope:** none.
- **Gen 2-only grants outside OLD's mapped scope** (survey, backup, …): 7 admins.
- **Ambiguous:** 22 admins have rows in OWNER-DECISION modules (w2a_cd, BackUp, questionnaire, live, docx, …).
- **Caveat:** the "whole module" mapping overstates OLD. OLD narrowed each Khotab / Anasheed / Telawah moderator with
  per-action flags in `nuke_*_mod` (keyed by login name). An import should use those flags; that needs an owner
  decision.
- Nothing was imported or modified.

## 11. One-time OLD permission migration (2026-11-17)

**Command:** `php artisan admin:migrate-old-permissions [--dry-run] --actor=<super-admin aid> [--report=path]`.
**Code:** `MigrateOldAdminPermissionsAction`. **Tests:** 8 in `MigrateOldAdminPermissionsTest`.
- **Additive only:** never revokes, never touches super-admins (`radminsuper` or the role); skips deactivated admins.
- **Idempotent.**
- **Audited:** `migrate_old_permissions`, one row per admin.
- **Report:** a JSON report on every run. The dry-run evidence (ids and keys only) is
  `docs/reports/evidence/old-permission-migration-dry-run.json`.

**Sources:** `nuke_modules.admins` (the module gate, exact name match, as OLD) and the active `nuke_islamic_mod` /
`nuke_anasheed_mod` / `nuke_telawah_mod` rows (OLD's stored per-action tokens, matched by login name).
`GreenZone()`, OLD's runtime token check, returns immediately, so the rows are the only record of each moderator's
intended actions. A token is used only when the admin is also in that module's list.

**Mappings (OLD token → Laravel key; each pair is OLD function → Laravel route):**
- **Khotab:** addauthor / editauthor / delauthor → `khotab.*author`; add / edit / delgroup → `khotab.*group`;
  add / edit / delseries → `khotab.*series`; addkhotab → `additem`; editkhotab → `edititem`; movekhotab → `moveitem`;
  delkhotab → `delitem`; add / edit / delmirror → same; addbanner → `uploadbanner`; requestbanner → same;
  addcomment → `approvecomment`; delcomment → same; rearrange → `sortitems`; addpdf → `uploadpdf`;
  adddocx / deletedocx → same.
- **Khotab media scope:** OLD `AvAdmin()` limits item, series, group and mirror-edit operations to the audio and / or
  video section. Those 12 keys are granted only when **both** `audio` and `vedio` are set; otherwise they are
  withheld and reported. Laravel has no media scope, so granting would widen access.
- **Anasheed:** add / edit / delgroup, add / edit / delanasheed, addbanner, addicon, add / delcomment,
  add / edit / delmirror → same-named `anasheed.*`.
- **Telawah:** add / edit / delgroup, add / edit / deltelawah → same-named `telawah.*`.
- **Single-gate modules** (OLD checked only the list; Laravel's route docs say so): Fatwa → `fatwa.manage`;
  Mobile → `mobile.managedownloads`; ads → `ads.manageads`; w2acd → `w2acd.managecds`; Surveys → `polls.managepolls`;
  advfiles → `khotab.addadv`; Cat → `khotab.addcat` + `anasheed.addcat`.

**Unresolved (never granted; listed per admin in the report):**
- **Khotab media scope:** 10 video-only, 1 audio-only and 8 no-section moderators. Their scoped keys are withheld.
- **Tokens without a confirmed Laravel equivalent:** Khotab `AdvFile` (Laravel lets every admin request),
  `uploader`, `downloader`, `add_tree` (that column does not exist in OLD's Cat table); Anasheed `rearrange` (dead
  in production); Telawah `addbanner`, `addcomment`, `delcomment`.
- **No per-action source:** `telawah.editmirror` / `delmirror` / `repair` — `nuke_telawah_mod` has no mirror tokens.
- **Ambiguous modules:** w2a_cd, banners_ads, report_ads, BackUp, questionnaire, live, update_khotab_count, docx,
  chat_server, Classificate_anasheed.
- **Retired modules** (no Laravel admin): nothing to map.

**Dry run on a disposable full copy, after `admin:sync-permissions`:**
- 42 regular admins considered; **36 receive grants**.
- **462 grants proposed.**
- 2 super-admins skipped; 81 unresolved items.

**Verified on that copy:**
- The dry run wrote nothing.
- The apply added exactly the 462 proposed grants and removed none.
- Roles were unchanged, and no grant went to a super-admin.
- A second run proposed 0 and the grant tables were byte-identical.
- 36 audit rows were written.
- Real pages: 15 pages × 7 representative admins (super-admin 2; regulars 5, 15, 9564, 9599, 9636, 9643) return
  200 exactly where the key is held and 403 elsewhere.
- Video-only 9643 has no `additem` / `edititem`; Fatwa-only 9636 has exactly `fatwa.manage`; 9564 keeps its 20 Gen 2
  grants.
- Shared `olddb` / `w2acp_vb` and the local Laravel database were unchanged.

> **Correction (2026-11-17, #155):** `admin:sync-permissions` is not an automatic cutover step. It replaces grants and
> has not been a deployment step since 2026-10-14 (handoff §10). Run it only if production Spatie grants are empty,
> `--dry-run` first. See `admincp-post-deployment-resume-checkpoint.md` §3.3. Before any of this:
> `AdminPermissionSeeder` must run with the deployment (§3.1 there).

**Cutover order:**
1. `db:seed --class=AdminPermissionSeeder`.
2. `admin:sync-permissions`, once.
3. `admin:migrate-old-permissions --dry-run`; review the report.
4. `admin:migrate-old-permissions --actor=<super-admin aid>`.
5. Never re-run `admin:sync-permissions`.

## 12. Khotab audio / video moderator scope and unresolved actions (2026-11-17)

**Owner instruction "strict functional parity with OLD" supersedes decision D1b' (2026-10-12).**

**Media scope implemented:**
- **Permissions:** two new seeded permissions, `khotab.media_audio` («قسم الصوتيات») and `khotab.media_video`
  («قسم المرئيات»). Both are editable in the permission editor.
- **Enforcement:** `KhotabMediaScopeConstraint`, registered on the existing `AdminRecordAuthorizer`. The keys OLD's
  `AvAdmin()` scoped work on a record only with the matching section:
  - item add / edit / move / delete;
  - series add / edit / delete;
  - group add / edit / delete;
  - mirror edit / delete.
- **Section source:** the record's `vedio`; for a mirror, its item's `vedio` (as OLD); for author-level adds, the
  `{media}` chosen.
- **Forms:** `EnsureKhotabMediaScope` applies the same check to the Khotab forms, as OLD did.
- **Unaffected:**
  - super-admins (bypass, as before);
  - unscoped keys (comments, banners, PDF, docx, add mirror, sort, categories);
  - no runtime read of the legacy tables.
- **Migration:** the flags become the two permissions. A moderator with neither section gets no section-scoped key
  (OLD refused them everything scoped).

**Unresolved OLD actions re-checked against Laravel:**
- **Now mapped:** `uploader` («صيانة الوصلات») → `khotab.repair` (same label in OLD, Gen 2 and Laravel).
- **No permission needed:** `AdvFile` («إضافة ملفات دعائية»). Laravel lets every admin request (owner-approved).
- **Missing capability (documented, not built):**
  - `add_tree`: OLD Cat's category-tree add / edit / delete (`w2a_cat_add/edit/del`); Laravel has category
    assignment only.
  - Anasheed `rearrange`: OLD `ReArrange`; no Laravel route (recorded as dead in production).
  - Telawah `addbanner`: OLD `UploadBanner`.
  - Telawah `addcomment` / `delcomment`: OLD `AddComment` / `WaitingList` / `DelComment`.
- **Unresolved, ambiguous target:** `downloader` («النسخ الإحتياطي»). OLD gated no operation with it; Laravel's
  backup splits into `backup.backupkhotab` / `backupkhotabmirror`.
- **Unresolved, no OLD source data:** `telawah.editmirror` / `delmirror` / `repair` (no tokens in `nuke_telawah_mod`).
- **Ambiguous modules:** unchanged and not granted.

**Verified:**
- **Tests:**
  - 5 new media-scope tests through real routes and writes;
  - migration tests updated;
  - the two tests that pinned D1b' rewritten to the new rule;
  - test harnesses give both sections when a test does not choose (the pre-scope meaning);
  - focused regression: 1,462 passed, 13 skipped, 0 failed;
  - PHPStan 0.
- **Disposable full copy (real MySQL):**
  - **Migration:** dry run 623 grants, 63 unresolved. The apply added exactly those 623, removed none, left roles
    unchanged and gave nothing to super-admins. The re-run proposed 0 and the grant tables were identical.
  - **Khotab moderators by scope:** 15 both, 10 video-only, 1 audio-only, 9 neither.
  - **Real AdminCP pages and writes, 100 checks:** super-admin 2, both 5, audio-only 140, video-only 9643, neither
    9599. Each ran 5 forms, a group edit, an item add, an author-level group add, a mirror edit and an item delete,
    on an audio and a video group. Every outcome matched OLD's scope: writes landed exactly for the permitted
    section, and the other section answered 403 with nothing written. The neither-section moderator was refused all
    scoped operations and kept its unscoped access (add mirror, sort, categories).
  - Shared databases unchanged.
