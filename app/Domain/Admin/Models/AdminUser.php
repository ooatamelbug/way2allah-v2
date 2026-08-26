<?php

namespace App\Domain\Admin\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

/**
 * Backed by the legacy `nuke_authors` table (ADR-0011, Blueprint v1.0 §9).
 * Separate identity from VbUser — admin auth is a genuinely different system
 * from public-site auth today and is deliberately not unified with it.
 *
 * Column shape confirmed from admincp/index.php's own login query: id, uid,
 * aid, name, email, pwd, password, thumb, admlanguage, radminsuper,
 * permissions. `password` takes priority over the older `pwd` column when
 * both are present (same priority order as the legacy login code).
 *
 * `API` (Task 6.8 addition) — the 32-character key `backup.php:56-58,65`
 * compares against a request's `APIKey` field. Confirmed column, not
 * previously modeled since nothing before Task 6.8 read it.
 *
 * @property int $id
 * @property int|null $uid
 * @property string $aid
 * @property string|null $name
 * @property string|null $email
 * @property string|null $pwd
 * @property string|null $password
 * @property string|null $thumb
 * @property string|null $admlanguage
 * @property bool $radminsuper
 * @property string|null $API
 *
 * `permissions` is deliberately NOT listed as an `@property` above, even
 * though `nuke_authors.permissions` is a real, always-selected column
 * (the legacy serialized-permissions blob `backupCategoryPermissions()`
 * and the old admin sidebar read). It shares its name with Spatie's own
 * `permissions()` `BelongsToMany` relation (`HasPermissions` trait), and
 * Eloquent's attribute resolution checks loaded column attributes BEFORE
 * relation methods — so on any row where this column is actually
 * populated (every real row; only this project's own deliberately-trimmed
 * `MainSchema::nukeAuthors()` test fixture omits it), `$this->permissions`
 * silently returned the raw legacy string instead of Spatie's permission
 * Collection. Several Spatie methods access the relation this exact
 * property-style way internally (`getPermissionNames()`,
 * `getAllPermissions()`, `givePermissionTo()`, `getDirectPermissions()`)
 * — all of them broke on real data (`AdminCP Permissions Crash` finding,
 * reproduced via `tinker` before this fix, not guessed). `getAttribute()`
 * below is overridden for this one key only, forcing property-style
 * access to always resolve the real Spatie relation — the same outcome
 * `$this->permissions()` (method call) already gave safely. This does
 * NOT touch the database, the column, or Spatie's own vendor code — the
 * raw legacy blob is still fully present in storage and still readable
 * via `getRawOriginal('permissions')` (used nowhere in current Laravel
 * code — grepped fresh; `PermissionController` is the one real,
 * Spatie-backed replacement for all 5 legacy permission-editor copies,
 * per its own docblock, and no other current code reads this column).
 */
class AdminUser extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasRoles;

    protected $guard_name = 'admin';

    protected $connection = 'main';

    protected $table = 'nuke_authors';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['pwd', 'password'];

    protected function casts(): array
    {
        return [
            'radminsuper' => 'boolean',
        ];
    }

    /**
     * The stored-hash column actually in use for this row, mirroring the
     * legacy `password` > `pwd` priority (admincp/index.php).
     */
    public function currentStoredPassword(): string
    {
        return (string) ($this->getAttribute('password') ?: $this->getAttribute('pwd') ?: '');
    }

    /**
     * `permissions` attribute/relationship collision fix — see the class
     * docblock. Every OTHER attribute keeps Eloquent's normal resolution
     * order; only this one key is redirected to the relation.
     */
    public function getAttribute($key)
    {
        if ($key === 'permissions') {
            return $this->getRelationValue('permissions');
        }

        return parent::getAttribute($key);
    }
}
