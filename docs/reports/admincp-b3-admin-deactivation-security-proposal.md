# AdminCP B3 / F02: administrator deactivation — security investigation and architecture proposal

**Date:** 2026-11-16. **Phase:** investigation and proposal only. **Nothing implemented.**
**Basis:** plan §F02 (2026-11-14), which this report **re-verifies and corrects** (§5.4); decision-log #10 (permission
editor super-admin only).

**Scope of what was done:**
- **Read-only code reading** of Laravel, Gen 2 (`7amlat`, working copy **and** committed `HEAD` via `git show` /
  `git diff`) and OLD (`old-project`, the same).
- **Read-only aggregate queries** on the local `olddb.nuke_authors`: counts and value *formats* only. **No password,
  hash, key or API value was printed.**
- No code, schema or data change; no production access; no Way2Allah host request; nothing committed, pushed or
  deployed.

---

## 0. Summary for the owner

1. **One identity row, three systems.** Every system authenticates admins against the same shared row,
   `olddb.nuke_authors`.
   - Laravel and Gen 2 by `aid` + password; OLD by `uid` through a signed bridge cookie made at Gen 2 login.
   - `nuke_authors.uid` = the vBulletin `user.userid`.
   - **There is no status / active column** on `nuke_authors`.
2. **Sessions are not shared.**

   | System | Session store | Session cookie |
   |---|---|---|
   | Laravel | Laravel file sessions | host-only |
   | Gen 2 | PHP native sessions, `/var/cpanel/php/sessions/ea-php72` | PHPSESSID, host-only |
   | OLD | PHP native sessions, `/tmp` | PHPSESSID, host-only |

   The only cross-system carrier is OLD's **30-day `w2a_user` bridge cookie**, issued by Gen 2 on
   `.way2allah.com`.
3. **What happens to an existing session when an admin is deactivated:**
   - **Laravel:** **it can be revoked at the next request.** `AdminGuard::user()` re-reads `nuke_authors` on every
     request.
   - **Gen 2:** **it survives.** The session is a login-time snapshot; `main.php` never re-checks the database.
   - **OLD, non-super-admin:** it is cut once the bridge `uid` lookup fails.
   - **OLD, super-admin:** **it survives**, because `admin.php:35` also admits any session whose `usertype` is
     `superadmin`.
   - Legacy sessions expire only after **1,440 s idle**; **an actively used one never expires.** Closing these gaps
     needs a small legacy patch, or a host-level session purge.
4. **Release blockers found, before deactivation even matters:**
   - **(a)** The **committed** Gen 2 / OLD code trusts client-forgeable cookies:
     - Gen 2 `main.php`: `UserType($_COOKIE['newadmin'])`;
     - Gen 2 `index.php`: rebuilds the admin session from an unsigned `w2a_user` JSON cookie;
     - OLD `mainfile.php`: takes `radminsuper` from that JSON.

     Only the **uncommitted local working copies** are hardened. **Which version production runs is unverified.** If
     it runs `HEAD`, no deactivation design can protect Gen 2 or OLD.
   - **(b)** **15 admin accounts share one identical 9-character, non-hash `pwd` value.** Gen 2's login has a
     **plain-text fallback**, so anyone who knows that one string can sign in to Gen 2 as any of them.
   - **(c)** Gen 2 **public** pages grant admin controls to **any forum user id** in a client-settable cookie
     (`UserType($_COOKIE['bbuserid'])`, `admin_from_vb`).
5. **Recommended architecture: Option B′.**
   - **Laravel enforcement:** a new `admin_account_states` record checked by `AdminGuard`, the backup API and the
     CLI actors.
   - **Legacy neutralisation in the same transaction:** unusable password markers, a **unique negative sentinel
     `uid`** (not `0`), and a cleared API key.
   - **An owner-applied, two-line legacy patch** to close the surviving-session gaps.

   Laravel-only (A) is insufficient. Centralised authentication (C) is a migration project, not a feature.
6. **Several corrections to the 2026-11-14 plan are required (§5.4):**
   - `pwd` is `varchar(40)`, so the planned 64-hex marker does not fit;
   - `uid = 0` collides with 2 existing rows and opens a backup-API path;
   - `admin_audit_log` lives on `main`, which argues for putting the state table there too, so the change is atomic.

---

## 1. Current authentication architecture

### 1.1 Laravel AdminCP

| Element | Behaviour (file) |
|---|---|
| Guard | `AdminGuard` (`app/Domain/Identity/Guards/AdminGuard.php`), driver `admin-guard`; session key `admin_auth_id` holds only `nuke_authors.id` |
| Per request | `user()` → `AdminUser::on('main')->find($id)`, **re-read from the database every request** (memoised per request) |
| Login | `AdminAuthController::login` → `attempt()` → `resolveValidAdmin()`: `where('aid', …)`, then `LegacyPasswordVerifier::verify()` against `password ?: pwd`. Accepts bcrypt, md5 and sha1; **no plain-text fallback** |
| Hardening | throttling (`AdminLoginThrottle`), generic error, session regenerate on login and logout, POST-only logout |
| Rehash | on success, a non-bcrypt hash is rehashed into `password` (bcrypt). **`pwd` is left as it was** |
| Remember-me | **none** (`viaRemember()` is always false) |
| Session | `SESSION_DRIVER=file`, `SESSION_LIFETIME=120` (idle), `SESSION_DOMAIN=null` (host-only) |
| Authorisation | `admin.role` (`EnsureAdminHasRole`): 401 without a user, then an optional role check. `admin.permission` (`EnsureAdminHasPermission`): super-admin bypass, else Spatie permissions. Contextual admin on public pages uses the same guard (`ContextualAdmin::admin()`) |
| Spatie | role `super-admin` and `{module}.{key}` permissions on the **default** connection (local: SQLite file; 5 super-admin holders), rebuilt from `radminsuper` and the `permissions` blob by **`admin:sync-permissions`** (on demand, not scheduled) |
| Other identity entry points | `BackupApiAuthenticator`: `where('uid', ID)` + `API` key (32 chars), **no password**. CLI commands `khotab:repair-f03-series-counts` / `anasheed:repair-f07-links`: `--actor=<aid>`. Queued jobs (`Recheck*LinkSizeJob`) do not act as an admin |
| Staff management | `admincp/staff` (`authors.liststuff` / `authors.addstuff`): create links `uid` = a vBulletin `userid`, refuses a duplicate `uid`. `admincp/permissions/{admin}` (super-admin only): edit grants; **`updatePassword` writes `password` only, unaudited** |
| Audit | `admin_audit_log` on the **`main`** connection (57 rows locally) |

### 1.2 Gen 2 (`7amlat`)

| Element | Working copy (uncommitted) | Committed `HEAD` |
|---|---|---|
| Login (`admincp/index.php`) | `SELECT … FROM nuke_authors WHERE aid = …`; stored = `password` if non-empty, else `pwd`; bcrypt / md5 / sha1, **else a plain-text `===` comparison** | the same, including the plain-text fallback |
| Session | `$_SESSION['w2a']->admin = (object) $Author` (row snapshot), `usertype = superadmin|admin` from `radminsuper`, `$_SESSION['uid']` | the same, **plus** a session rebuilt from `$_COOKIE['w2a_user']` (unsigned JSON) when absent (`HEAD:212-213`) |
| AdminCP gate (`admincp/main.php`) | **session only** (`usertype` admin / superadmin); no database re-check | **`UserType($_COOKIE['newadmin'])`**: an unsigned client cookie resolved against the **forum** `user` table |
| Cookies set at login (`.way2allah.com`, 30 days) | `admin` = base64(`aid:md5:lang`), `aid`, `pwd` (md5), `newadmin` (= uid), `usertitle`, `newlogin`, and **`w2a_user` = `uid.issued_at.HMAC`** (only when `W2A_BRIDGE_SECRET` is set; secure and HttpOnly) | the same, but **`w2a_user` = `json_encode($Author)`**: the full row, including hashes, the API key and the permission blob |
| Session lifetime | `.htaccess`: `session.gc_maxlifetime 1440`, save path `/var/cpanel/php/sessions/ea-php72` | the same |
| Public pages | `UserType($_COOKIE['admin_from_vb'])` (`header.php:5`), `UserType($_COOKIE['bbuserid'])` (`functions.php:484,494`), `UserType()` (fatawa, surveys) → `VBUserType()` returns **`admin` for any existing forum user id** | the same |

### 1.3 OLD (`old-project`)

| Element | Working copy (uncommitted) | Committed `HEAD` |
|---|---|---|
| Gate (`admin.php:35`) | `if (!is_w2a_admin('') && $_SESSION['w2a']->usertype != 'superadmin') login();` | the same line |
| `is_w2a_admin()` (`mainfile.php:377`) | verifies the **HMAC bridge cookie** (30-day max age), then `SELECT * FROM nuke_authors WHERE uid = …`, and rebuilds the session from the **database** row. On failure returns 0 **without clearing** the session | **`json_decode($_COOKIE['w2a_user'])`**: `radminsuper` taken from the cookie (forgeable super-admin) |
| Per-action `GreenZone()` (`mainfile.php:1915`) | identity = the **forum session's** username (`VB5()`) → `nuke_authors.aid`; checks per-module tables and `radminsuper` | the same |
| Session | `/tmp`, `gc_maxlifetime 1440` | the same |

### 1.4 vBulletin / public users

- `nuke_authors.uid` is a foreign key to vBulletin `user.userid`.
- Public visitors and forum members authenticate through `VbulletinSessionGuard` (Laravel) or the forum's own
  session, and are **not** affected by any `nuke_authors` change.

## 2. Identity mapping

| Key | Meaning | Used by |
|---|---|---|
| `nuke_authors.id` | admin primary key | Laravel session (`admin_auth_id`), Spatie `model_id`, `admin_audit_log.actor_admin_id` |
| `nuke_authors.aid` | admin login name | Laravel and Gen 2 login; OLD `GreenZone()` (via the forum username); CLI `--actor` |
| `nuke_authors.uid` | vBulletin `userid` | OLD bridge lookup; backup API (`ID`); staff duplicate check; Gen 2 `VBUserType()` admin row |
| `password` (TEXT) / `pwd` (`varchar(40)`) | credentials | Laravel (`password ?: pwd`, no plain text); Gen 2 (`password` else `pwd`, **plain text accepted**) |
| `API` (`varchar(32)`) | volunteer backup key | Laravel `BackupApiAuthenticator`; Gen 2 `backup.php` |
| `radminsuper` | super-admin | Gen 2 / OLD `usertype`; Laravel via the `super-admin` role (sync) |
| `permissions` (serialized) | legacy grants | Gen 2 / OLD; Laravel via the sync |

**Same identity?** Yes: one row per admin across all systems. **But each system decides "is this an admin, now?"
differently, and only Laravel re-checks it on every request.**

## 3. Session and cookie lifecycle

| Carrier | Lifetime | Re-validated against the database? | Effect of a `nuke_authors` change |
|---|---|---|---|
| Laravel session (`admin_auth_id`) | 120 min idle | **every request** (`find($id)`) | immediate if the guard checks the state |
| Gen 2 PHP session (`$_SESSION['w2a']`) | 1,440 s **idle** (sliding; an active session lives on) | **never** | **none** until it expires |
| Gen 2 cookies `admin`, `aid`, `pwd`, `newadmin`, `usertitle`, `newlogin` | 30 days | the working-copy gate ignores them; **`HEAD` trusts `newadmin`** | none (working copy) / bypass (`HEAD`) |
| `w2a_user` bridge (HMAC) | 30 days | OLD: `uid` lookup per gate call | a changed `uid` fails it, **but** OLD super-admin sessions pass via the session clause |
| OLD PHP session | 1,440 s idle | only through `is_w2a_admin()` (which never clears it) | super-admins: **none** until expiry |
| Forum session (`bbsessionhash`) | forum policy | — | not an admin credential, except on Gen 2 public pages (§0.4c) |

## 4. Existing status fields

**None.** The `nuke_authors` columns are `id, uid, aid, name, url, email, pwd, counter, radminsuper, admlanguage,
feed_back, plain, key, password, thumb, permissions, API`.
- `counter`, `feed_back`, `plain` and `key` are not status fields. `plain` and `key` are empty in all 48 rows, and
  **must not** be repurposed: legacy semantics are unknown.
- `radminsuper = 0` only removes super-admin rights.
- **No existing field is safe to reuse as a status.**

**Local credential profile** (48 admins; formats only):
- `password`: 1 bcrypt, 1 other 32-character value, 46 empty;
- `pwd`: 31 md5-shaped, **15 identical 9-character alphanumeric values** (not a hash; 14 of those accounts have no
  permission blob; none is a super-admin), 2 empty;
- `uid = 0`: **2 rows**;
- `API` set: 2 rows (none with `uid = 0`);
- `radminsuper = 1`: 5.

## 5. Security gaps

### 5.1 Deactivation gaps (what any design must address)

| # | Gap | Severity |
|---|---|---|
| G1 | No status anywhere: a Spatie role removal is undone by `admin:sync-permissions`, and `admin.role` without an argument passes any admin | high |
| G2 | **Gen 2 existing sessions** never re-check the database (snapshot) | high (residual) |
| G3 | **OLD super-admin sessions** pass on `usertype = superadmin` even when the bridge fails (`admin.php:35`) | high (residual) |
| G4 | Gen 2 **new logins** succeed with the stored `password` / `pwd`, including **plain text** | high |
| G5 | **Backup API** authenticates by `uid` + `API` only (no password, no status) in Laravel and Gen 2 `backup.php` | medium |
| G6 | CLI `--actor` accepts any existing `aid` (deactivation not checked) | low |
| G7 | `updatePassword` and permission edits are **unaudited** | medium |
| G8 | Re-creating a deactivated admin's forum user as a new admin is possible if only `uid` is used for the duplicate check | low |

### 5.2 Pre-existing vulnerabilities found (independent of B3; owner action)

| # | Finding | Where |
|---|---|---|
| V1 | **Committed** Gen 2 AdminCP gate trusts the unsigned `newadmin` cookie, resolved via the forum `user` table: anyone can pass with any forum id | `7amlat` `HEAD` `admincp/main.php` (fixed only in the uncommitted working copy) |
| V2 | **Committed** Gen 2 login ships the full `nuke_authors` row (hashes, API key, permissions) in an unsigned `w2a_user` cookie, and rebuilds sessions from it | `7amlat` `HEAD` `admincp/index.php:196-213` |
| V3 | **Committed** OLD takes `radminsuper` from that cookie: forgeable super-admin | `old-project` `HEAD` `mainfile.php:390-402` |
| V4 | Gen 2 **public pages** treat any forum user id in `bbuserid` / `admin_from_vb` as admin (admin controls, hidden-content preview) | `7amlat` `header.php:5`, `functions.php:484,494`, fatawa / surveys (`VBUserType()`) |
| V5 | **15 accounts share one 9-character non-hash `pwd`**, accepted by Gen 2's plain-text fallback | local data; Gen 2 login |
| V6 | Gen 2 login cookies `admin` / `pwd` carry `md5(password)` for 30 days (secure / HttpOnly not set) | `7amlat` `admincp/index.php:256,280` |
| V7 | Debug `var_dump($_COOKIE['newadmin'])` / `var_dump($MyUser)` in `admincp/main.php` (both versions) | `7amlat` |

**Production verification is required for V1–V3.** If production runs `HEAD`, legacy admin access is forgeable and
**deactivation cannot be made effective there.**

### 5.3 What is achievable without modifying legacy code

| Requirement | Without a legacy change | Mechanism |
|---|---|---|
| Laravel login refused | **yes** | guard check |
| Existing Laravel sessions cut | **yes**, at the next request | guard check |
| Gen 2 new login refused | **yes** | unusable `password` / `pwd` markers (defeat bcrypt / md5 / sha1 / plain text) |
| OLD bridge (new requests) refused | **yes** for non-super-admins | `uid` → a sentinel that no bridge cookie can carry |
| Backup API refused | **yes** | `API` cleared, and a Laravel status check |
| **Gen 2 existing session** cut | **no** | survives until 1,440 s idle; needs patch L1 or a host purge |
| **OLD existing super-admin session** cut | **no** | survives until idle; needs patch L2 or a host purge |
| Any guarantee if production runs committed `HEAD` | **no** | needs V1–V3 fixed (deploy the hardened copies) |

### 5.4 Corrections to the 2026-11-14 plan (§F02.2)

1. **`pwd` is `varchar(40)`.** A "64 random hex behind a prefix" marker does not fit (strict mode error or
   truncation).
   - Use a non-hex marker of ≤ 40 characters for `pwd`, e.g. `!` + 39 random base62, which every Gen 2 branch,
     including plain text, rejects.
   - `password` (TEXT) can hold a longer marker.
   - Never hex-only: a 32 or 40-hex value would be treated as md5 / sha1.
2. **`uid = 0` is unsafe.**
   - 2 rows already have `uid = 0`.
   - The backup API does `where('uid', (int) ID)->first()`, so `ID=0` could match a deactivated admin whose `API` key
     still works.
   - The staff duplicate check would no longer see the deactivated admin's forum user.

   Use a **unique negative sentinel `uid = -id`**:
   - the bridge requires digits, so it can never match;
   - no vBulletin id is negative;
   - it is unique per admin.

   **Also clear `API`.**
3. **The state table belongs on `main`.** `admin_audit_log` already lives on `main`; placing `admin_account_states`
   there makes **state, legacy neutralisation and audit one transaction**, removing the plan's partial-failure state.
   It is still a Laravel-owned, additive table, which legacy never reads. ⚑ owner choice (the alternative is the
   default connection, with the plan's two-phase ordering).
4. **The staff duplicate check** must also consult `admin_account_states.original_uid` (G8).
5. **CLI actors and the backup API** must check the state (G5, G6).

## 6. Proposed solution: Option B′

### 6.1 Options compared

| | **A. Laravel-only** | **B. Shared status checked by all systems** | **C. Central authentication boundary** | **B′. Laravel state + legacy neutralisation (+ optional legacy patch)** |
|---|---|---|---|---|
| Mechanism | state table; `AdminGuard`, sync, CLI and API check it | new `nuke_authors` status column; Laravel, Gen 2 `index.php` / `main.php`, OLD `mainfile.php` / `admin.php` and `backup.php` check it on every request | all admin areas behind one identity provider or reverse-proxy authentication (SSO); legacy trusts only the gateway | A, **plus** in the same transaction: `password` / `pwd` → unusable markers, `uid` → `-id`, `API` → NULL. Optional patches L1 / L2 |
| Laravel guarantee | full | full | full | full |
| Gen 2 new logins | **not blocked** | blocked | blocked | **blocked** |
| Gen 2 existing sessions | open | **blocked** (per-request check) | blocked | open until idle expiry; **blocked with L1** |
| OLD bridge / sessions | open (30 days) | blocked | blocked | non-super blocked; super-admin open until idle; **blocked with L2** |
| Backup API | open in Gen 2 | blocked | blocked | **blocked** |
| Code changes | Laravel only | Laravel + **substantial legacy edits** across 5+ files | **major**: new auth infrastructure plus rewiring every legacy area | Laravel + **two small owner-applied legacy patches** (optional but recommended) |
| Database impact | new Laravel table | **ALTER on shared `nuke_authors`** (legacy `SELECT *` tolerates an extra column) | new identity store / config | new Laravel table on `main` (additive); row-level writes to `nuke_authors` |
| Legacy compatibility | untouched | legacy must be patched to read it; untouched legacy ignores it | legacy rewired | **untouched legacy keeps working**, it simply cannot authenticate the deactivated admin |
| Operational risk | false sense of security | coordinated multi-system deploy | high | low; one transactional Action |
| Deployment dependencies | none | owner applies legacy edits **and** the migration | infrastructure | migration (additive) + Laravel deploy; L1 / L2 at the owner's pace |
| Rollback | drop feature | revert legacy + column | large | reactivation is the in-app rollback; the feature can be disabled; the table is additive |

**Recommendation: B′.**
- It is the only option that gives **real cross-system denial for new access without touching legacy code**.
- It leaves a precisely bounded residual (open legacy sessions, idle ≤ 24 min) that two tiny, owner-applied patches
  close.
- B is B′ plus far more legacy surgery for the same result. C is a migration programme.
- A alone is **insufficient** (Gen 2 login and the OLD bridge stay open).

### 6.2 Deactivation semantics (B′)

| Surface | Behaviour after deactivation |
|---|---|
| Laravel login | refused (the guard checks the state *before* the password; generic error; counted by the throttle) |
| Existing Laravel session | the next request → `user()` returns `null` and forgets `admin_auth_id` → 401 / redirect to login; contextual admin controls disappear |
| Remember-me | none exists (Laravel); legacy 30-day cookies are not authentication in the hardened copies |
| Permission checks | inert (no user); Spatie grants **kept** for reactivation; `admin:sync-permissions` **skips** deactivated admins |
| Admin-only routes | every `admin.role` / `admin.permission` route → 401 |
| CLI (`--actor`) | refused ("deactivated admin") |
| Backup API | refused (`API` NULL; Laravel also checks the state); Gen 2 `backup.php` fails on the key |
| Gen 2 login | refused (markers) |
| Gen 2 open session | **remains until 1,440 s idle** (residual R1) → **L1** closes it |
| OLD bridge | refused (`uid` sentinel) |
| OLD open super-admin session | **remains until idle** (residual R2) → **L2** closes it |
| Background jobs | none act as an admin |

**In-flight requests:** a request that already passed the guard completes; there is no mid-request revocation.
Optional: `AdminRecordAuthorizer::authorize()` re-checks the state before each write, which narrows the window to a
single statement.

### 6.3 Super-admin protection

- **Who:** super-admin only (Spatie `super-admin`, routes `admin.role:super-admin`). **No new permission.** Existing
  `authors.liststuff` / `authors.addstuff` are insufficient and must not grant this.
- **No self-deactivation.**
- **Last-active super-admin rule:** the count of admins with (`radminsuper = 1` **or** the Spatie `super-admin` role)
  and no active deactivation, excluding the target, must stay ≥ 1.
- **Peers:** a super-admin may deactivate another super-admin (needed operationally), subject to the last-active rule.
  ⚑ Optional stricter policy: super-admin targets need a second super-admin's confirmation (two-person rule).
- **Race safety:** the whole check-and-write runs in **one transaction on `main`**:
  - `SELECT … FOR UPDATE` on the target's and every super-admin's state rows (and `nuke_authors` rows);
  - plus an atomic cache lock `admin-deactivation`.

  Two super-admins deactivating each other at once cannot both succeed.

### 6.4 Audit and recovery

- **Confirmation:** a dedicated form showing the target, the legacy effects and the residual risk.
  - The reason is **required** (≤ 500 characters).
  - The actor re-enters their own password, or types the target's `aid` (⚑ which).
  - POST + CSRF.
- **Audit (`admin_audit_log`, same transaction):**
  - `deactivate_admin` with `{target_aid, reason, legacy: {uid_from, uid_to, api_cleared, credentials_neutralised},
    super_admin_before}`;
  - `reactivate_admin` with `{reason, uid_restored}`.
  - **Never** a hash, marker or API value.
  - Also audit `permissions.update` and `updatePassword` (G7).
- **Reactivation (super-admin):**
  - **sets a new password** (bcrypt into `password`; `pwd` stays the marker, and Gen 2 prefers `password`);
  - restores `uid` from `original_uid` (refused if another admin now holds it);
  - the `API` key is regenerated on request (⚑ or left empty);
  - Spatie grants come back as they were (never removed).
- **Session invalidation:**
  - Laravel: implicit (guard).
  - Legacy: L1 / L2, or a host-level purge of the session files containing the admin's `aid` (operational runbook,
    owner).
- **Last-admin recovery:**
  - Prevented by the rule.
  - Break-glass: an artisan command `admin:reactivate --aid=… --reason=…` run by the operator on the server (server
    shell access is the trust anchor). It is audited with a system actor marker. ⚑ This needs `AdminAuditRecorder` to
    accept a CLI system actor, or a designated break-glass admin row.
  - **No direct database edits.**

### 6.5 Legacy patches (owner-applied; this project does not modify `7amlat` / `old-project`)

- **L1, Gen 2 `admincp/main.php`, after the session gate:** re-select `uid, password, pwd` for
  `$_SESSION['w2a']->admin->id`. If the row is missing, or `uid` ≤ 0, or the credential differs from the session
  snapshot, destroy the session and send the admin to login. One indexed query per AdminCP request.
- **L2, OLD `admin.php:35`:** drop the `|| usertype == 'superadmin'` session shortcut (require `is_w2a_admin('')`),
  or make `is_w2a_admin()` clear `$_SESSION['w2a']` when verification fails.
- **Prerequisite:** production runs the hardened working copies (V1–V3).

## 7. Required changes (B′; for a later, approved implementation)

**New (Laravel):**
- the migration `admin_account_states`;
- `AdminAccountState` model;
- `DeactivateAdminAction` / `ReactivateAdminAction` (one transaction, locks, rules, audit);
- form requests, views (confirmation, badge, filter), routes `admincp/staff/{admin}/deactivate|reactivate` (POST,
  `admin.role:super-admin`);
- optional break-glass command.

**Edited (Laravel):**
- `AdminGuard` (`user()` / `resolveValidAdmin()` / `loginUsingId()` / `onceUsingId()` refuse deactivated);
- `SyncLegacyAdminPermissionsAction` (skip);
- `BackupApiAuthenticator` (refuse);
- the CLI actor resolution (refuse);
- `AdminStaffController::store` (duplicate check via `original_uid`);
- `PermissionController` (audit; read-only for deactivated admins);
- `AuditAdminGrants` (report deactivated);
- staff views.

**`nuke_authors` writes (row-level, in the Action):**
- `password` → marker;
- `pwd` → ≤ 40-character non-hex marker;
- `uid` → `-id`;
- `API` → NULL.

**Untouched:** `aid`, `name`, `email`, `radminsuper`, `permissions`, `thumb`, history.

## 8. Migration requirements

- **One additive table** (proposed on `main`):

  | Column | Notes |
  |---|---|
  | `admin_id` | unique |
  | `deactivated_at` | null = active |
  | `deactivated_by_admin_id` | |
  | `reason` | |
  | `original_uid` | |
  | `original_api_present` | boolean |
  | `reactivated_at` / `reactivated_by_admin_id` | |
  | `timestamps` | |

  Index `(deactivated_at)`.
- **No change to `nuke_authors`' schema** (Option B would need one; B′ does not).
- **Legacy tolerance of the values:**
  - `uid` negative (signed `int`);
  - the `pwd` marker ≤ 40 characters, non-hex;
  - `API` NULL (the column is nullable; both authenticators fail on it).

  Forum and public users are untouched; no vBulletin table is written.

## 9. Test plan (to implement with B′)

| Area | Tests |
|---|---|
| Login | deactivated → refused with the generic error; throttle still counts; reactivated + new password → accepted; the old password refused after reactivation |
| Laravel session | logged-in admin deactivated → next request 401 / redirect, session key forgotten; contextual controls gone; `admin.permission` and `admin.role` routes 401 |
| Permissions | sync skips deactivated (grants unchanged); grants inert while deactivated; restored on reactivation |
| Legacy (DB-level, isolated) | after deactivation: `password` / `pwd` markers fit (≤ 40 for `pwd`), non-hex, never equal to any input; `uid = -id`; `API` NULL; `aid` / `radminsuper` / `permissions` untouched; reactivation restores `uid`, sets bcrypt, keeps `pwd` marker |
| Legacy (simulation, read-only port) | replicate Gen 2's credential check and OLD's bridge lookup in a test helper and prove both refuse the neutralised row (no legacy file executed) |
| Backup API | deactivated → refused for `ID` = old `uid`, `-id` and `0` |
| CLI | `--actor` of a deactivated admin → refused |
| Super-admin rules | self → 422; last active super-admin → 422; non-super-admin → 403; peer super-admin allowed while another remains |
| Concurrency | two super-admins deactivating each other: exactly one succeeds (lock + `FOR UPDATE`; MySQL env-gated test like F07-B2) |
| Atomicity | failure injected mid-transaction → no state row, `nuke_authors` unchanged, no audit |
| Audit | one row per transition, no secret material, reason recorded; `updatePassword` / permission edits now audited |
| Staff | re-adding the deactivated admin's forum user refused |
| UI | controls only for super-admins; badge and filter; read-only edit for deactivated |

## 10. Explicit owner decisions

1. **Approve Option B′** (vs A / B / C).
2. **State table location:** `main` (atomic with `nuke_authors` and audit; recommended) or the default connection.
3. **Legacy patches L1 / L2:** apply them, or accept residuals R1 / R2 (open legacy sessions until 24-minute idle
   expiry), or a host-level session-purge runbook.
4. **Production legacy version:** confirm whether production runs the hardened working copies or committed `HEAD`
   (V1–V3). **Blocker.**
5. **The 15 shared-`pwd` accounts (V5):** identify them, then rotate or neutralise them now, independent of B3.
6. **V4 (Gen 2 public admin controls by forum cookie)** and **V6 / V7:** schedule fixes (legacy, owner).
7. **Confirmation style** (actor password vs typed `aid`) and the optional **two-person rule** for super-admin
   targets.
8. **The API key on reactivation:** regenerate, or leave empty.
9. **Break-glass recovery:** a CLI command with a system actor (needs an audit-recorder change), or a designated
   break-glass admin.
10. Audit `updatePassword` and permission edits in the same batch (recommended).

## 11. Release risks

| Risk | Mitigation |
|---|---|
| Production legacy code is the committed, forgeable version (V1–V3) | **Blocker:** verify; deploy the hardened copies first |
| Residual legacy sessions (R1 / R2) | L1 / L2, or a purge runbook; document; short idle (1,440 s) |
| Neutralising credentials on the wrong row | Action takes `nuke_authors.id`, re-reads under lock, refuses super-admin last-active, audited; reactivation path tested |
| A legacy reader of `uid` / `API` not found here | negative sentinel and NULL chosen for fail-closed behaviour; owner to confirm no other tool reads them (forum plugins, cron) |
| Lock-out of all super-admins | last-active rule under lock; break-glass |
| Cross-connection partial failure (if the table is on the default connection) | prefer `main`; otherwise the plan's ordered, idempotent two-phase write |
| Sync re-granting | the sync skips deactivated; the guard makes grants inert anyway |
| The 15 shared-credential accounts | independent urgent action (V5) |

**Out of scope here (reported):** V1–V7 are pre-existing legacy weaknesses. Fixing them requires legacy changes,
which this project does not make.
