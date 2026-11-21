<?php

namespace App\Domain\Admin\Support;

/**
 * AdminCP Authenticated Design/CSS Parity (2026-08-23) — display-only
 * labels/icons for the permission-editor's per-module checkbox blocks
 * (`resources/views/admin/permissions/edit.blade.php`). Does not touch
 * permission names, grants, or authorization semantics anywhere — purely
 * what legacy `authors/edit_author.php` prints next to each real Spatie
 * permission it renders as a checkbox.
 *
 * Sourced from each real `admincp/{module}/menu.php`'s own `['arabic']`,
 * `['icon']`, and `$authorization[$module]` array — read fresh, not
 * inferred — for every module that has one. Two exceptions, documented
 * per entry, not silently invented:
 *
 * - `khotab.uploaders` has no legacy `menu.php` authorization entry at
 *   all (a Wave 5 task 5.9 fresh addition, per `AdminPermissionSeeder`'s
 *   own docblock) — reuses the exact label `AdminDashboardModules`
 *   already displays for this same real capability ("فريق الرفع"), not a
 *   new translation.
 * - `backup.*` has no real `admincp/backup/menu.php` at all (decision-log
 *   #33 — the module name/icon and the 6 individual flag labels are not
 *   real legacy UI text; `backup.php` never rendered a UI for them).
 *   Best-effort labels derived from the real category names these flags
 *   gate (`BackupApiController::CATEGORY_TABLES`) and khotab/menu.php's
 *   own real "النسخ الإحتياطي" (Backup) label for its own (excluded)
 *   `backup` key — reasonable, not `SOURCE_CONFIRMED` the way the other
 *   23 permission labels are.
 */
class PermissionLabels
{
    /** @var array<string, string> module key => real legacy Arabic module name */
    private const MODULE_NAMES = [
        'survey' => 'الاستبيانات',
        'chat' => 'غرفة الهداية',
        'authors' => 'المشرفين',
        'locations' => 'مساجد و أماكن',
        'questionnaire' => 'الاستبيان',
        'soundcloud' => 'ساوندكلاود',
        'youtube' => 'اليوتيوب',
        'broadcasting' => 'البث المباشر',
        'khotab' => 'المرئيات',
        'telawah' => 'التلاوات',
        'backup' => 'النسخ الاحتياطي',
        // OLD Admin Migration Phase 0. `admincp/` has no `anasheed`
        // directory, so there is no legacy `['arabic']` to read — same
        // not-SOURCE_CONFIRMED situation as `backup` above. This label is
        // taken from the one place real legacy UI text does name the
        // module: `khotab/menu.php`'s own `backupanasheed` label, already
        // reproduced below as `backup.backupanasheed`.
        'anasheed' => 'الأناشيد',
        // OLD Admin Migration Phase 4 — Gen 1 `Surveys` admin title
        // (`_POLLSADMIN`, `modules/Surveys/admin/language/lang-arabic.php`).
        'polls' => 'التحكم في الإستفتاءات',
        // OLD Admin Migration Phase 6 — OLD's own admin menu label for
        // W2ACDMain (`modules/w2acd/admin/links.php`).
        'w2acd' => 'الاسطوانات الدعوية',
        // OLD Admin Migration Phase 7 — `_DOWNLOADSMAIN` names it
        // «صفحة الموبايل الرئيسية»; the section is قسم الموبايل.
        'mobile' => 'قسم الموبايل',
        // F01 / B6a — OLD's own admin menu label (`modules/ads/admin/links.php`).
        'ads' => 'الإعلانات',
        // F01 / B6b — OLD's own admin menu labels.
        'banners_ads' => 'إعلانات بنرات المواد',
        'report_ads' => 'الإبلاغ عن اعلانات غير لائقة',
        // OLD public admin parity — Fatwa (2026-10-30): OLD's own admin menu
        // label (`modules/Fatwa/admin/links.php`).
        'fatwa' => 'الفتـــاوي',
    ];

    /** @var array<string, string> module key => real legacy simple-line-icons class */
    private const MODULE_ICONS = [
        'survey' => 'icon-calculator',
        'chat' => 'icon-bubble',
        'authors' => 'icon-user',
        'locations' => 'icon-pointer',
        'questionnaire' => 'icon-calculator',
        'soundcloud' => 'icon-earphones-alt',
        'youtube' => 'icon-earphones-alt',
        'broadcasting' => 'icon-feed',
        'khotab' => 'icon-social-youtube',
        'telawah' => 'icon-book-open',
        'backup' => 'icon-wrench',
        // No legacy `['icon']` exists for anasheed (see MODULE_NAMES).
        // Reuses khotab's media icon rather than inventing a new one.
        'anasheed' => 'icon-social-youtube',
        // No legacy icon; reuses the survey engine's.
        'polls' => 'icon-calculator',
        'w2acd' => 'icon-disc',
        'mobile' => 'icon-screen-smartphone',
        'ads' => 'icon-badge',
        'banners_ads' => 'icon-picture',
        'report_ads' => 'icon-flag',
        'fatwa' => 'icon-question',
    ];

    /** @var array<string, string> {module}.{key} => real legacy Arabic label */
    private const PERMISSION_LABELS = [
        'authors.addstuff' => 'إضافة مشرف',
        'authors.editstuff' => 'تعديل مشرف',
        'authors.deletestuff' => 'حذف مشرف',
        'authors.liststuff' => 'عرض قائمة المشرفين',
        'broadcasting.addstream' => 'اضافة البث',
        'broadcasting.editstream' => 'تعديل البث',
        'chat.listrooms' => 'إضافة غرفة',
        'chat.editroom' => 'تعديل غرفة',
        'chat.deleteroom' => 'حذف غرفة',
        'chat.listroom' => 'عرض قائمة الغرف',
        'locations.add_location' => 'إضافة و تحديث',
        'locations.del_location' => 'مسح',
        'questionnaire.listquest' => 'عرض مشارك',
        'questionnaire.deletequest' => 'حذف مشارك',
        'questionnaire.listallquest' => 'عرض قائمة المشاركين',
        'soundcloud.update_soundcloud' => 'تحديث الساوند كلاود',
        'survey.modsurvey' => 'مشرف استبيانات',
        'survey.modquestion' => 'محرر استبيانات',
        'survey.modanalysis' => 'محلل استبيانات',
        'youtube.update_youtube' => 'تحديث اليوتيوب',
        'khotab.repair' => 'صيانة الوصلات',
        'telawah.repair' => 'صيانة الوصلات',
        'telawah.editmirror' => 'تعديل وصلة بديلة',
        'telawah.delmirror' => 'حذف وصلة بديلة',
        // OLD public admin parity — Telawah. The labels of the OLD controls
        // they gate (`group_main_cp.htm`, `author_cp.htm`, `AdvancedStats()`).
        'telawah.addgroup' => 'إضافة قارىء / قسم فرعى',
        'telawah.editgroup' => 'تعديل القسم',
        'telawah.delgroup' => 'حذف القسم',
        'telawah.addtelawah' => 'إضافة تلاوة',
        'telawah.edittelawah' => 'تعديل / نقل التلاوة',
        'telawah.deltelawah' => 'مسح التلاوة',
        // OLD public admin parity — Fatwa: one module-level permission, as
        // OLD's single Fatwa admin check (owner decision 1).
        'fatwa.manage' => 'إدارة الفتاوى',
        'khotab.uploaders' => 'فريق الرفع',
        'backup.allsite' => 'كل الأقسام',
        'backup.backupkhotab' => 'المرئيات',
        'backup.backupkhotabmirror' => 'مرايا المرئيات',
        'backup.backuptelawah' => 'التلاوات',
        'backup.backupanasheed' => 'الأناشيد',
        'backup.backupanasheedmirror' => 'مرايا الأناشيد',
        // OLD Admin Migration Phase 0 — Mirror Management.
        // The Khotab three are SOURCE_CONFIRMED: the exact strings from
        // `admincp/khotab/menu.php`'s own `$authorization['khotab']`
        // array (`addmirror`/`delmirror`/`editmirror`), read directly.
        'khotab.addmirror' => 'إضافة وصلة بديلة',
        'khotab.editmirror' => 'تعديل وصلة بديلة',
        'khotab.delmirror' => 'حذف وصلة بديلة',
        // The Anasheed three have no legacy menu.php to read, so they
        // reuse the same real Khotab wording for the same capability
        // rather than introducing a second translation of it.
        'anasheed.addmirror' => 'إضافة وصلة بديلة',
        'anasheed.editmirror' => 'تعديل وصلة بديلة',
        'anasheed.delmirror' => 'حذف وصلة بديلة',
        // OLD Admin Migration Phase 3 — Category Assignment. Khotab's is
        // SOURCE_CONFIRMED (`admincp/khotab/menu.php`); Anasheed reuses it.
        'khotab.addcat' => 'اضافة تصنيف',
        'anasheed.addcat' => 'اضافة تصنيف',
        // OLD public admin parity — Anasheed group page. The labels of the
        // OLD controls they gate (`group_cp.htm`); no legacy menu.php exists.
        'anasheed.addicon' => 'اضافة صورة للقسم',
        'anasheed.editgroup' => 'تعديل القسم',
        'anasheed.addanasheed' => 'إضافة مادة',
        'anasheed.addgroup' => 'إضافة قسم فرعي',
        'anasheed.delgroup' => 'حذف القسم',
        // OLD public admin parity — Anasheed item page. The labels of the OLD
        // controls they gate (`item_cp.htm`, `comment_admin.htm`, `Comments()`).
        'anasheed.addbanner' => 'اضافة صورة للمادة',
        'anasheed.editanasheed' => 'تعديل المادة',
        'anasheed.delanasheed' => 'حذف المادة',
        'anasheed.addcomment' => 'إضافة تعليق إداري / تعديل ونشر التعليقات',
        'anasheed.delcomment' => 'مسح (إخفاء) التعليقات',
        // OLD Admin Migration Phase 4 — `_POLLSADMIN`, the OLD module title.
        'polls.managepolls' => 'التحكم في الإستفتاءات',
        // OLD Admin Migration Phase 5 — SOURCE_CONFIRMED,
        // `admincp/khotab/menu.php:85`.
        'khotab.addadv' => 'إضافة ملفات دعائية',
        // OLD public admin parity — Khotab group page. SOURCE_CONFIRMED,
        // `admincp/khotab/menu.php`'s own `$authorization['khotab']`.
        'khotab.editgroup' => 'تعديل مجموعة',
        'khotab.delgroup' => 'حذف مجموعة',
        'khotab.addseries' => 'إضافة سلسلة',
        'khotab.editseries' => 'تعديل سلسلة',
        'khotab.delseries' => 'حذف سلسلة',
        'khotab.additem' => 'إضافة مادة',
        'khotab.sortitems' => 'ترتيب المواد',
        'khotab.moveitem' => 'نقل مادة',
        // OLD public admin parity — Khotab item page. SOURCE_CONFIRMED,
        // `admincp/khotab/menu.php`'s own `$authorization['khotab']`.
        'khotab.edititem' => 'تعديل مادة',
        'khotab.delitem' => 'حذف مادة',
        'khotab.uploadbanner' => 'رفع بانر',
        'khotab.requestbanner' => 'طلب بانر',
        'khotab.uploadpdf' => 'رفع ملف التفريغ',
        'khotab.adddocx' => 'رفع ملف وورد',
        'khotab.deletedocx' => 'حذف ملف وورد',
        // OLD `nuke_islamic_mod.audio` / `vedio` (KhotabMediaScopeConstraint).
        'khotab.media_audio' => 'قسم الصوتيات',
        'khotab.media_video' => 'قسم المرئيات',
        'khotab.approvecomment' => 'مراجعة التعليقات',
        'khotab.delcomment' => 'حذف تعليقات',
        // OLD public admin parity — Khotab author page. SOURCE_CONFIRMED,
        // `admincp/khotab/menu.php`'s own `$authorization['khotab']`.
        'khotab.editauthor' => 'تعديل شيخ',
        'khotab.delauthor' => 'حذف شيخ',
        'khotab.addgroup' => 'إضافة مجموعة',
        'khotab.addauthor' => 'إضافة شيخ',
        // OLD Admin Migration Phase 6 — the OLD module's menu label.
        'w2acd.managecds' => 'الاسطوانات الدعوية',
        'mobile.managedownloads' => 'إدارة قسم الموبايل',
        // F01 / B6a — one key for OLD's single module-level check.
        'ads.manageads' => 'إدارة الإعلانات',
        // F01 / B6b — one key per OLD module (each had its own module-level check).
        'banners_ads.managebanners' => 'إدارة إعلانات بنرات المواد',
        'report_ads.managereports' => 'إدارة الإبلاغات عن الإعلانات',
    ];

    public static function moduleName(string $module): string
    {
        return self::MODULE_NAMES[$module] ?? $module;
    }

    public static function moduleIcon(string $module): string
    {
        return self::MODULE_ICONS[$module] ?? 'icon-settings';
    }

    public static function permissionLabel(string $permissionName): string
    {
        return self::PERMISSION_LABELS[$permissionName] ?? $permissionName;
    }
}
