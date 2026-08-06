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
