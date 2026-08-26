<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Channel;
use App\Domain\Admin\Models\Survey;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * AdminCP Final Page-Level Visual-Parity Verification (2026-08-22).
 * Protects the per-page portlet reconstruction (`<x-admin-portlet>`
 * replacing the previous single-generic-wrap layout) against regression —
 * specifically the confirmed multi-portlet gaps (Broadcasting's 2 real
 * portlets, Chat's 2, Staff's up-to-2) and the "no dead controls restored"
 * / "permission behavior unchanged" requirements.
 */
function useInMemoryMainConnectionForPageLevelParity(array $extra = []): void
{
    InMemoryConnection::setup('main', array_merge([
        'nuke_authors' => MainSchema::nukeAuthors(),
    ], $extra));
}

beforeEach(function () {
    useInMemoryMainConnectionForPageLevelParity();
});

// ---- Broadcasting: the explicit multi-portlet priority ----

it('renders 2 portlets on the edit page when a streamcode is already set, matching edit_stream.php', function () {
    useInMemoryMainConnectionForPageLevelParity(['nuke_sat_channels' => MainSchema::nukeSatChannels()]);
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    $channel = Channel::create(['title' => 'Al-Nas', 'ch_visits' => 0, 'streamcode' => '<iframe src="x"></iframe>']);

    $content = $this->get(route('admin.broadcasting.edit', $channel))->assertOk()->getContent();

    expect(substr_count($content, 'portlet box purple'))->toBe(2)
        ->and($content)->toContain('تعديل بث قناة : Al-Nas')
        ->toContain('الكود الحالي لـ Al-Nas');
});

it('renders only 1 portlet on the edit page when no streamcode is set yet', function () {
    useInMemoryMainConnectionForPageLevelParity(['nuke_sat_channels' => MainSchema::nukeSatChannels()]);
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    $channel = Channel::create(['title' => 'Al-Nas', 'ch_visits' => 0, 'streamcode' => '']);

    $content = $this->get(route('admin.broadcasting.edit', $channel))->assertOk()->getContent();

    expect(substr_count($content, 'portlet box purple'))->toBe(1)
        ->and($content)->not->toContain('الكود الحالي');
});

// ---- Chat: FlashChat live-room admin tooling removed (Final Migration
// Owner-Decision Closure, 2026-08-23, CHAT_ROOM_ADMIN = REMOVE) — the
// "2 real portlets" test previously here is gone along with the route it
// tested; see AdminRetiredRoutesTest.php for the proof the route is gone.

// ---- Staff: portlet split by radminsuper ----

it('renders 2 distinct portlets when both super-admins and regular admins exist', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x', 'radminsuper' => true]);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    AdminUser::on('main')->create(['aid' => 'editor', 'password' => 'x', 'radminsuper' => false]);
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($content)->toContain('قائمة الإدارة العليا للموقع')->toContain('قائمة المشرفين');
});

it('renders only 1 staff portlet when every admin shares the same rank', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x', 'radminsuper' => false]);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($content)->toContain('قائمة المشرفين')->not->toContain('قائمة الإدارة العليا للموقع');
});

// ---- Survey: portlet caption + previously-missing real columns ----

it('renders the survey list inside its real portlet caption with the # and مشاهدة columns present', function () {
    useInMemoryMainConnectionForPageLevelParity(['nuke_survey' => MainSchema::nukeSurvey()]);
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    $survey = Survey::create(['title' => 'رأيك يهمنا', 'questions' => 0, 'submits' => 0]);

    $content = $this->get(route('admin.survey.index'))->assertOk()->getContent();

    expect($content)->toContain('قائمة الاستبيانات')
        ->toContain('مشاهدة')
        ->toContain('https://way2allah.com/survey/?id='.$survey->id);
});

// ---- No dead controls restored anywhere in the reconstructed shell ----

it('never renders any dead legacy control across the reconstructed pages', function () {
    useInMemoryMainConnectionForPageLevelParity(['nuke_sat_channels' => MainSchema::nukeSatChannels()]);
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.broadcasting.index'))->assertOk()->getContent();

    expect($content)->not->toContain('addstream')->not->toContain('delete_stream');
});

// ---- Permission/security behavior unchanged after the restructuring ----

it('still 403s a plain admin without the required permission after the page-level restructuring', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.staff.index'))->assertForbidden();
});

// ---- AdminCP Authenticated Browser Visual-Parity Investigation (2026-08-23) ----

it('every admin table carries real Bootstrap/Metronic classes, not a bare unstyled <table>', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($content)->not->toContain('<table>')
        ->toContain('<table class="table table-striped table-hover table-light">');
});
