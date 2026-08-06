<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Survey;
use App\Domain\Admin\Models\SurveyAnswer;
use App\Domain\Admin\Models\SurveyQuestion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Replaces `admincp/survey/*.php` — Roadmap task 5.2, "the best 1:1 port
 * candidate in the whole audit" (Blueprint §4). Full admin CRUD lifecycle;
 * this engine has no public voting UI in this codebase at all (`Survey`'s
 * own docblock) — nothing to build here beyond admin management.
 *
 * `add_survey.php`'s "survey moderators" checkbox list queried
 * `nuke_authors WHERE permissions LIKE '%s:9:"modsurvey";s:2:"on"%'` — the
 * fragile serialized-string-match this migration's whole permission system
 * replaces (decision-log #9, ADR-0010 "Can be replaced"). Here it queries
 * `AdminUser`s holding the real `survey.modsurvey` Spatie permission
 * instead — same capability, not the legacy mechanism.
 *
 * admincp.md §5's confirmed fix: `all_stats()`'s aggregation bug (question types 1/2/4/6
 * used the last loop-leftover `$answer` instead of iterating `$answers`,
 * confirmed by direct reading of `all_stats.php`) is fixed here, not
 * reproduced — every question type aggregates across all respondents.
 */
class SurveyController
{
    public function index(): View
    {
        $surveys = Survey::orderBy('id')->get(['id', 'title', 'questions', 'submits', 'start_date', 'end_date']);

        return view('admin.survey.index', compact('surveys'));
    }

    public function destroy(Survey $survey): RedirectResponse
    {
        // survey/index.php:27-40 — delete is blocked while questions exist,
        // not cascaded.
        if ($survey->questionsRelation()->exists()) {
            return back()->with('error', 'الاستبيان لم يتم حذفه لارتباطه بأسئلة بقاعدة البيانات ... يمكنك حذف الاسئلة أولاً لتتمكن من حذف الاستبيان');
        }

        $survey->delete();

        return redirect()->route('admin.survey.index')->with('success', 'تم مسح الاستبيان بنجاح من قاعدة البيانات');
    }

    public function create(): View
    {
        // AdminUser (connection 'main') and Spatie's permission pivot
        // tables (connection: default, App\Support\Permission\{Role,
        // Permission}'s own override) live on different connections —
        // Spatie's `permission()` *query scope* builds its join against
        // the queried model's own connection, which would look for the
        // pivot table on 'main' and fail to find it. The already-proven
        // path (AdminGuardTest/EnsureAdminHasRoleTest, both green) is the
        // *instance* method (`hasPermissionTo()`), not the static scope —
        // filtering in memory here uses that same proven path instead of
        // an unverified cross-connection query.
        $moderators = AdminUser::on('main')->get(['id', 'aid'])
            ->filter(fn (AdminUser $admin) => $admin->hasPermissionTo('survey.modsurvey', 'admin'))
            ->values();
        $groups = DB::connection('vbulletin')->table('usergroup')->select(['usergroupid', 'title'])->orderBy('usergroupid')->get();

        return view('admin.survey.create', compact('moderators', 'groups'));
    }

    /** `add_survey.php:48-76` — add-only (the legacy form is never actually pre-filled from an existing row on GET, confirmed by direct reading — no edit path exists). */
    public function store(Request $request): RedirectResponse
    {
        $survey = Survey::create([
            'title' => $request->string('title'),
            'openning' => $request->string('openning'),
            'finish' => $request->string('finish'),
            'start_date' => $request->string('start_date'),
            'end_date' => $request->string('end_date'),
            'users_only' => $request->boolean('users_only') ? 1 : 0,
            'ip_restriction' => $request->boolean('ip_restriction') ? 1 : 0,
            'anonymous' => $request->boolean('anonymous') ? 1 : 0,
            'published' => $request->boolean('published') ? 1 : 0,
            'editors' => implode('|', $request->input('editors', [])),
            'groups' => implode('|', $request->input('groups', [])),
        ]);

        return redirect()->route('admin.survey.questions.index', $survey)->with('success', 'تمت الإضافة بنجاح');
    }

    public function questionsIndex(Survey $survey): View
    {
        $questions = $survey->questionsRelation()->get();

        return view('admin.survey.questions', compact('survey', 'questions'));
    }

    /** `add_question.php:42-64`. */
    public function storeQuestion(Request $request, Survey $survey): RedirectResponse
    {
        $questionType = (int) $request->input('question_type');

        $options = $questionType === 7
            ? (string) $request->input('max_len')
            : serialize($request->input('question_options', []));

        SurveyQuestion::create([
            'title' => $request->string('title'),
            'des' => $request->string('des'),
            'question_options' => $options,
            'required' => $request->boolean('required') ? 1 : 0,
            'question_type' => $questionType,
            'max_sel_num' => $request->input('max_sel_num'),
            'survey_id' => $survey->id,
            'weight' => 0,
        ]);

        // add_question.php:60 — backfill weight=id for any still-zero row,
        // preserving insertion order for questions never manually reordered.
        SurveyQuestion::where('survey_id', $survey->id)->where('weight', 0)->update(['weight' => DB::raw('id')]);

        return redirect()->route('admin.survey.questions.index', $survey)->with('success', 'تمت الإضافة بنجاح');
    }

    /** `add_question.php:33-40` — drag-reorder POST, weight = position in the submitted `question[]` array. */
    public function reorderQuestions(Request $request, Survey $survey): RedirectResponse
    {
        foreach ($request->input('question', []) as $position => $questionId) {
            SurveyQuestion::where('id', (int) $questionId)->where('survey_id', $survey->id)->update(['weight' => $position + 1]);
        }

        return redirect()->route('admin.survey.questions.index', $survey);
    }

    public function destroyQuestion(Survey $survey, SurveyQuestion $question): RedirectResponse
    {
        abort_unless($question->survey_id === $survey->id, 404);

        $question->delete();

        return redirect()->route('admin.survey.questions.index', $survey)->with('success', 'تم مسح السؤال بنجاح من قاعدة البيانات');
    }

    /** `survey/stats.php` — list of respondents for one survey. */
    public function stats(Survey $survey): View
    {
        $answers = $survey->answers()->orderBy('id')->get();

        return view('admin.survey.stats', compact('survey', 'answers'));
    }

    /** `survey/answer.php` — one respondent's full question/answer breakdown. */
    public function showAnswer(Survey $survey, SurveyAnswer $answer): View
    {
        abort_unless($answer->survey_id === $survey->id, 404);

        $questions = $survey->questionsRelation()->get();

        return view('admin.survey.answer', compact('survey', 'answer', 'questions'));
    }

    /**
     * `survey/all_stats.php` — admincp.md §5's confirmed fix applied: every question type
     * aggregates across all of `$answers`, not the last loop-leftover
     * `$answer` (confirmed bug in the legacy source for types 1/2/4/6).
     */
    public function allStats(Survey $survey): View
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, SurveyAnswer> $answers */
        $answers = $survey->answers()->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, SurveyQuestion> $questions */
        $questions = $survey->questionsRelation()->get();

        $summaries = [];
        foreach ($questions as $question) {
            $summaries[] = [
                'question' => $question,
                'summary' => $this->summarizeQuestion($question, $answers),
            ];
        }

        return view('admin.survey.all-stats', compact('survey', 'summaries'));
    }

    /** @param  \Illuminate\Database\Eloquent\Collection<int, SurveyAnswer>  $answers */
    private function summarizeQuestion(SurveyQuestion $question, \Illuminate\Database\Eloquent\Collection $answers): array
    {
        $tally = [];

        foreach ($answers as $answer) {
            $value = $answer->answersArray()[$question->id] ?? null;

            if ($value === null) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $single) {
                $tally[$single] = ($tally[$single] ?? 0) + 1;
            }
        }

        return $tally;
    }
}
