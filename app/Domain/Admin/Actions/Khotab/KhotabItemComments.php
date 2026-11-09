<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Comment;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Services\GeoIpLookup;
use Illuminate\Support\Facades\DB;

/**
 * The comment controls of the item page (`Comments()`,
 * `modules/Khotab/index.php`), production working copy:
 *
 * - «إضافة تعليق إداري» — OLD `AddModComment`: a published comment
 *   (`view = 1`) with `uid = 0` and an empty name, which the page shows as
 *   «مشرف التعليقات»; the IP, its country code (`ips`) and the time. OLD did
 *   not update the item's `comments` counter, and neither does this. OLD
 *   checked nothing; the owner mapped it to `khotab.approvecomment`.
 * - «(تعديل التعليق)» → «نشر التعليـق» — OLD `AddComment`: the comment's
 *   text is replaced, `view = 1`, and the item's `comments` becomes its count
 *   of `view = 1` comments. `khotab.approvecomment`.
 * - … → «مسح التعليـق» — OLD `DelComment`: `view = 2` (hidden, not
 *   deleted) and the same recount. `khotab.delcomment`.
 *
 * Each write is one transaction and audited (the comment text digested).
 */
final class KhotabItemComments
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly GeoIpLookup $geoIp,
    ) {}

    public function addModerator(AdminUser $actor, KhotabItem $item, string $comment, string $ip): int
    {
        $this->authorizer->authorize($actor, 'khotab.approvecomment', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $comment, $ip) {
            $main = DB::connection('main');
            abort_unless($main->table('nuke_islamic_khotab')->where('id', $item->id)->exists(), 404);

            $id = (int) $main->table('nuke_islamic_comments')->insertGetId([
                'khid' => (int) $item->id,
                'uid' => 0,
                'uname' => '',
                // varchar(15), as OLD's REMOTE_ADDR always fitted.
                'ip' => substr($ip, 0, 15),
                'view' => 1,
                'code' => $this->geoIp->codeForIp($ip),
                'mytime' => time(),
                'comment' => $comment,
            ]);

            $this->audit->record($actor, 'add_mod_comment', 'khotab', 'comment', $id, [
                'item_id' => (int) $item->id,
                'comment' => AuditText::digest($comment),
            ], $ip);

            return $id;
        });
    }

    public function publish(AdminUser $actor, Comment $comment, string $text, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.approvecomment', $comment);

        return $this->moderate($actor, $comment, ['view' => 1, 'comment' => $text], 'publish_comment', $ip);
    }

    public function hide(AdminUser $actor, Comment $comment, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.delcomment', $comment);

        return $this->moderate($actor, $comment, ['view' => 2], 'hide_comment', $ip);
    }

    /**
     * @param  array<string, scalar>  $set
     * @return int the item id
     */
    private function moderate(AdminUser $actor, Comment $comment, array $set, string $action, ?string $ip): int
    {
        return DB::connection('main')->transaction(function () use ($actor, $comment, $set, $action, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_comments')->where('id', $comment->id)->lockForUpdate()->first(['id', 'khid', 'view', 'comment']);
            abort_if($row === null, 404);

            $main->table('nuke_islamic_comments')->where('id', $row->id)->update($set);
            $count = $main->table('nuke_islamic_comments')->where('khid', $row->khid)->where('view', 1)->count();
            $main->table('nuke_islamic_khotab')->where('id', $row->khid)->update(['comments' => $count]);

            $this->audit->record($actor, $action, 'khotab', 'comment', (int) $row->id, [
                'item_id' => (int) $row->khid,
                'view' => ['from' => (int) $row->view, 'to' => (int) $set['view']],
                'text_changed' => isset($set['comment']) && (string) $set['comment'] !== (string) $row->comment,
                'item_comments' => $count,
            ], $ip);

            return (int) $row->khid;
        });
    }
}
