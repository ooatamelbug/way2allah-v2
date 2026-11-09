<?php

namespace App\Domain\Engagement\Http\Controllers;

use App\Domain\Engagement\Actions\PostPollCommentAction;
use App\Domain\Engagement\Http\Requests\PollCommentRequest;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollComment;
use App\Domain\Engagement\Services\PollCommentThread;
use App\Domain\Identity\Models\VbUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Public poll comments (final public features, owner decision 2026-10-15) —
 * the working behavior of OLD `modules/Surveys/comments.php`.
 *
 * | OLD URL (`.htaccess:884-896`) | OLD behavior | Laravel |
 * |---|---|---|
 * | `survey-comment-{poll}-tid-{id}-{mode}-{order}-{thold}.htm` | `singlecomment()` | `comment()` |
 * | `survey-comment-{poll}-pid-{id}-…` | redirect to the poll's results, where comments were shown | `comment()` → redirect to the poll's comment page |
 * | `survey-commreply-{pid}-{poll}-{mode}-{order}-{thold}.htm` | `reply()` — the reply form | `reply()` |
 * | `survey-showreply-{tid}-{poll}-{pid}-{mode}-{order}-{thold}.htm` | `DisplayTopic()` under `pid` | `showReply()` |
 * | (form, `op` = «استعراض» / «موافق!») | `replyPreview()` / `CreateTopic()` | `submit()` — POST `/polls/{poll}/comments` |
 *
 * The poll's own comment page (`DisplayTopic` at `pid` 0) had no rewrite —
 * OLD reached it through the results page, whose `include` of comments.php
 * is commented out (`Surveys/index.php:66`) — so it gets
 * `/survey-comments-{poll}.htm`, linked from the results page.
 *
 * **Dead, not migrated:** `survey-comments.htm` and
 * `survey-showreply-{tid}-{mode}-{order}-{thold}.htm` carry no poll id, so OLD
 * rendered nothing useful; `DisplayBabies()` is never called; the comment
 * scoring form (`moderate`) — every stored `reason` is 0 and every `score` a
 * creation default, so it was never used; PHP-Nuke karma, journal, private
 * messages, user-info and website links (PHP-Nuke accounts, replaced by
 * vBulletin).
 *
 * **Security corrections:** comments are rendered through
 * `SafeLegacyHtmlRenderer` (OLD echoed them raw); poster emails and IP
 * addresses are never shown (OLD printed emails publicly and IPs to admins);
 * the form is CSRF-protected; names and subjects are shown as written (OLD's
 * `eregi("[a-z0-9]")` check replaced every Arabic-only name in replies with
 * «زائر»).
 *
 * Staff deletion is Phase 4's (`admin.polls.comments.destroy`, super-admin),
 * offered beside each comment through the contextual controls, as OLD's
 * «مسح» link was.
 */
class PollCommentController
{
    public function thread(Poll $poll): View
    {
        return $this->topic($poll, 0, request()->query('mode'), request()->query('order'), request()->query('thold'));
    }

    /** `survey-showreply-{tid}-{poll}-{pid}-{mode}-{order}-{thold}.htm` — `DisplayTopic()` under `pid`. */
    public function showReply(string $spec): View
    {
        [, , $pollId, $pid, $mode, $order, $thold] = $this->parse('/^(\d+)-(\d+)-(\d+)-([a-z]*)-(\d*)-(-?\d*)$/', $spec);

        return $this->topic($this->poll($pollId), (int) $pid, $mode, $order, $thold);
    }

    /** `survey-comment-{poll}-tid|pid-{id}-{mode}-{order}-{thold}.htm`. */
    public function comment(string $spec): View|RedirectResponse
    {
        [, $pollId, $kind, $id, $mode, $order, $thold] = $this->parse('/^(\d+)-(tid|pid)-(\d+)-([a-z]*)-(\d*)-(-?\d*)$/', $spec);
        $settings = $this->enabled();
        $poll = $this->poll($pollId);

        // OLD: a `pid` link redirected to the poll's results, where the comments were then shown.
        if ($kind === 'pid') {
            return redirect()->route('engagement.polls.comments', $poll);
        }

        $comment = PollComment::query()->where('pollID', $poll->pollID)->findOrFail((int) $id);

        return view('engagement.polls.comments.single', [
            'poll' => $poll,
            'comment' => $comment,
            'prefs' => PollCommentThread::preferences($mode, $order, $thold),
            'settings' => $settings,
            'canPost' => $this->canPost($settings),
        ]);
    }

    /** `survey-commreply-{pid}-{poll}-{mode}-{order}-{thold}.htm` — the reply form. */
    public function reply(string $spec): View
    {
        [, $pid, $pollId, $mode, $order, $thold] = $this->parse('/^(\d+)-(\d+)-([a-z]*)-(\d*)-(-?\d*)$/', $spec);
        $settings = $this->enabled();
        $poll = $this->poll($pollId);
        $parent = (int) $pid > 0 ? PollComment::query()->where('pollID', $poll->pollID)->findOrFail((int) $pid) : null;

        return view('engagement.polls.comments.reply', $this->formData($poll, $parent, $settings, PollCommentThread::preferences($mode, $order, $thold)));
    }

    public function submit(PollCommentRequest $request, Poll $poll, PostPollCommentAction $action): View|RedirectResponse
    {
        $settings = $this->enabled();
        $prefs = PollCommentThread::preferences($request->input('mode'), $request->input('order'), $request->input('thold'));
        $pid = (int) $request->input('pid', 0);
        $parent = $pid > 0 ? PollComment::query()->where('pollID', $poll->pollID)->find($pid) : null;

        // OLD CreateTopic(): neither a member nor anonymous posting allowed → refused ("Nice try").
        abort_unless($this->canPost($settings), 403);

        if ($request->input('op') === 'preview') {
            return view('engagement.polls.comments.reply', $this->formData($poll, $parent, $settings, $prefs) + [
                'preview' => ['subject' => (string) $request->input('subject'), 'comment' => (string) $request->input('comment')],
                'xanonpost' => $request->boolean('xanonpost'),
            ]);
        }

        $comment = $action->execute($poll, $this->member(), $request->validated(), $settings['anonpost'], $request->ip());

        return redirect()->to(route('engagement.polls.comments', $poll).'#'.$comment->tid)
            ->with('poll_comment_posted', true);
    }

    private function topic(Poll $poll, int $pid, mixed $mode, mixed $order, mixed $thold): View
    {
        $settings = $this->enabled();
        $prefs = PollCommentThread::preferences($mode, $order, $thold);

        return view('engagement.polls.comments.thread', [
            'poll' => $poll,
            'pid' => $pid,
            'parentPid' => $pid > 0 ? (int) PollComment::query()->where('tid', $pid)->value('pid') : null,
            'comments' => app(PollCommentThread::class)->topLevel((int) $poll->pollID, $pid, $prefs['order'], $prefs['thold']),
            'count' => PollComment::query()->where('pollID', $poll->pollID)->count(),
            'prefs' => $prefs,
            'settings' => $settings,
            'canPost' => $this->canPost($settings),
            'member' => $this->member(),
        ]);
    }

    /**
     * @param  array{enabled: bool, anonpost: bool, limit: int, anonymous: string}  $settings
     * @param  array{mode: string, order: int, thold: int}  $prefs
     * @return array<string, mixed>
     */
    private function formData(Poll $poll, ?PollComment $parent, array $settings, array $prefs): array
    {
        $subject = $parent ? (string) $parent->subject : (string) $poll->pollTitle;

        return [
            'poll' => $poll,
            'parent' => $parent,
            'defaultSubject' => str_contains($subject, 'Re:') ? $subject : 'Re: '.mb_substr($subject, 0, 81),
            'settings' => $settings,
            'prefs' => $prefs,
            'canPost' => $this->canPost($settings),
            'member' => $this->member(),
        ];
    }

    /** @return list<string> the regex groups of a legacy URL segment, or 404 */
    private function parse(string $pattern, string $spec): array
    {
        abort_unless(preg_match($pattern, $spec, $m) === 1, 404);

        return $m;
    }

    private function poll(string $pollId): Poll
    {
        return Poll::query()->findOrFail((int) $pollId);
    }

    /** @return array{enabled: bool, anonpost: bool, limit: int, anonymous: string} — 404 while `pollcomm` is off. */
    private function enabled(): array
    {
        $settings = PollCommentThread::settings();
        abort_unless($settings['enabled'], 404);

        return $settings;
    }

    /** @param array{anonpost: bool} $settings — OLD: `anonpost` = 1, or a member. */
    private function canPost(array $settings): bool
    {
        return $settings['anonpost'] || $this->member() !== null;
    }

    private function member(): ?VbUser
    {
        $user = Auth::guard('vbulletin')->user();

        return $user instanceof VbUser ? $user : null;
    }
}
