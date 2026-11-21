<?php

use App\Domain\Admin\Actions\Staff\AdminDeactivationRefused;
use App\Domain\Admin\Actions\Staff\DeactivateAdminAction;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminOwners;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * Administrator management — OLD `admin.php?op=mod_authors` functional parity (2026-11-17): extended add form,
 * profile edit, owner-only super-admin promote / demote, and owner-account protection across the permission
 * editor, the profile form and F02 deactivation (AdminManagementPolicy).
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'admin_account_states' => MainSchema::adminAccountStates(),
        'admin_audit_log' => MainSchema::adminAuditLog(),
        'nuke_modules' => MainSchema::nukeModules(),
        'nuke_backup_sessions' => MainSchema::nukeBackupSessions(),
    ]);
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
    // Owners come from OLD's rule (config/admin_owners.php, OLD's literal values); tests add their own login names.
    config(['admin_deactivation.enabled' => true]);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
    foreach (['authors.liststuff', 'authors.addstuff', 'khotab.addkhotab', 'fatwa.manage'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
    }
});

function amAdmin(string $aid, bool $super = false, array $attributes = []): AdminUser
{
    $admin = AdminUser::on('main')->create(array_merge([
        'aid' => $aid, 'uid' => crc32($aid) % 100000 + 1, 'name' => $aid, 'email' => $aid.'@test.invalid',
        'password' => Hash::make($aid.'-pass'), 'radminsuper' => $super,
    ], $attributes));
    if ($super) {
        $admin->assignRole('super-admin');
    }

    return $admin;
}

function amOwner(AdminUser ...$admins): void
{
    // OLD's rule: radminsuper 1 AND an owner login name. The test accounts' login names are added to the list.
    config(['admin_owners.owner_aids' => array_merge(['Admin', 'مؤمن يحب الله ورسوله'], array_map(fn (AdminUser $a) => (string) $a->aid, $admins))]);
}

function amAudit(?string $action = null): array
{
    return DB::connection('main')->table('admin_audit_log')->when($action, fn ($q) => $q->where('action', $action))->orderBy('id')->get()->all();
}

function amForumUser(int $id, string $username, string $email = 'f@example.com'): void
{
    DB::connection('vbulletin')->table('user')->insert(['userid' => $id, 'username' => $username, 'email' => $email]);
}

function amFresh(): void
{
    app('auth')->forgetGuards();
}

// ---------------------------------------------------------------- create

it('create: a super-admin sets nickname, name, email, URL, an initial password and permissions; the forum link and avatar default are kept; audited without secrets', function () {
    $boss = amAdmin('boss', true);
    amForumUser(500, 'forumname', 'forum@example.com');

    $this->actingAs($boss, 'admin')->post(route('admin.staff.store'), [
        'vbuid' => '500', 'aid' => 'nick', 'name' => 'Full Name', 'email' => 'staff@example.com', 'url' => 'https://example.com/me',
        'new_password' => 'initial-pass-1', 'new_password_confirmation' => 'initial-pass-1',
        'permissions' => ['khotab.addkhotab' => '1', 'fatwa.manage' => '1'],
    ])->assertRedirect()->assertSessionHas('success');

    $admin = AdminUser::on('main')->where('uid', 500)->firstOrFail();
    expect($admin->aid)->toBe('nick')
        ->and($admin->name)->toBe('Full Name')
        ->and($admin->email)->toBe('staff@example.com')
        ->and($admin->getAttribute('url'))->toBe('https://example.com/me')
        ->and($admin->getAttribute('thumb'))->toBe('https://way2allah.com/new/login-logo.png')
        ->and((bool) $admin->radminsuper)->toBeFalse()
        ->and(Hash::check('initial-pass-1', (string) $admin->password))->toBeTrue()
        ->and($admin->getPermissionNames()->sort()->values()->all())->toBe(['fatwa.manage', 'khotab.addkhotab'])
        ->and($admin->hasRole('super-admin'))->toBeFalse();

    $audit = amAudit('create_admin');
    expect($audit)->toHaveCount(1);
    $meta = json_decode((string) $audit[0]->metadata, true);
    expect($meta['forum_uid'])->toBe(500)
        ->and($meta['nickname_is_forum_username'])->toBeFalse()
        ->and($meta['initial_password_set'])->toBeTrue()
        ->and($meta['permissions'])->toBe(['fatwa.manage', 'khotab.addkhotab'])
        ->and((string) $audit[0]->metadata)->not->toContain('initial-pass-1');
});

it('create: a vbuid-only request still defaults every field to the forum account, with a random unknown password', function () {
    $boss = amAdmin('boss', true);
    amForumUser(501, 'member501', 'm501@example.com');

    $this->actingAs($boss, 'admin')->post(route('admin.staff.store'), ['vbuid' => '501'])->assertRedirect();

    $admin = AdminUser::on('main')->where('uid', 501)->firstOrFail();
    expect([$admin->aid, $admin->name, $admin->email, (string) $admin->getAttribute('url')])->toBe(['member501', 'member501', 'm501@example.com', ''])
        ->and($admin->getPermissionNames()->all())->toBe([]);
});

it('create: refuses escalation — permissions from a non-super-admin, super-admin from a non-owner — and writes nothing', function () {
    $adder = amAdmin('adder');
    $adder->givePermissionTo('authors.addstuff');
    amForumUser(502, 'm502');

    $this->actingAs($adder, 'admin')->post(route('admin.staff.store'), ['vbuid' => '502', 'permissions' => ['fatwa.manage' => '1']])
        ->assertSessionHas('error', 'تعيين الصلاحيات متاح للمشرف العام فقط.');
    $this->actingAs($adder, 'admin')->post(route('admin.staff.store'), ['vbuid' => '502', 'make_super_admin' => '1'])
        ->assertSessionHas('error', 'إضافة مشرف عام متاحة لحسابات الملاك فقط.');

    amFresh();
    $boss = amAdmin('boss', true);
    $this->actingAs($boss, 'admin')->post(route('admin.staff.store'), ['vbuid' => '502', 'make_super_admin' => '1'])
        ->assertSessionHas('error', 'إضافة مشرف عام متاحة لحسابات الملاك فقط.');

    expect(AdminUser::on('main')->where('uid', 502)->exists())->toBeFalse()
        ->and(amAudit('create_admin'))->toBe([]);
});

it('create: an owner can create a super-admin (Laravel role only; legacy radminsuper stays 0)', function () {
    $owner = amAdmin('owner', true);
    amOwner($owner);
    amForumUser(503, 'm503');

    $this->actingAs($owner, 'admin')->post(route('admin.staff.store'), ['vbuid' => '503', 'make_super_admin' => '1'])->assertRedirect();

    $admin = AdminUser::on('main')->where('uid', 503)->firstOrFail();
    expect($admin->hasRole('super-admin'))->toBeTrue()
        ->and((int) $admin->radminsuper)->toBe(0)
        ->and(json_decode((string) amAudit('create_admin')[0]->metadata, true)['super_admin'])->toBeTrue();
});

it('create: identity rules — no duplicate nickname or name, a nickname may not be another forum member\'s username, lengths and markup refused', function () {
    $boss = amAdmin('boss', true);
    amAdmin('taken', false, ['name' => 'Taken Name']);
    amForumUser(504, 'm504');
    amForumUser(505, 'someoneelse');

    $this->actingAs($boss, 'admin');
    $this->post(route('admin.staff.store'), ['vbuid' => '504', 'aid' => 'taken'])->assertSessionHas('error', 'يوجد مشرف آخر بنفس اسم المستخدم، لا يمكن إضافة هذا العضو');
    $this->post(route('admin.staff.store'), ['vbuid' => '504', 'name' => 'Taken Name'])->assertSessionHas('error', 'يوجد مشرف آخر بنفس الإسم، اختر إسمًا آخر');
    $this->post(route('admin.staff.store'), ['vbuid' => '504', 'aid' => 'someoneelse'])->assertSessionHas('error', 'هذه الكنية اسم مستخدم لعضو آخر بالمنتدى؛ اختر كنية أخرى.');
    $this->post(route('admin.staff.store'), ['vbuid' => '504', 'aid' => str_repeat('a', 26)])->assertSessionHasErrors('aid');
    $this->post(route('admin.staff.store'), ['vbuid' => '504', 'aid' => '<b>x</b>'])->assertSessionHasErrors('aid');
    $this->post(route('admin.staff.store'), ['vbuid' => '504', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
    $this->post(route('admin.staff.store'), ['vbuid' => '504', 'new_password' => 'short'])->assertSessionHasErrors('new_password');
    $this->post(route('admin.staff.store'), ['vbuid' => '504', 'new_password' => 'long-enough-1', 'new_password_confirmation' => 'different-1'])->assertSessionHasErrors('new_password');

    expect(AdminUser::on('main')->where('uid', 504)->exists())->toBeFalse();
});

it('create form: shows the permission grid to super-admins only and the super-admin flag to owners only', function () {
    $adder = amAdmin('adder');
    $adder->givePermissionTo('authors.addstuff');
    $html = $this->actingAs($adder, 'admin')->get(route('admin.staff.create'))->assertOk()->getContent();
    expect($html)->toContain('name="aid"')->toContain('name="url"')->not->toContain('permissions[')->not->toContain('make_super_admin');

    amFresh();
    $boss = amAdmin('boss', true);
    $html = $this->actingAs($boss, 'admin')->get(route('admin.staff.create'))->assertOk()->getContent();
    expect($html)->toContain('permissions[fatwa.manage]')->not->toContain('make_super_admin');

    amFresh();
    amOwner($boss);
    $html = $this->actingAs($boss, 'admin')->get(route('admin.staff.create'))->assertOk()->getContent();
    expect($html)->toContain('make_super_admin');
});

// ---------------------------------------------------------------- profile

it('profile: a super-admin edits a regular admin\'s email and URL; audited with field names only; the name is not editable', function () {
    $boss = amAdmin('boss', true);
    $target = amAdmin('target');

    $this->actingAs($boss, 'admin')->put(route('admin.staff.profile', $target), [
        'email' => 'new@example.com', 'url' => 'https://example.org', 'name' => 'Hacked',
    ])->assertRedirect(route('admin.permissions.edit', $target))->assertSessionHas('success');

    $fresh = AdminUser::on('main')->find($target->id);
    expect($fresh->email)->toBe('new@example.com')
        ->and($fresh->getAttribute('url'))->toBe('https://example.org')
        ->and($fresh->name)->toBe('target');
    $meta = json_decode((string) amAudit('update_admin_profile')[0]->metadata, true);
    expect($meta['changed'])->toBe(['email', 'url'])
        ->and((string) amAudit('update_admin_profile')[0]->metadata)->not->toContain('new@example.com');
});

it('profile: protected targets — an owner account or another super-admin can be edited only by an owner', function () {
    $owner = amAdmin('owner', true);
    $boss = amAdmin('boss', true);
    $other = amAdmin('other', true);
    amOwner($owner);

    $this->actingAs($boss, 'admin');
    $this->put(route('admin.staff.profile', $owner), ['email' => 'x@example.com'])->assertSessionHas('error', 'هذا حساب مالك محمي؛ لا يعدّله إلا حساب مالك.');
    $this->put(route('admin.staff.profile', $other), ['email' => 'x@example.com'])->assertSessionHas('error', 'إدارة حساب مشرف عام آخر متاحة لحسابات الملاك فقط.');
    expect(AdminUser::on('main')->find($owner->id)->email)->toBe('owner@test.invalid')
        ->and(AdminUser::on('main')->find($other->id)->email)->toBe('other@test.invalid');

    amFresh();
    $this->actingAs($owner, 'admin')->put(route('admin.staff.profile', $other), ['email' => 'ok@example.com'])->assertSessionHas('success');
    expect(AdminUser::on('main')->find($other->id)->email)->toBe('ok@example.com');
});

// ---------------------------------------------------------------- promote / demote

it('promote / demote: an owner changes the Laravel role; legacy radminsuper untouched; both audited', function () {
    $owner = amAdmin('owner', true);
    $target = amAdmin('target');
    amOwner($owner);
    $this->actingAs($owner, 'admin');

    // The editor shows the owner the promote button (and a non-owner none — see the permission-editor test).
    expect($this->get(route('admin.permissions.edit', $target))->assertOk()->getContent())
        ->toContain(route('admin.staff.super-admin.promote', $target))->toContain('ترقية إلى مشرف عام');

    $this->post(route('admin.staff.super-admin.promote', $target))->assertSessionHas('success');
    expect($this->get(route('admin.permissions.edit', $target))->getContent())->toContain('سحب صلاحية المشرف العام');
    expect(AdminUser::on('main')->find($target->id)->hasRole('super-admin'))->toBeTrue()
        ->and((int) AdminUser::on('main')->find($target->id)->radminsuper)->toBe(0);

    $this->post(route('admin.staff.super-admin.promote', $target))->assertSessionHas('error', 'هذا المشرف مشرف عام بالفعل.');

    $this->delete(route('admin.staff.super-admin.demote', $target))->assertSessionHas('success');
    expect(AdminUser::on('main')->find($target->id)->hasRole('super-admin'))->toBeFalse()
        ->and(array_column(amAudit(), 'action'))->toBe(['promote_super_admin', 'demote_super_admin']);
});

it('promote / demote: refused for non-owner super-admins, on oneself, on the main account (Good), for deactivated targets, and when no account satisfies OLD\'s owner rule; an owner may change another owner (OLD)', function () {
    $owner = amAdmin('owner', true);
    $owner2 = amAdmin('owner2', true);
    $good = amAdmin('goodacct', true, ['name' => 'Good']);
    $boss = amAdmin('boss', true);
    $target = amAdmin('target');

    // Nobody matches OLD's rule yet (no owner login names, and `Good` is not the actor) → blocked.
    $this->actingAs($owner, 'admin')->post(route('admin.staff.super-admin.promote', $target))
        ->assertSessionHas('error', 'ترقية المشرفين وتنزيلهم متاحة لحسابات الملاك فقط.');

    amOwner($owner, $owner2);
    amFresh();
    $this->actingAs($boss, 'admin')->post(route('admin.staff.super-admin.promote', $target))->assertSessionHas('error', 'ترقية المشرفين وتنزيلهم متاحة لحسابات الملاك فقط.');
    $this->actingAs($boss, 'admin')->delete(route('admin.staff.super-admin.demote', $boss))->assertSessionHas('error', 'ترقية المشرفين وتنزيلهم متاحة لحسابات الملاك فقط.');

    amFresh();
    $this->actingAs($owner, 'admin');
    $this->delete(route('admin.staff.super-admin.demote', $owner))->assertSessionHas('error', 'لا يمكنك تغيير صلاحيتك بنفسك.');
    $this->delete(route('admin.staff.super-admin.demote', $good))->assertSessionHas('error', 'لا يمكن تغيير صلاحية الحساب الرئيسي.');

    app(DeactivateAdminAction::class)->execute($owner, $target, 'r');
    $this->post(route('admin.staff.super-admin.promote', $target))->assertSessionHas('error', 'هذا المشرف معطّل؛ أعد تفعيله أولاً.');

    expect(AdminUser::on('main')->find($target->id)->hasRole('super-admin'))->toBeFalse()
        ->and(AdminUser::on('main')->find($good->id)->hasRole('super-admin'))->toBeTrue()
        ->and(amAudit('promote_super_admin'))->toBe([])
        ->and(amAudit('demote_super_admin'))->toBe([]);

    // OLD: an owner could downgrade another owner (only `Good` was exempt).
    $this->delete(route('admin.staff.super-admin.demote', $owner2))->assertSessionHas('success');
    expect(AdminUser::on('main')->find($owner2->id)->hasRole('super-admin'))->toBeFalse();
});

it('OLD owner rule: radminsuper 1 AND (name Good OR login Admin / مؤمن يحب الله ورسوله, case-insensitive); the main account is name Good exactly', function () {
    $cases = [
        ['admin', [], true],                                       // stored lower-case, OLD matched the typed `Admin` under the collation
        ['Admin', ['radminsuper' => false], false],                // not a super user
        ['مؤمن يحب الله ورسوله', [], true],
        ['x1', ['name' => 'Good'], true],
        ['Administrator', [], false],
    ];
    foreach ($cases as $i => [$aid, $attributes, $expected]) {
        $admin = amAdmin($aid, true, array_merge(['uid' => 900 + $i, 'name' => 'n'.$i], $attributes));
        expect(AdminOwners::isOwner($admin))->toBe($expected, $aid);
    }
    $good = AdminUser::on('main')->where('name', 'Good')->firstOrFail();
    expect(AdminOwners::isProtectedMainAccount($good))->toBeTrue()
        ->and(AdminOwners::isProtectedMainAccount(AdminUser::on('main')->where('aid', 'admin')->firstOrFail()))->toBeFalse()
        ->and(AdminOwners::ownerIds())->toHaveCount(3);
});

it('create: OLD authority identities are reserved (owner login names, the name Good) for every actor', function () {
    $owner = amAdmin('admin', true);
    amForumUser(506, 'm506');
    $this->actingAs($owner, 'admin');

    foreach ([['aid' => 'ADMIN '], ['aid' => 'مؤمن يحب الله ورسوله'], ['name' => 'Good'], ['name' => 'good']] as $fields) {
        $this->post(route('admin.staff.store'), array_merge(['vbuid' => '506'], $fields))
            ->assertSessionHas('error', 'هذه الكنية أو هذا الإسم محجوز لحسابات الملاك؛ اختر غيره.');
    }
    expect(AdminUser::on('main')->where('uid', 506)->exists())->toBeFalse();
});

it('promote / demote / profile: a regular admin (even with every authors.* permission) gets 403', function () {
    $regular = amAdmin('regular');
    $regular->givePermissionTo(['authors.liststuff', 'authors.addstuff']);
    $target = amAdmin('target');
    amOwner($regular);

    $this->actingAs($regular, 'admin');
    $this->post(route('admin.staff.super-admin.promote', $target))->assertForbidden();
    $this->post(route('admin.staff.super-admin.promote', $regular))->assertForbidden();
    $this->put(route('admin.staff.profile', $target), ['email' => 'x@example.com'])->assertForbidden();
    $this->get(route('admin.permissions.edit', $target))->assertForbidden();

    expect($regular->fresh()->hasRole('super-admin'))->toBeFalse();
});

// ---------------------------------------------------------------- protected targets in the permission editor and F02

it('permission editor: a non-owner super-admin cannot change an owner\'s or another super-admin\'s permissions or password; can for a regular admin', function () {
    $owner = amAdmin('owner', true);
    $boss = amAdmin('boss', true);
    $other = amAdmin('other', true);
    $regular = amAdmin('regular');
    amOwner($owner);
    $ownerHash = (string) AdminUser::on('main')->find($owner->id)->password;

    $this->actingAs($boss, 'admin');
    $this->put(route('admin.permissions.password', $owner), ['new_password' => 'takeover-123'])->assertSessionHas('error');
    $this->put(route('admin.permissions.update', $other), ['permissions' => ['fatwa.manage' => '1']])->assertSessionHas('error');
    expect((string) AdminUser::on('main')->find($owner->id)->password)->toBe($ownerHash)
        ->and(AdminUser::on('main')->find($other->id)->getPermissionNames()->all())->toBe([]);

    $this->put(route('admin.permissions.update', $regular), ['permissions' => ['fatwa.manage' => '1']])->assertSessionHas('success');
    $this->put(route('admin.permissions.update', $regular), ['permissions' => []])->assertSessionHas('success');
    expect(AdminUser::on('main')->find($regular->id)->getPermissionNames()->all())->toBe([])
        ->and(array_column(amAudit('update_permissions'), 'entity_id'))->toBe([(int) $regular->id, (int) $regular->id]);

    // The editor page shows the refusal instead of the profile form for a protected target.
    $html = $this->get(route('admin.permissions.edit', $owner))->assertOk()->getContent();
    expect($html)->toContain('هذا حساب مالك محمي')->not->toContain(route('admin.staff.profile', $owner))
        ->not->toContain('ترقية إلى مشرف عام')->not->toContain('سحب صلاحية المشرف العام');
    expect($this->get(route('admin.permissions.edit', $regular))->getContent())->not->toContain('ترقية إلى مشرف عام');
});

it('deactivation: the main account (Good) is never deactivated; an owner or another super-admin only by an owner; an owner may deactivate another owner (OLD)', function () {
    $owner = amAdmin('owner', true);
    $owner2 = amAdmin('owner2', true);
    $good = amAdmin('goodacct', true, ['name' => 'Good']);
    $boss = amAdmin('boss', true);
    $other = amAdmin('other', true);
    amOwner($owner, $owner2);

    expect(fn () => app(DeactivateAdminAction::class)->execute($owner, $good, 'r'))->toThrow(AdminDeactivationRefused::class, 'هذا هو الحساب الرئيسي المحمي ولا يمكن تعطيله.')
        ->and(fn () => app(DeactivateAdminAction::class)->execute($boss, $owner2, 'r'))->toThrow(AdminDeactivationRefused::class, 'هذا حساب مالك محمي؛ لا يعدّله إلا حساب مالك.')
        ->and(fn () => app(DeactivateAdminAction::class)->execute($boss, $other, 'r'))->toThrow(AdminDeactivationRefused::class, 'إدارة حساب مشرف عام آخر متاحة لحسابات الملاك فقط.');

    app(DeactivateAdminAction::class)->execute($owner, $other, 'r');
    app(DeactivateAdminAction::class)->execute($owner, $owner2, 'r');
    expect(amAudit('deactivate_admin'))->toHaveCount(2);

    // Staff list (non-owner viewer): badges; no deactivate link for owners, the main account or super-admins.
    $html = $this->actingAs($boss, 'admin')->get(route('admin.staff.index'))->assertOk()->getContent();
    expect($html)->toContain('مالك')->toContain('الحساب الرئيسي')
        ->not->toContain(route('admin.staff.deactivate.confirm', $owner))
        ->not->toContain(route('admin.staff.deactivate.confirm', $good))
        ->not->toContain(route('admin.staff.reactivate.confirm', $other));

    // Owner viewer: no deactivate link for the main account.
    amFresh();
    expect($this->actingAs($owner, 'admin')->get(route('admin.staff.index'))->getContent())
        ->not->toContain(route('admin.staff.deactivate.confirm', $good));
});

it('a deactivated admin is blocked from AdminCP', function () {
    $owner = amAdmin('owner', true);
    $target = amAdmin('target');
    $target->givePermissionTo('authors.liststuff');
    amOwner($owner);
    app(DeactivateAdminAction::class)->execute($owner, $target, 'r');

    amFresh();
    // A real session (not actingAs, which bypasses AdminGuard's lookup).
    $this->withSession(['admin_auth_id' => $target->id])->get(route('admin.staff.index'))->assertStatus(401);
});
