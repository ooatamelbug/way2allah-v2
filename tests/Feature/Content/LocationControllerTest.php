<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Wave C ("Public Locations & Da'wah Registration Surfaces"). Covers
 * `location-{id}.htm` / `alhedaya-room.htm` (LocationController) and the
 * "location param is ignored" quirk for `location-{id}-author-{id2}.htm`
 * (ChatRoomLessonController::author reused directly) and
 * `location-{id}-item-{id2}.htm` (KhotabItemController::show reused
 * directly).
 */
function useInMemoryMainConnectionForPublicLocations(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_locations' => MainSchema::nukeIslamicLocations(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_authors_location' => MainSchema::nukeIslamicAuthorsLocation(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_groups_location' => MainSchema::nukeIslamicGroupsLocation(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_series_location' => MainSchema::nukeIslamicSeriesLocation(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForPublicLocations();
});

it('show: does NOT 404 for a hidden location — chat_room/alhedaya_room.php:23 has no hidden filter on the location fetch at all, confirmed against real olddb data (location 10 is itself hidden=1, yet alhedaya-room.htm is a real, live legacy page)', function () {
    DB::connection('main')->table('nuke_islamic_locations')->insert(['id' => 1, 'title' => 'Masjid Hidden', 'hidden' => 1, 'type' => 1]);

    $this->get('/location-1.htm')->assertOk()->assertSee('Masjid Hidden');
});

it('show: 404s only for a genuinely nonexistent location id', function () {
    $this->get('/location-99999.htm')->assertNotFound();
});

it('show: renders a type=1 (physical) location with its address details and author list', function () {
    $position = serialize((object) [
        'formatted_address' => '123 Main St',
        'administrative_area_level_2' => 'City',
        'administrative_area_level_1' => 'Province',
        'country' => 'Country',
    ]);
    DB::connection('main')->table('nuke_islamic_locations')->insert([
        'id' => 1, 'title' => 'Masjid Test', 'hidden' => 0, 'type' => 1, 'website' => 'https://example.com', 'googlemap' => $position,
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 154, 'name' => 'Khudairi', 'prename' => 'Sheikh', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 154, 'location_id' => 1, 'count' => 7]);

    $response = $this->get('/location-1.htm');

    $response->assertOk()
        ->assertSee('Masjid Test')
        ->assertSee('123 Main St')
        ->assertSee('City')
        ->assertSee('Province')
        ->assertSee('https://example.com', false)
        ->assertSee('Khudairi')
        ->assertSee('7');
});

it('show: a type=2 (virtual) location does NOT render address/city/governorate/country lines', function () {
    DB::connection('main')->table('nuke_islamic_locations')->insert([
        'id' => 10, 'title' => 'الغرفة الدعوية', 'hidden' => 0, 'type' => 2,
    ]);

    $response = $this->get('/location-10.htm');

    $response->assertOk()->assertDontSee('العنوان:', false);
});

it('show: uses the computed media/locations/{bucket}/{id}.jpg path, NOT the legacy hardcoded archive.org override (Decision 1)', function () {
    DB::connection('main')->table('nuke_islamic_locations')->insert(['id' => 5000, 'title' => 'Far Location', 'hidden' => 0, 'type' => 1]);

    $response = $this->get('/location-5000.htm');

    $response->assertOk()
        ->assertDontSee('archive.org', false)
        ->assertSee('/media/locations/no_location_image.png', false);
});

it('alhedaya-room.htm renders location 10 specifically, without a location segment in the URL', function () {
    DB::connection('main')->table('nuke_islamic_locations')->insert(['id' => 10, 'title' => 'الغرفة الدعوية', 'hidden' => 0, 'type' => 2]);
    DB::connection('main')->table('nuke_islamic_locations')->insert(['id' => 1, 'title' => 'Other Location', 'hidden' => 0, 'type' => 1]);

    $response = $this->get('/alhedaya-room.htm');

    $response->assertOk()->assertSee('الغرفة الدعوية')->assertDontSee('Other Location');
});

it('location-{id}-author-{id2}.htm ignores the location segment entirely — reuses chat_author_{id}.htm behavior verbatim, matching verified chat_room/author.php (which never reads $_GET[location])', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 626, 'name' => 'Farhat', 'prename' => 'Dr', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 626, 'location_id' => 10, 'count' => 3]);

    // The URL claims "location 1," but the real legacy handler
    // (chat_room/author.php) is hardcoded to location 10 and never reads
    // the location segment — so this must behave identically to
    // /chat_author_626.htm, not scope to location 1.
    $this->get('/location-1-author-626.htm')->assertOk()->assertSee('Farhat');
    $this->get('/chat_author_626.htm')->assertOk()->assertSee('Farhat');
});

it('location-{id}-author-{id2}.htm 404s for an author only registered at a DIFFERENT location, even though the URL names that location — proof the location segment is truly ignored', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 154, 'name' => 'Khudairi', 'prename' => 'Sheikh', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 154, 'location_id' => 1, 'count' => 7]);
    // Author 154 is registered at location 1, NOT location 10.

    $this->get('/location-1-author-154.htm')->assertNotFound();
});

it('location-{id}-item-{id2}.htm produces the exact same response as khotab-item-{id}.htm — khotab/item.php never reads $_GET[location]', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 154, 'name' => 'Khudairi', 'prename' => 'Sheikh', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 107170, 'title' => 'Test Lesson', 'author' => 154, 'location_id' => 1, 'hidden' => 0, 'vedio' => 1,
    ]);

    $this->get('/location-1-item-107170.htm')->assertOk()->assertSee('Test Lesson');
    $this->get('/khotab-item-107170.htm')->assertOk()->assertSee('Test Lesson');
});
