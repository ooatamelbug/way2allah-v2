<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\FatwaGeneralQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A general question's topics (`nuke_fatwa_general_questions.topic_id`, the
 * `|t1|t2|` list) — the three `fatawa-all-{id}` panel controls, OLD
 * `modules/Fatwa/admin/index.php`, production-hash-verified:
 *
 * - Q4 «اضافة لموضوع أخر» — `question_many_topics()` (:1362-1424);
 * - Q5 «نقل لموضوع أخر» — `question_move_topics()` (:1247-1314), offered
 *   and accepted only while the question is not in more than one topic;
 * - Q6 «حذف من موضوع» — `question_delete_topics()` (:1164-1238), offered
 *   and accepted only while it is in more than one.
 *
 * Owner-approved corrections:
 * - **Q4 appends.** OLD rebuilt the whole list from its picker, re-sorted by
 *   topic name, so a save could change the primary (first) topic. Laravel
 *   keeps the stored ids in their stored order and appends the new ones;
 * - **at least one topic remains** after Q6, checked here (OLD only in
 *   JavaScript); Q5's and Q6's preconditions are checked here too;
 * - every topic must exist;
 * - **fatwa topic_id follows.** After the change, every fatwa whose FIRST
 *   question is this one is re-filed under the question's first topic
 *   (FatwaFilingTopic) — OLD left them stale.
 *
 * Also: authorized (`fatwa.manage`); bound SQL; one transaction with the
 * question locked; audited.
 */
final class GeneralQuestionTopicsAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * Q4.
     *
     * @param  list<int|string>  $topicIds
     *
     * @throws ValidationException nothing new to add
     */
    public function add(AdminUser $actor, FatwaGeneralQuestion $question, array $topicIds, ?string $ip = null): void
    {
        $this->change($actor, $question, 'add_question_topics', $ip, function (PipeIdList $current) use ($topicIds) {
            $new = self::existingTopics(PipeIdList::of($topicIds))->without($current);
            if ($new->isEmpty()) {
                throw ValidationException::withMessages(['topics' => 'السؤال موجود بالفعل في كل المواضيع المختارة']);
            }

            return $current->append($new);
        });
    }

    /**
     * Q5.
     *
     * @throws ValidationException the question is in more than one topic, or is already there
     */
    public function move(AdminUser $actor, FatwaGeneralQuestion $question, int $topicId, ?string $ip = null): void
    {
        $this->change($actor, $question, 'move_question_topic', $ip, function (PipeIdList $current) use ($topicId) {
            if ($current->count() > 1) {
                throw ValidationException::withMessages(['topics' => 'لا يمكنك نقل هذا السؤال لأنه ينتمى لأكثر من موضوع']);
            }
            $target = self::existingTopics(PipeIdList::of([$topicId]));
            if ($target->isEmpty()) {
                throw ValidationException::withMessages(['topics' => 'الموضوع المختار غير موجود']);
            }
            if ($current->ids() === $target->ids()) {
                throw ValidationException::withMessages(['topics' => 'السؤال موجود بالفعل في هذا الموضوع']);
            }

            return $target;
        });
    }

    /**
     * Q6.
     *
     * @param  list<int|string>  $topicIds
     *
     * @throws ValidationException the question is in one topic only, or every topic was selected
     */
    public function remove(AdminUser $actor, FatwaGeneralQuestion $question, array $topicIds, ?string $ip = null): void
    {
        $this->change($actor, $question, 'remove_question_topics', $ip, function (PipeIdList $current) use ($topicIds) {
            if ($current->count() <= 1) {
                throw ValidationException::withMessages(['topics' => 'لا يمكنك حذف الموضوع الوحيد المرتبط']);
            }
            $remaining = $current->without(PipeIdList::of($topicIds));
            if ($remaining->count() === $current->count()) {
                throw ValidationException::withMessages(['topics' => 'اختر موضوعا مرتبطا بالسؤال']);
            }
            if ($remaining->isEmpty()) {
                throw ValidationException::withMessages(['topics' => 'يجب أن يبقى السؤال في موضوع واحد على الأقل']);
            }

            return $remaining;
        });
    }

    /** @param callable(PipeIdList): PipeIdList $next */
    private function change(AdminUser $actor, FatwaGeneralQuestion $question, string $action, ?string $ip, callable $next): void
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $question);

        DB::connection('main')->transaction(function () use ($actor, $question, $action, $ip, $next) {
            $main = DB::connection('main');
            $row = $main->table('nuke_fatwa_general_questions')->where('id', $question->id)->lockForUpdate()->first(['id', 'topic_id']);
            abort_if($row === null, 404);

            $current = PipeIdList::parse($row->topic_id);
            $after = $next($current);

            $main->table('nuke_fatwa_general_questions')->where('id', $row->id)->update(['topic_id' => $after->toString()]);
            $refiled = FatwaFilingTopic::recomputeForQuestion((int) $row->id);

            $this->audit->record($actor, $action, 'fatwa', 'general_question', (int) $row->id, [
                'from' => (string) $row->topic_id,
                'to' => $after->toString(),
                'fatwas_refiled' => $refiled,
                'fatwa_topic_id' => FatwaFilingTopic::primaryTopicOf((int) $row->id),
            ], $ip);
        });
    }

    /** The given ids that are real topics, in the given order. */
    private static function existingTopics(PipeIdList $ids): PipeIdList
    {
        $existing = DB::connection('main')->table('nuke_fatwa_topics')->whereIn('id', $ids->ids())->pluck('id')->map(fn ($id) => (int) $id)->all();

        return PipeIdList::of(array_values(array_filter($ids->ids(), fn (int $id) => in_array($id, $existing, true))));
    }
}
