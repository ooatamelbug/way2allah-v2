<?php

namespace App\Domain\Admin\Actions\Polls;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Engagement\Models\PollComment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 4 — remove a poll comment. Replaces OLD
 * `RemovePollComment` → `removePollSubComments()`
 * (`admin/modules/comments.php:76-112`).
 *
 * Deletion scope, reproduced: the comment and its whole reply thread —
 * every descendant reached through `pid`, at any depth.
 *
 * Counter, intentionally corrected (plan §23.4, owner instruction): OLD
 * decremented `nuke_poll_desc.comments` once in RemovePollComment, once per
 * descendant, and once more per recursion level using a stale `$row`, with
 * no floor — a comment with replies drove the counter too low, even
 * negative. Here each affected poll's `comments` is decremented by exactly
 * the number of its rows deleted, floored at 0. Only polls that lost rows
 * are touched; no other counter is repaired.
 *
 * Gate, reproduced: OLD's comments admin ran for `radminsuper` only
 * (`admin/modules/comments.php:21`) — here the super-admin role, re-checked
 * at this boundary as well as by the route.
 */
final class DeletePollCommentAction
{
    public function __construct(private readonly AdminAuditRecorder $audit) {}

    /** @return list<int> the deleted comment ids */
    public function execute(AdminUser $actor, PollComment $comment, ?string $ip = null): array
    {
        if (! $actor->hasRole('super-admin')) {
            throw new AuthorizationException;
        }

        return DB::connection('main')->transaction(function () use ($actor, $comment, $ip) {
            $main = DB::connection('main');
            $root = $main->table('nuke_pollcomments')->where('tid', $comment->getKey())->lockForUpdate()->first(['tid', 'pollID']);
            abort_if($root === null, 404);

            // Breadth-first over pid, tolerating cycles (OLD's recursion would not).
            $pollOf = [(int) $root->tid => (int) $root->pollID];
            $frontier = [(int) $root->tid];
            while ($frontier !== []) {
                $children = $main->table('nuke_pollcomments')->whereIn('pid', $frontier)->lockForUpdate()->get(['tid', 'pollID']);
                $frontier = [];
                foreach ($children as $child) {
                    if (! isset($pollOf[(int) $child->tid])) {
                        $pollOf[(int) $child->tid] = (int) $child->pollID;
                        $frontier[] = (int) $child->tid;
                    }
                }
            }

            $deleted = array_keys($pollOf);
            sort($deleted);
            $main->table('nuke_pollcomments')->whereIn('tid', $deleted)->delete();

            $counters = [];
            foreach (array_count_values($pollOf) as $pollId => $removed) {
                $poll = $main->table('nuke_poll_desc')->where('pollID', $pollId)->lockForUpdate()->first(['pollID', 'comments']);
                if ($poll === null) {
                    continue;   // comment of a removed poll: no counter left to adjust
                }
                $before = (int) $poll->comments;
                $after = max(0, $before - $removed);
                $main->table('nuke_poll_desc')->where('pollID', $pollId)->update(['comments' => $after]);
                $counters[(string) $pollId] = ['before' => $before, 'after' => $after, 'removed' => $removed];
            }

            $this->audit->record($actor, 'del_poll_comment', 'polls', 'comment', (int) $root->tid, [
                'poll_id' => (int) $root->pollID,
                'deleted_tids' => $deleted,
                'deleted_count' => count($deleted),
                'counters' => $counters,
            ], $ip);

            return $deleted;
        });
    }
}
