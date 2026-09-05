<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Enhancement Batch E-10 — the category tree pages used to locate each
 * node's children by re-scanning the whole flat collection: five
 * `where('main_cat', …)`/`contains('main_cat', …)` calls per template,
 * 535 full scans and 284,085 element visits to render one page. The
 * controller now groups the rows by `main_cat` once and the templates
 * resolve children by key.
 *
 * These tests protect the *structure* that change could plausibly break —
 * which node's children appear under which parent, whether a node is
 * treated as having children at all, and the ordering — rather than
 * asserting wall-clock timings, which would be flaky and would not catch
 * a wrong-parent bug anyway.
 */
function useInMemoryMainConnectionForGroupedLookup(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
    ]);
}

/** A three-level tree: 1 → 2 → {3,4}, plus childless top-level 5. */
function seedThreeLevelTree(string $countColumn = 'video_count'): void
{
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Top With Children', 'main_cat' => 0, $countColumn => 1],
        ['id' => 2, 'title' => 'Middle', 'main_cat' => 1, $countColumn => 1],
        ['id' => 3, 'title' => 'Leaf Alpha', 'main_cat' => 2, $countColumn => 1],
        ['id' => 4, 'title' => 'Leaf Beta', 'main_cat' => 2, $countColumn => 1],
        ['id' => 5, 'title' => 'Zulu Childless Top', 'main_cat' => 0, $countColumn => 1],
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForGroupedLookup();
});

it('renders top-level categories, which are the rows whose main_cat is 0', function () {
    seedThreeLevelTree();

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    // Both top-level nodes appear; the grouped lookup must key `0` correctly
    // even though 0 is also a valid PHP array-key edge case.
    expect($content)->toContain('href="/category-1.htm"')
        ->toContain('href="/category-5.htm"');
});

it('nests each child under its own parent, not under a different one', function () {
    // The mutation this catches: resolving children with the wrong parent id
    // (e.g. $topLevel->id where $group->id was meant). Both ids exist, so a
    // swap would still render *something* — the nesting is what proves it.
    seedThreeLevelTree();

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    $middle = strpos($content, 'href="/category-2.htm"');
    $leafA = strpos($content, 'href="/category-3.htm"');
    $childlessTop = strpos($content, 'href="/category-5.htm"');

    // Level-3 leaves sit inside the level-2 node's container, which itself
    // sits inside the level-1 node — so document order must be 1, 2, 3.
    expect($middle)->toBeLessThan($leafA)
        // and the whole of that subtree precedes the next top-level sibling
        ->and($leafA)->toBeLessThan($childlessTop);
});

it('renders third-level children where they exist', function () {
    seedThreeLevelTree();

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/category-3.htm"')->toContain('Leaf Alpha')
        ->toContain('href="/category-4.htm"')->toContain('Leaf Beta')
        ->and(substr_count($content, 'class="w2a-tree-node level-3'))->toBe(2);
});

it('treats a node with children and a node without differently', function () {
    // This is the `has()` check that replaced `contains()`. Inverting it
    // would give every node the wrong markup branch, so assert both sides.
    seedThreeLevelTree();

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    // A parent renders the expandable control...
    expect($content)->toContain('aria-controls="w2a-tree-children-1"')
        ->toContain('class="w2a-tree-link">Top With Children</a>');

    // ...a childless top-level node renders the plain link branch instead:
    // no checkbox/label for it, and its anchor carries no catAncor class.
    expect($content)->not->toContain('aria-controls="w2a-tree-children-5"')
        ->toContain('href="/category-5.htm" class="w2a-tree-link"');
});

it('renders a childless top-level category as a plain leaf with no child container', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 9, 'title' => 'Only Node', 'main_cat' => 0, 'video_count' => 1],
    ]);

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/category-9.htm"')->toContain('Only Node')
        ->not->toContain('aria-controls="w2a-tree-children-9"');
});

it('preserves the title ASC, id DESC ordering among siblings', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Parent', 'main_cat' => 0, 'video_count' => 1],
        ['id' => 10, 'title' => 'Bravo', 'main_cat' => 1, 'video_count' => 1],
        ['id' => 11, 'title' => 'Alpha', 'main_cat' => 1, 'video_count' => 1],
        ['id' => 12, 'title' => 'Charlie', 'main_cat' => 1, 'video_count' => 1],
    ]);

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    // groupBy preserves the collection's order within each group, so the
    // query's ORDER BY must still govern sibling order after grouping.
    expect(strpos($content, 'Alpha'))->toBeLessThan(strpos($content, 'Bravo'))
        ->and(strpos($content, 'Bravo'))->toBeLessThan(strpos($content, 'Charlie'));
});

it('preserves every category link exactly once per node', function () {
    seedThreeLevelTree();

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    foreach ([1, 2, 3, 4, 5] as $id) {
        expect(substr_count($content, "href=\"/category-{$id}.htm\""))->toBe(1);
    }
});

it('groups correctly when main_cat arrives as a numeric string rather than an int', function () {
    // Key-type safety: `main_cat` is int(11) NOT NULL on MySQL, but the
    // grouped lookup must not depend on that — PHP coerces numeric-string
    // array keys to ints, and this asserts the behaviour rather than
    // trusting it. A driver returning strings must produce the same tree.
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Parent', 'main_cat' => '0', 'video_count' => 1],
        ['id' => 2, 'title' => 'Child', 'main_cat' => '1', 'video_count' => 1],
    ]);

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/category-1.htm"')
        ->toContain('href="/category-2.htm"')
        // the child must be nested, i.e. the parent is recognised as having children
        ->toContain('aria-controls="w2a-tree-children-1"')
        ->and(strpos($content, 'href="/category-1.htm"'))->toBeLessThan(strpos($content, 'href="/category-2.htm"'));
});

it('applies the same grouped lookup on the anasheed and fatawa sibling trees', function () {
    // All three templates received the identical change; the two siblings
    // filter on different count columns and emit different slugs.
    useInMemoryMainConnectionForGroupedLookup();
    seedThreeLevelTree('anasheed_count');
    $anasheed = $this->get('/var-categories.htm')->assertOk()->getContent();

    useInMemoryMainConnectionForGroupedLookup();
    seedThreeLevelTree('q_count');
    $fatawa = $this->get('/fatawa-categories.htm')->assertOk()->getContent();

    expect($anasheed)->toContain('href="/var-category-3.htm"')
        ->and(substr_count($anasheed, 'class="w2a-tree-node level-3'))->toBe(2);
    expect($fatawa)->toContain('href="/fatawa-category-3.htm"')
        ->and(substr_count($fatawa, 'class="w2a-tree-node level-3'))->toBe(2);
});

it('issues exactly one query per tree page — grouping adds no database work', function () {
    seedThreeLevelTree();
    $count = 0;
    DB::connection('main')->listen(function () use (&$count) {
        $count++;
    });

    $this->get('/categories.htm')->assertOk();

    expect($count)->toBe(1);
});
