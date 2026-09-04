<?php

use App\Domain\Content\Events\CommentPosted;
use App\Domain\Content\Mail\KhotabFriendMail;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForKhotabItemController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
        'nuke_islamic_comments' => MainSchema::nukeIslamicComments(),
        'ips' => MainSchema::ips(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForKhotabItemController();
});

function insertKhotabAuthor(int $id = 1): void
{
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => $id, 'name' => 'Author Name', 'prename' => 'Sheikh',
    ]);
}

it('show: renders the item detail page with title, description, and author', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item One', 'description' => 'A description',
        'vedio' => 1, 'hidden' => 0, 'hits' => 5, 'downcount' => 2,
    ]);

    $response = $this->get('/khotab-item-1.htm');

    $response->assertOk()->assertSee('Item One')->assertSee('A description');
});

it('show: 404s for a hidden item', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Hidden Item', 'vedio' => 1, 'hidden' => 1,
    ]);

    $this->get('/khotab-item-1.htm')->assertNotFound();
});

// ---- Missing-series repair (decision-log #55), MIGRATION_STRICTNESS_DEFECT,
// owner-approved, explicitly NOT legacy parity — see class docblock. ----

it('show: renders normally, without a series breadcrumb crumb, when ser_id references a series row that does not exist at all', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 999999, 'title' => 'Orphan Series Item',
        'description' => 'A real description', 'vedio' => 1, 'hidden' => 0, 'hits' => 5, 'downcount' => 2,
    ]);

    $response = $this->get('/khotab-item-1.htm');
    $content = $response->assertOk()->getContent();

    expect($content)->toContain('Orphan Series Item')
        ->and($content)->toContain('A real description')
        ->and($content)->not->toContain('/khotab-series-999999.htm')
        ->and($content)->not->toContain('سلسلة Orphan'); // no "series {title}" breadcrumb text emitted
});

it('show: an orphan-series item still renders download/PDF actions, mirrors, and comments — series absence only omits the breadcrumb crumb', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 999999, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'pdf' => 1, 'comments' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'HD quality', 'link' => '/tmp/x.mp4', 'linksize' => 100, 'hits' => 3,
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'A commenter', 'comment' => 'A real comment', 'view' => 1, 'mytime' => 100,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/khotab-download-1.htm')
        ->and($content)->toContain('/khotab-item-pdf-1.htm')
        ->and($content)->toContain('HD quality')
        ->and($content)->toContain('A real comment')
        ->and($content)->toContain('class="w2a-comments-wrap"')
        ->and($content)->toContain('class="w2a-comment-card"');
});

it('show: ser_id=0 items are unaffected by the missing-series repair — no series lookup is attempted, no breadcrumb crumb, unchanged from before', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 0, 'title' => 'Plain Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();
    $breadcrumb = substr($content, (int) strpos($content, 'page-breadcrumb'), 800);

    expect($content)->toContain('Plain Item')
        ->and($breadcrumb)->not->toContain('khotab-series-');
});

it('show: a valid, visible series still renders the series breadcrumb crumb exactly as before — regression guard for the missing-series repair', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'title' => 'My Series', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 10, 'title' => 'Series Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<li><a href="/khotab-series-10.htm">سلسلة My Series</a><i class="fa fa-angle-right"></i></li>');
});

// ---- Hidden-series repair (decision-log #56), owner-approved as
// ITEM_VISIBILITY_IS_INDEPENDENT (candidate B), explicitly NOT legacy
// parity — see class docblock. Merged into the same null-series branch as
// the missing-series repair above; KhotabSeriesController deliberately
// untouched (see KhotabSeriesControllerTest for its own 404 regression). ----

it('show: renders normally, without a series breadcrumb crumb, when ser_id references a series that exists but is hidden', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'title' => 'Hidden Series', 'vedio' => 1, 'hidden' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 10, 'title' => 'Hidden-Series Item',
        'description' => 'A real description', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();
    $breadcrumb = substr($content, (int) strpos($content, 'page-breadcrumb'), 800);

    expect($content)->toContain('Hidden-Series Item')
        ->and($content)->toContain('A real description')
        ->and($breadcrumb)->not->toContain('khotab-series-')
        ->and($content)->not->toContain('Hidden Series'); // the hidden series' own title never appears anywhere
});

it('show: a hidden-series item still renders download/PDF actions, mirrors, and comments — matching the missing-series repair\'s own content-independence guarantee', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'title' => 'Hidden Series', 'vedio' => 1, 'hidden' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 10, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'pdf' => 1, 'comments' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'HD quality', 'link' => '/tmp/x.mp4', 'linksize' => 100, 'hits' => 3,
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'A commenter', 'comment' => 'A real comment', 'view' => 1, 'mytime' => 100,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/khotab-download-1.htm')
        ->and($content)->toContain('/khotab-item-pdf-1.htm')
        ->and($content)->toContain('HD quality')
        ->and($content)->toContain('A real comment');
});

it('show: 404s when the item belongs to a hidden group', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_groups')->insert([
        'id' => 20, 'author_id' => 1, 'title' => 'Group', 'vedio' => 1, 'hidden' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'group_id' => 20, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $this->get('/khotab-item-1.htm')->assertNotFound();
});

it('show: increments hits (recordView) on load', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 10,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_islamic_khotab')->find(1)->hits)->toBe(11);
});

it('show: fixes IF-014 — a VIDEO item\'s "Most Downloaded"/"Newest" sidebar shows video items, not audio', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'The Video Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 1],
        ['id' => 2, 'author' => 1, 'title' => 'Other Video', 'vedio' => 1, 'hidden' => 0, 'hits' => 99],
        ['id' => 3, 'author' => 1, 'title' => 'Some Audio Item', 'vedio' => 0, 'hidden' => 0, 'hits' => 99],
    ]);

    $response = $this->get('/khotab-item-1.htm');

    $response->assertOk()->assertSee('Other Video')->assertDontSee('Some Audio Item');
});

it('show: on an AUDIO item, the sidebar shows audio items, not video', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'The Audio Item', 'vedio' => 0, 'hidden' => 0, 'hits' => 1],
        ['id' => 2, 'author' => 1, 'title' => 'Other Audio', 'vedio' => 0, 'hidden' => 0, 'hits' => 99],
        ['id' => 3, 'author' => 1, 'title' => 'Some Video Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 99],
    ]);

    $response = $this->get('/khotab-item-1.htm');

    $response->assertOk()->assertSee('Other Audio')->assertDontSee('Some Video Item');
});

it('show: G-13-03 — the profile-photo box ignores author_image, matching item.php\'s unconditional get_author_img() (unlike author.php/authors.php)', function () {
    // Author id deliberately far outside any real bucket in the now-populated
    // media library (authors/ only has bucket 0/), so this can't collide with
    // a genuine media/authors/sq/{id}.png and silently take the "real file" branch.
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 999999, 'name' => 'Author Name', 'prename' => 'Sheikh', 'author_image' => 'https://example.com/custom.png',
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 999999, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('الملف الشخصي')
        ->and($content)->not->toContain('https://example.com/custom.png')
        ->and($content)->toContain('/media/authors/no_author_image.png');
});

it('show: G-13-01 — sidebar rows render a real <img> thumbnail, not plain text-only links', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'The Video Item', 'vedio' => 1, 'frame' => 0, 'hidden' => 0, 'hits' => 1],
        ['id' => 2, 'author' => 1, 'title' => 'Other Video', 'vedio' => 1, 'frame' => 0, 'hidden' => 0, 'hits' => 99],
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('media-object')
        ->and($content)->toContain('/images/way2_withoutimg.png');
});

it('show: renders mirrors', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'HD quality', 'link' => '/tmp/x.mp4', 'linksize' => 100, 'hits' => 3,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk()->assertSee('HD quality');
});

it('show: each mirror card renders its normalized quality format badge', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'MP4 quality', 'link' => 'https://cdn.example.com/a.mp4', 'linksize' => 100, 'hits' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('class="w2a-quality-badge-fmt">MP4</span>');
});

it('Mirror::extensionIconFilename(): G-13-13 — reproduces item.php\'s exact 3-way branch, including the literal (not substring) soundcloud/youtube segment match', function () {
    $filename = fn (string $link) => (new Mirror(['link' => $link]))->extensionIconFilename();

    expect($filename('https://cdn.example.com/a.mp3'))->toBe('mp3.gif')
        ->and($filename('https://soundcloud.com/a-track'))->toBe('soundcloud.png') // exact "https://soundcloud" dot-segment
        ->and($filename('https://www.soundcloud.com/a-track'))->toBe('.gif') // "https://www" != "https://soundcloud" — falls through to the generic branch with an empty extension
        ->and($filename('https://cdn.example.com/a.mp4'))->toBe('mp4.gif')
        // Confirmed legacy quirk, reproduced not "fixed": the bare 'youtube'
        // segment check only matches a literal ".youtube." split point, which
        // an ordinary https://youtube.com/... URL never produces (its first
        // dot-segment is "https://youtube", not "youtube") — only youtu.be
        // short-links match, via the separate "https://youtu" prefix check.
        ->and($filename('https://youtube.com/watch?v=x'))->toBe('.gif')
        ->and($filename('https://youtu.be/x'))->toBe('youtube_icon.png')
        ->and($filename('https://cdn.example.com/a.wma'))->toBe('wma.gif')
        ->and($filename('https://cdn.example.com/a.AVI'))->toBe('avi.gif'); // lowercased
});

// ---- Visual parity audit (khotab-item-298784.htm, 2026-08-18): Batch 2 / Finding #8 — "تعليق على الدرس" row deliberately NOT implemented ----

it('show: renders a non-empty free-text lesson note without forcing numeric formatting', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'notes' => 'تفسير سورة التوبة',
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('تعليق الدرس')->toContain('تفسير سورة التوبة');
});

// ---- Visual parity audit (khotab-item-298784.htm, 2026-08-18): Batch 2 — action buttons + mirror row structure restored, previously missing ----

it('show: renders semantic responsive action controls for play, download, comments, and sharing', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('class="w2a-item-actions-grid"')
        ->and($content)->toContain('class="w2a-action-btn w2a-action-play"')
        ->and($content)->toContain('href="/khotab-download-1.htm" target="_blank" rel="noopener" class="w2a-action-btn w2a-action-download"')
        ->and($content)->toContain('data-target="#commentsModal" class="w2a-action-btn w2a-action-comment send-comment-btn"')
        ->and($content)->toContain('data-target="#sendFriendModal" class="w2a-action-btn w2a-action-share send-friend-btn"');
});

it('show: the play/watch button markup renders correctly (icon/label vary by vedio) and, as of Batch 4, is backed by a real player — #the_main_player/#w2a_main_player exist and w2a_play() is defined', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Video Item', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'title' => 'Audio Item', 'vedio' => 0, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-item-1.htm')->assertOk()->getContent();
    expect($video)->toContain('onclick="w2a_play(1,\'khotab\')"')
        ->and($video)->toContain('<i class="fa fa-play-circle" aria-hidden="true"></i><span>مشاهدة المادة</span>')
        ->and($video)->toContain('class="w2a-player-panel"')
        ->and(substr_count($video, 'id="the_main_player"'))->toBe(1)
        ->and(substr_count($video, 'id="w2a_main_player"'))->toBe(1)
        ->and($video)->toContain('function w2a_play(id, type)');

    $audio = $this->get('/khotab-item-2.htm')->assertOk()->getContent();
    expect($audio)->toContain('onclick="w2a_play(2,\'khotab\')"')
        ->and($audio)->toContain('<i class="fa fa-headphones" aria-hidden="true"></i><span>استماع المادة</span>')
        // scripts/w2a_play.js/get-mada-player.htm themselves are still not
        // loaded/routed — this app's own /media-player replaces them.
        ->and($audio)->not->toContain('w2a_play.js')
        ->and($audio)->not->toContain('get-mada-player.htm');
});

it('show: as of Batch 3, the send-friend button/trigger is restored, targeting #sendFriendModal', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<button type="button" data-toggle="modal" data-target="#sendFriendModal" class="w2a-action-btn w2a-action-share send-friend-btn">')
        ->and($content)->toContain('<i class="fa fa-paper-plane" aria-hidden="true"></i><span>أرسل لصديق</span>');
});

it('show: mirror qualities render responsive cards with metadata and semantic actions', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        ['id' => 429195, 'khid' => 1, 'vedio' => 1, 'comment' => 'جودة عالية - mp4', 'link' => 'a.mp4', 'linksize' => 319920537, 'hits' => 42],
        ['id' => 429196, 'khid' => 1, 'vedio' => 0, 'comment' => 'جودة صوت - mp3', 'link' => 'a.mp3', 'linksize' => 26948403, 'hits' => 43],
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('class="w2a-qualities-list"')
        ->and(substr_count($content, 'class="w2a-quality-card"'))->toBe(2)
        ->and($content)->toContain('<a class="w2a-quality-title" href="/khotab-mirror-1-429195.htm" download>جودة عالية - mp4</a>')
        ->and($content)->toContain('<a class="w2a-quality-title" href="/khotab-mirror-1-429196.htm" download>جودة صوت - mp3</a>')
        ->and($content)->toContain('onclick="w2a_play(429195,\'khotab_mirror\')"')
        ->and($content)->toContain('onclick="w2a_play(429196,\'khotab_mirror\')"')
        ->and($content)->toContain('42 تنزيل')
        ->and($content)->toContain('43 تنزيل');
});

it('show: mirror numbering restarts and increments correctly across 3+ mirrors, not a fixed/static counter', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        ['id' => 1, 'khid' => 1, 'vedio' => 1, 'comment' => 'First', 'link' => 'a.mp4', 'linksize' => 1, 'hits' => 0],
        ['id' => 2, 'khid' => 1, 'vedio' => 1, 'comment' => 'Second', 'link' => 'b.mp4', 'linksize' => 1, 'hits' => 0],
        ['id' => 3, 'khid' => 1, 'vedio' => 1, 'comment' => 'Third', 'link' => 'c.mp4', 'linksize' => 1, 'hits' => 0],
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<span class="w2a-quality-num">1</span>')
        ->and($content)->toContain('<span class="w2a-quality-num">2</span>')
        ->and($content)->toContain('<span class="w2a-quality-num">3</span>');
});

it('show: the legacy literal get-mada-player.htm path remains unrouted — Batch 4\'s player endpoint lives at the Laravel-native /media-player instead, same URL-adaptation approach as the comment/send-friend routes', function () {
    $response = $this->post('/get-mada-player.htm', ['id' => 1, 'type' => 'khotab']);

    $response->assertNotFound();
});

// ---- Visual parity audit (khotab-item-298784.htm, 2026-08-18): Batch 3 — comment modal (#12) + send-to-friend button/modal/backend (#11) restored, previously missing ----

it('show: #commentsModal exists exactly once, with the correct title/form/hidden-input/error-containers, matching post_comment_modal() (khotab/functions.php:1060-1092)', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'A Lesson Title', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect(substr_count($content, 'id="commentsModal"'))->toBe(1)
        ->and($content)->toContain('<h4 class="modal-title" id="commentsModalLabel">اضافة تعليق على : A Lesson Title</h4>')
        ->and($content)->toContain('<form name="comments_form" id="comments_form" action="" method="post">')
        ->and($content)->toContain('<input type="hidden" name="khotab_id" id="khotab_id" value="1" />')
        ->and($content)->toContain('id="user_nickname_error"')
        ->and($content)->toContain('id="user_comment_error"')
        ->and($content)->toContain('id="send_comment"')
        // The comment trigger button (already present since Batch 2) now has a real target.
        ->and($content)->toContain('data-target="#commentsModal"');
});

it('show: #sendFriendModal exists exactly once, with the correct title/form/fields/error-containers, matching send_friend_modal() (khotab/functions.php:1155-1199)', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'A Lesson Title', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect(substr_count($content, 'id="sendFriendModal"'))->toBe(1)
        ->and($content)->toContain('<h4 class="modal-title" id="sendFriendModalLabel">ارسل مادة : A Lesson Title</h4>')
        ->and($content)->toContain('<form name="sendFriend_form" id="sendFriend_form" action="" method="post">')
        ->and($content)->toContain('name="your_name"')
        ->and($content)->toContain('name="your_email"')
        ->and($content)->toContain('name="friend_name"')
        ->and($content)->toContain('name="friend_email"')
        ->and($content)->toContain('id="your_name_error"')
        ->and($content)->toContain('id="your_email_error"')
        ->and($content)->toContain('id="friend_name_error"')
        ->and($content)->toContain('id="friend_email_error"')
        ->and($content)->toContain('id="send_friend"');
});

it('show: no duplicate element ids across the page — legacy\'s own send_friend_modal() reuses id="khotab_id" (a genuine legacy bug), deliberately not reproduced', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    preg_match_all('/\sid="([^"]+)"/', $content, $matches);
    $ids = $matches[1];

    expect($ids)->toBe(array_unique($ids));
});

it('storeComment: the existing comment backend remains connected to the restored #commentsModal form (unchanged route/response contract)', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $ok = $this->post('/khotab-item-1/comments', ['user_nickname' => 'A Nickname', 'user_comment' => 'A Comment']);
    $ok->assertOk()->assertSeeText('1');

    $missingNickname = $this->post('/khotab-item-1/comments', ['user_nickname' => '', 'user_comment' => 'A Comment']);
    $missingNickname->assertOk()->assertSeeText('2');
});

it('sendToFriend: validates all 4 fields + both emails, matching khotab_send_friend()\'s single combined check (khotab/functions.php:1208)', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $missing = $this->post('/khotab-item-1/send-friend', [
        'your_name' => '', 'your_email' => 'a@example.com', 'friend_name' => 'Friend', 'friend_email' => 'b@example.com',
    ]);
    $missing->assertOk()->assertSeeText('2');

    $badEmail = $this->post('/khotab-item-1/send-friend', [
        'your_name' => 'Me', 'your_email' => 'not-an-email', 'friend_name' => 'Friend', 'friend_email' => 'b@example.com',
    ]);
    $badEmail->assertOk()->assertSeeText('2');
});

it('sendToFriend: on valid input, sends a KhotabFriendMail to the friend\'s email, From the submitter\'s own name/email — matching shams_mail_no_spam()\'s behavior (functions.php:942-949), reused from AnasheedFriendMail\'s established pattern', function () {
    Mail::fake();
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'A Lesson', 'vedio' => 1, 'hidden' => 0,
    ]);

    $response = $this->post('/khotab-item-1/send-friend', [
        'your_name' => 'Sender Name', 'your_email' => 'sender@example.com',
        'friend_name' => 'Friend Name', 'friend_email' => 'friend@example.com',
    ]);

    $response->assertOk()->assertSeeText('1');

    Mail::assertSent(KhotabFriendMail::class, function ($mail) {
        return $mail->hasTo('friend@example.com')
            && $mail->khotabItem->id === 1
            && $mail->yourName === 'Sender Name'
            && $mail->yourEmail === 'sender@example.com'
            && $mail->friendName === 'Friend Name';
    });
});

it('KhotabFriendMail: subject/body match khotab_send_friend() exactly, except the item link uses khotab-item- (not legacy\'s own copy-pasted var-item- anasheed URL)', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 42, 'author' => 1, 'title' => 'The Lesson Title', 'vedio' => 1, 'hidden' => 0,
    ]);
    $khotabItem = KhotabItem::find(42);

    $mail = new KhotabFriendMail($khotabItem, 'Friend Name', 'Your Name', 'you@example.com');
    $rendered = $mail->render();

    expect($mail->hasSubject('The Lesson Title - موقع الطريق الى الله'))->toBeTrue()
        ->and($mail->hasFrom('you@example.com', 'Your Name'))->toBeTrue()
        ->and($rendered)->toContain('/khotab-item-42.htm')
        ->and($rendered)->not->toContain('/var-item-42.htm')
        ->and($rendered)->toContain('Friend Name')
        ->and($rendered)->toContain('Your Name');
});

// ---- Visual parity audit (khotab-item-298784.htm, 2026-08-18): Batch 1 — page-title/breadcrumb/portlet-icon/table restored, previously missing ----

it('show: renders the page-title <h3>, matching item.php:122\'s title($title) call ("{item title} - {author}")', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item One', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<h3 class="page-title">Item One - Sheikh Author Name</h3>')
        // functions.php:541-543's malformed `\f`-escape icon is a legacy
        // authoring bug, deliberately not reproduced.
        ->not->toContain('class=a fa-gift');
});

it('show: breadcrumb\'s first segment ("المرئيات"/"الصوتيات") is a real link to /khotab-{op}.htm, matching item.php:92,96 (not plain text)', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Video Item', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'title' => 'Audio Item', 'vedio' => 0, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-item-1.htm')->assertOk()->getContent();
    expect($video)->toContain('<li><a href="/khotab-video.htm">المرئيات</a><i class="fa fa-angle-right"></i></li>');

    $audio = $this->get('/khotab-item-2.htm')->assertOk()->getContent();
    expect($audio)->toContain('<li><a href="/khotab-audio.htm">الصوتيات</a><i class="fa fa-angle-right"></i></li>');
});

it('show: breadcrumb adds "مجموعة {title}"/"سلسلة {title}" segments only when the item has a group/series, matching item.php:101-106', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'My Group', 'vedio' => 1, 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 10, 'author_id' => 1, 'title' => 'My Series', 'vedio' => 1, 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'group_id' => 0, 'ser_id' => 0, 'title' => 'Plain Item', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'group_id' => 20, 'ser_id' => 0, 'title' => 'Grouped Item', 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'author' => 1, 'group_id' => 0, 'ser_id' => 10, 'title' => 'Series Item', 'vedio' => 1, 'hidden' => 0],
    ]);

    $plain = $this->get('/khotab-item-1.htm')->assertOk()->getContent();
    expect($plain)->not->toContain('مجموعة My Group')->not->toContain('سلسلة My Series');

    $grouped = $this->get('/khotab-item-2.htm')->assertOk()->getContent();
    expect($grouped)->toContain('<li><a href="/khotab-group-20.htm">مجموعة My Group</a><i class="fa fa-angle-right"></i></li>');

    $series = $this->get('/khotab-item-3.htm')->assertOk()->getContent();
    expect($series)->toContain('<li><a href="/khotab-series-10.htm">سلسلة My Series</a><i class="fa fa-angle-right"></i></li>');
});

it('show: breadcrumb\'s final segment wraps the title in <a href=""> with a leading space and an empty icon, matching item.php:107 + breadcrumb_items()', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Final Segment Title', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<li><a href=""> Final Segment Title</a><i class=""></i></li>');
});

it('show: renders the category-tree breadcrumb extension via the real categories() relationship, ancestors-first, matching item.php:123\'s breadcrumb($b,1,true,$Khotab->cat) + functions.php:475-506', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 240, 'title' => 'Parent Category', 'main_cat' => 0],
        ['id' => 513, 'title' => 'Leaf Category', 'main_cat' => 240],
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Categorized Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 513]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    $parentPos = strpos($content, 'category-240.htm');
    $leafPos = strpos($content, 'category-513.htm');

    expect($content)->toContain('<img src="/images/arrowbullet.png" alt="" />')
        ->and($content)->toContain('<a href="/category-240.htm">Parent Category</a>')
        ->and($content)->toContain('<a href="/category-513.htm">Leaf Category</a>')
        ->and($parentPos)->not->toBeFalse()
        ->and($leafPos)->not->toBeFalse()
        ->and($parentPos)->toBeLessThan($leafPos);
});

it('show: renders no category-tree extension when the item has no categories', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Uncategorized Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('arrowbullet');
});

it('show: renders the redesigned details card and metadata grid', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('class="w2a-item-details-card"')
        ->and($content)->toContain('class="w2a-item-header-title">Item</h2>')
        ->and(substr_count($content, 'class="w2a-meta-pill"'))->toBeGreaterThanOrEqual(4);
});

it('show: every portlet caption on the page renders its w2a_open_div() icon (fa-video-camera/fa-clone/fa-comments/fa-child), matching functions.php:112', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'vedio' => 1, 'comment' => 'A mirror', 'link' => 'file.mp4',
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'A commenter', 'comment' => 'A comment', 'view' => 1, 'mytime' => 100,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->update(['comments' => 1]);

    $content = $this->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<div class="caption"><i class="fa fa-video-camera"></i> تفاصيل المادة</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-clone"></i> قائمة الجودات المختلفة للمادة</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-comments"></i> تعليقات الزوار على المادة</div>')
        // 4 sidebar portlets share this icon in legacy (item.php:439,453,461,470's
        // 'الملف الشخصي'/'اخترنا لك هذه المادة'/'الأكثر تحميلا'/'جديد المواد',
        // all icon=>'fa-child') — a real legacy copy-paste choice, not a typo.
        ->and(substr_count($content, '<div class="caption"><i class="fa fa-child"></i>'))->toBe(4);
});

it('show: renders the page-title/breadcrumb/portlet-icon fix set for the same live item-298784-shaped fixture used in the audit (multi-finding smoke test)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 22, 'name' => 'محمد إسماعيل المقدم', 'prename' => 'الشيخ']);
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 240, 'title' => 'فضائل الأعمال والترغيب فيها', 'main_cat' => 0],
        ['id' => 513, 'title' => 'الصلاة على النبي صلى الله عليه وسلم', 'main_cat' => 240],
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 298784, 'author' => 22, 'title' => 'صلوا عليه وسلموا تسليماً  ', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 298784, 'category_id' => 513]);

    $content = $this->get('/khotab-item-298784.htm')->assertOk()->getContent();

    expect($content)->toContain('<h3 class="page-title">صلوا عليه وسلموا تسليماً   - الشيخ محمد إسماعيل المقدم</h3>')
        ->and($content)->toContain('<li><a href="/khotab-video.htm">المرئيات</a><i class="fa fa-angle-right"></i></li>')
        ->and($content)->toContain('<a href="/category-240.htm">فضائل الأعمال والترغيب فيها</a>')
        ->and($content)->toContain('<a href="/category-513.htm">الصلاة على النبي صلى الله عليه وسلم</a>');
});

it('show: only renders comments with view=1, gated by the stored comments counter', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'comments' => 2,
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        ['id' => 1, 'khid' => 1, 'uname' => 'Visible User', 'comment' => 'approved comment', 'view' => 1, 'mytime' => 100],
        ['id' => 2, 'khid' => 1, 'uname' => 'Pending User', 'comment' => 'unapproved comment', 'view' => 0, 'mytime' => 100],
    ]);

    $response = $this->get('/khotab-item-1.htm');

    $response->assertOk()->assertSee('approved comment')->assertDontSee('unapproved comment');
});

it('show: does not query comments at all when the stored counter is 0, even if view=1 rows exist', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'comments' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'Someone', 'comment' => 'a stray approved comment', 'view' => 1, 'mytime' => 100,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk()->assertDontSee('a stray approved comment');
});

it('show: IF-019 fix — comment flags render from images/flags/, not flags/', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'comments' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'Visitor', 'code' => 'eg', 'comment' => 'hello', 'view' => 1, 'mytime' => 100,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk()->assertSee('images/flags/eg.png', false);
});

it('show: IF-020 fix — the PDF link resolves to a real route', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'pdf' => 1,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk()->assertSee('/khotab-item-pdf-1.htm', false);

    $this->get('/khotab-item-pdf-1.htm')->assertRedirect('/media/pdf/0/1.pdf');
});

it('download: increments downcount and streams the linked file', function () {
    $file = tempnam(sys_get_temp_dir(), 'khotab');
    file_put_contents($file, 'file-bytes');

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'Item', 'link' => $file, 'linksize' => 10, 'downcount' => 4,
    ]);

    $response = $this->get('/khotab-download-1.htm');

    $response->assertOk();
    expect(DB::connection('main')->table('nuke_islamic_khotab')->find(1)->downcount)->toBe(5);

    unlink($file);
});

it('downloadMirror: increments the mirror\'s own hits, not the item\'s downcount', function () {
    $file = tempnam(sys_get_temp_dir(), 'khotabmirror');
    file_put_contents($file, 'mirror-bytes');

    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item', 'downcount' => 0]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert(['id' => 1, 'khid' => 1, 'link' => $file, 'hits' => 7]);

    $this->get('/khotab-mirror-1-1.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_islamic_mirror')->find(1)->hits)->toBe(8);
    expect(DB::connection('main')->table('nuke_islamic_khotab')->find(1)->downcount)->toBe(0);

    unlink($file);
});

/**
 * G-01 regression coverage (Migration Gap Register) — streamFile()'s
 * is_file() pre-check previously rejected every http(s):// link before
 * fopen() was ever attempted, confirmed to affect 99.9% of real khotab
 * downloads/mirrors. Fixed to skip is_file() only for http(s) links.
 *
 * A real local HTTP server (PHP's built-in server, spawned for this test
 * only, torn down after) is used instead of an external URL — genuine
 * end-to-end proof that fopen() over http(s) actually streams real bytes
 * back to the client post-fix, without any dependency on an external
 * network resource or invented production URL. Pre-fix, is_file() would
 * have rejected this exact link with a 404 before fopen() ever ran.
 */
function startLocalHttpFixtureServer(string $body): array
{
    $dir = sys_get_temp_dir().'/khotab-g01-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/file.bin', $body);

    $port = random_int(20000, 60000);
    $process = proc_open(
        ['php', '-S', "127.0.0.1:{$port}", '-t', $dir],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    usleep(300000); // give the built-in server a moment to bind

    return [$process, $port, $dir];
}

function stopLocalHttpFixtureServer($process, string $dir): void
{
    proc_terminate($process);
    proc_close($process);
    @unlink($dir.'/file.bin');
    @rmdir($dir);
}

it('download: an http link is no longer rejected by is_file() — genuinely fetches and streams the remote bytes', function () {
    [$process, $port, $dir] = startLocalHttpFixtureServer('remote-http-bytes');

    try {
        DB::connection('main')->table('nuke_islamic_khotab')->insert([
            'id' => 1, 'title' => 'Item', 'link' => "http://127.0.0.1:{$port}/file.bin", 'linksize' => 18,
        ]);

        $response = $this->get('/khotab-download-1.htm');

        $response->assertOk();
        expect($response->streamedContent())->toBe('remote-http-bytes');
    } finally {
        stopLocalHttpFixtureServer($process, $dir);
    }
});

it('downloadMirror: an http link (https is the identical code path — see docblock) is no longer rejected by is_file()', function () {
    [$process, $port, $dir] = startLocalHttpFixtureServer('remote-mirror-bytes');

    try {
        DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);
        DB::connection('main')->table('nuke_islamic_mirror')->insert([
            'id' => 1, 'khid' => 1, 'link' => "http://127.0.0.1:{$port}/file.bin",
        ]);

        $response = $this->get('/khotab-mirror-1-1.htm');

        $response->assertOk();
        expect($response->streamedContent())->toBe('remote-mirror-bytes');
    } finally {
        stopLocalHttpFixtureServer($process, $dir);
    }
});

it('download: an empty link still 404s (legacy\'s own empty($_link) check, unaffected by this fix)', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item', 'link' => '']);

    $this->get('/khotab-download-1.htm')->assertNotFound();
});

it('download: a garbled/non-URL link still 404s via is_file() — unaffected by this fix', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item', 'link' => '..........']);

    $this->get('/khotab-download-1.htm')->assertNotFound();
});

it('download: a nonexistent local path still 404s via is_file() — unaffected by this fix', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'Item', 'link' => '/tmp/khotab-g01-test-nonexistent-file-'.uniqid().'.mp3',
    ]);

    $this->get('/khotab-download-1.htm')->assertNotFound();
});

it('storeComment: creates an unmoderated (view=0) comment and returns "1"', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/khotab-item-1/comments', [
        'user_nickname' => 'Test User',
        'user_comment' => 'Great lesson',
    ]);

    $response->assertOk()->assertSeeText('1');

    $comment = DB::connection('main')->table('nuke_islamic_comments')->where('khid', 1)->first();
    expect($comment->uname)->toBe('Test User');
    expect($comment->view)->toBe(0);
});

it('storeComment: post-Wave-4 fix — resolves the country code via GeoIpLookup instead of leaving it blank', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);
    DB::connection('main')->table('ips')->insert([
        'startip_num' => 0, 'endip_num' => 4294967295, 'code' => 'EG', 'country' => 'Egypt',
    ]);

    $this->post('/khotab-item-1/comments', ['user_nickname' => 'Test User', 'user_comment' => 'Great lesson'])
        ->assertOk();

    $comment = DB::connection('main')->table('nuke_islamic_comments')->where('khid', 1)->first();
    expect($comment->code)->toBe('eg');
});

it('storeComment: post-Wave-4 fix — dispatches CommentPosted', function () {
    Event::fake([CommentPosted::class]);

    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);

    $this->post('/khotab-item-1/comments', ['user_nickname' => 'Test User', 'user_comment' => 'Great lesson'])
        ->assertOk();

    Event::assertDispatched(CommentPosted::class);
});

it('storeComment: returns "2" when the nickname is missing', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);

    $this->post('/khotab-item-1/comments', ['user_comment' => 'Great lesson'])
        ->assertSeeText('2');
});

it('storeComment: returns "3" when the comment body is missing', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);

    $this->post('/khotab-item-1/comments', ['user_nickname' => 'Test User'])
        ->assertSeeText('3');
});
