# Pass 3A — Advfiles Old Administration Deep Dive

**Status.** Forensic analysis only. No migration code, no Laravel behaviour change, no legacy behaviour removed, nothing committed. Reads with `old-phpnuke-administration-forensic-discovery.md` (Pass 1), `old-phpnuke-khotab-inline-admin-pass-2a.md` (Pass 2A) and `old-admin-scope-reset.md`.

---

## 1. Executive conclusion

**Advfiles is not a CRUD module. It is a three-state editorial approval workflow for promotional "featured" media attached to Khotab content, and it is live.** 11 operations verified, all in `old-project` only.

An "Advfile" is a promotional/featured media payload attached 1:1 to a Khotab item: raw embed markup (`code`), an optional audio URL, a `credit` number labelled in the UI as "مرات العرض المتبقية لهذا الملف" (*remaining display count for this file*), and two thumbnail flags. The workflow state lives on `nuke_islamic_khotab.advfile` as `0 → -1 → 1`, and `AdvfilesMain()` is literally the pending-approval queue: it selects `WHERE advfile = -1`.

Three findings dominate:

**1. Generation 2 actively links staff back to `old.way2allah.com` for this operation — in source, with a line number.** `7amlat/khotab/functions.php:296-299` renders the "طلب رئيسية" button as `href="$inlineAdminUrl$admin_file.php?op=KhotabRequestAdvFile&id=..."`, and `7amlat/w2a_config.php:63` defines `$inlineAdminUrl = 'https://old.way2allah.com/'`. This is documentary proof of exactly the architecture the owner described, and it answers Pass 2A's open reachability question affirmatively.

**2. That pattern is not confined to Advfiles.** The same `$inlineAdminUrl` mechanism appears ~40 times across generation 2, targeting **35 distinct old-project operations** spanning Khotab, Fatawa, Telawah, Questionnaire, Categories, Surveys and Gallery (§7.2). Generation 2 does not merely *lack* this administration — it *delegates* it, by hyperlink, at runtime. This is a materially better dependency map than the guard-density proxy used in the scope reset, because generation 2 names its own dependencies.

**3. The workflow is live but partially broken, and the break is a column-name defect.** 1,129 items are PUBLISHED, 12 are PENDING right now, and 1,133 `nuke_islamic_advfile` rows exist. But four consumer call sites query `WHERE khid = …` against a table that **has no `khid` column** — verified as a hard SQL error. The module's own display function `ShowAdFile()` queries correctly by `id`; only the Khotab-side duplicates are broken.

Laravel owns **none** of this. `advfile` appears only as `@property int $advfile` in `KhotabItem`'s docblock — no model for the table, no route, no permission, no rendering. **`MIGRATION_GAP`**, with one component (`credit`) whose business requirement cannot be determined from source.

---

## 2. OLD Advfiles inventory

| Artefact | Path | Lines |
|---|---|---|
| Admin dispatcher | `modules/advfiles/admin/case.php` | 24 |
| Admin links/menu | `modules/advfiles/admin/links.php` | 20 |
| **Admin handlers** | `modules/advfiles/admin/index.php` | **526** |
| Public module | `modules/advfiles/index.php` | **531** |
| Language | `admin/language/lang-arabic.php`, `lang-english.php`, `language/lang-*.php` | — |

Also present: `.LCK` lock files beside four sources (editor artefacts) and `_notes/dwsync.xml` (Dreamweaver sync metadata) — deployment detritus, not code.

**Cross-module entry points into Advfiles:**

| From | Line | Target |
|---|---|---|
`old-project/modules/Khotab/index.php` | 1037-1039 | `AdvfilesEditAdvFile`, `AdvfilesDeleteAdvFile` (Pass 2A G-17) |
`old-project/modules/Khotab/admin/index.php` | 384-423 | `RequestAdvFile()` — the request producer |
**`7amlat/khotab/functions.php`** | **296-299** | **`KhotabRequestAdvFile` via `$inlineAdminUrl` → `old.way2allah.com`** |

**Inline guards in `modules/advfiles/index.php`:** 5 verified, at lines **34, 202, 264, 416, 463**.

---

## 3. Operation matrix

All 11 operations verified from `modules/advfiles/admin/*.php`. Entry point for every one is `admin.php?op=<name>` (`$admin_file = "admin"`, `config-w2a.php:39`). **Authorization actually executed: `is_w2a_admin()` at `admin.php:35` only** — every `GreenZone("AdvFile")` call in this module is *commented out* in source (`:53, :76, :115, :135, :184, :222`) in addition to `GreenZone()` itself being a no-op (Pass 1 §11). Double-disabled.

| # | Op | Handler | Params | Reads | Writes | Mutation via GET? |
|---|---|---|---|---|---|---|
| 1 | `AdvfilesMain` | `AdvfilesMain()` `:197` | — | `nuke_islamic_khotab WHERE advfile = -1 ORDER BY id ASC` `:202` | none | n/a (list) |
| 2 | `AdvfilesInsertAdvFile` | `InsertAdvFile($id)` `:50` | `id` | `nuke_islamic_khotab` `:56` | none (renders form) | no |
| 3 | `AdvfilesConfirmInsertAdvFile` | `ConfirmInsertAdvFile($id,$credit,$code,$audio_url)` `:72` | `id, credit, code, audio_url` | `nuke_islamic_khotab` `:80` | **`INSERT nuke_islamic_advfile SET id,credit,code,audio_url`** `:98` **+ `UPDATE nuke_islamic_khotab SET advfile=1`** `:100` | form POST |
| 4 | `AdvfilesEditAdvFile` | `:111` region | `id` | `nuke_islamic_advfile` `:138`, `nuke_islamic_khotab` `:141` | none (renders form) | no |
| 5 | `AdvfilesConfirmEditAdvFile` | `ConfirmEditAdvFile($id,$credit,$code,$audio_url)` `:111` | `id, credit, code, audio_url` | `nuke_islamic_khotab` `:124` | **`UPDATE nuke_islamic_advfile SET …`** `:119` | form POST |
| 6 | `AdvfilesDeleteAdvFile` | `DeleteAdvFile($id)` `:130` | `id` | `nuke_islamic_advfile` `:177`, `nuke_islamic_khotab` `:180` | none (confirm screen) | no |
| 7 | `AdvfilesConfirmDeleteAdvFile` | `ConfirmDeleteAdvFile($id)` `:175` | `id` | — | **`DELETE FROM nuke_islamic_advfile`** `:187` **+ `UPDATE nuke_islamic_khotab SET advfile=0`** `:188` | **YES — destructive via GET** |
| 8 | `AdvfilesCreditAdvFile` | `Credit()` `:235` | — | `nuke_islamic_advfile WHERE credit>'0' ORDER BY credit DESC` `:244` | none (bulk form) | n/a |
| 9 | `AdvfilesConfirmCreditAdvFile` | `ConfirmCredit($AdvFile)` `:261` | `AdvFile[]` (array) | — | **`update nuke_islamic_advfile set credit=$val WHERE id=$key`** `:269`, looped | form POST |
| 10 | `AdvfilesResetAdvFile` | `ResetAdvFile($id)` `:219` | `id` | `nuke_islamic_advfile` `:278` | credit reset | **likely GET** |
| 11 | `AdvfilesUploadAdvFileImage` | `UploadAdvFileImage($id)` `:417` | `id` + file | — | filesystem (thumbnail); `small_thumb`/`large_thumb` presumed | POST-gated `:422` |

Plus the **producer** outside the module: `RequestAdvFile($id)` at `modules/Khotab/admin/index.php:384` — `op=KhotabRequestAdvFile`, reads khotab + author, and if `advfile == 0` executes **`UPDATE nuke_islamic_khotab SET advfile='-1' WHERE id='$id'`** (`:418`), then meta-refreshes to the item page after 5s. **Mutation via GET.** This one *does* call `GreenZone('AdvFile')` uncommented at `:388` — still a no-op.

And the **public display**: `ShowAdFile($id, $mode)` at `modules/advfiles/index.php:457` — `SELECT * FROM nuke_islamic_advfile WHERE id = $id` `:461`, echoes `$AdvFile[code]` raw, and if `audio_url` is set renders a **Flash `player.swf`** object (`:470-476`).

---

## 4. Business workflow reconstruction

Derived from code, not assumed. Every transition cited.

```
                    ┌─────────────────────────────────────────┐
                    │  khotab.advfile = 0        NONE         │  291,450 items
                    │  "طلب رئيسية" button shown:             │
                    │   old-project AdminPanel (Pass 2A P-16) │
                    │   gen2 khotab/functions.php:296-299 ───►│──┐ links to
                    └──────────────────┬──────────────────────┘  │ old.way2allah.com
                                       │                         │
          op=KhotabRequestAdvFile  →  RequestAdvFile($id)        │
          Khotab/admin/index.php:384                             │
          guard: if (advfile > 0) refuse (:414)                  │
          UPDATE khotab SET advfile='-1'   (:418)  ◄─────────────┘
                                       ▼
                    ┌─────────────────────────────────────────┐
                    │  khotab.advfile = -1    REQUESTED       │  12 items NOW
                    │  appears in the pending queue:          │
                    │  AdvfilesMain()  advfiles/admin:197     │
                    │  SELECT … WHERE advfile = -1   (:202)   │
                    └──────────────────┬──────────────────────┘
                                       │
          op=AdvfilesInsertAdvFile  →  form (:50)
          op=AdvfilesConfirmInsertAdvFile (:72)
          INSERT nuke_islamic_advfile SET id, credit, code, audio_url  (:98)
          UPDATE khotab SET advfile=1                                  (:100)
                                       ▼
                    ┌─────────────────────────────────────────┐
                    │  khotab.advfile = 1     PUBLISHED       │  1,129 items
                    │  advfile row exists     1,133 rows      │
                    │  public: ShowAdFile()   index.php:457   │
                    │  manage: Edit / Credit / Reset / Upload │
                    └──────────────────┬──────────────────────┘
                                       │
          op=AdvfilesConfirmDeleteAdvFile (:175)
          DELETE FROM nuke_islamic_advfile  (:187)
          UPDATE khotab SET advfile=0       (:188)
                                       ▼
                              back to NONE
```

**Live state distribution** (local mirror, read-only):

| `khotab.advfile` | Meaning | Count |
|---|---|---|
| `0` | NONE | 291,450 |
| `-1` | **REQUESTED / pending** | **12** |
| `1` | PUBLISHED | 1,129 |
| `2` | **UNKNOWN — no code path sets this** | 1 |

`nuke_islamic_advfile`: 1,133 rows; 1,121 join a `advfile=1` khotab row. So ~12 rows are inconsistent with the flag in one direction or the other, and 8 advfile rows have no matching khotab row at all (orphans).

**What `credit` actually is — and the gap in it.** The UI labels it "remaining display count" (`modules/advfiles/index.php:464`; same text at `Khotab/index.php:1038`). 18 rows have `credit > 0`, 1,115 have `0`, maximum 22,242. **But `credit` is never decremented anywhere in any of the three codebases** — searched for `credit - 1`, `credit-1`, `credit = credit`, decrement patterns across all three; zero matches. So the quota is set and bulk-edited by admins (`Credit()`/`ConfirmCredit()`) and ordered by (`ORDER BY credit DESC`), but nothing consumes it. Functionally it behaves as a **manual priority/ranking number**, not a metered quota. Whether the metering was intended and never built, or abandoned, is not determinable from source — §12 Q1.

---

## 5. Database / schema semantics

`nuke_islamic_advfile` — verified via `SHOW COLUMNS`:

| Column | Type | Null | Key | Default | Semantics |
|---|---|---|---|---|---|
| `id` | `int(11)` | YES | **UNI** | NULL | **Shared PK with `nuke_islamic_khotab.id`** — 1:1. Not an auto-increment, not a declared FK. |
| `code` | `text` | YES | | NULL | Raw HTML/embed markup, echoed unescaped (`index.php:467`) |
| `credit` | `int(11)` | YES | | 0 | "Remaining display count" per the UI; **never decremented** — see §4 |
| `audio_url` | `text` | YES | | NULL | Audio file URL, played via Flash `player.swf` |
| `small_thumb` | `tinyint(1)` | NO | | 0 | Thumbnail flag — set by `UploadAdvFileImage()`; exact semantics **UNKNOWN** |
| `large_thumb` | `tinyint(1)` | NO | | 0 | As above, **UNKNOWN** |

`nuke_islamic_khotab.advfile` `int` — the workflow state column (§4). No declared foreign key in either direction; the relationship is by convention only.

**No `khid` column exists.** This is load-bearing for §6: four consumer sites query it.

**Orphan possibilities, measured:** 8 of 1,133 advfile rows have no matching khotab row. 12 rows are inconsistent between `advfile=1` and row presence. Nothing enforces either direction.

**Laravel representation:** none. No model, no migration, no schema definition for `nuke_islamic_advfile`. `KhotabItem` documents the `advfile` *column* at `:26` and `:71` (`@property int $advfile`) and never reads it.

---

## 6. Producer / consumer map across all three generations

| Location | Role | Evidence |
|---|---|---|
`old-project/modules/Khotab/admin/index.php:384-423` | **PRODUCER** — creates the REQUESTED state | `UPDATE khotab SET advfile='-1'` `:418` |
**`7amlat/khotab/functions.php:296-299`** | **ADMIN ENTRY POINT (gen 2 → gen 1)** | `href="$inlineAdminUrl$admin_file.php?op=KhotabRequestAdvFile&id=…"` |
`old-project/modules/advfiles/admin/index.php:98,100` | **MUTATOR** — publish | INSERT advfile + `advfile=1` |
`old-project/modules/advfiles/admin/index.php:119` | **MUTATOR** — edit | UPDATE advfile |
`old-project/modules/advfiles/admin/index.php:187,188` | **MUTATOR** — delete | DELETE + `advfile=0` |
`old-project/modules/advfiles/admin/index.php:269` | **MUTATOR** — bulk credit | `set credit=$val WHERE id=$key` |
`old-project/modules/advfiles/admin/index.php:197-202` | **ADMIN ENTRY POINT** — pending queue | `WHERE advfile = -1` |
`old-project/modules/advfiles/index.php:457-480` | **PUBLIC PRESENTATION** — works | `WHERE id = $id` ✓ |
`old-project/modules/Khotab/index.php:1035` | **CONSUMER — BROKEN** | `WHERE khid=` → `Unknown column 'khid'` |
`old-project/modules/index.php:905` | **CONSUMER — BROKEN** | same defect |
`old-project/modules/Locations/index.php:483` | **CONSUMER — BROKEN** | same defect |
`old-project/modules/Khotab/_index.php:909` | **CONSUMER — BROKEN** | same defect; `_index.php` also a DEAD/UNREACHABLE candidate |
`7amlat/khotab/old.php`, `channels/old.php` | **DEAD/UNREACHABLE CANDIDATE** | zero `.htaccess` references (`grep -c` = 0) |
`7amlat/english/themes/…/بعد السفر_index.php` | **DEAD/UNREACHABLE CANDIDATE** | English theme, `GreenZone("AdvFile")` call sites |
`laravel/app/Domain/Content/Models/KhotabItem.php:26,71` | **UNKNOWN / inert metadata** | `@property int $advfile` only; never queried or rendered |
`laravel/resources/**` | **nothing** | zero matches for `advfile` |

The broken-consumer defect is confirmed empirically, not inferred: executing the exact query from `Khotab/index.php:1035` returns `ERROR 1054 (42S22): Unknown column 'khid' in 'where clause'`.

---

## 7. Generation 1 vs Generation 2

### 7.1 Advfiles specifically

**Generation 2 has no Advfiles implementation at all.** No `advfiles/` directory, no admin module, no handler, no table access. Searched by behaviour and by table/column, not only by name: the only `advfile` references in `7amlat` are
- `khotab/functions.php:296-299` — a **link to generation 1**,
- `khotab/old.php` and `channels/old.php` — unreachable (no `.htaccess` rule),
- an English-theme file.

**Verdict: generation 2 does not own this workflow. It delegates it by hyperlink.** No field mapping is possible because there is nothing to map.

### 7.2 The wider pattern this exposes

`$inlineAdminUrl` (= `https://old.way2allah.com/`, `w2a_config.php:63`) appears ~40 times in generation 2, targeting **35 distinct old-project operations**:

| Domain | Operations generation 2 delegates to `old.way2allah.com` |
|---|---|
| **Khotab** | `KhotabEditKhotab`, `KhotabDeleteKhotab`, `KhotabMoveKhotab`, `KhotabInsertMirror`, `KhotabDetailes` (×2), `KhotabUploadPdf`, `Khotab_EditPdf`, `KhotabDeletePdf`, `KhotabUploaddocx`, `Khotab_Editdocx`, `KhotabDeletedocx`, `KhotabDelete_banner`, `KhotabRequestBanner`, **`KhotabRequestAdvFile`**, `KhotabWaitingFB`, `cat_add_to_item` |
| **Fatawa** | `w2a_fatwa_add`, `w2a_fatwa_edit`, `w2a_fatwa_del`, `fatwa_add_question` (×2), `fatwa_edit_question` (×3), `fatwa_del_question` (×3), `fatwa_add_general_question`, `fatwa_edit_general_question`, `fatwa_del_general_question`, `fatwa_many_questions` (×2), `del_all_topics_for_catg` |
| **Telawah** | `TelawahEditTelawah`, `TelawahDeleteTelawah`, `TelawahmoveTelawah`, `Telawahaddlink`, `TelawahWaitingFB` |
| **Questionnaire** | `question_move_topics`, `question_many_topics`, `question_delete_topics` |
| **Categories** | `cat_add_to_sub*`, `cat_del_from_sub*` (`functions.php:485-496`) |
| **Surveys** | `polledit`, `create` (`surveys/functions.php:132,263`) |
| **Gallery** | `zipped_albums/` path (`gallery/functions.php:85`) |

**This should replace the guard-density proxy as the primary dependency map for this workstream.** Generation 2 enumerates its own dependencies on generation 1, in source, with line numbers — far stronger evidence than inferring from where admin guards happen to cluster.

**It also bears materially on Business Confirmation #6, and I am flagging rather than deciding.** Generation 2 routes `KhotabEditKhotab`, `KhotabDeleteKhotab`, `KhotabUploadPdf` and eleven more Khotab content-CRUD operations to `old.way2allah.com`. #6 recorded that the system handling Khotab/Telawah content CRUD is *"explicitly not old.way2allah.com's admin panel."* This is **new source evidence** in tension with that answer — not the "direct business evidence" the scope reset required to reopen it. Per that instruction the #6 hypothesis stays closed; but this finding should be put to the business, because it is the strongest evidence yet on either side. See §12 Q4.

---

## 8. Laravel coverage comparison

| OLD behaviour | Generation 2 | Laravel | Status |
|---|---|---|---|
| Request featured file (`advfile 0 → -1`) | **links to gen 1** (`khotab/functions.php:296`) | none | **MIGRATION_GAP** |
| Pending-approval queue (`WHERE advfile = -1`) | none | none | **MIGRATION_GAP** |
| Create advfile + publish (`advfile → 1`) | none | none | **MIGRATION_GAP** |
| Edit advfile (`code`, `audio_url`, `credit`) | none | none | **MIGRATION_GAP** |
| Delete advfile + unpublish (`advfile → 0`) | none | none | **MIGRATION_GAP** |
| Bulk credit management + ordering | none | none | **MIGRATION_GAP**, requirement UNKNOWN (§12 Q1) |
| Reset credit | none | none | **MIGRATION_GAP**, requirement UNKNOWN |
| Upload thumbnail image | none | none | **MIGRATION_GAP**, semantics UNKNOWN |
| Public display of `code` / `audio_url` | none | **none** — zero matches in `resources/` | **MIGRATION_GAP** |
| Flash `player.swf` audio playback | none | none | **DEAD_CANDIDATE** — Flash end-of-life 2020; non-functional in any current browser regardless of migration |
| `advfile` column awareness | — | `@property int $advfile` only | inert metadata, not coverage |
| Permissions for the workflow | — | **none** — no `khotab.advfile`-equivalent key seeded | **MIGRATION_GAP** |

**Laravel owns nothing of this workflow.** The presence of an `@property` docblock entry is explicitly *not* coverage — Pass 3A's brief warned against inferring completeness from a model's existence, and that warning applies precisely here.

---

## 9. Reachability classification

| Operation | Code-level reachable | UI reachable | Operationally confirmed in use |
|---|---|---|---|
`KhotabRequestAdvFile` | **Yes** | **Yes — from BOTH generations** (old AdminPanel P-16; gen 2 `khotab/functions.php:296`) | **Yes — 12 rows sit at `advfile = -1` now** |
`AdvfilesMain` | Yes | Yes — `admin/links.php` menu | Indirect: the queue it lists is non-empty |
`AdvfilesInsertAdvFile` / `ConfirmInsert` | Yes | Yes — from the pending queue | **Yes — 1,129 published items, 1,133 rows** |
`AdvfilesEditAdvFile` | Yes | **Yes — from the public Khotab item page** (Pass 2A G-17) and `ShowAdFile()` `:464` | Unknown |
`AdvfilesDeleteAdvFile` / `ConfirmDelete` | Yes | Yes — same two places | Unknown |
`AdvfilesCreditAdvFile` / `ConfirmCredit` | Yes | Yes — admin menu | Partial: 18 rows carry `credit > 0` |
`AdvfilesResetAdvFile` | Yes | Not found — **URL-reachable only** | Unknown |
`AdvfilesUploadAdvFileImage` | Yes | Not found — **URL-reachable only** | Unknown |
`ShowAdFile()` (public) | Yes | Yes — `advfiles/index.php` op dispatch | Unknown |
4 × `WHERE khid=` consumers | **Reachable but fail** | Yes | **Broken** — hard SQL error |

**Evidence that generation 2 sends staff back to generation 1: YES, proven** — `7amlat/khotab/functions.php:296-299`. This was Pass 2A's open question.

Per the brief: `AdvfilesResetAdvFile` and `AdvfilesUploadAdvFileImage` have no discovered UI link but are URL-reachable and **are not** classified dead on that basis.

---

## 10. Authorization / security findings

Documented separately from migration behaviour; nothing fixed in this pass. Additive to Pass 1 §13 and Pass 2A §F.

| # | Finding | Evidence |
|---|---|---|
| A-1 | **Per-operation authorization doubly disabled.** `GreenZone()` returns immediately (Pass 1 §11) *and* every `GreenZone("AdvFile")` call in this module is commented out | `advfiles/admin/index.php:53, 76, 115, 135, 184, 222`; `:240, 264` for `"rearrange"` |
| A-2 | **Destructive delete via GET, no CSRF token.** `op=AdvfilesConfirmDeleteAdvFile&id=N` performs `DELETE` + `UPDATE` | `:187-188`; linked from the public Khotab item page (Pass 2A G-17) |
| A-3 | **State mutation via GET.** `op=KhotabRequestAdvFile&id=N` writes `advfile='-1'` | `Khotab/admin/index.php:418` |
| A-4 | **Unescaped integer interpolation throughout.** `WHERE id= $id`, `set credit=$val WHERE id=$key` — `$id` is `intval()`-cast at some call sites but `ConfirmCredit()`'s `$key`/`$val` come from a request **array** with no visible cast | `:269` |
| A-5 | **Stored raw HTML echoed unescaped.** `echo $AdvFile[code];` — `code` is admin-authored embed markup rendered on a public page with no filtering. Stored-XSS by design. | `advfiles/index.php:467` |
| A-6 | **Request-controlled array parameter.** `ConfirmCredit($AdvFile)` iterates a request-supplied array as `id => credit` pairs, updating arbitrary rows in one request | `:261-270` |
| A-7 | **Flash dependency.** `audio_url` playback uses `player.swf` | `advfiles/index.php:470-476` |
| A-8 | **Broken consumers emit SQL errors on public pages** — four `WHERE khid=` sites. With `display_errors` on (old-project's `.user.ini` formerly set it) this leaks schema | §6 |

**Must not be reproduced in Laravel:** A-1 through A-6. A-5 in particular — any migration of `code` needs an explicit decision about whether admins may author raw HTML, and that is a design question, not a port.

---

## 11. Migration-gap matrix

**1. Laravel definitely already owns:** nothing in this workflow.

**2. Laravel clearly lacks** — and the business demonstrably still uses (12 pending, 1,129 published):
- the request action (`advfile 0 → -1`)
- the pending-approval queue (`WHERE advfile = -1`)
- create-and-publish (`INSERT` + `advfile → 1`)
- edit (`code`, `audio_url`)
- delete-and-unpublish (`DELETE` + `advfile → 0`)
- public rendering of the published payload
- a permission covering the workflow

**3. Requirement UNKNOWN from source:**
- `credit` — set, bulk-edited and ordered by, but never decremented. Is it a quota that was never metered, or a priority number? (§12 Q1)
- `small_thumb` / `large_thumb` — written by `UploadAdvFileImage()`; display semantics not located
- `advfile = 2` — one row; no code path sets it
- whether raw-HTML `code` authoring must survive, or whether structured fields would satisfy the business (§12 Q2)

**4. Historical/security mechanics that must NOT be copied:** A-1…A-6, the Flash player (A-7), the `khid` column-name defect, and the 1:1 shared-PK-without-a-foreign-key convention.

---

## 12. Business confirmation questions

Only what source cannot answer.

**Q1 — Is `credit` a display quota or a priority number?** *Established:* the UI labels it "remaining display count"; 18 rows carry values up to 22,242; admins bulk-edit it and the admin list orders by it descending; **nothing in any of the three codebases ever decrements it.** *Unknown:* whether metered display was intended and never built, or deliberately abandoned. *Why it matters:* a quota needs a decrementing consumer and an exhaustion rule in Laravel; a priority number needs only an orderable integer. Different data model and different public-rendering logic.

**Q2 — Must admins keep authoring raw HTML in `code`?** *Established:* `code` is `text`, echoed unescaped on a public page (A-5), and is how the promotional payload is expressed today. *Unknown:* whether the business needs arbitrary markup or whether the real content is a small fixed set of shapes (embed + audio + thumbnail). *Why it matters:* it decides whether Laravel reproduces a stored-XSS surface, sanitises it, or replaces it with structured fields — which changes the whole admin screen.

**Q3 — Is the "featured/homepage" promotion still an editorial process?** *Established:* a complete three-state workflow with 12 items currently pending approval and 1,129 published. *Unknown:* whether anyone still works the pending queue, or whether those 12 have simply sat there. *Why it matters:* an active workflow justifies the full build; a dormant one may need only read-side rendering plus a migration of existing rows.

**Q4 — Given that generation 2 routes Khotab content CRUD to `old.way2allah.com` in source, does Business Confirmation #6's "not old.way2allah.com" still hold?** *Established:* `7amlat` contains ~40 hardcoded links to `old.way2allah.com/admin.php?op=…` covering 35 operations including `KhotabEditKhotab`, `KhotabDeleteKhotab` and `KhotabUploadPdf` (§7.2). *Unknown:* whether staff use those links, or a different unnamed system. *Why it matters:* it determines whether the ~20 Khotab capabilities currently held as `EXTERNALLY_OWNED — OWNER SYSTEM UNKNOWN` are in fact `MIGRATION_GAP`s. This is the single highest-leverage open question in the workstream. **Raised, not decided** — the scope reset requires direct business evidence to reopen #6, and this is source evidence.

**Q5 — Are the 8 orphan advfile rows and the 1 `advfile = 2` row meaningful?** *Established:* measured; no code path produces either. *Unknown:* whether they are historical corruption or a forgotten state. *Why it matters:* decides whether migration needs a reconciliation step.

---

## 13. Recommended migration boundary

No implementation. Proposed smallest behaviour-preserving ownership model, **conditional on Q1–Q3**.

**Laravel should own, as one vertical slice:**

1. **Read side first** — a model for `nuke_islamic_advfile` on the `main` connection (1:1 with `KhotabItem` by shared `id`, declared as a relation in Eloquent rather than a database FK, since none exists), plus public rendering of a published advfile. Read-only, no mutation, no new tables, immediately removes the public dependency and is independently useful.
2. **The pending queue** — an AdminCP listing of `advfile = -1`. Read-only, one query, directly visible value: it is the thing staff currently cross hosts to see.
3. **The three state transitions** — request (`0 → -1`), publish (`-1 → 1` + insert), unpublish (`1 → 0` + delete) as explicit Actions with POST routes, CSRF, and a new permission. This is where the security mechanics are deliberately *not* reproduced: POST not GET, authorization actually enforced, and `code` handling decided by Q2.
4. **`credit` / thumbnails — deferred** until Q1 and the thumbnail semantics are answered. Carrying the column read-only costs nothing and avoids designing against a guess.

**Explicitly excluded:** the Flash player (A-7); the `khid` consumer sites (defects, not behaviour to preserve — and §6 shows they have never worked, so there is no behaviour there to lose); raw-HTML echo without a Q2 decision; and anything that would have Laravel write `nuke_islamic_khotab.advfile` while old-project also writes it, which would need a cut-over decision first.

**Future implementation units, in dependency order:** OA-1a read model + public render → OA-1b pending queue → OA-1c transitions + permission → OA-1d credit/thumbnails (gated on Q1).

---

## 14. Source / file / line evidence index

| Claim | Evidence |
|---|---|
11 operations | `modules/advfiles/admin/*.php`, `case '<op>'` extraction |
Handlers | `modules/advfiles/admin/index.php:50, 72, 111, 130, 175, 197, 219, 235, 261, 417` |
Publish transition | `:98` INSERT, `:100` `advfile=1` |
Unpublish transition | `:187` DELETE, `:188` `advfile=0` |
Pending queue | `:197-202`, `WHERE advfile = -1` |
Bulk credit | `:244` select, `:269` update |
Request producer | `modules/Khotab/admin/index.php:384-423`, `:418` `advfile='-1'` |
Public display | `modules/advfiles/index.php:457-480`, `:461` `WHERE id`, `:467` raw echo, `:470-476` Flash |
Inline guards | `modules/advfiles/index.php:34, 202, 264, 416, 463` |
Khotab inline entry | `modules/Khotab/index.php:1035-1039` |
**Gen 2 → gen 1 link** | **`7amlat/khotab/functions.php:296-299`** + `7amlat/w2a_config.php:63` |
Gen 2 delegation surface | `7amlat/{functions,surveys/functions,gallery/functions,fatawa/functions}.php`, ~40 sites, 35 ops |
Schema | `SHOW COLUMNS FROM nuke_islamic_advfile` |
`khid` defect | `ERROR 1054 (42S22): Unknown column 'khid' in 'where clause'` |
State counts | `GROUP BY advfile` on `nuke_islamic_khotab` |
`credit` never decremented | negative search across all three projects |
Laravel coverage | `@property int $advfile`, `KhotabItem.php:26, 71`; zero matches in `routes/`, `resources/` |
GreenZone commented out | `advfiles/admin/index.php:53, 76, 115, 135, 184, 222, 240, 264` |

---

## 15. Unknowns and confidence

**High confidence:** the 11 operations and their handlers; the three-state workflow and every transition; the schema; the `khid` defect; generation 2's absence of an implementation and its delegation links; Laravel's complete absence of coverage; `credit` never being decremented; the live state counts.

**Medium confidence:** `small_thumb`/`large_thumb` semantics (writer located, display not); `AdvfilesResetAdvFile`'s exact effect (handler located at `:219`, body not fully traced); whether `UploadAdvFileImage` sets those flags.

**Low confidence / UNKNOWN:** whether the workflow is *operationally* still worked by staff (Q3); `credit`'s intended role (Q1); the `advfile = 2` row; the 8 orphans; whether production's `nuke_islamic_advfile` schema matches the local mirror — **the `khid` finding rests on the local schema**, and while the module's own code consistently uses `id`, a production `SHOW COLUMNS` would settle whether `khid` was dropped or never existed.

**Explicitly not concluded:** nothing here is marked `RETIRED`. Two operations lack a discovered UI link and are recorded as URL-reachable only, not dead. The `advfile = 2` row and the orphans are recorded as unexplained, not as corruption.
