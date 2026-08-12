<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForSearchController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForSearchController();
});

// ---- Validation: legacy's exact rule, not KhotabSearchController's ----

it('validation: rejects a title under 4 characters even when a department and other filters are given (legacy exact rule)', function () {
    $response = $this->post('/search.htm', [
        'kh_title' => 'abc', 'kh_dept' => 'video', 'kh_channel' => '1',
    ]);

    $response->assertOk()->assertSee('يجب إدخال عنوان');
});

it('validation: rejects a request with no department even when the title is valid', function () {
    $response = $this->post('/search.htm', ['kh_title' => 'a valid title']);

    $response->assertOk()->assertSee('يجب إدخال عنوان');
});

it('validation: does NOT inherit KhotabSearchController\'s permissive rule (channel/author/date alone, no title, still rejected)', function () {
    $response = $this->post('/search.htm', [
        'kh_title' => '', 'kh_dept' => 'video', 'kh_channel' => '1',
    ]);

    $response->assertOk()->assertSee('يجب إدخال عنوان');
});

it('only POST is registered for /search.htm — a GET request does not match this route', function () {
    $this->get('/search.htm?kh_title=test&kh_dept=video')->assertMethodNotAllowed();
});

// ---- video department (reuses existing khotabAdvancedSearch, unchanged) ----

it('video department: reuses the existing khotabAdvancedSearch/khotabSeriesAdvancedSearch methods unchanged', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'A video title', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'time' => 100, 'weight' => 5,
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'video title', 'kh_dept' => 'video']);

    $response->assertOk()->assertSee('A video title');
});

// ---- audio department (new method — video/audio/dumped_files discrepancy) ----

it('audio department: uses the NEW khotabAudioAdvancedSearch method (vedio=0), not the video-only existing one', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'An audio title', 'author' => 1, 'vedio' => 0, 'hidden' => 0, 'time' => 100, 'weight' => 1],
        ['id' => 2, 'title' => 'A video title, must not appear', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'time' => 100, 'weight' => 1],
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'title', 'kh_dept' => 'audio']);

    $response->assertOk()->assertSee('An audio title')->assertDontSee('A video title, must not appear');
});

// ---- dumped_files department (vedio=1 AND pdf>0, mawad-only, no series) ----

it('dumped_files department: requires vedio=1 AND pdf>0, and has no series panel (legacy\'s own always-false id<0 trick)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'A dumped file title', 'author' => 1, 'vedio' => 1, 'pdf' => 1, 'hidden' => 0, 'time' => 100, 'weight' => 1],
        ['id' => 2, 'title' => 'A video without pdf, must not appear', 'author' => 1, 'vedio' => 1, 'pdf' => 0, 'hidden' => 0, 'time' => 100, 'weight' => 1],
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'title', 'kh_dept' => 'dumped_files']);

    $response->assertOk()
        ->assertSee('A dumped file title')
        ->assertDontSee('A video without pdf, must not appear')
        ->assertDontSee('السلاسل'); // no series section rendered for this department
});

// ---- The 5 "varieties" departments — confirmed parent_id discrimination ----

it('anasheed varieties departments: each is filtered by its own confirmed parent_id, not shared across departments', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Anasheed item', 'parent_id' => 98, 'vedio' => 1, 'hidden' => 0, 'weight' => 1],
        ['id' => 2, 'title' => 'Cartoon item', 'parent_id' => 57, 'vedio' => 1, 'hidden' => 0, 'weight' => 1],
        ['id' => 3, 'title' => 'Documentary item', 'parent_id' => 12, 'vedio' => 1, 'hidden' => 0, 'weight' => 1],
    ]);

    $anasheedResponse = $this->post('/search.htm', ['kh_title' => 'item', 'kh_dept' => 'anasheed']);
    $anasheedResponse->assertOk()->assertSee('Anasheed item')->assertDontSee('Cartoon item')->assertDontSee('Documentary item');

    $cartoonResponse = $this->post('/search.htm', ['kh_title' => 'item', 'kh_dept' => 'cartoon']);
    $cartoonResponse->assertOk()->assertSee('Cartoon item')->assertDontSee('Anasheed item')->assertDontSee('Documentary item');

    $documentaryResponse = $this->post('/search.htm', ['kh_title' => 'item', 'kh_dept' => 'documentary']);
    $documentaryResponse->assertOk()->assertSee('Documentary item')->assertDontSee('Anasheed item')->assertDontSee('Cartoon item');
});

it('anasheed departments: hidden filter applies to items (mawad) but NOT to groups (series) — confirmed asymmetric legacy behavior', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Hidden anasheed item, must not appear', 'parent_id' => 98, 'vedio' => 1, 'hidden' => 1, 'weight' => 1,
    ]);
    $db->table('nuke_anasheed_groups')->insert([
        'id' => 1, 'title' => 'A group with matching title', 'parent_id' => 98,
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'anasheed', 'kh_dept' => 'anasheed']);

    $response->assertOk()->assertDontSee('Hidden anasheed item, must not appear');

    $groupResponse = $this->post('/search.htm', ['kh_title' => 'matching', 'kh_dept' => 'anasheed']);
    $groupResponse->assertOk()->assertSee('A group with matching title');
});

// ---- fatawa department — three result sets, auther_id vs author_id ----

it('fatawa department: renders three result sets (questions/general-questions/topics), each using its own confirmed author column', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Answering Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'question_text' => 'A specific fatwa answer', 'auther_id' => 1, 'date_of_fatwa' => '2026-01-01',
    ]);
    $db->table('nuke_fatwa_general_questions')->insert([
        'id' => 10, 'question_text' => 'A shared fatwa question', 'author_id' => 1, 'num_view' => 5,
    ]);
    $db->table('nuke_fatwa_topics')->insert([
        'id' => 20, 'topic_name' => 'A fatwa topic name', 'author_id' => 1,
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'fatwa', 'kh_dept' => 'fatawa']);

    $response->assertOk()
        ->assertSee('A specific fatwa answer')
        ->assertSee('A shared fatwa question')
        ->assertSee('A fatwa topic name');
});

it('fatawa department: no hidden filter is applied at all (neither table has a hidden column)', function () {
    // No hidden column exists on either fixture table — this test simply
    // confirms the query does not error and returns the seeded row,
    // proving no hidden filter is silently applied.
    $db = DB::connection('main');
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'question_text' => 'Visible regardless', 'auther_id' => 0, 'date_of_fatwa' => '2026-01-01',
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'Visible regardless', 'kh_dept' => 'fatawa']);

    $response->assertOk()->assertSee('Visible regardless');
});

// ---- gallery/cds — confirmed dead, not offered ----

it('gallery/cds: not selectable, and a manually-crafted request for either returns zero results without erroring', function () {
    $galleryResponse = $this->post('/search.htm', ['kh_title' => 'anything valid', 'kh_dept' => 'gallery']);
    $galleryResponse->assertOk()->assertSee('يجب إدخال عنوان');

    $cdsResponse = $this->post('/search.htm', ['kh_title' => 'anything valid', 'kh_dept' => 'cds']);
    $cdsResponse->assertOk()->assertSee('يجب إدخال عنوان');
});

// ---- date range — confirmed whole-day-inclusive boundary ----

it('date range: the "to" boundary is inclusive of the entire day (legacy\'s confirmed DATE()-truncated semantic, not an exact-instant comparison)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'Late in the day title', 'author' => 1, 'vedio' => 1, 'hidden' => 0,
        'time' => strtotime('2026-01-15 23:30:00'), 'weight' => 1,
    ]);

    $response = $this->post('/search.htm', [
        'kh_title' => 'Late in the day', 'kh_dept' => 'video', 'kh_to' => '2026-01-15',
    ]);

    $response->assertOk()->assertSee('Late in the day title');
});
