<?php

namespace Database\Seeders;

use App\Support\Permission\Permission;
use Illuminate\Database\Seeder;

/**
 * Wave 5, decision-log #9 — one Spatie `Permission` per legacy
 * `admincp/*\/menu.php`'s `$authorization[$module]` key, namespaced
 * `{module}.{key}`. Source: every `menu.php` in the 9 real feature
 * directories, read directly (not inferred). `khotab`/`telawah`'s real
 * content-CRUD authorization keys (~30 of them, `khotab/menu.php`) are
 * excluded — that content-CRUD doesn't exist in this codebase (Business
 * Confirmation #6); `forumConfig` excluded (Blueprint §18 "Never").
 *
 * `khotab.uploaders` (task 5.9) is one deliberate exception/addition:
 * a real, in-scope capability (`khotab/uploader(s).php`, Upload-Team
 * Tracking) that has no genuine match among `khotab/menu.php`'s 30
 * content-CRUD keys — none of them was ever actually wired to gate this
 * page (same "permission key defined, no corresponding real gate" shape
 * already confirmed for `khotab/`'s other permission keys, Pattern B).
 * Added as its own permission rather than force-fitting an unrelated
 * existing key or leaving this real capability permanently ungated.
 *
 * `khotab.repair`/`telawah.repair` (task 6.7) are a second exception,
 * for the mirror/link-quality stats-and-repair tool (`stats.php`,
 * `stats_khotab.php`, `stats_khotab_200mb.php`, `telawah/stats.php`) —
 * unlike the CRUD keys, this real capability doesn't need Business
 * Confirmation #6 (it's a straightforward port of already-working
 * legacy logic, not a "design fresh vs. reference" question).
 * `khotab.repair` is a genuine match, un-excluded from the otherwise-
 * excluded 30-key `khotab/menu.php` array specifically because it's
 * real and in-scope. `telawah/menu.php`'s own small array (4 keys, all
 * author-account-shaped) has no fitting match at all, so `telawah.repair`
 * is minted fresh, by direct analogy to `khotab.repair` — same shape as
 * the `khotab.uploaders` precedent above.
 *
 * `khotab.addmirror`/`editmirror`/`delmirror` and
 * `anasheed.addmirror`/`editmirror`/`delmirror` (OLD Admin Migration
 * Phase 0) are a third exception, for the Mirror Management unit. Three
 * things make them a different case from the ~30 still-excluded
 * `khotab/menu.php` content-CRUD keys:
 *
 * - The Khotab three are **genuine, real keys** in `admincp/khotab/menu.php`
 *   (`addmirror`/`delmirror`/`editmirror`), un-excluded here for the same
 *   reason `khotab.repair` was: the capability is real and in scope. The
 *   master execution plan classifies mirror management
 *   `IMPLEMENT_IN_LARAVEL`, not Business-Confirmation-#6 content CRUD.
 * - The Anasheed three are **minted fresh**. `admincp/` has no `anasheed`
 *   directory at all, so no legacy `menu.php` key exists to match — same
 *   shape as the `telawah.repair` precedent above. Owner-approved as a
 *   dedicated namespace (plan correction 2): Anasheed mirror capability is
 *   granted independently of Khotab's, because both legacy stores model
 *   the two domains as separate capability populations.
 * - **No `movemirror` key is minted.** Legacy re-parenting
 *   (`MoveMirror`) checked the `editmirror` token, and plan correction 4
 *   keeps that mapping; a finer-grained permission would be a new rule
 *   nobody asked for.
 *
 * These six are **definitions only**. Nothing here grants them, and no
 * legacy mirror grant is imported, unioned, intersected or synchronized
 * from `nuke_*_mod` or `nuke_authors.permissions` (plan correction 1).
 * Assignment happens through Laravel's own admin permission editor, like
 * any other permission in this list.
 *
 * Correction (2026-10-14, plan §27): this used to say only that
 * `SyncLegacyAdminPermissionsAction` was "not extended" to these keys.
 * That was not a guarantee — the action is generic: it grants every legacy
 * `{module}.{key}` matching *any* seeded permission and replaces each
 * admin's grants. The deployment installer ran it after this seeder, so it
 * would have imported legacy `khotab.addmirror` / `editmirror` / `delmirror`
 * / `addcat` / `addadv` assignments and erased editor-made grants. The
 * installer no longer runs it; `admin:sync-permissions` is a deliberate,
 * manual legacy-bootstrap tool only, and must not be run once Laravel
 * grants are in use (it would replace them). This applies to every key
 * added below as well.
 *
 * `khotab.addcat` and `anasheed.addcat` (OLD Admin Migration Phase 3 —
 * Category Assignment) follow the same two precedents: `khotab.addcat` is
 * the real `admincp/khotab/menu.php` key (`'addcat' => 'اضافة تصنيف'`);
 * `anasheed.addcat` is minted fresh for the same reason as the Anasheed
 * mirror keys. Each gates both adding and removing a category in its own
 * domain — no `delcat` key is minted, as none exists in the legacy
 * source. Definitions only; no legacy grant is imported.
 *
 * `polls.managepolls` (OLD Admin Migration Phase 4 — PHP-Nuke poll
 * administration) is minted fresh. OLD gated create / edit / remove with
 * one module-level check (`modules/Surveys/admin/index.php:19-29`: the
 * `Surveys` module's admins list, or radminsuper), so one key covers all
 * three. It is deliberately NOT `survey.modsurvey`: that key belongs to
 * the separate `admincp/survey/` custom-survey engine. Poll comment
 * removal needs no key — OLD allowed it to radminsuper only
 * (`admin/modules/comments.php:21`), reproduced as the super-admin role.
 *
 * `khotab.addadv` (OLD Admin Migration Phase 5 — Advfiles) is the real
 * `admincp/khotab/menu.php` key (`'addadv' => 'إضافة ملفات دعائية'`). It
 * gates every Advfiles management step (queue, approve, reject, edit,
 * remove, credit), which OLD gated with one module-level check. Requesting
 * needs no key: OLD let any administrator request. Definition only.
 *
 * `w2acd.managecds` (OLD Admin Migration Phase 6 — CD / Album Downloads) is
 * minted fresh: OLD gated its whole `modules/w2acd/admin` with one
 * module-level check, and no `admincp/` menu.php names W2ACD. It covers add,
 * edit, delete and order. Definition only.
 *
 * `ads.manageads` (F01 / B6a — OLD advertising administration) is minted
 * fresh: OLD gated the whole `modules/ads/admin` with one module-level check
 * (radminsuper, or the admin's name in `nuke_modules.admins` for `ads`), so
 * one key covers list, add, edit, delete, show/hide, upload and reports.
 * Raw-code ads (type 0) are additionally super-admin only (enforced in the
 * actions). Legacy `nuke_modules.admins` names are NOT imported: grants are
 * assigned by a super-admin. Definition only.
 *
 * `banners_ads.managebanners` and `report_ads.managereports` (F01 / B6b) are
 * minted fresh, one per OLD module: `modules/banners_ads/admin` and
 * `modules/report_ads/admin` each had their own module-level check
 * (radminsuper, or the admin's name in that module's `nuke_modules.admins`).
 * `ads.manageads` deliberately authorizes neither. Legacy names are not
 * imported. Definitions only.
 *
 * `mobile.managedownloads` (OLD Admin Migration Phase 7 — Mobile Downloads)
 * is minted fresh: OLD gated the whole `modules/Mobile/admin` with one
 * module-level check (the `Mobile` module admins, or radminsuper), and no
 * `admincp/` menu.php names it. It covers content administration and every
 * moderation step. Visitor actions are not admin permissions — they follow
 * the public member rules. Definition only.
 *
 * `telawah.editmirror` / `telawah.delmirror` (closure correction,
 * 2026-10-15 — Telawah mirror administration) use OLD's own token names:
 * OLD's Telawah handlers call `GreenZone("editmirror")` /
 * `GreenZone("delmirror")` (inert, like every GreenZone). No `addmirror`:
 * OLD disabled Telawah mirror insert itself. Definitions only.
 *
 * `khotab.editgroup` / `delgroup` / `addseries` / `additem` / `sortitems` /
 * `moveitem` (OLD public admin parity, Khotab group page, 2026-10-21) are
 * real `admincp/khotab/menu.php` keys, for the group page's
 * «لوحة تحكم المجموعة». The owner ruled that every working OLD public admin
 * control is preserved in this phase, Business Confirmation #6 included.
 * Definitions only.
 *
 * `khotab.editseries` / `delseries` (same parity rule, Khotab series page,
 * 2026-10-21) are real `admincp/khotab/menu.php` keys, for the series page's
 * «لوحة تحكم السلسلة». Definitions only.
 *
 * `khotab.edititem` / `delitem` / `uploadbanner` / `requestbanner` /
 * `uploadpdf` / `adddocx` / `deletedocx` / `approvecomment` / `delcomment`
 * (same parity rule, Khotab item page, 2026-10-21) are real
 * `admincp/khotab/menu.php` keys, for the item page's «لوحة تحكم المشرفين»
 * and its comment controls. Definitions only.
 *
 * `khotab.editauthor` / `delauthor` / `addgroup` (same parity rule, Khotab
 * author page, 2026-10-21) are real `admincp/khotab/menu.php` keys, for the
 * author page's «لوحة تحكم الداعية»; the owner also mapped its counter repair
 * (OLD checked nothing) to `editauthor`. The `audioauthor` / `videoauthor`
 * moderator split stays unreproduced. Definitions only.
 *
 * `khotab.addauthor` (same parity rule, Khotab Main, 2026-10-21) is the real
 * `admincp/khotab/menu.php` key («إضافة شيخ») for Main's «أضـــف شـــيـــخ»;
 * Main's counter sweep reuses `editauthor` and its comment queue reuses
 * `approvecomment` / `delcomment` (owner decisions). Definitions only.
 *
 * `anasheed.addicon` / `editgroup` / `addanasheed` / `addgroup` / `delgroup`
 * (OLD public admin parity, Anasheed group page, 2026-10-23) are OLD's own
 * tokens: the `nuke_anasheed_mod` columns its handlers name in
 * `GreenZone()` (inert, like every GreenZone) — the same table the
 * `anasheed.*mirror` keys already match. One per control of the group page's
 * «لوحة تحكم قسم المنوعات». Its «ترتيب القسم» is dead in production (the
 * `banner` column it selects does not exist), so `rearrange` is not minted.
 * Definitions only.
 *
 * `anasheed.addbanner` / `editanasheed` / `delanasheed` / `addcomment` /
 * `delcomment` (same parity rule, Anasheed item page, 2026-10-23) are OLD's
 * own `nuke_anasheed_mod` tokens too, owner-mapped: «اضافة صورة للمادة»
 * (`addbanner` — OLD's GreenZone token for that upload, despite the name),
 * «تعديل المادة», «حذف المادة», «إضافة تعليق إداري» and «(تعديل التعليق)»
 * with its publish (`addcomment`), and its «مسح» / hide (`delcomment`) —
 * OLD's own GreenZone split. Definitions only.
 *
 * `telawah.addgroup` / `editgroup` / `delgroup` / `addtelawah` /
 * `edittelawah` / `deltelawah` (OLD public admin parity, Telawah, 2026-10-29)
 * are OLD's own tokens: the `nuke_telawah_mod` columns its handlers name in
 * `GreenZone()` (inert, like every GreenZone). Main's «إضافة قارىء» and the
 * group page's «إضافة قسم فرعى» (`addgroup`), «تعديل القسم», «حذف القسم»,
 * «إضافة تلاوة», and the item page's «تعديل» and «نقل إلى قسم شيخ أخر»
 * (`edittelawah` — owner mapping, OLD had no move token) and «مسح». OLD's
 * `addbanner` / `addcomment` / `delcomment` are not minted: no working OLD
 * Telawah control uses them. Definitions only.
 *
 * `fatwa.manage` (OLD public admin parity, Fatwa, 2026-10-30) is minted
 * fresh, one key for the whole module (owner decision 1): OLD gated
 * `modules/Fatwa/admin` with one module-level check (the `Fatwa` module's
 * admins list, or radminsuper) and no `admincp/` menu.php names it. It
 * covers the fourteen preserved public controls and the size lookup; no
 * per-operation key is minted. Definition only — granted to nobody here.
 */
class AdminPermissionSeeder extends Seeder
{
    /** @var array<string, list<string>> module => authorization keys, per that module's own menu.php */
    private const MODULE_KEYS = [
        'survey' => ['modsurvey', 'modquestion', 'modanalysis'],
        'chat' => ['listrooms', 'editroom', 'deleteroom', 'listroom'],
        'authors' => ['addstuff', 'editstuff', 'deletestuff', 'liststuff'],
        'backup' => ['allsite', 'backupkhotab', 'backupkhotabmirror', 'backuptelawah', 'backupanasheed', 'backupanasheedmirror'],
        'locations' => ['add_location', 'del_location'],
        'soundcloud' => ['update_soundcloud'],
        'youtube' => ['update_youtube'],
        'questionnaire' => ['listquest', 'deletequest', 'listallquest'],
        'broadcasting' => ['addstream', 'editstream'],
        'khotab' => ['uploaders', 'repair', 'addmirror', 'editmirror', 'delmirror', 'addcat', 'addadv',
            'editgroup', 'delgroup', 'addseries', 'additem', 'sortitems', 'moveitem', 'editseries', 'delseries',
            'edititem', 'delitem', 'uploadbanner', 'requestbanner', 'uploadpdf', 'adddocx', 'deletedocx', 'approvecomment', 'delcomment',
            'editauthor', 'delauthor', 'addgroup', 'addauthor',
            // OLD Khotab audio / video moderator scope (KhotabMediaScopeConstraint, 2026-11-17).
            'media_audio', 'media_video'],
        'telawah' => ['repair', 'editmirror', 'delmirror',
            'addgroup', 'editgroup', 'delgroup', 'addtelawah', 'edittelawah', 'deltelawah'],
        'anasheed' => ['addmirror', 'editmirror', 'delmirror', 'addcat',
            'addicon', 'editgroup', 'addanasheed', 'addgroup', 'delgroup',
            'addbanner', 'editanasheed', 'delanasheed', 'addcomment', 'delcomment'],
        'polls' => ['managepolls'],
        'w2acd' => ['managecds'],
        'mobile' => ['managedownloads'],
        'fatwa' => ['manage'],
        'ads' => ['manageads'],
        'banners_ads' => ['managebanners'],
        'report_ads' => ['managereports'],
    ];

    public function run(): void
    {
        foreach (self::MODULE_KEYS as $module => $keys) {
            foreach ($keys as $key) {
                Permission::firstOrCreate(['name' => "{$module}.{$key}", 'guard_name' => 'admin']);
            }
        }
    }
}
