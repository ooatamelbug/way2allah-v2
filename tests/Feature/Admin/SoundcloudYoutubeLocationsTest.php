<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Location;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/** Roadmap task 5.4. */
function useInMemoryMainConnectionForLocations(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'nuke_options' => MainSchema::nukeOptions(),
        'nuke_islamic_locations' => MainSchema::nukeIslamicLocations(),
    ]);
}

function actingAsAdminWithPermission(string $permission): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    useInMemoryMainConnectionForLocations();
});

it('soundcloud: updates the single option row', function () {
    actingAsAdminWithPermission('soundcloud.update_soundcloud');
    DB::connection('main')->table('nuke_options')->insert(['option_name' => 'soundcloud', 'option_value' => '0']);

    $this->post(route('admin.soundcloud.update'), ['soundcloud' => '123456'])->assertRedirect();

    expect(DB::connection('main')->table('nuke_options')->where('option_name', 'soundcloud')->value('option_value'))->toBe('123456');
});

// ---- Global Authenticated Design/CSS Parity (2026-08-23) ----

it('soundcloud edit page uses real Metronic form-control/btn classes, not bare elements', function () {
    actingAsAdminWithPermission('soundcloud.update_soundcloud');
    DB::connection('main')->table('nuke_options')->insert(['option_name' => 'soundcloud', 'option_value' => '0']);

    $content = $this->get(route('admin.soundcloud.edit'))->assertOk()->getContent();

    expect($content)->toContain('name="soundcloud" value="0" class="form-control"')
        ->toContain('class="btn green"');
});

it('youtube edit page uses real Metronic form-control/btn classes, not bare elements', function () {
    actingAsAdminWithPermission('youtube.update_youtube');
    DB::connection('main')->table('nuke_options')->insert(['option_name' => 'youtube', 'option_value' => serialize([])]);

    $content = $this->get(route('admin.youtube.edit'))->assertOk()->getContent();

    expect($content)->toContain('name="youtube" class="form-control"')
        ->toContain('class="btn green"');
});

it('AdminCP Final 12-Route Browser Visual Evidence (2026-08-23): each video renders as a real YouTube iframe embed, matching index.php:138, not the raw video id as text', function () {
    actingAsAdminWithPermission('youtube.update_youtube');
    DB::connection('main')->table('nuke_options')->insert(['option_name' => 'youtube', 'option_value' => serialize(['abc123'])]);

    $content = $this->get(route('admin.youtube.edit'))->assertOk()->getContent();

    expect($content)->toContain('src="https://www.youtube.com/embed/abc123?rel=0&amp;controls=0"')
        ->toContain('width="560" height="315"')
        ->not->toMatch('/<div class="portlet-body">\s*abc123/');
});

it('youtube: adds a video id parsed from the full URL, appended to the serialized array', function () {
    actingAsAdminWithPermission('youtube.update_youtube');
    DB::connection('main')->table('nuke_options')->insert(['option_name' => 'youtube', 'option_value' => serialize(['abc123'])]);

    $this->post(route('admin.youtube.store'), ['youtube' => 'https://www.youtube.com/watch?v=xyz789'])->assertRedirect();

    $stored = unserialize(DB::connection('main')->table('nuke_options')->where('option_name', 'youtube')->value('option_value'));
    expect($stored)->toBe(['abc123', 'xyz789']);
});

it('youtube: removes a video id by its array index', function () {
    actingAsAdminWithPermission('youtube.update_youtube');
    DB::connection('main')->table('nuke_options')->insert(['option_name' => 'youtube', 'option_value' => serialize(['abc123', 'xyz789'])]);

    $this->delete(route('admin.youtube.destroy', 0))->assertRedirect();

    $stored = unserialize(DB::connection('main')->table('nuke_options')->where('option_name', 'youtube')->value('option_value'));
    expect($stored)->toBe(['xyz789']);
});

it('locations: the rebuilt add-flow actually persists a Location — proving the fix, not the legacy no-op INSERT', function () {
    actingAsAdminWithPermission('locations.add_location');

    $this->post(route('admin.locations.store'), [
        'name' => 'Al-Azhar Mosque', 'address' => 'Cairo', 'country' => 'Egypt',
    ])->assertRedirect();

    expect(Location::where('title', 'Al-Azhar Mosque')->exists())->toBeTrue();
});

it('locations: delete is blocked while count > 0', function () {
    actingAsAdminWithPermission('locations.del_location');
    $location = Location::create(['title' => 'Referenced Place', 'count' => 3]);

    $this->delete(route('admin.locations.destroy', $location))->assertRedirect();

    expect(Location::find($location->id))->not->toBeNull();
});

it('locations: delete succeeds when count = 0', function () {
    actingAsAdminWithPermission('locations.del_location');
    $location = Location::create(['title' => 'Unreferenced Place', 'count' => 0]);

    $this->delete(route('admin.locations.destroy', $location))->assertRedirect();

    expect(Location::find($location->id))->toBeNull();
});

it('decision-log #10: an admin holding only locations.del_location can still reach the list and edit views', function () {
    actingAsAdminWithPermission('locations.del_location');
    $location = Location::create(['title' => 'Findable Place', 'count' => 0]);

    $this->get(route('admin.locations.index'))->assertOk()->assertSee('Findable Place');
    $this->get(route('admin.locations.edit', $location))->assertOk();
});

it('decision-log #10: a del_location-only admin still cannot create a location', function () {
    actingAsAdminWithPermission('locations.del_location');

    $this->get(route('admin.locations.create'))->assertForbidden();
    $this->post(route('admin.locations.store'), ['name' => 'X', 'address' => 'Y', 'country' => 'Z'])->assertForbidden();
});

// ---- Map (AdminCP Locations Map — Owner Decision Resolution, 2026-08-22) ----

it('the create page renders the map portlet with the required self-hosted Leaflet/OSM assets, no Google Maps or legacy API key', function () {
    actingAsAdminWithPermission('locations.add_location');

    $content = $this->get(route('admin.locations.create'))->assertOk()->getContent();

    expect($content)->toContain('الخريطة')
        ->toContain('map_canvas')
        ->toContain('leaflet.js')
        ->toContain('leaflet.css')
        ->toContain('tile.openstreetmap.org')
        ->toContain('id="loc_long"')
        ->toContain('id="loc_lat"')
        ->not->toContain('maps.google.com')
        ->not->toContain('maps.googleapis.com')
        ->not->toContain('AIzaSyBdkUtocaKQkVuQz09HNL1PSuzSbQyqGJ8')
        ->not->toContain('getaddress');
});

it('the edit page emits the location\'s existing coordinates into the map initialization data', function () {
    actingAsAdminWithPermission('locations.del_location');
    $location = Location::create(['title' => 'Existing Place', 'count' => 0, 'lat' => 30.123456, 'lng' => 31.654321]);

    $content = $this->get(route('admin.locations.edit', $location))->assertOk()->getContent();

    expect($content)->toContain('30.123456')
        ->toContain('31.654321')
        ->not->toContain('maps.google.com');
});

it('the create page has no existing coordinates to emit and initializes the map with a null marker position', function () {
    actingAsAdminWithPermission('locations.add_location');

    $content = $this->get(route('admin.locations.create'))->assertOk()->getContent();

    expect($content)->toContain('existingLat = null')->toContain('existingLng = null');
});

it('the map portlet does not affect create persistence — location is still saved for real', function () {
    actingAsAdminWithPermission('locations.add_location');

    $this->post(route('admin.locations.store'), [
        'name' => 'Al-Fateh Mosque', 'address' => 'Cairo', 'country' => 'Egypt',
        'loc_long' => '31.234', 'loc_lat' => '30.567',
    ])->assertRedirect();

    $location = Location::where('title', 'Al-Fateh Mosque')->first();
    expect($location)->not->toBeNull()
        ->and((float) $location->lng)->toBe(31.234)
        ->and((float) $location->lat)->toBe(30.567);
});

it('the map portlet does not affect update persistence — location coordinates are still saved for real', function () {
    actingAsAdminWithPermission('locations.add_location');
    $location = Location::create(['title' => 'Movable Place', 'count' => 0, 'lat' => 1, 'lng' => 1]);

    $this->put(route('admin.locations.update', $location), [
        'name' => 'Movable Place', 'address' => 'Cairo', 'country' => 'Egypt',
        'loc_long' => '31.9', 'loc_lat' => '30.1',
    ])->assertRedirect();

    $location->refresh();
    expect((float) $location->lng)->toBe(31.9)->and((float) $location->lat)->toBe(30.1);
});

it('authorization for the map-bearing pages is unchanged — a plain admin without permission is still blocked', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody', 'password' => 'x']);
    $this->actingAs($admin, 'admin');
    $location = Location::create(['title' => 'Guarded Place', 'count' => 0]);

    $this->get(route('admin.locations.create'))->assertForbidden();
    $this->get(route('admin.locations.edit', $location))->assertForbidden();
});
