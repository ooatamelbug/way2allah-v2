<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\FatwaQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A fatwa's general questions (`nuke_fatwa_questions.general_question_id`,
 * the `|q1|q2|` list) — the per-fatwa icons on `fatawa-all-{id}`, OLD
 * `modules/Fatwa/admin/index.php`, production-hash-verified:
 *
 * - A1 «اضافة الى اسئلة اخرى» — `fatwa_many_questions()` (:929-994);
 * - A2 «نقل الفتوى من سؤال لأخر» — `fatwa_move_questions()` (:995-1067),
 *   offered and accepted only while the fatwa is not under more than one
 *   question;
 * - A3 «حذف الفتوى من على سؤال» — `fatwa_delete_questions()` (:1068-1149),
 *   offered and accepted only while it is under more than one.
 *
 * Owner-approved corrections:
 * - **A1 appends.** OLD rebuilt the list from its picker, re-sorted by
 *   question text, so a save could change the primary (first) question.
 *   Laravel keeps the stored ids in their stored order and appends;
 * - **at least one question remains** after A3, checked here (OLD only in
 *   JavaScript); A2's and A3's preconditions are checked here too;
 * - every question must exist;
 * - **topic_id follows** the fatwa's (possibly new) first question
 *   (FatwaFilingTopic) — OLD left it stale.
 *
 * Also: authorized (`fatwa.manage`); bound SQL; one transaction with the
 * fatwa locked; audited.
 */
final class FatwaQuestionsAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * A1.
     *
     * @param  list<int|string>  $questionIds
     *
     * @throws ValidationException nothing new to add
     */
    public function add(AdminUser $actor, FatwaQuestion $fatwa, array $questionIds, ?string $ip = null): void
    {
        $this->change($actor, $fatwa, 'add_fatwa_questions', $ip, function (PipeIdList $current) use ($questionIds) {
            $new = self::existingQuestions(PipeIdList::of($questionIds))->without($current);
            if ($new->isEmpty()) {
                throw ValidationException::withMessages(['questions' => 'الفتوى موجودة بالفعل في كل الأسئلة المختارة']);
            }

            return $current->append($new);
        });
    }

    /**
     * A2.
     *
     * @throws ValidationException the fatwa is under more than one question, or is already there
     */
    public function move(AdminUser $actor, FatwaQuestion $fatwa, int $questionId, ?string $ip = null): void
    {
        $this->change($actor, $fatwa, 'move_fatwa_question', $ip, function (PipeIdList $current) use ($questionId) {
            if ($current->count() > 1) {
                throw ValidationException::withMessages(['questions' => 'لا يمكنك نقل هذه الفتوى لأنها تنتمى لأكثر من سؤال']);
            }
            $target = self::existingQuestions(PipeIdList::of([$questionId]));
            if ($target->isEmpty()) {
                throw ValidationException::withMessages(['questions' => 'السؤال المختار غير موجود']);
            }
            if ($current->ids() === $target->ids()) {
                throw ValidationException::withMessages(['questions' => 'الفتوى موجودة بالفعل في هذا السؤال']);
            }

            return $target;
        });
    }

    /**
     * A3.
     *
     * @param  list<int|string>  $questionIds
     *
     * @throws ValidationException the fatwa is under one question only, or every question was selected
     */
    public function remove(AdminUser $actor, FatwaQuestion $fatwa, array $questionIds, ?string $ip = null): void
    {
        $this->change($actor, $fatwa, 'remove_fatwa_questions', $ip, function (PipeIdList $current) use ($questionIds) {
            if ($current->count() <= 1) {
                throw ValidationException::withMessages(['questions' => 'لا يمكنك حذف السؤال الوحيد المرتبط']);
            }
            $remaining = $current->without(PipeIdList::of($questionIds));
            if ($remaining->count() === $current->count()) {
                throw ValidationException::withMessages(['questions' => 'اختر سؤالا مرتبطا بالفتوى']);
            }
            if ($remaining->isEmpty()) {
                throw ValidationException::withMessages(['questions' => 'يجب أن تبقى الفتوى في سؤال واحد على الأقل']);
            }

            return $remaining;
        });
    }

    /** @param callable(PipeIdList): PipeIdList $next */
    private function change(AdminUser $actor, FatwaQuestion $fatwa, string $action, ?string $ip, callable $next): void
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $fatwa);

        DB::connection('main')->transaction(function () use ($actor, $fatwa, $action, $ip, $next) {
            $main = DB::connection('main');
            $row = $main->table('nuke_fatwa_questions')->where('id', $fatwa->id)->lockForUpdate()->first(['id', 'general_question_id', 'topic_id']);
            abort_if($row === null, 404);

            $after = $next(PipeIdList::parse($row->general_question_id));
            $topicId = FatwaFilingTopic::forQuestions($after);

            $main->table('nuke_fatwa_questions')->where('id', $row->id)->update([
                'general_question_id' => $after->toString(),
                'topic_id' => $topicId,
            ]);

            $this->audit->record($actor, $action, 'fatwa', 'fatwa', (int) $row->id, [
                'from' => (string) $row->general_question_id,
                'to' => $after->toString(),
                'topic_id' => ['from' => (int) $row->topic_id, 'to' => $topicId],
            ], $ip);
        });
    }

    /** The given ids that are real general questions, in the given order. */
    private static function existingQuestions(PipeIdList $ids): PipeIdList
    {
        $existing = DB::connection('main')->table('nuke_fatwa_general_questions')->whereIn('id', $ids->ids())->pluck('id')->map(fn ($id) => (int) $id)->all();

        return PipeIdList::of(array_values(array_filter($ids->ids(), fn (int $id) => in_array($id, $existing, true))));
    }
}
