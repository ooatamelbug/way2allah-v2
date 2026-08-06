<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * `functions.php`'s `get_option()`/`update_option()` — a WordPress-style
 * key/value settings table (`nuke_options`: `option_name`, `option_value`).
 * Roadmap task 5.4's `soundcloud`/`youtube` embed settings both live here.
 * Legacy `update_option()` is UPDATE-only (no upsert) — the row must
 * already exist; reproduced via `updateOrCreate()` here instead, since a
 * missing row silently no-op'ing on save would be a worse Laravel-side
 * behavior than the legacy assumption, not a faithful port of it.
 *
 * `option_name` is the only column either legacy function ever queries or
 * filters by (`functions.php:566,612`) — no `option_id` reference found
 * anywhere in the codebase, so it is used directly as the primary key
 * here rather than assuming an unconfirmed WordPress-style surrogate id.
 */
class SiteOption extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_options';

    protected $primaryKey = 'option_name';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public static function get(string $name, mixed $default = null): mixed
    {
        return static::where('option_name', $name)->value('option_value') ?? $default;
    }

    public static function put(string $name, mixed $value): void
    {
        static::updateOrCreate(['option_name' => $name], ['option_value' => $value]);
    }
}
