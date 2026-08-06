<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\Room;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Replaces `admincp/chat/index.php` + `edit_room.php` — Roadmap task 5.5.
 * Not to be confused with `App\Domain\Content\Http\Controllers\
 * ChatRoomLessonController` (Content domain, task 4.11, the location-
 * scoped recorded-lesson browsing half of the *public* `chat_room/`
 * directory) — this is the unrelated *admin* directory (`admincp/chat/`)
 * managing FlashChat voice-room configuration itself.
 *
 * `index()` ports `chat/index.php` as-is (confirmed functional — a plain
 * SELECT + open/closed split). `edit()`'s form and the owner/speaker
 * delete links are confirmed to have **no backend implementation
 * anywhere in the legacy file** (IF-034, found during this task: a full
 * read of `edit_room.php` shows zero `$_POST` handling and zero
 * `op=delowner`/`op=delspeaker` handling — the legacy page looks like a
 * working editor but only ever renders one; `admincp.md`'s "Editing is
 * functional" classification for this file does not hold). `update()`/
 * `removeOwner()`/`removeSpeaker()` are real, working implementations
 * built fresh, per ADR-0010 — not a port, since there is nothing working
 * to port.
 *
 * `chat/automation_room.php` (confirmed orphaned, ~90% unmodified theme
 * boilerplate, `admincp.md` §5 Pattern G) is not ported at all.
 */
class ChatRoomAdminController
{
    public function index(): View
    {
        $rooms = Room::orderByDesc('enable')->orderBy('sequence')->get();

        return view('admin.chat.index', compact('rooms'));
    }

    public function edit(Room $room): View
    {
        $owners = $this->resolveVbulletinUsers($room->ownerUsernames());
        $speakers = $this->resolveVbulletinUsers($room->speakerUsernames());

        return view('admin.chat.edit', compact('room', 'owners', 'speakers'));
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $room->update([
            'name' => $request->string('name'),
            'enable' => $request->boolean('enable') ? 1 : 0,
            'welcome' => $request->string('welcome'),
            'password' => $request->string('password'),
            'max_user' => $request->input('max_user'),
            'des' => $request->string('des'),
            'member_only' => $request->boolean('member_only') ? 1 : 0,
            'enable_audio' => $request->boolean('enable_audio') ? 1 : 0,
            'enable_video' => $request->boolean('enable_video') ? 1 : 0,
            'enable_white_board' => $request->boolean('enable_white_board') ? 1 : 0,
        ]);

        return redirect()->route('admin.chat.edit', $room)->with('success', 'تم الحفظ بنجاح');
    }

    public function removeOwner(Room $room, string $username): RedirectResponse
    {
        $room->removeOwner($username);

        return redirect()->route('admin.chat.edit', $room)->with('success', 'تم الحذف بنجاح');
    }

    public function removeSpeaker(Room $room, string $username): RedirectResponse
    {
        $room->removeSpeaker($username);

        return redirect()->route('admin.chat.edit', $room)->with('success', 'تم الحذف بنجاح');
    }

    /** `edit_room.php:198-211`'s user+avatar lookup, one query per username in the legacy source — reproduced as one query per name here too (small, bounded lists; not the kind of N+1 worth batching for). */
    private function resolveVbulletinUsers(array $usernames): array
    {
        return array_map(
            fn (string $username) => DB::connection('vbulletin')->table('user')
                ->leftJoin('avatar', 'user.avatarid', '=', 'avatar.avatarid')
                ->where('user.username', $username)
                ->select(['user.userid', 'user.username', 'user.posts', 'avatar.avatarpath'])
                ->first(),
            $usernames,
        );
    }
}
