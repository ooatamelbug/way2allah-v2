# SECURITY P0-A: Gen 2 AdminCP trusted identity (D1/D2) and administrator editor hotfix — implementation results

**Date:** 2026-11-16.
**Status (2026-11-16, after the final closure, §C):**

| | |
|---|---|
| **CLOSED LOCALLY** | D1/D2, the editor hotfix, the audit store (`nuke_islamic_logs`), debug-output removal, **credential-bound session revocation**, the backup-editor deny rule (prepared and tested on a local Apache), regression suites |
| **NOT DEPLOYED** | nothing has been committed, pushed or deployed; production has not been accessed |
| **PRODUCTION RELEASE BLOCKERS** | §C.7, with the checklist in §C.5 (owner-operated). §0.7 is superseded |
- Approval: "Security P0-A — APPROVED: D1/D2 + Admin Editor Security Hotfix".
- Scope respected:
  - local implementation and testing only;
  - no production access and no request to any Way2Allah host;
  - no shared-database write, no production credential change, no migration;
  - nothing committed, pushed or deployed;
  - OLD not modified; P0-B and B3 not started.
- Pre-existing uncommitted changes in `admincp/index.php` and `admincp/main.php` were preserved; P0-A edits sit on top of them.
- The earlier BLOCKED version of this report (session poisoning through `VBUserType()`) is superseded by §2.
- **§0 (final review) supersedes §7 and §11 where they differ.**

---

## 0. Final review — release blockers (2026-11-16)

Request: "Security P0-A — Final Review". Same restrictions: local only, nothing deployed, no shared-database write, P0-B and B3 not started.

### 0.1 Audit table: the implementation targeted the wrong database (fixed locally)

**Findings:**
- **Laravel's `admin_audit_log`:**
  - created by `database/migrations/2026_10_12_100000_create_admin_audit_log_table.php` on Laravel's **`main`** connection;
  - InnoDB, utf8mb4, with columns `actor_admin_id`, `actor_aid`, `action`, `module`, `entity_type`, `entity_id`, `metadata` (JSON), `ip` (45) and `created_at`.
  - Locally `main` = `olddb` @ 127.0.0.1:3307, which is **the same database** Gen 2's `$w2adb` uses. That is why the first round passed.
- **Production is different**, according to the owner-confirmed topology (`7amlat/db_old_to_new_forensic_report.md` §20.1, `docs/PROJECT-HANDOFF.md:289`):

  | Application | Production database |
  |---|---|
  | Gen 2 public site **and the Gen 2 dashboard (this AdminCP)** | **`w2acp_db2025-2`** (MariaDB 10.11.19, user `w2acp@localhost`, `GRANT ALL ON w2acp_db2025-2.*` only) |
  | Laravel | **`w2acp_laravel`** |

  - The Layer-1 dump of `w2acp_db2025-2` (`7amlat/w2acp_db2025-2.sql`, schema lines only) has **no `admin_audit_log`**.
  - It does have `nuke_authors` and `nuke_islamic_logs`, both **InnoDB**.
- **So the first implementation:**
  - would have **failed closed (HTTP 500) on every editor save** in production, because the table is missing;
  - cannot be redirected to `w2acp_laravel.admin_audit_log`, because Gen 2's DB user has no grant there. Granting one is a production privilege change; the transaction would then span two schemas; and `actor_admin_id` and `entity_id` would be ids from **another schema's** `nuke_authors` copy.

**Decision (reuse existing infrastructure, no new table, no migration):** audit rows now go to **`nuke_islamic_logs` in Gen 2's own schema.**
- It is the existing legacy admin log:
  - shared live state;
  - written by OLD's modules (Khotab, Anasheed, Telawah, Cat, …);
  - read by OLD's moderator-log views;
  - the target of Laravel's own (default-off) `LegacyAuditLogCompatibilityWriter`.
- **Row shape:**

  | Column | Value |
  |---|---|
  | `aid` | actor aid |
  | `module` | `gen2_admincp` |
  | `type` | `admin` |
  | `target_id` | target `nuke_authors.id` |
  | `action` | `admin_permissions_update` / `admin_password_reset` / `admin_password_self_change` |
  | `extrainfo` | plain summary, e.g. `[gen2-admincp] admin_password_reset admin#1004 by admin#1001 via=authors/edit_author.php` (permission edits add `granted=module:key,…`) |
  | `mytime` | unix time |
  | `ip` | IPv4 |
  | `hidden` | 0 |

- **`extrainfo` is built only from integers, fixed ASCII action names and identifier-shaped permission keys.** No aid, URL or free text goes in, because OLD renders the column.
- **IP handling:** IPv6 is longer than `ip varchar(15)`, so it is appended to `extrainfo` instead.
- **Pruning:** OLD's pruning (`DELETE … WHERE mytime < … AND module = '<OLD module>'`) is module-scoped and never matches `gen2_admincp`.

**Required audit fields:**

| Field | Supported | How |
|---|---|---|
| actor id | yes | `extrainfo` (`by admin#<id>`) |
| actor aid | yes | `aid` column |
| action | yes | `action` column |
| target | yes | `type` + `target_id` |
| changed permission set | yes | `extrainfo` (`granted=`) |
| time | yes | `mytime` |
| IP | yes | `ip`; IPv6 goes to `extrainfo` |
| no secrets | yes | enforced by construction and tested |

What is lost against `admin_audit_log`: structured JSON and an indexed actor-id column. Both are acceptable for P0-A.

**Atomicity:**
- The `nuke_authors` UPDATE and the `nuke_islamic_logs` INSERT run on **one mysqli connection in one transaction**. If either fails, it rolls back.
- **Tested:** with the log table renamed away, the INSERT fails *after* the UPDATE ran; the save returns 500, and permissions and password are unchanged (T69–T70).

| Failure scenario | Outcome |
|---|---|
| Log table missing or unwritable | rollback, 500, no change (tested) |
| UPDATE fails | rollback, no audit row |
| Connection drops during COMMIT | outcome unknown to PHP (500 shown); InnoDB makes it all-or-nothing, so the admin re-checks and retries |
| **Either table MyISAM in production** | **not atomic:** a MyISAM UPDATE is permanent at once, so an audit failure would leave an unaudited change. The dump shows InnoDB, but it is the older Layer-1 snapshot (MySQL 5.7.44), not the live MariaDB. **Read-only verification is a release prerequisite** (§0.7) |

**Not done (need approval):**
- creating `admin_audit_log` in `w2acp_db2025-2`. That would be a same-named duplicate table, which is the **alternative** if the owner prefers Laravel's richer schema;
- any cross-schema grant.

### 0.2 Debug output removed

- **`admincp/main.php`:** both `var_dump()` lines were removed: `$_COOKIE['newadmin']` and the resolved role. They were pre-existing uncommitted debug lines printed on every AdminCP page, including the login form.
- **`admincp/index.php`:** the unreferenced `w2a_debug_log()` was removed. It `print_r()`-appended data to `admincp/_login_debug.log` inside the web root; that file does not exist locally. The stale `ob_start()` comment was corrected; the buffer itself is kept, because it still protects the logout headers.
- **Remaining debug output in the authentication flow:** none.
  - The files scanned with comments stripped: `main.php`, `index.php`, `admin_functions.php`, `w2a__login.php`, both editors, `header.php` and `sidebar.php`.
  - Out of scope: `functions.php` still defines the generic `dumb()` / `dd()` helpers; nothing in AdminCP calls them.
- **Regression checks:**
  - static 26–27;
  - HTTP T68: a `newadmin=<marker>` cookie must not appear, and no var_dump signature or session id may appear, on the login page, the login response, the dashboard or the editor.
  - **T68 was confirmed to fail** when one `var_dump` is put back (negative control).

### 0.3 Backup editor `admincp/backup/edit_author.php`: effectively unused, recommend disabling it

**Why it is effectively unused:**
- Its only inbound link is `admincp/backup/index.php:222`, `edit_author.php?op=liststuff&id=<?php echo $book->id ?>`, where `$book` is a row of `SELECT booking.* … FROM nuke_backup_booking`. So `id` is a **booking id, not an author id**, and the link opens an unrelated admin record or a 404.
- The backup menu (`backup/menu.php`) has no link to it.
- It is a reduced duplicate of `authors/edit_author.php`'s permission form.

**Git status:**
- The whole `admincp/backup/` directory is ignored by a rule **committed in the initial commit** (`.gitignore:124 backup/`), and has never been tracked: `index.php`, `menu.php`, `edit_author.php`, plus `error_log` and `mysql_errors.log`.
- A git-based deploy therefore neither updates nor removes it. Production keeps whatever copy it has, presumably the **unpatched** one (GET write and SQL injection).

**Recommendation (no file deleted, `.gitignore` unchanged):**
1. **Primary:** deny HTTP access to `/admincp/backup/edit_author.php` at the web server, and remove it from production as part of the release. Consequence: the misdirected link in `backup/index.php` gets a 403/404 instead of a wrong admin record. Nothing else is lost; permission editing remains in `authors/edit_author.php`.
2. **Defence in depth, if the file stays:** deliver the patched copy reliably with **`git add -f admincp/backup/edit_author.php`** at commit time.
   - This tracks that single file despite the ignore rule. The rest of `backup/` stays ignored, and future edits to the file are tracked.
   - Without that, the copy must be manual and is easy to miss.
   - **Do not** delete the `backup/` rule: it would start tracking the logs and the whole module.
3. Either way, the release checklist must verify the production file is the patched one, or is gone.

### 0.4 Password compatibility (bcrypt + md5 dual write)

**What is written:** `password = bcrypt(new)` and `pwd = md5(new)`. `plain`, `key` and `API` are untouched.

| Authentication path | Reads | New password | Previous password | Evidence |
|---|---|---|---|---|
| Gen 2 AdminCP login (`admincp/index.php`) | `password` ?: `pwd`; bcrypt, md5, sha1 or plaintext | works | rejected | HTTP T39–T40, T55–T56 |
| Gen 2 legacy md5 admin (empty `password`) | `pwd` (md5) | works after a write (now bcrypt) | rejected | T61 |
| Laravel `AdminGuard` (`LegacyPasswordVerifier`, `AdminUser::currentStoredPassword()` = `password ?: pwd`, no plaintext) | same columns | verifies | rejected | T71–T72 (runs the real Laravel verifier class) |
| OLD pass-the-hash (`old-project/0000000…admin.php:84`, `mainfile.php:1942`, `includes/mainfile.php:352`): `admin` cookie md5 compared to `pwd` | `pwd` | matches md5(new) | old md5 and pre-existing legacy `pwd` no longer match | T73 |
| Rolled-back change | — | does not authenticate | still valid | T70 |

**Logs:** no plaintext, md5 or bcrypt value appears in the query-performance, PHP or server logs after all flows, including failed and rolled-back writes (T67, T74). The audit store holds none either (T64).
- Writes use prepared mysqli statements, so ezSQL's slow-query SQL logging is bypassed.
- The login SELECT contains no secret.

**Legacy compatibility, preserved:**
- md5, sha1 and plaintext logins still work for accounts not yet changed;
- the `admin` / `pwd` md5 cookies that OLD's pass-the-hash path accepts are still issued at Gen 2 login, and they match the new `pwd`.

**Behaviour change to note:** OLD's forum hook `old-project/vb5.php:126` rewrites `pwd = md5(forum password)` on every forum login of an admin.
- For admins with an **empty `password`**, that forum-synced `pwd` was their Gen 2 credential.
- Once P0-A writes `password` (bcrypt), Gen 2 and Laravel prefer it. The forum password then no longer logs that admin into Gen 2 AdminCP, while OLD's `pwd` paths follow the forum password again after their next forum login.
- This is intentional: the editor-set password is authoritative. It does mean `pwd` can diverge from `password`.

**Cross-database caveat (production):** a Gen 2 change updates `w2acp_db2025-2.nuke_authors` only. Laravel authenticates against **`w2acp_laravel`**'s copy, so **if** Laravel admin login is live in production, an old password stays valid there. Check this read-only (§0.7).

**Remaining md5 risk (documented, not fixed: no authentication rewrite):**
1. **Unsalted, fast hash.** A `pwd` dump is cracked quickly offline.
2. **md5 is a credential equivalent.** OLD's pass-the-hash path accepts the md5 itself; the plaintext is not needed.
3. **The browser carries it.** Gen 2 login issues the `pwd` and `admin` cookies containing md5(plain): 30 days, domain `.way2allah.com`, not HttpOnly. Any XSS on the domain yields a pass-the-hash credential for OLD.
4. **`pwd` is overwritten by the forum hook** (above), so it tracks the forum password for admins who log into the forum.
5. **Retirement path (P0-B / B3, not now):** once OLD's pwd-based paths are retired, stop writing `pwd`, clear it, and stop issuing the md5 cookies.

**Also seen:** the production dump contains **`nuke_authors_bk`**, a backup copy of the authors table that presumably includes historical hashes. It is not used for authentication. Review or drop it in production (owner decision).

### 0.5 Session revocation after a password reset *(superseded: implemented in §C.2)*

**The residual risk:**
- The key is `{id, auth_at}`, so changing an admin's password does not end that admin's **other** live AdminCP sessions.
- An attacker who already holds a session keeps it until idle expiry (`session.gc_maxlifetime` 1440 s, which is probabilistic garbage collection) or the absolute 12 h cap.
- Demotion (`radminsuper`) and deletion already take effect on the next request (T62–T63).

**Smallest safe fix (needs approval, because it changes session state):** add one field, a **credential version**, to the key.
- **At `establish()`:** `cv = substr(hash_hmac('sha256', id . '|' . (password ?: pwd), $w2aBridgeSecret ?: 'w2a-admincp'), 0, 32)`.
- **In `w2a_admincp_actor()`:** also select `password` and `pwd` (stripped before returning), recompute `cv`, compare with `hash_equals`, and on mismatch call `clear()` and show the login form. The row is already read on every request, so there is no extra query.
- **After a successful self-change:** recompute `cv` for the current session only, so the admin stays logged in while their other sessions end.
- **No schema change, no new table.** The key holds no credential: it holds a keyed, truncated digest, server-side.
- **Side effects:**
  - any credential change ends sessions, including OLD's forum hook rewriting `pwd` for admins whose `password` is empty, and Laravel's rehash on a shared DB. Both are correct for a credential change;
  - existing sessions without `cv` must log in again once.
- **Tests to add:** a reset ends the target's other session; a self-change keeps the current session but ends the others; a missing or malformed `cv` is rejected.
- **Interim alternative with no new state:** lower `W2A_ADMINCP_MAX_AGE` from 12 h (for example to 2 h). That narrows the window, but doesn't close it.

### 0.6 Tests (after the final-review changes)

| Suite | Result |
|---|---|
| `tests/p0a_admincp_http_tests.php` (disposable DBs, rebuilt; then dropped) | **90 / 90** (74 numbered tests). New: T68 debug output, T69–T70 atomicity, T71–T72 Laravel verifier, T73 OLD md5 path, T74 logs after all flows |
| Negative control (one `var_dump` re-inserted in the scratch copy only) | T68 **fails**, as it should |
| `tests/p0a_admincp_static_checks.php` | **31 / 31**: new 23 (audit to `_islamic_logs` in the same transaction, no `admin_audit_log` insert), 26–27 (debug output), 28 (audit summary markup-free, behavioural) |
| `tests/bridge_checks.php` | **19 / 19** |
| `php -l` (6 edited files and 2 test files) | clean |
| Laravel suite | not run: no Laravel code changed (the verifier was only `require`d by the harness) |

**After the run, shared `olddb` was confirmed unchanged:**
- `admin_audit_log` 57 rows;
- `nuke_islamic_logs` 222,853 rows, 0 of them `gen2_admincp`;
- no `p0a_*` database and no `p0a_test` user remain.

### 0.7 Remaining release blockers *(superseded by §C.7)*

| # | Blocker | Owner / type |
|---|---|---|
| 1 | **Approve the audit store:** `nuke_islamic_logs` in Gen 2's schema (implemented), or approve creating `admin_audit_log` in `w2acp_db2025-2` instead | owner decision |
| 2 | **Read-only production check:** `information_schema.TABLES.ENGINE` for `w2acp_db2025-2.nuke_authors` and `.nuke_islamic_logs` is InnoDB, otherwise audit atomicity does not hold | production verification |
| 3 | **Rotate the 15 default-password accounts** (unchanged from before) | production action |
| 4 | **Backup editor:** deny and remove it in production, or force-add the patched file (§0.3); verify after deploy | owner decision + deploy step |
| 5 | **Session revocation:** approve the `cv` fix (§0.5), or accept the residual risk (optionally with a shorter max age) | owner decision |
| 6 | **Laravel admin login live in production?** If yes, Gen 2 password changes do not reach `w2acp_laravel` (§0.4) | production verification |
| 7 | Confirm production `admincp/main.php` / `admincp/index.php` baseline before overlaying | deploy step |

**Closed by this review:**
- the wrong-database audit target;
- the debug output;
- password-path verification and log hygiene.


---

## 1. Files changed (all in `7amlat`)

| File | Change |
|---|---|
| `admincp/admin_functions.php` | **New P0-A helpers:**<br>• trusted identity: `w2a_admincp_actor()`, `_establish()`, `_clear()`, `_sync_session()`;<br>• authorisation: `w2a_require_admin()`, `w2a_require_superadmin()`;<br>• CSRF: `w2a_csrf_token()`, `_field()`, `_valid()`, `w2a_require_csrf()`;<br>• utilities: `w2a_positive_int()`, `w2a_admincp_verify_password()`, `w2a_admincp_audited_update()`, `w2a_admincp_permission_catalog()`, `w2a_admincp_abort()`, `w2a_admincp_strip_vbuser()`;<br>• the shared editor POST handler `w2a_admincp_handle_author_edit()`. |
| `admincp/main.php` | **Gate:** the role comes only from `w2a_admincp_actor()`, and `$_SESSION['w2a']` is re-derived from the DB row.<br>**Login-POST bypass:** restricted to the root `admincp/index.php`. Its stale comment was corrected. |
| `admincp/index.php` | **Login:** `w2a_admincp_establish()` runs right after the password check; failure means the login fails closed. Login always creates a fresh `$_SESSION['w2a']`, without credential columns and without the forum credential fields.<br>**Dashboard GET:** checks the actor.<br>**Logout:** both paths call `w2a_admincp_clear()`.<br>**`RenameUser()`** removed: unreferenced and SQL-injectable. |
| `admincp/authors/edit_author.php` | `ob_start()` before `main.php`.<br>The shared handler runs before any output and replaces the inline handler.<br>A GET id that is not a valid positive integer, or names no admin, returns 404.<br>Forms are POST with CSRF and `author_id`.<br>The permission save button shows for super-admins only; the password button shows for super-admins or for oneself, and the self-change form asks for the current password. |
| `admincp/backup/edit_author.php` | Same pattern, **permissions only**. The always-true `if (isset($_POST))` write and the injectable `WHERE uid = '<raw POST>'` are gone. **The file is git-ignored (`.gitignore:124 backup/`, since the initial commit): see §11.** |
| `admincp/authors/index.php` | The hard-coded default-password `pwd` line was removed from the disabled admin-creation `INSERT`, which still sits behind the existing `die()`. |
| `tests/p0a_admincp_static_checks.php` *(new)* | 28 numbered static checks (31 assertions after §0); no DB and no network. |
| `tests/p0a_admincp_http_tests.php` *(new)* | Disposable HTTP suite (74 numbered tests, 90 assertions after §0). It refuses to run unless the connected DB is `p0a_main`. |
| *(final review)* `admincp/main.php`, `admincp/index.php`, `admincp/admin_functions.php`, both editors | debug output removed; audit moved to `nuke_islamic_logs` (§0.1, §0.2) |

## 2. D1 — trusted AdminCP authentication

| Requirement | Implementation | Evidence |
|---|---|---|
| Set `$_SESSION['w2a_admincp']` only after a successful password check | `w2a_admincp_establish($Author)` is called in `login()` after `if (!$ok) { … die(); }` | static 1, 9; HTTP T09, T19 |
| Regenerate the session id | `session_regenerate_id(true)` runs **before** the key is written; if it fails, nothing is established and the login form is shown | static 2; HTTP T10, T11 (an attacker-chosen pre-login id is discarded and never holds the key) |
| Store only the admin id and the auth timestamp | `['id' => int, 'auth_at' => time()]`; the CSRF token is reset at login | static 3; HTTP T12 |
| Never copy from `$_SESSION['w2a']`, cookies or forum identity | the actor reader touches neither; the only input is the key | static 4 |
| Public-page functions cannot overwrite it | the only assignment in the tree is in `establish()`; no public code writes dynamic session keys (`$_SESSION[$x] =`, `extract()`, `session_decode()` searched: none) | static 1; HTTP T01–T02, T30–T31 |
| Logout invalidates it | both logout paths (`?op=logout` and `login()`'s `clear`/`logout`) call `w2a_admincp_clear()`, then empty and destroy the session | static 10; HTTP T15–T17 |
| Check identity and DB record on every AdminCP request | `main.php` → `w2a_admincp_actor()` re-reads `nuke_authors` by id (no ezSQL cache is enabled) | static 5–6; HTTP T62, T63 |
| Fail closed | missing, non-array or non-int id or `auth_at`, `auth_at` older than **12 h** (`W2A_ADMINCP_MAX_AGE`) or in the future, or the row gone or with an empty `aid`: the key is cleared and the login form is shown | HTTP T20 (7 cases), T63; control T21 |
| Search all writers of `w2a_admincp` | `7amlat` + `old-project`: **one writer** (`establish`), one remover (`clear`) | static 1 |

**`$_SESSION['w2a']` is no longer an authorisation source.**
- Public pages still rewrite it through `w2a_header()` → `UserType()` → `VBUserType()`. That was demonstrated at runtime (T01) and **left unchanged** (D3, not approved).
- On every AdminCP request `w2a_admincp_sync_session()` overwrites `->admin`, `->usertype`, `->vbuser` and `$_SESSION['uid']` from the trusted row, so the sidebar and menus never show a poisoned identity (T32).
- Two AdminCP pages call `w2a_header()` mid-request: `chat/edit_author.php` and `questionnaire/index.php`. They can alter that display copy for the rest of their own request. They can never alter the actor (T30).

## 3. D2 — centralised authorisation

- **`w2a_require_admin()`:** an actor exists, otherwise 403.
- **`w2a_require_superadmin()`:** the actor's **current** `radminsuper = 1`, re-read from the DB. Otherwise 403 with the buffered output discarded.

**Editor policy:**

| Action | Allowed for | Other checks |
|---|---|---|
| Edit permissions (any target, **including oneself**) | super-admin only | no self-elevation; `radminsuper` is never writable here |
| Reset **another** admin's password | super-admin only (may reset another super-admin) | — |
| Change **own** password | any admin | `current_password` is verified with the same formats as login: bcrypt in `password`, else `pwd` as md5, sha1 or legacy plaintext; comparison with `hash_equals` |

**All checks run before any write and before any output:**
- POST only;
- the actor must exist;
- the CSRF token must be valid;
- the target `author_id` must match `^[1-9][0-9]{0,9}$` and exist, otherwise 404.

**Permission blob:**
- rebuilt from a **whitelist**: the `$authorization[...]` keys declared by each `admincp/*/menu.php`;
- stored only as `'on'`;
- unknown modules and keys are dropped.

That matches what the form can submit, so the workflow is unchanged.

**Revocation:**
- a super-admin demoted in the DB loses rights on the next request (T62);
- a deleted admin is logged out on the next request (T63).

## 4. Password compatibility

**A new password writes two columns:**
- **`password = password_hash(new, PASSWORD_BCRYPT)`.** Gen 2 login prefers `password` when it is non-empty (`$2y$`), and so does Laravel `AdminGuard`.
- **`pwd = md5(new)`.** This is what this editor always wrote. It is kept so the **previous** credential does not stay alive on the `pwd`-based paths: the Gen 2 fallback when `password` is empty, the legacy md5 cookies, and OLD's pass-the-hash `admin.php` variant. `pwd varchar(40)` fits.

**Columns not touched:**
- `plain` is not written. OLD's `admincp.php:53` stores plaintext there at login; that is a gap (§10).
- `key` and `API` are not written.

**Password policy:**
- 8–72 bytes (the bcrypt limit);
- no trimming, matching login.

**Tested:**
- bcrypt login with `password` set (T09);
- md5 `pwd` login with an empty `password` (T61);
- the new password works and the old one fails, both for self-change (T39–T40) and for a reset (T55–T56);
- `pwd` equals md5(new) (T37).

## 5. CSRF and GET

- **CSRF token:** one per session, 64 hex characters, from `random_bytes(32)`. It is regenerated at login and checked with `hash_equals`. A non-string token (an array) is rejected.
- **CSRF failures:**
  - no token: 403 (T42);
  - wrong token: 403 (T43);
  - a token from another session: 403 (T44);
  - an array token: 403 (T45).
- **GET never writes,** including the backup editor, which used to write on every GET (T46).

## 6. SQL injection

- **Backup editor:**
  - **pre-fix, demonstrated:** a single POST with `uid=0' OR '1'='1` rewrote **every** `nuke_authors.permissions` row (pre-fix run, T48);
  - **post-fix:** the uid is ignored, the target is the validated `author_id`, and only that row changes (T48–T49).
- **Both editors write through parameterised mysqli statements** on `$w2adb`'s connection.
  - Malformed ids such as `1004 OR 1=1`, `1004' OR '1'='1`, `1e3` or `" 1004"` return 404 (T47).
  - Quote-bearing permission keys are dropped by the whitelist.
- **Why not ezSQL `query()`:** it writes the full SQL text of slow (over 50 ms) queries to `logs/query-performance/`. A password UPDATE would have put hashes on disk. The prepared statements bypass that logger (T67).

## 7. Audit *(superseded by §0.1: the store is now `nuke_islamic_logs`)*

`admin_audit_log` (the Laravel table, on the same `main`/`olddb` database):
- **Module and entity:** `module = gen2_admincp`, `entity_type = admin`, `entity_id` = the target id.
- **Actor and IP:** `actor_admin_id` and `actor_aid` come from the trusted actor; `ip` is `REMOTE_ADDR`.
- **Actions:** `admin_permissions_update` (metadata: `target_aid`, `via`, granted module keys), `admin_password_reset`, `admin_password_self_change` (metadata: `target_aid`, `via`).
- **Never stored:** passwords, md5s, bcrypt hashes, tokens or cookies (T64).
- **Atomicity:** the UPDATE and the audit INSERT run in **one InnoDB transaction**. If the audit insert fails, the change is rolled back and the user sees a 500. The editor therefore **fails closed if `admin_audit_log` is missing** (§11).

## 8. Gate bypass found and fixed

- **The bug:** `main.php`'s login-POST exemption tested `basename($_SERVER['SCRIPT_NAME']) === 'index.php'`. Every `admincp/<module>/index.php` matched, so an unauthenticated POST with `op=login&aid=x&pwd=y` skipped the gate.
- **Pre-fix proof:** this was demonstrated on `khotab/index.php` and `authors/index.php`.
- **The fix:** it now requires `realpath(SCRIPT_FILENAME) === realpath(admincp/index.php)` and a POST request (T07–T08).

## 9. Tests and results

### 9.1 Harness (disposable only)

- **Docroot copy:** a PHP-only copy of `7amlat`, 1,161 files, placed in the session scratchpad. In the copy only, `w2a_config.php`'s credential values were blanked and a test include pointed `$w2adb`, `$vbdb` and `$chatdb` at `127.0.0.1:3307`.
- **Disposable databases:**
  - `p0a_main`, `p0a_vb` and `p0a_chat`, created on local `mysql57` from a **schema-only** dump;
  - a dedicated user `p0a_test` granted **only** on `p0a_*`;
  - synthetic accounts: super-admin `1001` (bcrypt, forum uid 16715, a hard-coded `$SuperAdmins` id), super-admin `1002` (md5 `pwd`, empty `password`), and admins `1003` and `1004`;
  - test passwords were random per run and never printed.
- **Server:** `php -S 127.0.0.1` with:
  - `short_open_tag=1`, which Gen 2 needs (for example `functions.php:530`);
  - `allow_url_fopen=0`;
  - all proxies pointed at a closed port;
  - a private `session.save_path`.
- **Cleanup:**
  - the databases and the user were **dropped** afterwards, and the docroot copies deleted;
  - shared `olddb` was only read (schema dump, plus row counts of `nuke_authors.plain`);
  - `olddb.admin_audit_log` still has 57 rows.
- **Incident during setup, no impact:** one re-sync briefly restored the real `w2a_config.php` into the copy because of an rsync filter-order mistake. Its connections targeted the hostname `mysql57`, which does not resolve on the host, so **no connection was made**. The copy was re-scrubbed and the filter fixed.

### 9.2 Results

| Suite | Result |
|---|---|
| `tests/p0a_admincp_http_tests.php`, fixed code | **83 / 83 assertions passed** (final run, fresh reset) |
| Same suite against the **pre-fix** files (backups) | **23 passed, 60 failed**: it detects every vulnerability (below) |
| `tests/p0a_admincp_static_checks.php` | **28 / 28** |
| Existing `tests/bridge_checks.php` | **19 / 19** (login form, `w2a_user` bridge cookie unchanged) |
| `php -l` on the 6 edited files and 2 test files | clean |
| Laravel suite | not run: no Laravel code changed |

**Runtime confirmations from the pre-fix run** (before, this was static reading only):
- **T03–T06, session poisoning:** a forged `admin_from_vb=16715` cookie plus one visit to `pages/privacy.php` gave **dashboard access** and opened the editors.
- **T07–T08:** the module-index gate bypass.
- **T10:** no session-id change at login.
- **T24–T29:** an ordinary admin can reset another admin's or a super-admin's password and edit permissions.
- **T42–T46:** no CSRF check; GET writes.
- **T48:** the SQL injection rewrote all rows.

**Test coverage map** (approval list → test ids):

| Approval item | Tests |
|---|---|
| forged admin / super-admin cookies + public page cannot create AdminCP auth | T01–T06, T18 |
| `w2a_header()` cannot change the identity | T30–T32 |
| session id changes at login | T10–T11 |
| logout removes the key | T15–T17 |
| missing / invalid key rejected | T20 (7 cases), T21 control |
| revoked privileges not retained | T62, T63 |
| ordinary admin vs another admin / a super-admin | T24–T29 |
| GET and invalid CSRF cannot write | T42–T46 |
| SQL injection | T47–T49 |
| audit present and free of credentials | T54, T64–T66 |
| logs free of credentials | T67 |
| workflows preserved | T09, T14, T22, T39, T41, T51–T61 |

## 10. Entry points, bypasses and remaining gaps

**AdminCP entry-point inventory:**
- Of 57 `admincp/**/*.php` files, excluding `forumConfig/`, **all page scripts include `../main.php` first**. `admincp/index.php` includes it after `ob_start()`.
- **The files that do not include it are not admin actions:**
  - the 11 `menu.php` files (array declarations);
  - `header.php`, `footer.php`, `sidebar.php`, `navigation_menu.php` and `admin_functions.php` (function or template includes);
  - `w2a__login.php` (the login form);
  - `w2a_vb_login.php` and `w2a_vb_login_VB4.php` (they don't write the session);
  - `w2a__lock.php` (fatals on direct access).
- **Direct-access side effect:** `admincp/home.php` returns HTTP 500 when requested directly. This is pre-existing (an undefined `w2a_admin_header()`), and it does not render admin content.

**Not covered by the gate (known, out of P0-A scope):**
1. **Root `backup.php`:** the backup-client API, authenticated by `uid` and `APIKey` against `nuke_authors.API` (B3 review). It is independent of AdminCP sessions.
2. **`admincp/forumConfig/`:** about 712 PHP files (a vBulletin package tree), not tracked by git. It contains a copy of `w2a_config.php` under `…/guzzlehttp/psr7/src/` that **also contains the `VBUserType`-style session writer**. Determine whether it is deployed; if so, deny it at the web server.
3. **`english/`:** PHP-Nuke on `mysql_*`, likely dead. Deny it at the web server.

**Remaining gaps, ordered:**
1. **The 15 default-password accounts:** rotation is still urgent. Removing the literal from source does **not** change those stored passwords. Production action, no code.
2. **A reset does not end the target's other live sessions.** By instruction, the key stores only id and `auth_at`, so a target already logged in elsewhere keeps that session until idle expiry (`gc_maxlifetime` 1440 s) or the 12 h cap.
   - Admin **removal** or **demotion** takes effect immediately (T62, T63).
   - Closing the gap would need a credential fingerprint in the key, which is outside "id + timestamp only" and needs approval.
3. **Session poisoning of `$_SESSION['w2a']` itself still happens on public pages (D3).** It no longer grants AdminCP access. Any public-page feature that trusts `$_SESSION['w2a']->usertype`, such as the inline admin links in `functions.php` breadcrumbs, is still cookie-controlled (V4).
4. **No per-module authorisation in Gen 2:** any admin can use any module page. Only the editors are role-checked.
5. **OLD stores plaintext at login** (`old-project/admincp.php:53` → `nuke_authors.plain`). Locally all 48 rows are empty; check production read-only. OLD was not modified.
6. **No `SameSite`, `Secure` or `HttpOnly` on the PHP session cookie;** `session.use_strict_mode` is off. Regeneration closes fixation at login, but enabling strict mode and HttpOnly in `.htaccess` is advisable (P0-B).
7. ~~**`main.php` still has `var_dump()` debug output**~~ **Fixed in §0.2.** (pre-existing uncommitted lines 10 and ~60). It leaks the role and the `newadmin` cookie into every AdminCP page. Remove it before deployment.
8. **Login has no rate limit or lockout.**
9. Pre-existing PHP 8 warnings in the editors' permission-checkbox loop (undefined keys) are harmless.

## 11. Production deployment prerequisites *(see §0.7 for the current list)*

1. **Ship all six files together,** with D1 and the editors in the same release.
   - `admincp/backup/edit_author.php` is **git-ignored**, so a git-based deploy will **not** carry it. Copy it manually, or **delete** that file in production, since nothing links to it except `backup/index.php` (a booking list).
   - Confirm production's `admincp/main.php` and `admincp/index.php` match this working copy, including the earlier uncommitted session-only hardening.
2. **`admin_audit_log` must exist** in the production Gen 2 database, otherwise every editor save fails closed with a 500. It is a Laravel migration (needs approval).
3. **Rotate the 15 default-password accounts first** (or at the same time).
4. **Remove the `var_dump()` debug lines** from `admincp/main.php`.
5. **Expect every admin to log in again once:** old sessions lack the key.
6. **Verify read-only in production:**
   - that public Gen 2 pages share the session with `/admincp/` (same host and save path);
   - whether `admincp/forumConfig/` and `english/` are deployed;
   - `nuke_authors.plain` non-empty counts.
7. **The runtime needs** `random_bytes` / `hash_equals` (PHP 7+, already on `ea-php72`), mysqli `begin_transaction` and InnoDB `nuke_authors` (confirmed locally).
8. **Smoke test after deploy:**
   - log in as a super-admin, then log out;
   - edit an admin's permissions, then check the audit row;
   - an ordinary admin changes their own password.

## 12. Rollback

- Restore the six pre-edit files; backups are kept in the session scratchpad under `p0a-backup/`.
- Rollback **re-opens session poisoning, the editor takeover and the SQL injection**, so it is not recommended.
- If AdminCP misbehaves, deny `/admincp/` at the web server instead.
- There is no data migration to undo. Audit rows are additive.

## Decision needed

- **For deployment:** the §0.7 release blockers. (The earlier request, to create `admin_audit_log` in production Gen 2's database, is withdrawn in favour of `nuke_islamic_logs`, unless the owner prefers it.)
- **Optional follow-ups:**
  - a credential fingerprint in the key, so a password reset ends the target's other sessions;
  - D3: stop `VBUserType()` writing admin identity from cookies;
  - P0-B session-cookie hardening.

**STOPPED after the P0-A final review as instructed.**

---

## C. Final closure (2026-11-16)

Request: "Security P0-A — Final Closure".
- **Restrictions kept:** local only; no production access; no Way2Allah host request; no shared-database write; no migration; OLD unchanged; nothing committed, pushed or deployed.
- **Pre-existing uncommitted work:** preserved; `7amlat` status count unchanged at 36, OLD at 42.

### C.1 Audit logging: `nuke_islamic_logs`, kept (no new table)

| Required | Where | Example |
|---|---|---|
| trusted actor id | `extrainfo` `by admin#<id>` (from `w2a_admincp_actor()`), plus actor `aid` in `aid` | `by admin#1001` |
| target admin id | `target_id` and `extrainfo` `admin#<id>` | `1004` |
| action | `action` | `admin_permissions_update` / `admin_password_reset` / `admin_password_self_change` |
| timestamp | `mytime` | unix time |
| safe change summary | `extrainfo` | `[gen2-admincp] admin_permissions_update admin#1004 by admin#1001 via=authors/edit_author.php added=authors:addstuff removed=-` |

- **Changed in this closure:** permission edits now record **only the difference** (`added=` / `removed=` `module:key` pairs). Previously the record carried the full resulting set. Neither the stored blob nor unchanged keys are recorded (T52b).
- **Never recorded:** passwords, hashes, API keys, session tokens, cookies or the session secret (T64, T79–T81).
- **Transactional behaviour unchanged:** one connection, one transaction, rolled back if the audit insert fails (T69–T70).
- **Owner-operated release prerequisite:** production InnoDB verification (§C.5).

### C.2 Credential-bound AdminCP session: implemented

| Element | Implementation (`admincp/admin_functions.php`) |
|---|---|
| Key | `$_SESSION['w2a_admincp'] = ['id', 'auth_at', 'cv']`; still the only writer is `w2a_admincp_establish()` |
| Digest | `cv = HMAC-SHA256(secret, "w2a-admincp-session|v1|" . id . "|" . (password ?: pwd))`: the field login verifies. The key never holds the hash itself (T12b) |
| Secret | **dedicated** `W2A_ADMINCP_SESSION_SECRET`, read from the environment (`getenv`, or `$_SERVER` for `SetEnv`), else an untracked `w2a_admincp_secret.php` in the docroot that `return`s it. Valid only if it is **at least 32 bytes** and **differs from `W2A_BRIDGE_SECRET`** (the bridge secret is shared with OLD) |
| Validation | **every** AdminCP request: `w2a_admincp_actor()` re-reads `nuke_authors` (one query, including `pwd` and `password`, stripped immediately), recomputes the digest and compares it with **`hash_equals()`**. A malformed `cv` is rejected: it must be 64 lowercase hex characters |
| Fail closed | **No valid secret:**<br>• login returns **503** ("AdminCP not configured") and creates no key;<br>• every existing session is rejected and its key cleared (T75–T76, for a missing secret, one under 32 bytes, and one equal to the bridge secret).<br>**Rotating the secret** ends all sessions (T77); fresh logins work (T78) |
| Preserved | 12 h absolute lifetime (T20 expired; T21 at 11 h 56 min accepted), `session_regenerate_id(true)` at login (T10–T11), DB-backed identity and role, demotion and deletion on the next request (T62–T63), forged cookies and poisoning rejected (T01–T08, T30–T32) |
| Reset by another admin | the target's stored credential changes, so **all** of the target's existing AdminCP sessions are rejected on their next request (T53c, two live sessions). The resetting super-admin is unaffected (T53d). A **permission** edit alone does not end sessions (T52c) |
| Self change | after the committed update, **only the current request's session** is re-bound to the new digest (T36b); the admin's other sessions are rejected (T36c) |
| Migration | sessions created before this change have no `cv` and are rejected once, so every admin logs in again (T20 "missing cv") |

**What it does not do (not claimed):**
- It ends **Gen 2 AdminCP sessions only.** It does not revoke OLD sessions, the OLD bridge cookie (`w2a_user`), the legacy `admin` / `pwd` md5 cookies, forum sessions, or Laravel admin sessions.
- It does not touch `$_SESSION['w2a']` on public pages.

**Side effects to expect:** any change to the stored credential ends that admin's AdminCP sessions. That includes OLD's forum hook (`old-project/vb5.php:126`, which rewrites `pwd`) for admins whose `password` is empty, and, on a shared DB, Laravel's login rehash. Both are credential changes, so ending the sessions is correct.

### C.3 Backup editor: web-server denial (prepared, not applied)

The production file is **not deleted**, and the patched local copy is preserved.

**Recommended rule.** Create `admincp/backup/.htaccess` in production. The directory is git-ignored, so place it manually, exactly as follows:

```apache
# SECURITY P0-A: deny the unused backup author editor (patched copy kept on disk)
<Files "edit_author.php">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</Files>
```

**Why this form:**
- it is bound to the file on disk, so URL tricks don't matter;
- it needs no ordering inside the 400-line root `.htaccess`;
- it works on Apache 2.2 and 2.4;
- `<Files>` with `Require` / `Deny` is supported by LiteSpeed (the root `.htaccess` has an `lsapi_module` block).

**Alternative (tracked file), at the top of the root `.htaccess` after `RewriteEngine on`:**

```apache
RewriteRule ^admincp/+backup/+edit_author\.php - [F,NC]
```

- It only works while no `.htaccess` in `admincp/` or `admincp/backup/` enables its own `RewriteEngine` (there is none today).
- A server-level equivalent is `<LocationMatch "(?i)^/+admincp/+backup/+edit_author\.php">Require all denied</LocationMatch>`.
- A `<If "%{REQUEST_URI} …">` form also worked on Apache, but is **not** recommended: LiteSpeed's `<If>` support is uncertain.

**Local proof:**
- Apache 2.4.58 on 127.0.0.1, `AllowOverride All`, `AcceptPathInfo On`, a dummy docroot.
- **Baseline:** the editor was reachable (200) in 9 of 10 variants.
- **With the recommended rule** (the root `RewriteRule` and `<If>` forms gave the same results):

  | Request | Result |
  |---|---|
  | GET, `?op=liststuff&id=1`, POST | 403 |
  | `//admincp//backup//edit_author.php` | 403 |
  | `/admincp/backup/./edit_author.php`, `/admincp/x/../backup/edit_author.php` | 403 |
  | `edit%5fauthor.php`, `%62ackup` | 403 |
  | PATH_INFO `/edit_author.php/x` | 403 |
  | upper-case path | 404 (case-sensitive filesystem) |
  | `/admincp/backup/index.php`, `/admincp/authors/edit_author.php` | **200**, unaffected |

**Owner verification after placing the rule** (from any machine; replace `<gen2-host>` with the Gen 2 AdminCP host):

```bash
for u in "/admincp/backup/edit_author.php" "/admincp/backup/edit_author.php?op=liststuff&id=1" \
         "//admincp//backup//edit_author.php" "/admincp/backup/./edit_author.php" \
         "/admincp/backup/edit%5fauthor.php" "/admincp/backup/edit_author.php/x"; do
  printf '%-55s %s\n' "$u" "$(curl -s --path-as-is -o /dev/null -w '%{http_code}' "https://<gen2-host>$u")"
done
curl -s -o /dev/null -w 'POST %{http_code}\n' -X POST -d 'author_id=1' "https://<gen2-host>/admincp/backup/edit_author.php"
# every line above must be 403; controls must NOT be 403:
curl -s -o /dev/null -w 'backup/index %{http_code}\n'  "https://<gen2-host>/admincp/backup/index.php"
curl -s -o /dev/null -w 'authors/edit %{http_code}\n'  "https://<gen2-host>/admincp/authors/edit_author.php?id=1"
```

Also confirm that the server error log shows `AH01630: client denied by server configuration` (or LiteSpeed's equivalent) for those requests.

### C.4 Password compatibility (unchanged, documented)

- **The dual write is preserved:** `password = bcrypt(new)` and `pwd = md5(new)`. `plain`, `key` and `API` are untouched. No scope expansion.
- **Verified paths** are those in §0.4: Gen 2 login, Laravel's verifier and OLD's md5 paths. Each was tested with the new password working, the previous one failing, and a rolled-back change failing.
- **Remaining md5 risk:**
  - unsalted and fast;
  - a credential equivalent for OLD's pass-the-hash path;
  - carried in the 30-day, non-HttpOnly `admin` / `pwd` cookies.
- **OLD forum-login risk:** `old-project/vb5.php:126` rewrites `pwd = md5(forum password)` on every forum login of an admin.
  - OLD's `pwd`-based paths therefore follow the forum password, not the AdminCP password.
  - For admins with an empty `password`, it also changes their Gen 2 credential and ends their AdminCP sessions (§C.2).
  - After any P0-A write, `password` is set, and Gen 2 and Laravel ignore `pwd`.
- **New finding during closure (pre-existing; not changed, because it is D3 and not approved):** `VBUserType()` (`w2a_config.php`) stores the full `nuke_authors` row (`SELECT *`) in `$_SESSION['w2a']->admin` whenever a page sees an `admin_from_vb` or `bbuserid` cookie. That row includes `password`, `pwd`, `key` and `API`.
  - Session files are server-side, so this is credential-at-rest exposure in the session store; it is not reachable from a browser.
  - An AdminCP request re-syncs that field without credentials, but the next public page rewrites it.
  - **Smallest fix (needs approval):** select only non-credential columns in `VBUserType()`.
  - The P0-A tests assert that the **trusted key**, the logs and the audit rows are free of hashes (T81), and that the secret appears in no session file at all (T80).

### C.5 Production release checklist (read-only, owner-operated; nothing here was executed)

| # | Check / action | How | Pass criterion |
|---|---|---|---|
| 1 | **Gen 2 auth file versions** | On the server, back up and fingerprint the current files: `sha256sum admincp/{main,index,admin_functions}.php admincp/authors/{edit_author,index}.php admincp/backup/edit_author.php`. Diff them against this working copy before overlaying | the production baseline is known and backed up. Local SHA-256 prefixes: `admin_functions.php 7d52bc260e21093a`, `main.php 8fa85a0a2ed5b5d9`, `index.php 23312a94225e32bc`, `authors/edit_author.php 3aecedf5f9d893de`, `backup/edit_author.php e24671c580bcec4d`, `authors/index.php ab2b0deaccf279eb` |
| 2 | **Is Laravel admin login live?** | Check the production Laravel deployment for exposed admin routes and recent admin activity, e.g. `SELECT FROM_UNIXTIME(MAX(created_at)) FROM w2acp_laravel.admin_audit_log` (if the table exists) and the web-server routing for the Laravel admin path | if live: Gen 2 password changes **do not** reach `w2acp_laravel.nuke_authors`, so old passwords still work there. Rotate there too, or accept the risk |
| 3 | **InnoDB** | `SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'w2acp_db2025-2' AND TABLE_NAME IN ('nuke_authors','nuke_islamic_logs');` | both `InnoDB`. Otherwise audit atomicity is not guaranteed: stop |
| 4 | **Rotate the 15 default-password accounts** (ids 9602, 9625, 9628, 9631, 9633, 9635–9642, 9645, 9646) | After deploy, a super-admin resets each one in `authors/edit_author.php`. This is audited and ends that account's AdminCP sessions. Deactivate accounts nobody uses (B3 is a separate phase). Confirm with an owner-run query comparing `pwd` to the md5 of the known default, typed by the owner and never stored in docs | 0 accounts still match the default |
| 5 | **Backup editor denied** | place the §C.3 `.htaccess`, then run the §C.3 curl commands | all 403; controls not 403 |
| 6 | **Session HMAC secret** | `openssl rand -hex 32`; set `W2A_ADMINCP_SESSION_SECRET` server-side (cPanel / Apache `SetEnv` / PHP-FPM `env[]`), or an untracked `w2a_admincp_secret.php` returning it with file mode 0600 or 0640. **Never commit it** (it is not covered by `.gitignore`). It must differ from `W2A_BRIDGE_SECRET` | AdminCP login succeeds (a 503 means the secret is missing or invalid) |
| 7 | **Session behaviour after deploy** | expect every admin to log in once (pre-`cv` sessions are rejected). Smoke test: log in with two browsers as an admin; a super-admin resets that admin; both browsers must be logged out. A self-change in browser A keeps A and logs out B. Then check `SELECT … FROM nuke_islamic_logs WHERE module = 'gen2_admincp' ORDER BY id DESC LIMIT 5` | as described; audit rows present; no secrets in them |
| 8 | **`admin_audit_log`** | not needed for Gen 2 (audit goes to `nuke_islamic_logs`) | — |
| 9 | **Rollback prerequisites** | keep the step-1 backups of the six production files. The secret and the `.htaccess` may stay; audit rows are additive (`module = gen2_admincp`). Rollback **re-opens** session poisoning, the editor takeover and the SQL injection, so prefer denying `/admincp/` at the web server if AdminCP misbehaves | backups present before overlay |
| 10 | **Before overlay** | confirm production's `admincp/main.php` / `index.php` contain no other local changes that would be lost | diff reviewed |

### C.6 Regression results (final, disposable databases only)

**Harness:**
- a PHP-only docroot copy with credentials blanked;
- `p0a_main`, `p0a_vb` and `p0a_chat` on local `mysql57`, with a restricted `p0a_test` user;
- five isolated `php -S 127.0.0.1` servers sharing one session store, with the AdminCP secret set to: a valid random value, missing, too short, equal to the bridge secret, and a different (rotated) valid value;
- outbound HTTP blocked.

Everything was dropped or deleted afterwards. Shared `olddb` is unchanged: `admin_audit_log` 57 rows, `nuke_islamic_logs` 222,853 rows, 0 of them `gen2_admincp`.

| Suite | Result |
|---|---|
| `tests/p0a_admincp_http_tests.php` | **116 / 116** assertions |
| `tests/p0a_admincp_static_checks.php` | **38 / 38** |
| `tests/bridge_checks.php` | **19 / 19** |
| `php -l` (6 AdminCP files and 2 test files) | clean |
| Local Apache deny-rule matrix (§C.3) | all bypass variants 403, controls 200 |
| Laravel suite | not run: no Laravel code changed |

**Requested coverage:**

| Requested | Tests |
|---|---|
| login creates the credential-bound session | T09, T12 |
| the key never holds the hash | T12b |
| session id regeneration | T10, T11 |
| invalid or missing secret | T75–T76 (missing, under 32 bytes, equal to bridge); rotation T77–T78 |
| tampered digest | T20: tampered, missing, non-canonical, another admin's, wrong-secret, array |
| reset invalidates previous sessions | T50b → T53c (two sessions), T53d |
| self change preserves only the current session | T35b, T36b, T36c |
| deleted or demoted admin | T62, T63 |
| expired session | T20 (13 h), T21 (11 h 56 min accepted) |
| forged cookies and poisoning | T01–T08, T18, T30–T32 |
| authorisation | T24–T29, T41–T50 |
| editor functionality | T39, T51, T55, T59–T61 |
| audit | T52b, T54, T64–T66, T69–T70, T79 |
| no leakage | T13, T64, T67, T74, T80, T81 |
| debug output | T68 |

### C.7 Remaining production release blockers (owner-operated)

1. **Secret provisioning** (§C.5-6). Without it AdminCP refuses all logins (503).
2. **InnoDB verification** for `nuke_authors` and `nuke_islamic_logs` (§C.5-3).
3. **Rotate the 15 default-password accounts** (§C.5-4).
4. **Backup editor `.htaccess` deny**, verified with curl (§C.3).
5. **Laravel admin login status** in production and the cross-database password divergence (§C.5-2).
6. **Production file baseline backup and diff** before overlay (§C.5-1, -10).

**Not blockers, but approval-gated follow-ups:**
- `VBUserType()` stores full author rows in public-page sessions (§C.4);
- md5 retirement after OLD (P0-B / B3);
- per-module authorisation;
- `admincp/forumConfig/` and `english/` exposure;
- `nuke_authors_bk` in production.

**STOPPED after P0-A as instructed.** P0-B and B3 not started.
