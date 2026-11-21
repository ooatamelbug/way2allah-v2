# OLD AdminCP — complete functional parity audit

> **Status (2026-11-17, decision-log #155): PAUSED BY OWNER — RESUME AFTER UPCOMING DEPLOYMENT.** This checklist
> stands unchanged. No item is retired or completed by the pause. Resume plan:
> [`admincp-post-deployment-resume-checkpoint.md`](admincp-post-deployment-resume-checkpoint.md).

**Date:** 2026-11-17. **Read-only audit.** No application code, OLD / Gen 2 file, database, configuration, commit or deployment was changed. Local only — nothing here is production-verified.

## 0. Method and provenance

- **Screenshots.** The two owner screenshots were **not available** in the workspace or this session (the only image in the repository is unrelated). Both dashboards are therefore reconstructed **exactly as OLD renders them**, from the code that draws them: `old-project/admin.php` `GraphicAdmin()` (lines 99–195).
  - **Screenshot 1 — «التحكم في البرامج المستقلة» (`_MODULESADMIN`):** every `nuke_modules` row (`ORDER BY title`) whose module has `admin/index.php`, `admin/links.php` and `admin/case.php`; each module's `links.php` adds its `adminmenu()` entries (Montada adds two). Shown to super-admins and to admins listed in the module's `admins` column. Inactive modules are listed too (no `active` filter). `modules/Cat - Copy/` has admin files but no `nuke_modules` row, so it never renders.
  - **Screenshot 2 — «قائمة التحكم» (`_ADMINMENU`):** super-admins only; one entry per `admin/links/links.*.php` (sorted), plus «خروج».
  - Item order and numbering below follow that rendering. **The owner should cross-check this list against the two images**; production `nuke_modules` could differ from the 2025 local copy (e.g. a row added or removed), which would add or remove an item.
- **OLD operations.** Every `case "<op>"` label in each module's `admin/case.php` (and `admin/case/case.*.php` for the system panel) — **725 labels**, 597 in the 39 modules and 128 in the system panel — with the handler each label dispatches to in `admin/index.php`. Labels whose handler is undefined, which have no dispatch, or which earlier owner-closed work proved dead are listed in the appendix and **not counted as OLD functions** (the brief: "only evaluate functions that actually exist").
- **Laravel.** Current routes (`php artisan route:list`), controllers and the existing evidence base. Classification follows the brief: **VERIFIED** only where a real-route, real-database or owner manual functional review is on record; owner closure on tests / code review alone is **IMPLEMENTED — NOT VERIFIED**; earlier technical exclusions (DEAD by data, "output not consumed", PLAN §2 "DO_NOT_MIGRATE") are **UNKNOWN**, not RETIRED, unless the owner explicitly retired the functionality. Visitor-URL retirements (DL #113–#132) retire the public feature; they are cited, but they do **not** retire the admin side unless they say so.
- **Usage evidence** (local 2025 copy, read-only row counts / latest dates) is quoted where it informs priority.
- **Evidence keys:** PLAN = `docs/reviews/old-admin-master-execution-plan.md`; DL = `docs/decision-log.md`; ADM = `docs/reports/admincp-administrator-management-results.md`; B6a / B6b = `docs/reports/admincp-b6{a,b}-implementation-results.md`.

## A. Complete screenshot inventory

### Screenshot 1 — «التحكم في البرامج المستقلة» (independent programs)

| # | OLD displayed label | OLD module / route | Laravel equivalent | Overall status | Evidence |
|---|---|---|---|---|---|
| 1.1 | الإعلانات | `ads` — `admin.php?op=w2a_ads_main` | Ads (`admin.ads.*`) | IMPLEMENTED — NOT VERIFIED | B6a report |
| 1.2 | ملفات الرئيسية | `advfiles` — `admin.php?op=AdvfilesMain` | Advfiles (`admin.advfiles.*`) | VERIFIED | PLAN §24.9 |
| 1.3 | الأناشيد و المنوعات | `Anasheed` — `admin.php?op=AnasheedMain` | Anasheed admin + mirrors + categories | PARTIAL | PLAN §32–§38; DL #61–#67 |
| 1.4 | النسخ الإحتياطي | `BackUp` — `admin.php?op=BackUpMain` | Backup API / link quality (partial) | PARTIAL | this audit (§B) |
| 1.5 | إعلانات بنرات المواد | `banners_ads` — `admin.php?op=w2a_banners_ads_main` | Banner ads (`admin.banners_ads.*`) | IMPLEMENTED — NOT VERIFIED | B6b report |
| 1.6 | التصنيفات | `Cat` — `admin.php?op=w2a_cat_main` | Categories (assignment only) | PARTIAL | PLAN §21.7 |
| 1.7 | قسم الاسطوانات | `cd` — `admin.php?op=w2a_cds_main` | — | MISSING | this audit (§B) |
| 1.8 | ريستارت غرفة الهداية | `chat_server` — `admin.php?op=w2a_reboot_server` | — (retired) | RETIRED — OWNER APPROVED | DL #14, #39 |
| 1.9 | تصنيف المنوعات | `Classificate_anasheed` — `admin.php?op=w2a_classificate_anasheed_main` | Categories (Anasheed, item level)? | UNKNOWN | this audit (§B) |
| 1.10 | الأسئلة المتكررة | `contact` — `admin.php?op=FaqAdmin` | — | MISSING | DL #120 |
| 1.11 | البوم الصور المعروض | `default_picasa_album` — `admin.php?op=w2a_default_picasa_album_main` | — | MISSING | DL #128 |
| 1.12 | ملفات الوورد | `docx` — `admin.php?op=w2a_docx_main` | Khotab item DOCX (per item) | PARTIAL | this audit (§B) |
| 1.13 | ملفات التفريغ | `Dump_files` — `admin.php?op=w2a_dump_files_main` | Khotab item PDF (per item) | PARTIAL | this audit (§B) |
| 1.14 | الأسئلة المتكررة | `FAQ` — `admin.php?op=FaqAdmin` | — | MISSING | DL #120 |
| 1.15 | الفتـــاوي | `Fatwa` — `admin.php?op=w2a_fatwa_main&type=add` | Fatwa admin (contextual) | PARTIAL | DL #69 |
| 1.16 | قائمة الشير | `fb_share_list` — `admin.php?op=w2a_fb_share_list_main` | — | MISSING | this audit (§B) |
| 1.17 | راسلنا | `Feedback` — `admin.php?op=w2a_contact_us_main` | — (partly retired) | MISSING (part retired) | DL #108 |
| 1.18 | حملات الرئيسية | `flashxml` — `admin.php?op=w2a_7amalat` | — | MISSING | this audit (§B) |
| 1.19 | جدول الغرفة الصوتية | `hedaya_table` — `admin.php?op=w2a_hedaya_list_main` | — (retired) | RETIRED — OWNER APPROVED | DL #122 |
| 1.20 | شرح الموقع | `help` — `admin.php?op=w2a_help` | — | MISSING | DL #121 |
| 1.21 | رفع تقييم الفيديو | `high_rank` — `admin.php?op=High_rank_main` | — | MISSING | this audit (§B) |
| 1.22 | ملفات الرئيسية الجديد | `home_files` — `admin.php?op=w2a_home_files_main` | — | MISSING | DL #123 |
| 1.23 | الخطب و الدروس | `Khotab` — `admin.php?op=KhotabMain` | Khotab admin + mirrors + link quality + categories | PARTIAL | PLAN §31; DL #60; ADM §12 |
| 1.24 | البث المباشر | `live` — `admin.php?op=w2a_live_main` | Broadcasting (stream codes) | PARTIAL | DL #129 |
| 1.25 | مكتبة البرامج | `Mobile` — `admin.php?op=downloads` | Mobile (`admin.mobile.*`) | VERIFIED | PLAN §26.7, §26.13 |
| 1.26 | بنرات المنتدى | `Montada` — `admin.php?op=w2a_montada` | — | MISSING | this audit (§B) |
| 1.27 | رابط رمضان | `Montada` — `admin.php?op=w2a_montada&div=ramadan` | — | UNKNOWN | this audit (§B) |
| 1.28 | الالبوم الدعوي | `new_album` — `admin.php?op=new_album` | — | MISSING | this audit (§B) |
| 1.29 | إستطلاعات الرأي | `questionnaire` — `admin.php?op=w2a_questionnaire_main` | — (Laravel questionnaire reads another table) | MISSING | DL #125 |
| 1.30 | الإختبارات | `Quiz` — `admin.php?op=QuizMain` | — | MISSING | this audit (§B) |
| 1.31 | مسابقة حفظ القرآن الكريم | `ramadan_competition` — `admin.php?op=w2a_ramadan_competition_main` | — | MISSING | this audit (§B) |
| 1.32 | الإبلاغ عن اعلانات غير لائقة | `report_ads` — `admin.php?op=w2a_bad_ads_main` | Ad reports (`admin.report_ads.*`) | IMPLEMENTED — NOT VERIFIED | B6b report |
| 1.33 | الأقمار الصناعية | `Satellite` — `admin.php?op=w2a_sat_main` | Broadcasting (stream code) + item channel field | PARTIAL | this audit (§B) |
| 1.34 | الإستفتاء / التصويت | `Surveys` — `admin.php?op=create` | Polls (`admin.polls.*`) | VERIFIED | PLAN §23.9, §30.2 |
| 1.35 | التلاوات | `Telawah` — `admin.php?op=TelawahMain` | Telawah admin + mirrors + link quality | PARTIAL | PLAN §39; DL #68 |
| 1.36 | الدورات التدريبية | `Tutorials` — `admin.php?op=TutorialsMain` | — | MISSING | this audit (§B) |
| 1.37 | تعديل الخطب | `update_khotab_count` — `admin.php?op=w2a_update_khotab_count_main` | Khotab counter repair? | UNKNOWN | this audit (§B) |
| 1.38 | اسطوانات أرشيف | `w2a_cd` — `admin.php?op=w2a_cd` | Anasheed admin (same tables) | PARTIAL | PLAN §25.3; DL #82 |
| 1.39 | الاسطوانات الدعوية | `w2acd` — `admin.php?op=W2ACDMain` | CDs (`admin.cds.*`) | PARTIAL | PLAN §25, §44–§49; DL #74–#78 |
| 1.40 | الأعضاء | `Your_Account` — `admin.php?op=mod_users` | — | MISSING | DL #126 |
| 1.41 | خروج | forum logout link | Laravel AdminCP logout | VERIFIED | `admin.logout` (ADM §4) |

### Screenshot 2 — «قائمة التحكم» (main administration)

| # | OLD displayed label | OLD module / route | Laravel equivalent | Overall status | Evidence |
|---|---|---|---|---|---|
| 2.1 | AutoTheme | `admin/links.autotheme.php` — `admin.php?module=AutoTheme&op=main` | — | MISSING | this audit (§B) |
| 2.2 | نسخة إحتياطية | `admin/links.backup.php` — `admin.php?op=backup` | — | MISSING | this audit (§B) |
| 2.3 | المجموعات (Blocks) | `admin/links.blocks.php` — `admin.php?op=BlocksAdmin` | — | MISSING | this audit (§B) |
| 2.4 | تحرير المشرفين | `admin/links.editadmins.php` — `admin.php?op=mod_authors` | Staff management (`admin.staff.*`, `admin.permissions.*`) | VERIFIED | ADM §1–§12 |
| 2.5 | مجموعات المستخدمين | `admin/links.groups.php` — `admin.php?op=Groups` | — | MISSING | this audit (§B) |
| 2.6 | مرجعية العناوين | `admin/links.httpreferers.php` — `admin.php?op=hreferer` | — | MISSING | this audit (§B) |
| 2.7 | حجب ايبي | `admin/links.ipban.php` — `admin.php?op=ipban` | — | MISSING | this audit (§B) |
| 2.8 | الرسائل | `admin/links.messages.php` — `admin.php?op=messages` | — | MISSING | this audit (§B) |
| 2.9 | Moderation | `admin/links.moderation.php` — `admin.php?op=moderation` | — | UNKNOWN | this audit (§B) |
| 2.10 | البرامج المستقلة | `admin/links.modules.php` — `admin.php?op=modules` | — | MISSING | this audit (§B) |
| 2.11 | قائمة المراسلة | `admin/links.newsletter.php` — `admin.php?op=newsletter` | — | MISSING | this audit (§B) |
| 2.12 | nukeSEO | `admin/links.nukeSEO.php` — `admin.php?op=nukeSEO` | — | MISSING | this audit (§B) |
| 2.13 | Optimize DB | `admin/links.optimize.php` — `admin.php?op=optimize` | — | MISSING | this audit (§B) |
| 2.14 | إعدادات الموقع | `admin/links.settings.php` — `admin.php?op=Configure` | — | MISSING | this audit (§B) |
| 2.15 | مواضيع | `admin/links.submissions.php` — `admin.php?op=submissions` | — | NOT AN OLD FUNCTION (dead link) | no `admin/case` file handles `op=submissions`; news stories last written 2008 |
| 2.16 | خروج | forum logout link | Laravel AdminCP logout | VERIFIED | `admin.logout` |

**Related items:** `FAQ` and `contact` render the same label «الأسئلة المتكررة» and run the same `FaqAdmin` code (contact is a copy) — one capability. `w2a_cd` («اسطوانات أرشيف») administers the Anasheed tables (PLAN §25.3), not W2ACD; its operations map onto the Anasheed implementation. `w2acd` («الاسطوانات الدعوية») is the CD module Laravel implements. `banners_ads`' create / edit / delete / reports labels have no handlers in that module (only its main screen works). `Montada` contributes two items. `links.submissions.php` is a dead link in OLD (no handler).

## B. Detailed functional matrix

One row per OLD operation (a display op and its `Confirm…` / save op form one operation). All labels of the row are listed.

| OLD module | OLD operation (case labels) | Laravel module | Laravel operation | Status | Exact functional difference | Evidence |
|---|---|---|---|---|---|---|
| Khotab | `KhotabMain` | Khotab (Main pages) | Main video/audio author directories with «لوحة التحكم»; pending-comment count | **VERIFIED** | Laravel shows the admin panel on the public Main pages instead of a separate dashboard landing | PLAN §31.2(5); DL #60 |
| Khotab | `KhotabInsertAuthor`, `KhotabConfirmInsertAuthor`, `KhotabUploadAuthorImage` | Khotab | admin.khotab.main.authors.create/store (with image) | **VERIFIED** | OLD fatal after insert (`sql_nextid()`) not reproduced | PLAN §31.2(5), §31.3; DL #60 |
| Khotab | `KhotabEditAuthor`, `KhotabConfirmEditAuthor` | Khotab | admin.khotab.authors.edit/update (+ `authors.txt` refresh) | **VERIFIED** | — | PLAN §31.2(4); DL #60 |
| Khotab | `KhotabDeleteAuthor`, `KhotabConfirmDeleteAuthor` | Khotab | admin.khotab.authors.delete/destroy | **VERIFIED** | Refused while any item exists (approved safety correction) | PLAN §31.2(4) |
| Khotab | `KhotabAddGroup`, `KhotabConfirmAddGroup` | Khotab | admin.khotab.authors.groups.create/store | **VERIFIED** | Now also restricted by audio / video section (2026-11-17) | PLAN §31.2(4); ADM §12 |
| Khotab | `KhotabEditGroup`, `KhotabConfirmEditGroup` | Khotab | admin.khotab.groups.edit/update | **VERIFIED** | Section-scoped (ADM §12) | PLAN §31.2(1); ADM §12 |
| Khotab | `KhotabDeleteGroup`, `KhotabConfirmDeleteGroup` | Khotab | admin.khotab.groups.delete/destroy | **VERIFIED** | Section-scoped | PLAN §31.2(1); ADM §12 |
| Khotab | `GroupLists` | Khotab | group lists in the group / author pages and move-options | **VERIFIED** | No separate listing screen | PLAN §31.2(1) |
| Khotab | `KhotabInsertSeries`, `KhotabConfirmInsertSeries` | Khotab | admin.khotab.groups.series.* / authors.series.* | **VERIFIED** | Section-scoped | PLAN §31.2(1),(4); ADM §12 |
| Khotab | `KhotabEditSeries`, `KhotabConfirmEditSeries` | Khotab | admin.khotab.series.edit/update | **VERIFIED** | Section-scoped | PLAN §31.2(2) |
| Khotab | `KhotabDeleteSeries`, `KhotabConfirmDeleteSeries` | Khotab | admin.khotab.series.delete/destroy | **VERIFIED** | Section-scoped | PLAN §31.2(2) |
| Khotab | `KhotabUpSeries` | Khotab | admin.khotab.series.detach (DetachKhotabSeriesAction) | **VERIFIED** | Gen 2 copy of the link is dead (PLAN §2 row 19); OLD handler preserved | DL #72, #80; PLAN §31.2(1) |
| Khotab | `KhotabInsertKhotab`, `KhotabConfirmInsertKhotab` | Khotab | admin.khotab.{groups,series,authors}.items.create/store (+ archive.org step) | **VERIFIED** | Section-scoped | PLAN §31.2(1)-(4); ADM §12 |
| Khotab | `KhotabEditKhotab`, `KhotabConfirmEditKhotab` | Khotab | admin.khotab.items.edit/update | **VERIFIED** | Link change after download is super-admin only (F09) | PLAN §31.2(3); DL #150 |
| Khotab | `KhotabMoveKhotab`, `KhotabUpKhotab`, `KhotabLists` | Khotab | admin.khotab.items.move/move.save, items.detach, move-options.groups/series | **VERIFIED** | Dependent selects replace OLD AJAX lists | PLAN §31.2(3) |
| Khotab | `KhotabDeleteKhotab`, `KhotabConfirmDeleteKhotab` | Khotab | admin.khotab.items.delete/destroy | **VERIFIED** | Section-scoped | PLAN §31.2(3); ADM §12 |
| Khotab | `KhotabLeechImages` | Khotab | admin.khotab.items.images («سحب الصور», safe POST) | **VERIFIED** | Broken OLD GET flow replaced by a safe archive.org POST | PLAN §31.2(3), §31.3 |
| Khotab | `KhotabDetailes` | Khotab | item page «لوحة تحكم المشرفين» incl. «إحصائيات متطورة» | **VERIFIED** | — | PLAN §31.2(3); PLAN §28.3 K8 |
| Khotab | `KhotabReArrange`, `KhotabConfirmReArrange`, `KhotabOrder` | Khotab | admin.khotab.{groups,series}.rearrange/rearrange.save | **VERIFIED** | One rearrange form replaces OLD per-pair weight swaps | PLAN §31.2(1),(2) |
| Khotab | `KhotabJoin`, `KhotabConfirmJoin` | — | — (per-item move only) | **MISSING** | OLD bulk re-parent (`JoinMe`) has no Laravel equivalent; carried as legacy residual risk, never owner-retired | PLAN §2 row 24, §7 DoD 5a |
| Khotab | `KhotabInsertMirror`, `KhotabConfirmInsertMirror`, `KhotabEditMirror`, `KhotabConfirmEditMirror`, `KhotabDeleteMirror`, `KhotabConfirmDeleteMirror`, `KhotabMoveMirror` | Mirrors | admin.mirrors.khotab.* (store/edit/update/destroy/pick/move/clipboard.clear) | **VERIFIED** | Edit / delete section-scoped; move uses a server-side clipboard | PLAN §13, §15.8, §28.3 K1–K4; ADM §12 |
| Khotab | `MirrorExport`, `KhotabExport`, `KhotabExportMirror`, `KhotabExportAnasheed`, `KhotabExportTelawah` | — | — | **UNKNOWN** | Exports of backed-up link lists for the external backup tool; MirrorExport judged unused (PLAN §2 row 23, not an owner retirement); KhotabExport* never dispositioned | PLAN §2 row 23; docs/reviews/old-admin-scope-reset.md §2 K2 |
| Khotab | `KhotabRequestAdvFile` | Advfiles | admin.advfiles.request (any admin) | **VERIFIED** | — | PLAN §24.9, §28.3 K7 |
| Khotab | `KhotabRepair` | Link quality | admin.link-quality.khotab.* (recheck, fix-size, recompute) | **VERIFIED** | Laravel adds per-row recheck / fix-size | docs/reviews/old-admin-scope-reset.md §2 K3; PLAN §15.8 |
| Khotab | `KhotabLostedLink` | Link quality | admin.link-quality.khotab.lost (lost-link worklist) | **VERIFIED** | OLD repair form had no handler (dead); the worklist is Laravel's equivalent | PLAN §2 rows 2b, 2c; §15.8 |
| Khotab | `KhotabCount` | Khotab | admin.khotab.authors.repair-counts / main.repair-counts | **VERIFIED** | Deterministic set-wise repair | PLAN §31.2(4),(5), §31.3 |
| Khotab | `KhotabWaitingList`, `KhotabAddComment`, `KhotabDelComment`, `KhotabAddComments`, `نشر التعليـق`, `مسح التعليـق` | Khotab | admin.khotab.main.comments / comments.moderate, comments.publish/hide/edit | **VERIFIED** | Working pagination (OLD `{PAGE}` bug not reproduced) | PLAN §31.2(5), §31.3 |
| Khotab | `KhotabAddModComment` | Khotab | admin.khotab.items.comments.store («إضافة تعليق إداري») | **VERIFIED** | — | PLAN §31.2(3) |
| Khotab | `KhotabListModertors`, `KhotabConfirmEditMod` | Staff / permissions | admin.permissions.edit/update (+ khotab.media_* sections) | **VERIFIED** | OLD per-module moderator grants replaced by Laravel permissions; existing grants migrated by `admin:migrate-old-permissions` | ADM §1–§4, §11, §12 |
| Khotab | `KhotabModertorsLogs` | Audit | admin.audit.legacy (module Khotab) + admin.audit.index | **VERIFIED** | Read-only viewer | PLAN §15, §15.8 |
| Khotab | `KhotabClearModertorsLogs` | — | — | **MISSING** | Deleting legacy log history is not offered; retention is a policy decision | PLAN §15 |
| Khotab | `KhotabUploadBanner`, `KhotabDelete_banner`, `confirm_delete_banner` | Khotab | admin.khotab.items.banner / banner.upload / banner.delete/destroy | **VERIFIED** | — | PLAN §31.2(3) |
| Khotab | `KhotabRequestBanner` | Khotab | admin.khotab.items.banner.request | **VERIFIED** | — | PLAN §31.2(3) |
| Khotab | `KhotabUploadPdf`, `KhotabConfirmUploadPdf`, `Khotab_EditPdf`, `KhotabDeletePdf`, `confirm_delete_pdf` | Khotab | admin.khotab.items.pdf.* | **VERIFIED** | — | PLAN §31.2(3) |
| Khotab | `KhotabUploaddocx`, `KhotabConfirmUploaddocx`, `Khotab_Editdocx`, `KhotabDeletedocx`, `confirm_delete_docx` | Khotab | admin.khotab.items.docx.* | **VERIFIED** | — | PLAN §31.2(3) |
| Khotab | `KhotabAjaxGroupsSeries`, `KhotabAjaxSeries` | Khotab | admin.khotab.move-options.* | **VERIFIED** | — | PLAN §31.2(3) |
| Khotab | `KhotabWaitingFB`, `KhotabConfirmAddToShare` | — | — | **RETIRED — OWNER APPROVED** | Social share queue retired (owner 2026-10-15) | PLAN §28.1 |
| Khotab | `KhotabCatList`, `KhotabEditCat`, `تعديل التصنيف` | — | — | **MISSING** | Category title / notes edit (category CRUD) has no Laravel screen; see Cat | PLAN §17 (line 1489) |
| Anasheed | `AnasheedMain` | Anasheed | group / item pages with «لوحة تحكم» and the three «بدون …» worklists | **PARTIAL** | No AdminCP dashboard landing (OLD menu: totals, pending comments, lists); Main panel itself DEAD (PLAN §34) | PLAN §32–§38; DL #61–#67 |
| Anasheed | `AnasheedInsertAnasheed`, `AnasheedConfirmInsertAnasheed` | Anasheed | admin.anasheed.groups.items.create/store | **VERIFIED** | — | PLAN §32; DL #61 |
| Anasheed | `AnasheedEditAnasheed`, `AnasheedConfirmEditAnasheed` | Anasheed | admin.anasheed.items.edit/update | **VERIFIED** | — | PLAN §33; DL #62 |
| Anasheed | `AnasheedDeleteAnasheed`, `AnasheedConfirmDeleteAnasheed` | Anasheed | admin.anasheed.items.delete/destroy | **VERIFIED** | — | PLAN §33; DL #62 |
| Anasheed | `AnasheedAddGroup`, `AnasheedConfirmAddGroup` | Anasheed | admin.anasheed.groups.subgroups.create/store | **VERIFIED** | — | PLAN §32 |
| Anasheed | `AnasheedEditGroup`, `AnasheedConfirmEditGroup` | Anasheed | admin.anasheed.groups.edit/update | **VERIFIED** | — | PLAN §32 |
| Anasheed | `AnasheedDeleteGroup`, `AnasheedConfirmDeleteGroup` | Anasheed | admin.anasheed.groups.delete/destroy | **VERIFIED** | Also refuses while sub-groups exist | PLAN §32 |
| Anasheed | `AnasheedGroupIcon`, `AnasheedUploadIcon` | Anasheed | admin.anasheed.groups.icon/icon.store | **VERIFIED** | — | PLAN §32 |
| Anasheed | `AnasheedDetailes`, `AnasheedUploadBanner`, `AnasheedEditImage`, `AnasheedDeleteImage` | Anasheed | admin.anasheed.items.frame/frame.store (item image) | **VERIFIED** | Image delete folded into replace | DL #62 (T1) |
| Anasheed | `AnasheedAdd_banner`, `AnasheedEdit_banner`, `AnasheedDelete_banner`, `AnasheedConfirmDelete_banner` | — | — | **UNKNOWN** | Separate banner add / edit / delete ops; not individually dispositioned (the public panel dispositions cover the frame only) | DL #62; this audit |
| Anasheed | `AnasheedInsertMirror`, `AnasheedConfirmInsertMirror`, `AnasheedEditMirror`, `AnasheedConfirmEditMirror`, `AnasheedDeleteMirror`, `AnasheedConfirmDeleteMirror` | Mirrors | admin.mirrors.anasheed.* | **VERIFIED** | — | PLAN §13, §15.8, §28.3 A1–A3 |
| Anasheed | `AnasheedWaitingList`, `نشر التعليق للمادة`, `مسح التعليق للمادة`, `AnasheedAddComments` | Anasheed | admin.anasheed.comments.edit/publish/hide (per item) | **PARTIAL** | Per-item moderation verified; the site-wide pending-comments AdminCP queue was left out of scope (no owner retirement) | DL #62 |
| Anasheed | `AnasheedAddModComment` | Anasheed | admin.anasheed.items.comments.store | **VERIFIED** | — | DL #62 (K1) |
| Anasheed | `AnasheedListDown` | — | — | **MISSING** | Lost-backup report for Anasheed (`down = 0 OR trial > 5`); Laravel's lost-link worklist covers Khotab only | PLAN §15; this audit |
| Anasheed | `AnasheedCount` | — | — | **UNKNOWN** | Counter repair; DEAD by PLAN §2 row 14 (technical), not owner-retired | PLAN §2 row 14 |
| Anasheed | `AnasheedListModertors`, `AnasheedConfirmEditMod` | Staff / permissions | admin.permissions.edit/update | **VERIFIED** | Replaced by Laravel permissions; grants migrated | ADM §11, §12 |
| Anasheed | `AnasheedModertorsLogs` | Audit | admin.audit.legacy (module Anasheed) | **VERIFIED** | Read-only | PLAN §15.8 |
| Anasheed | `AnasheedWaitingFB`, `AnasheedConfirmAddToShare` | — | — | **RETIRED — OWNER APPROVED** | Share queue retired | PLAN §28.1 |
| Telawah | `TelawahMain` | Telawah | recite pages with «لوحة التحكم» | **PARTIAL** | No AdminCP landing/listing screen | PLAN §39; DL #68 |
| Telawah | `TelawahInsertTelawah`, `TelawahConfirmInsertTelawah` | Telawah | admin.telawah.groups.items.create/store (G3) | **VERIFIED** | — | PLAN §39; DL #68 |
| Telawah | `TelawahEditTelawah`, `TelawahConfirmEditTelawah`, `TelawahmoveTelawah`, `load_sub_categories` | Telawah | admin.telawah.items.edit/update, move/move.save, move-options.sub-groups | **VERIFIED** | — | PLAN §39 (I1, I3) |
| Telawah | `TelawahDeleteTelawah`, `TelawahConfirmDeleteTelawah` | Telawah | admin.telawah.items.delete/destroy | **VERIFIED** | Safe delete | PLAN §39 (I2) |
| Telawah | `TelawahAddGroup`, `TelawahConfirmAddGroup` | Telawah | admin.telawah.main.groups.create/store, groups.subgroups.* | **VERIFIED** | — | PLAN §39 (M1, G4) |
| Telawah | `TelawahEditGroup`, `TelawahConfirmEditGroup` | Telawah | admin.telawah.groups.edit/update | **VERIFIED** | Parent kept | PLAN §39 (G1) |
| Telawah | `TelawahDeleteGroup`, `TelawahConfirmDeleteGroup` | Telawah | admin.telawah.groups.delete/destroy | **VERIFIED** | Refuses non-empty | PLAN §39 (G5) |
| Telawah | `TelawahDetailes` | Telawah | item page panel + link-quality item page | **VERIFIED** | — | PLAN §39; §28.3 T1 |
| Telawah | `TelawahEditMirror`, `TelawahConfirmEditMirror`, `TelawahDeleteMirror`, `TelawahConfirmDeleteMirror` | Mirrors | admin.mirrors.telawah.* | **VERIFIED** | — | PLAN §29.1; §30.4 (14/14) |
| Telawah | `Telawahaddlink`, `TelawahConfirmInsertMirror` | — | — | **UNKNOWN** | OLD disabled insert itself («غير متاحة»); not an owner retirement | PLAN §29.1; DL #59 |
| Telawah | `TelawahCount` | — | — | **UNKNOWN** | Counter repair; DEAD by PLAN §2 row 14 (technical) | PLAN §2 row 14 |
| Telawah | `TelawahExport` | — | — | **UNKNOWN** | Backup export list; never dispositioned | this audit |
| Telawah | `TelawahOrder` | — | — | **UNKNOWN** | Weight ordering; never dispositioned for Telawah | this audit |
| Telawah | `TelawahWaitingList`, `نشر التعليق`, `مسح التعليق` | — | — | **UNKNOWN** | Comment queue; public «مسح التعليق» DEAD (PLAN §39 C1, 0 items with comments in production); AdminCP queue not dispositioned | PLAN §39; DL #68 |
| Telawah | `TelawahUploadBanner` | — | — | **MISSING** | Telawah banner upload has no Laravel operation | ADM §12 |
| Telawah | `TelawahListModertors`, `TelawahConfirmEditMod` | Staff / permissions | admin.permissions.edit/update | **VERIFIED** | Grants migrated | ADM §11, §12 |
| Telawah | `TelawahWaitingFB`, `TelawahConfirmAddToShare` | — | — | **RETIRED — OWNER APPROVED** | Share queue retired | PLAN §28.1 |
| Fatwa | `w2a_fatwa_main`, `w2a_fatwa_list_all`, `w2a_fatwa_list_all_questions`, `w2a_fatwa_list_all_general_questions` | Fatwa | public fatwa pages with contextual controls | **PARTIAL** | No AdminCP listing screens for fatwas / questions / general questions; management is from the public pages only | DL #69; PLAN §40 |
| Fatwa | `w2a_fatwa_add`, `w2a_fatwa_edit`, `w2a_fatwa_del` | Fatwa | admin.fatwa.questions.fatwas.create/store, fatwas.edit/update, fatwas.delete/destroy | **VERIFIED** | — | DL #69 (owner manual review) |
| Fatwa | `fatwa_add_question`, `fatwa_edit_question`, `fatwa_del_question` | Fatwa | admin.fatwa.topics.questions.create/store, questions.edit/update | **PARTIAL** | Question delete: not among the 14 preserved public controls; AdminCP delete not dispositioned | DL #69 |
| Fatwa | `fatwa_add_general_question`, `fatwa_edit_general_question`, `fatwa_del_general_question` | Fatwa | general-question admin (FatwaGeneralQuestionAdminController) | **IMPLEMENTED — NOT VERIFIED** | Controller exists; no functional record for the AdminCP flows | this audit |
| Fatwa | `fatwa_many_questions`, `fatwa_move_questions`, `fatwa_delete_questions` | Fatwa | admin.fatwa.fatwas.questions.add/move/remove | **VERIFIED** | — | DL #69 |
| Fatwa | `question_many_topics`, `question_move_topics`, `question_delete_topics` | Fatwa | admin.fatwa.questions.topics.add/move/remove | **VERIFIED** | — | DL #69, #71 |
| Fatwa | `del_all_topics_for_catg` | — | — | **UNKNOWN** | Bulk delete of a category's topics; not dispositioned | this audit |
| Fatwa | `getfilesize` | Fatwa | admin.fatwa.media-size | **VERIFIED** | Manual size entry kept when the probe fails | DL #69 |
| Fatwa | `CatClearModertorsLogs` | — | — | **MISSING** | Log deletion not offered (same as Khotab) | PLAN §15 |
| w2acd | `W2ACDMain`, `W2ACDDetailes` | CDs | admin.cds.index / edit | **VERIFIED** | Display/menu replaced by the admin list and edit page | PLAN §25.3, §25.9 |
| w2acd | `W2ACDInsertW2ACD`, `cdsConfirmInsert` | CDs | admin.cds.create/store | **VERIFIED** | — | PLAN §25.3, §25.9 |
| w2acd | `W2ACDEditW2ACD`, `cdsConfirmEdit`, `del_cd_img` | CDs | admin.cds.edit/update (links, images, title, group) | **VERIFIED** | Image drop is part of edit (OLD GET mutation) | PLAN §25.3, §25.9 |
| w2acd | `W2ACDDeleteW2ACD`, `W2ACDConfirmDeleteW2ACD` | CDs | admin.cds.destroy | **VERIFIED** | — | PLAN §25.3, §25.9 |
| w2acd | `Order_w2acd_in_group` | CDs | admin.cds.order | **VERIFIED** | — | PLAN §25.3 |
| w2acd | `W2ACDAddGroup`, `W2ACDConfirmAddGroup`, `W2ACDEditGroup`, `W2ACDConfirmEditGroup`, `W2ACDDeleteGroup`, `W2ACDConfirmDeleteGroup`, `W2ACDGroupIcon`, `W2ACDUploadIcon` | CDs | admin.cds.groups.* (create/edit/update/delete/destroy/icon/subgroups) | **VERIFIED** | Added by the W2ACD public-admin phases after §25.3 had excluded them | DL #74–#78; PLAN §44–§49 |
| w2acd | `W2ACDUploadBanner`, `W2ACDAdd_banner`, `W2ACDEdit_banner`, `W2ACDDelete_banner`, `W2ACDConfirmDelete_banner`, `W2ACDEditImage`, `W2ACDDeleteImage` | CDs | admin.cds.banner / banner.upload / banner.delete/destroy | **VERIFIED** | OLD .gif/.jpg path inconsistency resolved | DL #74–#76; PLAN §45 |
| w2acd | `W2ACDReArrange`, `W2ACDConfirmReArrange` | — | — | **UNKNOWN** | Executable in OLD but output not consumed (public ignores `weight`); technical exclusion, not owner retirement | PLAN §25.3 |
| w2acd | `W2ACDInsertMirror`, `W2ACDConfirmInsertMirror`, `W2ACDEditMirror`, `W2ACDConfirmEditMirror`, `W2ACDDeleteMirror`, `W2ACDConfirmDeleteMirror` | — | — | **UNKNOWN** | Normalized mirror table exactly empty, never used (PLAN §2 row 1b, technical) | PLAN §2 row 1b, §25.3 |
| w2acd | `W2ACDWaitingList`, `W2ACDAddComments`, `نشر التعليق للمادة`, `مسح التعليق للمادة`, `W2ACDAddModComment` | — | — | **UNKNOWN** | 0 comments and no public consumer (technical exclusion) | PLAN §25.3 |
| w2acd | `W2ACDListDown` | — | — | **UNKNOWN** | Always empty report (technical exclusion) | PLAN §25.3 |
| w2acd | `W2ACDListModertors`, `W2ACDConfirmEditMod` | Staff / permissions | admin.permissions.edit/update (w2acd.managecds) | **VERIFIED** | One module key; OLD tokens unused by OLD itself | ADM §11 |
| w2acd | `W2ACDWaitingFB`, `W2ACDConfirmAddToShare` | — | — | **UNKNOWN** | Generic share queue; the 2026-10-15 retirement named only Khotab / Anasheed / Telawah | DL #72 |
| w2a_cd | `w2a_cd` | Anasheed | Anasheed admin (same tables) | **PARTIAL** | No separate «اسطوانات أرشيف» view; its rows are Anasheed rows | PLAN §25.3; DL #82 |
| w2a_cd | `InsertCD`, `ConfirmInsertCD`, `del_cd_img` | — | — | **UNKNOWN** | CD-style insert into `nuke_anasheed_*`; never dispositioned (public controls DEAD, DL #82) | DL #82 |
| w2a_cd | `AnasheedEditAnasheed`, `AnasheedConfirmEditAnasheed`, `AnasheedDeleteAnasheed`, `AnasheedConfirmDeleteAnasheed` | Anasheed | admin.anasheed.items.* | **VERIFIED** | Shared Laravel Anasheed implementation | DL #62 |
| w2a_cd | `AnasheedAddGroup`, `AnasheedConfirmAddGroup`, `AnasheedEditGroup`, `AnasheedConfirmEditGroup`, `AnasheedDeleteGroup`, `AnasheedConfirmDeleteGroup`, `AnasheedGroupIcon`, `AnasheedUploadIcon` | Anasheed | admin.anasheed.groups.* | **VERIFIED** | Shared implementation | DL #61 |
| w2a_cd | `AnasheedEditImage`, `AnasheedDeleteImage` | Anasheed | admin.anasheed.items.frame | **VERIFIED** | Shared implementation | DL #62 |
| w2a_cd | `AnasheedCount`, `AnasheedListDown` | — | — | **UNKNOWN** | See Anasheed rows | PLAN §2 row 14 |
| w2a_cd | `AnasheedListModertors`, `AnasheedConfirmEditMod` | Staff / permissions | admin.permissions.* | **VERIFIED** | — | ADM §11 |
| cd | `w2a_cds_main`, `w2a_cds_newcd`, `w2a_cds_editcd`, `w2a_cds_delcd` | — | — | **MISSING** | CD list / add / edit / delete over `nuke_w2a_cds` (106 rows); Laravel neither reads nor writes this table | this audit (nuke_modules.active = 0) |
| cd | `w2a_image_details`, `w2a_add_cd_image`, `w2a_edit_cd_image`, `w2a_delete_cd_image` | — | — | **MISSING** | CD image details (`nuke_cd_image_details`, 3 rows); no Laravel equivalent | this audit |
| Mobile | `downloads` | Mobile | admin.mobile.dashboard / submissions | **VERIFIED** | OLD entry page crashes on PHP 7+ (defect) | PLAN §26.7, §26.13 |
| Mobile | `DownloadsAddCat`, `DownloadsAddSubCat`, `DownloadsModCat`, `DownloadsModCatS`, `DownloadsDelCat`, `DownloadsTransfer` | Mobile | admin.mobile.categories.* (store/edit/update/destroy/transfer) | **VERIFIED** | — | PLAN §26.7, §26.13 |
| Mobile | `DownloadsAddDownload`, `DownloadsModDownload`, `DownloadsModDownloadS`, `DownloadsDelDownload` | Mobile | admin.mobile.materials.* | **VERIFIED** | Duplicate URL refused | PLAN §26.7, §26.13 |
| Mobile | `DownloadsDelNew` | Mobile | admin.mobile.submissions.approve/reject | **VERIFIED** | — | PLAN §26.7 |
| Mobile | `DownloadsListModRequests`, `DownloadsChangeModRequests`, `DownloadsChangeIgnoreRequests`, `DownloadsListBrokenDownloads`, `DownloadsDelBrokenDownloads`, `DownloadsIgnoreBrokenDownloads` | Mobile | admin.mobile.corrections.*, broken.* | **VERIFIED** | — | PLAN §26.7, §26.13 |
| Mobile | `DownloadsDelVote`, `DownloadsDelComment`, `DownloadsCleanVotes` | Mobile | admin.mobile.votes.destroy, votes.comment.destroy, recalculate | **VERIFIED** | — | PLAN §26.7 |
| Mobile | `DownloadsAddEditorial`, `DownloadsModEditorial`, `DownloadsDelEditorial` | Mobile | admin.mobile.materials.editorial(.destroy) | **VERIFIED** | — | PLAN §26.7 |
| Surveys | `create`, `createPosted` | Polls | admin.polls.create/store | **VERIFIED** | — | PLAN §23, §23.9; DL #59 |
| Surveys | `polledit`, `polledit_select`, `savepoll` | Polls | admin.polls.index/edit/update | **VERIFIED** | — | PLAN §23.9 |
| Surveys | `remove`, `removePosted` | Polls | admin.polls.destroy | **VERIFIED** | — | PLAN §23.9 |
| Cat | `w2a_cat_main`, `w2a_cat_list` | Categories | admin.categories.khotab.index / anasheed.index (assignment views) | **PARTIAL** | Laravel lists categories for assignment only; no category-tree management screen | PLAN §21; ADM §12 |
| Cat | `w2a_cat_add`, `w2a_cat_edit`, `w2a_cat_del` | — | — | **MISSING** | Category tree add / edit / delete (`w2a_cat_add/edit/del`) has no Laravel equivalent | PLAN §17 (line 1489, "out of scope" — phase scope, not an owner retirement); ADM §12 |
| Cat | `cat_add_to_item`, `cat_del_from_item`, `cat_add_to_anasheed`, `cat_del_from_anasheed` | Categories | admin.categories.{khotab,anasheed}.item.add/remove | **VERIFIED** | — | PLAN §21, §21.7, §28.3 K5–K6, A4–A5 |
| Cat | `cat_add_to_series`, `cat_del_from_series`, `cat_add_to_group`, `cat_del_from_group`, `cat_add_to_subseries`, `cat_del_from_subseries`, `cat_add_to_subgroup`, `cat_del_from_subgroup` | Categories | admin.categories.khotab.{group,series}.add-all/remove-all | **VERIFIED** | Sub-group / sub-series variants folded into the cascade | PLAN §21, §28.3 G1–G2, S1–S2 |
| Cat | `cat_fastcopy`, `cat_fastcopy_confirm` | Khotab | admin.khotab.{groups,series}.fastcopy(.save) | **VERIFIED** | — | PLAN §31.2(1),(2) |
| Cat | `cat_repair_item`, `cat_repair_series`, `cat_repair_group` | Categories | KhotabParentCategoryRepair (automatic after each assignment) | **PARTIAL** | No manual repair screen; the cascade runs automatically | PLAN §21 |
| Cat | `CatModertorsLogs` | Audit | admin.audit.legacy (module Cat) | **VERIFIED** | — | PLAN §15.8 |
| advfiles | `AdvfilesMain` | Advfiles | admin.advfiles.queue | **VERIFIED** | — | PLAN §24, §24.9 |
| advfiles | `AdvfilesInsertAdvFile`, `AdvfilesConfirmInsertAdvFile`, `AdvfilesUploadAdvFileImage` | Advfiles | admin.advfiles.approve.form/approve (with image) | **VERIFIED** | — | PLAN §24.9 |
| advfiles | `AdvfilesEditAdvFile`, `AdvfilesConfirmEditAdvFile` | Advfiles | admin.advfiles.edit/update | **VERIFIED** | — | PLAN §24.9 |
| advfiles | `AdvfilesDeleteAdvFile`, `AdvfilesConfirmDeleteAdvFile`, `AdvfilesResetAdvFile` | Advfiles | admin.advfiles.reject / unpublish | **VERIFIED** | — | PLAN §24.9 |
| advfiles | `AdvfilesCreditAdvFile`, `AdvfilesConfirmCreditAdvFile` | Advfiles | admin.advfiles.credits / credits.save | **VERIFIED** | — | PLAN §24.9 |
| ads | `w2a_ads_main` | Ads | admin.ads.index | **IMPLEMENTED — NOT VERIFIED** | Owner-closed locally on tests and code / security review; no real-route functional run recorded | docs/reports/admincp-b6a-implementation-results.md §4, §5 |
| ads | `w2a_ads_newads`, `w2a_ads_editads` | Ads | admin.ads.create/store/edit/update | **IMPLEMENTED — NOT VERIFIED** | Code ads super-admin only; unsafe URLs rejected | B6a §2, §3.1 |
| ads | `w2a_ads_delads` | Ads | admin.ads.destroy | **IMPLEMENTED — NOT VERIFIED** | Delete via browser confirm, not a page | B6a §5 |
| ads | `update_show` | Ads | admin.ads.toggle | **IMPLEMENTED — NOT VERIFIED** | — | B6a §2 |
| ads | `w2a_ads_reports` | Ads | admin.ads.reports | **IMPLEMENTED — NOT VERIFIED** | View / click counting not implemented (no data source) | B6a §5; docs/reports/admincp-f01-placement-approval-matrix.md |
| banners_ads | `w2a_banners_ads_main` | Banner ads | admin.banners_ads.index/update (the featured-items banner set) | **IMPLEMENTED — NOT VERIFIED** | Owner-closed locally (B6b §9 concurrency / deletion review); no real-route functional run recorded | docs/reports/admincp-b6b-implementation-results.md §9 |
| report_ads | `w2a_bad_ads_main` | Ad reports | admin.report_ads.index | **IMPLEMENTED — NOT VERIFIED** | Public report submission not implemented (no source of new reports) | B6b §9.2 |
| report_ads | `w2a_bad_ads_delads` | Ad reports | admin.report_ads.destroy | **IMPLEMENTED — NOT VERIFIED** | — | B6b §9.2 |
| BackUp | `BackUpMain` | — | — | **UNKNOWN** | Backup coordination landing; whether the external backup tool still uses it is unresolved (Business Confirmation #7) | docs/reviews/old-admin-scope-reset.md §3; DL #101 |
| BackUp | `KhotabCheckLink` | Link quality | admin.link-quality.*.recheck | **PARTIAL** | Per-link recheck exists; OLD batch semantics not compared | PLAN §15.8 |
| BackUp | `BackUpListDown` | Link quality | admin.link-quality.khotab.lost | **PARTIAL** | Khotab only; OLD covered Khotab / Anasheed / Telawah | PLAN §15.8 |
| BackUp | `KhotabUpdateBackup`, `KhotabUpdateBackupServer` | Backup API | BackupApiController (`down` / `trial` lifecycle) | **PARTIAL** | Owned by the backup tool's API; no AdminCP screen | PLAN §2 row 2a |
| BackUp | `KhotabArchiveImport`, `KhotabArchiveImportMedia`, `KhotabArchiveExportForMedia` | — | — | **UNKNOWN** | archive.org import / export utilities; never dispositioned | this audit |
| BackUp | `BackUpGetGif` | — | — | **UNKNOWN** | GIF/JPG preview generation (`get_gif_jpg.php` family); NEEDS_BUSINESS_CONFIRMATION in the scope reset | docs/reviews/old-admin-scope-reset.md §2 (P-19) |
| BackUp | `BackUpRepairCD` | — | — | **UNKNOWN** | Never dispositioned | this audit |
| BackUp | `BackUpListBroken`, `BackUpDownloadList`, `GetStats` | — | — | **UNKNOWN** | Broken-list / download-list / statistics reports; never dispositioned | this audit |
| Satellite | `w2a_sat_main`, `w2a_sat_list`, `w2a_sat_add`, `w2a_sat_edit`, `w2a_sat_del` | — | — | **MISSING** | Satellite list / add / edit / delete (`nuke_sat_sats`, 4 rows, read by Laravel `Satellite` model) | this audit |
| Satellite | `w2a_channel_list`, `w2a_channel_add`, `w2a_channel_edit`, `w2a_channel_del`, `w2a_channel_addsub`, `w2a_channel_editsub`, `w2a_channel_delsub` | Broadcasting | admin.broadcasting.index/edit/update (stream code only) | **PARTIAL** | Only the live-stream code is editable; channel create / edit / delete (`nuke_sat_channels`, 70 rows, shown on public channel pages) are missing | BroadcastingController docblock; this audit |
| Satellite | `w2a_beam_add`, `w2a_beam_edit`, `w2a_beam_del`, `w2a_programs_add`, `w2a_programs_edit`, `w2a_programs_del` | — | — | **MISSING** | Beams / programs CRUD; both tables empty locally | this audit |
| Satellite | `channel_add_to_item`, `channel_add_to_series`, `channel_add_to_group` | Khotab | «القناة» field on item / series / group edit (group cascades) | **VERIFIED** | Assigned through the edit forms | PLAN §31.2; DL #60 |
| Satellite | `repair_channel` | — | — | **UNKNOWN** | Never dispositioned | this audit |
| Satellite | `w2a_series_list` | — | — | **UNKNOWN** | Never dispositioned (public `series_list` AJAX was dropped in Gen 2, DL #111) | DL #111 |
| live | `w2a_live_main`, `w2a_live_newlive`, `w2a_live_editlive`, `w2a_live_dellive`, `update_show` | Broadcasting | admin.broadcasting.* (channel stream codes) | **PARTIAL** | Gen 2 moved live streams to channel `streamcode`; Laravel follows Gen 2. OLD `nuke_live` CRUD (13 rows, last 2014) is not reproduced and not read by Laravel | DL #129; BroadcastingController docblock |
| live | `w2a_live_reports` | — | — | **MISSING** | Live-entry report; no equivalent | this audit |
| Quiz | `QuizMain`, `QuizResults` | — | — | **MISSING** | Quiz dashboard and results; no Laravel quiz feature (public or admin). Data: 202 exams (last 2019-02), 2,666 questions, 36,716 member rows | docs/reviews/old-admin-scope-reset.md §3–§4 (Priority 1, never dispositioned) |
| Quiz | `QuizAddQuiz`, `QuizConfirmAddQuiz`, `QuizEditQuiz`, `QuizConfirmEditQuiz`, `QuizDeleteQuiz`, `QuizConfirmDeleteQuiz`, `QuizReArrange`, `QuizConfirmReArrange` | — | — | **MISSING** | Question CRUD and ordering | same |
| Quiz | `QuizAddExam`, `QuizConfirmAddExam`, `QuizEditExam`, `QuizDeleteExam` | — | — | **MISSING** | Exam CRUD | same |
| Quiz | `QuizAddSemester`, `QuizConfirmAddSemester`, `QuizEditSemester`, `QuizDeleteSemester` | — | — | **MISSING** | Semester CRUD | same |
| Quiz | `QuizSaveMembers`, `QuizMemberReport`, `QuizInsertMember`, `QuizConfirmInsertMembers`, `QuizConfirmInsertMember`, `QuizDeleteMember`, `QuizConfirmDeleteMember` | — | — | **MISSING** | Participant management and reports (incl. import from a forum thread) | same |
| Quiz | `QuizEmailMember`, `QuizConfirmEmailMember` | — | — | **MISSING** | Email a participant | same |
| Quiz | `QuizListModertors`, `QuizConfirmEditMod` | — | — | **MISSING** | Quiz moderator grants; no `quiz.*` Laravel permission exists | ADM §11 |
| Tutorials | `TutorialsMain` | — | — | **MISSING** | Course dashboard; no Laravel tutorials feature. Data: 27 courses, 187 lessons, 2,048 members | docs/reviews/old-admin-scope-reset.md §3–§4 (never dispositioned) |
| Tutorials | `TutorialsInsertTutorials`, `TutorialsConfirmInsertTutorials`, `TutorialsEditTutorials`, `TutorialsConfirmEditTutorials`, `TutorialsDeleteTutorials`, `TutorialsConfirmDeleteTutorials` | — | — | **MISSING** | Course CRUD | same |
| Tutorials | `TutorialsAddLesson`, `TutorialsConfirmAddLesson`, `TutorialsDeleteLesson`, `TutorialsConfirmDeleteLesson` | — | — | **MISSING** | Lesson add / delete | same |
| Tutorials | `TutorialsLessonReport`, `TutorialsMultiLessonsReport` | — | — | **MISSING** | Lesson reports | same |
| Tutorials | `TutorialsSaveMembers`, `TutorialsMemberReport`, `TutorialsInsertMember`, `TutorialsConfirmInsertMembers`, `TutorialsConfirmInsertMember`, `TutorialsDeleteMember`, `TutorialsConfirmDeleteMember`, `TutorialsBanMember`, `TutorialsUnBanMember` | — | — | **MISSING** | Member management, ban / unban, reports | same |
| Tutorials | `TutorialsEmailMember`, `TutorialsConfirmEmailMember` | — | — | **MISSING** | Email a member | same |
| Feedback | `w2a_contact_us_reply`, `w2a_send_one_email`, `confirm_w2a_send_one_email` | — | — | **RETIRED — OWNER APPROVED** | Threaded replies retired | DL #108 (decision 3) |
| Feedback | `communication_admin_main`, `w2a_communication_del`, `w2a_communication_deltails` | — | — | **RETIRED — OWNER APPROVED** | Volunteer registration retired | DL #108 (decision 4) |
| Feedback | `w2a_contact_us_main`, `w2a_contact_us_details`, `w2a_contact_us_del`, `w2a_filter_dep`, `w2a_change_dep` | — | — | **MISSING** | Inbox of the retired form: list / read / delete / filter / move department. Historical data preserved (20,161 rows; 140-message burst in 2026-04, unprocessed); an inbox for it was not decided | DL #108 (decisions 2, 5) |
| Feedback | `w2a_contact_us_cats_main`, `w2a_contact_us_cat_del`, `w2a_contact_us_cat_add`, `w2a_contact_us_cat_insert`, `w2a_contact_us_cat_edit`, `w2a_contact_us_cat_update` | — | — | **MISSING** | Department CRUD for the retired form (0 categories) | DL #108 |
| FAQ | `FaqAdmin`, `FaqCatGo` | — | — | **MISSING** | FAQ category / answer listing (`nuke_faq*`, 0 rows locally) | DL #120 (public FAQ owner-retired; admin side not addressed) |
| FAQ | `FaqCatAdd`, `FaqCatGoAdd`, `FaqCatEdit`, `FaqCatGoEdit`, `FaqCatDel`, `FaqCatGoDel`, `FaqCatSave`, `FaqCatGoSave` | — | — | **MISSING** | Category and question CRUD | DL #120 |
| contact | `FaqAdmin`, `FaqCatGo` | — | — | **MISSING** | FAQ category / answer listing (`nuke_faq*`, 0 rows locally) | DL #120 (public FAQ owner-retired; admin side not addressed) |
| contact | `FaqCatAdd`, `FaqCatGoAdd`, `FaqCatEdit`, `FaqCatGoEdit`, `FaqCatDel`, `FaqCatGoDel`, `FaqCatSave`, `FaqCatGoSave` | — | — | **MISSING** | Category and question CRUD | DL #120 |
| flashxml | `w2a_7amalat` | — | — | **MISSING** | Campaign list; **Laravel's homepage slider reads `nuke_7amalat`** (`ContentListingService::homeSliderItems`) with no admin to maintain it | DL #124; this audit |
| flashxml | `w2a_7amalat_Add`, `w2a_7amalat_Edit`, `w2a_7amalat_Del`, `w2a_7amalat_Save` | — | — | **MISSING** | Add / edit / delete / save campaigns (image, link, order) | this audit |
| flashxml | `w2a_7amalat_status` | — | — | **MISSING** | Show / hide a campaign (`status`) | this audit |
| new_album | `album_upload` | — | — | **MISSING** | Upload album images; **Laravel's public gallery reads `nuke_albums` / `nuke_albums_images`** (84 albums, 2,522 images) | DL #107; this audit |
| new_album | `album_list_thumbnails` | — | — | **MISSING** | Thumbnail listing | this audit |
| new_album | `compress-album`, `delete-compressed-album` | — | — | **MISSING** | Build / delete the album ZIP that Laravel's restored ZIP download serves | DL #107 |
| Montada | `w2a_montada`, `w2a_montada_Save` | — | — | **MISSING** | Edit the two forum banners (`nuke_banners`, cat `montada`) that Laravel's home banner strip reads; the strip itself is built but disabled | docs/reports/admincp-b6b-implementation-results.md G2; F01 matrix |
| Montada | `w2a_ramadan_Save` | — | — | **UNKNOWN** | Save the «رابط رمضان» link; consumer not traced | this audit |
| hedaya_table | `w2a_hedaya_list_main`, `w2a_add_hedaya_lesson`, `w2a_edit_hedaya_lesson`, `w2a_del_hedaya_lesson` | — | — | **RETIRED — OWNER APPROVED** | Weekly live-room lesson schedule retired with the live rooms | DL #14, #39, #122; IF-033 |
| chat_server | `w2a_reboot_server` | — | — | **RETIRED — OWNER APPROVED** | Live voice rooms retired, no replacement; chat admin tooling removed | DL #14, #39 (CHAT_ROOM_ADMIN = REMOVE) |
| help | `w2a_help`, `w2a_edit_help`, `w2a_save_help` | — | — | **MISSING** | Edit contextual help texts; the public `help-{id}.htm` is owner-retired, the admin side was not addressed | DL #121 |
| home_files | `w2a_home_files_main`, `w2a_add_home_file`, `w2a_edit_home_file`, `w2a_del_home_file` | — | — | **MISSING** | Home-file CRUD; public home-file owner-retired, admin side not addressed; admin list unreachable in OLD (`hf_show`) | DL #123; PLAN line 3685 |
| questionnaire | `w2a_questionnaire_main`, `w2a_questionnaire_show`, `w2a_questionnaire_delete` | — | — | **MISSING** | List / show / delete the retired staff HR survey answers; Laravel's questionnaire admin reads a different (Gen 2) table; data retention is a separate owner decision | DL #125 |
| ramadan_competition | `w2a_ramadan_competition_main`, `w2a_ramadan_competition_show`, `w2a_ramadan_competition_delete` | — | — | **MISSING** | List / show / delete entries | this audit |
| high_rank | `High_rank_main` | — | — | **MISSING** | Video rating boost list | this audit |
| docx | `w2a_docx_main`, `w2a_docx_for_author` | Khotab | per-item DOCX upload / replace / delete | **PARTIAL** | Per-item management verified; the per-author Word-files report has no Laravel equivalent | PLAN §31.2(3) |
| Dump_files | `w2a_dump_files_main`, `w2a_dump_files_for_author` | Khotab | per-item PDF upload / replace / delete | **PARTIAL** | Per-item management verified; the per-author transcript report has no Laravel equivalent | PLAN §31.2(3); DL #119 |
| fb_share_list | `w2a_fb_share_list_main`, `add_share_mada`, `confirm_add_share_mada`, `edit_share_mada`, `confirm_edit_share_mada`, `delete_share_mada`, `confirm_delete_share_mada`, `update_fbshare_order` | — | — | **MISSING** | Curated Facebook share list (CRUD + order); the share *queues* were retired 2026-10-15, this list was not named | PLAN §28.1; docs/reviews/old-admin-scope-reset.md §4 |
| default_picasa_album | `w2a_default_picasa_album_main` | — | — | **MISSING** | Choose the default Picasa album; its public consumer (`gal2` browser) is owner-retired, admin side not addressed | DL #128 |
| update_khotab_count | `w2a_update_khotab_count_main` | Khotab | counter repair (author / main) | **UNKNOWN** | Bulk count / category update across Khotab; semantics not compared with Laravel's repair | this audit |
| Classificate_anasheed | `w2a_classificate_anasheed_main` | Categories | admin.categories.anasheed.* (item level) | **UNKNOWN** | Group-level Anasheed classification screen; equivalence not established | this audit |
| Your_Account | `mod_users`, `modifyUser`, `updateUser`, `delUser`, `delUserConf`, `addUser` | — | — | **MISSING** | PHP-Nuke member admin over `nuke_users` (30,632 rows); site members are vBulletin; the login URLs are historical-compatibility only | DL #126 |
| SYSTEM: AutoTheme | `autotheme` | — | — | **MISSING** | PHP-Nuke theme engine; Laravel renders fixed Blade layouts (no autotheme tables locally) | this audit |
| SYSTEM: نسخة إحتياطية | `backup` | — | — | **MISSING** | Download a database dump from the browser; Laravel has none (hosting-level backups) | this audit |
| SYSTEM: المجموعات (Blocks) | `BlocksAdmin`, `BlocksAdd`, `BlocksEdit`, `BlocksEditSave`, `ChangeStatus`, `BlocksDelete`, `BlockOrder`, `fixweight`, `block_show` | — | — | **MISSING** | Side / footer block management (`nuke_blocks`: 9 rows, 8 active; OLD layout renders only `blocks("Down")`); Laravel layout is code | this audit |
| SYSTEM: المجموعات (Blocks) | `HeadlinesAdmin`, `HeadlinesAdd`, `HeadlinesEdit`, `HeadlinesSave`, `HeadlinesDel` | — | — | **MISSING** | RSS headline sources (`nuke_headlines`, 26 rows) | this audit |
| SYSTEM: تحرير المشرفين | `mod_authors` | Staff | admin.staff.index | **VERIFIED** | Grouped by legacy rank; owner / super-admin badges | ADM §4, §10, §12 |
| SYSTEM: تحرير المشرفين | `AddAuthor` | Staff | admin.staff.create/store | **VERIFIED** | Forum-linked; optional initial password; owner-only super-admin flag | ADM §4 |
| SYSTEM: تحرير المشرفين | `modifyadmin`, `UpdateAuthor` | Staff / permissions | admin.permissions.edit/update/password, staff.profile, staff.rename (switch), super-admin promote / demote | **VERIFIED** | Super user → Laravel role only; login rename off until OLD retires | ADM §4, §10 |
| SYSTEM: تحرير المشرفين | `deladmin`, `deladmin2`, `deladminconf`, `assignstories` | Staff | admin.staff.deactivate / reactivate | **VERIFIED** | Delete replaced by deactivation (owner instruction); stories keep their attribution | ADM §4 |
| SYSTEM: مجموعات المستخدمين | `Groups`, `grp_add`, `grp_edit`, `grp_edit_save`, `grp_del`, `points_update` | — | — | **MISSING** | PHP-Nuke user groups / points (`nuke_groups`, 0 rows) | this audit |
| SYSTEM: مرجعية العناوين | `hreferer`, `delreferer` | — | — | **MISSING** | HTTP referrer log (`nuke_referer`, 318 rows) | this audit |
| SYSTEM: حجب ايبي | `ipban`, `save_banned`, `ipban_delete`, `ipban_edit`, `ipban_save` | — | — | **MISSING** | IP ban list (`nuke_banned_ip`, 0 rows); Laravel has throttles but no admin-managed ban list | this audit |
| SYSTEM: الرسائل | `messages`, `addmsg`, `editmsg`, `deletemsg`, `savemsg` | — | — | **MISSING** | Site-wide admin messages (`nuke_message`, 2 rows, 0 active) | this audit |
| SYSTEM: Moderation | `moderation`, `moderation_news`, `moderation_news_view`, `moderation_surveys`, `moderation_surveys_view`, `moderation_reviews`, `moderation_reviews_view`, `moderation_users_list`, `moderation_approval`, `moderation_reject` | — | — | **UNKNOWN** | PHP-Nuke karma moderation queue — empty; recorded as an intentional non-migration (technical), not an owner retirement | DL #59 |
| SYSTEM: البرامج المستقلة | `modules`, `module_status`, `module_edit`, `module_edit_save`, `home_module` | — | — | **MISSING** | Enable / rename modules and choose the home module (`nuke_modules`); Laravel routes are code | this audit |
| SYSTEM: قائمة المراسلة | `newsletter`, `newsletter_send`, `newsletter_sent`, `massmail_send`, `massmail_sent`, `check_type` | — | — | **MISSING** | Newsletter / mass mail to PHP-Nuke users | this audit |
| SYSTEM: nukeSEO | `nukeSEO`, `nukeSEOconfig`, `nukeSEOrobots`, `nukeSEOmeta`, `nukeSEOmetaSave`, `nukeSEOmetaValidate`, `nukeSEOMETAhelp`, `nukeSEOgoogleSMConfig`, `nukeSEODelMod`, `nukeSEODisableMod`, `nukeSEOgoogleSMwriteUI`, `nukeSEOgoogleSMSubmit`, `nukeSEOsubmit`, `nukeSEOsaturation`, `nukeSEOlinkpop`, `nukeSEOkeyrank` | — | — | **MISSING** | Meta tags, robots.txt, Google sitemap, search-engine submission and ranking tools; Laravel meta is code | this audit |
| SYSTEM: Optimize DB | `optimize` | — | — | **MISSING** | `OPTIMIZE TABLE` from the browser; no Laravel equivalent (DB maintenance) | this audit |
| SYSTEM: إعدادات الموقع | `Configure`, `options_menu`, `general`, `savegeneral`, `themes`, `savethemes`, `users`, `saveusers`, `comments`, `savecomments`, `languages`, `savelanguages`, `footer`, `savefooter`, `backend`, `savebackend`, `referers`, `savereferers`, `mailing`, `savemailing`, `other`, `saveother` | — | — | **MISSING** | PHP-Nuke `nuke_config` site settings (name, theme, footer, comments, mail …); Laravel uses config / .env | this audit |
| SYSTEM: — (comment removal, reached from public pages) | `RemovePollComment`, `removePollSubComments` | Polls | admin.polls.comments.destroy (contextual «مسح») | **VERIFIED** | — | PLAN §30.2, §30.4 |
| SYSTEM: — (comment removal, reached from public pages) | `RemoveComment`, `removeSubComments` | — | — | **UNKNOWN** | News-story comments (stories last written 2008) | this audit |

### B.1 Appendix — OLD dispatch labels that are not OLD functions (excluded from the counts)

| OLD module | Labels | Reason |
|---|---|---|
| Khotab | `KhotabConfirmMoveKhotab` (no dispatch in `admin/index.php`); `KhotabCopyKhotab` (handler `CopyKhotab` undefined); `Khotab` (handler `Khotab` undefined); `Edit` (no dispatch in `admin/index.php`); `ابحث` (handler `Search` undefined); `أضــف الآن` (no dispatch in `admin/index.php`) | dead in OLD |
| Anasheed | `AnasheedCheckRam` (handler `CheckRam` undefined); `AnasheedRepairRam` (handler `RepairRam` undefined); `AnasheedRepair` (handler `Repair` undefined); `AnasheedExport` (handler `Export` undefined); `Anasheed` (handler `Anasheed` undefined); `Edit` (no dispatch in `admin/index.php`); `AnasheedCatList` (no dispatch in `admin/index.php`); `AnasheedEditCat` (no dispatch in `admin/index.php`); `تعديل التصنيف` (no dispatch in `admin/index.php`); `ابحث` (handler `Search` undefined); `أضــف الآن` (no dispatch in `admin/index.php`); `confirm_delete_image` (handler `confirm_delete_image` undefined); `AnasheedReArrange` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)); `AnasheedConfirmReArrange` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)); `AnasheedOrder` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)); `Order_anasheed_in_group` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)) | dead in OLD |
| Telawah | `TelawahMoveGroup` (no dispatch in `admin/index.php`); `TelawahConfirmMoveGroup` (no dispatch in `admin/index.php`); `TelawahCheckRam` (handler `CheckRam` undefined); `TelawahRepairRam` (handler `RepairRam` undefined); `TelawahRepair` (handler `Repair` undefined); `TelawahModertorsLogs` (handler `ModertorsLogs` undefined); `TelawahClearModertorsLogs` (handler `ClearModertorsLogs` undefined); `Telawah` (handler `Telawah` undefined); `Edit` (no dispatch in `admin/index.php`); `TelawahCatList` (no dispatch in `admin/index.php`); `TelawahEditCat` (no dispatch in `admin/index.php`); `تعديل التصنيف` (no dispatch in `admin/index.php`); `ابحث` (handler `Search` undefined); `أضــف الآن` (no dispatch in `admin/index.php`) | dead in OLD |
| Fatwa | `CatModertorsLogs` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)) | dead in OLD |
| w2acd | `W2ACDConfirmInsertW2ACD` (handler `ConfirmInsertW2ACD` undefined); `W2ACDConfirmEditW2ACD` (handler `ConfirmEditW2ACD` undefined); `W2ACDCheckRam` (handler `CheckRam` undefined); `W2ACDRepairRam` (handler `RepairRam` undefined); `W2ACDRepair` (handler `Repair` undefined); `W2ACDExport` (handler `Export` undefined); `W2ACD` (handler `W2ACD` undefined); `Edit` (no dispatch in `admin/index.php`); `W2ACDCatList` (no dispatch in `admin/index.php`); `W2ACDEditCat` (no dispatch in `admin/index.php`); `تعديل التصنيف` (no dispatch in `admin/index.php`); `ابحث` (handler `Search` undefined); `أضــف الآن` (no dispatch in `admin/index.php`); `confirm_delete_image` (handler `confirm_delete_image` undefined); `W2ACDOrder` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)); `W2ACDCount` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)) | dead in OLD |
| w2a_cd | `AnasheedDetailes` (handler `Detailes` undefined); `cdsConfirmInsertxx` (no dispatch in `admin/index.php`); `cdsConfirmEditxx` (no dispatch in `admin/index.php`); `AnasheedCheckRam` (handler `CheckRam` undefined); `AnasheedRepairRam` (handler `RepairRam` undefined); `AnasheedRepair` (handler `Repair` undefined); `AnasheedExport` (handler `Export` undefined); `confirm_delete_image` (handler `confirm_delete_image` undefined); `AnasheedOrder` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)) | dead in OLD |
| Mobile | `DownloadsDownloadCheck` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)); `DownloadsValidate` (owner-closed DEAD / unreachable (PLAN §25.3, §34, §38; DL #61, #67)) | dead in OLD |
| Surveys | `ChangePoll` (handler `ChangePoll` undefined) | dead in OLD |
| Cat | `CatClearModertorsLogs` (handler `ClearModertorsLogs` undefined) | dead in OLD |
| banners_ads | `w2a_ads_newads` (no dispatch in `admin/index.php`); `w2a_ads_editads` (no dispatch in `admin/index.php`); `w2a_ads_delads` (no dispatch in `admin/index.php`); `update_show` (no dispatch in `admin/index.php`); `w2a_ads_reports` (no dispatch in `admin/index.php`) | dead in OLD |
| BackUp | `BackUpCDRepair` (no dispatch in `admin/index.php`) | dead in OLD |
| Quiz | `QuizDetailes` (handler `Detailes` undefined); `QuizConfirmEditExam` (no dispatch in `admin/index.php`); `QuizConfirmDeleteExam` (no dispatch in `admin/index.php`); `QuizConfirmEditSemester` (no dispatch in `admin/index.php`); `QuizConfirmDeleteSemester` (no dispatch in `admin/index.php`); `Quiz` (handler `Quiz` undefined); `أضــف الآن` (no dispatch in `admin/index.php`) | dead in OLD |
| Tutorials | `TutorialsDetailes` (handler `Detailes` undefined) | dead in OLD |
| new_album | `new_album` (no dispatch in `admin/index.php`) | dead in OLD |
| default_picasa_album | `w2a_bad_ads_delads` (no dispatch in `admin/index.php`) | dead in OLD |
| update_khotab_count | `w2a_bad_ads_delads` (handler `w2a_del_bad_ads` undefined) | dead in OLD |
| SYSTEM | `op=submissions` (dashboard link) | no `admin/case` file handles it |

## C. Missing functionality, grouped by Laravel module

Rows with status MISSING, PARTIAL, UNKNOWN or IMPLEMENTED — NOT VERIFIED. Shared implementations are listed once (FAQ = contact; `w2a_cd` = Anasheed).

### Ad reports

- **IMPLEMENTED — NOT VERIFIED** — report_ads: `w2a_bad_ads_main` — Public report submission not implemented (no source of new reports)
- **IMPLEMENTED — NOT VERIFIED** — report_ads: `w2a_bad_ads_delads` — —

### Ads

- **IMPLEMENTED — NOT VERIFIED** — ads: `w2a_ads_main` — Owner-closed locally on tests and code / security review; no real-route functional run recorded
- **IMPLEMENTED — NOT VERIFIED** — ads: `w2a_ads_newads`, `w2a_ads_editads` — Code ads super-admin only; unsafe URLs rejected
- **IMPLEMENTED — NOT VERIFIED** — ads: `w2a_ads_delads` — Delete via browser confirm, not a page
- **IMPLEMENTED — NOT VERIFIED** — ads: `update_show` — —
- **IMPLEMENTED — NOT VERIFIED** — ads: `w2a_ads_reports` — View / click counting not implemented (no data source)

### Anasheed

- **PARTIAL** — Anasheed: `AnasheedMain` — No AdminCP dashboard landing (OLD menu: totals, pending comments, lists); Main panel itself DEAD (PLAN §34)
- **UNKNOWN** — Anasheed: `AnasheedAdd_banner`, `AnasheedEdit_banner`, `AnasheedDelete_banner`, `AnasheedConfirmDelete_banner` — Separate banner add / edit / delete ops; not individually dispositioned (the public panel dispositions cover the frame only)
- **PARTIAL** — Anasheed: `AnasheedWaitingList`, `نشر التعليق للمادة`, `مسح التعليق للمادة`, `AnasheedAddComments` — Per-item moderation verified; the site-wide pending-comments AdminCP queue was left out of scope (no owner retirement)
- **MISSING** — Anasheed: `AnasheedListDown` — Lost-backup report for Anasheed (`down = 0 OR trial > 5`); Laravel's lost-link worklist covers Khotab only
- **UNKNOWN** — Anasheed: `AnasheedCount` — Counter repair; DEAD by PLAN §2 row 14 (technical), not owner-retired
- **UNKNOWN** — w2a_cd: `InsertCD`, `ConfirmInsertCD`, `del_cd_img` — CD-style insert into `nuke_anasheed_*`; never dispositioned (public controls DEAD, DL #82)
- **UNKNOWN** — w2a_cd: `AnasheedCount`, `AnasheedListDown` — See Anasheed rows

### Banner ads

- **IMPLEMENTED — NOT VERIFIED** — banners_ads: `w2a_banners_ads_main` — Owner-closed locally (B6b §9 concurrency / deletion review); no real-route functional run recorded
- **MISSING** — Montada: `w2a_montada`, `w2a_montada_Save` — Edit the two forum banners (`nuke_banners`, cat `montada`) that Laravel's home banner strip reads; the strip itself is built but disabled
- **UNKNOWN** — Montada: `w2a_ramadan_Save` — Save the «رابط رمضان» link; consumer not traced

### Broadcasting / channels

- **MISSING** — Satellite: `w2a_sat_main`, `w2a_sat_list`, `w2a_sat_add`, `w2a_sat_edit`, `w2a_sat_del` — Satellite list / add / edit / delete (`nuke_sat_sats`, 4 rows, read by Laravel `Satellite` model)
- **PARTIAL** — Satellite: `w2a_channel_list`, `w2a_channel_add`, `w2a_channel_edit`, `w2a_channel_del`, `w2a_channel_addsub`, `w2a_channel_editsub` … — Only the live-stream code is editable; channel create / edit / delete (`nuke_sat_channels`, 70 rows, shown on public channel pages) are missing
- **MISSING** — Satellite: `w2a_beam_add`, `w2a_beam_edit`, `w2a_beam_del`, `w2a_programs_add`, `w2a_programs_edit`, `w2a_programs_del` — Beams / programs CRUD; both tables empty locally
- **UNKNOWN** — Satellite: `repair_channel` — Never dispositioned
- **UNKNOWN** — Satellite: `w2a_series_list` — Never dispositioned (public `series_list` AJAX was dropped in Gen 2, DL #111)
- **PARTIAL** — live: `w2a_live_main`, `w2a_live_newlive`, `w2a_live_editlive`, `w2a_live_dellive`, `update_show` — Gen 2 moved live streams to channel `streamcode`; Laravel follows Gen 2. OLD `nuke_live` CRUD (13 rows, last 2014) is not reproduced and not read by Laravel
- **MISSING** — live: `w2a_live_reports` — Live-entry report; no equivalent

### CDs

- **UNKNOWN** — w2acd: `W2ACDReArrange`, `W2ACDConfirmReArrange` — Executable in OLD but output not consumed (public ignores `weight`); technical exclusion, not owner retirement
- **UNKNOWN** — w2acd: `W2ACDInsertMirror`, `W2ACDConfirmInsertMirror`, `W2ACDEditMirror`, `W2ACDConfirmEditMirror`, `W2ACDDeleteMirror`, `W2ACDConfirmDeleteMirror` — Normalized mirror table exactly empty, never used (PLAN §2 row 1b, technical)
- **UNKNOWN** — w2acd: `W2ACDWaitingList`, `W2ACDAddComments`, `نشر التعليق للمادة`, `مسح التعليق للمادة`, `W2ACDAddModComment` — 0 comments and no public consumer (technical exclusion)
- **UNKNOWN** — w2acd: `W2ACDListDown` — Always empty report (technical exclusion)
- **UNKNOWN** — w2acd: `W2ACDWaitingFB`, `W2ACDConfirmAddToShare` — Generic share queue; the 2026-10-15 retirement named only Khotab / Anasheed / Telawah

### Categories

- **PARTIAL** — Cat: `w2a_cat_main`, `w2a_cat_list` — Laravel lists categories for assignment only; no category-tree management screen
- **MISSING** — Cat: `w2a_cat_add`, `w2a_cat_edit`, `w2a_cat_del` — Category tree add / edit / delete (`w2a_cat_add/edit/del`) has no Laravel equivalent
- **PARTIAL** — Cat: `cat_repair_item`, `cat_repair_series`, `cat_repair_group` — No manual repair screen; the cascade runs automatically
- **UNKNOWN** — Classificate_anasheed: `w2a_classificate_anasheed_main` — Group-level Anasheed classification screen; equivalence not established

### Fatwa

- **PARTIAL** — Fatwa: `w2a_fatwa_main`, `w2a_fatwa_list_all`, `w2a_fatwa_list_all_questions`, `w2a_fatwa_list_all_general_questions` — No AdminCP listing screens for fatwas / questions / general questions; management is from the public pages only
- **PARTIAL** — Fatwa: `fatwa_add_question`, `fatwa_edit_question`, `fatwa_del_question` — Question delete: not among the 14 preserved public controls; AdminCP delete not dispositioned
- **IMPLEMENTED — NOT VERIFIED** — Fatwa: `fatwa_add_general_question`, `fatwa_edit_general_question`, `fatwa_del_general_question` — Controller exists; no functional record for the AdminCP flows
- **UNKNOWN** — Fatwa: `del_all_topics_for_catg` — Bulk delete of a category's topics; not dispositioned
- **MISSING** — Fatwa: `CatClearModertorsLogs` — Log deletion not offered (same as Khotab)

### Khotab

- **MISSING** — Khotab: `KhotabJoin`, `KhotabConfirmJoin` — OLD bulk re-parent (`JoinMe`) has no Laravel equivalent; carried as legacy residual risk, never owner-retired
- **UNKNOWN** — Khotab: `MirrorExport`, `KhotabExport`, `KhotabExportMirror`, `KhotabExportAnasheed`, `KhotabExportTelawah` — Exports of backed-up link lists for the external backup tool; MirrorExport judged unused (PLAN §2 row 23, not an owner retirement); KhotabExport* never dispositioned
- **MISSING** — Khotab: `KhotabClearModertorsLogs` — Deleting legacy log history is not offered; retention is a policy decision
- **MISSING** — Khotab: `KhotabCatList`, `KhotabEditCat`, `تعديل التصنيف` — Category title / notes edit (category CRUD) has no Laravel screen; see Cat
- **PARTIAL** — docx: `w2a_docx_main`, `w2a_docx_for_author` — Per-item management verified; the per-author Word-files report has no Laravel equivalent
- **PARTIAL** — Dump_files: `w2a_dump_files_main`, `w2a_dump_files_for_author` — Per-item management verified; the per-author transcript report has no Laravel equivalent
- **UNKNOWN** — update_khotab_count: `w2a_update_khotab_count_main` — Bulk count / category update across Khotab; semantics not compared with Laravel's repair

### Link quality / Backup API

- **UNKNOWN** — BackUp: `BackUpMain` — Backup coordination landing; whether the external backup tool still uses it is unresolved (Business Confirmation #7)
- **PARTIAL** — BackUp: `KhotabCheckLink` — Per-link recheck exists; OLD batch semantics not compared
- **PARTIAL** — BackUp: `BackUpListDown` — Khotab only; OLD covered Khotab / Anasheed / Telawah
- **PARTIAL** — BackUp: `KhotabUpdateBackup`, `KhotabUpdateBackupServer` — Owned by the backup tool's API; no AdminCP screen
- **UNKNOWN** — BackUp: `KhotabArchiveImport`, `KhotabArchiveImportMedia`, `KhotabArchiveExportForMedia` — archive.org import / export utilities; never dispositioned
- **UNKNOWN** — BackUp: `BackUpGetGif` — GIF/JPG preview generation (`get_gif_jpg.php` family); NEEDS_BUSINESS_CONFIRMATION in the scope reset
- **UNKNOWN** — BackUp: `BackUpRepairCD` — Never dispositioned
- **UNKNOWN** — BackUp: `BackUpListBroken`, `BackUpDownloadList`, `GetStats` — Broken-list / download-list / statistics reports; never dispositioned

### Telawah

- **PARTIAL** — Telawah: `TelawahMain` — No AdminCP landing/listing screen
- **UNKNOWN** — Telawah: `Telawahaddlink`, `TelawahConfirmInsertMirror` — OLD disabled insert itself («غير متاحة»); not an owner retirement
- **UNKNOWN** — Telawah: `TelawahCount` — Counter repair; DEAD by PLAN §2 row 14 (technical)
- **UNKNOWN** — Telawah: `TelawahExport` — Backup export list; never dispositioned
- **UNKNOWN** — Telawah: `TelawahOrder` — Weight ordering; never dispositioned for Telawah
- **UNKNOWN** — Telawah: `TelawahWaitingList`, `نشر التعليق`, `مسح التعليق` — Comment queue; public «مسح التعليق» DEAD (PLAN §39 C1, 0 items with comments in production); AdminCP queue not dispositioned
- **MISSING** — Telawah: `TelawahUploadBanner` — Telawah banner upload has no Laravel operation

### New capability — no Laravel module (OLD `FAQ`)

- **MISSING** — FAQ: `FaqAdmin`, `FaqCatGo` — FAQ category / answer listing (`nuke_faq*`, 0 rows locally)
- **MISSING** — FAQ: `FaqCatAdd`, `FaqCatGoAdd`, `FaqCatEdit`, `FaqCatGoEdit`, `FaqCatDel`, `FaqCatGoDel` … — Category and question CRUD

### New capability — no Laravel module (OLD `Feedback`)

- **MISSING** — Feedback: `w2a_contact_us_main`, `w2a_contact_us_details`, `w2a_contact_us_del`, `w2a_filter_dep`, `w2a_change_dep` — Inbox of the retired form: list / read / delete / filter / move department. Historical data preserved (20,161 rows; 140-message burst in 2026-04, unprocessed); an inbox for it was not decided
- **MISSING** — Feedback: `w2a_contact_us_cats_main`, `w2a_contact_us_cat_del`, `w2a_contact_us_cat_add`, `w2a_contact_us_cat_insert`, `w2a_contact_us_cat_edit`, `w2a_contact_us_cat_update` — Department CRUD for the retired form (0 categories)

### New capability — no Laravel module (OLD `Quiz`)

- **MISSING** — Quiz: `QuizMain`, `QuizResults` — Quiz dashboard and results; no Laravel quiz feature (public or admin). Data: 202 exams (last 2019-02), 2,666 questions, 36,716 member rows
- **MISSING** — Quiz: `QuizAddQuiz`, `QuizConfirmAddQuiz`, `QuizEditQuiz`, `QuizConfirmEditQuiz`, `QuizDeleteQuiz`, `QuizConfirmDeleteQuiz` … — Question CRUD and ordering
- **MISSING** — Quiz: `QuizAddExam`, `QuizConfirmAddExam`, `QuizEditExam`, `QuizDeleteExam` — Exam CRUD
- **MISSING** — Quiz: `QuizAddSemester`, `QuizConfirmAddSemester`, `QuizEditSemester`, `QuizDeleteSemester` — Semester CRUD
- **MISSING** — Quiz: `QuizSaveMembers`, `QuizMemberReport`, `QuizInsertMember`, `QuizConfirmInsertMembers`, `QuizConfirmInsertMember`, `QuizDeleteMember` … — Participant management and reports (incl. import from a forum thread)
- **MISSING** — Quiz: `QuizEmailMember`, `QuizConfirmEmailMember` — Email a participant
- **MISSING** — Quiz: `QuizListModertors`, `QuizConfirmEditMod` — Quiz moderator grants; no `quiz.*` Laravel permission exists

### New capability — no Laravel module (OLD `Tutorials`)

- **MISSING** — Tutorials: `TutorialsMain` — Course dashboard; no Laravel tutorials feature. Data: 27 courses, 187 lessons, 2,048 members
- **MISSING** — Tutorials: `TutorialsInsertTutorials`, `TutorialsConfirmInsertTutorials`, `TutorialsEditTutorials`, `TutorialsConfirmEditTutorials`, `TutorialsDeleteTutorials`, `TutorialsConfirmDeleteTutorials` — Course CRUD
- **MISSING** — Tutorials: `TutorialsAddLesson`, `TutorialsConfirmAddLesson`, `TutorialsDeleteLesson`, `TutorialsConfirmDeleteLesson` — Lesson add / delete
- **MISSING** — Tutorials: `TutorialsLessonReport`, `TutorialsMultiLessonsReport` — Lesson reports
- **MISSING** — Tutorials: `TutorialsSaveMembers`, `TutorialsMemberReport`, `TutorialsInsertMember`, `TutorialsConfirmInsertMembers`, `TutorialsConfirmInsertMember`, `TutorialsDeleteMember` … — Member management, ban / unban, reports
- **MISSING** — Tutorials: `TutorialsEmailMember`, `TutorialsConfirmEmailMember` — Email a member

### New capability — no Laravel module (OLD `Your_Account`)

- **MISSING** — Your_Account: `mod_users`, `modifyUser`, `updateUser`, `delUser`, `delUserConf`, `addUser` — PHP-Nuke member admin over `nuke_users` (30,632 rows); site members are vBulletin; the login URLs are historical-compatibility only

### New capability — no Laravel module (OLD `cd`)

- **MISSING** — cd: `w2a_cds_main`, `w2a_cds_newcd`, `w2a_cds_editcd`, `w2a_cds_delcd` — CD list / add / edit / delete over `nuke_w2a_cds` (106 rows); Laravel neither reads nor writes this table
- **MISSING** — cd: `w2a_image_details`, `w2a_add_cd_image`, `w2a_edit_cd_image`, `w2a_delete_cd_image` — CD image details (`nuke_cd_image_details`, 3 rows); no Laravel equivalent

### New capability — no Laravel module (OLD `default_picasa_album`)

- **MISSING** — default_picasa_album: `w2a_default_picasa_album_main` — Choose the default Picasa album; its public consumer (`gal2` browser) is owner-retired, admin side not addressed

### New capability — no Laravel module (OLD `fb_share_list`)

- **MISSING** — fb_share_list: `w2a_fb_share_list_main`, `add_share_mada`, `confirm_add_share_mada`, `edit_share_mada`, `confirm_edit_share_mada`, `delete_share_mada` … — Curated Facebook share list (CRUD + order); the share *queues* were retired 2026-10-15, this list was not named

### New capability — no Laravel module (OLD `flashxml`)

- **MISSING** — flashxml: `w2a_7amalat` — Campaign list; **Laravel's homepage slider reads `nuke_7amalat`** (`ContentListingService::homeSliderItems`) with no admin to maintain it
- **MISSING** — flashxml: `w2a_7amalat_Add`, `w2a_7amalat_Edit`, `w2a_7amalat_Del`, `w2a_7amalat_Save` — Add / edit / delete / save campaigns (image, link, order)
- **MISSING** — flashxml: `w2a_7amalat_status` — Show / hide a campaign (`status`)

### New capability — no Laravel module (OLD `help`)

- **MISSING** — help: `w2a_help`, `w2a_edit_help`, `w2a_save_help` — Edit contextual help texts; the public `help-{id}.htm` is owner-retired, the admin side was not addressed

### New capability — no Laravel module (OLD `high_rank`)

- **MISSING** — high_rank: `High_rank_main` — Video rating boost list

### New capability — no Laravel module (OLD `home_files`)

- **MISSING** — home_files: `w2a_home_files_main`, `w2a_add_home_file`, `w2a_edit_home_file`, `w2a_del_home_file` — Home-file CRUD; public home-file owner-retired, admin side not addressed; admin list unreachable in OLD (`hf_show`)

### New capability — no Laravel module (OLD `new_album`)

- **MISSING** — new_album: `album_upload` — Upload album images; **Laravel's public gallery reads `nuke_albums` / `nuke_albums_images`** (84 albums, 2,522 images)
- **MISSING** — new_album: `album_list_thumbnails` — Thumbnail listing
- **MISSING** — new_album: `compress-album`, `delete-compressed-album` — Build / delete the album ZIP that Laravel's restored ZIP download serves

### New capability — no Laravel module (OLD `questionnaire`)

- **MISSING** — questionnaire: `w2a_questionnaire_main`, `w2a_questionnaire_show`, `w2a_questionnaire_delete` — List / show / delete the retired staff HR survey answers; Laravel's questionnaire admin reads a different (Gen 2) table; data retention is a separate owner decision

### New capability — no Laravel module (OLD `ramadan_competition`)

- **MISSING** — ramadan_competition: `w2a_ramadan_competition_main`, `w2a_ramadan_competition_show`, `w2a_ramadan_competition_delete` — List / show / delete entries

### Platform administration (no Laravel module)

- **MISSING** — SYSTEM: AutoTheme: `autotheme` — PHP-Nuke theme engine; Laravel renders fixed Blade layouts (no autotheme tables locally)
- **MISSING** — SYSTEM: نسخة إحتياطية: `backup` — Download a database dump from the browser; Laravel has none (hosting-level backups)
- **MISSING** — SYSTEM: المجموعات (Blocks): `BlocksAdmin`, `BlocksAdd`, `BlocksEdit`, `BlocksEditSave`, `ChangeStatus`, `BlocksDelete` … — Side / footer block management (`nuke_blocks`: 9 rows, 8 active; OLD layout renders only `blocks("Down")`); Laravel layout is code
- **MISSING** — SYSTEM: المجموعات (Blocks): `HeadlinesAdmin`, `HeadlinesAdd`, `HeadlinesEdit`, `HeadlinesSave`, `HeadlinesDel` — RSS headline sources (`nuke_headlines`, 26 rows)
- **MISSING** — SYSTEM: مجموعات المستخدمين: `Groups`, `grp_add`, `grp_edit`, `grp_edit_save`, `grp_del`, `points_update` — PHP-Nuke user groups / points (`nuke_groups`, 0 rows)
- **MISSING** — SYSTEM: مرجعية العناوين: `hreferer`, `delreferer` — HTTP referrer log (`nuke_referer`, 318 rows)
- **MISSING** — SYSTEM: حجب ايبي: `ipban`, `save_banned`, `ipban_delete`, `ipban_edit`, `ipban_save` — IP ban list (`nuke_banned_ip`, 0 rows); Laravel has throttles but no admin-managed ban list
- **MISSING** — SYSTEM: الرسائل: `messages`, `addmsg`, `editmsg`, `deletemsg`, `savemsg` — Site-wide admin messages (`nuke_message`, 2 rows, 0 active)
- **UNKNOWN** — SYSTEM: Moderation: `moderation`, `moderation_news`, `moderation_news_view`, `moderation_surveys`, `moderation_surveys_view`, `moderation_reviews` … — PHP-Nuke karma moderation queue — empty; recorded as an intentional non-migration (technical), not an owner retirement
- **MISSING** — SYSTEM: البرامج المستقلة: `modules`, `module_status`, `module_edit`, `module_edit_save`, `home_module` — Enable / rename modules and choose the home module (`nuke_modules`); Laravel routes are code
- **MISSING** — SYSTEM: قائمة المراسلة: `newsletter`, `newsletter_send`, `newsletter_sent`, `massmail_send`, `massmail_sent`, `check_type` — Newsletter / mass mail to PHP-Nuke users
- **MISSING** — SYSTEM: nukeSEO: `nukeSEO`, `nukeSEOconfig`, `nukeSEOrobots`, `nukeSEOmeta`, `nukeSEOmetaSave`, `nukeSEOmetaValidate` … — Meta tags, robots.txt, Google sitemap, search-engine submission and ranking tools; Laravel meta is code
- **MISSING** — SYSTEM: Optimize DB: `optimize` — `OPTIMIZE TABLE` from the browser; no Laravel equivalent (DB maintenance)
- **MISSING** — SYSTEM: إعدادات الموقع: `Configure`, `options_menu`, `general`, `savegeneral`, `themes`, `savethemes` … — PHP-Nuke `nuke_config` site settings (name, theme, footer, comments, mail …); Laravel uses config / .env
- **UNKNOWN** — SYSTEM: — (comment removal, reached from public pages): `RemoveComment`, `removeSubComments` — News-story comments (stories last written 2008)

## D. Existing verified functionality

Local verification only — **none of this is deployed or production-verified.**

- **Administrator management (OLD `mod_authors`):** list, create, edit, password, module permissions, super-admin promote / demote (owner-only), owner protection, deactivation / reactivation, login rename (switch off until OLD retires), one-time OLD permission migration with per-action tokens and the Khotab audio / video scope — real routes on disposable full copies (51/51, 68/68, 24/24, 19/19, 100/100 checks). ADM §1–§12; DL #154.
- **Khotab:** every public admin surface (Group, Series, Item, Author, Main) re-reviewed page by page with OLD-vs-Laravel browser and database comparisons and owner visual approval — item / series / group / author CRUD, move, rearrange, fast-copy, banners, PDF / DOCX, comments queue, counter repair, image leech. PLAN §31; DL #60.
- **Mirrors** (Khotab, Anasheed; Telawah edit / delete): PLAN §13, §15.8, §28.5 (57/57), §29.1, §30.4 (14/14).
- **Link quality / lost links / per-item link status:** PLAN §15.8, §28.3.
- **Categories (assignment):** item / group / series add / remove, cascade: PLAN §21.7, §28.3.
- **Anasheed:** group and item panels, the three «بدون …» worklists, per-item comment moderation: PLAN §32–§38; DL #61–#67 (owner manual review).
- **Telawah:** Main / group / item panels, item move: PLAN §39; DL #68 (owner manual review).
- **Fatwa:** the 14 public admin controls: DL #69 (owner manual review).
- **W2ACD (CDs):** CRUD, order, groups, banners: PLAN §25.9, §44–§49; DL #74–#78.
- **Mobile downloads:** categories, materials, submissions, corrections, broken reports, votes, editorials: PLAN §26.13.
- **Polls:** create / edit / delete and comment removal: PLAN §23.9, §30.2.
- **Advfiles (ملفات الرئيسية):** queue, approve, edit, reject / unpublish, credits, request: PLAN §24.9.
- **Moderator logs:** read-only legacy log viewer (Khotab, Anasheed, Cat) and the Laravel audit log: PLAN §15.8.
- **Owner-approved retirements:** social share queues (PLAN §28.1); live rooms and their schedule / restart tools (DL #14, #39, #122); Feedback replies and volunteer registration (DL #108).

## E. Priority of remaining gaps

### HIGH

- **Homepage campaigns «حملات الرئيسية»** (`flashxml`, `nuke_7amalat`): Laravel's homepage slider reads the table, but nothing can add, edit, hide or delete a campaign.
- **Gallery «الالبوم الدعوي»** (`new_album`): Laravel's public gallery and ZIP download read `nuke_albums*`, but there is no album upload, thumbnail listing or ZIP build / delete.
- **Satellite channels «الأقمار الصناعية»**: public channel pages and the `Satellite` model read `nuke_sat_*`; only a channel's stream code is editable — channel and satellite create / edit / delete are missing.
- **Category tree «التصنيفات»** (`w2a_cat_add/edit/del`, `KhotabEditCat`): categories can be assigned but not created, renamed or deleted.

### MEDIUM

- **Functional verification still owed:** Ads (5 ops), banner-ad set, ad reports (B6a / B6b — tests and reviews only); Fatwa general-question admin.
- **Fatwa AdminCP listings** (all fatwas / questions / general questions) and question delete; `del_all_topics_for_catg`.
- **Anasheed:** site-wide pending-comments queue (per-item moderation exists); banner add / edit / delete ops (U); lost-backup report (`AnasheedListDown`); AdminCP landing.
- **Lost-link worklist for Anasheed / Telawah** (OLD BackUp `ListDown` covered all three; Laravel: Khotab only).
- **Telawah banner upload** (`TelawahUploadBanner`).
- **Khotab `JoinMe` bulk re-parent** — never owner-retired; carried as residual risk (PLAN DoD 5a).
- **Forum banners** (`Montada`, `nuke_banners`) — needed only if the owner enables the home banner strip (F01).

### LOW

- Per-author Word / transcript reports (`docx`, `Dump_files`); manual category repair screen; Anasheed / Telawah dashboard landings; live-entry report; Satellite beams / programs (empty tables); Telawah export / order / count; W2ACD technical exclusions confirmed empty (re-arrange, normalized mirrors, comments, ListDown).
- UNKNOWN small modules needing a short trace: `update_khotab_count`, `Classificate_anasheed`, `Satellite` `repair_channel` / `w2a_series_list`, `Montada` «رابط رمضان», news-comment removal.

### OWNER DECISION

- **OLD-only programs with no Gen 2 / Laravel public side:** Quiz (36 ops; data to 2019), Tutorials (25 ops) — build or retire.
- **Admin of owner-retired public features** (public side retired, admin side never decided): FAQ / contact, help, home_files, questionnaire (staff survey data), default Picasa album, fb_share_list (share list, distinct from the retired queues), W2ACD share queue.
- **Feedback inbox** for the preserved `nuke_contact_us` messages (incl. the unprocessed 2026-04 burst) — view / delete, or archive only.
- **`cd` («قسم الاسطوانات», `nuke_w2a_cds`, inactive)**, **ramadan_competition**, **high_rank** — empty or inactive.
- **BackUp module** (Business Confirmation #7 still open): exports, archive.org import / export, GIF generation, broken / download lists, statistics, `RepairCD`.
- **Legacy-log clearing** (`*ClearModertorsLogs`) — a retention policy.
- **`live` (`nuke_live`)** — Gen 2 moved live streams to channel stream codes; confirm the old table is retired.
- **PHP-Nuke platform screens** (Screenshot 2): AutoTheme, DB backup download, Blocks / Headlines, user groups, referrers, IP ban, site messages, PHP-Nuke moderation, module manager, newsletter, nukeSEO, Optimize DB, site settings, PHP-Nuke members (`Your_Account`). Laravel implements these concerns in code / hosting; each needs an explicit "not needed" (retire) or a scoped replacement.
- **Technical exclusions never owner-retired** (status UNKNOWN): counter-repair ops (`AnasheedCount`, `TelawahCount`), Telawah mirror insert (disabled by OLD itself), `MirrorExport` / `KhotabExport*`.

## F. Final summary

1. **Total screenshot items:** **57** — Screenshot 1: 41 (40 module entries from 39 modules + «خروج»); Screenshot 2: 16 (15 entries + «خروج»).
2. **Total unique OLD modules:** **54** — 39 module programs + 15 system entries (`editadmins` = `mod_authors`; `submissions` a dead link). FAQ and contact share one implementation.
3. **Total OLD administrative functions:** **213** operations (620 case labels); a further **84** labels / links are dead in OLD and excluded (appendix B.1).
4. **Verified:** 95
5. **Implemented but not verified:** 9
6. **Partial:** 15
7. **Missing:** 59
8. **Explicitly retired (owner):** 7
9. **Unknown:** 28

10. **Work remaining before Laravel can replace OLD AdminCP:**
    1. **Implement the four HIGH gaps** — homepage campaigns admin, gallery admin (upload / thumbnails / ZIP), satellite / channel admin, category-tree management.
    2. **Close the MEDIUM items** — functional verification of ads / banner sets / ad reports and Fatwa general questions; Fatwa listings and question delete; Anasheed pending-comments queue, banner ops and lost-link report; Anasheed / Telawah lost-link worklist; Telawah banner upload; a decision or implementation for `JoinMe`.
    3. **Obtain owner decisions** on every OWNER DECISION group (§E) — each item is either retired explicitly or scoped for implementation; nothing may stay implicitly excluded.
    4. **Resolve the UNKNOWN rows** with a short trace each (§E LOW) and convert the technical exclusions into explicit owner retirements or implementation items.
    5. **Cutover prerequisites already recorded:** a production check that at least one active account matches the OLD owner rule, `admin:sync-permissions` once then `admin:migrate-old-permissions`, deactivation migration, media-root / upload permissions, and the OLD / Gen 2 retirement checklist (PLAN §55; ADM §11–§12).

**Verdict:** **NOT FULL PARITY.** Laravel covers the core content administration (Khotab, Anasheed, Telawah, Fatwa, W2ACD, Mobile, polls, advfiles, mirrors, categories assignment, administrators) with functional evidence, but 59 OLD operations are missing, 15 partial, 28 unknown and 9 unverified — including four HIGH gaps whose content Laravel already displays without any way to manage it.

