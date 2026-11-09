<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Identity\Models\VbUser;
use App\Support\Permission\Role;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only access to the Spatie grant tables and the admin identity columns
 * (admin grant safety, owner-approved 2026-11-08). Used by Laravel admin creation
 * (refuse a new admin whose freshly allocated `nuke_authors.id` already carries
 * grants) and by `admin:audit-grants`.
 *
 * Only SELECT statements — never an insert, update, delete or sync. Grant tables
 * are read on the connection Spatie itself uses (the Role model's, i.e. the
 * default connection); admin rows on `main`; forum users on `vbulletin`.
 * Selected admin columns are limited to id, uid, aid and radminsuper — never
 * passwords, hashes, emails, permission blobs or API keys.
 */
class AdminGrantInspector
{
    public static function adminMorphClass(): string
    {
        return (new AdminUser)->getMorphClass();
    }

    public static function forumMorphClass(): string
    {
        return (new VbUser)->getMorphClass();
    }

    public function grantConnection(): Connection
    {
        return DB::connection((new Role)->getConnectionName());
    }

    /** @return array{roles: int, permissions: int} grants already stored under this admin id */
    public function grantCountsFor(int $adminId): array
    {
        $grants = $this->grantConnection();
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        return [
            'roles' => $grants->table((string) config('permission.table_names.model_has_roles'))
                ->where('model_type', self::adminMorphClass())->where($morphKey, $adminId)->count(),
            'permissions' => $grants->table((string) config('permission.table_names.model_has_permissions'))
                ->where('model_type', self::adminMorphClass())->where($morphKey, $adminId)->count(),
        ];
    }

    /** @return list<array{id: int, uid: int, aid: string, radminsuper: int}> */
    public function admins(): array
    {
        return DB::connection('main')->table('nuke_authors')->orderBy('id')
            ->get(['id', 'uid', 'aid', 'radminsuper'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'uid' => (int) ($r->uid ?? 0), 'aid' => (string) ($r->aid ?? ''), 'radminsuper' => (int) ($r->radminsuper ?? 0)])
            ->all();
    }

    /**
     * @param  'roles'|'permissions'  $kind
     * @return list<array{model_type: string, model_id: int, name: string, guard: string}>
     */
    public function assignments(string $kind): array
    {
        $grants = $this->grantConnection();
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $pivot = (string) config($kind === 'roles' ? 'permission.table_names.model_has_roles' : 'permission.table_names.model_has_permissions');
        $target = (string) config($kind === 'roles' ? 'permission.table_names.roles' : 'permission.table_names.permissions');
        $foreign = $kind === 'roles'
            ? ((string) config('permission.column_names.role_pivot_key') ?: 'role_id')
            : ((string) config('permission.column_names.permission_pivot_key') ?: 'permission_id');

        return $grants->table($pivot.' as p')
            ->leftJoin($target.' as t', 't.id', '=', 'p.'.$foreign)
            ->orderBy('p.'.$morphKey)
            ->get(['p.model_type', 'p.'.$morphKey.' as model_id', 't.name', 't.guard_name'])
            ->map(fn ($r) => ['model_type' => (string) $r->model_type, 'model_id' => (int) $r->model_id, 'name' => (string) ($r->name ?? ''), 'guard' => (string) ($r->guard_name ?? '')])
            ->all();
    }

    /**
     * Duplicate usernames as the database itself compares them (the `aid` column's collation decides case /
     * accent / trailing-space equality — not PHP).
     *
     * @return list<array{aid: string, count: int}>
     */
    public function duplicateUsernames(): array
    {
        return DB::connection('main')->table('nuke_authors')
            ->select('aid', DB::raw('COUNT(*) AS c'))->groupBy('aid')->havingRaw('COUNT(*) > 1')->orderBy('aid')->get()
            ->map(fn ($r) => ['aid' => (string) $r->aid, 'count' => (int) $r->c])->all();
    }

    /** @return list<array{uid: int, count: int}> */
    public function duplicateForumIds(): array
    {
        return DB::connection('main')->table('nuke_authors')->where('uid', '>', 0)
            ->select('uid', DB::raw('COUNT(*) AS c'))->groupBy('uid')->havingRaw('COUNT(*) > 1')->orderBy('uid')->get()
            ->map(fn ($r) => ['uid' => (int) $r->uid, 'count' => (int) $r->c])->all();
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int> the given forum user ids that exist
     */
    public function existingForumUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::connection('vbulletin')->table('user')->whereIn('userid', $userIds)->pluck('userid')->map(fn ($v) => (int) $v)->all();
    }
}
