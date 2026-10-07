<?php

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * C-1 behavior-preservation guards. The optimization is a join-ORDER hint
 * that must change no observable result, so these assert the observable
 * contract rather than the SQL (SQL text is guarded separately and
 * server-free by `tests/Unit/Content/SearchStraightJoinSqlTest.php`).
 *
 * Deliberately NOT asserted: the relative order of rows with EQUAL weight.
 * `ORDER BY tb1.weight DESC` is unchanged in SQL but is not a total order,
 * and the old filesort and the new `(vedio, weight, time)` index walk may
 * emit equal-weight rows differently. Adding a tie-breaker would alter the
 * SQL contract and is a separate decision, so these tests prove descending
 * weight without depending on any particular tie order.
 */
function useInMemoryMainForSearchStraightJoin(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainForSearchStraightJoin();
});

/** The POST `search` video caller's exact argument set, including the C-1 opt-in. */
function videoSearch(array $overrides = []): LengthAwarePaginator
{
    return app(ContentListingService::class)
        ->khotabAdvancedSearch(array_merge(['title' => 'Lesson'], $overrides), 'tb1.weight', true, true);
}

function seedAuthor(int $id = 1): void
{
    DB::connection('main')->table('nuke_islamic_authors')
        ->insert(['id' => $id, 'name' => 'Shaikh', 'prename' => 'Dr.']);
}

/** @param  array<int, array<string, mixed>>  $rows */
function seedKhotab(array $rows): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->insert($rows);
}

it('exact total is preserved and non-matching rows are excluded from it', function () {
    seedAuthor();

    $rows = [];
    for ($id = 1; $id <= 25; $id++) {
        $rows[] = ['id' => $id, 'title' => "Lesson {$id}", 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => $id];
    }
    // Three rows that must NOT be counted: wrong title, audio, hidden.
    $rows[] = ['id' => 90, 'title' => 'Unrelated', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1];
    $rows[] = ['id' => 91, 'title' => 'Lesson audio', 'author' => 1, 'vedio' => 0, 'hidden' => 0, 'weight' => 1];
    $rows[] = ['id' => 92, 'title' => 'Lesson hidden', 'author' => 1, 'vedio' => 1, 'hidden' => 1, 'weight' => 1];
    seedKhotab($rows);

    $paginator = videoSearch();

    expect($paginator->total())->toBe(25)
        ->and($paginator->count())->toBe(20);
});

it('a matching row whose author is NULL is excluded from both the rows and the total (INNER JOIN semantics)', function () {
    seedAuthor();
    seedKhotab([
        ['id' => 1, 'title' => 'Lesson with author', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 5],
        ['id' => 2, 'title' => 'Lesson with null author', 'author' => null, 'vedio' => 1, 'hidden' => 0, 'weight' => 9],
    ]);

    $paginator = videoSearch();

    expect($paginator->total())->toBe(1)
        ->and($paginator->getCollection()->pluck('id')->all())->toBe([1]);
});

it('a matching row whose author id does not exist is excluded from both the rows and the total', function () {
    seedAuthor();
    seedKhotab([
        ['id' => 1, 'title' => 'Lesson with author', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 5],
        ['id' => 2, 'title' => 'Lesson with dangling author', 'author' => 9999, 'vedio' => 1, 'hidden' => 0, 'weight' => 9],
    ]);

    $paginator = videoSearch();

    expect($paginator->total())->toBe(1)
        ->and($paginator->getCollection()->pluck('id')->all())->toBe([1]);
});

it('weight ordering is descending, without depending on the order of equal-weight rows', function () {
    seedAuthor();
    seedKhotab([
        ['id' => 1, 'title' => 'Lesson a', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 5],
        ['id' => 2, 'title' => 'Lesson b', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 9],
        ['id' => 3, 'title' => 'Lesson c', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 9],
        ['id' => 4, 'title' => 'Lesson d', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1],
        ['id' => 5, 'title' => 'Lesson e', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 5],
    ]);

    $weights = videoSearch()->getCollection()->pluck('weight')->all();

    expect($weights)->toBe([9, 9, 5, 5, 1]);
});

it('filters are preserved: channel existence validation, author, and date range all still narrow the results', function () {
    seedAuthor(1);
    seedAuthor(2);
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Real Channel']);
    seedKhotab([
        ['id' => 1, 'title' => 'Lesson real channel', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'channel_id' => 5, 'time' => 1000],
        ['id' => 2, 'title' => 'Lesson fake channel', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'channel_id' => 999, 'time' => 1000],
        ['id' => 3, 'title' => 'Lesson other author', 'author' => 2, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'channel_id' => 5, 'time' => 1000],
        ['id' => 4, 'title' => 'Lesson out of range', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'channel_id' => 5, 'time' => 50_000],
    ]);

    // Every seeded row shares weight 1, so these compare SETS, not order —
    // equal-weight tie order is deliberately not part of the contract.
    $ids = fn (array $filters) => videoSearch($filters)->pluck('id')->sort()->values()->all();

    // channel 5 matches rows 1, 3 and 4 (row 4 differs only by its date).
    expect($ids(['channel_id' => 5]))->toBe([1, 3, 4]);
    expect(videoSearch(['channel_id' => 999])->total())->toBe(0);
    expect($ids(['author_id' => 2]))->toBe([3]);
    expect($ids(['start' => 900, 'end' => 1100]))->toBe([1, 2, 3]);
});

it('perPage stays 20 and page resolution still works, with the last page holding the remainder', function () {
    seedAuthor();

    $rows = [];
    for ($id = 1; $id <= 25; $id++) {
        $rows[] = ['id' => $id, 'title' => "Lesson {$id}", 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 100 - $id];
    }
    seedKhotab($rows);

    $page1 = videoSearch();
    expect($page1->perPage())->toBe(20)
        ->and($page1->currentPage())->toBe(1)
        ->and($page1->count())->toBe(20)
        ->and($page1->firstItem())->toBe(1)
        ->and($page1->lastPage())->toBe(2);

    Paginator::currentPageResolver(fn () => 2);

    $page2 = videoSearch();
    expect($page2->currentPage())->toBe(2)
        ->and($page2->count())->toBe(5)
        ->and($page2->firstItem())->toBe(21)
        ->and($page2->total())->toBe(25);
});

it('returns a LengthAwarePaginator for both the opted-in POST caller and the default GET caller', function () {
    seedAuthor();
    seedKhotab([['id' => 1, 'title' => 'Lesson one', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1]]);

    $listing = app(ContentListingService::class);

    expect($listing->khotabAdvancedSearch(['title' => 'Lesson'], 'tb1.weight', true, true))
        ->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($listing->khotabAdvancedSearch(['title' => 'Lesson']))
        ->toBeInstanceOf(LengthAwarePaginator::class);
});

it('the POST search page still renders the exact total and carries the search filters into pagination', function () {
    seedAuthor();

    $rows = [];
    for ($id = 1; $id <= 25; $id++) {
        $rows[] = ['id' => $id, 'title' => "Lesson {$id}", 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 100 - $id];
    }
    seedKhotab($rows);

    $content = $this->post('/search.htm', ['kh_title' => 'Lesson', 'kh_dept' => 'video'])->assertOk()->getContent();

    // premium-pagination.blade.php's own "عرض 1–20 من 25" summary.
    expect($content)->toContain('<strong>25</strong>')
        ->toContain('name="kh_title" value="Lesson"')
        ->toContain('name="kh_dept" value="video"');
});

/**
 * Call-site guards. The SQL-level guards assert what the SERVICE emits for a
 * given argument set; these assert that each CONTROLLER passes the argument
 * set it is supposed to. Without them, reverting `SearchController`'s 4th
 * argument would silently disable the optimization with every other test
 * still green — the test suite runs on SQLite, where the hint is gated off
 * and therefore unobservable in the emitted SQL.
 */
function searchListingSpy(): ContentListingService
{
    return new class extends ContentListingService
    {
        /** @var array<int, array{string, bool, bool}> */
        public array $khotabAdvancedSearchCalls = [];

        public function khotabAdvancedSearch(array $filters, string $orderBy = 'tb1.time', bool $validateChannelExists = false, bool $straightJoinResults = false): LengthAwarePaginator
        {
            $this->khotabAdvancedSearchCalls[] = [$orderBy, $validateChannelExists, $straightJoinResults];

            return parent::khotabAdvancedSearch($filters, $orderBy, $validateChannelExists, $straightJoinResults);
        }
    };
}

it('the POST video search caller opts into the results-only STRAIGHT_JOIN hint', function () {
    seedAuthor();
    seedKhotab([['id' => 1, 'title' => 'Lesson one', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1]]);

    $spy = searchListingSpy();
    app()->instance(ContentListingService::class, $spy);

    $this->post('/search.htm', ['kh_title' => 'Lesson', 'kh_dept' => 'video'])->assertOk();

    expect($spy->khotabAdvancedSearchCalls)->toBe([['tb1.weight', true, true]]);
});

it('the GET khotab search caller does NOT opt in, keeping its query byte-identical', function () {
    seedAuthor();
    seedKhotab([['id' => 1, 'title' => 'Lesson one', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1]]);

    $spy = searchListingSpy();
    app()->instance(ContentListingService::class, $spy);

    $this->get('/khotab/search?title=Lesson')->assertOk();

    expect($spy->khotabAdvancedSearchCalls)->toBe([['tb1.time', false, false]]);
});

it('the GET khotab search endpoint is unaffected — it never opts in and still returns its own results', function () {
    seedAuthor();
    seedKhotab([['id' => 7, 'title' => 'Lesson unaffected', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1]]);

    $content = $this->get('/khotab/search?title=Lesson')->assertOk()->getContent();

    expect($content)->toContain('href="/khotab-item-7.htm"');
});
