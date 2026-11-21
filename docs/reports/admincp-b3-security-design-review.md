# AdminCP B3 / F02: administrator deactivation — security design review

**Date:** 2026-11-16. **Phase:** security design review only. **Nothing implemented. B′ is NOT approved.**
**Final architecture decision: see §13, the final database-compatibility section. It supersedes the `main` assumptions in §6, §7, §9 and §10 where they differ.**
**Basis:** `admincp-b3-admin-deactivation-security-proposal.md` (refined and partly superseded here), plan §F02.

**Scope of what was done:**
- **Read-only code reading:** Laravel; Gen 2 `7amlat` and OLD `old-project`, each in both the **working copy** and
  **committed `HEAD`** (`git show` / `git diff`).
- **Read-only aggregate queries** on the local `olddb`.
- **No password, hash, API key, session token or bridge secret was printed or used. No login was attempted.** No
  production access, no Way2Allah host request, no code, schema or data change, nothing committed, pushed or
  deployed.

---

## 0. Answers

| Question | Answer |
|---|---|
| Can `uid` stay unchanged? | **Yes**, provided four `uid`-keyed paths each get a deactivation check: the Laravel state check (guard, backup API, CLI); the OLD bridge state check (L2); the Gen 2 forum-session hooks (L3); and the backup API via `API` cleared. Without the legacy patches, preserving `uid` leaves **OLD bridge cookies valid for up to 30 days** and **Gen 2 forum-session admin hooks** open (§3) |
| Is credential neutralisation sufficient? | **No.** It reliably blocks **new password logins** (Laravel, Gen 2 bcrypt / md5 / sha1 / **plain text**) and the `pwd`-as-cookie comparison, **if `password` is a non-empty unusable marker**. It does nothing for existing sessions, the bridge cookie, forum-session hooks or the backup API (§2) |
| Mandatory legacy patches | **P0:** deploy the hardened Gen 2 / OLD versions (production unverified). **L1:** Gen 2 per-request state check. **L2:** OLD bridge state check and removal of the super-admin session shortcut. **L3:** Gen 2 forum-session hook state check, if those pages are still routed (§6) |
| Cookie vulnerabilities confirmed locally | V1–V3 in **committed** code (fixed only in uncommitted working copies); **V4, V5, V8–V11 present in both** (§4) |
| Are the 15 shared-value accounts exploitable? | **Yes, through existing code.** Gen 2's plain-text fallback accepts the stored value. **Any Gen 2 admin session can then reset any admin's `pwd`** (`edit_author.php`), and 3 of 5 super-admins authenticate by `pwd`: **a takeover chain** (§5). **Rotate now** |
| Recommended architecture | **Option B:** Laravel state (on `main`) + credential neutralisation + `API` cleared, **`uid` preserved**, + the minimal legacy patches L1–L3 after P0 (§7) |
| Complete deactivation without changing legacy code? | **No** (§7.2) |

---

## 1. Verified findings

### 1.1 Uses of `nuke_authors.uid`

| System | Location | Use | If `uid` changed (e.g. a sentinel) |
|---|---|---|---|
| Laravel | `BackupApiAuthenticator:34-35` | API auth: `where('uid', intval(ID))` + `API` | the volunteer client stops (desired while deactivated) |
| Laravel | `BackupApiController:118-131` | `backup_sessions` created and looked up by `uid` | backup history detached from the admin until restored |
| Laravel | `AdminStaffController:104,117` | creation links `uid` = vBulletin `userid`; duplicate check by `uid` | the same forum user could be re-added as a second admin (G8) |
| Laravel | `PermissionController:57`, `staff/index` | shows the linked forum user | the link disappears |
| Laravel | `AdminGrantInspector:101`, `admin:audit-grants` | duplicate-forum-uid and weak-identity reporting | a sentinel is excluded or flagged |
| Laravel | Spatie (`model_id` = `nuke_authors.id`), `admin_audit_log` (`actor_admin_id`, `actor_aid`) | — | **not affected** (keyed by `id` / `aid`) |
| Gen 2 | `admincp/index.php:184,309` | `$_SESSION['uid']`; bridge cookie payload | a new bridge cannot be issued (login already blocked) |
| Gen 2 | `admincp/sidebar.php:28-30` | menu permissions `WHERE uid = …` | the menu empties in open sessions (cosmetic) |
| Gen 2 | `functions.php:18-33` `is_w2a_admin()` | **forum session (`VB5('id')`) → `nuke_authors` by `uid` → admin** | the hook stops treating the user as admin |
| Gen 2 | `w2a_config.php:193` `VBUserType()` | loads the admin row by `uid` (but returns `admin` for **any** forum user anyway, V4) | no effect (V4 dominates) |
| Gen 2 | `admincp/authors/index.php:94` | admin management by forum user | admin listing |
| Gen 2 / OLD | `backup.php` (Gen 2 `:56-70,124,167`; OLD `:51-58,110,154`) | API auth by `uid` (intval) + `API`; backup sessions by `uid` | as in Laravel |
| OLD | `mainfile.php:462` `is_w2a_admin()` | **bridge cookie `uid` → `nuke_authors` by `uid`** | the bridge fails |
| OLD | `vb5.php:115-127` | forum login: when `uid` matches → set `admin_from_vb` and **rewrite `nuke_authors.pwd`** | the hook stops |
| vBulletin | `user.userid` = `nuke_authors.uid` | forum account | **untouched either way** |

**Workflows that break if `uid` changes:** none that a deactivated admin legitimately needs. But it is an **identity
mutation on shared data**, it detaches backup history and the forum link, and reactivation must restore it exactly
(collision risk). **Preserving `uid` is cleaner if L2 / L3 are applied.**

### 1.2 Authentication paths and what each one checks

| Path | Credential | Re-checked per request | Status-aware today |
|---|---|---|---|
| Laravel login | `aid` + `password ?: pwd` (bcrypt / md5 / sha1; empty or unknown → reject; **no plain text**) | — | no |
| Laravel session | `admin_auth_id` → `find($id)` | **yes** | no |
| Gen 2 login | `aid` + `password` if non-empty, else `pwd`; bcrypt (`$2y$` / `$2a$`), md5, sha1, **else plain text `===`** | — | no |
| Gen 2 AdminCP session | session snapshot (working copy) / `newadmin` cookie (`HEAD`) | **no** | no |
| Gen 2 public admin hooks | forum session → `uid` (`is_w2a_admin`), or any forum id cookie (`VBUserType`, V4) | per request | no |
| OLD AdminCP | HMAC bridge `uid` → `nuke_authors` **or** session `usertype == superadmin` (`admin.php:35`) | bridge: yes; shortcut: **no** | no |
| OLD `0000000dsak…admin.php` | cookie `admin_from_vb` = base64(`name:pwd:lang`) compared **raw** with `nuke_authors.pwd` (**pass-the-hash**), then the bridge for module ops | per request | no |
| OLD `vb5.php` (forum-login bridge) | forum login → sets `admin_from_vb`, rewrites `pwd = md5(forum password)` | — | no |
| Backup API ×3 (Laravel, Gen 2, OLD) | `intval(ID)` → `uid`; 32-character `API` equality; no password | per request | no |
| Laravel CLI `--actor` | `aid` lookup | — | no |

### 1.3 Session lifetimes

| System | Lifetime |
|---|---|
| Laravel | file sessions, 120 min idle |
| Gen 2 | PHP `/var/cpanel/php/sessions/ea-php72`, `gc_maxlifetime 1440` (sliding: **an active session never expires**) |
| OLD | PHP `/tmp`, 1440 |
| Bridge cookie | 30 days, no server-side revocation (only secret rotation) |
| Gen 2 legacy cookies (`admin`, `aid`, `pwd`, `newadmin`, …) | 30 days |

Logout clears cookies client-side only. Production values are **unverified** (§8).

## 2. Assumptions (explicit)

- Local `7amlat` / `old-project` working copies reflect intended hardening; **production is unknown** (§8).
- Gen 2 public pages (`channels/old.php`, `advanced-search/`, `fatawa/*`, `surveys/*`) and OLD's host **may still be
  routed** in production (unverified, plan R2 / B4).
- `vb5.php` on the OLD host is reachable only if something posts to it. The Gen 2 form posts to
  `https://way2allah.com/vb5.php` (the Gen 2 copy, which does **not** write `nuke_authors`).
- The bridge secret is configured in production (`W2A_BRIDGE_SECRET` / file); unverified.

## 3. Identity preservation analysis

**Option "keep `uid`" requires each `uid`-keyed admin path to learn about deactivation:**

| Path | How, keeping `uid` |
|---|---|
| Laravel guard / backup API / CLI | Laravel state check (no legacy change) |
| All three backup endpoints | **`API` → NULL** (fails closed: 32-character equality); Laravel also checks the state |
| OLD bridge | **L2** state check in `is_w2a_admin()`. Stop-gap without code: **rotate the bridge secret** at each deactivation (invalidates every admin's OLD bridge cookie; all must log in to Gen 2 again) |
| Gen 2 `functions.php` forum hook | **L3** state check, **or** confirm those Gen 2 pages are no longer routed |
| OLD `vb5.php` + the `0000000dsak…` file | neutralised `pwd` breaks the comparison, **but** `vb5.php` rewrites `pwd` on forum login. Remove or disable the obscure file and `vb5.php`'s `pwd` rewrite (E3 / E4) |

**Comparison:**
- **Changing `uid`** (the earlier B′ sentinel) closes the bridge, the forum hooks and the vb5 hook *without* legacy
  code, at the cost of an identity mutation and the collision handling.
- **Keeping `uid`** needs L2 / L3 (or secret rotation and confirmed unrouted pages).
- Given that **P0 and L1 are mandatory anyway** (legacy must be touched regardless), **keeping `uid` + L2 / L3** is
  the cleaner end state.

## 4. Authentication risks

### 4.1 Cookie and session weaknesses

| # | Weakness | Committed `HEAD` | Working copy | Production |
|---|---|---|---|---|
| V1 | Gen 2 AdminCP gate = `UserType($_COOKIE['newadmin'])` (unsigned; forum table) | **confirmed** | fixed (session-only gate) | **unknown** |
| V2 | Gen 2 login puts the **full row** (hashes, API key, permissions) in unsigned `w2a_user`, and **rebuilds the session from it** | **confirmed** | fixed (HMAC `uid.issued_at.mac`; no row; no rebuild) | **unknown** |
| V3 | OLD trusts `radminsuper` from the `w2a_user` JSON | **confirmed** | fixed (HMAC verify, DB authority) | **unknown** |
| V4 | Gen 2 public pages: any forum id in `bbuserid` / `admin_from_vb` → `admin` (`VBUserType`) | **confirmed** | **still present** | unknown |
| V5 | Gen 2 login **plain-text fallback** | **confirmed** | **still present** | unknown |
| V6 | Gen 2 cookies `admin` / `pwd` carry `md5(password)`, 30 days, **no Secure / HttpOnly** | **confirmed** | **still present** | unknown |
| V7 | `var_dump()` debug output in `admincp/main.php` | **confirmed** | **still present** | unknown |
| V8 | **Gen 2 `edit_author.php` (5 copies)**: any admin session may rewrite **any** admin's `permissions` (by unescaped `$_POST['uid']`: **SQL injection**), and `authors/edit_author.php` may reset **any** admin's `pwd`. No super-admin check, no CSRF token | **confirmed** | **still present** (file unchanged) | unknown |
| V9 | OLD `admin.php:35` super-admin **session shortcut** bypasses the bridge | **confirmed** | **still present** | unknown |
| V10 | OLD `0000000dsak…admin.php`: **pass-the-hash** cookie authentication against `pwd` | **confirmed** | **still present** | unknown (file present locally) |
| V11 | OLD `vb5.php` rewrites `nuke_authors.pwd` on forum login and sets `admin_from_vb` | **confirmed** | **still present** | unknown |

### 4.2 Security properties of the local hardening (and its limits)

**Provides:**
- HMAC-SHA-256 over `uid.issued_at` with a server secret;
- strict format; constant-time comparison;
- 30-day maximum age; future timestamps rejected;
- **fail closed** when the secret is absent (no cookie issued; the consumer returns 0);
- authorisation always re-read from the database (no privilege in the cookie);
- Gen 2 AdminCP gate session-only (no cookie trusted);
- Secure + HttpOnly on the bridge cookie.

**Does not provide:**
- revocation: a copied cookie stays valid 30 days after logout, and only **secret rotation** revokes;
- no binding to session, IP or UA; no nonce; no replay detection;
- the OLD super-admin shortcut (V9) and `is_w2a_admin()` not clearing a stale session;
- V4, V5, V6, V8, V10, V11 are untouched.
- **The local hardening is not complete.** It closes the forged-cookie paths V1–V3 only.

## 5. The 15 shared-value accounts

| Item | Finding |
|---|---|
| Accounts | **15** (`nuke_authors.id` 9602, 9625, 9628, 9631, 9633, 9635–9642, 9645, 9646); **one identical** 9-character alphanumeric `pwd`; `password` empty |
| Active? | there is no status concept, so **all can authenticate** wherever their credential is accepted. All have a forum `uid`. **4 legacy log entries since 2025-01-01** are attributed to these accounts' `aid` (at least one appears in use) |
| Plain-text fallback accepts the format? | **Gen 2: yes** (not bcrypt / md5 / sha1 → `===` comparison). Laravel: **no** (no plain-text fallback). OLD `0000000dsak…`: **yes** (raw cookie comparison with `pwd`) |
| Administrative permissions | `radminsuper = 0` for all; one has a legacy permission blob with **no** enabled entry; **no** Spatie grants; no API key |
| **Effective risk** | a Gen 2 session as any of them is still an **admin session**, and **V8 lets any admin session grant itself permissions and reset any admin's `pwd`**. 3 of the 5 super-admins authenticate by `pwd` (empty `password`) in Gen 2 and Laravel. **Chain: shared value → Gen 2 session → reset a super-admin's `pwd` → super-admin in Gen 2 and Laravel** |
| Rotation required? | **Yes, immediately**, independent of B3: give each legitimately used account its own credential (bcrypt via Laravel's password form) and neutralise the rest. **Fix V8** and remove V5 as soon as possible |

## 6. Required legacy changes (owner-applied; this project does not edit `7amlat` / `old-project`)

| # | Change | Mandatory for |
|---|---|---|
| **P0** | Deploy the hardened working copies of Gen 2 `admincp/index.php`, `admincp/main.php`, `w2a_config.php` and OLD `mainfile.php`, `admin.php` (fix V1–V3), with the bridge secret configured on both hosts | **any** guarantee |
| **L1** | Gen 2 `admincp/main.php`, after the session gate: `SELECT 1 FROM admin_account_states WHERE admin_id = ? AND deactivated_at IS NOT NULL` (and the row still exists) → destroy the session, redirect to login. One indexed query per AdminCP request | revoking **open Gen 2 sessions** |
| **L2** | OLD `mainfile.php` `is_w2a_admin()`: the same state check after the `uid` lookup, clearing `$_SESSION['w2a']` on failure; **and** `admin.php:35` drops the `usertype == 'superadmin'` shortcut | the **bridge** (`uid` kept) and **open OLD super-admin sessions** |
| **L3** | Gen 2 `functions.php` `is_w2a_admin()`: the same state check | the **Gen 2 forum-session hooks** (if those pages are routed) |
| E1 | Gen 2 `edit_author.php` ×5: require `superadmin`, a CSRF token, and `intval` on `uid` (V8) | security (urgent) |
| E2 | Gen 2 `VBUserType()` / public callers: require a real `nuke_authors` match (V4) | security |
| E3 | Remove or disable OLD `0000000dsak…admin.php` (V10) | security |
| E4 | Remove OLD `vb5.php`'s `pwd` rewrite / `admin_from_vb` (V11) | security; protects neutralisation |
| E5 | Remove the plain-text fallback (V5) after rotation; Secure / HttpOnly or drop the `admin` / `pwd` cookies (V6); remove `var_dump` (V7) | security |

**Shared state check:**
- It is safe **only if the state table lives on `main`**, the database legacy already connects to.
- Each check is a single indexed read.
- **Behaviour when the table does not exist yet:** treat it as "not deactivated" (avoids a global lockout during
  rollout). Deploy the Laravel migration **before** the legacy patches.

## 7. Recommended architecture

### 7.1 Options

| | **A. Laravel state + credential neutralisation, `uid` kept** | **B. A + minimal legacy patches (P0, L1–L3)** | **C. Shared enforcement at every boundary (B + E1–E5 + backup endpoints + forum hook)** |
|---|---|---|---|
| New logins (Laravel / Gen 2) | blocked | blocked | blocked |
| Laravel sessions | cut at the next request | the same | the same |
| Gen 2 open sessions | **remain** (idle ≥ 24 min; unlimited if active) | **cut at the next request** | cut |
| OLD bridge (30 days) | **open** (unless the secret is rotated globally) | **blocked** | blocked |
| OLD super-admin sessions | **remain** | **cut** | cut |
| Gen 2 forum-session hooks | **open** | **blocked** (L3) | blocked |
| Backup API ×3 | blocked (`API` NULL) | the same | the same, plus explicit checks |
| Escalation via V8 / V5 | **open** | open (separate track) | **closed** |
| Required changes | Laravel only | Laravel + ~4 small legacy edits | Laravel + ~10 legacy edits |
| Database impact | additive table on `main`; row writes to `password` / `pwd` / `API` | the same | the same |
| Legacy compatibility | untouched | patched files must read the new table (tolerate absence) | broader legacy edits |
| Deployment dependencies | Laravel migration + deploy | + P0 verified + legacy deploy after Laravel | + coordinated legacy release |
| Rollback | reactivate (new password, new API key) | the same + revert patches (fail-open by design) | larger |
| Operational complexity | low | moderate | high |

**Recommendation: Option B** (keep `uid`), with **E1 and the shared-value rotation done first as an urgent,
separate security track**, and E2–E5 scheduled (moving toward C).

### 7.2 Complete deactivation without changing legacy code?

**No.**
- Without legacy changes, open Gen 2 sessions and OLD super-admin sessions survive, and the OLD bridge stays valid for
  up to 30 days if `uid` is kept. Option A still blocks every new password login and the backup API.
- If production runs the committed `HEAD` code (V1–V3), nothing is achievable for Gen 2 / OLD at all.

### 7.3 Credential neutralisation details (for any option)

- **`password` (TEXT): always a non-empty marker.**
  - Gen 2 and Laravel use it in preference to `pwd`, so a later `pwd` rewrite (V8 reset, V11 forum hook) cannot
    revive a login.
  - It must **not** start with `$2y$` / `$2a$` and must not be 32 / 40 hex, so Gen 2 falls to the `===` branch with an
    unknowable value, and Laravel rejects the unknown format.
  - Example shape: `!disabled:` + 48 random base62 characters (generated, never logged).
- **`pwd` (`varchar(40)`):** a ≤ 40-character non-hex random marker. This breaks the pass-the-hash comparison (V10)
  and any md5 cookie match.
- **`API`:** NULL. All three backup endpoints require 32-character equality.
- **Accidental-acceptance review:**
  - **Laravel:** empty → reject; unknown format → reject.
  - **Gen 2:** empty input rejected; the plain-text branch requires knowing the random marker.
  - **No path accepts an empty stored credential.** The only revivals are the `pwd` rewrites V8 / V11, neutralised
    by the non-empty `password` marker for Gen 2 and Laravel logins (V10 still reads `pwd`, hence E3 / E4).
- **Reactivation:** a new bcrypt `password` set by a super-admin. `pwd` stays the marker. A new random `API` key is
  generated only on request and shown once. **No old credential is ever stored or restored.**

## 8. Production verification checklist (owner, read-only; no credentials, no bypass tests)

On each production host (shell or file manager), **do not print secrets**.

1. **Identify docroots.**
   - Read the vhost configuration and record which docroot serves `way2allah.com`, `nuke.way2allah.com` and
     `old.way2allah.com`, and whether `/admincp/`, `/backup.php`, `/vb5.php`, `/channels/`, `/advanced-search/` are
     served by Laravel or by legacy files.
   - Read access logs (`grep -c`) for requests to `/admincp/`, `/vb5.php`, `/backup.php` and
     `0000000dsak…admin.php` over the last 30 days. **Counts only.**
2. **Marker strings** (`grep -c`, prints only a number):

   | File | Hardened marker (expect ≥ 1) | Committed / vulnerable marker (expect 0) |
   |---|---|---|
   | Gen 2 `admincp/main.php` | `native PHP session ONLY` | `UserType(isset($_COOKIE['newadmin'])` |
   | Gen 2 `admincp/index.php` | `hash_hmac('sha256'` | `$cookieValue = json_encode($Author)` |
   | OLD `mainfile.php` | `hash_equals($w2aBridgeExpected` | `json_decode($cookieUser` |
   | OLD `admin.php` | `is_w2a_admin('')` (present in both versions; confirms the file) | — |
   | Gen 2 `admincp/authors/edit_author.php` | — | `SET pwd = '` **(present = V8 open)** |
   | OLD `0000000dsakjdlsatydrftasyfdasytdfsaytdfaadmin.php` | — | file exists = V10 present |
   | OLD `vb5.php` | — | `UPDATE nuke_authors SET pwd` (V11) |

3. **Checksums** (`sha256sum <file>`; compare with these local references; config edits or line endings can differ,
   so the marker strings are authoritative):

   | File | Committed `HEAD` | Local hardened working copy |
   |---|---|---|
   | Gen 2 `admincp/main.php` | `5688a1c28049a187b2b6633bd0218f8bc33834617aa5093e5ebab26b54ce90b4` | `a4b82b5aa81b06ab4227992ccbbd04eaea4304f8479411cdf6a1297fea104847` |
   | Gen 2 `admincp/index.php` | `cdaf1a606d532de5bde72c0bdeb8634e2cdf8a9ca064f90bc972084aae92a63a` | `e981068acb558c19411cb328e1491787e0080fee88cbf3fec056e53ab1239e94` |
   | Gen 2 `admincp/authors/edit_author.php` | `58b33fef3a11c3231050108825364fb6d929e4d7c2bcc6847689000f627aa68f` | same (unchanged) |
   | OLD `mainfile.php` | `2ce18435168b1499041ab291705305d07615abe08d64e5c7f7bec1d16e6c5939` | `25c5cb6ba596588ec9a33a7c017d25418378529fbd281ba16f5a5f3f9cdc2bce` |
   | OLD `admin.php` | `3e9a574f935ee6bdb45b3958505621ed637e725929ce3124345a19c601da38a9` | `22b1c0106fa7c98e6b3b3e57faa0dfaf46b29c6ec3c336580d8c1a500d882641` |
   | OLD `vb5.php` | `e4428a3b34a2d8102938bdc9ef9f090eaac8100ef5692fa8b8b8d66f0b74f414` | same (unchanged) |
   | OLD `0000000dsak…admin.php` | `076c907024c652fdc90e220bb60de05cf44906aa872ebbb122bf0714f6421532` | same (unchanged) |

4. **Bridge secret configured** (prints only yes / no):
   - `[ -n "$W2A_BRIDGE_SECRET" ] && echo set || echo unset` in the PHP environment;
   - or check that the configured secret file exists and is non-empty (`test -s <file> && echo present`).
5. **Session settings:** `php -i | grep -E 'session.gc_maxlifetime|session.save_path'` per PHP handler, or `grep`
   the vhost `.htaccess` (`php_value session.`).
6. **Credential profile** (read-only SQL, aggregates only, as in this review): count of `pwd` formats, `password`
   formats, super-admins with empty `password`, and shared values. **Never select the columns themselves.**

**Interpretation:** any "committed / vulnerable marker ≥ 1" for V1–V3 is a **release blocker** for B3 and an
immediate security issue.

## 9. Implementation phases (proposed)

| Phase | Content | Owner / project |
|---|---|---|
| **0, urgent** | run §8; rotate the 15 shared-value accounts; apply **E1** (V8); confirm or apply **P0** | owner (legacy) |
| **1** | Laravel:<br>• `admin_account_states` on `main` (additive migration);<br>• Deactivate / Reactivate actions (one transaction, `FOR UPDATE`, lock, rules, audit);<br>• guard, backup API, CLI and sync enforcement;<br>• neutralisation (`password` / `pwd` markers, `API` NULL; **`uid` kept**);<br>• UI; audit of permission and password edits;<br>• tests (incl. MySQL env-gated concurrency) | project, after approval |
| **2** | L1, L2, L3 (state checks; OLD shortcut removal), deployed **after** phase 1's migration | owner (legacy) |
| **3** | E2–E5 hardening; optional bridge-secret rotation policy | owner (legacy) |

## 10. Super-admin safety

| Case | Safeguard |
|---|---|
| Self-deactivation | refused (422) |
| Last active super-admin | refused. Count of (`radminsuper = 1` or Spatie `super-admin`) and not deactivated, excluding the target, must be ≥ 1, computed inside the transaction under `FOR UPDATE` |
| Another super-admin as target | **recommended: requires approval by a second active super-admin** (request → approve, expiring, both audited). Rationale: V5 / V8 show how a super-admin account can be taken over; a single compromised super-admin must not be able to lock the others out |
| Concurrent attempts | one transaction on `main` + `SELECT … FOR UPDATE` on the target's and all super-admins' state rows + an atomic `admin-deactivation` lock; mutual deactivation cannot both succeed |
| Reactivation | super-admin only (a super-admin target needs a second super-admin, as above); new password required; `API` new only on request; Spatie grants were never removed |
| Emergency recovery | `php artisan admin:reactivate --aid=… --reason=…` (server shell = trust anchor), audited with a CLI system actor (needs an `AdminAuditRecorder` extension), refuses unless the target is deactivated. **No manual SQL** |
| Audit | `deactivate_admin`, `reactivate_admin`, `deactivation_requested` / `_approved` / `_expired`, with reason, actor, target and legacy effects (booleans only); **never** markers, hashes or keys; plus the currently missing audit of permission and password edits |
| Permission | super-admin only; **no new permission key** |

## 11. Release blockers

1. **Production legacy version unknown** (V1–V3). Run §8 first.
2. **The 15 shared-value accounts + V8 escalation chain.** Fix before any further admin feature ships.
3. **L1–L3 not applied:** B3 can only promise "new access blocked; open legacy sessions until expiry".
4. The bridge secret must be configured on both hosts (fail-closed otherwise, which disables the OLD bridge
   entirely).
5. State table placement on `main` (required for the legacy checks).

## 12. Decisions requiring owner approval

1. **Option B** (keep `uid`) over A / C; or the earlier B′ `uid`-sentinel variant if no legacy patch will ever be
   applied.
2. Run the §8 checklist and share the yes / no results (no secrets).
3. The urgent track: rotate the 15 accounts; apply E1 (V8); decide on E2–E5.
4. Legacy patches **L1, L2, L3** and their deployment order (after the Laravel migration).
5. The state table on `main`.
6. **Second-super-admin approval** for deactivating or reactivating a super-admin.
7. The break-glass CLI with a system audit actor.
8. API key on reactivation: new on request (recommended) or never.
9. Whether to rotate the bridge secret on each deactivation as a stop-gap until L2 ships.

---

## 13. FINAL DATABASE COMPATIBILITY AND ARCHITECTURE DECISION (2026-11-16)

**Investigation and documentation only.**
- No code, data, schema or migration change.
- No production access and no Way2Allah host request; nothing committed, pushed or deployed.
- Evidence is the owner-supplied production observations already recorded in the repository, plus local source reading.

### 13.1 Actual database topology: the evidence, in date order

| Date | Source (owner-supplied unless noted) | Statement |
|---|---|---|
| 2026-09-03 | decision-log #58 | Laravel `main` = **`w2acp_laravel`** on **MySQL 5.7.44**; six `w2acp*` schemas visible |
| before 2026-09-18 | `7amlat/db_old_to_new_forensic_report.md` §20.1 | "two separate application databases": legacy public site and legacy dashboard on **`w2acp_db2025-2`**, Laravel on **`w2acp_laravel`** |
| 2026-09-19 / -22 | forensic §18.1 / §19.0; `7amlat/laravel_to_old_db_compatibility_report.md` §0.1 (Q1) | `w2acp_db2025-2` on **MariaDB 10.11.19**, host `server.way2allah.com`, user `w2acp@localhost` with grants only on `w2acp_db2025-2`. **`w2acp_db2025-2` and `w2acp_laravel` are visible from the same server**; the MySQL 5.7.44 observation is declared stale |
| 2026-09-22 | same report §0.2 (Q2, production Laravel `.env`) | `DB_DATABASE = DB_MAIN_DATABASE = w2acp_laravel`. Laravel had never used `w2acp_db2025-2`. The intended target is `main → w2acp_db2025-2` |
| **2026-09-24 (latest)** | same report §0A (Tinker + `migrate:status`) | **cutover done:** `default_connection = mysql`, **`default_db = w2acp_db2025-2`, `main_db = w2acp_db2025-2`**, `migrate:status` = 7 Ran |

**Local configuration (not production):**
- Laravel `main` is `olddb` @ 127.0.0.1:3307; `default` is SQLite (`.env.production.example` also shows SQLite);
- Gen 2 `w2a_config.php` uses `olddb` @ `mysql57`;
- OLD `config-w2a.php` names **`w2acp_db2025-2`** on host `way2allah.com` (the owner confirmed that OLD shares the legacy database).

**Conclusion:**

| System | Current production database (latest evidence, 2026-09-24) |
|---|---|
| Gen 2 (public pages and `admincp/`) | `w2acp_db2025-2` |
| OLD (`old.way2allah.com`) | `w2acp_db2025-2` |
| Laravel `main` (legacy/business: `nuke_authors`, …) | **`w2acp_db2025-2`** |
| Laravel default (Spatie, migrations, cache, jobs) | **`w2acp_db2025-2`**. This deviates from the owner's own intended `w2acp_laravel`, but it is what was reported |
| `w2acp_laravel` | now-unused legacy copy (its own drifting `nuke_*` copies) |
| Server | one MariaDB 10.11.19 (`server.way2allah.com`) |

**Correction:** the premise "Gen 2 = `w2acp_db2025-2`, Laravel = `w2acp_laravel`" came from P0-A's final review (§0.1 of the P0-A results, citing forensic §20.1). It reflects the **pre-cutover** state and is **superseded by the 2026-09-24 cutover record**.
- P0-A's audit choice (`nuke_islamic_logs` in Gen 2's own schema) remains correct under either topology.
- P0-A's blocker "Gen 2 password changes do not reach `w2acp_laravel`" is **moot if the cutover stands**.
- The evidence is now 7 weeks old and was not re-verified here. **It must be re-read before B3** (§13.8, check C1).

**Production Laravel build:** `migrate:status = 7 Ran`, while the repository now has 11 migrations. So `admin_audit_log` (2026-10-12) and every later migration are **not** in production, and the OLD-admin-migration work (October–November) is **not deployed**. Whether the September build's Laravel admin login is live is still unverified (P0-A §C.5-2).

### 13.2 Answers to the compatibility questions

| Question | Answer |
|---|---|
| Can a single deactivation-state table be read reliably by both systems? | **Yes, if it lives in `w2acp_db2025-2`.** Laravel reads it through `main` (= `w2acp_db2025-2` after the cutover). Gen 2 (`$w2adb`) and OLD (`$db`) read it through their **existing** connections and grants, as a same-schema table. No new credential, grant or connection |
| Can Gen 2 access a Laravel-owned table? | Only if that table is in `w2acp_db2025-2`. A table in `w2acp_laravel` is **not** reachable by Gen 2's user (`w2acp@localhost`, grants on `w2acp_db2025-2` only), so it would need a production grant change, and it would sit next to the wrong `nuke_authors` copy. **Rejected** |
| Would separate state tables create consistency risks? | **Yes, rejected.** Two states (for example one per schema or per system) means split-brain: a deactivation visible to one system and not the other, no common transaction, and a reconciliation job that is itself a failure point. Deactivation must have **one** authority |
| Can existing `nuke_authors` fields provide the enforcement signal without changing `uid`? | **Partly, and it is used as the second layer, not the authority.** No status column exists, but credential neutralisation (non-hash `password` marker, non-hex `pwd` marker, `API = NULL`) already: blocks every new password login (Laravel, Gen 2 incl. its plain-text branch); blocks the md5 pass-the-hash and all three backup APIs; and **with P0-A deployed, immediately ends every open Gen 2 AdminCP session**, because P0-A's `cv` is an HMAC of `password ?: pwd`. **Not sufficient alone:** it does not cover the `uid`-keyed OLD bridge or the Gen 2 forum-session hooks, it carries no who/when/why, and **any later password write would silently reactivate** the account. Hence an explicit state row is the authority and neutralisation is defence in depth |
| Cross-database transaction required? | **No**, provided Laravel `main` = `w2acp_db2025-2`. The state row, the `nuke_authors` neutralisation and the audit row are all one InnoDB transaction on `main`. If production `main` is **not** `w2acp_db2025-2` at B3 time, **B3 is blocked**: Laravel would deactivate a copy that Gen 2 and OLD never read. No cross-schema or XA design is proposed |

### 13.3 Identity preservation

- **Unchanged:** `nuke_authors.uid`, `id`, `aid`, `name` and `email`. Forum identity (`user.userid`), backup history (`uid`), Spatie (`model_id` = `id`), `admin_audit_log` (`actor_admin_id`) and `nuke_islamic_logs` (`aid`) all stay attached.
- **Disabled** (one transaction, §7.3 markers, never logged):
  - `password` = `!disabled:` + 48 random base62 characters;
  - `pwd` = a ≤ 40-character non-hex random marker;
  - `API` = NULL.
- **Spatie grants and `radminsuper` stay unchanged.** Deactivation is a state, not a privilege edit, so reactivation restores the same authority. Every enforcement point checks the state, so retained privileges are inert.

### 13.4 Recommended design (final)

**State location: one new table, `admin_account_states`, in `w2acp_db2025-2`.**
- Created by a Laravel migration on the `main` connection, with charset and collation **explicit** (`utf8mb4` / `utf8mb4_unicode_ci`), because the schema default is `latin1`.
- InnoDB, no foreign keys (the project's rule for the shared legacy schema), and a `NonEmptyTableGuard` on `down()`.

| Column | Type | Note |
|---|---|---|
| `admin_id` | INT UNSIGNED **PK** | `nuke_authors.id` |
| `deactivated_at` | INT UNSIGNED NULL | non-NULL means deactivated (the only enforcement field) |
| `deactivated_by` | INT UNSIGNED NULL | actor `nuke_authors.id`, or 0 for the CLI system actor |
| `reason` | VARCHAR(255) NULL | plain text, rendered escaped only |
| `reactivated_at` / `reactivated_by` | INT UNSIGNED NULL | last reactivation |
| `pending_action` | VARCHAR(16) NULL | `deactivate` or `reactivate`, while awaiting a second super-admin |
| `pending_requested_by` / `pending_requested_at` / `pending_expires_at` | INT UNSIGNED NULL | approval request (expiry 24 h, owner-tunable) |
| `updated_at` | INT UNSIGNED | — |

- **No row means active.** Rows are only ever written by Laravel actions or the CLI.
- **Approval requests live in the same row,** so no second table is needed.
- **Audit:** Laravel writes `admin_audit_log` on `main`. Its migration must therefore also run in production; that table **does not exist** there today (7 Ran).
  - Optionally mirror the rows to `nuke_islamic_logs` (the existing `LegacyAuditLogCompatibilityWriter`, default-off), so that Gen 2 and OLD readers see them too.
  - Never logged: markers, hashes or keys.

### 13.5 Enforcement per system

| System | Check | Effective | Change needed |
|---|---|---|---|
| **Laravel AdminCP: login** | `AdminGuard::resolveValidAdmin()`: refuse if a state row has `deactivated_at` (plus the markers already fail verification) | at the Laravel deploy | Laravel |
| **Laravel AdminCP: existing sessions** | `AdminGuard::user()` already re-reads `nuke_authors` per request (`find($id)`); add the state check, and on a hit, log out and invalidate | **next request** after the Laravel deploy | Laravel |
| **Laravel backup API / CLI `--actor` / `admin:sync-permissions`** | state check (`BackupApiAuthenticator` after the `uid` lookup; CLI refuses deactivated actors; sync skips them) | Laravel deploy; `API = NULL` already fails `apiKey != API` | Laravel |
| **Gen 2 AdminCP: login** | the markers fail every format branch, **including the plain-text branch** (random value) | **immediate** (data) | none |
| **Gen 2 AdminCP: existing sessions** | P0-A `cv` = HMAC(`password ?: pwd`) changes at neutralisation, so **every** session is rejected on its next request. **G1** adds an explicit state check in `w2a_admincp_actor()` (same SELECT, `LEFT JOIN admin_account_states`), so a later credential write cannot re-open access | **immediate with P0-A deployed**; G1 at the Gen 2 deploy | Gen 2 (G1) |
| **Gen 2 AdminCP editor** | **G2:** refuse password resets and permission edits for a deactivated target (a reset would write a valid `password` / `pwd`, reviving OLD's md5 paths) | Gen 2 deploy | Gen 2 (G2) |
| **Gen 2 public-page admin controls** | `functions.php is_w2a_admin()` (forum session → `uid`) needs the state check (**G3** = L3). `VBUserType()` grants `admin` to **any** forum id from a cookie (V4), so deactivation is meaningless there until **E2** requires a real, active `nuke_authors` match. The controls are links into OLD / Gen 2 admin endpoints, which enforce on their own (O1 / G1) | Gen 2 deploy | Gen 2 (G3, E2) |
| **OLD AdminCP** | **O1** (= L2): `mainfile.php is_w2a_admin()` adds the state check after the `uid` lookup and clears `$_SESSION['w2a']`. **O2:** remove the `admin.php:35` super-admin session shortcut (V9) | OLD deploy | OLD |
| **OLD bridge cookies (30 days)** | `uid`-keyed and **not** credential-bound, so they stay valid until **O1** ships. Stop-gap with no code: **rotate `W2A_BRIDGE_SECRET`** on both hosts at a deactivation (ends every admin's bridge) | O1 at the OLD deploy; rotation is immediate | OLD / ops |
| **OLD pass-the-hash `0000000dsak…admin.php`** | the `pwd` marker breaks it immediately, **but** `vb5.php:126` rewrites `pwd = md5(forum password)` on the next forum login. **O3:** deny the file at the web server (as for the P0-A backup editor); **O4:** skip the `pwd` rewrite for deactivated admins | O3 immediate once configured; O4 at the OLD deploy | ops / OLD |
| **Backup API (Laravel, Gen 2 `backup.php`, OLD `backup.php`)** | `API = NULL`: each requires a 32-character key equal to `API`, so all three fail immediately. Laravel adds the explicit state check | **immediate** (data) | none (Laravel optional) |
| **Forum (vBulletin)** | not an admin system; the forum account is deliberately untouched | — | — |

**Immediate on deactivation**, with only the Laravel deploy plus the already-prepared P0-A:
- all new logins (Laravel, Gen 2);
- Laravel sessions;
- **Gen 2 AdminCP sessions** (P0-A `cv`);
- all three backup APIs;
- OLD pass-the-hash (until a forum login rewrites `pwd`).

**Needs the coordinated legacy deployment:**
- OLD bridge cookies and OLD super-admin sessions (O1, O2; or bridge-secret rotation meanwhile);
- the Gen 2 forum-session hooks (G3, E2);
- protection against reactivation by credential write (G1, G2);
- the vb5 `pwd` revival (O3 or O4).

### 13.6 Super-admin safety across the topology

All of this runs on Laravel's `main` connection, against the single schema `w2acp_db2025-2`.

| Safeguard | Mechanism |
|---|---|
| Super-admin only | the Laravel action requires the Spatie `super-admin` role **and** the actor's `radminsuper = 1`, and that the actor is not deactivated (the legacy authority Gen 2 / OLD use) |
| No self-deactivation | refused (422), whatever the role |
| Last active super-admin | inside the transaction:<br>• **`SELECT … GET_LOCK('w2a_admin_state', 10)`** on `main`. This is a server-wide named lock, so it works across web nodes **without** a shared cache store; it replaces the cache-lock assumption;<br>• `SELECT … FOR UPDATE` on the state rows and on the `nuke_authors` rows with `radminsuper = 1`;<br>• refuse unless at least one **other** active admin remains who is super by **both** definitions (`radminsuper = 1` and the Spatie `super-admin` role).<br>The Spatie read uses the default connection. After the cutover that is the same schema but a separate connection, so it is serialised by the named lock rather than the row locks |
| Second-super-admin approval (super-admin target) | request: `pending_*` set, audited, expires after 24 h. A **different** active super-admin approves (no self-approval), and the approval executes the deactivation in the same locked transaction. Reactivation of a super-admin uses the same flow. Expired requests are inert; there is no background job, because expiry is checked on read |
| Audit | `admin_audit_log`: `deactivation_requested` / `_approved` / `_expired` / `deactivate_admin` / `reactivate_admin` / `emergency_reactivate`, with actor, target, reason and boolean legacy effects. Never markers, hashes or keys |
| Controlled reactivation | super-admin (plus the second approval for a super-admin target). It **requires setting a new password** (bcrypt in `password`; `pwd` stays the marker, so OLD's md5 paths stay dead); a new API key only on request, shown once; Spatie / `radminsuper` were never removed |
| Emergency recovery | `php artisan admin:reactivate --aid=… --reason=…` on the Laravel host (shell = trust anchor). It requires the target to be deactivated, applies the same transaction and audit (CLI system actor id 0), and prompts for a new password interactively (never in argv or logs). If Laravel is down: a documented, owner-run, single-statement runbook (`DELETE FROM admin_account_states WHERE admin_id = …` and a new bcrypt `password`), executed only under a recorded incident. This is the one manual-SQL exception, and it needs approval |

**Consequence of the topology:** every safeguard is single-database and transactional. Gen 2 and OLD only **read** the state; they never write it, so they need no locking logic.

### 13.7 Smallest safe implementation sequence

| Step | What | Who | Depends on | Rollback |
|---|---|---|---|---|
| **0** | Read-only verification (§13.8); P0-A deployed with its secret; rotate the 15 accounts | owner | — | — |
| **1** | Laravel migrations on `main`: `admin_audit_log` (exists in the repository, not in production) + **`admin_account_states`** (new, additive). Legacy code ignores both | project → owner deploy | step 0, C1 holds | tables stay (non-empty tables are never dropped); harmless |
| **2** | Laravel code:<br>• enforcement first (guard login and session, backup API, CLI, sync), a no-op while the table is empty;<br>• then the Deactivate / Reactivate / approve actions, UI and `admin:reactivate`;<br>• tests | project → owner deploy | step 1 | redeploy the previous build; deactivated admins **stay neutralised** (fail-safe), and reactivation is via CLI or a fresh deploy |
| **3** | **Enable** deactivation (feature flag / config) | owner | step 2 | flag off. Existing deactivations persist |
| **4** | Gen 2 patch **G1 + G2 (+ G3, E2)**: one indexed read; **tolerate a missing table** (treat it as active, because neutralisation still blocks logins and `cv` still ends sessions) | project → owner deploy | step 1 (the table exists) | revert the files: state checks disappear, and neutralisation and `cv` still hold |
| **5** | OLD patch **O1 + O2 (+ O4)**; **O3** web-server deny | owner (OLD changes need explicit approval) | step 1 | revert: bridge and shortcut re-open; use bridge-secret rotation meanwhile |
| **6** | Smoke test on production with a dedicated test admin account (owner): deactivate, check every path, reactivate | owner | steps 2–5 | reactivate |

Between steps 3 and 5, B3 guarantees "no new access anywhere; Laravel and Gen 2 AdminCP sessions cut; backup API cut". It does **not** cut OLD bridge cookies or OLD super-admin sessions, and the owner either accepts that window or rotates the bridge secret per deactivation.

**Changes in summary:**
- **Laravel:**
  - the migration (`admin_account_states`, explicit charset);
  - an `AdminAccountState` model on `main`;
  - Deactivate / Reactivate / Approve actions (transaction, `GET_LOCK`, `FOR UPDATE`, rules, audit, neutralisation);
  - guard, backup API, CLI and sync checks;
  - UI on the staff page;
  - the `admin:reactivate` command;
  - tests.
- **Gen 2:** G1 (actor state check), G2 (editor refuses deactivated targets), G3 / E2 (public hooks).
- **OLD:** O1 (bridge state check), O2 (remove the V9 shortcut), O4 (vb5 `pwd` rewrite skip). O3 is web-server only.
- **Database:** one new table (plus deploying the existing `admin_audit_log` migration). `nuke_authors` is unchanged in schema, with row-level writes only on deactivation and reactivation.

**Test strategy:**
- **Laravel:**
  - Pest, using the existing `MainSchema` fixtures, plus a fixture for the new table and a schema-drift test;
  - an env-gated MySQL / **MariaDB** suite for `GET_LOCK` / `FOR UPDATE` concurrency (mutual deactivation, last super-admin);
  - the approval-expiry rules.
- **Cross-system integration:** the P0-A-style disposable harness, extended so that Laravel (pointed at the disposable `p0a_main`) deactivates, and Gen 2 and OLD served from disposable docroots against the **same** database are checked:
  - login refused;
  - open Gen 2 session cut (`cv` and G1);
  - OLD bridge cookie refused (O1);
  - super-admin shortcut gone (O2);
  - backup API ×3 refused;
  - a credential reset of a deactivated admin refused (G2);
  - missing-table tolerance.
- **No production tests** other than the owner's step-6 smoke test.

### 13.8 Read-only verification before B3 (owner)

| # | Check | Pass |
|---|---|---|
| **C1** | Production Laravel `.env` / Tinker: `config('database.connections.main.database')` and `config('database.default')` plus its database | `main` = `w2acp_db2025-2` (the 2026-09-24 state). **Otherwise B3 stops** |
| C2 | `information_schema.TABLES` for `nuke_authors`, `nuke_islamic_logs` (and later `admin_account_states`, `admin_audit_log`) in `w2acp_db2025-2` | InnoDB |
| C3 | Is Laravel admin login live, and which build is deployed (`migrate:status`)? | known |
| C4 | Bridge secret set on both hosts (yes / no only); OLD host still served | known |
| C5 | Gen 2 public pages using `is_w2a_admin()` / `VBUserType()` still routed | known (decides G3 / E2 urgency) |
| C6 | `0000000dsak…admin.php` and `vb5.php` reachable on the OLD host | known (decides O3 / O4) |

### 13.9 Remaining owner decisions

1. **Confirm C1.** B3 depends on Laravel `main` = `w2acp_db2025-2`. Also confirm the P0-A results' topology note is superseded, and whether the default connection is meant to stay on `w2acp_db2025-2` (as reported) or move back to `w2acp_laravel` (the owner's earlier intent). Either is compatible, because the state lives on `main`.
2. **Approve the schema:** `admin_account_states` in `w2acp_db2025-2` via a Laravel `main` migration, and deploying the existing `admin_audit_log` migration.
3. **Approve the legacy patches:** G1, G2, G3 / E2 (Gen 2); O1, O2, O4 (OLD, which needs explicit OLD approval); the O3 web-server deny. Also who deploys OLD.
4. **The window** between steps 3 and 5: accept it, or rotate the bridge secret per deactivation.
5. **The second-super-admin approval flow** in the state row (24 h expiry), and the **dual super-admin definition** (`radminsuper` and Spatie) for the last-super-admin rule.
6. **Emergency recovery:** the `admin:reactivate` CLI, plus the single-statement break-glass runbook as the only manual-SQL exception.
7. **API key on reactivation:** new on request (recommended) or never.
8. **Optional:** mirror Laravel audit rows to `nuke_islamic_logs` for legacy readers.

**STOPPED. B3 is not implemented.**

