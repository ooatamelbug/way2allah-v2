<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Polls\CreatePollAction;
use App\Domain\Admin\Actions\Polls\DeletePollAction;
use App\Domain\Admin\Actions\Polls\DeletePollCommentAction;
use App\Domain\Admin\Actions\Polls\UpdatePollAction;
use App\Domain\Admin\Http\Requests\Polls\PollRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollComment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OLD Admin Migration Phase 4 — PHP-Nuke poll administration
 * (`nuke_poll_*`). Replaces Gen 1 `modules/Surveys/admin` (create,
 * polledit_select / polledit / savepoll, remove / removePosted) and
 * `RemovePollComment`. Separate from the custom-survey engine
 * (`SurveyController`, `nuke_survey*`), which it does not touch.
 *
 * OLD's two pickers (`polledit_select`, `remove`) become the one list with
 * Edit and Delete actions — the same capability, not a new one.
 */
class PollAdminController
{
    /** OLD's pickers listed every poll `ORDER BY timeStamp`, title and language. */
    public function index(): View
    {
        return view('admin.polls.index', [
            'polls' => Poll::query()->orderBy('timeStamp')->orderBy('pollID')
                ->get(['pollID', 'pollTitle', 'timeStamp', 'planguage', 'voters', 'artid', 'comments']),
            'canModerateComments' => $this->actor()->hasRole('super-admin'),
        ]);
    }

    public function create(): View
    {
        return view('admin.polls.create', ['slots' => CreatePollAction::SLOTS]);
    }

    public function store(PollRequest $request, CreatePollAction $action): RedirectResponse
    {
        $id = $action->execute($this->actor(), (string) $request->validated('pollTitle'), $request->options(), $request->ip());

        return redirect()->route('admin.polls.edit', $id)->with('success', "تم إنشاء الإستفتاء #{$id}");
    }

    /** OLD polledit: title, then each existing slot with its vote count. */
    public function edit(Poll $poll): View
    {
        return view('admin.polls.edit', [
            'poll' => $poll,
            'slots' => $poll->options()->whereBetween('voteID', [1, CreatePollAction::SLOTS])->get()->groupBy('voteID'),
            'canModerateComments' => $this->actor()->hasRole('super-admin'),
        ]);
    }

    public function update(PollRequest $request, Poll $poll, UpdatePollAction $action): RedirectResponse
    {
        $changed = $action->execute($this->actor(), $poll, (string) $request->validated('pollTitle'), $request->options(), $request->ip());

        return redirect()->route('admin.polls.edit', $poll)
            ->with($changed ? 'success' : 'error', $changed ? 'تم حفظ التغييرات' : 'لم يتغير شيء');
    }

    public function destroy(Request $request, Poll $poll, DeletePollAction $action): RedirectResponse
    {
        $id = (int) $poll->pollID;
        $action->execute($this->actor(), $poll, $request->ip());

        return redirect()->route('admin.polls.index')->with('success', "تم حذف الإستفتاء #{$id}");
    }

    /** Super-admin only, as OLD's comments admin. Threads shown in tid order. */
    public function comments(Poll $poll): View
    {
        $comments = PollComment::query()->where('pollID', $poll->pollID)->orderBy('tid')
            ->get(['tid', 'pid', 'pollID', 'date', 'name', 'subject', 'comment']);

        return view('admin.polls.comments', ['poll' => $poll, 'comments' => $comments]);
    }

    public function destroyComment(Request $request, PollComment $comment, DeletePollCommentAction $action): RedirectResponse
    {
        $pollId = (int) $comment->pollID;
        $deleted = $action->execute($this->actor(), $comment, $request->ip());

        $redirect = Poll::query()->whereKey($pollId)->exists()
            ? redirect()->route('admin.polls.comments', $pollId)
            : redirect()->route('admin.polls.index');

        return $redirect->with('success', 'تم حذف '.count($deleted).' تعليق');
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
