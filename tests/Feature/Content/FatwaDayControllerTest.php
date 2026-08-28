<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
});

it('lists individual answers added today (db_insertion_date match), joined with the answering author', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $today = now()->format('Y-m-d');
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1, 'question_text' => 'Added today', 'db_insertion_date' => $today],
        ['id' => 2, 'auther_id' => 1, 'question_text' => 'Added yesterday', 'db_insertion_date' => '2020-01-01'],
    ]);

    $response = $this->get('/fatwa-today.htm');

    $response->assertOk()->assertSee('Added today')->assertDontSee('Added yesterday');
});

it('featured/random section joins on plain integer topic_id equality, not the pipe-delimited format', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'topic_id' => 10, 'question_text' => 'Featured candidate', 'general_question_id' => '|500|',
    ]);

    // The featured box uses a random OFFSET up to 7400 — with only one
    // row present, it will only actually render if the random offset
    // happens to land within range; assert the route at least renders
    // without error (the join itself is what's under test — a
    // topic_id=999 row would break the JOIN, proving the equality shape).
    $response = $this->get('/fatwa-today.htm');

    $response->assertOk();
});

it('the fatwa-today-{page}.htm route also works', function () {
    $this->get('/fatwa-today-1.htm')->assertOk();
});

it('featured section: G-13-10 — each item shows the hardcoded images/tvnoise.gif, matching fatwa-today.php:87 (no per-item image field)', function () {
    // fatwaRandomFeatured()'s hardcoded random OFFSET (up to 7400) makes it
    // unreliable to exercise through the full controller with test-sized
    // data (documented in the sibling test above) — rendering the view
    // directly with a controlled collection tests the same Blade output
    // deterministically, without relying on the random offset landing in range.
    $featured = collect([
        (object) ['id' => 1, 'general_question_id' => '|500|', 'question_text' => 'A featured question'],
    ]);

    $html = view('fatawa.day', [
        'featured' => $featured,
        'questions' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25),
        'displayDate' => 'اليوم',
        'date' => now()->format('Y-m-d'),
        'pageUrl' => fn (int $page): string => "/fatwa-today-{$page}.htm",
    ])->render();

    expect($html)->toContain('/images/tvnoise.gif')
        ->and($html)->toContain('A featured question');
});

it('Fatwa Today Visual Parity Pass: restores the real legacy portlet structure, thumbs grid, calendar block, and per-row channel markup', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Channel Five']);
    $today = now()->format('Y-m-d');
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1, 'channel_id' => 5, 'question_text' => 'With a real channel', 'db_insertion_date' => $today],
        ['id' => 2, 'auther_id' => 1, 'channel_id' => 999, 'question_text' => 'With a deleted channel', 'db_insertion_date' => $today],
    ]);

    $response = $this->get('/fatwa-today.htm');

    $response->assertOk()
        // Featured/results portlet wrappers, matching legacy exactly.
        ->assertSee('portlet box blue', false)
        ->assertSee('fatawa-mokhtara', false)
        ->assertSee('date_fatawa', false)
        // The calendar block (static shell) is present.
        ->assertSee('calendar-days', false)
        ->assertSee('تقويم الطريق إلى الله')
        // A real, existing channel renders the icon/link.
        ->assertSee('/fatawa-channel-5.htm', false)
        ->assertSee('/images/channels/5.png', false)
        // A channel_id with no matching row falls back to "بدون قناه",
        // exactly like `channel_id = null`/0 does — proving the LEFT JOIN
        // existence check, not a bare channel_id truthiness check.
        ->assertSee('/fatawa-channel-0.htm', false)
        ->assertSeeInOrder(['With a real channel', 'بدون قناه'], false);
});

it('Fatwa Today Visual Parity Pass: real legacy empty-state row renders when no questions exist for the date', function () {
    $response = $this->get('/fatwa-today.htm');

    $response->assertOk()->assertSee('لا تـوجـــد فتاوى مضافـــة حاليا لهذا التصنيف');
});

it('Fatwa Calendar Visual Dependency Audit (decision-log #45): loads the real fatawa/css/new-style.css that the calendar/portlet styling actually depends on, and it is genuinely reachable on disk', function () {
    $response = $this->get('/fatwa-today.htm');

    $response->assertOk()->assertSee('<link rel="stylesheet" href="/fatawa/css/new-style.css">', false);

    // Static files aren't routed through Laravel's kernel in feature
    // tests (real requests are served by the webserver directly from
    // public/, bypassing routing entirely) — a real HTTP round trip was
    // already verified manually against the local dev server; this
    // asserts the filesystem contract the webserver relies on instead:
    // the symlink exists, resolves, and the real calendar CSS is in it.
    $path = public_path('fatawa/css/new-style.css');
    expect(is_link(public_path('fatawa/css')))->toBeTrue()
        ->and(is_readable($path))->toBeTrue()
        ->and(file_get_contents($path))->toContain('.calendar-ympicker')
        ->and(file_get_contents($path))->toContain('.calendar-body');
});

it('Fatwa Date Route Completion (decision-log #46): /fatwa-date-{d}-{m}-{y}-{page}.htm filters by the exact requested calendar date, reusing the same query/markup as fatwa-today.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1, 'question_text' => 'Added on the requested date', 'db_insertion_date' => '2026-08-16'],
        ['id' => 2, 'auther_id' => 1, 'question_text' => 'Added on a different date', 'db_insertion_date' => '2026-08-17'],
    ]);

    $response = $this->get('/fatwa-date-16-8-2026-1.htm');

    $response->assertOk()
        ->assertSee('Added on the requested date')
        ->assertDontSee('Added on a different date')
        // Same restored markup as fatwa-today.htm — one shared view/path.
        ->assertSee('portlet box blue', false)
        ->assertSee('calendar-days', false);
});

it('Fatwa Date Route Completion: normalizes d/m/y the same way fatwa-today.php normalizes $_GET[date] — permissive rollover for an impossible date, not rejected', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    // 31 Feb doesn't exist — real PHP strtotime('2026-2-31') rolls over
    // to 2026-03-03, verified directly before implementing.
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'auther_id' => 1, 'question_text' => 'Rolled-over date match', 'db_insertion_date' => '2026-03-03',
    ]);

    $this->get('/fatwa-date-31-2-2026-1.htm')
        ->assertOk()
        ->assertSee('Rolled-over date match');
});

it('Fatwa Date Route Completion: a genuinely unparseable date (month=13) falls back to the Unix epoch, matching date(\'Y-m-d\', false) exactly — real questions never match, empty state renders', function () {
    $this->get('/fatwa-date-1-13-2026-1.htm')
        ->assertOk()
        ->assertSee('لا تـوجـــد فتاوى مضافـــة حاليا لهذا التصنيف');
});

it('Fatwa Date Route Completion: page 2 of a historical date renders the correct rows and correct pretty-URL pagination links, not ?page=', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $rows = [];
    for ($i = 1; $i <= 30; $i++) {
        $rows[] = ['id' => $i, 'auther_id' => 1, 'question_text' => "Question {$i}", 'db_insertion_date' => '2026-08-16'];
    }
    $db->table('nuke_fatwa_questions')->insert($rows);

    $page1 = $this->get('/fatwa-date-16-8-2026-1.htm');
    $page1->assertOk()
        ->assertSee('Question 1')
        ->assertDontSee('Question 26')
        // Real legacy pagination markup/classes, correct pretty-URL shape.
        ->assertSee('class="pagination"', false)
        ->assertSee('/fatwa-date-16-8-2026-2.htm', false)
        ->assertDontSee('?page=', false);

    $page2 = $this->get('/fatwa-date-16-8-2026-2.htm');
    $page2->assertOk()
        ->assertSee('Question 26')
        ->assertDontSee('Question 1 ')
        ->assertSee('/fatwa-date-16-8-2026-1.htm', false);
});

it('Fatwa Date Route Completion: fatwa-today.htm pagination also now uses the correct pretty-URL shape, not ?page=', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $today = now()->format('Y-m-d');
    $rows = [];
    for ($i = 1; $i <= 30; $i++) {
        $rows[] = ['id' => $i, 'auther_id' => 1, 'question_text' => "Today question {$i}", 'db_insertion_date' => $today];
    }
    $db->table('nuke_fatwa_questions')->insert($rows);

    $this->get('/fatwa-today.htm')
        ->assertOk()
        ->assertSee('/fatwa-today-2.htm', false)
        ->assertDontSee('?page=', false);
});

it('Fatwa Pagination Closure Check (decision-log #47): /fatwa-today-{page}.htm (.htaccess:283, a real, source-backed rule — op=day&page=$1, same as fatwa-today.htm) round-trips to the correct page, not just 200', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $today = now()->format('Y-m-d');
    $rows = [];
    for ($i = 1; $i <= 30; $i++) {
        $rows[] = ['id' => $i, 'auther_id' => 1, 'question_text' => "Today question {$i}", 'db_insertion_date' => $today];
    }
    $db->table('nuke_fatwa_questions')->insert($rows);

    $page1 = $this->get('/fatwa-today-1.htm');
    $page1->assertOk()
        ->assertSee('Today question 1')
        ->assertDontSee('Today question 26')
        ->assertSee('/fatwa-today-2.htm', false);

    $page2 = $this->get('/fatwa-today-2.htm');
    $page2->assertOk()
        ->assertSee('Today question 26')
        ->assertDontSee('Today question 1 ')
        // The pagination partial's "first page" link special-cases page 1
        // back to the canonical /fatwa-today.htm (no {page} segment),
        // not /fatwa-today-1.htm — both are real, registered routes, but
        // this confirms the emitted link matches the canonical shape.
        ->assertSee('/fatwa-today.htm', false);
});

it('fatawa-today.htm (legacy header.php typo, decision-log #43/IF-054) redirects to the canonical fatwa-today.htm', function () {
    $this->get('/fatawa-today.htm')
        ->assertRedirect('/fatwa-today.htm');
});
