<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\Mobile\MobileMaterial;
use App\Domain\Content\Models\TelawahItem;
use App\Domain\Content\Models\TelawahMirror;
use App\Domain\Content\Models\W2acdItem;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollComment;

/**
 * The single-control contexts: polls (Phase 4), CDs (Phase 6), Mobile
 * materials (Phase 7) and the Telawah item link status (Phase 2 family).
 */
final class PublicPageControls
{
    /** OLD `pollList()` :241/:279, Gen 2 `pollList()` — «Edit» beside each poll. */
    public static function pollRow(Poll $poll): ContextualControl
    {
        return ContextualControl::link('تعديل', 'fa-pencil', 'admin.polls.edit', ['poll' => $poll->getKey()]);
    }

    /** OLD `pollResults()` :418, Gen 2 `pollResults()` — «Add | Edit». @return list<ContextualControl> */
    public static function pollResults(Poll $poll): array
    {
        return [
            ContextualControl::link('إضافة إستفتاء', 'fa-plus', 'admin.polls.create'),
            ContextualControl::link('تعديل الإستفتاء', 'fa-pencil', 'admin.polls.edit', ['poll' => $poll->getKey()]),
        ];
    }

    /**
     * OLD `DisplayTopic()` (`modules/Surveys/comments.php:486-487`): «مسح» beside
     * each comment for an admin — Phase 4's thread delete (super-admin, as
     * OLD's `RemovePollComment`). It removes the replies too, so it returns to
     * the poll's comment page.
     */
    public static function pollComment(PollComment $comment): ContextualControl
    {
        return ContextualControl::action('مسح', 'fa-trash', 'admin.polls.comments.destroy', ['comment' => (int) $comment->tid], 'DELETE', [],
            'حذف هذا التعليق وكل الردود عليه؟', returnTo: '/survey-comments-'.(int) $comment->pollID.'.htm');
    }

    /**
     * The CD page's «لوحة تحكم الاسطوانة» — OLD `Detailes()` (`modules/w2acd/
     * index.php:285-310`): `item_cp_cd.htm`'s «تعديل» / «نقل» / «حذف», then
     * the banner controls (`banner = 0` → «رفع بانر», otherwise «تعديل البانر»
     * / «حذف البانر»). «نقل» opens the same edit form, as in OLD — its group
     * selector is the move. A deleted CD has no page to come back to, so
     * delete returns to the CD listing; the banner pages send the admin back
     * to the CD themselves. «اضافة الى قائمة الشير» (`W2ACDWaitingFB`) is
     * owner-retired (2026-11-01).
     *
     * @return list<ContextualControl>
     */
    public static function cd(W2acdItem $release): array
    {
        $own = ['release' => $release->id];
        $controls = [
            ContextualControl::link('تعديل الإسطوانة', 'fa-pencil', 'admin.cds.edit', $own),
            ContextualControl::link('نقل الإسطوانة', 'fa-arrows', 'admin.cds.edit', $own),
            ContextualControl::action('حذف الإسطوانة', 'fa-trash', 'admin.cds.destroy', $own, 'DELETE', [],
                "حذف الإسطوانة #{$release->id}؟", returnTo: '/cds-main.htm'),
        ];

        if ((int) $release->getAttribute('banner') === 0) {
            $controls[] = ContextualControl::link('رفع بانر', 'fa-picture-o', 'admin.cds.banner', $own, returns: false);
        } else {
            $controls[] = ContextualControl::link('تعديل البانر', 'fa-picture-o', 'admin.cds.banner', $own, returns: false);
            $controls[] = ContextualControl::link('حذف البانر', 'fa-times-circle', 'admin.cds.banner.delete', $own, returns: false);
        }

        return $controls;
    }

    /** OLD `DownloadsModDownload` edit icon beside each listed material. */
    public static function mobileMaterial(MobileMaterial $material): ContextualControl
    {
        return ContextualControl::link('تعديل المادة', 'fa-pencil', 'admin.mobile.materials.edit', ['material' => $material->lid]);
    }

    /**
     * OLD Telawah `Mirror()` (`modules/Telawah/index.php:384-386`) — edit /
     * delete beside each of the item's mirrors (closure correction,
     * 2026-10-15). OLD had no working «add»: it disabled insert itself.
     *
     * @return list<ContextualControl>
     */
    public static function telawahMirror(TelawahMirror $mirror): array
    {
        return [
            ContextualControl::link('تعديل الوصلة', 'fa-pencil', 'admin.mirrors.telawah.edit', ['telawahMirror' => $mirror->id]),
            ContextualControl::action('حذف الوصلة', 'fa-trash', 'admin.mirrors.telawah.destroy', ['telawahMirror' => $mirror->id], 'DELETE', [], "حذف الوصلة #{$mirror->id}؟"),
        ];
    }

    /** OLD Telawah `AdvancedStats()` :317 — the admin-only link status block. */
    public static function telawahItem(TelawahItem $item): ContextualControl
    {
        return ContextualControl::link('حالة الوصلة', 'fa-heartbeat', 'admin.link-quality.telawah.item', ['telawahItem' => $item->id], returns: false);
    }
}
