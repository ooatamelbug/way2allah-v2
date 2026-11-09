<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\FatwaGeneralQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Q1 «اضافة فتوى للسؤال» on `fatawa-all-{id}` (and the author's
 * `auther-all-fatawa-{author}-{id}`) — OLD `w2a_fatwa_add_question`
 * (`modules/Fatwa/admin/index.php`:134-207, production-hash-verified).
 *
 * OLD's insert: general_question_id = `|question|`, question_text,
 * auther_id, answer_text, media_type, media_link, media_size, channel_id
 * (0 = «بدون قناة»), date_of_fatwa when given, db_insertion_date = today.
 *
 * Owner-approved corrections:
 * - **topic_id is derived, not taken from the page.** OLD wrote the URL's
 *   `main_topic` (the LAST topic of the panel on `fatawa-all`, the FIRST on
 *   the author page). Laravel writes the canonical filing topic — the first
 *   topic of the question (FatwaFilingTopic);
 * - **the sheikh's `fatwa` counter is maintained** (AuthorFatwaCounter);
 * - decimal media_size, NULL for no date (FatwaFields); the sheikh and
 *   channel must exist (OLD accepted «أختر اسم الشيخ» = 0).
 *
 * Also: authorized (`fatwa.manage`); bound SQL; the question locked, the
 * insert, the counter and the audit row in one transaction.
 */
final class InsertFatwaAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data the validated form, OLD field names */
    public function execute(AdminUser $actor, FatwaGeneralQuestion $question, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $question);

        return DB::connection('main')->transaction(function () use ($actor, $question, $data, $ip) {
            $main = DB::connection('main');
            $questionId = (int) $main->table('nuke_fatwa_general_questions')->where('id', $question->id)->lockForUpdate()->value('id');
            abort_if($questionId < 1, 404);

            $questions = PipeIdList::of([$questionId]);
            $columns = FatwaFields::columns($data) + [
                'general_question_id' => $questions->toString(),
                'topic_id' => FatwaFilingTopic::forQuestions($questions),
                'db_insertion_date' => now()->toDateString(),
            ];

            $id = (int) $main->table('nuke_fatwa_questions')->insertGetId($columns);
            $counter = AuthorFatwaCounter::recount([$columns['auther_id']]);

            $this->audit->record($actor, 'add_fatwa', 'fatwa', 'fatwa', $id, [
                'general_question_id' => $columns['general_question_id'],
                'topic_id' => $columns['topic_id'],
                'author_id' => $columns['auther_id'],
                'channel_id' => $columns['channel_id'],
                'question_text' => AuditText::digest($columns['question_text']),
                'author_fatwa_count' => $counter,
            ], $ip);

            return $id;
        });
    }
}
