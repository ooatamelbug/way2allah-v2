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
}
