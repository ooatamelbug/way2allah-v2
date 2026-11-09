# Admin Cross-Subdomain Auth Regression — Investigation

**Symptom.** Admins log into the enhanced legacy AdminCP (`nuke.way2allah.com`) and can no longer navigate into `old.way2allah.com` administration while remaining recognised as an administrator.

**Status.** Investigation only. No code, cookie, session, configuration, schema or database changed in any project. No commit. Advfiles Pass 3A remains stopped.

**Headline.** The regression is **not provable from source and git history**, and I am not going to assert a cause I cannot evidence. All three sets of recent changes were examined in full; none of them explains the symptom, and the one mechanism I suspected was empirically disproved. §5 classifies the cause as `UNPROVEN — ENVIRONMENTAL OR BROWSER-STATE SUSPECTED`, ranks the surviving candidates, and §10 gives the single browser check that discriminates between them. §7's recommended fix is deliberately conditional on that check.

> **SUPERSEDED IN PART — see §11 and §12.** Browser evidence from a fresh login disproved candidate C-2 and narrowed the cause to the cookie *value* (§11). Response-header evidence then disproved the encoding hypothesis and narrowed it further to **browser rejection on size** (§12). §5's ranking, §10's check and §11.5–§11.6 are superseded by §12; §1–§9 stand, except that §7's Option B gains a second, independent justification (payload size, not only signing).

---

## 1. Historical working mechanism (proven, Pass 1)

The bridge is the **`w2a_user` cookie**, not the vBulletin session.

```
Admin logs into nuke.way2allah.com/admincp  (credentials checked against nuke_authors)
  └─ admincp/index.php:216-224 writes 7 cookies, all Domain=.way2allah.com Path=/ 30 days
       w2a_user = json_encode($Author)      ← the entire nuke_authors row, unsigned
  ▼
Admin follows a link to old.way2allah.com   ($inlineAdminUrl, w2a_config.php:63)
  └─ old-project/mainfile.php:377 is_w2a_admin($ignored)
       ├─ reads $_COOKIE['w2a_user'] → json_decode
       ├─ $_SESSION['w2a']->admin    = (object) $Author
       ├─ $_SESSION['w2a']->usertype = cookie's radminsuper ? 'superadmin' : 'admin'
       ├─ $_SESSION['uid']           = (int) $Author['uid']
       ├─ SELECT * FROM nuke_authors WHERE uid='$aid'      (existence check only)
       └─ returns 1 if that uid exists AND usertype ∈ {superadmin, admin}
```

Two properties matter for this investigation:

- **The PHP session is not the bridge.** Neither application calls `session_set_cookie_params()` or sets `session.cookie_domain` (verified by search across both trees, excluding the bundled vBulletin fork), so each host has its own host-scoped `PHPSESSID`. `old-project` rebuilds `$_SESSION['w2a']` from the cookie on **every** request, so session storage differences cannot break recognition.
- **The two applications never shared session storage.** `7amlat/.user.ini:14` → `/var/cpanel/php/sessions/ea-php72`; `old-project/.user.ini` formerly → `/tmp`. Independent confirmation that the cookie is the only channel.

**Naming collision, carried forward from Pass 1 so it is not mistaken here:** `$w2a_user` the *PHP variable* (`old-project/admin.php:16`, `header.php:16`) is the **vBulletin forum user array** returned by `VB5()`. `$_COOKIE['w2a_user']` is the **admin identity JSON**. Same name, different trust levels, both in scope in `admin.php`. Nothing in this report conflates them.

---

## 2. Recent changes — complete inventory

Neither repository has a commit for any of this work: `7amlat`'s HEAD is `dc6a7a8` (2026-07-27) and every login/logout change is an **uncommitted working-tree modification**. So `git log` shows nothing; `git diff` against HEAD is the only history available, and it is complete.

### 2.1 `7amlat/admincp/index.php` — 3 hunks

| Hunk | Change | Bearing on the bridge |
|---|---|---|
| top of file | `$w2aLogoutObLevel = ob_get_level(); ob_start();` added before `include_once('main.php')` | See §5 candidate C-2 |
| `case "logout"` | bounded `ob_end_clean()` loop added, floored at `$w2aLogoutObLevel` | Makes logout's headers actually send — see §5 candidate C-2 |
| logout tail | `header('Location: ' . $adminurl . 'index.php')` → `header('Location: /admincp/index.php')` | None. `$adminurl` = `https://way2allah.com/admincp/` (`w2a_config.php:61-62`) — the Laravel host. Root-relative keeps the admin on `nuke.way2allah.com`. |

**`setcookie("w2a_user", …)` is NOT in the diff.** The producer block is byte-identical to HEAD.

### 2.2 `7amlat/admincp/main.php` — 1 hunk

`$MyUser = UserType($_COOKIE['newadmin'])` → session-only derivation from `$_SESSION['w2a']->usertype`. This changed **AdminCP's own authorization gate** (Option D). It does not touch cookie production, and `old-project` does not consult `$MyUser`.

### 2.3 `7amlat/w2a_config.php` — 1 hunk

`$page=$_GET['page']` → `$page=intval($_GET['page'])`. A sanitisation fix. Nothing auth- or cookie-related.

### 2.4 `7amlat/functions.php`

One hunk, inside `add_to_radio_playlist()`. Unrelated.

### 2.5 `old-project/mainfile.php` — 2 hunks, 2 lines

```diff
-  if ($author['uid'] == $aid && !empty($author['uid']) && $_SESSION['w2a']->usertype == 'superadmin') {
+  if ($author['uid'] == $aid && !empty($author['uid']) && in_array($_SESSION['w2a']->usertype,['superadmin','admin'])) {
```
plus one added blank line.

**This change WIDENS access** — previously only `superadmin` passed, now `admin` passes too. It would make *more* admins work, never fewer. It cannot be the regression.

### 2.6 `old-project/admin.php`

```diff
-  $aid = $_SESSION['uid'] ?? '';
+  $aid = !empty($_SESSION['w2a']) ? str_replace('+',' ',$_SESSION['w2a']->admin->name) : '';
```

`$aid` changed from a numeric uid to the admin's display name. `admin.php:41` then guards with `preg_match("/[^Á-ía-zA-Z0-9[[:space:]]_-]/", trim($aid))` → `die("Begone")`.

**I suspected this was the regression and tested it rather than asserting it. It is not.** Executed directly:

| `$aid` value | `preg_match` | Result |
|---|---|---|
| `16715` (old form) | 0 | passes |
| `56540` (old form) | 0 | passes |
| `مؤمن يحب الله ورسوله` (new form, uid 16715) | 0 | **passes** |
| `أبوسلمى المصري` (new form, uid 56540) | 0 | **passes** |

My byte-range reasoning predicted Arabic would trigger `die("Begone")`; the empirical result contradicts it. Recording the disproof because it was the single most promising source-level candidate.

### 2.7 `old-project/.htaccess` — 8 lines

Rewrite retargeting only: `new_modules.php` → `modules.php` / `modules/Cat/index.php` for Cat and Fatwa routes. No `Header`, `Cookie`, `SameSite`, `Secure` or auth-path directive changed.

### 2.8 `old-project/.user.ini` — the one environmentally significant change

```diff
-[PHP]
-asp_tags = Off
-display_errors = On
-max_execution_time = 30
-max_input_time = 60
-max_input_vars = 1000
-memory_limit = 256M
-post_max_size = 8M
-session.gc_maxlifetime = 1440
-session.save_path = "/tmp"
-upload_max_filesize = 4M
-zlib.output_compression = On
+error_log = "/home/php8way2allah/logs/php.error.log"
+log_errors = On
```

The entire `[PHP]` block was removed and replaced with error logging under **`/home/php8way2allah/`**. Two things follow, and I am separating what this proves from what it merely suggests:

- **Proven:** `session.save_path` and `session.gc_maxlifetime` are no longer pinned for `old-project`; it now uses the handler default. File mode also changed `100755 → 100644`.
- **Suggested, not proven:** the account/home path `php8way2allah` and `mainfile.php:2`'s `include_once("php83_compatibility.php")` together suggest `old-project` was recently moved to a **PHP 8.x handler**, possibly under a different cPanel account, while `7amlat` still references `ea-php72`. A handler or account change is the kind of event that alters cookie handling, `display_errors` behaviour and warning-to-fatal promotion. **I cannot confirm any of this from the repository, and I am not going to infer deployment facts from a log path** — the owner has previously and correctly ruled that out.

---

## 3. Current legacy producer behaviour

Login success path, `admincp/index.php:199-224`. All seven cookies use the same 5-argument `setcookie($name, $value, $expires, $path, $domain)` form, so `secure`, `httponly` and `samesite` are all left at their defaults.

| Cookie | Created by | Domain | Path | Secure | HttpOnly | SameSite | Payload / purpose | Deleted by |
|---|---|---|---|---|---|---|---|---|
| **`w2a_user`** | `admincp/index.php:218` | `.way2allah.com` | `/` | **no** | **no** | **unset → browser-default Lax** | `json_encode($Author)` — the whole `nuke_authors` row, **unsigned**. **The bridge.** | `index.php:324-331` logout loop, same Domain/Path |
| `admin` | `:217` | `.way2allah.com` | `/` | no | no | unset | `base64(aid:md5(pwd):admlanguage)` | same loop |
| `newadmin` | `:219` | `.way2allah.com` | `/` | no | no | unset | `$Author['uid']`, bare integer | same loop |
| `aid` | `:220` | `.way2allah.com` | `/` | no | no | unset | admin username | same loop |
| `pwd` | `:221` | `.way2allah.com` | `/` | no | no | unset | md5 or stored hash | same loop |
| `usertitle` | `:222` | `.way2allah.com` | `/` | no | no | unset | display name | same loop |
| `newlogin` | `:223` | `.way2allah.com` | `/` | no | no | unset | `'OK'` | same loop |
| `PHPSESSID` | `session_start()` (`w2a_config.php:6`) | **host-only** | `/` | no | yes (PHP default) | unset | AdminCP's own session — the Option D authority | `:345-347`, expired with Domain `.way2allah.com` (see §7 note) |

Logout path, `:309-363`: `$_SESSION = []` → `session_destroy()` → expire all seven cookies at Domain `.way2allah.com` Path `/` → expire `session_name()` → `header('Location: /admincp/index.php')`.

**Login and logout are symmetric on Domain and Path for all seven cookies.** Verified line by line.

---

## 4. Current old-project consumer behaviour

`old-project/mainfile.php:377-423`, `is_w2a_admin($admin)`.

**Authentication — "who is this admin?"**

| Step | Line | Behaviour |
|---|---|---|
| Session opened | `:386-388` | `session_start()` if none |
| Cookie consumed | `:390` | `if (!empty($_COOKIE['w2a_user']))` — **yes, still consumed** |
| Decoded | `:391-392` | `html_entity_decode(…, ENT_QUOTES, 'UTF-8')` then `json_decode(…, true)` |
| Malformed payload | `:395-398` | **`echo 'JSON ERROR…'; die;`** — a hard stop, not a graceful denial |
| Required field | `:400` | `is_array($Author) && !empty($Author['aid'])` — **`aid` must be present and non-empty** |
| Session built | `:401-405` | `$_SESSION['w2a']->admin`, `->usertype`, `$_SESSION['uid']` |
| Identity used | `:408` | `$aid = $_SESSION['uid']` |
| Cookie absent | `:408-409` | `$aid` falsy → `return 0` → treated as non-admin |
| DB verification | `:410-414` | `SELECT * FROM nuke_authors WHERE uid='$aid'` — **existence only**; no password, hash, token or signature |
| Verdict | `:415-421` | `1` if the uid exists **and** `$_SESSION['w2a']->usertype ∈ {superadmin, admin}` |
| Alternative mechanism | — | **None.** `VB5()` resolves the forum user for public features; it is never an admin authority. `admin.php:6` reads `$_COOKIE['admin_from_vb']` and never uses it for the gate. `login()` (`admin.php:36`) is a fallback form, not a bridge. |

**Authorization — "what may this admin do?"** `GreenZone($MyPerm)` (`mainfile.php:1855`) begins with `return;`. Every statement after it is dead. **There is no per-operation authorization in old-project at all**; any recognised admin can invoke any operation. Unchanged by any recent edit.

**Two fragilities worth recording, neither proven to be active:**

- `usertype` is taken from the **cookie's** `radminsuper`, while the DB row fetched at `:410` is used only for its `uid`.
- If a stale `$_SESSION['uid']` exists while `$_SESSION['w2a']` does not — possible if a request arrives with no cookie after one that had it — then `:415` dereferences `->usertype` on a non-object. Under PHP 8 that is an `Error`, not a warning. **This is the one source-level path that could turn a transient missing cookie into a hard failure that persists**, and it is listed as candidate C-3 below.

---

## 5. Root cause

**Classification: `UNPROVEN — ENVIRONMENTAL OR BROWSER-STATE SUSPECTED`.**

Not one of the eight offered categories can be asserted. Against each:

| Candidate category | Verdict from source |
|---|---|
| `COOKIE_NO_LONGER_CREATED` | **Not in source.** `setcookie("w2a_user", …)` is byte-identical to HEAD and on the login-success path. |
| `COOKIE_DOMAIN_CHANGED` | **Not in source.** `$cookie_domain = '.way2allah.com'` unchanged at `:216`. |
| `COOKIE_PATH_CHANGED` | **Not in source.** `'/'` unchanged. |
| `COOKIE_PAYLOAD_CHANGED` | **Not in source.** `json_encode($Author)` unchanged; consumer still requires only `aid`. |
| `COOKIE_SECURITY_ATTRIBUTE_CHANGED` | **Not in source.** The 5-argument form is unchanged, so `Secure`/`HttpOnly`/`SameSite` are all still unset — as before. |
| `COOKIE_DELETED_INCORRECTLY` | **Not as written** — deletion is symmetric with creation. But see C-2: deletion only began *working* recently. |
| `OLD_CONSUMER_CHANGED` | **Changed, but in the wrong direction** — widened from `superadmin` to `superadmin|admin`. |
| `MULTIPLE_CAUSES` | Possible, unproven. |

### Surviving candidates, ranked

**C-1 — Environmental: `old-project` moved PHP handler/account.** `.user.ini` lost its entire `[PHP]` block and now logs to `/home/php8way2allah/`, while `7amlat` still references `ea-php72`. A handler change alters `display_errors`, warning-to-`Error` promotion, and default session parameters. Combined with C-3, a PHP 8 `Error` on `->usertype` would surface as a blank or 500 page rather than a login prompt — which matches "no longer recognised" from a user's perspective. **Most probable, and not confirmable from the repository.**

**C-2 — Logout now actually works, so a previously-immortal cookie is now cleared.** Our own comment at `admincp/index.php:5-16` records that before `ob_start()` was added, `main.php`'s unconditional `var_dump()`s at its lines 10 and 12 sent output first, so *"every later setcookie()/header() call in `?op=logout` is silently discarded — the admin cookies would not be cleared and the redirect would never fire."* So historically `w2a_user` survived logout and lived its full 30 days. It is entirely possible that staff were never relying on a *fresh* login for the bridge, but on a cookie that had been immortal for weeks — and that the first genuine logout since the fix removed it. **If so this is not a login regression at all; the bridge would be restored by logging in again.** That makes it the cheapest hypothesis to test and the first thing §10's check settles.

**C-3 — Null-dereference on `$_SESSION['w2a']->usertype`** (`mainfile.php:415`) when `$_SESSION['uid']` is stale and `w2a_user` is absent. Under PHP 8 this is fatal. Would convert a one-off missing cookie into a hard error until the session expires. Plausible as an amplifier of C-1 or C-2, not as a primary cause.

**C-4 — Browser cookie state.** `Domain=.way2allah.com` cookies set without `Secure` over HTTPS, alongside any host-only cookie of the same name, can produce shadowing where the browser sends the wrong one. No source change points here, but it is cheap to rule out with the same check.

**Ruled out:** the `die("Begone")` guard (empirically disproved, §2.6); the logout redirect change (same-host, root-relative); `main.php`'s Option D gate (AdminCP-internal, not consulted by old-project); `w2a_config.php` and `functions.php` (unrelated); `.htaccess` (rewrite retargeting only); shared session storage (never shared — §1).

---

## 6. Cookie scope analysis (Phase 5)

`nuke.way2allah.com` and `old.way2allah.com` are sibling hosts under the same registrable domain, so they are **same-site** — a fact that resolves most of this cleanly.

| Attribute | Required value | Reasoning |
|---|---|---|
| **Domain** | **`.way2allah.com`** — required | Host-only would not reach `old.way2allah.com`. This is the one attribute that must stay broad. |
| **Path** | **`/`** — required | old-project's admin surface spans `/admin.php`, `/modules.php` and the pretty-URL rewrites. |
| **Secure** | **should be added** | Both hosts are HTTPS. Currently unset. Adding it is pure hardening with no functional cost. |
| **HttpOnly** | **should be added** | Verified: **no JavaScript in either project reads `w2a_user`** (`grep` over all `*.js` in both trees returns nothing). Adding it removes XSS theft of an admin credential at zero functional cost. |
| **SameSite** | **leave unset, or set `Lax`. Do NOT set `None`.** | Currently unset → browsers default to `Lax`. Because the two hosts are same-site, `Lax` cookies are sent on *every* same-site request, including subresources — not merely top-level navigations. **SameSite is not implicated in this regression and must not be weakened.** `None` would require `Secure` and would needlessly expose the cookie cross-site. |

---

## 7. Restoration options and recommendation

### Option A — Exact legacy compatibility

Restore the historical cookie behaviour verbatim.

*Works because* it is what old-project already consumes; zero consumer change. *But it restores every known defect:* an **unsigned, client-authored admin credential** whose only validation is that the `uid` exists in `nuke_authors` (Pass 1 S-1); **privilege forgery**, since `usertype` derives from the cookie's own `radminsuper` and not from the row fetched immediately afterwards (S-2); and it leaves `GreenZone()`'s disabled authorization untouched (S-3). Forum `uid`s are not secret — `16715` and `56540` are ordinary values — so anyone who knows one can mint super-admin access to a system holding live credentials for `w2acp_db2025-2`.

**Fallback only.** Not recommended.

### Option B — Minimal hardened shared-cookie bridge ← **RECOMMENDED, conditional on §10**

Keep the cookie and its name, scope and transport exactly as they are. Change only *what it carries* and *what is trusted*:

1. **Producer** (`admincp/index.php`): keep `w2a_user` but carry only a server-verifiable identity — the `uid` plus an HMAC over it using a shared secret, e.g. `{"uid":16715,"sig":"<hmac_sha256(uid|issued_at, SECRET)>","iat":…}`. Add `Secure` and `HttpOnly` by switching to the array form of `setcookie()`. Keep Domain `.way2allah.com`, Path `/`.
2. **Consumer** (`old-project/mainfile.php:390-406`): verify the HMAC with `hash_equals()` before trusting anything; on success load the admin row from `nuke_authors` and take **`radminsuper` from the database row**, never from the cookie. Reject on bad signature or stale `iat` by returning `0` — not `die()`.
3. Fix C-3 while touching that function: guard the `->usertype` dereference.

*Why this is proportionate:* two small edits in two files, no new tables, no new endpoints, no session-architecture change, and it closes S-1 and S-2 — the two Critical findings — without touching S-3 or anything else. It is also trivially removable when old-project administration retires, which is the stated direction.

*Feasibility:* both applications are PHP (old-project on 8.x per its compatibility shim) with `hash_hmac` and `hash_equals` available since 5.6. The shared secret can live beside the existing `$vbhash` convention in each app's config. No deployment change beyond file upload.

### Option C — Short-lived handoff token

AdminCP mints a single-use, short-TTL token, redirects to `old.way2allah.com/?handoff=…`, old-project verifies it server-side and establishes its own session.

Strictly the most secure — no long-lived domain-wide admin credential at all. **But disproportionate here:** it needs shared server-side token storage, replay protection, an entry point in old-project, and a new failure mode when the handoff expires mid-workflow. For infrastructure actively being retired, that is a rewrite where a signature suffices. **Not recommended now;** the correct destination for this pattern is Laravel, once it owns the administration.

### If §10 shows C-2 was the cause

Then **no code change is needed for the regression itself** — logging in again restores the bridge, and the "regression" was an immortal cookie finally being cleared by a logout that now works. Option B would still be worth doing as hardening, but on the migration's own schedule rather than as an emergency fix. **This is why §10 comes before implementation.**

---

## 8. Files that would need modification (Option B)

| File | Change |
|---|---|
| `7amlat/admincp/index.php` | `:218` — emit signed payload; switch to array-form `setcookie()` for `Secure`/`HttpOnly`. `:324-331` — unchanged (already symmetric). |
| `7amlat/w2a_config.php` | add the shared HMAC secret beside the existing `$vbhash` |
| `old-project/mainfile.php` | `:390-406` — verify HMAC; take `radminsuper` from the DB row; return `0` instead of `die()` on malformed input; guard the `->usertype` dereference (C-3) |
| `old-project/config-w2a.php` | add the same shared secret |

Nothing else. No Laravel file, no schema, no migration, no seeder, no permission.

---

## 9. Tests required before implementation

Phase 8's seven, with the mechanics each needs:

| # | Test | Mechanics |
|---|---|---|
| 1 | Successful AdminCP login establishes the bridge | assert a `w2a_user` cookie is emitted with Domain `.way2allah.com`, Path `/`, `Secure`, `HttpOnly`, and a payload whose signature verifies |
| 2 | old-project recognises the authenticated admin | drive `is_w2a_admin()` with a validly signed cookie for a real `nuke_authors.uid` → `1` |
| 3 | **Privilege fields cannot be forged** | cookie with a valid signature over `uid` but `radminsuper` tampered in the payload → `usertype` must still resolve from the **DB row**; and an unsigned legacy-format cookie must be rejected |
| 4 | Tampered/invalid bridge rejected | bad signature, truncated payload, wrong secret, expired `iat`, non-JSON → all return `0`, **no `die()`, no fatal** |
| 5 | Logout invalidates the bridge | after `?op=logout`, assert all seven cookies are expired at Domain `.way2allah.com` Path `/`, and that `is_w2a_admin()` then returns `0` |
| 6 | AdminCP's own authentication still works | Option D's session-only gate in `main.php` unchanged: valid session → admin; no session → denied regardless of any cookie |
| 7 | **Public/vBulletin authentication unaffected** | `VB5()` still resolves the forum user from `bbsessionhash`; the `$w2a_user` *variable* path is untouched. Explicitly guards the naming collision. |
| 8 | C-3 regression guard | stale `$_SESSION['uid']` with **no** `w2a_user` cookie → returns `0` cleanly, no `Error` |

These are legacy PHP with no test harness in either project, so 1–8 would need either a small standalone harness or manual scripted verification. That cost is part of the Option B decision and should not be hidden.

---

## 10. Production/browser evidence still needed — ONE check

Source and history cannot discriminate between C-1, C-2, C-3 and C-4. One browser inspection separates all four. **Read-only, no production command, no credentials, no server access.**

> **In a browser, log into `https://nuke.way2allah.com/admincp/` as an admin. Then open DevTools → Application (or Storage) → Cookies, and report, for the cookie named `w2a_user` only:**
>
> 1. **does it exist** after login?
> 2. its **Domain** value exactly as shown (`.way2allah.com` vs `nuke.way2allah.com`)
> 3. its **Path**, **Secure**, **HttpOnly**, **SameSite** and **Expires** columns
> 4. then navigate to `https://old.way2allah.com/admin.php` and report whether the **Network tab shows `w2a_user` in that request's `Cookie:` header**, and what the page renders (login form / blank / error / admin page)
>
> **Do not paste the cookie's value** — it contains admin identity data. Only the attribute columns and whether it was sent.

How to read the result:

| Observation | Conclusion |
|---|---|
| Cookie absent after login | `COOKIE_NO_LONGER_CREATED` — producer is failing at runtime despite unchanged source; next step is why `setcookie` does not take effect (headers-already-sent is the prime suspect given `main.php`'s `var_dump()`s) |
| Present, Domain = `nuke.way2allah.com` | `COOKIE_DOMAIN_CHANGED` at runtime — environmental override; investigate the handler/`.user.ini` change |
| Present, Domain = `.way2allah.com`, **and sent** to old.way2allah.com, but the page still denies | `OLD_CONSUMER_CHANGED` / C-3 — cause is inside `is_w2a_admin()`; the null-dereference guard becomes the fix |
| Present, Domain = `.way2allah.com`, **not sent** | `COOKIE_SECURITY_ATTRIBUTE_CHANGED` or C-4 shadowing — inspect for a duplicate host-only `w2a_user` |
| Admin page renders normally | **C-2 confirmed** — there was no login regression; a long-immortal cookie was finally cleared by a logout that now works. No fix needed for access; Option B proceeds as scheduled hardening. |

**Do not restore Option A to "get staff working" before this check.** If C-2 is the answer, re-logging-in already fixes it, and Option A would reintroduce two Critical vulnerabilities to solve a problem that no longer exists.

---

## 11. Runtime Evidence Round 2 — the failing mechanism, proven

### 11.1 The runtime fact

Fresh successful login at `https://nuke.way2allah.com/admincp/`. Firefox DevTools → Storage → Cookies for `https://nuke.way2allah.com` shows:

| Present | Absent |
|---|---|
| `admin`, `aid`, `newadmin`, `newlogin`, `pwd`, `usertitle` (all `.way2allah.com` scope) + `PHPSESSID` | **`w2a_user`** |

### 11.2 What this immediately rules out

The seven cookies are written on **consecutive lines** at `admincp/index.php:217-223`:

```
:217  setcookie("admin",     $admin,                 …)   ← PRESENT
:218  setcookie("w2a_user",  $cookieValue,           …)   ← ABSENT
:219  setcookie("newadmin",  $Author['uid'],         …)   ← PRESENT
:220  setcookie("aid",       $aid,                   …)   ← PRESENT
:221  setcookie("pwd",       $PasswordHash,          …)   ← PRESENT
:222  setcookie("usertitle", $Author['name'],        …)   ← PRESENT
:223  setcookie("newlogin",  'OK',                   …)   ← PRESENT
```

| Hypothesis | Verdict |
|---|---|
| **C-2** — logout started working, fresh login restores the bridge | **DISPROVED.** The cookie is absent immediately after a fresh successful login. |
| Statement not on the execution path | **DISPROVED.** The lines before (`:217`) and after (`:218`) both reached the browser. |
| "Headers already sent" | **DISPROVED.** `:219` succeeded *after* `:218` failed. A sent-headers condition is monotonic — it cannot skip one call and resume. |
| `$Author` empty or unpopulated | **DISPROVED.** `newadmin` carries `$Author['uid']` and `usertitle` carries `$Author['name']`; both arrived. `$Author` is a populated array at `:218`. |
| Domain / Path / Secure / SameSite attribute problem | **DISPROVED.** All seven calls use the identical 5-argument form with the same `'/'` and `.way2allah.com`. Six succeeded. |
| Deleted later in the request or redirect chain | **Ruled out by exhaustive search.** The only writers/deleters of `w2a_user` in the enhanced legacy tree are `admincp/index.php:218` (write), `:324-331` (the logout loop, not on the login path) and `:231` (a read). No other file writes or expires it. |

Everything about the request is identical across the seven calls **except the value**. The cause is therefore in `$cookieValue`.

### 11.3 The mechanism, proven empirically

`$cookieValue = json_encode($Author)` is computed at `:214`, before the block. Two facts, both executed rather than reasoned:

**Fact 1 — `json_encode()` returns `false` on malformed UTF-8:**

```
json_encode(invalid UTF-8) => false   error=Malformed UTF-8 characters, possibly incorrectly encoded
```

**Fact 2 — `setcookie()` with a `false`/empty value emits a DELETION cookie.** Captured from a real HTTP response, with the two calls adjacent exactly as in production:

```
Set-Cookie: w2a_user=deleted; expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0; path=/; domain=.way2allah.com
Set-Cookie: newadmin=16715; expires=Tue, 10 Nov 2026 06:51:07 GMT; Max-Age=2592000; path=/; domain=.way2allah.com
```

**This reproduces the observed browser state exactly** — `w2a_user` absent, its immediate neighbour present, no warning, no error, no header problem. PHP silently converts the intended write into a delete.

### 11.4 Why the value would fail to encode on production but not locally

Measured against the real rows on the local mirror, reconstructing `json_encode($Author)` from the full `nuke_authors` row:

| uid | `json_encode` | value bytes | header bytes | vs 4096 B browser limit |
|---|---|---|---|---|
| 16715 | **OK** | 2,220 | 2,316 | within |
| 56540 | **OK** | 2,638 | 2,734 | within |

So **size is not the cause** (both well under the per-cookie limit, even with `permissions` contributing 1,407 and 1,813 bytes), and the local data encodes cleanly. The failure must be data- or connection-dependent on production.

**There is a documented mechanism in this very project that produces exactly that.** `server_migration_parity.md` establishes that `ez_sql_mysql.php`'s charset guard breaks on MySQL 8: `SHOW CHARACTER SET` reports the 3-byte charset as `utf8mb3`, so the historical name `utf8` is absent, `in_array($encoding, $charsets)` fails, **`set_charset()` is silently skipped**, the connection falls back to the server default, and *"where the target charset cannot represent Arabic, MySQL substitutes literal `?` (`0x3F`) during transcoding."* Mis-transcoded or truncated multi-byte output is precisely what makes `json_encode()` return `false`.

The fix for that guard exists in `7amlat`'s working tree but is **uncommitted** (`ez_sql_mysql.php`, +14/-2), and `server_migration_parity.md` records it as deployed to `/home/w2acp/public_html/new/` — i.e. `new.way2allah.com`. **Whether it was also deployed to `nuke.way2allah.com` is unknown and is not inferable from this repository.**

Note that the six succeeding cookies do **not** disprove this. `setcookie()` URL-encodes its value and is indifferent to UTF-8 validity, so `usertitle` carries an Arabic name successfully even if those bytes are malformed. Only `json_encode()` is strict. That asymmetry is exactly why one cookie fails and six do not.

### 11.5 Revised root cause

**Classification: `COOKIE_PAYLOAD_ENCODING_FAILURE` → silent write-to-delete conversion.**

More precise than any of the eight categories originally offered. The closest, `COOKIE_PAYLOAD_CHANGED`, implies the payload's *shape* changed; here the payload's *encoding* fails and PHP converts the write into a deletion.

```
nuke.way2allah.com: ez_sql_mysql.php charset guard state UNKNOWN on that host
  → if set_charset() is skipped (MySQL 8 utf8 → utf8mb3 rename), connection uses the server default
  → Arabic columns of nuke_authors arrive mis-transcoded / invalid UTF-8
  → json_encode($Author) === false                                    [PROVEN mechanism]
  → $cookieValue = false
  → setcookie("w2a_user", false, …) emits  w2a_user=deleted; expires=1970  [PROVEN]
  → browser stores nothing; six adjacent cookies unaffected            [MATCHES RUNTIME]
  → old.way2allah.com receives no w2a_user → is_w2a_admin() returns 0
```

**The remaining uncertainty is one link only:** whether `json_encode()` actually returns `false` on production, or whether the header never appears at all (which would instead mean production's login code differs from this repository).

### 11.6 ONE next action — browser Network check

This supersedes §10. Still read-only, no production command, no credentials, no server access.

> **In Firefox, open DevTools → Network, tick "Persist Logs", then log in again at `https://nuke.way2allah.com/admincp/`. Select the login `POST` request, open the Response Headers panel, and report every `Set-Cookie` line whose name is `w2a_user` — the attributes only.**
>
> If the login response is a redirect, check the `POST` response itself, not the page it redirects to.
>
> **Do not paste the value if it is long JSON** — it contains admin identity data. Report only: does a `w2a_user` line exist, and if so, is its value the literal word `deleted`, empty, or a long JSON string, plus its `expires`/`Max-Age`.

| Observation | Conclusion |
|---|---|
| `Set-Cookie: w2a_user=deleted; expires=…1970…; Max-Age=0` | **ROOT CAUSE CONFIRMED** — `json_encode($Author)` returned `false`. Fix targets the encoding/charset, not the cookie mechanics. |
| `Set-Cookie: w2a_user=` (empty value, future expiry) | Same root cause class — value empty for another reason; next step is why `$Author` fails to encode. |
| `Set-Cookie: w2a_user={…long JSON…}` present, future expiry, yet no stored cookie | Header is correct and the **browser rejected it** — investigate per-cookie size against production's real `permissions` length and any duplicate host-scoped `w2a_user`. |
| **No `w2a_user` line at all**, while the other six are present | **Production source differs from this repository** — the `setcookie` at `:218` is not in the deployed file. The follow-up would then be one minimal read-only production command to diff the login block, and not before. |

Do not change login, logout, `old-project`, or recommend hardening until this returns. HMAC hardening (§7 Option B) remains desirable but is a separate decision from fixing the current producer.

---

## 12. Runtime Evidence Round 3 — browser rejection on size

### 12.1 The runtime fact

The production login `POST` response **does** emit `w2a_user`: long JSON value, future expiry, `Max-Age=2592000`, `Path=/`, `Domain=.way2allah.com`, not `deleted`, neighbours normal. HTTP 200, no redirect chain, no subsequent application response capable of deleting it (only 404 asset requests follow). Firefox Storage afterwards holds the other six legacy cookies and **not** `w2a_user`.

**`COOKIE_PAYLOAD_ENCODING_FAILURE` is disproved.** `json_encode($Author)` succeeded on production. §11.3's mechanism is real but not what is happening here, and §11.5's chain is withdrawn.

The problem is now bounded precisely: **the server emits a well-formed `Set-Cookie`; the browser declines to store it.**

### 12.2 Correction to my own measurement in §11.4

§11.4 reported `json_encode($Author)` at 2,220 bytes (uid 16715) and 2,638 bytes (uid 56540) and concluded *"size is not the cause."* **That conclusion was wrong, because the figure was the wrong quantity.**

`setcookie()` **URL-encodes the value by default.** JSON is dense in exactly the characters that expand to three bytes each — `{` `}` `"` `:` `,` `\` all become `%7B` `%7D` `%22` `%3A` `%2C` `%5C` — and the `permissions` field is a PHP-serialized blob, which is itself dense in `"` and `:`. The browser never sees the raw JSON length; it sees the encoded length. Re-measured against the same two real rows:

| uid | raw `json_encode` | **URL-encoded** | `name + value` | `permissions` | Verdict vs 4096 B |
|---|---|---|---|---|---|
| 16715 | 2,220 | **4,014** | **4,022** | 1,407 | within — **74 bytes of headroom** |
| 56540 | 2,638 | **4,840** | **4,848** | 1,813 | **exceeds by 752 bytes** |

URL-encoding nearly doubles the payload. My earlier figure understated the real header size by roughly 1,800–2,200 bytes, and that error is what led me to rule size out. This is the second measurement in this investigation where my own number pointed the wrong way; recording it plainly rather than quietly revising the table.

### 12.3 Why this explains every observation

| Observation | Explained by size rejection |
|---|---|
| Server emits a valid-looking `Set-Cookie` with long JSON | yes — the server is behaving correctly |
| Browser stores nothing, silently, no error in Storage | yes — browsers drop oversized cookies without surfacing anything in the Storage panel |
| Six neighbouring cookies stored fine | yes — all are tens of bytes |
| No `deleted` value, no 1970 expiry | yes — distinguishes this from §11's encoding failure |
| No redirect chain, nothing deletes it | yes — nothing needs to; it was never stored |
| **Different admins may behave differently** | yes — the size is a function of **that admin's `permissions` blob**: 16715 lands 74 bytes under the line, 56540 lands 752 bytes over |
| "It used to work" | yes — the blob grows as permission keys are added. `AdminPermissionSeeder`-era expansion of the legacy key set is exactly the kind of change that pushes a borderline cookie over a hard cap. |

Note the implication of the 16715 row: at 4,022 bytes of `name + value` it is within Firefox's cap **if the cap counts name+value only**, but over 4096 **if attributes are counted** (the attribute string here is roughly 95 bytes: `; expires=…GMT; Max-Age=2592000; path=/; domain=.way2allah.com`). Browsers differ on this — Chromium counts name+value+attributes against 4096; Firefox has historically capped name+value. **So whether *some* admins still work depends on which rule applies**, and that is directly testable (§12.5).

### 12.4 Remaining candidates, ranked

**R-1 — Per-cookie size rejection. LEADING, and the only candidate with a measurement behind it.** 4,848 bytes of `name + value` for uid 56540 against a 4096-byte cap. Firefox's documented behaviour is to reject the whole cookie and log a console warning naming the reason.

**R-2 — Per-domain cookie quota.** Firefox allows ~180 cookies per domain. Only seven legacy cookies plus `PHPSESSID` are in play, so this is implausible, but it has not been measured. Would also not be admin-dependent.

**R-3 — Duplicate `w2a_user` at a different scope.** A host-only `w2a_user` for `nuke.way2allah.com` could shadow the domain-wide one. The Storage panel showed **no** `w2a_user` at all for that host, which argues against it, but a cookie present at a different path would not necessarily be visible in the same view.

**R-4 — Malformed `Set-Cookie` syntax from the value.** `setcookie()` URL-encodes, so the value cannot contain a raw `;` or newline that would truncate the header. Low probability, and the owner's observation that the attributes parsed correctly (`Max-Age`, `Path`, `Domain` all displayed) argues the header was syntactically valid.

**R-5 — Browser cookie/privacy policy.** Total Cookie Protection partitions third-party cookies; `nuke.way2allah.com` → `old.way2allah.com` is same-site first-party navigation, so partitioning does not apply. Enhanced Tracking Protection would not drop a first-party cookie on its own origin. Very low probability.

**Not reopened:** charset / encoding (disproved by this round), headers-already-sent (disproved §11.2), `$Author` unpopulated (disproved §11.2), later deletion (ruled out by the Network trace plus the exhaustive writer/deleter search in §11.2).

### 12.5 ONE next check

Firefox names the rejection reason itself. This requires no value exposure, no server access, no production command.

> **In Firefox, open the Web Console (DevTools → Console). Enable the **Warnings** filter, and make sure the gear menu's **Show Content Messages** is ticked. Then log in again at `https://nuke.way2allah.com/admincp/`.**
>
> **Report any console message that mentions `w2a_user`** — the message text only.

Firefox emits a specific, self-diagnosing warning for each rejection cause, so the message text alone identifies which candidate is correct:

| Console message mentioning `w2a_user` | Conclusion |
|---|---|
| *"…is invalid because its size is too big. Max size is 4096 B."* | **R-1 CONFIRMED — per-cookie size rejection.** Root cause proven; the fix is to shrink the payload. |
| *"…has been rejected because it is already expired."* | contradicts the observed future expiry — re-examine the header |
| *"…has been rejected because it is in a cross-site context and its 'SameSite' is 'Lax' or 'Strict'."* | R-5 — a SameSite/partitioning issue after all |
| *"…has been rejected for invalid domain."* | domain mismatch — would point at the host serving the login |
| **No message mentioning `w2a_user` at all** | R-1 is unlikely (Firefox would normally say so); escalate to R-2/R-3 by checking the per-domain cookie count and searching Storage for a second `w2a_user` at any path |

**If R-1 is confirmed, the regression and the hardening converge on the same change** — the payload must stop being the entire `nuke_authors` row — and §7 Option B becomes the fix for *both* the outage and the security findings rather than optional hardening. That is a materially better position than it looked two rounds ago, but it is still a decision to take after the check, not before.

### 12.6 Security note on the captured header

The production `Set-Cookie` confirms that `w2a_user` carries **the full admin record** — including authentication-adjacent material (`password`/`pwd` hash columns, `key`, `plain`, `API`) and the complete serialized `permissions` blob — to the browser, unsigned, over a 30-day domain-wide cookie.

That value is treated as sensitive here: it is **not** reproduced anywhere in this report, and only byte *lengths* derived from local mirror rows are recorded. The previously pasted production value should be considered exposed and is a reason to rotate whatever it contained once the regression is closed.

This strengthens §7 Option B independently of the regression: the payload should carry an identifier and a signature, not the record. But per instruction, hardening is **not** mixed into this diagnosis and no implementation is proposed here.

---

## 13. Implementation — `COOKIE_SIZE_REJECTION_CONFIRMED`

Root cause closed by Firefox runtime evidence: `Cookie "w2a_user" is invalid because its size is too big. Max size is 4096 B.`

### 13.1 Files changed (4, across both legacy projects — nothing in Laravel)

| File | Change |
|---|---|
| `7amlat/w2a_config.php` | `$w2aBridgeSecret` config hook — `getenv('W2A_BRIDGE_SECRET')`, falling back to an untracked `w2a_bridge_secret.php`, else `null` |
| `7amlat/admincp/index.php` | producer: compact signed payload replaces `json_encode($Author)`; `Secure`+`HttpOnly` added; stale-cookie cleanup when no secret; AdminCP's own session now built from `$Author` instead of re-reading the cookie |
| `old-project/config-w2a.php` | same `$w2aBridgeSecret` hook |
| `old-project/mainfile.php` | consumer: `is_w2a_admin()` verifies the MAC, then derives all identity and authorization from the `nuke_authors` row |

### 13.2 Payload

`uid.issued_at.hmac_sha256(uid.issued_at, secret)` — dot-separated rather than JSON precisely because `.`, `-` and `_` pass through `urlencode()` unchanged, so the encoded size does not balloon the way JSON's `{ } " : ,` did.

| | before | after |
|---|---|---|
| `name + value`, uid 16715 | 4,022 B | **89 B** |
| `name + value`, uid 56540 | 4,848 B (**rejected**) | **89 B** |
| Contains password/key/API columns | yes | **no** |
| Contains `permissions` blob | yes | **no** |
| Contains `radminsuper` / role claim | yes, **and it was trusted** | **no** |
| Signed | no | **HMAC-SHA256, `hash_equals()`** |

A 45–54× reduction, with ~4 KB of headroom against the 4096 B cap regardless of how large any admin's `permissions` blob becomes.

### 13.3 Verified behaviour — 13 cases executed

| Case | Result |
|---|---|
| happy path, uid 16715 | ADMIN, `usertype=superadmin` |
| happy path, uid 56540 (the rejected one) | ADMIN, `usertype=superadmin` |
| non-super admin (`radminsuper=0`) | ADMIN, **`usertype=admin`** — derived from the DB row |
| TAMPER: uid swapped in payload | denied, fail-closed |
| TAMPER: one MAC character flipped | denied, fail-closed |
| TAMPER: legacy full-JSON cookie replayed | denied, fail-closed |
| TAMPER: `radminsuper=1` appended to a valid token | denied, fail-closed |
| EXPIRED: issued 30 days + 1 s ago | denied, fail-closed |
| FUTURE: issued +1 h (clock-skew abuse) | denied, fail-closed |
| wrong secret at consumer | denied, fail-closed |
| secret absent at consumer | denied, fail-closed |
| cookie absent | denied, fail-closed |
| valid signature, unknown uid | denied |
| producer with no secret | emits no bridge cookie, expires any stale one |

`php -l` clean on all four files.

### 13.4 Security properties

**Closed:** S-1 (unsigned credential — now MAC-verified before anything is trusted) and S-2 (`radminsuper` taken from the cookie — now read only from the `nuke_authors` row, verified by the non-super-admin test case).

**Also fixed in passing:** the consumer's `echo 'JSON ERROR…'; die;` on malformed input is gone — a bad cookie now returns `0` instead of halting a public page. And the §4 null-dereference fragility (`$_SESSION['w2a']->usertype` on a non-object when `$_SESSION['uid']` is stale) is structurally impossible now, because the function returns before touching session state unless a verified uid exists.

**Deliberately NOT changed:** `GreenZone()`'s disabled per-operation authorization (S-3). This hotfix establishes *which* admin is present, not what they may do — widening or restoring authorization is a separate, documented decision.

**SameSite:** not set and not weakened. The array form of `setcookie()` that accepts `samesite` requires PHP 7.3+, and this file must keep running on the deployed handler; omitting it leaves the browser default of `Lax`, which is correct because the two hosts are the same site, so `Lax` cookies are sent on every request between them. `SameSite=None` would be strictly worse and is not needed for cross-subdomain sharing. Documented rather than weakened, as instructed.

### 13.5 Logout symmetry — verified

`w2a_user` remains in the logout loop's `$cookies` array and is expired with the identical `Path='/'` and `Domain='.way2allah.com'` used at creation. Cookie deletion matches on name, domain and path, so adding `Secure`/`HttpOnly` at creation does not affect it. Login and logout stay symmetrical.

### 13.6 DEPLOYMENT — required manual step

**The bridge fails closed until a secret is supplied on both hosts. Nothing was fabricated and no secret is in the repository** — both config files are tracked in git.

1. Generate one value: `openssl rand -hex 32`
2. Provide it identically to **both** applications, by either route:
   - **environment:** `W2A_BRIDGE_SECRET` — Apache `SetEnv`, the PHP-FPM pool env, or cPanel's environment settings; or
   - **file:** an untracked `w2a_bridge_secret.php` beside each config, returning the string, e.g. `<?php return 'â€¦';` — and excluded from version control
3. The same value must be present for `nuke.way2allah.com` (beside `w2a_config.php`) and `old.way2allah.com` (beside `config-w2a.php`).

Until step 2 is done on both, AdminCP login still works normally and old-project simply denies — the pre-fix state, not a new failure.

### 13.7 Verification after deployment

1. Log into `nuke.way2allah.com/admincp/` → DevTools → Storage: `w2a_user` **present**, `Domain=.way2allah.com`, `Path=/`, `Secure`, `HttpOnly`, value ~89 B.
2. Console shows **no** size warning.
3. Navigate to `old.way2allah.com/admin.php` → recognised as admin.
4. Repeat for an admin whose `permissions` blob is large (uid 56540 was 4,848 B pre-fix) — must now work.
5. Repeat for a non-super admin — must be recognised, and must **not** gain super-admin.
6. Log out of AdminCP → `w2a_user` gone → `old.way2allah.com` denies.
7. Tamper check: edit one character of the cookie in DevTools → old-project denies.
8. Confirm public/forum login is unaffected — `VB5()` and the `$w2a_user` *variable* path were not touched.

### 13.8 Deployment-found regression — `$w2aBridgeSecret` not in scope inside `login()`

Found on first deployment, before the bridge ever worked. **My defect, introduced by §13.1's producer change.**

**Symptom.** After a successful AdminCP login, production emitted:

```
Set-Cookie: w2a_user=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT; Max-Age=0; path=/; domain=.way2allah.com
```

The secret file was independently verified present, readable, loaded, 64 characters, and loading correctly when running PHP as `w2acp`. So this was neither browser rejection nor the 4096-byte problem — the producer was deliberately taking its own fail-closed branch.

**Cause — PHP function scope.** Three verified facts:

| Fact | Evidence |
|---|---|
| `$w2aBridgeSecret` is assigned at **file scope**, so it is a genuine global | `w2a_config.php:99-105`; included by `functions.php:2` with no function definition anywhere before it |
| The entire producer block sits **inside `login()`** | `admincp/index.php:56` `function login(){` … `:277` `}`; producer at `:245-277` |
| `login()` did **not** import it | `:58` read `global $w2adb, $prefix, $db, $aid, $op, $admin, $adminurl, $siteurl, $vbdb;` — `$w2aBridgeSecret` absent |

Inside `login()` the variable was therefore an undefined local → `null` → `is_string(null)` false → the guard at `:248` failed → `$cookieValue` stayed `''` from `:246` → `:270`'s `if ($cookieValue !== '')` false → **`:276`** fired, and `setcookie()` with an empty value emits exactly the `deleted` / 1970 header proved in §11.3.

This is the codebase's own convention, and I broke it: `$vbhash` works in other functions precisely because they declare it. The configuration layer was correct throughout, which is why every check of the secret file passed while the bridge was broken.

**Trap for anyone re-reading this report:** the `w2a_user=deleted` header signature is identical to the one §11 attributed to a `json_encode()` failure. Same observable, different cause.

**Correction — one identifier, `admincp/index.php:58`:**

```diff
-	global $w2adb, $prefix, $db, $aid, $op, $admin, $adminurl, $siteurl, $vbdb;
+	global $w2adb, $prefix, $db, $aid, $op, $admin, $adminurl, $siteurl, $vbdb, $w2aBridgeSecret;
```

`$w2aBridgeSecret` is referenced only at `:248` and `:251`, both inside `login()`, so this single import covers every use. Nothing else changed — not the consumer, config loading, cookie format, secret handling, logout, or any unrelated code.

**The consumer was checked and is correct**, not assumed: `old-project/mainfile.php:417` declares `global $prefix, $db, $w2aBridgeSecret;` inside `is_w2a_admin()` (`:377`), and `mainfile.php:43` includes `config-w2a.php` at file scope. Had it carried the same defect it would have failed closed *silently* — denying rather than emitting a visible header — so confirming it matters.

**One benign consequence of the broken state:** because `:276` actively expired the cookie on every login, any admin still holding a pre-fix cookie has had it cleared. Harmless — the new consumer rejects unsigned payloads anyway — and it means nobody is running on stale state. A single clean login after this correction is all that is required.

### 13.9 Regression harness — `7amlat/tests/bridge_checks.php`

New standalone file, 10 checks, no database, no browser, no secret, no autoloader. Run with `php tests/bridge_checks.php` from the project root. Neither legacy project has a test harness and this deliberately does not introduce one.

Checks 1–3 are **static source parsing**, because the defect was static — every runtime check of the configuration layer passed straight through it.

| # | Check | Result |
|---|---|---|
| 1 | `login()` imports `$w2aBridgeSecret` via `global` — the direct regression test | PASS |
| 2 | **every** `$w2aBridge*` used in `login()` is imported or locally assigned (generalised; catches this defect class without anticipating it) | PASS — checked `$w2aBridgeSecret`, `$w2aBridgeIssuedAt`, `$w2aBridgeUid`, `$w2aBridgePayload` |
| 3 | `is_w2a_admin()` imports `$w2aBridgeSecret` via `global` (consumer side) | PASS |
| 4 | producer with secret emits a signed cookie matching `^\d+\.\d+\.[a-f0-9]{64}$` | PASS |
| 5 | producer with secret does **not** take the deletion branch | PASS |
| 6 | producer without secret still fails closed — guards against "fixing" this by deleting the `else` branch | PASS |
| 7 | cookie `name+value` under 4096 B | PASS — **89 B** (was 4,022 / 4,848) |
| 8 | round trip: producer output verifies to the right uid in consumer logic | PASS |
| 9 | round trip: tampered uid rejected | PASS |
| 10 | round trip: legacy full-JSON payload rejected | PASS |

**Mutation-verified.** Reverting the one-token fix and re-running: checks **1 and 2 FAIL** (`unresolved: $w2aBridgeSecret`), 8 others pass. File restored byte-identical afterwards. So the harness detects the exact deployed defect statically, with no production access.

`php -l` clean on `admincp/index.php`, `w2a_config.php` and `tests/bridge_checks.php`.

### 13.10 Residual note

The production cookie value captured during diagnosis contained the full admin record including `pwd`/`password`, `key`, `plain` and `API` columns. It is not reproduced anywhere in this report. **That value should be treated as exposed and the material it carried rotated** once the bridge is deployed — independent of this fix.

---

## 14. Regression hotfix — AdminCP login POST left the Nuke origin (2026-10-13)

**Separate from the OLD-admin migration.** Phase 3 (Category Assignment) is paused for this and was not touched.

### 14.1 Evidence

After an explicit HTTPS login to the Nuke AdminCP, the browser ended at `http://nuke.way2allah.com/admincp/index.php`.
That HTTP response tried to set `w2a_user` with `Secure`, and Firefox rejected it: *"a non-HTTPS cookie can't be set
as 'secure'"*. The §12/§13 size problem is **not** the cause; the cookie is the compact 89-byte signed token.

### 14.2 Root cause

The login form used the stale, cross-host `$adminurl`. In `admincp/w2a__login.php` the form action was
`<?php echo $adminurl; ?>index.php`, and `$adminurl` resolves (`w2a_config.php:61-62`) to
`https://way2allah.com/admincp/` — the Laravel app, not `nuke.way2allah.com`. So the login POST left the Nuke
origin.

**Infrastructure outside both repositories** returned it to Nuke over plain HTTP. No Gen 2 PHP, Laravel route,
fallback route, legacy-URL-map rule or `.htaccess` rule produces `http://nuke.way2allah.com/admincp/index.php`.
Because the HTTP request ran the *successful-login* branch (`admincp/index.php:271` — the only place `w2a_user` is
given a value), the forward preserved the POST body (a 307/308 redirect or a proxy). The login then sets its
cookies on that same HTTP response, with no redirect afterwards, and the browser correctly rejects the `Secure`
bridge cookie.

**This predates the §13 hotfix.** At `HEAD` the form action and config URLs are identical, and `w2a_user` was set
*without* `Secure`, so it was silently accepted over HTTP. Adding `Secure` exposed the HTTP landing; it did not
cause it.

### 14.3 Fix — same-origin, root-relative login POST

One line in `7amlat/admincp/w2a__login.php`:

```php
<form action="/admincp/index.php" method="post">
```

The POST now stays on the origin that served the login page, and the cross-host forward is never reached. This is
the same approach already used for the logout redirect (`admincp/index.php`, *"Root-relative on purpose"*).
`$siteurl` and `$adminurl` are **unchanged**: they feed asset URLs, including on this page, and links across the site.

**Unchanged:** the `w2a_user` cookie stays `Secure`, `HttpOnly`, `domain=.way2allah.com`, in the same 7-argument
form (so SameSite behavior is as before), with the same compact `uid.issued_at.hmac` token. Authentication,
password handling, session construction and logout are untouched. No Laravel and no OLD code changed.

### 14.4 Verification

`7amlat/tests/bridge_checks.php` gained checks **11–19**; all **19 pass**. The new checks prove:

- the action is exactly `/admincp/index.php`;
- the action no longer uses `$adminurl` or any PHP;
- rendering the form with `$adminurl = 'https://way2allah.com/admincp/'`, inside a function that imports it the way
  `login()` does, still yields `/admincp/index.php`;
- `w2a_user` is still `Secure`, still `HttpOnly`, and still uses `$cookie_domain = '.way2allah.com'`;
- the call is still the 7-argument form;
- the **real** `login()` still builds `uid.issued_at` and signs it with `hash_hmac('sha256', …)`. Checks 8–10
  exercise the harness's own copy of that logic; check 18 pins the source;
- there is no `http://` redirect or form destination in `login()`, the template, or its rendering.

**Mutation-tested:** with the old `$adminurl` action restored, exactly checks 11, 12 and 13 fail; with the fix they
pass. `php -l` is clean on both changed files.

### 14.5 Follow-up items — recorded, deliberately NOT fixed here

- **A. AdminCP sidebar links still use `$adminurl`** (`admincp/sidebar.php`), so navigating from the sidebar can
  leave the Nuke origin for `way2allah.com`. Same root cause, different symptom.
- **B. Other legacy admin cookies are not consistently `Secure`**: `admin`, `aid`, `pwd` (a password hash),
  `newadmin`, `usertitle` and `newlogin` are set without it, so they are accepted and sent over plain HTTP.
- **C. The external `way2allah.com/admincp` redirect or forward appears to downgrade HTTPS to HTTP.** It lies
  outside both repositories and should be reviewed separately. Any other POST that lands there would be downgraded
  the same way.

## Appendix — unchanged security findings

Pass 1 §13 and Pass 2A §F stand. Relevant here and **not** to be reintroduced by any fix: **S-1** unsigned `w2a_user` accepted as an admin credential with existence-only validation; **S-2** `radminsuper` trusted from the cookie rather than the row fetched immediately after; **S-3** `GreenZone()` returning immediately, disabling all per-operation authorization. `old-project/config-w2a.php:31` holds live credentials for `w2acp_db2025-2` — the database Laravel reads.
