<?php

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Fatwa Authors Batch 1 — `/fatawa-authors.htm`'s displayed count and its
 * directory membership, both derived from `nuke_fatwa_questions` instead of
 * the stale `nuke_islamic_authors.fatwa` column.
 *
 * Why the change: production (2026-09-24) proved the stored column is not a
 * count of anything displayable — author 17 stored `12` against 126 real
 * distinct questions, author 242 stored `289` against **zero** mapping rows —
 * and that nothing in legacy PHP or Laravel ever writes it. Measured effect
 * on production: 51 -> 44 visible authors (35 stale entries removed, 28
 * authors with real content made discoverable).
 *
 * `hidden = 0` (`khotab/authors.php:24`) is preserved exactly; production
 * R-1c confirmed 0 authors with `hidden <> 0` have fatwas, so the change
 * cannot reveal a hidden author — pinned by its own test below.
 */
function useInMemoryMainConnectionForFatwaAuthorsDirectory(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaAuthorsDirectory();
});

/** Insert one general question and one mapping row pointing at it. */
function seedFatwaMapping(int $mappingId, int $authorId, int $generalQuestionId, bool $createGeneralQuestion = true): void
{
    $db = DB::connection('main');

    if ($createGeneralQuestion && $db->table('nuke_fatwa_general_questions')->where('id', $generalQuestionId)->doesntExist()) {
        $db->table('nuke_fatwa_general_questions')->insert([
            'id' => $generalQuestionId,
            'question_text' => "General question {$generalQuestionId}",
            'topic_id' => '0',
        ]);
    }

    $db->table('nuke_fatwa_questions')->insert([
        'id' => $mappingId,
        'auther_id' => $authorId,
        'general_question_id' => "|{$generalQuestionId}|",
        'question_text' => "Answer {$mappingId}",
    ]);
}

it('directory: shows an author with mappings, and displays the derived count', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'With Mappings', 'prename' => 'Sh.', 'fatwa' => 0, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 100);
    seedFatwaMapping(2, 7, 101);
    seedFatwaMapping(3, 7, 102);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect($content)->toContain('With Mappings')->toContain('3 فتوى');
});

it('directory: duplicate mappings to the same general question count ONCE (author 17\'s real 132-rows/126-questions shape)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Dup Mappings', 'fatwa' => 0, 'hidden' => 0,
    ]);
    // 4 mapping rows, but only 2 distinct general questions.
    seedFatwaMapping(1, 7, 100);
    seedFatwaMapping(2, 7, 100);
    seedFatwaMapping(3, 7, 100);
    seedFatwaMapping(4, 7, 101);

    $counts = app(ContentListingService::class)->fatwaDistinctQuestionCountsByAuthor();

    expect($counts->get(7))->toBe(2);
});

it('directory: an author with stale fatwa > 0 but ZERO mappings is absent, and its stale number is never rendered (author 242\'s shape)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 242, 'name' => 'Stale Only', 'fatwa' => 289, 'hidden' => 0],
        ['id' => 7, 'name' => 'Genuine', 'fatwa' => 0, 'hidden' => 0],
    ]);
    seedFatwaMapping(1, 7, 100);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    // Assert on the card markup, not a bare number: the shared navigation
    // embeds public/w2a_autocomplete/authors.txt, a static real-author map
    // that coincidentally contains the id 289 — so `not->toContain('289')`
    // would fail for a reason unrelated to this feature.
    expect($content)
        ->toContain('Genuine')
        ->not->toContain('Stale Only')
        ->not->toContain('/khotab-fatwa-242.htm')
        ->not->toContain('289 فتوى');
});

it('directory: an author with real mappings but a stale fatwa of 0 IS shown (the 28-author class production was hiding)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Hidden By Stale Counter', 'fatwa' => 0, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 100);
    seedFatwaMapping(2, 7, 101);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect($content)->toContain('Hidden By Stale Counter');
});

it('directory: a negative stale fatwa value does not gate membership either', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Negative Counter', 'fatwa' => -5, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 100);

    expect($this->get('/fatawa-authors.htm')->assertOk()->getContent())
        ->toContain('Negative Counter');
});

it('directory: a hidden=1 author is excluded even with real mappings (hidden = 0 semantics preserved)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 7, 'name' => 'Visible Author', 'fatwa' => 0, 'hidden' => 0],
        ['id' => 8, 'name' => 'Hidden Author', 'fatwa' => 0, 'hidden' => 1],
    ]);
    seedFatwaMapping(1, 7, 100);
    seedFatwaMapping(2, 8, 101);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect($content)->toContain('Visible Author')->not->toContain('Hidden Author');
});

it('directory: membership is exactly the set of hidden=0 authors with at least one mapping', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Alpha Keep', 'fatwa' => 9, 'hidden' => 0],     // KEEP
        ['id' => 2, 'name' => 'Beta RemoveStale', 'fatwa' => 9, 'hidden' => 0], // REMOVE_STALE
        ['id' => 3, 'name' => 'Gamma AddMissing', 'fatwa' => 0, 'hidden' => 0], // ADD_MISSING
        ['id' => 4, 'name' => 'Delta AbsentBoth', 'fatwa' => 0, 'hidden' => 0], // ABSENT_BOTH
    ]);
    seedFatwaMapping(1, 1, 100);
    seedFatwaMapping(2, 3, 101);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('Alpha Keep')
        ->toContain('Gamma AddMissing')
        ->not->toContain('Beta RemoveStale')
        ->not->toContain('Delta AbsentBoth');
});

/**
 * ORPHAN BEHAVIOUR — a deliberately documented divergence, pinned so it
 * cannot change silently.
 *
 * The author page's listing drops an id with no matching
 * `nuke_fatwa_general_questions` row (its `whereIn` simply doesn't match).
 * The directory's SQL aggregate does NOT — expressing that resolution check
 * in SQL was measured at 32-53s on MariaDB 10.11 (the optimizer refuses an
 * index for a join on an expression), versus ~10ms for the aggregate, so it
 * is excluded from the directory path by design.
 *
 * On production this is currently invisible: R-1d (2026-09-24) measured
 * 10,491 distinct parsed ids and **0 orphans**. That is a property of the
 * DATA, not a guarantee of the query, and is expressly not encoded as one.
 *
 * If a future change makes the two agree on orphans, THIS test fails — which
 * is the intended signal: the divergence must be closed deliberately, with a
 * measurement showing the cost is acceptable, not by accident.
 */
it('directory: an orphan general_question_id is counted by the directory but NOT listed on the author page (known, measured divergence)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Has Orphan', 'fatwa' => 0, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 100);                        // resolves
    seedFatwaMapping(2, 7, 999, createGeneralQuestion: false); // orphan

    $counts = app(ContentListingService::class)->fatwaDistinctQuestionCountsByAuthor();
    $listing = app(ContentListingService::class)->fatwaGeneralQuestionsByAuthor(7, 1);

    expect($counts->get(7))->toBe(2)          // directory counts the orphan
        ->and($listing->total())->toBe(1);    // the page cannot display it
});

it('directory: an author whose ONLY mapping is an orphan still appears (same documented divergence)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Orphan Only', 'fatwa' => 0, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 999, createGeneralQuestion: false);

    expect($this->get('/fatawa-authors.htm')->assertOk()->getContent())
        ->toContain('Orphan Only');
});

/**
 * ANTI-DRIFT. With no orphans — production's actual state — the directory's
 * number and the author page's own paginator total must be identical. This is
 * the guard that stops the two sides of the feature diverging again.
 */
it('anti-drift: with no orphans, the directory count equals the author page paginator total', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Parity Author', 'fatwa' => 999, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 100);
    seedFatwaMapping(2, 7, 100); // duplicate — must not double-count on either side
    seedFatwaMapping(3, 7, 101);
    seedFatwaMapping(4, 7, 102);

    $service = app(ContentListingService::class);

    expect($service->fatwaDistinctQuestionCountsByAuthor()->get(7))
        ->toBe($service->fatwaGeneralQuestionsByAuthor(7, 1)->total())
        ->toBe(3);
});

it('directory: zero-mapping ids (0, empty, non-numeric) are excluded, matching the listing\'s own > 0 filter', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Junk Mappings', 'fatwa' => 0, 'hidden' => 0,
    ]);
    $db = DB::connection('main');
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 7, 'general_question_id' => '0', 'question_text' => 'a'],
        ['id' => 2, 'auther_id' => 7, 'general_question_id' => '', 'question_text' => 'b'],
        ['id' => 3, 'auther_id' => 7, 'general_question_id' => '||', 'question_text' => 'c'],
    ]);

    $service = app(ContentListingService::class);

    expect($service->fatwaDistinctQuestionCountsByAuthor()->get(7))->toBeNull()
        ->and($service->fatwaGeneralQuestionsByAuthor(7, 1)->total())->toBe(0);

    expect($this->get('/fatawa-authors.htm')->assertOk()->getContent())
        ->not->toContain('Junk Mappings');
});

it('directory: the stale fatwa column is neither read for the number nor written by the request', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Count Check', 'fatwa' => 4242, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 100);
    seedFatwaMapping(2, 7, 101);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    // The derived count (2) is shown as the card's count; the stale value is
    // never rendered as one. Asserted on the count markup rather than a bare
    // number (see the note in the author-242 test above).
    expect($content)
        ->toContain('Count Check')
        ->toContain('2 فتوى')
        ->not->toContain('4242 فتوى');

    // And the column is left exactly as it was.
    expect(DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->value('fatwa'))
        ->toBe(4242);
});

it('directory: still generates the real khotab-fatwa-{id}.htm link shape (unchanged by this batch)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Link Shape', 'fatwa' => 0, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 100);

    expect($this->get('/fatawa-authors.htm')->assertOk()->getContent())
        ->toContain('/khotab-fatwa-7.htm');
});

it('legacy URLs remain valid: /fatawa-authors.htm and both /auther-questions-* shapes', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 7, 'name' => 'Listed', 'fatwa' => 0, 'hidden' => 0],
        ['id' => 242, 'name' => 'Delisted', 'fatwa' => 289, 'hidden' => 0],
    ]);
    seedFatwaMapping(1, 7, 100);

    $this->get('/fatawa-authors.htm')->assertOk();
    $this->get('/auther-questions-7.htm')->assertOk();
    $this->get('/auther-questions-7-1.htm')->assertOk();

    // Removed from the directory, but the URL must still work — no 404, no redirect.
    $this->get('/auther-questions-242.htm')->assertOk();
    $this->get('/auther-questions-242-1.htm')->assertOk();
});

/**
 * REGRESSION PIN — `auther_id = 0` (pre-deployment review, 2026-09-24).
 *
 * `nuke_fatwa_questions` really does contain mapping rows with
 * `auther_id = 0` — 112 rows / 106 distinct ids in the real dataset
 * (production reported 109). The directory aggregate therefore legitimately
 * contains a group keyed `0`: it groups whatever `auther_id` values exist and
 * does NOT filter them.
 *
 * Nothing renders for that group, and the reason matters: it is the AUTHOR
 * LOOKUP that excludes it, not any filtering of the aggregate.
 * `KhotabAuthorController::fatwaIndex()` feeds the aggregate's keys into
 * `Author::where('hidden', 0)->whereIn('id', ...)`, and `nuke_islamic_authors`
 * has no row with `id = 0` (real data: `MIN(id) = 1`). A key with no matching
 * author row simply produces no model, so no card.
 *
 * This is why the production aggregate returned 45 groups while the directory
 * shows 44 authors — the same 1-group gap reproduced locally (44 -> 43).
 *
 * **No production `id = 0` special case exists and none is wanted.** This
 * test documents the emergent behaviour so that, if it ever changes (e.g.
 * someone inserts an author row with `id = 0`, or the lookup is replaced by a
 * join), the change is visible rather than silent.
 */
it('directory: an aggregate key of 0 renders no author, because the author lookup finds no id=0 row (not because the aggregate is filtered)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 7, 'name' => 'Real Author', 'prename' => 'Sh.', 'fatwa' => 0, 'hidden' => 0,
    ]);
    seedFatwaMapping(1, 7, 100);
    // A mapping attributed to no author at all — the real data's own shape.
    seedFatwaMapping(2, 0, 200);

    $counts = app(ContentListingService::class)->fatwaDistinctQuestionCountsByAuthor();

    // The aggregate is NOT filtered: the 0 group is present, and counted.
    expect($counts->has(0))->toBeTrue()
        ->and($counts->get(0))->toBe(1);

    // No nuke_islamic_authors row has id = 0 ...
    expect(DB::connection('main')->table('nuke_islamic_authors')->where('id', 0)->exists())->toBeFalse();

    // ... so exactly one author card renders, and it is the real author.
    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect(substr_count($content, 'w2a-preacher-card'))->toBe(1)
        ->and($content)->toContain('Real Author')
        ->and($content)->not->toContain('/khotab-fatwa-0.htm');
});

/**
 * REGRESSION PIN — empty directory (pre-deployment review, 2026-09-24).
 *
 * With no fatwa mappings at all, the aggregate returns no rows, so the author
 * lookup receives an empty id list (`whereIn('id', [])`, compiled to
 * `0 = 1`). The page must still be a normal 200 rendering the view's own
 * existing empty state — not an exception, not a 500, and not a blank
 * response. Two queries are issued either way.
 */
it('directory: with no fatwa mappings at all, the page is a 200 with the existing empty state and no author cards', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 7, 'name' => 'Stale Counter Only', 'fatwa' => 99, 'hidden' => 0],
        ['id' => 8, 'name' => 'Another Stale', 'fatwa' => 5, 'hidden' => 0],
    ]);

    $response = $this->get('/fatawa-authors.htm');

    $response->assertOk();

    $content = $response->getContent();

    expect(substr_count($content, 'w2a-preacher-card'))->toBe(0)
        ->and($content)->not->toContain('Stale Counter Only')
        ->and($content)->not->toContain('Another Stale')
        // The page chrome still renders — this is the view's empty state,
        // not a truncated or failed response.
        ->and($content)->toContain('قسم الفتاوى المرئية');
});
