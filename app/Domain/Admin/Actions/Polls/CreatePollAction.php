<?php

namespace App\Domain\Admin\Actions\Polls;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 4 — create a poll. Replaces OLD
 * `poll_createPosted()` (`modules/Surveys/admin/index.php:181-207`).
 *
 * Reproduced:
 * - one `nuke_poll_desc` row: the title, `timeStamp = time()`, `voters = 0`,
 *   `artid = 0` (a standalone poll, the only kind the public pages list),
 *   `comments = 0`, and `planguage` = the site language
 *   (`nuke_config.language`, which is what OLD's `$language` held when
 *   `multilingual` is 0);
 * - **always twelve** `nuke_poll_data` rows, `voteID` 1–12, `optionCount = 0`,
 *   blank texts stored as `''`. OLD's loop inserted every slot, blank or not,
 *   and its edit operation only updates rows that exist, so the twelve slots
 *   are what make a later edit able to fill a blank choice.
 *
 * Corrected (plan §23): OLD found the new poll's id with
 * `SELECT pollID … WHERE pollTitle = '<title>'`, so a duplicate title
 * attached the new choices to the OLDER poll (the 24/36/84-row polls in the
 * data). The inserted id is used instead.
 *
 * Not reproduced: the optional "announce poll" News story. OLD wrote an
 * unlinked `nuke_stories` row (its `pollID` = 0; the poll's `artid` stays 0)
 * into a News system with no installed module (plan §23.3).
 */
final class CreatePollAction
{
    public const SLOTS = 12;

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array<int|string, string|null> $options voteID => text */
    public function execute(AdminUser $actor, string $title, array $options, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'polls.managepolls');

        return DB::connection('main')->transaction(function () use ($actor, $title, $options, $ip) {
            $main = DB::connection('main');
            $language = (string) $main->table('nuke_config')->value('language');

            $pollId = (int) $main->table('nuke_poll_desc')->insertGetId([
                'pollTitle' => $title,
                'timeStamp' => time(),
                'voters' => 0,
                'planguage' => $language,
                'artid' => 0,
                'comments' => 0,
            ], 'pollID');

            $rows = [];
            $audited = [];
            for ($voteId = 1; $voteId <= self::SLOTS; $voteId++) {
                $text = (string) ($options[$voteId] ?? '');
                $rows[] = ['pollID' => $pollId, 'optionText' => $text, 'optionCount' => 0, 'voteID' => $voteId];
                if ($text !== '') {
                    $audited[(string) $voteId] = PollAuditText::of($text);
                }
            }
            $main->table('nuke_poll_data')->insert($rows);

            $this->audit->record($actor, 'add_poll', 'polls', 'poll', $pollId, [
                'title' => PollAuditText::of($title),
                'planguage' => $language,
                'options' => $audited,
            ], $ip);

            return $pollId;
        });
    }
}
