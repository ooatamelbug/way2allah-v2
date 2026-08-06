<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Models\Concerns\TracksViews;
use App\Domain\Content\Models\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single, shared model over `nuke_sat_channels` (Blueprint v1.0 §4/§6),
 * replacing 3 independent per-module treatments in `khotab`, `live-stream`,
 * and `channels`. Referenced by ID from KhotabItem/Series — never owned by
 * or nested inside another aggregate (§6's shared-reference rule).
 *
 * Column list confirmed from 00-database-schema.md's `nuke_sat_channels`
 * entry (Fact, from prior schema inspection): id, title, notes, programs,
 * time, freq, srate, fec, polar, enc, beam, sat_id, active, khotab,
 * anasheed, streamcode, ch_visits. No secondary indexes, no foreign keys,
 * no created_at/updated_at columns.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $notes
 * @property string|null $programs
 * @property int|null $time
 * @property string|null $freq
 * @property string|null $srate
 * @property string|null $fec
 * @property string|null $polar
 * @property string|null $enc
 * @property int|null $beam
 * @property int|null $sat_id
 * @property int|null $active
 * @property int|null $khotab
 * @property int|null $anasheed
 * @property string|null $streamcode
 * @property int $ch_visits
 * @property-read \App\Domain\Content\Models\Satellite|null $satellite
 *
 * Deliberately no hasMany(Series::class)/hasMany(KhotabItem::class) yet —
 * those models don't exist until Wave 4 (Roadmap task 4.1). Adding a
 * relationship method that references a not-yet-built class would be
 * exactly the kind of premature, unverifiable scaffolding the Wave 0
 * completion review found none of — the relationship is added alongside
 * Series/KhotabItem themselves in Wave 4, not stubbed out ahead of them.
 *
 * TWO PRESERVED BEHAVIORS THAT COULD EASILY BE MISTAKEN FOR BUGS
 * (Wave 3, live-stream.md §5 + direct read of live-stream/functions.php):
 *
 * 1. `active = 0` means eligible/live, not disabled. Every legacy query
 *    selects live-eligible channels via `WHERE active = 0`, never `= 1` —
 *    the column name reads as a normal boolean-flag ("active" = usable),
 *    but the actual stored semantics are inverted from that expectation.
 *    scopeEligibleForLiveStream() below encodes this deliberately rather
 *    than leaving every future caller to rediscover it by reading raw SQL.
 * 2. `streamcode` is trusted, pre-authored raw HTML/JS (an embed
 *    snippet), not user input — it MUST be rendered with Blade's `{!! !!}`
 *    unescaped-output syntax, never `{{ }}`. Auto-escaping it would break
 *    every channel's live embed. This is safe only because it is
 *    admin-authored, not public-submitted (functions.php:66,
 *    live_channel_script()) — if this column is ever exposed to a
 *    lower-trust role, this assumption must be revisited.
 */
class Channel extends Model implements Viewable
{
    use TracksViews;

    protected $connection = 'main';

    protected $table = 'nuke_sat_channels';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // Confirmed tinyint (00-database-schema.md: "beam (tinyint) —
            // used directly as an images/beams/<beam>.png filename").
            // Every other column's exact type is not yet confirmed at
            // this precision, so is deliberately left uncast rather than
            // guessed.
            'beam' => 'integer',
        ];
    }

    public function satellite(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Satellite::class, 'sat_id');
    }

    /**
     * `WHERE active = 0 AND streamcode IS NOT NULL AND streamcode <> ''`
     * — the eligibility filter used by list_live_channels() and
     * most_viewed_channels() (live-stream/functions.php:6,44). NOT used
     * by live-channel.php's or live.php's direct single-channel lookup,
     * which check only `active = 0 AND id = ?` — a real, confirmed
     * difference (IF-009) between "listed in the directory" and
     * "directly viewable by URL", preserved as found rather than
     * unified.
     */
    public function scopeEligibleForLiveStream(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('active', 0)
            ->whereNotNull('streamcode')
            ->where('streamcode', '<>', '');
    }

    /**
     * live-stream/functions.php:79 — `$channel->beam = (empty($channel->beam))
     * ? 1 : $channel->beam;` immediately before building the coverage-map
     * image path. Kept as a dedicated accessor rather than overriding the
     * raw `beam` attribute, so code that legitimately wants the stored
     * (possibly empty) value isn't silently rewritten.
     */
    public function beamForDisplay(): int
    {
        return $this->beam ?: 1;
    }

    /**
     * live-channel.php:37 increments `ch_visits`, not `hits` — the first
     * confirmed exception to P-014's column naming (see
     * TracksViews::viewCountColumn()'s own docblock).
     */
    public function viewCountColumn(): string
    {
        return 'ch_visits';
    }

    /** `nuke_sat_channels` has no `lastvisit` column at all. */
    public function tracksLastVisit(): bool
    {
        return false;
    }
}
