<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminUser;

/**
 * `/admincp/` Login + Dashboard Completion (owner decision, 2026-08-22,
 * superseding Wave 5's "no dashboard nav" exclusion — decision-log entry
 * recording this). Derives the dashboard's module list directly from
 * `routes/admin.php`'s own already-registered routes/permission gates —
 * not a re-typed copy of the prompt's investigation leads. Each entry's
 * `permissions` array matches that module's own index route's
 * `admin.permission:...` middleware argument exactly (comma-separated
 * "any of" semantics in the middleware become an array here, checked via
 * `hasAnyPermission()`, the same OR semantics).
 *
 * **Grouped (parent + `children`) entries (AdminCP Production-vs-Laravel
 * Screenshot Visual Comparison, 2026-08-23):** a real production screenshot
 * showed the sidebar rendering with real Metronic expand arrows/sub-menus
 * that this flat list never reproduced — traced to `admincp/sidebar.php`'s
 * own real logic: any module whose `menu.php` defines a non-empty
 * `$modulelinks` array renders as an expandable parent (`<a
 * href="javascript:;">` + `<span class="arrow">` + `<ul class="sub-menu">`),
 * not a flat direct link; only a module with zero `$modulelinks` renders
 * flat. Every module's real `$modulelinks` was re-read fresh from its own
 * `menu.php` before grouping anything — `survey`/`soundcloud`/`youtube`/
 * `questionnaire` have zero entries (confirmed flat in legacy too, left
 * flat here, not a gap). `locations` (2 links: list/add), `authors`/staff
 * (2: add/list), `khotab`+`telawah` (both real, in-scope sub-capabilities
 * grouped under their own module), and `broadcasting` (1 link) have real,
 * non-empty `$modulelinks` and are grouped below using ONLY already-existing
 * Laravel routes — no new route, controller, or content-CRUD capability
 * was added to make this grouping possible.
 *
 * **Order and labels corrected 2026-08-26 (owner-supplied real sidebar
 * screenshot, same-day continuation of the reversal above):** `sidebar.php`
 * iterates `glob($current_file_path.'/*')` — PHP's default alphabetical
 * sort, not a curated priority list — so real legacy's own sidebar order is
 * simply each real `admincp/` subdirectory's name, alphabetically:
 * `authors`, `broadcasting`, `khotab`, `locations`, `questionnaire`,
 * `soundcloud`, `survey`, `telawah`, `youtube` (`chat` excluded per the
 * owner's own `CHAT_ROOM_ADMIN = REMOVE` decision, unaffected by this
 * reordering). `MODULES` below now matches that order exactly, confirmed
 * against the real screenshot's own visible sequence. `authors`'s real
 * `menu.php` arabic name is `المشرفين`, not `فريق الإدارة` (the label this
 * class previously used, inherited from an earlier, unrelated dashboard
 * entry) — corrected to the real source value.
 *
 * **`broadcasting`'s single real `$modulelinks` entry hardcodes a specific
 * legacy channel id** (`edit_stream.php?...&id=51`, a legacy quirk, not a
 * reusable pattern) — its child link below points to the real
 * `admin.broadcasting.index` listing page instead (a strictly better,
 * already-existing replacement), not the literal hardcoded-id target; only
 * the real grouping/chevron presence is reproduced, not the broken link.
 *
 * **Known, disclosed, NOT-implemented gap:** the real production sidebar
 * also shows a `النسخ الاحتياطي` (backup) group, alphabetically between
 * `authors` and `broadcasting` — its `admincp/backup/` directory does not
 * exist anywhere in the current `legacy-project` reference snapshot (a
 * repo-wide `find` confirms this), so its real `menu.php`/`$modulelinks`
 * cannot be read, and no Laravel admin page for it was ever built (the
 * real backup capability is the root `/backup.php` machine API,
 * decision-log #33 — not a browser page). Not fabricated here without
 * real source; flagged, not silently reproduced or ignored.
 *
 * One real, already-migrated capability is deliberately NOT listed here,
 * not by oversight:
 * - `admin.permissions.*` (the permission editor) — already reachable via
 *   the Staff module's own per-row "تعديل الصلاحيات" link
 *   (`resources/views/admin/staff/index.blade.php`), and gated by
 *   `admin.role:super-admin`, a ROLE check, not a permission check this
 *   class's generic permission-driven filtering can correctly express.
 *   Giving it its own top-level dashboard entry here would need an
 *   invented, unproven permission key — not done (per explicit
 *   instruction not to invent permission keys).
 *
 * `admin.broadcasting.index` (Admin Broadcasting Final Closure task,
 * 2026-08-22) IS listed below — legacy `index.php?op=editstream`'s
 * channel-list branch was source-confirmed real and functional (not
 * demo/dead), so its Laravel equivalent was built and is a genuine
 * dashboard entry, not a workaround.
 *
 * `admin.chat.index` ("الغرف الصوتية") was removed (Final Migration
 * Owner-Decision Closure, 2026-08-23) — the FlashChat live-room feature
 * it administered is retired with no replacement (Business Confirmation
 * #4), and the owner decided `CHAT_ROOM_ADMIN = REMOVE`. Do not re-add
 * without a new owner decision reversing that closure.
 *
 * `icon` (AdminCP Full Visual/Layout Parity Reconstruction, 2026-08-22)
 * is the real simple-line-icons class from that module's own legacy
 * `admincp/{dir}/menu.php` (`${$module}['icon']`) wherever a module maps
 * 1:1 to a real legacy admincp subdirectory — re-read fresh from each
 * menu.php, not guessed. The 2 remaining bare link-quality entries
 * (mirror/telawah — khotab's own is now grouped under `المرئيات` above)
 * have no legacy subdirectory of their own (Roadmap task 6.7 consolidated
 * them from khotab/telawah repair tooling that had no dedicated admincp/
 * menu.php in legacy) — `icon-wrench` is a reasonable icon choice, not a
 * reproduced legacy value; classified `NO_CANONICAL_LEGACY_VISUAL_REFERENCE`
 * in the visual-parity report, not `SOURCE_CONFIRMED`.
 */
class AdminDashboardModules
{
    /**
     * A leaf entry has `route`; a parent entry has `children` (a list of
     * leaf entries) instead and no `route` of its own — matching
     * `sidebar.php`'s own real distinction (`<a href="javascript:;">` for
     * a parent vs a real `href` for a flat or child link).
     *
     * @var list<array{label: string, icon: string, route?: string, permissions?: list<string>, children?: list<array{label: string, route: string, permissions: list<string>}>}>
     */
    private const MODULES = [
        [
            'label' => 'المشرفين', 'icon' => 'icon-user',
            'children' => [
                ['label' => 'إضافة مشرف', 'route' => 'admin.staff.create', 'permissions' => ['authors.addstuff']],
                ['label' => 'قائمة المشرفين', 'route' => 'admin.staff.index', 'permissions' => ['authors.liststuff']],
            ],
        ],
        [
            'label' => 'البث المباشر', 'icon' => 'icon-feed',
            'children' => [
                ['label' => 'تعديل بث', 'route' => 'admin.broadcasting.index', 'permissions' => ['broadcasting.editstream']],
            ],
        ],
        [
            'label' => 'المرئيات', 'icon' => 'icon-social-youtube',
            'children' => [
                ['label' => 'فريق الرفع', 'route' => 'admin.uploaders.index', 'permissions' => ['khotab.uploaders']],
                ['label' => 'جودة الروابط - المرئيات والصوتيات', 'route' => 'admin.link-quality.khotab.index', 'permissions' => ['khotab.repair']],
                ['label' => 'الملفات كبيرة الحجم', 'route' => 'admin.link-quality.khotab.large-files', 'permissions' => ['khotab.repair']],
                ['label' => 'جودة الروابط - المرايا', 'route' => 'admin.link-quality.mirror.index', 'permissions' => ['khotab.repair']],
            ],
        ],
        [
            'label' => 'مساجد و أماكن', 'icon' => 'icon-pointer',
            'children' => [
                ['label' => 'قائمة الأماكن', 'route' => 'admin.locations.index', 'permissions' => ['locations.add_location', 'locations.del_location']],
                ['label' => 'إضافة مكان جديد', 'route' => 'admin.locations.create', 'permissions' => ['locations.add_location']],
            ],
        ],
        ['label' => 'استبيان الدعاة', 'route' => 'admin.questionnaire.index', 'permissions' => ['questionnaire.listallquest', 'questionnaire.listquest'], 'icon' => 'icon-calculator'],
        ['label' => 'ساوند كلاود', 'route' => 'admin.soundcloud.edit', 'permissions' => ['soundcloud.update_soundcloud'], 'icon' => 'icon-earphones-alt'],
        ['label' => 'الاستطلاعات', 'route' => 'admin.survey.index', 'permissions' => ['survey.modsurvey'], 'icon' => 'icon-calculator'],
        [
            'label' => 'التلاوات', 'icon' => 'icon-book-open',
            'children' => [
                ['label' => 'جودة الروابط - التلاوات', 'route' => 'admin.link-quality.telawah.index', 'permissions' => ['telawah.repair']],
            ],
        ],
        ['label' => 'يوتيوب', 'route' => 'admin.youtube.edit', 'permissions' => ['youtube.update_youtube'], 'icon' => 'icon-earphones-alt'],
    ];

    /**
     * Same bypass semantics as `EnsureAdminHasPermission`: a super-admin
     * sees every module/child regardless of individually-held permissions
     * — navigation visibility here is a UX convenience only, not the
     * security boundary (every linked route independently re-enforces its
     * own `admin.role`/`admin.permission` middleware unchanged). A parent
     * is visible if the admin can see at least one of its children.
     *
     * @return list<array{label: string, icon: string, route?: string, children?: list<array{label: string, route: string}>}>
     */
    public function visibleFor(AdminUser $admin): array
    {
        $isSuperAdmin = $admin->hasRole('super-admin');

        $visible = [];
        foreach (self::MODULES as $module) {
            if (isset($module['children'])) {
                $visibleChildren = [];
                foreach ($module['children'] as $child) {
                    if ($isSuperAdmin || $admin->hasAnyPermission($child['permissions'])) {
                        $visibleChildren[] = ['label' => $child['label'], 'route' => $child['route']];
                    }
                }

                if ($visibleChildren !== []) {
                    $visible[] = ['label' => $module['label'], 'icon' => $module['icon'], 'children' => $visibleChildren];
                }

                continue;
            }

            if ($isSuperAdmin || $admin->hasAnyPermission($module['permissions'])) {
                $visible[] = ['label' => $module['label'], 'route' => $module['route'], 'icon' => $module['icon']];
            }
        }

        return $visible;
    }
}
