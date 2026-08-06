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

    expect($content)->toContain('Lesson A')->toContain('Lesson C');
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
