<?php

namespace App\Domain\Admin\Actions\Staff;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminDeactivation;
use App\Domain\Admin\Support\AdminManagementPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Edit an administrator's profile (OLD `mod_authors` → `modifyadmin` / `UpdateAuthor`): email and URL.
 *
 * Like OLD, the name is not editable after creation. The nickname (`aid`, OLD-editable) is NOT changed here: it is the
 * login identifier and the attribution key of the legacy logs, so a rename touches shared history (owner decision,
 * reported). Audited with the changed field names only (no values).
 */
final class UpdateAdminProfileAction
{
    public function __construct(private readonly AdminAuditRecorder $audit) {}

    public function execute(AdminUser $actor, AdminUser $target, string $email, string $url, ?string $ip = null): void
    {
        if (($refusal = AdminManagementPolicy::manageRefusal($actor, $target)) !== null) {
            throw new AdminManagementRefused($refusal);
        }
        if (AdminDeactivation::isDeactivated((int) $target->getKey())) {
            throw new AdminManagementRefused('هذا المشرف معطّل؛ أعد تفعيله أولاً.');
        }

        DB::connection('main')->transaction(function () use ($actor, $target, $email, $url, $ip) {
            /** @var AdminUser $locked */
            $locked = AdminUser::on('main')->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $changed = [];
            if ((string) $locked->email !== $email) {
                $changed[] = 'email';
            }
            if ((string) $locked->getAttribute('url') !== $url) {
                $changed[] = 'url';
            }
            if ($changed === []) {
                return;
            }
            $locked->forceFill(['email' => $email, 'url' => $url])->save();

            $this->audit->record($actor, 'update_admin_profile', 'authors', 'admin', (int) $locked->getKey(), [
                'target_aid' => (string) $locked->aid,
                'changed' => $changed,
            ], $ip);
        });
    }
}
