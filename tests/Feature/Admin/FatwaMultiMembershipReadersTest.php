<?php

/*
 * Fatwa admin blocker fix (owner, 2026-10-30; decision-log #69). The approved
 * Q4 / Q6 / A1 / A3 controls create OLD's multi-membership relationships:
 * a general question in several topics (`topic_id = '|10|11|'`), a fatwa
 * under several questions (`general_question_id = '|1|3|'`). Laravel's
 * readers must consume them as OLD did:
 *
 * - R1 / R2 / R3: delimiter-aware membership (`LIKE '%|id|%'`) for the topic
 *   question list (rows and count), the question page's fatwas (also the
 *   author-scoped page) and the per-question fatwa count;
 * - every `general_question_id` reader / link builder: each id separately,
 *   or the FIRST id for a contextless single link — never the concatenated
 *   digits (`|1|3|` is never question 13, even when question 13 exists).
 *
 * Single-relationship rows must read exactly as before.
 */

use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Support\FatwaPipeIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();

    H::insert('nuke_w2a_cat', ['id' => 100, 'title' => 'الفقه', 'main_cat' => 0, 'q_count' => 5]);
    H::insert('nuke_w2a_cat', ['id' => 101, 'title' => 'العبادات', 'main_cat' => 100, 'q_count' => 2]);
    H::insert('nuke_w2a_cat', ['id' => 200, 'title' => 'المعاملات', 'main_cat' => 0, 'q_count' => 1]);

    foreach ([10 => ['الصلاة', 101], 11 => ['الزكاة', 101], 20 => ['الصيام', 200]] as $id => [$name, $cat]) {
        H::insert('nuke_fatwa_topics', ['id' => $id, 'topic_name' => $name, 'parent_id' => $cat]);
    }

    // Q1 in topic 10; Q2 in topics 10 and 20 (multi); Q3 in 20; Q13 — the collision: `|1|3|` concatenated.
    H::insert('nuke_fatwa_general_questions', ['id' => 1, 'question_text' => 'سؤال الصلاة', 'topic_id' => '|10|']);
    H::insert('nuke_fatwa_general_questions', ['id' => 2, 'question_text' => 'سؤال مشترك', 'topic_id' => '|10|20|']);
    H::insert('nuke_fatwa_general_questions', ['id' => 3, 'question_text' => 'سؤال الصيام', 'topic_id' => '|20|']);
    H::insert('nuke_fatwa_general_questions', ['id' => 13, 'question_text' => 'سؤال ثلاثة عشر الخاطئ', 'topic_id' => '|11|']);

    H::insert('nuke_islamic_authors', ['id' => 1, 'name' => 'الشيخ الأول', 'prename' => 'الشيخ', 'fatwa' => 2]);
    H::insert('nuke_islamic_authors', ['id' => 2, 'name' => 'الشيخ الثاني', 'prename' => 'الشيخ', 'fatwa' => 1]);
    H::insert('nuke_sat_channels', ['id' => 1, 'title' => 'قناة الفتاوى']);

    $f = fn (array $a) => H::insert('nuke_fatwa_questions', $a + ['answer_text' => 'الجواب', 'media_type' => 'audio', 'media_link' => 'https://m/x.mp3',
        'media_size' => 1, 'channel_id' => 1, 'db_insertion_date' => now()->toDateString(), 'date_of_fatwa' => '2020-01-01']);
    // The multi-question fatwa (as A1 leaves it), the most downloaded so it heads every sidebar.
    $this->multi = $f(['general_question_id' => '|1|3|', 'topic_id' => 10, 'question_text' => 'فتوى متعددة الأسئلة', 'auther_id' => 1, 'num_download' => 900]);
    $this->single = $f(['general_question_id' => '|1|', 'topic_id' => 10, 'question_text' => 'فتوى مفردة', 'auther_id' => 1, 'num_download' => 5]);
    $this->other = $f(['general_question_id' => '|3|', 'topic_id' => 20, 'question_text' => 'فتوى الصيام', 'auther_id' => 2, 'num_download' => 1]);

    $this->boss = H::superAdmin();
});

function fmListing(): ContentListingService
{
    return app(ContentListingService::class);
}

/** @return list<int> */
function fmTopicQuestionIds(int $topicId, int $page = 1): array
{
    return collect(fmListing()->fatwaGeneralQuestionsByTopic($topicId, $page)->items())->map(fn ($q) => (int) $q->id)->sort()->values()->all();
}

/** @return list<int> */
function fmQuestionFatwaIds(int $questionId, ?int $author = null): array
{
    return fmListing()->fatwaQuestionsForGeneralQuestion($questionId, $author)->map(fn ($q) => (int) $q->id)->sort()->values()->all();
}

// ─── the membership helper ──────────────────────────────────────────────────

it('matches a whole pipe token only: 12 matches |12|, |12|45|, |7|12|, |7|12|45| and never |112|, |120|, |7|112|', function () {
    foreach (['|12|', '|12|45|', '|7|12|', '|7|12|45|', '|112|', '|120|', '|7|112|', '|1|2|'] as $i => $value) {
        H::insert('nuke_fatwa_general_questions', ['id' => 500 + $i, 'question_text' => 'd'.$i, 'topic_id' => $value]);
    }
    $matched = FatwaPipeIds::whereContains(DB::connection('main')->table('nuke_fatwa_general_questions'), 'topic_id', 12)->orderBy('id')->pluck('topic_id')->all();

    expect($matched)->toBe(['|12|', '|12|45|', '|7|12|', '|7|12|45|']);
});

it('reads ids without concatenation and links to the first one; a single id reads exactly as before', function () {
    expect(FatwaPipeIds::ids('|1|3|'))->toBe([1, 3])
        ->and(FatwaPipeIds::ids('|5|'))->toBe([5])->and(FatwaPipeIds::ids('5'))->toBe([5])->and(FatwaPipeIds::ids(''))->toBe([])
        ->and(FatwaPipeIds::linkId('|1|3|'))->toBe('1')
        ->and(FatwaPipeIds::linkId('|5|'))->toBe(str_replace('|', '', '|5|'))
        ->and(FatwaPipeIds::linkId('5'))->toBe('5')->and(FatwaPipeIds::linkId(''))->toBe('');
});

// ─── R1: topic → questions ─────────────────────────────────────────────────

it('R1 lists a multi-topic question under BOTH topics, on the page and in the topic count', function () {
    expect(fmTopicQuestionIds(10))->toBe([1, 2])->and(fmTopicQuestionIds(20))->toBe([2, 3])
        ->and(fmListing()->fatwaGeneralQuestionCountForTopic(10))->toBe(fmListing()->fatwaGeneralQuestionsByTopic(10)->total());

    $this->get('/fatawa-group-10-101.htm')->assertOk()->assertSee('سؤال مشترك')->assertSee('سؤال الصلاة');
    $this->get('/fatawa-group-20-200.htm')->assertOk()->assertSee('سؤال مشترك')->assertSee('سؤال الصيام');
});

it('R1 pages and counts by the same membership', function () {
    H::insert('nuke_fatwa_topics', ['id' => 30, 'topic_name' => 'كثير', 'parent_id' => 101]);
    $ids = [];
    for ($i = 0; $i < 30; $i++) {
        $ids[] = H::insert('nuke_fatwa_general_questions', ['question_text' => 'س'.$i, 'topic_id' => $i % 3 === 0 ? '|11|30|' : '|30|']);
    }
    H::insert('nuke_fatwa_general_questions', ['question_text' => 'ليس هنا', 'topic_id' => '|300|']);
    H::insert('nuke_fatwa_general_questions', ['question_text' => 'ليس هنا ايضا', 'topic_id' => '|3|0|']);

    $first = fmListing()->fatwaGeneralQuestionsByTopic(30, 1);
    $second = fmListing()->fatwaGeneralQuestionsByTopic(30, 2);
    $seen = collect([...$first->items(), ...$second->items()])->map(fn ($q) => (int) $q->id)->sort()->values()->all();

    expect($first->total())->toBe(30)->and($second->total())->toBe(30)
        ->and(count($first->items()))->toBe(25)->and(count($second->items()))->toBe(5)
        ->and($seen)->toBe($ids)
        ->and(fmListing()->fatwaGeneralQuestionCountForTopic(30))->toBe(30);
});

it('after Q4 the question stays in its topic and appears in the added one; after Q6 it leaves only the removed one', function () {
    $session = H::session($this->boss);

    $this->withSession($session)->put(route('admin.fatwa.questions.topics.add.save', 1), ['ids' => [20, 11]])->assertSessionHasNoErrors();
    expect(fmTopicQuestionIds(10))->toContain(1)->and(fmTopicQuestionIds(20))->toContain(1)->and(fmTopicQuestionIds(11))->toContain(1);
    $this->get('/fatawa-group-10-101.htm')->assertSee('سؤال الصلاة');
    $this->get('/fatawa-group-20-200.htm')->assertSee('سؤال الصلاة');

    $this->withSession($session)->put(route('admin.fatwa.questions.topics.remove.save', 1), ['ids' => [20]])->assertSessionHasNoErrors();
    expect(fmTopicQuestionIds(20))->not->toContain(1)->and(fmTopicQuestionIds(10))->toContain(1)->and(fmTopicQuestionIds(11))->toContain(1);
    H::visitor();
    $this->get('/fatawa-group-20-200.htm')->assertDontSee('سؤال الصلاة');
    $this->get('/fatawa-group-10-101.htm')->assertSee('سؤال الصلاة');
});

it('Q5 single-topic moves still read exactly: the question leaves the old topic and appears in the new one', function () {
    $this->withSession(H::session($this->boss))->put(route('admin.fatwa.questions.topics.move.save', 3), ['id' => 10])->assertSessionHasNoErrors();

    expect(fmTopicQuestionIds(20))->toBe([2])->and(fmTopicQuestionIds(10))->toBe([1, 2, 3]);
});

// ─── R2 / R3: question → fatwas ────────────────────────────────────────────

it('R2 shows a multi-question fatwa on BOTH question pages and both author-scoped pages; R3 counts it on both', function () {
    expect(fmQuestionFatwaIds(1))->toBe([$this->multi, $this->single])->and(fmQuestionFatwaIds(3))->toBe([$this->multi, $this->other])
        ->and(fmQuestionFatwaIds(1, 1))->toBe([$this->multi, $this->single])->and(fmQuestionFatwaIds(3, 1))->toBe([$this->multi])
        ->and(fmQuestionFatwaIds(13))->toBe([]);

    foreach ([1, 3] as $q) {
        expect(fmListing()->fatwaAnswerCountForGeneralQuestion($q))->toBe(count(fmQuestionFatwaIds($q)));
        $this->get("/fatawa-all-{$q}.htm")->assertOk()->assertSee('فتوى متعددة الأسئلة');
        $this->get("/auther-all-fatawa-1-{$q}.htm")->assertOk()->assertSee('فتوى متعددة الأسئلة');
    }
    // Question 13's page renders no answer for it (its category sidebar may list it, linked to question 1).
    $this->get('/fatawa-all-13.htm')->assertOk()->assertDontSee('<a id="'.$this->multi.'"></a>', false)->assertDontSee('fatawa-all-13.htm#'.$this->multi, false);
    $this->get('/auther-all-fatawa-2-3.htm')->assertOk()->assertDontSee('<a id="'.$this->multi.'"></a>', false)->assertSee('<a id="'.$this->other.'"></a>', false);
});

it('makes A3 reachable for a multi-question fatwa, and A3 removes it only from the removed question', function () {
    $session = H::session($this->boss);
    foreach ([1, 3] as $q) {
        expect($this->withSession($session)->get("/fatawa-all-{$q}.htm")->assertOk()->getContent())
            ->toContain('href="'.route('admin.fatwa.fatwas.questions.remove', $this->multi))
            ->not->toContain('href="'.route('admin.fatwa.fatwas.questions.move', $this->multi).'?');
    }

    // The single-question fatwa beside it keeps «نقل الفتوى من سؤال لأخر».
    expect($this->withSession($session)->get('/fatawa-all-1.htm')->getContent())->toContain('href="'.route('admin.fatwa.fatwas.questions.move', $this->single).'?');

    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.remove.save', $this->multi), ['ids' => [1]])->assertSessionHasNoErrors();

    expect(fmQuestionFatwaIds(1))->toBe([$this->single])->and(fmQuestionFatwaIds(3))->toBe([$this->multi, $this->other])
        ->and(fmListing()->fatwaAnswerCountForGeneralQuestion(1))->toBe(1);
    H::visitor();
    $this->get('/fatawa-all-1.htm')->assertDontSee('فتوى متعددة الأسئلة');
    $this->get('/fatawa-all-3.htm')->assertSee('فتوى متعددة الأسئلة');
});

it('after A1 the fatwa stays on its question and appears on the added one; A2 single moves read exactly', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.add.save', $this->other), ['ids' => [2]])->assertSessionHasNoErrors();

    expect(fmQuestionFatwaIds(3))->toContain($this->other)->and(fmQuestionFatwaIds(2))->toBe([$this->other])
        ->and(fmListing()->fatwaAnswerCountForGeneralQuestion(2))->toBe(1);
    H::visitor();
    $this->get('/fatawa-all-2.htm')->assertSee('فتوى الصيام');
    $this->get('/fatawa-all-3.htm')->assertSee('فتوى الصيام');

    $this->withSession(H::session($this->boss))->put(route('admin.fatwa.fatwas.questions.move.save', $this->single), ['id' => 2])->assertSessionHasNoErrors();
    expect(fmQuestionFatwaIds(1))->toBe([$this->multi])->and(fmQuestionFatwaIds(2))->toBe([$this->single, $this->other]);
});

it('reads single-membership rows exactly as before', function () {
    H::insert('nuke_fatwa_general_questions', ['id' => 112, 'question_text' => 'سؤال 112', 'topic_id' => '|11|']);
    $lone = H::insert('nuke_fatwa_questions', ['general_question_id' => '|112|', 'topic_id' => 11, 'question_text' => 'فتوى 112', 'auther_id' => 2]);

    expect(fmTopicQuestionIds(11))->toBe([13, 112])
        ->and(fmQuestionFatwaIds(112))->toBe([$lone])->and(fmQuestionFatwaIds(12))->toBe([])
        ->and(fmListing()->fatwaAnswerCountForGeneralQuestion(112))->toBe(1)->and(fmListing()->fatwaAnswerCountForGeneralQuestion(11))->toBe(0);
});

// ─── general_question_id readers and links: never question 13 ──────────────

it('lists the author\'s questions and counts them by every relationship, never the concatenated 13', function () {
    $listed = collect(fmListing()->fatwaGeneralQuestionsByAuthor(1)->items())->map(fn ($q) => (int) $q->id)->sort()->values()->all();
    expect($listed)->toBe([1, 3])
        ->and(fmListing()->fatwaDistinctQuestionCountsByAuthor()->all())->toEqual([1 => 2, 2 => 1]);

    $page = $this->get('/auther-questions-1.htm')->assertOk()->getContent();
    expect($page)->toContain('سؤال الصلاة')->toContain('سؤال الصيام')->not->toContain('سؤال ثلاثة عشر الخاطئ')->not->toContain('fatawa-all-13.htm');

    $this->get('/fatawa-authors.htm')->assertOk();
});

it('lists a channel\'s questions and their authors by every relationship, never the concatenated 13', function () {
    $page = fmListing()->fatwaQuestionsForChannel(1);
    $rows = collect($page->items())->keyBy(fn ($q) => (int) $q->id);

    expect($rows->keys()->sort()->values()->all())->toBe([1, 3])
        ->and((int) $rows[1]->author->id)->toBe(1)
        ->and((int) $rows[3]->author->id)->toBe(1); // the lowest-id answer containing 3 is the multi fatwa (author 1)

    $html = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();
    expect($html)->toContain('سؤال الصلاة')->toContain('سؤال الصيام')->not->toContain('سؤال ثلاثة عشر الخاطئ')->not->toContain('fatawa-all-13.htm')
        ->toContain('/fatawa-all-1.htm#'.$this->multi);
});

it('links the multi-question fatwa to its first question in every contextless link, never to 13', function () {
    $first = '/fatawa-all-1.htm#'.$this->multi;

    // Sidebars on the control pages themselves (category-scoped by topic_id 10).
    foreach (['/fatawa-group-10-101.htm', '/fatawa-topics-101-1.htm', '/fatawa-all-1.htm'] as $url) {
        expect($this->get($url)->assertOk()->getContent())->toContain($first)->not->toContain('fatawa-all-13.htm');
    }
    // Author page sidebars, day page, channel page.
    foreach (['/auther-questions-1.htm', '/fatwa-today.htm', '/fatawa-channel-1.htm'] as $url) {
        expect($this->get($url)->assertOk()->getContent())->toContain($first)->not->toContain('fatawa-all-13.htm');
    }
    // Search results.
    $search = $this->post('/search.htm', ['kh_title' => 'فتوى متعددة', 'kh_dept' => 'fatawa'])->assertOk()->getContent();
    expect($search)->toContain('/fatawa-all-1.htm')->not->toContain('fatawa-all-13.htm');

    // A single-question fatwa links exactly as before.
    expect($this->get('/fatwa-today.htm')->getContent())->toContain('/fatawa-all-3.htm#'.$this->other);
});
