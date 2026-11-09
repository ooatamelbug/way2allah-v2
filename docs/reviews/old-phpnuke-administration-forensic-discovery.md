# OLD PHP-Nuke Administration Layer — Forensic Discovery (Pass 1)

**Status:** investigation only. No code, data, schema, route, view or configuration was modified in any of the three projects. All database access was `SELECT`/`SHOW`.

**Scope of this pass.** Phases A (architecture), B (inline admin mechanism), D (authentication bridge) and the security review are **evidence-complete**. Phase C (capability inventory) is complete **structurally** and detailed **for the Khotab domain only**. Phases E, F, G and H — the old→enhanced→Laravel mapping, duplicate-risk list, and wave proposal — are **deliberately not attempted in this pass**; see §16 for why and what they need. Producing them at the evidence standard this investigation demands requires tracing ~200 distinct operations through handler, SQL, permission and then cross-checking each against Laravel routes, controllers, tests and the decision log. Pass 1 establishes the architecture, proves the auth bridge, and fixes the method; guessing the mapping now would produce exactly the thin, unverified conclusions the brief forbids.

---

## 1. Executive Summary

`/mnt/Projects/Php/pure/old-project` is a **live, still-reachable PHP-Nuke installation** — 1,712 PHP files, 687 MB — that shares the production database and cookie domain with the enhanced legacy application. It is not an archive. It is Generation 1 of the Way2Allah administration stack, and it still owns administration behavior that Generation 2 (`7amlat/admincp`) never replaced.

Its administration surface has two distinct halves:

1. **A central admin area** — `admin.php` (configured as `$admin_file = "admin"`, `config-w2a.php:39`) dispatching to ~40 per-module `admin/` directories.
2. **Inline, context-sensitive controls injected into ordinary public pages.** When an authenticated administrator views a content page, the page renders extra toolbars and links. This is the mechanism the owner remembered, and it is real: `AdminPanel($id)` at `modules/Khotab/index.php:784`, plus roughly 100 further independent `if (is_w2a_admin($admin))` blocks scattered across 15 public modules.

Three findings dominate everything else:

- **The authentication bridge is an unsigned JSON cookie**, `w2a_user`, minted by the enhanced legacy AdminCP and trusted wholesale by old-project. vBulletin is *not* the admin bridge, contrary to the working assumption.
- **The old system's per-operation permission model does not execute.** `GreenZone($MyPerm)` — the function every old admin operation relies on for authorization — begins with `return;` (`mainfile.php:1855-1857`). Every statement after it is dead. There is no granular authorization in the old layer at all; the only gate is "is this visitor any administrator".
- **Every old administrative mutation is a `GET` link with no CSRF token.**

The practical consequence for migration: the old layer's *business capabilities* must be recovered, but its *authorization model* must not be, because there isn't one. Laravel's existing Spatie-based granular permissions are strictly stronger than what they would be "preserving".

---

## 2. Three-Generation Architecture

### Generation 1 — `/mnt/Projects/Php/pure/old-project`

PHP-Nuke derivative, heavily modified, running under a PHP 8.3 compatibility shim (`mainfile.php:2` → `php83_compatibility.php`).

| Element | Evidence |
|---|---|
| Bootstrap | `mainfile.php` (1,889 lines) → `config-w2a.php` (`:43`), `way2allah_functions.php` (`:62`), `includes/players.php` (`:65`), `db/db.php` (`:224`), `includes/sql_layer.php` (`:231`), `includes/ipban.php` (`:236`) |
| Admin entry | `admin.php` (9,480 bytes), `$admin_file = "admin"` (`config-w2a.php:39`) |
| Other admin entry points present | `admin2.php`, `admincp.php`, `0000000dsakjdlsatydrftasyfdasytdfsaytdfaadmin.php` (an obfuscated-name duplicate of `admin.php`), `w2a_upgrade/admin.php` |
| Central admin modules | `admin/modules/` — `authors, backup, blocks, comments, groups, ipban, messages, moderation, modules, newsletter, nukeSEO, optimize, referers, settings` |
| Per-module admin | ~40 `modules/*/admin/` directories |
| Public modules | 60+ under `modules/` |
| Blocks / themes | `blocks/`, `themes/` (classic PHP-Nuke block and theme system) |
| DB layer | ezSQL (`ez_sql_core.php`, `ez_sql_mysql.php`) plus PHP-Nuke's `sql_layer.php` |
| Table prefix | `nuke` — **the same prefix and the same database the enhanced legacy app and Laravel's `main` connection use** |

### Generation 2 — `/mnt/Projects/Php/pure/7amlat` (enhanced legacy)

The project already under migration. Its `admincp/` replaced *part* of Generation 1's administration and introduced the real permission model (`nuke_authors.permissions`, a serialized per-module/per-action map — see §11). It did **not** replace Generation 1 wholesale, and it actively keeps Generation 1 reachable by minting the cookies Generation 1 consumes (`admincp/index.php:210-224`).

### Generation 3 — Laravel

Owns the public site, substantial AdminCP functionality, the permission bridge and most content administration. Its relationship to Generation 1 is the open question this investigation exists to answer.

**What survived and why:** Generation 2 took over the domains it rebuilt and left the rest pointing at Generation 1. Because both generations read and write the same `nuke_*` tables under the same prefix, "leaving it" cost nothing operationally — an admin simply navigated from the new AdminCP into an old page and kept working. That is why Generation 1 is still live.

---

## 3. Authentication & Session Flow — PROVEN

### The bridge is the `w2a_user` cookie, not vBulletin

**Minting (Generation 2).** `7amlat/admincp/index.php:210-224`, on successful AdminCP login:

```php
$admin = base64_encode($aid . ':' . $PasswordHash . ':' . $admlanguage);
$cookie_domain = '.way2allah.com';
$cookieValue = json_encode($Author);          // the entire nuke_authors row

setcookie("admin",     $admin,            time() + 2592000, '/', $cookie_domain);
setcookie("w2a_user",  $cookieValue,      time() + 2592000, '/', $cookie_domain);
setcookie("newadmin",  $Author['uid'],    time() + 2592000, '/', $cookie_domain);
setcookie("aid",       $aid,              time() + 2592000, '/', $cookie_domain);
setcookie("pwd",       $PasswordHash,     time() + 2592000, '/', $cookie_domain);
setcookie("usertitle", $Author['name'],   time() + 2592000, '/', $cookie_domain);
setcookie("newlogin",  'OK',              time() + 2592000, '/', $cookie_domain);
```

Domain `.way2allah.com` and a 30-day lifetime: every subdomain receives these, which is precisely what makes the cross-generation hop work.

**Consumption (Generation 1).** `old-project/mainfile.php:377-423`, `is_w2a_admin($admin)`:

```php
if (!empty($_COOKIE['w2a_user'])) {
    $cookieUser = html_entity_decode($_COOKIE['w2a_user'], ENT_QUOTES, 'UTF-8');
    $Author = json_decode($cookieUser, true);
    if (is_array($Author) && !empty($Author['aid'])) {
        $_SESSION['w2a'] = new stdClass();
        $_SESSION['w2a']->admin    = (object) $Author;
        $_SESSION['w2a']->usertype = (!empty($Author['radminsuper']) && (int) $Author['radminsuper'] === 1)
                                     ? 'superadmin' : 'admin';
        $_SESSION['uid'] = (int) ($Author['uid'] ?? 0);
    }
}
$aid = $_SESSION['uid'];
if (!$aid) { return 0; }
$sql = "SELECT * FROM nuke_authors WHERE uid='$aid'";
...
if ($author['uid'] == $aid && !empty($author['uid'])
    && in_array($_SESSION['w2a']->usertype, ['superadmin','admin'])) {
    return 1;
}
return 0;
```

Three things to note, all load-bearing:

1. **The `$admin` parameter is never used.** Callers pass `$admin`, `''`, anything — the body ignores it entirely and reads cookies and session instead.
2. **The only database validation is existence.** `uid` must match a row in `nuke_authors`. No password, no hash, no token, no signature is verified against anything.
3. **`radminsuper` is read from the cookie, not from the row that was just fetched.** The `$author` row is loaded and then only its `uid` is used.

### Answers to the Phase D questions

| Question | Answer | Evidence |
|---|---|---|
| Does old-project authenticate independently? | **No.** It has a `login()` fallback (`admin.php:36`) but recognises an existing admin purely from cookies. | `mainfile.php:377-423` |
| Does it trust a PHP-Nuke `admin` cookie? | It is *set* (`admincp/index.php:218`) and `mainfile.php:146-166` still processes it, but `is_w2a_admin()` does not consult it. `admin.php:6` reads a third name, `$_COOKIE['admin_from_vb']`, and never uses it for the gate. | `admin.php:6`, `mainfile.php:162-166` |
| Does it derive identity from vBulletin? | **Not for admin identity.** `VB5()` (`mainfile.php:424+`) resolves the *forum* user from `$_COOKIE['bbsessionhash']` against `nuke_session`/`nuke_user`/`nuke_userfield`. It is used for public-user features and, separately, by `GreenZone()` — which does not execute. | `mainfile.php:424-470`, `admin.php:16`, `header.php:16` |
| Does Generation 2 create something Generation 1 consumes? | **Yes — the `w2a_user` cookie.** This is the entire bridge. | `admincp/index.php:218` ↔ `mainfile.php:390` |
| Shared DB/cookies? | Both. Same `nuke` prefix, same database, same `.way2allah.com` cookie scope. | `config-w2a.php`, `w2a_config.php` |
| Which IDs are involved? | `nuke_authors.uid` (forum user id — the session key), `nuke_authors.aid` (admin username, used for display and by the dead `GreenZone`), `nuke_authors.id` (surrogate PK, unused by the bridge), vBulletin `userid` (separate path via `VB5()`) | `mainfile.php:390-410`, `admin.php:17` |
| How are permissions resolved? | **They are not.** See §11 and the `GreenZone` finding. | `mainfile.php:1855-1857` |
| How is super-admin resolved? | From the **cookie's** `radminsuper` field. Separately, `7amlat/w2a_config.php:16` hardcodes `$SuperAdmins = array(1, 16715)`. | `mainfile.php:403`, `w2a_config.php:16` |
| What changes after authentication? | Inline toolbars and ~100 admin-only blocks appear on public pages; `admin.php` stops redirecting to `login()`. | §4 |

### Flow diagram

```
Administrator logs into ENHANCED LEGACY AdminCP
  7amlat/admincp/index.php  (credential check against nuke_authors)
        │
        ├─ setcookie admin, w2a_user, newadmin, aid, pwd, usertitle, newlogin
        │  domain .way2allah.com, 30 days          [admincp/index.php:210-224]
        │  w2a_user = json_encode(entire nuke_authors row)   ← THE BRIDGE
        ▼
Administrator navigates to any OLD-PROJECT page
  old-project/<module or admin.php>
        │
        ├─ require mainfile.php                              [admin.php:5]
        │     └─ config-w2a.php, db/db.php, sql_layer.php    [:43,:224,:231]
        ▼
  is_w2a_admin($ignored)                                     [mainfile.php:377]
        ├─ read $_COOKIE['w2a_user'] → json_decode
        ├─ $_SESSION['w2a']->admin    = (object) $Author
        ├─ $_SESSION['w2a']->usertype = cookie radminspuper ? superadmin : admin
        ├─ $_SESSION['uid']           = (int) $Author['uid']
        ├─ SELECT * FROM nuke_authors WHERE uid='$aid'   (existence only)
        └─ return 1 if uid exists AND usertype ∈ {superadmin, admin}
        ▼
   ┌──────────────────────────────┬─────────────────────────────────┐
   │ PUBLIC PAGE PATH             │ CENTRAL ADMIN PATH              │
   │ modules/<X>/index.php        │ admin.php                       │
   │  if (is_w2a_admin($admin)) { │  if (!is_w2a_admin('') && ...)  │
   │     AdminPanel($id);  ...    │       login();      [admin.php:35]
   │  }                           │  → modules/<X>/admin/index.php  │
   │  emits GET links to          │  → switch($op) { ~95 Khotab ops }│
   │  admin.php?op=…&id=…         │  → GreenZone('perm')  ← NO-OP    │
   └──────────────┬───────────────┴─────────────────┬───────────────┘
                  └──────────────┬──────────────────┘
                                 ▼
                      SQL mutation on shared nuke_* tables
                      (same rows Laravel's `main` connection serves)
```

---

## 4. Inline Admin Panel Mechanism

There is **not one generic mechanism — there are two, plus a long tail.**

### 4.1 `AdminPanel($id)` — the content toolbar

| Property | Value |
|---|---|
| Definition | `modules/Khotab/index.php:784`; duplicates at `modules/index.php:709` and `modules/Khotab/_index.php:714` |
| Caller | `modules/Khotab/index.php:1026`, inside `if (is_w2a_admin($admin)) {` at `:1022` |
| Authentication condition | `is_w2a_admin($admin)` — any administrator (§3) |
| Authorization condition | **None.** No per-action check before rendering, and none in the handlers (`GreenZone` is a no-op) |
| Context supplied | A single `$id`; the function re-reads the row itself: `SELECT * FROM nuke_islamic_khotab WHERE id='$id'` (`$id` is `intval()`-cast at `:786`) |
| Output | A `<div class="block">` grid of icon links, Arabic labels, conditional on `banner`, `pdf`, `docx`, `advfile` column values |
| Link form | `GET` → `admin.php?op=<Operation>&id=<id>` |
| Tables read | `nuke_islamic_khotab` |

Actions emitted, in source order:

| Label (ar) | Operation | Meaning |
|---|---|---|
| تعديل | `KhotabEditKhotab` | edit item |
| نقل | `KhotabMoveKhotab` | move item |
| مسح | `KhotabDeleteKhotab` | **delete item** |
| إضافة تصنيف | `cat_add_to_item` | attach category |
| إضافة وصلة | `KhotabInsertMirror` | add mirror link |
| رفع بانر / تعديل البانر | `KhotabDetailes` | upload / edit banner (conditional on `banner == 0`) |
| حذف البانر | `KhotabDelete_banner` | **delete banner** |
| رفع التفريغ | `KhotabUploadPdf` | upload transcript PDF (if `pdf == 0`) |
| تعديل التفريغ | `Khotab_EditPdf` | edit transcript |
| حذف التفريغ | `KhotabDeletePdf` | **delete transcript** |
| رفع الوورد | `KhotabUploaddocx` | upload Word transcript (if `docx == 0`) |
| تعديل الوورد | `Khotab_Editdocx` | edit Word transcript |
| حذف الوورد | `KhotabDeletedocx` | **delete Word transcript** |
| تنزيل الوورد | direct link `media/docx/{floor(id/1000)}/{id}.docx` | download Word file |
| طلب رئيسية | `KhotabRequestAdvFile` | request homepage feature (if `advfile == 0`) |
| طلب بانر | `KhotabRequestBanner` | request banner (if `banner == 0`) |
| اضافة للشير | `KhotabWaitingFB` | queue for Facebook share; passes `&link=curPageURL()` |
| (fancybox) | `get_gif_jpg.php?id=` | generate GIF/JPG preview |

### 4.2 `is_w2a_admin()` guard blocks — the long tail

`AdminPanel()` is only the most visible mechanism. Independent admin-only blocks are scattered through public module code, each with its own markup and links:

| Public module | `is_w2a_admin()` occurrences |
|---|---|
| `modules/Khotab/index.php` | **21** |
| `modules/Fatwa/index.php` | 12 |
| `modules/Anasheed/index.php` | 9 |
| `modules/Telawah/index.php` | 7 |
| `modules/Mobile/index.php` | 7 |
| `modules/Locations/index.php` | 7 |
| `modules/Satellite/index.php` | 6 |
| `modules/w2acd/index.php` | 5 |
| `modules/advfiles/index.php` | 5 |
| `modules/search/index.php` | 4 |
| `modules/Surveys/index.php` | 3 |
| `modules/Cat/index.php` | 3 |
| `modules/advanced_search/index.php` | 3 |
| `modules/Your_Account/index.php` | 2 |

In Khotab alone, only 2 of the 21 guards relate to `AdminPanel`; the other 19 wrap separate inline features (`AdvancedStats($Khotab['id'])` at `:1032`, group/series controls at `:590`, listing controls at `:376`, and others). **Any migration that reproduces only `AdminPanel` would miss the large majority of the inline surface.**

### 4.3 Execution chain (real, not illustrative)

```
GET /modules.php?name=Khotab&id=12345        (public content page)
  → mainfile.php                              bootstrap, cookies, DB
  → is_w2a_admin($admin)                      mainfile.php:377  → 1
  → modules/Khotab/index.php:1022              if (is_w2a_admin($admin))
  → AdminPanel(12345)                          :1026 → :784
  → SELECT * FROM nuke_islamic_khotab WHERE id='12345'
  → emits  <a href="admin.php?op=KhotabDeleteKhotab&id=12345">مسح</a>
  → admin.php                                  :5 require mainfile.php
  → admin.php:35                               if (!is_w2a_admin('') …) login();
  → modules/Khotab/admin/index.php             switch ($op)
  → case 'KhotabDeleteKhotab'                  confirmation screen
  → case 'KhotabConfirmDeleteKhotab'           SQL mutation
  → DELETE/UPDATE on nuke_islamic_khotab (+ dependent tables)
  → redirect
```

---

## 5. Old Admin Capability Inventory

### 5.1 Structural totals (complete)

| Measure | Count |
|---|---|
| PHP files in old-project | 1,712 |
| Public modules under `modules/` | 60+ |
| Modules with an `admin/` subdirectory | **~40** |
| Central admin modules under `admin/modules/` | 14 |
| `case '<op>'` handlers outside Khotab | **103** |
| `case '<op>'` handlers in `modules/Khotab/admin/index.php` | **~95** |
| Public modules carrying inline admin guards | 14 |

Modules with an `admin/` directory: `ads, advfiles, AlFatawa, Anasheed, BackUp, banners_ads, Cat, cd, chat_server, Classificate_anasheed, contact, default_picasa_album, docx, Dump_files, FAQ, Fatwa, fb_share_list, Feedback, flashxml, hedaya_table, help, high_rank, home_files, Khotab, live, Mobile, Montada, new_album, questionnaire, Quiz, ramadan_competition, report_ads, Satellite, Surveys, Telawah, Tutorials, update_khotab_count, w2a_cd, w2acd, Your_Account` — plus a `Cat - Copy` duplicate directory.

Central admin modules: `authors, backup, blocks, comments, groups, ipban, messages, moderation, modules, newsletter, nukeSEO, optimize, referers, settings`.

### 5.2 Khotab domain — detailed (the ~95 operations)

Grouped by capability. **Permission for every single one is `is_w2a_admin()` only** — see §11.

| Capability group | Operations | UI type |
|---|---|---|
| Item CRUD | `KhotabInsertKhotab`, `KhotabConfirmInsertKhotab`, `KhotabEditKhotab`, `KhotabConfirmEditKhotab`, `KhotabDeleteKhotab`, `KhotabConfirmDeleteKhotab`, `KhotabMoveKhotab`, `KhotabCopyKhotab`, `KhotabUpKhotab`, `KhotabDetailes` | inline + central |
| Mirrors | `KhotabInsertMirror`, `KhotabConfirmInsertMirror`, `KhotabEditMirror`, `KhotabConfirmEditMirror`, `KhotabDeleteMirror`, `KhotabConfirmDeleteMirror`, `KhotabMoveMirror`, `MirrorExport` | inline + central |
| Series | `KhotabInsertSeries`, `KhotabConfirmInsertSeries`, `KhotabEditSeries`, `KhotabConfirmEditSeries`, `KhotabDeleteSeries`, `KhotabConfirmDeleteSeries`, `KhotabUpSeries` | central |
| Groups | `KhotabAddGroup`, `KhotabConfirmAddGroup`, `KhotabEditGroup`, `KhotabConfirmEditGroup`, `KhotabDeleteGroup`, `KhotabConfirmDeleteGroup`, `GroupLists` | central |
| Authors | `KhotabInsertAuthor`, `KhotabConfirmInsertAuthor`, `KhotabEditAuthor`, `KhotabConfirmEditAuthor`, `KhotabUploadAuthorImage`, `KhotabDeleteAuthor`, `KhotabConfirmDeleteAuthor` | central |
| Categories | `KhotabCatList`, `KhotabEditCat`, `cat_add_to_item` | inline + central |
| Transcript PDF (التفريغ) | `KhotabUploadPdf`, `KhotabConfirmUploadPdf`, `Khotab_EditPdf`, `KhotabDeletePdf`, `confirm_delete_pdf` | inline + central |
| Word transcript (docx) | `KhotabUploaddocx`, `KhotabConfirmUploaddocx`, `Khotab_Editdocx`, `KhotabDeletedocx`, `confirm_delete_docx` | inline + central |
| Banners | `KhotabUploadBanner`, `KhotabRequestBanner`, `KhotabDelete_banner`, `confirm_delete_banner` | inline + central |
| Homepage feature | `KhotabRequestAdvFile` | inline |
| Comments | `KhotabAddComments`, `KhotabAddComment`, `KhotabDelComment`, `KhotabAddModComment` | central |
| Moderators | `KhotabListModertors`, `KhotabConfirmEditMod`, `KhotabModertorsLogs`, `KhotabClearModertorsLogs` | central |
| Ordering / arrangement | `KhotabOrder`, `KhotabReArrange`, `KhotabConfirmReArrange`, `KhotabJoin`, `KhotabConfirmJoin` | central |
| Exports | `KhotabExport`, `KhotabExportAnasheed`, `KhotabExportTelawah`, `KhotabExportMirror` | central |
| Maintenance / QA | `KhotabRepair`, `KhotabCount`, `KhotabLostedLink`, `KhotabLeechImages` | central |
| Facebook share queue | `KhotabWaitingFB`, `KhotabConfirmAddToShare`, `KhotabWaitingList` | inline + central |
| Listing filters | `lowsize`, `highsize`, `hidden`, `invalid_link`, `broken_link`, `un_cat`, `video`, `audio`, `autorename` | central |
| AJAX helpers | `KhotabAjaxGroupsSeries`, `KhotabAjaxSeries`, `KhotabLists` | central |
| Entry points | `Khotab`, `KhotabMain` | central |

The remaining ~39 admin modules are **not yet enumerated at operation level** — that is pass-2 work (§16).

---

## 11. Permissions Mapping — the decisive finding

### Generation 1: no effective permission model

`old-project/mainfile.php:1855`:

```php
function GreenZone($MyPerm)
{
    return;                                    // ← everything below is dead code
    $Calls = debug_backtrace();
    ...
    $result = $db->sql_query("SELECT $MyPerm FROM " . $prefix . $mytable . " WHERE aid='$aid'");
    ...
    if ($Allowed == 1 or $row[radminsuper] == 1) { return; }
    eval(IncMe("not_allowed.htm"));
    exit;
}
```

`GreenZone()` was the per-operation gate, designed to read a module-specific moderator table (`nuke_islamic_mod` for Khotab, `nuke_anasheed_mod`, `nuke_islamic_cat_mod`, `nuke_telawah_mod`) keyed on `aid`. **The unconditional `return;` on the first line disables it completely.** Every `GreenZone('…')` call site in old-project is therefore a no-op, and the moderator tables it consulted are not consulted.

Independent corroboration from the enhanced legacy side: `7amlat/backup.php:200` carries `//GreenZone("downloader");` — commented out — showing the same function was being abandoned there too.

**Effective old-project authorization: `is_w2a_admin()` and nothing else.** Any administrator, super or not, can invoke any of the ~200 operations.

### Generation 2: a real model

`nuke_authors.permissions` holds a PHP-serialized per-module/per-action map. Verified against the local database for two live accounts:

```
uid 16715 → 9 modules;  backup → { backupkhotabmirror: on }
uid 56540 → 11 modules; backup → { backupkhotab, backuptelawah, backupanasheed, backupanasheedmirror: on }
```

`7amlat/backup.php:98-102` reads these keys into mode flags and `:448` passes them as the gate that decides which content tables an operator is served — a genuinely enforced, granular model.

### Generation 3: Laravel

Spatie permissions, seeded per module, enforced in middleware/policies.

**Semantic mismatch to record:** there is no old→new permission mapping to perform, because Generation 1 enforces nothing. Any attempt to "preserve old permission behavior" would mean *removing* authorization. The correct migration posture is: recover Generation 1's **capabilities**, authorize them with Generation 2's or Laravel's model, and treat the absence of checks as a defect that must not travel.

---

## 13. Security Findings

Marked, not fixed. Each is a reason to migrate the **behavior** and discard the **implementation**.

| # | Finding | Evidence | Severity |
|---|---|---|---|
| S-1 | **Unsigned, client-controlled admin credential cookie.** `w2a_user` is plain `json_encode()` of a `nuke_authors` row, with no signature, HMAC or encryption, on `.way2allah.com` for 30 days. `is_w2a_admin()` validates only that `uid` exists in `nuke_authors`. No password, hash or token is checked. Anyone who knows any administrator's forum `uid` can forge full admin access to old-project. | `admincp/index.php:218`, `mainfile.php:390-420` | **Critical** |
| S-2 | **Privilege escalation to super-admin via the same cookie.** `usertype` is computed from the cookie's own `radminsuper` field, not from the `nuke_authors` row that `is_w2a_admin()` then fetches. `{"aid":"x","uid":<any admin uid>,"radminsuper":1}` yields `superadmin`. | `mainfile.php:403` vs `:412-416` | **Critical** |
| S-3 | **All per-operation authorization disabled.** `GreenZone()` returns immediately; ~200 operations run with no granular check. | `mainfile.php:1855-1857` | **Critical** |
| S-4 | **State-changing operations performed via `GET`, with no CSRF token.** `admin.php?op=KhotabDeleteKhotab&id=…`, `…op=KhotabDeletePdf…`, `…op=KhotabDelete_banner…` and every other mutation is a plain link. | `modules/Khotab/index.php:784-860` | **High** |
| S-5 | **SQL injection in the dead permission path, reachable if ever re-enabled.** `GreenZone()` interpolates `$aid = $w2a_user['username']` — a vBulletin-supplied username — and `$MyPerm` directly into SQL. | `mainfile.php:1877-1882` | High (latent) |
| S-6 | **Authorization by URL blacklist.** `admin.php:23-27` rejects requests whose URI contains `AddAuthor`, `UpdateAuthor`, their base64 forms, `?admin` or `&admin`. A denylist standing in for access control. | `admin.php:23-27` | Medium |
| S-7 | **Multiple competing admin entry points**, including an obfuscated-filename duplicate of `admin.php` (`0000000dsakjdlsatydrftasyfdasytdfsaytdfaadmin.php`), plus `admin2.php`, `admincp.php`, `w2a_upgrade/admin.php`. Security by obscurity; each is a separate attack surface with its own copy of `adminmenu()`. | directory listing; `grep` for `function adminmenu` | Medium |
| S-8 | **Three different admin cookie names in play** — `admin`, `admin_from_vb` (`admin.php:6`), `newadmin` — with the gate depending on none of them. Confusing, and a sign the trust model drifted. | `admin.php:6`, `admincp/index.php:220` | Low |
| S-9 | **Variable-name collision that obscures the trust boundary.** `$w2a_user` (PHP variable) is the *vBulletin forum user array* from `VB5()`, while `$_COOKIE['w2a_user']` is the *admin identity JSON*. Same name, different trust levels, both in scope in `admin.php`. | `admin.php:16` vs `mainfile.php:390` | Low (clarity) |
| S-10 | **Destructive operations with a two-step but unauthenticated confirmation.** The `…Delete…` / `…ConfirmDelete…` pairs provide a confirmation screen, but the confirm step is itself a `GET` with no token, so the screen is bypassable. | Khotab op list, §5.2 | High |

---

## 12. Database / Table Mapping (first pass)

Tables confirmed touched by Generation 1 administration so far. All are in the **same database and prefix** that Laravel's `main` connection uses, so every Generation 1 mutation lands on rows Laravel serves.

| Table | Touched by | Laravel ownership |
|---|---|---|
| `nuke_islamic_khotab` | `AdminPanel()` read; item/mirror/pdf/docx/banner ops | Laravel `KhotabItem` model exists; `main` connection mapped |
| `nuke_islamic_mirror` | mirror ops | Laravel `Mirror` model exists |
| `nuke_islamic_series` | series ops | Laravel `Series` model exists |
| `nuke_islamic_groups` | group ops | Laravel `KhotabGroup` model exists |
| `nuke_islamic_authors` | author ops | Laravel `Author` model exists |
| `khotab_category_index` | `cat_add_to_item`, category ops | used by Laravel listing services |
| `nuke_w2a_cat` | category ops | Laravel `Category` model exists |
| `nuke_authors` | identity, `admin/modules/authors.php` | Laravel `AdminUser` model exists |
| `nuke_islamic_mod`, `nuke_anasheed_mod`, `nuke_islamic_cat_mod`, `nuke_telawah_mod` | `GreenZone()` — **never actually read** | no Laravel equivalent; likely retired |
| `nuke_islamic_advanced` | media metadata | Laravel reads it in listing services |
| `nuke_session`, `nuke_user`, `nuke_userfield` | `VB5()` forum identity | vBulletin-owned |

Per-operation table mapping for the remaining ~39 modules is pass-2 work.

---

## 16. Why Phases E–H are deferred, and what pass 2 needs

The brief requires, for every old capability, a verified mapping across three generations with at least two independent evidence sources each. The real surface is **~95 Khotab operations plus 103 operations across ~39 other admin modules, plus ~100 inline guard blocks in 14 public modules** — on the order of 200 distinct administrative behaviors. Each needs handler reading, SQL extraction, side-effect identification, then a Laravel cross-check against routes, controllers, Actions, FormRequests, models, permissions, tests and `docs/decision-log.md`.

Pass 1 deliberately stops here because the alternative is asserting mappings I have not verified, and the brief is explicit that filename similarity is not evidence of equivalence. What pass 1 does deliver is the part that everything else depends on: **the architecture, the proven auth bridge, the inline mechanism, and the discovery that the old permission model does not execute** — the last of which changes the migration posture for every capability.

**Pass 2, per domain** (recommended order by inline-guard density, i.e. by how much administration actually happened there): Khotab → Fatwa → Anasheed → Telawah → Mobile → Locations → Satellite → w2acd → advfiles → the remainder.

For each operation, pass 2 records: op name → handler file:line → SQL → tables → side effects (filesystem, cache, external) → Laravel equivalent (route/controller/action/test) → classification (`ALREADY_MIGRATED` / `PARTIALLY_MIGRATED` / `NOT_MIGRATED` / `RETIRED_CONFIRMED` / `SOURCE_UNRECOVERABLE` / `NEEDS_BUSINESS_CONFIRMATION`).

### Candidate gaps worth testing first in pass 2

Flagged as **hypotheses to verify, not findings** — these are capabilities visible in the old layer whose Laravel counterpart I have not yet looked for:

- Transcript management — PDF (التفريغ) and Word/docx upload, edit, delete, download
- Banner upload / delete / **request** workflow (`KhotabRequestBanner`)
- Homepage-feature request workflow (`KhotabRequestAdvFile`)
- Facebook share queue (`KhotabWaitingFB`, `KhotabWaitingList`, `KhotabConfirmAddToShare`)
- Moderator management and moderator activity logs (`KhotabListModertors`, `KhotabModertorsLogs`, `KhotabClearModertorsLogs`)
- Content QA filters (`lowsize`, `highsize`, `invalid_link`, `broken_link`, `un_cat`)
- Maintenance operations (`KhotabRepair`, `KhotabCount`, `KhotabLostedLink`, `KhotabLeechImages`, `autorename`)
- Export operations (`KhotabExport`, `…Anasheed`, `…Telawah`, `…Mirror`, `MirrorExport`)
- Item `Join` and `ReArrange` operations
- `get_gif_jpg.php` preview generation

### Security items that should not wait for pass 2

S-1, S-2 and S-3 are not migration findings — they are live production exposures in a currently reachable application sharing the production database. They are reported here, unfixed per instruction, but they warrant a decision independent of the migration timetable.

---

## 14. Business Confirmation Questions

Only questions source and history cannot answer. Deliberately short — the rest must wait until pass 2 establishes what is genuinely missing.

1. **Is old-project still reachable in production, and by whom?** Everything in §13 depends on it. If it is already firewalled or offline, S-1/S-2 change severity entirely.
2. **Are administrators still using old-project today?** `nuke_authors.permissions` shows a live Generation 2 model, but nothing in the code proves current Generation 1 usage. Web-server access logs for `old-project` paths would settle it, and would also tell pass 2 which domains to prioritise by real usage rather than by guard density.
3. **Do the "request" workflows still have business meaning?** `KhotabRequestBanner` and `KhotabRequestAdvFile` look like queues feeding a human design/editorial process. Whether that process still exists is not determinable from source.
4. **Is the Facebook share queue still operated?** It is clearly built as a workflow (`KhotabWaitingFB` → `KhotabWaitingList` → `KhotabConfirmAddToShare`), but whether the downstream posting still runs is a business fact.
