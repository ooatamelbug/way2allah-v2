<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\FatwaQuestion;
use Illuminate\Support\Facades\DB;

/**
 * A5 «حذف» beside each fatwa on `fatawa-all-{id}` — OLD
 * `w2a_fatwa_del_question` (`modules/Fatwa/admin/index.php`:313-347):
 * after OLD's confirmation page, `DELETE` the fatwa row — the fatwa itself,
 * whichever questions it belongs to (A3 is the unlink). Phase 1 found no
 * fatwa mirror / comment table depending on it, so nothing else is removed.
 *
 * Owner-approved correction: the sheikh's `fatwa` counter is recounted
 * (AuthorFatwaCounter). Also: authorized (`fatwa.manage`); one transaction
 * with the row locked; audited.
 */
final class DeleteFatwaAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return array{general_question_id: string} */
    public function execute(AdminUser $actor, FatwaQuestion $fatwa, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $fatwa);

        return DB::connection('main')->transaction(function () use ($actor, $fatwa, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_fatwa_questions')->where('id', $fatwa->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $main->table('nuke_fatwa_questions')->where('id', $row->id)->delete();
            $counter = AuthorFatwaCounter::recount([(int) $row->auther_id]);

            $this->audit->record($actor, 'delete_fatwa', 'fatwa', 'fatwa', (int) $row->id, [
                'general_question_id' => (string) $row->general_question_id,
                'topic_id' => (int) $row->topic_id,
                'author_id' => (int) $row->auther_id,
                'question_text' => AuditText::digest((string) $row->question_text),
                'author_fatwa_count' => $counter,
            ], $ip);

            return ['general_question_id' => (string) $row->general_question_id];
        });
    }
}
