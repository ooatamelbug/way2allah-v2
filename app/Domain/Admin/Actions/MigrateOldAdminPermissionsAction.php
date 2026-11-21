<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminDeactivation;
use App\Domain\Admin\Support\Constraints\KhotabMediaScopeConstraint;
use App\Support\Permission\Permission;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-time migration of OLD administrator permissions to Laravel (2026-11-17).
 *
 * Sources (read only): `nuke_modules.admins` (the module gate — OLD compared names exactly), the per-action grant
 * tables `nuke_islamic_mod` / `nuke_anasheed_mod` / `nuke_telawah_mod` (OLD's stored per-moderator tokens; OLD's
 * runtime check `GreenZone()` was disabled, so these rows are the only record of each moderator's intended actions),
 * and the seeded Laravel permissions. Nothing in OLD is written.
 *
 * Rules:
 * - **Additive only.** A missing mapped key is granted; nothing is ever revoked, so Gen 2 / Laravel-only grants stay.
 * - **Super-admins are untouched** (legacy `radminsuper` or the Spatie role) — their role already covers everything.
 * - **Deactivated admins are skipped** (as `admin:sync-permissions` does).
 * - **Per-action modules** (Khotab, Anasheed, Telawah): an admin in the module list gets only the keys whose OLD token
 *   is set on its active `*_mod` row; in the list without a row → nothing, reported.
 * - **Khotab media scope**: OLD's `AvAdmin()` restricted item / series / group / mirror-edit operations to the audio
 *   and / or video section (`audio`, `vedio`). The flags become `khotab.media_audio` / `khotab.media_video`, which
 *   KhotabMediaScopeConstraint enforces per record. A moderator with neither section gets no section-scoped key.
 * - **Single-gate modules** (OLD checked the module list only, and Laravel's route documentation says so): one key each.
 * - **Ambiguous or unmapped** OLD modules and tokens are never granted; they are reported.
 * - **Idempotent:** a second run proposes nothing.
 */
final class MigrateOldAdminPermissionsAction
{
    /** OLD `nuke_islamic_mod` token → Laravel key (OLD function gated by the token → Laravel route gated by the key). */
    public const KHOTAB_TOKENS = [
        'addauthor' => 'khotab.addauthor', 'editauthor' => 'khotab.editauthor', 'delauthor' => 'khotab.delauthor',
        'addgroup' => 'khotab.addgroup', 'editgroup' => 'khotab.editgroup', 'delgroup' => 'khotab.delgroup',
        'addseries' => 'khotab.addseries', 'editseries' => 'khotab.editseries', 'delseries' => 'khotab.delseries',
        'addkhotab' => 'khotab.additem', 'editkhotab' => 'khotab.edititem', 'movekhotab' => 'khotab.moveitem', 'delkhotab' => 'khotab.delitem',
        'addmirror' => 'khotab.addmirror', 'editmirror' => 'khotab.editmirror', 'delmirror' => 'khotab.delmirror',
        'addbanner' => 'khotab.uploadbanner', 'requestbanner' => 'khotab.requestbanner',
        'addcomment' => 'khotab.approvecomment', 'delcomment' => 'khotab.delcomment',
        'rearrange' => 'khotab.sortitems', 'addpdf' => 'khotab.uploadpdf', 'adddocx' => 'khotab.adddocx', 'deletedocx' => 'khotab.deletedocx',
        // OLD's label for `uploader` is «صيانة الوصلات» (link maintenance) — Gen 2's and Laravel's `khotab.repair`.
        'uploader' => 'khotab.repair',
    ];

    /** Khotab keys OLD's `AvAdmin()` scoped to the audio / video section. */
    public const KHOTAB_MEDIA_SCOPED = KhotabMediaScopeConstraint::SCOPED;

    /**
     * Tokens with no confirmed Laravel equivalent (never granted; reported when set):
     * `downloader` («النسخ الإحتياطي»: Laravel splits backup into `backup.backupkhotab` / `backupkhotabmirror`, and OLD
     * gated no operation with it); `add_tree` («اضافة تصنيف»: OLD's category-tree add / edit / delete — Laravel has
     * category assignment only, no tree management).
     * `AdvFile` («إضافة ملفات دعائية») needs no key: Laravel lets every admin submit an advfile request (owner-approved,
     * OLD's gate was a no-op), so it is neither granted nor reported.
     */
    public const KHOTAB_UNMAPPED = ['downloader', 'add_tree'];

    public const ANASHEED_TOKENS = [
        'addgroup' => 'anasheed.addgroup', 'editgroup' => 'anasheed.editgroup', 'delgroup' => 'anasheed.delgroup',
        'addanasheed' => 'anasheed.addanasheed', 'editanasheed' => 'anasheed.editanasheed', 'delanasheed' => 'anasheed.delanasheed',
        'addbanner' => 'anasheed.addbanner', 'addicon' => 'anasheed.addicon',
        'addcomment' => 'anasheed.addcomment', 'delcomment' => 'anasheed.delcomment',
        'addmirror' => 'anasheed.addmirror', 'editmirror' => 'anasheed.editmirror', 'delmirror' => 'anasheed.delmirror',
    ];

    public const ANASHEED_UNMAPPED = ['rearrange'];

    public const TELAWAH_TOKENS = [
        'addgroup' => 'telawah.addgroup', 'editgroup' => 'telawah.editgroup', 'delgroup' => 'telawah.delgroup',
        'addtelawah' => 'telawah.addtelawah', 'edittelawah' => 'telawah.edittelawah', 'deltelawah' => 'telawah.deltelawah',
    ];

    public const TELAWAH_UNMAPPED = ['addbanner', 'addcomment', 'delcomment'];

    /** OLD modules gated by the module list alone → the one Laravel key that replaced that gate. */
    public const SINGLE_GATE = [
        'Fatwa' => ['fatwa.manage'],
        'Mobile' => ['mobile.managedownloads'],
        'ads' => ['ads.manageads'],
        'w2acd' => ['w2acd.managecds'],
        'Surveys' => ['polls.managepolls'],
        'advfiles' => ['khotab.addadv'],
        'Cat' => ['khotab.addcat', 'anasheed.addcat'],
    ];

    /** OLD modules with a plausible but unconfirmed Laravel equivalent: never granted, reported. */
    public const AMBIGUOUS = ['w2a_cd', 'banners_ads', 'report_ads', 'BackUp', 'questionnaire', 'live', 'update_khotab_count', 'docx', 'chat_server', 'Classificate_anasheed'];

    private const PER_ACTION = [
        'Khotab' => ['table' => 'nuke_islamic_mod', 'tokens' => self::KHOTAB_TOKENS, 'unmapped' => self::KHOTAB_UNMAPPED],
        'Anasheed' => ['table' => 'nuke_anasheed_mod', 'tokens' => self::ANASHEED_TOKENS, 'unmapped' => self::ANASHEED_UNMAPPED],
        'Telawah' => ['table' => 'nuke_telawah_mod', 'tokens' => self::TELAWAH_TOKENS, 'unmapped' => self::TELAWAH_UNMAPPED],
    ];

    public function __construct(private readonly AdminAuditRecorder $audit) {}

    /**
     * @return array{admins: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function plan(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $known = Permission::where('guard_name', 'admin')->pluck('name')->all();
        $deactivated = AdminDeactivation::deactivatedIds();
        $main = DB::connection('main');

        $lists = [];
        foreach ($main->table('nuke_modules')->get(['title', 'admins']) as $m) {
            $lists[(string) $m->title] = array_values(array_filter(explode(',', (string) $m->admins), fn ($n) => trim($n) !== ''));
        }

        $authors = AdminUser::on('main')->orderBy('id')->get();
        $fold = static fn (string $v): string => mb_strtolower(rtrim($v, ' '), 'UTF-8');

        // Per-action rows, active only, matched to exactly one author by login name (OLD looked rows up by aid under a
        // case-insensitive collation; OLD's moderator sync wrote names, so the name is accepted too).
        $rows = [];
        foreach (self::PER_ACTION as $module => $def) {
            foreach ($main->table($def['table'])->where('active', 1)->get() as $row) {
                $key = $fold((string) $row->aid);
                $matches = $authors->filter(fn (AdminUser $a) => $fold((string) $a->aid) === $key || $fold((string) $a->name) === $key);
                $rows[$module][] = ['row' => (array) $row, 'author_ids' => $matches->pluck('id')->map(fn ($i) => (int) $i)->all()];
            }
        }

        $admins = [];
        $totals = ['admins_considered' => 0, 'admins_with_changes' => 0, 'grants_proposed' => 0, 'skipped_super_admin' => 0, 'skipped_deactivated' => 0, 'unresolved_items' => 0];

        foreach ($authors as $a) {
            $id = (int) $a->id;
            $memberOf = array_keys(array_filter($lists, fn (array $names) => in_array((string) $a->name, $names, true)));
            if ($memberOf === []) {
                continue;
            }
            if ((int) $a->radminsuper === 1 || $a->hasRole('super-admin')) {
                $totals['skipped_super_admin']++;

                continue;
            }
            if (in_array($id, $deactivated, true)) {
                $totals['skipped_deactivated']++;
                $admins[] = ['id' => $id, 'skipped' => 'deactivated', 'old_modules' => $memberOf];

                continue;
            }
            $totals['admins_considered']++;

            $intended = [];
            $unresolved = [];
            foreach ($memberOf as $module) {
                if (isset(self::SINGLE_GATE[$module])) {
                    array_push($intended, ...self::SINGLE_GATE[$module]);
                } elseif (isset(self::PER_ACTION[$module])) {
                    $def = self::PER_ACTION[$module];
                    $own = array_values(array_filter($rows[$module] ?? [], fn ($r) => in_array($id, $r['author_ids'], true)));
                    if (count($own) !== 1 || count($own[0]['author_ids']) !== 1) {
                        $unresolved[] = $module.': '.(count($own) === 0 ? 'in the module list but no active per-action row' : 'per-action row ambiguous');

                        continue;
                    }
                    $row = array_change_key_case($own[0]['row'], CASE_LOWER);
                    $keys = [];
                    foreach ($def['tokens'] as $token => $key) {
                        if ((int) ($row[strtolower($token)] ?? 0) === 1) {
                            $keys[] = $key;
                        }
                    }
                    foreach ($def['unmapped'] as $token) {
                        if ((int) ($row[strtolower($token)] ?? 0) === 1) {
                            $unresolved[] = $module.': token `'.$token.'` has no confirmed Laravel equivalent';
                        }
                    }
                    if ($module === 'Khotab') {
                        // OLD's AvAdmin() media scope → the two scope permissions KhotabMediaScopeConstraint enforces.
                        $audio = (int) ($row['audio'] ?? 0) === 1;
                        $video = (int) ($row['vedio'] ?? 0) === 1;
                        if ($audio) {
                            $keys[] = KhotabMediaScopeConstraint::AUDIO;
                        }
                        if ($video) {
                            $keys[] = KhotabMediaScopeConstraint::VIDEO;
                        }
                        if (! $audio && ! $video) {
                            // No section in OLD: every scoped operation was refused. Not granted (they would still reach
                            // record-less pages gated by the same keys), and reported.
                            $withheld = array_values(array_intersect($keys, self::KHOTAB_MEDIA_SCOPED));
                            if ($withheld !== []) {
                                $unresolved[] = 'Khotab: no media section in OLD — section-scoped keys not granted: '.implode(' ', $withheld);
                                $keys = array_values(array_diff($keys, $withheld));
                            }
                        }
                    }
                    array_push($intended, ...$keys);
                } elseif (in_array($module, self::AMBIGUOUS, true)) {
                    $unresolved[] = $module.': ambiguous OLD module — not mapped';
                }
                // Any other OLD module has no Laravel admin (retired / not implemented): nothing to map.
            }

            $intended = array_values(array_unique($intended));
            sort($intended);
            $missingDefinitions = array_values(array_diff($intended, $known));
            foreach ($missingDefinitions as $key) {
                $unresolved[] = 'Laravel permission `'.$key.'` is not seeded — run AdminPermissionSeeder first';
            }
            $intended = array_values(array_diff($intended, $missingDefinitions));
            $held = $a->getPermissionNames()->all();
            $grant = array_values(array_diff($intended, $held));

            $totals['grants_proposed'] += count($grant);
            $totals['admins_with_changes'] += $grant === [] ? 0 : 1;
            $totals['unresolved_items'] += count($unresolved);
            $admins[] = [
                'id' => $id,
                'old_modules' => $memberOf,
                'intended' => $intended,
                'already_held' => array_values(array_intersect($intended, $held)),
                'grant' => $grant,
                'kept_other_laravel_grants' => array_values(array_diff($held, $intended)),
                'unresolved' => $unresolved,
            ];
        }

        return ['admins' => $admins, 'totals' => $totals];
    }

    /**
     * Apply the plan: grant only, audited per admin (`migrate_old_permissions`). Returns the plan that was applied.
     *
     * @return array{admins: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function apply(AdminUser $actor, ?string $ip = null): array
    {
        $plan = $this->plan();
        foreach ($plan['admins'] as $entry) {
            if (($entry['grant'] ?? []) === []) {
                continue;
            }
            $admin = AdminUser::on('main')->findOrFail($entry['id']);
            $admin->givePermissionTo($entry['grant']);
            $this->audit->record($actor, 'migrate_old_permissions', 'authors', 'admin', (int) $entry['id'], [
                'granted' => $entry['grant'],
                'old_modules' => $entry['old_modules'],
            ], $ip);
        }

        return $plan;
    }
}
