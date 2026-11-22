<?php

/*
 * AUTH-01 (2026-11-21) — Khotab contextual controls respect the audio / video scope.
 *
 * Before: visibility checked only the target route's `admin.permission`, so a moderator holding a section-scoped key
 * (`khotab.edititem`, `editgroup`, `editseries`, `editmirror`, …) without the record's section key saw the control
 * and then got 403 from `EnsureKhotabMediaScope`. Now `ContextualAdmin::visible()` asks the same
 * `KhotabMediaScopeGate` the middleware uses, so a control is shown exactly when its action would be allowed.
 *
 * Every case also checks the server's own answer for the control's route, so "visible" and "allowed" are proven to be
 * the same decision. Isolated in-memory data (ContextualAdminHarness) — no real record or counter is touched.
 */

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

const CTXMS_KEYS = ['khotab.edititem', 'khotab.editgroup', 'khotab.editseries', 'khotab.editmirror'];

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 10, 'audio' => 10]);
    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    foreach (['video' => 1, 'audio' => 0] as $section => $vedio) {
        $group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => "مجموعة {$section}", 'hidden' => 0, 'vedio' => $vedio, 'channel_id' => 3, 'count' => 1]);
        $series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $group, 'channel_id' => 3, 'title' => "سلسلة {$section}", 'hidden' => 0, 'vedio' => $vedio, 'count' => 1]);
        $item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => "مادة {$section}", 'vedio' => $vedio, 'hidden' => 0, 'group_id' => $group, 'ser_id' => $series, 'channel_id' => 3, 'mirror' => 1]);
        $mirror = H::insert('nuke_islamic_mirror', ['khid' => $item, 'link' => "https://x/{$section}.mp4", 'comment' => "وصلة {$section}", 'vedio' => $vedio]);
        $this->{$section} = [
            // public page => the control's target URL on it
            "/khotab-item-{$item}.htm" => route('admin.khotab.items.edit', $item),
            "/khotab-group-{$group}.htm" => route('admin.khotab.groups.edit', $group),
            "/khotab-series-{$series}.htm" => route('admin.khotab.series.edit', $series),
            "/khotab-item-{$item}.htm#mirror" => route('admin.mirrors.khotab.edit', $mirror),
        ];
    }
});

/** @param list<string> $permissions */
function ctxmsAdmin(array $permissions, int $uid): AdminUser
{
    $admin = H::admin($permissions, 'kms-'.uniqid());
    $admin->forceFill(['uid' => $uid])->save();   // 0 = standalone, > 0 = forum-linked

    return $admin->fresh();
}

/** Controls shown to $admin on each public page, and the server's own answer for each target route. */
function ctxmsObserve($test, AdminUser $admin, array $targets): array
{
    $out = [];
    foreach ($targets as $page => $target) {
        $html = H::contextualMarkup($test->withSession(H::session($admin))->get(strtok($page, '#'))->assertOk()->getContent());
        $shown = str_contains($html, 'href="'.$target) || str_contains($html, 'action="'.$target);
        $status = $test->withSession(H::session($admin))->get($target)->getStatusCode();
        $out[$page] = [$shown, $status];
    }

    return $out;
}

it('shows each Khotab control exactly when the audio / video scope allows its action', function (array $sections, bool $super, int $uid) {
    $admin = $super ? H::superAdmin() : ctxmsAdmin(array_merge(CTXMS_KEYS, $sections), $uid);
    $mayVideo = $super || in_array('khotab.media_video', $sections, true);
    $mayAudio = $super || in_array('khotab.media_audio', $sections, true);

    foreach (['video' => $mayVideo, 'audio' => $mayAudio] as $section => $allowed) {
        foreach (ctxmsObserve($this, $admin, $this->{$section}) as $page => [$shown, $status]) {
            // The in-memory fixture cannot render every full edit form, so the server answer is judged as the
            // authorization decision: refused (403) or let through to the controller (anything else but 401).
            expect($shown)->toBe($allowed, "{$section} {$page}: control ".($allowed ? 'must be shown' : 'must be hidden'))
                ->and($status === 403)->toBe(! $allowed, "{$section} {$page}: server answer was {$status}")
                ->and($status)->not->toBe(401);
        }
    }
})->with([
    'neither section, standalone' => [[], false, 0],
    'neither section, forum-linked' => [[], false, 4242],
    'audio only, standalone' => [['khotab.media_audio'], false, 0],
    'audio only, forum-linked' => [['khotab.media_audio'], false, 4242],
    'video only, standalone' => [['khotab.media_video'], false, 0],
    'video only, forum-linked' => [['khotab.media_video'], false, 4242],
    'both sections, standalone' => [['khotab.media_audio', 'khotab.media_video'], false, 0],
    'both sections, forum-linked' => [['khotab.media_audio', 'khotab.media_video'], false, 4242],
    'super-admin (no keys at all)' => [[], true, 0],
]);

it('follows grants and revokes made in the permission editor', function (int $uid) {
    $boss = H::superAdmin('kms-boss');
    $admin = ctxmsAdmin([], $uid);
    [$videoItemPage, $videoEdit] = [array_key_first($this->video), $this->video[array_key_first($this->video)]];
    [$audioItemPage, $audioEdit] = [array_key_first($this->audio), $this->audio[array_key_first($this->audio)]];
    $editor = fn (array $permissions) => $this->withSession(H::session($boss))
        ->put(route('admin.permissions.update', $admin), ['permissions' => array_fill_keys($permissions, '1')])->assertRedirect();
    $sees = fn (string $page, string $target) => str_contains(H::contextualMarkup($this->withSession(H::session($admin))->get($page)->getContent()), 'href="'.$target);

    // the item key alone: the control is hidden in both sections, the server refuses both
    $editor(['khotab.edititem']);
    expect($sees($videoItemPage, $videoEdit))->toBeFalse()->and($sees($audioItemPage, $audioEdit))->toBeFalse();
    $this->withSession(H::session($admin))->get($videoEdit)->assertForbidden();

    // + video section: shown and allowed on the video item only
    $editor(['khotab.edititem', 'khotab.media_video']);
    expect($sees($videoItemPage, $videoEdit))->toBeTrue()->and($sees($audioItemPage, $audioEdit))->toBeFalse();
    expect($this->withSession(H::session($admin))->get($videoEdit)->getStatusCode())->not->toBeIn([401, 403]);
    $this->withSession(H::session($admin))->get($audioEdit)->assertForbidden();

    // revoke everything: hidden again, refused again
    $editor([]);
    expect($sees($videoItemPage, $videoEdit))->toBeFalse();
    $this->withSession(H::session($admin))->get($videoEdit)->assertForbidden();
})->with(['standalone' => [0], 'forum-linked' => [4242]]);

it('leaves controls outside the scope untouched (any-admin and non-scoped keys)', function () {
    // «مواد لم تصنف» (any admin) and «إضافة وصلة» (khotab.addmirror, not section-scoped) do not depend on the section
    $admin = ctxmsAdmin(['khotab.addmirror'], 0);
    $page = array_key_first($this->audio);
    $html = H::contextualMarkup($this->withSession(H::session($admin))->get($page)->assertOk()->getContent());
    $itemId = (int) preg_replace('/\D/', '', $page);

    expect($html)->toContain('href="'.route('admin.mirrors.khotab.show', $itemId));
});
