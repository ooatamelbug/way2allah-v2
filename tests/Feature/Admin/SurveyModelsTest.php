<?php

use App\Domain\Admin\Models\Survey;
use App\Domain\Admin\Models\SurveyAnswer;
use App\Domain\Admin\Models\SurveyQuestion;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Roadmap task 5.1. `nuke_survey.questions`/`submits` are legacy
 * counter-cache columns — tested here for accuracy under repeated
 * creation/deletion, matching this task's own stated test requirement.
 */
function useInMemoryMainConnectionForSurveyModels(): void
{
    InMemoryConnection::setup('main', [
        'nuke_survey' => MainSchema::nukeSurvey(),
        'nuke_survey_questions' => MainSchema::nukeSurveyQuestions(),
        'nuke_survey_answers' => MainSchema::nukeSurveyAnswers(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForSurveyModels();
});

it('increments the questions counter-cache when a SurveyQuestion is created', function () {
    $survey = Survey::create(['title' => 'Test Survey']);

    SurveyQuestion::create(['title' => 'Q1', 'survey_id' => $survey->id, 'question_type' => 1]);
    SurveyQuestion::create(['title' => 'Q2', 'survey_id' => $survey->id, 'question_type' => 1]);

    expect($survey->fresh()->questions)->toBe(2);
});

it('decrements the questions counter-cache when a SurveyQuestion is deleted', function () {
    $survey = Survey::create(['title' => 'Test Survey']);
    $question = SurveyQuestion::create(['title' => 'Q1', 'survey_id' => $survey->id, 'question_type' => 1]);

    $question->delete();

    expect($survey->fresh()->questions)->toBe(0);
});

it('increments the submits counter-cache when a SurveyAnswer is created', function () {
    $survey = Survey::create(['title' => 'Test Survey']);

    SurveyAnswer::create(['survey_id' => $survey->id, 'user_id' => 0, 'answers' => serialize([1 => 'x'])]);
    SurveyAnswer::create(['survey_id' => $survey->id, 'user_id' => 5, 'answers' => serialize([1 => 'y'])]);

    expect($survey->fresh()->submits)->toBe(2);
});

it('reproduces question_options dual-shape: type 7 stores a raw max-length, every other type stores a serialized option array', function () {
    $shortText = SurveyQuestion::create(['title' => 'Q', 'survey_id' => 1, 'question_type' => 7, 'question_options' => '50']);
    $multipleChoice = SurveyQuestion::create(['title' => 'Q', 'survey_id' => 1, 'question_type' => 1, 'question_options' => serialize(['a', 'b'])]);

    expect($shortText->maxLength())->toBe(50)
        ->and($shortText->optionsArray())->toBe([])
        ->and($multipleChoice->maxLength())->toBeNull()
        ->and($multipleChoice->optionsArray())->toBe(['a', 'b']);
});

it('reproduces editors/groups as pipe-delimited id lists', function () {
    $survey = Survey::create(['title' => 'T', 'editors' => '5|9|12', 'groups' => '3|7']);

    expect($survey->editorIds())->toBe(['5', '9', '12'])
        ->and($survey->groupIds())->toBe(['3', '7']);
});

it('isGuest() reflects the legacy user_id=0 convention', function () {
    $guest = SurveyAnswer::create(['survey_id' => 1, 'user_id' => 0]);
    $member = SurveyAnswer::create(['survey_id' => 1, 'user_id' => 42]);

    expect($guest->isGuest())->toBeTrue()->and($member->isGuest())->toBeFalse();
});
