<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForTelawah(): void
{
    InMemoryConnection::setup('main', [
        'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForTelawah();
});

it('authors index: lists only top-level (parent_id=0) groups', function () {
    DB::connection('main')->table('nuke_telawah_groups')->insert([
        ['id' => 1, 'title' => 'Top Level Reader', 'parent_id' => 0],
        ['id' => 2, 'title' => 'Nested Group', 'parent_id' => 1],
    ]);

    $response = $this->get('/recite.htm');

    $response->assertOk()->assertSee('Top Level Reader')->assertDontSee('Nested Group');
});

it('group show: renders sub-groups and sorah-ordered items', function () {
    DB::connection('main')->table('nuke_telawah_groups')->insert([
        ['id' => 1, 'title' => 'Reader One', 'parent_id' => 0],
        ['id' => 2, 'title' => 'Sub Group', 'parent_id' => 1],
    ]);
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        ['id' => 1, 'title' => 'Al-Baqarah', 'group_id' => 1, 'sorah' => 2],
        ['id' => 2, 'title' => 'Al-Fatiha', 'group_id' => 1, 'sorah' => 1],
    ]);

    $content = $this->get('/recite-group-1.htm')->assertOk()->assertSee('Sub Group')->getContent();

    expect(strpos($content, 'Al-Fatiha'))->toBeLessThan(strpos($content, 'Al-Baqarah'));
    expect($content)
        ->toContain('class="w2a-qualities-list"')
        ->toContain("w2a_play(2, 'telawat')")
        ->toContain('/recite-download-2.htm');
});

it('group show: 404s for a nonexistent group', function () {
    $this->get('/recite-group-999.htm')->assertNotFound();
});

// ---- G-13-07 (media/visual parity phase): telawah/functions.php:164's hardcoded images/telawah.gif ----

it('authors index: G-13-07 — each reader card shows the hardcoded telawah.gif, matching list_telawat_groups() exactly (no per-reader image field)', function () {
    DB::connection('main')->table('nuke_telawah_groups')->insert(['id' => 1, 'title' => 'Top Level Reader', 'parent_id' => 0]);

    $this->get('/recite.htm')->assertOk()->assertSee('/images/telawah.gif', false);
});

it('group show: G-13-07 — sub-group rows show the hardcoded telawah.gif too', function () {
    DB::connection('main')->table('nuke_telawah_groups')->insert([
        ['id' => 1, 'title' => 'Reader One', 'parent_id' => 0],
        ['id' => 2, 'title' => 'Sub Group', 'parent_id' => 1],
    ]);

    $this->get('/recite-group-1.htm')->assertOk()->assertSee('/images/telawah.gif', false);
});

// ---- recite.htm parity: one outer portlet containing a flat card grid, not one portlet per reader ----

it('authors index: renders exactly one portlet with a searchable premium reciter grid', function () {
    DB::connection('main')->table('nuke_telawah_groups')->insert([
        ['id' => 1, 'title' => 'Reader One', 'parent_id' => 0, 'hits' => 5, 'child' => 2, 'telawah' => 10],
        ['id' => 2, 'title' => 'Reader Two', 'parent_id' => 0, 'hits' => 7, 'child' => 0, 'telawah' => 3],
    ]);

    $content = $this->get('/recite.htm')->assertOk()->getContent();

    expect(substr_count($content, 'portlet-title'))->toBe(1)
        ->and(substr_count($content, 'class="w2a-reciter-card"'))->toBe(2)
        ->and($content)->toContain('w2a_reciter_search_input')
        ->and($content)->toContain('2 قارئ')
        ->and($content)->toContain('fa-users');
});

it('authors index: premium card metadata includes counts, the new fallback description, and truncation', function () {
    DB::connection('main')->table('nuke_telawah_groups')->insert([
        ['id' => 1, 'title' => 'No Comment Reader', 'parent_id' => 0, 'hits' => 100, 'child' => 3, 'telawah' => 25, 'des' => ''],
        ['id' => 2, 'title' => 'Long Comment Reader', 'parent_id' => 0, 'hits' => 1, 'child' => 0, 'telawah' => 1,
            'des' => str_repeat('كلمة ', 30), // >90 bytes, must truncate at a word boundary with "..."
        ],
    ]);

    $content = $this->get('/recite.htm')->assertOk()->getContent();

    expect($content)->toContain('3 قسم فرعي')
        ->and($content)->toContain('25 تلاوة')
        ->and($content)->toContain('100 زيارة')
        ->and($content)->toContain('تلاوات قرآنية خاشعة ومجودة')
        ->and($content)->toContain('...'); // the long comment must be truncated, not shown in full
});

it('group show: subgroups reuse the searchable reciter-card component', function () {
    DB::connection('main')->table('nuke_telawah_groups')->insert([
        ['id' => 1, 'title' => 'Reader One', 'parent_id' => 0, 'hits' => 0, 'child' => 1, 'telawah' => 0],
        ['id' => 2, 'title' => 'Sub Group', 'parent_id' => 1, 'hits' => 7, 'child' => 0, 'telawah' => 3],
    ]);

    $content = $this->get('/recite-group-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('class="w2a-reciter-card"')
        ->toContain('data-title="Sub Group"')
        ->toContain('1 قسم فرعي');
});

it('item show: renders details WITHOUT incrementing hits — legacy never does either', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->insert(['id' => 1, 'title' => 'A Recitation', 'hits' => 7]);

    $this->get('/recite-item-1.htm')->assertOk()->assertSee('A Recitation');

    expect(DB::connection('main')->table('nuke_telawah_telawah')->find(1)->hits)->toBe(7);
});

it('item show: renders the premium details, player, and metadata sidebars', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        ['id' => 1, 'title' => 'A Recitation', 'link' => 'https://example.com/a.mp3', 'linksize' => 1024, 'hits' => 7, 'downcount' => 3, 'mytime' => 100],
        ['id' => 2, 'title' => 'Popular Recitation', 'link' => '', 'linksize' => 0, 'hits' => 90, 'downcount' => 12, 'mytime' => 50],
        ['id' => 3, 'title' => 'Recent Recitation', 'link' => '', 'linksize' => 0, 'hits' => 1, 'downcount' => 2, 'mytime' => 200],
    ]);

    $content = $this->get('/recite-item-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('class="w2a-item-details-card"')
        ->toContain("w2a_play(1,'telawat')")
        ->toContain('id="the_main_player"')
        ->toContain('class="w2a-chat-sidebar-list"')
        ->toContain('12 مرة')
        ->toContain('function w2a_play(id, type)');
});

// ---- Shared Page Chrome Parity Audit: recite.htm's heading vs document <title> are genuinely different strings ----

it('authors index: document <title> ("قسم التلاوات") and visible heading ("قائمة القراء بقسم التلاوات") are different, real strings — not the same text reused', function () {
    $content = $this->get('/recite.htm')->assertOk()->getContent();

    expect($content)->toContain('<title>قسم التلاوات - ')
        ->and($content)->toContain('<h3 class="page-title">قائمة القراء بقسم التلاوات</h3>')
        ->and($content)->not->toContain('<title>قائمة القراء بقسم التلاوات - ');
});

it('authors index: breadcrumb — التلاوات is a real (empty-href) link, قائمة القراء is plain text with the trailing empty icon', function () {
    $content = $this->get('/recite.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<li><a href="">التلاوات</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li>قائمة القراء<i class=""></i></li>');
});

it('item download: redirects to the raw link (no https rewrite, unlike anasheed) and increments downcount', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        'id' => 1, 'title' => 'A Recitation', 'link' => 'http://example.com/a.mp3', 'downcount' => 1,
    ]);

    $this->get('/recite-download-1.htm')->assertRedirect('http://example.com/a.mp3');

    expect(DB::connection('main')->table('nuke_telawah_telawah')->find(1)->downcount)->toBe(2);
});
