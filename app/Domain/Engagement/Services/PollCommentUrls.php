<?php

namespace App\Domain\Engagement\Services;

/**
 * The public poll-comment links, in OLD's URL shapes (`.htaccess:884-893`),
 * carrying the reader's display preferences as OLD's links did.
 */
final class PollCommentUrls
{
    /** @param array{mode: string, order: int, thold: int} $prefs */
    public static function reply(int $pollId, int $pid, array $prefs): string
    {
        return "/survey-commreply-{$pid}-{$pollId}-".self::tail($prefs).'.htm';
    }

    /** @param array{mode: string, order: int, thold: int} $prefs */
    public static function showReply(int $pollId, int $tid, int $pid, array $prefs): string
    {
        return "/survey-showreply-{$tid}-{$pollId}-{$pid}-".self::tail($prefs).".htm#{$tid}";
    }

    /** @param array{mode: string, order: int, thold: int} $prefs */
    public static function single(int $pollId, int $tid, array $prefs): string
    {
        return "/survey-comment-{$pollId}-tid-{$tid}-".self::tail($prefs).'.htm';
    }

    public static function thread(int $pollId): string
    {
        return "/survey-comments-{$pollId}.htm";
    }

    /** @param array{mode: string, order: int, thold: int} $prefs */
    private static function tail(array $prefs): string
    {
        return $prefs['mode'].'-'.$prefs['order'].'-'.$prefs['thold'];
    }
}
