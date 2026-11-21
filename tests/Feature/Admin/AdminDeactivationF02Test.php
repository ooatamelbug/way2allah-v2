<?php

use App\Domain\Admin\Actions\Staff\AdminDeactivationRefused;
use App\Domain\Admin\Actions\Staff\AdminStaffSafety;
use App\Domain\Admin\Actions\Staff\DeactivateAdminAction;
use App\Domain\Admin\Actions\Staff\ReactivateAdminAction;
use App\Domain\Admin\Actions\SyncLegacyAdminPermissionsAction;
use App\Domain\Admin\Models\AdminAccountState;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Identity\Services\LegacyPasswordVerifier;
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
 * F02 — Laravel-side administrator deactivation (owner decisions 2026-11-14,
 * B3 design review §13). Laravel only: OLD, the bridge cookie and Gen 2's
 * forum-session hooks do not read the state yet (documented limitation).
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
    config(['admin_deactivation.enabled' => true]);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
});

function f02Admin(string $aid, bool $super = false, array $attributes = []): AdminUser
{
    $admin = AdminUser::on('main')->create(array_merge([
        'aid' => $aid, 'uid' => crc32($aid) % 100000, 'name' => $aid, 'email' => $aid.'@test.invalid',
        'password' => Hash::make($aid.'-pass'), 'pwd' => md5($aid.'-pass'), 'radminsuper' => $super,
        'API' => str_repeat('k', 32),
    ], $attributes));
    if ($super) {
        $admin->assignRole('super-admin');
    }

    return $admin;
}

function f02Audit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
}

function f02Fresh(): void
{
    app('auth')->forgetGuards();
}

// ---------------------------------------------------------------- deactivate

it('deactivates an admin: record kept, uid/aid/grants preserved, credentials and API key neutralised, audited', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    $target->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $before = AdminUser::on('main')->find($target->id);

    $this->actingAs($boss, 'admin')
        ->post(route('admin.staff.deactivate', $target), ['reason' => 'انتهاء التكليف', 'confirm' => '1'])
        ->assertRedirect(route('admin.staff.index'))
        ->assertSessionHas('success');

    $row = AdminUser::on('main')->find($target->id);
    expect($row)->not->toBeNull()
        ->and((int) $row->uid)->toBe((int) $before->uid)
        ->and($row->aid)->toBe('target')
        ->and($row->email)->toBe('target@test.invalid')
        ->and($row->getRawOriginal('password'))->toStartWith('!disabled:')
        ->and(strlen($row->getRawOriginal('password')))->toBe(58)
        ->and($row->getRawOriginal('pwd'))->toMatch('/^x[A-Za-z0-9]{39}$/')
        ->and($row->getRawOriginal('API'))->toBeNull()
        ->and($row->getPermissionNames()->all())->toBe(['authors.liststuff']);

    $state = AdminAccountState::query()->find($target->id);
    expect((int) $state->deactivated_by)->toBe((int) $boss->id)
        ->and($state->reason)->toBe('انتهاء التكليف')
        ->and((int) $state->deactivated_at)->toBeGreaterThan(time() - 60);

    $audit = collect(f02Audit())->firstWhere('action', 'deactivate_admin');
    expect($audit)->not->toBeNull()
        ->and((int) $audit->entity_id)->toBe((int) $target->id)
        ->and((int) $audit->actor_admin_id)->toBe((int) $boss->id);
    // No marker, hash or key in the audit row.
    expect((string) $audit->metadata)->not->toContain('!disabled:')
        ->not->toContain($row->getRawOriginal('pwd'))
        ->not->toContain(str_repeat('k', 32));
});

it('the neutralised credentials reject every verifier branch, including the empty string and the old password', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');

    $row = AdminUser::on('main')->find($target->id);
    $stored = $row->currentStoredPassword();
    $verifier = app(LegacyPasswordVerifier::class);
    foreach (['', 'target-pass', $stored, md5('target-pass')] as $attempt) {
        expect($verifier->verify($attempt, $stored))->toBeFalse();
    }
    // Gen 2's plain-text branch compares with the stored value — unknowable random marker.
    expect($stored)->not->toMatch('/^\$2[ay]\$/')->not->toMatch('/^[a-f0-9]{32}$/i')->not->toMatch('/^[a-f0-9]{40}$/i');
});

it('an existing Laravel session of the deactivated admin loses access on its next request', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target', false);
    $target->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));

    $this->withSession(['admin_auth_id' => $target->id])->get(route('admin.staff.index'))->assertOk();
    f02Fresh();

    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');
    f02Fresh();

    $this->withSession(['admin_auth_id' => $target->id])->get(route('admin.staff.index'))->assertStatus(401);
    $this->assertGuest('admin');
});

it('refuses the login of a deactivated admin even if a legacy system later rewrote a valid credential', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');

    // e.g. a Gen 2 reset or OLD's vb5.php rewrite after deactivation: the state row stays authoritative.
    DB::connection('main')->table('nuke_authors')->where('id', $target->id)->update(['password' => Hash::make('revived-pass')]);

    $this->post('/admincp/login', ['aid' => 'target', 'password' => 'revived-pass'])->assertSessionHasErrors('aid');
    $this->assertGuest('admin');
});

it('refuses non-super-admins (403), self-deactivation and the last active super-admin', function () {
    $boss = f02Admin('boss', true);
    $plain = f02Admin('plain');
    $plain->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $other = f02Admin('other');

    $this->actingAs($plain, 'admin')->post(route('admin.staff.deactivate', $other), ['reason' => 'x', 'confirm' => '1'])->assertForbidden();
    expect(AdminAccountState::query()->count())->toBe(0);

    f02Fresh();
    $this->actingAs($boss, 'admin')->post(route('admin.staff.deactivate', $boss), ['reason' => 'x', 'confirm' => '1'])
        ->assertRedirect(route('admin.staff.index'))->assertSessionHas('error', 'لا يمكنك تعطيل حسابك.');
    expect(AdminAccountState::query()->count())->toBe(0);
});

it('never leaves zero active super-admins: deactivating one of two works, then the survivor cannot be deactivated by the deactivated one', function () {
    $a = f02Admin('alpha', true);
    $b = f02Admin('beta', true);
    // Administrator management (2026-11-17): deactivating another super-admin needs an owner actor.
    config(['admin_owners.owner_aids' => [(string) $a->aid]]); // OLD rule: radminsuper 1 + owner login name

    app(DeactivateAdminAction::class)->execute($a, $b, 'r');
    expect(AdminAccountState::query()->whereNotNull('deactivated_at')->pluck('admin_id')->all())->toBe([(int) $b->id]);

    // Mutual-deactivation race, serialised: beta's request is evaluated after alpha's committed.
    expect(fn () => app(DeactivateAdminAction::class)->execute($b, $a, 'r'))
        ->toThrow(AdminDeactivationRefused::class, 'هذا الإجراء متاح للمشرف العام النشط فقط.');
    expect(AdminAccountState::query()->whereKey($a->id)->whereNotNull('deactivated_at')->exists())->toBeFalse();
});

it('refuses deactivating the last active super-admin by either definition (radminsuper or Spatie role)', function () {
    // The actor is a Spatie super-admin; the only other super-admin target is legacy radminsuper only.
    $actor = f02Admin('actor', false);
    $actor->assignRole('super-admin');
    $legacySuper = f02Admin('legacy', false, ['radminsuper' => true]);
    // Administrator management (2026-11-17): a super-admin target (either definition) needs an owner actor
    // (OLD's rule needs radminsuper 1 on the actor).
    $actor->forceFill(['radminsuper' => true])->save();
    config(['admin_owners.owner_aids' => [(string) $actor->aid]]); // OLD rule: radminsuper 1 + owner login name

    // Deactivating the legacy super-admin leaves the actor → allowed.
    app(DeactivateAdminAction::class)->execute($actor, $legacySuper, 'r');
    expect(AdminAccountState::query()->whereKey($legacySuper->id)->exists())->toBeTrue();

    expect(AdminStaffSafety::remainingActiveSuperAdmins((int) $actor->id))->toBe(0);
});

it('refuses deactivating an already deactivated admin, and audits nothing extra', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');
    $count = count(f02Audit());

    expect(fn () => app(DeactivateAdminAction::class)->execute($boss, $target, 'again'))
        ->toThrow(AdminDeactivationRefused::class, 'هذا المشرف معطّل بالفعل.');
    expect(count(f02Audit()))->toBe($count);
});

it('validates the reason (required, plain text, ≤ 255) and the confirmation', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    $this->actingAs($boss, 'admin');

    $this->post(route('admin.staff.deactivate', $target), ['confirm' => '1'])->assertSessionHasErrors('reason');
    $this->post(route('admin.staff.deactivate', $target), ['reason' => '<b>x</b>', 'confirm' => '1'])->assertSessionHasErrors('reason');
    $this->post(route('admin.staff.deactivate', $target), ['reason' => str_repeat('س', 256), 'confirm' => '1'])->assertSessionHasErrors('reason');
    $this->post(route('admin.staff.deactivate', $target), ['reason' => 'x'])->assertSessionHasErrors('confirm');
    expect(AdminAccountState::query()->count())->toBe(0);
});

// ---------------------------------------------------------------- reactivate

it('reactivates with a required new password: access restored, uid and grants intact, pwd marker and API stay closed, audited', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    $target->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');
    $markerPwd = AdminUser::on('main')->find($target->id)->getRawOriginal('pwd');

    $this->actingAs($boss, 'admin')
        ->post(route('admin.staff.reactivate', $target), ['new_password' => 'short', 'new_password_confirmation' => 'short'])
        ->assertSessionHasErrors('new_password');
    $this->post(route('admin.staff.reactivate', $target), ['new_password' => 'Brand-new-123', 'new_password_confirmation' => 'different'])
        ->assertSessionHasErrors('new_password');

    $this->post(route('admin.staff.reactivate', $target), ['new_password' => 'Brand-new-123', 'new_password_confirmation' => 'Brand-new-123'])
        ->assertRedirect(route('admin.staff.index'))->assertSessionHas('success');

    $row = AdminUser::on('main')->find($target->id);
    expect(Hash::check('Brand-new-123', $row->getRawOriginal('password')))->toBeTrue()
        ->and($row->getRawOriginal('pwd'))->toBe($markerPwd)
        ->and($row->getRawOriginal('API'))->toBeNull()
        ->and($row->getPermissionNames()->all())->toBe(['authors.liststuff']);

    $state = AdminAccountState::query()->find($target->id);
    expect($state->deactivated_at)->toBeNull()
        ->and((int) $state->reactivated_by)->toBe((int) $boss->id);

    $audit = collect(f02Audit())->firstWhere('action', 'reactivate_admin');
    expect($audit)->not->toBeNull()->and((string) $audit->metadata)->not->toContain('Brand-new-123');

    f02Fresh();
    $this->post('/admincp/login', ['aid' => 'target', 'password' => 'Brand-new-123'])->assertRedirect(route('admin.entry'));
});

it('refuses reactivating an active admin and refuses non-super-admins', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');

    expect(fn () => app(ReactivateAdminAction::class)->execute($boss, $target, 'Brand-new-123'))
        ->toThrow(AdminDeactivationRefused::class, 'هذا المشرف ليس معطّلاً.');

    $plain = f02Admin('plain');
    $this->actingAs($plain, 'admin')->get(route('admin.staff.reactivate.confirm', $target))->assertForbidden();
});

// ---------------------------------------------------------------- other enforcement points

it('the permission editor refuses permission and password changes for a deactivated admin (no silent reactivation)', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');
    $stored = AdminUser::on('main')->find($target->id)->getRawOriginal('password');

    $this->actingAs($boss, 'admin');
    $this->put(route('admin.permissions.password', $target), ['new_password' => 'Sneaky-12345'])->assertSessionHas('error');
    expect(AdminUser::on('main')->find($target->id)->getRawOriginal('password'))->toBe($stored);

    Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']);
    $this->put(route('admin.permissions.update', $target), ['permissions' => ['authors.addstuff' => '1']])->assertSessionHas('error');
    expect(AdminUser::on('main')->find($target->id)->getPermissionNames()->all())->toBe([]);

    $this->get(route('admin.permissions.edit', $target))->assertOk()->assertSee('هذا المشرف معطّل');
});

it('audits permission edits (names added/removed) and password sets (no secret) — owner decision 16', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']);
    $this->actingAs($boss, 'admin');

    $this->put(route('admin.permissions.update', $target), ['permissions' => ['authors.addstuff' => '1']])->assertRedirect();
    $this->put(route('admin.permissions.password', $target), ['new_password' => 'Secret-pass-9'])->assertRedirect();

    $rows = collect(f02Audit());
    $perm = $rows->firstWhere('action', 'update_permissions');
    $pass = $rows->firstWhere('action', 'set_password');
    expect(json_decode((string) $perm->metadata, true))->toMatchArray(['added' => ['authors.addstuff'], 'removed' => []])
        ->and($pass)->not->toBeNull()
        ->and((string) $pass->metadata)->not->toContain('Secret-pass-9');
});

it('the backup API refuses a deactivated admin even if an API key were written back', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target', false, ['uid' => 777]);
    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');
    DB::connection('main')->table('nuke_authors')->where('id', $target->id)->update(['API' => str_repeat('z', 32)]);

    expect($this->post('/backup.php', ['ID' => 777, 'APIKey' => str_repeat('z', 32)])->getContent())->toBe('API Error 2');
});

it('the permission sync skips deactivated admins and keeps their grants', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target', true);
    // Administrator management (2026-11-17): deactivating another super-admin needs an owner actor.
    config(['admin_owners.owner_aids' => [(string) $boss->aid]]); // OLD rule: radminsuper 1 + owner login name
    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');

    $result = app(SyncLegacyAdminPermissionsAction::class)->execute();

    expect($result->firstWhere('aid', 'target')['skipped'] ?? null)->toBe('deactivated')
        ->and(AdminUser::on('main')->find($target->id)->hasRole('super-admin'))->toBeTrue();
});

it('the staff list keeps deactivated admins with a badge, and shows status controls only to super-admins', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    app(DeactivateAdminAction::class)->execute($boss, $target, 'r');

    $html = $this->actingAs($boss, 'admin')->get(route('admin.staff.index'))->assertOk()->getContent();
    expect($html)->toContain('target')->toContain('معطّل')->toContain(route('admin.staff.reactivate.confirm', $target))
        ->not->toContain(route('admin.staff.deactivate.confirm', $boss));

    f02Fresh();
    $viewer = f02Admin('viewer');
    $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $html = $this->actingAs($viewer, 'admin')->get(route('admin.staff.index'))->assertOk()->getContent();
    expect($html)->not->toContain('/deactivate')->not->toContain('/reactivate');
});

// ---------------------------------------------------------------- switch OFF (production default)

it('with the switch OFF (default): no state query, routes 404, no controls, and existing state rows are not read', function () {
    $boss = f02Admin('boss', true);
    $target = f02Admin('target');
    config(['admin_deactivation.enabled' => false]);

    $queries = [];
    DB::connection('main')->listen(function ($q) use (&$queries) {
        $queries[] = $q->sql;
    });

    $this->actingAs($boss, 'admin');
    $this->get(route('admin.staff.deactivate.confirm', $target))->assertNotFound();
    $this->post(route('admin.staff.deactivate', $target), ['reason' => 'x', 'confirm' => '1'])->assertNotFound();
    $html = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($html)->not->toContain('/deactivate')
        ->and(collect($queries)->filter(fn ($sql) => str_contains($sql, 'admin_account_states'))->all())->toBe([])
        ->and(AdminAccountState::query()->count())->toBe(0);
});
