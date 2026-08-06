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
            $table->unsignedInteger('mirror')->default(0);
            $table->unsignedInteger('location_id')->nullable();
            $table->string('uploader')->nullable();
            $table->unsignedInteger('addeddate')->nullable();
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
}
