<?php

/*
 * AUTH-01, AdminCP side (2026-11-21) — the Khotab mirror pages (`/admincp/mirrors/khotab/{item}` and the mirror edit
 * page) show Edit / «نقل» / Delete only when the server would allow them: the permission AND the record's audio /
 * video section. Before, `canEdit` / `canDelete` were computed without the record, so a moderator of the other section
 * saw the buttons and got 403.
 *
 * Realistic limited administrators (ContextualAdminHarness: exactly the permissions passed — no role, no implied
 * section). Isolated in-memory data; no real record, counter or timestamp is touched.
 */

use App\Domain\Admin\Http\Controllers\KhotabMirrorController;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => '']);
    $this->videoItem = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مرئية', 'vedio' => 1, 'hidden' => 0, 'mirror' => 1]);
    $this->audioItem = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'صوتية', 'vedio' => 0, 'hidden' => 0, 'mirror' => 1]);
    $this->videoMirror = H::insert('nuke_islamic_mirror', ['khid' => $this->videoItem, 'link' => 'https://x/v.mp4', 'comment' => 'وصلة مرئية', 'vedio' => 1]);
    $this->audioMirror = H::insert('nuke_islamic_mirror', ['khid' => $this->audioItem, 'link' => 'https://x/a.mp3', 'comment' => 'وصلة صوتية', 'vedio' => 0]);
});

/** @param list<string> $permissions */
function kmpAdmin(array $permissions, int $uid): AdminUser
{
    $admin = H::admin($permissions, 'kmp-'.uniqid());
    $admin->forceFill(['uid' => $uid])->save();   // 0 = standalone, > 0 = forum-linked

    return $admin->fresh();
}

/** What the mirror list page of $item offers $admin for $mirror: [edit, pick, delete]. */
function kmpControls($test, AdminUser $admin, int $item, int $mirror): array
{
    $html = $test->withSession(H::session($admin))->get(route('admin.mirrors.khotab.show', $item))->assertOk()->getContent();

    return [
        str_contains($html, 'href="'.route('admin.mirrors.khotab.edit', $mirror).'"'),
        str_contains($html, 'action="'.route('admin.mirrors.khotab.pick', $mirror).'"'),
        str_contains($html, 'action="'.route('admin.mirrors.khotab.destroy', $mirror).'"'),
    ];
}

it('shows a mirror\'s Edit / pick / Delete exactly when the section scope allows them, and the server agrees', function (array $sections, bool $super, int $uid) {
    $admin = $super ? H::superAdmin() : kmpAdmin(array_merge(['khotab.editmirror', 'khotab.delmirror'], $sections), $uid);
    $may = ['video' => $super || in_array('khotab.media_video', $sections, true), 'audio' => $super || in_array('khotab.media_audio', $sections, true)];

    foreach (['video' => [$this->videoItem, $this->videoMirror], 'audio' => [$this->audioItem, $this->audioMirror]] as $section => [$item, $mirror]) {
        expect(kmpControls($this, $admin, $item, $mirror))->toBe([$may[$section], $may[$section], $may[$section]], "{$section} page controls");

        $edit = $this->withSession(H::session($admin))->get(route('admin.mirrors.khotab.edit', $mirror))->getStatusCode();
        expect($edit === 403)->toBe(! $may[$section], "{$section} edit answered {$edit}");
    }
    // the deletes last (they write): refused exactly where the button was hidden
    foreach (['video' => $this->videoMirror, 'audio' => $this->audioMirror] as $section => $mirror) {
        $delete = $this->withSession(H::session($admin))->delete(route('admin.mirrors.khotab.destroy', $mirror))->getStatusCode();
        expect($delete === 403)->toBe(! $may[$section], "{$section} delete answered {$delete}")
            ->and(DB::connection('main')->table('nuke_islamic_mirror')->where('id', $mirror)->exists())->toBe(! $may[$section]);
    }
})->with([
    'audio-only, standalone' => [['khotab.media_audio'], false, 0],
    'audio-only, forum-linked' => [['khotab.media_audio'], false, 4242],
    'video-only, standalone' => [['khotab.media_video'], false, 0],
    'video-only, forum-linked' => [['khotab.media_video'], false, 4242],
    'both sections, standalone' => [['khotab.media_audio', 'khotab.media_video'], false, 0],
    'both sections, forum-linked' => [['khotab.media_audio', 'khotab.media_video'], false, 4242],
    'neither section' => [[], false, 0],
    'super-admin' => [[], true, 0],
]);

it('offers «نقل الوصلة هنا» only when the picked mirror\'s section allows the move; «إلغاء» stays available', function () {
    $audioOnly = kmpAdmin(['khotab.editmirror', 'khotab.media_audio'], 0);
    $both = kmpAdmin(['khotab.editmirror', ...H::BOTH_SECTIONS], 4242);
    $picked = [KhotabMirrorController::CLIPBOARD_KEY => $this->videoMirror];   // a VIDEO mirror on the clipboard
    $moveAction = 'action="'.route('admin.mirrors.khotab.move', $this->videoMirror).'"';
    $clearAction = 'action="'.route('admin.mirrors.khotab.clipboard.clear').'"';

    $html = $this->withSession(H::session($audioOnly) + $picked)->get(route('admin.mirrors.khotab.show', $this->audioItem))->assertOk()->getContent();
    expect($html)->not->toContain($moveAction)->toContain($clearAction);
    $this->withSession(H::session($audioOnly) + $picked)->post(route('admin.mirrors.khotab.move', $this->videoMirror), ['target_item_id' => $this->audioItem])->assertForbidden();

    $html = $this->withSession(H::session($both) + $picked)->get(route('admin.mirrors.khotab.show', $this->audioItem))->assertOk()->getContent();
    expect($html)->toContain($moveAction)->toContain($clearAction);
});

it('the edit page offers Delete only with the mirror\'s section and the delete permission', function () {
    $editor = kmpAdmin(['khotab.editmirror', 'khotab.delmirror', 'khotab.media_video'], 0);
    $editOnly = kmpAdmin(['khotab.editmirror', 'khotab.media_video'], 4242);
    // The update form posts to the same URL as the delete (only the method differs): the DELETE method field is the marker.
    $deletePortlet = 'name="_method" value="DELETE"';

    $this->withSession(H::session($editor))->get(route('admin.mirrors.khotab.edit', $this->videoMirror))->assertOk()->assertSee($deletePortlet, false);
    $this->withSession(H::session($editOnly))->get(route('admin.mirrors.khotab.edit', $this->videoMirror))->assertOk()->assertDontSee($deletePortlet, false);
    $this->withSession(H::session($editor))->get(route('admin.mirrors.khotab.edit', $this->audioMirror))->assertForbidden();
});

it('follows grants and revokes made in the permission editor', function (int $uid) {
    $boss = H::superAdmin('kmp-boss');
    $admin = kmpAdmin([], $uid);
    $editor = fn (array $permissions) => $this->withSession(H::session($boss))
        ->put(route('admin.permissions.update', $admin), ['permissions' => array_fill_keys($permissions, '1')])->assertRedirect();

    $editor(['khotab.editmirror', 'khotab.delmirror']);                          // keys without a section
    expect(kmpControls($this, $admin, $this->videoItem, $this->videoMirror))->toBe([false, false, false]);

    $editor(['khotab.editmirror', 'khotab.delmirror', 'khotab.media_video']);    // + video section
    expect(kmpControls($this, $admin, $this->videoItem, $this->videoMirror))->toBe([true, true, true])
        ->and(kmpControls($this, $admin, $this->audioItem, $this->audioMirror))->toBe([false, false, false]);
    expect($this->withSession(H::session($admin))->get(route('admin.mirrors.khotab.edit', $this->videoMirror))->getStatusCode())->not->toBeIn([401, 403]);

    $editor(['khotab.editmirror']);                                              // delete revoked: its button goes, the delete is refused
    expect(kmpControls($this, $admin, $this->videoItem, $this->videoMirror))->toBe([false, false, false]);
    $editor([]);                                                                 // everything revoked: the page itself is refused
    $this->withSession(H::session($admin))->get(route('admin.mirrors.khotab.show', $this->videoItem))->assertForbidden();
    $this->withSession(H::session($admin))->get(route('admin.mirrors.khotab.edit', $this->videoMirror))->assertForbidden();
    $this->withSession(H::session($admin))->delete(route('admin.mirrors.khotab.destroy', $this->videoMirror))->assertForbidden();
    expect(DB::connection('main')->table('nuke_islamic_mirror')->where('id', $this->videoMirror)->exists())->toBeTrue();
})->with(['standalone' => [0], 'forum-linked' => [4242]]);
