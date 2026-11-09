<?php

/*
 * W2ACD OLD public admin parity, Phase 1 (2026-11-01; decision-log #74) — the CD
 * page `cds-item-{id}.htm` («لوحة تحكم الاسطوانة», OLD `Detailes()`):
 * I2 «نقل الإسطوانة» (the existing edit form), I5 «رفع بانر», I6 «تعديل البانر»,
 * I7 «حذف البانر», and the banner's public consumer `cds-code-{id}-{type}.htm`
 * (OLD `Codes()`). I1 edit / I3 delete stay as before; I4 (share list) is
 * owner-retired.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->media = sys_get_temp_dir().'/w2acd-banner-'.bin2hex(random_bytes(4));
    config(['admin_cds.media_root' => $this->media, 'media.base_url' => '/media']);
    DB::connection('main')->table('nuke_config')->update(['sitename' => 'الطريق إلى الله']);

    $this->groupA = H::insert('nuke_w2acd_groups', ['title' => 'قسم أ', 'parent_id' => 0, 'w2acd' => 1, 'module_type' => 'cd']);
    $this->groupB = H::insert('nuke_w2acd_groups', ['title' => 'قسم ب', 'parent_id' => 0, 'w2acd' => 0, 'module_type' => 'cd']);
    $this->cd = H::insert('nuke_w2acd_w2acd', ['group_id' => $this->groupA, 'title' => 'إسطوانة الإيمان', 'banner' => '0', 'hidden' => 0,
        'link' => 'https://archive.org/a.iso', 'cd' => 'رابط ISO', 'thumbnail' => '']);
    $this->manager = H::admin(['w2acd.managecds'], 'cd-manager');
});

afterEach(function () {
    File::deleteDirectory($this->media);
});

function cdBannerFile(int $id): string
{
    return test()->media.'/w2acd/banner/'.intdiv($id, 1000).'/'.$id.'.gif';
}

function cdPage(int $id, $admin = null): string
{
    if ($admin === null) {
        H::visitor();

        return test()->get("/cds-item-{$id}.htm")->assertOk()->getContent();
    }

    return test()->withSession(H::session($admin))->get("/cds-item-{$id}.htm")->assertOk()->getContent();
}

function cdRow(int $id): object
{
    return DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $id)->first();
}

function cdPutBanner(int $id): void
{
    $path = cdBannerFile($id);
    File::ensureDirectoryExists(dirname($path));
    $image = imagecreatetruecolor(120, 60);
    imagegif($image, $path);
    DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $id)->update(['banner' => '1']);
}

// ─── I2 «نقل الإسطوانة» ─────────────────────────────────────────────────────

it('I2: visitors and admins without w2acd.managecds see no move control', function () {
    expect(cdPage($this->cd))->not->toContain('نقل الإسطوانة')->not->toContain('data-contextual-admin');
    H::visitor();
    expect(cdPage($this->cd, H::admin(['polls.managepolls'], 'cd-other')))->not->toContain('نقل الإسطوانة');
});

it('I2: the manager and a super-admin see «نقل الإسطوانة» opening the existing edit form, in OLD\'s order', function () {
    $url = "/cds-item-{$this->cd}.htm";
    $edit = 'href="'.route('admin.cds.edit', $this->cd).'?return_to='.urlencode($url).'"';

    foreach ([$this->manager, H::superAdmin('cd-boss')] as $admin) {
        $html = cdPage($this->cd, $admin);
        expect($html)->toContain('لوحة تحكم الاسطوانة')
            ->and(substr_count($html, $edit))->toBe(2)
            ->and(strpos($html, 'تعديل الإسطوانة'))->toBeLessThan(strpos($html, 'نقل الإسطوانة'))
            ->and(strpos($html, 'نقل الإسطوانة'))->toBeLessThan(strpos($html, 'حذف الإسطوانة'))
            ->and($html)->not->toContain('قائمة الشير');
        H::visitor();
    }
});

it('I2: the move through the edit form changes the group, moves both counters and returns to the CD page', function () {
    $this->withSession(H::session($this->manager))->put(route('admin.cds.update', $this->cd), [
        'title' => 'إسطوانة الإيمان', 'group_id' => $this->groupB,
        'links' => [['title' => 'رابط ISO', 'url' => 'https://archive.org/a.iso']],
        'return_to' => "/cds-item-{$this->cd}.htm",
    ])->assertRedirect("/cds-item-{$this->cd}.htm");

    $groups = DB::connection('main')->table('nuke_w2acd_groups')->pluck('w2acd', 'id');
    expect((int) cdRow($this->cd)->group_id)->toBe($this->groupB)
        ->and((int) $groups[$this->groupA])->toBe(0)
        ->and((int) $groups[$this->groupB])->toBe(1);
});

// ─── banner controls on the page ────────────────────────────────────────────

it('shows «رفع بانر» while banner = 0, and «تعديل البانر» / «حذف البانر» once there is one', function () {
    $without = cdPage($this->cd, $this->manager);
    expect($without)->toContain('رفع بانر')->toContain('href="'.route('admin.cds.banner', $this->cd).'"')
        ->not->toContain('تعديل البانر')->not->toContain('حذف البانر');

    cdPutBanner($this->cd);
    H::visitor();
    $with = cdPage($this->cd, $this->manager);
    expect($with)->not->toContain('رفع بانر')
        ->toContain('تعديل البانر')->toContain('href="'.route('admin.cds.banner', $this->cd).'"')
        ->toContain('حذف البانر')->toContain('href="'.route('admin.cds.banner.delete', $this->cd).'"');
});

// ─── I5 «رفع بانر» ──────────────────────────────────────────────────────────

it('I5: uploads a png as a real GIF at OLD\'s path, sets banner = 1, audits, and returns to the CD page', function () {
    $this->withSession(H::session($this->manager))->get(route('admin.cds.banner', $this->cd))->assertOk()->assertSee('اختر ملف البانر من جهازك');

    $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.banner.upload', $this->cd), ['file2upl' => UploadedFile::fake()->image('banner.png', 200, 50)])
        ->assertRedirect("/cds-item-{$this->cd}.htm");

    $info = getimagesize(cdBannerFile($this->cd));
    expect($info[2])->toBe(IMAGETYPE_GIF)->and([$info[0], $info[1]])->toBe([200, 50])
        ->and((int) cdRow($this->cd)->banner)->toBe(1)
        ->and(DB::connection('main')->table('admin_audit_log')->where('action', 'upload_banner')->where('module', 'w2acd')->where('entity_id', $this->cd)->exists())->toBeTrue();
});

it('I5: stores a GIF byte for byte and converts a JPEG', function () {
    $gif = UploadedFile::fake()->image('banner.gif', 90, 30);
    $bytes = file_get_contents($gif->getRealPath());
    $this->withSession(H::session($this->manager))->post(route('admin.cds.banner.upload', $this->cd), ['file2upl' => $gif]);
    expect(file_get_contents(cdBannerFile($this->cd)))->toBe($bytes);

    $other = H::insert('nuke_w2acd_w2acd', ['group_id' => 0, 'title' => 'إسطوانة ثانية', 'banner' => '0', 'hidden' => 0]);
    $this->withSession(H::session($this->manager))->post(route('admin.cds.banner.upload', $other), ['file2upl' => UploadedFile::fake()->image('banner.jpg', 60, 20)]);
    expect(getimagesize(cdBannerFile($other))[2])->toBe(IMAGETYPE_GIF)->and((int) cdRow($other)->banner)->toBe(1);
});

it('I5: refuses admins without w2acd.managecds, writing nothing', function () {
    $other = H::admin(['polls.managepolls'], 'cd-other');
    $this->withSession(H::session($other))->get(route('admin.cds.banner', $this->cd))->assertForbidden();
    $this->withSession(H::session($other))->post(route('admin.cds.banner.upload', $this->cd), ['file2upl' => UploadedFile::fake()->image('b.png', 20, 20)])->assertForbidden();

    expect(is_file(cdBannerFile($this->cd)))->toBeFalse()->and((int) cdRow($this->cd)->banner)->toBe(0);
});

it('I5: rejects a missing file, another type, a disguised file and an oversized one, changing nothing', function (Closure $file, string $message) {
    $this->withSession(H::session($this->manager))
        ->from(route('admin.cds.banner', $this->cd))
        ->post(route('admin.cds.banner.upload', $this->cd), $file() === null ? [] : ['file2upl' => $file()])
        ->assertSessionHasErrors(['file2upl' => $message]);

    expect(is_file(cdBannerFile($this->cd)))->toBeFalse()->and((int) cdRow($this->cd)->banner)->toBe(0);
})->with([
    'missing' => [fn () => null, 'لم يتم إختيار ملف للتحميل'],
    'pdf' => [fn () => UploadedFile::fake()->create('banner.pdf', 5, 'application/pdf'), 'هذا الإمتداد غير مسموح به'],
    'text named .gif' => [fn () => UploadedFile::fake()->createWithContent('banner.gif', 'not an image'), 'هذا الإمتداد غير مسموح به'],
    'over 40000 bytes' => [fn () => UploadedFile::fake()->image('banner.png', 2000, 2000)->size(60), 'حجم الملف أكبر من الحجم المسموح به'],
]);

// ─── I6 «تعديل البانر» ──────────────────────────────────────────────────────

it('I6: a replacement overwrites the banner and keeps banner = 1', function () {
    cdPutBanner($this->cd);
    $page = $this->withSession(H::session($this->manager))->get(route('admin.cds.banner', $this->cd))->assertOk();
    $page->assertSee('رفع أي بانر جديد سيؤدي إلى مسح البانر الحالي')->assertSee(route('w2acd.banner-code', ['khid' => $this->cd, 'type' => 3]), false);

    $this->withSession(H::session($this->manager))
        ->post(route('admin.cds.banner.upload', $this->cd), ['file2upl' => UploadedFile::fake()->image('new.png', 300, 90)])
        ->assertRedirect("/cds-item-{$this->cd}.htm");

    expect(array_slice(getimagesize(cdBannerFile($this->cd)), 0, 3))->toBe([300, 90, IMAGETYPE_GIF])
        ->and((int) cdRow($this->cd)->banner)->toBe(1)
        ->and(glob(dirname(cdBannerFile($this->cd)).'/.*.gif.*'))->toBe([]);
});

// ─── I7 «حذف البانر» ────────────────────────────────────────────────────────

it('I7: confirms, sets banner = 0, keeps the file as OLD did, and returns to the CD page', function () {
    cdPutBanner($this->cd);
    $this->withSession(H::session($this->manager))->get(route('admin.cds.banner.delete', $this->cd))
        ->assertOk()->assertSee('هل أنت متأكد من أنك تريد حذف هذا البانر؟');

    $this->withSession(H::session($this->manager))->delete(route('admin.cds.banner.destroy', $this->cd))
        ->assertRedirect("/cds-item-{$this->cd}.htm");

    expect((int) cdRow($this->cd)->banner)->toBe(0)
        ->and(is_file(cdBannerFile($this->cd)))->toBeTrue()
        ->and(DB::connection('main')->table('admin_audit_log')->where('action', 'delete_banner')->where('entity_id', $this->cd)->exists())->toBeTrue();
});

it('I7: offers no confirmation and refuses the delete without a banner on disk; refuses other admins', function () {
    $this->withSession(H::session($this->manager))->get(route('admin.cds.banner.delete', $this->cd))->assertOk()->assertDontSee('هل أنت متأكد');
    $this->withSession(H::session($this->manager))->delete(route('admin.cds.banner.destroy', $this->cd))->assertNotFound();

    cdPutBanner($this->cd);
    $this->withSession(H::session(H::admin(['polls.managepolls'], 'cd-other')))->delete(route('admin.cds.banner.destroy', $this->cd))->assertForbidden();
    expect((int) cdRow($this->cd)->banner)->toBe(1);
});

// ─── cds-code-{id}-{type}.htm (OLD Codes()) ─────────────────────────────────

it('cds-code: serves the HTML, vBulletin and phpBB codes of the restored banner, publicly', function () {
    cdPutBanner($this->cd);
    $image = 'http://localhost/media/w2acd/banner/0/'.$this->cd.'.gif';
    $page = 'http://localhost/cds-item-'.$this->cd.'.htm';

    H::visitor();
    $html = $this->get("/cds-code-{$this->cd}-1.htm")->assertOk()->getContent();
    expect($html)->toContain('قم بنسخ الكود التالي و ضعه في صفحة موقعك بلغة HTML')
        ->toContain(e('<a target="_blank" href="'.$page.'"><img src="'.$image.'" border="0" Width="120" Height="60"'))
        ->toContain(e('إسطوانة الإيمان - موقع الطريق إلى الله'));

    foreach ([2 => 'VBulletin', 3 => 'phpBB'] as $type => $forum) {
        $code = $this->get("/cds-code-{$this->cd}-{$type}.htm")->assertOk()->getContent();
        expect($code)->toContain("بمنتديات {$forum}")
            ->toContain(e("[CENTER][URL=\"{$page}\"][IMG]{$image} [/IMG][/URL]"))
            ->toContain(e("[B][URL=\"{$page}\"]إسطوانة الإيمان [/URL][/B]"))
            ->toContain(e("[B][URL=\"{$page}\"]موقع الطريق إلى الله [/URL][/B][/CENTER]"));
    }
});

it('cds-code: escapes the title inside the code', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $this->cd)->update(['title' => 'عنوان <script>x</script>']);
    cdPutBanner($this->cd);

    expect($this->get("/cds-code-{$this->cd}-1.htm")->assertOk()->getContent())->not->toContain('<script>x</script>');
});

it('cds-code: 404 for an unknown type, an unknown CD, no banner, a hidden CD, or a missing file', function () {
    expect($this->get("/cds-code-{$this->cd}-1.htm")->status())->toBe(404);
    cdPutBanner($this->cd);
    $this->get("/cds-code-{$this->cd}-4.htm")->assertNotFound();
    $this->get("/cds-code-{$this->cd}-0.htm")->assertNotFound();
    $this->get('/cds-code-999999-1.htm')->assertNotFound();

    DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $this->cd)->update(['hidden' => 1]);
    $this->get("/cds-code-{$this->cd}-1.htm")->assertNotFound();

    DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $this->cd)->update(['hidden' => 0]);
    unlink(cdBannerFile($this->cd));
    $this->get("/cds-code-{$this->cd}-1.htm")->assertNotFound();
});
