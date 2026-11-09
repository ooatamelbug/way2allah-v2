<?php

namespace App\Domain\Engagement\Services;

use App\Domain\Engagement\Models\PollComment;
use Illuminate\Support\Facades\DB;

/**
 * Public poll comments — reading (final public features, owner decision
 * 2026-10-15), from OLD `modules/Surveys/comments.php`.
 *
 * - **Top level** (`DisplayTopic`): `pollID` and `pid`, `score >= thold`;
 *   `order` 1 = newest first, 2 = highest score first, otherwise stored order
 *   (OLD had no ORDER BY, i.e. insertion order — `tid`).
 * - **Children** (`DisplayKids`): `pid = tid ORDER BY date, tid`, the same
 *   `score >= thold` filter.
 * - **Display mode** (`thread` default, `nested`, `flat`, `nocomments`) only
 *   changes how children are shown; `nocomments` also hides the reply button.
 *
 * PHP-Nuke settings come from `nuke_config` exactly as OLD read them:
 * `pollcomm` (comments on), `anonpost` (anonymous posting allowed),
 * `commentlimit` (truncate longer comments with «read the rest»), `anonymous`
 * (the name shown for a nameless comment).
 *
 * The tree walk is cycle-safe (a `pid` pointing back up the thread stops
 * there), which OLD's recursion was not.
 */
final class PollCommentThread
{
    public const MODES = ['thread', 'nested', 'flat', 'nocomments'];

    /** @return array{enabled: bool, anonpost: bool, limit: int, anonymous: string} */
    public static function settings(): array
    {
        $row = (array) (DB::connection('main')->table('nuke_config')->first(['anonpost', 'pollcomm', 'commentlimit', 'anonymous']) ?? []);

        return [
            'enabled' => (int) ($row['pollcomm'] ?? 0) === 1,
            'anonpost' => (int) ($row['anonpost'] ?? 0) === 1,
            'limit' => max(1, (int) ($row['commentlimit'] ?? 4096)),
            'anonymous' => trim((string) ($row['anonymous'] ?? '')) !== '' ? (string) $row['anonymous'] : 'زائر',
        ];
    }

    /** @return array{mode: string, order: int, thold: int} OLD's display preferences, normalised. */
    public static function preferences(mixed $mode, mixed $order, mixed $thold): array
    {
        $mode = is_string($mode) && in_array($mode, self::MODES, true) ? $mode : 'thread';
        $order = in_array((string) $order, ['1', '2'], true) ? (int) $order : 0;
        $thold = is_numeric($thold) ? max(-1, min(5, (int) $thold)) : 0;

        return ['mode' => $mode, 'order' => $order, 'thold' => $thold];
    }

    /**
     * Top-level comments under `$pid`, each with its descendants.
     *
     * @return list<array{comment: PollComment, children: list<array<string, mixed>>}>
     */
    public function topLevel(int $pollId, int $pid, int $order, int $thold): array
    {
        $query = PollComment::query()->where('pollID', $pollId)->where('pid', $pid)->where('score', '>=', $thold);
        $query = match ($order) {
            1 => $query->orderByDesc('date')->orderByDesc('tid'),
            2 => $query->orderByDesc('score')->orderBy('tid'),
            default => $query->orderBy('tid'),
        };

        /** @var array<int, list<PollComment>> $byParent */
        $byParent = [];
        foreach (PollComment::query()->where('pollID', $pollId)->where('score', '>=', $thold)->orderBy('date')->orderBy('tid')->get() as $comment) {
            $byParent[(int) $comment->pid][] = $comment;
        }

        return $query->get()->map(fn (PollComment $c) => [
            'comment' => $c,
            'children' => $this->children($byParent, (int) $c->tid, [(int) $c->tid => true]),
        ])->all();
    }

    /**
     * @param  array<int, list<PollComment>>  $byParent
     * @param  array<int, true>  $seen
     * @return list<array{comment: PollComment, children: list<array<string, mixed>>}>
     */
    private function children(array $byParent, int $tid, array $seen): array
    {
        $out = [];
        foreach ($byParent[$tid] ?? [] as $child) {
            $childId = (int) $child->tid;
            if (isset($seen[$childId])) {
                continue;
            }
            $out[] = ['comment' => $child, 'children' => $this->children($byParent, $childId, $seen + [$childId => true])];
        }

        return $out;
    }
}
