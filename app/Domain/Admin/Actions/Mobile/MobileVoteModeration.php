<?php

namespace App\Domain\Admin\Actions\Mobile;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Mobile\MobileVote;
use App\Domain\Content\Services\Mobile\MobileRating;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — ratings and comments. Replaces OLD
 * `DownloadsDelVote`, `DownloadsDelComment` and `DownloadsCleanVotes`:
 *
 * - delete vote: the vote row is removed and the material's counters are
 *   recomputed from its remaining votes;
 * - delete comment: the vote stays, its comment is blanked, and the
 *   material's `totalcomments` moves −1 (floored at 0 — OLD had no floor);
 * - recalculate: an explicit staff repair — every material that has vote
 *   rows is recomputed from them. Materials without vote rows (whose
 *   historical rows are gone) are not touched.
 */
final class MobileVoteModeration extends MobileAdminAction
{
    public function deleteVote(AdminUser $actor, MobileVote $vote, ?string $ip = null): void
    {
        $this->authorize($actor, $vote);

        DB::connection('main')->transaction(function () use ($actor, $vote, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_downloads_votedata')->where('ratingdbid', $vote->getKey())->lockForUpdate()->first();
            abort_if($row === null, 404);
            $before = $main->table('nuke_downloads_downloads')->where('lid', $row->ratinglid)->lockForUpdate()
                ->first(['downloadratingsummary', 'totalvotes', 'totalcomments']);

            $main->table('nuke_downloads_votedata')->where('ratingdbid', $row->ratingdbid)->delete();
            $after = $before === null ? null : MobileRating::recompute((int) $row->ratinglid);

            $this->record($actor, 'del_mobile_vote', 'mobile_vote', (int) $row->ratingdbid, [
                'lid' => (int) $row->ratinglid,
                'voter' => self::text($row->ratinguser),
                'rating' => (int) $row->rating,
                'had_comment' => trim((string) $row->ratingcomments) !== '',
                'counters_before' => $before === null ? null : ['summary' => (float) $before->downloadratingsummary, 'votes' => (int) $before->totalvotes, 'comments' => (int) $before->totalcomments],
                'counters_after' => $after,
            ], $ip);
        });
    }

    public function deleteComment(AdminUser $actor, MobileVote $vote, ?string $ip = null): bool
    {
        $this->authorize($actor, $vote);

        return DB::connection('main')->transaction(function () use ($actor, $vote, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_downloads_votedata')->where('ratingdbid', $vote->getKey())->lockForUpdate()->first();
            abort_if($row === null, 404);
            if (trim((string) $row->ratingcomments) === '') {
                return false;
            }

            $main->table('nuke_downloads_votedata')->where('ratingdbid', $row->ratingdbid)->update(['ratingcomments' => '']);
            $material = $main->table('nuke_downloads_downloads')->where('lid', $row->ratinglid)->lockForUpdate()->first(['lid', 'totalcomments']);
            $counter = null;
            if ($material !== null) {
                $counter = ['before' => (int) $material->totalcomments, 'after' => max(0, (int) $material->totalcomments - 1)];
                $main->table('nuke_downloads_downloads')->where('lid', $material->lid)->update(['totalcomments' => $counter['after']]);
            }

            $this->record($actor, 'del_mobile_comment', 'mobile_vote', (int) $row->ratingdbid, [
                'lid' => (int) $row->ratinglid,
                'voter' => self::text($row->ratinguser),
                'comment' => ['sha256' => hash('sha256', (string) $row->ratingcomments), 'length' => mb_strlen((string) $row->ratingcomments)],
                'totalcomments' => $counter,
            ], $ip);

            return true;
        });
    }

    /** @return int materials whose counters changed */
    public function recalculate(AdminUser $actor, ?string $ip = null): int
    {
        $this->authorize($actor);

        return DB::connection('main')->transaction(function () use ($actor, $ip) {
            $main = DB::connection('main');
            $changed = 0;
            foreach ($main->table('nuke_downloads_votedata')->distinct()->orderBy('ratinglid')->pluck('ratinglid') as $lid) {
                $before = $main->table('nuke_downloads_downloads')->where('lid', $lid)->lockForUpdate()->first(['downloadratingsummary', 'totalvotes', 'totalcomments']);
                if ($before === null) {
                    continue;
                }
                $after = MobileRating::recompute((int) $lid);
                $old = ['downloadratingsummary' => (int) $before->downloadratingsummary, 'totalvotes' => (int) $before->totalvotes, 'totalcomments' => (int) $before->totalcomments];
                if ($old === $after && (float) $before->downloadratingsummary === (float) $after['downloadratingsummary']) {
                    continue;
                }
                $this->record($actor, 'recalc_mobile_rating', 'mobile_material', (int) $lid, ['before' => $old, 'after' => $after], $ip);
                $changed++;
            }

            return $changed;
        });
    }
}
