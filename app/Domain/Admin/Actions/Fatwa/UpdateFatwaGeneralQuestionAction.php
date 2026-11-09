<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\FatwaGeneralQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Q2 «تعديل السؤال» on `fatawa-all-{id}` — OLD `fatwa_edit_general_question`
 * (`modules/Fatwa/admin/index.php`:882-928): question_text, description,
 * meta_keywords, meta_description, meta_index and meta_follow. The
 * question's topics (`topic_id`) are never written, as in OLD.
 *
 * Corrections: authorized (`fatwa.manage`); bound SQL; one transaction with
 * the row locked; audited.
 */
final class UpdateFatwaGeneralQuestionAction
{
    private const TEXT = ['question_text', 'description', 'meta_keywords', 'meta_description'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data the validated form */
    public function execute(AdminUser $actor, FatwaGeneralQuestion $question, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $question);

        DB::connection('main')->transaction(function () use ($actor, $question, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_fatwa_general_questions')->where('id', $question->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $after = [
                'question_text' => trim((string) $data['title']),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => ! empty($data['meta_index']) ? 1 : 0,
                'meta_follow' => ! empty($data['meta_follow']) ? 1 : 0,
            ];
            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column};
            }

            $main->table('nuke_fatwa_general_questions')->where('id', $row->id)->update($after);

            $this->audit->record($actor, 'edit_general_question', 'fatwa', 'general_question', (int) $row->id, [
                'changes' => AuditText::changes($before, $after, self::TEXT),
            ], $ip);
        });
    }
}
