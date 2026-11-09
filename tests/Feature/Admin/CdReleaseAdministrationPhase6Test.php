<?php

use App\Domain\Admin\Actions\Cds\CdImageList;
use App\Domain\Content\Models\W2acdItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\CdAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 6 — CD / Album Downloads (W2ACD) release
 * administration through the real routes, against the contract recovered
 * from `modules/w2acd/admin/index.php` (plan §25).
 */
beforeEach(function () {
    $this->root = H::setUp();
    $this->actingAs(H::superAdmin(), 'admin');
});

afterEach(function () {
    H::tearDown($this->root);
});

function cdForm(array $override = []): array
{
    return array_merge([
        'title' => 'إسطوانة جديدة',
        'links' => [['title' => 'رابط ISO', 'url' => 'https://archive.org/x.iso'], ['title' => 'رابط تورنت', 'url' => 'https://archive.org/x.torrent']],
    ], $override);
}

// ---- create (OLD cdsConfirmInsert) ----

it('creates a release with the live comma-list links, images, and OLD defaults', function () {
    $before = time();
    $this->post(route('admin.cds.store'), cdForm([
        'images' => [UploadedFile::fake()->image('cover.png'), UploadedFile::fake()->image('back.jpg')],
    ]))->assertSessionHasNoErrors()->assertSessionHas('success');

    $row = DB::connection('main')->table('nuke_w2acd_w2acd')->first();
    expect($row->title)->toBe('إسطوانة جديدة')
        ->and($row->link)->toBe('https://archive.org/x.iso,https://archive.org/x.torrent')
        ->and($row->cd)->toBe('رابط ISO,رابط تورنت')
        ->and((int) $row->group_id)->toBe(0)->and((int) $row->parent_id)->toBe(0)
        ->and((int) $row->order_in_group)->toBe(1)
        ->and((int) $row->mytime)->toBeGreaterThanOrEqual($before)
        ->and($row->downloader)->toBe('');

    $images = array_values(array_filter(explode(',', $row->thumbnail)));
    expect($row->thumbnail)->toEndWith(',')->and($images)->toHaveCount(2)
        ->and($images[0])->toMatch('/^0\d+\.png$/')->and($images[1])->toMatch('/^1\d+\.jpg$/');
    foreach ($images as $name) {
        expect(file_exists($this->root.'/'.$name))->toBeTrue();
    }
});

it('stores an empty image list when no image is uploaded', function () {
    $this->post(route('admin.cds.store'), cdForm());

    expect(DB::connection('main')->table('nuke_w2acd_w2acd')->value('thumbnail'))->toBe('');
});

it('places the release in an existing group and moves that group counter +1; an unknown group becomes the main list', function () {
    $group = H::group(3);

    $this->post(route('admin.cds.store'), cdForm(['group_id' => $group, 'title' => 'في قسم']));
    $this->post(route('admin.cds.store'), cdForm(['group_id' => 999, 'title' => 'قسم مجهول']));

    $rows = DB::connection('main')->table('nuke_w2acd_w2acd')->orderBy('id')->get();
    expect([(int) $rows[0]->group_id, (int) $rows[0]->parent_id])->toBe([$group, $group])
        ->and((int) $rows[1]->group_id)->toBe(0)
        ->and(H::counter($group))->toBe(4);
});

it('keeps link titles aligned with their links — a pair is both parts or neither', function () {
    $this->post(route('admin.cds.store'), cdForm(['links' => [
        ['title' => 'أ', 'url' => 'https://a'],
        ['title' => '', 'url' => ''],
        ['title' => 'ج', 'url' => 'https://c'],
    ]]))->assertSessionHasNoErrors();

    $row = DB::connection('main')->table('nuke_w2acd_w2acd')->first();
    expect([$row->link, $row->cd])->toBe(['https://a,https://c', 'أ,ج']);
});

it('validates the release, writing nothing and no file on failure', function (array $data, string $field) {
    $before = H::snapshot();

    $this->post(route('admin.cds.store'), array_merge(cdForm(), $data))->assertSessionHasErrors($field);

    expect(H::snapshot())->toBe($before)->and(glob($this->root.'/*'))->toBe([])->and(H::auditRows())->toBe([]);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'title over 100' => [['title' => str_repeat('ا', 101)], 'title'],
    'no link' => [['links' => [['title' => '', 'url' => '']]], 'links'],
    'url without title' => [['links' => [['title' => 'أ', 'url' => 'https://a'], ['title' => '', 'url' => 'https://b']]], 'links.1'],
    'title without url' => [['links' => [['title' => 'أ', 'url' => '']]], 'links.0'],
    'comma in url' => [['links' => [['title' => 'أ', 'url' => 'https://a,b']]], 'links.0.url'],
    'comma in title' => [['links' => [['title' => 'أ,ب', 'url' => 'https://a']]], 'links.0.title'],
    'titles over 255 joined' => [['links' => array_fill(0, 6, ['title' => str_repeat('ع', 50), 'url' => 'https://a'])], 'links'],
    'six images' => [['images' => array_map(fn ($i) => UploadedFile::fake()->image("i{$i}.png"), range(1, 6))], 'images'],
    'not an image type' => [['images' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]], 'images.0'],
]);

it('removes the uploaded files when the create transaction fails', function () {
    DB::connection('main')->unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON admin_audit_log BEGIN SELECT RAISE(ABORT, 'audit-down'); END");
    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('admin.cds.store'), cdForm(['images' => [UploadedFile::fake()->image('a.png')]])))
        ->toThrow(Exception::class, 'audit-down');

    expect(DB::connection('main')->table('nuke_w2acd_w2acd')->count())->toBe(0)->and(glob($this->root.'/*'))->toBe([]);
});

it('never writes the normalized mirror table', function () {
    $id = H::release();

    $writes = H::writesDuring(function () use ($id) {
        $this->post(route('admin.cds.store'), cdForm());
        $this->put(route('admin.cds.update', $id), cdForm(['title' => 'تعديل']));
        $this->delete(route('admin.cds.destroy', $id));
    });

    expect(collect($writes)->filter(fn ($w) => str_contains($w['sql'], 'nuke_w2acd_mirror')))->toBeEmpty()
        ->and(DB::connection('main')->table('nuke_w2acd_mirror')->count())->toBe(0);
});

// ---- edit (OLD cdsConfirmEdit / del_cd_img) ----

it('edits title and links, keeping images, group, counters and order', function () {
    $id = H::release(['order_in_group' => 7]);

    $this->put(route('admin.cds.update', $id), cdForm([
        'title' => 'عنوان معدل',
        'links' => [['title' => 'رابط واحد', 'url' => 'https://one']],
        'existing' => [['name' => '01600000000.png', 'keep' => 1], ['name' => '11600000000.png', 'keep' => 1]],
    ]))->assertSessionHas('success');

    $row = H::row($id);
    expect([$row->title, $row->link, $row->cd, $row->thumbnail, (int) $row->order_in_group])
        ->toBe(['عنوان معدل', 'https://one', 'رابط واحد', '01600000000.png,11600000000.png,', 7]);
});

it('drops an image from the list without deleting any file (OLD del_cd_img)', function () {
    $id = H::release();
    file_put_contents($this->root.'/11600000000.png', 'x');

    $this->put(route('admin.cds.update', $id), cdForm(['existing' => [['name' => '01600000000.png', 'keep' => 1], ['name' => '11600000000.png', 'keep' => 0]]]));

    expect(H::row($id)->thumbnail)->toBe('01600000000.png,')->and(file_exists($this->root.'/11600000000.png'))->toBeTrue();
});

it('replaces an image in place, keeping the cover position, and appends new images', function () {
    $id = H::release();

    $this->put(route('admin.cds.update', $id), cdForm([
        'existing' => [['name' => '01600000000.png', 'keep' => 1, 'file' => UploadedFile::fake()->image('new-cover.jpg')], ['name' => '11600000000.png', 'keep' => 1]],
        'images' => [UploadedFile::fake()->image('extra.gif')],
    ]))->assertSessionHasNoErrors();

    $images = array_values(array_filter(explode(',', H::row($id)->thumbnail)));
    expect($images)->toHaveCount(3)
        ->and($images[0])->toMatch('/^0\d+\.jpg$/')->and($images[1])->toBe('11600000000.png')->and($images[2])->toMatch('/^2\d+\.gif$/');
});

it('moves the group counters when the group changes (OLD did not)', function () {
    $from = H::group(5);
    $to = H::group(2);
    $id = H::release(['group_id' => $from]);

    $this->put(route('admin.cds.update', $id), cdForm(['group_id' => $to, 'existing' => [['name' => '01600000000.png', 'keep' => 1], ['name' => '11600000000.png', 'keep' => 1]]]));

    expect([(int) H::row($id)->group_id, H::counter($from), H::counter($to)])->toBe([$to, 4, 3]);
});

it('treats an unchanged edit as a no-op: no write, no audit', function () {
    $id = H::release(['title' => 'ثابت']);
    $before = H::snapshot();

    $writes = H::writesDuring(fn () => $this->put(route('admin.cds.update', $id), [
        'title' => 'ثابت',
        'links' => [['title' => 'رابط ISO', 'url' => 'https://archive.org/a.iso'], ['title' => 'رابط تورنت', 'url' => 'https://archive.org/a.torrent']],
        'existing' => [['name' => '01600000000.png', 'keep' => 1], ['name' => '11600000000.png', 'keep' => 1]],
    ])->assertSessionHas('error'));

    expect($writes)->toBe([])->and(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('refuses a sixth image on edit', function () {
    $id = H::release(['thumbnail' => 'a.png,b.png,c.png,d.png,e.png,']);
    $before = H::snapshot();

    $this->put(route('admin.cds.update', $id), cdForm([
        'existing' => array_map(fn ($n) => ['name' => $n, 'keep' => 1], ['a.png', 'b.png', 'c.png', 'd.png', 'e.png']),
        'images' => [UploadedFile::fake()->image('f.png')],
    ]))->assertSessionHasErrors('images');

    expect(H::snapshot())->toBe($before);
});

// ---- delete (OLD ConfirmDeleteW2ACD) ----

it('deletes the release and moves its group counter −1, floored, leaving files', function () {
    $group = H::group(0);
    $id = H::release(['group_id' => $group]);
    file_put_contents($this->root.'/01600000000.png', 'x');

    $this->delete(route('admin.cds.destroy', $id))->assertRedirect(route('admin.cds.index', ['group' => $group]));

    expect(H::row($id))->toBeNull()->and(H::counter($group))->toBe(0)->and(file_exists($this->root.'/01600000000.png'))->toBeTrue();
});

it('returns 404 for a missing release', function () {
    $this->get(route('admin.cds.edit', 4040))->assertNotFound();
    $this->put(route('admin.cds.update', 4040), cdForm())->assertNotFound();
    $this->delete(route('admin.cds.destroy', 4040))->assertNotFound();
});

// ---- order (OLD Order_w2acd_in_group) ----

it('orders a group: the first listed gets the highest order_in_group, only changed rows written', function () {
    $a = H::release(['title' => 'أ', 'order_in_group' => 1]);
    $b = H::release(['title' => 'ب', 'order_in_group' => 1]);
    $c = H::release(['title' => 'ج', 'order_in_group' => 1]);
    $other = H::release(['group_id' => H::group(), 'order_in_group' => 1]);

    $writes = H::writesDuring(fn () => $this->post(route('admin.cds.order', ['group' => 0]), ['position' => [$a => 3, $b => 1, $c => 2]])->assertSessionHas('success'));

    $order = fn ($id) => (int) H::row($id)->order_in_group;
    expect([$order($b), $order($c), $order($a), $order($other)])->toBe([3, 2, 1, 1])
        ->and(collect($writes)->filter(fn ($w) => str_starts_with(strtolower($w['sql']), 'update "nuke_w2acd_w2acd"'))->count())->toBe(2);
});

it('refuses an order that does not list exactly the group releases', function (string $case) {
    $a = H::release();
    $b = H::release();
    $foreign = H::release(['group_id' => H::group()]);
    $before = H::snapshot();

    $positions = match ($case) {
        'missing' => [$a => 1],
        'foreign' => [$a => 1, $b => 2, $foreign => 3],
    };
    $this->post(route('admin.cds.order', ['group' => 0]), ['position' => $positions])->assertSessionHas('error');

    expect(H::snapshot())->toBe($before);
})->with(['missing', 'foreign']);

it('lists a group releases in public order', function () {
    H::release(['title' => 'قديم منخفض', 'order_in_group' => 1, 'mytime' => 100]);
    H::release(['title' => 'مرتفع', 'order_in_group' => 9, 'mytime' => 50]);
    H::release(['title' => 'حديث منخفض', 'order_in_group' => 1, 'mytime' => 200]);

    $content = $this->get(route('admin.cds.index'))->assertOk()->getContent();

    $p = fn ($t) => mb_strpos($content, $t);
    expect($p('مرتفع'))->toBeLessThan($p('حديث منخفض'))->and($p('حديث منخفض'))->toBeLessThan($p('قديم منخفض'));
});

// ---- public compatibility (W2acdController, unchanged) ----

it('serves an admin-created release on the existing public list and item pages', function () {
    $this->post(route('admin.cds.store'), cdForm(['title' => 'إسطوانة للعرض', 'images' => [UploadedFile::fake()->image('c.png')]]));
    $row = DB::connection('main')->table('nuke_w2acd_w2acd')->first();
    $cover = array_values(array_filter(explode(',', $row->thumbnail)))[0];

    $this->get('/cds-main.htm')->assertOk()->assertSee('إسطوانة للعرض')->assertSee($cover);
    $this->get("/cds-item-{$row->id}.htm")->assertOk()
        ->assertSee('رابط ISO')->assertSee('https://archive.org/x.iso', false)
        ->assertSee('رابط تورنت')->assertSee('https://archive.org/x.torrent', false);
});

it('shows edited links publicly, paired by position', function () {
    $id = H::release();

    $this->put(route('admin.cds.update', $id), cdForm(['links' => [['title' => 'نسخة جديدة', 'url' => 'https://new.example/n.iso']], 'existing' => [['name' => '01600000000.png', 'keep' => 1]]]));

    $item = W2acdItem::query()->findOrFail($id);
    expect($item->mirrorLinks())->toBe([['link' => 'https://new.example/n.iso', 'title' => 'نسخة جديدة', 'extension' => 'iso', 'isPrivateServer' => false]]);
    $this->get("/cds-item-{$id}.htm")->assertOk()->assertSee('نسخة جديدة');
});

it('never overwrites an existing image file with the OLD-style name', function () {
    // Occupy the OLD name for this second and the next, so the test cannot race the clock.
    $t = time();
    foreach ([$t, $t + 1] as $second) {
        file_put_contents($this->root."/0{$second}.png", 'existing');
    }

    $name = CdImageList::store(UploadedFile::fake()->image('n.png'), 0);

    expect($name)->toMatch('/^0\d+_\d+\.png$/')
        ->and(file_get_contents($this->root."/0{$t}.png"))->toBe('existing')
        ->and(file_get_contents($this->root.'/0'.($t + 1).'.png'))->toBe('existing');
});
