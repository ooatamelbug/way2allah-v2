<?php

use App\Domain\Admin\Actions\Staff\DeactivateAdminAction;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminOwners;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * Administrator login-name rename — OLD `mod_authors` → UpdateAuthor `aid` (2026-11-17). Owner-only, switch-gated
 * (`admin_rename.enabled`, off by default because a rename breaks the account in OLD while OLD still runs).
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'admin_account_states' => MainSchema::adminAccountStates(),
        'admin_audit_log' => MainSchema::adminAuditLog(),
        'nuke_modules' => MainSchema::nukeModules(),
        'nuke_backup_sessions' => MainSchema::nukeBackupSessions(),
        // The real columns OLD's rename touches (`nuke_stories.aid` varchar(30), `informant` varchar(20)).
        'nuke_stories' => function (Blueprint $table) {
            $table->increments('sid');
            $table->string('aid', 30)->default('');
            $table->string('informant', 20)->default('');
            $table->string('title')->default('');
        },
    ]);
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
    config(['admin_deactivation.enabled' => true, 'admin_rename.enabled' => true]);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
    Permission::firstOrCreate(['name' => 'fatwa.manage', 'guard_name' => 'admin']);
});

function rnAdmin(string $aid, bool $super = false, array $attributes = []): AdminUser
{
    $admin = AdminUser::on('main')->create(array_merge([
        'aid' => $aid, 'uid' => crc32($aid) % 100000 + 1, 'name' => $aid.'-name', 'email' => $aid.'@test.invalid',
        'url' => 'https://example.invalid/'.$aid, 'password' => Hash::make($aid.'-pass'), 'radminsuper' => $super,
    ], $attributes));
    if ($super) {
        $admin->assignRole('super-admin');
    }

    return $admin;
}

/** OLD's owner rule: radminsuper 1 + the literal owner login name `Admin` (stored lower-case, as in the local data). */
function rnOwner(): AdminUser
{
    return rnAdmin('admin', true);
}

it('an owner renames a regular admin: login name changes, everything else is preserved, stories re-attributed as OLD did, audited', function () {
    $owner = rnOwner();
    $target = rnAdmin('oldname');
    $target->givePermissionTo('fatwa.manage');
    DB::connection('main')->table('nuke_stories')->insert([
        ['aid' => 'oldname', 'informant' => 'oldname', 'title' => 'a'],
        ['aid' => 'oldname', 'informant' => 'visitor', 'title' => 'b'],
        ['aid' => 'someone', 'informant' => 'oldname', 'title' => 'c'],
    ]);
    $before = AdminUser::on('main')->find($target->id)->getAttributes();

    $this->actingAs($owner, 'admin')->put(route('admin.staff.rename', $target), ['aid' => 'newname'])
        ->assertRedirect(route('admin.permissions.edit', $target))->assertSessionHas('success');

    $after = AdminUser::on('main')->find($target->id);
    expect($after->aid)->toBe('newname')
        ->and(array_diff_key($after->getAttributes(), ['aid' => 1]))->toBe(array_diff_key($before, ['aid' => 1]))
        ->and($after->getPermissionNames()->all())->toBe(['fatwa.manage'])
        ->and(DB::connection('main')->table('nuke_stories')->orderBy('sid')->get(['aid', 'informant'])->map(fn ($r) => [$r->aid, $r->informant])->all())
        ->toBe([['newname', 'newname'], ['newname', 'visitor'], ['someone', 'oldname']]);

    $audit = DB::connection('main')->table('admin_audit_log')->where('action', 'rename_admin')->get();
    expect($audit)->toHaveCount(1)
        ->and(json_decode((string) $audit[0]->metadata, true))->toEqual(['new_aid' => 'newname', 'old_aid' => 'oldname', 'stories_reattributed' => 2]);
});

it('the renamed admin logs in with the new name only; its existing audit history stays attached by id', function () {
    $owner = rnOwner();
    $target = rnAdmin('oldname');
    app(AdminAuditRecorder::class)->record($target, 'some_earlier_action', 'authors', 'admin', 1, []);

    $this->actingAs($owner, 'admin')->put(route('admin.staff.rename', $target), ['aid' => 'newname'])->assertSessionHas('success');
    app('auth')->forgetGuards();
    $this->post(route('admin.logout'));
    app('auth')->forgetGuards();

    $this->post(route('admin.login'), ['aid' => 'oldname', 'password' => 'oldname-pass'])->assertSessionHasErrors('aid');
    app('auth')->forgetGuards();
    $this->post(route('admin.login'), ['aid' => 'newname', 'password' => 'oldname-pass'])->assertSessionHasNoErrors();
    expect(auth('admin')->id())->toBe((int) $target->id)
        ->and(DB::connection('main')->table('admin_audit_log')->where('actor_admin_id', $target->id)->where('action', 'some_earlier_action')->count())->toBe(1);
});

it('duplicates are refused case- and trailing-space-insensitively (OLD collation), and another forum member\'s username is refused', function () {
    $owner = rnOwner();
    rnAdmin('Taken');
    $target = rnAdmin('target');
    DB::connection('vbulletin')->table('user')->insert(['userid' => 777, 'username' => 'forumguy', 'email' => 'f@example.com']);

    $this->actingAs($owner, 'admin');
    foreach (['taken', 'TAKEN ', 'Taken'] as $name) {
        $this->put(route('admin.staff.rename', $target), ['aid' => $name])->assertSessionHas('error', 'يوجد مشرف آخر بنفس اسم الدخول.');
    }
    $this->put(route('admin.staff.rename', $target), ['aid' => 'forumguy'])->assertSessionHas('error', 'هذه الكنية اسم مستخدم لعضو آخر بالمنتدى؛ اختر كنية أخرى.');
    $this->put(route('admin.staff.rename', $target), ['aid' => str_repeat('x', 26)])->assertSessionHasErrors('aid');
    $this->put(route('admin.staff.rename', $target), ['aid' => '<b>x</b>'])->assertSessionHasErrors('aid');

    expect(AdminUser::on('main')->find($target->id)->aid)->toBe('target');
});

it('no escalation and protected accounts: owner-only; reserved owner names refused; an owner by login name is never renamed; Good can be renamed only by an owner', function () {
    $owner = rnOwner();
    $owner2 = rnAdmin('مؤمن يحب الله ورسوله', true);
    $good = rnAdmin('goodacct', true, ['name' => 'Good']);
    $boss = rnAdmin('boss', true);
    $target = rnAdmin('target');
    $regular = rnAdmin('regular');

    $this->actingAs($boss, 'admin')->put(route('admin.staff.rename', $target), ['aid' => 'x1'])->assertSessionHas('error', 'تعديل اسم الدخول متاح لحسابات الملاك فقط.');
    $this->actingAs($regular, 'admin')->put(route('admin.staff.rename', $regular), ['aid' => 'Admin'])->assertForbidden();

    app('auth')->forgetGuards();
    $this->actingAs($owner, 'admin');
    foreach (['Admin', 'ADMIN', 'مؤمن يحب الله ورسوله', 'good'] as $reserved) {
        $this->put(route('admin.staff.rename', $target), ['aid' => $reserved])->assertSessionHas('error', 'هذه الكنية أو هذا الإسم محجوز لحسابات الملاك؛ اختر غيره.');
    }
    $this->put(route('admin.staff.rename', $owner2), ['aid' => 'x2'])->assertSessionHas('error', 'لا يمكن تعديل اسم الدخول لحساب مالك؛ صلاحيته مرتبطة بهذا الاسم.');
    $this->put(route('admin.staff.rename', $owner), ['aid' => 'x3'])->assertSessionHas('error', 'لا يمكن تعديل اسم الدخول لحساب مالك؛ صلاحيته مرتبطة بهذا الاسم.');
    // `Good` is protected by its name, which a login rename does not touch (OLD allowed editing its aid).
    $this->put(route('admin.staff.rename', $good), ['aid' => 'good2'])->assertSessionHas('success');

    expect(AdminUser::on('main')->find($owner2->id)->aid)->toBe('مؤمن يحب الله ورسوله')
        ->and(AdminUser::on('main')->find($target->id)->aid)->toBe('target')
        ->and(AdminOwners::isProtectedMainAccount(AdminUser::on('main')->find($good->id)))->toBeTrue()
        ->and(AdminOwners::isOwner(AdminUser::on('main')->find($good->id)))->toBeTrue();
});

it('a deactivated admin is not renamed; informant overflow (varchar 20) is refused before any write', function () {
    $owner = rnOwner();
    $target = rnAdmin('target');
    $writer = rnAdmin('writer');
    DB::connection('main')->table('nuke_stories')->insert(['aid' => 'writer', 'informant' => 'writer', 'title' => 'a']);
    app(DeactivateAdminAction::class)->execute($owner, $target, 'r');

    $this->actingAs($owner, 'admin');
    $this->put(route('admin.staff.rename', $target), ['aid' => 'other'])->assertSessionHas('error', 'هذا المشرف معطّل؛ أعد تفعيله أولاً.');
    $this->put(route('admin.staff.rename', $writer), ['aid' => str_repeat('w', 21)])->assertSessionHas('error');

    expect(AdminUser::on('main')->find($writer->id)->aid)->toBe('writer')
        ->and(DB::connection('main')->table('nuke_stories')->value('aid'))->toBe('writer');
});

it('switch OFF (default): the route is 404, the editor has no rename form, nothing changes', function () {
    config(['admin_rename.enabled' => false]);
    $owner = rnOwner();
    $target = rnAdmin('target');

    $this->actingAs($owner, 'admin')->put(route('admin.staff.rename', $target), ['aid' => 'newname'])->assertNotFound();
    expect($this->get(route('admin.permissions.edit', $target))->assertOk()->getContent())->not->toContain(route('admin.staff.rename', $target))
        ->and(AdminUser::on('main')->find($target->id)->aid)->toBe('target');

    config(['admin_rename.enabled' => true]);
    expect($this->get(route('admin.permissions.edit', $target))->getContent())->toContain(route('admin.staff.rename', $target));
});
