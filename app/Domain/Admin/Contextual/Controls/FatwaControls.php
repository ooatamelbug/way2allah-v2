<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Actions\Fatwa\PipeIdList;
use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\FatwaGeneralQuestion;
use App\Domain\Content\Models\FatwaTopic;

/**
 * OLD public admin parity — Fatwa (owner, 2026-10-30), from OLD
 * `modules/Fatwa/index.php` (`AdvancedStats()` and `all_Details()` +
 * `details.htm`, production-hash-verified), in OLD's order and with OLD's
 * labels. Each control opens the Laravel counterpart of the OLD admin page
 * and carries the page as `return_to`, so the operation lands back here.
 *
 * Not offered — dead in production: the category page's «حذف التصنيف من
 * قسم الفتاوى» (C2), the topic page's «حذف الموضوع» (T3), the question
 * page's «حذف السؤال» (Q3), the single-fatwa page's panel (D1–D7) and
 * «رابط لا يعمل» (X1).
 */
final class FatwaControls
{
    /** `fatawa-topics-{cat}` / `fatawa-category-{cat}` — OLD `AdvancedStats(0,0,$cat,"topics")`. @return list<ContextualControl> */
    public static function category(Category $category): array
    {
        return [
            ContextualControl::link('اضافة موضوع للتصنيف', 'fa-plus', 'admin.fatwa.categories.topics.create', ['category' => $category->id]),
        ];
    }

    /** `fatawa-group-{topic}-{cat}` — OLD `AdvancedStats(0,$topic,$cat,"questions")`. @return list<ContextualControl> */
    public static function topic(FatwaTopic $topic): array
    {
        $id = ['fatwaTopic' => $topic->id];

        return [
            ContextualControl::link('اضافة سؤال للموضوع', 'fa-plus', 'admin.fatwa.topics.questions.create', $id),
            ContextualControl::link('تعديل الموضوع', 'fa-pencil-square-o', 'admin.fatwa.topics.edit', $id),
        ];
    }

    /**
     * `fatawa-all-{id}` and `auther-all-fatawa-{author}-{id}` — OLD
     * `AdvancedStats($id,…,"all_details")`: «نقل لموضوع أخر» while the
     * question is in one topic, «حذف من موضوع» once it is in more.
     *
     * @return list<ContextualControl>
     */
    public static function question(FatwaGeneralQuestion $question): array
    {
        $id = ['fatwaGeneralQuestion' => $question->id];
        $manyTopics = PipeIdList::parse($question->topic_id)->count() > 1;

        return [
            ContextualControl::link('اضافة فتوى للسؤال', 'fa-plus', 'admin.fatwa.questions.fatwas.create', $id),
            ContextualControl::link('تعديل السؤال', 'fa-pencil-square-o', 'admin.fatwa.questions.edit', $id),
            ContextualControl::link('اضافة لموضوع أخر', 'fa-plus-square', 'admin.fatwa.questions.topics.add', $id),
            $manyTopics
                ? ContextualControl::link('حذف من موضوع', 'fa-trash', 'admin.fatwa.questions.topics.remove', $id)
                : ContextualControl::link('نقل لموضوع أخر', 'fa-arrows', 'admin.fatwa.questions.topics.move', $id),
        ];
    }

    /**
     * Beside each fatwa on those pages — OLD `details.htm`'s icons:
     * «اضافة الى اسئلة اخرى», then «نقل الفتوى من سؤال لأخر» while the fatwa
     * is under one question or «حذف الفتوى من على سؤال» once under more,
     * «تعديل», «حذف».
     *
     * @return list<ContextualControl>
     */
    public static function fatwa(int $fatwaId, ?string $generalQuestionIds): array
    {
        $id = ['fatwaQuestion' => $fatwaId];
        $manyQuestions = PipeIdList::parse($generalQuestionIds)->count() > 1;

        return [
            ContextualControl::link('اضافة الى اسئلة اخرى', 'fa-plus-square', 'admin.fatwa.fatwas.questions.add', $id),
            $manyQuestions
                ? ContextualControl::link('حذف الفتوى من على سؤال', 'fa-chain-broken', 'admin.fatwa.fatwas.questions.remove', $id)
                : ContextualControl::link('نقل الفتوى من سؤال لأخر', 'fa-arrows', 'admin.fatwa.fatwas.questions.move', $id),
            ContextualControl::link('تعديل', 'fa-pencil-square-o', 'admin.fatwa.fatwas.edit', $id),
            ContextualControl::link('حذف', 'fa-trash', 'admin.fatwa.fatwas.delete', $id),
        ];
    }
}
