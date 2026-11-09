<?php

/*
 * OLD public admin parity — the Anasheed item page (owner, 2026-10-23):
 * «لوحة تحكم المادة» (OLD item_cp.htm) completed with «اضافة صورة للمادة»,
 * «تعديل المادة» and «حذف المادة», plus the comment controls «إضافة تعليق
 * إداري» and «(تعديل التعليق)». Production OLD = the old-project working copy
 * on PHP 8.1. Dead (image edit / delete, banners) and owner-retired (share
 * queue, link status) controls must stay absent; visitor output is unchanged.
 */

use App\Domain\Admin\Actions\Anasheed\AnasheedItemComments;
use App\Domain\Content\Models\AnasheedComment;
use App\Domain\Identity\Models\VbUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();

    $this->tmp = sys_get_temp_dir().'/w2a-anasheed-item-'.uniqid();
    config(['admin_anasheed.media_root' => $this->tmp.'/media', 'media.base_url' => '/media']);

    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'المنشد', 'prename' => 'أ.']);
    H::category(1, 'أناشيد الأطفال');
    H::category(3, 'المناسبات');

    $g = fn (array $a) => H::insert('nuke_anasheed_groups', $a);
    $this->releases = $g(['id' => 158, 'title' => 'إصدارات الموقع', 'parent_id' => 0, 'anasheed' => 5]);
    $this->root = $g(['title' => 'الجذر', 'parent_id' => 0, 'child' => 1]);
    $this->group = $g(['title' => 'القسم', 'parent_id' => $this->root, 'child' => 1, 'anasheed' => 2]);
    $this->child = $g(['title' => 'الفرعي', 'parent_id' => $this->group, 'anasheed' => 0]);
    $this->stranger = $g(['title' => 'قسم غريب', 'parent_id' => 0, 'anasheed' => 4]);

    $this->item = H::insert('nuke_anasheed_anasheed', [
        'title' => 'نشيد', 'group_id' => $this->group, 'parent_id' => 999, 'link' => 'https://x/a.mp4', 'linksize' => 3 * 1048576,
        'mirror' => 2, 'comments' => 2, 'cat_id' => '|1|3|', 'vedio' => 1, 'fixed' => 0, 'is_published' => 1, 'notes' => 'تعليق',
        'mytime' => 1000, 'channel_id' => 3, 'author_id' => 7,
    ]);
    $this->m1 = H::insert('nuke_anasheed_mirror', ['id' => 500, 'khid' => $this->item, 'title' => 'جودة عالية', 'link' => 'https://x/b.mp4', 'vedio' => 1]);
    $this->m2 = H::insert('nuke_anasheed_mirror', ['id' => 501, 'khid' => $this->item, 'title' => 'صوت', 'link' => 'https://x/c.mp3', 'vedio' => 0]);
    $this->c1 = H::insert('nuke_anasheed_comments', ['khid' => $this->item, 'uid' => 0, 'uname' => 'زائر', 'view' => 1, 'mytime' => 1000, 'comment' => 'تعليق أول', 'code' => '']);
    $this->c2 = H::insert('nuke_anasheed_comments', ['khid' => $this->item, 'uid' => 0, 'uname' => 'زائر 2', 'view' => 1, 'mytime' => 2000, 'comment' => 'تعليق ثان', 'code' => '']);
    $this->c3 = H::insert('nuke_anasheed_comments', ['khid' => $this->item, 'uid' => 0, 'uname' => 'زائر 3', 'view' => 2, 'mytime' => 3000, 'comment' => 'مخفي', 'code' => '']);
    H::insert('nuke_anasheed_advanced', ['id' => $this->item, 'vstr' => 'Video']);

    // Another item's data, which must survive every operation on $this->item.
    $this->other = H::insert('nuke_anasheed_anasheed', ['title' => 'آخر', 'group_id' => $this->group, 'parent_id' => $this->root, 'comments' => 1, 'mirror' => 1, 'cat_id' => '|1|']);
    $this->otherMirror = H::insert('nuke_anasheed_mirror', ['khid' => $this->other, 'title' => 'م', 'link' => 'https://x/o.mp4']);
    $this->otherComment = H::insert('nuke_anasheed_comments', ['khid' => $this->other, 'view' => 1, 'comment' => 'تعليق آخر']);
    H::insert('nuke_anasheed_advanced', ['id' => $this->other, 'vstr' => 'Other']);
    // Item 500's technical row: its id equals one of $this->item's mirror ids, as ids collide in production.
    H::insert('nuke_anasheed_anasheed', ['id' => 500, 'title' => 'المادة 500', 'group_id' => $this->stranger]);
    H::insert('nuke_anasheed_advanced', ['id' => 500, 'vstr' => 'Collision']);

    $this->url = "/var-item-{$this->item}.htm";
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

function aiRow(string $table, int $id): ?object
{
    return DB::connection('main')->table($table)->where('id', $id)->first();
}

function aiAudit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

/**
 * A REAL uploaded file (test mode), so validation runs fileinfo on the bytes.
 * `UploadedFile::fake()` reports a MIME type guessed from the NAME, which hid
 * a name / content mismatch (owner manual review, 2026-10-23).
 *
 * @param  'jpeg'|'png'|'webp'  $format  the bytes actually written
 * @param  int  $side  image width / height; noise makes larger JPEGs big
 */
function aiImage(string $name = 'frame.jpg', string $format = 'jpeg', int $side = 4, bool $noise = false): UploadedFile
{
    $image = imagecreatetruecolor($side, $side);
    if ($noise) {
        mt_srand(7);
        for ($x = 0; $x < $side; $x++) {
            for ($y = 0; $y < $side; $y++) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'w2a-upload-');
    match ($format) {
        'jpeg' => imagejpeg($image, $path, 100),
        'png' => imagepng($image, $path),
        'webp' => imagewebp($image, $path),
    };

    return new UploadedFile($path, $name, null, null, true);
}

function aiJpeg(string $name = 'frame.jpg'): UploadedFile
{
    return aiImage($name);
}

function aiRoutes(int $item, int $comment): array
{
    return [
        ['get', route('admin.anasheed.items.frame', $item)],
        ['post', route('admin.anasheed.items.frame.store', $item)],
        ['get', route('admin.anasheed.items.edit', $item)],
        ['put', route('admin.anasheed.items.update', $item)],
        ['get', route('admin.anasheed.items.delete', $item)],
        ['delete', route('admin.anasheed.items.destroy', $item)],
        ['post', route('admin.anasheed.items.comments.store', $item)],
        ['get', route('admin.anasheed.comments.edit', $comment)],
        ['put', route('admin.anasheed.comments.publish', $comment)],
        ['post', route('admin.anasheed.comments.hide', $comment)],
    ];
}

function aiEdit(array $overrides = []): array
{
    return $overrides + [
        'title' => 'نشيد معدل', 'vedio' => '0', 'fixed' => '1', 'is_published' => '0', 'Anasheed_link' => 'https://x/new.mp4',
        'Channel' => '', 'Author' => '0', 'Anasheedsize' => '3.00', 'Des' => 'تعليق جديد', 'description' => 'وصف',
        'meta_keywords' => 'ك', 'meta_description' => 'و', 'meta_index' => '1',
    ];
}

const AI_LABELS = ['اضافة صورة للمادة', 'تعديل المادة', 'إضافة وصلة', 'حذف المادة', 'إضافة تصنيف'];

// ─── the public page ────────────────────────────────────────────────────────

it('shows a visitor and a signed-in member no admin control', function () {
    $html = $this->get($this->url)->assertOk()->getContent();
    expect($html)->not->toContain('data-contextual-admin')->not->toContain('لوحة تحكم المادة')->not->toContain('(تعديل التعليق)')->toContain('تعليق أول');

    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    $html = $this->actingAs($member, 'vbulletin')->get($this->url)->assertOk()->getContent();
    expect($html)->not->toContain('data-contextual-admin')->not->toContain('إضافة تعليق إداري');
});

it('shows a super-admin «لوحة تحكم المادة» in OLD\'s order, with no dead or retired control', function () {
    $html = $this->withSession(H::session($this->boss))->get($this->url)->assertOk()->getContent();
    $panel = H::contextualMarkup($html);

    expect($panel)->toContain('لوحة تحكم المادة');
    $positions = array_map(fn ($label) => strpos($panel, '</i> '.$label.'</a>'), AI_LABELS);
    expect($positions)->not->toContain(false);
    $sorted = $positions;
    sort($sorted);
    expect($positions)->toBe($sorted);

    expect($panel)->toContain(route('admin.anasheed.items.frame', $this->item))
        ->toContain(route('admin.anasheed.items.edit', $this->item))
        ->toContain(route('admin.anasheed.items.delete', $this->item));
    foreach (['تعديل صورة للمادة', 'حذف صورة للمادة', 'رفع بانر', 'تعديل البانر', 'حذف البانر', 'اضافة الى قائمة الشير', 'AnasheedWaitingFB'] as $absent) {
        expect($html)->not->toContain($absent);
    }
    expect(Route::has('admin.anasheed.items.banner'))->toBeFalse();
});

it('shows the comment controls to an admin: the moderator form and «(تعديل التعليق)» beside each published comment', function () {
    $html = $this->withSession(H::session($this->boss))->get($this->url)->assertOk()->getContent();

    expect($html)->toContain('إضافة تعليق إداري')
        ->toContain('action="'.route('admin.anasheed.items.comments.store', $this->item).'"')
        ->toContain(route('admin.anasheed.comments.edit', $this->c1))
        ->toContain(route('admin.anasheed.comments.edit', $this->c2))
        ->not->toContain(route('admin.anasheed.comments.edit', $this->c3));
});

it('shows each admin only the controls their permissions reach; the existing mirror / category controls are unchanged', function () {
    $map = [
        'anasheed.addbanner' => 'admin.anasheed.items.frame',
        'anasheed.editanasheed' => 'admin.anasheed.items.edit',
        'anasheed.delanasheed' => 'admin.anasheed.items.delete',
    ];
    foreach ($map as $permission => $routeName) {
        $html = $this->withSession(H::session(H::admin([$permission], 'a-'.$permission)))->get($this->url)->getContent();
        foreach ($map as $other) {
            $other === $routeName
                ? expect($html)->toContain(route($other, $this->item))
                : expect($html)->not->toContain(route($other, $this->item));
        }
        expect($html)->not->toContain('إضافة تعليق إداري')->not->toContain('admin/anasheed/comments');
    }

    // OLD gates the moderator form and the WaitingList(id) page alike with `addcomment`.
    $commenter = $this->withSession(H::session(H::admin(['anasheed.addcomment'], 'k1')))->get($this->url)->getContent();
    expect($commenter)->toContain('إضافة تعليق إداري')->toContain(route('admin.anasheed.comments.edit', $this->c1));
    $hider = $this->withSession(H::session(H::admin(['anasheed.delcomment'], 'k2')))->get($this->url)->getContent();
    expect($hider)->not->toContain(route('admin.anasheed.comments.edit', $this->c1))->not->toContain('إضافة تعليق إداري');

    $legacy = $this->withSession(H::session(H::admin(['anasheed.addmirror', 'anasheed.editmirror', 'anasheed.delmirror', 'anasheed.addcat'], 'old')))->get($this->url)->getContent();
    expect($legacy)->toContain(route('admin.mirrors.anasheed.show', $this->item))
        ->toContain(route('admin.categories.anasheed.item', $this->item))
        ->toContain('action="'.route('admin.mirrors.anasheed.destroy', $this->m1).'"')
        ->not->toContain(route('admin.anasheed.items.edit', $this->item));
});

it('refuses every page and mutation to a visitor or member (401) and to an admin without the permission (403)', function () {
    foreach (aiRoutes($this->item, $this->c1) as [$method, $url]) {
        $this->{$method}($url)->assertUnauthorized();
    }
    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach (aiRoutes($this->item, $this->c1) as [$method, $url]) {
        $this->actingAs($member, 'vbulletin')->{$method}($url)->assertUnauthorized();
    }
    H::visitor();

    $session = H::session(H::admin(['anasheed.addmirror', 'anasheed.addcat', 'anasheed.editgroup', 'anasheed.addanasheed', 'khotab.edititem', 'khotab.delitem', 'khotab.approvecomment', 'khotab.delcomment']));
    foreach (aiRoutes($this->item, $this->c1) as [$method, $url]) {
        $this->withSession($session)->{$method}($url, aiEdit(['comment' => 'x', 'group' => $this->group]))->assertForbidden();
    }

    expect(aiRow('nuke_anasheed_anasheed', $this->item)->title)->toBe('نشيد')
        ->and(DB::connection('main')->table('nuke_anasheed_comments')->count())->toBe(4)
        ->and(aiAudit())->toBe([]);
});

// ─── T1 اضافة صورة للمادة ────────────────────────────────────────────────────

it('frame upload stores a JPEG at OLD\'s frame path, sets frame = 1, audits and returns to the item', function () {
    $session = H::session(H::admin(['anasheed.addbanner'], 'frame'));
    $this->withSession($session)->get(route('admin.anasheed.items.frame', $this->item))->assertOk()
        ->assertSee('name="frame"', false)->assertSee('أقصى حجم مسموح به 40 كيلو بايت');

    $this->withSession($session)->post(route('admin.anasheed.items.frame.store', $this->item), ['frame' => aiJpeg('../../x.jpg')])
        ->assertRedirect($this->url)->assertSessionHas('contextual_admin_success');

    $file = $this->tmp."/media/anasheed/frame/0/{$this->item}.jpg";
    expect(is_file($file))->toBeTrue()
        ->and(glob(dirname($file).'/*'))->toBe([$file])
        ->and((int) aiRow('nuke_anasheed_anasheed', $this->item)->frame)->toBe(1)
        ->and(aiAudit())->toBe(['upload_image']);
});

it('frame upload replaces an existing frame and buckets by floor(id / 1000)', function () {
    $big = H::insert('nuke_anasheed_anasheed', ['id' => 2345, 'title' => 'كبير', 'group_id' => $this->group]);
    $file = $this->tmp.'/media/anasheed/frame/2/2345.jpg';
    mkdir(dirname($file), 0775, true);
    file_put_contents($file, 'old-frame');

    $this->withSession(H::session($this->boss))->post(route('admin.anasheed.items.frame.store', $big), ['frame' => aiJpeg()])->assertRedirect('/var-item-2345.htm');

    expect(file_get_contents($file))->not->toBe('old-frame')->and(glob(dirname($file).'/*'))->toBe([$file])
        ->and((int) aiRow('nuke_anasheed_anasheed', $big)->frame)->toBe(1);
});

it('frame upload refuses a missing file, a non-jpg, a fake jpg and one over 40 000 bytes, changing nothing', function () {
    $session = H::session(H::admin(['anasheed.addbanner'], 'frame'));
    $url = route('admin.anasheed.items.frame.store', $this->item);

    $this->withSession($session)->post($url, [])->assertSessionHasErrors(['frame' => 'لم يتم إختيار ملف للتحميل']);
    $this->withSession($session)->post($url, ['frame' => UploadedFile::fake()->create('a.png', 2, 'image/png')])->assertSessionHasErrors(['frame' => 'هذا الإمتداد غير مسموح به']);
    $this->withSession($session)->post($url, ['frame' => UploadedFile::fake()->create('a.jpg', 2, 'text/plain')])->assertSessionHasErrors('frame');
    $this->withSession($session)->post($url, ['frame' => UploadedFile::fake()->createWithContent('a.jpg', str_repeat('j', 40001))])->assertSessionHasErrors(['frame' => 'حجم الملف أكبر من الحجم المسموح به']);

    expect((int) aiRow('nuke_anasheed_anasheed', $this->item)->frame)->toBe(0)
        ->and(is_dir($this->tmp.'/media/anasheed/frame'))->toBeFalse()
        ->and(aiAudit())->toBe([]);
});

it('frame upload validates the detected content: genuine JPEG passes, disguised PNG / WebP are refused with their own message', function () {
    $session = H::session(H::admin(['anasheed.addbanner'], 'frame'));
    $url = route('admin.anasheed.items.frame.store', $this->item);
    $notJpeg = 'الملف ليس صورة JPG حقيقية، يرجى حفظ الصورة بصيغة JPG ثم إعادة رفعها';

    // B. PNG bytes named frame.jpg.
    $png = aiImage('frame.jpg', 'png');
    expect($png->getMimeType())->toBe('image/png');
    $this->withSession($session)->post($url, ['frame' => $png])->assertSessionHasErrors(['frame' => $notJpeg]);

    // C. WebP bytes named frame.jpg (GD's WebP writer is present in this environment).
    expect(function_exists('imagewebp'))->toBeTrue();
    $webp = aiImage('frame.jpg', 'webp');
    expect($webp->getMimeType())->toBe('image/webp');
    $this->withSession($session)->post($url, ['frame' => $webp])->assertSessionHasErrors(['frame' => $notJpeg]);

    // D. Genuine JPEG bytes with a wrong extension — the extension message, not the content one.
    $this->withSession($session)->post($url, ['frame' => aiImage('frame.jpeg')])->assertSessionHasErrors(['frame' => 'هذا الإمتداد غير مسموح به']);
    $this->withSession($session)->post($url, ['frame' => aiImage('frame.png')])->assertSessionHasErrors(['frame' => 'هذا الإمتداد غير مسموح به']);

    // E. Genuine JPEG over 40 000 bytes.
    $big = aiImage('frame.jpg', 'jpeg', 160, true);
    expect($big->getMimeType())->toBe('image/jpeg')->and($big->getSize())->toBeGreaterThan(40000);
    $this->withSession($session)->post($url, ['frame' => $big])->assertSessionHasErrors(['frame' => 'حجم الملف أكبر من الحجم المسموح به']);

    expect((int) aiRow('nuke_anasheed_anasheed', $this->item)->frame)->toBe(0)
        ->and(is_dir($this->tmp.'/media/anasheed/frame'))->toBeFalse()
        ->and(aiAudit())->toBe([]);

    // A. Genuine JPEG bytes named frame.jpg, detected as image/jpeg by fileinfo.
    $jpeg = aiImage('frame.jpg');
    expect($jpeg->getMimeType())->toBe('image/jpeg');
    $this->withSession($session)->post($url, ['frame' => $jpeg])->assertSessionHasNoErrors()->assertRedirect($this->url);
    expect((int) aiRow('nuke_anasheed_anasheed', $this->item)->frame)->toBe(1);
});

it('frame upload puts the previous file back and leaves frame unchanged when the database step fails', function () {
    $file = $this->tmp."/media/anasheed/frame/0/{$this->item}.jpg";
    mkdir(dirname($file), 0775, true);
    file_put_contents($file, 'previous-frame');
    Schema::connection('main')->drop('admin_audit_log');

    $this->withoutExceptionHandling();
    expect(fn () => $this->withSession(H::session($this->boss))->post(route('admin.anasheed.items.frame.store', $this->item), ['frame' => aiJpeg()]))
        ->toThrow(Exception::class);

    expect(file_get_contents($file))->toBe('previous-frame')->and(glob(dirname($file).'/*'))->toBe([$file])
        ->and((int) aiRow('nuke_anasheed_anasheed', $this->item)->frame)->toBe(0);
});

// ─── T4 تعديل المادة ──────────────────────────────────────────────────────────

it('edit shows OLD\'s fields, prefilled, with OLD\'s group choices', function () {
    $html = $this->withSession(H::session(H::admin(['anasheed.editanasheed'], 'ed')))->get(route('admin.anasheed.items.edit', $this->item))->assertOk()->getContent();

    foreach (['name="title"', 'name="vedio"', 'name="fixed"', 'name="is_published"', 'name="Anasheed_link"', 'name="Channel"', 'name="Author"', 'name="Anasheedsize"', 'name="group"', 'name="Des"', 'name="description"', 'name="meta_keywords"', 'name="meta_description"', 'name="meta_index"', 'name="meta_follow"'] as $field) {
        expect($html)->toContain($field);
    }
    expect($html)->toContain('value="3.00" name="Anasheedsize"')
        ->toContain('<option value="158"')
        ->toContain('<option value="'.$this->group.'" selected>')
        ->toContain('<option value="'.$this->child.'"')
        ->not->toContain('<option value="'.$this->stranger.'"')
        ->toContain('منشوره علي المصة');
});

it('edit within the same group writes every OLD column, keeps the size and does not rewrite parent_id or categories', function () {
    $this->withSession(H::session(H::admin(['anasheed.editanasheed'], 'ed')))
        ->put(route('admin.anasheed.items.update', $this->item), aiEdit(['group' => $this->group]))
        ->assertRedirect($this->url)->assertSessionHas('contextual_admin_success', 'تم تعديل المادة');

    $row = aiRow('nuke_anasheed_anasheed', $this->item);
    expect($row->title)->toBe('نشيد معدل')->and($row->link)->toBe('https://x/new.mp4')
        ->and((int) $row->vedio)->toBe(0)->and((int) $row->fixed)->toBe(1)->and((int) $row->is_published)->toBe(0)
        ->and($row->channel_id)->toBeNull()->and((int) $row->author_id)->toBe(0)
        ->and($row->notes)->toBe('تعليق جديد')->and($row->description)->toBe('وصف')
        ->and($row->meta_keywords)->toBe('ك')->and($row->meta_description)->toBe('و')
        ->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(0)
        ->and((int) $row->trial)->toBe(0)
        ->and((int) $row->linksize)->toBe(3 * 1048576)
        ->and((int) $row->group_id)->toBe($this->group)
        ->and((int) $row->parent_id)->toBe(999)
        ->and($row->cat_id)->toBe('|1|3|')
        ->and((int) aiRow('nuke_anasheed_groups', $this->group)->anasheed)->toBe(2)
        ->and(aiAudit())->toBe(['edit_item']);
});

it('edit persists is_published 1 and a changed size in bytes (owner-approved correction)', function () {
    $this->withSession(H::session($this->boss))
        ->put(route('admin.anasheed.items.update', $this->item), aiEdit(['group' => $this->group, 'is_published' => '1', 'Anasheedsize' => '2.5']))
        ->assertRedirect($this->url);

    $row = aiRow('nuke_anasheed_anasheed', $this->item);
    expect((int) $row->is_published)->toBe(1)->and((int) $row->linksize)->toBe((int) (1024 * 1024 * 2.5));
});

it('edit moving the item keeps the counters and recomputes parent_id from the new group (owner-approved correction)', function () {
    $session = H::session(H::admin(['anasheed.editanasheed'], 'ed'));

    // Into the sub-group: same root.
    $this->withSession($session)->put(route('admin.anasheed.items.update', $this->item), aiEdit(['group' => $this->child]))->assertRedirect($this->url);
    $row = aiRow('nuke_anasheed_anasheed', $this->item);
    expect((int) $row->group_id)->toBe($this->child)->and((int) $row->parent_id)->toBe($this->root)
        ->and((int) aiRow('nuke_anasheed_groups', $this->group)->anasheed)->toBe(1)
        ->and((int) aiRow('nuke_anasheed_groups', $this->child)->anasheed)->toBe(1)
        ->and($row->cat_id)->toBe('|1|3|');

    // To «***إصدارات الموقع» (158): a root of its own.
    $this->withSession($session)->put(route('admin.anasheed.items.update', $this->item), aiEdit(['group' => 158]))->assertRedirect($this->url);
    $row = aiRow('nuke_anasheed_anasheed', $this->item);
    expect((int) $row->group_id)->toBe(158)->and((int) $row->parent_id)->toBe(158)
        ->and((int) aiRow('nuke_anasheed_groups', $this->child)->anasheed)->toBe(0)
        ->and((int) aiRow('nuke_anasheed_groups', 158)->anasheed)->toBe(6)
        ->and($row->cat_id)->toBe('|1|3|')
        ->and(aiAudit())->toBe(['edit_item', 'edit_item']);
});

it('edit refuses a group OLD did not offer and invalid fields, changing nothing', function () {
    $session = H::session(H::admin(['anasheed.editanasheed'], 'ed'));
    $url = route('admin.anasheed.items.update', $this->item);

    $this->withSession($session)->put($url, aiEdit(['group' => $this->stranger]))->assertSessionHasErrors('group');
    $this->withSession($session)->put($url, aiEdit(['group' => 4242]))->assertSessionHasErrors('group');
    $this->withSession($session)->put($url, aiEdit(['group' => $this->group, 'title' => '']))->assertSessionHasErrors('title');
    $this->withSession($session)->put($url, aiEdit(['group' => $this->group, 'title' => str_repeat('ع', 126)]))->assertSessionHasErrors(['title' => 'عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات']);
    $this->withSession($session)->put($url, aiEdit(['group' => $this->group, 'Anasheed_link' => '']))->assertSessionHasErrors('Anasheed_link');
    $this->withSession($session)->put($url, aiEdit(['group' => $this->group, 'is_published' => 'yes']))->assertSessionHasErrors('is_published');
    $this->withSession($session)->put($url, aiEdit(['group' => $this->group, 'Anasheedsize' => 'كبير']))->assertSessionHasErrors('Anasheedsize');
    $this->withSession($session)->put($url, aiEdit(['group' => $this->group, 'Author' => '99']))->assertSessionHasErrors('Author');
    $this->withSession($session)->put($url, aiEdit(['group' => $this->group, 'Des' => str_repeat('a', 256)]))->assertSessionHasErrors('Des');

    $row = aiRow('nuke_anasheed_anasheed', $this->item);
    expect($row->title)->toBe('نشيد')->and((int) $row->group_id)->toBe($this->group)
        ->and((int) aiRow('nuke_anasheed_groups', $this->group)->anasheed)->toBe(2)
        ->and(aiAudit())->toBe([]);
});

// ─── T6 حذف المادة ────────────────────────────────────────────────────────────

it('delete confirms first, then removes the item and exactly its own data, keeping categories consistent', function () {
    $frame = $this->tmp."/media/anasheed/frame/0/{$this->item}.jpg";
    mkdir(dirname($frame), 0775, true);
    file_put_contents($frame, 'frame');

    $session = H::session(H::admin(['anasheed.delanasheed'], 'del'));
    $this->withSession($session)->get(route('admin.anasheed.items.delete', $this->item))->assertOk()
        ->assertSee('هل ترغب في مسح النشيد التالي')->assertSee('الوصلات البديلة (2)')->assertSee('التعليقات (3)');

    $this->withSession($session)->delete(route('admin.anasheed.items.destroy', $this->item))
        ->assertRedirect("/var-group-{$this->group}.htm")->assertSessionHas('contextual_admin_success');

    $main = DB::connection('main');
    expect(aiRow('nuke_anasheed_anasheed', $this->item))->toBeNull()
        ->and($main->table('nuke_anasheed_mirror')->where('khid', $this->item)->count())->toBe(0)
        ->and($main->table('nuke_anasheed_comments')->where('khid', $this->item)->count())->toBe(0)
        ->and(aiRow('nuke_anasheed_advanced', $this->item))->toBeNull()
        ->and((int) $main->table('nuke_w2a_cat')->where('id', 1)->value('anasheed_count'))->toBe(9)
        ->and((int) $main->table('nuke_w2a_cat')->where('id', 3)->value('anasheed_count'))->toBe(9)
        ->and((int) aiRow('nuke_anasheed_groups', $this->group)->anasheed)->toBe(1)
        ->and(is_file($frame))->toBeFalse()
        ->and(glob(dirname($frame).'/*'))->toBe([])
        ->and(aiAudit())->toBe(['del_item']);

    // Nothing shared or belonging to another item was touched.
    expect(aiRow('nuke_anasheed_anasheed', $this->other))->not->toBeNull()
        ->and(aiRow('nuke_anasheed_mirror', $this->otherMirror))->not->toBeNull()
        ->and(aiRow('nuke_anasheed_comments', $this->otherComment))->not->toBeNull()
        ->and(aiRow('nuke_anasheed_advanced', $this->other))->not->toBeNull()
        ->and(aiRow('nuke_anasheed_advanced', $this->m1))->not->toBeNull()
        ->and($main->table('nuke_w2a_cat')->count())->toBe(2)
        ->and(aiRow('nuke_anasheed_groups', $this->group))->not->toBeNull()
        ->and($main->table('nuke_sat_channels')->count())->toBe(1)
        ->and($main->table('nuke_islamic_authors')->count())->toBe(1);
});

it('delete of an item without a group returns to the home page', function () {
    $loose = H::insert('nuke_anasheed_anasheed', ['title' => 'بدون قسم', 'group_id' => 0]);
    $this->withSession(H::session($this->boss))->delete(route('admin.anasheed.items.destroy', $loose))->assertRedirect('/');
    expect(aiRow('nuke_anasheed_anasheed', $loose))->toBeNull();
});

it('delete is atomic: a failure leaves the item, its data, the counters and its frame file in place', function () {
    $frame = $this->tmp."/media/anasheed/frame/0/{$this->item}.jpg";
    mkdir(dirname($frame), 0775, true);
    file_put_contents($frame, 'frame');
    Schema::connection('main')->drop('admin_audit_log');

    $this->withoutExceptionHandling();
    expect(fn () => $this->withSession(H::session($this->boss))->delete(route('admin.anasheed.items.destroy', $this->item)))->toThrow(Exception::class);

    $main = DB::connection('main');
    expect(aiRow('nuke_anasheed_anasheed', $this->item))->not->toBeNull()
        ->and($main->table('nuke_anasheed_mirror')->where('khid', $this->item)->count())->toBe(2)
        ->and($main->table('nuke_anasheed_comments')->where('khid', $this->item)->count())->toBe(3)
        ->and(aiRow('nuke_anasheed_advanced', $this->item))->not->toBeNull()
        ->and((int) $main->table('nuke_w2a_cat')->where('id', 1)->value('anasheed_count'))->toBe(10)
        ->and((int) aiRow('nuke_anasheed_groups', $this->group)->anasheed)->toBe(2)
        ->and(file_get_contents($frame))->toBe('frame')
        ->and(glob(dirname($frame).'/*'))->toBe([$frame]);
});

// ─── K1 إضافة تعليق إداري ────────────────────────────────────────────────────

it('the admin comment is published as «مشرف التعليقات», recounts the item and returns to it (owner-approved correction)', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $this->item)->update(['comments' => 7]);

    $this->withSession(H::session(H::admin(['anasheed.addcomment'], 'k1')))
        ->post(route('admin.anasheed.items.comments.store', $this->item), ['comment' => 'تعليق الإدارة'])
        ->assertRedirect($this->url)->assertSessionHas('contextual_admin_success');

    $comment = DB::connection('main')->table('nuke_anasheed_comments')->where('comment', 'تعليق الإدارة')->first();
    expect($comment)->not->toBeNull()
        ->and((int) $comment->khid)->toBe($this->item)->and((int) $comment->uid)->toBe(0)->and((string) $comment->uname)->toBe('')
        ->and((int) $comment->view)->toBe(1)->and(abs((int) $comment->mytime - time()))->toBeLessThan(5)
        ->and((string) $comment->ip)->toBe('127.0.0.1')
        ->and((int) aiRow('nuke_anasheed_anasheed', $this->item)->comments)->toBe(3)
        ->and(aiAudit())->toBe(['add_mod_comment']);

    $this->get($this->url)->assertSee('تعليق الإدارة')->assertSee('مشرف التعليقات');
});

it('the admin comment requires text', function () {
    $this->withSession(H::session(H::admin(['anasheed.addcomment'], 'k1')))
        ->post(route('admin.anasheed.items.comments.store', $this->item), ['comment' => ''])->assertSessionHasErrors('comment');

    expect(DB::connection('main')->table('nuke_anasheed_comments')->count())->toBe(4)->and(aiAudit())->toBe([]);
});

// ─── K2 (تعديل التعليق) ──────────────────────────────────────────────────────

it('the comment page shows the text with OLD\'s publish and hide buttons', function () {
    $this->withSession(H::session(H::admin(['anasheed.addcomment'], 'k2')))->get(route('admin.anasheed.comments.edit', $this->c1))->assertOk()
        ->assertSee('تعليق أول')->assertSee('نشر التعليق للمادة')->assertSee('مسح التعليق للمادة')->assertSee('نشيد');
});

it('publish saves the edited text, sets view = 1, recounts and returns to the item', function () {
    $session = H::session(H::admin(['anasheed.addcomment'], 'k2'));

    $this->withSession($session)->put(route('admin.anasheed.comments.publish', $this->c3), ['comment' => 'نص معدل'])
        ->assertRedirect($this->url)->assertSessionHas('contextual_admin_success', 'تم نشر التعليق');

    $row = aiRow('nuke_anasheed_comments', $this->c3);
    expect((int) $row->view)->toBe(1)->and($row->comment)->toBe('نص معدل')
        ->and((int) aiRow('nuke_anasheed_anasheed', $this->item)->comments)->toBe(3)
        ->and(aiAudit())->toBe(['publish_comment']);

    $this->withSession($session)->put(route('admin.anasheed.comments.publish', $this->c1), ['comment' => ''])->assertSessionHasErrors('comment');
});

it('hide sets view = 2 without deleting or changing the text, recounts and returns to the item', function () {
    $this->withSession(H::session(H::admin(['anasheed.delcomment'], 'k2')))->post(route('admin.anasheed.comments.hide', $this->c1), ['comment' => 'ignored'])
        ->assertRedirect($this->url)->assertSessionHas('contextual_admin_success', 'تم مسح التعليق');

    $row = aiRow('nuke_anasheed_comments', $this->c1);
    expect((int) $row->view)->toBe(2)->and($row->comment)->toBe('تعليق أول')
        ->and((int) aiRow('nuke_anasheed_anasheed', $this->item)->comments)->toBe(1)
        ->and(aiRow('nuke_anasheed_comments', $this->otherComment)->view)->toBe(1)
        ->and(aiAudit())->toBe(['hide_comment']);
});

it('splits K2 as OLD did: addcomment opens the page and publishes, delcomment hides (confirmed parity correction)', function () {
    $adder = H::session(H::admin(['anasheed.addcomment'], 'k2-add'));

    // addcomment alone: the page and publish, never hide.
    $this->withSession($adder)->get(route('admin.anasheed.comments.edit', $this->c1))->assertOk();
    $this->withSession($adder)->put(route('admin.anasheed.comments.publish', $this->c1), ['comment' => 'نص الناشر'])->assertRedirect($this->url);
    $this->withSession($adder)->post(route('admin.anasheed.comments.hide', $this->c1))->assertForbidden();
    expect((int) aiRow('nuke_anasheed_comments', $this->c1)->view)->toBe(1)
        ->and(aiRow('nuke_anasheed_comments', $this->c1)->comment)->toBe('نص الناشر');

    // delcomment alone: hide, never the page or publish.
    $hider = H::session(H::admin(['anasheed.delcomment'], 'k2-del'));
    $this->withSession($hider)->get(route('admin.anasheed.comments.edit', $this->c2))->assertForbidden();
    $this->withSession($hider)->put(route('admin.anasheed.comments.publish', $this->c3), ['comment' => 'لا'])->assertForbidden();
    $this->withSession($hider)->post(route('admin.anasheed.comments.hide', $this->c2))->assertRedirect($this->url);
    expect((int) aiRow('nuke_anasheed_comments', $this->c2)->view)->toBe(2)
        ->and((int) aiRow('nuke_anasheed_comments', $this->c3)->view)->toBe(2)
        ->and(aiRow('nuke_anasheed_comments', $this->c3)->comment)->toBe('مخفي')
        ->and((int) aiRow('nuke_anasheed_anasheed', $this->item)->comments)->toBe(1)
        ->and(aiAudit())->toBe(['publish_comment', 'hide_comment']);
});

it('the publish action itself authorizes addcomment and hide delcomment, whatever the route', function () {
    $comments = app(AnasheedItemComments::class);
    $comment = AnasheedComment::query()->findOrFail($this->c1);

    expect(fn () => $comments->publish(H::admin(['anasheed.delcomment'], 'act-del'), $comment, 'x'))->toThrow(AuthorizationException::class)
        ->and(fn () => $comments->hide(H::admin(['anasheed.addcomment'], 'act-add'), $comment))->toThrow(AuthorizationException::class)
        ->and(aiAudit())->toBe([]);
});

it('a super-admin still opens, publishes and hides', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->get(route('admin.anasheed.comments.edit', $this->c3))->assertOk();
    $this->withSession($session)->put(route('admin.anasheed.comments.publish', $this->c3), ['comment' => 'منشور'])->assertRedirect($this->url);
    $this->withSession($session)->post(route('admin.anasheed.comments.hide', $this->c1))->assertRedirect($this->url);

    expect((int) aiRow('nuke_anasheed_comments', $this->c3)->view)->toBe(1)
        ->and((int) aiRow('nuke_anasheed_comments', $this->c1)->view)->toBe(2)
        ->and(aiAudit())->toBe(['publish_comment', 'hide_comment']);
});
