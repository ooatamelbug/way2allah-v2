<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\FatwaTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T1 «اضافة سؤال للموضوع» on `fatawa-group-{topic}-{cat}` — OLD
 * `fatwa_add_general_question` (`modules/Fatwa/admin/index.php`:698-881).
 *
 * OLD's insert: question_text, description, meta_keywords, meta_description,
 * meta_index, meta_follow and topic_id = `|topic|` (the pipe format).
 *
 * Duplicate correction: OLD looked for a question of the same text whose
 * `topic_id` was `|{category id}|` — the category, not the topic — so it
 * never matched a real duplicate. Laravel refuses a question of the same
 * text that already belongs to THIS topic (anywhere in its topic list),
 * with OLD's message.
 *
 * Corrections: authorized (`fatwa.manage`); bound SQL; one transaction with
 * the topic locked; audited.
 */
final class InsertFatwaGeneralQuestionAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form
     *
     * @throws ValidationException the question already exists in this topic
     */
    public function execute(AdminUser $actor, FatwaTopic $topic, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $topic);

        return DB::connection('main')->transaction(function () use ($actor, $topic, $data, $ip) {
            $main = DB::connection('main');
            $topicId = (int) $main->table('nuke_fatwa_topics')->where('id', $topic->id)->lockForUpdate()->value('id');
            abort_if($topicId < 1, 404);

            $title = trim((string) $data['title']);
            $duplicate = $main->table('nuke_fatwa_general_questions')->where('question_text', $title)->orderBy('id')->get(['id', 'topic_id'])
                ->first(fn (object $row) => PipeIdList::parse($row->topic_id)->contains($topicId));
            if ($duplicate !== null) {
                throw ValidationException::withMessages(['title' => '(*) هذا السؤال موجود من قبل برقم كودي هو : '.$duplicate->id]);
            }

            $topics = PipeIdList::of([$topicId])->toString();
            $id = (int) $main->table('nuke_fatwa_general_questions')->insertGetId([
                'question_text' => $title,
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => ! empty($data['meta_index']) ? 1 : 0,
                'meta_follow' => ! empty($data['meta_follow']) ? 1 : 0,
                'topic_id' => $topics,
            ]);

            $this->audit->record($actor, 'add_general_question', 'fatwa', 'general_question', $id, [
                'topic_id' => $topics,
                'title' => AuditText::digest($title),
            ], $ip);

            return $id;
        });
    }
}
