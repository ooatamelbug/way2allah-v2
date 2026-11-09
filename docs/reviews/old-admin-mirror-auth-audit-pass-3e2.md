# Pass 3E.2 — Mirror Administration Authorization & Audit Contract

**Status:** ANALYSIS ONLY — no implementation, no deployment, no commit, no production modification.
**Date:** 2026-10-12
**Predecessor:** Pass 3E.1, **CLOSED**. Not reopened — no concrete dependency requiring it was found.
**Unit under preparation:** Khotab insert / edit / delete / re-parent; Anasheed insert / edit / delete. **7 operations, 2 domains.**
**Method rule carried forward from 3E.1:** source existence proves nothing about production use.

---

## 1. Phase A — source reconstruction

### 1.1 `GreenZone()` — complete trace

One definition only: `old-project/mainfile.php:1915-1948`.

```php
function GreenZone($MyPerm)
{
    return;                                    // ← line 1917: dead from here down
    $Calls = debug_backtrace();
    foreach ($Calls as &$call) {
        if ($call['function'] == 'GreenZone') {
            $currentmodule = explode('/', $call['file']);
            $currentmodule = $currentmodule[5];
            if      ($currentmodule == 'Khotab')   { $mytable = '_islamic_mod'; }
            elseif  ($currentmodule == 'Anasheed') { $mytable = '_anasheed_mod'; }
            elseif  ($currentmodule == 'Cat')      { $mytable = '_islamic_cat_mod'; }
            elseif  ($currentmodule == 'Telawah')  { $mytable = '_telawah_mod'; }
            else    { /* die(...) commented out */ }
        }
    }
    global $w2a_user, $prefix, $db;
    $aid = $w2a_user['username'];
    $result  = $db->sql_query("SELECT $MyPerm FROM " . $prefix . $mytable . " WHERE aid='$aid'");
    $row     = $db->sql_fetchrow($result);
    $Allowed = $row[$MyPerm];
    $result  = $db->sql_query("SELECT pwd, radminsuper FROM " . $prefix . "_authors WHERE aid='$aid'");
    $row     = $db->sql_fetchrow($result);
    if ($Allowed == 1 or $row[radminsuper] == 1) { return; }
    eval(IncMe("not_allowed.htm"));
    exit;
}
```

| Aspect | Historical intent | Currently executable |
|---|---|---|
| Entry | — | **`return;` on the first line. Nothing below ever runs.** |
| Early return / disabled branch | the `return;` at `:1917`; also a commented-out `die()` in the module `else` branch | n/a |
| Tables read | `nuke_islamic_mod` (Khotab), `nuke_anasheed_mod` (Anasheed), `nuke_islamic_cat_mod` (Cat), `nuke_telawah_mod` (Telawah), plus `nuke_authors` | **none** |
| Module resolution | `debug_backtrace()` → `explode('/', $call['file'])[5]` — the module name at **filesystem path segment index 5**, so it depends on absolute deployment depth. An unmapped module leaves `$mytable` **undefined**, producing a malformed table name. | n/a |
| Admin identity | `$aid = $w2a_user['username']` | n/a |
| Token lookup semantics | **`$MyPerm` is interpolated as a COLUMN NAME**: `SELECT $MyPerm FROM …_mod WHERE aid='$aid'`. Grants are therefore **one boolean column per capability**, not rows. | n/a |
| Superadmin bypass | **Yes** — `nuke_authors.radminsuper == 1` returns unconditionally | n/a |
| Category / group / series / item scoping | **None.** The grant is per-admin per-module, global within the module. `_islamic_cat_mod`'s name hints at category scope for the Cat module, but the lookup is still `WHERE aid=`. | n/a |
| No grant exists | `$Allowed` is NULL → not `1` → `eval(IncMe("not_allowed.htm")); exit;` — **fail-closed** | n/a |
| Read / write / destructive distinction | Only through separate token names (`addmirror` vs `editmirror` vs `delmirror`). No structural distinction; `GreenZone()` is only ever called on mutation and mutation-form handlers. | n/a |

**Two hazards if it were ever re-enabled** — recorded, not acted on: `$MyPerm` reaches SQL unsanitized (safe today only because every call site passes a literal), and the path-index-5 module resolution is deployment-path-dependent. A third: `$w2a_user['username']` no longer exists in the Workstream C HMAC bridge format, which carries only `uid.issued_at.hmac`.

**Do not copy this enforcement literally.** It is recorded as specification, not as code to port.

### 1.2 `AvAdmin()` — a LIVE authorization layer, and the pass's most consequential finding

`modules/Khotab/admin/index.php:3949-3991`. **It has no early return.** It is called immediately *before* `GreenZone()` in four of the seven operations.

```php
function AvAdmin($Vedio)
{
    global $w2a_user, $aid, $admin, $prefix, $db;
    if ($aid != "") {
        $db->sql_query("SELECT pwd, radminsuper FROM {$prefix}_authors WHERE aid='$aid'");  // result discarded
        if (!empty($_SESSION['w2a']->usertype) && $_SESSION['w2a']->usertype === 'superadmin') {
            return;                                                      // superadmin bypass
        }
        $aid = substr("$aid", 0, 150);
        $row = $db->sql_fetchrow($db->sql_query(
            "SELECT audio, vedio FROM {$prefix}_islamic_mod WHERE aid='$aid'"));
        if ($Vedio == 1) { if ($row['vedio'] == 1) { return; } }
        else             { if ($row['audio'] == 1) { return; } }
    }
    $Content = ($Vedio == 1) ? "غير مصرح لك بدخول قسم المرئيات" : "غير مصرح لك بدخول قسم الصوتيات";
    eval(IncMe("not_allowed.htm"));
    exit;
}
```

- **`nuke_islamic_mod` IS read by live code today** — for the `audio` / `vedio` media-type scope, not for the mirror tokens.
- **Strictly fail-closed.** The only allowing paths are the superadmin session or a matching `audio`/`vedio` grant. An empty `$aid` falls through to the deny block, which sits *outside* the `if ($aid != "")`.
- **Identity is live.** `admin.php:17` sets `$aid = !empty($_SESSION['w2a']) ? str_replace('+',' ',$_SESSION['w2a']->admin->name) : ''`, and `admin.php` is the entry point for every `op=` mirror operation. The `$aid = $w2a_user['username']` assignment at `mainfile.php:1937` is inside dead `GreenZone` code and is not the source.
- The `radminsuper` query at `:3955` is executed and its result **discarded** — superadmin is decided from the session instead. Vestigial.
- **Anasheed has no `AvAdmin`.** `grep -c 'AvAdmin' modules/Anasheed/admin/index.php` returns **0**.

So the grant table is **not purely historical**, and the first unit must consciously preserve or replace a live check — not merely resurrect a dead one.

### 1.3 Moderator grant tables

| | `nuke_islamic_mod` (Khotab) | `nuke_anasheed_mod` (Anasheed) |
|---|---|---|
| Grant key / identity key | `aid` — the admin **username string** | `aid` |
| Surrogate key | `id` | `id` |
| Content scope key | **none** | **none** |
| Permission representation | **one boolean column per capability**, written `'1'` / `'0'` | same |
| Authoritative token vocabulary | **the table's own column list minus `id` and `aid`** (see §1.4) | same |
| Columns read by live code | `audio`, `vedio` (via `AvAdmin`) | **none — no live reader exists** |
| Columns read by dead code | `$MyPerm` (any) via `GreenZone` | `$MyPerm` via `GreenZone` |
| Create grant | `INSERT … SET aid='…', active=1` (`Khotab/admin:4022`) | `:2084` |
| Update grant | `UPDATE … SET active=1 WHERE aid='…'` (`:4019`); permission columns via `:4063` | `:2082`, `:2129` |
| Delete grant | `UPDATE … SET active=0` for all, then `DELETE … WHERE active=0` (`:3998`, `:4027`) | `:2064`, `:2088` |
| Management UI | **yes** — `addmods`-gated flow + `ListModertors()` | yes |
| Rename / maintenance | **Generation 2's AdminCP maintains it** — `admincp/index.php:~440-500` updates `nuke_islamic_mod`, `nuke_anasheed_mod`, `nuke_telawah_mod`, `nuke_w2acd_mod` and `nuke_islamic_logs` on admin rename (Pass 3D §8.3) | same |
| Khotab vs Anasheed semantics | identical mechanism; **different column sets** — Khotab's `$Trans` includes `audio` / `vedio`, Anasheed's does not | — |

The permission-column writer (`Khotab/admin:4044-4063`) iterates the fetched row and, for each key that is not `id` or `aid`, writes `'1'` when the submitted checkbox is `"on"` and `'0'` otherwise:

```php
foreach ($Mods as $key => $value) {
    if ($NumArray == 0) {
        if ($key=='id' OR $key=='aid') { } else {
            $sql .= ($Moderator[$key] == "on") ? " $key = '1'," : " $key = '0',";
        }
        $NumArray = 1;
    } else { $NumArray = 0; }
}
$db->sql_query("UPDATE {$prefix}_islamic_mod SET $sql id = {$Moderator['id']} WHERE id = {$Moderator['id']}");
```

The `$NumArray` alternation looked like an off-by-one that would skip every other column. **It is not a bug:** `db/mysql.php:198-213` implements `sql_fetchrow()` as `mysqli_fetch_array($query_id)` with the default `MYSQLI_BOTH`, so each column appears twice (numeric then associative) and the alternation correctly skips the numeric duplicates. Checked rather than assumed.

**Consequence:** the grant UI rewrites *every* permission column on save, so any capability column present in the table is live-writable whether or not it has a display label.

### 1.4 `ListModertors()`

- **It is all three things, and the distinction matters:** a **management UI** (it renders the per-admin capability checkbox grid that `:4063` saves), a **reporting UI** (it lists active moderators), and a **display-label dictionary** — but it is **not** the permission dictionary.
- `$Trans` is a label map only. `Khotab/admin:4139` reads `$label = $Trans[$key] ?? $key` — **a passthrough fallback.** A token absent from `$Trans` still renders (as its raw column name), is still written by `:4063`, and would still be enforced by `GreenZone`.
- **Therefore tokens absent from `$Trans` can be stored and enforced.** The authoritative vocabulary is the `_mod` table's column list, not `$Trans`.
- Mirror-relevant `$Trans` entries: `addmirror` → *إضافة وصلة بديلة*, `editmirror` → *تعديل وصلة بديلة*, `delmirror` → *حذف وصلة بديلة*. Khotab additionally labels `audio` → *مشرف صوتيات* and `vedio` → *مشرف مرئيات* — the two columns `AvAdmin` actually reads.
- **It proves nothing about active grants.** It is a form plus a label array. Per the 3E.1 method rule, population is a production question (Phase C).

### 1.5 Audit contract

Two functions, **both writing `nuke_islamic_logs`**, distinguished by a `module` column.

| | `RecordLogs` (per-module copy, e.g. `Khotab/admin:429`) | `RecordLog` (shared, `includes/logs.php:226`) |
|---|---|---|
| Signature | `($type, $target_id, $action, $extrainfo, $hidden)` — **all 5 required** | `($type, $target_id, $action, $extrainfo, $hidden='0', $module='')` |
| Columns written | `aid, module, type, target_id, action, extrainfo, mytime, ip, hidden` | identical |
| Actor | global `$aid`, truncated to 150 chars, `addslashes` | global `$aid`, **unescaped** |
| Action representation | free-text token: `add_mirror`, `edit_mirror`, `del_mirror`, `move_mirror`, `join_item`, … | same |
| Target | `type` ∈ {`item`, `mirror`} + `target_id` | same |
| Timestamp | `time()` | `mktime()` (deprecated no-arg form) |
| IP | `REMOTE_ADDR`, falling back to `REMOTE_HOST` | **`REMOTE_HOST` first, then `REMOTE_ADDR`** — reversed |
| Escaping | `addslashes` on aid/type/action/extrainfo/ip; ints cast | `addslashes` on `extrainfo` **only** |
| Failure behavior | `sql_query()` result **discarded** — no return value, no check | same |
| Ordering | **after** the mutation, in every mirror path traced | same |
| Mutation succeeds if logging fails? | **Yes.** Silently. | Yes |
| Transactionally coupled? | **No.** No `BEGIN`/`COMMIT` anywhere in these paths. | No |
| Sensitive content | **Yes — `extrainfo` carries raw mirror URLs.** Khotab's insert embeds the link twice (HTML-escaped); Anasheed's edit/delete embed `$link` / `$Mirror[link]` **unescaped**. `aid` is an admin username and `ip` is a client IP. | same |

### 1.6 Laravel comparison — what already exists

| Facility | Present? | Detail |
|---|---|---|
| Spatie permissions | **Yes** | `App\Support\Permission\Role` / `Permission` override `getConnectionName()` to the **default** connection, deliberately never `main` (ADR-0011) |
| Guard | **Yes** | `admin` |
| Super-admin role | **Yes** | `super-admin` (guard `admin`), mapped from `nuke_authors.radminsuper = 1` — mirrors legacy's own bypass |
| Legacy grant bridge | **Yes, already built** | `SyncLegacyAdminPermissionsAction` + `admin:sync-permissions {--dry-run}`. Idempotent, one-directional, reads `nuke_authors.permissions` via `getRawOriginal()`, never writes it. |
| Naming convention | **Yes** | `{module}.{key}`, where `module` = `basename(__DIR__)` of the gen-2 AdminCP module |
| Seeded permission vocabulary | 29 names | `authors.*`, `backup.*`, `broadcasting.*`, `chat.*`, `khotab.repair`, `khotab.uploaders`, `locations.*`, `questionnaire.*`, `soundcloud.*`, `survey.*`, `telawah.repair`, `youtube.*` |
| **Mirror CRUD permissions** | **NO** | Nearest are `backup.backupkhotabmirror` / `backup.backupanasheedmirror` (backup-programme scope) and `khotab.repair` (link quality). **No add/edit/delete mirror permission exists.** |
| Policy / Gate layer | **NO** | No `app/Policies`, no `Gate::define`, no `->can(`. Authorization is entirely route middleware `admin.permission:`. |
| Audit / activity logging | **NO** | No `spatie/activitylog`, no `activity_log`, and **zero references to `nuke_islamic_logs`** anywhere in `app/`, `routes/` or `config/`. |
| Actor / request metadata captured | Partial | `RequestMetrics` (UUID, timings, counts — deliberately no bindings); `admin.permission` middleware resolves the admin. No actor-attributed write log. |
| Reusable pattern from a migrated feature | **Yes** | `LinkQualityStatsController` + `routes/admin.php:155-157` is the closest precedent: route-middleware permission (`khotab.repair`, `telawah.repair`) over `main`-connection content models, with no policy layer and no audit. |

### 1.7 The decisive structural finding — OLD has two parallel permission systems

| | System 1 — `nuke_authors.permissions` | System 2 — `nuke_*_mod` columns |
|---|---|---|
| Shape | serialized blob, `module => [key => 'on']` | one boolean column per capability, keyed by `aid` |
| Key source | gen-2 AdminCP `admincp/<module>/menu.php` | the `_mod` table's column list |
| Read by | **Generation 2 AdminCP**, and **Laravel** via `SyncLegacyAdminPermissionsAction` → Spatie | OLD's `GreenZone()` (**dead**) and `AvAdmin()` (**live**, `audio`/`vedio` only) |
| Contains mirror tokens? | **YES** — `admincp/khotab/menu.php:82-84` defines `addmirror`, `delmirror`, `editmirror` | **YES** — as columns |
| Laravel grants them today? | **No** — `SyncLegacyAdminPermissionsAction` grants only names that already exist as seeded `Permission` rows; the mirror keys have no counterpart, so they are *silently and deliberately ignored* (its docblock names them: "`khotab/menu.php`'s excluded content-CRUD keys, Business Confirmation #6") | No — Laravel never reads `*_mod` |

**And an asymmetry:** `admincp/` contains `authors, backup, broadcasting, chat, khotab, locations, questionnaire, soundcloud, survey, telawah` — **no `anasheed` module at all.** So Khotab's mirror tokens have a home in System 1 (`khotab.addmirror`, `khotab.editmirror`, `khotab.delmirror`, by the confirmed `basename(__DIR__)` convention, matching the already-seeded `khotab.repair`). **Anasheed's mirror tokens exist only in System 2** — no gen-2 menu key, no blob key, no Spatie counterpart.

**Implication for design (not designed here):** the path of least invention is to **extend System 1** — seed the mirror `Permission` rows and let the existing idempotent sync populate them from grant data that already exists — rather than resurrect `GreenZone()`/`*_mod`. Whether real grant data exists is exactly what Phase C must establish, and Anasheed will need a decision either way.

---

## 2. Exact seven-operation permission mapping

Traced from the handlers, not from `ListModertors()`.

| # | Operation | Handler | Dead `GreenZone` token | **Live** check | Live outcome |
|---|---|---|---|---|---|
| 1 | Khotab insert (form) | `KhotabInsertMirror` `:458` | `addmirror` `:463` | **none** | unauthorized |
| 2 | **Khotab insert (commit)** | `ConfirmInsertMirror` `:471` | `addmirror` `:475` | **none** | **unauthorized** |
| 3 | Khotab edit (form) | `EditMirror` `:548` | `editmirror` `:576` | `AvAdmin` | audio/vedio scope |
| 4 | **Khotab edit (commit)** | `ConfirmEditMirror` `:596` | **`editMirror`** `:624` | **`AvAdmin($Khotab['vedio'])`** `:623` | **audio/vedio scope, fail-closed** |
| 5 | Khotab delete (form) | `DeleteMirror` `:643` | `delmirror` `:664` | `AvAdmin` | audio/vedio scope |
| 6 | **Khotab delete (commit)** | `ConfirmDeleteMirror` `:671` | `delmirror` `:690` | **`AvAdmin`** `:689` | **audio/vedio scope, fail-closed** |
| 7 | **Khotab re-parent** | `MoveMirror` `:342` | **`editmirror`** `:346` | **none** | **unauthorized** |
| 8 | Anasheed insert (form) | `InsertMirror` `:46` | `addmirror` `:55` | **none** | unauthorized |
| 9 | **Anasheed insert (commit)** | `ConfirmInsertMirror` `:77` | `addmirror` `:90` | **none** | **unauthorized** |
| 10 | Anasheed edit (form) | `EditMirror` `:225` | `editmirror` `:236` | **none** | unauthorized |
| 11 | **Anasheed edit (commit)** | `ConfirmEditMirror` `:272` | **`editMirror`** `:289` | **none** | **unauthorized** |
| 12 | Anasheed delete (form) | `DeleteMirror` `:311` | `delmirror` `:318` | **none** | unauthorized |
| 13 | **Anasheed delete (commit)** | `ConfirmDeleteMirror` `:330` | `delmirror` `:340` | **none** | **unauthorized** |

**Answers to the questions asked:**

- Khotab insert → **`addmirror`** ✔
- Khotab edit → **`editMirror`** on the commit path (capital M), `editmirror` on the form path
- Khotab delete → **`delmirror`** ✔
- **Khotab re-parent → `editmirror`** (not a distinct `movemirror` token; it reuses the edit token, lowercase)
- Anasheed insert / edit / delete → **`addmirror` / `editMirror` / `delmirror`** — the **same tokens as Khotab**, with the same capital-M inconsistency on the commit path

**Casing is harmless in OLD, dangerous in Laravel.** The token becomes a SQL **column name**, and MySQL column names are case-insensitive, so `editMirror` and `editmirror` resolve to the same column. `$Trans` and `admincp/khotab/menu.php` both use lowercase `editmirror`. In a case-sensitive Laravel permission string these are two different names — **normalize to lowercase, and record that as a deliberate normalization, not a port.**

**Net live authorization state for the 7 commit operations: 2 of 7 are protected** (Khotab edit and delete, by media-type scope only), **5 of 7 have no authorization whatsoever.**

---

## 3. Exact seven-operation audit mapping

| Operation | Audit call(s) | `type` | `target_id` | `action` | `hidden` | Contains raw URL? |
|---|---|---|---|---|---|---|
| **Khotab insert** | **2** — `:540`, `:541` | `item`, then `mirror` | `$Khotab['id']`, then `$id` | `add_mirror` | `1`, then `0` | **Yes** — `:541` embeds the link twice (escaped) |
| **Khotab edit** | **1** — `:636` | `mirror` | `$id` (the mirror id) | `edit_mirror` | `0` | No |
| **Khotab delete** | **2** — `:698`, `:699` | `mirror`, then `item` | `$Mirror['id']`, then `$khid` | `del_mirror` | `1`, then `0` | No |
| **Khotab re-parent** | **1** — `:369` | `mirror` | `$Mirror['id']` | `move_mirror` | `1` | No |
| **Anasheed insert** | **1** — `:207` | `item` | `$Anasheed['id']` | `add_mirror` | *omitted* | **Yes** — `$link`, **unescaped** |
| **Anasheed edit** | **1** — `:302` | `item` | `$Mirror['khid']` | `edit_mirror` | *omitted* | **Yes** — `$link`, unescaped |
| **Anasheed delete** | **1** — `:347` | `item` | `$Mirror['khid']` | `del_mirror` | *omitted* | **Yes** — `$Mirror['link']`, unescaped |

**Three defects in the audit layer, all source-confirmed:**

1. **Khotab insert mis-attributes the mirror record.** At `:541` the call is `RecordLogs('mirror', $id, …)`, but `$id` was reassigned to the *khotab* id at `:521`, and the `$id_number = (int) $db->sql_nextid();` that would have captured the new mirror id is **commented out** at `:520`. So the `type='mirror'` row carries the parent's id.
2. **Anasheed logs against the parent, never the mirror.** All three calls use `type='item'` with the parent `khid`. Khotab's dual-row convention (`mirror` + `item`) is not reproduced — so Anasheed's trail cannot answer "what happened to this mirror."
3. **Anasheed's insert audit call is likely fatal on PHP 8.** `:207` calls `RecordLogs(` with **4 arguments**, but Anasheed's own `RecordLogs` (`:1384`) declares **5 required parameters** with no default on `$hidden` → `ArgumentCountError`. Anasheed's edit and delete correctly call `RecordLog` (singular, `$hidden='0'` default). Stated conditionally because the production PHP version is not proven from source; under PHP 7 it is a warning and `$hidden` becomes NULL.

---

## 4. Bypass paths (from Pass 3E.1 §3.5.3)

| Path | Authorization | Audit |
|---|---|---|
| `JoinMe` mirror insert — `Khotab/admin:816` | `GreenZone('delkhotab')` `:800` — **dead**; no `AvAdmin` | **none at mirror level.** `:838` logs `('item', $targetId, 'join_item', …, '0')` only |
| `JoinMe` bulk re-parent — `:832` (`UPDATE … SET khid = target WHERE khid = source`, every source mirror at once) | same dead token | **none** |
| `BackUp/admin:1690` mirror insert | **nothing at all** — all 7 `GreenZone` calls in that file are **commented out** (`:489`, `:730`, `:747`, `:1014`, `:1143`, `:1365`, `:1731`) | **none** — `grep -c 'RecordLog' modules/BackUp/admin/index.php` = **0** |
| `BackUp/admin:880`, `:1765` — backup completion (`down`, `trial`, `linksize`, `online`, `downloader`, `checktime`, `cd`) | none | none |

So the two most powerful mirror mutations in the codebase — a bulk re-parent and an unauthenticated insert — are the **least** governed. Neither authorization nor audit is uniform across write paths.

---

## 5. Phase B — classification

### Authorization

| Classification | Items |
|---|---|
| **CURRENT_EXECUTABLE_BEHAVIOR** | `AvAdmin()` — live, fail-closed, reads `nuke_islamic_mod.audio` / `.vedio`, superadmin bypass via `$_SESSION['w2a']->usertype === 'superadmin'`, identity from `admin.php:17`. Governs Khotab edit and delete only. · The grant **management UI** (`addmods` flow + `ListModertors`) is live code. · Laravel's Spatie layer, `super-admin` role and `admin.permission:` middleware. |
| **DEAD_ENFORCEMENT** | **All 13 `GreenZone()` calls** across the seven operations. · All 7 `GreenZone` calls in `BackUp/admin` (commented out). · Telawah's `GreenZone` calls (commented out, Pass 3E.1 §3.2). |
| **HISTORICAL_INTENT** | The per-capability token model (`addmirror` / `editMirror` / `delmirror`), its fail-closed semantics, the `radminsuper` bypass, and the module→table mapping. Valuable as **specification**; not executable. |
| **UNKNOWN_CURRENT_USAGE** | Whether `nuke_islamic_mod` / `nuke_anasheed_mod` hold any rows. · Whether the mirror **columns** exist on those tables. · Whether any grant has them set. · Whether `nuke_authors.permissions` blobs contain the mirror keys for real admins. **Nothing here is "required migration behavior" merely because the code and tables exist.** |

### Audit

| Classification | Items |
|---|---|
| **CURRENT_EXECUTABLE_BEHAVIOR** | `RecordLogs` / `RecordLog` are live and unguarded — they execute on every traced mirror mutation (subject to defect 3). Writes are **after** the mutation, **untransacted**, and **silent on failure**. Generation 2's AdminCP actively maintains `nuke_islamic_logs` on admin rename. |
| **DEAD_ENFORCEMENT** | Telawah's audit calls (commented out). · `BackUp/admin` — never had any. · `JoinMe`'s mirror-level audit (absent, not disabled). |
| **HISTORICAL_INTENT** | The dual-row `mirror` + `item` convention with the `hidden` visibility flag; the `$type`/`action` vocabulary. Khotab implements it; Anasheed does not. |
| **UNKNOWN_CURRENT_USAGE** | Whether `nuke_islamic_logs` holds rows, how recently it was written, and whether any recent rows are mirror actions. |

---

## 6. Phase C — minimal production evidence

### 6.1 What must be distinguished

| Scenario | Design consequence |
|---|---|
| **S1 — historical design, no real production use** (both grant stores empty of mirror tokens; log empty or stale) | Design permission names and an audit contract **fresh**. Do not migrate. Seed 6 permissions, add a new audit mechanism, pick semantics on merit. |
| **S2 — populated historical grants, dead enforcement** (mirror tokens granted to real admins, but only the dead path ever read them) | **Migrate the grant data, not the mechanism.** Seed the `Permission` rows so the existing `SyncLegacyAdminPermissionsAction` populates them. The grants are real intent even though nothing enforced them. |
| **S3 — evidence of recent operational use** (recent mirror actions in the log) | Mirror administration is live work by real people. Authorization and audit are **release-blocking**, not nice-to-have, and the new UI must not regress what admins do today. |
| **S4 — active audit trail worth preserving semantically** (log written recently, mirror actions present) | **Write to `nuke_islamic_logs`** with the existing `type`/`action`/`hidden` vocabulary so one trail spans both systems — rather than starting a separate Laravel log. |

S2/S3/S4 are independent and can co-occur; the check below separates them in one pass.

### 6.2 The one check — Check E

Read-only. Aggregates and metadata only. **Returns no `aid`, no `ip`, no `extrainfo`, no `link`, no blob contents, no usernames, emails, passwords, URLs or cookie values.** `nuke_islamic_logs.extrainfo` carries raw mirror URLs and `aid`/`ip` are personal identifiers, so none of those columns is selected.

E1 is metadata-only and must run first: it establishes which token **columns** actually exist, so E2 cannot error on a missing column.

```sql
USE `w2acp_db2025-2`;

-- E1. FREE. Which capability columns exist? This IS the authoritative
--     token vocabulary (§1.4), and it gates E2.
SHOW COLUMNS FROM `nuke_islamic_mod`;
SHOW COLUMNS FROM `nuke_anasheed_mod`;

-- E2. System 2 — are the grant tables populated, and are the mirror
--     tokens actually granted? Run only the lines whose columns E1 showed.
--     Tiny tables; counts only, no identities.
SELECT 'islamic_mod' AS grant_table,
  COUNT(*)              AS rows_total,
  SUM(`active` = 1)     AS active_rows,
  SUM(`addmirror`  = 1) AS grant_addmirror,
  SUM(`editmirror` = 1) AS grant_editmirror,
  SUM(`delmirror`  = 1) AS grant_delmirror,
  SUM(`audio`      = 1) AS grant_audio,
  SUM(`vedio`      = 1) AS grant_vedio
FROM `nuke_islamic_mod`;

SELECT 'anasheed_mod' AS grant_table,
  COUNT(*)              AS rows_total,
  SUM(`active` = 1)     AS active_rows,
  SUM(`addmirror`  = 1) AS grant_addmirror,
  SUM(`editmirror` = 1) AS grant_editmirror,
  SUM(`delmirror`  = 1) AS grant_delmirror
FROM `nuke_anasheed_mod`;

-- E3. System 1 — do real admins carry the mirror keys in the blob that
--     Laravel's existing sync already reads? Counts only; the blob is
--     never returned. LIKE over a tiny table (nuke_authors is small).
SELECT
  COUNT(*)                                        AS admins_total,
  SUM(`radminsuper` = 1)                          AS super_admins,
  SUM(`permissions` IS NOT NULL
      AND `permissions` <> '')                     AS admins_with_blob,
  SUM(`permissions` LIKE '%addmirror%')           AS blob_has_addmirror,
  SUM(`permissions` LIKE '%editmirror%')          AS blob_has_editmirror,
  SUM(`permissions` LIKE '%delmirror%')           AS blob_has_delmirror
FROM `nuke_authors`;

-- E4. Audit trail — populated, recent, and does it contain mirror work?
--     No extrainfo / aid / ip selected.
SELECT
  COUNT(*)                                   AS log_rows_total,
  COUNT(DISTINCT `module`)                   AS distinct_modules,
  MIN(`mytime`)                              AS first_unix,
  MAX(`mytime`)                              AS last_unix,
  FROM_UNIXTIME(MIN(`mytime`))               AS first_utc,
  FROM_UNIXTIME(MAX(`mytime`))               AS last_utc
FROM `nuke_islamic_logs`;

SELECT `action`,
  COUNT(*)                        AS n,
  FROM_UNIXTIME(MAX(`mytime`))    AS last_utc
FROM `nuke_islamic_logs`
WHERE `action` IN ('add_mirror','edit_mirror','del_mirror','move_mirror','join_item')
GROUP BY `action`
ORDER BY n DESC;
```

**How the results select a scenario:**

- E2/E3 all zero → **S1.** Design fresh.
- E3 mirror counts > 0 → **S2 via System 1**, the cheap path: seed `khotab.addmirror` / `.editmirror` / `.delmirror` and the existing `admin:sync-permissions` populates real grants with **no new mechanism**. (Anasheed still needs a decision — it has no gen-2 menu module, §1.7.)
- E2 mirror counts > 0 but E3 zero → **S2 via System 2 only.** The grant data exists solely in a store nothing reads; a one-off data migration into Spatie would be required, which is more work than seeding.
- E2 `grant_audio` / `grant_vedio` > 0 → confirms the **live** `AvAdmin` scope is real and must be preserved or consciously replaced (§1.2).
- E4 `last_utc` recent **and** mirror actions present with recent `last_utc` → **S3 + S4.** Authorization and audit become release-blocking, and the new unit should write to `nuke_islamic_logs` using the existing vocabulary.
- E4 populated but stale, or no mirror actions → the trail is historical; a Laravel-native audit store is the better choice.

### 6.3 Deliberately not requested

- Khotab/Anasheed mirror schema or data — Pass 3E.1 is closed and nothing here required reopening it.
- Any `aid`, `ip`, `extrainfo`, `link` or blob value.
- Telawah and W2ACD grant tables — both domains are outside the first unit.
- `nuke_islamic_cat_mod` — the Cat module is outside the first unit.
- Whether `nuke_authors.author` is nullable and the mirror `link` collation — Pass 3E.1 §7.2 recorded both as non-blocking.

---

## 6.4 Production results E1–E4, and what they overturn

### E1 — grant schemas (metadata)

`nuke_islamic_mod` carries `active`, `audio`, `vedio`, `addmirror`, `editmirror`, `delmirror`.
`nuke_anasheed_mod` carries `active`, `addmirror`, `editmirror`, `delmirror` — **no `audio` / `vedio`**,
matching §1.3's observation that the two column sets differ. The source-derived capability vocabulary
therefore **physically exists in current production schema**. Schema presence only; E2 settles use.

### E2 — System 2 grant data

| | `nuke_islamic_mod` | `nuke_anasheed_mod` |
|---|---:|---:|
| rows_total | **36** | **15** |
| active_rows | **36** (100%) | **15** (100%) |
| `addmirror` | **33** | **9** |
| `editmirror` | **32** | **10** |
| `delmirror` | **28** | **9** |
| `audio` | **17** | — (column absent) |
| `vedio` | **26** | — |

**System 2 is not an unused historical schema.** Both tables are populated, every row is active, and the
mirror capabilities are materially granted. For Khotab, `audio` / `vedio` are materially granted too —
which matters precisely because those are the columns live `AvAdmin()` reads (§1.2).

No identity or per-grant overlap is inferred from these aggregates.

### E3 — System 1 grant data

`nuke_authors`: **48** admins, **5** super-admins, **20** with a non-empty `permissions` blob, and
`addmirror` **7** / `editmirror` **6** / `delmirror` **6**.

**Both permission systems are populated in current production, with materially different counts** (33 vs 7
for `addmirror`). They are not merely parallel in source.

### E4 — audit trail

`nuke_islamic_logs`: **253,499** rows across **3** distinct modules, spanning **2025-03-17 14:03:39** to
**2026-10-12 14:21:03**. Timestamps are database-rendered; **not described as UTC**, since the session
timezone was not independently established.

| Action | Count | Latest |
|---|---:|---|
| `add_mirror` | **87,547** | 2026-10-12 14:21:03 |
| `del_mirror` | **243** | 2026-10-11 10:39:30 |
| `edit_mirror` | **191** | 2026-10-11 10:36:46 |
| `move_mirror` | **1** | 2026-01-20 13:08:22 |
| `join_item` | no result row in this filtered aggregate — **nothing further inferred** | — |

**The audit trail is active and mirror administration is current work.** Add, edit and delete mirror
activity all occurred within the last two days of this pass.

### 6.5 Hypotheses overturned by E1–E4

| Earlier statement | Status |
|---|---|
| §6.2's shortcut: *"E3 > 0 → seed three permissions and the existing sync does the rest with no new mechanism"* | **WITHDRAWN.** No longer sufficient. System 2 is independently populated and carries the `audio` / `vedio` dimensions that live `AvAdmin()` consumes — state that seeding System 1 cannot express. The two stores must be reconciled semantically before any seeding decision. |
| §1.3's *"`nuke_anasheed_mod` columns read by live code: **none — no live reader exists**"* | **Stands as written** (no live reader), but must now be read alongside E2: the table is **populated and fully active**, so the absence of a reader is a gap, not evidence of disuse. |
| §5's UNKNOWN_CURRENT_USAGE row for grant population | **RESOLVED.** Both stores populated; reclassified in §9.1. |
| §5's UNKNOWN_CURRENT_USAGE row for the audit trail | **RESOLVED.** Active and current; audit is now a **migration requirement**, not a choice. |
| §6.1's scenario set (S1–S4) | **S1 eliminated.** Production shows **S2 + S3 + S4 simultaneously**: populated grants with dead enforcement, recent operational use, and an active trail worth preserving. |

### 6.6 One source fact that completes the picture

Checked after E1–E4, because the E3 result made it load-bearing: **does anything actually enforce System
1's mirror keys?**

- `grep` for `addmirror|editmirror|delmirror` across all of Generation 2, excluding `menu.php`, returns
  **nothing.** The keys appear *only* in `admincp/khotab/menu.php:82-84`.
- `admincp/sidebar.php:43` gates on `is_array($permissions[$dir_name])` — **module-level visibility only**
  (does this admin have *any* khotab entry → render the khotab menu). No individual key is ever checked.
- Generation 2 has no mirror operations at all; it delegates them to OLD by hyperlink (Pass 3D).

So `admincp/khotab/menu.php`'s key list is a **vocabulary/label declaration**, structurally identical in
role to `ListModertors()`'s `$Trans` — not an enforcement point. The complete enforcement picture:

| Store | Granularity actually enforced today | By what |
|---|---|---|
| System 1 blob | **module-level only** — sidebar visibility | `admincp/sidebar.php:43` |
| System 1 → Spatie | `{module}.{key}`, but **only the 29 seeded names**; mirror keys excluded by design | Laravel `admin.permission:` middleware |
| System 2 mirror columns (33/32/28 + 9/10/9) | **nothing** | `GreenZone()` dead |
| System 2 `audio` / `vedio` (17 / 26) | **Khotab edit + delete only** | live `AvAdmin()` |

> **The only currently-enforced authorization data anywhere in the seven-operation surface is System 2's
> `audio` / `vedio` columns, via `AvAdmin()`, on two of the seven operations.** Every mirror-specific grant
> in both systems — 33+32+28 columns and 7+6+6 blob keys — is populated and enforced by nothing.

---

## 7. Final authorization contract for the seven-operation unit

Not a resurrection of `GreenZone()`. Built from what production and source jointly establish.

### 7.1 Classification

**A. Current executable authorization**

1. `AvAdmin()` — live, fail-closed, superadmin-bypassing, reading `nuke_islamic_mod.audio` / `.vedio`.
   Governs **Khotab edit and delete only**. Grants are real (17 audio, 26 vedio of 36 active rows).
2. Laravel's Spatie layer — guard `admin`, `super-admin` role from `radminsuper` (5 super-admins),
   `admin.permission:` route middleware over the 29 seeded names. **None of which is a mirror permission.**
3. Generation 2's module-level sidebar visibility from the System 1 blob.

**B. Populated but currently unenforced authorization data**

4. System 2 mirror columns — `nuke_islamic_mod` 33/32/28, `nuke_anasheed_mod` 9/10/9. Real grants, dead
   reader.
5. System 1 mirror blob keys — 7/6/6 of 48 admins. Real grants, no reader at any granularity (§6.6).

**C. Historical intent**

6. `GreenZone()`'s per-capability, fail-closed, `radminsuper`-bypassing model, and its module→table map.
   Specification only. The `movemirror` capability was never minted — re-parent reuses `editmirror`.

**D. Migration requirement**

7. **Preserve the `audio` / `vedio` media-type scope for Khotab edit and delete.** It is the only live
   authorization on the surface; dropping it is a real authorization regression, not a simplification.
   17 and 26 grants say it discriminates in practice.
8. **Preserve the `super-admin` bypass.** Both OLD paths honour it (`AvAdmin` via session usertype,
   `GreenZone` via `radminsuper`) and Laravel already models it.
9. **Every one of the seven operations must be permission-gated.** Today 5 of 7 have no authorization at
   all; shipping a Laravel CRUD at that level would be strictly worse than OLD's *intent*, even if
   identical to OLD's *current behavior*.

**E. Design choices still requiring an explicit decision**

10. **Which store is authoritative for mirror capability.** Both are populated with materially different
    counts (33 vs 7). **Neither union nor intersection is taken here**, because no semantic justification
    exists in the evidence: the two stores serve different enforcement engines, were maintained by
    different UIs, and their populations (36 `_mod` rows vs 48 admins / 20 with blobs) are of different
    size with **unknown overlap** — identities were deliberately not requested. Union would silently
    *grant* capability to admins the moderator UI never granted it to; intersection would silently
    *revoke* from up to 26 moderators. Both are policy decisions, not derivations.
11. **Anasheed's permission namespace.** It has populated System 2 grants (9/10/9), **no `AvAdmin`**, and
    **no Generation-2 System 1 module** — `admincp/` has no `anasheed` directory. So no `{module}.{key}`
    home exists. Mint `anasheed.*` names, or scope Anasheed under `khotab.*`?
12. **Whether Anasheed gains a media-type scope.** Its `_mod` table has no `audio` / `vedio` columns at
    all, so there is nothing to preserve — adding one would be new behavior.
13. **Token naming.** Normalize `editMirror` → `editmirror` (§2). Re-parent: reuse `editmirror` as OLD
    intended, or mint a distinct `movemirror`? OLD never minted one.

### 7.2 What can be stated without further evidence

The minimal-risk position, stated so the decisions in 7.1.E are framed rather than pre-empted:

- `audio` / `vedio` must survive (D7) — it is the only thing currently enforcing anything.
- All seven operations must be gated (D9) — the current 5-of-7 gap is not a baseline to preserve.
- The authoritative-store question (E10) is the **one genuine blocker** to writing permission names, and it
  is a business/ownership decision, not a research gap. Resolving it needs one answer: *which UI does the
  organization consider the source of truth for who may administer mirrors — the moderator grid
  (`ListModertors`, System 2) or the AdminCP permission editor (System 1)?*

---

## 8. Final audit contract for the seven-operation unit

E4 makes audit a **migration requirement**, not an option: 87,547 `add_mirror` rows with the most recent
at 2026-10-12 14:21:03.

### 8.1 Must remain observable for operational continuity

| Property | Why |
|---|---|
| Actor attribution | `aid` is the only record of who changed a link. 253,499 rows depend on it. |
| Action vocabulary — `add_mirror`, `edit_mirror`, `del_mirror`, `move_mirror` | Production queries and the moderator-log views filter on these exact strings. Changing them orphans the existing trail. |
| `module` discriminator | 3 distinct values in production; it is how one table serves several domains. |
| `type` + `target_id` | The entity the action applied to. |
| Timestamp | `mytime`, unix integer. |
| `hidden` flag | Controls visibility in the moderator-log view; Khotab's dual-row convention depends on it. |
| **Coverage of all seven operations** | All seven log today (subject to 8.2.3). Logging fewer would be a regression. |
| Re-parent logging | `move_mirror` exists with exactly **1** production row. Low volume is not grounds to drop it — it is the audit record for the most destructive single-row operation in the unit. |

### 8.2 Legacy defects that should NOT be reproduced

1. **Khotab insert's mirror-row mis-attribution.** `:541` logs `type='mirror'` with the *khotab* id, because
   `$id` was reassigned and `$id_number = sql_nextid()` is commented out at `:520`. 87,547 `add_mirror`
   rows are affected in aggregate. **Fix; do not replicate.** Recorded as a deliberate divergence.
2. **Anasheed's parent-only granularity.** All three Anasheed calls use `type='item'` with the parent
   `khid`, so the trail cannot answer "what happened to this mirror." Khotab's dual-row (`mirror` +
   `item`) convention is the better contract and should be applied to both domains.
3. **Anasheed insert's 4-argument `RecordLogs` call** against a 5-required-parameter signature — likely
   fatal on PHP 8 (§3, defect 3). Not reproduced.
4. **Silent logging failure.** `sql_query()`'s result is discarded; a failed log leaves a silent,
   unattributed mutation. A new implementation should surface the failure.
5. **No transaction coupling.** Mutation and log commit independently. Pass 3E.1's I-10 already requires a
   transaction spanning the mirror write and the parent-counter write; the audit write belongs inside it,
   or at minimum must fail loudly outside it.
6. **IP-source inconsistency.** `RecordLogs` prefers `REMOTE_ADDR` then `REMOTE_HOST`; `RecordLog` reverses
   it. Pick one.
7. **Inconsistent escaping.** `RecordLog` escapes only `extrainfo`, leaving `aid`, `type` and `action`
   unescaped.

### 8.3 Sensitive / raw data not to be copied without need

- **`extrainfo` carries raw mirror URLs** — twice per Khotab insert (HTML-escaped) and unescaped in all
  three Anasheed calls. It also carries HTML markup. A new audit representation should record the
  *mirror id* and let the URL be resolved from the mirror row, rather than embedding it. Where a before/after
  URL genuinely matters for an edit, store it as a structured field, not inside a display string.
- **`ip`** is a client IP. Retain only if operationally justified, with a retention policy.
- **`aid`** is an admin username. Required for attribution — keep, but as attribution, not as free text
  inside `extrainfo`.

### 8.4 The dual-write compatibility question

**Does Laravel need to keep writing `nuke_islamic_logs` during side-by-side migration, or merely provide an
equivalent trail?**

What the evidence settles:

- The table is **shared live state**: OLD writes it (253,499 rows, latest 2026-10-12 14:21:03), Generation 2's
  AdminCP maintains its referential integrity on admin rename (`admincp/index.php:~440-500`), and OLD's
  moderator-log views read it. It is **not** an OLD-private store.
- During side-by-side operation, OLD mirror administration remains reachable (Pass 3D), so **both systems
  can mutate mirrors concurrently.** A Laravel-only trail would therefore be **structurally incomplete** —
  it could not answer "who changed this mirror" for changes made through OLD, and OLD's reader could not
  see Laravel's changes.
- Laravel currently has **no audit facility at all** (§1.6), so there is no existing trail to extend.

What the evidence does **not** settle, and is therefore an explicit decision rather than an invented answer:

> **DESIGN/BUSINESS DECISION — audit write target.** Whether Laravel writes `nuke_islamic_logs` directly
> (one continuous trail, legacy schema and its constraints inherited, including the `extrainfo`
> URL-embedding convention the readers expect), writes a Laravel-native trail **and** mirrors a compatible
> row into `nuke_islamic_logs` for the migration window, or writes only a native trail and accepts a split
> record. The source establishes the **cost** of each option but not the organization's tolerance for a
> split audit record, nor how long side-by-side operation is expected to last.

The structural argument favours continuing to write `nuke_islamic_logs` for the migration window — it is
the only option under which the trail stays answerable while both systems are live — but that is a
recommendation, not a finding, and the retention/PII questions in §8.3 interact with it.

---

## 9. Closure test

### 9.1 Pass 3E.2 — **CAN CLOSE.**

Every question Phase A set out to answer is answered from source, and every population question Phase C
raised is answered from production:

| Phase C question | Resolved? |
|---|---|
| Are the grant tables populated? | **Yes** — 36 and 15 rows, all active (E2) |
| Do stored grants contain the exact handler tokens? | **Yes** — 33/32/28 and 9/10/9 (E2); schema confirmed (E1) |
| How many distinct admins? | Answered at aggregate level; identities deliberately not requested |
| Is `nuke_islamic_logs` populated? | **Yes** — 253,499 rows (E4) |
| Still being written recently? | **Yes** — latest 2026-10-12 14:21:03 (E4) |
| Do recent records contain mirror actions? | **Yes** — all four actions present, three within two days (E4) |

The audit contract is complete (§8), with one explicitly-marked decision (§8.4). The authorization
classification is complete (§7.1), with four explicitly-marked decisions (§7.1.E).

**No remaining question can be resolved by more investigation.** The authoritative-store question (§7.1.E10)
is the one genuine blocker to writing permission names, and it is **ownership policy, not evidence** —
union would grant capability the moderator UI never granted, intersection would revoke it from up to 26
moderators, and no artifact in any of the three codebases ranks the two stores. Requesting admin identities
would reveal the overlap but still would not say which store the organization trusts.

### 9.2 Next pass before implementation

**Not another investigation pass.** The next step is a **decision pass** — the five marked decisions, four
authorization (§7.1.E10–E13) and one audit (§8.4), put to the owner. Each is stated as a closed question
with its options and consequences; none needs further research.

Once those five are answered, implementation design can begin directly: Pass 3E.1 fixed the data contract
(12 invariants), and this pass fixes the authorization and audit contract.

## 10. What this pass deliberately did not do

- **No permission names designed, no audit contract designed.** §1.7's "path of least invention" is an observation about existing infrastructure, not a design.
- No recommendation to port `GreenZone()` enforcement literally — explicitly the opposite.
- No change to `SyncLegacyAdminPermissionsAction`, `RoleSeeder`, the Spatie seeding, or any route middleware.
- No claim that moderator grants are required migration behavior. Every population question is routed to Phase C.
- No implementation, no deployment, no commit, no production modification.

## 11. Open items carried out of this pass

| # | Item | Status |
|---|---|---|
| 1 | Is the live `AvAdmin` audio/vedio media scope intended to survive into Laravel, or is it legacy division-of-labour to retire? | **Open — product.** It is the only live authorization on any of the 7 operations. |
| 2 | Anasheed has no gen-2 AdminCP module, so no System 1 home for its mirror tokens. Introduce `anasheed.*` permission names, or scope Anasheed under `khotab.*`? | **Open — design**, after Check E. |
| 3 | `editMirror` vs `editmirror` casing — normalize to lowercase in Laravel. | **Decided in principle**, recorded as a deliberate normalization. |
| 4 | Khotab insert's mirror-row audit mis-attribution (`$id_number` commented out). Reproduce the defect or fix it? | **Open — but fixing is the obvious call**; recorded so it is a decision, not an accident. |
| 5 | Anasheed's 4-argument `RecordLogs` call is likely fatal on PHP 8. | **Open**, conditional on the production PHP version — which Pass 3E.1 §3.6 also left open. |
| 6 | `JoinMe`'s bulk re-parent and `BackUp/admin:1690`'s insert have neither authorization nor mirror-level audit. | **Open — scope.** Both are outside the 7 operations; flagged so the gap is not inherited silently. |
| 7 | Laravel has **no** audit facility at all. Write to `nuke_islamic_logs`, or introduce one? | **Open**, decided by Check E's E4. |
