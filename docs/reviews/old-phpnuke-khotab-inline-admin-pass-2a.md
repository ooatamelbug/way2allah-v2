# Pass 2A — Khotab Inline Administration Reconciliation

**Companion to** `old-phpnuke-administration-forensic-discovery.md` (Pass 1). Investigation only. No source, database, schema, route or configuration was modified in any of the three projects; the only write was this document.

---

## 0. Headline: this workstream collides with a closed business decision

Before the tables, the finding that governs everything below.

**Every content-administration capability the old Khotab inline panel exposes has already been business-decided as out of scope for this migration.** Business Confirmation #6, recorded in four independent places:

- `docs/PROJECT-HANDOFF.md:76` — *"Khotab/telawah admin content-CRUD (Confirmation #6) | **Closed — handled by another dashboard/system, not built in this migration** | Business-confirmed fact: managed elsewhere. **Still-unconfirmed detail:** which system — explicitly confirmed **not** `old.way2allah.com`'s admin panel, actual system not yet named, not investigated further per explicit instruction."*
- `docs/PROJECT-HANDOFF.md:95` — same, in the confirmation list.
- `docs/decision-log.md:150` — *"#6 … content management is handled by another dashboard/system, not built in this migration … that system is explicitly **not** `old.way2allah.com`'s admin panel — it is a different system … Roadmap task 6.4 closed; `AdminPermissionSeeder`'s ~30 excluded khotab/telawah CRUD permission keys remain excluded regardless of which system this turns out to be."*
- `docs/decision-log.md:521` — *"`khotab.*`'s ~30 excluded content-CRUD keys reconfirmed, not reopened: still `INTENTIONALLY_RETIRED_CAPABILITY` / out of migration scope per Business Confirmation #6."*

Corroborated structurally: `AdminPermissionSeeder` seeds only `khotab.repair`, `khotab.uploaders`, `khotab.index`, `khotab.large`, `khotab.uploader`, `khotab.addeddate` — **no** `additem`/`edititem`/`delitem`/`addauthor`/`addseries`/`addgroup`/`addmirror`/`uploadpdf`/`adddocx`/`approvecomment`/`uploadbanner` keys, despite all ~31 existing in the legacy `permissions` blob (verified in §11 of Pass 1 against live rows for uids 16715 and 56540).

And `docs/reviews/wave-6-analysis.md:44` establishes that **Generation 2 never implemented Khotab content CRUD either** — `admincp/khotab/index.php` is *"a broken, misnamed, copy-pasted duplicate of `admincp/chat/index.php`"*, running `SELECT * FROM room ORDER BY enable DESC, sequence ASC` against the FlashChat connection, with `khotab/menu.php` defining ~30 khotab-shaped permission keys for *"real functionality that was then never implemented."*

### The conflict I cannot resolve, and will not paper over

Confirmation #6 says the other system is **explicitly not `old.way2allah.com`'s admin panel**. This investigation is examining `/mnt/Projects/Php/pure/old-project`. Three facts sit in unresolved tension:

1. In an earlier session the owner instructed: *"The previous interpretation of old.way2allah.com was WRONG. Forget old.way2allah.com completely… The CURRENT LEGACY application we are working on is `nuke.way2allah.com`."* So "old.way2allah.com" was itself a misidentification at the time #6 was answered.
2. `old-project` is a PHP-Nuke installation sharing the production database and cookie domain, with a complete, working Khotab administration surface — exactly the profile of "another dashboard/system that handles content management."
3. #6's answer therefore may have excluded a system that was never the right candidate, leaving the real one unnamed — and `old-project` is now the strongest candidate for it.

**Either reading leads away from implementation:**

- If `old-project` **is** the Confirmation-#6 system, then nothing in this pass is a migration gap. The decision already stands, the permission keys stay excluded, and the correct action is to record that identification and close the workstream.
- If `old-project` is **not** that system, then #6's open detail is still open, and a third system exists that nobody has named. Building Khotab CRUD in Laravel before knowing which system is authoritative risks two systems writing the same `nuke_*` rows.

This is §Final-Answer 5's real answer, and it is a question, not a slice.

---

## 1. Complete inline surface — all 21 guards

Every `is_w2a_admin()` block in `modules/Khotab/index.php`. None collapsed.

| # | Line | Page context | What the guard exposes | Mechanism |
|---|---|---|---|---|
| G-01 | 174 | Khotab module index | Pending-comment count (`SELECT id FROM nuke_islamic_comments WHERE view='0'`) + `admincp.htm` panel | template |
| G-02 | 255 | Author page | "لوحة تحكم الداعية" — `author_cp.htm` | template |
| G-03 | 260 | Author page | **Dead** — commented out; `apply_search()` runs for everyone | — |
| G-04 | 343 | Group page | "لوحة تحكم المجموعة" — `group_cp.htm` | template |
| G-05 | 376 | Item page | Category add/remove table, only when item has >1 category | inline HTML |
| G-06 | 464 | Series page | "لوحة تحكم السلسلة" — `series_cp.htm` | template |
| G-07 | 494 | `ListGroup()` | **Data visibility** — drops `hidden='0'` from the groups query | SQL branch |
| G-08 | 524 | `ListSeries()` | **Data visibility** — drops `hidden='0' AND count > '0'` | SQL branch |
| G-09 | 590 | `ListSeries()` row | `KhotabUpSeries` link (detach series from group), only when `group_id > 0` | inline link |
| G-10 | 626 | `ListKhotab()` | **Data visibility** — drops `hidden='0'` from the items query | SQL branch |
| G-11 | 690 | `ListKhotab()` | Sets `$show_admin_panel=true`; emits JS `out_khotab()` → AJAX `GET admin.php?op=KhotabUpKhotab&id=` | inline JS |
| G-12 | 980 | Item page | Hidden item is **viewable**, title suffixed " مخفي "; non-admin gets `die()` | visibility |
| G-13 | 1001 | Item page | `Tree($id, $cat, '1')` — **editable** category tree vs `'0'` read-only | helper mode |
| G-14 | 1007 | Item page | If `$_COOKIE['MirrorMove']` set: "نقل الجودة البديلة" → `KhotabMoveMirror&id=…&khid=…` | cookie-driven link |
| G-15 | 1022 | Item page | **`AdminPanel($id)`** — the 18-control toolbar (§2) | function |
| G-16 | 1032 | Item page | **`AdvancedStats($id)`** — per-item backup/link diagnostics (§3) | function |
| G-17 | 1037 | Advfile view | Remaining display credit + `AdvfilesEditAdvFile` / `AdvfilesDeleteAdvFile` | inline links |
| G-18 | 1216 | Mirror list row | Per-mirror `KhotabEditMirror`, `KhotabDeleteMirror`, `khotab-MirrorMove-{id}.htm` | inline links |
| G-19 | 1324 | Comments block | `comment_admin.htm` moderation header | template |
| G-20 | 1341 | Per comment | "(تعديل التعليق)" → `KhotabWaitingList&id={comment_id}` | inline link |
| G-21 | 1356 | Download handler | Hidden item downloadable by admin; non-admin `die()`; then `downcount+1` | visibility |

Operations reachable from the four control-panel templates:

| Template | Guard | Operations |
|---|---|---|
| `admincp.htm` | G-01 | `KhotabCount`, `KhotabInsertAuthor`, `KhotabListDown`, `KhotabWaitingList` |
| `author_cp.htm` | G-02 | `KhotabEditAuthor`, `KhotabDeleteAuthor`, `KhotabAddGroup`, `KhotabInsertSeries`, `KhotabInsertKhotab`, `KhotabLists`, `KhotabCount` |
| `group_cp.htm` | G-04 | `KhotabEditGroup`, `KhotabDeleteGroup`, `KhotabInsertSeries`, `KhotabInsertKhotab`, `KhotabReArrange`, `KhotabLists`, `cat_fastcopy` |
| `series_cp.htm` | G-06 | `KhotabEditSeries`, `KhotabDeleteSeries`, `KhotabInsertKhotab`, `KhotabReArrange`, `KhotabLists`, `cat_fastcopy` |

`comment_admin.htm` contains no `op=` links — it is a markup header only; the actionable per-comment link is G-20.

---

## 2. `AdminPanel($id)` full decomposition

`modules/Khotab/index.php:784-889`. Reads `SELECT * FROM nuke_islamic_khotab WHERE id='$id'` (`$id` `intval()`-cast), emits a `<div>` grid wrapped in a "لوحة تحكم المشرفين" header, then a fancybox script block. 18 controls; four are conditional on column state.

| # | Label | `op` | Mutation or nav | Condition |
|---|---|---|---|---|
| P-01 | تعديل | `KhotabEditKhotab` | nav → form | — |
| P-02 | نقل | `KhotabMoveKhotab` | nav → form | — |
| P-03 | مسح | `KhotabDeleteKhotab` | nav → confirm | — |
| P-04 | إضافة تصنيف | `cat_add_to_item` | nav → form | — |
| P-05 | إضافة وصلة | `KhotabInsertMirror` | nav → form | — |
| P-06 | رفع بانر | `KhotabDetailes` | nav → form | `banner == 0` |
| P-07 | تعديل البانر | `KhotabDetailes` | nav → form | `banner != 0` |
| P-08 | حذف البانر | `KhotabDelete_banner` | nav → confirm | `banner != 0` |
| P-09 | رفع التفريغ | `KhotabUploadPdf` | nav → form | `pdf == 0` |
| P-10 | تعديل التفريغ | `Khotab_EditPdf` | nav → form | `pdf != 0` |
| P-11 | حذف التفريغ | `KhotabDeletePdf` | nav → confirm | `pdf != 0` |
| P-12 | رفع الوورد | `KhotabUploaddocx` | nav → form | `docx == 0` |
| P-13 | تعديل الوورد | `Khotab_Editdocx` | nav → form | `docx != 0` |
| P-14 | حذف الوورد | `KhotabDeletedocx` | nav → confirm | `docx != 0` |
| P-15 | تنزيل الوورد | direct file link `media/docx/{floor(id/1000)}/{id}.docx` | **download, no handler** | `docx != 0` |
| P-16 | طلب رئيسية | `KhotabRequestAdvFile` | workflow request | `advfile == 0` |
| P-17 | طلب بانر | `KhotabRequestBanner` | workflow request | `banner == 0` |
| P-18 | اضافة للشير | `KhotabWaitingFB` + `&link=curPageURL()` | workflow request | — |
| P-19 | سحب الصور | `get_gif_jpg.php?id=` (fancybox iframe) | generate GIF/JPG preview | — |

Note P-15 and P-19 bypass `admin.php` entirely — a direct filesystem path and a standalone script. P-19's `get_gif_jpg.php` is the only control whose target is not an `op` handler.

**Does public-page context provide capability unavailable centrally?** For P-01…P-14, no — they are id-parameterised links to the same central handlers, so context saves navigation, not capability. For P-18 it does: `&link=curPageURL()` captures the *current canonical URL* of the page being viewed, which the central handler cannot reconstruct. That is the one genuine context dependency in the whole panel.

---

## 3. `AdvancedStats($id)` decomposition

`modules/Khotab/index.php:891+`. Reads `nuke_islamic_khotab` and `nuke_islamic_advanced` for one item and renders a per-item diagnostic block. Pure read — no mutation.

| Field | Derivation |
|---|---|
| Download state | `down == 0` → "بإنتظار التنزيل ({trial} محاولة)"; `== 1` → "تم تنزيل الملف"; else → "لم يتم ({trial} محاولة)" |
| Link health | `online == linksize` → "تعمل" else "لا تعمل" |
| Last check | `CoolShortDate(checktime)` |
| Size on disk | `CoolSize(online)` |
| CD status | `cd == ''` → "لم يتم نسخ الملف حتى الآن" |
| Broken reports | `broken == '0'` → "لم يتم الإبلاغ"; else `SELECT count FROM nuke_brokenlinks WHERE khid=… AND divid='1'` |
| Media metadata | the `nuke_islamic_advanced` row (`perf`, `vres`, `ares`, `vbit`, `abit`, `width`, `height`, `frate`, `srate`, `adur`…) |

This is the same data family `backup.php` writes (Pass 1 §12 and the 56540/16715 investigation) and that Laravel's `LinkQualityStatsController` aggregates.

---

## 4. Master reconciliation table

Enhanced-legacy column reflects `wave-6-analysis.md:44`'s finding that `admincp/khotab/` is a broken chat-module duplicate — i.e. **Generation 2 implemented none of this**, so the column reads `NEVER_IMPLEMENTED` throughout except where noted.

| # | Old context | Control / action | Old op | Side effect / table | Enhanced | Laravel equivalent | Status | Contextual shortcut | Evidence |
|---|---|---|---|---|---|---|---|---|---|
| P-01 | item page | edit item | `KhotabEditKhotab` | `nuke_islamic_khotab` UPDATE | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | handoff:76; seeder lacks `khotab.edititem` |
| P-02 | item page | move item | `KhotabMoveKhotab` | `nuke_islamic_khotab` UPDATE (`ser_id`/`group_id`/`author`) | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | ditto, `khotab.moveitem` excluded |
| P-03 | item page | delete item | `KhotabDeleteKhotab` → `…Confirm…` | `nuke_islamic_khotab` DELETE + dependents | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `NO_CONTEXTUAL_SHORTCUT_NEEDED` | `khotab.delitem` excluded |
| P-04 | item page | attach category | `cat_add_to_item` | `khotab_category_index` INSERT | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.addcat` excluded |
| P-05 | item page | add mirror | `KhotabInsertMirror` | `nuke_islamic_mirror` INSERT | NEVER_IMPLEMENTED | none (read-only in `LinkQualityStatsController`) | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.addmirror` excluded |
| P-06/07/08 | item page | banner upload / edit / delete | `KhotabDetailes`, `KhotabDelete_banner` | `nuke_islamic_khotab.banner` + image files | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.uploadbanner` excluded |
| P-09…P-11 | item page | PDF transcript upload / edit / delete | `KhotabUploadPdf`, `Khotab_EditPdf`, `KhotabDeletePdf` | `nuke_islamic_khotab.pdf` + PDF files | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.uploadpdf` excluded |
| P-12…P-14 | item page | Word transcript upload / edit / delete | `KhotabUploaddocx`, `Khotab_Editdocx`, `KhotabDeletedocx` | `nuke_islamic_khotab.docx` + docx files | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.adddocx`/`deletedocx` excluded |
| P-15 | item page | download Word file | direct path | filesystem read | NEVER_IMPLEMENTED | none | `NOT_MIGRATED` (trivial) | `NO_CONTEXTUAL_SHORTCUT_NEEDED` | no handler; a static link |
| P-16 | item page | request homepage feature | `KhotabRequestAdvFile` | `nuke_islamic_advfile` (workflow queue) | NEVER_IMPLEMENTED | none found | `NEEDS_BUSINESS_CONFIRMATION` | `BUSINESS_CONFIRMATION` | searched routes/admin.php + Admin controllers — no match |
| P-17 | item page | request banner | `KhotabRequestBanner` | banner-request queue | NEVER_IMPLEMENTED | none found | `NEEDS_BUSINESS_CONFIRMATION` | `BUSINESS_CONFIRMATION` | ditto |
| P-18 | item page | add to Facebook share queue | `KhotabWaitingFB` (+ current URL) | `nuke_fb_share_list` / waiting queue | NEVER_IMPLEMENTED | none found | `NEEDS_BUSINESS_CONFIRMATION` | **`CONTEXTUAL_SHORTCUT_REQUIRED_FOR_WORKFLOW`** | only control that needs `curPageURL()` |
| P-19 | item page | generate GIF/JPG preview | `get_gif_jpg.php` | image generation | NEVER_IMPLEMENTED | none found | `NEEDS_BUSINESS_CONFIRMATION` | `BUSINESS_CONFIRMATION` | standalone script |
| G-01 | module index | pending-comment count + panel | `KhotabWaitingList`, `KhotabCount`, `KhotabInsertAuthor`, `KhotabListDown` | `nuke_islamic_comments` read | NEVER_IMPLEMENTED | none | `NEEDS_BUSINESS_CONFIRMATION` (comments) / `RETIRED_CONFIRMED` (author insert, #6) | `CONTEXTUAL_SHORTCUT_RECOMMENDED` | `khotab.approvecomment` excluded from seeder |
| G-02 | author page | author CP (edit/delete author, add group/series/item) | 7 ops | `nuke_islamic_authors`, `_groups`, `_series`, `_khotab` | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.addauthor`/`editauthor`/`delauthor` excluded |
| G-04 | group page | group CP | 7 ops incl. `cat_fastcopy`, `KhotabReArrange` | `nuke_islamic_groups`, `khotab_category_index` | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.addgroup`/`editgroup`/`delgroup` excluded |
| G-06 | series page | series CP | 6 ops | `nuke_islamic_series` | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.addseries`/`editseries`/`delseries` excluded |
| G-05, G-13 | item page | category add/remove + editable category tree | `cat_add_to_item`, `Tree(…,'1')` | `khotab_category_index` | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `BUSINESS_CONFIRMATION` | `khotab.addcat` excluded |
| G-07, G-08, G-10, G-12, G-21 | listings, item page, download | **admin sees hidden content** | — (SQL branch) | read-only visibility | NEVER_IMPLEMENTED | **deliberately not reproduced** | `RETIRED_CONFIRMED` (documented decision) | `NO_CONTEXTUAL_SHORTCUT_NEEDED` | `KhotabSearchController:14` — *"legacy's admin branch, which drops the `hidden='0'` … filter, is not reproduced"* |
| G-09 | series row | detach series from group | `KhotabUpSeries` | `nuke_islamic_series.group_id` UPDATE | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `NO_CONTEXTUAL_SHORTCUT_NEEDED` | `khotab.moveitem` family excluded |
| G-11 | item row | detach item from series (AJAX) | `KhotabUpKhotab` | `nuke_islamic_khotab.ser_id` UPDATE | NEVER_IMPLEMENTED | none | `RETIRED_CONFIRMED` (#6) | `NO_CONTEXTUAL_SHORTCUT_NEEDED` | ditto |
| G-14, G-18 | item page, mirror row | mirror edit / delete / move (cookie-staged) | `KhotabEditMirror`, `KhotabDeleteMirror`, `KhotabMoveMirror` | `nuke_islamic_mirror` | NEVER_IMPLEMENTED | read + recheck/fix-size only | `PARTIALLY_MIGRATED` → see §B | `CONTEXTUAL_SHORTCUT_RECOMMENDED` | `LinkQualityStatsController:119-154` |
| G-16 | item page | `AdvancedStats()` per-item diagnostics | — (read only) | `nuke_islamic_khotab`, `_advanced`, `nuke_brokenlinks` | NEVER_IMPLEMENTED | aggregate equivalents exist | `PARTIALLY_MIGRATED` → see §B | **`CONTEXTUAL_SHORTCUT_RECOMMENDED`** | `LinkQualityStatsController:42-169` |
| G-17 | advfile view | advfile credit + edit/delete | `AdvfilesEditAdvFile`, `AdvfilesDeleteAdvFile` | `nuke_islamic_advfile` | NEVER_IMPLEMENTED | none found | `NEEDS_BUSINESS_CONFIRMATION` | `BUSINESS_CONFIRMATION` | different module (`advfiles`); Pass 2B |
| G-19, G-20 | comments | moderation header + per-comment edit | `KhotabWaitingList` | `nuke_islamic_comments` | NEVER_IMPLEMENTED | none | `NEEDS_BUSINESS_CONFIRMATION` | `CONTEXTUAL_SHORTCUT_RECOMMENDED` | `khotab.approvecomment`/`delcomment` excluded from seeder |
| G-03 | author page | — | — | — | — | — | `RETIRED_CONFIRMED` (dead code) | n/a | guard commented out at `:260` |
| — | central | link repair / lost links | `KhotabRepair`, `KhotabLostedLink` | `nuke_islamic_khotab`, `_mirror` | NEVER_IMPLEMENTED | **`LinkQualityStatsController`** | `ALREADY_MIGRATED` | `NO_CONTEXTUAL_SHORTCUT_NEEDED` | `routes/admin.php:157-178`, permission `khotab.repair` |

---

## A. Already migrated — DO NOT REIMPLEMENT

**Link repair / quality / lost-link maintenance.** Old `KhotabRepair`, `KhotabLostedLink` (and the mirror-side equivalents) are behaviorally owned, and exceeded, by Laravel:

- `routes/admin.php:157-178` — `admincp/link-quality/{mirror,khotab,telawah}`, each gated `admin.permission:khotab.repair` / `telawah.repair`
- `app/Domain/Admin/Http/Controllers/LinkQualityStatsController.php` — `mirror()`, `khotab()`, `telawah()`, `khotabLargeFiles()`, `recompute{Mirror,Khotab,Telawah}()`, `fixSize{Mirror,Khotab,Telawah}()`, `recheck{Mirror,Khotab,Telawah}()`
- `RecomputeLinkQualityPercentAction`
- Laravel adds per-row recheck and fix-size, which the old op did not have

**Admin-sees-hidden-content visibility** (G-07, G-08, G-10, G-12, G-21) is a **documented deliberate non-reproduction**, not a gap: `KhotabSearchController:14` records *"Public-only, same scope decision as every other khotab controller (legacy's admin branch, which drops the `hidden='0' AND count>'0'` filter, is not reproduced)."*

> **Evidence caveat, stated rather than smoothed over.** That sentence asserts the decision applies to *"every other khotab controller,"* but it is a docblock claim in one controller, not an entry in `decision-log.md`. I verified the phrase exists in exactly one file. Whether every khotab controller actually implements it consistently is unverified, and confirming it is a per-controller check I did not perform in this pass.

**Uploader tracking** — `UploaderController` (`index`, `recompute`, `backfillVbulletinIdentity`) with `khotab.uploaders`. Note this is *not* equivalent to the old `KhotabListDown`; see §C.

---

## B. Partial migrations — exact deltas only

### B-1 Mirror administration (G-14, G-18)

| | |
|---|---|
| Old behavior present | Per-mirror **edit** (`KhotabEditMirror`), **delete** (`KhotabDeleteMirror`), and **move to another item** via a two-step cookie-staged flow (`khotab-MirrorMove-{id}.htm` sets `$_COOKIE['MirrorMove']`; the target item page then offers `KhotabMoveMirror&id=…&khid=…`) |
| Laravel behavior present | Read/list, `recheckMirror()`, `fixSizeMirror()`, `recomputeMirror()` — `LinkQualityStatsController:98-154` |
| **Missing delta** | mirror **create**, **edit**, **delete**, and **re-parent to another khotab item**. The cookie-staged cross-page move has no analogue at all. |

### B-2 Per-item media/backup diagnostics (G-16, `AdvancedStats()`)

| | |
|---|---|
| Old behavior present | A single item's download state + trial count, link-health verdict (`online == linksize`), last check time, size on disk, CD-copy status, broken-link report count from `nuke_brokenlinks`, and the full `nuke_islamic_advanced` metadata row — all rendered inline on the item page |
| Laravel behavior present | The same underlying data, surfaced as **aggregate admin lists** with recompute/recheck/fix-size per row |
| **Missing delta** | the **per-item consolidated view reached from the item itself**. Every datum exists in Laravel; the single-item diagnostic panel does not. This is a presentation/workflow delta, not a data gap — which is why it belongs in §D rather than §C. |

---

## C. True Laravel gaps

Only one item has no behavioral equivalent anywhere and is not covered by Confirmation #6:

**C-1 `KhotabListDown`.** Appears in `admincp.htm` (G-01) but is **absent from `modules/Khotab/admin/index.php`'s ~95 `case` labels** — so the link is very likely already dead in old-project. Laravel's `UploaderController` is about uploader identity and stats, not download lists, so it is not a counterpart. Classified `NOT_MIGRATED` with low confidence and low value; verifying whether the handler exists elsewhere is Pass 2B work.

Everything else that Laravel lacks falls into one of two other buckets, and conflating them would be the main analytical error available here:

- **Covered by Business Confirmation #6** — the entire content-CRUD family. Not gaps; decided.
- **`NEEDS_BUSINESS_CONFIRMATION`** — the workflow queues in §E, whose business status cannot be read from source.

---

## D. Contextual shortcut candidates

Capabilities whose *behavior* is or would be owned centrally, but where the old control existed because the administrator was already looking at the content. Not missing business behavior.

| Capability | Recommendation | Why |
|---|---|---|
| **Facebook share queue (P-18)** | `CONTEXTUAL_SHORTCUT_REQUIRED_FOR_WORKFLOW` | It passes `&link=curPageURL()`. The canonical URL of the page being viewed is genuinely unavailable to a central screen. This is the only control in the entire panel with a real context dependency — subject to §E confirming the workflow still runs. |
| **Per-item diagnostics (B-2)** | `CONTEXTUAL_SHORTCUT_RECOMMENDED` | All data already in Laravel; an unobtrusive "diagnostics" affordance on the item page would restore the workflow without new business behavior or new tables. |
| **Mirror edit/delete/move (B-1)** | `CONTEXTUAL_SHORTCUT_RECOMMENDED` | Mirrors are intrinsically per-item; managing them from the item is materially better than from a flat list. Depends on B-1's delta being built, which depends on #6. |
| **Comment moderation (G-01, G-19, G-20)** | `CONTEXTUAL_SHORTCUT_RECOMMENDED` | Moderating a comment while reading the thread is the natural context. Subject to §E. |
| Item edit / move / category attach / banner / transcripts (P-01…P-14) | `BUSINESS_CONFIRMATION` | Cannot recommend a shortcut to behavior whose owning system is undecided. If #6's system is authoritative, Laravel should offer no shortcut at all. |
| Delete operations (P-03, P-08, P-11, P-14), detach (G-09, G-11) | `NO_CONTEXTUAL_SHORTCUT_NEEDED` | Destructive actions do not belong on a public page regardless of who owns them. |
| Hidden-content visibility (G-07/08/10/12/21) | `NO_CONTEXTUAL_SHORTCUT_NEEDED` | Deliberately not reproduced. |

---

## E. Retired / confirmation-needed

**`RETIRED_CONFIRMED` by Business Confirmation #6** — the whole content-CRUD family: item insert/edit/delete/move/copy; author CRUD; series CRUD; group CRUD; mirror CRUD (the mutating half); category attach/detach and the editable tree; banner upload/edit/delete; PDF and Word transcript upload/edit/delete; rearrange/join; `cat_fastcopy`; `KhotabCount`; `KhotabLists`. Corroborated by the ~30 deliberately-excluded `khotab.*` seeder keys (`decision-log.md:101`, `:271`, `:521`).

**`RETIRED_CONFIRMED` by documented decision** — admin-sees-hidden-content (with the caveat in §A).

**`RETIRED_CONFIRMED` as dead code** — G-03, commented out at `:260`.

**`NEEDS_BUSINESS_CONFIRMATION`** — four workflow queues whose technical behavior is understood but whose current business status is not determinable from source:

1. **Homepage-feature request** (P-16, `KhotabRequestAdvFile` → `nuke_islamic_advfile`) — looks like a queue feeding an editorial decision.
2. **Banner request** (P-17, `KhotabRequestBanner`) — a queue feeding a design process.
3. **Facebook share queue** (P-18, `KhotabWaitingFB` → `KhotabWaitingList` → `KhotabConfirmAddToShare`) — a three-step publishing workflow; whether downstream posting still runs is a business fact.
4. **Comment moderation** (G-01/G-19/G-20, `nuke_islamic_comments.view`) — ambiguous against #6: comments are arguably content, and `khotab.approvecomment`/`delcomment` are among the excluded keys, but #6's wording is about "content-CRUD" and comment *moderation* is a distinguishable capability. **I am not resolving this by assumption.**

Also `NEEDS_BUSINESS_CONFIRMATION`: **GIF/JPG preview generation** (P-19, `get_gif_jpg.php`) and **advfile credit management** (G-17, a different module — Pass 2B).

---

## F. Security findings specific to Khotab inline administration

Behavior to preserve; implementation to discard. Additive to Pass 1 §13.

| # | Finding | Evidence |
|---|---|---|
| K-1 | **Every mutation is a `GET` with no CSRF token** — delete item, delete banner, delete transcript, delete mirror, detach series/item. A prefetching browser or an `<img>` tag on any page can trigger them for a logged-in admin. | `AdminPanel()` output, `:784-889`; G-09, G-11, G-18 |
| K-2 | **The two-step confirmation is bypassable.** `…Delete…` → `…ConfirmDelete…` pairs render a confirmation screen, but the confirm step is itself a tokenless `GET`, so linking straight to it skips the screen. | Khotab op list, Pass 1 §5.2 |
| K-3 | **No server-side authorization on any of it.** `GreenZone()` returns immediately (Pass 1 §11), so `is_w2a_admin()` — satisfiable with a forged unsigned cookie (Pass 1 S-1/S-2) — is the only barrier between an anonymous request and `DELETE FROM nuke_islamic_khotab`. | `mainfile.php:1855`, `:390-420` |
| K-4 | **Mutation state carried in a client cookie.** The mirror-move flow stages the source mirror in `$_COOKIE['MirrorMove']` and `unserialize()`s it on the next page view. `unserialize()` on attacker-controlled input, with no `allowed_classes` restriction — object-injection exposure, and the staged id is freely editable. | `:1007-1012` |
| K-5 | **`eval(IncMe(...))` for every admin template.** All four control panels and the comment header are rendered by `eval()` of file contents. Any write access to `themes/way2allah/templates/Khotab/*.htm` is remote code execution. | G-01, G-02, G-04, G-06, G-19 |
| K-6 | **Predictable direct file path exposure.** P-15 links `media/docx/{floor(id/1000)}/{id}.docx` with no handler, so transcript files are enumerable without any authorization check if the directory is web-served. | `:838-845` |
| K-7 | **AJAX mutation via plain GET from inline JS**, hardcoded to `https://way2allah.com/admin.php?op=KhotabUpKhotab&id=` — a cross-origin-capable state change. | `:690-697` |

None is a reason to skip the *behavior*; all are reasons the implementation must not be ported.

---

## Final answers

### 1. If old-project were shut down tomorrow, what Khotab administration would actually disappear?

Technically: **the entire Khotab content-administration surface** — item create/edit/delete/move/copy, author/series/group CRUD, mirror create/edit/delete/move, category attachment, banner management, PDF and Word transcript management, comment moderation, rearrange/join, the four workflow queues, per-item diagnostics, and admin visibility of hidden content. Neither Generation 2 nor Laravel implements any of it.

**But "disappear" presumes old-project is where this work happens, and Business Confirmation #6 says it happens in another system.** If #6 is accurate and that system is not old-project, shutting old-project down costs nothing operationally. If old-project *is* the #6 system, shutting it down removes all Khotab content administration from the organisation. **Which of those is true is not determinable from any source I can read.** That is the question, not the answer.

### 2. Genuine business-function losses versus lost convenience?

**Genuine function, if not owned elsewhere:** content CRUD across item/author/series/group/mirror, transcript management, banner management, category attachment, comment moderation, and the four workflow queues.

**Convenience only:** every `AdminPanel` link from P-01 to P-14 — they are id-parameterised links to central handlers, so context saves clicks, not capability. Admin-sees-hidden is already a decided non-reproduction. Link repair is already migrated and improved.

**Genuine function that is specifically contextual:** exactly one — P-18's `&link=curPageURL()`.

### 3. Which old controls must NOT be migrated because Laravel already owns the behavior?

- `KhotabRepair`, `KhotabLostedLink` and the mirror/telawah equivalents → `LinkQualityStatsController` + `khotab.repair` / `telawah.repair`, which also adds per-row recheck and fix-size
- Mirror **read/health** paths (G-18's listing half) → same controller
- Admin-sees-hidden-content → deliberately not reproduced, with the §A caveat
- Uploader listing/tracking → `UploaderController` + `khotab.uploaders`

### 4. Which contextual shortcuts would be worth restoring without recreating the toolbar?

In priority order, all conditional on §E/§0: the **Facebook-share action** (only genuine context dependency), a **per-item diagnostics affordance** (all data already present in Laravel), **mirror management from the item page**, and **comment moderation from the thread**. Nothing else earns a public-page control.

### 5. The smallest genuine Khotab migration gap suitable for a first slice

**There isn't one yet, and recommending a slice now would be wrong.**

Everything substantial is `RETIRED_CONFIRMED` under Business Confirmation #6. The two `PARTIALLY_MIGRATED` deltas (B-1 mirror mutations, B-2 per-item diagnostics) both sit inside the family #6 retired. The four `NEEDS_BUSINESS_CONFIRMATION` queues are blocked on business facts. The single `NOT_MIGRATED` item (C-1 `KhotabListDown`) is probably already dead in old-project.

**The correct next step is to resolve Confirmation #6's one open detail: which system actually administers Khotab content today, and whether it is `/mnt/Projects/Php/pure/old-project`.** Until that is answered, any implementation risks either duplicating a system that already owns these writes, or building against a decision that was made on a misidentified premise.

If the answer comes back that old-project *is* the system and should be replaced, the smallest safe first slice would then be **B-2, the per-item diagnostics view** — read-only, no new tables, every datum already reachable through existing Laravel models and the `khotab.repair` permission, zero mutation risk, and directly useful. That is a conditional recommendation, not a proposal.
