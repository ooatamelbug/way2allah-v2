<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * G-07-03 (Phase 1 audit) — `fatawa-by-authers.htm`, previously entirely
 * unbuilt despite real, complete surviving source
 * (`fatawa/fatawa-by-authers.php`'s default branch).
 */
function useInMemoryMainConnectionForFatwaByAuthorsController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaByAuthorsController();
});

it('lists only authors who have at least one fatwa answer, via the INNER JOIN, ordered by name ASC', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Zaid', 'prename' => 'Dr.'],
        ['id' => 2, 'name' => 'Ahmed', 'prename' => 'Sheikh'],
        ['id' => 3, 'name' => 'No Fatwa Author', 'prename' => 'Dr.'],
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1],
        ['id' => 2, 'auther_id' => 2],
    ]);

    $content = $this->get('/fatawa-by-authers.htm')->assertOk()->getContent();

    expect($content)->toContain('Zaid')->toContain('Ahmed')->not->toContain('No Fatwa Author');
    // ORDER BY name ASC (plain, not BINARY) — Ahmed before Zaid.
    expect(strpos($content, 'Ahmed'))->toBeLessThan(strpos($content, 'Zaid'));
});

it('shows the correct per-author answer count (COUNT of matched fatwa_questions rows, via GROUP BY)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Prolific Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1],
        ['id' => 2, 'auther_id' => 1],
        ['id' => 3, 'auther_id' => 1],
    ]);

    $this->get('/fatawa-by-authers.htm')->assertOk()->assertSee('3 فتوى');
});

it('links each author to their auther-questions-{id}.htm page', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 42, 'name' => 'Linked Author', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 42]);

    $this->get('/fatawa-by-authers.htm')
        ->assertOk()
        ->assertSee('href="/auther-questions-42.htm"', false);
});

it('a hidden=1 author with a fatwa answer still appears — legacy\'s own default branch has no hidden=0 filter, reproduced exactly, not fixed', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Hidden But Has Fatwa', 'prename' => 'Dr.', 'hidden' => 1]);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 1]);

    $this->get('/fatawa-by-authers.htm')->assertOk()->assertSee('Hidden But Has Fatwa');
});

it('groups authors under their first Arabic letter, normalizing ه to هـ', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'هشام', 'prename' => 'Dr.'],
    ]);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 1]);

    $this->get('/fatawa-by-authers.htm')->assertOk()->assertSee('هـ');
});

it('the video/audio/pdf branches are not reachable via this route — no op parameter changes the result', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Fatwa Author', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 1]);

    // .htaccess:279's op=fatawa_by_authers never becomes 'video'/'audio'/
    // 'pdf' (the missing modules.php dispatcher is the only thing that
    // would ever pass those) — a query-string op is not consulted here,
    // matching legacy exactly.
    $content = $this->get('/fatawa-by-authers.htm?op=video')->assertOk()->getContent();

    expect($content)->toContain('Fatwa Author');
});

/**
 * ONLY_FULL_GROUP_BY REGRESSION — production error 1055, 2026-09-25.
 *
 * `fatwaAuthorsWithQuestions()` selected `nuke_islamic_authors.*` while
 * grouping by `nuke_islamic_authors.id` alone. MariaDB does not implement
 * MySQL 5.7.5+'s functional-dependency detection, so with
 * `ONLY_FULL_GROUP_BY` enabled it rejected the query outright —
 * `'w2acp_db2025-2.nuke_islamic_authors.name' isn't in GROUP BY` — and
 * `/fatawa-by-authers.htm` returned a 500.
 *
 * SQLite does NOT enforce that rule, so these tests CANNOT prove production
 * SQL compatibility. That was verified separately by running both query
 * shapes against a real MariaDB 10.11.19 with `ONLY_FULL_GROUP_BY` on and the
 * real 736-author / 13,364-mapping dataset: the old shape reproduced error
 * 1055 exactly, the new shape succeeded, and the two result sets were
 * byte-identical (43 rows, same ids, counts and order).
 *
 * What these tests DO pin, portably:
 *  - the generated SQL names every non-aggregated selected column in GROUP BY
 *    (the structural property ONLY_FULL_GROUP_BY requires), and no longer
 *    selects `*`;
 *  - the observable result — membership, per-author count and ordering — is
 *    unchanged.
 */
it('generated SQL is ONLY_FULL_GROUP_BY safe: no SELECT *, and every non-aggregated selected column appears in GROUP BY', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([['id' => 1, 'name' => 'A', 'prename' => 'Sh.']]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1, 'general_question_id' => '|10|', 'question_text' => 'q'],
    ]);

    $sql = null;
    DB::listen(function ($query) use (&$sql) {
        if (str_contains($query->sql, 'COUNT(nuke_fatwa_questions.id)')) {
            $sql = $query->sql;
        }
    });

    app(App\Domain\Content\Services\ContentListingService::class)->fatwaAuthorsWithQuestions();

    expect($sql)->not->toBeNull();

    // The regression itself: the old shape selected every author column.
    expect($sql)->not->toContain('"nuke_islamic_authors".*');

    // Each non-aggregated selected column must also be grouped.
    [, $groupBy] = explode('group by', $sql, 2);

    foreach (['id', 'name', 'prename'] as $column) {
        expect($sql)->toContain('"nuke_islamic_authors"."' . $column . '"');
        expect($groupBy)->toContain('"nuke_islamic_authors"."' . $column . '"');
    }

    // The aggregate is the only thing selected that is not grouped.
    expect($groupBy)->not->toContain('COUNT(');
});

it('result parity after the ONLY_FULL_GROUP_BY fix: one row per author, legacy COUNT(mapping rows) semantics, name ASC', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Beta', 'prename' => 'Sh.'],
        ['id' => 2, 'name' => 'Alpha', 'prename' => 'Dr.'],
        ['id' => 3, 'name' => 'NoAnswers', 'prename' => 'Sh.'],
    ]);
    // Author 1: 3 mapping rows but only 2 DISTINCT general questions — this
    // page counts MAPPING ROWS (legacy `COUNT(nuke_fatwa_questions.id)`), so
    // it must report 3, unlike /fatawa-authors.htm's distinct-question count.
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1, 'general_question_id' => '|10|', 'question_text' => 'a'],
        ['id' => 2, 'auther_id' => 1, 'general_question_id' => '|10|', 'question_text' => 'b'],
        ['id' => 3, 'auther_id' => 1, 'general_question_id' => '|11|', 'question_text' => 'c'],
        ['id' => 4, 'auther_id' => 2, 'general_question_id' => '|12|', 'question_text' => 'd'],
    ]);

    $authors = app(App\Domain\Content\Services\ContentListingService::class)->fatwaAuthorsWithQuestions();

    expect($authors)->toHaveCount(2)
        ->and($authors->pluck('name')->all())->toBe(['Alpha', 'Beta'])
        ->and($authors->firstWhere('name', 'Beta')->count)->toBe(3)
        ->and($authors->firstWhere('name', 'Alpha')->count)->toBe(1);

    // The columns the view actually consumes survive the narrowed select.
    $beta = $authors->firstWhere('name', 'Beta');
    expect($beta->id)->toBe(1)
        ->and($beta->prename)->toBe('Sh.')
        ->and($beta->fallbackImageUrl())->toBeString();
});

it('the page still renders after the fix, with the author link, name and mapping-row count', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([['id' => 17, 'name' => 'Al Huwayni', 'prename' => 'Sh.']]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 17, 'general_question_id' => '|10|', 'question_text' => 'a'],
        ['id' => 2, 'auther_id' => 17, 'general_question_id' => '|10|', 'question_text' => 'b'],
    ]);

    $content = $this->get('/fatawa-by-authers.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('Al Huwayni')
        ->toContain('/auther-questions-17.htm')
        ->toContain('2 فتوى');
});
