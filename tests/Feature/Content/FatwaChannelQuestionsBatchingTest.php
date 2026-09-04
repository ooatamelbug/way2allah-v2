<?php

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Enhancement Batch E-03 (F-03) — `fatwaQuestionsForChannel()` used to
 * resolve each row's topic and author with its own query, measuring 57
 * queries for a full 25-row page. Both lookups are now batched.
 *
 * The important property these tests protect is *scaling*: the query
 * count must stay flat as rows are added. They assert an upper bound
 * rather than an exact number, so a harmless extra framework query can
 * never make them brittle.
 */
function useInMemoryMainConnectionForFatwaChannelBatching(): void
{
    InMemoryConnection::setup('main', [
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);
}

/** @return int queries issued on the `main` connection while running the callback */
function countFatwaChannelQueries(Closure $callback): int
{
    $count = 0;
    DB::connection('main')->listen(function () use (&$count) {
        $count++;
    });
    $callback();

    return $count;
}

/** Seeds $rows answers on channel 9, each its own general question, sharing one topic and one author. */
function seedChannelRows(int $rows, int $topicId = 5, int $authorId = 7): void
{
    $db = DB::connection('main');
    $db->table('nuke_fatwa_topics')->insertOrIgnore(['id' => $topicId, 'topic_name' => 'Topic '.$topicId, 'parent_id' => 0]);
    $db->table('nuke_islamic_authors')->insertOrIgnore(['id' => $authorId, 'name' => 'Author '.$authorId, 'prename' => 'Sheikh']);

    for ($i = 1; $i <= $rows; $i++) {
        $db->table('nuke_fatwa_general_questions')->insert([
            'id' => $i, 'question_text' => sprintf('Q%03d', $i), 'topic_id' => "|{$topicId}|",
        ]);
        $db->table('nuke_fatwa_questions')->insert([
            'id' => $i, 'channel_id' => 9, 'auther_id' => $authorId,
            'general_question_id' => "|{$i}|", 'question_text' => 'answer '.$i,
        ]);
    }
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaChannelBatching();
});

it('does not repeat the topic query when many rows share one topic', function () {
    seedChannelRows(20, topicId: 5);
    $listing = app(ContentListingService::class);

    $count = countFatwaChannelQueries(fn () => $listing->fatwaQuestionsForChannel(9, 1));

    // 20 rows sharing 1 topic: previously 20 topic queries, now 1.
    expect($count)->toBeLessThanOrEqual(8);
});

it('does not repeat the author query when many rows share one author', function () {
    seedChannelRows(20, authorId: 7);
    $listing = app(ContentListingService::class);

    $queries = [];
    DB::connection('main')->listen(function ($q) use (&$queries) {
        $queries[] = $q->sql;
    });
    $listing->fatwaQuestionsForChannel(9, 1);

    $authorQueries = array_filter($queries, fn ($sql) => str_contains($sql, 'nuke_islamic_authors'));
    expect(count($authorQueries))->toBe(1);
});

it('keeps the query count flat as the row count grows — the N+1 is gone', function () {
    $listing = app(ContentListingService::class);

    seedChannelRows(1);
    $oneRow = countFatwaChannelQueries(fn () => $listing->fatwaQuestionsForChannel(9, 1));

    useInMemoryMainConnectionForFatwaChannelBatching(); // fresh schema
    seedChannelRows(25);
    $fullPage = countFatwaChannelQueries(fn () => $listing->fatwaQuestionsForChannel(9, 1));

    // Before this batch a 25-row page cost ~50 more queries than a 1-row page.
    expect($fullPage)->toBe($oneRow)
        ->and($fullPage)->toBeLessThanOrEqual(8);
});

it('preserves a null topic when the referenced topic row does not exist', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'A', 'prename' => 'Sheikh']);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 1, 'question_text' => 'Q', 'topic_id' => '|999|']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 9, 'auther_id' => 7, 'general_question_id' => '|1|', 'question_text' => 'a']);

    $result = app(ContentListingService::class)->fatwaQuestionsForChannel(9, 1);

    expect($result->getCollection()->first()->topic)->toBeNull();
});

it('preserves a null topic when the topic id is zero or blank', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'A', 'prename' => 'Sheikh']);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 1, 'question_text' => 'Q', 'topic_id' => '|0|']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 9, 'auther_id' => 7, 'general_question_id' => '|1|', 'question_text' => 'a']);

    $result = app(ContentListingService::class)->fatwaQuestionsForChannel(9, 1);

    expect($result->getCollection()->first()->topic)->toBeNull();
});

it('preserves a null author when the referenced author does not exist', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 1, 'question_text' => 'Q', 'topic_id' => '|0|']);
    // auther_id 0 has no matching row — legacy's join skipped it, so must this.
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 9, 'auther_id' => 0, 'general_question_id' => '|1|', 'question_text' => 'a']);

    $result = app(ContentListingService::class)->fatwaQuestionsForChannel(9, 1);

    expect($result->getCollection()->first()->author)->toBeNull();
});

it('picks the author of the lowest-id matching answer row, skipping non-existent authors', function () {
    // The exhaustively-verified legacy winner rule (672/672 real pairs):
    // lowest nuke_fatwa_questions.id whose auther_id actually exists.
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 3, 'name' => 'Later Author', 'prename' => 'Sheikh'],
        ['id' => 8, 'name' => 'Higher Id Author', 'prename' => 'Sheikh'],
    ]);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 1, 'question_text' => 'Q', 'topic_id' => '|0|']);
    $db->table('nuke_fatwa_questions')->insert([
        // lowest row id, but author 0 does not exist → must be skipped
        ['id' => 10, 'channel_id' => 9, 'auther_id' => 0, 'general_question_id' => '|1|', 'question_text' => 'a'],
        // next lowest row id with a real author → this one must win
        ['id' => 20, 'channel_id' => 9, 'auther_id' => 8, 'general_question_id' => '|1|', 'question_text' => 'b'],
        // higher row id, lower author id → must NOT win
        ['id' => 30, 'channel_id' => 9, 'auther_id' => 3, 'general_question_id' => '|1|', 'question_text' => 'c'],
    ]);

    $author = app(ContentListingService::class)->fatwaQuestionsForChannel(9, 1)->getCollection()->first()->author;

    expect($author->id)->toBe(8)
        ->and($author->name)->toBe('Higher Id Author');
});

it('does not leak the grouping key onto the author row', function () {
    seedChannelRows(2);

    $author = app(ContentListingService::class)->fatwaQuestionsForChannel(9, 1)->getCollection()->first()->author;

    expect($author)->not->toHaveProperty('fatwa_general_question_id')
        ->and($author->id)->toBe(7);
});

it('scopes authors to the requested channel only', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 3, 'name' => 'Right Channel', 'prename' => 'Sheikh'],
        ['id' => 8, 'name' => 'Wrong Channel', 'prename' => 'Sheikh'],
    ]);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 1, 'question_text' => 'Q', 'topic_id' => '|0|']);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 5, 'channel_id' => 4, 'auther_id' => 8, 'general_question_id' => '|1|', 'question_text' => 'other channel'],
        ['id' => 9, 'channel_id' => 9, 'auther_id' => 3, 'general_question_id' => '|1|', 'question_text' => 'this channel'],
    ]);

    $author = app(ContentListingService::class)->fatwaQuestionsForChannel(9, 1)->getCollection()->first()->author;

    // Row id 5 is lower, but belongs to another channel — must be ignored.
    expect($author->id)->toBe(3);
});

it('preserves question ordering by question_text', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'A', 'prename' => 'S']);
    foreach ([[1, 'Charlie'], [2, 'Alpha'], [3, 'Bravo']] as [$id, $text]) {
        $db->table('nuke_fatwa_general_questions')->insert(['id' => $id, 'question_text' => $text, 'topic_id' => '|0|']);
        $db->table('nuke_fatwa_questions')->insert(['id' => $id, 'channel_id' => 9, 'auther_id' => 7, 'general_question_id' => "|{$id}|", 'question_text' => 'a']);
    }

    $texts = app(ContentListingService::class)->fatwaQuestionsForChannel(9, 1)
        ->getCollection()->pluck('question_text')->all();

    expect($texts)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('preserves pagination metadata and page slicing', function () {
    seedChannelRows(30);
    $listing = app(ContentListingService::class);

    $page1 = $listing->fatwaQuestionsForChannel(9, 1);
    $page2 = $listing->fatwaQuestionsForChannel(9, 2);

    expect($page1->total())->toBe(30)
        ->and($page1->perPage())->toBe(25)
        ->and($page1->currentPage())->toBe(1)
        ->and($page1->lastPage())->toBe(2)
        ->and($page2->currentPage())->toBe(2)
        ->and($page2->getCollection())->toHaveCount(5);
});

it('returns an empty collection, and issues no batched lookups, for a channel with no answers', function () {
    $listing = app(ContentListingService::class);

    $result = null;
    $count = countFatwaChannelQueries(function () use ($listing, &$result) {
        $result = $listing->fatwaQuestionsForChannel(9999, 1);
    });

    expect($result->getCollection())->toBeEmpty()
        ->and($result->total())->toBe(0)
        // no rows → the topic/author batches must be skipped entirely
        ->and($count)->toBeLessThanOrEqual(3);
});
