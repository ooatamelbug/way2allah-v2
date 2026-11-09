<?php

namespace App\Domain\Engagement\Actions;

use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollComment;
use App\Domain\Identity\Models\VbUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts a public poll comment — OLD `CreateTopic()`
 * (`modules/Surveys/comments.php:686-767`).
 *
 * Kept from OLD:
 * - who may post: a member (OLD `is_user()` = the vBulletin session, as
 *   Phase 7), or anyone when `anonpost` = 1; a staff session alone may not
 *   (OLD answered "Nice try");
 * - a member posts under their username with `score` 1; an anonymous post
 *   (or a member's «ارسال الموضوع باسم زائر» when `anonpost` = 1) has no name
 *   and `score` 0;
 * - the subject is plain text; the parent (`pid`) is 0 or a comment of the
 *   same poll; `poll_desc.comments + 1`; the poster's IP is stored in
 *   `host_name` (never displayed).
 *
 * Corrections:
 * - the comment is stored as escaped plain text (line breaks kept). OLD's
 *   form said «كود HTML غير مسموح» while its filter still let HTML through;
 * - no email or website is stored or shown (OLD copied them from the
 *   PHP-Nuke account and printed the email publicly);
 * - insert and counter in one transaction.
 *
 * Not reproduced (dead): PHP-Nuke karma routing to the moderation queue (it
 * read `nuke_users.karma`; the queue table is empty), `update_points()`, and
 * `format_url()`'s link rewriting (it needed `ereg` and served HTML input).
 */
final class PostPollCommentAction
{
    /** @param array{subject?: string|null, comment: string, pid?: int|string|null, xanonpost?: mixed} $data */
    public function execute(Poll $poll, ?VbUser $member, array $data, bool $anonymousAllowed, ?string $ip): PollComment
    {
        $pid = (int) ($data['pid'] ?? 0);

        if ($pid > 0 && ! PollComment::query()->where('tid', $pid)->where('pollID', $poll->pollID)->exists()) {
            throw ValidationException::withMessages(['pid' => 'التعليق الذي ترد عليه غير موجود']);
        }

        $asMember = $member !== null && ! ($anonymousAllowed && ! empty($data['xanonpost']));
        $name = $member !== null && $asMember ? mb_substr((string) $member->username, 0, 60) : '';

        return DB::connection('main')->transaction(function () use ($poll, $name, $data, $pid, $asMember, $ip) {
            $comment = new PollComment;
            $comment->forceFill([
                'pid' => $pid,
                'pollID' => (int) $poll->pollID,
                'date' => now()->format('Y-m-d H:i:s'),
                'name' => $name,
                'email' => '',
                'url' => '',
                'host_name' => mb_substr((string) $ip, 0, 60),
                'subject' => mb_substr(trim(strip_tags((string) ($data['subject'] ?? ''))), 0, 60),
                'comment' => nl2br(e(trim((string) $data['comment'])), false),
                'score' => $asMember ? 1 : 0,
                'reason' => 0,
                'last_moderation_ip' => '',
            ])->save();

            Poll::query()->whereKey($poll->pollID)->increment('comments');

            return $comment;
        });
    }
}
