<?php

/*
 * Khotab final public-parity batch (owner, 2026-10-22):
 *  1. a hidden item's direct download is a 404 for visitors (OLD getit()
 *     refused them), admins keep access;
 *  2. the item's public banner and its three embed-code pages (OLD Detailes()
 *     / Codes());
 *  3. khotab-pdf-{id}.htm: the author keeps the URL, otherwise an item's
 *     transcript is a 301 to /khotab-item-pdf-{id}.htm, which counts it
 *     (OLD getpdf(): pdf = pdf + 1);
 *  4. the author's bucket JPG as the sidebar thumbnail of frame-less items on
 *     the author's own lists (OLD Top10()).
 */

use App\Domain\Content\Services\ContentSidebarWidget;
use App\Support\Downloads\DownloadCapacityGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->media = sys_get_temp_dir().'/w2a-public-'.uniqid();
    config(['admin_khotab.media_root' => $this->media, 'media.base_url' => '/media']);

    $this->slotDir = sys_get_temp_dir().'/khotab-slots-'.uniqid();
    config(['downloads.guard' => true, 'downloads.max_concurrent' => 1, 'downloads.slot_directory' => $this->slotDir]);
    $this->file = tempnam(sys_get_temp_dir(), 'khotab-dl');
    file_put_contents($this->file, 'file-bytes');

    DB::connection('main')->table('nuke_config')->update(['sitename' => 'الطريق إلى الله']);
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 3]);
    H::insert('nuke_islamic_authors', ['id' => 8, 'name' => 'آخر', 'prename' => 'أ.', 'vedio' => 1]);
    $this->visible = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة "ظاهرة"', 'vedio' => 1, 'link' => $this->file, 'downcount' => 5, 'banner' => 1, 'pdf' => 4]);
    $this->hidden = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مخفية', 'vedio' => 1, 'hidden' => 1, 'link' => $this->file, 'downcount' => 9, 'banner' => 1]);
    $this->held = [];
});

afterEach(function () {
    foreach ($this->held as $slot) {
        $slot->release();
    }
    @unlink($this->file);
    foreach (glob($this->slotDir.'/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($this->slotDir);
});

function writeBanner(string $root, int $id): void
{
    $dir = $root.'/banner/'.intdiv($id, 1000);
    @mkdir($dir, 0775, true);
    // a real 2x1 GIF, so getimagesize() reports its size
    file_put_contents($dir.'/'.$id.'.gif', base64_decode('R0lGODlhAgABAIAAAP///wAAACH5BAEAAAAALAAAAAACAAEAAAICBAoAOw=='));
}

function downcount(int $id): int
{
    return (int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $id)->value('downcount');
}

// ─── 1. hidden-item download ────────────────────────────────────────────────

it('refuses a hidden item\'s download to a visitor with 404, before counting or taking a slot', function () {
    // Every slot is taken: a request that reached the guard would get its 503.
    $this->held[] = app(DownloadCapacityGuard::class)->tryAcquire();

    $this->get("/khotab-download-{$this->hidden}.htm")->assertNotFound();
    expect(downcount($this->hidden))->toBe(9);

    // The visible item reaches the guard as before (503 while it is full).
    $this->get("/khotab-download-{$this->visible}.htm")->assertStatus(503);
    expect(downcount($this->visible))->toBe(5);
});

it('lets a contextual admin download a hidden item', function () {
    $response = $this->withSession(H::session(H::superAdmin()))->get("/khotab-download-{$this->hidden}.htm");

    $response->assertOk();
    expect($response->streamedContent())->toBe('file-bytes');
});

it('serves a visible item to a visitor exactly as before', function () {
    $response = $this->get("/khotab-download-{$this->visible}.htm");

    $response->assertOk();
    expect($response->streamedContent())->toBe('file-bytes');
});

// ─── 2. public banner + embed codes ─────────────────────────────────────────

it('shows the banner and its three code links when banner = 1 and the file exists', function () {
    writeBanner($this->media, $this->visible);
    $html = $this->get("/khotab-item-{$this->visible}.htm")->assertOk()->getContent();

    expect($html)->toContain('data-item-banner')
        ->toContain('src="/media/banner/0/'.$this->visible.'.gif"')->toMatch('/<img src="\/media\/banner\/0\/'.$this->visible.'\.gif"\s+width="2" height="1"/')
        ->toContain('href="/khotab-code-'.$this->visible.'-1.htm"')->toContain('href="/khotab-code-'.$this->visible.'-2.htm"')->toContain('href="/khotab-code-'.$this->visible.'-3.htm"')
        ->toContain('شارك معنا و قم بوضع رابط لهذه المادة في موقعك أو توقيعك');
    // after the item details, before the comments
    expect(strpos($html, 'تفاصيل المادة'))->toBeLessThan(strpos($html, 'data-item-banner'));
});

it('shows no banner without its file, while requested (-1), when 0, or for a hidden item', function () {
    expect($this->get("/khotab-item-{$this->visible}.htm")->getContent())->not->toContain('data-item-banner');   // file missing

    writeBanner($this->media, $this->visible);
    foreach ([-1, 0] as $state) {
        DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->visible)->update(['banner' => $state]);
        expect($this->get("/khotab-item-{$this->visible}.htm")->getContent())->not->toContain('data-item-banner');
    }

    writeBanner($this->media, $this->hidden);
    $this->get("/khotab-item-{$this->hidden}.htm")->assertNotFound();
    $this->get("/khotab-code-{$this->hidden}-1.htm")->assertNotFound();
});

it('serves the three embed codes for an eligible banner, escaped, and 404s otherwise', function () {
    writeBanner($this->media, $this->visible);
    $site = rtrim(url('/'), '/');

    $html = $this->get("/khotab-code-{$this->visible}-1.htm")->assertOk()->getContent();
    expect($html)->toContain('قم بنسخ الكود التالي و ضعه في صفحة موقعك بلغة HTML')
        ->toContain(e('<a target="_blank" href="'.$site.'/khotab-item-'.$this->visible.'.htm"><img src="'.$site.'/media/banner/0/'.$this->visible.'.gif" border="0" Width="2" Height="1"'))
        // the title's quotes are escaped inside the code, then escaped for display
        ->toContain('مادة &amp;quot;ظاهرة&amp;quot; لـد. الشيخ - موقع الطريق إلى الله');

    foreach ([2 => 'VBulletin', 3 => 'phpBB'] as $type => $forum) {
        $code = $this->get("/khotab-code-{$this->visible}-{$type}.htm")->assertOk()->getContent();
        expect($code)->toContain('منتديات '.$forum)
            ->toContain('[CENTER][URL=&quot;'.$site.'/khotab-item-'.$this->visible.'.htm&quot;][IMG]'.$site.'/media/banner/0/'.$this->visible.'.gif [/IMG][/URL]')
            ->toContain('د. الشيخ - موقع الطريق إلى الله [/URL][/B][/CENTER]');
    }

    $this->get("/khotab-code-{$this->visible}-4.htm")->assertNotFound();
    $this->get("/khotab-code-{$this->visible}-0.htm")->assertNotFound();
    $this->get('/khotab-code-999999-1.htm')->assertNotFound();
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->visible)->update(['banner' => -1]);
    $this->get("/khotab-code-{$this->visible}-1.htm")->assertNotFound();
});

// ─── 3. khotab-pdf-{id}.htm and the PDF counter ─────────────────────────────

it('keeps khotab-pdf-{id}.htm for an author, redirects an item id to its transcript, and 404s otherwise', function () {
    // An author id: the transcripts page, unchanged.
    $this->get('/khotab-pdf-7.htm')->assertOk()->assertSee('المواد المفرغة');

    // No author with this id, an item with a transcript: permanent redirect.
    $item = H::insert('nuke_islamic_khotab', ['id' => 5000, 'author' => 7, 'title' => 'مفرغة', 'vedio' => 1, 'pdf' => 3]);
    $this->get("/khotab-pdf-{$item}.htm")->assertStatus(301)->assertRedirect("/khotab-item-pdf-{$item}.htm");
    expect((int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $item)->value('pdf'))->toBe(3);   // the redirect itself counts nothing

    // Neither.
    H::insert('nuke_islamic_khotab', ['id' => 5001, 'author' => 7, 'title' => 'بلا تفريغ', 'vedio' => 1, 'pdf' => 0]);
    $this->get('/khotab-pdf-5001.htm')->assertNotFound();
    $this->get('/khotab-pdf-6000.htm')->assertNotFound();

    // Both an author and an item with a transcript: the author wins.
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->visible)->update(['id' => 8]);
    $this->get('/khotab-pdf-8.htm')->assertOk()->assertSee('المواد المفرغة');
});

it('counts a transcript download on the canonical route only, and only when the transcript exists', function () {
    $item = H::insert('nuke_islamic_khotab', ['id' => 5000, 'author' => 7, 'title' => 'مفرغة', 'vedio' => 1, 'pdf' => 3]);

    $this->get("/khotab-pdf-{$item}.htm")->assertRedirect("/khotab-item-pdf-{$item}.htm");
    $this->get("/khotab-item-pdf-{$item}.htm")->assertRedirect('/media/pdf/5/5000.pdf');
    expect((int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $item)->value('pdf'))->toBe(4);

    H::insert('nuke_islamic_khotab', ['id' => 5001, 'author' => 7, 'title' => 'بلا تفريغ', 'vedio' => 1, 'pdf' => 0]);
    $this->get('/khotab-item-pdf-5001.htm')->assertRedirect('/');
    expect((int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', 5001)->value('pdf'))->toBe(0);
    $this->get('/khotab-item-pdf-777777.htm')->assertNotFound();
});

// ─── 4. author JPG as the sidebar thumbnail ─────────────────────────────────

it('uses the list author\'s bucket JPG for frame-less items, keeps frames, and falls back to the placeholder', function () {
    // Item 896's frame, in the configured media root (P0 2026-11-09: the sidebar reads frames and author JPGs
    // from the same configured root — formerly the frame came from the shared tree under public_path()).
    $frameItem = H::insert('nuke_islamic_khotab', ['id' => 896, 'author' => 7, 'title' => 'بإطار', 'vedio' => 1, 'frame' => 1, 'hits' => 100]);
    @mkdir($this->media.'/khotab_frames/0', 0775, true);
    file_put_contents($this->media.'/khotab_frames/0/896.jpg', 'jpg');

    $sidebar = fn (int $author) => app(ContentSidebarWidget::class)->khotabMostDownloadedByAuthor($author, true)->pluck('thumb', 'id');

    // no author JPG yet: placeholder for frame-less items
    expect($sidebar(7)[$this->visible])->toBe('/images/way2_withoutimg.png')->and($sidebar(7)[$frameItem])->toBe('/media/khotab_frames/0/'.$frameItem.'.jpg');

    @mkdir($this->media.'/authors/0', 0775, true);
    file_put_contents($this->media.'/authors/0/7.jpg', 'jpg');
    expect($sidebar(7)[$this->visible])->toBe('/media/authors/0/7.jpg')
        ->and($sidebar(7)[$frameItem])->toBe('/media/khotab_frames/0/'.$frameItem.'.jpg');

    // another author's list never borrows author 7's image; non-author lists keep the placeholder
    H::insert('nuke_islamic_khotab', ['author' => 8, 'title' => 'للآخر', 'vedio' => 1]);
    expect($sidebar(8)->unique()->values()->all())->toBe(['/images/way2_withoutimg.png'])
        ->and(app(ContentSidebarWidget::class)->khotabMostDownloadedByVideoFlag(true)->pluck('thumb')->contains('/media/authors/0/7.jpg'))->toBeFalse();

    // OLD Group() / Series() ran Top10() for the group's author: the group page's sidebar shows it.
    $group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'vedio' => 1, 'hidden' => 0, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->visible)->update(['group_id' => $group]);
    expect($this->get("/khotab-group-{$group}.htm")->assertOk()->getContent())->toContain('/media/authors/0/7.jpg');

    // The author page's profile card is untouched (it never used this JPG, in OLD either).
    expect($this->get('/khotab-video-7.htm')->getContent())->toContain('w2a-author-avatar" src="/media/authors/no_author_image.png"');

});

it('renders the ranking thumbnails on the author page: frame first, then that author\'s JPG, else the placeholder', function () {
    // item 896's frame, in the configured media root (P0 2026-11-09: same root as the author JPG)
    H::insert('nuke_islamic_khotab', ['id' => 896, 'author' => 7, 'title' => 'بإطار', 'vedio' => 1, 'frame' => 1, 'hits' => 100, 'time' => 100]);
    @mkdir($this->media.'/khotab_frames/0', 0775, true);
    file_put_contents($this->media.'/khotab_frames/0/896.jpg', 'jpg');
    H::insert('nuke_islamic_khotab', ['author' => 8, 'title' => 'للآخر', 'vedio' => 1, 'hits' => 50]);
    $thumbs = fn (string $html) => (preg_match_all('/<img src="([^"]+)"\s+class="w2a-ranking-thumb/', $html, $m) ? $m[1] : []);

    // no author JPG yet: the frame item keeps its frame, the frame-less item gets the placeholder
    $page = $this->get('/khotab-video-7.htm')->assertOk()->getContent();
    expect($thumbs($page))->toContain('/media/khotab_frames/0/896.jpg')->toContain('/images/way2_withoutimg.png')
        ->not->toContain('/media/authors/0/7.jpg');

    @mkdir($this->media.'/authors/0', 0775, true);
    file_put_contents($this->media.'/authors/0/7.jpg', 'jpg');

    $response = $this->get('/khotab-video-7.htm')->assertOk();
    $html = $response->getContent();
    expect($thumbs($html))->toContain('/media/khotab_frames/0/896.jpg')->toContain('/media/authors/0/7.jpg')->not->toContain('/images/way2_withoutimg.png')
        // the profile card and a visitor's cacheable page are as before
        ->and($html)->toContain('w2a-author-avatar" src="/media/authors/no_author_image.png"')->not->toContain('data-contextual-admin')
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');

    // another author's page never borrows author 7's JPG
    expect($thumbs($this->get('/khotab-video-8.htm')->getContent()))->not->toContain('/media/authors/0/7.jpg')->toContain('/images/way2_withoutimg.png');

    // the transcripts page (no OLD counterpart) is unchanged: no ranking thumbnails
    expect($thumbs($this->get('/khotab-pdf-7.htm')->getContent()))->toBe([]);
});

it('keeps the admin author page private, with its panel unchanged, alongside the thumbnails', function () {
    @mkdir($this->media.'/authors/0', 0775, true);
    file_put_contents($this->media.'/authors/0/7.jpg', 'jpg');

    $response = $this->withSession(H::session(H::superAdmin()))->get('/khotab-video-7.htm')->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('لوحة تحكم الداعية')->toContain('class="w2a-ranking-thumb')->toContain('/media/authors/0/7.jpg')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    foreach (['تعديل بيانات الداعية', 'إضافة مجموعة', 'إضافة سلسلة', 'إضافة مادة', 'حذف الداعية', 'مواد لم تصنف', 'صيانة العدادات'] as $label) {
        expect($html)->toContain('</i> '.$label.'</');
    }
});
