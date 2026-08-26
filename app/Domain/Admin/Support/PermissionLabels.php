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
        'khotab.uploaders' => 'فريق الرفع',
        'backup.allsite' => 'كل الأقسام',
        'backup.backupkhotab' => 'المرئيات',
        'backup.backupkhotabmirror' => 'مرايا المرئيات',
        'backup.backuptelawah' => 'التلاوات',
        'backup.backupanasheed' => 'الأناشيد',
        'backup.backupanasheedmirror' => 'مرايا الأناشيد',
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
