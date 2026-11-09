<?php

/*
 * FATWA MULTI-TOPIC GENERAL-QUESTION `topic_id` FIX (2026-11-01; decision-log #71).
 * `nuke_fatwa_general_questions.topic_id` is a pipe membership list (`|258|91|`
 * = topics 258 and 91). Where a page shows ONE topic for a question it is the
 * FIRST stored topic — OLD `get_topic_id_for_general_question()` (`fatawa-all`,
 * `auther-all-fatawa`) and `explode('|', $topic_id)[1]` (author and channel
 * lists) — never the digits concatenated: `|258|91|` must never resolve as
 * topic 25891, which exists here as the trap. Single-topic values read as before.
 */

use App\Domain\Content\Support\FatwaPipeIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();

    foreach ([100 => 'تصنيف الأول', 200 => 'تصنيف الثاني', 300 => 'تصنيف الخامس والعشرين', 400 => 'تصنيف خاطئ'] as $id => $title) {
        H::insert('nuke_w2a_cat', ['id' => $id, 'title' => $title, 'main_cat' => 0, 'q_count' => 1]);
    }
    foreach ([258 => ['موضوع مائتين وثمانية وخمسين', 100], 91 => ['موضوع واحد وتسعين', 200], 25 => ['موضوع خمسة وعشرين', 300], 25891 => ['موضوع مدموج خاطئ', 400]] as $id => [$name, $cat]) {
        H::insert('nuke_fatwa_topics', ['id' => $id, 'topic_name' => $name, 'parent_id' => $cat]);
    }

    H::insert('nuke_fatwa_general_questions', ['id' => 1, 'question_text' => 'سؤال مفرد', 'topic_id' => '|258|']);
    H::insert('nuke_fatwa_general_questions', ['id' => 2, 'question_text' => 'سؤال متعدد أوله 258', 'topic_id' => '|258|91|']);
    H::insert('nuke_fatwa_general_questions', ['id' => 3, 'question_text' => 'سؤال متعدد أوله 91', 'topic_id' => '|91|258|']);
    H::insert('nuke_fatwa_general_questions', ['id' => 4, 'question_text' => 'سؤال بلا موضوع', 'topic_id' => '']);
    H::insert('nuke_fatwa_general_questions', ['id' => 5, 'question_text' => 'سؤال بموضوع فارغ', 'topic_id' => '||']);

    H::insert('nuke_islamic_authors', ['id' => 1, 'name' => 'الشيخ', 'prename' => 'الشيخ', 'fatwa' => 5]);
    H::insert('nuke_sat_channels', ['id' => 1, 'title' => 'قناة الفتاوى']);
    foreach ([1, 2, 3, 4, 5] as $q) {
        H::insert('nuke_fatwa_questions', ['general_question_id' => '|'.$q.'|', 'topic_id' => 0, 'question_text' => 'فتوى '.$q, 'auther_id' => 1,
            'answer_text' => 'الجواب', 'media_type' => 'audio', 'media_link' => 'https://m/x.mp3', 'media_size' => 1, 'channel_id' => 1,
            'db_insertion_date' => now()->toDateString(), 'date_of_fatwa' => '2020-01-01']);
    }
});

/** @return list<string> every `fatawa-group-{t}-{c}` target on the page */
function fgtTopicLinks(string $content): array
{
    preg_match_all('/href="\/fatawa-group-(\d+-\d+)\.htm"/', $content, $m);

    return array_values(array_unique($m[1]));
}

/** The topic link shown in a list row: the first `fatawa-group-*` between the row's question link and the next question link. */
function fgtRowTopic(string $content, int $question, string $prefix = '/fatawa-all-'): ?string
{
    $start = strpos($content, 'href="'.$prefix.$question.'.htm"');
    if ($start === false) {
        return null;
    }
    $next = strpos($content, 'href="'.$prefix, $start + 1);
    $row = substr($content, $start, $next === false ? null : $next - $start);

    return preg_match('/href="\/fatawa-group-(\d+-\d+)\.htm"/', $row, $m) ? $m[1] : null;
}

// ─── the helper ─────────────────────────────────────────────────────────────

it('reads a question\'s first topic from its pipe list, never the concatenated digits', function () {
    expect(FatwaPipeIds::firstId('|258|'))->toBe(258)
        ->and(FatwaPipeIds::firstId('|258|91|'))->toBe(258)->not->toBe(25891)
        ->and(FatwaPipeIds::firstId('|91|258|'))->toBe(91)
        ->and(FatwaPipeIds::firstId('258'))->toBe(258)
        ->and(FatwaPipeIds::firstId('258|91|'))->toBe(258)
        ->and(FatwaPipeIds::firstId('|0|91|'))->toBe(91)
        ->and(FatwaPipeIds::ids('|258|91|'))->toBe([258, 91]);

    foreach (['', '||', '|0|', 'abc', null] as $empty) {
        expect(FatwaPipeIds::firstId($empty))->toBe(0);
    }
});

// ─── fatawa-all-{q} / auther-all-fatawa-{a}-{q} (renderAllAnswers) ───────────

it('fatawa-all: a single-topic question keeps its topic breadcrumb and category', function () {
    $content = $this->get('/fatawa-all-1.htm')->assertOk()->getContent();

    expect(fgtTopicLinks($content))->toBe(['258-100'])
        ->and($content)->toContain('موضوع مائتين وثمانية وخمسين')->toContain('تصنيف الأول');
});

it('fatawa-all: |258|91| shows its first topic 258 and never topic 25891', function () {
    $content = $this->get('/fatawa-all-2.htm')->assertOk()->getContent();

    expect(fgtTopicLinks($content))->toBe(['258-100'])
        ->and($content)->toContain('موضوع مائتين وثمانية وخمسين')->toContain('تصنيف الأول')
        ->not->toContain('fatawa-group-25891')->not->toContain('موضوع مدموج خاطئ')->not->toContain('تصنيف خاطئ');
});

it('fatawa-all: |91|258| shows its first topic 91, not the one it shares with |258|91|', function () {
    $content = $this->get('/fatawa-all-3.htm')->assertOk()->getContent();

    expect(fgtTopicLinks($content))->toBe(['91-200'])
        ->and($content)->toContain('موضوع واحد وتسعين')->toContain('تصنيف الثاني')
        ->not->toContain('fatawa-group-91258');
});

it('fatawa-all: an empty or malformed topic list renders the page without a topic', function () {
    foreach ([4, 5] as $question) {
        expect(fgtTopicLinks($this->get('/fatawa-all-'.$question.'.htm')->assertOk()->getContent()))->toBe([]);
    }
});

it('auther-all-fatawa: the author-scoped page uses the same first topic', function () {
    $multi = $this->get('/auther-all-fatawa-1-2.htm')->assertOk()->getContent();
    $notFirst = $this->get('/auther-all-fatawa-1-3.htm')->assertOk()->getContent();

    expect(fgtTopicLinks($multi))->toBe(['258-100'])->and($multi)->not->toContain('25891')
        ->and(fgtTopicLinks($notFirst))->toBe(['91-200']);
});

// ─── list pages: author (fatwaGeneralQuestionsByAuthor) and channel (fatwaTopicsKeyedById) ───

it('auther-questions: each question row links its first topic', function () {
    $content = $this->get('/auther-questions-1.htm')->assertOk()->getContent();
    $row = fn (int $q) => fgtRowTopic($content, $q, '/auther-all-fatawa-1-');

    expect($content)->toContain('href="/auther-all-fatawa-1-4.htm"')
        ->and($row(1))->toBe('258-100')
        ->and($row(2))->toBe('258-100')
        ->and($row(3))->toBe('91-200')
        ->and($row(4))->toBeNull()
        ->and($content)->not->toContain('fatawa-group-25891')->not->toContain('موضوع مدموج خاطئ');
});

it('fatawa-channel: each question row links its first topic', function () {
    $content = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();

    expect(fgtRowTopic($content, 1))->toBe('258-100')
        ->and(fgtRowTopic($content, 2))->toBe('258-100')
        ->and(fgtRowTopic($content, 3))->toBe('91-200')
        ->and(fgtRowTopic($content, 4))->toBe('0-0')
        ->and($content)->not->toContain('fatawa-group-25891')->not->toContain('موضوع مدموج خاطئ');
});

// ─── membership is unchanged (the topic question list) ──────────────────────

it('the topic question list keeps whole-token membership: first, not-first, and no lookalike', function () {
    $ids = function (string $url): array {
        preg_match_all('/href="\/fatawa-all-(\d+)\.htm"/', $this->get($url)->assertOk()->getContent(), $m);

        return collect($m[1])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    };

    expect($ids('/fatawa-group-258-100.htm'))->toBe([1, 2, 3])
        ->and($ids('/fatawa-group-91-200.htm'))->toBe([2, 3])
        ->and($ids('/fatawa-group-25-300.htm'))->toBe([])
        ->and($ids('/fatawa-group-25891-400.htm'))->toBe([]);
});
