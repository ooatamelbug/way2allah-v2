<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/**
 * OLD Admin Migration, contextual surface — one `main` fixture covering every
 * public page that carries contextual controls and every admin operation they
 * lead to, so a test can render the real public page and then follow the
 * control into the real admin route. Tables come from `MainSchema` only.
 *
 * Admins are disposable rows logged in through the admin guard's own session
 * key (`session()` helper below), exactly as a real login leaves them, so the
 * public page sees them the way production will.
 */
final class ContextualAdminHarness
{
    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
            'nuke_config' => MainSchema::nukeConfig(),
            'ips' => MainSchema::ips(),
            'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
            'khotab_category_index' => MainSchema::khotabCategoryIndex(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
            'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
            'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
            'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
            'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
            'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
            'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
            'nuke_islamic_comments' => MainSchema::nukeIslamicComments(),
            'nuke_islamic_advfile' => MainSchema::nukeIslamicAdvfile(),
            'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
            'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
            'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
            'nuke_sat_channels' => MainSchema::nukeSatChannels(),
            'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
            'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
            'nuke_anasheed_comments' => MainSchema::nukeAnasheedComments(),
            'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
            'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
            'nuke_poll_desc' => MainSchema::nukePollDesc(),
            'nuke_poll_data' => MainSchema::nukePollData(),
            'nuke_pollcomments' => MainSchema::nukePollcomments(),
            'nuke_w2acd_w2acd' => MainSchema::nukeW2acdW2acd(),
            'nuke_w2acd_groups' => MainSchema::nukeW2acdGroups(),
            'nuke_w2acd_mirror' => MainSchema::nukeW2acdMirror(),
            'nuke_downloads_categories' => MainSchema::nukeDownloadsCategories(),
            'nuke_downloads_downloads' => MainSchema::nukeDownloadsDownloads(),
            'nuke_downloads_newdownload' => MainSchema::nukeDownloadsNewdownload(),
            'nuke_downloads_modrequest' => MainSchema::nukeDownloadsModrequest(),
            'nuke_downloads_votedata' => MainSchema::nukeDownloadsVotedata(),
            'nuke_downloads_editorials' => MainSchema::nukeDownloadsEditorials(),
            'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
            'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
            'nuke_telawah_mirror' => MainSchema::nukeTelawahMirror(),
            'nuke_telawah_comments' => MainSchema::nukeTelawahComments(),
            'nuke_telawah_advanced' => MainSchema::nukeTelawahAdvanced(),
            'nuke_telawah_sorahlist' => MainSchema::nukeTelawahSorahlist(),
        ]);
        DB::connection('main')->table('nuke_config')->insert(['language' => 'arabic', 'multilingual' => 0, 'anonymous' => 'Anonymous']);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    /** Both Khotab sections, for a test that means "a moderator of audio AND video" — passed explicitly, never implied. */
    public const BOTH_SECTIONS = ['khotab.media_audio', 'khotab.media_video'];

    /**
     * An administrator holding EXACTLY $permissions — as the AdminCP permission editor creates one: no role, and no
     * Khotab section key unless the test passes `khotab.media_audio` / `khotab.media_video` itself (AUTH-03,
     * 2026-11-21: the earlier silent `admin` role and both-section grant hid AUTH-01 from the whole suite).
     *
     * @param  list<string>  $permissions
     */
    public static function admin(array $permissions = [], string $aid = 'ctx-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'ctx-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    /**
     * The session a real admin login leaves behind (AdminGuard::SESSION_KEY).
     * Also forgets resolved guards: a guard memoises its user for the life of
     * the app instance, which in a test spans several requests.
     *
     * @return array<string, int>
     */
    public static function session(AdminUser $admin): array
    {
        Auth::forgetGuards();

        return ['admin_auth_id' => (int) $admin->getKey()];
    }

    /** A fresh visitor for the next request in the same test. */
    public static function visitor(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }

    /** @param array<string, mixed> $attributes */
    public static function insert(string $table, array $attributes, string $key = 'id'): int
    {
        return (int) DB::connection('main')->table($table)->insertGetId($attributes, $key);
    }

    public static function category(int $id, string $title, int $parent = 0): void
    {
        DB::connection('main')->table('nuke_w2a_cat')->insert([
            'id' => $id, 'title' => $title, 'main_cat' => $parent, 'video_count' => 10, 'anasheed_count' => 10,
        ]);
    }

    /** The contextual markup only (data-contextual-admin blocks), for focused assertions. */
    public static function contextualMarkup(string $html): string
    {
        preg_match_all('#<div[^>]*data-contextual-admin[^>]*>.*?</div>#s', $html, $m);

        return implode("\n", $m[0]);
    }
}
