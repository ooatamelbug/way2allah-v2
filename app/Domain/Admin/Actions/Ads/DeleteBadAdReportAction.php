<?php

namespace App\Domain\Admin\Actions\Ads;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Support\Facades\DB;

/**
 * F01 / B6b — OLD `w2a_del_bad_ads()` (`modules/report_ads/admin/index.php`): `DELETE FROM
 * nuke_report_for_bad_ads WHERE id = {$_GET['id']}` from a GET link (injectable, no CSRF). Here: DELETE + CSRF,
 * re-authorized, audited.
 *
 * The audit keeps the report's date and URL (markup redacted) and only a SHA-256 fingerprint of the reporter's
 * e-mail — personal data is not copied into the audit log.
 */
final class DeleteBadAdReportAction
{
    public const PERMISSION = 'report_ads.managereports';

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, int $id, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, self::PERMISSION);

        DB::connection('main')->transaction(function () use ($actor, $id, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_report_for_bad_ads')->where('id', $id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $main->table('nuke_report_for_bad_ads')->where('id', $row->id)->delete();

            $url = (string) $row->ads_url;
            $this->audit->record($actor, 'del_bad_ad_report', 'report_ads', 'bad_ad_report', (int) $row->id, [
                'deleted' => [
                    'ads_url' => preg_match('/<[a-z!\/][^>]*>/i', $url) === 1 ? ['redacted' => 'markup', 'length' => mb_strlen($url)] : $url,
                    'user_email_sha256' => $row->user_email === null ? null : hash('sha256', mb_strtolower(trim((string) $row->user_email))),
                    'db_creation_date' => $row->db_creation_date,
                ],
            ], $ip);
        });
    }
}
