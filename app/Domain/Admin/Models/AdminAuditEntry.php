<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OLD Admin Migration Phase 0 — one administration mutation, recorded.
 *
 * Lives on `main` with the content it describes, so an audit write can
 * join the same transaction as the mutation (see the migration's docblock
 * for why that placement is deliberate and why it differs from Spatie's).
 *
 * `$timestamps = false` with an explicit integer `created_at`: the legacy
 * trail stores `mytime` as a unix integer and the moderator-log views
 * compare against it, so an entry's time stays directly comparable with
 * legacy rows during coexistence. Written by `AdminAuditRecorder`, which
 * is the only thing that should create these — nothing updates or deletes
 * them.
 *
 * @property int $id
 * @property int $actor_admin_id
 * @property string $actor_aid
 * @property string $action
 * @property string $module
 * @property string $entity_type
 * @property int $entity_id
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip
 * @property int $created_at
 */
class AdminAuditEntry extends Model
{
    protected $connection = 'main';

    protected $table = 'admin_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'actor_admin_id',
        'actor_aid',
        'action',
        'module',
        'entity_type',
        'entity_id',
        'metadata',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'actor_admin_id' => 'integer',
        'entity_id' => 'integer',
        'metadata' => 'array',
        'created_at' => 'integer',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'actor_admin_id');
    }
}
