# Pass 3D — Existing Laravel Ownership Reconciliation

**Status:** ANALYSIS ONLY — no implementation, no legacy changes, no deployment, no commit.
**Date:** 2026-10-12
**Predecessor:** Pass 3C (`old-contextual-admin-surface-pass-3c.md`), accepted; its contextual-admin model is the working scope model.
**Parked:** OA-1 Advfiles. **Not resumed:** OA-3. **Not reinterpreted:** Business Confirmation #6.

**Target restated:** preserve the required content-administration capabilities historically exposed to an authenticated administrator while browsing/managing content, regardless of delivery mechanism. We are eliminating *required* administration dependencies that remained in OLD after Generation 2 moved only part of administration into AdminCP — not migrating OLD because OLD contains code.

**Purpose of this pass:** remove false migration gaps before ranking implementation work.

---

## 1. Summary of outcomes

| Reconciliation | Outcome |
|---|---|
| **A. Link / mirror quality** | **Split.** Laravel genuinely owns link *measurement*, the quality metric, size correction, and the whole `down`/`trial` lifecycle. It owns **no mirror mutation whatsoever**. The gap is sharper and larger than Pass 3C stated, not smaller. |
| **B. Locations contextual administration** | **Family dissolved.** None of the six operations is a Locations capability. Zero genuine Locations gaps; the six de-duplicate into three families already counted. |
| **Net effect on Pass 3C** | 7 apparent gap-operations removed, 1 reclassified to a reporting gap, 1 family deleted (17 → 16), and the mirror family re-scoped upward from ~7 to ~10 operations across 4 content domains. |
| **New cross-cutting finding** | OLD's "moderator" family is not a feature — it is the **per-capability content-administration permission model**, currently stored, displayed and *unenforced*. It is an input to every other migration unit, not a standalone one. |

---

## 2. Family A — link / mirror quality administration

### 2.1 OLD operations examined (behavior-first)

| OLD operation | Admin intent | Context | Input | Mutation | Side effects | Result |
|---|---|---|---|---|---|---|
| `KhotabLostedLink` | see every item whose link was never successfully verified, and repair it | central admin, paginated 50/page | `$Mode`, `$Page` | **none** (see 2.2) | none | HTML listing of `nuke_islamic_khotab WHERE down = 0` with per-row edit link and a repair form |
| `KhotabEditMirror` → `UpdateMirror` | correct a mirror's **URL** | item page → returns to item page | `id`, `khid`, `link`, `comment`, `vedio`, `backupme` | `nuke_islamic_mirror SET comment, trial='0', vedio, backupme, link` | `RecordLogs('mirror', id, 'edit_mirror', …)` | `header('Location: khotab-item-$khid.htm')` — full contextual round-trip |
| `KhotabDeleteMirror` → `ConfirmDeleteMirror` | remove a dead mirror | item page | `id` | deletes the mirror row | `RecordLogs`, `GreenZone('delmirror')` | returns to item |
| `KhotabInsertMirror` | add an alternative download link | item page | `khid`, `link`, … | inserts mirror row | `RecordLogs` | returns to item |
| `KhotabMoveMirror` → `MoveMirror($id,$khid)` | re-parent a mirror to a different item | item page | `id`, `khid` | `nuke_islamic_mirror SET khid` | — | returns to item |
| `AnasheedEditMirror` / `AnasheedDeleteMirror` / `AnasheedInsertMirror` | same, Anasheed domain | item page | — | `nuke_anasheed_mirror` | `RecordLogs` | — |
| `TelawahEditMirror` / `TelawahDeleteMirror` | same, Telawah domain | item page | — | telawah mirror rows | `RecordLogs` | — |
| `W2ACDEditMirror` / `W2ACDDeleteMirror` / `W2ACDInsertMirror` | same, W2ACD domain | item page | — | w2acd mirror rows | `RecordLogs` | — |
| `MirrorExport` | bulk-dump mirror links to files | central admin, maintenance | id range | none (read + file write) | writes export files | — |

### 2.2 `KhotabLostedLink` — its repair path never existed

`modules/Khotab/admin/index.php:3435-3515` renders, per row, a form:

```php
echo "<tr><td ...><form action=\"" . $admin_file . ".php\" method=\"post\">
  <input type=\"hidden\" name=\"id\" value=\"$id\" />
  ... <input type=\"hidden\" name=\"op\" value=\"KhotabRepairLostedLink\" /> ...
```

Two independent defects, both verified:

1. **No handler exists.** `grep -rc 'KhotabRepairLostedLink' --include=*.php` over the entire `old-project` returns exactly **one** occurrence — the hidden input above. There is no `case "KhotabRepairLostedLink":` in `admin/case.php` or `admin/index.php`, and no `function RepairLostedLink`. The POST falls through PHP-Nuke's `switch` to no branch.
2. **The row id is never sent.** `LostedLink()`'s `global` list is `$prefix, $db, $module_name, $ThemeSel, $admin_file, $admin, $nukeurl` — `$id` is not among them and is never assigned in the function. The loop variable is `$khotabId`. So `value="$id"` interpolates an undefined local and submits empty.

**Therefore `KhotabLostedLink` is, and has always been within this source, a reporting screen only.** Pass 3C listed it under "link integrity" implying a repair capability. That implication is withdrawn: there is no OLD link-repair behavior at this operation to migrate.

What it does provide, and Laravel does not, is **the `down = 0` worklist itself** — "which items have never been successfully verified."

### 2.3 The `down` / `trial` lifecycle is already Laravel-owned

The flag vocabulary, read from `modules/Khotab/admin/index.php:4295-4299` and `modules/BackUp/admin/index.php:88-109`:

- `down = 0` — never successfully fetched (the `LostedLink` worklist)
- `down = 1` — fetched; `online != linksize` is a quality mismatch
- `down = 2` — verified/archived; `trial > 10` is a repeatedly-failing row

In Laravel, `app/Domain/Content/Http/Controllers/BackupApiController.php` (replacing root `backup.php`, Roadmap 6.8 — the machine-to-machine Desktop-App backup API) owns both ends of that lifecycle:

- **work hand-out** reads `down = 2 AND trial < $maxTrials` (`:337-373`) and `->update(['trial' => DB::raw('trial + 1'), 'booking' => time()])` (`:279`)
- **completion** (`myop=put`, `:409-420`) writes `['down' => 1, 'trial' => 0, 'linksize' => $size, 'online' => $size, 'downloader' => $admin->uid, 'checktime' => time(), 'booking' => 0]`

across `CATEGORY_TABLES` = `nuke_islamic_khotab`, `nuke_islamic_mirror`, `nuke_telawah_telawah`, `nuke_anasheed_anasheed`, `nuke_anasheed_mirror` (`:43-49`).

**Status: `MIGRATED`.** OLD's only remaining writer of this flag is a single reset (`modules/Khotab/admin/index.php:3658`, `SET down='0' WHERE down='2'`), a maintenance sweep with no contextual entry point.

Note for §7: the five backup tables **exclude W2ACD**.

### 2.4 What Laravel's link-quality work actually replaces

`LinkQualityStatsController`'s own docblock states its provenance: `admincp/khotab/stats.php`, `admincp/khotab/stats_khotab.php`, `admincp/telawah/stats.php`, `admincp/khotab/stats_khotab_200mb.php` — **Generation-2 AdminCP pages, not OLD operations.** Likewise `LinkSizeChecker` documents itself as `stats*.php`'s `getsizeid` branch.

This matters for bucketing: the link-quality programme belongs to *"already moved into Generation-2/AdminCP, and since then into Laravel"*, not to *"OLD migration work."* It was never an OLD-dependency elimination.

Verified Laravel surface:

| Component | Behavior |
|---|---|
| `LinkSizeChecker::check()` | `Http::get($link)->header('Content-Length')`, returns 0 on throw; `$downgradeHttpsToHttp` preserves a confirmed legacy inconsistency between modules |
| `RecheckKhotabLinkSizeJob` / `RecheckTelawahLinkSizeJob` / `RecheckMirrorLinkSizeJob` | queued; write `checktime`, `online`, and `percent = 100` on exact match. **Do not write `link`, `down`, `trial`, or `backupme`.** |
| `LinkQualityStatsController::mirror/khotab/telawah/khotabLargeFiles` | mismatch listings; per-module thresholds and embed exclusions preserved as confirmed divergences |
| `recomputeMirror/Khotab/Telawah` | explicit action via `RecomputeLinkQualityPercentAction` — deliberately not the legacy per-pageload recompute |
| `fixSizeMirror/Khotab/Telawah` | `update(['linksize' => $online, 'percent' => 100])` |
| `recheckMirror/Khotab/Telawah` | dispatch the job |
| `routes/admin.php:155-157` | `/admin/link-quality/*`, gated by `khotab.repair` / `telawah.repair` permissions |

**The decisive asymmetry:** `fixSize*` declares the *measured* size authoritative. It does not change a URL. Searching `app/` for any mirror mutation — `Mirror::create|update|delete|save|insert`, `->mirrors()` writes — returns **nothing**.

> **Laravel can measure a link's quality and accept its size. It cannot change a link, add one, remove one, or re-parent one.**

### 2.5 Family A capability matrix

| OLD operation | Admin intent | Data affected | Laravel equivalent | Equivalent behavior? | Contextual UX? | Status |
|---|---|---|---|---|---|---|
| `KhotabLostedLink` (listing half) | find never-verified items | `khotab.down=0` | none | **No** — no `down=0` report exists | n/a (was central in OLD too) | **MIGRATION_GAP** (reporting only) |
| `KhotabLostedLink` (repair form half) | fix the URL | — | — | **No OLD behavior to migrate** (§2.2) | — | **HISTORICAL/BROKEN — do not port** |
| `down`/`trial`/`linksize`/`online`/`downloader`/`checktime`/`booking` lifecycle | archive & verify content | 5 content tables | `BackupApiController` `myop=get`/`put` | **Yes** | No UI at all, by design (machine API) | **MIGRATED** |
| link-size measurement + `percent` metric | know whether a link is healthy | `online`, `percent`, `checktime` | `LinkSizeChecker` + 3 jobs + `recompute*` | **Yes** | Central only (`/admin/link-quality`) | **MIGRATED** *(from gen-2, not OLD)* |
| mismatch worklist | triage unhealthy links | reads `percent` | `LinkQualityStatsController::mirror/khotab/telawah` | **Yes** | Central only | **MIGRATED** *(from gen-2)* |
| size correction | accept measured size | `linksize`, `percent` | `fixSize*` | **Yes** | Central only | **MIGRATED** *(from gen-2)* |
| `KhotabEditMirror` / `UpdateMirror` | **change a mirror's URL** | `mirror.link`, `comment`, `vedio`, `backupme`, `trial` | **none** | **No** | — | **MIGRATION_GAP** |
| `KhotabDeleteMirror` | remove a dead mirror | mirror row deletion | **none** | **No** | — | **MIGRATION_GAP** |
| `KhotabInsertMirror` | add an alternative link | mirror row insert | **none** | **No** | — | **MIGRATION_GAP** |
| `KhotabMoveMirror` | re-parent a mirror | `mirror.khid` | **none** | **No** | — | **MIGRATION_GAP** |
| `AnasheedEditMirror` / `DeleteMirror` / `InsertMirror` | same, Anasheed | `nuke_anasheed_mirror` | **none** | **No** | — | **MIGRATION_GAP** |
| `TelawahEditMirror` / `DeleteMirror` | same, Telawah | telawah mirrors | **none** | **No** | — | **MIGRATION_GAP** |
| `W2ACDEditMirror` / `DeleteMirror` / `InsertMirror` | same, W2ACD | w2acd mirrors | **none** | **No** | — | **MIGRATION_GAP** *(also §7-gated)* |
| `MirrorExport` | bulk dump links to files | read-only + files | none | No | — | **UNKNOWN** — maintenance utility, no evidence of current use |

`SUPERSEDED_BY_AUTOMATION` is **not** used anywhere in this matrix. The jobs measure; they never repair a URL. Awarding that status here would have been exactly the name-similarity error the brief warned against.

### 2.6 Shared-data dependency

Every OLD mirror mutation calls `RecordLogs('mirror', $id, …)` → `nuke_islamic_logs` (`aid, module, type, target_id, action, extrainfo, hidden, ip`; 45 call sites in Khotab admin, 7 Anasheed, 3 Telawah), and is wrapped in `GreenZone('editMirror')` / `GreenZone('delmirror')`.

So the mirror family is coupled to the "moderator" family (§8) through both the audit log and the permission vocabulary. That coupling is a ranking input, not a blocker — see §10.

---

## 3. Family B — Locations contextual administration

### 3.1 The six operations, traced

All six live in a single file, `old-project/modules/Locations/index.php`:

| Line | Operation | What it actually is |
|---|---|---|
| 256 | `cat_add_to_sub{$type}` | **category assignment** — `{$type}_id=$id&cat_id=$Cat[id]`, "اضف للكل" (add to all) |
| 257 | `cat_del_from_sub{$type}` | **category assignment** — "امسح من الكل" (remove from all) |
| 486 | `AdvfilesEditAdvFile` | **Advfiles** — `id=$AdvFile[id]`, shown with the file's remaining display credit |
| 487 | `AdvfilesDeleteAdvFile` | **Advfiles** |
| 632 | `KhotabEditMirror` | **mirror management** — `id=$Mirror[id]` |
| 634 | `KhotabDeleteMirror` | **mirror management** |

**Not one of the six is a Locations capability.**

### 3.2 Why — the module is misnamed

`modules/Locations/index.php`'s own header reads *"قسم الخطب و الدروس الإصدار الثاني"* — **"Khotab & Lessons section, second edition."** It is a **location-filtered Khotab browse page**, which renders items, their mirrors, their advertised files, and category controls. The directory name describes the *filter*, not the administered entity.

Corroborating evidence:

- `grep -rhoE 'op=[A-Za-z_0-9]*[Ll]ocation[A-Za-z_0-9]*'` across the whole of `old-project` returns **zero results**. **No OLD operation of any kind names a location.**
- `location` is a *query dimension*, per `.htaccess:214-229`: `locations.htm`, `location-N.htm`, `location-N-author-M.htm`, `location-N-group-M.htm`, `location-N-series-M.htm`, and `location-N-item-M.htm` (which routes to `name=Khotab&op=Detailes`, confirming the content is Khotab).
- Production carries `idx_location_author_hidden (location_id, author, hidden)` — an index built for *browsing by location*, consistent with location being a column on content, set elsewhere.

### 3.3 The brief's special attention: item ↔ location attach/detach

The brief rightly flagged that generic Location CRUD would not cover contextual attach/detach. **Verified: no such OLD operation exists.** Location assignment is not performed by any contextual control in OLD — so Laravel's `LocationsController` is not missing it; there was never an OLD dependency of that shape to eliminate.

### 3.4 Laravel's ownership

`routes/admin.php:96-108` + `LocationsController`: `index`, `create`, `store`, `edit`, `update`, `destroy`, gated by `locations.add_location` / `locations.del_location`. This is complete CRUD over the **Location entity**.

### 3.5 Family B capability matrix

| OLD operation | Admin intent | Data affected | Laravel equivalent | Equivalent behavior? | Contextual UX? | Status |
|---|---|---|---|---|---|---|
| Location entity CRUD | manage the location list | locations table | `LocationsController` (6 routes, permission-gated) | **Yes** | Central; **OLD had no contextual equivalent either** | **MIGRATED** |
| item ↔ location assignment | — | — | — | **No OLD operation exists** | — | **NOT APPLICABLE** |
| `cat_add_to_sub` / `cat_del_from_sub` (`:256-257`) | category assignment | category ↔ group/series | — | — | — | **DUPLICATE of OA-3** — re-counted there, not here |
| `AdvfilesEditAdvFile` / `AdvfilesDeleteAdvFile` (`:486-487`) | advertised-file admin | advfiles rows | — | — | — | **DUPLICATE of OA-1** — re-counted there |
| `KhotabEditMirror` / `KhotabDeleteMirror` (`:632-634`) | mirror admin | mirror rows | — | — | — | **DUPLICATE of Family A** — re-counted there |

**Result: the Locations family is deleted from the migration map. Zero genuine Locations gaps.**

### 3.6 Secondary finding — this file is very likely non-executable

`modules/Locations/index.php:12` is the file's **first executable statement**:

```php
if (!eregi("modules.php", $_SERVER['SCRIPT_NAME'])) {
```

`eregi()` was removed in PHP 7.0. Verified across `old-project`: **no `function eregi` definition anywhere** (any file type), **no `auto_prepend_file`** in `.htaccess`, `.user.ini` or `php.ini`. The guard precedes `require_once("mainfile.php")` at `:15`, so no shim loaded later could help.

Meanwhile, the live content modules were de-`eregi`'d as part of PHP-7 maintenance: `Khotab/index.php`, `Anasheed/index.php`, `Telawah/index.php`, `w2acd/index.php` all show `eregi=0`, `preg_match>0`. 55 files across OLD still use `eregi` — `modules/Locations/index.php` among them.

It *is* routed (`.htaccess:214-229` → `new_modules.php?name=Locations`, and `new_modules.php:94` does `include($module_file)`), so this is **reachable-but-fatal**, not merely unrouted. It is also internally inconsistent — `:244` reads `if ($type=='group' OR $type='series')`, an assignment, not a comparison.

**Stated conditionally, because source cannot prove the production PHP version:** if OLD runs PHP ≥ 7.0, every `location*.htm` URL fatals at line 12. One read-only browser request to `https://old.way2allah.com/locations.htm` settles it. This does not change §3.5 — the family dissolves on behavioral grounds regardless — so it is recorded as context, not as a dependency.

---

## 4. Reconciliation with completed migration work

Per the brief, this is coverage reconciliation; the completed link-quality implementation is **not** reopened or redesigned. No concrete behavior gap was found *within its own scope*.

| Completed Laravel work | Which OLD administrator action it replaces |
|---|---|
| `RecheckMirrorLinkSizeJob` | **None.** Replaces gen-2 `admincp/khotab/stats.php`'s `getsizeid`. |
| `RecheckKhotabLinkSizeJob` | **None.** Replaces gen-2 `admincp/khotab/stats_khotab.php`'s `getsizeid`. |
| `RecheckTelawahLinkSizeJob` | **None.** Replaces gen-2 `admincp/telawah/stats.php`'s `getsizeid`. |
| `LinkSizeChecker` | **None.** Extracted service, gen-2 provenance. |
| `RecomputeLinkQualityPercentAction` | **None.** Replaces gen-2's per-pageload `percent` recompute (Pattern E). |
| `LinkQualityStatsController` (listings, `fixSize*`, `recompute*`, `khotabLargeFiles`) | **None.** Replaces four gen-2 AdminCP stats pages. |
| `BackupApiController` | **Substantially replaces** the OLD `down`/`trial` lifecycle and the `modules/BackUp/admin` worklist queries — the one place where completed work genuinely eliminates an OLD dependency. |
| `khotab.repair` / `telawah.repair` permissions, `/admin/link-quality` routes, `admin.link-quality.*` views | Gate the above. No OLD counterpart in OLD's own vocabulary (see §8). |
| Uploader tracking (`UploaderController::recompute()`) | Cited in `LinkQualityStatsController`'s docblock as the precedent for making recompute explicit. Not an OLD-dependency replacement. |

**Conclusion:** of the completed link-quality programme, only `BackupApiController` eliminates OLD administration dependency. The rest consolidated Generation-2 AdminCP. That is a correct and valuable outcome — it is simply a different bucket (§11), and treating it as OLD coverage would have produced a false gap closure.

---

## 5. Contextual UX vs behavior — classified cases

| Capability | Behavior ownership | Contextual access | Classification |
|---|---|---|---|
| Link-size measurement, quality metric, size correction, mismatch triage | **Laravel** — complete | Central only (`/admin/link-quality/*`). OLD and gen-2 offered recheck/size actions from the **item page**. | **BEHAVIOR_MIGRATED / CONTEXTUAL_UX_GAP** |
| `down`/`trial` archive lifecycle | **Laravel** — complete (`BackupApiController`) | No UI at all, and correctly so — it is a machine-to-machine API. But OLD's `down = 0` **worklist report** has no Laravel counterpart. | **BEHAVIOR_MIGRATED / REPORTING_GAP** |
| Location entity CRUD | **Laravel** — complete | Central. **OLD had no contextual equivalent**, so there is no UX regression. | **MIGRATED** (not a UX gap) |
| Mirror create / update / delete / re-parent | **Nobody but OLD** | — | **MIGRATION_GAP** (behavior, not UX) |

Per the brief's rule, none of the first three is marked a behavior gap merely for lacking an inline button. Conversely, the fourth is **not** softened to a UX gap: there is no Laravel behavior to reach.

---

## 6. Revised Pass 3C gap counts — these two families only

| | Pass 3C | Pass 3D | Change |
|---|---|---|---|
| Families flagged for reconciliation | 2 (link integrity, Locations) | **1** (link/mirror) | Locations deleted |
| Operations believed to be gaps | ~7 (1 + 6) | **0 from these two rows** | **−7** |
| Of those, now **MIGRATED / not applicable** | — | 6 Locations ops (de-duplicated or N/A) | |
| Of those, now **partial** | — | 1 (`KhotabLostedLink` → `REPORTING_GAP`) | |
| Of those, now **historical/broken — do not port** | — | 1 (`KhotabRepairLostedLink`) | |
| **Mirror family, re-scoped** | implied ~7 ops, owner "needs reconciliation" | **~10 ops across 4 domains, owner confirmed OLD-only** | **gap sharpened and widened** |
| Pass 3C capability families | 17 | **16** | −1 |

**Honest net reading:** the reconciliation removed 7 apparent gap-operations and one whole family, and in the same pass *enlarged* the mirror gap by proving Laravel has no mirror mutation of any kind. Pass 3D is not a net reduction in work; it is a relocation of work from a false target (Locations, link "repair") to the real one (mirror CRUD).

No other Pass 3C family was altered.

---

## 7. W2ACD ownership — source evidence

Searched: configuration, external URLs, comments, redirects, forms, admin links, documentation, historical code copies, permissions, Generation-2 AdminCP, Laravel.

| Evidence | Finding |
|---|---|
| External URLs in `modules/w2acd`, `modules/w2a_cd` | Only `http://phpnuke.org` (upstream boilerplate in `links.php`, `Comments.php`, `friend.php`). **No external administration URL, no redirect, no "moved to" comment.** |
| Generation-2 public serving | Live: `.htaccess:251-256` maps `cds-main.htm`, `cds-news.htm`, `cds-group-N.htm`, `cds-item-N.htm` → `new_modules.php?name=w2acd`; `7amlat/w2acd/{item,cds,functions}.php` exist. |
| Generation-2 admin controls | **Zero.** `grep -cE 'is_w2a_admin|inlineAdminUrl|admin_file'` returns `0` for all three w2acd files; `0` occurrences of `op=W2ACD*` anywhere in Generation 2. |
| Generation-2 AdminCP | **Exactly one touchpoint:** `admincp/index.php:468` — `UPDATE nuke_w2acd_mod SET aid = … WHERE aid = …` inside the admin-rename routine. Generation 2 **actively maintains the W2ACD moderator table's referential integrity** while offering no W2ACD administration. |
| Laravel | `W2acdGroup`, `W2acdItem` models exist (read path; `ContentSidebarWidget`, `ThumbnailService` reference W2ACD). No admin controller, no routes, no permission tokens. |
| Laravel backup programme | `BackupApiController::CATEGORY_TABLES` covers khotab, islamic_mirror, telawah, anasheed, anasheed_mirror — **W2ACD is excluded.** OLD's `modules/w2a_cd/index.php:671-678` and `admin/index.php:124,1557` do operate a `down` workflow, so W2ACD has an archive lifecycle that Laravel does not serve. |

**Conclusion.** No direct evidence that administration moved to any external system. Per the brief, the unnamed BC#6 system is **not** inferred to be W2ACD's owner. W2ACD administration remained OLD-only and is currently unowned.

New affirmative evidence *against* abandonment: Generation 2 deliberately maintains `nuke_w2acd_mod` consistency. A capability the business had retired would not have its moderator table kept correct on every admin rename.

**Business question: still open** (does the organization consider W2ACD live?) — but better framed than in Pass 3C, and no longer plausibly answered by "the external system handles it."

---

## 8. Moderator ownership — source evidence, and a significant reframe

### 8.1 What `ListModertors()` actually is

Not a list screen. Reading `modules/Anasheed/admin/index.php:2133-2155`, its `$Trans` map enumerates the capability tokens themselves:

```
active · addgroup · editgroup · delgroup · add<domain> · edit<domain> · del<domain>
addbanner · addcomment · delcomment · rearrange · addicon
addmirror · editmirror · delmirror
```

These are exactly the strings passed to `GreenZone($MyPerm)` throughout OLD's admin handlers — e.g. `GreenZone('editMirror')` at `modules/Khotab/admin/index.php:624`, `GreenZone('delmirror')` at `:665`, `GreenZone('addmods')` at `:2155`.

> **OLD's "moderator" family is the per-capability content-administration permission model.** `*ListModertors` is its grant-assignment UI; `*ModertorsLogs` is its audit view.

### 8.2 Storage and audit

| Artifact | Detail |
|---|---|
| Grants | `nuke_islamic_mod` (Khotab), `nuke_anasheed_mod`, `nuke_telawah_mod`, `nuke_w2acd_mod` |
| Audit log | `nuke_islamic_logs` via `RecordLogs($type, $target_id, $action, $extrainfo, $hidden)` → `INSERT … SET aid, module, type, target_id, action, extrainfo, hidden, ip` |
| Audit coverage | 45 `RecordLogs()` call sites in `modules/Khotab/admin/index.php`, 7 in Anasheed, 3 in Telawah |
| Enforcement | `GreenZone($MyPerm)` at `old-project/mainfile.php:1855` **begins with a bare `return;`** |

So grants are **stored, maintained and displayed — and never enforced.**

### 8.3 Generation-2 evidence

Generation 2's admin-rename routine (`admincp/index.php:~440-500`) updates **all four** moderator tables **and the audit log**, alongside the user/author/comment tables:

```
nuke_islamic_mod · nuke_anasheed_mod · nuke_telawah_mod · nuke_w2acd_mod
nuke_islamic_logs · nuke_islamic_comments · nuke_anasheed_comments
nuke_authors · nuke_users · nuke_modules · nuke_quiz_members · nuke_tutorials_members
```

This is affirmative evidence that Generation 2 treats the moderator grants and the audit trail as **live data worth keeping consistent** — while providing no UI to read or change them.

### 8.4 Laravel evidence

- **Zero references** to `nuke_islamic_mod`, `nuke_anasheed_mod`, `nuke_telawah_mod`, `nuke_w2acd_mod` or `nuke_islamic_logs` anywhere in `app/`, `routes/` or `database/`.
- Laravel's permission vocabulary is a **different, non-intersecting** 14-token set in `module.capability` form: `authors.addstuff`, `authors.liststuff`, `broadcasting.editstream`, `khotab.repair`, `khotab.uploaders`, `locations.add_location`, `locations.del_location`, `questionnaire.listallquest`, `questionnaire.listquest`, `soundcloud.update_soundcloud`, `survey.modsurvey`, `telawah.repair`, `youtube.update_youtube`.
- Laravel **has the enforcement mechanism** OLD lacks: `admin.permission:` middleware, `PermissionController`, `AdminStaffController`.
- `AdminStaffController` exposes `index`, `create`, `store` only — **no rename/update**. So Laravel introduces no cascade risk to the four `*_mod` tables; rename remains Generation-2-owned.

### 8.5 Conclusion

**No Generation-2 or Laravel mechanism supersedes it.** But the right conclusion is not "schedule a moderator feature." It is:

> OLD's moderator model is the **authorization specification** for content administration — a populated, per-moderator, per-capability grant set across four live tables, plus an audit-log contract. Any migration of Family A, OA-1 or OA-3 needs that specification, because OLD's own enforcement is dead and Laravel's token vocabulary contains nothing for content-item administration.

Migrating a mirror-edit capability *without* it would ship "any authenticated admin may rewrite any download URL, unlogged" — strictly worse than OLD's intent, though identical to OLD's current behavior.

**Whether humans still operate the assignment workflow: open.** Source cannot answer; Generation 2's active maintenance is evidence against abandonment, not proof of use.

---

## 9. Mobile / Downloads ownership — source evidence

### 9.1 What it manages

`old-project/modules/Mobile/` (`index.php`, `admin/`, `d_config.php`, `language/`, `voteinclude.php`) operates the PHP-Nuke **Downloads** module tables:

- `nuke_downloads_downloads` (5 references)
- `nuke_downloads_categories` (4)
- `nuke_downloads_votedata` (1)

Its ~15 admin operations are the standard Downloads moderation set: `DownloadsValidate`, `DownloadsModDownload`, `DownloadsDelDownload`, `DownloadsDelCat`, `DownloadsDelComment`, `DownloadsDelVote`, `DownloadsCleanVotes`, `DownloadsListModRequests`, `DownloadsChangeModRequests`, `DownloadsListBrokenDownloads`, `DownloadsDelBrokenDownloads`, `DownloadsIgnoreBrokenDownloads`, `DownloadsChangeIgnoreRequests`, `DownloadsDownloadCheck`, `DownloadsDelEditorial`, `DownloadsDelNew`.

### 9.2 Reachability across generations

| Generation | Evidence |
|---|---|
| OLD | 23 `mobile*` rewrite rules (`.htaccess:804-816+`) → `new_modules.php?name=Mobile`; `new_modules.php` exists and includes `modules/Mobile/index.php`. **Routed and servable.** |
| Generation 2 | 24 `mobile*` rewrite rules exist in `7amlat/.htaccess` → `new_modules.php?name=Mobile`. But Generation 2 has **no `new_modules.php`**, **no `modules/` directory**, **no `Mobile` code anywhere**, and **zero references to the downloads tables**. Every Generation-2 mobile URL 404s. **The rules are copied-forward dead configuration.** |
| Laravel | **Zero references** to `nuke_downloads_*`. |

### 9.3 Conclusion

Distinguishing as the brief requires:

- **Content still consumed:** cannot be determined from source. OLD's routes are live and servable; whether traffic arrives is operational. Generation 2's apparent mobile routes are *proven dead*, which narrows the question to OLD alone.
- **Admins still actively operate the workflow:** cannot be determined from source. **Open.**

Both the public content and its administration are OLD-exclusive. This family needs a discovery pass of its own before it can be ranked honestly against the others; a single read-only check of whether `nuke_downloads_downloads` holds recent rows would resolve most of it without involving the business.

---

## 10. Revised ranking of remaining contextual-admin families

Ranked on: required business capability · public-visible decay risk · absence of another owner · dependencies/shared data · business-question blockers · implementation complexity. **Not** on operation count.

| Rank | Family | Capability | Decay risk | Other owner | Blocker | Why this rank |
|---|---|---|---|---|---|---|
| **1** | **Mirror / link CRUD** (~10 ops, 4 domains) | required | **High — public-visible** | **None** (zero mirror mutations in `app/`) | none | The only family scoring worst-case on risk *and* ownership with no blocker. A dead mirror is a broken download on a live public page with no repair path in any system. Laravel already owns every adjacent column (`down`, `trial`, `linksize`, `online`, `percent`, `checktime`, `backupme`), so the seam is small and the integration points already exist. One design serves Khotab, Anasheed, Telawah and W2ACD. |
| **2** | **Moderator permission model + audit log** (5 ops, 4 tables + 1 log) | required (authorization) | n/a | **None** | none | **Not a standalone feature — the authorization specification for rank 1.** Should be designed *with* rank 1, not after it: OLD's `GreenZone()` is dead, so rank 1 would otherwise ship unauthorized and unlogged. Laravel already has `admin.permission:` middleware and `PermissionController`; the grants are populated data in tables Generation 2 actively maintains. |
| **3** | **OA-3 category assignment** | required | Medium (mis-filing becomes permanent) | None | none | Needs **re-specification** before implementation: the *add* half is Generation-2-delegated and the *remove* half is OLD-only, and §3.1 reveals a second call site (`modules/Locations/index.php:256-257`) carrying a `{$type}` dimension (group/series) plus "add/remove to **all**" bulk semantics that the original OA-3 scope may not cover. |
| **4** | **W2ACD administration** (~22 ops) | unknown | High if live | None | **§7 — ownership unresolved** | Business-question gated. Note rank 1 already covers `W2ACDEditMirror`/`DeleteMirror`/`InsertMirror` if designed domain-agnostically, which reduces this to non-mirror W2ACD ops. |
| **5** | **OA-1 Advfiles** | required | Low–medium | None | none | Stays parked. Pass 3A's analysis stands; §3.1 adds a confirmed second call site (`:486-487`) with the file's remaining display credit rendered beside it. |
| **6** | **`down = 0` worklist report** (1 op) | useful | Low | Lifecycle owner exists | none | `BEHAVIOR_MIGRATED / REPORTING_GAP`. Cheap — a listing over data Laravel already writes. Natural companion to rank 1's UI. |
| **7** | **Link-quality contextual access** | useful | None | **Laravel owns behavior** | none | `BEHAVIOR_MIGRATED / CONTEXTUAL_UX_GAP`. Pure UX integration: surface the existing `recheck*`/`fixSize*` actions on the item page. Should ride along with rank 1, not be scheduled separately. |
| **8** | **Mobile / Downloads** (~15 ops) | unknown | Unknown | None | **§9 — usage unknown** | Needs its own discovery pass. One read-only data check first. |
| — | **Locations** | — | — | — | — | **Removed.** §3. |

---

## 11. Scope buckets preserved

### Already moved into Generation-2 / AdminCP
Link-size measurement, the `percent` quality metric, mismatch worklists, size correction (`admincp/khotab/stats.php`, `stats_khotab.php`, `stats_khotab_200mb.php`, `telawah/stats.php`). **Since consolidated into Laravel** (`LinkQualityStatsController`, `LinkSizeChecker`, 3 jobs, `RecomputeLinkQualityPercentAction`). Not OLD migration work — Laravel does not lack it.
Admin rename and moderator-table/audit-log integrity maintenance (`admincp/index.php:~440-500`). Laravel has no rename, so no regression is introduced.

### Still executed by OLD — primary surface
Mirror create/update/delete/re-parent (4 domains) · moderator grant assignment and audit views · W2ACD administration · Advfiles · category assignment (removal half) · Mobile/Downloads moderation · the `down = 0` worklist report · plus the Pass 3C families not revisited here.

### Already owned by Laravel — removed from the gap
Location entity CRUD · the `down`/`trial`/`linksize`/`online`/`downloader`/`checktime`/`booking` lifecycle · link measurement, metric, triage and size correction · identity, permissions, surveys, questionnaires, broadcasting, uploads, integrations.

### Behavior migrated but contextual UX missing — UX integration, not behavior migration
Link recheck and size correction from the item page (rank 7).

### Business / external-system gated — do not implement until ownership is resolved
W2ACD administration (§7) · Mobile/Downloads (§9) · operational use of the moderator workflow (§8.5).

### Historical / dead / broken — do not port for parity
`KhotabRepairLostedLink` (no handler, undefined `$id`) · `KhotabUpSeries` via Generation 2 (undefined `$admin_file`) · the three `khotab/old.php`, `channels/old.php`, `vars/old.php` snapshots · Generation 2's 24 dead `mobile*` rewrite rules · `modules/Locations/index.php` if OLD runs PHP ≥ 7 (§3.6) · `MirrorExport` pending evidence of use.

---

## 12. Recommended FIRST implementation unit

> **Mirror / link CRUD across all four content domains, designed together with the moderator permission vocabulary and the `nuke_islamic_logs` audit contract as its authorization model.**

It is the only candidate that simultaneously:

1. serves a **required** business capability (content remains downloadable);
2. carries **confirmed public-visible decay risk** — a dead mirror is a broken download on a live page, and no system can currently fix a URL;
3. has **no other owner** — verified: no `Mirror` create/update/delete/save anywhere in `app/`;
4. sits on **data Laravel already owns end-to-end** (`down`, `trial`, `linksize`, `online`, `percent`, `checktime`, `backupme`, `downloader`), so the integration seam is small and well-understood;
5. has **no business-question blocker**;
6. **de-duplicates across four domains** (Khotab, Anasheed, Telawah, W2ACD) and absorbs the Locations page's two mirror call sites — one design, four domains, highest leverage per unit of work;
7. and provides the natural home for rank 6 (the `down = 0` report) and rank 7 (contextual access to the existing recheck/fix actions).

**One design constraint, stated now rather than discovered later:** OLD's enforcement is dead (`GreenZone()` returns immediately), so this unit cannot be "ported faithfully" and be correct. The permission tokens OLD *intended* — `addmirror`, `editmirror`, `delmirror`, per moderator per content domain — already exist as populated grants in `nuke_islamic_mod`, `nuke_anasheed_mod`, `nuke_telawah_mod`, `nuke_w2acd_mod`, and Laravel already has the middleware to enforce them. Rank 2 is therefore scoped **into** rank 1, not scheduled after it.

**Before implementation**, two analysis items remain, both cheap and neither requiring the business:

- confirm the mirror table schema and row counts per domain on current production (read-only), to size the work and confirm W2ACD's mirror table name;
- decide whether the audit-log contract is reproduced on `nuke_islamic_logs` or re-homed, since Laravel currently writes nothing to it.

---

## 13. Business questions — answered from source vs still open

| Question | Answered from source? | Detail |
|---|---|---|
| Does an external system administer W2ACD? | **Answered: no evidence of one.** | No external URL, redirect, form, comment or AdminCP link. Not attributed to BC#6's unnamed system. §7 |
| Is W2ACD considered live by the business? | **Open.** | New evidence against abandonment: Generation 2 maintains `nuke_w2acd_mod` on every admin rename. |
| What does the OLD moderator workflow do? | **Fully answered.** | Per-capability permission model (15 tokens) + grant assignment UI + audit log; 4 grant tables + `nuke_islamic_logs`; enforcement dead. §8 |
| Does anything supersede the moderator workflow? | **Fully answered: no.** | Zero Laravel references to all five tables; non-intersecting permission vocabularies. §8.4 |
| Do humans still operate moderator assignment? | **Open.** | Requires operational evidence. |
| What does `Mobile/` administer? | **Fully answered.** | PHP-Nuke Downloads module: `nuke_downloads_downloads`, `_categories`, `_votedata`; ~15 moderation ops. §9.1 |
| Is Mobile content still consumed? | **Partially answered.** | Generation 2's mobile routes are **proven dead**; Laravel has none. Narrowed to OLD alone, where routes are live and servable. Traffic is operational. §9.2 |
| Do admins still operate Downloads moderation? | **Open.** | Needs its own discovery pass. |
| Does OLD run PHP ≥ 7 (i.e. is `modules/Locations/index.php` fatal)? | **Open, but immaterial.** | One read-only request to `locations.htm` settles it. Family B dissolves on behavioral grounds either way. §3.6 |

**Still genuinely requiring us or the business:** W2ACD liveness; moderator-workflow operational use; Downloads operational use. All three are *usage* questions, not *ownership* questions — ownership is now resolved from source in every case.

---

## 14. Documentation files changed

| File | Change |
|---|---|
| `docs/reviews/old-contextual-admin-ownership-reconciliation-pass-3d.md` | **Created** — this document. |
| `docs/reviews/old-contextual-admin-surface-pass-3c.md` | **Short reconciliation note added** to §5 and §15 only: Locations row marked dissolved, link-integrity row marked reclassified, both pointing here. Pass 3C is otherwise unchanged. |

Pass 3A and Pass 3B are **not** modified — no factual error was found in either during this pass.

---

## Appendix — reproduction

```bash
OLD=/mnt/Projects/Php/pure/old-project
G2=/mnt/Projects/Php/pure/7amlat
LAR=/mnt/Projects/Php/pure/way2allah-migration/laravel-project

# §2.2 — KhotabRepairLostedLink has no handler (returns exactly 1: the hidden input)
grep -rc 'KhotabRepairLostedLink' --include=*.php $OLD | grep -v ':0'

# §2.4 — Laravel has no mirror mutation (returns nothing)
grep -rn 'Mirror::create\|Mirror::\|->mirrors()' $LAR/app --include=*.php \
  | grep -iE 'create|update|delete|save|insert'

# §2.3 — the down/trial lifecycle in Laravel
sed -n '405,420p' $LAR/app/Domain/Content/Http/Controllers/BackupApiController.php
sed -n '42,49p'   $LAR/app/Domain/Content/Http/Controllers/BackupApiController.php   # 5 tables, no w2acd

# §3.1 — the six "Locations" ops, all in one file
grep -nE 'op=(AdvfilesDeleteAdvFile|AdvfilesEditAdvFile|cat_add_to_sub|cat_del_from_sub|KhotabDeleteMirror|KhotabEditMirror)' \
  $OLD/modules/Locations/index.php

# §3.2 — no OLD operation names a location (returns nothing)
grep -rhoE 'op=[A-Za-z_0-9]*[Ll]ocation[A-Za-z_0-9]*' --include=*.php $OLD | sort -u

# §3.6 — no eregi shim anywhere, and the live modules were de-eregi'd
grep -rn 'function eregi' $OLD
for f in Khotab Anasheed Telawah w2acd; do \
  echo "$f: eregi=$(grep -c 'eregi(' $OLD/modules/$f/index.php)"; done

# §7 — gen2's single W2ACD AdminCP touchpoint; zero W2ACD admin controls
grep -n 'nuke_w2acd_mod' $G2/admincp/index.php
grep -cE 'is_w2a_admin|inlineAdminUrl|admin_file' $G2/w2acd/*.php

# §8 — the moderator permission vocabulary, and the tables gen2 maintains
sed -n '2133,2155p' $OLD/modules/Anasheed/admin/index.php
sed -n '440,500p'   $G2/admincp/index.php | grep -oE 'UPDATE `nuke_[a-z_]+`' | sort -u
for t in nuke_islamic_mod nuke_anasheed_mod nuke_telawah_mod nuke_w2acd_mod nuke_islamic_logs; do \
  echo "$t: $(grep -rl $t $LAR/app $LAR/routes $LAR/database 2>/dev/null | wc -l) Laravel refs"; done

# §9 — gen2 cannot serve Mobile despite 24 rewrite rules
grep -ci 'mobile' $G2/.htaccess; ls -d $G2/modules $G2/new_modules.php 2>/dev/null
grep -rhoE '\$prefix \. "_[a-z_]+"' $OLD/modules/Mobile | sort -u
```
