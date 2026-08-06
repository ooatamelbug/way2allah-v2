<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Room;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * Roadmap task 5.5. IF-034: `chat/edit_room.php`'s form/delowner/delspeaker
 * links have no backend anywhere in the legacy source (confirmed by a
 * full read) — `admincp.md`'s "Editing is functional" claim for this file
 * does not hold. This task builds a real, working editor, not a port.
 */
function useInMemoryConnectionsForChatRoomAdmin(): void
{
    InMemoryConnection::setup('main', ['nuke_authors' => MainSchema::nukeAuthors()]);
    InMemoryConnection::setup('flashchat', ['room' => MainSchema::room()]);
    InMemoryConnection::setup('vbulletin', [
        'user' => VbulletinSchema::user(),
        'avatar' => VbulletinSchema::avatar(),
    ]);
}

function actingAsAdminWithChatPermission(string $permission): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    useInMemoryConnectionsForChatRoomAdmin();
});

it('lists rooms split by open/closed, matching chat/index.php', function () {
    actingAsAdminWithChatPermission('chat.listrooms');
    Room::create(['name' => 'Open Room', 'enable' => 1]);
    Room::create(['name' => 'Closed Room', 'enable' => 0]);

    $this->get(route('admin.chat.index'))->assertOk()->assertSee('Open Room')->assertSee('Closed Room');
});

it('IF-034 fix: updating a room actually persists, unlike the legacy form with no backend', function () {
    actingAsAdminWithChatPermission('chat.editroom');
    $room = Room::create(['name' => 'Old Name', 'enable' => 0]);

    $this->put(route('admin.chat.update', $room), ['name' => 'New Name', 'enable' => '1'])->assertRedirect();

    expect($room->fresh()->name)->toBe('New Name')->and($room->fresh()->enable)->toBe(1);
});

it('IF-034 fix: removing an owner actually persists, unlike the legacy dead link', function () {
    actingAsAdminWithChatPermission('chat.editroom');
    $room = Room::create(['name' => 'R', 'enable' => 1, 'owner' => 'alice,bob']);

    $this->delete(route('admin.chat.owner.destroy', [$room, 'alice']))->assertRedirect();

    expect($room->fresh()->ownerUsernames())->toBe(['bob']);
});

it('resolves owner/speaker display names via the vbulletin connection', function () {
    actingAsAdminWithChatPermission('chat.listrooms');
    \Illuminate\Support\Facades\DB::connection('vbulletin')->table('user')->insert([
        'userid' => 1, 'username' => 'alice', 'posts' => 42,
    ]);
    $room = Room::create(['name' => 'R', 'enable' => 1, 'owner' => 'alice']);

    $this->get(route('admin.chat.edit', $room))->assertOk()->assertSee('alice')->assertSee('42');
});

it('a plain admin without chat.editroom cannot update a room', function () {
    actingAsAdminWithChatPermission('chat.listrooms');
    $room = Room::create(['name' => 'R', 'enable' => 1]);

    $this->put(route('admin.chat.update', $room), ['name' => 'Hacked'])->assertForbidden();
});

it('decision-log #10: an admin holding only chat.editroom can still reach the list and edit views', function () {
    actingAsAdminWithChatPermission('chat.editroom');
    $room = Room::create(['name' => 'Editable Room', 'enable' => 1]);

    $this->get(route('admin.chat.index'))->assertOk()->assertSee('Editable Room');
    $this->get(route('admin.chat.edit', $room))->assertOk();
});
