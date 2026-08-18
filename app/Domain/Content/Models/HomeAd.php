<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * G-02 (Migration Gap Register): `nuke_ads` — legacy `home_functions.php`'s
 * `show_ads_byposition()`/`switch_on_ads_type()`/`hide_ads()`. No existing
 * Laravel model or controller covers this table (confirmed by repository
 * search during the Homepage Implementation Blueprint's infrastructure
 * inspection) — genuinely new.
 *
 * Column list confirmed via `Schema::getColumnListing('nuke_ads')` against
 * `olddb`: id, name, image_path, position, percentage, type,
 * ads_show_type, required_num_view, show, link, startdate, enddate,
 * num_view, num_click, path_type.
 *
 * `show` is a real column name that collides with the MySQL reserved
 * word — every query against it must use the query builder's automatic
 * identifier quoting (plain Eloquent attribute access is safe; raw SQL
 * fragments are not).
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $image_path
 * @property int $position
 * @property int|null $percentage
 * @property int $type
 * @property string|null $ads_show_type
 * @property int|null $required_num_view
 * @property int $show
 * @property string|null $link
 * @property string|null $startdate
 * @property string|null $enddate
 * @property int $num_view
 * @property int|null $num_click
 * @property string|null $path_type
 */
class HomeAd extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_ads';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
