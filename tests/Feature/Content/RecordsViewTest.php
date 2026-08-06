<?php

use App\Domain\Content\Events\ContentViewed;
use App\Domain\Content\Models\Concerns\TracksViews;
use App\Domain\Content\Models\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Tests\Support\InMemoryConnection;

/**
 * Fixture models standing in for a real content-item table (khotab/anasheed/
 * w2acd item shape: hits + lastvisit) and a mirror table (hits only) — the
 * two shapes P-014's legacy call sites actually have. Real KhotabItem/Mirror
 * models don't exist until Wave 4 (task 4.1); building them early just to
 * test this trait would be exactly the premature scaffolding Wave 0's
 * review flagged as something to avoid.
 *
 * implements Viewable explicitly (decision #3, added alongside ContentViewed's
 * Viewable&Model type-hint) — TracksViews satisfies the contract structurally,
 * but PHP's own parameter-type enforcement (not just PHPStan) needs the
 * interface declared, the same way Channel now declares it.
 */
class FixtureContentItem extends Model implements Viewable
{
    use TracksViews;

    protected $connection = 'main';

    protected $table = 'fixture_content_items';

    public $timestamps = false;

    protected $guarded = [];
}

class FixtureMirror extends Model implements Viewable
{
    use TracksViews;

    protected $connection = 'main';

    protected $table = 'fixture_mirrors';

    public $timestamps = false;

    protected $guarded = [];

    public function tracksLastVisit(): bool
    {
        return false;
    }
}

/**
 * fixture_content_items/fixture_mirrors are deliberately NOT real legacy
 * table names — they stand in for "any model with a hits column," so they
 * stay defined inline here rather than moving into
 * Tests\Support\Fixtures\MainSchema (which holds only real, confirmed
 * legacy table definitions).
 */
function useInMemoryMainConnectionForRecordsView(): void
{
    InMemoryConnection::setup('main', [
        'fixture_content_items' => function ($table) {
            $table->increments('id');
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('lastvisit')->nullable();
        },
        'fixture_mirrors' => function ($table) {
            $table->increments('id');
            $table->unsignedInteger('hits')->default(0);
        },
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForRecordsView();
});

it('increments hits and sets lastvisit for a content-item-shaped model', function () {
    $item = FixtureContentItem::create(['hits' => 5, 'lastvisit' => null]);

    $beforeCall = time();
    $item->recordView();

    $fresh = FixtureContentItem::find($item->id);

    expect($fresh->hits)->toBe(6)
        ->and($fresh->lastvisit)->toBeGreaterThanOrEqual($beforeCall);
});

it('increments hits only (no lastvisit column touched) for a mirror-shaped model', function () {
    $mirror = FixtureMirror::create(['hits' => 10]);

    $mirror->recordView();

    expect(FixtureMirror::find($mirror->id)->hits)->toBe(11);
});

it('increments atomically under concurrent-style repeated dispatch without lost updates', function () {
    $item = FixtureContentItem::create(['hits' => 0]);

    for ($i = 0; $i < 5; $i++) {
        FixtureContentItem::find($item->id)->recordView();
    }

    expect(FixtureContentItem::find($item->id)->hits)->toBe(5);
});

it('dispatches ContentViewed with the correct viewable model', function () {
    Event::fake([ContentViewed::class]);

    $item = FixtureContentItem::create(['hits' => 0]);
    $item->recordView();

    Event::assertDispatched(ContentViewed::class, fn ($event) => $event->viewable->is($item));
});
