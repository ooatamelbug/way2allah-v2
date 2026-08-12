<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * `nuke_islamic_setting` (`00-database-schema.md`) — overwhelmingly a
 * DB-backed HTML-fragment cache (`authors_list`/`channels_list` keys, not
 * ported here — superseded by `Cache::remember()` per the schema doc's own
 * migration note), but `ramadan_counter` is a genuine, narrow settings/
 * counter usage (`pages.md` §3/§4) — the only key this model serves.
 *
 * CONFIRMED FROM LEGACY SOURCE, reproduced exactly (Task 6.3 investigation
 * §6 Finding A / §13.5 — explicitly authorized as preserve-as-is, not an
 * oversight): `ramadan.php`/`ramadan1442.php`/`ramadan-archive.php` all
 * SELECT via `WHERE option='ramadan_counter' OR Id=4`, but UPDATE via
 * `WHERE option='ramadan_counter'` only (no `OR Id=4`). If the row actually
 * matched by the read were ever the `Id=4` branch rather than the `option`
 * branch, the subsequent write would silently miss it. Not normalized to a
 * single predicate here.
 *
 * The stored `value` blob's unserialized shape is confirmed inconsistent
 * across the 3 legacy files (array in `ramadan.php`/`ramadan-archive.php`,
 * object in `ramadan1442.php` — `pages.md` §5/§8, `00-blockers.md`'s
 * `ramadan_counter` entry). Reads here defensively accept either shape;
 * writes always use array shape, matching `ramadan.php` — the file the
 * Task 6.3 plan named authoritative (§13.2) — not `ramadan1442.php`'s
 * object syntax.
 */
class IslamicSetting extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_setting';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $guarded = ['Id'];

    private const RAMADAN_COUNTER_OPTION = 'ramadan_counter';

    /** `ramadan.php:50`'s `OR $table.id = 4` SELECT fallback — a legacy row-id assumption, not derived from any confirmed schema rule. */
    private const RAMADAN_COUNTER_LEGACY_FALLBACK_ID = 4;

    /** `ramadan.php:50-56`'s read + unserialize, defensive against either the array or object stored shape. */
    public static function ramadanCounters(): array
    {
        $row = static::query()
            ->where('option', self::RAMADAN_COUNTER_OPTION)
            ->orWhere('Id', self::RAMADAN_COUNTER_LEGACY_FALLBACK_ID)
            ->first();

        if ($row === null || empty($row->value)) {
            return [];
        }

        $counters = @unserialize((string) $row->value);

        if ($counters === false) {
            return [];
        }

        return is_array($counters) ? $counters : (array) $counters;
    }

    /**
     * `ramadan.php:57-64`'s increment-then-write. Returns the full,
     * updated counters array (not just the incremented year) so a caller
     * can display every other year's stored count from the same read,
     * without a second query.
     */
    public static function incrementRamadanCounter(int $year): array
    {
        $counters = static::ramadanCounters();
        $counters[$year] = ($counters[$year] ?? 0) + 1;

        static::query()
            ->where('option', self::RAMADAN_COUNTER_OPTION)
            ->update([
                'value' => serialize($counters),
                'edit_time' => time(),
            ]);

        return $counters;
    }
}
