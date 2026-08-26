<?php

namespace App\Domain\Identity\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use LogicException;
use Spatie\Permission\Traits\HasRoles;

/**
 * Read-only projection over vBulletin's own `user` table (ADR-0011, Blueprint
 * v1.0 §5/§9). This application does not own vBulletin's user data and never
 * writes back to it — the model exists so the vbulletin-session Guard, and
 * Spatie's HasRoles trait, have a concrete Eloquent model to resolve to and
 * attach roles/permissions to.
 *
 * HasRoles' pivot tables (model_has_roles etc.) are new, Laravel-owned
 * tables keyed by this model's class + userid — they require no write
 * access back to vBulletin's database, and are unaffected by save()/
 * delete() being blocked below.
 *
 * Implements both Authorizable (Spatie's Gate::before hook requires it) and
 * Authenticatable. The latter was deliberately *not* implemented originally
 * — the reasoning at the time was "resolved via cookies, not Laravel's
 * standard credential login flow" — but PHPStan (wired up pre-Wave-4,
 * decision #3) surfaced why that reasoning was incomplete: VbulletinSessionGuard
 * implements the `Guard` *contract*, whose `user()` method and GuardHelpers'
 * internal `$user` property are typed against `Authenticatable` regardless
 * of how a Guard resolves its user — cookie-based resolution and contract
 * conformance are separate concerns. Without it, framework Guard
 * conveniences that assume `Authenticatable` (e.g. `getAuthIdentifier()`)
 * were a latent gap, not just a type-checker complaint. `AdminUser` already
 * does the same via the same trait; this brings VbUser in line with it.
 *
 * @property int $userid
 * @property string $password
 * @property string|null $username
 * @property string|null $email
 * @property int $posts
 * @property int|null $avatarid
 * @property string|null $membergroupids
 * @property int|null $usergroupid
 * @property int|null $joindate
 * @property int|null $lastactivity
 * @property int|null $lastpost
 * @property string|null $usertitle
 *
 * The original two columns were declared because VbulletinSessionGuard
 * reads them; the rest were added for `PermissionController::edit()`'s
 * profile/stats portlets (AdminCP Final Page-Level Visual-Parity Closure,
 * 2026-08-22) — confirmed by legacy `authors/edit_author.php` reading
 * these exact column names unconditionally (no fallback/isset guards),
 * and already independently modeled in `tests/Support/Fixtures/
 * VbulletinSchema::user()` before this change. Beyond this set, vBulletin's
 * `user` table schema still hasn't been read/confirmed by this migration
 * (ADR-0002: vendor internals are documented at integration-surface depth
 * only) — do not add further @property entries without that same
 * evidence bar.
 */
class VbUser extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use HasRoles;

    protected $guard_name = 'vbulletin';

    protected $connection = 'vbulletin';

    protected $table = 'user';

    protected $primaryKey = 'userid';

    public $incrementing = true;

    public $timestamps = false;

    protected $guarded = ['*'];

    /**
     * This model is a read-only projection — vBulletin's database is owned
     * and written to by vBulletin itself, never by this application.
     */
    public function save(array $options = []): bool
    {
        throw new LogicException(self::class.' is read-only and must never be saved back to vBulletin\'s database.');
    }

    public function delete(): ?bool
    {
        throw new LogicException(self::class.' is read-only and must never be deleted via this application.');
    }
}
