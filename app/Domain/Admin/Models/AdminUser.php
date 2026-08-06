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
}
