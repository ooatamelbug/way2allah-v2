<?php

/*
 * W2ACD OLD public admin parity, Phase 2 (2026-11-02; decision-log #75) — the CD
 * group page `cds-group-{id}.htm` (OLD `Group()`) and its «لوحة تحكم قسم
 * الاسطوانات»: G1 «اضافة صورة للقسم», G2 «تعديل القسم», G4 «إضافة قسم فرعي»,
 * G5 «حذف القسم». G3 «إضافة إسطوانة» is owner-retired.
 */

use App\Domain\Admin\Actions\Cds\CdGroupIconFiles;
use App\Domain\Admin\Actions\Cds\DeleteCdGroupAction;
use App\Domain\Admin\Actions\Cds\InsertCdSubGroupAction;
use App\Domain\Admin\Actions\Cds\UpdateCdGroupAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Content\Models\W2acdGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\CdAdminHarness;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->media = sys_get_temp_dir().'/w2acd-group-'.bin2hex(random_bytes(4));
    config(['admin_cds.media_root' => $this->media, 'media.base_url' => '/media']);

    // root ─┬─ group (child: sub-a, sub-b; CDs: cd1, cd2 hidden)
    //       └─ sibling
    $this->root = H::insert('nuke_w2acd_groups', ['title' => 'القسم الرئيسي', 'parent_id' => 0, 'child' => 2, 'module_type' => 'cd', 'des' => '']);
    $this->group = H::insert('nuke_w2acd_groups', ['title' => 'سلسلة العقيدة', 'parent_id' => $this->root, 'child' => 2, 'w2acd' => 2, 'module_type' => 'cd', 'des' => 'وصف القسم', 'hits' => 5]);
    $this->sibling = H::insert('nuke_w2acd_groups', ['title' => 'قسم شقيق', 'parent_id' => $this->root, 'module_type' => 'cd', 'des' => '']);
    $this->subA = H::insert('nuke_w2acd_groups', ['title' => 'فرعي أول', 'parent_id' => $this->group, 'module_type' => 'cd', 'des' => 'تعليق الفرعي', 'hits' => 7]);
    $this->subB = H::insert('nuke_w2acd_groups', ['title' => 'فرعي ثان', 'parent_id' => $this->group, 'module_type' => 'cd', 'des' => '']);
    $this->cd1 = H::insert('nuke_w2acd_w2acd', ['group_id' => $this->group, 'title' => 'إسطوانة ظاهرة', 'hidden' => 0, 'order_in_group' => 2, 'mytime' => 100, 'thumbnail' => '']);
    $this->cd2 = H::insert('nuke_w2acd_w2acd', ['group_id' => $this->group, 'title' => 'إسطوانة مخفية', 'hidden' => 1, 'order_in_group' => 1, 'mytime' => 200, 'thumbnail' => '']);
    $this->cdSub = H::insert('nuke_w2acd_w2acd', ['group_id' => $this->subA, 'title' => 'إسطوانة الفرعي', 'hidden' => 0, 'thumbnail' => '']);
    $this->manager = H::admin(['w2acd.managecds'], 'cd-manager');
});

afterEach(function () {
    File::deleteDirectory($this->media);
});

function grpPage(int $id, $admin = null): string
{
    if ($admin === null) {
        H::visitor();

        return test()->get("/cds-group-{$id}.htm")->assertOk()->getContent();
    }

    return test()->withSession(H::session($admin))->get("/cds-group-{$id}.htm")->assertOk()->getContent();
}

function grpRow(int $id): ?object
{
    return DB::connection('main')->table('nuke_w2acd_groups')->where('id', $id)->first();
}

function grpIconFile(int $id): string
{
    return test()->media.'/w2acd/icons/'.intdiv($id, 1000).'/'.$id.'.jpg';
}

function grpAudit(string $action, int $id): bool
{
    return DB::connection('main')->table('admin_audit_log')->where('action', $action)->where('module', 'w2acd')->where('entity_type', 'cd_group')->where('entity_id', $id)->exists();
}

// ─── public page ────────────────────────────────────────────────────────────

it('page: cds-group-{id}.htm shows the group, its breadcrumb, sub-groups (id DESC) and CDs (order_in_group DESC)', function () {
    $html = grpPage($this->group);

    expect($html)->toContain('<title>مجموعة سلسلة العقيدة')
        ->toContain('href="/cds-main.htm"')->toContain('الاسطوانات الدعوية')
        ->toContain('href="/cds-group-'.$this->root.'.htm"')
        ->toContain('قائمة الأقسام الفرعية')
        ->toContain('قائمة الإسطوانات بقسم : سلسلة العقيدة')
        ->toContain('href="/cds-item-'.$this->cd1.'.htm"')
        ->and(strpos($html, 'data-cd-subgroup="'.$this->subB.'"'))->toBeLessThan(strpos($html, 'data-cd-subgroup="'.$this->subA.'"'))
        ->and(strpos($html, 'href="/cds-item-'.$this->cd1.'.htm"'))->toBeLessThan(strpos($html, 'href="/cds-item-'.$this->cd2.'.htm"'))
        ->and($html)->not->toContain('href="/cds-item-'.$this->cdSub.'.htm"')
        ->and($html)->not->toContain('href="/cds-group-'.$this->sibling.'.htm"');
});

it('page: a sub-group card shows its comment (or «بدون تعليق»), live CD and sub-group counts, visits and the default icon', function () {
    $html = grpPage($this->group);

    expect($html)->toContain('تعليق الفرعي')->toContain('بدون تعليق')
        ->toContain('الإسطوانات : 1 إسطوانة')->toContain('الأقسام الفرعية : 0 قسم')
        ->toContain('الزيارات : 7 زيارة')
        ->toContain('src="/images/way2_cddefault.png"');
});

it('page: a sub-group card follows OLD group_cd_content.htm — title first, the 89×88 icon, then sub-groups, CDs, visits, comment', function () {
    $html = grpPage($this->group);

    $card = function (int $id) use ($html): string {
        $start = strpos($html, 'data-cd-subgroup="'.$id.'"');
        expect($start)->not->toBeFalse();

        return substr($html, $start, strpos($html, '</a>', $start) - $start);
    };

    $a = $card($this->subA);
    $order = array_map(fn (string $needle) => strpos($a, $needle), [
        'w2a-cd-subgroup-title', 'فرعي أول', 'w2a-cd-subgroup-icon', 'width="89" height="88"',
        'الأقسام الفرعية : 0 قسم', 'الإسطوانات : 1 إسطوانة', 'الزيارات : 7 زيارة', 'التعليق : تعليق الفرعي',
    ]);
    expect($order)->not->toContain(false)->and($order)->toBe(collect($order)->sort()->values()->all())
        ->and($card($this->subB))->toContain('التعليق : بدون تعليق')
        // The shared Anasheed icon box (48×48, built for a font icon) is not used here.
        ->and($a)->not->toContain('w2a-subgroup-icon-wrap')->not->toContain('w2a-reciter-meta');
});

it('page: lists hidden CDs like OLD ListW2ACD() and cds-main.htm, with no hidden filter', function () {
    expect(grpPage($this->group))->toContain('إسطوانة مخفية');
});

it('page: counts the visit, as OLD did', function () {
    grpPage($this->group);
    expect((int) grpRow($this->group)->hits)->toBe(6);
});

it('page: a group with neither sub-groups nor CDs shows neither list', function () {
    $html = grpPage($this->sibling);
    expect($html)->toContain('قسم شقيق')->not->toContain('قائمة الأقسام الفرعية')->not->toContain('قائمة الإسطوانات بقسم');
});

it('page: an unknown group is a 404 and group 0 is cds-main', function () {
    H::visitor();
    $this->get('/cds-group-999999.htm')->assertNotFound();
    $this->get('/cds-group-0.htm')->assertRedirect('/cds-main.htm');
});

it('page: pages by 24 with links to the -page-{n}.htm segment the route reads', function () {
    foreach (range(1, 30) as $i) {
        H::insert('nuke_w2acd_w2acd', ['group_id' => $this->sibling, 'title' => "إسطوانة رقم {$i}", 'order_in_group' => 100 - $i, 'mytime' => $i, 'thumbnail' => '']);
    }
    $first = grpPage($this->sibling);
    expect($first)->toContain('إسطوانة رقم 1<')->not->toContain('إسطوانة رقم 25<')
        ->toContain('href="'.route('w2acd.group.paged', ['group' => $this->sibling, 'page' => 2]).'"');

    $second = $this->get("/cds-group-{$this->sibling}-page-2.htm")->assertOk()->getContent();
    expect($second)->toContain('إسطوانة رقم 25<')->not->toContain('إسطوانة رقم 1<');
});

it('page: shows a group icon only when icon = 1 and the file exists', function () {
    DB::connection('main')->table('nuke_w2acd_groups')->where('id', $this->subA)->update(['icon' => 1]);
    expect(grpPage($this->group))->not->toContain('/media/w2acd/icons/');

    File::ensureDirectoryExists(dirname(grpIconFile($this->subA)));
    imagejpeg(imagecreatetruecolor(75, 100), grpIconFile($this->subA));
    expect(grpPage($this->group))->toContain('src="/media/w2acd/icons/0/'.$this->subA.'.jpg?v=');
});

// ─── contextual controls ────────────────────────────────────────────────────

it('controls: visitors and admins without w2acd.managecds see no panel', function () {
    expect(grpPage($this->group))->not->toContain('لوحة تحكم قسم الاسطوانات')->not->toContain('data-contextual-admin');
    H::visitor();
    expect(grpPage($this->group, H::admin(['polls.managepolls'], 'cd-other')))->not->toContain('لوحة تحكم قسم الاسطوانات');
});

it('controls: the manager and a super-admin see G1, G2, G4, G5 in OLD\'s order — and never G3 «إضافة إسطوانة»', function () {
    $id = ['cdGroup' => $this->group];
    foreach ([$this->manager, H::superAdmin('cd-boss')] as $admin) {
        $html = grpPage($this->group, $admin);
        $positions = array_map(fn (string $route) => strpos($html, 'href="'.route($route, $id).'"'), [
            'admin.cds.groups.icon', 'admin.cds.groups.edit', 'admin.cds.groups.subgroups.create', 'admin.cds.groups.delete',
        ]);

        expect($html)->toContain('لوحة تحكم قسم الاسطوانات')
            ->toContain('اضافة صورة للقسم')->toContain('تعديل القسم')->toContain('إضافة قسم فرعي')->toContain('حذف القسم')
            ->not->toContain('إضافة إسطوانة')->not->toContain('return_to=')
            ->and($positions)->each->toBeInt()
            ->and($positions)->toBe(array_values(array_unique($positions)))
            ->and($positions)->toEqual(collect($positions)->sort()->values()->all());
        H::visitor();
    }
});

it('controls: every admin route refuses admins without w2acd.managecds and writes nothing', function () {
    $other = H::admin(['polls.managepolls'], 'cd-other');
    $session = H::session($other);
    $before = DB::connection('main')->table('nuke_w2acd_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    $id = ['cdGroup' => $this->sibling];

    foreach (['icon', 'edit', 'subgroups.create', 'delete'] as $page) {
        $this->withSession($session)->get(route("admin.cds.groups.{$page}", $id))->assertForbidden();
    }
    $this->withSession($session)->post(route('admin.cds.groups.icon.store', $id), ['file2upl' => UploadedFile::fake()->image('i.jpg', 20, 20)])->assertForbidden();
    $this->withSession($session)->put(route('admin.cds.groups.update', $id), ['title' => 'x', 'parent_id' => 0])->assertForbidden();
    $this->withSession($session)->post(route('admin.cds.groups.subgroups.store', $id), ['Title' => 'x'])->assertForbidden();
    $this->withSession($session)->delete(route('admin.cds.groups.destroy', $id))->assertForbidden();

    expect(DB::connection('main')->table('nuke_w2acd_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe($before)
        ->and(is_file(grpIconFile($this->sibling)))->toBeFalse();
});

it('controls: the actions re-check the permission themselves', function () {
    $other = H::admin(['polls.managepolls'], 'cd-other');
    $group = W2acdGroup::findOrFail($this->sibling);

    expect(fn () => app(DeleteCdGroupAction::class)->execute($other, $group))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(InsertCdSubGroupAction::class)->execute($other, $group, ['Title' => 'x']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateCdGroupAction::class)->execute($other, $group, ['title' => 'x', 'parent_id' => 0]))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(CdGroupIconFiles::class)->upload($other, $group, UploadedFile::fake()->image('i.jpg', 20, 20)))
        ->toThrow(AuthorizationException::class);
    expect(grpRow($this->sibling))->not->toBeNull();
});

// ─── G1 «اضافة صورة للقسم» ──────────────────────────────────────────────────

it('G1: stores a JPEG byte for byte at OLD\'s path, sets icon = 1, audits, and returns to the parent group', function () {
    $this->withSession(H::session($this->manager))->get(route('admin.cds.groups.icon', ['cdGroup' => $this->group]))
        ->assertOk()->assertSee('اختر ملف الصورة من جهازك')->assertSee('أقصى حجم مسموح به 10 كيلو بايت');

    $jpg = UploadedFile::fake()->image('icon.jpg', 75, 100);
    $bytes = file_get_contents($jpg->getRealPath());
    $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.groups.icon.store', ['cdGroup' => $this->group]), ['file2upl' => $jpg])
        ->assertRedirect("/cds-group-{$this->root}.htm")
        ->assertSessionHas(ContextualReturn::FLASH, 'جزاك الله خيرا على إضافة الأيقونة إلى الموقع.');

    expect(file_get_contents(grpIconFile($this->group)))->toBe($bytes)
        ->and((int) grpRow($this->group)->icon)->toBe(1)
        ->and(grpAudit('upload_group_icon', $this->group))->toBeTrue();
});

it('G1: converts a PNG or GIF into a real JPEG; a root group returns to cds-main', function (string $name) {
    $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.groups.icon.store', ['cdGroup' => $this->root]), ['file2upl' => UploadedFile::fake()->image($name, 40, 60)])
        ->assertRedirect('/cds-main.htm');

    expect(array_slice(getimagesize(grpIconFile($this->root)), 0, 3))->toBe([40, 60, IMAGETYPE_JPEG])
        ->and((int) grpRow($this->root)->icon)->toBe(1);
})->with(['icon.png', 'icon.gif']);

it('G1: a replacement overwrites the icon, leaves no temporary file, and the page URL changes', function () {
    File::ensureDirectoryExists(dirname(grpIconFile($this->subA)));
    imagejpeg(imagecreatetruecolor(10, 10), grpIconFile($this->subA));
    touch(grpIconFile($this->subA), time() - 100);
    DB::connection('main')->table('nuke_w2acd_groups')->where('id', $this->subA)->update(['icon' => 1]);
    $firstHash = sha1_file(grpIconFile($this->subA));
    preg_match('#/media/w2acd/icons/0/'.$this->subA.'\.jpg\?v=\d+#', grpPage($this->group), $before);

    $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.groups.icon.store', ['cdGroup' => $this->subA]), ['file2upl' => UploadedFile::fake()->image('new.png', 75, 100)]);
    H::visitor();
    preg_match('#/media/w2acd/icons/0/'.$this->subA.'\.jpg\?v=\d+#', grpPage($this->group), $after);

    expect(array_slice(getimagesize(grpIconFile($this->subA)), 0, 2))->toBe([75, 100])
        ->and(sha1_file(grpIconFile($this->subA)))->not->toBe($firstHash)
        ->and(glob(dirname(grpIconFile($this->subA)).'/.*.jpg.*'))->toBe([])
        ->and($before[0] ?? null)->not->toBeNull()
        ->and($after[0] ?? null)->not->toBe($before[0]);
});

it('G1: rejects a missing file, another type, a disguised file and an oversized one, changing nothing', function (Closure $file, string $message) {
    $this->withSession(H::session($this->manager))
        ->from(route('admin.cds.groups.icon', ['cdGroup' => $this->group]))
        ->post(route('admin.cds.groups.icon.store', ['cdGroup' => $this->group]), $file() === null ? [] : ['file2upl' => $file()])
        ->assertSessionHasErrors(['file2upl' => $message]);

    expect(is_file(grpIconFile($this->group)))->toBeFalse()->and((int) grpRow($this->group)->icon)->toBe(0);
})->with([
    'missing' => [fn () => null, 'لم يتم إختيار ملف للتحميل'],
    'pdf' => [fn () => UploadedFile::fake()->create('icon.pdf', 5, 'application/pdf'), 'هذا الإمتداد غير مسموح به'],
    'text named .jpg' => [fn () => UploadedFile::fake()->createWithContent('icon.jpg', 'not an image'), 'هذا الإمتداد غير مسموح به'],
    'over 10000 bytes' => [fn () => UploadedFile::fake()->image('icon.jpg', 500, 500)->size(20), 'حجم الملف أكبر من الحد الأقصى المسموح به'],
]);

// ─── G2 «تعديل القسم» ───────────────────────────────────────────────────────

it('G2: the form offers OLD\'s parents — grandparent level, current parent, siblings — never a descendant', function () {
    $html = $this->withSession(H::session($this->manager))->get(route('admin.cds.groups.edit', ['cdGroup' => $this->group]))
        ->assertOk()->getContent();

    expect($html)->toContain('<option value="0"')->toContain('بدون مجموعة')
        ->toContain('--القسم الرئيسي')->toContain('-----قسم شقيق')
        ->not->toContain('value="'.$this->subA.'"')->not->toContain('value="'.$this->group.'"');
});

it('G2: updates title and comment, keeps channel_id, and returns to the group page', function () {
    DB::connection('main')->table('nuke_w2acd_groups')->where('id', $this->group)->update(['channel_id' => 9]);

    $this->withSession(H::session($this->manager))
        ->put(route('admin.cds.groups.update', ['cdGroup' => $this->group]), ['title' => 'عنوان جديد', 'parent_id' => $this->root, 'Des' => 'تعليق جديد'])
        ->assertRedirect("/cds-group-{$this->group}.htm")
        ->assertSessionHas(ContextualReturn::FLASH, 'تم تعديل القسم');

    $row = grpRow($this->group);
    expect($row->title)->toBe('عنوان جديد')->and($row->des)->toBe('تعليق جديد')
        ->and((int) $row->parent_id)->toBe($this->root)->and((int) $row->channel_id)->toBe(9)
        ->and($row->module_type)->toBe('cd')
        ->and((int) grpRow($this->root)->child)->toBe(2)
        ->and(grpAudit('edit_group', $this->group))->toBeTrue();
});

it('G2: moving into a sibling moves both child counters', function () {
    $this->withSession(H::session($this->manager))
        ->put(route('admin.cds.groups.update', ['cdGroup' => $this->group]), ['title' => 'سلسلة العقيدة', 'parent_id' => $this->sibling]);

    expect((int) grpRow($this->group)->parent_id)->toBe($this->sibling)
        ->and((int) grpRow($this->root)->child)->toBe(1)
        ->and((int) grpRow($this->sibling)->child)->toBe(1);
});

it('G2: refuses a parent OLD did not offer (a descendant) and an empty title, changing nothing', function () {
    $before = (array) grpRow($this->group);
    $this->withSession(H::session($this->manager))->from(route('admin.cds.groups.edit', ['cdGroup' => $this->group]))
        ->put(route('admin.cds.groups.update', ['cdGroup' => $this->group]), ['title' => 'x', 'parent_id' => $this->subA])
        ->assertSessionHasErrors('parent_id');
    $this->withSession(H::session($this->manager))->from(route('admin.cds.groups.edit', ['cdGroup' => $this->group]))
        ->put(route('admin.cds.groups.update', ['cdGroup' => $this->group]), ['title' => '', 'parent_id' => $this->root])
        ->assertSessionHasErrors(['title' => 'من فضلك أدخل عنوان القسم']);

    expect((array) grpRow($this->group))->toBe($before);
});

// ─── G4 «إضافة قسم فرعي» ────────────────────────────────────────────────────

it('G4: creates a sub-group under this group with OLD\'s columns, counts it, and returns to this group', function () {
    $this->withSession(H::session($this->manager))->get(route('admin.cds.groups.subgroups.create', ['cdGroup' => $this->group]))
        ->assertOk()->assertSee('سلسلة العقيدة')->assertSee('أضــف قسم فرعي');

    $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.groups.subgroups.store', ['cdGroup' => $this->group]), ['Title' => 'فرعي جديد', 'Des' => 'تعليقه', 'parent_id' => $this->sibling])
        ->assertRedirect("/cds-group-{$this->group}.htm");

    $new = DB::connection('main')->table('nuke_w2acd_groups')->where('title', 'فرعي جديد')->first();
    expect($new)->not->toBeNull()
        ->and((int) $new->parent_id)->toBe($this->group)
        ->and($new->des)->toBe('تعليقه')->and($new->module_type)->toBe('cd')
        ->and((int) $new->size)->toBe(0)->and((int) $new->dur)->toBe(0)
        ->and((int) grpRow($this->group)->child)->toBe(3)
        ->and((int) grpRow($this->sibling)->child)->toBe(0)
        ->and(grpAudit('add_group', (int) $new->id))->toBeTrue();
    expect(grpPage($this->group))->toContain('data-cd-subgroup="'.$new->id.'"');
});

it('G4: writes size and dur explicitly (NOT NULL without a default on production)', function () {
    $writes = CdAdminHarness::writesDuring(fn () => $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.groups.subgroups.store', ['cdGroup' => $this->group]), ['Title' => 'فرعي جديد']));
    $insert = collect($writes)->first(fn ($w) => str_starts_with(strtolower(ltrim($w['sql'])), 'insert into "nuke_w2acd_groups"'));

    expect($insert)->not->toBeNull()->and($insert['sql'])->toContain('"size"')->toContain('"dur"');
});

it('G4: refuses a duplicate title under the same parent and an empty title, creating nothing', function () {
    $count = DB::connection('main')->table('nuke_w2acd_groups')->count();
    $this->withSession(H::session($this->manager))->from(route('admin.cds.groups.subgroups.create', ['cdGroup' => $this->group]))
        ->post(route('admin.cds.groups.subgroups.store', ['cdGroup' => $this->group]), ['Title' => 'فرعي أول'])
        ->assertSessionHasErrors('Title');
    $this->withSession(H::session($this->manager))->from(route('admin.cds.groups.subgroups.create', ['cdGroup' => $this->group]))
        ->post(route('admin.cds.groups.subgroups.store', ['cdGroup' => $this->group]), ['Title' => ''])
        ->assertSessionHasErrors('Title');

    expect(DB::connection('main')->table('nuke_w2acd_groups')->count())->toBe($count)
        ->and((int) grpRow($this->group)->child)->toBe(2);
});

// ─── G5 «حذف القسم» ─────────────────────────────────────────────────────────

it('G5: deletes an empty group, takes 1 from the parent\'s child counter, and returns to the parent group', function () {
    $this->withSession(H::session($this->manager))->get(route('admin.cds.groups.delete', ['cdGroup' => $this->sibling]))
        ->assertOk()->assertSee("هل ترغب في مسح قسم '' قسم شقيق '' من قاعدة بيانات الموقع????", false);

    $this->withSession(H::session($this->manager))->delete(route('admin.cds.groups.destroy', ['cdGroup' => $this->sibling]))
        ->assertRedirect("/cds-group-{$this->root}.htm")
        ->assertSessionHas(ContextualReturn::FLASH, "تم حذف القسم '' قسم شقيق ''");

    expect(grpRow($this->sibling))->toBeNull()
        ->and((int) grpRow($this->root)->child)->toBe(1)
        ->and(grpAudit('del_group', $this->sibling))->toBeTrue();
});

it('G5: an empty root group goes back to cds-main; a counter already at 0 stays at 0', function () {
    $lone = H::insert('nuke_w2acd_groups', ['title' => 'قسم وحيد', 'parent_id' => 0, 'module_type' => 'cd', 'des' => '']);
    $this->withSession(H::session($this->manager))->delete(route('admin.cds.groups.destroy', ['cdGroup' => $lone]))->assertRedirect('/cds-main.htm');
    expect(grpRow($lone))->toBeNull();

    DB::connection('main')->table('nuke_w2acd_groups')->where('id', $this->root)->update(['child' => 0]);
    $this->withSession(H::session($this->manager))->delete(route('admin.cds.groups.destroy', ['cdGroup' => $this->sibling]));
    expect(grpRow($this->sibling))->toBeNull()->and((int) grpRow($this->root)->child)->toBe(0);
});

it('G5: refuses a group that holds CDs, sub-groups, or both — nothing deleted, nothing moved, no counter changed', function (string $case) {
    $target = match ($case) {
        'CDs only' => $this->subA,
        'sub-groups only' => $this->root,
        'both' => $this->group,
    };
    $message = $case === 'sub-groups only' ? 'جميع الأقسام الفرعية' : 'جميع الإسطوانات';
    $groups = DB::connection('main')->table('nuke_w2acd_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    $cds = DB::connection('main')->table('nuke_w2acd_w2acd')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

    $this->withSession(H::session($this->manager))->from(route('admin.cds.groups.delete', ['cdGroup' => $target]))
        ->delete(route('admin.cds.groups.destroy', ['cdGroup' => $target]))
        ->assertRedirect(route('admin.cds.groups.delete', ['cdGroup' => $target]))
        ->assertSessionHasErrors('group');

    expect(session('errors')->first('group'))->toContain($message)
        ->and(DB::connection('main')->table('nuke_w2acd_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe($groups)
        ->and(DB::connection('main')->table('nuke_w2acd_w2acd')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe($cds)
        ->and(grpAudit('del_group', $target))->toBeFalse();
})->with(['CDs only', 'sub-groups only', 'both']);

it('G5: keeps the icon file, as OLD did', function () {
    File::ensureDirectoryExists(dirname(grpIconFile($this->sibling)));
    imagejpeg(imagecreatetruecolor(10, 10), grpIconFile($this->sibling));
    $this->withSession(H::session($this->manager))->delete(route('admin.cds.groups.destroy', ['cdGroup' => $this->sibling]));
    expect(grpRow($this->sibling))->toBeNull()->and(is_file(grpIconFile($this->sibling)))->toBeTrue();
});
