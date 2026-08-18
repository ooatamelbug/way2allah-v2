<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * G-07-03 (Phase 1 audit) — `fatawa-by-authers.htm`, previously entirely
 * unbuilt despite real, complete surviving source
 * (`fatawa/fatawa-by-authers.php`'s default branch).
 */
function useInMemoryMainConnectionForFatwaByAuthorsController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaByAuthorsController();
});

it('lists only authors who have at least one fatwa answer, via the INNER JOIN, ordered by name ASC', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Zaid', 'prename' => 'Dr.'],
        ['id' => 2, 'name' => 'Ahmed', 'prename' => 'Sheikh'],
        ['id' => 3, 'name' => 'No Fatwa Author', 'prename' => 'Dr.'],
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1],
        ['id' => 2, 'auther_id' => 2],
    ]);

    $content = $this->get('/fatawa-by-authers.htm')->assertOk()->getContent();

    expect($content)->toContain('Zaid')->toContain('Ahmed')->not->toContain('No Fatwa Author');
    // ORDER BY name ASC (plain, not BINARY) — Ahmed before Zaid.
    expect(strpos($content, 'Ahmed'))->toBeLessThan(strpos($content, 'Zaid'));
});

it('shows the correct per-author answer count (COUNT of matched fatwa_questions rows, via GROUP BY)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Prolific Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1],
        ['id' => 2, 'auther_id' => 1],
        ['id' => 3, 'auther_id' => 1],
    ]);

    $this->get('/fatawa-by-authers.htm')->assertOk()->assertSee('3 فتوى');
});

it('links each author to their auther-questions-{id}.htm page', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 42, 'name' => 'Linked Author', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 42]);

    $this->get('/fatawa-by-authers.htm')
        ->assertOk()
        ->assertSee('href="/auther-questions-42.htm"', false);
});

it('a hidden=1 author with a fatwa answer still appears — legacy\'s own default branch has no hidden=0 filter, reproduced exactly, not fixed', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Hidden But Has Fatwa', 'prename' => 'Dr.', 'hidden' => 1]);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 1]);

    $this->get('/fatawa-by-authers.htm')->assertOk()->assertSee('Hidden But Has Fatwa');
});

it('groups authors under their first Arabic letter, normalizing ه to هـ', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'هشام', 'prename' => 'Dr.'],
    ]);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 1]);

    $this->get('/fatawa-by-authers.htm')->assertOk()->assertSee('هـ');
});

it('the video/audio/pdf branches are not reachable via this route — no op parameter changes the result', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Fatwa Author', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 1]);

    // .htaccess:279's op=fatawa_by_authers never becomes 'video'/'audio'/
    // 'pdf' (the missing modules.php dispatcher is the only thing that
    // would ever pass those) — a query-string op is not consulted here,
    // matching legacy exactly.
    $content = $this->get('/fatawa-by-authers.htm?op=video')->assertOk()->getContent();

    expect($content)->toContain('Fatwa Author');
});
