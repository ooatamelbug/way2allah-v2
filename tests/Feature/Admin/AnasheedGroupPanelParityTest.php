<?php

/*
 * OLD public admin parity — the Anasheed group page (owner, 2026-10-23):
 * five of the six controls of «لوحة تحكم قسم المنوعات» (OLD group_cp.htm).
 * «ترتيب القسم» is dead in production and must stay absent. Visitor output
 * of the page is out of scope and must not change.
 */

use App\Domain\Identity\Models\VbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();

    $this->tmp = sys_get_temp_dir().'/w2a-anasheed-'.uniqid();
    config(['admin_anasheed.media_root' => $this->tmp.'/media', 'media.base_url' => '/media']);

    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'المنشد']);

    // root → group (the page) → child; a sibling of the group; a second root.
    $g = fn (array $a) => H::insert('nuke_anasheed_groups', $a);
    $this->root = $g(['title' => 'الجذر', 'parent_id' => 0, 'child' => 2]);
    $this->group = $g(['title' => 'القسم', 'parent_id' => $this->root, 'child' => 1, 'anasheed' => 1, 'channel_id' => 3, 'des' => 'تعليق']);
    $this->sibling = $g(['title' => 'الأخ', 'parent_id' => $this->root, 'child' => 0]);
    $this->child = $g(['title' => 'الفرعي', 'parent_id' => $this->group, 'child' => 0]);
    $this->otherRoot = $g(['title' => 'جذر آخر', 'parent_id' => 0, 'child' => 0]);
    $this->item = H::insert('nuke_anasheed_anasheed', ['title' => 'نشيد', 'group_id' => $this->group, 'mytime' => 1000]);

    $this->url = "/var-group-{$this->group}.htm";
    $this->boss = H::superAdmin();
});

afterEach(function () {
    if (is_dir($this->tmp)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->tmp);
    }
});

function agRow(int $id): ?object
{
    return DB::connection('main')->table('nuke_anasheed_groups')->where('id', $id)->first();
}

function agAudit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

function agRoutes(int $group): array
{
    return [
        ['get', route('admin.anasheed.groups.icon', $group)],
        ['post', route('admin.anasheed.groups.icon.store', $group)],
        ['get', route('admin.anasheed.groups.edit', $group)],
        ['put', route('admin.anasheed.groups.update', $group)],
        ['get', route('admin.anasheed.groups.items.create', $group)],
        ['post', route('admin.anasheed.groups.items.store', $group)],
        ['get', route('admin.anasheed.groups.subgroups.create', $group)],
        ['post', route('admin.anasheed.groups.subgroups.store', $group)],
        ['get', route('admin.anasheed.groups.delete', $group)],
        ['delete', route('admin.anasheed.groups.destroy', $group)],
    ];
}

/** A minimal real JPEG, so the detected type is image/jpeg. */
/**
 * A REAL uploaded file (test mode) with genuine image bytes, so validation
 * runs fileinfo on the content. `UploadedFile::fake()` reports a MIME type
 * guessed from the NAME and so could not catch a disguised file (found during
 * the Anasheed item T1 manual review, 2026-10-23). Test fixture only.
 *
 * @param  'jpeg'|'png'|'webp'  $format  the bytes actually written
 */
function agJpeg(string $name = 'icon.jpg', string $format = 'jpeg'): UploadedFile
{
    $image = imagecreatetruecolor(4, 4);
    $path = tempnam(sys_get_temp_dir(), 'w2a-upload-');
    match ($format) {
        'jpeg' => imagejpeg($image, $path),
        'png' => imagepng($image, $path),
        'webp' => imagewebp($image, $path),
    };

    return new UploadedFile($path, $name, null, null, true);
}

const AG_LABELS = ['اضافة صورة للقسم', 'تعديل القسم', 'إضافة مادة', 'إضافة قسم فرعي', 'حذف القسم'];

// ─── the public page ────────────────────────────────────────────────────────

it('shows a visitor nothing, and a signed-in member nothing', function () {
    $html = $this->get($this->url)->assertOk()->getContent();
    expect($html)->not->toContain('data-contextual-admin')->not->toContain('لوحة تحكم قسم المنوعات')->not->toContain('/admincp/anasheed');

    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    $html = $this->actingAs($member, 'vbulletin')->get($this->url)->assertOk()->getContent();
    expect($html)->not->toContain('data-contextual-admin')->not->toContain('لوحة تحكم قسم المنوعات');
});

it('shows a super-admin the five OLD controls in OLD\'s order, and never the dead «ترتيب القسم»', function () {
    $html = $this->withSession(H::session($this->boss))->get($this->url)->assertOk()->getContent();
    $panel = H::contextualMarkup($html);

    expect($panel)->toContain('لوحة تحكم قسم المنوعات');
    $positions = array_map(fn ($label) => strpos($panel, '</i> '.$label.'</a>'), AG_LABELS);
    expect($positions)->not->toContain(false);
    $sorted = $positions;
    sort($sorted);
    expect($positions)->toBe($sorted);

    expect($panel)
        ->toContain(route('admin.anasheed.groups.icon', $this->group))
        ->toContain(route('admin.anasheed.groups.edit', $this->group))
        ->toContain(route('admin.anasheed.groups.items.create', $this->group))
        ->toContain(route('admin.anasheed.groups.subgroups.create', $this->group))
        ->toContain(route('admin.anasheed.groups.delete', $this->group))
        ->not->toContain('return_to');
    expect($html)->not->toContain('ترتيب القسم')->not->toContain('Order_anasheed')->not->toContain('rearrange');
    expect(Route::has('admin.anasheed.groups.rearrange'))->toBeFalse();
});

it('shows each admin only the controls their permissions reach', function () {
    $none = $this->withSession(H::session(H::admin(['anasheed.addmirror', 'khotab.editgroup', 'khotab.delgroup'])))->get($this->url)->getContent();
    expect($none)->not->toContain('لوحة تحكم قسم المنوعات');

    $map = [
        'anasheed.addicon' => 'admin.anasheed.groups.icon',
        'anasheed.editgroup' => 'admin.anasheed.groups.edit',
        'anasheed.addanasheed' => 'admin.anasheed.groups.items.create',
        'anasheed.addgroup' => 'admin.anasheed.groups.subgroups.create',
        'anasheed.delgroup' => 'admin.anasheed.groups.delete',
    ];
    foreach ($map as $permission => $routeName) {
        $panel = H::contextualMarkup($this->withSession(H::session(H::admin([$permission], 'a-'.$permission)))->get($this->url)->getContent());
        foreach ($map as $routeOther) {
            $routeOther === $routeName
                ? expect($panel)->toContain(route($routeOther, $this->group))
                : expect($panel)->not->toContain(route($routeOther, $this->group));
        }
    }
});

it('refuses every page and mutation to a visitor and a member (401) and to an admin without the permission (403)', function () {
    foreach (agRoutes($this->group) as [$method, $url]) {
        $this->{$method}($url)->assertUnauthorized();
    }

    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach (agRoutes($this->group) as [$method, $url]) {
        $this->actingAs($member, 'vbulletin')->{$method}($url)->assertUnauthorized();
    }
    H::visitor();

    $session = H::session(H::admin(['anasheed.addmirror', 'anasheed.addcat', 'khotab.editgroup', 'khotab.delgroup', 'khotab.additem', 'khotab.addgroup']));
    foreach (agRoutes($this->group) as [$method, $url]) {
        $this->withSession($session)->{$method}($url, ['title' => 'x', 'Title' => 'x', 'parent_id' => 0])->assertForbidden();
    }

    expect(agRow($this->group)->title)->toBe('القسم')
        ->and(DB::connection('main')->table('nuke_anasheed_groups')->count())->toBe(5)
        ->and(DB::connection('main')->table('nuke_anasheed_anasheed')->count())->toBe(1)
        ->and(agAudit())->toBe([]);
});

it('binds only a real group: OLD Main\'s Group(0) has no panel or route, and the visitor 404 is unchanged', function () {
    $this->get('/var-group-0.htm')->assertNotFound();
    $this->withSession(H::session($this->boss))->get('/var-group-0.htm')->assertNotFound();
    $this->withSession(H::session($this->boss))->get(route('admin.anasheed.groups.edit', 0))->assertNotFound();
    $this->withSession(H::session($this->boss))->delete(route('admin.anasheed.groups.destroy', 999))->assertNotFound();
});

// ─── 1. اضافة صورة للقسم ─────────────────────────────────────────────────────

it('icon upload stores a JPEG at OLD\'s path, sets icon = 1 and returns to the parent group', function () {
    $session = H::session(H::admin(['anasheed.addicon'], 'icon'));
    $this->withSession($session)->get(route('admin.anasheed.groups.icon', $this->group))->assertOk()
        ->assertSee('name="icon"', false)->assertSee('أقصى حجم مسموح به 10 كيلو بايت');

    $this->withSession($session)->post(route('admin.anasheed.groups.icon.store', $this->group), ['icon' => agJpeg('anything ../../evil.jpg')])
        ->assertRedirect("/var-group-{$this->root}.htm")
        ->assertSessionHas('contextual_admin_success');

    $file = $this->tmp."/media/anasheed/icons/0/{$this->group}.jpg";
    expect(is_file($file))->toBeTrue()
        ->and(glob($this->tmp.'/media/anasheed/icons/0/*'))->toBe([$file])
        ->and((int) agRow($this->group)->icon)->toBe(1)
        ->and(agAudit())->toBe(['upload_group_icon']);
});

it('icon upload buckets by floor(id / 1000) and, for a root group, returns to the group itself', function () {
    $big = H::insert('nuke_anasheed_groups', ['id' => 1234, 'title' => 'كبير', 'parent_id' => 0]);

    $this->withSession(H::session($this->boss))->post(route('admin.anasheed.groups.icon.store', $big), ['icon' => agJpeg()])
        ->assertRedirect('/var-group-1234.htm');

    expect(is_file($this->tmp.'/media/anasheed/icons/1/1234.jpg'))->toBeTrue()->and((int) agRow($big)->icon)->toBe(1);
});

it('icon upload refuses a missing file, a non-jpg, a fake jpg and an oversized one, with OLD\'s messages and no state change', function () {
    $session = H::session(H::admin(['anasheed.addicon'], 'icon'));
    $url = route('admin.anasheed.groups.icon.store', $this->group);

    $this->withSession($session)->post($url, [])->assertSessionHasErrors(['icon' => 'لم يتم إختيار ملف للتحميل']);
    $this->withSession($session)->post($url, ['icon' => UploadedFile::fake()->create('a.png', 2, 'image/png')])->assertSessionHasErrors(['icon' => 'هذا الإمتداد غير مسموح به']);
    $this->withSession($session)->post($url, ['icon' => UploadedFile::fake()->create('a.jpg', 2, 'text/plain')])->assertSessionHasErrors('icon');
    $this->withSession($session)->post($url, ['icon' => UploadedFile::fake()->createWithContent('a.jpg', str_repeat('j', 10001))])->assertSessionHasErrors(['icon' => 'حجم الملف أكبر من الحجم المسموح به']);

    expect((int) agRow($this->group)->icon)->toBe(0)
        ->and(is_dir($this->tmp.'/media/anasheed/icons'))->toBeFalse()
        ->and(agAudit())->toBe([]);
});

it('icon upload checks the detected content: PNG / WebP bytes named icon.jpg are refused (Group messages unchanged)', function () {
    $session = H::session(H::admin(['anasheed.addicon'], 'icon'));
    $url = route('admin.anasheed.groups.icon.store', $this->group);

    foreach (['png' => 'image/png', 'webp' => 'image/webp'] as $format => $mime) {
        $disguised = agJpeg('icon.jpg', $format);
        expect($disguised->getMimeType())->toBe($mime);
        $this->withSession($session)->post($url, ['icon' => $disguised])->assertSessionHasErrors(['icon' => 'هذا الإمتداد غير مسموح به']);
    }

    expect((int) agRow($this->group)->icon)->toBe(0)
        ->and(is_dir($this->tmp.'/media/anasheed/icons'))->toBeFalse()
        ->and(agAudit())->toBe([]);

    $genuine = agJpeg();
    expect($genuine->getMimeType())->toBe('image/jpeg');
    $this->withSession($session)->post($url, ['icon' => $genuine])->assertSessionHasNoErrors();
    expect((int) agRow($this->group)->icon)->toBe(1);
});

it('icon upload puts the previous file back and leaves icon unchanged when the database step fails', function () {
    $file = $this->tmp."/media/anasheed/icons/0/{$this->group}.jpg";
    mkdir(dirname($file), 0775, true);
    file_put_contents($file, 'previous-icon');

    Schema::connection('main')->drop('admin_audit_log');

    $this->withoutExceptionHandling();
    expect(fn () => $this->withSession(H::session($this->boss))->post(route('admin.anasheed.groups.icon.store', $this->group), ['icon' => agJpeg()]))
        ->toThrow(Exception::class);

    expect(file_get_contents($file))->toBe('previous-icon')
        ->and(glob(dirname($file).'/*'))->toBe([$file])
        ->and((int) agRow($this->group)->icon)->toBe(0);
});

// ─── 2. تعديل القسم ──────────────────────────────────────────────────────────

it('edit offers OLD\'s parent choices: up one level, stay, or a sibling — never a descendant', function () {
    $html = $this->withSession(H::session($this->boss))->get(route('admin.anasheed.groups.edit', $this->group))->assertOk()->getContent();

    expect($html)->toContain('<option value="0"')
        ->toContain('<option value="'.$this->root.'" selected>--الجذر</option>')
        ->toContain('<option value="'.$this->sibling.'" >-----الأخ</option>')
        ->not->toContain('<option value="'.$this->child.'"')
        ->not->toContain('<option value="'.$this->otherRoot.'"');
});

it('edit writes OLD\'s columns without touching counters when the parent is unchanged', function () {
    $this->withSession(H::session(H::admin(['anasheed.editgroup'], 'ed')))->put(route('admin.anasheed.groups.update', $this->group), [
        'title' => 'قسم معدل', 'parent_id' => $this->root, 'Channel' => 0, 'Des' => 'تعليق جديد',
        'description' => 'وصف', 'meta_keywords' => 'ك', 'meta_description' => 'و', 'meta_index' => '1',
    ])->assertRedirect($this->url)->assertSessionHas('contextual_admin_success', 'تم تعديل القسم');

    $row = agRow($this->group);
    expect($row->title)->toBe('قسم معدل')->and((int) $row->channel_id)->toBe(0)->and($row->des)->toBe('تعليق جديد')
        ->and($row->description)->toBe('وصف')->and($row->meta_keywords)->toBe('ك')->and($row->meta_description)->toBe('و')
        ->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(0)
        ->and((int) $row->parent_id)->toBe($this->root)
        ->and((int) agRow($this->root)->child)->toBe(2)
        ->and(agAudit())->toBe(['edit_group']);
});

it('edit moving the group keeps OLD\'s maintained child counters on both parents', function () {
    $session = H::session(H::admin(['anasheed.editgroup'], 'ed'));

    $this->withSession($session)->put(route('admin.anasheed.groups.update', $this->group), ['title' => 'القسم', 'parent_id' => $this->sibling])->assertRedirect($this->url);
    expect((int) agRow($this->group)->parent_id)->toBe($this->sibling)
        ->and((int) agRow($this->root)->child)->toBe(1)
        ->and((int) agRow($this->sibling)->child)->toBe(1);

    // From under the sibling, "up one level" is the root again.
    $this->withSession($session)->put(route('admin.anasheed.groups.update', $this->group), ['title' => 'القسم', 'parent_id' => $this->root])->assertRedirect($this->url);
    expect((int) agRow($this->group)->parent_id)->toBe($this->root)
        ->and((int) agRow($this->root)->child)->toBe(2)
        ->and((int) agRow($this->sibling)->child)->toBe(0)
        ->and(agAudit())->toBe(['edit_group', 'edit_group']);
});

it('edit refuses a parent OLD did not offer, a missing or long title and an unknown channel — changing nothing', function () {
    $session = H::session(H::admin(['anasheed.editgroup'], 'ed'));
    $url = route('admin.anasheed.groups.update', $this->group);

    $this->withSession($session)->put($url, ['title' => 'x', 'parent_id' => $this->child])->assertSessionHasErrors('parent_id');
    $this->withSession($session)->put($url, ['title' => 'x', 'parent_id' => $this->otherRoot])->assertSessionHasErrors('parent_id');
    $this->withSession($session)->put($url, ['title' => '', 'parent_id' => $this->root])->assertSessionHasErrors(['title' => 'من فضلك أدخل عنوان القسم']);
    $this->withSession($session)->put($url, ['title' => str_repeat('ع', 101), 'parent_id' => $this->root])->assertSessionHasErrors('title');
    $this->withSession($session)->put($url, ['title' => 'x', 'parent_id' => $this->root, 'Channel' => 99])->assertSessionHasErrors(['Channel' => 'القناة غير موجودة بقواعد البيانات']);

    $row = agRow($this->group);
    expect($row->title)->toBe('القسم')->and((int) $row->parent_id)->toBe($this->root)
        ->and((int) agRow($this->root)->child)->toBe(2)->and((int) agRow($this->child)->child)->toBe(0)
        ->and(agAudit())->toBe([]);
});

// ─── 3. إضافة مادة ───────────────────────────────────────────────────────────

it('add item shows OLD\'s form, with «نشر علي المنصة» checked by default', function () {
    $html = $this->withSession(H::session(H::admin(['anasheed.addanasheed'], 'add')))
        ->get(route('admin.anasheed.groups.items.create', $this->group))->assertOk()->getContent();

    expect($html)->toContain('name="Anasheedfile"')->toContain('المنشد')
        ->toContain('<input type="checkbox" name="is_published" value="1" checked> نشر علي المنصة</label>');
});

it('add item inserts OLD\'s columns, with weight = id and the group counter + 1', function () {
    $session = H::session(H::admin(['anasheed.addanasheed'], 'add'));

    // The form's default: «نشر علي المنصة» checked → is_published = 1, as production OLD writes it.
    $this->withSession($session)->post(route('admin.anasheed.groups.items.store', $this->group), [
        'title' => 'نشيد جديد', 'vedio' => '0', 'fixed' => '1', 'Anasheedfile' => 'https://archive.org/x.mp3',
        'Channel' => '', 'Author' => '7', 'Anasheedsize' => '2.5', 'MoreInfo' => 'تعليق', 'description' => 'وصف',
        'meta_keywords' => 'ك', 'meta_description' => 'و', 'meta_follow' => '1', 'is_published' => '1',
    ])->assertRedirect($this->url)->assertSessionHas('contextual_admin_success', 'تمت إضافة المادة');

    $item = DB::connection('main')->table('nuke_anasheed_anasheed')->where('title', 'نشيد جديد')->first();
    expect($item)->not->toBeNull()
        ->and((int) $item->group_id)->toBe($this->group)
        ->and((int) $item->parent_id)->toBe($this->root)
        ->and($item->link)->toBe('https://archive.org/x.mp3')
        ->and((int) $item->linksize)->toBe((int) (1024 * 1024 * 2.5))
        ->and((int) $item->down)->toBe(2)
        ->and((int) $item->vedio)->toBe(0)->and((int) $item->fixed)->toBe(1)
        ->and($item->channel_id)->toBeNull()->and((int) $item->author_id)->toBe(7)
        ->and($item->notes)->toBe('تعليق')->and($item->description)->toBe('وصف')
        ->and((int) $item->meta_index)->toBe(0)->and((int) $item->meta_follow)->toBe(1)
        ->and((int) $item->weight)->toBe((int) $item->id)
        ->and((int) $item->hidden)->toBe(0)
        ->and((int) $item->is_published)->toBe(1)
        ->and(abs((int) $item->mytime - time()))->toBeLessThan(5)
        ->and((int) agRow($this->group)->anasheed)->toBe(2)
        ->and(agAudit())->toBe(['add_item']);
});

it('add item stores is_published = 0 when «نشر علي المنصة» is unchecked, as production OLD does', function () {
    $session = H::session(H::admin(['anasheed.addanasheed'], 'add'));
    $url = route('admin.anasheed.groups.items.store', $this->group);

    // An unchecked box posts nothing; an explicit 0 means the same.
    $this->withSession($session)->post($url, ['title' => 'غير منشور', 'vedio' => '1', 'Anasheedfile' => 'https://x/a.mp3'])->assertRedirect($this->url);
    $this->withSession($session)->post($url, ['title' => 'صفر صريح', 'vedio' => '1', 'Anasheedfile' => 'https://x/b.mp3', 'is_published' => '0'])->assertRedirect($this->url);

    $published = DB::connection('main')->table('nuke_anasheed_anasheed')->whereIn('title', ['غير منشور', 'صفر صريح'])->pluck('is_published')->map(fn ($v) => (int) $v)->all();
    expect($published)->toBe([0, 0])->and((int) agRow($this->group)->anasheed)->toBe(3);
});

it('add item rejects an is_published value other than 0 or 1, creating nothing', function () {
    $session = H::session(H::admin(['anasheed.addanasheed'], 'add'));

    foreach (['yes', '2', '-1'] as $value) {
        $this->withSession($session)->post(route('admin.anasheed.groups.items.store', $this->group), [
            'title' => 'x', 'vedio' => '1', 'Anasheedfile' => 'https://x', 'is_published' => $value,
        ])->assertSessionHasErrors('is_published');
    }

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->count())->toBe(1)
        ->and((int) agRow($this->group)->anasheed)->toBe(1)
        ->and(agAudit())->toBe([]);
});

it('add item under roots 57 and 12 offers no author and stores author 0, as OLD did', function () {
    H::insert('nuke_anasheed_groups', ['id' => 57, 'title' => 'الكارتون', 'parent_id' => 0]);
    $cartoon = H::insert('nuke_anasheed_groups', ['title' => 'سلسلة كرتون', 'parent_id' => 57]);
    $session = H::session($this->boss);

    $this->withSession($session)->get(route('admin.anasheed.groups.items.create', $cartoon))->assertOk()->assertSee('غير متاح بهذا القسم');
    $this->withSession($session)->post(route('admin.anasheed.groups.items.store', $cartoon), [
        'title' => 'حلقة', 'vedio' => '1', 'Anasheedfile' => 'https://x/a.mp4', 'Author' => '7', 'Channel' => '0',
    ])->assertRedirect("/var-group-{$cartoon}.htm");

    $item = DB::connection('main')->table('nuke_anasheed_anasheed')->where('title', 'حلقة')->first();
    expect((int) $item->author_id)->toBe(0)->and((int) $item->channel_id)->toBe(0)->and((int) $item->parent_id)->toBe(57);
});

it('add item refuses invalid input without creating an item or moving the counter', function () {
    $session = H::session(H::admin(['anasheed.addanasheed'], 'add'));
    $url = route('admin.anasheed.groups.items.store', $this->group);

    $this->withSession($session)->post($url, ['vedio' => '1', 'Anasheedfile' => 'https://x'])->assertSessionHasErrors('title');
    $this->withSession($session)->post($url, ['title' => str_repeat('ع', 126), 'vedio' => '1', 'Anasheedfile' => 'https://x'])
        ->assertSessionHasErrors(['title' => 'عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات']);
    $this->withSession($session)->post($url, ['title' => 'x', 'vedio' => '1'])->assertSessionHasErrors('Anasheedfile');
    $this->withSession($session)->post($url, ['title' => 'x', 'vedio' => '1', 'Anasheedfile' => 'https://x', 'Author' => '99'])->assertSessionHasErrors('Author');
    $this->withSession($session)->post($url, ['title' => 'x', 'vedio' => '1', 'Anasheedfile' => 'https://x', 'MoreInfo' => str_repeat('a', 256)])->assertSessionHasErrors('MoreInfo');
    $this->withSession($session)->post($url, ['title' => 'x', 'vedio' => '1', 'Anasheedfile' => 'https://x', 'Anasheedsize' => 'كبير'])->assertSessionHasErrors('Anasheedsize');

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->count())->toBe(1)
        ->and((int) agRow($this->group)->anasheed)->toBe(1)
        ->and(agAudit())->toBe([]);
});

it('add item is atomic: a failure after the insert leaves no item and the counter unchanged', function () {
    Schema::connection('main')->drop('admin_audit_log');

    $this->withoutExceptionHandling();
    expect(fn () => $this->withSession(H::session($this->boss))->post(route('admin.anasheed.groups.items.store', $this->group), [
        'title' => 'لن يحفظ', 'vedio' => '1', 'Anasheedfile' => 'https://x/a.mp3',
    ]))->toThrow(Exception::class);

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('title', 'لن يحفظ')->exists())->toBeFalse()
        ->and((int) agRow($this->group)->anasheed)->toBe(1);
});

// ─── 4. إضافة قسم فرعي ───────────────────────────────────────────────────────

it('add sub-group inserts OLD\'s columns under this group and adds 1 to its child counter', function () {
    $session = H::session(H::admin(['anasheed.addgroup'], 'sub'));
    $this->withSession($session)->get(route('admin.anasheed.groups.subgroups.create', $this->group))->assertOk()->assertSee('name="Title"', false);

    $this->withSession($session)->post(route('admin.anasheed.groups.subgroups.store', $this->group), [
        'Title' => 'قسم جديد', 'Channel' => '3', 'Des' => 'تعليق', 'description' => 'وصف', 'meta_keywords' => 'ك', 'meta_index' => '1', 'meta_follow' => '1',
    ])->assertRedirect($this->url)->assertSessionHas('contextual_admin_success');

    $row = DB::connection('main')->table('nuke_anasheed_groups')->where('title', 'قسم جديد')->first();
    expect((int) $row->parent_id)->toBe($this->group)->and((int) $row->channel_id)->toBe(3)
        ->and($row->des)->toBe('تعليق')->and($row->description)->toBe('وصف')->and($row->meta_keywords)->toBe('ك')
        ->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(1)
        ->and((int) $row->child)->toBe(0)->and((int) $row->anasheed)->toBe(0)->and((int) $row->icon)->toBe(0)
        ->and((int) agRow($this->group)->child)->toBe(2)
        ->and(agAudit())->toBe(['add_group']);
});

it('add sub-group refuses a title already used under the same parent, but allows it under another', function () {
    $session = H::session(H::admin(['anasheed.addgroup'], 'sub'));

    $this->withSession($session)->post(route('admin.anasheed.groups.subgroups.store', $this->group), ['Title' => 'الفرعي'])
        ->assertSessionHasErrors('Title');
    expect(session('errors')->first('Title'))->toContain('لن تتمكن من إضافة قسم');
    expect(DB::connection('main')->table('nuke_anasheed_groups')->where('title', 'الفرعي')->count())->toBe(1)
        ->and((int) agRow($this->group)->child)->toBe(1);

    $this->withSession($session)->post(route('admin.anasheed.groups.subgroups.store', $this->sibling), ['Title' => 'الفرعي'])->assertRedirect("/var-group-{$this->sibling}.htm");
    expect(DB::connection('main')->table('nuke_anasheed_groups')->where('title', 'الفرعي')->count())->toBe(2)
        ->and((int) agRow($this->sibling)->child)->toBe(1);

    $this->withSession($session)->post(route('admin.anasheed.groups.subgroups.store', $this->group), ['Title' => ''])->assertSessionHasErrors('Title');
});

it('add sub-group is atomic: a failure leaves no new group and the counter unchanged', function () {
    Schema::connection('main')->drop('admin_audit_log');

    $this->withoutExceptionHandling();
    expect(fn () => $this->withSession(H::session($this->boss))->post(route('admin.anasheed.groups.subgroups.store', $this->group), ['Title' => 'لن يحفظ']))
        ->toThrow(Exception::class);

    expect(DB::connection('main')->table('nuke_anasheed_groups')->where('title', 'لن يحفظ')->exists())->toBeFalse()
        ->and((int) agRow($this->group)->child)->toBe(1);
});

// ─── 5. حذف القسم ────────────────────────────────────────────────────────────

it('delete removes an empty group, takes 1 from the parent counter and returns to the parent', function () {
    $session = H::session(H::admin(['anasheed.delgroup'], 'del'));
    $this->withSession($session)->get(route('admin.anasheed.groups.delete', $this->sibling))->assertOk()->assertSee('هل ترغب في مسح قسم');

    $this->withSession($session)->delete(route('admin.anasheed.groups.destroy', $this->sibling))
        ->assertRedirect("/var-group-{$this->root}.htm")->assertSessionHas('contextual_admin_success');

    expect(agRow($this->sibling))->toBeNull()
        ->and((int) agRow($this->root)->child)->toBe(1)
        ->and(agAudit())->toBe(['del_group']);
});

it('delete of an empty root group returns to the home page, since Laravel has no Anasheed Main', function () {
    $this->withSession(H::session($this->boss))->delete(route('admin.anasheed.groups.destroy', $this->otherRoot))->assertRedirect('/');
    expect(agRow($this->otherRoot))->toBeNull();
});

it('delete refuses a group that still has items (OLD) or sub-groups (safety correction), changing nothing', function () {
    $session = H::session(H::admin(['anasheed.delgroup'], 'del'));

    // Has items (and a sub-group): OLD's own refusal comes first.
    $this->withSession($session)->from(route('admin.anasheed.groups.delete', $this->group))
        ->delete(route('admin.anasheed.groups.destroy', $this->group))
        ->assertRedirect(route('admin.anasheed.groups.delete', $this->group))->assertSessionHasErrors('group');
    expect(session('errors')->first('group'))->toContain('يجب عليك أولا مسح جميع الخطب');

    // Sub-groups only: OLD would have deleted and orphaned the child.
    DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $this->item)->delete();
    $this->withSession($session)->delete(route('admin.anasheed.groups.destroy', $this->group))->assertSessionHasErrors('group');
    expect(session('errors')->first('group'))->toContain('الأقسام الفرعية');

    expect(agRow($this->group))->not->toBeNull()
        ->and((int) agRow($this->child)->parent_id)->toBe($this->group)
        ->and((int) agRow($this->root)->child)->toBe(2)
        ->and(agAudit())->toBe([]);
});

it('delete is atomic: a failure leaves the group and the parent counter unchanged', function () {
    Schema::connection('main')->drop('admin_audit_log');

    $this->withoutExceptionHandling();
    expect(fn () => $this->withSession(H::session($this->boss))->delete(route('admin.anasheed.groups.destroy', $this->sibling)))
        ->toThrow(Exception::class);

    expect(agRow($this->sibling))->not->toBeNull()->and((int) agRow($this->root)->child)->toBe(2);
});

// ─── visitor regression ─────────────────────────────────────────────────────

it('leaves the visitor page and the series playlist exactly as before for a visitor', function () {
    $visitor = $this->get($this->url)->assertOk();
    expect($visitor->getContent())->toContain('القسم')->toContain('الفرعي')->toContain('نشيد');
    $this->get("/var-series-{$this->group}.grx")->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="Way2Allah-Anasheed-'.$this->group.'.grx"');

    // hits still go up once per view, admin or not
    $before = (int) agRow($this->group)->hits;
    $this->withSession(H::session($this->boss))->get($this->url)->assertOk();
    expect((int) agRow($this->group)->hits)->toBe($before + 1);
});
