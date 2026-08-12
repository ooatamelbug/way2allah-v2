<?php

namespace App\Domain\Content\Support;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * `backup.php:52-108`'s auth gate, ported exactly. Three confirmed,
 * sequential checks, each with its own distinct raw-text failure body
 * (all HTTP 200 — legacy never calls `header()` to change the status
 * code, every `die()` response is 200 OK regardless of outcome):
 *
 * 1. `strlen($APIKey) <> 32` -> "API Error 1:{len}" (`backup.php:52-55`).
 * 2. No `nuke_authors` row for `uid=$ID`, or its `API` column doesn't
 *    match -> "API Error 2" (`backup.php:56-67`). The legacy comparison
 *    is loose (`<>`), preserved exactly, not tightened to `!==`.
 * 3. Not `radminsuper` AND not named in `nuke_modules.admins` (a raw,
 *    UN-trimmed comma-separated list; loose `==` string comparison;
 *    empty list never matches even for an exact-empty-string coincidence)
 *    for the `title='BackUp'` row -> "Error!" (`backup.php:73-91`).
 */
class BackupApiAuthenticator
{
    public function authenticate(Request $request): BackupApiAuthResult
    {
        $apiKey = trim((string) $request->input('APIKey', ''));

        if (strlen($apiKey) !== 32) {
            return BackupApiAuthResult::error('API Error 1:'.strlen($apiKey));
        }

        $uid = (int) $request->input('ID');
        $admin = AdminUser::query()->where('uid', $uid)->first();

        if ($admin === null || $apiKey != (string) $admin->API) {
            return BackupApiAuthResult::error('API Error 2');
        }

        if (! $this->isAuthorizedForBackupModule($admin)) {
            return BackupApiAuthResult::error('Error!');
        }

        return BackupApiAuthResult::ok($admin);
    }

    /**
     * `backup.php:94-104`'s `unserialize($authors['permissions'])['backup']`
     * flags — read directly from the raw `nuke_authors.permissions`
     * serialized column, exactly as legacy does.
     *
     * **Correction (KhotabUpdateBackup implementation round):** an earlier
     * version of this method read the already-migrated Spatie `backup.*`
     * permissions (Wave 5, `AdminPermissionSeeder`) instead. Those Spatie
     * permissions are sourced from `admincp/backup/menu.php` — the
     * ADMIN-PANEL UI's own authorization gate, a genuinely different,
     * unrelated permission surface from what `backup.php` itself reads.
     * Since this method was never actually consumed by any implemented
     * operation until now (`get`'s category gating, below), the Spatie
     * version's mismatch to the real legacy source was untested. Corrected
     * here, before its first real use, to read the actual column
     * `UpdateBackup()` reads — not a "fix" to legacy behavior, a fix to
     * this port's own prior placeholder.
     *
     * **Confirmed dead flag, reproduced not "fixed":** `backupallsite`
     * (`'allsite'` below) is computed by legacy (`backup.php:98`, `$All=1`)
     * but `$All` is never consulted by any of `UpdateBackup()`'s per-mode
     * gates — turning this permission "on" does NOT unlock every category,
     * despite the name. Exposed here for completeness only; `get()` below
     * must NOT use it to bypass the other 5 flags.
     *
     * **Read via a direct query, NOT `$admin->permissions`.** `nuke_authors`
     * genuinely has a `permissions` column in production, and `AdminUser`
     * uses Spatie's `HasRoles`/`HasPermissions` traits, which define their
     * own `permissions()` BelongsToMany relation with the same name.
     * Eloquent's attribute resolution checks loaded attributes before
     * relations (`HasAttributes::getAttribute()`) — so the instant this
     * column is included in any `AdminUser` query's result set, the magic
     * `$admin->permissions` property silently returns the raw column
     * instead of Spatie's relation, breaking any code that legitimately
     * needs the relation (e.g. `PermissionController`'s
     * `getPermissionNames()`) for that same loaded instance. Discovered
     * this round, not previously documented anywhere. A direct query here
     * avoids relying on the ambiguous property at all; it does not fix the
     * underlying collision, which remains latent wherever else an
     * `AdminUser` row might be loaded with this column selected — flagged
     * in this round's report, not resolved here (`AdminUser.php`/
     * `PermissionController.php` are outside this task's scope).
     *
     * @return array<string, bool>
     */
    public function backupCategoryPermissions(AdminUser $admin): array
    {
        $raw = DB::connection('main')->table('nuke_authors')
            ->where($admin->getKeyName(), $admin->getKey())
            ->value('permissions');

        $permissions = @unserialize((string) $raw);
        $backup = is_array($permissions) ? ($permissions['backup'] ?? []) : [];

        return [
            'allsite' => ($backup['backupallsite'] ?? null) === 'on',
            'backupkhotab' => ($backup['backupkhotab'] ?? null) === 'on',
            'backupkhotabmirror' => ($backup['backupkhotabmirror'] ?? null) === 'on',
            'backuptelawah' => ($backup['backuptelawah'] ?? null) === 'on',
            'backupanasheed' => ($backup['backupanasheed'] ?? null) === 'on',
            'backupanasheedmirror' => ($backup['backupanasheedmirror'] ?? null) === 'on',
        ];
    }

    private function isAuthorizedForBackupModule(AdminUser $admin): bool
    {
        if ($admin->radminsuper) {
            return true;
        }

        $modulesAdmins = DB::connection('main')->table('nuke_modules')
            ->where('title', 'BackUp')
            ->value('admins');

        if ($modulesAdmins === null || $modulesAdmins === '') {
            return false;
        }

        foreach (explode(',', (string) $modulesAdmins) as $candidate) {
            if ($admin->name == $candidate) {
                return true;
            }
        }

        return false;
    }
}
