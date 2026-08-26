<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * Final Migration Owner-Decision Closure (2026-08-23), decision 1:
 * `CHAT_ROOM_ADMIN = REMOVE` — the FlashChat live-room feature itself is
 * retired with no replacement (Business Confirmation #4), so the admin
 * tooling that only ever administered it (`ChatRoomAdminController`, the
 * `admin.chat.*` routes, `resources/views/admin/chat/*`) is removed too.
 * Proves the retired surface is actually gone, not just undocumented.
 *
 * `App\Domain\Content\Http\Controllers\ChatRoomLessonController`'s
 * recorded-lesson-browsing routes (`chat_room.htm`, `chat_author_{id}.htm`,
 * `chat_lesson_{id}.htm`, `lesson-download-{id}.htm`) are a completely
 * separate, unrelated, still-active capability — not covered by this file,
 * see `tests/Feature/Content/ChatRoomLessonControllerTest.php` instead.
 */
it('no admin.chat.* named route exists anymore', function () {
    expect(Route::has('admin.chat.index'))->toBeFalse()
        ->and(Route::has('admin.chat.edit'))->toBeFalse()
        ->and(Route::has('admin.chat.update'))->toBeFalse()
        ->and(Route::has('admin.chat.owner.destroy'))->toBeFalse()
        ->and(Route::has('admin.chat.speaker.destroy'))->toBeFalse();
});

it('GET /admincp/chat 404s for a real authenticated super-admin, not 200/403', function () {
    InMemoryConnection::setup('main', ['nuke_authors' => MainSchema::nukeAuthors()]);
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $this->get('/admincp/chat')->assertNotFound();
});

it('the retired ChatRoomAdminController/Room model classes no longer exist', function () {
    expect(class_exists(\App\Domain\Admin\Http\Controllers\ChatRoomAdminController::class))->toBeFalse()
        ->and(class_exists(\App\Domain\Admin\Models\Room::class))->toBeFalse();
});

it('the super-admin dashboard no longer lists a chat/voice-room module, but every other real module is still present', function () {
    InMemoryConnection::setup('main', ['nuke_authors' => MainSchema::nukeAuthors()]);
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->not->toContain('الغرف الصوتية')
        ->toContain(route('admin.survey.index'))
        ->toContain(route('admin.staff.index'));
});
