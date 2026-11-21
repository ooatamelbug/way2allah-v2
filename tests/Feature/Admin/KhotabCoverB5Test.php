<?php

/*
 * F10 / B5 — custom Khotab cover (owner-approved 2026-11-16): `media/khotab_covers/{b}/{id}.jpg`, the file itself is
 * the state. Covers: resolver priority on every existing image surface, upload / replace / remove, validation,
 * failure safety, the per-item lock, Archive.org independence, cache busting, and that nothing else changes
 * (player, featured eligibility, database).
 */

use App\Domain\Admin\Actions\Khotab\KhotabArchiveImages;
use App\Domain\Admin\Actions\Khotab\KhotabItemCoverAction;
use App\Domain\Admin\Contextual\Controls\KhotabItemControls;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Support\KhotabCover;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AnasheedVisibilityHarness as H;

uses(RefreshDatabase::class);

const B5_VIDEO = 4101;      // video, archive frame on disk
const B5_GIF = 4102;        // video, animated GIF + frame (GIF-first surfaces)
const B5_AUDIO = 4103;      // audio item
const B5_PLAIN = 4104;      // video, no archive images (frame = 0, gif = 0)

beforeEach(function () {
    H::setUp();
    $this->media = sys_get_temp_dir().'/w2a-b5-'.uniqid();
    mkdir($this->media, 0775, true);
    config(['admin_khotab.media_root' => $this->media, 'media.base_url' => '/media', 'admin_khotab.cover_lock_wait' => 0]);

    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'الشيخ', 'prename' => '']);
    foreach ([
        [B5_VIDEO, 'مرئي', 1, 1, 0, 30], [B5_GIF, 'مرئي متحرك', 1, 1, 1, 20], [B5_AUDIO, 'صوتي', 0, 1, 0, 10], [B5_PLAIN, 'مرئي بلا صورة', 1, 0, 0, 5],
    ] as [$id, $title, $vedio, $frame, $gif, $hits]) {
        $db->table('nuke_islamic_khotab')->insert(['id' => $id, 'title' => $title, 'vedio' => $vedio, 'frame' => $frame, 'gif' => $gif,
            'hidden' => 0, 'author' => 7, 'channel_id' => 3, 'hits' => $hits, 'newslist' => 1, 'lastmirror' => $id, 'time' => 1700000000,
            'link' => 'https://archive.org/download/b5/'.$id.'.mp4']);
    }
    b5Write($this->media.'/khotab_frames/4/'.B5_VIDEO.'.jpg', b5Jpeg(160, 110, [10, 20, 30]));
    b5Write($this->media.'/khotab_frames/4/'.B5_GIF.'.jpg', b5Jpeg(160, 110, [10, 20, 30]));
    b5Write($this->media.'/khotab_gifs/4/'.B5_GIF.'.gif', 'GIF89a-fixture');
    $this->rowsBefore = DB::connection('main')->table('nuke_islamic_khotab')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
});

afterEach(function () {
    if (is_dir($this->media)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->media, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->media);
    }
});

function b5Write(string $path, string $bytes): void
{
    is_dir(dirname($path)) || mkdir(dirname($path), 0775, true);
    file_put_contents($path, $bytes);
}

/** @param array{0:int,1:int,2:int} $rgb */
function b5Image(string $format, int $w, int $h, array $rgb = [200, 40, 40], bool $alpha = false): string
{
    $img = imagecreatetruecolor($w, $h);
    if ($alpha) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 255, 127));
    } else {
        imagefill($img, 0, 0, imagecolorallocate($img, ...$rgb));
    }
    ob_start();
    match ($format) {
        'jpeg' => imagejpeg($img, null, 90),
        'png' => imagepng($img),
        'webp' => imagewebp($img),
        'gif' => imagegif($img),
    };

    return (string) ob_get_clean();
}

function b5Jpeg(int $w, int $h, array $rgb = [200, 40, 40]): string
{
    return b5Image('jpeg', $w, $h, $rgb);
}

function b5Upload(string $bytes, string $name, string $mime): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'b5u');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, $mime, null, true);
}

function b5Store(int $id, ?UploadedFile $file = null)
{
    return test()->post(route('admin.khotab.items.cover.store', ['item' => $id]), ['cover' => $file ?? b5Upload(b5Jpeg(800, 450), 'c.jpg', 'image/jpeg')]);
}

function b5CoverFile(int $id): string
{
    return KhotabCover::absolutePath($id);
}

function b5Surfaces(int $id): array
{
    $item = KhotabItem::findOrFail($id);
    $sidebar = app(ContentSidebarWidget::class)->channelMostDownloadedKhotabItems(3)->firstWhere('id', $id);
    $featured = Blade::render('<x-content.featured-items :items="$items" />', ['items' => collect([(object) [
        'id' => $id, 'gif' => $item->gif, 'title' => $item->title, 'prename' => '', 'name' => 'الشيخ', 'channel_id' => 3, 'author' => 7, 'comments' => 0, 'time' => 0, 'hits' => 0,
    ]])]);
    preg_match('/<img src="([^"]+)" alt="[^"]*" class="w2a-featured-img"/', $featured, $m);

    return ['sidebar' => $sidebar?->thumb, 'featured' => html_entity_decode($m[1] ?? ''), 'social' => $item->socialImageUrl()];
}

// ─── resolution priority on the existing surfaces ────────────────────────────

it('1 & 3: without a cover every surface keeps its existing image (frame; GIF first where it was)', function () {
    expect(b5Surfaces(B5_VIDEO))->toBe([
        'sidebar' => '/media/khotab_frames/4/4101.jpg', 'featured' => '/media/khotab_frames/4/4101.jpg', 'social' => '/media/khotab_frames/4/4101.jpg',
    ])->and(b5Surfaces(B5_GIF))->toBe([
        'sidebar' => '/media/khotab_frames/4/4102.jpg', 'featured' => '/media/khotab_gifs/4/4102.gif', 'social' => '/media/khotab_gifs/4/4102.gif',
    ])->and(KhotabCover::url(B5_VIDEO))->toBeNull();
});

it('2 & 4: a valid cover wins on every surface, including over the animated GIF; the home widget too', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_VIDEO)->assertRedirect(route('admin.khotab.items.cover', ['item' => B5_VIDEO]));
    b5Store(B5_GIF)->assertRedirect();

    foreach ([B5_VIDEO, B5_GIF] as $id) {
        $cover = KhotabCover::url($id);
        expect($cover)->toStartWith('/media/khotab_covers/4/'.$id.'.jpg?v=')
            ->and(b5Surfaces($id))->toBe(['sidebar' => $cover, 'featured' => $cover, 'social' => $cover]);
    }

    auth('admin')->forgetUser();
    expect($this->get('/')->assertOk()->getContent())->toContain(e(KhotabCover::url(B5_VIDEO)));
});

it('5: removing the cover restores the existing behaviour (frame, and the GIF where it was first)', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_GIF);
    $this->delete(route('admin.khotab.items.cover.destroy', ['item' => B5_GIF]))->assertRedirect()->assertSessionHas('contextual_admin_success', 'تم حذف صورة الغلاف، وعادت صورة الأرشيف');

    expect(is_file(b5CoverFile(B5_GIF)))->toBeFalse()
        ->and(b5Surfaces(B5_GIF))->toBe(['sidebar' => '/media/khotab_frames/4/4102.jpg', 'featured' => '/media/khotab_gifs/4/4102.gif', 'social' => '/media/khotab_gifs/4/4102.gif'])
        ->and(is_file($this->media.'/khotab_frames/4/4102.jpg'))->toBeTrue();   // the archive frame was never touched
});

it('a cover works for a video item that has no Archive.org image; removal returns to its existing fallback', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    expect(b5Surfaces(B5_PLAIN)['social'])->toBeNull();

    b5Store(B5_PLAIN);
    expect(b5Surfaces(B5_PLAIN)['social'])->toBe(KhotabCover::url(B5_PLAIN));

    $this->delete(route('admin.khotab.items.cover.destroy', ['item' => B5_PLAIN]));
    expect(b5Surfaces(B5_PLAIN)['social'])->toBeNull();
});

// ─── 13 & 14: missing file, cache busting ───────────────────────────────────

it('13: a missing or empty cover file falls back to the existing image', function () {
    b5Write(b5CoverFile(B5_VIDEO), '');
    expect(KhotabCover::url(B5_VIDEO))->toBeNull()->and(b5Surfaces(B5_VIDEO)['sidebar'])->toBe('/media/khotab_frames/4/4101.jpg');

    unlink(b5CoverFile(B5_VIDEO));
    expect(KhotabCover::url(B5_VIDEO))->toBeNull()->and(KhotabCover::url(0))->toBeNull();
});

it('14: every replacement gets a new cache-busting URL (even within the same second)', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_VIDEO, b5Upload(b5Jpeg(800, 450, [1, 2, 3]), 'a.jpg', 'image/jpeg'));
    $first = KhotabCover::url(B5_VIDEO);
    b5Store(B5_VIDEO, b5Upload(b5Jpeg(800, 450, [250, 250, 250]), 'b.jpg', 'image/jpeg'))->assertSessionHas('contextual_admin_success', 'تم استبدال صورة الغلاف');
    $second = KhotabCover::url(B5_VIDEO);

    expect($first)->not->toBe($second)->and(strtok($first, '?'))->toBe(strtok($second, '?'));
});

// ─── 8 & 9: formats and validation ───────────────────────────────────────────

it('8: accepts JPEG, PNG (transparency flattened) and WebP, stores a re-encoded JPEG, downscales keeping the aspect ratio', function (string $format, string $name, string $mime, int $w, int $h, array $stored) {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');

    b5Store(B5_VIDEO, b5Upload(b5Image($format, $w, $h, alpha: $format === 'png'), $name, $mime))->assertSessionHasNoErrors()->assertRedirect();

    $info = getimagesize(b5CoverFile(B5_VIDEO));
    expect($info[2])->toBe(IMAGETYPE_JPEG)->and([$info[0], $info[1]])->toBe($stored);
    if ($format === 'png') {
        $img = imagecreatefromjpeg(b5CoverFile(B5_VIDEO));
        $rgb = imagecolorat($img, 5, 5);
        expect(($rgb >> 16) & 0xFF)->toBeGreaterThan(240);   // transparent → white, not black
    }
})->with([
    'jpeg' => ['jpeg', 'c.jpg', 'image/jpeg', 800, 450, [800, 450]],
    'png (transparent)' => ['png', 'c.png', 'image/png', 400, 300, [400, 300]],
    'webp' => ['webp', 'c.webp', 'image/webp', 640, 360, [640, 360]],
    'oversized jpeg is downscaled' => ['jpeg', 'big.jpg', 'image/jpeg', 2560, 1440, [1280, 720]],
    'tall image keeps aspect' => ['jpeg', 'tall.jpg', 'image/jpeg', 900, 1800, [640, 1280]],
]);

it('9b: a file PHP refused before validation (over upload_max_filesize) returns to the cover page with a readable Arabic message; nothing is stored or audited', function (int $phpError) {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    $file = new UploadedFile(tempnam(sys_get_temp_dir(), 'b5big'), 'phone-photo.jpg', 'image/jpeg', $phpError, true);

    $html = $this->from(route('admin.khotab.items.cover', ['item' => B5_VIDEO]))->followingRedirects()
        ->post(route('admin.khotab.items.cover.store', ['item' => B5_VIDEO]), ['cover' => $file])->assertOk()->getContent();

    expect($html)->toContain('تعذر رفع الصورة: تأكد أن حجمها لا يتجاوز 2 ميجابايت ثم حاول مرة أخرى')
        ->not->toContain('failed to upload')
        ->toContain('لا توجد صورة مخصصة')
        ->and(is_file(b5CoverFile(B5_VIDEO)))->toBeFalse()
        ->and(DB::connection('main')->table('admin_audit_log')->where('action', 'like', '%cover%')->count())->toBe(0);
})->with([
    'over upload_max_filesize' => [UPLOAD_ERR_INI_SIZE],
    'over the form MAX_FILE_SIZE' => [UPLOAD_ERR_FORM_SIZE],
    'partial upload' => [UPLOAD_ERR_PARTIAL],
]);

it('9: rejects invalid, disguised, corrupted, unsupported, too small, too large and oversized files; the current cover is unchanged', function (string $bytes, string $name, string $mime) {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_VIDEO);
    $before = hash_file('sha256', b5CoverFile(B5_VIDEO));
    $audit = count(H::audit());

    b5Store(B5_VIDEO, b5Upload($bytes, $name, $mime))->assertSessionHasErrors('cover');

    expect(hash_file('sha256', b5CoverFile(B5_VIDEO)))->toBe($before)->and(H::audit())->toHaveCount($audit)
        ->and(glob(dirname(b5CoverFile(B5_VIDEO)).'/*'))->toBe([b5CoverFile(B5_VIDEO)]);   // no temporary left behind
})->with([
    'text renamed .jpg' => ['not an image', 'x.jpg', 'image/jpeg'],
    'php renamed .jpg' => ['<?php echo 1;', 'x.jpg', 'image/jpeg'],
    'truncated jpeg' => fn () => [substr(b5Jpeg(400, 300), 0, 200), 'x.jpg', 'image/jpeg'],
    'gif' => fn () => [b5Image('gif', 400, 300), 'x.gif', 'image/gif'],
    'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', 'x.svg', 'image/svg+xml'],
    'too small' => fn () => [b5Jpeg(100, 60), 'x.jpg', 'image/jpeg'],
    'too large dimensions' => fn () => [b5Jpeg(6100, 200), 'x.jpg', 'image/jpeg'],
    'over 2 MiB' => fn () => [b5Jpeg(400, 300).str_repeat("\0", 2 * 1024 * 1024 + 10), 'x.jpg', 'image/jpeg'],
    'empty file' => ['', 'x.jpg', 'image/jpeg'],
]);

// ─── 6 & 7: scope and authorization ──────────────────────────────────────────

it('6: an audio item cannot get a cover (and its page offers no upload)', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');

    b5Store(B5_AUDIO)->assertSessionHasErrors(['cover' => 'الصورة المخصصة متاحة لمواد الفيديو فقط']);
    expect(is_file(b5CoverFile(B5_AUDIO)))->toBeFalse()->and(H::audit())->toBe([]);
    expect($this->get(route('admin.khotab.items.cover', ['item' => B5_AUDIO]))->assertOk()->getContent())
        ->toContain('متاحة لمواد الفيديو فقط')->not->toContain('name="cover"');
});

it('7: only khotab.edititem holders (and super-admins) may view, upload, replace or remove', function () {
    foreach ([[], ['khotab.additem', 'khotab.uploadbanner', 'anasheed.editanasheed']] as $i => $perms) {
        $this->actingAs(H::admin($perms, 'b5-noperm-'.$i), 'admin');
        $this->get(route('admin.khotab.items.cover', ['item' => B5_VIDEO]))->assertForbidden();
        b5Store(B5_VIDEO)->assertForbidden();
        $this->delete(route('admin.khotab.items.cover.destroy', ['item' => B5_VIDEO]))->assertForbidden();
    }
    expect(is_file(b5CoverFile(B5_VIDEO)))->toBeFalse()->and(H::audit())->toBe([]);

    $this->actingAs(H::superAdmin(), 'admin');
    b5Store(B5_VIDEO)->assertRedirect();
    expect(is_file(b5CoverFile(B5_VIDEO)))->toBeTrue();
});

it('7b: the action re-checks authorization; visitors are sent away; GET cannot write; forms carry CSRF', function () {
    $outsider = H::admin(['khotab.additem'], 'b5-outsider');
    expect(fn () => app(KhotabItemCoverAction::class)->upload($outsider, KhotabItem::findOrFail(B5_VIDEO), b5Upload(b5Jpeg(400, 300), 'c.jpg', 'image/jpeg')))
        ->toThrow(AuthorizationException::class);

    expect(b5Store(B5_VIDEO)->status())->toBeIn([302, 401]);
    expect(is_file(b5CoverFile(B5_VIDEO)))->toBeFalse();

    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_VIDEO);
    $this->get(route('admin.khotab.items.cover.destroy', ['item' => B5_VIDEO]))->assertOk();   // GET = the page, not a delete
    expect(is_file(b5CoverFile(B5_VIDEO)))->toBeTrue()
        ->and($this->get(route('admin.khotab.items.cover', ['item' => B5_VIDEO]))->getContent())
        ->toContain('name="_token"')->toContain('name="_method" value="DELETE"')->toContain('enctype="multipart/form-data"')
        ->toContain('data-cover-preview')->toContain('data-archive-preview');
});

it('the item toolbar offers «صورة الغلاف» for video items only', function () {
    $video = KhotabItemControls::toolbar(KhotabItem::findOrFail(B5_VIDEO));
    $audio = KhotabItemControls::toolbar(KhotabItem::findOrFail(B5_AUDIO));
    $labels = fn (array $controls) => array_map(fn ($c) => $c->label, $controls);

    expect($labels($video))->toContain('صورة الغلاف')->and($labels($audio))->not->toContain('صورة الغلاف');
});

// ─── audit, 10 failure safety, 11 concurrency, removal ───────────────────────

it('records upload, replacement and removal in the audit log, without touching any legacy column', function () {
    $admin = H::admin(['khotab.edititem']);
    $this->actingAs($admin, 'admin');

    b5Store(B5_VIDEO);
    b5Store(B5_VIDEO, b5Upload(b5Image('png', 400, 300), 'c.png', 'image/png'));
    $this->delete(route('admin.khotab.items.cover.destroy', ['item' => B5_VIDEO]));
    $this->delete(route('admin.khotab.items.cover.destroy', ['item' => B5_VIDEO]))->assertSessionHas('contextual_admin_success', 'لا توجد صورة غلاف لهذه المادة');

    $audit = H::audit();
    expect(array_column($audit, 'action'))->toBe(['upload_cover', 'replace_cover', 'remove_cover'])
        ->and(array_unique(array_column($audit, 'module')))->toBe(['khotab'])
        ->and((int) $audit[0]->entity_id)->toBe(B5_VIDEO)->and((int) $audit[0]->actor_admin_id)->toBe((int) $admin->getKey());
    $meta = json_decode($audit[1]->metadata, true);
    expect($meta['path'])->toBe('media/khotab_covers/4/4101.jpg')->and($meta['source_format'])->toBe('png')
        ->and($meta['stored_size'])->toBe([400, 300])->and(strlen($meta['sha256']))->toBe(64);
});

it('10: a failed replacement (the audit write fails) keeps the current cover byte-for-byte and leaves no temporary file', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_VIDEO);
    $before = hash_file('sha256', b5CoverFile(B5_VIDEO));
    Schema::connection('main')->drop('admin_audit_log');

    $this->withoutExceptionHandling();
    expect(fn () => app(KhotabItemCoverAction::class)->upload(H::admin(['khotab.edititem'], 'b5-second'), KhotabItem::findOrFail(B5_VIDEO), b5Upload(b5Jpeg(900, 500, [9, 9, 9]), 'n.jpg', 'image/jpeg')))
        ->toThrow(QueryException::class);

    expect(hash_file('sha256', b5CoverFile(B5_VIDEO)))->toBe($before)
        ->and(glob(dirname(b5CoverFile(B5_VIDEO)).'/*'))->toBe([b5CoverFile(B5_VIDEO)]);
});

it('10b: a failed first upload leaves no cover and no temporary file; a failed removal keeps the cover', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_GIF);
    Schema::connection('main')->drop('admin_audit_log');
    $actor = H::admin(['khotab.edititem'], 'b5-third');

    expect(fn () => app(KhotabItemCoverAction::class)->upload($actor, KhotabItem::findOrFail(B5_VIDEO), b5Upload(b5Jpeg(400, 300), 'n.jpg', 'image/jpeg')))->toThrow(QueryException::class)
        ->and(fn () => app(KhotabItemCoverAction::class)->remove($actor, KhotabItem::findOrFail(B5_GIF)))->toThrow(QueryException::class);

    expect(is_file(b5CoverFile(B5_VIDEO)))->toBeFalse()->and(glob(dirname(b5CoverFile(B5_VIDEO)).'/4101*'))->toBe([])
        ->and(is_file(b5CoverFile(B5_GIF)))->toBeTrue()->and(glob(dirname(b5CoverFile(B5_GIF)).'/4102*'))->toBe([b5CoverFile(B5_GIF)]);
});

it('11: concurrent replacement is serialized — while another update holds the item lock, a second one is refused and changes nothing', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_VIDEO);
    $before = hash_file('sha256', b5CoverFile(B5_VIDEO));

    $lock = Cache::lock('khotab-cover:'.B5_VIDEO, 30);
    expect($lock->get())->toBeTrue();
    b5Store(B5_VIDEO, b5Upload(b5Jpeg(900, 500, [9, 9, 9]), 'n.jpg', 'image/jpeg'))
        ->assertSessionHasErrors(['cover' => 'يجري تعديل صورة هذه المادة الآن، حاول مرة أخرى بعد لحظات']);
    $this->delete(route('admin.khotab.items.cover.destroy', ['item' => B5_VIDEO]))->assertSessionHasErrors('cover');
    expect(hash_file('sha256', b5CoverFile(B5_VIDEO)))->toBe($before);

    $lock->release();
    b5Store(B5_VIDEO, b5Upload(b5Jpeg(900, 500, [9, 9, 9]), 'n.jpg', 'image/jpeg'))->assertSessionHasNoErrors();
    expect(hash_file('sha256', b5CoverFile(B5_VIDEO)))->not->toBe($before)
        ->and(glob(dirname(b5CoverFile(B5_VIDEO)).'/4101*'))->toBe([b5CoverFile(B5_VIDEO)]);
});

// ─── 12: Archive.org independence ────────────────────────────────────────────

it('12: an Archive.org frame refresh writes only khotab_frames; the cover stays and keeps winning', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_VIDEO);
    $cover = hash_file('sha256', b5CoverFile(B5_VIDEO));
    $newFrame = b5Jpeg(512, 384, [0, 255, 0]);
    Http::fake(['archive.org/*' => Http::response($newFrame, 200)]);

    $flags = app(KhotabArchiveImages::class)->store(B5_VIDEO, '', 'https://archive.org/download/b5/'.B5_VIDEO.'.thumbs/x_000005.jpg');

    expect($flags)->toBe(['frame' => 1])
        ->and(file_get_contents($this->media.'/khotab_frames/4/4101.jpg'))->toBe($newFrame)     // the archive copy was refreshed
        ->and(hash_file('sha256', b5CoverFile(B5_VIDEO)))->toBe($cover)                          // the cover was not touched
        ->and(b5Surfaces(B5_VIDEO)['sidebar'])->toBe(KhotabCover::url(B5_VIDEO));

    $this->delete(route('admin.khotab.items.cover.destroy', ['item' => B5_VIDEO]));
    expect(b5Surfaces(B5_VIDEO)['sidebar'])->toBe('/media/khotab_frames/4/4101.jpg');           // the refreshed frame now shows
});

// ─── 15–18: nothing else changes ─────────────────────────────────────────────

it('16: the video player is unchanged — no poster, no cover', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_VIDEO);
    auth('admin')->forgetUser();

    $html = $this->post('/media-player', ['id' => B5_VIDEO, 'type' => 'khotab'])->assertOk()->getContent();

    expect($html)->toContain('<video')->not->toContain('poster')->not->toContain('khotab_covers');
});

it('17: featured eligibility is unchanged — a cover never makes an item eligible; eligible items stay eligible', function () {
    $this->actingAs(H::admin(['khotab.edititem']), 'admin');
    b5Store(B5_PLAIN);   // frame = 0, gif = 0: not eligible
    b5Store(B5_VIDEO);   // frame = 1: eligible

    $seen = [];
    for ($i = 0; $i < 60; $i++) {
        foreach (app(ContentSidebarWidget::class)->khotabRandomFeatured(4) as $row) {
            $seen[(int) $row->id] = true;
        }
    }

    expect($seen)->not->toHaveKey(B5_PLAIN)->toHaveKey(B5_VIDEO);
});

it('18: no database write besides the audit rows, no legacy flag changed, no schema change', function () {
    $tablesBefore = collect(Schema::connection('main')->getTables())->pluck('name')->sort()->values()->all();
    $writes = [];
    DB::connection('main')->listen(function ($q) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $q->sql) === 1) {
            $writes[] = $q->sql;
        }
    });
    $this->actingAs(H::admin(['khotab.edititem'], 'b5-writer'), 'admin');
    $writes = [];

    b5Store(B5_VIDEO);
    b5Store(B5_VIDEO, b5Upload(b5Image('webp', 640, 360), 'c.webp', 'image/webp'));
    $this->delete(route('admin.khotab.items.cover.destroy', ['item' => B5_VIDEO]));

    expect(collect($writes)->reject(fn ($sql) => str_contains($sql, 'admin_audit_log'))->values()->all())->toBe([])
        ->and(DB::connection('main')->table('nuke_islamic_khotab')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe($this->rowsBefore)
        ->and(collect(Schema::connection('main')->getTables())->pluck('name')->sort()->values()->all())->toBe($tablesBefore);
});
