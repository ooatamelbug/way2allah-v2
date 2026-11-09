<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Fatwa\FatwaPublicPages;
use App\Domain\Admin\Actions\Fatwa\InsertFatwaGeneralQuestionAction;
use App\Domain\Admin\Actions\Fatwa\InsertFatwaTopicAction;
use App\Domain\Admin\Actions\Fatwa\UpdateFatwaTopicAction;
use App\Domain\Admin\Http\Requests\Fatwa\StoreFatwaGeneralQuestionRequest;
use App\Domain\Admin\Http\Requests\Fatwa\StoreFatwaTopicRequest;
use App\Domain\Admin\Http\Requests\Fatwa\UpdateFatwaTopicRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\FatwaTopic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * OLD public admin parity — Fatwa (owner, 2026-10-30), the category and
 * topic pages' «لوحة تحكم المشرفين» (OLD `AdvancedStats()`,
 * `modules/Fatwa/index.php`:379-433):
 *
 * - `fatawa-topics-{cat}`: C1 «اضافة موضوع للتصنيف»;
 * - `fatawa-group-{topic}-{cat}`: T1 «اضافة سؤال للموضوع», T2 «تعديل الموضوع».
 *
 * C2 «حذف التصنيف من قسم الفتاوى» and T3 «حذف الموضوع» are dead in
 * production and have no route. Every mutation is an action class
 * (authorized, transactional, audited) followed by a direct redirect to the
 * public page the admin came from.
 */
class FatwaTopicAdminController
{
    /** C1 — OLD w2a_fatwa_add()'s form (fatwa_add.htm). */
    public function createTopic(Category $category): View
    {
        return view('admin.fatwa.topics.create', ['category' => $category]);
    }

    public function storeTopic(StoreFatwaTopicRequest $request, Category $category, InsertFatwaTopicAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $category, $request->validated(), $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::category((int) $category->id), 'تم إضافة الموضوع بنجاح');
    }

    /** T1 — OLD fatwa_add_general_question()'s form (fatwa_general_question_add.htm). */
    public function createQuestion(FatwaTopic $fatwaTopic): View
    {
        return view('admin.fatwa.questions.create', ['topic' => $fatwaTopic]);
    }

    public function storeQuestion(StoreFatwaGeneralQuestionRequest $request, FatwaTopic $fatwaTopic, InsertFatwaGeneralQuestionAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $fatwaTopic, $request->validated(), $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::topic((int) $fatwaTopic->id), 'تم إضافة السؤال بنجاح');
    }

    /** T2 — OLD w2a_fatwa_edit()'s form (fatwa_edit.htm). */
    public function edit(FatwaTopic $fatwaTopic): View
    {
        return view('admin.fatwa.topics.edit', ['topic' => $fatwaTopic]);
    }

    public function update(UpdateFatwaTopicRequest $request, FatwaTopic $fatwaTopic, UpdateFatwaTopicAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $fatwaTopic, $request->validated(), $request->ip());

        return FatwaPublicPages::back($request, FatwaPublicPages::topic((int) $fatwaTopic->id), 'تم تعديل اسم الموضوع بنجاح و أصبح '.trim((string) $request->validated('topicname')));
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
