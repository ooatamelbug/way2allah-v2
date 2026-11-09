<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Contextual\ContextualReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Where a Fatwa admin operation lands: the public page the admin started
 * from (the validated `return_to` its contextual control carried, through
 * the AdminCP form), else OLD's own `redire` destination — never AdminCP,
 * never OLD's 3-second meta-refresh. Per-fatwa operations add OLD's
 * `#{fatwa id}` anchor. The message shows in the admin-only public notice.
 */
final class FatwaPublicPages
{
    /** OLD C1's intended `fatawa-topics-{cat}` (OLD lost its `redire`). */
    public static function category(int $categoryId): string
    {
        return '/fatawa-topics-'.$categoryId.'-1.htm';
    }

    public static function topic(int $topicId): string
    {
        $categoryId = (int) DB::connection('main')->table('nuke_fatwa_topics')->where('id', $topicId)->value('parent_id');

        return '/fatawa-group-'.$topicId.'-'.$categoryId.'.htm';
    }

    public static function question(int $generalQuestionId): string
    {
        return '/fatawa-all-'.$generalQuestionId.'.htm';
    }

    /** OLD all_Details()' `redire`: the fatwa's first question page. */
    public static function fatwaQuestion(?string $generalQuestionIds): string
    {
        $first = PipeIdList::parse($generalQuestionIds)->first();

        return $first !== null ? self::question($first) : '/fatawa.htm';
    }

    public static function back(Request $request, string $fallback, string $message, ?int $anchor = null): RedirectResponse
    {
        $target = ContextualReturn::safeTarget($request->input(ContextualReturn::PARAM), $request) ?? $fallback;

        if ($anchor !== null && ! str_contains($target, '#')) {
            $target .= '#'.$anchor;
        }

        return redirect()->to($target)->with(ContextualReturn::FLASH, $message);
    }
}
