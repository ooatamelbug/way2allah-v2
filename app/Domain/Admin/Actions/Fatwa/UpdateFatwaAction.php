<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\FatwaQuestion;
use Illuminate\Support\Facades\DB;

/**
 * A4 «تعديل» beside each fatwa on `fatawa-all-{id}` — OLD
 * `w2a_fatwa_edit_question` (`modules/Fatwa/admin/index.php`:209-311,
 * production-hash-verified).
 *
 * OLD wrote only the columns that changed: question_text, auther_id,
 * answer_text, media_type, media_link, media_size, date_of_fatwa,
 * channel_id — and always `topic_id`, from the URL. The general questions
 * are not part of the form and are never written.
 *
 * Owner-approved corrections:
 * - **topic_id is recomputed server-side** from the stored questions — the
 *   same value OLD computed into the URL (FatwaFilingTopic);
 * - **place_of_fatwa is preserved** (not on the form; OLD could clear it);
 * - decimal media_size, an unchanged size or date not rewritten, a cleared
 *   date stored as NULL (FatwaFields);
 * - **a sheikh change recounts both sheikhs** (AuthorFatwaCounter).
 *
 * Also: authorized (`fatwa.manage`); the sheikh and channel must exist;
 * bound SQL; one transaction with the row locked; audited.
 */
final class UpdateFatwaAction
{
    private const TEXT = ['question_text', 'answer_text'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data the validated form, OLD field names */
    public function execute(AdminUser $actor, FatwaQuestion $fatwa, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $fatwa);

        DB::connection('main')->transaction(function () use ($actor, $fatwa, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_fatwa_questions')->where('id', $fatwa->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $after = FatwaFields::columns($data, $row);
            $after['topic_id'] = FatwaFilingTopic::forQuestions(PipeIdList::parse($row->general_question_id));

            $changed = [];
            $before = [];
            // As OLD: only the columns whose value changed are written.
            foreach ($after as $column => $value) {
                $old = $row->{$column};
                $same = $value === null ? $old === null : $old !== null && (string) $value === (string) $old;
                if (! $same) {
                    $changed[$column] = $value;
                    $before[$column] = $old;
                }
            }

            if ($changed !== []) {
                $main->table('nuke_fatwa_questions')->where('id', $row->id)->update($changed);
            }

            $oldAuthor = (int) $row->auther_id;
            $newAuthor = (int) $after['auther_id'];
            $counter = $oldAuthor !== $newAuthor ? AuthorFatwaCounter::recount([$oldAuthor, $newAuthor]) : [];

            $this->audit->record($actor, 'edit_fatwa', 'fatwa', 'fatwa', (int) $row->id, [
                'general_question_id' => (string) $row->general_question_id,
                'changes' => AuditText::changes($before, $changed, self::TEXT),
                'author_fatwa_count' => $counter,
            ], $ip);
        });
    }
}
