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
            // ONLY_FULL_GROUP_BY fix (2026-09-25) — `des` is a real column
            // (`text`, confirmed against the schema dump) that legacy's
            // `fatawa-by-authers.php:24` selects, so
            // `ContentListingService::fatwaAuthorsWithQuestions()` selects and
            // groups by it to preserve the legacy query contract. Added here
            // because no fixture-consuming test had needed it until now.
            $table->text('des')->nullable();
            $table->unsignedInteger('audio')->default(0);
            $table->unsignedInteger('vedio')->default(0);
            $table->unsignedInteger('fatwa')->default(0);
            $table->unsignedInteger('pdf')->default(0);
            $table->unsignedTinyInteger('hidden')->default(0);
            // OLD Admin Migration Phase 5 addition — the real `advfile`
            // column (int NOT NULL DEFAULT 0, `information_schema`). Defaulted,
            // so no existing insert is affected.
            $table->integer('advfile')->default(0);
            // OLD public admin parity (Khotab item page) — the real `docx`
            // counter (int(11) DEFAULT 0). Defaulted.
            $table->integer('docx')->default(0);
            // OLD public admin parity (Khotab author page) — the real columns
            // «تعديل بيانات الداعية» writes: block / meta_keywords /
            // meta_description (text NULL), meta_index / meta_follow
            // (tinyint(1) DEFAULT 1). Nullable / defaulted.
            $table->text('block')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedTinyInteger('meta_index')->default(1);
            $table->unsignedTinyInteger('meta_follow')->default(1);
            // OLD public admin parity (Khotab Main) — the real `time` column
            // (int(11) DEFAULT 0) «أضـــف شـــيـــخ» stamps. Defaulted.
            $table->integer('time')->default(0);
        };
    }

    /** `nuke_brokenlinks` — the visitors' broken-link reports (real columns, local `SHOW COLUMNS`). */
    public static function nukeBrokenlinks(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->integer('khid')->default(0);
            $table->tinyInteger('divid')->default(0);
            $table->string('title')->nullable();
            $table->text('link')->nullable();
            $table->integer('size')->default(0);
            $table->text('emails')->nullable();
            $table->unsignedInteger('count')->nullable()->default(0);
            $table->integer('time')->nullable();
            $table->string('cd')->default('');
            $table->tinyInteger('backupme')->default(1);
            $table->integer('confirmed')->default(0);
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
            // Full Design Parity Pass (khotab-group-{id}.htm) addition —
            // group.php:70's real, already-documented KhotabGroup column
            // (`@property string|null $description`), not previously
            // needed by any fixture-consuming test.
            $table->text('description')->nullable();
            $table->unsignedInteger('time')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->unsignedTinyInteger('vedio')->default(0);
            $table->unsignedTinyInteger('hidden')->default(0);
            // OLD Admin Migration Phase 5 addition — the real `advfile`
            // column (int NOT NULL DEFAULT 0, `information_schema`). Defaulted,
            // so no existing insert is affected.
            $table->integer('advfile')->default(0);
            // OLD public admin parity (Khotab group page) additions — the real
            // columns OLD ConfirmEditGroup writes. Nullable / defaulted, so no
            // existing insert is affected.
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedTinyInteger('meta_index')->default(1);
            $table->unsignedTinyInteger('meta_follow')->default(1);
            $table->text('des')->nullable();
            $table->unsignedInteger('lastupdate')->nullable();
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
            // OLD Admin Migration Phase 5 addition — the real `advfile`
            // column (int NOT NULL DEFAULT 0, `information_schema`). Defaulted,
            // so no existing insert is affected.
            $table->integer('advfile')->default(0);
            // OLD public admin parity (Khotab group page) additions — the real
            // columns OLD ConfirmInsertSeries writes. Nullable / defaulted.
            $table->text('description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedTinyInteger('meta_index')->default(1);
            $table->unsignedTinyInteger('meta_follow')->default(1);
            $table->text('des')->nullable();
        };
    }

    /**
     * OLD Admin Migration Phase 5 — `nuke_islamic_advfile`, per `SHOW CREATE
     * TABLE`: `id` is the Khotab item's id (1:1, unique, nullable, no FK).
     */
    public static function nukeIslamicAdvfile(): \Closure
    {
        return function (Blueprint $table) {
            $table->integer('id')->nullable()->unique();
            $table->text('code')->nullable();
            $table->integer('credit')->nullable()->default(0);
            $table->text('audio_url')->nullable();
            $table->boolean('small_thumb')->default(false);
            $table->boolean('large_thumb')->default(false);
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
            // Full Design Parity Pass (fatawa-topics-{category}-{page}.htm) —
            // under_this_tasnif()'s real `ORDER BY level DESC, id DESC`
            // (net `level ASC, id ASC` after legacy's own krsort()), a real
            // Category model column not previously needed by any fixture.
            $table->unsignedInteger('level')->default(0);
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
            // C-1 (2026-10-07): made nullable to match the real column,
            // confirmed by production `SHOW CREATE TABLE` — `author int(6)
            // DEFAULT NULL`. Needed so the `INNER JOIN nuke_islamic_authors`
            // exclusion of NULL-author rows (2 such rows in production's
            // `vedio=1 AND hidden=0` scope) is actually testable. Widening a
            // NOT NULL column to nullable cannot affect any existing test:
            // every insert still supplies a value or takes the default.
            $table->unsignedInteger('author')->nullable()->default(0);
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('ser_id')->default(0);
            $table->unsignedInteger('group_id')->default(0);
            // OLD Admin Migration Phase 3 addition — the real pipe-delimited
            // category list (`cat`), written by category assignment and read
            // by the `khotab_category_index` triggers. Nullable, so no
            // existing insert is affected.
            $table->string('cat')->nullable();
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
            // OLD Admin Migration Phase 5 addition — the real `advfile`
            // column (int NOT NULL DEFAULT 0, `information_schema`). Defaulted,
            // so no existing insert is affected.
            $table->integer('advfile')->default(0);
            // OLD public admin parity (Khotab group page) additions — the real
            // SEO columns OLD ConfirmInsertKhotab writes. Nullable / defaulted.
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedTinyInteger('meta_index')->default(1);
            $table->unsignedTinyInteger('meta_follow')->default(1);
            // OLD public admin parity (Khotab item page) additions — the real
            // columns its panel reads and writes (local `SHOW COLUMNS`):
            // banner tinyint(1), docx / docx_time int(11), leeched tinyint(4),
            // cd varchar(255). Defaulted, so no existing insert is affected.
            $table->tinyInteger('banner')->default(0);
            $table->integer('docx')->default(0);
            $table->integer('docx_time')->default(0);
            $table->tinyInteger('leeched')->default(0);
            $table->string('cd')->default('');
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
            // OLD Admin Migration Phase 1. Real column, production-confirmed
            // (Pass 3E.1 Check A3: `lastmirror` DEFAULT 0) and already a
            // documented AnasheedItem property; mirror insert writes it.
            $table->unsignedInteger('lastmirror')->default(0);
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
            // OLD public admin parity (Anasheed group page, «إضافة مادة») —
            // the real columns OLD ConfirmInsertAnasheed writes that no
            // fixture-consuming test had needed: notes (varchar(255) NOT NULL
            // DEFAULT ''), meta_keywords / meta_description (text NULL),
            // meta_index / meta_follow (tinyint(1) DEFAULT 1). Defaulted.
            $table->string('notes', 255)->default('');
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedTinyInteger('meta_index')->default(1);
            $table->unsignedTinyInteger('meta_follow')->default(1);
            // Production column (owner-verified information_schema, 2026-10-23):
            // `is_published` NOT NULL DEFAULT 0 — absent from the local olddb
            // copy and the 2025 dump, present in production.
            $table->unsignedTinyInteger('is_published')->default(0);
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
            // OLD public admin parity (Anasheed group page, «تعديل القسم» /
            // «إضافة قسم فرعي») — real columns those handlers write:
            // meta_keywords / meta_description (text NULL), meta_index /
            // meta_follow (tinyint(1) DEFAULT 1). Defaulted.
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedTinyInteger('meta_index')->default(1);
            $table->unsignedTinyInteger('meta_follow')->default(1);
        };
    }

    /**
     * `nuke_telawah_mirror` — the production shape (Pass 3E.1 §0.2.1), as the
     * local `olddb` copy shows it: no `khid`, no backup-queue or link-quality
     * columns; `video` and `backupme` are CHAR(1).
     */
    public static function nukeTelawahMirror(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->integer('telawa_id')->nullable();
            $table->string('comment', 255)->default('');
            $table->string('link', 255)->nullable();
            $table->string('extension', 100)->nullable();
            $table->integer('size')->nullable();
            $table->char('video', 1)->nullable()->default('');
            $table->char('backupme', 1)->default('');
            $table->timestamp('creation_date')->nullable()->useCurrent();
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
            // OLD Admin Migration Phase 1. Real column (AnasheedMirror's own
            // confirmed column list; OLD's insert writes `time`).
            $table->unsignedInteger('time')->nullable();
            // Task 6.8 (KhotabUpdateBackup) addition.
            $table->unsignedTinyInteger('down')->default(0);
            $table->unsignedInteger('booking')->default(0);
            $table->unsignedInteger('trial')->default(0);
            $table->unsignedTinyInteger('backupme')->default(0);
            $table->unsignedInteger('downloader')->nullable();
            $table->unsignedInteger('online')->default(0);
            $table->unsignedInteger('checktime')->default(0);
            // var-item-{id}.htm parity batch — already-documented real
            // columns (AnasheedMirror's own @property list: vedio, hidden),
            // not previously needed by any fixture-consuming test.
            $table->unsignedTinyInteger('vedio')->default(1);
            $table->unsignedTinyInteger('hidden')->default(0);
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
            // OLD Admin Migration Phase 6 additions — real columns
            // (`information_schema`) written by CD administration. All
            // defaulted, so no existing insert is affected.
            $table->unsignedInteger('parent_id')->default(0);
            $table->string('notes')->nullable();
            $table->string('downloader')->default('');
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
            // OLD Admin Migration Phase 6 addition — the real per-group
            // release counter OLD moved by ±1 on create / delete.
            $table->integer('w2acd')->default(0);
            // W2ACD public admin parity, Phase 2 — the rest of the real
            // columns the group page and its controls read or write.
            $table->integer('time')->nullable();
            $table->integer('child')->default(0);
            $table->integer('size')->default(0);
            $table->integer('dur')->default(0);
            $table->integer('author_id')->default(0)->nullable();
            $table->integer('channel_id')->nullable();
            $table->tinyInteger('icon')->default(0);
        };
    }

    /**
     * OLD Admin Migration Phase 6 — the normalized `nuke_w2acd_mirror`
     * table, which is empty and never used (3E.1 §0.4.1). Modelled only so
     * tests can prove CD administration never writes to it.
     */
    public static function nukeW2acdMirror(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('khid')->default(0);
            $table->text('link')->nullable();
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
            // OLD public admin parity — Telawah (2026-10-29): the production
            // columns the admin insert / edit / move write, with production's defaults.
            $table->string('notes', 255)->default('');
            $table->unsignedTinyInteger('vedio')->default(0);
            $table->integer('parent_id')->default(0);
            $table->integer('comments')->default(0);
        };
    }

    /** OLD public admin parity — Telawah (2026-10-29): `nuke_telawah_comments`, the production shape. */
    public static function nukeTelawahComments(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->integer('khid')->nullable()->default(0);
            $table->integer('uid')->nullable()->default(0);
            $table->integer('date')->default(0);
            $table->text('comment');
            $table->unsignedTinyInteger('view')->default(0);
        };
    }

    /** OLD public admin parity — Telawah (2026-10-29): `nuke_telawah_sorahlist` (production: ids 1–114). */
    public static function nukeTelawahSorahlist(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->nullable();
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
            // OLD Admin Migration Phase 4 additions — the real `planguage`
            // and `comments` columns (`SHOW CREATE TABLE nuke_poll_desc`),
            // written by poll administration. Both defaulted, so no
            // existing insert is affected.
            $table->string('planguage', 30)->default('');
            $table->integer('comments')->nullable()->default(0);
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
            // OLD Admin Migration Phase 4: the key is the real `tid` (was
            // `id`, which no reader referenced) plus the real `pid` thread
            // parent and display columns (`SHOW CREATE TABLE
            // nuke_pollcomments`). Every existing insert supplies only
            // pollID/comment, so none is affected.
            $table->increments('tid');
            $table->unsignedInteger('pid')->default(0);
            $table->unsignedInteger('pollID');
            $table->dateTime('date')->nullable();
            $table->string('name', 60)->default('');
            $table->string('subject', 60)->default('');
            $table->text('comment')->nullable();
            // Final public features (2026-10-15) — the rest of the production
            // shape, needed by the public comment pages; defaults keep every
            // earlier insert valid.
            $table->string('email', 60)->default('');
            $table->string('url', 60)->default('');
            $table->string('host_name', 60)->default('');
            $table->tinyInteger('score')->default(0);
            $table->tinyInteger('reason')->default(0);
            $table->string('last_moderation_ip', 15)->default('');
        };
    }

    /**
     * OLD Admin Migration Phase 4 — the two `nuke_config` columns poll
     * administration reads (`language`, `multilingual`). The real table
     * is a single wide settings row; only these columns are modelled.
     */
    public static function nukeConfig(): \Closure
    {
        return function (Blueprint $table) {
            $table->string('language', 100)->default('');
            $table->unsignedTinyInteger('multilingual')->default(0);
            // OLD Admin Migration Phase 7 addition — the anonymous-visitor
            // name PHP-Nuke modules record (`$anonymous`, mainfile.php:278).
            $table->string('anonymous', 40)->default('');
            // Final public features (2026-10-15) — PHP-Nuke's comment
            // settings, defaulted to the local production values.
            $table->integer('anonpost')->default(0);
            $table->integer('pollcomm')->default(1);
            $table->integer('commentlimit')->default(4096);
            // Khotab public parity (2026-10-22) — PHP-Nuke's `$sitename`, which the
            // banner embed codes quote. Defaulted.
            $table->string('sitename')->default('');
        };
    }

    /*
    |------------------------------------------------------------------
    | OLD Admin Migration Phase 7 — Mobile Downloads (`modules/Mobile`,
    | PHP-Nuke Downloads tables). Columns and defaults per the real
    | `information_schema` of olddb.
    |------------------------------------------------------------------
    */
    public static function nukeDownloadsCategories(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('cid');
            $table->string('title', 50)->default('');
            $table->text('cdescription')->default('');
            $table->integer('parentid')->default(0);
        };
    }

    public static function nukeDownloadsDownloads(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('lid');
            $table->integer('cid')->default(0);
            $table->integer('sid')->default(0);
            $table->string('title', 100)->default('');
            $table->string('url', 100)->default('');
            $table->text('description')->default('');
            $table->dateTime('date')->nullable();
            $table->string('name', 100)->default('');
            $table->string('email', 100)->default('');
            $table->integer('hits')->default(0);
            $table->string('submitter', 60)->default('');
            $table->double('downloadratingsummary')->default(0);
            $table->integer('totalvotes')->default(0);
            $table->integer('totalcomments')->default(0);
            $table->integer('filesize')->default(0);
            $table->string('version', 10)->default('');
            $table->string('homepage', 200)->default('');
        };
    }

    public static function nukeDownloadsNewdownload(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('lid');
            $table->integer('cid')->default(0);
            $table->integer('sid')->default(0);
            $table->string('title', 100)->default('');
            $table->string('url', 100)->default('');
            $table->text('description')->default('');
            $table->string('name', 100)->default('');
            $table->string('email', 100)->default('');
            $table->string('submitter', 60)->default('');
            $table->integer('filesize')->default(0);
            $table->string('version', 10)->default('');
            $table->string('homepage', 200)->default('');
        };
    }

    public static function nukeDownloadsModrequest(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('requestid');
            $table->integer('lid')->default(0);
            $table->integer('cid')->default(0);
            $table->integer('sid')->default(0);
            $table->string('title', 100)->default('');
            $table->string('url', 100)->default('');
            $table->text('description')->default('');
            $table->string('modifysubmitter', 60)->default('');
            $table->integer('brokendownload')->default(0);
            $table->string('name', 100)->default('');
            $table->string('email', 100)->default('');
            $table->integer('filesize')->default(0);
            $table->string('version', 10)->default('');
            $table->string('homepage', 200)->default('');
        };
    }

    public static function nukeDownloadsVotedata(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('ratingdbid');
            $table->integer('ratinglid')->default(0);
            $table->string('ratinguser', 60)->default('');
            $table->integer('rating')->default(0);
            $table->string('ratinghostname', 60)->default('');
            $table->text('ratingcomments')->default('');
            $table->dateTime('ratingtimestamp')->nullable();
        };
    }

    public static function nukeDownloadsEditorials(): \Closure
    {
        return function (Blueprint $table) {
            $table->integer('downloadid')->default(0)->primary();
            $table->string('adminid', 60)->default('');
            $table->dateTime('editorialtimestamp')->nullable();
            $table->text('editorialtext')->default('');
            $table->string('editorialtitle', 100)->default('');
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
            // Fatwa admin parity (2026-10-30): production's SEO columns.
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('meta_index')->default(1);
            $table->boolean('meta_follow')->default(1);
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
            // Fatwa admin parity (2026-10-30): production's SEO columns.
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('meta_index')->default(1);
            $table->boolean('meta_follow')->default(1);
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
            // Production: DOUBLE (MB) — Fatwa admin parity keeps decimals.
            $table->double('media_size')->nullable()->default(0);
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

    /**
     * OLD Admin Migration Phase 0 — the authoritative Laravel audit trail.
     * Mirrors `2026_10_12_100000_create_admin_audit_log_table.php` exactly;
     * `json` is portable to SQLite (stored as text), so no divergence.
     */
    public static function adminAuditLog(): \Closure
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('actor_admin_id');
            $table->string('actor_aid', 150);
            $table->string('action', 64);
            $table->string('module', 64);
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('created_at');
        };
    }

    /**
     * Global download capacity telemetry (2026-10-22) — the test stand-in for
     * `create_download_rejections_table` (skipped under tests, like the audit
     * log); `DownloadRejectionSchemaDriftTest` keeps the two in column
     * agreement.
     */
    public static function downloadRejections(): \Closure
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('created_at');
            $table->string('reason', 32);
            $table->string('pool', 32);
            $table->string('module', 32);
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id');
            $table->string('variant', 32);
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('requested_url', 2048);
            $table->string('resolved_download_url', 2048)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_size_source', 16);
            $table->string('client_ip', 45);
            $table->string('forwarded_for', 255)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 1024)->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('admin_id')->nullable();
            $table->boolean('is_contextual_admin')->default(false);
            $table->unsignedInteger('active_downloads');
            $table->unsignedInteger('download_limit');
            $table->unsignedSmallInteger('retry_after_seconds');
            $table->string('request_id', 36)->nullable();
        };
    }

    /**
     * The legacy audit log, for the Phase 0 compatibility-adapter tests
     * only. Columns per `includes/logs.php:226`'s own INSERT and the
     * per-module `RecordLogs()` copies — `aid`, `module`, `type`,
     * `target_id`, `action`, `extrainfo`, `mytime`, `ip`, `hidden`.
     */
    public static function nukeIslamicLogs(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('aid', 150)->nullable();
            $table->string('module')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('action')->nullable();
            $table->text('extrainfo')->nullable();
            $table->unsignedInteger('mytime')->nullable();
            $table->string('ip')->nullable();
            $table->string('hidden', 4)->nullable();
        };
    }
}
