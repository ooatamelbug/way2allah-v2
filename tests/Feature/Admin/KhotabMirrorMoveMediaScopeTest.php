<?php

/*
 * Khotab mirror move — both sections (owner decision 2026-11-21). Moving a mirror requires `khotab.editmirror` on the
 * mirror (its current item's audio / video section) AND on the destination item. Enforced in MoveKhotabMirrorAction
 * before anything is written; the AdminCP clipboard box and the public item-page control show the move exactly when
 * the server would allow it.
 *
 * Realistic limited administrators (exactly the permissions passed, no implied section). Isolated in-memory data.
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
    $item = fn (string $title, int $vedio) => H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => $title, 'vedio' => $vedio, 'hidden' => 0, 'mirror' => 1]);
    $this->items = ['audio' => [$item('صوتية 1', 0), $item('صوتية 2', 0)], 'video' => [$item('مرئية 1', 1), $item('مرئية 2', 1)]];
});

/** @param list<string> $permissions */
function kmmAdmin(array $permissions, int $uid): AdminUser
{
    $admin = H::admin($permissions, 'kmm-'.uniqid());
    $admin->forceFill(['uid' => $uid])->save();

    return $admin->fresh();
}

/** A fresh mirror on the first item of $section. */
function kmmMirror($test, string $section): int
{
    return H::insert('nuke_islamic_mirror', ['khid' => $test->items[$section][0], 'link' => 'https://x/'.uniqid().'.mp4', 'comment' => 'وصلة', 'vedio' => $section === 'video' ? 1 : 0]);
}

/** Everything a move can touch, to prove a refused move left it all as it was. */
function kmmState(int $mirror, int $from, int $to): array
{
    $main = DB::connection('main');

    return [
        'khid' => (int) $main->table('nuke_islamic_mirror')->where('id', $mirror)->value('khid'),
        'from_count' => (int) $main->table('nuke_islamic_khotab')->where('id', $from)->value('mirror'),
        'to_count' => (int) $main->table('nuke_islamic_khotab')->where('id', $to)->value('mirror'),
        'audit' => $main->table('admin_audit_log')->where('action', 'move_mirror')->where('entity_id', $mirror)->count(),
    ];
}

/** Does the destination's AdminCP mirror page / public item page offer the move of $mirror? */
function kmmOffered($test, AdminUser $admin, int $mirror, int $target): array
{
    $session = H::session($admin) + [KhotabMirrorController::CLIPBOARD_KEY => $mirror];
    $move = 'action="'.route('admin.mirrors.khotab.move', $mirror).'"';
    $admincp = $test->withSession($session)->get(route('admin.mirrors.khotab.show', $target));
    $public = H::contextualMarkup($test->withSession($session)->get("/khotab-item-{$target}.htm")->assertOk()->getContent());

    return [
        $admincp->getStatusCode() === 200 && str_contains((string) $admincp->getContent(), $move),
        str_contains($public, $move),
    ];
}

it('moves only when the admin holds the move permission for BOTH sections; refusals change nothing; the controls agree', function (array $permissions, bool $super, int $uid) {
    $admin = $super ? H::superAdmin('kmm-boss') : kmmAdmin($permissions, $uid);
    $may = fn (string $section) => $super || (in_array('khotab.editmirror', $permissions, true) && in_array('khotab.media_'.$section, $permissions, true));

    foreach ([['audio', 'audio'], ['video', 'video'], ['audio', 'video'], ['video', 'audio']] as [$from, $to]) {
        $mirror = kmmMirror($this, $from);
        [$source, $target] = [$this->items[$from][0], $this->items[$to][1]];
        $allowed = $may($from) && $may($to);

        // the controls the destination pages offer match the server's decision
        expect(kmmOffered($this, $admin, $mirror, $target))->toBe([$allowed, $allowed], "{$from}→{$to}: controls");

        $before = kmmState($mirror, $source, $target);
        $response = $this->withSession(H::session($admin))->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $target]);
        $after = kmmState($mirror, $source, $target);

        if ($allowed) {   // (the source counter never goes below 0)
            $response->assertRedirect(route('admin.mirrors.khotab.show', $target));
            expect($after)->toBe(['khid' => $target, 'from_count' => max(0, $before['from_count'] - 1), 'to_count' => $before['to_count'] + 1, 'audit' => 1], "{$from}→{$to}: moved");
        } else {
            $response->assertForbidden();
            expect($after)->toBe($before, "{$from}→{$to}: unchanged after refusal");
        }
    }
})->with([
    'audio-only, standalone' => [['khotab.editmirror', 'khotab.media_audio'], false, 0],
    'audio-only, forum-linked' => [['khotab.editmirror', 'khotab.media_audio'], false, 4242],
    'video-only, standalone' => [['khotab.editmirror', 'khotab.media_video'], false, 0],
    'video-only, forum-linked' => [['khotab.editmirror', 'khotab.media_video'], false, 4242],
    'both sections, standalone' => [['khotab.editmirror', 'khotab.media_audio', 'khotab.media_video'], false, 0],
    'both sections, forum-linked' => [['khotab.editmirror', 'khotab.media_audio', 'khotab.media_video'], false, 4242],
    'neither section' => [['khotab.editmirror'], false, 0],
    'both sections but no move permission' => [['khotab.media_audio', 'khotab.media_video', 'khotab.delmirror'], false, 0],
    'super-admin' => [[], true, 0],
]);

it('cannot be bypassed by posting a foreign destination directly (the route itself only sees the mirror)', function () {
    $videoOnly = kmmAdmin(['khotab.editmirror', 'khotab.media_video'], 0);
    $mirror = kmmMirror($this, 'video');
    [$source, $audioTarget] = [$this->items['video'][0], $this->items['audio'][1]];
    $before = kmmState($mirror, $source, $audioTarget);

    // no clipboard, no page: a hand-made request straight to the endpoint
    $this->withSession(H::session($videoOnly))->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $audioTarget])->assertForbidden();
    $this->withSession(H::session($videoOnly))->postJson(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $audioTarget])->assertForbidden();

    expect(kmmState($mirror, $source, $audioTarget))->toBe($before);
});

it('keeps validation unchanged: an unknown destination and the same item are still refused as before', function () {
    $both = kmmAdmin(['khotab.editmirror', 'khotab.media_audio', 'khotab.media_video'], 0);
    $mirror = kmmMirror($this, 'audio');

    $this->withSession(H::session($both))->from(route('admin.mirrors.khotab.show', $this->items['audio'][0]))
        ->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => 999999])->assertSessionHasErrors('target_item_id');
    $this->withSession(H::session($both))->from(route('admin.mirrors.khotab.show', $this->items['audio'][0]))
        ->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $this->items['audio'][0]])->assertSessionHasErrors('target_item_id');

    expect((int) DB::connection('main')->table('nuke_islamic_mirror')->where('id', $mirror)->value('khid'))->toBe($this->items['audio'][0]);
});

it('offers «إلغاء» (clear the clipboard) whenever the admin may edit mirrors, independent of sections', function () {
    $audioOnly = kmmAdmin(['khotab.editmirror', 'khotab.media_audio'], 0);
    $mirror = kmmMirror($this, 'audio');
    $session = H::session($audioOnly) + [KhotabMirrorController::CLIPBOARD_KEY => $mirror];
    $clear = 'action="'.route('admin.mirrors.khotab.clipboard.clear').'"';

    // on a VIDEO destination the move is not offered, but the clipboard can still be cleared
    $html = (string) $this->withSession($session)->get(route('admin.mirrors.khotab.show', $this->items['video'][1]))->getContent();
    expect($html)->toContain($clear)->not->toContain('action="'.route('admin.mirrors.khotab.move', $mirror).'"');
});
