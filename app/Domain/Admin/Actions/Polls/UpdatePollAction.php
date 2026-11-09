<?php

namespace App\Domain\Admin\Actions\Polls;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Engagement\Models\Poll;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 4 — edit a poll. Replaces OLD `savepoll()`
 * (`modules/Surveys/admin/index.php:335-352`).
 *
 * Reproduced:
 * - the title is rewritten; `planguage` is kept as stored (with
 *   `multilingual` = 0, OLD's edit form carried it back unchanged in a
 *   hidden field);
 * - each choice text is rewritten by `WHERE pollID = ? AND voteID = ?`, so
 *   every row of that slot changes, and a slot with no row stays absent. A
 *   slot the form leaves out is written as `''`, exactly as OLD's
 *   `$optiontextN` defaulted;
 * - `optionCount`, `voters`, `timeStamp`, `artid` and `comments` are never
 *   touched: editing a poll keeps its votes and results.
 *
 * Only what actually changes is written. A submission identical to the
 * stored poll writes nothing and records no audit row.
 */
final class UpdatePollAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<int|string, string|null>  $options  voteID => text
     * @return bool whether anything changed
     */
    public function execute(AdminUser $actor, Poll $poll, string $title, array $options, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, 'polls.managepolls', $poll);

        return DB::connection('main')->transaction(function () use ($actor, $poll, $title, $options, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_poll_desc')->where('pollID', $poll->getKey())->lockForUpdate()->first(['pollID', 'pollTitle']);
            abort_if($row === null, 404);

            $slots = $main->table('nuke_poll_data')->where('pollID', $row->pollID)
                ->whereBetween('voteID', [1, CreatePollAction::SLOTS])->lockForUpdate()
                ->get(['voteID', 'optionText'])->groupBy('voteID');

            $before = [];
            $after = [];

            if ((string) $row->pollTitle !== $title) {
                $main->table('nuke_poll_desc')->where('pollID', $row->pollID)->update(['pollTitle' => $title]);
                $before['title'] = PollAuditText::of($row->pollTitle);
                $after['title'] = PollAuditText::of($title);
            }

            foreach ($slots as $voteId => $rows) {
                $text = (string) ($options[$voteId] ?? '');

                if ($rows->every(fn ($r) => (string) $r->optionText === $text)) {
                    continue;
                }

                $main->table('nuke_poll_data')->where('pollID', $row->pollID)->where('voteID', $voteId)->update(['optionText' => $text]);
                $before['options'][(string) $voteId] = PollAuditText::of($rows->first()->optionText);
                $after['options'][(string) $voteId] = PollAuditText::of($text);
            }

            if ($after === []) {
                return false;
            }

            $this->audit->record($actor, 'edit_poll', 'polls', 'poll', (int) $row->pollID, [
                'before' => $before,
                'after' => $after,
            ], $ip);

            return true;
        });
    }
}
