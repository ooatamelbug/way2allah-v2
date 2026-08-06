<?php

namespace Tests\Support\Fixtures;

use Illuminate\Database\Schema\Blueprint;

/**
 * Canonical `vbulletin`-connection table fixtures — single source of truth
 * so VbulletinSessionGuardTest and RoleSeederTest (both faking `user`)
 * can't silently diverge from each other.
 */
class VbulletinSchema
{
    public static function user(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedBigInteger('userid')->primary();
            $table->string('password')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('posts')->default(0);
            $table->unsignedInteger('avatarid')->nullable();
            $table->string('membergroupids')->nullable();
            $table->unsignedInteger('usergroupid')->nullable();
            $table->unsignedInteger('joindate')->nullable();
            $table->unsignedInteger('lastactivity')->nullable();
            $table->unsignedInteger('lastpost')->nullable();
            $table->string('usertitle')->nullable();
        };
    }

    public static function session(): \Closure
    {
        return function (Blueprint $table) {
            $table->string('sessionhash')->primary();
            $table->unsignedBigInteger('userid');
            $table->string('idhash');
            $table->unsignedInteger('lastactivity');
        };
    }

    /** Wave 5, task 5.5 — used to resolve chat-room owner/speaker display names. */
    public static function avatar(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('avatarid')->primary();
            $table->string('title')->nullable();
            $table->string('avatarpath')->nullable();
        };
    }

    /** Wave 5, task 5.1 — `add_survey.php`'s audience-targeting groups list. */
    public static function usergroup(): \Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('usergroupid')->primary();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
        };
    }
}
