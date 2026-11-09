<?php

namespace App\Domain\Content\Models\Mobile;

use Illuminate\Database\Eloquent\Model;

/**
 * OLD Admin Migration Phase 7 — the staff review of a material
 * (`nuke_downloads_editorials`, at most one per material, keyed by it).
 *
 * @property int $downloadid
 * @property string $adminid
 * @property string|null $editorialtimestamp
 * @property string $editorialtext
 * @property string $editorialtitle
 */
class MobileEditorial extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_downloads_editorials';

    protected $primaryKey = 'downloadid';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
