<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Roadmap task 4.11 (added post-Wave-4 — see
 * docs/reviews/gap-closure-action-plan.md item 4). Covers only the
 * lesson-browsing half of chat_room/ — the live-room half stays task 6.5.
 */
function useInMemoryMainConnectionForChatRoom(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_authors_location' => MainSchema::nukeIslamicAuthorsLocation(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_groups_location' => MainSchema::nukeIslamicGroupsLocation(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_series_location' => MainSchema::nukeIslamicSeriesLocation(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForChatRoom();
});

function seedChatRoomAuthorFixture(): void
{
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Sheikh', 'prename' => 'Dr', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 1, 'location_id' => 10, 'count' => 5]);
}

it('author: 404s when the author is not registered for this location', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Sheikh', 'prename' => 'Dr', 'hidden' => 0]);
    // No nuke_islamic_authors_location row for author 1.

    $this->get('/chat_author_1.htm')->assertNotFound();
});

it('author: renders groups, series, and top-level items scoped to this author + location', function () {
    seedChatRoomAuthorFixture();

    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 1, 'title' => 'Group One', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_groups_location')->insert(['group_id' => 1, 'location_id' => 10, 'count' => 3]);

    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'Series One', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_series_location')->insert(['series_id' => 1, 'location_id' => 10, 'count' => 2]);

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 0, 'group_id' => 0, 'location_id' => 10, 'title' => 'Top Level Lesson', 'hidden' => 0,
    ]);

    $response = $this->get('/chat_author_1.htm');

    $response->assertOk()
        ->assertSee('Group One')
        ->assertSee('Series One')
        ->assertSee('Top Level Lesson');

    expect($response->getContent())
        ->toContain('class="w2a-items-list-wrap"')
        ->toContain('class="w2a-item-card-row"');
});

it('author: a group/series/item from a DIFFERENT location is excluded', function () {
    seedChatRoomAuthorFixture();

    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 1, 'title' => 'Wrong Location Group', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_groups_location')->insert(['group_id' => 1, 'location_id' => 99, 'count' => 3]);

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 0, 'group_id' => 0, 'location_id' => 99, 'title' => 'Wrong Location Lesson', 'hidden' => 0,
    ]);

    $content = $this->get('/chat_author_1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('Wrong Location Group')->not->toContain('Wrong Location Lesson');
});

function seedChatRoomLessonFixture(): void
{
    seedChatRoomAuthorFixture();

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'location_id' => 10, 'title' => 'The Lesson', 'hidden' => 0, 'weight' => 10, 'hits' => 5, 'downcount' => 2, 'link' => 'https://example.com/lesson.mp3',
    ]);
}

it('show: renders lesson details, increments hits by 1, does not touch lastvisit', function () {
    seedChatRoomLessonFixture();

    $response = $this->get('/chat_lesson_1.htm');

    $response->assertOk()->assertSee('The Lesson');

    expect(DB::connection('main')->table('nuke_islamic_khotab')->where('id', 1)->value('hits'))->toBe(6);
});

it('show: 404s for a lesson outside this location or hidden', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'location_id' => 99, 'title' => 'Wrong Location', 'hidden' => 0,
    ]);

    $this->get('/chat_lesson_1.htm')->assertNotFound();
});

it('show: renders previous/next lesson navigation ordered by weight', function () {
    seedChatRoomAuthorFixture();

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'location_id' => 10, 'title' => 'Lesson A', 'hidden' => 0, 'weight' => 5],
        ['id' => 2, 'author' => 1, 'location_id' => 10, 'title' => 'Lesson B (current)', 'hidden' => 0, 'weight' => 10],
        ['id' => 3, 'author' => 1, 'location_id' => 10, 'title' => 'Lesson C', 'hidden' => 0, 'weight' => 15],
    ]);

    $content = $this->get('/chat_lesson_2.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('Lesson A')
        ->toContain('Lesson C')
        ->toContain('class="w2a-nav-prev-next"')
        ->toContain('المادة السابقة')
        ->toContain('المادة التالية');
});

it('show: mirrors are listed, linking to the already-built khotab-mirror route, not the dead lesson-mirror-download route', function () {
    seedChatRoomLessonFixture();

    DB::connection('main')->table('nuke_islamic_mirror')->insert(['id' => 1, 'khid' => 1, 'comment' => 'HD Quality']);

    $content = $this->get('/chat_lesson_1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('HD Quality')
        ->toContain('/khotab-mirror-1-1.htm');
});

it('show: related lessons match on title words, exclude self, and are scoped to this location', function () {
    seedChatRoomLessonFixture();

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 2, 'author' => 1, 'location_id' => 10, 'title' => 'The Lesson Continued', 'hidden' => 0],
        ['id' => 3, 'author' => 1, 'location_id' => 99, 'title' => 'The Lesson Elsewhere', 'hidden' => 0],
    ]);

    $content = $this->get('/chat_lesson_1.htm')->assertOk()->getContent();

    expect($content)->toContain('The Lesson Continued')->not->toContain('The Lesson Elsewhere');
});

it('download: increments downcount and redirects to the raw link, no location/hidden filter', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 0, 'location_id' => 99, 'title' => 'Any Lesson', 'hidden' => 1, 'downcount' => 3, 'link' => 'https://example.com/file.mp3',
    ]);

    $this->get('/lesson-download-1.htm')->assertRedirect('https://example.com/file.mp3');

    expect(DB::connection('main')->table('nuke_islamic_khotab')->where('id', 1)->value('downcount'))->toBe(4);
});

// ---- chat_room.htm: Owner-Approved Partial Reconstruction ----
// OWNER_DECISION: live FlashChat rooms + weekly live-lesson schedule are
// retired (decision-log #14, "FlashChat = NO, Zoom = NO, no replacement
// of any kind") and intentionally omitted, not rendered as empty/unavailable.
// Only recorded-lesson discovery (most active authors, most viewed/recent
// recorded lessons) is implemented.

it('index: returns 200 and renders the shared page chrome', function () {
    $response = $this->get('/chat_room.htm');

    $response->assertOk()
        ->assertSee('<h3 class="page-title">الغرف الصوتية - غرفة الهداية الدعوية</h3>', false)
        ->assertSee('<div class="page-bar">', false);
});

it('index: lists the most active authors ordered by lessons_count DESC, capped at 15, linking to chat_author_{id}.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Least Active', 'prename' => 'Sh.', 'hidden' => 0],
        ['id' => 2, 'name' => 'Most Active', 'prename' => 'Sh.', 'hidden' => 0],
    ]);
    $db->table('nuke_islamic_authors_location')->insert([
        ['author_id' => 1, 'location_id' => 10, 'count' => 2],
        ['author_id' => 2, 'location_id' => 10, 'count' => 9],
    ]);

    $content = $this->get('/chat_room.htm')->getContent();

    expect(strpos($content, 'Most Active'))->toBeLessThan(strpos($content, 'Least Active'));
    expect($content)->toContain('href="/chat_author_1.htm"')->toContain('href="/chat_author_2.htm"');
    expect($content)->toContain('9 درس')->toContain('2 درس');
});

it('index: caps the most-active-authors list at 15, matching legacy\'s LIMIT 15', function () {
    $db = DB::connection('main');
    for ($i = 1; $i <= 20; $i++) {
        $db->table('nuke_islamic_authors')->insert(['id' => $i, 'name' => "Author {$i}", 'prename' => 'Sh.', 'hidden' => 0]);
        $db->table('nuke_islamic_authors_location')->insert(['author_id' => $i, 'location_id' => 10, 'count' => $i]);
    }

    $content = $this->get('/chat_room.htm')->getContent();

    expect(substr_count($content, 'class="w2a-chat-author-card"'))->toBe(15);
});

it('index: excludes a hidden author and an author registered at a different location', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Hidden Author', 'prename' => 'Sh.', 'hidden' => 1],
        ['id' => 2, 'name' => 'Other Location Author', 'prename' => 'Sh.', 'hidden' => 0],
    ]);
    $db->table('nuke_islamic_authors_location')->insert([
        ['author_id' => 1, 'location_id' => 10, 'count' => 5],
        ['author_id' => 2, 'location_id' => 99, 'count' => 5],
    ]);

    $content = $this->get('/chat_room.htm')->getContent();

    expect($content)->not->toContain('Hidden Author')->not->toContain('Other Location Author');
});

it('index: shows the real empty-results message when no authors are registered at this location', function () {
    $content = $this->get('/chat_room.htm')->getContent();

    expect($content)->toContain('عفوا ، لا يوجد نتائج');
});

it('index: renders most-viewed and most-recent recorded lessons, ordered correctly, linking to chat_lesson_{id}.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'location_id' => 10, 'hidden' => 0, 'title' => 'Least Viewed', 'hits' => 1, 'vedio' => 0],
        ['id' => 2, 'author' => 1, 'location_id' => 10, 'hidden' => 0, 'title' => 'Most Viewed', 'hits' => 99, 'vedio' => 1],
    ]);

    $content = $this->get('/chat_room.htm')->getContent();

    expect($content)->toContain('أكثر دروس الغرفة مشاهدة')->toContain('أجدد تسجيلات الغرفة');
    expect($content)->toContain('href="/chat_lesson_1.htm"')->toContain('href="/chat_lesson_2.htm"');
    // Most-viewed section: highest hits first.
    $viewedStart = strpos($content, 'أكثر دروس الغرفة مشاهدة');
    $recentStart = strpos($content, 'أجدد تسجيلات الغرفة');
    $viewedSection = substr($content, $viewedStart, $recentStart - $viewedStart);
    expect(strpos($viewedSection, 'Most Viewed'))->toBeLessThan(strpos($viewedSection, 'Least Viewed'));
    expect($content)
        ->toContain('class="w2a-chat-authors-grid"')
        ->toContain('class="w2a-chat-sidebar-list"')
        ->toContain('fa-video-camera')
        ->toContain('fa-headphones');
});

it('index: omits the most-viewed/most-recent portlets entirely when no recorded lessons exist at this location, matching legacy\'s own $TotalList > 0 guard', function () {
    $content = $this->get('/chat_room.htm')->getContent();

    expect($content)->not->toContain('أكثر دروس الغرفة مشاهدة')->not->toContain('أجدد تسجيلات الغرفة');
});

it('index: intentionally omits the retired live-room and today\'s-lesson sections entirely — no listing, no empty-room message, no schedule, no notice', function () {
    $content = $this->get('/chat_room.htm')->getContent();

    expect($content)
        ->not->toContain('قائمة الغرف الحالية')
        ->not->toContain('لا يوجد غرف متاحة الأن')
        ->not->toContain('دروس اليوم للغرف الصوتية')
        ->not->toContain('لا يوجد دروس متاحة اليوم')
        ->not->toContain('غير متاحة')
        ->not->toContain('flashchat')
        ->not->toContain('123flashchat')
        ->not->toContain('zoom')
        ->not->toContain('Zoom')
        ->not->toContain('<iframe');
});

it('index: does not register the retired live-room routes', function () {
    $this->get('/chat_1.htm')->assertNotFound();
});
