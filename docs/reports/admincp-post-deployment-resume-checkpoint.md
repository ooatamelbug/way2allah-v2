# AdminCP — post-deployment resume checkpoint

**Status (owner, 2026-11-17): OLD AdminCP FUNCTIONAL PARITY — PAUSED BY OWNER — RESUME AFTER THE UPCOMING DEPLOYMENT.**

This is a **scheduling** decision. Nothing listed as missing, partial, unknown or unverified below is retired, abandoned or
complete. OLD and Gen 2 stay operational after the upcoming deployment.

The owner's order of work:
1. Finish the current feedback fixes.
2. Prepare and verify the accumulated Laravel changes.
3. The owner deploys.
4. Monitor production stability.
5. Resume OLD AdminCP parity work.

This document is documentation only:
- no application code, database, migration, seeder, OLD / Gen 2, commit or deployment change;
- no investigation was reopened.

It links to the detailed records instead of repeating them.

| Read first | What it holds |
|---|---|
| [`old-admincp-complete-functional-parity-audit.md`](old-admincp-complete-functional-parity-audit.md) | The authoritative checklist. Both OLD menus (57 items), all 213 OLD operations with Laravel mapping, status, difference and evidence, plus the dead-label appendix and gap priorities |
| [`admincp-administrator-management-results.md`](admincp-administrator-management-results.md) | Administrator management (OLD `mod_authors`): §1–§9 the build, §10 login rename, §11 permission migration, §12 audio / video scope |
| [`evidence/`](evidence/) | `old-module-assignments-{summary,detail}.csv`, `admin-permission-parity.csv`, `old-permission-migration-dry-run.json` (ids and keys only) |
| `../decision-log.md` #150, #154, #155 | F01–F10 closure, administrator management, this pause |

---

## 1. Administrator management — where it stands

| Capability | State | Evidence |
|---|---|---|
| Staff list (legacy-rank grouping; «مالك», «الحساب الرئيسي», «مشرف عام» badges) | **Implemented and verified locally** | ADM §4 |
| Create admin (forum-linked; nickname, name, email, URL, optional initial password, permissions for super-admins, super-admin flag for owners; reserved OLD identities refused; Gen 2 avatar). Also fixes the earlier Laravel insert that **always failed** on the real schema (`thumb` NOT NULL) | **Implemented and verified locally** | ADM §1, §4 |
| Edit profile (email, URL); name immutable as in OLD | **Implemented and verified locally** | ADM §4 |
| Login-name rename (owner-only; case-insensitive uniqueness; reserved names; `nuke_stories` re-attributed as OLD did; audited) | **Implemented, DISABLED** (`ADMIN_RENAME_ENABLED=false`) — must stay off while OLD runs | ADM §10 |
| Super-admin promote / demote (owners only; Laravel `super-admin` role only, legacy `radminsuper` untouched) | **Implemented and verified locally** | ADM §4, final pass |
| Owner rule: OLD's literal `mod_authors` rule, no configured ids (`radminsuper = 1` + name `Good` or login `Admin` / `مؤمن يحب الله ورسوله`, case-insensitive). Main account = name `Good` (never deactivated or demoted). An owner may manage another owner (OLD). Fails closed if no row matches | **Implemented and verified locally** (local data: owners 3 and 64, no `Good`) | ADM final-pass note; `config/admin_owners.php` |
| Deactivation / reactivation (never deletes; credentials neutralised; sessions blocked; new password on reactivation; owner / main-account protection) | **Implemented, DISABLED** (`ADMIN_DEACTIVATION_ENABLED=false`); needs the `admin_account_states` table | ADM §4; DL #150 (F02) |
| Permission editor (assign / revoke; protected targets need an owner) | **Implemented and verified locally** | ADM §4 |
| One-time OLD permission migration `admin:migrate-old-permissions` (additive, idempotent, dry-run, audited; OLD module lists + `nuke_*_mod` per-action tokens; never touches super-admins) | **Implemented and verified locally; NOT RUN anywhere real** — final-cutover step | ADM §11, §12 |
| Khotab audio / video moderator scope (`khotab.media_audio` / `khotab.media_video`, `KhotabMediaScopeConstraint` + `EnsureKhotabMediaScope`) | **Implemented and verified locally; ACTIVE on deployment (no flag)** — see §3 risk R1 | ADM §12 |
| Audit (`create_admin`, `update_admin_profile`, `promote_super_admin`, `demote_super_admin`, `rename_admin`, `migrate_old_permissions`, plus `update_permissions`, `set_password`, `deactivate_admin`, `reactivate_admin`) | **Implemented and verified locally** | ADM §1 |

**Verification on record (local only):**
- **Focused regression:** 1,462 passed, 13 skipped, 0 failed. PHPStan 0.
- **Real-route runs on disposable full copies of `olddb`**, each torn down with shared data verified unchanged:

| Run | Checks |
|---|---|
| Admin management | 51 / 51, then 68 / 68 |
| Login rename | 24 / 24 |
| Migration page access (7 admins × 15 pages) | 19 / 19 |
| Audio / video writes and forms | 100 / 100 |

- **Migration dry run:** 36 admins, **623 grants**, **63 unresolved items**. Re-run idempotent; nothing revoked; super-admins untouched.

**Unresolved permission mappings** (never granted, listed per admin in the dry-run JSON; ADM §11–§12):
- **No Laravel operation:**
  - `add_tree` (category tree);
  - Anasheed `rearrange`;
  - Telawah `addbanner` / `addcomment` / `delcomment`.
- **Ambiguous target:** `downloader` (backup).
- **No OLD source data:** Telawah mirror edit / delete / repair.
- **Ambiguous modules:** w2a_cd, BackUp, questionnaire, live, docx, chat_server, Classificate_anasheed, banners_ads, report_ads.
- **No media section:** 9 Khotab moderators had neither section in OLD, so they get no section-scoped key.

Mapped: `uploader` → `khotab.repair`; `AdvFile` needs no key.

**Pending production deployment:** everything above (nothing AdminCP-related from this workstream is deployed).
**Pending final cutover (NOT part of the upcoming deployment):** the permission migration, enabling rename, the decision
on `admin:sync-permissions` (§3), enabling deactivation if the owner wants it, and confirming the production owner rule.

## 2. Complete OLD AdminCP audit — preserved, not changed

Authoritative file: [`old-admincp-complete-functional-parity-audit.md`](old-admincp-complete-functional-parity-audit.md)
(2026-11-17). Its counts stand.

**Scope**

| | |
|---|---|
| Screenshot items | **57**. Screenshot 1 «التحكم في البرامج المستقلة»: 41. Screenshot 2 «قائمة التحكم»: 16. Reconstructed from OLD's rendering code because the images were not available; the owner should cross-check them |
| OLD modules | **54** |
| OLD operations | **213** (620 labels). 84 dead labels / links excluded (appendix B.1) |

**Status of the 213 operations**

| Verified | Implemented, not verified | Partial | Missing | Retired by owner | Unknown |
|---|---|---|---|---|---|
| **95** | **9** | **15** | **59** | **7** | **28** |

**HIGH gaps.** In each case Laravel already displays the content but cannot manage it:
1. **Homepage campaigns** «حملات الرئيسية» (`flashxml`, `nuke_7amalat` → Laravel homepage slider).
2. **Gallery administration** «الالبوم الدعوي» (`new_album`: upload, thumbnails, ZIP build / delete).
3. **Satellite channel administration** «الأقمار الصناعية» (only the stream code is editable today).
4. **Category-tree management** «التصنيفات» (`w2a_cat_add/edit/del`, `KhotabEditCat`).

**MEDIUM** (audit §E):
- **Functional verification owed:** ads (5 ops), the banner-ad set, ad reports, Fatwa general questions.
- **Fatwa:** AdminCP listings, question delete, `del_all_topics_for_catg`.
- **Anasheed:**
  - the site-wide pending-comments queue;
  - banner add / edit / delete;
  - `AnasheedListDown`;
  - the AdminCP landing.
- **Lost-link worklist** for Anasheed / Telawah.
- **Telawah:** banner upload.
- **Khotab:** `JoinMe` bulk re-parent.
- **Forum banners** (`Montada`), only if the home banner strip is enabled.

**Owner decisions still open** (audit §E):
- **OLD-only programs:** Quiz, Tutorials.
- **Admin of public features already retired:** FAQ / contact, help, home_files, questionnaire, default Picasa album, fb_share_list, the W2ACD share queue.
- **The Feedback inbox** for the preserved `nuke_contact_us` messages.
- **Smaller inactive modules:** `cd`, ramadan_competition, high_rank.
- **The BackUp module** (Business Confirmation #7).
- **Legacy-log clearing.**
- **`live` / `nuke_live`.**
- **The PHP-Nuke platform screens:** AutoTheme, DB backup, Blocks, user groups, referrers, IP ban, messages, PHP-Nuke moderation, module manager, newsletter, nukeSEO, Optimize DB, settings, members.
- **Technical exclusions never owner-retired:** counter repairs, Telawah mirror insert, exports.

**The 7 owner-approved retirements:**
- the Khotab / Anasheed / Telawah share queues (PLAN §28.1);
- `chat_server` restart and `hedaya_table` (DL #14, #39, #122);
- Feedback replies and volunteer registration (DL #108).

## 3. Deployment boundary (upcoming deployment)

All of this workstream is **local and uncommitted** (`git status`):
- **New:** `app/Domain/Admin/Actions/Staff/*`, `MigrateOldAdminPermissions*`, `Support/{AdminOwners,AdminManagementPolicy,AdminDeactivation}`, `Support/Constraints/*`, `Http/Middleware/EnsureKhotabMediaScope`, `Models/AdminAccountState`, `config/admin_{owners,rename,deactivation}.php`, the `admin_account_states` migration, and the views and tests.
- **Changed:** `AdminStaffController`, `PermissionController`, `AdminServiceProvider`, `AdminPermissionSeeder`, `PermissionLabels`, `AdminGuard`, `BackupApiAuthenticator`, the three `InsertKhotabAuthor*Action`s, `routes/admin.php`, and the staff / permission views.

### 3.1 Immediate deployment requirements (if this code is included)

| Item | Requirement | Why |
|---|---|---|
| `php artisan migrate --force` | Runs the normal migrations. It creates the **new, empty `admin_account_states` table on `main`** (2026_11_17_100000), plus any earlier pending migrations (e.g. `admin_audit_log` if absent) | Additive. OLD and Gen 2 do not read the new table, and Laravel does not read it while deactivation is off |
| `php artisan db:seed --force --class=AdminPermissionSeeder` | **Required.** Idempotent (`firstOrCreate`); creates only permission *definitions*, including the new `khotab.media_audio` / `khotab.media_video` | **R1:** without the two rows, Spatie throws `PermissionDoesNotExist` → **HTTP 500** for any non-super-admin holding a section-scoped Khotab key, on Khotab forms and writes |
| Config | Leave `ADMIN_DEACTIVATION_ENABLED` unset / `false`. Leave `ADMIN_RENAME_ENABLED` unset / `false`. **No `ADMIN_OWNER_IDS`** (owners come from OLD's rule). Rebuild the config cache after deploying (`config:cache`) so the three new config files load | Rename must stay off while OLD runs (it breaks the renamed admin in OLD) |
| **Do NOT run** `admin:sync-permissions` | — | Superseded as a deployment step since 2026-10-14 (handoff §10 note). It **replaces** every admin's Laravel grants with the legacy state, erasing editor-made grants |
| **Do NOT run** `admin:migrate-old-permissions` | — | A final-cutover step. While OLD runs, moderators keep working in OLD |

### 3.2 Behaviour that changes on deployment (no flag)

- **R1 — Audio / video scope is active.** A regular admin with a section-scoped Khotab key (item / series / group add-edit-move-delete, mirror edit / delete) is refused unless they also hold `khotab.media_audio` and/or `khotab.media_video`.
  - Super-admins are unaffected.
  - Locally, no regular admin holds such keys; only super-admins did, via the Gen 2 blob.
  - **After seeding, check production:** any regular admin granted those keys through the permission editor must also be given the matching section key(s) in the editor. This is not a cutover command.
- **R2 — Protected targets.** A super-admin who is not an owner can no longer edit another super-admin's or an owner's permissions, password, profile or status. Promote / demote is owner-only. If no production account matches OLD's owner rule, those actions are blocked for everyone; this fails closed by design.
- **R3 — Create admin** now writes `thumb` / `url`, fixing the real-schema insert failure, and audits `create_admin`. That requires the `admin_audit_log` table on `main`.

### 3.3 Final cutover only (NOT now)

1. Confirm at least one active production account matches OLD's owner rule.
2. Decide `admin:sync-permissions`:
   - run it only if production Spatie grants are empty;
   - always `--dry-run` first;
   - never after editor grants exist.
3. Seed, then run `admin:migrate-old-permissions --dry-run`, review it, then run it with `--actor=<super-admin aid>`.
4. Set `ADMIN_RENAME_ENABLED=true` only after OLD is retired.
5. If wanted, set `ADMIN_DEACTIVATION_ENABLED=true` (table already migrated). Note that deactivation does not reach OLD / Gen 2 sessions.
6. Hand the 63 unresolved migration items to the owner.
7. Work through the OLD / Gen 2 retirement checklist (PLAN §55).

**Correction:** ADM §11's cutover list said "`admin:sync-permissions` once". Read it with step 2 above and the handoff §10
supersession.

## 4. Post-deployment resume plan

**Where work stopped (2026-11-17):**
- the complete OLD AdminCP audit was delivered;
- administrator management, the permission migration and the audio / video scope are complete locally;
- no further AdminCP implementation has started.

**Read first:**
1. This file.
2. The audit (§E priorities, §B rows).
3. ADM §10–§12.
4. DL #154–#155.

Do **not** re-run the OLD inventory or the evidence extraction; the audit is authoritative. Re-check only what production
evidence changes.

**Recommended order:**
1. **Production check (read-only):**
   - confirm the screenshot inventory against the owner's images and production `nuke_modules`;
   - confirm R1 (media keys);
   - confirm the owner-rule match.
2. **HIGH, one module at a time** (investigation → owner approval → implementation → real-route verification):
   1. homepage campaigns;
   2. category tree;
   3. gallery;
   4. satellite / channels.
3. **MEDIUM:**
   1. real-route verification of ads / banner set / ad reports / Fatwa general questions;
   2. Fatwa listings and question delete;
   3. the Anasheed pending-comments queue and banner ops;
   4. lost-link worklists;
   5. Telawah banner;
   6. a decision on `JoinMe`.
4. **Owner decisions** (§2 list): ask in one batch. Each item becomes either an explicit retirement or a scoped task.
5. **UNKNOWN rows:** a short trace each (audit §E LOW).
6. **Final cutover** (§3.3).

**Open owner decisions:**
- the audit §E owner-decision list;
- the 63 unresolved migration items;
- `admin:sync-permissions` at cutover;
- whether to enable deactivation;
- `radminsuper` mirroring for role changes (ADM D1, currently Laravel role only).
