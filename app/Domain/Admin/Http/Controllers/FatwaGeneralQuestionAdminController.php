<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Fatwa\FatwaOptions;
use App\Domain\Admin\Actions\Fatwa\FatwaPublicPages;
use App\Domain\Admin\Actions\Fatwa\GeneralQuestionTopicsAction;
use App\Domain\Admin\Actions\Fatwa\InsertFatwaAction;
use App\Domain\Admin\Actions\Fatwa\PipeIdList;
use App\Domain\Admin\Actions\Fatwa\UpdateFatwaGeneralQuestionAction;
use App\Domain\Admin\Http\Requests\Fatwa\FatwaRequest;
use App\Domain\Admin\Http\Requests\Fatwa\UpdateFatwaGeneralQuestionRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\FatwaGeneralQuestion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OLD public admin parity — Fatwa (owner, 2026-10-30), the question page's
 * «لوحة تحكم المشرفين» (OLD `AdvancedStats(…, "all_details")` on
 * `fatawa-all-{id}` and the author's `auther-all-fatawa-{author}-{id}`):
 * Q1 «اضافة فتوى للسؤال», Q2 «تعديل السؤال», Q4 «اضافة لموضوع أخر», and
 * Q5 «نقل لموضوع أخر» or Q6 «حذف من موضوع». Q3 «حذف السؤال» is dead in
 * production and has no route.
 */
class FatwaGeneralQuestionAdminController
{
    /** Q1 — OLD w2a_fatwa_add_question()'s form (fatwa_question_add.htm). */
    public function createFatwa(FatwaGeneralQuestion $fatwaGeneralQuestion): View
    {
        return view('admin.fatwa.fatwas.form', [
            'question' => $fatwaGeneralQuestion,
            'fatwa' => null,
            'authors' => FatwaOptions::authors(),
            'channels' => FatwaOptions::channels(),
            'today' => now()->toDateString(),
        ]);
    }

    public function storeFatwa(FatwaRequest $request, FatwaGeneralQuestion $fatwaGeneralQuestion, InsertFatwaAction $action): RedirectResponse
    {
        $id = $action->execute($this->actor(), $fatwaGeneralQuestion, $request->validated(), $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::question((int) $fatwaGeneralQuestion->id), 'تم إضافة الفتوى بنجاح', $id);
    }

    /** Q2 — OLD fatwa_edit_general_question()'s form (fatwa_general_question_edit.htm). */
    public function edit(FatwaGeneralQuestion $fatwaGeneralQuestion): View
    {
        return view('admin.fatwa.questions.edit', ['question' => $fatwaGeneralQuestion]);
    }

    public function update(UpdateFatwaGeneralQuestionRequest $request, FatwaGeneralQuestion $fatwaGeneralQuestion, UpdateFatwaGeneralQuestionAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $fatwaGeneralQuestion, $request->validated(), $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::question((int) $fatwaGeneralQuestion->id), 'تم تعديل نص السؤال بنجاح و أصبح '.trim((string) $request->validated('title')));
    }

    /** Q4 / Q5 / Q6 — OLD's add / move / remove pages (add_question_many_topics.htm, move_…, delete_…). */
    public function topics(Request $request, FatwaGeneralQuestion $fatwaGeneralQuestion): View
    {
        $mode = (string) $request->route('mode');
        $current = PipeIdList::parse($fatwaGeneralQuestion->topic_id);

        return view('admin.fatwa.relations', [
            'mode' => $mode,
            'kind' => 'topics',
            'subject' => $fatwaGeneralQuestion->question_text,
            'action' => route('admin.fatwa.questions.topics.'.$mode.'.save', $fatwaGeneralQuestion),
            'linked' => FatwaOptions::linked($current, 'nuke_fatwa_topics', 'topic_name'),
            'options' => $mode === 'remove' ? [] : FatwaOptions::topics()->map(fn ($t) => ['id' => (int) $t->id, 'title' => (string) $t->topic_name])->all(),
            'backUrl' => FatwaPublicPages::question((int) $fatwaGeneralQuestion->id),
            'blocked' => match (true) {
                $mode === 'move' && $current->count() > 1 => 'لا يمكنك نقل هذا السؤال لأنه ينتمى لأكثر من موضوع',
                $mode === 'remove' && $current->count() <= 1 => 'لا يمكنك حذف الموضوع الوحيد المرتبط',
                default => null,
            },
        ]);
    }

    public function addTopics(Request $request, FatwaGeneralQuestion $fatwaGeneralQuestion, GeneralQuestionTopicsAction $action): RedirectResponse
    {
        $data = $request->validate(self::manyRules('nuke_fatwa_topics'), self::messages());
        $action->add($this->actor(), $fatwaGeneralQuestion, $data['ids'], $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::question((int) $fatwaGeneralQuestion->id), 'تم اضافة السؤال الى موضوع بنجاح');
    }

    public function moveTopic(Request $request, FatwaGeneralQuestion $fatwaGeneralQuestion, GeneralQuestionTopicsAction $action): RedirectResponse
    {
        $data = $request->validate(['id' => ['required', 'integer', 'min:1', 'exists:main.nuke_fatwa_topics,id']], self::messages());
        $action->move($this->actor(), $fatwaGeneralQuestion, (int) $data['id'], $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::question((int) $fatwaGeneralQuestion->id), 'تم نقل السؤال من موضوع الى موضوع بنجاح');
    }

    public function removeTopics(Request $request, FatwaGeneralQuestion $fatwaGeneralQuestion, GeneralQuestionTopicsAction $action): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'min:1']], self::messages());
        $action->remove($this->actor(), $fatwaGeneralQuestion, $data['ids'], $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::question((int) $fatwaGeneralQuestion->id), 'تم حذف السؤال من الموضوع بنجاح');
    }

    /** @return array<string, mixed> */
    public static function manyRules(string $table): array
    {
        return ['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'min:1', 'distinct', 'exists:main.'.$table.',id']];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'ids.required' => 'اختر عنصرا واحدا على الأقل',
            'ids.*.exists' => 'أحد العناصر المختارة غير موجود',
            'id.required' => 'اختر عنصرا',
            'id.exists' => 'العنصر المختار غير موجود',
        ];
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
