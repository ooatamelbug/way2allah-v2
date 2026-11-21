<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

/**
 * OLD Khotab audio / video moderator scope (`AvAdmin()`), reproduced 2026-11-17 by KhotabMediaScopeConstraint +
 * EnsureKhotabMediaScope. Real AdminCP routes, real writes, both sections.
 */
const KMS_KEYS = ['khotab.editgroup', 'khotab.delgroup', 'khotab.additem', 'khotab.addgroup', 'khotab.edititem', 'khotab.delitem'];

beforeEach(function () {
    H::setUp();
    foreach ([
        'nuke_islamic_locations' => MainSchema::nukeIslamicLocations(),
        'nuke_islamic_authors_location' => MainSchema::nukeIslamicAuthorsLocation(),
        'nuke_islamic_groups_location' => MainSchema::nukeIslamicGroupsLocation(),
        'nuke_islamic_series_location' => MainSchema::nukeIslamicSeriesLocation(),
    ] as $table => $schema) {
        Schema::connection('main')->create($table, $schema);
    }
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 0, 'audio' => 0]);
    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    $this->videoGroup = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مرئي', 'hidden' => 0, 'vedio' => 1, 'channel_id' => 3, 'count' => 1]);
    $this->audioGroup = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'صوتي', 'hidden' => 0, 'vedio' => 0, 'channel_id' => 3, 'count' => 1]);
    $this->videoItem = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة مرئية', 'vedio' => 1, 'group_id' => $this->videoGroup, 'ser_id' => 0, 'channel_id' => 3]);
    $this->audioItem = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة صوتية', 'vedio' => 0, 'group_id' => $this->audioGroup, 'ser_id' => 0, 'channel_id' => 3]);
});

/** A moderator with the given sections, built without the harness's both-sections default. */
function kmsModerator(string $aid, array $sections): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
    $admin->assignRole('admin');
    foreach (array_merge(KMS_KEYS, $sections) as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
    }
    $admin->givePermissionTo(array_merge(KMS_KEYS, $sections));

    return $admin->fresh();
}

function kmsTitle(string $table, int $id): ?string
{
    return DB::connection('main')->table($table)->where('id', $id)->value('title');
}

/** Run every scoped write against the video and the audio records; return what each did. */
function kmsRun($test, AdminUser $actor): array
{
    $s = H::session($actor);
    $out = [];
    foreach (['video' => [$test->videoGroup, $test->videoItem], 'audio' => [$test->audioGroup, $test->audioItem]] as $media => [$group, $item]) {
        $out[$media]['edit_form'] = $test->withSession($s)->get(route('admin.khotab.items.edit', $item))->status();
        $out[$media]['group_form'] = $test->withSession($s)->get(route('admin.khotab.groups.edit', $group))->status();
        $test->withSession($s)->put(route('admin.khotab.groups.update', $group), ['title' => 'عدّل-'.$media]);
        $out[$media]['group_edited'] = kmsTitle('nuke_islamic_groups', $group) === 'عدّل-'.$media;
        $before = DB::connection('main')->table('nuke_islamic_khotab')->where('group_id', $group)->count();
        $test->withSession($s)->post(route('admin.khotab.groups.items.store', $group), ['link' => 'https://example.test/'.$media.'.mp4', 'title' => 'جديد '.$media]);
        $out[$media]['item_added'] = DB::connection('main')->table('nuke_islamic_khotab')->where('group_id', $group)->count() > $before;
        $groupsBefore = DB::connection('main')->table('nuke_islamic_groups')->where('vedio', $media === 'video' ? 1 : 0)->count();
        $test->withSession($s)->post(route('admin.khotab.authors.groups.store', [7, $media]), ['title' => 'مجموعة جديدة '.$media]);
        $out[$media]['author_group_added'] = DB::connection('main')->table('nuke_islamic_groups')->where('vedio', $media === 'video' ? 1 : 0)->count() > $groupsBefore;
        $out[$media]['item_delete_status'] = $test->withSession($s)->delete(route('admin.khotab.items.destroy', $item))->status();
    }

    return $out;
}

it('video-only: every scoped operation works on video content and is refused (403, nothing written) on audio content', function () {
    $r = kmsRun($this, kmsModerator('video-only', ['khotab.media_video']));

    // The group form itself uses MySQL-only `ORDER BY BINARY` (500 on the SQLite fixture, for any admin); the scope
    // decision happens before it, so "not refused" is the check here (the real page is verified on MySQL).
    expect($r['video']['edit_form'])->toBe(200)->and($r['video']['group_form'])->not->toBe(403)
        ->and($r['video']['group_edited'])->toBeTrue()->and($r['video']['item_added'])->toBeTrue()->and($r['video']['author_group_added'])->toBeTrue()
        ->and($r['video']['item_delete_status'])->not->toBe(403)
        ->and($r['audio']['edit_form'])->toBe(403)->and($r['audio']['group_form'])->toBe(403)
        ->and($r['audio']['group_edited'])->toBeFalse()->and($r['audio']['item_added'])->toBeFalse()->and($r['audio']['author_group_added'])->toBeFalse()
        ->and($r['audio']['item_delete_status'])->toBe(403)
        ->and(DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->audioItem)->exists())->toBeTrue();
});

it('audio-only: the mirror image', function () {
    $r = kmsRun($this, kmsModerator('audio-only', ['khotab.media_audio']));

    expect($r['audio']['edit_form'])->toBe(200)->and($r['audio']['group_edited'])->toBeTrue()->and($r['audio']['item_added'])->toBeTrue()
        ->and($r['audio']['author_group_added'])->toBeTrue()->and($r['audio']['item_delete_status'])->not->toBe(403)
        ->and($r['video']['edit_form'])->toBe(403)->and($r['video']['group_edited'])->toBeFalse()->and($r['video']['item_added'])->toBeFalse()
        ->and($r['video']['author_group_added'])->toBeFalse()->and($r['video']['item_delete_status'])->toBe(403);
});

it('both sections: every scoped operation works on both', function () {
    $r = kmsRun($this, kmsModerator('both', ['khotab.media_audio', 'khotab.media_video']));

    foreach (['video', 'audio'] as $m) {
        expect($r[$m]['edit_form'])->toBe(200)->and($r[$m]['group_edited'])->toBeTrue()->and($r[$m]['item_added'])->toBeTrue()
            ->and($r[$m]['author_group_added'])->toBeTrue()->and($r[$m]['item_delete_status'])->not->toBe(403);
    }
});

it('neither section: the scoped keys alone allow nothing on either section', function () {
    $r = kmsRun($this, kmsModerator('neither', []));

    foreach (['video', 'audio'] as $m) {
        expect($r[$m]['edit_form'])->toBe(403)->and($r[$m]['group_form'])->toBe(403)->and($r[$m]['group_edited'])->toBeFalse()
            ->and($r[$m]['item_added'])->toBeFalse()->and($r[$m]['author_group_added'])->toBeFalse()->and($r[$m]['item_delete_status'])->toBe(403);
    }
});

it('no escalation: a section key without the action key grants nothing; super-admins are not scoped; unscoped Khotab keys are unaffected', function () {
    $sectionOnly = AdminUser::on('main')->create(['aid' => 'section-only', 'password' => 'x']);
    $sectionOnly->assignRole('admin');
    Permission::firstOrCreate(['name' => 'khotab.media_video', 'guard_name' => 'admin']);
    $sectionOnly->givePermissionTo('khotab.media_video');
    $this->withSession(H::session($sectionOnly))->put(route('admin.khotab.groups.update', $this->videoGroup), ['title' => 'x'])->assertForbidden();
    expect(kmsTitle('nuke_islamic_groups', $this->videoGroup))->toBe('مرئي');

    $r = kmsRun($this, H::superAdmin());
    expect($r['video']['group_edited'])->toBeTrue()->and($r['audio']['group_edited'])->toBeTrue()
        ->and($r['audio']['item_added'])->toBeTrue()->and($r['video']['author_group_added'])->toBeTrue();

    // `sortitems` is not section-scoped in OLD: a neither-section moderator holding it reaches the rearrange page.
    $sorter = kmsModerator('sorter', []);
    Permission::firstOrCreate(['name' => 'khotab.sortitems', 'guard_name' => 'admin']);
    $sorter->givePermissionTo('khotab.sortitems');
    $this->withSession(H::session($sorter))->get(route('admin.khotab.groups.rearrange', $this->audioGroup))->assertOk();
});
