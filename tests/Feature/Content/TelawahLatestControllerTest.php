<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Repair Batch 1 (decision-log #52) — /recite-news.htm, Sitewide Internal
 * 404 Audit finding #1 (MISSING_MIGRATION_ROUTE), now implemented.
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
    ]);
});

it('lists the 24 most recently added telawah items, newest first, joined with the group', function () {
    $db = DB::connection('main');
    $db->table('nuke_telawah_groups')->insert(['id' => 1, 'title' => 'Group One']);
    $rows = [];
    for ($i = 1; $i <= 30; $i++) {
        $rows[] = ['id' => $i, 'group_id' => 1, 'title' => "Telawah {$i}", 'hits' => $i * 10];
    }
    $db->table('nuke_telawah_telawah')->insert($rows);

    $response = $this->get('/recite-news.htm');

    $response->assertOk()
        ->assertSee('Telawah 30')
        ->assertSee('/recite-item-30.htm', false)
        ->assertSee('/recite-group-1.htm', false)
        ->assertSee('Group One')
        // more.php's real LIMIT 24 — only the 24 newest ids should appear.
        ->assertDontSee('Telawah 6 ')
        ->assertDontSee('Telawah 1<');
});

it('shows the hit count per row, matching more.php:50\'s real "الزيارات" field', function () {
    $db = DB::connection('main');
    $db->table('nuke_telawah_groups')->insert(['id' => 1, 'title' => 'Group One']);
    $db->table('nuke_telawah_telawah')->insert(['id' => 1, 'group_id' => 1, 'title' => 'Telawah One', 'hits' => 777]);

    $this->get('/recite-news.htm')->assertOk()->assertSee('777');
});

it('the "الأكثر تحميلا" sidebar reuses ContentSidebarWidget::telawahMostDownloaded() (hits DESC, top 10) and links to recite-item-{id}.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_telawah_groups')->insert(['id' => 1, 'title' => 'Group']);
    $db->table('nuke_telawah_telawah')->insert([
        ['id' => 1, 'group_id' => 1, 'title' => 'Low hits', 'hits' => 1],
        ['id' => 2, 'group_id' => 1, 'title' => 'High hits', 'hits' => 999],
    ]);

    $content = $this->get('/recite-news.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('downloaded_list')
        ->toContain('High hits')
        ->toContain('/recite-item-2.htm');
});

it('renders no sidebar portlet at all when there are zero telawah rows, matching most_downloaded_list()\'s own if($TotalList>0) wrapping the whole box', function () {
    $content = $this->get('/recite-news.htm')->assertOk()->getContent();

    expect($content)->not->toContain('downloaded_list');
});

it('page-specific CSS (fatawa/css/new-style.css) is loaded', function () {
    $this->get('/recite-news.htm')
        ->assertOk()
        ->assertSee('<link rel="stylesheet" href="/fatawa/css/new-style.css">', false);
});

// The homepage-still-generates-this-link regression check lives in
// HomeControllerTest.php, alongside its own existing fixture setup,
// rather than duplicating that page's heavier fixture stack here.
