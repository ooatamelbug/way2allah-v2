<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * FATWA LEGACY-ROUTE PAGINATION FIX (2026-11-01) — `fatawa-topics-{cat}-{page}.htm`
 * and `fatawa-group-{t}-{c}[-{page}].htm` read the page from the route
 * segment, so their pagers must link to segment URLs (OLD `pagination()`,
 * `modules/Fatwa/index.php`:232-285), never `?page=N` — which the readers
 * ignored, reloading the same page. Queries, page size (25) and order unchanged.
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);

    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 5, 'title' => 'Category Five', 'main_cat' => 0, 'q_count' => 1],
        ['id' => 6, 'title' => 'Category Six', 'main_cat' => 0, 'q_count' => 1],
    ]);

    // 60 topics in category 5 (pages of 25, 25, 10) and 5 in category 6 interleaved by id.
    $topics = [];
    for ($id = 1; $id <= 65; $id++) {
        $topics[] = ['id' => $id, 'topic_name' => sprintf('Topic %02d', $id), 'parent_id' => $id % 13 === 0 ? 6 : 5];
    }
    $db->table('nuke_fatwa_topics')->insert($topics);

    // Topic 1: 30 single-topic questions, one multi-topic `|9|1|` (OLD's LIKE
    // membership) and two lookalikes (`|11|`, `|10|`) that are not members.
    // The page does not read a question's own topic list, so the separate
    // multi-topic `topic_id` cast bug is not exercised here.
    $questions = [];
    for ($id = 1; $id <= 30; $id++) {
        $questions[] = ['id' => $id, 'question_text' => sprintf('Question %02d', $id), 'topic_id' => '|1|'];
    }
    $questions[] = ['id' => 31, 'question_text' => 'Question 31', 'topic_id' => '|11|'];
    $questions[] = ['id' => 32, 'question_text' => 'Question 32', 'topic_id' => '|9|1|'];
    $questions[] = ['id' => 33, 'question_text' => 'Question 33', 'topic_id' => '|10|'];
    $db->table('nuke_fatwa_general_questions')->insert($questions);
});

/** @return list<int> the ids of the listed rows, in page order */
function fatwaPagedIds(string $content, string $pattern): array
{
    preg_match_all($pattern, $content, $m);

    return array_values(array_unique(array_map('intval', $m[1])));
}

/** @return list<string> every href inside the page's pagers */
function fatwaPagerHrefs(string $content): array
{
    preg_match_all('/<nav class="w2a-pagination".*?<\/nav>/s', $content, $navs);
    preg_match_all('/href="([^"]+)"/', implode('', $navs[0]), $m);

    return array_values(array_unique($m[1]));
}

function fatwaPagerRel(string $content, string $rel): ?string
{
    return preg_match('/href="([^"]+)" rel="'.$rel.'"/', $content, $m) ? $m[1] : null;
}

// ─── A. Category → Topics ───────────────────────────────────────────────────

it('category pager links to fatawa-topics-{cat}-{n}.htm route segments, never ?page=', function () {
    $content = $this->get('/fatawa-topics-5-1.htm')->assertOk()->getContent();
    $hrefs = fatwaPagerHrefs($content);

    expect($hrefs)->toContain(url('/fatawa-topics-5-2.htm'))->toContain(url('/fatawa-topics-5-3.htm'))
        ->and(fatwaPagerRel($content, 'next'))->toBe(url('/fatawa-topics-5-2.htm'))
        ->and(fatwaPagerRel($content, 'prev'))->toBeNull()
        ->and(implode(' ', $hrefs))->not->toContain('?page=')
        ->and($content)->not->toContain('?page=');
});

it('category route pages return distinct topic sets in id order, the last page holding the remainder', function () {
    $members = array_values(array_filter(range(1, 65), fn ($id) => $id % 13 !== 0));
    $pattern = '/href="\/fatawa-group-(\d+)-5\.htm"/';

    $page1 = fatwaPagedIds($this->get('/fatawa-topics-5-1.htm')->getContent(), $pattern);
    $page2 = fatwaPagedIds($this->get('/fatawa-topics-5-2.htm')->getContent(), $pattern);
    $page3 = fatwaPagedIds($this->get('/fatawa-topics-5-3.htm')->getContent(), $pattern);

    expect($page1)->toBe(array_slice($members, 0, 25))
        ->and($page2)->toBe(array_slice($members, 25, 25))
        ->and($page3)->toBe(array_slice($members, 50))
        ->and($page3)->toHaveCount(10)
        ->and(array_merge($page1, $page2, $page3))->toBe($members);
});

it('category pager navigates backward and forward by route segment on middle and last pages', function () {
    $page2 = $this->get('/fatawa-topics-5-2.htm')->getContent();
    $page3 = $this->get('/fatawa-topics-5-3.htm')->getContent();

    expect(fatwaPagerRel($page2, 'prev'))->toBe(url('/fatawa-topics-5-1.htm'))
        ->and(fatwaPagerRel($page2, 'next'))->toBe(url('/fatawa-topics-5-3.htm'))
        ->and(fatwaPagerHrefs($page2))->toContain(url('/fatawa-topics-5-1.htm'))
        ->and(fatwaPagerRel($page3, 'prev'))->toBe(url('/fatawa-topics-5-2.htm'))
        ->and(fatwaPagerRel($page3, 'next'))->toBeNull()
        ->and($page3)->toContain('aria-current="page"')
        ->and($page2.$page3)->not->toContain('?page=');
});

it('fatawa-category-{cat}.htm still serves page 1 and its pager links to the fatawa-topics segment pages', function () {
    $content = $this->get('/fatawa-category-5.htm')->assertOk()->getContent();

    expect(fatwaPagedIds($content, '/href="\/fatawa-group-(\d+)-5\.htm"/'))->toHaveCount(25)->toContain(1)
        ->and(fatwaPagerRel($content, 'next'))->toBe(url('/fatawa-topics-5-2.htm'))
        ->and($content)->not->toContain('?page=');
});

it('a category of 25 topics or fewer renders no pager', function () {
    $content = $this->get('/fatawa-topics-6-1.htm')->assertOk()->getContent();

    expect(fatwaPagedIds($content, '/href="\/fatawa-group-(\d+)-6\.htm"/'))->toBe([13, 26, 39, 52, 65])
        ->and($content)->not->toContain('class="w2a-pagination"');
});

// ─── B. Topic → General Questions ───────────────────────────────────────────

it('topic pager links to fatawa-group-{t}-{c}-{n}.htm route segments, never ?page=', function () {
    $content = $this->get('/fatawa-group-1-5.htm')->assertOk()->getContent();
    $hrefs = fatwaPagerHrefs($content);

    expect($hrefs)->toContain(url('/fatawa-group-1-5-2.htm'))
        ->and(fatwaPagerRel($content, 'next'))->toBe(url('/fatawa-group-1-5-2.htm'))
        ->and(fatwaPagerRel($content, 'prev'))->toBeNull()
        ->and(implode(' ', $hrefs))->not->toContain('?page=')
        ->and($content)->not->toContain('?page=');
});

it('topic route pages return distinct question sets with membership and id order unchanged', function () {
    $members = [...range(1, 30), 32];
    $pattern = '/href="\/fatawa-all-(\d+)\.htm"/';

    $first = fatwaPagedIds($this->get('/fatawa-group-1-5.htm')->getContent(), $pattern);
    $explicitFirst = fatwaPagedIds($this->get('/fatawa-group-1-5-1.htm')->getContent(), $pattern);
    $last = fatwaPagedIds($this->get('/fatawa-group-1-5-2.htm')->getContent(), $pattern);

    expect($first)->toBe(array_slice($members, 0, 25))
        ->and($explicitFirst)->toBe($first)
        ->and($last)->toBe(array_slice($members, 25))
        ->and($last)->toContain(32)->not->toContain(31)->not->toContain(33)
        ->and(array_merge($first, $last))->toBe($members);
});

it('topic pager navigates backward by route segment from the last page', function () {
    $last = $this->get('/fatawa-group-1-5-2.htm')->assertOk()->getContent();

    expect(fatwaPagerRel($last, 'prev'))->toBe(url('/fatawa-group-1-5-1.htm'))
        ->and(fatwaPagerRel($last, 'next'))->toBeNull()
        ->and(fatwaPagerHrefs($last))->toBe([url('/fatawa-group-1-5-1.htm')])
        ->and($last)->not->toContain('?page=');
});

it('a topic of 25 questions or fewer renders no pager', function () {
    $content = $this->get('/fatawa-group-9-5.htm')->assertOk()->getContent();

    expect(fatwaPagedIds($content, '/href="\/fatawa-all-(\d+)\.htm"/'))->toBe([32])
        ->and($content)->not->toContain('class="w2a-pagination"');
});
