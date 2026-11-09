<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * «تعليقات تنتظر النشر» on Khotab Main — OLD `KhotabWaitingList&mode=1`
 * (`WaitingList()`) and its «موافق» → `KhotabAddComments` (`AddComments()`),
 * `modules/Khotab/admin/index.php:2959-3100`, production working copy.
 *
 * The queue is every Khotab comment with `view = 0`, oldest first, 30 a page.
 * Each row is left «لم يراجع» (unchanged), published (`view = 1`) or hidden
 * (`view = 2` — OLD's «مسح التعليق» never deleted the row); every item whose
 * comment changed then has its `comments` counter recounted from its
 * `view = 1` comments, as OLD did.
 *
 * Permissions (owner, 2026-10-21): publishing needs `khotab.approvecomment`,
 * hiding needs `khotab.delcomment` — so holding one never allows the other; a
 * request asking for anything not allowed is refused whole. Only comments
 * still pending are touched (the queue never shows others; OLD would have
 * changed any id posted). One transaction; one audit row per moderated
 * comment, under the Item page's action names.
 */
final class KhotabPendingComments
{
    public const PER_PAGE = 30;

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public static function count(): int
    {
        return DB::connection('main')->table('nuke_islamic_comments')->where('view', 0)->count();
    }

    /**
     * @param  array<int|string, mixed>  $choices  comment id ⇒ 0 | 1 | 2 (OLD's radio values)
     * @return array{published: int, hidden: int, items: int}
     *
     * @throws AuthorizationException a choice the admin may not make
     */
    public function moderate(AdminUser $actor, array $choices, ?string $ip = null): array
    {
        $publish = [];
        $hide = [];
        foreach ($choices as $id => $value) {
            match ((int) $value) {
                1 => $publish[] = (int) $id,
                2 => $hide[] = (int) $id,
                default => null,
            };
        }

        if ($publish !== []) {
            $this->authorizer->authorize($actor, 'khotab.approvecomment');
        }
        if ($hide !== []) {
            $this->authorizer->authorize($actor, 'khotab.delcomment');
        }

        if ($publish === [] && $hide === []) {
            return ['published' => 0, 'hidden' => 0, 'items' => 0];
        }

        return DB::connection('main')->transaction(function () use ($actor, $publish, $hide, $ip) {
            $main = DB::connection('main');
            $pending = fn (array $ids) => $main->table('nuke_islamic_comments')->whereIn('id', $ids)->where('view', 0);

            $rows = $pending(array_merge($publish, $hide))->lockForUpdate()->get(['id', 'khid']);
            $items = $rows->pluck('khid')->map(fn ($id) => (int) $id)->unique()->values()->all();
            $published = $publish === [] ? 0 : $pending($publish)->update(['view' => 1]);
            $hidden = $hide === [] ? 0 : $pending($hide)->update(['view' => 2]);

            $counts = [];
            foreach ($items as $itemId) {
                $counts[$itemId] = $main->table('nuke_islamic_comments')->where('khid', $itemId)->where('view', 1)->count();
                $main->table('nuke_islamic_khotab')->where('id', $itemId)->update(['comments' => $counts[$itemId]]);
            }

            foreach ($rows as $row) {
                $view = in_array((int) $row->id, $publish, true) ? 1 : 2;
                $this->audit->record($actor, $view === 1 ? 'publish_comment' : 'hide_comment', 'khotab', 'comment', (int) $row->id, [
                    'item_id' => (int) $row->khid,
                    'view' => ['from' => 0, 'to' => $view],
                    'item_comments' => $counts[(int) $row->khid],
                    'queue' => true,
                ], $ip);
            }

            return ['published' => $published, 'hidden' => $hidden, 'items' => count($items)];
        });
    }
}
