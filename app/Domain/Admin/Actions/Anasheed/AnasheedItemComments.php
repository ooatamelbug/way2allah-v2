<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedComment;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Services\GeoIpLookup;
use Illuminate\Support\Facades\DB;

/**
 * The comment controls of the Anasheed item page (`Comments()`,
 * `modules/Anasheed/index.php`; handlers in `modules/Anasheed/admin/index.php`,
 * production working copy):
 *
 * - «إضافة تعليق إداري» — OLD `AnasheedAddModComment`: a published comment
 *   (`view = 1`) with `uid = 0` and an empty name, which the page shows as
 *   «مشرف التعليقات»; the IP, its country code (`ips`) and the time.
 *   `anasheed.addcomment`. **Owner-approved correction (2026-10-23):** OLD did
 *   not update the item's `comments` counter; Laravel recounts it (the item's
 *   `view = 1` comments), as OLD's own publish / hide did.
 * - «(تعديل التعليق)» → «نشر التعليق للمادة» — OLD `AddComment`: the text is
 *   replaced, `view = 1`, and `comments` becomes the `view = 1` count.
 *   `anasheed.addcomment`, as OLD's `GreenZone('addcomment')` (which also
 *   gates the `WaitingList(id)` page itself).
 * - … → «مسح التعليق للمادة» — OLD `DelComment`: `view = 2` (hidden, not
 *   deleted; the text is not saved) and the same recount.
 *   `anasheed.delcomment`, as OLD's `GreenZone('delcomment')`.
 *
 * The text is stored as submitted (Blade escapes it on output); OLD's
 * `filter_text()` / `nl2br()` markup handling is not reproduced. Each write is
 * one transaction and audited (the text digested).
 */
final class AnasheedItemComments
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly GeoIpLookup $geoIp,
    ) {}

    public function addModerator(AdminUser $actor, AnasheedItem $item, string $comment, string $ip): int
    {
        $this->authorizer->authorize($actor, 'anasheed.addcomment', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $comment, $ip) {
            $main = DB::connection('main');
            abort_if($main->table('nuke_anasheed_anasheed')->where('id', $item->id)->lockForUpdate()->value('id') === null, 404);

            $id = (int) $main->table('nuke_anasheed_comments')->insertGetId([
                'khid' => (int) $item->id,
                'uid' => 0,
                'uname' => '',
                'ip' => substr($ip, 0, 15),
                'view' => 1,
                'code' => $this->geoIp->codeForIp($ip),
                'mytime' => time(),
                'comment' => $comment,
            ]);
            $count = self::recount((int) $item->id);

            $this->audit->record($actor, 'add_mod_comment', 'anasheed', 'comment', $id, [
                'item_id' => (int) $item->id,
                'comment' => AuditText::digest($comment),
                'item_comments' => $count,
            ], $ip);

            return $id;
        });
    }

    public function publish(AdminUser $actor, AnasheedComment $comment, string $text, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'anasheed.addcomment', $comment);

        return $this->moderate($actor, $comment, ['view' => 1, 'comment' => $text], 'publish_comment', $ip);
    }

    public function hide(AdminUser $actor, AnasheedComment $comment, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'anasheed.delcomment', $comment);

        return $this->moderate($actor, $comment, ['view' => 2], 'hide_comment', $ip);
    }

    /**
     * @param  array<string, scalar>  $set
     * @return int the item id
     */
    private function moderate(AdminUser $actor, AnasheedComment $comment, array $set, string $action, ?string $ip): int
    {
        return DB::connection('main')->transaction(function () use ($actor, $comment, $set, $action, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_anasheed_comments')->where('id', $comment->id)->lockForUpdate()->first(['id', 'khid', 'view', 'comment']);
            abort_if($row === null, 404);

            $main->table('nuke_anasheed_comments')->where('id', $row->id)->update($set);
            $count = self::recount((int) $row->khid);

            $this->audit->record($actor, $action, 'anasheed', 'comment', (int) $row->id, [
                'item_id' => (int) $row->khid,
                'view' => ['from' => (int) $row->view, 'to' => (int) $set['view']],
                'text_changed' => isset($set['comment']) && (string) $set['comment'] !== (string) $row->comment,
                'item_comments' => $count,
            ], $ip);

            return (int) $row->khid;
        });
    }

    /** OLD's recount: the item's `comments` = its `view = 1` comments. */
    private static function recount(int $itemId): int
    {
        $main = DB::connection('main');
        $count = $main->table('nuke_anasheed_comments')->where('khid', $itemId)->where('view', 1)->count();
        $main->table('nuke_anasheed_anasheed')->where('id', $itemId)->update(['comments' => $count]);

        return $count;
    }
}
