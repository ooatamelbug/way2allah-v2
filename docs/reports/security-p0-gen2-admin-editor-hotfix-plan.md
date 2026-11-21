# SECURITY P0-A: Gen 2 administrator account takeover — hotfix plan

**Date:** 2026-11-16. **Phase:** investigation and patch preparation only. **No code changed; Gen 2 / OLD untouched.**
**Basis:** `admincp-b3-security-design-review.md` (finding V8), `admincp-b3-admin-deactivation-security-proposal.md`.

**Scope of what was done:**
- Read-only code reading of `7amlat` (working copy and committed `HEAD`) and `old-project`.
- Read-only aggregate queries on the local `olddb`.
- **No credential value was printed.** One equality check compared hashes inside SQL; the literal was read from the
  source file into a shell variable, never echoed.
- No login attempted; no production access; no Way2Allah host request; nothing committed, pushed or deployed.

---

## 0. Summary

- **Vulnerable:** any authenticated Gen 2 admin session can **rewrite any admin's legacy permissions**
  (`admincp/authors/edit_author.php`, `admincp/backup/edit_author.php`, the latter SQL-injectable) and **reset any
  admin's `pwd`**, super-admins included (`admincp/authors/edit_author.php`).
  - No super-admin check, no CSRF token, no audit.
  - 3 of 5 super-admins authenticate by `pwd` in Gen 2 and Laravel.
- **The 15 shared-credential accounts hold the hard-coded default password** assigned by Gen 2's own admin-creation
  code (`admincp/authors/index.php:~125`). The literal is published in the source; confirmed by hash comparison, not
  printed.
  - Any reader of the repository can therefore sign in to Gen 2 as one of them (plain-text fallback), and then take
    over a super-admin through V8.
- **Does it depend on unsigned cookies?**
  - **Not on the hardened working copy:** any genuine admin login is enough.
  - **On committed `HEAD`:** forged cookies make it worse (no login needed at all).
  - **The fix itself must trust only a server-side session,** so it is only sound once P0 (the hardened
    `main.php` / `index.php`) is deployed (§5).
- **Minimal safe fix:**
  - one central, database-backed `w2a_require_superadmin()` check (fail closed);
  - POST-only handlers with a per-session CSRF token;
  - integer-cast targets;
  - self-service password change for one's own account only (current password re-entered);
  - write-once audit rows;
  - plus urgent rotation of the 15 default-password accounts.

## 1. Confirmed vulnerable actions

**Endpoint inventory.** Every Gen 2 code path that writes `nuke_authors`, the per-module admin tables or
`nuke_modules.admins` (`grep` of all of `7amlat`, bundled forum code excluded):

| # | Endpoint | Method | Writes | Authorisation today | State |
|---|---|---|---|---|---|
| **E1** | `admincp/authors/edit_author.php` | POST (`authorization[...]`, `uid`) | **`nuke_authors.permissions`** of **any** `uid` (`intval`) | `main.php` gate only (any admin session) | **live, vulnerable** |
| **E2** | `admincp/authors/edit_author.php` | POST (`update_password`, `new_password`, `author_id`) | **`nuke_authors.pwd = md5(new)`** of **any** id (`intval`) | `main.php` only | **live, vulnerable**. Also ineffective for admins with a non-empty `password` (Gen 2 / Laravel prefer `password`) |
| **E3** | `admincp/backup/edit_author.php` | **any** method (`if (isset($_POST))` is always true) | `nuke_authors.permissions = serialize($_POST['authorization'])` WHERE `uid = '$_POST[uid]'` | `main.php` only | **live, vulnerable: SQL injection** (unescaped `uid`). **A plain GET overwrites** the permissions of the `uid = 0` rows with `serialize(null)` |
| E4 | `admincp/broadcasting/edit_author.php` | any | none (the `UPDATE` is commented out) | `main.php` | inert |
| E5 | `admincp/khotab/edit_author.php`, `admincp/chat/edit_author.php` | GET | none (forms post to themselves; no handler) | `main.php` | read-only: displays any admin's forum e-mail and details to any admin (info exposure) |
| E6 | `admincp/authors/index.php` (create admin) | POST | `INSERT nuke_authors` with the **hard-coded default `pwd`** | `main.php` | **dead:** `die('hhhh')` precedes the handler (`HEAD` and working copy). Must never be re-enabled as written |
| E7 | `admincp/index.php` `RenameUser()` | — | `aid` / `name` and per-module `_mod` tables, `nuke_modules.admins` | — | **dead:** no caller anywhere in `7amlat` |
| E8 | `english/admin.php` + `english/admin/modules/authors.php` (PHP-Nuke) | GET / POST | `aid`, `pwd`, **`radminsuper`**, insert and delete authors | PHP-Nuke `admin` cookie (base64 `aid:md5:lang`) compared with `pwd`, **the same cookie Gen 2's login sets on `.way2allah.com` for 30 days** | **likely dead:** `english/db/mysql.php` uses `mysql_connect` / `mysql_query`, removed in PHP 7. The local `.htaccess` targets `ea-php72`. **Lethal if any vhost still runs PHP 5:** the cookie is forgeable offline for the 15 default-password accounts |

**API keys, `uid`, `aid`, `radminsuper`:** no live Gen 2 AdminCP endpoint writes `API`, `uid`, `aid` or
`radminsuper` (only the dead E6 / E7 / E8).

**Related, outside the editor:**
- Gen 2 AdminCP enforces **no** per-module permission server-side; the sidebar only hides menu items.
- The permission blob that E1 / E3 write is **consumed elsewhere**:
  - Laravel `admin:sync-permissions` rebuilds Spatie grants from it, so **an E1 self-grant escalates into Laravel**
    at the next sync;
  - the backup API's per-category flags;
  - OLD's `GreenZone()`.

## 2. Root cause

1. **The authorisation boundary is "is an admin", not "may manage admins".** `admincp/main.php` admits any admin
   session (`usertype` admin or superadmin); no page re-checks the role, and no shared helper exists.
2. **State-changing requests are not method-, CSRF- or target-checked:** `isset($_POST)` instead of a method check;
   no token; target ids taken from the body (one unescaped).
3. **Identity on `HEAD` is forgeable** (V1 / V2: `newadmin` cookie gate; session rebuilt from the unsigned
   `w2a_user` JSON). Even a correct role check would trust a forged session there.
4. **Credential hygiene:**
   - a default password hard-coded in the creation code (E6), still held by 15 accounts;
   - md5 resets;
   - a plain-text login fallback;
   - two credential columns (`password` preferred, `pwd` reset): a reset is silently ineffective for some admins.

## 3. Authorisation policy (narrowest safe)

| Action | Allowed to | Conditions |
|---|---|---|
| Change **any** admin's permissions (E1, E3) | **super-admin only** | actor row re-read from the database: `radminsuper = 1`; POST; valid CSRF token; target `uid` an integer > 0 that **exists**; **target ≠ actor is not required** (a super-admin editing themselves is harmless); never via GET |
| Reset **another** admin's password (E2) | **super-admin only** | as above; target id integer > 0 and exists. **A super-admin target requires the actor to be super-admin** (already implied). ⚑ Optional: forbid resetting another *super-admin's* password except by a second super-admin |
| Change **own** password (E2 with `author_id == actor id`) | **any admin** | POST + CSRF + **current password re-entered and verified** with the same verifier as login; new password ≥ 8 characters |
| Create / rename / delete admins, change `uid` / `aid` / `radminsuper` / `API` | **not in Gen 2.** Laravel AdminCP only (`admincp/staff`, super-admin `permissions/{admin}`) | E6 stays dead (remove the default-password literal); E7 removed; E8 denied |
| View another admin's details (E5) | super-admin (⚑ or keep for all admins; it exposes forum e-mail only) | GET |

**Fail-closed rules:**
- no session;
- no `$_SESSION['w2a']->admin->id`;
- a database error;
- the actor row missing;
- `radminsuper ≠ 1`.

Each of these answers **403 and exits before any write**. The role is **never** taken from a cookie, nor from the
session's `usertype` snapshot alone (stale after a demotion): **always re-read** `nuke_authors` by the session's admin
`id`.

## 4. Required code changes (Gen 2; owner-applied; prepared here, not applied)

### 4.1 Central helpers: `admincp/admin_functions.php`, already included by `main.php` on every AdminCP page

```php
// Trusted identity: the server-side session created by admincp/index.php after the credential check.
function w2a_session_admin_id(): int {
    return (isset($_SESSION['w2a']->admin->id) && is_numeric($_SESSION['w2a']->admin->id))
        ? (int) $_SESSION['w2a']->admin->id : 0;
}
// Re-read the actor from the database on every call; never trust the session's usertype snapshot or any cookie.
function w2a_current_admin_row() {
    global $w2adb;
    $id = w2a_session_admin_id();
    if ($id <= 0) return null;
    $row = $w2adb->get_row("SELECT id, uid, aid, radminsuper, password, pwd FROM nuke_authors WHERE id = " . $id . " LIMIT 1");
    return (is_object($row) && (int) $row->id === $id) ? $row : null;
}
function w2a_require_superadmin() {
    $row = w2a_current_admin_row();
    if (!$row || (int) $row->radminsuper !== 1) { http_response_code(403); exit('403'); }
    return $row;
}
// Per-session CSRF token (random_bytes; hash_equals).
function w2a_csrf_token(): string { /* create once per session, 32 random bytes hex */ }
function w2a_require_csrf(): void { /* POST only; hash_equals(session token, $_POST['_w2a_csrf']) or 403 + exit */ }
```

### 4.2 Per file

| File | Change |
|---|---|
| `admincp/authors/edit_author.php` | Wrap the handler in `if ($_SERVER['REQUEST_METHOD'] === 'POST') { w2a_require_csrf(); … }`. **Permissions branch:** `$actor = w2a_require_superadmin();` target `uid` = `intval`, must be > 0 and exist. **Password branch:** if `intval(author_id) === w2a_session_admin_id()`, require and verify `current_password` (the same bcrypt / md5 / sha1 logic as login; no plain text), else `w2a_require_superadmin()`; target must exist. Write the new credential as **`password = password_hash(new, PASSWORD_BCRYPT)`** (Gen 2 and Laravel both prefer `password`) ⚑. Add the hidden `_w2a_csrf` field to both forms |
| `admincp/backup/edit_author.php` | Replace `if (isset($_POST))` with the POST + `isset($_POST['authorization'])` check; `w2a_require_csrf()`; `w2a_require_superadmin()`; `intval($_POST['uid'])` > 0 and exists (removes the SQL injection and the GET overwrite) |
| `admincp/broadcasting/edit_author.php` | Remove the dead `isset($_POST)` block (no write today); add `w2a_require_superadmin()` for the view ⚑ |
| `admincp/khotab/edit_author.php`, `admincp/chat/edit_author.php` | View only: `w2a_require_superadmin()` ⚑ (or leave; they write nothing) |
| `admincp/authors/index.php` | Keep the handler disabled. **Delete the default-password `INSERT`** so it can never be re-enabled as written (admin creation lives in Laravel) |
| `admincp/index.php` | Delete the dead `RenameUser()` |
| `english/` | **Deny at the web server** (`Require all denied` for `/english/`) or remove from the deployed tree |
| `admincp/main.php`, `admincp/index.php`, `w2a_config.php` | **P0:** the hardened working copies (session-only gate; HMAC bridge; no session rebuild from cookies) must be the deployed versions. **Prerequisite, not part of this diff** |

**Audit** (no secrets): one row per successful change in Gen 2 to the existing `nuke_islamic_logs`, or to the Laravel
`admin_audit_log` table on `main` ⚑:
- actor id / aid, target id, action (`permissions_update`, `password_reset`, `password_self_change`), timestamp, IP;
- **never** the password, hash or blob content; for permissions, only the changed module keys.

Refused attempts are logged as `forbidden` with actor and target (no body).

**Laravel side (same release, small, separately approved):**
- do **not** run `admin:sync-permissions` until the blobs have been reviewed, since a malicious blob would become
  Spatie grants;
- audit `PermissionController::update` / `updatePassword` (G7).

## 5. Authentication dependencies

| Dependency | Why the fix needs it | Status |
|---|---|---|
| **P0-1:** `admincp/main.php` session-only gate (no `UserType($_COOKIE['newadmin'])`) | on `HEAD`, a forged `newadmin` cookie reaches every AdminCP page **without a session**. `w2a_session_admin_id()` would then be 0, so the fix fails closed. **But** see P0-2 | hardened in the working copy; production unknown |
| **P0-2:** `admincp/index.php` **no session rebuild from `w2a_user`** (`HEAD:212-213`) | on `HEAD` a forged JSON cookie can **create** `$_SESSION['w2a']->admin` with any `id`, including a real super-admin's. The database re-read would then pass for the forged id. **The fix is bypassable on `HEAD`** | hardened in the working copy; production unknown |
| P0-3: bridge secret configured | fail-closed bridge; not needed by the editor fix itself | `tests/bridge_checks.php` records "verified in production: file present, readable, 64 chars" (**claim, not verified here**) |
| Credential rotation of the 15 default-password accounts | the fix stops escalation, but those accounts still sign in to Gen 2 (and through E5 see staff data) | required |

**Conclusion:**
- The editor **can be secured independently of the unsigned-cookie weaknesses only after P0-1 and P0-2 are
  deployed.** Before that, any role check is bypassable by forging the identity it trusts.
- **Deploy order:** P0 (verify, or deploy the hardened files) → the editor hotfix → rotation. Rotation can run first.

## 6. Test plan

The project has no Gen 2 harness. Two layers are proposed, both runnable locally against the existing legacy runtime
(the `legacy-logout-test.mjs` setup on `way2allah.com:8083`), **pointed at a disposable database copy, never the
shared `olddb`**:

1. **Static checks** (a `tests/admin_editor_checks.php` in the style of the existing `tests/bridge_checks.php`):
   - each write branch calls `w2a_require_csrf()` and `w2a_require_superadmin()` (or the self-path) **before** its
     `UPDATE`;
   - no `isset($_POST)` gate;
   - no unescaped `$_POST` inside SQL;
   - no `pwd  = '` default literal;
   - `RenameUser` absent.
2. **Black-box HTTP** (curl or puppeteer; sessions created by real logins on the disposable copy):

| Case | Expected |
|---|---|
| Ordinary admin POSTs permissions for another admin (E1, E3) | 403; blob unchanged; refusal logged |
| Ordinary admin POSTs permissions for a super-admin | 403; unchanged |
| Ordinary admin grants **themself** permissions | 403 (permission edits are super-only) |
| Ordinary admin resets another admin's / a super-admin's password (E2) | 403; `pwd` / `password` unchanged |
| Ordinary admin changes **own** password with the correct current password | 200; new bcrypt in `password`; login with the new password works; the old one is refused |
| … with a wrong or missing current password | 403 / 422; unchanged |
| Any write without or with a wrong CSRF token | 403; unchanged |
| GET to `backup/edit_author.php` | **no write** (the `uid = 0` rows are unchanged) |
| SQL-injection payload in `uid` | rejected (`intval` → not found → no write) |
| No session / expired session / session for a deleted admin | 403 / redirect; no write |
| Session `usertype = superadmin` but database `radminsuper = 0` (demoted) | 403 (database re-read wins) |
| Forged `newadmin` / `w2a_user` / `admin` cookies, no session | 403 / login; no write (with P0 deployed) |
| Super-admin edits another admin's permissions and resets a password | 200; changed; audit row without secrets |
| `english/admin.php` | denied (403) at the web server |
| Existing workflows | super-admin permission editing unchanged in UI; Laravel staff and permission pages unaffected |
| Audit | one row per success and per refusal; no password, hash or blob content in any log |

## 7. Deployment prerequisites

1. **Run the B3 review §8 checklist** (marker strings / checksums). Confirm production has the hardened
   `admincp/main.php` and `admincp/index.php` (P0-1 / P0-2). **If not, deploy those first.**
2. **Rotate the 15 default-password accounts now** (independent of the code):
   - for each one legitimately in use, set an individual bcrypt password via Laravel `admincp/permissions/{admin}`
     (writes `password`, which Gen 2 prefers);
   - neutralise the rest (`password` and `pwd` unusable markers) through an audited path;
   - review the 4 log entries since 2025.
3. **Review every admin's permission blob and the super-admins' credentials** for signs of past abuse. E1 / E3 leave
   no audit trail; compare against backups.
4. Hold `admin:sync-permissions` until step 3 is done.
5. Back up the files to be changed; deploy in the order P0 → helpers + editor files → web-server deny for `english/`.

## 8. Rollback considerations

- The fix is additive checks plus file deletions (dead code).
- **Rollback = restore the backed-up files.** No database migration; audit rows remain harmless.
- **Do not roll back to a state where E1–E3 are open.** If the hotfix misbehaves, deny `admincp/authors/` and
  `admincp/backup/edit_author.php` at the web server instead (permission editing then happens in Laravel only).
- A password changed through the new self-service path is bcrypt in `password`; that is compatible with Gen 2 and
  Laravel, so no rollback issue.

## 9. Remaining security risks (after the hotfix)

| Risk | Note |
|---|---|
| Gen 2 AdminCP has **no per-module server-side authorisation**: every admin can use every module | broader than the editor; schedule a module-permission gate (reusing `w2a_current_admin_row()`) |
| Plain-text login fallback (V5); md5 `admin` / `pwd` cookies without flags (V6); `var_dump` (V7) | the B3 review E5 |
| Gen 2 public pages trust forum-id cookies (V4) | the B3 review E2 |
| OLD super-admin session shortcut (V9), pass-the-hash file (V10), `vb5.php` `pwd` rewrite (V11) | the B3 review L2 / E3 / E4 |
| Survey pages hard-code `$superadmin = 1` | review separately |
| `english/` PHP-Nuke tree | deny or remove (E8) |
| Abuse that already happened (no audit) | step 3 of §7 |
| B3 deactivation | still pending; builds on this hotfix |
