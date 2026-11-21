# AdminCP feedback F01–F10: closure pass

**Date:** 2026-11-17.

**Scope:** finish the remaining feedback points, using the original F01–F10 numbering.
- Local implementation and testing only.
- No production access, no shared-database write, no OLD change.
- Nothing committed, pushed or deployed.
- Existing uncommitted work preserved.

---

## 1. Result

> **2026-11-17: F04 download normalisation fix applied** (owner-approved).
> - **What changed:** the Khotab item / mirror, Telawah and lesson downloads now fetch the normalised archive.org URL
>   (shared `ArchiveOrgLink`).
> - **Verified:** `/khotab-mirror-2432-5.htm` went from 500 to 200 attachment with real bytes; 8 of 11 real samples
>   were fixed and none regressed.
> - **Status:** F04 **CODE COMPLETE LOCALLY — NOT DEPLOYED** (`f04-save-material-download-results.md` §7).
>
> **2026-11-17: functional verification of F03 / F05 / F07** on real local data (disposable copy, real pages, real
> Chrome): `admincp-f03-f05-f07-functional-verification.md`.
> - **F03:** verified, 22 / 22 series visible after the repair.
> - **F05:** design verified, but 12 of 21 sampled MP4s are MPEG-4 Part 2 (sound, no picture) and 6 are corrupt at
>   the source.
> - **F07:** 46 verified, except items 9075 / 9826 (404) and 8 unverified `vars` items.
> - **Defect found and awaiting approval:** Khotab / Telawah / lesson download routes proxy the RAW stored link;
>   retired archive.org storage-node links then fail (500). In a sample, 26 / 30 pinned links fail and 25 of those
>   work normalised. **This corrects F04's "Khotab already downloads" for pinned links.**

| Point | Original issue | Implemented | Tested | Status | Exact remaining action |
|---|---|---|---|---|---|
| **F01** | OLD advertising missing (`ads`, `banners_ads`, `report_ads`, public rendering) | Earlier: B6a admin, B6b banners and reports, B6c-1 rendering foundation (switch OFF, nothing placed). **This pass: nothing new** — every remaining step is a placement or activation decision (§2) | B6a / B6b / B6c-1 suites in the full run (§7) | **ON HOLD — AWAITING STAKEHOLDER CLARIFICATION** (2026-11-17; owner decision: no implementation, change, activation or placement assumptions until clarified) | Approve per-page hosts for positions 1, 2, 3 (+ markup), 4, 7, 10 (§2 table), and separately 5/6/8, 9, 11, popup, strip. Then: seeder, key assignment, media checks, `ads.rendering_enabled` |
| **F02** | Administrator management: deactivate instead of delete | **This pass: Laravel-side deactivation** (§3): `admin_account_states` (migration on `main`, not run), Deactivate / Reactivate actions, guard / backup-API / CLI / sync enforcement, staff-list UI, permission-edit audit. **Switch `admin_deactivation.enabled` default OFF** | 19 new + drift tests; permission, staff, auth, sync, backup-API suites (§7) | **OWNER APPROVAL REQUIRED** | Approve and run the migration in production, then enable the switch. **Cross-system deactivation is NOT complete**: OLD AdminCP, the OLD bridge cookie (30 days), OLD super-admin sessions and Gen 2 forum-session hooks do not read the state (legacy patches G1–G3 / O1–O4, B3 review §13) |
| **F03** | New Khotab series not visible | Earlier: F03-B admin visibility; F03-A repair command. **This pass: verified end to end** on a disposable MySQL copy (§4) | dry run 22 / 316, apply, idempotent re-run, rollback byte-identical (table checksum), refused without `--actor`, items untouched, audit 22 + 22 | **CODE COMPLETE — DATA ACTION PENDING** | Owner runs the repair in production per `admincp-b2-f03-counter-repair-results.md` §7 (backup, `--actor`, dry run first). The reported series id is still unidentified |
| **F04** | «حفظ المادة» does not save the file | **2026-11-17 (owner-approved proxy expansion):** Variety items and mirrors, Telawah, Fatwa and lessons now download through the existing capacity-protected proxy (Khotab pattern, `ProxiedDownloadResponder`). Khotab is unchanged. `vars.way2allah.com` can bypass PHP via its header rule. See `f04-save-material-download-results.md` §6 | 35 + 35 new; 581/581 route and download suites; 2,099/2,138 (39 gated skips) Content / Anasheed / Downloads | **CODE COMPLETE LOCALLY — NOT DEPLOYED** | Production prerequisites §6.5: deploy the stalled-source timeout fix first; confirm guard and slot limit; `download_rejections` table; smoke test. Not downloadable: YouTube / SoundCloud pages, host-less paths (F07 data), private addresses, 2 archive.org `/details/` links |
| **F05** | Medium-quality video does not play | Earlier: F05-1 archive.org normalisation; F05-2 MP4-mirror-as-video and the unsupported-format notice. **This pass: verified** | `MediaPlayerService`, format and Variety suites in the full run (§7) | **BLOCKED — SPECIFIC EVIDENCE NEEDED** | The reported `khotab-item-{id}` and mirror id. Code paths are complete. 93 % of medium mirrors are non-browser formats (RMVB / WMV / …): data, not code; they now show the notice and the download |
| **F06** | Author image | Earlier (B1) | existing suites (§7) | **CLOSED LOCALLY** | Production media-root verification only (B1 §2) |
| **F07** | Variety video playback fails | Earlier: F07-A safeguards; F07-B2 repair command. **This pass: verified end to end** on a disposable MySQL copy (§4) | dry run 46, apply, idempotent, rollback byte-identical (both tables); MySQL byte-semantics suite **13/13** on real MySQL (normally skipped) | **CODE COMPLETE — DATA ACTION PENDING** | Owner runs the 46-row repair in production (`admincp-b2-f07b2-implementation-results.md` §10). The 221 MEDIUM / LOW records need decisions. The original reported record is unidentified, so the original complaint cannot be confirmed fixed |
| **F08** | Hide / unhide a Variety clip | Earlier (B4) | existing suites (§7) | **CLOSED LOCALLY** | Deployment blocked until production routing and legacy exposure are verified (B4) |
| **F09** | «نقل الوصلة» / `backupme` | Move and backup-queue already ported. **This pass: OLD's post-backup link lock restored** for Khotab mirrors (§6) | 8 new tests + the existing mirror suite: 31 / 31 | **CLOSED LOCALLY** | Wording of the two labels: owner (screenshot of the option the reviewers meant). Not blocking |
| **F10** | Custom material thumbnail | Earlier (B5) | existing suites (§7) | **CLOSED LOCALLY** | Production prerequisites (media persistence, permissions, GD WebP) |

## 2. F01: exact remaining placement and activation work

> **2026-11-17:** the full owner-approval matrix (all 12 positions, the popup and the banner strip, with dimensions, uncertain mappings and the exact decisions) is in `docs/reports/admincp-f01-placement-approval-matrix.md`.

**Not implemented.** The B6c-1 owner review said "Placement: none invented". The positions with a clear OLD source still have **no slot in Laravel's pages**:
- Laravel's home follows Gen 2's card layout. OLD's three columns (positions 1–3) do not exist in it, and Gen 2's only ad card (position 3, `new_content.php:333`, title «إعلان») is not part of Laravel's home.
- The detail, author and channel pages have no ad region.

Every placement is therefore a new visible layout choice, and needs owner approval.

| Position | OLD location | Proposed Laravel host (for approval) | Open item |
|---|---|---|---|
| 1 (120×600) | OLD home left column | `resources/views/home.blade.php`: no equivalent column | choose a spot, or drop |
| 2 (120×240) | OLD home third block | `home.blade.php` | choose a spot |
| 3 (120×240) | OLD third block; **Gen 2 live card «إعلان»** | `home.blade.php`: a `x-home.section-card` «إعلان» like Gen 2 | markup (natural size recommended) |
| 4 (728×90) | `Detailes()` top | `khotab/item`, `anasheed/item`, `w2acd/show` (locations item: no Laravel view) | exact spot per page |
| 7 (728×90) | sheikh / group / series top | `khotab/author`, `khotab/group`, `khotab/series` | exact spot |
| 10 (728×90) | channel lists | `channels/show`, `channels/author` | exact spot |
| 5, 6, 8 | `Top10()` sidebars | per-page proposal | owner (B6c-1 §9.4 #11) |
| 9 | Variety group top | — | owner-deferred (W2) |
| 11 | `Play()` dialog | — | no Laravel page (owner) |
| 12 popup, strip | home | — | kept disabled (owner) |

**Activation prerequisites (unchanged):**
- the permission seeder and key assignment;
- production media-root and `APP_URL` verification;
- no script execution in `media/bann/`;
- InnoDB at REPEATABLE READ for `nuke_banners_ads`;
- no view-limited ads until a counting mechanism is approved;
- code ads withheld.

**Code:** each approved position is a one-line `<x-ads.slot :position="N" />`. With the switch OFF it produces no query and no output (B6c-1 tests).

## 3. F02: Laravel-side administrator deactivation

**Switch:** `config/admin_deactivation.php` → `enabled` (env `ADMIN_DEACTIVATION_ENABLED`), **default false**.

While off:
- no query reads `admin_account_states`;
- the four routes return 404;
- there are no controls (tested).

**Storage:**
- migration `2026_11_17_100000_create_admin_account_states_table` on **`main`** (B3 review §13.4): explicit utf8mb4, InnoDB, no foreign key, `NonEmptyTableGuard` on `down()`. Skipped under tests, with the fixture `MainSchema::adminAccountStates()` and a drift test.
- **It was not run anywhere**, so there was no shared-database write.

**Deactivate** (`DeactivateAdminAction`): one `main` transaction that:
- locks the actor's and target's admin rows and state rows (id order);
- applies the rules (below);
- writes the state row (who, when, reason);
- **neutralises credentials**: `password` = `!disabled:` + 48 random characters, `pwd` = a 40-character non-hex random value, `API` = NULL;
- writes the audit entry `deactivate_admin` (no marker, hash or key).

**Preserved:**
- **identity:** `uid`, `aid`, `name` and `email`;
- **privileges:** `radminsuper`, the legacy permission blob and the Spatie grants.

**Rules:**

| Rule | Behaviour |
|---|---|
| Who | Spatie super-admin, re-checked as still active under the lock |
| Self | refused |
| Target already deactivated | refused |
| Last active super-admin | refused. Counted by either definition (`radminsuper = 1` or the Spatie role), minus deactivated, minus the target |
| Mutual deactivation | serialised by the row locks; the second request is refused because its actor is now deactivated (tested) |

**Reactivate** (`ReactivateAdminAction`):
- super-admin only; the target must be deactivated;
- **a new password is required** (8–72, confirmed), written as bcrypt in `password`;
- `pwd` keeps its marker, so OLD's md5 paths stay closed;
- `API` stays NULL;
- audited as `reactivate_admin`.

**Laravel enforcement:**

| Point | Behaviour |
|---|---|
| `AdminGuard::user()` | an existing session is dropped on its **next request** (401) |
| `resolveValidAdmin()` | login refused, even if a legacy system later rewrites a valid credential (tested) |
| `BackupApiAuthenticator` | `API Error 2` |
| F03 / F07 CLI `--actor` | refused |
| `SyncLegacyAdminPermissionsAction` | skips the admin (grants kept); `admin:sync-permissions` shows "skipped: deactivated" |
| Permission editor | permission or password changes for a deactivated admin are refused, so there is no silent reactivation |

**Also, owner decision 16:** permission edits (`update_permissions`: names added and removed) and password sets (`set_password`, no secret) are now audited.

**UI:**
- the staff list keeps deactivated admins with «معطّل»;
- «تعطيل» (not for oneself) and «إعادة تفعيل» links appear only to super-admins while the switch is on;
- confirmation pages require a reason (plain text) or a new password;
- the permission editor shows a deactivated notice.

**Not covered (stated in the UI and the flash message):**
- OLD AdminCP and the OLD bridge cookie (30 days);
- OLD super-admin sessions (V9);
- Gen 2 forum-session admin hooks;
- OLD md5 paths after `vb5.php` rewrites `pwd`.

New Gen 2 / Laravel password logins and all three backup APIs are blocked by the neutralised credentials. Open Gen 2 AdminCP sessions end only if P0-A (session `cv`) is deployed.

## 4. F03 and F07: repair commands, end to end on disposable data

**Setup:**
- database `f0x_disp` on local `mysql57`, with a dedicated user granted only there;
- content tables copied from `olddb` (read only);
- a synthetic actor `disp-super`;
- Spatie in a throwaway SQLite file;
- `main` confirmed to resolve to `f0x_disp` before any write.

Everything was dropped afterwards. **Shared `olddb` checksums were unchanged** (series 4046396329, anasheed 2306280694, mirrors 961017140; `admin_audit_log` 57 rows).

| Command | Dry run | Apply | Re-run | Rollback | Audit |
|---|---|---|---|---|---|
| `khotab:repair-f03-series-counts` | 22 series, 316 own items, `ready`; refused without `--actor` | 22 repaired, backup file written | `already_repaired` | dry run writes nothing; `--apply` restores 22; **series table checksum identical to before**; `nuke_islamic_khotab` identical to source | 22 + 22 |
| `anasheed:repair-f07-links` | 46 pending, `ready` | 46 corrected, backup file written | `already_applied` | restores 46; **both table checksums identical to before** | 46 + 46 |
| `AnasheedF07LinkRepairMysqlTest` (real MySQL byte semantics, normally skipped) | — | — | — | — | **13 / 13 passed** |

## 5. F04: the decision needed

- **Guaranteed saving needs the file to pass through a server we control.** archive.org MP4 has no CORS, so the browser cannot force a cross-origin download.
- The two in-scope routes are closed: **Blob** (rejected 2026-11-14) and **expanding the existing PHP proxy** (frozen).
- **The smallest decision is one of the following** (`f04-download-architecture-comparison.md` §4):
  1. **Option C**, a small dedicated download gateway (recommended; new host or service, external);
  2. **Option B2**, `X-Accel-Redirect`, only if production has nginx or a proxy in front of Apache (a host fact to verify first);
  3. **lift the freeze** on the existing PHP proxy (not recommended: capacity).
- **Without a decision:** today's behaviour plus UI guidance, which does **not** guarantee a save.

## 6. F09: post-backup link lock restored

**OLD's lock** (`modules/Khotab/admin/index.php:578-581`, `admin_edit_mirror.htm:5`): once the volunteer backup archived the file (`down = 1`), only a super-admin may change a Khotab mirror's link.
- OLD enforced it with a `disabled` input only. The browser therefore omitted the field, and `ConfirmEditMirror` **blanked the link**.

**Laravel now:**
- `UpdateKhotabMirrorAction::linkEditable()` decides on the **locked current row**, so a backup that completes while the form is open still locks the link;
- the stored link is kept, while comment, type, `backupme` and the trial reset are still saved;
- the form shows the field `readonly` (not `disabled`) with an explanation;
- the audit records no link change.

**Not applicable:**
- **Telawah:** its mirrors have no `down` column, so OLD's check never fired;
- **Anasheed:** OLD never locked the link.

The item-level lock (`UpdateKhotabItemAction::linkEditable`) already existed.

**`backupme` and «نقل الوصلة»:** unchanged.
- The move keeps the URL and shifts the counts by ±1 (tested again with a backed-up mirror).
- The backup queue (`myop=get`) is covered by the existing backup-API suites.

**Wording:** an owner choice, not blocking.

## 7. Files changed (Laravel)

| File | Change |
|---|---|
| `app/Domain/Admin/Actions/Mirrors/UpdateKhotabMirrorAction.php` | F09 lock (`linkEditable`, server-side keep) |
| `app/Domain/Admin/Http/Controllers/KhotabMirrorController.php` | passes `linkEditable` to the edit view |
| `resources/views/admin/mirrors/khotab/edit.blade.php` | read-only link and notice |
| `config/admin_deactivation.php` *(new)* | F02 switch, default OFF |
| `database/migrations/2026_11_17_100000_create_admin_account_states_table.php` *(new, not run)* | F02 state table on `main` |
| `app/Domain/Admin/Models/AdminAccountState.php` *(new)* | model |
| `app/Domain/Admin/Support/AdminDeactivation.php` *(new)* | the single state read (no query while OFF) |
| `app/Domain/Admin/Actions/Staff/{DeactivateAdminAction,ReactivateAdminAction,AdminStaffSafety,AdminDeactivationRefused}.php` *(new)* | actions and rules |
| `app/Domain/Identity/Guards/AdminGuard.php` | session and login refusal |
| `app/Domain/Admin/Support/BackupApiAuthenticator.php` | refusal |
| `app/Console/Commands/KhotabRepairF03SeriesCounts.php`, `AnasheedRepairF07Links.php` | `--actor` refusal |
| `app/Domain/Admin/Actions/SyncLegacyAdminPermissionsAction.php`, `app/Console/Commands/SyncAdminPermissions.php` | skip and report ("skipped: deactivated") |
| `app/Domain/Admin/Http/Controllers/AdminStaffController.php`, `PermissionController.php`, `routes/admin.php` | endpoints, refusals, audit |
| `resources/views/admin/staff/{index,deactivate,reactivate}.blade.php`, `resources/views/admin/permissions/edit.blade.php` | UI |
| `tests/Support/Fixtures/MainSchema.php` | `adminAccountStates()` fixture |
| `tests/Feature/Admin/PermissionControllerTest.php`, `MirrorPermissionUiPhase0Test.php` | add the audit table to their in-memory setup (edits are now audited) |
| `tests/Feature/Admin/{AdminDeactivationF02Test,AdminAccountStateSchemaDriftTest,KhotabMirrorBackupLinkLockF09Test}.php` *(new)* | tests |

**Test results:** see §8.

## 8. Validation

### 8.1 Full suite: **INCOMPLETE (not passed)**

**The run:** `php artisan test --parallel --processes=4`, started 00:28.

**The stall:**
- It stalled at 00:30:35 with one file outstanding on worker 4, so no summary was produced.
- Only that run's four PIDs (197766, 197767, 197770, 197811) were terminated, after about 53 minutes. No broad
  `pkill` was used, and the suite was not restarted.
- The output and progress files are preserved:
  - `/tmp/worker_0{1..4}_stdout_6afb8383*_{progress,result_cache,status,unexpected_output}`;
  - `scratchpad/final_suite.txt`, which is empty because the runner prints only at the end.

**Recorded before the stall:** 4,005 tests: 3,950 passed, 52 skipped, **2 failed, 1 error**.

| Test | Cause | Now |
|---|---|---|
| `MirrorPermissionUiPhase0Test` "grants and revokes a mirror permission through the real update route" (F) | **caused by this pass:** permission edits are now audited and the test's in-memory `main` had no `admin_audit_log` | fixture fixed; file 5/5 |
| `VarietyMalformedLinksF07aTest` "adds ASF, RAM, RA and MPG…" `('ram')` (E) | not in changed code; parallel-only | file passes alone, **58/58** |
| `DeploymentPackageVerifierTest` #19 "never extracts or writes the package…" (F) | not in changed code; parallel-only (temp-directory comparison) | file passes alone, **38/38** |

**Skipped (52):** the MariaDB- and MySQL-gated suites (no server configured in a normal run), plus a GD-dependent
test. Expected.

**Likely hanging file:** `tests/Feature/Support/Downloads/Archive/RejectionArchiveTest.php`.
- It is the only test file with no recorded result in this run (all other files were found in the four result
  caches).
- Worker 4 was idle, blocked reading its stdin; the runner was waiting for that file.
- **It passes alone in 15 s (44/44).** The stall therefore looks like a parallel hand-off or contention issue, not a
  hang inside the tests.
- The file and its code (`app/Support/Downloads/Archive/`) are pre-existing, untracked and untouched here.
- **Unconfirmed** without another full run.

### 8.2 Focused results (complete)

| Suite | Result |
|---|---|
| F02 (`AdminDeactivationF02Test`, drift test) | 19/19 |
| F09 link lock and existing Khotab mirror suite | 31/31 |
| F02 / F09 / sync / permission editor re-check after the last edits | **50/50** |
| Related admin suites (permission, staff, auth, sync, grant safety, backup API, F03 / F07 commands, retired routes, parity) | 210 run; the 3 failures were the audit-table fixture, fixed: `PermissionControllerTest` 11/11; then `MirrorPermissionUiPhase0Test` + staff / collision / backup API **42/42** |
| F01 / F03 / F05 / F06 / F07 / F08 / F10 targeted (36 files) | **947 tests: 934 passed, 13 skipped (MySQL-gated), 0 failed** |
| F07 MySQL byte-semantics suite on the disposable database | **13/13** |
| F03 / F07 commands end to end on the disposable database | passed (§4) |
| PHPStan | **0 errors** |
| Pint (`--test`, whole project) | passed |

### 8.3 Deployment note discovered by the failure

- `PermissionController` now writes `admin_audit_log`. **The Laravel permission editor therefore requires the
  `admin_audit_log` migration in production.**
- Production reported `migrate:status` = 7 Ran on 2026-09-24, so that table is absent there.
- It is already a prerequisite for every other audited admin feature (B6a, B6b, B4, B5, F03 / F07).
