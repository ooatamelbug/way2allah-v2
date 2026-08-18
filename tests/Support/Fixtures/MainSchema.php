<?php

namespace Tests\Support\Fixtures;

use Illuminate\Database\Schema\Blueprint;

/**
 * Canonical `main`-connection table fixtures — single source of truth so
 * no two test files can silently define the same legacy table with
 * different columns (the exact hazard found during Wave 0/1: AdminGuardTest
 * and EnsureAdminHasRoleTest both faked `nuke_authors`, and
 * ContentListingServiceTest/ContentSidebarWidgetTest both faked
 * `nuke_islamic_khotab` and `nuke_sat_channels` — each pair had drifted to
 * a different column set before this consolidation). Every column list
 * here is the richest version found across the files that previously
 * defined it, not a new invention.
 */
class MainSchema
{
    public static function nukeAuthors(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('uid')->nullable();
            $table->string('aid');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('pwd')->nullable();
            $table->string('password')->nullable();
            $table->boolean('radminsuper')->default(false);
            // Task 6.8 addition — backup.php's API-key column.
            $table->string('API')->nullable();
            // NOT added here: `permissions` (the raw serialized blob
            // `backupCategoryPermissions()` reads). Confirmed real legacy
            // column, but deliberately excluded from this SHARED fixture —
            // adding it here caused AdminUser::query()->first() to load it
            // into every loaded instance's attributes, which shadows
            // Spatie's own `permissions()` BelongsToMany relation for ANY
            // AdminUser row app-wide (Eloquent's getAttribute() checks
            // loaded attributes before relations), breaking
            // PermissionControllerTest's real, unrelated
            // getPermissionNames() call. This is a genuine, pre-existing
            // production-relevant conflict between AdminUser's Spatie
            // integration and the real `nuke_authors.permissions` column —
            // not created by this fixture, only exposed by it. Reported,
            // not fixed here (would require touching AdminUser.php/
            // PermissionController.php, both out of this task's scope).
            // BackupApiControllerTest/BackupApiKhotabUpdateBackupTest add
            // this column locally via their own Schema::table() call
            // instead of here, so no other test is affected.
        };
    }

    public static function nukeSatChannels(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->text('programs')->nullable();
            $table->unsignedInteger('time')->nullable();
            $table->string('freq')->nullable();
            $table->string('srate')->nullable();
            $table->string('fec')->nullable();
            $table->string('polar')->nullable();
            $table->string('enc')->nullable();
            $table->unsignedTinyInteger('beam')->nullable();
            $table->unsignedInteger('sat_id')->nullable();
            $table->unsignedTinyInteger('active')->nullable();
            $table->unsignedTinyInteger('khotab')->nullable();
            $table->unsignedTinyInteger('anasheed')->nullable();
            $table->string('streamcode')->nullable();
            $table->unsignedInteger('ch_visits')->nullable();
        };
    }

    public static function nukeSatSats(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('des')->nullable();
            $table->string('pos')->nullable();
            $table->unsignedInteger('channels')->nullable();
            $table->unsignedInteger('time')->nullable();
        };
    }

    public static function nukeIslamicAuthors(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('prename')->nullable();
            $table->string('author_image')->nullable();
            // Visual parity audit (khotab-video-17.htm, 2026-08-18) —
            // already a documented real column (Author model's own
            // docblock), just not previously needed by any fixture-
            // consuming test until author.php's description-block portlet.
            $table->text('description')->nullable();
            $table->unsignedInteger('audio')->default(0);
            $table->unsignedInteger('vedio')->default(0);
            $table->unsignedInteger('fatwa')->default(0);
            $table->unsignedInteger('pdf')->default(0);
            $table->unsignedTinyInteger('hidden')->default(0);
        };
    }

    public static function nukeIslamicAdvanced(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('adur')->nullable();
            // Task 6.8 (KhotabUpdateBackup/put) addition — the remaining
            // columns UpdateBackup()'s Adv metadata block writes.
            $table->string('perf')->nullable();
            $table->string('cright')->nullable();
            $table->string('frate')->nullable();
            $table->string('srate')->nullable();
            $table->string('vres')->nullable();
            $table->string('ares')->nullable();
            $table->string('astr')->nullable();
            $table->string('vstr')->nullable();
            $table->string('abit')->nullable();
            $table->string('vbit')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alist')->nullable();
            $table->string('vlist')->nullable();
        };
    }

    public static function nukeIslamicGroups(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('author_id')->default(0);
            $table->string('cat')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('time')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->unsignedTinyInteger('vedio')->default(0);
            $table->unsignedTinyInteger('hidden')->default(0);
        };
    }

    public static function nukeIslamicSeries(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('author_id')->default(0);
            $table->unsignedInteger('group_id')->default(0);
            $table->unsignedInteger('channel_id')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('time')->nullable();
            $table->unsignedInteger('lastupdate')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->unsignedTinyInteger('vedio')->default(0);
            $table->unsignedTinyInteger('hidden')->default(0);
            // Task 6.3 addition — pages/ramadan.php's WHERE ramadan='1' filter.
            $table->unsignedTinyInteger('ramadan')->default(0);
            // G-06 addition — categories/series.php's own Ser_Cat_Breadcrumb($Series->cat, ...)
            // (IF-039), a real, already-documented column (Series model's own
            // @property list) not previously needed by any fixture-consuming test.
            $table->string('cat')->nullable();
        };
    }

    /** Task 6.3 addition — `00-database-schema.md`'s `nuke_islamic_setting` entry: `Id` (capitalized in the real schema), `option`, `value` (text), `edit_time`. Backs `pages/ramadan.php`'s `ramadan_counter` row. */
    public static function nukeIslamicSetting(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('Id');
            $table->string('option')->nullable();
            $table->text('value')->nullable();
            $table->unsignedInteger('edit_time')->nullable();
        };
    }

    /** Post-Wave-4 addition (chat_room's lesson-browsing half, task 4.11). */
    public static function nukeIslamicAuthorsLocation(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('author_id')->default(0);
            $table->unsignedInteger('location_id')->default(0);
            $table->unsignedInteger('count')->default(0);
        };
    }

    /** Post-Wave-4 addition (chat_room's lesson-browsing half, task 4.11). */
    public static function nukeIslamicGroupsLocation(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('group_id')->default(0);
            $table->unsignedInteger('location_id')->default(0);
            $table->unsignedInteger('count')->default(0);
        };
    }

    /** Post-Wave-4 addition (chat_room's lesson-browsing half, task 4.11). */
    public static function nukeIslamicSeriesLocation(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('series_id')->default(0);
            $table->unsignedInteger('location_id')->default(0);
            $table->unsignedInteger('count')->default(0);
        };
    }

    public static function nukeW2aCat(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('main_cat')->default(0);
            // Added for Roadmap task 6.1 (fatawa) — showtree()'s/under_this_tasnif()'s
            // q_count>0 filter, already a real nuke_w2a_cat column
            // (00-database-schema.md), just not previously needed by any
            // fixture-consuming test.
            $table->unsignedInteger('q_count')->default(0);
            // G-06 additions — categoryTree()/anasheedCategoryTree()'s own
            // filter columns (categories.htm/var-categories.htm), both
            // already-documented real columns (Category model's own
            // @property list), not previously needed by any
            // fixture-consuming test.
            $table->unsignedInteger('video_count')->default(0);
            $table->unsignedInteger('anasheed_count')->default(0);
        };
    }

    public static function seriesCategoryIndex(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('series_id');
            $table->unsignedInteger('category_id');
        };
    }

    public static function khotabCategoryIndex(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('khotab_id');
            $table->unsignedInteger('category_id');
        };
    }

    public static function nukeIslamicKhotab(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('author')->default(0);
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('ser_id')->default(0);
            $table->unsignedInteger('group_id')->default(0);
            $table->string('title')->nullable();
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('time')->nullable();
            $table->unsignedInteger('pdf_time')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->integer('weight')->default(0);
            $table->unsignedTinyInteger('vedio')->default(0);
            $table->unsignedTinyInteger('hidden')->default(0);
            $table->unsignedTinyInteger('fixed')->default(0);
            $table->unsignedTinyInteger('broken')->default(0);
            $table->unsignedInteger('pdf')->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('link')->nullable();
            $table->unsignedInteger('linksize')->default(0);
            $table->unsignedInteger('downcount')->default(0);
            $table->unsignedTinyInteger('frame')->default(0);
            $table->unsignedTinyInteger('gif')->default(0);
            $table->unsignedInteger('lastvisit')->nullable();
            // G-02 (Homepage Migration) addition — homeLatestVideos()/homeLatestAudios()'s
            // ORDER BY lastmirror DESC, an already real, documented KhotabItem column.
            $table->unsignedInteger('lastmirror')->nullable();
            $table->unsignedInteger('mirror')->default(0);
            $table->unsignedInteger('location_id')->nullable();
            $table->string('uploader')->nullable();
            $table->unsignedInteger('addeddate')->nullable();
            $table->unsignedInteger('online')->default(0);
            $table->unsignedInteger('percent')->nullable();
            $table->unsignedInteger('checktime')->default(0);
            // Task 6.8 (KhotabUpdateBackup) addition — backup.php's get/put columns.
            $table->unsignedTinyInteger('down')->default(0);
            $table->unsignedInteger('booking')->default(0);
            $table->unsignedInteger('trial')->default(0);
            $table->unsignedInteger('downloader')->nullable();
            // G-02 (Homepage Migration) addition — list_latest_videos()/
            // list_latest_audios()'s `newslist = '1'` filter, an already
            // real, documented KhotabItem column (its own @property list)
            // not previously needed by any fixture-consuming test.
            $table->unsignedTinyInteger('newslist')->default(0);
        };
    }

    public static function nukeIslamicMirror(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('khid')->default(0);
            $table->string('comment')->nullable();
            $table->string('link')->nullable();
            $table->unsignedInteger('linksize')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedTinyInteger('vedio')->nullable();
            $table->unsignedTinyInteger('hidden')->default(0);
            $table->unsignedInteger('time')->nullable();
            $table->unsignedInteger('online')->default(0);
            $table->unsignedInteger('percent')->nullable();
            $table->unsignedInteger('checktime')->default(0);
            // Task 6.8 (KhotabUpdateBackup) addition.
            $table->unsignedTinyInteger('down')->default(0);
            $table->unsignedInteger('booking')->default(0);
            $table->unsignedInteger('trial')->default(0);
            $table->unsignedTinyInteger('backupme')->default(0);
            $table->unsignedInteger('downloader')->nullable();
        };
    }

    public static function nukeIslamicAdvancedM(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('vstr')->nullable();
            $table->string('astr')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // Task 6.8 (KhotabUpdateBackup/put) addition.
            $table->string('perf')->nullable();
            $table->string('cright')->nullable();
            $table->string('frate')->nullable();
            $table->string('srate')->nullable();
            $table->string('vres')->nullable();
            $table->string('ares')->nullable();
            $table->string('abit')->nullable();
            $table->string('vbit')->nullable();
            $table->string('adur')->nullable();
            $table->string('alist')->nullable();
            $table->string('vlist')->nullable();
        };
    }

    public static function nukeIslamicComments(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('khid')->default(0);
            $table->unsignedInteger('uid')->default(0);
            $table->string('uname')->nullable();
            $table->string('uemail')->default('');
            $table->unsignedInteger('mytime')->default(0);
            $table->text('comment')->nullable();
            $table->unsignedTinyInteger('view')->default(0);
            $table->string('ip')->nullable();
            $table->string('code')->nullable();
        };
    }

    public static function nukeAnasheedAnasheed(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('frame')->default(0);
            $table->unsignedInteger('downcount')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('mytime')->nullable();
            $table->unsignedInteger('group_id')->default(0);
            $table->string('link')->default('');
            $table->unsignedInteger('linksize')->nullable();
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedTinyInteger('mirror')->default(0);
            $table->unsignedInteger('order_in_group')->default(0);
            $table->unsignedTinyInteger('hidden')->default(0);
            $table->unsignedInteger('lastvisit')->nullable();
            $table->unsignedTinyInteger('fixed')->default(0);
            // Added for Roadmap task 6.2 (advanced-search) — vedio/parent_id/
            // weight/channel_id/author_id are already-documented real
            // columns (AnasheedItem's own @property list), just not
            // previously needed by any fixture-consuming test.
            $table->unsignedTinyInteger('vedio')->default(1);
            $table->unsignedInteger('parent_id')->nullable();
            $table->integer('weight')->default(0);
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('author_id')->nullable();
            // Task 6.8 (KhotabUpdateBackup) addition.
            $table->unsignedTinyInteger('down')->default(0);
            $table->unsignedInteger('booking')->default(0);
            $table->unsignedInteger('trial')->default(0);
            $table->unsignedInteger('downloader')->nullable();
            // G-06 addition — categories/functions.php's ListVar()
            // (`cat_id LIKE '%|X|%'`), a real, already-documented column
            // (AnasheedItem model's own @property list) not previously
            // needed by any fixture-consuming test.
            $table->string('cat_id')->nullable();
        };
    }

    public static function nukeAnasheedGroups(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('parent_id')->default(0);
            $table->text('description')->nullable();
            // Added for Roadmap task 6.2 (advanced-search) — already-documented
            // real columns (AnasheedGroup's own @property list).
            $table->unsignedInteger('time')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('author_id')->nullable();
            // G-05 (Migration Gap Register) additions — varieties_series_view()'s
            // own fields, confirmed real columns via Schema::getColumnListing()
            // against real olddb (`child`, `anasheed`, `des` distinct from
            // `description` above, `icon`), not previously needed by any
            // fixture-consuming test.
            $table->unsignedInteger('child')->default(0);
            $table->unsignedInteger('anasheed')->default(0);
            $table->text('des')->nullable();
            $table->unsignedTinyInteger('icon')->default(0);
        };
    }

    public static function nukeAnasheedMirror(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('khid')->default(0);
            $table->string('title')->nullable();
            $table->string('link')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('linksize')->nullable();
            // Task 6.8 (KhotabUpdateBackup) addition.
            $table->unsignedTinyInteger('down')->default(0);
            $table->unsignedInteger('booking')->default(0);
            $table->unsignedInteger('trial')->default(0);
            $table->unsignedTinyInteger('backupme')->default(0);
            $table->unsignedInteger('downloader')->nullable();
            $table->unsignedInteger('online')->default(0);
            $table->unsignedInteger('checktime')->default(0);
        };
    }

    public static function nukeAnasheedAdvanced(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('vstr')->nullable();
            $table->string('astr')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // Task 6.8 (KhotabUpdateBackup/put) addition.
            $table->string('perf')->nullable();
            $table->string('cright')->nullable();
            $table->string('frate')->nullable();
            $table->string('srate')->nullable();
            $table->string('vres')->nullable();
            $table->string('ares')->nullable();
            $table->string('abit')->nullable();
            $table->string('vbit')->nullable();
            $table->string('adur')->nullable();
            $table->string('alist')->nullable();
            $table->string('vlist')->nullable();
        };
    }

    /** Task 6.8 (KhotabUpdateBackup/put) addition — `nuke_anasheed_advanced_m`, `put`'s cat=5 target (`backup.php:382-384`). No prior model or fixture existed for this table. */
    public static function nukeAnasheedAdvancedM(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('perf')->nullable();
            $table->string('cright')->nullable();
            $table->string('frate')->nullable();
            $table->string('srate')->nullable();
            $table->string('vres')->nullable();
            $table->string('ares')->nullable();
            $table->string('astr')->nullable();
            $table->string('vstr')->nullable();
            $table->string('abit')->nullable();
            $table->string('vbit')->nullable();
            $table->string('adur')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alist')->nullable();
            $table->string('vlist')->nullable();
        };
    }

    public static function nukeAnasheedComments(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('khid')->default(0);
            $table->unsignedInteger('uid')->default(0);
            $table->string('uname')->nullable();
            $table->unsignedInteger('mytime')->default(0);
            $table->text('comment')->nullable();
            $table->string('ip')->default('0');
            $table->unsignedTinyInteger('view')->default(0);
            $table->string('code')->nullable();
        };
    }

    public static function nukeW2acdW2acd(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('mytime')->nullable();
            $table->string('banner')->nullable();
            $table->string('thumbnail')->default('');
            $table->unsignedInteger('group_id')->default(0);
            $table->unsignedInteger('order_in_group')->default(0);
            $table->text('link')->default('');
            $table->string('cd')->nullable();
            $table->unsignedInteger('linksize')->nullable();
            $table->unsignedInteger('downcount')->default(0);
            $table->unsignedTinyInteger('hidden')->default(0);
            $table->unsignedInteger('lastvisit')->nullable();
        };
    }

    public static function nukeW2acdGroups(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('parent_id')->default(0);
            $table->text('des')->default('');
            $table->string('module_type')->default('w2acd');
        };
    }

    public static function nukeTelawahTelawah(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('downcount')->default(0);
            $table->unsignedInteger('mytime')->nullable();
            $table->unsignedInteger('group_id')->default(0);
            $table->unsignedTinyInteger('sorah')->nullable();
            $table->string('link')->default('');
            $table->unsignedInteger('linksize')->nullable();
            $table->unsignedInteger('online')->default(0);
            $table->unsignedInteger('percent')->nullable();
            $table->unsignedInteger('checktime')->default(0);
            // Task 6.8 (KhotabUpdateBackup) addition.
            $table->unsignedTinyInteger('down')->default(0);
            $table->unsignedInteger('booking')->default(0);
            $table->unsignedInteger('trial')->default(0);
            $table->unsignedInteger('downloader')->nullable();
        };
    }

    /** Task 6.8 (KhotabUpdateBackup/put) addition — `nuke_telawah_advanced`, `put`'s cat=3 target (`backup.php:374-375`). No prior model or fixture existed for this table. */
    public static function nukeTelawahAdvanced(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('perf')->nullable();
            $table->string('cright')->nullable();
            $table->string('frate')->nullable();
            $table->string('srate')->nullable();
            $table->string('vres')->nullable();
            $table->string('ares')->nullable();
            $table->string('astr')->nullable();
            $table->string('vstr')->nullable();
            $table->string('abit')->nullable();
            $table->string('vbit')->nullable();
            $table->string('adur')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alist')->nullable();
            $table->string('vlist')->nullable();
        };
    }

    public static function nukeTelawahGroups(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('child')->default(0);
            $table->unsignedInteger('telawah')->default(0);
            $table->unsignedInteger('parent_id')->default(0);
            $table->text('des')->nullable();
        };
    }

    public static function nukeAlbums(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('album_id');
            $table->string('title')->nullable();
            $table->text('des')->nullable();
            $table->integer('order')->default(0);
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedTinyInteger('is_compressed')->default(0);
            $table->unsignedInteger('author_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('creation_date')->nullable();
            $table->unsignedInteger('last_update')->nullable();
        };
    }

    public static function nukeAlbumsImages(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('image_id');
            $table->unsignedInteger('album_id');
            $table->string('title')->nullable();
            $table->text('url');
            $table->integer('order')->default(0);
            $table->unsignedInteger('hits')->nullable();
            $table->unsignedInteger('author_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('creation_date')->nullable();
            $table->unsignedInteger('last_update')->nullable();
        };
    }

    /** No primary key (Fact, 00-database-schema.md) — GeoIpLookup's range table. */
    public static function ips(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('startip_num');
            $table->unsignedInteger('endip_num');
            $table->string('code')->nullable();
            $table->string('country')->nullable();
        };
    }

    // ---- Wave 5 (Admin domain) ----

    public static function nukeSurvey(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('openning')->nullable();
            $table->text('finish')->nullable();
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->unsignedTinyInteger('users_only')->default(0);
            $table->unsignedTinyInteger('ip_restriction')->default(0);
            $table->unsignedTinyInteger('anonymous')->default(0);
            $table->unsignedTinyInteger('published')->default(0);
            $table->string('editors')->nullable();
            $table->string('groups')->nullable();
            $table->unsignedInteger('questions')->default(0);
            $table->unsignedInteger('submits')->default(0);
        };
    }

    public static function nukeSurveyQuestions(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->string('des')->nullable();
            $table->text('question_options')->nullable();
            $table->unsignedTinyInteger('required')->default(0);
            $table->unsignedTinyInteger('question_type')->default(1);
            $table->unsignedInteger('max_sel_num')->nullable();
            $table->unsignedInteger('survey_id')->default(0);
            $table->unsignedInteger('weight')->default(0);
        };
    }

    public static function nukeSurveyAnswers(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('survey_id')->default(0);
            $table->unsignedInteger('user_id')->default(0);
            $table->string('ip')->nullable();
            $table->unsignedInteger('mytime')->nullable();
            $table->text('answers')->nullable();
        };
    }

    public static function estebian(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('facebook')->nullable();
            for ($i = 1; $i <= 11; $i++) {
                $table->text("remarks{$i}")->nullable();
            }
        };
    }

    public static function nukeUploaders(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('uid')->default(0);
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('counter')->default(0);
            $table->unsignedInteger('last_upload')->nullable();
        };
    }

    public static function nukeIslamicLocations(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('des')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('website')->nullable();
            $table->string('lng')->nullable();
            $table->string('lat')->nullable();
            $table->text('googlemap')->nullable();
            $table->unsignedTinyInteger('type')->default(1);
            $table->unsignedTinyInteger('hidden')->default(0);
            $table->unsignedInteger('count')->default(0);
        };
    }

    public static function nukeOptions(): \Closure
    {
        return function (Blueprint $table) {
            $table->string('option_name')->primary();
            $table->text('option_value')->nullable();
        };
    }

    public static function nukePollDesc(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('pollID');
            $table->string('pollTitle')->nullable();
            $table->unsignedInteger('timeStamp')->nullable();
            $table->unsignedInteger('voters')->default(0);
            $table->unsignedTinyInteger('artid')->default(0);
        };
    }

    public static function nukePollData(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('pollID');
            $table->unsignedTinyInteger('voteID');
            $table->string('optionText')->nullable();
            $table->unsignedInteger('optionCount')->default(0);
        };
    }

    /** G-02 (Homepage Migration) addition — `print_polls()`'s comment-count-quirk source table. No prior model or fixture existed for this table. */
    public static function nukePollcomments(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pollID');
            $table->string('comment')->nullable();
        };
    }

    /**
     * G-02 (Homepage Migration) addition — `show_ads_byposition()`'s
     * table. Column list confirmed via `Schema::getColumnListing('nuke_ads')`
     * against real `olddb` (HomeAd's own docblock).
     */
    public static function nukeAds(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->text('image_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('percentage')->nullable();
            $table->unsignedTinyInteger('type')->default(0);
            $table->string('ads_show_type')->nullable();
            $table->unsignedInteger('required_num_view')->nullable();
            $table->unsignedTinyInteger('show')->default(1);
            $table->string('link')->nullable();
            $table->string('startdate')->nullable();
            $table->string('enddate')->nullable();
            $table->unsignedInteger('num_view')->default(0);
            $table->unsignedInteger('num_click')->nullable();
            $table->string('path_type')->nullable();
        };
    }

    /** G-13-06 — `slider.php`'s backing table (`nuke_7amalat`, confirmed via real olddb schema). */
    public static function nuke7amalat(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('website')->default(0);
            $table->integer('order_index')->nullable();
            $table->string('title')->nullable();
            $table->string('image')->nullable();
            $table->string('mobile_image')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->text('url')->nullable();
            $table->string('des')->nullable();
            $table->integer('status')->default(1);
        };
    }

    public static function room(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('room_id');
            $table->string('name')->nullable();
            $table->string('des')->nullable();
            $table->unsignedInteger('max_user')->nullable();
            $table->unsignedTinyInteger('enable')->default(1);
            $table->unsignedTinyInteger('enable_audio')->default(0);
            $table->unsignedTinyInteger('enable_video')->default(0);
            $table->unsignedTinyInteger('enable_white_board')->default(0);
            $table->unsignedTinyInteger('member_only')->default(0);
            $table->string('password')->nullable();
            $table->string('welcome')->nullable();
            $table->string('owner')->nullable();
            $table->string('speaker')->nullable();
            $table->unsignedInteger('sequence')->default(0);
        };
    }

    // ---- Fatawa (Roadmap task 6.1) ------------------------------------

    public static function nukeFatwaTopics(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('topic_name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('parent_id')->default(0);
            $table->unsignedInteger('db_insertion_date')->nullable();
            $table->unsignedInteger('author_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
        };
    }

    public static function nukeFatwaGeneralQuestions(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->text('question_text')->nullable();
            $table->text('description')->nullable();
            $table->string('topic_id')->nullable();
            $table->unsignedInteger('num_view')->default(0);
            $table->unsignedInteger('author_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
        };
    }

    public static function nukeFatwaQuestions(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('topic_id')->nullable();
            $table->string('general_question_id')->nullable();
            $table->text('question_text')->nullable();
            $table->unsignedInteger('auther_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->text('answer_text')->nullable();
            $table->string('media_type')->nullable();
            $table->string('media_link')->nullable();
            $table->unsignedInteger('media_size')->nullable();
            $table->string('place_of_fatwa')->nullable();
            $table->string('date_of_fatwa')->nullable();
            $table->unsignedInteger('db_insertion_date')->nullable();
            $table->unsignedInteger('num_view')->default(0);
            $table->unsignedInteger('num_download')->default(0);
        };
    }

    /** Task 6.8 addition — `nuke_modules`, used here only for `backup.php`'s `WHERE title='BackUp'` admins-list lookup. */
    public static function nukeModules(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('admins')->nullable();
        };
    }

    /**
     * Task 6.8 addition — `00-database-schema.md`'s `nuke_backup_sessions`
     * entry. `active`'s real DEFAULT is confirmed UNKNOWN from PHP source
     * alone (see `BackupSession`'s docblock) — `default(1)` here is this
     * fixture's own inference (needed for CreateSession-then-LiveUpdate
     * to be testable at all), not a claim about the real schema's actual
     * DEFAULT clause.
     */
    public static function nukeBackupSessions(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('uid')->nullable();
            $table->unsignedInteger('createtime')->nullable();
            $table->unsignedInteger('updatetime')->nullable();
            $table->string('downloaded')->nullable();
            $table->string('ip')->nullable();
            $table->string('size')->nullable();
            // G-08-01 fix (Phase 1 audit) — count/speed/itemid/catid are
            // real int(11)/tinyint(4) NOT NULL DEFAULT '0' columns on
            // nuke_backup_sessions (SHOW CREATE TABLE, real olddb),
            // previously typed as nullable strings here — that mismatch
            // masked a real MySQL strict-mode risk (see
            // BackupApiController::liveUpdate()'s docblock).
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('speed')->default(0);
            $table->unsignedInteger('itemid')->default(0);
            $table->unsignedTinyInteger('catid')->default(0);
            $table->boolean('active')->default(1);
        };
    }

    /** Task 6.8 addition — `00-database-schema.md`'s `nuke_backup_booking` entry. Not yet written by any implemented operation (§`BackupBooking`'s docblock). */
    public static function nukeBackupBooking(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('uid')->nullable();
            $table->unsignedInteger('createtime')->nullable();
            $table->unsignedInteger('catid')->nullable();
            $table->unsignedInteger('itemid')->nullable();
            $table->unsignedInteger('sessionid')->nullable();
            $table->string('ip')->nullable();
        };
    }
}
