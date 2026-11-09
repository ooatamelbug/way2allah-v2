<?php

namespace App\Support\Downloads;

use Illuminate\Database\Eloquent\Model;

/**
 * One download refused because the global capacity pool was full — see
 * DownloadRejectionRecorder (which writes the rows with the query builder, so
 * this model is for reading them back). `created_at` is a unix timestamp, as
 * in `admin_audit_log`.
 */
class DownloadRejection extends Model
{
    protected $connection = 'main';

    protected $table = DownloadRejectionRecorder::TABLE;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_contextual_admin' => 'boolean',
        ];
    }
}
