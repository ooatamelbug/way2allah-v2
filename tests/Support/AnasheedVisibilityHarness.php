<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/**
 * F08 / B4 test harness: every table the Variety surfaces read (item, group, news, home, search, category,
 * player, downloads, comments) plus the AdminCP tables (admins, permissions, audit).
 */
final class AnasheedVisibilityHarness
{
    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
            'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
            'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
            'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
            'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
            'nuke_anasheed_comments' => MainSchema::nukeAnasheedComments(),
            'ips' => MainSchema::ips(),
            'nuke_sat_channels' => MainSchema::nukeSatChannels(),
            'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
            'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
            'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
            'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
            'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
            'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
            'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
            'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
            'nuke_options' => MainSchema::nukeOptions(),
            'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
            'nuke_poll_desc' => MainSchema::nukePollDesc(),
            'nuke_poll_data' => MainSchema::nukePollData(),
            'nuke_pollcomments' => MainSchema::nukePollcomments(),
            'nuke_7amalat' => MainSchema::nuke7amalat(),
        ]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions = ['anasheed.editanasheed'], string $aid = 'b4-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');
        if ($permissions !== []) {
            $admin->givePermissionTo(KhotabMediaScope::withBothSectionsIfUnspecified($permissions));
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'b4-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    /** A department (root group, e.g. 98 / 57 / 12 / 158) with one sub-group. */
    public static function department(int $rootId, int $subId): void
    {
        DB::connection('main')->table('nuke_anasheed_groups')->insert([
            ['id' => $rootId, 'title' => 'قسم '.$rootId, 'parent_id' => 0],
            ['id' => $subId, 'title' => 'سلسلة '.$subId, 'parent_id' => $rootId],
        ]);
    }

    public static function item(int $id, string $title, int $groupId, int $parentId, array $attributes = []): int
    {
        DB::connection('main')->table('nuke_anasheed_anasheed')->insert(array_merge([
            'id' => $id, 'title' => $title, 'group_id' => $groupId, 'parent_id' => $parentId, 'hidden' => 0,
            'vedio' => 1, 'frame' => 0, 'hits' => 10, 'downcount' => 3, 'mytime' => 1700000000 + $id, 'lastvisit' => 1700000000 + $id,
            'weight' => $id, 'link' => 'https://archive.org/download/b4/'.$id.'.mp4', 'mirror' => 1, 'comments' => 0,
            'is_published' => 1, 'description' => 'وصف '.$title,
        ], $attributes));
        DB::connection('main')->table('nuke_anasheed_advanced')->insert(['id' => $id, 'adur' => '60']);
        DB::connection('main')->table('nuke_anasheed_mirror')->insert(['khid' => $id, 'title' => 'وصلة '.$id, 'hidden' => 0, 'vedio' => 1, 'hits' => 0,
            'link' => 'https://archive.org/download/b4/'.$id.'-m.mp3']);

        return $id;
    }

    /** @return array<string, mixed> */
    public static function row(int $id): array
    {
        return (array) DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $id)->first();
    }

    /** @return list<object> */
    public static function audit(): array
    {
        return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
    }
}
