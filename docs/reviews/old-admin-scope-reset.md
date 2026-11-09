# Old Administration Migration — Scope Reset

**Purpose.** Reset the workstream's scope using evidence already gathered in Pass 1 and Pass 2A, under the owner-confirmed system identity. Identify which `old-project` administration domains are genuine candidates for migration, without assuming every old capability is still active.

**Status.** Documentation only. No application code, seeder, route, permission, schema or database modified. No commit. Khotab Pass 2B remains stopped.

**Reads with:** `old-phpnuke-administration-forensic-discovery.md` (Pass 1), `old-phpnuke-khotab-inline-admin-pass-2a.md` (Pass 2A), `business-confirmation-6-reconciliation.md` (including its 2026-10-10 correction note).

---

## 1. Corrected Confirmation #6 interpretation

**Owner-confirmed deployment identity (ground truth):** `old-project` = `old.way2allah.com`; enhanced legacy = `nuke.way2allah.com`; Laravel = the new migrated application.

| Question | Answer |
|---|---|
| Did the business retire Khotab/Telawah content CRUD? | **No.** The original question (`business-confirmation-report.md:179-213`) offered three choices — design fresh, model on the older system, or a different process — and retirement was not among them. |
| What did #6 actually decide? | **`EXTERNALLY_OWNED` / excluded from that migration's scope.** Every recorded wording is a scope statement: *"not built in this migration"*, *"will not be built in Laravel Admin domain"*. |
| Which system owns it? | **UNKNOWN.** Never named. The only negative fact is that it is **not** `old.way2allah.com`'s admin panel. |
| Is `old-project` that system? | **No** — `old-project` *is* `old.way2allah.com`, which #6 explicitly excluded. The Pass-2A-era hypothesis is withdrawn. |
| Is `INTENTIONALLY_RETIRED_CAPABILITY` (`decision-log.md:521`) supported? | **No.** Unsupported as a description of the business capability. The seeder exclusion it was derived from remains correct. |
| Do Khotab CRUD rows become Laravel migration gaps? | **No.** They are `EXTERNALLY_OWNED — OWNER SYSTEM UNKNOWN`: neither retired nor a gap. |

**Subject separation enforced throughout this document:** Subject A (#6 content CRUD, externally owned, do not migrate) is kept apart from Subject B (old administration behavior that remained dependent on `old-project`). The presence of working CRUD handlers in `old-project` is **not** evidence that Laravel should build Subject A.

---

## 2. Khotab Pass 2A regrouping

Pass 2A's technical tracing is unchanged — 21 guards, 19 `AdminPanel` controls, `AdvancedStats()`, every handler/SQL/table mapping and all seven security findings stand. Only the status column is regrouped.

### GROUP K1 — Confirmation #6 content CRUD → `EXTERNALLY_OWNED — OWNER SYSTEM UNKNOWN`

Do **not** migrate. Do **not** classify as a gap. Do **not** duplicate ownership.

| Pass 2A ref | Capability |
|---|---|
| P-01, P-02 | item edit, item move |
| P-03 | item delete |
| P-04, G-05, G-13 | category attach / detach, editable category tree |
| G-02 | author CRUD (edit, delete, insert) |
| G-04 | group CRUD (add, edit, delete) + `cat_fastcopy` |
| G-06 | series CRUD (edit, delete) + `cat_fastcopy` |
| G-09, G-11 | detach series from group; detach item from series |
| — | rearrange / join (`KhotabReArrange`, `KhotabJoin`) |
| — | `KhotabCount`, `KhotabLists` |

### GROUP K1-AMBIGUOUS — #6 boundary not established

Marked rather than resolved. **Expanding #6 by assumption is the specific error this reset exists to avoid**, so these are held separately until the #6 boundary is clarified.

| Pass 2A ref | Capability | Why ambiguous |
|---|---|---|
| P-05, G-14, G-18 | mirror create / edit / delete / re-parent | Mirrors are "alternate download links" — named in #6's own evidence bullet as part of the ~30-control permission structure (`business-confirmation-report.md:191`), which argues inside #6. But Laravel already owns mirror *read/health* via `LinkQualityStatsController`, so ownership is already split. |
| P-06…P-08 | banner upload / edit / delete | Media attachment, not content CRUD proper. #6's wording covers "add, edit, or remove video sermons", not attachments. |
| P-09…P-14 | PDF and Word transcript upload / edit / delete | Same reasoning as banners. `khotab.uploadpdf`/`adddocx`/`deletedocx` are among the ~30 excluded keys, which argues inside #6. |
| G-19, G-20, G-01 (count) | comment moderation | `khotab.approvecomment`/`delcomment` are excluded keys, but #6's wording is "content-CRUD" and moderation is a distinguishable workflow. Pass 2A already flagged this. |

### GROUP K2 — Old administration behavior outside #6 → this workstream's real candidates

Not ordinary content CRUD. Reconciled independently below; none is hidden behind #6.

| Pass 2A ref | Capability | Enhanced legacy | Laravel | Status |
|---|---|---|---|---|
| P-16 | **homepage-feature request workflow** (`KhotabRequestAdvFile` → `nuke_islamic_advfile`) | **module absent entirely** | **zero matches** | `NEEDS_BUSINESS_CONFIRMATION` → candidate gap |
| G-17 | **advfile credit display + edit/delete** (`AdvfilesEditAdvFile`, `AdvfilesDeleteAdvFile`) | **module absent entirely** | **zero matches** | same workflow as P-16 |
| P-18 | **Facebook/share queue** (`KhotabWaitingFB` → `KhotabWaitingList` → `KhotabConfirmAddToShare`), passes `curPageURL()` | **module absent entirely** | **zero matches** | `NEEDS_BUSINESS_CONFIRMATION` → candidate gap |
| P-17 | **banner request workflow** (`KhotabRequestBanner`) | absent | zero matches | `NEEDS_BUSINESS_CONFIRMATION` |
| G-16 | **per-item diagnostics** (`AdvancedStats()`) | absent | aggregate equivalents exist | `PARTIALLY_MIGRATED` — presentation delta |
| P-19 | GIF/JPG preview generation (`get_gif_jpg.php`) | absent | zero matches | `NEEDS_BUSINESS_CONFIRMATION` |
| — | QA/maintenance filters (`lowsize`, `highsize`, `invalid_link`, `broken_link`, `un_cat`, `autorename`) | absent | partially via `link-quality` | `PARTIALLY_MIGRATED` — needs tracing |
| — | moderator management + activity logs (`KhotabListModertors`, `KhotabModertorsLogs`, `KhotabClearModertorsLogs`) | absent | zero matches | `NEEDS_BUSINESS_CONFIRMATION` |
| — | export operations (`KhotabExport`, `…Anasheed`, `…Telawah`, `…Mirror`, `MirrorExport`) | absent | zero matches | `NEEDS_BUSINESS_CONFIRMATION` |

### GROUP K3 — Already migrated → DO NOT REIMPLEMENT

| Capability | Laravel owner |
|---|---|
| Link repair / quality / lost links (`KhotabRepair`, `KhotabLostedLink`) | `LinkQualityStatsController` + `khotab.repair` (`routes/admin.php:157-178`); adds per-row recheck and fix-size the old op lacked |
| Uploader listing / tracking | `UploaderController` + `khotab.uploaders` |
| Admin-sees-hidden-content (G-07, G-08, G-10, G-12, G-21) | **deliberately not reproduced** — `KhotabSearchController:14`. Caveat from Pass 2A §A stands: that is a docblock claim in one file, not a `decision-log` entry. |

### GROUP K4 — Dead / obsolete / unresolved

| Ref | Item | Note |
|---|---|---|
| G-03 | commented-out guard at `modules/Khotab/index.php:260` | dead code |
| C-1 | `KhotabListDown` | linked from `admincp.htm` but absent from all ~95 `case` labels — probably already dead |
| P-15 | direct `media/docx/…` file link | static path, no handler; also security finding K-6 |

---

## 3. Old administration domain map

Built from Pass 1's inventory. `ops` = distinct `case '<op>'` labels under `modules/<X>/admin/`; `guards` = `is_w2a_admin()` occurrences in `modules/<X>/index.php`. Enhanced-legacy column records whether a counterpart **module directory** exists in `7amlat` (verified by directory listing, with renames mapped). Laravel column records route/controller presence (verified by `grep` over `routes/` and `app/Domain/*/Http/Controllers/`).

| Domain | Inline | Central ops | Enhanced legacy | Laravel | Prior business decision | Priority |
|---|---|---|---|---|---|---|
| **Khotab** | 21 | 100 | `khotab/` — admincp page is a broken chat duplicate (`wave-6-analysis.md:44`) | `uploaders`, `link-quality` only | **#6 EXTERNALLY_OWNED** (CRUD) | **2** (K2 items only) |
| **advfiles** | 5 | 11 | **absent** | **zero matches** | none | **1** |
| **fb_share_list** | 0 | 11 | **absent** | **zero matches** | none | **1** |
| **Mobile** | 7 | 26 | **absent** | 6 file matches (static "mobile-app" page, `legacy-vs-laravel-coverage.md:158-159`) — not a content admin | none | **1** |
| **Tutorials** | 0 | 25 | **absent** | **zero matches** | none | **1** |
| **Quiz** | 0 | 41 | **absent** | **zero matches** | none | **1** |
| **Feedback** | 0 | 21 | **absent** | 3 matches (unverified) | none | **1–2** |
| **contact** | 0 | 10 | **absent** | **zero matches** | none | **1–2** |
| **FAQ** | 0 | 10 | `help/` (partial) | **zero matches** | none | 2 |
| **home_files** | 2 | 4 | **absent** | **zero matches** | none | 2 |
| **ads** / **banners_ads** / **report_ads** | 0 | 6/6/2 | **absent** | 3 matches ("banner" generic) | **#2** (Ramadan + "Share This Site" banners) — partial | 2 |
| **Fatwa** / **AlFatawa** | 12 | 43/0 | `fatawa/` (rename) | 18 matches, public + admin | **#1** (is this content still needed?) — **Blocked** | **Excluded pending #1** |
| **Telawah** | 7 | 43 | `telawah/` | `link-quality/telawah` only | **#6 EXTERNALLY_OWNED** (CRUD) | 2 (non-CRUD only) |
| **Anasheed** | 9 | 53 | `anasheed/` | 13 matches, public | none found for admin | **2** |
| **w2acd** / **w2a_cd** / **cd** | 5 / 0 / 0 | 55/51/8 | `w2acd/`, `cds/` | 4 + 3 matches, public | none found for admin | **2** |
| **Satellite** | 6 | 28 | `channels/` (rename) | `broadcasting` + public channels | partial | 2 |
| **Cat** / **Cat - Copy** | 3 / 3 | 24/22 | `categories/`, `vars_categories/` | public categories | none found for admin | 2 |
| **new_album** / **default_picasa_album** | 1 / 0 | 8/2 | `gallery/` | 5 + 3 matches, public | none found for admin | 2 |
| **live** | 0 | 6 | `live-stream/` | `broadcasting` + public | **#4** FlashChat/Zoom retired (decision-log #14) — scope unclear for `live` | 3 |
| **BackUp** | 0 | 32 | `admincp/backup` + `backup.php` | none | **#7** (is an external tool still using this?) + `decision-log.md:196` UNRESOLVED | 3 — separate track |
| **Surveys** | 3 | 8 | `surveys/` | `survey` ✓ | — | 3 (verify) |
| **questionnaire** | 0 | 3 | `admincp/questionnaire` | `questionnaire` ✓ | — | 3 (verify) |
| **Locations** | 7 | — | `admincp/locations` | `locations` ✓ | `LOCATIONS_MAIN_INDEX = DO_NOT_BUILD` (#39) | 3 (verify inline guards only) |
| **Your_Account** | 2 | 6 | auth | auth/permissions | **#5** legacy cross-login bridge | 3 — security track |
| **Chat** / **chat_server** / **flashxml** / **123flashchat** | 0 | 1/1/6/0 | `chat_room/` | retired | **#4** retired, "no replacement of any kind" (decision-log #14) | **Excluded** |
| **hedaya_table** | 0 | 6 | `chat_room/table.php` | retired | IF-033 re-scoped to task 6.5 | **Excluded** |
| **Montada** | 0 | 3 | `admincp/forumConfig` | vBulletin | out of migration scope | **Excluded** |
| **ramadan_competition** | 0 | 3 | `pages/ramadan.php` | partial | **#2** (still promoted each season?) | 3 |
| **update_khotab_count** / **docx** / **high_rank** / **Classificate_anasheed** / **Dump_files** | 0 | 2/2/1/1/2 | partial | partial | — | 3 |

### The structural finding this map exposes

**Every prior coverage audit was scoped to the enhanced legacy application.** `docs/reviews/legacy-vs-laravel-coverage.md` has **no entry at all** for Quiz, Tutorials, FAQ, contact, advfiles, fb_share_list or Feedback — verified by direct search. Those modules exist **only** in `old-project`, so they were never in any audit's scope, carry no coverage verdict, no business decision, and no Laravel counterpart.

That is precisely the blind spot this workstream exists to close, and it is why raw operation count is a poor priority signal: Quiz has 41 operations and Khotab has 100, but Khotab's are largely `EXTERNALLY_OWNED` while Quiz's were simply never looked at.

---

## 4. Ranked candidate domains

### Priority 1 — old admin exists, enhanced legacy did not replace it, Laravel does not own it, no business decision covers it

| Domain | Ops | Inline | Why |
|---|---|---|---|
| **advfiles** | 11 | 5 | Module absent from enhanced legacy; zero Laravel matches; **directly coupled to Khotab's public page** (Pass 2A G-17, P-16) and to `nuke_islamic_advfile`. Not content CRUD, so outside #6. |
| **fb_share_list** | 11 | 0 | Module absent; zero Laravel matches; implements the three-step share workflow Pass 2A P-18 surfaced, the **only control with a genuine page-context dependency** (`curPageURL()`). |
| **Mobile** | 26 | 7 | Module absent; Laravel's 6 matches are a static "mobile-app" page, not a content admin. 7 inline guards indicate real contextual administration. Unassessed by any audit. |
| **Tutorials** | 25 | 0 | Module absent; zero Laravel matches; no decision. Entirely unassessed. |
| **Quiz** | 41 | 0 | Module absent; zero Laravel matches; no decision. Largest wholly-unassessed surface. |
| **Feedback**, **contact** | 21, 10 | 0, 0 | Modules absent; contact has zero Laravel matches, Feedback 3 unverified. Both unassessed. |

### Priority 2 — partial ownership or unclear mapping, needs detailed reconciliation

Khotab (K2 items only), Telawah (non-CRUD only), Anasheed, w2acd/w2a_cd/cd, Satellite, Cat, new_album, FAQ, home_files, ads/banners_ads.

Anasheed (53 ops, 9 guards) and w2acd (55 ops, 5 guards) are the largest Priority 2 surfaces: both have enhanced-legacy *public* modules and Laravel *public* controllers, but **no evidence of admin replacement in either generation**. They may be Priority 1 in substance; proving it needs the same per-domain check performed for Khotab.

### Priority 3 — likely already migrated, verify only

Surveys, questionnaire, Locations (inline guards only), live, BackUp (separate track per #7), Your_Account (security track per #5), and the small residual modules.

### Excluded

Chat / chat_server / flashxml / 123flashchat / hedaya_table (retired, #4 + decision-log #14, "no replacement of any kind"); Montada (vBulletin, out of scope); Fatwa/AlFatawa (**pending Confirmation #1**, still Blocked — must not be pre-empted); Khotab/Telawah content CRUD (**#6 externally owned**).

---

> **Pass 3C supersedes Pass 3B's *scope*, not its findings — see `old-contextual-admin-surface-pass-3c.md`.** Pass 3B's 76-token map is correct for `$inlineAdminUrl` delegation but covers **52 of 127** generation-1 contextual operations (41%). The true contextual admin surface is **151 operations / 17 capability families**, and it adds three workflow units Pass 3B could not see: **W2ACD administration** (a fifth content domain, administrable only in generation 1), **moderator oversight & audit**, and **Downloads moderation**. Pass 3B's 8 workflow units and Laravel-coverage tally stand within their stated scope.

> **Pass 3B supersedes §3's prioritisation method — see `old-admin-inline-dependency-map-pass-3b.md`.** Exhaustive extraction found **101** generation-2 → OLD URL references across **76 op tokens / 78 real operations** (Pass 3A's "~40 / 35" was under half the surface; corrected there). 74 resolve to verified handlers, 4 more via 2 dynamically-composed templates, **0 missing**. They compress to **8 workflow units**. §3's domain priorities are **not withdrawn**, but guard density is superseded by generation 2's own link inventory: **Anasheed rises** (17 delegated ops, zero Laravel admin) and **Categories** appears, both under-weighted by guard counts; **Surveys is confirmed MIGRATED** (its OLD links are stale pointers) and **Questionnaire is PARTIALLY_MIGRATED**, not verified-complete.

> **Pass 3A is complete — see `old-admin-advfiles-pass-3a.md`.** It confirms advfiles as a genuine `MIGRATION_GAP` (an 11-operation three-state editorial workflow, 12 items pending and 1,129 published right now, nothing in Laravel). It also found a **stronger dependency map than §3's guard-density proxy**: generation 2 contains ~40 hardcoded `$inlineAdminUrl` links into `old.way2allah.com` covering **35 distinct operations** across Khotab, Fatawa, Telawah, Questionnaire, Categories, Surveys and Gallery (Pass 3A §7.2). Generation 2 enumerates its own dependencies on generation 1; future passes should prioritise from that list rather than from guard counts. Nothing in §3 or §4 below is withdrawn — the priority ordering simply has better evidence available now.

## 5. Recommended next deep-dive: **advfiles**

Not Khotab, and not the largest surface.

**Why it is likely a real remaining old dependency.** The module directory is **absent from the enhanced legacy project entirely** (verified by directory listing) — so this is exactly the pattern the owner described: a domain the enhanced admincp never rebuilt, leaving `old-project` the active owner. It is also the one Priority 1 domain whose dependency is already *proven* rather than inferred: Pass 2A found Khotab's public item page calling into it from two separate guards — G-17 renders the remaining display credit with `AdvfilesEditAdvFile` / `AdvfilesDeleteAdvFile` links, and P-16's `KhotabRequestAdvFile` writes the request into `nuke_islamic_advfile`. A live cross-module workflow between two old-project modules is stronger evidence of continued use than operation count anywhere else on the map.

**Why it is not already covered by Laravel.** Zero matches for `advfile` across `routes/` and all `app/Domain/*/Http/Controllers/`. No model, no route, no permission, no test. `legacy-vs-laravel-coverage.md` has no entry for it.

**Why no existing business decision excludes it.** It appears in no confirmation (#1–#9, #13), no decision-log entry, and no retirement list. Critically, it is **not** content CRUD, so Confirmation #6 does not reach it — the homepage-feature *request* workflow is an editorial queue, not an add/edit/delete of a sermon. Pass 2A already classified P-16 and G-17 as `NEEDS_BUSINESS_CONFIRMATION`, never as retired.

**Why it is the right size for a first pass.** 11 central operations and 5 inline guards — roughly a tenth of Khotab's surface, and small enough to trace end to end (route → guard → control → op → handler → validation → SQL → table) in one pass while the method is still being proven.

**What needs tracing.**

1. The 11 `case` operations in `modules/advfiles/admin/` — full chain each.
2. The 5 inline guards in `modules/advfiles/index.php`.
3. `nuke_islamic_advfile` schema and semantics: what `credit` means, how it decrements, what consumes it.
4. The `KhotabRequestAdvFile` → queue → approval → display chain, including which Khotab column (`advfile`) gates P-16's visibility.
5. Whether the "homepage feature" output has a Laravel equivalent on the *public* side — Laravel's homepage already renders featured content, so part of the **consumption** half may already be migrated even though the **administration** half is not. This is the most likely place to find a partial migration, and the reason not to assume a clean gap.
6. Whether `nuke_islamic_advfile` carries live recent rows — a read-only data check that would show whether the workflow is still in use.

**Pair it with `fb_share_list`** as the immediate follow-on, not in the same pass: same absent-from-enhanced pattern, same size (11 ops), and it owns the one control with a genuine page-context dependency. Together they would resolve both outstanding Pass 2A K2 workflow questions.

---

## 6. Still genuinely requiring business confirmation

Only items source and history cannot settle.

| # | Question | Why it cannot be answered from source |
|---|---|---|
| B-1 | **Which system administers Khotab/Telawah content today?** #6's unnamed external owner. | Never named; `old-project` now excluded by owner confirmation, removing the only candidate ever proposed. Blocks any decision about Subject A, and determines whether Laravel must eventually absorb it. |
| B-2 | **What is Confirmation #6's exact boundary?** Does it cover mirrors, banners, transcripts and comment moderation, or only item/author/series/group CRUD? | #6's wording is "add, edit, or remove video sermons and Qur'an recitations"; its evidence bullet mentions "alternate download links" among the ~30 controls. The two readings disagree, and K1-AMBIGUOUS cannot be resolved without this. |
| B-3 | **Is the homepage-feature request workflow still operated?** (`nuke_islamic_advfile`) | Technically understood; whether the editorial process still runs is a business fact. Gates the recommended deep-dive's conclusion, not its investigation. |
| B-4 | **Is the Facebook/share queue still operated?** | Three-step workflow clearly built; whether downstream posting still runs is unknowable from source. |
| B-5 | **Is `old.way2allah.com` still reachable, and does anyone still use it?** | Determines whether the Pass 1 security findings are live exposures and which domains to prioritise by real usage rather than by structural proxy. Access logs for `old-project` paths would answer both. |
| B-6 | **Are the wholly-unassessed domains still in use?** Quiz, Tutorials, FAQ, contact, Feedback. | They exist only in `old-project` and were never in any audit's scope. Before tracing ~100 combined operations, knowing which are live would avoid forensic work on dead features. |

Questions deliberately **not** asked, because existing documentation already answers them: whether live chat rooms survive (#4, retired, "no replacement of any kind"); whether `locations.htm` should be built (#39, `DO_NOT_BUILD`); whether the khotab uploader add-flow should be built (#39, `DO_NOT_BUILD`); whether Fatwa content is still needed (#1, open but already tracked — not re-asked here, only respected as an exclusion).

---

## 7. Security track — kept separate

Pass 1 §13 and Pass 2A §F remain the record. Now that `old-project` is confirmed as `old.way2allah.com`, those findings concern that deployment directly. Remediation is a separate track and is deliberately **not** allowed to influence the capability inventory above.

Unchanged in severity, and independent of every ownership question in this document: **S-1** (unsigned `w2a_user` JSON cookie accepted as an admin credential, validated only for `uid` existence), **S-2** (`radminsuper` read from that cookie rather than from the row just fetched, yielding super-admin), **S-3** (`GreenZone()` returns immediately, disabling all per-operation authorization), and **K-5** (`eval(IncMe(...))` renders every admin template). The deployment holds live production credentials for `w2acp_db2025-2` — the database Laravel reads (`old-project/config-w2a.php:31`).
