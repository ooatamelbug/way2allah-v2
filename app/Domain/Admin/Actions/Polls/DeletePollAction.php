<?php

namespace App\Domain\Admin\Actions\Polls;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Engagement\Models\Poll;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 4 — remove a poll. Replaces OLD
 * `poll_removePosted()` (`modules/Surveys/admin/index.php:244-250`):
 * delete the `nuke_poll_desc` row and every `nuke_poll_data` row of the
 * poll — its choices and their vote counts — whatever votes it holds.
 *
 * Exactly OLD's scope: the poll's comments (`nuke_pollcomments`) and vote
 * throttle rows (`nuke_poll_check`) are not touched. Those comments become
 * unreachable, as they did in OLD; nothing deletes them in bulk here.
 *
 * Votes cannot be recovered after a delete, so the audit row keeps a full
 * snapshot of the poll and each choice's count.
 */
final class DeletePollAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, Poll $poll, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'polls.managepolls', $poll);

        DB::connection('main')->transaction(function () use ($actor, $poll, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_poll_desc')->where('pollID', $poll->getKey())->lockForUpdate()->first();
            abort_if($row === null, 404);

            $options = $main->table('nuke_poll_data')->where('pollID', $row->pollID)->orderBy('voteID')->lockForUpdate()
                ->get(['voteID', 'optionText', 'optionCount']);

            $main->table('nuke_poll_data')->where('pollID', $row->pollID)->delete();
            $main->table('nuke_poll_desc')->where('pollID', $row->pollID)->delete();

            $this->audit->record($actor, 'del_poll', 'polls', 'poll', (int) $row->pollID, [
                'deleted' => [
                    'title' => PollAuditText::of($row->pollTitle),
                    'planguage' => (string) $row->planguage,
                    'time_stamp' => (int) $row->timeStamp,
                    'voters' => (int) $row->voters,
                    'artid' => (int) $row->artid,
                    'comments' => (int) $row->comments,
                    'options' => $options->map(fn ($o) => [
                        'vote_id' => (int) $o->voteID,
                        'text' => PollAuditText::of($o->optionText),
                        'count' => (int) $o->optionCount,
                    ])->all(),
                ],
            ], $ip);
        });
    }
}
