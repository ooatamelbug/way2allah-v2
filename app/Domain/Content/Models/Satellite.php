<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps to `nuke_sat_sats` (Roadmap task 3.1). Confirmed schema (Fact, per
 * live-stream.md §4, pulled from a real CREATE TABLE this round's audit
 * inspected): id, title, des, pos, channels, time. Only `title`/`pos` are
 * read anywhere in the 4 live-stream files this port is grounded in
 * (`live-stream/functions.php:80`, `live_channel_details()`), but the full
 * confirmed column set is mapped for the same reason Channel's is.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $des
 * @property string|null $pos
 * @property int|null $channels
 * @property int|null $time
 */
class Satellite extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_sat_sats';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
