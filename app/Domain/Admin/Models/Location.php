<?php

namespace App\Domain\Admin\Models;

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
}
