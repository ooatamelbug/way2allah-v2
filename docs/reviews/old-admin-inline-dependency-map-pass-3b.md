# Pass 3B — OLD Inline Administration Dependency Map

**Status.** Analysis and documentation only. No legacy behaviour changed, no Laravel code written, Advfiles not migrated, nothing committed.

**Semantic rule observed throughout:** Business Confirmation #6's statement that the system it referenced was *"not old.way2allah.com"* is **not reinterpreted here**. Two concepts are kept strictly separate: (1) the system #6 referenced, and (2) explicit generation-2 dependencies on `old.way2allah.com`. This pass establishes (2) as a technical surface only. §7 records the tension without merging them.

---

## 1. The mechanism, and a correction to my own Pass 3A figure

`7amlat/w2a_config.php:63` — `$inlineAdminUrl = 'https://old.way2allah.com/'`.

Generation 2 renders admin controls on its own pages whose `href` points at generation 1. **Pass 3A reported "~40 references across 35 operations." Both numbers were too low**, because the regex I used (`inlineAdminUrl[^"']*op=`) stopped at the first quote and so missed every concatenated form such as `".$inlineAdminUrl."admin.php?op=polledit`. Corrected by exhaustive extraction:

| Measure | Pass 3A | **Pass 3B (verified)** |
|---|---|---|
| `$inlineAdminUrl` occurrences | — | **121** |
| …that construct an OLD admin URL | ~40 | **101** |
| Distinct OLD `op` tokens referenced | 35 | **76** |
| Real OLD operations behind them | 35 | **78** (74 direct + 4 composed from 2 templates) |

**`$inlineAdminUrl` is the only vector.** Verified negatively: literal `old.way2allah.com` appears 8 times in non-forum, non-English source, of which 6 are `w2a_config.php` plus the new bridge comments and 2 are a Ramadan banner image; and there is **no JavaScript vector at all** (zero matches for OLD admin URLs across `*.js`).

---

## 2. Dependency enumeration (A)

101 URL-building references. Principal generation-2 source sites:

| Generation-2 file | Role | Domain |
|---|---|---|
`khotab/functions.php` | inline admin toolbars on khotab pages | Khotab, Advfiles |
`functions.php:454-496` | category add/remove-from-all controls | Categories |
`fatawa/functions.php:3-51+` | fatwa topic/question controls | Fatawa |
`telawah/functions.php` | recitation/group controls | Telawah |
`anasheed/functions.php` | anasheed item/group/banner controls | Anasheed |
`surveys/functions.php:132, 263` | poll edit/create links | Surveys |
`gallery/functions.php:85` | `zipped_albums/` download path (not an `op`) | Gallery |

All are `GET` links in rendered HTML. No forms POST to generation 1, and no server-side redirect to it — the dependency is entirely browser-mediated hyperlinks, which is why the `w2a_user` bridge cookie was load-bearing for it.

**Authorization at the generation-2 end (security finding, §10):** `functions.php:484` gates these controls with `in_array(UserType($_COOKIE['bbuserid']), ['superadmin','admin'])` — the *same* client-settable-cookie pattern that Option D removed from `admincp/main.php` for being unsafe. It survives here.

---

## 3. Target resolution in old-project (B)

Indexed all 670 distinct `case '<label>'` dispatch labels across `old-project/modules/*/admin/*.php`, `admin/modules/*.php` and `admin.php`, then resolved each referenced op against it.

| Classification | Count | Detail |
|---|---|---|
| **VERIFIED_HANDLER** | **74** | a dispatch label exists in old-project |
| **AMBIGUOUS_TARGET → resolved** | **2 templates → 4 handlers** | `op=cat_add_to_sub$type` / `cat_del_from_sub$type` where `$type` ∈ {`group`,`series`} (`functions.php:485-496`). All four composed labels — `cat_add_to_subgroup`, `cat_add_to_subseries`, `cat_del_from_subgroup`, `cat_del_from_subseries` — exist |
| **MISSING_HANDLER** | **0** | — |
| **BROKEN_TARGET** | **0 at dispatcher level** | but see the caveat below |

**Caveat, stated because "a link resolves" is not "the operation works."** Dispatcher resolution proves a handler is *reachable*, not *functional*. Pass 3A found a concrete counter-example inside this very surface: four Advfile consumer sites query `WHERE khid =` against a table with no such column — a hard SQL error. Dispatcher-level verification cannot detect that class of defect. Per-operation functional verification remains per-domain work, and the 78 figure should be read as "reachable", not "proven working".

**Destructive GET operations within this surface** (flagged separately per the brief): `KhotabDeleteKhotab`, `KhotabDeleteSeries`, `KhotabDeleteGroup`, `KhotabDeleteAuthor`, `KhotabDeletePdf`, `KhotabDeletedocx`, `KhotabDelete_banner`, `AnasheedDeleteAnasheed`, `AnasheedDeleteGroup`, `AnasheedDeleteImage`, `AnasheedDelete_banner`, `TelawahDeleteTelawah`, `TelawahDeleteGroup`, `fatwa_del_question`, `fatwa_del_general_question`, `w2a_fatwa_del`, `del_all_topics_for_catg`, `question_delete_topics`, `cat_del_from_sub*` — **19 destructive operations reachable by GET from generation-2 pages, with no CSRF token and with `GreenZone()` disabled** (Pass 1 §11).

---

## 4. Capability groups (D)

76 referenced op tokens, grouped by business capability.

| # | Capability group | Ops | Laravel ownership | Status |
|---|---|---|---|---|
| 1 | **Khotab content administration** | **27** | absent (only `link-quality`, `uploaders`) | `EXTERNALLY_OWNED` per #6 — **unchanged, not reinterpreted** |
| 2 | **Anasheed administration** | **17** | public read only; **no admin controller, no route, no permission** | **`MIGRATION_GAP`** |
| 3 | **Fatawa administration** | **13** | public read only | **DEFERRED** — Confirmation #1 still Blocked |
| 4 | **Telawah administration** | **10** | only `link-quality/telawah` | `EXTERNALLY_OWNED` per #6 (telawah named alongside khotab) |
| 5 | **Categories administration** | **4** | public read only | **`MIGRATION_GAP`** |
| 6 | **Questionnaire administration** | **3** | `QuestionnaireController`: `index`, `show` only | **`PARTIALLY_MIGRATED`** |
| 7 | **Surveys administration** | **2** | `SurveyController`: 11 methods | **`MIGRATED`** |

Plus **Advfiles** (Pass 3A), reached through group 1 via `KhotabRequestAdvFile`: **`MIGRATION_GAP`**.

And **Gallery** — `gallery/functions.php:85` builds an `old.way2allah.com/zipped_albums/` *path*, not an admin op. A file-serving dependency, not an administration one; recorded, not counted.

---

## 5. Laravel coverage by status (C)

Assessed by behaviour, table and domain — not by operation name. Per the brief, a model, docblock, column or public read query is **not** admin coverage.

| Status | Ops | Evidence |
|---|---|---|
| **MIGRATED** | **2** | `polledit`, `create` → `SurveyController` (`index`, `create`, `store`, `destroy`, `questionsIndex`, `storeQuestion`, `reorderQuestions`, `destroyQuestion`, `stats`, `showAnswer`, `allStats`) + `admin.permission:survey.modsurvey`, `routes/admin.php:55` |
| **PARTIALLY_MIGRATED** | **3** | `question_move_topics`, `question_many_topics`, `question_delete_topics`. Laravel's `QuestionnaireController` has `index()` + `show()` — **read side only**; no move, no bulk, no delete |
| **MIGRATION_GAP** | **21** | Anasheed 17 + Categories 4 |
| **EXTERNALLY_OWNED** | **37** | Khotab 27 + Telawah 10, per #6 — carried forward unchanged |
| **DEFERRED** | **13** | Fatawa, pending Confirmation #1 |
| **DEAD_CANDIDATE** | **0** | nothing in this surface qualifies |
| **UNKNOWN** | **0** | every op resolved to a domain |

**RETIRED: not used anywhere.** No business evidence retires any of these.

---

## 6. Dependency chains and what disappears automatically (E, F)

The 78 operations are not 78 migration units. Source shows them composing into workflows.

**Chain 1 — Advfiles editorial workflow** (Pass 3A, fully traced): `KhotabRequestAdvFile` → `advfile = -1` → `AdvfilesMain` pending queue → `AdvfilesInsertAdvFile`/`ConfirmInsert` (`advfile = 1`) → `Edit`/`Credit`/`Reset`/`UploadImage` → `ConfirmDelete` (`advfile = 0`). **12 operations, one workflow, one migration unit.**

**Chain 2 — Khotab media attachments.** `KhotabUploadPdf` → `Khotab_EditPdf` → `KhotabDeletePdf`; `KhotabUploaddocx` → `Khotab_Editdocx` → `KhotabDeletedocx`; `KhotabRequestBanner` → `KhotabDetailes` → `KhotabDelete_banner`. **Three parallel attach-edit-detach triples, one capability.**

**Chain 3 — Anasheed content lifecycle.** `AnasheedInsertAnasheed` → `AnasheedEditAnasheed` → `AnasheedDeleteAnasheed`, with `AnasheedAddGroup`/`EditGroup`/`DeleteGroup`, banner (`Add_banner`/`Edit_banner`/`Delete_banner`), image (`EditImage`/`DeleteImage`/`GroupIcon`), `InsertMirror`, `Order_anasheed_in_group`, `cat_add_to_anasheed`, `AnasheedWaitingFB`, `AnasheedDetailes`. **17 operations mirroring the Khotab shape almost exactly — strong evidence of a single shared "content administration" pattern rather than two unrelated domains.**

**Chain 4 — Fatawa question hierarchy.** Category → topic (`w2a_fatwa_add`/`edit`/`del`, `del_all_topics_for_catg`) → general question (`fatwa_add/edit/del_general_question`) → question (`fatwa_add/edit/del_question`, `fatwa_many_questions`, `fatwa_move_questions`, `fatwa_delete_questions`). **A three-level hierarchy, one unit.**

**Chain 5 — Social share queue.** `KhotabWaitingFB`, `AnasheedWaitingFB`, `TelawahWaitingFB` — the same capability across three domains, pointing at one shared queue.

### What disappears automatically

| Generation-2 link | Verdict |
|---|---|
`polledit`, `create` (Surveys) | **Behaviour already replaced in Laravel.** These 2 links are **stale pointers**, not migration work. Nothing to port; the links themselves become removable when generation 2 retires. |
`AdvfilesEditAdvFile`, `AdvfilesDeleteAdvFile`, `AdvfilesCreditAdvFile`, `AdvfilesResetAdvFile`, `AdvfilesUploadAdvFileImage` | **Implementation details that disappear when the parent workflow migrates.** They are internal steps of Chain 1, not independently-required capabilities — Laravel must own the *workflow*, not reproduce five op handlers. |
`KhotabCount`, `KhotabLists` | Likely the same: admin listing/counting helpers subordinate to a listing screen, not standalone capabilities. **Needs per-op confirmation.** |
Everything else | **OLD dependency still needed** until its capability is owned by Laravel. |

This is the point of the pass: **78 reachable operations reduce to roughly 7 migration units**, and 2 of the 76 referenced ops require no work at all.

---

## 7. Reconciliation with previous documentation (G)

History is not rewritten. Each entry records the previous conclusion, the new evidence, and the corrected interpretation.

| Previous conclusion | New evidence | Corrected interpretation |
|---|---|---|
**Pass 3A:** "~40 references across 35 distinct operations" | Exhaustive extraction: 101 URL-building references, 76 op tokens, 78 real operations | **My figure was under half the real surface.** Cause: a regex that stopped at the first quote. Corrected in §1. |
**Scope reset §3:** domain priority inferred from `is_w2a_admin()` guard density | Generation 2 *enumerates its own dependencies* in source, with line numbers | Guard density was a reasonable proxy; this is direct evidence and should supersede it for prioritisation. §3's priorities are **not withdrawn** — they are re-ranked in §9 with better data. Notably **Anasheed rises** (17 delegated ops, zero Laravel admin) and **Categories** appears, which guard density under-weighted. |
**Scope reset §3:** Surveys "Priority 3 — verify" | `SurveyController` has 11 methods covering create/edit/delete/reorder/stats, and generation 2 still links to OLD `polledit`/`create` | **Confirmed MIGRATED.** The surviving OLD links are stale pointers. Verification complete; no work. |
**Scope reset §3:** Questionnaire "Priority 3 — verify" | Laravel has `index`+`show`; OLD has 3 mutation ops | **PARTIALLY_MIGRATED, not verified-complete.** Read side owned, mutation side absent. Correction to a Priority-3 assumption. |
**Pass 2A:** `KhotabRequestAdvFile` contextual status `BUSINESS_CONFIRMATION` | Generation 2 links to it from its own khotab pages (`khotab/functions.php:296-299`) | Reachability question **answered affirmatively**; business requirement still open. |
**Earlier coverage audits** (`legacy-vs-laravel-coverage.md`) | Audits were scoped to generation 2's own code; they never examined generation 2's *outbound links* to generation 1 | Those audits are **incomplete rather than wrong** for administration — they correctly assessed what generation 2 implements, and could not assess what it delegates. **No closed public-site migration is reopened by this.** |

### On Business Confirmation #6 — recorded, not merged

Generation 2 delegates 27 Khotab and 10 Telawah operations to `old.way2allah.com`, including `KhotabEditKhotab`, `KhotabDeleteKhotab` and `KhotabUploadPdf`. #6 records that the system handling khotab/telawah content CRUD is *"explicitly not old.way2allah.com's admin panel."*

**These remain two separate propositions and this pass does not merge them.** Both can be simultaneously true: generation 2 may contain links that staff do not use, while a different unnamed system is the one they actually use. The links prove a *technical* dependency, not a *human workflow*. Resolving it needs business input (§8 Q1), not more source reading. The 37 operations stay `EXTERNALLY_OWNED`.

---

## 8. Advfiles — Phase H investigations (results, before escalating to business)

### H-Q1 — `credit`: substantially answered from source and data; **no longer a business question**

| Line of enquiry | Result |
|---|---|
Decrement anywhere in all 3 projects | **none** — negative search for `credit - 1`, `credit-1`, `credit = credit`, decrement patterns |
Scheduled/cron/script consumer | **none** — the only `credit` hits under `w2a_cron`, `automation`, `wget` are incidental occurrences inside bundled ezSQL library files |
Effect on public presentation | **none** — `ShowAdFile()` (`advfiles/index.php:457-480`) displays one advfile by `id` and never reads `credit` |
Effect anywhere | **admin list ordering only** — `WHERE credit>'0' ORDER BY credit DESC` (`advfiles/admin/index.php:244`) |
Value distribution vs content age | 18 rows carry `credit > 0`, all on items published **2007-09 → 2011-07**. The 1,107 `credit = 0` rows span 2005 → 2017. **Nothing has had credit set since 2011.** |

**Conclusion:** `credit` influences no presentation whatsoever and has been dormant for ~15 years. **Recommendation (evidence-based, not a business question): carry the column read-only, build no quota logic.** If metering is ever wanted it is a new feature, not a migration of existing behaviour. Q1 is withdrawn from the business list.

### H-Q2 — what `code` structurally is: **substantially answered**

Pattern census over all 1,133 rows, no payloads reproduced:

| Pattern | Rows | Share |
|---|---|---|
`<img` | 1,121 | 99% |
`<a ` | 1,098 | 97% |
`[audio]` shortcode | 428 | 38% |
`<embed` | 7 | 0.6% |
`<object` | 5 | 0.4% |
**`<iframe`** | **0** | — |
**`<script`** | **0** | — |
empty | 2 | — |

**`code` is not arbitrary HTML in practice.** It is overwhelmingly *an anchor wrapping an image* — a clickable promotional banner — optionally carrying the `[audio]` shortcode, which generation 1 already expands server-side into a player (`Khotab/index.php:1041`). **Zero `<script>` and zero `<iframe>` across the entire table.**

**Consequence:** the real content maps cleanly onto structured fields — image, link target, optional audio — rather than a raw-HTML field. That removes the need to reproduce a stored-XSS surface. Q2 narrows from "must admins author raw HTML?" to a design choice with a strong evidence-backed default, and the 12 `<embed>`/`<object>` outliers are a small migration-time conversion, not a blocker.

### H-Q3 — pending-queue usage: **partially answered, production evidence still absent**

The 12 `advfile = -1` items were published between **2009-12-27 and 2023-07-16**. A request cannot predate its item, so **at least one request was made in or after July 2023**. That is meaningful evidence the request action was still in use recently.

It is **not** proof the queue is being *worked* — `advfile = -1` carries no timestamp of its own, and nothing local records when a request was made or last reviewed. Per the brief I am not claiming operational use without production evidence. Q3 stays, narrowed.

### `advfile = 2` and orphans

**No code path in either legacy project sets or reads `advfile = 2`** — negative search for `advfile=2`, `advfile = '2'`, `advfile > 1`. No removed state found in historical source. 1 row affected, plus 8 advfile rows with no matching khotab row. **Treated as data anomalies needing a reconciliation step at migration time, not a business question.** Q5 withdrawn.

---

## 9. The migration programme (I)

### Level 1 — Capabilities still dependent on OLD

1. Khotab content administration *(`EXTERNALLY_OWNED` — blocked on §8 Q1)*
2. **Anasheed content administration** *(no Laravel admin at all)*
3. Telawah content administration *(`EXTERNALLY_OWNED` — same block)*
4. Fatawa administration *(DEFERRED — Confirmation #1)*
5. **Advfiles editorial workflow**
6. **Category assignment administration**
7. Questionnaire topic administration *(mutation half)*
8. Social share queue *(cross-domain)*

### Level 2 — Workflows needing Laravel ownership

| Unit | Workflow | Ops | Blocked by |
|---|---|---|---|
**OA-1** | Advfiles request → approve → publish → unpublish | 12 | §8 Q3 only (design is otherwise resolved) |
**OA-2** | Anasheed item lifecycle + groups + media | 17 | nothing |
**OA-3** | Category assignment (item/sub-tree, bulk add/remove) | 4 | nothing |
**OA-4** | Questionnaire topic move/bulk/delete | 3 | nothing |
**OA-5** | Khotab media attachments (PDF / docx / banner) | 9 | §8 Q1 (#6 boundary) |
**OA-6** | Khotab + Telawah core content CRUD | ~28 | §8 Q1 |
**OA-7** | Fatawa question hierarchy | 13 | Confirmation #1 |
**OA-8** | Social share queue | 3 | §8 Q2 (is it still operated) |

### Level 3 — Operations

The 78 operations enumerated in §3–§4, mapped to units above. **Note the compression: 78 operations → 8 workflow units, of which 2 ops need no work at all.**

### Proposed order, by dependency and impact

1. **OA-3 Category assignment** — 4 ops, unblocked, no business question, and `khotab_category_index` is already read by Laravel's listing services. Smallest unblocked unit.
2. **OA-4 Questionnaire mutations** — 3 ops, unblocked, and Laravel already owns the read side plus the `questionnaire.*` permissions. Pure completion of an existing screen.
3. **OA-1 Advfiles** — 12 ops, one coherent workflow, design questions now largely resolved by §8.
4. **OA-2 Anasheed** — 17 ops, unblocked, but the largest unit; its shape mirrors Khotab, so building it establishes the pattern OA-5/OA-6 would reuse.
5. **OA-5 / OA-6 Khotab + Telawah** — blocked on the #6 boundary.
6. **OA-7 Fatawa** — blocked on Confirmation #1.
7. **OA-8 Share queue** — blocked on whether it is still operated.

---

## 10. Security findings (additive)

Behaviour to preserve; mechanics not to copy. Adds to Pass 1 §13, Pass 2A §F, Pass 3A §10.

| # | Finding | Evidence |
|---|---|---|
| B-1 | **19 destructive operations reachable by GET** from generation-2 pages, no CSRF token, `GreenZone()` disabled | §3 |
| B-2 | **Generation-2 admin controls gated by a client-settable cookie.** `in_array(UserType($_COOKIE['bbuserid']), ['superadmin','admin'])` — the exact pattern Option D removed from `admincp/main.php` as unsafe, still live on public pages | `7amlat/functions.php:484, 494` |
| B-3 | **Cross-host admin surface by hyperlink.** 101 links carry administrative intent across an origin boundary, relying on a domain-wide cookie. The hardened bridge reduced the credential's value but not the architecture | §2 |
| B-4 | **Dispatcher reachability ≠ functional correctness.** 74 ops resolve to handlers; at least 4 known consumer sites in this surface issue invalid SQL. Audits asserting "reachable" must not be read as "working" | §3 caveat |

---

## 11. Remaining genuine business questions

Reduced from five to **three** — §8 answered two from source and data.

**Q1 — Given that generation 2 delegates 37 Khotab/Telawah operations to `old.way2allah.com` in source, is Confirmation #6's "not old.way2allah.com" still the correct description of where staff actually work?** *Established:* 101 links, 37 of them Khotab/Telawah content CRUD, with line numbers. *Unknown:* whether staff follow them. *Why it matters:* decides whether 37 operations are `EXTERNALLY_OWNED` or `MIGRATION_GAP` — the largest single scope question in the workstream. **Not merged with #6 here; put to the business as evidence.**

**Q2 — Is the social share queue (`KhotabWaitingFB` / `AnasheedWaitingFB` / `TelawahWaitingFB`) still operated?** *Established:* one capability reached from three domains, with handlers. *Unknown:* whether downstream posting still runs. *Why it matters:* OA-8 is either a real unit or removable.

**Q3 — Is the Advfiles pending queue still worked by staff?** *Established:* 12 items pending; at least one request made in or after July 2023. *Unknown:* whether anyone reviews them. *Why it matters:* an active queue justifies OA-1's full workflow; a dormant one may need only read-side rendering plus row migration.

Withdrawn as answerable from evidence: the `credit` question (§8 H-Q1), the raw-HTML question (§8 H-Q2, now a design default), and the `advfile = 2`/orphan question (§8, data anomalies).

---

## 12. Recommended next investigation

**OA-3 — Category assignment administration (4 operations).**

Smallest unblocked unit: `cat_add_to_item`, `cat_fastcopy`, `cat_add_to_sub{group,series}`, `cat_del_from_sub{group,series}`. No business question blocks it, Laravel already reads `khotab_category_index` in its listing services, and `Category` is an existing model. Four operations is small enough to trace end to end — route → guard → control → op → handler → SQL → tables — while confirming the per-op functional verification method that §3's caveat says the 78-op figure still needs.

**OA-4** (Questionnaire mutations, 3 ops) is an equally defensible first pick and is arguably even smaller, since Laravel already owns that screen's read side and its permissions. Either is a sound choice; I'd take OA-3 because category assignment touches the same junction table three other units will need.

**Not recommended first:** OA-1 Advfiles, despite being well understood — it is 12 operations and still has one open business question, where OA-3 has none.
