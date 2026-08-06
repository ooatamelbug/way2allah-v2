<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Survey;
use App\Domain\Admin\Models\SurveyAnswer;
use App\Domain\Admin\Models\SurveyQuestion;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/** Roadmap task 5.2. */
function useInMemoryMainConnectionForSurveyController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'nuke_survey' => MainSchema::nukeSurvey(),
        'nuke_survey_questions' => MainSchema::nukeSurveyQuestions(),
        'nuke_survey_answers' => MainSchema::nukeSurveyAnswers(),
    ]);
}

function actingAsAdminWithSurveyPermission(): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'moderator', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    useInMemoryMainConnectionForSurveyController();
});

it('rejects a plain admin without survey.modsurvey permission', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.survey.index'))->assertForbidden();
});

it('lists surveys for an admin holding survey.modsurvey', function () {
    actingAsAdminWithSurveyPermission();
    Survey::create(['title' => 'Ramadan Survey']);

    $this->get(route('admin.survey.index'))->assertOk()->assertSee('Ramadan Survey');
});

it('a super-admin bypasses the permission check entirely', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(\App\Support\Permission\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.survey.index'))->assertOk();
});

it('creates a survey with pipe-delimited editors/groups', function () {
    actingAsAdminWithSurveyPermission();

    $this->post(route('admin.survey.store'), [
        'title' => 'New Survey',
        'editors' => ['5', '9'],
        'groups' => ['3'],
    ])->assertRedirect();

    $survey = Survey::first();
    expect($survey->title)->toBe('New Survey')
        ->and($survey->editors)->toBe('5|9')
        ->and($survey->groups)->toBe('3');
});

it('blocks deleting a survey that still has questions, matching survey/index.php:27-40', function () {
    actingAsAdminWithSurveyPermission();
    $survey = Survey::create(['title' => 'T']);
    SurveyQuestion::create(['title' => 'Q', 'survey_id' => $survey->id, 'question_type' => 1]);

    $this->delete(route('admin.survey.destroy', $survey))->assertRedirect();

    expect(Survey::find($survey->id))->not->toBeNull();
});

it('deletes a survey with no questions', function () {
    actingAsAdminWithSurveyPermission();
    $survey = Survey::create(['title' => 'T']);

    $this->delete(route('admin.survey.destroy', $survey))->assertRedirect();

    expect(Survey::find($survey->id))->toBeNull();
});

it('stores a short-text question (type 7) as a raw max-length, not a serialized array', function () {
    actingAsAdminWithSurveyPermission();
    $survey = Survey::create(['title' => 'T']);

    $this->post(route('admin.survey.questions.store', $survey), [
        'title' => 'Your name?',
        'question_type' => 7,
        'max_len' => '100',
    ])->assertRedirect();

    $question = SurveyQuestion::first();
    expect($question->question_options)->toBe('100')
        ->and($question->maxLength())->toBe(100);
});

it('reorders questions via the drag-list POST, weight = submitted position', function () {
    actingAsAdminWithSurveyPermission();
    $survey = Survey::create(['title' => 'T']);
    $q1 = SurveyQuestion::create(['title' => 'Q1', 'survey_id' => $survey->id, 'question_type' => 1, 'weight' => 1]);
    $q2 = SurveyQuestion::create(['title' => 'Q2', 'survey_id' => $survey->id, 'question_type' => 1, 'weight' => 2]);

    $this->post(route('admin.survey.questions.reorder', $survey), [
        'question' => [$q2->id, $q1->id],
    ])->assertRedirect();

    expect($q2->fresh()->weight)->toBe(1)->and($q1->fresh()->weight)->toBe(2);
});

it('admincp.md §5 fix: all_stats aggregates every respondent, not just the last one, for question types 1/2/4/6', function () {
    actingAsAdminWithSurveyPermission();
    $survey = Survey::create(['title' => 'T']);
    $question = SurveyQuestion::create([
        'title' => 'Pick one', 'survey_id' => $survey->id, 'question_type' => 1,
        'question_options' => serialize(['Red', 'Blue']),
    ]);
    SurveyAnswer::create(['survey_id' => $survey->id, 'user_id' => 1, 'answers' => serialize([$question->id => 1])]);
    SurveyAnswer::create(['survey_id' => $survey->id, 'user_id' => 2, 'answers' => serialize([$question->id => 2])]);
    SurveyAnswer::create(['survey_id' => $survey->id, 'user_id' => 3, 'answers' => serialize([$question->id => 1])]);

    $response = $this->get(route('admin.survey.all-stats', $survey));

    $response->assertOk()->assertSee('Red: 2')->assertSee('Blue: 1');
});
