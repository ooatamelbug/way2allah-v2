# Pass 3E.1 — Mirror Schema Production Verification Plan

**Status:** ANALYSIS / PREPARATION ONLY — no implementation, no code changes, no deployment, no commit.
**Date:** 2026-10-12
**Predecessor:** Pass 3D (accepted). Mirror/link CRUD is the designated first implementation unit; this pass prepares its schema ground truth.
**No production access.** Every production figure in this document is a *query to be run by the owner*. Nothing here reports a production result.
**Out of scope by instruction:** authorization vocabulary and the `nuke_islamic_logs` audit contract (next sub-pass). Recorded where encountered, not designed.
**Scope:** the four mirror domains only — Khotab, Anasheed, Telawah, W2ACD.

---

## 0. Production reconciliation (Check A complete, 2026-10-12)

Check A was run by the owner against `w2acp_db2025-2`. All four tables exist. The sections below are
**unchanged where production confirmed them** and **annotated inline where production corrected them**.
Statements marked *(source-derived)* that production contradicted are struck and replaced, not silently edited.

### 0.1 Confirmed by production

| Source-derived expectation | Production |
|---|---|
| Four mirror tables exist with the predicted names | **Confirmed** — `nuke_islamic_mirror`, `nuke_anasheed_mirror`, `nuke_telawah_mirror`, `nuke_w2acd_mirror` |
| Telawah's item FK is `telawa_id`, size is `size`, media flag is `video` | **Confirmed** — all three |
| No ordering column in any domain | **Confirmed** |
| Duplicate-URL guards are application-only | **Confirmed — no unique constraint on any of the four tables** |
| Khotab/Anasheed/W2ACD share `khid` + `linksize` + `vedio` + `backupme` + `time` + `down` + `trial` | **Confirmed** |
| Khotab and Anasheed parents carry `mirror` / `lastmirror` | **Confirmed** (`nuke_islamic_khotab.mirror` DEFAULT 0, `lastmirror` nullable; `nuke_anasheed_anasheed.mirror` DEFAULT 0, `lastmirror` DEFAULT 0) |
| W2ACD has two physical mirror representations | **Confirmed** — `nuke_w2acd_mirror` exists *and* `nuke_w2acd_w2acd` carries `link`, `cd`, `thumbnail`. Which one holds live data is still unknown. |
| `uploader` / `addeddate` are Khotab-only | **Confirmed** |

### 0.2 Corrected by production — four material changes

**(1) Telawah's mirror table is structurally outside both the backup and link-quality programmes.**
Production shape: `id`, `telawa_id`, `comment`, `link` **VARCHAR(255)**, **`extension`**, `size`, `video`,
`backupme` **CHAR**, **`creation_date`**. It has **no** `khid`, `linksize`, `time`, `down`, `trial`, `booking`,
`percent`, `checktime`, `online`, `downloader`.

This is a stronger statement than Pass 3E.1 made. Telawah mirrors cannot participate in the backup queue
(no `down`/`trial`/`booking`) or the quality metric (no `percent`/`online`/`checktime`) **at the schema level** —
not merely because Laravel does not write them. Two columns were not predicted from source at all:
`extension` (no OLD code traced writes it) and `creation_date` (OLD's INSERT sets no timestamp, so this
column is either defaulted or left empty by every write path in OLD).

**(2) Telawah's parent has no counter contract.** `nuke_telawah_telawah` carries **no `mirror` and no
`lastmirror`**. Pass 3E.1 §3.2 described OLD's commented-out Telawah counter code as evidence of an
"incomplete copy-paste" and §8 item 2 posed it as a product decision. **Production resolves that half of the
question: the code is commented out because the columns do not exist.** Constraint **C5 does not apply to
Telawah**, and no Telawah counter behavior should be designed. The audit-log and permission halves of §8
item 2 remain open.

**(3) `down` DEFAULT 2 and `trial` DEFAULT 0 resolve constraint C4.** On `nuke_islamic_mirror`,
`nuke_anasheed_mirror` and `nuke_w2acd_mirror`, a newly inserted mirror — which no OLD INSERT initializes —
lands at `down = 2, trial = 0`. The backup API hands out work on exactly `down = 2 AND trial < max`
(`BackupApiController:337-373`). **So a new mirror is immediately backup-eligible.** It does *not* land on
the `down = 0` lost-link worklist, as Pass 3E.1 left open. C4's instruction stands — do not initialize these
fields — but its rationale is now settled rather than conditional.

**(4) Only Khotab and Anasheed have a parent-FK index.** `idx_khid (khid, id)` exists on both — a covering
secondary index for parent-scoped mirror lookups. **`nuke_telawah_mirror` and `nuke_w2acd_mirror` have
`PRIMARY (id)` only**, so every parent-scoped lookup on those two is a clustered-index scan. This affects
both the eventual design and the cost of Checks B and C, which is why Check B now opens with a free
row-count estimate (§6, B0). Reported cardinality was 0 for both; per the owner's instruction that is
**not** read as an exact row count.

### 0.4 Check B results (production, exact)

| Domain | Mirror rows | Distinct parents | Parents with >1 mirror | Max per parent | Avg per parent |
|---|---:|---:|---:|---:|---:|
| Khotab | **432,974** | 235,003 | 181,437 (**77.2%**) | 10 | 1.84 |
| Anasheed | **18,809** | 12,337 | 4,538 (36.8%) | 9 | 1.52 |
| Telawah | **1** | 1 | 0 | 1 | — |
| W2ACD | **0** | 0 | 0 | — | — |

Three conclusions follow directly:

**(1) Multi-mirror is the normal case for Khotab, not an edge case.** 77.2% of Khotab parents carry more
than one mirror. Any per-item admin UI must treat the mirror list as the primary object, not an occasional
extra. Both domains are bounded at ≤10 per parent, so no pagination is needed inside an item.

**(2) Telawah mirror support was never put into production use — 1 row.** This is not an isolated fact. It
converges with five independent signals already recorded: no quality/backup columns exist (§0.2.1); the
parent has no counter columns (§0.2.2); there is no parent-FK index (§0.2.4); `GreenZone()` is commented out
(§3.2); and the audit-log calls are commented out (§3.2). **Telawah is therefore removed from the first
implementation unit** — see §0.5. It is not a migration gap to close but an unfinished legacy feature with
one row of data, and whether it should exist at all is a product question.

**(3) `nuke_w2acd_mirror` is exactly empty — confirmed, not estimated.** Per the owner's instruction this
does **not** establish that W2ACD has no live mirror/download representation. It establishes only that the
*normalized* one is unused. C4 resolves the parent `link`/`cd` question.

### 0.4.1 Check C4 results — W2ACD classified (production, exact)

`nuke_w2acd_w2acd` holds **39 rows**. All 39 have a meaningful `link` **and** a meaningful `cd`; zero are
NULL, empty, whitespace-only or delimiter-only. Link elements per item: min 1, max 3, **avg 1.92**. And
decisively: **`mirror` is 0 on all 39, `lastmirror` is unset on all 39**, while all 39 carry live links.

**Classification: the comma `link`/`cd` representation on the parent is the live and only W2ACD
multi-link representation. Normalized `nuke_w2acd_mirror` has never been used.**

| Conclusion | Status |
|---|---|
| W2ACD download / multi-link capability is **not dead** | **Established** — 39/39 items carry real links, 1–3 each |
| Normalized `nuke_w2acd_mirror` is unused in current production data | **Established** — 0 rows (Check B) |
| `mirror` / `lastmirror` do **not** track the comma list | **Established** — all zero / all unset, yet all 39 have links |
| `mirror` / `lastmirror` are **not authoritative W2ACD state** | **Established** — do not read or maintain them for W2ACD |
| W2ACD stays **outside** the first mirror CRUD unit | **Confirmed** |
| Normalized W2ACD CRUD is **not** a current parity requirement | **Confirmed** — the existence of OLD handlers is not evidence the capability is in use |
| Whether Laravel should preserve the comma representation or normalize it | **Deliberately left open** — a later design decision, not a prerequisite established here |

### 0.4.2 Check D results — counters, orphans, duplicates (production, exact)

#### D1 — parent counter integrity

| | Khotab | Anasheed |
|---|---:|---:|
| Parents total | 298,251 | 16,639 |
| Counter drifted | **11,700 (3.92%)** | **39 (0.23%)** |
| …too high | 11,694 | 26 |
| …too low | 6 | 13 |
| `mirror = 0` but mirrors exist | 2 | 0 |
| Max counter | 10 | 9 |

**`parent.mirror` is NOT a trusted invariant.** Drift is material in Khotab and small but real in Anasheed,
and it is **overwhelmingly biased toward over-counting** (11,694 of 11,700 in Khotab). That direction is
**consistent with failure windows created by separate non-transactional statements** — OLD performs the
mirror write and the parent-counter write as independent, unwrapped statements (`Khotab/admin:530-535`,
`:693`, `:362-364`), which provides a plausible mechanism. **The aggregate production data does not
establish historical causation**, and no claim about what actually produced the drift is made here. Any statement in this document implying the
counter is a sound invariant is superseded by this table.

**Anasheed `lastmirror` — the 4,615 figure must not be read as broken state.** D1's
`lastmirror_unset = 4,615` was computed over *all* 16,639 parents, including the many with no mirrors at
all. A follow-up restricted to parents that actually have mirror rows established: **12,015 mirrored
parents, 0 with `lastmirror` unset, 12,015 with it set — 100% populated.** `lastmirror` is therefore
correctly maintained for every existing Anasheed parent that has mirrors. The 4,615 is a denominator
artifact, not a defect.

#### D3 — orphan mirrors

| Domain | Orphan mirror rows |
|---|---:|
| Khotab | **4,059** |
| Anasheed | **480** |

Real production state, not hypothetical. But the design consequence must be split in two, and only the
first is parity:

1. **CRUD robustness in the presence of orphans** — required. Delete and re-parent must not fault or
   mis-decrement when the parent row is absent. This is parity-adjacent: OLD's `ConfirmDeleteMirror`
   returns early printing `المادة غير موجودة` without deleting (`Khotab/admin:683-687`), so an orphan is
   currently *undeletable* through the normal path.
2. **A new orphan cleanup / repair capability** — **not parity, and not assumed into this unit.** OLD has
   no such capability. Building one is a new feature requiring separate justification. §6.2's earlier
   wording ("an orphan-cleanup affordance becomes a candidate for the first unit") overreached and is
   withdrawn.

#### D2 — duplicate data

| Scope tested | Result |
|---|---|
| Anasheed, `(khid, MD5(link))` | **21 duplicate groups** |
| Khotab, `(khotab.author, MD5(mirror.link))` | **329 duplicate groups** |

Two methodological notes carried from the owner's run: the drafted Anasheed author-scope query failed
because production `nuke_anasheed_anasheed` has **`author_id`**, not `author` — and author scope was
conceptually unnecessary for Anasheed anyway, since source establishes OLD has no equivalent guard there.
Both Khotab figures come from an INNER JOIN, so they cover **existing-parent mirrors only** and exclude the
4,059 orphans.

**What these numbers do and do not establish:**

- `(khid, link)` is **demonstrably not** a production uniqueness invariant in Anasheed. Do **not** add a
  per-parent UNIQUE constraint or duplicate rejection merely because a Laravel CRUD is being introduced.
- For Khotab, 329 author-scoped duplicate groups mean **the production data does not satisfy author-scoped
  uniqueness globally.** This does **not** prove the OLD guard is absent, ineffective or irrelevant — §3.5
  traces it end-to-end and shows exactly how both facts hold together.
- **A DB-level UNIQUE constraint is unsafe for either domain** at either scope. Existing data violates both.
- **Duplicate counts alone do not define the intended policy.** What the business wants enforced is a
  separate question from what the data currently contains; neither 21 nor 329 settles it.

### 0.5 Implementation-scope interpretation, revised

Pass 3D ranked mirror CRUD as "~10 operations across 4 content domains." Production narrows that materially.

| Domain | In first unit? | Rationale |
|---|---|---|
| **Khotab** | **Yes** | 432,974 live rows; `idx_khid` present; backup-programme table (`CATEGORY_TABLES[2]`); `Mirror` model exists; parent counters exist; full CRUD + re-parent in OLD. |
| **Anasheed** | **Yes** | 18,809 live rows; identical column vocabulary and index; `CATEGORY_TABLES[5]`; `AnasheedMirror` model exists; parent counters exist. |
| **Telawah** | **No — deferred** | 1 row; no quality/backup columns; no parent counters; no FK index; permission and audit calls commented out. Becomes open item 5, not unit-1 scope. |
| **W2ACD** | **No — gated on C4** | Normalized table exactly empty; parent `link`/`cd` representation exists and is read by all three generations (§4.2, corrected). Classification required first. |

**Revised first-unit surface: 7 operations across 2 domains** — Khotab insert / edit / delete / re-parent,
and Anasheed insert / edit / delete. The 5 remaining operations (Telawah ×2, W2ACD ×3) are deferred, not
cancelled.

This is a genuine simplification rather than a scope cut: the two included domains are homogeneous on every
axis that matters — same columns, same index, same backup enrolment, same parent-counter contract, same
model shape. The three real deltas remain per-domain configuration (label column `comment` vs `title`,
edit-rewrites-`linksize` on Anasheed only, duplicate-URL scope author-scoped vs none). §3.4's verdict that
"Telawah needs its own adapter" is superseded: Telawah is simply out of scope, so **one service can own the
entire first unit.**

### 0.3 Smaller production details worth carrying into design

- **`link` column types differ:** `TEXT` on Khotab, Anasheed and W2ACD; **`VARCHAR(255)` on Telawah.**
  Telawah has a hard 255-character URL limit the other three do not.
- **`linksize` width differs:** `BIGINT` on Khotab, **`INT` on Anasheed.** A signed `INT` caps at ~2.1 GB,
  so a genuinely large Anasheed file cannot be represented. Pre-existing, not introduced by this unit.
- **`backupme` type differs:** integer-like on Khotab/Anasheed/W2ACD, **`CHAR` on Telawah.**
- **W2ACD's mirror table has `hits`, `cd`, `archgif`, `thumbnail`, `hidden`, `downloader`** but A2's
  enumeration lists **no `percent` and no `booking`**. If that absence is exact, W2ACD holds the
  *measurement* columns (`online`, `checktime`) without the *metric* column, so
  `LinkQualityStatsController`'s percent-threshold queries could not run against it as written. Treated as
  likely-absent pending opportunistic confirmation, not asserted.
- A2's Khotab and Anasheed enumerations were given as "important facts" rather than exhaustive column
  lists, so the fuller 24- and 22-column lists in the `Mirror` / `AnasheedMirror` docblocks remain the
  reference for those two, now corroborated on every column A2 did name.

---

## 1. The four expected mirror tables

| Domain | Mirror table | Parent (item) table | Source evidence |
|---|---|---|---|
| Khotab | **`nuke_islamic_mirror`** | `nuke_islamic_khotab` | `modules/Khotab/admin/index.php:513` (INSERT), `:626` (UPDATE), `:692` (DELETE), `:361` (re-parent) |
| Anasheed | **`nuke_anasheed_mirror`** | `nuke_anasheed_anasheed` | `modules/Anasheed/admin/index.php:168` (INSERT), `:291` (UPDATE), `:342` (DELETE) |
| Telawah | **`nuke_telawah_mirror`** | `nuke_telawah_telawah` | `modules/Telawah/admin/index.php:1488` (INSERT), `:1544` (UPDATE), `:1584` (DELETE); live read at `modules/Telawah/index.php:350-351` |
| W2ACD | **`nuke_w2acd_mirror`** | `nuke_w2acd_w2acd` | `modules/w2acd/admin/index.php:94` (INSERT), `:168` (UPDATE), `:214` (DELETE); live read at `modules/w2acd/index.php:374-378` |

Reference-count corroboration across `old-project`: `nuke_anasheed_mirror` 29, `nuke_islamic_mirror` 21+6, `nuke_telawah_mirror` 2 (+5 admin call sites via `$prefix` concatenation), `nuke_w2acd_mirror` 9 reads + 3 writes.

### 1.1 Two prerequisite disambiguations

**`modules/w2a_cd/` is not a W2ACD module.** It is a CD-styled *view over Anasheed*: `modules/w2a_cd/index.php` has **0** references to `nuke_w2acd_*` and **48** to `nuke_anasheed_*`, and its operations are named `Anasheed*`. Only `modules/w2acd/` (33 `nuke_w2acd_*` refs, 0 anasheed) owns the W2ACD tables. Pass 3C counted both directories; for mirror purposes only `modules/w2acd/` is in scope, and `modules/w2a_cd/`'s mirror ops collapse into the Anasheed domain.

**`nuke_islamic_mirror` is shared with the backup programme**, not Khotab-exclusive — see §5.

---

## 2. Normalized schema matrix (source-derived)

Columns are stated only where source reads or writes them, per the instruction not to infer from naming. "—" means *no source evidence in this domain*, not *absent from the table*; Check A resolves that.

| Semantic field | Khotab | Anasheed | Telawah | W2ACD |
|---|---|---|---|---|
| **Table** | `nuke_islamic_mirror` | `nuke_anasheed_mirror` | `nuke_telawah_mirror` | `nuke_w2acd_mirror` |
| **Primary key** | `id` | `id` | `id` | `id` |
| **Item FK** | `khid` | `khid` | **`telawa_id`** | `khid` |
| **URL column** | `link` | `link` | `link` | `link` |
| **Human label** | `comment` | **`title`** | `comment` | `comment` |
| **Size column** | `linksize` | `linksize` | **`size`** | `linksize` |
| **Media-type flag** | `vedio` | `vedio` | **`video`** | `vedio` |
| **Backup opt-in** | `backupme` | `backupme` | `backupme` | `backupme` |
| **Created timestamp** | `time` (unix int) | `time` (unix int) | **`creation_date`** — exists in production, written by no OLD path (§0.2.1) | `time` (unix int) |
| **Ordering column** | **none** | **none** | **none** | **none** |
| **Quality / status** | `down`, `trial`, `online`, `percent`, `checktime`, `broken`, `booking` | same set | **none exist** (§0.2.1) | `down`, `trial`, `online`, `checktime`, `broken`; **no `percent`/`booking`** |
| **Download counter** | `hits` | `hits` | — | `hits` (read at `:374`) |
| **Attribution** | `downloader`, `uploader` | `downloader` | **none exist** | `downloader` |
| **Other documented** | `backup`, `cd`, `archgif`, `thumbnail`, `hidden`, `addeddate` | `backup`, `cd`, `archgif`, `thumbnail`, `hidden` | **`extension`** (unpredicted, §0.2.1) | `hits`, `cd`, `archgif`, `thumbnail`, `hidden` |
| **`link` type** *(production)* | `TEXT` | `TEXT` | **`VARCHAR(255)`** | `TEXT` |
| **`linksize` type** *(production)* | `BIGINT` | **`INT`** | `size` | — |
| **Parent-FK index** *(production)* | `idx_khid (khid,id)` | `idx_khid (khid,id)` | **none** | **none** |
| **Parent counters** *(production)* | `mirror`, `lastmirror` | `mirror`, `lastmirror` | **neither exists** | `mirror`, `lastmirror` |
| **Laravel model** | `Mirror` | `AnasheedMirror` | **none** | **none** |
| **Laravel column list** | 24 cols, confirmed Fact in model docblock | 22 cols, confirmed Fact | — | — |

**No domain has an ordering column.** Display order is derived: `modules/w2acd/index.php:378` orders `BY nuke_w2acd_mirror.id ASC`; the Khotab and Anasheed item pages render mirrors in natural/PK order. Any "reorder mirrors" capability would therefore be **new behavior, not migrated behavior** — out of scope for the first unit.

**No domain distinguishes mirrors by server or source.** `vedio`/`video` is audio-vs-video, not a provider. Provider is inferred from the URL at render time only (`Mirror::extensionIconFilename()` reproduces the legacy 3-way soundcloud/youtube/extension branch).

### 2.1 Columns each side touches

| | OLD writes | OLD reads | Laravel writes | Laravel reads |
|---|---|---|---|---|
| Khotab | `khid`, `link`, `comment`, `linksize`, `vedio`, `backupme`, `time`, `trial` | `*` | `checktime`, `online`, `percent`, `linksize`, `hits`, `down`, `trial`, `downloader`, `booking` | `*` via `Mirror` |
| Anasheed | `khid`, `link`, `title`, `linksize`, `vedio`, `backupme`, `time`, `trial` | `*` | `down`, `trial`, `linksize`, `online`, `downloader`, `checktime`, `booking`, `hits` | `*` via `AnasheedMirror` |
| Telawah | `telawa_id`, `link`, `comment`, `size`, `video`, `backupme` | `*` | **nothing** | **nothing** |
| W2ACD | `khid`, `link`, `comment`, `linksize`, `vedio`, `backupme`, `time`, `trial` | `id`, `comment`, `hits`, `link`, `linksize` (+ join to `nuke_w2acd_advanced_m`) | **nothing** | **nothing** |

---

## 3. CRUD semantics per domain, and where they differ

### 3.1 The brief's nine questions

| Question | Answer |
|---|---|
| **Does edit change URL only or other metadata?** | Always more than the URL. Khotab/W2ACD: `comment`, `vedio`, `backupme`, `link`, and **`trial` reset to 0**. Anasheed: same **plus `linksize`**. Telawah: `comment`, `video`, `backupme`, `link` — and **no `trial` reset**. |
| **Does insert initialize quality fields?** | **No — in any domain.** No INSERT sets `down`, `online`, `percent`, `checktime`, `broken`, `booking`, `downloader` or `hits`. All four rely on **table DEFAULTs**, which Check A must capture: a new mirror's initial `down` value decides whether the backup programme immediately picks it up (§5). |
| **Does delete physically delete?** | **Yes, all four.** `DELETE FROM … WHERE id = …`. No soft-delete, no `hidden` flip, no archive. Khotab/Anasheed/W2ACD additionally decrement the parent's `mirror` counter; Telawah does not. |
| **Does moving change ownership or display order?** | Khotab only, and it changes **ownership**: `UPDATE … SET khid = $khid`, then `mirror+1` on the new parent and `mirror-1` on the old. Display order is unaffected (none exists). No other domain has a move operation. |
| **Are duplicate URLs allowed?** | **Divergent, and all three answers are different.** Khotab: blocked, **author-scoped** (`:498-509`, `die()`). Telawah: blocked, **group-scoped** (`:1466-1474`, `die()`). Anasheed: **no check**. W2ACD: **no check**. None is a DB-level constraint — **production confirms no unique index on any of the four tables** (§0.1). |
| **Can one item have multiple mirrors?** | Yes in all four — the parent's denormalized `mirror` counter exists precisely to count them. Check B quantifies the real distribution. |
| **Is there an explicit ordering mechanism?** | **No.** §2. |
| **Are mirrors distinguished by server/source/type?** | Only audio-vs-video (`vedio`/`video`). Provider is render-time URL inference. §2. |
| **Does any mutation trigger another process?** | **Yes, three kinds.** (a) **Parent denormalization** — insert sets `lastmirror` + `mirror+1`; delete sets `mirror-1`; move does both (Khotab/Anasheed/W2ACD only). (b) **Backup enrolment** — `backupme = 1` opts the row into the Desktop-App backup programme that Laravel's `BackupApiController` serves (§5). (c) **Audit log** — `RecordLogs`/`RecordLog` → `nuke_islamic_logs`, divergently (below). |

### 3.2 Divergence table — the design-relevant deltas

| Behavior | Khotab | Anasheed | Telawah | W2ACD |
|---|---|---|---|---|
| Size input unit | MB → bytes (`×1024²`) | MB → bytes | MB → bytes, **stored in `size`** | MB → bytes |
| Edit resets `trial = 0` | **yes** | **yes** | **no** | **yes** |
| Edit rewrites `linksize` | no | **yes** | no | no |
| Insert sets `time` | yes | yes | **no** | yes |
| Parent `lastmirror` / `mirror+1` on insert | yes | yes | **no — and no parent contract exists.** Production `nuke_telawah_telawah` has neither column; the commented-out code at `:1495-1498` targets columns that are absent (§0.2.2) | yes |
| Parent `mirror-1` on delete | yes | yes | **no — no parent contract** (§0.2.2) | yes |
| Duplicate-URL guard | author-scoped | **none** | group-scoped | **none** |
| Re-parent / move | **yes** | no | no | no |
| `GreenZone()` permission call | live | live | **commented out** (`:1540-1541`, `:1580-1582`) | live |
| Audit log on insert | `RecordLogs` ×2 (`item` hidden=1, `mirror` hidden=0) | `RecordLog` ×1 | **commented out** | **none** |
| Audit log on edit | ×1 (`mirror`) | ×1 (`item`) | **commented out** | **none** |
| Audit log on delete | ×2 | ×1 | **commented out** | **none** |
| Redirect target | `/khotab-item-{khid}.htm` | `var-item-{khid}.htm` | `modules.php?name=…&op=Detailes&khid={telawa_id}` | `modules.php?name=…&op=Detailes&khid={khid}` |

`RecordLog` (singular, `includes/logs.php:226`, `$hidden` defaults `'0'`) and `RecordLogs` (per-module copies, e.g. `modules/Khotab/admin/index.php:429`) **both write `nuke_islamic_logs`**, distinguished by a `module` column. Anasheed's and Telawah's 4-argument calls are valid against the shared function — not fatal, as a signature mismatch would first suggest.

### 3.3 Khotab's move is a cross-page cookie clipboard, not a form

Fully traced, because it is the one operation whose UX cannot be inferred from the handler:

1. On a Khotab item page, each mirror row carries a "نقل الوصلة" (move link) control → `khotab-MirrorMove-{mirror_id}.htm` (`modules/Khotab/index.php:1221`). This is a **public-page** op, not an `$admin_file` op.
2. `MirrorMove($id)` (`:1855-1866`) sets `setcookie('MirrorMove', serialize($Mirror), time() + 3600, '/', '.way2allah.com')` — a one-hour, domain-wide clipboard holding the serialized mirror row.
3. On **any other** item page, `:1008-1013` detects the cookie and renders a "paste here" control → `$admin_file.php?op=KhotabMoveMirror&id={cookie.id}&khid={this item}`.
4. `MoveMirror($id, $khid)` (`admin/index.php:342-372`) performs the re-parent and then **clears the cookie** with `time() - 3600`.

Design consequences: the operation is inherently two-request and stateful; its state is a `.way2allah.com`-wide cookie carrying a serialized DB row; and step 1 lives on a public page while step 4 lives in admin. A Laravel equivalent should keep the two-step *interaction* but must not reproduce a serialized-row cookie.

### 3.5 Khotab duplicate-URL guard — complete source trace

Required by the owner to explain how **329 production author-scoped duplicate groups** coexist with a guard
that rejects author-scoped duplicates. Source only; nothing below is inferred beyond what code establishes.

#### 3.5.1 The guard

Exactly one site, in `ConfirmInsertMirror` (`modules/Khotab/admin/index.php:497-509`), after
`GreenZone('addmirror')` (`:475`) and after the parent-existence check (`:484-495`):

```php
$authorId = (int) ($Khotab['author'] ?? 0);
$sql = "SELECT {$prefix}_islamic_mirror.id, comment, khid, {$prefix}_islamic_khotab.author
        FROM {$prefix}_islamic_mirror, {$prefix}_islamic_khotab
        WHERE {$prefix}_islamic_mirror.link = '$linkSql'
          AND {$prefix}_islamic_khotab.author = '$authorId'
          AND {$prefix}_islamic_mirror.khid = {$prefix}_islamic_khotab.id";
$Mirror = $db->sql_fetchrow($db->sql_query($sql));
if (is_array($Mirror) && (int) ($Mirror['id'] ?? 0) > 0) {
    die('هذه الوصلة مضافة بقاعدة بيانات الموقع في المادة : …');
}
```

#### 3.5.2 Answers to each traced question

| Question | Source answer |
|---|---|
| Which operations invoke it? | **`ConfirmInsertMirror` only.** One call site in the entire codebase. |
| Insert vs edit vs re-parent? | **Insert only.** `ConfirmEditMirror` (`:596`, committing at `:626`) and `MoveMirror` (`:342`) contain no duplicate check of any kind. |
| Exact predicate | `mirror.link = '<new link>' AND khotab.author = '<target item's author, cast to int>' AND mirror.khid = khotab.id` |
| Scope breadth | **All mirrors whose parent item belongs to that author** — across every one of that author's items, series and groups. It does **not** narrow by `vedio`, `hidden`, `ser_id` or `group_id`. |
| Orphan mirrors | **Excluded.** The `mirror.khid = khotab.id` equi-join is an inner join, so the 4,059 orphan mirrors are invisible to the guard — the same blind spot as the production test. |
| `author IS NULL` treatment | `(int) ($Khotab['author'] ?? 0)` coerces NULL to `0`, and the predicate becomes `khotab.author = '0'`. In MySQL that **never matches a NULL `author`**. So if the column is nullable, two NULL-author items can accumulate the same link indefinitely, and a NULL-author item is compared only against literal-`0`-author items. **Whether `author` is nullable was not reported by A3** — stated conditionally, not asserted. |
| Exact / case-sensitive / collation-dependent? | `=` on a `TEXT` column, so it uses the **column's collation**, not a byte comparison. If that collation is a `_ci` variant (the utf8mb3 default is `utf8mb3_general_ci`), the guard is **case-insensitive and therefore broader than the production `MD5(link)` test**, which is byte-exact. The guard would reject some pairs the 329 figure does not count, so **329 is a lower bound** under the guard's own semantics. The mirror table's collation was not reported in A2 — stated conditionally. |
| Current mirror excluded during edit? | **Moot.** There is no guard on edit, so there is nothing to exclude from. |
| Can re-parent create a state the guard would reject? | **Yes, definitively.** `MoveMirror` (`:361`) sets `khid` to an arbitrary target with no link check, so a move can place two identical links under one author. |

#### 3.5.3 Every live write path, and whether it is guarded

Enumerated exhaustively over `old-project` for `INSERT INTO` / `UPDATE` / `DELETE FROM` against
`nuke_islamic_mirror`:

| Path | Writes `link`? | Changes `khid`? | Guarded? |
|---|---|---|---|
| `Khotab/admin:513` `ConfirmInsertMirror` | yes (insert) | yes (insert) | **YES** — the only guarded path |
| `Khotab/admin:626` **`ConfirmEditMirror`** | **yes** | no | **no** |
| `Khotab/admin:361` `MoveMirror` | no | **yes** | **no** |
| `Khotab/admin:816` `JoinMe` — inserts the source item's own primary link as a mirror of the target | **yes (insert)** | yes (insert) | **no** |
| `Khotab/admin:832` `JoinMe` — `UPDATE … SET khid = target WHERE khid = source`, bulk re-parents *every* mirror of the source | no | **yes, in bulk** | **no** |
| `BackUp/admin:1690` — inserts a mirror during a backup/merge flow | **yes (insert)** | yes (insert) | **no** — its only check is on `nuke_islamic_khotab.link` (the *parent's* primary link), not on any mirror link |
| `BackUp/admin:880`, `:1765` — backup completion (`down`, `trial`, `linksize`, `online`, `downloader`, `checktime`, `cd`) | no | no | n/a |
| `BackUp/admin:825` — `trial = trial + 1` | no | no | n/a |
| `BackUp/admin:1282`, `Khotab/index.php:1904` — bulk `backup = 0` | no | no | n/a |
| `Khotab/admin:692` `ConfirmDeleteMirror` — `DELETE` | n/a | n/a | n/a |
| `BackUp/admin:560` — `SET link = …, trial = 0` | — | — | **not live**; inside a `/* … */` block |
| `get_uploader.php:59`, `stats_authors.php:130` — `SET uploader, addeddate WHERE link LIKE …` | no | no | the `sql_query($sql2)` call is commented out at both sites |

**Five live paths can introduce a duplicate link; exactly one of them is guarded.**

#### 3.5.4 How 329 duplicate groups coexist with the guard

No guessing is needed — source establishes four independent mechanisms, each sufficient on its own:

1. **Edit is unguarded.** `ConfirmEditMirror` writes `link` freely. An admin correcting a URL to a value another
   of that author's mirrors already holds creates a duplicate the insert guard would have rejected.
2. **Re-parent is unguarded.** `MoveMirror` changes only `khid`. Moving a mirror onto another item — of the
   same author, or of a different author — can create an author-scoped collision.
3. **`JoinMe` (item merge) bypasses the guard twice.** It inserts the source item's own primary link as a
   mirror of the target (`:816`), then bulk re-parents every one of the source's mirrors onto the target
   (`:832`). Its eligibility test compares `vedio`, `ser_id` and `group_id` — **not `author`, and not any
   link** (`:793-797`). Since items sharing a series or group are predominantly by one author, merges
   produce same-author duplicates as a matter of course.
4. **`BackUp/admin:1690` is an unguarded second insert path**, whose only duplicate check looks at the
   parent table's `link` column rather than at mirror links at all.

Two further contributors, both consistent with the data and neither requiring speculation:

5. **Orphans are invisible to the guard.** Its inner join cannot see the 4,059 orphan Khotab mirrors, so a
   link held only by an orphan is never detected on insert. (If a parent is later deleted, surviving
   mirrors become orphans and drop out of the guard's view entirely.)
6. **The guard is `die()`-based and insert-time only.** It has no retroactive effect. Any duplicate created
   before the guard existed, or by any of paths 2–4, persists indefinitely — nothing in OLD ever
   re-validates.

**What this does not establish, and is not guessed:** whether the guard predates the bulk of the data,
whether `author` is nullable, the mirror column's exact collation, and the relative contribution of each
mechanism to the 329. Those would each need their own evidence. The point established here is narrower and
sufficient: **the guard is a single insert-time check against four unguarded live write paths, so 329
author-scoped duplicates are fully consistent with the guard being present, correct and effective on the
one path it covers.**

#### 3.5.5 Anasheed — confirmed absence

`ConfirmInsertMirror` (`modules/Anasheed/admin/index.php:77`+) performs **only a parent-existence check**
(`SELECT * FROM {$prefix}_anasheed_anasheed WHERE id = '{$id}'`) before inserting. There is no link lookup,
no `die()`, and no duplicate guard at any scope — and none in `ConfirmEditMirror` (`:272`, committing at `:291`) or
`ConfirmDeleteMirror` (`:330`) either. Anasheed has no re-parent operation at all.

**Preserve that behavior**: Anasheed accepts duplicate links by design-as-built, and the 21 production
duplicate groups are the expected consequence, not a defect. Introducing a guard would be a **new
restriction**, not parity, and requires its own authoritative requirement.

### 3.4 Verdict on a single generic design

**One service can own Khotab, Anasheed and W2ACD; Telawah needs its own adapter; none should be forced.**

- Khotab / Anasheed / W2ACD share the identical column vocabulary (`khid`, `link`, `linksize`, `vedio`, `backupme`, `time`, `trial`) and the identical parent-counter contract. Their three real deltas — label column (`comment` vs `title`), edit-rewrites-`linksize` (Anasheed only), duplicate scope (author / none / none) — are per-domain **configuration**, not different algorithms.
- Telawah diverges in **four column names at once** (`telawa_id`, `size`, `video`, `creation_date` instead of `time`), carries two columns the others lack (`extension`, and a `CHAR` `backupme`), caps `link` at 255 characters, **has none of the quality/backup columns at all**, and **has no parent-counter contract** — production-confirmed (§0.2.1–0.2.2). Per the instruction not to force an abstraction the source contradicts, Telawah should be a distinct implementation behind the same interface — not a fourth config row.
- The commented-out blocks in Telawah (`GreenZone`, counters, logs) are **not a specification to replicate**. Production now splits this question: the **counter** block is commented out because `nuke_telawah_telawah` has no `mirror`/`lastmirror` columns, so there is nothing to decide — no Telawah counter behavior should be designed. The **audit-log and permission** blocks remain a product decision for the owner. Flagged, not decided here.

---

## 4. Laravel models, services and current assumptions

| | Khotab | Anasheed | Telawah | W2ACD |
|---|---|---|---|---|
| Eloquent model | `Content\Models\Mirror` | `Content\Models\AnasheedMirror` | **none** | **none** |
| `$connection` / `$table` | `main` / `nuke_islamic_mirror` | `main` / `nuke_anasheed_mirror` | — | — |
| `$timestamps` / `$guarded` | `false` / `['id']` | `false` / `['id']` | — | — |
| Relations | `belongsTo(KhotabItem, 'khid')`; `hasOne(MirrorAdvanced, 'id', 'id')` | `belongsTo(AnasheedItem, 'khid')`; `hasOne(AnasheedAdvanced, 'id', 'id')` | — | — |
| Inverse on parent | `KhotabItem::mirrors()` `HasMany` (`:144`) | `AnasheedItem::mirrors()` `HasMany` (`:106`) | — | — |
| Parent counter columns | `KhotabItem.mirror`, `.lastmirror` (`:69-70`) | `AnasheedItem.mirror`, `.lastmirror` (`:71-72`) | — | — |
| Jobs | `RecheckMirrorLinkSizeJob`, `RecheckKhotabLinkSizeJob` | — | `RecheckTelawahLinkSizeJob` (item, not mirror) | — |
| Link-quality controller | `LinkQualityStatsController::mirror/khotab` | — | `::telawah` (item) | — |
| BackupApi | `nuke_islamic_mirror` is `CATEGORY_TABLES[2]` | `nuke_anasheed_mirror` is `[5]` | item table is `[3]` | **excluded** |
| Domain behavior on model | `extensionIconFilename()`, `incrementDownloadCount()` | same + `isAudioLike()` | — | — |
| Validation | **none anywhere** | **none** | — | — |
| Permissions | `khotab.repair` gates link-quality routes only | none | `telawah.repair` | none |

**There is no mirror-mutation code anywhere in `app/`** — re-confirmed for this pass. Both models are read-and-increment only. `$guarded = ['id']` means a future `create()`/`update()` would mass-assign freely; that is an existing posture to tighten deliberately, not a bug to report.

### 4.1 Two documentation defects to correct before design

**(a) `TelawahItem.php:25-28` overstates its scope.** It reads:

> *"No mirrors table exists for this module (confirmed by the absence of any `nuke_telawah_mirror`-shaped table or query anywhere in `telawah/*.php`) — each item has exactly one `link`, unlike `KhotabItem`/`AnasheedItem`."*

The parenthetical is correctly scoped to Generation 2 and is accurate there: gen-2's only `nuke_telawah_mirror` references are **three commented-out queries** in `7amlat/radio/functions.php:240,503,603`. But the headline sentence — "No mirrors table exists for this module" — is **false as written**: the table exists, OLD reads it live (`modules/Telawah/index.php:350-351`, a `LEFT JOIN` on `telawa_id`), and OLD has full CRUD against it (`modules/Telawah/admin/index.php:1466-1595`). A developer reading this model would conclude there is nothing to build. **Correct the wording to the gen-2 scope before Telawah mirror work starts.** No code change is implied.

**(b) `decision-log.md:134-137` is generation-scoped in a way that matters.** It records `nuke_w2acd_mirror` as *"already-existing but unused"* and W2ACD as storing mirrors in comma-delimited `link`/`cd`/`thumbnail` columns on the item. That is true of **Generation 2 and Laravel**, and `post-wave-4-cross-wave-architecture-review.md:44` reasonably concluded it was right not to model a relationship for it. But **Generation 1 both writes and reads `nuke_w2acd_mirror`** (`modules/w2acd/admin/index.php:94` INSERT; `modules/w2acd/index.php:374-378` a `LEFT JOIN nuke_w2acd_advanced_m` read, `ORDER BY id ASC`). The claim should be re-scoped rather than withdrawn — Roadmap task 4.4's deferral remains correct on its own terms.

### 4.2 The W2ACD two-representation fork — the highest-value thing to verify

W2ACD mirrors exist in **two unsynchronized representations**:

> **Corrected 2026-10-12 after the Check B parsing trace.** The original version of this table claimed the
> comma representation was read only by Generation 2 and Laravel. That was wrong: **Generation 1 reads it
> too.** The corrected table follows.

| Representation | Written by | Read by |
|---|---|---|
| Normalized `nuke_w2acd_mirror` rows — **production: 0 rows, never written** (§0.4.1) | **OLD only** (`W2ACDInsertMirror` / `ConfirmEditMirror` / `ConfirmDeleteMirror`) | **OLD only**, via two paths: the item-page mirror block (`modules/w2acd/index.php:374-378`, `LEFT JOIN nuke_w2acd_advanced_m`, `ORDER BY id ASC`) and the **download handler** (`:505`, `$W2ACD[link] = $Mirror[link]`). With the table empty, **both OLD paths are dead.** |
| Comma-delimited `link` / `cd` / `thumbnail` on `nuke_w2acd_w2acd` | *no writer found in any generation* | **All three generations.** OLD: `modules/w2acd/index.php:325-327`, `explode(",", $W2ACD['link'])` / `['cd']`. Generation 2: `7amlat/w2acd/functions.php:136-137`. Laravel: `W2acdItem::mirrorLinks()`. |

No code in any generation synchronizes the two, and the comma columns have **no identified writer in any
generation** — so they are very likely frozen legacy data, populated before the normalized table was
introduced or by a tool outside these three codebases.

Given Check B's result (normalized table exactly empty), the live reading is inverted from what this
section originally implied: it is not that OLD-added mirrors are invisible elsewhere — **nothing has been
added through OLD, and the only W2ACD representation any generation can currently render is the comma
list.** OLD's two normalized read paths, including its download handler, resolve to nothing.

**C4 closed this (§0.4.1).** All 39 parent rows carry meaningful `link` and `cd`; the counters OLD's insert
handler would have written are 0 and unset on every row. So the comma columns are confirmed **live data
with no writer in any of the three codebases**, and the normalized path is confirmed **never exercised**.
The remaining question is not which representation is live — it is whether Laravel should keep the comma
representation or normalize it, which is a **design decision for later**, not a prerequisite.

This single fact determines whether W2ACD mirror CRUD is a migration target, a data-migration prerequisite
(Roadmap 4.4), or a dead capability. **A3 and B settled the structural half; C4 settles the data half**, and
the answer should gate W2ACD's rank in Pass 3D §10.

### 4.3 How the comma representation is parsed — source-confirmed, for C4's predicate design

Traced in all three generations because C4's aggregates must match real rendering semantics, not a guess.

| Generation | Code | Parse |
|---|---|---|
| OLD | `modules/w2acd/index.php:326-327` | `explode(",", $W2ACD['link'])`, `explode(",", $W2ACD['cd'])` |
| Generation 2 | `7amlat/w2acd/functions.php:136-137` | `explode(",", $w2acd->link)`, `explode(",", $w2acd->cd)` |
| Laravel | `W2acdItem::mirrorLinks()` | `explode(',', (string) $this->link)`, `explode(',', (string) $this->cd)` |

**Four semantics that matter, identical across all three:**

1. **No trimming and no filtering of the link/cd lists.** `trim_array()` (`way2allah_functions.php:951-961`,
   which `unset`s elements equal to `''`) **is applied only to `thumbnail`** — never to `link` or `cd`. This
   is the one source-confirmed empty convention in the module, and it deliberately does not cover links.
2. **`explode` on an empty string returns one empty element.** So an item with `link = ''` or `link = NULL`
   renders **exactly one row**, not zero. Generation 2 labels it `سيرفر خاص` (private server) and Laravel
   sets `isPrivateServer => true`, because `getExtension('')` / `extensionOf('')` returns `''` and empty
   extension is the private-server branch.
3. **Consequence: rendering cannot distinguish "no links" from "one unparseable link."** Element count is
   therefore useless as a populated-ness test — it is always ≥ 1. C4 must test the string's *content*.
4. **`cd` is zipped positionally by index** (`$titles[$index] ?? ''` in Laravel; bare `$titles[$i]` in
   Generation 2 and OLD). `cd` holds the per-link display titles. A `cd` shorter than `link` yields empty
   titles, not a shorter list.

C4's predicates below are built from (3): an item counts as having a meaningful `link` only if the value
retains at least one character after **all commas and all whitespace** are removed — which classifies
`NULL`, `''`, `'   '`, `','`, `',,'` and `', ,'` as not-meaningful, covering the delimiter-only and
whitespace conventions the owner asked about.

---

## 5. Constraints the new CRUD must preserve

Derived from the completed backup and link-quality programmes, which are **not** reopened or redesigned here.

| # | Constraint | Why | Source |
|---|---|---|---|
| **C1** | **`nuke_islamic_mirror` and `nuke_anasheed_mirror` are backup-programme tables** (`CATEGORY_TABLES[2]` and `[5]`). Insert/update/delete must not disturb `down`, `trial`, `booking`, `downloader`, `checktime` outside the two patterns below. | The Desktop App hands out work by `down = 2 AND trial < max` and claims rows via `booking`. A stray write can orphan a claim or re-queue a completed row. | `BackupApiController:43-49`, `:337-373` |
| **C2** | **Never write `booking` from CRUD.** It is the backup API's claim token (`booking = time()` on hand-out, `0` on completion/release). | A CRUD write to `booking` would steal or void an in-flight claim. | `BackupApiController:264`, `:279`, `:412-419` |
| **C3** | **Preserve `trial = 0` on edit for Khotab, Anasheed and W2ACD** — and preserve its *absence* for Telawah unless the owner decides otherwise. It is the documented way a corrected URL re-enters the backup queue. | Dropping it leaves a fixed link permanently excluded by `trial < max`. Adding it to Telawah is a behavior change, not parity. | `Khotab/admin:628`, `Anasheed/admin:293`, `w2acd/admin:170`; absent at `Telawah/admin:1544-1549` |
| **C4** | **Do not initialize quality fields on insert.** **Production-resolved:** `down` DEFAULT **2** and `trial` DEFAULT **0** on Khotab, Anasheed and W2ACD, so a new mirror is **immediately backup-eligible** — it does not land on the `down = 0` worklist. Telawah has none of these columns, so C4 does not apply there. | The backup API hands out work on exactly `down = 2 AND trial < max`. Hard-coding a different value would silently change enrolment. | §0.2.3; `BackupApiController:337-373` |
| **C5** *(revised after D1)* | **Keep `parent.mirror` / `parent.lastmirror` synchronized transactionally for compatibility, but derive authoritative counts from the relation — never from the column.** Khotab drift is **3.92%** (11,700 of 298,251, 11,694 over-counting); Anasheed drift is **0.23%** (39 of 16,639). Applies to Khotab and Anasheed; **Telawah excluded definitively** (no such columns); **W2ACD excluded** (counters never written, not authoritative — §0.4.1). **Do not bulk-repair counters as part of CRUD** — that needs separate justification. | `KhotabItem.mirror` / `AnasheedItem.mirror` are modeled properties and drive rendering. OLD does these as separate unwrapped statements; a crash between them already corrupts the count. Laravel should wrap them, which is a *correctness improvement*, not a parity break. | `Khotab/admin:530-535`, `:693`, `:362-364` |
| **C6** | **`backupme` is the backup opt-in flag and is admin-settable on both insert and edit in all four domains.** Preserve it as a first-class field. Note production types differ — integer-like on Khotab/Anasheed/W2ACD, **`CHAR` on Telawah** — and on Telawah it opts into nothing, since the table has no `down`/`trial`/`booking`. | It decides whether the row ever enters the archive programme. | all four INSERT/UPDATE statements; §0.2.1, §0.3 |
| **C7** | **`linksize` is stored in bytes; the admin UI accepts megabytes** (`intval(1024 * 1024 * $input)`). Production widths differ: `BIGINT` (Khotab) vs **`INT`** (Anasheed, caps ~2.1 GB signed) vs `size` (Telawah). | `fixSize*` and the `percent` metric compare `linksize` to `online` in bytes. A unit slip silently breaks the entire quality metric. | `Khotab/admin:479`, `Anasheed/admin:95`, `Telawah/admin:1461`, `w2acd/admin:80` |
| **C8** | **Do not touch `hits`.** It is a download counter incremented by the public download path (`incrementDownloadCount()`), not an admin field. | Conflating it with view-tracking is an error the models explicitly document avoiding. | `Mirror`/`AnasheedMirror` docblocks |
| **C9** | **`MirrorAdvanced` / `AnasheedAdvanced` share the mirror's `id`** (`hasOne(…, 'id', 'id')`). Deleting a mirror orphans its advanced row. | OLD does not cascade; not changed here without justification. | `Mirror::advanced()` |
| **C11** *(new, after D3)* | **Tolerate a missing parent in every operation.** 4,059 Khotab and 480 Anasheed mirrors are orphans. Delete must succeed without a parent (OLD cannot — it returns early, leaving orphans undeletable); re-parent and counter maintenance must skip the decrement when the old parent is absent rather than faulting. **This is robustness, not a cleanup feature** — no orphan-repair capability is in scope. | Real production state; the guard and OLD's delete path both blind to it. | §0.4.2 D3; `Khotab/admin:683-687` |
| **C12** *(new, after D2 + §3.5)* | **Do not introduce a DB-level UNIQUE constraint at any scope, and do not add duplicate rejection where OLD has none.** Existing data violates both candidate scopes (Anasheed 21 per-parent groups; Khotab 329 author-scoped groups). Reproduce Khotab's author-scoped insert-time guard as application validation on the insert path only, matching §3.5's exact scope; leave Anasheed unguarded. | Duplicate counts describe current data, not intended policy. A schema constraint would fail on existing rows and block legitimate edits. | §0.4.2 D2; §3.5 |
| **C10** | **Laravel writes `linksize` on Anasheed edit but OLD's Khotab edit does not.** Keep the asymmetry unless the owner changes it. | Rewriting `linksize` from admin input resets the quality baseline that `percent` is measured against. | `Anasheed/admin:293-297` vs `Khotab/admin:626-631` |

Nothing above requires changing `LinkSizeChecker`, the three `Recheck*` jobs, `RecomputeLinkQualityPercentAction`, `LinkQualityStatsController` or `BackupApiController`.

---

## 6. Production verification

Read-only. Metadata and existence only — **no row contents, no URLs, no personal data, no secrets, no mutation, no `EXPLAIN ANALYZE`.** Run from the already-authenticated production MySQL/MariaDB shell. Credentials are not referenced.

The database name contains a hyphen and must stay backtick-quoted.

### Check A — table existence + schema

```sql
USE `w2acp_db2025-2`;

-- A1. Which mirror tables actually exist, and their exact names.
--     Also settles whether `nuke_w2acd_mirror` is present at all.
SHOW TABLES LIKE '%mirror%';

-- A2. Full schema for the four expected tables: columns, types, NULLability,
--     DEFAULTS (constraint C4), engine, charset, and every index/unique key
--     (settles whether any duplicate-URL guard exists at the DB level).
SHOW CREATE TABLE `nuke_islamic_mirror`;
SHOW CREATE TABLE `nuke_anasheed_mirror`;
SHOW CREATE TABLE `nuke_telawah_mirror`;
SHOW CREATE TABLE `nuke_w2acd_mirror`;

-- A3. The parent-side columns this unit must maintain (constraint C5),
--     plus W2ACD's comma-delimited representation (§4.2).
--     Targeted rather than a full SHOW CREATE on the large parent tables.
SHOW COLUMNS FROM `nuke_islamic_khotab`   WHERE Field IN ('id','mirror','lastmirror');
SHOW COLUMNS FROM `nuke_anasheed_anasheed` WHERE Field IN ('id','mirror','lastmirror');
SHOW COLUMNS FROM `nuke_telawah_telawah`   WHERE Field IN ('id','mirror','lastmirror');
SHOW COLUMNS FROM `nuke_w2acd_w2acd`       WHERE Field IN ('id','mirror','lastmirror','link','cd','thumbnail');

-- A4. Index cardinality for the item-FK columns, which SHOW CREATE TABLE
--     does not report. Confirms whether parent-scoped lookups are indexed.
SHOW INDEX FROM `nuke_islamic_mirror`;
SHOW INDEX FROM `nuke_anasheed_mirror`;
SHOW INDEX FROM `nuke_telawah_mirror`;
SHOW INDEX FROM `nuke_w2acd_mirror`;
```

If A1 does not list `nuke_telawah_mirror` or `nuke_w2acd_mirror`, **stop and report that** — the corresponding `SHOW CREATE TABLE` will error, and the domain's status changes materially. Do not create anything.

**What A settles:** the four tables' real existence and exact names; every column name, type and **DEFAULT** (C4); whether any unique index enforces URL uniqueness (§3.1); whether the item-FK is indexed (A4); the presence and type of the parent counter columns (C5); and whether W2ACD's comma columns exist alongside the normalized table (§4.2).

### Checks B and C

Check A and Check B are **complete**; their results are reconciled in §0.1–0.5. C4 is **issued**; C1/C2/C3/C5 remain drafted and unissued (§6.1 preamble).

#### Check B — aggregate counts *(production-informed, revised after Check A)*

Superseded by the version issued to the owner on 2026-10-12. Three changes from the pre-Check-A draft:
Telawah groups by `telawa_id` (confirmed) and **has no parent counters**, so no counter work appears here;
Telawah and W2ACD have **no parent-FK index**, so their aggregates are clustered-index scans and are
therefore **gated behind a free `information_schema` estimate (B0)**; and the four separate queries per
domain are collapsed into **one single-pass aggregate per table**, which returns row count, distinct
parents, multi-mirror parents and max-per-parent from one scan instead of four.

```sql
USE `w2acp_db2025-2`;

-- B0. FREE. Metadata only, no table access. Gates B1b/B2 cost.
SELECT TABLE_NAME, ENGINE, TABLE_ROWS AS est_rows,
       DATA_LENGTH, INDEX_LENGTH
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'w2acp_db2025-2'
  AND TABLE_NAME IN ('nuke_islamic_mirror','nuke_anasheed_mirror',
                     'nuke_telawah_mirror','nuke_w2acd_mirror');

-- B1a. Khotab + Anasheed. Index-only scan of idx_khid(khid,id).
SELECT 'khotab' AS domain, SUM(c) AS rows_total, COUNT(*) AS parents,
       SUM(c > 1) AS parents_multi, MAX(c) AS max_per_parent
FROM (SELECT khid, COUNT(*) AS c FROM `nuke_islamic_mirror` GROUP BY khid) t
UNION ALL
SELECT 'anasheed', SUM(c), COUNT(*), SUM(c > 1), MAX(c)
FROM (SELECT khid, COUNT(*) AS c FROM `nuke_anasheed_mirror` GROUP BY khid) t;

-- B1b. Telawah + W2ACD. Clustered-index scan (no parent-FK index).
--      Run only after B0 confirms these are small.
SELECT 'telawah' AS domain, SUM(c) AS rows_total, COUNT(*) AS parents,
       SUM(c > 1) AS parents_multi, MAX(c) AS max_per_parent
FROM (SELECT telawa_id, COUNT(*) AS c FROM `nuke_telawah_mirror` GROUP BY telawa_id) t
UNION ALL
SELECT 'w2acd', SUM(c), COUNT(*), SUM(c > 1), MAX(c)
FROM (SELECT khid, COUNT(*) AS c FROM `nuke_w2acd_mirror` GROUP BY khid) t;

-- B2. Populated-ness and write recency for the two unindexed tables.
--     Answers "was this ever actively written, and when did it stop?"
SELECT 'telawah' AS domain, COUNT(*) AS rows_total,
       MIN(id) AS min_id, MAX(id) AS max_id,
       MIN(creation_date) AS first_created, MAX(creation_date) AS last_created
FROM `nuke_telawah_mirror`;

SELECT 'w2acd' AS domain, COUNT(*) AS rows_total,
       MIN(id) AS min_id, MAX(id) AS max_id,
       MIN(`time`) AS first_time, MAX(`time`) AS last_time
FROM `nuke_w2acd_mirror`;
```

**Deliberately not in Check B:** the W2ACD parent `link`/`cd` comparison. An empty
`nuke_w2acd_mirror` does **not** on its own prove normalized storage is dead — that conclusion requires
reconciling against the parent representation, which is Check C's C4.

#### Check C — triaged after C4

C4 is complete (§0.4.1). **C1/C2/C3/C5 are now triaged rather than issued wholesale**, against a single
test: *could this production fact change a CRUD invariant, the counter contract, orphan handling, duplicate
policy, backup/quality behavior, a transaction boundary, or the authorization/audit design?*

| Drafted check | Verdict | Reasoning |
|---|---|---|
| **C3b — parent counter drift** (`parent.mirror` vs real `COUNT(*)`) | **REQUIRED** | Decides whether constraint C5 means "maintain a trusted invariant" or "maintain a value already known wrong." OLD performs the counter updates as **separate unwrapped statements** (`Khotab/admin:530-535`, `:693`, `:362-364`), a plausible mechanism **consistent with** observed drift; causation is not claimed. It cannot be defaulted conservatively: trusting a drifted counter and repairing a sound one are opposite designs, and the answer also sizes the transaction-boundary argument. W2ACD just demonstrated counters can be *entirely* unwritten (§0.4.1) — the same must be verified, not assumed, for the two in-scope domains. |
| **C2 — duplicate URLs**, rewritten | **REQUIRED, and the drafted version is wrong-scoped** | Drafted C2 groups by `(khid, link)` — per-parent. But OLD's actual guard is **author-scoped** for Khotab (`k.author`, `Khotab/admin:498-509`) and **absent entirely** for Anasheed. Open item 4 (unify or preserve per domain) cannot be defaulted: adding a per-parent unique rule when existing data violates it silently blocks legitimate edits, and reproducing the author-scoped `die()` requires knowing whether data actually honors it. Must report **both** scopes, both domains. |
| **C1 — orphan mirrors** | **REQUIRED (nearly free)** | An orphan is currently **undeletable in OLD**: `ConfirmDeleteMirror` looks up the parent and returns early printing `المادة غير موجودة` without deleting (`Khotab/admin:683-687`). So this is not only defensive coding — it decides whether the new CRUD needs an orphan-cleanup affordance OLD never had. The anti-join is covered by `idx_khid` on the mirror side, so cost is low enough that guessing is not worth it. |
| **C3a — NULL / empty `link`** | **NOT REQUIRED** | Conservatively defaultable at zero cost: do not apply a `required` rule to `link` when editing an existing row, so pre-existing empty links stay editable either way. The count would be descriptive only. |
| **C5 — `down` / `backupme` distribution** | **NOT REQUIRED** | The only backup-enrolment fact that affects CRUD is the **insert-time default**, and schema already settled it: `down` DEFAULT 2, `trial` DEFAULT 0 → immediately backup-eligible (§0.2.3). The CRUD never branches on `down`; it must simply not touch it (C1, C2) beyond `trial = 0` on edit (C3). A state histogram changes no invariant, no boundary and no validation rule. Issuing it would be mechanical. |

**Consolidated into one check (§6.2).** C1, C2-rewritten and C3b are all single-pass aggregates over the
same two table pairs and are issued together as **Check D**.

### 6.1 Check C4 — W2ACD representation classification (issued)

Read-only. Aggregates only. No URLs, no row contents, no mutation. Three queries, each one scan of
`nuke_w2acd_w2acd`; nothing writes to `nuke_w2acd_mirror`.

Predicates derive from §4.3: a value is **meaningful** only if it retains at least one character after every
comma, space, tab, CR and LF is removed. That classifies `NULL`, `''`, whitespace-only, `','`, `',,'` and
`', ,'` as not-meaningful.

```sql
USE `w2acp_db2025-2`;

-- C4a. Emptiness distribution for `link` and `cd`, one scan.
--      Requirements 4, 5, 6, 9.
SELECT
  COUNT(*)                                                      AS parent_rows_total,
  SUM(`link` IS NULL)                                           AS link_null,
  SUM(`link` IS NOT NULL AND `link` = '')                        AS link_empty_string,
  SUM(`link` IS NOT NULL AND `link` <> ''
      AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`link`,
          ',',''),' ',''),CHAR(9),''),CHAR(13),''),CHAR(10),'') = '')
                                                                AS link_delimiter_or_ws_only,
  SUM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(`link`,''),
          ',',''),' ',''),CHAR(9),''),CHAR(13),''),CHAR(10),'') <> '')
                                                                AS link_meaningful,
  SUM(`cd` IS NULL)                                             AS cd_null,
  SUM(`cd` IS NOT NULL AND `cd` = '')                           AS cd_empty_string,
  SUM(`cd` IS NOT NULL AND `cd` <> ''
      AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`cd`,
          ',',''),' ',''),CHAR(9),''),CHAR(13),''),CHAR(10),'') = '')
                                                                AS cd_delimiter_or_ws_only,
  SUM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(`cd`,''),
          ',',''),' ',''),CHAR(9),''),CHAR(13),''),CHAR(10),'') <> '')
                                                                AS cd_meaningful
FROM `nuke_w2acd_w2acd`;

-- C4b. Overlap matrix. Requirement 7.
SELECT
  CASE WHEN link_ok AND cd_ok THEN 'both'
       WHEN link_ok          THEN 'link_only'
       WHEN cd_ok            THEN 'cd_only'
       ELSE                       'neither'
  END                       AS representation,
  COUNT(*)                  AS parent_rows,
  MIN(link_elements)        AS min_link_elements,
  MAX(link_elements)        AS max_link_elements,
  ROUND(AVG(link_elements),2) AS avg_link_elements
FROM (
  SELECT
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(`link`,''),
        ',',''),' ',''),CHAR(9),''),CHAR(13),''),CHAR(10),'') <> '' AS link_ok,
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(`cd`,''),
        ',',''),' ',''),CHAR(9),''),CHAR(13),''),CHAR(10),'') <> '' AS cd_ok,
    CHAR_LENGTH(COALESCE(`link`,''))
      - CHAR_LENGTH(REPLACE(COALESCE(`link`,''),',','')) + 1       AS link_elements
  FROM `nuke_w2acd_w2acd`
) t
GROUP BY representation
ORDER BY parent_rows DESC;

-- C4c. Are `mirror`/`lastmirror` stale, or do they track the comma list?
--      Requirement 8. The normalized table is empty (Check B), so any
--      mirror > 0 is unbacked by normalized rows; the decisive test is
--      whether the counter equals the comma element count instead.
SELECT
  COUNT(*)                                        AS parent_rows_total,
  SUM(`mirror` > 0)                               AS counter_claims_mirrors,
  SUM(`mirror` = 0)                               AS counter_zero,
  SUM(`mirror` = link_elements AND link_ok)       AS counter_equals_comma_count,
  SUM(`mirror` > 0 AND NOT link_ok)               AS counter_set_but_no_link,
  SUM(`mirror` = 0 AND link_ok)                   AS link_present_but_counter_zero,
  MAX(`mirror`)                                   AS max_counter,
  MIN(NULLIF(`lastmirror`,0))                     AS first_lastmirror_unix,
  MAX(NULLIF(`lastmirror`,0))                     AS last_lastmirror_unix,
  FROM_UNIXTIME(MIN(NULLIF(`lastmirror`,0)))      AS first_lastmirror_utc,
  FROM_UNIXTIME(MAX(NULLIF(`lastmirror`,0)))      AS last_lastmirror_utc,
  SUM(`lastmirror` IS NULL OR `lastmirror` = 0)   AS lastmirror_unset
FROM (
  SELECT `mirror`, `lastmirror`,
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(`link`,''),
        ',',''),' ',''),CHAR(9),''),CHAR(13),''),CHAR(10),'') <> '' AS link_ok,
    CHAR_LENGTH(COALESCE(`link`,''))
      - CHAR_LENGTH(REPLACE(COALESCE(`link`,''),',','')) + 1       AS link_elements
  FROM `nuke_w2acd_w2acd`
) t;
```

**How to read the result:**

- If **`link_meaningful` is high** and C4b's `both` or `link_only` dominates → the comma representation is
  the live one, and W2ACD mirror CRUD is a **data-migration prerequisite** (Roadmap task 4.4), not a
  straight CRUD port. The first unit stays at 2 domains.
- If **`link_meaningful` is ~0** → W2ACD has no live download representation in either form, and its mirror
  operations are a **dead capability** — do not port for parity.
- C4c's `counter_equals_comma_count`: if high, `mirror` tracks the **comma list**, confirming that
  representation as the operative one and the counters as meaningful. If instead
  `counter_set_but_no_link` is high, the counters are **stale historical residue** from when the normalized
  table held rows, and `last_lastmirror_utc` dates when W2ACD mirror administration stopped.

> **Result (§0.4.1): neither branch.** `counter_equals_comma_count` = 0 **and**
> `counter_set_but_no_link` = 0, because every `mirror` is 0 and every `lastmirror` is unset while all 39
> items have links. The counters are therefore **not stale residue — they were never written at all.**
> This supersedes the "stale historical residue" hypothesis offered above and, with 0 normalized rows,
> establishes that OLD's `W2ACDInsertMirror` (which would increment `mirror` and set `lastmirror`) has
> **never been exercised in production.** `last_lastmirror_utc` is NULL, so this pass yields no date for
> when W2ACD mirror administration stopped — it appears never to have started.
- `last_lastmirror_utc` is the single most useful date in this pass: it bounds when W2ACD mirror
  administration was last exercised, which informs the business question in Pass 3D §7 without asking.

### 6.2 Check D — the one remaining pre-design check (issued)

Read-only. Aggregates only. No URLs, no row contents, no mutation. Khotab and Anasheed only — Telawah and
W2ACD are out of the first unit (§0.5).

`link` is `TEXT` on both tables, and grouping by a `TEXT` column forces an **on-disk** temporary table
(MEMORY cannot hold TEXT/BLOB). D2 therefore groups by `MD5(link)` — a fixed 32-char value that stays in
memory. That is both cheaper and strictly better for the no-URLs requirement, since no link value is
grouped, compared or returned.

```sql
USE `w2acp_db2025-2`;

-- D1. Parent counter drift. Decides whether constraint C5 maintains a
--     trusted invariant or an already-wrong value. Scans each parent
--     table once; the derived table is an index-only scan of idx_khid.
SELECT 'khotab' AS domain,
  COUNT(*)                                            AS parents_total,
  SUM(COALESCE(p.`mirror`,0) <> COALESCE(m.c,0))      AS counter_drifted,
  SUM(COALESCE(p.`mirror`,0) >  COALESCE(m.c,0))      AS counter_too_high,
  SUM(COALESCE(p.`mirror`,0) <  COALESCE(m.c,0))      AS counter_too_low,
  SUM(COALESCE(p.`mirror`,0) = 0 AND COALESCE(m.c,0) > 0) AS counter_zero_but_has_mirrors,
  SUM(p.`lastmirror` IS NULL OR p.`lastmirror` = 0)   AS lastmirror_unset,
  MAX(p.`mirror`)                                     AS max_counter
FROM `nuke_islamic_khotab` p
LEFT JOIN (SELECT khid, COUNT(*) AS c FROM `nuke_islamic_mirror` GROUP BY khid) m
       ON m.khid = p.id;

SELECT 'anasheed' AS domain,
  COUNT(*)                                            AS parents_total,
  SUM(COALESCE(p.`mirror`,0) <> COALESCE(m.c,0))      AS counter_drifted,
  SUM(COALESCE(p.`mirror`,0) >  COALESCE(m.c,0))      AS counter_too_high,
  SUM(COALESCE(p.`mirror`,0) <  COALESCE(m.c,0))      AS counter_too_low,
  SUM(COALESCE(p.`mirror`,0) = 0 AND COALESCE(m.c,0) > 0) AS counter_zero_but_has_mirrors,
  SUM(p.`lastmirror` IS NULL OR p.`lastmirror` = 0)   AS lastmirror_unset,
  MAX(p.`mirror`)                                     AS max_counter
FROM `nuke_anasheed_anasheed` p
LEFT JOIN (SELECT khid, COUNT(*) AS c FROM `nuke_anasheed_mirror` GROUP BY khid) m
       ON m.khid = p.id;

-- D2. Duplicate-URL groups at BOTH candidate scopes. Author scope is what
--     OLD's Khotab guard actually enforces; parent scope is what a Laravel
--     unique rule would naturally use. Anasheed has no guard at all today.
SELECT 'khotab' AS domain,
  (SELECT COUNT(*) FROM (
     SELECT 1 FROM `nuke_islamic_mirror`
     GROUP BY khid, MD5(`link`) HAVING COUNT(*) > 1) t)          AS dup_groups_parent_scope,
  (SELECT COUNT(*) FROM (
     SELECT 1 FROM `nuke_islamic_mirror` m
     JOIN `nuke_islamic_khotab` k ON k.id = m.khid
     GROUP BY k.author, MD5(m.`link`) HAVING COUNT(*) > 1) t)    AS dup_groups_author_scope;

SELECT 'anasheed' AS domain,
  (SELECT COUNT(*) FROM (
     SELECT 1 FROM `nuke_anasheed_mirror`
     GROUP BY khid, MD5(`link`) HAVING COUNT(*) > 1) t)          AS dup_groups_parent_scope,
  (SELECT COUNT(*) FROM (
     SELECT 1 FROM `nuke_anasheed_mirror` m
     JOIN `nuke_anasheed_anasheed` k ON k.id = m.khid
     GROUP BY k.author, MD5(m.`link`) HAVING COUNT(*) > 1) t)    AS dup_groups_author_scope;

-- D3. Orphan mirrors. An orphan is currently UNDELETABLE in OLD
--     (ConfirmDeleteMirror returns early when the parent is missing),
--     so this decides whether the new CRUD needs an orphan affordance.
--     Anti-join covered by idx_khid on the mirror side.
SELECT 'khotab' AS domain, COUNT(*) AS orphan_mirrors
FROM `nuke_islamic_mirror` m
LEFT JOIN `nuke_islamic_khotab` k ON k.id = m.khid
WHERE k.id IS NULL
UNION ALL
SELECT 'anasheed', COUNT(*)
FROM `nuke_anasheed_mirror` m
LEFT JOIN `nuke_anasheed_anasheed` k ON k.id = m.khid
WHERE k.id IS NULL;
```

**How the results change the design:**

- **D1 `counter_drifted` low (≈0)** → the counters are a sound invariant; C5 maintains them transactionally
  and the design may read `parent.mirror` for display. **High** → they are already unreliable; Laravel should
  derive the count from the relation and treat `mirror`/`lastmirror` as legacy display fields to keep
  updated but never trust — the same posture C4 forced for W2ACD, for a different reason.
  *(Result: 3.92% / 0.23%, over-counting. Unreliable. No causal claim made — see §0.4.2.)*
- **D2 `dup_groups_parent_scope` = 0** → a per-parent unique rule is safe to introduce. **> 0** → existing
  data violates it, so either preserve OLD's author scope or add the rule as validation-on-write only,
  without a schema constraint. `dup_groups_author_scope` says whether OLD's own guard is actually honored
  in data, and Anasheed's two numbers price the cost of giving it a guard it has never had.
- **D3 > 0** → delete and re-parent must tolerate a missing parent (OLD cannot). ~~an orphan-cleanup
  affordance becomes a candidate for the first unit~~ — **withdrawn.** Results: 4,059 Khotab / 480 Anasheed
  orphans. CRUD robustness is required (constraint C11); a cleanup/repair capability is **a new feature,
  not parity**, and is not in this unit (§0.4.2 D3).

### 6.3 Not needed before design — and why

- **C3a (empty `link` counts)** — defaultable: no `required` rule on `link` when editing an existing row.
- **C5 (`down`/`backupme` histogram)** — the insert-time default is the only CRUD-relevant fact and schema
  already gave it (§0.2.3). The CRUD never branches on `down`.
- **Anything about Telawah or W2ACD** — both out of the first unit (§0.5).

### 6.4 Deferred to the authorization/audit sub-pass

The next unresolved area is the moderator permission and audit contract (`nuke_islamic_mod`,
`nuke_anasheed_mod`, `nuke_telawah_mod`, `nuke_w2acd_mod`, `nuke_islamic_logs` — Pass 3D §8). Three
production facts will be needed there, and **deliberately are not bundled into Check D**, because they
answer a different question and would invite designing authorization inside a schema pass:

1. **Are the grant tables populated, and for how many distinct admins?** If `nuke_islamic_mod` is empty, the
   per-capability model must be *designed*, not *migrated* — the exact lesson C4 just taught for W2ACD
   (§0.4.1, open item 12). This single fact could collapse Pass 3D's rank 2.
2. **Which capability tokens actually appear in the grant data**, versus the 15 enumerated in
   `ListModertors()`'s `$Trans` map. Specifically whether `addmirror` / `editmirror` / `delmirror` — the
   three this unit needs — are among them.
3. **Is `nuke_islamic_logs` actively written, and how recently?** Row count, distinct `module` values, and
   `MAX` timestamp establish whether the audit trail is live or historical, which decides whether this unit
   writes to it or to something new.

> **Naming correction (made during Pass 3E.2).** This document referred in several places to an
> `UpdateMirror` function. **No such function exists in either domain.** The edit-commit handler is
> **`ConfirmEditMirror`** — `modules/Khotab/admin/index.php:596` and
> `modules/Anasheed/admin/index.php:272`; `EditMirror` (`:548` / `:225`) renders the form. Corrected in
> place above. No conclusion changes.
>
> **Authorization/audit follow-on:** `docs/reviews/old-admin-mirror-auth-audit-pass-3e2.md`, now also
> **CLOSED**. Two findings there bear on this document: **`AvAdmin()` is live, fail-closed code that reads
> `nuke_islamic_mod`** and governs Khotab edit and delete; and production confirmed that table is
> **populated and fully active** (36 rows, 17 `audio` / 26 `vedio` grants). §7.2's conclusion that nothing
> in the data/schema domain blocks design still holds, but the grant table is **not** purely historical.
> That pass's I-10 companion requirement — the audit write belongs inside the same transaction — is
> recorded there in §8.2.5.

## 7. CRUD invariants established, and pass closure

### 7.1 The invariants

Everything below is established from source plus Checks A–D. These are inputs to design, not a design.

| # | Invariant | Basis |
|---|---|---|
| I-1 | **Scope is 7 operations, 2 domains**: Khotab insert/edit/delete/re-parent, Anasheed insert/edit/delete. | §0.5 |
| I-2 | **The relation is the only authoritative mirror count.** `parent.mirror` is legacy denormalized state with 3.92% drift (Khotab) and 0.23% (Anasheed), biased to over-counting. Keep it synchronized transactionally for compatibility; never read it as truth. No bulk repair inside CRUD. | D1, C5 |
| I-3 | **`parent.lastmirror` is sound where it matters** — 12,015/12,015 mirrored Anasheed parents have it set. Maintain on insert as OLD does. The 4,615 all-parent figure is a denominator artifact, not a defect. | D1 follow-up |
| I-4 | **Every operation must tolerate a missing parent** (4,059 + 480 orphans). Delete succeeds without a parent; counter decrement is skipped, not faulted. **Robustness only — no orphan-repair feature.** | D3, C11 |
| I-5 | **No DB-level UNIQUE at any scope; no new duplicate rejection where OLD has none.** Khotab: reproduce the author-scoped, insert-only guard as application validation, with §3.5's exact scope and its known blind spots. Anasheed: no guard. | D2, §3.5, C12 |
| I-6 | **Quality fields are never initialized on insert and never written by CRUD**, except `trial = 0` on edit (Khotab and Anasheed both do this). Defaults `down = 2`, `trial = 0` make a new mirror immediately backup-eligible. | §0.2.3, C1–C4 |
| I-7 | **`booking` is never written by CRUD** — it is the backup API's claim token. | C2 |
| I-8 | **`linksize` is bytes; admin input is megabytes** (`×1024²`). Anasheed edit rewrites `linksize`; Khotab edit does not. Preserve the asymmetry. | C7, C10 |
| I-9 | **`hits` is never an admin field** — it is a download counter. | C8 |
| I-10 | **Insert, delete and re-parent each touch two tables**, so each needs one transaction spanning the mirror write and the parent-counter write. OLD performs them unwrapped, which is **consistent with failure windows of the kind that would produce I-2's drift** — a plausible mechanism, not an established cause. | §3.1, D1 |
| I-11 | **Re-parent is Khotab-only**, and is a **two-request cookie clipboard** in OLD, not a form. Preserve the two-step interaction; do not reproduce a serialized-row cookie. | §3.3 |
| I-12 | **Label column differs**: `comment` (Khotab) vs `title` (Anasheed). Per-domain configuration, not separate algorithms. | §2 |

### 7.2 Still genuinely blocking design

**Nothing in the data or schema domain.** Two items remain, and both are decisions rather than evidence:

1. **Duplicate policy intent.** I-5 states the safe default (reproduce OLD exactly). If the business wants
   duplicates *prevented* going forward, that is a new requirement and needs stating — it cannot be read
   off 21 and 329.
2. **Authorization and audit** — the `*_mod` grant tables and `nuke_islamic_logs`. This is the genuine
   blocker: Pass 3D §8 established OLD's `GreenZone()` is dead, so a faithful port ships "any admin may
   rewrite any download URL, unlogged." §6.4 names the three production facts needed.

Two low-cost confirmations are noted but **not blocking**, both from §3.5.2, and both only matter if
duplicate policy becomes contested: whether `nuke_islamic_khotab.author` is nullable (NULL-author items
escape the guard entirely), and the mirror `link` column's collation (a `_ci` collation makes the guard
case-insensitive and therefore broader than the byte-exact 329, i.e. 329 is a lower bound).

### 7.3 Pass closure recommendation

**Pass 3E.1's data and schema investigation can be closed.** Checks A, B, C4 and D answered every question
that could materially change CRUD invariants, counter maintenance, orphan handling, duplicate behavior,
backup/quality behavior or transaction boundaries. The two remaining triaged queries (C3a, C5) were
deliberately not issued because neither changes a decision (§6.3).

**The next pass should be the moderator permission and audit contract** (`nuke_islamic_mod`,
`nuke_anasheed_mod`, `nuke_telawah_mod`, `nuke_w2acd_mod`, `nuke_islamic_logs`) — not implementation
design. It is the last unresolved dependency of the first unit, and it carries a specific risk this pass
twice demonstrated: **an OLD handler's existence proves nothing about whether the capability is in use.**
Telawah's mirror table holds 1 row; W2ACD's holds 0 with counters never written. The grant tables must be
checked for population before anything is treated as a model to migrate rather than design.

## 8. What this pass deliberately did not do

- No authorization or audit design. The `addmirror`/`editmirror`/`delmirror` tokens and the
  `nuke_islamic_logs` contract are recorded as observations only; §6.4 scopes the next pass.
- No reopening of `BackupApiController`, `LinkQualityStatsController`, `LinkSizeChecker`, the three
  `Recheck*` jobs, or `RecomputeLinkQualityPercentAction`.
- No code changes, including the two documentation defects in §4.1 — both reported for a later edit.
- No implementation design, no deployment, no commit.
- No bulk data repair proposed: not counters (I-2), not orphans (I-4), not duplicates (I-5).

## 9. Open items for the owner — revised after Check D

| # | Item | Status |
|---|---|---|
| 1 | **W2ACD representation fork (§4.2).** | **CLOSED by C4** (§0.4.1). Comma representation is live (39/39 items, 1–3 links, avg 1.92); normalized table never written; counters never set and **not authoritative**. W2ACD stays out of the first unit. Whether to preserve or normalize the comma representation is a later design decision, explicitly **not** declared a prerequisite. |
| 2 | **Telawah's commented-out counters, audit and permission checks (§3.2, §3.4).** | **Half resolved.** Counters: closed — `nuke_telawah_telawah` has no `mirror`/`lastmirror`, so there is nothing to design. Audit log and `GreenZone()` calls: **still a product decision.** |
| 3 | **Insert-time quality defaults (C4).** | **Resolved.** `down` DEFAULT 2, `trial` DEFAULT 0 → a new mirror is immediately backup-eligible on Khotab, Anasheed and W2ACD; not applicable to Telawah. |
| 4 | **Duplicate-URL policy (§3.1).** Three behaviors across four domains (author-scoped, group-scoped, none, none), and production confirms **no unique index anywhere**. Unify, or preserve per domain? | **Open.** |
| 5 | **Telawah mirror support is unfinished, with 1 production row.** Six converging signals (§0.4.2). **Resolved for scope:** Telawah is out of the first unit (§0.5). **Still open as product:** should Telawah mirrors exist at all, or should the table and its 2 OLD operations be retired? |
| 6 | **Telawah's `extension` and `creation_date` columns have no writer in any traced OLD path.** Who populates them? | **New, open.** |
| 7 | **No parent-FK index on `nuke_telawah_mirror` or `nuke_w2acd_mirror`.** | **Closed as a non-issue.** Check B: those tables hold 1 and 0 rows. Khotab and Anasheed — the only in-scope domains — both have `idx_khid (khid,id)` covering parent-scoped lookups across 432,974 and 18,809 rows. |
| 8 | **`nuke_anasheed_mirror.linksize` is `INT`** (~2.1 GB signed ceiling) where Khotab's is `BIGINT`. Pre-existing; flag only. | **New, informational.** |
| 9 | **W2ACD appears to lack `percent` and `booking`** (§0.3). | **Moot while the table is empty.** Revisit only if C4 puts W2ACD back in scope. |
| 10 | **Multi-mirror is the norm for Khotab** — 77.2% of 235,003 parents, bounded at ≤10. | **New, informational.** The per-item admin UI should treat the mirror list as the primary object; no in-item pagination needed. |
| 11 | **The comma `link`/`cd` columns have no identified writer in any generation** (§4.2), yet C4 proves all 39 rows populated. Something outside these three codebases wrote them. | **Open.** Only matters if/when W2ACD re-enters scope; a Laravel CRUD would be the first identified writer. |
| 12 | **OLD's `W2ACDInsertMirror` has never been exercised in production** — 0 normalized rows and 0/39 counters written (§0.4.1). | **Informational, but a method lesson:** the existence of an OLD handler is not evidence the capability is in use. Applied prospectively to Telawah (§0.4.2) and to the moderator grant tables before any authorization work. |
