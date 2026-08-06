<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForW2acd(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2acd_w2acd' => MainSchema::nukeW2acdW2acd(),
        'nuke_w2acd_groups' => MainSchema::nukeW2acdGroups(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForW2acd();
});

it('index: IF-025 fix — a specific group\'s page only lists that group\'s items', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        ['id' => 1, 'group_id' => 5, 'title' => 'In Group Five'],
        ['id' => 2, 'group_id' => 9, 'title' => 'In Group Nine'],
    ]);

    $content = $this->get('/w2acd/cds.php?id=5')->assertOk()->getContent();

    // The sidebar's "Most Downloaded"/"Newest" boxes are legitimately
    // group-unscoped (the legacy $Group parameter they take is confirmed
    // dead — ContentSidebarWidget's own docblock, P-016 §2), so the
    // assertion is scoped to the main list section only.
    preg_match('/قائمة الإسطوانات">(.*?)<\/section>/s', $content, $matches);
    $listSection = $matches[1] ?? '';

    expect($listSection)->toContain('In Group Five');
    expect($listSection)->not->toContain('In Group Nine');
});

it('index: IF-025 fix — visiting a group increments THAT group\'s hits, not group 0', function () {
    DB::connection('main')->table('nuke_w2acd_groups')->insert([
        ['id' => 0, 'title' => 'Root', 'hits' => 0],
        ['id' => 5, 'title' => 'Group Five', 'hits' => 10],
    ]);

    $this->get('/w2acd/cds.php?id=5')->assertOk();

    expect(DB::connection('main')->table('nuke_w2acd_groups')->find(5)->hits)->toBe(11);
    expect(DB::connection('main')->table('nuke_w2acd_groups')->find(0)->hits)->toBe(0);
});

it('index: with no id, lists group 0 (the root) and does not increment any group', function () {
    DB::connection('main')->table('nuke_w2acd_groups')->insert(['id' => 0, 'title' => 'Root', 'hits' => 5]);
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'group_id' => 0, 'title' => 'Root Item']);

    $this->get('/w2acd/cds.php')->assertOk()->assertSee('Root Item');

    expect(DB::connection('main')->table('nuke_w2acd_groups')->find(0)->hits)->toBe(5);
});

it('show: renders item details, mirror links, and increments hits', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'A CD', 'link' => 'https://example.com/a.mp3,https://example.com/b.mp3',
        'cd' => 'Part 1,Part 2', 'hits' => 3,
    ]);

    $response = $this->get('/w2acd/item.php?khid=1');

    $response->assertOk()->assertSee('A CD')->assertSee('Part 1')->assertSee('Part 2');
    expect(DB::connection('main')->table('nuke_w2acd_w2acd')->find(1)->hits)->toBe(4);
});

it('show: sidebar links use the correct cds-item- prefix, not the legacy var-item- bug', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        ['id' => 1, 'title' => 'Viewed Item', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only', 'hits' => 0],
        ['id' => 2, 'title' => 'Popular Item', 'link' => '', 'cd' => '', 'hits' => 999],
    ]);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect($content)->toContain('cds-item-2.htm');
    expect($content)->not->toContain('var-item-');
});

it('show: hidden items remain viewable but suppress the image gallery, matching legacy exactly', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'Hidden CD', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only',
        'thumbnail' => 'a.jpg', 'hidden' => 1,
    ]);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect($content)->toContain('Hidden CD');
    expect($content)->not->toContain('cds_image2/a.jpg');
});
