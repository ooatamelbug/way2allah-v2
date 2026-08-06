<?php

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Fixture schema uses the same canonical MainSchema table definitions as
 * ChannelTest/ContentSidebarWidgetTest — not a locally-drifted copy (see
 * MainSchema's own docblock for the hazard this prevents).
 */
function useInMemoryMainConnectionForListing(): void
{
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'series_category_index' => MainSchema::seriesCategoryIndex(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForListing();
    $this->service = new ContentListingService;
});

// ---- groupsByAuthor (khotab) ----

it('groupsByAuthor: uses the LIVE COUNT(kh.id) aggregate, not the stored grp.count column, matching the duplicate-alias bug production has always served', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 5, 'title' => 'G1', 'count' => 999, 'vedio' => 1, 'hidden' => 0]);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'group_id' => 1, 'author' => 5],
        ['id' => 2, 'group_id' => 1, 'author' => 5],
    ]);

    $results = $this->service->groupsByAuthor(5, true);

    expect($results)->toHaveCount(1)
        ->and((int) $results->first()->count)->toBe(2) // live aggregate, not the stored 999
        ->and((int) $results->first()->stored_group_count)->toBe(999); // preserved, not lost
});

it('groupsByAuthor: excludes hidden groups by default, includes them when explicitly requested', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 5, 'title' => 'Hidden', 'count' => 1, 'vedio' => 1, 'hidden' => 1]);
    $db->table('nuke_islamic_khotab')->insert(['id' => 1, 'group_id' => 1, 'author' => 5]);

    expect($this->service->groupsByAuthor(5, true))->toHaveCount(0)
        ->and($this->service->groupsByAuthor(5, true, includeHidden: true))->toHaveCount(1);
});

// ---- groupsByCategory (categories) ----

it('groupsByCategory: matches the pipe-delimited cat membership pattern and trusts the stored count directly, never joining khotab', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_groups')->insert([
        ['id' => 1, 'cat' => '|3|7|', 'title' => 'In category 3', 'count' => 42, 'vedio' => 0, 'hidden' => 0],
        ['id' => 2, 'cat' => '|9|', 'title' => 'Not in category 3', 'count' => 5, 'vedio' => 0, 'hidden' => 0],
    ]);

    $results = $this->service->groupsByCategory(3, false);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe(1)
        ->and((int) $results->first()->count)->toBe(42); // stored value used as-is, no aggregate
});

it('groupsByCategory: has no hidden-override capability at all — hidden rows are always excluded', function () {
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'cat' => '|3|', 'title' => 'X', 'count' => 1, 'vedio' => 0, 'hidden' => 1]);

    expect($this->service->groupsByCategory(3, false))->toHaveCount(0);
});

// ---- seriesByAuthorAndGroup (khotab) vs seriesByCategoryAndGroup (categories) ----

it('seriesByAuthorAndGroup: filters by author+group and never joins authors (no name in the result)', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 5, 'group_id' => 0, 'title' => 'S1', 'count' => 3, 'vedio' => 0, 'hidden' => 0, 'lastupdate' => 100,
    ]);

    $results = $this->service->seriesByAuthorAndGroup(5, 0, false);

    expect($results)->toHaveCount(1)
        ->and(property_exists($results->first(), 'name'))->toBeFalse();
});

it('seriesByCategoryAndGroup: filters via the junction table and does join authors (name present)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh X', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 9, 'group_id' => 0, 'title' => 'S1', 'count' => 3, 'vedio' => 0, 'hidden' => 0, 'lastupdate' => 100]);
    $db->table('series_category_index')->insert(['series_id' => 1, 'category_id' => 4]);

    $results = $this->service->seriesByCategoryAndGroup(4, 0, false);

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Shaikh X');
});

// ---- khotabItemsFixedOrNew (khotab, mode fixed/new) ----

it('khotabItemsFixedOrNew: onlyFixed=true adds the fixed=1 filter that mode=new omits', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'vedio' => 0, 'fixed' => 1, 'weight' => 1, 'time' => 100],
        ['id' => 2, 'vedio' => 0, 'fixed' => 0, 'weight' => 1, 'time' => 200],
    ]);

    expect($this->service->khotabItemsFixedOrNew(0, 0, 0, false, onlyFixed: true))->toHaveCount(1)
        ->and($this->service->khotabItemsFixedOrNew(0, 0, 0, false, onlyFixed: false))->toHaveCount(2);
});

it('khotabItemsFixedOrNew: author/ser/group filters are conditional — omitted entirely when 0, unlike the default-mode method', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 1, 'time' => 100],
        ['id' => 2, 'author' => 7, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 1, 'time' => 100],
    ]);

    // authorId=0 means "no author filter at all" here — both rows returned.
    expect($this->service->khotabItemsFixedOrNew(0, 0, 0, false, onlyFixed: false))->toHaveCount(2);
});

it('khotabItemsFixedOrNew: orders by weight desc then time desc, and caps at 50 rows', function () {
    $db = DB::connection('main');
    for ($i = 1; $i <= 55; $i++) {
        $db->table('nuke_islamic_khotab')->insert(['id' => $i, 'vedio' => 0, 'weight' => 0, 'time' => $i]);
    }

    $results = $this->service->khotabItemsFixedOrNew(0, 0, 0, false, onlyFixed: false);

    expect($results)->toHaveCount(50)
        ->and($results->first()->time)->toBe(55); // highest time first (weight tied at 0)
});

// ---- khotabItemsForDay ----

it('khotabItemsForDay: only includes items within [dayStart, dayEnd)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'vedio' => 0, 'time' => 999, 'weight' => 0],  // before window
        ['id' => 2, 'vedio' => 0, 'time' => 1000, 'weight' => 0], // start of window (inclusive)
        ['id' => 3, 'vedio' => 0, 'time' => 1999, 'weight' => 0], // end of window (exclusive boundary - 1)
        ['id' => 4, 'vedio' => 0, 'time' => 2000, 'weight' => 0], // at/after end (exclusive)
    ]);

    $results = $this->service->khotabItemsForDay(false, 1000, 2000);

    expect($results->pluck('id')->sort()->values()->all())->toBe([2, 3]);
});

// ---- khotabItemsWithPdf ----

it('khotabItemsWithPdf: filters pdf > 0 and orders/selects using pdf_time as "time"', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'pdf' => 0, 'pdf_time' => 500, 'weight' => 0],
        ['id' => 2, 'pdf' => 1, 'pdf_time' => 500, 'weight' => 0],
    ]);

    $results = $this->service->khotabItemsWithPdf();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe(2)
        ->and((int) $results->first()->time)->toBe(500);
});

// ---- khotabItemsDefault ----

it('khotabItemsDefault: filters author/ser/group unconditionally, even when 0 — the confirmed divergence from khotabItemsFixedOrNew', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 1],
        ['id' => 2, 'author' => 7, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 1],
    ]);

    // authorId=0 here means "filter to rows where author is literally 0" — only row 1.
    $results = $this->service->khotabItemsDefault(0, 0, 0, false);

    expect($results)->toHaveCount(1)->and($results->first()->id)->toBe(1);
});

it('khotabItemsDefault: never joins authors — no name/prename available in the result', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 1]);

    $result = $this->service->khotabItemsDefault(0, 0, 0, false)->first();

    expect(property_exists($result, 'name'))->toBeFalse();
});

// ---- khotabItemsByCategory ----

it('khotabItemsByCategory: filters via the junction table, joins authors, and orders by time only (no weight)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Y', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 9, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 100, 'time' => 1],
        ['id' => 2, 'author' => 9, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 2],
    ]);
    $db->table('khotab_category_index')->insert([
        ['khotab_id' => 1, 'category_id' => 3],
        ['khotab_id' => 2, 'category_id' => 3],
    ]);

    $results = $this->service->khotabItemsByCategory(3, 0, 0, false);

    // id 2 has lower weight but a later time, and this method orders by time only —
    // if it were weight-ordered (like khotab's own methods) id 1 would come first.
    expect($results->pluck('id')->all())->toBe([2, 1])
        ->and($results->first()->name)->toBe('Shaikh Y');
});

it('khotabItemsByCategory: excludes rows outside the requested category via the junction table', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 1]);
    $db->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 999]);

    expect($this->service->khotabItemsByCategory(3, 0, 0, false))->toHaveCount(0);
});

// ---- Wave 3: groupsByChannel / seriesByChannel / khotabItemsByChannel (channels/functions.php) ----

it('groupsByChannel: filters by channel always, author only when positive, and joins authors', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Z']);
    $db->table('nuke_islamic_groups')->insert([
        ['id' => 1, 'channel_id' => 5, 'author_id' => 9, 'title' => 'G1', 'count' => 1, 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'channel_id' => 5, 'author_id' => 99, 'title' => 'G2', 'count' => 1, 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'channel_id' => 6, 'author_id' => 9, 'title' => 'G3', 'count' => 1, 'vedio' => 1, 'hidden' => 0],
    ]);

    $allInChannel = $this->service->groupsByChannel(5, 0, true);
    $byAuthorInChannel = $this->service->groupsByChannel(5, 9, true);

    expect($allInChannel)->toHaveCount(2)
        ->and($byAuthorInChannel)->toHaveCount(1)
        ->and($byAuthorInChannel->first()->author)->toBe('Shaikh Z');
});

it('groupsByChannel: has no hidden-override capability — hidden rows always excluded, matching legacy', function () {
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'channel_id' => 5, 'title' => 'X', 'count' => 1, 'vedio' => 1, 'hidden' => 1]);

    expect($this->service->groupsByChannel(5, 0, true))->toHaveCount(0);
});

it('seriesByChannel: filters by channel always, author only when positive, and joins authors', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Z']);
    $db->table('nuke_islamic_series')->insert([
        'id' => 1, 'channel_id' => 5, 'author_id' => 9, 'title' => 'S1', 'count' => 1, 'vedio' => 1, 'hidden' => 0, 'lastupdate' => 1,
    ]);

    $results = $this->service->seriesByChannel(5, 9, true);

    expect($results)->toHaveCount(1)->and($results->first()->author)->toBe('Shaikh Z');
});

it('khotabItemsByChannel: filters ser_id/group_id unconditionally (even at 0), the same IF-005 shape found in khotab itself', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 1, 'time' => 1],
        ['id' => 2, 'channel_id' => 5, 'author' => 0, 'ser_id' => 7, 'group_id' => 0, 'vedio' => 1, 'time' => 1],
    ]);

    // ser_id=0 here means "filter to rows where ser_id is literally 0" — only row 1.
    $results = $this->service->khotabItemsByChannel(5, 0, 0, 0, true);

    expect($results)->toHaveCount(1)->and($results->first()->id)->toBe(1);
});

it('khotabItemsByChannel: orders by time only, not weight (channels/functions.php has no weight ordering)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 1, 'weight' => 100, 'time' => 1],
        ['id' => 2, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 1, 'weight' => 0, 'time' => 2],
    ]);

    $results = $this->service->khotabItemsByChannel(5, 0, 0, 0, true);

    // id 2 has far lower weight but a later time — if weight were used, id 1 would lead.
    expect($results->pluck('id')->all())->toBe([2, 1]);
});
