<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * F02 — one row per administrator ever deactivated (`admin_account_states`,
 * on `main`). `deactivated_at` non-NULL means deactivated; reactivation
 * clears it and keeps the row as history. No row means active.
 *
 * @property int $admin_id
 * @property int|null $deactivated_at
 * @property int|null $deactivated_by
 * @property string|null $reason
 * @property int|null $reactivated_at
 * @property int|null $reactivated_by
 * @property int $updated_at
 */
class AdminAccountState extends Model
{
    protected $connection = 'main';

    protected $table = 'admin_account_states';

    protected $primaryKey = 'admin_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
