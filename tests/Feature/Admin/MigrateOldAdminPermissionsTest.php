<?php

use App\Domain\Admin\Actions\MigrateOldAdminPermissionsAction;
use App\Domain\Admin\Actions\Staff\DeactivateAdminAction;
use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * One-time OLD → Laravel administrator permission migration (2026-11-17): per-action tokens, media scope, single-gate
 * modules, ambiguity, super-admins, additivity and idempotence.
 */
function mopModTable(array $tokens): Closure
{
    return function (Blueprint $table) use ($tokens) {
        $table->increments('id');
        $table->string('aid', 250)->default('');
        $table->unsignedTinyInteger('active')->default(1);
        foreach ($tokens as $token) {
            $table->unsignedTinyInteger($token)->default(0);
        }
    };
}

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'admin_account_states' => MainSchema::adminAccountStates(),
        'admin_audit_log' => MainSchema::adminAuditLog(),
        'nuke_modules' => MainSchema::nukeModules(),
        'nuke_backup_sessions' => MainSchema::nukeBackupSessions(),
        // Real token columns of OLD's per-action tables.
        'nuke_islamic_mod' => mopModTable(['audio', 'vedio', 'addauthor', 'editauthor', 'delauthor', 'addgroup', 'editgroup', 'delgroup', 'addseries', 'editseries', 'delseries', 'addkhotab', 'editkhotab', 'movekhotab', 'delkhotab', 'addbanner', 'addcomment', 'delcomment', 'addmirror', 'delmirror', 'editmirror', 'AdvFile', 'uploader', 'downloader', 'rearrange', 'requestbanner', 'addpdf', 'add_tree', 'adddocx', 'deletedocx']),
        'nuke_anasheed_mod' => mopModTable(['addgroup', 'editgroup', 'delgroup', 'addanasheed', 'editanasheed', 'delanasheed', 'addbanner', 'addcomment', 'delcomment', 'addicon', 'rearrange', 'addmirror', 'editmirror', 'delmirror']),
        'nuke_telawah_mod' => mopModTable(['addgroup', 'editgroup', 'delgroup', 'addtelawah', 'edittelawah', 'deltelawah', 'addbanner', 'addcomment', 'delcomment']),
    ]);
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
    config(['admin_deactivation.enabled' => true]);
    $this->seed(AdminPermissionSeeder::class);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
});

function mopAdmin(string $aid, array $attributes = []): AdminUser
{
    return AdminUser::on('main')->create(array_merge([
        'aid' => $aid, 'uid' => crc32($aid) % 100000 + 1, 'name' => $aid, 'email' => $aid.'@test.invalid', 'password' => Hash::make('x'), 'radminsuper' => 0,
    ], $attributes));
}

/** @param array<string, list<string>> $lists module title => admin names */
function mopLists(array $lists): void
{
    foreach ($lists as $title => $names) {
        DB::connection('main')->table('nuke_modules')->insert(['title' => $title, 'admins' => implode(',', $names).',']);
    }
}

function mopPlanFor(int $id): array
{
    return collect(app(MigrateOldAdminPermissionsAction::class)->plan()['admins'])->firstWhere('id', $id) ?? [];
}

it('per-action: a Khotab moderator gets only the keys whose OLD tokens are set — never the whole module', function () {
    $a = mopAdmin('mod1');
    mopLists(['Khotab' => ['mod1']]);
    DB::connection('main')->table('nuke_islamic_mod')->insert(['aid' => 'mod1', 'audio' => 1, 'vedio' => 1, 'addkhotab' => 1, 'editkhotab' => 1, 'addcomment' => 1, 'addpdf' => 1]);

    expect(mopPlanFor($a->id)['grant'])->toBe(['khotab.additem', 'khotab.approvecomment', 'khotab.edititem', 'khotab.media_audio', 'khotab.media_video', 'khotab.uploadpdf']);
});

it('media scope: single-section moderators keep their scoped keys plus that section only; both sections get both; no section gets no scoped key', function () {
    $v = mopAdmin('videoonly');
    $au = mopAdmin('audioonly');
    $n = mopAdmin('nosection');
    mopLists(['Khotab' => ['videoonly', 'audioonly', 'nosection']]);
    DB::connection('main')->table('nuke_islamic_mod')->insert([
        ['aid' => 'videoonly', 'audio' => 0, 'vedio' => 1, 'addkhotab' => 1, 'editmirror' => 1, 'addmirror' => 1, 'addcomment' => 1, 'addbanner' => 0],
        ['aid' => 'audioonly', 'audio' => 1, 'vedio' => 0, 'addkhotab' => 1, 'editmirror' => 0, 'addmirror' => 0, 'addcomment' => 0, 'addbanner' => 0],
        ['aid' => 'nosection', 'audio' => 0, 'vedio' => 0, 'addkhotab' => 1, 'editmirror' => 0, 'addmirror' => 0, 'addcomment' => 0, 'addbanner' => 1],
    ]);

    expect(mopPlanFor($v->id)['grant'])->toBe(['khotab.additem', 'khotab.addmirror', 'khotab.approvecomment', 'khotab.editmirror', 'khotab.media_video'])
        ->and(mopPlanFor($au->id)['grant'])->toBe(['khotab.additem', 'khotab.media_audio']);
    $pn = mopPlanFor($n->id);
    expect($pn['grant'])->toBe(['khotab.uploadbanner'])
        ->and(implode("\n", $pn['unresolved']))->toContain('no media section')->toContain('khotab.additem');
});

it('Anasheed and Telawah tokens; unmapped tokens and list-without-row are reported, never granted', function () {
    $a = mopAdmin('mixed');
    $b = mopAdmin('norow');
    mopLists(['Anasheed' => ['mixed'], 'Telawah' => ['mixed', 'norow']]);
    DB::connection('main')->table('nuke_anasheed_mod')->insert(['aid' => 'mixed', 'addanasheed' => 1, 'delmirror' => 1, 'rearrange' => 1]);
    DB::connection('main')->table('nuke_telawah_mod')->insert(['aid' => 'mixed', 'edittelawah' => 1, 'addcomment' => 1]);

    $p = mopPlanFor($a->id);
    expect($p['grant'])->toBe(['anasheed.addanasheed', 'anasheed.delmirror', 'telawah.edittelawah'])
        ->and(implode("\n", $p['unresolved']))->toContain('`rearrange`')->toContain('`addcomment`');
    expect(mopPlanFor($b->id)['grant'])->toBe([])
        ->and(mopPlanFor($b->id)['unresolved'])->toBe(['Telawah: in the module list but no active per-action row']);
});

it('a per-action row without module-list membership grants nothing (OLD needed both)', function () {
    $a = mopAdmin('rowonly');
    mopLists(['Fatwa' => ['rowonly']]);
    DB::connection('main')->table('nuke_islamic_mod')->insert(['aid' => 'rowonly', 'audio' => 1, 'vedio' => 1, 'addkhotab' => 1]);

    expect(mopPlanFor($a->id)['grant'])->toBe(['fatwa.manage']);
});

it('single-gate modules map to their one key; ambiguous and retired modules grant nothing', function () {
    $a = mopAdmin('single');
    mopLists(['Fatwa' => ['single'], 'Cat' => ['single'], 'Surveys' => ['single'], 'advfiles' => ['single'], 'w2a_cd' => ['single'], 'BackUp' => ['single'], 'Montada' => ['single']]);

    $p = mopPlanFor($a->id);
    expect($p['grant'])->toBe(['anasheed.addcat', 'fatwa.manage', 'khotab.addadv', 'khotab.addcat', 'polls.managepolls'])
        ->and($p['unresolved'])->toBe(['w2a_cd: ambiguous OLD module — not mapped', 'BackUp: ambiguous OLD module — not mapped']);
});

it('super-admins (legacy flag or Laravel role) and deactivated admins are untouched', function () {
    $legacy = mopAdmin('legacysuper', ['radminsuper' => 1]);
    $role = mopAdmin('rolesuper');
    $role->assignRole('super-admin');
    $gone = mopAdmin('gone');
    mopLists(['Fatwa' => ['legacysuper', 'rolesuper', 'gone']]);
    app(DeactivateAdminAction::class)->execute($role, $gone, 'r');

    app(MigrateOldAdminPermissionsAction::class)->apply($role);

    expect($legacy->fresh()->getPermissionNames()->all())->toBe([])
        ->and($role->fresh()->getPermissionNames()->all())->toBe([])
        ->and($role->fresh()->hasRole('super-admin'))->toBeTrue()
        ->and($gone->fresh()->getPermissionNames()->all())->toBe([])
        ->and(collect(app(MigrateOldAdminPermissionsAction::class)->plan()['admins'])->firstWhere('id', $gone->id)['skipped'])->toBe('deactivated');
});

it('additive and idempotent: Laravel-only grants kept, held keys not re-granted, a second run changes nothing, one audit row per admin', function () {
    $actor = mopAdmin('actor', ['radminsuper' => 1]);
    $actor->assignRole('super-admin');
    $a = mopAdmin('keeper');
    $a->givePermissionTo(['survey.modsurvey', 'fatwa.manage']);
    mopLists(['Fatwa' => ['keeper'], 'Cat' => ['keeper']]);

    $plan = mopPlanFor($a->id);
    expect($plan['grant'])->toBe(['anasheed.addcat', 'khotab.addcat'])
        ->and($plan['already_held'])->toBe(['fatwa.manage'])
        ->and($plan['kept_other_laravel_grants'])->toBe(['survey.modsurvey']);

    app(MigrateOldAdminPermissionsAction::class)->apply($actor);
    $after1 = $a->fresh()->getPermissionNames()->sort()->values()->all();
    app(MigrateOldAdminPermissionsAction::class)->apply($actor);

    expect($a->fresh()->getPermissionNames()->sort()->values()->all())->toBe($after1)
        ->and($after1)->toBe(['anasheed.addcat', 'fatwa.manage', 'khotab.addcat', 'survey.modsurvey'])
        ->and(mopPlanFor($a->id)['grant'])->toBe([])
        ->and(DB::connection('main')->table('admin_audit_log')->where('action', 'migrate_old_permissions')->count())->toBe(1);
});

it('command: --dry-run writes no grant and produces the report; a write needs an active super-admin --actor', function () {
    $actor = mopAdmin('actor', ['radminsuper' => 1]);
    $actor->assignRole('super-admin');
    $a = mopAdmin('target');
    mopLists(['Fatwa' => ['target']]);
    $report = tempnam(sys_get_temp_dir(), 'mop');

    $this->artisan('admin:migrate-old-permissions', ['--dry-run' => true, '--report' => $report])->assertSuccessful();
    expect($a->fresh()->getPermissionNames()->all())->toBe([])
        ->and(json_decode((string) file_get_contents($report), true)['totals']['grants_proposed'])->toBe(1);

    $this->artisan('admin:migrate-old-permissions', ['--report' => $report])->assertFailed();
    $this->artisan('admin:migrate-old-permissions', ['--actor' => 'target', '--report' => $report])->assertFailed();
    expect($a->fresh()->getPermissionNames()->all())->toBe([]);

    $this->artisan('admin:migrate-old-permissions', ['--actor' => 'actor', '--report' => $report])->assertSuccessful();
    expect($a->fresh()->getPermissionNames()->all())->toBe(['fatwa.manage']);
    @unlink($report);
});
