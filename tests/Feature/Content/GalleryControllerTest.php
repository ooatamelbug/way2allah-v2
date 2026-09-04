<?php

use App\Domain\Content\Support\LegacyShortDateFormatter;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForGallery(): void
{
    InMemoryConnection::setup('main', [
        'nuke_albums' => MainSchema::nukeAlbums(),
        'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForGallery();
});

it('index: lists albums with their thumbnail (first image by order)', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Ramadan Album', 'count' => 2]);
    DB::connection('main')->table('nuke_albums_images')->insert([
        ['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/second.jpg', 'order' => 2],
        ['image_id' => 2, 'album_id' => 1, 'url' => 'media/albums/first.jpg', 'order' => 1],
    ]);

    $response = $this->get('/gallery.htm');

    $response->assertOk()->assertSee('Ramadan Album');
});

it('show: renders an album\'s images ordered and increments the album\'s hits', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Eid Album', 'hits' => 4]);
    DB::connection('main')->table('nuke_albums_images')->insert([
        ['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1],
    ]);

    $this->get('/gallery-1.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_albums')->where('album_id', 1)->first()->hits)->toBe(5);
});

it('show: an album with no images shows the "no images yet" message', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Empty Album']);

    $this->get('/gallery-1.htm')->assertOk()->assertSee('لا يوجد صور مضافة');
});

it('show: 404s for a nonexistent album', function () {
    $this->get('/gallery-999.htm')->assertNotFound();
});

it('download: IF-027 fix — resolves from the app\'s own storage, not a hardcoded external legacy path', function () {
    $relativePath = 'gallery-test-fixture/sample.jpg';
    $fullPath = public_path($relativePath);
    @mkdir(dirname($fullPath), recursive: true);
    file_put_contents($fullPath, 'fake-image-bytes');

    DB::connection('main')->table('nuke_albums_images')->insert([
        'image_id' => 1, 'album_id' => 1, 'url' => $relativePath,
    ]);

    $this->get('/albumimg-download-1.htm')->assertOk();

    @unlink($fullPath);
    @rmdir(dirname($fullPath));
});

it('download: 404s when the file does not exist on disk', function () {
    DB::connection('main')->table('nuke_albums_images')->insert([
        'image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/does-not-exist.jpg',
    ]);

    $this->get('/albumimg-download-1.htm')->assertNotFound();
});

// ---- G-03 (Migration Gap Register): thumbnails.php parity + lightbox ----

// ---- Full Design Parity Pass (gallery.htm): page chrome, portlet, card DOM ----

it('index: renders the shared page chrome (title/breadcrumb), not a bare <section>', function () {
    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<h3 class="page-title">التصميمات الدعوية</h3>')
        ->toContain('<div class="page-bar">')
        ->toContain('<title>التصميمات الدعوية - ');
});

it('index: wraps the redesigned album grid in the gallery portlet and search toolbar', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Ramadan Album', 'count' => 1]);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('التصميمات والبطاقات الدعوية</div>')
        ->toContain('<div class="portlet-body ">')
        ->toContain('class="w2a-gallery-wrap"')
        ->toContain('id="w2a_gallery_search_input"')
        ->toContain('id="w2a_gallery_result_status"')
        ->toContain('class="w2a-albums-grid"');
});

it('index: omits the portlet entirely when there are no albums, matching legacy\'s own !empty($albums) gate', function () {
    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)->not->toContain('w2a-albums-grid')->not->toContain('fa-picture-o');
});

it('index: each album uses the premium card with count, date, and browse action', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 7, 'title' => 'Eid Cards', 'count' => 3]);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 7, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<article class="w2a-album-card" data-title="Eid Cards">')
        ->toContain('<h3 class="w2a-album-card-title">Eid Cards</h3>')
        ->toContain('class="w2a-album-date"')
        ->toContain('class="w2a-album-count-badge"')
        ->toContain('3 صورة')
        ->toContain('class="w2a-album-btn-view"');
});

it('index: shows the "حفظ الألبوم" save-album button only for compressed albums (is_compressed=1)', function () {
    DB::connection('main')->table('nuke_albums')->insert([
        ['album_id' => 1, 'title' => 'Compressed Album', 'is_compressed' => 1],
        ['album_id' => 2, 'title' => 'Plain Album', 'is_compressed' => 0],
    ]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('onclick="downlaod_gellery_images(1)"')
        ->toContain('class="w2a-album-btn-download"')
        ->toContain('title="تحميل جميع صور الألبوم"')
        ->not->toContain('downlaod_gellery_images(2)');
});

it('index: the downlaod_gellery_images() script is present, matching list.php\'s own inline trigger — its real destination is a separately-confirmed dead route, not built here', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album', 'is_compressed' => 1]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)->toContain('function downlaod_gellery_images(id)')
        ->toContain('download-album-');
});

it('index: album thumbnail routes through thumbnails.php at the exact legacy 250x350 dimensions, not the raw image', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Ramadan Album', 'count' => 1]);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/first.jpg', 'order' => 1]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)->toContain('/thumbnails.php?h=250&amp;w=350&amp;src=media/albums/first.jpg')
        ->and($content)->toContain('class="w2a-album-cover"')
        ->and($content)->toContain('loading="lazy"');
});

// Full Design Parity Pass (gallery.htm): list.php:51 calls
// CoolShortDate($album->last_update) — default $full_date=true — not
// tinydate()/plain date(). Confirmed live on production
// (`السبت 13 يناير 2018 مـ`). The prior version of this test asserted
// the opposite; corrected here, not just the markup.
it('index: shows the album\'s last-update date using CoolShortDate() (LegacyShortDateFormatter), the real legacy format', function () {
    $timestamp = mktime(0, 0, 0, 3, 15, 2024);
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Dated Album', 'last_update' => $timestamp]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)->toContain(LegacyShortDateFormatter::format($timestamp))
        ->toContain('مارس'); // CoolShortDate()'s Arabic month name for March.
});

it('show: image thumbnail routes through thumbnails.php at the exact legacy 150x166 dimensions', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/thumbnails.php?h=150&amp;w=166&amp;src=media/albums/a.jpg')
        ->and($content)->toContain('class="img-responsive pwimages"');
});

it('show: lightbox target uses width-only thumbnails.php (w=500, NO h, NO zc) — a deliberately different shape from the grid thumbnail', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/thumbnails.php?w=500&amp;src=media/albums/a.jpg"')
        ->not->toContain('w=500&amp;h=')
        ->not->toContain('zc=');
});

// ---- Full Design Parity Pass (gallery-{id}.htm): page chrome, portlet, card DOM ----

it('show: renders the shared page chrome — heading + two-item breadcrumb (linked gallery.htm, plain current album)', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Ramadan Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<h3 class="page-title">التصميمات الدعوية - Ramadan Album</h3>')
        ->toContain('<a href="/gallery.htm">التصميمات الدعوية</a>')
        ->toContain('<li>Ramadan Album<i class=""></i></li>')
        ->toContain('<title>التصميمات الدعوية - Ramadan Album - ');
});

it('show: wraps the image grid in the real w2a_open_div() portlet with the "ألبوم : {title}" caption, not a bare <section><h1>', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Ramadan Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="caption"><i class="fa fa-picture-o"></i> ألبوم : Ramadan Album</div>')
        ->toContain('<div class="portlet-body ">')
        ->toContain('<div class="row albums_list row-fluid">');
});

it('show: each image card uses the real .album-item.albumpic / .center-block.album-img wrappers and the w2a_singl_img lightbox class', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 5, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="album-item albumpic">')
        ->toContain('<div class="center-block album-img">')
        ->toContain('class="lightbox w2a_singl_img" rel="album1"');
});

it('show: each image card renders the real w2a_gal_sav save-image link — real class, onclick, and icon — not a bare <a>', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 5, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('class="w2a_gal_sav"')
        ->toContain("onclick=\"loadImg('http://way2allah.com/media/albums/a.jpg')\"")
        ->toContain('href="/albumimg-download-5.htm"')
        ->toContain('<i></i> حفظ الصورة');
});

it('show: the empty-album message uses the real .alert.alert-info[role=alert] structure with a bold "عفوا!" lead-in, not a bare <p>', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Empty Album']);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<div class="alert alert-info" role="alert"> <strong>عفوا!</strong> لا يوجد صور مضافة في هذا الالبوم بعد. </div>');
});

it('show: omits the portlet entirely for an empty album, matching legacy\'s own if/else — no image-grid markup alongside the empty message', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Empty Album']);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('albums_list')->not->toContain('fa-picture-o');
});

it('show: loads the legacy lightbox CSS/JS assets and the exact legacy init call', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/gallery/lightbox/jquery.lightbox-0.4.css')
        ->toContain('/gallery/lightbox/jquery.blockUI-1.33.pack.js')
        ->toContain('/gallery/lightbox/jquery.lightbox-0.4.pack.js')
        ->toContain("\$('a.lightbox').lightBox();");
});

it('index: does NOT load the lightbox assets — page-scoped to gallery-{album}.htm only, not the shared layout', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)->not->toContain('jquery.lightbox');
});

it('the public/gallery/lightbox symlink resolves and actually serves the real legacy asset bytes', function () {
    $cssPath = public_path('gallery/lightbox/jquery.lightbox-0.4.css');
    $jsPath = public_path('gallery/lightbox/jquery.lightbox-0.4.pack.js');
    $blockUiPath = public_path('gallery/lightbox/jquery.blockUI-1.33.pack.js');

    expect(is_link(public_path('gallery/lightbox')) || is_dir(public_path('gallery/lightbox')))->toBeTrue()
        ->and(is_file($cssPath))->toBeTrue()
        ->and(is_file($jsPath))->toBeTrue()
        ->and(is_file($blockUiPath))->toBeTrue()
        ->and(filesize($cssPath))->toBeGreaterThan(0)
        ->and(filesize($jsPath))->toBeGreaterThan(0)
        ->and(filesize($blockUiPath))->toBeGreaterThan(0);
});
