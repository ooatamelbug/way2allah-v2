<?php

/*
 * W2ACD OLD public admin parity, Phase 3 (2026-11-03; decision-log #77) — the CD
 * main page `cds-main.htm` (OLD `Group(0)`) and its «لوحة تحكم قسم الاسطوانات».
 * OLD rendered the group template there with id 0; only M4 «إضافة قسم فرعي»
 * (a top-level group) worked. M1 «اضافة صورة للقسم», M2 «تعديل القسم» and M5
 * «حذف القسم» acted on no row; M3 «إضافة إسطوانة» is owner-retired.
 */

use App\Domain\Admin\Actions\Cds\InsertCdSubGroupAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CdAdminHarness;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->top = H::insert('nuke_w2acd_groups', ['title' => 'قسم رئيسي قائم', 'parent_id' => 0, 'child' => 1, 'module_type' => 'cd', 'des' => '']);
    $this->sub = H::insert('nuke_w2acd_groups', ['title' => 'قسم الجديد', 'parent_id' => $this->top, 'module_type' => 'cd', 'des' => '']);
    $this->cd = H::insert('nuke_w2acd_w2acd', ['group_id' => 0, 'title' => 'إسطوانة عامة', 'hidden' => 0, 'thumbnail' => '']);
    $this->manager = H::admin(['w2acd.managecds'], 'cd-manager');
});

function mainPage($admin = null, string $url = '/cds-main.htm'): string
{
    if ($admin === null) {
        H::visitor();

        return test()->get($url)->assertOk()->getContent();
    }

    return test()->withSession(H::session($admin))->get($url)->assertOk()->getContent();
}

/** @return list<array<string, mixed>> */
function mainGroups(): array
{
    return DB::connection('main')->table('nuke_w2acd_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
}

// ─── panel ──────────────────────────────────────────────────────────────────

it('panel: visitors and admins without w2acd.managecds see no panel', function () {
    expect(mainPage())->not->toContain('لوحة تحكم قسم الاسطوانات')->not->toContain('data-contextual-admin')
        ->and(mainPage(H::admin(['polls.managepolls'], 'cd-other')))->not->toContain('لوحة تحكم قسم الاسطوانات')
        ->not->toContain(route('admin.cds.groups.create'));
});

it('panel: the manager and a super-admin see M4 «إضافة قسم فرعي» only — never M1, M2, M3 or M5', function () {
    foreach ([$this->manager, H::superAdmin('cd-boss')] as $admin) {
        $html = mainPage($admin);

        expect($html)->toContain('لوحة تحكم قسم الاسطوانات')
            ->toContain('href="'.route('admin.cds.groups.create').'"')->toContain('إضافة قسم فرعي')
            ->not->toContain('اضافة صورة للقسم')->not->toContain('تعديل القسم')
            ->not->toContain('إضافة إسطوانة')->not->toContain('حذف القسم')
            ->not->toContain('/admincp/cds/groups/0')->not->toContain('return_to=');
        H::visitor();
    }
});

it('panel: only on cds-main.htm — not on Gen 2\'s /w2acd/cds.php', function () {
    expect(mainPage($this->manager, '/w2acd/cds.php'))->not->toContain('لوحة تحكم قسم الاسطوانات');
});

// ─── authorization ──────────────────────────────────────────────────────────

it('authorization: both routes refuse a visitor and an admin without w2acd.managecds, writing nothing', function () {
    $before = mainGroups();

    H::visitor();
    $this->get(route('admin.cds.groups.create'))->assertUnauthorized();
    $this->post(route('admin.cds.groups.store'), ['Title' => 'x'])->assertUnauthorized();

    $session = H::session(H::admin(['polls.managepolls'], 'cd-other'));
    $this->withSession($session)->get(route('admin.cds.groups.create'))->assertForbidden();
    $this->withSession($session)->post(route('admin.cds.groups.store'), ['Title' => 'x'])->assertForbidden();

    expect(mainGroups())->toBe($before);
});

it('authorization: the action re-checks the permission itself', function () {
    expect(fn () => app(InsertCdSubGroupAction::class)->executeRoot(H::admin(['polls.managepolls'], 'cd-other'), ['Title' => 'x']))
        ->toThrow(AuthorizationException::class)
        ->and(mainGroups())->toHaveCount(2);
});

// ─── M4 «إضافة قسم فرعي» ────────────────────────────────────────────────────

it('M4: the form is OLD addgroup.htm with no parent («بدون مجموعة») and a way back to cds-main', function () {
    $this->withSession(H::session($this->manager))->get(route('admin.cds.groups.create'))->assertOk()
        ->assertSee('إضافة قسم فرعي')->assertSee('عنوان القسم :')->assertSee('بدون مجموعة')
        ->assertSee('التعليق على القسم :')->assertSee('أضــف قسم فرعي')
        ->assertSee('action="'.route('admin.cds.groups.store').'"', false)
        ->assertSee('href="/cds-main.htm"', false);
});

it('M4: creates a top-level group with OLD\'s columns, audits it, touches no counter, and returns to cds-main', function () {
    $before = mainGroups();

    $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.groups.store'), ['Title' => 'قسم رئيسي جديد', 'Des' => 'تعليقه', 'parent_id' => $this->top])
        ->assertRedirect('/cds-main.htm')
        ->assertSessionHas(ContextualReturn::FLASH, "تمت إضافة القسم '' قسم رئيسي جديد ''");

    $new = DB::connection('main')->table('nuke_w2acd_groups')->where('title', 'قسم رئيسي جديد')->first();
    expect($new)->not->toBeNull()
        ->and((int) $new->parent_id)->toBe(0)
        ->and($new->des)->toBe('تعليقه')->and($new->module_type)->toBe('cd')
        ->and((int) $new->size)->toBe(0)->and((int) $new->dur)->toBe(0)
        ->and((int) $new->child)->toBe(0)->and((int) $new->hits)->toBe(0)->and((int) $new->icon)->toBe(0)
        // every existing row unchanged — the posted parent_id is ignored, and no counter moves
        ->and(array_slice(mainGroups(), 0, 2))->toBe($before)
        ->and(DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $this->cd)->value('group_id'))->toBe(0);

    $audit = DB::connection('main')->table('admin_audit_log')->where('action', 'add_group')->where('module', 'w2acd')
        ->where('entity_type', 'cd_group')->where('entity_id', $new->id)->first();
    expect($audit)->not->toBeNull()->and(json_decode($audit->metadata, true)['parent_id'])->toBe(0);

    $this->get("/cds-group-{$new->id}.htm")->assertOk()->assertSee('قسم رئيسي جديد');
});

it('M4: writes size and dur explicitly (NOT NULL without a default on production)', function () {
    $writes = CdAdminHarness::writesDuring(fn () => $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.groups.store'), ['Title' => 'قسم رئيسي جديد']));
    $insert = collect($writes)->first(fn ($w) => str_starts_with(strtolower(ltrim($w['sql'])), 'insert into "nuke_w2acd_groups"'));

    expect($insert)->not->toBeNull()->and($insert['sql'])->toContain('"size"')->toContain('"dur"');
});

it('M4: refuses a duplicate top-level title (OLD\'s message) and an empty title, creating nothing', function () {
    $before = mainGroups();

    $this->withSession(H::session($this->manager))->from(route('admin.cds.groups.create'))
        ->post(route('admin.cds.groups.store'), ['Title' => 'قسم رئيسي قائم'])
        ->assertRedirect(route('admin.cds.groups.create'))
        ->assertSessionHasErrors(['Title' => "عفواً .... لن تتمكن من إضافة قسم '' قسم رئيسي قائم '' إلى قاعدة بيانات الموقع - يوجد بالموقع نفس البيانات الجديدة التى حاولت إدخالها"]);
    $this->withSession(H::session($this->manager))->from(route('admin.cds.groups.create'))
        ->post(route('admin.cds.groups.store'), ['Title' => ''])
        ->assertSessionHasErrors('Title');

    expect(mainGroups())->toBe($before);
});

it('M4: a title used only by a sub-group is not a top-level duplicate, as in OLD (title AND parent_id = 0)', function () {
    $this->withSession(H::session($this->manager))->post(route('admin.cds.groups.store'), ['Title' => 'قسم الجديد'])
        ->assertRedirect('/cds-main.htm');

    expect(DB::connection('main')->table('nuke_w2acd_groups')->where('title', 'قسم الجديد')->where('parent_id', 0)->count())->toBe(1);
});
