<?php

/*
 * Fatwa visitor parity (owner, 2026-11-04; decision-log #89) — OLD `modules/Fatwa/index.php` governs:
 * F6 `fatawa-channel-0.htm` «بدون قناة» (list_Fatawa_for_channel() with id 0) and F5b the legacy
 * `fatawa-topics-{cat}.htm` category URL (.htaccess:631).
 */

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_sat_sats' => MainSchema::nukeSatSats(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
    ]);
});

/** 30 «no channel» fatawa (one general question each), plus one fatwa on real channel 3. */
function fvpSeedChannels(): void
{
    $db = DB::connection('main');
    $db->table('nuke_sat_sats')->insert(['id' => 1, 'title' => 'Nilesat', 'pos' => '7W']);
    $db->table('nuke_sat_channels')->insert(['id' => 3, 'title' => 'Al Rahma', 'sat_id' => 1, 'freq' => '11727']);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic Ten', 'parent_id' => 1]);

    $questions = [];
    $fatawa = [];
    for ($i = 1; $i <= 30; $i++) {
        $questions[] = ['id' => $i, 'question_text' => sprintf('NoChannel Q%02d', $i), 'topic_id' => '|10|'];
        $fatawa[] = ['id' => $i, 'channel_id' => 0, 'general_question_id' => "|{$i}|", 'question_text' => sprintf('NoChannel F%02d', $i), 'num_download' => $i, 'db_insertion_date' => '2026-01-'.sprintf('%02d', $i % 28 + 1)];
    }
    $questions[] = ['id' => 100, 'question_text' => 'RealChannel Q', 'topic_id' => '|10|'];
    $fatawa[] = ['id' => 100, 'channel_id' => 3, 'general_question_id' => '|100|', 'question_text' => 'RealChannel F', 'num_download' => 999, 'db_insertion_date' => '2026-12-31'];
    $db->table('nuke_fatwa_general_questions')->insert($questions);
    $db->table('nuke_fatwa_questions')->insert($fatawa);
}

/** @return list<int> general-question ids listed in the questions table, in page order */
function fvpListedQuestionIds(string $html): array
{
    preg_match('#<table class="table table-striped table-hover" id="sample_5">(.*?)</table>#s', $html, $table);
    preg_match_all('#href="/fatawa-all-(\d+)\.htm"#', $table[1] ?? '', $m);

    return array_map('intval', $m[1]);
}

// ─── F6 — fatawa-channel-0.htm «بدون قناة» ───────────────────────────────────

it('F6: fatawa-channel-0.htm answers 200 titled «بدون قناة» and lists only the channel_id = 0 questions, 25 a page', function () {
    fvpSeedChannels();

    $page1 = $this->get('/fatawa-channel-0.htm')->assertOk()->getContent();
    $page2 = $this->get('/fatawa-channel-0-2.htm')->assertOk()->getContent();

    $listed = array_merge(fvpListedQuestionIds($page1), fvpListedQuestionIds($page2));
    sort($listed);

    expect(fvpListedQuestionIds($page1))->toHaveCount(25)
        ->and(fvpListedQuestionIds($page2))->toHaveCount(5)
        ->and($listed)->toBe(range(1, 30))
        ->and($page1)->toContain('<title>بدون قناة - الفتاوى المرئية - ')
        ->toContain('الفتاوى المضافة على قناة بدون قناة')
        ->toContain('<a href="/fatawa-channel-0.htm">بدون قناة</a>')
        ->and(fvpListedQuestionIds($page1))->not->toContain(100);
});

it('F6: channel 0 has no channel details block', function () {
    fvpSeedChannels();

    $html = $this->get('/fatawa-channel-0.htm')->assertOk()->getContent();

    expect($html)->not->toContain('بيانات قناة')
        ->not->toContain('القمر الصناعي :')
        ->not->toContain('/images/channels/0.png')
        ->not->toContain('/images/beams/1.png');
});

it('F6: channel 0\'s sidebar is sitewide, as OLD Top10(0, 0) — a real channel\'s stays channel-scoped', function () {
    fvpSeedChannels();

    preg_match('/aria-label="الشريط الجانبي">(.*)<\/aside>/s', $this->get('/fatawa-channel-0.htm')->assertOk()->getContent(), $noChannel);
    preg_match('/aria-label="الشريط الجانبي">(.*)<\/aside>/s', $this->get('/fatawa-channel-3.htm')->assertOk()->getContent(), $real);

    expect($noChannel[1])->toContain('RealChannel F')     // another channel's fatwa — no channel filter
        ->toContain('NoChannel F30')
        ->and($real[1])->toContain('RealChannel F')
        ->not->toContain('NoChannel F');
});

it('F6: a real channel page is unchanged — details block, «قناة» title, its own questions only', function () {
    fvpSeedChannels();

    $html = $this->get('/fatawa-channel-3.htm')->assertOk()->getContent();

    expect($html)->toContain('بيانات قناة Al Rahma')
        ->toContain('<title>قناة Al Rahma (Nilesat) - الفتاوى المرئية - ')
        ->toContain('الموقع المداري : 7 غرباً')
        ->and(fvpListedQuestionIds($html))->toBe([100]);
});

it('F6: an unknown channel still answers 404, paged or not', function () {
    fvpSeedChannels();

    $this->get('/fatawa-channel-999.htm')->assertNotFound();
    $this->get('/fatawa-channel-999-2.htm')->assertNotFound();
});

it('F6: no channel 0 row is created', function () {
    fvpSeedChannels();

    $this->get('/fatawa-channel-0.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_sat_channels')->where('id', 0)->exists())->toBeFalse()
        ->and(DB::connection('main')->table('nuke_sat_channels')->count())->toBe(1);
});

// ─── F5b — the legacy fatawa-topics-{cat}.htm URL ────────────────────────────

function fvpSeedCategory(): void
{
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root Category', 'main_cat' => 0, 'q_count' => 5],
        ['id' => 2, 'title' => 'Child Category', 'main_cat' => 1, 'q_count' => 2],
    ]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic Ten', 'parent_id' => 1]);
}

function fvpWithoutToken(string $html): string
{
    // og:url is per-request (IF-056: the requested URL, as Gen 2).
    return (string) preg_replace('/<meta name="csrf-token" content="[^"]*">|name="_token" value="[^"]*"|<meta property="og:url" content="[^"]*">/', '', $html);
}

it('F5b: fatawa-topics-{cat}.htm shows the same page as fatawa-topics-{cat}-1.htm', function () {
    fvpSeedCategory();

    $legacy = $this->get('/fatawa-topics-1.htm')->assertOk()->getContent();
    $paged = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect($legacy)->toContain('Root Category')->toContain('Child Category')->toContain('Topic Ten')
        ->and(fvpWithoutToken($legacy))->toBe(fvpWithoutToken($paged));
});

it('F5b: a missing category still answers 404 on both forms', function () {
    fvpSeedCategory();

    $this->get('/fatawa-topics-999.htm')->assertNotFound();
    $this->get('/fatawa-topics-999-1.htm')->assertNotFound();
});

it('F5b: the paged topic route and the fatawa-category alias are unchanged', function () {
    fvpSeedCategory();

    expect($this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent())->toContain('Topic Ten')
        ->and($this->get('/fatawa-category-1.htm')->assertOk()->getContent())->toContain('Topic Ten')
        ->and(route('fatawa.topics.show', ['category' => 1, 'page' => 2]))->toEndWith('/fatawa-topics-1-2.htm')
        ->and(route('fatawa.topics.show.legacy', ['category' => 1]))->toEndWith('/fatawa-topics-1.htm');
});
