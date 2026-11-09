<?php

/*
 * OLD public admin parity — Telawah (owner, 2026-10-29): the eight working
 * OLD public admin controls — Main's «إضافة قارىء»; the group page's «تعديل
 * القسم», «إضافة تلاوة», «إضافة قسم فرعى», «حذف القسم»; the item page's
 * «تعديل», «مسح», «نقل إلى قسم شيخ أخر» — with the four owner-approved
 * safety decisions. Dead / retired OLD controls must stay absent; visitor
 * output must not change.
 */

use App\Domain\Identity\Models\VbUser;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

const TW_PERMISSIONS = ['telawah.addgroup', 'telawah.editgroup', 'telawah.delgroup', 'telawah.addtelawah', 'telawah.edittelawah', 'telawah.deltelawah'];

beforeEach(function () {
    H::setUp();

    foreach ([1 => 'الفاتحة', 2 => 'البقرة', 3 => 'آل عمران'] as $id => $name) {
        H::insert('nuke_telawah_sorahlist', ['id' => $id, 'name' => $name]);
    }

    // reciter → group (the page) → child; a sibling of the group; a second reciter with a sub-group.
    $g = fn (array $a) => H::insert('nuke_telawah_groups', $a);
    $this->reciter = $g(['title' => 'القارىء', 'parent_id' => 0, 'child' => 2, 'telawah' => 0]);
    $this->group = $g(['title' => 'القسم', 'parent_id' => $this->reciter, 'child' => 1, 'telawah' => 2, 'des' => 'تعليق']);
    $this->sibling = $g(['title' => 'الأخ', 'parent_id' => $this->reciter, 'child' => 0, 'telawah' => 0]);
    $this->child = $g(['title' => 'الفرعي', 'parent_id' => $this->group, 'child' => 0, 'telawah' => 0]);
    $this->otherReciter = $g(['title' => 'قارىء آخر', 'parent_id' => 0, 'child' => 1, 'telawah' => 0]);
    $this->otherSub = $g(['title' => 'قسم آخر', 'parent_id' => $this->otherReciter, 'child' => 0, 'telawah' => 0]);

    $this->item = H::insert('nuke_telawah_telawah', ['title' => 'سورة الفاتحة', 'link' => 'https://x/1.mp3', 'linksize' => 1234567, 'mytime' => 1000,
        'group_id' => $this->group, 'parent_id' => $this->reciter, 'sorah' => 1, 'vedio' => 0, 'notes' => 'ملاحظة', 'hits' => 9, 'online' => 77]);
    $this->other = H::insert('nuke_telawah_telawah', ['title' => 'سورة البقرة', 'link' => 'https://x/2.mp3', 'group_id' => $this->group, 'parent_id' => $this->reciter]);

    $this->mirror = H::insert('nuke_telawah_mirror', ['telawa_id' => $this->item, 'comment' => 'm', 'link' => 'https://m/1.mp3']);
    $this->otherMirror = H::insert('nuke_telawah_mirror', ['telawa_id' => $this->other, 'comment' => 'm2', 'link' => 'https://m/2.mp3']);
    foreach ([0, 1, 2] as $view) {
        H::insert('nuke_telawah_comments', ['khid' => $this->item, 'comment' => 'c'.$view, 'view' => $view]);
    }
    $this->otherComment = H::insert('nuke_telawah_comments', ['khid' => $this->other, 'comment' => 'x', 'view' => 0]);
    H::insert('nuke_telawah_advanced', ['id' => $this->item, 'perf' => 'a']);
    H::insert('nuke_telawah_advanced', ['id' => $this->other, 'perf' => 'b']);

    $this->boss = H::superAdmin();
});

function twGroup(int $id): ?object
{
    return DB::connection('main')->table('nuke_telawah_groups')->where('id', $id)->first();
}

function twItem(int $id): ?object
{
    return DB::connection('main')->table('nuke_telawah_telawah')->where('id', $id)->first();
}

/** @return list<string> */
function twAudit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

/** @return array<string, mixed> */
function twAuditRow(): array
{
    $row = DB::connection('main')->table('admin_audit_log')->orderByDesc('id')->first();

    return ['module' => $row->module, 'entity_type' => $row->entity_type, 'entity_id' => (int) $row->entity_id, 'metadata' => json_decode((string) $row->metadata, true)];
}

/** @return list<array{0: string, 1: string}> */
function twRoutes(int $group, int $item): array
{
    return [
        ['get', route('admin.telawah.main.groups.create')],
        ['post', route('admin.telawah.main.groups.store')],
        ['get', route('admin.telawah.groups.edit', $group)],
        ['put', route('admin.telawah.groups.update', $group)],
        ['get', route('admin.telawah.groups.items.create', $group)],
        ['post', route('admin.telawah.groups.items.store', $group)],
        ['get', route('admin.telawah.groups.subgroups.create', $group)],
        ['post', route('admin.telawah.groups.subgroups.store', $group)],
        ['get', route('admin.telawah.groups.delete', $group)],
        ['delete', route('admin.telawah.groups.destroy', $group)],
        ['get', route('admin.telawah.items.edit', $item)],
        ['put', route('admin.telawah.items.update', $item)],
        ['get', route('admin.telawah.items.move', $item)],
        ['put', route('admin.telawah.items.move.save', $item)],
        ['get', route('admin.telawah.move-options.sub-groups', ['reciter' => 1])],
        ['get', route('admin.telawah.items.delete', $item)],
        ['delete', route('admin.telawah.items.destroy', $item)],
    ];
}

/** A valid item form, OLD field names. @return array<string, mixed> */
function twItemForm(array $overrides = []): array
{
    return $overrides + ['title' => 'سورة آل عمران', 'vedio' => '0', 'sorah' => '3', 'MoreInfo' => 'تعليق التلاوة', 'Telawahfile' => 'https://x/3.mp3', 'Telawahsize' => '1.5'];
}

// ─── definitions ────────────────────────────────────────────────────────────

it('defines the six OLD Telawah tokens, grants them to nobody, and mints no move or comment token', function () {
    expect(Permission::query()->where('guard_name', 'admin')->whereIn('name', TW_PERMISSIONS)->count())->toBe(6)
        ->and(Permission::query()->whereIn('name', ['telawah.movetelawah', 'telawah.addbanner', 'telawah.addcomment', 'telawah.delcomment'])->exists())->toBeFalse()
        ->and(DB::table('model_has_permissions')->count())->toBe(0)
        ->and(DB::table('role_has_permissions')->count())->toBe(0);
});

// ─── the public pages ───────────────────────────────────────────────────────

it('shows a visitor and a signed-in member no control on Main, the group page or the item page', function () {
    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach (['/recite.htm', "/recite-group-{$this->group}.htm", "/recite-item-{$this->item}.htm"] as $url) {
        foreach ([fn () => $this->get($url), fn () => $this->actingAs($member, 'vbulletin')->get($url)] as $request) {
            H::visitor();
            $html = $request()->assertOk()->getContent();
            expect($html)->not->toContain('data-contextual-admin')->not->toContain('/admincp/telawah')
                ->not->toContain('لوحة التحكم')->not->toContain('لوحة تحكم الاقسام الفرعية')->not->toContain('لوحة تحكم المشرفين');
        }
    }
});

it('shows a super-admin OLD\'s controls in OLD\'s order on each page, and none of the dead or retired ones', function () {
    $session = H::session($this->boss);
    $order = function (string $panel, array $labels) {
        $positions = array_map(fn ($label) => strpos($panel, '</i> '.$label.'</a>'), $labels);
        expect($positions)->not->toContain(false);
        $sorted = $positions;
        sort($sorted);
        expect($positions)->toBe($sorted);
    };

    $main = H::contextualMarkup($this->withSession($session)->get('/recite.htm')->assertOk()->getContent());
    expect($main)->toContain('لوحة التحكم')->toContain('href="'.route('admin.telawah.main.groups.create').'"')
        ->not->toContain('إضافة تلاوة')->not->toContain('return_to');
    $order($main, ['إضافة قارىء']);

    $group = H::contextualMarkup($this->withSession($session)->get("/recite-group-{$this->group}.htm")->assertOk()->getContent());
    expect($group)->toContain('لوحة تحكم الاقسام الفرعية')->not->toContain('نقل القسم')->not->toContain('إعادة ترتيب')
        ->not->toContain('التصنيف السريع')->not->toContain('مواد لم تصنف')->not->toContain('return_to');
    $order($group, ['تعديل القسم', 'إضافة تلاوة', 'إضافة قسم فرعى', 'حذف القسم']);

    $item = H::contextualMarkup($this->withSession($session)->get("/recite-item-{$this->item}.htm")->assertOk()->getContent());
    expect($item)->toContain('لوحة تحكم المشرفين')->not->toContain('اضافة وصلة بديلة')->not->toContain('قائمة الشير')
        ->toContain('href="'.route('admin.link-quality.telawah.item', $this->item).'"');
    $order($item, ['تعديل', 'مسح', 'نقل إلى قسم شيخ أخر', 'حالة الوصلة']);

    expect(Route::has('admin.telawah.groups.move'))->toBeFalse()
        ->and(Route::has('admin.telawah.main.items.create'))->toBeFalse()
        ->and(Route::has('admin.mirrors.telawah.store'))->toBeFalse();
});

it('shows each admin only the controls their permission reaches', function () {
    $map = [
        'telawah.addgroup' => ['/recite.htm', [route('admin.telawah.main.groups.create')]],
        'telawah.editgroup' => ["/recite-group-{$this->group}.htm", [route('admin.telawah.groups.edit', $this->group)]],
        'telawah.addtelawah' => ["/recite-group-{$this->group}.htm", [route('admin.telawah.groups.items.create', $this->group)]],
        'telawah.delgroup' => ["/recite-group-{$this->group}.htm", [route('admin.telawah.groups.delete', $this->group)]],
        'telawah.edittelawah' => ["/recite-item-{$this->item}.htm", [route('admin.telawah.items.edit', $this->item), route('admin.telawah.items.move', $this->item)]],
        'telawah.deltelawah' => ["/recite-item-{$this->item}.htm", [route('admin.telawah.items.delete', $this->item)]],
    ];
    $all = array_merge(...array_map(fn ($entry) => $entry[1], array_values($map)));
    $all[] = route('admin.telawah.groups.subgroups.create', $this->group);

    foreach ($map as $permission => [$url, $expected]) {
        $session = H::session(H::admin([$permission], 'a-'.$permission));
        $html = '';
        foreach (['/recite.htm', "/recite-group-{$this->group}.htm", "/recite-item-{$this->item}.htm"] as $page) {
            $html .= H::contextualMarkup($this->withSession($session)->get($page)->assertOk()->getContent());
        }
        foreach ($all as $href) {
            $permitted = in_array($href, $expected, true)
                || ($permission === 'telawah.addgroup' && $href === route('admin.telawah.groups.subgroups.create', $this->group));
            $permitted ? expect($html)->toContain('href="'.$href.'"') : expect($html)->not->toContain('href="'.$href.'"');
        }
    }

    $none = $this->withSession(H::session(H::admin(['telawah.editmirror', 'anasheed.editgroup', 'khotab.editgroup'], 'unrelated')))->get("/recite-group-{$this->group}.htm")->getContent();
    expect($none)->not->toContain('لوحة تحكم الاقسام الفرعية');
});

it('refuses every page and mutation to a visitor and a member (401) and to an admin without the permission (403)', function () {
    foreach (twRoutes($this->group, $this->item) as [$method, $url]) {
        $this->{$method}($url)->assertUnauthorized();
    }

    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach (twRoutes($this->group, $this->item) as [$method, $url]) {
        $this->actingAs($member, 'vbulletin')->{$method}($url)->assertUnauthorized();
    }
    H::visitor();

    $session = H::session(H::admin(['telawah.repair', 'telawah.editmirror', 'telawah.delmirror', 'anasheed.addgroup', 'anasheed.editanasheed', 'khotab.moveitem']));
    foreach (twRoutes($this->group, $this->item) as [$method, $url]) {
        $this->withSession($session)->{$method}($url, ['Title' => 'x', 'title' => 'x', 'group' => $this->group])->assertForbidden();
    }

    expect(DB::connection('main')->table('nuke_telawah_groups')->count())->toBe(6)
        ->and(DB::connection('main')->table('nuke_telawah_telawah')->count())->toBe(2)
        ->and(twGroup($this->group)->title)->toBe('القسم')
        ->and(twAudit())->toBe([]);
});

it('binds only real groups and items', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->get(route('admin.telawah.groups.edit', 999))->assertNotFound();
    $this->withSession($session)->delete(route('admin.telawah.groups.destroy', 999))->assertNotFound();
    $this->withSession($session)->get(route('admin.telawah.items.edit', 999))->assertNotFound();
    $this->withSession($session)->delete(route('admin.telawah.items.destroy', 999))->assertNotFound();
});

// ─── M1 «إضافة قارىء» ───────────────────────────────────────────────────────

it('M1 adds a reciter (a root group) with OLD\'s fields and returns to Main', function () {
    $session = H::session(H::admin(['telawah.addgroup'], 'adder'));
    $this->withSession($session)->get(route('admin.telawah.main.groups.create'))->assertOk()
        ->assertSee('اضافة قارىء بقسم التلاوات')->assertSee('name="Title"', false)->assertSee('name="Des"', false);

    $this->withSession($session)->post(route('admin.telawah.main.groups.store'), ['Title' => 'قارىء جديد', 'Des' => 'وصف'])
        ->assertRedirect('/recite.htm')->assertSessionHas('contextual_admin_success');

    $row = DB::connection('main')->table('nuke_telawah_groups')->where('title', 'قارىء جديد')->first();
    expect((int) $row->parent_id)->toBe(0)->and($row->des)->toBe('وصف')
        ->and((int) $row->child)->toBe(0)->and((int) $row->telawah)->toBe(0)
        ->and(twAudit())->toBe(['add_group'])
        ->and(twAuditRow())->toMatchArray(['module' => 'telawah', 'entity_type' => 'group', 'entity_id' => (int) $row->id])
        ->and(twAuditRow()['metadata']['parent_id'])->toBe(0);

    $this->get('/recite.htm')->assertSee('قارىء جديد');
});

it('M1 refuses a reciter title already used at the root, as OLD did', function () {
    $this->withSession(H::session($this->boss))->from(route('admin.telawah.main.groups.create'))
        ->post(route('admin.telawah.main.groups.store'), ['Title' => 'القارىء'])
        ->assertRedirect(route('admin.telawah.main.groups.create'))->assertSessionHasErrors('Title');

    expect(DB::connection('main')->table('nuke_telawah_groups')->where('title', 'القارىء')->count())->toBe(1)
        ->and(twAudit())->toBe([]);

    $this->withSession(H::session($this->boss))->post(route('admin.telawah.main.groups.store'), ['Title' => ''])->assertSessionHasErrors('Title');
});

// ─── G1 «تعديل القسم» ──────────────────────────────────────────────────────

it('G1 edits title and comment, keeps the parent (not OLD\'s accidental reset to 0), and returns to the group', function () {
    $session = H::session(H::admin(['telawah.editgroup'], 'editor'));
    $this->withSession($session)->get(route('admin.telawah.groups.edit', $this->group))->assertOk()
        ->assertSee('value="القسم"', false)->assertSee('value="تعليق"', false)->assertDontSee('name="parent_id"', false);

    $this->withSession($session)->put(route('admin.telawah.groups.update', $this->group), ['title' => 'قسم معدل', 'Des' => 'تعليق جديد', 'parent_id' => 0, 'telawah_group' => 0])
        ->assertRedirect("/recite-group-{$this->group}.htm")->assertSessionHas('contextual_admin_success');

    $row = twGroup($this->group);
    expect($row->title)->toBe('قسم معدل')->and($row->des)->toBe('تعليق جديد')
        ->and((int) $row->parent_id)->toBe($this->reciter)
        ->and((int) $row->child)->toBe(1)->and((int) $row->telawah)->toBe(2)
        ->and((int) twGroup($this->reciter)->child)->toBe(2)
        ->and(twAudit())->toBe(['edit_group'])
        ->and(array_keys(twAuditRow()['metadata']['changes']))->toBe(['title', 'des']);
});

// ─── G4 «إضافة قسم فرعى» ──────────────────────────────────────────────────

it('G4 adds a sub-group under the page\'s group, adds 1 to its child counter, and returns to the group', function () {
    $session = H::session(H::admin(['telawah.addgroup'], 'adder'));
    $this->withSession($session)->get(route('admin.telawah.groups.subgroups.create', $this->group))->assertOk()->assertSee('اضافة قسم فرعى');

    $this->withSession($session)->post(route('admin.telawah.groups.subgroups.store', $this->group), ['Title' => 'فرعي جديد', 'Des' => 'د'])
        ->assertRedirect("/recite-group-{$this->group}.htm");

    $row = DB::connection('main')->table('nuke_telawah_groups')->where('title', 'فرعي جديد')->first();
    expect((int) $row->parent_id)->toBe($this->group)
        ->and((int) twGroup($this->group)->child)->toBe(2)
        ->and((int) twGroup($this->reciter)->child)->toBe(2)
        ->and(twAudit())->toBe(['add_group'])
        ->and(twAuditRow()['metadata']['parent_id'])->toBe($this->group);
});

it('G4 refuses a duplicate title under the same parent and leaves the counter alone; the same title elsewhere is allowed', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->post(route('admin.telawah.groups.subgroups.store', $this->group), ['Title' => 'الفرعي'])
        ->assertSessionHasErrors(['Title' => "عفواً .... لن تتمكن من إضافة قسم '' الفرعي '' إلى قاعدة بيانات الموقع - يوجد بالموقع نفس البيانات الجديدة التى حاولت إدخالها"]);
    expect((int) twGroup($this->group)->child)->toBe(1)->and(twAudit())->toBe([]);

    $this->withSession($session)->post(route('admin.telawah.groups.subgroups.store', $this->sibling), ['Title' => 'الفرعي'])
        ->assertRedirect("/recite-group-{$this->sibling}.htm");
    expect((int) twGroup($this->sibling)->child)->toBe(1);
});

// ─── G5 «حذف القسم» ─────────────────────────────────────────────────────────

it('G5 refuses a group that still has recitations, as OLD did', function () {
    // No sub-groups left, so only OLD's own recitations check applies.
    DB::connection('main')->table('nuke_telawah_groups')->where('id', $this->child)->delete();
    DB::connection('main')->table('nuke_telawah_groups')->where('id', $this->group)->update(['child' => 0]);

    $this->withSession(H::session($this->boss))->from(route('admin.telawah.groups.delete', $this->group))
        ->delete(route('admin.telawah.groups.destroy', $this->group))
        ->assertRedirect(route('admin.telawah.groups.delete', $this->group))
        ->assertSessionHasErrors(['group' => "عفواً .... لن تتمكن من مسح '' القسم '' من قاعدة بيانات الموقع - يجب عليك أولا مسح جميع الخطب و السلاسل الخاصة بهذه القسم أولا من الموقع"]);

    expect(twGroup($this->group))->not->toBeNull()->and((int) twGroup($this->reciter)->child)->toBe(2)
        ->and(twItem($this->item))->not->toBeNull()->and(twAudit())->toBe([]);
});

it('G5 refuses a group that still has sub-groups (owner safety correction), cascading nothing', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->where('group_id', $this->group)->update(['group_id' => $this->sibling]);

    $this->withSession(H::session($this->boss))->delete(route('admin.telawah.groups.destroy', $this->group))
        ->assertSessionHasErrors(['group' => "عفواً .... لن تتمكن من مسح '' القسم '' من قاعدة بيانات الموقع - يجب عليك أولا مسح أو نقل جميع الأقسام الفرعية الخاصة بهذا القسم"]);

    expect(twGroup($this->group))->not->toBeNull()->and(twGroup($this->child))->not->toBeNull()
        ->and((int) twGroup($this->reciter)->child)->toBe(2)->and(twAudit())->toBe([]);
});

it('G5 deletes a genuinely empty group, takes 1 from the parent (never below 0), and returns to Main', function () {
    $session = H::session(H::admin(['telawah.delgroup'], 'deleter'));
    $this->withSession($session)->get(route('admin.telawah.groups.delete', $this->sibling))->assertOk()->assertSee("هل ترغب في مسح قسم '' الأخ ''", false);

    $this->withSession($session)->delete(route('admin.telawah.groups.destroy', $this->sibling))
        ->assertRedirect('/recite.htm')->assertSessionHas('contextual_admin_success');
    expect(twGroup($this->sibling))->toBeNull()->and((int) twGroup($this->reciter)->child)->toBe(1)
        ->and(twAudit())->toBe(['del_group'])->and(twAuditRow()['metadata']['parent_id'])->toBe($this->reciter);

    DB::connection('main')->table('nuke_telawah_groups')->where('id', $this->otherReciter)->update(['child' => 0]);
    $this->withSession($session)->delete(route('admin.telawah.groups.destroy', $this->otherSub))->assertRedirect('/recite.htm');
    expect((int) twGroup($this->otherReciter)->child)->toBe(0);
});

// ─── G3 «إضافة تلاوة» ──────────────────────────────────────────────────────

it('G3 adds a recitation with OLD\'s fields, the reciter as parent_id and the group counter, and returns to the group', function () {
    $session = H::session(H::admin(['telawah.addtelawah'], 'adder'));
    $this->withSession($session)->get(route('admin.telawah.groups.items.create', $this->child))->assertOk()
        ->assertSee('إضافة تلاوة جديد ضمن قسم : الفرعي')->assertSee('<option value="3" >آل عمران</option>', false)
        ->assertSee('name="Telawahfile"', false)->assertSee('name="Telawahsize"', false)->assertSee('name="MoreInfo"', false);

    $before = time();
    $this->withSession($session)->post(route('admin.telawah.groups.items.store', $this->child), twItemForm(['vedio' => '1']))
        ->assertRedirect("/recite-group-{$this->child}.htm")->assertSessionHas('contextual_admin_success');

    $row = DB::connection('main')->table('nuke_telawah_telawah')->where('title', 'سورة آل عمران')->first();
    expect((int) $row->group_id)->toBe($this->child)
        ->and((int) $row->parent_id)->toBe($this->reciter)
        ->and($row->link)->toBe('https://x/3.mp3')
        ->and((int) $row->linksize)->toBe(1572864)
        ->and((int) $row->sorah)->toBe(3)->and((int) $row->vedio)->toBe(1)
        ->and($row->notes)->toBe('تعليق التلاوة')
        ->and((int) $row->mytime)->toBeGreaterThanOrEqual($before)
        ->and((int) twGroup($this->child)->telawah)->toBe(1)
        ->and((int) twGroup($this->group)->telawah)->toBe(2)
        ->and(twAudit())->toBe(['add_item'])
        ->and(twAuditRow()['metadata'])->toMatchArray(['group_id' => $this->child, 'parent_id' => $this->reciter]);

    $this->get("/recite-group-{$this->child}.htm")->assertSee('سورة آل عمران');
});

it('G3 on a reciter (root) group stores the reciter itself as parent_id; «لا يوجد» stores sura 0', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.telawah.groups.items.store', $this->reciter), twItemForm(['sorah' => '', 'Telawahsize' => '']))
        ->assertRedirect("/recite-group-{$this->reciter}.htm");

    $row = DB::connection('main')->table('nuke_telawah_telawah')->where('title', 'سورة آل عمران')->first();
    expect((int) $row->parent_id)->toBe($this->reciter)->and((int) $row->sorah)->toBe(0)->and((int) $row->linksize)->toBe(0)
        ->and((int) twGroup($this->reciter)->telawah)->toBe(1);
});

it('G3 keeps OLD\'s 256-byte title refusal and refuses an unknown sura, a missing link and an over-long comment, changing nothing', function () {
    $session = H::session($this->boss);
    $url = route('admin.telawah.groups.items.store', $this->group);

    $this->withSession($session)->post($url, twItemForm(['title' => str_repeat('ب', 129)]))
        ->assertSessionHasErrors(['title' => 'عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات']);
    $this->withSession($session)->post($url, twItemForm(['sorah' => '99']))->assertSessionHasErrors('sorah');
    $this->withSession($session)->post($url, twItemForm(['Telawahfile' => '']))->assertSessionHasErrors('Telawahfile');
    $this->withSession($session)->post($url, twItemForm(['MoreInfo' => str_repeat('x', 256)]))->assertSessionHasErrors('MoreInfo');

    expect(DB::connection('main')->table('nuke_telawah_telawah')->count())->toBe(2)
        ->and((int) twGroup($this->group)->telawah)->toBe(2)->and(twAudit())->toBe([]);
});

// ─── I1 «تعديل» ─────────────────────────────────────────────────────────────

it('I1 offers OLD\'s group choices and the 2-decimal MB size', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->update(['linksize' => 1572864]);

    $html = $this->withSession(H::session(H::admin(['telawah.edittelawah'], 'editor')))->get(route('admin.telawah.items.edit', $this->item))
        ->assertOk()->assertSee('value="1.50"', false)->assertSee('<option value="1" selected>الفاتحة</option>', false)->getContent();

    preg_match('#<select name="group">(.*?)</select>#s', $html, $m);
    preg_match_all('#value="(\d+)"#', $m[1], $ids);
    expect(array_map('intval', $ids[1]))->toBe([$this->reciter, $this->group, $this->child]);
});

it('I1 edits OLD\'s fields without a group change: counters and parent_id untouched, unchanged size not rounded, back to the item', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->update(['parent_id' => 4242]); // stale root: not rewritten without a move

    $this->withSession(H::session(H::admin(['telawah.edittelawah'], 'editor')))->put(route('admin.telawah.items.update', $this->item), [
        'title' => 'الفاتحة معدلة', 'vedio' => '1', 'sorah' => '2', 'Telawah_link' => 'https://x/new.mp3',
        'Telawahsize' => '1.18', 'Des' => 'ملاحظة جديدة', 'group' => (string) $this->group,
    ])->assertRedirect("/recite-item-{$this->item}.htm")->assertSessionHas('contextual_admin_success');

    $row = twItem($this->item);
    expect($row->title)->toBe('الفاتحة معدلة')->and((int) $row->vedio)->toBe(1)->and((int) $row->sorah)->toBe(2)
        ->and($row->link)->toBe('https://x/new.mp3')->and($row->notes)->toBe('ملاحظة جديدة')
        ->and((int) $row->linksize)->toBe(1234567)
        ->and((int) $row->group_id)->toBe($this->group)->and((int) $row->parent_id)->toBe(4242)
        ->and((int) $row->hits)->toBe(9)->and((int) $row->online)->toBe(77)->and((int) $row->mytime)->toBe(1000)
        ->and((int) twGroup($this->group)->telawah)->toBe(2)
        ->and(twAudit())->toBe(['edit_item'])->and(twAuditRow()['metadata']['moved'])->toBeNull();

    $this->withSession(H::session($this->boss))->put(route('admin.telawah.items.update', $this->item), [
        'title' => 'الفاتحة معدلة', 'vedio' => '1', 'Telawah_link' => 'https://x/new.mp3', 'Telawahsize' => '2', 'group' => (string) $this->group,
    ])->assertRedirect();
    expect((int) twItem($this->item)->linksize)->toBe(2097152)->and((int) twItem($this->item)->sorah)->toBe(0);
});

it('I1 with a group change moves both counters and recomputes parent_id from the new group', function () {
    $this->withSession(H::session($this->boss))->put(route('admin.telawah.items.update', $this->item), [
        'title' => 'سورة الفاتحة', 'vedio' => '0', 'Telawah_link' => 'https://x/1.mp3', 'Telawahsize' => '1.18', 'group' => (string) $this->child,
    ])->assertRedirect("/recite-item-{$this->item}.htm");
    expect((int) twItem($this->item)->group_id)->toBe($this->child)->and((int) twItem($this->item)->parent_id)->toBe($this->reciter)
        ->and((int) twGroup($this->group)->telawah)->toBe(1)->and((int) twGroup($this->child)->telawah)->toBe(1)
        ->and(twAuditRow()['metadata']['moved'])->toBe(['from_group' => $this->group, 'to_group' => $this->child, 'parent_id' => $this->reciter]);

    // Up to the stored root; the old group's counter is floored at 0.
    DB::connection('main')->table('nuke_telawah_groups')->where('id', $this->child)->update(['telawah' => 0]);
    $this->withSession(H::session($this->boss))->put(route('admin.telawah.items.update', $this->item), [
        'title' => 'سورة الفاتحة', 'vedio' => '0', 'Telawah_link' => 'https://x/1.mp3', 'group' => (string) $this->reciter,
    ])->assertRedirect();
    expect((int) twItem($this->item)->group_id)->toBe($this->reciter)->and((int) twItem($this->item)->parent_id)->toBe($this->reciter)
        ->and((int) twGroup($this->child)->telawah)->toBe(0)->and((int) twGroup($this->reciter)->telawah)->toBe(1);
});

it('I1 refuses a group OLD\'s select did not offer, changing nothing', function () {
    $this->withSession(H::session($this->boss))->put(route('admin.telawah.items.update', $this->item), [
        'title' => 'x', 'vedio' => '0', 'Telawah_link' => 'https://x/1.mp3', 'group' => (string) $this->otherSub,
    ])->assertSessionHasErrors(['group' => 'القسم المختار غير متاح لهذه التلاوة']);

    expect(twItem($this->item)->title)->toBe('سورة الفاتحة')->and((int) twItem($this->item)->group_id)->toBe($this->group)
        ->and((int) twGroup($this->otherSub)->telawah)->toBe(0)->and(twAudit())->toBe([]);
});

// ─── I3 «نقل إلى قسم شيخ أخر» ─────────────────────────────────────────────

it('I3 renders OLD\'s two selects, and the sub-group select reloads for the chosen reciter', function () {
    $session = H::session(H::admin(['telawah.edittelawah'], 'mover'));
    $html = $this->withSession($session)->get(route('admin.telawah.items.move', $this->item))->assertOk()->getContent();
    expect($html)->toContain('<option value="'.$this->reciter.'" selected>القارىء</option>')
        ->toContain('<option value="'.$this->group.'" selected>القسم</option>')
        ->toContain('<option value="'.$this->sibling.'" >الأخ</option>')
        ->toContain('<option value="'.$this->otherReciter.'" >قارىء آخر</option>')
        ->not->toContain('>قسم آخر<');

    $partial = $this->withSession($session)->get(route('admin.telawah.move-options.sub-groups', ['reciter' => $this->otherReciter]))->assertOk()->getContent();
    expect($partial)->toContain('إختر القسم')->toContain('<option value="'.$this->otherSub.'" >قسم آخر</option>')->not->toContain('الأخ');
});

it('I3 moves to another reciter\'s sub-group with both counters and parent_id, and returns to the item', function () {
    $this->withSession(H::session(H::admin(['telawah.edittelawah'], 'mover')))
        ->put(route('admin.telawah.items.move.save', $this->item), ['group_id' => $this->otherReciter, 'sub_group_id' => $this->otherSub])
        ->assertRedirect("/recite-item-{$this->item}.htm")->assertSessionHas('contextual_admin_success', 'تم نقل التلاوة إلى القسم الجديد');

    $row = twItem($this->item);
    expect((int) $row->group_id)->toBe($this->otherSub)->and((int) $row->parent_id)->toBe($this->otherReciter)
        ->and($row->title)->toBe('سورة الفاتحة')->and((int) $row->linksize)->toBe(1234567)
        ->and((int) twGroup($this->group)->telawah)->toBe(1)->and((int) twGroup($this->otherSub)->telawah)->toBe(1)
        ->and(twAudit())->toBe(['move_item'])
        ->and(twAuditRow()['metadata'])->toBe(['group_id' => ['from' => $this->group, 'to' => $this->otherSub], 'parent_id' => ['from' => $this->reciter, 'to' => $this->otherReciter]]);

    // «إختر القسم» (0) moves to the reciter itself.
    $this->withSession(H::session($this->boss))->put(route('admin.telawah.items.move.save', $this->item), ['group_id' => $this->reciter, 'sub_group_id' => 0])->assertRedirect();
    expect((int) twItem($this->item)->group_id)->toBe($this->reciter)->and((int) twItem($this->item)->parent_id)->toBe($this->reciter)
        ->and((int) twGroup($this->otherSub)->telawah)->toBe(0)->and((int) twGroup($this->reciter)->telawah)->toBe(1);
});

it('I3 to the item\'s own group changes nothing and writes no audit row', function () {
    $this->withSession(H::session($this->boss))->put(route('admin.telawah.items.move.save', $this->item), ['group_id' => $this->reciter, 'sub_group_id' => $this->group])
        ->assertRedirect("/recite-item-{$this->item}.htm")->assertSessionHas('contextual_admin_success', 'التلاوة موجودة بالفعل في هذا القسم');

    expect((int) twItem($this->item)->group_id)->toBe($this->group)->and((int) twGroup($this->group)->telawah)->toBe(2)->and(twAudit())->toBe([]);
});

it('I3 refuses no reciter (OLD wrote group_id = 0), a non-root reciter and a sub-group of another reciter', function () {
    $session = H::session($this->boss);
    $url = route('admin.telawah.items.move.save', $this->item);
    $this->withSession($session)->put($url, ['group_id' => 0, 'sub_group_id' => 0])->assertSessionHasErrors(['group_id' => 'من فضلك إختر الشيخ']);
    $this->withSession($session)->put($url, ['group_id' => $this->group, 'sub_group_id' => 0])->assertSessionHasErrors('group_id');
    $this->withSession($session)->put($url, ['group_id' => $this->reciter, 'sub_group_id' => $this->otherSub])->assertSessionHasErrors(['sub_group_id' => 'القسم المختار لا يتبع هذا الشيخ']);

    expect((int) twItem($this->item)->group_id)->toBe($this->group)->and(twAudit())->toBe([]);
});

// ─── I2 «مسح» ───────────────────────────────────────────────────────────────

it('I2 deletes the item and only its own mirrors, comments and technical row, floors the counter, and returns to the former group', function () {
    $session = H::session(H::admin(['telawah.deltelawah'], 'deleter'));
    $this->withSession($session)->get(route('admin.telawah.items.delete', $this->item))->assertOk()
        ->assertSee('سورة الفاتحة')->assertSee('الوصلات البديلة (1) والتعليقات (3)');

    $this->withSession($session)->delete(route('admin.telawah.items.destroy', $this->item))
        ->assertRedirect("/recite-group-{$this->group}.htm")->assertSessionHas('contextual_admin_success');

    $main = DB::connection('main');
    expect(twItem($this->item))->toBeNull()
        ->and($main->table('nuke_telawah_mirror')->where('telawa_id', $this->item)->count())->toBe(0)
        ->and($main->table('nuke_telawah_comments')->where('khid', $this->item)->count())->toBe(0)
        ->and($main->table('nuke_telawah_advanced')->where('id', $this->item)->count())->toBe(0)
        ->and(twItem($this->other))->not->toBeNull()
        ->and($main->table('nuke_telawah_mirror')->where('id', $this->otherMirror)->exists())->toBeTrue()
        ->and($main->table('nuke_telawah_comments')->where('id', $this->otherComment)->exists())->toBeTrue()
        ->and($main->table('nuke_telawah_advanced')->where('id', $this->other)->exists())->toBeTrue()
        ->and((int) twGroup($this->group)->telawah)->toBe(1)
        ->and($main->table('nuke_telawah_groups')->count())->toBe(6)
        ->and(twAudit())->toBe(['del_item'])
        ->and(twAuditRow()['metadata'])->toBe(['group_id' => $this->group, 'mirrors_deleted' => [$this->mirror], 'comments_deleted' => 3, 'advanced_deleted' => 1]);

    $main->table('nuke_telawah_groups')->where('id', $this->group)->update(['telawah' => 0]);
    $this->withSession($session)->delete(route('admin.telawah.items.destroy', $this->other))->assertRedirect("/recite-group-{$this->group}.htm");
    expect((int) twGroup($this->group)->telawah)->toBe(0);
});

it('I2 of an item whose group is gone returns to Main', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->update(['group_id' => 9999]);

    $this->withSession(H::session($this->boss))->delete(route('admin.telawah.items.destroy', $this->item))->assertRedirect('/recite.htm');
    expect(twItem($this->item))->toBeNull();
});

// ─── regression: the closed mirror / link-status controls ──────────────────

it('keeps the closed mirror controls and the link-status control on the item page', function () {
    $html = $this->withSession(H::session($this->boss))->get("/recite-item-{$this->item}.htm")->assertOk()->getContent();
    expect($html)->toContain(route('admin.mirrors.telawah.edit', $this->mirror))
        ->toContain(route('admin.mirrors.telawah.destroy', $this->mirror))
        ->toContain('href="'.route('admin.link-quality.telawah.item', $this->item).'"');
});
