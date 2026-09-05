<?php

namespace App\Domain\Admin\Models;

use App\Domain\Content\Support\MediaPathResolver;
use App\Domain\Content\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

/**
 * `nuke_islamic_locations` (mosque/dawah-location directory) —
 * Roadmap task 5.4. `count` blocks deletion when > 0 (`index.php:26`,
 * referencing-item count) — enforced in `LocationsController::destroy()`,
 * not here (a model-level guard would also block legitimate seed/test
 * setup that doesn't go through the controller).
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $des
 * @property string|null $address
 * @property string|null $country
 * @property string|null $lng
 * @property string|null $lat
 * @property string|null $googlemap
 * @property int $type
 * @property int $hidden
 * @property int $count
 */
class Location extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_locations';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * `chat_room/alhedaya_room.php:34-39` computes exactly this bucketed
     * path (`MediaPathResolver`'s own convention) then immediately
     * overwrites it with a hardcoded `archive.org/.../hidaya11.png` image —
     * confirmed dead/overridden code, specific to that file's own
     * location=10 hardcoding, not a real per-location convention (IF —
     * decided explicitly, not silently: use the computed path + its own
     * `no_location_image.png` fallback, matching `Author::fallbackImageUrl()`'s
     * already-established precedent, not the dead override).
     */
    public function photoUrl(): string
    {
        $path = MediaPathResolver::path('locations', $this->id, 'jpg');

        return file_exists(public_path($path))
            ? MediaUrl::asset($path)
            : MediaUrl::asset('locations/no_location_image.png');
    }
}
