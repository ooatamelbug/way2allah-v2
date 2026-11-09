<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Fatwa\DeleteFatwaAction;
use App\Domain\Admin\Actions\Fatwa\FatwaFields;
use App\Domain\Admin\Actions\Fatwa\FatwaOptions;
use App\Domain\Admin\Actions\Fatwa\FatwaPublicPages;
use App\Domain\Admin\Actions\Fatwa\FatwaQuestionsAction;
use App\Domain\Admin\Actions\Fatwa\PipeIdList;
use App\Domain\Admin\Actions\Fatwa\UpdateFatwaAction;
use App\Domain\Admin\Http\Requests\Fatwa\FatwaRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\LinkSizeChecker;
use App\Domain\Content\Models\FatwaGeneralQuestion;
use App\Domain\Content\Models\FatwaQuestion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OLD public admin parity — Fatwa (owner, 2026-10-30), the icons beside each
 * fatwa on the question page (OLD `all_Details()` + `details.htm`):
 * A1 «اضافة الى اسئلة اخرى», A2 «نقل الفتوى من سؤال لأخر» or A3 «حذف الفتوى
 * من على سؤال», A4 «تعديل», A5 «حذف» — and X2, the add / edit forms' size
 * lookup (OLD `getfilesize`).
 *
 * The single-fatwa page's own panel (OLD `Detailes`, D1–D7) and «رابط لا
 * يعمل» (X1) are dead in production and are not reproduced.
 */
class FatwaAdminController
{
    /** A4 — OLD w2a_fatwa_edit_question()'s form (fatwa_question_edit.htm). */
    public function edit(FatwaQuestion $fatwaQuestion): View
    {
        $first = PipeIdList::parse($fatwaQuestion->general_question_id)->first();

        return view('admin.fatwa.fatwas.form', [
            'question' => $first !== null ? FatwaGeneralQuestion::find($first) : null,
            'fatwa' => $fatwaQuestion,
            'sizeField' => FatwaFields::sizeField($fatwaQuestion->media_size),
            'dateField' => FatwaFields::dateField($fatwaQuestion->date_of_fatwa),
            'authors' => FatwaOptions::authors(),
            'channels' => FatwaOptions::channels(),
            'today' => (string) $fatwaQuestion->db_insertion_date,
        ]);
    }

    public function update(FatwaRequest $request, FatwaQuestion $fatwaQuestion, UpdateFatwaAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $fatwaQuestion, $request->validated(), $request->ip());

        return $this->back($request, $fatwaQuestion, 'تم تعديل الفتوى بنجاح');
    }

    /** A5 — OLD w2a_fatwa_del_question()'s confirmation page (fatwa_question_del.htm). */
    public function confirmDestroy(FatwaQuestion $fatwaQuestion): View
    {
        return view('admin.fatwa.fatwas.delete', [
            'fatwa' => $fatwaQuestion,
            'questions' => FatwaOptions::linked(PipeIdList::parse($fatwaQuestion->general_question_id), 'nuke_fatwa_general_questions', 'question_text'),
            'backUrl' => FatwaPublicPages::fatwaQuestion($fatwaQuestion->general_question_id).'#'.$fatwaQuestion->id,
        ]);
    }

    public function destroy(Request $request, FatwaQuestion $fatwaQuestion, DeleteFatwaAction $action): RedirectResponse
    {
        $result = $action->execute($this->actor(), $fatwaQuestion, $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::fatwaQuestion($result['general_question_id']), 'تم حذف الفتوى بنجاح');
    }

    /** A1 / A2 / A3 — OLD's add / move / remove pages for a fatwa's questions. */
    public function questions(Request $request, FatwaQuestion $fatwaQuestion): View
    {
        $mode = (string) $request->route('mode');
        $current = PipeIdList::parse($fatwaQuestion->general_question_id);

        return view('admin.fatwa.relations', [
            'mode' => $mode,
            'kind' => 'questions',
            'subject' => $fatwaQuestion->question_text,
            'action' => route('admin.fatwa.fatwas.questions.'.$mode.'.save', $fatwaQuestion),
            'linked' => FatwaOptions::linked($current, 'nuke_fatwa_general_questions', 'question_text'),
            'options' => $mode === 'remove' ? [] : FatwaOptions::generalQuestions()->map(fn ($q) => ['id' => (int) $q->id, 'title' => (string) $q->question_text])->all(),
            'backUrl' => FatwaPublicPages::fatwaQuestion($fatwaQuestion->general_question_id).'#'.$fatwaQuestion->id,
            'blocked' => match (true) {
                $mode === 'move' && $current->count() > 1 => 'لا يمكنك نقل هذه الفتوى لأنها تنتمى لأكثر من سؤال',
                $mode === 'remove' && $current->count() <= 1 => 'لا يمكنك حذف السؤال الوحيد المرتبط',
                default => null,
            },
        ]);
    }

    public function addQuestions(Request $request, FatwaQuestion $fatwaQuestion, FatwaQuestionsAction $action): RedirectResponse
    {
        $data = $request->validate(FatwaGeneralQuestionAdminController::manyRules('nuke_fatwa_general_questions'), FatwaGeneralQuestionAdminController::messages());
        $action->add($this->actor(), $fatwaQuestion, $data['ids'], $request->ip());

        return $this->back($request, $fatwaQuestion, 'تم اضافة الاسئلة الى الفتوى بنجاح');
    }

    public function moveQuestion(Request $request, FatwaQuestion $fatwaQuestion, FatwaQuestionsAction $action): RedirectResponse
    {
        $data = $request->validate(['id' => ['required', 'integer', 'min:1', 'exists:main.nuke_fatwa_general_questions,id']], FatwaGeneralQuestionAdminController::messages());
        $action->move($this->actor(), $fatwaQuestion, (int) $data['id'], $request->ip());

        return $this->back($request, $fatwaQuestion, 'تم نقل الفتوى من سؤال لأخر بنجاح');
    }

    public function removeQuestions(Request $request, FatwaQuestion $fatwaQuestion, FatwaQuestionsAction $action): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'min:1']], FatwaGeneralQuestionAdminController::messages());
        $action->remove($this->actor(), $fatwaQuestion, $data['ids'], $request->ip());

        return $this->back($request, $fatwaQuestion, 'تم حذف الفتوى من السؤال بنجاح');
    }

    /**
     * X2 — OLD `getfilesize` (`admin/index.php`:69-90 + `js/inc.js`
     * `get_filesize()`): the link's size in MB with two decimals, as OLD's
     * script filled it. Through the shared LinkSizeChecker (a HEAD request).
     * `size` is null when it cannot be learned; the form keeps the typed value.
     */
    public function mediaSize(Request $request, LinkSizeChecker $checker): JsonResponse
    {
        $link = $request->query('url');
        $bytes = is_string($link) && strlen($link) <= 2048 ? $checker->headContentLength($link) : null;

        return response()->json(['size' => $bytes === null ? null : number_format(floor($bytes / 1048576 * 100) / 100, 2, '.', '')]);
    }

    /** OLD `redire`: the page the admin came from, else the fatwa's first question — at the fatwa's anchor. */
    private function back(Request $request, FatwaQuestion $fatwa, string $message): RedirectResponse
    {
        $questions = FatwaQuestion::query()->whereKey($fatwa->id)->value('general_question_id');

        return FatwaPublicPages::back($request, FatwaPublicPages::fatwaQuestion(is_string($questions) ? $questions : null), $message, (int) $fatwa->id);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
